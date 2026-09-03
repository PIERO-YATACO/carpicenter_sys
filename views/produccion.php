<?php
require_once __DIR__ . '/../auth/check_auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../modules/contratos/contrato_model.php';

$model = new ContratoModel($db);

// Obtener todas las órdenes de producción activas
$sql = "
    SELECT 
        c.*,
        cli.nombre as cliente_nombre,
        cli.telefono as cliente_telefono,
        l.nombre as local_nombre,
        u.nombre_completo as vendedor_nombre,
        (SELECT COUNT(*) FROM contrato_detalles cd WHERE cd.contrato_id = c.id) as total_items
    FROM contratos c
    LEFT JOIN clientes cli ON c.cliente_id = cli.id
    LEFT JOIN locales l ON c.local_id = l.id
    LEFT JOIN usuarios u ON c.vendedor_id = u.id
    WHERE c.estado_contrato IN ('Pendiente', 'En Producción', 'Listo para Entrega')
    ORDER BY CASE WHEN c.prioridad = 'Urgente' THEN 1 ELSE 2 END, c.fecha_entrega_estimada ASC, c.id DESC
";

$ordenesProduccion = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Obtener solo los detalles marcados para Fabricación en Taller (origen_item = 'Producción' o NULL)
$filteredOrders = [];
$countUrgentes = 0;
foreach ($ordenesProduccion as $ord) {
    $stmtDet = $db->prepare("
        SELECT cd.*, p.nombre as producto_nombre, col.nombre as color_nombre 
        FROM contrato_detalles cd
        LEFT JOIN productos p ON cd.producto_id = p.id
        LEFT JOIN colores col ON cd.color_id = col.id
        WHERE cd.contrato_id = :id AND (cd.origen_item IS NULL OR cd.origen_item = 'Producción')
    ");
    $stmtDet->execute([':id' => $ord['id']]);
    $detallesProd = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($detallesProd)) {
        $ord['detalles'] = $detallesProd;
        if ($ord['prioridad'] === 'Urgente') $countUrgentes++;
        $filteredOrders[] = $ord;
    }
}
$ordenesProduccion = $filteredOrders;

// Contadores
$countPendientes = 0;
$countEnProceso = 0;
$countListos = 0;

foreach ($ordenesProduccion as $o) {
    if ($o['estado_contrato'] === 'Pendiente') $countPendientes++;
    if ($o['estado_contrato'] === 'En Producción') $countEnProceso++;
    if ($o['estado_contrato'] === 'Listo para Entrega') $countListos++;
}

