<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('admin');

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function tableExistsAdminSellers(mysqli $cx, string $table): bool {
    $t = $cx->real_escape_string($table);
    $rs = $cx->query("SHOW TABLES LIKE '$t'");
    return ($rs && $rs->num_rows > 0);
}

$admin = getCurrentUser();
$adminId = (int)($admin['id'] ?? $_SESSION['user_id'] ?? 0);
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim((string)($_POST['nombre'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $whatsapp = trim((string)($_POST['whatsapp'] ?? ''));
    $tienda = trim((string)($_POST['tienda_nombre'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($nombre === '' || strlen($nombre) < 3) {
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

                $st = $conexion->prepare("
                    INSERT INTO usuarios (nombre, email, whatsapp, password, role, saldo, estado, created_by)
                    VALUES (?, ?, ?, ?, ?, 0.00, ?, ?)
                ");
                $st->bind_param("ssssssi", $nombre, $email, $whatsapp, $hash, $role, $estado, $adminId);
                $st->execute();
                $vendedorId = (int)$st->insert_id;
                $st->close();

                if (tableExistsAdminSellers($conexion, 'vendedor_perfiles')) {
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
$sql = "
    SELECT u.id, u.nombre, u.email, u.whatsapp, u.estado, u.created_at,
           vp.tienda_nombre,
           COUNT(DISTINCT p.id) AS productos,
           COUNT(DISTINCT c.id) AS ventas,
           COALESCE(SUM(c.monto), 0) AS vendido
    FROM usuarios u
    LEFT JOIN vendedor_perfiles vp ON vp.vendedor_id = u.id
    LEFT JOIN productos p ON p.vendedor_id = u.id
    LEFT JOIN compras c ON c.vendedor_id = u.id AND c.estado = 'completada'
    WHERE u.role = 'vendedor'
    GROUP BY u.id, u.nombre, u.email, u.whatsapp, u.estado, u.created_at, vp.tienda_nombre
    ORDER BY u.id DESC
";
$rs = $conexion->query($sql);
if ($rs) {
    while ($row = $rs->fetch_assoc()) $vendedores[] = $row;
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
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;800&display=swap');
    *{box-sizing:border-box;margin:0;padding:0;font-family:Inter,sans-serif}
    body{background:linear-gradient(135deg,#0d0f14,#11131a 45%,#0b0c11);color:#e5e5e5;min-height:100vh}
    .wrap{max-width:1200px;margin:0 auto;padding:32px 20px}
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
    table{width:100%;border-collapse:collapse}
    th,td{text-align:left;padding:12px;border-bottom:1px solid rgba(255,255,255,.07)}
    th{color:#9a9a9a;font-size:.86rem}
    @media(max-width:900px){.grid{grid-template-columns:1fr}}
  </style>
</head>
<body>
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

  <section class="grid">
    <form class="card" method="POST">
      <h2 style="color:#fff;margin-bottom:8px;">Nuevo vendedor</h2>
      <p class="muted">El cliente se registra solo; el vendedor lo crea el admin.</p>

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

      <button class="btn primary" style="margin-top:16px;" type="submit"><i class="fas fa-user-plus"></i> Crear vendedor</button>
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
          </tr>
        <?php endforeach; ?>
        <?php if (!$vendedores): ?>
          <tr><td colspan="7" class="muted">Aun no hay vendedores creados.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>
</body>
</html>
