<?php
// admin/index.php — Dashboard REAL (sin demo)
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/sidebar.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * ✅ Protección ADMIN (sin depender de getCurrentUser)
 * Tu admin/login.php guarda: admin_id, admin_email, admin_name, admin_table
 */
if (empty($_SESSION['admin_id'])) {
    header('Location: index.php');

    exit;
}

// Datos del admin desde sesión (fallback)
$admin = [
    'id'     => $_SESSION['admin_id'] ?? null,
    'email'  => $_SESSION['admin_email'] ?? '',
    'nombre' => $_SESSION['admin_name'] ?? 'Administrador',
];

// Si quieres, intentamos refrescar datos desde BD (si existe tabla usuarios)
if (!empty($admin['email'])) {
    $stmt = $conexion->prepare("SELECT id, nombre, email FROM usuarios WHERE email = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("s", $admin['email']);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if ($row) {
            $admin['id'] = $row['id'] ?? $admin['id'];
            $admin['nombre'] = $row['nombre'] ?? $admin['nombre'];
            $admin['email'] = $row['email'] ?? $admin['email'];
        }
    }
}

// =====================
// Helpers DB
// =====================
function pickDateColumn(mysqli $conexion, string $table, array $candidates): ?string {
    foreach ($candidates as $col) {
        $c = $conexion->real_escape_string($col);
        $rs = $conexion->query("SHOW COLUMNS FROM `$table` LIKE '$c'");
        if ($rs && $rs->num_rows > 0) return $col;
    }
    return null;
}
function tableExists(mysqli $conexion, string $table): bool {
    $t = $conexion->real_escape_string($table);
    $rs = $conexion->query("SHOW TABLES LIKE '$t'");
    return ($rs && $rs->num_rows > 0);
}
function colExists(mysqli $conexion, string $table, string $col): bool {
    $t = $conexion->real_escape_string($table);
    $c = $conexion->real_escape_string($col);
    $rs = $conexion->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
    return ($rs && $rs->num_rows > 0);
}

// =====================
// Config
// =====================
$page_title = "Panel de Administración - Monkeystraming";

// =====================
// Stats reales
// =====================
$estadisticas = [
    'usuarios_totales'      => 0,
    'usuarios_nuevos_hoy'   => 0,
    'ventas_hoy'            => 0,
    'ingresos_hoy'          => 0.00,
    'recargas_pendientes'   => 0,
    'productos_activos'     => 0,
    'productos_agotados'    => 0,
    'tickets_soporte'       => 0,
];

$hasUsuarios  = tableExists($conexion, 'usuarios');
$hasProductos = tableExists($conexion, 'productos');
$hasCompras   = tableExists($conexion, 'compras');
$hasRecargas  = tableExists($conexion, 'recargas');
$hasTickets   = tableExists($conexion, 'tickets');

// Usuarios
if ($hasUsuarios) {
    $rs = $conexion->query("SELECT COUNT(*) c FROM usuarios");
    if ($rs) $estadisticas['usuarios_totales'] = (int)$rs->fetch_assoc()['c'];

    $userDateCol = pickDateColumn($conexion, 'usuarios', ['created_at', 'fecha_registro', 'fecha_creacion', 'fecha']);
    if ($userDateCol) {
        $rs = $conexion->query("SELECT COUNT(*) c FROM usuarios WHERE DATE($userDateCol) = CURDATE()");
        if ($rs) $estadisticas['usuarios_nuevos_hoy'] = (int)$rs->fetch_assoc()['c'];
    }
}

// Productos
if ($hasProductos) {
    $activoCol = colExists($conexion, 'productos', 'activo') ? 'activo' : null;

    if ($activoCol) {
        $rs = $conexion->query("SELECT COUNT(*) c FROM productos WHERE activo=1");
        if ($rs) $estadisticas['productos_activos'] = (int)$rs->fetch_assoc()['c'];
    } else {
        $rs = $conexion->query("SELECT COUNT(*) c FROM productos");
        if ($rs) $estadisticas['productos_activos'] = (int)$rs->fetch_assoc()['c'];
    }

    if (colExists($conexion, 'productos', 'stock')) {
        $whereActivo = $activoCol ? " AND activo=1" : "";
        $rs = $conexion->query("SELECT COUNT(*) c FROM productos WHERE stock<=0 $whereActivo");
        if ($rs) $estadisticas['productos_agotados'] = (int)$rs->fetch_assoc()['c'];
    }
}

