<?php
// admin/stock.php — Gestión de Stock (cuentas/perfiles) con layout del panel
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** ===== Helpers comunes ===== */
function tableExists(mysqli $cx, string $table): bool {
    $t = $cx->real_escape_string($table);
    $rs = $cx->query("SHOW TABLES LIKE '$t'");
    return ($rs && $rs->num_rows > 0);
}
function colExists(mysqli $cx, string $table, string $col): bool {
    $t = $cx->real_escape_string($table);
    $c = $cx->real_escape_string($col);
    $rs = $cx->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
    return ($rs && $rs->num_rows > 0);
}
function pickDateColumn(mysqli $cx, string $table, array $candidates): ?string {
    foreach ($candidates as $col) {
        if (colExists($cx, $table, $col)) return $col;
    }
    return null;
}
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$currentPage = basename($_SERVER['PHP_SELF']);
function navActive(string $file, string $currentPage): string {
    return $currentPage === $file ? 'active' : '';
}

/** ===== Protección admin (hard) ===== */
function requireAdminHard(): void {
    if (function_exists('requireAdmin')) {
        requireAdmin();
        return;
    }
    if (!function_exists('isLoggedIn') || !function_exists('getCurrentUser')) {
        http_response_code(500);
        die('Faltan helpers de sesión (isLoggedIn/getCurrentUser).');
    }
    if (!isLoggedIn()) {
        header('Location: ../login.php');
        exit;
    }
    $u = getCurrentUser();
    $role = strtolower((string)($u['role'] ?? $u['rol'] ?? $u['user_role'] ?? ''));
    if ($role !== 'admin') {
        http_response_code(403);
        die('Acceso denegado: solo administradores.');
    }
}
requireAdminHard();

/** ===== Admin data para header ===== */
$admin = [
    'id'     => $_SESSION['admin_id'] ?? null,
    'email'  => $_SESSION['admin_email'] ?? '',
    'nombre' => $_SESSION['admin_name'] ?? 'Administrador',
];

if (empty($_SESSION['admin_id']) && function_exists('getCurrentUser')) {
    $u = getCurrentUser();
    if ($u) {
        $admin['id']     = $u['id'] ?? $admin['id'];
        $admin['email']  = $u['email'] ?? $admin['email'];
        $admin['nombre'] = $u['nombre'] ?? ($u['full_name'] ?? $admin['nombre']);
    }
}

$adminName  = $admin['nombre'] ?? 'Administrador';
$adminEmail = $admin['email'] ?? '';

/** ===== Estadísticas para badges del menú ===== */
$estadisticas = [
    'usuarios_nuevos_hoy' => 0,
    'productos_agotados'  => 0,
    'recargas_pendientes' => 0,
    'tickets_soporte'     => 0,
];

if (tableExists($conexion, 'usuarios')) {
    $userDateCol = pickDateColumn($conexion, 'usuarios', ['created_at','fecha_registro','fecha_creacion','fecha']);
    if ($userDateCol) {
        $rs = $conexion->query("SELECT COUNT(*) c FROM usuarios WHERE DATE($userDateCol)=CURDATE()");
        if ($rs) $estadisticas['usuarios_nuevos_hoy'] = (int)($rs->fetch_assoc()['c'] ?? 0);
    }
}
if (tableExists($conexion, 'productos') && colExists($conexion, 'productos', 'stock')) {
    $whereActivo = colExists($conexion, 'productos', 'activo') ? " AND activo=1" : "";
    $rs = $conexion->query("SELECT COUNT(*) c FROM productos WHERE stock<=0 $whereActivo");
    if ($rs) $estadisticas['productos_agotados'] = (int)($rs->fetch_assoc()['c'] ?? 0);
}
if (tableExists($conexion, 'recargas') && colExists($conexion, 'recargas', 'estado')) {
    $rs = $conexion->query("SELECT COUNT(*) c FROM recargas WHERE estado='pendiente'");
    if ($rs) $estadisticas['recargas_pendientes'] = (int)($rs->fetch_assoc()['c'] ?? 0);
}
if (tableExists($conexion, 'tickets') && colExists($conexion, 'tickets', 'estado')) {
    $rs = $conexion->query("SELECT COUNT(*) c FROM tickets WHERE estado IN ('abierto','en_proceso','en proceso','pendiente')");
    if ($rs) $estadisticas['tickets_soporte'] = (int)($rs->fetch_assoc()['c'] ?? 0);
}

