<?php
require_once '../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// OK INICIAR SESIÓN SI NO ESTÁ INICIADA
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// OK FUNCIÓN REDIRECT (si no existe)
if (!function_exists('redirect')) {
    function redirect($url) {
        header("Location: $url");
        exit;
    }
}

// OK VERIFICAR LOGIN MANUALMENTE
requireRole(['cliente', 'admin']);

function tableExistsUserHistory(mysqli $cx, string $table): bool {
    $t = $cx->real_escape_string($table);
    $rs = $cx->query("SHOW TABLES LIKE '$t'");
    return ($rs && $rs->num_rows > 0);
}

function colExistsUserHistory(mysqli $cx, string $table, string $col): bool {
    $t = $cx->real_escape_string($table);
    $c = $cx->real_escape_string($col);
    $rs = $cx->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
    return ($rs && $rs->num_rows > 0);
}

$user_id = (int)$_SESSION['user_id'];

// OK OBTENER DATOS DEL USUARIO
$sql_user = "SELECT id, nombre, email, saldo FROM usuarios WHERE id = ?";
$stmt_user = $conexion->prepare($sql_user);
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$usuario = $stmt_user->get_result()->fetch_assoc();
$stmt_user->close();

if (!$usuario) {
    redirect('../logout.php');
}

$page_title = "Historial - Monkeystraming";

include '../includes/header.php';

// OK COMPRAS CON FECHA DE VENCIMIENTO
$hasCuentas = tableExistsUserHistory($conexion, 'cuentas');
$hasPerfiles = tableExistsUserHistory($conexion, 'cuenta_perfiles');
$comprasHasCuenta = colExistsUserHistory($conexion, 'compras', 'cuenta_id');
$comprasHasPerfil = colExistsUserHistory($conexion, 'compras', 'perfil_id');
$perfilesHasCompraItem = $hasPerfiles && colExistsUserHistory($conexion, 'cuenta_perfiles', 'compra_item_id');

$credSelect = ", NULL AS login_user, NULL AS login_pass, NULL AS pin, NULL AS perfil_nombre";
$credJoin = "";
if ($hasCuentas && $hasPerfiles && $comprasHasCuenta && $comprasHasPerfil) {
    $credSelect = ", cu.login_user, cu.login_pass, cu.pin, cp.perfil_nombre";
    $credJoin = "
                LEFT JOIN cuentas cu ON cu.id = c.cuenta_id
                LEFT JOIN cuenta_perfiles cp ON cp.id = c.perfil_id";
} elseif ($hasCuentas && $hasPerfiles && $perfilesHasCompraItem) {
    $credSelect = ", cu.login_user, cu.login_pass, cu.pin, cp.perfil_nombre";
    $credJoin = "
                LEFT JOIN cuenta_perfiles cp ON cp.compra_item_id = c.id
                LEFT JOIN cuentas cu ON cu.id = cp.cuenta_id";
}

$sql_compras = "SELECT c.id, p.nombre, c.monto, c.estado, c.fecha_compra, c.fecha_vencimiento
                       $credSelect
                FROM compras c
                JOIN productos p ON c.producto_id = p.id
                $credJoin
                WHERE c.usuario_id = ?
                ORDER BY c.fecha_compra DESC";
$stmt_c = $conexion->prepare($sql_compras);
$stmt_c->bind_param("i", $user_id);
$stmt_c->execute();
$compras = $stmt_c->get_result();

// OK RECARGAS
$sql_r = "SELECT id, metodo, monto, comision, estado, fecha_solicitud
          FROM recargas
          WHERE usuario_id = ?
          ORDER BY fecha_solicitud DESC";
$stmt_r = $conexion->prepare($sql_r);
$stmt_r->bind_param("i", $user_id);
$stmt_r->execute();
$recargas = $stmt_r->get_result();
?>

