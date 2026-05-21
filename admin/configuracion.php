<?php
// admin/configuracion.php — Configuración (métodos de pago)
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/sidebar.php';

/**
 * Protección REAL admin
 */
if (function_exists('requireAdmin')) {
    requireAdmin();
} else {
    if (!function_exists('isLoggedIn') || !function_exists('getCurrentUser')) {
        http_response_code(500);
        die('Faltan helpers de sesión (isLoggedIn/getCurrentUser).');
    }
    if (!isLoggedIn()) redirect('../login.php');

    $u = getCurrentUser();
    $role = strtolower((string)($u['role'] ?? $u['rol'] ?? $u['user_role'] ?? ''));
    if ($role !== 'admin') {
        http_response_code(403);
        die('Acceso denegado: solo administradores.');
    }
}

/** ===== Utilidades compatibilidad BD ===== */
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
    foreach ($candidates as $t) if (tableExists($cx, $t)) return $t;
    return null;
}
function pickCol(mysqli $cx, string $table, array $candidates, ?string $default = null): ?string {
    foreach ($candidates as $c) if (colExists($cx, $table, $c)) return $c;
    return $default;
}
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function toFloat($v): float { return is_numeric($v) ? (float)$v : 0.0; }
function cleanStr($v): string { return trim((string)$v); }

/** ===== CSRF simple ===== */
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['_csrf_admin_cfg'])) {
    $_SESSION['_csrf_admin_cfg'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['_csrf_admin_cfg'];

/** ===== Detectar tabla ===== */
$TABLE_PM = pickTable($conexion, [
    'metodos_pago',
    'payment_methods',
    'metodos',
    'metodos_de_pago',
    'formas_pago'
]);

if (!$TABLE_PM) {
    http_response_code(500);
    die("No encuentro una tabla de métodos de pago (metodos_pago/payment_methods/etc.).");
}

/** ===== Detectar columnas ===== */
$C_ID       = pickCol($conexion, $TABLE_PM, ['id'], 'id');
$C_CLAVE    = pickCol($conexion, $TABLE_PM, ['clave','code','slug','key'], null);
$C_NOMBRE   = pickCol($conexion, $TABLE_PM, ['nombre','name','titulo','title'], null);
$C_DESC     = pickCol($conexion, $TABLE_PM, ['descripcion','description','detalle','details'], null);
$C_ICONO    = pickCol($conexion, $TABLE_PM, ['icono','icon','fa_icon','icon_class'], null);
$C_TIEMPO   = pickCol($conexion, $TABLE_PM, ['tiempo','time','processing_time'], null);
$C_COM_PCT  = pickCol($conexion, $TABLE_PM, ['comision_porcentaje','commission_percent','comision_pct','porcentaje_comision'], null);
$C_COM_FIJA = pickCol($conexion, $TABLE_PM, ['comision_fija','commission_fixed','monto_comision','comision_monto'], null);
$C_COM_TXT  = pickCol($conexion, $TABLE_PM, ['comision','commission_text'], null);
$C_INST     = pickCol($conexion, $TABLE_PM, ['instrucciones','instructions','texto','texto_instrucciones'], null);
$C_IMG      = pickCol($conexion, $TABLE_PM, ['imagen','image','imagen_url','image_url','logo'], null);
$C_ACTIVO   = pickCol($conexion, $TABLE_PM, ['activo','is_active','active'], null);
$C_ORDEN    = pickCol($conexion, $TABLE_PM, ['orden','sort_order','ordering','posicion'], null);

/** ===== Helpers de upload imagen ===== */
function ensureDir(string $dir): void {
    if (!is_dir($dir)) mkdir($dir, 0755, true);
}
function uploadMetodoImage(array $file, string $destDirRel = 'uploads/metodos/', int $maxBytes = 2097152): array {
    // return ['ok'=>bool, 'path'=>string, 'error'=>string]
    if (!isset($file['tmp_name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok'=>false, 'path'=>'', 'error'=>''];
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['ok'=>false, 'path'=>'', 'error'=>'Error de subida (código '.$file['error'].')'];
    }

    if (($file['size'] ?? 0) > $maxBytes) {
        return ['ok'=>false, 'path'=>'', 'error'=>'La imagen supera el tamaño máximo (2MB).'];
    }

    $allowedExt = ['jpg','jpeg','png','webp'];
    $name = (string)($file['name'] ?? '');
    $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));

    if (!in_array($ext, $allowedExt, true)) {
        return ['ok'=>false, 'path'=>'', 'error'=>'Extensión no permitida. Usa JPG, PNG o WEBP.'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowedMime = ['image/jpeg','image/png','image/webp'];
    if (!in_array($mime, $allowedMime, true)) {
        return ['ok'=>false, 'path'=>'', 'error'=>'MIME no válido (archivo no parece imagen real).'];
    }

    $destDirRel = rtrim($destDirRel, '/').'/';
    $destDirAbs = __DIR__ . '/../' . $destDirRel;
    ensureDir($destDirAbs);

    $rand = bin2hex(random_bytes(12));
    $fileName = "metodo_$rand.$ext";
    $destAbs = $destDirAbs . $fileName;
    $destRel = $destDirRel . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $destAbs)) {
        return ['ok'=>false, 'path'=>'', 'error'=>'No se pudo guardar la imagen en el servidor.'];
    }

    return ['ok'=>true, 'path'=>$destRel, 'error'=>''];
}

/** ===== Acciones ===== */
$flash_ok = '';
$flash_er = '';

$action = (string)($_GET['action'] ?? '');
$editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

/** Toggle activo */
if ($action === 'toggle' && $editId > 0 && $_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!$C_ACTIVO) {
        $flash_er = 'Tu tabla no tiene columna activo/is_active.';
    } else {
        $st = $conexion->prepare("SELECT `$C_ACTIVO` AS activo FROM `$TABLE_PM` WHERE `$C_ID`=? LIMIT 1");
        $st->bind_param("i", $editId);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        if (!$row) {
            $flash_er = 'Método no encontrado.';
        } else {
            $newVal = ((int)$row['activo'] === 1) ? 0 : 1;
            $st2 = $conexion->prepare("UPDATE `$TABLE_PM` SET `$C_ACTIVO`=? WHERE `$C_ID`=?");
            $st2->bind_param("ii", $newVal, $editId);
            if ($st2->execute()) $flash_ok = 'Estado actualizado.';
            else $flash_er = 'No se pudo actualizar estado.';
        }
    }
}

