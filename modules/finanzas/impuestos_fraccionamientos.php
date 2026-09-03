<?php
require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../config/db.php';

$page_title = 'Impuestos (SUNAT / SAT)';
$page_subtitle = 'Liquidación de tributos SUNAT, arbitrios e infracciones SAT';

// Procesamiento de formularios POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_sunat') {
        $cod = trim($_POST['cod'] ?? '');
        $tributo = trim($_POST['tributo'] ?? '');
        $periodo = trim($_POST['periodo'] ?? '');
        $importe = floatval($_POST['importe'] ?? 0);
        $estado = $_POST['estado'] ?? 'PENDIENTE';
        $f_pago = !empty($_POST['f_pago']) ? $_POST['f_pago'] : null;
        $obs = trim($_POST['observacion'] ?? '');
        
        $stmt = $db->prepare("INSERT INTO finanzas_sunat (cod, tributo, periodo, importe, estado, f_pago, observacion) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$cod, $tributo, $periodo, $importe, $estado, $f_pago, $obs]);
        header("Location: impuestos_fraccionamientos.php?tab=sunat&msg=creado");
        exit;
    }
    
    if ($action === 'create_sat') {
        $f_emision = !empty($_POST['f_emision']) ? $_POST['f_emision'] : null;
        $tipo_inf = trim($_POST['tipo_infraccion'] ?? '');
        $nro_doc = trim($_POST['nro_documento'] ?? '');
        $por_pagar = floatval($_POST['por_pagar'] ?? 0);
        $estado = $_POST['estado'] ?? 'PENDIENTE';
        $f_pago = !empty($_POST['f_pago']) ? $_POST['f_pago'] : null;
        $obs = trim($_POST['observacion'] ?? '');
        
        $stmt = $db->prepare("INSERT INTO finanzas_sat (f_emision, tipo_infraccion, nro_documento, por_pagar, estado, f_pago, observacion) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$f_emision, $tipo_inf, $nro_doc, $por_pagar, $estado, $f_pago, $obs]);
        header("Location: impuestos_fraccionamientos.php?tab=sat&msg=creado");
        exit;
    }

    if ($action === 'update_sunat_estado') {
        $id = intval($_POST['id']);
        $estado = $_POST['estado'] ?? 'PAGADO';
        $f_pago = !empty($_POST['f_pago']) ? $_POST['f_pago'] : date('Y-m-d');
        $stmt = $db->prepare("UPDATE finanzas_sunat SET estado = ?, f_pago = ? WHERE id = ?");
        $stmt->execute([$estado, $f_pago, $id]);
        header("Location: impuestos_fraccionamientos.php?tab=sunat&msg=actualizado");
        exit;
    }

    if ($action === 'update_sat_estado') {
        $id = intval($_POST['id']);
        $estado = $_POST['estado'] ?? 'PAGADO';
        $f_pago = !empty($_POST['f_pago']) ? $_POST['f_pago'] : date('Y-m-d');
        $stmt = $db->prepare("UPDATE finanzas_sat SET estado = ?, f_pago = ? WHERE id = ?");
        $stmt->execute([$estado, $f_pago, $id]);
        header("Location: impuestos_fraccionamientos.php?tab=sat&msg=actualizado");
        exit;
    }

    if ($action === 'delete_sunat') {
        $id = intval($_POST['id']);
        $stmt = $db->prepare("DELETE FROM finanzas_sunat WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: impuestos_fraccionamientos.php?tab=sunat&msg=eliminado");
        exit;
    }

    if ($action === 'delete_sat') {
        $id = intval($_POST['id']);
        $stmt = $db->prepare("DELETE FROM finanzas_sat WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: impuestos_fraccionamientos.php?tab=sat&msg=eliminado");
        exit;
    }
}

$tab = $_GET['tab'] ?? 'sunat';
$search = trim($_GET['search'] ?? '');
$filtro_estado = $_GET['estado'] ?? '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

// Totales Generales
$total_sunat = floatval($db->query("SELECT COALESCE(SUM(importe), 0) FROM finanzas_sunat WHERE estado NOT IN ('CANCELADO', 'PAGADO')")->fetchColumn());
$total_sat = floatval($db->query("SELECT COALESCE(SUM(por_pagar), 0) FROM finanzas_sat WHERE estado NOT IN ('CANCELADO', 'PAGADO')")->fetchColumn());
$total_sunat_reg = intval($db->query("SELECT COUNT(*) FROM finanzas_sunat")->fetchColumn());
$total_sat_reg = intval($db->query("SELECT COUNT(*) FROM finanzas_sat")->fetchColumn());

