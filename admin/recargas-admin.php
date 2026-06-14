<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/sidebar.php';
require_once __DIR__ . '/../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** ===== Helpers layout/badges ===== */
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

/** ===== Auth admin (tu lógica original) ===== */
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$admin = getCurrentUser();
if (!$admin || strtolower(($admin['role'] ?? '')) !== 'admin') {
    http_response_code(403);
    die('Acceso denegado: solo administradores.');
}

$page_title  = "Recargas - Admin - Monkeystraming";
$success_msg = '';
$error_msg   = '';

/** ===== CSRF ===== */
if (empty($_SESSION['csrf_token'])) {
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        $_SESSION['csrf_token'] = sha1(uniqid('', true));
    }
}
$csrf_token = $_SESSION['csrf_token'];

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

/** ===== Detectar columna rechazo_motivo ===== */
$has_rechazo_motivo = false;
$colCheck = $conexion->query("SHOW COLUMNS FROM recargas LIKE 'rechazo_motivo'");
if ($colCheck && $colCheck->num_rows > 0) {
    $has_rechazo_motivo = true;
}

/** ===== POST actions (aprobar / rechazar) ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($csrf_token, $token)) {
        $error_msg = "Token inválido. Recarga la página e intenta nuevamente.";
    } else {
        $action    = cleanInput($_POST['action'] ?? '');
        $recargaId = (int)($_POST['recarga_id'] ?? 0);

        if ($recargaId <= 0) {
            $error_msg = "ID de recarga inválido.";
        } else {
            if ($action === 'approve') {
                try {
                    $conexion->begin_transaction();

                    $stmt = $conexion->prepare("SELECT id, usuario_id, monto, comision, estado FROM recargas WHERE id = ? FOR UPDATE");
                    $stmt->bind_param("i", $recargaId);
                    $stmt->execute();
                    $rec = $stmt->get_result()->fetch_assoc();

                    if (!$rec) throw new Exception("La recarga no existe.");
                    if ($rec['estado'] !== 'pendiente') throw new Exception("Esta recarga ya no está pendiente.");

                    $usuarioId = (int)$rec['usuario_id'];
                    $monto     = (float)$rec['monto'];
                    $comision  = (float)$rec['comision'];

                    $bonus = ($monto >= 100) ? ($monto * 0.05) : 0;
                    $montoNeto = $monto - $comision + $bonus;
                    if ($montoNeto < 0) $montoNeto = 0;

                    $up = $conexion->prepare("UPDATE recargas
                                              SET estado = 'aprobada',
                                                  aprobado_por = ?,
                                                  fecha_aprobacion = NOW()
                                              WHERE id = ? AND estado = 'pendiente'");
                    $adminId = (int)($admin['id'] ?? 0);
                    $up->bind_param("ii", $adminId, $recargaId);
                    $up->execute();

                    if ($up->affected_rows !== 1) {
                        throw new Exception("No se pudo aprobar (posible carrera o estado cambiado).");
                    }

                    $upUser = $conexion->prepare("UPDATE usuarios SET saldo = saldo + ? WHERE id = ?");
                    $upUser->bind_param("di", $montoNeto, $usuarioId);
                    $upUser->execute();

                    if ($upUser->affected_rows !== 1) {
                        throw new Exception("No se pudo actualizar el saldo del usuario.");
                    }

                    $conexion->commit();
                    $success_msg = "Recarga #{$recargaId} aprobada. Se sumó S/ " . number_format($montoNeto, 2) . " al usuario #{$usuarioId}.";
                } catch (Exception $e) {
                    $conexion->rollback();
                    $error_msg = "Error al aprobar: " . $e->getMessage();
                }
            } elseif ($action === 'reject') {
                $motivo = trim((string)($_POST['motivo'] ?? ''));
                $motivo = ($motivo !== '') ? cleanInput($motivo) : 'Sin motivo especificado';

                try {
                    $conexion->begin_transaction();

                    $stmt = $conexion->prepare("SELECT id, estado FROM recargas WHERE id = ? FOR UPDATE");
                    $stmt->bind_param("i", $recargaId);
                    $stmt->execute();
                    $rec = $stmt->get_result()->fetch_assoc();

                    if (!$rec) throw new Exception("La recarga no existe.");
                    if ($rec['estado'] !== 'pendiente') throw new Exception("Esta recarga ya no está pendiente.");

                    $adminId = (int)($admin['id'] ?? 0);

                    if ($has_rechazo_motivo) {
                        $up = $conexion->prepare("UPDATE recargas
                                                  SET estado = 'rechazada',
                                                      aprobado_por = ?,
                                                      fecha_aprobacion = NOW(),
                                                      rechazo_motivo = ?
                                                  WHERE id = ? AND estado = 'pendiente'");
                        $up->bind_param("isi", $adminId, $motivo, $recargaId);
                    } else {
                        $up = $conexion->prepare("UPDATE recargas
                                                  SET estado = 'rechazada',
                                                      aprobado_por = ?,
                                                      fecha_aprobacion = NOW()
                                                  WHERE id = ? AND estado = 'pendiente'");
                        $up->bind_param("ii", $adminId, $recargaId);
                    }

                    $up->execute();

                    if ($up->affected_rows !== 1) {
                        throw new Exception("No se pudo rechazar (posible carrera o estado cambiado).");
                    }

                    $conexion->commit();
                    $success_msg = $has_rechazo_motivo
                        ? "Recarga #{$recargaId} rechazada con motivo."
                        : "Recarga #{$recargaId} rechazada. (Nota: falta columna rechazo_motivo en BD para guardar el motivo).";
                } catch (Exception $e) {
                    $conexion->rollback();
                    $error_msg = "Error al rechazar: " . $e->getMessage();
                }
            } else {
                $error_msg = "Acción no válida.";
            }
        }
    }
}

/** ===== Filtros y paginación ===== */
$filtro_estado = isset($_GET['estado']) ? cleanInput($_GET['estado']) : 'todos';
$filtro_usuario = isset($_GET['usuario']) ? cleanInput($_GET['usuario']) : '';
$filtro_metodo = isset($_GET['metodo']) ? cleanInput($_GET['metodo']) : '';
$filtro_fecha_inicio = isset($_GET['fecha_inicio']) ? cleanInput($_GET['fecha_inicio']) : '';
$filtro_fecha_fin = isset($_GET['fecha_fin']) ? cleanInput($_GET['fecha_fin']) : '';

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