/** Guardar (crear/editar) */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['_csrf'] ?? '');
    if ($token !== $csrf) {
        $flash_er = 'Token inválido (CSRF).';
    } else {
        $do = (string)($_POST['do'] ?? '');

        if ($do === 'save') {
            $id = (int)($_POST['id'] ?? 0);

            $clave  = cleanStr($_POST['clave'] ?? '');
            $nombre = cleanStr($_POST['nombre'] ?? '');
            $desc   = cleanStr($_POST['descripcion'] ?? '');
            $icono  = cleanStr($_POST['icono'] ?? '');
            $tiempo = cleanStr($_POST['tiempo'] ?? '');
            $comPct = toFloat($_POST['comision_porcentaje'] ?? 0);
            $comFix = toFloat($_POST['comision_fija'] ?? 0);
            $comTxt = cleanStr($_POST['comision_texto'] ?? '');
            $inst   = trim((string)($_POST['instrucciones'] ?? ''));
            $activo = isset($_POST['activo']) ? 1 : 0;
            $orden  = (int)($_POST['orden'] ?? 0);

            // Validaciones mínimas
            if ($C_CLAVE && $clave === '') $flash_er = 'La clave es obligatoria.';
            elseif ($C_NOMBRE && $nombre === '') $flash_er = 'El nombre es obligatorio.';
            elseif ($comPct < 0 || $comPct > 100) $flash_er = 'La comisión % debe estar entre 0 y 100.';
            elseif ($comFix < 0) $flash_er = 'La comisión fija no puede ser negativa.';
            else {
                // Upload imagen (opcional)
                $newImgPath = '';
                if ($C_IMG && isset($_FILES['imagen_file'])) {
                    $up = uploadMetodoImage($_FILES['imagen_file']);
                    if (!$up['ok'] && $up['error'] !== '') {
                        $flash_er = $up['error'];
                    } elseif ($up['ok']) {
                        $newImgPath = $up['path'];
                    }
                }

                if ($flash_er === '') {
                    // Armar SETs según columnas disponibles
                    $cols = [];
                    $vals = [];
                    $types = '';

                    $add = function(?string $col, string $type, $val) use (&$cols,&$vals,&$types) {
                        if ($col) { $cols[] = "`$col`=?"; $vals[] = $val; $types .= $type; }
                    };

                    $add($C_CLAVE,    's', $clave);
                    $add($C_NOMBRE,   's', $nombre);
                    $add($C_DESC,     's', $desc);
                    $add($C_ICONO,    's', $icono);
                    $add($C_TIEMPO,   's', $tiempo);
                    $add($C_COM_PCT,  'd', $comPct);
                    $add($C_COM_FIJA, 'd', $comFix);
                    $add($C_COM_TXT,  's', $comTxt);
                    $add($C_INST,     's', $inst);
                    if ($C_IMG && $newImgPath !== '') $add($C_IMG, 's', $newImgPath);
                    if ($C_ACTIVO) $add($C_ACTIVO, 'i', $activo);
                    if ($C_ORDEN)  $add($C_ORDEN,  'i', $orden);

                    if (empty($cols)) {
                        $flash_er = 'No hay columnas compatibles para guardar.';
                    } else {
                        if ($id > 0) {
                            $sql = "UPDATE `$TABLE_PM` SET ".implode(', ', $cols)." WHERE `$C_ID`=?";
                            $vals[] = $id;
                            $types .= 'i';
                            $st = $conexion->prepare($sql);
                            $st->bind_param($types, ...$vals);
                            if ($st->execute()) {
                                $flash_ok = 'Método actualizado.';
                                header("Location: configuracion.php");
                                exit;
                            } else {
                                $flash_er = 'No se pudo actualizar el método.';
                            }
                        } else {
                            // Insert
                            $sql = "INSERT INTO `$TABLE_PM` SET ".implode(', ', $cols);
                            $st = $conexion->prepare($sql);
                            $st->bind_param($types, ...$vals);
                            if ($st->execute()) {
                                $flash_ok = 'Método creado.';
                                header("Location: configuracion.php");
                                exit;
                            } else {
                                $flash_er = 'No se pudo crear el método.';
                            }
                        }
                    }
                }
            }
        }

        if ($do === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) $flash_er = 'ID inválido.';
            else {
                // Recomendación: no borrar, solo desactivar
                if ($C_ACTIVO) {
                    $st = $conexion->prepare("UPDATE `$TABLE_PM` SET `$C_ACTIVO`=0 WHERE `$C_ID`=?");
                    $st->bind_param("i", $id);
                    if ($st->execute()) {
                        $flash_ok = 'Método desactivado.';
                        header("Location: configuracion.php");
                        exit;
                    } else $flash_er = 'No se pudo desactivar.';
                } else {
                    $st = $conexion->prepare("DELETE FROM `$TABLE_PM` WHERE `$C_ID`=?");
                    $st->bind_param("i", $id);
                    if ($st->execute()) {
                        $flash_ok = 'Método eliminado.';
                        header("Location: configuracion.php");
                        exit;
                    } else $flash_er = 'No se pudo eliminar.';
                }
            }
        }
    }
}