// Consultas Paginadas según Tab
$params = [];
if ($tab === 'sunat') {
    $sql_base = "FROM finanzas_sunat WHERE 1=1";
    if (!empty($search)) {
        $sql_base .= " AND (tributo ILIKE ? OR cod ILIKE ? OR periodo ILIKE ? OR observacion ILIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    if (!empty($filtro_estado)) {
        if ($filtro_estado === 'PAGADO') $sql_base .= " AND (estado = 'CANCELADO' OR estado = 'PAGADO')";
        else $sql_base .= " AND estado != 'CANCELADO' AND estado != 'PAGADO'";
    }
    
    $stmt_cnt = $db->prepare("SELECT COUNT(*) " . $sql_base);
    $stmt_cnt->execute($params);
    $total_items = $stmt_cnt->fetchColumn();
    $total_pages = ceil($total_items / $limit);
    
    $stmt_data = $db->prepare("SELECT * " . $sql_base . " ORDER BY (estado NOT IN ('CANCELADO', 'PAGADO')) DESC, id DESC LIMIT $limit OFFSET $offset");
    $stmt_data->execute($params);
    $sunat_items = $stmt_data->fetchAll(PDO::FETCH_ASSOC);
    $sat_items = [];
} else {
    $sql_base = "FROM finanzas_sat WHERE 1=1";
    if (!empty($search)) {
        $sql_base .= " AND (tipo_infraccion ILIKE ? OR nro_documento ILIKE ? OR observacion ILIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    if (!empty($filtro_estado)) {
        if ($filtro_estado === 'PAGADO') $sql_base .= " AND (estado = 'CANCELADO' OR estado = 'PAGADO')";
        else $sql_base .= " AND estado != 'CANCELADO' AND estado != 'PAGADO'";
    }
    
    $stmt_cnt = $db->prepare("SELECT COUNT(*) " . $sql_base);
    $stmt_cnt->execute($params);
    $total_items = $stmt_cnt->fetchColumn();
    $total_pages = ceil($total_items / $limit);
    
    $stmt_data = $db->prepare("SELECT * " . $sql_base . " ORDER BY (estado NOT IN ('CANCELADO', 'PAGADO')) DESC, f_emision DESC NULLS LAST, id DESC LIMIT $limit OFFSET $offset");
    $stmt_data->execute($params);
    $sat_items = $stmt_data->fetchAll(PDO::FETCH_ASSOC);
    $sunat_items = [];
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
        /* ===== ESTILOS PREMIUM IMPUESTOS ===== */
        .tax-hero {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.4rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .tax-hero-title h1 {
            font-size: 1.45rem;
            font-weight: 800;
            color: #111827;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .tax-hero-title p {
            color: #6B7280;
            font-size: 0.84rem;
            margin: 0.25rem 0 0 0;
        }

        /* Nav Tabs */
        .tax-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.25rem;
        }
        .tax-tab-link {
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
        .tax-tab-link:hover {
            border-color: #D1D5DB;
            background: #F9FAFB;
        }
        .tax-tab-link.active {
            background: #111827;
            color: #FFFFFF;
            border-color: #111827;
            box-shadow: 0 4px 12px rgba(17,24,39,0.15);
        }

        /* KPIs */
        .tax-kpis-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 1.2rem;
            margin-bottom: 1.3rem;
        }
        .tax-kpi-card {
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
        .tax-kpi-card:hover {
            transform: translateY(-2px);
        }
        .tax-kpi-icon {
            width: 50px;
            height: 50px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
        }
        .icon-amber-gradient { background: linear-gradient(135deg, rgba(217,119,6,0.12) 0%, rgba(245,158,11,0.2) 100%); color: #D97706; }
        .icon-rose-gradient { background: linear-gradient(135deg, rgba(220,38,38,0.12) 0%, rgba(239,68,68,0.2) 100%); color: #DC2626; }
        .icon-indigo-gradient { background: linear-gradient(135deg, rgba(79,70,229,0.12) 0%, rgba(99,102,241,0.2) 100%); color: #4F46E5; }

        .tax-kpi-info span.label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
            color: #6B7280;
            display: block;
            margin-bottom: 0.15rem;
        }
        .tax-kpi-info h3 {
            font-size: 1.35rem;
            font-weight: 800;
            color: #111827;
            margin: 0;
            line-height: 1.2;
        }
        .tax-kpi-info span.sub-tag {
            font-size: 0.74rem;
            font-weight: 600;
            display: inline-block;
            margin-top: 0.3rem;
            padding: 2px 8px;
            border-radius: 20px;
        }

        /* Filter Panel */
        .tax-filter-card {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 14px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.25rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        .tax-filter-form {
            display: flex;
            gap: 0.85rem;
            flex-wrap: wrap;
            align-items: center;
        }
        .tax-search-box {
            flex: 2;
            min-width: 250px;
            position: relative;
        }
        .tax-search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #9CA3AF;
            font-size: 0.9rem;
        }
        .tax-search-box input {
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
        .tax-search-box input:focus {
            background: #FFFFFF;
            border-color: #E31E24;
            box-shadow: 0 0 0 3px rgba(227,30,36,0.1);
        }
        .tax-select {
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
        .tax-table-card {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        }
        .tax-table-header-title {
            padding: 1rem 1.4rem;
            border-bottom: 1px solid #F3F4F6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .tax-table-header-title h3 {
            font-size: 0.95rem;
            font-weight: 700;
            color: #111827;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .tax-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        .tax-table th {
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
        .tax-table td {
            padding: 0.9rem 1rem;
            border-bottom: 1px solid #F3F4F6;
            font-size: 0.85rem;
            color: #374151;
            vertical-align: middle;
        }
        .tax-table tbody tr:hover {
            background: #F9FAFB;
        }

        /* Badges */
        .badge-cod {
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
            padding: 4px 8px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.15s;
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
        .tax-toast {
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
                <div class="tax-hero">
                    <div class="tax-hero-title">
                        <h1><i class="fas fa-landmark" style="color:#E31E24;"></i> Gestión Tributaria (SUNAT / SAT)</h1>
                        <p>Liquidación mensual de tributos, cronograma fiscal e infracciones</p>
                    </div>
                    <div>
                        <?php if($tab === 'sunat'): ?>
                            <button class="btn btn-primary" onclick="abrirModal('modalNuevoSunat')" style="font-weight:600; padding:0.55rem 1.1rem; border-radius:10px; box-shadow:0 4px 12px rgba(227,30,36,0.25);">
                                <i class="fas fa-plus" style="margin-right:6px;"></i> Registrar Liquidación SUNAT
                            </button>
                        <?php else: ?>
                            <button class="btn btn-primary" onclick="abrirModal('modalNuevoSat')" style="font-weight:600; padding:0.55rem 1.1rem; border-radius:10px; box-shadow:0 4px 12px rgba(227,30,36,0.25);">
                                <i class="fas fa-plus" style="margin-right:6px;"></i> Registrar Infracción SAT
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Toast Alerts -->
                <?php if (isset($_GET['msg'])): ?>
                    <div id="taxToast" class="tax-toast">
                        <div>
                            <i class="fas fa-check-circle" style="margin-right:8px;"></i>
                            <?php 
                                if ($_GET['msg'] === 'creado') echo "Registro tributario guardado correctamente.";
                                elseif ($_GET['msg'] === 'actualizado') echo "Estado de pago actualizado con éxito.";
                                elseif ($_GET['msg'] === 'eliminado') echo "Registro eliminado del sistema.";
                            ?>
                        </div>
                        <button type="button" onclick="this.parentElement.remove()" style="background:none; border:none; color:#FFFFFF; font-size:1.2rem; cursor:pointer;">&times;</button>
                    </div>
                <?php endif; ?>

                <!-- KPI Cards -->
                <div class="tax-kpis-grid">
                    <div class="tax-kpi-card">
                        <div class="tax-kpi-icon icon-amber-gradient">
                            <i class="fas fa-landmark"></i>
                        </div>
                        <div class="tax-kpi-info">
                            <span class="label">Pendiente SUNAT</span>
                            <h3 style="color:#D97706;"><?= formatearMonto($total_sunat) ?></h3>
                            <span class="sub-tag" style="background:#FFFBEB; color:#D97706;">
                                <i class="fas fa-file-invoice"></i> <?= $total_sunat_reg ?> Liquidaciones
                            </span>
                        </div>
                    </div>

                    <div class="tax-kpi-card">
                        <div class="tax-kpi-icon icon-rose-gradient">
                            <i class="fas fa-shield-halved"></i>
                        </div>
                        <div class="tax-kpi-info">
                            <span class="label">Pendiente SAT / Mun.</span>
                            <h3 style="color:#DC2626;"><?= formatearMonto($total_sat) ?></h3>
                            <span class="sub-tag" style="background:#FEF2F2; color:#DC2626;">
                                <i class="fas fa-triangle-exclamation"></i> <?= $total_sat_reg ?> Papeletas / Multas
                            </span>
                        </div>
                    </div>

                    <div class="tax-kpi-card">
                        <div class="tax-kpi-icon icon-indigo-gradient">
                            <i class="fas fa-layer-group"></i>
                        </div>
                        <div class="tax-kpi-info">
                            <span class="label">Total en Consulta</span>
                            <h3><?= $total_items ?></h3>
                            <span class="sub-tag" style="background:#EEF2FF; color:#4F46E5;">
                                Pestaña <?= strtoupper($tab) ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Tabs de Navegación -->
                <div class="tax-tabs">
                    <a href="?tab=sunat" class="tax-tab-link <?= $tab==='sunat'?'active':'' ?>">
                        <i class="fas fa-landmark"></i> 1. SUNAT (Liquidación de Tributos)
                    </a>
                    <a href="?tab=sat" class="tax-tab-link <?= $tab==='sat'?'active':'' ?>">
                        <i class="fas fa-shield-halved"></i> 2. SAT (Infracciones y Papeletas)
                    </a>
                </div>

                <!-- Filtros -->
                <div class="tax-filter-card">
                    <form method="GET" class="tax-filter-form">
                        <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
                        <div class="tax-search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" name="search" placeholder="<?= $tab==='sunat' ? 'Buscar tributo, código o periodo fiscal...' : 'Buscar tipo de infracción o N° documento...' ?>" value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <select name="estado" class="tax-select" onchange="this.form.submit()">
                            <option value="">Todos los Estados</option>
                            <option value="PENDIENTE" <?= $filtro_estado==='PENDIENTE'?'selected':'' ?>>⏳ Pendientes de Pago</option>
                            <option value="PAGADO" <?= $filtro_estado==='PAGADO'?'selected':'' ?>>✅ Pagados / Cancelados</option>
                        </select>
                        <button type="submit" class="btn btn-primary" style="padding:0.58rem 1.1rem; border-radius:10px; font-weight:600;">
                            <i class="fas fa-filter"></i> Filtrar
                        </button>
                        <?php if(!empty($search) || !empty($filtro_estado)): ?>
                            <a href="impuestos_fraccionamientos.php?tab=<?= $tab ?>" class="btn btn-outline" style="padding:0.58rem 0.9rem; border-radius:10px;" title="Limpiar Filtros">
                                <i class="fas fa-times"></i>
                            </a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Tabla de Datos según Tab -->
                <?php if($tab === 'sunat'): ?>
                    <div class="tax-table-card">
                        <div class="tax-table-header-title">
                            <h3><i class="fas fa-landmark" style="color:#D97706;"></i> Liquidaciones Tributarias SUNAT</h3>
                            <span style="font-size:0.8rem; color:#6B7280; font-weight:500;">
                                Mostrando <?= count($sunat_items) ?> de <?= $total_items ?> registros
                            </span>
                        </div>

                        <div style="overflow-x:auto;">
                            <table class="tax-table">
                                <thead>
                                    <tr>
                                        <th>Código</th>
                                        <th>Tributo / Concepto</th>
                                        <th>Periodo Fiscal</th>
                                        <th style="text-align:right;">Importe (S/)</th>
                                        <th>Estado</th>
                                        <th>F. Pago</th>
                                        <th>Observación</th>
                                        <th style="text-align:center;">Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($sunat_items)): ?>
                                        <tr>
                                            <td colspan="8" style="text-align:center; padding:3.5rem; color:#9CA3AF;">
                                                <i class="fas fa-folder-open" style="font-size:2.5rem; margin-bottom:0.8rem; opacity:0.35; display:block;"></i>
                                                No se encontraron liquidaciones SUNAT con los filtros seleccionados.
                                            </td>
                                        </tr>
                                    <?php else: foreach($sunat_items as $r): 
                                        $isCancelado = in_array($r['estado'], ['CANCELADO', 'PAGADO']);
                                    ?>
                                        <tr>
                                            <td><span class="badge-cod"><?= htmlspecialchars($r['cod'] ?: 'N/A') ?></span></td>
                                            <td><strong style="color:#111827;"><?= htmlspecialchars($r['tributo']) ?></strong></td>
                                            <td><span style="font-weight:600; color:#4B5563;"><?= htmlspecialchars($r['periodo'] ?: '—') ?></span></td>
                                            <td style="text-align:right; font-weight:800; color:<?= $isCancelado ? '#059669' : '#DC2626' ?>;">
                                                <?= formatearMonto($r['importe']) ?>
                                            </td>
                                            <td>
                                                <?php if($isCancelado): ?>
                                                    <span class="status-pill pagado"><i class="fas fa-circle-check"></i> CANCELADO</span>
                                                <?php else: ?>
                                                    <span class="status-pill pendiente"><i class="fas fa-clock"></i> PENDIENTE</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="color:#4B5563; font-size:0.82rem;">
                                                <?= $r['f_pago'] ? date('d/m/Y', strtotime($r['f_pago'])) : '<span style="color:#9CA3AF;">—</span>' ?>
                                            </td>
                                            <td style="color:#6B7280; font-size:0.82rem;">
                                                <?= htmlspecialchars($r['observacion'] ?: '—') ?>
                                            </td>
                                            <td style="text-align:center;">
                                                <div class="btn-action-group">
                                                    <?php if(!$isCancelado): ?>
                                                        <form method="POST" style="display:inline;">
                                                            <input type="hidden" name="action" value="update_sunat_estado">
                                                            <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                                            <input type="hidden" name="estado" value="CANCELADO">
                                                            <button type="submit" class="btn-pay-pill" title="Marcar como Pagado">
                                                                <i class="fas fa-check"></i> Pagar
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar esta liquidación SUNAT?');">
                                                        <input type="hidden" name="action" value="delete_sunat">
                                                        <input type="hidden" name="id" value="<?= $r['id'] ?>">
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
                <?php else: ?>
                    <div class="tax-table-card">
                        <div class="tax-table-header-title">
                            <h3><i class="fas fa-shield-halved" style="color:#DC2626;"></i> Infracciones y Arbitrios SAT</h3>
                            <span style="font-size:0.8rem; color:#6B7280; font-weight:500;">
                                Mostrando <?= count($sat_items) ?> de <?= $total_items ?> registros
                            </span>
                        </div>

                        <div style="overflow-x:auto;">
                            <table class="tax-table">
                                <thead>
                                    <tr>
                                        <th>F. Emisión</th>
                                        <th>Tipo Infracción / Arbitrio</th>
                                        <th>N° Documento</th>
                                        <th style="text-align:right;">Por Pagar (S/)</th>
                                        <th>Estado</th>
                                        <th>F. Pago</th>
                                        <th>Observación</th>
                                        <th style="text-align:center;">Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($sat_items)): ?>
                                        <tr>
                                            <td colspan="8" style="text-align:center; padding:3.5rem; color:#9CA3AF;">
                                                <i class="fas fa-folder-open" style="font-size:2.5rem; margin-bottom:0.8rem; opacity:0.35; display:block;"></i>
                                                No se encontraron registros SAT con los filtros seleccionados.
                                            </td>
                                        </tr>
                                    <?php else: foreach($sat_items as $r): 
                                        $isCancelado = in_array($r['estado'], ['CANCELADO', 'PAGADO']);
                                    ?>
                                        <tr>
                                            <td><?= $r['f_emision'] ? date('d/m/Y', strtotime($r['f_emision'])) : '<span style="color:#9CA3AF;">—</span>' ?></td>
                                            <td><strong style="color:#111827;"><?= htmlspecialchars($r['tipo_infraccion']) ?></strong></td>
                                            <td><span class="badge-cod"><?= htmlspecialchars($r['nro_documento'] ?: '—') ?></span></td>
                                            <td style="text-align:right; font-weight:800; color:<?= $isCancelado ? '#059669' : '#DC2626' ?>;">
                                                <?= formatearMonto($r['por_pagar']) ?>
                                            </td>
                                            <td>
                                                <?php if($isCancelado): ?>
                                                    <span class="status-pill pagado"><i class="fas fa-circle-check"></i> CANCELADO</span>
                                                <?php else: ?>
                                                    <span class="status-pill pendiente"><i class="fas fa-clock"></i> PENDIENTE</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="color:#4B5563; font-size:0.82rem;">
                                                <?= $r['f_pago'] ? date('d/m/Y', strtotime($r['f_pago'])) : '<span style="color:#9CA3AF;">—</span>' ?>
                                            </td>
                                            <td style="color:#6B7280; font-size:0.82rem;">
                                                <?= htmlspecialchars($r['observacion'] ?: '—') ?>
                                            </td>
                                            <td style="text-align:center;">
                                                <div class="btn-action-group">
                                                    <?php if(!$isCancelado): ?>
                                                        <form method="POST" style="display:inline;">
                                                            <input type="hidden" name="action" value="update_sat_estado">
                                                            <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                                            <input type="hidden" name="estado" value="CANCELADO">
                                                            <button type="submit" class="btn-pay-pill" title="Marcar como Pagado">
                                                                <i class="fas fa-check"></i> Pagar
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar esta infracción SAT?');">
                                                        <input type="hidden" name="action" value="delete_sat">
                                                        <input type="hidden" name="id" value="<?= $r['id'] ?>">
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
                <?php endif; ?>

            </div>
        </div>
    </div>

    <!-- Modal Nuevo SUNAT -->
    <div class="modal-overlay" id="modalNuevoSunat">
        <div class="modal-box">
            <div class="modal-header">
                <h3><i class="fas fa-landmark" style="color:#D97706;"></i> Registrar Liquidación SUNAT</h3>
                <button type="button" class="btn-icon-soft" onclick="cerrarModal('modalNuevoSunat')">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create_sunat">
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Código Tributo *</label>
                            <input type="text" name="cod" class="form-control-custom" required placeholder="Ej: 1011, 3031, 5210">
                        </div>
                        <div class="form-group">
                            <label>Periodo Fiscal *</label>
                            <input type="text" name="periodo" class="form-control-custom" required placeholder="Ej: 2026-08">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Nombre del Tributo / Concepto *</label>
                        <input type="text" name="tributo" class="form-control-custom" required placeholder="Ej: IGV Cuenta Propia, Renta 3ra Cat., Essalud">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Importe a Pagar (S/) *</label>
                            <input type="number" step="0.01" name="importe" class="form-control-custom" required placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label>Estado Inicial</label>
                            <select name="estado" class="form-control-custom">
                                <option value="PENDIENTE">PENDIENTE</option>
                                <option value="CANCELADO">CANCELADO / PAGADO</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Observación / Nro Orden de Pago</label>
                        <input type="text" name="observacion" class="form-control-custom" placeholder="Ej: Fraccionamiento Nro 012-99">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="cerrarModal('modalNuevoSunat')">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Guardar Liquidación</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Nuevo SAT -->
    <div class="modal-overlay" id="modalNuevoSat">
        <div class="modal-box">
            <div class="modal-header">
                <h3><i class="fas fa-shield-halved" style="color:#DC2626;"></i> Registrar Infracción / Papeleta SAT</h3>
                <button type="button" class="btn-icon-soft" onclick="cerrarModal('modalNuevoSat')">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create_sat">
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Tipo de Infracción *</label>
                            <input type="text" name="tipo_infraccion" class="form-control-custom" required placeholder="Ej: M01 - Tránsito, Arbitrios 2026">
                        </div>
                        <div class="form-group">
                            <label>N° Documento / Placa</label>
                            <input type="text" name="nro_documento" class="form-control-custom" placeholder="Ej: PIT-002194 / Placa B7X-912">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Monto por Pagar (S/) *</label>
                            <input type="number" step="0.01" name="por_pagar" class="form-control-custom" required placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label>Fecha de Emisión</label>
                            <input type="date" name="f_emision" class="form-control-custom" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Observación</label>
                        <input type="text" name="observacion" class="form-control-custom" placeholder="Detalles de la falta o descargo">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="cerrarModal('modalNuevoSat')">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Guardar Infracción</button>
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
