<?php
require_once 'config/database.php';
require_once __DIR__ . '/includes/auth.php';

$page_title = "Monkeystraming - Streaming Premium";

// Obtener estadísticas reales de la BD
$stats_sql = "SELECT 
    (SELECT COUNT(*) FROM usuarios) AS total_usuarios,
    (SELECT COUNT(*) FROM usuarios WHERE DATE(created_at) = CURDATE()) AS nuevos_hoy,
    (SELECT COUNT(*) FROM compras WHERE estado = 'completada' AND DATE(fecha_compra) = CURDATE()) AS ventas_hoy,
    (SELECT IFNULL(SUM(monto), 0) FROM compras WHERE estado = 'completada' AND DATE(fecha_compra) = CURDATE()) AS ingresos_hoy";

$stats_result   = $conexion->query($stats_sql);
$estadisticas   = $stats_result ? $stats_result->fetch_assoc() : [
    'total_usuarios' => 0,
    'nuevos_hoy'     => 0,
    'ventas_hoy'     => 0,
    'ingresos_hoy'   => 0,
];

// Obtener TODOS los productos activos de la BD (no solo destacados)
$productos_sql = "SELECT 
        p.id,
        p.nombre,
        p.precio,
        p.imagen_url,
        p.destacado,
        p.descripcion AS descripcion_corta,
        c.id AS categoria_id,
        c.nombre AS categoria_nombre,
        c.color AS categoria_color
    FROM productos p
    INNER JOIN categorias c ON p.categoria_id = c.id
    WHERE p.activo = 1
    ORDER BY p.destacado DESC, p.id DESC
    LIMIT 50";

$productos_result = $conexion->query($productos_sql);
$productos_todos = [];
if ($productos_result) {
    while ($row = $productos_result->fetch_assoc()) {
        $productos_todos[] = $row;
    }
}

// ORGANIZAR productos por categoría
$productos_por_categoria = [];
foreach ($productos_todos as $producto) {
    $categoria_id = $producto['categoria_id'];
    if (!isset($productos_por_categoria[$categoria_id])) {
        $productos_por_categoria[$categoria_id] = [
            'nombre' => $producto['categoria_nombre'],
            'color' => $producto['categoria_color'],
            'productos' => []
        ];
    }
    $productos_por_categoria[$categoria_id]['productos'][] = $producto;
}

// Obtener categorías populares
$categorias_sql = "SELECT 
        c.id,
        c.nombre,
        c.color,
        (SELECT COUNT(*) FROM productos p WHERE p.categoria_id = c.id AND p.activo = 1) AS cantidad
    FROM categorias c
    WHERE c.visible = 1
    ORDER BY c.id ASC
    LIMIT 6";
$categorias_result = $conexion->query($categorias_sql);
$categorias_populares = [];
if ($categorias_result) {
    while ($row = $categorias_result->fetch_assoc()) {
        $categorias_populares[] = $row;
    }
}

// Obtener productos DESTACADOS para la sección principal
$destacados_sql = "SELECT 
        p.id,
        p.nombre,
        p.precio,
        p.imagen_url,
        p.descripcion AS descripcion_corta,
        c.nombre AS categoria_nombre
    FROM productos p
    INNER JOIN categorias c ON p.categoria_id = c.id
    WHERE p.destacado = 1 AND p.activo = 1
    LIMIT 8";

$destacados_result = $conexion->query($destacados_sql);
$productos_destacados = [];
if ($destacados_result) {
    while ($row = $destacados_result->fetch_assoc()) {
        $productos_destacados[] = $row;
    }
}

// Obtener usuario actual si está logueado
$usuario_actual = null;
if (isLoggedIn()) {
    $usuario_actual = getCurrentUser();
}

/**
 * Mapeo de nombres de producto -> imágenes (logos) desde internet
 * Solo como respaldo si no hay imagen en BD
 */
