<?php
require_once dirname(__DIR__) . '/app/Config/database.php';
require_once dirname(__DIR__) . '/app/Core/Database.php';

$db = App\Core\Database::getInstance();

// Vijay is player 4, Wallet 3
$walletId = 3;
$splitId = 5;
$amount = 20.00;

$stmt = $db->prepare("SELECT current_balance FROM wallets WHERE id = ?");
$stmt->execute([$walletId]);
$opening = $stmt->fetchColumn();

$db->beginTransaction();

// Insert missing ledger entry
$closing = $opening + $amount;
$ins = $db->prepare("INSERT INTO wallet_ledger (wallet_id, entry_type, direction, amount, opening_balance, closing_balance, reference_type, reference_id, description) VALUES (?, 'split_dispute_correction', 'credit', ?, ?, ?, 'split', ?, ?)");
$ins->execute([$walletId, $amount, $opening, $closing, $splitId, "Manual correction: Re-credit after dispute resolution (Confirm) Split #$splitId"]);

// Update wallet balance
$upd = $db->prepare("UPDATE wallets SET current_balance = current_balance + ?, available_balance = available_balance + ? WHERE id = ?");
$upd->execute([$amount, $amount, $walletId]);

$db->commit();

echo "Successfully corrected Vijay's balance. New Balance: $closing\n";
