<?php
// admin/productos-admin.php â€” CRUD REAL de productos (stock + activo + destacado) + Layout PRO
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/sidebar.php';
require_once __DIR__ . '/../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** ===== Helpers ===== */
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function fmoney($n): string { return number_format((float)$n, 2); }

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
    foreach ($candidates as $col) if (colExists($cx, $table, $col)) return $col;
    return null;
}
function getColumnType(mysqli $cx, string $table, string $col): ?string {
    $t = $cx->real_escape_string($table);
    $c = $cx->real_escape_string($col);
    $rs = $cx->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
    if ($rs && ($row = $rs->fetch_assoc())) return (string)($row['Type'] ?? '');
    return null;
}
function isNumericColumnType(?string $type): bool {
    if (!$type) return false;
    $t = strtolower($type);
    return (strpos($t, 'int') !== false) || (strpos($t, 'decimal') !== false) || (strpos($t, 'float') !== false) || (strpos($t, 'double') !== false);
}
function isTruthyActive($v): bool {
    $s = strtolower(trim((string)$v));
    if ($s === '1' || $s === 'true') return true;
    if (is_numeric($v) && (int)$v === 1) return true;
    return in_array($s, ['activo','active','enabled','habilitado','on'], true);
}
function toggleActiveValue($current, bool $numericColumn) {
    if ($numericColumn) {
        return isTruthyActive($current) ? 0 : 1;
    }
    return isTruthyActive($current) ? 'INACTIVO' : 'ACTIVO';
}
function requireAdminHard(): void {
    if (function_exists('requireAdmin')) {
        requireAdmin();
        return;
    }
    if (!function_exists('isLoggedIn') || !function_exists('getCurrentUser')) {
        http_response_code(500);
        die('Faltan helpers de sesiÃ³n (isLoggedIn/getCurrentUser).');
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

/** ===== ProtecciÃ³n admin ===== */
requireAdminHard();

/** ===== Tabla/columnas productos ===== */
$TABLE_PRODUCTS = 'productos';
if (!tableExists($conexion, $TABLE_PRODUCTS)) {
    http_response_code(500);
    die("No existe la tabla `$TABLE_PRODUCTS` en la BD.");
}

$COL_ID        = pickCol($conexion, $TABLE_PRODUCTS, ['id'], 'id');
$COL_NAME      = pickCol($conexion, $TABLE_PRODUCTS, ['nombre', 'name', 'titulo', 'title'], 'nombre');
$COL_DESC      = pickCol($conexion, $TABLE_PRODUCTS, ['descripcion', 'description', 'detalles', 'details'], null);
$COL_PRICE     = pickCol($conexion, $TABLE_PRODUCTS, ['precio', 'price', 'precio_cents', 'price_cents'], 'precio');
$COL_STOCK     = pickCol($conexion, $TABLE_PRODUCTS, ['stock', 'cantidad', 'qty'], 'stock');
$COL_IMAGE     = pickCol($conexion, $TABLE_PRODUCTS, ['imagen_url', 'image_url', 'imagen', 'foto', 'image'], null);
$COL_ACTIVE    = pickCol($conexion, $TABLE_PRODUCTS, ['activo', 'is_active', 'estado'], 'activo');
$COL_FEATURED  = pickCol($conexion, $TABLE_PRODUCTS, ['destacado', 'featured', 'is_featured'], null);
$COL_CAT_ID    = pickCol($conexion, $TABLE_PRODUCTS, ['categoria_id', 'category_id'], null);
$COL_SELLER_ID = pickCol($conexion, $TABLE_PRODUCTS, ['vendedor_id', 'seller_id'], null);

$activeType = getColumnType($conexion, $TABLE_PRODUCTS, $COL_ACTIVE);
$activeIsNumeric = isNumericColumnType($activeType);

/** ===== CategorÃ­as (opcional) ===== */
$TABLE_CATS = tableExists($conexion, 'categorias') ? 'categorias' : (tableExists($conexion, 'categories') ? 'categories' : null);
$cats = [];
if ($TABLE_CATS && $COL_CAT_ID) {
    $C_ID   = pickCol($conexion, $TABLE_CATS, ['id'], 'id');
    $C_NAME = pickCol($conexion, $TABLE_CATS, ['nombre', 'name'], 'nombre');
    $rsCats = $conexion->query("SELECT `$C_ID` AS id, `$C_NAME` AS nombre FROM `$TABLE_CATS` ORDER BY `$C_NAME` ASC");
    if ($rsCats) while ($r = $rsCats->fetch_assoc()) $cats[] = $r;
}

/** ===== CSRF + Flash ===== */
if (empty($_SESSION['csrf_admin'])) {
    try { $_SESSION['csrf_admin'] = bin2hex(random_bytes(16)); }
    catch (Throwable $e) { $_SESSION['csrf_admin'] = sha1(uniqid('', true)); }
}
$csrf = $_SESSION['csrf_admin'];

$success_msg = $_SESSION['success'] ?? '';
$error_msg   = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

/** ===== Badges (igual que usuarios.php) ===== */
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
if (tableExists($conexion, 'productos') && colExists($conexion, 'productos', $COL_STOCK)) {
    $whereActivo = colExists($conexion, 'productos', $COL_ACTIVE) ? " AND `$COL_ACTIVE` " . ($activeIsNumeric ? "=1" : "IN ('ACTIVO','activo','ACTIVE','active','1')") : "";
    $rs = $conexion->query("SELECT COUNT(*) c FROM productos WHERE `$COL_STOCK`<=0 $whereActivo");
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

/** ===== Subida imagen producto (opcional) ===== */
function saveProductImage(array $file, string $targetDirRel = 'uploads/productos/', int $maxBytes = 5242880): array {
    // Retorna ['ok'=>bool,'path'=>string,'error'=>string]
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'path' => '', 'error' => ''];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'path' => '', 'error' => 'Error al subir archivo.'];
    }
    if (($file['size'] ?? 0) > $maxBytes) {
        return ['ok' => false, 'path' => '', 'error' => 'Imagen demasiado grande. MÃ¡x 5MB.'];
    }

    $allowedExt = ['jpg','jpeg','png','webp'];
    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        return ['ok' => false, 'path' => '', 'error' => 'ExtensiÃ³n no permitida. Solo JPG/PNG/WEBP.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    $allowedMime = ['image/jpeg','image/png','image/webp'];
    if (!in_array($mime, $allowedMime, true)) {
        return ['ok' => false, 'path' => '', 'error' => 'MIME invÃ¡lido (no es imagen real).'];
    }

    $absDir = __DIR__ . '/../' . $targetDirRel;
    if (!is_dir($absDir)) mkdir($absDir, 0755, true);

    $rand = bin2hex(random_bytes(12));
    $name = "prod_$rand.$ext";
    $abs  = $absDir . $name;
    $rel  = $targetDirRel . $name;

    if (!move_uploaded_file($file['tmp_name'], $abs)) {
        return ['ok' => false, 'path' => '', 'error' => 'No se pudo mover la imagen subida.'];
    }

    return ['ok' => true, 'path' => $rel, 'error' => ''];
}

/** ===== Acciones (POST) ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedCsrf = $_POST['csrf'] ?? '';
    if (!hash_equals($csrf, $postedCsrf)) {
        $_SESSION['error'] = 'CSRF invÃ¡lido. Recarga e intenta de nuevo.';
        header("Location: productos-admin.php");
        exit;
    }

    $action = $_POST['action'] ?? '';

    try {
        $pid = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;

        if ($action === 'toggle_active') {
            if ($pid <= 0) throw new Exception('Producto invÃ¡lido.');

            $sql = "SELECT `$COL_ACTIVE` AS a FROM `$TABLE_PRODUCTS` WHERE `$COL_ID`=? LIMIT 1";
            $st  = $conexion->prepare($sql);
            $st->bind_param("i", $pid);
            $st->execute();
            $row = $st->get_result()->fetch_assoc();
            if (!$row) throw new Exception('No existe el producto.');

            $nuevo = toggleActiveValue($row['a'], $activeIsNumeric);

            $sqlU = "UPDATE `$TABLE_PRODUCTS` SET `$COL_ACTIVE`=? WHERE `$COL_ID`=?";
            $stU  = $conexion->prepare($sqlU);

            if ($activeIsNumeric) {
                $nuevoInt = (int)$nuevo;
                $stU->bind_param("ii", $nuevoInt, $pid);
                $stU->execute();
                $_SESSION['success'] = ($nuevoInt === 1) ? 'Producto activado.' : 'Producto desactivado.';
            } else {
                $nuevoStr = (string)$nuevo;
                $stU->bind_param("si", $nuevoStr, $pid);
                $stU->execute();
                $_SESSION['success'] = (strtoupper($nuevoStr) === 'ACTIVO') ? 'Producto activado.' : 'Producto desactivado.';
            }

            header("Location: productos-admin.php");
            exit;
        }

        if ($action === 'toggle_featured') {
            if ($pid <= 0) throw new Exception('Producto invÃ¡lido.');
            if (!$COL_FEATURED) throw new Exception('Tu tabla no tiene columna destacado/featured.');

            $sql = "SELECT `$COL_FEATURED` AS d FROM `$TABLE_PRODUCTS` WHERE `$COL_ID`=? LIMIT 1";
            $st  = $conexion->prepare($sql);
            $st->bind_param("i", $pid);
            $st->execute();
            $row = $st->get_result()->fetch_assoc();
            if (!$row) throw new Exception('No existe el producto.');

            $nuevo = ((int)$row['d'] === 1) ? 0 : 1;

            $sqlU = "UPDATE `$TABLE_PRODUCTS` SET `$COL_FEATURED`=? WHERE `$COL_ID`=?";
            $stU  = $conexion->prepare($sqlU);
            $stU->bind_param("ii", $nuevo, $pid);
            $stU->execute();

            $_SESSION['success'] = $nuevo ? 'Marcado como destacado.' : 'Quitado de destacados.';
            header("Location: productos-admin.php");
            exit;
        }

        if ($action === 'update_stock') {
            if ($pid <= 0) throw new Exception('Producto invÃ¡lido.');
            if (!$COL_STOCK) throw new Exception('Tu tabla no tiene columna stock.');

            $stock = isset($_POST['stock']) ? (int)$_POST['stock'] : 0;
            if ($stock < 0) $stock = 0;

            $sqlU = "UPDATE `$TABLE_PRODUCTS` SET `$COL_STOCK`=? WHERE `$COL_ID`=?";
            $stU  = $conexion->prepare($sqlU);
            $stU->bind_param("ii", $stock, $pid);
            $stU->execute();

            $_SESSION['success'] = 'Stock actualizado.';
            header("Location: productos-admin.php");
            exit;
        }

        if ($action === 'create_product') {
            $nombre = trim((string)($_POST['nombre'] ?? ''));
            if ($nombre === '') throw new Exception('El nombre es obligatorio.');

            $precioRaw = (string)($_POST['precio'] ?? '0');
            $precio = (float)$precioRaw;
            if ($precio < 0) $precio = 0;

            $stock = isset($_POST['stock']) ? (int)$_POST['stock'] : 0;
            if ($stock < 0) $stock = 0;

            $activo = isset($_POST['activo']) ? 1 : 0;
            $destacado = isset($_POST['destacado']) ? 1 : 0;

            $desc = $COL_DESC ? trim((string)($_POST['descripcion'] ?? '')) : null;
            $catId = ($COL_CAT_ID && isset($_POST['categoria_id']) && $_POST['categoria_id'] !== '') ? (int)$_POST['categoria_id'] : null;

            $imagePath = '';
            if ($COL_IMAGE && isset($_FILES['imagen'])) {
                $up = saveProductImage($_FILES['imagen']);
                if (!$up['ok']) throw new Exception($up['error']);
                $imagePath = $up['path'];
            }

            $isCents = (stripos($COL_PRICE, 'cents') !== false);
            $priceToStore = $isCents ? (int)round($precio * 100) : $precio;

            $cols = ["`$COL_NAME`", "`$COL_PRICE`", "`$COL_STOCK`", "`$COL_ACTIVE`"];
            $vals = ["?", "?", "?", "?"];
            $types = "s" . ($isCents ? "i" : "d") . "ii";
            $bind = [$nombre, $priceToStore, $stock, $activeIsNumeric ? $activo : ($activo ? 'ACTIVO' : 'INACTIVO')];

            if ($COL_FEATURED) { $cols[] = "`$COL_FEATURED`"; $vals[] = "?"; $types .= "i"; $bind[] = $destacado; }
            if ($COL_DESC)     { $cols[] = "`$COL_DESC`";     $vals[] = "?"; $types .= "s"; $bind[] = $desc ?? ''; }
            if ($COL_IMAGE)    { $cols[] = "`$COL_IMAGE`";    $vals[] = "?"; $types .= "s"; $bind[] = $imagePath; }
            if ($COL_CAT_ID && $catId !== null) { $cols[] = "`$COL_CAT_ID`"; $vals[] = "?"; $types .= "i"; $bind[] = $catId; }

            $sql = "INSERT INTO `$TABLE_PRODUCTS` (" . implode(",", $cols) . ") VALUES (" . implode(",", $vals) . ")";
            $st  = $conexion->prepare($sql);
            $st->bind_param($types, ...$bind);
            $st->execute();

            $_SESSION['success'] = 'Producto creado.';
            header("Location: productos-admin.php");
            exit;
        }

        if ($action === 'update_product') {
            if ($pid <= 0) throw new Exception('Producto invÃ¡lido.');

            $nombre = trim((string)($_POST['nombre'] ?? ''));
            if ($nombre === '') throw new Exception('El nombre es obligatorio.');

            $precioRaw = (string)($_POST['precio'] ?? '0');
            $precio = (float)$precioRaw;
            if ($precio < 0) $precio = 0;

            $stock = isset($_POST['stock']) ? (int)$_POST['stock'] : 0;
            if ($stock < 0) $stock = 0;

            $activo = isset($_POST['activo']) ? 1 : 0;
            $destacado = isset($_POST['destacado']) ? 1 : 0;

            $desc = $COL_DESC ? trim((string)($_POST['descripcion'] ?? '')) : null;
            $catId = ($COL_CAT_ID && isset($_POST['categoria_id']) && $_POST['categoria_id'] !== '') ? (int)$_POST['categoria_id'] : null;

            $imagePath = '';
            $setImage = false;
            if ($COL_IMAGE && isset($_FILES['imagen']) && ($_FILES['imagen']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $up = saveProductImage($_FILES['imagen']);
                if (!$up['ok']) throw new Exception($up['error']);
                $imagePath = $up['path'];
                $setImage = true;
            }

            $isCents = (stripos($COL_PRICE, 'cents') !== false);
            $priceToStore = $isCents ? (int)round($precio * 100) : $precio;

            $sets  = ["`$COL_NAME`=?", "`$COL_PRICE`=?", "`$COL_STOCK`=?", "`$COL_ACTIVE`=?"];
            $types = "s" . ($isCents ? "i" : "d") . "i" . ($activeIsNumeric ? "i" : "s");
            $bind  = [
                $nombre,
                $priceToStore,
                $stock,
                $activeIsNumeric ? $activo : ($activo ? 'ACTIVO' : 'INACTIVO'),
            ];

            if ($COL_FEATURED) { $sets[] = "`$COL_FEATURED`=?"; $types .= "i"; $bind[] = $destacado; }
            if ($COL_DESC)     { $sets[] = "`$COL_DESC`=?";     $types .= "s"; $bind[] = $desc ?? ''; }
            if ($COL_CAT_ID)   { $sets[] = "`$COL_CAT_ID`=?";   $types .= "i"; $bind[] = (int)($catId ?? 0); }
            if ($COL_IMAGE && $setImage) { $sets[] = "`$COL_IMAGE`=?"; $types .= "s"; $bind[] = $imagePath; }

            $types .= "i";
            $bind[] = $pid;

            $sql = "UPDATE `$TABLE_PRODUCTS` SET " . implode(", ", $sets) . " WHERE `$COL_ID`=?";
            $st  = $conexion->prepare($sql);
            $st->bind_param($types, ...$bind);
            $st->execute();

            $_SESSION['success'] = 'Producto actualizado.';
            header("Location: productos-admin.php");
            exit;
        }

        if ($action === 'delete_product') {
            if ($pid <= 0) throw new Exception('Producto invÃ¡lido.');

            // Preferimos "desactivar" (borrado lÃ³gico)
            if ($COL_ACTIVE) {
                $offVal = $activeIsNumeric ? 0 : 'INACTIVO';
                $sqlU = "UPDATE `$TABLE_PRODUCTS` SET `$COL_ACTIVE`=? WHERE `$COL_ID`=?";
                $stU  = $conexion->prepare($sqlU);
                if ($activeIsNumeric) {
                    $offInt = (int)$offVal;
                    $stU->bind_param("ii", $offInt, $pid);
                } else {
                    $offStr = (string)$offVal;
                    $stU->bind_param("si", $offStr, $pid);
                }
                $stU->execute();
                $_SESSION['success'] = 'Producto desactivado (borrado lÃ³gico).';
            } else {
                $sqlD = "DELETE FROM `$TABLE_PRODUCTS` WHERE `$COL_ID`=?";
                $stD  = $conexion->prepare($sqlD);
                $stD->bind_param("i", $pid);
                $stD->execute();
                $_SESSION['success'] = 'Producto eliminado.';
            }

            header("Location: productos-admin.php");
            exit;
        }

        $_SESSION['error'] = 'AcciÃ³n no vÃ¡lida.';
        header("Location: productos-admin.php");
        exit;

    } catch (Exception $e) {
        $_SESSION['error'] = 'Error: ' . $e->getMessage();
        header("Location: productos-admin.php");
        exit;
    }
}

/** ===== Listado + bÃºsqueda + paginaciÃ³n ===== */
$q     = trim((string)($_GET['q'] ?? ''));
$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = 25;
$off   = ($page - 1) * $limit;

$where  = "1=1";
$params = [];
$types  = "";

if ($q !== '') {
    $where .= " AND (p.`$COL_NAME` LIKE ? OR p.`$COL_ID` = ?";
    if ($COL_SELLER_ID && tableExists($conexion, 'usuarios') && colExists($conexion, 'usuarios', 'nombre')) {
        $where .= " OR sv.nombre LIKE ?";
    }
    $where .= ")";
    $like = "%$q%";
    $params[] = $like;
    $params[] = (int)$q;
    $types = "si";
    if ($COL_SELLER_ID && tableExists($conexion, 'usuarios') && colExists($conexion, 'usuarios', 'nombre')) {
        $params[] = $like;
        $types .= "s";
    }
}

$sellerJoin = ($COL_SELLER_ID && tableExists($conexion, 'usuarios')) ? " LEFT JOIN usuarios sv ON sv.id = p.`$COL_SELLER_ID`" : "";
$sqlCount = "SELECT COUNT(*) c FROM `$TABLE_PRODUCTS` p $sellerJoin WHERE $where";
$stC = $conexion->prepare($sqlCount);
if ($types) $stC->bind_param($types, ...$params);
$stC->execute();
$total = (int)($stC->get_result()->fetch_assoc()['c'] ?? 0);
$totalPages = max(1, (int)ceil($total / $limit));

$selectCols = [
    "`$COL_ID` AS id",
    "`$COL_NAME` AS nombre",
    "`$COL_PRICE` AS precio",
    "`$COL_STOCK` AS stock",
    "`$COL_ACTIVE` AS activo"
];
if ($COL_FEATURED) $selectCols[] = "`$COL_FEATURED` AS destacado";
if ($COL_IMAGE)    $selectCols[] = "`$COL_IMAGE` AS imagen";
if ($COL_DESC)     $selectCols[] = "`$COL_DESC` AS descripcion";
if ($COL_CAT_ID)   $selectCols[] = "`$COL_CAT_ID` AS categoria_id";
if ($COL_SELLER_ID) $selectCols[] = "p.`$COL_SELLER_ID` AS vendedor_id";
if ($COL_SELLER_ID && tableExists($conexion, 'usuarios')) {
    $selectCols[] = "sv.nombre AS vendedor_nombre";
    $selectCols[] = "sv.email AS vendedor_email";
}

$selectColsSql = array_map(function($col) {
    return str_starts_with($col, "`") ? "p.$col" : $col;
}, $selectCols);

$sql = "SELECT " . implode(", ", $selectColsSql) . " FROM `$TABLE_PRODUCTS` p $sellerJoin WHERE $where ORDER BY p.`$COL_ID` DESC LIMIT $limit OFFSET $off";
$st = $conexion->prepare($sql);
if ($types) $st->bind_param($types, ...$params);
$st->execute();
$rs = $st->get_result();

// Edit
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$edit = null;
if ($editId > 0) {
    $sqlE = "SELECT " . implode(", ", $selectColsSql) . " FROM `$TABLE_PRODUCTS` p $sellerJoin WHERE p.`$COL_ID`=? LIMIT 1";
    $stE  = $conexion->prepare($sqlE);
    $stE->bind_param("i", $editId);
    $stE->execute();
    $edit = $stE->get_result()->fetch_assoc();
}

$page_title  = "Productos - Admin - Monkeystraming";
$isCents = (stripos($COL_PRICE, 'cents') !== false);

$admin = function_exists('getCurrentUser') ? getCurrentUser() : [];
$adminName  = $admin['nombre'] ?? 'Administrador';
$adminEmail = $admin['email'] ?? '';
$currentPage = basename($_SERVER['PHP_SELF']);
function navActive(string $file, string $currentPage): string { return $currentPage === $file ? 'active' : ''; }
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

        /* Cards / content */
        .card{
            background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);
            border-radius:18px;padding:18px;backdrop-filter:blur(10px);
        }
        .alert{padding:12px 14px;border-radius:12px;margin-bottom:14px;font-weight:800;display:flex;gap:10px;align-items:center}
        .ok{background:rgba(52,199,89,0.12);border:1px solid rgba(52,199,89,0.35);color:#34c759}
        .err{background:rgba(255,59,48,0.12);border:1px solid rgba(255,59,48,0.35);color:#ff3b30}

        .grid{display:grid;grid-template-columns: 1.2fr 1fr; gap:18px; align-items:start;}
        @media (max-width: 980px){ .grid{grid-template-columns:1fr} }

        .toolbar{display:flex;gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap;margin-bottom:12px}
        .search{
            display:flex;gap:10px;align-items:center;flex:1;min-width:260px;
            background:rgba(0,0,0,0.35);border:1px solid rgba(255,255,255,0.12);
            border-radius:14px;padding:10px 12px;
        }
        .search input{border:none;outline:none;background:transparent;color:#fff;width:100%;font-size:0.95rem}
        .meta{color:#aaa;font-size:0.9rem}

        table{width:100%;border-collapse:collapse}
        th,td{padding:12px 10px;text-align:left;border-bottom:1px solid rgba(255,255,255,0.06);vertical-align:middle}
        th{color:#aaa;font-size:0.85rem;text-transform:uppercase;letter-spacing:0.5px;font-weight:900}

        .badge{padding:5px 10px;border-radius:999px;font-size:0.8rem;font-weight:900;display:inline-block}
        .b-on{background:rgba(52,199,89,0.18);color:#34c759}
        .b-off{background:rgba(255,59,48,0.18);color:#ff3b30}
        .b-feat{background:rgba(255,204,0,0.18);color:#ffcc00}

        .actions{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}
        .iconbtn{
            width:38px;height:38px;border-radius:12px;border:1px solid rgba(255,255,255,0.10);
            background:rgba(255,255,255,0.06);color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center
        }
        .iconbtn.danger{background:rgba(255,59,48,0.12);border-color:rgba(255,59,48,0.25);color:#ff3b30}
        .iconbtn.ok{background:rgba(52,199,89,0.12);border-color:rgba(52,199,89,0.25);color:#34c759}
        .iconbtn.warn{background:rgba(255,204,0,0.12);border-color:rgba(255,204,0,0.25);color:#ffcc00}

        .formrow{display:grid;grid-template-columns: 1fr 1fr; gap:12px}
        label{display:block;color:#ccc;font-size:0.9rem;margin:10px 0 6px}
        input, textarea, select{
            width:100%;padding:10px 12px;border-radius:12px;outline:none;color:#fff;
            background:rgba(0,0,0,0.35);border:1px solid rgba(255,255,255,0.12)
        }
        textarea{min-height:90px;resize:vertical}
        .checks{display:flex;gap:14px;flex-wrap:wrap;margin-top:10px}
        .checks label{margin:0;display:flex;gap:8px;align-items:center;font-weight:900}
        .checks input{width:auto}

        .pager{display:flex;gap:10px;align-items:center;justify-content:flex-end;margin-top:12px;flex-wrap:wrap}
        .pager a{padding:8px 12px;border-radius:12px;border:1px solid rgba(255,255,255,0.10);background:rgba(255,255,255,0.06);color:#fff;text-decoration:none}
        .pager .current{color:#12aaff;font-weight:900}
        .muted{color:#777}

        .thumb{
            width:44px;height:44px;border-radius:12px;overflow:hidden;
            background:rgba(0,0,0,0.35);border:1px solid rgba(255,255,255,0.10);
            display:flex;align-items:center;justify-content:center
        }
        .thumb img{width:100%;height:100%;object-fit:cover}

        .btn{
            border:none;cursor:pointer;border-radius:12px;padding:10px 14px;font-weight:900;
            background:var(--primary-gradient);color:#0d0f14;text-decoration:none;display:inline-flex;gap:8px;align-items:center
        }
        .btn.secondary{background:rgba(255,255,255,0.06);color:#fff;border:1px solid rgba(255,255,255,0.10)}
    </style>
</head>
<body>

<?php renderAdminSidebar($conexion, $currentPage ?? basename($_SERVER['PHP_SELF'])); ?>

<main class="admin-main">
    <header class="admin-header">
        <div class="header-title">
            <h1>Productos</h1>
            <p>Bienvenido, <?php echo h($adminName); ?><?php echo $adminEmail ? " â€” " . h($adminEmail) : ""; ?></p>
        </div>

        <div class="header-actions">
            <input type="text" class="search-bar" placeholder="ðŸ” Buscar en el sistema..." disabled>
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

        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px;">
            <a class="btn secondary" href="index.php"><i class="fas fa-arrow-left"></i> Dashboard</a>
            <a class="btn" href="../productos.php"><i class="fas fa-globe"></i> Ver productos</a>
        </div>

        <?php if ($success_msg): ?>
            <div class="alert ok"><i class="fas fa-check-circle"></i> <?php echo h($success_msg); ?></div>
        <?php endif; ?>
        <?php if ($error_msg): ?>
            <div class="alert err"><i class="fas fa-exclamation-triangle"></i> <?php echo h($error_msg); ?></div>
        <?php endif; ?>

        <div class="grid">

            <!-- LISTADO -->
            <div class="card">
                <div class="toolbar">
                    <form class="search" method="GET" action="">
                        <i class="fas fa-search" style="color:#12aaff;"></i>
                        <input type="text" name="q" placeholder="Buscar por nombre o ID..." value="<?php echo h($q); ?>">
                    </form>
                    <div class="meta">Total: <strong><?php echo number_format($total); ?></strong></div>
                </div>

                <div style="overflow:auto;">
                    <table>
                        <thead>
                            <tr>
                                <th style="width:70px;">ID</th>
                                <th>Producto</th>
                                <th style="width:190px;">Vendedor</th>
                                <th style="width:130px;">Stock</th>
                                <th style="width:140px;">Precio</th>
                                <th style="width:140px;">Estado</th>
                                <th style="width:270px;text-align:right;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($rs && $rs->num_rows > 0): ?>
                            <?php while($p = $rs->fetch_assoc()): ?>
                                <?php
                                    $activo = isTruthyActive($p['activo'] ?? 0);
                                    $dest   = $COL_FEATURED ? ((int)($p['destacado'] ?? 0) === 1) : false;
                                    $precioShow = $isCents ? ((int)($p['precio'] ?? 0) / 100) : (float)($p['precio'] ?? 0);
                                    $stockVal = (int)($p['stock'] ?? 0);
                                ?>
                                <tr>
                                    <td>#<?php echo (int)$p['id']; ?></td>

                                    <td>
                                        <div style="display:flex;gap:10px;align-items:center;">
                                            <?php if ($COL_IMAGE): ?>
                                                <div class="thumb">
                                                    <?php if (!empty($p['imagen'])): ?>
                                                        <img src="<?php echo h('../' . ltrim((string)$p['imagen'], '/')); ?>" alt="img">
                                                    <?php else: ?>
                                                        <i class="fas fa-image" style="color:#12aaff;"></i>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <div style="font-weight:900;color:#fff;">
                                                    <?php echo h($p['nombre'] ?? ('Producto #' . (int)$p['id'])); ?>
                                                </div>
                                                <div class="muted" style="font-size:0.85rem;">
                                                    <?php if ($dest): ?><span class="badge b-feat">destacado</span><?php endif; ?>
                                                    <?php if ($stockVal <= 0): ?><span class="badge b-off" style="margin-left:6px;">sin stock</span><?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <td>
                                        <?php if (!empty($p['vendedor_id'])): ?>
                                            <strong><?php echo h($p['vendedor_nombre'] ?? ('Vendedor #' . (int)$p['vendedor_id'])); ?></strong>
                                            <div class="muted" style="font-size:0.82rem;"><?php echo h($p['vendedor_email'] ?? ''); ?></div>
                                            <a class="muted" style="font-size:0.82rem;color:#12aaff;text-decoration:none;" href="vendedores.php?seller_id=<?php echo (int)$p['vendedor_id']; ?>">Ver vendedor</a>
                                        <?php else: ?>
                                            <span class="muted">Admin / sin vendedor</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <form method="POST" action="" style="display:flex;gap:8px;align-items:center;">
                                            <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
                                            <input type="hidden" name="action" value="update_stock">
                                            <input type="hidden" name="product_id" value="<?php echo (int)$p['id']; ?>">
                                            <input type="number" name="stock" min="0" value="<?php echo $stockVal; ?>" style="max-width:90px;">
                                            <button class="iconbtn" title="Guardar stock" type="submit"><i class="fas fa-save"></i></button>
                                        </form>
                                    </td>

                                    <td>S/ <?php echo fmoney($precioShow); ?></td>

                                    <td>
                                        <span class="badge <?php echo $activo ? 'b-on' : 'b-off'; ?>">
                                            <?php echo $activo ? 'activo' : 'inactivo'; ?>
                                        </span>
                                    </td>

                                    <td style="text-align:right;">
                                        <div class="actions">
                                            <a class="iconbtn" href="productos-admin.php?edit=<?php echo (int)$p['id']; ?>" title="Editar" style="text-decoration:none;">
                                                <i class="fas fa-edit"></i>
                                            </a>

                                            <form method="POST" action="" onsubmit="return confirm('Â¿Seguro que deseas <?php echo $activo?'desactivar':'activar'; ?> este producto?');">
                                                <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
                                                <input type="hidden" name="action" value="toggle_active">
                                                <input type="hidden" name="product_id" value="<?php echo (int)$p['id']; ?>">
                                                <button class="iconbtn <?php echo $activo ? 'danger' : 'ok'; ?>" type="submit" title="<?php echo $activo?'Desactivar':'Activar'; ?>">
                                                    <i class="fas <?php echo $activo ? 'fa-toggle-off' : 'fa-toggle-on'; ?>"></i>
                                                </button>
                                            </form>

                                            <?php if ($COL_FEATURED): ?>
                                            <form method="POST" action="">
                                                <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
                                                <input type="hidden" name="action" value="toggle_featured">
                                                <input type="hidden" name="product_id" value="<?php echo (int)$p['id']; ?>">
                                                <button class="iconbtn warn" type="submit" title="<?php echo $dest ? 'Quitar destacado' : 'Marcar destacado'; ?>">
                                                    <i class="fas fa-star"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>

                                            <form method="POST" action="" onsubmit="return confirm('Esto desactivarÃ¡ el producto. Â¿Continuar?');">
                                                <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
                                                <input type="hidden" name="action" value="delete_product">
                                                <input type="hidden" name="product_id" value="<?php echo (int)$p['id']; ?>">
                                                <button class="iconbtn danger" type="submit" title="Eliminar (lÃ³gico)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="muted" style="padding:18px;">No se encontraron productos.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="pager">
                    <?php
                    $base = "productos-admin.php?q=" . urlencode($q) . "&page=";
                    $prev = max(1, $page - 1);
                    $next = min($totalPages, $page + 1);
                    ?>
                    <a href="<?php echo $base . $prev; ?>"><i class="fas fa-chevron-left"></i></a>
                    <span class="current">PÃ¡gina <?php echo (int)$page; ?> / <?php echo (int)$totalPages; ?></span>
                    <a href="<?php echo $base . $next; ?>"><i class="fas fa-chevron-right"></i></a>
                </div>
            </div>

            <!-- FORM CREAR / EDITAR -->
            <div class="card">
                <?php if ($edit): ?>
                    <h2 style="font-size:1.2rem;color:#fff;margin-bottom:8px;"><i class="fas fa-pen"></i> Editar producto #<?php echo (int)$edit['id']; ?></h2>
                    <p class="muted" style="margin-bottom:10px;">Si subes imagen nueva, reemplaza la imagen guardada.</p>

                    <?php if ($COL_IMAGE && !empty($edit['imagen'])): ?>
                        <div style="display:flex;gap:10px;align-items:center;margin:10px 0 6px;">
                            <div class="thumb" style="width:70px;height:70px;">
                                <img src="<?php echo h('../' . ltrim((string)$edit['imagen'], '/')); ?>" alt="img">
                            </div>
                            <div class="muted"><?php echo h(basename((string)$edit['imagen'])); ?></div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" enctype="multipart/form-data">
                        <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
                        <input type="hidden" name="action" value="update_product">
                        <input type="hidden" name="product_id" value="<?php echo (int)$edit['id']; ?>">

                        <label>Nombre</label>
                        <input type="text" name="nombre" value="<?php echo h($edit['nombre'] ?? ''); ?>" required>

                        <?php if ($COL_DESC): ?>
                            <label>DescripciÃ³n</label>
                            <textarea name="descripcion"><?php echo h($edit['descripcion'] ?? ''); ?></textarea>
                        <?php endif; ?>

                        <div class="formrow">
                            <div>
                                <label>Precio (S/)</label>
                                <input type="number" step="0.01" min="0" name="precio"
                                       value="<?php echo h($isCents ? ((int)($edit['precio'] ?? 0)/100) : (float)($edit['precio'] ?? 0)); ?>" required>
                            </div>
                            <div>
                                <label>Stock</label>
                                <input type="number" min="0" name="stock" value="<?php echo (int)($edit['stock'] ?? 0); ?>" required>
                            </div>
                        </div>

                        <?php if ($COL_CAT_ID && $TABLE_CATS): ?>
                            <label>CategorÃ­a</label>
                            <select name="categoria_id">
                                <option value="">â€”</option>
                                <?php foreach($cats as $c): ?>
                                    <option value="<?php echo (int)$c['id']; ?>"
                                        <?php echo ((int)($edit['categoria_id'] ?? 0) === (int)$c['id']) ? 'selected' : ''; ?>>
                                        <?php echo h($c['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>

                        <?php if ($COL_IMAGE): ?>
                            <label>Imagen (opcional)</label>
                            <input type="file" name="imagen" accept="image/jpeg,image/png,image/webp">
                            <p class="muted" style="margin-top:6px;">Se guarda en <strong>uploads/productos/</strong> (JPG/PNG/WEBP, mÃ¡x 5MB).</p>
                        <?php endif; ?>

                        <div class="checks">
                            <label><input type="checkbox" name="activo" <?php echo isTruthyActive($edit['activo'] ?? 0) ? 'checked' : ''; ?>> Activo</label>
                            <?php if ($COL_FEATURED): ?>
                                <label><input type="checkbox" name="destacado" <?php echo ((int)($edit['destacado'] ?? 0) === 1) ? 'checked' : ''; ?>> Destacado</label>
                            <?php endif; ?>
                        </div>

                        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:14px;">
                            <button class="btn" type="submit"><i class="fas fa-save"></i> Guardar cambios</button>
                            <a class="btn secondary" href="productos-admin.php"><i class="fas fa-times"></i> Cancelar</a>
                        </div>
                    </form>
                <?php else: ?>
                    <h2 style="font-size:1.2rem;color:#fff;margin-bottom:8px;"><i class="fas fa-plus"></i> Crear producto</h2>
                    <p class="muted" style="margin-bottom:10px;">Crea un producto nuevo con precio, stock y estado.</p>

                    <form method="POST" action="" enctype="multipart/form-data">
                        <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
                        <input type="hidden" name="action" value="create_product">

                        <label>Nombre</label>
                        <input type="text" name="nombre" placeholder="Ej: Netflix 4K Premium" required>

                        <?php if ($COL_DESC): ?>
                            <label>DescripciÃ³n</label>
                            <textarea name="descripcion" placeholder="Detalles del producto..."></textarea>
                        <?php endif; ?>

                        <div class="formrow">
                            <div>
                                <label>Precio (S/)</label>
                                <input type="number" step="0.01" min="0" name="precio" value="0.00" required>
                            </div>
                            <div>
                                <label>Stock</label>
                                <input type="number" min="0" name="stock" value="0" required>
                            </div>
                        </div>

                        <?php if ($COL_CAT_ID && $TABLE_CATS): ?>
                            <label>CategorÃ­a</label>
                            <select name="categoria_id">
                                <option value="">â€”</option>
                                <?php foreach($cats as $c): ?>
                                    <option value="<?php echo (int)$c['id']; ?>"><?php echo h($c['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>

                        <?php if ($COL_IMAGE): ?>
                            <label>Imagen (opcional)</label>
                            <input type="file" name="imagen" accept="image/jpeg,image/png,image/webp">
                            <p class="muted" style="margin-top:6px;">Se guarda en <strong>uploads/productos/</strong> (JPG/PNG/WEBP, mÃ¡x 5MB).</p>
                        <?php endif; ?>

                        <div class="checks">
                            <label><input type="checkbox" name="activo" checked> Activo</label>
                            <?php if ($COL_FEATURED): ?>
                                <label><input type="checkbox" name="destacado"> Destacado</label>
                            <?php endif; ?>
                        </div>

                        <div style="margin-top:14px;">
                            <button class="btn" type="submit"><i class="fas fa-plus"></i> Crear producto</button>
                        </div>
                    </form>
                <?php endif; ?>
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