/** ===== Construir consulta para historial ===== */
$where = [];
$params = [];
$types = '';

// Filtro por estado - SOLO si no es 'todos'
if ($filtro_estado !== 'todos') {
    $where[] = "r.estado = ?";
    $params[] = $filtro_estado;
    $types .= 's';
}

// Filtro por usuario (ID o nombre/email)
if ($filtro_usuario !== '') {
    if (is_numeric($filtro_usuario)) {
        $where[] = "u.id = ?";
        $params[] = (int)$filtro_usuario;
        $types .= 'i';
    } else {
        $where[] = "(u.nombre LIKE ? OR u.email LIKE ?)";
        $params[] = "%$filtro_usuario%";
        $types .= 's';
        $params[] = "%$filtro_usuario%";
        $types .= 's';
    }
}

// Filtro por método
if ($filtro_metodo !== '') {
    $where[] = "r.metodo = ?";
    $params[] = $filtro_metodo;
    $types .= 's';
}

// Filtro por fecha - CONVERSIÓN DE FORMATO
if ($filtro_fecha_inicio !== '') {
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $filtro_fecha_inicio, $matches)) {
        $filtro_fecha_inicio = $matches[3] . '-' . $matches[1] . '-' . $matches[2];
    }
    $where[] = "DATE(r.fecha_solicitud) >= ?";
    $params[] = $filtro_fecha_inicio;
    $types .= 's';
}
if ($filtro_fecha_fin !== '') {
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $filtro_fecha_fin, $matches)) {
        $filtro_fecha_fin = $matches[3] . '-' . $matches[1] . '-' . $matches[2];
    }
    $where[] = "DATE(r.fecha_solicitud) <= ?";
    $params[] = $filtro_fecha_fin;
    $types .= 's';
}

$where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

/** ===== Contar total de registros ===== */
$sql_count = "SELECT COUNT(*) as total 
              FROM recargas r
              INNER JOIN usuarios u ON u.id = r.usuario_id
              $where_clause";

