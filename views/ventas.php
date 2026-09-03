<?php
require_once __DIR__ . '/../auth/check_auth.php';
require_once __DIR__ . '/../config/db.php';

// Filtros y parámetros
$search = $_GET['search'] ?? '';
$fecha_inicio = $_GET['fecha_inicio'] ?? '';
$fecha_fin = $_GET['fecha_fin'] ?? '';
$estado_pago = $_GET['estado_pago'] ?? '';

// Construir consulta dinámica
$isSeller = in_array(strtolower($user_role ?? ''), ['vendedor', 'vendedora']);
$userId = intval($_SESSION['user_id'] ?? 0);
$userLocalId = intval($_SESSION['local_id'] ?? 0);

$sql = "
    SELECT v.*, c.nombre as cliente_nombre 
    FROM ventas v
    LEFT JOIN clientes c ON v.cliente_id = c.id
    WHERE 1=1
";
$params = [];

if ($isSeller) {
    if ($userLocalId > 0) {
        $sql .= " AND (v.usuario_id = :uid_seller OR v.local_id = :lid_seller)";
        $params[':uid_seller'] = $userId;
        $params[':lid_seller'] = $userLocalId;
    } else {
        $sql .= " AND v.usuario_id = :uid_seller";
        $params[':uid_seller'] = $userId;
    }
}

if (!empty($search)) {
    $sql .= " AND (c.nombre ILIKE :search OR v.serie ILIKE :search OR v.numero ILIKE :search OR CAST(v.id AS TEXT) ILIKE :search)";
    $params[':search'] = '%' . $search . '%';
}
if (!empty($fecha_inicio)) {
    $sql .= " AND v.fecha_emision >= :fecha_inicio";
    $params[':fecha_inicio'] = $fecha_inicio;
}
if (!empty($fecha_fin)) {
    $sql .= " AND v.fecha_emision <= :fecha_fin";
    $params[':fecha_fin'] = $fecha_fin;
}
if (!empty($estado_pago) && $estado_pago !== 'Todos') {
    $sql .= " AND v.estado_pago = :estado_pago";
    $params[':estado_pago'] = $estado_pago;
}

$sql .= " ORDER BY v.fecha DESC";

