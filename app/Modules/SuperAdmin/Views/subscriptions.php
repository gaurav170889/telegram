<?php include __DIR__ . '/partials/header.php'; ?>
<?php include __DIR__ . '/partials/sidebar.php'; ?>

<main class="main-content">
    <div class="topbar">
        <h1 class="page-title">Manage Contracts & Subscriptions</h1>
        <a href="<?= $basePath ?>/logout" class="logout-btn">
            <i class="fa-solid fa-right-from-bracket"></i> Logout
        </a>
    </div>
    
    <div class="glass-panel" style="padding: 2rem;">
        <h2 style="margin-top: 0; font-weight: 500; font-size: 1.25rem;">Active Contracts</h2>
        <p style="color: var(--text-muted); margin-bottom: 2rem;">Manage the allowed active periods for each bookie.</p>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div style="background: rgba(16, 185, 129, 0.1); color: var(--success); padding: 1rem; border-radius: 0.5rem; border: 1px solid var(--success); margin-bottom: 1.5rem;">
                <?= $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div style="background: rgba(239, 68, 68, 0.1); color: var(--danger); padding: 1rem; border-radius: 0.5rem; border: 1px solid var(--danger); margin-bottom: 1.5rem;">
                <?= $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <th style="padding: 1rem; color: var(--text-muted); font-weight: 500;">ID</th>
                        <th style="padding: 1rem; color: var(--text-muted); font-weight: 500;">Bookie Name</th>
                        <th style="padding: 1rem; color: var(--text-muted); font-weight: 500;">Contract Period</th>
                        <th style="padding: 1rem; color: var(--text-muted); font-weight: 500;">Bot Token</th>
                        <th style="padding: 1rem; color: var(--text-muted); font-weight: 500;">Status</th>
                        <th style="padding: 1rem; color: var(--text-muted); font-weight: 500;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (isset($subscriptionsList) && count($subscriptionsList) > 0): ?>
                        <?php foreach($subscriptionsList as $sub): ?>
                        <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                            <form action="<?= $basePath ?>/admin/subscriptions/update" method="POST">
                                <input type="hidden" name="id" value="<?= $sub['id'] ?>">
                                
                                <td style="padding: 1rem;"><?= $sub['id'] ?></td>
                                <td style="padding: 1rem;">
                                    <strong><?= htmlspecialchars($sub['bookie_name']) ?: '<span style="color:var(--warning)">Pending</span>' ?></strong><br>
                                    <small style="color: var(--text-muted);">@<?= htmlspecialchars($sub['bookie_username']) ?></small>
                                </td>
                                <td style="padding: 1rem;">
                                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                                        <input type="date" name="start_date" value="<?= $sub['start_date'] ?>" required style="padding: 0.5rem; background: rgba(0,0,0,0.2); border: 1px solid var(--border-color); color: white; border-radius: 0.25rem; color-scheme: dark;">
                                        <span style="color: var(--text-muted);">to</span>
                                        <input type="date" name="end_date" value="<?= $sub['end_date'] ?>" required style="padding: 0.5rem; background: rgba(0,0,0,0.2); border: 1px solid var(--border-color); color: white; border-radius: 0.25rem; color-scheme: dark;">
                                    </div>
                                </td>
                                <td style="padding: 1rem; max-width: 120px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-family: monospace; color: var(--primary);">
                                    <?= htmlspecialchars($sub['telegram_bot_token']) ?>
                                </td>
                                <td style="padding: 1rem;">
                                    <?php 
                                        $now = date('Y-m-d');
                                        if ($sub['end_date'] < $now) {
                                            echo '<span style="background: rgba(239, 68, 68, 0.2); color: var(--danger); padding: 0.25rem 0.75rem; border-radius: 1rem; font-size: 0.85rem;">Expired</span>';
                                        } else {
                                            echo '<span style="background: rgba(16, 185, 129, 0.2); color: var(--success); padding: 0.25rem 0.75rem; border-radius: 1rem; font-size: 0.85rem;">Active</span>';
                                        }
                                    ?>
                                </td>
                                <td style="padding: 1rem;">
                                    <button type="submit" style="background: var(--primary); color: white; border: none; padding: 0.5rem 1rem; border-radius: 0.25rem; font-weight: 500; cursor: pointer; transition: background 0.2s;">Save Dates</button>
                                </td>
                            </form>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="padding: 2rem; text-align: center; color: var(--text-muted);">No subscriptions found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