<div class="container">
    <div class="user-dashboard">
        <h1 style="margin-bottom:20px;">Historial de movimientos</h1>

        <div class="content-card" style="margin-bottom:30px;">
            <div class="card-header">
                <h3><i class="fas fa-shopping-cart"></i> Compras</h3>
            </div>
            <div class="card-body">
                <?php if ($compras->num_rows > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Fecha compra</th>
                            <th>Vence el</th> <!-- OK NUEVA COLUMNA -->
                            <th>Credenciales</th>
                            <th>Monto</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while($c = $compras->fetch_assoc()): 
                        // OK CALCULAR ESTADO DE VENCIMIENTO
                        $vencimiento_txt = '-';
                        $vencimiento_class = '';
                        
                        if (!empty($c['fecha_vencimiento'])) {
                            $vencimiento_txt = date('d/m/Y H:i', strtotime($c['fecha_vencimiento']));
                            $hoy = time();
                            $vencimiento = strtotime($c['fecha_vencimiento']);
                            
                            if ($vencimiento < $hoy) {
                                $vencimiento_class = 'vencido';
                                $vencimiento_txt .= ' âš ï¸';
                            } elseif (($vencimiento - $hoy) < (3 * 86400)) {
                                $vencimiento_class = 'proximo';
                                $vencimiento_txt .= ' â³';
                            }
                        }
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($c['nombre']); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($c['fecha_compra'])); ?></td>
                            <td class="<?php echo $vencimiento_class; ?>">
                                <?php echo $vencimiento_txt; ?>
                            </td>
                            <td>
                                <?php if (!empty($c['login_user']) || !empty($c['login_pass'])): ?>
                                    <details class="cred-box">
                                        <summary>Ver datos</summary>
                                        <div><strong>Usuario:</strong> <?php echo htmlspecialchars((string)$c['login_user']); ?></div>
                                        <div><strong>Clave:</strong> <?php echo htmlspecialchars((string)$c['login_pass']); ?></div>
                                        <?php if (!empty($c['perfil_nombre'])): ?>
                                            <div><strong>Perfil:</strong> <?php echo htmlspecialchars((string)$c['perfil_nombre']); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($c['pin'])): ?>
                                            <div><strong>PIN:</strong> <?php echo htmlspecialchars((string)$c['pin']); ?></div>
                                        <?php endif; ?>
                                    </details>
                                <?php else: ?>
                                    <span class="muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>S/ <?php echo number_format($c['monto'], 2); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $c['estado']; ?>">
                                    <?php echo ucfirst($c['estado']); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <p style="padding:20px 0; color:#aaa;">No tienes compras registradas.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-coins"></i> Recargas</h3>
            </div>
            <div class="card-body">
                <?php if ($recargas->num_rows > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Método</th>
                            <th>Monto</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while($r = $recargas->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo date('d/m/Y H:i', strtotime($r['fecha_solicitud'])); ?></td>
                            <td><?php echo htmlspecialchars($r['metodo']); ?></td>
                            <td>S/ <?php echo number_format($r['monto'], 2); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $r['estado']; ?>">
                                    <?php echo ucfirst($r['estado']); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <p style="padding:20px 0; color:#aaa;">No tienes recargas registradas.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
/*  ESTILOS ACTUALIZADOS */
.container { max-width: 1200px; margin: 40px auto; padding: 0 20px; }
.user-dashboard { display: flex; flex-direction: column; gap: 30px; }

.content-card { 
    background: rgba(255, 255, 255, 0.04); 
    border-radius: 18px; 
    border: 1px solid rgba(255, 255, 255, 0.06); 
    overflow: hidden; 
}

.card-header { 
    padding: 20px 25px; 
    border-bottom: 1px solid rgba(255, 255, 255, 0.06); 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
}

.card-header h3 { 
    font-size: 1.3rem; 
    color: #fff; 
    display: flex; 
    align-items: center; 
    gap: 10px; 
}

.card-body { padding: 25px; }

.data-table { 
    width: 100%; 
    border-collapse: collapse; 
}

.data-table th { 
    text-align: left; 
    padding: 12px 0; 
    color: #aaa; 
    font-weight: 500; 
    border-bottom: 1px solid rgba(255, 255, 255, 0.06); 
}

.data-table td { 
    padding: 15px 0; 
    border-bottom: 1px solid rgba(255, 255, 255, 0.04); 
}

.data-table tr:last-child td { 
    border-bottom: none; 
}

.status-badge { 
    padding: 5px 12px; 
    border-radius: 20px; 
    font-size: 0.8rem; 
    font-weight: 600; 
    display: inline-block; 
}

.status-completada, .status-aprobada { 
    background: rgba(52, 199, 89, 0.2); 
    color: #34c759; 
}

.status-pendiente { 
    background: rgba(255, 204, 0, 0.2); 
    color: #ffcc00; 
}

.status-cancelada, .status-rechazada { 
    background: rgba(255, 59, 48, 0.2); 
    color: #ff3b30; 
}

/* OK ESTILOS PARA VENCIMIENTOS */
.vencido { 
    color: #ff3b30 !important; 
    font-weight: bold; 
    background: rgba(255, 59, 48, 0.1); 
    padding: 2px 8px;
    border-radius: 4px;
}

.proximo { 
    color: #ffcc00 !important; 
    font-weight: bold; 
    background: rgba(255, 204, 0, 0.1); 
    padding: 2px 8px;
    border-radius: 4px;
}

.cred-box {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 10px;
    padding: 8px 10px;
    color: #ddd;
    max-width: 240px;
}

.cred-box summary {
    color: #12aaff;
    cursor: pointer;
    font-weight: 800;
}

.cred-box div {
    margin-top: 7px;
    word-break: break-all;
    font-size: 0.88rem;
}

/* FILTROS (opcional, para más adelante) */
.filtros { 
    display: flex; 
    gap: 10px; 
    margin-bottom: 20px; 
    flex-wrap: wrap; 
}

.filtro-btn { 
    padding: 8px 16px; 
    border-radius: 8px; 
    border: 1px solid rgba(255, 255, 255, 0.1); 
    background: rgba(255, 255, 255, 0.04); 
    color: #aaa; 
    cursor: pointer; 
    transition: all 0.3s ease; 
}

.filtro-btn:hover { 
    background: rgba(18, 170, 255, 0.1); 
    color: #12aaff; 
}

.filtro-btn.active { 
    background: rgba(18, 170, 255, 0.2); 
    color: #12aaff; 
    border-color: #12aaff; 
}
</style>

<?php include '../includes/footer.php'; ?>
