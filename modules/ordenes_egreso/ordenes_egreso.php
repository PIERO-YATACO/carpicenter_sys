<?php
require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../config/db.php';

$search = $_GET['search'] ?? '';
$fecha_inicio = $_GET['fecha_inicio'] ?? '';
$fecha_fin = $_GET['fecha_fin'] ?? '';

$sql = "
    SELECT oe.*, l.nombre as local_origen_nombre_db
    FROM ordenes_egreso oe
    LEFT JOIN locales l ON oe.local_origen_id = l.id
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $sql .= " AND (oe.numero ILIKE :search OR oe.local_destino_nombre ILIKE :search OR oe.motivo_egreso ILIKE :search OR oe.recepcionado_nombre ILIKE :search OR oe.recepcionado_dni ILIKE :search)";
    $params[':search'] = '%' . $search . '%';
}
if (!empty($fecha_inicio)) {
    $sql .= " AND oe.fecha_emision >= :fecha_inicio";
    $params[':fecha_inicio'] = $fecha_inicio;
}
if (!empty($fecha_fin)) {
    $sql .= " AND oe.fecha_emision <= :fecha_fin";
    $params[':fecha_fin'] = $fecha_fin;
}

$sql .= " ORDER BY oe.id DESC";

try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $ordenes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_ordenes = count($ordenes);
    $firmados_count = 0;
    $pendientes_cargo = 0;
    foreach($ordenes as $o) {
        if (!empty($o['foto_documento_firmado'])) $firmados_count++;
        else $pendientes_cargo++;
    }
} catch (PDOException $e) {
    die("Error al consultar órdenes de egreso: " . $e->getMessage());
}

