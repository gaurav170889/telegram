<?php
require_once __DIR__ . '/app/Config/database.php';
$db = \App\Core\Database::getInstance();
$db->exec("ALTER TABLE bookie_players ADD COLUMN temp_payload JSON NULL DEFAULT NULL AFTER current_state;");
echo "Added temp_payload to bookie_players.";
