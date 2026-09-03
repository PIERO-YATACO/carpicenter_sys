<?php
require_once __DIR__ . '/../auth/check_auth.php';
require_once '../config/db.php';

$page_title = 'Productos'; 
$page_subtitle = 'Catálogo maestro de modelos, variantes de color y precios de venta'; 

// Función para obtener color CSS basado en el nombre
function getCssColor($nombre) {
    $n = strtoupper(trim($nombre));
    $map = [
        'BLANCO' => '#FFFFFF', 
        'NEGRO' => '#111827', 
        'TAUPE' => '#877B73', 
        'ROJO' => '#D32F2F',
        'AMARILLO' => '#EAB308', 
        'AMARILLO OSCURO' => '#CA8A04',
        'VERDE LIMON' => '#84CC16', 
        'VERDE PASTEL' => '#86EFAC',
        'CELESTE' => '#38BDF8', 
        'GRIS OSCURO' => '#475569', 
        'GRIS CLARO' => '#CBD5E1',
        'AZUL' => '#2563EB', 
        'DUNA' => '#D2B48C', 
        'MARRON' => '#78350F',
        'MARRON (VIDRIO)' => '#78350F',
        'ROSADO' => '#F472B6', 
        'VERDE' => '#16A34A', 
        'NARANJA' => '#EA580C',
        'TORTORA(TAUPE)' => '#877B73', 
        'TURQUESA CLARO' => '#358588', // Tono turquesa medio / teal claro de muestra real
        'TURQUESA OSCURO' => '#275C6B', // Azul petróleo / Teal oscuro de muestra real
        'JADE' => '#D2F0E0', // Pastel Jade exacto (#d2f0e0)
        'PANELA' => '#C89D66',
        'MULTICOLOR' => 'linear-gradient(135deg, #ef4444, #f59e0b, #10b981, #3b82f6, #8b5cf6)',
        'VIDRIO TRANSPARENTE' => 'linear-gradient(135deg, #e0f2fe, #bae6fd)',
        'VIDRIO PAVONADO' => 'linear-gradient(135deg, #f1f5f9, #94a3b8)',
        'VIDRIO TEMPLADO' => 'linear-gradient(135deg, #dbeafe, #93c5fd)',
        'VIDRIO NEGRO' => 'linear-gradient(135deg, #334155, #0f172a)',
        'VIDRIO BRONCE' => 'linear-gradient(135deg, #b45309, #78350f)',
        'ESTÁNDAR (SIN COLOR)' => '#64748b'
    ];
    return $map[$n] ?? '#CCCCCC';
}

// Procesar eliminación (Solo Admin)
if(isset($_POST['delete_id'])) {
    if (!$is_admin) {
        header("Location: productos.php?error=" . urlencode("Acceso denegado: Solo los administradores pueden eliminar productos."));
        exit;
    }
    $del_id = (int)$_POST['delete_id'];
    try {
        $db->beginTransaction();
        $db->prepare("DELETE FROM inventario_local WHERE producto_id = ?")->execute([$del_id]);
        $db->prepare("DELETE FROM producto_colores WHERE producto_id = ?")->execute([$del_id]);
        $db->prepare("DELETE FROM productos WHERE id = ?")->execute([$del_id]);
        $db->commit();
        header("Location: productos.php?msg=deleted");
        exit;
    } catch(Exception $e) {
        if($db->inTransaction()) $db->rollBack();
        header("Location: productos.php?error=" . urlencode("No se puede eliminar este producto porque está vinculado a comprobantes o cotizaciones registradas."));
        exit;
    }
}

// Parámetros de búsqueda, categoría y paginación
$search = $_GET['search'] ?? '';
$categoria_id = isset($_GET['categoria_id']) ? (int)$_GET['categoria_id'] : 0;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

// Obtener todas las categorías para el filtro
$stmt_cats = $db->query("SELECT * FROM categorias ORDER BY UPPER(nombre) ASC");
$categorias_list = $stmt_cats->fetchAll(PDO::FETCH_ASSOC);

