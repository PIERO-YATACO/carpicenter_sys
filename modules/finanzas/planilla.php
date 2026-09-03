<?php
require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../config/db.php';

$mensaje = '';
$tipo_mensaje = '';

// Procesar Acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'crear_planilla') {
        $semana_codigo = trim($_POST['semana_codigo'] ?? '');
        $fecha_inicio = $_POST['fecha_inicio'] ?? '';
        $fecha_fin = $_POST['fecha_fin'] ?? '';
        $fecha_pago = $_POST['fecha_pago'] ?? date('Y-m-d');
        $observaciones = trim($_POST['observaciones'] ?? '');

        if (empty($semana_codigo) || empty($fecha_inicio) || empty($fecha_fin)) {
            $mensaje = 'Por favor completa el código y las fechas de la semana.';
            $tipo_mensaje = 'danger';
        } else {
            try {
                $db->beginTransaction();

                $ins_plan = $db->prepare("
                    INSERT INTO planilla_semanal (semana_codigo, fecha_inicio, fecha_fin, fecha_pago, observaciones, usuario_id)
                    VALUES (?, ?, ?, ?, ?, ?)
                    RETURNING id
                ");
                $ins_plan->execute([$semana_codigo, $fecha_inicio, $fecha_fin, $fecha_pago, $observaciones, $_SESSION['user_id'] ?? null]);
                $new_id = $ins_plan->fetchColumn();

                $personal_activos = $db->query("
                    SELECT * FROM personal 
                    WHERE estado = 'ACTIVO' 
                    ORDER BY 
                        CASE 
                            WHEN categoria = 'ADMINISTRATIVO' THEN 1 
                            WHEN categoria = 'TIENDAS' THEN 2 
                            ELSE 3 
                        END, orden ASC, id ASC
                ")->fetchAll(PDO::FETCH_ASSOC);

                $ins_det = $db->prepare("
                    INSERT INTO planilla_semanal_detalle (
                        planilla_id, personal_id, nombre_personal, area, categoria, tipo_trabajador,
                        cuenta_bancaria, metodo_pago, sueldo_mensual, base_dia, base_semanal,
                        bono_comision, horas_extra, pago_hora, monto_horas_extra,
                        horas_falta, descuento_falta, descuento_prestamo, descuento_planilla,
                        total_descuentos, total_pagar, incluido, estado_pago, orden
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?,
                        0, 0, ?, 0,
                        0, 0, 0, 0,
                        0, ?, ?, 'PENDIENTE', ?
                    )
                ");

                $tot_admin = 0; $tot_tiendas = 0; $tot_prod = 0;
                $i = 1;
                foreach ($personal_activos as $p) {
                    $incluido = ($p['tipo_trabajador'] === 'FIJO');
                    $baseSem = floatval($p['base_semanal']);
                    $pagoHora = floatval($p['pago_hora']);
                    $sueldoMen = floatval($p['sueldo_mensual']);
                    $baseDia = floatval($p['base_dia']);
                    $totPagar = $incluido ? $baseSem : 0;

                    if ($incluido) {
                        if ($p['categoria'] === 'ADMINISTRATIVO') $tot_admin += $totPagar;
                        elseif ($p['categoria'] === 'TIENDAS') $tot_tiendas += $totPagar;
                        else $tot_prod += $totPagar;
                    }

                    $ins_det->execute([
                        $new_id, $p['id'], $p['nombres'], $p['area'], $p['categoria'], $p['tipo_trabajador'],
                        $p['cuenta_bancaria'], $p['metodo_pago'], $sueldoMen, $baseDia, $baseSem,
                        $pagoHora, $totPagar, $incluido ? 1 : 0, $i++
                    ]);
                }

                $tot_gen = $tot_admin + $tot_tiendas + $tot_prod;
                $db->prepare("
                    UPDATE planilla_semanal 
                    SET total_administrativo = ?, total_tiendas = ?, total_produccion = ?, total_general = ?
                    WHERE id = ?
                ")->execute([$tot_admin, $tot_tiendas, $tot_prod, $tot_gen, $new_id]);

                $db->commit();
                header("Location: planilla.php?id=" . $new_id . "&msg=created");
                exit;
            } catch (Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                $mensaje = 'Error al crear la planilla: ' . $e->getMessage();
                $tipo_mensaje = 'danger';
            }
        }
    } elseif ($action === 'guardar_detalle_completo') {
        $curr_id = intval($_POST['planilla_id'] ?? 0);
        $detalles_post = $_POST['detalles'] ?? [];

        if ($curr_id > 0 && !empty($detalles_post)) {
            try {
                $db->beginTransaction();

                $tot_admin = 0; $tot_tiendas = 0; $tot_prod = 0;

                $upd_det = $db->prepare("
                    UPDATE planilla_semanal_detalle SET
                        sueldo_mensual = :sueldo_mensual,
                        base_dia = :base_dia,
                        base_semanal = :base_semanal,
                        bono_comision = :bono_comision,
                        horas_extra = :horas_extra,
                        pago_hora = :pago_hora,
                        monto_horas_extra = :monto_horas_extra,
                        descuento_falta = :descuento_falta,
                        descuento_prestamo = :descuento_prestamo,
                        descuento_planilla = :descuento_planilla,
                        total_descuentos = :total_descuentos,
                        total_pagar = :total_pagar,
                        incluido = :incluido
                    WHERE id = :id AND planilla_id = :planilla_id
                ");

                foreach ($detalles_post as $det_id => $row) {
                    $incluido = isset($row['incluido']) && $row['incluido'] == '1';
                    $baseSem = floatval($row['base_semanal'] ?? 0);
                    $sueldoMen = floatval($row['sueldo_mensual'] ?? ($baseSem * 4));
                    $baseDia = floatval($row['base_dia'] ?? ($baseSem / 6));
                    $pagoHora = floatval($row['pago_hora'] ?? ($baseDia / 10));
                    $bono = floatval($row['bono_comision'] ?? 0);
                    $hExtra = floatval($row['horas_extra'] ?? 0);
                    $montoHExtra = $hExtra * $pagoHora;

                    $dstoFalta = floatval($row['descuento_falta'] ?? 0);
                    $dstoPrestamo = floatval($row['descuento_prestamo'] ?? 0);
                    $dstoPlanilla = floatval($row['descuento_planilla'] ?? 0);
                    $totDsctos = $dstoFalta + $dstoPrestamo + $dstoPlanilla;

                    $totPagar = 0;
                    if ($incluido) {
                        $totPagar = max(0, ($baseSem + $bono + $montoHExtra) - $totDsctos);
                        $cat = $row['categoria'] ?? 'PRODUCCION';
                        if ($cat === 'ADMINISTRATIVO') $tot_admin += $totPagar;
                        elseif ($cat === 'TIENDAS') $tot_tiendas += $totPagar;
                        else $tot_prod += $totPagar;
                    }

                    $upd_det->execute([
                        ':sueldo_mensual' => $sueldoMen,
                        ':base_dia' => $baseDia,
                        ':base_semanal' => $baseSem,
                        ':bono_comision' => $bono,
                        ':horas_extra' => $hExtra,
                        ':pago_hora' => $pagoHora,
                        ':monto_horas_extra' => $montoHExtra,
                        ':descuento_falta' => $dstoFalta,
                        ':descuento_prestamo' => $dstoPrestamo,
                        ':descuento_planilla' => $dstoPlanilla,
                        ':total_descuentos' => $totDsctos,
                        ':total_pagar' => $totPagar,
                        ':incluido' => $incluido ? 1 : 0,
                        ':id' => $det_id,
                        ':planilla_id' => $curr_id
                    ]);
                }

                $tot_gen = $tot_admin + $tot_tiendas + $tot_prod;
                $db->prepare("
                    UPDATE planilla_semanal 
                    SET total_administrativo = ?, total_tiendas = ?, total_produccion = ?, total_general = ?
                    WHERE id = ?
                ")->execute([$tot_admin, $tot_tiendas, $tot_prod, $tot_gen, $curr_id]);

                $db->commit();
                $mensaje = 'Planilla semanal guardada y calculada exitosamente.';
                $tipo_mensaje = 'success';
            } catch (Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                $mensaje = 'Error al guardar: ' . $e->getMessage();
                $tipo_mensaje = 'danger';
            }
        }
    } elseif ($action === 'eliminar_planilla') {
        $del_id = intval($_POST['planilla_id'] ?? 0);
        if ($del_id > 0) {
            $db->prepare("DELETE FROM planilla_semanal WHERE id = ?")->execute([$del_id]);
            header("Location: planilla.php?msg=deleted");
            exit;
        }
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'created') {
    $mensaje = '¡Planilla semanal generada con éxito!';
    $tipo_mensaje = 'success';
} elseif (isset($_GET['msg']) && $_GET['msg'] === 'deleted') {
    $mensaje = 'Planilla eliminada del historial.';
    $tipo_mensaje = 'success';
}

// 1. Obtener la lista completa de planillas para el historial
$stmt_semanas = $db->query("SELECT * FROM planilla_semanal ORDER BY fecha_pago DESC, id DESC");
$planillas_lista = $stmt_semanas->fetchAll(PDO::FETCH_ASSOC);

// Identificar si estamos en la vista de Historial (ID = 0) o en el detalle de una planilla
$planilla_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$curr_planilla = null;
$detalles_admin = [];
$detalles_tiendas = [];
$detalles_prod = [];

if ($planilla_id > 0) {
    $stmt_p = $db->prepare("SELECT * FROM planilla_semanal WHERE id = ?");
    $stmt_p->execute([$planilla_id]);
    $curr_planilla = $stmt_p->fetch(PDO::FETCH_ASSOC);

    if ($curr_planilla) {
        $stmt_d = $db->prepare("
            SELECT * FROM planilla_semanal_detalle 
            WHERE planilla_id = ? 
            ORDER BY orden ASC, id ASC
        ");
        $stmt_d->execute([$planilla_id]);
        $all_detalles = $stmt_d->fetchAll(PDO::FETCH_ASSOC);

        foreach ($all_detalles as $d) {
            if ($d['categoria'] === 'ADMINISTRATIVO') $detalles_admin[] = $d;
            elseif ($d['categoria'] === 'TIENDAS') $detalles_tiendas[] = $d;
            else $detalles_prod[] = $d;
        }
    }
}

$page_title = 'Planilla Semanal';
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
        .payroll-hero-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.25rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .payroll-hero-title h1 {
            font-size: 1.45rem;
            font-weight: 800;
            color: #111827;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .payroll-hero-title p {
            color: #6B7280;
            font-size: 0.84rem;
            margin: 0.25rem 0 0 0;
        }

        /* Top Action Bar en Vista Detalle */
        .payroll-top-bar {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 14px;
            padding: 0.85rem 1.25rem;
            margin-bottom: 1.1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.9rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        .week-picker-box {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .week-badge-icon {
            width: 36px;
            height: 36px;
            border-radius: 9px;
            background: #FEF2F2;
            color: #DC2626;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
        }
        .week-dropdown {
            padding: 0.5rem 0.85rem;
            border-radius: 9px;
            border: 1px solid #CBD5E1;
            background: #F8FAFC;
            font-size: 0.88rem;
            font-weight: 700;
            color: #0F172A;
            outline: none;
            cursor: pointer;
        }

        /* KPI Strip */
        .payroll-summary-strip {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.8rem;
            margin-bottom: 1.2rem;
        }
        @media (max-width: 992px) { .payroll-summary-strip { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 540px) { .payroll-summary-strip { grid-template-columns: 1fr; } }

        .summary-card {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 12px;
            padding: 0.8rem 1.1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .summary-card span.tag {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #64748B;
            display: block;
        }
        .summary-card h4 {
            margin: 3px 0 0 0;
            font-size: 1.18rem;
            font-weight: 800;
            color: #0F172A;
        }
        .summary-card-total {
            background: #F0FDF4;
            border-color: #BBF7D0;
        }
        .summary-card-total span.tag { color: #166534; }
        .summary-card-total h4 { color: #15803D; font-size: 1.25rem; }

        /* Card Container Principal */
        .main-card-table {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
            margin-bottom: 1.5rem;
        }

        /* Acordeón de Secciones */
        .section-accordion {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 14px;
            overflow: hidden;
            margin-bottom: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
            transition: all 0.2s ease;
        }
        .section-accordion-header {
            padding: 0.9rem 1.2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            user-select: none;
            transition: background 0.15s;
        }
        .sec-head-admin { background: #FFFDF5; border-bottom: 1px solid #FEF3C7; color: #92400E; }
        .sec-head-tiendas { background: #F8FBFF; border-bottom: 1px solid #E0F2FE; color: #0369A1; }
        .sec-head-prod { background: #FFFAF5; border-bottom: 1px solid #FFEDD5; color: #C2410C; }

        .accordion-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 800;
            font-size: 0.88rem;
            letter-spacing: 0.3px;
        }
        .accordion-chevron {
            font-size: 0.85rem;
            transition: transform 0.2s ease;
        }
        .section-accordion.collapsed .accordion-chevron {
            transform: rotate(-90deg);
        }
        .section-accordion.collapsed .section-accordion-body {
            display: none;
        }
        .section-accordion-subtotal {
            font-weight: 800;
            font-size: 0.88rem;
        }

        /* Tabla Limpia */
        .clean-payroll-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.84rem;
        }
        .clean-payroll-table th {
            background: #F9FAFB;
            color: #64748B;
            font-size: 0.72rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid #E5E7EB;
        }
        .clean-payroll-table td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #F3F4F6;
            color: #1E293B;
            vertical-align: middle;
        }
        .clean-payroll-table tbody tr:hover {
            background: #F8FAFC;
        }

        /* Avatar Inicial */
        .worker-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #EFF6FF;
            color: #2563EB;
            font-weight: 800;
            font-size: 0.82rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #DBEAFE;
            flex-shrink: 0;
        }

        /* Botón de Desglose */
        .btn-edit-worker {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: 1px solid #E2E8F0;
            background: #FFFFFF;
            color: #64748B;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.15s;
        }
        .btn-edit-worker:hover {
            background: #F1F5F9;
            color: #0F172A;
            border-color: #CBD5E1;
        }

        /* Sticky Footer Bar */
        .sticky-footer-bar {
            position: sticky;
            bottom: 12px;
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            color: #1E293B;
            border-radius: 14px;
            padding: 0.85rem 1.4rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
            z-index: 100;
            flex-wrap: wrap;
            gap: 1rem;
            margin-top: 1rem;
        }

        /* Modals */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .modal-overlay.open { display: flex; }
        .drawer-box {
            background: #FFFFFF;
            border-radius: 18px;
            width: 100%;
            max-width: 520px;
            box-shadow: 0 20px 45px rgba(0,0,0,0.2);
            overflow: hidden;
            animation: popIn 0.18s ease-out;
        }
        @keyframes popIn {
            from { opacity: 0; transform: scale(0.96); }
            to { opacity: 1; transform: scale(1); }
        }
        .drawer-header {
            padding: 1.1rem 1.35rem;
            border-bottom: 1px solid #F3F4F6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .drawer-body {
            padding: 1.25rem 1.35rem;
            max-height: 75vh;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 0.9rem;
        }
        .drawer-footer {
            padding: 0.9rem 1.35rem;
            background: #F9FAFB;
            border-top: 1px solid #F3F4F6;
            display: flex;
            justify-content: flex-end;
            gap: 0.6rem;
        }
        .calc-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.8rem;
        }
        .calc-lbl {
            display: block;
            font-size: 0.73rem;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            margin-bottom: 0.25rem;
        }
        .calc-inp {
            width: 100%;
            padding: 0.55rem 0.75rem;
            border-radius: 8px;
            border: 1px solid #D1D5DB;
            background: #F9FAFB;
            font-size: 0.88rem;
            box-sizing: border-box;
            outline: none;
            font-weight: 600;
            color: #0F172A;
        }
        .calc-inp:focus {
            background: #FFFFFF;
            border-color: #E31E24;
            box-shadow: 0 0 0 3px rgba(227,30,36,0.1);
        }
        .calc-inp-readonly {
            background: #F1F5F9 !important;
            color: #64748B !important;
            cursor: default;
        }
        .calc-inp-dscto {
            color: #DC2626;
        }
    </style>
</head>
<body>
    <div class="app-wrapper">
        <?php include '../../views/partials/sidebar.php'; ?>
        <div class="main-content">
            <?php include '../../views/partials/header.php'; ?>

            <div class="page-content" style="padding-top:0.5rem;">
                
                <?php if (!empty($mensaje)): ?>
                    <div style="background: <?= $tipo_mensaje === 'success' ? '#ECFDF5' : '#FEF2F2' ?>; border-left: 4px solid <?= $tipo_mensaje === 'success' ? '#059669' : '#DC2626' ?>; color: <?= $tipo_mensaje === 'success' ? '#065F46' : '#991B1B' ?>; padding: 9px 14px; border-radius: 8px; font-size: 0.85rem; margin-bottom: 0.9rem; display: flex; align-items: center; justify-content: space-between;">
                        <div><i class="fas <?= $tipo_mensaje === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle' ?>"></i> <?= htmlspecialchars($mensaje) ?></div>
                        <button onclick="this.parentElement.remove()" style="background:none; border:none; color:inherit; cursor:pointer;"><i class="fas fa-times"></i></button>
                    </div>
                <?php endif; ?>

                <?php if ($planilla_id === 0 || !$curr_planilla): ?>
                    <!-- ========================================================================= -->
                    <!-- VISTA PRINCIPAL: HISTORIAL DE PLANILLAS SEMANALES -->
                    <!-- ========================================================================= -->

                    <div class="payroll-hero-bar">
                        <div class="payroll-hero-title">
                            <h1><i class="fas fa-calendar-week" style="color:#E31E24;"></i> Planilla Semanal</h1>
                            <p>Historial de desembolsos semanales, cálculo de sueldos y exportación contable</p>
                        </div>
                        <div style="display:flex; gap:0.6rem;">
                            <a href="personal.php" class="btn btn-outline" style="display:inline-flex; align-items:center; gap:6px;">
                                <i class="fas fa-users-gear"></i> Padrón de Personal
                            </a>
                            <button type="button" class="btn btn-primary" onclick="openNewWeekModal()" style="display:inline-flex; align-items:center; gap:6px; font-weight:700;">
                                <i class="fas fa-plus"></i> + Generar Nueva Planilla Semanal
                            </button>
                        </div>
                    </div>

                    <?php 
                    $tot_acumulado = array_sum(array_column($planillas_lista, 'total_general'));
                    ?>
                    <!-- Resumen del Historial -->
                    <div class="payroll-summary-strip">
                        <div class="summary-card" style="border-left: 3px solid #3B82F6;">
                            <div>
                                <span class="tag">📋 Planillas Registradas</span>
                                <h4><?= count($planillas_lista) ?> semanas</h4>
                            </div>
                        </div>
                        <div class="summary-card summary-card-total" style="border-left: 3px solid #10B981;">
                            <div>
                                <span class="tag">💰 Desembolso Acumulado</span>
                                <h4>S/ <?= number_format($tot_acumulado, 2) ?></h4>
                            </div>
                        </div>
                        <div class="summary-card" style="border-left: 3px solid #F59E0B;">
                            <div>
                                <span class="tag">📅 Última Planilla</span>
                                <h4 style="font-size:1rem;"><?= !empty($planillas_lista) ? htmlspecialchars($planillas_lista[0]['semana_codigo']) : 'Ninguna' ?></h4>
                            </div>
                        </div>
                        <div class="summary-card" style="border-left: 3px solid #8B5CF6;">
                            <div>
                                <span class="tag">👥 Personal Activo</span>
                                <h4><?= $db->query("SELECT COUNT(*) FROM personal WHERE estado = 'ACTIVO'")->fetchColumn() ?> colaboradores</h4>
                            </div>
                        </div>
                    </div>

                    <!-- Tabla del Historial -->
                    <div class="main-card-table">
                        <div class="table-responsive">
                            <table class="clean-payroll-table">
                                <thead>
                                    <tr>
                                        <th style="width:30px; text-align:center;">#</th>
                                        <th>SEMANA / CÓDIGO</th>
                                        <th>PERIODO DE TRABAJO</th>
                                        <th>FECHA DE PAGO</th>
                                        <th style="text-align:right;">ADMINISTRATIVO</th>
                                        <th style="text-align:right;">TIENDAS</th>
                                        <th style="text-align:right;">PRODUCCIÓN</th>
                                        <th style="text-align:right;">TOTAL DESEMBOLSO</th>
                                        <th style="text-align:center; width:160px;">ACCIONES</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($planillas_lista)): ?>
                                        <tr>
                                            <td colspan="9" style="text-align:center; padding:3rem 1.5rem; color:#9CA3AF;">
                                                <i class="fas fa-calendar-xmark" style="font-size:2.2rem; margin-bottom:0.6rem; display:block; color:#CBD5E1;"></i>
                                                Aún no se ha generado ninguna planilla semanal.
                                                <div style="margin-top:1rem;">
                                                    <button type="button" class="btn btn-primary" onclick="openNewWeekModal()">
                                                        <i class="fas fa-plus"></i> Generar Primera Planilla
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php else: 
                                        $num = 1;
                                        foreach ($planillas_lista as $pl): 
                                    ?>
                                        <tr>
                                            <td style="text-align:center; font-weight:700; color:#94A3B8;"><?= $num++ ?></td>
                                            <td>
                                                <a href="planilla.php?id=<?= $pl['id'] ?>" style="text-decoration:none; color:#0F172A;">
                                                    <strong style="font-size:0.9rem; display:block;"><?= htmlspecialchars($pl['semana_codigo']) ?></strong>
                                                </a>
                                                <?php if(!empty($pl['observaciones'])): ?>
                                                    <span style="font-size:0.72rem; color:#64748B;"><?= htmlspecialchars($pl['observaciones']) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="font-size:0.82rem; color:#475569;">
                                                <i class="far fa-calendar" style="color:#94A3B8; margin-right:4px;"></i>
                                                <?= date('d/m/Y', strtotime($pl['fecha_inicio'])) ?> al <?= date('d/m/Y', strtotime($pl['fecha_fin'])) ?>
                                            </td>
                                            <td style="font-size:0.82rem; color:#475569; font-weight:600;">
                                                <?= date('d/m/Y', strtotime($pl['fecha_pago'])) ?>
                                            </td>
                                            <td style="text-align:right; font-weight:600; color:#475569;">
                                                S/ <?= number_format($pl['total_administrativo'], 2) ?>
                                            </td>
                                            <td style="text-align:right; font-weight:600; color:#475569;">
                                                S/ <?= number_format($pl['total_tiendas'], 2) ?>
                                            </td>
                                            <td style="text-align:right; font-weight:600; color:#475569;">
                                                S/ <?= number_format($pl['total_produccion'], 2) ?>
                                            </td>
                                            <td style="text-align:right; font-weight:800; color:#059669; font-size:0.95rem;">
                                                S/ <?= number_format($pl['total_general'], 2) ?>
                                            </td>
                                            <td style="text-align:center;">
                                                <div style="display:flex; justify-content:center; gap:6px;">
                                                    <a href="planilla.php?id=<?= $pl['id'] ?>" class="btn btn-outline" style="padding:4px 9px; font-size:0.78rem; color:#2563EB; font-weight:700; display:inline-flex; align-items:center; gap:4px;" title="Abrir y Editar Planilla">
                                                        <i class="fas fa-folder-open"></i> Abrir
                                                    </a>
                                                    <a href="export_planilla_excel.php?id=<?= $pl['id'] ?>" class="btn btn-outline" style="padding:4px 8px; font-size:0.78rem; color:#059669;" title="Descargar Excel">
                                                        <i class="fas fa-file-excel"></i>
                                                    </a>
                                                    <form method="POST" style="display:inline;" onsubmit="return confirm('¿Seguro de eliminar esta planilla permanentemente?');">
                                                        <input type="hidden" name="action" value="eliminar_planilla">
                                                        <input type="hidden" name="planilla_id" value="<?= $pl['id'] ?>">
                                                        <button type="submit" class="btn btn-outline" style="padding:4px 8px; font-size:0.78rem; color:#DC2626;" title="Eliminar">
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

                <?php else: ?>
                    <!-- ========================================================================= -->
                    <!-- VISTA DETALLE: GESTOR DE LA PLANILLA SELECCIONADA -->
                    <!-- ========================================================================= -->

                    <!-- Top Bar con botón Volver al Historial -->
                    <div class="payroll-top-bar">
                        <div style="display:flex; align-items:center; gap:12px;">
                            <a href="planilla.php" class="btn btn-outline" style="font-size:0.82rem; padding:0.55rem 0.9rem; font-weight:700; display:inline-flex; align-items:center; gap:6px;">
                                <i class="fas fa-arrow-left"></i> Volver al Historial
                            </a>
                            <div class="week-picker-box">
                                <div class="week-badge-icon">
                                    <i class="fas fa-calendar-week"></i>
                                </div>
                                <div>
                                    <span style="display:block; font-size:0.68rem; font-weight:700; color:#64748B; text-transform:uppercase;">Semana Activa:</span>
                                    <select class="week-dropdown" onchange="location.href='planilla.php?id=' + this.value;">
                                        <?php foreach ($planillas_lista as $pl): ?>
                                        <option value="<?= $pl['id'] ?>" <?= $pl['id'] == $curr_planilla['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($pl['semana_codigo']) ?> (<?= date('d/m', strtotime($pl['fecha_inicio'])) ?> al <?= date('d/m/Y', strtotime($pl['fecha_fin'])) ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div style="display:flex; gap:0.5rem; align-items:center;">
                            <a href="export_planilla_excel.php?id=<?= $curr_planilla['id'] ?>" class="btn btn-outline" style="background:#ECFDF5; border-color:#A7F3D0; color:#065F46; font-weight:700; font-size:0.82rem; padding:0.55rem 0.9rem; display:inline-flex; align-items:center; gap:6px;">
                                <i class="fas fa-file-excel" style="color:#059669;"></i> Descargar Excel
                            </a>
                            <a href="personal.php" class="btn btn-outline" style="font-size:0.82rem; padding:0.55rem 0.9rem;">
                                <i class="fas fa-users-gear"></i> Personal
                            </a>
                            <button type="button" class="btn btn-primary" onclick="openNewWeekModal()" style="font-size:0.82rem; padding:0.55rem 0.9rem; font-weight:700;">
                                <i class="fas fa-plus"></i> Nueva Semana
                            </button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Seguro de eliminar esta planilla?');">
                                <input type="hidden" name="action" value="eliminar_planilla">
                                <input type="hidden" name="planilla_id" value="<?= $curr_planilla['id'] ?>">
                                <button type="submit" class="btn btn-outline" style="color:#DC2626; padding:0.55rem 0.75rem;" title="Eliminar Planilla">
                                    <i class="fas fa-trash-can"></i>
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- KPI Cards Resumen -->
                    <div class="payroll-summary-strip">
                        <div class="summary-card" style="border-left: 3px solid #EAB308;">
                            <div>
                                <span class="tag">🟡 Administrativo</span>
                                <h4 id="kpi_total_admin">S/ <?= number_format($curr_planilla['total_administrativo'], 2) ?></h4>
                            </div>
                        </div>
                        <div class="summary-card" style="border-left: 3px solid #0284C7;">
                            <div>
                                <span class="tag">🔵 Tiendas / Ventas</span>
                                <h4 id="kpi_total_tiendas">S/ <?= number_format($curr_planilla['total_tiendas'], 2) ?></h4>
                            </div>
                        </div>
                        <div class="summary-card" style="border-left: 3px solid #EA580C;">
                            <div>
                                <span class="tag">🟠 Producción</span>
                                <h4 id="kpi_total_prod">S/ <?= number_format($curr_planilla['total_produccion'], 2) ?></h4>
                            </div>
                        </div>
                        <div class="summary-card summary-card-total" style="border-left: 3px solid #10B981;">
                            <div>
                                <span class="tag">🟢 Total Desembolso</span>
                                <h4 id="kpi_total_general">S/ <?= number_format($curr_planilla['total_general'], 2) ?></h4>
                            </div>
                        </div>
                    </div>

                    <!-- Formulario de Guardado -->
                    <form method="POST" action="planilla.php?id=<?= $curr_planilla['id'] ?>" id="formPlanilla">
                        <input type="hidden" name="action" value="guardar_detalle_completo">
                        <input type="hidden" name="planilla_id" value="<?= $curr_planilla['id'] ?>">

                        <!-- 1. ACORDEÓN ADMINISTRATIVO -->
                        <div class="section-accordion" id="acc_admin">
                            <div class="section-accordion-header sec-head-admin" onclick="toggleAccordion('acc_admin')">
                                <div class="accordion-title">
                                    <i class="fas fa-chevron-down accordion-chevron"></i>
                                    <span>🟡 ADMINISTRATIVO</span>
                                </div>
                                <div class="section-accordion-subtotal">
                                    Subtotal: <span id="sub_badge_admin">S/ <?= number_format($curr_planilla['total_administrativo'], 2) ?></span>
                                </div>
                            </div>
                            <div class="section-accordion-body">
                                <div class="table-responsive">
                                    <table class="clean-payroll-table">
                                        <thead>
                                            <tr>
                                                <th style="width:280px;">EMPLEADO</th>
                                                <th>ÁREA</th>
                                                <th style="text-align:right;">SUELDO BASE</th>
                                                <th style="text-align:right;">BONOS</th>
                                                <th style="text-align:right;">DESCUENTOS</th>
                                                <th style="text-align:right;">TOTAL A PAGAR</th>
                                                <th style="width:50px; text-align:center;">ACCIONES</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($detalles_admin as $d): ?>
                                                <?php include 'clean_row_item.php'; ?>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr style="background:#FAFAFC; font-weight:800; font-size:0.83rem;">
                                                <td colspan="5" style="text-align:right; color:#64748B;">Total Administrativo:</td>
                                                <td style="text-align:right; color:#0F172A;" id="sub_pagar_admin">S/ <?= number_format($curr_planilla['total_administrativo'], 2) ?></td>
                                                <td></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- 2. ACORDEÓN TIENDAS / VENTAS -->
                        <div class="section-accordion" id="acc_tiendas">
                            <div class="section-accordion-header sec-head-tiendas" onclick="toggleAccordion('acc_tiendas')">
                                <div class="accordion-title">
                                    <i class="fas fa-chevron-down accordion-chevron"></i>
                                    <span>🔵 TIENDAS / VENTAS</span>
                                </div>
                                <div class="section-accordion-subtotal">
                                    Subtotal: <span id="sub_badge_tiendas">S/ <?= number_format($curr_planilla['total_tiendas'], 2) ?></span>
                                </div>
                            </div>
                            <div class="section-accordion-body">
                                <div class="table-responsive">
                                    <table class="clean-payroll-table">
                                        <thead>
                                            <tr>
                                                <th style="width:280px;">EMPLEADO</th>
                                                <th>ÁREA</th>
                                                <th style="text-align:right;">SUELDO BASE</th>
                                                <th style="text-align:right;">BONOS</th>
                                                <th style="text-align:right;">DESCUENTOS</th>
                                                <th style="text-align:right;">TOTAL A PAGAR</th>
                                                <th style="width:50px; text-align:center;">ACCIONES</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($detalles_tiendas as $d): ?>
                                                <?php include 'clean_row_item.php'; ?>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr style="background:#FAFAFC; font-weight:800; font-size:0.83rem;">
                                                <td colspan="5" style="text-align:right; color:#64748B;">Total Tiendas / Ventas:</td>
                                                <td style="text-align:right; color:#0F172A;" id="sub_pagar_tiendas">S/ <?= number_format($curr_planilla['total_tiendas'], 2) ?></td>
                                                <td></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- 3. ACORDEÓN PRODUCCIÓN & TALLER -->
                        <div class="section-accordion" id="acc_prod">
                            <div class="section-accordion-header sec-head-prod" onclick="toggleAccordion('acc_prod')">
                                <div class="accordion-title">
                                    <i class="fas fa-chevron-down accordion-chevron"></i>
                                    <span>🟠 PRODUCCIÓN, TALLER & SERVICIOS EXTERNOS</span>
                                </div>
                                <div class="section-accordion-subtotal">
                                    Subtotal: <span id="sub_badge_prod">S/ <?= number_format($curr_planilla['total_produccion'], 2) ?></span>
                                </div>
                            </div>
                            <div class="section-accordion-body">
                                <div class="table-responsive">
                                    <table class="clean-payroll-table">
                                        <thead>
                                            <tr>
                                                <th style="width:280px;">EMPLEADO</th>
                                                <th>ÁREA</th>
                                                <th style="text-align:right;">SUELDO BASE</th>
                                                <th style="text-align:right;">BONOS</th>
                                                <th style="text-align:right;">DESCUENTOS</th>
                                                <th style="text-align:right;">TOTAL A PAGAR</th>
                                                <th style="width:50px; text-align:center;">ACCIONES</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($detalles_prod as $d): ?>
                                                <?php include 'clean_row_item.php'; ?>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr style="background:#FAFAFC; font-weight:800; font-size:0.83rem;">
                                                <td colspan="5" style="text-align:right; color:#64748B;">Total Producción:</td>
                                                <td style="text-align:right; color:#0F172A;" id="sub_pagar_prod">S/ <?= number_format($curr_planilla['total_produccion'], 2) ?></td>
                                                <td></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Sticky Footer Bar -->
                        <div class="sticky-footer-bar">
                            <div>
                                <span style="font-size:0.75rem; color:#64748B; text-transform:uppercase; font-weight:700;">Semana Seleccionada:</span>
                                <strong style="font-size:0.95rem; margin-left:5px; color:#0F172A;"><?= htmlspecialchars($curr_planilla['semana_codigo']) ?></strong>
                            </div>
                            <div style="display:flex; align-items:center; gap:1.4rem;">
                                <div style="text-align:right;">
                                    <span style="font-size:0.72rem; color:#64748B; display:block; text-transform:uppercase; font-weight:700;">Total Desembolso Semanal:</span>
                                    <strong style="color:#059669; font-size:1.3rem;" id="bar_total_general">S/ <?= number_format($curr_planilla['total_general'], 2) ?></strong>
                                </div>
                                <button type="submit" class="btn btn-primary" style="padding:0.65rem 1.6rem; font-weight:800; font-size:0.88rem; box-shadow:0 4px 14px rgba(227,30,36,0.3);">
                                    <i class="fas fa-save"></i> Guardar Cambios
                                </button>
                            </div>
                        </div>

                    </form>

                <?php endif; ?>

            </div>
        </div>
    </div>

    <!-- Modal Drawer de Desglose de Cálculo del Trabajador -->
    <div class="modal-overlay" id="modalWorkerCalc">
        <div class="drawer-box">
            <div class="drawer-header">
                <div>
                    <h3 style="margin:0; font-size:1.05rem; font-weight:800; color:#111827;" id="calcWorkerName">Detalle del Colaborador</h3>
                    <span id="calcWorkerMeta" style="font-size:0.78rem; color:#64748B; display:block; margin-top:2px;"></span>
                </div>
                <button type="button" onclick="closeWorkerModal()" style="background:#F3F4F6; border:none; width:30px; height:30px; border-radius:8px; font-size:0.95rem; color:#6B7280; cursor:pointer;">&times;</button>
            </div>
            
            <div class="drawer-body">
                <!-- Switch Incluir -->
                <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:10px; padding:0.75rem 1rem; display:flex; align-items:center; justify-content:space-between;">
                    <span style="font-size:0.82rem; font-weight:700; color:#1E293B;">¿Incluir en el pago de esta semana?</span>
                    <input type="checkbox" id="m_incluido" style="width:18px; height:18px; cursor:pointer;" onchange="onModalRecalc()">
                </div>

                <!-- Bloque Ingresos -->
                <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:12px; padding:1rem;">
                    <h4 style="margin:0 0 0.75rem 0; font-size:0.8rem; font-weight:800; color:#0F172A; text-transform:uppercase; letter-spacing:0.4px;">
                        💵 1. Ingresos & Sueldo Base
                    </h4>
                    
                    <div class="calc-grid-2" style="margin-bottom:0.75rem;">
                        <div>
                            <label class="calc-lbl">Base Semanal (S/)</label>
                            <input type="number" step="0.01" id="m_base_semanal" class="calc-inp" placeholder="0.00" oninput="onModalBaseChange()">
                        </div>
                        <div>
                            <label class="calc-lbl">Bono / Comisión (S/)</label>
                            <input type="number" step="0.01" id="m_bono" class="calc-inp" placeholder="0.00" oninput="onModalRecalc()">
                        </div>
                    </div>

                    <div class="calc-grid-2" style="margin-bottom:0.75rem;">
                        <div>
                            <label class="calc-lbl">Horas Extra (Cant.)</label>
                            <input type="number" step="0.5" id="m_hextra" class="calc-inp" placeholder="0" oninput="onModalRecalc()">
                        </div>
                        <div>
                            <label class="calc-lbl">Pago x Hora (Auto)</label>
                            <input type="number" step="0.01" id="m_pagohora" class="calc-inp calc-inp-readonly" readonly>
                        </div>
                    </div>

                    <div class="calc-grid-2">
                        <div>
                            <label class="calc-lbl">Mensual (Auto)</label>
                            <input type="number" step="0.01" id="m_mensual" class="calc-inp calc-inp-readonly" readonly>
                        </div>
                        <div>
                            <label class="calc-lbl">Base x Día (Auto)</label>
                            <input type="number" step="0.01" id="m_basedia" class="calc-inp calc-inp-readonly" readonly>
                        </div>
                    </div>
                </div>

                <!-- Bloque Descuentos -->
                <div style="background:#FFF5F5; border:1px solid #FED7D7; border-radius:12px; padding:1rem;">
                    <h4 style="margin:0 0 0.75rem 0; font-size:0.8rem; font-weight:800; color:#991B1B; text-transform:uppercase; letter-spacing:0.4px;">
                        🔻 2. Descuentos & Deducciones
                    </h4>
                    
                    <div class="calc-grid-2" style="margin-bottom:0.75rem;">
                        <div>
                            <label class="calc-lbl" style="color:#991B1B;">Falta x Hora (S/)</label>
                            <input type="number" step="0.01" id="m_dfalta" class="calc-inp calc-inp-dscto" placeholder="0.00" oninput="onModalRecalc()">
                        </div>
                        <div>
                            <label class="calc-lbl" style="color:#991B1B;">Dsto. Préstamo (S/)</label>
                            <input type="number" step="0.01" id="m_dprestamo" class="calc-inp calc-inp-dscto" placeholder="0.00" oninput="onModalRecalc()">
                        </div>
                    </div>

                    <div class="calc-grid-2">
                        <div>
                            <label class="calc-lbl" style="color:#991B1B;">Dsto. Planilla (S/)</label>
                            <input type="number" step="0.01" id="m_dplanilla" class="calc-inp calc-inp-dscto" placeholder="0.00" oninput="onModalRecalc()">
                        </div>
                        <div>
                            <label class="calc-lbl" style="color:#991B1B;">Total Dsctos (Auto)</label>
                            <input type="number" step="0.01" id="m_totdsctos" class="calc-inp calc-inp-readonly calc-inp-dscto" readonly style="font-weight:800;">
                        </div>
                    </div>
                </div>

                <!-- Total Neto Preview -->
                <div style="background:#F0FDF4; border:1px solid #BBF7D0; border-radius:12px; padding:0.9rem 1.1rem; display:flex; align-items:center; justify-content:space-between;">
                    <span style="font-size:0.85rem; font-weight:800; color:#166534;">TOTAL NETO A PAGAR:</span>
                    <strong style="font-size:1.35rem; color:#15803D;" id="m_totpagar_preview">S/ 0.00</strong>
                </div>

            </div>

            <div class="drawer-footer">
                <button type="button" class="btn btn-outline" onclick="closeWorkerModal()">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="applyWorkerCalc()"><i class="fas fa-check"></i> Aplicar y Actualizar</button>
            </div>
        </div>
    </div>

    <!-- Modal Nueva Semana -->
    <div class="modal-overlay" id="modalNewWeek">
        <div class="drawer-box" style="max-width:460px;">
            <div class="drawer-header">
                <h3 style="margin:0; font-size:1.1rem; font-weight:800; color:#111827;">
                    <i class="fas fa-calendar-plus" style="color:#E31E24;"></i> Nueva Planilla Semanal
                </h3>
                <button type="button" onclick="closeNewWeekModal()" style="background:#F3F4F6; border:none; width:30px; height:30px; border-radius:8px; font-size:0.95rem; color:#6B7280; cursor:pointer;">&times;</button>
            </div>
            <form method="POST" action="planilla.php">
                <input type="hidden" name="action" value="crear_planilla">
                <div class="drawer-body">
                    <div>
                        <label class="calc-lbl">Código / Nombre de la Semana *</label>
                        <input type="text" name="semana_codigo" class="calc-inp" value="PLANILLA <?= date('d/m/Y') ?>" required style="font-weight:700;">
                    </div>
                    <div class="calc-grid-2">
                        <div>
                            <label class="calc-lbl">Fecha Inicio *</label>
                            <input type="date" name="fecha_inicio" class="calc-inp" value="<?= date('Y-m-d', strtotime('last monday')) ?>" required>
                        </div>
                        <div>
                            <label class="calc-lbl">Fecha Fin *</label>
                            <input type="date" name="fecha_fin" class="calc-inp" value="<?= date('Y-m-d', strtotime('next sunday')) ?>" required>
                        </div>
                    </div>
                    <div>
                        <label class="calc-lbl">Fecha de Pago *</label>
                        <input type="date" name="fecha_pago" class="calc-inp" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div>
                        <label class="calc-lbl">Observaciones (Opcional)</label>
                        <input type="text" name="observaciones" class="calc-inp">
                    </div>
                </div>
                <div class="drawer-footer">
                    <button type="button" class="btn btn-outline" onclick="closeNewWeekModal()">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Generar Planilla</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    let activeRowId = null;

    function toggleAccordion(accId) {
        const el = document.getElementById(accId);
        if (el) el.classList.toggle('collapsed');
    }

    function openNewWeekModal() {
        document.getElementById('modalNewWeek').classList.add('open');
    }
    function closeNewWeekModal() {
        document.getElementById('modalNewWeek').classList.remove('open');
    }

    function openWorkerCalc(rowId) {
        activeRowId = rowId;
        const row = document.querySelector(`.clean-row[data-rowid="${rowId}"]`);
        if (!row) return;

        document.getElementById('calcWorkerName').innerText = row.dataset.nombre;
        document.getElementById('calcWorkerMeta').innerText = row.dataset.area + ' • ' + (row.dataset.cuenta || 'Sin cuenta registrada');

        document.getElementById('m_incluido').checked = (document.getElementById(`hid_incluido_${rowId}`).value === '1');
        document.getElementById('m_base_semanal').value = document.getElementById(`hid_base_${rowId}`).value;
        document.getElementById('m_bono').value = document.getElementById(`hid_bono_${rowId}`).value;
        document.getElementById('m_hextra').value = document.getElementById(`hid_hextra_${rowId}`).value;
        document.getElementById('m_dfalta').value = document.getElementById(`hid_dfalta_${rowId}`).value;
        document.getElementById('m_dprestamo').value = document.getElementById(`hid_dprestamo_${rowId}`).value;
        document.getElementById('m_dplanilla').value = document.getElementById(`hid_dplanilla_${rowId}`).value;

        onModalBaseChange();
        document.getElementById('modalWorkerCalc').classList.add('open');
    }

    function closeWorkerModal() {
        document.getElementById('modalWorkerCalc').classList.remove('open');
        activeRowId = null;
    }

    function onModalBaseChange() {
        const baseSem = parseFloat(document.getElementById('m_base_semanal').value) || 0;
        const mensual = parseFloat((baseSem * 4).toFixed(2));
        const baseDia = parseFloat((baseSem / 6).toFixed(2));
        const pagoHora = parseFloat((baseDia / 10).toFixed(2));

        document.getElementById('m_mensual').value = mensual.toFixed(2);
        document.getElementById('m_basedia').value = baseDia.toFixed(2);
        document.getElementById('m_pagohora').value = pagoHora.toFixed(2);

        onModalRecalc();
    }

    function onModalRecalc() {
        const isIncluded = document.getElementById('m_incluido').checked;
        const baseSem = parseFloat(document.getElementById('m_base_semanal').value) || 0;
        const bono = parseFloat(document.getElementById('m_bono').value) || 0;
        const hExtra = parseFloat(document.getElementById('m_hextra').value) || 0;
        const pagoHora = parseFloat(document.getElementById('m_pagohora').value) || 0;

        const dstoFalta = parseFloat(document.getElementById('m_dfalta').value) || 0;
        const dstoPrestamo = parseFloat(document.getElementById('m_dprestamo').value) || 0;
        const dstoPlanilla = parseFloat(document.getElementById('m_dplanilla').value) || 0;

        const totDsctos = parseFloat((dstoFalta + dstoPrestamo + dstoPlanilla).toFixed(2));
        const montoHExtra = parseFloat((hExtra * pagoHora).toFixed(2));

        let totPagar = 0;
        if (isIncluded) {
            totPagar = Math.max(0, parseFloat(((baseSem + bono) + montoHExtra - totDsctos).toFixed(2)));
        }

        document.getElementById('m_totdsctos').value = totDsctos.toFixed(2);
        document.getElementById('m_totpagar_preview').innerText = 'S/ ' + totPagar.toFixed(2);
    }

    function applyWorkerCalc() {
        if (!activeRowId) return;
        const rowId = activeRowId;

        const isIncluded = document.getElementById('m_incluido').checked;
        const baseSem = parseFloat(document.getElementById('m_base_semanal').value) || 0;
        const bono = parseFloat(document.getElementById('m_bono').value) || 0;
        const hExtra = parseFloat(document.getElementById('m_hextra').value) || 0;
        const pagoHora = parseFloat(document.getElementById('m_pagohora').value) || 0;
        const mensual = parseFloat(document.getElementById('m_mensual').value) || 0;
        const baseDia = parseFloat(document.getElementById('m_basedia').value) || 0;

        const dstoFalta = parseFloat(document.getElementById('m_dfalta').value) || 0;
        const dstoPrestamo = parseFloat(document.getElementById('m_dprestamo').value) || 0;
        const dstoPlanilla = parseFloat(document.getElementById('m_dplanilla').value) || 0;
        const totDsctos = parseFloat((dstoFalta + dstoPrestamo + dstoPlanilla).toFixed(2));

        const montoHExtra = parseFloat((hExtra * pagoHora).toFixed(2));
        let totPagar = 0;
        if (isIncluded) {
            totPagar = Math.max(0, parseFloat(((baseSem + bono) + montoHExtra - totDsctos).toFixed(2)));
        }

        // Actualizar inputs ocultos en el formulario principal
        document.getElementById(`hid_incluido_${rowId}`).value = isIncluded ? '1' : '0';
        document.getElementById(`hid_base_${rowId}`).value = baseSem.toFixed(2);
        document.getElementById(`hid_bono_${rowId}`).value = bono.toFixed(2);
        document.getElementById(`hid_hextra_${rowId}`).value = hExtra;
        document.getElementById(`hid_pagohora_${rowId}`).value = pagoHora.toFixed(2);
        document.getElementById(`hid_mensual_${rowId}`).value = mensual.toFixed(2);
        document.getElementById(`hid_basedia_${rowId}`).value = baseDia.toFixed(2);
        document.getElementById(`hid_dfalta_${rowId}`).value = dstoFalta.toFixed(2);
        document.getElementById(`hid_dprestamo_${rowId}`).value = dstoPrestamo.toFixed(2);
        document.getElementById(`hid_dplanilla_${rowId}`).value = dstoPlanilla.toFixed(2);
        document.getElementById(`hid_totdsctos_${rowId}`).value = totDsctos.toFixed(2);
        document.getElementById(`hid_totpagar_${rowId}`).value = totPagar.toFixed(2);

        // Actualizar vista de la tabla
        document.getElementById(`view_base_${rowId}`).innerText = 'S/ ' + baseSem.toFixed(2);
        document.getElementById(`view_bono_${rowId}`).innerText = 'S/ ' + bono.toFixed(2);
        document.getElementById(`view_dscto_${rowId}`).innerText = 'S/ ' + totDsctos.toFixed(2);
        document.getElementById(`view_pagar_${rowId}`).innerText = 'S/ ' + totPagar.toFixed(2);

        if (!isIncluded) {
            document.querySelector(`.clean-row[data-rowid="${rowId}"]`).style.opacity = '0.5';
        } else {
            document.querySelector(`.clean-row[data-rowid="${rowId}"]`).style.opacity = '1';
        }

        closeWorkerModal();
        recalcGlobalTotals();
    }

    function recalcGlobalTotals() {
        const rows = document.querySelectorAll('.clean-row');
        let totAdmin = 0, totTiendas = 0, totProd = 0;

        rows.forEach(r => {
            const rowId = r.dataset.rowid;
            const cat = r.dataset.categoria;
            const incluido = document.getElementById(`hid_incluido_${rowId}`)?.value === '1';
            const pagar = parseFloat(document.getElementById(`hid_totpagar_${rowId}`)?.value) || 0;

            if (incluido) {
                if (cat === 'ADMINISTRATIVO') totAdmin += pagar;
                else if (cat === 'TIENDAS') totTiendas += pagar;
                else totProd += pagar;
            }
        });

        // Actualizar subtotales
        if (document.getElementById('sub_badge_admin')) {
            document.getElementById('sub_badge_admin').innerText = 'S/ ' + totAdmin.toFixed(2);
            document.getElementById('sub_pagar_admin').innerText = 'S/ ' + totAdmin.toFixed(2);
            document.getElementById('kpi_total_admin').innerText = 'S/ ' + totAdmin.toFixed(2);

            document.getElementById('sub_badge_tiendas').innerText = 'S/ ' + totTiendas.toFixed(2);
            document.getElementById('sub_pagar_tiendas').innerText = 'S/ ' + totTiendas.toFixed(2);
            document.getElementById('kpi_total_tiendas').innerText = 'S/ ' + totTiendas.toFixed(2);

            document.getElementById('sub_badge_prod').innerText = 'S/ ' + totProd.toFixed(2);
            document.getElementById('sub_pagar_prod').innerText = 'S/ ' + totProd.toFixed(2);
            document.getElementById('kpi_total_prod').innerText = 'S/ ' + totProd.toFixed(2);

            const totGen = totAdmin + totTiendas + totProd;
            document.getElementById('kpi_total_general').innerText = 'S/ ' + totGen.toFixed(2);
            document.getElementById('bar_total_general').innerText = 'S/ ' + totGen.toFixed(2);
        }
    }
    </script>

    <?php include '../../views/partials/footer.php'; ?>
</body>
</html>
