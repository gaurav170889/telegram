<?php
require_once __DIR__ . '/config.php';

function db() : PDO {
  static $pdo = null;
  if ($pdo) return $pdo;

  try {
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ]);
  } catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
  }
  return $pdo;
}
?>