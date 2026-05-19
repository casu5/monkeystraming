<?php
// includes/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';

if (!isset($page_title) || trim($page_title) === '') {
    $page_title = "Monkeystraming";
}

// Detectar si estamos dentro de /user para prefijar rutas
$prefix = (strpos($_SERVER['SCRIPT_NAME'], '/user/') !== false) ? '../' : '';

// Usuario actual (para header)
$usuario_actual = null;
if (function_exists('isLoggedIn') && isLoggedIn()) {
    $usuario_actual = getCurrentUser();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* === BASE (igual estilo del index) === */
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
            overflow-x: hidden;
        }

        /* === HEADER (copiado del index) === */
        .header {
            position: sticky;
            top: 0;
            z-index: 1000;
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 80px; /* Reducido para mejor ajuste */
            background: linear-gradient(90deg, rgba(20,22,29,0.95), rgba(16,18,24,0.98));
            backdrop-filter: blur(15px);
            border-bottom: 1px solid rgba(255,255,255,0.07);
            box-shadow: 0 4px 25px rgba(0, 0, 0, 0.3);
            min-height: 130px; /* Altura mínima fija */
            max-height: 70px; /* Altura máxima fija */
            overflow: hidden; /* Previene desbordamientos */
        }

        .logo {
            display: flex;
            align-items: center;
            height: 100%; /* Usa toda la altura del header */
        }

        .logo-img {
            height: 230px; /* Altura fija para el logo */
            width: auto; /* Ancho automático mantiene proporción */
            max-width: 300px; /* Ancho máximo para evitar que sea muy ancho */
            object-fit: contain; /* Mantiene proporción sin deformar */
            transition: transform 0.3s ease;
        }

        .logo-img:hover {
            transform: scale(1.05);
        }

        .nav-container {
            display: flex;
            align-items: center;
            gap: 30px;
        }

        .nav {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .nav a {
            text-decoration: none;
            color: #d0d0d0;
            font-weight: 500;
            transition: all 0.3s ease;
            font-size: 0.95rem;
            position: relative;
            padding: 5px 0;
        }

        .nav a::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background: linear-gradient(90deg, #12aaff, #0de0c9);
            transition: width 0.3s ease;
        }

        .nav a:hover {
            color: #12aaff;
        }

        .nav a:hover::after {
            width: 100%;
        }

        .search-bar {
            padding: 10px 18px;
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.1);
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
            outline: none;
            transition: all 0.3s ease;
            width: 220px;
            font-size: 0.9rem;
            backdrop-filter: blur(5px);
        }

        .search-bar:focus {
            border-color: #12aaff;
            box-shadow: 0 0 0 3px rgba(18, 170, 255, 0.2);
            background: rgba(255, 255, 255, 0.08);
        }

        .btn-registro {
            padding: 10px 22px;
            background: linear-gradient(135deg, #12aaff, #0de0c9);
            border-radius: 10px;
            color: #0d0f14 !important;
            font-weight: 700;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            box-shadow: 0 4px 15px rgba(18, 170, 255, 0.3);
        }

        .btn-registro:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(18, 170, 255, 0.4);
            color: #fff !important;
        }

        .user-name-nav,
        .user-saldo-nav {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.9rem;
        }

        .user-name-nav {
            color: #d0d0d0;
        }

        .user-saldo-nav {
            padding: 6px 12px;
            border-radius: 999px;
            background: rgba(18,170,255,0.12);
            color: #fb9919;
            font-weight: 600;
        }

        /* === RESPONSIVE (igual) === */
        @media (max-width: 1024px) {
            .nav { gap: 15px; }
            .search-bar { width: 180px; }
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 20px;
                padding: 15px 20px;
            }

            .nav-container {
                width: 100%;
                justify-content: space-between;
            }

            .nav {
                gap: 12px;
                flex-wrap: wrap;
                justify-content: center;
            }

            .search-bar {
                order: -1;
                width: 100%;
            }
        }
    </style>
</head>
<body>

<header class="header">
    <div class="logo">
        <!-- REEMPLAZA LA URL CON LA RUTA DE TU IMAGEN DE LOGO -->
        <img src="../assets/img/monkylogo.png" alt="Monkeystraming Logo" class="logo-img">
    </div>

    <div class="nav-container">
        <nav class="nav">
            <input type="text" class="search-bar" placeholder="🔍 Buscar productos..." id="searchInput">
            <a href="<?php echo $prefix; ?>index.php"><i class="fas fa-home"></i> Inicio</a>
            <a href="<?php echo $prefix; ?>productos.php"><i class="fas fa-box-open"></i> Productos</a>
            <a href="<?php echo $prefix; ?>recargar.php"><i class="fas fa-coins"></i> Recargar</a>

            <?php if ($usuario_actual): ?>
                <span class="user-name-nav">
                    <i class="fas fa-user-circle"></i>
                    <?php echo htmlspecialchars($usuario_actual['nombre']); ?>
                </span>
                <span class="user-saldo-nav">
                    <i class="fas fa-wallet"></i>
                    S/ <?php echo number_format((float)$usuario_actual['saldo'], 2); ?>
                </span>
                <a href="<?php echo $prefix; ?>user/dashboard.php"><i class="fas fa-th-large"></i> Mi cuenta</a>
                <a href="<?php echo $prefix; ?>logout.php"><i class="fas fa-sign-out-alt"></i> Salir</a>
            <?php else: ?>
                <a href="<?php echo $prefix; ?>login.php"><i class="fas fa-sign-in-alt"></i> Login</a>
                <a href="<?php echo $prefix; ?>register.php" class="btn-registro"><i class="fas fa-user-plus"></i> Registrarse</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
