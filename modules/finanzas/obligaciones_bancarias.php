<?php
require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../config/db.php';

$page_title = 'Cuentas por Pagar';
$page_subtitle = 'Control de letras a proveedores, cronogramas bancarios y créditos';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create_letra') {
        $tipo = $_POST['tipo'] ?? 'LETRA_PROVEEDOR';
        $banco_prov = trim($_POST['banco_proveedor'] ?? '');
        $nro_unico = trim($_POST['nro_unico'] ?? '');
        $factura_ref = trim($_POST['factura_ref'] ?? '');
        $monto_soles = floatval($_POST['monto_soles'] ?? 0);
        $f_venc = !empty($_POST['f_venc']) ? $_POST['f_venc'] : null;
        $estado = $_POST['estado'] ?? 'PENDIENTE';
        
        $stmt = $db->prepare("INSERT INTO finanzas_bancos_letras (tipo, banco_proveedor, nro_unico, factura_ref, monto_soles, f_venc, estado) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$tipo, $banco_prov, $nro_unico, $factura_ref, $monto_soles, $f_venc, $estado]);
        header("Location: obligaciones_bancarias.php?tab=" . ($tipo === 'LETRA_PROVEEDOR' ? 'letras' : 'bancos') . "&msg=creado");
        exit;
    }
    
    if ($action === 'update_estado') {
        $id = intval($_POST['id']);
        $estado = $_POST['estado'] ?? 'PAGADO';
        $f_pago = !empty($_POST['f_pago']) ? $_POST['f_pago'] : date('Y-m-d');
        
        $stmt = $db->prepare("UPDATE finanzas_bancos_letras SET estado = ?, f_pago = ? WHERE id = ?");
        $stmt->execute([$estado, $f_pago, $id]);
        header("Location: obligaciones_bancarias.php?tab=" . ($_GET['tab'] ?? 'letras') . "&msg=actualizado");
        exit;
    }

    if ($action === 'delete_obligacion') {
        $id = intval($_POST['id']);
        $stmt = $db->prepare("DELETE FROM finanzas_bancos_letras WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: obligaciones_bancarias.php?tab=" . ($_GET['tab'] ?? 'letras') . "&msg=eliminado");
        exit;
    }
}

$tab = $_GET['tab'] ?? 'letras';
$search = trim($_GET['search'] ?? '');
$filtro_estado = $_GET['estado'] ?? '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

// Totales Generales
$tot_letras_pend = floatval($db->query("SELECT COALESCE(SUM(monto_soles), 0) FROM finanzas_bancos_letras WHERE tipo = 'LETRA_PROVEEDOR' AND estado != 'PAGADO'")->fetchColumn());
$tot_bancos_pend = floatval($db->query("SELECT COALESCE(SUM(monto_soles), 0) FROM finanzas_bancos_letras WHERE tipo != 'LETRA_PROVEEDOR' AND estado != 'PAGADO'")->fetchColumn());
$tot_general_pagado = floatval($db->query("SELECT COALESCE(SUM(monto_soles), 0) FROM finanzas_bancos_letras WHERE estado = 'PAGADO'")->fetchColumn());

// Consulta según Pestaña
$params = [];
$tipo_val = ($tab === 'letras') ? 'LETRA_PROVEEDOR' : 'PRESTAMO_BANCO';

$sql_base = "FROM finanzas_bancos_letras WHERE " . ($tab === 'letras' ? "tipo = 'LETRA_PROVEEDOR'" : "tipo != 'LETRA_PROVEEDOR'");

if (!empty($search)) {
    $sql_base .= " AND (banco_proveedor ILIKE ? OR nro_unico ILIKE ? OR factura_ref ILIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if (!empty($filtro_estado)) {
    if ($filtro_estado === 'PAGADO') $sql_base .= " AND estado = 'PAGADO'";
    else $sql_base .= " AND estado != 'PAGADO'";
}

$stmt_cnt = $db->prepare("SELECT COUNT(*) " . $sql_base);
$stmt_cnt->execute($params);
$total_items = $stmt_cnt->fetchColumn();
$total_pages = ceil($total_items / $limit);

