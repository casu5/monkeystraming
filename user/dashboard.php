<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// ✅ Fallback por si redirect() no existe en auth.php
if (!function_exists('redirect')) {
    function redirect($url) {
        header("Location: $url");
        exit;
    }
}

// Requiere login
requireLogin('../login.php'); // o 'login.php' según tu estructura

// Usuario actual (desde auth.php)
$usuario_actual = getCurrentUser();
if (!$usuario_actual) {
    redirect('../login.php');
}

// Variables base
$user_id   = (int)($usuario_actual['id'] ?? $_SESSION['user_id'] ?? 0);
$user_name = (string)($usuario_actual['nombre'] ?? 'Usuario');

if ($user_id <= 0) {
    redirect('../login.php');
}

// Obtener datos del usuario actualizados desde la BD
$sql = "SELECT id, nombre, email, saldo, created_at FROM usuarios WHERE id = ?";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result  = $stmt->get_result();
$usuario = $result->fetch_assoc();
$stmt->close();

if (!$usuario) {
    // Si por alguna razón el usuario no existe en BD, cerramos sesión
    redirect('../logout.php');
}

$saldo_usuario  = (float)$usuario['saldo'];
$fecha_registro = !empty($usuario['created_at']) ? date('d/m/Y', strtotime($usuario['created_at'])) : '-';

// Obtener resumen de compras del mes actual
$sql_resumen = "SELECT 
                    COUNT(*) AS total_compras,
                    COALESCE(SUM(monto), 0) AS total_gastado
                FROM compras
                WHERE usuario_id = ?
                  AND estado = 'completada'
                  AND MONTH(fecha_compra) = MONTH(CURDATE())
                  AND YEAR(fecha_compra) = YEAR(CURDATE())";
$stmt_resumen = $conexion->prepare($sql_resumen);
$stmt_resumen->bind_param("i", $user_id);
$stmt_resumen->execute();
$resumen = $stmt_resumen->get_result()->fetch_assoc();
$stmt_resumen->close();

$compras_mes = (int)($resumen['total_compras'] ?? 0);
$gastado_mes = (float)($resumen['total_gastado'] ?? 0);

// Obtener cantidad de tickets activos
$sql_tickets = "SELECT COUNT(*) AS total_tickets
                FROM tickets
                WHERE usuario_id = ?
                  AND estado IN ('abierto', 'en proceso')";
$stmt_tickets = $conexion->prepare($sql_tickets);
$stmt_tickets->bind_param("i", $user_id);
$stmt_tickets->execute();
$row_tickets = $stmt_tickets->get_result()->fetch_assoc();
$stmt_tickets->close();

$tickets_activos = (int)($row_tickets['total_tickets'] ?? 0);

// ✅ NUEVO: Contar productos vencidos y próximos a vencer
$sql_vencimientos = "SELECT 
    COUNT(CASE WHEN fecha_vencimiento < NOW() THEN 1 END) AS vencidos,
    COUNT(CASE WHEN fecha_vencimiento BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 DAY) THEN 1 END) AS proximos_a_vencer,
    COUNT(CASE WHEN fecha_vencimiento > DATE_ADD(NOW(), INTERVAL 3 DAY) THEN 1 END) AS vigentes
    FROM compras
    WHERE usuario_id = ? 
    AND estado = 'completada'
    AND fecha_vencimiento IS NOT NULL";
$stmt_vencimientos = $conexion->prepare($sql_vencimientos);
$stmt_vencimientos->bind_param("i", $user_id);
$stmt_vencimientos->execute();
$vencimientos = $stmt_vencimientos->get_result()->fetch_assoc();
$stmt_vencimientos->close();

$productos_vencidos = (int)($vencimientos['vencidos'] ?? 0);
$productos_proximos = (int)($vencimientos['proximos_a_vencer'] ?? 0);
$productos_vigentes = (int)($vencimientos['vigentes'] ?? 0);