$stmt_count = $conexion->prepare($sql_count);
if ($types !== '' && !empty($params)) {
    $stmt_count->bind_param($types, ...$params);
}
$stmt_count->execute();
$result_count = $stmt_count->get_result()->fetch_assoc();
$total_recargas = (int)($result_count['total'] ?? 0);
$total_pages = ceil($total_recargas / $limit);

/** ===== Obtener recargas para mostrar ===== */
$sql = "SELECT r.*, 
               u.nombre AS usuario_nombre, 
               u.email AS usuario_email,
               a.nombre AS admin_nombre
        FROM recargas r
        INNER JOIN usuarios u ON u.id = r.usuario_id
        LEFT JOIN usuarios a ON a.id = r.aprobado_por
        $where_clause
        ORDER BY r.fecha_solicitud DESC
        LIMIT ? OFFSET ?";

// Parámetros para la consulta principal
$params_for_query = $params;
$types_for_query = $types;

// Añadir limit y offset
$params_for_query[] = $limit;
$types_for_query .= 'i';
$params_for_query[] = $offset;
$types_for_query .= 'i';

$stmt = $conexion->prepare($sql);
if ($types_for_query !== '' && !empty($params_for_query)) {
    $stmt->bind_param($types_for_query, ...$params_for_query);
}
$stmt->execute();
$recargas = $stmt->get_result();

/** ===== Obtener métodos únicos para el filtro ===== */
$sql_metodos = "SELECT DISTINCT metodo FROM recargas WHERE metodo IS NOT NULL AND metodo != '' ORDER BY metodo";
$result_metodos = $conexion->query($sql_metodos);
$metodos = [];
while ($row = $result_metodos->fetch_assoc()) {
    $metodos[] = $row['metodo'];
}

/** ===== Obtener contadores para estadísticas ===== */
// Total general (sin filtros)
$sql_total_general = $conexion->query("SELECT COUNT(*) as total FROM recargas");
$total_general = $sql_total_general ? (int)$sql_total_general->fetch_assoc()['total'] : 0;

// Recargas pendientes (para el badge del menú y estadísticas)
$sql_pendientes = $conexion->query("SELECT COUNT(*) as total FROM recargas WHERE estado = 'pendiente'");
$total_pendientes = $sql_pendientes ? (int)$sql_pendientes->fetch_assoc()['total'] : 0;

// Recargas aprobadas
$sql_aprobadas = $conexion->query("SELECT COUNT(*) as total FROM recargas WHERE estado = 'aprobada'");
$total_aprobadas = $sql_aprobadas ? (int)$sql_aprobadas->fetch_assoc()['total'] : 0;

// Recargas rechazadas
$sql_rechazadas = $conexion->query("SELECT COUNT(*) as total FROM recargas WHERE estado = 'rechazada'");
$total_rechazadas = $sql_rechazadas ? (int)$sql_rechazadas->fetch_assoc()['total'] : 0;

function isImagePath(string $path): bool {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return in_array($ext, ['jpg','jpeg','png','webp','gif'], true);
}

function getBadgeClass($estado) {
    switch (strtolower($estado)) {
        case 'pendiente': return 'badge-pendiente';
        case 'aprobada': return 'badge-aprobada';
        case 'rechazada': return 'badge-rechazada';
        default: return 'badge-default';
    }
}

