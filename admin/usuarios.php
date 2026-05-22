<?php
// admin/usuarios.php — Gestión REAL de usuarios (activar/suspender, ver saldo, cambiar rol)
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/sidebar.php';
require_once __DIR__ . '/../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** ===== Helpers generales ===== */
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

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
function isTruthyActive($v): bool {
    $s = strtolower(trim((string)$v));
    if ($s === '1' || $s === 'true') return true;
    if (is_numeric($v) && (int)$v === 1) return true;
    return in_array($s, ['activo','active','enabled','habilitado','on'], true);
}
function toggleActiveValue($current) {
    // Si es numérico (1/0), togglear numérico
    if (is_numeric($current)) {
        return ((int)$current === 1) ? 0 : 1;
    }
    $s = strtolower(trim((string)$current));
    // Si viene tipo ACTIVO/INACTIVO (o similares)
    if (in_array($s, ['activo','active','enabled','habilitado','on','1','true'], true)) return 'INACTIVO';
    return 'ACTIVO';
}
function adminRoleBadge(string $role): string {
    if ($role === 'admin') return 'ADMIN';
    if ($role === 'vendedor') return 'VENDEDOR';
    return 'CLIENTE';
}

/** ===== Protección admin ===== */
requireAdminHard();

/** ===== Tablas/columnas ===== */
$TABLE_USERS = tableExists($conexion, 'usuarios') ? 'usuarios' : (tableExists($conexion, 'users') ? 'users' : 'usuarios');

$COL_ID      = pickCol($conexion, $TABLE_USERS, ['id'], 'id');
$COL_NAME    = pickCol($conexion, $TABLE_USERS, ['nombre', 'full_name', 'name'], 'nombre');
$COL_EMAIL   = pickCol($conexion, $TABLE_USERS, ['email'], 'email');
$COL_SALDO   = pickCol($conexion, $TABLE_USERS, ['saldo', 'balance'], 'saldo');
$COL_ACTIVE  = pickCol($conexion, $TABLE_USERS, ['estado', 'is_active'], 'estado');
$COL_ROLE    = pickCol($conexion, $TABLE_USERS, ['role', 'rol', 'user_role'], 'rol');

/** ===== CSRF ===== */
if (empty($_SESSION['csrf_admin'])) {
    try {
        $_SESSION['csrf_admin'] = bin2hex(random_bytes(16));
    } catch (Throwable $e) {
        $_SESSION['csrf_admin'] = sha1(uniqid('', true));
    }
}
$csrf = $_SESSION['csrf_admin'];

/** ===== Flash ===== */
$success_msg = $_SESSION['success'] ?? '';
$error_msg   = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

/** ===== Badges del menú ===== */
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

