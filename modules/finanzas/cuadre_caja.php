<?php
require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../config/db.php';

$page_title = 'Cuadre de Caja Tiendas';
$page_subtitle = 'Arqueo semanal y diario de recaudaciones por tienda y liquidación de egresos';

// Handle AJAX request for single cuadre data
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_cuadre') {
    header('Content-Type: application/json');
    $id = intval($_GET['id'] ?? 0);
    $stmt = $db->prepare("SELECT * FROM finanzas_cuadre_caja WHERE id = ?");
    $stmt->execute([$id]);
    $cuadre = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cuadre) {
        echo json_encode(['success' => false, 'message' => 'No encontrado']);
        exit;
    }
    
    $stmtDet = $db->prepare("SELECT * FROM finanzas_cuadre_detalle WHERE cuadre_id = ? ORDER BY id ASC");
    $stmtDet->execute([$id]);
    $detalles = $stmtDet->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'cuadre' => $cuadre, 'detalles' => $detalles]);
    exit;
}

// Handle AJAX request for last saldo
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_last_saldo') {
    header('Content-Type: application/json');
    $stmt = $db->query("SELECT saldo_final, codigo, fecha_fin FROM finanzas_cuadre_caja ORDER BY id DESC LIMIT 1");
    $last = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'last' => $last]);
    exit;
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_cuadre') {
        try {
            $db->beginTransaction();
            $id = intval($_POST['id'] ?? 0);
            
            $codigo = trim($_POST['codigo'] ?? '');
            if (empty($codigo)) {
                $count = $db->query("SELECT COUNT(*) FROM finanzas_cuadre_caja")->fetchColumn() + 1;
                $codigo = 'CC-' . date('Y') . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);
            }
            
            $area = trim($_POST['area'] ?? 'ADMINISTRATIVO');
            $tienda = trim($_POST['tienda'] ?? 'GENERAL');
            $encargado = trim($_POST['encargado'] ?? 'NAOMI');
            $fecha_inicio = !empty($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : date('Y-m-d');
            $fecha_fin = !empty($_POST['fecha_fin']) ? $_POST['fecha_fin'] : $fecha_inicio;
            
            $aut_responsable = trim($_POST['autorizacion_responsable'] ?? 'CARPICENTER');
            $f_aut_responsable = !empty($_POST['fecha_aut_responsable']) ? $_POST['fecha_aut_responsable'] : $fecha_inicio;
            $pagar_a = trim($_POST['pagar_a'] ?? '');
            $f_pagar_a = !empty($_POST['fecha_pagar_a']) ? $_POST['fecha_pagar_a'] : null;
            $aut_direccion = trim($_POST['autorizacion_direccion'] ?? '');
            $f_aut_direccion = !empty($_POST['fecha_aut_direccion']) ? $_POST['fecha_aut_direccion'] : null;
            
            $saldo_anterior = floatval($_POST['saldo_anterior'] ?? 0);
            $observacion = trim($_POST['observacion'] ?? '');
            $estado = trim($_POST['estado'] ?? 'CERRADO');
            
            // Entradas fijas y dinámicas
            $ent_fechas = $_POST['entrada_fecha'] ?? [];
            $ent_descripciones = $_POST['entrada_descripcion'] ?? [];
            $ent_justificantes = $_POST['entrada_justificante'] ?? [];
            $ent_montos = $_POST['entrada_monto'] ?? [];
            $ent_categorias = $_POST['entrada_categoria'] ?? [];
            
            $total_ingreso = 0;
            $entradas_to_insert = [];
            for ($i = 0; $i < count($ent_descripciones); $i++) {
                $desc = trim($ent_descripciones[$i] ?? '');
                $monto = floatval($ent_montos[$i] ?? 0);
                $just = trim($ent_justificantes[$i] ?? '');
                $fec = !empty($ent_fechas[$i]) ? $ent_fechas[$i] : $fecha_inicio;
                $cat = trim($ent_categorias[$i] ?? 'TIENDA');
                
                if ($cat === 'SALDO_ANTERIOR') {
                    $saldo_anterior = $monto;
                }
                
                if (!empty($desc) || $monto > 0) {
                    $total_ingreso += $monto;
                    $entradas_to_insert[] = [
                        'fecha' => $fec,
                        'tipo' => 'ENTRADA',
                        'categoria' => $cat,
                        'descripcion' => $desc,
                        'nro_justificante' => $just,
                        'monto' => $monto
                    ];
                }
            }
            
            // Egresos / Salidas
            $egr_fechas = $_POST['egreso_fecha'] ?? [];
            $egr_descripciones = $_POST['egreso_descripcion'] ?? [];
            $egr_justificantes = $_POST['egreso_justificante'] ?? [];
            $egr_montos = $_POST['egreso_monto'] ?? [];
            $egr_categorias = $_POST['egreso_categoria'] ?? [];
            
            $tot_produccion = 0;
            $tot_melamine = 0;
            $tot_servicio = 0;
            $tot_combustible = 0;
            $tot_movilidad = 0;
            $tot_otros = 0;
            $total_egreso = 0;
            $salidas_to_insert = [];
            
            for ($j = 0; $j < count($egr_descripciones); $j++) {
                $desc = trim($egr_descripciones[$j] ?? '');
                $monto = floatval($egr_montos[$j] ?? 0);
                $just = trim($egr_justificantes[$j] ?? '');
                $fec = !empty($egr_fechas[$j]) ? $egr_fechas[$j] : $fecha_inicio;
                $cat = strtoupper(trim($egr_categorias[$j] ?? 'OTROS'));
                
                if (!empty($desc) && $monto > 0) {
                    $total_egreso += $monto;
                    switch($cat) {
                        case 'PRODUCCION':
                        case 'PRODUCCIÓN':
                            $tot_produccion += $monto; break;
                        case 'MELAMINE':
                            $tot_melamine += $monto; break;
                        case 'SERVICIO':
                            $tot_servicio += $monto; break;
                        case 'COMBUSTIBLE':
                            $tot_combustible += $monto; break;
                        case 'MOVILIDAD':
                            $tot_movilidad += $monto; break;
                        default:
                            $tot_otros += $monto; break;
                    }
                    $salidas_to_insert[] = [
                        'fecha' => $fec,
                        'tipo' => 'SALIDA',
                        'categoria' => $cat,
                        'descripcion' => $desc,
                        'nro_justificante' => $just,
                        'monto' => $monto
                    ];
                }
            }
            
            $saldo_final = $total_ingreso - $total_egreso;
            
            if ($id > 0) {
                // Actualizar cabecera
                $stmt = $db->prepare("
                    UPDATE finanzas_cuadre_caja SET
                        codigo = ?, titulo = ?, area = ?, tienda = ?, encargado = ?,
                        fecha_inicio = ?, fecha_fin = ?,
                        autorizacion_responsable = ?, fecha_aut_responsable = ?,
                        pagar_a = ?, fecha_pagar_a = ?,
                        autorizacion_direccion = ?, fecha_aut_direccion = ?,
                        saldo_anterior = ?, total_ingreso = ?,
                        total_salida_produccion = ?, total_salida_melamine = ?,
                        total_salida_servicio = ?, total_salida_combustible = ?,
                        total_salida_movilidad = ?, total_salida_otros = ?,
                        total_egreso = ?, saldo_final = ?,
                        observacion = ?, estado = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $codigo, $codigo, $area, $tienda, $encargado,
                    $fecha_inicio, $fecha_fin,
                    $aut_responsable, $f_aut_responsable,
                    $pagar_a, $f_pagar_a,
                    $aut_direccion, $f_aut_direccion,
                    $saldo_anterior, $total_ingreso,
                    $tot_produccion, $tot_melamine,
                    $tot_servicio, $tot_combustible,
                    $tot_movilidad, $tot_otros,
                    $total_egreso, $saldo_final,
                    $observacion, $estado,
                    $id
                ]);
                $cuadre_id = $id;
                
                // Borrar detalles antiguos
                $db->prepare("DELETE FROM finanzas_cuadre_detalle WHERE cuadre_id = ?")->execute([$cuadre_id]);
            } else {
                // Insertar nueva cabecera
                $stmt = $db->prepare("
                    INSERT INTO finanzas_cuadre_caja (
                        codigo, titulo, area, tienda, encargado,
                        fecha_inicio, fecha_fin,
                        autorizacion_responsable, fecha_aut_responsable,
                        pagar_a, fecha_pagar_a,
                        autorizacion_direccion, fecha_aut_direccion,
                        saldo_anterior, total_ingreso,
                        total_salida_produccion, total_salida_melamine,
                        total_salida_servicio, total_salida_combustible,
                        total_salida_movilidad, total_salida_otros,
                        total_egreso, saldo_final,
                        observacion, estado
                    ) VALUES (
                        ?, ?, ?, ?, ?,
                        ?, ?,
                        ?, ?,
                        ?, ?,
                        ?, ?,
                        ?, ?,
                        ?, ?,
                        ?, ?,
                        ?, ?,
                        ?, ?,
                        ?, ?
                    ) RETURNING id
                ");
                $stmt->execute([
                    $codigo, $codigo, $area, $tienda, $encargado,
                    $fecha_inicio, $fecha_fin,
                    $aut_responsable, $f_aut_responsable,
                    $pagar_a, $f_pagar_a,
                    $aut_direccion, $f_aut_direccion,
                    $saldo_anterior, $total_ingreso,
                    $tot_produccion, $tot_melamine,
                    $tot_servicio, $tot_combustible,
                    $tot_movilidad, $tot_otros,
                    $total_egreso, $saldo_final,
                    $observacion, $estado
                ]);
                $cuadre_id = $stmt->fetchColumn();
            }
            
            // Insertar entradas y salidas
            $stmtDet = $db->prepare("
                INSERT INTO finanzas_cuadre_detalle (cuadre_id, fecha, tipo, categoria, detalle, descripcion, nro_justificante, monto)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($entradas_to_insert as $item) {
                $stmtDet->execute([
                    $cuadre_id, $item['fecha'], $item['tipo'], $item['categoria'],
                    $item['descripcion'], $item['descripcion'], $item['nro_justificante'], $item['monto']
                ]);
            }
            
            foreach ($salidas_to_insert as $item) {
                $stmtDet->execute([
                    $cuadre_id, $item['fecha'], $item['tipo'], $item['categoria'],
                    $item['descripcion'], $item['descripcion'], $item['nro_justificante'], $item['monto']
                ]);
            }
            
            $db->commit();
            header("Location: cuadre_caja.php?msg=guardado");
            exit;
            
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            die("Error al guardar cuadre de caja: " . $e->getMessage());
        }
    }
    
    if ($action === 'delete_cuadre') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $db->prepare("DELETE FROM finanzas_cuadre_detalle WHERE cuadre_id = ?")->execute([$id]);
            $db->prepare("DELETE FROM finanzas_cuadre_caja WHERE id = ?")->execute([$id]);
            header("Location: cuadre_caja.php?msg=eliminado");
            exit;
        }
    }
}

