<?php
require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../config/db.php';

$mensaje = '';
$tipo_mensaje = '';

// Endpoint AJAX para obtener documentos de un trabajador
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_docs') {
    $p_id = intval($_GET['personal_id'] ?? 0);
    $stmt_docs = $db->prepare("SELECT * FROM personal_documentos WHERE personal_id = ? ORDER BY id DESC");
    $stmt_docs->execute([$p_id]);
    header('Content-Type: application/json');
    echo json_encode($stmt_docs->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// Procesar Acciones CRUD de Personal y Documentos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'guardar_personal') {
        $id = intval($_POST['id'] ?? 0);
        $nombres = strtoupper(trim($_POST['nombres'] ?? ''));
        $area = strtoupper(trim($_POST['area'] ?? ''));
        $categoria = $_POST['categoria'] ?? 'PRODUCCION';
        $tipo_trabajador = $_POST['tipo_trabajador'] ?? 'FIJO';
        $cuenta_bancaria = trim($_POST['cuenta_bancaria'] ?? '');
        $metodo_pago = $_POST['metodo_pago'] ?? 'DEPOSITO';
        $sueldo_mensual = floatval($_POST['sueldo_mensual'] ?? 0);
        $base_semanal = floatval($_POST['base_semanal'] ?? 0);
        $horas_dia = intval($_POST['horas_dia'] ?? 8);
        $dias_semana = intval($_POST['dias_semana'] ?? 6);
        $base_dia = floatval($_POST['base_dia'] ?? 0);
        $pago_hora = floatval($_POST['pago_hora'] ?? 0);

        if ($base_dia <= 0 && $base_semanal > 0 && $dias_semana > 0) {
            $base_dia = round($base_semanal / $dias_semana, 2);
        }
        if ($pago_hora <= 0 && $base_dia > 0 && $horas_dia > 0) {
            $pago_hora = round($base_dia / $horas_dia, 2);
        }

        if (empty($nombres) || empty($area)) {
            $mensaje = 'El nombre y el área son obligatorios.';
            $tipo_mensaje = 'danger';
        } else {
            try {
                if ($id > 0) {
                    $stmt = $db->prepare("
                        UPDATE personal SET 
                            nombres = ?, area = ?, categoria = ?, tipo_trabajador = ?,
                            cuenta_bancaria = ?, metodo_pago = ?, sueldo_mensual = ?,
                            base_dia = ?, base_semanal = ?, pago_hora = ?, horas_dia = ?, dias_semana = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$nombres, $area, $categoria, $tipo_trabajador, $cuenta_bancaria, $metodo_pago, $sueldo_mensual, $base_dia, $base_semanal, $pago_hora, $horas_dia, $dias_semana, $id]);
                    $mensaje = 'Datos del personal actualizados correctamente.';
                } else {
                    $stmt = $db->prepare("
                        INSERT INTO personal (nombres, area, categoria, tipo_trabajador, cuenta_bancaria, metodo_pago, sueldo_mensual, base_dia, base_semanal, pago_hora, horas_dia, dias_semana)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$nombres, $area, $categoria, $tipo_trabajador, $cuenta_bancaria, $metodo_pago, $sueldo_mensual, $base_dia, $base_semanal, $pago_hora, $horas_dia, $dias_semana]);
                    $mensaje = 'Nuevo trabajador registrado exitosamente.';
                }
                $tipo_mensaje = 'success';
            } catch (Exception $e) {
                $mensaje = 'Error al guardar personal: ' . $e->getMessage();
                $tipo_mensaje = 'danger';
            }
        }
    } elseif ($action === 'subir_documento') {
        $personal_id = intval($_POST['personal_id'] ?? 0);
        $tipo_documento = trim($_POST['tipo_documento'] ?? 'OTRO');
        
        if ($personal_id > 0 && !empty($_FILES['archivo']['name'])) {
            $file = $_FILES['archivo'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx'];
            
            if (in_array($ext, $allowed)) {
                $upload_dir = __DIR__ . '/../../uploads/personal_docs/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                
                $safe_filename = time() . '_' . rand(1000, 9999) . '.' . $ext;
                $dest = $upload_dir . $safe_filename;
                
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $size_kb = round($file['size'] / 1024);
                    $stmt_ins = $db->prepare("
                        INSERT INTO personal_documentos (personal_id, tipo_documento, nombre_archivo, ruta_archivo, tamanio_kb)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt_ins->execute([$personal_id, $tipo_documento, $file['name'], 'uploads/personal_docs/' . $safe_filename, $size_kb]);
                    $mensaje = 'Documento subido y guardado en el legajo correctamente.';
                    $tipo_mensaje = 'success';
                } else {
                    $mensaje = 'No se pudo mover el archivo al servidor.';
                    $tipo_mensaje = 'danger';
                }
            } else {
                $mensaje = 'Formato de archivo no permitido. Solo se permiten PDF, imágenes o Word.';
                $tipo_mensaje = 'danger';
            }
        }
    } elseif ($action === 'eliminar_documento') {
        $doc_id = intval($_POST['doc_id'] ?? 0);
        $stmt_doc = $db->prepare("SELECT * FROM personal_documentos WHERE id = ?");
        $stmt_doc->execute([$doc_id]);
        $doc = $stmt_doc->fetch(PDO::FETCH_ASSOC);
        if ($doc) {
            $file_path = __DIR__ . '/../../' . $doc['ruta_archivo'];
            if (file_exists($file_path)) @unlink($file_path);
            $db->prepare("DELETE FROM personal_documentos WHERE id = ?")->execute([$doc_id]);
            $mensaje = 'Documento eliminado del legajo.';
            $tipo_mensaje = 'success';
        }
    } elseif ($action === 'cambiar_estado') {
        $id = intval($_POST['id'] ?? 0);
        $nuevo_estado = $_POST['nuevo_estado'] === 'ACTIVO' ? 'ACTIVO' : 'INACTIVO';
        if ($id > 0) {
            $db->prepare("UPDATE personal SET estado = ? WHERE id = ?")->execute([$nuevo_estado, $id]);
            $mensaje = 'Estado del trabajador actualizado.';
            $tipo_mensaje = 'success';
        }
    } elseif ($action === 'eliminar_personal') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $db->prepare("DELETE FROM personal WHERE id = ?")->execute([$id]);
                $mensaje = 'Trabajador eliminado del padrón.';
                $tipo_mensaje = 'success';
            } catch (Exception $e) {
                $mensaje = 'No se puede eliminar el trabajador porque ya tiene registros en planillas anteriores.';
                $tipo_mensaje = 'danger';
            }
        }
    }
}

