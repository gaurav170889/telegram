<?php include __DIR__ . '/partials/header.php'; ?>
<?php include __DIR__ . '/partials/sidebar.php'; ?>

<main class="main-content">
    <div class="topbar">
        <h1 class="page-title">Global Transactions & Queue</h1>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
        
        <!-- Global Queue View -->
        <div class="glass-panel" style="padding: 2rem;">
            <h2 style="margin-top: 0; font-weight: 500; font-size: 1.25rem; margin-bottom: 1.5rem;">Global Withdrawal Queue</h2>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">All pending and partially paid withdrawals across all bookies. This engine runs automatically when deposits are approved.</p>
            
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; text-align: left;">
                    <thead>
                        <tr style="border-bottom: 1px solid rgba(255,255,255,0.1);">
                            <th style="padding: 1rem; color: var(--text-muted);">Bookie</th>
                            <th style="padding: 1rem; color: var(--text-muted);">Player</th>
                            <th style="padding: 1rem; color: var(--text-muted);">Method</th>
                            <th style="padding: 1rem; color: var(--text-muted);">Status</th>
                            <th style="padding: 1rem; color: var(--text-muted);">Paid / Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($globalQueue)): ?>
                            <tr><td colspan="5" style="padding: 1rem; text-align: center;">No queued withdrawals.</td></tr>
                        <?php else: ?>
                            <?php foreach ($globalQueue as $q): ?>
                                <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                    <td style="padding: 1rem;"><strong><?= htmlspecialchars($q['bookie_name']) ?></strong></td>
                                    <td style="padding: 1rem;">
                                        <?= htmlspecialchars($q['first_name']) ?><br>
                                        <small style="color: var(--text-muted);">@<?= htmlspecialchars($q['username']) ?></small>
                                    </td>
                                    <td style="padding: 1rem;">
                                        <?= htmlspecialchars($q['method_name']) ?><br>
                                        <small style="color: var(--text-muted);"><?= htmlspecialchars($q['handle']) ?></small>
                                    </td>
                                    <td style="padding: 1rem;">
                                        <?php if ($q['status'] === 'partially_paid'): ?>
                                            <span style="background: rgba(56, 187, 248, 0.2); color: #38bdf8; padding: 0.25rem 0.75rem; border-radius: 1rem; font-size: 0.75rem;">Partially Paid</span>
                                        <?php else: ?>
                                            <span style="background: rgba(245, 158, 11, 0.2); color: #f59e0b; padding: 0.25rem 0.75rem; border-radius: 1rem; font-size: 0.75rem;">Queued</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 1rem; font-weight: 600;">
                                        $<?= number_format($q['paid_amount'] ?: 0, 2) ?> / $<?= number_format($q['amount'], 2) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Global Ledger View -->
        <div class="glass-panel" style="padding: 2rem;">
            <h2 style="margin-top: 0; font-weight: 500; font-size: 1.25rem; margin-bottom: 1.5rem;">Global Master Ledger</h2>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">The immutable double-entry ledger tracking all wallet balances strictly.</p>

            <div style="overflow-x: auto; max-height: 600px; overflow-y: auto;">
                <table style="width: 100%; border-collapse: collapse; text-align: left;">
                    <thead>
                        <tr style="border-bottom: 1px solid rgba(255,255,255,0.1);">
                            <th style="padding: 1rem; color: var(--text-muted); position: sticky; top: 0; background: var(--bg-panel);">Time</th>
                            <th style="padding: 1rem; color: var(--text-muted); position: sticky; top: 0; background: var(--bg-panel);">Context</th>
                            <th style="padding: 1rem; color: var(--text-muted); position: sticky; top: 0; background: var(--bg-panel);">Action</th>
                            <th style="padding: 1rem; color: var(--text-muted); position: sticky; top: 0; background: var(--bg-panel);">Movement</th>
                            <th style="padding: 1rem; color: var(--text-muted); position: sticky; top: 0; background: var(--bg-panel);">Bal Before -> After</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($globalLedger)): ?>
                            <tr><td colspan="5" style="padding: 1rem; text-align: center;">No ledger entries.</td></tr>
                        <?php else: ?>
                            <?php foreach ($globalLedger as $entry): ?>
                                <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                    <td style="padding: 0.5rem 1rem; font-size: 0.85rem; color: var(--text-muted);">
                                        <?= htmlspecialchars(date('m/d H:i', strtotime($entry['created_at']))) ?>
                                    </td>
                                    <td style="padding: 0.5rem 1rem;">
                                        <strong><?= htmlspecialchars($entry['bookie_name']) ?></strong><br>
                                        <small><?= htmlspecialchars($entry['first_name']) ?> (@<?= htmlspecialchars($entry['username']) ?>)</small>
                                    </td>
                                    <td style="padding: 0.5rem 1rem; font-size: 0.85rem;">
                                        <strong style="color: white;"><?= ucwords(str_replace('_', ' ', $entry['entry_type'])) ?></strong><br>
                                        <span style="color: var(--text-muted);">Ref: <?= htmlspecialchars($entry['reference_type']) ?> #<?= htmlspecialchars($entry['reference_id']) ?></span>
                                    </td>
                                    <td style="padding: 0.5rem 1rem; font-weight: 600;">
                                        <?php if ($entry['direction'] === 'credit'): ?>
                                            <span style="color: var(--success);">+$<?= number_format($entry['amount'], 2) ?></span>
                                        <?php else: ?>
                                            <span style="color: var(--danger);">-$<?= number_format($entry['amount'], 2) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 0.5rem 1rem; font-size: 0.9rem;">
                                        $<?= number_format($entry['opening_balance'], 2) ?> &rarr; 
                                        <strong>$<?= number_format($entry['closing_balance'], 2) ?></strong>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
