<?php
if (!function_exists('adminSidebarH')) {
    function adminSidebarH($value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('adminSidebarTableExists')) {
    function adminSidebarTableExists(mysqli $cx, string $table): bool {
        $t = $cx->real_escape_string($table);
        $rs = $cx->query("SHOW TABLES LIKE '$t'");
        return ($rs && $rs->num_rows > 0);
    }
}

if (!function_exists('adminSidebarColExists')) {
    function adminSidebarColExists(mysqli $cx, string $table, string $col): bool {
        $t = $cx->real_escape_string($table);
        $c = $cx->real_escape_string($col);
        $rs = $cx->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
        return ($rs && $rs->num_rows > 0);
    }
}

if (!function_exists('adminSidebarStats')) {
    function adminSidebarStats(mysqli $cx): array {
        $stats = [
            'usuarios_nuevos_hoy' => 0,
            'productos_agotados' => 0,
            'recargas_pendientes' => 0,
            'recuperaciones' => 0,
            'tickets_soporte' => 0,
        ];

        if (adminSidebarTableExists($cx, 'usuarios')) {
            foreach (['created_at', 'fecha_registro', 'fecha_creacion', 'fecha'] as $dateCol) {
                if (adminSidebarColExists($cx, 'usuarios', $dateCol)) {
                    $rs = $cx->query("SELECT COUNT(*) c FROM usuarios WHERE DATE(`$dateCol`)=CURDATE()");
                    if ($rs) $stats['usuarios_nuevos_hoy'] = (int)($rs->fetch_assoc()['c'] ?? 0);
                    break;
                }
            }
        }

        if (adminSidebarTableExists($cx, 'productos') && adminSidebarColExists($cx, 'productos', 'stock')) {
            $whereActivo = adminSidebarColExists($cx, 'productos', 'activo') ? " AND activo=1" : "";
            $rs = $cx->query("SELECT COUNT(*) c FROM productos WHERE stock<=0 $whereActivo");
            if ($rs) $stats['productos_agotados'] = (int)($rs->fetch_assoc()['c'] ?? 0);
        }

        if (adminSidebarTableExists($cx, 'recargas') && adminSidebarColExists($cx, 'recargas', 'estado')) {
            $rs = $cx->query("SELECT COUNT(*) c FROM recargas WHERE estado='pendiente'");
            if ($rs) $stats['recargas_pendientes'] = (int)($rs->fetch_assoc()['c'] ?? 0);
        }

        if (adminSidebarTableExists($cx, 'recuperaciones_pendientes') && adminSidebarColExists($cx, 'recuperaciones_pendientes', 'estado')) {
            $rs = $cx->query("SELECT COUNT(*) c FROM recuperaciones_pendientes WHERE estado='pendiente'");
            if ($rs) $stats['recuperaciones'] = (int)($rs->fetch_assoc()['c'] ?? 0);
        }

        if (adminSidebarTableExists($cx, 'tickets') && adminSidebarColExists($cx, 'tickets', 'estado')) {
            $rs = $cx->query("SELECT COUNT(*) c FROM tickets WHERE estado IN ('abierto','en_proceso','en proceso','pendiente')");
            if ($rs) $stats['tickets_soporte'] = (int)($rs->fetch_assoc()['c'] ?? 0);
        }

        return $stats;
    }
}

if (!function_exists('adminSidebarActive')) {
    function adminSidebarActive(string $file, string $currentPage): string {
        return $file === $currentPage ? 'active' : '';
    }
}

if (!function_exists('renderAdminSidebar')) {
    function renderAdminSidebar(mysqli $cx, ?string $currentPage = null): void {
        $currentPage = $currentPage ?: basename($_SERVER['PHP_SELF']);
        $stats = adminSidebarStats($cx);
        ?>
<button class="sidebar-toggle" id="sidebarToggle"><i class="fas fa-bars"></i></button>

<aside class="admin-sidebar" id="adminSidebar">
    <div class="admin-logo">
        <div class="logo">Monkeystraming</div>
        <div class="subtitle">Panel de Administracion</div>
    </div>

    <div class="admin-sidebar-scroll">
        <nav class="admin-menu">
            <div class="menu-section">
                <h3>Principal</h3>
                <a href="index.php" class="menu-item <?php echo adminSidebarActive('index.php', $currentPage); ?>"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>
                <a href="ventas.php" class="menu-item <?php echo adminSidebarActive('ventas.php', $currentPage); ?>"><i class="fas fa-shopping-cart"></i><span>Ventas</span></a>
                <a href="recargas-admin.php" class="menu-item <?php echo adminSidebarActive('recargas-admin.php', $currentPage); ?>"><i class="fas fa-coins"></i><span>Recargas</span><span class="menu-badge"><?php echo (int)$stats['recargas_pendientes']; ?></span></a>
            </div>

            <div class="menu-section">
                <h3>Gestion</h3>
                <a href="admin_recuperaciones.php" class="menu-item <?php echo adminSidebarActive('admin_recuperaciones.php', $currentPage); ?>"><i class="fab fa-whatsapp"></i><span>Recuperaciones</span><span class="menu-badge"><?php echo (int)$stats['recuperaciones']; ?></span></a>
                <a href="usuarios.php" class="menu-item <?php echo adminSidebarActive('usuarios.php', $currentPage); ?>"><i class="fas fa-users"></i><span>Usuarios</span><span class="menu-badge"><?php echo (int)$stats['usuarios_nuevos_hoy']; ?></span></a>
                <a href="vendedores.php" class="menu-item <?php echo adminSidebarActive('vendedores.php', $currentPage); ?>"><i class="fas fa-user-tie"></i><span>Vendedores</span></a>
                <a href="productos-admin.php" class="menu-item <?php echo adminSidebarActive('productos-admin.php', $currentPage); ?>"><i class="fas fa-box-open"></i><span>Productos</span><span class="menu-badge"><?php echo (int)$stats['productos_agotados']; ?></span></a>
                <a href="stock.php" class="menu-item <?php echo adminSidebarActive('stock.php', $currentPage); ?>"><i class="fas fa-warehouse"></i><span>Stock</span></a>
            </div>

            <div class="menu-section">
                <h3>Soporte</h3>
                <a href="tickets.php" class="menu-item <?php echo adminSidebarActive('tickets.php', $currentPage); ?>"><i class="fas fa-ticket-alt"></i><span>Tickets</span><span class="menu-badge"><?php echo (int)$stats['tickets_soporte']; ?></span></a>
            </div>

            <div class="menu-section">
                <h3>Sistema</h3>
                <a href="configuracion.php" class="menu-item <?php echo adminSidebarActive('configuracion.php', $currentPage); ?>"><i class="fas fa-cog"></i><span>Configuracion</span></a>
            </div>
        </nav>

        <div class="menu-section admin-sidebar-bottom">
            <a href="../index.php" class="menu-item"><i class="fas fa-globe"></i><span>Ver Sitio Web</span></a>
            <a href="../logout.php" class="menu-item"><i class="fas fa-sign-out-alt"></i><span>Cerrar Sesion</span></a>
        </div>
    </div>
</aside>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var sidebar = document.getElementById('adminSidebar');
    var toggle = document.getElementById('sidebarToggle');
    if (sidebar && toggle) {
        toggle.addEventListener('click', function () {
            sidebar.classList.toggle('active');
        });
    }
});
</script>
        <?php
    }
}
