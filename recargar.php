<?php
require_once 'config/database.php';
require_once __DIR__ . '/includes/auth.php';

// Verificar que el usuario esté logueado
if (!isLoggedIn()) {
    redirect('login.php');
}

$page_title   = "Recargar Saldo - Monkeystraming";
$success_msg  = '';
$error_msg    = '';

// Usuario actual
$usuario_actual = getCurrentUser();
if (!$usuario_actual) {
    redirect('login.php');
}

// Obtener métodos de pago activos
$metodos_sql    = "SELECT * FROM metodos_pago WHERE activo = 1 ORDER BY orden ASC";
$metodos_result = $conexion->query($metodos_sql);
$metodos_pago   = [];

if ($metodos_result) {
    while ($row = $metodos_result->fetch_assoc()) {
        // clave = identificador interno del método (yape, plin, etc.)
        $metodos_pago[$row['clave']] = $row;
    }
}

// Montos sugeridos
$montos_sugeridos = [10, 20, 50, 100, 200, 500];

// Manejo del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $metodo = cleanInput($_POST['metodo'] ?? '');
    $monto  = floatval($_POST['monto'] ?? 0);

    $min_recharge = 5.00;
    $max_recharge = 5000.00;

    if ($monto < $min_recharge || $monto > $max_recharge) {
        $error_msg = "El monto debe estar entre S/ {$min_recharge} y S/ {$max_recharge}";
    } elseif (!isset($metodos_pago[$metodo])) {
        $error_msg = "Método de pago no válido";
    } elseif (!isset($_FILES['comprobante']) || $_FILES['comprobante']['error'] !== UPLOAD_ERR_OK) {
        $error_msg = "Debes adjuntar el comprobante de pago.";
    } else {

        $metodo_info         = $metodos_pago[$metodo];
        $comision_porcentaje = floatval($metodo_info['comision_porcentaje'] ?? 0);
        $comision_fija       = floatval($metodo_info['comision_fija'] ?? 0);

        // Comisión + bonus (5% promo si monto >= 100)
        $comision = ($monto * $comision_porcentaje / 100) + $comision_fija;
        $bonus    = ($monto >= 100) ? ($monto * 0.05) : 0;
        $total_recibir = $monto - $comision + $bonus;

        // Insertar recarga
        $sql = "INSERT INTO recargas (usuario_id, metodo, monto, comision, estado)
                VALUES (?, ?, ?, ?, 'pendiente')";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("isdd", $_SESSION['user_id'], $metodo, $monto, $comision);

        if ($stmt->execute()) {
            $recarga_id = $stmt->insert_id;

            // Guardar comprobante
            $comprobante = $_FILES['comprobante'];
            if ($comprobante['error'] === UPLOAD_ERR_OK) {
                $extension      = strtolower(pathinfo($comprobante['name'], PATHINFO_EXTENSION));
                $nombre_archivo = "recarga_" . $recarga_id . "_" . time() . "." . $extension;
                $ruta_destino   = "assets/comprobantes/" . $nombre_archivo;

                if (!is_dir('assets/comprobantes')) {
                    mkdir('assets/comprobantes', 0755, true);
                }

                if (move_uploaded_file($comprobante['tmp_name'], $ruta_destino)) {
                    $update_sql  = "UPDATE recargas SET comprobante_url = ? WHERE id = ?";
                    $update_stmt = $conexion->prepare($update_sql);
                    $update_stmt->bind_param("si", $ruta_destino, $recarga_id);
                    $update_stmt->execute();
                }
            }

            $_SESSION['success'] = "¡Solicitud de recarga enviada! Se añadirán <strong>S/ " . number_format($total_recibir, 2) . "</strong> a tu saldo una vez validemos el comprobante.";
            redirect('recargar.php');
        } else {
            $error_msg = "Error al procesar la recarga. Por favor, intenta de nuevo.";
        }
    }
}

// Mensajes flash
if (isset($_SESSION['success'])) {
    $success_msg = $_SESSION['success'];
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $error_msg = $_SESSION['error'];
    unset($_SESSION['error']);
}

// Recargas recientes del usuario
$recargas_sql = "SELECT id, metodo, monto, comision, estado, fecha_solicitud
                 FROM recargas
                 WHERE usuario_id = ?
                 ORDER BY fecha_solicitud DESC
                 LIMIT 5";
