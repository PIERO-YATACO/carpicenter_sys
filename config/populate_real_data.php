<?php
require_once 'db.php';

try {
    $db->beginTransaction();

    // Limpiar tablas dependientes
    $db->exec("TRUNCATE TABLE receta_detalles, recetas, auditoria, compra_detalles, venta_detalles, kardex, compras, ventas, productos, producto_colores RESTART IDENTITY CASCADE");

    // Asegurar que existan los colores
    $colores = ['BLANCO', 'NEGRO', 'TAUPE', 'ROJO', 'AMARILLO', 'VERDE LIMON', 'VERDE PASTEL', 'CELESTE', 'GRIS OSCURO', 'GRIS CLARO', 'AZUL', 'DUNA', 'MARRON (VIDRIO)', 'ROSADO', 'VERDE', 'NARANJA', 'TORTORA(TAUPE)', 'TURQUESA CLARO', 'TURQUESA OSCURO'];
    
    $stmt = $db->prepare("INSERT INTO colores (nombre) VALUES (?) ON CONFLICT (nombre) DO NOTHING");
    foreach($colores as $color) {
        $stmt->execute([$color]);
    }

    // Obtener map de colores
    $color_map = [];
    $qc = $db->query("SELECT id, nombre FROM colores");
    while($row = $qc->fetch(PDO::FETCH_ASSOC)) {
        $color_map[$row['nombre']] = $row['id'];
    }

    $productos_data = [
        "BANCO CAPRI" => ["categoria" => 2, "colores" => ["BLANCO"=>52, "NEGRO"=>33, "VERDE LIMON"=>54, "GRIS CLARO"=>49, "TURQUESA OSCURO"=>58]],
        "BANCO ELY" => ["categoria" => 2, "colores" => ["BLANCO"=>68, "ROJO"=>17, "GRIS OSCURO"=>3]],
        "BANCO REINA" => ["categoria" => 2, "colores" => ["ROJO"=>4, "AMARILLO"=>12]],
        "BANCO TAVARUA" => ["categoria" => 2, "colores" => ["GRIS CLARO"=>5]],
        "BANCO TEFFY" => ["categoria" => 2, "colores" => []],
        "BANCO TOP" => ["categoria" => 2, "colores" => ["BLANCO"=>73, "ROJO"=>3, "VERDE PASTEL"=>7, "GRIS CLARO"=>15]],
        "BANCO VICTORIA" => ["categoria" => 2, "colores" => ["BLANCO"=>24, "NEGRO"=>14, "GRIS CLARO"=>48, "TURQUESA OSCURO"=>60]],
        "BANCO TOLIX SIN MADERA" => ["categoria" => 2, "colores" => ["BLANCO"=>4, "NEGRO"=>2]],
        "BANCO ARY" => ["categoria" => 2, "colores" => []],
        "BANCO MAYA" => ["categoria" => 2, "colores" => ["BLANCO"=>49, "NEGRO"=>13, "VERDE LIMON"=>27, "GRIS CLARO"=>34]],
        "BANCO TAVARUA RATAN" => ["categoria" => 2, "colores" => ["GRIS CLARO"=>5]],
        "BUTACA FELY" => ["categoria" => 2, "colores" => ["BLANCO"=>97, "NEGRO"=>51, "TAUPE"=>69, "GRIS OSCURO"=>30, "TURQUESA OSCURO"=>46]],
        "BUTACA HILARY" => ["categoria" => 2, "colores" => ["BLANCO"=>8, "GRIS OSCURO"=>8]],
        "MESA BRIE 80DM" => ["categoria" => 1, "colores" => ["DUNA"=>7]],
        "MESA HUAYRURO PP" => ["categoria" => 1, "colores" => ["BLANCO"=>11]],
        "MESA ROBLE 150x80" => ["categoria" => 1, "colores" => ["MARRON (VIDRIO)"=>2]],
        "MESA TAVARUA 95x95" => ["categoria" => 1, "colores" => ["MARRON (VIDRIO)"=>2]],
        "MESA TAVARUA 145x85" => ["categoria" => 1, "colores" => ["MARRON (VIDRIO)"=>2]],
        "SILLA ADA SIN BRAZO" => ["categoria" => 2, "colores" => ["BLANCO"=>12, "NEGRO"=>50, "TAUPE"=>34, "ROJO"=>30, "CELESTE"=>9, "GRIS CLARO"=>4]],
        "SILLA AMI" => ["categoria" => 2, "colores" => ["ROJO"=>39]],
        "SILLA CAPRI" => ["categoria" => 2, "colores" => ["BLANCO"=>111, "NEGRO"=>110, "TAUPE"=>90, "ROJO"=>64, "VERDE LIMON"=>62, "GRIS CLARO"=>102, "ROSADO"=>41, "TURQUESA OSCURO"=>66]],
        "SILLA CAPITONE" => ["categoria" => 2, "colores" => ["BLANCO"=>12, "GRIS CLARO"=>27]],
        "SILLA CRISTY" => ["categoria" => 2, "colores" => ["BLANCO"=>167, "NEGRO"=>157, "TAUPE"=>117, "ROJO"=>118, "AMARILLO"=>168, "VERDE LIMON"=>142, "VERDE PASTEL"=>176, "CELESTE"=>170, "VERDE"=>127, "ROSADO"=>136, "NARANJA"=>155, "TORTORA(TAUPE)"=>150, "TURQUESA OSCURO"=>172]],
        "SILLA ECO RATAN" => ["categoria" => 2, "colores" => ["ROSADO"=>22]],
        "SILLA KARIM COMPLETO" => ["categoria" => 2, "colores" => ["NEGRO"=>147, "TAUPE"=>126, "VERDE PASTEL"=>93, "GRIS CLARO"=>171]],
        "SILLA KARIN 2 COLORES" => ["categoria" => 2, "colores" => ["BLANCO"=>145, "NEGRO"=>81, "VERDE PASTEL"=>163, "GRIS CLARO"=>178]],
        "SILLA MABEL" => ["categoria" => 2, "colores" => ["BLANCO"=>111, "VERDE LIMON"=>19, "CELESTE"=>54]],
        "SILLA MARIELA" => ["categoria" => 2, "colores" => ["BLANCO"=>15, "NEGRO"=>156, "AMARILLO"=>25, "CELESTE"=>16, "ROSADO"=>75]],
        "SILLA TIFFANI PP" => ["categoria" => 2, "colores" => ["BLANCO"=>17, "NEGRO"=>31, "VERDE PASTEL"=>47, "ROSADO"=>40]],
        "SILLA SARA IMPORTADO" => ["categoria" => 2, "colores" => ["NEGRO"=>22]],
        "SILLA SOFY CLASICA" => ["categoria" => 2, "colores" => ["AMARILLO"=>16, "VERDE LIMON"=>59, "CELESTE"=>30, "GRIS CLARO"=>27, "NARANJA"=>50, "TURQUESA OSCURO"=>1]],
        "SILLA MICA" => ["categoria" => 2, "colores" => ["BLANCO"=>119, "NEGRO"=>113, "TAUPE"=>54, "VERDE PASTEL"=>140, "GRIS CLARO"=>125, "TURQUESA OSCURO"=>168]],
        "SILLA THONET" => ["categoria" => 2, "colores" => ["BLANCO"=>131, "NEGRO"=>190, "TAUPE"=>145, "VERDE PASTEL"=>119, "GRIS CLARO"=>35, "TURQUESA CLARO"=>76]],
        "SILLA TOP" => ["categoria" => 2, "colores" => ["AMARILLO"=>32]],
        "SILLA TULIPA PATAS PVC" => ["categoria" => 2, "colores" => ["BLANCO"=>30, "AMARILLO"=>64, "VERDE PASTEL"=>17, "CELESTE"=>4, "AZUL"=>36]],
        "SILLA YESSI" => ["categoria" => 2, "colores" => ["CELESTE"=>15, "GRIS CLARO"=>25, "TURQUESA OSCURO"=>8]],
        "SILLA MARIE" => ["categoria" => 2, "colores" => ["BLANCO"=>52, "NEGRO"=>9, "TAUPE"=>4, "VERDE LIMON"=>29, "VERDE PASTEL"=>134, "CELESTE"=>28, "GRIS OSCURO"=>18, "MARRON (VIDRIO)"=>15, "ROSADO"=>75, "VERDE"=>22, "TURQUESA OSCURO"=>74]],
        "SILLA MARIE CLASSIC" => ["categoria" => 2, "colores" => ["TURQUESA CLARO"=>61]],
        "SILLA SARA CLASSIC" => ["categoria" => 2, "colores" => ["NEGRO"=>26, "TURQUESA CLARO"=>59]],
        "SILLA AMI CLASSIC" => ["categoria" => 2, "colores" => ["ROJO"=>39]],
        "SILLA MAYA" => ["categoria" => 2, "colores" => ["BLANCO"=>108, "NEGRO"=>154, "TAUPE"=>111, "VERDE PASTEL"=>107, "GRIS OSCURO"=>55, "TURQUESA OSCURO"=>56]],
        "SILLA TOLIX SIN MADERA" => ["categoria" => 2, "colores" => ["BLANCO"=>3, "NEGRO"=>4]],
        "SILLA TOLIX CON MADERA" => ["categoria" => 2, "colores" => ["NEGRO"=>3]]
    ];

    $stmt_prod = $db->prepare("INSERT INTO productos (nombre, categoria_id, precio_compra, precio_venta, stock_minimo, es_fabricado, fecha_creacion) VALUES (?, ?, 0, 0, 5, false, CURRENT_DATE) RETURNING id");
    $stmt_col = $db->prepare("INSERT INTO producto_colores (producto_id, color_id, stock) VALUES (?, ?, ?)");

    foreach($productos_data as $nombre => $data) {
        $stmt_prod->execute([$nombre, $data['categoria']]);
        $prod_id = $stmt_prod->fetchColumn();

        foreach($data['colores'] as $color_nombre => $stock) {
            if(isset($color_map[$color_nombre])) {
                $stmt_col->execute([$prod_id, $color_map[$color_nombre], $stock]);
            }
        }
    }

    $db->commit();
    echo "Catálogo de productos de la empresa insertado exitosamente.\n";

} catch (Exception $e) {
    if($db->inTransaction()) {
        $db->rollBack();
    }
    echo "Error: " . $e->getMessage() . "\n";
}
?>
