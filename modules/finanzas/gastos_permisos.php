<?php
require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../config/db.php';

$page_title = 'Gastos Fijos y Licencias ITSE';
$page_subtitle = 'Control de servicios básicos, arbitrios y vigencia de Defensa Civil';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_permiso') {
        $titulo = trim($_POST['titulo'] ?? '');
        $tienda = trim($_POST['tienda'] ?? '');
        $direccion = trim($_POST['direccion_tienda'] ?? '');
        $f_servicio = !empty($_POST['f_servicio']) ? $_POST['f_servicio'] : null;
        $f_venc = !empty($_POST['f_venc']) ? $_POST['f_venc'] : null;
        $obs = trim($_POST['observacion'] ?? '');
        
        $stmt = $db->prepare("INSERT INTO finanzas_permisos (titulo, tienda, direccion_tienda, f_servicio, f_venc, observacion) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$titulo, $tienda, $direccion, $f_servicio, $f_venc, $obs]);
        header("Location: gastos_permisos.php?tab=permisos&msg=creado");
        exit;
    }
    
    if ($action === 'create_gasto') {
        $categoria = trim($_POST['categoria'] ?? 'SERVICIO');
        $tienda = trim($_POST['tienda'] ?? '');
        $proveedor = trim($_POST['proveedor_servicio'] ?? '');
        $monto = floatval($_POST['monto'] ?? 0);
        $f_venc = !empty($_POST['f_venc']) ? $_POST['f_venc'] : null;
        $obs = trim($_POST['observacion'] ?? '');
        
        $stmt = $db->prepare("INSERT INTO finanzas_gastos_fijos (categoria, tienda, proveedor_servicio, monto, f_venc, observacion) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$categoria, $tienda, $proveedor, $monto, $f_venc, $obs]);
        header("Location: gastos_permisos.php?tab=gastos&msg=creado");
        exit;
    }

    if ($action === 'update_gasto_estado') {
        $id = intval($_POST['id']);
        $estado = $_POST['estado'] ?? 'PAGADO';
        $stmt = $db->prepare("UPDATE finanzas_gastos_fijos SET estado = ? WHERE id = ?");
        $stmt->execute([$estado, $id]);
        header("Location: gastos_permisos.php?tab=gastos&msg=actualizado");
        exit;
    }

    if ($action === 'delete_permiso') {
        $id = intval($_POST['id']);
        $stmt = $db->prepare("DELETE FROM finanzas_permisos WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: gastos_permisos.php?tab=permisos&msg=eliminado");
        exit;
    }

    if ($action === 'delete_gasto') {
        $id = intval($_POST['id']);
        $stmt = $db->prepare("DELETE FROM finanzas_gastos_fijos WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: gastos_permisos.php?tab=gastos&msg=eliminado");
        exit;
    }
}

$tab = $_GET['tab'] ?? 'permisos';
$search = trim($_GET['search'] ?? '');
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

// Totales de Certificados
$permisos_raw = $db->query("SELECT *, (f_venc - CURRENT_DATE) as dias_restantes FROM finanzas_permisos")->fetchAll(PDO::FETCH_ASSOC);
$totalVigentes = 0; $totalPorVencer = 0; $totalVencidos = 0;
foreach($permisos_raw as $p) {
    $d = intval($p['dias_restantes'] ?? 0);
    if ($d < 0) $totalVencidos++;
    else if ($d <= 30) $totalPorVencer++;
    else $totalVigentes++;
}

$totalGastosFijos = floatval($db->query("SELECT COALESCE(SUM(monto), 0) FROM finanzas_gastos_fijos WHERE estado != 'PAGADO'")->fetchColumn());
$totalGastosFijosReg = intval($db->query("SELECT COUNT(*) FROM finanzas_gastos_fijos WHERE estado != 'PAGADO'")->fetchColumn());

