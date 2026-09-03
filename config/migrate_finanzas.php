<?php
require_once __DIR__ . '/db.php';

try {
    $db->beginTransaction();

    // 1. Cuentas por Cobrar
    $db->exec("
        CREATE TABLE IF NOT EXISTS finanzas_cuentas_cobrar (
            id SERIAL PRIMARY KEY,
            referencia VARCHAR(100),
            ft_lt VARCHAR(100),
            cliente VARCHAR(255) NOT NULL,
            f_venc DATE,
            monto_total NUMERIC(12, 2) DEFAULT 0.00,
            banco VARCHAR(50),
            monto_pagado NUMERIC(12, 2) DEFAULT 0.00,
            fecha_pago DATE,
            moneda VARCHAR(10) DEFAULT 'SOLES',
            estado VARCHAR(50) DEFAULT 'PENDIENTE',
            observacion TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // 2. SUNAT (Liquidación de impuestos)
    $db->exec("
        CREATE TABLE IF NOT EXISTS finanzas_sunat (
            id SERIAL PRIMARY KEY,
            nro_letra VARCHAR(100),
            cod VARCHAR(50),
            tributo VARCHAR(100) NOT NULL,
            periodo VARCHAR(100),
            importe NUMERIC(12, 2) DEFAULT 0.00,
            estado VARCHAR(50) DEFAULT 'PENDIENTE',
            f_pago DATE,
            observacion TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // 3. SAT (Papeletas / Infracciones)
    $db->exec("
        CREATE TABLE IF NOT EXISTS finanzas_sat (
            id SERIAL PRIMARY KEY,
            f_emision DATE,
            nro_letra VARCHAR(100),
            tipo_infraccion VARCHAR(150),
            nro_documento VARCHAR(100),
            por_pagar NUMERIC(12, 2) DEFAULT 0.00,
            f_pago DATE,
            estado VARCHAR(50) DEFAULT 'PENDIENTE',
            observacion TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // 4. Fraccionamientos
    $db->exec("
        CREATE TABLE IF NOT EXISTS finanzas_fraccionamientos (
            id SERIAL PRIMARY KEY,
            resolucion_nro VARCHAR(100),
            cuota_nro VARCHAR(50),
            monto NUMERIC(12, 2) DEFAULT 0.00,
            f_venc DATE,
            estado VARCHAR(50) DEFAULT 'PENDIENTE',
            f_pago DATE,
            observacion TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // 5. Gastos Fijos
    $db->exec("
        CREATE TABLE IF NOT EXISTS finanzas_gastos_fijos (
            id SERIAL PRIMARY KEY,
            categoria VARCHAR(100) NOT NULL,
            tienda VARCHAR(100),
            proveedor_servicio VARCHAR(200),
            monto NUMERIC(12, 2) DEFAULT 0.00,
            f_venc DATE,
            f_pago DATE,
            estado VARCHAR(50) DEFAULT 'PENDIENTE',
            observacion TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // 6. Impuesto Predial y Arbitrios
    $db->exec("
        CREATE TABLE IF NOT EXISTS finanzas_predial (
            id SERIAL PRIMARY KEY,
            predio_local VARCHAR(200) NOT NULL,
            trimestre VARCHAR(50),
            monto NUMERIC(12, 2) DEFAULT 0.00,
            f_venc DATE,
            f_pago DATE,
            estado VARCHAR(50) DEFAULT 'PENDIENTE',
            observacion TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // 7. Permisos e Inspecciones (ITSE / Defensa Civil / Licencias)
    $db->exec("
        CREATE TABLE IF NOT EXISTS finanzas_permisos (
            id SERIAL PRIMARY KEY,
            titulo VARCHAR(255) NOT NULL,
            direccion_tienda VARCHAR(255),
            tienda VARCHAR(50),
            f_servicio DATE,
            f_venc DATE,
            observacion TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // 8. Bancos y Letras por Pagar
    $db->exec("
        CREATE TABLE IF NOT EXISTS finanzas_bancos_letras (
            id SERIAL PRIMARY KEY,
            tipo VARCHAR(50) NOT NULL, -- 'BANCO' o 'LETRA_PROVEEDOR'
            banco_proveedor VARCHAR(200) NOT NULL,
            nro_unico VARCHAR(100),
            factura_ref VARCHAR(100),
            monto_soles NUMERIC(12, 2) DEFAULT 0.00,
            monto_dolares NUMERIC(12, 2) DEFAULT 0.00,
            f_emision DATE,
            f_venc DATE,
            f_pago DATE,
            estado VARCHAR(50) DEFAULT 'PENDIENTE',
            banco_pago VARCHAR(50),
            observacion TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // 9. Cuadre de Caja (Cabecera)
    $db->exec("
        CREATE TABLE IF NOT EXISTS finanzas_cuadre_caja (
            id SERIAL PRIMARY KEY,
            titulo VARCHAR(200) NOT NULL,
            fecha_inicio DATE,
            fecha_fin DATE,
            total_ingreso NUMERIC(12, 2) DEFAULT 0.00,
            total_egreso NUMERIC(12, 2) DEFAULT 0.00,
            saldo NUMERIC(12, 2) DEFAULT 0.00,
            entregado_a VARCHAR(150),
            observacion TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // 10. Cuadre de Caja (Detalle)
    $db->exec("
        CREATE TABLE IF NOT EXISTS finanzas_cuadre_detalle (
            id SERIAL PRIMARY KEY,
            cuadre_id INT REFERENCES finanzas_cuadre_caja(id) ON DELETE CASCADE,
            tipo VARCHAR(20) NOT NULL, -- 'INGRESO' o 'EGRESO'
            detalle VARCHAR(255) NOT NULL,
            monto NUMERIC(12, 2) DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");

    $db->commit();
    echo "MIGRACION_EXITOSA: Todas las tablas financieras se crearon correctamente.";
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "ERROR_MIGRACION: " . $e->getMessage();
}
