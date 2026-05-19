<?php
require_once 'config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

$page_title = "Recuperar Contraseña";
$error = '';
$success = '';
$mostrar_formulario = true;
$whatsapp_url = '';
$solicitud_id = '';
$nombre_usuario = '';

// WhatsApp del admin
$ADMIN_WHATSAPP = '51964279873';

if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['whatsapp'])) {
    $whatsapp_input = trim($_POST['whatsapp']);
    
    // Validar número
    if (empty($whatsapp_input) || !preg_match('/^9[0-9]{8}$/', $whatsapp_input)) {
        $error = '❌ Ingresa un número de WhatsApp válido de 9 dígitos (ej: 987654321)';
    } else {
        // Buscar usuario
        $whatsapp_con_codigo = '+51' . $whatsapp_input;
        $whatsapp_solo_numero = $whatsapp_input;
        $whatsapp_con_51 = '51' . $whatsapp_input;
        
        $sql = "SELECT id, nombre, email, whatsapp FROM usuarios 
                WHERE whatsapp = ? OR whatsapp = ? OR whatsapp = ? 
                LIMIT 1";
        
        $stmt = $conexion->prepare($sql);
        
        if ($stmt) {
            $stmt->bind_param("sss", 
                $whatsapp_con_codigo,
                $whatsapp_solo_numero,
                $whatsapp_con_51
            );
            
            $stmt->execute();
            $result = $stmt->get_result();
            $usuario = $result->fetch_assoc();
            $stmt->close();
            
            if ($usuario) {
                // Guardar datos del usuario
                $nombre_usuario = $usuario['nombre'];
                
                // Crear mensaje SIMPLE para el admin
                $mensaje_admin = "Hola soy " . $usuario['nombre'] . " eh solicitado mi cambio de contraseña";
                
                // Crear URL de WhatsApp
                $whatsapp_url = "https://wa.me/$ADMIN_WHATSAPP?text=" . urlencode($mensaje_admin);
                
                // Guardar en BD para el panel del admin
                try {
                    // Crear tabla si no existe
                    $sql_check = "SHOW TABLES LIKE 'recuperaciones_pendientes'";
                    $check_result = $conexion->query($sql_check);
                    
                    if ($check_result->num_rows == 0) {
                        $sql_create = "CREATE TABLE recuperaciones_pendientes (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            usuario_id INT NOT NULL,
                            whatsapp VARCHAR(20) NOT NULL,
                            nombre_usuario VARCHAR(100),
                            token VARCHAR(64) UNIQUE,
                            enlace TEXT,
                            estado ENUM('pendiente', 'enviado') DEFAULT 'pendiente',
                            fecha_solicitud DATETIME DEFAULT CURRENT_TIMESTAMP,
                            fecha_envio DATETIME NULL
                        )";
                        $conexion->query($sql_create);
                    }
                    
                    // Generar token único para después
                    $token = bin2hex(random_bytes(32));
                    
                    // Crear enlace para después
                    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                    $host = $_SERVER['HTTP_HOST'];
                    $script = dirname($_SERVER['SCRIPT_NAME']);
                    $enlace = $protocol . "://" . $host . $script . "/restablecer.php?token=" . $token;
                    
                    // Guardar solicitud
                    $sql_insert = "INSERT INTO recuperaciones_pendientes 
                                  (usuario_id, whatsapp, nombre_usuario, token, enlace) 
                                  VALUES (?, ?, ?, ?, ?)";
                    
                    $stmt_insert = $conexion->prepare($sql_insert);
                    $whatsapp_db = '+51' . $whatsapp_input;
                    $stmt_insert->bind_param("issss", 
                        $usuario['id'], 
                        $whatsapp_db, 
                        $usuario['nombre'],
                        $token,
                        $enlace
                    );
                    
                    if ($stmt_insert->execute()) {
                        $solicitud_id = $conexion->insert_id;
                        
                        // Guardar en sesión
                        $_SESSION['rec_token'] = $token;
                        $_SESSION['rec_usuario_id'] = $usuario['id'];
                        $_SESSION['rec_expira'] = time() + 1800;
                    }
                    $stmt_insert->close();
                    
                } catch (Exception $e) {
                    // Si hay error con la BD, continuamos igual
                    error_log("Error BD: " . $e->getMessage());
                }
                
                // Mostrar botón de WhatsApp
                $mostrar_formulario = false;
                $success = '✅ Cuenta encontrada. Notifica al admin.';
                
            } else {
                $error = '❌ No encontramos una cuenta con ese número';
            }
        } else {
            $error = '❌ Error en la base de datos';
        }
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
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { 
            background: linear-gradient(135deg, #0d0f14 0%, #11131a 35%, #0b0c11 100%);
            color: #e5e5e5; min-height: 100vh; display: flex; justify-content: center; align-items: center;
            padding: 20px;
        }
        .container { width: 100%; max-width: 450px; }
        .card { 
            background: rgba(255,255,255,0.05); border-radius: 20px; padding: 35px; 
            border: 1px solid rgba(255,255,255,0.1); box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        .logo { text-align: center; margin-bottom: 25px; }
        .logo h1 { 
            font-size: 2rem; font-weight: 800; margin-bottom: 8px;
            background: linear-gradient(135deg, #25D366, #128C7E);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .logo p { color: #aaa; font-size: 0.95rem; }
        .alert { 
            padding: 14px; border-radius: 12px; margin-bottom: 20px; font-size: 0.95rem;
            display: flex; align-items: flex-start; gap: 12px; line-height: 1.5;
        }
        .alert.error { background: rgba(255,59,48,0.15); border: 1px solid rgba(255,59,48,0.3); color: #ff3b30; }
        .alert.success { background: rgba(52,199,89,0.15); border: 1px solid rgba(52,199,89,0.3); color: #34c759; }
        .alert.info { background: rgba(18,170,255,0.15); border: 1px solid rgba(18,170,255,0.3); color: #12aaff; }
        .form-group { margin-bottom: 20px; }
        label { display: block; color: #d0d0d0; margin-bottom: 8px; font-weight: 500; }
        .input-with-example { display: flex; align-items: center; gap: 10px; }
        .prefix { 
            padding: 12px 15px; background: rgba(37, 211, 102, 0.1); color: #25D366;
            font-weight: 600; border-radius: 10px; border: 1px solid rgba(37, 211, 102, 0.2);
            min-width: 60px; text-align: center;
        }
        input {
            flex: 1; padding: 12px 15px; background: rgba(0,0,0,0.3);
            border: 1px solid rgba(255,255,255,0.1); border-radius: 10px;
            color: #fff; font-size: 1rem; outline: none; transition: all 0.3s ease;
        }
        input:focus { border-color: #25D366; box-shadow: 0 0 0 2px rgba(37, 211, 102, 0.2); }
        .form-text { display: block; margin-top: 8px; color: #777; font-size: 0.85rem; line-height: 1.4; }
        .btn { 
            width: 100%; padding: 15px; border: none; border-radius: 12px; 
            font-size: 1rem; font-weight: 600; cursor: pointer; transition: all 0.3s ease;
            display: flex; justify-content: center; align-items: center; gap: 10px;
            margin-top: 10px;
        }
        .btn-primary { background: linear-gradient(135deg, #25D366, #128C7E); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(37, 211, 102, 0.3); }
        .btn-whatsapp { 
            background: #25D366; color: white; padding: 20px; border-radius: 12px;
            text-decoration: none; display: flex; align-items: center; justify-content: center;
            gap: 15px; font-size: 1.1rem; font-weight: 600; margin: 20px 0;
            box-shadow: 0 8px 25px rgba(37, 211, 102, 0.4); transition: all 0.3s ease;
        }
        .btn-whatsapp:hover { transform: translateY(-3px); box-shadow: 0 12px 30px rgba(37, 211, 102, 0.6); }
        .soporte-box {
            background: rgba(37, 211, 102, 0.08); border-radius: 12px; padding: 15px;
            margin-top: 20px; border-left: 4px solid #25D366;
        }
        .soporte-box h4 { color: #25D366; margin-bottom: 8px; font-size: 1rem; }
        .soporte-box p { color: #aaa; font-size: 0.9rem; line-height: 1.5; margin: 5px 0; }
        .links { text-align: center; margin-top: 25px; }
        .links a { 
            color: #12aaff; text-decoration: none; font-size: 0.95rem;
            display: inline-flex; align-items: center; gap: 6px; margin: 0 10px;
        }
        .links a:hover { text-decoration: underline; }
        .mensaje-preview {
            background: rgba(255,255,255,0.05); border-radius: 10px; padding: 15px;
            margin: 15px 0; border-left: 3px solid #25D366; font-size: 0.9rem;
        }
        .mensaje-preview strong { color: #25D366; }
        .pasos { margin: 20px 0; }
        .paso { display: flex; align-items: center; gap: 15px; margin-bottom: 15px; }
        .paso-num { 
            background: #25D366; color: white; width: 30px; height: 30px;
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-weight: bold;
        }
        .paso-text { flex: 1; }
        .paso-text strong { color: #25D366; }
        .auto-redirect { 
            text-align: center; margin: 15px 0; padding: 10px;
            background: rgba(37, 211, 102, 0.1); border-radius: 8px;
            color: #25D366; font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="logo">
                <h1><i class="fab fa-whatsapp"></i> Recuperar Contraseña</h1>
                <p>Monkeystraming</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert error">
                    <i class="fas fa-exclamation-circle"></i> 
                    <div><?php echo $error; ?></div>
                </div>
            <?php endif; ?>
            
            <?php if (!$mostrar_formulario): ?>
                <!-- MOSTRAR BOTÓN PARA NOTIFICAR AL ADMIN -->
                <div class="alert success">
                    <i class="fas fa-check-circle"></i> 
                    <div>
                        ✅ Hola <strong><?php echo htmlspecialchars($nombre_usuario); ?></strong><br>
                        Tu cuenta ha sido encontrada
                    </div>
                </div>
                
                <div class="mensaje-preview">
                    <strong>Mensaje que enviarás:</strong><br>
                    "Hola soy <?php echo htmlspecialchars($nombre_usuario); ?> eh solicitado mi cambio de contraseña"
                </div>
                
                <a href="<?php echo $whatsapp_url; ?>" target="_blank" class="btn-whatsapp">
                    <i class="fab fa-whatsapp"></i>
                    Notificar al Administrador
                </a>
                
                <div class="auto-redirect" id="auto-redirect">
                    Se abrirá WhatsApp en <span id="segundos">5</span> segundos...
                </div>
                
                <div class="pasos">
                    <div class="paso">
                        <div class="paso-num">1</div>
                        <div class="paso-text">
                            <strong>Envía el mensaje al admin</strong><br>
                            <small>Haz clic en el botón verde de arriba</small>
                        </div>
                    </div>
                    <div class="paso">
                        <div class="paso-num">2</div>
                        <div class="paso-text">
                            <strong>Espera respuesta</strong><br>
                            <small>El admin te enviará el enlace por WhatsApp</small>
                        </div>
                    </div>
                    <div class="paso">
                        <div class="paso-num">3</div>
                        <div class="paso-text">
                            <strong>Cambia tu contraseña</strong><br>
                            <small>Ingresa tu nueva contraseña en el enlace</small>
                        </div>
                    </div>
                </div>
                
                <div class="soporte-box">
                    <h4><i class="fas fa-info-circle"></i> ¿Qué pasa después?</h4>
                    <p>• El admin verá tu solicitud en su panel</p>
                    <p>• Te enviará el enlace de recuperación</p>
                    <p>• El enlace es válido por 30 minutos</p>
                </div>
                
                <div class="links">
                    <a href="recuperar.php"><i class="fas fa-redo"></i> Nueva solicitud</a> | 
                    <a href="login.php"><i class="fas fa-arrow-left"></i> Volver al login</a>
                </div>
                
            <?php else: ?>
                <!-- FORMULARIO INICIAL -->
                <div class="alert info">
                    <i class="fas fa-info-circle"></i>
                    Ingresa los 9 dígitos de tu WhatsApp registrado (sin +51)
                </div>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label><i class="fas fa-phone"></i> Número de WhatsApp</label>
                        <div class="input-with-example">
                            <span class="prefix">+51</span>
                            <input type="text" 
                                   name="whatsapp" 
                                   placeholder="937401236"
                                   maxlength="9"
                                   pattern="[0-9]{9}"
                                   title="9 dígitos sin el +51"
                                   required
                                   value="<?php echo isset($_POST['whatsapp']) ? htmlspecialchars($_POST['whatsapp']) : ''; ?>"
                                   autofocus>
                        </div>
                        <small class="form-text">
                            Solo los <strong>9 dígitos</strong> después del +51
                        </small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Buscar mi cuenta
                    </button>
                </form>
                
                <div class="soporte-box">
                    <h4><i class="fas fa-question-circle"></i> ¿Cómo funciona?</h4>
                    <p>1. Ingresa tu WhatsApp registrado</p>
                    <p>2. Notificas al admin con un clic</p>
                    <p>3. El admin te envía el enlace por WhatsApp</p>
                    <p>4. Cambias tu contraseña</p>
                </div>
                
                <div class="links">
                    <a href="login.php"><i class="fas fa-arrow-left"></i> Volver al login</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
    // Validación en tiempo real
    const inputWhatsapp = document.querySelector('input[name="whatsapp"]');
    if (inputWhatsapp) {
        inputWhatsapp.addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            this.value = value.slice(0, 9);
        });
    }
    
    // Auto-redirección a WhatsApp
    <?php if (!$mostrar_formulario && !empty($whatsapp_url)): ?>
    let segundos = 5;
    const segundosSpan = document.getElementById('segundos');
    
    function redirigirWhatsApp() {
        segundos--;
        segundosSpan.textContent = segundos;
        
        if (segundos <= 0) {
            window.open('<?php echo $whatsapp_url; ?>', '_blank');
        }
    }
    
    setInterval(redirigirWhatsApp, 1000);
    <?php endif; ?>
    </script>
</body>
</html>