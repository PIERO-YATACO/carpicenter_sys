<?php
require_once __DIR__ . '/../auth/check_auth.php';
require_once __DIR__ . '/../config/db.php';

$mensaje = '';
$tipo_mensaje = '';

// 1. PROCESAR AJUSTE MANUAL DE INVENTARIO (Solo Administradores / Supervisores)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajuste_manual') {
    $prod_id = intval($_POST['producto_id'] ?? 0);
    $color_id = intval($_POST['color_id'] ?? 0);
    $local_id = intval($_POST['local_id'] ?? 0);
    $tipo_mov = $_POST['tipo_movimiento'] ?? 'Entrada';
    $cantidad = floatval($_POST['cantidad'] ?? 0);
    $motivo_base = trim($_POST['motivo_seleccion'] ?? '');
    $motivo_detalle = trim($_POST['motivo_detalle'] ?? '');
    $motivo_completo = $motivo_base . (!empty($motivo_detalle) ? ' - ' . $motivo_detalle : '');

    if ($prod_id <= 0 || $color_id <= 0 || $local_id <= 0 || $cantidad <= 0 || empty($motivo_base)) {
        $mensaje = 'Todos los campos del ajuste son obligatorios (Producto, Color, Local, Cantidad > 0 y Motivo).';
        $tipo_mensaje = 'danger';
    } else {
        try {
            $db->beginTransaction();

            $user_id = $_SESSION['user_id'] ?? null;
            $ajuste_codigo = 'AJ-' . date('Ymd') . '-' . rand(100, 999);

            // Actualizar inventario local
            if ($tipo_mov === 'Entrada') {
                $stmtInv = $db->prepare("
                    INSERT INTO inventario_local (producto_id, color_id, local_id, stock_actual) 
                    VALUES (:p, :c, :l, :cant)
                    ON CONFLICT (producto_id, local_id, color_id) 
                    DO UPDATE SET stock_actual = inventario_local.stock_actual + :cant
                    RETURNING stock_actual
                ");
                $stmtInv->execute([':p' => $prod_id, ':c' => $color_id, ':l' => $local_id, ':cant' => $cantidad]);
            } else {
                $stmtInv = $db->prepare("
                    UPDATE inventario_local 
                    SET stock_actual = GREATEST(COALESCE(stock_actual, 0) - :cant, 0)
                    WHERE producto_id = :p AND color_id = :c AND local_id = :l
                    RETURNING stock_actual
                ");
                $stmtInv->execute([':p' => $prod_id, ':c' => $color_id, ':l' => $local_id, ':cant' => $cantidad]);
            }
            $stockResultante = $stmtInv->fetchColumn();
            if ($stockResultante === false) $stockResultante = ($tipo_mov === 'Entrada') ? $cantidad : 0;

            // Registrar movimiento en el Kardex
            $stmtKardex = $db->prepare("
                INSERT INTO kardex (tipo_movimiento, producto_id, color_id, local_id, cantidad, stock_resultante, motivo, documento_referencia, usuario_id)
                VALUES (:tipo, :p, :c, :l, :cant, :stock_res, :motivo, :doc_ref, :u)
            ");
            $stmtKardex->execute([
                ':tipo' => $tipo_mov,
                ':p' => $prod_id,
                ':c' => $color_id,
                ':l' => $local_id,
                ':cant' => $cantidad,
                ':stock_res' => $stockResultante,
                ':motivo' => 'Ajuste Manual: ' . $motivo_completo,
                ':doc_ref' => 'Ajuste N° ' . $ajuste_codigo,
                ':u' => $user_id
            ]);

            $db->commit();
            $mensaje = "Ajuste de stock ($tipo_mov: {$cantidad} un) registrado correctamente en el Kardex.";
            $tipo_mensaje = 'success';
        } catch (Exception $e) {
            $db->rollBack();
            $mensaje = 'Error al registrar el ajuste: ' . $e->getMessage();
            $tipo_mensaje = 'danger';
        }
    }
}

// 2. PARÁMETROS DE FILTRADO
$search = trim($_GET['search'] ?? '');
$fecha_inicio = $_GET['fecha_inicio'] ?? '';
$fecha_fin = $_GET['fecha_fin'] ?? '';
$local_id_filtro = isset($_GET['local_id']) && $_GET['local_id'] !== '' ? intval($_GET['local_id']) : null;
$producto_id_filtro = isset($_GET['producto_id']) && $_GET['producto_id'] !== '' ? intval($_GET['producto_id']) : null;
$tipo_movimiento = $_GET['tipo_movimiento'] ?? 'Todos';
$export = $_GET['export'] ?? '';

// Construir Consulta Dinámica
$sql = "
    SELECT 
        k.*,
        p.nombre as producto_nombre,
        p.codigo as producto_codigo,
        col.nombre as color_nombre,
        col.codigo as color_codigo,
        l.nombre as local_nombre,
        u.nombre_completo as usuario_nombre
    FROM kardex k
    LEFT JOIN productos p ON k.producto_id = p.id
    LEFT JOIN colores col ON k.color_id = col.id
    LEFT JOIN locales l ON k.local_id = l.id
    LEFT JOIN usuarios u ON k.usuario_id = u.id
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $sql .= " AND (p.nombre ILIKE :search OR p.codigo ILIKE :search OR k.documento_referencia ILIKE :search OR k.motivo ILIKE :search)";
    $params[':search'] = '%' . $search . '%';
}
if (!empty($fecha_inicio)) {
    $sql .= " AND k.fecha >= :fecha_inicio";
    $params[':fecha_inicio'] = $fecha_inicio;
}
if (!empty($fecha_fin)) {
    $sql .= " AND k.fecha <= :fecha_fin 23:59:59";
    $params[':fecha_fin'] = $fecha_fin;
}
if ($local_id_filtro !== null) {
    $sql .= " AND k.local_id = :local_id";
    $params[':local_id'] = $local_id_filtro;
}
if ($producto_id_filtro !== null) {
    $sql .= " AND k.producto_id = :producto_id";
    $params[':producto_id'] = $producto_id_filtro;
}
if ($tipo_movimiento !== 'Todos') {
    $sql .= " AND k.tipo_movimiento = :tipo";
    $params[':tipo'] = $tipo_movimiento;
}

$sql .= " ORDER BY k.fecha DESC, k.id DESC";

if ($export !== 'excel') {
    $sql .= " LIMIT 250";
}

try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Listas auxiliares para los filtros y el modal
    $locales = $db->query("SELECT id, nombre, tipo FROM locales ORDER BY tipo DESC, nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
    $productos = $db->query("SELECT id, nombre, codigo FROM productos ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener catálogo de colores por producto para el modal de ajuste
    $prodColoresStmt = $db->query("
        SELECT pc.producto_id, pc.color_id, c.nombre as color_nombre, c.codigo as color_codigo 
        FROM producto_colores pc 
        JOIN colores c ON pc.color_id = c.id 
        ORDER BY c.nombre ASC
    ");
    $prodColoresMap = [];
    while ($r = $prodColoresStmt->fetch(PDO::FETCH_ASSOC)) {
        $prodColoresMap[$r['producto_id']][] = [
            'id' => $r['color_id'],
            'nombre' => $r['color_nombre'],
            'codigo' => $r['color_codigo']
        ];
    }

    // Totales calculados sobre los registros filtrados
    $totEntradas = 0;
    $totSalidas = 0;
    foreach ($movimientos as $m) {
        if ($m['tipo_movimiento'] === 'Entrada') $totEntradas += floatval($m['cantidad']);
        if ($m['tipo_movimiento'] === 'Salida') $totSalidas += floatval($m['cantidad']);
    }
} catch (PDOException $e) {
    die("Error al consultar Kardex: " . $e->getMessage());
}

// 3. EXPORTACIÓN A EXCEL (CSV UTF-8 con BOM)
if ($export === 'excel') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Kardex_Carpicenter_' . date('Y-m-d_His') . '.csv');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    fputcsv($output, ['ID', 'Fecha y Hora', 'Tipo', 'Documento Referencia', 'Local / Almacén', 'Producto', 'Color', 'Entrada (+)', 'Salida (-)', 'Stock Resultante', 'Motivo / Detalle', 'Usuario Responsable'], ';');

    foreach ($movimientos as $m) {
        $isEnt = ($m['tipo_movimiento'] === 'Entrada');
        $isSal = ($m['tipo_movimiento'] === 'Salida');
        fputcsv($output, [
            $m['id'],
            date('d/m/Y H:i', strtotime($m['fecha'])),
            $m['tipo_movimiento'],
            $m['documento_referencia'] ?: '—',
            $m['local_nombre'] ?: 'Almacén Principal',
            $m['producto_nombre'] ?: 'Producto',
            $m['color_nombre'] ?: 'Único',
            $isEnt ? number_format($m['cantidad'], 1, '.', '') : '0.0',
            $isSal ? number_format($m['cantidad'], 1, '.', '') : '0.0',
            number_format($m['stock_resultante'], 1, '.', ''),
            $m['motivo'] ?: '',
            $m['usuario_nombre'] ?: 'Sistema'
        ], ';');
    }
    fclose($output);
    exit;
}

$page_title = 'Kardex';
$page_subtitle = 'Auditoría y trazabilidad histórica de entradas, salidas y transferencias de stock';
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
        /* ===== KARDEX EJECUTIVO PREMIUM ===== */
        .kardex-hero {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.4rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .kardex-hero-title h1 {
            font-size: 1.45rem;
            font-weight: 800;
            color: #111827;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .kardex-hero-title p {
            color: #6B7280;
            font-size: 0.84rem;
            margin: 0.25rem 0 0 0;
        }
        .kardex-hero-actions {
            display: flex;
            gap: 0.65rem;
            align-items: center;
            flex-wrap: wrap;
        }

        /* KPI Cards */
        .kardex-kpis-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.2rem;
            margin-bottom: 1.3rem;
        }
        .kardex-kpi-card {
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
        .kardex-kpi-card:hover {
            transform: translateY(-2px);
        }
        .kardex-kpi-icon {
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

        .kardex-kpi-info span.label {
            font-size: 0.74rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
            color: #6B7280;
            display: block;
            margin-bottom: 0.15rem;
        }
        .kardex-kpi-info h3 {
            font-size: 1.35rem;
            font-weight: 800;
            color: #111827;
            margin: 0;
            line-height: 1.2;
        }
        .kardex-kpi-info span.sub-tag {
            font-size: 0.74rem;
            font-weight: 600;
            display: inline-block;
            margin-top: 0.25rem;
            padding: 2px 8px;
            border-radius: 20px;
        }

        /* Filter Panel */
        .kardex-filter-card {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 14px;
            padding: 1.1rem 1.3rem;
            margin-bottom: 1.25rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        .kardex-filter-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr 1fr auto;
            gap: 0.75rem;
            align-items: end;
        }
        @media (max-width: 1100px) {
            .kardex-filter-grid { grid-template-columns: 1fr 1fr 1fr; }
        }
        @media (max-width: 650px) {
            .kardex-filter-grid { grid-template-columns: 1fr; }
        }
        .kardex-filter-item label {
            display: block;
            font-size: 0.74rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #4B5563;
            margin-bottom: 0.35rem;
        }
        .kardex-input {
            width: 100%;
            padding: 0.55rem 0.85rem;
            border-radius: 9px;
            border: 1px solid #D1D5DB;
            background: #F9FAFB;
            font-size: 0.84rem;
            color: #111827;
            outline: none;
            box-sizing: border-box;
        }
        .kardex-input:focus {
            background: #FFFFFF;
            border-color: #E31E24;
            box-shadow: 0 0 0 3px rgba(227,30,36,0.1);
        }

        /* Table Card */
        .kardex-table-card {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        }
        .kardex-table-header-title {
            padding: 1rem 1.4rem;
            border-bottom: 1px solid #F3F4F6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .kardex-table-header-title h3 {
            font-size: 0.95rem;
            font-weight: 700;
            color: #111827;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .kardex-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        .kardex-table th {
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
        .kardex-table td {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid #F3F4F6;
            font-size: 0.85rem;
            color: #374151;
            vertical-align: middle;
        }
        .kardex-table tbody tr:hover {
            background: #F9FAFB;
        }

        /* Badges */
        .badge-kardex-tipo {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 0.73rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .tipo-entrada { background: #ECFDF5; color: #059669; border: 1px solid #A7F3D0; }
        .tipo-salida { background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA; }
        .tipo-transfer { background: #EFF6FF; color: #2563EB; border: 1px solid #BFDBFE; }
        .tipo-ajuste { background: #FFFBEB; color: #D97706; border: 1px solid #FDE68A; }

        .doc-badge {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-weight: 700;
            font-size: 0.8rem;
            color: #1E293B;
            background: #F1F5F9;
            padding: 2px 7px;
            border-radius: 6px;
            border: 1px solid #CBD5E1;
            display: inline-block;
        }
        .store-pill {
            background: #F8FAFC;
            color: #334151;
            border: 1px solid #E2E8F0;
            padding: 2px 7px;
            border-radius: 6px;
            font-size: 0.76rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        /* ===== MODAL ULTRA LIMPIO & MODERNO ===== */
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
        .modal-overlay.open {
            display: flex;
        }
        .clean-modal-box {
            background: #FFFFFF;
            border-radius: 18px;
            width: 100%;
            max-width: 520px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.22);
            animation: modalCleanIn 0.2s ease-out;
            overflow: hidden;
            border: 1px solid #E5E7EB;
        }
        @keyframes modalCleanIn {
            from { opacity: 0; transform: scale(0.97); }
            to { opacity: 1; transform: scale(1); }
        }

        .clean-modal-header {
            padding: 1.1rem 1.4rem;
            border-bottom: 1px solid #F3F4F6;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .clean-modal-title {
            font-size: 1.1rem;
            font-weight: 800;
            color: #111827;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .clean-modal-title i {
            color: #E31E24;
            font-size: 1.05rem;
        }
        .clean-close-btn {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: none;
            background: #F3F4F6;
            color: #6B7280;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.15s;
        }
        .clean-close-btn:hover {
            background: #E5E7EB;
            color: #111827;
        }

        .clean-modal-body {
            padding: 1.25rem 1.4rem;
            display: flex;
            flex-direction: column;
            gap: 0.95rem;
        }

        /* Selector de Tipo (Pills Simples) */
        .type-toggle-wrapper {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.6rem;
            background: #F3F4F6;
            padding: 4px;
            border-radius: 12px;
        }
        .type-pill {
            cursor: pointer;
            margin: 0;
        }
        .type-pill input {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }
        .type-pill-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: 0.6rem 0.5rem;
            border-radius: 9px;
            font-size: 0.86rem;
            font-weight: 700;
            color: #6B7280;
            transition: all 0.2s ease;
            text-align: center;
            user-select: none;
        }
        .type-pill-in:has(input:checked) .type-pill-btn {
            background: #059669;
            color: #FFFFFF;
            box-shadow: 0 2px 8px rgba(5, 150, 105, 0.3);
        }
        .type-pill-out:has(input:checked) .type-pill-btn {
            background: #DC2626;
            color: #FFFFFF;
            box-shadow: 0 2px 8px rgba(220, 38, 38, 0.3);
        }

        /* Form Fields */
        .field-label {
            display: block;
            font-size: 0.76rem;
            font-weight: 700;
            color: #374151;
            margin-bottom: 0.3rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .field-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.8rem;
        }
        @media (max-width: 480px) {
            .field-grid-2 { grid-template-columns: 1fr; }
        }

        .clean-input {
            width: 100%;
            padding: 0.6rem 0.8rem;
            border-radius: 9px;
            border: 1px solid #D1D5DB;
            background: #F9FAFB;
            font-size: 0.86rem;
            color: #111827;
            outline: none;
            box-sizing: border-box;
            font-family: inherit;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        .clean-input:focus {
            background: #FFFFFF;
            border-color: #E31E24;
            box-shadow: 0 0 0 3px rgba(227, 30, 36, 0.12);
        }
        .clean-select {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236B7280' stroke-width='2'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.7rem center;
            background-size: 1rem;
            padding-right: 2rem;
        }

        .clean-modal-footer {
            padding: 0.9rem 1.4rem;
            background: #F9FAFB;
            border-top: 1px solid #F3F4F6;
            display: flex;
            justify-content: flex-end;
            gap: 0.6rem;
        }
        .btn-cancel {
            padding: 0.55rem 1.1rem;
            border-radius: 8px;
            border: 1px solid #D1D5DB;
            background: #FFFFFF;
            color: #4B5563;
            font-size: 0.84rem;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-cancel:hover {
            background: #F3F4F6;
            color: #111827;
        }
        .btn-save {
            padding: 0.55rem 1.3rem;
            border-radius: 8px;
            border: none;
            background: #E31E24;
            color: #FFFFFF;
            font-size: 0.84rem;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: background 0.15s;
        }
        .btn-save:hover {
            background: #C6181E;
        }
            font-size: 0.71rem;
            color: #64748B;
            line-height: 1.2;
        }
        .movement-card-check {
            font-size: 1rem;
            color: transparent;
            transition: all 0.2s;
        }

        /* Checked State for Entrada */
        .movement-card-in:has(input:checked) .movement-card-inner {
            border-color: #10B981;
            background: #ECFDF5;
            box-shadow: 0 4px 14px rgba(16, 185, 129, 0.18);
        }
        .movement-card-in:has(input:checked) .movement-card-icon {
            background: #10B981;
            color: #FFFFFF;
        }
        .movement-card-in:has(input:checked) .movement-card-title {
            color: #065F46;
        }
        .movement-card-in:has(input:checked) .movement-card-check {
            color: #059669;
        }

        /* Checked State for Salida */
        .movement-card-out:has(input:checked) .movement-card-inner {
            border-color: #EF4444;
            background: #FEF2F2;
            box-shadow: 0 4px 14px rgba(239, 68, 68, 0.18);
        }
        .movement-card-out:has(input:checked) .movement-card-icon {
            background: #EF4444;
            color: #FFFFFF;
        }
        .movement-card-out:has(input:checked) .movement-card-title {
            color: #991B1B;
        }
        .movement-card-out:has(input:checked) .movement-card-check {
            color: #DC2626;
        }

        /* Inputs & Selects */
        .form-row-2col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.9rem;
        }
        @media (max-width: 520px) {
            .movement-type-grid, .form-row-2col {
                grid-template-columns: 1fr;
            }
        }
        .modern-input {
            width: 100%;
            padding: 0.65rem 0.9rem;
            border-radius: 12px;
            border: 1.5px solid #E2E8F0;
            background: #F8FAFC;
            font-size: 0.86rem;
            color: #0F172A;
            outline: none;
            box-sizing: border-box;
            font-family: inherit;
            transition: all 0.2s ease;
        }
        .modern-input:focus {
            background: #FFFFFF;
            border-color: #E31E24;
            box-shadow: 0 0 0 3.5px rgba(227, 30, 36, 0.12);
        }
        .modern-select {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748b' stroke-width='2'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 1.1rem;
            padding-right: 2.25rem;
        }
        .modern-textarea {
            resize: vertical;
            min-height: 60px;
        }

        /* Input Unit Wrapper */
        .input-unit-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        .input-quantity {
            font-weight: 800;
            font-size: 0.98rem;
            color: #0F172A;
            padding-right: 4rem;
        }
        .unit-badge {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            background: #E2E8F0;
            color: #475569;
            font-size: 0.7rem;
            font-weight: 800;
            padding: 3px 8px;
            border-radius: 8px;
            pointer-events: none;
            letter-spacing: 0.5px;
        }

        /* Modal Footer */
        .modern-modal-footer {
            padding: 1.1rem 1.6rem;
            background: #F8FAFC;
            border-top: 1px solid #F1F5F9;
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 0.75rem;
        }
        .btn-modern-cancel {
            padding: 0.65rem 1.25rem;
            border-radius: 12px;
            border: 1px solid #E2E8F0;
            background: #FFFFFF;
            color: #64748B;
            font-size: 0.85rem;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }
        .btn-modern-cancel:hover {
            background: #F1F5F9;
            color: #1E293B;
            border-color: #CBD5E1;
        }
        .btn-modern-submit {
            padding: 0.68rem 1.5rem;
            border-radius: 12px;
            border: none;
            background: linear-gradient(135deg, #E31E24 0%, #B91C1C 100%);
            color: #FFFFFF;
            font-size: 0.86rem;
            font-weight: 800;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 14px rgba(227, 30, 36, 0.35);
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .btn-modern-submit:hover {
            background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%);
            box-shadow: 0 6px 20px rgba(227, 30, 36, 0.45);
            transform: translateY(-1px);
        }
        .btn-modern-submit:active {
            transform: translateY(0);
        }

        @media print {
            .app-sidebar, .header-wrapper, .kardex-filter-card, .kardex-hero-actions, .no-print {
                display: none !important;
            }
            .app-wrapper { margin: 0 !important; padding: 0 !important; }
            .main-content, .page-content { padding: 0 !important; margin: 0 !important; }
            .kardex-table-card { border: none !important; box-shadow: none !important; }
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
                <div class="kardex-hero">
                    <div class="kardex-hero-title">
                        <h1><i class="fas fa-clipboard-list" style="color:#E31E24;"></i> Kardex</h1>
                        <p>Libro histórico de movimientos, auditoría de entradas, salidas y trazabilidad física</p>
                    </div>
                    <div class="kardex-hero-actions">
                        <button type="button" class="btn btn-primary" onclick="openAjusteModal()" style="font-weight:600; padding:0.55rem 1.1rem; border-radius:10px; box-shadow:0 4px 12px rgba(227,30,36,0.25);">
                            <i class="fas fa-sliders" style="margin-right:6px;"></i> Ajuste de Stock
                        </button>
                        <?php
                        $exportQuery = $_GET;
                        $exportQuery['export'] = 'excel';
                        $exportUrl = 'kardex.php?' . http_build_query($exportQuery);
                        ?>
                        <a href="<?= htmlspecialchars($exportUrl) ?>" class="btn btn-outline" style="border-color:#107C41; color:#107C41; font-weight:600; padding:0.55rem 1rem; border-radius:10px;">
                            <i class="fas fa-file-excel" style="margin-right:6px;"></i> Exportar Excel
                        </a>
                        <button type="button" class="btn btn-outline" onclick="window.print()" style="padding:0.55rem 0.9rem; border-radius:10px;" title="Imprimir Reporte">
                            <i class="fas fa-print"></i>
                        </button>
                    </div>
                </div>

                <!-- Toast Alerts -->
                <?php if (!empty($mensaje)): ?>
                    <div style="background:<?= $tipo_mensaje === 'success' ? '#10B981' : '#EF4444' ?>; color:#fff; padding:0.85rem 1.25rem; border-radius:10px; margin-bottom:1.2rem; display:flex; justify-content:space-between; align-items:center; font-weight:600;">
                        <div>
                            <i class="fas <?= $tipo_mensaje === 'success' ? 'fa-check-circle' : 'fa-triangle-exclamation' ?>" style="margin-right:8px;"></i>
                            <?= htmlspecialchars($mensaje) ?>
                        </div>
                        <button type="button" onclick="this.parentElement.remove()" style="background:none; border:none; color:#fff; font-size:1.2rem; cursor:pointer;">&times;</button>
                    </div>
                <?php endif; ?>

                <!-- KPI Cards -->
                <div class="kardex-kpis-grid">
                    <div class="kardex-kpi-card">
                        <div class="kardex-kpi-icon icon-indigo-bg">
                            <i class="fas fa-list-check"></i>
                        </div>
                        <div class="kardex-kpi-info">
                            <span class="label">Movimientos Filtrados</span>
                            <h3><?= count($movimientos) ?></h3>
                            <span class="sub-tag" style="background:#EEF2FF; color:#4F46E5;">
                                Registros en consulta
                            </span>
                        </div>
                    </div>

                    <div class="kardex-kpi-card">
                        <div class="kardex-kpi-icon icon-emerald-bg">
                            <i class="fas fa-arrow-down"></i>
                        </div>
                        <div class="kardex-kpi-info">
                            <span class="label">Total Entradas</span>
                            <h3 style="color:#059669;">+<?= number_format($totEntradas, 1) ?> un</h3>
                            <span class="sub-tag" style="background:#ECFDF5; color:#059669;">
                                Ingresos a almacén
                            </span>
                        </div>
                    </div>

                    <div class="kardex-kpi-card">
                        <div class="kardex-kpi-icon icon-rose-bg">
                            <i class="fas fa-arrow-up"></i>
                        </div>
                        <div class="kardex-kpi-info">
                            <span class="label">Total Salidas</span>
                            <h3 style="color:#DC2626;">-<?= number_format($totSalidas, 1) ?> un</h3>
                            <span class="sub-tag" style="background:#FEF2F2; color:#DC2626;">
                                Despachos y ventas
                            </span>
                        </div>
                    </div>

                    <div class="kardex-kpi-card">
                        <div class="kardex-kpi-icon icon-blue-bg">
                            <i class="fas fa-scale-balanced"></i>
                        </div>
                        <div class="kardex-kpi-info">
                            <?php $balance = $totEntradas - $totSalidas; ?>
                            <span class="label">Balance Neto</span>
                            <h3 style="color:<?= $balance >= 0 ? '#2563EB' : '#DC2626' ?>;">
                                <?= ($balance >= 0 ? '+' : '') . number_format($balance, 1) ?> un
                            </h3>
                            <span class="sub-tag" style="background:<?= $balance >= 0 ? '#EFF6FF' : '#FEF2F2' ?>; color:<?= $balance >= 0 ? '#2563EB' : '#DC2626' ?>;">
                                Flujo de inventario
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Panel de Filtros -->
                <div class="kardex-filter-card no-print">
                    <form method="GET" action="kardex.php">
                        <div class="kardex-filter-grid">
                            <div class="kardex-filter-item">
                                <label>Búsqueda Rápida</label>
                                <input type="text" name="search" class="kardex-input" placeholder="Buscar por producto, documento o motivo..." value="<?= htmlspecialchars($search) ?>">
                            </div>
                            <div class="kardex-filter-item">
                                <label>Local / Sucursal</label>
                                <select name="local_id" class="kardex-input">
                                    <option value="">-- Todos los locales --</option>
                                    <?php foreach ($locales as $loc): ?>
                                    <option value="<?= $loc['id'] ?>" <?= $local_id_filtro === intval($loc['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($loc['nombre']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="kardex-filter-item">
                                <label>Producto</label>
                                <select name="producto_id" class="kardex-input">
                                    <option value="">-- Todos los productos --</option>
                                    <?php foreach ($productos as $p): 
                                        $codeTag = !empty($p['codigo']) ? '[' . $p['codigo'] . '] ' : '';
                                    ?>
                                    <option value="<?= $p['id'] ?>" <?= $producto_id_filtro === intval($p['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($codeTag . $p['nombre']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="kardex-filter-item">
                                <label>Fecha Inicio</label>
                                <input type="date" name="fecha_inicio" class="kardex-input" value="<?= htmlspecialchars($fecha_inicio) ?>">
                            </div>
                            <div class="kardex-filter-item">
                                <label>Fecha Fin</label>
                                <input type="date" name="fecha_fin" class="kardex-input" value="<?= htmlspecialchars($fecha_fin) ?>">
                            </div>
                            <div class="kardex-filter-item">
                                <label>Tipo de Movimiento</label>
                                <select name="tipo_movimiento" class="kardex-input">
                                    <option value="Todos" <?= $tipo_movimiento === 'Todos' ? 'selected' : '' ?>>Todos</option>
                                    <option value="Entrada" <?= $tipo_movimiento === 'Entrada' ? 'selected' : '' ?>>Solo Entradas (+)</option>
                                    <option value="Salida" <?= $tipo_movimiento === 'Salida' ? 'selected' : '' ?>>Solo Salidas (-)</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="kardex-filter-actions">
                            <button type="submit" class="btn btn-primary" style="padding:0.6rem 1.4rem; font-weight:600;">
                                <i class="fas fa-filter"></i> Aplicar Filtros
                            </button>
                            <a href="kardex.php" class="btn btn-outline" style="padding:0.6rem 1.1rem; font-weight:600;">
                                <i class="fas fa-rotate-left"></i> Limpiar
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Tabla de Movimientos de Kardex -->
                <div class="kardex-table-card">
                    <div class="kardex-table-header-title">
                        <h3><i class="fas fa-list-check" style="color:#E31E24;"></i> Registro Detallado de Movimientos</h3>
                        <span style="font-size:0.8rem; color:#6B7280; font-weight:500;">
                            Mostrando <?= count($movimientos) ?> operaciones
                        </span>
                    </div>

                    <div style="overflow-x:auto;">
                        <table class="kardex-table">
                            <thead>
                                <tr>
                                    <th>Fecha & Hora</th>
                                    <th>Tipo</th>
                                    <th>Documento / Ref.</th>
                                    <th>Sede / Sucursal</th>
                                    <th>Producto / Acabado</th>
                                    <th style="text-align:right;">Entrada</th>
                                    <th style="text-align:right;">Salida</th>
                                    <th style="text-align:right;">Saldo Stock</th>
                                    <th>Registrado Por</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($movimientos)): ?>
                                    <tr>
                                        <td colspan="9" style="text-align:center; padding:3.5rem; color:#9CA3AF;">
                                            <i class="fas fa-folder-open" style="font-size:2.5rem; margin-bottom:0.8rem; opacity:0.35; display:block;"></i>
                                            No se encontraron movimientos registrados con los filtros aplicados.
                                        </td>
                                    </tr>
                                <?php else: foreach($movimientos as $m): 
                                    $isEntrada = $m['tipo_movimiento'] === 'Entrada';
                                    $isSalida = $m['tipo_movimiento'] === 'Salida';
                                    $tipoClass = $isEntrada ? 'tipo-entrada' : 'tipo-salida';
                                ?>
                                    <tr>
                                        <td style="white-space:nowrap; font-weight:600; color:#4B5563; font-size:0.82rem;">
                                            <?= date('d/m/Y H:i', strtotime($m['fecha'])) ?>
                                        </td>
                                        <td>
                                            <span class="badge-kardex-tipo <?= $tipoClass ?>">
                                                <i class="fas <?= $isSalida ? 'fa-arrow-up' : 'fa-arrow-down' ?>"></i> <?= htmlspecialchars($m['tipo_movimiento']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="doc-badge"><?= htmlspecialchars($m['documento_referencia'] ?: 'S/N') ?></span>
                                        </td>
                                        <td>
                                            <span class="store-pill">
                                                <i class="fas fa-shop" style="color:#64748B; font-size:0.75rem;"></i>
                                                <?= htmlspecialchars($m['local_nombre'] ?: 'Almacén Central') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="font-weight:700; color:#111827; font-size:0.88rem; display:flex; align-items:center; gap:5px; flex-wrap:wrap;">
                                                <?php if(!empty($m['producto_codigo'])): ?>
                                                    <span class="doc-badge" style="font-size:0.75rem; font-weight:700;"><?= htmlspecialchars($m['producto_codigo']) ?></span>
                                                <?php endif; ?>
                                                <?= htmlspecialchars($m['producto_nombre'] ?: 'Producto') ?>
                                            </div>
                                            <?php if(!empty($m['color_nombre'])): ?>
                                                <div style="font-size:0.75rem; color:#6B7280; margin-top:2px; display:flex; align-items:center; gap:4px;">
                                                    <i class="fas fa-circle" style="font-size:0.45rem;"></i> <?= htmlspecialchars($m['color_nombre']) ?>
                                                    <?php if(!empty($m['color_codigo'])): ?>
                                                        <span class="doc-badge" style="font-size:0.65rem; padding:1px 4px;"><?= htmlspecialchars($m['color_codigo']) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if(!empty($m['motivo'])): ?>
                                                <div style="font-size:0.73rem; color:#9CA3AF; margin-top:2px; font-style:italic;">
                                                    <?= htmlspecialchars($m['motivo']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="color:#059669; font-weight:800; text-align:right; font-size:0.92rem;">
                                            <?= $isEntrada ? '+' . number_format($m['cantidad'], 1) : '<span style="color:#CBD5E1;">—</span>' ?>
                                        </td>
                                        <td style="color:#DC2626; font-weight:800; text-align:right; font-size:0.92rem;">
                                            <?= $isSalida ? '-' . number_format($m['cantidad'], 1) : '<span style="color:#CBD5E1;">—</span>' ?>
                                        </td>
                                        <td style="font-weight:800; text-align:right; color:#111827; font-size:0.92rem;">
                                            <span style="background:#F1F5F9; padding:3px 8px; border-radius:6px;">
                                                <?= number_format($m['stock_resultante'], 1) ?> un
                                            </span>
                                        </td>
                                        <td style="color:#4B5563; font-size:0.82rem;">
                                            <div style="display:flex; align-items:center; gap:5px;">
                                                <i class="fas fa-user-circle" style="color:#9CA3AF;"></i>
                                                <?= htmlspecialchars($m['usuario_nombre'] ?: 'Sistema') ?>
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

    <!-- Modal Movimiento / Ajuste de Stock (Limpio & Conciso) -->
    <div class="modal-overlay" id="modalAjuste">
        <div class="clean-modal-box">
            <!-- Header -->
            <div class="clean-modal-header">
                <h3 class="clean-modal-title">
                    <i class="fas fa-boxes-stacked"></i> Movimiento de Stock
                </h3>
                <button type="button" class="clean-close-btn" onclick="closeAjusteModal()" title="Cerrar">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form method="POST" action="kardex.php" id="formAjusteStock">
                <input type="hidden" name="action" value="ajuste_manual">
                
                <div class="clean-modal-body">
                    
                    <!-- Tipo: Ingreso / Salida -->
                    <div class="type-toggle-wrapper">
                        <label class="type-pill type-pill-in">
                            <input type="radio" name="tipo_movimiento" value="Entrada" checked required>
                            <span class="type-pill-btn">
                                <i class="fas fa-plus-circle"></i> + Ingreso de Stock
                            </span>
                        </label>

                        <label class="type-pill type-pill-out">
                            <input type="radio" name="tipo_movimiento" value="Salida" required>
                            <span class="type-pill-btn">
                                <i class="fas fa-minus-circle"></i> - Salida de Stock
                            </span>
                        </label>
                    </div>

                    <!-- Fila 1: Sede y Cantidad -->
                    <div class="field-grid-2">
                        <div>
                            <label class="field-label">Sede / Almacén *</label>
                            <select name="local_id" class="clean-input clean-select" required>
                                <?php foreach ($locales as $loc): ?>
                                <option value="<?= $loc['id'] ?>"><?= htmlspecialchars($loc['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="field-label">Cantidad (Unidades) *</label>
                            <input type="number" step="0.5" min="0.1" name="cantidad" class="clean-input" placeholder="Ej: 10" required style="font-weight: 700;">
                        </div>
                    </div>

                    <!-- Fila 2: Producto y Color -->
                    <div class="field-grid-2">
                        <div>
                            <label class="field-label">Producto *</label>
                            <select name="producto_id" id="ajuste_prod_id" class="clean-input clean-select" required onchange="onAjusteProductoChange(this.value)">
                                <option value="">-- Seleccionar --</option>
                                <?php foreach ($productos as $p): 
                                    $codeTag = !empty($p['codigo']) ? '[' . $p['codigo'] . '] ' : '';
                                ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($codeTag . $p['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="field-label">Color / Variante *</label>
                            <select name="color_id" id="ajuste_color_id" class="clean-input clean-select" required>
                                <option value="">-- Seleccionar producto primero --</option>
                            </select>
                        </div>
                    </div>

                    <!-- Motivo -->
                    <div>
                        <label class="field-label">Motivo *</label>
                        <select name="motivo_seleccion" class="clean-input clean-select" required>
                            <option value="Llegada de Mercadería / Proveedor">📦 Llegada de Mercadería / Proveedor</option>
                            <option value="Conteo Físico / Cuadre de Almacén">📋 Cuadre de Almacén / Inventario</option>
                            <option value="Devolución / Reingreso de Cliente">🔄 Devolución de Cliente</option>
                            <option value="Merma / Dañado en Transporte o Manipulación">⚠️ Merma / Dañado</option>
                            <option value="Muestra para Exhibición / Showroom">🛋️ Muestra para Exhibición</option>
                            <option value="Otro Motivo">📝 Otro Motivo</option>
                        </select>
                    </div>

                    <!-- Nota / Observación -->
                    <div>
                        <label class="field-label" style="color: #6B7280;">Nota o N° Guía (Opcional)</label>
                        <input type="text" name="motivo_detalle" class="clean-input" placeholder="Ej: Camión proveedor, Guía 001-452...">
                    </div>

                </div>

                <!-- Footer Acciones -->
                <div class="clean-modal-footer">
                    <button type="button" class="clean-btn clean-btn-cancel" onclick="closeAjusteModal()">
                        Cancelar
                    </button>
                    <button type="submit" class="clean-btn clean-btn-submit">
                        <i class="fas fa-check"></i> Registrar Movimiento
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    const prodColoresMap = <?= json_encode($prodColoresMap) ?>;

    function openAjusteModal() {
        document.getElementById('modalAjuste').classList.add('active');
    }

    function closeAjusteModal() {
        document.getElementById('modalAjuste').classList.remove('active');
        document.getElementById('formAjusteStock').reset();
        document.getElementById('ajuste_color_id').innerHTML = '<option value="">-- Seleccionar producto primero --</option>';
    }

    function onAjusteProductoChange(prodId) {
        const colorSelect = document.getElementById('ajuste_color_id');
        colorSelect.innerHTML = '<option value="">-- Seleccionar Color --</option>';

        if (!prodId || !prodColoresMap[prodId]) {
            colorSelect.innerHTML = '<option value="1">Color Estándar / Único</option>';
            return;
        }

        prodColoresMap[prodId].forEach(c => {
            const codeTag = c.codigo ? `[${c.codigo}] ` : '';
            colorSelect.innerHTML += `<option value="${c.id}">${codeTag}${c.nombre}</option>`;
        });
    }

    window.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeAjusteModal();
    });
    </script>

    <?php include 'partials/footer.php'; ?>
</body>
</html>
