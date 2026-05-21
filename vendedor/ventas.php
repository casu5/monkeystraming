<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('vendedor');

$seller = getCurrentUser();
$sellerId = (int)($seller['id'] ?? 0);

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function salesTableExists(mysqli $cx, string $table): bool {
    $t = $cx->real_escape_string($table);
    $rs = $cx->query("SHOW TABLES LIKE '$t'");
    return ($rs && $rs->num_rows > 0);
}
function salesColExists(mysqli $cx, string $table, string $col): bool {
    $t = $cx->real_escape_string($table);
    $c = $cx->real_escape_string($col);
    $rs = $cx->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
    return ($rs && $rs->num_rows > 0);
}
function saleStateBadge(string $state): string {
    $state = strtolower(trim($state));
    if ($state === 'completada') return 'ok';
    if ($state === 'cancelada' || $state === 'rechazada') return 'bad';
    return 'warn';
}

$migrationReady = salesTableExists($conexion, 'compras')
    && salesColExists($conexion, 'compras', 'vendedor_id')
    && salesTableExists($conexion, 'productos')
    && salesTableExists($conexion, 'usuarios');

$estado = strtolower(trim((string)($_GET['estado'] ?? 'todos')));
$allowedStates = ['todos', 'completada', 'pendiente', 'cancelada', 'rechazada'];
if (!in_array($estado, $allowedStates, true)) $estado = 'todos';

$stats = [
    'ventas' => 0,
    'ingresos' => 0.00,
    'pendientes' => 0,
    'clientes' => 0,
];
$ventas = [];

