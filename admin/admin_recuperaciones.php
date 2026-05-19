<?php
// Ajustar ruta según la ubicación de tu admin_recuperaciones.php
require_once '../config/database.php'; // Agregar ../ para subir un nivel

// Iniciar sesión solo si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// DEBUG: Para ver qué hay en la sesión (opcional, quitar después)
// echo "<pre>SESSION: "; print_r($_SESSION); echo "</pre>";

// Verificar que esté logueado (forma simple primero)
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Opcional: Verificar si es admin en BD (si tu sistema tiene rol)
/*
$user_id = $_SESSION['user_id'];
$sql_admin = "SELECT * FROM usuarios WHERE id = ?";
$stmt_admin = $conexion->prepare($sql_admin);
$stmt_admin->bind_param("i", $user_id);
$stmt_admin->execute();
$result_admin = $stmt_admin->get_result();
$usuario = $result_admin->fetch_assoc();
$stmt_admin->close();

// Si tu sistema tiene campo 'rol', 'tipo', o similar
if (!$usuario || ($usuario['rol'] !== 'admin' && $usuario['tipo_usuario'] !== 'admin')) {
    header('Location: ../dashboard.php');
    exit;
}
*/

$page_title = "Solicitudes de Recuperación";
$mensaje = '';

// Verificar si existe la tabla, si no, crearla
$sql_check = "SHOW TABLES LIKE 'recuperaciones_pendientes'";
$check_result = $conexion->query($sql_check);
if ($check_result->num_rows == 0) {
    // Crear tabla
    $sql_create = "CREATE TABLE recuperaciones_pendientes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        whatsapp VARCHAR(20) NOT NULL,
        nombre_usuario VARCHAR(100),
        token VARCHAR(64) UNIQUE,
        enlace TEXT,
        estado ENUM('pendiente', 'enviado') DEFAULT 'pendiente',
        fecha_solicitud DATETIME DEFAULT CURRENT_TIMESTAMP,
        fecha_envio DATETIME NULL
    )";
    $conexion->query($sql_create);
}

// Enviar enlace al usuario
if (isset($_GET['enviar'])) {
    $id = intval($_GET['enviar']);
    
    // Obtener datos de la solicitud
    $sql = "SELECT * FROM recuperaciones_pendientes WHERE id = ? AND estado = 'pendiente'";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $solicitud = $result->fetch_assoc();
    $stmt->close();
    
    if ($solicitud) {
        // Crear mensaje para enviar al usuario
        $mensaje_usuario = "Hola " . $solicitud['nombre_usuario'] . ", aquí está tu enlace de recuperación: " . $solicitud['enlace'];
        
        // Crear URL de WhatsApp
        $whatsapp_url = "https://wa.me/" . $solicitud['whatsapp'] . "?text=" . urlencode($mensaje_usuario);
        
        // Actualizar estado
        $sql_update = "UPDATE recuperaciones_pendientes SET estado = 'enviado', fecha_envio = NOW() WHERE id = ?";
        $stmt_update = $conexion->prepare($sql_update);
        $stmt_update->bind_param("i", $id);
        $stmt_update->execute();
        $stmt_update->close();
        
        // Redirigir a WhatsApp
        header("Location: $whatsapp_url");
        exit;
    } else {
        $mensaje = '❌ La solicitud no existe o ya fue enviada';
    }
}

// Eliminar solicitud
if (isset($_GET['eliminar'])) {
    $id = intval($_GET['eliminar']);
    $sql = "DELETE FROM recuperaciones_pendientes WHERE id = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $mensaje = '✅ Solicitud eliminada';
    } else {
        $mensaje = '❌ Error al eliminar la solicitud';
    }
    $stmt->close();
}

