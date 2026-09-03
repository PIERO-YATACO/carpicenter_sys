<?php
require_once __DIR__ . '/db.php';

try {
    $db->beginTransaction();

    // 1. Asegurar tabla cabecera de cuadre de caja
    $db->exec("
        CREATE TABLE IF NOT EXISTS finanzas_cuadre_caja (
            id SERIAL PRIMARY KEY,
            codigo VARCHAR(50),
            titulo VARCHAR(200),
            area VARCHAR(100) DEFAULT 'ADMINISTRATIVO',
            tienda VARCHAR(100) DEFAULT 'GENERAL',
            encargado VARCHAR(150),
            fecha_inicio DATE,
            fecha_fin DATE,
            autorizacion_responsable VARCHAR(150) DEFAULT 'CARPICENTER',
            fecha_aut_responsable DATE,
            pagar_a VARCHAR(150),
            fecha_pagar_a DATE,
            autorizacion_direccion VARCHAR(150),
            fecha_aut_direccion DATE,
            saldo_anterior NUMERIC(12, 2) DEFAULT 0.00,
            total_ingreso NUMERIC(12, 2) DEFAULT 0.00,
            total_salida_produccion NUMERIC(12, 2) DEFAULT 0.00,
            total_salida_melamine NUMERIC(12, 2) DEFAULT 0.00,
            total_salida_servicio NUMERIC(12, 2) DEFAULT 0.00,
            total_salida_combustible NUMERIC(12, 2) DEFAULT 0.00,
            total_salida_movilidad NUMERIC(12, 2) DEFAULT 0.00,
            total_salida_otros NUMERIC(12, 2) DEFAULT 0.00,
            total_egreso NUMERIC(12, 2) DEFAULT 0.00,
            saldo_final NUMERIC(12, 2) DEFAULT 0.00,
            observacion TEXT,
            estado VARCHAR(50) DEFAULT 'CERRADO',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // Quitar NOT NULL de titulo si existía
    try {
        $db->exec("ALTER TABLE finanzas_cuadre_caja ALTER COLUMN titulo DROP NOT NULL;");
    } catch (Exception $e) {}

    // Agregar columnas si no existen (en caso la tabla ya existiera previamente con menos columnas)
    $colsToAdd = [
        "codigo VARCHAR(50)",
        "area VARCHAR(100) DEFAULT 'ADMINISTRATIVO'",
        "tienda VARCHAR(100) DEFAULT 'GENERAL'",
        "encargado VARCHAR(150)",
        "autorizacion_responsable VARCHAR(150) DEFAULT 'CARPICENTER'",
        "fecha_aut_responsable DATE",
        "pagar_a VARCHAR(150)",
        "fecha_pagar_a DATE",
        "autorizacion_direccion VARCHAR(150)",
        "fecha_aut_direccion DATE",
        "saldo_anterior NUMERIC(12, 2) DEFAULT 0.00",
        "total_salida_produccion NUMERIC(12, 2) DEFAULT 0.00",
        "total_salida_melamine NUMERIC(12, 2) DEFAULT 0.00",
        "total_salida_servicio NUMERIC(12, 2) DEFAULT 0.00",
        "total_salida_combustible NUMERIC(12, 2) DEFAULT 0.00",
        "total_salida_movilidad NUMERIC(12, 2) DEFAULT 0.00",
        "total_salida_otros NUMERIC(12, 2) DEFAULT 0.00",
        "saldo_final NUMERIC(12, 2) DEFAULT 0.00",
        "estado VARCHAR(50) DEFAULT 'CERRADO'"
    ];

    foreach ($colsToAdd as $col) {
        $parts = explode(" ", $col);
        $colName = $parts[0];
        try {
            $db->exec("ALTER TABLE finanzas_cuadre_caja ADD COLUMN IF NOT EXISTS $col;");
        } catch (Exception $e) {
            // Ignorar si ya existe
        }
    }

    // 2. Asegurar tabla detalle de cuadre de caja
    $db->exec("
        CREATE TABLE IF NOT EXISTS finanzas_cuadre_detalle (
            id SERIAL PRIMARY KEY,
            cuadre_id INT REFERENCES finanzas_cuadre_caja(id) ON DELETE CASCADE,
            fecha DATE,
            tipo VARCHAR(20) NOT NULL, -- 'ENTRADA' o 'SALIDA'
            categoria VARCHAR(50), -- 'SALDO_ANTERIOR', 'TIENDA_1', 'TIENDA_2', 'TIENDA_3', 'TIENDA_4', 'OTRO_INGRESO', 'PRODUCCION', 'MELAMINE', 'SERVICIO', 'COMBUSTIBLE', 'MOVILIDAD', 'OTROS'
            detalle VARCHAR(255),
            descripcion VARCHAR(255),
            nro_justificante VARCHAR(100),
            monto NUMERIC(12, 2) DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // Quitar NOT NULL de detalle si existía
    try {
        $db->exec("ALTER TABLE finanzas_cuadre_detalle ALTER COLUMN detalle DROP NOT NULL;");
    } catch (Exception $e) {}

    $detColsToAdd = [
        "fecha DATE",
        "categoria VARCHAR(50)",
        "detalle VARCHAR(255)",
        "descripcion VARCHAR(255)",
        "nro_justificante VARCHAR(100)"
    ];

    foreach ($detColsToAdd as $col) {
        try {
            $db->exec("ALTER TABLE finanzas_cuadre_detalle ADD COLUMN IF NOT EXISTS $col;");
        } catch (Exception $e) {
            // Ignorar
        }
    }

    $db->commit();
    echo "MIGRACION_EXITOSA: Tablas de Cuadre de Caja actualizadas correctamente.\n";
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "ERROR_MIGRACION: " . $e->getMessage() . "\n";
}
