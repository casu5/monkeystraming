<?php
// admin/ventas.php - Listar compras/ventas (completadas) con joins a usuario y producto
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/sidebar.php';
require_once __DIR__ . '/../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * OK Protección ADMIN (compat)
 * - Si existe sesión admin_id (admin/login.php), lo usa
 * - Si no, cae a requireAdmin() / role=admin por isLoggedIn()
 */
$admin = [
    'id'     => $_SESSION['admin_id'] ?? null,
    'email'  => $_SESSION['admin_email'] ?? '',
    'nombre' => $_SESSION['admin_name'] ?? 'Administrador',
];

if (empty($_SESSION['admin_id'])) {
    // Fallback antiguo: user-login con role admin
    if (function_exists('requireAdmin')) {
        requireAdmin();
        if (function_exists('getCurrentUser')) {
            $u = getCurrentUser();
            if (!empty($u)) {
                $admin['id']     = $u['id'] ?? $admin['id'];
                $admin['email']  = $u['email'] ?? $admin['email'];
                $admin['nombre'] = $u['nombre'] ?? ($u['full_name'] ?? $admin['nombre']);
            }
        }
    } else {
        if (!function_exists('isLoggedIn') || !function_exists('getCurrentUser')) {
            http_response_code(500);
            die('Faltan helpers de sesión (isLoggedIn/getCurrentUser).');
        }
        if (!isLoggedIn()) {
            // OJO: si tu login admin es admin/login.php, usa esta:
            header('Location: login.php');
            exit;
        }

        $u = getCurrentUser();
        $role = strtolower((string)($u['role'] ?? $u['rol'] ?? $u['user_role'] ?? ''));
        if ($role !== 'admin') {
            http_response_code(403);
            die('Acceso denegado: solo administradores.');
        }

        $admin['id']     = $u['id'] ?? $admin['id'];
        $admin['email']  = $u['email'] ?? $admin['email'];
        $admin['nombre'] = $u['nombre'] ?? ($u['full_name'] ?? $admin['nombre']);
    }
}

/** ===== Utilidades compatibilidad BD ===== */
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
function pickCol(mysqli $cx, string $table, array $candidates, ?string $default = null): ?string {
    foreach ($candidates as $c) if (colExists($cx, $table, $c)) return $c;
    return $default;
}
function pickDateColumn(mysqli $cx, string $table, array $candidates): ?string {
    foreach ($candidates as $col) {
        if (colExists($cx, $table, $col)) return $col;
    }
    return null;
}
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function fmoney($n): string { return number_format((float)$n, 2); }

/** ===== Para activar el menú automáticamente ===== */
$currentPage = basename($_SERVER['PHP_SELF']);
function navActive(string $file, string $currentPage): string {
    return $currentPage === $file ? 'active' : '';
}

/** ===== Mini estadísticas para badges del menú (opcional, pero queda pro) ===== */
$estadisticas = [
    'usuarios_nuevos_hoy' => 0,
    'productos_agotados'  => 0,
    'recargas_pendientes' => 0,
    'tickets_soporte'     => 0,
];

