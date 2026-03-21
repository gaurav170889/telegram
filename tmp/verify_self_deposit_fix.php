<?php
require_once dirname(__DIR__) . '/app/Config/database.php';
require_once dirname(__DIR__) . '/app/Core/Database.php';
require_once dirname(__DIR__) . '/app/Modules/Wallet/Services/QueueService.php';
require_once dirname(__DIR__) . '/app/Modules/Wallet/Services/LedgerService.php';
require_once dirname(__DIR__) . '/app/Modules/Wallet/Repositories/LedgerRepository.php';
require_once dirname(__DIR__) . '/app/Modules/Wallet/Repositories/WalletRepository.php';

use App\Modules\Wallet\Services\QueueService;

$db = App\Core\Database::getInstance();

// 1. Identification
// Rajani is player 3 (based on previous dumps)
$rajaniId = 3;
$bookieId = 1;

// 2. Ensure Rajani has a withdrawal
echo "Checking Rajani's withdrawals...\n";
$stmt = $db->prepare("SELECT id, amount_remaining FROM withdrawals WHERE player_id = ? AND status IN ('queued', 'partially_paid')");
$stmt->execute([$rajaniId]);
$withdrawal = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$withdrawal) {
    echo "Creating dummy withdrawal for Rajani...\n";
    $db->prepare("INSERT INTO withdrawals (player_id, bookie_id, amount, amount_remaining, status, method_id) VALUES (?, ?, 100, 100, 'queued', 1)")
       ->execute([$rajaniId, $bookieId]);
    $withdrawalId = $db->lastInsertId();
} else {
    $withdrawalId = $withdrawal['id'];
    echo "Found withdrawal id: $withdrawalId\n";
}

// 3. Run split plan generation normally (WITHOUT fix)
$queueService = new QueueService();
echo "--- OLD BEHAVIOR (WITHOUT EXCLUSION) ---\n";
$splitsOld = $queueService->generateSplitPlan($bookieId, 30);
foreach($splitsOld as $s) {
    echo "Split Recipient: Player #" . $s['recipient_player_id'] . " Amount: " . $s['amount'] . "\n";
}

// 4. Run split plan generation WITH fix
echo "\n--- NEW BEHAVIOR (WITH EXCLUSION) ---\n";
$splitsNew = $queueService->generateSplitPlan($bookieId, 30, null, $rajaniId);
foreach($splitsNew as $s) {
    if (isset($s['recipient_player_id'])) {
        echo "Split Recipient: Player #" . $s['recipient_player_id'] . " Amount: " . $s['amount'] . "\n";
    } else {
        echo "Split Recipient: Bookie Amount: " . $s['amount'] . "\n";
    }
}

// 5. Final Check
$foundSelf = false;
foreach ($splitsNew as $s) {
    if (isset($s['recipient_player_id']) && $s['recipient_player_id'] == $rajaniId) {
        $foundSelf = true;
    }
}

if ($foundSelf) {
    echo "\nRESULT: FAILURE - Rajani found in her own plan.\n";
} else {
    echo "\nRESULT: SUCCESS - Rajani NOT found in her own plan.\n";
}
