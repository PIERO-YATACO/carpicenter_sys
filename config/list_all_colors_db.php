<?php
require_once __DIR__ . '/db.php';
$stmt = $db->query("SELECT id, nombre, codigo FROM colores ORDER BY id ASC");
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) {
    echo "ID: {$c['id']} | Nombre: '{$c['nombre']}' | Codigo: '{$c['codigo']}'\n";
}
