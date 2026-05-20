<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$page_title = "Monkeystraming - Productos";

/** ===== Helpers ===== */
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

/** Usuario actual */
$usuario_actual = null;
if (isLoggedIn()) {
    $usuario_actual = getCurrentUser();
}

/** Saldo real (por si getCurrentUser no trae saldo actualizado) */
$saldo_actual = 0.0;
if ($usuario_actual && !empty($usuario_actual['id'])) {
    $uid = (int)$usuario_actual['id'];
    $stmtSaldo = $conexion->prepare("SELECT saldo FROM usuarios WHERE id=? LIMIT 1");
    $stmtSaldo->bind_param("i", $uid);
    $stmtSaldo->execute();
    $rowSaldo = $stmtSaldo->get_result()->fetch_assoc();
    $stmtSaldo->close();
    if ($rowSaldo) $saldo_actual = (float)$rowSaldo['saldo'];
}

/** Detectar estructuras */
$has_stock_col = false;
if (tableExists($conexion, 'productos') && colExists($conexion, 'productos', 'stock')) $has_stock_col = true;

$has_tipo_venta = false;
if (tableExists($conexion, 'productos') && colExists($conexion, 'productos', 'tipo_venta')) $has_tipo_venta = true;

$has_vendedor_id = false;
if (tableExists($conexion, 'productos') && colExists($conexion, 'productos', 'vendedor_id')) $has_vendedor_id = true;

$has_estado_revision = false;
if (tableExists($conexion, 'productos') && colExists($conexion, 'productos', 'estado_revision')) $has_estado_revision = true;

$has_cuentas = tableExists($conexion, 'cuentas');
$has_perfiles = tableExists($conexion, 'cuenta_perfiles');
$cuentas_has_estado = $has_cuentas && colExists($conexion, 'cuentas', 'estado');
$cuentas_has_modo_venta = $has_cuentas && colExists($conexion, 'cuentas', 'modo_venta');
$perfiles_has_estado = $has_perfiles && colExists($conexion, 'cuenta_perfiles', 'estado');

/** Categorías */
$categorias = [];
$q = $conexion->query("SELECT id, nombre, color FROM categorias ORDER BY id ASC");
if ($q) {
    while ($c = $q->fetch_assoc()) {
        $categorias[(int)$c['id']] = [
            'nombre' => $c['nombre'],
            'color'  => $c['color'] ?? '#12aaff'
        ];
    }
}

/** Categoría activa */
$categoria_activa = isset($_GET['cat']) ? (int)$_GET['cat'] : 0;
if ($categoria_activa <= 0 || !isset($categorias[$categoria_activa])) {
    $categoria_activa = !empty($categorias) ? (int)array_key_first($categorias) : 0;
}

$titulo_categoria = $categoria_activa && isset($categorias[$categoria_activa])
    ? $categorias[$categoria_activa]['nombre']
    : 'Productos';

/** Contadores por categoría (NO filtramos por stock, solo activo=1) */
$contador_categorias = [];
$sqlCont = "
    SELECT categoria_id, COUNT(*) AS total
    FROM productos
    WHERE activo = 1
    GROUP BY categoria_id
";
$qc = $conexion->query($sqlCont);
if ($qc) {
    while ($row = $qc->fetch_assoc()) {
        $contador_categorias[(int)$row['categoria_id']] = (int)$row['total'];
    }
}

/** Producto seleccionado para resaltar */
$producto_seleccionado = null;
if (isset($_GET['producto_id']) && is_numeric($_GET['producto_id'])) {
    $producto_id_buscar = (int)$_GET['producto_id'];
    
    // Buscar el producto en la base de datos
    $sqlProdSel = "
        SELECT p.id, p.nombre, p.descripcion, p.precio, p.imagen_url, 
               c.nombre AS categoria, p.tipo_venta, p.stock
        FROM productos p
        JOIN categorias c ON c.id = p.categoria_id
        WHERE p.id = ? AND p.activo = 1
        LIMIT 1
    ";
    
    $stmtProdSel = $conexion->prepare($sqlProdSel);
    if ($stmtProdSel) {
        $stmtProdSel->bind_param("i", $producto_id_buscar);
        $stmtProdSel->execute();
        $resProdSel = $stmtProdSel->get_result();
        if ($resProdSel && $resProdSel->num_rows > 0) {
            $producto_seleccionado = $resProdSel->fetch_assoc();
        }
        $stmtProdSel->close();
    }
}

