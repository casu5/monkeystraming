<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/sidebar.php';

requireRole('admin');

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function tableExistsAdminSellers(mysqli $cx, string $table): bool {
    $t = $cx->real_escape_string($table);
    $rs = $cx->query("SHOW TABLES LIKE '$t'");
    return ($rs && $rs->num_rows > 0);
}
function colExistsAdminSellers(mysqli $cx, string $table, string $col): bool {
    $t = $cx->real_escape_string($table);
    $c = $cx->real_escape_string($col);
    $rs = $cx->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
    return ($rs && $rs->num_rows > 0);
}

$admin = getCurrentUser();
$adminId = (int)($admin['id'] ?? $_SESSION['user_id'] ?? 0);
$success = '';
$error = '';

if (empty($_SESSION['_csrf_admin_sellers'])) {
    $_SESSION['_csrf_admin_sellers'] = bin2hex(random_bytes(32));
}
$csrfAdminSellers = $_SESSION['_csrf_admin_sellers'];
$migrationReady = tableExistsAdminSellers($conexion, 'usuarios')
    && tableExistsAdminSellers($conexion, 'productos')
    && tableExistsAdminSellers($conexion, 'compras')
    && colExistsAdminSellers($conexion, 'productos', 'vendedor_id')
    && colExistsAdminSellers($conexion, 'compras', 'vendedor_id');
$hasSellerProfiles = tableExistsAdminSellers($conexion, 'vendedor_perfiles');
$hasCreatedBy = colExistsAdminSellers($conexion, 'usuarios', 'created_by');