if ($migrationReady && $sellerId > 0) {
    $amountCol = salesColExists($conexion, 'compras', 'monto_vendedor') ? 'monto_vendedor' : 'monto';
    $clienteCol = salesColExists($conexion, 'compras', 'cliente_id') ? 'cliente_id' : 'usuario_id';
    $cuentaJoin = salesColExists($conexion, 'compras', 'cuenta_id') && salesTableExists($conexion, 'cuentas');
    $perfilJoin = salesColExists($conexion, 'compras', 'perfil_id') && salesTableExists($conexion, 'cuenta_perfiles');
    $venceCol = salesColExists($conexion, 'compras', 'fecha_vencimiento') ? 'fecha_vencimiento' : null;

    $st = $conexion->prepare("
        SELECT
            COUNT(*) AS ventas,
            COALESCE(SUM(CASE WHEN estado='completada' THEN `$amountCol` ELSE 0 END), 0) AS ingresos,
            SUM(CASE WHEN estado='pendiente' THEN 1 ELSE 0 END) AS pendientes,
            COUNT(DISTINCT `$clienteCol`) AS clientes
        FROM compras
        WHERE vendedor_id=?
    ");
    $st->bind_param("i", $sellerId);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();

    $stats['ventas'] = (int)($row['ventas'] ?? 0);
    $stats['ingresos'] = (float)($row['ingresos'] ?? 0);
    $stats['pendientes'] = (int)($row['pendientes'] ?? 0);
    $stats['clientes'] = (int)($row['clientes'] ?? 0);

    $where = "c.vendedor_id=?";
    $types = "i";
    $params = [$sellerId];
    if ($estado !== 'todos') {
        $where .= " AND c.estado=?";
        $types .= "s";
        $params[] = $estado;
    }

    $selectPerfil = $perfilJoin ? "cp.perfil_nombre" : "NULL AS perfil_nombre";
    $selectCuenta = $cuentaJoin ? "cu.login_user" : "NULL AS login_user";
    $selectVence = $venceCol ? "c.`$venceCol` AS fecha_vencimiento" : "NULL AS fecha_vencimiento";
    $joins = "
        INNER JOIN productos p ON p.id = c.producto_id
        LEFT JOIN usuarios u ON u.id = c.`$clienteCol`
    ";
    if ($cuentaJoin) $joins .= " LEFT JOIN cuentas cu ON cu.id = c.cuenta_id";
    if ($perfilJoin) $joins .= " LEFT JOIN cuenta_perfiles cp ON cp.id = c.perfil_id";

    $sql = "
        SELECT
            c.id,
            c.estado,
            c.monto,
            c.`$amountCol` AS monto_vendedor,
            c.fecha_compra,
            $selectVence,
            p.nombre AS producto_nombre,
            u.nombre AS cliente_nombre,
            u.email AS cliente_email,
            $selectCuenta,
            $selectPerfil
        FROM compras c
        $joins
        WHERE $where
        ORDER BY c.id DESC
        LIMIT 150
    ";
    $st = $conexion->prepare($sql);
    $st->bind_param($types, ...$params);
    $st->execute();
    $rs = $st->get_result();
    while ($rs && ($row = $rs->fetch_assoc())) $ventas[] = $row;
    $st->close();
}

$page_title = "Mis ventas - Vendedor";
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo h($page_title); ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;800&display=swap');
    *{box-sizing:border-box;margin:0;padding:0;font-family:Inter,sans-serif}
    body{min-height:100vh;background:linear-gradient(135deg,#0d0f14,#11131a 45%,#0b0c11);color:#e5e5e5}
    .wrap{max-width:1220px;margin:0 auto;padding:32px 20px}
    .top{display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:22px}
    h1,h2{color:#fff}.muted{color:#aaa}.btn{border:0;border-radius:10px;padding:10px 14px;font-weight:900;text-decoration:none;display:inline-flex;gap:8px;align-items:center;cursor:pointer}
    .primary{background:linear-gradient(135deg,#12aaff,#0de0c9);color:#0d0f14}.secondary{background:rgba(255,255,255,.06);color:#fff;border:1px solid rgba(255,255,255,.12)}
    .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:14px;margin-bottom:18px}
    .card{background:rgba(255,255,255,.045);border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:18px}
    .stat{display:flex;align-items:center;gap:14px}.icon{width:44px;height:44px;border-radius:12px;display:grid;place-items:center;background:rgba(18,170,255,.14);color:#12aaff}
    .num{font-size:1.45rem;font-weight:900;color:#fff}.toolbar{display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:14px}
    select{padding:10px 12px;border-radius:10px;border:1px solid rgba(255,255,255,.13);background:#151821;color:#fff}
    table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:12px;border-bottom:1px solid rgba(255,255,255,.07);vertical-align:top}th{color:#9a9a9a;font-size:.84rem;text-transform:uppercase;letter-spacing:.4px}
    .badge{display:inline-flex;padding:5px 9px;border-radius:999px;font-size:.78rem;font-weight:900}.badge.ok{background:rgba(52,199,89,.15);color:#34c759}.badge.warn{background:rgba(255,204,0,.14);color:#ffcc00}.badge.bad{background:rgba(255,59,48,.14);color:#ff6b6b}
    .alert{padding:12px;border-radius:12px;margin-bottom:14px}.err{background:rgba(255,59,48,.14);border:1px solid rgba(255,59,48,.35);color:#ff6b6b}
    @media(max-width:760px){th:nth-child(4),td:nth-child(4),th:nth-child(5),td:nth-child(5){display:none}}
  </style>
</head>
<body>
<main class="wrap">
  <section class="top">
    <div>
      <h1>Mis ventas</h1>
      <p class="muted">Revisa compras, clientes, productos vendidos y vencimientos.</p>
    </div>
    <div>
      <a class="btn secondary" href="dashboard.php"><i class="fas fa-arrow-left"></i> Panel</a>
      <a class="btn secondary" href="stock.php"><i class="fas fa-key"></i> Stock</a>
    </div>
  </section>

  <?php if (!$migrationReady): ?>
    <div class="alert err">Falta aplicar la migracion marketplace para ver ventas por vendedor.</div>
  <?php endif; ?>

  <section class="grid">
    <div class="card stat"><div class="icon"><i class="fas fa-receipt"></i></div><div><div class="num"><?php echo (int)$stats['ventas']; ?></div><div class="muted">Ventas totales</div></div></div>
    <div class="card stat"><div class="icon"><i class="fas fa-coins"></i></div><div><div class="num">S/ <?php echo number_format($stats['ingresos'], 2); ?></div><div class="muted">Ingresos completados</div></div></div>
    <div class="card stat"><div class="icon"><i class="fas fa-clock"></i></div><div><div class="num"><?php echo (int)$stats['pendientes']; ?></div><div class="muted">Pendientes</div></div></div>
    <div class="card stat"><div class="icon"><i class="fas fa-users"></i></div><div><div class="num"><?php echo (int)$stats['clientes']; ?></div><div class="muted">Clientes</div></div></div>
  </section>

  <section class="card">
    <div class="toolbar">
      <h2>Historial</h2>
      <form method="GET">
        <select name="estado" onchange="this.form.submit()">
          <option value="todos" <?php echo $estado === 'todos' ? 'selected' : ''; ?>>Todos</option>
          <option value="completada" <?php echo $estado === 'completada' ? 'selected' : ''; ?>>Completadas</option>
          <option value="pendiente" <?php echo $estado === 'pendiente' ? 'selected' : ''; ?>>Pendientes</option>
          <option value="cancelada" <?php echo $estado === 'cancelada' ? 'selected' : ''; ?>>Canceladas</option>
          <option value="rechazada" <?php echo $estado === 'rechazada' ? 'selected' : ''; ?>>Rechazadas</option>
        </select>
      </form>
    </div>

    <div style="overflow:auto;">
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Producto</th>
            <th>Cliente</th>
            <th>Cuenta / perfil</th>
            <th>Vence</th>
            <th>Monto</th>
            <th>Estado</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($ventas as $v): ?>
          <tr>
            <td>#<?php echo (int)$v['id']; ?><br><span class="muted"><?php echo h(date('d/m/Y H:i', strtotime((string)$v['fecha_compra']))); ?></span></td>
            <td><strong><?php echo h($v['producto_nombre']); ?></strong></td>
            <td><?php echo h($v['cliente_nombre'] ?? 'Cliente'); ?><br><span class="muted"><?php echo h($v['cliente_email'] ?? ''); ?></span></td>
            <td><?php echo h($v['login_user'] ?? '-'); ?><br><span class="muted"><?php echo h($v['perfil_nombre'] ?? ''); ?></span></td>
            <td><?php echo !empty($v['fecha_vencimiento']) ? h(date('d/m/Y', strtotime((string)$v['fecha_vencimiento']))) : '<span class="muted">-</span>'; ?></td>
            <td>S/ <?php echo number_format((float)$v['monto_vendedor'], 2); ?></td>
            <td><span class="badge <?php echo saleStateBadge((string)$v['estado']); ?>"><?php echo h($v['estado']); ?></span></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$ventas): ?>
          <tr><td colspan="7" class="muted">Aun no hay ventas para este filtro.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>
</body>
</html>
