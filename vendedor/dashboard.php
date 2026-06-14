<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/panel-shell.php';

requireRole('vendedor');

$vendedor = getCurrentUser();
$vendedorId = (int)($vendedor['id'] ?? 0);

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function tableExistsSeller(mysqli $cx, string $table): bool {
    $t = $cx->real_escape_string($table);
    $rs = $cx->query("SHOW TABLES LIKE '$t'");
    return ($rs && $rs->num_rows > 0);
}
function colExistsSeller(mysqli $cx, string $table, string $col): bool {
    $t = $cx->real_escape_string($table);
    $c = $cx->real_escape_string($col);
    $rs = $cx->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
    return ($rs && $rs->num_rows > 0);
}
function ensureSellerWithdrawalsTable(mysqli $cx): void {
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
    if (!colExistsSeller($cx, 'vendedor_retiros', 'comprobante_url')) {
        $cx->query("ALTER TABLE vendedor_retiros ADD COLUMN comprobante_url VARCHAR(255) NULL AFTER admin_nota");
    }
    if (!colExistsSeller($cx, 'vendedor_retiros', 'comprobante_subido_en')) {
        $cx->query("ALTER TABLE vendedor_retiros ADD COLUMN comprobante_subido_en DATETIME NULL AFTER comprobante_url");
    }
}
function dashBadge(string $state): string {
    $state = strtolower(trim($state));
    if ($state === 'completada' || $state === 'aprobado') return 'ok';
    if ($state === 'cancelada' || $state === 'rechazada' || $state === 'rechazado') return 'bad';
    return 'warn';
}

ensureSellerWithdrawalsTable($conexion);

$stats = [
    'productos' => 0,
    'stock' => 0,
    'ventas' => 0,
    'clientes' => 0,
    'ingresos' => 0.00,
    'ingresos_mes' => 0.00,
    'pendiente_retiro' => 0.00,
    'retirado' => 0.00,
    'disponible' => 0.00,
];
$recentSales = [];
$lowStock = [];

if ($vendedorId > 0 && tableExistsSeller($conexion, 'productos') && colExistsSeller($conexion, 'productos', 'vendedor_id')) {
    $st = $conexion->prepare("SELECT COUNT(*) c FROM productos WHERE vendedor_id=?");
    $st->bind_param("i", $vendedorId);
    $st->execute();
    $stats['productos'] = (int)($st->get_result()->fetch_assoc()['c'] ?? 0);
    $st->close();

    if (colExistsSeller($conexion, 'productos', 'stock')) {
        $st = $conexion->prepare("SELECT id, nombre, stock FROM productos WHERE vendedor_id=? ORDER BY stock ASC, id DESC LIMIT 5");
        $st->bind_param("i", $vendedorId);
        $st->execute();
        $rs = $st->get_result();
        while ($rs && ($row = $rs->fetch_assoc())) $lowStock[] = $row;
        $st->close();
    }
}