if ($migrationReady && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim((string)($_POST['nombre'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $whatsapp = trim((string)($_POST['whatsapp'] ?? ''));
    $tienda = trim((string)($_POST['tienda_nombre'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if (!hash_equals($csrfAdminSellers, (string)($_POST['_csrf'] ?? ''))) {
        $error = 'Token invalido. Recarga la pagina e intenta nuevamente.';
    } elseif ($nombre === '' || strlen($nombre) < 3) {
        $error = 'El nombre debe tener al menos 3 caracteres.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Correo invalido.';
    } elseif ($tienda === '') {
        $error = 'El nombre de tienda es obligatorio.';
    } elseif (strlen($password) < 6) {
        $error = 'La password debe tener al menos 6 caracteres.';
    } else {
        $st = $conexion->prepare("SELECT id FROM usuarios WHERE email=? LIMIT 1");
        $st->bind_param("s", $email);
        $st->execute();
        $exists = $st->get_result()->fetch_assoc();
        $st->close();

        if ($exists) {
            $error = 'Ese correo ya esta registrado.';
        } else {
            try {
                $conexion->begin_transaction();

                $hash = password_hash($password, PASSWORD_DEFAULT);
                $estado = 'activo';
                $role = 'vendedor';

                if ($hasCreatedBy) {
                    $st = $conexion->prepare("
                        INSERT INTO usuarios (nombre, email, whatsapp, password, role, saldo, estado, created_by)
                        VALUES (?, ?, ?, ?, ?, 0.00, ?, ?)
                    ");
                    $st->bind_param("ssssssi", $nombre, $email, $whatsapp, $hash, $role, $estado, $adminId);
                } else {
                    $st = $conexion->prepare("
                        INSERT INTO usuarios (nombre, email, whatsapp, password, role, saldo, estado)
                        VALUES (?, ?, ?, ?, ?, 0.00, ?)
                    ");
                    $st->bind_param("ssssss", $nombre, $email, $whatsapp, $hash, $role, $estado);
                }
                $st->execute();
                $vendedorId = (int)$st->insert_id;
                $st->close();

                if ($hasSellerProfiles) {
                    $st = $conexion->prepare("
                        INSERT INTO vendedor_perfiles (vendedor_id, tienda_nombre, soporte_whatsapp)
                        VALUES (?, ?, ?)
                    ");
                    $st->bind_param("iss", $vendedorId, $tienda, $whatsapp);
                    $st->execute();
                    $st->close();
                }

                $conexion->commit();
                $success = 'Vendedor creado correctamente.';
            } catch (Throwable $e) {
                try { $conexion->rollback(); } catch (Throwable $x) {}
                $error = 'No se pudo crear el vendedor: ' . $e->getMessage();
            }
        }
    }
}

$vendedores = [];
if ($migrationReady) {
    $profileSelect = $hasSellerProfiles ? "vp.tienda_nombre," : "NULL AS tienda_nombre,";
    $profileJoin = $hasSellerProfiles ? "LEFT JOIN vendedor_perfiles vp ON vp.vendedor_id = u.id" : "";
    $profileGroup = $hasSellerProfiles ? ", vp.tienda_nombre" : "";
    $sql = "
        SELECT u.id, u.nombre, u.email, u.whatsapp, u.estado, u.created_at,
               $profileSelect
               COUNT(DISTINCT p.id) AS productos,
               COUNT(DISTINCT c.id) AS ventas,
               COALESCE(SUM(c.monto), 0) AS vendido
        FROM usuarios u
        $profileJoin
        LEFT JOIN productos p ON p.vendedor_id = u.id
        LEFT JOIN compras c ON c.vendedor_id = u.id AND c.estado = 'completada'
        WHERE u.role = 'vendedor'
        GROUP BY u.id, u.nombre, u.email, u.whatsapp, u.estado, u.created_at $profileGroup
        ORDER BY u.id DESC
    ";
    $rs = $conexion->query($sql);
    if ($rs) {
        while ($row = $rs->fetch_assoc()) $vendedores[] = $row;
    }
}

$selectedSellerId = max(0, (int)($_GET['seller_id'] ?? 0));
$selectedSeller = null;
$sellerProducts = [];
$sellerAccounts = [];
$sellerClients = [];
$sellerSales = [];
$sellerTotals = [
    'productos' => 0,
    'cuentas' => 0,
    'perfiles_disponibles' => 0,
    'clientes' => 0,
    'ventas' => 0,
    'vendido' => 0.00,
    'para_vendedor' => 0.00,
    'comision_admin' => 0.00,
];

if ($selectedSellerId > 0 && tableExistsAdminSellers($conexion, 'usuarios')) {
    $profileSelect = $hasSellerProfiles ? "vp.tienda_nombre, vp.soporte_whatsapp" : "NULL AS tienda_nombre, NULL AS soporte_whatsapp";
    $profileJoin = $hasSellerProfiles ? "LEFT JOIN vendedor_perfiles vp ON vp.vendedor_id = u.id" : "";
    $st = $conexion->prepare("
        SELECT u.*, $profileSelect
        FROM usuarios u
        $profileJoin
        WHERE u.id=? AND u.role='vendedor'
        LIMIT 1
    ");
    $st->bind_param("i", $selectedSellerId);
    $st->execute();
    $selectedSeller = $st->get_result()->fetch_assoc();
    $st->close();
}

if ($selectedSeller) {
    if (tableExistsAdminSellers($conexion, 'productos') && colExistsAdminSellers($conexion, 'productos', 'vendedor_id')) {
        $selectProductCols = ["p.id", "p.nombre", "p.precio", "p.stock", "p.activo", "p.vendedor_id"];
        foreach (['tipo_venta', 'duracion_dias', 'imagen_url', 'descripcion'] as $col) {
            if (colExistsAdminSellers($conexion, 'productos', $col)) $selectProductCols[] = "p.`$col`";
        }
        $sql = "SELECT " . implode(", ", $selectProductCols) . " FROM productos p WHERE p.vendedor_id=? ORDER BY p.id DESC LIMIT 200";
        $st = $conexion->prepare($sql);
        $st->bind_param("i", $selectedSellerId);
        $st->execute();
        $rs = $st->get_result();
        while ($rs && ($row = $rs->fetch_assoc())) $sellerProducts[] = $row;
        $st->close();
        $sellerTotals['productos'] = count($sellerProducts);
    }

    if (tableExistsAdminSellers($conexion, 'cuentas') && tableExistsAdminSellers($conexion, 'productos')) {
        $hasCuentaSeller = colExistsAdminSellers($conexion, 'cuentas', 'vendedor_id');
        $hasPerfiles = tableExistsAdminSellers($conexion, 'cuenta_perfiles');
        $selectCuentaCols = [
            "cu.id", "cu.producto_id", "p.nombre AS producto_nombre", "cu.login_user", "cu.login_pass",
            "cu.estado", "cu.max_perfiles"
        ];
        foreach (['pin', 'modo_venta'] as $col) {
            if (colExistsAdminSellers($conexion, 'cuentas', $col)) $selectCuentaCols[] = "cu.`$col`";
        }
        $whereCuenta = $hasCuentaSeller ? "cu.vendedor_id=?" : "p.vendedor_id=?";
        $selectDisponibles = $hasPerfiles
            ? "(SELECT COUNT(*) FROM cuenta_perfiles cp WHERE cp.cuenta_id=cu.id AND cp.estado='DISPONIBLE') AS disponibles"
            : "0 AS disponibles";
        $selectVendidos = $hasPerfiles
            ? "(SELECT COUNT(*) FROM cuenta_perfiles cp WHERE cp.cuenta_id=cu.id AND cp.estado='VENDIDO') AS vendidos"
            : "0 AS vendidos";
        $sql = "
            SELECT " . implode(", ", $selectCuentaCols) . ",
                   $selectDisponibles,
                   $selectVendidos
            FROM cuentas cu
            INNER JOIN productos p ON p.id = cu.producto_id
            WHERE $whereCuenta
            ORDER BY cu.id DESC
            LIMIT 200
        ";
        $st = $conexion->prepare($sql);
        $st->bind_param("i", $selectedSellerId);
        $st->execute();
        $rs = $st->get_result();
        while ($rs && ($row = $rs->fetch_assoc())) $sellerAccounts[] = $row;
        $st->close();

        $sellerTotals['cuentas'] = count($sellerAccounts);
        foreach ($sellerAccounts as $account) {
            $sellerTotals['perfiles_disponibles'] += (int)($account['disponibles'] ?? 0);
        }
    }

    if (tableExistsAdminSellers($conexion, 'compras') && colExistsAdminSellers($conexion, 'compras', 'vendedor_id')) {
        $clienteCol = colExistsAdminSellers($conexion, 'compras', 'cliente_id') ? 'cliente_id' : 'usuario_id';
        $amountSellerCol = colExistsAdminSellers($conexion, 'compras', 'monto_vendedor') ? 'monto_vendedor' : 'monto';
        $commissionCol = colExistsAdminSellers($conexion, 'compras', 'comision_admin') ? 'comision_admin' : null;
        $dateCol = colExistsAdminSellers($conexion, 'compras', 'fecha_compra') ? 'fecha_compra' : (colExistsAdminSellers($conexion, 'compras', 'created_at') ? 'created_at' : 'id');

        $selectCommission = $commissionCol ? "COALESCE(SUM(CASE WHEN estado='completada' THEN `$commissionCol` ELSE 0 END),0)" : "0";
        $st = $conexion->prepare("
            SELECT COUNT(*) AS ventas,
                   COALESCE(SUM(CASE WHEN estado='completada' THEN monto ELSE 0 END),0) AS vendido,
                   COALESCE(SUM(CASE WHEN estado='completada' THEN `$amountSellerCol` ELSE 0 END),0) AS para_vendedor,
                   $selectCommission AS comision_admin,
                   COUNT(DISTINCT `$clienteCol`) AS clientes
            FROM compras
            WHERE vendedor_id=?
        ");
        $st->bind_param("i", $selectedSellerId);
        $st->execute();
        $tot = $st->get_result()->fetch_assoc() ?: [];
        $st->close();
        $sellerTotals['ventas'] = (int)($tot['ventas'] ?? 0);
        $sellerTotals['vendido'] = (float)($tot['vendido'] ?? 0);
        $sellerTotals['para_vendedor'] = (float)($tot['para_vendedor'] ?? 0);
        $sellerTotals['comision_admin'] = (float)($tot['comision_admin'] ?? 0);
        $sellerTotals['clientes'] = (int)($tot['clientes'] ?? 0);

        $sql = "
            SELECT c.id, c.estado, c.monto, c.`$amountSellerCol` AS monto_vendedor, c.`$clienteCol` AS cliente_id,
                   c.producto_id, c.`$dateCol` AS fecha,
                   u.nombre AS cliente_nombre, u.email AS cliente_email, u.whatsapp AS cliente_whatsapp,
                   p.nombre AS producto_nombre
            FROM compras c
            LEFT JOIN usuarios u ON u.id = c.`$clienteCol`
            LEFT JOIN productos p ON p.id = c.producto_id
            WHERE c.vendedor_id=?
            ORDER BY c.id DESC
            LIMIT 200
        ";
        $st = $conexion->prepare($sql);
        $st->bind_param("i", $selectedSellerId);
        $st->execute();
        $rs = $st->get_result();
        while ($rs && ($row = $rs->fetch_assoc())) $sellerSales[] = $row;
        $st->close();

        $sql = "
            SELECT u.id, u.nombre, u.email, u.whatsapp,
                   COUNT(c.id) AS compras,
                   COALESCE(SUM(CASE WHEN c.estado='completada' THEN c.monto ELSE 0 END),0) AS total_comprado,
                   MAX(c.`$dateCol`) AS ultima_compra
            FROM compras c
            LEFT JOIN usuarios u ON u.id = c.`$clienteCol`
            WHERE c.vendedor_id=?
            GROUP BY u.id, u.nombre, u.email, u.whatsapp
            ORDER BY ultima_compra DESC
            LIMIT 200
        ";
        $st = $conexion->prepare($sql);
        $st->bind_param("i", $selectedSellerId);
        $st->execute();
        $rs = $st->get_result();
        while ($rs && ($row = $rs->fetch_assoc())) $sellerClients[] = $row;
        $st->close();
    }
}

$page_title = "Vendedores - Admin";
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
    h1{color:#fff;font-size:1.8rem}
    .muted{color:#aaa}
    .grid{display:grid;grid-template-columns:390px 1fr;gap:18px;align-items:start}
    .card{background:rgba(255,255,255,.045);border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:18px}
    label{display:block;color:#ccc;font-weight:700;margin:12px 0 6px}
    input{width:100%;padding:11px 12px;border-radius:10px;border:1px solid rgba(255,255,255,.13);background:rgba(0,0,0,.28);color:#fff}
    .btn{border:0;border-radius:10px;padding:11px 15px;font-weight:900;text-decoration:none;display:inline-flex;gap:8px;align-items:center;cursor:pointer}
    .btn.primary{background:linear-gradient(135deg,#12aaff,#0de0c9);color:#0d0f14}
    .btn.secondary{background:rgba(255,255,255,.06);color:#fff;border:1px solid rgba(255,255,255,.12)}
    .alert{padding:12px;border-radius:12px;margin-bottom:14px}
    .ok{background:rgba(52,199,89,.14);border:1px solid rgba(52,199,89,.35);color:#34c759}
    .err{background:rgba(255,59,48,.14);border:1px solid rgba(255,59,48,.35);color:#ff6b6b}
    .stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin:18px 0}
    .stat{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:14px;padding:14px}
    .stat .k{color:#8b8b8b;font-size:.78rem;text-transform:uppercase;letter-spacing:.5px}
    .stat .v{color:#fff;font-size:1.25rem;font-weight:900;margin-top:4px}
    .section-title{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:12px}
    .badge{display:inline-flex;align-items:center;gap:6px;border-radius:999px;padding:4px 9px;font-size:.78rem;font-weight:900;background:rgba(18,170,255,.12);color:#12aaff;border:1px solid rgba(18,170,255,.24)}
    .mini{font-size:.82rem;color:#8d8d8d;margin-top:4px}
    .details-grid{display:grid;grid-template-columns:1fr;gap:16px;margin-top:18px}
    .table-wrap{overflow:auto}
    table{width:100%;border-collapse:collapse}
    th,td{text-align:left;padding:12px;border-bottom:1px solid rgba(255,255,255,.07)}
    th{color:#9a9a9a;font-size:.86rem}
    @media(max-width:900px){.grid{grid-template-columns:1fr}}
    @media(max-width:992px){.wrap{width:100%;margin-left:0;padding:82px 16px 24px}}
  </style>
</head>
<body>
<?php renderAdminSidebar($conexion, 'vendedores.php'); ?>
<main class="wrap">
  <section class="top">
    <div>
      <h1>Vendedores</h1>
      <p class="muted">Crea cuentas de vendedor y supervisa su actividad.</p>
    </div>
    <a class="btn secondary" href="index.php"><i class="fas fa-arrow-left"></i> Volver al admin</a>
  </section>

  <?php if ($success): ?><div class="alert ok"><?php echo h($success); ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert err"><?php echo h($error); ?></div><?php endif; ?>
  <?php if (!$migrationReady): ?><div class="alert err">Falta aplicar la migracion marketplace antes de crear vendedores.</div><?php endif; ?>

  <section class="grid">
    <form class="card" method="POST">
      <h2 style="color:#fff;margin-bottom:8px;">Nuevo vendedor</h2>
      <p class="muted">El cliente se registra solo; el vendedor lo crea el admin.</p>
      <input type="hidden" name="_csrf" value="<?php echo h($csrfAdminSellers); ?>">

      <label>Nombre</label>
      <input name="nombre" required minlength="3">

      <label>Correo</label>
      <input name="email" type="email" required>

      <label>WhatsApp</label>
      <input name="whatsapp" placeholder="+51999999999">

      <label>Nombre de tienda</label>
      <input name="tienda_nombre" required>

      <label>Password temporal</label>
      <input name="password" type="password" required minlength="6">

      <button class="btn primary" style="margin-top:16px;" type="submit" <?php echo !$migrationReady ? 'disabled' : ''; ?>><i class="fas fa-user-plus"></i> Crear vendedor</button>
    </form>

    <div class="card">
      <h2 style="color:#fff;margin-bottom:12px;">Listado</h2>
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Vendedor</th>
            <th>Tienda</th>
            <th>Productos</th>
            <th>Ventas</th>
            <th>Vendido</th>
            <th>Estado</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($vendedores as $v): ?>
          <tr>
            <td>#<?php echo (int)$v['id']; ?></td>
            <td>
              <strong><?php echo h($v['nombre']); ?></strong><br>
              <span class="muted"><?php echo h($v['email']); ?></span>
            </td>
            <td><?php echo h($v['tienda_nombre'] ?? 'Sin tienda'); ?></td>
            <td><?php echo (int)$v['productos']; ?></td>
            <td><?php echo (int)$v['ventas']; ?></td>
            <td>S/ <?php echo number_format((float)$v['vendido'], 2); ?></td>
            <td><?php echo h($v['estado']); ?></td>
            <td><a class="btn secondary" href="vendedores.php?seller_id=<?php echo (int)$v['id']; ?>"><i class="fas fa-eye"></i> Ver todo</a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$vendedores): ?>
          <tr><td colspan="8" class="muted">Aun no hay vendedores creados.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <?php if ($selectedSeller): ?>
    <section class="details-grid">
      <div class="card">
        <div class="section-title">
          <div>
            <h2 style="color:#fff;">Acceso total: <?php echo h($selectedSeller['nombre']); ?></h2>
            <p class="muted">
              <?php echo h($selectedSeller['email'] ?? ''); ?>
              <?php if (!empty($selectedSeller['tienda_nombre'])): ?> · Tienda: <?php echo h($selectedSeller['tienda_nombre']); ?><?php endif; ?>
              <?php if (!empty($selectedSeller['whatsapp'])): ?> · WhatsApp: <?php echo h($selectedSeller['whatsapp']); ?><?php endif; ?>
            </p>
          </div>
          <a class="btn secondary" href="vendedores.php"><i class="fas fa-times"></i> Cerrar detalle</a>
        </div>

        <div class="stats">
          <div class="stat"><div class="k">Productos</div><div class="v"><?php echo (int)$sellerTotals['productos']; ?></div></div>
          <div class="stat"><div class="k">Cuentas</div><div class="v"><?php echo (int)$sellerTotals['cuentas']; ?></div></div>
          <div class="stat"><div class="k">Perfiles disp.</div><div class="v"><?php echo (int)$sellerTotals['perfiles_disponibles']; ?></div></div>
          <div class="stat"><div class="k">Clientes</div><div class="v"><?php echo (int)$sellerTotals['clientes']; ?></div></div>
          <div class="stat"><div class="k">Ventas</div><div class="v"><?php echo (int)$sellerTotals['ventas']; ?></div></div>
          <div class="stat"><div class="k">Vendido total</div><div class="v">S/ <?php echo number_format((float)$sellerTotals['vendido'], 2); ?></div></div>
          <div class="stat"><div class="k">Para vendedor</div><div class="v">S/ <?php echo number_format((float)$sellerTotals['para_vendedor'], 2); ?></div></div>
          <div class="stat"><div class="k">Comision admin</div><div class="v">S/ <?php echo number_format((float)$sellerTotals['comision_admin'], 2); ?></div></div>
        </div>
      </div>

      <div class="card">
        <div class="section-title">
          <h2 style="color:#fff;">Productos creados por el vendedor</h2>
          <span class="badge"><i class="fas fa-box-open"></i> <?php echo count($sellerProducts); ?></span>
        </div>
        <div class="table-wrap">
          <table>
            <thead><tr><th>ID</th><th>Producto</th><th>Tipo</th><th>Precio</th><th>Stock</th><th>Estado</th></tr></thead>
            <tbody>
            <?php foreach ($sellerProducts as $p): ?>
              <tr>
                <td>#<?php echo (int)$p['id']; ?></td>
                <td><strong><?php echo h($p['nombre'] ?? 'Producto'); ?></strong><div class="mini"><?php echo h(substr((string)($p['descripcion'] ?? ''), 0, 90)); ?></div></td>
                <td><?php echo h($p['tipo_venta'] ?? '-'); ?></td>
                <td>S/ <?php echo number_format((float)($p['precio'] ?? 0), 2); ?></td>
                <td><?php echo (int)($p['stock'] ?? 0); ?></td>
                <td><?php echo h((string)($p['activo'] ?? '-')); ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$sellerProducts): ?><tr><td colspan="6" class="muted">Este vendedor aun no creo productos.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="card">
        <div class="section-title">
          <h2 style="color:#fff;">Cuentas y credenciales del vendedor</h2>
          <span class="badge"><i class="fas fa-key"></i> <?php echo count($sellerAccounts); ?></span>
        </div>
        <div class="table-wrap">
          <table>
            <thead><tr><th>ID</th><th>Producto</th><th>Login</th><th>Password</th><th>PIN</th><th>Estado</th><th>Perfiles</th></tr></thead>
            <tbody>
            <?php foreach ($sellerAccounts as $a): ?>
              <tr>
                <td>#<?php echo (int)$a['id']; ?></td>
                <td><?php echo h($a['producto_nombre'] ?? ('Producto #' . (int)$a['producto_id'])); ?><div class="mini"><?php echo h($a['modo_venta'] ?? ''); ?></div></td>
                <td><?php echo h($a['login_user'] ?? ''); ?></td>
                <td><code><?php echo h($a['login_pass'] ?? ''); ?></code></td>
                <td><?php echo h($a['pin'] ?? '-'); ?></td>
                <td><?php echo h($a['estado'] ?? '-'); ?></td>
                <td>Disp: <?php echo (int)($a['disponibles'] ?? 0); ?> / Vend: <?php echo (int)($a['vendidos'] ?? 0); ?> / Max: <?php echo (int)($a['max_perfiles'] ?? 0); ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$sellerAccounts): ?><tr><td colspan="7" class="muted">Este vendedor aun no cargo cuentas.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="card">
        <div class="section-title">
          <h2 style="color:#fff;">Clientes que compraron a este vendedor</h2>
          <span class="badge"><i class="fas fa-users"></i> <?php echo count($sellerClients); ?></span>
        </div>
        <div class="table-wrap">
          <table>
            <thead><tr><th>ID</th><th>Cliente</th><th>Email</th><th>WhatsApp</th><th>Compras</th><th>Total</th><th>Ultima compra</th></tr></thead>
            <tbody>
            <?php foreach ($sellerClients as $c): ?>
              <tr>
                <td>#<?php echo (int)($c['id'] ?? 0); ?></td>
                <td><?php echo h($c['nombre'] ?? 'Cliente'); ?></td>
                <td><?php echo h($c['email'] ?? ''); ?></td>
                <td><?php echo h($c['whatsapp'] ?? ''); ?></td>
                <td><?php echo (int)($c['compras'] ?? 0); ?></td>
                <td>S/ <?php echo number_format((float)($c['total_comprado'] ?? 0), 2); ?></td>
                <td><?php echo h($c['ultima_compra'] ?? '-'); ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$sellerClients): ?><tr><td colspan="7" class="muted">Este vendedor aun no tiene clientes.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="card">
        <div class="section-title">
          <h2 style="color:#fff;">Ventas del vendedor</h2>
          <span class="badge"><i class="fas fa-receipt"></i> <?php echo count($sellerSales); ?></span>
        </div>
        <div class="table-wrap">
          <table>
            <thead><tr><th>ID</th><th>Fecha</th><th>Producto</th><th>Cliente</th><th>Monto</th><th>Vendedor</th><th>Estado</th></tr></thead>
            <tbody>
            <?php foreach ($sellerSales as $s): ?>
              <tr>
                <td>#<?php echo (int)$s['id']; ?></td>
                <td><?php echo h($s['fecha'] ?? '-'); ?></td>
                <td><?php echo h($s['producto_nombre'] ?? ('Producto #' . (int)$s['producto_id'])); ?></td>
                <td><?php echo h($s['cliente_nombre'] ?? ('Cliente #' . (int)$s['cliente_id'])); ?><div class="mini"><?php echo h($s['cliente_email'] ?? ''); ?></div></td>
                <td>S/ <?php echo number_format((float)($s['monto'] ?? 0), 2); ?></td>
                <td>S/ <?php echo number_format((float)($s['monto_vendedor'] ?? 0), 2); ?></td>
                <td><?php echo h($s['estado'] ?? '-'); ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$sellerSales): ?><tr><td colspan="7" class="muted">Este vendedor aun no tiene ventas.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  <?php elseif ($selectedSellerId > 0): ?>
    <div class="alert err" style="margin-top:18px;">No se encontro un vendedor con ese ID.</div>
  <?php endif; ?>
</main>
</body>
</html>