$stmt_data = $db->prepare("SELECT * " . $sql_base . " ORDER BY (estado != 'PAGADO') DESC, f_venc ASC NULLS LAST, id DESC LIMIT $limit OFFSET $offset");
$stmt_data->execute($params);
$items = $stmt_data->fetchAll(PDO::FETCH_ASSOC);
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
        /* ===== ESTILOS CUENTAS POR PAGAR ===== */
        .cxp-hero {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.4rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .cxp-hero-title h1 {
            font-size: 1.45rem;
            font-weight: 800;
            color: #111827;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .cxp-hero-title p {
            color: #6B7280;
            font-size: 0.84rem;
            margin: 0.25rem 0 0 0;
        }

        /* Nav Tabs */
        .cxp-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.25rem;
        }
        .cxp-tab-link {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 12px;
            padding: 0.75rem 1.2rem;
            font-size: 0.86rem;
            font-weight: 700;
            color: #4B5563;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
            box-shadow: 0 1px 2px rgba(0,0,0,0.02);
        }
        .cxp-tab-link:hover {
            border-color: #D1D5DB;
            background: #F9FAFB;
        }
        .cxp-tab-link.active {
            background: #111827;
            color: #FFFFFF;
            border-color: #111827;
            box-shadow: 0 4px 12px rgba(17,24,39,0.15);
        }

        /* KPIs */
        .cxp-kpis-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 1.2rem;
            margin-bottom: 1.3rem;
        }
        .cxp-kpi-card {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 14px;
            padding: 1.2rem 1.4rem;
            display: flex;
            align-items: center;
            gap: 1.1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
            transition: transform 0.2s ease;
        }
        .cxp-kpi-card:hover {
            transform: translateY(-2px);
        }
        .cxp-kpi-icon {
            width: 50px;
            height: 50px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
        }
        .icon-rose-gradient { background: linear-gradient(135deg, rgba(220,38,38,0.12) 0%, rgba(239,68,68,0.2) 100%); color: #DC2626; }
        .icon-amber-gradient { background: linear-gradient(135deg, rgba(217,119,6,0.12) 0%, rgba(245,158,11,0.2) 100%); color: #D97706; }
        .icon-emerald-gradient { background: linear-gradient(135deg, rgba(5,150,105,0.12) 0%, rgba(16,185,129,0.2) 100%); color: #059669; }

        .cxp-kpi-info span.label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
            color: #6B7280;
            display: block;
            margin-bottom: 0.15rem;
        }
        .cxp-kpi-info h3 {
            font-size: 1.35rem;
            font-weight: 800;
            color: #111827;
            margin: 0;
            line-height: 1.2;
        }
        .cxp-kpi-info span.sub-tag {
            font-size: 0.74rem;
            font-weight: 600;
            display: inline-block;
            margin-top: 0.3rem;
            padding: 2px 8px;
            border-radius: 20px;
        }

        /* Filter Panel */
        .cxp-filter-card {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 14px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.25rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        .cxp-filter-form {
            display: flex;
            gap: 0.85rem;
            flex-wrap: wrap;
            align-items: center;
        }
        .cxp-search-box {
            flex: 2;
            min-width: 250px;
            position: relative;
        }
        .cxp-search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #9CA3AF;
            font-size: 0.9rem;
        }
        .cxp-search-box input {
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
        .cxp-search-box input:focus {
            background: #FFFFFF;
            border-color: #E31E24;
            box-shadow: 0 0 0 3px rgba(227,30,36,0.1);
        }
        .cxp-select {
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
        }

        /* Table Card */
        .cxp-table-card {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        }
        .cxp-table-header-title {
            padding: 1rem 1.4rem;
            border-bottom: 1px solid #F3F4F6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .cxp-table-header-title h3 {
            font-size: 0.95rem;
            font-weight: 700;
            color: #111827;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .cxp-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        .cxp-table th {
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
        .cxp-table td {
            padding: 0.9rem 1rem;
            border-bottom: 1px solid #F3F4F6;
            font-size: 0.85rem;
            color: #374151;
            vertical-align: middle;
        }
        .cxp-table tbody tr:hover {
            background: #F9FAFB;
        }

        /* Badges */
        .badge-doc {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-weight: 700;
            font-size: 0.8rem;
            color: #1E293B;
            background: #F1F5F9;
            padding: 2px 7px;
            border-radius: 6px;
            border: 1px solid #CBD5E1;
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
        .status-pill.pagado { background: rgba(5,150,105,0.1); color: #059669; border: 1px solid rgba(5,150,105,0.25); }
        .status-pill.pendiente { background: rgba(220,38,38,0.1); color: #DC2626; border: 1px solid rgba(220,38,38,0.25); }

        /* Actions */
        .btn-action-group {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }
        .btn-pay-pill {
            background: rgba(5,150,105,0.1);
            color: #059669;
            border: 1px solid rgba(5,150,105,0.25);
            padding: 4px 9px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.15s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-pay-pill:hover {
            background: #059669;
            color: #FFFFFF;
        }
        .btn-icon-soft {
            width: 28px;
            height: 28px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: none;
            cursor: pointer;
            font-size: 0.8rem;
            transition: all 0.15s;
        }
        .btn-icon-soft.delete { background: rgba(220,38,38,0.08); color: #DC2626; }
        .btn-icon-soft.delete:hover { background: #DC2626; color: #FFFFFF; }

        /* Modals */
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
            max-width: 560px;
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
            padding: 1.4rem;
        }
        .modal-footer {
            padding: 1rem 1.4rem;
            background: #F9FAFB;
            border-top: 1px solid #E5E7EB;
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            font-size: 0.76rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #4B5563;
            margin-bottom: 0.35rem;
        }
        .form-control-custom {
            width: 100%;
            padding: 0.58rem 0.85rem;
            border-radius: 8px;
            border: 1px solid #D1D5DB;
            background: #FFFFFF;
            font-size: 0.88rem;
            color: #111827;
            outline: none;
            box-sizing: border-box;
        }
        .form-control-custom:focus {
            border-color: #E31E24;
            box-shadow: 0 0 0 3px rgba(227,30,36,0.1);
        }

        /* Toast */
        .cxp-toast {
            background: #10B981;
            color: #FFFFFF;
            padding: 0.85rem 1.25rem;
            border-radius: 10px;
            margin-bottom: 1.2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-weight: 600;
            font-size: 0.88rem;
            box-shadow: 0 4px 12px rgba(16,185,129,0.25);
        }
    </style>
</head>
<body>
    <div class="app-wrapper">
        <?php include __DIR__ . '/../../views/partials/sidebar.php'; ?>
        <div class="main-content">
            <?php include __DIR__ . '/../../views/partials/header.php'; ?>
            <div class="page-content">

                <!-- Header de la Página -->
                <div class="cxp-hero">
                    <div class="cxp-hero-title">
                        <h1><i class="fas fa-file-invoice-dollar" style="color:#E31E24;"></i> Cuentas por Pagar (Letras y Bancos)</h1>
                        <p>Cronogramas bancarios y seguimiento de compromisos comerciales de pago</p>
                    </div>
                    <button class="btn btn-primary" onclick="abrirModal('modalNuevaLetra')" style="font-weight:600; padding:0.55rem 1.2rem; border-radius:10px; box-shadow:0 4px 12px rgba(227,30,36,0.25);">
                        <i class="fas fa-plus" style="margin-right:6px;"></i> Registrar Obligación / Letra
                    </button>
                </div>

                <!-- Toast Alerts -->
                <?php if (isset($_GET['msg'])): ?>
                    <div id="cxpToast" class="cxp-toast">
                        <div>
                            <i class="fas fa-check-circle" style="margin-right:8px;"></i>
                            <?php 
                                if ($_GET['msg'] === 'creado') echo "Obligación financiera registrada exitosamente.";
                                elseif ($_GET['msg'] === 'actualizado') echo "Estado de pago actualizado correctamente.";
                                elseif ($_GET['msg'] === 'eliminado') echo "Registro eliminado del sistema.";
                            ?>
                        </div>
                        <button type="button" onclick="this.parentElement.remove()" style="background:none; border:none; color:#FFFFFF; font-size:1.2rem; cursor:pointer;">&times;</button>
                    </div>
                <?php endif; ?>

                <!-- KPI Cards -->
                <div class="cxp-kpis-grid">
                    <div class="cxp-kpi-card">
                        <div class="cxp-kpi-icon icon-rose-gradient">
                            <i class="fas fa-file-signature"></i>
                        </div>
                        <div class="cxp-kpi-info">
                            <span class="label">Letras Proveedores</span>
                            <h3 style="color:#DC2626;"><?= formatearMonto($tot_letras_pend) ?></h3>
                            <span class="sub-tag" style="background:#FEF2F2; color:#DC2626;">
                                Letras por pagar
                            </span>
                        </div>
                    </div>

                    <div class="cxp-kpi-card">
                        <div class="cxp-kpi-icon icon-amber-gradient">
                            <i class="fas fa-building-columns"></i>
                        </div>
                        <div class="cxp-kpi-info">
                            <span class="label">Créditos Bancarios</span>
                            <h3 style="color:#D97706;"><?= formatearMonto($tot_bancos_pend) ?></h3>
                            <span class="sub-tag" style="background:#FFFBEB; color:#D97706;">
                                Cuotas préstamos
                            </span>
                        </div>
                    </div>

                    <div class="cxp-kpi-card">
                        <div class="cxp-kpi-icon icon-emerald-gradient">
                            <i class="fas fa-circle-check"></i>
                        </div>
                        <div class="cxp-kpi-info">
                            <span class="label">Total Cancelado</span>
                            <h3 style="color:#059669;"><?= formatearMonto($tot_general_pagado) ?></h3>
                            <span class="sub-tag" style="background:#ECFDF5; color:#059669;">
                                Deudas pagadas
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Pestañas de Navegación -->
                <div class="cxp-tabs">
                    <a href="?tab=letras" class="cxp-tab-link <?= $tab==='letras'?'active':'' ?>">
                        <i class="fas fa-file-signature"></i> 1. Letras por Pagar (Proveedores)
                    </a>
                    <a href="?tab=bancos" class="cxp-tab-link <?= $tab==='bancos'?'active':'' ?>">
                        <i class="fas fa-building-columns"></i> 2. Créditos y Préstamos Bancarios
                    </a>
                </div>

                <!-- Filtros -->
                <div class="cxp-filter-card">
                    <form method="GET" class="cxp-filter-form">
                        <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
                        <div class="cxp-search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" name="search" placeholder="Buscar por proveedor, banco, N° único o factura..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <select name="estado" class="cxp-select" onchange="this.form.submit()">
                            <option value="">Todos los Estados</option>
                            <option value="PENDIENTE" <?= $filtro_estado==='PENDIENTE'?'selected':'' ?>>⏳ Solo Pendientes</option>
                            <option value="PAGADO" <?= $filtro_estado==='PAGADO'?'selected':'' ?>>✅ Solo Pagados</option>
                        </select>
                        <button type="submit" class="btn btn-primary" style="padding:0.58rem 1.1rem; border-radius:10px; font-weight:600;">
                            <i class="fas fa-filter"></i> Filtrar
                        </button>
                        <?php if(!empty($search) || !empty($filtro_estado)): ?>
                            <a href="obligaciones_bancarias.php?tab=<?= htmlspecialchars($tab) ?>" class="btn btn-outline" style="padding:0.58rem 0.9rem; border-radius:10px;" title="Limpiar Filtros">
                                <i class="fas fa-times"></i>
                            </a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Tabla de Datos -->
                <div class="cxp-table-card">
                    <div class="cxp-table-header-title">
                        <h3>
                            <i class="fas <?= $tab === 'letras' ? 'fa-file-signature' : 'fa-building-columns' ?>" style="color:<?= $tab === 'letras' ? '#7C3AED' : '#2563EB' ?>;"></i>
                            <?= $tab === 'letras' ? 'Letras Comerciales por Pagar a Proveedores' : 'Cronograma de Préstamos y Créditos Bancarios' ?>
                        </h3>
                        <span style="font-size:0.8rem; color:#6B7280; font-weight:500;">
                            Mostrando <?= count($items) ?> de <?= $total_items ?> obligaciones
                        </span>
                    </div>

                    <div style="overflow-x:auto;">
                        <table class="cxp-table">
                            <thead>
                                <tr>
                                    <th>Proveedor / Entidad</th>
                                    <th>N° Único / Letra</th>
                                    <th>Factura Asociada</th>
                                    <th>F. Vencimiento</th>
                                    <th style="text-align:right;">Importe (S/)</th>
                                    <th>Estado</th>
                                    <th>F. Pago</th>
                                    <th style="text-align:center;">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($items)): ?>
                                    <tr>
                                        <td colspan="8" style="text-align:center; padding:3.5rem; color:#9CA3AF;">
                                            <i class="fas fa-folder-open" style="font-size:2.5rem; margin-bottom:0.8rem; opacity:0.35; display:block;"></i>
                                            No se encontraron obligaciones registradas con los filtros seleccionados.
                                        </td>
                                    </tr>
                                <?php else: foreach($items as $l): 
                                    $isPagado = ($l['estado'] === 'PAGADO');
                                    $isVencido = (!empty($l['f_venc']) && strtotime($l['f_venc']) < strtotime(date('Y-m-d')) && !$isPagado);
                                ?>
                                    <tr>
                                        <td><strong style="color:#111827;"><?= htmlspecialchars($l['banco_proveedor']) ?></strong></td>
                                        <td><span class="badge-doc"><?= htmlspecialchars($l['nro_unico'] ?: 'S/N') ?></span></td>
                                        <td><span style="color:#6B7280; font-size:0.82rem;"><?= htmlspecialchars($l['factura_ref'] ?: '—') ?></span></td>
                                        <td>
                                            <?php if(!empty($l['f_venc'])): ?>
                                                <span style="font-weight:600; color:<?= $isVencido ? '#DC2626' : '#4B5563' ?>;">
                                                    <?php if($isVencido): ?><i class="fas fa-exclamation-circle" style="color:#DC2626;"></i><?php endif; ?>
                                                    <?= date('d/m/Y', strtotime($l['f_venc'])) ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="color:#9CA3AF;">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align:right; font-weight:800; color:<?= $isPagado ? '#059669' : '#DC2626' ?>;">
                                            <?= formatearMonto($l['monto_soles']) ?>
                                        </td>
                                        <td>
                                            <?php if($isPagado): ?>
                                                <span class="status-pill pagado"><i class="fas fa-circle-check"></i> PAGADO</span>
                                            <?php else: ?>
                                                <span class="status-pill pendiente"><i class="fas fa-clock"></i> PENDIENTE</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="color:#6B7280; font-size:0.82rem;">
                                            <?= !empty($l['f_pago']) ? date('d/m/Y', strtotime($l['f_pago'])) : '<span style="color:#9CA3AF;">—</span>' ?>
                                        </td>
                                        <td style="text-align:center;">
                                            <div class="btn-action-group">
                                                <?php if(!$isPagado): ?>
                                                    <form method="POST" style="display:inline;" onsubmit="return confirm('¿Confirmar pago de esta obligación?');">
                                                        <input type="hidden" name="action" value="update_estado">
                                                        <input type="hidden" name="id" value="<?= $l['id'] ?>">
                                                        <input type="hidden" name="estado" value="PAGADO">
                                                        <button type="submit" class="btn-pay-pill" title="Marcar como Pagado">
                                                            <i class="fas fa-check"></i> Pagar
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar este registro?');">
                                                    <input type="hidden" name="action" value="delete_obligacion">
                                                    <input type="hidden" name="id" value="<?= $l['id'] ?>">
                                                    <button type="submit" class="btn-icon-soft delete" title="Eliminar">
                                                        <i class="fas fa-trash-can"></i>
                                                    </button>
                                                </form>
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

    <!-- Modal Nueva Letra / Crédito -->
    <div class="modal-overlay" id="modalNuevaLetra">
        <div class="modal-box">
            <div class="modal-header">
                <h3><i class="fas fa-file-invoice-dollar" style="color:#E31E24;"></i> Registrar Obligación / Letra</h3>
                <button type="button" class="btn-icon-soft" onclick="cerrarModal('modalNuevaLetra')">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create_letra">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Tipo de Obligación *</label>
                        <select name="tipo" class="form-control-custom">
                            <option value="LETRA_PROVEEDOR" <?= $tab==='letras'?'selected':'' ?>>LETRA COMERCIAL PROVEEDOR</option>
                            <option value="PRESTAMO_BANCO" <?= $tab==='bancos'?'selected':'' ?>>CRÉDITO / PRÉSTAMO BANCARIO</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Proveedor o Banco *</label>
                        <input type="text" name="banco_proveedor" class="form-control-custom" required placeholder="Ej: TABLEROS DEL PERU SAC o BCP">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>N° Único / N° Letra</label>
                            <input type="text" name="nro_unico" class="form-control-custom" placeholder="Ej: LT-009182">
                        </div>
                        <div class="form-group">
                            <label>Factura de Referencia</label>
                            <input type="text" name="factura_ref" class="form-control-custom" placeholder="Ej: F001-4458">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Monto en Soles (S/) *</label>
                            <input type="number" step="0.01" name="monto_soles" class="form-control-custom" required placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label>Fecha de Vencimiento *</label>
                            <input type="date" name="f_venc" class="form-control-custom" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="cerrarModal('modalNuevaLetra')">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Guardar Obligación</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function abrirModal(id) { document.getElementById(id).classList.add('open'); }
    function cerrarModal(id) { document.getElementById(id).classList.remove('open'); }
    </script>
</body>
</html>
