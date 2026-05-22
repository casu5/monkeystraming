<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) {
    redirect('../login.php');
    exit();
}

// Datos actuales del usuario
$usuario = getCurrentUser();
if (!$usuario) {
    // Por si acaso algo raro
    redirect('../login.php');
    exit();
}

$page_title   = "Mi perfil";
$success_msg  = '';
$error_msg    = '';
$errors       = [];

if (empty($_SESSION['_csrf_profile'])) {
    $_SESSION['_csrf_profile'] = bin2hex(random_bytes(32));
}
$csrf_profile = $_SESSION['_csrf_profile'];

// Valores por defecto de los campos
$nombre_value = $usuario['nombre'];
$email_value  = $usuario['email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    global $conexion;

    $nombre          = cleanInput($_POST['nombre'] ?? '');
    $email           = cleanInput($_POST['email'] ?? '');
    $password_actual = $_POST['password_actual'] ?? '';
    $password_nueva  = $_POST['password_nueva'] ?? '';
    $password_nueva2 = $_POST['password_nueva2'] ?? '';

    // Mantener valores en el form
    $nombre_value = $nombre;
    $email_value  = $email;

    if (!hash_equals($csrf_profile, (string)($_POST['_csrf'] ?? ''))) {
        $errors[] = "Token invalido. Recarga la pagina e intenta nuevamente.";
    }

    // Validaciones básicas
    if (empty($nombre) || strlen($nombre) < 3) {
        $errors[] = "El nombre debe tener al menos 3 caracteres.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "El correo electrónico no es válido.";
    }

    // Verificar que el email no esté en uso por otro usuario
    if (empty($errors)) {
        $sql_email = "SELECT id FROM usuarios WHERE email = ? AND id <> ?";
        $stmt_email = $conexion->prepare($sql_email);
        $stmt_email->bind_param("si", $email, $usuario['id']);
        $stmt_email->execute();
        $res_email = $stmt_email->get_result();

        if ($res_email->num_rows > 0) {
            $errors[] = "Este correo ya está registrado por otro usuario.";
        }
    }

    // ¿Quieren cambiar contraseña?
    $cambia_password = ($password_nueva !== '' || $password_nueva2 !== '');

    if ($cambia_password) {
        // Debe escribir la actual
        if (empty($password_actual)) {
            $errors[] = "Debes ingresar tu contraseña actual para poder cambiarla.";
        }

        // Validación de nueva contraseña
        if (strlen($password_nueva) < 6) {
            $errors[] = "La nueva contraseña debe tener al menos 6 caracteres.";
        }

        if ($password_nueva !== $password_nueva2) {
            $errors[] = "Las nuevas contraseñas no coinciden.";
        }

        // Comprobar contraseña actual
        if (empty($errors)) {
            $sql_pass = "SELECT password FROM usuarios WHERE id = ?";
            $stmt_pass = $conexion->prepare($sql_pass);
            $stmt_pass->bind_param("i", $usuario['id']);
            $stmt_pass->execute();
            $res_pass = $stmt_pass->get_result();
            $row_pass = $res_pass->fetch_assoc();

            if (!$row_pass || !password_verify($password_actual, $row_pass['password'])) {
                $errors[] = "La contraseña actual no es correcta.";
            }
        }
    }

    // Si no hay errores, actualizar en BD
    if (empty($errors)) {
        if ($cambia_password) {
            $hashed_new = password_hash($password_nueva, PASSWORD_DEFAULT);
            $sql_update = "UPDATE usuarios 
                           SET nombre = ?, email = ?, password = ?, updated_at = NOW()
                           WHERE id = ?";
            $stmt_update = $conexion->prepare($sql_update);
            $stmt_update->bind_param("sssi", $nombre, $email, $hashed_new, $usuario['id']);
        } else {
            $sql_update = "UPDATE usuarios 
                           SET nombre = ?, email = ?, updated_at = NOW()
                           WHERE id = ?";
            $stmt_update = $conexion->prepare($sql_update);
            $stmt_update->bind_param("ssi", $nombre, $email, $usuario['id']);
        }

        if ($stmt_update->execute()) {
            $success_msg = "Datos actualizados correctamente.";

            // Actualizar datos en sesión
            $_SESSION['user_name']  = $nombre;
            $_SESSION['user_email'] = $email;

            // Refrescar datos del usuario
            $usuario = getCurrentUser();
            $nombre_value = $usuario['nombre'];
            $email_value  = $usuario['email'];

            // Limpiar campos de contraseña
            $password_actual = $password_nueva = $password_nueva2 = '';
        } else {
            $error_msg = "Ocurrió un error al actualizar tu perfil. Inténtalo nuevamente.";
        }
    } else {
        // Juntar errores en un solo mensaje
        $error_msg = implode("<br>", $errors);
    }
}

