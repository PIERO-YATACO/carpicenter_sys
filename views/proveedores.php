<?php
require_once __DIR__ . '/../auth/check_auth.php';
require_once '../config/db.php';

$page_title = 'Proveedores'; 
$page_subtitle = 'Directorio y gestión de proveedores de materiales e insumos'; 

// Eliminar (Solo Admin)
if(isset($_POST['delete_id'])) {
    if (!$is_admin) {
        header("Location: proveedores.php?error=" . urlencode("Acceso denegado: Solo los administradores pueden eliminar proveedores."));
        exit;
    }
    $del = (int)$_POST['delete_id'];
    try {
        $db->prepare("DELETE FROM proveedores WHERE id=?")->execute([$del]);
        header("Location: proveedores.php?msg=deleted");
        exit;
    } catch (Exception $e) {
        header("Location: proveedores.php?error=" . urlencode("No se puede eliminar este proveedor porque posee compras u órdenes vinculadas."));
        exit;
    }
}

// Paginación y búsqueda
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

$params = [];
$where = "";
if(!empty($search)) {
    $where = " WHERE nombre ILIKE ? OR ruc ILIKE ? OR rubro ILIKE ? ";
    $params = ["%$search%", "%$search%", "%$search%"];
}

// Total
$stmt_t = $db->prepare("SELECT COUNT(*) FROM proveedores $where");
$stmt_t->execute($params);
$total = $stmt_t->fetchColumn();
$total_pages = ceil($total / $limit);