// Construir condición de búsqueda dinámica
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(p.nombre ILIKE ? OR p.codigo ILIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($categoria_id > 0) {
    $where_conditions[] = "p.categoria_id = ?";
    $params[] = $categoria_id;
}

$where_clause = "";
if (count($where_conditions) > 0) {
    $where_clause = " WHERE " . implode(" AND ", $where_conditions);
}

// Obtener el total de productos para la paginación
$sql_total = "SELECT COUNT(*) FROM productos p " . $where_clause;
$stmt_total = $db->prepare($sql_total);
$stmt_total->execute($params);
$total_productos = $stmt_total->fetchColumn();
$total_pages = ceil($total_productos / $limit);

// Estadísticas Globales
$stats_total_stock = floatval($db->query("SELECT COALESCE(SUM(stock_actual), 0) FROM inventario_local")->fetchColumn());
$stats_total_categorias = count($categorias_list);

// Obtener los productos ordenados
$query = "SELECT p.*, c.nombre as categoria_nombre,
          COALESCE((SELECT SUM(stock_actual) FROM inventario_local WHERE producto_id = p.id), (SELECT SUM(stock) FROM producto_colores WHERE producto_id = p.id), 0) as stock_total
          FROM productos p 
          LEFT JOIN categorias c ON p.categoria_id = c.id 
          $where_clause
          ORDER BY p.nombre ASC 
          LIMIT $limit OFFSET $offset";
$stmt = $db->prepare($query);
$stmt->execute($params);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener lista de sedes/locales
$stmt_locales = $db->query("SELECT id, nombre, tipo FROM locales ORDER BY id ASC");
$locales_list = $stmt_locales->fetchAll(PDO::FETCH_ASSOC);

// Obtener el detalle de colores y stock por variante
$prod_ids = array_column($productos, 'id');
$colores_por_producto = [];
if(count($prod_ids) > 0) {
    $ids_str = implode(',', $prod_ids);
    
    // Obtener desglose por sede para cada variante
    $stock_por_local = [];
    $q_inv = $db->query("SELECT producto_id, color_id, local_id, stock_actual FROM inventario_local WHERE producto_id IN ($ids_str)");
    while($inv = $q_inv->fetch(PDO::FETCH_ASSOC)) {
        $stock_por_local[$inv['producto_id']][$inv['color_id']][$inv['local_id']] = floatval($inv['stock_actual']);
    }

    $qc = $db->query("
        SELECT 
            active.producto_id, 
            active.color_id,
            c.nombre, 
            c.codigo as codigo_color,
            pc.codigo as codigo_variante,
            COALESCE(
                (SELECT SUM(stock_actual) FROM inventario_local WHERE producto_id = active.producto_id AND color_id = c.id),
                pc.stock, 
                0
            ) as stock, 
            pc.imagen_url 
        FROM colores c
        JOIN (
            SELECT producto_id, color_id FROM producto_colores WHERE producto_id IN ($ids_str)
            UNION
            SELECT producto_id, color_id FROM inventario_local WHERE producto_id IN ($ids_str) AND stock_actual > 0
        ) active ON active.color_id = c.id
        LEFT JOIN producto_colores pc ON pc.producto_id = active.producto_id AND pc.color_id = c.id
        ORDER BY c.nombre ASC
    ");
    while($row = $qc->fetch(PDO::FETCH_ASSOC)) {
        $row['css_color'] = getCssColor($row['nombre']);
        $row['stock'] = floatval($row['stock']);
        $row['locales_stock'] = $stock_por_local[$row['producto_id']][$row['color_id']] ?? [];
        $colores_por_producto[$row['producto_id']][] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> — Carpicenter</title>
    <link rel="stylesheet" href="/carpicenter_sys/assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* Desactivar flechas / triangulitos en inputs numéricos */
        input[type=number]::-webkit-inner-spin-button, 
        input[type=number]::-webkit-outer-spin-button { 
            -webkit-appearance: none; 
            margin: 0; 
        }
        input[type=number] { 
            -moz-appearance: textfield; 
            appearance: textfield;
        }

        /* ===== PRODUCTOS PREMIUM ===== */
        .prd-hero {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.4rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .prd-hero-title h1 {
            font-size: 1.45rem;
            font-weight: 800;
            color: #111827;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .prd-hero-title p {
            color: #6B7280;
            font-size: 0.84rem;
            margin: 0.25rem 0 0 0;
        }

        /* KPIs */
        .prd-kpis-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.2rem;
            margin-bottom: 1.3rem;
        }
        .prd-kpi-card {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 14px;
            padding: 1.15rem 1.35rem;
            display: flex;
            align-items: center;
            gap: 1.1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
            transition: transform 0.2s ease;
        }
        .prd-kpi-card:hover {
            transform: translateY(-2px);
        }
        .prd-kpi-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }
        .icon-indigo-bg { background: linear-gradient(135deg, rgba(79,70,229,0.12) 0%, rgba(99,102,241,0.2) 100%); color: #4F46E5; }
        .icon-emerald-bg { background: linear-gradient(135deg, rgba(5,150,105,0.12) 0%, rgba(16,185,129,0.2) 100%); color: #059669; }
        .icon-blue-bg { background: linear-gradient(135deg, rgba(37,99,235,0.12) 0%, rgba(59,130,246,0.2) 100%); color: #2563EB; }

        .prd-kpi-info span.label {
            font-size: 0.74rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
            color: #6B7280;
            display: block;
            margin-bottom: 0.15rem;
        }
        .prd-kpi-info h3 {
            font-size: 1.35rem;
            font-weight: 800;
            color: #111827;
            margin: 0;
            line-height: 1.2;
        }
        .prd-kpi-info span.sub-tag {
            font-size: 0.74rem;
            font-weight: 600;
            display: inline-block;
            margin-top: 0.25rem;
            padding: 2px 8px;
            border-radius: 20px;
        }

        /* Filter Panel */
        .prd-filter-card {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 14px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.25rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .prd-search-box {
            position: relative;
            flex: 1;
            min-width: 250px;
            max-width: 400px;
        }
        .prd-search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #9CA3AF;
            font-size: 0.9rem;
        }
        .prd-input {
            width: 100%;
            padding: 0.55rem 0.85rem 0.55rem 2.25rem;
            border-radius: 9px;
            border: 1px solid #D1D5DB;
            background: #F9FAFB;
            font-size: 0.84rem;
            color: #111827;
            outline: none;
            box-sizing: border-box;
            transition: all 0.2s;
        }
        .prd-input:focus {
            background: #FFFFFF;
            border-color: #E31E24;
            box-shadow: 0 0 0 3px rgba(227,30,36,0.1);
        }
        .prd-select {
            padding: 0.55rem 2rem 0.55rem 0.85rem;
            border-radius: 9px;
            border: 1px solid #D1D5DB;
            background: #F9FAFB;
            font-size: 0.84rem;
            color: #111827;
            outline: none;
            min-width: 220px;
        }

        /* Table Card */
        .prd-table-card {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        }
        .prd-table-header-title {
            padding: 1rem 1.4rem;
            border-bottom: 1px solid #F3F4F6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .prd-table-header-title h3 {
            font-size: 0.95rem;
            font-weight: 700;
            color: #111827;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .prd-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        .prd-table th {
            background: #F9FAFB;
            color: #6B7280;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            padding: 0.85rem 1rem;
            border-bottom: 1px solid #E5E7EB;
            white-space: nowrap;
        }
        .prd-table td {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid #F3F4F6;
            font-size: 0.85rem;
            color: #374151;
            vertical-align: middle;
        }
        .prd-table tbody tr:hover {
            background: #F9FAFB;
        }

        /* Badges */
        .doc-badge {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-weight: 700;
            font-size: 0.82rem;
            color: #1E293B;
            background: #F1F5F9;
            padding: 2px 7px;
            border-radius: 6px;
            border: 1px solid #CBD5E1;
            display: inline-block;
        }
        .cat-pill {
            background: #F5F3FF;
            color: #7C3AED;
            border: 1px solid #DDD6FE;
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 0.74rem;
            font-weight: 700;
        }
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 9px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .status-pill.activo { background: rgba(5,150,105,0.1); color: #059669; border: 1px solid rgba(5,150,105,0.25); }
        .status-pill.bajo { background: rgba(217,119,6,0.1); color: #D97706; border: 1px solid rgba(217,119,6,0.25); }
        .status-pill.agotado { background: rgba(220,38,38,0.1); color: #DC2626; border: 1px solid rgba(220,38,38,0.25); }

        /* Botón elegante de variantes en la tabla */
        .btn-var-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            padding: 3px 10px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.76rem;
            font-weight: 600;
            color: #334151;
            transition: all 0.15s ease;
        }
        .btn-var-pill:hover {
            background: #EEF2FF;
            border-color: #C7D2FE;
            color: #4338CA;
            transform: translateY(-1px);
            box-shadow: 0 2px 6px rgba(99, 102, 241, 0.15);
        }
        .var-badge-stock {
            background: #ECFDF5;
            color: #059669;
            border: 1px solid #A7F3D0;
            padding: 1px 7px;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 700;
        }

        /* Actions */
        .prd-actions {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }
        .btn-action-soft {
            width: 28px;
            height: 28px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: none;
            cursor: pointer;
            font-size: 0.8rem;
            text-decoration: none;
            transition: all 0.15s;
        }
        .btn-action-soft.edit { background: rgba(100,116,139,0.1); color: #475569; }
        .btn-action-soft.edit:hover { background: #475569; color: #FFFFFF; }
        .btn-action-soft.delete { background: rgba(220,38,38,0.08); color: #DC2626; }
        .btn-action-soft.delete:hover { background: #DC2626; color: #FFFFFF; }

        /* Pagination */
        .pagination-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 1.2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .pagination-info { font-size: 0.84rem; color: #6B7280; }
        .pagination { display: flex; gap: 4px; }
        .pagination a {
            padding: 5px 12px;
            border: 1px solid #D1D5DB;
            background: #FFFFFF;
            color: #374151;
            text-decoration: none;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.15s;
        }
        .pagination a:hover { background: #F3F4F6; }
        .pagination a.active { background: #111827; color: #FFFFFF; border-color: #111827; }

        /* ===== MODAL DE VARIANTES DE COLOR ===== */
        .modal-backdrop-custom {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.2rem;
            animation: fadeIn 0.2s ease;
        }
        .modal-dialog-custom {
            background: #FFFFFF;
            border-radius: 16px;
            border: 1px solid #E2E8F0;
            width: 100%;
            max-width: 860px;
            box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            max-height: 88vh;
            animation: scaleUp 0.2s ease;
        }
        @keyframes scaleUp {
            from { opacity: 0; transform: scale(0.97); }
            to { opacity: 1; transform: scale(1); }
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-header-custom {
            padding: 1.15rem 1.5rem;
            border-bottom: 1px solid #F1F5F9;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #FFFFFF;
        }
        .modal-icon-badge {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: #F1F5F9;
            border: 1px solid #E2E8F0;
            color: #475569;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            flex-shrink: 0;
        }
        .modal-close-btn {
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            color: #64748B;
            font-size: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .modal-close-btn:hover {
            background: #E2E8F0;
            color: #0F172A;
        }

        .modal-toolbar-custom {
            padding: 0.85rem 1.5rem;
            background: #F8FAFC;
            border-bottom: 1px solid #E2E8F0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .modal-local-select {
            padding: 6px 12px;
            border-radius: 8px;
            border: 1px solid #CBD5E1;
            background: #FFFFFF;
            font-size: 0.8rem;
            font-weight: 600;
            color: #334151;
            outline: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        .modal-local-select:focus {
            border-color: #6366F1;
            box-shadow: 0 0 0 2px rgba(99,102,241,0.1);
        }

        .modal-tabs {
            display: flex;
            gap: 4px;
            background: #E2E8F0;
            padding: 3px;
            border-radius: 8px;
        }
        .modal-tab-btn {
            border: none;
            background: transparent;
            padding: 5px 11px;
            border-radius: 6px;
            font-size: 0.78rem;
            font-weight: 600;
            color: #64748B;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.15s ease;
        }
        .modal-tab-btn.active {
            background: #FFFFFF;
            color: #0F172A;
            box-shadow: 0 1px 2px rgba(0,0,0,0.06);
        }
        .tab-badge {
            background: #CBD5E1;
            color: #334151;
            font-size: 0.68rem;
            padding: 1px 6px;
            border-radius: 8px;
            font-weight: 700;
        }
        .modal-tab-btn.active .tab-badge {
            background: #F1F5F9;
            color: #1E293B;
        }
        .tab-badge.green {
            background: #DCFCE7;
            color: #15803D;
        }

        .modal-search-box {
            position: relative;
        }
        .modal-search-box i {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #94A3B8;
            font-size: 0.8rem;
        }
        .modal-search-box input {
            padding: 5px 10px 5px 28px;
            border-radius: 8px;
            border: 1px solid #CBD5E1;
            background: #FFFFFF;
            font-size: 0.8rem;
            color: #1E293B;
            outline: none;
            width: 140px;
            transition: all 0.2s;
        }
        .modal-search-box input:focus {
            width: 175px;
            border-color: #6366F1;
            box-shadow: 0 0 0 2px rgba(99,102,241,0.1);
        }

        .modal-stock-total-kpi {
            display: flex;
            align-items: center;
            gap: 6px;
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            padding: 5px 12px;
            border-radius: 8px;
            font-size: 0.82rem;
        }

        .variants-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
            gap: 10px;
        }
        .variant-card {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 10px;
            padding: 10px 13px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.15s ease;
            box-shadow: 0 1px 2px rgba(0,0,0,0.02);
        }
        .variant-card:hover {
            border-color: #CBD5E1;
            box-shadow: 0 3px 8px rgba(0,0,0,0.04);
        }
        .variant-card.has-stock {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
        }
        .variant-card.no-stock {
            opacity: 0.6;
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
        }
        .var-card-left {
            display: flex;
            align-items: center;
            gap: 10px;
            overflow: hidden;
            flex: 1;
        }
        .var-color-swatch {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            border: 1.5px solid rgba(0,0,0,0.12);
            flex-shrink: 0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        }
        .var-card-details {
            display: flex;
            flex-direction: column;
            overflow: hidden;
            gap: 2px;
        }
        .var-card-title {
            font-size: 0.85rem;
            font-weight: 700;
            color: #1E293B;
            line-height: 1.25;
        }
        .var-card-status {
            font-size: 0.72rem;
        }
        .status-available {
            color: #059669;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .status-empty {
            color: #94A3B8;
            font-weight: 500;
        }
        .var-location-hint {
            font-size: 0.68rem;
            color: #64748B;
            display: block;
            margin-top: 1px;
        }
        .var-card-right {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
            margin-left: 8px;
        }
        .var-thumb {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            overflow: hidden;
            border: 1px solid #E2E8F0;
            background: #FFFFFF;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.15s;
        }
        .var-thumb:hover {
            transform: scale(1.08);
            border-color: #94A3B8;
        }
        .var-thumb img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        .var-stock-pill {
            font-size: 0.82rem;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 6px;
            min-width: 50px;
            text-align: center;
        }
        .stock-green {
            background: #F0FDF4;
            color: #166534;
            border: 1px solid #DCFCE7;
        }
        .stock-gray {
            background: #F8FAFC;
            color: #94A3B8;
            border: 1px solid #E2E8F0;
            font-size: 0.76rem;
            font-weight: 600;
        }

        .modal-footer-custom {
            padding: 0.9rem 1.5rem;
            background: #FFFFFF;
            border-top: 1px solid #F1F5F9;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .btn-edit-variants {
            background: #C62828;
            color: #FFFFFF;
            font-weight: 600;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.84rem;
            transition: all 0.2s;
        }
        .btn-edit-variants:hover {
            background: #B71C1C;
            color: #FFFFFF;
        }
    </style>
</head>
<body>
<div class="app-wrapper">
    <?php include 'partials/sidebar.php'; ?>
    <div class="main-content">
        <?php include 'partials/header.php'; ?>
        <div class="page-content">

            <!-- Header de la Página -->
            <div class="prd-hero">
                <div class="prd-hero-title">
                    <h1><i class="fas fa-box" style="color:#E31E24;"></i> Productos</h1>
                    <p>Catálogo maestro de modelos, variantes de color y precios de venta</p>
                </div>
                <?php 
                $can_manage_products = $is_admin || in_array(strtolower($user_role ?? ''), ['almacén', 'almacen', 'producción', 'produccion']);
                if ($can_manage_products): 
                ?>
                <a href="producto_form.php" class="btn btn-primary" style="font-weight:600; padding:0.55rem 1.2rem; border-radius:10px; box-shadow:0 4px 12px rgba(227,30,36,0.25);">
                    <i class="fas fa-plus" style="margin-right:6px;"></i> Nuevo Producto
                </a>
                <?php endif; ?>
            </div>

            <!-- Toast / Alertas -->
            <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
                <div style="background:#10B981; color:#fff; padding:0.85rem 1.25rem; border-radius:10px; margin-bottom:1.2rem; display:flex; justify-content:space-between; align-items:center; font-weight:600;">
                    <div><i class="fas fa-check-circle" style="margin-right:8px;"></i> Producto eliminado exitosamente.</div>
                    <button type="button" onclick="this.parentElement.remove()" style="background:none; border:none; color:#fff; font-size:1.2rem; cursor:pointer;">&times;</button>
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['error'])): ?>
                <div style="background:#EF4444; color:#fff; padding:0.85rem 1.25rem; border-radius:10px; margin-bottom:1.2rem; display:flex; justify-content:space-between; align-items:center; font-weight:600;">
                    <div><i class="fas fa-triangle-exclamation" style="margin-right:8px;"></i> <?= htmlspecialchars($_GET['error']) ?></div>
                    <button type="button" onclick="this.parentElement.remove()" style="background:none; border:none; color:#fff; font-size:1.2rem; cursor:pointer;">&times;</button>
                </div>
            <?php endif; ?>

            <!-- KPI Cards -->
            <div class="prd-kpis-grid">
                <div class="prd-kpi-card">
                    <div class="prd-kpi-icon icon-indigo-bg">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="prd-kpi-info">
                        <span class="label">Modelos Registrados</span>
                        <h3 style="color:#4F46E5;"><?= $total_productos ?></h3>
                        <span class="sub-tag" style="background:#EEF2FF; color:#4F46E5;">En catálogo</span>
                    </div>
                </div>

                <div class="prd-kpi-card">
                    <div class="prd-kpi-icon icon-emerald-bg">
                        <i class="fas fa-boxes-stacked"></i>
                    </div>
                    <div class="prd-kpi-info">
                        <span class="label">Stock Total Físico</span>
                        <h3 style="color:#059669;"><?= number_format($stats_total_stock, 0) ?> un</h3>
                        <span class="sub-tag" style="background:#ECFDF5; color:#059669;">En todas las sedes</span>
                    </div>
                </div>

                <div class="prd-kpi-card">
                    <div class="prd-kpi-icon icon-blue-bg">
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <div class="prd-kpi-info">
                        <span class="label">Líneas / Categorías</span>
                        <h3 style="color:#2563EB;"><?= $stats_total_categorias ?></h3>
                        <span class="sub-tag" style="background:#EFF6FF; color:#2563EB;">Familias de producto</span>
                    </div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="prd-filter-card">
                <form method="GET" action="productos.php" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; width:100%;">
                    <div class="prd-search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" class="prd-input" placeholder="Buscar producto por nombre o código..." value="<?= htmlspecialchars($search) ?>">
                    </div>

                    <select name="categoria_id" class="prd-select" onchange="this.form.submit()">
                        <option value="0">🔍 Todas las Categorías</option>
                        <?php foreach($categorias_list as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= ($categoria_id == $cat['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars(mb_strtoupper($cat['nombre'], 'UTF-8')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit" class="btn btn-primary" style="padding:0.55rem 1rem; border-radius:9px; font-weight:600;">
                        <i class="fas fa-filter"></i> Filtrar
                    </button>
                    <?php if(!empty($search) || $categoria_id > 0): ?>
                        <a href="productos.php" class="btn btn-outline" style="padding:0.55rem 0.85rem; border-radius:9px;" title="Limpiar Filtros">
                            <i class="fas fa-times"></i>
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Tabla de Productos -->
            <div class="prd-table-card">
                <div class="prd-table-header-title">
                    <h3><i class="fas fa-box" style="color:#E31E24;"></i> Catálogo de Modelos</h3>
                    <span style="font-size:0.8rem; color:#6B7280; font-weight:500;">
                        Mostrando <?= count($productos) ?> de <?= $total_productos ?> productos
                    </span>
                </div>

                <div style="overflow-x:auto;">
                    <table class="prd-table">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th style="width:50px;">Foto</th>
                                <th>Producto / Modelo</th>
                                <th>Categoría</th>
                                <th>Stock Total (Colores)</th>
                                <th style="text-align:right;">Precio Venta (S/)</th>
                                <th>Estado</th>
                                <th style="text-align:center;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($productos)): ?>
                                <tr>
                                    <td colspan="8" style="text-align:center; padding:3.5rem; color:#9CA3AF;">
                                        <i class="fas fa-box-open" style="font-size:2.5rem; margin-bottom:0.8rem; opacity:0.35; display:block;"></i>
                                        No se encontraron productos con los filtros aplicados.
                                    </td>
                                </tr>
                            <?php else: foreach($productos as $prod): 
                                $stockTotal = floatval($prod['stock_total']);
                                $stockMin = floatval($prod['stock_minimo'] ?? 0);
                                if ($stockTotal > $stockMin) { $stClass = 'activo'; $stTxt = 'ACTIVO'; }
                                elseif ($stockTotal > 0) { $stClass = 'bajo'; $stTxt = 'BAJO'; }
                                else { $stClass = 'agotado'; $stTxt = 'AGOTADO'; }

                                $variants = $colores_por_producto[$prod['id']] ?? [];
                                $total_vars = count($variants);
                                $vars_con_stock = array_values(array_filter($variants, fn($v) => floatval($v['stock']) > 0));
                                $con_stock_count = count($vars_con_stock);
                            ?>
                            <tr>
                                <td><span class="doc-badge"><?= htmlspecialchars(!empty($prod['codigo']) ? $prod['codigo'] : ('PRD-' . str_pad($prod['id'], 3, '0', STR_PAD_LEFT))) ?></span></td>
                                <td>
                                    <?php if(!empty($prod['imagen_url'])): ?>
                                        <div style="width:40px; height:40px; background:#fff; border-radius:8px; display:flex; align-items:center; justify-content:center; border:1px solid #E5E7EB; overflow:hidden; cursor:pointer; transition:transform 0.2s;" onclick="openImageModal('<?= htmlspecialchars(addslashes($prod['imagen_url'])) ?>', '<?= htmlspecialchars(addslashes($prod['nombre'])) ?>')" title="Clic para ampliar">
                                            <img id="main-img-<?= $prod['id'] ?>" src="<?= htmlspecialchars($prod['imagen_url']) ?>" style="max-width:100%; max-height:100%; object-fit:contain;">
                                        </div>
                                    <?php else: ?>
                                        <div style="width:40px; height:40px; background:#F1F5F9; border-radius:8px; display:flex; align-items:center; justify-content:center; color:#94A3B8;">
                                            <i class="fas fa-image"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display:flex; flex-direction:column; gap:4px;">
                                        <strong style="color:#111827; font-size:0.92rem;"><?= htmlspecialchars($prod['nombre']) ?></strong>
                                        
                                        <?php if ($total_vars > 0): ?>
                                            <div>
                                                <button type="button" class="btn-var-pill" onclick="openVariantsModal(<?= $prod['id'] ?>)" title="Ver variantes de color y stock">
                                                    <i class="fas fa-palette" style="color:#6366F1; font-size:0.78rem;"></i>
                                                    <span><?= $total_vars ?> variantes</span>
                                                    <?php if ($con_stock_count > 0): ?>
                                                        <span class="var-badge-stock"><?= $con_stock_count ?> con stock</span>
                                                    <?php endif; ?>
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><span class="cat-pill"><?= htmlspecialchars($prod['categoria_nombre'] ?? 'General') ?></span></td>
                                <td style="font-weight:800; color:#111827; font-size:0.92rem;"><?= number_format($stockTotal, 0) ?> un</td>
                                <td style="text-align:right; font-weight:800; color:#111827; font-size:0.92rem;">
                                    S/ <?= number_format($prod['precio_venta'], 2) ?>
                                </td>
                                <td>
                                    <span class="status-pill <?= $stClass ?>">
                                        <?= $stTxt ?>
                                    </span>
                                </td>
                                <td style="text-align:center;">
                                    <?php if ($can_manage_products): ?>
                                        <div class="prd-actions">
                                            <a href="producto_form.php?id=<?= $prod['id'] ?>" class="btn-action-soft edit" title="Editar Producto">
                                                <i class="fas fa-pen"></i>
                                            </a>
                                            <?php if ($is_admin): ?>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('⚠️ ¿Seguro de ELIMINAR este producto?');">
                                                    <input type="hidden" name="delete_id" value="<?= $prod['id'] ?>">
                                                    <button type="submit" class="btn-action-soft delete" title="Eliminar">
                                                        <i class="fas fa-trash-can"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="font-size:0.75rem; color:#94A3B8; font-weight:600;"><i class="fas fa-eye"></i> Solo Lectura</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Paginación -->
            <div class="pagination-container">
                <div class="pagination-info">
                    Mostrando <strong><?= ($total_productos > 0) ? ($offset + 1) : 0 ?></strong> a <strong><?= min($offset + $limit, $total_productos) ?></strong> de <strong><?= $total_productos ?></strong> productos
                </div>
                
                <?php if($total_pages > 1): ?>
                <div class="pagination">
                    <?php if($page > 1): ?>
                        <a href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&categoria_id=<?= $categoria_id ?>">&laquo;</a>
                    <?php endif; ?>
                    
                    <?php for($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                        <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&categoria_id=<?= $categoria_id ?>" class="<?= ($i == $page) ? 'active' : '' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    
                    <?php if($page < $total_pages): ?>
                        <a href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&categoria_id=<?= $categoria_id ?>">&raquo;</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<!-- Modal de Variantes de Color -->
<div id="variantsModal" class="modal-backdrop-custom" style="display:none;" onclick="if(event.target === this) closeVariantsModal();">
    <div class="modal-dialog-custom">
        <!-- Header -->
        <div class="modal-header-custom">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div class="modal-icon-badge">
                    <i class="fas fa-palette"></i>
                </div>
                <div>
                    <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                        <h3 id="varModalTitle" style="margin:0; font-size:1.15rem; font-weight:800; color:#0F172A;">Modelo</h3>
                        <span id="varModalCode" class="doc-badge">PRD-000</span>
                        <span id="varModalCat" class="cat-pill">Categoría</span>
                    </div>
                    <p style="margin:2px 0 0 0; font-size:0.78rem; color:#64748B;">Distribución de stock y variantes de acabado</p>
                </div>
            </div>
            <button type="button" class="modal-close-btn" onclick="closeVariantsModal()">&times;</button>
        </div>

        <!-- Barra de herramientas (Filtro por Sede, Pestañas, Búsqueda y KPI) -->
        <div class="modal-toolbar-custom">
            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                <!-- Selector de Sede -->
                <select id="modalLocalFilter" class="modal-local-select" onchange="changeModalLocal(this.value)" title="Filtrar stock por tienda o almacén">
                    <option value="0">🏢 Todas las Sedes (Consolidado)</option>
                    <?php foreach($locales_list as $loc): ?>
                        <option value="<?= $loc['id'] ?>"><?= htmlspecialchars($loc['tipo']) ?> — <?= htmlspecialchars($loc['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>

                <!-- Pestañas de Filtro -->
                <div class="modal-tabs">
                    <button type="button" class="modal-tab-btn" data-filter="with_stock" onclick="filterModalVariants('with_stock', this)">
                        Con Stock <span id="tabCountWithStock" class="tab-badge green">0</span>
                    </button>
                    <button type="button" class="modal-tab-btn" data-filter="all" onclick="filterModalVariants('all', this)">
                        Todos <span id="tabCountAll" class="tab-badge">0</span>
                    </button>
                    <button type="button" class="modal-tab-btn" data-filter="no_stock" onclick="filterModalVariants('no_stock', this)">
                        Sin Stock <span id="tabCountNoStock" class="tab-badge">0</span>
                    </button>
                </div>
            </div>

            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                <div class="modal-search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="modalSearchInput" placeholder="Buscar color..." onkeyup="searchModalVariants(this.value)">
                </div>
                <div class="modal-stock-total-kpi">
                    <span style="color:#64748B; font-weight:600; font-size:0.75rem;">Total:</span>
                    <strong id="varModalTotalStock" style="color:#0F172A; font-weight:800; font-size:0.92rem;">0 un</strong>
                </div>
            </div>
        </div>

        <!-- Grid de Tarjetas de Colores -->
        <div class="modal-body-custom" style="max-height: 54vh; overflow-y: auto; padding: 1.1rem 1.4rem; background:#F8FAFC;">
            <div id="variantsGrid" class="variants-grid"></div>
            <div id="variantsEmptyState" style="display:none; text-align:center; padding:2.5rem 1rem; color:#94A3B8;">
                <i class="fas fa-filter" style="font-size:2rem; opacity:0.3; margin-bottom:8px; display:block;"></i>
                <span style="font-size:0.86rem; font-weight:600;">No se encontraron colores con el filtro seleccionado.</span>
            </div>
        </div>

        <!-- Footer -->
        <div class="modal-footer-custom">
            <span style="font-size:0.78rem; color:#64748B;">
                <i class="fas fa-circle-info" style="color:#6366F1;"></i> Clic en la foto para ampliarla
            </span>
            <div style="display:flex; gap:8px;">
                <button type="button" class="btn btn-outline" style="font-size:0.82rem; padding:0.45rem 0.9rem;" onclick="closeVariantsModal()">Cerrar</button>
                <?php if ($can_manage_products): ?>
                    <a id="varModalEditLink" href="#" class="btn-edit-variants">
                        <i class="fas fa-pen"></i> Editar Variantes
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal para ver imagen ampliada -->
<div id="imageModal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.85); z-index:10000; align-items:center; justify-content:center; backdrop-filter:blur(4px);" onclick="if(event.target === this) document.getElementById('imageModal').style.display='none'">
    <div style="position:relative; max-width:90%; max-height:90%; padding:20px; text-align:center;">
        <button onclick="document.getElementById('imageModal').style.display='none'" style="position:absolute; top:-10px; right:-10px; background:#111827; border:1px solid #374151; color:#fff; font-size:1.4rem; cursor:pointer; width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center;">&times;</button>
        <h3 id="modalImageTitle" style="margin:0 0 12px 0; color:#fff; font-size:1.1rem; font-weight:700;"></h3>
        <div style="background:#fff; padding:15px; border-radius:12px; display:inline-block; box-shadow:0 20px 40px rgba(0,0,0,0.5);">
            <img id="modalImageSrc" src="" style="max-width:100%; max-height:70vh; object-fit:contain; border-radius:6px;">
        </div>
    </div>
</div>

<script>
const productosMap = <?= json_encode(array_column($productos, null, 'id'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
const variantesMap = <?= json_encode($colores_por_producto, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
const localesMap = <?= json_encode(array_column($locales_list, null, 'id'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;

let currentModalProdId = null;
let currentFilter = 'with_stock';
let currentLocalId = 0; // 0 = Todas las Sedes

function escapeHtml(text) {
    if (!text) return '';
    return String(text).replace(/[&<>"']/g, function(m) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
    });
}

function openVariantsModal(prodId) {
    currentModalProdId = prodId;
    const prod = productosMap[prodId];
    if (!prod) return;

    const variants = variantesMap[prodId] || [];
    
    // Set Header Info
    document.getElementById('varModalTitle').textContent = prod.nombre;
    document.getElementById('varModalCode').textContent = prod.codigo || ('PRD-' + String(prod.id).padStart(3, '0'));
    document.getElementById('varModalCat').textContent = prod.categoria_nombre || 'General';
    
    const editLink = document.getElementById('varModalEditLink');
    if (editLink) {
        editLink.href = 'producto_form.php?id=' + prod.id;
    }

    // Reset local filter dropdown to "Todas las sedes"
    currentLocalId = 0;
    const localSelect = document.getElementById('modalLocalFilter');
    if (localSelect) localSelect.value = "0";

    // Reset search input
    document.getElementById('modalSearchInput').value = '';

    updateModalCountsAndStock();

    // Default filter: 'with_stock' if available, otherwise 'all'
    const withStockCount = variants.filter(v => Number(v.stock) > 0).length;
    currentFilter = withStockCount > 0 ? 'with_stock' : 'all';

    document.querySelectorAll('.modal-tab-btn').forEach(btn => btn.classList.remove('active'));
    const defaultTab = document.querySelector(`.modal-tab-btn[data-filter="${currentFilter}"]`);
    if (defaultTab) defaultTab.classList.add('active');

    renderVariantsGrid();
    
    document.getElementById('variantsModal').style.display = 'flex';
}

function closeVariantsModal() {
    document.getElementById('variantsModal').style.display = 'none';
    currentModalProdId = null;
}

function changeModalLocal(localId) {
    currentLocalId = parseInt(localId, 10);
    updateModalCountsAndStock();
    renderVariantsGrid();
}

function updateModalCountsAndStock() {
    if (!currentModalProdId) return;
    const variants = variantesMap[currentModalProdId] || [];
    
    let totalStock = 0;
    let withStockCount = 0;
    
    variants.forEach(v => {
        let stock = 0;
        if (currentLocalId === 0) {
            stock = Number(v.stock) || 0;
        } else {
            stock = Number(v.locales_stock?.[currentLocalId]) || 0;
        }
        totalStock += stock;
        if (stock > 0) withStockCount++;
    });

    const totalCount = variants.length;
    const noStockCount = totalCount - withStockCount;

    document.getElementById('tabCountAll').textContent = totalCount;
    document.getElementById('tabCountWithStock').textContent = withStockCount;
    document.getElementById('tabCountNoStock').textContent = noStockCount;
    document.getElementById('varModalTotalStock').textContent = Math.round(totalStock).toLocaleString() + ' un';
}

function filterModalVariants(filterType, btnEl) {
    currentFilter = filterType;
    document.querySelectorAll('.modal-tab-btn').forEach(btn => btn.classList.remove('active'));
    if (btnEl) btnEl.classList.add('active');
    renderVariantsGrid();
}

function searchModalVariants(query) {
    renderVariantsGrid();
}

function renderVariantsGrid() {
    if (!currentModalProdId) return;
    let variants = [...(variantesMap[currentModalProdId] || [])];
    const query = (document.getElementById('modalSearchInput').value || '').trim().toLowerCase();
    const grid = document.getElementById('variantsGrid');
    const emptyState = document.getElementById('variantsEmptyState');

    // Calculate effective stock for current local filter
    variants.forEach(v => {
        if (currentLocalId === 0) {
            v._effectiveStock = Number(v.stock) || 0;
        } else {
            v._effectiveStock = Number(v.locales_stock?.[currentLocalId]) || 0;
        }
    });

    // Sort: Items with stock > 0 first, then alphabetically
    variants.sort((a, b) => {
        const sa = a._effectiveStock;
        const sb = b._effectiveStock;
        if (sa > 0 && sb <= 0) return -1;
        if (sb > 0 && sa <= 0) return 1;
        if (sa > 0 && sb > 0) return sb - sa; // Higher stock first
        return a.nombre.localeCompare(b.nombre);
    });

    let filtered = variants.filter(v => {
        const stock = v._effectiveStock;
        if (currentFilter === 'with_stock' && stock <= 0) return false;
        if (currentFilter === 'no_stock' && stock > 0) return false;
        if (query) {
            const nomMatch = v.nombre.toLowerCase().includes(query);
            const codeVarMatch = v.codigo_variante && v.codigo_variante.toLowerCase().includes(query);
            const codeColMatch = v.codigo_color && v.codigo_color.toLowerCase().includes(query);
            if (!nomMatch && !codeVarMatch && !codeColMatch) return false;
        }
        return true;
    });

    if (filtered.length === 0) {
        grid.innerHTML = '';
        emptyState.style.display = 'block';
        return;
    }

    emptyState.style.display = 'none';
    grid.innerHTML = filtered.map(v => {
        const stock = v._effectiveStock;
        const hasStock = stock > 0;
        const stockFmt = stock.toFixed(0) + ' un';
        const imgHtml = v.imagen_url ? `
            <div class="var-thumb" onclick="openImageModal('${v.imagen_url}', '${escapeHtml(v.nombre)}')" title="Ver foto ampliada">
                <img src="${v.imagen_url}" alt="${escapeHtml(v.nombre)}">
            </div>
        ` : '';

        // Sede breakdown text when viewing "Todas las Sedes"
        let locationHint = '';
        if (currentLocalId === 0 && hasStock && v.locales_stock) {
            const locDetails = [];
            for (const [locId, locStock] of Object.entries(v.locales_stock)) {
                if (Number(locStock) > 0) {
                    const locObj = localesMap[locId];
                    if (locObj) {
                        locDetails.push(`${locObj.nombre}: ${locStock} un`);
                    }
                }
            }
            if (locDetails.length > 0) {
                locationHint = `<span class="var-location-hint"><i class="fas fa-location-dot" style="font-size:0.6rem; color:#64748B;"></i> ${escapeHtml(locDetails.join(' • '))}</span>`;
            }
        }

        const badgeCode = v.codigo_variante || v.codigo_color || '';

        return `
            <div class="variant-card ${hasStock ? 'has-stock' : 'no-stock'}">
                <div class="var-card-left">
                    <div class="var-color-swatch" style="background: ${v.css_color};" title="${escapeHtml(v.nombre)}"></div>
                    <div class="var-card-details">
                        <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                            <span class="var-card-title">${escapeHtml(v.nombre)}</span>
                            ${badgeCode ? `<span class="doc-badge" style="font-size:0.65rem; padding:1px 5px; font-weight:700;">${escapeHtml(badgeCode)}</span>` : ''}
                        </div>
                        <span class="var-card-status">
                            ${hasStock ? '<span class="status-available"><i class="fas fa-check"></i> En stock</span>' : '<span class="status-empty">Sin existencias</span>'}
                        </span>
                        ${locationHint}
                    </div>
                </div>
                <div class="var-card-right">
                    ${imgHtml}
                    <div class="var-stock-pill ${hasStock ? 'stock-green' : 'stock-gray'}">
                        ${stockFmt}
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

function openImageModal(src, title) {
    document.getElementById('modalImageSrc').src = src;
    document.getElementById('modalImageTitle').textContent = title;
    document.getElementById('imageModal').style.display = 'flex';
}

// Cerrar modales con tecla Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const imgModal = document.getElementById('imageModal');
        if (imgModal && imgModal.style.display === 'flex') {
            imgModal.style.display = 'none';
            return;
        }
        const varModal = document.getElementById('variantsModal');
        if (varModal && varModal.style.display === 'flex') {
            closeVariantsModal();
        }
    }
});
</script>

<?php include 'partials/footer.php'; ?>
</body>
</html>
