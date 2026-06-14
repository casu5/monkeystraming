<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/sidebar.php';

requireRole('admin');

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function adminWithdrawTableExists(mysqli $cx, string $table): bool {
    $t = $cx->real_escape_string($table);
    $rs = $cx->query("SHOW TABLES LIKE '$t'");
    return ($rs && $rs->num_rows > 0);
}
function adminWithdrawColExists(mysqli $cx, string $table, string $col): bool {
    $t = $cx->real_escape_string($table);
    $c = $cx->real_escape_string($col);
    $rs = $cx->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
    return ($rs && $rs->num_rows > 0);
}
function ensureAdminWithdrawalsTable(mysqli $cx): void {
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
    if (!adminWithdrawColExists($cx, 'vendedor_retiros', 'comprobante_url')) {
        $cx->query("ALTER TABLE vendedor_retiros ADD COLUMN comprobante_url VARCHAR(255) NULL AFTER admin_nota");
    }
    if (!adminWithdrawColExists($cx, 'vendedor_retiros', 'comprobante_subido_en')) {
        $cx->query("ALTER TABLE vendedor_retiros ADD COLUMN comprobante_subido_en DATETIME NULL AFTER comprobante_url");
    }
}
function adminWithdrawBadge(string $state): string {
    $state = strtolower(trim($state));
    if ($state === 'aprobado') return 'ok';
    if ($state === 'rechazado') return 'bad';
    return 'warn';
}
function saveWithdrawalVoucher(array $file, int $requestId): string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return '';
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new Exception('No se pudo subir el voucher.');
    }
    if ((int)($file['size'] ?? 0) > 5242880) {
        throw new Exception('El voucher no puede pesar mas de 5MB.');
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp) ?: '';
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($allowed[$mime])) {
        throw new Exception('El voucher debe ser imagen JPG, PNG o WEBP.');
    }

    $dirRel = 'uploads/comprobantes/';
    $dirAbs = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'comprobantes' . DIRECTORY_SEPARATOR;
    if (!is_dir($dirAbs) && !mkdir($dirAbs, 0755, true)) {
        throw new Exception('No se pudo preparar la carpeta de vouchers.');
    }

    $name = 'retiro_' . $requestId . '_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    $destAbs = $dirAbs . $name;
    if (!move_uploaded_file($tmp, $destAbs)) {
        throw new Exception('No se pudo guardar el voucher.');
    }

    return $dirRel . $name;
}

ensureAdminWithdrawalsTable($conexion);

if (empty($_SESSION['_csrf_admin_withdrawals'])) {
    $_SESSION['_csrf_admin_withdrawals'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['_csrf_admin_withdrawals'];
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['_csrf'] ?? ''))) {
        $error = 'Token invalido. Recarga la pagina e intenta nuevamente.';
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $action = (string)($_POST['accion'] ?? '');
        $adminNote = trim((string)($_POST['admin_nota'] ?? ''));

        if ($id <= 0 || !in_array($action, ['aprobar', 'rechazar', 'voucher'], true)) {
            $error = 'Solicitud invalida.';
        } else {
            try {
                $conexion->begin_transaction();
                $st = $conexion->prepare("SELECT id, estado FROM vendedor_retiros WHERE id=? LIMIT 1 FOR UPDATE");
                $st->bind_param("i", $id);
                $st->execute();
                $request = $st->get_result()->fetch_assoc();
                $st->close();

                if (!$request) {
                    throw new Exception('La solicitud no existe.');
                }
                if ($action !== 'voucher' && (string)$request['estado'] !== 'pendiente') {
                    throw new Exception('Esta solicitud ya fue revisada.');
                }

                $voucherUrl = saveWithdrawalVoucher($_FILES['comprobante'] ?? [], $id);
                if ($action === 'voucher') {
                    if ($voucherUrl === '') {
                        throw new Exception('Selecciona una imagen de voucher.');
                    }
                    $st = $conexion->prepare("UPDATE vendedor_retiros SET comprobante_url=?, comprobante_subido_en=NOW(), admin_nota=IF(?='', admin_nota, ?) WHERE id=?");
                    $st->bind_param("sssi", $voucherUrl, $adminNote, $adminNote, $id);
                    $st->execute();
                    $st->close();
                    $success = 'Voucher guardado correctamente.';
                } else {
                    $newState = $action === 'aprobar' ? 'aprobado' : 'rechazado';
                    if ($voucherUrl !== '') {
                        $st = $conexion->prepare("UPDATE vendedor_retiros SET estado=?, admin_nota=?, revisado_en=NOW(), comprobante_url=?, comprobante_subido_en=NOW() WHERE id=?");
                        $st->bind_param("sssi", $newState, $adminNote, $voucherUrl, $id);
                    } else {
                        $st = $conexion->prepare("UPDATE vendedor_retiros SET estado=?, admin_nota=?, revisado_en=NOW() WHERE id=?");
                        $st->bind_param("ssi", $newState, $adminNote, $id);
                    }
                    $st->execute();
                    $st->close();
                    $success = $action === 'aprobar' ? 'Retiro aprobado correctamente.' : 'Retiro rechazado correctamente.';
                }

                $conexion->commit();
            } catch (Throwable $e) {
                try { $conexion->rollback(); } catch (Throwable $x) {}
                $error = 'No se pudo actualizar la solicitud: ' . $e->getMessage();
            }
        }
    }
}