/** ===== Cargar registro para editar ===== */
$editRow = null;
if ($action === 'edit' && $editId > 0) {
    $st = $conexion->prepare("SELECT * FROM `$TABLE_PM` WHERE `$C_ID`=? LIMIT 1");
    $st->bind_param("i", $editId);
    $st->execute();
    $editRow = $st->get_result()->fetch_assoc();
}

/** ===== Listado ===== */
$order = $C_ORDEN ? "`$C_ORDEN` ASC, `$C_ID` DESC" : "`$C_ID` DESC";
$rs = $conexion->query("SELECT * FROM `$TABLE_PM` ORDER BY $order");

/** ===== Defaults form ===== */
$def = function($col, $fallback='') use ($editRow) {
    if (!$editRow) return $fallback;
    return $editRow[$col] ?? $fallback;
};

$page_title = "Configuración - Admin - Monkeystraming";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($page_title); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/panel-shell.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        *{margin:0;padding:0;box-sizing:border-box;font-family:'Inter',sans-serif}
        body{background:linear-gradient(135deg,#0d0f14 0%,#11131a 35%,#0b0c11 100%);color:#e5e5e5;min-height:100vh;padding:30px 30px 30px 302px}
        .topbar{display:flex;gap:15px;align-items:center;justify-content:space-between;flex-wrap:wrap;margin-bottom:18px}
        .title h1{font-size:1.6rem;color:#fff}
        .title p{color:#aaa;margin-top:4px}
        .btn{border:none;cursor:pointer;border-radius:12px;padding:10px 14px;font-weight:900;background:linear-gradient(135deg,#12aaff,#0de0c9);color:#0d0f14;text-decoration:none;display:inline-flex;gap:8px;align-items:center}
        .btn.secondary{background:rgba(255,255,255,0.06);color:#fff;border:1px solid rgba(255,255,255,0.10)}
        .btn.danger{background:rgba(255,59,48,0.12);color:#ff3b30;border:1px solid rgba(255,59,48,0.35)}
        .btn.small{padding:8px 10px;border-radius:12px}
        .card{background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:18px;padding:18px;backdrop-filter:blur(10px)}
        .grid{display:grid;grid-template-columns: 1.2fr 0.8fr; gap:16px; align-items:start}
        @media (max-width: 980px){ .grid{grid-template-columns:1fr} }
        label{display:block;color:#ccc;font-size:0.9rem;margin:10px 0 6px}
        input,select,textarea{width:100%;padding:10px 12px;border-radius:12px;outline:none;color:#fff;background:rgba(0,0,0,0.35);border:1px solid rgba(255,255,255,0.12)}
        textarea{min-height:110px;resize:vertical}
        table{width:100%;border-collapse:collapse}
        th,td{padding:12px 10px;text-align:left;border-bottom:1px solid rgba(255,255,255,0.06);vertical-align:top}
        th{color:#aaa;font-size:0.85rem;text-transform:uppercase;letter-spacing:0.5px}
        .muted{color:#777}
        .badge{padding:5px 10px;border-radius:999px;font-size:0.8rem;font-weight:900;display:inline-block}
        .b-ok{background:rgba(52,199,89,0.18);color:#34c759}
        .b-bad{background:rgba(255,59,48,0.18);color:#ff3b30}
        .alert{padding:12px 14px;border-radius:14px;margin-bottom:12px;border:1px solid rgba(255,255,255,0.08);background:rgba(0,0,0,0.25)}
        .alert.ok{border-color:rgba(52,199,89,0.35);background:rgba(52,199,89,0.10);color:#34c759}
        .alert.er{border-color:rgba(255,59,48,0.35);background:rgba(255,59,48,0.10);color:#ff3b30}
        .row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
        @media (max-width: 700px){ .row{grid-template-columns:1fr} }
        @media (max-width: 992px){ body{padding:82px 16px 24px} }
        .pill{display:inline-flex;gap:8px;align-items:center;padding:8px 10px;border-radius:999px;border:1px solid rgba(255,255,255,0.10);background:rgba(255,255,255,0.05)}
        img.thumb{width:54px;height:54px;border-radius:12px;object-fit:cover;border:1px solid rgba(255,255,255,0.10);background:rgba(0,0,0,0.25)}
    </style>
</head>
<body>
<?php renderAdminSidebar($conexion, 'configuracion.php'); ?>

<div class="topbar">
    <div class="title">
        <h1><i class="fas fa-cog"></i> Configuración</h1>
        <p>Gestión de métodos de pago (comisiones, instrucciones, imágenes, activo, orden).</p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <a class="btn secondary" href="index.php"><i class="fas fa-arrow-left"></i> Dashboard</a>
        <a class="btn" href="../index.php"><i class="fas fa-globe"></i> Ver sitio</a>
    </div>
</div>

<?php if ($flash_ok): ?><div class="alert ok"><i class="fas fa-check-circle"></i> <?php echo h($flash_ok); ?></div><?php endif; ?>
<?php if ($flash_er): ?><div class="alert er"><i class="fas fa-exclamation-circle"></i> <?php echo h($flash_er); ?></div><?php endif; ?>

<div class="grid">
    <!-- LISTA -->
    <div class="card">
        <div style="display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap;margin-bottom:10px;">
            <span class="pill"><i class="fas fa-database"></i> Tabla: <strong><?php echo h($TABLE_PM); ?></strong></span>
            <a class="btn secondary" href="configuracion.php"><i class="fas fa-sync"></i> Recargar</a>
        </div>

        <div style="overflow:auto;">
            <table>
                <thead>
                    <tr>
                        <th style="width:80px;">ID</th>
                        <?php if ($C_ORDEN): ?><th style="width:90px;">Orden</th><?php endif; ?>
                        <?php if ($C_IMG): ?><th style="width:80px;">Img</th><?php endif; ?>
                        <th>Método</th>
                        <?php if ($C_CLAVE): ?><th>Clave</th><?php endif; ?>
                        <?php if ($C_COM_PCT || $C_COM_FIJA): ?><th>Comisión</th><?php endif; ?>
                        <?php if ($C_ACTIVO): ?><th style="width:110px;">Activo</th><?php endif; ?>
                        <th style="width:190px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($rs && $rs->num_rows > 0): ?>
                    <?php while($m = $rs->fetch_assoc()): ?>
                        <?php
                            $id = (int)($m[$C_ID] ?? 0);
                            $nombre = $C_NOMBRE ? ($m[$C_NOMBRE] ?? '') : ('#'.$id);
                            $clave  = $C_CLAVE ? ($m[$C_CLAVE] ?? '') : '';
                            $activo = $C_ACTIVO ? (int)($m[$C_ACTIVO] ?? 0) : 1;
                            $orden  = $C_ORDEN ? (int)($m[$C_ORDEN] ?? 0) : 0;
                            $img    = $C_IMG ? (string)($m[$C_IMG] ?? '') : '';
                            $pct    = $C_COM_PCT ? (float)($m[$C_COM_PCT] ?? 0) : null;
                            $fix    = $C_COM_FIJA ? (float)($m[$C_COM_FIJA] ?? 0) : null;
                        ?>
                        <tr>
                            <td>#<?php echo $id; ?></td>

                            <?php if ($C_ORDEN): ?>
                                <td><?php echo $orden; ?></td>
                            <?php endif; ?>

                            <?php if ($C_IMG): ?>
                                <td>
                                    <?php if ($img): ?>
                                        <img class="thumb" src="../<?php echo h($img); ?>" alt="img">
                                    <?php else: ?>
                                        <div class="muted">—</div>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>

                            <td>
                                <div style="font-weight:900;color:#fff;"><?php echo h($nombre); ?></div>
                                <?php if ($C_DESC && !empty($m[$C_DESC])): ?>
                                    <div class="muted" style="margin-top:4px;font-size:0.85rem;">
                                        <?php echo h(mb_strimwidth((string)$m[$C_DESC], 0, 90, '…', 'UTF-8')); ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <?php if ($C_CLAVE): ?>
                                <td><?php echo h($clave); ?></td>
                            <?php endif; ?>

                            <?php if ($C_COM_PCT || $C_COM_FIJA): ?>
                                <td class="muted">
                                    <?php
                                        $parts = [];
                                        if ($C_COM_PCT)  $parts[] = number_format((float)$pct, 2).'%';
                                        if ($C_COM_FIJA) $parts[] = 'S/ '.number_format((float)$fix, 2);
                                        echo h(implode(' + ', $parts));
                                    ?>
                                </td>
                            <?php endif; ?>

                            <?php if ($C_ACTIVO): ?>
                                <td>
                                    <span class="badge <?php echo $activo ? 'b-ok' : 'b-bad'; ?>">
                                        <?php echo $activo ? 'Sí' : 'No'; ?>
                                    </span>
                                </td>
                            <?php endif; ?>

                            <td>
                                <a class="btn secondary small" href="configuracion.php?action=edit&id=<?php echo $id; ?>">
                                    <i class="fas fa-edit"></i> Editar
                                </a>
                                <?php if ($C_ACTIVO): ?>
                                    <a class="btn secondary small" href="configuracion.php?action=toggle&id=<?php echo $id; ?>">
                                        <i class="fas fa-power-off"></i> <?php echo $activo ? 'Desact.' : 'Activar'; ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="8" class="muted" style="padding:18px;">No hay métodos registrados.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- FORM -->
    <div class="card">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
            <div style="font-size:1.1rem;font-weight:900;color:#fff;">
                <i class="fas fa-sliders-h"></i>
                <?php echo ($editRow ? 'Editar método' : 'Nuevo método'); ?>
            </div>
            <?php if ($editRow): ?>
                <a class="btn secondary" href="configuracion.php"><i class="fas fa-plus"></i> Nuevo</a>
            <?php endif; ?>
        </div>

        <form method="POST" action="configuracion.php" enctype="multipart/form-data" style="margin-top:10px;">
            <input type="hidden" name="_csrf" value="<?php echo h($csrf); ?>">
            <input type="hidden" name="do" value="save">
            <input type="hidden" name="id" value="<?php echo $editRow ? (int)($editRow[$C_ID] ?? 0) : 0; ?>">

            <?php if ($C_CLAVE): ?>
                <label>Clave (identificador interno)</label>
                <input type="text" name="clave" placeholder="Ej: yape, plin, transferencia"
                       value="<?php echo h($editRow ? ($editRow[$C_CLAVE] ?? '') : ''); ?>">
                <div class="muted" style="margin-top:6px;font-size:0.85rem;">Debe ser única. Se usa en recargar.php</div>
            <?php endif; ?>

            <?php if ($C_NOMBRE): ?>
                <label>Nombre</label>
                <input type="text" name="nombre" placeholder="Ej: Yape"
                       value="<?php echo h($editRow ? ($editRow[$C_NOMBRE] ?? '') : ''); ?>">
            <?php endif; ?>

            <?php if ($C_DESC): ?>
                <label>Descripción (opcional)</label>
                <input type="text" name="descripcion" placeholder="Ej: Pago con QR"
                       value="<?php echo h($editRow ? ($editRow[$C_DESC] ?? '') : ''); ?>">
            <?php endif; ?>

            <div class="row">
                <?php if ($C_ICONO): ?>
                    <div>
                        <label>Icono (FontAwesome)</label>
                        <input type="text" name="icono" placeholder="Ej: fas fa-qrcode"
                               value="<?php echo h($editRow ? ($editRow[$C_ICONO] ?? '') : 'fas fa-credit-card'); ?>">
                        <div class="muted" style="margin-top:6px;font-size:0.85rem;">Ej: fas fa-credit-card</div>
                    </div>
                <?php endif; ?>

                <?php if ($C_TIEMPO): ?>
                    <div>
                        <label>Tiempo</label>
                        <input type="text" name="tiempo" placeholder="Ej: Inmediato / 5-10 min"
                               value="<?php echo h($editRow ? ($editRow[$C_TIEMPO] ?? '') : 'Inmediato'); ?>">
                    </div>
                <?php endif; ?>
            </div>

            <div class="row">
                <?php if ($C_COM_PCT): ?>
                    <div>
                        <label>Comisión %</label>
                        <input type="number" step="0.01" min="0" max="100" name="comision_porcentaje"
                               value="<?php echo h($editRow ? ($editRow[$C_COM_PCT] ?? 0) : 0); ?>">
                    </div>
                <?php endif; ?>
                <?php if ($C_COM_FIJA): ?>
                    <div>
                        <label>Comisión fija (S/)</label>
                        <input type="number" step="0.01" min="0" name="comision_fija"
                               value="<?php echo h($editRow ? ($editRow[$C_COM_FIJA] ?? 0) : 0); ?>">
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($C_COM_TXT): ?>
                <label>Texto comisión (opcional)</label>
                <input type="text" name="comision_texto" placeholder="Ej: 3% + S/ 1.00"
                       value="<?php echo h($editRow ? ($editRow[$C_COM_TXT] ?? '') : ''); ?>">
                <div class="muted" style="margin-top:6px;font-size:0.85rem;">Solo para mostrar (si tu UI lo usa)</div>
            <?php endif; ?>

            <?php if ($C_ORDEN): ?>
                <label>Orden</label>
                <input type="number" name="orden" value="<?php echo h($editRow ? ($editRow[$C_ORDEN] ?? 0) : 0); ?>">
                <div class="muted" style="margin-top:6px;font-size:0.85rem;">Menor = aparece primero</div>
            <?php endif; ?>

            <?php if ($C_INST): ?>
                <label>Instrucciones</label>
                <textarea name="instrucciones" placeholder="Ej: 1) Escanea el QR..."><?php echo h($editRow ? ($editRow[$C_INST] ?? '') : ''); ?></textarea>
            <?php endif; ?>

            <?php if ($C_IMG): ?>
                <label>Imagen del método (opcional)</label>
                <?php
                    $currImg = $editRow ? (string)($editRow[$C_IMG] ?? '') : '';
                    if ($currImg):
                ?>
                    <div style="display:flex;gap:12px;align-items:center;margin:8px 0 6px;">
                        <img class="thumb" src="../<?php echo h($currImg); ?>" alt="actual">
                        <div class="muted" style="font-size:0.85rem;">
                            Actual: <strong><?php echo h($currImg); ?></strong><br>
                            Si subes una nueva, se reemplaza la ruta (no borra el archivo viejo).
                        </div>
                    </div>
                <?php endif; ?>
                <input type="file" name="imagen_file" accept="image/jpeg,image/png,image/webp">
                <div class="muted" style="margin-top:6px;font-size:0.85rem;">JPG/PNG/WEBP — máx 2MB — se guarda en <strong>uploads/metodos/</strong></div>
            <?php endif; ?>

            <?php if ($C_ACTIVO): ?>
                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                    <input type="checkbox" name="activo" value="1" <?php
                        $val = $editRow ? (int)($editRow[$C_ACTIVO] ?? 0) : 1;
                        echo $val ? 'checked' : '';
                    ?>>
                    Activo
                </label>
            <?php endif; ?>

            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:14px;">
                <button class="btn" type="submit"><i class="fas fa-save"></i> Guardar</button>

                <?php if ($editRow): ?>
                    <button class="btn danger" type="submit"
                            formaction="configuracion.php"
                            formmethod="POST"
                            onclick="return confirm('¿Seguro? Esto desactivará/eliminará el método.');">
                        <i class="fas fa-trash"></i> Eliminar/Desactivar
                    </button>
                    <input type="hidden" name="do" value="save" id="doSave">
                    <input type="hidden" name="do_delete" value="" id="doDelete">
                <?php endif; ?>

                <a class="btn secondary" href="configuracion.php"><i class="fas fa-times"></i> Cancelar</a>
            </div>

            <?php if ($editRow): ?>
                <script>
                    // Hack simple: convertir botón "Eliminar/Desactivar" en POST do=delete sin duplicar formularios.
                    document.addEventListener('DOMContentLoaded', () => {
                        const btns = document.querySelectorAll('button.btn.danger[type="submit"]');
                        btns.forEach(btn => {
                            btn.addEventListener('click', () => {
                                const doInput = document.querySelector('input[name="do"]');
                                if (doInput) doInput.value = 'delete';
                            });
                        });
                    });
                </script>
            <?php endif; ?>
        </form>

        <div class="muted" style="margin-top:14px;font-size:0.85rem;line-height:1.55;">
            <strong>Nota:</strong> si tu tabla no tiene algunas columnas (ej. instrucciones o imagen),
            el formulario las oculta automáticamente.
        </div>
    </div>
</div>

</body>
</html>