$imagenes_predef = [
    'Netflix 4K Premium'         => 'https://upload.wikimedia.org/wikipedia/commons/0/08/Netflix_2015_logo.svg',
    'Netflix Básico HD'          => 'https://upload.wikimedia.org/wikipedia/commons/0/08/Netflix_2015_logo.svg',
    'HBO Max Completo'           => 'https://upload.wikimedia.org/wikipedia/commons/1/17/HBO_logo.svg',
    'Disney+ Pack Familiar'      => 'https://upload.wikimedia.org/wikipedia/commons/3/3e/Disney%2B_logo.svg',
    'Prime Video'                => 'https://upload.wikimedia.org/wikipedia/commons/f/f1/Prime_Video.png',
    'ChatGPT Plus'               => 'https://upload.wikimedia.org/wikipedia/commons/thumb/e/ef/ChatGPT-Logo.svg/960px-ChatGPT-Logo.svg.png',
    'Spotify Premium'            => 'https://www.logo.wine/a/logo/Spotify/Spotify-Icon-Logo.wine.svg',
    'ExpressVPN'                 => 'https://upload.wikimedia.org/wikipedia/commons/8/8e/ExpressVPN-logo.svg',
];

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
            overflow-x: hidden;
        }

        /* === HEADER === */
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
            height: 100%; /* Alinea con la altura del header */
        }

        .nav {
            display: flex;
            align-items: center;
            gap: 20px;
            height: 100%; /* Alinea con la altura del header */
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
        padding: 12px 28px; /* Más espacio interno */
        background: linear-gradient(135deg, #ffac12, #ff8c00); /* Gradiente completo */
        border-radius: 10px;
        color: #0d0f14 !important;
        font-weight: 700;
        transition: all 0.3s ease;
        border: none;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex; /* Cambiado de inline-block a inline-flex */
        align-items: center; /* Centra verticalmente el contenido */
        justify-content: center; /* Centra horizontalmente el contenido */
        min-width: 140px; /* Ancho mínimo para que no se encoja */
        box-shadow: 0 4px 15px rgba(255, 172, 18, 0.4); /* Color del shadow actualizado */
        white-space: nowrap; /* Evita que el texto se divida en varias líneas */
}

    .btn-registro:not(:has(i)) {
        min-width: 140px; /* Mismo ancho mínimo */
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
            color: #ffb028;
            font-weight: 600;
        }

        /* === HERO CON CARRUSEL === */
        .hero {
            position: relative;
            text-align: center;
            padding: 180px 20px 140px;
            overflow: hidden;
            margin-bottom: 80px;
            height: 80vh;
            min-height: 600px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .hero-carrusel {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -2;
        }

        .carrusel-slide {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            transition: opacity 1.5s ease-in-out;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }

        .carrusel-slide.active {
            opacity: 1;
        }

        /* Overlay oscuro sobre las imágenes */
        .hero-carrusel::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, 
                rgba(13, 15, 20, 0.85) 0%, 
                rgba(13, 15, 20, 0.7) 50%, 
                rgba(13, 15, 20, 0.9) 100%);
            z-index: 1;
        }

        .hero-content {
            position: relative;
            z-index: 2;
            max-width: 800px;
        }

        .hero h1 {
            font-size: 3.3rem;
            font-weight: 800;
            margin-bottom: 20px;
            background: linear-gradient(135deg, #fff 0%, #7f8081 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            line-height: 1.2;
            text-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .hero p {
            font-size: 1.2rem;
            color: #c9c9c9;
            margin-bottom: 35px;
            line-height: 1.6;
        }

        .hero-btn {
            padding: 16px 40px;
            background: linear-gradient(135deg, #ffac12, #e08f0d);
            color: #0d0f14;
            font-weight: 700;
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            display: inline-block;
            font-size: 1.1rem;
            box-shadow: 0 8px 25px rgba(255, 18, 18, 0.4);
            position: relative;
            overflow: hidden;
        }

        .hero-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.7s ease;
        }

        .hero-btn:hover::before {
            left: 100%;
        }

        .hero-btn:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 12px 35px rgba(18, 170, 255, 0.6);
            color: #fff;
        }

        /* Indicadores del carrusel */
        .carrusel-indicators {
            position: absolute;
            bottom: 40px;
            left: 0;
            right: 0;
            display: flex;
            justify-content: center;
            gap: 12px;
            z-index: 3;
        }

        .carrusel-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .carrusel-indicator.active {
            background: #ff9900;
            transform: scale(1.2);
            border-color: rgba(255, 255, 255, 0.5);
        }

        .carrusel-indicator:hover {
            background: #0de0c9;
            transform: scale(1.1);
        }

        /* Controles del carrusel */
        .carrusel-control {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: white;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            transition: all 0.3s ease;
            z-index: 3;
            backdrop-filter: blur(5px);
        }

        .carrusel-control:hover {
            background: rgba(18, 170, 255, 0.5);
            transform: translateY(-50%) scale(1.1);
        }

        .carrusel-control.prev {
            left: 30px;
        }

        .carrusel-control.next {
            right: 30px;
        }

        /* Efectos de partículas */
        .hero-particles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
            pointer-events: none;
        }

        .particle {
            position: absolute;
            background: rgba(18, 170, 255, 0.1);
            border-radius: 50%;
            animation: float 15s infinite linear;
        }

        @keyframes float {
            0% {
                transform: translateY(0) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 1;
            }
            100% {
                transform: translateY(-100vh) rotate(360deg);
                opacity: 0;
            }
        }

        /* === ESTADÍSTICAS === */
        .estadisticas {
            display: flex;
            justify-content: space-around;
            max-width: 1000px;
            margin: 0 auto 80px;
            padding: 30px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.06);
            backdrop-filter: blur(10px);
            gap: 20px;
            flex-wrap: wrap;
        }

        .stat-item {
            text-align: center;
            min-width: 180px;
        }

        .stat-number {
            font-size: 2.3rem;
            font-weight: 800;
            background: linear-gradient(135deg, #12aaff, #0de0c9);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #aaa;
            font-size: 0.95rem;
        }

        /* === CATEGORÍAS POPULARES === */
        .categorias {
            max-width: 1300px;
            margin: 0 auto 80px;
            padding: 0 20px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
        }

        .section-header h2 {
            font-size: 2.1rem;
            font-weight: 700;
            color: #fff;
            background: linear-gradient(135deg, #fff 0%, #7f8081);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .ver-todo {
            color: #12aaff;
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .ver-todo:hover {
            color: #0de0c9;
            transform: translateX(5px);
        }

        .grid-categorias {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
        }

        .categoria-card {
            background: rgba(255, 255, 255, 0.04);
            padding: 25px;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.06);
            backdrop-filter: blur(8px);
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
        }

        .categoria-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--cat-color, #12aaff), transparent);
        }

        .categoria-card:hover {
            transform: translateY(-8px);
            background: rgba(255, 255, 255, 0.06);
            border-color: rgba(18, 170, 255, 0.2);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.3);
        }

        .categoria-card h3 {
            font-size: 1.4rem;
            margin-bottom: 10px;
            color: #fff;
        }

        .categoria-card p {
            color: #bcbcbc;
            font-size: 0.95rem;
        }

        .categoria-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
        }

        .categoria-cantidad {
            color: #ffffff;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .categoria-link {
            color: #ff9e17;
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* === PRODUCTOS (grid reutilizable) === */
        .productos {
            max-width: 1400px;
            margin: 0 auto 100px;
            padding: 0 20px;
        }

        .grid-productos {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 30px;
        }

        .producto-card {
            background: rgba(255, 255, 255, 0.04);
            padding: 25px;
            border-radius: 18px;
            border: 1px solid rgba(255, 255, 255, 0.06);
            backdrop-filter: blur(10px);
            transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
            opacity: 0;
            animation: fadeInUp 0.6s ease forwards;
        }

        /* Estilo para producto encontrado en búsqueda */
        .producto-card.producto-encontrado {
            animation: highlightSearch 2s ease-in-out;
            border: 2px solid #ffac12 !important;
            position: relative;
            z-index: 1000;
        }

        .producto-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: linear-gradient(135deg, #ff6d00, #ff9e00);
            color: #fff;
            font-size: 0.75rem;
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: 700;
            z-index: 2;
        }

        .thumb {
            width: 100%;
            height: 180px;
            background: rgba(28, 31, 39, 0.7);
            border-radius: 14px;
            margin-bottom: 25px;
            background-size: contain;
            background-position: center;
            background-repeat: no-repeat;
            position: relative;
            overflow: hidden;
        }

        .thumb::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(18, 170, 255, 0.1), rgba(13, 224, 201, 0.05));
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .producto-card:hover .thumb::after {
            opacity: 1;
        }

        .producto-card h3 {
            font-size: 1.3rem;
            margin-bottom: 10px;
            color: #fff;
            font-weight: 600;
        }

        .producto-card p {
            color: #bcbcbc;
            margin-bottom: 20px;
            font-size: 0.95rem;
            line-height: 1.5;
            min-height: 45px;
        }

        .producto-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
        }

        .precio {
            font-size: 1.5rem;
            font-weight: 800;
            color: #fcfcfc;
            text-shadow: 0 2px 5px rgba(13, 224, 201, 0.2);
        }

        .producto-card button {
            padding: 12px 25px;
            background: linear-gradient(135deg, #ff9d0b, #df8f05);
            color: #0d0f14;
            font-weight: 700;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .producto-card button:hover {
            background: #0d92d6;
            color: #fff;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(18, 170, 255, 0.4);
        }

        /* === TESTIMONIOS === */
        .testimonios {
            max-width: 1200px;
            margin: 0 auto 100px;
            padding: 0 20px;
        }

        .testimonios-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-top: 40px;
        }

        .testimonio-card {
            background: rgba(255, 255, 255, 0.03);
            padding: 30px;
            border-radius: 18px;
            border: 1px solid rgba(255, 255, 255, 0.06);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            opacity: 0;
            animation: fadeInUp 0.6s ease forwards;
        }

        .testimonio-card:hover {
            transform: translateY(-5px);
            border-color: rgba(18, 170, 255, 0.2);
        }

        .testimonio-text {
            font-style: italic;
            color: #d0d0d0;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .testimonio-author {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .author-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #12aaff, #0de0c9);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #0d0f14;
            font-weight: 700;
            font-size: 1.2rem;
        }

        .author-info h4 {
            color: #fff;
            margin-bottom: 5px;
        }

        .author-info p {
            color: #aaa;
            font-size: 0.9rem;
        }

        /* === FOOTER === */
        .footer {
            text-align: center;
            padding: 40px 20px;
            background: rgba(11, 13, 18, 0.9);
            color: #7a7a7a;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            margin-top: 80px;
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 30px;
        }

        .footer-logo-img {
            height: 40px; /* Altura fija para el footer */
            width: auto;
            max-width: 200px; /* Ancho máximo para consistencia */
            object-fit: contain;
            margin-bottom: 10px;
        }

        .footer-links {
            display: flex;
            justify-content: center;
            gap: 30px;
            flex-wrap: wrap;
        }

        .footer-links a {
            color: #aaa;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .footer-links a:hover {
            color: #12aaff;
        }

        .footer-copyright {
            font-size: 0.9rem;
            color: #666;
            margin-top: 20px;
        }

        /* === ANIMACIONES DE BÚSQUEDA === */
        @keyframes highlightSearch {
            0% { 
                box-shadow: 0 0 0 0 rgba(255, 172, 18, 0.7); 
                transform: scale(1); 
            }
            50% { 
                box-shadow: 0 0 0 20px rgba(255, 172, 18, 0); 
                transform: scale(1.02); 
                border-color: #ffac12;
            }
            100% { 
                box-shadow: 0 0 0 0 rgba(255, 172, 18, 0); 
                transform: scale(1); 
            }
        }

        .no-results {
            grid-column: 1 / -1;
            text-align: center;
            padding: 40px;
            color: #999;
            font-size: 1.1rem;
            display: none;
        }

        .no-results.show {
            display: block;
        }

        /* === RESPONSIVE === */
        @media (max-width: 1024px) {
            .hero h1 {
                font-size: 2.8rem;
            }
            
            .nav {
                gap: 15px;
            }
            
            .search-bar {
                width: 180px;
            }
            
            .carrusel-control {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 15px; /* Reducido el gap */
                padding: 10px 20px; /* Padding reducido */
                min-height: auto; /* Altura automática en móviles */
                max-height: none;
                height: auto;
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
            
            .hero {
                padding: 120px 20px;
                height: 70vh;
                min-height: 500px;
            }
            
            .hero h1 {
                font-size: 2.2rem;
            }
            
            .hero p {
                font-size: 1.05rem;
            }
            
            .grid-productos,
            .grid-categorias {
                grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            }
            
            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .carrusel-control.prev {
                left: 15px;
            }
            
            .carrusel-control.next {
                right: 15px;
            }
            
            .logo-img {
                height: 35px; /* Logo más pequeño en tablets */
            }
        }

        @media (max-width: 480px) {
            .hero {
                height: 60vh;
                min-height: 400px;
            }
            
            .carrusel-control {
                display: none;
            }
            
            .grid-productos,
            .grid-categorias {
                grid-template-columns: 1fr;
            }
            
            .producto-footer {
                flex-direction: column;
                gap: 15px;
                align-items: stretch;
            }
            
            .producto-card button {
                width: 100%;
            }
            
            .logo-img {
                height: 30px; /* Logo más pequeño para móviles */
                max-width: 150px; /* Ancho máximo reducido */
            }
            
            .footer-logo-img {
                height: 150px;
                max-width: 150px;
            }
            
            .header {
                padding: 8px 15px; /* Padding más compacto */
            }
        }

        /* === ANIMACIONES === */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>

<header class="header">
    <div class="logo">
        <!-- REEMPLAZA LA URL CON LA RUTA DE TU IMAGEN DE LOGO -->
        <img src="assets/img/monkylogo.png" alt="Monkeystraming Logo" class="logo-img">
    </div>
    
    <div class="nav-container">
        <nav class="nav">
            <input type="text" class="search-bar" placeholder="🔍 Buscar productos..." id="searchInput">
            <a href="index.php"><i class="fas fa-home"></i> Inicio</a>
            <a href="productos.php"><i class="fas fa-box-open"></i> Productos</a>
            <a href="recargar.php"><i class="fas fa-coins"></i> Recargar</a>

            <?php if ($usuario_actual): ?>
                <span class="user-name-nav">
                    <i class="fas fa-user-circle"></i>
                    <?php echo htmlspecialchars($usuario_actual['nombre']); ?>
                </span>
                <span class="user-saldo-nav">
                    <i class="fas fa-wallet"></i>
                    S/ <?php echo number_format($usuario_actual['saldo'], 2); ?>
                </span>
                <a href="user/dashboard.php"><i class="fas fa-th-large"></i> Mi cuenta</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Salir</a>
            <?php else: ?>
                <a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a>  
                <a href="register.php" class="btn-registro"><i class="fas fa-user-plus"></i> Registrarse</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<!-- ===== HERO CON CARRUSEL ===== -->
<section class="hero">
    <!-- Carrusel de imágenes de fondo -->
    <div class="hero-carrusel">
        <div class="carrusel-slide active" 
             style="background-image: url('https://images.unsplash.com/photo-1536440136628-849c177e76a1?ixlib=rb-4.0.3&auto=format&fit=crop&w=1925&q=80')">
        </div>
        <div class="carrusel-slide" 
             style="background-image: url('https://images.unsplash.com/photo-1574375927938-d5a98e8ffe85?ixlib=rb-4.0.3&auto=format&fit=crop&w=1925&q=80')">
        </div>
        <div class="carrusel-slide" 
             style="background-image: url('https://images.unsplash.com/photo-1522869635100-9f4c5e86aa37?ixlib=rb-4.0.3&auto=format&fit=crop&w=1925&q=80')">
        </div>
        <div class="carrusel-slide" 
             style="background-image: url('https://images.unsplash.com/photo-1518709268805-4e9042af2176?ixlib=rb-4.0.3&auto=format&fit=crop&w=1925&q=80')">
        </div>
        <div class="carrusel-slide" 
             style="background-image: url('https://images.unsplash.com/photo-1593305841991-05c297ba4575?ixlib=rb-4.0.3&auto=format&fit=crop&w=1925&q=80')">
        </div>
    </div>

    <!-- Contenido principal -->
    <div class="hero-content">
        <h1>La mejor experiencia de streaming está aquí</h1>
        <p>Accede a miles de productos digitales con total seguridad, soporte 24/7 y los mejores precios del mercado.</p>
        <a href="productos.php" class="hero-btn"><i class="fas fa-rocket"></i> Explorar Catálogo</a>
    </div>

    <!-- Controles del carrusel -->
    <button class="carrusel-control prev">
        <i class="fas fa-chevron-left"></i>
    </button>
    <button class="carrusel-control next">
        <i class="fas fa-chevron-right"></i>
    </button>

    <!-- Indicadores -->
    <div class="carrusel-indicators">
        <div class="carrusel-indicator active" data-index="0"></div>
        <div class="carrusel-indicator" data-index="1"></div>
        <div class="carrusel-indicator" data-index="2"></div>
        <div class="carrusel-indicator" data-index="3"></div>
        <div class="carrusel-indicator" data-index="4"></div>
    </div>

    <!-- Efecto de partículas -->
    <div class="hero-particles" id="particles"></div>
</section>

<!-- ===== CATEGORÍAS POPULARES ===== -->
<section class="categorias">
    <div class="section-header">
        <h2><i class="fas fa-fire"></i> Categorías Populares</h2>
        <a href="productos.php" class="ver-todo">
            Ver todas <i class="fas fa-arrow-right"></i>
        </a>
    </div>
    
    <div class="grid-categorias">
        <?php if (!empty($categorias_populares)): ?>
            <?php foreach($categorias_populares as $categoria): ?>
                <div class="categoria-card" style="--cat-color: <?php echo $categoria['color'] ?? '#12aaff'; ?>">
                    <h3><?php echo htmlspecialchars($categoria['nombre']); ?></h3>
                    <p>Explora nuestra selección premium en esta categoría.</p>
                    <div class="categoria-info">
                        <span class="categoria-cantidad">
                            <?php echo (int)$categoria['cantidad']; ?> productos
                        </span>
                        <a href="productos.php?cat=<?php echo urlencode($categoria['id']); ?>" class="categoria-link">
                            Explorar <i class="fas fa-chevron-right"></i>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="categoria-card">
                <h3>No hay categorías</h3>
                <p>Configura categorías en tu panel de administración.</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ===== PRODUCTOS DESTACADOS (principal) ===== -->
<section class="productos">
    <div class="section-header">
        <h2><i class="fas fa-star"></i> Productos destacados</h2>
        <a href="productos.php" class="ver-todo">
            Ver todos los productos <i class="fas fa-arrow-right"></i>
        </a>
    </div>

    <div class="grid-productos" id="productos-destacados">
        <?php if (!empty($productos_destacados)): ?>
            <?php foreach($productos_destacados as $index => $producto): ?>
                <?php
                    // Resolver imagen (BD -> mapeo -> default)
                    if (!empty($producto['imagen_url'])) {
                        $img = $producto['imagen_url'];
                    } elseif (!empty($imagenes_predef[$producto['nombre']])) {
                        $img = $imagenes_predef[$producto['nombre']];
                    } else {
                        $img = 'https://via.placeholder.com/300x180/1a1d29/0de0c9?text=' . urlencode(substr($producto['nombre'], 0, 20));
                    }
                ?>
                <div class="producto-card" data-product-id="<?php echo (int)$producto['id']; ?>" 
                     data-product-name="<?php echo htmlspecialchars($producto['nombre']); ?>"
                     data-product-desc="<?php echo htmlspecialchars($producto['descripcion_corta'] ?? ''); ?>"
                     style="animation-delay: <?php echo 0.1 * ($index + 1); ?>s;">
                    <div class="thumb" style="background-image: url('<?php echo htmlspecialchars($img); ?>')">
                        <?php if (!empty($producto['categoria_nombre'])): ?>
                            <span class="producto-badge"><?php echo htmlspecialchars($producto['categoria_nombre']); ?></span>
                        <?php endif; ?>
                    </div>
                    <h3><?php echo htmlspecialchars($producto['nombre']); ?></h3>
                    <p><?php echo htmlspecialchars($producto['descripcion_corta'] ?? 'Sin descripción'); ?></p>
                    <div class="producto-footer">
                        <div class="precio">S/ <?php echo number_format($producto['precio'], 2); ?></div>
                        <button onclick="comprarProducto(<?php echo (int)$producto['id']; ?>, '<?php echo htmlspecialchars($producto['nombre'], ENT_QUOTES); ?>', <?php echo (float)$producto['precio']; ?>, <?php echo (int)($producto['categoria_id'] ?? 0); ?>)">
                            <i class="fas fa-shopping-cart"></i> Comprar
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="producto-card">
                <div class="thumb" style="background: #1a1d29;"></div>
                <h3>No hay productos destacados</h3>
                <p>Agrega productos y márcalos como "destacados" en el admin.</p>
                <div class="producto-footer">
                    <div class="precio">S/ 0.00</div>
                    <button onclick="window.location.href='productos.php'">
                        <i class="fas fa-box-open"></i> Ver catálogo
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ===== PRODUCTOS POR CATEGORÍA (dinámicos desde BD) ===== -->
<?php foreach($productos_por_categoria as $cat_id => $categoria): ?>
    <?php if (count($categoria['productos']) > 0): ?>
        <section class="productos" id="categoria-<?php echo $cat_id; ?>">
            <div class="section-header">
                <h2><i class="fas fa-tag"></i> <?php echo htmlspecialchars($categoria['nombre']); ?></h2>
                <a href="productos.php?cat=<?php echo $cat_id; ?>" class="ver-todo">
                    Ver más <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            
            <div class="grid-productos">
                <?php foreach($categoria['productos'] as $index => $producto): ?>
                    <?php
                        if (!empty($producto['imagen_url'])) {
                            $img = $producto['imagen_url'];
                        } elseif (!empty($imagenes_predef[$producto['nombre']])) {
                            $img = $imagenes_predef[$producto['nombre']];
                        } else {
                            $img = 'https://via.placeholder.com/300x180/1a1d29/0de0c9?text=' . urlencode(substr($producto['nombre'], 0, 20));
                        }
                    ?>
                    <div class="producto-card" data-product-id="<?php echo (int)$producto['id']; ?>"
                         data-product-name="<?php echo htmlspecialchars($producto['nombre']); ?>"
                         data-product-desc="<?php echo htmlspecialchars($producto['descripcion_corta'] ?? ''); ?>"
                         style="animation-delay: <?php echo 0.1 * ($index + 1); ?>s;">
                        <div class="thumb" style="background-image: url('<?php echo htmlspecialchars($img); ?>')">
                            <span class="producto-badge"><?php echo htmlspecialchars($categoria['nombre']); ?></span>
                        </div>
                        <h3><?php echo htmlspecialchars($producto['nombre']); ?></h3>
                        <p><?php echo htmlspecialchars($producto['descripcion_corta'] ?? 'Sin descripción'); ?></p>
                        <div class="producto-footer">
                            <div class="precio">S/ <?php echo number_format($producto['precio'], 2); ?></div>
                            <button onclick="comprarProducto(<?php echo (int)$producto['id']; ?>, '<?php echo htmlspecialchars($producto['nombre'], ENT_QUOTES); ?>', <?php echo (float)$producto['precio']; ?>, <?php echo (int)$producto['categoria_id']; ?>)">
                                <i class="fas fa-shopping-cart"></i> Comprar
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
<?php endforeach; ?>

<!-- ===== SI NO HAY PRODUCTOS EN BD, MOSTRAR MENSAJE ===== -->
<?php if (empty($productos_todos)): ?>
    <section class="productos">
        <div class="section-header">
            <h2><i class="fas fa-exclamation-triangle"></i> Catálogo vacío</h2>
        </div>
        <div style="text-align: center; padding: 50px; background: rgba(255,255,255,0.03); border-radius: 15px;">
            <i class="fas fa-box-open" style="font-size: 4rem; color: #666; margin-bottom: 20px;"></i>
            <h3 style="color: #ccc; margin-bottom: 15px;">No hay productos disponibles</h3>
            <p style="color: #888; max-width: 500px; margin: 0 auto 25px;">
                Aún no has agregado productos a tu tienda. Ve al panel de administración para comenzar.
            </p>
            <?php if ($usuario_actual && $usuario_actual['rol'] === 'admin'): ?>
                <a href="admin/productos.php" style="padding: 12px 30px; background: linear-gradient(135deg, #12aaff, #0de0c9); 
                   color: #0d0f14; text-decoration: none; border-radius: 10px; font-weight: 700; display: inline-flex; 
                   align-items: center; gap: 8px;">
                    <i class="fas fa-plus-circle"></i> Agregar productos
                </a>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>

<!-- ===== TESTIMONIOS ===== -->
<section class="testimonios">
    <div class="section-header">
        <h2><i class="fas fa-comment-dots"></i> Lo que dicen nuestros clientes</h2>
    </div>
    
    <div class="testimonios-grid">
        <div class="testimonio-card" style="animation-delay: 0.1s;">
            <p class="testimonio-text">"La mejor plataforma para streaming. Atención inmediata y productos 100% funcionales."</p>
            <div class="testimonio-author">
                <div class="author-avatar">CR</div>
                <div class="author-info">
                    <h4>Carlos Rodríguez</h4>
                    <p>Cliente desde 2023</p>
                </div>
            </div>
        </div>
        
        <div class="testimonio-card" style="animation-delay: 0.2s;">
            <p class="testimonio-text">"Increíble la variedad de productos. Todo en un solo lugar con soporte en español."</p>
            <div class="testimonio-author">
                <div class="author-avatar">AP</div>
                <div class="author-info">
                    <h4>Ana Pérez</h4>
                    <p>Cliente frecuente</p>
                </div>
            </div>
        </div>
        
        <div class="testimonio-card" style="animation-delay: 0.3s;">
            <p class="testimonio-text">"Soporte rápido y efectivo. Me resolvieron un problema en menos de 10 minutos."</p>
            <div class="testimonio-author">
                <div class="author-avatar">LM</div>
                <div class="author-info">
                    <h4>Luis Martínez</h4>
                    <p>Streamer profesional</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ===== FOOTER ===== -->
<footer class="footer">
    <div class="footer-content">
        <!-- Logo en el footer también -->
        
        <div class="footer-links">
            <a href="index.php">Inicio</a>
            <a href="productos.php">Productos</a>
            <a href="login.php">Login</a>
            <a href="register.php">Registro</a>
            <a href="recargar.php">Recargar</a>
            <a href="#">Soporte</a>
        </div>
        <div class="footer-copyright">
            © 2024 Monkeystraming. Todos los derechos reservados.<br>
            Streaming de calidad para todos.
        </div>
    </div>
</footer>

<script>
// Script para el carrusel
document.addEventListener('DOMContentLoaded', function() {
    const slides = document.querySelectorAll('.carrusel-slide');
    const indicators = document.querySelectorAll('.carrusel-indicator');
    const prevBtn = document.querySelector('.carrusel-control.prev');
    const nextBtn = document.querySelector('.carrusel-control.next');
    let currentSlide = 0;
    let slideInterval;

    // Inicializar partículas
    initParticles();

    // Función para cambiar de slide
    function goToSlide(n) {
        slides[currentSlide].classList.remove('active');
        indicators[currentSlide].classList.remove('active');
        
        currentSlide = (n + slides.length) % slides.length;
        
        slides[currentSlide].classList.add('active');
        indicators[currentSlide].classList.add('active');
    }

    // Función para siguiente slide
    function nextSlide() {
        goToSlide(currentSlide + 1);
    }

    // Función para slide anterior
    function prevSlide() {
        goToSlide(currentSlide - 1);
    }

    // Event listeners para controles
    nextBtn.addEventListener('click', nextSlide);
    prevBtn.addEventListener('click', prevSlide);

    // Event listeners para indicadores
    indicators.forEach(indicator => {
        indicator.addEventListener('click', function() {
            const index = parseInt(this.getAttribute('data-index'));
            goToSlide(index);
            resetInterval();
        });
    });

    // Autoplay del carrusel
    function startInterval() {
        slideInterval = setInterval(nextSlide, 5000);
    }

    function resetInterval() {
        clearInterval(slideInterval);
        startInterval();
    }

    // Iniciar autoplay
    startInterval();

    // Pausar autoplay al hacer hover
    const heroSection = document.querySelector('.hero');
    heroSection.addEventListener('mouseenter', () => {
        clearInterval(slideInterval);
    });

    heroSection.addEventListener('mouseleave', () => {
        startInterval();
    });

    // Función para crear partículas
    function initParticles() {
        const particlesContainer = document.getElementById('particles');
        const particleCount = 30;
        
        for (let i = 0; i < particleCount; i++) {
            const particle = document.createElement('div');
            particle.classList.add('particle');
            
            // Tamaño aleatorio
            const size = Math.random() * 6 + 2;
            particle.style.width = `${size}px`;
            particle.style.height = `${size}px`;
            
            // Posición aleatoria
            particle.style.left = `${Math.random() * 100}%`;
            particle.style.top = `${Math.random() * 100}%`;
            
            // Opacidad aleatoria
            particle.style.opacity = Math.random() * 0.4 + 0.1;
            
            // Animación personalizada
            const duration = Math.random() * 20 + 10;
            const delay = Math.random() * 5;
            particle.style.animation = `float ${duration}s infinite linear ${delay}s`;
            
            // Color aleatorio (azules y cian)
            const colors = [
                'rgba(18, 170, 255, 0.3)',
                'rgba(13, 224, 201, 0.3)',
                'rgba(100, 200, 255, 0.2)',
                'rgba(0, 150, 255, 0.2)'
            ];
            particle.style.background = colors[Math.floor(Math.random() * colors.length)];
            
            particlesContainer.appendChild(particle);
        }
    }

    // Touch swipe para móviles
    let touchStartX = 0;
    let touchEndX = 0;

    heroSection.addEventListener('touchstart', e => {
        touchStartX = e.changedTouches[0].screenX;
    });

    heroSection.addEventListener('touchend', e => {
        touchEndX = e.changedTouches[0].screenX;
        handleSwipe();
    });

    function handleSwipe() {
        const swipeThreshold = 50;
        
        if (touchEndX < touchStartX - swipeThreshold) {
            // Swipe izquierda = siguiente
            nextSlide();
            resetInterval();
        }
        
        if (touchEndX > touchStartX + swipeThreshold) {
            // Swipe derecha = anterior
            prevSlide();
            resetInterval();
        }
    }
});

// =================== SISTEMA DE BÚSQUEDA EN INDEX ===================
const searchInput = document.getElementById('searchInput');
let searchTimeout = null;
let currentHighlightedCard = null;

// Función para remover resaltado anterior
function removeHighlight() {
    if (currentHighlightedCard) {
        currentHighlightedCard.classList.remove('producto-encontrado');
        currentHighlightedCard.style.border = '';
        currentHighlightedCard.style.zIndex = '';
        currentHighlightedCard = null;
    }
}

// Función para resaltar un producto
function highlightProduct(card) {
    removeHighlight();
    
    // Aplicar clase de animación
    card.classList.add('producto-encontrado');
    
    // Hacer scroll suave a la tarjeta
    card.scrollIntoView({ 
        behavior: 'smooth', 
        block: 'center',
        inline: 'center' 
    });
    
    currentHighlightedCard = card;
    
    // Remover la animación después de 3 segundos
    setTimeout(() => {
        card.classList.remove('producto-encontrado');
        // Mantener un borde sutil
        card.style.border = '1px solid rgba(255, 172, 18, 0.3)';
        card.style.boxShadow = '0 0 15px rgba(255, 172, 18, 0.2)';
        
        setTimeout(() => {
            card.style.border = '';
            card.style.boxShadow = '';
            currentHighlightedCard = null;
        }, 5000);
    }, 3000);
}

// Función para realizar la búsqueda
function performSearch() {
    const searchTerm = searchInput.value.trim().toLowerCase();
    
    if (searchTerm === '') {
        // Mostrar todos los productos si no hay término de búsqueda
        document.querySelectorAll('.producto-card').forEach(card => {
            card.style.display = '';
        });
        removeHighlight();
        return;
    }
    
    let foundProducts = [];
    let firstMatch = null;
    
    // Buscar en todos los productos
    document.querySelectorAll('.producto-card').forEach(card => {
        const productName = card.getAttribute('data-product-name')?.toLowerCase() || '';
        const productDesc = card.getAttribute('data-product-desc')?.toLowerCase() || '';
        
        // Verificar si coincide con nombre o descripción
        if (productName.includes(searchTerm) || productDesc.includes(searchTerm)) {
            foundProducts.push(card);
            if (!firstMatch) {
                firstMatch = card;
            }
        } else {
            // Ocultar productos que no coinciden
            card.style.display = 'none';
        }
    });
    
    // Mostrar productos encontrados
    foundProducts.forEach(card => {
        card.style.display = '';
    });
    
    // Resaltar el primer producto encontrado
    if (firstMatch) {
        highlightProduct(firstMatch);
    } else {
        removeHighlight();
    }
}

// Event listener para el campo de búsqueda
if (searchInput) {
    // Buscar al escribir (con debounce)
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(performSearch, 300);
    });
    
    // Buscar al presionar Enter
    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            performSearch();
        }
    });
    
    // Limpiar búsqueda al hacer clic en la "X" (si el navegador la agrega)
    searchInput.addEventListener('search', function() {
        if (this.value === '') {
            performSearch();
        }
    });
}

