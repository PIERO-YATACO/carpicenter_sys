<?php
require_once __DIR__ . '/db.php';
$stmt = $db->query("
    SELECT table_name 
    FROM information_schema.tables 
    WHERE table_schema = 'public' AND table_type = 'BASE TABLE'
    ORDER BY table_name ASC
");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo "=== TABLAS EN 'carpicenter_db' (Total: " . count($tables) . ") ===\n";
foreach ($tables as $t) {
    $count = $db->query("SELECT COUNT(*) FROM \"$t\"")->fetchColumn();
    echo "- $t ($count registros)\n";
}