$stats = ['pendiente' => 0.00, 'aprobado' => 0.00, 'rechazado' => 0.00, 'total_pendientes' => 0];
$rs = $conexion->query("
    SELECT estado, COUNT(*) c, COALESCE(SUM(monto),0) s
    FROM vendedor_retiros
    GROUP BY estado
");
while ($rs && ($row = $rs->fetch_assoc())) {
    $state = (string)$row['estado'];
    if (isset($stats[$state])) $stats[$state] = (float)$row['s'];
    if ($state === 'pendiente') $stats['total_pendientes'] = (int)$row['c'];
}

$estado = strtolower(trim((string)($_GET['estado'] ?? 'pendiente')));
if (!in_array($estado, ['pendiente', 'aprobado', 'rechazado', 'todos'], true)) $estado = 'pendiente';

$where = $estado === 'todos' ? '1=1' : 'r.estado=?';
$requests = [];
$sql = "
    SELECT r.*, u.nombre AS vendedor_nombre, u.email AS vendedor_email, u.whatsapp AS vendedor_whatsapp
    FROM vendedor_retiros r
    LEFT JOIN usuarios u ON u.id = r.vendedor_id
    WHERE $where
    ORDER BY FIELD(r.estado, 'pendiente', 'aprobado', 'rechazado'), r.id DESC
    LIMIT 200
";
$st = $conexion->prepare($sql);
if ($estado !== 'todos') $st->bind_param("s", $estado);
$st->execute();
$rs = $st->get_result();
while ($rs && ($row = $rs->fetch_assoc())) $requests[] = $row;
$st->close();

$page_title = "Retiros - Admin";
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo h($page_title); ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/panel-shell.css?v=admin-polish-4">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;800&display=swap');
    *{box-sizing:border-box;margin:0;padding:0;font-family:Inter,sans-serif}
    body{background:linear-gradient(135deg,#0d0f14,#11131a 45%,#0b0c11);color:#e5e5e5;min-height:100vh}
    .wrap{width:calc(100% - var(--sidebar-width));max-width:none;margin:0 0 0 var(--sidebar-width);padding:32px 24px}
    .top{display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:22px}
    h1,h2{color:#fff}.muted{color:#aaa}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:14px;margin-bottom:18px}
    .card{background:rgba(255,255,255,.045);border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:18px}
    .stat{display:flex;align-items:center;gap:14px}.icon{width:44px;height:44px;border-radius:12px;display:grid;place-items:center;background:rgba(18,170,255,.14);color:#12aaff}
    .num{font-size:1.45rem;font-weight:900;color:#fff}.toolbar{display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:14px}
    select,textarea,input[type=file]{padding:10px 12px;border-radius:10px;border:1px solid rgba(255,255,255,.13);background:#151821;color:#fff}
    textarea{width:100%;min-height:68px;resize:vertical;margin-top:8px}.btn{border:0;border-radius:10px;padding:10px 13px;font-weight:900;text-decoration:none;display:inline-flex;gap:8px;align-items:center;justify-content:center;cursor:pointer}
    .primary{background:linear-gradient(135deg,#12aaff,#0de0c9);color:#0d0f14}.danger{background:rgba(255,59,48,.14);color:#ff6b6b;border:1px solid rgba(255,59,48,.35)}.secondary{background:rgba(255,255,255,.06);color:#fff;border:1px solid rgba(255,255,255,.12)}
    .alert{padding:12px;border-radius:12px;margin-bottom:14px}.ok-alert{background:rgba(52,199,89,.14);border:1px solid rgba(52,199,89,.35);color:#34c759}.err-alert{background:rgba(255,59,48,.14);border:1px solid rgba(255,59,48,.35);color:#ff6b6b}
    table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:12px;border-bottom:1px solid rgba(255,255,255,.07);vertical-align:top}th{color:#9a9a9a;font-size:.84rem;text-transform:uppercase}
    .table-wrap{overflow:auto}.badge{display:inline-flex;padding:5px 9px;border-radius:999px;font-size:.78rem;font-weight:900}.badge.ok{background:rgba(52,199,89,.15);color:#34c759}.badge.warn{background:rgba(255,204,0,.14);color:#ffcc00}.badge.bad{background:rgba(255,59,48,.14);color:#ff6b6b}
    .review-actions{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:8px}.review-actions textarea{grid-column:1/-1}
    .review-actions input[type=file]{grid-column:1/-1;width:100%}.voucher-link{display:inline-flex;align-items:center;gap:8px;color:#12aaff;text-decoration:none;font-weight:800}.voucher-thumb{width:78px;height:78px;object-fit:cover;border-radius:10px;border:1px solid rgba(255,255,255,.12);margin-top:8px}.voucher-form{display:grid;gap:8px;margin-top:10px}
    @media(max-width:992px){.wrap{width:100%;margin-left:0;padding:82px 16px 24px}}
    @media(max-width:760px){.review-actions{grid-template-columns:1fr}th:nth-child(4),td:nth-child(4){display:none}}
  </style>
  <link rel="stylesheet" href="../assets/css/mobile-urgent.css?v=20260612c">
</head>
<body>
<?php renderAdminSidebar($conexion, 'retiros.php'); ?>
<main class="wrap">
  <section class="top">
    <div>
      <h1>Retiros de vendedores</h1>
      <p class="muted">Revisa, aprueba o rechaza las solicitudes de pago.</p>
    </div>
    <a class="btn secondary" href="vendedores.php"><i class="fas fa-user-tie"></i> Ver vendedores</a>
  </section>

  <?php if ($success): ?><div class="alert ok-alert"><?php echo h($success); ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert err-alert"><?php echo h($error); ?></div><?php endif; ?>

  <section class="grid">
    <div class="card stat"><div class="icon"><i class="fas fa-clock"></i></div><div><div class="num"><?php echo (int)$stats['total_pendientes']; ?></div><div class="muted">Solicitudes pendientes</div></div></div>
    <div class="card stat"><div class="icon"><i class="fas fa-wallet"></i></div><div><div class="num">S/ <?php echo number_format($stats['pendiente'], 2); ?></div><div class="muted">Monto pendiente</div></div></div>
    <div class="card stat"><div class="icon"><i class="fas fa-check-circle"></i></div><div><div class="num">S/ <?php echo number_format($stats['aprobado'], 2); ?></div><div class="muted">Aprobado</div></div></div>
    <div class="card stat"><div class="icon"><i class="fas fa-ban"></i></div><div><div class="num">S/ <?php echo number_format($stats['rechazado'], 2); ?></div><div class="muted">Rechazado</div></div></div>
  </section>

  <section class="card">
    <div class="toolbar">
      <h2>Solicitudes</h2>
      <form method="GET">
        <select name="estado" onchange="this.form.submit()">
          <option value="pendiente" <?php echo $estado === 'pendiente' ? 'selected' : ''; ?>>Pendientes</option>
          <option value="aprobado" <?php echo $estado === 'aprobado' ? 'selected' : ''; ?>>Aprobadas</option>
          <option value="rechazado" <?php echo $estado === 'rechazado' ? 'selected' : ''; ?>>Rechazadas</option>
          <option value="todos" <?php echo $estado === 'todos' ? 'selected' : ''; ?>>Todas</option>
        </select>
      </form>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Vendedor</th>
            <th>Monto</th>
            <th>Destino</th>
            <th>Estado</th>
            <th>Voucher</th>
            <th>Revision</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($requests as $row): ?>
          <tr>
            <td>#<?php echo (int)$row['id']; ?><br><span class="muted"><?php echo h(date('d/m/Y H:i', strtotime((string)$row['creado_en']))); ?></span></td>
            <td>
              <strong><?php echo h($row['vendedor_nombre'] ?? 'Vendedor'); ?></strong><br>
              <span class="muted"><?php echo h($row['vendedor_email'] ?? ''); ?></span><br>
              <span class="muted"><?php echo h($row['vendedor_whatsapp'] ?? ''); ?></span>
            </td>
            <td><strong>S/ <?php echo number_format((float)$row['monto'], 2); ?></strong></td>
            <td>
              <?php echo h($row['metodo']); ?><br>
              <span class="muted"><?php echo h($row['cuenta_destino']); ?></span>
              <?php if (!empty($row['nota'])): ?><br><span class="muted">Nota: <?php echo h($row['nota']); ?></span><?php endif; ?>
            </td>
            <td><span class="badge <?php echo adminWithdrawBadge((string)$row['estado']); ?>"><?php echo h($row['estado']); ?></span></td>
            <td>
              <?php if (!empty($row['comprobante_url'])): ?>
                <a class="voucher-link" href="../<?php echo h($row['comprobante_url']); ?>" target="_blank" rel="noopener"><i class="fas fa-receipt"></i> Ver voucher</a>
                <br><img class="voucher-thumb" src="../<?php echo h($row['comprobante_url']); ?>" alt="Voucher de pago" loading="lazy">
              <?php else: ?>
                <span class="muted">Sin voucher</span>
              <?php endif; ?>
              <?php if ((string)$row['estado'] !== 'pendiente'): ?>
                <form method="POST" enctype="multipart/form-data" class="voucher-form">
                  <input type="hidden" name="_csrf" value="<?php echo h($csrf); ?>">
                  <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                  <input type="file" name="comprobante" accept="image/jpeg,image/png,image/webp" required>
                  <button class="btn secondary" type="submit" name="accion" value="voucher"><i class="fas fa-upload"></i> Subir voucher</button>
                </form>
              <?php endif; ?>
            </td>
            <td>
              <?php if ((string)$row['estado'] === 'pendiente'): ?>
                <form method="POST" enctype="multipart/form-data" class="review-actions">
                  <input type="hidden" name="_csrf" value="<?php echo h($csrf); ?>">
                  <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                  <textarea name="admin_nota" placeholder="Nota interna opcional"></textarea>
                  <input type="file" name="comprobante" accept="image/jpeg,image/png,image/webp">
                  <button class="btn primary" type="submit" name="accion" value="aprobar"><i class="fas fa-check"></i> Aprobar</button>
                  <button class="btn danger" type="submit" name="accion" value="rechazar"><i class="fas fa-xmark"></i> Rechazar</button>
                </form>
              <?php else: ?>
                <span class="muted"><?php echo !empty($row['revisado_en']) ? h(date('d/m/Y H:i', strtotime((string)$row['revisado_en']))) : 'Revisado'; ?></span>
                <?php if (!empty($row['admin_nota'])): ?><br><span class="muted"><?php echo h($row['admin_nota']); ?></span><?php endif; ?>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$requests): ?>
          <tr><td colspan="7" class="muted">No hay solicitudes para este filtro.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>
</body>
</html>
