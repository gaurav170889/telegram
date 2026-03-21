<?php
$currentPath = $_SERVER['REQUEST_URI'];
$base = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$basePath = ($base === '/' || $base === '.') ? '' : $base;

// Highlight active menu item
function isActive($path) {
    global $currentPath, $basePath;
    $fullPath = $basePath . $path;
    if ($path === '/admin') {
        return $currentPath === $fullPath || $currentPath === $fullPath . '/' ? 'active' : '';
    }
    return strpos($currentPath, $fullPath) === 0 ? 'active' : '';
}
?>

<style>
    .sidebar {
        width: var(--sidebar-width);
        background: var(--bg-sidebar);
        position: fixed;
        top: 0;
        left: 0;
        height: 100vh;
        border-right: 1px solid var(--border-color);
        display: flex;
        flex-direction: column;
        z-index: 100;
        box-shadow: 4px 0 24px rgba(0,0,0,0.2);
    }

    .sidebar-brand {
        padding: 2rem 1.5rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        border-bottom: 1px solid rgba(255,255,255,0.05);
    }

    .brand-icon {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        color: white;
        box-shadow: 0 4px 12px rgba(139, 92, 246, 0.4);
    }

    .brand-text {
        font-weight: 700;
        font-size: 1.25rem;
        letter-spacing: -0.025em;
    }

    .brand-text span {
        font-weight: 300;
        color: var(--text-muted);
    }

    .sidebar-menu {
        padding: 2rem 1rem;
        flex-grow: 1;
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .menu-label {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        color: var(--text-muted);
        margin: 1rem 0 0.5rem 1rem;
        font-weight: 600;
    }

    .menu-item {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 0.85rem 1.25rem;
        color: var(--text-muted);
        text-decoration: none;
        border-radius: 0.75rem;
        font-weight: 500;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
    }

    .menu-item i {
        font-size: 1.25rem;
        width: 24px;
        text-align: center;
        transition: transform 0.3s ease;
    }

    .menu-item:hover {
        color: white;
        background: rgba(255, 255, 255, 0.05);
    }

    .menu-item:hover i {
        transform: scale(1.1);
        color: var(--primary);
    }

    .menu-item.active {
        color: white;
        background: linear-gradient(90deg, rgba(139, 92, 246, 0.15) 0%, transparent 100%);
        border-left: 3px solid var(--primary);
    }

    .menu-item.active i {
        color: var(--primary);
    }

    .sidebar-footer {
        padding: 1.5rem;
        border-top: 1px solid rgba(255,255,255,0.05);
        font-size: 0.8rem;
        color: var(--text-muted);
        text-align: center;
    }
</style>

<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">
            <i class="fa-solid fa-bolt"></i>
        </div>
        <div class="brand-text">Super<span>Admin</span></div>
    </div>

    <nav class="sidebar-menu">
        <div class="menu-label">Overview</div>
        <a href="<?= $basePath ?>/admin" class="menu-item <?= isActive('/admin') ?>">
            <i class="fa-solid fa-chart-pie"></i>
            <span>Dashboard</span>
        </a>

        <div class="menu-label">Management</div>
        <a href="<?= $basePath ?>/admin/bookies" class="menu-item <?= isActive('/admin/bookies') ?>">
            <i class="fa-solid fa-users-gear"></i>
            <span>Bookies & Bots</span>
        </a>
        <a href="<?= $basePath ?>/admin/gateways" class="menu-item <?= isActive('/admin/gateways') ?>">
            <i class="fa-brands fa-cc-stripe"></i>
            <span>Payment Gateways</span>
        </a>
        <a href="<?= $basePath ?>/admin/subscriptions" class="menu-item <?= isActive('/admin/subscriptions') ?>">
            <i class="fa-solid fa-file-contract"></i>
            <span>Contracts</span>
        </a>
            <i class="fa-solid fa-file-contract"></i>
            <span>Contracts</span>
        </a>

        <div class="menu-label">Finance</div>
        <a href="<?= $basePath ?>/admin/transactions" class="menu-item <?= isActive('/admin/transactions') ?>">
            <i class="fa-solid fa-server"></i>
            <span>Global Queue / Ledger</span>
        </a>
        <a href="<?= $basePath ?>/admin/payments" class="menu-item <?= isActive('/admin/payments') ?>">
            <i class="fa-solid fa-money-bill-transfer"></i>
            <span>Manual Payments</span>
        </a>
        <a href="<?= $basePath ?>/admin/revenue" class="menu-item <?= isActive('/admin/revenue') ?>">
            <i class="fa-solid fa-chart-line"></i>
            <span>Revenue Report</span>
        </a>
    </nav>
    
    <div class="sidebar-footer">
        Telegram Wallet v1.0
    </div>
</aside>
