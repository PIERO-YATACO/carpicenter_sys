<?php
require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/cotizacion_model.php';

$model = new CotizacionModel($db);

// Cargar vendedoras y locales para filtros y privacidad
$isSeller = in_array(strtolower($user_role ?? ''), ['vendedor', 'vendedora']);
$user_id_session = $_SESSION['user_id'] ?? 0;
$user_local_session = $_SESSION['local_id'] ?? 0;

$filterVendedor = $_GET['vendedor_id'] ?? null;
$filterLocal = $_GET['local_id'] ?? null;

$cotizaciones = $model->getAll($user_id_session, $user_role, $user_local_session, $filterVendedor, $filterLocal);

$vendedores_filter_list = $db->query("
    SELECT u.id, u.username, u.nombre_completo, r.nombre as rol_nombre 
    FROM usuarios u 
    LEFT JOIN usuario_roles ur ON u.id = ur.usuario_id 
    LEFT JOIN roles r ON ur.rol_id = r.id 
    WHERE u.estado = 'Activo'
    ORDER BY u.nombre_completo ASC
")->fetchAll(PDO::FETCH_ASSOC);

$locales_filter_list = $db->query("SELECT id, nombre FROM locales ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

// Pre-calcular cotizaciones por cliente para identificar clientes recurrentes
$clienteCounts = [];
$totalMonto = 0;
$countPendientes = 0;
$countAprobadas = 0;
$countAnuladas = 0;
$totalMontoAnulado = 0;

foreach ($cotizaciones as $c) {
    $st = strtolower(trim($c['estado'] ?? 'pendiente'));
    $monto = floatval($c['total'] ?? 0);

    if ($st === 'anulada' || $st === 'rechazada') {
        $countAnuladas++;
        $totalMontoAnulado += $monto;
    } else {
        // Solo sumar al total valorado las cotizaciones vigentes (Pendientes, Aprobadas, Facturadas)
        $totalMonto += $monto;
    }

    if ($st === 'pendiente') $countPendientes++;
    if ($st === 'aprobada' || $st === 'aceptada' || $st === 'facturada') $countAprobadas++;

    $key = !empty($c['cliente_documento']) ? trim($c['cliente_documento']) : trim(mb_strtolower($c['cliente_nombre']));
    if (!isset($clienteCounts[$key])) {
        $clienteCounts[$key] = 0;
    }
    $clienteCounts[$key]++;
}

$page_title = 'Cotizaciones';
$page_subtitle = $isSeller ? 'Mis presupuestos comerciales y cotizaciones de tienda' : 'Control general de presupuestos comerciales por vendedora y tienda';
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
        /* ===== ESTILOS COTIZACIONES PREMIUM ===== */
        .coti-hero {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.4rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .coti-hero-title h1 {
            font-size: 1.45rem;
            font-weight: 800;
            color: #111827;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .coti-hero-title p {
            color: #6B7280;
            font-size: 0.84rem;
            margin: 0.25rem 0 0 0;
        }

        /* KPI Cards */
        .coti-kpis-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.2rem;
            margin-bottom: 1.3rem;
        }
        .coti-kpi-card {
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
        .coti-kpi-card:hover {
            transform: translateY(-2px);
        }
        .coti-kpi-icon {
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
        .icon-amber-bg { background: linear-gradient(135deg, rgba(217,119,6,0.12) 0%, rgba(245,158,11,0.2) 100%); color: #D97706; }
        .icon-emerald-bg { background: linear-gradient(135deg, rgba(5,150,105,0.12) 0%, rgba(16,185,129,0.2) 100%); color: #059669; }
        .icon-blue-bg { background: linear-gradient(135deg, rgba(37,99,235,0.12) 0%, rgba(59,130,246,0.2) 100%); color: #2563EB; }

        .coti-kpi-info span.label {
            font-size: 0.74rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
            color: #6B7280;
            display: block;
            margin-bottom: 0.15rem;
        }
        .coti-kpi-info h3 {
            font-size: 1.35rem;
            font-weight: 800;
            color: #111827;
            margin: 0;
            line-height: 1.2;
        }
        .coti-kpi-info span.sub-tag {
            font-size: 0.74rem;
            font-weight: 600;
            display: inline-block;
            margin-top: 0.25rem;
            padding: 2px 8px;
            border-radius: 20px;
        }

        /* Filter Panel */
        .coti-filter-card {
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
        .coti-search-box {
            position: relative;
            flex: 1;
            min-width: 260px;
            max-width: 450px;
        }
        .coti-search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #9CA3AF;
            font-size: 0.9rem;
        }
        .coti-search-box input {
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
        .coti-search-box input:focus {
            background: #FFFFFF;
            border-color: #E31E24;
            box-shadow: 0 0 0 3px rgba(227,30,36,0.1);
        }

        .filter-pill-group {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            flex-wrap: wrap;
        }
        .btn-filter-pill {
            background: #F3F4F6;
            color: #4B5563;
            border: 1px solid #E5E7EB;
            padding: 0.45rem 0.85rem;
            border-radius: 8px;
            font-size: 0.78rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.15s;
        }
        .btn-filter-pill:hover {
            background: #E5E7EB;
            color: #111827;
        }
        .btn-filter-pill.active {
            background: #111827;
            color: #FFFFFF;
            border-color: #111827;
        }

        /* Table Card */
        .coti-table-card {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        }
        .coti-table-header-title {
            padding: 1rem 1.4rem;
            border-bottom: 1px solid #F3F4F6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .coti-table-header-title h3 {
            font-size: 0.95rem;
            font-weight: 700;
            color: #111827;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .coti-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        .coti-table th {
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
        .coti-table td {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid #F3F4F6;
            font-size: 0.85rem;
            color: #374151;
            vertical-align: middle;
            white-space: nowrap;
        }
        .coti-table tbody tr:hover {
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
        .badge-cli-count {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            font-size: 0.72rem;
            padding: 2px 7px;
            border-radius: 12px;
            background: rgba(37,99,235,0.08);
            color: #2563EB;
            font-weight: 700;
            cursor: pointer;
            border: 1px solid rgba(37,99,235,0.2);
            transition: all 0.2s;
        }
        .badge-cli-count:hover {
            background: #2563EB;
            color: #FFFFFF;
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
        .status-pill.aprobada, .status-pill.aceptada, .status-pill.completada { background: rgba(5,150,105,0.1); color: #059669; border: 1px solid rgba(5,150,105,0.25); }
        .status-pill.pendiente { background: rgba(217,119,6,0.1); color: #D97706; border: 1px solid rgba(217,119,6,0.25); }
        .status-pill.facturada { background: rgba(37,99,235,0.1); color: #2563EB; border: 1px solid rgba(37,99,235,0.25); }
        .status-pill.rechazada, .status-pill.anulada { background: rgba(220,38,38,0.1); color: #DC2626; border: 1px solid rgba(220,38,38,0.25); }

        /* Action Buttons */
        .coti-actions {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            justify-content: center;
        }
        .btn-action-soft {
            width: 30px;
            height: 30px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: none;
            cursor: pointer;
            font-size: 0.85rem;
            text-decoration: none;
            transition: all 0.15s;
        }
        .btn-action-soft.view { background: rgba(37,99,235,0.08); color: #2563EB; }
        .btn-action-soft.view:hover { background: #2563EB; color: #FFFFFF; }
        .btn-action-soft.edit { background: rgba(100,116,139,0.1); color: #475569; }
        .btn-action-soft.edit:hover { background: #475569; color: #FFFFFF; }
        .btn-action-soft.copy { background: rgba(124,58,237,0.08); color: #7C3AED; }
        .btn-action-soft.copy:hover { background: #7C3AED; color: #FFFFFF; }
        .btn-action-soft.approve { background: rgba(217,119,6,0.1); color: #D97706; }
        .btn-action-soft.approve:hover { background: #D97706; color: #FFFFFF; }
        .btn-action-soft.whatsapp { background: rgba(37,211,102,0.1); color: #25D366; }
        .btn-action-soft.whatsapp:hover { background: #25D366; color: #FFFFFF; }
        .btn-action-soft.contract { background: rgba(5,150,105,0.1); color: #059669; }
        .btn-action-soft.contract:hover { background: #059669; color: #FFFFFF; }

        /* Modal */
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
            max-width: 500px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            animation: modalFadeIn 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            overflow: hidden;
        }
        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(8px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
    </style>
</head>
<body>
<div class="app-wrapper">
    <?php include '../../views/partials/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../views/partials/header.php'; ?>
        <div class="page-content">

            <!-- Header de la Página -->
            <div class="coti-hero">
                <div class="coti-hero-title">
                    <h1><i class="fas fa-file-invoice" style="color:#E31E24;"></i> Cotizaciones</h1>
                    <p>Presupuestos comerciales, versiones para clientes y firmas digitales</p>
                </div>
                <a href="cotizacion_form.php" class="btn btn-primary" style="font-weight:600; padding:0.55rem 1.2rem; border-radius:10px; box-shadow:0 4px 12px rgba(227,30,36,0.25);">
                    <i class="fas fa-plus" style="margin-right:6px;"></i> Nueva Cotización
                </a>
            </div>

            <!-- KPIs Resumen -->
            <div class="coti-kpis-grid">
                <div class="coti-kpi-card">
                    <div class="coti-kpi-icon icon-indigo-bg">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                    <div class="coti-kpi-info">
                        <span class="label">Total Cotizaciones</span>
                        <h3><?= count($cotizaciones) ?></h3>
                        <span class="sub-tag" style="background:#EEF2FF; color:#4F46E5;">Emitidas</span>
                    </div>
                </div>

                <div class="coti-kpi-card">
                    <div class="coti-kpi-icon icon-amber-bg">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="coti-kpi-info">
                        <span class="label">Pendientes</span>
                        <h3 style="color:#D97706;"><?= $countPendientes ?></h3>
                        <span class="sub-tag" style="background:#FFFBEB; color:#D97706;">En negociación</span>
                    </div>
                </div>

                <div class="coti-kpi-card">
                    <div class="coti-kpi-icon icon-emerald-bg">
                        <i class="fas fa-circle-check"></i>
                    </div>
                    <div class="coti-kpi-info">
                        <span class="label">Aprobadas / Aceptadas</span>
                        <h3 style="color:#059669;"><?= $countAprobadas ?></h3>
                        <span class="sub-tag" style="background:#ECFDF5; color:#059669;">Cierres logrados</span>
                    </div>
                </div>

                <div class="coti-kpi-card">
                    <div class="coti-kpi-icon icon-blue-bg">
                        <i class="fas fa-coins"></i>
                    </div>
                    <div class="coti-kpi-info">
                        <span class="label">Total Valorado</span>
                        <h3 style="color:#2563EB;">S/ <?= number_format($totalMonto, 2) ?></h3>
                        <span class="sub-tag" style="background:#EFF6FF; color:#2563EB;"><?= ($countAnuladas > 0) ? 'Monto vigente (' . $countAnuladas . ' anuladas)' : 'Monto vigente' ?></span>
                    </div>
                </div>
            </div>

            <!-- Toolbar de Búsqueda y Filtros Rápidos -->
            <div class="coti-filter-card" style="display:flex; flex-wrap:wrap; gap:10px; align-items:center;">
                <div class="coti-search-box" style="flex:1; min-width:240px;">
                    <i class="fas fa-search"></i>
                    <input type="text" id="filterInput" placeholder="Buscar por N° cotización, cliente, DNI/RUC, vendedora..." onkeyup="filterCotizaciones()">
                </div>

                <?php if (!$isSeller): ?>
                <!-- Filtros para Administrador y Contabilidad -->
                <form method="GET" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                    <select name="vendedor_id" class="form-control" style="padding:0.48rem 0.8rem; font-size:0.82rem; font-weight:600; border-radius:8px;" onchange="this.form.submit()">
                        <option value="">👤 Todas las Vendedoras</option>
                        <?php foreach($vendedores_filter_list as $vf): ?>
                            <option value="<?= $vf['id'] ?>" <?= ($filterVendedor == $vf['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($vf['nombre_completo'] ?: $vf['username']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="local_id" class="form-control" style="padding:0.48rem 0.8rem; font-size:0.82rem; font-weight:600; border-radius:8px;" onchange="this.form.submit()">
                        <option value="">🏢 Todas las Tiendas</option>
                        <?php foreach($locales_filter_list as $lf): ?>
                            <option value="<?= $lf['id'] ?>" <?= ($filterLocal == $lf['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($lf['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <?php if(!empty($filterVendedor) || !empty($filterLocal)): ?>
                        <a href="cotizaciones.php" class="btn btn-outline" style="padding:0.45rem 0.75rem; border-radius:8px;" title="Limpiar Filtros">
                            <i class="fas fa-times"></i>
                        </a>
                    <?php endif; ?>
                </form>
                <?php else: ?>
                    <span style="font-size:0.8rem; font-weight:700; background:#EFF6FF; color:#1E40AF; padding:6px 12px; border-radius:20px; border:1px solid #BFDBFE;">
                        <i class="fas fa-lock" style="margin-right:4px;"></i> Mis Cotizaciones (<?= htmlspecialchars($_SESSION['local_nombre'] ?? 'Tienda') ?>)
                    </span>
                <?php endif; ?>

                <div class="filter-pill-group">
                    <span style="font-size: 0.76rem; font-weight: 700; color: #6B7280; text-transform:uppercase;"><i class="fas fa-filter"></i> Estado:</span>
                    <button type="button" class="btn-filter-pill active" onclick="filterByState('TODOS', this)">Todos</button>
                    <button type="button" class="btn-filter-pill" onclick="filterByState('Pendiente', this)">Pendientes</button>
                    <button type="button" class="btn-filter-pill" onclick="filterByState('Aprobada', this)">Aprobadas</button>
                    <button type="button" class="btn-filter-pill" onclick="filterByState('Facturada', this)">Facturadas</button>
                    <button type="button" class="btn-filter-pill" onclick="filterByState('Rechazada', this)">Rechazadas / Anuladas</button>
                </div>
            </div>

            <!-- Tabla de Cotizaciones -->
            <div class="coti-table-card">
                <div class="coti-table-header-title">
                    <h3><i class="fas fa-file-invoice" style="color:#E31E24;"></i> Historial de Presupuestos Comerciales</h3>
                    <span style="font-size:0.8rem; color:#6B7280; font-weight:500;">
                        Mostrando <?= count($cotizaciones) ?> cotizaciones registradas
                    </span>
                </div>

                <div style="overflow-x:auto;">
                    <table class="coti-table" id="cotizacionesTable">
                        <thead>
                            <tr>
                                <th>Número</th>
                                <th>Cliente / Contacto</th>
                                <th>Vendedora & Tienda</th>
                                <th>Fecha</th>
                                <th>Válido Hasta</th>
                                <th style="text-align:right;">Total (S/)</th>
                                <th>Estado</th>
                                <th style="text-align:center;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($cotizaciones)): ?>
                                <tr>
                                    <td colspan="8" style="text-align:center; padding:3.5rem; color:#9CA3AF;">
                                        <i class="fas fa-file-invoice" style="font-size:2.5rem; margin-bottom:0.8rem; opacity:0.35; display:block;"></i>
                                        No hay cotizaciones registradas con los filtros aplicados.
                                    </td>
                                </tr>
                            <?php else: foreach($cotizaciones as $cot): 
                                $cliKey = !empty($cot['cliente_documento']) ? trim($cot['cliente_documento']) : trim(mb_strtolower($cot['cliente_nombre']));
                                $totalQuotesForCli = $clienteCounts[$cliKey] ?? 1;
                                $st = strtolower($cot['estado'] ?? 'pendiente');
                                $searchRow = strtolower($cot['numero'] . ' ' . $cot['cliente_nombre'] . ' ' . ($cot['cliente_documento'] ?? '') . ' ' . ($cot['cliente_telefono'] ?? '') . ' ' . ($cot['vendedor_display'] ?? '') . ' ' . ($cot['local_display'] ?? ''));
                            ?>
                            <tr data-estado="<?= htmlspecialchars($cot['estado'] ?? 'Pendiente') ?>" data-search="<?= htmlspecialchars($searchRow) ?>">
                                <td>
                                    <span class="doc-badge"><?= htmlspecialchars($cot['numero']) ?></span>
                                </td>
                                <td>
                                    <div style="font-weight:700; color:#111827;"><?= htmlspecialchars($cot['cliente_nombre']) ?></div>
                                    <div style="font-size:0.76rem; color:#6B7280; display:flex; align-items:center; gap:8px; margin-top:2px; flex-wrap:wrap;">
                                        <?php if(!empty($cot['cliente_documento'])): ?>
                                            <span><i class="fas fa-id-card"></i> <?= htmlspecialchars($cot['cliente_documento']) ?></span>
                                        <?php endif; ?>

                                        <?php if(!empty($cot['cliente_telefono'])): 
                                            $phoneClean = preg_replace('/[^0-9]/', '', $cot['cliente_telefono']);
                                            $wsUrl = (strlen($phoneClean) >= 9) ? "https://wa.me/" . ((strlen($phoneClean) === 9) ? '51' . $phoneClean : $phoneClean) : null;
                                        ?>
                                            <span style="display:inline-flex; align-items:center; gap:3px;">
                                                <i class="fas fa-phone" style="font-size:0.75rem;"></i> <?= htmlspecialchars($cot['cliente_telefono']) ?>
                                                <?php if($wsUrl): ?>
                                                    <a href="<?= $wsUrl ?>" target="_blank" title="WhatsApp Directo" style="color:#25D366; text-decoration:none; margin-left:2px;">
                                                        <i class="fab fa-whatsapp"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </span>
                                        <?php endif; ?>

                                        <?php if($totalQuotesForCli > 1): ?>
                                            <span class="badge-cli-count" title="Hacer clic para ver todas las cotizaciones de este cliente" onclick="filterByClientName('<?= htmlspecialchars(addslashes($cot['cliente_nombre'])) ?>')">
                                                <i class="fas fa-layer-group"></i> <?= $totalQuotesForCli ?> cotizaciones
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight:700; color:#1E293B; font-size:0.85rem;">
                                        <i class="fas fa-user-tag" style="color:#E31E24; font-size:0.78rem; margin-right:4px;"></i>
                                        <?= htmlspecialchars($cot['vendedor_display']) ?>
                                    </div>
                                    <div style="font-size:0.74rem; color:#64748B; margin-top:2px;">
                                        <i class="fas fa-shop" style="color:#2563EB; font-size:0.72rem; margin-right:3px;"></i>
                                        <?= htmlspecialchars($cot['local_display']) ?>
                                    </div>
                                </td>
                                <td style="font-size:0.83rem;"><?= date('d/m/Y', strtotime($cot['fecha'])) ?></td>
                                <td style="font-size:0.83rem;"><?= $cot['fecha_validez'] ? date('d/m/Y', strtotime($cot['fecha_validez'])) : '<span style="color:#9CA3AF;">—</span>' ?></td>
                                <td style="text-align:right; font-weight:800; color:#111827; font-size:0.92rem;">
                                    S/ <?= number_format($cot['total'], 2) ?>
                                </td>
                                <td>
                                    <span class="status-pill <?= $st ?>">
                                        <?= htmlspecialchars($cot['estado'] ?? 'Pendiente') ?>
                                    </span>
                                </td>
                                <td style="text-align:center;">
                                    <?php
                                    $stmtV = $db->prepare("SELECT id FROM ventas WHERE cotizacion_id = :cid LIMIT 1");
                                    $stmtV->execute([':cid' => $cot['id']]);
                                    $venta_exist = $stmtV->fetchColumn();

                                    $stmtC = $db->prepare("SELECT id FROM contratos WHERE cotizacion_id = :cid LIMIT 1");
                                    $stmtC->execute([':cid' => $cot['id']]);
                                    $contrato_exist = $stmtC->fetchColumn();
                                    ?>
                                    <div class="coti-actions">
                                        <a href="cotizacion_view.php?id=<?= $cot['id'] ?>" class="btn-action-soft view" title="Ver Cotización (Formato PDF)">
                                            <i class="fas fa-file-invoice"></i>
                                        </a>
                                        <a href="cotizacion_form.php?id=<?= $cot['id'] ?>" class="btn-action-soft edit" title="Editar">
                                            <i class="fas fa-pen"></i>
                                        </a>
                                        <a href="cotizacion_form.php?duplicate_id=<?= $cot['id'] ?>" class="btn-action-soft copy" title="Duplicar / Nueva Versión">
                                            <i class="fas fa-copy"></i>
                                        </a>
                                        
                                        <?php if ($cot['estado'] == 'Pendiente'): ?>
                                            <button type="button" class="btn-action-soft approve" title="Aprobar y Generar Contrato/Nota" onclick="abrirAprobacion(<?= $cot['id'] ?>)">
                                                <i class="fas fa-check-double"></i>
                                            </button>
                                        <?php endif; ?>

                                        <?php if (($cot['estado'] == 'Aprobada' || $cot['estado'] == 'Aceptada') && !empty($cot['firma_token']) && empty($cot['firma_digital'])): 
                                            $linkFirma = "http://" . $_SERVER['HTTP_HOST'] . "/carpicenter_sys/modules/cotizaciones/firma_remota.php?token=" . $cot['firma_token'];
                                            $msgWs = urlencode("Hola " . $cot['cliente_nombre'] . ", aquí tienes el enlace para revisar y firmar digitalmente tu " . $cot['tipo_documento'] . " de Carpicenter:\n\n" . $linkFirma);
                                            $phoneC = preg_replace('/[^0-9]/', '', $cot['cliente_telefono'] ?? '');
                                            $wsUrlFirma = (strlen($phoneC) >= 9) ? "https://wa.me/" . ((strlen($phoneC) === 9) ? '51' . $phoneC : $phoneC) . "?text=" . $msgWs : "https://api.whatsapp.com/send?text=" . $msgWs;
                                        ?>
                                            <a href="<?= $wsUrlFirma ?>" target="_blank" class="btn-action-soft whatsapp" title="Enviar Link de Firma por WhatsApp">
                                                <i class="fab fa-whatsapp"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if (($cot['estado'] == 'Aprobada' || $cot['estado'] == 'Aceptada') && !empty($cot['firma_digital'])): ?>
                                            <?php if (($cot['tipo_documento'] ?? '') === 'CONTRATO'): ?>
                                                <?php if (!$contrato_exist): ?>
                                                    <a href="/carpicenter_sys/modules/contratos/contrato_form.php?cotizacion_id=<?= $cot['id'] ?>" class="btn-action-soft contract" title="Generar Contrato y Abono">
                                                        <i class="fas fa-file-signature"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="btn-action-soft" style="background:#ECFDF5; color:#059669;" title="Contrato ya generado">
                                                        <i class="fas fa-check-circle"></i>
                                                    </span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Modal Aprobación -->
<div class="modal-overlay" id="modalAprobacion">
    <div class="modal-box">
        <div style="padding:1.25rem 1.5rem; border-bottom:1px solid #E5E7EB; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:1.1rem; font-weight:800; color:#111827;">
                <i class="fas fa-check-double" style="color:#059669; margin-right:6px;"></i> Aprobar Cotización
            </h3>
            <button type="button" onclick="cerrarAprobacion()" style="background:none; border:none; font-size:1.2rem; cursor:pointer;">&times;</button>
        </div>
        <form method="POST" action="cotizacion_aprobar.php">
            <input type="hidden" name="cotizacion_id" id="aprobacion_cot_id">
            <div style="padding:1.4rem;">
                <p style="font-size:0.88rem; color:#4B5563; margin-top:0;">¿Cómo desea proceder con esta cotización aprobada?</p>
                <div style="display:flex; flex-direction:column; gap:0.75rem;">
                    <label style="border:1px solid #D1D5DB; border-radius:10px; padding:12px; display:flex; align-items:center; gap:10px; cursor:pointer;">
                        <input type="radio" name="destino" value="CONTRATO" checked>
                        <div>
                            <strong style="color:#111827; font-size:0.88rem;">Generar Contrato de Fabricación</strong>
                            <div style="font-size:0.74rem; color:#6B7280;">Para muebles a medida o proyectos con abono inicial</div>
                        </div>
                    </label>
                    <label style="border:1px solid #D1D5DB; border-radius:10px; padding:12px; display:flex; align-items:center; gap:10px; cursor:pointer;">
                        <input type="radio" name="destino" value="NOTA_VENTA">
                        <div>
                            <strong style="color:#111827; font-size:0.88rem;">Generar Nota de Venta Inmediata</strong>
                            <div style="font-size:0.74rem; color:#6B7280;">Para despacho directo de tienda o venta al contado</div>
                        </div>
                    </label>
                </div>
            </div>
            <div style="padding:1rem 1.4rem; background:#F9FAFB; border-top:1px solid #E5E7EB; display:flex; justify-content:flex-end; gap:0.75rem;">
                <button type="button" class="btn btn-outline" onclick="cerrarAprobacion()">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Continuar</button>
            </div>
        </form>
    </div>
</div>

<script>
function filterCotizaciones() {
    const input = document.getElementById('filterInput').value.toLowerCase();
    const rows = document.querySelectorAll('#cotizacionesTable tbody tr');
    rows.forEach(row => {
        const text = row.getAttribute('data-search') || '';
        row.style.display = text.includes(input) ? '' : 'none';
    });
}

function filterByState(state, btn) {
    document.querySelectorAll('.btn-filter-pill').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    const rows = document.querySelectorAll('#cotizacionesTable tbody tr');
    rows.forEach(row => {
        const rowState = (row.getAttribute('data-estado') || '').toLowerCase();
        const targetState = state.toLowerCase();
        
        let match = false;
        if (targetState === 'todos') {
            match = true;
        } else if (targetState === 'rechazada') {
            match = (rowState === 'rechazada' || rowState === 'anulada');
        } else {
            match = (rowState === targetState);
        }

        row.style.display = match ? '' : 'none';
    });
}

function filterByClientName(name) {
    const input = document.getElementById('filterInput');
    input.value = name;
    filterCotizaciones();
}

function abrirAprobacion(id) {
    document.getElementById('aprobacion_cot_id').value = id;
    document.getElementById('modalAprobacion').classList.add('open');
}

function cerrarAprobacion() {
    document.getElementById('modalAprobacion').classList.remove('open');
}
</script>
</body>
</html>
