<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/panel-shell.php';

requireRole('vendedor');

$seller = getCurrentUser();
$sellerId = (int)($seller['id'] ?? 0);

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function sellerTableExists(mysqli $cx, string $table): bool {
    $t = $cx->real_escape_string($table);
    $rs = $cx->query("SHOW TABLES LIKE '$t'");
    return ($rs && $rs->num_rows > 0);
}
function sellerColExists(mysqli $cx, string $table, string $col): bool {
    $t = $cx->real_escape_string($table);
    $c = $cx->real_escape_string($col);
    $rs = $cx->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
    return ($rs && $rs->num_rows > 0);
}
function saveSellerProductImage(array $file, string $targetDirRel = 'uploads/productos/', int $maxBytes = 5242880): array {
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'path' => '', 'error' => ''];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'path' => '', 'error' => 'Error al subir imagen.'];
    }
    if (($file['size'] ?? 0) > $maxBytes) {
        return ['ok' => false, 'path' => '', 'error' => 'Imagen demasiado grande. Max 5MB.'];
    }
    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        return ['ok' => false, 'path' => '', 'error' => 'Solo se permite JPG, PNG o WEBP.'];
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        return ['ok' => false, 'path' => '', 'error' => 'El archivo no parece una imagen valida.'];
    }

    $absDir = __DIR__ . '/../' . $targetDirRel;
    if (!is_dir($absDir)) mkdir($absDir, 0755, true);

    $name = 'prod_' . bin2hex(random_bytes(12)) . '.' . $ext;
    $abs = $absDir . $name;
    $rel = $targetDirRel . $name;

    if (!move_uploaded_file($file['tmp_name'], $abs)) {
        return ['ok' => false, 'path' => '', 'error' => 'No se pudo guardar la imagen.'];
    }

    return ['ok' => true, 'path' => $rel, 'error' => ''];
}

$migrationReady = sellerTableExists($conexion, 'productos') && sellerColExists($conexion, 'productos', 'vendedor_id');
$success = '';
$error = '';

if (empty($_SESSION['_csrf_seller_products'])) {
    $_SESSION['_csrf_seller_products'] = bin2hex(random_bytes(32));
}
$csrfSellerProducts = $_SESSION['_csrf_seller_products'];

$categorias = [];
if (sellerTableExists($conexion, 'categorias')) {
    $rs = $conexion->query("SELECT id, nombre FROM categorias WHERE visible=1 ORDER BY nombre ASC");
    while ($rs && ($row = $rs->fetch_assoc())) $categorias[] = $row;
}

