<?php
require_once __DIR__ . '/db.php';

try {
    $db->beginTransaction();

    // Add the 4 new columns to contratos table
    $db->exec("
        ALTER TABLE contratos
            ADD COLUMN IF NOT EXISTS departamento        VARCHAR(100) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS provincia           VARCHAR(100) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS distrito            VARCHAR(100) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS persona_recepciona  VARCHAR(200) DEFAULT NULL;
    ");

    $db->commit();
    echo "✅ Columnas agregadas exitosamente a la tabla contratos.\n";
    echo "   - departamento\n";
    echo "   - provincia\n";
    echo "   - distrito\n";
    echo "   - persona_recepciona\n";

} catch (Exception $e) {
    $db->rollBack();
    echo "❌ Error: " . $e->getMessage() . "\n";
}
