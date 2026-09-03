<?php
require_once __DIR__ . '/../auth/check_auth.php';
require_once __DIR__ . '/../config/db.php';

// ==========================================
// DETECCIÓN DE ROL Y ALCANCE DE DATOS
// ==========================================
$isSeller = in_array(strtolower($user_role ?? ''), ['vendedor', 'vendedora']);
$user_id_act = intval($_SESSION['user_id'] ?? 0);
$local_id_act = intval($_SESSION['local_id'] ?? 0);
$user_name_act = trim($_SESSION['nombre_completo'] ?? ($_SESSION['username'] ?? ''));

// Periodos: Mes y Quincena
$f_mes_ini = date('Y-m-01');
$f_mes_fin = date('Y-m-t');

$dia_actual = intval(date('d'));
$mes_actual = intval(date('m'));
$ano_actual = intval(date('Y'));
$mes_nombre = date('F');
$meses_es = [
    'January'=>'Enero','February'=>'Febrero','March'=>'Marzo','April'=>'Abril',
    'May'=>'Mayo','June'=>'Junio','July'=>'Julio','August'=>'Agosto',
    'September'=>'Septiembre','October'=>'Octubre','November'=>'Noviembre','December'=>'Diciembre'
];
$mes_es_nom = $meses_es[$mes_nombre] ?? $mes_nombre;

if ($dia_actual <= 15) {
    $quincena_num = 1;
    $fecha_ini_q = "$ano_actual-" . str_pad($mes_actual, 2, '0', STR_PAD_LEFT) . "-01";
    $fecha_fin_q = "$ano_actual-" . str_pad($mes_actual, 2, '0', STR_PAD_LEFT) . "-15";
    $quincena_label = "1ra Quincena (01 al 15 de $mes_es_nom $ano_actual)";
} else {
    $quincena_num = 2;
    $fecha_ini_q = "$ano_actual-" . str_pad($mes_actual, 2, '0', STR_PAD_LEFT) . "-16";
    $ultimo_dia_mes = date('t');
    $fecha_fin_q = "$ano_actual-" . str_pad($mes_actual, 2, '0', STR_PAD_LEFT) . "-$ultimo_dia_mes";
    $quincena_label = "2da Quincena (16 al $ultimo_dia_mes de $mes_es_nom $ano_actual)";
}

$meta_quincenal = 20000.00;

// Filtros estrictos para vendedora (Privacidad personal por usuario / nombre)
$where_user_ventas = "";
$where_user_notas = "";
$where_user_contratos = "";
$where_user_cotizaciones = "";
$params_seller = [];

if ($isSeller) {
    $where_user_ventas = " AND usuario_id = :uid";
    $where_user_notas = " AND (usuario_id = :uid OR vendedor ILIKE :vname OR vendedor ILIKE :vuser)";
    $where_user_contratos = " AND vendedor_id = :uid";
    $where_user_cotizaciones = " AND (vendedor_id = :uid OR usuario_id = :uid)";
    
    $params_seller[':uid'] = $user_id_act;
    $params_seller[':vname'] = "%$user_name_act%";
    $params_seller[':vuser'] = "%" . ($_SESSION['username'] ?? '') . "%";
}

