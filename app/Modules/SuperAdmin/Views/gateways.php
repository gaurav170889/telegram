<?php include __DIR__ . '/partials/header.php'; ?>
<?php include __DIR__ . '/partials/sidebar.php'; ?>

<main class="main-content">
    <div class="topbar">
        <h1 class="page-title">Manage Global Payment Gateways</h1>
    </div>
    
    <div class="glass-panel" style="padding: 2rem;">
        <h2 style="margin-top: 0; font-weight: 500; font-size: 1.25rem; margin-bottom: 1.5rem;">Add New Payment Gateway</h2>
        
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1.5rem;">
            Define a global payment method (e.g., PayPal, Venmo, CashApp). Bookies will then be able to provide their Username/ID for this method during onboarding.
        </p>

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

        <form action="<?= $basePath ?>/admin/gateways/add" method="POST" style="display: grid; gap: 1.5rem; grid-template-columns: 1fr;">
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-muted);">Gateway Name</label>
                <input type="text" name="name" required placeholder="e.g., Venmo" style="width: 100%; padding: 0.75rem; border-radius: 0.5rem; background: rgba(0,0,0,0.2); border: 1px solid var(--border-color); color: white; box-sizing: border-box;">
            </div>
            <div>
                <button type="submit" style="background: var(--primary); color: white; border: none; padding: 0.75rem 2rem; border-radius: 0.5rem; font-weight: 600; cursor: pointer; transition: background 0.2s;">Create Gateway</button>
            </div>
        </form>
    </div>
    
    <div class="glass-panel" style="padding: 2rem; margin-top: 2rem;">
        <h2 style="margin-top: 0; font-weight: 500; font-size: 1.25rem; margin-bottom: 1.5rem;">Available Global Gateways</h2>
        
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <th style="padding: 1rem; color: var(--text-muted); font-weight: 500;">ID</th>
                        <th style="padding: 1rem; color: var(--text-muted); font-weight: 500;">Gateway Name</th>
                        <th style="padding: 1rem; color: var(--text-muted); font-weight: 500;">Status</th>
                    </tr>
                    </tr>
                </thead>
                <tbody>
                    <?php if (isset($gatewaysList) && count($gatewaysList) > 0): ?>
                        <?php foreach($gatewaysList as $g): ?>
                        <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                        <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                            <td style="padding: 1rem;"><?= $g['id'] ?></td>
                            <td style="padding: 1rem; font-weight: 600;"><?= htmlspecialchars($g['name']) ?></td>
                            <td style="padding: 1rem;">
                                <?php if($g['is_active']): ?>
                                    <span style="background: rgba(16, 185, 129, 0.2); color: var(--success); padding: 0.25rem 0.75rem; border-radius: 1rem; font-size: 0.85rem;">Active</span>
                                <?php else: ?>
                                    <span style="background: rgba(239, 68, 68, 0.2); color: var(--danger); padding: 0.25rem 0.75rem; border-radius: 1rem; font-size: 0.85rem;">Disabled</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="padding: 2rem; text-align: center; color: var(--text-muted);">No gateways defined yet. Create one above to allow bookies to use it.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