// Búsqueda y filtros
$filtro_anio = $_GET['anio'] ?? date('Y');
$filtro_buscar = trim($_GET['buscar'] ?? '');

$sql = "SELECT * FROM finanzas_cuadre_caja WHERE 1=1";
$params = [];

if (!empty($filtro_anio)) {
    $sql .= " AND (EXTRACT(YEAR FROM fecha_inicio) = ? OR fecha_inicio IS NULL)";
    $params[] = $filtro_anio;
}

if (!empty($filtro_buscar)) {
    $sql .= " AND (codigo ILIKE ? OR encargado ILIKE ? OR area ILIKE ? OR observacion ILIKE ?)";
    $term = "%$filtro_buscar%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$sql .= " ORDER BY fecha_inicio DESC, id DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$cuadres = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Totales de KPIs
$totalRecaudadoGeneral = 0;
$totalEgresosGeneral = 0;
$totalSaldoNetoGeneral = 0;
foreach($cuadres as $c) {
    $totalRecaudadoGeneral += floatval($c['total_ingreso']);
    $totalEgresosGeneral += floatval($c['total_egreso']);
    $totalSaldoNetoGeneral += floatval($c['saldo_final']);
}

// Obtener datos del último saldo registrado
$stmtLast = $db->query("SELECT saldo_final, codigo, fecha_fin FROM finanzas_cuadre_caja ORDER BY id DESC LIMIT 1");
$ultimoCierre = $stmtLast->fetch(PDO::FETCH_ASSOC);
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
        /* ===== ESTILOS CUADRE DE CAJA - PALETA CARPICENTER ===== */
        .caja-hero-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .caja-hero-title h1 {
            font-size: 1.45rem;
            font-weight: 800;
            color: #1E293B;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.65rem;
        }
        .caja-hero-title p {
            color: #64748B;
            font-size: 0.85rem;
            margin: 0.25rem 0 0 0;
        }

        /* KPI Cards */
        .caja-kpis-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 1.25rem;
            margin-bottom: 1.5rem;
        }
        .caja-kpi-card {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 14px;
            padding: 1.2rem 1.35rem;
            display: flex;
            align-items: center;
            gap: 1.1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
            transition: all 0.2s ease;
        }
        .caja-kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 18px rgba(0,0,0,0.05);
        }
        .caja-kpi-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
        }
        .icon-green-bg { background: #ECFDF5; color: #059669; }
        .icon-red-bg { background: #FEF2F2; color: #C62828; }
        .icon-blue-bg { background: #EFF6FF; color: #1565C0; }
        .icon-amber-bg { background: #FFFBEB; color: #D97706; }

        .caja-kpi-info span.label {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
            color: #64748B;
            display: block;
            margin-bottom: 0.2rem;
        }
        .caja-kpi-info h3 {
            font-size: 1.35rem;
            font-weight: 800;
            color: #1E293B;
            margin: 0;
            line-height: 1.2;
        }
        .caja-kpi-info span.sub-badge {
            font-size: 0.72rem;
            font-weight: 600;
            display: inline-block;
            margin-top: 0.3rem;
            padding: 2px 8px;
            border-radius: 20px;
        }

        /* Filter bar */
        .filter-panel {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 12px;
            padding: 0.9rem 1.2rem;
            margin-bottom: 1.5rem;
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
            justify-content: space-between;
        }
        .filter-form {
            display: flex;
            gap: 0.75rem;
            align-items: center;
            flex-wrap: wrap;
            flex: 1;
        }

        /* Tabla de Historial */
        .card-table-wrapper {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        .table-custom {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }
        .table-custom th {
            background: #F8FAFC;
            padding: 0.9rem 1.1rem;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #475569;
            border-bottom: 1px solid #E2E8F0;
        }
        .table-custom td {
            padding: 0.9rem 1.1rem;
            font-size: 0.85rem;
            color: #1E293B;
            border-bottom: 1px solid #F1F5F9;
            vertical-align: middle;
        }
        .table-custom tbody tr:hover {
            background: #F8FAFC;
        }

        .action-btn-group {
            display: flex;
            gap: 0.4rem;
            align-items: center;
        }
        .btn-act {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: 1px solid #E2E8F0;
            background: #FFFFFF;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #475569;
            font-size: 0.82rem;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.15s ease;
        }
        .btn-act:hover { background: #1E293B; color: #FFFFFF; border-color: #1E293B; }
        .btn-act.print:hover { background: #C62828; color: #FFFFFF; border-color: #C62828; }
        .btn-act.excel:hover { background: #2E7D32; color: #FFFFFF; border-color: #2E7D32; }
        .btn-act.delete:hover { background: #DC2626; color: #FFFFFF; border-color: #DC2626; }

        /* ==========================================================================
           MODAL REDISEÑADO ELEGANTE (TEMA CLARO LIMPIO Y ARMONIOSO)
           ========================================================================== */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            z-index: 99999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .modal-overlay.open {
            display: flex;
        }
        
        .modal-container-clean {
            background: #FFFFFF;
            border-radius: 16px;
            width: 100%;
            max-width: 1140px;
            height: 90vh;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            border: 1px solid #E2E8F0;
            animation: modalPop 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes modalPop {
            from { opacity: 0; transform: translateY(12px) scale(0.99); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .modal-header-clean {
            background: #FFFFFF;
            color: #1E293B;
            padding: 1.15rem 1.6rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
            border-bottom: 1px solid #E2E8F0;
        }
        .modal-header-clean h2 {
            margin: 0;
            font-size: 1.15rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #1E293B;
        }
        .modal-close-clean {
            background: #F1F5F9;
            border: 1px solid #E2E8F0;
            color: #64748B;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .modal-close-clean:hover {
            background: #C62828;
            color: #FFFFFF;
            border-color: #C62828;
        }

        .modal-form-flex {
            display: flex;
            flex-direction: column;
            flex: 1 1 auto;
            min-height: 0;
            height: 100%;
            overflow: hidden;
        }
        .modal-body-content {
            flex: 1 1 auto;
            overflow-y: auto !important;
            overflow-x: hidden;
            padding: 1.5rem 1.8rem;
            background: #F8FAFC;
            overscroll-behavior: contain;
        }
        .modal-body-content::-webkit-scrollbar {
            width: 7px;
        }
        .modal-body-content::-webkit-scrollbar-track {
            background: #F1F5F9;
        }
        .modal-body-content::-webkit-scrollbar-thumb {
            background: #CBD5E1;
            border-radius: 4px;
        }
        .modal-body-content::-webkit-scrollbar-thumb:hover {
            background: #94A3B8;
        }

        .modal-footer-clean {
            background: #FFFFFF;
            border-top: 1px solid #E2E8F0;
            padding: 1rem 1.6rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
        }

        /* Hide spin buttons on all number inputs */
        input[type=number]::-webkit-inner-spin-button, 
        input[type=number]::-webkit-outer-spin-button { 
            -webkit-appearance: none; 
            margin: 0; 
        }
        input[type=number] {
            -moz-appearance: textfield;
        }

        /* Secciones Limpias */
        .section-box {
            background: #FFFFFF;
            border-radius: 12px;
            border: 1px solid #E2E8F0;
            padding: 1.25rem 1.35rem;
            margin-bottom: 1.25rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
        }
        .section-box-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.6rem;
            border-bottom: 1px solid #F1F5F9;
        }
        .section-box-title {
            font-size: 0.88rem;
            font-weight: 800;
            color: #1E293B;
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0;
        }

        /* Inputs estilizados */
        .form-ctrl-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .form-ctrl-label {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #64748B;
        }
        .form-ctrl-input {
            width: 100%;
            padding: 0.52rem 0.75rem;
            border-radius: 8px;
            border: 1px solid #D1D5DB;
            background: #FFFFFF;
            font-size: 0.86rem;
            color: #1E293B;
            outline: none;
            box-sizing: border-box;
            transition: all 0.15s ease;
        }
        .form-ctrl-input:focus {
            border-color: #C62828;
            box-shadow: 0 0 0 3px rgba(198, 40, 40, 0.1);
        }
        .form-ctrl-input.money {
            text-align: right;
            font-weight: 700;
        }

        /* Tarjetas de tiendas limpias */
        .tiendas-row-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 0.85rem;
        }
        .tienda-item-box {
            background: #FAFAFA;
            border: 1px solid #E5E7EB;
            border-radius: 10px;
            padding: 0.85rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            transition: all 0.15s ease;
        }
        .tienda-item-box:hover {
            background: #FFFFFF;
            border-color: #CBD5E1;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
        }
        .tienda-tag-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.75rem;
            font-weight: 800;
            color: #1E293B;
        }

        /* Tabla de egresos limpia con una sola cabecera */
        .egresos-table-clean {
            width: 100%;
            border-collapse: collapse;
        }
        .egresos-table-clean th {
            background: #F8FAFC;
            color: #475569;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            padding: 0.5rem 0.65rem;
            border-bottom: 1px solid #E2E8F0;
            text-align: left;
        }
        .egresos-table-clean td {
            padding: 0.4rem 0.5rem;
            border-bottom: 1px solid #F1F5F9;
            vertical-align: middle;
        }
        .egresos-table-clean tbody tr:hover td {
            background: #FAFAFA;
        }

        .chip-btn {
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            padding: 0.35rem 0.75rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            color: #475569;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.15s ease;
        }
        .chip-btn:hover {
            background: #F1F5F9;
            color: #1E293B;
            border-color: #CBD5E1;
        }
        .chip-btn.blue { color: #1D4ED8; border-color: #BFDBFE; background: #EFF6FF; }
        .chip-btn.blue:hover { background: #DBEAFE; }
        .chip-btn.green { color: #15803D; border-color: #BBF7D0; background: #F0FDF4; }
        .chip-btn.green:hover { background: #DCFCE7; }

        /* Panel de Liquidación en tono claro elegante */
        .liquidation-panel-light {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 12px;
            padding: 1rem 1.4rem;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.2rem;
            align-items: center;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
        }
        .liq-stat {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .liq-stat span {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
            color: #64748B;
        }
        .liq-stat strong {
            font-size: 1.35rem;
            font-weight: 800;
            line-height: 1.2;
        }
    </style>
</head>
<body>
    <div class="app-wrapper">
        <?php include __DIR__ . '/../../views/partials/sidebar.php'; ?>
        <div class="main-content">
            <?php include __DIR__ . '/../../views/partials/header.php'; ?>
            <div class="page-content">

                <!-- Header de la Página -->
                <div class="caja-hero-bar">
                    <div class="caja-hero-title">
                        <h1><i class="fas fa-cash-register" style="color:#C62828;"></i> Cuadre de Caja Tiendas</h1>
                        <p><?= $page_subtitle ?></p>
                    </div>
                    <button class="btn btn-primary" onclick="abrirModalNuevo()" style="font-weight:700; padding:0.6rem 1.3rem; border-radius:10px; box-shadow:0 4px 12px rgba(198,40,40,0.2); display:inline-flex; align-items:center; gap:8px;">
                        <i class="fas fa-plus"></i> Registrar Cuadre de Caja
                    </button>
                </div>

                <!-- Alertas de éxito -->
                <?php if (isset($_GET['msg'])): ?>
                    <div style="background:#10B981; color:#fff; padding:0.85rem 1.25rem; border-radius:10px; margin-bottom:1.3rem; display:flex; justify-content:space-between; align-items:center; font-weight:600; box-shadow:0 2px 6px rgba(16,185,129,0.15);">
                        <div>
                            <i class="fas fa-check-circle" style="margin-right:8px;"></i>
                            <?= $_GET['msg'] === 'eliminado' ? 'Cuadre de caja eliminado correctamente.' : 'Cuadre de caja guardado con éxito.' ?>
                        </div>
                        <button type="button" onclick="this.parentElement.remove()" style="background:none; border:none; color:#fff; font-size:1.2rem; cursor:pointer;">&times;</button>
                    </div>
                <?php endif; ?>

                <!-- KPIs Cards -->
                <div class="caja-kpis-grid">
                    <div class="caja-kpi-card">
                        <div class="caja-kpi-icon icon-green-bg">
                            <i class="fas fa-hand-holding-dollar"></i>
                        </div>
                        <div class="caja-kpi-info">
                            <span class="label">Total Recaudado</span>
                            <h3 style="color:#059669;"><?= formatearMonto($totalRecaudadoGeneral) ?></h3>
                            <span class="sub-badge" style="background:#ECFDF5; color:#059669;">
                                Entradas + Recojos
                            </span>
                        </div>
                    </div>

                    <div class="caja-kpi-card">
                        <div class="caja-kpi-icon icon-red-bg">
                            <i class="fas fa-receipt"></i>
                        </div>
                        <div class="caja-kpi-info">
                            <span class="label">Total Egresos / Gastos</span>
                            <h3 style="color:#C62828;"><?= formatearMonto($totalEgresosGeneral) ?></h3>
                            <span class="sub-badge" style="background:#FEF2F2; color:#C62828;">
                                Gastos Operativos
                            </span>
                        </div>
                    </div>

                    <div class="caja-kpi-card">
                        <div class="caja-kpi-icon icon-blue-bg">
                            <i class="fas fa-vault"></i>
                        </div>
                        <div class="caja-kpi-info">
                            <span class="label">Saldo Neto Custodia</span>
                            <h3 style="color:#1565C0;"><?= formatearMonto($totalSaldoNetoGeneral) ?></h3>
                            <span class="sub-badge" style="background:#EFF6FF; color:#1565C0;">
                                Remanente
                            </span>
                        </div>
                    </div>

                    <div class="caja-kpi-card">
                        <div class="caja-kpi-icon icon-amber-bg">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="caja-kpi-info">
                            <span class="label">Cuadres Registrados</span>
                            <h3><?= count($cuadres) ?></h3>
                            <span class="sub-badge" style="background:#FFFBEB; color:#D97706;">
                                Período <?= htmlspecialchars($filtro_anio) ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Panel de Filtros -->
                <div class="filter-panel">
                    <form method="GET" class="filter-form">
                        <div style="display:flex; align-items:center; gap:6px;">
                            <label style="font-size:0.75rem; font-weight:700; color:#64748B;">AÑO:</label>
                            <select name="anio" onchange="this.form.submit()" class="form-ctrl-input" style="width:95px; padding:0.45rem;">
                                <?php for($y = date('Y'); $y >= 2024; $y--): ?>
                                    <option value="<?= $y ?>" <?= $filtro_anio == $y ? 'selected' : '' ?>><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div style="flex:1; min-width:200px;">
                            <input type="text" name="buscar" value="<?= htmlspecialchars($filtro_buscar) ?>" placeholder="Buscar por código, encargado, área u observación..." class="form-ctrl-input">
                        </div>
                        <button type="submit" class="btn btn-outline" style="padding:0.45rem 1rem; font-size:0.82rem;">
                            <i class="fas fa-search"></i> Filtrar
                        </button>
                        <?php if(!empty($filtro_buscar)): ?>
                            <a href="cuadre_caja.php" class="btn btn-outline" style="padding:0.45rem 0.8rem; font-size:0.82rem; color:#64748B;">Limpiar</a>
                        <?php endif; ?>
                    </form>

                    <?php if(!empty($ultimoCierre)): ?>
                        <div style="font-size:0.8rem; color:#475569; background:#F8FAFC; padding:5px 12px; border-radius:8px; border:1px dashed #CBD5E1;">
                            Último Arqueo: <strong><?= htmlspecialchars($ultimoCierre['codigo'] ?? '') ?></strong> (Saldo: <span style="color:#059669; font-weight:700;"><?= formatearMonto($ultimoCierre['saldo_final']) ?></span>)
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Listado Principal de Cuadres -->
                <div class="card-table-wrapper">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>Código / Turno</th>
                                <th>Período</th>
                                <th>Encargado</th>
                                <th>Área / Tienda</th>
                                <th style="text-align:right;">Recaudado</th>
                                <th style="text-align:right;">Egresos</th>
                                <th style="text-align:right;">Saldo Final</th>
                                <th style="text-align:center;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($cuadres)): ?>
                                <tr>
                                    <td colspan="8" style="text-align:center; padding:3.5rem; color:#94A3B8;">
                                        <i class="fas fa-cash-register" style="font-size:2.5rem; margin-bottom:0.8rem; opacity:0.35; display:block;"></i>
                                        No se encontraron registros de cuadre de caja para el filtro seleccionado.
                                    </td>
                                </tr>
                            <?php else: foreach($cuadres as $c): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight:800; color:#1E293B;">
                                            <i class="fas fa-file-invoice-dollar" style="color:#C62828; margin-right:4px;"></i>
                                            <?= htmlspecialchars($c['codigo'] ?? ('CC-'.$c['id'])) ?>
                                        </div>
                                        <span style="font-size:0.72rem; color:#64748B;">
                                            Resp: <?= htmlspecialchars($c['autorizacion_responsable'] ?? 'CARPICENTER') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="font-weight:600;">
                                            <?= $c['fecha_inicio'] ? date('d/m/Y', strtotime($c['fecha_inicio'])) : '—' ?>
                                            <?php if(!empty($c['fecha_fin']) && $c['fecha_fin'] !== $c['fecha_inicio']): ?>
                                                <span style="color:#64748B;">al</span> <?= date('d/m/Y', strtotime($c['fecha_fin'])) ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span style="background:#F1F5F9; color:#334155; font-size:0.75rem; font-weight:700; padding:3px 8px; border-radius:6px; display:inline-flex; align-items:center; gap:5px;">
                                            <i class="fas fa-user-tie"></i> <?= htmlspecialchars($c['encargado'] ?? 'NAOMI') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="font-size:0.82rem; font-weight:600; color:#334155;"><?= htmlspecialchars($c['area'] ?? 'ADMINISTRATIVO') ?></div>
                                        <div style="font-size:0.72rem; color:#94A3B8;"><?= htmlspecialchars($c['tienda'] ?? 'GENERAL') ?></div>
                                    </td>
                                    <td style="text-align:right; font-weight:700; color:#059669;">
                                        <?= formatearMonto($c['total_ingreso']) ?>
                                    </td>
                                    <td style="text-align:right; font-weight:700; color:#C62828;">
                                        <?= formatearMonto($c['total_egreso']) ?>
                                    </td>
                                    <td style="text-align:right;">
                                        <span style="font-weight:800; font-size:0.92rem; color: <?= floatval($c['saldo_final']) >= 0 ? '#059669' : '#C62828' ?>;">
                                            <?= formatearMonto($c['saldo_final']) ?>
                                        </span>
                                    </td>
                                    <td style="text-align:center;">
                                        <div class="action-btn-group" style="justify-content:center;">
                                            <a href="cuadre_caja_print.php?id=<?= $c['id'] ?>" target="_blank" class="btn-act print" title="Imprimir Formato Oficial (Carpicenter)">
                                                <i class="fas fa-print"></i>
                                            </a>
                                            <a href="export_cuadre_excel.php?id=<?= $c['id'] ?>" class="btn-act excel" title="Descargar Excel">
                                                <i class="fas fa-file-excel"></i>
                                            </a>
                                            <button type="button" class="btn-act" onclick="editarCuadre(<?= $c['id'] ?>)" title="Editar Cuadre">
                                                <i class="fas fa-pen-to-square"></i>
                                            </button>
                                            <form method="POST" onsubmit="return confirm('¿Está seguro de eliminar este cuadre de caja? Esta acción no se puede deshacer.');" style="margin:0;">
                                                <input type="hidden" name="action" value="delete_cuadre">
                                                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                                <button type="submit" class="btn-act delete" title="Eliminar">
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

    <!-- =========================================================================
         MODAL ELEGANTE REDISEÑADO (PALETA CLARA Y ARMONIOSA)
         ========================================================================= -->
    <div class="modal-overlay" id="modalCuadre">
        <div class="modal-container-clean">
            
            <!-- Header Blanco y Limpio -->
            <div class="modal-header-clean">
                <h2>
                    <i class="fas fa-cash-register" style="color:#C62828;"></i>
                    <span id="modalTitulo">Registrar Cuadre de Caja Tiendas</span>
                </h2>
                <button type="button" class="modal-close-clean" onclick="cerrarModal('modalCuadre')">&times;</button>
            </div>

            <!-- Formulario Wrapper -->
            <form method="POST" id="formCuadre" class="modal-form-flex">
                <input type="hidden" name="action" value="save_cuadre">
                <input type="hidden" name="id" id="cuadre_id" value="0">

                <!-- Cuerpo con Scroll Suave -->
                <div class="modal-body-content" id="modalScrollBody">

                    <!-- SECCIÓN 1: DATOS GENERALES -->
                    <div class="section-box">
                        <div class="section-box-header">
                            <h3 class="section-box-title">
                                <i class="fas fa-calendar-check" style="color:#1565C0;"></i> 1. Control del Período
                            </h3>
                            <span style="font-size:0.75rem; font-weight:700; color:#64748B;">
                                Semanal (Dom-Sáb) o Diario
                            </span>
                        </div>

                        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:0.85rem;">
                            <div class="form-ctrl-group">
                                <label class="form-ctrl-label">Código / N° Arqueo</label>
                                <input type="text" name="codigo" id="form_codigo" class="form-ctrl-input" placeholder="Ej: CC-2026-001">
                            </div>
                            <div class="form-ctrl-group">
                                <label class="form-ctrl-label">Encargado(a)</label>
                                <input type="text" name="encargado" id="form_encargado" class="form-ctrl-input" value="NAOMI" required>
                            </div>
                            <div class="form-ctrl-group">
                                <label class="form-ctrl-label">Fecha Inicio</label>
                                <input type="date" name="fecha_inicio" id="form_fecha_inicio" class="form-ctrl-input" value="<?= date('Y-m-d') ?>" required onchange="sincronizarFechaInicio(this.value)">
                            </div>
                            <div class="form-ctrl-group">
                                <label class="form-ctrl-label">Fecha Final</label>
                                <input type="date" name="fecha_fin" id="form_fecha_fin" class="form-ctrl-input" value="<?= date('Y-m-d') ?>">
                            </div>
                        </div>

                        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:0.85rem; margin-top:0.85rem; padding-top:0.85rem; border-top:1px dashed #E2E8F0;">
                            <div class="form-ctrl-group">
                                <label class="form-ctrl-label">Área</label>
                                <input type="text" name="area" id="form_area" class="form-ctrl-input" value="ADMINISTRATIVO">
                            </div>
                            <div class="form-ctrl-group">
                                <label class="form-ctrl-label">Autorización Responsable</label>
                                <input type="text" name="autorizacion_responsable" id="form_aut_responsable" class="form-ctrl-input" value="CARPICENTER">
                            </div>
                            <div class="form-ctrl-group">
                                <label class="form-ctrl-label">Pagar a:</label>
                                <input type="text" name="pagar_a" id="form_pagar_a" class="form-ctrl-input" placeholder="Ej: RUSBEL / GERENCIA">
                            </div>
                            <div class="form-ctrl-group">
                                <label class="form-ctrl-label">Autorización Dirección</label>
                                <input type="text" name="autorizacion_direccion" id="form_aut_direccion" class="form-ctrl-input" placeholder="Ej: AUTORIZADO">
                            </div>
                        </div>
                    </div>

                    <!-- SECCIÓN 2: RECOJO EN TIENDAS (ENTRADAS) -->
                    <div class="section-box" style="border-left: 4px solid #10B981;">
                        <div class="section-box-header">
                            <h3 class="section-box-title" style="color:#059669;">
                                <i class="fas fa-hand-holding-dollar"></i> 2. Recojo de Efectivo en Tiendas (Entradas)
                            </h3>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <button type="button" class="chip-btn green" onclick="jalarUltimoSaldo()">
                                    <i class="fas fa-clock-rotate-left"></i> Traer Saldo Anterior
                                </button>
                                <span style="font-weight:800; font-size:0.92rem; color:#059669; background:#ECFDF5; padding:3px 10px; border-radius:6px;" id="badgeSubtotalEntradas">
                                    Total: S/ 0.00
                                </span>
                            </div>
                        </div>

                        <!-- Grid de Tiendas -->
                        <div class="tiendas-row-grid" id="tiendasGrid">
                            
                            <!-- Saldo Anterior -->
                            <div class="tienda-item-box" style="border-color:#A7F3D0; background:#F0FDF4;">
                                <div class="tienda-tag-header" style="color:#059669;">
                                    <span><i class="fas fa-vault"></i> SALDO ANTERIOR</span>
                                    <input type="hidden" name="entrada_categoria[]" value="SALDO_ANTERIOR">
                                    <input type="hidden" name="entrada_descripcion[]" value="SALDO ANTERIOR">
                                    <input type="hidden" name="entrada_justificante[]" value="(N/A)">
                                    <input type="hidden" name="entrada_fecha[]" id="fecha_saldo_ant" value="<?= date('Y-m-d') ?>">
                                </div>
                                <div class="form-ctrl-group">
                                    <label class="form-ctrl-label" style="color:#047857;">Monto Saldo Anterior</label>
                                    <input type="number" step="0.01" name="entrada_monto[]" id="monto_saldo_ant" class="form-ctrl-input money inp-entrada" placeholder="0.00" oninput="recalcularTotales()" style="color:#059669; font-size:1.05rem;">
                                </div>
                            </div>

                            <!-- Tienda 1 -->
                            <div class="tienda-item-box">
                                <div class="tienda-tag-header">
                                    <span><i class="fas fa-store" style="color:#1565C0;"></i> TIENDA 1</span>
                                    <input type="hidden" name="entrada_categoria[]" value="TIENDA_1">
                                    <input type="hidden" name="entrada_descripcion[]" value="TIENDA 1">
                                    <input type="hidden" name="entrada_fecha[]" class="fecha-tienda" value="<?= date('Y-m-d') ?>">
                                </div>
                                <div class="form-ctrl-group">
                                    <input type="text" name="entrada_justificante[]" id="just_t1" class="form-ctrl-input" placeholder="N° Tickets (ej: 1824-1825)">
                                </div>
                                <div class="form-ctrl-group">
                                    <input type="number" step="0.01" name="entrada_monto[]" id="monto_t1" class="form-ctrl-input money inp-entrada" placeholder="Monto S/" oninput="recalcularTotales()" style="color:#059669;">
                                </div>
                            </div>

                            <!-- Tienda 2 -->
                            <div class="tienda-item-box">
                                <div class="tienda-tag-header">
                                    <span><i class="fas fa-store" style="color:#1565C0;"></i> TIENDA 2</span>
                                    <input type="hidden" name="entrada_categoria[]" value="TIENDA_2">
                                    <input type="hidden" name="entrada_descripcion[]" value="TIENDA 2">
                                    <input type="hidden" name="entrada_fecha[]" class="fecha-tienda" value="<?= date('Y-m-d') ?>">
                                </div>
                                <div class="form-ctrl-group">
                                    <input type="text" name="entrada_justificante[]" id="just_t2" class="form-ctrl-input" placeholder="N° Tickets (ej: 1921-1922)">
                                </div>
                                <div class="form-ctrl-group">
                                    <input type="number" step="0.01" name="entrada_monto[]" id="monto_t2" class="form-ctrl-input money inp-entrada" placeholder="Monto S/" oninput="recalcularTotales()" style="color:#059669;">
                                </div>
                            </div>

                            <!-- Tienda 3 -->
                            <div class="tienda-item-box">
                                <div class="tienda-tag-header">
                                    <span><i class="fas fa-store" style="color:#1565C0;"></i> TIENDA 3</span>
                                    <input type="hidden" name="entrada_categoria[]" value="TIENDA_3">
                                    <input type="hidden" name="entrada_descripcion[]" value="TIENDA 3">
                                    <input type="hidden" name="entrada_fecha[]" class="fecha-tienda" value="<?= date('Y-m-d') ?>">
                                </div>
                                <div class="form-ctrl-group">
                                    <input type="text" name="entrada_justificante[]" id="just_t3" class="form-ctrl-input" placeholder="N° Tickets (ej: 1399-1400)">
                                </div>
                                <div class="form-ctrl-group">
                                    <input type="number" step="0.01" name="entrada_monto[]" id="monto_t3" class="form-ctrl-input money inp-entrada" placeholder="Monto S/" oninput="recalcularTotales()" style="color:#059669;">
                                </div>
                            </div>

                            <!-- Tienda 4 -->
                            <div class="tienda-item-box">
                                <div class="tienda-tag-header">
                                    <span><i class="fas fa-store" style="color:#1565C0;"></i> TIENDA 4</span>
                                    <input type="hidden" name="entrada_categoria[]" value="TIENDA_4">
                                    <input type="hidden" name="entrada_descripcion[]" value="TIENDA 4">
                                    <input type="hidden" name="entrada_fecha[]" class="fecha-tienda" value="<?= date('Y-m-d') ?>">
                                </div>
                                <div class="form-ctrl-group">
                                    <input type="text" name="entrada_justificante[]" id="just_t4" class="form-ctrl-input" placeholder="N° Tickets T4">
                                </div>
                                <div class="form-ctrl-group">
                                    <input type="number" step="0.01" name="entrada_monto[]" id="monto_t4" class="form-ctrl-input money inp-entrada" placeholder="Monto S/" oninput="recalcularTotales()" style="color:#059669;">
                                </div>
                            </div>

                        </div>

                        <!-- Ingresos extra -->
                        <div id="entradasExtraContainer" style="margin-top:0.8rem; display:flex; flex-direction:column; gap:0.5rem;"></div>

                        <div style="margin-top:0.8rem;">
                            <button type="button" onclick="agregarEntradaExtra('', '', '', 0)" class="chip-btn">
                                <i class="fas fa-plus"></i> + Agregar Ingreso / Tienda Extra
                            </button>
                        </div>
                    </div>

                    <!-- SECCIÓN 3: EGRESOS Y GASTOS (SALIDAS) -->
                    <div class="section-box" style="border-left: 4px solid #C62828;">
                        <div class="section-box-header">
                            <h3 class="section-box-title" style="color:#C62828;">
                                <i class="fas fa-arrow-up-right-dots"></i> 3. Salidas / Egresos Inmediatos
                            </h3>
                            <span style="font-weight:800; font-size:0.92rem; color:#C62828; background:#FEF2F2; padding:3px 10px; border-radius:6px;" id="badgeSubtotalSalidas">
                                Total Gastos: S/ 0.00
                            </span>
                        </div>

                        <!-- Botones Rápidos -->
                        <div style="display:flex; flex-wrap:wrap; gap:0.45rem; margin-bottom:0.9rem;">
                            <span style="font-size:0.72rem; font-weight:700; color:#64748B; align-self:center; margin-right:4px;">ACCESO RÁPIDO:</span>
                            <button type="button" class="chip-btn blue" onclick="agregarFilaEgreso('', 'OTROS', 'POR DEPOSITO A CUENTA CARPI BCP', '', '')">
                                <i class="fas fa-building-columns"></i> + Depósito BCP
                            </button>
                            <button type="button" class="chip-btn green" onclick="agregarFilaEgreso('', 'OTROS', 'POR DEPOSITO A CUENTA CARPI IBK', '', '')">
                                <i class="fas fa-building-columns"></i> + Depósito Interbank
                            </button>
                            <button type="button" class="chip-btn" onclick="agregarFilaEgreso('', 'COMBUSTIBLE', 'COMBUSTIBLE', '', '')">
                                <i class="fas fa-gas-pump"></i> + Combustible
                            </button>
                            <button type="button" class="chip-btn" onclick="agregarFilaEgreso('', 'MOVILIDAD', 'MOVILIDAD / PASAJES', '', '')">
                                <i class="fas fa-motorcycle"></i> + Movilidad
                            </button>
                            <button type="button" class="chip-btn" onclick="agregarFilaEgreso('', 'MELAMINE', 'COMPRA DE MELAMINE', '', '')">
                                <i class="fas fa-layer-group"></i> + Melamine
                            </button>
                            <button type="button" class="chip-btn" onclick="agregarFilaEgreso('', 'PRODUCCION', 'GASTO DE PRODUCCIÓN', '', '')">
                                <i class="fas fa-hammer"></i> + Producción
                            </button>
                            <button type="button" class="chip-btn" onclick="agregarFilaEgreso('', 'OTROS', '', '', '')" style="color:#C62828; border-color:#FECACA;">
                                <i class="fas fa-plus"></i> + Fila en Blanco
                            </button>
                        </div>

                        <!-- Tabla limpia de egresos con 1 sola cabecera -->
                        <div style="border: 1px solid #E2E8F0; border-radius: 8px; overflow: hidden; background: #FFFFFF;">
                            <table class="egresos-table-clean">
                                <thead>
                                    <tr>
                                        <th style="width: 130px;">Fecha</th>
                                        <th style="width: 170px;">Categoría</th>
                                        <th>Descripción del Gasto / Salida</th>
                                        <th style="width: 170px;">N° Justificante / OP</th>
                                        <th style="width: 130px; text-align:right;">Monto S/</th>
                                        <th style="width: 36px; text-align:center;"></th>
                                    </tr>
                                </thead>
                                <tbody id="egresosTableBody">
                                    <!-- Filas generadas dinámicamente -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- SECCIÓN 4: OBSERVACIONES -->
                    <div class="section-box" style="margin-bottom:1.25rem;">
                        <div class="section-box-header">
                            <h3 class="section-box-title" style="color:#475569;">
                                <i class="fas fa-clipboard-list"></i> 4. Observaciones y Notas
                            </h3>
                        </div>
                        <textarea name="observacion" id="form_observacion" rows="2" class="form-ctrl-input" placeholder="Comentarios sobre faltantes, sobrantes, cheques o detalles de entrega a gerencia..."></textarea>
                    </div>

                    <!-- PANEL DE RESUMEN FINAL EN TONO CLARO ARMONIOSO -->
                    <div class="liquidation-panel-light">
                        <div class="liq-stat">
                            <span><i class="fas fa-hand-holding-dollar" style="color:#059669;"></i> Total Recaudado (Entradas)</span>
                            <strong style="color:#059669;" id="dashEntradas">S/ 0.00</strong>
                        </div>
                        <div class="liq-stat">
                            <span><i class="fas fa-receipt" style="color:#C62828;"></i> Total Egresos (Gastos)</span>
                            <strong style="color:#C62828;" id="dashSalidas">S/ 0.00</strong>
                        </div>
                        <div class="liq-stat" style="border-left:1px solid #E2E8F0; padding-left:1.2rem;">
                            <span style="color:#1E293B;"><i class="fas fa-vault" style="color:#C62828;"></i> SALDO FINAL NETO</span>
                            <strong style="color:#1E293B; font-size:1.55rem;" id="dashSaldoFinal">S/ 0.00</strong>
                        </div>
                    </div>

                </div>

                <!-- Footer Fijo -->
                <div class="modal-footer-clean">
                    <button type="button" class="btn btn-outline" onclick="cerrarModal('modalCuadre')" style="padding:0.55rem 1.3rem; font-weight:600;">
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary" style="font-weight:700; padding:0.6rem 1.6rem; border-radius:10px; box-shadow:0 4px 12px rgba(198,40,40,0.2);">
                        <i class="fas fa-floppy-disk" style="margin-right:6px;"></i> Guardar Cuadre de Caja
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function abrirModal(id) { 
        document.getElementById(id).classList.add('open'); 
        document.getElementById('modalScrollBody').scrollTop = 0;
    }
    function cerrarModal(id) { 
        document.getElementById(id).classList.remove('open'); 
    }

    function abrirModalNuevo() {
        document.getElementById('modalTitulo').innerText = 'Registrar Cuadre de Caja Tiendas';
        document.getElementById('cuadre_id').value = '0';
        document.getElementById('form_codigo').value = '';
        document.getElementById('form_area').value = 'ADMINISTRATIVO';
        document.getElementById('form_encargado').value = 'NAOMI';
        document.getElementById('form_fecha_inicio').value = new Date().toISOString().split('T')[0];
        document.getElementById('form_fecha_fin').value = new Date().toISOString().split('T')[0];
        document.getElementById('form_aut_responsable').value = 'CARPICENTER';
        document.getElementById('form_pagar_a').value = '';
        document.getElementById('form_aut_direccion').value = '';
        document.getElementById('form_observacion').value = '';

        // Reset inputs de tiendas fijas
        document.getElementById('monto_saldo_ant').value = '';
        document.getElementById('just_t1').value = ''; document.getElementById('monto_t1').value = '';
        document.getElementById('just_t2').value = ''; document.getElementById('monto_t2').value = '';
        document.getElementById('just_t3').value = ''; document.getElementById('monto_t3').value = '';
        document.getElementById('just_t4').value = ''; document.getElementById('monto_t4').value = '';

        // Limpiar extras y egresos
        document.getElementById('entradasExtraContainer').innerHTML = '';
        document.getElementById('egresosTableBody').innerHTML = '';

        const hoy = document.getElementById('form_fecha_inicio').value;
        sincronizarFechaInicio(hoy);

        // Fila de gasto inicial común
        agregarFilaEgreso(hoy, 'OTROS', 'POR DEPOSITO A CUENTA CARPI BCP', '', '');

        recalcularTotales();
        abrirModal('modalCuadre');
    }

    function sincronizarFechaInicio(val) {
        document.getElementById('fecha_saldo_ant').value = val;
        document.querySelectorAll('.fecha-tienda').forEach(inp => inp.value = val);
    }

    function agregarEntradaExtra(fecha, desc, just, monto) {
        const container = document.getElementById('entradasExtraContainer');
        const div = document.createElement('div');
        div.className = 'tienda-item-box';
        div.style.background = '#FFFFFF';
        const f = fecha || document.getElementById('form_fecha_inicio').value;
        
        div.innerHTML = `
            <div class="tienda-tag-header" style="color:#B45309;">
                <span><i class="fas fa-coins"></i> INGRESO ADICIONAL</span>
                <button type="button" onclick="this.closest('.tienda-item-box').remove(); recalcularTotales();" style="border:none; background:none; color:#EF4444; font-size:1.1rem; cursor:pointer;">&times;</button>
                <input type="hidden" name="entrada_categoria[]" value="OTRO">
                <input type="hidden" name="entrada_fecha[]" value="${f}">
            </div>
            <div style="display:grid; grid-template-columns: 1fr 1fr 130px; gap:0.6rem;">
                <input type="text" name="entrada_descripcion[]" class="form-ctrl-input" value="${desc}" placeholder="Concepto / Tienda" required>
                <input type="text" name="entrada_justificante[]" class="form-ctrl-input" value="${just}" placeholder="N° Justificante">
                <input type="number" step="0.01" name="entrada_monto[]" class="form-ctrl-input money inp-entrada" value="${monto || ''}" placeholder="Monto S/" oninput="recalcularTotales()" style="color:#059669;">
            </div>
        `;
        container.appendChild(div);
    }

    function agregarFilaEgreso(fecha, cat, desc, just, monto) {
        const tbody = document.getElementById('egresosTableBody');
        const tr = document.createElement('tr');
        const f = fecha || document.getElementById('form_fecha_inicio').value || new Date().toISOString().split('T')[0];
        
        tr.innerHTML = `
            <td>
                <input type="date" name="egreso_fecha[]" class="form-ctrl-input" value="${f}" style="padding:0.4rem 0.5rem; font-size:0.82rem;">
            </td>
            <td>
                <select name="egreso_categoria[]" class="form-ctrl-input inp-egreso-cat" onchange="recalcularTotales()" style="font-weight:600; padding:0.4rem 0.5rem; font-size:0.82rem;">
                    <option value="PRODUCCION" ${cat === 'PRODUCCION' ? 'selected' : ''}>🔨 Producción</option>
                    <option value="MELAMINE" ${cat === 'MELAMINE' ? 'selected' : ''}>🪵 Melamine</option>
                    <option value="SERVICIO" ${cat === 'SERVICIO' ? 'selected' : ''}>⚙️ Servicio</option>
                    <option value="COMBUSTIBLE" ${cat === 'COMBUSTIBLE' ? 'selected' : ''}>⛽ Combustible</option>
                    <option value="MOVILIDAD" ${cat === 'MOVILIDAD' ? 'selected' : ''}>🛵 Movilidad</option>
                    <option value="OTROS" ${cat === 'OTROS' || !cat ? 'selected' : ''}>📦 Otros</option>
                </select>
            </td>
            <td>
                <input type="text" name="egreso_descripcion[]" class="form-ctrl-input" value="${desc}" placeholder="Concepto (ej: POR DEPOSITO A CUENTA CARPI BCP)" required style="padding:0.4rem 0.6rem; font-size:0.82rem;">
            </td>
            <td>
                <input type="text" name="egreso_justificante[]" class="form-ctrl-input" value="${just}" placeholder="N° OP / Factura" style="padding:0.4rem 0.6rem; font-size:0.82rem;">
            </td>
            <td>
                <input type="number" step="0.01" name="egreso_monto[]" class="form-ctrl-input money inp-egreso-monto" value="${monto || ''}" placeholder="0.00" oninput="recalcularTotales()" style="color:#C62828; padding:0.4rem 0.6rem; font-size:0.85rem;">
            </td>
            <td style="text-align:center;">
                <button type="button" onclick="this.closest('tr').remove(); recalcularTotales();" style="border:none; background:none; color:#94A3B8; font-size:1.1rem; cursor:pointer;" title="Eliminar fila" onmouseover="this.style.color='#C62828'" onmouseout="this.style.color='#94A3B8'">&times;</button>
            </td>
        `;
        tbody.appendChild(tr);
        recalcularTotales();
    }

    function recalcularTotales() {
        let totEntradas = 0;
        document.querySelectorAll('.inp-entrada').forEach(inp => {
            totEntradas += (parseFloat(inp.value) || 0);
        });

        let totSalidas = 0;
        document.querySelectorAll('.inp-egreso-monto').forEach(inp => {
            totSalidas += (parseFloat(inp.value) || 0);
        });

        const saldoFinal = totEntradas - totSalidas;
        const fmt = (n) => 'S/ ' + n.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        document.getElementById('badgeSubtotalEntradas').innerText = 'Total: ' + fmt(totEntradas);
        document.getElementById('badgeSubtotalSalidas').innerText = 'Total Gastos: ' + fmt(totSalidas);

        document.getElementById('dashEntradas').innerText = fmt(totEntradas);
        document.getElementById('dashSalidas').innerText = fmt(totSalidas);
        document.getElementById('dashSaldoFinal').innerText = fmt(saldoFinal);
    }

    function jalarUltimoSaldo() {
        fetch('cuadre_caja.php?ajax=get_last_saldo')
            .then(res => res.json())
            .then(data => {
                if (data.success && data.last) {
                    const monto = parseFloat(data.last.saldo_final) || 0;
                    document.getElementById('monto_saldo_ant').value = monto.toFixed(2);
                    recalcularTotales();
                    alert(`Saldo anterior importado: S/ ${monto.toFixed(2)} (Cierre: ${data.last.codigo})`);
                } else {
                    alert('No hay cierres anteriores registrados para obtener saldo.');
                }
            });
    }

    function editarCuadre(id) {
        fetch(`cuadre_caja.php?ajax=get_cuadre&id=${id}`)
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    alert('Error al cargar datos del cuadre.');
                    return;
                }
                const c = data.cuadre;
                const d = data.detalles;

                document.getElementById('modalTitulo').innerText = `Editar Cuadre de Caja — ${c.codigo || ('CC-'+c.id)}`;
                document.getElementById('cuadre_id').value = c.id;
                document.getElementById('form_codigo').value = c.codigo || '';
                document.getElementById('form_area').value = c.area || 'ADMINISTRATIVO';
                document.getElementById('form_encargado').value = c.encargado || 'NAOMI';
                document.getElementById('form_fecha_inicio').value = c.fecha_inicio || '';
                document.getElementById('form_fecha_fin').value = c.fecha_fin || '';
                document.getElementById('form_aut_responsable').value = c.autorizacion_responsable || 'CARPICENTER';
                document.getElementById('form_pagar_a').value = c.pagar_a || '';
                document.getElementById('form_aut_direccion').value = c.autorizacion_direccion || '';
                document.getElementById('form_observacion').value = c.observacion || '';

                // Reset
                document.getElementById('monto_saldo_ant').value = parseFloat(c.saldo_anterior || 0).toFixed(2);
                document.getElementById('just_t1').value = ''; document.getElementById('monto_t1').value = '';
                document.getElementById('just_t2').value = ''; document.getElementById('monto_t2').value = '';
                document.getElementById('just_t3').value = ''; document.getElementById('monto_t3').value = '';
                document.getElementById('just_t4').value = ''; document.getElementById('monto_t4').value = '';

                document.getElementById('entradasExtraContainer').innerHTML = '';
                document.getElementById('egresosTableBody').innerHTML = '';

                // Parsear detalles
                const entradas = d.filter(item => item.tipo === 'ENTRADA');
                const salidas = d.filter(item => item.tipo === 'SALIDA');

                entradas.forEach(ent => {
                    const descUpper = (ent.descripcion || ent.detalle || '').toUpperCase();
                    if (descUpper.includes('SALDO ANTERIOR') || ent.categoria === 'SALDO_ANTERIOR') {
                        document.getElementById('monto_saldo_ant').value = parseFloat(ent.monto).toFixed(2);
                    } else if (descUpper === 'TIENDA 1' || ent.categoria === 'TIENDA_1') {
                        document.getElementById('just_t1').value = ent.nro_justificante || '';
                        document.getElementById('monto_t1').value = parseFloat(ent.monto).toFixed(2);
                    } else if (descUpper === 'TIENDA 2' || ent.categoria === 'TIENDA_2') {
                        document.getElementById('just_t2').value = ent.nro_justificante || '';
                        document.getElementById('monto_t2').value = parseFloat(ent.monto).toFixed(2);
                    } else if (descUpper === 'TIENDA 3' || ent.categoria === 'TIENDA_3') {
                        document.getElementById('just_t3').value = ent.nro_justificante || '';
                        document.getElementById('monto_t3').value = parseFloat(ent.monto).toFixed(2);
                    } else if (descUpper === 'TIENDA 4' || ent.categoria === 'TIENDA_4') {
                        document.getElementById('just_t4').value = ent.nro_justificante || '';
                        document.getElementById('monto_t4').value = parseFloat(ent.monto).toFixed(2);
                    } else {
                        agregarEntradaExtra(ent.fecha, ent.descripcion || ent.detalle, ent.nro_justificante, ent.monto);
                    }
                });

                salidas.forEach(sal => {
                    agregarFilaEgreso(sal.fecha, sal.categoria, sal.descripcion || sal.detalle, sal.nro_justificante, sal.monto);
                });

                recalcularTotales();
                abrirModal('modalCuadre');
            });
    }
    </script>
</body>
</html>
