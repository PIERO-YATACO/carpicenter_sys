<?php
require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../config/db.php';

$page_title = 'Inventario';
$page_subtitle = 'Control de existencias, stock físico y disponibilidad multitienda';

// Get all locales
$locales = $db->query("SELECT * FROM locales ORDER BY tipo DESC, nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

// Get categories
$categorias = $db->query("SELECT * FROM categorias ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

// Build dynamic existencias query
$localColumns = implode(",\n        ", array_map(function($l) {
    return "COALESCE(SUM(CASE WHEN il.local_id = {$l['id']} THEN il.stock_actual ELSE 0 END), 0) AS local_{$l['id']}_actual,\n" .
           "COALESCE(SUM(CASE WHEN il.local_id = {$l['id']} THEN COALESCE(il.stock_reservado, 0) ELSE 0 END), 0) AS local_{$l['id']}_reservado";
}, $locales));

$sql = "
    SELECT 
        p.id as producto_id,
        p.nombre as producto_nombre,
        p.codigo as producto_codigo,
        cat.nombre as categoria,
        c.id as color_id,
        c.nombre as color_nombre,
        c.codigo as color_codigo,
        pc.codigo as variante_codigo,
        $localColumns,
        COALESCE(SUM(il.stock_actual), 0) as stock_total_actual,
        COALESCE(SUM(il.stock_reservado), 0) as stock_total_reservado
    FROM producto_colores pc
    JOIN productos p ON pc.producto_id = p.id
    JOIN colores c ON pc.color_id = c.id
    LEFT JOIN categorias cat ON p.categoria_id = cat.id
    LEFT JOIN inventario_local il ON il.producto_id = p.id AND il.color_id = c.id
    GROUP BY p.id, p.nombre, p.codigo, cat.nombre, c.id, c.nombre, c.codigo, pc.codigo
    HAVING COALESCE(SUM(il.stock_actual), 0) > 0 OR COALESCE(SUM(il.stock_reservado), 0) > 0
    ORDER BY p.nombre, c.nombre
";

$existencias = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Stock summary per local
$localTotals = [];
$localTotalsReservado = [];
foreach ($locales as $l) {
    $localTotals[$l['id']] = array_sum(array_column($existencias, "local_{$l['id']}_actual"));
    $localTotalsReservado[$l['id']] = array_sum(array_column($existencias, "local_{$l['id']}_reservado"));
}
$grandTotal = array_sum($localTotals);
$grandTotalReservado = array_sum($localTotalsReservado);
$grandTotalDisponible = max(0, $grandTotal - $grandTotalReservado);

function getHexColorInv($name) {
    $n = strtoupper(trim($name));
    $map = [
        'BLANCO' => '#FFFFFF', 'NEGRO' => '#111827', 'TAUPE' => '#877B73', 'TORTORA (TAUPE)' => '#877B73',
        'ROJO' => '#D32F2F', 'AMARILLO' => '#EAB308', 'AMARILLO OSCURO' => '#CA8A04', 'VERDE LIMON' => '#84CC16', 
        'VERDE PASTEL' => '#86EFAC', 'CELESTE' => '#38BDF8', 'GRIS OSCURO' => '#475569', 'GRIS CLARO' => '#CBD5E1', 
        'AZUL' => '#2563EB', 'DUNA' => '#D2B48C', 'DUNA RAYADA' => '#C4A482', 'DUNA ESTRELLADA' => '#B8977E',
        'MARRON' => '#78350F', 'MARRON (VIDRIO)' => '#78350F', 
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
    <title><?= $page_title ?> — Carpicenter</title>
    <link rel="stylesheet" href="/carpicenter_sys/assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* ===== INVENTARIO MULTITIENDA PREMIUM ===== */
        .inv-hero {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .inv-hero-title h1 {
            font-size: 1.45rem;
            font-weight: 800;
            color: #111827;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .inv-hero-title p {
            color: #6B7280;
            font-size: 0.84rem;
            margin: 0.25rem 0 0 0;
        }
        .inv-hero-actions {
            display: flex;
            gap: 0.65rem;
            align-items: center;
            flex-wrap: wrap;
        }

        /* Nav Tabs por Local */
        .inv-tabs-container {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.25rem;
            overflow-x: auto;
            padding-bottom: 4px;
        }
        .inv-tab-btn {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 12px;
            padding: 0.75rem 1.1rem;
            font-size: 0.84rem;
            font-weight: 700;
            color: #4B5563;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
            white-space: nowrap;
            box-shadow: 0 1px 2px rgba(0,0,0,0.02);
        }
        .inv-tab-btn:hover {
            border-color: #D1D5DB;
            background: #F9FAFB;
            transform: translateY(-1px);
        }
        .inv-tab-btn.active {
            background: #E31E24;
            color: #FFFFFF;
            border-color: #E31E24;
            box-shadow: 0 4px 12px rgba(227,30,36,0.25);
        }
        .inv-tab-btn .badge-pill {
            background: #F3F4F6;
            color: #374151;
            padding: 2px 7px;
            border-radius: 10px;
            font-size: 0.72rem;
            font-weight: 700;
        }
        .inv-tab-btn.active .badge-pill {
            background: rgba(255,255,255,0.25);
            color: #FFFFFF;
        }

        /* KPI Cards Grid */
        .inv-kpis-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.1rem;
            margin-bottom: 1.3rem;
        }
        .inv-kpi-card {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 14px;
            padding: 1.1rem 1.3rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
            transition: transform 0.2s ease;
        }
        .inv-kpi-card:hover {
            transform: translateY(-2px);
        }
        .inv-kpi-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }
        .icon-indigo-bg { background: rgba(99,102,241,0.1); color: #6366F1; }
        .icon-emerald-bg { background: rgba(16,185,129,0.1); color: #10B981; }
        .icon-amber-bg { background: rgba(245,158,11,0.1); color: #F59E0B; }
        .icon-blue-bg { background: rgba(59,130,246,0.1); color: #3B82F6; }

        .inv-kpi-info span.label {
            font-size: 0.75rem;
            font-weight: 600;
            color: #6B7280;
            text-transform: uppercase;
            display: block;
            margin-bottom: 0.15rem;
        }
        .inv-kpi-info h3 {
            font-size: 1.3rem;
            font-weight: 800;
            color: #111827;
            margin: 0;
            line-height: 1.2;
        }
        .inv-kpi-info span.sub-tag {
            font-size: 0.72rem;
            font-weight: 600;
            display: inline-block;
            margin-top: 0.25rem;
        }

        /* Filter Panel */
        .inv-filter-card {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 14px;
            padding: 0.9rem 1.2rem;
            margin-bottom: 1.2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        .inv-filter-form {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            align-items: center;
        }
        .inv-search-box {
            flex: 2;
            min-width: 240px;
            position: relative;
        }
        .inv-search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #9CA3AF;
            font-size: 0.9rem;
        }
        .inv-search-box input {
            width: 100%;
            padding: 0.55rem 0.85rem 0.55rem 2.25rem;
            border-radius: 10px;
            border: 1px solid #D1D5DB;
            background: #F9FAFB;
            font-size: 0.85rem;
            color: #111827;
            outline: none;
            box-sizing: border-box;
            transition: all 0.2s;
        }
        .inv-search-box input:focus {
            background: #FFFFFF;
            border-color: #E31E24;
            box-shadow: 0 0 0 3px rgba(227,30,36,0.1);
        }
        .inv-select {
            padding: 0.55rem 2rem 0.55rem 0.85rem;
            border-radius: 10px;
            border: 1px solid #D1D5DB;
            background: #F9FAFB;
            font-size: 0.85rem;
            font-weight: 500;
            color: #374151;
            outline: none;
            cursor: pointer;
            min-width: 160px;
        }
        .inv-select:focus {
            border-color: #E31E24;
            background: #FFFFFF;
        }

        /* Table Card */
        .inv-table-card {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        }
        .inv-table-header-title {
            padding: 1rem 1.4rem;
            border-bottom: 1px solid #F3F4F6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .inv-table-header-title h3 {
            font-size: 0.95rem;
            font-weight: 700;
            color: #111827;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .inv-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }
        .inv-table th {
            background: #F9FAFB;
            color: #6B7280;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 0.85rem 1rem;
            border-bottom: 1px solid #E5E7EB;
            white-space: nowrap;
        }
        .inv-table td {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid #F3F4F6;
            font-size: 0.85rem;
            color: #374151;
            vertical-align: middle;
        }
        .inv-table tbody tr {
            transition: background 0.15s ease;
        }
        .inv-table tbody tr:hover {
            background: #F9FAFB;
        }

        /* Badges & Stock Indicators */
        .doc-badge {
            background: #F1F5F9;
            color: #1E293B;
            font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
            font-size: 0.8rem;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 6px;
            border: 1px solid #CBD5E1;
            letter-spacing: 0.3px;
            display: inline-flex;
            align-items: center;
            white-space: nowrap;
        }
        .color-badge-code {
            background: #E2E8F0;
            color: #0F172A;
            font-size: 0.72rem;
            font-weight: 800;
            padding: 1px 6px;
            border-radius: 4px;
            border: 1px solid #CBD5E1;
            margin-left: 6px;
            display: inline-block;
        }
        .color-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #FFFFFF;
            color: #1E293B;
            padding: 3px 9px;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 600;
            border: 1px solid #E2E8F0;
            box-shadow: 0 1px 2px rgba(0,0,0,0.02);
        }
        .color-dot-swatch {
            width: 13px;
            height: 13px;
            border-radius: 50%;
            display: inline-block;
            border: 1.5px solid rgba(0,0,0,0.15);
            flex-shrink: 0;
        }
        .stock-badge-main {
            font-size: 0.95rem;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .stock-badge-disp {
            background: #ECFDF5;
            color: #059669;
            border: 1px solid #A7F3D0;
            padding: 4px 10px;
            border-radius: 8px;
            font-weight: 800;
            font-size: 0.92rem;
            display: inline-block;
        }
        .stock-badge-zero {
            background: #FEF2F2;
            color: #DC2626;
            border: 1px solid #FECACA;
            padding: 4px 10px;
            border-radius: 8px;
            font-weight: 800;
            font-size: 0.88rem;
            display: inline-block;
        }
        .status-badge-stock {
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            display: inline-block;
        }
        .badge-instock { background: rgba(5,150,105,0.1); color: #059669; border: 1px solid rgba(5,150,105,0.25); }
        .badge-lowstock { background: rgba(217,119,6,0.1); color: #D97706; border: 1px solid rgba(217,119,6,0.25); }
        .badge-nostock { background: rgba(220,38,38,0.1); color: #DC2626; border: 1px solid rgba(220,38,38,0.25); }

        /* Button to see other stores */
        .btn-other-stores {
            background: #F8FAFC;
            border: 1px solid #CBD5E1;
            color: #475569;
            padding: 4px 9px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .btn-other-stores:hover {
            background: #2563EB;
            color: #FFFFFF;
            border-color: #2563EB;
        }

        /* Modal Desglose */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            z-index: 999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .modal-overlay.open {
            display: flex;
        }
        .modal-box {
            background: #FFFFFF;
            border-radius: 16px;
            width: 100%;
            max-width: 540px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            animation: modalFadeIn 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            overflow: hidden;
        }
        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(8px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .modal-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #E5E7EB;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .modal-body {
            padding: 1.5rem;
        }
        .modal-footer {
            padding: 1rem 1.5rem;
            background: #F9FAFB;
            border-top: 1px solid #E5E7EB;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .store-breakdown-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem 0.9rem;
            border-radius: 10px;
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            margin-bottom: 0.6rem;
        }

        /* ===== ESTILOS PARA IMPRESIÓN / PDF ===== */
        .print-only-header { display: none; }
        @media print {
            .sidebar, .main-header, .inv-hero, .inv-tabs-container, .inv-filter-card, .inv-hero-actions, .modal-backdrop, .no-print, button, .col-otras-tiendas {
                display: none !important;
            }
            .print-only-header {
                display: block !important;
                margin-bottom: 15px;
            }
            .app-wrapper, .main-content, .page-content {
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
                background: #ffffff !important;
            }
            .inv-table-card {
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
            }
            .inv-table {
                width: 100% !important;
                font-size: 8.5pt !important;
            }
            .inv-table th {
                background: #f1f5f9 !important;
                color: #0f172a !important;
                border: 1px solid #cbd5e1 !important;
                padding: 5px 6px !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .inv-table td {
                border: 1px solid #e2e8f0 !important;
                padding: 4px 6px !important;
            }
            .inv-kpis-grid {
                display: grid !important;
                grid-template-columns: repeat(4, 1fr) !important;
                gap: 8px !important;
                margin-bottom: 12px !important;
            }
            .inv-kpi-card {
                padding: 8px 10px !important;
                border: 1px solid #cbd5e1 !important;
                box-shadow: none !important;
            }
            .inv-kpi-icon {
                display: none !important;
            }
            .inv-kpi-info h3 {
                font-size: 1.1rem !important;
            }
        }
    </style>
</head>
<body>
    <div class="app-wrapper">
        <?php include __DIR__ . '/../../views/partials/sidebar.php'; ?>
        <div class="main-content">
            <?php include __DIR__ . '/../../views/partials/header.php'; ?>
            <div class="page-content">

                <!-- Encabezado exclusivo para Impresión -->
                <div class="print-only-header">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; border-bottom:2px solid #C62828; padding-bottom:8px;">
                        <div>
                            <h2 style="margin:0; font-size:16pt; color:#C62828; font-weight:800;">INDUSTRIAS CARPICENTER</h2>
                            <p style="margin:2px 0 0 0; font-size:10pt; color:#334155; font-weight:600;" id="printReportSubtitle">Reporte de Inventario — Consolidado General</p>
                        </div>
                        <div style="text-align:right; font-size:8.5pt; color:#64748B;">
                            <div><strong>Fecha de emisión:</strong> <?= date('d/m/Y H:i:s') ?></div>
                            <div id="printFiltrosInfo" style="font-weight:600; color:#1E293B;">Todos los artículos</div>
                        </div>
                    </div>
                </div>

                <!-- Header de la Página -->
                <div class="inv-hero">
                    <div class="inv-hero-title">
                        <h1><i class="fas fa-boxes-stacked" style="color:#E31E24;"></i> Inventario General de Stock</h1>
                        <p>Control físico, stock reservado y disponibilidad inmediata por sucursal</p>
                    </div>
                    <div class="inv-hero-actions">
                        <button type="button" onclick="imprimirInventario()" class="btn btn-outline" style="border-color:#475569; color:#475569; font-weight:600; padding:0.55rem 1rem; border-radius:10px;" title="Imprimir o exportar a PDF la vista actual">
                            <i class="fas fa-print" style="margin-right:6px;"></i> Imprimir / PDF
                        </button>
                        <button type="button" onclick="exportarExcel()" class="btn btn-outline" style="border-color:#107C41; color:#107C41; font-weight:600; padding:0.55rem 1rem; border-radius:10px;" title="Descargar en Excel según la tienda y filtros activos">
                            <i class="fas fa-file-excel" style="margin-right:6px;"></i> Exportar a Excel
                        </button>
                        <a href="/carpicenter_sys/modules/transferencias/transferencia_form.php" class="btn btn-primary" style="font-weight:600; padding:0.55rem 1.1rem; border-radius:10px; box-shadow:0 4px 12px rgba(227,30,36,0.25);">
                            <i class="fas fa-truck-loading" style="margin-right:6px;"></i> Nueva Transferencia
                        </a>
                    </div>
                </div>

                <!-- Pestañas Rápidas por Local -->
                <div class="inv-tabs-container">
                    <button type="button" class="inv-tab-btn active" id="tabAll" onclick="selectLocalTab('all', 'Consolidado General')">
                        <i class="fas fa-globe"></i> Todas las Tiendas
                        <span class="badge-pill"><?= number_format($grandTotalDisponible) ?> disp.</span>
                    </button>
                    <?php foreach ($locales as $l): 
                        $isAlm = $l['tipo'] === 'Almacen';
                        $totAct = $localTotals[$l['id']] ?? 0;
                        $totRes = $localTotalsReservado[$l['id']] ?? 0;
                        $totDisp = max(0, $totAct - $totRes);
                    ?>
                        <button type="button" class="inv-tab-btn" id="tabLocal<?= $l['id'] ?>" onclick="selectLocalTab(<?= $l['id'] ?>, '<?= addslashes($l['nombre']) ?>')">
                            <i class="fas <?= $isAlm ? 'fa-warehouse' : 'fa-store' ?>"></i> <?= htmlspecialchars($l['nombre']) ?>
                            <span class="badge-pill"><?= number_format($totDisp) ?> disp.</span>
                        </button>
                    <?php endforeach; ?>
                </div>

                <!-- KPI Cards Dinámicos -->
                <div class="inv-kpis-grid">
                    <div class="inv-kpi-card">
                        <div class="inv-kpi-icon icon-blue-bg">
                            <i class="fas fa-cubes"></i>
                        </div>
                        <div class="inv-kpi-info">
                            <span class="label">Stock Físico Real</span>
                            <h3 id="kpiStockFisico"><?= number_format($grandTotal) ?></h3>
                            <span class="sub-tag" id="kpiStockFisicoSub" style="color:#2563EB;">Unidades en físico</span>
                        </div>
                    </div>

                    <div class="inv-kpi-card">
                        <div class="inv-kpi-icon icon-rose-bg">
                            <i class="fas fa-lock"></i>
                        </div>
                        <div class="inv-kpi-info">
                            <span class="label">Reservado Contratos</span>
                            <h3 id="kpiStockReservado" style="color:#DC2626;"><?= number_format($grandTotalReservado) ?></h3>
                            <span class="sub-tag" style="color:#DC2626;">Comprometido</span>
                        </div>
                    </div>

                    <div class="inv-kpi-card">
                        <div class="inv-kpi-icon icon-green-bg">
                            <i class="fas fa-circle-check"></i>
                        </div>
                        <div class="inv-kpi-info">
                            <span class="label">Disponible para Venta</span>
                            <h3 id="kpiStockDisponible" style="color:#059669;"><?= number_format($grandTotalDisponible) ?></h3>
                            <span class="sub-tag" style="color:#059669;">Libre para entrega</span>
                        </div>
                    </div>

                    <div class="inv-kpi-card">
                        <div class="inv-kpi-icon icon-amber-bg">
                            <i class="fas fa-tags"></i>
                        </div>
                        <div class="inv-kpi-info">
                            <span class="label">Modelos / Variantes</span>
                            <h3 id="kpiTotalVariantes"><?= count($existencias) ?></h3>
                            <span class="sub-tag" id="kpiFilterContext" style="color:#D97706;">Vista Global</span>
                        </div>
                    </div>
                </div>

                <!-- Buscador y Filtros -->
                <div class="inv-filter-card">
                    <div class="inv-filter-form">
                        <div class="inv-search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" id="invSearchInput" placeholder="Buscar por producto, modelo o color (ej: Banco Capri, Negro, Cedro)..." oninput="applyFilters()">
                        </div>

                        <select id="invCatFilter" class="inv-select" onchange="applyFilters()">
                            <option value="">Todas las Categorías</option>
                            <?php foreach ($categorias as $c): ?>
                                <option value="<?= strtolower(htmlspecialchars($c['nombre'])) ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>

                        <select id="invDisponibilidadFilter" class="inv-select" onchange="applyFilters()">
                            <option value="all">Todo el Inventario</option>
                            <option value="disponible">Solo con Stock Disponible (> 0)</option>
                            <option value="agotado">Agotados (0 disponibles)</option>
                            <option value="reservado">Con Stock Reservado</option>
                        </select>

                        <button type="button" class="btn btn-outline" onclick="resetFilters()" style="padding:0.55rem 0.9rem; border-radius:10px;" title="Limpiar Filtros">
                            <i class="fas fa-rotate-left"></i> Limpiar
                        </button>
                    </div>
                </div>

                <!-- Tabla Principal de Inventario -->
                <div class="inv-table-card">
                    <div class="inv-table-header-title">
                        <h3 id="tableTitle"><i class="fas fa-list-check" style="color:#E31E24;"></i> Existencias - Consolidado General</h3>
                        <span style="font-size:0.8rem; color:#6B7280; font-weight:500;">
                            <strong id="visibleCount"><?= count($existencias) ?></strong> artículos visibles
                        </span>
                    </div>

                    <div style="overflow-x:auto;">
                        <table class="inv-table" id="invTable">
                            <thead>
                                <tr id="tableHeaders">
                                    <th style="width: 140px;">CÓDIGO OFICIAL</th>
                                    <th>PRODUCTO / MODELO</th>
                                    <th>COLOR / VARIANTE</th>
                                    <th>CATEGORÍA</th>
                                    <th style="text-align:center;">STOCK FÍSICO</th>
                                    <th style="text-align:center;">RESERVADO</th>
                                    <th style="text-align:center;">🟢 STOCK DISPONIBLE</th>
                                    <th style="text-align:center;">ESTADO</th>
                                    <th style="text-align:center;">OTRAS TIENDAS</th>
                                </tr>
                            </thead>
                            <tbody id="invTableBody">
                                <?php foreach ($existencias as $idx => $e): 
                                    $totActual = intval($e['stock_total_actual']);
                                    $totRes = intval($e['stock_total_reservado']);
                                    $totDisp = max(0, $totActual - $totRes);
                                    $hex = getHexColorInv($e['color_nombre']);

                                    // Build store JSON data for rapid modal view
                                    $storesData = [];
                                    foreach ($locales as $l) {
                                        $act = intval($e["local_{$l['id']}_actual"] ?? 0);
                                        $res = intval($e["local_{$l['id']}_reservado"] ?? 0);
                                        $disp = max(0, $act - $res);
                                        $storesData[] = [
                                            'local_id' => $l['id'],
                                            'nombre' => $l['nombre'],
                                            'tipo' => $l['tipo'],
                                            'actual' => $act,
                                            'reservado' => $res,
                                            'disponible' => $disp
                                        ];
                                    }
                                    $jsonStores = htmlspecialchars(json_encode($storesData), ENT_QUOTES, 'UTF-8');
                                ?>
                                    <?php 
                                        $codigoOficial = (!empty($e['producto_codigo']) ? $e['producto_codigo'] : 'CA-PRD') . (!empty($e['color_codigo']) ? $e['color_codigo'] : '');
                                    ?>
                                    <tr class="inv-row" 
                                        data-nombre="<?= strtolower($codigoOficial . ' ' . $e['producto_nombre'] . ' ' . $e['color_nombre']) ?>"
                                        data-cat="<?= strtolower($e['categoria'] ?? '') ?>"
                                        data-stores='<?= $jsonStores ?>'
                                        data-total-act="<?= $totActual ?>"
                                        data-total-res="<?= $totRes ?>"
                                        data-total-disp="<?= $totDisp ?>">
                                        <td>
                                            <span class="doc-badge"><?= htmlspecialchars($codigoOficial) ?></span>
                                        </td>
                                        <td>
                                            <strong style="color:#0F172A; font-size:0.92rem;"><?= htmlspecialchars($e['producto_nombre']) ?></strong>
                                        </td>
                                        <td>
                                            <span style="font-weight:600; color:#334155; font-size:0.86rem; text-transform:uppercase;">
                                                <?= htmlspecialchars($e['color_nombre']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span style="color:#475569; font-weight:700; font-size:0.75rem; background:#F1F5F9; padding:3px 8px; border-radius:6px; border:1px solid #E2E8F0; text-transform:uppercase;">
                                                <?= htmlspecialchars($e['categoria'] ?? 'GENERAL') ?>
                                            </span>
                                        </td>
                                        <td style="text-align:center; font-weight:700; color:#1E293B;" class="col-fisico">
                                            <?= $totActual ?>
                                        </td>
                                        <td style="text-align:center;" class="col-reservado">
                                            <?php if ($totRes > 0): ?>
                                                <span style="font-weight:700; color:#DC2626; background:rgba(220,38,38,0.06); padding:2px 7px; border-radius:6px;">
                                                    🔒 <?= $totRes ?> sep.
                                                </span>
                                            <?php else: ?>
                                                <span style="color:#9CA3AF;">0</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align:center;" class="col-disponible">
                                            <?php if ($totDisp > 0): ?>
                                                <span class="stock-badge-disp"><?= $totDisp ?> und.</span>
                                            <?php else: ?>
                                                <span class="stock-badge-zero">0 und.</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align:center;" class="col-estado">
                                            <?php if ($totDisp > 3): ?>
                                                <span class="status-badge-stock badge-instock">En Stock</span>
                                            <?php elseif ($totDisp > 0): ?>
                                                <span class="status-badge-stock badge-lowstock">Bajo Stock</span>
                                            <?php else: ?>
                                                <span class="status-badge-stock badge-nostock">Agotado</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align:center;">
                                            <button type="button" class="btn-other-stores" onclick="verDesglose(<?= htmlspecialchars(json_encode($e['producto_nombre'] . ' (' . $e['color_nombre'] . ')')) ?>, <?= $jsonStores ?>)">
                                                <i class="fas fa-shop"></i> Ver Locales
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if (empty($existencias)): ?>
                        <div style="text-align:center; padding:3.5rem; color:#9CA3AF;">
                            <i class="fas fa-boxes-stacked" style="font-size:2.5rem; margin-bottom:0.8rem; opacity:0.35; display:block;"></i>
                            No hay existencias registradas en el sistema.
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>

    <!-- Modal Desglose Rápido por Local -->
    <div class="modal-overlay" id="modalDesglose">
        <div class="modal-box">
            <div class="modal-header">
                <div>
                    <h3 style="font-size:1.05rem; font-weight:800; color:#111827; margin:0;" id="modalProdTitle">Producto</h3>
                    <p style="font-size:0.78rem; color:#6B7280; margin:3px 0 0 0;">Disponibilidad física y reservas por local</p>
                </div>
                <button type="button" class="btn-icon-soft edit" onclick="cerrarModal('modalDesglose')" style="background:none; border:none; font-size:1.2rem; cursor:pointer;">&times;</button>
            </div>
            <div class="modal-body" id="modalStoresList" style="max-height:60vh; overflow-y:auto;">
                <!-- Filled via JS -->
            </div>
            <div class="modal-footer">
                <span style="font-size:0.8rem; color:#6B7280;" id="modalTotalSummary"></span>
                <a href="/carpicenter_sys/modules/transferencias/transferencia_form.php" class="btn btn-primary" style="font-size:0.82rem; padding:0.45rem 0.9rem; border-radius:8px;">
                    <i class="fas fa-truck-ramp-box" style="margin-right:5px;"></i> Solicitar Traslado
                </a>
            </div>
        </div>
    </div>

    <script>
    let currentLocalId = 'all';
    const localesInfo = <?= json_encode($locales) ?>;
    const localTotalsInfo = <?= json_encode($localTotals) ?>;
    const localTotalsResInfo = <?= json_encode($localTotalsReservado) ?>;
    const grandTotalActual = <?= $grandTotal ?>;
    const grandTotalRes = <?= $grandTotalReservado ?>;
    const grandTotalDisp = <?= $grandTotalDisponible ?>;

    function selectLocalTab(localId, localName) {
        currentLocalId = localId;

        // Update active tab button
        document.querySelectorAll('.inv-tab-btn').forEach(btn => btn.classList.remove('active'));
        if (localId === 'all') {
            document.getElementById('tabAll').classList.add('active');
            document.getElementById('tableTitle').innerHTML = '<i class="fas fa-globe" style="color:#E31E24;"></i> Existencias — Consolidado General';
            document.getElementById('kpiFilterContext').textContent = 'Vista Global';
            document.getElementById('kpiStockFisico').textContent = grandTotalActual.toLocaleString('es-PE');
            document.getElementById('kpiStockReservado').textContent = grandTotalRes.toLocaleString('es-PE');
            document.getElementById('kpiStockDisponible').textContent = grandTotalDisp.toLocaleString('es-PE');
        } else {
            const btn = document.getElementById('tabLocal' + localId);
            if (btn) btn.classList.add('active');
            document.getElementById('tableTitle').innerHTML = '<i class="fas fa-store" style="color:#2563EB;"></i> Stock en: ' + localName;
            document.getElementById('kpiFilterContext').textContent = localName;

            const act = localTotalsInfo[localId] || 0;
            const res = localTotalsResInfo[localId] || 0;
            const disp = Math.max(0, act - res);

            document.getElementById('kpiStockFisico').textContent = act.toLocaleString('es-PE');
            document.getElementById('kpiStockReservado').textContent = res.toLocaleString('es-PE');
            document.getElementById('kpiStockDisponible').textContent = disp.toLocaleString('es-PE');
        }

        applyFilters();
    }

    function applyFilters() {
        const search = document.getElementById('invSearchInput').value.toLowerCase().trim();
        const cat = document.getElementById('invCatFilter').value.toLowerCase();
        const dispFilter = document.getElementById('invDisponibilidadFilter').value;
        const rows = document.querySelectorAll('#invTableBody .inv-row');

        let visibleCount = 0;

        rows.forEach(row => {
            const nombre = row.dataset.nombre || '';
            const rowCat = row.dataset.cat || '';
            const stores = JSON.parse(row.dataset.stores || '[]');

            // Match Search & Category
            const matchSearch = !search || nombre.includes(search);
            const matchCat = !cat || rowCat.includes(cat);

            // Determine stock for selected local or global
            let actual = 0;
            let reservado = 0;
            let disponible = 0;

            if (currentLocalId === 'all') {
                actual = parseInt(row.dataset.totalAct) || 0;
                reservado = parseInt(row.dataset.totalRes) || 0;
                disponible = parseInt(row.dataset.totalDisp) || 0;
            } else {
                const storeObj = stores.find(s => s.local_id == currentLocalId);
                if (storeObj) {
                    actual = storeObj.actual;
                    reservado = storeObj.reservado;
                    disponible = storeObj.disponible;
                }
            }

            // Update row numbers dynamically
            const colFisico = row.querySelector('.col-fisico');
            const colRes = row.querySelector('.col-reservado');
            const colDisp = row.querySelector('.col-disponible');
            const colEst = row.querySelector('.col-estado');

            if (colFisico) colFisico.textContent = actual;
            if (colRes) {
                if (reservado > 0) {
                    colRes.innerHTML = `<span style="font-weight:700; color:#DC2626; background:rgba(220,38,38,0.06); padding:2px 7px; border-radius:6px;">🔒 ${reservado} sep.</span>`;
                } else {
                    colRes.innerHTML = `<span style="color:#9CA3AF;">0</span>`;
                }
            }
            if (colDisp) {
                if (disponible > 0) {
                    colDisp.innerHTML = `<span class="stock-badge-disp">${disponible} und.</span>`;
                } else {
                    colDisp.innerHTML = `<span class="stock-badge-zero">0 und.</span>`;
                }
            }
            if (colEst) {
                if (disponible > 3) {
                    colEst.innerHTML = `<span class="status-badge-stock badge-instock">En Stock</span>`;
                } else if (disponible > 0) {
                    colEst.innerHTML = `<span class="status-badge-stock badge-lowstock">Bajo Stock</span>`;
                } else {
                    colEst.innerHTML = `<span class="status-badge-stock badge-nostock">Agotado</span>`;
                }
            }

            // Match Disponibilidad Filter
            let matchDisp = true;
            if (dispFilter === 'disponible') matchDisp = (disponible > 0);
            else if (dispFilter === 'agotado') matchDisp = (disponible === 0);
            else if (dispFilter === 'reservado') matchDisp = (reservado > 0);

            const show = matchSearch && matchCat && matchDisp;
            row.style.display = show ? '' : 'none';
            if (show) visibleCount++;
        });

        document.getElementById('visibleCount').textContent = visibleCount;
        document.getElementById('kpiTotalVariantes').textContent = visibleCount;
    }

    function resetFilters() {
        document.getElementById('invSearchInput').value = '';
        document.getElementById('invCatFilter').value = '';
        document.getElementById('invDisponibilidadFilter').value = 'all';
        selectLocalTab('all', 'Consolidado General');
    }

    function verDesglose(prodName, stores) {
        document.getElementById('modalProdTitle').textContent = prodName;
        const container = document.getElementById('modalStoresList');
        container.innerHTML = '';

        let totFis = 0;
        let totDisp = 0;

        stores.forEach(s => {
            totFis += s.actual;
            totDisp += s.disponible;
            const isAlm = s.tipo === 'Almacen';
            const icon = isAlm ? 'fa-warehouse' : 'fa-store';
            const badgeBg = isAlm ? '#FEF2F2' : '#EFF6FF';
            const badgeColor = isAlm ? '#DC2626' : '#2563EB';

            const item = document.createElement('div');
            item.className = 'store-breakdown-item';
            item.innerHTML = `
                <div style="display:flex; align-items:center; gap:10px;">
                    <div style="width:36px; height:36px; border-radius:8px; background:${badgeBg}; color:${badgeColor}; display:flex; align-items:center; justify-content:center; font-size:0.95rem;">
                        <i class="fas ${icon}"></i>
                    </div>
                    <div>
                        <div style="font-weight:700; color:#1E293B; font-size:0.88rem;">${s.nombre}</div>
                        <div style="font-size:0.74rem; color:#64748B;">${s.tipo} · Físico: <strong>${s.actual}</strong> ${s.reservado > 0 ? `| 🔒 ${s.reservado} reserv.` : ''}</div>
                    </div>
                </div>
                <div>
                    ${s.disponible > 0 
                        ? `<span class="stock-badge-disp" style="font-size:0.85rem; padding:3px 8px;">${s.disponible} disp.</span>`
                        : `<span class="stock-badge-zero" style="font-size:0.8rem; padding:3px 8px;">Agotado</span>`
                    }
                </div>
            `;
            container.appendChild(item);
        });

        document.getElementById('modalTotalSummary').innerHTML = `Total Físico: <strong>${totFis}</strong> | Disponible Global: <strong style="color:#059669;">${totDisp}</strong>`;
        abrirModal('modalDesglose');
    }

    function exportarExcel() {
        const search = document.getElementById('invSearchInput').value.trim();
        const cat = document.getElementById('invCatFilter').value.trim();
        const disp = document.getElementById('invDisponibilidadFilter').value;

        const params = new URLSearchParams();
        params.append('local_id', currentLocalId);
        if (search) params.append('search', search);
        if (cat) params.append('categoria', cat);
        if (disp && disp !== 'all') params.append('disponibilidad', disp);

        window.location.href = 'export_inventario.php?' + params.toString();
    }

    function imprimirInventario() {
        const subtitleEl = document.getElementById('printReportSubtitle');
        const filtrosEl = document.getElementById('printFiltrosInfo');
        if (subtitleEl) {
            subtitleEl.textContent = 'Reporte de Inventario — ' + currentLocalName;
        }
        if (filtrosEl) {
            const search = document.getElementById('invSearchInput').value.trim();
            const cat = document.getElementById('invCatFilter').value.trim();
            const disp = document.getElementById('invDisponibilidadFilter').value;
            let fList = [];
            if (cat) fList.push('Cat: ' + cat);
            if (search) fList.push('Buscar: ' + search);
            if (disp && disp !== 'all') {
                if (disp === 'disponible') fList.push('Solo Disponibles');
                else if (disp === 'agotado') fList.push('Agotados');
                else if (disp === 'reservado') fList.push('Con Reservas');
            }
            filtrosEl.textContent = fList.length > 0 ? fList.join(' | ') : 'Todos los artículos visibles';
        }
        window.print();
    }

    function abrirModal(id) { document.getElementById(id).classList.add('open'); }
    function cerrarModal(id) { document.getElementById(id).classList.remove('open'); }
    </script>
</body>
</html>
