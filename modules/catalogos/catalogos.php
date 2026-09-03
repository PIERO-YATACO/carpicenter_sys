<?php
require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../config/db.php';

$page_title = 'Catálogo Digital';
$page_subtitle = 'Visualización y gestión de productos en catálogo';

// Parámetros de filtrado
$search = trim($_GET['search'] ?? '');
$categoria_id = isset($_GET['categoria_id']) ? (int)$_GET['categoria_id'] : 0;

// Obtener Categorías
$stmt_cats = $db->query("SELECT * FROM categorias ORDER BY UPPER(nombre) ASC");
$categorias = $stmt_cats->fetchAll(PDO::FETCH_ASSOC);

// Consulta de productos
$where = ["(p.en_catalogo IS NOT FALSE)"];
$params = [];

if (!empty($search)) {
    $where[] = "(p.nombre ILIKE :search OR p.codigo ILIKE :search)";
    $params[':search'] = "%$search%";
}
if ($categoria_id > 0) {
    $where[] = "p.categoria_id = :cat";
    $params[':cat'] = $categoria_id;
}

$where_sql = "WHERE " . implode(" AND ", $where);

$sql = "SELECT p.*, c.nombre as categoria_nombre,
        COALESCE(NULLIF(p.imagen_url, ''), (SELECT imagen_url FROM producto_colores WHERE producto_id = p.id AND imagen_url IS NOT NULL AND imagen_url != '' LIMIT 1)) AS img_display
        FROM productos p 
        LEFT JOIN categorias c ON p.categoria_id = c.id 
        $where_sql 
        ORDER BY c.nombre ASC, p.destacado_catalogo DESC, p.nombre ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$catalog_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Mapear colores por producto
$prod_ids = array_column($catalog_items, 'id');
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

// Agrupar por categoría para el visor web
$items_por_cat = [];
foreach ($catalog_items as $item) {
    $cn = $item['categoria_nombre'] ?? 'Otros';
    if (!isset($items_por_cat[$cn])) $items_por_cat[$cn] = [];
    $items_por_cat[$cn][] = $item;
}

