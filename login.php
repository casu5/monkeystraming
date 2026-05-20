<?php
require_once 'config/database.php';
require_once __DIR__ . '/includes/auth.php';

$page_title = "Iniciar sesión - Monkeystraming";

// ✅ Si ya está logueado, redirigir según rol
if (isLoggedIn()) {
    $role = normalizeUserRole($_SESSION['user_role'] ?? '');
    if ($role === 'admin') {
        redirect('admin/index.php');
    } elseif ($role === 'vendedor') {
        redirect('vendedor/dashboard.php');
    } else {
        redirect('index.php');
    }
}

$producto_redirect = $_GET['redirect'] ?? '';

$error_msg   = '';
$success_msg = '';

if (isset($_SESSION['success_msg'])) {
    $success_msg = $_SESSION['success_msg'];
    unset($_SESSION['success_msg']);
}
if (isset($_SESSION['error_msg'])) {
    $error_msg = $_SESSION['error_msg'];
    unset($_SESSION['error_msg']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email    = cleanInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error_msg = "Debe ingresar correo y contraseña.";
    } else {

        $sql = "
            SELECT id, nombre, email, password, role, saldo
            FROM usuarios
            WHERE email = ?
            LIMIT 1
        ";

        $stmt = $conexion->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($user = $result->fetch_assoc()) {

                if (password_verify($password, $user['password'])) {
                    $role = normalizeUserRole($user['role'] ?? 'cliente');

                    // ✅ Sesión normal (USER/ADMIN)
                    $_SESSION['user_id']    = $user['id'];
                    $_SESSION['user_name']  = $user['nombre'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role']  = $role;
                    $_SESSION['user_saldo'] = $user['saldo'];

                    // ✅ Compatibilidad: si es admin, también setear sesión admin
                    if ($role === 'admin') {
                        $_SESSION['admin_id']    = $user['id'];
                        $_SESSION['admin_email'] = $user['email'];
                        $_SESSION['admin_name']  = $user['nombre'];
                        $_SESSION['admin_table'] = 'usuarios';

                        redirect('admin/index.php');
                    } elseif ($role === 'vendedor') {
                        redirect('vendedor/dashboard.php');
                    } else {
                        redirect('index.php');
                    }

                } else {
                    $error_msg = "Correo o contraseña incorrectos.";
                }
            } else {
                $error_msg = "Correo o contraseña incorrectos.";
            }

            $stmt->close();
        } else {
            $error_msg = "Error interno.";
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
            overflow: hidden;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 30%, rgba(18, 170, 255, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(13, 224, 201, 0.1) 0%, transparent 50%);
            z-index: -1;
        }

        body::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                radial-gradient(1px 1px at 20% 30%, rgba(18, 170, 255, 0.3) 0%, transparent 100%),
                radial-gradient(1px 1px at 40% 70%, rgba(13, 224, 201, 0.3) 0%, transparent 100%),
                radial-gradient(1px 1px at 60% 20%, rgba(255, 255, 255, 0.2) 0%, transparent 100%),
                radial-gradient(1px 1px at 80% 50%, rgba(18, 170, 255, 0.2) 0%, transparent 100%);
            background-size: 300px 300px;
            z-index: -1;
            animation: particles 20s infinite linear;
        }

        @keyframes particles {
            from { background-position: 0 0; }
            to { background-position: 300px 300px; }
        }

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
        }

        .home-btn:hover {
            background: rgba(255, 255, 255, 0.12);
            border-color: #12aaff;
            color: #12aaff;
            transform: translateX(-5px);
        }

        .auth-container {
            width: 100%;
            max-width: 450px;
            background: rgba(255, 255, 255, 0.04);
            padding: 50px;
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(15px);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.4);
            position: relative;
            overflow: hidden;
            animation: slideUp 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            z-index: 1;
        }

        .auth-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, #12aaff, #0de0c9, transparent);
            animation: borderGlow 3s infinite linear;
        }

        @keyframes borderGlow {
            0% { opacity: 0; }
            50% { opacity: 1; }
            100% { opacity: 0; }
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .auth-logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .auth-logo .logo {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #12aaff, #0de0c9);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
            letter-spacing: 0.5px;
        }

        .auth-logo p {
            color: #aaa;
            font-size: 0.95rem;
        }

        .auth-container h2 {
            font-size: 2.2rem;
            font-weight: 700;
            color: #fff;
            text-align: center;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #fff, #12aaff);
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

        .alert {
            padding: 15px;
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

        .alert i { font-size: 1.2rem; }

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

        .input-group label i { color: #ff6512; }

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
            border-color: #12aaff;
            box-shadow: 0 0 0 3px rgba(18, 170, 255, 0.2);
            background: rgba(255, 255, 255, 0.08);
        }

        .input-group input::placeholder { color: #666; }

        .char-count {
            position: absolute;
            right: 45px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            font-size: 0.85rem;
            pointer-events: none;
        }

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

        .toggle-password:hover { color: #12aaff; }

        .auth-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            font-size: 0.9rem;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #aaa;
            cursor: pointer;
        }

        .checkbox-custom {
            width: 18px;
            height: 18px;
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .remember-me input:checked + .checkbox-custom {
            background: #12aaff;
            border-color: #12aaff;
        }

        .checkbox-custom i {
            font-size: 0.8rem;
            color: #0d0f14;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .remember-me input:checked + .checkbox-custom i { opacity: 1; }
        .remember-me input { display: none; }

        .forgot-password {
            color: #818486;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .forgot-password:hover {
            color: #0de0c9;
            text-decoration: underline;
        }

        .auth-btn {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, #f99f2a, #ffd017);
            border: none;
            border-radius: 14px;
            color: #0d0f14;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(18, 170, 255, 0.3);
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

        .auth-btn:hover::before { left: 100%; }

        .auth-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(18, 170, 255, 0.5);
            color: #fff;
        }

        .auth-btn:active { transform: translateY(-1px); }

        .separator {
            display: flex;
            align-items: center;
            margin: 30px 0;
            color: #666;
        }

        .separator::before,
        .separator::after {
            content: '';
            flex: 1;
            height: 1px;
            background: rgba(255, 255, 255, 0.1);
        }

        .separator span { padding: 0 15px; font-size: 0.9rem; }

        .auth-links {
            text-align: center;
            margin-top: 25px;
            color: #aaa;
            font-size: 0.95rem;
        }

        .auth-links a {
            color: #77797a;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .auth-links a:hover {
            color: #0de0c9;
            text-decoration: underline;
        }

        .product-info {
            background: rgba(18, 170, 255, 0.1);
            border: 1px solid rgba(18, 170, 255, 0.2);
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 25px;
            text-align: center;
        }

        .product-info i { color: #12aaff; margin-right: 8px; }

        .product-info strong { color: #0de0c9; }

        @media (max-width: 576px) {
            .auth-container { padding: 35px 25px; margin-top: 40px; }
            .floating-home { top: 20px; left: 20px; }
            .home-btn { padding: 10px 15px; font-size: 0.9rem; }
            .auth-options { flex-direction: column; gap: 15px; align-items: flex-start; }
        }

        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #12aaff;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

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
    </style>
</head>
<body>

    <div class="floating-home">
        <a href="index.php" class="home-btn">
            <i class="fas fa-arrow-left"></i>
            Volver al inicio
        </a>
    </div>
<div class="logo">
        <!-- REEMPLAZA LA URL CON LA RUTA DE TU IMAGEN DE LOGO -->
        <img src="assets/img/monkylogo.png" alt="Monkeystraming Logo" class="logo-img">
    </div>
    <div class="auth-container">
        <div class="auth-logo">
            
            
        </div>

        <?php if (!empty($producto_redirect)): ?>
        <div class="product-info">
            <i class="fas fa-info-circle"></i>
            Inicia sesión para comprar: 
            <strong><?php echo htmlspecialchars($producto_redirect); ?></strong>
        </div>
        <?php endif; ?>

        <?php if (!empty($error_msg)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error_msg); ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($success_msg)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($success_msg); ?>
        </div>
        <?php endif; ?>

     
        <p class="auth-subtitle">Ingresa a tu cuenta para acceder a contenido exclusivo</p>

        <form action="" method="POST" id="loginForm">
            <div class="input-group">
                <label for="email"><i class="fas fa-envelope"></i> Correo electrónico</label>
                <input type="email" name="email" id="email" required 
                       placeholder="tu@email.com" 
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>

            <div class="input-group">
                <label for="password"><i class="fas fa-lock"></i> Contraseña</label>
                <input type="password" name="password" id="password" required 
                       placeholder="••••••••" 
                       minlength="6" maxlength="50">
                <button type="button" class="toggle-password" id="togglePassword">
                    <i class="fas fa-eye"></i>
                </button>
                <span class="char-count" id="charCount">0/50</span>
            </div>

            <div class="auth-options">
                <label class="remember-me">
                    <input type="checkbox" name="remember" id="remember">
                    <span class="checkbox-custom">
                        <i class="fas fa-check"></i>
                    </span>
                    Recordarme
                </label>
                <a href="recuperar.php" class="forgot-password" id="forgotPassword">
                    ¿Olvidaste tu contraseña?
                </a>
            </div>

            <button type="submit" class="auth-btn" id="submitBtn">
                <span id="btnText">Ingresar a mi cuenta</span>
                <span id="btnLoading" style="display: none;">
                    <span class="loading"></span> Procesando...
                </span>
            </button>

            <div class="separator">
                <span>O continuar con</span>
            </div>

            <div class="auth-links">
                ¿No tienes cuenta? <a href="register.php">Crea una aquí</a><br>
                <a href="index.php" style="margin-top: 10px; display: inline-block;">
                    <i class="fas fa-home"></i> Volver al inicio
                </a>
                <a href="admin/login.php">Panel Admin</a>
            </div>
        </form>
    </div>

    <script>
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput  = document.getElementById('password');
        const charCount      = document.getElementById('charCount');
        const loginForm      = document.getElementById('loginForm');
        const submitBtn      = document.getElementById('submitBtn');
        const btnText        = document.getElementById('btnText');
        const btnLoading     = document.getElementById('btnLoading');
        const emailInput     = document.getElementById('email');

        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
        });

        passwordInput.addEventListener('input', function() {
            const length = this.value.length;
            charCount.textContent = `${length}/50`;
            
            if (length < 6) {
                charCount.style.color = '#ff3b30';
            } else if (length < 10) {
                charCount.style.color = '#ff9500';
            } else {
                charCount.style.color = '#34c759';
            }
        });

        emailInput.addEventListener('blur', function() {
            const email = this.value.trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (email && !emailRegex.test(email)) {
                this.classList.add('input-error');
                showError(this, 'Por favor ingresa un email válido');
            } else {
                this.classList.remove('input-error');
                removeError(this);
            }
        });

        passwordInput.addEventListener('blur', function() {
            if (this.value.length > 0 && this.value.length < 6) {
                this.classList.add('input-error');
                showError(this, 'La contraseña debe tener al menos 6 caracteres');
            } else {
                this.classList.remove('input-error');
                removeError(this);
            }
        });

        function showError(input, message) {
            removeError(input);
            const errorDiv = document.createElement('div');
            errorDiv.className = 'error-text';
            errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${message}`;
            input.parentNode.appendChild(errorDiv);
        }

        function removeError(input) {
            const existingError = input.parentNode.querySelector('.error-text');
            if (existingError) {
                existingError.remove();
            }
        }

        loginForm.addEventListener('submit', function(e) {
            const emailVal = emailInput.value.trim();
            const passVal  = passwordInput.value.trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

            if (!emailVal || !passVal) {
                e.preventDefault();
                alert('Por favor completa todos los campos');
                return;
            }

            if (!emailRegex.test(emailVal)) {
                e.preventDefault();
                emailInput.classList.add('input-error');
                showError(emailInput, 'Por favor ingresa un email válido');
                return;
            }

            btnText.style.display    = 'none';
            btnLoading.style.display = 'inline-block';
            submitBtn.disabled       = true;
        });

        
    

        document.addEventListener('DOMContentLoaded', function() {
            const container = document.querySelector('.auth-container');
            container.style.opacity = '1';
            container.style.transform = 'translateY(0)';
        });

        const urlParams = new URLSearchParams(window.location.search);
        const producto = urlParams.get('redirect');
        if (producto) {
            setTimeout(() => {
                document.getElementById('email').focus();
            }, 500);
        }
    </script>

</body>
</html>
