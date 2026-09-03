<?php
require_once 'db.php';
try {
    // 1. Create colores table
    $db->exec("CREATE TABLE IF NOT EXISTS colores (
        id SERIAL PRIMARY KEY,
        nombre VARCHAR(50) NOT NULL UNIQUE
    )");

    // 2. Create producto_colores table
    $db->exec("CREATE TABLE IF NOT EXISTS producto_colores (
        producto_id INTEGER REFERENCES productos(id) ON DELETE CASCADE,
        color_id INTEGER REFERENCES colores(id) ON DELETE CASCADE,
        stock INTEGER DEFAULT 0,
        PRIMARY KEY(producto_id, color_id)
    )");

    // 3. Insert base colors
    $colores = ['BLANCO', 'NEGRO', 'TAUPE', 'ROJO', 'AMARILLO', 'VERDE LIMON', 'VERDE PASTEL', 'CELESTE', 'GRIS OSCURO', 'GRIS CLARO', 'AZUL', 'DUNA', 'MARRON (VIDRIO)', 'ROSADO', 'VERDE', 'NARANJA', 'TORTORA(TAUPE)', 'TURQUESA CLARO', 'TURQUESA OSCURO'];
    
    $stmt = $db->prepare("INSERT INTO colores (nombre) VALUES (?) ON CONFLICT (nombre) DO NOTHING");
    foreach($colores as $color) {
        $stmt->execute([$color]);
    }

    // 4. Check if stock_actual exists in productos to migrate
    $check_column = $db->query("SELECT column_name FROM information_schema.columns WHERE table_name='productos' AND column_name='stock_actual'");
    if ($check_column->rowCount() > 0) {
        // Migrate existing stock to 'BLANCO' (ID 1) as fallback so we don't lose data
        $db->exec("INSERT INTO producto_colores (producto_id, color_id, stock)
                   SELECT id, 1, stock_actual FROM productos WHERE stock_actual > 0
                   ON CONFLICT DO NOTHING");

        // 5. Drop stock_actual from productos
        $db->exec("ALTER TABLE productos DROP COLUMN stock_actual");
        echo "Columna stock_actual eliminada y datos migrados.\n";
    }

    echo "Migración de colores completada exitosamente.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
