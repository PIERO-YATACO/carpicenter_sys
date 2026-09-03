<?php
require_once __DIR__ . '/../auth/check_auth.php';
require_once __DIR__ . '/../config/db.php';

// Params
$search = $_GET['search'] ?? '';
$tipo_filter = $_GET['tipo'] ?? '';
$estado_filter = $_GET['estado'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Query
$where = "WHERE 1=1";
$params = [];
if ($search) { 
    $where .= " AND (nombre ILIKE :s OR dni_ruc ILIKE :s OR razon_social ILIKE :s OR email ILIKE :s OR telefono ILIKE :s)"; 
    $params[':s'] = "%$search%"; 
}
if ($tipo_filter) { $where .= " AND tipo_cliente = :tipo"; $params[':tipo'] = $tipo_filter; }
if ($estado_filter) { $where .= " AND estado = :estado"; $params[':estado'] = $estado_filter; }

$total = $db->prepare("SELECT COUNT(*) FROM clientes $where");
$total->execute($params);
$total_rows = $total->fetchColumn();
$total_pages = ceil($total_rows / $per_page);

$stmt = $db->prepare("SELECT * FROM clientes $where ORDER BY id DESC LIMIT $per_page OFFSET $offset");
$stmt->execute($params);
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stats
$stats = $db->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN tipo_cliente='Empresa' OR tipo_cliente='Persona Jurídica' THEN 1 ELSE 0 END) as empresas,
    SUM(CASE WHEN tipo_cliente='Persona Natural' OR tipo_cliente IS NULL THEN 1 ELSE 0 END) as personas,
    SUM(CASE WHEN estado='Activo' OR estado IS NULL THEN 1 ELSE 0 END) as activos
FROM clientes")->fetch(PDO::FETCH_ASSOC);