$page_title = 'Órdenes de Egreso';
$page_subtitle = 'Comprobantes de salida física de almacén y tienda (Descuento 100% Inventario)';
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
        /* ===== ÓRDENES DE EGRESO PREMIUM ===== */
        .oe-hero {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.4rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .oe-hero-title h1 {
            font-size: 1.45rem;
            font-weight: 800;
            color: #111827;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .oe-hero-title p {
            color: #6B7280;
            font-size: 0.84rem;
            margin: 0.25rem 0 0 0;
        }

        /* KPIs */
        .oe-kpis-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 1.2rem;
            margin-bottom: 1.3rem;
        }
        .oe-kpi-card {
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
        .oe-kpi-card:hover {
            transform: translateY(-2px);
        }
        .oe-kpi-icon {
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
        .icon-amber-bg { background: linear-gradient(135deg, rgba(217,119,6,0.12) 0%, rgba(245,158,11,0.2) 100%); color: #D97706; }

        .oe-kpi-info span.label {
            font-size: 0.74rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
            color: #6B7280;
            display: block;
            margin-bottom: 0.15rem;
        }
        .oe-kpi-info h3 {
            font-size: 1.35rem;
            font-weight: 800;
            color: #111827;
            margin: 0;
            line-height: 1.2;
        }
        .oe-kpi-info span.sub-tag {
            font-size: 0.74rem;
            font-weight: 600;
            display: inline-block;
            margin-top: 0.25rem;
            padding: 2px 8px;
            border-radius: 20px;
        }

        /* Filter Panel */
        .oe-filter-card {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 14px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.25rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        .oe-filter-form {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            align-items: center;
        }
        .oe-search-box {
            flex: 2;
            min-width: 240px;
            position: relative;
        }
        .oe-search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #9CA3AF;
            font-size: 0.9rem;
        }
        .oe-input {
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
        .oe-input:focus {
            background: #FFFFFF;
            border-color: #E31E24;
            box-shadow: 0 0 0 3px rgba(227,30,36,0.1);
        }
        .oe-control-sm {
            padding: 0.55rem 0.85rem;
            border-radius: 9px;
            border: 1px solid #D1D5DB;
            background: #F9FAFB;
            font-size: 0.84rem;
            color: #111827;
            outline: none;
        }

        /* Table Card */
        .oe-table-card {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        }
        .oe-table-header-title {
            padding: 1rem 1.4rem;
            border-bottom: 1px solid #F3F4F6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .oe-table-header-title h3 {
            font-size: 0.95rem;
            font-weight: 700;
            color: #111827;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .oe-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        .oe-table th {
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
        .oe-table td {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid #F3F4F6;
            font-size: 0.85rem;
            color: #374151;
            vertical-align: middle;
        }
        .oe-table tbody tr:hover {
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
        .motivo-pill {
            background: #F0F9FF;
            color: #0369A1;
            border: 1px solid #BAE6FD;
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 0.75rem;
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
        .status-pill.firmado { background: rgba(5,150,105,0.1); color: #059669; border: 1px solid rgba(5,150,105,0.25); }
        .status-pill.pendiente { background: rgba(217,119,6,0.1); color: #D97706; border: 1px solid rgba(217,119,6,0.25); }

        /* Actions */
        .oe-actions {
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
        .btn-action-soft.print { background: rgba(217,119,6,0.1); color: #D97706; }
        .btn-action-soft.print:hover { background: #D97706; color: #FFFFFF; }
        .btn-action-soft.view-doc { background: rgba(5,150,105,0.1); color: #059669; }
        .btn-action-soft.view-doc:hover { background: #059669; color: #FFFFFF; }
        .btn-action-soft.upload { background: rgba(79,70,229,0.1); color: #4F46E5; }
        .btn-action-soft.upload:hover { background: #4F46E5; color: #FFFFFF; }
        .btn-action-soft.void { background: rgba(220,38,38,0.08); color: #DC2626; }
        .btn-action-soft.void:hover { background: #DC2626; color: #FFFFFF; }
        .btn-action-soft.delete { background: rgba(220,38,38,0.08); color: #DC2626; }
        .btn-action-soft.delete:hover { background: #DC2626; color: #FFFFFF; }

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
            max-width: 480px;
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
            <div class="oe-hero">
                <div class="oe-hero-title">
                    <h1><i class="fas fa-boxes-packing" style="color:#E31E24;"></i> Órdenes de Egreso</h1>
                    <p>Despacho y salida física de mercadería de almacenes y tiendas</p>
                </div>
                <a href="egreso_nuevo.php" class="btn btn-primary" style="font-weight:600; padding:0.55rem 1.2rem; border-radius:10px; box-shadow:0 4px 12px rgba(227,30,36,0.25);">
                    <i class="fas fa-plus" style="margin-right:6px;"></i> Emitir Orden de Egreso
                </a>
            </div>

            <!-- Toast / Alertas -->
            <?php if (isset($_GET['msg']) && $_GET['msg'] === 'eliminado'): ?>
                <div style="background:#EF4444; color:#fff; padding:0.85rem 1.25rem; border-radius:10px; margin-bottom:1.2rem; display:flex; justify-content:space-between; align-items:center; font-weight:600;">
                    <div><i class="fas fa-trash-alt" style="margin-right:8px;"></i> Orden de Egreso eliminada permanentemente.</div>
                    <button type="button" onclick="this.parentElement.remove()" style="background:none; border:none; color:#fff; font-size:1.2rem; cursor:pointer;">&times;</button>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['voided']) && $_GET['voided'] == '1'): ?>
                <div style="background:#F59E0B; color:#fff; padding:0.85rem 1.25rem; border-radius:10px; margin-bottom:1.2rem; display:flex; justify-content:space-between; align-items:center; font-weight:600;">
                    <div><i class="fas fa-ban" style="margin-right:8px;"></i> Orden anulada y stock físico restaurado al inventario.</div>
                    <button type="button" onclick="this.parentElement.remove()" style="background:none; border:none; color:#fff; font-size:1.2rem; cursor:pointer;">&times;</button>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
                <div style="background:#10B981; color:#fff; padding:0.85rem 1.25rem; border-radius:10px; margin-bottom:1.2rem; display:flex; justify-content:space-between; align-items:center; font-weight:600;">
                    <div><i class="fas fa-check-circle" style="margin-right:8px;"></i> Orden de Egreso registrada con éxito y stock descontado.</div>
                    <button type="button" onclick="this.parentElement.remove()" style="background:none; border:none; color:#fff; font-size:1.2rem; cursor:pointer;">&times;</button>
                </div>
            <?php endif; ?>

            <!-- KPI Cards -->
            <div class="oe-kpis-grid">
                <div class="oe-kpi-card">
                    <div class="oe-kpi-icon icon-indigo-bg">
                        <i class="fas fa-boxes-packing"></i>
                    </div>
                    <div class="oe-kpi-info">
                        <span class="label">Total Órdenes</span>
                        <h3 style="color:#4F46E5;"><?= $total_ordenes ?></h3>
                        <span class="sub-tag" style="background:#EEF2FF; color:#4F46E5;">Despachos físicos</span>
                    </div>
                </div>

                <div class="oe-kpi-card">
                    <div class="oe-kpi-icon icon-emerald-bg">
                        <i class="fas fa-file-circle-check"></i>
                    </div>
                    <div class="oe-kpi-info">
                        <span class="label">Cargos Archivados</span>
                        <h3 style="color:#059669;"><?= $firmados_count ?></h3>
                        <span class="sub-tag" style="background:#ECFDF5; color:#059669;">Con firma de recepción</span>
                    </div>
                </div>

                <div class="oe-kpi-card">
                    <div class="oe-kpi-icon icon-amber-bg">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="oe-kpi-info">
                        <span class="label">Pendiente Cargo</span>
                        <h3 style="color:#D97706;"><?= $pendientes_cargo ?></h3>
                        <span class="sub-tag" style="background:#FFFBEB; color:#D97706;">Por digitalizar</span>
                    </div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="oe-filter-card">
                <form method="GET" action="ordenes_egreso.php" class="oe-filter-form">
                    <div class="oe-search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" class="oe-input" placeholder="Buscar por N° orden, destino, motivo o recepciona..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <input type="date" name="fecha_inicio" class="oe-control-sm" value="<?= htmlspecialchars($fecha_inicio) ?>" title="Fecha Inicio">
                    <input type="date" name="fecha_fin" class="oe-control-sm" value="<?= htmlspecialchars($fecha_fin) ?>" title="Fecha Fin">
                    <button type="submit" class="btn btn-primary" style="padding:0.55rem 1rem; border-radius:9px; font-weight:600;">
                        <i class="fas fa-filter"></i> Filtrar
                    </button>
                    <?php if(!empty($search) || !empty($fecha_inicio) || !empty($fecha_fin)): ?>
                        <a href="ordenes_egreso.php" class="btn btn-outline" style="padding:0.55rem 0.85rem; border-radius:9px;" title="Limpiar Filtros">
                            <i class="fas fa-times"></i>
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Tabla de Órdenes -->
            <div class="oe-table-card">
                <div class="oe-table-header-title">
                    <h3><i class="fas fa-boxes-packing" style="color:#E31E24;"></i> Registro de Salidas Físicas</h3>
                    <span style="font-size:0.8rem; color:#6B7280; font-weight:500;">
                        Mostrando <?= count($ordenes) ?> órdenes de egreso
                    </span>
                </div>

                <div style="overflow-x:auto;">
                    <table class="oe-table">
                        <thead>
                            <tr>
                                <th>N° Orden</th>
                                <th>Emisión</th>
                                <th>Local Origen</th>
                                <th>Destino</th>
                                <th>Motivo Egreso</th>
                                <th>Recepcionado Por</th>
                                <th>Doc. Firmado</th>
                                <th style="text-align:center;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($ordenes)): ?>
                                <tr>
                                    <td colspan="8" style="text-align:center; padding:3.5rem; color:#9CA3AF;">
                                        <i class="fas fa-boxes-packing" style="font-size:2.5rem; margin-bottom:0.8rem; opacity:0.35; display:block;"></i>
                                        No se encontraron órdenes de egreso registradas.
                                    </td>
                                </tr>
                            <?php else: foreach($ordenes as $o): 
                                $hasSignedDoc = !empty($o['foto_documento_firmado']);
                            ?>
                            <tr>
                                <td><span class="doc-badge"><?= htmlspecialchars($o['numero']) ?></span></td>
                                <td style="font-size:0.83rem;">
                                    <?= date('d/m/Y', strtotime($o['fecha_emision'])) ?>
                                    <div style="font-size:0.75rem; color:#6B7280;"><?= htmlspecialchars($o['hora_emision'] ?? '') ?></div>
                                </td>
                                <td><strong style="color:#111827;"><?= htmlspecialchars($o['local_origen_nombre'] ?? $o['local_origen_nombre_db'] ?? 'Almacén Central') ?></strong></td>
                                <td><?= htmlspecialchars($o['local_destino_nombre'] ?: '—') ?></td>
                                <td><span class="motivo-pill"><?= htmlspecialchars($o['motivo_egreso']) ?></span></td>
                                <td>
                                    <strong style="color:#111827;"><?= htmlspecialchars($o['recepcionado_nombre'] ?: '—') ?></strong>
                                    <?php if(!empty($o['recepcionado_dni'])): ?>
                                        <div style="font-size:0.75rem; color:#6B7280; margin-top:2px;">DNI: <?= htmlspecialchars($o['recepcionado_dni']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($hasSignedDoc): ?>
                                        <span class="status-pill firmado"><i class="fas fa-circle-check"></i> Firmado</span>
                                    <?php else: ?>
                                        <span class="status-pill pendiente"><i class="fas fa-clock"></i> Pendiente</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;">
                                    <div class="oe-actions">
                                        <a href="egreso_print.php?id=<?= $o['id'] ?>" target="_blank" class="btn-action-soft print" title="Imprimir Orden">
                                            <i class="fas fa-print"></i>
                                        </a>
                                        <?php if ($hasSignedDoc): ?>
                                            <a href="<?= htmlspecialchars($o['foto_documento_firmado']) ?>" target="_blank" class="btn-action-soft view-doc" title="Ver Documento Firmado">
                                                <i class="fas fa-file-image"></i>
                                            </a>
                                        <?php endif; ?>
                                        <button type="button" class="btn-action-soft upload" onclick="openUploadModal(<?= $o['id'] ?>, '<?= htmlspecialchars($o['numero']) ?>')" title="Adjuntar Cargo Firmado">
                                            <i class="fas fa-upload"></i>
                                        </button>
                                        <?php if ($is_admin): ?>
                                            <?php if ($o['estado'] !== 'Anulada'): ?>
                                                <a href="egreso_void.php?id=<?= $o['id'] ?>" class="btn-action-soft void" title="Anular Orden (Restaurar Stock)" onclick="return confirm('¿Seguro de anular la Orden N° <?= htmlspecialchars($o['numero']) ?>? El stock se restaurará.');">
                                                    <i class="fas fa-ban"></i>
                                                </a>
                                            <?php endif; ?>
                                            <a href="egreso_delete.php?id=<?= $o['id'] ?>" class="btn-action-soft delete" title="Eliminar Permanentemente" onclick="return confirm('⚠️ ¿Seguro de ELIMINAR esta Orden de Egreso?');">
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

<!-- Modal para subir foto de orden firmada -->
<div id="uploadModal" class="modal-overlay">
    <div class="modal-box">
        <div style="padding:1.25rem 1.5rem; border-bottom:1px solid #E5E7EB; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:1.1rem; font-weight:800; color:#111827;">
                <i class="fas fa-camera" style="color:#4F46E5; margin-right:6px;"></i> Archivar Orden Firmada
            </h3>
            <button type="button" onclick="closeUploadModal()" style="background:none; border:none; font-size:1.2rem; cursor:pointer;">&times;</button>
        </div>
        <form action="egreso_upload_firmado.php" method="POST" enctype="multipart/form-data">
            <div style="padding:1.4rem;">
                <input type="hidden" name="id" id="modalOrdenId">
                <p style="font-size:0.85rem; color:#4B5563; margin-top:0; margin-bottom:1rem;">
                    Sube una foto o PDF de la Orden de Egreso <strong id="modalOrdenNum" style="color:#111827;"></strong> firmada con conformidad.
                </p>
                <div class="form-group">
                    <label style="display:block; font-size:0.76rem; font-weight:700; text-transform:uppercase; color:#4B5563; margin-bottom:0.35rem;">Archivo del Cargo Firmado</label>
                    <input type="file" name="foto_firmada" accept="image/*,.pdf" class="oe-input" required style="padding:0.4rem;">
                </div>
            </div>
            <div style="padding:1rem 1.4rem; background:#F9FAFB; border-top:1px solid #E5E7EB; display:flex; justify-content:flex-end; gap:0.75rem;">
                <button type="button" class="btn btn-outline" onclick="closeUploadModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Guardar y Archivar</button>
            </div>
        </form>
    </div>
</div>

<script>
function openUploadModal(id, num) {
    document.getElementById('modalOrdenId').value = id;
    document.getElementById('modalOrdenNum').textContent = num;
    document.getElementById('uploadModal').classList.add('open');
}
function closeUploadModal() {
    document.getElementById('uploadModal').classList.remove('open');
}
</script>

<?php include '../../views/partials/footer.php'; ?>
</body>
</html>