// =================== FUNCIÓN DE COMPRA (actualizada) ===================
const userIsLogged = <?php echo isLoggedIn() ? 'true' : 'false'; ?>;
const userSaldo    = <?php echo $usuario_actual ? (float)$usuario_actual['saldo'] : 0; ?>;

function cerrarModal(modal) {
  if (modal && modal.parentNode) modal.parentNode.removeChild(modal);
}

function comprarProducto(id, nombre, precio, categoriaId = null) {
  // Si NO está logueado, mostrar modal para iniciar sesión
  if (!userIsLogged) {
    const modal = document.createElement('div');
    modal.style.cssText = `
      position:fixed; inset:0; background:rgba(0,0,0,0.85);
      display:flex; justify-content:center; align-items:center;
      z-index:2000; backdrop-filter:blur(5px);
    `;

    modal.innerHTML = `
      <div style="background:linear-gradient(135deg,#12151d,#0d0f14);
        padding:40px; border-radius:20px; max-width:500px; width:90%;
        border:1px solid rgba(18,170,255,0.2); text-align:center;
        box-shadow:0 20px 50px rgba(0,0,0,0.5);">
        <div style="font-size:3rem; color:#ffac12; margin-bottom:20px;">
          <i class="fas fa-shopping-cart"></i>
        </div>

        <h2 style="color:#ffac12; margin-bottom:20px;">Inicia sesión para comprar</h2>

        <div style="background:rgba(255,255,255,0.05); padding:20px; border-radius:12px; margin-bottom:30px;">
          <p style="font-size:1.2rem; margin-bottom:10px;"><strong>${nombre}</strong></p>
          <p style="color:#ffb028; font-size:1.8rem; font-weight:800;">S/ ${precio.toFixed(2)}</p>
          <p style="color:#aaa; font-size:.9rem; margin-top:10px;">Pago mensual • Renovación automática</p>
        </div>

        <div style="display:flex; gap:15px; justify-content:center; flex-wrap:wrap;">
          <button type="button" class="btn-cancelar"
            style="padding:14px 30px; background:#333; color:#fff; border:none; border-radius:10px; cursor:pointer; font-weight:600;">
            Cancelar
          </button>

          <button type="button" class="btn-principal"
            style="padding:14px 30px; background:linear-gradient(135deg,#ffac12,#ff8c00);
            color:#0d0f14; border:none; border-radius:10px; font-weight:700; cursor:pointer; display:flex; align-items:center; gap:8px;">
            <i class="fas fa-sign-in-alt"></i> Iniciar sesión
          </button>
        </div>

        <p style="color:#888; font-size:.85rem; margin-top:25px;">
          <i class="fas fa-shield-alt"></i> Compra 100% segura • Soporte 24/7
        </p>
      </div>
    `;

    document.body.appendChild(modal);

    const btnCancelar = modal.querySelector('.btn-cancelar');
    const btnPrincipal = modal.querySelector('.btn-principal');

    btnCancelar.addEventListener('click', () => cerrarModal(modal));
    modal.addEventListener('click', e => { if (e.target === modal) cerrarModal(modal); });

    btnPrincipal.addEventListener('click', () => {
      window.location.href = 'login.php?redirect=' + encodeURIComponent('index.php');
    });
    
    return;
  }

  // Si ESTÁ logueado, redirigir directamente a productos.php con el ID del producto
  // Incluir categoría si está disponible
  let url = `productos.php?producto_id=${id}`;
  if (categoriaId) {
    url += `&cat=${categoriaId}`;
  }
  window.location.href = url;
}

// IMPORTANTE: Esta función solo se usa en productos.php
async function ejecutarCompra(productId, btnPrincipal, btnCancelar) {
  btnPrincipal.disabled = true;
  btnCancelar.disabled = true;
  const oldHtml = btnPrincipal.innerHTML;
  btnPrincipal.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Procesando...`;

  try {
    const body = new URLSearchParams();
    body.append('action', 'buy');
    body.append('product_id', String(productId));

    const r = await fetch('comprar.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      credentials: 'same-origin',
      body
    });

    const j = await r.json();

    if (!r.ok || !j.ok) {
      if (j.redirect) {
        window.location.href = j.redirect;
        return;
      }
      alert(j.message || 'No se pudo completar la compra.');
      return;
    }

    window.location.href = j.redirect || 'user/dashboard.php';

  } catch (e) {
    alert('Error de red o respuesta inválida del servidor.');
  } finally {
    btnPrincipal.disabled = false;
    btnCancelar.disabled = false;
    btnPrincipal.innerHTML = oldHtml;
  }
}

function procesarCompra(idProducto) {
  comprarProducto(idProducto, 'Producto', 0);
}
</script>

</body>
</html>