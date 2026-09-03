<?php
require_once __DIR__ . '/db.php';

try {
    $db->beginTransaction();

    // 1. Actualizar códigos oficiales de 2 letras para los Colores
    $color_codes_map = [
        'BLANCO' => 'BL',
        'NEGRO' => 'NE',
        'TAUPE' => 'TA',
        'ROJO' => 'RO',
        'AMARILLO' => 'AM',
        'VERDE LIMON' => 'VL',
        'VERDE PASTEL' => 'VP',
        'JADE' => 'JA',
        'CELESTE' => 'CE',
        'GRIS OSCURO' => 'GO',
        'GRIS CLARO' => 'GC',
        'AZUL' => 'AZ',
        'DUNA' => 'DR',
        'DUNA RAYADA' => 'DR',
        'DUNA ESTRELLADA' => 'DE',
        'MARRON (VIDRIO)' => 'MV',
        'ROSADO' => 'RS',
        'VERDE' => 'VE',
        'NARANJA' => 'NA',
        'TORTORA(TAUPE)' => 'TO',
        'TURQUESA CLARO' => 'TC',
        'TURQUESA OSCURO' => 'TQ',
        'AMARILLO OSCURO' => 'AO',
        'MARRON' => 'MA',
        'PANELA' => 'PA',
        'MULTICOLOR' => 'MU',
        'VIDRIO TRANSPARENTE' => 'VT',
        'VIDRIO PAVONADO' => 'VP',
        'VIDRIO TEMPLADO' => 'VT',
        'VIDRIO NEGRO' => 'VN',
        'VIDRIO BRONCE' => 'VB',
        'ESTÁNDAR (SIN COLOR)' => 'ES'
    ];

    $stmt_upd_col = $db->prepare("UPDATE colores SET codigo = ? WHERE id = ?");
    $cols = $db->query("SELECT id, nombre FROM colores")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
        $nom = strtoupper(trim($c['nombre']));
        $code = $color_codes_map[$nom] ?? mb_substr(preg_replace('/[^A-Z]/', '', $nom), 0, 2);
        $stmt_upd_col->execute([$code, $c['id']]);
    }

    // 2. Mapeo específico y generador de códigos de producto según la hoja de Carpicenter
    // Formato: CA - [TIPO (SI, BA, BU, ME, SE...)] - [MODELO]
    $exact_prod_codes = [
        'SILLA AMI' => 'CA-SI-AMI',
        'SILLA CAPRI' => 'CA-SI-CAPRI',
        'SILLA CAPITONE' => 'CA-SI-CAPITONE',
        'BANCO CAPITONE' => 'CA-BA-CAPITONE',
        'SILLA CRISTY' => 'CA-SI-CRISTY',
        'SILLA KARIM' => 'CA-SI-KARIM',
        'SILLA KARIM COMPLETO' => 'CA-SI-KARIM',
        'SILLA KARIN 2 COLORES' => 'CA-SI-KARIMDUO',
        'SILLA KARIM DUO' => 'CA-SI-KARIMDUO',
        'SILLA MABEL' => 'CA-SI-MABEL',
        'SILLA MARIELA' => 'CA-SI-MARIELA',
        'SILLA TIFFANI PP' => 'CA-SI-TIFFANIPP',
        'SILLA SARA' => 'CA-SI-SARA',
        'SILLA SOFY CLASICA' => 'CA-SI-SOFYCLASICA',
        'SILLA MICA' => 'CA-SI-MICA',
        'SILLA THONNET' => 'CA-SI-THONNET',
        'BANCO THONET' => 'CA-BA-THONET',
        'SILLA TOP' => 'CA-SI-TOP',
        'SILLA TULIPA' => 'CA-SI-TULIPA',
        'SILLA TULIPA PP' => 'CA-SI-TULIPAPP',
        'BANCO TULIPA' => 'CA-BA-TULIPA',
        'SILLA YESSI' => 'CA-SI-YESSI',
        'SILLA MARIE' => 'CA-SI-MARIE',
        'SILLA MARIE CLASSIC' => 'CA-SI-MARIECLASSIC',
        'SILLA SARA CLASSIC' => 'CA-SI-SARACLASSIC',
        'SILLA AMI CLASSIC' => 'CA-SI-AMICLASSIC',
        'SILLA TOLIX SIN MADERA' => 'CA-SI-TOLIXSM',
        'SILLA TOLIX CON MADERA' => 'CA-SI-TOLIXCM',
        'SILLA MAYA' => 'CA-SI-MAYA',
        'SILLA ADA SIN BRAZO' => 'CA-SI-ADASINBRAZO',
        'SILLA ECO RATAN' => 'CA-SI-ECORATAN',
        'SILLA BELINDA' => 'CA-SI-BELINDA',
        'SILLA DIANA DUO' => 'CA-SI-DIANADUO',
        'SILLA DORA' => 'CA-SI-DORA',
        'SILLA HILARY' => 'CA-SI-HILARY',
        'SILLA IVIS DUO' => 'CA-SI-IVISDUO',
        'SILLA KESLY' => 'CA-SI-KESLY',

        'BANCO CAPRI' => 'CA-BA-CAPRI',
        'BANCO ELY' => 'CA-BA-ELY',
        'BANCO REINA' => 'CA-BA-REINA',
        'BANCO TEFFY' => 'CA-BA-TEFFY',
        'BANCO TOP' => 'CA-BA-TOP',
        'BANCO VICTORIA' => 'CA-BA-VICTORIA',
        'BANCO TOLIX SIN MADERA' => 'CA-BA-TOLIXSM',
        'BANCO MAYA' => 'CA-BA-MAYA',
        'BANCO ARY' => 'CA-BA-ARY',
        'BANCO TAVARUA' => 'CA-BA-TAVARUA',
        'BANCO TAVARUA RATAN' => 'CA-BA-TAVARUARATAN',
        'BANCO SOFY CLÁSICA BAR' => 'CA-BA-SOFYBAR',
        'BANCO SUMI CB' => 'CA-BA-SUMICB',
        'BANCO SUMI SB' => 'CA-BA-SUMISB',
        'BANCO KARIM DUO' => 'CA-BA-KARIMDUO',
        'BANCO SOFY TAPIZADO CROMADO' => 'CA-BA-SOFYCROM',
        'BANCO AMARIS' => 'CA-BA-AMARIS',
        'BANCO AXXIS VENSO' => 'CA-BA-AXXISVENSO',
        'BANCO BRAND' => 'CA-BA-BRAND',
        'BANCO DALIA' => 'CA-BA-DALIA',
        'BANCO KESLY' => 'CA-BA-KESLY',
        'KESLY CROMADO' => 'CA-BA-KESLYCROM',
        'BANCO ORBIT' => 'CA-BA-ORBIT',
        'BANCO SONRISA' => 'CA-BA-SONRISA',
        'BANCO TRIBECA' => 'CA-BA-TRIBECA',
        'BANCO WORK' => 'CA-BA-WORK',

        'BUTACA FELY' => 'CA-BU-FELY',
        'BUTACA HILARY' => 'CA-BU-HILARY',
        'BUTACA MARILYN' => 'CA-BU-MARILYN',

        'MESA BRIE 80DM' => 'CA-ME-BRIE80DM',
        'MESA BRIE 80X80 CM' => 'CA-ME-BRIE80X80',
        'MESA BRIE 120X60 CM' => 'CA-ME-BRIE120X60',
        'MESA BRIE 140x80 cm' => 'CA-ME-BRIE140X80',
        'MESA JADE 80X80 CM' => 'CA-ME-JADE80X80',
        'MESA SOFY VIDRIO 120X80 CM' => 'CA-ME-SOFY120X80',
        'MESA HUAYRURO PP' => 'CA-ME-HUAYRURO',
        'MESA ROBLE 150x80' => 'CA-ME-ROBLE150X80',
        'MESA TAVARUA 95x95' => 'CA-ME-TAVARUA95X95',
        'MESA TAVARUA 145x85' => 'CA-ME-TAVARUA145X85',
        'MESA TULIPA PIEDRA 60 DM' => 'CA-ME-TULIPAPI60DM',
        'MESA TULIPA PIEDRA 80 DM' => 'CA-ME-TULIPAPI80DM',
        'MESA YESSI 60 DM' => 'CA-ME-YESSI60DM',
        'MESA PRISMA 80X80' => 'CA-ME-PRISMA80X80',
        'MESA TULIPA 60X60 LAMINADO' => 'CA-ME-TULIPALA60X60',
        'MESA TULIPA 80X80 LAMINADO' => 'CA-ME-TULIPALA80X80',
        'MESA TULIPA 60 DM LAMINADO' => 'CA-ME-TULIPALA60DM',
        'MESA TULIPA 80 DM LAMINADO' => 'CA-ME-TULIPALA80DM',
        'SET DE TERRAZA (3-2-1)' => 'CA-SE-TERRAZA321'
    ];

    $stmt_upd_prd = $db->prepare("UPDATE productos SET codigo = ? WHERE id = ?");
    $prods = $db->query("SELECT id, nombre, categoria_id FROM productos")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($prods as $p) {
        $nom = strtoupper(trim($p['nombre']));
        if (isset($exact_prod_codes[$nom])) {
            $code = $exact_prod_codes[$nom];
        } else {
            // Auto-detect prefix
            $pref = 'PR';
            if (str_starts_with($nom, 'SILLA')) { $pref = 'SI'; $nom_clean = trim(substr($nom, 5)); }
            elseif (str_starts_with($nom, 'BANCO')) { $pref = 'BA'; $nom_clean = trim(substr($nom, 5)); }
            elseif (str_starts_with($nom, 'BUTACA')) { $pref = 'BU'; $nom_clean = trim(substr($nom, 6)); }
            elseif (str_starts_with($nom, 'MESA')) { $pref = 'ME'; $nom_clean = trim(substr($nom, 4)); }
            elseif (str_starts_with($nom, 'SET')) { $pref = 'SE'; $nom_clean = trim(substr($nom, 3)); }
            else { $nom_clean = $nom; }

            $clean_sub = preg_replace('/[^A-Z0-9]/', '', $nom_clean);
            $code = 'CA-' . $pref . '-' . $clean_sub;
        }

        $stmt_upd_prd->execute([$code, $p['id']]);
    }

    // 3. Sincronizar códigos de variante en producto_colores con formato: [COD_PRODUCTO]-[COD_COLOR]
    $db->exec("
        UPDATE producto_colores pc
        SET codigo = p.codigo || '-' || c.codigo
        FROM productos p, colores c
        WHERE pc.producto_id = p.id AND pc.color_id = c.id
    ");

    $db->commit();
    echo "OK: Códigos oficiales aplicados exitosamente a Productos, Colores y Variantes.\n";
} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    echo "ERROR: " . $e->getMessage() . "\n";
}