include '../includes/header.php';
?>

<div class="container">
    <div class="user-dashboard">

        <!-- Título y resumen -->
        <div class="welcome-card" style="margin-bottom: 20px;">
            <div class="welcome-content">
                <h1>Mi perfil 👤</h1>
               
            </div>
           
        </div>

        <!-- Mensajes -->
        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo $error_msg; ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo $success_msg; ?></span>
            </div>
        <?php endif; ?>

        <div class="profile-grid">
            <!-- Info de la cuenta -->
            <div class="profile-card">
                <h3><i class="fas fa-id-card"></i> Información de la cuenta</h3>

                <div class="profile-info-row">
                    <span class="label">Usuario:</span>
                    <span class="value"><?php echo htmlspecialchars($usuario['nombre']); ?></span>
                </div>
                <div class="profile-info-row">
                    <span class="label">Correo:</span>
                    <span class="value"><?php echo htmlspecialchars($usuario['email']); ?></span>
                </div>
                <div class="profile-info-row">
                    <span class="label">Rol:</span>
                    <span class="value">
                        <?php echo function_exists('roleLabel') ? roleLabel($usuario['role'] ?? '') : ucfirst((string)($usuario['role'] ?? 'cliente')); ?>
                    </span>
                </div>
                <div class="profile-info-row">
                    <span class="label">Estado:</span>
                    <span class="status-badge status-<?php echo $usuario['estado']; ?>">
                        <?php echo ucfirst($usuario['estado']); ?>
                    </span>
                </div>
                <div class="profile-info-row">
                    <span class="label">Miembro desde:</span>
                    <span class="value">
                        <?php echo date('d/m/Y', strtotime($usuario['created_at'])); ?>
                    </span>
                </div>
                <?php if (!empty($usuario['ultimo_login'])): ?>
                <div class="profile-info-row">
                    <span class="label">Último acceso:</span>
                    <span class="value">
                        <?php echo date('d/m/Y H:i', strtotime($usuario['ultimo_login'])); ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Formulario de edición -->
            <div class="profile-card">
                <h3><i class="fas fa-user-edit"></i> Editar datos</h3>

                <form action="" method="POST" autocomplete="off" class="profile-form">
                    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf_profile, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="form-group">
                        <label for="nombre"><i class="fas fa-user"></i> Nombre</label>
                        <input
                            type="text"
                            id="nombre"
                            name="nombre"
                            value="<?php echo htmlspecialchars($nombre_value); ?>"
                            required
                            minlength="3"
                            maxlength="50"
                        >
                    </div>

                    <div class="form-group">
                        <label for="email"><i class="fas fa-envelope"></i> Correo electrónico</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="<?php echo htmlspecialchars($email_value); ?>"
                            required
                        >
                    </div>

                    <hr class="profile-divider">

                    <h4 class="password-title"><i class="fas fa-lock"></i> Cambiar contraseña</h4>
                    <p class="password-help">
                        Si no quieres cambiar la contraseña, deja estos campos vacíos.
                    </p>

                    <div class="form-group">
                        <label for="password_actual">Contraseña actual</label>
                        <input
                            type="password"
                            id="password_actual"
                            name="password_actual"
                            placeholder="••••••••"
                        >
                    </div>

                    <div class="form-group">
                        <label for="password_nueva">Nueva contraseña</label>
                        <input
                            type="password"
                            id="password_nueva"
                            name="password_nueva"
                            placeholder="Mínimo 6 caracteres"
                        >
                    </div>

                    <div class="form-group">
                        <label for="password_nueva2">Repetir nueva contraseña</label>
                        <input
                            type="password"
                            id="password_nueva2"
                            name="password_nueva2"
                            placeholder="Repite la nueva contraseña"
                        >
                    </div>

                    <button type="submit" class="btn-guardar">
                        <i class="fas fa-save"></i> Guardar cambios
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.container {
    max-width: 1200px;
    margin: 40px auto;
    padding: 0 20px;
}

