<?php
require_once __DIR__ . '/db.php';

try {
    $db->exec("
        INSERT INTO locales (nombre, tipo) VALUES 
        ('Tienda 3', 'Tienda'), 
        ('Tienda 4', 'Tienda')
        ON CONFLICT DO NOTHING;
    ");
    echo "Tiendas 3 y 4 agregadas con éxito.\n";
} catch (PDOException $e) {
    die("Error al agregar tiendas: " . $e->getMessage());
}
?>