// Fetch
$query = "SELECT * FROM proveedores $where ORDER BY nombre ASC LIMIT $limit OFFSET $offset";
$stmt = $db->prepare($query);
$stmt->execute($params);
$proveedores = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalActivos = $db->query("SELECT COUNT(*) FROM proveedores WHERE estado = 'Activo'")->fetchColumn();
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
        /* ===== PROVEEDORES PREMIUM ===== */
        .prv-hero {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.4rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .prv-hero-title h1 {
            font-size: 1.45rem;
            font-weight: 800;
            color: #111827;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .prv-hero-title p {
            color: #6B7280;
            font-size: 0.84rem;
            margin: 0.25rem 0 0 0;
        }

        /* KPIs */
        .prv-kpis-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.2rem;
            margin-bottom: 1.3rem;
        }
        .prv-kpi-card {
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
        .prv-kpi-card:hover {
            transform: translateY(-2px);
        }
        .prv-kpi-icon {
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
        .icon-emerald-bg { background: linear-gradient(135deg, rgba(5,150,105,0.12) 0%, rgba(16,185,129,0.2) 100%); color: #059669; }

        .prv-kpi-info span.label {
            font-size: 0.74rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
            color: #6B7280;
            display: block;
            margin-bottom: 0.15rem;
        }
        .prv-kpi-info h3 {
            font-size: 1.35rem;
            font-weight: 800;
            color: #111827;
            margin: 0;
            line-height: 1.2;
        }
        .prv-kpi-info span.sub-tag {
            font-size: 0.74rem;
            font-weight: 600;
            display: inline-block;
            margin-top: 0.25rem;
            padding: 2px 8px;
            border-radius: 20px;
        }

        /* Filter Panel */
        .prv-filter-card {
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
        .prv-search-box {
            position: relative;
            flex: 1;
            min-width: 250px;
            max-width: 450px;
        }
        .prv-search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #9CA3AF;
            font-size: 0.9rem;
        }
        .prv-input {
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
        .prv-input:focus {
            background: #FFFFFF;
            border-color: #E31E24;
            box-shadow: 0 0 0 3px rgba(227,30,36,0.1);
        }

        /* Table Card */
        .prv-table-card {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        }
        .prv-table-header-title {
            padding: 1rem 1.4rem;
            border-bottom: 1px solid #F3F4F6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .prv-table-header-title h3 {
            font-size: 0.95rem;
            font-weight: 700;
            color: #111827;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .prv-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        .prv-table th {
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
        .prv-table td {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid #F3F4F6;
            font-size: 0.85rem;
            color: #374151;
            vertical-align: middle;
            white-space: nowrap;
        }
        .prv-table tbody tr:hover {
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
        .rubro-pill {
            background: #F5F3FF;
            color: #7C3AED;
            border: 1px solid #DDD6FE;
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 0.74rem;
            font-weight: 700;
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
        .status-pill.activo { background: rgba(5,150,105,0.1); color: #059669; border: 1px solid rgba(5,150,105,0.25); }
        .status-pill.inactivo { background: rgba(217,119,6,0.1); color: #D97706; border: 1px solid rgba(217,119,6,0.25); }

        /* Actions */
        .prv-actions {
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
        .btn-action-soft.edit { background: rgba(100,116,139,0.1); color: #475569; }
        .btn-action-soft.edit:hover { background: #475569; color: #FFFFFF; }
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
            <div class="prv-hero">
                <div class="prv-hero-title">
                    <h1><i class="fas fa-truck" style="color:#E31E24;"></i> Proveedores</h1>
                    <p>Directorio comercial y gestión de proveedores de materiales e insumos</p>
                </div>
                <a href="proveedor_form.php" class="btn btn-primary" style="font-weight:600; padding:0.55rem 1.2rem; border-radius:10px; box-shadow:0 4px 12px rgba(227,30,36,0.25);">
                    <i class="fas fa-plus" style="margin-right:6px;"></i> Nuevo Proveedor
                </a>
            </div>

            <!-- Toast / Alertas -->
            <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
                <div style="background:#10B981; color:#fff; padding:0.85rem 1.25rem; border-radius:10px; margin-bottom:1.2rem; display:flex; justify-content:space-between; align-items:center; font-weight:600;">
                    <div><i class="fas fa-check-circle" style="margin-right:8px;"></i> Proveedor eliminado exitosamente.</div>
                    <button type="button" onclick="this.parentElement.remove()" style="background:none; border:none; color:#fff; font-size:1.2rem; cursor:pointer;">&times;</button>
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['error'])): ?>
                <div style="background:#EF4444; color:#fff; padding:0.85rem 1.25rem; border-radius:10px; margin-bottom:1.2rem; display:flex; justify-content:space-between; align-items:center; font-weight:600;">
                    <div><i class="fas fa-triangle-exclamation" style="margin-right:8px;"></i> <?= htmlspecialchars($_GET['error']) ?></div>
                    <button type="button" onclick="this.parentElement.remove()" style="background:none; border:none; color:#fff; font-size:1.2rem; cursor:pointer;">&times;</button>
                </div>
            <?php endif; ?>

            <!-- KPI Cards -->
            <div class="prv-kpis-grid">
                <div class="prv-kpi-card">
                    <div class="prv-kpi-icon icon-indigo-bg">
                        <i class="fas fa-building"></i>
                    </div>
                    <div class="prv-kpi-info">
                        <span class="label">Total Proveedores</span>
                        <h3 style="color:#4F46E5;"><?= $total ?></h3>
                        <span class="sub-tag" style="background:#EEF2FF; color:#4F46E5;">Empresas aliadas</span>
                    </div>
                </div>

                <div class="prv-kpi-card">
                    <div class="prv-kpi-icon icon-emerald-bg">
                        <i class="fas fa-circle-check"></i>
                    </div>
                    <div class="prv-kpi-info">
                        <span class="label">Proveedores Activos</span>
                        <h3 style="color:#059669;"><?= $totalActivos ?></h3>
                        <span class="sub-tag" style="background:#ECFDF5; color:#059669;">Cuentas comerciales</span>
                    </div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="prv-filter-card">
                <form method="GET" action="proveedores.php" style="display:flex; width:100%; gap:10px;">
                    <div class="prv-search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" class="prv-input" placeholder="Buscar por razón social, RUC o rubro..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <button type="submit" class="btn btn-primary" style="padding:0.55rem 1rem; border-radius:9px; font-weight:600;">
                        <i class="fas fa-filter"></i> Filtrar
                    </button>
                    <?php if(!empty($search)): ?>
                        <a href="proveedores.php" class="btn btn-outline" style="padding:0.55rem 0.85rem; border-radius:9px;" title="Limpiar Filtros">
                            <i class="fas fa-times"></i>
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Tabla de Proveedores -->
            <div class="prv-table-card">
                <div class="prv-table-header-title">
                    <h3><i class="fas fa-truck" style="color:#E31E24;"></i> Directorio de Empresas Proveedoras</h3>
                    <span style="font-size:0.8rem; color:#6B7280; font-weight:500;">
                        Mostrando <?= count($proveedores) ?> de <?= $total ?> proveedores
                    </span>
                </div>

                <div style="overflow-x:auto;">
                    <table class="prv-table">
                        <thead>
                            <tr>
                                <th>RUC</th>
                                <th>Razón Social</th>
                                <th>Rubro / Categoría</th>
                                <th>Contacto</th>
                                <th>Teléfono</th>
                                <th>Estado</th>
                                <th style="text-align:center;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($proveedores)): ?>
                                <tr>
                                    <td colspan="7" style="text-align:center; padding:3.5rem; color:#9CA3AF;">
                                        <i class="fas fa-truck" style="font-size:2.5rem; margin-bottom:0.8rem; opacity:0.35; display:block;"></i>
                                        No se encontraron proveedores registrados.
                                    </td>
                                </tr>
                            <?php else: foreach($proveedores as $p): 
                                $isActivo = ($p['estado'] === 'Activo');
                            ?>
                            <tr>
                                <td><span class="doc-badge"><?= htmlspecialchars($p['ruc'] ?: 'S/N') ?></span></td>
                                <td><strong style="color:#111827;"><?= htmlspecialchars($p['nombre']) ?></strong></td>
                                <td><span class="rubro-pill"><?= htmlspecialchars($p['rubro'] ?: 'General') ?></span></td>
                                <td style="font-size:0.83rem; color:#4B5563;">
                                    <i class="fas fa-user-circle" style="color:#9CA3AF; margin-right:3px;"></i>
                                    <?= htmlspecialchars($p['contacto'] ?: '—') ?>
                                </td>
                                <td style="font-size:0.83rem; color:#4B5563;">
                                    <?php if(!empty($p['telefono'])): ?>
                                        <i class="fas fa-phone" style="color:#9CA3AF; font-size:0.75rem; margin-right:3px;"></i>
                                        <?= htmlspecialchars($p['telefono']) ?>
                                    <?php else: ?>
                                        <span style="color:#9CA3AF;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-pill <?= $isActivo ? 'activo' : 'inactivo' ?>">
                                        <?= $isActivo ? 'ACTIVO' : 'INACTIVO' ?>
                                    </span>
                                </td>
                                <td style="text-align:center;">
                                    <div class="prv-actions">
                                        <a href="proveedor_form.php?id=<?= $p['id'] ?>" class="btn-action-soft edit" title="Editar Proveedor">
                                            <i class="fas fa-pen"></i>
                                        </a>
                                        <?php if ($is_admin): ?>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('⚠️ ¿Seguro que deseas ELIMINAR este proveedor?');">
                                                <input type="hidden" name="delete_id" value="<?= $p['id'] ?>">
                                                <button type="submit" class="btn-action-soft delete" title="Eliminar">
                                                    <i class="fas fa-trash-can"></i>
                                                </button>
                                            </form>
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