/** Productos */
$productos = [];
if ($categoria_activa) {

    // Si existen tablas de stock real, agregamos joins para calcular stock por producto.
    $use_stock_real = $has_cuentas && $has_perfiles && $cuentas_has_estado && $perfiles_has_estado;

    if ($use_stock_real) {

        // Subquery: cuentas disponibles (para productos tipo CUENTA / CUENTA_COMPLETA)
        $subCuentas = "
            SELECT cu.producto_id" . ($has_vendedor_id && colExists($conexion, 'cuentas', 'vendedor_id') ? ", cu.vendedor_id" : "") . ", COUNT(*) AS cuentas_disp
            FROM cuentas cu
            WHERE 1=1
        ";
        if ($cuentas_has_estado) $subCuentas .= " AND cu.estado = 'DISPONIBLE' ";
        if ($cuentas_has_modo_venta) $subCuentas .= " AND cu.modo_venta = 'CUENTA' ";
        $subCuentas .= " GROUP BY cu.producto_id" . ($has_vendedor_id && colExists($conexion, 'cuentas', 'vendedor_id') ? ", cu.vendedor_id" : "") . " ";

        // Subquery: perfiles disponibles (para productos tipo PERFIL)
        $subPerfiles = "
            SELECT cu.producto_id" . ($has_vendedor_id && colExists($conexion, 'cuentas', 'vendedor_id') ? ", cu.vendedor_id" : "") . ", COUNT(*) AS perfiles_disp
            FROM cuenta_perfiles cp
            INNER JOIN cuentas cu ON cu.id = cp.cuenta_id
            WHERE 1=1
              AND cp.estado = 'DISPONIBLE'
        ";
        if ($cuentas_has_estado) $subPerfiles .= " AND cu.estado = 'DISPONIBLE' ";
        $subPerfiles .= " GROUP BY cu.producto_id" . ($has_vendedor_id && colExists($conexion, 'cuentas', 'vendedor_id') ? ", cu.vendedor_id" : "") . " ";

        $hasSellerProfileTable = tableExists($conexion, 'vendedor_perfiles');
        $sellerSelect = $has_vendedor_id ? ", p.vendedor_id, u.nombre AS vendedor_nombre" . ($hasSellerProfileTable ? ", vp.tienda_nombre" : ", NULL AS tienda_nombre") : "";
        $sellerJoin = $has_vendedor_id ? "
            LEFT JOIN usuarios u ON u.id = p.vendedor_id
            " . ($hasSellerProfileTable ? "LEFT JOIN vendedor_perfiles vp ON vp.vendedor_id = p.vendedor_id" : "") . "
        " : "";
        $sellerStockJoinCt = $has_vendedor_id && colExists($conexion, 'cuentas', 'vendedor_id') ? " AND ct.vendedor_id = p.vendedor_id" : "";
        $sellerStockJoinPf = $has_vendedor_id && colExists($conexion, 'cuentas', 'vendedor_id') ? " AND pf.vendedor_id = p.vendedor_id" : "";
        $reviewWhere = $has_estado_revision ? " AND p.estado_revision = 'aprobado' " : "";

        $sqlProd = "
            SELECT
                p.id, p.nombre, p.descripcion, p.precio, p.imagen_url,
                c.nombre AS categoria
                " . ($has_tipo_venta ? ", p.tipo_venta" : "") . "
                " . ($has_stock_col ? ", p.stock" : "") . "
                $sellerSelect
                , COALESCE(ct.cuentas_disp, 0) AS stock_cuentas
                , COALESCE(pf.perfiles_disp, 0) AS stock_perfiles
            FROM productos p
            JOIN categorias c ON c.id = p.categoria_id
            $sellerJoin
            LEFT JOIN ($subCuentas) ct ON ct.producto_id = p.id $sellerStockJoinCt
            LEFT JOIN ($subPerfiles) pf ON pf.producto_id = p.id $sellerStockJoinPf
            WHERE p.activo = 1 AND p.categoria_id = ?
            $reviewWhere
            ORDER BY p.id DESC
        ";

        $stmt = $conexion->prepare($sqlProd);
        $stmt->bind_param("i", $categoria_activa);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) $productos = $res->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

    } else {
        // Sin stock real: solo listamos productos activos (NO filtramos por stock)
        $hasSellerProfileTable = tableExists($conexion, 'vendedor_perfiles');
        $sellerSelect = $has_vendedor_id ? ", p.vendedor_id, u.nombre AS vendedor_nombre" . ($hasSellerProfileTable ? ", vp.tienda_nombre" : ", NULL AS tienda_nombre") : "";
        $sellerJoin = $has_vendedor_id ? "
            LEFT JOIN usuarios u ON u.id = p.vendedor_id
            " . ($hasSellerProfileTable ? "LEFT JOIN vendedor_perfiles vp ON vp.vendedor_id = p.vendedor_id" : "") . "
        " : "";
        $reviewWhere = $has_estado_revision ? " AND p.estado_revision = 'aprobado' " : "";

        $sqlProd = "
            SELECT p.id, p.nombre, p.descripcion, p.precio, p.imagen_url,
                   c.nombre AS categoria
                   " . ($has_tipo_venta ? ", p.tipo_venta" : "") . "
                   " . ($has_stock_col ? ", p.stock" : "") . "
                   $sellerSelect
            FROM productos p
            JOIN categorias c ON c.id = p.categoria_id
            $sellerJoin
            WHERE p.activo = 1 AND p.categoria_id = ?
            $reviewWhere
            ORDER BY p.id DESC
        ";
        $stmt = $conexion->prepare($sqlProd);
        $stmt->bind_param("i", $categoria_activa);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) $productos = $res->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