/** ===== Acciones POST ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedCsrf = $_POST['csrf'] ?? '';
    if (!hash_equals($csrf, $postedCsrf)) {
        $_SESSION['error'] = 'CSRF inválido. Recarga la página e intenta de nuevo.';
        header("Location: usuarios.php");
        exit;
    }

    $action = $_POST['action'] ?? '';
    $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;

    if ($userId <= 0) {
        $_SESSION['error'] = 'Usuario inválido.';
        header("Location: usuarios.php");
        exit;
    }

    try {
        if ($action === 'toggle_active') {
            $sql = "SELECT `$COL_ACTIVE` AS activo_actual FROM `$TABLE_USERS` WHERE `$COL_ID` = ? LIMIT 1";
            $st  = $conexion->prepare($sql);
            $st->bind_param("i", $userId);
            $st->execute();
            $row = $st->get_result()->fetch_assoc();
            if (!$row) throw new Exception('No existe el usuario.');

            $nuevo = toggleActiveValue($row['activo_actual']);

            $sqlU = "UPDATE `$TABLE_USERS` SET `$COL_ACTIVE` = ? WHERE `$COL_ID` = ?";
            $stU  = $conexion->prepare($sqlU);

            if (is_numeric($nuevo)) {
                $nuevoInt = (int)$nuevo;
                $stU->bind_param("ii", $nuevoInt, $userId);
                $stU->execute();
                $_SESSION['success'] = ($nuevoInt === 1) ? 'Usuario activado.' : 'Usuario suspendido.';
            } else {
                $nuevoStr = (string)$nuevo;
                $stU->bind_param("si", $nuevoStr, $userId);
                $stU->execute();
                $_SESSION['success'] = (strtoupper($nuevoStr) === 'ACTIVO') ? 'Usuario activado.' : 'Usuario suspendido.';
            }

            header("Location: usuarios.php");
            exit;
        }

        if ($action === 'update_role') {
            $newRole = normalizeUserRole((string)($_POST['role'] ?? ''));
            $allowed = ['cliente', 'vendedor', 'admin'];

            if (!in_array($newRole, $allowed, true)) {
                throw new Exception('Rol no permitido.');
            }

            if ($userId === (int)($_SESSION['user_id'] ?? 0) && $newRole !== 'admin') {
                throw new Exception('No puedes quitarte tu propio rol de administrador.');
            }

            $sqlU = "UPDATE `$TABLE_USERS` SET `$COL_ROLE` = ? WHERE `$COL_ID` = ?";
            $stU  = $conexion->prepare($sqlU);
            $stU->bind_param("si", $newRole, $userId);
            $stU->execute();

            if (
                $newRole === 'vendedor'
                && tableExists($conexion, 'vendedor_perfiles')
                && colExists($conexion, 'vendedor_perfiles', 'vendedor_id')
            ) {
                $tienda = 'Tienda #' . $userId;
                if (colExists($conexion, 'vendedor_perfiles', 'tienda_nombre')) {
                    $sqlProfile = "
                        INSERT INTO vendedor_perfiles (vendedor_id, tienda_nombre)
                        VALUES (?, ?)
                        ON DUPLICATE KEY UPDATE tienda_nombre = tienda_nombre
                    ";
                    $stProfile = $conexion->prepare($sqlProfile);
                    $stProfile->bind_param("is", $userId, $tienda);
                } else {
                    $sqlProfile = "
                        INSERT IGNORE INTO vendedor_perfiles (vendedor_id)
                        VALUES (?)
                    ";
                    $stProfile = $conexion->prepare($sqlProfile);
                    $stProfile->bind_param("i", $userId);
                }
                $stProfile->execute();
            }

            $_SESSION['success'] = 'Rol actualizado.';
            header("Location: usuarios.php");
            exit;
        }

        $_SESSION['error'] = 'Acción no válida.';
        header("Location: usuarios.php");
        exit;

    } catch (Exception $e) {
        $_SESSION['error'] = 'Error: ' . $e->getMessage();
        header("Location: usuarios.php");
        exit;
    }
}

/** ===== Búsqueda + paginación ===== */
$q     = trim((string)($_GET['q'] ?? ''));
$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = 25;
$off   = ($page - 1) * $limit;

$where  = "1=1";
$params = [];
$types  = "";

if ($q !== '') {
    $where .= " AND (`$COL_NAME` LIKE ? OR `$COL_EMAIL` LIKE ? OR `$COL_ID` = ?)";
    $like = "%$q%";
    $params[] = $like;
    $params[] = $like;
    $params[] = (int)$q;
    $types = "ssi";
}

$sqlCount = "SELECT COUNT(*) c FROM `$TABLE_USERS` WHERE $where";
$stC = $conexion->prepare($sqlCount);
if ($types) $stC->bind_param($types, ...$params);
$stC->execute();
$total = (int)($stC->get_result()->fetch_assoc()['c'] ?? 0);
$totalPages = max(1, (int)ceil($total / $limit));

$sql = "
    SELECT
        `$COL_ID`     AS id,
        `$COL_NAME`   AS nombre,
        `$COL_EMAIL`  AS email,
        `$COL_SALDO`  AS saldo,
        `$COL_ROLE`   AS role,
        `$COL_ACTIVE` AS estado
    FROM `$TABLE_USERS`
    WHERE $where
    ORDER BY `$COL_ID` DESC
    LIMIT $limit OFFSET $off
";
$st = $conexion->prepare($sql);
if ($types) $st->bind_param($types, ...$params);
$st->execute();
$rs = $st->get_result();

/** ===== Datos UI ===== */
$page_title  = "Usuarios - Admin - Monkeystraming";
$currentPage = basename($_SERVER['PHP_SELF']);
function navActive(string $file, string $currentPage): string {
    return $currentPage === $file ? 'active' : '';
}