try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Estadísticas
    $stats_facturado = floatval($db->query("SELECT COALESCE(SUM(total), 0) FROM ventas WHERE estado != 'Cancelada'")->fetchColumn());
    $stats_productos = intval($db->query("
        SELECT COALESCE(SUM(vd.cantidad), 0) 
        FROM venta_detalles vd 
        JOIN ventas v ON vd.venta_id = v.id 
        WHERE v.estado != 'Cancelada'
    ")->fetchColumn());
    $stats_promedio = floatval($db->query("SELECT COALESCE(AVG(total), 0) FROM ventas WHERE estado != 'Cancelada'")->fetchColumn());

} catch (PDOException $e) {
    die("Error en base de datos: " . $e->getMessage());
}

$page_title = 'Ventas';
$page_subtitle = 'Historial y control de comprobantes de venta emitidos';
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
        /* ===== VENTAS PREMIUM ===== */
        .vta-hero {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.4rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .vta-hero-title h1 {
            font-size: 1.45rem;
            font-weight: 800;
            color: #111827;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .vta-hero-title p {
            color: #6B7280;
            font-size: 0.84rem;
            margin: 0.25rem 0 0 0;
        }

        /* KPIs */
        .vta-kpis-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 1.2rem;
            margin-bottom: 1.3rem;
        }
        .vta-kpi-card {
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
        .vta-kpi-card:hover {
            transform: translateY(-2px);
        }
        .vta-kpi-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }
        .icon-emerald-bg { background: linear-gradient(135deg, rgba(5,150,105,0.12) 0%, rgba(16,185,129,0.2) 100%); color: #059669; }
        .icon-indigo-bg { background: linear-gradient(135deg, rgba(79,70,229,0.12) 0%, rgba(99,102,241,0.2) 100%); color: #4F46E5; }
        .icon-amber-bg { background: linear-gradient(135deg, rgba(217,119,6,0.12) 0%, rgba(245,158,11,0.2) 100%); color: #D97706; }

        .vta-kpi-info span.label {
            font-size: 0.74rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
            color: #6B7280;
            display: block;
            margin-bottom: 0.15rem;
        }
        .vta-kpi-info h3 {
            font-size: 1.35rem;
            font-weight: 800;
            color: #111827;
            margin: 0;
            line-height: 1.2;
        }
        .vta-kpi-info span.sub-tag {
            font-size: 0.74rem;
            font-weight: 600;
            display: inline-block;
            margin-top: 0.25rem;
            padding: 2px 8px;
            border-radius: 20px;
        }

        /* Filter Panel */
        .vta-filter-card {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 14px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.25rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        .vta-filter-form {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            align-items: center;
        }
        .vta-search-box {
            flex: 2;
            min-width: 240px;
            position: relative;
        }
        .vta-search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #9CA3AF;
            font-size: 0.9rem;
        }
        .vta-input {
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
        .vta-input:focus {
            background: #FFFFFF;
            border-color: #E31E24;
            box-shadow: 0 0 0 3px rgba(227,30,36,0.1);
        }
        .vta-control-sm {
            padding: 0.55rem 0.85rem;
            border-radius: 9px;
            border: 1px solid #D1D5DB;
            background: #F9FAFB;
            font-size: 0.84rem;
            color: #111827;
            outline: none;
        }

        /* Table Card */
        .vta-table-card {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        }
        .vta-table-header-title {
            padding: 1rem 1.4rem;
            border-bottom: 1px solid #F3F4F6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .vta-table-header-title h3 {
            font-size: 0.95rem;
            font-weight: 700;
            color: #111827;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .vta-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        .vta-table th {
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
        .vta-table td {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid #F3F4F6;
            font-size: 0.85rem;
            color: #374151;
            vertical-align: middle;
            white-space: nowrap;
        }
        .vta-table tbody tr:hover {
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
        .status-pill.pagado, .status-pill.aceptado { background: rgba(5,150,105,0.1); color: #059669; border: 1px solid rgba(5,150,105,0.25); }
        .status-pill.pendiente { background: rgba(217,119,6,0.1); color: #D97706; border: 1px solid rgba(217,119,6,0.25); }
        .status-pill.vencido, .status-pill.rechazado { background: rgba(220,38,38,0.1); color: #DC2626; border: 1px solid rgba(220,38,38,0.25); }
        .status-pill.no-enviado { background: rgba(100,116,139,0.1); color: #475569; border: 1px solid rgba(100,116,139,0.25); }

        /* Action Buttons */
        .vta-actions {
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
        .btn-action-soft.view { background: rgba(37,99,235,0.08); color: #2563EB; }
        .btn-action-soft.view:hover { background: #2563EB; color: #FFFFFF; }
        .btn-action-soft.guia { background: rgba(5,150,105,0.1); color: #059669; }
        .btn-action-soft.guia:hover { background: #059669; color: #FFFFFF; }
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
            <div class="vta-hero">
                <div class="vta-hero-title">
                    <h1><i class="fas fa-shopping-bag" style="color:#E31E24;"></i> Ventas</h1>
                    <p>Facturación electrónica, boletas de venta y control de recaudación</p>
                </div>
                <a href="venta_nueva.php" class="btn btn-primary" style="font-weight:600; padding:0.55rem 1.2rem; border-radius:10px; box-shadow:0 4px 12px rgba(227,30,36,0.25);">
                    <i class="fas fa-plus" style="margin-right:6px;"></i> Nueva Venta
                </a>
            </div>
            
            <?php if (isset($_GET['msg']) && $_GET['msg'] === 'eliminado'): ?>
                <div style="background:#EF4444; color:#fff; padding:0.85rem 1.25rem; border-radius:10px; margin-bottom:1.2rem; display:flex; justify-content:space-between; align-items:center; font-weight:600;">
                    <div><i class="fas fa-trash-alt" style="margin-right:8px;"></i> Venta eliminada permanentemente del sistema.</div>
                    <button type="button" onclick="this.parentElement.remove()" style="background:none; border:none; color:#fff; font-size:1.2rem; cursor:pointer;">&times;</button>
                </div>
            <?php endif; ?>

            <!-- KPI Cards -->
            <div class="vta-kpis-grid">
                <div class="vta-kpi-card">
                    <div class="vta-kpi-icon icon-emerald-bg">
                        <i class="fas fa-coins"></i>
                    </div>
                    <div class="vta-kpi-info">
                        <span class="label">Total Facturado</span>
                        <h3 style="color:#059669;">S/ <?= number_format($stats_facturado, 2) ?></h3>
                        <span class="sub-tag" style="background:#ECFDF5; color:#059669;">Ventas válidas</span>
                    </div>
                </div>

                <div class="vta-kpi-card">
                    <div class="vta-kpi-icon icon-indigo-bg">
                        <i class="fas fa-boxes-stacked"></i>
                    </div>
                    <div class="vta-kpi-info">
                        <span class="label">Productos Vendidos</span>
                        <h3 style="color:#4F46E5;"><?= number_format($stats_productos) ?> un</h3>
                        <span class="sub-tag" style="background:#EEF2FF; color:#4F46E5;">Unidades despachadas</span>
                    </div>
                </div>

                <div class="vta-kpi-card">
                    <div class="vta-kpi-icon icon-amber-bg">
                        <i class="fas fa-calculator"></i>
                    </div>
                    <div class="vta-kpi-info">
                        <span class="label">Ticket Promedio</span>
                        <h3 style="color:#D97706;">S/ <?= number_format($stats_promedio, 2) ?></h3>
                        <span class="sub-tag" style="background:#FFFBEB; color:#D97706;">Por transacción</span>
                    </div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="vta-filter-card">
                <form method="GET" action="ventas.php" class="vta-filter-form">
                    <div class="vta-search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" class="vta-input" placeholder="Buscar por cliente o comprobante..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <input type="date" name="fecha_inicio" class="vta-control-sm" value="<?= htmlspecialchars($fecha_inicio) ?>" title="Fecha Inicio">
                    <input type="date" name="fecha_fin" class="vta-control-sm" value="<?= htmlspecialchars($fecha_fin) ?>" title="Fecha Fin">
                    <select name="estado_pago" class="vta-control-sm">
                        <option value="Todos" <?= $estado_pago == 'Todos' ? 'selected' : '' ?>>Todos los pagos</option>
                        <option value="PENDIENTE" <?= $estado_pago == 'PENDIENTE' ? 'selected' : '' ?>>PENDIENTE</option>
                        <option value="PAGADO" <?= $estado_pago == 'PAGADO' ? 'selected' : '' ?>>PAGADO</option>
                        <option value="VENCIDO" <?= $estado_pago == 'VENCIDO' ? 'selected' : '' ?>>VENCIDO</option>
                    </select>
                    <button type="submit" class="btn btn-primary" style="padding:0.55rem 1rem; border-radius:9px; font-weight:600;">
                        <i class="fas fa-filter"></i> Filtrar
                    </button>
                    <?php if(!empty($search) || !empty($fecha_inicio) || !empty($fecha_fin) || !empty($estado_pago)): ?>
                        <a href="ventas.php" class="btn btn-outline" style="padding:0.55rem 0.85rem; border-radius:9px;" title="Limpiar Filtros">
                            <i class="fas fa-times"></i>
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Tabla de Ventas -->
            <div class="vta-table-card">
                <div class="vta-table-header-title">
                    <h3><i class="fas fa-shopping-bag" style="color:#E31E24;"></i> Registro de Comprobantes</h3>
                    <span style="font-size:0.8rem; color:#6B7280; font-weight:500;">
                        Mostrando <?= count($ventas) ?> ventas registradas
                    </span>
                </div>

                <div style="overflow-x:auto;">
                    <table class="vta-table">
                        <thead>
                            <tr>
                                <th>Comprobante</th>
                                <th>Cliente</th>
                                <th>Fecha Emisión</th>
                                <th style="text-align:right;">Total (S/)</th>
                                <th>Estado Pago</th>
                                <th>SUNAT</th>
                                <th style="text-align:center;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($ventas)): ?>
                                <tr>
                                    <td colspan="7" style="text-align:center; padding:3.5rem; color:#9CA3AF;">
                                        <i class="fas fa-shopping-bag" style="font-size:2.5rem; margin-bottom:0.8rem; opacity:0.35; display:block;"></i>
                                        No se encontraron ventas con los filtros aplicados.
                                    </td>
                                </tr>
                            <?php else: foreach($ventas as $v): 
                                $comprobante = !empty($v['tipo_comprobante']) 
                                    ? "{$v['tipo_comprobante']} {$v['serie']}-{$v['numero']}" 
                                    : "VTA-" . str_pad($v['id'], 5, '0', STR_PAD_LEFT);
                                
                                $fecha_emision_formato = !empty($v['fecha_emision']) 
                                    ? date('d/m/Y', strtotime($v['fecha_emision'])) 
                                    : date('d/m/Y', strtotime($v['fecha']));
                                
                                $pago_status = $v['estado_pago'] ?? ($v['estado'] === 'Completada' ? 'PAGADO' : 'PENDIENTE');
                                $sunat_status = $v['estado_sunat'] ?? 'NO_ENVIADO';
                            ?>
                            <tr>
                                <td><span class="doc-badge"><?= htmlspecialchars($comprobante) ?></span></td>
                                <td><strong style="color:#111827;"><?= htmlspecialchars($v['cliente_nombre'] ?? 'Cliente General') ?></strong></td>
                                <td style="font-size:0.83rem;"><?= htmlspecialchars($fecha_emision_formato) ?></td>
                                <td style="text-align:right; font-weight:800; color:#111827; font-size:0.92rem;">
                                    S/ <?= number_format($v['total'], 2) ?>
                                </td>
                                <td>
                                    <span class="status-pill <?= strtolower($pago_status) ?>">
                                        <?= $pago_status ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    $sunatClass = ($sunat_status === 'ACEPTADO') ? 'aceptado' : (($sunat_status === 'NO_ENVIADO') ? 'no-enviado' : 'rechazado');
                                    ?>
                                    <span class="status-pill <?= $sunatClass ?>">
                                        <?= $sunat_status ?>
                                    </span>
                                </td>
                                <td style="text-align:center;">
                                    <div class="vta-actions">
                                        <a href="venta_view.php?id=<?= $v['id'] ?>" class="btn-action-soft view" title="Ver Detalles">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="guia_nueva.php?venta_id=<?= $v['id'] ?>" class="btn-action-soft guia" title="Generar Guía de Remisión">
                                            <i class="fas fa-truck"></i>
                                        </a>
                                        <?php if ($is_admin): ?>
                                            <a href="venta_delete.php?id=<?= $v['id'] ?>" class="btn-action-soft delete" title="Eliminar Venta Permanentemente" onclick="return confirm('⚠️ ¿Seguro de ELIMINAR esta venta?');">
                                                <i class="fas fa-trash-can"></i>
                                            </a>
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
<?php include 'partials/footer.php'; ?>
</body>
</html>
