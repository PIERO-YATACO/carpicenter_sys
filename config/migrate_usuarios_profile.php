<?php
require_once __DIR__ . '/db.php';

try {
    $db->beginTransaction();

    // 1. Add nombre_completo column if not exists
    $db->exec("ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS nombre_completo VARCHAR(255);");

    // 2. Add email column if not exists
    $db->exec("ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS email VARCHAR(255);");

    // 3. Update existing users with sensible defaults so they have profiles
    $db->exec("UPDATE usuarios SET nombre_completo = 'Administrador', email = 'admin@carpicenter.com' WHERE username = 'admin' AND (nombre_completo IS NULL OR nombre_completo = '');");
    $db->exec("UPDATE usuarios SET nombre_completo = 'Juan Flores', email = 'jflores@carpicenter.com' WHERE username = 'jflores' AND (nombre_completo IS NULL OR nombre_completo = '');");
    $db->exec("UPDATE usuarios SET nombre_completo = 'Maria López', email = 'mlopez@carpicenter.com' WHERE username = 'mlopez' AND (nombre_completo IS NULL OR nombre_completo = '');");
    $db->exec("UPDATE usuarios SET nombre_completo = 'Pedro Ramírez', email = 'pramirez@carpicenter.com' WHERE username = 'pramirez' AND (nombre_completo IS NULL OR nombre_completo = '');");

    $db->commit();
    echo "Migración de perfil de usuarios completada con éxito.\n";
} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    die("Error al ejecutar migración de perfiles: " . $e->getMessage() . "\n");
}
?>