// Obtener últimas compras con fecha de vencimiento
$sql_compras = "SELECT p.nombre, c.monto, c.fecha_compra, c.estado, c.fecha_vencimiento 
                FROM compras c 
                JOIN productos p ON c.producto_id = p.id 
                WHERE c.usuario_id = ? 
                ORDER BY c.fecha_compra DESC 
                LIMIT 5";
$stmt_compras = $conexion->prepare($sql_compras);
$stmt_compras->bind_param("i", $user_id);
$stmt_compras->execute();
$compras = $stmt_compras->get_result();
$stmt_compras->close();

// Obtener últimas recargas
$sql_recargas = "SELECT monto, metodo, fecha_solicitud, estado 
                 FROM recargas 
                 WHERE usuario_id = ? 
                 ORDER BY fecha_solicitud DESC 
                 LIMIT 5";
$stmt_recargas = $conexion->prepare($sql_recargas);
$stmt_recargas->bind_param("i", $user_id);
$stmt_recargas->execute();
$recargas = $stmt_recargas->get_result();
$stmt_recargas->close();

$page_title = "Mi Cuenta";
include '../includes/header.php';
?>

<div class="container">
    <div class="user-dashboard">
        <!-- Bienvenida -->
        <div class="welcome-card">
            <div class="welcome-content">
                <h1>Hola, <?php echo htmlspecialchars($user_name); ?>! 👋</h1>
                <p>Bienvenido a tu panel de control</p>
            </div>
            <div class="saldo-info">
                <div class="saldo-label">Saldo disponible</div>
                <div class="saldo-amount">S/ <?php echo number_format($saldo_usuario, 2); ?></div>
                <a href="../recargar.php" class="btn-recargar">
                    <i class="fas fa-plus"></i> Recargar
                </a>
            </div>
        </div>

        <!-- Estadísticas rápidas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo (int)$compras_mes; ?></h3>
                    <p>Compras este mes</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-coins"></i>
                </div>
                <div class="stat-content">
                    <h3>S/ <?php echo number_format($gastado_mes, 2); ?></h3>
                    <p>Gastado este mes</p>
                </div>
            </div>

            <!-- ✅ NUEVO: Productos Vigentes -->
            

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-ticket-alt"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo (int)$tickets_activos; ?></h3>
                    <p>Tickets activos</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $fecha_registro; ?></h3>
                    <p>Miembro desde</p>
                </div>
            </div>
        </div>

        <!-- ✅ NUEVO: Alerta de productos vencidos -->
        <?php if ($productos_vencidos > 0): ?>
        <div class="alert-card" style="background: rgba(255, 59, 48, 0.1); border-left: 4px solid #ff3b30;">
            <div style="display: flex; align-items: center; gap: 15px;">
                <div style="font-size: 1.5rem; color: #ff3b30;">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div>
                    <h4 style="color: #fff; margin-bottom: 5px;">¡Tienes <?php echo $productos_vencidos; ?> producto(s) vencido(s)!</h4>
                    <p style="color: #aaa; margin: 0;">Algunos de tus productos han expirado. Considera renovarlos.</p>
                </div>
            </div>
            <a href="historial.php?filtro=vencidos" class="btn-primary" style="background: rgba(255, 59, 48, 0.2); color: #ff3b30; border: 1px solid rgba(255, 59, 48, 0.3);">
                Ver productos vencidos
            </a>
        </div>
        <?php endif; ?>

        <!-- Últimas compras -->
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-history"></i> Últimas Compras</h3>
                <a href="historial.php" class="card-link">Ver historial completo</a>
            </div>
            <div class="card-body">
                <?php if ($compras->num_rows > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Fecha compra</th>
                            <th>Vence el</th>
                            <th>Monto</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($compra = $compras->fetch_assoc()): 
                            // Calcular estado de vencimiento
                            $vencimiento_txt = '-';
                            $vencimiento_class = '';
                            
                            if (!empty($compra['fecha_vencimiento'])) {
                                $vencimiento_txt = date('d/m/Y', strtotime($compra['fecha_vencimiento']));
                                $hoy = time();
                                $vencimiento = strtotime($compra['fecha_vencimiento']);
                                
                                if ($vencimiento < $hoy) {
                                    $vencimiento_class = 'vencido';
                                    $vencimiento_txt .= ' ⚠️';
                                } elseif (($vencimiento - $hoy) < (3 * 86400)) {
                                    $vencimiento_class = 'proximo';
                                    $vencimiento_txt .= ' ⏳';
                                }
                            }
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($compra['nombre']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($compra['fecha_compra'])); ?></td>
                            <td class="<?php echo $vencimiento_class; ?>"><?php echo $vencimiento_txt; ?></td>
                            <td>S/ <?php echo number_format($compra['monto'], 2); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo htmlspecialchars($compra['estado']); ?>">
                                    <?php echo ucfirst($compra['estado']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-shopping-cart"></i>
                    <p>No tienes compras recientes</p>
                    <a href="../productos.php" class="btn-primary">Explorar productos</a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Últimas recargas -->
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-wallet"></i> Últimas Recargas</h3>
                <a href="../recargar.php" class="card-link">Ver opciones de recarga</a>
            </div>
            <div class="card-body">
                <?php if ($recargas->num_rows > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Método</th>
                            <th>Fecha</th>
                            <th>Monto</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($recarga = $recargas->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($recarga['metodo']); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($recarga['fecha_solicitud'])); ?></td>
                            <td>S/ <?php echo number_format($recarga['monto'], 2); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo htmlspecialchars($recarga['estado']); ?>">
                                    <?php echo ucfirst($recarga['estado']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-wallet"></i>
                    <p>Todavía no has hecho recargas</p>
                    <a href="../recargar.php" class="btn-primary">Hacer mi primera recarga</a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Acciones rápidas -->
        <div class="quick-actions">
            <a href="../productos.php" class="quick-action">
                <div class="action-icon">
                    <i class="fas fa-store"></i>
                </div>
                <h4>Comprar Productos</h4>
                <p>Explora nuestro catálogo</p>
            </a>

            <a href="perfil.php" class="quick-action">
                <div class="action-icon">
                    <i class="fas fa-user-edit"></i>
                </div>
                <h4>Editar Perfil</h4>
                <p>Actualiza tu información</p>
            </a>

            <a href="tickets-usuario.php" class="quick-action">
                <div class="action-icon">
                    <i class="fas fa-headset"></i>
                </div>
                <h4>Soporte</h4>
                <p>Abre un ticket de ayuda</p>
            </a>

            <a href="../recargar.php" class="quick-action">
                <div class="action-icon">
                    <i class="fas fa-wallet"></i>
                </div>
                <h4>Recargar Saldo</h4>
                <p>Añade saldo a tu cuenta</p>
            </a>
        </div>
    </div>
</div>

<style>
/* (Tu CSS existente) */
.container { max-width: 1200px; margin: 40px auto; padding: 0 20px; }
.user-dashboard { display: flex; flex-direction: column; gap: 30px; }
.welcome-card {
    background: linear-gradient(135deg, rgba(18, 170, 255, 0.1), rgba(13, 224, 201, 0.05));
    border-radius: 20px; padding: 30px; display: flex; justify-content: space-between; align-items: center;
    border: 1px solid rgba(18, 170, 255, 0.2); backdrop-filter: blur(10px);
}
.welcome-content h1 { font-size: 2.2rem; color: #fff; margin-bottom: 10px; }
.welcome-content p { color: #aaa; font-size: 1.1rem; }
.saldo-info { text-align: right; }
.saldo-label { color: #aaa; font-size: 0.9rem; margin-bottom: 5px; }
.saldo-amount { font-size: 2.5rem; font-weight: 800; color: #faad13; margin-bottom: 15px; }
.btn-recargar { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px;
    background: linear-gradient(135deg, #fc8906, #faae17); color: #0d0f14; text-decoration: none;
    border-radius: 10px; font-weight: 600; transition: all 0.3s ease; }
.btn-recargar:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(18, 170, 255, 0.3); }
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
.stat-card { background: rgba(255, 255, 255, 0.04); border-radius: 16px; padding: 25px; display: flex; align-items: center; gap: 20px;
    border: 1px solid rgba(255, 255, 255, 0.06); transition: all 0.3s ease; }
.stat-card:hover { transform: translateY(-5px); border-color: rgba(18, 170, 255, 0.2); }
.stat-icon { width: 60px; height: 60px; border-radius: 12px; display: flex; align-items: center; justify-content: center;
    font-size: 1.8rem; }
.stat-content h3 { font-size: 1.8rem; color: #fff; margin-bottom: 5px; }
.stat-content p { color: #aaa; font-size: 0.9rem; }

/* ✅ NUEVO: Alert card */
.alert-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 25px;
    border-radius: 16px;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.06);
}

.content-card { background: rgba(255, 255, 255, 0.04); border-radius: 18px; border: 1px solid rgba(255, 255, 255, 0.06); overflow: hidden; }
.card-header { padding: 20px 25px; border-bottom: 1px solid rgba(255, 255, 255, 0.06); display: flex; justify-content: space-between; align-items: center; }
.card-header h3 { font-size: 1.3rem; color: #fff; display: flex; align-items: center; gap: 10px; }
.card-link { color: #12aaff; text-decoration: none; font-size: 0.9rem; display: flex; align-items: center; gap: 5px; }
.card-body { padding: 25px; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th { text-align: left; padding: 12px 0; color: #aaa; font-weight: 500; border-bottom: 1px solid rgba(255, 255, 255, 0.06); }
.data-table td { padding: 15px 0; border-bottom: 1px solid rgba(255, 255, 255, 0.04); }
.data-table tr:last-child td { border-bottom: none; }
.status-badge { padding: 5px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; display: inline-block; }
.status-completada, .status-aprobada { background: rgba(52, 199, 89, 0.2); color: #34c759; }
.status-pendiente { background: rgba(255, 204, 0, 0.2); color: #ffcc00; }
.status-cancelada, .status-rechazada { background: rgba(255, 59, 48, 0.2); color: #ff3b30; }

/* ✅ NUEVO: Colores para vencimientos */
.vencido { color: #ff3b30; font-weight: bold; }
.proximo { color: #ffcc00; font-weight: bold; }

.empty-state { text-align: center; padding: 40px 20px; }
.empty-state i { font-size: 3rem; color: #12aaff; margin-bottom: 15px; }
.empty-state p { color: #aaa; margin-bottom: 20px; }
.btn-primary { display: inline-block; padding: 12px 25px; background: linear-gradient(135deg, #12aaff, #0de0c9);
    color: #0d0f14; text-decoration: none; border-radius: 10px; font-weight: 600; transition: all 0.3s ease; }
.btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(18, 170, 255, 0.3); }
.quick-actions { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
.quick-action { background: rgba(255, 255, 255, 0.04); border-radius: 16px; padding: 25px; text-decoration: none; border: 1px solid rgba(255, 255, 255, 0.06);
    transition: all 0.3s ease; text-align: center; }
.quick-action:hover { transform: translateY(-5px); border-color: rgba(18, 170, 255, 0.2); background: rgba(255, 255, 255, 0.06); }
.action-icon { width: 60px; height: 60px; margin: 0 auto 15px; border-radius: 16px; background: linear-gradient(135deg, #12aaff, #0de0c9);
    display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: #0d0f14; }
.quick-action h4 { color: #fff; margin-bottom: 10px; font-size: 1.1rem; }
.quick-action p { color: #aaa; font-size: 0.9rem; line-height: 1.5; }
@media (max-width: 768px) {
    .welcome-card { flex-direction: column; text-align: center; gap: 20px; }
    .saldo-info { text-align: center; }
    .stats-grid { grid-template-columns: 1fr; }
    .quick-actions { grid-template-columns: 1fr; }
    .alert-card { flex-direction: column; text-align: center; gap: 15px; }
}
</style>

<?php include '../includes/footer.php'; ?>