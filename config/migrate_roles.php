<?php
require_once __DIR__ . '/db.php';

try {
    $db->exec("
        ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS local_id INT REFERENCES locales(id);
        
        -- Asignar locales a los usuarios base
        UPDATE usuarios SET local_id = (SELECT id FROM locales WHERE nombre = 'Almacén Principal' LIMIT 1) WHERE username = 'admin';
        UPDATE usuarios SET local_id = (SELECT id FROM locales WHERE nombre = 'Tienda 1' LIMIT 1) WHERE username = 'jflores';
        UPDATE usuarios SET local_id = (SELECT id FROM locales WHERE nombre = 'Almacén Principal' LIMIT 1) WHERE username = 'mlopez';
        UPDATE usuarios SET local_id = (SELECT id FROM locales WHERE nombre = 'Almacén Principal' LIMIT 1) WHERE username = 'pramirez';
    ");
    echo "Migración de locales para usuarios completada.\n";
} catch (PDOException $e) {
    die("Error en migración: " . $e->getMessage());
}
?>