function getCssColorHexWeb($nombre) {
    $n = strtoupper(trim($nombre));
    $map = [
        'BLANCO' => '#FFFFFF', 'NEGRO' => '#111827', 'TAUPE' => '#877B73', 'TORTORA(TAUPE)' => '#877B73',
        'ROJO' => '#D32F2F', 'AMARILLO' => '#EAB308', 'AMARILLO OSCURO' => '#CA8A04', 'VERDE LIMON' => '#84CC16', 
        'VERDE PASTEL' => '#86EFAC', 'CELESTE' => '#38BDF8', 'GRIS OSCURO' => '#475569', 'GRIS CLARO' => '#CBD5E1', 
        'AZUL' => '#2563EB', 'DUNA' => '#D2B48C', 'MARRON' => '#78350F', 'MARRON (VIDRIO)' => '#78350F', 
        'ROSADO' => '#F472B6', 'VERDE' => '#16A34A', 'NARANJA' => '#EA580C', 'ROBLE' => '#A16207', 
        'CEDRO' => '#854D0E', 'WENGUE' => '#3B2F2F', 'CAOBA' => '#6A1B1A',
        'TURQUESA CLARO' => '#358588', 'TURQUESA OSCURO' => '#275C6B', 'JADE' => '#D2F0E0', 'PANELA' => '#C89D66'
    ];
    return $map[$n] ?? '#94A3B8';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo Digital 2026 — Industrias Carpicenter</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="/carpicenter_sys/assets/css/styles.css">
    <style>
        .cat-wrapper { background: #f8fafc; min-height: 100vh; padding: 1.5rem; font-family: 'Montserrat', sans-serif; }
        
        /* Top Header */
        .cat-top-bar { background: #fff; border-radius: 12px; padding: 1.2rem 1.8rem; margin-bottom: 1.5rem; border: 1px solid #e2e8f0; box-shadow: 0 2px 6px rgba(0,0,0,.03); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; }
        .cat-top-bar .left { display: flex; align-items: center; gap: 1.2rem; }
        .cat-top-bar .left img { height: 44px; width: auto; }
        .cat-top-bar .left h1 { font-size: 1.35rem; font-weight: 900; color: #0f172a; margin: 0; }
        .cat-top-bar .left p { font-size: .85rem; color: #64748b; margin: 0; }
        
        .cat-btn-actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .btn-pdf-dl { background: #d32f2f; color: #fff; padding: .75rem 1.6rem; border-radius: 8px; font: 800 .88rem/1 'Montserrat', sans-serif; text-decoration: none; display: inline-flex; align-items: center; gap: .6rem; box-shadow: 0 4px 14px rgba(211,47,47,.25); transition: background .2s, transform .2s; }
        .btn-pdf-dl:hover { background: #b71c1c; transform: translateY(-1px); color: #fff; }

        /* Filtros */
        .cat-filters { background: #fff; border-radius: 12px; padding: 1rem 1.4rem; margin-bottom: 2rem; border: 1px solid #e2e8f0; display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; }
        .cat-filters input { flex: 1; min-width: 220px; padding: .65rem 1rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: .88rem; font-family: 'Montserrat', sans-serif; }
        .cat-filters input:focus { outline: none; border-color: #d32f2f; box-shadow: 0 0 0 3px rgba(211,47,47,.12); }
        .cat-filters select { padding: .65rem 1rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: .88rem; background: #fff; font-family: 'Montserrat', sans-serif; }

        /* Secciones por categoría */
        .cat-section { margin-bottom: 2.5rem; }
        .cat-section-hdr { display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid #e2e8f0; padding-bottom: .6rem; margin-bottom: 1.2rem; }
        .cat-section-title { font-size: 1.25rem; font-weight: 900; color: #0f172a; display: flex; align-items: center; gap: .6rem; }
        .cat-section-title .bar-red { width: 4px; height: 20px; background: #d32f2f; border-radius: 2px; }
        .cat-badge-count { background: #f1f5f9; color: #475569; font-size: .78rem; font-weight: 800; padding: 4px 10px; border-radius: 12px; border: 1px solid #e2e8f0; }

        /* Grid limpia de tarjetas */
        .cat-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 1.2rem; }

        /* Tarjeta de producto estilo Venso */
        .cat-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; display: flex; flex-direction: column; transition: box-shadow .2s, transform .2s; position: relative; }
        .cat-card:hover { box-shadow: 0 10px 25px rgba(0,0,0,.08); transform: translateY(-2px); }
        
        .cat-card .img-box { height: 180px; background: #f8fafc; display: flex; align-items: center; justify-content: center; padding: 12px; position: relative; border-bottom: 1px solid #f1f5f9; }
        .cat-card .img-box img { max-width: 100%; max-height: 100%; object-fit: contain; }
        
        .cat-card .code-tag { position: absolute; top: 8px; left: 8px; background: #0f172a; color: #fff; font-size: .65rem; font-weight: 800; padding: 3px 7px; border-radius: 4px; letter-spacing: .5px; }
        .cat-card .cat-tag { position: absolute; top: 8px; right: 8px; background: rgba(255,255,255,.95); font-size: .65rem; font-weight: 700; padding: 3px 7px; border-radius: 4px; color: #475569; border: 1px solid #e2e8f0; }
        
        .cat-card .body { padding: 1rem; display: flex; flex-direction: column; flex: 1; justify-content: space-between; }
        .cat-card .body h3 { font-size: .95rem; font-weight: 900; color: #0f172a; margin-bottom: .4rem; line-height: 1.25; }
        
        /* Swatches */
        .card-swatches { display: flex; align-items: center; gap: 3px; margin-bottom: .6rem; }
        .card-swatches .swatch { width: 10px; height: 10px; border: 1px solid #cbd5e1; border-radius: 2px; display: inline-block; }
        
        .cat-card .body .comp { font-size: .75rem; color: #64748b; line-height: 1.35; margin-bottom: .8rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        
        .cat-card .foot { margin-top: auto; padding-top: .6rem; border-top: 1px dotted #e2e8f0; display: flex; align-items: center; justify-content: space-between; }
        .cat-card .price { font-size: .95rem; font-weight: 900; color: #d32f2f; }
        .cat-card .btn-wa { background: #16a34a; color: #fff; font-size: .72rem; font-weight: 800; padding: 4px 8px; border-radius: 4px; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }
        .cat-card .btn-wa:hover { background: #15803d; color: #fff; }
    </style>
</head>
<body>
<div class="app-wrapper">
    <?php include __DIR__ . '/../../views/partials/sidebar.php'; ?>

    <div class="main-content">
        <?php include __DIR__ . '/../../views/partials/header.php'; ?>

        <div class="cat-wrapper">

            <!-- Top Header Bar -->
            <div class="cat-top-bar">
                <div class="left">
                    <img src="/carpicenter_sys/assets/img/logo_carpicenter_full_official.png" alt="Carpicenter">
                    <div>
                        <h1>Catálogo Digital Oficial 2026</h1>
                        <p>Visualización y exportación editorial de productos Industrias Carpicenter</p>
                    </div>
                </div>
                <div class="cat-btn-actions">
                    <a href="catalogo_pdf.php<?= $categoria_id > 0 ? '?categoria_id=' . $categoria_id : '' ?>" target="_blank" class="btn-pdf-dl">
                        <i class="fas fa-file-pdf"></i> <?= $categoria_id > 0 ? 'Exportar Categoría en PDF' : 'Exportar Catálogo Oficial (PDF)' ?>
                    </a>
                </div>
            </div>

            <!-- Filtros de búsqueda -->
            <form class="cat-filters" method="GET">
                <input type="text" name="search" placeholder="Buscar producto por nombre..." value="<?= htmlspecialchars($search) ?>">
                <select name="categoria_id" onchange="this.form.submit()">
                    <option value="0">Todas las Categorías</option>
                    <?php foreach($categorias as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $categoria_id == $cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-search"></i> Filtrar</button>
            </form>

            <!-- Categorías y productos -->
            <?php if (empty($items_por_cat)): ?>
                <div style="text-align:center; padding:4rem 2rem; background:#fff; border-radius:12px; border:1px solid #e2e8f0;">
                    <i class="fas fa-box-open" style="font-size:2.5rem; color:#94a3b8; margin-bottom:1rem;"></i>
                    <h3 style="color:#0f172a; margin-bottom:.3rem;">No se encontraron productos</h3>
                    <p style="color:#64748b;">Intenta cambiar los criterios de búsqueda o categoría.</p>
                </div>
            <?php endif; ?>

            <?php foreach ($items_por_cat as $cat_name => $items): ?>
            <div class="cat-section">
                <div class="cat-section-hdr">
                    <div class="cat-section-title">
                        <span class="bar-red"></span>
                        <?= htmlspecialchars($cat_name) ?>
                    </div>
                    <span class="cat-badge-count"><?= count($items) ?> productos</span>
                </div>

                <div class="cat-grid">
                    <?php foreach ($items as $item):
                        $img_url = !empty($item['img_display']) ? $item['img_display'] : '/carpicenter_sys/assets/img/logo_bird_clean.png';
                        $precio = floatval($item['precio_venta'] ?? 0);
                        $precio_txt = $precio > 0 ? formatearMonto($precio) : 'A COTIZAR';
                        $code = !empty($item['codigo']) ? $item['codigo'] : sprintf("CA-%04d", $item['id']);
                        $cols = $colores_por_producto[$item['id']] ?? ['Blanco', 'Negro', 'Roble', 'Taupe'];
                        $wa_msg = "Hola Carpicenter, deseo consultar por el producto " . $item['nombre'] . " (" . $code . ") del catálogo.";
                    ?>
                    <div class="cat-card">
                        <div class="img-box">
                            <span class="code-tag"><?= $code ?></span>
                            <span class="cat-tag"><?= htmlspecialchars($cat_name) ?></span>
                            <img src="<?= htmlspecialchars($img_url) ?>" alt="<?= htmlspecialchars($item['nombre']) ?>">
                        </div>
                        <div class="body">
                            <div>
                                <h3><?= htmlspecialchars($item['nombre']) ?></h3>
                                
                                <div class="card-swatches">
                                    <span style="font-size:10px; font-weight:700; color:#64748b; margin-right:4px;">Colores:</span>
                                    <?php foreach(array_slice($cols, 0, 5) as $cn): ?>
                                        <span class="swatch" style="background: <?= getCssColorHexWeb($cn) ?>;" title="<?= htmlspecialchars($cn) ?>"></span>
                                    <?php endforeach; ?>
                                </div>

                                <div class="comp">
                                    <?= htmlspecialchars($item['descripcion_corta'] ?? 'Diseño vanguardista de alta durabilidad y ergonomía superior.') ?>
                                </div>
                            </div>

                            <div class="foot">
                                <span class="price"><?= $precio_txt ?></span>
                                <a href="https://wa.me/51927961032?text=<?= urlencode($wa_msg) ?>" target="_blank" class="btn-wa">
                                    <i class="fab fa-whatsapp"></i> Cotizar
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>

        </div>
    </div>
</div>
</body>
</html>
