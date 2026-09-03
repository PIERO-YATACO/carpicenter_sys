<?php
require 'config/db.php';
try {
    $db->exec("
        ALTER TABLE clientes ADD COLUMN IF NOT EXISTS tipo_doc VARCHAR(20) DEFAULT 'DNI';
        ALTER TABLE clientes ADD COLUMN IF NOT EXISTS email VARCHAR(150);
        ALTER TABLE clientes ADD COLUMN IF NOT EXISTS direccion TEXT;
        ALTER TABLE clientes ADD COLUMN IF NOT EXISTS ciudad VARCHAR(100);
        ALTER TABLE clientes ADD COLUMN IF NOT EXISTS tipo_cliente VARCHAR(20) DEFAULT 'Persona Natural';
        ALTER TABLE clientes ADD COLUMN IF NOT EXISTS razon_social VARCHAR(200);
        ALTER TABLE clientes ADD COLUMN IF NOT EXISTS estado VARCHAR(20) DEFAULT 'Activo';
        ALTER TABLE clientes ADD COLUMN IF NOT EXISTS notas TEXT;
        ALTER TABLE clientes ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
    ");
    echo "Tabla clientes actualizada con éxito.\n";
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
