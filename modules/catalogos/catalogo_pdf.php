<?php
require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../config/db.php';

// Filtro de categoría opcional y búsqueda
$categoria_id = isset($_GET['categoria_id']) ? (int)$_GET['categoria_id'] : 0;
$search = trim($_GET['search'] ?? '');

// ══════════════════════════════════════════════
// 1. OBTENER CATEGORÍAS DISPONIBLES
// ══════════════════════════════════════════════
$stmt_all_cats = $db->query("
    SELECT c.id, c.nombre, COUNT(p.id) as total_prods
    FROM categorias c
    JOIN productos p ON p.categoria_id = c.id
    WHERE (p.en_catalogo IS NOT FALSE)
    GROUP BY c.id, c.nombre
    HAVING COUNT(p.id) > 0
    ORDER BY c.nombre ASC
");
$categorias_lista = $stmt_all_cats->fetchAll(PDO::FETCH_ASSOC);

// ══════════════════════════════════════════════
// 2. CONSULTA DE PRODUCTOS ACTIVOS EN CATÁLOGO
// ══════════════════════════════════════════════
$where = ["(p.en_catalogo IS NOT FALSE)"];
$params = [];

if ($categoria_id > 0) {
    $where[] = "p.categoria_id = :cat";
    $params[':cat'] = $categoria_id;
}
if (!empty($search)) {
    $where[] = "(p.nombre ILIKE :search OR p.codigo ILIKE :search)";
    $params[':search'] = "%$search%";
}

$where_sql = "WHERE " . implode(" AND ", $where);

$sql = "
    SELECT p.id, p.nombre, p.codigo, p.descripcion_corta, p.precio_venta, p.categoria_id, p.destacado_catalogo, p.dimensiones, p.tiempo_fabricacion,
           COALESCE(NULLIF(p.imagen_url, ''), (SELECT imagen_url FROM producto_colores WHERE producto_id = p.id AND imagen_url IS NOT NULL AND imagen_url != '' LIMIT 1)) AS imagen_url,
           COALESCE(c.nombre, 'VARIOS') as categoria_nombre
    FROM productos p
    LEFT JOIN categorias c ON p.categoria_id = c.id
    $where_sql
    ORDER BY c.nombre ASC, p.destacado_catalogo DESC, p.nombre ASC
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$all_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ══════════════════════════════════════════════
// 3. MAPA DE COLORES POR PRODUCTO
// ══════════════════════════════════════════════
$prod_ids = array_column($all_products, 'id');
$colores_por_producto = [];
if (!empty($prod_ids)) {
    $ids_str = implode(',', array_map('intval', $prod_ids));
    $stmt_col = $db->query("
        SELECT pc.producto_id, c.nombre 
        FROM producto_colores pc 
        JOIN colores c ON pc.color_id = c.id 
        WHERE pc.producto_id IN ($ids_str)
        ORDER BY c.nombre ASC
    ");
    while ($row = $stmt_col->fetch(PDO::FETCH_ASSOC)) {
        $colores_por_producto[$row['producto_id']][] = $row['nombre'];
    }
}

// Función para mapear nombre de color a código HEX para swatches
function getCssColorHex($nombre) {
    $n = strtoupper(trim($nombre));
    $map = [
        'BLANCO' => '#FFFFFF', 
        'NEGRO' => '#111827', 
        'TAUPE' => '#877B73', 
        'TORTORA(TAUPE)' => '#877B73',
        'ROJO' => '#D32F2F',
        'AMARILLO' => '#EAB308', 
        'VERDE LIMON' => '#84CC16', 
        'VERDE PASTEL' => '#86EFAC',
        'CELESTE' => '#38BDF8', 
        'GRIS OSCURO' => '#475569', 
        'GRIS CLARO' => '#CBD5E1',
        'AZUL' => '#2563EB', 
        'DUNA' => '#D2B48C', 
        'MARRON (VIDRIO)' => '#78350F',
        'ROSADO' => '#F472B6', 
        'VERDE' => '#16A34A', 
        'NARANJA' => '#EA580C',
        'ROBLE' => '#A16207', 
        'CEDRO' => '#854D0E', 
        'WENGUE' => '#3B2F2F', 
        'CAOBA' => '#6A1B1A',
        'TURQUESA CLARO' => '#358588',
        'TURQUESA OSCURO' => '#275C6B',
        'JADE' => '#D2F0E0',
        'PANELA' => '#C89D66',
        'AMARILLO OSCURO' => '#CA8A04',
        'MARRON' => '#78350F',
        'VIDRIO TRANSPARENTE' => '#E0F2FE'
    ];
    return $map[$n] ?? '#94A3B8';
}

// Función para generar viñetas técnicas según la categoría del producto
function getProductTechnicalBullets($item) {
    $bullets = [];
    $cat = strtoupper(trim($item['categoria_nombre'] ?? ''));
    $desc = trim($item['descripcion_corta'] ?? '');
    
    if (!empty($desc)) {
        $lines = preg_split('/[\r\n•\-\*]+/', $desc);
        foreach ($lines as $l) {
            $l = trim($l);
            if (!empty($l)) $bullets[] = $l;
        }
    }
    
    // Si no hay viñetas suficientes en BD, generar viñetas estándar profesionales como en el catálogo de referencia
    if (count($bullets) < 3) {
        if (strpos($cat, 'SILLA') !== false) {
            $bullets = [
                'Monopieza inyectada en polipropileno virgen de alta densidad.',
                'Espaldar y asiento anatómico con protección UV.',
                'Estructura reforzada con base y patas de alta durabilidad.',
                'Regatones antideslizantes para protección del suelo.'
            ];
        } elseif (strpos($cat, 'BANCO') !== false) {
            $bullets = [
                'Asiento de polipropileno / tapizado ergonómico.',
                'Estructura de acero electroestático o madera tratada.',
                'Apoyapiés integrado para máximo confort.',
                'Patas con tapones protectores antideslizantes.'
            ];
        } elseif (strpos($cat, 'MESA') !== false) {
            $bullets = [
                'Tablero en melamina de alto tránsito / polipropileno / vidrio.',
                'Estructura metálica resistente con acabado en pintura horneada.',
                'Bases niveladoras con regatones antideslizantes.',
                'Fácil armado y mantenimiento preventivo.'
            ];
        } elseif (strpos($cat, 'BUTACA') !== false) {
            $bullets = [
                'Espaldar y asiento tapizado en tela / cuero sintético premium.',
                'Espuma de alta densidad indeformable para confort prolongado.',
                'Estructura robusta con finos acabados en madera o metal.',
                'Patas con acabado de protección antideslizante.'
            ];
        } else {
            $bullets = [
                'Estructura de alta resistencia para uso continuo.',
                'Acabados finos con materiales seleccionados de primera calidad.',
                'Diseño ergonómico pensado para confort y durabilidad.',
                'Garantía directa de fábrica Carpicenter.'
            ];
        }
    }
    
    return array_slice($bullets, 0, 4);
}

// ══════════════════════════════════════════════
// 4. AGRUPACIÓN POR CATEGORÍAS
// ══════════════════════════════════════════════
$categorias_con_productos = [];
foreach ($all_products as $prod) {
    $cat_name = strtoupper(trim($prod['categoria_nombre'] ?? 'VARIOS'));
    if (!isset($categorias_con_productos[$cat_name])) {
        $categorias_con_productos[$cat_name] = [];
    }
    $categorias_con_productos[$cat_name][] = $prod;
}

// Imágenes curadas para los separadores de sección
$imagenes_portada_cat = [
    'BANCOS' => '/carpicenter_sys/assets/img/portada_linea_bancos.png',
    'BUTACAS' => 'https://images.unsplash.com/photo-1586023492125-27b2c045efd7?w=1600&auto=format&fit=crop&q=90',
    'MESAS' => '/carpicenter_sys/assets/img/portada_linea_mesas.jpg',
    'SILLAS' => '/carpicenter_sys/assets/img/portada_linea_sillas.jpg',
    'SOFÁS' => 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=1600&auto=format&fit=crop&q=90',
    'JUEGOS DE COMEDOR' => 'https://images.unsplash.com/photo-1615066390971-03e4e1c36ddf?w=1600&auto=format&fit=crop&q=90',
    'VARIOS' => 'https://images.unsplash.com/photo-1618221195710-dd6b41faaea6?w=1600&auto=format&fit=crop&q=90'
];

$desc_cat = [
    'BANCOS' => 'Mobiliario ergonómico y resistente diseñado para barras, cocinas residenciales, terrazas y espacios comerciales de alto tránsito.',
    'BUTACAS' => 'Elegancia, confort y acabados de lujo para salas de estar, recepciones, hotelería y ambientes de descanso.',
    'MESAS' => 'Estructuras robustas y superficies resistentes al desgaste para comedores, oficinas, restaurantes y salas de reunión.',
    'SILLAS' => 'Diseños modernos que fusionan estilo vanguardista, ergonomía y máxima durabilidad para el uso diario residencial y comercial.',
    'VARIOS' => 'Mobiliario y complementos con la calidad y garantía de Industrias Carpicenter.'
];

// Numeración de páginas del catálogo
$page_cursor = 1;
$page_portada = $page_cursor++;
$page_nosotros = $page_cursor++;

$category_sections = [];
$cat_index_counter = 1;

foreach ($categorias_con_productos as $cat_name => $prods) {
    // 6 productos por página de catálogo (3 columnas x 2 filas o 2 columnas x 3 filas)
    $chunks = array_chunk($prods, 6);
    $prod_pages = [];
    $total_chunks = count($chunks);
    
    // Si la categoría tiene productos, incluye la portadilla de sección (spread)
    $cat_intro_page = $page_cursor++;
    
    foreach ($chunks as $chunk_idx => $chunk) {
        $start_item = ($chunk_idx * 6) + 1;
        $end_item = $start_item + count($chunk) - 1;
        $prod_pages[] = [
            'page_num' => $page_cursor++,
            'items' => $chunk,
            'is_continuation' => $chunk_idx > 0,
            'chunk_idx' => $chunk_idx + 1,
            'total_chunks' => $total_chunks,
            'start_item' => $start_item,
            'end_item' => $end_item,
            'total_items' => count($prods)
        ];
    }

    $category_sections[] = [
        'idx' => str_pad($cat_index_counter++, 2, '0', STR_PAD_LEFT),
        'name' => $cat_name,
        'title' => $cat_name,
        'intro_image' => $imagenes_portada_cat[$cat_name] ?? $imagenes_portada_cat['VARIOS'],
        'intro_page' => $cat_intro_page,
        'count' => count($prods),
        'desc' => $desc_cat[$cat_name] ?? 'Mobiliario de diseño con materiales de primera calidad y garantía certificada.',
        'prod_pages' => $prod_pages
    ];
}

$page_postura = $page_cursor++;
$page_contraportada = $page_cursor++;
$total_pages = $page_cursor - 1;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CATÁLOGO OFICIAL 2026 — Industrias Carpicenter</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,400;1,700&family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* ══════════════════════════════════════════════
           DISEÑO A4 LANDSCAPE / SPREAD INSPIRADO EN VENSO
           Dimensiones: 297mm ancho × 210mm alto
           ══════════════════════════════════════════════ */
        *, *::before, *::after { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
        }
        
        html, body { 
            background: #e2e8f0; 
            font-family: 'Montserrat', 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; 
            color: #1e293b;
            -webkit-print-color-adjust: exact !important; 
            print-color-adjust: exact !important; 
            color-adjust: exact !important;
            margin: 0;
            padding: 0;
        }

        /* Barra Flotante Superior (Solo Web) */
        .toolbar { 
            position: fixed; 
            top: 16px; 
            right: 24px; 
            z-index: 99999; 
            display: flex; 
            gap: 12px; 
            background: #ffffff; 
            padding: 9px 20px; 
            border-radius: 40px; 
            box-shadow: 0 12px 32px rgba(0,0,0,.2); 
            align-items: center; 
            border: 1px solid #cbd5e1;
        }
        .toolbar select {
            padding: 7px 14px;
            border-radius: 20px;
            border: 1px solid #cbd5e1;
            font-size: 13px;
            font-weight: 600;
            font-family: 'Montserrat', sans-serif;
            background: #f8fafc;
            outline: none;
            color: #0f172a;
        }
        .toolbar button, .toolbar a { 
            display: inline-flex; 
            align-items: center; 
            gap: 8px; 
            border: none; 
            padding: 8px 18px; 
            border-radius: 20px; 
            font: 700 .84rem/1 'Montserrat', sans-serif; 
            cursor: pointer; 
            text-decoration: none; 
        }
        .toolbar .btn-red { 
            background: #d32f2f; 
            color: #fff; 
            box-shadow: 0 4px 14px rgba(211,47,47,.35); 
            transition: background .2s, transform .2s; 
        }
        .toolbar .btn-red:hover { background: #b71c1c; transform: translateY(-1px); }
        .toolbar .btn-outline { background: #f8fafc; border: 1px solid #cbd5e1; color: #0f172a; }
        .toolbar .btn-outline:hover { background: #e2e8f0; }

        /* Contenedor central del catálogo */
        .catalog { 
            width: 297mm;
            margin: 28px auto; 
            display: flex; 
            flex-direction: column; 
            gap: 28px; 
            align-items: center;
        }

        /* ══════════════════════════════════════════════
           PÁGINA INDIVIDUAL (297mm × 210mm)
           ══════════════════════════════════════════════ */
        .page { 
            width: 297mm; 
            height: 210mm; 
            min-width: 297mm;
            max-width: 297mm;
            min-height: 210mm;
            max-height: 210mm;
            background: #ffffff !important; 
            position: relative; 
            overflow: hidden; 
            box-shadow: 0 10px 35px rgba(0,0,0,.15); 
            page-break-after: always; 
            break-after: page;
            page-break-inside: avoid; 
            break-inside: avoid;
            box-sizing: border-box;
        }

        /* Encabezado estándar con Logo y Página */
        .venso-header {
            position: absolute;
            top: 6mm;
            left: 10mm;
            right: 10mm;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 20;
        }
        .venso-header .header-logo {
            height: 8.5mm;
            width: auto;
            object-fit: contain;
        }
        .venso-header .header-pagenum {
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
            font-family: 'Montserrat', sans-serif;
        }

        .ref-watermark {
            position: absolute;
            font-size: 6.5px;
            color: #94a3b8;
            letter-spacing: 0.5px;
            font-weight: 600;
            z-index: 10;
        }
        .ref-watermark-top { top: 3mm; right: 10mm; }
        .ref-watermark-bottom { bottom: 3mm; left: 10mm; }

        /* ══════════════════════════════════════════════
           PÁGINA 1: PORTADA ESTILO EXACTO VENSO
           ══════════════════════════════════════════════ */
        .cover-container {
            width: 297mm;
            height: 210mm;
            position: relative;
            background: #ffffff;
            overflow: hidden;
            display: flex;
        }
        .cover-bg-image {
            position: absolute;
            top: 0;
            right: 0;
            width: 200mm;
            height: 210mm;
            object-fit: cover;
            z-index: 1;
        }
        .cover-overlay-gradient {
            position: absolute;
            top: 0;
            right: 0;
            width: 200mm;
            height: 210mm;
            background: linear-gradient(to right, rgba(255,255,255,1) 0%, rgba(255,255,255,0.2) 40%, rgba(255,255,255,0) 100%);
            z-index: 2;
        }

        .cover-top-logo {
            position: absolute;
            top: 14mm;
            right: 18mm;
            z-index: 10;
            text-align: right;
        }
        .cover-top-logo img {
            height: 18mm;
            width: auto;
            object-fit: contain;
            display: block;
        }

        /* Tarjeta vertical gris estilo catálogo Venso */
        .cover-grey-card {
            position: absolute;
            top: 18mm;
            left: 22mm;
            width: 82mm;
            height: 174mm;
            background: #dbe0e6;
            z-index: 10;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 16mm 10mm 12mm 10mm;
            box-sizing: border-box;
            box-shadow: 0 8px 30px rgba(0,0,0,0.06);
        }
        .cover-card-inner {
            text-align: center;
            margin: auto 0;
        }
        .cover-tagline-wrap {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-bottom: 2px;
        }
        .cover-tagline-wrap .line {
            height: 1px;
            width: 24px;
            background: #475569;
        }
        .cover-tagline-text {
            font-size: 15px;
            font-weight: 500;
            letter-spacing: 5px;
            color: #1e293b;
            text-transform: uppercase;
        }
        .cover-big-year {
            font-size: 64px;
            font-weight: 900;
            color: #0f172a;
            line-height: 1;
            letter-spacing: -1px;
            font-family: 'Montserrat', sans-serif;
            margin-top: 4px;
        }

        .cover-card-footer {
            border-top: 1px solid #94a3b8;
            padding-top: 8px;
            text-align: center;
        }
        .cover-card-url {
            font-size: 11px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: 1px;
            display: block;
            margin-bottom: 5px;
            text-decoration: none;
        }
        .cover-card-socials {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            font-size: 8.5px;
            color: #334155;
            font-weight: 600;
        }
        .cover-card-socials i {
            font-size: 10px;
            color: #0f172a;
        }

        /* ══════════════════════════════════════════════
           PÁGINA 2: NOSOTROS (CORPORATIVA EXACTA VENSO)
           ══════════════════════════════════════════════ */
        .nosotros-page-wrap {
            width: 297mm;
            height: 210mm;
            padding: 18mm 16mm 14mm 16mm;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
        }
        .nosotros-top-grid {
            display: grid;
            grid-template-columns: 85mm 165mm;
            gap: 12mm;
            align-items: center;
        }
        .nosotros-fleet-img {
            width: 100%;
            height: 48mm;
            object-fit: cover;
            border-radius: 6px;
        }
        .nosotros-top-texts {
            font-size: 11.5px;
            line-height: 1.6;
            color: #334155;
            font-weight: 500;
        }
        .nosotros-top-texts p {
            margin-bottom: 8px;
        }

        .nosotros-bottom-grid {
            display: grid;
            grid-template-columns: 130mm 125mm;
            gap: 10mm;
            align-items: center;
            margin-top: 4mm;
        }
        .nosotros-hands-img {
            width: 100%;
            height: 98mm;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .nosotros-pillars-box {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .nosotros-pillars-intro {
            font-size: 13px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 4px;
        }
        .pillar-row {
            border-top: 1px solid #0f172a;
            padding-top: 8px;
            padding-bottom: 4px;
        }
        .pillar-row-text {
            font-size: 11px;
            font-weight: 500;
            color: #1e293b;
            line-height: 1.4;
        }

        /* ══════════════════════════════════════════════
           SEPARADOR DE CATEGORÍA / SPREAD EDITORIAL
           ══════════════════════════════════════════════ */
        .cat-spread-container {
            width: 297mm;
            height: 210mm;
            display: grid;
            grid-template-columns: 140mm 157mm;
            position: relative;
            background: #ffffff;
        }
        .cat-spread-left {
            width: 140mm;
            height: 210mm;
            position: relative;
            background: #ffffff;
            display: flex;
            overflow: hidden;
            border-right: 1px solid #e2e8f0;
        }
        .cat-spread-img {
            width: 105mm;
            height: 190mm;
            object-fit: cover;
            margin: auto 0 auto 12mm;
            border-radius: 4px;
        }
        .cat-spread-vertical-title {
            position: absolute;
            top: 24mm;
            right: 2mm;
            writing-mode: vertical-rl;
            transform: rotate(180deg);
            font-size: 42px;
            font-weight: 900;
            color: #0f172a;
            letter-spacing: 2px;
            font-family: 'Montserrat', sans-serif;
            text-transform: capitalize;
            line-height: 1;
        }

        .cat-spread-right {
            width: 157mm;
            height: 210mm;
            padding: 16mm 10mm 10mm 10mm;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .spread-right-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4mm;
        }
        .spread-right-desc {
            font-size: 11px;
            line-height: 1.5;
            color: #475569;
            margin-top: 4mm;
        }
        .spread-highlight-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-top: 8mm;
        }
        .spread-highlight-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 8px 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .spread-highlight-card i {
            font-size: 16px;
            color: #d32f2f;
        }
        .spread-highlight-card div {
            font-size: 9.5px;
            font-weight: 700;
            color: #1e293b;
        }

        /* ══════════════════════════════════════════════
           PÁGINAS DE PRODUCTOS (GRILLA 6 EXACTA ESTILO VENSO)
           ══════════════════════════════════════════════ */
        .venso-prod-page {
            width: 297mm;
            height: 210mm;
            padding: 16mm 10mm 8mm 10mm;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            background: #ffffff;
        }
        
        .venso-products-grid {
            display: grid;
            grid-template-columns: repeat(3, 89mm);
            grid-template-rows: repeat(2, 88mm);
            gap: 5mm;
            width: 277mm;
            margin: auto 0;
        }

        .venso-prod-item {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            padding: 2mm 3mm;
            box-sizing: border-box;
            background: #ffffff;
            border: 1px solid #f1f5f9;
            border-radius: 4px;
        }

        .prod-header-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1mm;
        }
        .prod-title-group {
            display: flex;
            align-items: baseline;
            gap: 4px;
            flex-wrap: wrap;
            max-width: 62mm;
        }
        .prod-cat-tag {
            font-size: 8px;
            font-weight: 600;
            color: #64748b;
            text-transform: capitalize;
        }
        .prod-code-title {
            font-size: 10.5px;
            font-weight: 900;
            color: #0f172a;
            font-family: 'Montserrat', sans-serif;
            letter-spacing: 0.2px;
            text-transform: uppercase;
        }

        /* Swatches de colores estilo Venso (cuadraditos pequeños) */
        .prod-swatches {
            display: flex;
            align-items: center;
            gap: 2px;
            flex-wrap: wrap;
            justify-content: flex-end;
            max-width: 24mm;
        }
        .swatch-sq {
            width: 6.5px;
            height: 6.5px;
            border: 1px solid #94a3b8;
            box-sizing: border-box;
            display: inline-block;
        }

        /* Stamp Badge NUEVA */
        .badge-nueva {
            position: absolute;
            top: 7mm;
            right: 2mm;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            border: 1.5px solid #2563eb;
            color: #2563eb;
            font-size: 5.5px;
            font-weight: 900;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            line-height: 1;
            letter-spacing: 0.2px;
            transform: rotate(-12deg);
            z-index: 5;
            background: rgba(255,255,255,0.95);
        }

        .prod-image-wrap {
            height: 44mm;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1mm 2mm;
            margin: auto 0;
        }
        .prod-image-wrap img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            display: block;
        }

        .prod-bullets-list {
            list-style: none;
            padding: 0;
            margin: 0 0 1mm 0;
        }
        .prod-bullets-list li {
            font-size: 6.8px;
            color: #475569;
            line-height: 1.25;
            position: relative;
            padding-left: 6px;
            margin-bottom: 1.5px;
            font-weight: 500;
        }
        .prod-bullets-list li::before {
            content: "•";
            position: absolute;
            left: 0;
            color: #64748b;
            font-size: 7px;
        }

        .prod-footer-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 1.5px;
            border-top: 1px dotted #e2e8f0;
            margin-top: auto;
        }
        .prod-price-text {
            font-size: 8.5px;
            font-weight: 800;
            color: #d32f2f;
        }
        .prod-wa-btn {
            background: #16a34a;
            color: #ffffff;
            font-size: 6.5px;
            font-weight: 700;
            padding: 2px 5px;
            border-radius: 3px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 2px;
        }

        /* ══════════════════════════════════════════════
           PÁGINA: GUÍA ERGONÓMICA Y POSTURA (PÁG 22-23 VENSO)
           ══════════════════════════════════════════════ */
        .postura-page-wrap {
            width: 297mm;
            height: 210mm;
            padding: 16mm 14mm 10mm 14mm;
            box-sizing: border-box;
            display: grid;
            grid-template-columns: 135mm 125mm;
            gap: 12mm;
            position: relative;
            background: #ffffff;
        }
        .postura-left-col h2 {
            font-size: 16px;
            font-weight: 900;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            border-bottom: 2px solid #d32f2f;
            padding-bottom: 3px;
            display: inline-block;
        }
        .postura-left-col p {
            font-size: 9.5px;
            color: #334155;
            line-height: 1.5;
            margin-bottom: 10px;
        }
        .postura-guide-item {
            margin-bottom: 8px;
            border-left: 2px solid #cbd5e1;
            padding-left: 8px;
        }
        .postura-guide-item h4 {
            font-size: 10px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 2px;
        }
        .postura-guide-item p {
            font-size: 8.5px;
            color: #64748b;
            line-height: 1.35;
            margin: 0;
        }

        .postura-right-col {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
            text-align: center;
        }
        .postura-title-large {
            font-size: 20px;
            font-weight: 900;
            color: #0f172a;
            line-height: 1.15;
            margin-bottom: 4mm;
        }
        .postura-diagram-img {
            width: 100%;
            height: 110mm;
            object-fit: contain;
            margin: auto 0;
        }
        .postura-bottom-note {
            font-size: 8px;
            color: #64748b;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 4px 10px;
            border-radius: 4px;
            width: 100%;
            text-align: center;
        }

        /* ══════════════════════════════════════════════
           PÁGINA FINAL: CONTRAPORTADA CORPORATIVA
           ══════════════════════════════════════════════ */
        .contraportada-wrap {
            width: 297mm;
            height: 210mm;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: #ffffff;
            position: relative;
            padding: 20mm;
            box-sizing: border-box;
        }
        .cp-card-box {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            max-width: 550px;
        }
        .cp-logo-img {
            height: 26mm;
            width: auto;
            object-fit: contain;
            margin-bottom: 8mm;
        }
        .cp-company-title {
            font-size: 16px;
            font-weight: 900;
            color: #0f172a;
            letter-spacing: 2px;
            margin-bottom: 2px;
        }
        .cp-company-sub {
            font-size: 11px;
            font-weight: 600;
            color: #d32f2f;
            letter-spacing: 1px;
            margin-bottom: 10mm;
        }
        .cp-contact-grid {
            display: flex;
            flex-direction: column;
            gap: 4mm;
            width: 100%;
        }
        .cp-contact-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 13px;
            font-weight: 700;
            color: #1e293b;
            text-decoration: none;
        }
        .cp-contact-row i {
            color: #d32f2f;
            font-size: 15px;
            width: 20px;
            text-align: center;
        }

        /* ══════════════════════════════════════════════
           MEDIA PRINT EXACTO (297mm × 210mm SIN CORTES)
           ══════════════════════════════════════════════ */
        @media print {
            @page { 
                size: 297mm 210mm landscape; 
                margin: 0 !important; 
            }
            html, body { 
                background: #ffffff !important; 
                margin: 0 !important; 
                padding: 0 !important; 
                width: 297mm !important; 
                height: auto !important; 
                -webkit-print-color-adjust: exact !important; 
                print-color-adjust: exact !important; 
                overflow: visible !important; 
            }
            .toolbar, .no-print { 
                display: none !important; 
            }
            .catalog { 
                width: 297mm !important; 
                margin: 0 !important; 
                padding: 0 !important; 
                display: block !important; 
                overflow: visible !important; 
            }
            .page { 
                width: 297mm !important; 
                height: 209.5mm !important; 
                min-width: 297mm !important;
                max-width: 297mm !important;
                min-height: 209.5mm !important;
                max-height: 209.5mm !important;
                box-shadow: none !important; 
                border: none !important; 
                margin: 0 !important; 
                page-break-after: always !important; 
                break-after: page !important;
                page-break-inside: avoid !important; 
                break-inside: avoid !important;
                overflow: hidden !important;
            }
        }
    </style>
</head>
<body>

<!-- BARRA DE CONTROL WEB (NO SALE EN PDF/IMPRESIÓN) -->
<div class="toolbar no-print">
    <div style="display:flex; align-items:center; gap:8px; font-size:13px; font-weight:800; color:#0f172a;">
        <i class="fas fa-book-open" style="color:#d32f2f;"></i>
        <span>Catálogo Oficial:</span>
    </div>
    
    <form method="GET" action="catalogo_pdf.php" style="display:flex; align-items:center; gap:6px;">
        <select name="categoria_id" onchange="this.form.submit()">
            <option value="0">-- Catálogo Completo (<?= count($all_products) ?> Productos) --</option>
            <?php foreach ($categorias_lista as $cl): ?>
            <option value="<?= $cl['id'] ?>" <?= $categoria_id === intval($cl['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($cl['nombre']) ?> (<?= $cl['total_prods'] ?> productos)
            </option>
            <?php endforeach; ?>
        </select>
    </form>

    <button type="button" class="btn-red" onclick="window.print()">
        <i class="fas fa-print"></i> Guardar PDF / Imprimir (<?= $total_pages ?> Págs)
    </button>
    <a href="catalogos.php" class="btn-outline">
        <i class="fas fa-arrow-left"></i> Volver al Sistema
    </a>
</div>

<div class="catalog">

    <!-- ═══════════════════════════════════════════
         PÁGINA 1: PORTADA ESTILO EXACTO VENSO
         ═══════════════════════════════════════════ -->
    <div class="page">
        <div class="cover-container">
            <!-- Imagen de Fondo Arquitectónica de Alta Calidad -->
            <img class="cover-bg-image" src="/carpicenter_sys/assets/img/portada_studio_clean.png" alt="Carpicenter Mobiliario">
            <div class="cover-overlay-gradient"></div>

            <!-- Logotipo Oficial Carpicenter en la esquina superior derecha -->
            <div class="cover-top-logo">
                <img src="/carpicenter_sys/assets/img/logo_carpicenter_full_official.png" alt="Industrias Carpicenter">
            </div>

            <!-- Tarjeta Gris Vertical Característica de la Referencia -->
            <div class="cover-grey-card">
                <div></div>
                
                <div class="cover-card-inner">
                    <div class="cover-tagline-wrap">
                        <div class="line"></div>
                        <div class="cover-tagline-text">CATÁLOGO</div>
                        <div class="line"></div>
                    </div>
                    <div class="cover-big-year">2026</div>
                </div>

                <div class="cover-card-footer">
                    <a href="https://www.carpicenter.com.pe" class="cover-card-url" target="_blank">www.carpicenter.com.pe</a>
                    <div class="cover-card-socials">
                        <span><i class="fab fa-instagram"></i> @carpicenterperu</span>
                        <span><i class="fab fa-facebook"></i> carpicenterperu</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════
         PÁGINA 2: NOSOTROS / CORPORATIVA EXACTA VENSO
         ═══════════════════════════════════════════ -->
    <div class="page">
        <div class="venso-header">
            <img class="header-logo" src="/carpicenter_sys/assets/img/logo_carpicenter_full_official.png" alt="Carpicenter">
            <span class="header-pagenum">02</span>
        </div>

        <span class="ref-watermark ref-watermark-top">• FOTOS REFERENCIALES</span>

        <div class="nosotros-page-wrap">
            <div></div> <!-- Espacio para header -->

            <!-- Fila Superior: Camiones/Despacho y Texto de Presentación -->
            <div class="nosotros-top-grid">
                <img class="nosotros-fleet-img" src="/carpicenter_sys/assets/img/portada_tienda.png" alt="Flota Carpicenter">
                <div class="nosotros-top-texts">
                    <p>Llevamos más de 20 años ayudando a personas y empresas a encontrar el mobiliario perfecto. Somos fabricantes e importadores directos, lo que nos permite ofrecerte calidad, diseño y confort al mejor precio.</p>
                    <p>Tenemos una gran variedad de modelos ergonómicos y modernos, ideales para oficinas, espacios de trabajo, restaurantes, cafeterías, universidades y cualquier lugar donde la comodidad importa.</p>
                </div>
            </div>

            <!-- Fila Inferior: Manos/Acabados y 4 Pilares con Líneas -->
            <div class="nosotros-bottom-grid">
                <img class="nosotros-hands-img" src="/carpicenter_sys/assets/img/quienes_somos_ref.png" alt="Acabados Carpicenter">
                
                <div class="nosotros-pillars-box">
                    <div class="nosotros-pillars-intro">Te acompañamos en todo el proceso:</div>
                    
                    <div class="pillar-row">
                        <div class="pillar-row-text">Todas nuestras sillas y muebles tienen garantía de 1 a 2 años.</div>
                    </div>
                    <div class="pillar-row">
                        <div class="pillar-row-text">Envíos y despachos seguros a todo el Perú.</div>
                    </div>
                    <div class="pillar-row">
                        <div class="pillar-row-text">Asesoría personalizada, para ayudarte a elegir la mejor opción.</div>
                    </div>
                    <div class="pillar-row">
                        <div class="pillar-row-text">Personalizamos tu mueble con el color o tapizado que combine con tu marca.</div>
                    </div>
                </div>
            </div>

            <div></div>
        </div>

        <span class="ref-watermark ref-watermark-bottom">• FOTOS REFERENCIALES</span>
    </div>

    <!-- ═══════════════════════════════════════════
         SECCIONES POR CATEGORÍA (SPREADS Y GRILLAS)
         ═══════════════════════════════════════════ -->
    <?php foreach ($category_sections as $csec): ?>
        
        <!-- Separador de Categoría / Portadilla Spread con Título Vertical en 90° -->
        <div class="page">
            <div class="venso-header">
                <img class="header-logo" src="/carpicenter_sys/assets/img/logo_carpicenter_full_official.png" alt="Carpicenter">
                <span class="header-pagenum"><?= str_pad($csec['intro_page'], 2, '0', STR_PAD_LEFT) ?></span>
            </div>

            <span class="ref-watermark ref-watermark-bottom">• FOTOS REFERENCIALES</span>

            <div class="cat-spread-container">
                <!-- Lado Izquierdo: Foto Interior + Título Vertical Rotado -->
                <div class="cat-spread-left">
                    <img class="cat-spread-img" src="<?= htmlspecialchars($csec['intro_image']) ?>" alt="<?= htmlspecialchars($csec['title']) ?>">
                    <div class="cat-spread-vertical-title"><?= htmlspecialchars(strtolower($csec['title'])) ?></div>
                </div>

                <!-- Lado Derecho: Descripción y Destacados de Línea -->
                <div class="cat-spread-right">
                    <div>
                        <div class="spread-right-header">
                            <div>
                                <span style="font-size:10px; font-weight:800; color:#d32f2f; letter-spacing:2px; text-transform:uppercase;">COLECCIÓN 2026</span>
                                <h2 style="font-size:24px; font-weight:900; color:#0f172a; margin-top:2px;">LÍNEA DE <?= htmlspecialchars($csec['title']) ?></h2>
                            </div>
                            <span style="background:#0f172a; color:#fff; font-size:10px; font-weight:800; padding:4px 10px; border-radius:12px;">
                                <?= $csec['count'] ?> MODELOS
                            </span>
                        </div>
                        <p class="spread-right-desc"><?= htmlspecialchars($csec['desc']) ?></p>

                        <div class="spread-highlight-grid">
                            <div class="spread-highlight-card">
                                <i class="fas fa-shield-alt"></i>
                                <div>Garantía de Fábrica 1 a 2 Años</div>
                            </div>
                            <div class="spread-highlight-card">
                                <i class="fas fa-palette"></i>
                                <div>Variedad de Colores & Tapices</div>
                            </div>
                            <div class="spread-highlight-card">
                                <i class="fas fa-truck-moving"></i>
                                <div>Envíos a Todo el Perú</div>
                            </div>
                            <div class="spread-highlight-card">
                                <i class="fas fa-award"></i>
                                <div>Estructura de Alto Tránsito</div>
                            </div>
                        </div>
                    </div>

                    <div style="border-top:1px solid #e2e8f0; padding-top:4mm; display:flex; justify-content:space-between; align-items:center; font-size:9px; color:#64748b; font-weight:600;">
                        <span>INDUSTRIAS CARPICENTER®</span>
                        <span>DISEÑO & CONFORT PERUANO</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Páginas de Grilla de Productos (6 Productos Exactos por Página) -->
        <?php foreach ($csec['prod_pages'] as $pp): ?>
        <div class="page venso-prod-page">
            <div class="venso-header">
                <img class="header-logo" src="/carpicenter_sys/assets/img/logo_carpicenter_full_official.png" alt="Carpicenter">
                <span class="header-pagenum"><?= str_pad($pp['page_num'], 2, '0', STR_PAD_LEFT) ?></span>
            </div>

            <span class="ref-watermark ref-watermark-top">• FOTOS REFERENCIALES</span>

            <div class="venso-products-grid">
                <?php foreach ($pp['items'] as $item):
                    $img = !empty($item['imagen_url']) ? $item['imagen_url'] : '/carpicenter_sys/assets/img/logo_bird_clean.png';
                    $name = $item['nombre'] ?? 'Producto';
                    $cat_label = ucfirst(strtolower($csec['title']));
                    $code = !empty($item['codigo']) ? $item['codigo'] : sprintf("CA-%04d", $item['id']);
                    $precio = floatval($item['precio_venta'] ?? 0);
                    $precio_txt = $precio > 0 ? formatearMonto($precio) : 'A COTIZAR';
                    
                    $cols = $colores_por_producto[$item['id']] ?? ['Blanco', 'Negro', 'Gris Oscuro', 'Rojo'];
                    if (empty($cols)) $cols = ['Blanco', 'Negro', 'Gris Oscuro', 'Rojo'];
                    
                    $bullets = getProductTechnicalBullets($item);
                    $wa_msg = "Hola Carpicenter, deseo cotizar el producto " . $name . " (" . $code . ") del catálogo.";
                ?>
                <div class="venso-prod-item">
                    <!-- Fila Superior: Categoría + Nombre + Swatches de Colores -->
                    <div class="prod-header-row">
                        <div class="prod-title-group">
                            <span class="prod-cat-tag"><?= htmlspecialchars($cat_label) ?></span>
                            <span class="prod-code-title"><?= htmlspecialchars($name) ?></span>
                        </div>

                        <div class="prod-swatches">
                            <?php foreach (array_slice($cols, 0, 5) as $cname): 
                                $hex = getCssColorHex($cname);
                            ?>
                                <span class="swatch-sq" style="background: <?= $hex ?>;" title="<?= htmlspecialchars($cname) ?>"></span>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <?php if (!empty($item['destacado_catalogo'])): ?>
                        <div class="badge-nueva">NUEVA</div>
                    <?php endif; ?>

                    <!-- Foto del Producto -->
                    <div class="prod-image-wrap">
                        <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($name) ?>">
                    </div>

                    <!-- Viñetas Técnicas Estilo Venso -->
                    <div>
                        <ul class="prod-bullets-list">
                            <?php foreach ($bullets as $b): ?>
                                <li><?= htmlspecialchars($b) ?></li>
                            <?php endforeach; ?>
                        </ul>

                        <div class="prod-footer-row">
                            <span class="prod-price-text"><?= $precio_txt ?></span>
                            <a href="https://wa.me/51927961032?text=<?= urlencode($wa_msg) ?>" class="prod-wa-btn" target="_blank">
                                <i class="fab fa-whatsapp"></i> Cotizar
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <span class="ref-watermark ref-watermark-bottom">• FOTOS REFERENCIALES</span>
        </div>
        <?php endforeach; ?>

    <?php endforeach; ?>

    <!-- ═══════════════════════════════════════════
         PÁGINA: GUÍA ERGONÓMICA Y CONSIDERACIONES DE POSTURA
         ═══════════════════════════════════════════ -->
    <div class="page">
        <div class="venso-header">
            <img class="header-logo" src="/carpicenter_sys/assets/img/logo_carpicenter_full_official.png" alt="Carpicenter">
            <span class="header-pagenum"><?= str_pad($page_postura, 2, '0', STR_PAD_LEFT) ?></span>
        </div>

        <span class="ref-watermark ref-watermark-top">• FOTOS REFERENCIALES</span>

        <div class="postura-page-wrap">
            <div class="postura-left-col">
                <h2>¿Cómo ajustar tu silla y mobiliario?</h2>
                <p>Ajustar correctamente tu mobiliario de oficina y hogar es esencial para cuidar tu salud postural y maximizar tu rendimiento diario.</p>

                <div class="postura-guide-item">
                    <h4>1. Altura del asiento y apoyo de pies</h4>
                    <p>Regula la altura de la silla de modo que tus pies descansen planos sobre el suelo y tus rodillas formen un ángulo recto de 90°.</p>
                </div>

                <div class="postura-guide-item">
                    <h4>2. Soporte lumbar y curvatura natural</h4>
                    <p>El soporte lumbar debe alinearse exactamente con la curva inferior de tu espalda para evitar sobrecargas musculares.</p>
                </div>

                <div class="postura-guide-item">
                    <h4>3. Apoyabrazos y altura de mesa</h4>
                    <p>Los brazos deben descansar relajados con los codos a 90°, paralelos a la superficie de trabajo o teclado.</p>
                </div>

                <div class="postura-guide-item">
                    <h4>4. Profundidad del asiento</h4>
                    <p>Debe existir un espacio libre de 3 a 5 centímetros entre la parte frontal del asiento y la parte posterior de tus rodillas.</p>
                </div>
            </div>

            <div class="postura-right-col">
                <div class="postura-title-large">Consideraciones para una buena postura.</div>
                <img class="postura-diagram-img" src="/carpicenter_sys/assets/img/portada_main_chairs.png" alt="Ergonomía Carpicenter">
                <div class="postura-bottom-note">
                    <strong>Industrias Carpicenter:</strong> Mobiliario diseñado bajo estándares ergonómicos internacionales.
                </div>
            </div>
        </div>

        <span class="ref-watermark ref-watermark-bottom">• FOTOS REFERENCIALES</span>
    </div>

    <!-- ═══════════════════════════════════════════
         PÁGINA FINAL: CONTRAPORTADA CORPORATIVA
         ═══════════════════════════════════════════ -->
    <div class="page">
        <div class="contraportada-wrap">
            <div class="cp-card-box">
                <img class="cp-logo-img" src="/carpicenter_sys/assets/img/logo_carpicenter_full_official.png" alt="Industrias Carpicenter">
                <div class="cp-company-title">INDUSTRIAS CARPICENTER S.A.C.</div>
                <div class="cp-company-sub">FABRICACIÓN & IMPORTACIÓN DE MOBILIARIO PROFESIONAL</div>

                <div class="cp-contact-grid">
                    <a href="https://wa.me/51927961032" class="cp-contact-row" target="_blank">
                        <i class="fab fa-whatsapp"></i>
                        <span>(+51) 927 961 032 / 921 213 016</span>
                    </a>
                    <a href="https://www.carpicenter.com.pe" class="cp-contact-row" target="_blank">
                        <i class="fas fa-globe"></i>
                        <span>www.carpicenter.com.pe</span>
                    </a>
                    <div class="cp-contact-row">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>Lima — Perú | Envíos a todo el territorio nacional</span>
                    </div>
                    <div class="cp-contact-row">
                        <i class="fab fa-facebook"></i>
                        <span>Facebook: Carpicenterperu</span>
                    </div>
                    <div class="cp-contact-row">
                        <i class="fab fa-instagram"></i>
                        <span>Instagram: @carpicenterperu</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

</body>
</html>
