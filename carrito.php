<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin('/login.php');

$page_title = "Carrito - Monkeystraming";
$usuario_actual = getCurrentUser();

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $productId = (int)($_POST['product_id'] ?? 0);

    if ($action === 'remove' && $productId > 0) {
        unset($_SESSION['cart'][$productId]);
        $_SESSION['success_msg'] = 'Producto quitado del carrito.';
        redirect('carrito.php');
    }

    if ($action === 'clear') {
        $_SESSION['cart'] = [];
        $_SESSION['success_msg'] = 'Carrito vaciado.';
        redirect('carrito.php');
    }
}

$cart = array_values($_SESSION['cart']);
$total = 0.0;
foreach ($cart as $item) {
    $total += (float)($item['precio'] ?? 0) * max(1, (int)($item['qty'] ?? 1));
}

$success_msg = $_SESSION['success_msg'] ?? '';
$error_msg = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);
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
    .header{display:flex;justify-content:space-between;align-items:center;gap:18px;flex-wrap:wrap;padding:24px 7%;background:rgba(255,255,255,.035);border-bottom:1px solid rgba(255,255,255,.07)}
    .brand{display:flex;align-items:center;gap:12px;color:#fff;text-decoration:none;font-weight:900;font-size:1.1rem}.brand img{height:48px;width:auto}
    .nav{display:flex;align-items:center;gap:18px;flex-wrap:wrap}.nav a{color:#d0d0d0;text-decoration:none;font-weight:700}.nav a:hover{color:#12aaff}
    .wrap{max-width:1120px;margin:0 auto;padding:34px 20px}.top{display:flex;justify-content:space-between;align-items:flex-end;gap:16px;flex-wrap:wrap;margin-bottom:22px}
    h1{font-size:2rem;color:#fff}.muted{color:#aaa}.grid{display:grid;grid-template-columns:minmax(0,1fr) 330px;gap:18px;align-items:start}
    .card{background:rgba(255,255,255,.045);border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:18px}
    .item{display:grid;grid-template-columns:1fr auto;gap:14px;align-items:center;padding:16px 0;border-bottom:1px solid rgba(255,255,255,.07)}.item:last-child{border-bottom:0}
    .item h3{color:#fff;margin-bottom:6px}.price{font-size:1.15rem;font-weight:900;color:#0de0c9;white-space:nowrap}
    .btn{border:0;border-radius:10px;padding:11px 15px;font-weight:900;text-decoration:none;display:inline-flex;gap:8px;align-items:center;justify-content:center;cursor:pointer}
    .primary{background:linear-gradient(135deg,#12aaff,#0de0c9);color:#0d0f14}.secondary{background:rgba(255,255,255,.06);color:#fff;border:1px solid rgba(255,255,255,.12)}.danger{background:rgba(255,59,48,.14);color:#ff6b6b;border:1px solid rgba(255,59,48,.25)}
    .actions{display:flex;gap:10px;flex-wrap:wrap}.summary-row{display:flex;justify-content:space-between;gap:12px;margin:12px 0;color:#ccc}.summary-total{font-size:1.35rem;color:#fff;font-weight:900}
    .alert{padding:12px;border-radius:12px;margin-bottom:14px}.ok{background:rgba(52,199,89,.14);border:1px solid rgba(52,199,89,.35);color:#34c759}.err{background:rgba(255,59,48,.14);border:1px solid rgba(255,59,48,.35);color:#ff6b6b}
    .empty{text-align:center;padding:54px 18px}.empty i{font-size:3rem;color:#12aaff;margin-bottom:14px}.msg{min-height:22px;margin-top:12px;font-weight:800}
    @media(max-width:850px){.grid{grid-template-columns:1fr}.item{grid-template-columns:1fr}.price{white-space:normal}}
  </style>
</head>
<body>
<header class="header">
  <a class="brand" href="index.php">
    <img src="assets/img/monkylogo.png" alt="Monkeystraming">
    <span>Monkeystraming</span>
  </a>
  <nav class="nav">
    <a href="index.php"><i class="fas fa-home"></i> Inicio</a>
    <a href="productos.php"><i class="fas fa-box-open"></i> Productos</a>
    <a href="recargar.php"><i class="fas fa-coins"></i> Recargar</a>
    <a href="user/dashboard.php"><i class="fas fa-th-large"></i> Mi cuenta</a>
  </nav>
</header>

<main class="wrap">
  <section class="top">
    <div>
      <h1>Carrito</h1>
      <p class="muted">Revisa tus productos antes de completar la compra.</p>
    </div>
    <a class="btn secondary" href="productos.php"><i class="fas fa-arrow-left"></i> Seguir comprando</a>
  </section>

  <?php if ($success_msg): ?><div class="alert ok"><?php echo h($success_msg); ?></div><?php endif; ?>
  <?php if ($error_msg): ?><div class="alert err"><?php echo h($error_msg); ?></div><?php endif; ?>

  <?php if (!$cart): ?>
    <section class="card empty">
      <i class="fas fa-shopping-cart"></i>
      <h2>Tu carrito está vacío</h2>
      <p class="muted" style="margin:8px 0 18px;">Agrega productos desde el catálogo para verlos aquí.</p>
      <a class="btn primary" href="productos.php"><i class="fas fa-box-open"></i> Explorar productos</a>
    </section>
  <?php else: ?>
    <section class="grid">
      <div class="card">
        <?php foreach ($cart as $item): ?>
          <?php
            $pid = (int)($item['id'] ?? 0);
            $qty = max(1, (int)($item['qty'] ?? 1));
            $price = (float)($item['precio'] ?? 0);
          ?>
          <article class="item" data-product-id="<?php echo $pid; ?>">
            <div>
              <h3><?php echo h($item['nombre'] ?? ('Producto #' . $pid)); ?></h3>
              <p class="muted">Cantidad: <?php echo $qty; ?><?php echo !empty($item['added_at']) ? ' - Agregado: ' . h(date('d/m/Y H:i', strtotime((string)$item['added_at']))) : ''; ?></p>
              <div class="msg"></div>
            </div>
            <div>
              <div class="price">S/ <?php echo number_format($price * $qty, 2); ?></div>
              <div class="actions" style="margin-top:10px;justify-content:flex-end;">
                <button class="btn primary js-buy" type="button" data-id="<?php echo $pid; ?>"><i class="fas fa-credit-card"></i> Comprar</button>
                <form method="POST">
                  <input type="hidden" name="action" value="remove">
                  <input type="hidden" name="product_id" value="<?php echo $pid; ?>">
                  <button class="btn danger" type="submit"><i class="fas fa-trash"></i></button>
                </form>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>

      <aside class="card">
        <h2 style="color:#fff;margin-bottom:12px;">Resumen</h2>
        <div class="summary-row"><span>Productos</span><strong><?php echo cartCount(); ?></strong></div>
        <div class="summary-row"><span>Saldo</span><strong>S/ <?php echo number_format((float)($usuario_actual['saldo'] ?? 0), 2); ?></strong></div>
        <div class="summary-row"><span>Total</span><strong class="summary-total">S/ <?php echo number_format($total, 2); ?></strong></div>
        <p class="muted" style="line-height:1.45;margin:14px 0;">La compra usa tu saldo disponible y entrega las credenciales al instante si hay stock.</p>
        <form method="POST" onsubmit="return confirm('¿Vaciar todo el carrito?');">
          <input type="hidden" name="action" value="clear">
          <button class="btn secondary" style="width:100%;" type="submit"><i class="fas fa-broom"></i> Vaciar carrito</button>
        </form>
      </aside>
    </section>
  <?php endif; ?>
</main>

<script>
async function buyProduct(productId, item) {
  const msg = item.querySelector('.msg');
  const btn = item.querySelector('.js-buy');
  const old = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
  msg.textContent = '';
  msg.style.color = '#ffb3b3';

  try {
    const body = new URLSearchParams();
    body.append('action', 'buy');
    body.append('product_id', String(productId));

    const response = await fetch('comprar.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body
    });
    const data = await response.json();

    if (!response.ok || !data.ok) {
      if (data.redirect) {
        window.location.href = data.redirect;
        return;
      }
      msg.textContent = data.message || 'No se pudo completar la compra.';
      btn.disabled = false;
      btn.innerHTML = old;
      return;
    }

    msg.style.color = '#34c759';
    msg.textContent = 'Compra completada. Redirigiendo...';
    window.location.href = 'user/dashboard.php';
  } catch (err) {
    msg.textContent = 'Error de red o servidor.';
    btn.disabled = false;
    btn.innerHTML = old;
  }
}

document.addEventListener('click', (event) => {
  const btn = event.target.closest('.js-buy');
  if (!btn) return;
  const item = btn.closest('.item');
  buyProduct(btn.dataset.id, item);
});
</script>
</body>
</html>
