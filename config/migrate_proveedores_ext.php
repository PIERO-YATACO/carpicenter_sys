<?php
require_once 'c:/xampp/htdocs/carpicenter_sys/config/db.php';

try {
    $db->beginTransaction();

    $db->exec("ALTER TABLE proveedores ADD COLUMN IF NOT EXISTS email VARCHAR(255) DEFAULT NULL");
    $db->exec("ALTER TABLE proveedores ADD COLUMN IF NOT EXISTS ciudad VARCHAR(255) DEFAULT NULL");
    $db->exec("ALTER TABLE proveedores ADD COLUMN IF NOT EXISTS observaciones TEXT DEFAULT NULL");
    
    // Datos bancarios
    $db->exec("ALTER TABLE proveedores ADD COLUMN IF NOT EXISTS banco VARCHAR(100) DEFAULT NULL");
    $db->exec("ALTER TABLE proveedores ADD COLUMN IF NOT EXISTS numero_cuenta VARCHAR(100) DEFAULT NULL");
    $db->exec("ALTER TABLE proveedores ADD COLUMN IF NOT EXISTS cci VARCHAR(100) DEFAULT NULL");
    $db->exec("ALTER TABLE proveedores ADD COLUMN IF NOT EXISTS tipo_cuenta VARCHAR(50) DEFAULT NULL");

    $db->commit();
    echo "Columnas extendidas agregadas a proveedores exitosamente.";

} catch (Exception $e) {
    $db->rollBack();
    echo "Error: " . $e->getMessage();
}
?>