if (tableExists($conexion, 'usuarios')) {
    $userDateCol = pickDateColumn($conexion, 'usuarios', ['created_at', 'fecha_registro', 'fecha_creacion', 'fecha']);
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

/** ===== Detectar tablas ===== */
$TABLE_SALES = tableExists($conexion, 'compras') ? 'compras' : null;
$TABLE_USERS = tableExists($conexion, 'usuarios') ? 'usuarios' : (tableExists($conexion, 'users') ? 'users' : null);
$TABLE_PRODS = tableExists($conexion, 'productos') ? 'productos' : (tableExists($conexion, 'products') ? 'products' : null);

if (!$TABLE_SALES) {
    http_response_code(500);
    die("No existe la tabla `compras` (ventas) en la BD.");
}

/** ===== Detectar columnas compras ===== */
$C_ID     = pickCol($conexion, $TABLE_SALES, ['id'], 'id');
$C_UID    = pickCol($conexion, $TABLE_SALES, ['usuario_id', 'user_id'], 'usuario_id');
$C_PID    = pickCol($conexion, $TABLE_SALES, ['producto_id', 'product_id'], 'producto_id');
$C_AMT    = pickCol($conexion, $TABLE_SALES, ['monto', 'amount', 'total', 'total_amount', 'amount_cents'], 'monto');
$C_STATE  = pickCol($conexion, $TABLE_SALES, ['estado', 'status'], null);
$C_DATE   = pickCol($conexion, $TABLE_SALES, ['fecha', 'fecha_compra', 'created_at', 'fecha_solicitud', 'fecha_registro'], null);
$C_SELLER = pickCol($conexion, $TABLE_SALES, ['vendedor_id', 'seller_id'], null);
$C_ADMIN_FEE = pickCol($conexion, $TABLE_SALES, ['comision_admin', 'admin_fee'], null);
$C_SELLER_AMOUNT = pickCol($conexion, $TABLE_SALES, ['monto_vendedor', 'seller_amount'], null);

// OK NUEVA: Detectar columna de fecha de vencimiento
$C_VENCIMIENTO = pickCol($conexion, $TABLE_SALES, ['fecha_vencimiento', 'vencimiento_at', 'expires_at', 'vence_at'], null);

$isCents = (stripos($C_AMT, 'cents') !== false);

/** ===== Columnas usuario ===== */
$U_ID    = $TABLE_USERS ? pickCol($conexion, $TABLE_USERS, ['id'], 'id') : null;
$U_NAME  = $TABLE_USERS ? pickCol($conexion, $TABLE_USERS, ['nombre', 'full_name', 'name'], null) : null;
$U_EMAIL = $TABLE_USERS ? pickCol($conexion, $TABLE_USERS, ['email'], null) : null;

/** ===== Columnas producto ===== */
$P_ID   = $TABLE_PRODS ? pickCol($conexion, $TABLE_PRODS, ['id'], 'id') : null;
$P_NAME = $TABLE_PRODS ? pickCol($conexion, $TABLE_PRODS, ['nombre', 'name'], null) : null;

/** ===== Filtros ===== */
$q       = trim((string)($_GET['q'] ?? ''));
$status  = trim((string)($_GET['status'] ?? 'completada')); // default
$from    = trim((string)($_GET['from'] ?? '')); // YYYY-MM-DD
$to      = trim((string)($_GET['to'] ?? ''));

$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = 25;
$off   = ($page - 1) * $limit;

$whereParts = ["1=1"];
$params = [];
$types  = "";

if ($C_STATE) {
    if ($status !== '' && strtolower($status) !== 'todos') {
        $whereParts[] = "c.`$C_STATE` = ?";
        $params[] = $status;
        $types   .= "s";
    }
}

if ($C_DATE) {
    if ($from !== '') {
        $whereParts[] = "DATE(c.`$C_DATE`) >= ?";
        $params[] = $from;
        $types   .= "s";
    }
    if ($to !== '') {
        $whereParts[] = "DATE(c.`$C_DATE`) <= ?";
        $params[] = $to;
        $types   .= "s";
    }
}

if ($q !== '') {
    $sub = [];
    $sub[] = "c.`$C_ID` = ?";
    $params[] = (int)$q;
    $types   .= "i";

    if ($TABLE_USERS && $U_NAME) {
        $sub[] = "u.`$U_NAME` LIKE ?";
        $params[] = "%$q%";
        $types   .= "s";
    }
    if ($TABLE_USERS && $U_EMAIL) {
        $sub[] = "u.`$U_EMAIL` LIKE ?";
        $params[] = "%$q%";
        $types   .= "s";
    }
    if ($TABLE_PRODS && $P_NAME) {
        $sub[] = "p.`$P_NAME` LIKE ?";
        $params[] = "%$q%";
        $types   .= "s";
    }

    $whereParts[] = "(" . implode(" OR ", $sub) . ")";
}

$where = implode(" AND ", $whereParts);

/** ===== SELECT base ===== */
$selectCols = [
    "c.`$C_ID` AS venta_id",
    "c.`$C_AMT` AS monto",
];
if ($C_STATE) $selectCols[] = "c.`$C_STATE` AS estado";
if ($C_DATE)  $selectCols[] = "c.`$C_DATE` AS fecha";
// OK AGREGAR COLUMNA DE VENCIMIENTO SI EXISTE
if ($C_VENCIMIENTO) $selectCols[] = "c.`$C_VENCIMIENTO` AS fecha_vencimiento";
if ($TABLE_USERS && $U_ID)   $selectCols[] = "c.`$C_UID` AS usuario_id";
if ($TABLE_PRODS && $P_ID)   $selectCols[] = "c.`$C_PID` AS producto_id";

if ($TABLE_USERS && $U_NAME)  $selectCols[] = "u.`$U_NAME` AS usuario_nombre";
if ($TABLE_USERS && $U_EMAIL) $selectCols[] = "u.`$U_EMAIL` AS usuario_email";
if ($TABLE_PRODS && $P_NAME)  $selectCols[] = "p.`$P_NAME` AS producto_nombre";
if ($C_SELLER) $selectCols[] = "c.`$C_SELLER` AS vendedor_id";
if ($C_ADMIN_FEE) $selectCols[] = "c.`$C_ADMIN_FEE` AS comision_admin";
if ($C_SELLER_AMOUNT) $selectCols[] = "c.`$C_SELLER_AMOUNT` AS monto_vendedor";
if ($C_SELLER && $TABLE_USERS && $U_NAME) $selectCols[] = "sv.`$U_NAME` AS vendedor_nombre";
if ($C_SELLER && $TABLE_USERS && $U_EMAIL) $selectCols[] = "sv.`$U_EMAIL` AS vendedor_email";

$joins = [];
if ($TABLE_USERS && $U_ID && $C_UID) {
    $joins[] = "LEFT JOIN `$TABLE_USERS` u ON u.`$U_ID` = c.`$C_UID`";
}
if ($TABLE_USERS && $U_ID && $C_SELLER) {
    $joins[] = "LEFT JOIN `$TABLE_USERS` sv ON sv.`$U_ID` = c.`$C_SELLER`";
}
if ($TABLE_PRODS && $P_ID && $C_PID) {
    $joins[] = "LEFT JOIN `$TABLE_PRODS` p ON p.`$P_ID` = c.`$C_PID`";
}
$joinSql = implode("\n", $joins);

/** ===== Conteo ===== */
$sqlCount = "SELECT COUNT(*) c FROM `$TABLE_SALES` c
$joinSql
WHERE $where";
$stC = $conexion->prepare($sqlCount);
if ($types !== '') $stC->bind_param($types, ...$params);
$stC->execute();
$total = (int)($stC->get_result()->fetch_assoc()['c'] ?? 0);
$totalPages = max(1, (int)ceil($total / $limit));

/** ===== Listado ===== */
$orderBy = $C_DATE ? "c.`$C_DATE` DESC" : "c.`$C_ID` DESC";
$sql = "SELECT " . implode(", ", $selectCols) . "
FROM `$TABLE_SALES` c
$joinSql
WHERE $where
ORDER BY $orderBy
LIMIT $limit OFFSET $off";

$st = $conexion->prepare($sql);
if ($types !== '') $st->bind_param($types, ...$params);
$st->execute();
$rs = $st->get_result();

/** ===== Totales rápidos (según filtro) ===== */
$sumSql = "SELECT COALESCE(SUM(c.`$C_AMT`),0) s FROM `$TABLE_SALES` c
$joinSql
WHERE $where";
$stS = $conexion->prepare($sumSql);
if ($types !== '') $stS->bind_param($types, ...$params);
$stS->execute();
$sum = (float)($stS->get_result()->fetch_assoc()['s'] ?? 0);
$sumShow = $isCents ? ($sum / 100) : $sum;

$sellerSumShow = null;
if ($C_SELLER_AMOUNT) {
    $sellerSumSql = "SELECT COALESCE(SUM(c.`$C_SELLER_AMOUNT`),0) s FROM `$TABLE_SALES` c
$joinSql
WHERE $where";
    $stSellerSum = $conexion->prepare($sellerSumSql);
    if ($types !== '') $stSellerSum->bind_param($types, ...$params);
    $stSellerSum->execute();
    $sellerSumShow = (float)($stSellerSum->get_result()->fetch_assoc()['s'] ?? 0);
}

$adminFeeSumShow = null;
if ($C_ADMIN_FEE) {
    $adminFeeSql = "SELECT COALESCE(SUM(c.`$C_ADMIN_FEE`),0) s FROM `$TABLE_SALES` c
$joinSql
WHERE $where";
    $stAdminFee = $conexion->prepare($adminFeeSql);
    if ($types !== '') $stAdminFee->bind_param($types, ...$params);
    $stAdminFee->execute();
    $adminFeeSumShow = (float)($stAdminFee->get_result()->fetch_assoc()['s'] ?? 0);
}

$tableColspan = 6
    + ($C_VENCIMIENTO ? 1 : 0)
    + ($C_SELLER ? 1 : 0)
    + ($C_SELLER_AMOUNT ? 1 : 0)
    + ($C_ADMIN_FEE ? 1 : 0);

$page_title = "Ventas - Admin - Monkeystraming";
$adminName  = $admin['nombre'] ?? 'Administrador';
$adminEmail = $admin['email'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($page_title); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/panel-shell.css?v=admin-polish-4">
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

        .sidebar-toggle{
            display:none;background:none;border:none;color:#fff;font-size:1.5rem;cursor:pointer;position:fixed;top:20px;left:20px;z-index:1001
        }
        @media (max-width: 992px){
            .admin-sidebar{transform:translateX(-100%)}
            .admin-main{margin-left:0}
            .sidebar-toggle{display:block}
            .search-bar{display:none;}
        }

        /* ===== Estilos específicos Ventas ===== */
        .btn{
            border:none;cursor:pointer;border-radius:12px;padding:10px 14px;font-weight:800;
            background:linear-gradient(135deg,#12aaff,#0de0c9);color:#0d0f14;text-decoration:none;display:inline-flex;gap:8px;align-items:center
        }
        .btn.secondary{background:rgba(255,255,255,0.06);color:#fff;border:1px solid rgba(255,255,255,0.10)}
        .card{
            background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);
            border-radius:18px;padding:18px;backdrop-filter:blur(10px);
        }
        .filters{display:grid;grid-template-columns: 1.6fr 0.9fr 0.9fr 0.9fr auto; gap:10px; align-items:end; margin-bottom:14px}
        @media (max-width: 980px){ .filters{grid-template-columns:1fr} }
        label{display:block;color:#ccc;font-size:0.9rem;margin:10px 0 6px}
        input,select{
            width:100%;padding:10px 12px;border-radius:12px;outline:none;color:#fff;
            background:rgba(0,0,0,0.35);border:1px solid rgba(255,255,255,0.12)
        }
        table{width:100%;border-collapse:collapse}
        th,td{padding:12px 10px;text-align:left;border-bottom:1px solid rgba(255,255,255,0.06);vertical-align:middle}
        th{color:#aaa;font-size:0.85rem;text-transform:uppercase;letter-spacing:0.5px}
        .badge{padding:5px 10px;border-radius:999px;font-size:0.8rem;font-weight:800;display:inline-block}
        .b-ok{background:rgba(52,199,89,0.18);color:#34c759}
        .b-pend{background:rgba(255,204,0,0.18);color:#ffcc00}
        .b-bad{background:rgba(255,59,48,0.18);color:#ff3b30}
        .muted{color:#777}
        .vencido{color:#ff3b30;font-weight:bold}
        .proximo{color:#ffcc00;font-weight:bold}
        .metaRow{display:flex;gap:14px;flex-wrap:wrap;align-items:center;justify-content:space-between;margin-bottom:10px}
        .metaBox{padding:10px 12px;border-radius:14px;border:1px solid rgba(255,255,255,0.10);background:rgba(255,255,255,0.05)}
        .metaBox .k{color:#aaa;font-size:0.85rem}
        .metaBox .v{color:#fff;font-weight:900;margin-top:3px}
        .pager{display:flex;gap:10px;align-items:center;justify-content:flex-end;margin-top:12px;flex-wrap:wrap}
        .pager a{padding:8px 12px;border-radius:12px;border:1px solid rgba(255,255,255,0.10);background:rgba(255,255,255,0.06);color:#fff;text-decoration:none}
        .pager .current{color:#12aaff;font-weight:900}
    </style>
  <link rel="stylesheet" href="../assets/css/mobile-urgent.css?v=20260610">
</head>
<body>

<?php renderAdminSidebar($conexion, $currentPage ?? basename($_SERVER['PHP_SELF'])); ?>

<main class="admin-main">
    <header class="admin-header">
        <div class="header-title">
            <h1>Ventas</h1>
            <p>Bienvenido, <?php echo h($adminName); ?><?php echo $adminEmail ? " - " . h($adminEmail) : ""; ?></p>
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
        <div class="card">
            <form method="GET" action="">
                <div class="filters">
                    <div>
                        <label>Buscar</label>
                        <input type="text" name="q" placeholder="ID venta, usuario o producto..." value="<?php echo h($q); ?>">
                    </div>

                    <div>
                        <label>Estado</label>
                        <select name="status">
                            <?php
                            $opts = ['completada','pendiente','cancelada','rechazada','todos'];
                            $current = $status === '' ? 'completada' : $status;
                            foreach ($opts as $op):
                            ?>
                                <option value="<?php echo h($op); ?>" <?php echo ($current === $op) ? 'selected' : ''; ?>>
                                    <?php echo h($op); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!$C_STATE): ?>
                            <div class="muted" style="margin-top:6px;font-size:0.85rem;">(Tu tabla no tiene columna estado/status, este filtro se ignora)</div>
                        <?php endif; ?>
                    </div>

                    <div>
                        <label>Desde</label>
                        <input type="date" name="from" value="<?php echo h($from); ?>" <?php echo $C_DATE ? '' : 'disabled'; ?>>
                        <?php if (!$C_DATE): ?>
                            <div class="muted" style="margin-top:6px;font-size:0.85rem;">(No hay columna de fecha)</div>
                        <?php endif; ?>
                    </div>

                    <div>
                        <label>Hasta</label>
                        <input type="date" name="to" value="<?php echo h($to); ?>" <?php echo $C_DATE ? '' : 'disabled'; ?>>
                    </div>

                    <div style="padding-top:28px;">
                        <button class="btn" type="submit"><i class="fas fa-filter"></i> Filtrar</button>
                    </div>
                </div>
            </form>

            <div class="metaRow">
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <div class="metaBox">
                        <div class="k">Total registros</div>
                        <div class="v"><?php echo number_format($total); ?></div>
                    </div>
                    <div class="metaBox">
                        <div class="k">Suma (según filtro)</div>
                        <div class="v">S/ <?php echo fmoney($sumShow); ?></div>
                    </div>
                    <?php if ($sellerSumShow !== null): ?>
                    <div class="metaBox">
                        <div class="k">Importe vendedores</div>
                        <div class="v">S/ <?php echo fmoney($sellerSumShow); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($adminFeeSumShow !== null): ?>
                    <div class="metaBox">
                        <div class="k">Comision admin</div>
                        <div class="v">S/ <?php echo fmoney($adminFeeSumShow); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="muted">Página <?php echo $page; ?> / <?php echo $totalPages; ?></div>
            </div>

            <div style="overflow:auto;">
                <table>
                    <thead>
                        <tr>
                            <th style="width:90px;">ID</th>
                            <th>Usuario</th>
                            <?php if ($C_SELLER): ?>
                            <th>Vendedor</th>
                            <?php endif; ?>
                            <th>Producto</th>
                            <th style="width:140px;">Monto</th>
                            <?php if ($C_SELLER_AMOUNT): ?>
                            <th style="width:150px;">Para vendedor</th>
                            <?php endif; ?>
                            <?php if ($C_ADMIN_FEE): ?>
                            <th style="width:150px;">Comision admin</th>
                            <?php endif; ?>
                            <th style="width:140px;">Estado</th>
                            <th style="width:190px;">Fecha compra</th>
                            <?php if ($C_VENCIMIENTO): ?>
                            <th style="width:190px;">Vence el</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($rs && $rs->num_rows > 0): ?>
                        <?php while($v = $rs->fetch_assoc()): ?>
                            <?php
                                $monto = $isCents ? ((int)$v['monto'] / 100) : (float)$v['monto'];
                                $estado = $C_STATE ? (string)($v['estado'] ?? '') : '';
                                $badge = 'b-pend';
                                $eLow = strtolower($estado);
                                if (in_array($eLow, ['completada','completado','approved','aprobada','aprobado'], true)) $badge = 'b-ok';
                                if (in_array($eLow, ['cancelada','cancelado','rechazada','rechazado','failed','error'], true)) $badge = 'b-bad';

                                $uName = $v['usuario_nombre'] ?? ('Usuario #' . (int)($v['usuario_id'] ?? 0));
                                $uMail = $v['usuario_email'] ?? '';
                                $sellerName = $v['vendedor_nombre'] ?? (!empty($v['vendedor_id']) ? ('Vendedor #' . (int)$v['vendedor_id']) : 'Sin vendedor');
                                $sellerMail = $v['vendedor_email'] ?? '';
                                $pName = $v['producto_nombre'] ?? ('Producto #' . (int)($v['producto_id'] ?? 0));

                                $fechaTxt = '-';
                                if ($C_DATE && !empty($v['fecha'])) {
                                    $fechaTxt = date('d/m/Y H:i', strtotime($v['fecha']));
                                }
                                
                                // OK Mostrar fecha de vencimiento con colores según estado
                                $vencimientoTxt = '-';
                                $vencimientoClass = '';
                                if ($C_VENCIMIENTO && !empty($v['fecha_vencimiento'])) {
                                    $vencimientoTxt = date('d/m/Y H:i', strtotime($v['fecha_vencimiento']));
                                    $hoy = time();
                                    $vencimiento = strtotime($v['fecha_vencimiento']);
                                    
                                    // Si ya venció
                                    if ($vencimiento < $hoy) {
                                        $vencimientoClass = 'vencido';
                                        $vencimientoTxt .= ' !';
                                    } 
                                    // Si vence en menos de 3 días
                                    elseif (($vencimiento - $hoy) < (3 * 86400)) {
                                        $vencimientoClass = 'proximo';
                                        $vencimientoTxt .= ' ';
                                    }
                                }
                            ?>
                            <tr>
                                <td>#<?php echo (int)$v['venta_id']; ?></td>
                                <td>
                                    <div style="font-weight:900;color:#fff;"><?php echo h($uName); ?></div>
                                    <?php if ($uMail): ?><div class="muted" style="font-size:0.85rem;"><?php echo h($uMail); ?></div><?php endif; ?>
                                </td>
                                <?php if ($C_SELLER): ?>
                                <td>
                                    <div style="font-weight:900;color:#fff;"><?php echo h($sellerName); ?></div>
                                    <?php if ($sellerMail): ?><div class="muted" style="font-size:0.85rem;"><?php echo h($sellerMail); ?></div><?php endif; ?>
                                </td>
                                <?php endif; ?>
                                <td><?php echo h($pName); ?></td>
                                <td>S/ <?php echo fmoney($monto); ?></td>
                                <?php if ($C_SELLER_AMOUNT): ?>
                                <td>S/ <?php echo fmoney($v['monto_vendedor'] ?? 0); ?></td>
                                <?php endif; ?>
                                <?php if ($C_ADMIN_FEE): ?>
                                <td>S/ <?php echo fmoney($v['comision_admin'] ?? 0); ?></td>
                                <?php endif; ?>
                                <td>
                                    <?php if ($C_STATE): ?>
                                        <span class="badge <?php echo $badge; ?>"><?php echo h($estado); ?></span>
                                    <?php else: ?>
                                        <span class="muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo h($fechaTxt); ?></td>
                                <?php if ($C_VENCIMIENTO): ?>
                                <td class="<?php echo $vencimientoClass; ?>">
                                    <?php echo h($vencimientoTxt); ?>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?php echo (int)$tableColspan; ?>" class="muted" style="padding:18px;">
                                No hay ventas con esos filtros.
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="pager">
                <?php
                $queryBase = http_build_query([
                    'q' => $q,
                    'status' => $status,
                    'from' => $from,
                    'to' => $to,
                ]);
                $prev = max(1, $page - 1);
                $next = min($totalPages, $page + 1);
                ?>
                <a href="ventas.php?<?php echo $queryBase; ?>&page=<?php echo $prev; ?>"><i class="fas fa-chevron-left"></i></a>
                <span class="current">Página <?php echo $page; ?> / <?php echo $totalPages; ?></span>
                <a href="ventas.php?<?php echo $queryBase; ?>&page=<?php echo $next; ?>"><i class="fas fa-chevron-right"></i></a>
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
</script>

</body>
</html>


