<?php
require_once __DIR__ . '/../auth/check_auth.php';
require_once __DIR__ . '/../config/db.php';

// Filtros y parámetros
$search = $_GET['search'] ?? '';
$estado_facturacion = $_GET['estado_facturacion'] ?? '';

// Construir consulta dinámica
$sql = "
    SELECT g.*, v.tipo_comprobante as venta_tipo, v.serie as venta_serie, v.numero as venta_numero
    FROM guias_remision g
    LEFT JOIN ventas v ON g.venta_id = v.id
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $sql .= " AND (g.destinatario_nombre ILIKE :search OR g.codigo ILIKE :search OR g.destinatario_documento ILIKE :search)";
    $params[':search'] = '%' . $search . '%';
}
if (!empty($estado_facturacion) && $estado_facturacion !== 'Todos') {
    $sql .= " AND g.estado_facturacion = :estado_facturacion";
    $params[':estado_facturacion'] = $estado_facturacion;
}

$sql .= " ORDER BY g.fecha_emision DESC";

try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $guias = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Estadísticas
    $stats_total = count($guias);
    $stats_facturadas = intval($db->query("SELECT COUNT(*) FROM guias_remision WHERE estado_facturacion = 'FACTURADA'")->fetchColumn());
    $stats_no_facturadas = intval($db->query("SELECT COUNT(*) FROM guias_remision WHERE estado_facturacion = 'NO_FACTURADA'")->fetchColumn());

} catch (PDOException $e) {
    die("Error en base de datos: " . $e->getMessage());
}