/** URL actual (para redirect login) */
$currentUrl = basename($_SERVER['PHP_SELF']) . (!empty($_SERVER['QUERY_STRING']) ? ('?' . $_SERVER['QUERY_STRING']) : '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        *{margin:0;padding:0;box-sizing:border-box;font-family:'Inter',sans-serif;}
        body{
            background:linear-gradient(135deg,#0d0f14 0%,#11131a 35%,#0b0c11 100%);
            color:#e5e5e5;min-height:100vh;
        }
        .header {
            position: sticky;
            top: 0;
            z-index: 1000;
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 80px; /* Reducido para mejor ajuste */
            background: linear-gradient(90deg, rgba(20,22,29,0.95), rgba(16,18,24,0.98));
            backdrop-filter: blur(15px);
            border-bottom: 1px solid rgba(255,255,255,0.07);
            box-shadow: 0 4px 25px rgba(0, 0, 0, 0.3);
            min-height: 130px; /* Altura mínima fija */
            max-height: 70px; /* Altura máxima fija */
            overflow: hidden; /* Previene desbordamientos */
        }

        .logo {
            display: flex;
            align-items: center;
            height: 100%; /* Usa toda la altura del header */
        }

        .logo-img {
            height: 230px; /* Altura fija para el logo */
            width: auto; /* Ancho automático mantiene proporción */
            max-width: 300px; /* Ancho máximo para evitar que sea muy ancho */
            object-fit: contain; /* Mantiene proporción sin deformar */
            transition: transform 0.3s ease;
        }
        .nav{display:flex;align-items:center;gap:25px;flex-wrap:wrap;}
        .nav a{
            text-decoration:none;color:#d0d0d0;font-weight:500;
            transition:.3s;font-size:.95rem;
        }
        .nav a:hover{color:#12aaff;transform:translateY(-1px);}
        .btn-registro{
            padding:10px 18px;background:linear-gradient(135deg,#12aaff,#0de0c9);
            border-radius:8px;color:#0d0f14!important;font-weight:600;
        }
        .btn-registro:hover{
            background:#0d92d6;color:#fff!important;transform:translateY(-2px);
            box-shadow:0 5px 15px rgba(18,170,255,0.3);
        }
        .search-bar{
            padding:10px 18px;border-radius:10px;
            border:1px solid rgba(255,255,255,0.1);
            background:rgba(255,255,255,0.05);
            color:#fff;outline:none;width:260px;font-size:.9rem;
        }
        .search-bar:focus{
            border-color:#12aaff;
            box-shadow:0 0 0 3px rgba(18,170,255,0.2);
        }
        .user-name-nav, .user-saldo-nav{
            display:inline-flex;align-items:center;gap:6px;font-size:.9rem;
        }
        .user-name-nav{color:#d0d0d0;}
        .user-saldo-nav{
            padding:6px 12px;border-radius:999px;
            background:rgba(18,170,255,0.12);
            color: #f9a42c;font-weight:600;
        }

        .container{
            display:flex;max-width:1400px;margin:100px auto 50px;
            padding:0 20px;gap:30px;
        }
        .sidebar{
            width:260px;padding:20px;background:rgba(255,255,255,0.03);
            border-radius:16px;border:1px solid rgba(255,255,255,0.06);
            backdrop-filter:blur(10px);height:fit-content;position:sticky;top:100px;
        }
        .sidebar h3{font-size:1.2rem;margin-bottom:18px;color: #ffffff;}
        .sidebar ul{list-style:none;}
        .sidebar ul li{
            padding:10px 12px;border-radius:10px;margin-bottom:6px;
            cursor:pointer;transition:.3s;color: #ffffff;font-size:.92rem;
            display:flex;align-items:center;gap:10px;
        }
        .sidebar ul li:hover{background:rgba(255,255,255,0.05);color: #fff;}
        .sidebar ul li.active{
            background:linear-gradient(135deg,rgba(18,170,255,0.18),rgba(13,224,201,0.1));
            color:#12aaff;border-left:3px solid #ffa812;
        }
        .productos{flex:1;padding:10px 0;}
        .categoria-header{
            display:flex;justify-content:space-between;align-items:center;margin-bottom:25px;
        }
        .categoria-titulo{
            font-size:2rem;font-weight:700;
            background:linear-gradient(135deg, #fff 0%, #7f8081);
            -webkit-background-clip:text;-webkit-text-fill-color:transparent;
        }
        .contador-productos{
            background:rgba(18,170,255,0.1);padding:6px 14px;
            border-radius:20px;font-size:.85rem;color:#12aaff;font-weight:600;
        }
        .grid-productos{
            display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:20px;
        }
        .producto-card{
            background:rgba(255,255,255,0.04);padding:18px;border-radius:16px;
            border:1px solid rgba(255,255,255,0.06);backdrop-filter:blur(8px);
            transition:.3s;position:relative;overflow:hidden;
        }
        .producto-card:hover{
            transform:translateY(-5px);
            border-color:#12aaff44;box-shadow:0 12px 25px rgba(0,0,0,0.35);
        }
        .thumb{
            width:100%;height:150px;border-radius:12px;margin-bottom:16px;
            background:#181b23;background-size:contain;background-position:center;
            background-repeat:no-repeat;position:relative;
        }
        .badge-categoria{
            position:absolute;top:10px;right:10px;
            background:rgba(18,170,255,0.2);color: #fea917;
            font-size:.7rem;padding:4px 10px;border-radius:20px;font-weight:600;
            text-transform:uppercase;
        }
        .seller-name{
            display:inline-flex;align-items:center;gap:6px;margin-bottom:8px;
            color:#0de0c9;font-size:.82rem;font-weight:800;
        }
        .producto-card h3{font-size:1.1rem;margin-bottom:8px;color:#fff;}
        .producto-card p{font-size:.9rem;color:#b4b4b4;margin-bottom:14px;}
        .producto-footer{
            display:flex;align-items:center;justify-content:space-between;margin-top:10px;
        }
        .precio{font-size:1.2rem;font-weight:700;color: #ffffff;}
        .producto-card button{
            padding:10px 18px;background:linear-gradient(135deg, #ff8d0b, #fdaf28);
            border:none;border-radius:10px;color: #0d0f14;font-size:.9rem;
            font-weight:700;cursor: pointer;transition:.3s;
        }
        .producto-card button:hover{
            background:#0d92d6; color: #fff;
            box-shadow:0 5px 15px rgba(18,170,255,0.4);
        }
        .producto-card button[disabled]{
            opacity:.55; cursor:not-allowed;
            filter: grayscale(30%);
        }
        .no-productos{
            grid-column:1/-1;text-align:center;padding:50px 20px;color: #999;
        }
        .footer{
            text-align:center;padding:25px;background:rgba(11,13,18,0.9);
            color:#7a7a7a;font-size:.85rem;border-top:1px solid rgba(255,255,255,0.05);
            margin-top:60px;
        }
        @media(max-width:1024px){
            .container{flex-direction:column;}
            .sidebar{width:100%;position:static;}
        }
        @media(max-width:768px){
            .header{flex-direction:column;gap:10px;padding:15px 20px;}
            .categoria-header{flex-direction:column;align-items:flex-start;gap:10px;}
            .categoria-titulo{font-size:1.7rem;}
            .grid-productos{grid-template-columns:repeat(auto-fill,minmax(220px,1fr));}
            .search-bar{width:100%;}
        }
        @media(max-width:480px){
            .grid-productos{grid-template-columns:1fr;}
            .producto-footer{flex-direction:column;gap:10px;align-items:stretch;}
            .producto-card button{width:100%;}
        }
    </style>
</head>
<body>

<header class="header">
   <div class="logo">
        <!-- REEMPLAZA LA URL CON LA RUTA DE TU IMAGEN DE LOGO -->
        <img src="assets/img/monkylogo.png" alt="Monkeystraming Logo" class="logo-img">
    </div>
    <nav class="nav">
        <input type="text" class="search-bar" placeholder="🔍 Buscar productos..." id="searchInput">
        <a href="index.php"><i class="fas fa-home"></i> Inicio</a>
        <a href="productos.php" style="color:#12aaff;"><i class="fas fa-box-open"></i> Productos</a>
        <a href="recargar.php"><i class="fas fa-coins"></i> Recargar</a>

        <?php if ($usuario_actual): ?>
            <span class="user-name-nav">
                <i class="fas fa-user-circle"></i>
                <?php echo htmlspecialchars($usuario_actual['nombre'] ?? 'Usuario'); ?>
            </span>
            <span class="user-saldo-nav">
                <i class="fas fa-wallet"></i>
                S/ <?php echo number_format($saldo_actual, 2); ?>
            </span>
            <a href="user/dashboard.php"><i class="fas fa-th-large"></i> Mi cuenta</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Salir</a>
        <?php else: ?>
            <a href="login.php?redirect=<?php echo urlencode($currentUrl); ?>"><i class="fas fa-sign-in-alt"></i> Login</a>
            <a href="register.php" class="btn-registro"><i class="fas fa-user-plus"></i> Registrarse</a>
        <?php endif; ?>
    </nav>
</header>

<div class="container">
    <aside class="sidebar">
        <h3><i class="fas fa-filter"></i> Categorías</h3>
        <ul>
            <?php if (!empty($categorias)): ?>
                <?php foreach ($categorias as $id => $cat): ?>
                    <li class="cat <?php echo ($id === $categoria_activa) ? 'active' : ''; ?>"
                        data-cat="<?php echo (int)$id; ?>">
                        <?php echo htmlspecialchars($cat['nombre']); ?>
                        <span style="margin-left:auto;font-size:.8rem;color:#777;">
                            (<?php echo $contador_categorias[$id] ?? 0; ?>)
                        </span>
                    </li>
                <?php endforeach; ?>
            <?php else: ?>
                <li style="color:#999;">No hay categorías registradas.</li>
            <?php endif; ?>
        </ul>
    </aside>

    <main class="productos">
        <div class="categoria-header">
            <h1 class="categoria-titulo"><?php echo htmlspecialchars($titulo_categoria); ?></h1>
            <div class="contador-productos">
                <i class="fas fa-box"></i>
                <?php echo $categoria_activa ? ($contador_categorias[$categoria_activa] ?? 0) : 0; ?> productos
            </div>
        </div>

        <div class="grid-productos" id="grid">
            <?php if (!empty($productos)): ?>
                <?php foreach ($productos as $producto): ?>
                    <?php
                        $img = !empty($producto['imagen_url'])
                            ? $producto['imagen_url']
                            : 'assets/img/productos/default.png';

                        $pid   = (int)$producto['id'];
                        $pnom  = (string)$producto['nombre'];
                        $pprec = (float)$producto['precio'];
                        $pdesc = (string)($producto['descripcion'] ?? '');
                        $sellerName = (string)($producto['tienda_nombre'] ?? $producto['vendedor_nombre'] ?? '');

                        $tipoVenta = strtoupper((string)($producto['tipo_venta'] ?? 'PERFIL'));

                        // Stock visible: preferimos stock real si viene de joins, si no, productos.stock, si no, "sin control".
                        $stockVisible = null;

                        if (array_key_exists('stock_cuentas', $producto) || array_key_exists('stock_perfiles', $producto)) {
                            $stockC = (int)($producto['stock_cuentas'] ?? 0);
                            $stockP = (int)($producto['stock_perfiles'] ?? 0);

                            $esCuenta = in_array($tipoVenta, ['CUENTA','CUENTA_COMPLETA','COMPLETA','FULL','CUENTA COMPLETA'], true);
                            $stockVisible = $esCuenta ? $stockC : $stockP;
                        } elseif ($has_stock_col) {
                            $stockVisible = (int)($producto['stock'] ?? 0);
                        }

                        $agotado = ($stockVisible !== null) ? ($stockVisible <= 0) : false;

                        $badge = strtoupper((string)($producto['categoria'] ?? ''));
                        if ($has_tipo_venta) $badge .= " • " . ($tipoVenta ?: 'PERFIL');
                        if ($stockVisible !== null) $badge .= " • STOCK: " . (int)$stockVisible;
                    ?>
                    <div class="producto-card" data-product-id="<?php echo $pid; ?>">
                        <div class="thumb" style="background-image:url('<?php echo htmlspecialchars($img); ?>');">
                            <span class="badge-categoria">
                                <?php echo htmlspecialchars($badge); ?>
                            </span>
                        </div>
                        <?php if ($sellerName !== ''): ?>
                            <div class="seller-name"><i class="fas fa-store"></i> <?php echo htmlspecialchars($sellerName); ?></div>
                        <?php endif; ?>
                        <h3><?php echo htmlspecialchars($pnom); ?></h3>
                        <p><?php echo htmlspecialchars($pdesc); ?></p>
                        <div class="producto-footer">
                            <div class="precio">S/ <?php echo number_format($pprec, 2); ?>/mes</div>

                            <button
                              type="button"
                              class="btn-buy"
                              <?php echo $agotado ? 'disabled' : ''; ?>
                              data-id="<?php echo $pid; ?>"
                              data-nombre="<?php echo htmlspecialchars($pnom, ENT_QUOTES); ?>"
                              data-precio="<?php echo htmlspecialchars((string)$pprec, ENT_QUOTES); ?>"
                            >
                                <i class="fas fa-shopping-cart"></i>
                                <?php echo $agotado ? 'Agotado' : 'Comprar'; ?>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-productos">
                    <i class="fas fa-box-open" style="font-size:3rem;margin-bottom:15px;color:#12aaff;"></i>
                    <p>No hay productos disponibles en esta categoría.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<footer class="footer">
    © 2024 Monkeystraming. Todos los derechos reservados.
</footer>

<script>
// =================== UI ===================
(function(){
  // cambiar categoría
  document.querySelectorAll('.cat').forEach(item=>{
      item.addEventListener('click',function(){
          const c = this.getAttribute('data-cat');
          window.location.href = 'productos.php?cat='+encodeURIComponent(c);
      });
  });

  // buscador
  const searchInput = document.getElementById('searchInput');
  if (searchInput) {
      searchInput.addEventListener('input',function(){
          const term = this.value.toLowerCase();
          document.querySelectorAll('.producto-card').forEach(card=>{
              const text = card.innerText.toLowerCase();
              card.style.display = text.includes(term) ? '' : 'none';
          });
      });
  }
})();

// =================== COMPRA MODAL (con visto ✅) ===================
const userIsLogged  = <?php echo isLoggedIn() ? 'true' : 'false'; ?>;
const userSaldo     = <?php echo $usuario_actual ? (float)$saldo_actual : 0; ?>;
const redirectLogin = <?php echo json_encode('login.php?redirect='.$currentUrl, JSON_UNESCAPED_UNICODE); ?>;

function cerrarModal(modal) {
    if (modal && modal.parentNode) modal.parentNode.removeChild(modal);
}

function postForm(url, dataObj){
    const body = new URLSearchParams();
    for (const k in dataObj) body.append(k, dataObj[k]);

    return fetch(url, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
        body
    });
}

function toast(msg){
    const t = document.createElement('div');
    t.textContent = msg;
    t.style.cssText = `
        position:fixed; bottom:20px; left:50%; transform:translateX(-50%);
        background:rgba(0,0,0,.85); color:#fff; padding:10px 14px; border-radius:10px;
        z-index:999999; font-weight:600; font-size:14px; border:1px solid rgba(255,255,255,.1);
    `;
    document.body.appendChild(t);
    setTimeout(()=>{ if(t.parentNode) t.parentNode.removeChild(t); }, 1800);
}

async function safeJson(response){
  const text = await response.text();
  try { return JSON.parse(text); } catch(e) { return { ok:false, message:'Respuesta no es JSON', raw:text }; }
}

function copiarTexto(txt){
  if (!txt) return;
  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(txt).then(()=>toast('Copiado ✅')).catch(()=>toast('No se pudo copiar'));
  } else {
    const ta = document.createElement('textarea');
    ta.value = txt;
    ta.style.position = 'fixed';
    ta.style.left = '-9999px';
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); toast('Copiado ✅'); } catch(e){ toast('No se pudo copiar'); }
    document.body.removeChild(ta);
  }
}

function escapeHtml(s){
  return String(s ?? '').replace(/[&<>"']/g, (m)=>({
    '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
  }[m]));
}

// ✅ Vista de compra exitosa + visto animado + datos + copiar
function renderCompraExitosa(modal, data){
  // Soporta varias formas que pueda mandar tu backend:
  const compra = data.purchase || data.compra || data.data || {};
  const cred   = compra.credenciales || data.credenciales || compra.creds || {};

  const producto = compra.producto_nombre || compra.producto || '';
  const fecha    = compra.fecha_compra || compra.fecha || '';
  const vence    = compra.vence_at || compra.vence || '';

  const userOrEmail = cred.login_user || '';
  const pass        = cred.login_pass || '';
  const perfil      = cred.perfil_nombre || '';
  const pin         = cred.pin || '';
  const maxPerf     = (cred.max_perfiles != null) ? Number(cred.max_perfiles) : '';

  const textoCopia =
`Producto: ${producto}
Fecha compra: ${fecha}
Vence: ${vence}
Usuario/Correo: ${userOrEmail}
Contraseña: ${pass}${perfil ? "\nPerfil: "+perfil : ""}${pin ? "\nPIN: "+pin : ""}${maxPerf ? "\nMax perfiles: "+maxPerf : ""}`.trim();

  modal.innerHTML = `
    <div style="background: linear-gradient(135deg, #12151d, #0d0f14);
                padding: 40px;
                border-radius: 20px;
                max-width: 560px;
                width: 100%;
                border: 1px solid rgba(18,170,255,0.2);
                text-align: center;
                box-shadow: 0 20px 50px rgba(0,0,0,0.5);">
      <style>
        @keyframes pop { 0%{transform:scale(.6);opacity:.2} 60%{transform:scale(1.1);opacity:1} 100%{transform:scale(1)} }
        @keyframes draw { to { stroke-dashoffset: 0; } }
      </style>

      <div style="display:flex; justify-content:center; margin-bottom:18px; animation: pop .55s ease;">
        <svg width="88" height="88" viewBox="0 0 120 120">
          <circle cx="60" cy="60" r="52" fill="rgba(13,224,201,0.12)" stroke="rgba(13,224,201,0.55)" stroke-width="6"/>
          <path d="M38 62 L54 78 L84 44" fill="none" stroke="#0de0c9" stroke-width="10"
                stroke-linecap="round" stroke-linejoin="round"
                stroke-dasharray="90" stroke-dashoffset="90" style="animation: draw .55s ease forwards .1s"/>
        </svg>
      </div>

      <h2 style="color:#0de0c9; margin-bottom:8px;">Compra exitosa</h2>
      <p style="color:#bdbdbd; margin-bottom:18px;">Aquí tienes tus credenciales:</p>

      <div style="text-align:left; background: rgba(255,255,255,0.05); padding: 18px; border-radius: 12px; border:1px solid rgba(255,255,255,0.06);">
        <div style="display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap;">
          <div style="color:#aaa; font-size:.9rem;">Producto</div>
          <div style="color:#fff; font-weight:700;">${escapeHtml(producto||'—')}</div>
        </div>
        <div style="height:10px"></div>

        <div style="display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap;">
          <div style="color:#aaa; font-size:.9rem;">Fecha de compra</div>
          <div style="color:#fff; font-weight:700;">${escapeHtml(fecha||'—')}</div>
        </div>
        <div style="height:10px"></div>

        <div style="display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap;">
          <div style="color:#aaa; font-size:.9rem;">Vence</div>
          <div style="color:#fff; font-weight:700;">${escapeHtml(vence||'—')}</div>
        </div>

        <hr style="border:none; border-top:1px solid rgba(255,255,255,0.08); margin:14px 0;">

        <div style="color:#aaa; font-size:.9rem; margin-bottom:6px;">Usuario/Correo</div>
        <div style="color:#fff; font-weight:800; word-break:break-all;">${escapeHtml(userOrEmail||'—')}</div>

        <div style="height:10px"></div>

        <div style="color:#aaa; font-size:.9rem; margin-bottom:6px;">Contraseña</div>
        <div style="color:#fff; font-weight:800; word-break:break-all;">${escapeHtml(pass||'—')}</div>

        ${perfil ? `<div style="height:10px"></div><div style="color:#aaa;font-size:.9rem;margin-bottom:6px;">Perfil</div><div style="color:#fff;font-weight:800;">${escapeHtml(perfil)}</div>` : ``}
        ${pin ? `<div style="height:10px"></div><div style="color:#aaa;font-size:.9rem;margin-bottom:6px;">PIN</div><div style="color:#fff;font-weight:800;">${escapeHtml(pin)}</div>` : ``}
        ${maxPerf ? `<div style="height:10px"></div><div style="color:#aaa;font-size:.9rem;margin-bottom:6px;">Máx. perfiles</div><div style="color:#fff;font-weight:800;">${escapeHtml(maxPerf)}</div>` : ``}
      </div>

      <div style="display:flex; gap:12px; justify-content:center; margin-top:18px; flex-wrap:wrap;">
        <button type="button" class="btn-copy"
          style="padding: 12px 18px; background: #111827; color: #e5e5e5; border: 1px solid rgba(255,255,255,0.1);
                 border-radius: 10px; cursor: pointer; font-weight: 700; display:flex; align-items:center; gap:8px;">
          <i class="fas fa-copy"></i> Copiar datos
        </button>

        <button type="button" class="btn-go"
          style="padding: 12px 18px; background: linear-gradient(135deg, #0de0c9, #12aaff);
                 color: #0d0f14; border: none; border-radius: 10px; cursor:pointer; font-weight:800; display:flex; align-items:center; gap:8px;">
          <i class="fas fa-user"></i> Mi cuenta
        </button>

        <button type="button" class="btn-close"
          style="padding: 12px 18px; background: #333; color: #fff; border: none; border-radius: 10px; cursor: pointer; font-weight: 700;">
          Cerrar
        </button>
      </div>

      <p style="color:#888; font-size:.85rem; margin-top:16px;">
        <i class="fas fa-shield-alt"></i> Guarda tus credenciales. Soporte 24/7.
      </p>
    </div>
  `;

  modal.querySelector('.btn-copy').addEventListener('click', ()=>copiarTexto(textoCopia));
  modal.querySelector('.btn-go').addEventListener('click', ()=>window.location.href='user/dashboard.php');
  modal.querySelector('.btn-close').addEventListener('click', ()=>cerrarModal(modal));
  modal.addEventListener('click', e => { if (e.target === modal) cerrarModal(modal); });
}

// ====== MODAL PRINCIPAL ======
function abrirModalCompra(id, nombre, precio){
    const modal = document.createElement('div');
    modal.style.cssText = `
        position: fixed; inset: 0;
        background: rgba(0,0,0,0.85);
        display: flex; justify-content: center; align-items: center;
        z-index: 999999;
        backdrop-filter: blur(5px);
        padding: 18px;
    `;

    modal.innerHTML = `
        <div style="background: linear-gradient(135deg, #12151d, #0d0f14);
                    padding: 40px;
                    border-radius: 20px;
                    max-width: 520px;
                    width: 100%;
                    border: 1px solid rgba(18,170,255,0.2);
                    text-align: center;
                    box-shadow: 0 20px 50px rgba(0,0,0,0.5);">
            <div style="font-size: 3rem; color: #12aaff; margin-bottom: 20px;">
                <i class="fas fa-shopping-cart"></i>
            </div>
            <h2 class="m-title" style="color: #12aaff; margin-bottom: 20px;">¿Deseas comprar este producto?</h2>

            <div style="background: rgba(255,255,255,0.05); padding: 20px; border-radius: 12px; margin-bottom: 18px;">
                <p style="font-size: 1.2rem; margin-bottom: 10px;"><strong>${escapeHtml(nombre)}</strong></p>
                <p style="color: #0de0c9; font-size: 1.8rem; font-weight: 800;">S/ ${Number(precio).toFixed(2)}</p>
                <p class="m-sub" style="color: #aaa; font-size: 0.9rem; margin-top: 10px;">Pago mensual • Renovación automática</p>
            </div>

            <div class="m-msg" style="min-height: 22px; color:#ffb3b3; font-weight:600; margin-bottom: 10px;"></div>

            <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                <button type="button" class="btn-cancelar"
                    style="padding: 14px 30px; background: #333; color: #fff; border: none; border-radius: 10px; cursor: pointer; font-weight: 600;">
                    Cancelar
                </button>

                <button type="button" class="btn-sec"
                    style="padding: 14px 30px; background: #111827; color: #e5e5e5; border: 1px solid rgba(255,255,255,0.1);
                           border-radius: 10px; cursor: pointer; font-weight: 600; display:flex; align-items:center; gap:8px;">
                </button>

                <button type="button" class="btn-pri"
                    style="padding: 14px 30px; background: linear-gradient(135deg, #0de0c9, #12aaff);
                           color: #0d0f14; border: none; border-radius: 10px; font-weight: 700; cursor: pointer;
                           display:flex; align-items:center; gap:8px;">
                </button>
            </div>

            <p style="color: #888; font-size: 0.85rem; margin-top: 25px;">
                <i class="fas fa-shield-alt"></i> Compra 100% segura • Soporte 24/7
            </p>
        </div>
    `;

    document.body.appendChild(modal);

    const btnCancelar  = modal.querySelector('.btn-cancelar');
    const btnSec       = modal.querySelector('.btn-sec');
    const btnPri       = modal.querySelector('.btn-pri');
    const msg          = modal.querySelector('.m-msg');
    const title        = modal.querySelector('.m-title');

    btnCancelar.addEventListener('click', () => cerrarModal(modal));
    modal.addEventListener('click', e => { if (e.target === modal) cerrarModal(modal); });

    if (!userIsLogged) {
        btnSec.style.display = 'none';
        btnPri.innerHTML = `<i class="fas fa-sign-in-alt"></i> Iniciar sesión`;
        btnPri.addEventListener('click', () => { window.location.href = redirectLogin; });
        return;
    }

    if (userSaldo < precio) {
        title.textContent = 'Saldo insuficiente';
        msg.textContent = `No cuentas con saldo suficiente. Tu saldo: S/ ${Number(userSaldo).toFixed(2)}`;
        btnSec.innerHTML = `<i class="fas fa-wallet"></i> Ir a recargar`;
        btnPri.innerHTML = `<i class="fas fa-wallet"></i> Recargar saldo`;
        btnSec.addEventListener('click', () => window.location.href = 'recargar.php');
        btnPri.addEventListener('click', () => window.location.href = 'recargar.php');
        return;
    }

    btnSec.innerHTML = `<i class="fas fa-cart-plus"></i> Añadir al carrito`;
    btnPri.innerHTML = `<i class="fas fa-credit-card"></i> Comprar ahora`;

    btnSec.addEventListener('click', async () => {
        btnSec.disabled = true;
        btnPri.disabled = true;
        msg.textContent = '';

        try {
            const r = await postForm('comprar.php', { action: 'add_to_cart', product_id: String(id) });
            const j = await safeJson(r);

            if (!r.ok || !j.ok) {
                msg.textContent = j.message || 'No se pudo añadir al carrito.';
            } else {
                toast('Añadido al carrito ✅');
                cerrarModal(modal);
            }
        } catch (e) {
            msg.textContent = 'Error de red.';
        } finally {
            btnSec.disabled = false;
            btnPri.disabled = false;
        }
    });

    btnPri.addEventListener('click', async () => {
        btnSec.disabled = true;
        btnPri.disabled = true;
        msg.textContent = '';
        const old = btnPri.innerHTML;
        btnPri.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Procesando...`;

        try {
            const r = await postForm('comprar.php', { action: 'buy', product_id: String(id) });
            const j = await safeJson(r);

            if (!r.ok || !j.ok) {
                if (j.code === 'SALDO_INSUFICIENTE') {
                    title.textContent = 'Saldo insuficiente';
                    msg.textContent = j.message || 'Saldo insuficiente. Ve a recargar.';
                    btnPri.innerHTML = `<i class="fas fa-wallet"></i> Recargar`;
                    btnPri.disabled = false;
                    btnPri.onclick = () => window.location.href = 'recargar.php';
                    return;
                }
                if (j.code === 'SIN_STOCK') {
                    title.textContent = 'Sin stock';
                    msg.textContent = j.message || 'Este producto ya no tiene stock.';
                    btnPri.innerHTML = `<i class="fas fa-times"></i> OK`;
                    btnPri.disabled = false;
                    btnPri.onclick = () => cerrarModal(modal);
                    return;
                }
                if (j.code === 'NOT_LOGGED') {
                    window.location.href = j.redirect || redirectLogin;
                    return;
                }
                msg.textContent = j.message || 'No se pudo completar la compra.';
                btnPri.innerHTML = old;
                btnSec.disabled = false;
                btnPri.disabled = false;
                return;
            }

            // ✅ Compra OK -> mostrar visto + credenciales + copiar
            renderCompraExitosa(modal, j);

        } catch (e) {
            msg.textContent = 'Error de red.';
            btnPri.innerHTML = old;
            btnSec.disabled = false;
            btnPri.disabled = false;
        }
    });
}

// Delegación click compra (evita doble ejecución)
document.addEventListener('click', (e)=>{
  const btn = e.target.closest('.btn-buy');
  if (!btn || btn.disabled) return;

  const id = parseInt(btn.dataset.id || '0', 10);
  const nombre = btn.dataset.nombre || '';
  const precio = parseFloat(btn.dataset.precio || '0');

  if (!id || !precio) return;
  abrirModalCompra(id, nombre, precio);
});
</script>

<?php if ($producto_seleccionado): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Pequeña espera para que se cargue todo
    setTimeout(() => {
        // Encontrar el producto seleccionado
        const productCards = document.querySelectorAll('.producto-card');
        productCards.forEach(card => {
            const productId = card.getAttribute('data-product-id');
            if (productId === '<?php echo $producto_seleccionado['id']; ?>') {
                // Hacer scroll suave
                card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                
                // Agregar animación de resaltado
                card.style.animation = 'highlightPulse 2s ease-in-out';
                card.style.border = '2px solid #ffac12';
                card.style.position = 'relative';
                card.style.zIndex = '10';
                
                // Crear keyframes para la animación
                const style = document.createElement('style');
                style.textContent = `
                    @keyframes highlightPulse {
                        0% { box-shadow: 0 0 0 0 rgba(255, 172, 18, 0.7); transform: scale(1); }
                        50% { box-shadow: 0 0 0 20px rgba(255, 172, 18, 0); transform: scale(1.02); }
                        100% { box-shadow: 0 0 0 0 rgba(255, 172, 18, 0); transform: scale(1); }
                    }
                `;
                document.head.appendChild(style);
                
                // Remover la animación después de 3 segundos
                setTimeout(() => {
                    card.style.animation = '';
                    card.style.border = '';
                    card.style.zIndex = '';
                }, 3000);
            }
        });
    }, 500); // Pequeño delay para asegurar que todo esté cargado
});
</script>
<?php endif; ?>

</body>
</html>
