<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/panel-shell.php';

requireRole('vendedor');

$seller = getCurrentUser();
$sellerId = (int)($seller['id'] ?? 0);

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function stockTableExists(mysqli $cx, string $table): bool {
    $t = $cx->real_escape_string($table);
    $rs = $cx->query("SHOW TABLES LIKE '$t'");
    return ($rs && $rs->num_rows > 0);
}
function stockColExists(mysqli $cx, string $table, string $col): bool {
    $t = $cx->real_escape_string($table);
    $c = $cx->real_escape_string($col);
    $rs = $cx->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
    return ($rs && $rs->num_rows > 0);
}
function recalcularStockVendedor(mysqli $cx, int $productoId, int $sellerId): void {
    if (!stockColExists($cx, 'productos', 'stock')) return;

    $stock = 0;
    $st = $cx->prepare("
        SELECT COUNT(*) c
        FROM cuenta_perfiles cp
        INNER JOIN cuentas cu ON cu.id = cp.cuenta_id
        WHERE cu.producto_id=? AND cu.vendedor_id=? AND cu.estado='DISPONIBLE' AND cp.estado='DISPONIBLE'
    ");
    $st->bind_param("ii", $productoId, $sellerId);
    $st->execute();
    $stock = (int)($st->get_result()->fetch_assoc()['c'] ?? 0);
    $st->close();

    $up = $cx->prepare("UPDATE productos SET stock=? WHERE id=? AND vendedor_id=?");
    $up->bind_param("iii", $stock, $productoId, $sellerId);
    $up->execute();
    $up->close();
}

$migrationReady = stockTableExists($conexion, 'productos')
    && stockColExists($conexion, 'productos', 'vendedor_id')
    && stockTableExists($conexion, 'cuentas')
    && stockColExists($conexion, 'cuentas', 'vendedor_id');

$success = '';
$error = '';

if (empty($_SESSION['_csrf_seller_stock'])) {
    $_SESSION['_csrf_seller_stock'] = bin2hex(random_bytes(32));
}
$csrfSellerStock = $_SESSION['_csrf_seller_stock'];

$productos = [];
if ($migrationReady) {
    $st = $conexion->prepare("SELECT id, nombre, tipo_venta, stock FROM productos WHERE vendedor_id=? AND activo=1 ORDER BY nombre ASC");
    $st->bind_param("i", $sellerId);
    $st->execute();
    $rs = $st->get_result();
    while ($rs && ($row = $rs->fetch_assoc())) $productos[] = $row;
    $st->close();
}

if ($migrationReady && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $productoId = (int)($_POST['producto_id'] ?? 0);
    $loginUser = trim((string)($_POST['login_user'] ?? ''));
    $loginPass = trim((string)($_POST['login_pass'] ?? ''));
    $pin = trim((string)($_POST['pin'] ?? ''));
    $maxPerfiles = max(1, (int)($_POST['max_perfiles'] ?? 1));

    if (!hash_equals($csrfSellerStock, (string)($_POST['_csrf'] ?? ''))) {
        $error = 'Token invalido. Recarga la pagina e intenta nuevamente.';
    } elseif ($productoId <= 0 || $loginUser === '' || $loginPass === '') {
        $error = 'Completa producto, usuario/correo y password.';
    } else {
        $st = $conexion->prepare("SELECT id, tipo_venta FROM productos WHERE id=? AND vendedor_id=? LIMIT 1");
        $st->bind_param("ii", $productoId, $sellerId);
        $st->execute();
        $producto = $st->get_result()->fetch_assoc();
        $st->close();

        if (!$producto) {
            $error = 'Producto invalido o no pertenece a tu cuenta.';
        } else {
            try {
                $conexion->begin_transaction();

                $modoVenta = ((string)$producto['tipo_venta'] === 'CUENTA_COMPLETA') ? 'CUENTA_COMPLETA' : 'PERFIL';
                if ($modoVenta === 'CUENTA_COMPLETA') $maxPerfiles = 1;
                $pinDb = ($pin === '') ? null : $pin;

                $hasModo = stockColExists($conexion, 'cuentas', 'modo_venta');
                if ($hasModo) {
                    $st = $conexion->prepare("
                        INSERT INTO cuentas (vendedor_id, producto_id, modo_venta, login_user, login_pass, pin, max_perfiles, estado)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'DISPONIBLE')
                    ");
                    $st->bind_param("iissssi", $sellerId, $productoId, $modoVenta, $loginUser, $loginPass, $pinDb, $maxPerfiles);
                } else {
                    $st = $conexion->prepare("
                        INSERT INTO cuentas (vendedor_id, producto_id, login_user, login_pass, pin, max_perfiles, estado)
                        VALUES (?, ?, ?, ?, ?, ?, 'DISPONIBLE')
                    ");
                    $st->bind_param("iisssi", $sellerId, $productoId, $loginUser, $loginPass, $pinDb, $maxPerfiles);
                }
                $st->execute();
                $cuentaId = (int)$st->insert_id;
                $st->close();

                for ($i = 1; $i <= $maxPerfiles; $i++) {
                    $perfilNombre = ($modoVenta === 'CUENTA_COMPLETA') ? 'Cuenta completa' : "Perfil $i";
                    $st = $conexion->prepare("INSERT INTO cuenta_perfiles (cuenta_id, perfil_nombre, estado) VALUES (?, ?, 'DISPONIBLE')");
                    $st->bind_param("is", $cuentaId, $perfilNombre);
                    $st->execute();
                    $st->close();
                }

                recalcularStockVendedor($conexion, $productoId, $sellerId);

                $conexion->commit();
                $success = 'Stock agregado correctamente.';
            } catch (Throwable $e) {
                try { $conexion->rollback(); } catch (Throwable $x) {}
                $error = 'No se pudo agregar stock: ' . $e->getMessage();
            }
        }
    }
}

$cuentas = [];
if ($migrationReady) {
    $st = $conexion->prepare("
        SELECT cu.id, cu.producto_id, p.nombre AS producto_nombre, cu.login_user, cu.estado, cu.max_perfiles,
               (SELECT COUNT(*) FROM cuenta_perfiles cp WHERE cp.cuenta_id=cu.id AND cp.estado='DISPONIBLE') AS disponibles,
               (SELECT COUNT(*) FROM cuenta_perfiles cp WHERE cp.cuenta_id=cu.id AND cp.estado='VENDIDO') AS vendidos
        FROM cuentas cu
        INNER JOIN productos p ON p.id = cu.producto_id
        WHERE cu.vendedor_id=?
        ORDER BY cu.id DESC
        LIMIT 100
    ");
    $st->bind_param("i", $sellerId);
    $st->execute();
    $rs = $st->get_result();
    while ($rs && ($row = $rs->fetch_assoc())) $cuentas[] = $row;
    $st->close();
}

$page_title = "Mi stock - Vendedor";
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
    .wrap{max-width:1200px;margin:0 auto;padding:32px 20px}.top{display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:22px}
    h1,h2{color:#fff}.muted{color:#aaa}.grid{display:grid;grid-template-columns:390px 1fr;gap:18px;align-items:start}
    .card{background:rgba(255,255,255,.045);border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:18px}
    label{display:block;color:#ccc;font-weight:700;margin:12px 0 6px}
    input,select{width:100%;padding:11px 12px;border-radius:10px;border:1px solid rgba(255,255,255,.13);background:rgba(0,0,0,.28);color:#fff}
    .btn{border:0;border-radius:10px;padding:10px 14px;font-weight:900;text-decoration:none;display:inline-flex;gap:8px;align-items:center;cursor:pointer}
    .primary{background:linear-gradient(135deg,#12aaff,#0de0c9);color:#0d0f14}.secondary{background:rgba(255,255,255,.06);color:#fff;border:1px solid rgba(255,255,255,.12)}
    .alert{padding:12px;border-radius:12px;margin-bottom:14px}.ok{background:rgba(52,199,89,.14);border:1px solid rgba(52,199,89,.35);color:#34c759}.err{background:rgba(255,59,48,.14);border:1px solid rgba(255,59,48,.35);color:#ff6b6b}
    table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:12px;border-bottom:1px solid rgba(255,255,255,.07)}th{color:#9a9a9a;font-size:.86rem}
    @media(max-width:900px){.grid{grid-template-columns:1fr}}
  </style>
  <link rel="stylesheet" href="../assets/css/mobile-urgent.css?v=20260610k">
</head>
<body>
<?php sellerPanelStart('Mi stock', 'Carga cuentas y perfiles que se entregaran automaticamente al comprar.', $seller, 'stock'); ?>

  <?php if (!$migrationReady): ?><div class="alert err">Falta aplicar la migracion marketplace para habilitar stock por vendedor.</div><?php endif; ?>
  <?php if ($success): ?><div class="alert ok"><?php echo h($success); ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert err"><?php echo h($error); ?></div><?php endif; ?>

  <section class="grid">
    <form class="card" method="POST">
      <h2>Agregar cuenta</h2>
      <input type="hidden" name="_csrf" value="<?php echo h($csrfSellerStock); ?>">

      <label>Producto</label>
      <select name="producto_id" required>
        <option value="">Seleccionar</option>
        <?php foreach ($productos as $p): ?>
          <option value="<?php echo (int)$p['id']; ?>"><?php echo h($p['nombre']); ?> - <?php echo h($p['tipo_venta']); ?></option>
        <?php endforeach; ?>
      </select>

      <label>Usuario / correo / codigo</label>
      <input name="login_user" required>

      <label>Password / clave</label>
      <input name="login_pass" required>

      <label>PIN opcional</label>
      <input name="pin">

      <label>Perfiles disponibles</label>
      <input name="max_perfiles" type="number" min="1" value="1">

      <button class="btn primary" type="submit" style="margin-top:16px;" <?php echo !$migrationReady ? 'disabled' : ''; ?>><i class="fas fa-plus"></i> Agregar stock</button>
    </form>

    <div class="card">
      <h2 style="margin-bottom:12px;">Cuentas cargadas</h2>
      <table>
        <thead><tr><th>ID</th><th>Producto</th><th>Usuario</th><th>Disp.</th><th>Vendidos</th><th>Estado</th></tr></thead>
        <tbody>
        <?php foreach ($cuentas as $c): ?>
          <tr>
            <td>#<?php echo (int)$c['id']; ?></td>
            <td><?php echo h($c['producto_nombre']); ?></td>
            <td><?php echo h($c['login_user']); ?></td>
            <td><?php echo (int)$c['disponibles']; ?></td>
            <td><?php echo (int)$c['vendidos']; ?></td>
            <td><?php echo h($c['estado']); ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$cuentas): ?><tr><td colspan="6" class="muted">Aun no has cargado stock.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php sellerPanelEnd(); ?>
</body>
</html>
