<?php
require_once __DIR__ . '/db.php';

$stmt = $db->query("
    SELECT
        tc.table_name, 
        kcu.column_name, 
        ccu.table_name AS foreign_table_name,
        ccu.column_name AS foreign_column_name 
    FROM information_schema.table_constraints AS tc 
    JOIN information_schema.key_column_usage AS kcu
      ON tc.constraint_name = kcu.constraint_name
      AND tc.table_schema = kcu.table_schema
    JOIN information_schema.constraint_column_usage AS ccu
      ON ccu.constraint_name = tc.constraint_name
      AND ccu.table_schema = tc.table_schema
    WHERE tc.constraint_type = 'FOREIGN KEY' AND tc.table_schema='public';
");

$fks = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== LLAVES FORÁNEAS (Foreign Keys) EN 'carpicenter_db' (Total: " . count($fks) . ") ===\n";
foreach ($fks as $fk) {
    echo "{$fk['table_name']}.{$fk['column_name']} -> {$fk['foreign_table_name']}.{$fk['foreign_column_name']}\n";
}
