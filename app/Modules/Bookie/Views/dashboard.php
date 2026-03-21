<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?= htmlspecialchars($bookie->name); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <style>
        :root {
            --primary: #6366f1;
            --primary-hover: #4f46e5;
            --bg-dark: #0f172a;
            --bg-card: rgba(30, 41, 59, 0.7);
            --bg-sidebar: #1e293b;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --border-color: rgba(255, 255, 255, 0.1);
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --sidebar-width: 260px;
        }

        body {
            margin: 0;
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg-dark);
            color: var(--text-main);
            display: flex;
            min-height: 100vh;
        }

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
        }

        .sidebar-brand {
            padding: 2rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            border-bottom: 1px solid var(--border-color);
            font-weight: 700;
            font-size: 1.25rem;
        }

        .sidebar-menu { padding: 2rem 1rem; flex-grow: 1; display: flex; flex-direction: column; gap: 0.5rem; }
        .menu-label { font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); margin: 1rem 0 0.5rem 1rem; font-weight: 600; }
        .menu-item { display: flex; align-items: center; gap: 1rem; padding: 0.85rem 1.25rem; color: var(--text-muted); text-decoration: none; border-radius: 0.75rem; font-weight: 500; transition: 0.3s; }
        .menu-item:hover, .menu-item.active { color: white; background: rgba(255,255,255,0.05); }
        .menu-item.active { border-left: 3px solid var(--primary); }

        .main-content {
            margin-left: var(--sidebar-width);
            flex-grow: 1;
            padding: 2rem 3rem;
            width: calc(100% - var(--sidebar-width));
            box-sizing: border-box;
        }

        .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; border-bottom: 1px solid var(--border-color); padding-bottom: 1.5rem; }
        h1.page-title { margin: 0; font-size: 2rem; background: linear-gradient(to right, #a78bfa, #f472b6); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }

        .card { background: var(--bg-card); padding: 1.5rem; border-radius: 1rem; margin-bottom: 2rem; border: 1px solid var(--border-color); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { padding: 1rem; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05); }
        th { color: var(--text-muted); font-weight: 500; }
        
        .btn { padding: 0.5rem 1rem; border: none; border-radius: 0.5rem; cursor: pointer; font-size: 0.85rem; font-weight: 600; color: white; display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; }
        .btn-success { background: var(--success); }
        .btn-danger { background: var(--danger); }
        .btn-warning { background: var(--warning); color: black; }
        
        .badge { padding: 0.25rem 0.75rem; border-radius: 1rem; font-size: 0.75rem; font-weight: 600; }
        .badge-warning { background: rgba(245, 158, 11, 0.2); color: var(--warning); }
        .badge-info { background: rgba(56, 187, 248, 0.2); color: #38bdf8; }
        .badge-success { background: rgba(16, 185, 129, 0.2); color: var(--success); }
        .badge-danger { background: rgba(239, 68, 68, 0.2); color: var(--danger); }
        
        .split-item { font-size: 0.75rem; padding: 0.5rem; background: rgba(255,255,255,0.03); border-radius: 0.5rem; margin-top: 0.5rem; display: flex; justify-content: space-between; align-items: center; }
        .progress-container { width: 100%; height: 6px; background: rgba(255,255,255,0.1); border-radius: 3px; margin-top: 0.5rem; overflow: hidden; }
        .progress-bar { height: 100%; background: var(--primary); transition: 0.3s; }
        .progress-bar-submitted { background: var(--warning); }
        
        /* DataTables Dark Theme Overrides */
        .dataTables_wrapper { color: var(--text-main); font-family: 'Outfit', sans-serif; }
        .dataTables_length select, .dataTables_filter input { 
            background: #1e293b; border: 1px solid var(--border-color); color: white; border-radius: 0.5rem; padding: 0.35rem 0.75rem; 
        }
        .dataTables_info { color: var(--text-muted) !important; font-size: 0.85rem; padding-top: 1rem !important; }
        .dataTables_paginate { padding-top: 1rem !important; }
        .dataTables_paginate .paginate_button { color: var(--text-muted) !important; border-radius: 0.5rem !important; border: 1px solid var(--border-color) !important; background: rgba(255,255,255,0.05) !important; }
        .dataTables_paginate .paginate_button.current { background: var(--primary) !important; color: white !important; border: 1px solid var(--primary) !important; }
        .dataTables_paginate .paginate_button:hover { background: var(--primary-hover) !important; color: white !important; }
        table.dataTable.no-footer { border-bottom: 1px solid rgba(255,255,255,0.05) !important; }
        table.dataTable thead th { border-bottom: 1px solid var(--border-color) !important; color: var(--text-muted); font-weight: 500; }
        .dataTables_filter { margin-bottom: 1rem; }
    </style>
</head>
<body>
    <?php $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/'); ?>

    <aside class="sidebar">
        <div class="sidebar-brand">
            <i class="fa-solid fa-gamepad" style="color: var(--primary);"></i>
            <?= htmlspecialchars($bookie->name); ?>
        </div>
        <nav class="sidebar-menu">
            <div class="menu-label">Main</div>
            <a href="<?= $basePath ?>/dashboard" class="menu-item active"><i class="fa-solid fa-chart-pie"></i> Dashboard</a>
            <div class="menu-label">Analytics</div>
            <a href="<?= $basePath ?>/bookie/reports" class="menu-item"><i class="fa-solid fa-file-invoice-dollar"></i> Reports</a>
            <a href="#" class="menu-item"><i class="fa-solid fa-users"></i> My Players</a>
            <div class="menu-label">Settings</div>
            <a href="<?= $basePath ?>/logout" class="menu-item" style="color: var(--danger);"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <div class="topbar">
            <h1 class="page-title">Operational Dashboard</h1>
            <div style="color: var(--text-muted);">Bot ID: @<?= htmlspecialchars($bookie->telegramBotUsername) ?></div>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div style="background: rgba(16, 185, 129, 0.1); color: var(--success); padding: 1rem; border-radius: 0.5rem; border: 1px solid var(--success); margin-bottom: 1.5rem;">Action completed successfully.</div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div style="background: rgba(239, 68, 68, 0.1); color: var(--danger); padding: 1rem; border-radius: 0.5rem; border: 1px solid var(--danger); margin-bottom: 1.5rem;">
                <?= htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
            <!-- Pending Deposits -->
            <div class="card">
                <h2 style="margin-top: 0;">Pending Deposits</h2>
                <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">Action required: Review receipts to approve or reject.</p>
                <?php if (empty($pendingDeposits)): ?>
                    <p style="color: var(--text-muted);">No pending deposits.</p>
                <?php else: ?>
                    <table>
                        <tr>
                            <th>Player</th>
                            <th>Details</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                        <?php foreach($pendingDeposits as $dep): ?>
                        <tr>
                            <td>
                                <?= htmlspecialchars($dep['first_name']) ?><br>
                                <small style="color: var(--text-muted);">@<?= htmlspecialchars($dep['username']) ?></small>
                                <div style="margin-top: 5px;">
                                    <span class="badge <?= $dep['deposit_type'] === 'split_p2p' ? 'badge-info' : 'badge-warning' ?>">
                                        <?= $dep['deposit_type'] === 'split_p2p' ? 'P2P Split' : 'Direct' ?>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <?php 
                                    $disputedAmt = 0;
                                    if (!empty($dep['splits'])) {
                                        foreach($dep['splits'] as $split) {
                                            if ($split['status'] === 'disputed') $disputedAmt += $split['amount'];
                                        }
                                    }
                                ?>
                                <div style="font-weight: 600; color: var(--success);">+$<?= number_format($dep['amount'], 2) ?></div>
                                <?php if ($disputedAmt > 0): ?>
                                    <div style="font-size: 0.75rem; color: var(--danger); font-weight: 700; margin-top: 2px;">
                                        <i class="fa-solid fa-triangle-exclamation"></i> Disputed: $<?= number_format($disputedAmt, 2) ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($dep['splits'])): ?>
                                    <div style="margin-top: 10px;">
                                        <?php foreach ($dep['splits'] as $split): ?>
                                            <div class="split-item">
                                                <span>To: <?= htmlspecialchars($split['recipient_name'] ?: 'Bookie') ?> ($<?= number_format($split['amount'], 2) ?>)</span>
                                                <span>
                                                    <?php if ($split['status'] === 'receipt_uploaded'): ?>
                                                        <a href="<?= $basePath ?>/bookie/receipt?id=<?= $dep['id'] ?>&split=<?= $split['id'] ?>" target="_blank" title="View Split Receipt"><i class="fa-solid fa-image" style="color: var(--warning);"></i></a>
                                                    <?php elseif ($split['status'] === 'accepted' || $split['status'] === 'accepted_no_dispute' || $split['status'] === 'resolved_confirmed'): ?>
                                                        <i class="fa-solid fa-check-double" style="color: var(--success);"></i>
                                                    <?php elseif ($split['status'] === 'disputed'): ?>
                                                        <i class="fa-solid fa-triangle-exclamation" style="color: var(--danger);"></i>
                                                        <div style="display: flex; gap: 4px; margin-top: 4px;">
                                                            <form action="<?= $basePath ?>/bookie/dispute/resolve" method="POST" onsubmit="return confirm('Confirm this payment as valid?')">
                                                                <input type="hidden" name="split_id" value="<?= $split['id'] ?>">
                                                                <input type="hidden" name="action" value="confirm">
                                                                <button type="submit" class="btn btn-success" style="padding: 2px 6px; font-size: 0.65rem;" title="Confirm Receipt"><i class="fa-solid fa-check"></i></button>
                                                            </form>
                                                            <form action="<?= $basePath ?>/bookie/dispute/resolve" method="POST" onsubmit="return confirm('Reject this payment as invalid?')">
                                                                <input type="hidden" name="split_id" value="<?= $split['id'] ?>">
                                                                <input type="hidden" name="action" value="reject">
                                                                <button type="submit" class="btn btn-danger" style="padding: 2px 6px; font-size: 0.65rem;" title="Reject Receipt"><i class="fa-solid fa-x"></i></button>
                                                            </form>
                                                        </div>
                                                    <?php else: ?>
                                                        <i class="fa-solid fa-clock" style="color: var(--text-muted);"></i>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                    $statusClass = 'badge-warning';
                                    $statusText = str_replace('_', ' ', $dep['status']);
                                    if ($dep['status'] === 'receipts_submitted' || $dep['status'] === 'under_review_window') $statusClass = 'badge-info';
                                    if ($dep['status'] === 'disputed' || $dep['status'] === 'partially_disputed') $statusClass = 'badge-danger';
                                    if ($dep['status'] === 'approved') $statusClass = 'badge-success';
                                ?>
                                <span class="badge <?= $statusClass ?>"><?= ucwords($statusText) ?></span>
                            </td>
                            <td>
                                <?php if ($dep['status'] === 'pending' || $dep['status'] === 'receipts_submitted' || $dep['status'] === 'under_review_window'): ?>
                                    <div style="display: flex; gap: 0.5rem;">
                                        <form action="<?= $basePath ?>/bookie/deposit/approve" method="POST">
                                            <input type="hidden" name="id" value="<?= $dep['id'] ?>">
                                            <button type="submit" class="btn btn-success" title="Approve All"><i class="fa-solid fa-check"></i></button>
                                        </form>
                                        <form action="<?= $basePath ?>/bookie/deposit/reject" method="POST">
                                            <input type="hidden" name="id" value="<?= $dep['id'] ?>">
                                            <button type="submit" class="btn btn-danger" title="Reject"><i class="fa-solid fa-xmark"></i></button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Withdrawal Queue -->
            <div class="card">
                <h2 style="margin-top: 0;">Withdrawal Queue</h2>
                <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">Auto-funded by approved deposits.</p>
                <?php if (empty($queuedWithdrawals)): ?>
                    <p style="color: var(--text-muted);">Queue is empty.</p>
                <?php else: ?>
                    <table>
                        <tr>
                            <th>Player</th>
                            <th>Method</th>
                            <th>Progress / Status</th>
                        </tr>
                        <?php foreach($queuedWithdrawals as $with): ?>
                        <tr>
                            <td>
                                <?= htmlspecialchars($with['first_name']) ?><br>
                                <small style="color: var(--text-muted);">@<?= htmlspecialchars($with['username']) ?></small>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($with['method_name']) ?></strong><br>
                                <small style="color: var(--text-muted);"><?= htmlspecialchars($with['handle']) ?></small>
                            </td>
                            <td>
                                <div style="display: flex; justify-content: space-between; align-items: flex-end;">
                                    <strong style="color: var(--danger);">-$<?= number_format($with['amount'], 2) ?></strong>
                                    <small style="color: var(--text-muted);">$<?= number_format($with['amount_confirmed'], 2) ?> of $<?= number_format($with['amount'], 2) ?></small>
                                </div>
                                
                                <div class="progress-container">
                                    <?php 
                                        $confPct = ($with['amount'] > 0) ? ($with['amount_confirmed'] / $with['amount']) * 100 : 0;
                                        $subPct = ($with['amount'] > 0) ? ($with['amount_submitted'] / $with['amount']) * 100 : 0;
                                    ?>
                                    <div class="progress-bar" style="width: <?= $confPct ?>%;"></div>
                                    <div class="progress-bar progress-bar-submitted" style="width: <?= $subPct ?>%; margin-top: -6px;"></div>
                                </div>
                                
                                <div style="margin-top: 5px; display: flex; justify-content: space-between; align-items: center;">
                                    <?php if ($with['status'] === 'completed'): ?>
                                        <span class="badge badge-success">Completed</span>
                                    <?php elseif ($with['status'] === 'partially_paid'): ?>
                                        <span class="badge badge-info">Partially Paid</span>
                                    <?php elseif ($with['status'] === 'partial_dispute'): ?>
                                        <span class="badge badge-danger">Disputed</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Queued</span>
                                    <?php endif; ?>
                                    
                                    <form action="<?= $basePath ?>/bookie/withdrawal/manual_pay" method="POST" onsubmit="return confirmManualPay(this, '<?= number_format($with['amount_remaining'], 2, '.', '') ?>')">
                                        <input type="hidden" name="id" value="<?= $with['id'] ?>">
                                        <input type="hidden" name="amount" id="manual_amount_<?= $with['id'] ?>" value="<?= $with['amount_remaining'] ?>">
                                        <input type="hidden" name="note" value="">
                                        <button type="submit" class="btn btn-warning" style="padding: 2px 8px; font-size: 0.65rem;">Manual Pay</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <script>
            function confirmManualPay(form, maxAmount) {
                const amount = prompt("Enter amount to pay manually (Max: $" + maxAmount + "):", maxAmount);
                if (amount === null) return false;
                
                const val = parseFloat(amount);
                if (isNaN(val) || val <= 0 || val > parseFloat(maxAmount)) {
                    alert("Invalid amount.");
                    return false;
                }

                const note = prompt("Enter a note for this adjustment (optional):");
                if (note === null) return false;
                
                form.querySelector('input[name="amount"]').value = val;
                form.querySelector('input[name="note"]').value = note;
                return confirm("Are you sure you want to mark $" + val + " as manually paid?");
            }
        </script>

        <div class="card">
            <h2 style="margin-top: 0;">Full Ledger History</h2>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">Immutable record of all deposits, withdrawals, and actions.</p>
            <?php if (empty($ledgerHistory)): ?>
                <p style="color: var(--text-muted);">No transactions yet.</p>
            <?php else: ?>
                <table id="ledgerTable">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Player</th>
                            <th>Type</th>
                            <th>Note</th>
                            <th>Amount</th>
                            <th>Receipt</th>
                            <th>Reverse Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($ledgerHistory as $tx): ?>
                    <tr>
                        <td><small style="color: var(--text-muted);"><?= htmlspecialchars($tx['created_at']) ?></small></td>
                        <td>
                            <?= htmlspecialchars($tx['first_name']) ?> <br>
                            <small style="color: var(--text-muted);">@<?= htmlspecialchars($tx['username']) ?></small>
                        </td>
                        <td>
                            <strong><?= ucwords(str_replace('_', ' ', $tx['entry_type'])) ?></strong><br>
                            <small style="color: var(--text-muted);">Ref: <?= htmlspecialchars($tx['reference_type']) ?> #<?= htmlspecialchars($tx['reference_id']) ?></small>
                        </td>
                        <td>
                            <div style="font-size: 0.75rem; max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($tx['description'] ?? '') ?>">
                                <?= htmlspecialchars($tx['description'] ?? 'N/A') ?>
                            </div>
                        </td>
                        <td>
                            <?php 
                                        $displayAmount = $tx['amount'];
                                        if ($tx['entry_type'] === 'withdrawal_requested' && $tx['amount'] == 0) {
                                            $displayAmount = $tx['with_amount'] ?? 0;
                                        }

                                        if ($tx['direction'] === 'credit'): 
                                    ?>
                                        <span style="color: var(--success); font-weight: 600;">+$<?= number_format($displayAmount, 2) ?></span>
                                    <?php else: ?>
                                        <span style="color: var(--danger); font-weight: 600;">-$<?= number_format($displayAmount, 2) ?></span>
                                    <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($tx['receipt_url'])): ?>
                                <a href="<?= $basePath ?>/bookie/receipt?<?= $tx['reference_type'] === 'split' ? 'split=' : 'id=' ?><?= $tx['reference_id'] ?>" target="_blank" style="color: var(--primary); text-decoration: none; font-size: 0.85rem;"><i class="fa-solid fa-image"></i> View Receipt</a>
                            <?php else: ?>
                                <span style="color: var(--text-muted); font-size: 0.85rem;">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($tx['reference_type'] === 'deposit'): ?>
                                <form action="<?= $basePath ?>/bookie/deposit/reverse" method="POST" onsubmit="return confirm('Are you sure you want to reverse this action and return the deposit to PENDING status?');">
                                    <input type="hidden" name="id" value="<?= $tx['reference_id'] ?>">
                                    <button type="submit" class="btn btn-warning" style="padding: 0.25rem 0.75rem; font-size: 0.75rem;">Reverse</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </main>

    <!-- DataTables JS & jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#ledgerTable').DataTable({
                "order": [[ 0, "desc" ]], // Sort by first column (Date) descending
                "pageLength": 25,
                "language": {
                    "search": "🔍 Search Ledger:",
                    "lengthMenu": "Show _MENU_ entries"
                }
            });
        });
    </script>
</body>
</html>
