<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(['cliente', 'admin']);

// Datos actuales del usuario
$usuario = getCurrentUser();
if (!$usuario) {
    header("Location: ../login.php");
    exit();
}

$user_id = (int)$usuario['id'];
$user_whatsapp = $usuario['whatsapp'] ?? '';

function h($v){ 
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); 
}

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
    foreach ($candidates as $t) {
        if (tableExists($cx, $t)) return $t;
    }
    return null;
}

function pickCol(mysqli $cx, string $table, array $candidates, ?string $default=null): ?string {
    foreach ($candidates as $c) {
        if (colExists($cx, $table, $c)) return $c;
    }
    return $default;
}

function normPri($p){
    $p = strtolower(trim((string)$p));
    return in_array($p, ['baja','media','alta'], true) ? $p : 'media';
}

function normEstado($e){
    $e = strtolower(trim((string)$e));
    return in_array($e, ['abierto','en_proceso','cerrado'], true) ? $e : 'abierto';
}

$page_title = "Mis Tickets de Soporte";
$success_msg = '';
$error_msg = '';

/** ===== CSRF ===== */
if (empty($_SESSION['_csrf_user_tickets'])) {
    $_SESSION['_csrf_user_tickets'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['_csrf_user_tickets'];

/** ===== Tablas ===== */
$T_TICKETS = pickTable($conexion, ['tickets','soporte_tickets']);
$T_MSGS    = pickTable($conexion, ['ticket_messages','tickets_mensajes','mensajes_ticket','ticket_respuestas']);
$T_USERS   = pickTable($conexion, ['usuarios','users','clientes']);

if (!$T_TICKETS || !$T_MSGS) {
    $error_msg = "Sistema de tickets no disponible temporalmente.";
}

/** ===== Columnas (tickets) ===== */
if (!$error_msg) {
    $C_T_ID    = pickCol($conexion, $T_TICKETS, ['id'], 'id');
    $C_T_UID   = pickCol($conexion, $T_TICKETS, ['usuario_id','user_id','cliente_id'], null);
    $C_T_SUBJ  = pickCol($conexion, $T_TICKETS, ['asunto','subject','titulo'], null);
    $C_T_PRI   = pickCol($conexion, $T_TICKETS, ['prioridad','priority'], null);
    $C_T_EST   = pickCol($conexion, $T_TICKETS, ['estado','status'], null);
    $C_T_CA    = pickCol($conexion, $T_TICKETS, ['creado_en','created_at','fecha_creacion'], null);
    $C_T_UA    = pickCol($conexion, $T_TICKETS, ['actualizado_en','updated_at'], null);
    $C_T_LRR   = pickCol($conexion, $T_TICKETS, ['last_reply_role'], null);
    $C_T_LRA   = pickCol($conexion, $T_TICKETS, ['last_reply_at'], null);

    if (!$C_T_UID || !$C_T_SUBJ || !$C_T_PRI || !$C_T_EST) {
        $error_msg = "Error en configuración de tickets.";
    }
}

/** ===== Columnas (mensajes) ===== */
if (!$error_msg) {
    $C_M_ID   = pickCol($conexion, $T_MSGS, ['id'], 'id');
    $C_M_TID  = pickCol($conexion, $T_MSGS, ['ticket_id'], null);
    $C_M_ROLE = pickCol($conexion, $T_MSGS, ['sender_role','role','autor_tipo'], null);
    $C_M_SID  = pickCol($conexion, $T_MSGS, ['sender_id','autor_id','user_id','admin_id'], null);
    $C_M_MSG  = pickCol($conexion, $T_MSGS, ['mensaje','message','contenido'], null);
    $C_M_CA   = pickCol($conexion, $T_MSGS, ['creado_en','created_at','fecha'], null);

    if (!$C_M_TID || !$C_M_ROLE || !$C_M_SID || !$C_M_MSG) {
        $error_msg = "Error en configuración de mensajes.";
    }
}

/** ===== Acciones USUARIO ===== */
$viewId = (int)($_GET['view'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['_csrf'] ?? '');
    if ($token !== $csrf) {
        $error_msg = "Token de seguridad inválido.";
    } else {
        $do = (string)($_POST['do'] ?? '');

        // CREAR NUEVO TICKET
        if ($do === 'new_ticket') {
            $asunto = trim((string)($_POST['asunto'] ?? ''));
            $mensaje = trim((string)($_POST['mensaje'] ?? ''));
            $prioridad = normPri($_POST['prioridad'] ?? 'media');

            if (empty($asunto) || empty($mensaje)) {
                $error_msg = "Asunto y mensaje son obligatorios.";
            } elseif (empty($user_whatsapp)) {
                $error_msg = "Debes tener un número de WhatsApp registrado para crear tickets. Actualiza tu perfil primero.";
            } else {
                $conexion->begin_transaction();
                try {
                    // Insertar ticket
                    $sqlTicket = "INSERT INTO `$T_TICKETS` 
                                  (`$C_T_UID`, `$C_T_SUBJ`, `$C_T_PRI`, `$C_T_EST`, `$C_T_CA`) 
                                  VALUES (?, ?, ?, 'abierto', NOW())";
                    $stTicket = $conexion->prepare($sqlTicket);
                    $stTicket->bind_param("iss", $user_id, $asunto, $prioridad);
                    
                    if (!$stTicket->execute()) {
                        throw new Exception("No se pudo crear el ticket.");
                    }
                    
                    $ticket_id = $conexion->insert_id;
                    
                    // Insertar primer mensaje (del usuario)
                    $sqlMsg = "INSERT INTO `$T_MSGS` 
                               (`$C_M_TID`, `$C_M_ROLE`, `$C_M_SID`, `$C_M_MSG`, `$C_M_CA`) 
                               VALUES (?, 'USER', ?, ?, NOW())";
                    $stMsg = $conexion->prepare($sqlMsg);
                    $stMsg->bind_param("iis", $ticket_id, $user_id, $mensaje);
                    
                    if (!$stMsg->execute()) {
                        throw new Exception("No se pudo guardar el mensaje.");
                    }
                    
                    // Insertar mensaje automático del sistema
                    $mensaje_auto = "OK Ticket #$ticket_id creado exitosamente. Hola " . h($usuario['nombre']) . ", nos comunicaremos contigo por WhatsApp (" . h($user_whatsapp) . ") en las próximas 24 horas. Por favor, mantén tu WhatsApp disponible.";
                    
                    $sqlAuto = "INSERT INTO `$T_MSGS` 
                                (`$C_M_TID`, `$C_M_ROLE`, `$C_M_SID`, `$C_M_MSG`, `$C_M_CA`) 
                                VALUES (?, 'ADMIN', 0, ?, NOW())";
                    $stAuto = $conexion->prepare($sqlAuto);
                    $stAuto->bind_param("is", $ticket_id, $mensaje_auto);
                    $stAuto->execute();
                    
                    $conexion->commit();
                    
                    // Redirigir al ticket recién creado
                    header("Location: tickets-usuario.php?view=" . $ticket_id);
                    exit();
                    
                } catch (Exception $e) {
                    $conexion->rollback();
                    $error_msg = $e->getMessage() ?: "Error al crear el ticket.";
                }
            }
        }

        // RESPONDER A TICKET (usuario)
        if ($do === 'reply_user') {
            $tid = (int)($_POST['ticket_id'] ?? 0);
            $msg = trim((string)($_POST['mensaje'] ?? ''));

            if ($tid <= 0 || $msg === '') {
                $error_msg = "Mensaje inválido.";
            } else {
                // Verificar que el ticket pertenece al usuario
                $stCheck = $conexion->prepare("SELECT `$C_T_ID`, `$C_T_EST` FROM `$T_TICKETS` 
                                               WHERE `$C_T_ID`=? AND `$C_T_UID`=? LIMIT 1");
                $stCheck->bind_param("ii", $tid, $user_id);
                $stCheck->execute();
                $ticket = $stCheck->get_result()->fetch_assoc();
                
                if (!$ticket) {
                    $error_msg = "Ticket no encontrado o no tienes permiso.";
                } elseif (normEstado($ticket[$C_T_EST]) === 'cerrado') {
                    $error_msg = "Este ticket está cerrado.";
                } else {
                    // Insertar respuesta del usuario
                    $sqlReply = "INSERT INTO `$T_MSGS` 
                                 (`$C_M_TID`, `$C_M_ROLE`, `$C_M_SID`, `$C_M_MSG`, `$C_M_CA`) 
                                 VALUES (?, 'USER', ?, ?, NOW())";
                    $stReply = $conexion->prepare($sqlReply);
                    $stReply->bind_param("iis", $tid, $user_id, $msg);
                    
                    if ($stReply->execute()) {
                        // Actualizar última respuesta
                        if ($C_T_LRR) {
                            $conexion->query("UPDATE `$T_TICKETS` 
                                              SET `$C_T_LRR`='USER', `$C_T_LRA`=NOW() 
                                              WHERE `$C_T_ID`=$tid");
                        }
                        
                        // Redirigir para ver la respuesta
                        header("Location: tickets-usuario.php?view=" . $tid);
                        exit();
                    } else {
                        $error_msg = "No se pudo enviar la respuesta.";
                    }
                }
            }
        }
    }
}

/** ===== Listar tickets del USUARIO ACTUAL ===== */
$fEstado = normEstado($_GET['estado'] ?? '');
$where = "t.`$C_T_UID` = ?";
$params = [$user_id];
$types = "i";

if (!empty($_GET['estado'])) { 
    $where .= " AND t.`$C_T_EST`=?"; 
    $params[] = $fEstado; 
    $types .= "s"; 
}

$order = $C_T_UA ? "t.`$C_T_UA` DESC" : ($C_T_CA ? "t.`$C_T_CA` DESC" : "t.`$C_T_ID` DESC");

$sqlList = "SELECT t.* 
            FROM `$T_TICKETS` t
            WHERE $where
            ORDER BY $order
            LIMIT 100";

$stL = $conexion->prepare($sqlList);
if ($types !== '') $stL->bind_param($types, ...$params);
$stL->execute();
$list = $stL->get_result();

/** ===== Ticket actual + mensajes ===== */
$ticketActual = null;
$mensajes = [];

if (!$error_msg && $viewId > 0) {
    // Verificar que el ticket pertenece al usuario
    $stT = $conexion->prepare("SELECT t.* 
                               FROM `$T_TICKETS` t
                               WHERE t.`$C_T_ID`=? AND t.`$C_T_UID`=? LIMIT 1");
    $stT->bind_param("ii", $viewId, $user_id);
    $stT->execute();
    $ticketActual = $stT->get_result()->fetch_assoc();

    if ($ticketActual) {
        $orderM = $C_M_CA ? "`$C_M_CA` ASC" : "`$C_M_ID` ASC";
        $stM = $conexion->prepare("SELECT * FROM `$T_MSGS` WHERE `$C_M_TID`=? ORDER BY $orderM");
        $stM->bind_param("i", $viewId);
        $stM->execute();
        $rm = $stM->get_result();
        while($m = $rm->fetch_assoc()) $mensajes[] = $m;
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo h($page_title); ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
*{margin:0;padding:0;box-sizing:border-box;font-family:'Inter',sans-serif}
body{background:linear-gradient(135deg,#0d0f14 0%,#11131a 35%,#0b0c11 100%);color:#e5e5e5;min-height:100vh;padding:0px}
a{color:#12aaff;text-decoration:none}
a:hover{text-decoration:underline}
h1{font-size:1.6rem;color:#fff}
.card{background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:18px;backdrop-filter:blur(10px)}
.grid{display:grid;grid-template-columns:420px 1fr;gap:14px}
@media(max-width:980px){.grid{grid-template-columns:1fr}}
.left{padding:14px}
.right{padding:14px;min-height:460px}
.alert{padding:12px 14px;border-radius:14px;margin-bottom:12px;border:1px solid rgba(255,255,255,0.08);background:rgba(0,0,0,0.25)}
.alert.ok{border-color:rgba(52,199,89,0.35);background:rgba(52,199,89,0.10);color:#34c759}
.alert.er{border-color:rgba(255,59,48,0.35);background:rgba(255,59,48,0.10);color:#ff3b30}
.muted{color:#8b8b8b;font-size:0.9rem}
.badge{padding:5px 10px;border-radius:999px;font-size:0.78rem;font-weight:900}
.b-abierto{background:rgba(255,204,0,0.14);color:#ffcc00;border:1px solid rgba(255,204,0,0.25)}
.b-en_proceso{background:rgba(18,170,255,0.14);color:#12aaff;border:1px solid rgba(18,170,255,0.25)}
.b-cerrado{background:rgba(255,59,48,0.14);color:#ff3b30;border:1px solid rgba(255,59,48,0.25)}
.list{display:flex;flex-direction:column;gap:10px;max-height:620px;overflow:auto;padding-right:6px}
.item{padding:12px;border-radius:14px;border:1px solid rgba(255,255,255,0.10);background:rgba(255,255,255,0.03)}
.item.active{border-color:rgba(18,170,255,0.5);background:linear-gradient(135deg,rgba(18,170,255,0.15),rgba(13,224,201,0.08))}
.item .top{display:flex;justify-content:space-between;gap:10px;align-items:center}
.btn{border:none;cursor:pointer;border-radius:12px;padding:10px 14px;font-weight:900;display:inline-flex;align-items:center;gap:8px}
.btn.primary{background:linear-gradient(135deg,#12aaff,#0de0c9);color:#0d0f14}
.btn.secondary{background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.12);color:#fff}
.btn.danger{background:rgba(255,59,48,0.12);border:1px solid rgba(255,59,48,0.35);color:#ff3b30}
input,select,textarea{width:100%;padding:10px 12px;border-radius:12px;outline:none;color:#fff;background:rgba(0,0,0,0.35);border:1px solid rgba(255,255,255,0.12)}
textarea{min-height:120px;resize:vertical}
.filters{display:grid;grid-template-columns:1fr;gap:10px;margin-bottom:10px}
.chat{display:flex;flex-direction:column;gap:10px;margin-top:10px}
.msg{padding:10px 12px;border-radius:14px;border:1px solid rgba(255,255,255,0.10);background:rgba(255,255,255,0.03)}
.msg.user{border-color:rgba(18,170,255,0.25)}
.msg.admin{border-color:rgba(52,199,89,0.25)}
.msg.system{background:rgba(255, 193, 7, 0.1);border:1px solid rgba(255, 193, 7, 0.3)}
.msg .meta{display:flex;justify-content:space-between;gap:10px;font-size:0.82rem;margin-bottom:6px}
.msg.user .meta{color:#a7a7a7}
.msg.admin .meta{color:#34c759}
.msg.system .meta{color:#ffc107}
.hr{height:1px;background:rgba(255,255,255,0.08);margin:12px 0}
.new-ticket-form{margin-bottom:20px;padding:15px;border:1px solid rgba(255,255,255,0.1);border-radius:12px;}

/* Estilos para información de WhatsApp */
.whatsapp-info {
    background: rgba(37, 211, 102, 0.1);
    border: 1px solid rgba(37, 211, 102, 0.3);
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.whatsapp-info i {
    color: #25D366;
    font-size: 1.5rem;
}

.whatsapp-info div {
    flex: 1;
}

.whatsapp-info strong {
    color: #25D366;
    display: block;
    margin-bottom: 5px;
}

.whatsapp-info p {
    color: #c9c9c9;
    margin: 0;
    font-size: 0.95rem;
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding: 0 10px;
}

.header-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
</style>
  <link rel="stylesheet" href="../assets/css/mobile-urgent.css?v=20260610">
</head>
<body>
<?php
  include '../includes/header.php';
?>
<div class="header">
  <h1><i class="fas fa-ticket-alt"></i> Mis Tickets de Soporte</h1>
  <div class="header-buttons">
    <a class="btn secondary" href="perfil.php"><i class="fas fa-user"></i> Mi Perfil</a>
    <a class="btn secondary" href="index.php"><i class="fas fa-home"></i> Inicio</a>
  </div>
</div>

<?php if ($success_msg): ?>
    <div class="alert ok"><?php echo h($success_msg); ?></div>
<?php endif; ?>

<?php if ($error_msg): ?>
    <div class="alert er"><?php echo h($error_msg); ?></div>
<?php endif; ?>

<!-- Información de WhatsApp -->
<?php if (!empty($user_whatsapp)): ?>
    <div class="whatsapp-info">
        <i class="fab fa-whatsapp"></i>
        <div>
            <strong><i class="fas fa-info-circle"></i> Soporte por WhatsApp</strong>
            <p>Nos comunicaremos contigo por este número: <strong><?php echo h($user_whatsapp); ?></strong></p>
        </div>
    </div>
<?php else: ?>
    <div class="alert er">
        <i class="fas fa-exclamation-triangle"></i> 
        No tienes un número de WhatsApp registrado. Por favor, actualiza tu perfil para recibir soporte.
        <a href="perfil.php" style="margin-left: 10px; color: #12aaff;">Actualizar perfil</a>
    </div>
<?php endif; ?>

<div class="grid">
  <div class="card left">
    <!-- Formulario NUEVO TICKET -->
    <div class="new-ticket-form">
      <h3 style="margin-bottom:10px;color:#fff;"><i class="fas fa-plus-circle"></i> Crear Nuevo Ticket</h3>
      <form method="POST">
        <input type="hidden" name="_csrf" value="<?php echo h($csrf); ?>">
        <input type="hidden" name="do" value="new_ticket">
        
        <div style="margin-bottom:10px;">
          <label class="muted">Asunto *</label>
          <input type="text" name="asunto" placeholder="¿Cuál es el problema?" required>
        </div>
        
        <div style="margin-bottom:10px;">
          <label class="muted">Prioridad</label>
          <select name="prioridad">
            <option value="baja">Baja</option>
            <option value="media" selected>Media</option>
            <option value="alta">Alta (Urgente)</option>
          </select>
        </div>
        
        <div style="margin-bottom:10px;">
          <label class="muted">Descripción detallada *</label>
          <textarea name="mensaje" placeholder="Describe tu problema con el mayor detalle posible..." required></textarea>
        </div>
        
        <?php if (empty($user_whatsapp)): ?>
            <div style="background: rgba(255,59,48,0.1); padding: 10px; border-radius: 8px; margin-bottom: 15px; border: 1px solid rgba(255,59,48,0.3);">
                <p style="color: #ff3b30; margin: 0; font-size: 0.9rem;">
                    <i class="fas fa-exclamation-circle"></i> 
                    No puedes crear tickets sin un número de WhatsApp. 
                    <a href="perfil.php" style="color: #12aaff;">Actualiza tu perfil primero.</a>
                </p>
            </div>
        <?php endif; ?>
        
        <div style="display:flex;justify-content:flex-end;">
          <button class="btn primary" type="submit" <?php echo empty($user_whatsapp) ? 'disabled' : ''; ?>>
            <i class="fas fa-paper-plane"></i> Crear Ticket
          </button>
        </div>
        
        <p style="color: #888; font-size: 0.85rem; margin-top: 10px;">
            <i class="fas fa-info-circle"></i> Te contactaremos por WhatsApp en las próximas 24 horas.
        </p>
      </form>
    </div>

    <!-- Filtros -->
    <form method="GET" class="filters">
      <div>
        <label class="muted">Filtrar por estado</label>
        <select name="estado" onchange="this.form.submit()">
          <option value="">Todos los tickets</option>
          <option value="abierto" <?php echo (($_GET['estado']??'')==='abierto')?'selected':''; ?>>Abiertos</option>
          <option value="en_proceso" <?php echo (($_GET['estado']??'')==='en_proceso')?'selected':''; ?>>En proceso</option>
          <option value="cerrado" <?php echo (($_GET['estado']??'')==='cerrado')?'selected':''; ?>>Cerrados</option>
        </select>
      </div>
      <div>
        <a class="btn secondary" href="tickets-usuario.php" style="width:100%;text-align:center;">
          <i class="fas fa-broom"></i> Limpiar filtros
        </a>
      </div>
    </form>

    <!-- Lista de tickets -->
    <h3 style="margin:15px 0 10px 0;color:#fff;"><i class="fas fa-list"></i> Mis Tickets</h3>
    <div class="list">
      <?php if (!$error_msg && $list && $list->num_rows>0): ?>
        <?php while($t = $list->fetch_assoc()):
          $tid = (int)$t[$C_T_ID];
          $estado = normEstado($t[$C_T_EST] ?? 'abierto');
          $asunto = $t[$C_T_SUBJ] ?? '';
          $pri    = $t[$C_T_PRI] ?? 'media';
          $fecha  = $C_T_CA ? date('d/m/Y', strtotime($t[$C_T_CA])) : '';
        ?>
          <a class="item <?php echo ($viewId===$tid)?'active':''; ?>" href="tickets-usuario.php?view=<?php echo $tid; ?>">
            <div class="top">
              <div style="font-weight:900;color:#fff;">#<?php echo $tid; ?> · <?php echo h($asunto); ?></div>
              <span class="badge b-<?php echo h($estado); ?>"><?php echo h(str_replace('_',' ', $estado)); ?></span>
            </div>
            <div class="muted" style="margin-top:6px;">
              Prioridad: <strong><?php echo h($pri); ?></strong>
              <?php if ($fecha): ?> · Creado: <?php echo h($fecha); ?><?php endif; ?>
            </div>
          </a>
        <?php endwhile; ?>
      <?php else: ?>
        <div class="muted" style="text-align:center;padding:20px;">
          <i class="fas fa-inbox" style="font-size:2em;margin-bottom:10px;opacity:0.5;"></i><br>
          No tienes tickets <?php echo $fEstado ? 'con ese estado' : 'aún'; ?>.
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="card right">
    <?php if ($error_msg && $error_msg !== "Sistema de tickets no disponible temporalmente."): ?>
      <div class="muted"><?php echo h($error_msg); ?></div>

    <?php elseif ($ticketActual): ?>
      <?php
        $tid = (int)$ticketActual[$C_T_ID];
        $estado = normEstado($ticketActual[$C_T_EST] ?? 'abierto');
        $asunto = (string)($ticketActual[$C_T_SUBJ] ?? '');
        $pri    = (string)($ticketActual[$C_T_PRI] ?? 'media');
        $fecha_creacion = $C_T_CA ? date('d/m/Y H:i', strtotime($ticketActual[$C_T_CA])) : '';
      ?>
      <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:flex-start;">
        <div>
          <div style="font-size:1.1rem;font-weight:900;color:#fff;">Ticket #<?php echo $tid; ?> - <?php echo h($asunto); ?></div>
          <div class="muted">
            Estado: <strong><?php echo h(str_replace('_',' ', $estado)); ?></strong> · 
            Prioridad: <strong><?php echo h($pri); ?></strong>
            <?php if ($fecha_creacion): ?> · Creado: <?php echo h($fecha_creacion); ?><?php endif; ?>
          </div>
        </div>
        <div>
          <a class="btn secondary" href="tickets-usuario.php">
            <i class="fas fa-arrow-left"></i> Volver
          </a>
        </div>
      </div>

      <div class="hr"></div>

      <!-- Chat del ticket -->
      <div class="chat">
        <?php foreach($mensajes as $m):
          $role = (string)($m[$C_M_ROLE] ?? 'USER');
          $cls = ($role === 'ADMIN') ? 'admin' : ($role === 'SYSTEM' ? 'system' : 'user');
          $when = $C_M_CA ? (string)($m[$C_M_CA] ?? '') : '';
          $whenFmt = $when ? date('d/m/Y H:i', strtotime($when)) : '-';
          
          if ($role === 'SYSTEM') {
            $sender = 'Sistema';
            $icon = '<i class="fas fa-robot"></i>';
          } elseif ($role === 'ADMIN') {
            $sender = 'Soporte';
            $icon = '<i class="fas fa-headset"></i>';
          } else {
            $sender = 'Tú';
            $icon = '<i class="fas fa-user"></i>';
          }
        ?>
          <div class="msg <?php echo $cls; ?>">
            <div class="meta">
              <span><strong><?php echo $icon . ' ' . h($sender); ?></strong></span>
              <span><?php echo h($whenFmt); ?></span>
            </div>
            <div style="white-space:pre-wrap;"><?php echo h($m[$C_M_MSG] ?? ''); ?></div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="hr"></div>

      <!-- Formulario de respuesta (solo si está abierto) -->
      <?php if ($estado !== 'cerrado'): ?>
        <form method="POST" action="tickets-usuario.php?view=<?php echo $tid; ?>">
          <input type="hidden" name="_csrf" value="<?php echo h($csrf); ?>">
          <input type="hidden" name="do" value="reply_user">
          <input type="hidden" name="ticket_id" value="<?php echo $tid; ?>">
          <label class="muted" style="display:block;margin-bottom:6px;">Añadir respuesta</label>
          <textarea name="mensaje" placeholder="Escribe tu respuesta o información adicional..." required></textarea>
          <div style="display:flex;justify-content:flex-end;margin-top:10px;gap:10px;">
            <button class="btn primary" type="submit">
              <i class="fas fa-paper-plane"></i> Enviar Respuesta
            </button>
          </div>
        </form>
      <?php else: ?>
        <div class="muted" style="text-align:center;padding:20px;">
          <i class="fas fa-lock" style="font-size:1.5em;margin-bottom:10px;opacity:0.5;"></i><br>
          Este ticket está cerrado. Si necesitas más ayuda, crea un nuevo ticket.
        </div>
      <?php endif; ?>

    <?php elseif ($viewId > 0): ?>
      <div class="muted" style="text-align:center;padding:40px;">
        <i class="fas fa-exclamation-triangle" style="font-size:2em;margin-bottom:10px;color:#ffcc00;"></i><br>
        Ticket no encontrado o no tienes permiso para verlo.
      </div>
    <?php else: ?>
      <div class="muted" style="text-align:center;padding:40px;">
        <i class="fas fa-comments" style="font-size:2em;margin-bottom:10px;opacity:0.5;"></i><br>
        Selecciona un ticket de la lista para ver la conversación,<br>
        o crea un nuevo ticket si necesitas ayuda.
      </div>
    <?php endif; ?>
  </div>
</div>

</body>
</html>