if ($migrationReady && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $productoId = (int)($_POST['producto_id'] ?? 0);

    try {
        if (!hash_equals($csrfSellerProducts, (string)($_POST['_csrf'] ?? ''))) {
            throw new Exception('Token invalido. Recarga la pagina e intenta nuevamente.');
        }

        if ($action === 'create' || $action === 'update') {
            $nombre = trim((string)($_POST['nombre'] ?? ''));
            $descripcion = trim((string)($_POST['descripcion'] ?? ''));
            $precio = (float)($_POST['precio'] ?? 0);
            $categoriaId = (int)($_POST['categoria_id'] ?? 0);
            $tipoVenta = strtoupper(trim((string)($_POST['tipo_venta'] ?? 'PERFIL')));
            $tipoVenta = ($tipoVenta === 'CUENTA_COMPLETA') ? 'CUENTA_COMPLETA' : 'PERFIL';
            $duracionDias = max(1, (int)($_POST['duracion_dias'] ?? 30));
            $activo = isset($_POST['activo']) ? 1 : 0;

            if ($nombre === '') throw new Exception('El nombre es obligatorio.');
            if ($precio <= 0) throw new Exception('El precio debe ser mayor a cero.');
            if ($categoriaId <= 0) throw new Exception('Selecciona una categoria.');

            $imagePath = '';
            $setImage = false;
            if (isset($_FILES['imagen']) && ($_FILES['imagen']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $up = saveSellerProductImage($_FILES['imagen']);
                if (!$up['ok']) throw new Exception($up['error']);
                $imagePath = $up['path'];
                $setImage = true;
            }

            if ($action === 'create') {
                $cols = ['vendedor_id', 'nombre', 'descripcion', 'precio', 'categoria_id', 'stock', 'activo', 'tipo_venta', 'duracion_dias'];
                $vals = ['?', '?', '?', '?', '?', '0', '?', '?', '?'];
                $types = 'issdiisi';
                $bind = [$sellerId, $nombre, $descripcion, $precio, $categoriaId, $activo, $tipoVenta, $duracionDias];

                if (sellerColExists($conexion, 'productos', 'estado_revision')) {
                    $cols[] = 'estado_revision';
                    $vals[] = "'aprobado'";
                }
                if ($setImage && sellerColExists($conexion, 'productos', 'imagen_url')) {
                    $cols[] = 'imagen_url';
                    $vals[] = '?';
                    $types .= 's';
                    $bind[] = $imagePath;
                }

                $sql = "INSERT INTO productos (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")";
                $st = $conexion->prepare($sql);
                $st->bind_param($types, ...$bind);
                $st->execute();
                $success = 'Producto creado.';
            } else {
                if ($productoId <= 0) throw new Exception('Producto invalido.');

                $sets = ['nombre=?', 'descripcion=?', 'precio=?', 'categoria_id=?', 'activo=?', 'tipo_venta=?', 'duracion_dias=?'];
                $types = 'ssdiisi';
                $bind = [$nombre, $descripcion, $precio, $categoriaId, $activo, $tipoVenta, $duracionDias];

                if ($setImage && sellerColExists($conexion, 'productos', 'imagen_url')) {
                    $sets[] = 'imagen_url=?';
                    $types .= 's';
                    $bind[] = $imagePath;
                }

                $types .= 'ii';
                $bind[] = $productoId;
                $bind[] = $sellerId;

                $sql = "UPDATE productos SET " . implode(', ', $sets) . " WHERE id=? AND vendedor_id=?";
                $st = $conexion->prepare($sql);
                $st->bind_param($types, ...$bind);
                $st->execute();
                $success = 'Producto actualizado.';
            }
        }

        if ($action === 'toggle') {
            if ($productoId <= 0) throw new Exception('Producto invalido.');
            $st = $conexion->prepare("UPDATE productos SET activo = IF(activo=1,0,1) WHERE id=? AND vendedor_id=?");
            $st->bind_param("ii", $productoId, $sellerId);
            $st->execute();
            $success = 'Estado actualizado.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$productos = [];
if ($migrationReady) {
    $sql = "
        SELECT p.*, c.nombre AS categoria_nombre
        FROM productos p
        LEFT JOIN categorias c ON c.id = p.categoria_id
        WHERE p.vendedor_id=?
        ORDER BY p.id DESC
    ";
    $st = $conexion->prepare($sql);
    $st->bind_param("i", $sellerId);
    $st->execute();
    $rs = $st->get_result();
    while ($rs && ($row = $rs->fetch_assoc())) $productos[] = $row;
    $st->close();
}

$edit = null;
if ($migrationReady && isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $st = $conexion->prepare("SELECT * FROM productos WHERE id=? AND vendedor_id=? LIMIT 1");
    $st->bind_param("ii", $editId, $sellerId);
    $st->execute();
    $edit = $st->get_result()->fetch_assoc();
    $st->close();
}

$page_title = "Mis productos - Vendedor";
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
    .wrap{max-width:1200px;margin:0 auto;padding:32px 20px}
    .top{display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:22px}
    h1,h2{color:#fff}.muted{color:#aaa}.grid{display:grid;grid-template-columns:390px 1fr;gap:18px;align-items:start}
    .card{background:rgba(255,255,255,.045);border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:18px}
    label{display:block;color:#ccc;font-weight:700;margin:12px 0 6px}
    input,select,textarea{width:100%;padding:11px 12px;border-radius:10px;border:1px solid rgba(255,255,255,.13);background:rgba(0,0,0,.28);color:#fff}
    textarea{min-height:90px;resize:vertical}.row{display:grid;grid-template-columns:1fr 1fr;gap:10px}
    .btn{border:0;border-radius:10px;padding:10px 14px;font-weight:900;text-decoration:none;display:inline-flex;gap:8px;align-items:center;cursor:pointer}
    .primary{background:linear-gradient(135deg,#12aaff,#0de0c9);color:#0d0f14}.secondary{background:rgba(255,255,255,.06);color:#fff;border:1px solid rgba(255,255,255,.12)}
    .alert{padding:12px;border-radius:12px;margin-bottom:14px}.ok{background:rgba(52,199,89,.14);border:1px solid rgba(52,199,89,.35);color:#34c759}.err{background:rgba(255,59,48,.14);border:1px solid rgba(255,59,48,.35);color:#ff6b6b}
    table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:12px;border-bottom:1px solid rgba(255,255,255,.07)}th{color:#9a9a9a;font-size:.86rem}
    .thumb{width:54px;height:38px;border-radius:8px;object-fit:cover;background:#1a1d26}
    @media(max-width:900px){.grid{grid-template-columns:1fr}.row{grid-template-columns:1fr}}
  </style>
  <link rel="stylesheet" href="../assets/css/mobile-urgent.css?v=20260610">
</head>
<body>
<?php sellerPanelStart('Mis productos', 'Crea las cartillas que los clientes encontraran en el marketplace.', $seller, 'productos'); ?>

  <?php if (!$migrationReady): ?>
    <div class="alert err">Falta aplicar la migracion marketplace para habilitar productos por vendedor.</div>
  <?php endif; ?>
  <?php if ($success): ?><div class="alert ok"><?php echo h($success); ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert err"><?php echo h($error); ?></div><?php endif; ?>

  <section class="grid">
    <form class="card" method="POST" enctype="multipart/form-data">
      <h2><?php echo $edit ? 'Editar producto' : 'Nuevo producto'; ?></h2>
      <input type="hidden" name="_csrf" value="<?php echo h($csrfSellerProducts); ?>">
      <input type="hidden" name="action" value="<?php echo $edit ? 'update' : 'create'; ?>">
      <?php if ($edit): ?><input type="hidden" name="producto_id" value="<?php echo (int)$edit['id']; ?>"><?php endif; ?>

      <label>Nombre</label>
      <input name="nombre" required value="<?php echo h($edit['nombre'] ?? ''); ?>">

      <label>Descripcion</label>
      <textarea name="descripcion"><?php echo h($edit['descripcion'] ?? ''); ?></textarea>

      <div class="row">
        <div>
          <label>Precio</label>
          <input name="precio" type="number" step="0.01" min="0.01" required value="<?php echo h($edit['precio'] ?? ''); ?>">
        </div>
        <div>
          <label>Duracion dias</label>
          <input name="duracion_dias" type="number" min="1" value="<?php echo h($edit['duracion_dias'] ?? 30); ?>">
        </div>
      </div>

      <label>Categoria</label>
      <select name="categoria_id" required>
        <option value="">Seleccionar</option>
        <?php foreach ($categorias as $cat): ?>
          <option value="<?php echo (int)$cat['id']; ?>" <?php echo ((int)($edit['categoria_id'] ?? 0) === (int)$cat['id']) ? 'selected' : ''; ?>>
            <?php echo h($cat['nombre']); ?>
          </option>
        <?php endforeach; ?>
      </select>

      <label>Tipo de venta</label>
      <select name="tipo_venta">
        <?php $tv = (string)($edit['tipo_venta'] ?? 'PERFIL'); ?>
        <option value="PERFIL" <?php echo $tv === 'PERFIL' ? 'selected' : ''; ?>>Perfil</option>
        <option value="CUENTA_COMPLETA" <?php echo $tv === 'CUENTA_COMPLETA' ? 'selected' : ''; ?>>Cuenta completa</option>
      </select>

      <label>Imagen</label>
      <input type="file" name="imagen" accept="image/jpeg,image/png,image/webp">

      <label style="display:flex;gap:8px;align-items:center;">
        <input style="width:auto;" type="checkbox" name="activo" <?php echo (int)($edit['activo'] ?? 1) === 1 ? 'checked' : ''; ?>>
        Activo
      </label>

      <button class="btn primary" type="submit" <?php echo !$migrationReady ? 'disabled' : ''; ?>><i class="fas fa-save"></i> Guardar</button>
      <?php if ($edit): ?><a class="btn secondary" href="productos.php">Cancelar</a><?php endif; ?>
    </form>

    <div class="card">
      <h2 style="margin-bottom:12px;">Mis cartillas</h2>
      <table>
        <thead><tr><th></th><th>Producto</th><th>Precio</th><th>Stock</th><th>Estado</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($productos as $p): ?>
          <tr>
            <td><?php if (!empty($p['imagen_url'])): ?><img class="thumb" src="../<?php echo h($p['imagen_url']); ?>" alt=""><?php endif; ?></td>
            <td><strong><?php echo h($p['nombre']); ?></strong><br><span class="muted"><?php echo h($p['categoria_nombre'] ?? 'Sin categoria'); ?></span></td>
            <td>S/ <?php echo number_format((float)$p['precio'], 2); ?></td>
            <td><?php echo (int)($p['stock'] ?? 0); ?></td>
            <td><?php echo ((int)($p['activo'] ?? 0) === 1) ? 'Activo' : 'Inactivo'; ?></td>
            <td>
              <a class="btn secondary" href="productos.php?edit=<?php echo (int)$p['id']; ?>"><i class="fas fa-edit"></i></a>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="_csrf" value="<?php echo h($csrfSellerProducts); ?>">
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="producto_id" value="<?php echo (int)$p['id']; ?>">
                <button class="btn secondary" type="submit"><i class="fas fa-power-off"></i></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$productos): ?><tr><td colspan="6" class="muted">Aun no has creado productos.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php sellerPanelEnd(); ?>
</body>
</html>
