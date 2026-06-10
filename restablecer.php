<?php
require_once 'config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

$page_title = "Nueva Contraseña";
$error = '';
$success = '';

$token = (string)($_POST['token'] ?? ($_GET['token'] ?? ''));
$resetRequest = null;

// Verificar token
if (false && (empty($token) || !isset($_SESSION['rec_token']) || $_SESSION['rec_token'] !== $token)) {
    $error = 'âŒ Enlace inválido o expirado';
} elseif (false && isset($_SESSION['rec_expira']) && $_SESSION['rec_expira'] < time()) {
    $error = 'âŒ El enlace ha expirado (30 minutos)';
}

// Cambiar contraseña
if ($token === '' || !preg_match('/^[a-f0-9]{64}$/i', $token)) {
    $error = 'Enlace invalido o expirado';
} else {
    $stmt = $conexion->prepare("
        SELECT id, usuario_id, fecha_solicitud, estado
        FROM recuperaciones_pendientes
        WHERE token = ?
          AND estado IN ('pendiente', 'enviado')
          AND fecha_solicitud >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
        LIMIT 1
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $resetRequest = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$resetRequest) {
        $error = 'Enlace invalido o expirado';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    $password = $_POST['password'];
    $confirm = $_POST['password_confirm'];
    
    if (strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres';
    } elseif ($password !== $confirm) {
        $error = 'Las contraseñas no coinciden';
    } elseif ($resetRequest) {
        // Actualizar contraseña
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conexion->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
        $usuarioId = (int)$resetRequest['usuario_id'];
        $stmt->bind_param("si", $hash, $usuarioId);
        
        if ($stmt->execute()) {
            $success = 'OK Contraseña actualizada correctamente';
            // Limpiar sesión
            $del = $conexion->prepare("DELETE FROM recuperaciones_pendientes WHERE id = ?");
            $requestId = (int)$resetRequest['id'];
            $del->bind_param("i", $requestId);
            $del->execute();
            $del->close();
            unset($_SESSION['rec_token'], $_SESSION['rec_usuario_id'], $_SESSION['rec_whatsapp'], $_SESSION['rec_expira']);
        } else {
            $error = 'âŒ Error al actualizar la contraseña';
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { 
            background: linear-gradient(135deg, #0d0f14 0%, #11131a 35%, #0b0c11 100%);
            color: #e5e5e5; min-height: 100vh; display: flex; justify-content: center; align-items: center;
            padding: 20px;
        }
        .container { width: 100%; max-width: 450px; }
        .card { 
            background: rgba(255,255,255,0.05); border-radius: 20px; padding: 35px; 
            border: 1px solid rgba(255,255,255,0.1); 
        }
        .logo { text-align: center; margin-bottom: 25px; }
        .logo h1 { 
            font-size: 2rem; font-weight: 800; margin-bottom: 8px;
            background: linear-gradient(135deg, #12aaff, #0de0c9);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .logo p { color: #aaa; font-size: 0.95rem; }
        .alert { 
            padding: 14px; border-radius: 12px; margin-bottom: 20px; font-size: 0.95rem;
            display: flex; align-items: center; gap: 12px;
        }
        .alert.error { background: rgba(255,59,48,0.15); border: 1px solid rgba(255,59,48,0.3); color: #ff3b30; }
        .alert.success { background: rgba(52,199,89,0.15); border: 1px solid rgba(52,199,89,0.3); color: #34c759; }
        .form-group { margin-bottom: 20px; }
        label { display: block; color: #d0d0d0; margin-bottom: 8px; font-weight: 500; }
        .password-container { position: relative; }
        .password-container input {
            width: 100%; padding: 12px 45px 12px 15px; background: rgba(0,0,0,0.3);
            border: 1px solid rgba(255,255,255,0.1); border-radius: 10px;
            color: #fff; font-size: 1rem; outline: none;
        }
        .password-container input:focus { border-color: #12aaff; }
        .toggle-password {
            position: absolute; right: 15px; top: 50%; transform: translateY(-50%);
            background: none; border: none; color: #666; cursor: pointer;
            font-size: 1rem; padding: 5px;
        }
        .btn { 
            width: 100%; padding: 15px; border: none; border-radius: 12px; 
            font-size: 1rem; font-weight: 600; cursor: pointer; transition: all 0.3s ease;
            display: flex; justify-content: center; align-items: center; gap: 10px;
        }
        .btn-primary { background: linear-gradient(135deg, #12aaff, #0de0c9); color: #0d0f14; }
        .btn-primary:hover { transform: translateY(-2px); }
        .links { text-align: center; margin-top: 25px; }
        .links a { color: #12aaff; text-decoration: none; font-size: 0.95rem; }
        .links a:hover { text-decoration: underline; }
    </style>
    <link rel="stylesheet" href="assets/css/auth-responsive.css">
    <link rel="stylesheet" href="assets/css/mobile-urgent.css?v=20260610">
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="logo">
                <h1><i class="fas fa-lock"></i> Nueva Contraseña</h1>
                <p>Monkeystraming</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
                <div style="text-align: center; margin-top: 20px;">
                    <a href="recuperar.php" class="btn btn-primary">
                        <i class="fab fa-whatsapp"></i> Solicitar nuevo enlace
                    </a>
                </div>
            <?php elseif ($success): ?>
                <div class="alert success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
                <div style="text-align: center; margin-top: 20px;">
                    <a href="login.php" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Ir al Login
                    </a>
                </div>
            <?php else: ?>
            <form method="POST" action="">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="form-group">
                    <label><i class="fas fa-key"></i> Nueva Contraseña</label>
                    <div class="password-container">
                        <input type="password" name="password" id="password" 
                               placeholder="Mínimo 6 caracteres" required minlength="6">
                        <button type="button" class="toggle-password" onclick="togglePassword('password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-key"></i> Confirmar Contraseña</label>
                    <div class="password-container">
                        <input type="password" name="password_confirm" id="password_confirm" 
                               placeholder="Repite la contraseña" required minlength="6">
                        <button type="button" class="toggle-password" onclick="togglePassword('password_confirm')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Cambiar Contraseña
                </button>
            </form>
            <?php endif; ?>
            
            <div class="links">
                <a href="login.php"><i class="fas fa-arrow-left"></i> Volver al login</a>
            </div>
        </div>
    </div>
    
    <script>
    function togglePassword(id) {
        const input = document.getElementById(id);
        const icon = input.nextElementSibling.querySelector('i');
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'fas fa-eye-slash';
        } else {
            input.type = 'password';
            icon.className = 'fas fa-eye';
        }
    }
    </script>
</body>
</html>
