<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - <?= htmlspecialchars($bookie->name); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        .menu-item.active { border-left: 3px solid var(--primary); color: white; background: rgba(255,255,255,0.05); }

        .main-content {
            margin-left: var(--sidebar-width);
            flex-grow: 1;
            padding: 2rem 3rem;
            width: calc(100% - var(--sidebar-width));
            box-sizing: border-box;
        }

        .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; border-bottom: 1px solid var(--border-color); padding-bottom: 1.5rem; }
        h1.page-title { margin: 0; font-size: 2rem; background: linear-gradient(to right, #a78bfa, #f472b6); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }

        .card { background: var(--bg-card); padding: 1.5rem; border-radius: 1rem; margin-bottom: 2rem; border: 1px solid var(--border-color); }
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background: var(--bg-card); padding: 1.5rem; border-radius: 1rem; border: 1px solid var(--border-color); }
        .stat-label { font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.5rem; }
        .stat-value { font-size: 1.5rem; font-weight: 700; }

        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { padding: 1rem; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05); }
        th { color: var(--text-muted); font-weight: 500; }
        
        .filter-bar { display: flex; gap: 1rem; align-items: flex-end; margin-bottom: 2rem; }
        .form-group { display: flex; flex-direction: column; gap: 0.5rem; }
        .form-group label { font-size: 0.75rem; color: var(--text-muted); font-weight: 600; }
        .form-control { background: #1e293b; border: 1px solid var(--border-color); color: white; padding: 0.5rem 1rem; border-radius: 0.5rem; font-family: inherit; }
        
        .btn { padding: 0.65rem 1.25rem; border: none; border-radius: 0.5rem; cursor: pointer; font-size: 0.85rem; font-weight: 600; color: white; display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; transition: 0.3s; }
        .btn-primary { background: var(--primary); }
        .btn-primary:hover { background: var(--primary-hover); }
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
            <a href="<?= $basePath ?>/dashboard" class="menu-item"><i class="fa-solid fa-chart-pie"></i> Dashboard</a>
            <div class="menu-label">Analytics</div>
            <a href="<?= $basePath ?>/bookie/reports" class="menu-item active"><i class="fa-solid fa-file-invoice-dollar"></i> Reports</a>
            <a href="#" class="menu-item"><i class="fa-solid fa-users"></i> My Players</a>
            <div class="menu-label">Settings</div>
            <a href="<?= $basePath ?>/logout" class="menu-item" style="color: var(--danger);"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <div class="topbar">
            <h1 class="page-title">Analytics & Reports</h1>
            <div style="color: var(--text-muted);">Performance Overview</div>
        </div>

        <form class="filter-bar" method="GET">
            <div class="form-group">
                <label>Start Date</label>
                <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate) ?>">
            </div>
            <div class="form-group">
                <label>End Date</label>
                <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate) ?>">
            </div>
            <button type="submit" class="btn btn-primary">Apply Filter</button>
        </form>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Revenue (Deposits)</div>
                <div class="stat-value" style="color: var(--success);">+$<?= number_format($stats['total_revenue'] ?? 0, 2) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Payouts (Withdrawals)</div>
                <div class="stat-value" style="color: var(--danger);">-$<?= number_format($stats['total_payouts'] ?? 0, 2) ?></div>
            </div>
            <div class="stat-card">
                <?php $net = ($stats['total_revenue'] ?? 0) - ($stats['total_payouts'] ?? 0); ?>
                <div class="stat-label">Net P&L</div>
                <div class="stat-value" style="color: <?= $net >= 0 ? 'var(--success)' : 'var(--danger)' ?>;">
                    <?= $net >= 0 ? '+' : '' ?>$<?= number_format($net, 2) ?>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Transactions</div>
                <div class="stat-value"><?= number_format($stats['transaction_count'] ?? 0) ?></div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">
            <div class="card">
                <h2 style="margin-top: 0; font-size: 1.25rem;">Player Performance</h2>
                <p style="font-size: 0.85rem; color: var(--text-muted);">Revenue and Payouts per player for the selected period.</p>
                <?php if (empty($playerBreakdown)): ?>
                    <p style="color: var(--text-muted); padding: 2rem; text-align: center;">No data found for this period.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Player</th>
                                <th>Revenue (Dep)</th>
                                <th>Payouts (With)</th>
                                <th>Net</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($playerBreakdown as $p): ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars($p['first_name']) ?><br>
                                        <small style="color: var(--text-muted);">@<?= htmlspecialchars($p['username']) ?></small>
                                    </td>
                                    <td style="color: var(--success); font-weight: 500;">+$<?= number_format($p['revenue'], 2) ?></td>
                                    <td style="color: var(--danger); font-weight: 500;">-$<?= number_format($p['payouts'], 2) ?></td>
                                    <td style="font-weight: 600;">
                                        <?php $pnet = $p['revenue'] - $p['payouts']; ?>
                                        <span style="color: <?= $pnet >= 0 ? 'var(--success)' : 'var(--danger)' ?>">
                                            $<?= number_format($pnet, 2) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div class="card">
                <h2 style="margin-top: 0; font-size: 1.25rem;">Method Breakdown</h2>
                <p style="font-size: 0.85rem; color: var(--text-muted);">Volume by payment platform.</p>
                <?php if (empty($methodBreakdown)): ?>
                    <p style="color: var(--text-muted); padding: 2rem; text-align: center;">No data.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Platform</th>
                                <th style="text-align: right;">Total Volume</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($methodBreakdown as $m): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($m['method_name']) ?></strong></td>
                                    <td style="text-align: right; font-weight: 600;">$<?= number_format($m['volume'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>
