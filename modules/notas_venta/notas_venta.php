<?php
require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../config/db.php';

// Filtros y parámetros
$search = $_GET['search'] ?? '';
$fecha_inicio = $_GET['fecha_inicio'] ?? '';
$fecha_fin = $_GET['fecha_fin'] ?? '';
$estado = $_GET['estado'] ?? 'Todos';

// Construir consulta dinámica
$sql = "
    SELECT nv.* 
    FROM notas_venta nv
    WHERE 1=1
";
$params = [];

$isSeller = in_array(strtolower($user_role ?? ''), ['vendedor', 'vendedora']);
$userId = intval($_SESSION['user_id'] ?? 0);
$userName = trim($_SESSION['nombre_completo'] ?? '');
$userUsername = trim($_SESSION['username'] ?? '');

$filterVendedor = $_GET['vendedor'] ?? '';
$filterLocal = $_GET['local_id'] ?? '';

// Filtro de privacidad estricta: Vendedoras solo ven sus propias notas de venta
if ($isSeller) {
    $sql .= " AND (nv.usuario_id = :uid_seller OR nv.vendedor ILIKE :vname_seller OR nv.vendedor ILIKE :vuser_seller)";
    $params[':uid_seller'] = $userId;
    $params[':vname_seller'] = "%$userName%";
    $params[':vuser_seller'] = "%$userUsername%";
} else {
    if (!empty($filterVendedor)) {
        $sql .= " AND nv.vendedor ILIKE :f_vend";
        $params[':f_vend'] = "%$filterVendedor%";
    }
    if (!empty($filterLocal)) {
        $sql .= " AND nv.local_id = :f_loc";
        $params[':f_loc'] = intval($filterLocal);
    }
}

if (!empty($search)) {
    $sql .= " AND (nv.cliente_nombre ILIKE :search OR nv.numero ILIKE :search OR nv.vendedor ILIKE :search OR nv.cliente_documento ILIKE :search)";
    $params[':search'] = '%' . $search . '%';
}
if (!empty($fecha_inicio)) {
    $sql .= " AND nv.fecha >= :fecha_inicio";
    $params[':fecha_inicio'] = $fecha_inicio;
}
if (!empty($fecha_fin)) {
    $sql .= " AND nv.fecha <= :fecha_fin";
    $params[':fecha_fin'] = $fecha_fin;
}
if ($estado !== 'Todos') {
    $sql .= " AND nv.estado = :estado";
    $params[':estado'] = $estado;
}

$sql .= " ORDER BY nv.fecha DESC, nv.numero DESC";

try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $notas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calcular estadísticas dinámicas por alcance de usuario
    if ($isSeller) {
        $stmtStTot = $db->prepare("SELECT COALESCE(SUM(total), 0) FROM notas_venta WHERE estado = 'Activa' AND (usuario_id = :uid OR vendedor ILIKE :vn OR vendedor ILIKE :vu)");
        $stmtStTot->execute([':uid' => $userId, ':vn' => "%$userName%", ':vu' => "%$userUsername%"]);
        $stats_total = floatval($stmtStTot->fetchColumn());

        $stmtStAct = $db->prepare("SELECT COUNT(*) FROM notas_venta WHERE estado = 'Activa' AND (usuario_id = :uid OR vendedor ILIKE :vn OR vendedor ILIKE :vu)");
        $stmtStAct->execute([':uid' => $userId, ':vn' => "%$userName%", ':vu' => "%$userUsername%"]);
        $stats_cant_activas = intval($stmtStAct->fetchColumn());

        $stmtStAnu = $db->prepare("SELECT COUNT(*) FROM notas_venta WHERE estado = 'Anulada' AND (usuario_id = :uid OR vendedor ILIKE :vn OR vendedor ILIKE :vu)");
        $stmtStAnu->execute([':uid' => $userId, ':vn' => "%$userName%", ':vu' => "%$userUsername%"]);
        $stats_cant_anuladas = intval($stmtStAnu->fetchColumn());
    } else {
        $stats_total = floatval($db->query("SELECT COALESCE(SUM(total), 0) FROM notas_venta WHERE estado = 'Activa'")->fetchColumn());
        $stats_cant_activas = intval($db->query("SELECT COUNT(*) FROM notas_venta WHERE estado = 'Activa'")->fetchColumn());
        $stats_cant_anuladas = intval($db->query("SELECT COUNT(*) FROM notas_venta WHERE estado = 'Anulada'")->fetchColumn());
    }

} catch (PDOException $e) {
    die("Error en base de datos: " . $e->getMessage());
}