if ($vendedorId > 0 && tableExistsSeller($conexion, 'cuentas') && tableExistsSeller($conexion, 'cuenta_perfiles') && colExistsSeller($conexion, 'cuentas', 'vendedor_id')) {
    $st = $conexion->prepare("
        SELECT COUNT(*) c
        FROM cuenta_perfiles cp
        INNER JOIN cuentas cta ON cta.id = cp.cuenta_id
        WHERE cta.vendedor_id=? AND cta.estado='DISPONIBLE' AND cp.estado='DISPONIBLE'
    ");
    $st->bind_param("i", $vendedorId);
    $st->execute();
    $stats['stock'] = (int)($st->get_result()->fetch_assoc()['c'] ?? 0);
    $st->close();
}

if ($vendedorId > 0 && tableExistsSeller($conexion, 'compras') && colExistsSeller($conexion, 'compras', 'vendedor_id')) {
    $amountCol = colExistsSeller($conexion, 'compras', 'monto_vendedor') ? 'monto_vendedor' : 'monto';
    $clienteCol = colExistsSeller($conexion, 'compras', 'cliente_id') ? 'cliente_id' : 'usuario_id';
    $dateCol = colExistsSeller($conexion, 'compras', 'fecha_compra') ? 'fecha_compra' : (colExistsSeller($conexion, 'compras', 'created_at') ? 'created_at' : null);
    $monthExpr = $dateCol
        ? "COALESCE(SUM(CASE WHEN estado='completada' AND `$dateCol` >= DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN `$amountCol` ELSE 0 END),0)"
        : "0";

    $st = $conexion->prepare("
        SELECT
            COUNT(*) c,
            COUNT(DISTINCT `$clienteCol`) clientes,
            COALESCE(SUM(CASE WHEN estado='completada' THEN `$amountCol` ELSE 0 END),0) ingresos,
            $monthExpr ingresos_mes
        FROM compras
        WHERE vendedor_id=?
    ");
    $st->bind_param("i", $vendedorId);
    $st->execute();
    $row = $st->get_result()->fetch_assoc() ?: [];
    $stats['ventas'] = (int)($row['c'] ?? 0);
    $stats['clientes'] = (int)($row['clientes'] ?? 0);
    $stats['ingresos'] = (float)($row['ingresos'] ?? 0);
    $stats['ingresos_mes'] = (float)($row['ingresos_mes'] ?? 0);
    $st->close();

    if (tableExistsSeller($conexion, 'productos') && tableExistsSeller($conexion, 'usuarios')) {
        $selectDate = $dateCol ? "c.`$dateCol` AS fecha" : "NULL AS fecha";
        $st = $conexion->prepare("
            SELECT c.id, c.estado, c.`$amountCol` AS monto_vendedor, $selectDate,
                   p.nombre AS producto_nombre, u.nombre AS cliente_nombre
            FROM compras c
            LEFT JOIN productos p ON p.id = c.producto_id
            LEFT JOIN usuarios u ON u.id = c.`$clienteCol`
            WHERE c.vendedor_id=?
            ORDER BY c.id DESC
            LIMIT 6
        ");
        $st->bind_param("i", $vendedorId);
        $st->execute();
        $rs = $st->get_result();
        while ($rs && ($row = $rs->fetch_assoc())) $recentSales[] = $row;
        $st->close();
    }
}

if ($vendedorId > 0 && tableExistsSeller($conexion, 'vendedor_retiros')) {
    $st = $conexion->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN estado='pendiente' THEN monto ELSE 0 END),0) pendiente,
            COALESCE(SUM(CASE WHEN estado='aprobado' THEN monto ELSE 0 END),0) retirado
        FROM vendedor_retiros
        WHERE vendedor_id=?
    ");
    $st->bind_param("i", $vendedorId);
    $st->execute();
    $row = $st->get_result()->fetch_assoc() ?: [];
    $stats['pendiente_retiro'] = (float)($row['pendiente'] ?? 0);
    $stats['retirado'] = (float)($row['retirado'] ?? 0);
    $st->close();
}
$stats['disponible'] = max(0, $stats['ingresos'] - $stats['pendiente_retiro'] - $stats['retirado']);

$page_title = "Panel vendedor - Monkeystraming";
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
    .muted{color:#9a9a9a}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-bottom:18px}
    .card{background:rgba(255,255,255,.045);border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:20px}
    .hero{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:18px;align-items:center;margin-bottom:18px;background:linear-gradient(135deg,rgba(18,170,255,.14),rgba(13,224,201,.08));border-color:rgba(18,170,255,.18)}
    .hero h2{font-size:1.6rem;color:#fff;margin-bottom:7px}.hero p{line-height:1.5}.hero-balance{text-align:right}.hero-balance .money{font-size:2rem;font-weight:900;color:#fff}
    .stat{display:flex;align-items:center;gap:15px}.icon{width:48px;height:48px;border-radius:12px;display:grid;place-items:center;background:rgba(18,170,255,.14);color:#12aaff;font-size:1.35rem}
    .num{font-size:1.55rem;font-weight:900;color:#fff;margin-bottom:3px}.actions{display:grid;grid-template-columns:repeat(auto-fit,minmax(235px,1fr));gap:16px;margin-bottom:18px}
    .action h3,.section-title h2{color:#fff;margin-bottom:8px}.action p{color:#aaa;line-height:1.45;margin-bottom:16px}.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:11px 15px;border-radius:10px;text-decoration:none;font-weight:900;border:1px solid rgba(255,255,255,.12);color:#fff;background:rgba(255,255,255,.06)}
    .btn.primary{background:linear-gradient(135deg,#12aaff,#0de0c9);color:#0d0f14;border:0}.two-col{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(260px,.8fr);gap:18px;align-items:start}
    .section-title{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:12px}.table-wrap{overflow:auto}
    table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:12px;border-bottom:1px solid rgba(255,255,255,.07);vertical-align:top}th{color:#9a9a9a;font-size:.84rem;text-transform:uppercase}
    .badge{display:inline-flex;padding:5px 9px;border-radius:999px;font-size:.78rem;font-weight:900}.badge.ok{background:rgba(52,199,89,.15);color:#34c759}.badge.warn{background:rgba(255,204,0,.14);color:#ffcc00}.badge.bad{background:rgba(255,59,48,.14);color:#ff6b6b}
    .stock-list{display:grid;gap:10px}.stock-item{display:flex;justify-content:space-between;gap:12px;padding:12px;border-radius:12px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.06)}
    @media(max-width:900px){.hero,.two-col{grid-template-columns:1fr}.hero-balance{text-align:left}.hero-balance .money{font-size:1.7rem}}
  </style>
  <link rel="stylesheet" href="../assets/css/mobile-urgent.css?v=20260610k">
</head>
<body>
  <?php sellerPanelStart('Panel vendedor', 'Hola, ' . ($vendedor['nombre'] ?? 'vendedor') . '. Gestiona tus productos, stock, ventas y retiros.', $vendedor, 'dashboard'); ?>

    <section class="card hero">
      <div>
        <h2>Resumen de tu tienda</h2>
        <p class="muted">Tus ventas completadas alimentan el saldo disponible. Los retiros pendientes quedan separados hasta que el admin los revise.</p>
      </div>
      <div class="hero-balance">
        <div class="muted">Saldo disponible</div>
        <div class="money">S/ <?php echo number_format($stats['disponible'], 2); ?></div>
        <a class="btn primary" href="retirar.php" style="margin-top:10px;"><i class="fas fa-money-bill-transfer"></i> Retirar</a>
      </div>
    </section>

    <section class="grid">
      <div class="card stat"><div class="icon"><i class="fas fa-box"></i></div><div><div class="num"><?php echo (int)$stats['productos']; ?></div><div class="muted">Productos</div></div></div>
      <div class="card stat"><div class="icon"><i class="fas fa-layer-group"></i></div><div><div class="num"><?php echo (int)$stats['stock']; ?></div><div class="muted">Perfiles disponibles</div></div></div>
      <div class="card stat"><div class="icon"><i class="fas fa-receipt"></i></div><div><div class="num"><?php echo (int)$stats['ventas']; ?></div><div class="muted">Ventas totales</div></div></div>
      <div class="card stat"><div class="icon"><i class="fas fa-users"></i></div><div><div class="num"><?php echo (int)$stats['clientes']; ?></div><div class="muted">Clientes</div></div></div>
      <div class="card stat"><div class="icon"><i class="fas fa-calendar-check"></i></div><div><div class="num">S/ <?php echo number_format($stats['ingresos_mes'], 2); ?></div><div class="muted">Este mes</div></div></div>
      <div class="card stat"><div class="icon"><i class="fas fa-clock"></i></div><div><div class="num">S/ <?php echo number_format($stats['pendiente_retiro'], 2); ?></div><div class="muted">Retiro pendiente</div></div></div>
    </section>

    <section class="actions">
      <div class="card action">
        <h3>Mis productos</h3>
        <p>Crea y edita las cartillas que apareceran en el marketplace.</p>
        <a class="btn primary" href="productos.php"><i class="fas fa-plus"></i> Gestionar productos</a>
      </div>
      <div class="card action">
        <h3>Mi stock</h3>
        <p>Agrega cuentas, perfiles y credenciales propias para vender.</p>
        <a class="btn primary" href="stock.php"><i class="fas fa-key"></i> Cargar stock</a>
      </div>
      <div class="card action">
        <h3>Mis ventas</h3>
        <p>Revisa compras completadas, clientes y vencimientos.</p>
        <a class="btn primary" href="ventas.php"><i class="fas fa-chart-line"></i> Ver ventas</a>
      </div>
      <div class="card action">
        <h3>Retiros</h3>
        <p>Solicita el pago total o parcial de tu saldo disponible.</p>
        <a class="btn primary" href="retirar.php"><i class="fas fa-wallet"></i> Solicitar retiro</a>
      </div>
    </section>

    <section class="two-col">
      <div class="card">
        <div class="section-title">
          <h2>Ventas recientes</h2>
          <a class="btn" href="ventas.php"><i class="fas fa-arrow-right"></i> Ver todo</a>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>ID</th>
                <th>Producto</th>
                <th>Cliente</th>
                <th>Monto</th>
                <th>Estado</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($recentSales as $sale): ?>
              <tr>
                <td>#<?php echo (int)$sale['id']; ?><br><span class="muted"><?php echo !empty($sale['fecha']) && strtotime((string)$sale['fecha']) ? h(date('d/m/Y', strtotime((string)$sale['fecha']))) : ''; ?></span></td>
                <td><?php echo h($sale['producto_nombre'] ?? 'Producto'); ?></td>
                <td><?php echo h($sale['cliente_nombre'] ?? 'Cliente'); ?></td>
                <td>S/ <?php echo number_format((float)$sale['monto_vendedor'], 2); ?></td>
                <td><span class="badge <?php echo dashBadge((string)$sale['estado']); ?>"><?php echo h($sale['estado']); ?></span></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$recentSales): ?>
              <tr><td colspan="5" class="muted">Aun no hay ventas registradas.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="card">
        <div class="section-title">
          <h2>Stock bajo</h2>
          <a class="btn" href="stock.php"><i class="fas fa-key"></i> Stock</a>
        </div>
        <div class="stock-list">
          <?php foreach ($lowStock as $product): ?>
            <div class="stock-item">
              <div>
                <strong><?php echo h($product['nombre'] ?? 'Producto'); ?></strong>
                <div class="muted">Producto #<?php echo (int)$product['id']; ?></div>
              </div>
              <div class="badge <?php echo ((int)($product['stock'] ?? 0) <= 0) ? 'bad' : 'warn'; ?>"><?php echo (int)($product['stock'] ?? 0); ?> disp.</div>
            </div>
          <?php endforeach; ?>
          <?php if (!$lowStock): ?>
            <p class="muted">No hay productos para mostrar todavia.</p>
          <?php endif; ?>
        </div>
      </div>
    </section>
  <?php sellerPanelEnd(); ?>
</body>
</html>
