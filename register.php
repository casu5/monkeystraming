<?php
require_once 'config/database.php';
require_once __DIR__ . '/includes/auth.php';

$page_title = "Registrarse - Monkeystraming";
$error_msg = '';
$success_msg = '';
$form_data = [];
$registration_success = false; // para saber si el registro fue OK en este request

// Mensajes desde procesos (por si algún día los usas)
if (isset($_SESSION['error'])) {
    $error_msg = $_SESSION['error'];
    unset($_SESSION['error']);
}
if (isset($_SESSION['success'])) {
    $success_msg = $_SESSION['success'];
    unset($_SESSION['success']);
}

// Si ya está logueado, redirigir
if (isLoggedIn()) {
    header("Location: user/dashboard.php");
    exit();
}

// Procesar formulario de registro
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre     = trim($_POST['username'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $whatsapp   = trim($_POST['whatsapp'] ?? '');
    $password   = $_POST['password'] ?? '';
    $password2  = $_POST['password2'] ?? '';
    $terms_accepted = isset($_POST['terms']);

    $form_data = [
        'username'  => htmlspecialchars($nombre),
        'email'     => htmlspecialchars($email),
        'whatsapp'  => htmlspecialchars($whatsapp)
    ];

    // Validaciones
    $errors = [];

    if (empty($nombre) || strlen($nombre) < 3) {
        $errors[] = "El nombre debe tener al menos 3 caracteres";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Email inválido";
    }

    // Validar WhatsApp (formato internacional)
    if (empty($whatsapp)) {
        $errors[] = "El número de WhatsApp es obligatorio para soporte";
    } else {
        // Limpiar número (quitar espacios, guiones, etc.)
        $whatsapp_limpio = preg_replace('/[^0-9+]/', '', $whatsapp);
        
        // Validar formato internacional
        if (!preg_match('/^\+?[0-9]{10,15}$/', $whatsapp_limpio)) {
            $errors[] = "Formato de WhatsApp inválido. Ejemplo: +51987654321 o 987654321";
        } else {
            // Formatear a internacional si no tiene +
            if (substr($whatsapp_limpio, 0, 1) !== '+') {
                // Asumir que es de Perú si no tiene código de país
                if (strlen($whatsapp_limpio) === 9) {
                    $whatsapp_limpio = '+51' . $whatsapp_limpio;
                }
            }
            $whatsapp = $whatsapp_limpio;
        }
    }

    if (strlen($password) < 6) {
        $errors[] = "La contraseña debe tener al menos 6 caracteres";
    }

    if ($password !== $password2) {
        $errors[] = "Las contraseñas no coinciden";
    }

    if (!$terms_accepted) {
        $errors[] = "Debes aceptar los términos y condiciones";
    }

    // Verificar si el email ya existe (solo si no hay errores previos)
    if (empty($errors)) {
        $check_sql  = "SELECT id FROM usuarios WHERE email = ?";
        $check_stmt = $conexion->prepare($check_sql);
        if ($check_stmt) {
            $check_stmt->bind_param("s", $email);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            if ($result && $result->num_rows > 0) {
                $errors[] = "Este email ya está registrado";
            }
            $check_stmt->close();
        } else {
            $errors[] = "Error interno al verificar el email.";
        }
    }

    // Verificar si el WhatsApp ya está registrado
    if (empty($errors)) {
        $check_whatsapp_sql = "SELECT id FROM usuarios WHERE whatsapp = ?";
        $check_whatsapp_stmt = $conexion->prepare($check_whatsapp_sql);
        if ($check_whatsapp_stmt) {
            $check_whatsapp_stmt->bind_param("s", $whatsapp);
            $check_whatsapp_stmt->execute();
            $result_whatsapp = $check_whatsapp_stmt->get_result();
            if ($result_whatsapp && $result_whatsapp->num_rows > 0) {
                $errors[] = "Este número de WhatsApp ya está registrado";
            }
            $check_whatsapp_stmt->close();
        }
    }

    // Si hay errores
    if (!empty($errors)) {
        $error_msg = implode("<br>", $errors);
    } else {
        // Hash de la contraseña
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Insertar usuario en BD CON WHATSAPP
        $sql  = "INSERT INTO usuarios (nombre, email, whatsapp, password, role, saldo) 
                 VALUES (?, ?, ?, ?, 'user', 0.00)";
        $stmt = $conexion->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("ssss", $nombre, $email, $whatsapp, $hashed_password);

            if ($stmt->execute()) {
                $success_msg          = "¡Cuenta creada exitosamente! Ahora puedes iniciar sesión.";
                $registration_success = true;
                $form_data            = []; // limpiar campos del formulario
                
                // Guardar en sesión para mostrar en login
                $_SESSION['registered_email'] = $email;
                $_SESSION['registered_name'] = $nombre;
            } else {
                $error_msg = "Error al crear la cuenta. Por favor, intenta de nuevo.";
            }

            $stmt->close();
        } else {
            $error_msg = "Error al preparar el registro. Inténtalo más tarde.";
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
        /* === MONKYDOS - ESTILO OSCURO MODERNO === */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #0d0f14 0%, #11131a 35%, #0b0c11 100%);
            color: #e5e5e5;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }

        /* Fondo con efectos */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 10% 20%, rgba(37, 211, 102, 0.15) 0%, transparent 40%), /* Verde WhatsApp */
                radial-gradient(circle at 90% 80%, rgba(13, 224, 201, 0.15) 0%, transparent 40%),
                radial-gradient(circle at 50% 50%, rgba(255, 255, 255, 0.05) 0%, transparent 100%);
            z-index: -1;
            animation: gradientShift 15s ease infinite alternate;
        }

        @keyframes gradientShift {
            0% { filter: hue-rotate(0deg); }
            100% { filter: hue-rotate(20deg); }
        }

        /* Partículas animadas */
        .particles {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            pointer-events: none;
            z-index: -1;
        }

        .particle {
            position: absolute;
            background: linear-gradient(135deg, #25D366, #0de0c9); /* Verde WhatsApp + cyan */
            border-radius: 50%;
            opacity: 0.3;
            animation: float 20s infinite linear;
        }

        @keyframes float {
            0% { transform: translateY(100vh) translateX(0); }
            100% { transform: translateY(-100px) translateX(100px); }
        }

        /* Botón flotante de volver */
        .floating-home {
            position: absolute;
            top: 30px;
            left: 30px;
            z-index: 100;
        }

        .home-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 24px;
            background: rgba(255, 255, 255, 0.07);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            color: #d0d0d0;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .home-btn:hover {
            background: rgba(255, 255, 255, 0.12);
            border-color: #25D366; /* Verde WhatsApp */
            color: #25D366;
            transform: translateX(-5px);
            box-shadow: 0 8px 20px rgba(37, 211, 102, 0.3);
        }

        /* Contenedor de autenticación */
        .auth-container {
            width: 100%;
            max-width: 500px;
            background: rgba(255, 255, 255, 0.04);
            padding: 50px;
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(20px);
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.5);
            position: relative;
            overflow: hidden;
            animation: slideUp 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            z-index: 1;
        }

        /* Efecto de borde brillante con verde WhatsApp */
        .auth-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: conic-gradient(from 0deg at 50% 50%, 
                transparent 0%, 
                #25D366 10%, /* Verde WhatsApp */
                #0de0c9 20%, 
                transparent 30%);
            animation: rotateBorder 4s linear infinite;
            z-index: -1;
            opacity: 0.5;
        }

        @keyframes rotateBorder {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        /* Logo */
        .auth-logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .auth-logo .logo {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #25D366, #0de0c9); /* WhatsApp + cyan */
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
            letter-spacing: 0.5px;
            text-shadow: 0 5px 15px rgba(37, 211, 102, 0.2);
        }

        .auth-logo p {
            color: #aaa;
            font-size: 0.95rem;
        }

        /* Títulos */
        .auth-container h2 {
            font-size: 2.2rem;
            font-weight: 700;
            color: #fff;
            text-align: center;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #2b2a2a, #c0c9c0, #c7cdc7); /* Incluye verde WhatsApp */
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .auth-subtitle {
            text-align: center;
            color: #aaa;
            margin-bottom: 40px;
            font-size: 1rem;
            line-height: 1.5;
        }

        /* Indicador de progreso - Aumentado a 4 pasos */
        .progress-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
            position: relative;
        }

        .progress-indicator::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 12.5px; /* Ajustado para 4 pasos */
            right: 12.5px;
            height: 2px;
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-50%);
            z-index: 1;
        }

        .progress-step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #666;
            font-weight: 600;
            position: relative;
            z-index: 2;
            transition: all 0.3s ease;
        }

        .progress-step.active {
            background: linear-gradient(135deg, #25D366, #0de0c9); /* WhatsApp + cyan */
            border-color: transparent;
            color: #0d0f14;
            transform: scale(1.1);
            box-shadow: 0 0 20px rgba(37, 211, 102, 0.4);
        }

        .progress-step.completed {
            background: #34c759;
            border-color: transparent;
            color: #0d0f14;
        }

        /* Mensajes de estado */
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .alert-error {
            background: rgba(255, 59, 48, 0.15);
            border: 1px solid rgba(255, 59, 48, 0.3);
            color: #ff3b30;
        }

        .alert-success {
            background: rgba(52, 199, 89, 0.15);
            border: 1px solid rgba(52, 199, 89, 0.3);
            color: #34c759;
        }

        .alert i {
            font-size: 1.2rem;
        }

        /* Grupo de inputs */
        .input-group {
            margin-bottom: 25px;
            position: relative;
        }

        .input-group label {
            display: block;
            margin-bottom: 10px;
            color: #d0d0d0;
            font-weight: 500;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .input-group label i {
            color: #25D366; /* Verde WhatsApp */
        }

        .input-group label i.fa-user { color: #ff6d00; }
        .input-group label i.fa-envelope { color: #ff6d00; }
        .input-group label i.fa-lock { color: #0de0c9; }

        .input-group input {
            width: 100%;
            padding: 16px 20px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            color: #fff;
            font-size: 1rem;
            outline: none;
            transition: all 0.3s ease;
            backdrop-filter: blur(5px);
        }

        .input-group input:focus {
            border-color: #25D366; /* Verde WhatsApp */
            box-shadow: 0 0 0 3px rgba(37, 211, 102, 0.2);
            background: rgba(255, 255, 255, 0.08);
            transform: translateY(-2px);
        }

        .input-group input::placeholder {
            color: #666;
        }

        /* Información sobre WhatsApp */
        .whatsapp-info {
            background: rgba(37, 211, 102, 0.05);
            border: 1px solid rgba(37, 211, 102, 0.1);
            border-radius: 10px;
            padding: 12px 15px;
            margin-top: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .whatsapp-info i {
            color: #25D366;
            font-size: 1.2rem;
        }

        .whatsapp-info p {
            color: #aaa;
            font-size: 0.85rem;
            margin: 0;
            line-height: 1.4;
        }

        /* Indicadores de fuerza de contraseña */
        .password-strength {
            height: 4px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 2px;
            margin-top: 10px;
            overflow: hidden;
            position: relative;
        }

        .strength-meter {
            height: 100%;
            width: 0%;
            border-radius: 2px;
            transition: all 0.3s ease;
        }

        .strength-text {
            position: absolute;
            right: 0;
            top: -20px;
            font-size: 0.8rem;
            color: #666;
            transition: color 0.3s ease;
        }

        /* Requisitos de contraseña */
        .password-requirements {
            margin-top: 10px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .requirement {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            font-size: 0.85rem;
            color: #888;
            transition: color 0.3s ease;
        }

        .requirement i {
            font-size: 0.8rem;
        }

        .requirement.valid {
            color: #34c759;
        }

        .requirement.invalid {
            color: #ff3b30;
        }

        /* Mostrar/ocultar password */
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            font-size: 1rem;
            padding: 5px;
            transition: color 0.3s ease;
        }

        .toggle-password:hover {
            color: #25D366; /* Verde WhatsApp */
        }

        /* Términos y condiciones */
        .terms-group {
            margin: 25px 0;
            padding: 20px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .terms-checkbox {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            cursor: pointer;
        }

        .terms-checkbox input {
            display: none;
        }

        .checkbox-custom {
            min-width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            margin-top: 2px;
        }

        .terms-checkbox input:checked + .checkbox-custom {
            background: #25D366; /* Verde WhatsApp */
            border-color: #25D366;
        }

        .checkbox-custom i {
            font-size: 0.9rem;
            color: #0d0f14;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .terms-checkbox input:checked + .checkbox-custom i {
            opacity: 1;
        }

        .terms-text {
            color: #aaa;
            font-size: 0.9rem;
            line-height: 1.5;
        }

        .terms-text a {
            color: #25D366; /* Verde WhatsApp */
            text-decoration: none;
            font-weight: 500;
        }

        .terms-text a:hover {
            text-decoration: underline;
        }

        /* Botón de registro */
        .auth-btn {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, #f99f2a, #ffd017); /* WhatsApp + cyan */
            border: none;
            border-radius: 14px;
            color: #0d0f14;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            margin-top: 20px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(37, 211, 102, 0.3);
        }

        .auth-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.7s ease;
        }

        .auth-btn:hover::before {
            left: 100%;
        }

        .auth-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(37, 211, 102, 0.5);
            color: #fff;
        }

        .auth-btn:active {
            transform: translateY(-1px);
        }

        .auth-btn.secondary {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #fff;
            box-shadow: none;
        }

        .auth-btn.secondary:hover {
            background: rgba(255, 255, 255, 0.15);
            border-color: #25D366;
        }

        /* Links */
        .auth-links {
            text-align: center;
            margin-top: 30px;
            color: #aaa;
            font-size: 0.95rem;
        }

        .auth-links a {
            color: #9ca39e; /* Verde WhatsApp */
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .auth-links a:hover {
            color: #0de0c9;
            text-decoration: underline;
        }

        /* Efecto de carga */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #25D366; /* Verde WhatsApp */
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Input con error */
        .input-error {
            border-color: #ff3b30 !important;
            box-shadow: 0 0 0 3px rgba(255, 59, 48, 0.2) !important;
        }

        .error-text {
            color: #ff3b30;
            font-size: 0.85rem;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* Responsive */
        @media (max-width: 576px) {
            .auth-container {
                padding: 35px 25px;
                margin-top: 40px;
            }
            
            .floating-home {
                top: 20px;
                left: 20px;
            }
            
            .home-btn {
                padding: 10px 15px;
                font-size: 0.9rem;
            }
            
            .progress-indicator {
                margin-bottom: 30px;
            }
            
            .progress-step {
                width: 35px;
                height: 35px;
                font-size: 0.9rem;
            }
        }

        /* Animación de confeti para éxito */
        @keyframes confettiFall {
            0% { transform: translateY(-100px) rotate(0deg); opacity: 1; }
            100% { transform: translateY(100vh) rotate(360deg); opacity: 0; }
        }

        /* WhatsApp bounce animation */
        @keyframes whatsappBounce {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .whatsapp-icon-animated {
            animation: whatsappBounce 2s infinite;
        }
    </style>
</head>
<body>

    <!-- Partículas de fondo -->
    <div class="particles" id="particles"></div>

    <!-- Botón flotante para volver al inicio -->
    <div class="floating-home">
        <a href="index.php" class="home-btn">
            <i class="fas fa-arrow-left"></i>
            Volver al inicio
        </a>
    </div>

<img src="assets/img/monkylogo.png" alt="Monkeystraming Logo" class="logo-img">
    <div class="auth-container" id="authContainer" data-success="<?php echo $registration_success ? '1' : '0'; ?>">
        <!-- Logo -->
        <div class="auth-logo">
         
          
        </div>

        <!-- Indicador de progreso (4 pasos ahora) -->
        <div class="progress-indicator">
            <div class="progress-step active">1</div>
            <div class="progress-step">2</div>
            <div class="progress-step">3</div>
            <div class="progress-step">4</div>
        </div>

        <!-- Mensajes de error/success -->
        <?php if ($error_msg): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $error_msg; ?>
        </div>
        <?php endif; ?>

        <?php if ($success_msg): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo $success_msg; ?>
        </div>
        <?php endif; ?>

        <h2>Crea tu cuenta</h2>
        <p class="auth-subtitle">Únete a miles de usuarios que disfrutan de contenido premium</p>

        <form action="" method="POST" id="registerForm">
            <!-- Paso 1: Información básica -->
            <div class="form-step" id="step1">
                <div class="input-group">
                    <label for="username"><i class="fas fa-user"></i> Nombre de usuario</label>
                    <input type="text" name="username" id="username" required 
                           placeholder="ej: streamer_pro" 
                           value="<?php echo $form_data['username'] ?? ''; ?>"
                           minlength="3" maxlength="20">
                    <div class="error-text" id="usernameError"></div>
                </div>

                <div class="input-group">
                    <label for="email"><i class="fas fa-envelope"></i> Correo electrónico</label>
                    <input type="email" name="email" id="email" required 
                           placeholder="tu@email.com" 
                           value="<?php echo $form_data['email'] ?? ''; ?>">
                    <div class="error-text" id="emailError"></div>
                </div>

                <button type="button" class="auth-btn next-step" data-next="step2">
                    Siguiente <i class="fas fa-arrow-right"></i>
                </button>
            </div>

            <!-- Paso 2: WhatsApp (NUEVO) -->
            <div class="form-step" id="step2" style="display: none;">
                <div class="input-group">
                    <label for="whatsapp">
                        <i class="fab fa-whatsapp whatsapp-icon-animated" style="color: #25D366;"></i> 
                        Número de WhatsApp
                    </label>
                    <input type="tel" name="whatsapp" id="whatsapp" required 
                           placeholder="Ej: +51987654321 o 987654321" 
                           value="<?php echo $form_data['whatsapp'] ?? ''; ?>">
                    <div class="error-text" id="whatsappError"></div>
                    
                    <!-- Información sobre WhatsApp -->
                    <div class="whatsapp-info">
                        <i class="fab fa-whatsapp"></i>
                        <p>Importante: Usaremos este número para contacto y soporte. Asegúrate de que sea correcto.</p>
                    </div>
                </div>

                <div style="display: flex; gap: 15px; margin-top: 20px;">
                    <button type="button" class="auth-btn secondary prev-step">
                        <i class="fas fa-arrow-left"></i> Anterior
                    </button>
                    <button type="button" class="auth-btn next-step" data-next="step3">
                        Siguiente <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>

            <!-- Paso 3: Contraseña -->
            <div class="form-step" id="step3" style="display: none;">
                <div class="input-group">
                    <label for="password"><i class="fas fa-lock"></i> Contraseña</label>
                    <input type="password" name="password" id="password" required 
                           placeholder="••••••••" 
                           minlength="6" maxlength="50">
                    <button type="button" class="toggle-password" id="togglePassword1">
                        <i class="fas fa-eye"></i>
                    </button>
                    
                    <!-- Indicador de fuerza -->
                    <div class="password-strength">
                        <div class="strength-meter" id="strengthMeter"></div>
                        <div class="strength-text" id="strengthText">Débil</div>
                    </div>
                    
                    <!-- Requisitos -->
                    <div class="password-requirements">
                        <div class="requirement" id="reqLength">
                            <i class="fas fa-circle"></i> Mínimo 6 caracteres
                        </div>
                        <div class="requirement" id="reqUppercase">
                            <i class="fas fa-circle"></i> Al menos una mayúscula
                        </div>
                        <div class="requirement" id="reqNumber">
                            <i class="fas fa-circle"></i> Al menos un número
                        </div>
                        <div class="requirement" id="reqSpecial">
                            <i class="fas fa-circle"></i> Al menos un carácter especial (opcional)
                        </div>
                    </div>
                </div>

                <div class="input-group">
                    <label for="password2"><i class="fas fa-lock"></i> Confirmar contraseña</label>
                    <input type="password" name="password2" id="password2" required 
                           placeholder="••••••••">
                    <button type="button" class="toggle-password" id="togglePassword2">
                        <i class="fas fa-eye"></i>
                    </button>
                    <div class="error-text" id="passwordError"></div>
                </div>

                <div style="display: flex; gap: 15px;">
                    <button type="button" class="auth-btn secondary prev-step">
                        <i class="fas fa-arrow-left"></i> Anterior
                    </button>
                    <button type="button" class="auth-btn next-step" data-next="step4">
                        Siguiente <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>

            <!-- Paso 4: Términos y envío -->
            <div class="form-step" id="step4" style="display: none;">
                <div class="terms-group">
                    <label class="terms-checkbox">
                        <input type="checkbox" name="terms" id="terms" required>
                        <span class="checkbox-custom">
                            <i class="fas fa-check"></i>
                        </span>
                        <span class="terms-text">
                            Acepto los <a href="#" id="termsLink">Términos y Condiciones</a> y la 
                            <a href="#" id="privacyLink">Política de Privacidad</a>
                        </span>
                    </label>
                    <div class="error-text" id="termsError"></div>
                </div>

                <!-- Resumen del registro -->
                <div style="background: rgba(255,255,255,0.03); padding: 20px; border-radius: 12px; margin-bottom: 20px;">
                    <h4 style="color: #25D366; margin-bottom: 15px;">
                        <i class="fas fa-clipboard-check"></i> Resumen de tu registro
                    </h4>
                    <div id="registrationSummary">
                        <!-- Se llenará con JavaScript -->
                    </div>
                </div>

                <div style="display: flex; gap: 15px;">
                    <button type="button" class="auth-btn secondary prev-step">
                        <i class="fas fa-arrow-left"></i> Anterior
                    </button>
                    <button type="submit" class="auth-btn" id="submitBtn">
                        <span id="btnText">
                            <i class="fab fa-whatsapp"></i> Crear mi cuenta
                        </span>
                        <span id="btnLoading" style="display: none;">
                            <span class="loading"></span> Creando cuenta...
                        </span>
                    </button>
                </div>
            </div>
        </form>

        <div class="auth-links">
            ¿Ya tienes cuenta? <a href="login.php">Inicia sesión aquí</a><br>
            <a href="index.php" style="margin-top: 10px; display: inline-block;">
                <i class="fas fa-home"></i> Volver al inicio
            </a>
        </div>
    </div>

    <script>
        // Generar partículas de fondo
        function createParticles() {
            const particlesContainer = document.getElementById('particles');
            const particleCount = 20;
            
            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                
                // Tamaño aleatorio
                const size = Math.random() * 10 + 2;
                particle.style.width = `${size}px`;
                particle.style.height = `${size}px`;
                
                // Posición aleatoria
                particle.style.left = `${Math.random() * 100}%`;
                
                // Animación con delay aleatorio
                const duration = Math.random() * 20 + 10;
                const delay = Math.random() * 5;
                particle.style.animation = `float ${duration}s linear ${delay}s infinite`;
                
                // Opacidad aleatoria
                particle.style.opacity = Math.random() * 0.5 + 0.1;
                
                particlesContainer.appendChild(particle);
            }
        }

        // Variables
        let currentStep = 1;
        const form           = document.getElementById('registerForm');
        const steps          = document.querySelectorAll('.form-step');
        const progressSteps  = document.querySelectorAll('.progress-step');
        const usernameInput  = document.getElementById('username');
        const emailInput     = document.getElementById('email');
        const whatsappInput  = document.getElementById('whatsapp');
        const passwordInput  = document.getElementById('password');
        const password2Input = document.getElementById('password2');
        const termsCheckbox  = document.getElementById('terms');
        const submitBtn      = document.getElementById('submitBtn');
        const btnText        = document.getElementById('btnText');
        const btnLoading     = document.getElementById('btnLoading');
        const summaryDiv     = document.getElementById('registrationSummary');

        // Inicializar partículas
        createParticles();

        // Navegación por pasos
        document.querySelectorAll('.next-step').forEach(btn => {
            btn.addEventListener('click', function() {
                const nextStep = this.getAttribute('data-next');
                if (validateStep(currentStep)) {
                    goToStep(nextStep);
                }
            });
        });

        document.querySelectorAll('.prev-step').forEach(btn => {
            btn.addEventListener('click', function() {
                const currentStepId = `step${currentStep}`;
                const prevStepNumber = currentStep - 1;
                if (prevStepNumber >= 1) {
                    goToStep(`step${prevStepNumber}`);
                }
            });
        });

        function goToStep(stepId) {
            // Ocultar todos los pasos
            steps.forEach(step => step.style.display = 'none');
            
            // Mostrar paso actual
            document.getElementById(stepId).style.display = 'block';
            
            // Actualizar indicador de progreso
            const stepNumber = parseInt(stepId.replace('step', ''));
            currentStep = stepNumber;
            
            progressSteps.forEach((step, index) => {
                step.classList.remove('active', 'completed');
                
                if (index + 1 < stepNumber) {
                    step.classList.add('completed');
                } else if (index + 1 === stepNumber) {
                    step.classList.add('active');
                }
            });
            
            // Actualizar resumen en paso 4
            if (stepNumber === 4) {
                updateRegistrationSummary();
            }
        }

        // Función para actualizar el resumen del registro
        function updateRegistrationSummary() {
            const username = usernameInput.value.trim();
            const email = emailInput.value.trim();
            const whatsapp = whatsappInput.value.trim();
            
            let summaryHTML = `
                <div style="display: grid; grid-template-columns: 1fr; gap: 10px;">
                    <div>
                        <strong style="color: #aaa;">Usuario:</strong>
                        <div style="color: #fff; margin-top: 5px;">${username || 'No ingresado'}</div>
                    </div>
                    <div>
                        <strong style="color: #aaa;">Email:</strong>
                        <div style="color: #fff; margin-top: 5px;">${email || 'No ingresado'}</div>
                    </div>
                    <div>
                        <strong style="color: #aaa;">WhatsApp:</strong>
                        <div style="color: #fff; margin-top: 5px;">
                            <i class="fab fa-whatsapp" style="color: #25D366;"></i> 
                            ${whatsapp || 'No ingresado'}
                        </div>
                    </div>
                    <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid rgba(255,255,255,0.1);">
                        <p style="color: #888; font-size: 0.9rem;">
                            <i class="fas fa-info-circle" style="color: #25D366;"></i>
                            Te contactaremos por WhatsApp para soporte y notificaciones.
                        </p>
                    </div>
                </div>
            `;
            
            summaryDiv.innerHTML = summaryHTML;
        }

        // Validación por pasos
        function validateStep(step) {
            let isValid = true;
            
            if (step === 1) {
                // Validar username
                const username     = usernameInput.value.trim();
                const usernameError = document.getElementById('usernameError');
                
                if (username.length < 3) {
                    usernameError.textContent = 'El usuario debe tener al menos 3 caracteres';
                    usernameInput.classList.add('input-error');
                    isValid = false;
                } else if (!/^[a-zA-Z0-9_]+$/.test(username)) {
                    usernameError.textContent = 'Solo letras, números y guiones bajos';
                    usernameInput.classList.add('input-error');
                    isValid = false;
                } else {
                    usernameError.textContent = '';
                    usernameInput.classList.remove('input-error');
                }
                
                // Validar email
                const email      = emailInput.value.trim();
                const emailError = document.getElementById('emailError');
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                
                if (!emailRegex.test(email)) {
                    emailError.textContent = 'Por favor ingresa un email válido';
                    emailInput.classList.add('input-error');
                    isValid = false;
                } else {
                    emailError.textContent = '';
                    emailInput.classList.remove('input-error');
                }
            } else if (step === 2) {
                // Validar WhatsApp
                const whatsapp = whatsappInput.value.trim();
                const whatsappError = document.getElementById('whatsappError');
                const whatsappLimpio = whatsapp.replace(/[^0-9+]/g, '');
                
                if (!whatsapp) {
                    whatsappError.textContent = 'El número de WhatsApp es obligatorio';
                    whatsappInput.classList.add('input-error');
                    isValid = false;
                } else if (!/^\+?[0-9]{10,15}$/.test(whatsappLimpio)) {
                    whatsappError.textContent = 'Formato inválido. Ej: +51987654321 o 987654321';
                    whatsappInput.classList.add('input-error');
                    isValid = false;
                } else {
                    whatsappError.textContent = '';
                    whatsappInput.classList.remove('input-error');
                    
                    // Formatear automáticamente
                    if (whatsappLimpio.length === 9 && whatsappLimpio[0] !== '+') {
                        whatsappInput.value = '+51' + whatsappLimpio;
                    }
                }
            }
            
            return isValid;
        }

        // Validación en tiempo real para username
        usernameInput.addEventListener('input', function() {
            const username = this.value.trim();
            const errorDiv = document.getElementById('usernameError');
            
            if (username.length > 0 && username.length < 3) {
                errorDiv.textContent = 'Mínimo 3 caracteres';
                this.classList.add('input-error');
            } else if (username.length >= 3 && !/^[a-zA-Z0-9_]+$/.test(username)) {
                errorDiv.textContent = 'Solo letras, números y _';
                this.classList.add('input-error');
            } else {
                errorDiv.textContent = '';
                this.classList.remove('input-error');
            }
        });

        // Validación en tiempo real para email
        emailInput.addEventListener('blur', function() {
            const email      = this.value.trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            const errorDiv   = document.getElementById('emailError');
            
            if (email && !emailRegex.test(email)) {
                errorDiv.textContent = 'Email inválido';
                this.classList.add('input-error');
            } else {
                errorDiv.textContent = '';
                this.classList.remove('input-error');
            }
        });

        // Validación en tiempo real para WhatsApp
        whatsappInput.addEventListener('input', function() {
            const whatsapp = this.value.trim();
            const errorDiv = document.getElementById('whatsappError');
            const whatsappLimpio = whatsapp.replace(/[^0-9+]/g, '');
            
            if (whatsapp && !/^\+?[0-9]{0,15}$/.test(whatsappLimpio)) {
                errorDiv.textContent = 'Solo números y + al inicio';
                this.classList.add('input-error');
            } else {
                errorDiv.textContent = '';
                this.classList.remove('input-error');
                
                // Formatear automáticamente mientras escribe
                if (whatsappLimpio.length === 9 && whatsappLimpio[0] !== '+') {
                    this.value = '+51' + whatsappLimpio;
                }
            }
        });

        // Mostrar/ocultar contraseñas
        document.getElementById('togglePassword1').addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
        });

        document.getElementById('togglePassword2').addEventListener('click', function() {
            const type = password2Input.getAttribute('type') === 'password' ? 'text' : 'password';
            password2Input.setAttribute('type', type);
            this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
        });

        // Verificador de fuerza de contraseña
        passwordInput.addEventListener('input', function() {
            const password      = this.value;
            const strengthMeter = document.getElementById('strengthMeter');
            const strengthText  = document.getElementById('strengthText');
            
            // Calcular puntuación
            let score = 0;
            const requirements = {
                length:    password.length >= 6,
                uppercase: /[A-Z]/.test(password),
                number:    /[0-9]/.test(password),
                special:   /[^A-Za-z0-9]/.test(password)
            };
            
            // Actualizar indicadores visuales
            document.getElementById('reqLength').className    = requirements.length    ? 'requirement valid' : 'requirement invalid';
            document.getElementById('reqUppercase').className = requirements.uppercase ? 'requirement valid' : 'requirement invalid';
            document.getElementById('reqNumber').className    = requirements.number    ? 'requirement valid' : 'requirement invalid';
            document.getElementById('reqSpecial').className   = requirements.special   ? 'requirement valid' : 'requirement';
            
            // Calcular puntuación
            if (requirements.length)    score += 25;
            if (requirements.uppercase) score += 25;
            if (requirements.number)    score += 25;
            if (requirements.special)   score += 25;
            
            // Actualizar barra y texto
            strengthMeter.style.width = `${score}%`;
            
            if (score < 25) {
                strengthMeter.style.background = '#ff3b30';
                strengthText.textContent       = 'Muy débil';
                strengthText.style.color       = '#ff3b30';
            } else if (score < 50) {
                strengthMeter.style.background = '#ff9500';
                strengthText.textContent       = 'Débil';
                strengthText.style.color       = '#ff9500';
            } else if (score < 75) {
                strengthMeter.style.background = '#ffcc00';
                strengthText.textContent       = 'Media';
                strengthText.style.color       = '#ffcc00';
            } else if (score < 100) {
                strengthMeter.style.background = '#34c759';
                strengthText.textContent       = 'Fuerte';
                strengthText.style.color       = '#34c759';
            } else {
                strengthMeter.style.background = '#32d74b';
                strengthText.textContent       = 'Muy fuerte';
                strengthText.style.color       = '#32d74b';
            }
            
            // Validar confirmación de contraseña
            validatePasswordMatch();
        });

        // Validar que las contraseñas coincidan
        password2Input.addEventListener('input', validatePasswordMatch);
        
        function validatePasswordMatch() {
            const password  = passwordInput.value;
            const password2 = password2Input.value;
            const errorDiv  = document.getElementById('passwordError');
            
            if (password2 && password !== password2) {
                errorDiv.textContent = 'Las contraseñas no coinciden';
                password2Input.classList.add('input-error');
                return false;
            } else {
                errorDiv.textContent = '';
                password2Input.classList.remove('input-error');
                return true;
            }
        }

        // Enlace a términos y privacidad
        document.getElementById('termsLink').addEventListener('click', function(e) {
            e.preventDefault();
            alert('TÉRMINOS Y CONDICIONES - Monkeystraming\n\n' +
                  '1. El número de WhatsApp es obligatorio para soporte técnico.\n' +
                  '2. Nos comunicaremos contigo por WhatsApp para:\n'   +
                  '   - Envío de credenciales de productos\n' +
                  '   - Soporte técnico 24/7\n' +
                  '   - Notificaciones importantes\n' +
                  '3. Tu número será usado exclusivamente para fines del servicio.\n' +
                  '4. No compartimos tu información con terceros.\n\n' +
                  'Al registrarte, aceptas estos términos.');
        });

        document.getElementById('privacyLink').addEventListener('click', function(e) {
            e.preventDefault();
            alert('POLÍTICA DE PRIVACIDAD - WhatsApp\n\n' +
                  '1. Tu número de WhatsApp se almacena de forma segura.\n' +
                  '2. Solo el equipo de soporte tendrá acceso a tu número.\n' +
                  '3. Usamos tu WhatsApp para:\n' +
                  '   - Respuesta a tickets de soporte\n' +
                  '   - Envío de productos comprados\n' +
                  '   - Notificaciones de seguridad\n' +
                  '4. Puedes solicitar la eliminación de tu número en cualquier momento.\n' +
                  '5. Cumplimos con las leyes de protección de datos.');
        });

        // Envío del formulario
        form.addEventListener('submit', function(e) {
            // Validar todos los pasos
            const step1Valid = validateStep(1);
            const step2Valid = validateStep(2);
            const passwordsValid = validatePasswordMatch();
            const termsAccepted = termsCheckbox.checked;
            
            if (!step1Valid || !step2Valid || !passwordsValid || !termsAccepted) {
                e.preventDefault();
                
                const termsError = document.getElementById('termsError');
                if (!termsCheckbox.checked) {
                    termsError.textContent = 'Debes aceptar los términos y condiciones';
                } else {
                    termsError.textContent = '';
                }
                
                // Ir al primer paso con error
                if (!step1Valid) {
                    goToStep('step1');
                } else if (!step2Valid) {
                    goToStep('step2');
                } else if (!passwordsValid) {
                    goToStep('step3');
                } else {
                    goToStep('step4');
                }
                
                alert('Por favor completa todos los campos correctamente');
                return;
            }
            
            // Mostrar animación de carga mientras PHP procesa
            btnText.style.display   = 'none';
            btnLoading.style.display = 'inline-block';
            submitBtn.disabled      = true;
            // NO hacemos preventDefault: dejamos que el formulario se envíe al PHP
        });

        // Animación de confeti para éxito
        function createConfetti() {
            const colors        = ['#25D366', '#0de0c9', '#12aaff', '#9d4edd', '#ff0054']; // WhatsApp verde primero
            const confettiCount = 50;
            
            for (let i = 0; i < confettiCount; i++) {
                const confetti = document.createElement('div');
                confetti.style.position   = 'fixed';
                confetti.style.width      = '10px';
                confetti.style.height     = '10px';
                confetti.style.background = colors[Math.floor(Math.random() * colors.length)];
                confetti.style.borderRadius = Math.random() > 0.5 ? '50%' : '0';
                confetti.style.left       = `${Math.random() * 100}vw`;
                confetti.style.top        = '-20px';
                confetti.style.opacity    = '0.8';
                confetti.style.zIndex     = '9999';
                confetti.style.animation  = `confettiFall ${Math.random() * 3 + 2}s linear forwards`;
                
                document.body.appendChild(confetti);
                
                // Remover después de la animación
                setTimeout(() => confetti.remove(), 5000);
            }
        }

        // Efecto de entrada + comportamiento post-registro
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.querySelector('.auth-container');
            if (container) {
                container.style.opacity   = '1';
                container.style.transform = 'translateY(0) scale(1)';
            }

            // Si el servidor confirmó que el registro fue exitoso
            const authContainer = document.getElementById('authContainer');
            if (authContainer && authContainer.dataset.success === '1') {
                // Confeti y redirección real al login
                createConfetti();
                setTimeout(() => {
                    window.location.href = 'login.php?registered=1';
                }, 2500);
            }
        });
    </script>

</body>
</html>