$admin = getCurrentUser();
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
            color:#e5e5e5;min-height:100vh;display:flex;overflow-x:hidden
        }

        /* Sidebar */
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
            font-weight:700;min-width:20px;text-align:center
        }

        /* Main */
        .admin-main{flex:1;margin-left:var(--sidebar-width);min-height:100vh}
        .admin-header{
            height:var(--header-height);background:rgba(255,255,255,0.03);backdrop-filter:blur(15px);
            border-bottom:1px solid rgba(255,255,255,0.06);
            padding:0 30px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:999
        }
        .header-title h1{font-size:1.6rem;font-weight:800;color:#fff}
        .header-title p{color:#aaa;font-size:.9rem;margin-top:3px}
        .header-actions{display:flex;align-items:center;gap:20px}
        .search-bar{
            padding:10px 18px;border-radius:10px;border:1px solid rgba(255,255,255,0.1);background:rgba(255,255,255,0.05);
            color:#fff;outline:none;width:300px;font-size:.9rem;backdrop-filter:blur(5px)
        }
        .user-menu{display:flex;align-items:center;gap:15px}
        .user-avatar{
            width:45px;height:45px;border-radius:50%;background:var(--primary-gradient);
            display:flex;align-items:center;justify-content:center;color:#0d0f14;font-weight:800;font-size:1.2rem
        }
        .user-info{display:flex;flex-direction:column}
        .user-name{font-weight:700;color:#fff}
        .user-role{font-size:.8rem;color:#12aaff;font-weight:700}
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

        /* Content */
        .card{
            background:rgba(255,255,255,.04);
            border:1px solid rgba(255,255,255,.08);
            border-radius:16px;padding:16px;backdrop-filter:blur(10px)
        }
        .alert{padding:12px 14px;border-radius:12px;margin-bottom:12px;font-weight:800;display:flex;align-items:center;gap:10px}
        .ok{background:rgba(52,199,89,.12);border:1px solid rgba(52,199,89,.35);color:#34c759}
        .err{background:rgba(255,59,48,.12);border:1px solid rgba(255,59,48,.35);color:#ff3b30}

        .toolbar{display:flex;gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap;margin-bottom:12px}
        .search{
            display:flex;gap:10px;align-items:center;flex:1;min-width:260px;
            background:rgba(0,0,0,0.35);border:1px solid rgba(255,255,255,0.12);
            border-radius:14px;padding:10px 12px;
        }
        .search input{
            border:none;outline:none;background:transparent;color:#fff;width:100%;
            font-size:0.95rem;
        }
        .meta{color:#aaa;font-size:0.9rem}

        table{width:100%;border-collapse:collapse}
        th,td{padding:12px 10px;text-align:left;border-bottom:1px solid rgba(255,255,255,0.06);vertical-align:middle}
        th{color:#aaa;font-size:0.85rem;text-transform:uppercase;letter-spacing:0.5px;font-weight:900}

        .badge{padding:5px 10px;border-radius:999px;font-size:0.8rem;font-weight:900;display:inline-block}
        .b-on{background:rgba(52,199,89,0.18);color:#34c759}
        .b-off{background:rgba(255,59,48,0.18);color:#ff3b30}

        .actions{display:flex;gap:8px;flex-wrap:wrap}
        .iconbtn{
            width:38px;height:38px;border-radius:12px;border:1px solid rgba(255,255,255,0.10);
            background:rgba(255,255,255,0.06);color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center
        }
        .iconbtn.danger{background:rgba(255,59,48,0.12);border-color:rgba(255,59,48,0.25);color:#ff3b30}
        .iconbtn.ok{background:rgba(52,199,89,0.12);border-color:rgba(52,199,89,0.25);color:#34c759}

        .roleform{display:flex;gap:8px;align-items:center}
        select{
            background:rgba(0,0,0,0.35);border:1px solid rgba(255,255,255,0.12);color:#fff;
            border-radius:12px;padding:8px 10px;outline:none
        }

        .pager{display:flex;gap:10px;align-items:center;justify-content:flex-end;margin-top:12px;flex-wrap:wrap}
        .pager a{
            padding:8px 12px;border-radius:12px;border:1px solid rgba(255,255,255,0.10);
            background:rgba(255,255,255,0.06);color:#fff;text-decoration:none
        }
        .pager .current{color:#12aaff;font-weight:900}

        .muted{color:#777}

        @media (max-width: 720px){
            th:nth-child(3), td:nth-child(3){display:none}
        }
    </style>
</head>
<body>

<?php renderAdminSidebar($conexion, $currentPage ?? basename($_SERVER['PHP_SELF'])); ?>

<main class="admin-main">
    <header class="admin-header">
        <div class="header-title">
            <h1>Usuarios</h1>
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

        <?php if ($success_msg): ?>
            <div class="alert ok"><i class="fas fa-check-circle"></i> <?php echo h($success_msg); ?></div>
        <?php endif; ?>
        <?php if ($error_msg): ?>
            <div class="alert err"><i class="fas fa-exclamation-triangle"></i> <?php echo h($error_msg); ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="toolbar">
                <form class="search" method="GET" action="">
                    <i class="fas fa-search" style="color:#12aaff;"></i>
                    <input type="text" name="q" placeholder="Buscar por nombre, email o ID..." value="<?php echo h($q); ?>">
                </form>
                <div class="meta">
                    Total: <strong><?php echo number_format($total); ?></strong>
                </div>
            </div>

            <div style="overflow:auto;">
                <table>
                    <thead>
                        <tr>
                            <th style="width:80px;">ID</th>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th style="width:140px;">Saldo</th>
                            <th style="width:190px;">Rol</th>
                            <th style="width:130px;">Estado</th>
                            <th style="width:240px;text-align:right;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($rs && $rs->num_rows > 0): ?>
                        <?php while($u = $rs->fetch_assoc()): ?>
                            <?php
                                $isActive = isTruthyActive($u['estado'] ?? 0);
                                $estadoTxt = $isActive ? 'activo' : 'inactivo';

                                $role = normalizeUserRole((string)($u['role'] ?? 'cliente'));
                                $roleBadge = adminRoleBadge($role);

                                $nombre = (string)($u['nombre'] ?? ('Usuario #' . (int)$u['id']));
                                $initial = strtoupper(substr($nombre !== '' ? $nombre : 'U', 0, 1));
                            ?>
                            <tr>
                                <td>#<?php echo (int)$u['id']; ?></td>
                                <td>
                                    <div style="display:flex;gap:10px;align-items:center;">
                                        <div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#12aaff,#0de0c9);display:flex;align-items:center;justify-content:center;color:#0d0f14;font-weight:900;">
                                            <?php echo h($initial); ?>
                                        </div>
                                        <div>
                                            <div style="font-weight:900;color:#fff;"><?php echo h($nombre); ?></div>
                                            <div class="muted" style="font-size:0.85rem;"><?php echo h($roleBadge); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo h($u['email'] ?? ''); ?></td>
                                <td>S/ <?php echo number_format((float)($u['saldo'] ?? 0), 2); ?></td>

                                <td>
                                    <form class="roleform" method="POST" action="">
                                        <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
                                        <input type="hidden" name="action" value="update_role">
                                        <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                                        <select name="role">
                                            <option value="cliente"  <?php echo ($role==='cliente'?'selected':''); ?>>Cliente</option>
                                            <option value="vendedor" <?php echo ($role==='vendedor'?'selected':''); ?>>Vendedor</option>
                                            <option value="admin"    <?php echo ($role==='admin'?'selected':''); ?>>Admin</option>
                                        </select>
                                        <button class="iconbtn" title="Guardar rol" type="submit">
                                            <i class="fas fa-save"></i>
                                        </button>
                                    </form>
                                </td>

                                <td>
                                    <span class="badge <?php echo $isActive ? 'b-on' : 'b-off'; ?>">
                                        <?php echo h($estadoTxt); ?>
                                    </span>
                                </td>

                                <td style="text-align:right;">
                                    <div class="actions" style="justify-content:flex-end;">
                                        <form method="POST" action="" onsubmit="return confirm('¿Seguro que deseas <?php echo $isActive?'suspender':'activar'; ?> este usuario?');">
                                            <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
                                            <input type="hidden" name="action" value="toggle_active">
                                            <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                                            <button class="iconbtn <?php echo $isActive ? 'danger' : 'ok'; ?>" type="submit"
                                                    title="<?php echo $isActive ? 'Suspender' : 'Activar'; ?>">
                                                <i class="fas <?php echo $isActive ? 'fa-user-slash' : 'fa-user-check'; ?>"></i>
                                            </button>
                                        </form>

                                        <a class="iconbtn" title="Ver (si existe)" href="../user/dashboard.php?user_id=<?php echo (int)$u['id']; ?>"
                                           style="text-decoration:none;">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="muted" style="padding:18px;">No se encontraron usuarios.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="pager">
                <?php
                $base = "usuarios.php?q=" . urlencode($q) . "&page=";
                $prev = max(1, $page - 1);
                $next = min($totalPages, $page + 1);
                ?>
                <a href="<?php echo $base . $prev; ?>"><i class="fas fa-chevron-left"></i></a>
                <span class="current">Página <?php echo (int)$page; ?> / <?php echo (int)$totalPages; ?></span>
                <a href="<?php echo $base . $next; ?>"><i class="fas fa-chevron-right"></i></a>
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