$recargas_stmt = $conexion->prepare($recargas_sql);
$recargas_stmt->bind_param("i", $_SESSION['user_id']);
$recargas_stmt->execute();
$recargas_recientes = $recargas_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
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

        /* === HEADER (COPIADO DEL INDEX) === */
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
            color: #ffbc13;
            font-weight: 600;
        }

        /* === SECCIÓN RECARGA === */
        .recarga-section {
            max-width: 1200px;
            margin: 60px auto 50px;
            padding: 0 20px 40px;
        }

        .user-info-card {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(255,255,255,0.04);
            border-radius: 18px;
            padding: 20px 25px;
            border: 1px solid rgba(255,255,255,0.08);
            backdrop-filter: blur(12px);
            margin-bottom: 25px;
        }

        .saldo-info h3 {
            font-size: 0.95rem;
            color: #aaa;
            margin-bottom: 5px;
        }

        .saldo-amount {
            font-size: 1.8rem;
            font-weight: 700;
            color: #f9af1b;
        }

        .user-details .user-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: #fff;
        }

        .user-details p {
            color: #aaa;
            font-size: 0.9rem;
            margin-top: 4px;
        }

        .section-header {
            margin: 25px 0 20px;
        }

        .section-header h2 {
            font-size: 1.8rem;
            color: #fff;
            margin-bottom: 6px;
        }

        .section-header p {
            color: #aaa;
            font-size: 0.95rem;
        }

        /* === ALERTAS === */
        .alert {
            padding: 15px 18px;
            border-radius: 12px;
            margin-bottom: 15px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert i {
            font-size: 1.2rem;
        }

        .alert-success {
            background: rgba(52, 199, 89, 0.12);
            border: 1px solid rgba(52, 199, 89, 0.35);
            color: #34c759;
        }

        .alert-error {
            background: rgba(255, 59, 48, 0.12);
            border: 1px solid rgba(255, 59, 48, 0.35);
            color: #ff3b30;
        }

        /* === GRID PRINCIPAL === */
        .recarga-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.2fr) minmax(0, 1.4fr);
            gap: 25px;
            margin-bottom: 40px;
        }

        .card {
            background: rgba(255,255,255,0.04);
            border-radius: 18px;
            padding: 20px 22px;
            border: 1px solid rgba(255,255,255,0.08);
            backdrop-filter: blur(10px);
        }

        /* === MÉTODOS DE PAGO === */
        .metodos-title {
            color: #fff;
            margin-bottom: 15px;
            font-size: 1.3rem;
        }

        .metodo-card {
            background: rgba(255,255,255,0.03);
            border-radius: 14px;
            padding: 16px 14px;
            border: 1px solid rgba(255,255,255,0.08);
            margin-bottom: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .metodo-card:hover {
            transform: translateY(-2px);
            border-color: rgba(18,170,255,0.6);
            box-shadow: 0 8px 18px rgba(0,0,0,0.25);
        }

        .metodo-card.selected {
            border-color: #12aaff;
            box-shadow: 0 0 0 1px rgba(18,170,255,0.7);
            background: linear-gradient(135deg, rgba(18, 170, 255, 0.18), rgba(13, 224, 201, 0.12));
        }

        .metodo-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 10px;
        }

        .metodo-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .metodo-icon i {
            font-size: 1.3rem;
            color: #12aaff;
        }

        .metodo-info h4 {
            font-size: 1rem;
            color: #fff;
            margin-bottom: 2px;
        }

        .metodo-info p {
            font-size: 0.85rem;
            color: #aaa;
        }

        .metodo-details {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            font-size: 0.8rem;
            color: #ccc;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .detail-item i {
            color: #12aaff;
            font-size: 0.85rem;
        }

        /* === MONTOS === */
        .monto-title {
            color: #fff;
            margin-bottom: 10px;
            font-size: 1.2rem;
        }

        .monto-options {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 15px;
        }

        .monto-option {
            padding: 8px 14px;
            border-radius: 999px;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            font-size: 0.9rem;
            color: #ddd;
            cursor: pointer;
            transition: all 0.25s ease;
        }

        .monto-option:hover {
            border-color: #12aaff;
            color: #12aaff;
        }

        .monto-option.selected {
            background: linear-gradient(135deg, #12aaff, #0de0c9);
            color: #0d0f14;
            border-color: transparent;
            box-shadow: 0 6px 14px rgba(18,170,255,0.35);
        }

        .monto-input-group {
            margin-top: 10px;
        }

        .monto-input-label {
            font-size: 0.9rem;
            color: #ccc;
            margin-bottom: 5px;
        }

        .monto-input-wrapper {
            display: flex;
            align-items: center;
            background: rgba(0,0,0,0.4);
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.12);
            padding: 6px 10px;
        }

        .monto-input-wrapper span {
            color: #aaa;
            margin-right: 6px;
            font-size: 0.9rem;
        }

        .monto-input-wrapper input {
            border: none;
            background: transparent;
            outline: none;
            color: #fff;
            flex: 1;
            padding: 6px 4px;
            font-size: 1rem;
        }

        .monto-help {
            margin-top: 4px;
            font-size: 0.8rem;
            color: #777;
        }

        /* === RESUMEN === */
        .resumen-title {
            font-size: 1.1rem;
            color: #fff;
            margin-bottom: 10px;
        }

        .resumen-items {
            margin-top: 5px;
            font-size: 0.9rem;
        }

        .resumen-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 6px;
            color: #ccc;
        }

        .resumen-row.total {
            margin-top: 10px;
            padding-top: 8px;
            border-top: 1px dashed rgba(255,255,255,0.1);
            font-weight: 700;
            color: #0de0c9;
        }

        /* === COMPROBANTE === */
        .upload-title {
            font-size: 1.1rem;
            color: #fff;
            margin-bottom: 10px;
        }

        .upload-subtitle {
            font-size: 0.85rem;
            color: #aaa;
            margin-bottom: 12px;
        }

        .upload-area {
            border: 1px dashed rgba(255,255,255,0.3);
            border-radius: 14px;
            padding: 25px 15px;
            text-align: center;
            background: rgba(0,0,0,0.35);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .upload-area.dragover {
            border-color: #12aaff;
            background: rgba(18,170,255,0.08);
        }

        .upload-area i {
            font-size: 2rem;
            color: #12aaff;
            margin-bottom: 10px;
        }

        .upload-area p {
            font-size: 0.9rem;
            color: #ccc;
        }

        .upload-area small {
            display: block;
            margin-top: 6px;
            font-size: 0.8rem;
            color: #777;
        }

        .file-info {
            display: none;
            margin-top: 15px;
            align-items: center;
            gap: 12px;
        }

        .file-preview {
            width: 52px;
            height: 52px;
            border-radius: 10px;
            overflow: hidden;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .file-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .file-meta {
            flex: 1;
        }

        .file-meta .name {
            font-size: 0.9rem;
            color: #fff;
        }

        .file-meta .size {
            font-size: 0.8rem;
            color: #aaa;
        }

        .file-remove-btn {
            border: none;
            background: transparent;
            color: #ff3b30;
            cursor: pointer;
            font-size: 0.9rem;
        }

        /* === DETALLE MÉTODO === */
        .metodo-extra {
            margin-top: 18px;
        }

        /* ✅ ACTUALIZADO: MÁS GRANDE Y CUADRADO */
        .metodo-image-container {
            width: 100%;
            max-width: 420px;          /* más grande */
            aspect-ratio: 1 / 1;       /* cuadrado */
            height: auto;              /* por si el navegador soporta aspect-ratio */
            border-radius: 14px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.1);
            background: rgba(0,0,0,0.45);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;       /* centrado */
        }

        .metodo-image-container img {
            width: 100%;
            height: 100%;
            object-fit: contain;       /* NO recorta */
        }

        .metodo-instrucciones {
            font-size: 0.9rem;
            color: #ccc;
            line-height: 1.5;
        }

        /* === BOTÓN ENVIAR === */
        .btn-recargar {
            margin-top: 20px;
            width: 100%;
            padding: 15px;
            border-radius: 14px;
            border: none;
            cursor: pointer;
            background: linear-gradient(135deg, #fd9c1e, #ffd829);
            color: #0d0f14;
            font-weight: 700;
            font-size: 1rem;
            box-shadow: 0 10px 25px rgba(18,170,255,0.35);
            transition: all 0.3s ease;
        }

        .btn-recargar:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(18,170,255,0.5);
        }

        .btn-recargar:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            box-shadow: none;
        }

        .loading {
            display: inline-block;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            border: 3px solid rgba(255,255,255,0.2);
            border-top-color: #12aaff;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* === HISTORIAL === */
        .historial-section {
            margin-top: 15px;
        }

        .historial-section h3 {
            font-size: 1.2rem;
            color: #fff;
            margin-bottom: 12px;
        }

        .historial-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
            overflow: hidden;
            border-radius: 14px;
            background: rgba(0,0,0,0.5);
        }

        .historial-table th,
        .historial-table td {
            padding: 10px 12px;
            text-align: left;
        }

        .historial-table thead {
            background: rgba(255,255,255,0.05);
        }

        .historial-table tbody tr:nth-child(even) {
            background: rgba(255,255,255,0.02);
        }

        .historial-table th {
            color: #ccc;
            font-weight: 600;
        }

        .historial-table td {
            color: #ddd;
        }

        .status {
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status.pendiente {
            background: rgba(255, 204, 0, 0.15);
            color: #ffcc00;
        }

        .status.aprobado {
            background: rgba(52, 199, 89, 0.18);
            color: #34c759;
        }

        .status.rechazado {
            background: rgba(255, 59, 48, 0.18);
            color: #ff3b30;
        }

        /* === RESPONSIVE === */
        @media (max-width: 992px) {
            .recarga-grid {
                grid-template-columns: 1fr;
            }
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

            .recarga-section {
                margin-top: 40px;
                padding: 0 15px 30px;
            }

            .user-info-card {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }

            /* ✅ Para móviles: un poco más compacto */
            .metodo-image-container {
                max-width: 320px;
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

<section class="recarga-section">
    <!-- Información del usuario -->
    <div class="user-info-card">
        <div class="saldo-info">
            <h3>Saldo actual</h3>
            <div class="saldo-amount">S/ <?php echo number_format($usuario_actual['saldo'], 2); ?></div>
        </div>
        <div class="user-details">
            <div class="user-name">Hola, <?php echo htmlspecialchars($usuario_actual['nombre']); ?> 👋</div>
            <p><?php echo htmlspecialchars($usuario_actual['email']); ?></p>
        </div>
    </div>

    <!-- Mensajes -->
    <?php if ($success_msg): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo $success_msg; ?>
        </div>
    <?php endif; ?>

    <?php if ($error_msg): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $error_msg; ?>
        </div>
    <?php endif; ?>

    <div class="section-header">
        <h2>Recargar saldo</h2>
        <p>Elige un método de pago, ingresa el monto y sube tu comprobante. Tu saldo se actualizará después de la validación.</p>
    </div>

    <form action="" method="POST" enctype="multipart/form-data" id="recargaForm">
        <div class="recarga-grid">
            <!-- COLUMNA MÉTODOS -->
            <div class="card">
                <h3 class="metodos-title">Método de pago</h3>

                <?php if (!empty($metodos_pago)): ?>
                    <?php foreach($metodos_pago as $key => $metodo): ?>
                        <div class="metodo-card"
                             data-metodo="<?php echo htmlspecialchars($key); ?>"
                             data-imagen="<?php echo htmlspecialchars($metodo['imagen'] ?? 'assets/img/metodos/default.png'); ?>"
                             data-instrucciones="<?php echo htmlspecialchars($metodo['instrucciones'] ?? ''); ?>"
                             data-comision="<?php echo htmlspecialchars($metodo['comision_porcentaje'] ?? '0'); ?>">
                            <div class="metodo-header">
                                <div class="metodo-icon">
                                    <i class="<?php echo htmlspecialchars($metodo['icono'] ?? 'fas fa-credit-card'); ?>"></i>
                                </div>
                                <div class="metodo-info">
                                    <h4><?php echo htmlspecialchars($metodo['nombre']); ?></h4>
                                    <p><?php echo htmlspecialchars($metodo['descripcion'] ?? ''); ?></p>
                                </div>
                            </div>
                            <div class="metodo-details">
                                <div class="detail-item">
                                    <i class="fas fa-percentage"></i>
                                    <span>Comisión:
                                        <?php
                                        $comision_text = (isset($metodo['comision']) && $metodo['comision'] !== '')
                                            ? $metodo['comision']
                                            : (($metodo['comision_porcentaje'] ?? 0) . '%');
                                        echo htmlspecialchars($comision_text);
                                        ?>
                                    </span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-clock"></i>
                                    <span>Tiempo: <?php echo htmlspecialchars($metodo['tiempo'] ?? 'Inmediato'); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="font-size:0.9rem;color:#aaa;">No hay métodos de pago configurados.</p>
                <?php endif; ?>

                <input type="hidden" name="metodo" id="metodoSeleccionado" value="" required>
            </div>

            <!-- COLUMNA MONTOS / RESUMEN / COMPROBANTE -->
            <div>
                <!-- MONTOS -->
                <div class="card" style="margin-bottom: 15px;">
                    <h3 class="monto-title">Selecciona el monto</h3>
                    <div class="monto-options">
                        <?php foreach($montos_sugeridos as $m): ?>
                            <div class="monto-option" data-monto="<?php echo (float)$m; ?>">
                                S/ <?php echo number_format($m, 2); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="monto-input-group">
                        <div class="monto-input-label">Otro monto</div>
                        <div class="monto-input-wrapper">
                            <span>S/</span>
                            <input type="number" step="0.01" min="0" name="monto" id="montoInput" placeholder="0.00">
                        </div>
                        <div class="monto-help">Mínimo S/ 5.00 - Máximo S/ 5000.00</div>
                    </div>
                </div>

                <!-- RESUMEN -->
                <div class="card" id="resumenRecarga" style="display:none; margin-bottom: 15px;">
                    <h3 class="resumen-title">Resumen de la recarga</h3>
                    <div class="resumen-items">
                        <div class="resumen-row">
                            <span>Monto ingresado:</span>
                            <span id="resumenMonto">S/ 0.00</span>
                        </div>
                        <div class="resumen-row">
                            <span>Comisión aproximada:</span>
                            <span id="resumenComision">S/ 0.00</span>
                        </div>
                        <div class="resumen-row">
                            <span>Bonus (>= S/ 100):</span>
                            <span id="resumenBonus">S/ 0.00</span>
                        </div>
                        <div class="resumen-row total">
                            <span>Total a recibir:</span>
                            <span id="resumenTotal">S/ 0.00</span>
                        </div>
                    </div>
                </div>

                <!-- COMPROBANTE -->
                <div class="card" style="margin-bottom: 15px;">
                    <h3 class="upload-title">Comprobante de pago</h3>
                    <p class="upload-subtitle">Sube la captura o el PDF del pago realizado.</p>

                    <div class="upload-area" id="fileUploadArea">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Haz clic o arrastra tu archivo aquí</p>
                        <small>Formatos permitidos: JPG, PNG, PDF — Máx: 5MB</small>
                    </div>
                    <input type="file" name="comprobante" id="comprobante" accept="image/*,.pdf" hidden>

                    <div class="file-info" id="fileInfo">
                        <div class="file-preview" id="filePreview"></div>
                        <div class="file-meta">
                            <div class="name" id="fileName"></div>
                            <div class="size" id="fileSize"></div>
                        </div>
                        <button type="button" class="file-remove-btn" id="removeFile">
                            <i class="fas fa-times"></i> Quitar
                        </button>
                    </div>
                </div>

                <!-- DETALLE DEL MÉTODO -->
                <div class="card metodo-extra" id="imagenMetodo" style="display:none;">
                    <h3 class="upload-title">Detalles del método seleccionado</h3>
                    <div class="metodo-image-container" id="metodoImageContainer"></div>
                    <div class="metodo-instrucciones" id="instruccionesMetodo"></div>
                </div>

                <!-- BOTÓN ENVIAR -->
                <button type="submit" class="btn-recargar" id="btnRecargar" disabled>
                    <span id="btnText">Enviar solicitud de recarga</span>
                </button>
            </div>
        </div>

        <!-- HISTORIAL -->
        <div class="historial-section">
            <h3><i class="fas fa-history"></i> Historial de recargas recientes</h3>
            <table class="historial-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Método</th>
                        <th>Monto</th>
                        <th>Estado</th>
                        <th>Recibido (sin bonus)</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($recargas_recientes && $recargas_recientes->num_rows > 0): ?>
                    <?php while ($rec = $recargas_recientes->fetch_assoc()):
                        $clave_metodo   = $rec['metodo'];
                        $metodo_nombre  = isset($metodos_pago[$clave_metodo]) ? $metodos_pago[$clave_metodo]['nombre'] : ucfirst($clave_metodo);
                        $metodo_icono   = isset($metodos_pago[$clave_metodo]) ? ($metodos_pago[$clave_metodo]['icono'] ?? 'fas fa-credit-card') : 'fas fa-credit-card';
                        $total_recibido = $rec['monto'] - $rec['comision'];
                    ?>
                        <tr>
                            <td><?php echo date('d/m/Y H:i', strtotime($rec['fecha_solicitud'])); ?></td>
                            <td><i class="<?php echo htmlspecialchars($metodo_icono); ?>"></i> <?php echo htmlspecialchars($metodo_nombre); ?></td>
                            <td>S/ <?php echo number_format($rec['monto'], 2); ?></td>
                            <td>
                                <span class="status <?php echo htmlspecialchars($rec['estado']); ?>">
                                    <?php echo ucfirst($rec['estado']); ?>
                                </span>
                            </td>
                            <td>S/ <?php echo number_format($total_recibido, 2); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align:center; padding:18px; color:#aaa;">
                            Aún no tienes recargas registradas.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </form>
</section>

<script>
let metodoSeleccionado  = '';
let montoSeleccionado   = 0;
let archivoSeleccionado = null;

// Selección de método
document.querySelectorAll('.metodo-card').forEach(card => {
    card.addEventListener('click', function () {
        document.querySelectorAll('.metodo-card').forEach(c => c.classList.remove('selected'));
        this.classList.add('selected');

        metodoSeleccionado = this.getAttribute('data-metodo');
        document.getElementById('metodoSeleccionado').value = metodoSeleccionado;

        mostrarImagenMetodo(
            this.getAttribute('data-imagen'),
            this.getAttribute('data-instrucciones'),
            parseFloat(this.getAttribute('data-comision') || '0')
        );

        actualizarResumen();
        validarFormulario();
    });
});

// Montos sugeridos
document.querySelectorAll('.monto-option').forEach(option => {
    option.addEventListener('click', function () {
        document.querySelectorAll('.monto-option').forEach(o => o.classList.remove('selected'));
        this.classList.add('selected');

        montoSeleccionado = parseFloat(this.getAttribute('data-monto')) || 0;
        document.getElementById('montoInput').value = montoSeleccionado;

        actualizarResumen();
        validarFormulario();
    });
});

// Input de monto manual
const montoInput = document.getElementById('montoInput');
if (montoInput) {
    montoInput.addEventListener('input', function () {
        montoSeleccionado = parseFloat(this.value) || 0;
        document.querySelectorAll('.monto-option').forEach(o => o.classList.remove('selected'));
        actualizarResumen();
        validarFormulario();
    });
}

// Imagen e instrucciones del método
function mostrarImagenMetodo(rutaImagen, instrucciones, comision) {
    const contenedor      = document.getElementById('imagenMetodo');
    const imageContainer  = document.getElementById('metodoImageContainer');
    const instruccionesEl = document.getElementById('instruccionesMetodo');

    if (!contenedor || !imageContainer || !instruccionesEl) return;

    imageContainer.innerHTML = '';
    const img = new Image();
    img.src = rutaImagen;
    img.alt = 'Método de pago';
    img.onload = function () {
        imageContainer.appendChild(img);
    };
    img.onerror = function () {
        imageContainer.innerHTML = '<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:2rem;color:#12aaff;"><i class="fas fa-credit-card"></i></div>';
    };

    instruccionesEl.innerHTML = instrucciones || 'Sigue las instrucciones del método de pago seleccionado.';
    contenedor.style.display = 'block';
}

// Resumen de recarga
function actualizarResumen() {
    const resumen = document.getElementById('resumenRecarga');
    if (!resumen) return;

    const metodoCard = document.querySelector('.metodo-card.selected');
    const comisionPorcentaje = metodoCard ? parseFloat(metodoCard.getAttribute('data-comision') || '0') : 0;
    const monto = montoSeleccionado;

    if (monto > 0) {
        const comision = (monto * comisionPorcentaje) / 100;
        const bonus    = monto >= 100 ? (monto * 0.05) : 0;
        const total    = monto - comision + bonus;

        document.getElementById('resumenMonto').textContent    = `S/ ${monto.toFixed(2)}`;
        document.getElementById('resumenComision').textContent = `S/ ${comision.toFixed(2)}`;
        document.getElementById('resumenBonus').textContent    = `S/ ${bonus.toFixed(2)}`;
        document.getElementById('resumenTotal').textContent    = `S/ ${total.toFixed(2)}`;

        resumen.style.display = 'block';
    } else {
        resumen.style.display = 'none';
    }
}

// Upload de comprobante
const fileInput       = document.getElementById('comprobante');
const fileUploadArea  = document.getElementById('fileUploadArea');
const fileInfo        = document.getElementById('fileInfo');
const fileName        = document.getElementById('fileName');
const fileSize        = document.getElementById('fileSize');
const filePreview     = document.getElementById('filePreview');
const removeFileBtn   = document.getElementById('removeFile');

if (fileUploadArea && fileInput) {
    fileUploadArea.addEventListener('click', () => fileInput.click());

    fileInput.addEventListener('change', () => {
        handleFiles(fileInput.files);
    });

    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        fileUploadArea.addEventListener(eventName, e => {
            e.preventDefault();
            e.stopPropagation();
        }, false);
    });

    ['dragenter', 'dragover'].forEach(eventName => {
        fileUploadArea.addEventListener(eventName, () => fileUploadArea.classList.add('dragover'), false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        fileUploadArea.addEventListener(eventName, () => fileUploadArea.classList.remove('dragover'), false);
    });

    fileUploadArea.addEventListener('drop', e => {
        handleFiles(e.dataTransfer.files);
    });
}

function handleFiles(files) {
    if (!files || files.length === 0) return;

    const file    = files[0];
    const maxSize = 5 * 1024 * 1024;
    const validTypes = ['image/jpeg', 'image/png', 'application/pdf'];

    if (file.size > maxSize) {
        alert('El archivo es demasiado grande. Máximo 5MB.');
        return;
    }
    if (!validTypes.includes(file.type)) {
        alert('Formato no válido. Solo JPG, PNG o PDF.');
        return;
    }

    archivoSeleccionado = file;

    if (fileInfo) {
        fileName.textContent = file.name;
        fileSize.textContent = (file.size / 1024 / 1024).toFixed(2) + ' MB';

        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = e => {
                filePreview.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
            };
            reader.readAsDataURL(file);
        } else {
            filePreview.innerHTML = '<i class="fas fa-file-pdf" style="font-size:1.6rem;color:#ff3b30;"></i>';
        }

        fileInfo.style.display = 'flex';
    }

    validarFormulario();
}

if (removeFileBtn) {
    removeFileBtn.addEventListener('click', () => {
        archivoSeleccionado = null;
        if (fileInput) fileInput.value = '';
        if (fileInfo) fileInfo.style.display = 'none';
        validarFormulario();
    });
}

// Validar formulario para habilitar botón
function validarFormulario() {
    const btn = document.getElementById('btnRecargar');
    if (!btn) return;

    const metodoValido  = !!metodoSeleccionado;
    const montoValido   = montoSeleccionado >= 5 && montoSeleccionado <= 5000;
    const archivoValido = archivoSeleccionado !== null;

    btn.disabled = !(metodoValido && montoValido && archivoValido);
}

// Envío REAL del formulario
document.getElementById('recargaForm').addEventListener('submit', function (e) {
    if (!metodoSeleccionado || !montoSeleccionado || !archivoSeleccionado) {
        e.preventDefault();
        alert('Completa método, monto y comprobante antes de enviar.');
        return;
    }

    const btn = document.getElementById('btnRecargar');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="loading"></span> Procesando...';
    }
});
</script>

</body>
</html>
