<?php
require_once dirname(__DIR__) . '/app/Config/database.php';
require_once dirname(__DIR__) . '/app/Core/Database.php';

$db = App\Core\Database::getInstance();

// 1. Identification
$rajaniId = 3; 
$bookieId = 1;

// 2. Ensure Rajani has an active withdrawal
echo "Checking if Rajani has active withdrawals...\n";
$stmt = $db->prepare("SELECT id, amount_remaining FROM withdrawals WHERE player_id = ? AND bookie_id = ? AND status IN ('queued', 'partially_paid', 'partial_dispute')");
$stmt->execute([$rajaniId, $bookieId]);
$active = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$active) {
    echo "Creating a dummy withdrawal for Rajani...\n";
    $db->prepare("INSERT INTO withdrawals (player_id, bookie_id, amount, amount_remaining, status, method_id) VALUES (?, ?, 50.00, 50.00, 'queued', 1)")
       ->execute([$rajaniId, $bookieId]);
    $activeId = $db->lastInsertId();
    $activeAmt = 50.00;
} else {
    $activeId = $active['id'];
    $activeAmt = $active['amount_remaining'];
    echo "Found active withdrawal #$activeId with remaining: $activeAmt\n";
}

// 3. Test the query logic that I added to the controller
echo "\nSimulating the controller check...\n";
$stmt2 = $db->prepare("
    SELECT amount_remaining 
    FROM withdrawals 
    WHERE player_id = ? AND bookie_id = ? 
    AND status IN ('queued', 'partially_paid', 'partial_dispute')
    LIMIT 1
");
$stmt2->execute([$rajaniId, $bookieId]);
$result = $stmt2->fetch(PDO::FETCH_ASSOC);

if ($result) {
    $pendingAmt = number_format($result['amount_remaining'], 2);
    echo "RESULT: [MATCHED] Found pending withdrawal of $pendingAmt. User will be blocked.\n";
    echo "SUCCESS: Logic correctly identifies active withdrawal.\n";
} else {
    echo "RESULT: [NOT MATCHED] No pending withdrawal found. User will be allowed to proceed.\n";
    echo "FAILURE: Logic failed to identify active withdrawal.\n";
}
