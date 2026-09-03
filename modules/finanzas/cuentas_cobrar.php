<?php
require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../config/db.php';

$page_title = 'Cuentas por Cobrar';
$page_subtitle = 'Gestión de comprobantes, abonos y saldos pendientes de clientes';

// Manejo de Acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $referencia = trim($_POST['referencia'] ?? '');
        $ft_lt = trim($_POST['ft_lt'] ?? '');
        $cliente = trim($_POST['cliente'] ?? '');
        $f_venc = !empty($_POST['f_venc']) ? $_POST['f_venc'] : null;
        $monto_total = floatval($_POST['monto_total'] ?? 0);
        $banco = trim($_POST['banco'] ?? 'BCP');
        $monto_pagado = floatval($_POST['monto_pagado'] ?? 0);
        $fecha_pago = !empty($_POST['fecha_pago']) ? $_POST['fecha_pago'] : null;
        
        // Auto-calcular estado si no viene explícito
        $saldo = $monto_total - $monto_pagado;
        if ($saldo <= 0.001 && $monto_total > 0) {
            $estado = 'PAGADO';
        } elseif ($monto_pagado > 0) {
            $estado = 'PARCIAL';
        } else {
            $estado = 'PENDIENTE';
        }
        
        $stmt = $db->prepare("
            INSERT INTO finanzas_cuentas_cobrar (referencia, ft_lt, cliente, f_venc, monto_total, banco, monto_pagado, fecha_pago, estado)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$referencia, $ft_lt, $cliente, $f_venc, $monto_total, $banco, $monto_pagado, $fecha_pago, $estado]);
        header("Location: cuentas_cobrar.php?msg=creado");
        exit;
    }
    
    if ($action === 'update_pago') {
        $id = intval($_POST['id']);
        $monto_pagado = floatval($_POST['monto_pagado'] ?? 0);
        $banco = trim($_POST['banco'] ?? 'BCP');
        $fecha_pago = !empty($_POST['fecha_pago']) ? $_POST['fecha_pago'] : date('Y-m-d');
        
        // Obtener monto total para validar estado
        $stmtCheck = $db->prepare("SELECT monto_total FROM finanzas_cuentas_cobrar WHERE id = ?");
        $stmtCheck->execute([$id]);
        $monto_total = floatval($stmtCheck->fetchColumn() ?: 0);
        
        $saldo = $monto_total - $monto_pagado;
        if ($saldo <= 0.001 && $monto_total > 0) {
            $estado = 'PAGADO';
        } elseif ($monto_pagado > 0) {
            $estado = 'PARCIAL';
        } else {
            $estado = 'PENDIENTE';
        }
        
        $stmt = $db->prepare("
            UPDATE finanzas_cuentas_cobrar 
            SET monto_pagado = ?, banco = ?, fecha_pago = ?, estado = ?
            WHERE id = ?
        ");
        $stmt->execute([$monto_pagado, $banco, $fecha_pago, $estado, $id]);
        header("Location: cuentas_cobrar.php?msg=actualizado");
        exit;
    }
    
    if ($action === 'edit_cobro') {
        $id = intval($_POST['id']);
        $referencia = trim($_POST['referencia'] ?? '');
        $ft_lt = trim($_POST['ft_lt'] ?? '');
        $cliente = trim($_POST['cliente'] ?? '');
        $f_venc = !empty($_POST['f_venc']) ? $_POST['f_venc'] : null;
        $monto_total = floatval($_POST['monto_total'] ?? 0);
        $banco = trim($_POST['banco'] ?? 'BCP');
        $monto_pagado = floatval($_POST['monto_pagado'] ?? 0);
        $fecha_pago = !empty($_POST['fecha_pago']) ? $_POST['fecha_pago'] : null;
        
        $saldo = $monto_total - $monto_pagado;
        if ($saldo <= 0.001 && $monto_total > 0) {
            $estado = 'PAGADO';
        } elseif ($monto_pagado > 0) {
            $estado = 'PARCIAL';
        } else {
            $estado = 'PENDIENTE';
        }

        $stmt = $db->prepare("
            UPDATE finanzas_cuentas_cobrar 
            SET referencia = ?, ft_lt = ?, cliente = ?, f_venc = ?, monto_total = ?, banco = ?, monto_pagado = ?, fecha_pago = ?, estado = ?
            WHERE id = ?
        ");
        $stmt->execute([$referencia, $ft_lt, $cliente, $f_venc, $monto_total, $banco, $monto_pagado, $fecha_pago, $estado, $id]);
        header("Location: cuentas_cobrar.php?msg=actualizado");
        exit;
    }
    
    if ($action === 'delete_cobro') {
        $id = intval($_POST['id']);
        $stmt = $db->prepare("DELETE FROM finanzas_cuentas_cobrar WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: cuentas_cobrar.php?msg=eliminado");
        exit;
    }
}

// Resumen KPIs
$totales = $db->query("
    SELECT 
        COALESCE(SUM(monto_total), 0) as total_facturado,
        COALESCE(SUM(monto_pagado), 0) as total_cobrado,
        COALESCE(SUM(monto_total - monto_pagado), 0) as total_pendiente,
        COUNT(*) as total_registros
    FROM finanzas_cuentas_cobrar
")->fetch(PDO::FETCH_ASSOC);

$porcentajeCobrado = $totales['total_facturado'] > 0 ? round(($totales['total_cobrado'] / $totales['total_facturado']) * 100, 1) : 0;

// Filtros y Paginación
$filtro_estado = $_GET['estado'] ?? '';
$filtro_banco = $_GET['banco'] ?? '';
$search = trim($_GET['search'] ?? '');
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

$sql_base = "FROM finanzas_cuentas_cobrar WHERE 1=1";
$params = [];

if ($filtro_estado) {
    if ($filtro_estado === 'PAGADO') {
        $sql_base .= " AND (monto_total - monto_pagado) <= 0.001";
    } elseif ($filtro_estado === 'PARCIAL') {
        $sql_base .= " AND monto_pagado > 0 AND (monto_total - monto_pagado) > 0.001";
    } elseif ($filtro_estado === 'PENDIENTE') {
        $sql_base .= " AND (monto_pagado = 0 OR monto_pagado IS NULL) AND (monto_total - monto_pagado) > 0.001";
    } elseif ($filtro_estado === 'VENCIDO') {
        $sql_base .= " AND f_venc < CURRENT_DATE AND (monto_total - monto_pagado) > 0.001";
    }
}

if ($filtro_banco) {
    $sql_base .= " AND banco = ?";
    $params[] = $filtro_banco;
}

if ($search) {
    $sql_base .= " AND (cliente ILIKE ? OR ft_lt ILIKE ? OR referencia ILIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Total para paginación
$stmt_count = $db->prepare("SELECT COUNT(*) " . $sql_base);
$stmt_count->execute($params);
$total_items = $stmt_count->fetchColumn();
$total_pages = ceil($total_items / $limit);

// Items paginados
$sql_data = "SELECT * " . $sql_base . " ORDER BY (monto_total - monto_pagado) > 0 DESC, f_venc ASC NULLS LAST, id DESC LIMIT $limit OFFSET $offset";
$stmt = $db->prepare($sql_data);
$stmt->execute($params);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Bancos disponibles
$bancosDisponibles = ['BCP', 'BBVA', 'INTERBANK', 'SCOTIABANK', 'EFECTIVO'];
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
        /* ===== ESTILOS PREMIUM - CUENTAS POR COBRAR ===== */
        .cxc-hero {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .cxc-hero-title h1 {
            font-size: 1.45rem;
            font-weight: 800;
            color: #111827;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .cxc-hero-title p {
            color: #6B7280;
            font-size: 0.84rem;
            margin: 0.25rem 0 0 0;
        }
        .cxc-hero-actions {
            display: flex;
            gap: 0.75rem;
            align-items: center;
        }

        /* Stats Cards */
        .cxc-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.2rem;
            margin-bottom: 1.5rem;
        }
        .cxc-stat-card {
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
        .cxc-stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.06);
        }
        .cxc-stat-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
            flex-shrink: 0;
        }
        .icon-blue-gradient {
            background: linear-gradient(135deg, rgba(37,99,235,0.12) 0%, rgba(59,130,246,0.2) 100%);
            color: #2563EB;
        }
        .icon-green-gradient {
            background: linear-gradient(135deg, rgba(5,150,105,0.12) 0%, rgba(16,185,129,0.2) 100%);
            color: #059669;
        }
        .icon-red-gradient {
            background: linear-gradient(135deg, rgba(220,38,38,0.12) 0%, rgba(239,68,68,0.2) 100%);
            color: #DC2626;
        }
        .icon-amber-gradient {
            background: linear-gradient(135deg, rgba(217,119,6,0.12) 0%, rgba(245,158,11,0.2) 100%);
            color: #D97706;
        }
        .cxc-stat-info {
            flex: 1;
            min-width: 0;
        }
        .cxc-stat-info span.label {
            font-size: 0.76rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
            color: #6B7280;
            display: block;
            margin-bottom: 0.2rem;
        }
        .cxc-stat-info h3 {
            font-size: 1.35rem;
            font-weight: 800;
            color: #111827;
            margin: 0;
            line-height: 1.2;
            letter-spacing: -0.3px;
        }
        .cxc-stat-info span.sub-indicator {
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            margin-top: 0.35rem;
            padding: 2px 8px;
            border-radius: 20px;
        }

        /* Filter Panel */
        .cxc-filter-card {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 14px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.25rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        .cxc-filter-form {
            display: flex;
            gap: 0.85rem;
            flex-wrap: wrap;
            align-items: center;
        }
        .cxc-search-input-group {
            flex: 2;
            min-width: 260px;
            position: relative;
        }
        .cxc-search-input-group i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #9CA3AF;
            font-size: 0.9rem;
        }
        .cxc-search-input-group input {
            width: 100%;
            padding: 0.58rem 0.85rem 0.58rem 2.25rem;
            border-radius: 10px;
            border: 1px solid #D1D5DB;
            background: #F9FAFB;
            font-size: 0.85rem;
            color: #111827;
            outline: none;
            transition: all 0.2s;
        }
        .cxc-search-input-group input:focus {
            background: #FFFFFF;
            border-color: #E31E24;
            box-shadow: 0 0 0 3px rgba(227,30,36,0.1);
        }
        .cxc-select {
            padding: 0.58rem 2rem 0.58rem 0.85rem;
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
        .cxc-select:focus {
            border-color: #E31E24;
            background: #FFFFFF;
        }

        /* Table Design */
        .cxc-table-card {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        }
        .cxc-table-header-title {
            padding: 1rem 1.4rem;
            border-bottom: 1px solid #F3F4F6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .cxc-table-header-title h3 {
            font-size: 0.95rem;
            font-weight: 700;
            color: #111827;
            margin: 0;
        }
        .cxc-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        .cxc-table th {
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
        .cxc-table td {
            padding: 0.95rem 1rem;
            border-bottom: 1px solid #F3F4F6;
            font-size: 0.85rem;
            color: #374151;
            vertical-align: middle;
        }
        .cxc-table tbody tr {
            transition: background 0.15s ease;
        }
        .cxc-table tbody tr:hover {
            background: #F9FAFB;
        }

        /* Badges */
        .doc-badge {
            display: inline-block;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-weight: 700;
            font-size: 0.82rem;
            color: #1E293B;
            background: #F1F5F9;
            padding: 3px 8px;
            border-radius: 6px;
            border: 1px solid #CBD5E1;
        }
        .ref-tag {
            font-size: 0.72rem;
            color: #64748B;
            margin-top: 3px;
            display: block;
        }

        /* Bank Badges */
        .bank-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 0.74rem;
            font-weight: 700;
            letter-spacing: 0.3px;
        }
        .bank-bcp { background: #EBF5FF; color: #002A8F; border: 1px solid #BFDBFE; }
        .bank-bbva { background: #EEF2FF; color: #004481; border: 1px solid #C7D2FE; }
        .bank-interbank { background: #ECFDF5; color: #00965E; border: 1px solid #A7F3D0; }
        .bank-scotiabank { background: #FEF2F2; color: #EC111A; border: 1px solid #FECACA; }
        .bank-efectivo { background: #F3F4F6; color: #4B5563; border: 1px solid #E5E7EB; }

        /* Status Badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .status-pagado { background: rgba(5,150,105,0.1); color: #059669; border: 1px solid rgba(5,150,105,0.25); }
        .status-parcial { background: rgba(37,99,235,0.1); color: #2563EB; border: 1px solid rgba(37,99,235,0.25); }
        .status-pendiente { background: rgba(217,119,6,0.1); color: #D97706; border: 1px solid rgba(217,119,6,0.25); }
        .status-vencido { background: rgba(220,38,38,0.1); color: #DC2626; border: 1px solid rgba(220,38,38,0.25); }

        /* Progress Mini-bar */
        .cxc-progress-mini {
            width: 100%;
            max-width: 90px;
            height: 5px;
            background: #E5E7EB;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 4px;
        }
        .cxc-progress-mini-bar {
            height: 100%;
            background: #059669;
            border-radius: 4px;
        }

        /* Action Buttons */
        .btn-action-group {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }
        .btn-abono-pill {
            background: rgba(5,150,105,0.1);
            color: #059669;
            border: 1px solid rgba(5,150,105,0.25);
            padding: 5px 10px;
            border-radius: 8px;
            font-size: 0.78rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .btn-abono-pill:hover {
            background: #059669;
            color: #FFFFFF;
            border-color: #059669;
        }
        .btn-icon-soft {
            width: 30px;
            height: 30px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: none;
            cursor: pointer;
            font-size: 0.82rem;
            transition: all 0.15s ease;
            text-decoration: none;
        }
        .btn-icon-soft.edit { background: #F3F4F6; color: #4B5563; }
        .btn-icon-soft.edit:hover { background: #E5E7EB; color: #111827; }
        .btn-icon-soft.delete { background: rgba(220,38,38,0.08); color: #DC2626; }
        .btn-icon-soft.delete:hover { background: #DC2626; color: #FFFFFF; }

        /* Modal Styles */
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
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            animation: modalFadeIn 0.2s cubic-bezier(0.16, 1, 0.3, 1);
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
        .modal-header h3 {
            font-size: 1.1rem;
            font-weight: 700;
            color: #111827;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .modal-body {
            padding: 1.5rem;
        }
        .modal-footer {
            padding: 1rem 1.5rem;
            background: #F9FAFB;
            border-top: 1px solid #E5E7EB;
            border-bottom-left-radius: 16px;
            border-bottom-right-radius: 16px;
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
            margin-bottom: 1.1rem;
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
            padding: 0.6rem 0.85rem;
            border-radius: 8px;
            border: 1px solid #D1D5DB;
            background: #FFFFFF;
            font-size: 0.88rem;
            color: #111827;
            outline: none;
            box-sizing: border-box;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }
        .form-control-custom:focus {
            border-color: #E31E24;
            box-shadow: 0 0 0 3px rgba(227,30,36,0.1);
        }

        /* Toast / Alert */
        .cxc-toast {
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
            animation: slideDown 0.3s ease;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
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
                <div class="cxc-hero">
                    <div class="cxc-hero-title">
                        <h1><i class="fas fa-hand-holding-dollar" style="color:#E31E24;"></i> Cuentas por Cobrar</h1>
                        <p>Registro, amortizaciones y control de cartera de cobranzas por cliente</p>
                    </div>
                    <div class="cxc-hero-actions">
                        <a href="export_cuentas_cobrar.php?<?= http_build_query($_GET) ?>" class="btn btn-outline" style="border-color:#107C41; color:#107C41; font-weight:600; padding:0.55rem 1rem; border-radius:10px;">
                            <i class="fas fa-file-excel" style="margin-right:6px;"></i> Exportar a Excel
                        </a>
                        <button class="btn btn-primary" onclick="abrirModal('modalNuevoCobro')" style="font-weight:600; padding:0.55rem 1.2rem; border-radius:10px; box-shadow:0 4px 12px rgba(227,30,36,0.25);">
                            <i class="fas fa-plus" style="margin-right:6px;"></i> Nueva Cuenta por Cobrar
                        </button>
                    </div>
                </div>

                <!-- Toast Alerts -->
                <?php if (isset($_GET['msg'])): ?>
                    <div id="cxcToast" class="cxc-toast">
                        <div>
                            <i class="fas fa-check-circle" style="margin-right:8px;"></i>
                            <?php 
                                if ($_GET['msg'] === 'creado') echo "Registro de cuenta por cobrar creado exitosamente.";
                                elseif ($_GET['msg'] === 'actualizado') echo "Abono / Datos de cobranza actualizados correctamente.";
                                elseif ($_GET['msg'] === 'eliminado') echo "Registro eliminado permanentemente del sistema.";
                            ?>
                        </div>
                        <button type="button" onclick="this.parentElement.remove()" style="background:none; border:none; color:#FFFFFF; font-size:1.2rem; cursor:pointer; line-height:1;">&times;</button>
                    </div>
                <?php endif; ?>

                <!-- Stats Cards / KPIs -->
                <div class="cxc-stats-grid">
                    <div class="cxc-stat-card">
                        <div class="cxc-stat-icon icon-blue-gradient">
                            <i class="fas fa-file-invoice-dollar"></i>
                        </div>
                        <div class="cxc-stat-info">
                            <span class="label">Total Facturado</span>
                            <h3><?= formatearMonto($totales['total_facturado']) ?></h3>
                            <span class="sub-indicator" style="background:#EFF6FF; color:#2563EB;">
                                <i class="fas fa-layer-group"></i> <?= $totales['total_registros'] ?> Comprobantes
                            </span>
                        </div>
                    </div>

                    <div class="cxc-stat-card">
                        <div class="cxc-stat-icon icon-green-gradient">
                            <i class="fas fa-circle-check"></i>
                        </div>
                        <div class="cxc-stat-info">
                            <span class="label">Total Cobrado</span>
                            <h3 style="color:#059669;"><?= formatearMonto($totales['total_cobrado']) ?></h3>
                            <span class="sub-indicator" style="background:#ECFDF5; color:#059669;">
                                <i class="fas fa-arrow-trend-up"></i> <?= $porcentajeCobrado ?>% Recaudado
                            </span>
                        </div>
                    </div>

                    <div class="cxc-stat-card">
                        <div class="cxc-stat-icon icon-red-gradient">
                            <i class="fas fa-clock-rotate-left"></i>
                        </div>
                        <div class="cxc-stat-info">
                            <span class="label">Saldo por Cobrar</span>
                            <h3 style="color:#DC2626;"><?= formatearMonto($totales['total_pendiente']) ?></h3>
                            <span class="sub-indicator" style="background:#FEF2F2; color:#DC2626;">
                                <i class="fas fa-triangle-exclamation"></i> <?= formatearMonto($totales['total_pendiente']) ?> Pendiente
                            </span>
                        </div>
                    </div>

                    <div class="cxc-stat-card">
                        <div class="cxc-stat-icon icon-amber-gradient">
                            <i class="fas fa-receipt"></i>
                        </div>
                        <div class="cxc-stat-info">
                            <span class="label">Total Registros</span>
                            <h3><?= $total_items ?></h3>
                            <span class="sub-indicator" style="background:#FFFBEB; color:#D97706;">
                                <i class="fas fa-filter"></i> En este filtro
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Filtros y Búsqueda -->
                <div class="cxc-filter-card">
                    <form method="GET" class="cxc-filter-form">
                        <div class="cxc-search-input-group">
                            <i class="fas fa-search"></i>
                            <input type="text" name="search" placeholder="Buscar por cliente, N° comprobante o referencia..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                        
                        <select name="estado" class="cxc-select" onchange="this.form.submit()">
                            <option value="">Todos los Estados</option>
                            <option value="PENDIENTE" <?= $filtro_estado==='PENDIENTE'?'selected':'' ?>>⏳ Pendientes (Sin abono)</option>
                            <option value="PARCIAL" <?= $filtro_estado==='PARCIAL'?'selected':'' ?>>🔄 Con Abonos (Parcial)</option>
                            <option value="VENCIDO" <?= $filtro_estado==='VENCIDO'?'selected':'' ?>>⚠️ Vencidos</option>
                            <option value="PAGADO" <?= $filtro_estado==='PAGADO'?'selected':'' ?>>✅ Pagados (100%)</option>
                        </select>

                        <select name="banco" class="cxc-select" onchange="this.form.submit()">
                            <option value="">Todos los Bancos</option>
                            <?php foreach ($bancosDisponibles as $b): ?>
                                <option value="<?= $b ?>" <?= $filtro_banco===$b?'selected':'' ?>><?= $b ?></option>
                            <?php endforeach; ?>
                        </select>

                        <button type="submit" class="btn btn-primary" style="padding:0.58rem 1.1rem; border-radius:10px; font-weight:600;">
                            <i class="fas fa-filter"></i> Filtrar
                        </button>

                        <?php if(!empty($search) || !empty($filtro_estado) || !empty($filtro_banco)): ?>
                            <a href="cuentas_cobrar.php" class="btn btn-outline" style="padding:0.58rem 0.9rem; border-radius:10px;" title="Limpiar Filtros">
                                <i class="fas fa-times"></i>
                            </a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Tabla Principal -->
                <div class="cxc-table-card">
                    <div class="cxc-table-header-title">
                        <h3>Cartera de Clientes y Cobranzas</h3>
                        <span style="font-size:0.8rem; color:#6B7280; font-weight:500;">
                            Mostrando <?= count($items) ?> de <?= $total_items ?> registros
                        </span>
                    </div>

                    <div style="overflow-x:auto;">
                        <table class="cxc-table">
                            <thead>
                                <tr>
                                    <th>Comprobante / Ref</th>
                                    <th>Cliente / Razón Social</th>
                                    <th>F. Vencimiento</th>
                                    <th style="text-align:right;">Monto Total</th>
                                    <th style="text-align:right;">Monto Cobrado</th>
                                    <th style="text-align:right;">Saldo Pendiente</th>
                                    <th>Banco</th>
                                    <th>F. Últ. Pago</th>
                                    <th>Estado</th>
                                    <th style="text-align:center;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($items)): ?>
                                    <tr>
                                        <td colspan="10" style="text-align:center; padding:3.5rem; color:#9CA3AF;">
                                            <i class="fas fa-folder-open" style="font-size:2.5rem; margin-bottom:0.8rem; opacity:0.35; display:block;"></i>
                                            No se encontraron cuentas por cobrar con los filtros seleccionados.
                                        </td>
                                    </tr>
                                <?php else: foreach($items as $row): 
                                    $montoTotal = floatval($row['monto_total']);
                                    $montoPagado = floatval($row['monto_pagado']);
                                    $saldo = max(0, $montoTotal - $montoPagado);
                                    $pct = $montoTotal > 0 ? min(100, round(($montoPagado / $montoTotal) * 100)) : 0;
                                    
                                    // Cálculo exacto de estado
                                    $isVencido = (!empty($row['f_venc']) && strtotime($row['f_venc']) < strtotime(date('Y-m-d')) && $saldo > 0.001);
                                    if ($saldo <= 0.001 && $montoTotal > 0) {
                                        $estadoLabel = 'PAGADO';
                                        $estadoClass = 'status-pagado';
                                        $estadoIcon = 'fa-circle-check';
                                    } elseif ($isVencido) {
                                        $estadoLabel = 'VENCIDO';
                                        $estadoClass = 'status-vencido';
                                        $estadoIcon = 'fa-circle-exclamation';
                                    } elseif ($montoPagado > 0) {
                                        $estadoLabel = 'PARCIAL';
                                        $estadoClass = 'status-parcial';
                                        $estadoIcon = 'fa-circle-dot';
                                    } else {
                                        $estadoLabel = 'PENDIENTE';
                                        $estadoClass = 'status-pendiente';
                                        $estadoIcon = 'fa-clock';
                                    }

                                    // Banco badge class
                                    $bancoUpper = strtoupper($row['banco'] ?? 'EFECTIVO');
                                    $bancoClass = 'bank-efectivo';
                                    if (strpos($bancoUpper, 'BCP') !== false) $bancoClass = 'bank-bcp';
                                    elseif (strpos($bancoUpper, 'BBVA') !== false) $bancoClass = 'bank-bbva';
                                    elseif (strpos($bancoUpper, 'INTER') !== false) $bancoClass = 'bank-interbank';
                                    elseif (strpos($bancoUpper, 'SCOTIA') !== false) $bancoClass = 'bank-scotiabank';
                                ?>
                                    <tr>
                                        <td>
                                            <span class="doc-badge"><?= htmlspecialchars($row['ft_lt'] ?: 'S/N') ?></span>
                                            <?php if(!empty($row['referencia'])): ?>
                                                <span class="ref-tag">Ref: <?= htmlspecialchars($row['referencia']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="font-weight:700; color:#111827; font-size:0.88rem;">
                                                <?= htmlspecialchars($row['cliente']) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if (!empty($row['f_venc'])): ?>
                                                <div style="font-weight:600; color:<?= $isVencido ? '#DC2626' : '#4B5563' ?>; font-size:0.82rem; display:flex; align-items:center; gap:4px;">
                                                    <?php if($isVencido): ?><i class="fas fa-exclamation-circle" style="color:#DC2626;"></i><?php endif; ?>
                                                    <?= date('d/m/Y', strtotime($row['f_venc'])) ?>
                                                </div>
                                            <?php else: ?>
                                                <span style="color:#9CA3AF; font-size:0.8rem;">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align:right; font-weight:800; color:#111827;">
                                            <?= formatearMonto($montoTotal) ?>
                                        </td>
                                        <td style="text-align:right;">
                                            <span style="color:#059669; font-weight:700;">
                                                <?= formatearMonto($montoPagado) ?>
                                            </span>
                                            <div class="cxc-progress-mini" style="margin-left:auto;">
                                                <div class="cxc-progress-mini-bar" style="width: <?= $pct ?>%;"></div>
                                            </div>
                                        </td>
                                        <td style="text-align:right;">
                                            <?php if($saldo > 0.001): ?>
                                                <span style="font-weight:800; color:#DC2626; background:rgba(220,38,38,0.06); padding:2px 8px; border-radius:6px;">
                                                    <?= formatearMonto($saldo) ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="font-weight:700; color:#059669;">S/ 0.00</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="bank-badge <?= $bancoClass ?>">
                                                <?= htmlspecialchars($row['banco'] ?: 'EFECTIVO') ?>
                                            </span>
                                        </td>
                                        <td style="color:#4B5563; font-size:0.82rem;">
                                            <?= $row['fecha_pago'] ? date('d/m/Y', strtotime($row['fecha_pago'])) : '<span style="color:#9CA3AF;">—</span>' ?>
                                        </td>
                                        <td>
                                            <span class="status-badge <?= $estadoClass ?>">
                                                <i class="fas <?= $estadoIcon ?>"></i> <?= $estadoLabel ?>
                                            </span>
                                        </td>
                                        <td style="text-align:center;">
                                            <div class="btn-action-group">
                                                <button type="button" class="btn-abono-pill" onclick="abrirPago(<?= htmlspecialchars(json_encode($row)) ?>)" title="Registrar Abono">
                                                    <i class="fas fa-dollar-sign"></i> Abono
                                                </button>
                                                <button type="button" class="btn-icon-soft edit" onclick="abrirEditar(<?= htmlspecialchars(json_encode($row)) ?>)" title="Editar">
                                                    <i class="fas fa-pen-to-square"></i>
                                                </button>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('¿Está seguro de eliminar esta cuenta por cobrar? Esta acción no se puede deshacer.');">
                                                    <input type="hidden" name="action" value="delete_cobro">
                                                    <input type="hidden" name="id" value="<?= $row['id'] ?>">
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

                    <!-- Barra de Paginación -->
                    <?php if($total_pages > 1): 
                        $query_params = $_GET;
                    ?>
                        <div style="padding:1rem 1.4rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; border-top:1px solid #F3F4F6;">
                            <div style="font-size:0.82rem; color:#6B7280;">
                                Mostrando <strong><?= ($offset + 1) ?> - <?= min($offset + $limit, $total_items) ?></strong> de <strong><?= $total_items ?></strong> cuentas registradas
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

    <!-- Modal Nuevo Cobro -->
    <div class="modal-overlay" id="modalNuevoCobro">
        <div class="modal-box">
            <div class="modal-header">
                <h3><i class="fas fa-plus-circle" style="color:#E31E24;"></i> Nueva Cuenta por Cobrar</h3>
                <button type="button" class="btn-icon-soft edit" onclick="cerrarModal('modalNuevoCobro')">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Cliente / Razón Social *</label>
                        <input type="text" name="cliente" class="form-control-custom" required placeholder="Ej: GRUPO GRANDE SAC o Juan Pérez">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Comprobante / Nro Letra</label>
                            <input type="text" name="ft_lt" class="form-control-custom" placeholder="Ej: E001-2973 o F008-455">
                        </div>
                        <div class="form-group">
                            <label>Referencia Interna</label>
                            <input type="text" name="referencia" class="form-control-custom" placeholder="Ej: 138-01-2026">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Monto Total (S/) *</label>
                            <input type="number" step="0.01" name="monto_total" class="form-control-custom" required placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label>Fecha de Vencimiento</label>
                            <input type="date" name="f_venc" class="form-control-custom">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Monto Cobrado Inicial (S/)</label>
                            <input type="number" step="0.01" name="monto_pagado" class="form-control-custom" placeholder="0.00" value="0.00">
                        </div>
                        <div class="form-group">
                            <label>Banco de Destino</label>
                            <select name="banco" class="form-control-custom">
                                <option value="BCP">BCP</option>
                                <option value="BBVA">BBVA</option>
                                <option value="INTERBANK">INTERBANK</option>
                                <option value="SCOTIABANK">SCOTIABANK</option>
                                <option value="EFECTIVO">EFECTIVO</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="cerrarModal('modalNuevoCobro')" style="border-radius:8px;">Cancelar</button>
                    <button type="submit" class="btn btn-primary" style="border-radius:8px; font-weight:600;">
                        <i class="fas fa-floppy-disk" style="margin-right:6px;"></i> Guardar Registro
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Registrar Abono -->
    <div class="modal-overlay" id="modalPago">
        <div class="modal-box">
            <div class="modal-header">
                <h3><i class="fas fa-hand-holding-dollar" style="color:#059669;"></i> Registrar Cobro / Abono</h3>
                <button type="button" class="btn-icon-soft edit" onclick="cerrarModal('modalPago')">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_pago">
                <input type="hidden" name="id" id="pago_id">
                <div class="modal-body">
                    <div style="background:#F8FAFC; padding:0.9rem 1.1rem; border-radius:10px; margin-bottom:1.2rem; border:1px solid #E2E8F0;">
                        <div id="pago_cliente_info" style="font-weight:700; color:#1E293B; font-size:0.92rem;"></div>
                        <div id="pago_saldo_info" style="font-size:0.8rem; color:#64748B; margin-top:3px;"></div>
                    </div>
                    <div class="form-group">
                        <label>Monto Total Cobrado / Acumulado (S/) *</label>
                        <input type="number" step="0.01" name="monto_pagado" id="pago_monto" class="form-control-custom" required>
                        <small style="color:#6B7280; font-size:0.75rem; margin-top:3px; display:block;">
                            Ingresa el monto total pagado a la fecha. Si cancela todo, el estado pasará a <strong>PAGADO</strong> automáticamente.
                        </small>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Banco / Medio de Cobro</label>
                            <select name="banco" id="pago_banco" class="form-control-custom">
                                <option value="BCP">BCP</option>
                                <option value="BBVA">BBVA</option>
                                <option value="INTERBANK">INTERBANK</option>
                                <option value="SCOTIABANK">SCOTIABANK</option>
                                <option value="EFECTIVO">EFECTIVO</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Fecha de Pago</label>
                            <input type="date" name="fecha_pago" id="pago_fecha" class="form-control-custom" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="cerrarModal('modalPago')" style="border-radius:8px;">Cancelar</button>
                    <button type="submit" class="btn btn-primary" style="background:#059669; border-color:#059669; border-radius:8px; font-weight:600;">
                        <i class="fas fa-check" style="margin-right:6px;"></i> Guardar Pago
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Editar Cobro -->
    <div class="modal-overlay" id="modalEditarCobro">
        <div class="modal-box">
            <div class="modal-header">
                <h3><i class="fas fa-pen-to-square" style="color:#2563EB;"></i> Editar Cuenta por Cobrar</h3>
                <button type="button" class="btn-icon-soft edit" onclick="cerrarModal('modalEditarCobro')">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit_cobro">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Cliente / Razón Social *</label>
                        <input type="text" name="cliente" id="edit_cliente" class="form-control-custom" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Comprobante / Nro Letra</label>
                            <input type="text" name="ft_lt" id="edit_ft_lt" class="form-control-custom">
                        </div>
                        <div class="form-group">
                            <label>Referencia</label>
                            <input type="text" name="referencia" id="edit_referencia" class="form-control-custom">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Monto Total (S/) *</label>
                            <input type="number" step="0.01" name="monto_total" id="edit_monto_total" class="form-control-custom" required>
                        </div>
                        <div class="form-group">
                            <label>Fecha Vencimiento</label>
                            <input type="date" name="f_venc" id="edit_f_venc" class="form-control-custom">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Monto Cobrado (S/)</label>
                            <input type="number" step="0.01" name="monto_pagado" id="edit_monto_pagado" class="form-control-custom">
                        </div>
                        <div class="form-group">
                            <label>Fecha de Pago</label>
                            <input type="date" name="fecha_pago" id="edit_fecha_pago" class="form-control-custom">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Banco de Destino</label>
                        <select name="banco" id="edit_banco" class="form-control-custom">
                            <option value="BCP">BCP</option>
                            <option value="BBVA">BBVA</option>
                            <option value="INTERBANK">INTERBANK</option>
                            <option value="SCOTIABANK">SCOTIABANK</option>
                            <option value="EFECTIVO">EFECTIVO</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="cerrarModal('modalEditarCobro')" style="border-radius:8px;">Cancelar</button>
                    <button type="submit" class="btn btn-primary" style="border-radius:8px; font-weight:600;">
                        <i class="fas fa-floppy-disk" style="margin-right:6px;"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function abrirModal(id) { document.getElementById(id).classList.add('open'); }
    function cerrarModal(id) { document.getElementById(id).classList.remove('open'); }
    
    function abrirPago(data) {
        document.getElementById('pago_id').value = data.id;
        document.getElementById('pago_cliente_info').innerText = data.cliente + ' — ' + (data.ft_lt || 'Sin Comprobante');
        const saldo = (parseFloat(data.monto_total || 0) - parseFloat(data.monto_pagado || 0)).toFixed(2);
        document.getElementById('pago_saldo_info').innerText = 'Monto Total: S/ ' + parseFloat(data.monto_total || 0).toLocaleString('es-PE', {minimumFractionDigits:2}) + ' | Saldo Pendiente: S/ ' + parseFloat(saldo).toLocaleString('es-PE', {minimumFractionDigits:2});
        document.getElementById('pago_monto').value = data.monto_pagado || data.monto_total;
        if(data.banco) document.getElementById('pago_banco').value = data.banco;
        abrirModal('modalPago');
    }
    
    function abrirEditar(data) {
        document.getElementById('edit_id').value = data.id;
        document.getElementById('edit_cliente').value = data.cliente || '';
        document.getElementById('edit_ft_lt').value = data.ft_lt || '';
        document.getElementById('edit_referencia').value = data.referencia || '';
        document.getElementById('edit_monto_total').value = data.monto_total || 0;
        document.getElementById('edit_f_venc').value = data.f_venc || '';
        document.getElementById('edit_monto_pagado').value = data.monto_pagado || 0;
        document.getElementById('edit_fecha_pago').value = data.fecha_pago || '';
        document.getElementById('edit_banco').value = data.banco || 'EFECTIVO';
        abrirModal('modalEditarCobro');
    }

    // Auto-dismiss alert
    document.addEventListener('DOMContentLoaded', function() {
        const toast = document.getElementById('cxcToast');
        if (toast) {
            setTimeout(function() {
                toast.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(-8px)';
                setTimeout(function() { if(toast.parentNode) toast.remove(); }, 600);
            }, 3500);

            const url = new URL(window.location);
            if (url.searchParams.has('msg')) {
                url.searchParams.delete('msg');
                window.history.replaceState({}, document.title, url.pathname + (url.searchParams.toString() ? '?' + url.searchParams.toString() : ''));
            }
        }
    });
    </script>
</body>
</html>