// Obtener solicitudes pendientes
$sql = "SELECT * FROM recuperaciones_pendientes WHERE estado = 'pendiente' ORDER BY fecha_solicitud DESC";
$result = $conexion->query($sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background: #0d0f14; color: #e5e5e5; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        header { 
            background: rgba(255,255,255,0.05); padding: 20px; border-radius: 15px;
            margin-bottom: 30px; border: 1px solid rgba(255,255,255,0.1);
        }
        .header-top { display: flex; justify-content: space-between; align-items: center; }
        h1 { 
            background: linear-gradient(135deg, #25D366, #128C7E);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            font-size: 1.8rem;
        }
        table {
            width: 100%; background: rgba(255,255,255,0.05);
            border-radius: 12px; overflow: hidden; border: 1px solid rgba(255,255,255,0.1);
        }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.1); }
        th { background: rgba(37, 211, 102, 0.1); color: #25D366; font-weight: 600; }
        tr:hover { background: rgba(255,255,255,0.02); }
        .badge { padding: 5px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
        .badge-pendiente { background: rgba(255, 193, 7, 0.2); color: #ffc107; }
        .btn {
            padding: 8px 15px; border-radius: 8px; border: none; font-size: 0.9rem;
            font-weight: 600; cursor: pointer; display: inline-flex; align-items: center;
            gap: 6px; transition: all 0.3s ease; text-decoration: none;
        }
        .btn-whatsapp { background: #25D366; color: white; }
        .btn-whatsapp:hover { background: #128C7E; transform: translateY(-2px); }
        .btn-danger { background: #ff3b30; color: white; }
        .btn-danger:hover { background: #d70015; }
        .btn-volver { background: rgba(255,255,255,0.1); color: white; }
        .btn-volver:hover { background: rgba(255,255,255,0.2); }
        .alert { 
            padding: 15px; border-radius: 10px; margin-bottom: 20px; 
            display: flex; align-items: center; gap: 10px;
        }
        .alert-success { background: rgba(52, 199, 89, 0.1); color: #34c759; border: 1px solid rgba(52, 199, 89, 0.2); }
        .alert-error { background: rgba(255, 59, 48, 0.1); color: #ff3b30; border: 1px solid rgba(255, 59, 48, 0.2); }
        .empty-state { text-align: center; padding: 50px; color: #777; }
        .empty-state i { font-size: 3rem; margin-bottom: 15px; color: #444; }
        .tiempo { font-size: 0.85rem; color: #777; }
        .stats { 
            display: grid; grid-template-columns: repeat(3, 1fr); 
            gap: 15px; margin: 20px 0;
        }
        .stat-card {
            background: rgba(255,255,255,0.05); padding: 15px;
            border-radius: 10px; text-align: center;
        }
        .stat-card .number { 
            font-size: 1.8rem; font-weight: 800; color: #25D366;
        }
        .stat-card .label { 
            font-size: 0.85rem; color: #aaa; margin-top: 5px;
        }
        .mensaje-preview {
            background: rgba(37, 211, 102, 0.1); padding: 10px; border-radius: 8px;
            margin: 5px 0; font-size: 0.85rem; color: #25D366;
            border-left: 3px solid #25D366;
        }
        @media (max-width: 768px) {
            .stats { grid-template-columns: 1fr; }
            table { display: block; overflow-x: auto; }
            th, td { padding: 10px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            
            <div class="header-top">
                <h1><i class="fab fa-whatsapp"></i> Solicitudes de Recuperación</h1>
                <a href="index.php" class="btn btn-volver">
                    <i class="fas fa-arrow-left"></i> Volver al Dashboard
                </a>
            </div>
            
            <div class="stats">
                <?php
                // Obtener estadísticas
                $total = $conexion->query("SELECT COUNT(*) as total FROM recuperaciones_pendientes")->fetch_assoc();
                $pendientes = $conexion->query("SELECT COUNT(*) as pendientes FROM recuperaciones_pendientes WHERE estado = 'pendiente'")->fetch_assoc();
                $hoy = $conexion->query("SELECT COUNT(*) as hoy FROM recuperaciones_pendientes WHERE DATE(fecha_solicitud) = CURDATE()")->fetch_assoc();
                ?>
                <div class="stat-card">
                    <div class="number"><?php echo $total['total']; ?></div>
                    <div class="label">Total Solicitudes</div>
                </div>
                <div class="stat-card">
                    <div class="number"><?php echo $pendientes['pendientes']; ?></div>
                    <div class="label">Pendientes</div>
                </div>
                <div class="stat-card">
                    <div class="number"><?php echo $hoy['hoy']; ?></div>
                    <div class="label">Hoy</div>
                </div>
            </div>
        </header>
        
        <?php if ($mensaje): ?>
            <div class="alert <?php echo strpos($mensaje, '❌') !== false ? 'alert-error' : 'alert-success'; ?>">
                <i class="fas fa-<?php echo strpos($mensaje, '❌') !== false ? 'exclamation-circle' : 'check-circle'; ?>"></i>
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($result->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Usuario</th>
                    <th>WhatsApp</th>
                    <th>Solicitud</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($solicitud = $result->fetch_assoc()): 
                    $time_ago = time() - strtotime($solicitud['fecha_solicitud']);
                    $minutes = floor($time_ago / 60);
                    $hours = floor($minutes / 60);
                    $time_text = '';
                    
                    if ($hours > 0) {
                        $time_text = "Hace $hours hora" . ($hours > 1 ? 's' : '');
                    } else {
                        $time_text = "Hace $minutes minuto" . ($minutes > 1 ? 's' : '');
                    }
                ?>
                <tr>
                    <td>#<?php echo $solicitud['id']; ?></td>
                    <td>
                        <strong><?php echo htmlspecialchars($solicitud['nombre_usuario']); ?></strong>
                    </td>
                    <td>
                        <?php echo $solicitud['whatsapp']; ?>
                    </td>
                    <td>
                        <span class="tiempo">
                            <i class="far fa-clock"></i> <?php echo $time_text; ?>
                        </span>
                        <div class="mensaje-preview">
                            <i class="fas fa-link"></i> 
                            <a href="<?php echo $solicitud['enlace']; ?>" target="_blank" style="color: #25D366;">
                                Ver enlace
                            </a>
                        </div>
                    </td>
                    <td>
                        <span class="badge badge-pendiente">
                            <i class="fas fa-clock"></i> PENDIENTE
                        </span>
                    </td>
                    <td>
                        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                            <a href="?enviar=<?php echo $solicitud['id']; ?>" 
                               class="btn btn-whatsapp"
                               title="Enviar enlace por WhatsApp">
                                <i class="fab fa-whatsapp"></i> Enviar Enlace
                            </a>
                            <a href="?eliminar=<?php echo $solicitud['id']; ?>" 
                               class="btn btn-danger"
                               onclick="return confirm('¿Eliminar esta solicitud?')"
                               title="Eliminar solicitud">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <h3>No hay solicitudes pendientes</h3>
            <p>Todas las solicitudes han sido atendidas.</p>
            <p style="margin-top: 15px; font-size: 0.9rem; color: #aaa;">
                <i class="fas fa-info-circle"></i> 
                Cuando un usuario solicite recuperación, aparecerá aquí.
            </p>
        </div>
        <?php endif; ?>
        
        <?php
        // Mostrar también solicitudes ya enviadas (opcional)
        $sql_enviados = "SELECT * FROM recuperaciones_pendientes WHERE estado = 'enviado' ORDER BY fecha_envio DESC LIMIT 5";
        $result_enviados = $conexion->query($sql_enviados);
        if ($result_enviados->num_rows > 0): ?>
        <div style="margin-top: 40px;">
            <h3 style="color: #25D366; margin-bottom: 15px;">
                <i class="fas fa-history"></i> Solicitudes Recientes Enviadas
            </h3>
            <table>
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>WhatsApp</th>
                        <th>Enviado</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($enviado = $result_enviados->fetch_assoc()): 
                        $time_envio = time() - strtotime($enviado['fecha_envio']);
                        $minutos_envio = floor($time_envio / 60);
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($enviado['nombre_usuario']); ?></td>
                        <td><?php echo $enviado['whatsapp']; ?></td>
                        <td>
                            <span class="tiempo">
                                <i class="far fa-clock"></i> 
                                Hace <?php echo $minutos_envio; ?> minuto<?php echo $minutos_envio > 1 ? 's' : ''; ?>
                            </span>
                        </td>
                        <td>
                            <span style="background: rgba(52, 199, 89, 0.2); color: #34c759; padding: 5px 12px; border-radius: 20px; font-size: 0.8rem;">
                                <i class="fas fa-check-circle"></i> ENVIADO
                            </span>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
    // Auto-refrescar cada 60 segundos
    setTimeout(() => {
        location.reload();
    }, 60000);
    
    // Confirmar antes de enviar
    document.addEventListener('DOMContentLoaded', function() {
        const linksEnviar = document.querySelectorAll('a[href*="enviar="]');
        linksEnviar.forEach(link => {
            link.addEventListener('click', function(e) {
                const row = this.closest('tr');
                const usuario = row.querySelector('strong').textContent;
                const whatsapp = row.querySelector('td:nth-child(3)').textContent.trim();
                
                if (!confirm(`¿Enviar enlace de recuperación a:\n${usuario}\n${whatsapp}?`)) {
                    e.preventDefault();
                }
            });
        });
        
        // Abrir enlaces en nueva pestaña
        const enlacesVer = document.querySelectorAll('a[href*="http"]');
        enlacesVer.forEach(enlace => {
            if (enlace.getAttribute('href').includes('?')) {
                enlace.addEventListener('click', function(e) {
                    e.preventDefault();
                    window.open(this.getAttribute('href'), '_blank');
                });
            }
        });
    });
    </script>
</body>
</html>