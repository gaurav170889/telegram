<?php include __DIR__ . '/partials/header.php'; ?>
<?php include __DIR__ . '/partials/sidebar.php'; ?>

<main class="main-content">
    <div class="topbar">
        <h1 class="page-title">SuperAdmin Dashboard</h1>
        <a href="<?= $basePath ?>/logout" class="logout-btn">
            <i class="fa-solid fa-right-from-bracket"></i> Logout
        </a>
    </div>

    <div class="stat-grid">
        <div class="glass-panel stat-card">
            <i class="fa-solid fa-users"></i>
            <div class="stat-title">Total Bookies</div>
            <div class="stat-value">--</div>
        </div>
        <div class="glass-panel stat-card">
            <i class="fa-solid fa-robot"></i>
            <div class="stat-title">Active Bots</div>
            <div class="stat-value">--</div>
        </div>
        <div class="glass-panel stat-card">
            <i class="fa-solid fa-coins"></i>
            <div class="stat-title">Total Revenue</div>
            <div class="stat-value">$0.00</div>
        </div>
        <div class="glass-panel stat-card">
            <i class="fa-solid fa-file-contract"></i>
            <div class="stat-title">Expiring Contracts</div>
            <div class="stat-value">0</div>
        </div>
    </div>

    <div class="glass-panel" style="padding: 2rem;">
        <h2 style="margin-top: 0; font-weight: 500; font-size: 1.25rem;">Recent Activity</h2>
        <p style="color: var(--text-muted);">No recent activity to display.</p>
    </div>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