// Consultas Paginadas según Tab
$params = [];
if ($tab === 'permisos') {
    $sql_base = "FROM finanzas_permisos WHERE 1=1";
    if (!empty($search)) {
        $sql_base .= " AND (titulo ILIKE ? OR tienda ILIKE ? OR direccion_tienda ILIKE ? OR observacion ILIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $stmt_cnt = $db->prepare("SELECT COUNT(*) " . $sql_base);
    $stmt_cnt->execute($params);
    $total_items = $stmt_cnt->fetchColumn();
    $total_pages = ceil($total_items / $limit);
    
    $stmt_data = $db->prepare("SELECT *, (f_venc - CURRENT_DATE) as dias_restantes " . $sql_base . " ORDER BY f_venc ASC NULLS LAST LIMIT $limit OFFSET $offset");
    $stmt_data->execute($params);
    $permisos = $stmt_data->fetchAll(PDO::FETCH_ASSOC);
    $gastos = [];
} else {
    $sql_base = "FROM finanzas_gastos_fijos WHERE 1=1";
    if (!empty($search)) {
        $sql_base .= " AND (categoria ILIKE ? OR tienda ILIKE ? OR proveedor_servicio ILIKE ? OR observacion ILIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $stmt_cnt = $db->prepare("SELECT COUNT(*) " . $sql_base);
    $stmt_cnt->execute($params);
    $total_items = $stmt_cnt->fetchColumn();
    $total_pages = ceil($total_items / $limit);
    
    $stmt_data = $db->prepare("SELECT * " . $sql_base . " ORDER BY (estado != 'PAGADO') DESC, f_venc ASC NULLS LAST, id DESC LIMIT $limit OFFSET $offset");
    $stmt_data->execute($params);
    $gastos = $stmt_data->fetchAll(PDO::FETCH_ASSOC);
    $permisos = [];
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
        /* ===== ESTILOS GASTOS Y LICENCIAS ===== */
        .gp-hero {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.4rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .gp-hero-title h1 {
            font-size: 1.45rem;
            font-weight: 800;
            color: #111827;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .gp-hero-title p {
            color: #6B7280;
            font-size: 0.84rem;
            margin: 0.25rem 0 0 0;
        }

        /* Nav Tabs */
        .gp-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.25rem;
        }
        .gp-tab-link {
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
        .gp-tab-link:hover {
            border-color: #D1D5DB;
            background: #F9FAFB;
        }
        .gp-tab-link.active {
            background: #111827;
            color: #FFFFFF;
            border-color: #111827;
            box-shadow: 0 4px 12px rgba(17,24,39,0.15);
        }

        /* KPI Cards Grid */
        .gp-kpis-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.2rem;
            margin-bottom: 1.3rem;
        }
        .gp-kpi-card {
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
        .gp-kpi-card:hover {
            transform: translateY(-2px);
        }
        .gp-kpi-icon {
            width: 50px;
            height: 50px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
        }
        .icon-emerald-bg { background: linear-gradient(135deg, rgba(5,150,105,0.12) 0%, rgba(16,185,129,0.2) 100%); color: #059669; }
        .icon-amber-bg { background: linear-gradient(135deg, rgba(217,119,6,0.12) 0%, rgba(245,158,11,0.2) 100%); color: #D97706; }
        .icon-rose-bg { background: linear-gradient(135deg, rgba(220,38,38,0.12) 0%, rgba(239,68,68,0.2) 100%); color: #DC2626; }
        .icon-indigo-bg { background: linear-gradient(135deg, rgba(79,70,229,0.12) 0%, rgba(99,102,241,0.2) 100%); color: #4F46E5; }

        .gp-kpi-info span.label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
            color: #6B7280;
            display: block;
            margin-bottom: 0.15rem;
        }
        .gp-kpi-info h3 {
            font-size: 1.35rem;
            font-weight: 800;
            color: #111827;
            margin: 0;
            line-height: 1.2;
        }
        .gp-kpi-info span.sub-tag {
            font-size: 0.74rem;
            font-weight: 600;
            display: inline-block;
            margin-top: 0.3rem;
            padding: 2px 8px;
            border-radius: 20px;
        }

        /* Filter Panel */
        .gp-filter-card {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 14px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.25rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        .gp-filter-form {
            display: flex;
            gap: 0.85rem;
            flex-wrap: wrap;
            align-items: center;
        }
        .gp-search-box {
            flex: 2;
            min-width: 250px;
            position: relative;
        }
        .gp-search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #9CA3AF;
            font-size: 0.9rem;
        }
        .gp-search-box input {
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
        .gp-search-box input:focus {
            background: #FFFFFF;
            border-color: #E31E24;
            box-shadow: 0 0 0 3px rgba(227,30,36,0.1);
        }

        /* Table Card */
        .gp-table-card {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        }
        .gp-table-header-title {
            padding: 1rem 1.4rem;
            border-bottom: 1px solid #F3F4F6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .gp-table-header-title h3 {
            font-size: 0.95rem;
            font-weight: 700;
            color: #111827;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .gp-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        .gp-table th {
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
        .gp-table td {
            padding: 0.9rem 1rem;
            border-bottom: 1px solid #F3F4F6;
            font-size: 0.85rem;
            color: #374151;
            vertical-align: middle;
        }
        .gp-table tbody tr:hover {
            background: #F9FAFB;
        }

        /* Badges */
        .badge-tienda {
            background: #EFF6FF;
            color: #2563EB;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 0.74rem;
            font-weight: 700;
            border: 1px solid #BFDBFE;
        }
        .badge-cat-gasto {
            background: #F5F3FF;
            color: #7C3AED;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 0.74rem;
            font-weight: 700;
            border: 1px solid #DDD6FE;
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
        .status-pill.vigente { background: rgba(5,150,105,0.1); color: #059669; border: 1px solid rgba(5,150,105,0.25); }
        .status-pill.por-vencer { background: rgba(217,119,6,0.1); color: #D97706; border: 1px solid rgba(217,119,6,0.25); }
        .status-pill.vencido { background: rgba(220,38,38,0.1); color: #DC2626; border: 1px solid rgba(220,38,38,0.25); }
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
        .gp-toast {
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
                <div class="gp-hero">
                    <div class="gp-hero-title">
                        <h1><i class="fas fa-shield-halved" style="color:#E31E24;"></i> Gastos Fijos y Licencias ITSE</h1>
                        <p>Vigencia de certificados de Defensa Civil, servicios básicos y alquileres</p>
                    </div>
                    <div>
                        <?php if($tab === 'permisos'): ?>
                            <button class="btn btn-primary" onclick="abrirModal('modalNuevoPermiso')" style="font-weight:600; padding:0.55rem 1.1rem; border-radius:10px; box-shadow:0 4px 12px rgba(227,30,36,0.25);">
                                <i class="fas fa-plus" style="margin-right:6px;"></i> Registrar Certificado ITSE
                            </button>
                        <?php else: ?>
                            <button class="btn btn-primary" onclick="abrirModal('modalNuevoGasto')" style="font-weight:600; padding:0.55rem 1.1rem; border-radius:10px; box-shadow:0 4px 12px rgba(227,30,36,0.25);">
                                <i class="fas fa-plus" style="margin-right:6px;"></i> Registrar Gasto Fijo
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Toast Alerts -->
                <?php if (isset($_GET['msg'])): ?>
                    <div id="gpToast" class="gp-toast">
                        <div>
                            <i class="fas fa-check-circle" style="margin-right:8px;"></i>
                            <?php 
                                if ($_GET['msg'] === 'creado') echo "Registro guardado exitosamente.";
                                elseif ($_GET['msg'] === 'actualizado') echo "Estado de pago actualizado correctamente.";
                                elseif ($_GET['msg'] === 'eliminado') echo "Registro eliminado del sistema.";
                            ?>
                        </div>
                        <button type="button" onclick="this.parentElement.remove()" style="background:none; border:none; color:#FFFFFF; font-size:1.2rem; cursor:pointer;">&times;</button>
                    </div>
                <?php endif; ?>

                <!-- KPI Cards -->
                <div class="gp-kpis-grid">
                    <div class="gp-kpi-card">
                        <div class="gp-kpi-icon icon-emerald-bg">
                            <i class="fas fa-certificate"></i>
                        </div>
                        <div class="gp-kpi-info">
                            <span class="label">Certificados Vigentes</span>
                            <h3 style="color:#059669;"><?= $totalVigentes ?></h3>
                            <span class="sub-tag" style="background:#ECFDF5; color:#059669;">
                                <i class="fas fa-circle-check"></i> Al día (> 30 días)
                            </span>
                        </div>
                    </div>

                    <div class="gp-kpi-card">
                        <div class="gp-kpi-icon icon-amber-bg">
                            <i class="fas fa-triangle-exclamation"></i>
                        </div>
                        <div class="gp-kpi-info">
                            <span class="label">Por Vencer</span>
                            <h3 style="color:#D97706;"><?= $totalPorVencer ?></h3>
                            <span class="sub-tag" style="background:#FFFBEB; color:#D97706;">
                                <i class="fas fa-clock"></i> Próximos 30 días
                            </span>
                        </div>
                    </div>

                    <div class="gp-kpi-card">
                        <div class="gp-kpi-icon icon-rose-bg">
                            <i class="fas fa-circle-xmark"></i>
                        </div>
                        <div class="gp-kpi-info">
                            <span class="label">Certificados Vencidos</span>
                            <h3 style="color:#DC2626;"><?= $totalVencidos ?></h3>
                            <span class="sub-tag" style="background:#FEF2F2; color:#DC2626;">
                                <i class="fas fa-triangle-exclamation"></i> Requiere renovación
                            </span>
                        </div>
                    </div>

                    <div class="gp-kpi-card">
                        <div class="gp-kpi-icon icon-indigo-bg">
                            <i class="fas fa-bolt"></i>
                        </div>
                        <div class="gp-kpi-info">
                            <span class="label">Gastos Fijos por Pagar</span>
                            <h3 style="color:#4F46E5;"><?= formatearMonto($totalGastosFijos) ?></h3>
                            <span class="sub-tag" style="background:#EEF2FF; color:#4F46E5;">
                                <?= $totalGastosFijosReg ?> Servicios pendientes
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Tabs de Navegación -->
                <div class="gp-tabs">
                    <a href="?tab=permisos" class="gp-tab-link <?= $tab==='permisos'?'active':'' ?>">
                        <i class="fas fa-shield-halved"></i> 1. Licencias ITSE y Defensa Civil
                    </a>
                    <a href="?tab=gastos" class="gp-tab-link <?= $tab==='gastos'?'active':'' ?>">
                        <i class="fas fa-bolt"></i> 2. Servicios Básicos y Gastos Fijos
                    </a>
                </div>

                <!-- Filtros -->
                <div class="gp-filter-card">
                    <form method="GET" class="gp-filter-form">
                        <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
                        <div class="gp-search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" name="search" placeholder="<?= $tab==='permisos' ? 'Buscar por certificado, tienda o dirección...' : 'Buscar por categoría, tienda o proveedor...' ?>" value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <button type="submit" class="btn btn-primary" style="padding:0.58rem 1.1rem; border-radius:10px; font-weight:600;">
                            <i class="fas fa-filter"></i> Filtrar
                        </button>
                        <?php if(!empty($search)): ?>
                            <a href="gastos_permisos.php?tab=<?= $tab ?>" class="btn btn-outline" style="padding:0.58rem 0.9rem; border-radius:10px;" title="Limpiar Filtros">
                                <i class="fas fa-times"></i>
                            </a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Tablas de Datos -->
                <?php if($tab === 'permisos'): ?>
                    <div class="gp-table-card">
                        <div class="gp-table-header-title">
                            <h3><i class="fas fa-shield-halved" style="color:#059669;"></i> Licencias Municipales y Certificados ITSE</h3>
                            <span style="font-size:0.8rem; color:#6B7280; font-weight:500;">
                                Mostrando <?= count($permisos) ?> de <?= $total_items ?> certificados
                            </span>
                        </div>

                        <div style="overflow-x:auto;">
                            <table class="gp-table">
                                <thead>
                                    <tr>
                                        <th>Certificado / Licencia</th>
                                        <th>Tienda / Local</th>
                                        <th>Dirección</th>
                                        <th>F. Emisión</th>
                                        <th>F. Vencimiento</th>
                                        <th>Días Restantes</th>
                                        <th>Estado</th>
                                        <th>Observación</th>
                                        <th style="text-align:center;">Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($permisos)): ?>
                                        <tr>
                                            <td colspan="9" style="text-align:center; padding:3.5rem; color:#9CA3AF;">
                                                <i class="fas fa-folder-open" style="font-size:2.5rem; margin-bottom:0.8rem; opacity:0.35; display:block;"></i>
                                                No se encontraron certificados o licencias con los filtros seleccionados.
                                            </td>
                                        </tr>
                                    <?php else: foreach($permisos as $p): 
                                        $dias = intval($p['dias_restantes'] ?? 0);
                                        if ($dias < 0) {
                                            $badgeClass = 'vencido';
                                            $estadoTxt = 'VENCIDO';
                                            $diasColor = '#DC2626';
                                            $diasLabel = abs($dias) . ' días vencido';
                                        } elseif ($dias <= 30) {
                                            $badgeClass = 'por-vencer';
                                            $estadoTxt = 'POR VENCER';
                                            $diasColor = '#D97706';
                                            $diasLabel = $dias . ' días restantes';
                                        } else {
                                            $badgeClass = 'vigente';
                                            $estadoTxt = 'VIGENTE';
                                            $diasColor = '#059669';
                                            $diasLabel = $dias . ' días restantes';
                                        }
                                    ?>
                                        <tr>
                                            <td><strong style="color:#111827;"><?= htmlspecialchars($p['titulo']) ?></strong></td>
                                            <td><span class="badge-tienda"><?= htmlspecialchars($p['tienda'] ?: 'GENERAL') ?></span></td>
                                            <td style="color:#4B5563; font-size:0.82rem;"><?= htmlspecialchars($p['direccion_tienda'] ?: '—') ?></td>
                                            <td><?= $p['f_servicio'] ? date('d/m/Y', strtotime($p['f_servicio'])) : '—' ?></td>
                                            <td><strong><?= $p['f_venc'] ? date('d/m/Y', strtotime($p['f_venc'])) : '—' ?></strong></td>
                                            <td>
                                                <span style="font-weight:700; color:<?= $diasColor ?>;">
                                                    <?= $diasLabel ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="status-pill <?= $badgeClass ?>">
                                                    <?= $estadoTxt ?>
                                                </span>
                                            </td>
                                            <td style="color:#6B7280; font-size:0.82rem;">
                                                <?= htmlspecialchars($p['observacion'] ?: '—') ?>
                                            </td>
                                            <td style="text-align:center;">
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar este certificado?');">
                                                    <input type="hidden" name="action" value="delete_permiso">
                                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                                    <button type="submit" class="btn-icon-soft delete" title="Eliminar">
                                                        <i class="fas fa-trash-can"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="gp-table-card">
                        <div class="gp-table-header-title">
                            <h3><i class="fas fa-bolt" style="color:#7C3AED;"></i> Servicios Básicos, Alquileres y Gastos Operativos</h3>
                            <span style="font-size:0.8rem; color:#6B7280; font-weight:500;">
                                Mostrando <?= count($gastos) ?> de <?= $total_items ?> gastos
                            </span>
                        </div>

                        <div style="overflow-x:auto;">
                            <table class="gp-table">
                                <thead>
                                    <tr>
                                        <th>Categoría</th>
                                        <th>Tienda / Local</th>
                                        <th>Proveedor / Empresa</th>
                                        <th>F. Vencimiento</th>
                                        <th style="text-align:right;">Monto (S/)</th>
                                        <th>Estado</th>
                                        <th>Observación</th>
                                        <th style="text-align:center;">Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($gastos)): ?>
                                        <tr>
                                            <td colspan="8" style="text-align:center; padding:3.5rem; color:#9CA3AF;">
                                                <i class="fas fa-folder-open" style="font-size:2.5rem; margin-bottom:0.8rem; opacity:0.35; display:block;"></i>
                                                No se encontraron gastos fijos registrados.
                                            </td>
                                        </tr>
                                    <?php else: foreach($gastos as $g): 
                                        $isPagado = ($g['estado'] === 'PAGADO');
                                    ?>
                                        <tr>
                                            <td><span class="badge-cat-gasto"><?= htmlspecialchars($g['categoria']) ?></span></td>
                                            <td><span class="badge-tienda"><?= htmlspecialchars($g['tienda'] ?: 'GENERAL') ?></span></td>
                                            <td><strong style="color:#111827;"><?= htmlspecialchars($g['proveedor_servicio'] ?: '—') ?></strong></td>
                                            <td><?= $g['f_venc'] ? date('d/m/Y', strtotime($g['f_venc'])) : '<span style="color:#9CA3AF;">—</span>' ?></td>
                                            <td style="text-align:right; font-weight:800; color:<?= $isPagado ? '#059669' : '#DC2626' ?>;">
                                                <?= formatearMonto($g['monto']) ?>
                                            </td>
                                            <td>
                                                <?php if($isPagado): ?>
                                                    <span class="status-pill pagado"><i class="fas fa-circle-check"></i> PAGADO</span>
                                                <?php else: ?>
                                                    <span class="status-pill pendiente"><i class="fas fa-clock"></i> PENDIENTE</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="color:#6B7280; font-size:0.82rem;">
                                                <?= htmlspecialchars($g['observacion'] ?: '—') ?>
                                            </td>
                                            <td style="text-align:center;">
                                                <div class="btn-action-group">
                                                    <?php if(!$isPagado): ?>
                                                        <form method="POST" style="display:inline;">
                                                            <input type="hidden" name="action" value="update_gasto_estado">
                                                            <input type="hidden" name="id" value="<?= $g['id'] ?>">
                                                            <input type="hidden" name="estado" value="PAGADO">
                                                            <button type="submit" class="btn-pay-pill" title="Marcar como Pagado">
                                                                <i class="fas fa-check"></i> Pagar
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar este gasto fijo?');">
                                                        <input type="hidden" name="action" value="delete_gasto">
                                                        <input type="hidden" name="id" value="<?= $g['id'] ?>">
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

    <!-- Modal Nuevo Permiso -->
    <div class="modal-overlay" id="modalNuevoPermiso">
        <div class="modal-box">
            <div class="modal-header">
                <h3><i class="fas fa-shield-halved" style="color:#059669;"></i> Registrar Certificado / Licencia ITSE</h3>
                <button type="button" class="btn-icon-soft" onclick="cerrarModal('modalNuevoPermiso')">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create_permiso">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Título del Certificado / Licencia *</label>
                        <input type="text" name="titulo" class="form-control-custom" required placeholder="Ej: INSPECCIÓN TÉCNICA DE SEGURIDAD EN EDIFICACIONES (ITSE)">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Tienda / Local Asignado</label>
                            <input type="text" name="tienda" class="form-control-custom" placeholder="Ej: TIENDA 01, ALMACÉN CENTRAL">
                        </div>
                        <div class="form-group">
                            <label>Dirección del Predio</label>
                            <input type="text" name="direccion_tienda" class="form-control-custom" placeholder="Ej: Av. Principal 123">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Fecha de Emisión</label>
                            <input type="date" name="f_servicio" class="form-control-custom">
                        </div>
                        <div class="form-group">
                            <label>Fecha de Vencimiento *</label>
                            <input type="date" name="f_venc" class="form-control-custom" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Observación / Nro Expediente</label>
                        <input type="text" name="observacion" class="form-control-custom" placeholder="Detalles de la inspección">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="cerrarModal('modalNuevoPermiso')">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Guardar Certificado</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Nuevo Gasto -->
    <div class="modal-overlay" id="modalNuevoGasto">
        <div class="modal-box">
            <div class="modal-header">
                <h3><i class="fas fa-bolt" style="color:#7C3AED;"></i> Registrar Gasto Fijo / Servicio</h3>
                <button type="button" class="btn-icon-soft" onclick="cerrarModal('modalNuevoGasto')">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create_gasto">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Categoría del Gasto *</label>
                        <select name="categoria" class="form-control-custom">
                            <option value="ALQUILER">ALQUILER DE LOCAL</option>
                            <option value="LUZ">ENERGÍA ELÉCTRICA (LUZ)</option>
                            <option value="AGUA">AGUA POTABLE</option>
                            <option value="INTERNET">INTERNET / TELEFONÍA</option>
                            <option value="PREDIAL">IMPUESTO PREDIAL / ARBITRIOS</option>
                            <option value="OTRO">OTRO SERVICIO</option>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Tienda / Local</label>
                            <input type="text" name="tienda" class="form-control-custom" placeholder="Ej: TIENDA 01">
                        </div>
                        <div class="form-group">
                            <label>Proveedor / Empresa</label>
                            <input type="text" name="proveedor_servicio" class="form-control-custom" placeholder="Ej: LUZ DEL SUR, SEDAPAL, CLARO">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Monto a Pagar (S/) *</label>
                            <input type="number" step="0.01" name="monto" class="form-control-custom" required placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label>Fecha de Vencimiento</label>
                            <input type="date" name="f_venc" class="form-control-custom">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Observación</label>
                        <input type="text" name="observacion" class="form-control-custom" placeholder="Nro de suministro o recibo">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="cerrarModal('modalNuevoGasto')">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Guardar Gasto</button>
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
