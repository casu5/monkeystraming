<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

header('Content-Type: application/json; charset=UTF-8');

$DEBUG = false;

function respond(array $data, int $http = 200): void
{
    http_response_code($http);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function tableExistsLocal(mysqli $cx, string $table): bool
{
    $t = $cx->real_escape_string($table);
    $rs = $cx->query("SHOW TABLES LIKE '$t'");
    return ($rs && $rs->num_rows > 0);
}

function colExistsLocal(mysqli $cx, string $table, string $col): bool
{
    $t = $cx->real_escape_string($table);
    $c = $cx->real_escape_string($col);
    $rs = $cx->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
    return ($rs && $rs->num_rows > 0);
}

$action = $_POST['action'] ?? 'buy';
$product_id = (int)($_POST['product_id'] ?? 0);
$csrf = (string)($_POST['_csrf'] ?? '');

if (!in_array($action, ['buy', 'add_to_cart'], true)) {
    respond(['ok' => false, 'code' => 'BAD_ACTION', 'message' => 'Accion no valida.'], 400);
}

if (empty($_SESSION['_csrf_purchase']) || !hash_equals((string)$_SESSION['_csrf_purchase'], $csrf)) {
    respond(['ok' => false, 'code' => 'BAD_CSRF', 'message' => 'Sesion expirada. Recarga la pagina e intenta nuevamente.'], 403);
}

if (!function_exists('isLoggedIn') || !isLoggedIn()) {
    respond(['ok' => false, 'code' => 'NOT_LOGGED', 'message' => 'Debes iniciar sesion.', 'redirect' => 'login.php'], 401);
}

$u = getCurrentUser();
$uid = (int)($u['id'] ?? 0);
if ($uid <= 0) {
    respond(['ok' => false, 'code' => 'NOT_LOGGED', 'message' => 'Sesion invalida.', 'redirect' => 'login.php'], 401);
}
$account_url = (($u['rol'] ?? $u['role'] ?? '') === 'admin')
    ? 'admin/index.php'
    : 'user/dashboard.php';

if ($product_id <= 0) {
    respond(['ok' => false, 'code' => 'BAD_PRODUCT', 'message' => 'Producto invalido.'], 400);
}

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $productosHasVendedor = colExistsLocal($conexion, 'productos', 'vendedor_id');
    $productosHasDuracion = colExistsLocal($conexion, 'productos', 'duracion_dias');
    $productosHasTipoVenta = colExistsLocal($conexion, 'productos', 'tipo_venta');
    $cuentasHasVendedor = colExistsLocal($conexion, 'cuentas', 'vendedor_id');
    $comprasHasCliente = colExistsLocal($conexion, 'compras', 'cliente_id');
    $comprasHasVendedor = colExistsLocal($conexion, 'compras', 'vendedor_id');
    $comprasHasCuenta = colExistsLocal($conexion, 'compras', 'cuenta_id');
    $comprasHasPerfil = colExistsLocal($conexion, 'compras', 'perfil_id');
    $comprasHasComision = colExistsLocal($conexion, 'compras', 'comision_admin');
    $comprasHasMontoVendedor = colExistsLocal($conexion, 'compras', 'monto_vendedor');

    $selectVendedor = $productosHasVendedor ? 'vendedor_id' : 'NULL AS vendedor_id';
    $selectDuracion = $productosHasDuracion ? 'duracion_dias' : '30 AS duracion_dias';
    $selectTipoVenta = $productosHasTipoVenta ? 'tipo_venta' : "'PERFIL' AS tipo_venta";
    $stmtP = $conexion->prepare("SELECT id, nombre, precio, $selectVendedor, $selectDuracion, $selectTipoVenta FROM productos WHERE id=? AND activo=1 LIMIT 1");
    $stmtP->bind_param("i", $product_id);
    $stmtP->execute();
    $prod = $stmtP->get_result()->fetch_assoc();
    $stmtP->close();

    if (!$prod) {
        respond(['ok' => false, 'code' => 'NOT_FOUND', 'message' => 'Producto no existe o no esta activo.'], 404);
    }

    $precio = (float)$prod['precio'];
    $nombreProducto = (string)$prod['nombre'];
    $vendedorId = isset($prod['vendedor_id']) ? (int)$prod['vendedor_id'] : null;
    $duracionDias = max(1, (int)($prod['duracion_dias'] ?? 30));
    $tipoVenta = strtoupper((string)($prod['tipo_venta'] ?? 'PERFIL'));
    $tipoVenta = $tipoVenta === 'CUENTA_COMPLETA' ? 'CUENTA_COMPLETA' : 'PERFIL';
    $venceAt = date('Y-m-d H:i:s', time() + ($duracionDias * 86400));
    $fecha_compra = date('Y-m-d H:i:s');

    if ($action === 'add_to_cart') {
        if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }

        $sellerFilterCart = ($cuentasHasVendedor && $vendedorId) ? " AND c.vendedor_id = ? " : "";
        $stmtStockCart = $conexion->prepare("
            SELECT COUNT(*) c
            FROM cuenta_perfiles cp
            JOIN cuentas c ON c.id = cp.cuenta_id
            WHERE c.producto_id = ?
              $sellerFilterCart
              AND c.estado = 'DISPONIBLE'
              AND cp.estado = 'DISPONIBLE'
        ");
        if ($cuentasHasVendedor && $vendedorId) {
            $stmtStockCart->bind_param("ii", $product_id, $vendedorId);
        } else {
            $stmtStockCart->bind_param("i", $product_id);
        }
        $stmtStockCart->execute();
        $availableForCart = (int)($stmtStockCart->get_result()->fetch_assoc()['c'] ?? 0);
        $stmtStockCart->close();

        $currentQty = isset($_SESSION['cart'][$product_id]) ? max(1, (int)($_SESSION['cart'][$product_id]['qty'] ?? 1)) : 0;
        $nextQty = $currentQty + 1;
        if ($availableForCart <= 0 || $nextQty > $availableForCart) {
            respond([
                'ok' => false,
                'code' => 'SIN_STOCK',
                'message' => $currentQty > 0 ? 'No hay mas stock disponible para agregar otra unidad.' : 'Este producto no tiene stock disponible.',
                'cart_count' => function_exists('cartCount') ? cartCount() : 0,
            ], 409);
        }

        $_SESSION['cart'][$product_id] = [
            'id' => $product_id,
            'nombre' => $nombreProducto,
            'precio' => $precio,
            'vendedor_id' => $vendedorId,
            'qty' => $nextQty,
            'added_at' => $_SESSION['cart'][$product_id]['added_at'] ?? date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $cartCount = 0;
        foreach ($_SESSION['cart'] as $item) {
            $cartCount += max(1, (int)($item['qty'] ?? 1));
        }

        respond([
            'ok' => true,
            'message' => $nextQty > 1 ? 'Cantidad actualizada en el carrito.' : 'Producto anadido al carrito.',
            'cart_count' => $cartCount,
            'qty' => $nextQty,
        ]);
    }

    $conexion->begin_transaction();

    $stmtS = $conexion->prepare("SELECT saldo FROM usuarios WHERE id=? FOR UPDATE");
    $stmtS->bind_param("i", $uid);
    $stmtS->execute();
    $rowS = $stmtS->get_result()->fetch_assoc();
    $stmtS->close();

    if (!$rowS) {
        throw new Exception("Usuario no encontrado.");
    }

    $saldo = (float)$rowS['saldo'];
    if ($saldo < $precio) {
        $conexion->rollback();
        respond(['ok' => false, 'code' => 'SALDO_INSUFICIENTE', 'message' => 'Saldo insuficiente.', 'redirect' => 'recargar.php'], 409);
    }

    $sellerFilter = ($cuentasHasVendedor && $vendedorId) ? " AND c.vendedor_id = ? " : "";
    $stmtPick = $conexion->prepare("
        SELECT
            cp.id AS perfil_id,
            cp.perfil_nombre,
            c.id AS cuenta_id,
            c.login_user,
            c.login_pass,
            c.pin,
            c.max_perfiles
        FROM cuenta_perfiles cp
        JOIN cuentas c ON c.id = cp.cuenta_id
        WHERE c.producto_id = ?
          $sellerFilter
          AND c.estado = 'DISPONIBLE'
          AND cp.estado = 'DISPONIBLE'
        ORDER BY cp.id ASC
        LIMIT 1
        FOR UPDATE
    ");
    if ($cuentasHasVendedor && $vendedorId) {
        $stmtPick->bind_param("ii", $product_id, $vendedorId);
    } else {
        $stmtPick->bind_param("i", $product_id);
    }
    $stmtPick->execute();
    $stock = $stmtPick->get_result()->fetch_assoc();
    $stmtPick->close();

    if (!$stock) {
        $conexion->rollback();
        respond(['ok' => false, 'code' => 'SIN_STOCK', 'message' => 'Este producto no tiene stock disponible.'], 409);
    }

    $perfilId = (int)$stock['perfil_id'];

    $stmtV = $conexion->prepare("
        UPDATE cuenta_perfiles
        SET
            estado = 'VENDIDO',
            vendido_a_usuario_id = ?,
            compra_item_id = NULL,
            vendido_at = NOW(),
            vence_at = ?
        WHERE id = ?
          AND estado = 'DISPONIBLE'
    ");
    $stmtV->bind_param("isi", $uid, $venceAt, $perfilId);
    $stmtV->execute();

    if ($stmtV->affected_rows <= 0) {
        $stmtV->close();
        throw new Exception("No se pudo reservar el perfil.");
    }
    $stmtV->close();

    $stmtU = $conexion->prepare("UPDATE usuarios SET saldo = saldo - ? WHERE id=?");
    $stmtU->bind_param("di", $precio, $uid);
    $stmtU->execute();
    $stmtU->close();

    $compraCols = ['usuario_id', 'producto_id', 'monto', 'estado', 'fecha_compra', 'fecha_vencimiento'];
    $compraVals = ['?', '?', '?', "'completada'", '?', '?'];
    $types = 'iidss';
    $bind = [$uid, $product_id, $precio, $fecha_compra, $venceAt];

    if ($comprasHasCliente) {
        array_unshift($compraCols, 'cliente_id');
        array_unshift($compraVals, '?');
        $types = 'i' . $types;
        array_unshift($bind, $uid);
    }
    if ($comprasHasVendedor) {
        $compraCols[] = 'vendedor_id';
        $compraVals[] = '?';
        $types .= 'i';
        $bind[] = (int)($vendedorId ?? 0);
    }
    if ($comprasHasCuenta) {
        $compraCols[] = 'cuenta_id';
        $compraVals[] = '?';
        $types .= 'i';
        $bind[] = (int)$stock['cuenta_id'];
    }
    if ($comprasHasPerfil) {
        $compraCols[] = 'perfil_id';
        $compraVals[] = '?';
        $types .= 'i';
        $bind[] = $perfilId;
    }
    if ($comprasHasComision) {
        $compraCols[] = 'comision_admin';
        $compraVals[] = '?';
        $types .= 'd';
        $bind[] = 0.00;
    }
    if ($comprasHasMontoVendedor) {
        $compraCols[] = 'monto_vendedor';
        $compraVals[] = '?';
        $types .= 'd';
        $bind[] = $precio;
    }

    $stmtCompra = $conexion->prepare(
        "INSERT INTO compras (" . implode(', ', $compraCols) . ") VALUES (" . implode(', ', $compraVals) . ")"
    );
    $stmtCompra->bind_param($types, ...$bind);
    $stmtCompra->execute();
    $compra_id = $stmtCompra->insert_id;
    $stmtCompra->close();

    $stmtUpdatePerfil = $conexion->prepare("UPDATE cuenta_perfiles SET compra_item_id = ? WHERE id = ?");
    $stmtUpdatePerfil->bind_param("ii", $compra_id, $perfilId);
    $stmtUpdatePerfil->execute();
    $stmtUpdatePerfil->close();

    $cuentaId = (int)$stock['cuenta_id'];
    $stmtRemaining = $conexion->prepare("SELECT COUNT(*) c FROM cuenta_perfiles WHERE cuenta_id=? AND estado='DISPONIBLE'");
    $stmtRemaining->bind_param("i", $cuentaId);
    $stmtRemaining->execute();
    $remainingProfiles = (int)($stmtRemaining->get_result()->fetch_assoc()['c'] ?? 0);
    $stmtRemaining->close();

    if ($remainingProfiles <= 0) {
        $stmtAccountSold = $conexion->prepare("UPDATE cuentas SET estado='VENDIDA_COMPLETA' WHERE id=?");
        $stmtAccountSold->bind_param("i", $cuentaId);
        $stmtAccountSold->execute();
        $stmtAccountSold->close();
    }

    if (colExistsLocal($conexion, 'productos', 'stock')) {
        $stockSellerFilter = ($cuentasHasVendedor && $vendedorId) ? " AND cu.vendedor_id = ? " : "";
        $stmtStock = $conexion->prepare("
            SELECT COUNT(*) c
            FROM cuenta_perfiles cp
            INNER JOIN cuentas cu ON cu.id = cp.cuenta_id
            WHERE cu.producto_id=?
              $stockSellerFilter
              AND cu.estado='DISPONIBLE'
              AND cp.estado='DISPONIBLE'
        ");
        if ($cuentasHasVendedor && $vendedorId) {
            $stmtStock->bind_param("ii", $product_id, $vendedorId);
        } else {
            $stmtStock->bind_param("i", $product_id);
        }
        $stmtStock->execute();
        $stockRestante = (int)($stmtStock->get_result()->fetch_assoc()['c'] ?? 0);
        $stmtStock->close();

        $stmtProductStock = $conexion->prepare("UPDATE productos SET stock=? WHERE id=?");
        $stmtProductStock->bind_param("ii", $stockRestante, $product_id);
        $stmtProductStock->execute();
        $stmtProductStock->close();
    }

    $stmtF = $conexion->prepare("SELECT vendido_at, vence_at FROM cuenta_perfiles WHERE id=? LIMIT 1");
    $stmtF->bind_param("i", $perfilId);
    $stmtF->execute();
    $fechas = $stmtF->get_result()->fetch_assoc();
    $stmtF->close();

    if (tableExistsLocal($conexion, 'saldo_movimientos')) {
        $saldoNuevo = $saldo - $precio;
        $nota = 'Compra de ' . $nombreProducto;
        $stmtMov = $conexion->prepare("
            INSERT INTO saldo_movimientos
                (usuario_id, actor_id, tipo, monto, saldo_anterior, saldo_nuevo, referencia_tipo, referencia_id, nota)
            VALUES (?, ?, 'compra', ?, ?, ?, 'compras', ?, ?)
        ");
        $stmtMov->bind_param("iidddis", $uid, $uid, $precio, $saldo, $saldoNuevo, $compra_id, $nota);
        $stmtMov->execute();
        $stmtMov->close();
    }

    $conexion->commit();

    $_SESSION['user_saldo'] = $saldo - $precio;
    if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
        unset($_SESSION['cart'][$product_id]);
    }

    respond([
        'ok' => true,
        'code' => 'OK',
        'compra_id' => $compra_id,
        'cart_count' => function_exists('cartCount') ? cartCount() : 0,
        'saldo' => $_SESSION['user_saldo'],
        'redirect' => $account_url,
        'purchase' => [
            'producto_id' => $product_id,
            'vendedor_id' => $vendedorId,
            'producto_nombre' => $nombreProducto,
            'fecha_compra' => $fechas['vendido_at'] ?? $fecha_compra,
            'vence_at' => $fechas['vence_at'] ?? $venceAt,
            'credenciales' => [
                'login_user' => $stock['login_user'],
                'login_pass' => $stock['login_pass'],
                'pin' => $stock['pin'],
                'perfil_nombre' => $stock['perfil_nombre'],
                'max_perfiles' => (int)$stock['max_perfiles'],
            ],
        ],
    ]);
} catch (Throwable $e) {
    if (isset($conexion) && $conexion instanceof mysqli) {
        try { $conexion->rollback(); } catch (Throwable $x) {}
    }

    $out = ['ok' => false, 'code' => 'ERROR', 'message' => 'Error al procesar compra.'];
    if ($DEBUG) {
        $out['debug'] = [
            'error' => $e->getMessage(),
            'file' => basename($e->getFile()),
            'line' => $e->getLine(),
        ];
    }
    respond($out, 500);
}