$page_title = 'Guías de Remisión';
$page_subtitle = 'Gestión de traslados, fletes y evidencias de entrega con cargo firmado';
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
        /* ===== GUÍAS DE REMISIÓN PREMIUM ===== */
        .gr-hero {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.4rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .gr-hero-title h1 {
            font-size: 1.45rem;
            font-weight: 800;
            color: #111827;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .gr-hero-title p {
            color: #6B7280;
            font-size: 0.84rem;
            margin: 0.25rem 0 0 0;
        }

        /* KPIs */
        .gr-kpis-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 1.2rem;
            margin-bottom: 1.3rem;
        }
        .gr-kpi-card {
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
        .gr-kpi-card:hover {
            transform: translateY(-2px);
        }
        .gr-kpi-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }
        .icon-blue-bg { background: linear-gradient(135deg, rgba(37,99,235,0.12) 0%, rgba(59,130,246,0.2) 100%); color: #2563EB; }
        .icon-emerald-bg { background: linear-gradient(135deg, rgba(5,150,105,0.12) 0%, rgba(16,185,129,0.2) 100%); color: #059669; }
        .icon-amber-bg { background: linear-gradient(135deg, rgba(217,119,6,0.12) 0%, rgba(245,158,11,0.2) 100%); color: #D97706; }

        .gr-kpi-info span.label {
            font-size: 0.74rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
            color: #6B7280;
            display: block;
            margin-bottom: 0.15rem;
        }
        .gr-kpi-info h3 {
            font-size: 1.35rem;
            font-weight: 800;
            color: #111827;
            margin: 0;
            line-height: 1.2;
        }
        .gr-kpi-info span.sub-tag {
            font-size: 0.74rem;
            font-weight: 600;
            display: inline-block;
            margin-top: 0.25rem;
            padding: 2px 8px;
            border-radius: 20px;
        }

        /* Filter Panel */
        .gr-filter-card {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 14px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.25rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        .gr-filter-form {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            align-items: center;
        }
        .gr-search-box {
            flex: 2;
            min-width: 240px;
            position: relative;
        }
        .gr-search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #9CA3AF;
            font-size: 0.9rem;
        }
        .gr-input {
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
        .gr-input:focus {
            background: #FFFFFF;
            border-color: #E31E24;
            box-shadow: 0 0 0 3px rgba(227,30,36,0.1);
        }
        .gr-select {
            padding: 0.55rem 2rem 0.55rem 0.85rem;
            border-radius: 9px;
            border: 1px solid #D1D5DB;
            background: #F9FAFB;
            font-size: 0.84rem;
            color: #111827;
            outline: none;
            min-width: 200px;
        }

        /* Table Card */
        .gr-table-card {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        }
        .gr-table-header-title {
            padding: 1rem 1.4rem;
            border-bottom: 1px solid #F3F4F6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .gr-table-header-title h3 {
            font-size: 0.95rem;
            font-weight: 700;
            color: #111827;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .gr-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        .gr-table th {
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
        .gr-table td {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid #F3F4F6;
            font-size: 0.85rem;
            color: #374151;
            vertical-align: middle;
        }
        .gr-table tbody tr:hover {
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
        .status-pill.entregado, .status-pill.facturada { background: rgba(5,150,105,0.1); color: #059669; border: 1px solid rgba(5,150,105,0.25); }
        .status-pill.en-transito { background: rgba(37,99,235,0.1); color: #2563EB; border: 1px solid rgba(37,99,235,0.25); }
        .status-pill.pendiente, .status-pill.no_facturada { background: rgba(217,119,6,0.1); color: #D97706; border: 1px solid rgba(217,119,6,0.25); }

        /* Actions */
        .gr-actions {
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
        .btn-action-soft.cargo { background: rgba(124,58,237,0.08); color: #7C3AED; }
        .btn-action-soft.cargo:hover { background: #7C3AED; color: #FFFFFF; }
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
            max-width: 520px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            animation: modalFadeIn 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            overflow: hidden;
        }
        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(8px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .upload-area-sys {
            border: 2px dashed #D1D5DB;
            border-radius: 10px;
            padding: 1.2rem;
            text-align: center;
            cursor: pointer;
            background: #F9FAFB;
            transition: all 0.2s;
        }
        .upload-area-sys:hover {
            border-color: #E31E24;
            background: rgba(227,30,36,0.04);
        }
    </style>
</head>
<body>
<div class="app-wrapper">
    <?php include 'partials/sidebar.php'; ?>
    <div class="main-content">
        <?php include 'partials/header.php'; ?>
        <div class="page-content">

            <!-- Header de la Página -->
            <div class="gr-hero">
                <div class="gr-hero-title">
                    <h1><i class="fas fa-truck" style="color:#E31E24;"></i> Guías de Remisión</h1>
                    <p>Traslados de mercadería, despachos y control de cargos firmados</p>
                </div>
                <a href="guia_nueva.php" class="btn btn-primary" style="font-weight:600; padding:0.55rem 1.2rem; border-radius:10px; box-shadow:0 4px 12px rgba(227,30,36,0.25);">
                    <i class="fas fa-plus" style="margin-right:6px;"></i> Nueva Guía
                </a>
            </div>

            <!-- Toast / Alertas -->
            <?php if (isset($_GET['msg']) && $_GET['msg'] === 'eliminado'): ?>
                <div style="background:#EF4444; color:#fff; padding:0.85rem 1.25rem; border-radius:10px; margin-bottom:1.2rem; display:flex; justify-content:space-between; align-items:center; font-weight:600;">
                    <div><i class="fas fa-trash-alt" style="margin-right:8px;"></i> Guía de Remisión eliminada del sistema.</div>
                    <button type="button" onclick="this.parentElement.remove()" style="background:none; border:none; color:#fff; font-size:1.2rem; cursor:pointer;">&times;</button>
                </div>
            <?php endif; ?>

            <!-- KPI Cards -->
            <div class="gr-kpis-grid">
                <div class="gr-kpi-card">
                    <div class="gr-kpi-icon icon-blue-bg">
                        <i class="fas fa-truck"></i>
                    </div>
                    <div class="gr-kpi-info">
                        <span class="label">Total Guías</span>
                        <h3 style="color:#2563EB;"><?= $stats_total ?></h3>
                        <span class="sub-tag" style="background:#EFF6FF; color:#2563EB;">Traslados emitidos</span>
                    </div>
                </div>

                <div class="gr-kpi-card">
                    <div class="gr-kpi-icon icon-emerald-bg">
                        <i class="fas fa-check-double"></i>
                    </div>
                    <div class="gr-kpi-info">
                        <span class="label">Guías Facturadas</span>
                        <h3 style="color:#059669;"><?= $stats_facturadas ?></h3>
                        <span class="sub-tag" style="background:#ECFDF5; color:#059669;">Con comprobante</span>
                    </div>
                </div>

                <div class="gr-kpi-card">
                    <div class="gr-kpi-icon icon-amber-bg">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="gr-kpi-info">
                        <span class="label">Por Facturar</span>
                        <h3 style="color:#D97706;"><?= $stats_no_facturadas ?></h3>
                        <span class="sub-tag" style="background:#FFFBEB; color:#D97706;">Pendientes de venta</span>
                    </div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="gr-filter-card">
                <form method="GET" action="guias.php" class="gr-filter-form">
                    <div class="gr-search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" class="gr-input" placeholder="Buscar por destinatario, documento o código..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <select name="estado_facturacion" class="gr-select">
                        <option value="Todos" <?= $estado_facturacion == 'Todos' ? 'selected' : '' ?>>Todos los estados de facturación</option>
                        <option value="FACTURADA" <?= $estado_facturacion == 'FACTURADA' ? 'selected' : '' ?>>FACTURADA</option>
                        <option value="NO_FACTURADA" <?= $estado_facturacion == 'NO_FACTURADA' ? 'selected' : '' ?>>NO FACTURADA</option>
                    </select>
                    <button type="submit" class="btn btn-primary" style="padding:0.55rem 1rem; border-radius:9px; font-weight:600;">
                        <i class="fas fa-filter"></i> Filtrar
                    </button>
                    <?php if(!empty($search) || !empty($estado_facturacion)): ?>
                        <a href="guias.php" class="btn btn-outline" style="padding:0.55rem 0.85rem; border-radius:9px;" title="Limpiar Filtros">
                            <i class="fas fa-times"></i>
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Tabla de Guías -->
            <div class="gr-table-card">
                <div class="gr-table-header-title">
                    <h3><i class="fas fa-truck" style="color:#E31E24;"></i> Registro de Traslados de Mercadería</h3>
                    <span style="font-size:0.8rem; color:#6B7280; font-weight:500;">
                        Mostrando <?= count($guias) ?> guías generadas
                    </span>
                </div>

                <div style="overflow-x:auto;">
                    <table class="gr-table">
                        <thead>
                            <tr>
                                <th>Código Guía</th>
                                <th>Destinatario / Cliente</th>
                                <th>Fecha Emisión</th>
                                <th>Comprobante Venta</th>
                                <th>Entrega / Cargo</th>
                                <th>Facturación</th>
                                <th style="text-align:center;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($guias)): ?>
                                <tr>
                                    <td colspan="7" style="text-align:center; padding:3.5rem; color:#9CA3AF;">
                                        <i class="fas fa-truck" style="font-size:2.5rem; margin-bottom:0.8rem; opacity:0.35; display:block;"></i>
                                        No se encontraron guías de remisión.
                                    </td>
                                </tr>
                            <?php else: foreach($guias as $g): 
                                $fecha_formato = date('d/m/Y H:i', strtotime($g['fecha_emision']));
                                $venta_asoc = !empty($g['venta_id']) 
                                    ? "{$g['venta_tipo']} {$g['venta_serie']}-{$g['venta_numero']}" 
                                    : 'Sin Venta';
                                $estado_entrega = $g['estado_entrega'] ?? 'PENDIENTE';
                                $entregaClass = ($estado_entrega === 'ENTREGADO') ? 'entregado' : (($estado_entrega === 'EN TRANSITO') ? 'en-transito' : 'pendiente');

                                // Verificar si tiene evidencias/cargos adjuntos
                                $stmtDocs = $db->prepare("SELECT COUNT(*) FROM documentos_adjuntos WHERE referencia_id = :ref AND tipo LIKE 'guia_%'");
                                $stmtDocs->execute([':ref' => $g['id']]);
                                $cargosCount = $stmtDocs->fetchColumn();
                            ?>
                            <tr>
                                <td><span class="doc-badge"><?= htmlspecialchars($g['codigo']) ?></span></td>
                                <td>
                                    <strong style="color:#111827;"><?= htmlspecialchars($g['destinatario_nombre']) ?></strong>
                                    <?php if(!empty($g['destinatario_documento'])): ?>
                                        <div style="font-size:0.75rem; color:#6B7280; margin-top:2px;">
                                            <i class="fas fa-id-card"></i> <?= htmlspecialchars($g['destinatario_documento']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:0.83rem;"><?= htmlspecialchars($fecha_formato) ?></td>
                                <td>
                                    <?php if (!empty($g['venta_id'])): ?>
                                        <a href="venta_view.php?id=<?= $g['venta_id'] ?>" style="text-decoration:none; color:#2563EB; font-weight:700; font-size:0.82rem;">
                                            <i class="fas fa-receipt"></i> <?= htmlspecialchars($venta_asoc) ?>
                                        </a>
                                    <?php else: ?>
                                        <span style="color:#9CA3AF; font-size:0.82rem;"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($venta_asoc) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-pill <?= $entregaClass ?>"><?= htmlspecialchars($estado_entrega) ?></span>
                                    <?php if($cargosCount > 0): ?>
                                        <div style="font-size:0.74rem; color:#059669; font-weight:700; margin-top:3px;">
                                            <i class="fas fa-circle-check"></i> <?= $cargosCount ?> cargo(s)
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-pill <?= strtolower($g['estado_facturacion']) ?>">
                                        <?= $g['estado_facturacion'] ?>
                                    </span>
                                </td>
                                <td style="text-align:center;">
                                    <div class="gr-actions">
                                        <a href="guia_view.php?id=<?= $g['id'] ?>" class="btn-action-soft view" title="Ver Detalles de Guía">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button type="button" class="btn-action-soft cargo" title="Adjuntar Cargo Firmado / Foto de Entrega" onclick="openCargoModal(<?= $g['id'] ?>, '<?= htmlspecialchars(addslashes($g['codigo'])) ?>', '<?= htmlspecialchars($estado_entrega) ?>')">
                                            <i class="fas fa-camera"></i>
                                        </button>
                                        <?php if ($is_admin): ?>
                                            <a href="guia_delete.php?id=<?= $g['id'] ?>" class="btn-action-soft delete" title="Eliminar Guía" onclick="return confirm('⚠️ ¿Seguro de ELIMINAR esta guía de remisión?');">
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

<!-- Modal: Adjuntar Cargo Firmado -->
<div class="modal-overlay" id="modalCargo">
    <div class="modal-box">
        <div style="padding:1.25rem 1.5rem; border-bottom:1px solid #E5E7EB; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:1.1rem; font-weight:800; color:#111827;">
                <i class="fas fa-file-signature" style="color:#7C3AED; margin-right:6px;"></i> Cargo Firmado / Evidencia de Entrega
            </h3>
            <button type="button" onclick="closeCargoModal()" style="background:none; border:none; font-size:1.2rem; cursor:pointer;">&times;</button>
        </div>
        <form id="formCargo" action="guia_cargo_process.php" method="POST" enctype="multipart/form-data">
            <div style="padding:1.4rem;">
                <input type="hidden" name="guia_id" id="cargoGuiaId">

                <div class="form-group" style="margin-bottom:1rem;">
                    <label style="display:block; font-size:0.76rem; font-weight:700; text-transform:uppercase; color:#4B5563; margin-bottom:0.35rem;">Código de Guía</label>
                    <input type="text" id="cargoCodigoGuia" class="gr-input" readonly style="opacity:0.8;">
                </div>

                <div class="form-group" style="margin-bottom:1rem;">
                    <label style="display:block; font-size:0.76rem; font-weight:700; text-transform:uppercase; color:#4B5563; margin-bottom:0.35rem;">Estado de la Entrega *</label>
                    <select name="estado_entrega" id="cargoEstadoEntrega" class="gr-input" required>
                        <option value="PENDIENTE">PENDIENTE DE ENTREGA</option>
                        <option value="EN TRANSITO">EN TRÁNSITO / EN AGENCIA</option>
                        <option value="ENTREGADO">ENTREGADO CON CONFORMIDAD</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom:1rem;">
                    <label style="display:block; font-size:0.76rem; font-weight:700; text-transform:uppercase; color:#4B5563; margin-bottom:0.35rem;">Tipo de Evidencia *</label>
                    <select name="tipo_documento" class="gr-input" required>
                        <option value="cargo_firmado">Cargo Firmado por Cliente (DNI / Nombre / Firma)</option>
                        <option value="guia_transportista">Guía de Remisión Transportista (Agencia)</option>
                        <option value="foto_entrega">Foto de Entrega en Domicilio</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom:1rem;">
                    <label style="display:block; font-size:0.76rem; font-weight:700; text-transform:uppercase; color:#4B5563; margin-bottom:0.35rem;">Capturar / Adjuntar Imagen o Documento</label>
                    <div class="upload-area-sys" onclick="document.getElementById('fileCargoInput').click()">
                        <i class="fas fa-camera" style="font-size:1.8rem; color:#7C3AED; margin-bottom:0.4rem; display:block;"></i>
                        <p style="font-size:0.84rem; color:#4B5563; margin:0;">Haz clic para tomar foto o seleccionar archivo (PDF / Imagen)</p>
                    </div>
                    <input type="file" id="fileCargoInput" name="documento" accept=".pdf,.jpg,.jpeg,.png" capture="environment" style="display:none;" onchange="previewCargoFile(this)">
                    <div id="cargoFilePreview" style="margin-top:0.5rem; font-size:0.84rem; color:#059669; font-weight:600;"></div>
                </div>

                <div class="form-group">
                    <label style="display:block; font-size:0.76rem; font-weight:700; text-transform:uppercase; color:#4B5563; margin-bottom:0.35rem;">Observaciones de Entrega</label>
                    <textarea name="observaciones" class="gr-input" rows="2" placeholder="Detalles de la recepción, agencia, etc."></textarea>
                </div>
            </div>
            <div style="padding:1rem 1.4rem; background:#F9FAFB; border-top:1px solid #E5E7EB; display:flex; justify-content:flex-end; gap:0.75rem;">
                <button type="button" onclick="closeCargoModal()" class="btn btn-outline">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Registrar Evidencia</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCargoModal(id, codigo, estado) {
    document.getElementById('cargoGuiaId').value = id;
    document.getElementById('cargoCodigoGuia').value = codigo;
    document.getElementById('cargoEstadoEntrega').value = estado || 'PENDIENTE';
    document.getElementById('cargoFilePreview').innerHTML = '';
    document.getElementById('formCargo').reset();
    document.getElementById('cargoGuiaId').value = id;
    document.getElementById('cargoCodigoGuia').value = codigo;
    document.getElementById('modalCargo').classList.add('open');
}

function closeCargoModal() {
    document.getElementById('modalCargo').classList.remove('open');
}

function previewCargoFile(input) {
    const preview = document.getElementById('cargoFilePreview');
    if (input.files && input.files[0]) {
        preview.innerHTML = '<i class="fas fa-check-circle"></i> Archivo seleccionado: <strong>' + input.files[0].name + '</strong>';
    } else {
        preview.innerHTML = '';
    }
}
</script>

<?php include 'partials/footer.php'; ?>
</body>
</html>
