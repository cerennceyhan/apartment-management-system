<?php
// config/database.php - Opens the global $db connection used by the pages.
// Connection settings live in src/Database.php (DB_HOST, DB_NAME, DB_USER, DB_PASS).

require_once __DIR__ . '/../src/Database.php';

$database = new Database();
$db = $database->getConnection();
