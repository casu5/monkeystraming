<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/panel-shell.php';

requireRole('vendedor');

$seller = getCurrentUser();
$sellerId = (int)($seller['id'] ?? 0);

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function withdrawTableExists(mysqli $cx, string $table): bool {
    $t = $cx->real_escape_string($table);
    $rs = $cx->query("SHOW TABLES LIKE '$t'");
    return ($rs && $rs->num_rows > 0);
}
function withdrawColExists(mysqli $cx, string $table, string $col): bool {
    $t = $cx->real_escape_string($table);
    $c = $cx->real_escape_string($col);
    $rs = $cx->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
    return ($rs && $rs->num_rows > 0);
}
function ensureWithdrawalsTable(mysqli $cx): void {
    $cx->query("
        CREATE TABLE IF NOT EXISTS vendedor_retiros (
            id INT AUTO_INCREMENT PRIMARY KEY,
            vendedor_id INT NOT NULL,
            monto DECIMAL(10,2) NOT NULL,
            metodo VARCHAR(50) NOT NULL,
            cuenta_destino VARCHAR(190) NOT NULL,
            estado ENUM('pendiente','aprobado','rechazado') NOT NULL DEFAULT 'pendiente',
            nota TEXT NULL,
            admin_nota TEXT NULL,
            comprobante_url VARCHAR(255) NULL,
            comprobante_subido_en DATETIME NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            revisado_en DATETIME NULL,
            KEY idx_vendedor_retiros_vendedor (vendedor_id),
            KEY idx_vendedor_retiros_estado (estado),
            KEY idx_vendedor_retiros_creado (creado_en)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    if (!withdrawColExists($cx, 'vendedor_retiros', 'comprobante_url')) {
        $cx->query("ALTER TABLE vendedor_retiros ADD COLUMN comprobante_url VARCHAR(255) NULL AFTER admin_nota");
    }
    if (!withdrawColExists($cx, 'vendedor_retiros', 'comprobante_subido_en')) {
        $cx->query("ALTER TABLE vendedor_retiros ADD COLUMN comprobante_subido_en DATETIME NULL AFTER comprobante_url");
    }
}
function sellerBalance(mysqli $cx, int $sellerId): array {
    $earned = 0.00;
    if ($sellerId > 0 && withdrawTableExists($cx, 'compras') && withdrawColExists($cx, 'compras', 'vendedor_id')) {
        $amountCol = withdrawColExists($cx, 'compras', 'monto_vendedor') ? 'monto_vendedor' : 'monto';
        $st = $cx->prepare("SELECT COALESCE(SUM(`$amountCol`),0) s FROM compras WHERE vendedor_id=? AND estado='completada'");
        $st->bind_param("i", $sellerId);
        $st->execute();
        $earned = (float)($st->get_result()->fetch_assoc()['s'] ?? 0);
        $st->close();
    }

    $pending = 0.00;
    $paid = 0.00;
    if ($sellerId > 0 && withdrawTableExists($cx, 'vendedor_retiros')) {
        $st = $cx->prepare("
            SELECT
                COALESCE(SUM(CASE WHEN estado='pendiente' THEN monto ELSE 0 END),0) pendiente,
                COALESCE(SUM(CASE WHEN estado='aprobado' THEN monto ELSE 0 END),0) pagado
            FROM vendedor_retiros
            WHERE vendedor_id=?
        ");
        $st->bind_param("i", $sellerId);
        $st->execute();
        $row = $st->get_result()->fetch_assoc() ?: [];
        $pending = (float)($row['pendiente'] ?? 0);
        $paid = (float)($row['pagado'] ?? 0);
        $st->close();
    }

    return [
        'ganado' => $earned,
        'pendiente' => $pending,
        'retirado' => $paid,
        'disponible' => max(0, $earned - $pending - $paid),
    ];
}
function withdrawBadge(string $state): string {
    $state = strtolower(trim($state));
    if ($state === 'aprobado') return 'ok';
    if ($state === 'rechazado') return 'bad';
    return 'warn';
}
function adminWhatsapp(mysqli $cx): string {
    if (!withdrawTableExists($cx, 'usuarios') || !withdrawColExists($cx, 'usuarios', 'whatsapp')) return '';
    $roleExpr = withdrawColExists($cx, 'usuarios', 'role') ? "role='admin'" : (withdrawColExists($cx, 'usuarios', 'rol') ? "rol='admin'" : "0=1");
    $rs = $cx->query("SELECT whatsapp FROM usuarios WHERE $roleExpr AND whatsapp IS NOT NULL AND whatsapp<>'' ORDER BY id ASC LIMIT 1");
    $row = $rs ? $rs->fetch_assoc() : null;
    return preg_replace('/\D+/', '', (string)($row['whatsapp'] ?? ''));
}
function adminNotifyUrl(string $phone, array $seller, array $request): string { 
    $message = "Hola admin, soy " . ($seller['nombre'] ?? 'vendedor') . ". Acabo de solicitar un retiro #" . (int)$request['id'] . " por S/ " . number_format((float)$request['monto'], 2) . ". Metodo: " . ($request['metodo'] ?? '-') . ". Por favor revisalo.";
    return $phone !== '' ? "https://wa.me/" . rawurlencode($phone) . "?text=" . rawurlencode($message) : '';
}
function cancelPendingWithdrawal(mysqli $cx, int $sellerId, int $requestId): bool {
    if ($sellerId <= 0 || $requestId <= 0) return false;
    $st = $cx->prepare("UPDATE vendedor_retiros SET estado='rechazado', nota=CONCAT(COALESCE(nota,''), CASE WHEN COALESCE(nota,'')='' THEN '' ELSE '\n' END, 'Cancelado por el vendedor') WHERE id=? AND vendedor_id=? AND estado='pendiente'");
    $st->bind_param("ii", $requestId, $sellerId);
    $st->execute();
    $ok = $st->affected_rows > 0;
    $st->close();
    return $ok;
}

ensureWithdrawalsTable($conexion);

if (empty($_SESSION['_csrf_seller_withdraw'])) {
    $_SESSION['_csrf_seller_withdraw'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['_csrf_seller_withdraw'];
$success = '';
$error = '';
$balance = sellerBalance($conexion, $sellerId);
$adminPhone = adminWhatsapp($conexion);
$lastRequestId = 0;
$lastRequestAmount = 0.00;
$lastRequestMethod = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['_csrf'] ?? ''))) {
        $error = 'Token invalido. Recarga la pagina e intenta nuevamente.';
    } elseif (($_POST['accion'] ?? '') === 'cancelar_retiro') {
        $requestId = (int)($_POST['retiro_id'] ?? 0);
        if (cancelPendingWithdrawal($conexion, $sellerId, $requestId)) {
            $success = 'Solicitud de retiro cancelada correctamente.';
            $balance = sellerBalance($conexion, $sellerId);
        } else {
            $error = 'No se pudo cancelar. Solo puedes cancelar retiros pendientes.';
        }
    } else {
        $balance = sellerBalance($conexion, $sellerId);
        $amount = (float)str_replace(',', '.', (string)($_POST['monto'] ?? '0'));
        if (isset($_POST['retirar_todo'])) {
            $amount = (float)$balance['disponible'];
        }
        $amount = round($amount, 2);
        $method = trim((string)($_POST['metodo'] ?? ''));
        $destination = trim((string)($_POST['cuenta_destino'] ?? ''));
        $note = trim((string)($_POST['nota'] ?? ''));
        $allowedMethods = ['Yape', 'Plin', 'Transferencia bancaria', 'Otro'];

        if ($sellerId <= 0) {
            $error = 'No se pudo identificar al vendedor.';
        } elseif ($amount <= 0) {
            $error = 'Ingresa un monto mayor a cero.';
        } elseif ($amount > (float)$balance['disponible']) {
            $error = 'El monto no puede superar tu saldo disponible.';
        } elseif (!in_array($method, $allowedMethods, true)) {
            $error = 'Selecciona un metodo de retiro valido.';
        } elseif ($destination === '' || strlen($destination) < 5) {
            $error = 'Agrega los datos de destino para el pago.';
        } else {
            $st = $conexion->prepare("
                INSERT INTO vendedor_retiros (vendedor_id, monto, metodo, cuenta_destino, nota, estado)
                VALUES (?, ?, ?, ?, ?, 'pendiente')
            ");
            $st->bind_param("idsss", $sellerId, $amount, $method, $destination, $note);
            if ($st->execute()) {
                $lastRequestId = (int)$st->insert_id;
                $lastRequestAmount = $amount;
                $lastRequestMethod = $method;
                $success = 'Solicitud enviada al admin correctamente.';
                $balance = sellerBalance($conexion, $sellerId);
            } else {
                $error = 'No se pudo registrar la solicitud.';
            }
            $st->close();
        }
    }
}

$history = [];
if ($sellerId > 0) {
    $st = $conexion->prepare("SELECT * FROM vendedor_retiros WHERE vendedor_id=? ORDER BY id DESC LIMIT 80");
    $st->bind_param("i", $sellerId);
    $st->execute();
    $rs = $st->get_result();
    while ($rs && ($row = $rs->fetch_assoc())) $history[] = $row;
    $st->close();
}

$page_title = "Retirar saldo - Vendedor";
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo h($page_title); ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/panel-shell.css">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;800&display=swap');
    *{box-sizing:border-box;margin:0;padding:0;font-family:Inter,sans-serif}
    body{min-height:100vh;background:linear-gradient(135deg,#0d0f14,#11131a 45%,#0b0c11);color:#e5e5e5}
    .muted{color:#aaa}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:14px;margin-bottom:18px}
    .layout{display:grid;grid-template-columns:minmax(300px,430px) 1fr;gap:18px;align-items:start}
    .card{background:rgba(255,255,255,.045);border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:18px}
    .stat{display:flex;align-items:center;gap:14px}.icon{width:44px;height:44px;border-radius:12px;display:grid;place-items:center;background:rgba(18,170,255,.14);color:#12aaff}
    .num{font-size:1.45rem;font-weight:900;color:#fff}.title{color:#fff;margin-bottom:8px}.text{line-height:1.5}
    label{display:block;margin:13px 0 6px;color:#ccc;font-weight:800}
    input,select,textarea{width:100%;padding:12px;border-radius:10px;border:1px solid rgba(255,255,255,.13);background:rgba(0,0,0,.28);color:#fff;outline:none}
    textarea{min-height:94px;resize:vertical}.btn{border:0;border-radius:10px;padding:11px 15px;font-weight:900;text-decoration:none;display:inline-flex;gap:8px;align-items:center;justify-content:center;cursor:pointer}
    .primary{background:linear-gradient(135deg,#12aaff,#0de0c9);color:#0d0f14}.secondary{background:rgba(255,255,255,.06);color:#fff;border:1px solid rgba(255,255,255,.12)}
    .actions-row{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px}.alert{padding:12px;border-radius:12px;margin-bottom:14px}
    .ok-alert{background:rgba(52,199,89,.14);border:1px solid rgba(52,199,89,.35);color:#34c759}.err-alert{background:rgba(255,59,48,.14);border:1px solid rgba(255,59,48,.35);color:#ff6b6b}
    table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:12px;border-bottom:1px solid rgba(255,255,255,.07);vertical-align:top}th{color:#9a9a9a;font-size:.84rem;text-transform:uppercase}
    .table-wrap{overflow:auto}.badge{display:inline-flex;padding:5px 9px;border-radius:999px;font-size:.78rem;font-weight:900}.badge.ok{background:rgba(52,199,89,.15);color:#34c759}.badge.warn{background:rgba(255,204,0,.14);color:#ffcc00}.badge.bad{background:rgba(255,59,48,.14);color:#ff6b6b}
    @media(max-width:900px){.layout{grid-template-columns:1fr}.actions-row .btn{width:100%}}
    .voucher-link{display:inline-flex;align-items:center;gap:8px;margin-top:6px;color:#12aaff;text-decoration:none;font-weight:800}.voucher-thumb{width:72px;height:72px;object-fit:cover;border-radius:10px;border:1px solid rgba(255,255,255,.12);margin-top:8px}.mini-actions{display:flex;gap:8px;flex-wrap:wrap}.btn.danger{background:rgba(255,59,48,.14);color:#ff6b6b;border:1px solid rgba(255,59,48,.35)}
  </style>
  <link rel="stylesheet" href="../assets/css/mobile-urgent.css?v=20260610">
</head>
<body>
<?php sellerPanelStart('Retirar saldo', 'Solicita al admin el pago de tus ganancias disponibles.', $seller, 'retirar'); ?>

  <?php if ($success): ?><div class="alert ok-alert"><?php echo h($success); ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert err-alert"><?php echo h($error); ?></div><?php endif; ?>
  <?php if ($lastRequestId > 0): ?>
    <?php
      $notifyRequest = ['id' => $lastRequestId, 'monto' => $lastRequestAmount, 'metodo' => $lastRequestMethod];
      $notifyUrl = adminNotifyUrl($adminPhone, $seller, $notifyRequest);
    ?>
    <div class="alert ok-alert">
      Solicitud creada. <?php if ($notifyUrl): ?><a class="voucher-link" href="<?php echo h($notifyUrl); ?>" target="_blank" rel="noopener"><i class="fab fa-whatsapp"></i> Notificar al admin</a><?php else: ?><span class="muted">Configura el WhatsApp del admin para habilitar la notificacion directa.</span><?php endif; ?>
    </div>
  <?php endif; ?>

  <section class="grid">
    <div class="card stat"><div class="icon"><i class="fas fa-coins"></i></div><div><div class="num">S/ <?php echo number_format($balance['ganado'], 2); ?></div><div class="muted">Ganado</div></div></div>
    <div class="card stat"><div class="icon"><i class="fas fa-wallet"></i></div><div><div class="num">S/ <?php echo number_format($balance['disponible'], 2); ?></div><div class="muted">Disponible</div></div></div>
    <div class="card stat"><div class="icon"><i class="fas fa-clock"></i></div><div><div class="num">S/ <?php echo number_format($balance['pendiente'], 2); ?></div><div class="muted">En revision</div></div></div>
    <div class="card stat"><div class="icon"><i class="fas fa-check-circle"></i></div><div><div class="num">S/ <?php echo number_format($balance['retirado'], 2); ?></div><div class="muted">Retirado</div></div></div>
  </section>

  <section class="layout">
    <form class="card" method="POST">
      <h2 class="title">Nueva solicitud</h2>
      <p class="muted text">Puedes pedir todo tu saldo disponible o una cantidad menor. El admin revisara la solicitud antes de marcarla como pagada.</p>
      <input type="hidden" name="_csrf" value="<?php echo h($csrf); ?>">

      <label>Monto</label>
      <input name="monto" id="withdrawAmount" type="number" step="0.01" min="0.01" max="<?php echo h(number_format($balance['disponible'], 2, '.', '')); ?>" value="<?php echo h(number_format($balance['disponible'], 2, '.', '')); ?>" required>

      <label>Metodo</label>
      <select name="metodo" required>
        <option value="Yape">Yape</option>
        <option value="Plin">Plin</option>
        <option value="Transferencia bancaria">Transferencia bancaria</option>
        <option value="Otro">Otro</option>
      </select>

      <label>Destino del pago</label>
      <textarea name="cuenta_destino" placeholder="Numero, titular, banco o datos necesarios para pagar" required></textarea>

      <label>Nota opcional</label>
      <textarea name="nota" placeholder="Mensaje para el admin"></textarea>

      <div class="actions-row">
        <button class="btn primary" type="submit" <?php echo $balance['disponible'] <= 0 ? 'disabled' : ''; ?>><i class="fas fa-paper-plane"></i> Solicitar retiro</button>
        <button class="btn secondary" type="submit" name="retirar_todo" value="1" <?php echo $balance['disponible'] <= 0 ? 'disabled' : ''; ?>><i class="fas fa-wallet"></i> Retirar todo</button>
      </div>
    </form>

    <div class="card">
      <h2 class="title">Historial de retiros</h2>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Monto</th>
              <th>Metodo</th>
              <th>Estado</th>
              <th>Voucher</th>
              <th>Acciones</th>
              <th>Fecha</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($history as $row): ?>
            <tr>
              <td>#<?php echo (int)$row['id']; ?></td>
              <td>S/ <?php echo number_format((float)$row['monto'], 2); ?></td>
              <td><?php echo h($row['metodo']); ?><br><span class="muted"><?php echo h($row['cuenta_destino']); ?></span></td>
              <td><span class="badge <?php echo withdrawBadge((string)$row['estado']); ?>"><?php echo h($row['estado']); ?></span></td>
              <td>
                <?php if (!empty($row['comprobante_url'])): ?>
                  <a class="voucher-link" href="../<?php echo h($row['comprobante_url']); ?>" target="_blank" rel="noopener"><i class="fas fa-receipt"></i> Ver voucher</a>
                  <br><img class="voucher-thumb" src="../<?php echo h($row['comprobante_url']); ?>" alt="Voucher de pago" loading="lazy">
                <?php else: ?>
                  <span class="muted">Sin voucher</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ((string)$row['estado'] === 'pendiente'): ?>
                  <div class="mini-actions">
                    <?php $notifyUrl = adminNotifyUrl($adminPhone, $seller, $row); ?>
                    <?php if ($notifyUrl): ?>
                      <a class="btn secondary" href="<?php echo h($notifyUrl); ?>" target="_blank" rel="noopener"><i class="fab fa-whatsapp"></i> Notificar</a>
                    <?php endif; ?>
                    <form method="POST" onsubmit="return confirm('¿Cancelar esta solicitud de retiro?');">
                      <input type="hidden" name="_csrf" value="<?php echo h($csrf); ?>">
                      <input type="hidden" name="accion" value="cancelar_retiro">
                      <input type="hidden" name="retiro_id" value="<?php echo (int)$row['id']; ?>">
                      <button class="btn danger" type="submit"><i class="fas fa-ban"></i> Cancelar</button>
                    </form>
                  </div>
                <?php else: ?>
                  <span class="muted">Sin acciones</span>
                <?php endif; ?>
              </td>
              <td><?php echo h(date('d/m/Y H:i', strtotime((string)$row['creado_en']))); ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$history): ?>
            <tr><td colspan="7" class="muted">Todavia no tienes solicitudes de retiro.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>

<?php sellerPanelEnd(); ?>
</body>
</html>