/** ===== Lógica Stock ===== */
$success = '';
$error   = '';

$cuentasTieneModoVenta = tableExists($conexion, 'cuentas') && colExists($conexion, 'cuentas', 'modo_venta');

function obtenerTipoVentaProducto(mysqli $cx, int $productoId): ?string {
    $stmt = $cx->prepare("SELECT tipo_venta FROM productos WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $productoId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row['tipo_venta'] ?? null;
}

function recalcularStockProducto(mysqli $cx, int $productoId, string $modoVenta): void {
    // Actualiza productos.stock según stock real
    if (!tableExists($cx, 'productos') || !colExists($cx, 'productos', 'stock')) return;

    $stock = 0;
    if ($modoVenta === 'CUENTA_COMPLETA') {
        if (tableExists($cx, 'cuentas')) {
            $stmt = $cx->prepare("SELECT COUNT(*) c FROM cuentas WHERE producto_id=? AND estado='DISPONIBLE'");
            $stmt->bind_param("i", $productoId);
            $stmt->execute();
            $stock = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
            $stmt->close();
        }
    } else { // PERFIL
        if (tableExists($cx, 'cuentas') && tableExists($cx, 'cuenta_perfiles')) {
            $stmt = $cx->prepare("
                SELECT COUNT(*) c
                FROM cuenta_perfiles cp
                INNER JOIN cuentas cu ON cu.id = cp.cuenta_id
                WHERE cu.producto_id=? AND cu.estado='DISPONIBLE' AND cp.estado='DISPONIBLE'
            ");
            $stmt->bind_param("i", $productoId);
            $stmt->execute();
            $stock = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
            $stmt->close();
        }
    }

    $upd = $cx->prepare("UPDATE productos SET stock=? WHERE id=?");
    $upd->bind_param("ii", $stock, $productoId);
    $upd->execute();
    $upd->close();
}

/** Productos disponibles */
$productos = [];
if (!tableExists($conexion, 'productos')) {
    $error = "No existe la tabla productos.";
} else {
    $r = $conexion->query("SELECT id, nombre, tipo_venta, duracion_dias, activo FROM productos WHERE activo=1 ORDER BY id DESC");
    while ($r && ($row = $r->fetch_assoc())) $productos[] = $row;
}

/** Agregar cuenta / cuenta completa */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_account') {
    $productoId  = (int)($_POST['producto_id'] ?? 0);
    $loginUser   = trim((string)($_POST['login_user'] ?? ''));
    $loginPass   = trim((string)($_POST['login_pass'] ?? ''));
    $pin         = trim((string)($_POST['pin'] ?? ''));
    $maxPerfiles = (int)($_POST['max_perfiles'] ?? 1);
    if ($maxPerfiles <= 0) $maxPerfiles = 1;

    $modoVentaPost = strtoupper(trim((string)($_POST['modo_venta'] ?? 'PERFIL')));
    $modoVentaPost = ($modoVentaPost === 'CUENTA_COMPLETA') ? 'CUENTA_COMPLETA' : 'PERFIL';

    if ($productoId <= 0 || $loginUser === '' || $loginPass === '') {
        $error = "Completa producto, usuario/correo y contraseña.";
    } else {

        // Tipo_venta oficial del producto (si existe)
        $tipoVentaProducto = obtenerTipoVentaProducto($conexion, $productoId);
        if (!$tipoVentaProducto) {
            $error = "Producto inválido o no encontrado.";
        } else {
            // Para evitar inconsistencias: por defecto lo alineamos al producto
            // Si quieres que el admin pueda forzar distinto, cambia esta línea a: $modoVenta = $modoVentaPost;
            $modoVenta = strtoupper($tipoVentaProducto) === 'CUENTA_COMPLETA' ? 'CUENTA_COMPLETA' : 'PERFIL';

            try {
                $conexion->begin_transaction();

                // Si es cuenta completa, forzamos max_perfiles = 1
                if ($modoVenta === 'CUENTA_COMPLETA') {
                    $maxPerfiles = 1;
                }

                // Insert cuenta
                $pinDb = ($pin === '') ? null : $pin;

                if ($cuentasTieneModoVenta) {
                    $stmt = $conexion->prepare("
                        INSERT INTO cuentas (producto_id, modo_venta, login_user, login_pass, pin, max_perfiles, estado)
                        VALUES (?, ?, ?, ?, ?, ?, 'DISPONIBLE')
                    ");
                    $stmt->bind_param("issssi", $productoId, $modoVenta, $loginUser, $loginPass, $pinDb, $maxPerfiles);
                } else {
                    $stmt = $conexion->prepare("
                        INSERT INTO cuentas (producto_id, login_user, login_pass, pin, max_perfiles, estado)
                        VALUES (?, ?, ?, ?, ?, 'DISPONIBLE')
                    ");
                    $stmt->bind_param("isssi", $productoId, $loginUser, $loginPass, $pinDb, $maxPerfiles);
                }

                $stmt->execute();
                $cuentaId = (int)$stmt->insert_id;
                $stmt->close();

                // Crear perfiles SOLO si es PERFIL
                if ($modoVenta === 'PERFIL') {
                    for ($i = 1; $i <= $maxPerfiles; $i++) {
                        $perfilNombre = "Perfil $i";
                        $stmt = $conexion->prepare("
                            INSERT INTO cuenta_perfiles (cuenta_id, perfil_nombre, estado)
                            VALUES (?, ?, 'DISPONIBLE')
                        ");
                        $stmt->bind_param("is", $cuentaId, $perfilNombre);
                        $stmt->execute();
                        $stmt->close();
                    }
                }

                // Recalcular stock del producto y actualizar productos.stock
                recalcularStockProducto($conexion, $productoId, $modoVenta);

                $conexion->commit();

                if ($modoVenta === 'CUENTA_COMPLETA') {
                    $success = "Stock agregado ✅ (Cuenta completa #$cuentaId).";
                } else {
                    $success = "Stock agregado ✅ (Cuenta #$cuentaId con $maxPerfiles perfiles).";
                }

            } catch (Throwable $e) {
                try { $conexion->rollback(); } catch (Throwable $t) {}
                $error = "Error: " . $e->getMessage();
            }
        }
    }
}

/** Listado de cuentas */
$cuentas = [];
if (tableExists($conexion, 'cuentas') && tableExists($conexion, 'cuenta_perfiles') && tableExists($conexion, 'productos')) {

    $selectModo = $cuentasTieneModoVenta ? "cu.modo_venta" : "p.tipo_venta AS modo_venta";

    $sql = "
        SELECT
          cu.id,
          cu.producto_id,
          p.nombre AS producto_nombre,
          p.tipo_venta AS producto_tipo_venta,
          $selectModo,
          cu.login_user,
          cu.estado,
          cu.max_perfiles,
          (SELECT COUNT(*) FROM cuenta_perfiles cp WHERE cp.cuenta_id=cu.id AND cp.estado='DISPONIBLE') AS disponibles,
          (SELECT COUNT(*) FROM cuenta_perfiles cp WHERE cp.cuenta_id=cu.id AND cp.estado='VENDIDO') AS vendidos
        FROM cuentas cu
        INNER JOIN productos p ON p.id = cu.producto_id
        ORDER BY cu.id DESC
        LIMIT 100
    ";
    $res = $conexion->query($sql);
    while ($res && ($row = $res->fetch_assoc())) $cuentas[] = $row;

} else {
    if (!$error) $error = "Faltan tablas: cuentas / cuenta_perfiles / productos.";
}

$page_title = "Stock - Admin - Monkeystraming";
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo h($page_title); ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
    *{margin:0;padding:0;box-sizing:border-box;font-family:'Inter',sans-serif}

    :root{
      --sidebar-width:280px;
      --header-height:70px;
      --primary-gradient:linear-gradient(135deg,#12aaff,#0de0c9);
      --danger-gradient:linear-gradient(135deg,#ff4757,#ff3838);
    }

    body{
      background:linear-gradient(135deg,#0d0f14 0%,#11131a 35%,#0b0c11 100%);
      color:#e5e5e5;min-height:100vh;display:flex;overflow-x:hidden;
    }

    /* ===== Sidebar ===== */
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

    /* ===== Main ===== */
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
    .user-menu{display:flex;align-items:center;gap:15px;position:relative}
    .user-avatar{
      width:45px;height:45px;border-radius:50%;background:var(--primary-gradient);
      display:flex;align-items:center;justify-content:center;color:#0d0f14;font-weight:700;font-size:1.2rem;cursor:pointer
    }
    .user-info{display:flex;flex-direction:column}
    .user-name{font-weight:600;color:#fff}
    .user-role{font-size:.8rem;color:#12aaff;font-weight:500}
    .admin-content{padding:30px}

    .sidebar-toggle{
      display:none;background:none;border:none;color:#fff;font-size:1.5rem;cursor:pointer;position:fixed;top:20px;left:20px;z-index:1001
    }
    @media (max-width: 992px){
      .admin-sidebar{transform:translateX(-100%)}
      .admin-main{margin-left:0}
      .sidebar-toggle{display:block}
      .search-bar{display:none}
    }

    /* ===== Cards / Forms / Table ===== */
    .card{
      background:rgba(255,255,255,0.04);
      border:1px solid rgba(255,255,255,0.08);
      border-radius:16px;
      padding:16px;
      backdrop-filter:blur(10px);
      margin-bottom:16px;
    }
    .card h3{font-size:1.2rem;margin-bottom:10px;color:#fff}
    label{display:block;color:#ccc;font-size:.9rem;margin:10px 0 6px}
    input,select{
      width:100%;padding:10px 12px;border-radius:12px;outline:none;color:#fff;
      background:rgba(0,0,0,0.35);border:1px solid rgba(255,255,255,0.12)
    }
    .btn{
      padding:12px 16px;border-radius:12px;border:none;cursor:pointer;font-weight:900;
      background:var(--primary-gradient);color:#0d0f14;display:inline-flex;gap:8px;align-items:center
    }
    .row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    @media (max-width: 900px){ .row{grid-template-columns:1fr} }

    .alert{padding:12px 14px;border-radius:12px;margin-bottom:12px;font-weight:700;display:flex;align-items:center;gap:10px}
    .ok{background:rgba(52,199,89,.12);border:1px solid rgba(52,199,89,.35);color:#34c759}
    .bad{background:rgba(255,59,48,.12);border:1px solid rgba(255,59,48,.35);color:#ff3b30}

    table{width:100%;border-collapse:collapse}
    th,td{padding:10px 12px;border-bottom:1px solid rgba(255,255,255,.08);text-align:left;vertical-align:middle}
    th{color:#aaa;text-transform:uppercase;letter-spacing:.5px;font-size:.85rem}
    .muted{color:#777}
    small.muted{display:block;margin-top:6px}
    .tag{
      display:inline-flex;align-items:center;gap:6px;
      padding:4px 10px;border-radius:999px;font-size:.8rem;font-weight:800;
      background:rgba(18,170,255,0.12);color:#12aaff;border:1px solid rgba(18,170,255,0.25)
    }
  </style>
</head>
<body>

<button class="sidebar-toggle" id="sidebarToggle">
  <i class="fas fa-bars"></i>
</button>

<aside class="admin-sidebar" id="adminSidebar">
  <div class="admin-logo">
    <div class="logo">Monkeystraming</div>
    <div class="subtitle">Panel de Administración</div>
  </div>

  <nav class="admin-menu">
    <div class="menu-section">
      <h3>Principal</h3>
      <a href="index.php" class="menu-item <?php echo navActive('index.php', $currentPage); ?>">
        <i class="fas fa-tachometer-alt"></i><span>Dashboard</span>
      </a>
      <a href="ventas.php" class="menu-item <?php echo navActive('ventas.php', $currentPage); ?>">
        <i class="fas fa-shopping-cart"></i><span>Ventas</span>
      </a>
      <a href="recargas-admin.php" class="menu-item <?php echo navActive('recargas-admin.php', $currentPage); ?>">
        <i class="fas fa-coins"></i><span>Recargas</span>
        <span class="menu-badge"><?php echo (int)$estadisticas['recargas_pendientes']; ?></span>
      </a>
    </div>

    <div class="menu-section">
      <h3>Gestión</h3>
      <a href="usuarios.php" class="menu-item <?php echo navActive('usuarios.php', $currentPage); ?>">
        <i class="fas fa-users"></i><span>Usuarios</span>
        <span class="menu-badge"><?php echo (int)$estadisticas['usuarios_nuevos_hoy']; ?></span>
      </a>
      <a href="productos-admin.php" class="menu-item <?php echo navActive('productos-admin.php', $currentPage); ?>">
        <i class="fas fa-box-open"></i><span>Productos</span>
        <span class="menu-badge"><?php echo (int)$estadisticas['productos_agotados']; ?></span>
      </a>
      <a href="stock.php" class="menu-item <?php echo navActive('stock.php', $currentPage); ?>">
        <i class="fas fa-warehouse"></i><span>Stock</span>
      </a>
    </div>

    <div class="menu-section">
      <h3>Soporte</h3>
      <a href="tickets.php" class="menu-item <?php echo navActive('tickets.php', $currentPage); ?>">
        <i class="fas fa-ticket-alt"></i><span>Tickets</span>
        <span class="menu-badge"><?php echo (int)$estadisticas['tickets_soporte']; ?></span>
      </a>
    </div>
  </nav>

  <div class="menu-section" style="margin-top:auto;padding-bottom:25px;">
    <a href="../index.php" class="menu-item"><i class="fas fa-globe"></i><span>Ver Sitio Web</span></a>
    <a href="../logout.php" class="menu-item"><i class="fas fa-sign-out-alt"></i><span>Cerrar Sesión</span></a>
  </div>
</aside>

<main class="admin-main">
  <header class="admin-header">
    <div class="header-title">
      <h1>Stock</h1>
      <p>Bienvenido, <?php echo h($adminName); ?><?php echo $adminEmail ? " — " . h($adminEmail) : ""; ?></p>
    </div>

    <div class="header-actions">
      <input type="text" class="search-bar" placeholder="🔍 Buscar en el sistema..." disabled>
      <div class="user-menu">
        <div class="user-avatar"><i class="fas fa-user-cog"></i></div>
        <div class="user-info">
          <div class="user-name"><?php echo h($adminName); ?></div>
          <div class="user-role">ADMIN</div>
        </div>
      </div>
    </div>
  </header>

  <div class="admin-content">

    <?php if ($success): ?>
      <div class="alert ok"><i class="fas fa-check-circle"></i> <?php echo h($success); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="alert bad"><i class="fas fa-exclamation-triangle"></i> <?php echo h($error); ?></div>
    <?php endif; ?>

    <div class="card">
      <h3><i class="fas fa-plus"></i> Agregar stock</h3>

      <form method="post" id="formStock">
        <input type="hidden" name="action" value="add_account">

        <div class="row">
          <div>
            <label>Producto</label>
            <select name="producto_id" id="productoSelect" required>
              <option value="">-- Selecciona --</option>
              <?php foreach ($productos as $p): ?>
                <option
                  value="<?php echo (int)$p['id']; ?>"
                  data-tipo-venta="<?php echo h($p['tipo_venta'] ?? 'PERFIL'); ?>"
                >
                  #<?php echo (int)$p['id']; ?> - <?php echo h($p['nombre']); ?>
                  (<?php echo h($p['tipo_venta'] ?? 'PERFIL'); ?> / <?php echo (int)($p['duracion_dias'] ?? 30); ?> días)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label>Tipo de stock</label>
            <select name="modo_venta" id="modoVenta">
              <option value="PERFIL">PERFIL (vende perfiles)</option>
              <option value="CUENTA_COMPLETA">CUENTA_COMPLETA (vende cuenta)</option>
            </select>
            <small class="muted">
              Nota: está alineado al tipo_venta del producto (recomendado para que el stock se cuente bien).
            </small>
          </div>
        </div>

        <div class="row" style="margin-top:12px">
          <div>
            <label>Correo/Usuario</label>
            <input type="text" name="login_user" placeholder="correo@dominio.com" required>
          </div>
          <div>
            <label>Contraseña</label>
            <input type="text" name="login_pass" placeholder="********" required>
          </div>
        </div>

        <div class="row" style="margin-top:12px">
          <div id="wrapMaxPerfiles">
            <label>Máx perfiles</label>
            <input type="number" name="max_perfiles" id="maxPerfiles" value="4" min="1">
            <small class="muted">Solo aplica cuando el tipo es PERFIL.</small>
          </div>

          <div>
            <label>PIN (opcional)</label>
            <input type="text" name="pin" placeholder="PIN">
          </div>
        </div>

        <div style="margin-top:14px">
          <button class="btn" type="submit">
            <i class="fas fa-save"></i> Guardar stock
          </button>
        </div>
      </form>
    </div>

    <div class="card">
      <h3><i class="fas fa-list"></i> Últimas cuentas</h3>

      <div style="overflow:auto;">
        <table>
          <thead>
            <tr>
              <th style="width:90px;">ID</th>
              <th>Producto</th>
              <th style="width:160px;">Tipo</th>
              <th>Login</th>
              <th style="width:140px;">Estado</th>
              <th style="width:260px;">Perfiles</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($cuentas as $c): ?>
              <?php
                $tipo = strtoupper((string)($c['modo_venta'] ?? $c['producto_tipo_venta'] ?? 'PERFIL'));
                $tipo = ($tipo === 'CUENTA_COMPLETA') ? 'CUENTA_COMPLETA' : 'PERFIL';
              ?>
              <tr>
                <td>#<?php echo (int)$c['id']; ?></td>
                <td><?php echo h($c['producto_nombre']); ?></td>
                <td><span class="tag"><i class="fas fa-tag"></i> <?php echo h($tipo); ?></span></td>
                <td><?php echo h($c['login_user']); ?></td>
                <td><?php echo h($c['estado']); ?></td>
                <td>
                  <?php if ($tipo === 'CUENTA_COMPLETA'): ?>
                    <span class="muted">No aplica (cuenta completa)</span>
                  <?php else: ?>
                    Disp: <?php echo (int)$c['disponibles']; ?> /
                    Vend: <?php echo (int)$c['vendidos']; ?> /
                    Max: <?php echo (int)$c['max_perfiles']; ?>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>

            <?php if (empty($cuentas)): ?>
              <tr><td colspan="6" class="muted" style="padding:14px;">Aún no hay stock.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
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

// Auto-ajuste del tipo según el producto seleccionado
const productoSelect = document.getElementById('productoSelect');
const modoVenta = document.getElementById('modoVenta');
const wrapMax = document.getElementById('wrapMaxPerfiles');
const maxPerfiles = document.getElementById('maxPerfiles');

function refrescarUI() {
  const opt = productoSelect.options[productoSelect.selectedIndex];
  const tipo = (opt && opt.dataset && opt.dataset.tipoVenta) ? opt.dataset.tipoVenta.toUpperCase() : 'PERFIL';

  // Alineamos selector al producto (para que el conteo de stock sea coherente)
  modoVenta.value = (tipo === 'CUENTA_COMPLETA') ? 'CUENTA_COMPLETA' : 'PERFIL';

  if (modoVenta.value === 'CUENTA_COMPLETA') {
    wrapMax.style.display = 'none';
    maxPerfiles.value = 1;
  } else {
    wrapMax.style.display = 'block';
    if (!maxPerfiles.value || parseInt(maxPerfiles.value, 10) <= 0) maxPerfiles.value = 4;
  }
}

productoSelect.addEventListener('change', refrescarUI);
modoVenta.addEventListener('change', () => {
  // Si fuerzas manualmente, igual adaptamos la UI
  if (modoVenta.value === 'CUENTA_COMPLETA') {
    wrapMax.style.display = 'none';
    maxPerfiles.value = 1;
  } else {
    wrapMax.style.display = 'block';
  }
});

refrescarUI();
</script>

</body>
</html>