// 1. Total Vendido en la Quincena Actual
$params_q = array_merge($params_seller, [':fini' => $fecha_ini_q, ':ffin' => $fecha_fin_q]);
$stmt_q = $db->prepare("
    SELECT (
        COALESCE((SELECT SUM(total) FROM ventas WHERE estado='Completada' AND COALESCE(fecha_emision, fecha) >= :fini AND COALESCE(fecha_emision, fecha) <= :ffin $where_user_ventas), 0) +
        COALESCE((SELECT SUM(total) FROM notas_venta WHERE estado='Activa' AND fecha >= :fini AND fecha <= :ffin $where_user_notas), 0) +
        COALESCE((SELECT SUM(monto_total) FROM contratos WHERE estado_contrato!='Anulado' AND fecha_emision >= :fini AND fecha_emision <= :ffin $where_user_contratos), 0)
    ) as total_quincena
");
$stmt_q->execute($params_q);
$total_vendido_quincena = floatval($stmt_q->fetchColumn() ?: 0);

$porcentaje_meta = ($meta_quincenal > 0) ? min(100, round(($total_vendido_quincena / $meta_quincenal) * 100, 1)) : 0;
$monto_faltante = max(0, $meta_quincenal - $total_vendido_quincena);
$meta_alcanzada = ($total_vendido_quincena >= $meta_quincenal);
$comision_ganada = $meta_alcanzada ? ($total_vendido_quincena * 0.01) : 0.00;

// 2. Ventas del Mes
$params_m = array_merge($params_seller, [':f_mini' => $f_mes_ini, ':f_mfin' => $f_mes_fin]);
$stmt_m = $db->prepare("
    SELECT (
        COALESCE((SELECT SUM(total) FROM ventas WHERE estado='Completada' AND COALESCE(fecha_emision, fecha) >= :f_mini AND COALESCE(fecha_emision, fecha) <= :f_mfin $where_user_ventas), 0) +
        COALESCE((SELECT SUM(total) FROM notas_venta WHERE estado='Activa' AND fecha >= :f_mini AND fecha <= :f_mfin $where_user_notas), 0) +
        COALESCE((SELECT SUM(monto_total) FROM contratos WHERE estado_contrato!='Anulado' AND fecha_emision >= :f_mini AND fecha_emision <= :f_mfin $where_user_contratos), 0)
    ) as total
");
$stmt_m->execute($params_m);
$ventas_mes = $stmt_m->fetch(PDO::FETCH_ASSOC);

// 3. KPI 4: Compras del Mes (para Admin) vs Mis Cotizaciones (para Vendedora)
if ($isSeller) {
    $stmtCotiCnt = $db->prepare("SELECT COUNT(*) FROM cotizaciones WHERE (vendedor_id = :uid OR usuario_id = :uid)");
    $stmtCotiCnt->execute([':uid' => $user_id_act]);
    $mis_cotizaciones_total = intval($stmtCotiCnt->fetchColumn() ?: 0);
} else {
    $compras_mes = $db->query("SELECT COALESCE(SUM(total),0) as total FROM compras WHERE EXTRACT(MONTH FROM fecha)=EXTRACT(MONTH FROM CURRENT_DATE) AND EXTRACT(YEAR FROM fecha)=EXTRACT(YEAR FROM CURRENT_DATE)")->fetch(PDO::FETCH_ASSOC);
}

// 4. Catálogo y Stock
$total_productos = $db->query("SELECT COUNT(*) as total FROM productos")->fetch();

$stock_bajo = $db->query("
    SELECT COUNT(*) as total FROM (
        SELECT p.id
        FROM productos p
        LEFT JOIN inventario_local il ON il.producto_id = p.id
        GROUP BY p.id, p.stock_minimo
        HAVING COALESCE(SUM(il.stock_actual), 0) <= COALESCE(p.stock_minimo, 5)
    ) sub
")->fetch();

// 5. Top 5 productos más vendidos
$top_productos = $db->query("
    SELECT p.nombre, 
           (COALESCE((SELECT SUM(cd.cantidad) FROM contrato_detalles cd JOIN contratos c ON cd.contrato_id=c.id WHERE cd.producto_id=p.id AND c.estado_contrato!='Anulado'), 0) +
            COALESCE((SELECT SUM(nvd.cantidad) FROM notas_venta_detalle nvd JOIN notas_venta nv ON nvd.nota_id=nv.id WHERE nvd.descripcion ILIKE '%' || p.nombre || '%' AND nv.estado='Activa'), 0)) as total_vendido
    FROM productos p
    ORDER BY total_vendido DESC LIMIT 5
")->fetchAll();

// 6. Stock bajo
$productos_bajo = $db->query("
    SELECT p.id, p.nombre, cat.nombre as categoria, col.nombre as color_nombre, 
           GREATEST(COALESCE(SUM(il.stock_actual - COALESCE(il.stock_reservado, 0)), 0), 0) as stock_disponible, 
           COALESCE(p.stock_minimo, 5) as stock_minimo
    FROM producto_colores pc
    JOIN productos p ON pc.producto_id = p.id
    JOIN colores col ON pc.color_id = col.id
    LEFT JOIN categorias cat ON p.categoria_id = cat.id
    LEFT JOIN inventario_local il ON il.producto_id = p.id AND il.color_id = col.id
    GROUP BY p.id, p.nombre, cat.nombre, col.nombre, p.stock_minimo
    HAVING GREATEST(COALESCE(SUM(il.stock_actual - COALESCE(il.stock_reservado, 0)), 0), 0) <= COALESCE(p.stock_minimo, 5)
    ORDER BY stock_disponible ASC, p.nombre ASC LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// 7. Últimas operaciones comerciales (Personalizadas estrictamente para Vendedora / Globales para Admin)
if ($isSeller) {
    $stmt_ult = $db->prepare("
        SELECT 'Contrato' as tipo, c.codigo_completo as comprobante, COALESCE(cli.nombre, 'Cliente General') as cliente, c.monto_total as total, c.fecha_emision as fecha, c.estado_contrato as estado 
        FROM contratos c 
        LEFT JOIN clientes cli ON c.cliente_id=cli.id 
        WHERE c.estado_contrato != 'Anulado' AND c.vendedor_id = :uid
        UNION ALL
        SELECT 'Nota Venta' as tipo, nv.numero as comprobante, nv.cliente_nombre as cliente, nv.total, nv.fecha, nv.estado
        FROM notas_venta nv
        WHERE nv.estado = 'Activa' AND (nv.usuario_id = :uid OR nv.vendedor ILIKE :vname OR nv.vendedor ILIKE :vuser)
        ORDER BY fecha DESC LIMIT 6
    ");
    $stmt_ult->execute([
        ':uid' => $user_id_act,
        ':vname' => "%$user_name_act%",
        ':vuser' => "%" . ($_SESSION['username'] ?? '') . "%"
    ]);
    $ultimas_ventas = $stmt_ult->fetchAll(PDO::FETCH_ASSOC);
} else {
    $ultimas_ventas = $db->query("
        SELECT 'Contrato' as tipo, c.codigo_completo as comprobante, COALESCE(cli.nombre, 'Cliente General') as cliente, c.monto_total as total, c.fecha_emision as fecha, c.estado_contrato as estado 
        FROM contratos c LEFT JOIN clientes cli ON c.cliente_id=cli.id 
        UNION ALL
        SELECT 'Nota Venta' as tipo, nv.numero as comprobante, nv.cliente_nombre as cliente, nv.total, nv.fecha, nv.estado
        FROM notas_venta nv
        ORDER BY fecha DESC LIMIT 6
    ")->fetchAll(PDO::FETCH_ASSOC);
}

// 8. Ventas por mes (últimos 6 meses)
if ($isSeller) {
    $ventas_meses = [];
    $labels_meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun'];
    $data_meses = [0, 0, 0, 0, 0, floatval($ventas_mes['total'] ?? 0)];
} else {
    $ventas_meses = $db->query("
        SELECT TO_CHAR(fecha,'Mon') as mes, EXTRACT(MONTH FROM fecha) as num_mes, 
               COALESCE(SUM(total),0) as total 
        FROM ventas 
        WHERE estado='Completada' AND fecha >= CURRENT_DATE - INTERVAL '6 months'
        GROUP BY mes, num_mes 
        ORDER BY num_mes
    ")->fetchAll(PDO::FETCH_ASSOC);

    $labels_meses = [];
    $data_meses = [];
    foreach ($ventas_meses as $vm) {
        $labels_meses[] = $vm['mes'];
        $data_meses[] = floatval($vm['total']);
    }

    if (empty($labels_meses)) {
        $labels_meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun'];
        $data_meses = [0, 0, 0, 0, 0, floatval($ventas_mes['total'] ?? 0)];
    }
}

$page_title = 'Inicio';
$page_subtitle = 'Panel principal y resumen general de operaciones';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> — Carpicenter</title>
    <link rel="stylesheet" href="/carpicenter_sys/assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        /* ===== INICIO / DASHBOARD PREMIUM ===== */
        .dash-hero {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.4rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .dash-hero-title h1 {
            font-size: 1.45rem;
            font-weight: 800;
            color: #111827;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .dash-hero-title p {
            color: #6B7280;
            font-size: 0.84rem;
            margin: 0.25rem 0 0 0;
        }

        /* KPIs */
        .dash-kpis-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.2rem;
            margin-bottom: 1.3rem;
        }
        .dash-kpi-card {
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
        .dash-kpi-card:hover {
            transform: translateY(-2px);
        }
        .dash-kpi-icon {
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
        .icon-indigo-bg { background: linear-gradient(135deg, rgba(79,70,229,0.12) 0%, rgba(99,102,241,0.2) 100%); color: #4F46E5; }
        .icon-amber-bg { background: linear-gradient(135deg, rgba(217,119,6,0.12) 0%, rgba(245,158,11,0.2) 100%); color: #D97706; }
        .icon-blue-bg { background: linear-gradient(135deg, rgba(37,99,235,0.12) 0%, rgba(59,130,246,0.2) 100%); color: #2563EB; }

        .dash-kpi-info span.label {
            font-size: 0.74rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
            color: #6B7280;
            display: block;
            margin-bottom: 0.15rem;
        }
        .dash-kpi-info h3 {
            font-size: 1.35rem;
            font-weight: 800;
            color: #111827;
            margin: 0;
            line-height: 1.2;
        }
        .dash-kpi-info span.sub-tag {
            font-size: 0.74rem;
            font-weight: 600;
            display: inline-block;
            margin-top: 0.25rem;
            padding: 2px 8px;
            border-radius: 20px;
        }

        /* 2 Columns Master Grid */
        .dash-main-grid {
            display: grid;
            grid-template-columns: 1.4fr 1fr;
            gap: 1.25rem;
            margin-bottom: 1.5rem;
        }
        @media (max-width: 1024px) {
            .dash-main-grid { grid-template-columns: 1fr; }
        }

        .dash-card {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
            margin-bottom: 1.25rem;
        }
        .dash-card-header {
            padding: 1rem 1.4rem;
            border-bottom: 1px solid #F3F4F6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .dash-card-header h3 {
            font-size: 0.95rem;
            font-weight: 700;
            color: #111827;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .dash-card-header a {
            font-size: 0.8rem;
            font-weight: 600;
            color: #2563EB;
            text-decoration: none;
        }
        .dash-card-header a:hover {
            text-decoration: underline;
        }
        .dash-card-body {
            padding: 1.2rem;
        }

        /* Tables (Single-Line Ultra Clean) */
        .dash-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        .dash-table th {
            background: #F9FAFB;
            color: #6B7280;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #E5E7EB;
            white-space: nowrap;
        }
        .dash-table td {
            padding: 0.8rem 1rem;
            border-bottom: 1px solid #F3F4F6;
            font-size: 0.84rem;
            color: #374151;
            vertical-align: middle;
            white-space: nowrap;
        }
        .dash-table tbody tr:hover {
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
        .status-pill.proceso { background: rgba(37,99,235,0.1); color: #2563EB; border: 1px solid rgba(37,99,235,0.25); }
        .status-pill.pendiente { background: rgba(217,119,6,0.1); color: #D97706; border: 1px solid rgba(217,119,6,0.25); }

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
        .btn-action-soft.move { background: rgba(37,99,235,0.08); color: #2563EB; }
        .btn-action-soft.move:hover { background: #2563EB; color: #FFFFFF; }
    </style>
</head>
<body>
<div class="app-wrapper">
    <?php include 'partials/sidebar.php'; ?>
    <div class="main-content">
        <?php include 'partials/header.php'; ?>
        <div class="page-content">

            <!-- Header de la Página -->
            <div class="dash-hero">
                <div class="dash-hero-title">
                    <h1><i class="fas fa-home" style="color:#E31E24;"></i> Inicio</h1>
                    <p>Centro de mando general y resumen operativo de Carpicenter</p>
                </div>
            </div>

            <!-- WIDGET DE META QUINCENAL Y COMISIONES (1%) -->
            <div class="dash-card" style="margin-bottom:1.4rem; border: 1.5px solid <?= $meta_alcanzada ? '#10B981' : '#E2E8F0' ?>; box-shadow: 0 4px 15px rgba(0,0,0,0.04);">
                <div class="dash-card-header" style="background: <?= $meta_alcanzada ? '#F0FDF4' : '#F8FAFC' ?>; padding: 0.9rem 1.3rem;">
                    <h3 style="color: <?= $meta_alcanzada ? '#047857' : '#1E293B' ?>; font-size: 0.95rem;">
                        <i class="fas fa-bullseye" style="color: <?= $meta_alcanzada ? '#10B981' : '#E31E24' ?>;"></i>
                        <?= in_array(strtolower($user_role ?? ''), ['vendedor', 'vendedora']) ? 'Mi Meta de Ventas y Comisión (1%)' : 'Control de Metas de Ventas Quincenal' ?>
                    </h3>
                    <span style="font-size: 0.78rem; font-weight: 700; background: #FFFFFF; color: #475569; padding: 4px 12px; border-radius: 20px; border: 1px solid #CBD5E1;">
                        <i class="fas fa-calendar-days" style="color: #2563EB; margin-right: 4px;"></i> <?= $quincena_label ?>
                    </span>
                </div>
                <div class="dash-card-body" style="padding: 1.25rem 1.35rem;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.2rem;">
                        <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 10px; padding: 10px 14px;">
                            <span style="font-size: 0.72rem; font-weight: 700; color: #64748B; text-transform: uppercase; letter-spacing: 0.5px; display: block;">Ventas en esta Quincena</span>
                            <div style="font-size: 1.35rem; font-weight: 900; color: #0F172A; margin-top: 2px;">
                                S/ <?= number_format($total_vendido_quincena, 2) ?>
                            </div>
                        </div>

                        <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 10px; padding: 10px 14px;">
                            <span style="font-size: 0.72rem; font-weight: 700; color: #64748B; text-transform: uppercase; letter-spacing: 0.5px; display: block;">Meta Quincenal</span>
                            <div style="font-size: 1.35rem; font-weight: 900; color: #2563EB; margin-top: 2px;">
                                S/ 20,000.00
                            </div>
                        </div>

                        <div style="background: <?= $meta_alcanzada ? '#F0FDF4' : '#FFF5F5' ?>; border: 1.5px solid <?= $meta_alcanzada ? '#86EFAC' : '#FECACA' ?>; border-radius: 10px; padding: 10px 14px;">
                            <span style="font-size: 0.72rem; font-weight: 800; color: <?= $meta_alcanzada ? '#15803D' : '#C62828' ?>; text-transform: uppercase; letter-spacing: 0.5px; display: block;">
                                <?= $meta_alcanzada ? '🎉 Comisión Ganada (1%)' : 'Comisión Estimada (1%)' ?>
                            </span>
                            <div style="font-size: 1.35rem; font-weight: 900; color: <?= $meta_alcanzada ? '#15803D' : '#C62828' ?>; margin-top: 2px;">
                                <?= $meta_alcanzada ? 'S/ ' . number_format($comision_ganada, 2) : 'S/ 0.00' ?>
                            </div>
                            <span style="font-size: 0.68rem; color: <?= $meta_alcanzada ? '#166534' : '#64748B' ?>; font-weight: 600;">
                                <?= $meta_alcanzada ? 'Comisión habilitada al 1%' : 'Requiere meta mínima de S/ 20,000' ?>
                            </span>
                        </div>
                    </div>

                    <!-- Barra de Progreso -->
                    <div style="margin-bottom: 0.8rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; font-size: 0.8rem; font-weight: 700;">
                            <span style="color: #334155;">Progreso hacia la Meta:</span>
                            <span style="color: <?= $meta_alcanzada ? '#15803D' : '#2563EB' ?>;"><?= $porcentaje_meta ?>% (S/ <?= number_format($total_vendido_quincena, 2) ?> / S/ 20,000.00)</span>
                        </div>
                        <div style="height: 12px; background: #E2E8F0; border-radius: 20px; overflow: hidden; position: relative;">
                            <div style="height: 100%; width: <?= $porcentaje_meta ?>%; background: <?= $meta_alcanzada ? 'linear-gradient(90deg, #10B981, #059669)' : 'linear-gradient(90deg, #3B82F6, #2563EB)' ?>; border-radius: 20px; transition: width 0.6s ease;"></div>
                        </div>
                    </div>

                    <!-- Mensaje de Estado / Motivacional -->
                    <?php if ($meta_alcanzada): ?>
                        <div style="background: #ECFDF5; border: 1px solid #A7F3D0; border-radius: 8px; padding: 8px 12px; font-size: 0.8rem; color: #065F46; display: flex; align-items: center; gap: 8px; font-weight: 600;">
                            <i class="fas fa-circle-check" style="color: #10B981; font-size: 1rem;"></i>
                            ¡Felicitaciones! Has superado la meta de S/ 20,000 en esta quincena. Tu comisión del 1% está activada y comisionas <strong>S/ <?= number_format($comision_ganada, 2) ?></strong>.
                        </div>
                    <?php else: ?>
                        <div style="background: #EFF6FF; border: 1px solid #BFDBFE; border-radius: 8px; padding: 8px 12px; font-size: 0.8rem; color: #1E40AF; display: flex; align-items: center; gap: 8px; font-weight: 600;">
                            <i class="fas fa-bolt" style="color: #3B82F6; font-size: 1rem;"></i>
                            Te faltan <strong>S/ <?= number_format($monto_faltante, 2) ?></strong> en ventas para alcanzar los S/ 20,000 y activar tu comisión del 1% (mínimo S/ 200.00). ¡Sigue impulsando tus ventas!
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- KPIs Ejecutivos -->
            <div class="dash-kpis-grid">
                <div class="dash-kpi-card">
                    <div class="dash-kpi-icon icon-emerald-bg">
                        <i class="fas fa-sack-dollar"></i>
                    </div>
                    <div class="dash-kpi-info">
                        <span class="label">Ventas del Mes</span>
                        <h3 style="color:#059669;">S/ <?= number_format($ventas_mes['total'] ?? 0, 2) ?></h3>
                        <span class="sub-tag" style="background:#ECFDF5; color:#059669;">Facturado + Contratos</span>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="dash-kpi-icon icon-indigo-bg">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="dash-kpi-info">
                        <span class="label">Total Productos</span>
                        <h3 style="color:#4F46E5;"><?= $total_productos['total'] ?></h3>
                        <span class="sub-tag" style="background:#EEF2FF; color:#4F46E5;">Modelos en catálogo</span>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="dash-kpi-icon icon-amber-bg">
                        <i class="fas fa-triangle-exclamation"></i>
                    </div>
                    <div class="dash-kpi-info">
                        <span class="label">Stock Bajo / Alertas</span>
                        <h3 style="color:#D97706;"><?= $stock_bajo['total'] ?></h3>
                        <span class="sub-tag" style="background:#FFFBEB; color:#D97706;">Requiere reposición</span>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <?php if ($isSeller): ?>
                        <div class="dash-kpi-icon icon-blue-bg">
                            <i class="fas fa-file-invoice"></i>
                        </div>
                        <div class="dash-kpi-info">
                            <span class="label">Mis Cotizaciones</span>
                            <h3 style="color:#2563EB;"><?= $mis_cotizaciones_total ?></h3>
                            <span class="sub-tag" style="background:#EFF6FF; color:#2563EB;">Presupuestos emitidos</span>
                        </div>
                    <?php else: ?>
                        <div class="dash-kpi-icon icon-blue-bg">
                            <i class="fas fa-cart-shopping"></i>
                        </div>
                        <div class="dash-kpi-info">
                            <span class="label">Compras del Mes</span>
                            <h3 style="color:#2563EB;">S/ <?= number_format($compras_mes['total'] ?? 0, 2) ?></h3>
                            <span class="sub-tag" style="background:#EFF6FF; color:#2563EB;">Insumos y materiales</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Main Layout Grid -->
            <div class="dash-main-grid">

                <!-- Columna Izquierda -->
                <div>
                    <!-- Gráfico de Ventas -->
                    <div class="dash-card">
                        <div class="dash-card-header">
                            <h3><i class="fas fa-chart-line" style="color:#E31E24;"></i> Tendencia de Ventas (Últimos 6 Meses)</h3>
                            <span style="font-size:0.8rem; color:#6B7280; font-weight:600;">Soles (S/)</span>
                        </div>
                        <div class="dash-card-body" style="height:280px; position:relative;">
                            <canvas id="salesChart"></canvas>
                        </div>
                    </div>

                    <!-- Últimas Operaciones Comerciales -->
                    <div class="dash-card">
                        <div class="dash-card-header">
                            <h3><i class="fas fa-receipt" style="color:#2563EB;"></i> Últimas Ventas y Contratos Emitidos</h3>
                            <a href="/carpicenter_sys/views/ventas.php">Ver todo</a>
                        </div>
                        <div style="overflow-x:auto;">
                            <table class="dash-table">
                                <thead>
                                    <tr>
                                        <th>Tipo</th>
                                        <th>Comprobante</th>
                                        <th>Cliente</th>
                                        <th style="text-align:right;">Total (S/)</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($ultimas_ventas)): ?>
                                        <tr><td colspan="5" style="text-align:center; padding:2rem; color:#9CA3AF;">Sin operaciones recientes.</td></tr>
                                    <?php else: foreach ($ultimas_ventas as $v): 
                                        $isContrato = ($v['tipo'] === 'Contrato');
                                        $st = $v['estado'];
                                        $badgeSt = 'activo';
                                        if (in_array($st, ['Pendiente', 'En Producción'])) $badgeSt = 'pendiente';
                                        if ($st === 'Anulado') $badgeSt = 'inactivo';
                                    ?>
                                    <tr>
                                        <td>
                                            <span style="font-size:0.74rem; font-weight:700; color:<?= $isContrato ? '#2563EB' : '#D97706' ?>; background:<?= $isContrato ? '#EFF6FF' : '#FFFBEB' ?>; padding:2px 7px; border-radius:6px;">
                                                <?= $v['tipo'] ?>
                                            </span>
                                        </td>
                                        <td><span class="doc-badge"><?= htmlspecialchars($v['comprobante'] ?? 'VTA') ?></span></td>
                                        <td><strong style="color:#111827;"><?= htmlspecialchars($v['cliente'] ?? 'N/A') ?></strong></td>
                                        <td style="text-align:right; font-weight:800; color:#111827;">S/ <?= number_format($v['total'], 2) ?></td>
                                        <td>
                                            <span class="status-pill <?= $badgeSt ?>">
                                                <?= htmlspecialchars($v['estado']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Columna Derecha -->
                <div>
                    <!-- Alertador de Stock Bajo -->
                    <div class="dash-card">
                        <div class="dash-card-header">
                            <h3><i class="fas fa-triangle-exclamation" style="color:#D97706;"></i> Alertador de Stock Bajo</h3>
                            <a href="/carpicenter_sys/modules/inventario/existencias.php?filter=lowstock">Inventario</a>
                        </div>
                        <div style="overflow-x:auto;">
                            <table class="dash-table">
                                <thead>
                                    <tr>
                                        <th>Producto / Color</th>
                                        <th style="text-align:center;">Stock</th>
                                        <th style="text-align:center;">Alerta</th>
                                        <th style="text-align:center;">Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($productos_bajo)): ?>
                                        <tr>
                                            <td colspan="4" style="text-align:center; padding:2rem; color:#059669;">
                                                <i class="fas fa-circle-check" style="font-size:1.5rem; display:block; margin-bottom:0.4rem;"></i>
                                                ¡Excelente! Todo el stock está en niveles óptimos.
                                            </td>
                                        </tr>
                                    <?php else: foreach ($productos_bajo as $pb): 
                                        $st = intval($pb['stock_disponible']);
                                        if ($st === 0) {
                                            $badgeHtml = '<span class="status-pill inactivo" style="font-size:0.68rem;"><i class="fas fa-ban"></i> AGOTADO</span>';
                                        } elseif ($st <= 3) {
                                            $badgeHtml = '<span class="status-pill pendiente" style="font-size:0.68rem;"><i class="fas fa-triangle-exclamation"></i> CRÍTICO</span>';
                                        } else {
                                            $badgeHtml = '<span class="status-pill pendiente" style="font-size:0.68rem;">BAJO</span>';
                                        }
                                    ?>
                                    <tr>
                                        <td>
                                            <strong style="color:#111827; display:block;"><?= htmlspecialchars($pb['nombre']) ?></strong>
                                            <small style="color:#6B7280; font-size:0.75rem;"><i class="fas fa-palette" style="color:#E31E24; font-size:0.7rem;"></i> <?= htmlspecialchars($pb['color_nombre']) ?></small>
                                        </td>
                                        <td style="text-align:center; font-weight:800; color:<?= $st===0 ? '#DC2626' : '#D97706' ?>;">
                                            <?= $st ?> un
                                        </td>
                                        <td style="text-align:center;"><?= $badgeHtml ?></td>
                                        <td style="text-align:center;">
                                            <a href="/carpicenter_sys/modules/transferencias/transferencia_form.php" class="btn-action-soft move" title="Mover stock">
                                                <i class="fas fa-truck-ramp-box"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Top Productos más Vendidos -->
                    <div class="dash-card">
                        <div class="dash-card-header">
                            <h3><i class="fas fa-trophy" style="color:#F59E0B;"></i> Productos Más Vendidos</h3>
                            <a href="/carpicenter_sys/views/productos.php">Catálogo</a>
                        </div>
                        <div style="overflow-x:auto;">
                            <table class="dash-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Producto</th>
                                        <th style="text-align:right;">Unidades</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($top_productos)): ?>
                                        <tr><td colspan="3" style="text-align:center; padding:2rem; color:#9CA3AF;">Sin ventas registradas.</td></tr>
                                    <?php else: foreach ($top_productos as $i => $tp): ?>
                                    <tr>
                                        <td style="font-weight:800; color:#6B7280;"><?= $i + 1 ?></td>
                                        <td><strong style="color:#111827;"><?= htmlspecialchars($tp['nombre']) ?></strong></td>
                                        <td style="text-align:right; font-weight:800; color:#059669;"><?= $tp['total_vendido'] ?> un</td>
                                    </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const salesCtx = document.getElementById('salesChart');
    if (salesCtx) {
        const ctx = salesCtx.getContext('2d');
        const gradient = ctx.createLinearGradient(0, 0, 0, 240);
        gradient.addColorStop(0, 'rgba(227, 30, 36, 0.25)');
        gradient.addColorStop(1, 'rgba(227, 30, 36, 0.0)');

        new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($labels_meses) ?>,
                datasets: [{ 
                    label: 'Ventas (S/)', 
                    data: <?= json_encode($data_meses) ?>, 
                    borderColor: '#E31E24', 
                    backgroundColor: gradient, 
                    fill: true, 
                    tension: 0.35, 
                    pointBackgroundColor: '#E31E24', 
                    pointBorderColor: '#FFFFFF',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false, 
                plugins: { 
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return ' S/ ' + context.parsed.y.toLocaleString('es-PE', {minimumFractionDigits: 2});
                            }
                        }
                    }
                }, 
                scales: { 
                    x: { 
                        grid: { display: false }, 
                        ticks: { color: '#6B7280', font: { size: 11, weight: 'bold' } } 
                    }, 
                    y: { 
                        grid: { color: 'rgba(0,0,0,0.05)' }, 
                        ticks: { 
                            color: '#6B7280', 
                            callback: function(value) { return 'S/ ' + value.toLocaleString('es-PE'); }
                        } 
                    } 
                } 
            }
        });
    }
});
</script>

<?php include 'partials/footer.php'; ?>
</body>
</html>