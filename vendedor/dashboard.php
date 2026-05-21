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

$stats = [
    'productos' => 0,
    'stock' => 0,
    'ventas' => 0,
    'ingresos' => 0.00,
];

if ($vendedorId > 0 && tableExistsSeller($conexion, 'productos') && colExistsSeller($conexion, 'productos', 'vendedor_id')) {
    $st = $conexion->prepare("SELECT COUNT(*) c FROM productos WHERE vendedor_id=?");
    $st->bind_param("i", $vendedorId);
    $st->execute();
    $stats['productos'] = (int)($st->get_result()->fetch_assoc()['c'] ?? 0);
    $st->close();
}

if ($vendedorId > 0 && tableExistsSeller($conexion, 'cuentas') && colExistsSeller($conexion, 'cuentas', 'vendedor_id')) {
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
    $st = $conexion->prepare("SELECT COUNT(*) c, COALESCE(SUM($amountCol),0) s FROM compras WHERE vendedor_id=? AND estado='completada'");
    $st->bind_param("i", $vendedorId);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $stats['ventas'] = (int)($row['c'] ?? 0);
    $stats['ingresos'] = (float)($row['s'] ?? 0);
    $st->close();
}

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
    .wrap{max-width:1180px;margin:0 auto;padding:34px 20px}
    .top{display:flex;justify-content:space-between;align-items:center;gap:18px;flex-wrap:wrap;margin-bottom:28px}
    h1{font-size:1.8rem;color:#fff}
    .muted{color:#9a9a9a}
    .btn{display:inline-flex;align-items:center;gap:8px;padding:11px 15px;border-radius:10px;text-decoration:none;font-weight:800;border:1px solid rgba(255,255,255,.12);color:#fff;background:rgba(255,255,255,.06)}
    .btn.primary{background:linear-gradient(135deg,#12aaff,#0de0c9);color:#0d0f14;border:0}
    .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-bottom:26px}
    .card{background:rgba(255,255,255,.045);border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:20px}
    .stat{display:flex;align-items:center;gap:15px}
    .icon{width:48px;height:48px;border-radius:12px;display:grid;place-items:center;background:rgba(18,170,255,.14);color:#12aaff;font-size:1.35rem}
    .num{font-size:1.55rem;font-weight:900;color:#fff;margin-bottom:3px}
    .actions{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:16px}
    .action h3{color:#fff;margin-bottom:8px}
    .action p{color:#aaa;line-height:1.45;margin-bottom:16px}
  </style>
</head>
<body>
  <?php sellerPanelStart('Panel vendedor', 'Hola, ' . ($vendedor['nombre'] ?? 'vendedor') . '. Gestiona tus productos, stock y ventas.', $vendedor, 'dashboard'); ?>

    <section class="grid">
      <div class="card stat"><div class="icon"><i class="fas fa-box"></i></div><div><div class="num"><?php echo (int)$stats['productos']; ?></div><div class="muted">Productos</div></div></div>
      <div class="card stat"><div class="icon"><i class="fas fa-layer-group"></i></div><div><div class="num"><?php echo (int)$stats['stock']; ?></div><div class="muted">Perfiles disponibles</div></div></div>
      <div class="card stat"><div class="icon"><i class="fas fa-receipt"></i></div><div><div class="num"><?php echo (int)$stats['ventas']; ?></div><div class="muted">Ventas completadas</div></div></div>
      <div class="card stat"><div class="icon"><i class="fas fa-coins"></i></div><div><div class="num">S/ <?php echo number_format($stats['ingresos'], 2); ?></div><div class="muted">Importe vendido</div></div></div>
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
    </section>
  <?php sellerPanelEnd(); ?>
</body>
</html>
