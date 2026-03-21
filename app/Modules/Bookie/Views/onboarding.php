<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Onboarding - Telegram Wallet Platform</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-hover: #4f46e5;
            --bg: #0f172a;
            --card-bg: #1e293b;
            --text: #f8fafc;
            --text-dim: #94a3b8;
            --error: #ef4444;
        }

        body {
            margin: 0;
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg);
            color: var(--text);
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
        }

        .card {
            background: var(--card-bg);
            padding: 2.5rem;
            border-radius: 1.5rem;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
            animation: fadeIn 0.5s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        h1 {
            margin: 0 0 1rem;
            font-weight: 600;
            font-size: 1.875rem;
            text-align: center;
            background: linear-gradient(to right, #818cf8, #c084fc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        p.description {
            color: var(--text-dim);
            margin-bottom: 2rem;
            font-size: 1rem;
            line-height: 1.6;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            font-weight: 400;
            color: var(--text-dim);
        }

        input {
            width: 100%;
            padding: 0.75rem 1rem;
            border-radius: 0.75rem;
            background: #0f172a;
            border: 1px solid #334155;
            color: white;
            font-family: inherit;
            box-sizing: border-box;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }

        .bot-token-info {
            background: rgba(99, 102, 241, 0.1);
            border: 1px solid rgba(99, 102, 241, 0.3);
            padding: 1rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
        }

        .bot-token-info span {
            display: block;
            margin-top: 0.5rem;
            color: #818cf8;
            font-family: monospace;
            word-break: break-all;
        }

        button {
            width: 100%;
            padding: 0.75rem;
            border-radius: 0.75rem;
            background: var(--primary);
            color: white;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s, transform 0.1s;
            margin-top: 1rem;
        }

        button:hover {
            background: var(--primary-hover);
        }

        .error-msg {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid var(--error);
            color: var(--error);
            padding: 0.75rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>Welcome, Agent!</h1>
        <p class="description">Finish setting up your account to start managing your players and transactions.</p>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid var(--success); color: var(--success); padding: 0.75rem; border-radius: 0.75rem; margin-bottom: 1.5rem; font-size: 0.875rem;">
                <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="error-msg"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form action="<?php echo str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])); ?>/onboarding" method="POST">
            <div class="form-group">
                <label for="name">Business / Bookie Name</label>
                <input type="text" id="name" name="name" required placeholder="e.g., Royal Elite Betting" value="<?php echo htmlspecialchars($bookie->name ?? ''); ?>">
            </div>

            <?php if (!$isVerified): ?>
                <div style="background: rgba(245, 158, 11, 0.1); border: 1px solid var(--warning); padding: 1.5rem; border-radius: 0.75rem; margin-bottom: 1.5rem; text-align: center;">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">📱</div>
                    <h3 style="color: var(--warning); margin: 0 0 0.5rem 0; font-size: 1.1rem; font-weight: 600;">Action Required: Verify Telegram</h3>
                    <p style="color: var(--text-dim); font-size: 0.875rem; margin-bottom: 1.5rem;">
                        Before you can complete setup, you must message your bot to prove ownership. Open your Bot: 
                        <br><br>
                        <a href="https://t.me/<?= ltrim(htmlspecialchars($bookie->telegramBotUsername ?? ''), '@') ?>" target="_blank" style="display: inline-block; background: rgba(99, 102, 241, 0.15); color: #818cf8; padding: 0.5rem 1rem; border-radius: 0.5rem; font-weight: 700; font-size: 1.1rem; text-decoration: none; border: 1px solid rgba(99, 102, 241, 0.3);">
                            <?= htmlspecialchars($bookie->telegramBotUsername ?? 'Your Bot') ?>
                        </a>
                        <br><br>
                        and send it the following exact code:
                    </p>
                    
                    <div style="background: rgba(0,0,0,0.3); border: 2px dashed var(--warning); padding: 1rem; border-radius: 0.5rem; display: inline-block; margin-bottom: 1.5rem;">
                        <span style="font-size: 2rem; font-weight: 700; letter-spacing: 0.5rem; color: #fcd34d; font-family: monospace;"><?= $bookie->telegramVerificationCode ?></span>
                    </div>

                    <p style="color: var(--text-dim); font-size: 0.875rem; margin-bottom: 1.5rem;">
                        Once the bot replies "Verification Successful", click the button below to continue.
                    </p>
                    
                    <a href="<?php echo str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])); ?>/onboarding/verify" style="display: inline-block; background: var(--warning); color: #000; padding: 0.75rem 1.5rem; border-radius: 0.5rem; font-weight: 600; text-decoration: none;">I have sent the code</a>
                </div>
            <?php else: ?>

            <?php if (!empty($gateways)): ?>
                <div style="margin-top: 2rem; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 1.5rem;">
                    <h3 style="margin-top:0; color:var(--text); font-size:1.1rem; font-weight:500;">Configure Payment Gateways</h3>
                    <p style="color:var(--text-dim); font-size:0.875rem; margin-bottom:1.5rem;">Configure at least one payment method for your players to deposit funds to be fully active.</p>

                    <?php foreach ($gateways as $gateway): ?>
                        <?php if ($gateway['is_active']): ?>
                            <div style="background: rgba(0,0,0,0.2); border: 1px solid #334155; border-radius: 0.75rem; padding: 1rem; margin-bottom: 1rem;">
                                <h4 style="margin:0 0 1rem; color:var(--primary); font-weight:500; font-size: 1rem;"><?= htmlspecialchars($gateway['name']) ?></h4>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label style="margin-bottom:0.25rem;">Your <?= htmlspecialchars($gateway['name']) ?> Username / ID</label>
                                    <input type="text" name="gateways[<?= $gateway['id'] ?>][account_id]" placeholder="e.g. @username or email" style="background: #1e293b; padding: 0.6rem;">
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="background: rgba(245, 158, 11, 0.1); border: 1px solid var(--warning); padding: 1rem; border-radius: 0.75rem; margin-bottom: 1.5rem;">
                    <p style="color: var(--warning); margin: 0; font-size: 0.875rem;">SuperAdmin has not configured any payment gateways yet.</p>
                </div>
            <?php endif; ?>

            <?php endif; ?>

            <button type="submit" <?= !$isVerified ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : '' ?>>
                <?= !$isVerified ? 'Verify Telegram First' : 'Complete Setup' ?>
            </button>
        </form>
    </div>
</body>
</html>
