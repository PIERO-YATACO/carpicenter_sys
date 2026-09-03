<?php
require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../config/db.php';

$page_title = 'Resumen Financiero';
$page_subtitle = 'Panel interactivo de liquidez, analítica gráfica y consolidado financiero';

// 1. KPIs Globales
// Cuentas por Cobrar pendientes reales (saldo > 0)
$tot_cobrar = floatval($db->query("
    SELECT COALESCE(SUM(monto_total - monto_pagado), 0) 
    FROM finanzas_cuentas_cobrar 
    WHERE (monto_total - monto_pagado) > 0.001
")->fetchColumn());

$tot_cobrado = floatval($db->query("
    SELECT COALESCE(SUM(monto_pagado), 0) 
    FROM finanzas_cuentas_cobrar
")->fetchColumn());

$tot_facturado = floatval($db->query("
    SELECT COALESCE(SUM(monto_total), 0) 
    FROM finanzas_cuentas_cobrar
")->fetchColumn());

// Pasivos pendientes
$tot_sunat = floatval($db->query("
    SELECT COALESCE(SUM(importe), 0) 
    FROM finanzas_sunat 
    WHERE estado NOT IN ('CANCELADO', 'PAGADO')
")->fetchColumn());

$tot_sat = floatval($db->query("
    SELECT COALESCE(SUM(por_pagar), 0) 
    FROM finanzas_sat 
    WHERE estado NOT IN ('CANCELADO', 'PAGADO')
")->fetchColumn());

$tot_letras = floatval($db->query("
    SELECT COALESCE(SUM(monto_soles), 0) 
    FROM finanzas_bancos_letras 
    WHERE tipo = 'LETRA_PROVEEDOR' AND estado NOT IN ('PAGADO', 'CANCELADO')
")->fetchColumn());

$tot_bancos = floatval($db->query("
    SELECT COALESCE(SUM(monto_soles), 0) 
    FROM finanzas_bancos_letras 
    WHERE tipo != 'LETRA_PROVEEDOR' AND estado NOT IN ('PAGADO', 'CANCELADO')
")->fetchColumn());

$tot_pagado_general = floatval($db->query("
    SELECT COALESCE(SUM(monto_soles), 0) 
    FROM finanzas_bancos_letras 
    WHERE estado = 'PAGADO'
")->fetchColumn());

$tot_pasivos = $tot_sunat + $tot_sat + $tot_letras + $tot_bancos;
$flujo_neto = $tot_cobrar - $tot_pasivos;
$ratio_liquidez = $tot_pasivos > 0 ? round($tot_cobrar / $tot_pasivos, 2) : 100;

// Datos para los Gráficos Chart.js
$chart_liquidez_labels = ['Por Cobrar (Pend.)', 'Recaudado (Real)', 'Total Deuda (Pasivos)', 'Deudas Pagadas'];
$chart_liquidez_data = [$tot_cobrar, $tot_cobrado, $tot_pasivos, $tot_pagado_general];

$chart_distribucion_labels = ['Letras Prov.', 'Bancos / Créd.', 'SUNAT', 'SAT / Mun.'];
$chart_distribucion_data = [$tot_letras, $tot_bancos, $tot_sunat, $tot_sat];

// 2. Parámetros de Filtro y Paginación para el Consolidado
$search = trim($_GET['search'] ?? '');
$tipo_filtro = $_GET['tipo'] ?? '';
$estado_filtro = $_GET['estado'] ?? '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

// Consulta Unificada (UNION ALL de todas las obligaciones y cobranzas)
$union_sql = "
    SELECT 
        'COBRANZA' as origen,
        id,
        cliente as entidad,
        COALESCE(ft_lt, referencia, 'Cobro') as concepto,
        monto_total as monto,
        (monto_total - monto_pagado) as saldo_pendiente,
        f_venc,
        CASE 
            WHEN (monto_total - monto_pagado) <= 0.001 THEN 'PAGADO'
            WHEN monto_pagado > 0 THEN 'PARCIAL'
            ELSE 'PENDIENTE'
        END as estado,
        fecha_pago as f_pago
    FROM finanzas_cuentas_cobrar
    UNION ALL
    SELECT 
        'LETRA_PROVEEDOR' as origen,
        id,
        banco_proveedor as entidad,
        COALESCE(nro_unico, factura_ref, 'Letra') as concepto,
        monto_soles as monto,
        monto_soles as saldo_pendiente,
        f_venc,
        estado,
        f_pago
    FROM finanzas_bancos_letras WHERE tipo = 'LETRA_PROVEEDOR'
    UNION ALL
    SELECT 
        'BANCO' as origen,
        id,
        banco_proveedor as entidad,
        COALESCE(nro_unico, 'Cuota Banco') as concepto,
        monto_soles as monto,
        monto_soles as saldo_pendiente,
        f_venc,
        estado,
        f_pago
    FROM finanzas_bancos_letras WHERE tipo != 'LETRA_PROVEEDOR'
    UNION ALL
    SELECT 
        'SUNAT' as origen,
        id,
        'SUNAT' as entidad,
        (tributo || ' ' || COALESCE(periodo, '')) as concepto,
        importe as monto,
        importe as saldo_pendiente,
        NULL as f_venc,
        estado,
        f_pago
    FROM finanzas_sunat
    UNION ALL
    SELECT 
        'SAT' as origen,
        id,
        'SAT / MUNICIPALIDAD' as entidad,
        (tipo_infraccion || ' ' || COALESCE(nro_documento, '')) as concepto,
        por_pagar as monto,
        por_pagar as saldo_pendiente,
        NULL as f_venc,
        estado,
        f_pago
    FROM finanzas_sat
";

// Aplicar filtros a la consulta unificada
$where_clauses = [];
$params = [];

if (!empty($search)) {
    $where_clauses[] = "(entidad ILIKE ? OR concepto ILIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if (!empty($tipo_filtro)) {
    $where_clauses[] = "origen = ?";
    $params[] = $tipo_filtro;
}
if (!empty($estado_filtro)) {
    if ($estado_filtro === 'PAGADO') {
        $where_clauses[] = "(estado = 'PAGADO' OR estado = 'CANCELADO')";
    } else {
        $where_clauses[] = "(estado != 'PAGADO' AND estado != 'CANCELADO')";
    }
}

$where_str = !empty($where_clauses) ? " WHERE " . implode(" AND ", $where_clauses) : "";

// Conteo total para paginación
$count_query = "SELECT COUNT(*) FROM ($union_sql) as c " . $where_str;
$stmt_count = $db->prepare($count_query);
$stmt_count->execute($params);
$total_registros = $stmt_count->fetchColumn();
$total_pages = ceil($total_registros / $limit);

// Registros paginados
$data_query = "SELECT * FROM ($union_sql) as c " . $where_str . " ORDER BY (estado != 'PAGADO' AND estado != 'CANCELADO') DESC, f_venc ASC NULLS LAST, id DESC LIMIT $limit OFFSET $offset";
$stmt_data = $db->prepare($data_query);
$stmt_data->execute($params);
$movimientos = $stmt_data->fetchAll(PDO::FETCH_ASSOC);
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
        /* ===== RESUMEN FINANCIERO PREMIUM ===== */
        .resumen-hero {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .resumen-hero-title h1 {
            font-size: 1.45rem;
            font-weight: 800;
            color: #111827;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .resumen-hero-title p {
            color: #6B7280;
            font-size: 0.84rem;
            margin: 0.25rem 0 0 0;
        }
        .resumen-hero-actions {
            display: flex;
            gap: 0.6rem;
            align-items: center;
        }

        /* KPI Cards Grid */
        .resumen-kpis-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(235px, 1fr));
            gap: 1.2rem;
            margin-bottom: 1.5rem;
        }
        .resumen-kpi-card {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 14px;
            padding: 1.25rem 1.4rem;
            display: flex;
            align-items: center;
            gap: 1.1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .resumen-kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.06);
        }
        .resumen-kpi-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
            flex-shrink: 0;
        }
        .icon-emerald-bg {
            background: linear-gradient(135deg, rgba(5,150,105,0.12) 0%, rgba(16,185,129,0.22) 100%);
            color: #059669;
        }
        .icon-rose-bg {
            background: linear-gradient(135deg, rgba(220,38,38,0.12) 0%, rgba(244,63,94,0.22) 100%);
            color: #DC2626;
        }
        .icon-indigo-bg {
            background: linear-gradient(135deg, rgba(79,70,229,0.12) 0%, rgba(99,102,241,0.22) 100%);
            color: #4F46E5;
        }
        .icon-amber-bg {
            background: linear-gradient(135deg, rgba(217,119,6,0.12) 0%, rgba(245,158,11,0.22) 100%);
            color: #D97706;
        }
        .resumen-kpi-info {
            flex: 1;
            min-width: 0;
        }
        .resumen-kpi-info span.label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
            color: #6B7280;
            display: block;
            margin-bottom: 0.2rem;
        }
        .resumen-kpi-info h3 {
            font-size: 1.35rem;
            font-weight: 800;
            color: #111827;
            margin: 0;
            line-height: 1.2;
            letter-spacing: -0.3px;
        }
        .resumen-kpi-info span.sub-tag {
            font-size: 0.74rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            margin-top: 0.35rem;
            padding: 2px 8px;
            border-radius: 20px;
        }

        /* Charts Layout */
        .resumen-charts-grid {
            display: grid;
            grid-template-columns: 1.8fr 1.2fr;
            gap: 1.25rem;
            margin-bottom: 1.5rem;
        }
        @media (max-width: 980px) {
            .resumen-charts-grid {
                grid-template-columns: 1fr;
            }
        }
        .resumen-chart-card {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 14px;
            padding: 1.3rem 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
            display: flex;
            flex-direction: column;
        }
        .resumen-chart-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.2rem;
            padding-bottom: 0.8rem;
            border-bottom: 1px solid #F3F4F6;
        }
        .resumen-chart-header h3 {
            font-size: 0.95rem;
            font-weight: 700;
            color: #111827;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .resumen-chart-canvas-box {
            position: relative;
            height: 270px;
            width: 100%;
            flex: 1;
        }

        /* Filter Box */
        .resumen-filter-card {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 14px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.25rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        .resumen-filter-form {
            display: flex;
            gap: 0.85rem;
            flex-wrap: wrap;
            align-items: center;
        }
        .resumen-search-box {
            flex: 2;
            min-width: 250px;
            position: relative;
        }
        .resumen-search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #9CA3AF;
            font-size: 0.9rem;
        }
        .resumen-search-box input {
            width: 100%;
            padding: 0.58rem 0.85rem 0.58rem 2.25rem;
            border-radius: 10px;
            border: 1px solid #D1D5DB;
            background: #F9FAFB;
            font-size: 0.85rem;
            color: #111827;
            outline: none;
            box-sizing: border-box;
            transition: all 0.2s;
        }
        .resumen-search-box input:focus {
            background: #FFFFFF;
            border-color: #E31E24;
            box-shadow: 0 0 0 3px rgba(227,30,36,0.1);
        }
        .resumen-select {
            padding: 0.58rem 2rem 0.58rem 0.85rem;
            border-radius: 10px;
            border: 1px solid #D1D5DB;
            background: #F9FAFB;
            font-size: 0.85rem;
            font-weight: 500;
            color: #374151;
            outline: none;
            cursor: pointer;
            min-width: 180px;
            transition: all 0.2s;
        }
        .resumen-select:focus {
            border-color: #E31E24;
            background: #FFFFFF;
        }

        /* Consolidated Table */
        .resumen-table-card {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        }
        .resumen-table-header-title {
            padding: 1rem 1.4rem;
            border-bottom: 1px solid #F3F4F6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .resumen-table-header-title h3 {
            font-size: 0.95rem;
            font-weight: 700;
            color: #111827;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .resumen-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        .resumen-table th {
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
        .resumen-table td {
            padding: 0.95rem 1rem;
            border-bottom: 1px solid #F3F4F6;
            font-size: 0.85rem;
            color: #374151;
            vertical-align: middle;
        }
        .resumen-table tbody tr {
            transition: background 0.15s ease;
        }
        .resumen-table tbody tr:hover {
            background: #F9FAFB;
        }

        /* Type Badges */
        .badge-type {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 9px;
            border-radius: 6px;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.4px;
            text-transform: uppercase;
        }
        .badge-cobranza { background: #ECFDF5; color: #059669; border: 1px solid #A7F3D0; }
        .badge-letra { background: #F5F3FF; color: #7C3AED; border: 1px solid #DDD6FE; }
        .badge-banco { background: #EFF6FF; color: #2563EB; border: 1px solid #BFDBFE; }
        .badge-sunat { background: #FFFBEB; color: #D97706; border: 1px solid #FDE68A; }
        .badge-sat { background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA; }

        /* Status Pills */
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
        .status-pill.pagado { background: rgba(5,150,105,0.1); color: #059669; border: 1px solid rgba(5,150,105,0.25); }
        .status-pill.pendiente { background: rgba(217,119,6,0.1); color: #D97706; border: 1px solid rgba(217,119,6,0.25); }
        .status-pill.parcial { background: rgba(37,99,235,0.1); color: #2563EB; border: 1px solid rgba(37,99,235,0.25); }
    </style>
</head>
<body>
    <div class="app-wrapper">
        <?php include __DIR__ . '/../../views/partials/sidebar.php'; ?>
        <div class="main-content">
            <?php include __DIR__ . '/../../views/partials/header.php'; ?>
            <div class="page-content">

                <!-- Header de la Página -->
                <div class="resumen-hero">
                    <div class="resumen-hero-title">
                        <h1><i class="fas fa-chart-pie" style="color:#E31E24;"></i> Resumen Financiero y Liquidez</h1>
                        <p>Tablero ejecutivo de balance, posición de tesorería y consolidado de obligaciones</p>
                    </div>
                    <div class="resumen-hero-actions">
                        <a href="cuentas_cobrar.php" class="btn btn-outline" style="border-color:#059669; color:#059669; font-weight:600; padding:0.55rem 1rem; border-radius:10px;">
                            <i class="fas fa-hand-holding-dollar" style="margin-right:6px;"></i> Ir a Cobranzas
                        </a>
                        <a href="obligaciones_bancarias.php" class="btn btn-outline" style="border-color:#2563EB; color:#2563EB; font-weight:600; padding:0.55rem 1rem; border-radius:10px;">
                            <i class="fas fa-building-columns" style="margin-right:6px;"></i> Letras y Bancos
                        </a>
                    </div>
                </div>

                <!-- KPI Cards Principales -->
                <div class="resumen-kpis-grid">
                    <div class="resumen-kpi-card">
                        <div class="resumen-kpi-icon icon-emerald-bg">
                            <i class="fas fa-arrow-down-left"></i>
                        </div>
                        <div class="resumen-kpi-info">
                            <span class="label">Cuentas por Cobrar</span>
                            <h3 style="color:#059669;"><?= formatearMonto($tot_cobrar) ?></h3>
                            <span class="sub-tag" style="background:#ECFDF5; color:#059669;">
                                <i class="fas fa-circle-check"></i> <?= formatearMonto($tot_cobrado) ?> Cobrado
                            </span>
                        </div>
                    </div>

                    <div class="resumen-kpi-card">
                        <div class="resumen-kpi-icon icon-rose-bg">
                            <i class="fas fa-arrow-up-right"></i>
                        </div>
                        <div class="resumen-kpi-info">
                            <span class="label">Total Obligaciones</span>
                            <h3 style="color:#DC2626;"><?= formatearMonto($tot_pasivos) ?></h3>
                            <span class="sub-tag" style="background:#FEF2F2; color:#DC2626;">
                                <i class="fas fa-clock"></i> Pasivos por Liquidar
                            </span>
                        </div>
                    </div>

                    <div class="resumen-kpi-card">
                        <div class="resumen-kpi-icon <?= $flujo_neto >= 0 ? 'icon-indigo-bg' : 'icon-rose-bg' ?>">
                            <i class="fas fa-scale-balanced"></i>
                        </div>
                        <div class="resumen-kpi-info">
                            <span class="label">Balance Neto Estimado</span>
                            <h3 style="color:<?= $flujo_neto >= 0 ? '#4F46E5' : '#DC2626' ?>;"><?= formatearMonto($flujo_neto) ?></h3>
                            <span class="sub-tag" style="background:<?= $flujo_neto >= 0 ? '#EEF2FF' : '#FEF2F2' ?>; color:<?= $flujo_neto >= 0 ? '#4F46E5' : '#DC2626' ?>;">
                                <i class="fas <?= $flujo_neto >= 0 ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down' ?>"></i> <?= $flujo_neto >= 0 ? 'Superávit Operativo' : 'Déficit Proyectado' ?>
                            </span>
                        </div>
                    </div>

                    <div class="resumen-kpi-card">
                        <div class="resumen-kpi-icon icon-amber-bg">
                            <i class="fas fa-layer-group"></i>
                        </div>
                        <div class="resumen-kpi-info">
                            <span class="label">Total Operaciones</span>
                            <h3><?= $total_registros ?></h3>
                            <span class="sub-tag" style="background:#FFFBEB; color:#D97706;">
                                <i class="fas fa-file-invoice"></i> Cartera Consolidada
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Sección de Gráficos Interactivos (Chart.js) -->
                <div class="resumen-charts-grid">
                    <!-- Gráfico 1: Comparativa de Liquidez -->
                    <div class="resumen-chart-card">
                        <div class="resumen-chart-header">
                            <h3><i class="fas fa-chart-column" style="color:#4F46E5;"></i> Comparativa de Fondos (Activos vs Pasivos)</h3>
                            <span style="font-size:0.75rem; background:#F3F4F6; color:#4B5563; padding:3px 8px; border-radius:6px; font-weight:600;">Moneda: Soles (S/)</span>
                        </div>
                        <div class="resumen-chart-canvas-box">
                            <canvas id="barChartLiquidez"></canvas>
                        </div>
                    </div>

                    <!-- Gráfico 2: Composición de Pasivos -->
                    <div class="resumen-chart-card">
                        <div class="resumen-chart-header">
                            <h3><i class="fas fa-chart-pie" style="color:#DC2626;"></i> Distribución de Pasivos</h3>
                            <span style="font-size:0.75rem; background:#FEF2F2; color:#DC2626; padding:3px 8px; border-radius:6px; font-weight:700;">Deuda Activa</span>
                        </div>
                        <div class="resumen-chart-canvas-box">
                            <canvas id="doughnutChartPasivos"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Filtros del Consolidado -->
                <div class="resumen-filter-card">
                    <form method="GET" class="resumen-filter-form">
                        <div class="resumen-search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" name="search" placeholder="Buscar por cliente, proveedor, banco o concepto..." value="<?= htmlspecialchars($search) ?>">
                        </div>

                        <select name="tipo" class="resumen-select" onchange="this.form.submit()">
                            <option value="">Todos los Orígenes</option>
                            <option value="COBRANZA" <?= $tipo_filtro==='COBRANZA'?'selected':'' ?>>🟢 Cobranzas a Clientes</option>
                            <option value="LETRA_PROVEEDOR" <?= $tipo_filtro==='LETRA_PROVEEDOR'?'selected':'' ?>>🟣 Letras Proveedores</option>
                            <option value="BANCO" <?= $tipo_filtro==='BANCO'?'selected':'' ?>>🔵 Créditos Bancarios</option>
                            <option value="SUNAT" <?= $tipo_filtro==='SUNAT'?'selected':'' ?>>🟠 Impuestos SUNAT</option>
                            <option value="SAT" <?= $tipo_filtro==='SAT'?'selected':'' ?>>🔴 Papeletas SAT / Mun.</option>
                        </select>

                        <select name="estado" class="resumen-select" onchange="this.form.submit()">
                            <option value="">Todos los Estados</option>
                            <option value="PENDIENTE" <?= $estado_filtro==='PENDIENTE'?'selected':'' ?>>⏳ Solo Pendientes</option>
                            <option value="PAGADO" <?= $estado_filtro==='PAGADO'?'selected':'' ?>>✅ Solo Pagados / Cancelados</option>
                        </select>

                        <button type="submit" class="btn btn-primary" style="padding:0.58rem 1.1rem; border-radius:10px; font-weight:600;">
                            <i class="fas fa-filter"></i> Filtrar
                        </button>
                        
                        <?php if(!empty($search) || !empty($tipo_filtro) || !empty($estado_filtro)): ?>
                            <a href="resumen_financiero.php" class="btn btn-outline" style="padding:0.58rem 0.9rem; border-radius:10px;" title="Limpiar Filtros">
                                <i class="fas fa-times"></i>
                            </a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Tabla de Consolidado -->
                <div class="resumen-table-card">
                    <div class="resumen-table-header-title">
                        <h3><i class="fas fa-list-check" style="color:#4F46E5;"></i> Consolidado General de Obligaciones y Cobros</h3>
                        <span style="font-size:0.8rem; color:#6B7280; font-weight:500;">
                            Mostrando <?= count($movimientos) ?> de <?= $total_registros ?> registros
                        </span>
                    </div>

                    <div style="overflow-x:auto;">
                        <table class="resumen-table">
                            <thead>
                                <tr>
                                    <th>Tipo de Operación</th>
                                    <th>Entidad / Cliente / Proveedor</th>
                                    <th>Concepto / Documento</th>
                                    <th>F. Vencimiento</th>
                                    <th style="text-align:right;">Monto Total</th>
                                    <th>Estado</th>
                                    <th>F. Pago</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($movimientos)): ?>
                                    <tr>
                                        <td colspan="7" style="text-align:center; padding:3.5rem; color:#9CA3AF;">
                                            <i class="fas fa-folder-open" style="font-size:2.5rem; margin-bottom:0.8rem; opacity:0.35; display:block;"></i>
                                            No se encontraron operaciones registradas con los filtros seleccionados.
                                        </td>
                                    </tr>
                                <?php else: foreach($movimientos as $m): 
                                    $is_ingreso = ($m['origen'] === 'COBRANZA');
                                    
                                    // Origin Badge
                                    $badgeClass = 'badge-cobranza';
                                    $tipoLabel = 'COBRANZA';
                                    $tipoIcon = 'fa-arrow-down-left';
                                    if ($m['origen'] === 'LETRA_PROVEEDOR') {
                                        $badgeClass = 'badge-letra';
                                        $tipoLabel = 'LETRA PROV';
                                        $tipoIcon = 'fa-file-signature';
                                    } elseif ($m['origen'] === 'BANCO') {
                                        $badgeClass = 'badge-banco';
                                        $tipoLabel = 'CRÉD. BANCO';
                                        $tipoIcon = 'fa-building-columns';
                                    } elseif ($m['origen'] === 'SUNAT') {
                                        $badgeClass = 'badge-sunat';
                                        $tipoLabel = 'SUNAT';
                                        $tipoIcon = 'fa-landmark';
                                    } elseif ($m['origen'] === 'SAT') {
                                        $badgeClass = 'badge-sat';
                                        $tipoLabel = 'SAT / MUN';
                                        $tipoIcon = 'fa-shield-halved';
                                    }

                                    // Status Pill
                                    $isPagado = in_array(strtoupper($m['estado']), ['PAGADO', 'CANCELADO']);
                                    $isParcial = (strtoupper($m['estado']) === 'PARCIAL');
                                ?>
                                    <tr>
                                        <td>
                                            <span class="badge-type <?= $badgeClass ?>">
                                                <i class="fas <?= $tipoIcon ?>"></i> <?= $tipoLabel ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="font-weight:700; color:#111827; font-size:0.88rem;">
                                                <?= htmlspecialchars($m['entidad'] ?? '—') ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span style="color:#4B5563; font-weight:500;"><?= htmlspecialchars($m['concepto'] ?? '—') ?></span>
                                        </td>
                                        <td>
                                            <?php if (!empty($m['f_venc'])): ?>
                                                <span style="font-weight:600; color:#4B5563; font-size:0.82rem;">
                                                    <?= date('d/m/Y', strtotime($m['f_venc'])) ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="color:#9CA3AF; font-size:0.8rem;">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align:right; font-weight:800; color:<?= $is_ingreso ? '#059669' : '#DC2626' ?>;">
                                            <?= ($is_ingreso ? '+ ' : '- ') . formatearMonto($m['monto']) ?>
                                        </td>
                                        <td>
                                            <?php if($isPagado): ?>
                                                <span class="status-pill pagado">
                                                    <i class="fas fa-circle-check"></i> PAGADO
                                                </span>
                                            <?php elseif($isParcial): ?>
                                                <span class="status-pill parcial">
                                                    <i class="fas fa-circle-dot"></i> PARCIAL
                                                </span>
                                            <?php else: ?>
                                                <span class="status-pill pendiente">
                                                    <i class="fas fa-clock"></i> PENDIENTE
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="color:#6B7280; font-size:0.82rem;">
                                            <?= !empty($m['f_pago']) ? date('d/m/Y', strtotime($m['f_pago'])) : '<span style="color:#9CA3AF;">—</span>' ?>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Barra de Paginación -->
                    <?php if($total_pages > 1): 
                        $query_params = $_GET;
                    ?>
                        <div style="padding:1rem 1.4rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; border-top:1px solid #F3F4F6;">
                            <div style="font-size:0.82rem; color:#6B7280;">
                                Mostrando <strong><?= ($offset + 1) ?> - <?= min($offset + $limit, $total_registros) ?></strong> de <strong><?= $total_registros ?></strong> registros consolidados
                            </div>
                            <div style="display:flex; gap:0.4rem; align-items:center;">
                                <?php if($page > 1): 
                                    $query_params['page'] = $page - 1;
                                ?>
                                    <a href="?<?= http_build_query($query_params) ?>" class="btn btn-outline" style="padding:4px 10px; font-size:0.8rem; border-radius:6px;">&laquo; Anterior</a>
                                <?php endif; ?>

                                <?php for($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): 
                                    $query_params['page'] = $i;
                                ?>
                                    <a href="?<?= http_build_query($query_params) ?>" class="btn <?= $i == $page ? 'btn-primary' : 'btn-outline' ?>" style="padding:4px 10px; font-size:0.8rem; border-radius:6px; min-width:32px; text-align:center;">
                                        <?= $i ?>
                                    </a>
                                <?php endfor; ?>

                                <?php if($page < $total_pages): 
                                    $query_params['page'] = $page + 1;
                                ?>
                                    <a href="?<?= http_build_query($query_params) ?>" class="btn btn-outline" style="padding:4px 10px; font-size:0.8rem; border-radius:6px;">Siguiente &raquo;</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>

            </div>
        </div>
    </div>

    <!-- Script de Gráficos Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        // 1. Gráfico de Barras de Flujo
        const barCtx = document.getElementById('barChartLiquidez');
        if (barCtx) {
            new Chart(barCtx, {
                type: 'bar',
                data: {
                    labels: <?= json_encode($chart_liquidez_labels) ?>,
                    datasets: [{
                        label: 'Importe en Soles (S/)',
                        data: <?= json_encode($chart_liquidez_data) ?>,
                        backgroundColor: [
                            'rgba(16, 185, 129, 0.8)',  // Emerald
                            'rgba(59, 130, 246, 0.8)',   // Blue
                            'rgba(239, 68, 68, 0.8)',    // Rose
                            'rgba(245, 158, 11, 0.8)'    // Amber
                        ],
                        borderColor: [
                            '#059669',
                            '#2563EB',
                            '#DC2626',
                            '#D97706'
                        ],
                        borderWidth: 1.5,
                        borderRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#1E293B',
                            titleFont: { size: 12, weight: 'bold' },
                            bodyFont: { size: 12 },
                            padding: 10,
                            cornerRadius: 8,
                            callbacks: {
                                label: function(context) {
                                    let value = context.parsed.y || 0;
                                    return ' S/ ' + value.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { font: { family: 'Inter', size: 11, weight: '500' }, color: '#6B7280' }
                        },
                        y: {
                            grid: { color: 'rgba(0, 0, 0, 0.04)' },
                            ticks: {
                                callback: function(value) {
                                    return 'S/ ' + (value >= 1000 ? (value/1000).toFixed(0) + 'k' : value);
                                },
                                font: { family: 'Inter', size: 10 },
                                color: '#9CA3AF'
                            }
                        }
                    }
                }
            });
        }

        // 2. Gráfico Doughnut de Distribución de Pasivos
        const doughnutCtx = document.getElementById('doughnutChartPasivos');
        if (doughnutCtx) {
            new Chart(doughnutCtx, {
                type: 'doughnut',
                data: {
                    labels: <?= json_encode($chart_distribucion_labels) ?>,
                    datasets: [{
                        data: <?= json_encode($chart_distribucion_data) ?>,
                        backgroundColor: [
                            '#7C3AED', // Letras violet
                            '#2563EB', // Bancos blue
                            '#F59E0B', // SUNAT amber
                            '#EF4444'  // SAT red
                        ],
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '68%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 10,
                                padding: 12,
                                font: { family: 'Inter', size: 11, weight: '600' },
                                color: '#374151'
                            }
                        },
                        tooltip: {
                            backgroundColor: '#1E293B',
                            titleFont: { size: 12, weight: 'bold' },
                            bodyFont: { size: 12 },
                            padding: 10,
                            cornerRadius: 8,
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    let val = context.parsed || 0;
                                    return ' ' + label + ': S/ ' + val.toLocaleString('es-PE', { minimumFractionDigits: 2 });
                                }
                            }
                        }
                    }
                }
            });
        }
    </script>
</body>
</html>
