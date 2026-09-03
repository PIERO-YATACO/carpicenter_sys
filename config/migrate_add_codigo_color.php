<?php
require_once __DIR__ . '/db.php';

try {
    // 1. Agregar columna codigo a la tabla colores
    $db->exec("ALTER TABLE colores ADD COLUMN IF NOT EXISTS codigo VARCHAR(50)");
    
    // Mapeo inicial de codigos abreviados estándar para los colores existentes
    $codigos_defecto = [
        'BLANCO' => 'BLA',
        'NEGRO' => 'NEG',
        'TAUPE' => 'TAU',
        'ROJO' => 'ROJ',
        'AMARILLO' => 'AMA',
        'AMARILLO OSCURO' => 'AMA-OSC',
        'VERDE LIMON' => 'V-LIM',
        'VERDE PASTEL' => 'V-PAS',
        'CELESTE' => 'CEL',
        'GRIS OSCURO' => 'G-OSC',
        'GRIS CLARO' => 'G-CLA',
        'AZUL' => 'AZU',
        'DUNA' => 'DUN',
        'MARRON' => 'MAR',
        'MARRON (VIDRIO)' => 'MAR-VID',
        'ROSADO' => 'ROS',
        'VERDE' => 'VER',
        'NARANJA' => 'NAR',
        'TORTORA(TAUPE)' => 'TOR',
        'TURQUESA CLARO' => 'TQ-CLA',
        'TURQUESA OSCURO' => 'TQ-OSC',
        'JADE' => 'JAD',
        'PANELA' => 'PAN',
        'MULTICOLOR' => 'MULTI',
        'VIDRIO TRANSPARENTE' => 'VID-TRA',
        'VIDRIO PAVONADO' => 'VID-PAV',
        'VIDRIO TEMPLADO' => 'VID-TEM',
        'VIDRIO NEGRO' => 'VID-NEG',
        'VIDRIO BRONCE' => 'VID-BRO',
        'ESTÁNDAR (SIN COLOR)' => 'EST'
    ];

    $stmt_col = $db->query("SELECT id, nombre, codigo FROM colores ORDER BY id ASC");
    $cols = $stmt_col->fetchAll(PDO::FETCH_ASSOC);

    $stmt_upd_col = $db->prepare("UPDATE colores SET codigo = ? WHERE id = ?");
    foreach ($cols as $c) {
        if (empty($c['codigo'])) {
            $nom = strtoupper(trim($c['nombre']));
            $code = $codigos_defecto[$nom] ?? ('COL-' . str_pad($c['id'], 2, '0', STR_PAD_LEFT));
            $stmt_upd_col->execute([$code, $c['id']]);
        }
    }

    // 2. Agregar columna codigo a la tabla producto_colores si se desea SKU personalizado por producto-color
    $db->exec("ALTER TABLE producto_colores ADD COLUMN IF NOT EXISTS codigo VARCHAR(100)");

    // Poblar producto_colores.codigo si está vacío combinando codigo_producto + codigo_color
    $db->exec("
        UPDATE producto_colores pc
        SET codigo = p.codigo || '-' || c.codigo
        FROM productos p, colores c
        WHERE pc.producto_id = p.id AND pc.color_id = c.id AND (pc.codigo IS NULL OR pc.codigo = '')
    ");

    echo "OK: Migracion de codigos de color completada exitosamente.\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
