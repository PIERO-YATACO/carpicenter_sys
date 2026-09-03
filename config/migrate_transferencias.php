<?php
require_once __DIR__ . '/db.php';

try {
    $sql = file_get_contents(__DIR__ . '/../modules/transferencias/transferencias.sql');
    $db->exec($sql);
    echo "Tablas de transferencias creadas con éxito.\n";
    
    // Poblar inventario inicial en el Almacén Principal para productos existentes
    $db->exec("
        INSERT INTO inventario_local (producto_id, local_id, stock_actual)
        SELECT p.id, (SELECT id FROM locales WHERE nombre = 'Almacén Principal' LIMIT 1), COALESCE((SELECT stock_resultante FROM kardex k WHERE k.producto_id = p.id ORDER BY id DESC LIMIT 1), 0)
        FROM productos p
        ON CONFLICT DO NOTHING;
    ");
    echo "Inventario inicial poblado en Almacén Principal.\n";

} catch (PDOException $e) {
    die("Error al crear tablas: " . $e->getMessage());
}
?>
