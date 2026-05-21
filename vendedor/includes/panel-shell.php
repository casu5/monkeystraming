<?php
if (!function_exists('sellerPanelH')) {
    function sellerPanelH($value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('sellerPanelStart')) {
    function sellerPanelStart(string $title, string $subtitle, array $seller, string $active = 'dashboard'): void {
        $items = [
            'dashboard' => ['href' => 'dashboard.php', 'icon' => 'fa-chart-line', 'label' => 'Dashboard'],
            'productos' => ['href' => 'productos.php', 'icon' => 'fa-box-open', 'label' => 'Productos'],
            'stock' => ['href' => 'stock.php', 'icon' => 'fa-key', 'label' => 'Stock'],
            'ventas' => ['href' => 'ventas.php', 'icon' => 'fa-receipt', 'label' => 'Ventas'],
        ];
        ?>
<button class="sidebar-toggle" id="sidebarToggle"><i class="fas fa-bars"></i></button>

<aside class="admin-sidebar seller-sidebar" id="adminSidebar">
    <div class="admin-logo">
        <div class="logo">Monkeystraming</div>
        <div class="subtitle">Panel de Vendedor</div>
    </div>

    <nav class="admin-menu">
        <div class="menu-section">
            <h3>Vendedor</h3>
            <?php foreach ($items as $key => $item): ?>
                <a href="<?php echo sellerPanelH($item['href']); ?>" class="menu-item <?php echo $active === $key ? 'active' : ''; ?>">
                    <i class="fas <?php echo sellerPanelH($item['icon']); ?>"></i>
                    <span><?php echo sellerPanelH($item['label']); ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="menu-section seller-menu-bottom">
            <h3>Cuenta</h3>
            <a href="../index.php" class="menu-item">
                <i class="fas fa-store"></i>
                <span>Ver tienda</span>
            </a>
            <a href="../logout.php" class="menu-item">
                <i class="fas fa-sign-out-alt"></i>
                <span>Salir</span>
            </a>
        </div>
    </nav>
</aside>

<main class="admin-main seller-main">
    <header class="admin-header seller-header">
        <div class="header-title">
            <h1><?php echo sellerPanelH($title); ?></h1>
            <p><?php echo sellerPanelH($subtitle); ?></p>
        </div>
        <div class="header-actions">
            <div class="user-menu">
                <div class="user-avatar"><?php echo sellerPanelH(strtoupper(substr((string)($seller['nombre'] ?? 'V'), 0, 1))); ?></div>
                <div class="user-info">
                    <div class="user-name"><?php echo sellerPanelH($seller['nombre'] ?? 'Vendedor'); ?></div>
                    <div class="user-role">Vendedor</div>
                </div>
            </div>
        </div>
    </header>

    <div class="admin-content seller-content">
        <div class="seller-page-wrap">
        <?php
    }
}

if (!function_exists('sellerPanelEnd')) {
    function sellerPanelEnd(): void {
        ?>
        </div>
    </div>
</main>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var toggle = document.getElementById('sidebarToggle');
    var sidebar = document.getElementById('adminSidebar');
    if (toggle && sidebar) {
        toggle.addEventListener('click', function () {
            sidebar.classList.toggle('active');
        });
    }
});
</script>
        <?php
    }
}
