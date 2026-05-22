<?php
// admin/tickets.php — Soporte: listar, ver, responder y cerrar tickets (robusto con introspección de tablas/columnas)
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/sidebar.php';
require_once __DIR__ . '/../includes/auth.php'; // <-- para helpers de sesión/redirect como en stock.php

/**
 * Protección REAL admin
 */
if (function_exists('requireAdmin')) {
    requireAdmin();
} else {
    if (!function_exists('isLoggedIn') || !function_exists('getCurrentUser')) {
        http_response_code(500);
        die('Faltan helpers de sesión (isLoggedIn/getCurrentUser).');
    }
    if (!isLoggedIn()) redirect('../login.php');

    $u = getCurrentUser();
    $role = strtolower((string)($u['role'] ?? $u['rol'] ?? $u['user_role'] ?? ''));
    if ($role !== 'admin') {
        http_response_code(403);
        die('Acceso denegado: solo administradores.');
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
function pickTable(mysqli $cx, array $candidates): ?string {
    foreach ($candidates as $t) if (tableExists($cx, $t)) return $t;
    return null;
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
function badgeClass(string $estado): string {
    $e = strtolower(trim($estado));
    if (in_array($e, ['cerrado','cerrada','closed','resuelto','resuelta'], true)) return 'b-ok';
    if (in_array($e, ['en proceso','en_proceso','proceso','processing','pendiente'], true)) return 'b-warn';
    if (in_array($e, ['abierto','abierta','open','nuevo','new'], true)) return 'b-pend';
    if (in_array($e, ['rechazado','rechazada','spam'], true)) return 'b-bad';
    return 'b-pend';
}

/** ===== Para sidebar (igual que stock.php) ===== */
if (session_status() === PHP_SESSION_NONE) session_start();

$currentPage = basename($_SERVER['PHP_SELF']);
function navActive(string $file, string $currentPage): string {
    return $currentPage === $file ? 'active' : '';
}

/** ===== Admin data para header (por si luego lo usas) ===== */
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

/** ===== Detectar tablas ===== */
$TABLE_TICKETS = pickTable($conexion, [
    'tickets',
    'tickets_soporte',
    'soporte_tickets',
    'support_tickets',
    'help_tickets',
    'contacto_tickets',
    'mensajes_contacto'
]);

$TABLE_USERS = pickTable($conexion, ['usuarios','users']);
$TABLE_TICKET_MSGS = pickTable($conexion, ['ticket_messages','tickets_mensajes','mensajes_ticket','ticket_respuestas']);
$TABLE_AUTO_MSGS = pickTable($conexion, ['ticket_auto_messages']);

if (!$TABLE_TICKETS) {
    http_response_code(500);
    die("No encuentro una tabla de tickets. Candidatas buscadas: tickets, tickets_soporte, soporte_tickets, support_tickets, help_tickets, mensajes_contacto.");
}

/** ===== Detectar columnas tickets ===== */
$T_ID       = pickCol($conexion, $TABLE_TICKETS, ['id'], 'id');
$T_UID      = pickCol($conexion, $TABLE_TICKETS, ['usuario_id','user_id','id_usuario'], null);
$T_SUBJECT  = pickCol($conexion, $TABLE_TICKETS, ['asunto','subject','titulo','title'], null);
$T_MESSAGE  = pickCol($conexion, $TABLE_TICKETS, ['mensaje','message','descripcion','detalle','contenido','content'], null);
$T_STATUS   = pickCol($conexion, $TABLE_TICKETS, ['estado','status'], null);
$T_PRIORITY = pickCol($conexion, $TABLE_TICKETS, ['prioridad','priority'], null);
$T_CREATED  = pickCol($conexion, $TABLE_TICKETS, ['created_at','fecha','fecha_creacion','fecha_registro','created_on'], null);

// Campos para respuesta/admin
$T_REPLY      = pickCol($conexion, $TABLE_TICKETS, ['respuesta','reply','respuesta_admin','admin_reply','respuesta_texto'], null);
$T_REPLIED_AT = pickCol($conexion, $TABLE_TICKETS, ['respondido_en','replied_at','answered_at','fecha_respuesta'], null);
$T_REPLIED_BY = pickCol($conexion, $TABLE_TICKETS, ['respondido_por','replied_by','answered_by','admin_id'], null);
$T_CLOSED_AT  = pickCol($conexion, $TABLE_TICKETS, ['cerrado_en','closed_at','fecha_cierre'], null);

/** ===== Columnas usuario ===== */
$U_ID    = $TABLE_USERS ? pickCol($conexion, $TABLE_USERS, ['id'], 'id') : null;
$U_NAME  = $TABLE_USERS ? pickCol($conexion, $TABLE_USERS, ['nombre','full_name','name'], null) : null;
$U_EMAIL = $TABLE_USERS ? pickCol($conexion, $TABLE_USERS, ['email'], null) : null;
$U_WHATSAPP = $TABLE_USERS ? pickCol($conexion, $TABLE_USERS, ['whatsapp','telefono','phone','celular'], null) : null;

/** ===== Columnas mensajes de tickets ===== */
if ($TABLE_TICKET_MSGS) {
    $TM_ID   = pickCol($conexion, $TABLE_TICKET_MSGS, ['id'], 'id');
    $TM_TID  = pickCol($conexion, $TABLE_TICKET_MSGS, ['ticket_id'], null);
    $TM_ROLE = pickCol($conexion, $TABLE_TICKET_MSGS, ['sender_role','role','autor_tipo'], null);
    $TM_SID  = pickCol($conexion, $TABLE_TICKET_MSGS, ['sender_id','autor_id','user_id','admin_id'], null);
    $TM_MSG  = pickCol($conexion, $TABLE_TICKET_MSGS, ['mensaje','message','contenido'], null);
    $TM_CA   = pickCol($conexion, $TABLE_TICKET_MSGS, ['creado_en','created_at','fecha'], null);
}

/** ===== Estadísticas para badges del menú (igual que stock.php) ===== */
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
// tickets badge usando la tabla/col detectada
if ($TABLE_TICKETS && $T_STATUS) {
    $openStates = ['abierto','en_proceso','en proceso','pendiente','open','processing','nuevo','new'];
    $placeholders = implode(',', array_fill(0, count($openStates), '?'));
    $sql = "SELECT COUNT(*) c FROM `$TABLE_TICKETS` WHERE `$T_STATUS` IN ($placeholders)";
    $st = $conexion->prepare($sql);
    if ($st) {
        $types = str_repeat('s', count($openStates));
        $st->bind_param($types, ...$openStates);
        $st->execute();
        $estadisticas['tickets_soporte'] = (int)($st->get_result()->fetch_assoc()['c'] ?? 0);
    }
}

/** ===== Ruta ===== */
$viewId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action = trim((string)($_GET['action'] ?? ''));

/** ===== CSRF simple (opcional) ===== */
if (empty($_SESSION['_csrf_admin'])) {
    $_SESSION['_csrf_admin'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['_csrf_admin'];

/** ===== Procesar acciones POST: responder / cerrar / cambiar estado ===== */
$flash_ok = '';
$flash_er = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postId  = (int)($_POST['ticket_id'] ?? 0);
    $do      = (string)($_POST['do'] ?? '');
    $token   = (string)($_POST['_csrf'] ?? '');

    if ($token !== $csrf) {
        $flash_er = 'Token inválido (CSRF). Recarga e intenta otra vez.';
    } elseif ($postId <= 0) {
        $flash_er = 'Ticket inválido.';
    } else {
        // Traer ticket para validar
        $st0 = $conexion->prepare("SELECT `$T_ID` AS id FROM `$TABLE_TICKETS` WHERE `$T_ID` = ? LIMIT 1");
        $st0->bind_param("i", $postId);
        $st0->execute();
        $exists = $st0->get_result()->fetch_assoc();

        if (!$exists) {
            $flash_er = 'El ticket ya no existe.';
        } else {
            $now = date('Y-m-d H:i:s');
            $adminU = function_exists('getCurrentUser') ? getCurrentUser() : [];
            $adminId = (int)($adminU['id'] ?? $adminU['admin_id'] ?? $_SESSION['admin_id'] ?? 0);

            if ($do === 'reply') {
                $replyText = trim((string)($_POST['respuesta'] ?? ''));
                $newStatus = trim((string)($_POST['estado'] ?? ''));

                // Guardar respuesta del admin en la tabla de mensajes si existe
                if ($TABLE_TICKET_MSGS && $TM_TID && $TM_ROLE && $TM_SID && $TM_MSG) {
                    $sqlMsg = "INSERT INTO `$TABLE_TICKET_MSGS` (`$TM_TID`, `$TM_ROLE`, `$TM_SID`, `$TM_MSG`, `$TM_CA`) 
                               VALUES (?, 'ADMIN', ?, ?, NOW())";
                    $stMsg = $conexion->prepare($sqlMsg);
                    $stMsg->bind_param("iis", $postId, $adminId, $replyText);
                    $stMsg->execute();
                    
                    // También intentar enviar mensaje automático de respuesta si hay tabla
                    if ($TABLE_AUTO_MSGS) {
                        $sqlAutoMsg = "SELECT mensaje FROM `$TABLE_AUTO_MSGS` WHERE tipo = 'respuesta' AND activo = 1 LIMIT 1";
                        $resultAuto = $conexion->query($sqlAutoMsg);
                        if ($resultAuto && $row = $resultAuto->fetch_assoc()) {
                            $mensaje_template = $row['mensaje'];
                            // Obtener datos del usuario y ticket para el mensaje
                            $sqlTicketData = "SELECT u.nombre, u.whatsapp, t.$T_ID as ticket_id 
                                              FROM `$TABLE_TICKETS` t 
                                              JOIN `$TABLE_USERS` u ON u.$U_ID = t.$T_UID 
                                              WHERE t.$T_ID = ?";
                            $stData = $conexion->prepare($sqlTicketData);
                            $stData->bind_param("i", $postId);
                            $stData->execute();
                            $ticketData = $stData->get_result()->fetch_assoc();
                            
                            if ($ticketData) {
                                $mensaje_auto = str_replace(
                                    ['{nombre}', '{whatsapp}', '{ticket_id}', '{respuesta}'],
                                    [
                                        htmlspecialchars($ticketData['nombre'] ?? ''),
                                        htmlspecialchars($ticketData['whatsapp'] ?? ''),
                                        $ticketData['ticket_id'],
                                        htmlspecialchars($replyText)
                                    ],
                                    $mensaje_template
                                );
                                
                                // Insertar mensaje automático
                                $sqlAutoInsert = "INSERT INTO `$TABLE_TICKET_MSGS` (`$TM_TID`, `$TM_ROLE`, `$TM_SID`, `$TM_MSG`, `$TM_CA`) 
                                                  VALUES (?, 'SYSTEM', 0, ?, NOW())";
                                $stAuto = $conexion->prepare($sqlAutoInsert);
                                $stAuto->bind_param("is", $postId, $mensaje_auto);
                                $stAuto->execute();
                            }
                        }
                    }
                }

                if ($T_REPLY || $T_STATUS) {
                    $sets = [];
                    $params = [];
                    $types = '';

                    if ($T_REPLY) {
                        $sets[] = "`$T_REPLY` = ?";
                        $params[] = $replyText;
                        $types .= 's';
                    }

                    if ($T_STATUS && $newStatus !== '') {
                        $sets[] = "`$T_STATUS` = ?";
                        $params[] = $newStatus;
                        $types .= 's';
                    } elseif ($T_STATUS) {
                        $sets[] = "`$T_STATUS` = ?";
                        $params[] = 'en_proceso';
                        $types .= 's';
                    }

                    if ($T_REPLIED_AT) {
                        $sets[] = "`$T_REPLIED_AT` = ?";
                        $params[] = $now;
                        $types .= 's';
                    }

                    if ($T_REPLIED_BY) {
                        $sets[] = "`$T_REPLIED_BY` = ?";
                        $params[] = $adminId;
                        $types .= 'i';
                    }

                    if (empty($sets)) {
                        $flash_er = 'No hay columnas para actualizar en tu tabla de tickets.';
                    } else {
                        $sqlU = "UPDATE `$TABLE_TICKETS` SET " . implode(', ', $sets) . " WHERE `$T_ID` = ?";
                        $params[] = $postId;
                        $types .= 'i';

                        $stU = $conexion->prepare($sqlU);
                        $stU->bind_param($types, ...$params);

                        if ($stU->execute()) {
                            $flash_ok = 'Respuesta guardada.';
                            header("Location: tickets.php?action=view&id=" . $postId);
                            exit;
                        } else {
                            $flash_er = 'No se pudo guardar la respuesta.';
                        }
                    }
                } else {
                    $flash_ok = 'Respuesta guardada en mensajes.';
                    header("Location: tickets.php?action=view&id=" . $postId);
                    exit;
                }
            }

            if ($do === 'close') {
                // Enviar mensaje automático de cierre si hay tabla
                if ($TABLE_TICKET_MSGS && $TABLE_AUTO_MSGS) {
                    $sqlAutoMsg = "SELECT mensaje FROM `$TABLE_AUTO_MSGS` WHERE tipo = 'cierre' AND activo = 1 LIMIT 1";
                    $resultAuto = $conexion->query($sqlAutoMsg);
                    if ($resultAuto && $row = $resultAuto->fetch_assoc()) {
                        $mensaje_template = $row['mensaje'];
                        $sqlTicketData = "SELECT u.nombre, u.whatsapp, t.$T_ID as ticket_id 
                                          FROM `$TABLE_TICKETS` t 
                                          JOIN `$TABLE_USERS` u ON u.$U_ID = t.$T_UID 
                                          WHERE t.$T_ID = ?";
                        $stData = $conexion->prepare($sqlTicketData);
                        $stData->bind_param("i", $postId);
                        $stData->execute();
                        $ticketData = $stData->get_result()->fetch_assoc();
                        
                        if ($ticketData) {
                            $mensaje_auto = str_replace(
                                ['{nombre}', '{whatsapp}', '{ticket_id}'],
                                [
                                    htmlspecialchars($ticketData['nombre'] ?? ''),
                                    htmlspecialchars($ticketData['whatsapp'] ?? ''),
                                    $ticketData['ticket_id']
                                ],
                                $mensaje_template
                            );
                            
                            $sqlAutoInsert = "INSERT INTO `$TABLE_TICKET_MSGS` (`$TM_TID`, `$TM_ROLE`, `$TM_SID`, `$TM_MSG`, `$TM_CA`) 
                                              VALUES (?, 'SYSTEM', 0, ?, NOW())";
                            $stAuto = $conexion->prepare($sqlAutoInsert);
                            $stAuto->bind_param("is", $postId, $mensaje_auto);
                            $stAuto->execute();
                        }
                    }
                }

                if (!$T_STATUS && !$T_CLOSED_AT) {
                    $flash_er = 'Tu tabla no tiene columnas para marcar el ticket como cerrado.';
                } else {
                    $sets = [];
                    $params = [];
                    $types = '';

                    if ($T_STATUS) {
                        $sets[] = "`$T_STATUS` = ?";
                        $params[] = 'cerrado';
                        $types .= 's';
                    }
                    if ($T_CLOSED_AT) {
                        $sets[] = "`$T_CLOSED_AT` = ?";
                        $params[] = $now;
                        $types .= 's';
                    }

                    $sqlU = "UPDATE `$TABLE_TICKETS` SET " . implode(', ', $sets) . " WHERE `$T_ID` = ?";
                    $params[] = $postId;
                    $types .= 'i';

                    $stU = $conexion->prepare($sqlU);
                    $stU->bind_param($types, ...$params);

                    if ($stU->execute()) {
                        $flash_ok = 'Ticket cerrado.';
                        header("Location: tickets.php?action=view&id=" . $postId);
                        exit;
                    } else {
                        $flash_er = 'No se pudo cerrar el ticket.';
                    }
                }
            }

            if ($do === 'set_status') {
                $newStatus = trim((string)($_POST['estado'] ?? ''));
                if (!$T_STATUS) {
                    $flash_er = 'Tu tabla no tiene columna estado/status.';
                } elseif ($newStatus === '') {
                    $flash_er = 'Estado inválido.';
                } else {
                    $stU = $conexion->prepare("UPDATE `$TABLE_TICKETS` SET `$T_STATUS`=? WHERE `$T_ID`=?");
                    $stU->bind_param("si", $newStatus, $postId);
                    if ($stU->execute()) {
                        $flash_ok = 'Estado actualizado.';
                        header("Location: tickets.php?action=view&id=" . $postId);
                        exit;
                    } else {
                        $flash_er = 'No se pudo actualizar el estado.';
                    }
                }
            }
        }
    }
}

/** ===== Listado (filtros) ===== */
$q      = trim((string)($_GET['q'] ?? ''));
$status = trim((string)($_GET['status'] ?? 'abierto'));
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 25;
$off    = ($page - 1) * $limit;

$whereParts = ["1=1"];
$params = [];
$types  = '';

if ($T_STATUS && $status !== '' && strtolower($status) !== 'todos') {
    $whereParts[] = "t.`$T_STATUS` = ?";
    $params[] = $status;
    $types .= 's';
}

if ($q !== '') {
    $sub = [];
    $sub[] = "t.`$T_ID` = ?";
    $params[] = (int)$q;
    $types .= 'i';

    if ($T_SUBJECT) {
        $sub[] = "t.`$T_SUBJECT` LIKE ?";
        $params[] = "%$q%";
        $types .= 's';
    }
    if ($T_MESSAGE) {
        $sub[] = "t.`$T_MESSAGE` LIKE ?";
        $params[] = "%$q%";
        $types .= 's';
    }
    if ($TABLE_USERS && $U_NAME) {
        $sub[] = "u.`$U_NAME` LIKE ?";
        $params[] = "%$q%";
        $types .= 's';
    }
    if ($TABLE_USERS && $U_EMAIL) {
        $sub[] = "u.`$U_EMAIL` LIKE ?";
        $params[] = "%$q%";
        $types .= 's';
    }
    if ($TABLE_USERS && $U_WHATSAPP) {
        $sub[] = "u.`$U_WHATSAPP` LIKE ?";
        $params[] = "%$q%";
        $types .= 's';
    }

    $whereParts[] = "(" . implode(" OR ", $sub) . ")";
}

$where = implode(" AND ", $whereParts);

$joins = '';
if ($TABLE_USERS && $U_ID && $T_UID) {
    $joins = "LEFT JOIN `$TABLE_USERS` u ON u.`$U_ID` = t.`$T_UID`";
}

$selectCols = [
    "t.`$T_ID` AS id",
];
if ($T_SUBJECT)  $selectCols[] = "t.`$T_SUBJECT` AS asunto";
if ($T_MESSAGE)  $selectCols[] = "t.`$T_MESSAGE` AS mensaje";
if ($T_STATUS)   $selectCols[] = "t.`$T_STATUS` AS estado";
if ($T_PRIORITY) $selectCols[] = "t.`$T_PRIORITY` AS prioridad";
if ($T_CREATED)  $selectCols[] = "t.`$T_CREATED` AS creado_en";
if ($TABLE_USERS && $U_NAME)   $selectCols[] = "u.`$U_NAME` AS usuario_nombre";
if ($TABLE_USERS && $U_EMAIL)  $selectCols[] = "u.`$U_EMAIL` AS usuario_email";
if ($TABLE_USERS && $U_WHATSAPP) $selectCols[] = "u.`$U_WHATSAPP` AS usuario_whatsapp";

$orderBy = $T_CREATED ? "t.`$T_CREATED` DESC" : "t.`$T_ID` DESC";

$sqlCount = "SELECT COUNT(*) c FROM `$TABLE_TICKETS` t $joins WHERE $where";
$stC = $conexion->prepare($sqlCount);
if ($types !== '') $stC->bind_param($types, ...$params);
$stC->execute();
$total = (int)($stC->get_result()->fetch_assoc()['c'] ?? 0);
$totalPages = max(1, (int)ceil($total / $limit));

$sqlList = "SELECT " . implode(", ", $selectCols) . " FROM `$TABLE_TICKETS` t $joins
WHERE $where
ORDER BY $orderBy
LIMIT $limit OFFSET $off";
$stL = $conexion->prepare($sqlList);
if ($types !== '') $stL->bind_param($types, ...$params);
$stL->execute();
$rsList = $stL->get_result();

/** ===== Vista detalle ===== */
$ticket = null;
$ticketMessages = [];

if ($action === 'view' && $viewId > 0) {
    $cols = ["t.`$T_ID` AS id"];
    if ($T_UID)      $cols[] = "t.`$T_UID` AS usuario_id";
    if ($T_SUBJECT)  $cols[] = "t.`$T_SUBJECT` AS asunto";
    if ($T_MESSAGE)  $cols[] = "t.`$T_MESSAGE` AS mensaje";
    if ($T_STATUS)   $cols[] = "t.`$T_STATUS` AS estado";
    if ($T_PRIORITY) $cols[] = "t.`$T_PRIORITY` AS prioridad";
    if ($T_CREATED)  $cols[] = "t.`$T_CREATED` AS creado_en";
    if ($T_REPLY)    $cols[] = "t.`$T_REPLY` AS respuesta";
    if ($T_REPLIED_AT) $cols[] = "t.`$T_REPLIED_AT` AS respondido_en";
    if ($T_CLOSED_AT)  $cols[] = "t.`$T_CLOSED_AT` AS cerrado_en";

    if ($TABLE_USERS && $U_NAME)  $cols[] = "u.`$U_NAME` AS usuario_nombre";
    if ($TABLE_USERS && $U_EMAIL) $cols[] = "u.`$U_EMAIL` AS usuario_email";
    if ($TABLE_USERS && $U_WHATSAPP) $cols[] = "u.`$U_WHATSAPP` AS usuario_whatsapp";

    $sqlV = "SELECT " . implode(", ", $cols) . " FROM `$TABLE_TICKETS` t $joins WHERE t.`$T_ID` = ? LIMIT 1";
    $stV = $conexion->prepare($sqlV);
    $stV->bind_param("i", $viewId);
    $stV->execute();
    $ticket = $stV->get_result()->fetch_assoc();

    // Obtener mensajes del ticket si existe la tabla
    if ($TABLE_TICKET_MSGS && $ticket) {
        $orderM = $TM_CA ? "`$TM_CA` ASC" : "`$TM_ID` ASC";
        $stM = $conexion->prepare("SELECT * FROM `$TABLE_TICKET_MSGS` WHERE `$TM_TID`=? ORDER BY $orderM");
        $stM->bind_param("i", $viewId);
        $stM->execute();
        $rm = $stM->get_result();
        while($m = $rm->fetch_assoc()) $ticketMessages[] = $m;
    }
}

$page_title = "Tickets - Admin - Monkeystraming";
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
          --whatsapp-green:#25D366;
        }

        body{
          background:linear-gradient(135deg,#0d0f14 0%,#11131a 35%,#0b0c11 100%);
          color:#e5e5e5;min-height:100vh;display:flex;overflow-x:hidden;
        }

        /* ===== Sidebar (copiado de stock.php) ===== */
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

        .admin-main{flex:1;margin-left:var(--sidebar-width);min-height:100vh;width:100%}
        .admin-content{padding:30px}

        .sidebar-toggle{
          display:none;background:none;border:none;color:#fff;font-size:1.5rem;cursor:pointer;position:fixed;top:20px;left:20px;z-index:1001
        }
        @media (max-width: 992px){
          .admin-sidebar{transform:translateX(-100%)}
          .admin-main{margin-left:0}
          .sidebar-toggle{display:block}
        }

        /* ===== Tus estilos originales de tickets (sin tocar la lógica) ===== */
        .topbar{display:flex;gap:15px;align-items:center;justify-content:space-between;flex-wrap:wrap;margin-bottom:18px}
        .title h1{font-size:1.6rem;color:#fff}
        .title p{color:#aaa;margin-top:4px}
        .btn{border:none;cursor:pointer;border-radius:12px;padding:10px 14px;font-weight:900;background:linear-gradient(135deg,#12aaff,#0de0c9);color:#0d0f14;text-decoration:none;display:inline-flex;gap:8px;align-items:center}
        .btn.secondary{background:rgba(255,255,255,0.06);color:#fff;border:1px solid rgba(255,255,255,0.10)}
        .btn.danger{background:rgba(255,59,48,0.12);color:#ff3b30;border:1px solid rgba(255,59,48,0.35)}
        .btn.whatsapp{background:#25D366;color:#fff}
        .card{background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:18px;padding:18px;backdrop-filter:blur(10px)}
        .grid{display:grid;grid-template-columns: 1.1fr 0.9fr; gap:16px; align-items:start}
        @media (max-width: 980px){ .grid{grid-template-columns:1fr} }
        label{display:block;color:#ccc;font-size:0.9rem;margin:10px 0 6px}
        input,select,textarea{width:100%;padding:10px 12px;border-radius:12px;outline:none;color:#fff;background:rgba(0,0,0,0.35);border:1px solid rgba(255,255,255,0.12)}
        textarea{min-height:120px;resize:vertical}
        table{width:100%;border-collapse:collapse}
        th,td{padding:12px 10px;text-align:left;border-bottom:1px solid rgba(255,255,255,0.06);vertical-align:top}
        th{color:#aaa;font-size:0.85rem;text-transform:uppercase;letter-spacing:0.5px}
        .muted{color:#777}
        .badge{padding:5px 10px;border-radius:999px;font-size:0.8rem;font-weight:900;display:inline-block}
        .b-ok{background:rgba(52,199,89,0.18);color:#34c759}
        .b-warn{background:rgba(255,204,0,0.18);color:#ffcc00}
        .b-pend{background:rgba(18,170,255,0.16);color:#12aaff}
        .b-bad{background:rgba(255,59,48,0.18);color:#ff3b30}
        .alert{padding:12px 14px;border-radius:14px;margin-bottom:12px;border:1px solid rgba(255,255,255,0.08);background:rgba(0,0,0,0.25)}
        .alert.ok{border-color:rgba(52,199,89,0.35);background:rgba(52,199,89,0.10);color:#34c759}
        .alert.er{border-color:rgba(255,59,48,0.35);background:rgba(255,59,48,0.10);color:#ff3b30}
        .pill{display:inline-flex;gap:8px;align-items:center;padding:8px 10px;border-radius:999px;border:1px solid rgba(255,255,255,0.10);background:rgba(255,255,255,0.05)}
        .pager{display:flex;gap:10px;align-items:center;justify-content:flex-end;margin-top:12px;flex-wrap:wrap}
        .pager a{padding:8px 12px;border-radius:12px;border:1px solid rgba(255,255,255,0.10);background:rgba(255,255,255,0.06);color:#fff;text-decoration:none}
        .pager .current{color:#12aaff;font-weight:900}
        .msgBox{white-space:pre-wrap;line-height:1.55;background:rgba(0,0,0,0.28);border:1px solid rgba(255,255,255,0.10);border-radius:14px;padding:14px}
        .rowBtns{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}
        
        /* Nuevos estilos para WhatsApp */
        .whatsapp-info {
            background: rgba(37, 211, 102, 0.1);
            border: 1px solid rgba(37, 211, 102, 0.3);
            border-radius: 10px;
            padding: 12px 15px;
            margin: 10px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .whatsapp-info i {
            color: #25D366;
            font-size: 1.4rem;
        }
        
        .whatsapp-info-content {
            flex: 1;
        }
        
        .whatsapp-info strong {
            color: #25D366;
            display: block;
            margin-bottom: 3px;
        }
        
        .whatsapp-info span {
            color: #c9c9c9;
            font-size: 0.95rem;
        }
        
        .whatsapp-contact-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #25D366;
            color: white;
            text-decoration: none;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .whatsapp-contact-btn:hover {
            background: #1DA851;
            transform: translateY(-1px);
        }
        
        .chat-container {
            max-height: 400px;
            overflow-y: auto;
            margin: 15px 0;
            padding-right: 5px;
        }
        
        .chat-message {
            padding: 10px 12px;
            border-radius: 14px;
            margin-bottom: 8px;
            border: 1px solid rgba(255,255,255,0.10);
        }
        
        .chat-message.user {
            background: rgba(18, 170, 255, 0.1);
            border-color: rgba(18, 170, 255, 0.25);
        }
        
        .chat-message.admin {
            background: rgba(52, 199, 89, 0.1);
            border-color: rgba(52, 199, 89, 0.25);
        }
        
        .chat-message.system {
            background: rgba(255, 193, 7, 0.1);
            border-color: rgba(255, 193, 7, 0.3);
        }
        
        .message-meta {
            display: flex;
            justify-content: space-between;
            font-size: 0.8rem;
            color: #888;
            margin-bottom: 4px;
        }
        
        .message-role {
            font-weight: 600;
        }
        
        .message-role.user { color: #12aaff; }
        .message-role.admin { color: #34c759; }
        .message-role.system { color: #ffc107; }
        
        .message-time {
            font-size: 0.75rem;
        }
        
        .message-content {
            white-space: pre-wrap;
            line-height: 1.5;
        }
    </style>
</head>
<body>

<?php renderAdminSidebar($conexion, $currentPage ?? basename($_SERVER['PHP_SELF'])); ?>

<main class="admin-main">
  <div class="admin-content">

    <!-- ===== Tu contenido ORIGINAL de tickets empieza aquí ===== -->
    <div class="topbar">
        <div class="title">
            <h1><i class="fas fa-ticket-alt"></i> Tickets de Soporte</h1>
            <p>Lista, detalle, respuesta y cierre (con consultas preparadas).</p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <a class="btn secondary" href="index.php"><i class="fas fa-arrow-left"></i> Dashboard</a>
            <a class="btn" href="../index.php"><i class="fas fa-globe"></i> Ver sitio</a>
        </div>
    </div>

    <?php if ($flash_ok): ?><div class="alert ok"><i class="fas fa-check-circle"></i> <?php echo h($flash_ok); ?></div><?php endif; ?>
    <?php if ($flash_er): ?><div class="alert er"><i class="fas fa-exclamation-circle"></i> <?php echo h($flash_er); ?></div><?php endif; ?>

    <div class="grid">

        <!-- LISTADO -->
        <div class="card">
            <form method="GET" action="">
                <div style="display:grid;grid-template-columns: 1.5fr 0.8fr auto; gap:10px; align-items:end;">
                    <div>
                        <label>Buscar</label>
                        <input type="text" name="q" value="<?php echo h($q); ?>" placeholder="ID, asunto, usuario, email, WhatsApp...">
                    </div>
                    <div>
                        <label>Estado</label>
                        <select name="status" <?php echo $T_STATUS ? '' : 'disabled'; ?>>
                            <?php
                            $opts = ['abierto','en_proceso','cerrado','todos'];
                            $cur = $status === '' ? 'abierto' : $status;
                            foreach ($opts as $op):
                            ?>
                                <option value="<?php echo h($op); ?>" <?php echo ($cur === $op) ? 'selected' : ''; ?>>
                                    <?php echo h($op); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!$T_STATUS): ?>
                            <div class="muted" style="margin-top:6px;font-size:0.85rem;">(Tu tabla no tiene estado/status)</div>
                        <?php endif; ?>
                    </div>
                    <div style="padding-top:28px;">
                        <button class="btn" type="submit"><i class="fas fa-filter"></i> Filtrar</button>
                    </div>
                </div>
            </form>

            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;justify-content:space-between;margin:14px 0 10px;">
                <span class="pill"><i class="fas fa-database"></i> Tabla: <strong><?php echo h($TABLE_TICKETS); ?></strong></span>
                <span class="muted">Total: <?php echo number_format($total); ?> | Página <?php echo $page; ?>/<?php echo $totalPages; ?></span>
            </div>

            <div style="overflow:auto;">
                <table>
                    <thead>
                        <tr>
                            <th style="width:90px;">ID</th>
                            <th>Asunto / Mensaje</th>
                            <th>Usuario</th>
                            <th>WhatsApp</th>
                            <th style="width:140px;">Estado</th>
                            <th style="width:130px;">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($rsList && $rsList->num_rows > 0): ?>
                        <?php while($t = $rsList->fetch_assoc()): ?>
                            <?php
                                $estado = $T_STATUS ? (string)($t['estado'] ?? '') : '';
                                $asunto = $t['asunto'] ?? ('Ticket #' . (int)$t['id']);
                                $msg    = $t['mensaje'] ?? '';
                                $uName  = $t['usuario_nombre'] ?? '—';
                                $uMail  = $t['usuario_email'] ?? '';
                                $uWhatsapp = $t['usuario_whatsapp'] ?? '';
                            ?>
                            <tr>
                                <td>#<?php echo (int)$t['id']; ?></td>
                                <td>
                                    <div style="font-weight:900;color:#fff;"><?php echo h($asunto); ?></div>
                                    <?php if ($msg): ?>
                                        <div class="muted" style="margin-top:4px;font-size:0.85rem;">
                                            <?php echo h(mb_strimwidth($msg, 0, 120, '…', 'UTF-8')); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-weight:800;"><?php echo h($uName); ?></div>
                                    <?php if ($uMail): ?><div class="muted" style="font-size:0.85rem;"><?php echo h($uMail); ?></div><?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($uWhatsapp)): ?>
                                        <div style="display: flex; align-items: center; gap: 5px;">
                                            <i class="fab fa-whatsapp" style="color: #25D366;"></i>
                                            <span><?php echo h($uWhatsapp); ?></span>
                                        </div>
                                        <div style="margin-top: 5px;">
                                            <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $uWhatsapp); ?>" 
                                               target="_blank" 
                                               style="padding: 3px 8px; background: #25D366; color: white; 
                                                      border-radius: 4px; text-decoration: none; font-size: 0.8rem;">
                                                <i class="fab fa-whatsapp"></i> Contactar
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <span class="muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($T_STATUS): ?>
                                        <span class="badge <?php echo badgeClass($estado); ?>"><?php echo h($estado); ?></span>
                                    <?php else: ?>
                                        <span class="muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a class="btn secondary" style="padding:8px 10px;border-radius:12px;" href="tickets.php?action=view&id=<?php echo (int)$t['id']; ?>">
                                        <i class="fas fa-eye"></i> Ver
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="muted" style="padding:18px;">No hay tickets con esos filtros.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="pager">
                <?php
                $queryBase = http_build_query(['q'=>$q,'status'=>$status,'action'=>'','id'=>'']);
                $prev = max(1, $page - 1);
                $next = min($totalPages, $page + 1);
                ?>
                <a href="tickets.php?<?php echo $queryBase; ?>&page=<?php echo $prev; ?>"><i class="fas fa-chevron-left"></i></a>
                <span class="current">Página <?php echo $page; ?> / <?php echo $totalPages; ?></span>
                <a href="tickets.php?<?php echo $queryBase; ?>&page=<?php echo $next; ?>"><i class="fas fa-chevron-right"></i></a>
            </div>
        </div>

        <!-- DETALLE -->
        <div class="card">
            <?php if ($ticket): ?>
                <?php
                    $estado = $T_STATUS ? (string)($ticket['estado'] ?? '') : '';
                    $asunto = $ticket['asunto'] ?? ('Ticket #' . (int)$ticket['id']);
                    $msg    = $ticket['mensaje'] ?? '';
                    $uName  = $ticket['usuario_nombre'] ?? '—';
                    $uMail  = $ticket['usuario_email'] ?? '';
                    $uWhatsapp = $ticket['usuario_whatsapp'] ?? '';
                    $prio   = $T_PRIORITY ? (string)($ticket['prioridad'] ?? '') : '';
                    $creado = $T_CREATED && !empty($ticket['creado_en']) ? date('d/m/Y H:i', strtotime($ticket['creado_en'])) : '—';
                    $resp   = $T_REPLY ? (string)($ticket['respuesta'] ?? '') : '';
                    $respAt = $T_REPLIED_AT && !empty($ticket['respondido_en']) ? date('d/m/Y H:i', strtotime($ticket['respondido_en'])) : '';
                    $closeAt= $T_CLOSED_AT && !empty($ticket['cerrado_en']) ? date('d/m/Y H:i', strtotime($ticket['cerrado_en'])) : '';
                ?>
                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                    <div>
                        <div class="muted">Ticket #<?php echo (int)$ticket['id']; ?></div>
                        <div style="font-size:1.15rem;font-weight:900;color:#fff;margin-top:3px;"><?php echo h($asunto); ?></div>
                        <div class="muted" style="margin-top:6px;">
                            <i class="fas fa-user"></i> <?php echo h($uName); ?>
                            <?php if ($uMail): ?> — <?php echo h($uMail); ?><?php endif; ?>
                        </div>
                        <div class="muted" style="margin-top:6px;">
                            <i class="fas fa-clock"></i> <?php echo h($creado); ?>
                        </div>
                    </div>

                    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                        <?php if ($T_STATUS): ?>
                            <span class="badge <?php echo badgeClass($estado); ?>"><?php echo h($estado); ?></span>
                        <?php endif; ?>
                        <?php if ($prio !== ''): ?>
                            <span class="pill"><i class="fas fa-bolt"></i> Prioridad: <strong><?php echo h($prio); ?></strong></span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Información de WhatsApp -->
                <?php if (!empty($uWhatsapp)): ?>
                    <div class="whatsapp-info">
                        <i class="fab fa-whatsapp"></i>
                        <div class="whatsapp-info-content">
                            <strong><i class="fas fa-info-circle"></i> Contacto por WhatsApp</strong>
                            <span><?php echo h($uWhatsapp); ?></span>
                        </div>
                        <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $uWhatsapp); ?>" 
                           target="_blank" 
                           class="whatsapp-contact-btn">
                            <i class="fab fa-whatsapp"></i> Contactar
                        </a>
                    </div>
                <?php endif; ?>

                <hr style="border:none;border-top:1px solid rgba(255,255,255,0.08);margin:14px 0;">

                <?php if (!empty($ticketMessages)): ?>
                    <!-- Chat de mensajes -->
                    <div>
                        <div class="muted" style="margin-bottom:8px;"><i class="fas fa-comments"></i> Historial de conversación</div>
                        <div class="chat-container">
                            <?php foreach($ticketMessages as $m):
                                $role = (string)($m[$TM_ROLE] ?? 'USER');
                                $cls = ($role === 'ADMIN') ? 'admin' : ($role === 'SYSTEM' ? 'system' : 'user');
                                $when = $TM_CA ? (string)($m[$TM_CA] ?? '') : '';
                                $whenFmt = $when ? date('d/m/Y H:i', strtotime($when)) : '—';
                                
                                if ($role === 'SYSTEM') {
                                    $sender = 'Sistema';
                                } elseif ($role === 'ADMIN') {
                                    $sender = 'Administrador';
                                } else {
                                    $sender = 'Usuario';
                                }
                            ?>
                                <div class="chat-message <?php echo $cls; ?>">
                                    <div class="message-meta">
                                        <span class="message-role <?php echo $cls; ?>">
                                            <?php if ($role === 'SYSTEM'): ?>
                                                <i class="fas fa-robot"></i>
                                            <?php elseif ($role === 'ADMIN'): ?>
                                                <i class="fas fa-headset"></i>
                                            <?php else: ?>
                                                <i class="fas fa-user"></i>
                                            <?php endif; ?>
                                            <?php echo h($sender); ?>
                                        </span>
                                        <span class="message-time"><?php echo h($whenFmt); ?></span>
                                    </div>
                                    <div class="message-content"><?php echo h($m[$TM_MSG] ?? ''); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Mensaje original del ticket -->
                    <div>
                        <div class="muted" style="margin-bottom:8px;"><i class="fas fa-comment-dots"></i> Mensaje del usuario</div>
                        <div class="msgBox"><?php echo h($msg !== '' ? $msg : '—'); ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($T_REPLY && $resp !== '' && empty($ticketMessages)): ?>
                    <div style="margin-top:14px;">
                        <div class="muted" style="margin-bottom:8px;"><i class="fas fa-reply"></i> Respuesta enviada <?php echo $respAt ? "($respAt)" : ""; ?></div>
                        <div class="msgBox"><?php echo h($resp); ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($closeAt): ?>
                    <div style="margin-top:12px;" class="muted"><i class="fas fa-lock"></i> Cerrado el: <?php echo h($closeAt); ?></div>
                <?php endif; ?>

                <div style="margin-top:16px;">
                    <div class="muted" style="margin-bottom:8px;"><i class="fas fa-pen"></i> Responder / actualizar</div>

                    <form method="POST" action="tickets.php?action=view&id=<?php echo (int)$ticket['id']; ?>">
                        <input type="hidden" name="_csrf" value="<?php echo h($csrf); ?>">
                        <input type="hidden" name="ticket_id" value="<?php echo (int)$ticket['id']; ?>">
                        <input type="hidden" name="do" value="reply">

                        <?php if ($TABLE_TICKET_MSGS): ?>
                            <label>Respuesta</label>
                            <textarea name="respuesta" placeholder="Escribe tu respuesta..." required></textarea>
                        <?php elseif ($T_REPLY): ?>
                            <label>Respuesta</label>
                            <textarea name="respuesta" placeholder="Escribe tu respuesta..."><?php echo h($resp); ?></textarea>
                        <?php else: ?>
                            <div class="alert er" style="margin-top:10px;">
                                Tu tabla no tiene columna de respuesta (respuesta/admin_reply). No podré guardar texto.
                            </div>
                        <?php endif; ?>

                        <?php if ($T_STATUS): ?>
                            <label>Estado</label>
                            <select name="estado">
                                <?php
                                $stOpts = ['abierto','en_proceso','cerrado'];
                                $curSt = $estado !== '' ? $estado : 'abierto';
                                foreach ($stOpts as $op):
                                ?>
                                    <option value="<?php echo h($op); ?>" <?php echo (strtolower($curSt) === strtolower($op)) ? 'selected' : ''; ?>>
                                        <?php echo h($op); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>

                        <div class="rowBtns">
                            <button class="btn" type="submit"><i class="fas fa-save"></i> Guardar</button>
                        </div>
                    </form>

                    <div class="rowBtns" style="margin-top:10px;">
                        <form method="POST" action="tickets.php?action=view&id=<?php echo (int)$ticket['id']; ?>" onsubmit="return confirm('¿Cerrar este ticket?');">
                            <input type="hidden" name="_csrf" value="<?php echo h($csrf); ?>">
                            <input type="hidden" name="ticket_id" value="<?php echo (int)$ticket['id']; ?>">
                            <input type="hidden" name="do" value="close">
                            <button class="btn danger" type="submit"><i class="fas fa-lock"></i> Cerrar ticket</button>
                        </form>

                        <a class="btn secondary" href="tickets.php"><i class="fas fa-list"></i> Volver al listado</a>
                    </div>
                </div>

            <?php else: ?>
                <div class="muted" style="line-height:1.6;">
                    <div style="font-size:1.1rem;font-weight:900;color:#fff;margin-bottom:6px;">
                        <i class="fas fa-info-circle"></i> Selecciona un ticket
                    </div>
                    Haz clic en "Ver" en el listado para abrir el detalle del ticket y poder responder/cerrar.
                </div>
            <?php endif; ?>
        </div>

    </div>
    <!-- ===== Fin contenido tickets ===== -->

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

