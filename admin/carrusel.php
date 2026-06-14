<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/sidebar.php';

requireAdmin();

function heroH($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function heroEnsureDir(string $dir): void {
    if (!is_dir($dir)) mkdir($dir, 0755, true);
}

function heroUploadImage(array $file, string $destDirRel = 'uploads/hero/', int $maxBytes = 5242880): array {
    if (!isset($file['tmp_name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'path' => '', 'error' => ''];
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'path' => '', 'error' => 'Error al subir la imagen.'];
    }
    if (($file['size'] ?? 0) > $maxBytes) {
        return ['ok' => false, 'path' => '', 'error' => 'La imagen supera 5MB.'];
    }

    $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        return ['ok' => false, 'path' => '', 'error' => 'Usa una imagen JPG, PNG o WEBP.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        return ['ok' => false, 'path' => '', 'error' => 'El archivo no parece una imagen válida.'];
    }

    $destDirRel = rtrim($destDirRel, '/') . '/';
    $destDirAbs = __DIR__ . '/../' . $destDirRel;
    heroEnsureDir($destDirAbs);

    $name = 'hero_' . bin2hex(random_bytes(12)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $destDirAbs . $name)) {
        return ['ok' => false, 'path' => '', 'error' => 'No se pudo guardar la imagen.'];
    }

    return ['ok' => true, 'path' => $destDirRel . $name, 'error' => ''];
}

function heroDateTime(?string $value): ?string {
    $value = trim((string)$value);
    if ($value === '') return null;
    $value = str_replace('T', ' ', $value);
    return strlen($value) === 16 ? $value . ':00' : $value;
}

$conexion->query("CREATE TABLE IF NOT EXISTS hero_slides (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(160) NOT NULL,
    subtitle TEXT NULL,
    button_text VARCHAR(80) NOT NULL DEFAULT 'Explorar Catálogo',
    button_url VARCHAR(255) NOT NULL DEFAULT 'productos.php',
    image_url VARCHAR(500) NOT NULL,
    orden INT NOT NULL DEFAULT 0,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conexion->query("CREATE TABLE IF NOT EXISTS hero_promociones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(160) NOT NULL,
    subtitle TEXT NULL,
    button_text VARCHAR(80) NOT NULL DEFAULT 'Ver promoción',
    button_url VARCHAR(255) NOT NULL DEFAULT 'productos.php',
    image_url VARCHAR(500) NOT NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$countSlides = $conexion->query("SELECT COUNT(*) c FROM hero_slides");
$slidesCount = $countSlides ? (int)($countSlides->fetch_assoc()['c'] ?? 0) : 0;
if ($slidesCount === 0) {
    $defaults = [
        ['La mejor experiencia de streaming está aquí', 'Accede a miles de productos digitales con total seguridad, soporte 24/7 y los mejores precios del mercado.', 'Explorar Catálogo', 'productos.php', 'https://images.unsplash.com/photo-1536440136628-849c177e76a1?ixlib=rb-4.0.3&auto=format&fit=crop&w=1925&q=80', 1],
        ['Entretenimiento premium para todos', 'Encuentra cuentas, perfiles y servicios digitales listos para usar en minutos.', 'Ver Productos', 'productos.php', 'https://images.unsplash.com/photo-1574375927938-d5a98e8ffe85?ixlib=rb-4.0.3&auto=format&fit=crop&w=1925&q=80', 2],
        ['Compra rápido y con soporte', 'Tu saldo, tus compras y tus accesos protegidos desde un solo lugar.', 'Recargar Saldo', 'recargar.php', 'https://images.unsplash.com/photo-1522869635100-9f4c5e86aa37?ixlib=rb-4.0.3&auto=format&fit=crop&w=1925&q=80', 3],
    ];
    $stSeed = $conexion->prepare("INSERT INTO hero_slides (title, subtitle, button_text, button_url, image_url, orden, activo) VALUES (?, ?, ?, ?, ?, ?, 1)");
    foreach ($defaults as $row) {
        $stSeed->bind_param("sssssi", $row[0], $row[1], $row[2], $row[3], $row[4], $row[5]);
        $stSeed->execute();
    }
    $stSeed->close();
}

if (empty($_SESSION['_csrf_hero'])) {
    $_SESSION['_csrf_hero'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['_csrf_hero'];
$success = $_SESSION['hero_success'] ?? '';
$error = $_SESSION['hero_error'] ?? '';
unset($_SESSION['hero_success'], $_SESSION['hero_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['_csrf'] ?? ''))) {
        $_SESSION['hero_error'] = 'Token inválido. Recarga e intenta de nuevo.';
        header('Location: carrusel.php');
        exit;
    }

    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'save_slide') {
            $id = (int)($_POST['id'] ?? 0);
            $title = trim((string)($_POST['title'] ?? ''));
            $subtitle = trim((string)($_POST['subtitle'] ?? ''));
            $buttonText = trim((string)($_POST['button_text'] ?? 'Explorar Catálogo'));
            $buttonUrl = trim((string)($_POST['button_url'] ?? 'productos.php'));
            $orden = (int)($_POST['orden'] ?? 0);
            $activo = isset($_POST['activo']) ? 1 : 0;
            $imageUrl = trim((string)($_POST['image_url'] ?? ''));
            $upload = heroUploadImage($_FILES['image_file'] ?? []);
            if (!$upload['ok']) throw new Exception($upload['error']);
            if ($upload['path'] !== '') $imageUrl = $upload['path'];
            if ($title === '' || $imageUrl === '') throw new Exception('Título e imagen son obligatorios.');

            if ($id > 0) {
                $st = $conexion->prepare("UPDATE hero_slides SET title=?, subtitle=?, button_text=?, button_url=?, image_url=?, orden=?, activo=? WHERE id=?");
                $st->bind_param("sssssiii", $title, $subtitle, $buttonText, $buttonUrl, $imageUrl, $orden, $activo, $id);
            } else {
                $st = $conexion->prepare("INSERT INTO hero_slides (title, subtitle, button_text, button_url, image_url, orden, activo) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $st->bind_param("sssssii", $title, $subtitle, $buttonText, $buttonUrl, $imageUrl, $orden, $activo);
            }
            $st->execute();
            $st->close();
            $_SESSION['hero_success'] = 'Slide guardado.';
        } elseif ($action === 'delete_slide') {
            $id = (int)($_POST['id'] ?? 0);
            $st = $conexion->prepare("DELETE FROM hero_slides WHERE id=?");
            $st->bind_param("i", $id);
            $st->execute();
            $st->close();
            $_SESSION['hero_success'] = 'Slide eliminado.';
        } elseif ($action === 'save_promo') {
            $title = trim((string)($_POST['title'] ?? ''));
            $subtitle = trim((string)($_POST['subtitle'] ?? ''));
            $buttonText = trim((string)($_POST['button_text'] ?? 'Ver promoción'));
            $buttonUrl = trim((string)($_POST['button_url'] ?? 'productos.php'));
            $startsAt = heroDateTime($_POST['starts_at'] ?? '');
            $endsAt = heroDateTime($_POST['ends_at'] ?? '');
            $activo = isset($_POST['activo']) ? 1 : 0;
            $imageUrl = trim((string)($_POST['image_url'] ?? ''));
            $upload = heroUploadImage($_FILES['image_file'] ?? []);
            if (!$upload['ok']) throw new Exception($upload['error']);
            if ($upload['path'] !== '') $imageUrl = $upload['path'];
            if ($title === '' || $imageUrl === '' || !$startsAt || !$endsAt) throw new Exception('Completa título, imagen, inicio y fin.');
            if (strtotime($endsAt) <= strtotime($startsAt)) throw new Exception('La fecha final debe ser mayor que la inicial.');

            if ($activo) $conexion->query("UPDATE hero_promociones SET activo=0");
            $st = $conexion->prepare("INSERT INTO hero_promociones (title, subtitle, button_text, button_url, image_url, starts_at, ends_at, activo) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $st->bind_param("sssssssi", $title, $subtitle, $buttonText, $buttonUrl, $imageUrl, $startsAt, $endsAt, $activo);
            $st->execute();
            $st->close();
            $_SESSION['hero_success'] = 'Promoción creada.';
        } elseif ($action === 'toggle_promo') {
            $id = (int)($_POST['id'] ?? 0);
            $activo = (int)($_POST['activo'] ?? 0) ? 0 : 1;
            if ($activo) $conexion->query("UPDATE hero_promociones SET activo=0");
            $st = $conexion->prepare("UPDATE hero_promociones SET activo=? WHERE id=?");
            $st->bind_param("ii", $activo, $id);
            $st->execute();
            $st->close();
            $_SESSION['hero_success'] = $activo ? 'Promoción activada.' : 'Promoción desactivada.';
        } elseif ($action === 'delete_promo') {
            $id = (int)($_POST['id'] ?? 0);
            $st = $conexion->prepare("DELETE FROM hero_promociones WHERE id=?");
            $st->bind_param("i", $id);
            $st->execute();
            $st->close();
            $_SESSION['hero_success'] = 'Promoción eliminada.';
        }
    } catch (Throwable $e) {
        $_SESSION['hero_error'] = $e->getMessage();
    }

    header('Location: carrusel.php');
    exit;
}

$slides = [];
$rs = $conexion->query("SELECT * FROM hero_slides ORDER BY orden ASC, id ASC");
while ($rs && ($row = $rs->fetch_assoc())) $slides[] = $row;

$promos = [];
$rs = $conexion->query("SELECT *, (activo=1 AND starts_at <= NOW() AND ends_at > NOW()) AS vigente FROM hero_promociones ORDER BY id DESC LIMIT 30");
while ($rs && ($row = $rs->fetch_assoc())) $promos[] = $row;

$adminName = $_SESSION['user_name'] ?? $_SESSION['admin_name'] ?? 'Administrador';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Carrusel - Admin</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/panel-shell.css?v=admin-polish-4">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;800&display=swap');
    *{box-sizing:border-box;margin:0;padding:0;font-family:Inter,sans-serif}
    body{background:linear-gradient(135deg,#0d0f14,#11131a 45%,#0b0c11);color:#e5e5e5;min-height:100vh}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start}
    .card{background:rgba(255,255,255,.045);border:1px solid rgba(255,255,255,.08);border-radius:14px;padding:16px}
    .top-actions{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px}
    .btn{border:0;border-radius:10px;padding:10px 13px;font-weight:900;text-decoration:none;display:inline-flex;gap:8px;align-items:center;justify-content:center;cursor:pointer}
    .btn.primary{background:linear-gradient(135deg,#12aaff,#0de0c9);color:#0d0f14}.btn.secondary{background:rgba(255,255,255,.06);color:#fff;border:1px solid rgba(255,255,255,.12)}.btn.danger{background:rgba(255,59,48,.14);color:#ff6b6b;border:1px solid rgba(255,59,48,.35)}
    label{display:block;color:#ccc;font-weight:800;margin:10px 0 5px;font-size:.86rem}
    input,textarea{width:100%;padding:10px 12px;border-radius:10px;border:1px solid rgba(255,255,255,.12);background:rgba(0,0,0,.32);color:#fff}
    textarea{min-height:78px;resize:vertical}.row{display:grid;grid-template-columns:1fr 1fr;gap:10px}.checks{display:flex;gap:12px;align-items:center;margin-top:10px}.checks input{width:auto}
    .alert{padding:11px 13px;border-radius:11px;margin-bottom:12px;font-weight:800}.ok{background:rgba(52,199,89,.13);border:1px solid rgba(52,199,89,.34);color:#34c759}.err{background:rgba(255,59,48,.13);border:1px solid rgba(255,59,48,.34);color:#ff6b6b}
    .list{display:grid;gap:12px}.item{display:grid;grid-template-columns:112px 1fr;gap:12px;padding:10px;border-radius:12px;background:rgba(0,0,0,.2);border:1px solid rgba(255,255,255,.07)}
    .preview{width:112px;aspect-ratio:16/9;border-radius:10px;background:#101722 center/cover no-repeat;border:1px solid rgba(255,255,255,.08)}.muted{color:#aaa;font-size:.82rem}.badge{display:inline-flex;border-radius:999px;padding:4px 8px;font-size:.72rem;font-weight:900}.on{background:rgba(52,199,89,.16);color:#34c759}.off{background:rgba(255,59,48,.14);color:#ff6b6b}.warn{background:rgba(255,174,44,.15);color:#ffae2c}
    .item-actions{display:flex;gap:7px;flex-wrap:wrap;margin-top:8px}.item-actions form{display:inline-flex}
    @media(max-width:980px){.grid{grid-template-columns:1fr}.row{grid-template-columns:1fr}.item{grid-template-columns:1fr}.preview{width:100%}}
  </style>
  <link rel="stylesheet" href="../assets/css/mobile-urgent.css?v=20260612c">
</head>
<body>
<?php renderAdminSidebar($conexion, 'carrusel.php'); ?>
<main class="admin-main">
  <header class="admin-header">
    <div class="header-title">
      <h1>Carrusel</h1>
      <p>Controla las imágenes del inicio y las promociones temporales.</p>
    </div>
    <div class="header-actions"><div class="user-menu"><div class="user-avatar"><i class="fas fa-user-cog"></i></div><div class="user-info"><div class="user-name"><?php echo heroH($adminName); ?></div><div class="user-role">ADMIN</div></div></div></div>
  </header>
  <div class="admin-content">
    <div class="top-actions">
      <a class="btn secondary" href="index.php"><i class="fas fa-arrow-left"></i> Dashboard</a>
      <a class="btn" href="../index.php"><i class="fas fa-eye"></i> Ver inicio</a>
    </div>
    <?php if ($success): ?><div class="alert ok"><i class="fas fa-check-circle"></i> <?php echo heroH($success); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert err"><i class="fas fa-triangle-exclamation"></i> <?php echo heroH($error); ?></div><?php endif; ?>

    <div class="grid">
      <section class="card">
        <h2>Agregar imagen al carrusel</h2>
        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="_csrf" value="<?php echo heroH($csrf); ?>">
          <input type="hidden" name="action" value="save_slide">
          <label>Título</label><input name="title" required>
          <label>Texto</label><textarea name="subtitle"></textarea>
          <div class="row"><div><label>Texto del botón</label><input name="button_text" value="Explorar Catálogo"></div><div><label>URL del botón</label><input name="button_url" value="productos.php"></div></div>
          <div class="row"><div><label>Orden</label><input type="number" name="orden" value="0"></div><div><label>Imagen</label><input type="file" name="image_file" accept="image/*"></div></div>
          <label>O URL de imagen</label><input name="image_url" placeholder="https://...">
          <div class="checks"><label><input type="checkbox" name="activo" checked> Activo</label></div>
          <button class="btn primary" style="margin-top:12px" type="submit"><i class="fas fa-save"></i> Guardar slide</button>
        </form>
      </section>

      <section class="card">
        <h2>Crear promoción temporal</h2>
        <p class="muted">Mientras esté vigente, reemplaza el carrusel completo. Al terminar, vuelve el carrusel normal.</p>
        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="_csrf" value="<?php echo heroH($csrf); ?>">
          <input type="hidden" name="action" value="save_promo">
          <label>Título</label><input name="title" required>
          <label>Texto</label><textarea name="subtitle"></textarea>
          <div class="row"><div><label>Texto del botón</label><input name="button_text" value="Ver promoción"></div><div><label>URL del botón</label><input name="button_url" value="productos.php"></div></div>
          <div class="row"><div><label>Inicia</label><input type="datetime-local" name="starts_at" required></div><div><label>Termina</label><input type="datetime-local" name="ends_at" required></div></div>
          <label>Imagen</label><input type="file" name="image_file" accept="image/*">
          <label>O URL de imagen</label><input name="image_url" placeholder="https://...">
          <div class="checks"><label><input type="checkbox" name="activo" checked> Activar promoción</label></div>
          <button class="btn primary" style="margin-top:12px" type="submit"><i class="fas fa-bolt"></i> Crear promoción</button>
        </form>
      </section>
    </div>

    <div class="grid" style="margin-top:16px">
      <section class="card">
        <h2>Slides actuales</h2>
        <div class="list">
          <?php foreach ($slides as $s): ?>
            <article class="item">
              <div class="preview" style="background-image:url('../<?php echo heroH($s['image_url']); ?>'), url('<?php echo heroH($s['image_url']); ?>')"></div>
              <div>
                <strong><?php echo heroH($s['title']); ?></strong>
                <div class="muted"><?php echo heroH($s['subtitle']); ?></div>
                <div><span class="badge <?php echo (int)$s['activo'] ? 'on' : 'off'; ?>"><?php echo (int)$s['activo'] ? 'activo' : 'inactivo'; ?></span> <span class="badge warn">orden <?php echo (int)$s['orden']; ?></span></div>
                <form method="post" enctype="multipart/form-data" style="margin-top:8px">
                  <input type="hidden" name="_csrf" value="<?php echo heroH($csrf); ?>"><input type="hidden" name="action" value="save_slide"><input type="hidden" name="id" value="<?php echo (int)$s['id']; ?>">
                  <div class="row"><input name="title" value="<?php echo heroH($s['title']); ?>"><input name="orden" type="number" value="<?php echo (int)$s['orden']; ?>"></div>
                  <textarea name="subtitle"><?php echo heroH($s['subtitle']); ?></textarea>
                  <div class="row"><input name="button_text" value="<?php echo heroH($s['button_text']); ?>"><input name="button_url" value="<?php echo heroH($s['button_url']); ?>"></div>
                  <input name="image_url" value="<?php echo heroH($s['image_url']); ?>">
                  <input type="file" name="image_file" accept="image/*">
                  <div class="item-actions"><label class="muted"><input type="checkbox" name="activo" <?php echo (int)$s['activo'] ? 'checked' : ''; ?>> Activo</label><button class="btn secondary" type="submit"><i class="fas fa-save"></i> Actualizar</button></div>
                </form>
                <form method="post" class="item-actions" onsubmit="return confirm('¿Eliminar este slide?')"><input type="hidden" name="_csrf" value="<?php echo heroH($csrf); ?>"><input type="hidden" name="action" value="delete_slide"><input type="hidden" name="id" value="<?php echo (int)$s['id']; ?>"><button class="btn danger" type="submit"><i class="fas fa-trash"></i> Eliminar</button></form>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="card">
        <h2>Promociones</h2>
        <div class="list">
          <?php foreach ($promos as $p): ?>
            <article class="item">
              <div class="preview" style="background-image:url('../<?php echo heroH($p['image_url']); ?>'), url('<?php echo heroH($p['image_url']); ?>')"></div>
              <div>
                <strong><?php echo heroH($p['title']); ?></strong>
                <div class="muted"><?php echo heroH($p['starts_at']); ?> hasta <?php echo heroH($p['ends_at']); ?></div>
                <div><span class="badge <?php echo (int)$p['activo'] ? 'on' : 'off'; ?>"><?php echo (int)$p['activo'] ? 'activa' : 'inactiva'; ?></span> <?php if ((int)$p['vigente']): ?><span class="badge warn">visible ahora</span><?php endif; ?></div>
                <div class="item-actions">
                  <form method="post"><input type="hidden" name="_csrf" value="<?php echo heroH($csrf); ?>"><input type="hidden" name="action" value="toggle_promo"><input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>"><input type="hidden" name="activo" value="<?php echo (int)$p['activo']; ?>"><button class="btn secondary" type="submit"><?php echo (int)$p['activo'] ? 'Desactivar' : 'Activar'; ?></button></form>
                  <form method="post" onsubmit="return confirm('¿Eliminar esta promoción?')"><input type="hidden" name="_csrf" value="<?php echo heroH($csrf); ?>"><input type="hidden" name="action" value="delete_promo"><input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>"><button class="btn danger" type="submit"><i class="fas fa-trash"></i></button></form>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
          <?php if (!$promos): ?><p class="muted">Aún no hay promociones creadas.</p><?php endif; ?>
        </div>
      </section>
    </div>
  </div>
</main>
</body>
</html>