$page_title = 'Notas de Venta';
$page_subtitle = 'Historial y registro interno de comprobantes de venta libre en tienda';
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
        /* ===== NOTAS DE VENTA PREMIUM ===== */
        .nv-hero {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.4rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .nv-hero-title h1 {
            font-size: 1.45rem;
            font-weight: 800;
            color: #111827;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .nv-hero-title p {
            color: #6B7280;
            font-size: 0.84rem;
            margin: 0.25rem 0 0 0;
        }

        /* KPIs */
        .nv-kpis-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 1.2rem;
            margin-bottom: 1.3rem;
        }
        .nv-kpi-card {
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
        .nv-kpi-card:hover {
            transform: translateY(-2px);
        }
        .nv-kpi-icon {
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
        .icon-blue-bg { background: linear-gradient(135deg, rgba(37,99,235,0.12) 0%, rgba(59,130,246,0.2) 100%); color: #2563EB; }
        .icon-rose-bg { background: linear-gradient(135deg, rgba(220,38,38,0.12) 0%, rgba(239,68,68,0.2) 100%); color: #DC2626; }

        .nv-kpi-info span.label {
            font-size: 0.74rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
            color: #6B7280;
            display: block;
            margin-bottom: 0.15rem;
        }
        .nv-kpi-info h3 {
            font-size: 1.35rem;
            font-weight: 800;
            color: #111827;
            margin: 0;
            line-height: 1.2;
        }
        .nv-kpi-info span.sub-tag {
            font-size: 0.74rem;
            font-weight: 600;
            display: inline-block;
            margin-top: 0.25rem;
            padding: 2px 8px;
            border-radius: 20px;
        }

        /* Filter Panel */
        .nv-filter-card {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 14px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.25rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        .nv-filter-form {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            align-items: center;
        }
        .nv-search-box {
            flex: 2;
            min-width: 240px;
            position: relative;
        }
        .nv-search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #9CA3AF;
            font-size: 0.9rem;
        }
        .nv-input {
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
        .nv-input:focus {
            background: #FFFFFF;
            border-color: #E31E24;
            box-shadow: 0 0 0 3px rgba(227,30,36,0.1);
        }
        .nv-control-sm {
            padding: 0.55rem 0.85rem;
            border-radius: 9px;
            border: 1px solid #D1D5DB;
            background: #F9FAFB;
            font-size: 0.84rem;
            color: #111827;
            outline: none;
        }

        /* Table Card */
        .nv-table-card {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        }
        .nv-table-header-title {
            padding: 1rem 1.4rem;
            border-bottom: 1px solid #F3F4F6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .nv-table-header-title h3 {
            font-size: 0.95rem;
            font-weight: 700;
            color: #111827;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .nv-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        .nv-table th {
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
        .nv-table td {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid #F3F4F6;
            font-size: 0.85rem;
            color: #374151;
            vertical-align: middle;
            white-space: nowrap;
        }
        .nv-table tbody tr:hover {
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
        .pay-pill {
            background: #F8FAFC;
            color: #475569;
            border: 1px solid #E2E8F0;
            padding: 2px 7px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
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
        .status-pill.activa { background: rgba(5,150,105,0.1); color: #059669; border: 1px solid rgba(5,150,105,0.25); }
        .status-pill.anulada { background: rgba(220,38,38,0.1); color: #DC2626; border: 1px solid rgba(220,38,38,0.25); }

        /* Action Buttons */
        .nv-actions {
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
        .btn-action-soft.print { background: rgba(217,119,6,0.1); color: #D97706; }
        .btn-action-soft.print:hover { background: #D97706; color: #FFFFFF; }
        .btn-action-soft.edit { background: rgba(100,116,139,0.1); color: #475569; }
        .btn-action-soft.edit:hover { background: #475569; color: #FFFFFF; }
        .btn-action-soft.void { background: rgba(234,179,8,0.15); color: #B45309; }
        .btn-action-soft.void:hover { background: #F59E0B; color: #FFFFFF; }
        .btn-action-soft.delete { background: rgba(220,38,38,0.08); color: #DC2626; }
        .btn-action-soft.delete:hover { background: #DC2626; color: #FFFFFF; }
    </style>
</head>
<body>
<div class="app-wrapper">
    <?php include '../../views/partials/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../views/partials/header.php'; ?>
        <div class="page-content">

            <!-- Header de la Página -->
            <div class="nv-hero">
                <div class="nv-hero-title">
                    <h1><i class="fas fa-receipt" style="color:#E31E24;"></i> Notas de Venta</h1>
                    <p>Historial y registro de comprobantes de venta libre en tienda / mostrador</p>
                </div>
                <a href="nota_nueva.php" class="btn btn-primary" style="font-weight:600; padding:0.55rem 1.2rem; border-radius:10px; box-shadow:0 4px 12px rgba(227,30,36,0.25);">
                    <i class="fas fa-plus" style="margin-right:6px;"></i> Nueva Nota de Venta
                </a>
            </div>

            <!-- Toast / Alertas -->
            <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
                <div style="background:#10B981; color:#fff; padding:0.85rem 1.25rem; border-radius:10px; margin-bottom:1.2rem; display:flex; justify-content:space-between; align-items:center; font-weight:600;">
                    <div><i class="fas fa-check-circle" style="margin-right:8px;"></i> Nota de Venta registrada exitosamente.</div>
                    <button type="button" onclick="this.parentElement.remove()" style="background:none; border:none; color:#fff; font-size:1.2rem; cursor:pointer;">&times;</button>
                </div>
            <?php endif; ?>

            <!-- KPI Cards -->
            <div class="nv-kpis-grid">
                <div class="nv-kpi-card">
                    <div class="nv-kpi-icon icon-emerald-bg">
                        <i class="fas fa-coins"></i>
                    </div>
                    <div class="nv-kpi-info">
                        <span class="label">Total Recaudado Activo</span>
                        <h3 style="color:#059669;">S/ <?= number_format($stats_total, 2) ?></h3>
                        <span class="sub-tag" style="background:#ECFDF5; color:#059669;">Venta neta</span>
                    </div>
                </div>

                <div class="nv-kpi-card">
                    <div class="nv-kpi-icon icon-blue-bg">
                        <i class="fas fa-circle-check"></i>
                    </div>
                    <div class="nv-kpi-info">
                        <span class="label">Notas Activas</span>
                        <h3 style="color:#2563EB;"><?= number_format($stats_cant_activas) ?></h3>
                        <span class="sub-tag" style="background:#EFF6FF; color:#2563EB;">Comprobantes válidos</span>
                    </div>
                </div>

                <div class="nv-kpi-card">
                    <div class="nv-kpi-icon icon-rose-bg">
                        <i class="fas fa-ban"></i>
                    </div>
                    <div class="nv-kpi-info">
                        <span class="label">Notas Anuladas</span>
                        <h3 style="color:#DC2626;"><?= number_format($stats_cant_anuladas) ?></h3>
                        <span class="sub-tag" style="background:#FEF2F2; color:#DC2626;">Sin efecto fiscal/comercial</span>
                    </div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="nv-filter-card">
                <form method="GET" action="notas_venta.php" class="nv-filter-form">
                    <div class="nv-search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" class="nv-input" placeholder="Buscar por cliente, documento, vendedor o N°..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <input type="date" name="fecha_inicio" class="nv-control-sm" value="<?= htmlspecialchars($fecha_inicio) ?>" title="Fecha Inicio">
                    <input type="date" name="fecha_fin" class="nv-control-sm" value="<?= htmlspecialchars($fecha_fin) ?>" title="Fecha Fin">
                    <select name="estado" class="nv-control-sm">
                        <option value="Todos" <?= $estado == 'Todos' ? 'selected' : '' ?>>Todos los estados</option>
                        <option value="Activa" <?= $estado == 'Activa' ? 'selected' : '' ?>>Activa</option>
                        <option value="Anulada" <?= $estado == 'Anulada' ? 'selected' : '' ?>>Anulada</option>
                    </select>
                    <button type="submit" class="btn btn-primary" style="padding:0.55rem 1rem; border-radius:9px; font-weight:600;">
                        <i class="fas fa-filter"></i> Filtrar
                    </button>
                    <?php if(!empty($search) || !empty($fecha_inicio) || !empty($fecha_fin) || $estado !== 'Todos'): ?>
                        <a href="notas_venta.php" class="btn btn-outline" style="padding:0.55rem 0.85rem; border-radius:9px;" title="Limpiar Filtros">
                            <i class="fas fa-times"></i>
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Tabla de Notas de Venta -->
            <div class="nv-table-card">
                <div class="nv-table-header-title">
                    <h3><i class="fas fa-receipt" style="color:#E31E24;"></i> Listado de Comprobantes</h3>
                    <span style="font-size:0.8rem; color:#6B7280; font-weight:500;">
                        Mostrando <?= count($notas) ?> notas registradas
                    </span>
                </div>

                <div style="overflow-x:auto;">
                    <table class="nv-table">
                        <thead>
                            <tr>
                                <th>Número</th>
                                <th>Cliente</th>
                                <th>Fecha</th>
                                <th>Método Pago</th>
                                <th>Vendedor</th>
                                <th style="text-align:right;">Total (S/)</th>
                                <th>Estado</th>
                                <th style="text-align:center;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($notas)): ?>
                                <tr>
                                    <td colspan="8" style="text-align:center; padding:3.5rem; color:#9CA3AF;">
                                        <i class="fas fa-receipt" style="font-size:2.5rem; margin-bottom:0.8rem; opacity:0.35; display:block;"></i>
                                        No se encontraron notas de venta con los filtros aplicados.
                                    </td>
                                </tr>
                            <?php else: foreach($notas as $n): 
                                $fecha_formato = date('d/m/Y', strtotime($n['fecha']));
                                $is_active = ($n['estado'] === 'Activa');
                            ?>
                            <tr style="<?= !$is_active ? 'opacity: 0.65; background:#FFFBFB;' : '' ?>">
                                <td><span class="doc-badge"><?= htmlspecialchars($n['numero']) ?></span></td>
                                <td>
                                    <strong style="color:#111827;"><?= htmlspecialchars($n['cliente_nombre']) ?></strong>
                                    <?php if(!empty($n['cliente_documento'])): ?>
                                        <div style="font-size:0.75rem; color:#6B7280; margin-top:2px;">
                                            <i class="fas fa-id-card"></i> <?= htmlspecialchars($n['cliente_documento']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:0.83rem;"><?= htmlspecialchars($fecha_formato) ?></td>
                                <td><span class="pay-pill"><?= htmlspecialchars($n['metodo_pago'] ?: 'EFECTIVO') ?></span></td>
                                <td style="font-size:0.83rem; color:#4B5563;"><?= htmlspecialchars($n['vendedor'] ?: 'Mostrador') ?></td>
                                <td style="text-align:right; font-weight:800; color:<?= $is_active ? '#111827' : '#DC2626' ?>; font-size:0.92rem;">
                                    S/ <?= number_format($n['total'], 2) ?>
                                </td>
                                <td>
                                    <span class="status-pill <?= $is_active ? 'activa' : 'anulada' ?>">
                                        <?= htmlspecialchars($n['estado']) ?>
                                    </span>
                                </td>
                                <td style="text-align:center;">
                                    <div class="nv-actions">
                                        <a href="nota_view.php?id=<?= $n['id'] ?>" class="btn-action-soft view" title="Ver Detalles">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="nota_print.php?id=<?= $n['id'] ?>" target="_blank" class="btn-action-soft print" title="Imprimir Comprobante">
                                            <i class="fas fa-print"></i>
                                        </a>
                                        <?php if ($is_admin): ?>
                                            <?php if ($is_active): ?>
                                                <a href="nota_editar.php?id=<?= $n['id'] ?>" class="btn-action-soft edit" title="Editar Nota">
                                                    <i class="fas fa-pen"></i>
                                                </a>
                                                <a href="nota_void.php?id=<?= $n['id'] ?>" class="btn-action-soft void" title="Anular Nota" onclick="return confirm('¿Seguro que deseas anular esta nota de venta?');">
                                                    <i class="fas fa-ban"></i>
                                                </a>
                                            <?php endif; ?>
                                            <a href="nota_delete.php?id=<?= $n['id'] ?>" class="btn-action-soft delete" title="Eliminar Permanentemente" onclick="return confirm('⚠️ ¿Seguro que deseas ELIMINAR PERMANENTEMENTE esta nota de venta?');">
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
<?php include '../../views/partials/footer.php'; ?>
</body>
</html>
