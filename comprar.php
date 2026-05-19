<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

header('Content-Type: application/json; charset=UTF-8');

$DEBUG = true; // ponlo false cuando ya funcione

function respond(array $data, int $http = 200): void {
    http_response_code($http);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$action     = $_POST['action'] ?? 'buy';
$product_id = (int)($_POST['product_id'] ?? 0);

if (!function_exists('isLoggedIn') || !isLoggedIn()) {
    respond(['ok'=>false,'code'=>'NOT_LOGGED','message'=>'Debes iniciar sesión.','redirect'=>'login.php'], 401);
}

$u   = getCurrentUser();
$uid = (int)($u['id'] ?? 0);
if ($uid <= 0) {
    respond(['ok'=>false,'code'=>'NOT_LOGGED','message'=>'Sesión inválida.','redirect'=>'login.php'], 401);
}

if ($product_id <= 0) {
    respond(['ok'=>false,'code'=>'BAD_PRODUCT','message'=>'Producto inválido.'], 400);
}

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    // Carrito placeholder
    if ($action === 'add_to_cart') {
        respond(['ok'=>true,'message'=>'Añadido (placeholder).']);
    }

    // 1) Producto
    $stmtP = $conexion->prepare("SELECT id, nombre, precio FROM productos WHERE id=? AND activo=1 LIMIT 1");
    $stmtP->bind_param("i", $product_id);
    $stmtP->execute();
    $prod = $stmtP->get_result()->fetch_assoc();
    $stmtP->close();

    if (!$prod) {
        respond(['ok'=>false,'code'=>'NOT_FOUND','message'=>'Producto no existe o no está activo.'], 404);
    }

    $precio = (float)$prod['precio'];
    $nombreProducto = (string)$prod['nombre'];

    // ✅ FECHA DE VENCIMIENTO (30 días después)
    $duracionDias = 30;
    $venceAt = date('Y-m-d H:i:s', time() + ($duracionDias * 86400));
    $fecha_compra = date('Y-m-d H:i:s'); // Fecha actual

    $conexion->begin_transaction();

    // 2) Lock saldo usuario
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
        respond(['ok'=>false,'code'=>'SALDO_INSUFICIENTE','message'=>'Saldo insuficiente.','redirect'=>'recargar.php'], 409);
    }

    // 3) Tomar 1 PERFIL disponible del producto (lock)
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
          AND c.estado = 'DISPONIBLE'
          AND cp.estado = 'DISPONIBLE'
        ORDER BY cp.id ASC
        LIMIT 1
        FOR UPDATE
    ");
    $stmtPick->bind_param("i", $product_id);
    $stmtPick->execute();
    $stock = $stmtPick->get_result()->fetch_assoc();
    $stmtPick->close();

    if (!$stock) {
        $conexion->rollback();
        respond(['ok'=>false,'code'=>'SIN_STOCK','message'=>'Este producto no tiene stock disponible.'], 409);
    }

    $perfilId = (int)$stock['perfil_id'];

    // 4) Marcar perfil como vendido (sin compras aún)
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
        throw new Exception("No se pudo reservar el perfil (venta simultánea o estado cambió).");
    }
    $stmtV->close();

    // 5) Descontar saldo
    $stmtU = $conexion->prepare("UPDATE usuarios SET saldo = saldo - ? WHERE id=?");
    $stmtU->bind_param("di", $precio, $uid);
    $stmtU->execute();
    $stmtU->close();

    // ✅✅✅ CORRECCIÓN: INSERTAR EN TABLA COMPRAS CON FECHA VENCIMIENTO ✅✅✅
    $stmtCompra = $conexion->prepare("
        INSERT INTO compras (
            usuario_id, 
            producto_id, 
            monto, 
            estado, 
            fecha_compra, 
            fecha_vencimiento  -- ✅ NUEVA COLUMNA
        ) VALUES (?, ?, ?, 'completada', ?, ?)
    ");
    $stmtCompra->bind_param("iidss", $uid, $product_id, $precio, $fecha_compra, $venceAt);
    $stmtCompra->execute();
    $compra_id = $stmtCompra->insert_id;
    $stmtCompra->close();

    // ✅ ACTUALIZAR perfil con el ID de la compra
    $stmtUpdatePerfil = $conexion->prepare("
        UPDATE cuenta_perfiles
        SET compra_item_id = ?
        WHERE id = ?
    ");
    $stmtUpdatePerfil->bind_param("ii", $compra_id, $perfilId);
    $stmtUpdatePerfil->execute();
    $stmtUpdatePerfil->close();

    // 6) Obtener fechas reales
    $stmtF = $conexion->prepare("SELECT vendido_at, vence_at FROM cuenta_perfiles WHERE id=? LIMIT 1");
    $stmtF->bind_param("i", $perfilId);
    $stmtF->execute();
    $fechas = $stmtF->get_result()->fetch_assoc();
    $stmtF->close();

    $conexion->commit();

    respond([
        'ok' => true,
        'code' => 'OK',
        'compra_id' => $compra_id,
        'purchase' => [
            'producto_id' => $product_id,
            'producto_nombre' => $nombreProducto,
            'fecha_compra' => $fechas['vendido_at'] ?? $fecha_compra,
            'vence_at'     => $fechas['vence_at'] ?? $venceAt,
            'credenciales' => [
                'login_user'    => $stock['login_user'],
                'login_pass'    => $stock['login_pass'],
                'pin'           => $stock['pin'],
                'perfil_nombre' => $stock['perfil_nombre'],
                'max_perfiles'  => (int)$stock['max_perfiles'],
            ]
        ]
    ]);

} catch (Throwable $e) {
    if (isset($conexion) && $conexion instanceof mysqli) {
        try { $conexion->rollback(); } catch(Throwable $x) {}
    }

    $out = ['ok'=>false,'code'=>'ERROR','message'=>'Error al procesar compra.'];
    if ($DEBUG) {
        $out['debug'] = [
            'error' => $e->getMessage(),
            'file'  => basename($e->getFile()),
            'line'  => $e->getLine(),
        ];
    }
    respond($out, 500);
}