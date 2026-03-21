<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Fix DB Tool (Bookie1)</h1>";

require_once __DIR__ . '/app/Config/database.php';
require_once __DIR__ . '/app/Core/Database.php';

use App\Core\Database;

try {
    $db = Database::getInstance();
    $password = 'pass123';
    $hashed = password_hash($password, PASSWORD_BCRYPT);

    echo "Generated Hash: <code>$hashed</code><br>";

    $stmt = $db->prepare("UPDATE users SET password = ? WHERE username = 'bookie1'");
    $stmt->execute([$hashed]);
    
    echo "Bookie1 password updated.<br>";

    $stmt = $db->prepare("SELECT id, username, password, role FROM users WHERE username = 'bookie1'");
    $stmt->execute();
    $bookie = $stmt->fetch();

    echo "<h3>Bookie1 Record in DB</h3><pre>";
    print_r($bookie);
    echo "</pre>";
    
    if ($bookie && password_verify($password, $bookie['password'])) {
        echo "<h2 style='color:green'>VERIFICATION SUCCESS: Password matches DB hash!</h2>";
    } else {
        echo "<h2 style='color:red'>VERIFICATION FAILED: Password Does NOT match DB hash!</h2>";
    }

} catch (Exception $e) {
    echo "<h2 style='color:red'>Error: " . $e->getMessage() . "</h2>";
}