// Filtros
$filtro_categoria = $_GET['categoria'] ?? 'TODOS';
$filtro_estado = $_GET['estado'] ?? 'ACTIVO';
$search = trim($_GET['search'] ?? '');

$where = ["1=1"];
$params = [];

if ($filtro_categoria !== 'TODOS') {
    $where[] = "p.categoria = ?";
    $params[] = $filtro_categoria;
}
if ($filtro_estado !== 'TODOS') {
    $where[] = "p.estado = ?";
    $params[] = $filtro_estado;
}
if (!empty($search)) {
    $where[] = "(p.nombres ILIKE ? OR p.area ILIKE ? OR p.cuenta_bancaria ILIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_sql = implode(" AND ", $where);
$stmt = $db->prepare("
    SELECT p.*, COALESCE(d.cant_docs, 0) as cant_docs
    FROM personal p
    LEFT JOIN (
        SELECT personal_id, COUNT(*) as cant_docs
        FROM personal_documentos
        GROUP BY personal_id
    ) d ON d.personal_id = p.id
    WHERE $where_sql 
    ORDER BY 
        CASE 
            WHEN p.categoria = 'ADMINISTRATIVO' THEN 1 
            WHEN p.categoria = 'TIENDAS' THEN 2 
            ELSE 3 
        END, p.orden ASC, p.nombres ASC
");
$stmt->execute($params);
$personal_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas
$tot_admin = $db->query("SELECT COUNT(*) FROM personal WHERE categoria = 'ADMINISTRATIVO' AND estado = 'ACTIVO'")->fetchColumn();
$tot_tiendas = $db->query("SELECT COUNT(*) FROM personal WHERE categoria = 'TIENDAS' AND estado = 'ACTIVO'")->fetchColumn();
$tot_produccion = $db->query("SELECT COUNT(*) FROM personal WHERE categoria = 'PRODUCCION' AND estado = 'ACTIVO'")->fetchColumn();
$tot_eventuales = $db->query("SELECT COUNT(*) FROM personal WHERE tipo_trabajador = 'EVENTUAL' AND estado = 'ACTIVO'")->fetchColumn();

$page_title = 'Padrón de Personal';
$page_subtitle = 'Control maestro de colaboradores, legajos digitales, cuentas y sueldos';
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
        .staff-hero {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.25rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .staff-hero-title h1 {
            font-size: 1.45rem;
            font-weight: 800;
            color: #111827;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .staff-hero-title p {
            color: #6B7280;
            font-size: 0.84rem;
            margin: 0.25rem 0 0 0;
        }

        /* KPIs Grid */
        .staff-kpis-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.3rem;
        }
        .staff-kpi-card {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 14px;
            padding: 1rem 1.2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        .staff-kpi-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }
        .icon-admin { background: #FEF3C7; color: #D97706; }
        .icon-tiendas { background: #E0F2FE; color: #0284C7; }
        .icon-prod { background: #FFEDD5; color: #EA580C; }
        .icon-eventual { background: #F3E8FF; color: #9333EA; }

        .staff-kpi-info span.label {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
            color: #6B7280;
            display: block;
        }
        .staff-kpi-info h3 {
            font-size: 1.4rem;
            font-weight: 800;
            color: #111827;
            margin: 0.1rem 0 0 0;
        }

        /* Filters Bar */
        .staff-filters-bar {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 14px;
            padding: 0.85rem 1.2rem;
            margin-bottom: 1.25rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.8rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        .staff-search-input {
            padding: 0.55rem 0.9rem;
            border-radius: 9px;
            border: 1px solid #D1D5DB;
            background: #F9FAFB;
            font-size: 0.85rem;
            width: 250px;
            outline: none;
        }
        .staff-search-input:focus {
            background: #FFFFFF;
            border-color: #E31E24;
            box-shadow: 0 0 0 3px rgba(227,30,36,0.1);
        }
        .staff-select {
            padding: 0.55rem 0.85rem;
            border-radius: 9px;
            border: 1px solid #D1D5DB;
            background: #F9FAFB;
            font-size: 0.85rem;
            outline: none;
        }

        /* Table Card */
        .staff-table-card {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        .staff-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.85rem;
        }
        .staff-table th {
            background: #F9FAFB;
            color: #6B7280;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 0.85rem 1rem;
            border-bottom: 1px solid #E5E7EB;
        }
        .staff-table td {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid #F3F4F6;
            color: #374151;
            vertical-align: middle;
        }
        .staff-table tbody tr:hover {
            background: #F8FAFC;
        }

        /* Badges */
        .badge-cat {
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 0.73rem;
            font-weight: 800;
            text-transform: uppercase;
            display: inline-block;
        }
        .cat-admin { background: #FEF3C7; color: #92400E; border: 1px solid #FDE68A; }
        .cat-tiendas { background: #E0F2FE; color: #0369A1; border: 1px solid #BAE6FD; }
        .cat-prod { background: #FFEDD5; color: #C2410C; border: 1px solid #FED7AA; }

        .badge-tipo-trab {
            padding: 2px 7px;
            border-radius: 5px;
            font-size: 0.7rem;
            font-weight: 700;
        }
        .tipo-fijo { background: #DCFCE7; color: #15803D; }
        .tipo-eventual { background: #F3E8FF; color: #7E22CE; }

        /* Modals */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(5px);
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .modal-overlay.open { display: flex; }
        .staff-modal-box {
            background: #FFFFFF;
            border-radius: 18px;
            width: 100%;
            max-width: 560px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.22);
            overflow: hidden;
            animation: popIn 0.2s ease-out;
        }
        .docs-modal-box {
            background: #FFFFFF;
            border-radius: 18px;
            width: 100%;
            max-width: 650px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.22);
            overflow: hidden;
            animation: popIn 0.2s ease-out;
        }
        @keyframes popIn {
            from { opacity: 0; transform: scale(0.97); }
            to { opacity: 1; transform: scale(1); }
        }
        .modal-header-staff {
            padding: 1.1rem 1.4rem;
            border-bottom: 1px solid #F3F4F6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-body-staff {
            padding: 1.3rem 1.4rem;
            display: flex;
            flex-direction: column;
            gap: 0.9rem;
            max-height: 75vh;
            overflow-y: auto;
        }
        .modal-footer-staff {
            padding: 0.9rem 1.4rem;
            background: #F9FAFB;
            border-top: 1px solid #F3F4F6;
            display: flex;
            justify-content: flex-end;
            gap: 0.6rem;
        }
        .form-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.8rem;
        }
        .field-lbl {
            display: block;
            font-size: 0.75rem;
            font-weight: 700;
            color: #374151;
            text-transform: uppercase;
            margin-bottom: 0.25rem;
        }
        .form-ctrl {
            width: 100%;
            padding: 0.58rem 0.8rem;
            border-radius: 8px;
            border: 1px solid #D1D5DB;
            background: #F9FAFB;
            font-size: 0.85rem;
            box-sizing: border-box;
            outline: none;
        }
        .form-ctrl:focus {
            background: #FFFFFF;
            border-color: #E31E24;
            box-shadow: 0 0 0 3px rgba(227,30,36,0.1);
        }

        /* Document Item Card */
        .doc-item-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem 1rem;
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 10px;
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="app-wrapper">
        <?php include '../../views/partials/sidebar.php'; ?>
        <div class="main-content">
            <?php include '../../views/partials/header.php'; ?>

            <div class="page-content">
                
                <?php if (!empty($mensaje)): ?>
                    <div style="background: <?= $tipo_mensaje === 'success' ? '#ECFDF5' : '#FEF2F2' ?>; border-left: 4px solid <?= $tipo_mensaje === 'success' ? '#059669' : '#DC2626' ?>; color: <?= $tipo_mensaje === 'success' ? '#065F46' : '#991B1B' ?>; padding: 10px 14px; border-radius: 8px; font-size: 0.86rem; margin-bottom: 1.2rem; display: flex; align-items: center; justify-content: space-between;">
                        <div><i class="fas <?= $tipo_mensaje === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle' ?>"></i> <?= htmlspecialchars($mensaje) ?></div>
                        <button onclick="this.parentElement.remove()" style="background:none; border:none; color:inherit; cursor:pointer;"><i class="fas fa-times"></i></button>
                    </div>
                <?php endif; ?>

                <!-- Hero Section -->
                <div class="staff-hero">
                    <div class="staff-hero-title">
                        <h1><i class="fas fa-users-gear" style="color:#E31E24;"></i> <?= $page_title ?></h1>
                        <p><?= $page_subtitle ?></p>
                    </div>
                    <div style="display:flex; gap:0.6rem;">
                        <a href="planilla.php" class="btn btn-outline" style="display:inline-flex; align-items:center; gap:6px;">
                            <i class="fas fa-calendar-week"></i> Ir a Planilla Semanal
                        </a>
                        <button type="button" class="btn btn-primary" onclick="openStaffModal()" style="display:inline-flex; align-items:center; gap:6px;">
                            <i class="fas fa-user-plus"></i> + Nuevo Trabajador
                        </button>
                    </div>
                </div>

                <!-- KPI Cards -->
                <div class="staff-kpis-grid">
                    <div class="staff-kpi-card">
                        <div class="staff-kpi-icon icon-admin"><i class="fas fa-user-tie"></i></div>
                        <div class="staff-kpi-info">
                            <span class="label">Administrativos</span>
                            <h3><?= $tot_admin ?></h3>
                        </div>
                    </div>
                    <div class="staff-kpi-card">
                        <div class="staff-kpi-icon icon-tiendas"><i class="fas fa-store"></i></div>
                        <div class="staff-kpi-info">
                            <span class="label">Tiendas / Ventas</span>
                            <h3><?= $tot_tiendas ?></h3>
                        </div>
                    </div>
                    <div class="staff-kpi-card">
                        <div class="staff-kpi-icon icon-prod"><i class="fas fa-industry"></i></div>
                        <div class="staff-kpi-info">
                            <span class="label">Producción / Taller</span>
                            <h3><?= $tot_produccion ?></h3>
                        </div>
                    </div>
                    <div class="staff-kpi-card">
                        <div class="staff-kpi-icon icon-eventual"><i class="fas fa-handshake"></i></div>
                        <div class="staff-kpi-info">
                            <span class="label">Eventuales / Terceros</span>
                            <h3><?= $tot_eventuales ?></h3>
                        </div>
                    </div>
                </div>

                <!-- Filters Bar -->
                <div class="staff-filters-bar">
                    <form method="GET" action="personal.php" style="display:flex; gap:0.75rem; flex-wrap:wrap; align-items:center;">
                        <input type="text" name="search" class="staff-search-input" placeholder="Buscar por nombre, cargo, cuenta..." value="<?= htmlspecialchars($search) ?>">
                        
                        <select name="categoria" class="staff-select" onchange="this.form.submit()">
                            <option value="TODOS" <?= $filtro_categoria==='TODOS'?'selected':'' ?>>Todas las Categorías</option>
                            <option value="ADMINISTRATIVO" <?= $filtro_categoria==='ADMINISTRATIVO'?'selected':'' ?>>🟡 Administrativos</option>
                            <option value="TIENDAS" <?= $filtro_categoria==='TIENDAS'?'selected':'' ?>>🔵 Tiendas / Ventas</option>
                            <option value="PRODUCCION" <?= $filtro_categoria==='PRODUCCION'?'selected':'' ?>>🟠 Producción / Taller</option>
                        </select>

                        <select name="estado" class="staff-select" onchange="this.form.submit()">
                            <option value="TODOS" <?= $filtro_estado==='TODOS'?'selected':'' ?>>Todos los Estados</option>
                            <option value="ACTIVO" <?= $filtro_estado==='ACTIVO'?'selected':'' ?>>Activos</option>
                            <option value="INACTIVO" <?= $filtro_estado==='INACTIVO'?'selected':'' ?>>Inactivos</option>
                        </select>

                        <button type="submit" class="btn btn-outline" style="padding:0.55rem 0.9rem;">
                            <i class="fas fa-search"></i> Filtrar
                        </button>
                    </form>
                    
                    <span style="font-size:0.8rem; color:#6B7280; font-weight:600;">
                        Mostrando <strong><?= count($personal_list) ?></strong> colaboradores
                    </span>
                </div>

                <!-- Table -->
                <div class="staff-table-card">
                    <div class="table-responsive">
                        <table class="staff-table">
                            <thead>
                                <tr>
                                    <th style="width:40px; text-align:center;">#</th>
                                    <th>Colaborador</th>
                                    <th>Área</th>
                                    <th>Categoría</th>
                                    <th>Tipo</th>
                                    <th>Cuenta BCP / Yape</th>
                                    <th style="text-align:right;">Base Semanal</th>
                                    <th style="text-align:center;">Legajo</th>
                                    <th style="text-align:center;">Estado</th>
                                    <th style="text-align:center; width:90px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($personal_list)): ?>
                                    <tr>
                                        <td colspan="10" style="text-align:center; padding:2rem; color:#9CA3AF;">
                                            <i class="fas fa-users-slash" style="font-size:2rem; margin-bottom:0.5rem; display:block;"></i>
                                            No se encontraron colaboradores registrados con los filtros seleccionados.
                                        </td>
                                    </tr>
                                <?php else: 
                                    $i = 1;
                                    foreach ($personal_list as $p): 
                                        $catClass = ($p['categoria'] === 'ADMINISTRATIVO') ? 'cat-admin' : (($p['categoria'] === 'TIENDAS') ? 'cat-tiendas' : 'cat-prod');
                                ?>
                                    <tr>
                                        <td style="text-align:center; font-weight:700; color:#9CA3AF;"><?= $i++ ?></td>
                                        <td style="font-weight:700; color:#111827;">
                                            <div style="display:flex; align-items:center; gap:8px;">
                                                <i class="fas fa-user-circle" style="color:#CBD5E1; font-size:1.1rem;"></i>
                                                <?= htmlspecialchars($p['nombres']) ?>
                                            </div>
                                        </td>
                                        <td><span style="font-weight:600; font-size:0.82rem; color:#4B5563;"><?= htmlspecialchars($p['area']) ?></span></td>
                                        <td><span class="badge-cat <?= $catClass ?>"><?= htmlspecialchars($p['categoria']) ?></span></td>
                                        <td>
                                            <span class="badge-tipo-trab <?= $p['tipo_trabajador']==='EVENTUAL' ? 'tipo-eventual' : 'tipo-fijo' ?>">
                                                <?= $p['tipo_trabajador']==='EVENTUAL' ? 'Eventual' : 'Fijo' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span style="font-family:monospace; font-weight:700; color:#1E293B; background:#F1F5F9; padding:2px 6px; border-radius:5px; font-size:0.8rem;">
                                                <?= htmlspecialchars($p['cuenta_bancaria'] ?: '—') ?>
                                            </span>
                                        </td>
                                        <td style="text-align:right; font-weight:700; color:#059669;">
                                            <?= $p['base_semanal'] > 0 ? 'S/ ' . number_format($p['base_semanal'], 2) : '<span style="color:#CBD5E1;">Variable</span>' ?>
                                        </td>
                                        
                                        <!-- Botón Legajo / Documentos con contador -->
                                        <td style="text-align:center;">
                                            <button type="button" class="btn-action-soft" title="Ver / Adjuntar Documentos" onclick="openDocsModal(<?= $p['id'] ?>, '<?= addslashes($p['nombres']) ?>')" style="padding:4px 8px; border-radius:6px; border:none; background:#EFF6FF; color:#2563EB; font-weight:700; font-size:0.78rem; display:inline-flex; align-items:center; gap:5px; cursor:pointer;">
                                                <i class="fas fa-folder-open"></i> 
                                                <span><?= $p['cant_docs'] > 0 ? $p['cant_docs'] . ' docs' : 'Adjuntar' ?></span>
                                            </button>
                                        </td>

                                        <td style="text-align:center;">
                                            <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:<?= $p['estado']==='ACTIVO'?'#10B981':'#9CA3AF' ?>; margin-right:4px;"></span>
                                            <span style="font-size:0.75rem; font-weight:700; color:<?= $p['estado']==='ACTIVO'?'#059669':'#6B7280' ?>;"><?= $p['estado'] ?></span>
                                        </td>
                                        <td style="text-align:center;">
                                            <div style="display:flex; justify-content:center; gap:5px;">
                                                <button type="button" class="btn-action-soft edit" title="Editar" onclick='editStaff(<?= json_encode($p) ?>)' style="padding:5px 8px; border-radius:6px; border:none; background:#F3F4F6; cursor:pointer; color:#2563EB;">
                                                    <i class="fas fa-pen"></i>
                                                </button>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('¿Seguro de eliminar este trabajador?');">
                                                    <input type="hidden" name="action" value="eliminar_personal">
                                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                                    <button type="submit" class="btn-action-soft delete" title="Eliminar" style="padding:5px 8px; border-radius:6px; border:none; background:#F3F4F6; cursor:pointer; color:#DC2626;">
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

            </div>
        </div>
    </div>

    <!-- Modal Form Personal (Ultra Limpio & Elegante) -->
    <div class="modal-overlay" id="modalStaff">
        <div class="staff-modal-box">
            <div class="modal-header-staff">
                <h3 style="margin:0; font-size:1.1rem; font-weight:800; color:#111827; display:flex; align-items:center; gap:8px;" id="modalStaffTitle">
                    <i class="fas fa-user-plus" style="color:#E31E24;"></i> Nuevo Trabajador
                </h3>
                <button type="button" onclick="closeStaffModal()" style="background:#F3F4F6; border:none; width:30px; height:30px; border-radius:8px; font-size:0.95rem; color:#6B7280; cursor:pointer;">&times;</button>
            </div>
            <form method="POST" action="personal.php">
                <input type="hidden" name="action" value="guardar_personal">
                <input type="hidden" name="id" id="staff_id" value="0">
                
                <div class="modal-body-staff">
                    <div>
                        <label class="field-lbl">Nombres y Apellidos *</label>
                        <input type="text" name="nombres" id="staff_nombres" class="form-ctrl" placeholder="Ingrese nombres y apellidos" required style="font-weight:700;">
                    </div>

                    <div class="form-grid-2">
                        <div>
                            <label class="field-lbl">Área / Cargo *</label>
                            <input type="text" name="area" id="staff_area" class="form-ctrl" placeholder="Ingrese área o cargo" required>
                        </div>
                        <div>
                            <label class="field-lbl">Área en Planilla *</label>
                            <select name="categoria" id="staff_categoria" class="form-ctrl" required>
                                <option value="ADMINISTRATIVO">🟡 Administrativo</option>
                                <option value="TIENDAS">🔵 Tiendas / Ventas</option>
                                <option value="PRODUCCION">🟠 Producción / Taller</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div>
                            <label class="field-lbl">Tipo</label>
                            <select name="tipo_trabajador" id="staff_tipo" class="form-ctrl">
                                <option value="FIJO">Fijo (Semanal)</option>
                                <option value="EVENTUAL">Eventual (Soldador, Albañil)</option>
                            </select>
                        </div>
                        <div>
                            <label class="field-lbl">Forma de Pago</label>
                            <select name="metodo_pago" id="staff_metodo" class="form-ctrl">
                                <option value="DEPOSITO">Depósito BCP</option>
                                <option value="YAPE">Yape / Plin</option>
                                <option value="EFECTIVO">Efectivo</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="field-lbl">N° Cuenta o Celular Yape</label>
                        <input type="text" name="cuenta_bancaria" id="staff_cuenta" class="form-ctrl" placeholder="Ingrese número de cuenta o celular">
                    </div>

                    <div>
                        <label class="field-lbl">Sueldo Base Semanal (S/)</label>
                        <input type="number" step="0.01" name="base_semanal" id="staff_base_semanal" class="form-ctrl" placeholder="0.00" style="font-weight:700;">
                    </div>
                </div>

                <div class="modal-footer-staff">
                    <button type="button" class="btn btn-outline" onclick="closeStaffModal()" style="border-radius:8px;">Cancelar</button>
                    <button type="submit" class="btn btn-primary" style="border-radius:8px; font-weight:700;"><i class="fas fa-save"></i> Guardar Trabajador</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Legajo / Documentos del Trabajador -->
    <div class="modal-overlay" id="modalDocs">
        <div class="docs-modal-box">
            <div class="modal-header-staff">
                <div>
                    <h3 style="margin:0; font-size:1.1rem; font-weight:800; color:#111827; display:flex; align-items:center; gap:8px;">
                        <i class="fas fa-folder-open" style="color:#2563EB;"></i> Legajo Digital de Documentos
                    </h3>
                    <span id="docWorkerName" style="font-size:0.84rem; font-weight:700; color:#4B5563; margin-top:3px; display:block;"></span>
                </div>
                <button type="button" onclick="closeDocsModal()" style="background:#F3F4F6; border:none; width:30px; height:30px; border-radius:8px; font-size:0.95rem; color:#6B7280; cursor:pointer;">&times;</button>
            </div>
            
            <div class="modal-body-staff">
                <!-- Formulario de Subida de Documento -->
                <div style="background:#F8FAFC; border:1px dashed #CBD5E1; border-radius:12px; padding:1.1rem;">
                    <h4 style="margin:0 0 0.8rem 0; font-size:0.88rem; font-weight:800; color:#1E293B;">
                        <i class="fas fa-cloud-arrow-up" style="color:#2563EB;"></i> Adjuntar Nuevo Documento
                    </h4>
                    <form method="POST" action="personal.php" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="subir_documento">
                        <input type="hidden" name="personal_id" id="doc_personal_id" value="0">
                        
                        <div class="form-grid-2" style="margin-bottom:0.75rem;">
                            <div>
                                <label class="field-lbl">Tipo de Documento *</label>
                                <select name="tipo_documento" class="form-ctrl" required>
                                    <option value="COPIA_DNI">📄 Copia de DNI</option>
                                    <option value="CONTRATO">📝 Contrato de Trabajo</option>
                                    <option value="CURRICULUM">💼 Currículum Vitae (CV)</option>
                                    <option value="ANTECEDENTES">🛡️ Certificado / Antecedentes</option>
                                    <option value="RECIBO_SERVICIO">🏠 Recibo de Servicios</option>
                                    <option value="OTRO">📁 Otro Documento</option>
                                </select>
                            </div>
                            <div>
                                <label class="field-lbl">Seleccionar Archivo (PDF o Imagen) *</label>
                                <input type="file" name="archivo" class="form-ctrl" required accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx" style="padding:4px 8px;">
                            </div>
                        </div>

                        <div style="text-align:right;">
                            <button type="submit" class="btn btn-primary" style="font-size:0.82rem; padding:0.45rem 1rem;">
                                <i class="fas fa-upload"></i> Subir Documento
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Lista de Documentos Subidos -->
                <div>
                    <h4 style="margin:0.5rem 0 0.6rem 0; font-size:0.88rem; font-weight:800; color:#1E293B;">
                        <i class="fas fa-file-lines"></i> Documentos Registrados
                    </h4>
                    <div id="docsListContainer">
                        <div style="text-align:center; padding:1.5rem; color:#9CA3AF;">
                            <i class="fas fa-spinner fa-spin"></i> Cargando documentos...
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer-staff">
                <button type="button" class="btn btn-outline" onclick="closeDocsModal()" style="border-radius:8px;">Cerrar</button>
            </div>
        </div>
    </div>

    <script>
    function openStaffModal() {
        document.getElementById('modalStaffTitle').innerHTML = '<i class="fas fa-user-plus" style="color:#E31E24;"></i> Nuevo Trabajador';
        document.getElementById('staff_id').value = '0';
        document.getElementById('staff_nombres').value = '';
        document.getElementById('staff_area').value = '';
        document.getElementById('staff_categoria').value = 'PRODUCCION';
        document.getElementById('staff_tipo').value = 'FIJO';
        document.getElementById('staff_metodo').value = 'DEPOSITO';
        document.getElementById('staff_cuenta').value = '';
        document.getElementById('staff_base_semanal').value = '';
        document.getElementById('modalStaff').classList.add('open');
    }

    function editStaff(p) {
        document.getElementById('modalStaffTitle').innerHTML = '<i class="fas fa-user-edit" style="color:#E31E24;"></i> Editar Trabajador';
        document.getElementById('staff_id').value = p.id;
        document.getElementById('staff_nombres').value = p.nombres;
        document.getElementById('staff_area').value = p.area;
        document.getElementById('staff_categoria').value = p.categoria;
        document.getElementById('staff_tipo').value = p.tipo_trabajador || 'FIJO';
        document.getElementById('staff_metodo').value = p.metodo_pago || 'DEPOSITO';
        document.getElementById('staff_cuenta').value = p.cuenta_bancaria || '';
        document.getElementById('staff_base_semanal').value = p.base_semanal || '';
        document.getElementById('modalStaff').classList.add('open');
    }

    function closeStaffModal() {
        document.getElementById('modalStaff').classList.remove('open');
    }

    // Modal de Legajo / Documentos
    function openDocsModal(personalId, personalNombre) {
        document.getElementById('doc_personal_id').value = personalId;
        document.getElementById('docWorkerName').innerText = 'Colaborador: ' + personalNombre;
        document.getElementById('modalDocs').classList.add('open');
        loadDocs(personalId);
    }

    function closeDocsModal() {
        document.getElementById('modalDocs').classList.remove('open');
    }

    function loadDocs(personalId) {
        const container = document.getElementById('docsListContainer');
        container.innerHTML = '<div style="text-align:center; padding:1.5rem; color:#9CA3AF;"><i class="fas fa-spinner fa-spin"></i> Cargando documentos...</div>';
        
        fetch('personal.php?ajax=get_docs&personal_id=' + personalId)
            .then(res => res.json())
            .then(data => {
                if (data.length === 0) {
                    container.innerHTML = '<div style="text-align:center; padding:1.5rem; color:#9CA3AF; background:#F8FAFC; border-radius:10px;"><i class="fas fa-folder-empty" style="font-size:1.5rem; display:block; margin-bottom:5px;"></i>Aún no hay documentos adjuntos para este colaborador.</div>';
                    return;
                }

                let html = '';
                data.forEach(d => {
                    let icon = 'fa-file-pdf';
                    let iconColor = '#DC2626';
                    if (d.nombre_archivo.match(/\.(jpg|jpeg|png|webp)$/i)) {
                        icon = 'fa-file-image';
                        iconColor = '#0284C7';
                    } else if (d.nombre_archivo.match(/\.(doc|docx)$/i)) {
                        icon = 'fa-file-word';
                        iconColor = '#2563EB';
                    }

                    html += `
                    <div class="doc-item-row">
                        <div style="display:flex; align-items:center; gap:10px; overflow:hidden;">
                            <div style="font-size:1.5rem; color:${iconColor};">
                                <i class="fas ${icon}"></i>
                            </div>
                            <div>
                                <span style="font-size:0.75rem; font-weight:800; color:#2563EB; background:#EFF6FF; padding:1px 6px; border-radius:4px; text-transform:uppercase;">${d.tipo_documento.replace('_', ' ')}</span>
                                <strong style="display:block; font-size:0.83rem; color:#1E293B; margin-top:2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:280px;" title="${d.nombre_archivo}">${d.nombre_archivo}</strong>
                                <span style="font-size:0.7rem; color:#94A3B8;">${d.tamanio_kb} KB &bull; Subido el ${d.fecha_subida}</span>
                            </div>
                        </div>
                        <div style="display:flex; align-items:center; gap:6px;">
                            <a href="/carpicenter_sys/${d.ruta_archivo}" target="_blank" class="btn btn-outline" style="padding:4px 8px; font-size:0.75rem; color:#0284C7;" title="Ver / Descargar">
                                <i class="fas fa-eye"></i> Ver
                            </a>
                            <form method="POST" action="personal.php" style="display:inline;" onsubmit="return confirm('¿Seguro de eliminar este documento del legajo?');">
                                <input type="hidden" name="action" value="eliminar_documento">
                                <input type="hidden" name="doc_id" value="${d.id}">
                                <button type="submit" class="btn btn-outline" style="padding:4px 8px; font-size:0.75rem; color:#DC2626;" title="Eliminar">
                                    <i class="fas fa-trash-can"></i>
                                </button>
                            </form>
                        </div>
                    </div>`;
                });
                container.innerHTML = html;
            })
            .catch(err => {
                container.innerHTML = '<div style="color:#DC2626; padding:1rem;">Error al cargar documentos.</div>';
            });
    }
    </script>

    <?php include '../../views/partials/footer.php'; ?>
</body>
</html>
