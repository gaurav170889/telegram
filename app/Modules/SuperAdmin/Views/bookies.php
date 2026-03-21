<?php include __DIR__ . '/partials/header.php'; ?>
<?php include __DIR__ . '/partials/sidebar.php'; ?>

<main class="main-content">
    <div class="topbar">
        <h1 class="page-title">Manage Bookies & Bots</h1>
    </div>
    
    <div class="glass-panel" style="padding: 2rem;">
        <h2 style="margin-top: 0; font-weight: 500; font-size: 1.25rem; margin-bottom: 1.5rem;">Add New Bookie</h2>
        
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

        <form action="<?= $basePath ?>/admin/bookies/add" method="POST" style="display: grid; gap: 1.5rem; grid-template-columns: 1fr 1fr;">
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-muted);">Login Username</label>
                <input type="text" name="username" required style="width: 100%; padding: 0.75rem; border-radius: 0.5rem; background: rgba(0,0,0,0.2); border: 1px solid var(--border-color); color: white; box-sizing: border-box;">
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-muted);">Temporary Password</label>
                <input type="text" name="password" required value="pass123" style="width: 100%; padding: 0.75rem; border-radius: 0.5rem; background: rgba(0,0,0,0.2); border: 1px solid var(--border-color); color: white; box-sizing: border-box;">
            </div>
            <div style="grid-column: span 2;">
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-muted);">Telegram Bot Token (from @BotFather)</label>
                <input type="text" name="bot_token" required placeholder="e.g., 123456789:ABCDEfghiJKlmNOPQRst_uVwXYZ" style="width: 100%; padding: 0.75rem; border-radius: 0.5rem; background: rgba(0,0,0,0.2); border: 1px solid var(--border-color); color: white; box-sizing: border-box; font-family: monospace;">
            </div>
            <div style="grid-column: span 2;">
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-muted);">Bookie's Bot Link / Username</label>
                <input type="text" name="bot_username" required placeholder="e.g., @Gamerlb01bot or t.me/Gamerlb01bot" style="width: 100%; padding: 0.75rem; border-radius: 0.5rem; background: rgba(0,0,0,0.2); border: 1px solid var(--border-color); color: white; box-sizing: border-box; font-family: monospace;">
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-muted);">Contract Start Date</label>
                <input type="date" name="start_date" required value="<?= date('Y-m-d') ?>" style="width: 100%; padding: 0.75rem; border-radius: 0.5rem; background: rgba(0,0,0,0.2); border: 1px solid var(--border-color); color: white; box-sizing: border-box; color-scheme: dark;">
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-muted);">Contract End Date</label>
                <input type="date" name="end_date" required value="<?= date('Y-m-d', strtotime('+30 days')) ?>" style="width: 100%; padding: 0.75rem; border-radius: 0.5rem; background: rgba(0,0,0,0.2); border: 1px solid var(--border-color); color: white; box-sizing: border-box; color-scheme: dark;">
            </div>
            <div style="grid-column: span 2;">
                <button type="submit" style="background: var(--primary); color: white; border: none; padding: 0.75rem 2rem; border-radius: 0.5rem; font-weight: 600; cursor: pointer; transition: background 0.2s;">Create Bookie</button>
            </div>
        </form>
    </div>
    
    <div class="glass-panel" style="padding: 2rem; margin-top: 2rem;">
        <h2 style="margin-top: 0; font-weight: 500; font-size: 1.25rem; margin-bottom: 1.5rem;">Registered Bookies</h2>
        
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <th style="padding: 1rem; color: var(--text-muted); font-weight: 500;">ID</th>
                        <th style="padding: 1rem; color: var(--text-muted); font-weight: 500;">Business Name</th>
                        <th style="padding: 1rem; color: var(--text-muted); font-weight: 500;">Username</th>
                        <th style="padding: 1rem; color: var(--text-muted); font-weight: 500;">Bot Token</th>
                        <th style="padding: 1rem; color: var(--text-muted); font-weight: 500;">Bot Link</th>
                        <th style="padding: 1rem; color: var(--text-muted); font-weight: 500;">Status</th>
                        <th style="padding: 1rem; color: var(--text-muted); font-weight: 500;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (isset($bookiesList) && count($bookiesList) > 0): ?>
                        <?php foreach($bookiesList as $b): ?>
                        <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                            <td style="padding: 1rem;"><?= $b['id'] ?></td>
                            <td style="padding: 1rem;"><?= htmlspecialchars($b['name']) ?: '<span style="color:var(--warning)">Pending Onboarding</span>' ?></td>
                            <td style="padding: 1rem; font-family: monospace;"><?= htmlspecialchars($b['username']) ?></td>
                            <td style="padding: 1rem; max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-family: monospace; color: var(--primary);"><?= htmlspecialchars($b['telegram_bot_token']) ?></td>
                            <td style="padding: 1rem; font-family: monospace;">
                                <?= htmlspecialchars($b['telegram_bot_username']) ?>
                                <?php if ($b['telegram_verified']): ?>
                                    <span style="color: var(--success); font-size: 0.8rem; margin-left: 0.25rem;">&#10004;</span>
                                <?php else: ?>
                                    <span style="color: var(--warning); font-size: 0.8rem; margin-left: 0.25rem;">&#9888; Unverified</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 1rem;">
                                <?php if($b['status'] === 'active'): ?>
                                    <span style="background: rgba(16, 185, 129, 0.2); color: var(--success); padding: 0.25rem 0.75rem; border-radius: 1rem; font-size: 0.85rem;">Active</span>
                                <?php elseif($b['status'] === 'disabled'): ?>
                                    <span style="background: rgba(239, 68, 68, 0.2); color: var(--danger); padding: 0.25rem 0.75rem; border-radius: 1rem; font-size: 0.85rem;">Disabled</span>
                                <?php else: ?>
                                    <span style="background: rgba(245, 158, 11, 0.2); color: var(--warning); padding: 0.25rem 0.75rem; border-radius: 1rem; font-size: 0.85rem; text-transform: capitalize;"><?= $b['status'] ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 1rem;">
                                <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                                    <button onclick="openEditModal(<?= $b['id'] ?>, '<?= htmlspecialchars($b['telegram_bot_token'], ENT_QUOTES) ?>', '<?= htmlspecialchars($b['telegram_bot_username'], ENT_QUOTES) ?>')" style="background: #3b82f6; color: white; border: none; padding: 0.5rem 1rem; border-radius: 0.25rem; cursor: pointer; font-size: 0.85rem;">Edit</button>
                                    
                                    <form action="<?= $basePath ?>/admin/bookies/toggle" method="POST" style="display: inline;">
                                        <input type="hidden" name="id" value="<?= $b['id'] ?>">
                                        <input type="hidden" name="current_status" value="<?= $b['status'] ?>">
                                        <?php if ($b['status'] === 'disabled'): ?>
                                            <button type="submit" style="background: var(--success); color: white; border: none; padding: 0.5rem 1rem; border-radius: 0.25rem; cursor: pointer; font-size: 0.85rem;">Activate</button>
                                        <?php else: ?>
                                            <button type="submit" style="background: var(--danger); color: white; border: none; padding: 0.5rem 1rem; border-radius: 0.25rem; cursor: pointer; font-size: 0.85rem;">Suspend</button>
                                        <?php endif; ?>
                                    </form>

                                    <?php if ($b['status'] !== 'onboarding'): ?>
                                    <form action="<?= $basePath ?>/admin/bookies/refresh-webhook" method="POST" style="display: inline;">
                                        <input type="hidden" name="id" value="<?= $b['id'] ?>">
                                        <button type="submit" style="background: var(--primary); color: white; border: none; padding: 0.5rem 1rem; border-radius: 0.25rem; cursor: pointer; font-size: 0.85rem;" title="Refresh Telegram Webhook URL">
                                            <i class="fas fa-sync-alt"></i> Webhook
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="padding: 2rem; text-align: center; color: var(--text-muted);">No bookies found. Add one above.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Edit Modal -->
<dialog id="editModal" style="background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 1rem; padding: 2rem; color: var(--text); backdrop-filter: blur(10px); min-width: 400px; max-width: 90vw;">
    <h2 style="margin-top: 0; font-weight: 500; font-size: 1.25rem; margin-bottom: 1.5rem;">Edit Bookie Telegram Info</h2>
    <form action="<?= $basePath ?>/admin/bookies/edit" method="POST" style="display: flex; flex-direction: column; gap: 1rem;">
        <input type="hidden" name="id" id="edit_id">
        <div>
            <label style="display: block; margin-bottom: 0.5rem; color: var(--text-muted);">Telegram Bot Token</label>
            <input type="text" name="bot_token" id="edit_bot_token" required style="width: 100%; padding: 0.75rem; border-radius: 0.5rem; background: rgba(0,0,0,0.2); border: 1px solid var(--border-color); color: white; box-sizing: border-box; font-family: monospace;">
        </div>
        <div>
            <label style="display: block; margin-bottom: 0.5rem; color: var(--text-muted);">Bot Link / Username</label>
            <input type="text" name="bot_username" id="edit_bot_username" required style="width: 100%; padding: 0.75rem; border-radius: 0.5rem; background: rgba(0,0,0,0.2); border: 1px solid var(--border-color); color: white; box-sizing: border-box; font-family: monospace;">
        </div>
        <div style="display: flex; gap: 1rem; margin-top: 1rem;">
            <button type="submit" style="background: var(--primary); color: white; border: none; padding: 0.75rem 2rem; border-radius: 0.5rem; font-weight: 600; cursor: pointer; flex: 1;">Save Changes</button>
            <button type="button" onclick="document.getElementById('editModal').close()" style="background: rgba(255,255,255,0.1); color: white; border: none; padding: 0.75rem 2rem; border-radius: 0.5rem; font-weight: 600; cursor: pointer; flex: 1;">Cancel</button>
        </div>
    </form>
</dialog>

<script>
    function openEditModal(id, botToken, botUsername) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_bot_token').value = botToken;
        document.getElementById('edit_bot_username').value = botUsername;
        document.getElementById('editModal').showModal();
    }
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>