$page_title = 'Producción y Fabricación';
$page_subtitle = 'Control de corte, armado y terminado en Taller Central';
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
        /* ===== PRODUCCIÓN PREMIUM ===== */
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
        .prd-hero-actions {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            flex-wrap: wrap;
        }

        /* KPIs */
        .prd-kpis-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
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
        .icon-amber-bg { background: linear-gradient(135deg, rgba(217,119,6,0.12) 0%, rgba(245,158,11,0.2) 100%); color: #D97706; }
        .icon-blue-bg { background: linear-gradient(135deg, rgba(37,99,235,0.12) 0%, rgba(59,130,246,0.2) 100%); color: #2563EB; }
        .icon-emerald-bg { background: linear-gradient(135deg, rgba(5,150,105,0.12) 0%, rgba(16,185,129,0.2) 100%); color: #059669; }
        .icon-rose-bg { background: linear-gradient(135deg, rgba(220,38,38,0.12) 0%, rgba(239,68,68,0.2) 100%); color: #DC2626; }

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

        /* Sede Banner Compact */
        .sede-banner {
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-left: 4px solid #E31E24;
            border-radius: 12px;
            padding: 0.75rem 1.2rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .sede-banner-left {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        /* View Toggler & Search */
        .toolbar-produccion {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.25rem;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .prd-search-box {
            position: relative;
            flex: 1;
            max-width: 380px;
            min-width: 250px;
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
            background: #FFFFFF;
            font-size: 0.84rem;
            color: #111827;
            outline: none;
            box-sizing: border-box;
            transition: all 0.2s;
        }
        .prd-input:focus {
            border-color: #E31E24;
            box-shadow: 0 0 0 3px rgba(227,30,36,0.1);
        }

        .view-btn-group {
            display: inline-flex;
            background: #F3F4F6;
            padding: 3px;
            border-radius: 10px;
            border: 1px solid #E5E7EB;
        }
        .view-btn {
            border: none;
            background: transparent;
            padding: 6px 14px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.82rem;
            color: #6B7280;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .view-btn.active {
            background: #FFFFFF;
            color: #111827;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        /* ===== KANBAN BOARD ===== */
        .kanban-board {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.25rem;
            align-items: start;
            margin-bottom: 1.5rem;
        }
        @media (max-width: 992px) {
            .kanban-board { grid-template-columns: 1fr; }
        }

        .kanban-column {
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 16px;
            padding: 1.1rem;
            min-height: 480px;
            display: flex;
            flex-direction: column;
        }
        .kanban-col-head {
            padding-bottom: 0.75rem;
            margin-bottom: 1rem;
            border-bottom: 2px solid #E2E8F0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .kanban-col-head h4 {
            margin: 0;
            font-size: 0.92rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .count-pill {
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 800;
        }

        /* Kanban Cards */
        .kanban-card {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 0.9rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            transition: all 0.2s ease;
        }
        .kanban-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.08);
            border-color: #CBD5E1;
        }
        .kanban-card.urgente {
            border-left: 4px solid #DC2626;
        }

        .card-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.6rem;
        }
        .card-code {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-weight: 800;
            font-size: 0.9rem;
            color: #111827;
            background: #F1F5F9;
            padding: 2px 7px;
            border-radius: 6px;
            border: 1px solid #E2E8F0;
        }
        .priority-badge {
            font-size: 0.68rem;
            font-weight: 800;
            padding: 2px 6px;
            border-radius: 6px;
            text-transform: uppercase;
        }
        .priority-badge.urgente { background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA; }
        .priority-badge.normal { background: #F3F4F6; color: #4B5563; border: 1px solid #E5E7EB; }

        .card-items-box {
            background: #F8FAFC;
            border-radius: 8px;
            border: 1px solid #F1F5F9;
            padding: 0.6rem 0.75rem;
            margin: 0.65rem 0;
            font-size: 0.8rem;
        }
        .card-item-row {
            padding: 3px 0;
            border-bottom: 1px dashed #E2E8F0;
            color: #334151;
        }
        .card-item-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .card-actions-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.75rem;
            padding-top: 0.75rem;
            border-top: 1px solid #F3F4F6;
        }

        /* ===== TABLE VIEW (Single-Line Ultra Clean) ===== */
        .prd-table-card {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
            margin-bottom: 1.5rem;
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
            padding: 0.8rem 1rem;
            border-bottom: 1px solid #F3F4F6;
            font-size: 0.84rem;
            color: #374151;
            vertical-align: middle;
            white-space: nowrap;
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
            padding: 3px 8px;
            border-radius: 6px;
            border: 1px solid #CBD5E1;
            display: inline-block;
            white-space: nowrap;
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
            white-space: nowrap;
        }
        .status-pill.pendiente { background: rgba(217,119,6,0.1); color: #D97706; border: 1px solid rgba(217,119,6,0.25); }
        .status-pill.proceso { background: rgba(37,99,235,0.1); color: #2563EB; border: 1px solid rgba(37,99,235,0.25); }
        .status-pill.listo { background: rgba(5,150,105,0.1); color: #059669; border: 1px solid rgba(5,150,105,0.25); }

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
        .btn-action-soft.view { background: rgba(37,99,235,0.08); color: #2563EB; }
        .btn-action-soft.view:hover { background: #2563EB; color: #FFFFFF; }
        .btn-action-soft.delete { background: rgba(220,38,38,0.08); color: #DC2626; }
        .btn-action-soft.delete:hover { background: #DC2626; color: #FFFFFF; }
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
                    <h1><i class="fas fa-hammer" style="color:#E31E24;"></i> Producción y Fabricación</h1>
                    <p>Monitoreo de órdenes de corte, ensamblado y terminado en Almacén Central</p>
                </div>
            </div>

            <!-- KPIs -->
            <div class="prd-kpis-grid">
                <div class="prd-kpi-card">
                    <div class="prd-kpi-icon icon-amber-bg">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="prd-kpi-info">
                        <span class="label">1. Por Fabricar</span>
                        <h3 style="color:#D97706;"><?= $countPendientes ?></h3>
                        <span class="sub-tag" style="background:#FFFBEB; color:#D97706;">En cola de espera</span>
                    </div>
                </div>

                <div class="prd-kpi-card">
                    <div class="prd-kpi-icon icon-blue-bg">
                        <i class="fas fa-screwdriver-wrench"></i>
                    </div>
                    <div class="prd-kpi-info">
                        <span class="label">2. En Fabricación</span>
                        <h3 style="color:#2563EB;"><?= $countEnProceso ?></h3>
                        <span class="sub-tag" style="background:#EFF6FF; color:#2563EB;">Corte y ensamble</span>
                    </div>
                </div>

                <div class="prd-kpi-card">
                    <div class="prd-kpi-icon icon-emerald-bg">
                        <i class="fas fa-circle-check"></i>
                    </div>
                    <div class="prd-kpi-info">
                        <span class="label">3. Terminados</span>
                        <h3 style="color:#059669;"><?= $countListos ?></h3>
                        <span class="sub-tag" style="background:#ECFDF5; color:#059669;">Disponibles para entrega</span>
                    </div>
                </div>

                <div class="prd-kpi-card">
                    <div class="prd-kpi-icon icon-rose-bg">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <div class="prd-kpi-info">
                        <span class="label">Prioridad Urgente</span>
                        <h3 style="color:#DC2626;"><?= $countUrgentes ?></h3>
                        <span class="sub-tag" style="background:#FEF2F2; color:#DC2626;">Atención inmediata</span>
                    </div>
                </div>
            </div>

            <!-- Banner Sede Central -->
            <div class="sede-banner">
                <div class="sede-banner-left">
                    <i class="fas fa-industry" style="color:#E31E24; font-size:1.25rem;"></i>
                    <span style="font-size:0.84rem; color:#374151;">
                        <strong>Taller Central de Carpintería:</strong> Sede Almacén Principal procesa el corte, canteado y ensamble de todos los pedidos.
                    </span>
                </div>
                <span class="status-pill proceso" style="font-size:0.75rem;">
                    <i class="fas fa-location-dot"></i> Almacén Principal
                </span>
            </div>

            <!-- Toolbar & Toggler -->
            <div class="toolbar-produccion">
                <div class="prd-search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="prodSearch" class="prd-input" placeholder="Buscar por contrato, cliente o mueble..." onkeyup="filterProduccion()">
                </div>

                <div class="view-btn-group">
                    <button id="btnKanban" class="view-btn active" onclick="switchView('kanban')">
                        <i class="fas fa-columns"></i> Tablero Kanban
                    </button>
                    <button id="btnTable" class="view-btn" onclick="switchView('table')">
                        <i class="fas fa-list"></i> Vista Tabla
                    </button>
                </div>
            </div>

            <!-- ================= VISTA KANBAN ================= -->
            <div id="kanbanView" class="kanban-board">

                <!-- COLUMNA 1: POR FABRICAR -->
                <div class="kanban-column" style="border-top: 4px solid #D97706;">
                    <div class="kanban-col-head">
                        <h4 style="color:#D97706;"><i class="fas fa-clock"></i> 1. Por Fabricar</h4>
                        <span class="count-pill" style="background:#FEF3C7; color:#D97706;" id="countPendientesBadge"><?= $countPendientes ?></span>
                    </div>

                    <div id="colPendientes">
                        <?php foreach ($ordenesProduccion as $o): 
                            if ($o['estado_contrato'] !== 'Pendiente') continue;
                            $isUrgente = ($o['prioridad'] === 'Urgente');
                        ?>
                        <div class="kanban-card <?= $isUrgente ? 'urgente' : '' ?>" id="card-<?= $o['id'] ?>" data-search="<?= strtolower(htmlspecialchars($o['codigo_completo'] . ' ' . $o['cliente_nombre'] . ' ' . implode(' ', array_column($o['detalles'], 'descripcion')))) ?>">
                            <div class="card-top">
                                <span class="card-code"><?= htmlspecialchars($o['codigo_completo']) ?></span>
                                <div style="display:flex; align-items:center; gap:0.4rem;">
                                    <span class="priority-badge <?= $isUrgente ? 'urgente' : 'normal' ?>"><?= htmlspecialchars($o['prioridad']) ?></span>
                                    <button type="button" style="background:none; border:none; padding:2px 4px; cursor:pointer; color:#EF4444;" onclick="eliminarProduccion(<?= $o['id'] ?>, '<?= htmlspecialchars($o['codigo_completo']) ?>')" title="Eliminar orden">
                                        <i class="fas fa-trash-can" style="font-size:0.75rem;"></i>
                                    </button>
                                </div>
                            </div>
                            <div style="font-size:0.78rem; color:#6B7280; margin-bottom:0.4rem;">
                                <i class="fas fa-calendar-days" style="margin-right:4px;"></i> Entrega: <strong><?= !empty($o['fecha_entrega_estimada']) ? date('d/m/Y', strtotime($o['fecha_entrega_estimada'])) : 'Sin fecha' ?></strong>
                            </div>
                            <div style="font-size:0.83rem; color:#111827; font-weight:700;">
                                <?= htmlspecialchars($o['cliente_nombre'] ?? 'Cliente General') ?>
                            </div>
                            <div class="card-items-box">
                                <?php foreach ($o['detalles'] as $d): ?>
                                <div class="card-item-row">
                                    <strong>[<?= $d['cantidad'] ?> un]</strong> <?= htmlspecialchars($d['descripcion']) ?>
                                    <?php if(!empty($d['color_nombre'])): ?>
                                        <span style="color:#E31E24; font-size:0.75rem; margin-left:4px;">• <?= htmlspecialchars($d['color_nombre']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="card-actions-bar" style="justify-content:flex-end;">
                                <button class="btn btn-primary" style="font-size:0.75rem; padding:4px 10px;" onclick="moverEstado(<?= $o['id'] ?>, 'En Producción')">
                                    Fabricar <i class="fas fa-arrow-right" style="margin-left:3px;"></i>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- COLUMNA 2: EN FABRICACIÓN -->
                <div class="kanban-column" style="border-top: 4px solid #2563EB;">
                    <div class="kanban-col-head">
                        <h4 style="color:#2563EB;"><i class="fas fa-screwdriver-wrench"></i> 2. En Fabricación</h4>
                        <span class="count-pill" style="background:#DBEAFE; color:#2563EB;" id="countEnProcesoBadge"><?= $countEnProceso ?></span>
                    </div>

                    <div id="colEnProceso">
                        <?php foreach ($ordenesProduccion as $o): 
                            if ($o['estado_contrato'] !== 'En Producción') continue;
                            $isUrgente = ($o['prioridad'] === 'Urgente');
                        ?>
                        <div class="kanban-card <?= $isUrgente ? 'urgente' : '' ?>" id="card-<?= $o['id'] ?>" data-search="<?= strtolower(htmlspecialchars($o['codigo_completo'] . ' ' . $o['cliente_nombre'] . ' ' . implode(' ', array_column($o['detalles'], 'descripcion')))) ?>">
                            <div class="card-top">
                                <span class="card-code"><?= htmlspecialchars($o['codigo_completo']) ?></span>
                                <div style="display:flex; align-items:center; gap:0.4rem;">
                                    <span class="priority-badge <?= $isUrgente ? 'urgente' : 'normal' ?>"><?= htmlspecialchars($o['prioridad']) ?></span>
                                    <button type="button" style="background:none; border:none; padding:2px 4px; cursor:pointer; color:#EF4444;" onclick="eliminarProduccion(<?= $o['id'] ?>, '<?= htmlspecialchars($o['codigo_completo']) ?>')" title="Eliminar orden">
                                        <i class="fas fa-trash-can" style="font-size:0.75rem;"></i>
                                    </button>
                                </div>
                            </div>
                            <div style="font-size:0.78rem; color:#6B7280; margin-bottom:0.4rem;">
                                <i class="fas fa-calendar-days" style="margin-right:4px;"></i> Entrega: <strong><?= !empty($o['fecha_entrega_estimada']) ? date('d/m/Y', strtotime($o['fecha_entrega_estimada'])) : 'Sin fecha' ?></strong>
                            </div>
                            <div style="font-size:0.83rem; color:#111827; font-weight:700;">
                                <?= htmlspecialchars($o['cliente_nombre'] ?? 'Cliente General') ?>
                            </div>
                            <div class="card-items-box">
                                <?php foreach ($o['detalles'] as $d): ?>
                                <div class="card-item-row">
                                    <strong>[<?= $d['cantidad'] ?> un]</strong> <?= htmlspecialchars($d['descripcion']) ?>
                                    <?php if(!empty($d['color_nombre'])): ?>
                                        <span style="color:#E31E24; font-size:0.75rem; margin-left:4px;">• <?= htmlspecialchars($d['color_nombre']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="card-actions-bar">
                                <button class="btn btn-outline" style="font-size:0.75rem; padding:4px 8px;" onclick="moverEstado(<?= $o['id'] ?>, 'Pendiente')">
                                    <i class="fas fa-arrow-left"></i> Volver
                                </button>
                                <button class="btn btn-success" style="font-size:0.75rem; padding:4px 10px;" onclick="moverEstado(<?= $o['id'] ?>, 'Listo para Entrega')">
                                    <i class="fas fa-check" style="margin-right:3px;"></i> Terminado
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- COLUMNA 3: TERMINADO EN ALMACÉN -->
                <div class="kanban-column" style="border-top: 4px solid #059669;">
                    <div class="kanban-col-head">
                        <h4 style="color:#059669;"><i class="fas fa-circle-check"></i> 3. Terminado</h4>
                        <span class="count-pill" style="background:#D1FAE5; color:#059669;" id="countListosBadge"><?= $countListos ?></span>
                    </div>

                    <div id="colListos">
                        <?php foreach ($ordenesProduccion as $o): 
                            if ($o['estado_contrato'] !== 'Listo para Entrega') continue;
                            $isUrgente = ($o['prioridad'] === 'Urgente');
                        ?>
                        <div class="kanban-card <?= $isUrgente ? 'urgente' : '' ?>" id="card-<?= $o['id'] ?>" data-search="<?= strtolower(htmlspecialchars($o['codigo_completo'] . ' ' . $o['cliente_nombre'] . ' ' . implode(' ', array_column($o['detalles'], 'descripcion')))) ?>">
                            <div class="card-top">
                                <span class="card-code"><?= htmlspecialchars($o['codigo_completo']) ?></span>
                                <div style="display:flex; align-items:center; gap:0.4rem;">
                                    <span class="status-pill listo" style="font-size:0.68rem;">LISTO</span>
                                    <button type="button" style="background:none; border:none; padding:2px 4px; cursor:pointer; color:#EF4444;" onclick="eliminarProduccion(<?= $o['id'] ?>, '<?= htmlspecialchars($o['codigo_completo']) ?>')" title="Eliminar orden">
                                        <i class="fas fa-trash-can" style="font-size:0.75rem;"></i>
                                    </button>
                                </div>
                            </div>
                            <div style="font-size:0.83rem; color:#111827; font-weight:700; margin-bottom:0.2rem;">
                                <?= htmlspecialchars($o['cliente_nombre'] ?? 'Cliente General') ?>
                            </div>
                            <div style="font-size:0.78rem; color:#059669; font-weight:600; margin-bottom:0.4rem;">
                                <i class="fas fa-warehouse"></i> Disponible en Almacén Principal
                            </div>
                            <div class="card-items-box">
                                <?php foreach ($o['detalles'] as $d): ?>
                                <div class="card-item-row">
                                    <strong>[<?= $d['cantidad'] ?> un]</strong> <?= htmlspecialchars($d['descripcion']) ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="card-actions-bar" style="flex-direction:column; gap:0.4rem;">
                                <?php if ($user_role === 'Super Admin' || $user_role === 'Almacén'): ?>
                                <a href="/carpicenter_sys/modules/ordenes_egreso/egreso_nuevo.php?contrato_id=<?= $o['id'] ?>" class="btn btn-primary" style="font-size:0.75rem; padding:5px 10px; width:100%; text-align:center;">
                                    <i class="fas fa-boxes-packing" style="margin-right:4px;"></i> Emitir Orden de Egreso
                                </a>
                                <?php else: ?>
                                <div style="font-size:0.76rem; text-align:center; color:#059669; font-weight:700; padding:6px; background:#ECFDF5; border-radius:6px; width:100%;">
                                    <i class="fas fa-check-double"></i> Fabricación Completa
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>

            <!-- ================= VISTA TABLA (Single Line) ================= -->
            <div id="tableView" class="prd-table-card" style="display:none;">
                <div class="prd-table-header-title">
                    <h3><i class="fas fa-clipboard-list" style="color:#E31E24;"></i> Lista de Órdenes en Fabricación</h3>
                    <span style="font-size:0.8rem; color:#6B7280; font-weight:500;">
                        Mostrando <?= count($ordenesProduccion) ?> órdenes activas
                    </span>
                </div>

                <div style="overflow-x:auto;">
                    <table class="prd-table">
                        <thead>
                            <tr>
                                <th>N° Contrato</th>
                                <th>Cliente</th>
                                <th>Prioridad</th>
                                <th>F. Entrega Est.</th>
                                <th>Muebles a Fabricar</th>
                                <th>Sede Fabricación</th>
                                <th>Estado Producción</th>
                                <th style="text-align:center;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($ordenesProduccion)): ?>
                                <tr>
                                    <td colspan="8" style="text-align:center; padding:3.5rem; color:#9CA3AF;">
                                        <i class="fas fa-hammer" style="font-size:2.5rem; margin-bottom:0.8rem; opacity:0.35; display:block;"></i>
                                        No hay órdenes de producción pendientes en este momento.
                                    </td>
                                </tr>
                            <?php else: foreach ($ordenesProduccion as $o): 
                                $isUrgente = ($o['prioridad'] === 'Urgente');
                                $itemsDesc = implode(', ', array_map(function($d) {
                                    return '[' . $d['cantidad'] . ' un] ' . $d['descripcion'];
                                }, $o['detalles']));
                            ?>
                            <tr>
                                <td><span class="doc-badge"><?= htmlspecialchars($o['codigo_completo']) ?></span></td>
                                <td><strong style="color:#111827;"><?= htmlspecialchars($o['cliente_nombre'] ?? 'Cliente General') ?></strong></td>
                                <td>
                                    <span class="priority-badge <?= $isUrgente ? 'urgente' : 'normal' ?>">
                                        <?= htmlspecialchars($o['prioridad']) ?>
                                    </span>
                                </td>
                                <td>
                                    <i class="fas fa-calendar-days" style="color:#9CA3AF; margin-right:4px;"></i>
                                    <?= !empty($o['fecha_entrega_estimada']) ? date('d/m/Y', strtotime($o['fecha_entrega_estimada'])) : 'Sin fecha' ?>
                                </td>
                                <td>
                                    <div style="max-width:300px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?= htmlspecialchars($itemsDesc) ?>">
                                        <?= htmlspecialchars($itemsDesc) ?>
                                    </div>
                                </td>
                                <td>
                                    <span style="font-size:0.75rem; font-weight:700; color:#4F46E5; background:#EEF2FF; padding:2px 7px; border-radius:6px;">
                                        Almacén Principal
                                    </span>
                                </td>
                                <td>
                                    <select class="form-control" style="font-size:0.82rem; padding:0.35rem 0.6rem; border-radius:8px; border:1px solid #D1D5DB; font-weight:600;" onchange="moverEstado(<?= $o['id'] ?>, this.value)">
                                        <option value="Pendiente" <?= ($o['estado_contrato'] === 'Pendiente') ? 'selected' : '' ?>>🟡 Por Fabricar</option>
                                        <option value="En Producción" <?= ($o['estado_contrato'] === 'En Producción') ? 'selected' : '' ?>>🔵 En Fabricación</option>
                                        <option value="Listo para Entrega" <?= ($o['estado_contrato'] === 'Listo para Entrega') ? 'selected' : '' ?>>🟢 Terminado en Almacén</option>
                                    </select>
                                </td>
                                <td style="text-align:center;">
                                    <div style="display:inline-flex; align-items:center; gap:4px;">
                                        <a href="/carpicenter_sys/modules/contratos/contrato_view.php?id=<?= $o['id'] ?>" class="btn-action-soft view" title="Ver Contrato">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button type="button" class="btn-action-soft delete" title="Eliminar Orden" onclick="eliminarProduccion(<?= $o['id'] ?>, '<?= htmlspecialchars($o['codigo_completo']) ?>')">
                                            <i class="fas fa-trash-can"></i>
                                        </button>
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

<script>
function switchView(view) {
    const kanban = document.getElementById('kanbanView');
    const table = document.getElementById('tableView');
    const btnK = document.getElementById('btnKanban');
    const btnT = document.getElementById('btnTable');

    if (view === 'kanban') {
        kanban.style.display = 'grid';
        table.style.display = 'none';
        btnK.classList.add('active');
        btnT.classList.remove('active');
    } else {
        kanban.style.display = 'none';
        table.style.display = 'block';
        btnT.classList.add('active');
        btnK.classList.remove('active');
    }
}

function filterProduccion() {
    const q = document.getElementById('prodSearch').value.toLowerCase().trim();
    
    // Filtrar Kanban
    const cards = document.querySelectorAll('.kanban-card');
    cards.forEach(card => {
        const text = card.getAttribute('data-search') || '';
        card.style.display = text.includes(q) ? 'block' : 'none';
    });

    // Filtrar Tabla
    const rows = document.querySelectorAll('#tableView tbody tr');
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(q) ? '' : 'none';
    });
}

function moverEstado(contratoId, nuevoEstado) {
    const form = new FormData();
    form.append('action', 'update_estado_produccion');
    form.append('id', contratoId);
    form.append('estado_contrato', nuevoEstado);

    fetch('/carpicenter_sys/modules/contratos/contrato_guardar.php', {
        method: 'POST',
        body: form
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'No se pudo cambiar el estado.'));
        }
    })
    .catch(() => location.reload());
}

function eliminarProduccion(contratoId, codigo) {
    if (confirm('⚠️ ¿Seguro que deseas eliminar la orden ' + codigo + ' del flujo de producción?')) {
        const form = new FormData();
        form.append('action', 'delete');
        form.append('id', contratoId);

        fetch('/carpicenter_sys/modules/contratos/contrato_guardar.php', {
            method: 'POST',
            body: form
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error al eliminar: ' + (data.message || ''));
            }
        })
        .catch(() => location.reload());
    }
}
</script>

<?php include 'partials/footer.php'; ?>
</body>
</html>