$page_title = 'Clientes';
$page_subtitle = 'Directorio general y cartera de clientes de la empresa';
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
        /* ===== CLIENTES PREMIUM ===== */
        .cli-hero {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.4rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .cli-hero-title h1 {
            font-size: 1.45rem;
            font-weight: 800;
            color: #111827;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .cli-hero-title p {
            color: #6B7280;
            font-size: 0.84rem;
            margin: 0.25rem 0 0 0;
        }

        /* KPIs */
        .cli-kpis-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 1.2rem;
            margin-bottom: 1.3rem;
        }
        .cli-kpi-card {
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
        .cli-kpi-card:hover {
            transform: translateY(-2px);
        }
        .cli-kpi-icon {
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
        .icon-blue-bg { background: linear-gradient(135deg, rgba(37,99,235,0.12) 0%, rgba(59,130,246,0.2) 100%); color: #2563EB; }
        .icon-amber-bg { background: linear-gradient(135deg, rgba(217,119,6,0.12) 0%, rgba(245,158,11,0.2) 100%); color: #D97706; }
        .icon-emerald-bg { background: linear-gradient(135deg, rgba(5,150,105,0.12) 0%, rgba(16,185,129,0.2) 100%); color: #059669; }

        .cli-kpi-info span.label {
            font-size: 0.74rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
            color: #6B7280;
            display: block;
            margin-bottom: 0.15rem;
        }
        .cli-kpi-info h3 {
            font-size: 1.35rem;
            font-weight: 800;
            color: #111827;
            margin: 0;
            line-height: 1.2;
        }
        .cli-kpi-info span.sub-tag {
            font-size: 0.74rem;
            font-weight: 600;
            display: inline-block;
            margin-top: 0.25rem;
            padding: 2px 8px;
            border-radius: 20px;
        }

        /* Filter Panel */
        .cli-filter-card {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 14px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.25rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
            display: flex;
            gap: 0.75rem;
            align-items: center;
            flex-wrap: wrap;
        }
        .cli-search-box {
            position: relative;
            flex: 2;
            min-width: 240px;
        }
        .cli-search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #9CA3AF;
            font-size: 0.9rem;
        }
        .cli-input {
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
        .cli-input:focus {
            background: #FFFFFF;
            border-color: #E31E24;
            box-shadow: 0 0 0 3px rgba(227,30,36,0.1);
        }
        .cli-select {
            padding: 0.55rem 1.8rem 0.55rem 0.85rem;
            border-radius: 9px;
            border: 1px solid #D1D5DB;
            background: #F9FAFB;
            font-size: 0.84rem;
            color: #111827;
            outline: none;
            min-width: 140px;
        }

        /* Table Card */
        .cli-table-card {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        }
        .cli-table-header-title {
            padding: 1rem 1.4rem;
            border-bottom: 1px solid #F3F4F6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .cli-table-header-title h3 {
            font-size: 0.95rem;
            font-weight: 700;
            color: #111827;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .cli-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        .cli-table th {
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
        .cli-table td {
            padding: 0.8rem 1rem;
            border-bottom: 1px solid #F3F4F6;
            font-size: 0.84rem;
            color: #374151;
            vertical-align: middle;
            white-space: nowrap;
        }
        .cli-table tbody tr:hover {
            background: #F9FAFB;
        }

        /* Badges */
        .doc-badge {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-weight: 700;
            font-size: 0.82rem;
            color: #1E293B;
            background: #F1F5F9;
            padding: 3px 8px;
            border-radius: 6px;
            border: 1px solid #CBD5E1;
            display: inline-block;
            white-space: nowrap;
        }
        .tipo-pill {
            font-size: 0.75rem;
            font-weight: 700;
            padding: 3px 9px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
        }
        .tipo-pill.natural { background: #FFFBEB; color: #D97706; border: 1px solid #FDE68A; }
        .tipo-pill.empresa { background: #EFF6FF; color: #2563EB; border: 1px solid #BFDBFE; }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 9px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            white-space: nowrap;
        }
        .status-pill.activo { background: rgba(5,150,105,0.1); color: #059669; border: 1px solid rgba(5,150,105,0.25); }
        .status-pill.inactivo { background: rgba(220,38,38,0.1); color: #DC2626; border: 1px solid rgba(220,38,38,0.25); }

        /* Actions */
        .cli-actions {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            white-space: nowrap;
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
        .btn-action-soft.wa { background: rgba(37,211,102,0.1); color: #25D366; }
        .btn-action-soft.wa:hover { background: #25D366; color: #FFFFFF; }
        .btn-action-soft.view { background: rgba(37,99,235,0.08); color: #2563EB; }
        .btn-action-soft.view:hover { background: #2563EB; color: #FFFFFF; }
        .btn-action-soft.edit { background: rgba(100,116,139,0.1); color: #475569; }
        .btn-action-soft.edit:hover { background: #475569; color: #FFFFFF; }
        .btn-action-soft.delete { background: rgba(220,38,38,0.08); color: #DC2626; }
        .btn-action-soft.delete:hover { background: #DC2626; color: #FFFFFF; }

        /* Single line text container with ellipsis */
        .cli-name-cell {
            display: flex;
            align-items: center;
            gap: 8px;
            max-width: 280px;
        }
        .cli-name-text {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-weight: 700;
            color: #111827;
        }
        .cli-loc-cell {
            max-width: 280px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 0.82rem;
            color: #4B5563;
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
            <div class="cli-hero">
                <div class="cli-hero-title">
                    <h1><i class="fas fa-users" style="color:#E31E24;"></i> Directorio de Clientes</h1>
                    <p>Cartera de clientes registrados, historial de presupuestos y contactos directos</p>
                </div>
                <button class="btn btn-primary" onclick="openModal()" style="font-weight:600; padding:0.55rem 1.2rem; border-radius:10px; box-shadow:0 4px 12px rgba(227,30,36,0.25);">
                    <i class="fas fa-plus" style="margin-right:6px;"></i> Nuevo Cliente
                </button>
            </div>

            <!-- KPI Cards -->
            <div class="cli-kpis-grid">
                <div class="cli-kpi-card">
                    <div class="cli-kpi-icon icon-indigo-bg">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="cli-kpi-info">
                        <span class="label">Total Clientes</span>
                        <h3 style="color:#4F46E5;"><?= $stats['total'] ?></h3>
                        <span class="sub-tag" style="background:#EEF2FF; color:#4F46E5;">En cartera</span>
                    </div>
                </div>

                <div class="cli-kpi-card">
                    <div class="cli-kpi-icon icon-blue-bg">
                        <i class="fas fa-building"></i>
                    </div>
                    <div class="cli-kpi-info">
                        <span class="label">Empresas (RUC)</span>
                        <h3 style="color:#2563EB;"><?= $stats['empresas'] ?></h3>
                        <span class="sub-tag" style="background:#EFF6FF; color:#2563EB;">Corporativos</span>
                    </div>
                </div>

                <div class="cli-kpi-card">
                    <div class="cli-kpi-icon icon-amber-bg">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="cli-kpi-info">
                        <span class="label">Personas Naturales</span>
                        <h3 style="color:#D97706;"><?= $stats['personas'] ?></h3>
                        <span class="sub-tag" style="background:#FFFBEB; color:#D97706;">Particulares</span>
                    </div>
                </div>

                <div class="cli-kpi-card">
                    <div class="cli-kpi-icon icon-emerald-bg">
                        <i class="fas fa-circle-check"></i>
                    </div>
                    <div class="cli-kpi-info">
                        <span class="label">Clientes Activos</span>
                        <h3 style="color:#059669;"><?= $stats['activos'] ?></h3>
                        <span class="sub-tag" style="background:#ECFDF5; color:#059669;">Cuentas vigentes</span>
                    </div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="cli-filter-card">
                <form method="GET" style="display:flex; width:100%; gap:10px; flex-wrap:wrap;">
                    <div class="cli-search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" class="cli-input" value="<?= htmlspecialchars($search) ?>" placeholder="Buscar por nombre, DNI, RUC o teléfono...">
                    </div>
                    <select name="tipo" class="cli-select" onchange="this.form.submit()">
                        <option value="">Todos los tipos</option>
                        <option value="Persona Natural" <?= $tipo_filter=='Persona Natural'?'selected':'' ?>>Persona Natural</option>
                        <option value="Empresa" <?= ($tipo_filter=='Empresa'||$tipo_filter=='Persona Jurídica')?'selected':'' ?>>Empresa</option>
                    </select>
                    <select name="estado" class="cli-select" onchange="this.form.submit()">
                        <option value="">Todos los estados</option>
                        <option value="Activo" <?= $estado_filter=='Activo'?'selected':'' ?>>Activo</option>
                        <option value="Inactivo" <?= $estado_filter=='Inactivo'?'selected':'' ?>>Inactivo</option>
                    </select>
                    <button type="submit" class="btn btn-primary" style="padding:0.55rem 1rem; border-radius:9px; font-weight:600;">
                        <i class="fas fa-filter"></i> Filtrar
                    </button>
                    <?php if($search || $tipo_filter || $estado_filter): ?>
                        <a href="clientes.php" class="btn btn-outline" style="padding:0.55rem 0.85rem; border-radius:9px;" title="Limpiar Filtros">
                            <i class="fas fa-times"></i>
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Tabla de Clientes -->
            <div class="cli-table-card">
                <div class="cli-table-header-title">
                    <h3><i class="fas fa-users" style="color:#E31E24;"></i> Listado de Clientes</h3>
                    <span style="font-size:0.8rem; color:#6B7280; font-weight:500;">
                        Mostrando <?= count($clientes) ?> de <?= $total_rows ?> clientes
                    </span>
                </div>

                <div style="overflow-x:auto;">
                    <table class="cli-table">
                        <thead>
                            <tr>
                                <th>Cliente / Razón Social</th>
                                <th>Documento</th>
                                <th>Contacto / Teléfono</th>
                                <th>Ciudad / Dirección</th>
                                <th>Tipo</th>
                                <th>Estado</th>
                                <th style="text-align:center;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($clientes)): ?>
                                <tr>
                                    <td colspan="7" style="text-align:center; padding:3.5rem; color:#9CA3AF;">
                                        <i class="fas fa-users-slash" style="font-size:2.5rem; margin-bottom:0.8rem; opacity:0.35; display:block;"></i>
                                        No se encontraron clientes registrados.
                                    </td>
                                </tr>
                            <?php else: foreach($clientes as $c): 
                                $isEmpresa = ($c['tipo_cliente'] === 'Empresa' || $c['tipo_cliente'] === 'Persona Jurídica');
                                $isActivo = ($c['estado'] === 'Activo' || empty($c['estado']));
                                $phoneClean = preg_replace('/[^0-9]/', '', $c['telefono'] ?? '');
                                $wsUrl = (strlen($phoneClean) >= 9) ? "https://wa.me/" . ((strlen($phoneClean) === 9) ? '51' . $phoneClean : $phoneClean) : null;
                                
                                // Construir texto de ubicación en una sola línea
                                $locParts = array_filter([$c['ciudad'] ?? '', $c['direccion'] ?? '']);
                                $locText = !empty($locParts) ? implode(' · ', $locParts) : '—';
                            ?>
                            <tr>
                                <td>
                                    <div class="cli-name-cell">
                                        <div style="width:30px; height:30px; border-radius:50%; background:<?= $isEmpresa ? '#EFF6FF' : '#FEF2F2' ?>; color:<?= $isEmpresa ? '#2563EB' : '#DC2626' ?>; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:0.8rem; flex-shrink:0;">
                                            <?= strtoupper(substr($c['nombre'], 0, 1)) ?>
                                        </div>
                                        <span class="cli-name-text" title="<?= htmlspecialchars($c['nombre']) ?>">
                                            <?= htmlspecialchars($c['nombre']) ?>
                                        </span>
                                    </div>
                                </td>
                                <td><span class="doc-badge"><?= htmlspecialchars($c['dni_ruc'] ?: 'S/D') ?></span></td>
                                <td>
                                    <?php if(!empty($c['telefono'])): ?>
                                        <span style="display:inline-flex; align-items:center; gap:5px; font-size:0.84rem; color:#374151; font-weight:600;">
                                            <i class="fas fa-phone" style="color:#9CA3AF; font-size:0.75rem;"></i>
                                            <?= htmlspecialchars($c['telefono']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color:#9CA3AF;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="cli-loc-cell" title="<?= htmlspecialchars($locText) ?>">
                                        <i class="fas fa-location-dot" style="color:#9CA3AF; font-size:0.75rem; margin-right:4px;"></i>
                                        <?= htmlspecialchars($locText) ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="tipo-pill <?= $isEmpresa ? 'empresa' : 'natural' ?>">
                                        <i class="fas <?= $isEmpresa ? 'fa-building' : 'fa-user' ?>" style="font-size:0.7rem;"></i>
                                        <?= htmlspecialchars($c['tipo_cliente'] ?: 'Persona Natural') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-pill <?= $isActivo ? 'activo' : 'inactivo' ?>">
                                        <?= $isActivo ? 'ACTIVO' : 'INACTIVO' ?>
                                    </span>
                                </td>
                                <td style="text-align:center;">
                                    <div class="cli-actions">
                                        <?php if($wsUrl): ?>
                                            <a href="<?= $wsUrl ?>" target="_blank" class="btn-action-soft wa" title="WhatsApp Directo">
                                                <i class="fab fa-whatsapp"></i>
                                            </a>
                                        <?php endif; ?>
                                        <button type="button" class="btn-action-soft edit" title="Editar Cliente" onclick="editCliente(<?= htmlspecialchars(json_encode($c), ENT_QUOTES, 'UTF-8') ?>)">
                                            <i class="fas fa-pen"></i>
                                        </button>
                                        <?php if ($is_admin): ?>
                                            <button type="button" class="btn-action-soft delete" title="Eliminar" onclick="deleteCliente(<?= $c['id'] ?>)">
                                                <i class="fas fa-trash-can"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Paginación -->
            <?php if($total_pages > 1): ?>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:1.2rem;">
                    <span style="font-size:0.84rem; color:#6B7280;">
                        Mostrando página <strong><?= $page ?></strong> de <strong><?= $total_pages ?></strong>
                    </span>
                    <div style="display:flex; gap:4px;">
                        <?php if($page > 1): ?>
                            <a href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&tipo=<?= urlencode($tipo_filter) ?>" class="btn btn-outline" style="padding:4px 10px;">&laquo;</a>
                        <?php endif; ?>
                        <?php for($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                            <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&tipo=<?= urlencode($tipo_filter) ?>" class="btn <?= $i==$page?'btn-primary':'btn-outline' ?>" style="padding:4px 10px;"><?= $i ?></a>
                        <?php endfor; ?>
                        <?php if($page < $total_pages): ?>
                            <a href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&tipo=<?= urlencode($tipo_filter) ?>" class="btn btn-outline" style="padding:4px 10px;">&raquo;</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<!-- Modal Cliente -->
<div class="modal-overlay" id="modalCliente" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.6); z-index:999; align-items:center; justify-content:center; padding:1rem;">
    <div style="background:#fff; border-radius:16px; width:100%; max-width:540px; box-shadow:0 20px 40px rgba(0,0,0,0.2); overflow:hidden;">
        <div style="padding:1.25rem 1.5rem; border-bottom:1px solid #E5E7EB; display:flex; justify-content:space-between; align-items:center;">
            <h3 id="modalClienteTitle" style="margin:0; font-size:1.1rem; font-weight:800; color:#111827;">Nuevo Cliente</h3>
            <button type="button" onclick="closeModal()" style="background:none; border:none; font-size:1.2rem; cursor:pointer;">&times;</button>
        </div>
        <form id="clienteForm" onsubmit="saveCliente(event)">
            <input type="hidden" name="id" id="cliId">
            <div style="padding:1.4rem;">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.8rem; margin-bottom:0.8rem;">
                    <div>
                        <label style="display:block; font-size:0.76rem; font-weight:700; text-transform:uppercase; color:#4B5563; margin-bottom:0.35rem;">Tipo de Cliente *</label>
                        <select name="tipo_cliente" id="cliTipo" class="cli-input" style="padding:0.55rem;" required>
                            <option value="Persona Natural">Persona Natural</option>
                            <option value="Empresa">Empresa</option>
                        </select>
                    </div>
                    <div>
                        <label style="display:block; font-size:0.76rem; font-weight:700; text-transform:uppercase; color:#4B5563; margin-bottom:0.35rem;">DNI / RUC *</label>
                        <input type="text" name="dni_ruc" id="cliDni" class="cli-input" style="padding:0.55rem;" required placeholder="N° Documento">
                    </div>
                </div>

                <div style="margin-bottom:0.8rem;">
                    <label style="display:block; font-size:0.76rem; font-weight:700; text-transform:uppercase; color:#4B5563; margin-bottom:0.35rem;">Nombre Completo / Razón Social *</label>
                    <input type="text" name="nombre" id="cliNombre" class="cli-input" style="padding:0.55rem;" required placeholder="Nombre del cliente">
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.8rem; margin-bottom:0.8rem;">
                    <div>
                        <label style="display:block; font-size:0.76rem; font-weight:700; text-transform:uppercase; color:#4B5563; margin-bottom:0.35rem;">Teléfono / Celular</label>
                        <input type="text" name="telefono" id="cliTel" class="cli-input" style="padding:0.55rem;" placeholder="Ej: 999 888 777">
                    </div>
                    <div>
                        <label style="display:block; font-size:0.76rem; font-weight:700; text-transform:uppercase; color:#4B5563; margin-bottom:0.35rem;">Correo Electrónico</label>
                        <input type="email" name="email" id="cliEmail" class="cli-input" style="padding:0.55rem;" placeholder="cliente@correo.com">
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.8rem;">
                    <div>
                        <label style="display:block; font-size:0.76rem; font-weight:700; text-transform:uppercase; color:#4B5563; margin-bottom:0.35rem;">Ciudad</label>
                        <input type="text" name="ciudad" id="cliCiudad" class="cli-input" style="padding:0.55rem;" placeholder="Ej: Lima">
                    </div>
                    <div>
                        <label style="display:block; font-size:0.76rem; font-weight:700; text-transform:uppercase; color:#4B5563; margin-bottom:0.35rem;">Dirección</label>
                        <input type="text" name="direccion" id="cliDir" class="cli-input" style="padding:0.55rem;" placeholder="Av. Principal 123">
                    </div>
                </div>
            </div>
            <div style="padding:1rem 1.4rem; background:#F9FAFB; border-top:1px solid #E5E7EB; display:flex; justify-content:flex-end; gap:0.75rem;">
                <button type="button" onclick="closeModal()" class="btn btn-outline">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Guardar Cliente</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal() {
    document.getElementById('cliId').value = '';
    document.getElementById('clienteForm').reset();
    document.getElementById('modalClienteTitle').textContent = 'Nuevo Cliente';
    document.getElementById('modalCliente').style.display = 'flex';
}

function closeModal() {
    document.getElementById('modalCliente').style.display = 'none';
}

function editCliente(data) {
    document.getElementById('cliId').value = data.id || '';
    document.getElementById('cliTipo').value = data.tipo_cliente || 'Persona Natural';
    document.getElementById('cliDni').value = data.dni_ruc || '';
    document.getElementById('cliNombre').value = data.nombre || '';
    document.getElementById('cliTel').value = data.telefono || '';
    document.getElementById('cliEmail').value = data.email || '';
    document.getElementById('cliCiudad').value = data.ciudad || '';
    document.getElementById('cliDir').value = data.direccion || '';
    document.getElementById('modalClienteTitle').textContent = 'Editar Cliente';
    document.getElementById('modalCliente').style.display = 'flex';
}

function saveCliente(e) {
    e.preventDefault();
    const form = new FormData(document.getElementById('clienteForm'));
    const cliId = document.getElementById('cliId').value;
    form.append('action', cliId ? 'update' : 'create');

    fetch('cliente_controller.php', {
        method: 'POST',
        body: form
    }).then(r => r.json()).then(res => {
        if(res.success) {
            location.reload();
        } else {
            alert(res.message || 'Error al guardar cliente');
        }
    }).catch(() => {
        alert('Error al comunicarse con el servidor.');
    });
}

function deleteCliente(id) {
    if(confirm('⚠️ ¿Seguro que deseas eliminar este cliente del sistema?')) {
        const form = new FormData();
        form.append('id', id);
        form.append('action', 'delete');
        fetch('cliente_controller.php', {
            method: 'POST',
            body: form
        }).then(r => r.json()).then(res => {
            if(res.success) {
                location.reload();
            } else {
                alert(res.message || 'Error al eliminar cliente');
            }
        }).catch(() => {
            location.reload();
        });
    }
}
</script>

<?php include 'partials/footer.php'; ?>
</body>
</html>