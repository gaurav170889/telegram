<?php include __DIR__ . '/partials/header.php'; ?>
<?php include __DIR__ . '/partials/sidebar.php'; ?>

<main class="main-content">
    <div class="topbar">
        <h1 class="page-title">Manual Payments</h1>
    </div>
    
    <div class="glass-panel" style="padding: 2rem;">
        <h2 style="margin-top: 0; font-weight: 500; font-size: 1.25rem;">Log Received Payment</h2>
        <p style="color: var(--text-muted); margin-bottom: 2rem;">Record a manual payment made by a bookie for using the platform/bot.</p>
        
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

        <form action="<?= $basePath ?>/admin/payments/add" method="POST" style="display: grid; gap: 1.5rem; grid-template-columns: 1fr 1fr;">
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-muted);">Select Bookie</label>
                <select name="bookie_id" required style="width: 100%; padding: 0.75rem; border-radius: 0.5rem; background: rgba(0,0,0,0.2); border: 1px solid var(--border-color); color: white; box-sizing: border-box;">
                    <option value="">-- Choose Bookie --</option>
                    <?php if (isset($bookiesList)): foreach ($bookiesList as $b): ?>
                        <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name'] ?: $b['username']) ?> (@<?= htmlspecialchars($b['username']) ?>)</option>
                    <?php endforeach; endif; ?>
                </select>
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-muted);">Amount Received ($)</label>
                <input type="number" step="0.01" name="amount" required placeholder="e.g. 150.00" style="width: 100%; padding: 0.75rem; border-radius: 0.5rem; background: rgba(0,0,0,0.2); border: 1px solid var(--border-color); color: white; box-sizing: border-box;">
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-muted);">Date of Payment</label>
                <input type="date" name="payment_date" required value="<?= date('Y-m-d') ?>" style="width: 100%; padding: 0.75rem; border-radius: 0.5rem; background: rgba(0,0,0,0.2); border: 1px solid var(--border-color); color: white; box-sizing: border-box; color-scheme: dark;">
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-muted);">Notes / Transaction ID (Optional)</label>
                <input type="text" name="notes" placeholder="e.g. Paid via Crypto TXN..." style="width: 100%; padding: 0.75rem; border-radius: 0.5rem; background: rgba(0,0,0,0.2); border: 1px solid var(--border-color); color: white; box-sizing: border-box;">
            </div>
            <div style="grid-column: span 2;">
                <button type="submit" style="background: var(--success); color: white; border: none; padding: 0.75rem 2rem; border-radius: 0.5rem; font-weight: 600; cursor: pointer; transition: background 0.2s;">Log Payment</button>
            </div>
        </form>
    </div>
    
    <div class="glass-panel" style="padding: 2rem; margin-top: 2rem;">
        <h2 style="margin-top: 0; font-weight: 500; font-size: 1.25rem; margin-bottom: 1.5rem;">Payment History</h2>
        
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <th style="padding: 1rem; color: var(--text-muted); font-weight: 500;">Date</th>
                        <th style="padding: 1rem; color: var(--text-muted); font-weight: 500;">Bookie</th>
                        <th style="padding: 1rem; color: var(--text-muted); font-weight: 500;">Amount</th>
                        <th style="padding: 1rem; color: var(--text-muted); font-weight: 500;">Notes</th>
                        <th style="padding: 1rem; color: var(--text-muted); font-weight: 500;">Recorded On</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (isset($paymentsList) && count($paymentsList) > 0): ?>
                        <?php foreach($paymentsList as $p): ?>
                        <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                            <td style="padding: 1rem; color: var(--primary); font-weight: 500;"><?= date('M j, Y', strtotime($p['payment_date'])) ?></td>
                            <td style="padding: 1rem;">
                                <strong><?= htmlspecialchars($p['bookie_name']) ?: '<span style="color:var(--warning)">Pending</span>' ?></strong><br>
                                <small style="color: var(--text-muted);">@<?= htmlspecialchars($p['bookie_username']) ?></small>
                            </td>
                            <td style="padding: 1rem; font-weight: bold; color: var(--success);">
                                $<?= number_format($p['amount'], 2) ?>
                            </td>
                            <td style="padding: 1rem; color: var(--text-muted); font-size: 0.9rem;">
                                <?= htmlspecialchars($p['notes']) ?: '-' ?>
                            </td>
                            <td style="padding: 1rem; color: var(--text-muted); font-size: 0.85rem;">
                                <?= date('Y-m-d H:i', strtotime($p['created_at'])) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="padding: 2rem; text-align: center; color: var(--text-muted);">No manual payments logged yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
