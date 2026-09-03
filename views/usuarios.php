<?php
require_once __DIR__ . '/../auth/check_auth.php';
require_once __DIR__ . '/../config/db.php';

// Parametros de filtrado y búsqueda
$search = trim($_GET['search'] ?? '');
$rol_filter = $_GET['rol'] ?? '';
$estado_filter = $_GET['estado'] ?? '';

// Construir query de usuarios
$where = "WHERE 1=1";
$params = [];
if ($search) {
    $where .= " AND (u.username ILIKE :s OR u.nombre_completo ILIKE :s OR u.email ILIKE :s)";
    $params[':s'] = "%$search%";
}
if ($rol_filter) {
    $where .= " AND ur.rol_id = :rol";
    $params[':rol'] = $rol_filter;
}
if ($estado_filter) {
    $where .= " AND u.estado = :estado";
    $params[':estado'] = $estado_filter;
}

// Cargar usuarios
$stmt = $db->prepare("
    SELECT u.id, u.username, u.nombre_completo, u.email, u.estado, u.foto_url, 
           l.nombre as local_nombre, r.nombre as rol_nombre, r.id as rol_id
    FROM usuarios u
    LEFT JOIN locales l ON u.local_id = l.id
    LEFT JOIN usuario_roles ur ON u.id = ur.usuario_id
    LEFT JOIN roles r ON ur.rol_id = r.id
    $where
    ORDER BY u.id ASC
");
$stmt->execute($params);
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Cargar roles y locales para los dropdowns y modales
$roles = $db->query("SELECT id, nombre FROM roles ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$locales = $db->query("SELECT id, nombre FROM locales ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas rápidas
$stats = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN u.estado='Activo' THEN 1 ELSE 0 END) as activos,
        SUM(CASE WHEN u.estado='Inactivo' THEN 1 ELSE 0 END) as inactivos,
        SUM(CASE WHEN r.nombre='Super Admin' THEN 1 ELSE 0 END) as admins
    FROM usuarios u
    LEFT JOIN usuario_roles ur ON u.id = ur.usuario_id
    LEFT JOIN roles r ON ur.rol_id = r.id
")->fetch(PDO::FETCH_ASSOC);

$page_title = 'Usuarios';
$page_subtitle = 'Gestión y control de acceso del personal';
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
        /* ===== USUARIOS PREMIUM ===== */
        .usr-hero {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.4rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .usr-hero-title h1 {
            font-size: 1.45rem;
            font-weight: 800;
            color: #111827;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .usr-hero-title p {
            color: #6B7280;
            font-size: 0.84rem;
            margin: 0.25rem 0 0 0;
        }

        /* KPIs */
        .usr-kpis-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 1.2rem;
            margin-bottom: 1.3rem;
        }
        .usr-kpi-card {
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
        .usr-kpi-card:hover {
            transform: translateY(-2px);
        }
        .usr-kpi-icon {
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
        .icon-rose-bg { background: linear-gradient(135deg, rgba(220,38,38,0.12) 0%, rgba(239,68,68,0.2) 100%); color: #DC2626; }
        .icon-blue-bg { background: linear-gradient(135deg, rgba(37,99,235,0.12) 0%, rgba(59,130,246,0.2) 100%); color: #2563EB; }

        .usr-kpi-info span.label {
            font-size: 0.74rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
            color: #6B7280;
            display: block;
            margin-bottom: 0.15rem;
        }
        .usr-kpi-info h3 {
            font-size: 1.35rem;
            font-weight: 800;
            color: #111827;
            margin: 0;
            line-height: 1.2;
        }
        .usr-kpi-info span.sub-tag {
            font-size: 0.74rem;
            font-weight: 600;
            display: inline-block;
            margin-top: 0.25rem;
            padding: 2px 8px;
            border-radius: 20px;
        }

        /* Filter Panel */
        .usr-filter-card {
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
        .usr-search-box {
            position: relative;
            flex: 2;
            min-width: 240px;
        }
        .usr-search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #9CA3AF;
            font-size: 0.9rem;
        }
        .usr-input {
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
        .usr-input:focus {
            background: #FFFFFF;
            border-color: #E31E24;
            box-shadow: 0 0 0 3px rgba(227,30,36,0.1);
        }
        .usr-select {
            padding: 0.55rem 1.8rem 0.55rem 0.85rem;
            border-radius: 9px;
            border: 1px solid #D1D5DB;
            background: #F9FAFB;
            font-size: 0.84rem;
            color: #111827;
            outline: none;
            min-width: 160px;
        }

        /* Table Card */
        .usr-table-card {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        }
        .usr-table-header-title {
            padding: 1rem 1.4rem;
            border-bottom: 1px solid #F3F4F6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .usr-table-header-title h3 {
            font-size: 0.95rem;
            font-weight: 700;
            color: #111827;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .usr-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        .usr-table th {
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
        .usr-table td {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid #F3F4F6;
            font-size: 0.85rem;
            color: #374151;
            vertical-align: middle;
        }
        .usr-table tbody tr:hover {
            background: #F9FAFB;
        }

        /* Avatar Circle */
        .usr-avatar-sm {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #111827;
            color: #FFFFFF;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.85rem;
            flex-shrink: 0;
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
        .status-pill.inactivo { background: rgba(220,38,38,0.1); color: #DC2626; border: 1px solid rgba(220,38,38,0.25); }

        .role-pill {
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 700;
            display: inline-block;
        }
        .role-super-admin { background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA; }
        .role-contabilidad { background: #F5F3FF; color: #7C3AED; border: 1px solid #DDD6FE; }
        .role-almacen { background: #FFFBEB; color: #D97706; border: 1px solid #FDE68A; }
        .role-produccion { background: #ECFDF5; color: #059669; border: 1px solid #A7F3D0; }
        .role-vendedor { background: #EFF6FF; color: #2563EB; border: 1px solid #BFDBFE; }

        /* Actions */
        .usr-actions {
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
        .btn-action-soft.edit { background: rgba(100,116,139,0.1); color: #475569; }
        .btn-action-soft.edit:hover { background: #475569; color: #FFFFFF; }
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
            max-width: 500px;
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
    <?php include 'partials/sidebar.php'; ?>
    <div class="main-content">
        <?php include 'partials/header.php'; ?>
        <div class="page-content">

            <!-- Header de la Página -->
            <div class="usr-hero">
                <div class="usr-hero-title">
                    <h1><i class="fas fa-user-gear" style="color:#E31E24;"></i> Usuarios</h1>
                    <p>Panel administrativo de personal, accesos por tienda y asignación de roles</p>
                </div>
                <button class="btn btn-primary" onclick="openModal()" style="font-weight:600; padding:0.55rem 1.2rem; border-radius:10px; box-shadow:0 4px 12px rgba(227,30,36,0.25);">
                    <i class="fas fa-plus" style="margin-right:6px;"></i> Nuevo Usuario
                </button>
            </div>

            <!-- KPI Cards -->
            <div class="usr-kpis-grid">
                <div class="usr-kpi-card">
                    <div class="usr-kpi-icon icon-indigo-bg">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="usr-kpi-info">
                        <span class="label">Total Cuentas</span>
                        <h3 style="color:#4F46E5;"><?= $stats['total'] ?></h3>
                        <span class="sub-tag" style="background:#EEF2FF; color:#4F46E5;">Usuarios registrados</span>
                    </div>
                </div>

                <div class="usr-kpi-card">
                    <div class="usr-kpi-icon icon-emerald-bg">
                        <i class="fas fa-circle-check"></i>
                    </div>
                    <div class="usr-kpi-info">
                        <span class="label">Usuarios Activos</span>
                        <h3 style="color:#059669;"><?= $stats['activos'] ?></h3>
                        <span class="sub-tag" style="background:#ECFDF5; color:#059669;">Con acceso habilitado</span>
                    </div>
                </div>

                <div class="usr-kpi-card">
                    <div class="usr-kpi-icon icon-rose-bg">
                        <i class="fas fa-circle-xmark"></i>
                    </div>
                    <div class="usr-kpi-info">
                        <span class="label">Inactivos</span>
                        <h3 style="color:#DC2626;"><?= $stats['inactivos'] ?></h3>
                        <span class="sub-tag" style="background:#FEF2F2; color:#DC2626;">Acceso revocado</span>
                    </div>
                </div>

                <div class="usr-kpi-card">
                    <div class="usr-kpi-icon icon-blue-bg">
                        <i class="fas fa-shield-halved"></i>
                    </div>
                    <div class="usr-kpi-info">
                        <span class="label">Super Admins</span>
                        <h3 style="color:#2563EB;"><?= $stats['admins'] ?></h3>
                        <span class="sub-tag" style="background:#EFF6FF; color:#2563EB;">Acceso total</span>
                    </div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="usr-filter-card">
                <form method="GET" style="display:flex; width:100%; gap:10px; flex-wrap:wrap;">
                    <div class="usr-search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" class="usr-input" value="<?= htmlspecialchars($search) ?>" placeholder="Buscar por usuario, nombre completo o correo...">
                    </div>
                    <select name="rol" class="usr-select" onchange="this.form.submit()">
                        <option value="">Todos los Roles</option>
                        <?php foreach($roles as $r): ?>
                            <option value="<?= $r['id'] ?>" <?= $rol_filter == $r['id'] ? 'selected' : '' ?>><?= htmlspecialchars($r['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="estado" class="usr-select" onchange="this.form.submit()">
                        <option value="">Todos los Estados</option>
                        <option value="Activo" <?= $estado_filter == 'Activo' ? 'selected' : '' ?>>Activo</option>
                        <option value="Inactivo" <?= $estado_filter == 'Inactivo' ? 'selected' : '' ?>>Inactivo</option>
                    </select>
                    <button type="submit" class="btn btn-primary" style="padding:0.55rem 1rem; border-radius:9px; font-weight:600;">
                        <i class="fas fa-filter"></i> Filtrar
                    </button>
                    <?php if($search || $rol_filter || $estado_filter): ?>
                        <a href="usuarios.php" class="btn btn-outline" style="padding:0.55rem 0.85rem; border-radius:9px;" title="Limpiar Filtros">
                            <i class="fas fa-times"></i>
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Tabla de Usuarios -->
            <div class="usr-table-card">
                <div class="usr-table-header-title">
                    <h3><i class="fas fa-users" style="color:#E31E24;"></i> Lista de Cuentas de Personal</h3>
                    <span style="font-size:0.8rem; color:#6B7280; font-weight:500;">
                        Mostrando <?= count($usuarios) ?> usuarios
                    </span>
                </div>

                <div style="overflow-x:auto;">
                    <table class="usr-table">
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Nombre Completo</th>
                                <th>Email</th>
                                <th>Rol Asignado</th>
                                <th>Local / Sucursal</th>
                                <th>Estado</th>
                                <th style="text-align:center;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($usuarios)): ?>
                                <tr>
                                    <td colspan="7" style="text-align:center; padding:3.5rem; color:#9CA3AF;">
                                        <i class="fas fa-user-slash" style="font-size:2.5rem; margin-bottom:0.8rem; opacity:0.35; display:block;"></i>
                                        No se encontraron usuarios registrados.
                                    </td>
                                </tr>
                            <?php else: foreach($usuarios as $u):
                                $rol_name = $u['rol_nombre'] ?? 'Sin Rol';
                                $initial = strtoupper(substr($u['nombre_completo'] ?? $u['username'] ?? 'U', 0, 1));
                                $rolClass = 'role-vendedor';
                                if ($rol_name === 'Super Admin' || $rol_name === 'Administrador') $rolClass = 'role-super-admin';
                                elseif ($rol_name === 'Contabilidad') $rolClass = 'role-contabilidad';
                                elseif ($rol_name === 'Almacén') $rolClass = 'role-almacen';
                                elseif ($rol_name === 'Producción') $rolClass = 'role-produccion';
                            ?>
                            <tr>
                                <td>
                                    <div style="display:flex; align-items:center; gap:8px;">
                                        <?php if(!empty($u['foto_url'])): ?>
                                            <img src="<?= htmlspecialchars($u['foto_url']) ?>" alt="Avatar" class="usr-avatar-sm" style="object-fit:cover;">
                                        <?php else: ?>
                                            <div class="usr-avatar-sm"><?= $initial ?></div>
                                        <?php endif; ?>
                                        <div>
                                            <strong style="color:#111827;"><?= htmlspecialchars($u['username']) ?></strong>
                                            <div style="font-size:0.75rem; color:#6B7280;">ID: <?= $u['id'] ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><strong style="color:#374151;"><?= htmlspecialchars($u['nombre_completo'] ?: '—') ?></strong></td>
                                <td style="font-size:0.83rem; color:#4B5563;"><?= htmlspecialchars($u['email'] ?: '—') ?></td>
                                <td><span class="role-pill <?= $rolClass ?>"><?= htmlspecialchars($rol_name) ?></span></td>
                                <td style="font-size:0.83rem; color:#4B5563;">
                                    <i class="fas fa-shop" style="color:#9CA3AF; margin-right:4px; font-size:0.78rem;"></i>
                                    <?= htmlspecialchars($u['local_nombre'] ?: 'Sin Asignar') ?>
                                </td>
                                <td>
                                    <span class="status-pill <?= $u['estado'] === 'Activo' ? 'activo' : 'inactivo' ?>">
                                        <?= htmlspecialchars($u['estado']) ?>
                                    </span>
                                </td>
                                <td style="text-align:center;">
                                    <div class="usr-actions">
                                        <button type="button" class="btn-action-soft edit" title="Editar Usuario" onclick="editUsuario(<?= $u['id'] ?>)">
                                            <i class="fas fa-pen"></i>
                                        </button>
                                        <?php if ((int)$u['id'] !== (int)$_SESSION['user_id']): ?>
                                            <button type="button" class="btn-action-soft delete" title="Eliminar Usuario" onclick="eliminarUsuario(<?= $u['id'] ?>, '<?= addslashes($u['username']) ?>')">
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

        </div>
    </div>
</div>

<!-- Modal: Crear / Editar Usuario -->
<div class="modal-overlay" id="modalUsuario">
    <div class="modal-box">
        <div style="padding:1.25rem 1.5rem; border-bottom:1px solid #E5E7EB; display:flex; justify-content:space-between; align-items:center;">
            <h3 id="modalTitle" style="margin:0; font-size:1.1rem; font-weight:800; color:#111827;">Nuevo Usuario</h3>
            <button type="button" onclick="closeModal()" style="background:none; border:none; font-size:1.2rem; cursor:pointer;">&times;</button>
        </div>
        <form id="usuarioForm" onsubmit="guardarUsuario(event)" autocomplete="off">
            <div style="padding:1.4rem;">
                <input type="hidden" id="usuarioId" name="id">
                <input type="hidden" id="usuarioAction" name="action" value="create">

                <div class="form-group" style="margin-bottom:0.8rem;">
                    <label style="display:block; font-size:0.76rem; font-weight:700; text-transform:uppercase; color:#4B5563; margin-bottom:0.35rem;">Nombre de Usuario (Username) *</label>
                    <input type="text" id="form_username" name="username" class="usr-input" style="padding:0.55rem;" required placeholder="Ej: jflores">
                </div>

                <div class="form-group" style="margin-bottom:0.8rem;">
                    <label style="display:block; font-size:0.76rem; font-weight:700; text-transform:uppercase; color:#4B5563; margin-bottom:0.35rem;">Nombre Completo</label>
                    <input type="text" id="form_nombre_completo" name="nombre_completo" class="usr-input" style="padding:0.55rem;" placeholder="Ej: Juan Flores Pérez">
                </div>

                <div class="form-group" style="margin-bottom:0.8rem;">
                    <label style="display:block; font-size:0.76rem; font-weight:700; text-transform:uppercase; color:#4B5563; margin-bottom:0.35rem;">Correo Electrónico</label>
                    <input type="email" id="form_email" name="email" class="usr-input" style="padding:0.55rem;" placeholder="usuario@carpicenter.com">
                </div>

                <div class="form-group" style="margin-bottom:0.8rem;">
                    <label style="display:block; font-size:0.76rem; font-weight:700; text-transform:uppercase; color:#4B5563; margin-bottom:0.35rem;" id="lblPassword">Contraseña *</label>
                    <input type="password" id="form_password" name="password" class="usr-input" style="padding:0.55rem;" placeholder="••••••••">
                    <small id="pwdHelp" style="display:none; color:#6B7280; font-size:0.72rem; margin-top:2px;">Dejar vacío para conservar la contraseña actual.</small>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.8rem; margin-bottom:0.8rem;">
                    <div>
                        <label style="display:block; font-size:0.76rem; font-weight:700; text-transform:uppercase; color:#4B5563; margin-bottom:0.35rem;">Rol Asignado *</label>
                        <select id="form_rol_id" name="rol_id" class="usr-input" style="padding:0.55rem;" required>
                            <?php foreach($roles as $r): ?>
                                <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="display:block; font-size:0.76rem; font-weight:700; text-transform:uppercase; color:#4B5563; margin-bottom:0.35rem;">Local / Sede</label>
                        <select id="form_local_id" name="local_id" class="usr-input" style="padding:0.55rem;">
                            <option value="">Sin Asignar</option>
                            <?php foreach($locales as $l): ?>
                                <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div>
                    <label style="display:block; font-size:0.76rem; font-weight:700; text-transform:uppercase; color:#4B5563; margin-bottom:0.35rem;">Estado de la Cuenta</label>
                    <select id="form_estado" name="estado" class="usr-input" style="padding:0.55rem;">
                        <option value="Activo">Activo</option>
                        <option value="Inactivo">Inactivo</option>
                    </select>
                </div>
            </div>
            <div style="padding:1rem 1.4rem; background:#F9FAFB; border-top:1px solid #E5E7EB; display:flex; justify-content:flex-end; gap:0.75rem;">
                <button type="button" onclick="closeModal()" class="btn btn-outline">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Guardar Usuario</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal() {
    document.getElementById('usuarioForm').reset();
    document.getElementById('usuarioId').value = '';
    document.getElementById('usuarioAction').value = 'create';
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-plus" style="color:#E31E24;margin-right:0.5rem;"></i>Nuevo Usuario';
    document.getElementById('form_password').required = true;
    document.getElementById('pwdHelp').style.display = 'none';
    document.getElementById('modalUsuario').classList.add('open');
}

function closeModal() {
    document.getElementById('modalUsuario').classList.remove('open');
}

function editUsuario(id) {
    fetch('usuario_controller.php?action=get&id=' + id)
        .then(r => r.json())
        .then(data => {
            if (data.error) { alert(data.error); return; }
            document.getElementById('usuarioId').value = data.id;
            document.getElementById('usuarioAction').value = 'update';
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-pen" style="color:#E31E24;margin-right:0.5rem;"></i>Editar Usuario: ' + data.username;
            document.getElementById('form_username').value = data.username;
            document.getElementById('form_nombre_completo').value = data.nombre_completo || '';
            document.getElementById('form_email').value = data.email || '';
            document.getElementById('form_password').value = '';
            document.getElementById('form_password').required = false;
            document.getElementById('pwdHelp').style.display = 'block';
            document.getElementById('form_rol_id').value = data.rol_id || 1;
            document.getElementById('form_local_id').value = data.local_id || '';
            document.getElementById('form_estado').value = data.estado || 'Activo';
            document.getElementById('modalUsuario').classList.add('open');
        })
        .catch(() => alert('Error al cargar datos del usuario'));
}

function guardarUsuario(e) {
    e.preventDefault();
    const form = new FormData(document.getElementById('usuarioForm'));
    fetch('usuario_controller.php', {
        method: 'POST',
        body: form
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            location.reload();
        } else {
            alert(res.message || 'Error al guardar');
        }
    })
    .catch(() => location.reload());
}

function eliminarUsuario(id, username) {
    if (confirm('⚠️ ¿Seguro de ELIMINAR al usuario "' + username + '"? Esta acción no se puede deshacer.')) {
        const form = new FormData();
        form.append('action', 'delete');
        form.append('id', id);
        fetch('usuario_controller.php', {
            method: 'POST',
            body: form
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                location.reload();
            } else {
                alert(res.message || 'No se pudo eliminar el usuario');
            }
        })
        .catch(() => location.reload());
    }
}
</script>

<?php include 'partials/footer.php'; ?>
</body>
</html>
