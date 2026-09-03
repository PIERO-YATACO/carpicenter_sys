<?php
require_once __DIR__ . '/db.php';
$stmt = $db->query("SELECT id, nombre, codigo FROM productos ORDER BY id ASC");
$prods = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($prods as $p) {
    echo "ID: {$p['id']} | Nombre: '{$p['nombre']}' | Codigo actual: '{$p['codigo']}'\n";
}
