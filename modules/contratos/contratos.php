<?php
require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/contrato_model.php';

$model = new ContratoModel($db);

$isSeller = in_array(strtolower($user_role ?? ''), ['vendedor', 'vendedora']);
$userId = $_SESSION['user_id'] ?? null;

$filters = [
    'estado' => $_GET['estado'] ?? null,
    'search' => $_GET['search'] ?? null,
];

if ($isSeller) {
    // Las vendedoras SOLO pueden ver sus propios contratos
    $filters['vendedor_id'] = $userId;
    $page_subtitle = 'Mis contratos de venta y pedidos a clientes';
} else {
    // Administradores y otros perfiles con acceso pueden ver todo y filtrar
    $filters['vendedor_id'] = !empty($_GET['vendedor_id']) ? intval($_GET['vendedor_id']) : null;
    $filters['local_id'] = !empty($_GET['local_id']) ? intval($_GET['local_id']) : null;
    $page_subtitle = 'Control general de contratos por vendedora y sucursal';
}

$contratos = $model->getAll($filters);

// Cargar listas para filtros de Administrador
$vendedores_list = [];
$locales = [];
if (!$isSeller) {
    $vendedores_list = $db->query("
        SELECT u.id, u.nombre_completo, u.username, r.nombre as rol_nombre 
        FROM usuarios u
        LEFT JOIN usuario_roles ur ON u.id = ur.usuario_id
        LEFT JOIN roles r ON ur.rol_id = r.id
        WHERE u.estado = 'Activo'
        ORDER BY u.nombre_completo ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $locales = $db->query("SELECT * FROM locales ORDER BY tipo DESC, nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
}

// KPI Stats calculation
$totalActivos = 0;
$totalPendientesProd = 0;
$totalPorEntregarSemana = 0;
$totalSaldoPorCobrar = 0.0;

$now = new DateTime();
$oneWeek = (new DateTime())->modify('+7 days');

// Contadores de estado para las pestañas/pills
$countTodos = count($contratos);
$countPendientes = 0;
$countProduccion = 0;
$countListos = 0;
$countEntregados = 0;
$countAnulados = 0;

foreach ($contratos as $c) {
    $st = $c['estado_contrato'] ?? '';
    if ($st === 'Pendiente') $countPendientes++;
    elseif ($st === 'En Producción') $countProduccion++;
    elseif ($st === 'Listo para Entrega') $countListos++;
    elseif ($st === 'Entregado') $countEntregados++;
    elseif ($st === 'Anulado') $countAnulados++;

    if ($st !== 'Anulado' && $st !== 'Entregado') {
        $totalActivos++;
        $totalSaldoPorCobrar += floatval($c['monto_saldo']);
    }
    if ($st === 'Pendiente' || $st === 'En Producción') {
        $totalPendientesProd++;
    }
    if (!empty($c['fecha_entrega_estimada']) && $st !== 'Entregado' && $st !== 'Anulado') {
        $fechaEnt = new DateTime($c['fecha_entrega_estimada']);
        if ($fechaEnt >= $now && $fechaEnt <= $oneWeek) {
            $totalPorEntregarSemana++;
        }
    }
}

// Helper para URLs de píldoras manteniendo filtros activos
function getPillUrl($estado, $filters, $isSeller) {
    $params = [];
    if (!empty($estado)) $params['estado'] = $estado;
    if (!empty($filters['search'])) $params['search'] = $filters['search'];
    if (!$isSeller) {
        if (!empty($filters['vendedor_id'])) $params['vendedor_id'] = $filters['vendedor_id'];
        if (!empty($filters['local_id'])) $params['local_id'] = $filters['local_id'];
    }
    return 'contratos.php' . (!empty($params) ? '?' . http_build_query($params) : '');
}

$page_title = 'Gestión de Contratos';
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
        /* Header Hero */
        .page-header-flex {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1.3rem;
        }
        .page-header-title h1 {
            font-size: 1.45rem;
            font-weight: 800;
            color: #0F172A;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .page-header-title p {
            color: #64748B;
            font-size: 0.85rem;
            margin: 0.25rem 0 0 0;
        }

        /* KPI Cards Grid */
        .contratos-kpis-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.1rem;
            margin-bottom: 1.3rem;
        }
        .contrato-kpi-card {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 12px;
            padding: 1.1rem 1.3rem;
            display: flex;
            align-items: center;
            gap: 1.1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .contrato-kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .contrato-kpi-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
        }
        .icon-blue-gradient { background: rgba(37,99,235,0.1); color: #2563EB; }
        .icon-amber-gradient { background: rgba(217,119,6,0.1); color: #D97706; }
        .icon-indigo-gradient { background: rgba(79,70,229,0.1); color: #4F46E5; }
        .icon-rose-gradient { background: rgba(225,29,72,0.1); color: #E11D48; }

        .contrato-kpi-info span.kpi-label {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
            color: #64748B;
            display: block;
            margin-bottom: 0.2rem;
        }
        .contrato-kpi-info h3 {
            font-size: 1.4rem;
            font-weight: 800;
            color: #0F172A;
            margin: 0;
            line-height: 1.1;
        }

        /* Filter Pills & Panel */
        .filter-panel-card {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 14px;
            padding: 1.1rem 1.3rem;
            margin-bottom: 1.3rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        .status-pills-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1rem;
            padding-bottom: 0.85rem;
            border-bottom: 1px solid #F1F5F9;
        }
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 0.4rem 0.85rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            color: #475569;
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            text-decoration: none;
            transition: all 0.15s ease;
        }
        .status-pill:hover {
            background: #F1F5F9;
            color: #0F172A;
            border-color: #CBD5E1;
        }
        .status-pill.active {
            background: #0F172A;
            color: #FFFFFF;
            border-color: #0F172A;
        }
        .status-pill .pill-count {
            font-size: 0.72rem;
            padding: 1px 6px;
            border-radius: 10px;
            background: rgba(0,0,0,0.08);
        }
        .status-pill.active .pill-count {
            background: rgba(255,255,255,0.25);
            color: #FFFFFF;
        }

        /* Filter Search Row */
        .filter-inputs-row-admin {
            display: grid;
            grid-template-columns: 2fr 1.3fr 1.3fr auto;
            gap: 0.85rem;
            align-items: flex-end;
        }
        .filter-inputs-row-seller {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 0.85rem;
            align-items: flex-end;
        }
        @media (max-width: 900px) {
            .filter-inputs-row-admin {
                grid-template-columns: 1fr;
            }
            .filter-inputs-row-seller {
                grid-template-columns: 1fr;
            }
        }

        /* Table Card */
        .table-container-card {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        .table-contratos {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.86rem;
        }
        .table-contratos thead th {
            background: #F8FAFC;
            color: #475569;
            font-weight: 700;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 0.9rem 1rem;
            border-bottom: 1px solid #E2E8F0;
            text-align: left;
            white-space: nowrap;
        }
        .table-contratos tbody tr {
            transition: background 0.15s ease;
        }
        .table-contratos tbody tr:hover {
            background: #F8FAFC;
        }
        .table-contratos tbody td {
            padding: 0.95rem 1rem;
            border-bottom: 1px solid #F1F5F9;
            vertical-align: middle;
            color: #334155;
        }
        .table-contratos tbody tr:last-child td {
            border-bottom: none;
        }

        /* Micro Components */
        .contract-code-badge {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-weight: 700;
            color: #0F172A;
            background: #F1F5F9;
            border: 1px solid #E2E8F0;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 0.84rem;
            display: inline-block;
        }
        .store-pill {
            display: inline-block;
            font-size: 0.73rem;
            font-weight: 600;
            color: #475569;
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            padding: 1px 6px;
            border-radius: 4px;
            margin-top: 3px;
        }

        .seller-chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.82rem;
            font-weight: 600;
            color: #1E293B;
            background: #F1F5F9;
            padding: 3px 8px;
            border-radius: 6px;
        }
        .seller-chip i {
            color: #64748B;
            font-size: 0.75rem;
        }

        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 0.3rem 0.7rem;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            white-space: nowrap;
        }
        .st-pendiente { background: rgba(217,119,6,0.1); color: #D97706; border: 1px solid rgba(217,119,6,0.2); }
        .st-produccion { background: rgba(37,99,235,0.1); color: #2563EB; border: 1px solid rgba(37,99,235,0.2); }
        .st-listo { background: rgba(147,51,234,0.1); color: #9333EA; border: 1px solid rgba(147,51,234,0.2); }
        .st-entregado { background: rgba(5,150,105,0.1); color: #059669; border: 1px solid rgba(5,150,105,0.2); }
        .st-anulado { background: #F1F5F9; color: #64748B; border: 1px solid #CBD5E1; }

        /* Action Buttons */
        .action-btn-group {
            display: inline-flex;
            gap: 0.35rem;
            align-items: center;
        }
        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #E2E8F0;
            background: #FFFFFF;
            color: #475569;
            font-size: 0.85rem;
            text-decoration: none;
            transition: all 0.15s ease;
        }
        .action-btn:hover {
            border-color: #CBD5E1;
            background: #F8FAFC;
            color: #0F172A;
            transform: translateY(-1px);
        }
        .action-btn.view:hover { color: #2563EB; border-color: #93C5FD; background: #EFF6FF; }
        .action-btn.print:hover { color: #4F46E5; border-color: #C7D2FE; background: #EEF2FF; }
        .action-btn.egreso:hover { color: #D97706; border-color: #FDE68A; background: #FFFBEB; }
        .action-btn.cancel:hover { color: #DC2626; border-color: #FECACA; background: #FEF2F2; }
    </style>
</head>
<body>
<div class="app-wrapper">
    <?php include '../../views/partials/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../views/partials/header.php'; ?>
        <div class="page-content">

            <!-- Hero Header -->
            <div class="page-header-flex">
                <div class="page-header-title">
                    <h1><i class="fas fa-file-signature" style="color:var(--primary);"></i> <?= $page_title ?></h1>
                    <p><?= $page_subtitle ?></p>
                </div>
                <a href="contrato_form.php" class="btn btn-primary" style="padding:0.65rem 1.3rem; border-radius:8px; font-weight:700; box-shadow:0 4px 12px rgba(227,30,36,0.2);">
                    <i class="fas fa-plus" style="margin-right:6px;"></i> Emitir Nuevo Contrato
                </a>
            </div>

            <!-- Alerts -->
            <?php if (isset($_GET['error'])): ?>
            <div id="alertMsg" style="background:#FEF2F2; color:#DC2626; border:1px solid #FECACA; padding:0.9rem 1.2rem; border-radius:10px; margin-bottom:1.3rem; display:flex; justify-content:space-between; align-items:center;">
                <div><i class="fas fa-exclamation-triangle" style="margin-right:0.5rem;"></i> <?= htmlspecialchars($_GET['error']) ?></div>
                <button type="button" onclick="this.parentElement.remove()" style="background:none; border:none; color:#DC2626; font-size:1.2rem; cursor:pointer;">&times;</button>
            </div>
            <?php endif; ?>

            <?php if (isset($_GET['msg']) && $_GET['msg'] === 'anulado'): ?>
            <div id="alertMsg" style="background:#FFFBEB; color:#D97706; border:1px solid #FDE68A; padding:0.9rem 1.2rem; border-radius:10px; margin-bottom:1.3rem; display:flex; justify-content:space-between; align-items:center;">
                <div><i class="fas fa-ban" style="margin-right:0.5rem;"></i> Contrato anulado correctamente. El stock reservado ha sido liberado.</div>
                <button type="button" onclick="this.parentElement.remove()" style="background:none; border:none; color:#D97706; font-size:1.2rem; cursor:pointer;">&times;</button>
            </div>
            <?php endif; ?>

            <?php if (isset($_GET['msg']) && $_GET['msg'] === 'eliminado'): ?>
            <div id="alertMsg" style="background:#FEF2F2; color:#DC2626; border:1px solid #FECACA; padding:0.9rem 1.2rem; border-radius:10px; margin-bottom:1.3rem; display:flex; justify-content:space-between; align-items:center;">
                <div><i class="fas fa-trash-alt" style="margin-right:0.5rem;"></i> Contrato eliminado permanentemente del sistema.</div>
                <button type="button" onclick="this.parentElement.remove()" style="background:none; border:none; color:#DC2626; font-size:1.2rem; cursor:pointer;">&times;</button>
            </div>
            <?php endif; ?>

            <!-- KPIs Cards -->
            <div class="contratos-kpis-grid">
                <div class="contrato-kpi-card">
                    <div class="contrato-kpi-icon icon-blue-gradient">
                        <i class="fas fa-file-contract"></i>
                    </div>
                    <div class="contrato-kpi-info">
                        <span class="kpi-label">Contratos Activos</span>
                        <h3><?= $totalActivos ?></h3>
                    </div>
                </div>

                <div class="contrato-kpi-card">
                    <div class="contrato-kpi-icon icon-amber-gradient">
                        <i class="fas fa-hammer"></i>
                    </div>
                    <div class="contrato-kpi-info">
                        <span class="kpi-label">En Producción</span>
                        <h3><?= $totalPendientesProd ?></h3>
                    </div>
                </div>

                <div class="contrato-kpi-card">
                    <div class="contrato-kpi-icon icon-indigo-gradient">
                        <i class="fas fa-truck-ramp-box"></i>
                    </div>
                    <div class="contrato-kpi-info">
                        <span class="kpi-label">Entrega esta Semana</span>
                        <h3><?= $totalPorEntregarSemana ?></h3>
                    </div>
                </div>

                <div class="contrato-kpi-card">
                    <div class="contrato-kpi-icon icon-rose-gradient">
                        <i class="fas fa-hand-holding-dollar"></i>
                    </div>
                    <div class="contrato-kpi-info">
                        <span class="kpi-label">Saldo por Cobrar</span>
                        <h3 style="color:#DC2626;"><?= formatearMonto($totalSaldoPorCobrar) ?></h3>
                    </div>
                </div>
            </div>

            <!-- Filters Card -->
            <div class="filter-panel-card">
                <!-- Status Filter Pills -->
                <div class="status-pills-row">
                    <a href="<?= getPillUrl('', $filters, $isSeller) ?>" class="status-pill <?= empty($filters['estado']) ? 'active' : '' ?>">
                        Todos <span class="pill-count"><?= $countTodos ?></span>
                    </a>
                    <a href="<?= getPillUrl('Pendiente', $filters, $isSeller) ?>" class="status-pill <?= ($filters['estado'] === 'Pendiente') ? 'active' : '' ?>">
                        <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:#D97706;"></span> Pendientes <span class="pill-count"><?= $countPendientes ?></span>
                    </a>
                    <a href="<?= getPillUrl('En Producción', $filters, $isSeller) ?>" class="status-pill <?= ($filters['estado'] === 'En Producción') ? 'active' : '' ?>">
                        <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:#2563EB;"></span> En Producción <span class="pill-count"><?= $countProduccion ?></span>
                    </a>
                    <a href="<?= getPillUrl('Listo para Entrega', $filters, $isSeller) ?>" class="status-pill <?= ($filters['estado'] === 'Listo para Entrega') ? 'active' : '' ?>">
                        <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:#9333EA;"></span> Listos <span class="pill-count"><?= $countListos ?></span>
                    </a>
                    <a href="<?= getPillUrl('Entregado', $filters, $isSeller) ?>" class="status-pill <?= ($filters['estado'] === 'Entregado') ? 'active' : '' ?>">
                        <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:#059669;"></span> Entregados <span class="pill-count"><?= $countEntregados ?></span>
                    </a>
                    <a href="<?= getPillUrl('Anulado', $filters, $isSeller) ?>" class="status-pill <?= ($filters['estado'] === 'Anulado') ? 'active' : '' ?>">
                        <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:#64748B;"></span> Anulados <span class="pill-count"><?= $countAnulados ?></span>
                    </a>
                </div>

                <!-- Input Filters -->
                <form method="GET" action="contratos.php" class="<?= $isSeller ? 'filter-inputs-row-seller' : 'filter-inputs-row-admin' ?>">
                    <?php if (!empty($filters['estado'])): ?>
                        <input type="hidden" name="estado" value="<?= htmlspecialchars($filters['estado']) ?>">
                    <?php endif; ?>

                    <div>
                        <label style="font-size:0.75rem; font-weight:700; color:#475569; display:block; margin-bottom:0.3rem;">Buscar por Código / Cliente / DNI</label>
                        <input type="text" name="search" class="form-control" placeholder="Ej: T003-00912, Juan Pérez, 70216622..." value="<?= htmlspecialchars($filters['search'] ?? '') ?>">
                    </div>

                    <?php if (!$isSeller): ?>
                    <div>
                        <label style="font-size:0.75rem; font-weight:700; color:#475569; display:block; margin-bottom:0.3rem;">Vendedora / Asesor</label>
                        <select name="vendedor_id" class="form-control" onchange="this.form.submit()">
                            <option value="">Todos los asesores</option>
                            <?php foreach ($vendedores_list as $vend): ?>
                            <option value="<?= $vend['id'] ?>" <?= ($filters['vendedor_id'] == $vend['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($vend['nombre_completo'] ?? $vend['username']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label style="font-size:0.75rem; font-weight:700; color:#475569; display:block; margin-bottom:0.3rem;">Tienda / Sucursal</label>
                        <select name="local_id" class="form-control" onchange="this.form.submit()">
                            <option value="">Todas las tiendas</option>
                            <?php foreach ($locales as $loc): ?>
                            <option value="<?= $loc['id'] ?>" <?= ($filters['local_id'] == $loc['id']) ? 'selected' : '' ?>><?= htmlspecialchars($loc['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <div style="display:flex; gap:0.4rem;">
                        <button type="submit" class="btn btn-primary" style="padding:0.55rem 1rem; border-radius:8px; font-weight:600;"><i class="fas fa-filter"></i> Filtrar</button>
                        <a href="contratos.php" class="btn btn-outline" style="padding:0.55rem 0.85rem; border-radius:8px;" title="Limpiar Filtros"><i class="fas fa-times"></i></a>
                    </div>
                </form>
            </div>

            <!-- Table Card -->
            <div class="table-container-card">
                <div style="overflow-x:auto;">
                    <table class="table-contratos">
                        <thead>
                            <tr>
                                <th>Contrato & Sede</th>
                                <th>Cliente</th>
                                <th>Vendedora / Asesor</th>
                                <th>Fechas</th>
                                <th style="text-align:right;">Monto Total</th>
                                <th style="text-align:right;">A Cuenta</th>
                                <th style="text-align:right;">Saldo Pendiente</th>
                                <th style="text-align:center;">Estado</th>
                                <th style="text-align:center;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($contratos as $c): 
                                $saldo = floatval($c['monto_saldo']);
                                $total = floatval($c['monto_total']);
                                $adelanto = floatval($c['monto_adelanto']);

                                $estadoMap = [
                                    'Pendiente' => 'st-pendiente',
                                    'En Producción' => 'st-produccion',
                                    'Listo para Entrega' => 'st-listo',
                                    'Entregado' => 'st-entregado',
                                    'Anulado' => 'st-anulado'
                                ];
                                $estadoCls = $estadoMap[$c['estado_contrato']] ?? 'st-pendiente';
                            ?>
                            <tr>
                                <!-- Contrato & Sede -->
                                <td>
                                    <div class="contract-code-badge">
                                        <?= htmlspecialchars($c['codigo_completo'] ?? '') ?>
                                    </div>
                                    <div>
                                        <span class="store-pill">
                                            <i class="fas fa-store" style="font-size:0.68rem; margin-right:3px; opacity:0.7;"></i>
                                            <?= htmlspecialchars($c['local_nombre'] ?? 'Sede Principal') ?>
                                        </span>
                                    </div>
                                </td>

                                <!-- Cliente -->
                                <td>
                                    <div style="font-weight:700; color:#0F172A; font-size:0.88rem;">
                                        <?= htmlspecialchars($c['cliente_nombre'] ?? 'Cliente General') ?>
                                    </div>
                                    <div style="display:flex; flex-wrap:wrap; gap:0.5rem; align-items:center; margin-top:2px; font-size:0.75rem; color:#64748B;">
                                        <?php if (!empty($c['cliente_doc'])): ?>
                                            <span><i class="fas fa-id-card" style="margin-right:2px; opacity:0.6;"></i> <?= htmlspecialchars($c['cliente_doc']) ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($c['cliente_telefono'])): ?>
                                            <span><a href="https://wa.me/51<?= preg_replace('/[^0-9]/', '', $c['cliente_telefono']) ?>" target="_blank" style="color:#059669; text-decoration:none; font-weight:600;"><i class="fab fa-whatsapp"></i> <?= htmlspecialchars($c['cliente_telefono']) ?></a></span>
                                        <?php endif; ?>
                                        <?php if (!empty($c['firma_digital'])): ?>
                                            <span style="color:#059669; font-weight:700; font-size:0.72rem; background:#ECFDF5; padding:1px 6px; border-radius:4px; border:1px solid #A7F3D0;"><i class="fas fa-check-circle"></i> Firmado</span>
                                        <?php else: ?>
                                            <span style="color:#D97706; font-weight:600; font-size:0.72rem; background:#FFFBEB; padding:1px 6px; border-radius:4px; border:1px solid #FDE68A;"><i class="fas fa-pen"></i> Pend. Firma</span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <!-- Vendedora / Asesor -->
                                <td>
                                    <span class="seller-chip">
                                        <i class="fas fa-user-check"></i>
                                        <?= htmlspecialchars($c['vendedor_nombre'] ?? 'Sistema') ?>
                                    </span>
                                </td>

                                <!-- Fechas -->
                                <td>
                                    <div style="font-size:0.8rem; color:#475569;">
                                        <span style="color:#64748B; font-size:0.72rem; text-transform:uppercase; font-weight:600;">Emisión:</span>
                                        <?= date('d/m/Y', strtotime($c['fecha_emision'])) ?>
                                    </div>
                                    <?php if (!empty($c['fecha_entrega_estimada'])): ?>
                                    <div style="font-size:0.8rem; margin-top:2px;">
                                        <span style="color:#64748B; font-size:0.72rem; text-transform:uppercase; font-weight:600;">Entrega:</span>
                                        <strong style="color:<?= (strtotime($c['fecha_entrega_estimada']) <= time() && $c['estado_contrato'] !== 'Entregado') ? '#DC2626' : '#0F172A' ?>;">
                                            <?= date('d/m/Y', strtotime($c['fecha_entrega_estimada'])) ?>
                                        </strong>
                                    </div>
                                    <?php endif; ?>
                                </td>

                                <!-- Monto Total -->
                                <td style="text-align:right; font-weight:700; color:#0F172A; font-size:0.9rem;">
                                    <?= formatearMonto($total) ?>
                                </td>

                                <!-- A Cuenta -->
                                <td style="text-align:right; font-weight:700; color:#059669; font-size:0.88rem;">
                                    <?= formatearMonto($adelanto) ?>
                                </td>

                                <!-- Saldo Pendiente -->
                                <td style="text-align:right;">
                                    <?php if ($saldo > 0): ?>
                                        <span style="font-weight:700; color:#DC2626; background:#FEF2F2; border:1px solid #FECACA; padding:2px 8px; border-radius:6px; font-size:0.85rem;">
                                            <?= formatearMonto($saldo) ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="font-weight:600; color:#059669; background:#ECFDF5; border:1px solid #A7F3D0; padding:2px 8px; border-radius:6px; font-size:0.78rem;">
                                            Cancelado
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Estado -->
                                <td style="text-align:center;">
                                    <span class="status-badge <?= $estadoCls ?>">
                                        <?= htmlspecialchars($c['estado_contrato'] ?? 'Pendiente') ?>
                                    </span>
                                </td>

                                <!-- Acciones -->
                                <td style="text-align:center; white-space:nowrap;">
                                    <div class="action-btn-group">
                                        <a href="contrato_view.php?id=<?= $c['id'] ?>" class="action-btn view" title="Ver Ficha y Seguimiento">
                                            <i class="fas fa-eye"></i>
                                        </a>

                                        <?php if ($c['estado_contrato'] !== 'Anulado' && $c['estado_contrato'] !== 'Entregado'): ?>
                                            <a href="contrato_form.php?id=<?= $c['id'] ?>" class="action-btn edit" style="color:#2563EB;" title="Editar / Modificar Contrato">
                                                <i class="fas fa-pen"></i>
                                            </a>
                                        <?php endif; ?>

                                        <?php 
                                        $phoneC = preg_replace('/[^0-9]/', '', $c['cliente_telefono'] ?? '');
                                        if (empty($c['firma_digital']) && $c['estado_contrato'] !== 'Anulado'): 
                                            $linkFirma = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . "/carpicenter_sys/modules/contratos/firma_contrato.php?token=" . urlencode($c['firma_token'] ?? '');
                                            $msgWs = urlencode("Hola *" . $c['cliente_nombre'] . "*, aquí tienes el enlace para revisar y firmar digitalmente tu Contrato de Venta N° " . $c['codigo_completo'] . " en Industrias Carpicenter:\n\n" . $linkFirma . "\n\n(Puedes firmar directamente con tu dedo en la pantalla de tu celular).");
                                            $wsUrlFirma = (strlen($phoneC) >= 9) ? "https://wa.me/" . ((strlen($phoneC) === 9) ? '51' . $phoneC : $phoneC) . "?text=" . $msgWs : "https://api.whatsapp.com/send?text=" . $msgWs;
                                        ?>
                                            <a href="<?= $wsUrlFirma ?>" target="_blank" class="action-btn" style="color:#059669; border-color:#A7F3D0; background:#ECFDF5;" title="Enviar Link de Firma Digital por WhatsApp">
                                                <i class="fab fa-whatsapp"></i>
                                            </a>
                                        <?php elseif (!empty($c['firma_digital']) && $c['estado_contrato'] !== 'Anulado'): 
                                            $linkPdf = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . "/carpicenter_sys/modules/contratos/contrato_print.php?id=" . $c['id'];
                                            $msgWsPdf = urlencode("Hola *" . $c['cliente_nombre'] . "*, aquí tienes la copia digital de tu Contrato N° " . $c['codigo_completo'] . " firmado en Industrias Carpicenter:\n\n" . $linkPdf);
                                            $wsUrlPdf = (strlen($phoneC) >= 9) ? "https://wa.me/" . ((strlen($phoneC) === 9) ? '51' . $phoneC : $phoneC) . "?text=" . $msgWsPdf : "https://api.whatsapp.com/send?text=" . $msgWsPdf;
                                        ?>
                                            <a href="<?= $wsUrlPdf ?>" target="_blank" class="action-btn" style="color:#059669; border-color:#A7F3D0; background:#ECFDF5;" title="Enviar Contrato Firmado a WhatsApp">
                                                <i class="fab fa-whatsapp"></i>
                                            </a>
                                        <?php endif; ?>

                                        <a href="contrato_print.php?id=<?= $c['id'] ?>" target="_blank" class="action-btn print" title="Imprimir / Ver PDF">
                                            <i class="fas fa-print"></i>
                                        </a>
                                        <?php if ($c['estado_contrato'] !== 'Anulado' && $c['estado_contrato'] !== 'Entregado'): ?>
                                            <a href="/carpicenter_sys/modules/ordenes_egreso/egreso_nuevo.php?contrato_id=<?= $c['id'] ?>" class="action-btn egreso" title="Emitir Orden de Egreso">
                                                <i class="fas fa-boxes-packing"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($is_admin): ?>
                                            <?php if ($c['estado_contrato'] !== 'Anulado'): ?>
                                                <a href="contrato_controller.php?action=anular&id=<?= $c['id'] ?>" class="action-btn cancel" title="Anular Contrato" onclick="return confirm('¿Estás seguro de ANULAR este contrato? Se liberará el stock reservado.');">
                                                    <i class="fas fa-ban"></i>
                                                </a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>

                            <?php if (empty($contratos)): ?>
                            <tr>
                                <td colspan="9" style="text-align:center; padding:3.5rem 1rem; color:#94A3B8;">
                                    <i class="fas fa-folder-open" style="font-size:2.8rem; margin-bottom:0.8rem; opacity:0.4; display:block;"></i>
                                    <p style="font-size:0.95rem; font-weight:600; margin:0; color:#64748B;">No se encontraron contratos registrados.</p>
                                    <p style="font-size:0.8rem; margin-top:4px;">Prueba ajustando los filtros o emite un nuevo contrato.</p>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>
<?php include '../../views/partials/footer.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const alertMsg = document.getElementById('alertMsg');
    if (alertMsg) {
        setTimeout(function() {
            alertMsg.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            alertMsg.style.opacity = '0';
            alertMsg.style.transform = 'translateY(-10px)';
            setTimeout(function() {
                if (alertMsg.parentNode) {
                    alertMsg.remove();
                }
            }, 600);
        }, 3000);

        const url = new URL(window.location);
        if (url.searchParams.has('msg') || url.searchParams.has('error')) {
            url.searchParams.delete('msg');
            url.searchParams.delete('error');
            window.history.replaceState({}, document.title, url.pathname + (url.searchParams.toString() ? '?' + url.searchParams.toString() : ''));
        }
    }
});
</script>
</body>
</html>
