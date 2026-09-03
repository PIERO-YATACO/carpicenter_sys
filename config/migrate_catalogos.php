<?php
require_once __DIR__ . '/db.php';

try {
    echo "Iniciando migración para Módulo de Catálogos...\n";

    // 1. Campos adicionales en la tabla productos
    $db->exec("
        ALTER TABLE productos 
        ADD COLUMN IF NOT EXISTS destacado_catalogo BOOLEAN DEFAULT FALSE,
        ADD COLUMN IF NOT EXISTS en_catalogo BOOLEAN DEFAULT TRUE,
        ADD COLUMN IF NOT EXISTS descripcion_corta TEXT,
        ADD COLUMN IF NOT EXISTS tiempo_fabricacion VARCHAR(50) DEFAULT '3 a 5 días',
        ADD COLUMN IF NOT EXISTS dimensiones VARCHAR(100);
    ");

    // 2. Tabla para colecciones de catálogo (Ej: Colección Cocinas 2024, Colección Ejecutiva, etc.)
    $db->exec("
        CREATE TABLE IF NOT EXISTS catalogo_colecciones (
            id SERIAL PRIMARY KEY,
            nombre VARCHAR(150) NOT NULL,
            descripcion TEXT,
            portada_url TEXT,
            estado VARCHAR(20) DEFAULT 'Activo',
            fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // 3. Tabla para galería de imágenes por producto/modelo
    $db->exec("
        CREATE TABLE IF NOT EXISTS catalogo_galeria (
            id SERIAL PRIMARY KEY,
            producto_id INT REFERENCES productos(id) ON DELETE CASCADE,
            imagen_url TEXT NOT NULL,
            titulo VARCHAR(150),
            orden INT DEFAULT 0,
            fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // 4. Si la tabla productos_maestros existe, agregarle soporte de catálogo
    $db->exec("
        DO $$ 
        BEGIN 
            IF EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'productos_maestros') THEN
                ALTER TABLE productos_maestros 
                ADD COLUMN IF NOT EXISTS en_catalogo BOOLEAN DEFAULT TRUE,
                ADD COLUMN IF NOT EXISTS destacado_catalogo BOOLEAN DEFAULT FALSE,
                ADD COLUMN IF NOT EXISTS imagen_url TEXT;
            END IF;
        END $$;
    ");

    echo "Migración completada con éxito.\n";
} catch (Exception $e) {
    echo "Error en la migración: " . $e->getMessage() . "\n";
}