// Recargas pendientes
if ($hasRecargas && colExists($conexion, 'recargas', 'estado')) {
    $rs = $conexion->query("SELECT COUNT(*) c FROM recargas WHERE estado='pendiente'");
    if ($rs) $estadisticas['recargas_pendientes'] = (int)$rs->fetch_assoc()['c'];
}

// Ventas (compras completadas)
if ($hasCompras && colExists($conexion, 'compras', 'estado') && colExists($conexion, 'compras', 'monto')) {
    $compraDateCol = pickDateColumn($conexion, 'compras', ['created_at', 'fecha_compra', 'fecha', 'fecha_solicitud']);
    if ($compraDateCol) {
        $rs = $conexion->query("SELECT COUNT(*) c, COALESCE(SUM(monto),0) s
                                FROM compras
                                WHERE estado='completada' AND DATE($compraDateCol)=CURDATE()");
        if ($rs) {
            $row = $rs->fetch_assoc();
            $estadisticas['ventas_hoy']   = (int)$row['c'];
            $estadisticas['ingresos_hoy'] = (float)$row['s'];
        }
    } else {
        $rs = $conexion->query("SELECT COUNT(*) c, COALESCE(SUM(monto),0) s
                                FROM compras
                                WHERE estado='completada'");
        if ($rs) {
            $row = $rs->fetch_assoc();
            $estadisticas['ventas_hoy']   = (int)$row['c'];
            $estadisticas['ingresos_hoy'] = (float)$row['s'];
        }
    }
}

// Tickets (abiertos/en proceso)
if ($hasTickets && colExists($conexion, 'tickets', 'estado')) {
    $rs = $conexion->query("SELECT COUNT(*) c FROM tickets WHERE estado IN ('abierto','en_proceso','en proceso','pendiente')");
    if ($rs) $estadisticas['tickets_soporte'] = (int)$rs->fetch_assoc()['c'];
}

// =====================
// Listados reales (recientes)
// =====================
$usuarios_recientes = [];
if ($hasUsuarios) {
    $orderCol = pickDateColumn($conexion, 'usuarios', ['created_at', 'fecha_registro', 'fecha_creacion', 'fecha']);
    $orderBy  = $orderCol ? $orderCol : 'id';

    $cols = "id,
             " . (colExists($conexion,'usuarios','nombre') ? "nombre" : (colExists($conexion,'usuarios','full_name') ? "full_name AS nombre" : "'' AS nombre")) . ",
             " . (colExists($conexion,'usuarios','email') ? "email" : "'' AS email") . ",
             " . (colExists($conexion,'usuarios','saldo') ? "saldo" : "0 AS saldo") . ",
             " . (colExists($conexion,'usuarios','activo') ? "activo" : (colExists($conexion,'usuarios','is_active') ? "is_active AS activo" : "1 AS activo")) . ",
             " . (colExists($conexion,'usuarios','role') ? "role" : (colExists($conexion,'usuarios','rol') ? "rol AS role" : "'' AS role")) . "
    ";

    $sql = "SELECT $cols FROM usuarios ORDER BY $orderBy DESC LIMIT 6";
    $rs = $conexion->query($sql);
    if ($rs) {
        while ($row = $rs->fetch_assoc()) {
            $row['estado'] = ((int)$row['activo'] === 1) ? 'activo' : 'inactivo';
            $usuarios_recientes[] = $row;
        }
    }
}

$ventas_recientes = [];
if ($hasCompras) {
    $compraDateCol = pickDateColumn($conexion, 'compras', ['created_at', 'fecha_compra', 'fecha', 'fecha_solicitud']);
    $fechaSelect   = $compraDateCol ? "$compraDateCol AS fecha" : "NULL AS fecha";

    $sql = "
        SELECT c.id, c.usuario_id, c.producto_id, c.monto, c.estado, $fechaSelect,
               u.nombre AS usuario_nombre,
               p.nombre AS producto_nombre
        FROM compras c
        LEFT JOIN usuarios u ON u.id = c.usuario_id
        LEFT JOIN productos p ON p.id = c.producto_id
        ORDER BY c.id DESC
        LIMIT 6
    ";
    $rs = $conexion->query($sql);
    if ($rs) {
        while ($row = $rs->fetch_assoc()) $ventas_recientes[] = $row;
    }
}

$recargas_pendientes = [];
if ($hasRecargas) {
    $sql = "
        SELECT r.id, r.usuario_id, r.metodo, r.monto, r.estado, r.fecha_solicitud, r.comprobante_url,
               u.nombre AS usuario_nombre
        FROM recargas r
        LEFT JOIN usuarios u ON u.id = r.usuario_id
        WHERE r.estado='pendiente'
        ORDER BY r.fecha_solicitud DESC
        LIMIT 6
    ";
    $rs = $conexion->query($sql);
    if ($rs) {
        while ($row = $rs->fetch_assoc()) $recargas_pendientes[] = $row;
    }
}

$tickets_soporte = [];
if ($hasTickets) {
    $ticketDateCol = pickDateColumn($conexion, 'tickets', ['created_at', 'fecha', 'fecha_creacion']);
    $fechaSelect   = $ticketDateCol ? "$ticketDateCol AS fecha" : "NULL AS fecha";

    $colsAsunto = colExists($conexion,'tickets','asunto') ? 'asunto' : (colExists($conexion,'tickets','titulo') ? 'titulo AS asunto' : "'' AS asunto");
    $colsPri    = colExists($conexion,'tickets','prioridad') ? 'prioridad' : "'media' AS prioridad";

    $sql = "
        SELECT t.id, t.usuario_id, $colsAsunto, $colsPri, t.estado, $fechaSelect,
               u.nombre AS usuario_nombre
        FROM tickets t
        LEFT JOIN usuarios u ON u.id = t.usuario_id
        ORDER BY t.id DESC
        LIMIT 6
    ";
    $rs = $conexion->query($sql);
    if ($rs) {
        while ($row = $rs->fetch_assoc()) $tickets_soporte[] = $row;
    }
}

$adminName  = $admin['nombre'] ?? 'Administrador';
$adminEmail = $admin['email'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/panel-shell.css?v=admin-polish-4">
    <style>
        /* === MONKYDOS - ADMIN DASHBOARD (REAL) === */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        *{margin:0;padding:0;box-sizing:border-box;font-family:'Inter',sans-serif}
        :root{
            --sidebar-width:280px;
            --header-height:70px;
            --primary-gradient:linear-gradient(135deg,#12aaff,#0de0c9);
            --danger-gradient:linear-gradient(135deg,#ff4757,#ff3838);
            --warning-gradient:linear-gradient(135deg,#ff9f43,#ffaf40);
            --success-gradient:linear-gradient(135deg,#10ac84,#00d2d3);
        }
        body{
            background:linear-gradient(135deg,#0d0f14 0%,#11131a 35%,#0b0c11 100%);
            color:#e5e5e5;min-height:100vh;display:flex;overflow-x:hidden
        }
        .admin-sidebar{
            width:var(--sidebar-width);
            background:rgba(255,255,255,0.03);
            border-right:1px solid rgba(255,255,255,0.06);
            backdrop-filter:blur(15px);
            height:100vh;position:fixed;left:0;top:0;z-index:1000;
            display:flex;flex-direction:column;padding:25px 0;overflow-y:auto;
            transition:transform .3s ease;
        }
        .admin-logo{padding:0 25px;margin-bottom:40px}
        .admin-logo .logo{
            font-size:1.8rem;font-weight:800;background:var(--primary-gradient);
            -webkit-background-clip:text;-webkit-text-fill-color:transparent;margin-bottom:5px
        }
        .admin-logo .subtitle{font-size:.85rem;color:#aaa;font-weight:500}
        .admin-menu{flex:1}
        .menu-section{margin-bottom:30px;padding:0 15px}
        .menu-section h3{font-size:.8rem;text-transform:uppercase;letter-spacing:1px;color:#666;margin-bottom:15px;padding-left:10px}
        .menu-item{
            display:flex;align-items:center;gap:15px;padding:14px 20px;color:#d0d0d0;text-decoration:none;
            border-radius:12px;margin-bottom:8px;transition:all .3s ease;position:relative;overflow:hidden
        }
        .menu-item::before{
            content:'';position:absolute;left:0;top:0;bottom:0;width:3px;background:var(--primary-gradient);
            transform:translateX(-100%);transition:transform .3s ease
        }
        .menu-item:hover{background:rgba(255,255,255,0.05);color:#fff;transform:translateX(5px)}
        .menu-item:hover::before{transform:translateX(0)}
        .menu-item.active{
            background:linear-gradient(135deg,rgba(18,170,255,0.15),rgba(13,224,201,0.1));
            color:#12aaff
        }
        .menu-item.active::before{transform:translateX(0)}
        .menu-item i{font-size:1.2rem;width:24px;text-align:center}
        .menu-badge{
            margin-left:auto;background:var(--danger-gradient);color:#fff;font-size:.75rem;padding:3px 8px;border-radius:10px;
            font-weight:600;min-width:20px;text-align:center
        }
        .admin-main{flex:1;margin-left:var(--sidebar-width);min-height:100vh}
        .admin-header{
            height:var(--header-height);background:rgba(255,255,255,0.03);backdrop-filter:blur(15px);
            border-bottom:1px solid rgba(255,255,255,0.06);
            padding:0 30px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:999
        }
        .header-title h1{font-size:1.6rem;font-weight:700;color:#fff}
        .header-title p{color:#aaa;font-size:.9rem;margin-top:3px}
        .header-actions{display:flex;align-items:center;gap:20px}
        .search-bar{
            padding:10px 18px;border-radius:10px;border:1px solid rgba(255,255,255,0.1);background:rgba(255,255,255,0.05);
            color:#fff;outline:none;width:300px;font-size:.9rem;backdrop-filter:blur(5px)
        }
        .search-bar:focus{border-color:#12aaff;box-shadow:0 0 0 3px rgba(18,170,255,0.2)}
        .user-menu{display:flex;align-items:center;gap:15px;position:relative}
        .user-avatar{
            width:45px;height:45px;border-radius:50%;background:var(--primary-gradient);
            display:flex;align-items:center;justify-content:center;color:#0d0f14;font-weight:700;font-size:1.2rem;cursor:pointer
        }
        .user-info{display:flex;flex-direction:column}
        .user-name{font-weight:600;color:#fff}
        .user-role{font-size:.8rem;color:#12aaff;font-weight:500}
        .admin-content{padding:30px}
        .stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px;margin-bottom:40px}
        .stat-card{
            background:rgba(255,255,255,0.04);border-radius:16px;padding:25px;border:1px solid rgba(255,255,255,0.06);
            backdrop-filter:blur(10px);transition:all .3s ease;position:relative;overflow:hidden
        }
        .stat-card:hover{transform:translateY(-5px);border-color:rgba(18,170,255,0.2);box-shadow:0 15px 30px rgba(0,0,0,0.3)}
        .stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--card-gradient,var(--primary-gradient))}
        .stat-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:15px}
        .stat-icon{
            width:50px;height:50px;border-radius:12px;background:rgba(255,255,255,0.1);
            display:flex;align-items:center;justify-content:center;font-size:1.5rem;color:var(--icon-color,#12aaff)
        }
        .stat-change{
            font-size:.9rem;font-weight:600;padding:4px 10px;border-radius:20px;
            background:rgba(52,199,89,0.2);color:#34c759
        }
        .stat-content h3{
            font-size:2.2rem;font-weight:800;margin-bottom:5px;background:var(--card-gradient,var(--primary-gradient));
            -webkit-background-clip:text;-webkit-text-fill-color:transparent
        }
        .stat-content p{color:#aaa;font-size:.9rem}
        .content-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(350px,1fr));gap:25px;margin-bottom:40px}
        .content-card{
            background:rgba(255,255,255,0.04);border-radius:18px;border:1px solid rgba(255,255,255,0.06);
            backdrop-filter:blur(10px);overflow:hidden
        }
        .card-header{
            padding:20px 25px;border-bottom:1px solid rgba(255,255,255,0.06);display:flex;justify-content:space-between;align-items:center
        }
        .card-header h3{font-size:1.3rem;color:#fff;font-weight:600}
        .card-link{color:#12aaff;text-decoration:none;font-size:.9rem;font-weight:500;display:flex;align-items:center;gap:5px;transition:all .3s ease}
        .card-link:hover{color:#0de0c9;transform:translateX(3px)}
        .card-body{padding:25px}
        .data-table{width:100%;border-collapse:collapse}
        .data-table th{
            text-align:left;padding:12px 0;color:#aaa;font-weight:500;font-size:.85rem;text-transform:uppercase;letter-spacing:.5px;
            border-bottom:1px solid rgba(255,255,255,0.06)
        }
        .data-table td{padding:15px 0;border-bottom:1px solid rgba(255,255,255,0.04)}
        .data-table tr:last-child td{border-bottom:none}
        .user-cell{display:flex;align-items:center;gap:12px}
        .user-avatar-sm{
            width:35px;height:35px;border-radius:50%;background:var(--primary-gradient);
            display:flex;align-items:center;justify-content:center;color:#0d0f14;font-weight:600;font-size:.9rem
        }
        .user-info-sm{display:flex;flex-direction:column}
        .user-name-sm{font-weight:500;color:#fff}
        .user-email-sm{font-size:.8rem;color:#aaa}
        .status-badge{
            padding:5px 12px;border-radius:20px;font-size:.8rem;font-weight:600;display:inline-block
        }
        .status-activo{background:rgba(52,199,89,0.2);color:#34c759}
        .status-inactivo{background:rgba(255,59,48,0.2);color:#ff3b30}
        .status-pendiente{background:rgba(255,204,0,0.2);color:#ffcc00}
        .status-completada{background:rgba(52,199,89,0.2);color:#34c759}
        .action-buttons{display:flex;gap:8px;justify-content:flex-end}
        .action-btn{
            width:35px;height:35px;border-radius:8px;border:none;display:flex;align-items:center;justify-content:center;
            cursor:pointer;transition:all .3s ease;font-size:.9rem
        }
        .action-view{background:rgba(18,170,255,0.1);color:#12aaff}
        .action-edit{background:rgba(255,204,0,0.1);color:#ffcc00}
        .quick-actions{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;margin-top:40px}
        .quick-action-card{
            background:rgba(255,255,255,0.04);border-radius:16px;padding:25px;border:1px solid rgba(255,255,255,0.06);
            backdrop-filter:blur(10px);transition:all .3s ease;text-align:center;cursor:pointer
        }
        .quick-action-card:hover{transform:translateY(-5px);border-color:rgba(18,170,255,0.2);box-shadow:0 10px 25px rgba(0,0,0,0.2)}
        .quick-action-icon{
            width:60px;height:60px;margin:0 auto 15px;border-radius:16px;background:var(--primary-gradient);
            display:flex;align-items:center;justify-content:center;font-size:1.8rem;color:#0d0f14
        }
        .sidebar-toggle{
            display:none;background:none;border:none;color:#fff;font-size:1.5rem;cursor:pointer;position:fixed;top:20px;left:20px;z-index:1001
        }
        @media (max-width: 992px){
            .admin-sidebar{transform:translateX(-100%)}
            .admin-main{margin-left:0}
            .sidebar-toggle{display:block}
        }
        .muted{color:#777;font-size:.9rem}
    </style>
</head>
<body>

<?php renderAdminSidebar($conexion, $currentPage ?? basename($_SERVER['PHP_SELF'])); ?>

<main class="admin-main">
    <header class="admin-header">
        <div class="header-title">
            <h1>Dashboard</h1>
            <p>Bienvenido, <?php echo htmlspecialchars($adminName); ?><?php echo $adminEmail ? " — " . htmlspecialchars($adminEmail) : ""; ?></p>
        </div>

        <div class="header-actions">
            <input type="text" class="search-bar" placeholder="🔍 Buscar en el sistema..." disabled>
            <div class="user-menu">
                <div class="user-avatar"><i class="fas fa-user-cog"></i></div>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($adminName); ?></div>
                    <div class="user-role">ADMIN</div>
                </div>
            </div>
        </div>
    </header>

    <div class="admin-content">
        <div class="stats-grid">
            <div class="stat-card" style="--card-gradient: linear-gradient(135deg, #12aaff, #0de0c9); --icon-color: #12aaff;">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <span class="stat-change">+<?php echo (int)$estadisticas['usuarios_nuevos_hoy']; ?> hoy</span>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format((int)$estadisticas['usuarios_totales']); ?></h3>
                    <p>Usuarios Totales</p>
                </div>
            </div>

            <div class="stat-card" style="--card-gradient: linear-gradient(135deg, #10ac84, #00d2d3); --icon-color: #10ac84;">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
                    <span class="stat-change">+<?php echo (int)$estadisticas['ventas_hoy']; ?> hoy</span>
                </div>
                <div class="stat-content">
                    <h3>S/ <?php echo number_format((float)$estadisticas['ingresos_hoy'], 2); ?></h3>
                    <p>Ingresos (hoy)</p>
                </div>
            </div>

            <div class="stat-card" style="--card-gradient: linear-gradient(135deg, #ff9f43, #ffaf40); --icon-color: #ff9f43;">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-coins"></i></div>
                    <span class="stat-change"><?php echo (int)$estadisticas['recargas_pendientes']; ?> pendientes</span>
                </div>
                <div class="stat-content">
                    <h3><?php echo (int)$estadisticas['productos_activos']; ?></h3>
                    <p>Productos Activos</p>
                </div>
            </div>

            <div class="stat-card" style="--card-gradient: linear-gradient(135deg, #ff4757, #ff3838); --icon-color: #ff4757;">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-ticket-alt"></i></div>
                    <span class="stat-change"><?php echo (int)$estadisticas['tickets_soporte']; ?> abiertos</span>
                </div>
                <div class="stat-content">
                    <h3><?php echo (int)$estadisticas['productos_agotados']; ?></h3>
                    <p>Productos Agotados</p>
                </div>
            </div>
        </div>

        <!-- Listados -->
        <div class="content-grid">

            <div class="content-card">
                <div class="card-header">
                    <h3><i class="fas fa-users"></i> Usuarios recientes</h3>
                    <a href="usuarios.php" class="card-link">Ver todos <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="card-body">
                    <?php if (count($usuarios_recientes) > 0): ?>
                        <table class="data-table">
                            <thead>
                            <tr>
                                <th>Usuario</th><th>Saldo</th><th>Estado</th><th style="text-align:right;">Acciones</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($usuarios_recientes as $u): ?>
                                <tr>
                                    <td>
                                        <div class="user-cell">
                                            <div class="user-avatar-sm">
                                                <?php echo strtoupper(substr((string)($u['nombre'] ?? 'U'), 0, 1)); ?>
                                            </div>
                                            <div class="user-info-sm">
                                                <div class="user-name-sm"><?php echo htmlspecialchars($u['nombre'] ?? ('Usuario #' . $u['id'])); ?></div>
                                                <div class="user-email-sm"><?php echo htmlspecialchars($u['email'] ?? ''); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>S/ <?php echo number_format((float)($u['saldo'] ?? 0), 2); ?></td>
                                    <td><span class="status-badge status-<?php echo htmlspecialchars($u['estado']); ?>"><?php echo htmlspecialchars($u['estado']); ?></span></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a class="action-btn action-view" title="Ver" href="usuarios.php?view=<?php echo (int)$u['id']; ?>" style="text-decoration:none;"><i class="fas fa-eye"></i></a>
                                            <a class="action-btn action-edit" title="Editar" href="usuarios.php?edit=<?php echo (int)$u['id']; ?>" style="text-decoration:none;"><i class="fas fa-edit"></i></a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="muted">No hay datos de usuarios para mostrar.</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="content-card">
                <div class="card-header">
                    <h3><i class="fas fa-shopping-cart"></i> Ventas recientes</h3>
                    <a href="ventas.php" class="card-link">Ver todas <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="card-body">
                    <?php if (count($ventas_recientes) > 0): ?>
                        <table class="data-table">
                            <thead><tr><th>ID</th><th>Producto</th><th>Monto</th><th>Estado</th></tr></thead>
                            <tbody>
                            <?php foreach ($ventas_recientes as $v): ?>
                                <tr>
                                    <td>#<?php echo (int)$v['id']; ?></td>
                                    <td>
                                        <div class="user-info-sm">
                                            <div class="user-name-sm"><?php echo htmlspecialchars($v['producto_nombre'] ?? ('Producto #' . (int)$v['producto_id'])); ?></div>
                                            <div class="user-email-sm"><?php echo htmlspecialchars($v['usuario_nombre'] ?? ('Usuario #' . (int)$v['usuario_id'])); ?></div>
                                        </div>
                                    </td>
                                    <td>S/ <?php echo number_format((float)$v['monto'], 2); ?></td>
                                    <td><span class="status-badge status-<?php echo htmlspecialchars($v['estado']); ?>"><?php echo htmlspecialchars($v['estado']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="muted">No hay ventas para mostrar.</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="content-card">
                <div class="card-header">
                    <h3><i class="fas fa-coins"></i> Recargas pendientes</h3>
                    <a href="recargas-admin.php" class="card-link">Ver todas <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="card-body">
                    <?php if (count($recargas_pendientes) > 0): ?>
                        <table class="data-table">
                            <thead><tr><th>Usuario</th><th>Método</th><th>Monto</th><th style="text-align:right;">Acciones</th></tr></thead>
                            <tbody>
                            <?php foreach ($recargas_pendientes as $r): ?>
                                <tr>
                                    <td>
                                        <div class="user-info-sm">
                                            <div class="user-name-sm"><?php echo htmlspecialchars($r['usuario_nombre'] ?? ('Usuario #' . (int)$r['usuario_id'])); ?></div>
                                            <div class="user-email-sm">ID recarga: #<?php echo (int)$r['id']; ?></div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($r['metodo']); ?></td>
                                    <td>S/ <?php echo number_format((float)$r['monto'], 2); ?></td>
                                    <td style="text-align:right;">
                                        <div class="action-buttons">
                                            <a class="action-btn action-view" title="Gestionar" href="recargas-admin.php" style="text-decoration:none;"><i class="fas fa-arrow-right"></i></a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="muted">No hay recargas pendientes.</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="content-card">
                <div class="card-header">
                    <h3><i class="fas fa-ticket-alt"></i> Tickets</h3>
                    <a href="tickets.php" class="card-link">Ver todos <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="card-body">
                    <?php if (count($tickets_soporte) > 0): ?>
                        <table class="data-table">
                            <thead><tr><th>Asunto</th><th>Usuario</th><th>Prioridad</th><th>Estado</th></tr></thead>
                            <tbody>
                            <?php foreach ($tickets_soporte as $t): ?>
                                <tr>
                                    <td>
                                        <div class="user-info-sm">
                                            <div class="user-name-sm"><?php echo htmlspecialchars($t['asunto'] ?? ''); ?></div>
                                            <div class="user-email-sm">ID: #<?php echo (int)$t['id']; ?></div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($t['usuario_nombre'] ?? ('Usuario #' . (int)$t['usuario_id'])); ?></td>
                                    <td><?php echo htmlspecialchars($t['prioridad'] ?? 'media'); ?></td>
                                    <td><span class="status-badge"><?php echo htmlspecialchars($t['estado'] ?? ''); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="muted"><?php echo $hasTickets ? "No hay tickets para mostrar." : "Tabla de tickets no encontrada (se activará cuando exista)."; ?></div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <div class="quick-actions">
            <a class="quick-action-card" href="productos-admin.php" style="text-decoration:none;">
                <div class="quick-action-icon"><i class="fas fa-plus"></i></div>
                <h4>Agregar / editar productos</h4>
                <p>Gestiona catálogo, stock y estado</p>
            </a>
            <a class="quick-action-card" href="recargas-admin.php" style="text-decoration:none;">
                <div class="quick-action-icon"><i class="fas fa-coins"></i></div>
                <h4>Validar recargas</h4>
                <p>Aprobar / rechazar recargas pendientes</p>
            </a>
            <a class="quick-action-card" href="ventas.php" style="text-decoration:none;">
                <div class="quick-action-icon"><i class="fas fa-chart-bar"></i></div>
                <h4>Ver ventas</h4>
                <p>Listado de compras completadas</p>
            </a>
            <a class="quick-action-card" href="configuracion.php" style="text-decoration:none;">
                <div class="quick-action-icon"><i class="fas fa-cog"></i></div>
                <h4>Configuración</h4>
                <p>Métodos de pago, comisión e instrucciones</p>
            </a>
        </div>
    </div>
</main>

<script>
const sidebar = document.getElementById('adminSidebar');
const sidebarToggle = document.getElementById('sidebarToggle');

sidebarToggle.addEventListener('click', () => {
  sidebar.style.transform = (sidebar.style.transform === 'translateX(0px)') ? 'translateX(-100%)' : 'translateX(0)';
});

document.addEventListener('click', (e) => {
  if (window.innerWidth <= 992 &&   
      !sidebar.contains(e.target) &&
      !sidebarToggle.contains(e.target) &&
      sidebar.style.transform === 'translateX(0px)') {
    sidebar.style.transform = 'translateX(-100%)';
  }
});
</script>

</body>
</html>

