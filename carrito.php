<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin('/login.php');

$page_title = "Carrito - Monkeystraming";
$usuario_actual = getCurrentUser();
$account_url = (($usuario_actual['rol'] ?? $usuario_actual['role'] ?? '') === 'admin')
  ? 'admin/index.php'
  : 'user/dashboard.php';

if (empty($_SESSION['_csrf_purchase'])) {
    $_SESSION['_csrf_purchase'] = bin2hex(random_bytes(32));
}
$csrf_purchase = $_SESSION['_csrf_purchase'];

if (empty($_SESSION['_csrf_cart'])) {
    $_SESSION['_csrf_cart'] = bin2hex(random_bytes(32));
}
$csrf_cart = $_SESSION['_csrf_cart'];

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function tableExistsCart(mysqli $cx, string $table): bool {
    $t = $cx->real_escape_string($table);
    $rs = $cx->query("SHOW TABLES LIKE '$t'");
    return ($rs && $rs->num_rows > 0);
}
function colExistsCart(mysqli $cx, string $table, string $col): bool {
    $t = $cx->real_escape_string($table);
    $c = $cx->real_escape_string($col);
    $rs = $cx->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
    return ($rs && $rs->num_rows > 0);
}
function availableStockForCart(mysqli $cx, int $productId, ?int $sellerId = null): int {
    if ($productId <= 0 || !tableExistsCart($cx, 'cuentas') || !tableExistsCart($cx, 'cuenta_perfiles')) return 0;
    $hasSeller = colExistsCart($cx, 'cuentas', 'vendedor_id') && $sellerId;
    $sellerFilter = $hasSeller ? " AND c.vendedor_id = ? " : "";
    $st = $cx->prepare("
        SELECT COUNT(*) c
        FROM cuenta_perfiles cp
        INNER JOIN cuentas c ON c.id = cp.cuenta_id
        WHERE c.producto_id = ?
          $sellerFilter
          AND c.estado = 'DISPONIBLE'
          AND cp.estado = 'DISPONIBLE'
    ");
    if ($hasSeller) {
        $st->bind_param("ii", $productId, $sellerId);
    } else {
        $st->bind_param("i", $productId);
    }
    $st->execute();
    $stock = (int)($st->get_result()->fetch_assoc()['c'] ?? 0);
    $st->close();
    return $stock;
}

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $productId = (int)($_POST['product_id'] ?? 0);

    if (!hash_equals($csrf_cart, (string)($_POST['_csrf'] ?? ''))) {
        $_SESSION['error_msg'] = 'Token invalido. Recarga la pagina e intenta nuevamente.';
        redirect('carrito.php');
    }

    if ($action === 'remove' && $productId > 0) {
        unset($_SESSION['cart'][$productId]);
        $_SESSION['success_msg'] = 'Producto quitado del carrito.';
        redirect('carrito.php');
    }

    if ($action === 'set_qty' && $productId > 0) {
        $qty = max(1, min(99, (int)($_POST['qty'] ?? 1)));
        if (isset($_SESSION['cart'][$productId])) {
            $sellerId = isset($_SESSION['cart'][$productId]['vendedor_id']) ? (int)$_SESSION['cart'][$productId]['vendedor_id'] : null;
            $available = availableStockForCart($conexion, $productId, $sellerId);
            if ($available <= 0) {
                $_SESSION['error_msg'] = 'Este producto ya no tiene stock disponible.';
                redirect('carrito.php');
            }
            if ($qty > $available) {
                $_SESSION['error_msg'] = 'Solo hay ' . $available . ' unidad(es) disponibles para este producto.';
                redirect('carrito.php');
            }
            $_SESSION['cart'][$productId]['qty'] = $qty;
            $_SESSION['cart'][$productId]['updated_at'] = date('Y-m-d H:i:s');
            $_SESSION['success_msg'] = 'Cantidad actualizada.';
        }
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
$cartStock = [];
foreach ($cart as $item) {
    $pid = (int)($item['id'] ?? 0);
    $sellerId = isset($item['vendedor_id']) ? (int)$item['vendedor_id'] : null;
    $cartStock[$pid] = availableStockForCart($conexion, $pid, $sellerId);
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
    .qty-form{display:inline-flex;align-items:center;gap:8px;margin-top:10px}.qty-form input{width:74px;padding:9px 10px;border-radius:10px;border:1px solid rgba(255,255,255,.13);background:rgba(0,0,0,.28);color:#fff;font-weight:800}
    .btn{border:0;border-radius:10px;padding:11px 15px;font-weight:900;text-decoration:none;display:inline-flex;gap:8px;align-items:center;justify-content:center;cursor:pointer}
    .primary{background:linear-gradient(135deg,#12aaff,#0de0c9);color:#0d0f14}.secondary{background:rgba(255,255,255,.06);color:#fff;border:1px solid rgba(255,255,255,.12)}.danger{background:rgba(255,59,48,.14);color:#ff6b6b;border:1px solid rgba(255,59,48,.25)}
    .actions{display:flex;gap:10px;flex-wrap:wrap}.summary-row{display:flex;justify-content:space-between;gap:12px;margin:12px 0;color:#ccc}.summary-total{font-size:1.35rem;color:#fff;font-weight:900}
    .alert{display:flex;align-items:flex-start;gap:12px;padding:15px 16px;border-radius:14px;margin-bottom:14px;font-weight:800}.alert i{font-size:1.2rem;margin-top:1px}.ok{background:linear-gradient(135deg,rgba(52,199,89,.18),rgba(13,224,201,.08));border:1px solid rgba(52,199,89,.35);color:#a8ffd0}.err{background:linear-gradient(135deg,rgba(255,59,48,.18),rgba(255,172,18,.08));border:1px solid rgba(255,59,48,.35);color:#ffb3b3}
    .empty{text-align:center;padding:54px 18px}.empty i{font-size:3rem;color:#12aaff;margin-bottom:14px}.msg{min-height:22px;margin-top:12px;font-weight:800}
    .cart-modal{position:fixed;inset:0;z-index:3000;display:flex;align-items:center;justify-content:center;padding:18px;background:rgba(0,0,0,.72);backdrop-filter:blur(8px)}
    .cart-modal-box{width:min(460px,100%);border:1px solid rgba(255,255,255,.12);border-radius:18px;background:linear-gradient(135deg,#151821,#0d0f14);box-shadow:0 24px 70px rgba(0,0,0,.55);padding:28px;text-align:center}
    .cart-modal-icon{width:68px;height:68px;margin:0 auto 16px;border-radius:18px;display:grid;place-items:center;background:linear-gradient(135deg,#ff4757,#ffac12);color:#fff;font-size:2rem}
    .cart-modal-box h3{color:#fff;font-size:1.35rem;margin-bottom:9px}.cart-modal-box p{color:#b8b8b8;line-height:1.55;margin-bottom:20px}
    @media(max-width:850px){.grid{grid-template-columns:1fr}.item{grid-template-columns:1fr}.price{white-space:normal}}
  </style>
  <link rel="stylesheet" href="assets/css/header-unificado.css?v=20260611a">
  <script src="assets/js/keyboard-scroll-fix.js?v=20260611a" defer></script>
  <script src="assets/js/mobile-menu.js?v=20260611a" defer></script>
  <script src="assets/js/mobile-enhance.js?v=20260611a" defer></script>
    <link rel="stylesheet" href="assets/css/mobile-urgent.css?v=20260611d">
</head>
<body class="home-scroll-nav">
<header class="header">
  <div class="logo">
    <img src="assets/img/monkylogo.png" alt="Monkeystraming Logo" class="logo-img">
  </div>
  <button type="button" class="mobile-nav-toggle" aria-label="Abrir menu" aria-expanded="false" onclick="(function(btn){var h=btn.closest('.header');var open=!(h&&h.classList.contains('mobile-menu-open'));if(h)h.classList.toggle('mobile-menu-open',open);document.body.classList.toggle('mobile-menu-open',open);btn.classList.toggle('active',open);btn.setAttribute('aria-expanded',open?'true':'false');btn.setAttribute('aria-label',open?'Cerrar menu':'Abrir menu');var i=btn.querySelector('i');if(i)i.className=open?'fas fa-times':'fas fa-bars';})(this)">
    <i class="fas fa-bars" aria-hidden="true"></i><span>Menu</span>
  </button>
  <div class="nav-container">
    <nav class="nav">
      <input type="text" class="search-bar" placeholder="Buscar productos..." id="searchInput">
      <a href="index.php"><i class="fas fa-home"></i> Inicio</a>
      <a href="productos.php"><i class="fas fa-box-open"></i> Productos</a>
      <a href="recargar.php"><i class="fas fa-coins"></i> Recargar</a>
      <a href="carrito.php"><i class="fas fa-shopping-cart"></i> Carrito<?php echo cartCount() > 0 ? ' (' . cartCount() . ')' : ''; ?></a>
      <span class="user-name-nav">
        <i class="fas fa-user-circle"></i>
        <?php echo h($usuario_actual['nombre'] ?? 'Usuario'); ?>
      </span>
      <span class="user-saldo-nav">
        <i class="fas fa-wallet"></i>
        S/ <?php echo number_format((float)($usuario_actual['saldo'] ?? 0), 2); ?>
      </span>
      <a href="<?php echo $account_url; ?>"><i class="fas fa-th-large"></i> Mi cuenta</a>
      <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Salir</a>
    </nav>
  </div>
</header>

<main class="wrap">
  <section class="top">
    <div>
      <h1>Carrito</h1>
      <p class="muted">Revisa tus productos antes de completar la compra.</p>
    </div>
    <a class="btn secondary" href="productos.php"><i class="fas fa-arrow-left"></i> Seguir comprando</a>
  </section>

  <?php if ($success_msg): ?><div class="alert ok"><i class="fas fa-check-circle"></i><span><?php echo h($success_msg); ?></span></div><?php endif; ?>
  <?php if ($error_msg): ?><div class="alert err"><i class="fas fa-triangle-exclamation"></i><span><?php echo h($error_msg); ?></span></div><?php endif; ?>

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
            $available = max(0, (int)($cartStock[$pid] ?? 0));
          ?>
          <article class="item" data-product-id="<?php echo $pid; ?>" data-qty="<?php echo $qty; ?>" data-stock="<?php echo $available; ?>">
            <div>
              <h3><?php echo h($item['nombre'] ?? ('Producto #' . $pid)); ?></h3>
              <p class="muted"><?php echo !empty($item['added_at']) ? 'Agregado: ' . h(date('d/m/Y H:i', strtotime((string)$item['added_at']))) : ''; ?></p>
              <form method="POST" class="qty-form">
                <input type="hidden" name="_csrf" value="<?php echo h($csrf_cart); ?>">
                <input type="hidden" name="action" value="set_qty">
                <input type="hidden" name="product_id" value="<?php echo $pid; ?>">
                <label class="muted" for="qty-<?php echo $pid; ?>">Cantidad</label>
                <input id="qty-<?php echo $pid; ?>" name="qty" type="number" min="1" max="<?php echo max(1, $available); ?>" value="<?php echo $qty; ?>">
                <button class="btn secondary" type="submit"><i class="fas fa-check"></i></button>
              </form>
              <p class="muted" style="margin-top:6px;">Stock disponible: <?php echo $available; ?></p>
              <?php if ($available > 0 && $qty > $available): ?>
                <p style="color:#ff6b6b;font-weight:800;margin-top:6px;">La cantidad supera el stock disponible.</p>
              <?php endif; ?>
              <div class="msg"></div>
            </div>
            <div>
              <div class="price">S/ <?php echo number_format($price * $qty, 2); ?></div>
              <div class="actions" style="margin-top:10px;justify-content:flex-end;">
                <button class="btn primary js-buy" type="button" data-id="<?php echo $pid; ?>" <?php echo ($available <= 0 || $qty > $available) ? 'disabled' : ''; ?>><i class="fas fa-credit-card"></i> Comprar</button>
                <form method="POST">
                  <input type="hidden" name="_csrf" value="<?php echo h($csrf_cart); ?>">
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
        <button class="btn primary" id="buyAllBtn" style="width:100%;margin-bottom:10px;" type="button"><i class="fas fa-credit-card"></i> Comprar todo</button>
        <div class="msg" id="cartMsg"></div>
        <form method="POST" id="clearCartForm">
          <input type="hidden" name="_csrf" value="<?php echo h($csrf_cart); ?>">
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
  const qty = Math.max(1, parseInt(item.querySelector('input[name="qty"]')?.value || item.dataset.qty || '1', 10));
  const stock = Math.max(0, parseInt(item.dataset.stock || '0', 10));
  if (qty > stock) {
    showCartNotice('Stock limitado', 'Solo hay ' + stock + ' unidad(es) disponibles para este producto. Ajusta la cantidad para poder continuar.', 'warning');
    return;
  }
  const old = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
  msg.textContent = '';
  msg.style.color = '#ffb3b3';

  try {
    for (let i = 0; i < qty; i++) {
      msg.textContent = 'Comprando unidad ' + (i + 1) + ' de ' + qty + '...';
      const body = new URLSearchParams();
      body.append('action', 'buy');
      body.append('product_id', String(productId));
      body.append('_csrf', <?php echo json_encode($csrf_purchase, JSON_UNESCAPED_UNICODE); ?>);

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
    }

    msg.style.color = '#34c759';
    msg.textContent = 'Compra completada. Redirigiendo...';
    window.location.href = <?php echo json_encode($account_url, JSON_UNESCAPED_UNICODE); ?>;
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

const buyAllBtn = document.getElementById('buyAllBtn');
if (buyAllBtn) {
  buyAllBtn.addEventListener('click', async () => {
    const proceed = await showCartConfirm('Comprar todo el carrito', 'Se procesará el total de S/ <?php echo number_format($total, 2); ?> usando tu saldo disponible.');
    if (!proceed) return;

    const cartMsg = document.getElementById('cartMsg');
    const items = Array.from(document.querySelectorAll('.item')).map((item) => ({
      item,
      id: item.dataset.productId,
      qty: Math.max(1, parseInt(item.querySelector('input[name="qty"]')?.value || '1', 10)),
      stock: Math.max(0, parseInt(item.dataset.stock || '0', 10))
    }));

    const invalid = items.find((entry) => entry.qty > entry.stock);
    if (invalid) {
      showCartNotice('Revisa las cantidades', 'Hay un producto con cantidad mayor al stock disponible. Ajusta la cantidad antes de comprar.', 'warning');
      return;
    }

    buyAllBtn.disabled = true;
    buyAllBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Comprando...';
    cartMsg.style.color = '#aaa';
    cartMsg.textContent = 'Procesando carrito...';

    for (const entry of items) {
      for (let i = 0; i < entry.qty; i++) {
        const msg = entry.item.querySelector('.msg');
        msg.style.color = '#aaa';
        msg.textContent = 'Comprando unidad ' + (i + 1) + ' de ' + entry.qty + '...';

        const body = new URLSearchParams();
        body.append('action', 'buy');
        body.append('product_id', String(entry.id));
        body.append('_csrf', <?php echo json_encode($csrf_purchase, JSON_UNESCAPED_UNICODE); ?>);

        const response = await fetch('comprar.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
          body
        });
        const data = await response.json();
        if (!response.ok || !data.ok) {
          cartMsg.style.color = '#ff6b6b';
          cartMsg.textContent = data.message || 'No se pudo completar todo el carrito.';
          buyAllBtn.disabled = false;
          buyAllBtn.innerHTML = '<i class="fas fa-credit-card"></i> Comprar todo';
          return;
        }
      }
    }

    cartMsg.style.color = '#34c759';
    cartMsg.textContent = 'Compra total completada. Redirigiendo...';
    window.location.href = <?php echo json_encode($account_url, JSON_UNESCAPED_UNICODE); ?>;
  });
}

const clearCartForm = document.getElementById('clearCartForm');
if (clearCartForm) {
  clearCartForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    const proceed = await showCartConfirm('Vaciar carrito', 'Se quitarán todos los productos guardados en tu carrito.');
    if (proceed) clearCartForm.submit();
  });
}

function showCartNotice(title, message, type = 'warning') {
  const modal = document.createElement('div');
  modal.className = 'cart-modal';
  const icon = type === 'ok' ? 'fa-check-circle' : 'fa-triangle-exclamation';
  modal.innerHTML = `
    <div class="cart-modal-box">
      <div class="cart-modal-icon"><i class="fas ${icon}"></i></div>
      <h3>${escapeHtml(title)}</h3>
      <p>${escapeHtml(message)}</p>
      <button class="btn primary" type="button"><i class="fas fa-check"></i> Entendido</button>
    </div>
  `;
  document.body.appendChild(modal);
  modal.querySelector('button').addEventListener('click', () => modal.remove());
  modal.addEventListener('click', (event) => {
    if (event.target === modal) modal.remove();
  });
}

function showCartConfirm(title, message) {
  return new Promise((resolve) => {
    const modal = document.createElement('div');
    modal.className = 'cart-modal';
    modal.innerHTML = `
      <div class="cart-modal-box">
        <div class="cart-modal-icon"><i class="fas fa-shopping-cart"></i></div>
        <h3>${escapeHtml(title)}</h3>
        <p>${escapeHtml(message)}</p>
        <div class="actions" style="justify-content:center;">
          <button class="btn secondary" type="button" data-action="cancel">Cancelar</button>
          <button class="btn primary" type="button" data-action="ok"><i class="fas fa-credit-card"></i> Aceptar</button>
        </div>
      </div>
    `;
    document.body.appendChild(modal);
    modal.addEventListener('click', (event) => {
      if (event.target === modal || event.target.closest('[data-action="cancel"]')) {
        modal.remove();
        resolve(false);
      }
      if (event.target.closest('[data-action="ok"]')) {
        modal.remove();
        resolve(true);
      }
    });
  });
}

function escapeHtml(value) {
  return String(value).replace(/[&<>"']/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
  }[char]));
}
</script>
</body>
</html>