$adminName  = $admin['nombre'] ?? 'Administrador';
$adminEmail = $admin['email'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php echo h($page_title); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/panel-shell.css?v=admin-polish-4">
    <style>
        /* Tus estilos CSS originales aquí (los mismos que ya tenías) */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        *{margin:0;padding:0;box-sizing:border-box;font-family:'Inter',sans-serif}

        :root{
            --sidebar-width:280px;
            --header-height:70px;
            --primary-gradient:linear-gradient(135deg,#12aaff,#0de0c9);
            --danger-gradient:linear-gradient(135deg,#ff4757,#ff3838);
            --success-color:#34c759;
            --warning-color:#ffcc00;
            --danger-color:#ff3b30;
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
            font-weight:600;min-width:20px;text-align:center
        }

        /* Main */
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
        .user-menu{display:flex;align-items:center;gap:15px}
        .user-avatar{
            width:45px;height:45px;border-radius:50%;background:var(--primary-gradient);
            display:flex;align-items:center;justify-content:center;color:#0d0f14;font-weight:700;font-size:1.2rem
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

        /* Page cards */
        .card{
            background:rgba(255,255,255,.04);
            border:1px solid rgba(255,255,255,.08);
            border-radius:16px;padding:16px;backdrop-filter:blur(10px);margin-bottom:20px
        }
        .alert{padding:12px 14px;border-radius:12px;margin-bottom:12px;font-weight:700;display:flex;align-items:center;gap:10px}
        .ok{background:rgba(52,199,89,.12);border:1px solid rgba(52,199,89,.35);color:var(--success-color)}
        .err{background:rgba(255,59,48,.12);border:1px solid rgba(255,59,48,.35);color:var(--danger-color)}
        table{width:100%;border-collapse:collapse;border-radius:14px;overflow:hidden}
        thead{background:rgba(255,255,255,.05)}
        th,td{padding:10px 12px;text-align:left;font-size:.92rem}
        th{color:#ccc;font-weight:800;text-transform:uppercase;letter-spacing:.5px;font-size:.82rem}
        td{color:#ddd;vertical-align:top}
        tbody tr:nth-child(even){background:rgba(255,255,255,.02)}
        tbody tr:hover{background:rgba(255,255,255,.05)}
        
        /* Badges */
        .badge{
            display:inline-block;padding:4px 10px;border-radius:999px;font-size:.8rem;font-weight:900;min-width:80px;text-align:center
        }
        .badge-pendiente{background:rgba(255,204,0,.15);color:var(--warning-color)}
        .badge-aprobada{background:rgba(52,199,89,.15);color:var(--success-color)}
        .badge-rechazada{background:rgba(255,59,48,.15);color:var(--danger-color)}
        .badge-default{background:rgba(128,128,128,.15);color:#888}
        
        /* Buttons */
        .btn{
            border:none;border-radius:12px;padding:8px 12px;cursor:pointer;font-weight:900;font-size:.88rem;
            text-decoration:none;display:inline-flex;align-items:center;gap:6px;transition:all 0.3s ease
        }
        .btn-approve{background:linear-gradient(135deg,#12aaff,#0de0c9);color:#0d0f14}
        .btn-reject{background:rgba(255,59,48,.18);border:1px solid rgba(255,59,48,.35);color:#ff3b30}
        .btn-secondary{background:rgba(255,255,255,.1);color:#fff;border:1px solid rgba(255,255,255,.2)}
        .btn-link{color:#12aaff;text-decoration:none;font-weight:800}
        .btn:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(0,0,0,0.2)}
        
        /* Forms */
        .filtros-container{
            display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:12px;margin-bottom:20px
        }
        .filtro-group{display:flex;flex-direction:column}
        .filtro-group label{font-size:.85rem;color:#aaa;margin-bottom:6px;font-weight:600}
        .filtro-group select, .filtro-group input{
            padding:10px 12px;border-radius:10px;border:1px solid rgba(255,255,255,.12);
            background:rgba(0,0,0,.25);color:#fff;outline:none;font-size:.9rem
        }
        .filtro-group input[type="date"]{padding:9px 12px}
        
        .actions{display:flex;gap:8px;flex-wrap:wrap}
        .motivo{margin-top:8px}
        .motivo input{width:100%;padding:10px 12px;border-radius:12px;border:1px solid rgba(255,255,255,.12);background:rgba(0,0,0,.35);color:#fff;outline:none}
        .mini{font-size:.82rem;color:#aaa;margin-top:6px;line-height:1.3}
        .preview{margin-top:8px;display:flex;gap:10px;align-items:center}
        .thumb{width:60px;height:60px;border-radius:12px;overflow:hidden;border:1px solid rgba(255,255,255,.10);background:rgba(0,0,0,.35);display:flex;align-items:center;justify-content:center}
        .thumb img{width:100%;height:100%;object-fit:cover}
        .thumb i{font-size:1.4rem;color:#12aaff}
        .right{white-space:nowrap}
        
        /* Pagination */
        .pagination{
            display:flex;justify-content:center;align-items:center;gap:8px;margin-top:20px;flex-wrap:wrap
        }
        .pagination a, .pagination span{
            padding:8px 12px;border-radius:10px;text-decoration:none;font-weight:600;font-size:.9rem;
            transition:all 0.3s ease
        }
        .pagination a{
            background:rgba(255,255,255,.08);color:#fff;border:1px solid rgba(255,255,255,.15)
        }
        .pagination a:hover{
            background:rgba(255,255,255,.15);transform:translateY(-2px)
        }
        .pagination .current{
            background:var(--primary-gradient);color:#0d0f14;font-weight:800
        }
        .pagination .disabled{
            opacity:.5;cursor:not-allowed
        }
        
        /* Stats */
        .stats-grid{
            display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:12px;margin-bottom:20px
        }
        .stat-card{
            background:rgba(255,255,255,.05);border-radius:12px;padding:15px;border:1px solid rgba(255,255,255,.08)
        }
        .stat-card .label{font-size:.85rem;color:#aaa;margin-bottom:5px}
        .stat-card .value{font-size:1.4rem;font-weight:800;color:#fff}
        .stat-card .subtext{font-size:.75rem;color:#777;margin-top:3px}
        
        /* Tabs */
        .tabs{
            display:flex;gap:2px;background:rgba(255,255,255,.05);border-radius:12px;padding:4px;margin-bottom:20px
        }
        .tab{
            flex:1;padding:12px;text-align:center;border-radius:10px;cursor:pointer;font-weight:600;
            transition:all 0.3s ease;color:#aaa}
        .tab.active{
            background:var(--primary-gradient);color:#0d0f14;box-shadow:0 2px 8px rgba(18,170,255,0.3)
        }
        .tab:hover:not(.active){
            background:rgba(255,255,255,.1);color:#fff
        }
        
        /* Section headers */
        .section-header{
            display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:14px
        }
        .section-header h2{font-weight:900;font-size:1.15rem;color:#fff}
        
        /* Responsive */
        @media (max-width: 768px){
            .filtros-container{grid-template-columns:1fr}
            .actions{flex-direction:column}
            .btn{width:100%;justify-content:center}
            table{display:block;overflow-x:auto}
            th, td{min-width:120px}
        }
    </style>
  <link rel="stylesheet" href="../assets/css/mobile-urgent.css?v=20260612c">
</head>
<body>

<?php renderAdminSidebar($conexion, $currentPage ?? basename($_SERVER['PHP_SELF'])); ?>

<main class="admin-main">
    <header class="admin-header">
        <div class="header-title">
            <h1>Recargas</h1>
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

        <?php if ($success_msg): ?>
            <div class="alert ok"><i class="fas fa-check-circle"></i> <?php echo $success_msg; ?></div>
        <?php endif; ?>
        <?php if ($error_msg): ?>
            <div class="alert err"><i class="fas fa-exclamation-triangle"></i> <?php echo h($error_msg); ?></div>
        <?php endif; ?>

        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="label">Total Recargas</div>
                <div class="value"><?php echo number_format($total_general); ?></div>
                <div class="subtext">Todas las recargas registradas</div>
            </div>
            <div class="stat-card">
                <div class="label">Pendientes</div>
                <div class="value"><?php echo number_format($total_pendientes); ?></div>
                <div class="subtext">Esperando aprobación</div>
            </div>
            <div class="stat-card">
                <div class="label">Aprobadas</div>
                <div class="value"><?php echo number_format($total_aprobadas); ?></div>
                <div class="subtext">Recargas completadas</div>
            </div>
            <div class="stat-card">
                <div class="label">Rechazadas</div>
                <div class="value"><?php echo number_format($total_rechazadas); ?></div>
                <div class="subtext">Recargas no aprobadas</div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card">
            <div class="section-header">
                <h2><i class="fas fa-filter"></i> Filtros</h2>
                <a href="recargas-admin.php" class="btn btn-secondary">
                    <i class="fas fa-sync"></i> Limpiar filtros
                </a>
            </div>
            
            <form method="GET" action="">
                <div class="filtros-container">
                    <div class="filtro-group">
                        <label>Estado</label>
                        <select name="estado">
                            <option value="todos" <?php echo $filtro_estado === 'todos' ? 'selected' : ''; ?>>Todos los estados</option>
                            <option value="pendiente" <?php echo $filtro_estado === 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                            <option value="aprobada" <?php echo $filtro_estado === 'aprobada' ? 'selected' : ''; ?>>Aprobada</option>
                            <option value="rechazada" <?php echo $filtro_estado === 'rechazada' ? 'selected' : ''; ?>>Rechazada</option>
                        </select>
                    </div>
                    
                    <div class="filtro-group">
                        <label>Usuario (ID/Nombre/Email)</label>
                        <input type="text" name="usuario" value="<?php echo h($filtro_usuario); ?>" placeholder="Ej: 123, Juan, email@...">
                    </div>
                    
                    <div class="filtro-group">
                        <label>Método de pago</label>
                        <select name="metodo">
                            <option value="">Todos los métodos</option>
                            <?php foreach ($metodos as $metodo): ?>
                                <option value="<?php echo h($metodo); ?>" <?php echo $filtro_metodo === $metodo ? 'selected' : ''; ?>>
                                    <?php echo h($metodo); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filtro-group">
                        <label>Fecha desde</label>
                        <input type="date" name="fecha_inicio" value="<?php echo h($filtro_fecha_inicio); ?>">
                    </div>
                    
                    <div class="filtro-group">
                        <label>Fecha hasta</label>
                        <input type="date" name="fecha_fin" value="<?php echo h($filtro_fecha_fin); ?>">
                    </div>
                </div>
                
                <div style="margin-top: 15px; display: flex; gap: 10px;">
                    <button type="submit" class="btn" style="flex: 1;">
                        <i class="fas fa-search"></i> Filtrar
                    </button>
                    <button type="button" onclick="window.print()" class="btn btn-secondary">
                        <i class="fas fa-print"></i> Imprimir
                    </button>
                </div>
            </form>
        </div>

        <!-- Historial de recargas -->
        <div class="card">
            <div class="section-header">
                <h2><i class="fas fa-history"></i> Historial de Recargas</h2>
                <div class="mini">
                    Mostrando <?php echo $recargas->num_rows; ?> de <?php echo number_format($total_recargas); ?> recargas
                </div>
            </div>

            <?php if ($recargas && $recargas->num_rows > 0): ?>
                <div style="overflow:auto;">
                    <table>
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Usuario</th>
                            <th>Método</th>
                            <th>Monto</th>
                            <th>Comisión</th>
                            <th>Estado</th>
                            <th>Fecha</th>
                            <th>Comprobante</th>
                            <th>Acciones</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php while ($r = $recargas->fetch_assoc()): ?>
                            <?php
                                $monto = (float)$r['monto'];
                                $comi  = (float)$r['comision'];
                                $bonus = ($monto >= 100) ? ($monto * 0.05) : 0;
                                $neto  = max(0, $monto - $comi + $bonus);
                                $comp  = (string)($r['comprobante_url'] ?? '');
                                $fecha_solicitud = !empty($r['fecha_solicitud']) ? date('d/m/Y H:i', strtotime($r['fecha_solicitud'])) : '-';
                                $fecha_aprobacion = !empty($r['fecha_aprobacion']) ? date('d/m/Y H:i', strtotime($r['fecha_aprobacion'])) : '-';
                            ?>
                            <tr>
                                <td>#<?php echo (int)$r['id']; ?></td>
                                <td>
                                    <div style="font-weight:900;color:#fff;"><?php echo h($r['usuario_nombre']); ?></div>
                                    <div class="mini"><?php echo h($r['usuario_email']); ?></div>
                                    <div class="mini">ID: <?php echo (int)$r['usuario_id']; ?></div>
                                </td>
                                <td><?php echo h($r['metodo']); ?></td>
                                <td>
                                    <div>S/ <?php echo number_format($monto, 2); ?></div>
                                    <?php if ($r['estado'] === 'aprobada'): ?>
                                        <div class="mini" style="color: #0de0c9;">
                                            Neto: S/ <?php echo number_format($neto, 2); ?>
                                            <?php if ($bonus > 0): ?>
                                                <br><span style="color:#777">(+S/ <?php echo number_format($bonus, 2); ?> bonus)</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>S/ <?php echo number_format($comi, 2); ?></td>
                                <td>
                                    <span class="badge <?php echo getBadgeClass($r['estado']); ?>">
                                        <?php echo h($r['estado']); ?>
                                    </span>
                                    <?php if ($r['estado'] !== 'pendiente' && !empty($r['admin_nombre'])): ?>
                                        <div class="mini">
                                            Por: <?php echo h($r['admin_nombre']); ?>
                                            <br><?php echo $r['estado'] === 'aprobada' ? $fecha_aprobacion : $fecha_aprobacion; ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($r['estado'] === 'rechazada' && !empty($r['rechazo_motivo'])): ?>
                                        <div class="mini" style="color: #ff3b30;">
                                            Motivo: <?php echo h($r['rechazo_motivo']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div><?php echo $fecha_solicitud; ?></div>
                                    <?php if ($r['estado'] !== 'pendiente'): ?>
                                        <div class="mini">
                                            <?php echo $r['estado'] === 'aprobada' ? 'Aprobada:' : 'Rechazada:'; ?>
                                            <br><?php echo $fecha_aprobacion; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($comp !== ''): ?>
                                        <a class="btn-link" href="../<?php echo h($comp); ?>" target="_blank" rel="noopener">
                                            <i class="fas fa-file"></i> Ver
                                        </a>

                                        <div class="preview">
                                            <div class="thumb">
                                                <?php if (isImagePath($comp)): ?>
                                                    <img src="../<?php echo h($comp); ?>" alt="Comprobante" loading="lazy">
                                                <?php else: ?>
                                                    <i class="fas fa-file-pdf"></i>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <span class="mini">Sin comprobante</span>
                                    <?php endif; ?>
                                </td>
                                <td class="right">
                                    <div class="actions">
                                        <?php if ($r['estado'] === 'pendiente'): ?>
                                            <!-- Aprobar -->
                                            <form method="post" style="display:inline;">
                                                <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <input type="hidden" name="recarga_id" value="<?php echo (int)$r['id']; ?>">
                                                <button class="btn btn-approve" type="submit">
                                                    <i class="fas fa-check"></i> Aprobar
                                                </button>
                                            </form>

                                            <!-- Rechazar -->
                                            <form method="post" style="display:inline;">
                                                <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                                                <input type="hidden" name="action" value="reject">
                                                <input type="hidden" name="recarga_id" value="<?php echo (int)$r['id']; ?>">
                                                <div class="motivo">
                                                    <input type="text" name="motivo" placeholder="Motivo de rechazo" <?php echo $has_rechazo_motivo ? 'required' : ''; ?>>
                                                </div>
                                                <button class="btn btn-reject" type="submit" style="width:100%; margin-top:8px;">
                                                    <i class="fas fa-times"></i> Rechazar
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="mini">Procesada</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                <i class="fas fa-angle-left"></i> Anterior
                            </a>
                        <?php else: ?>
                            <span class="disabled"><i class="fas fa-angle-double-left"></i></span>
                            <span class="disabled"><i class="fas fa-angle-left"></i> Anterior</span>
                        <?php endif; ?>

                        <?php 
                        // Mostrar números de página
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        if ($start_page > 1) {
                            echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => 1])) . '">1</a>';
                            if ($start_page > 2) echo '<span>...</span>';
                        }
                        
                        for ($i = $start_page; $i <= $end_page; $i++): 
                        ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                               class="<?php echo $i == $page ? 'current' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                                <span>...</span>
                            <?php endif; ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>">
                                <?php echo $total_pages; ?>
                            </a>
                        <?php endif; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                Siguiente <i class="fas fa-angle-right"></i>
                            </a>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>">
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        <?php else: ?>
                            <span class="disabled">Siguiente <i class="fas fa-angle-right"></i></span>
                            <span class="disabled"><i class="fas fa-angle-double-right"></i></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div style="padding:40px 20px; text-align:center; color:#888;">
                    <i class="fas fa-coins" style="font-size:3rem; margin-bottom:15px; opacity:0.3;"></i>
                    <h3>No hay recargas</h3>
                    <p>No se encontraron recargas con los filtros seleccionados.</p>
                </div>
            <?php endif; ?>
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

// Fecha de hoy por defecto en los filtros de fecha
document.addEventListener('DOMContentLoaded', function() {
    const today = new Date().toISOString().split('T')[0];
    const fechaFinInput = document.querySelector('input[name="fecha_fin"]');
    if (fechaFinInput && !fechaFinInput.value) {
        fechaFinInput.value = today;
    }
});
</script>

</body>
</html>