.user-dashboard {
    display: flex;
    flex-direction: column;
    gap: 30px;
}

/* Mensajes */
.alert {
    padding: 15px 20px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 500;
    font-size: 0.95rem;
}
.alert i {
    font-size: 1.2rem;
}
.alert-error {
    background: rgba(255, 59, 48, 0.18);
    border: 1px solid rgba(255, 59, 48, 0.4);
    color: #ff3b30;
}
.alert-success {
    background: rgba(52, 199, 89, 0.18);
    border: 1px solid rgba(52, 199, 89, 0.4);
    color: #34c759;
}

/* Grid principal */
.profile-grid {
    display: grid;
    grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.1fr);
    gap: 25px;
}
@media (max-width: 900px) {
    .profile-grid {
        grid-template-columns: 1fr;
    }
}

/* Tarjetas */
.profile-card {
    background: rgba(255, 255, 255, 0.04);
    border-radius: 16px;
    padding: 25px;
    border: 1px solid rgba(255, 255, 255, 0.06);
}
.profile-card h3 {
    color: #fff;
    font-size: 1.3rem;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Info de perfil */
.profile-info-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
    font-size: 0.95rem;
}
.profile-info-row .label {
    color: #aaa;
}
.profile-info-row .value {
    color: #fff;
    font-weight: 500;
}

/* Reutilizar badges de estado */
.status-badge {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}
.status-activo {
    background: rgba(52, 199, 89, 0.2);
    color: #34c759;
}
.status-suspendido {
    background: rgba(255, 204, 0, 0.2);
    color: #ffcc00;
}
.status-eliminado {
    background: rgba(255, 59, 48, 0.2);
    color: #ff3b30;
}

/* Formulario */
.profile-form {
    display: flex;
    flex-direction: column;
    gap: 15px;
}
.form-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.form-group label {
    color: #ccc;
    font-size: 0.95rem;
    font-weight: 500;
}
.form-group label i {
    margin-right: 5px;
    color: #12aaff;
}
.form-group input {
    padding: 12px 14px;
    border-radius: 10px;
    border: 1px solid rgba(255, 255, 255, 0.14);
    background: rgba(255, 255, 255, 0.04);
    color: #fff;
    outline: none;
    font-size: 0.95rem;
    transition: all 0.25s ease;
}
.form-group input:focus {
    border-color: #12aaff;
    box-shadow: 0 0 0 2px rgba(18, 170, 255, 0.25);
    background: rgba(255, 255, 255, 0.07);
}

.profile-divider {
    border: none;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    margin: 15px 0;
}

.password-title {
    color: #fff;
    margin-bottom: 4px;
    font-size: 1.05rem;
    display: flex;
    align-items: center;
    gap: 6px;
}
.password-title i {
    color: #ffcc00;
}
.password-help {
    color: #888;
    font-size: 0.85rem;
    margin-bottom: 10px;
}

.btn-guardar {
    margin-top: 10px;
    align-self: flex-start;
    padding: 12px 24px;
    border-radius: 12px;
    border: none;
    cursor: pointer;
    font-weight: 700;
    font-size: 0.95rem;
    background: linear-gradient(135deg, #12aaff, #0de0c9);
    color: #0d0f14;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
    box-shadow: 0 6px 18px rgba(18, 170, 255, 0.4);
}
.btn-guardar:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 26px rgba(18, 170, 255, 0.6);
    color: #fff;
}
</style>

<?php include '../includes/footer.php'; ?>
