<?php
require_once 'c:/xampp/htdocs/carpicenter_sys/config/db.php';

try {
    $db->beginTransaction();

    // Agregar columnas si no existen
    $db->exec("ALTER TABLE proveedores ADD COLUMN IF NOT EXISTS contacto VARCHAR(255) DEFAULT NULL");
    $db->exec("ALTER TABLE proveedores ADD COLUMN IF NOT EXISTS rubro VARCHAR(255) DEFAULT NULL");
    $db->exec("ALTER TABLE proveedores ADD COLUMN IF NOT EXISTS estado VARCHAR(50) DEFAULT 'Activo'");

    // Vaciar tabla actual
    $db->exec("TRUNCATE TABLE proveedores RESTART IDENTITY CASCADE");

    // Insertar datos
    $proveedores = [
        ['JL GRACIA', '20545702445', 'MELAMINA'],
        ['INVERSIONES OKADA', '20601691320', 'MELAMINA Y TAPACANTO'],
        ['GRUPO TEXAM', '20610755527', 'TAPACANTO'],
        ['REPRSENTACIONES MARTIN', '20306637305', 'MELAMINA, FORMICA, MDF'],
        ['EL MUNDO DEL TAPACANTO', '20609640422', 'CORREDERAS, TIRADORES, JALADORES, TAPACANTO'],
        ['DIST. EL MUNDO DEL TAPACANTO Y HERRAJES', '20614693984', 'CORREDERAS, TIRADORES, JALADORES, TAPACANTO'],
        ['LA CASA DEL TIRADOR', '20524244960', 'CORREDERAS, TIRADORES, JALADORES, TAPACANTO'],
        ['OKPLAST', '20611330708', 'REGATONES'],
        ['CARTONERIAS ANDINAS', '20600904532', 'CARTON Y EMBALAJES'],
        ['FERRETERIA LILY', '10102327531', 'ART. FERRETERIA, EMBALAJES, PINTURA'],
        ['PLASTICOOS EDUCATIVOS', '20608246411', 'PLASTICOS PARA BANQUETAS, SILLAS MOBILIARIO ESCOLAR'],
        ['INDUSTRIAS SIMET', '20523570391', 'SILLAS Y MESAS'],
        ['DISTRIBUIDORA VENSO', '20506535876', 'SILLAS Y MESAS'],
        ['PROVEFABRICA DEL PERU', '20521647371', 'SILLA OFICINA'],
        ['POLINPLAST', '20505520638', 'SILLAS POLIPROPILENO'],
        ['XIMESA', '20125508716', 'SILLAS POLIPROPILENO'],
        ['ARKOR', '20601406757', 'SILLAS POLIPROPILENO'],
    ];

    $stmt = $db->prepare("INSERT INTO proveedores (nombre, ruc, rubro) VALUES (?, ?, ?)");
    foreach ($proveedores as $p) {
        $stmt->execute([$p[0], $p[1], $p[2]]);
    }

    $db->commit();
    echo "Migración de proveedores exitosa.";

} catch (Exception $e) {
    $db->rollBack();
    echo "Error: " . $e->getMessage();
}
?>
