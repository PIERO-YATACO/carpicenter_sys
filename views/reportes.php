<?php
require_once __DIR__ . '/../auth/check_auth.php';
require_once __DIR__ . '/../config/db.php';

$page_title = 'Reportes y Analítica';
$page_subtitle = 'Métricas de rendimiento comercial, ventas mensuales y análisis de inventario';

// Parámetros de filtro
$anio = isset($_GET['anio']) ? intval($_GET['anio']) : intval(date('Y'));
$mes = isset($_GET['mes']) ? $_GET['mes'] : 'todos';

// 1. Consulta de Ventas Mensuales para el año seleccionado
$sql_meses = "
    SELECT 
        EXTRACT(MONTH FROM COALESCE(fecha_emision, fecha)) as nro_mes,
        TO_CHAR(COALESCE(fecha_emision, fecha), 'TMMonth') as mes_nombre,
        COUNT(*) as total_ventas,
        COALESCE(SUM(total), 0) as total_monto
    FROM ventas
    WHERE EXTRACT(YEAR FROM COALESCE(fecha_emision, fecha)) = :anio
      AND estado != 'Cancelada'
    GROUP BY EXTRACT(MONTH FROM COALESCE(fecha_emision, fecha)), TO_CHAR(COALESCE(fecha_emision, fecha), 'TMMonth')
    ORDER BY nro_mes ASC
";
$stmt_meses = $db->prepare($sql_meses);
$stmt_meses->execute([':anio' => $anio]);
$ventas_mensuales = $stmt_meses->fetchAll(PDO::FETCH_ASSOC);

// Normalizar los 12 meses
$meses_nombres = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

$datos_grafico_labels = [];
$datos_grafico_montos = [];
$tabla_mensual = [];
$tot_facturado_anio = 0;
$tot_operaciones_anio = 0;

$map_ventas = [];
foreach ($ventas_mensuales as $vm) {
    $map_ventas[intval($vm['nro_mes'])] = $vm;
}

$monto_anterior = 0;
for ($m = 1; $m <= 12; $m++) {
    $nombre = $meses_nombres[$m];
    $monto = isset($map_ventas[$m]) ? floatval($map_ventas[$m]['total_monto']) : 0;
    $cant = isset($map_ventas[$m]) ? intval($map_ventas[$m]['total_ventas']) : 0;
    
    $tot_facturado_anio += $monto;
    $tot_operaciones_anio += $cant;

    // Calcular variación
    $variacion = '—';
    $variacion_class = '';
    if ($m > 1 && $monto_anterior > 0) {
        $dif = (($monto - $monto_anterior) / $monto_anterior) * 100;
        $variacion = ($dif >= 0 ? '+' : '') . number_format($dif, 1) . '%';
        $variacion_class = ($dif >= 0) ? 'up' : 'down';
    }
    if ($monto > 0) $monto_anterior = $monto;

    $datos_grafico_labels[] = substr($nombre, 0, 3);
    $datos_grafico_montos[] = $monto;

    $tabla_mensual[] = [
        'mes' => $nombre,
        'monto' => $monto,
        'cantidad' => $cant,
        'variacion' => $variacion,
        'variacion_class' => $variacion_class,
        'promedio' => ($cant > 0) ? ($monto / $cant) : 0
    ];
}

// 2. Top 5 Productos más Vendidos
$sql_top = "
    SELECT 
        p.nombre,
        COALESCE(c.nombre, 'General') as categoria,
        COALESCE(SUM(vd.cantidad), 0) as unidades_vendidas,
        COALESCE(SUM(vd.cantidad * vd.precio_historico), 0) as total_generado
    FROM venta_detalles vd
    JOIN ventas v ON vd.venta_id = v.id
    JOIN productos p ON vd.producto_id = p.id
    LEFT JOIN categorias c ON p.categoria_id = c.id
    WHERE EXTRACT(YEAR FROM COALESCE(v.fecha_emision, v.fecha)) = :anio
      AND v.estado != 'Cancelada'
    GROUP BY p.id, p.nombre, c.nombre
    ORDER BY total_generado DESC
    LIMIT 5
";
$stmt_top = $db->prepare($sql_top);
$stmt_top->execute([':anio' => $anio]);
$top_productos = $stmt_top->fetchAll(PDO::FETCH_ASSOC);

// 3. Totales de Contratos y Abonos del año
$stmt_abonos = $db->prepare("
    SELECT COALESCE(SUM(monto), 0) 
    FROM contrato_abonos 
    WHERE EXTRACT(YEAR FROM fecha) = :anio
");
$stmt_abonos->execute([':anio' => $anio]);
$tot_abonos_contratos = floatval($stmt_abonos->fetchColumn());

$ticket_promedio_general = ($tot_operaciones_anio > 0) ? ($tot_facturado_anio / $tot_operaciones_anio) : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> — Carpicenter</title>
    <link rel="stylesheet" href="/carpicenter_sys/assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* ===== REPORTES & ANALÍTICA PREMIUM ===== */
        .rep-hero {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.4rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .rep-hero-title h1 {
            font-size: 1.45rem;
            font-weight: 800;
            color: #111827;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .rep-hero-title p {
            color: #6B7280;
            font-size: 0.84rem;
            margin: 0.25rem 0 0 0;
        }
        .rep-hero-actions {
            display: flex;
            gap: 0.65rem;
            align-items: center;
            flex-wrap: wrap;
        }

        /* KPIs */
        .rep-kpis-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.2rem;
            margin-bottom: 1.3rem;
        }
        .rep-kpi-card {
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
        .rep-kpi-card:hover {
            transform: translateY(-2px);
        }
        .rep-kpi-icon {
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
        .icon-blue-bg { background: linear-gradient(135deg, rgba(37,99,235,0.12) 0%, rgba(59,130,246,0.2) 100%); color: #2563EB; }
        .icon-indigo-bg { background: linear-gradient(135deg, rgba(79,70,229,0.12) 0%, rgba(99,102,241,0.2) 100%); color: #4F46E5; }
        .icon-amber-bg { background: linear-gradient(135deg, rgba(217,119,6,0.12) 0%, rgba(245,158,11,0.2) 100%); color: #D97706; }

        .rep-kpi-info span.label {
            font-size: 0.74rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
            color: #6B7280;
            display: block;
            margin-bottom: 0.15rem;
        }
        .rep-kpi-info h3 {
            font-size: 1.35rem;
            font-weight: 800;
            color: #111827;
            margin: 0;
            line-height: 1.2;
        }
        .rep-kpi-info span.sub-tag {
            font-size: 0.74rem;
            font-weight: 600;
            display: inline-block;
            margin-top: 0.25rem;
            padding: 2px 8px;
            border-radius: 20px;
        }

        /* Filter Panel */
        .rep-filter-card {
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
        .rep-select {
            padding: 0.55rem 2rem 0.55rem 0.85rem;
            border-radius: 9px;
            border: 1px solid #D1D5DB;
            background: #F9FAFB;
            font-size: 0.84rem;
            font-weight: 600;
            color: #111827;
            outline: none;
        }

        /* Grid Layout */
        .rep-analytics-grid {
            display: grid;
            grid-template-columns: 1.4fr 1fr;
            gap: 1.25rem;
            margin-bottom: 1.4rem;
        }
        @media (max-width: 1024px) {
            .rep-analytics-grid { grid-template-columns: 1fr; }
        }

        .rep-panel {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
            display: flex;
            flex-direction: column;
        }
        .rep-panel-header {
            padding: 1rem 1.4rem;
            border-bottom: 1px solid #F3F4F6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .rep-panel-header h3 {
            font-size: 0.95rem;
            font-weight: 700;
            color: #111827;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .rep-panel-body {
            padding: 1.2rem;
            flex: 1;
        }

        /* Tables */
        .rep-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        .rep-table th {
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
        .rep-table td {
            padding: 0.8rem 1rem;
            border-bottom: 1px solid #F3F4F6;
            font-size: 0.84rem;
            color: #374151;
            vertical-align: middle;
        }
        .rep-table tbody tr:hover {
            background: #F9FAFB;
        }

        .badge-var {
            font-size: 0.74rem;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 6px;
            display: inline-block;
        }
        .badge-var.up { background: #ECFDF5; color: #059669; }
        .badge-var.down { background: #FEF2F2; color: #DC2626; }

        @media print {
            .app-sidebar, .header-wrapper, .rep-filter-card, .rep-hero-actions, .no-print {
                display: none !important;
            }
            .app-wrapper { margin: 0 !important; padding: 0 !important; }
            .main-content, .page-content { padding: 0 !important; margin: 0 !important; }
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
            <div class="rep-hero">
                <div class="rep-hero-title">
                    <h1><i class="fas fa-chart-line" style="color:#E31E24;"></i> Reportes y Analítica</h1>
                    <p>Rendimiento comercial de ventas, facturación mensual y productos estrella</p>
                </div>
                <div class="rep-hero-actions">
                    <button type="button" class="btn btn-outline" onclick="window.print()" style="padding:0.55rem 1rem; border-radius:10px;" title="Imprimir Informe">
                        <i class="fas fa-print" style="margin-right:6px;"></i> Imprimir Informe
                    </button>
                </div>
            </div>

            <!-- KPIs -->
            <div class="rep-kpis-grid">
                <div class="rep-kpi-card">
                    <div class="rep-kpi-icon icon-emerald-bg">
                        <i class="fas fa-sack-dollar"></i>
                    </div>
                    <div class="rep-kpi-info">
                        <span class="label">Ventas Facturadas <?= $anio ?></span>
                        <h3 style="color:#059669;">S/ <?= number_format($tot_facturado_anio, 2) ?></h3>
                        <span class="sub-tag" style="background:#ECFDF5; color:#059669;">Facturación neta</span>
                    </div>
                </div>

                <div class="rep-kpi-card">
                    <div class="rep-kpi-icon icon-blue-bg">
                        <i class="fas fa-file-contract"></i>
                    </div>
                    <div class="rep-kpi-info">
                        <span class="label">Abonos Contratos</span>
                        <h3 style="color:#2563EB;">S/ <?= number_format($tot_abonos_contratos, 2) ?></h3>
                        <span class="sub-tag" style="background:#EFF6FF; color:#2563EB;">Recaudación de proyectos</span>
                    </div>
                </div>

                <div class="rep-kpi-card">
                    <div class="rep-kpi-icon icon-indigo-bg">
                        <i class="fas fa-receipt"></i>
                    </div>
                    <div class="rep-kpi-info">
                        <span class="label">Total Comprobantes</span>
                        <h3 style="color:#4F46E5;"><?= $tot_operaciones_anio ?></h3>
                        <span class="sub-tag" style="background:#EEF2FF; color:#4F46E5;">Transacciones emitidas</span>
                    </div>
                </div>

                <div class="rep-kpi-card">
                    <div class="rep-kpi-icon icon-amber-bg">
                        <i class="fas fa-calculator"></i>
                    </div>
                    <div class="rep-kpi-info">
                        <span class="label">Ticket Promedio</span>
                        <h3 style="color:#D97706;">S/ <?= number_format($ticket_promedio_general, 2) ?></h3>
                        <span class="sub-tag" style="background:#FFFBEB; color:#D97706;">Por comprobante</span>
                    </div>
                </div>
            </div>

            <!-- Filtro de Año -->
            <div class="rep-filter-card no-print">
                <form method="GET" action="reportes.php" style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                    <span style="font-size:0.84rem; font-weight:700; color:#4B5563; text-transform:uppercase;">
                        <i class="fas fa-calendar-days" style="color:#E31E24; margin-right:4px;"></i> Periodo Anual:
                    </span>
                    <select name="anio" class="rep-select" onchange="this.form.submit()">
                        <?php for($y = date('Y'); $y >= 2023; $y--): ?>
                            <option value="<?= $y ?>" <?= $anio === $y ? 'selected' : '' ?>>Año <?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </form>
                <div style="font-size:0.82rem; color:#6B7280; font-weight:500;">
                    Datos consolidados en tiempo real
                </div>
            </div>

            <!-- Gráfico y Desglose Mensual -->
            <div class="rep-analytics-grid">
                <!-- Gráfico de Ventas Mensuales -->
                <div class="rep-panel">
                    <div class="rep-panel-header">
                        <h3><i class="fas fa-chart-column" style="color:#E31E24;"></i> Evolución de Ventas por Mes (S/)</h3>
                        <span style="font-size:0.8rem; color:#6B7280; font-weight:600;">Año <?= $anio ?></span>
                    </div>
                    <div class="rep-panel-body" style="min-height:300px; position:relative;">
                        <canvas id="chartVentasMensuales"></canvas>
                    </div>
                </div>

                <!-- Top 5 Productos más vendidos -->
                <div class="rep-panel">
                    <div class="rep-panel-header">
                        <h3><i class="fas fa-trophy" style="color:#F59E0B;"></i> Top 5 Productos Más Vendidos</h3>
                    </div>
                    <div class="rep-panel-body" style="padding:0;">
                        <table class="rep-table">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th style="text-align:right;">Cantidad</th>
                                    <th style="text-align:right;">Total (S/)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($top_productos)): ?>
                                    <tr><td colspan="3" style="text-align:center; padding:2rem; color:#9CA3AF;">Sin ventas en este periodo.</td></tr>
                                <?php else: foreach($top_productos as $tp): ?>
                                <tr>
                                    <td>
                                        <strong style="color:#111827;"><?= htmlspecialchars($tp['nombre']) ?></strong>
                                        <div style="font-size:0.74rem; color:#6B7280;"><?= htmlspecialchars($tp['categoria']) ?></div>
                                    </td>
                                    <td style="text-align:right; font-weight:700; color:#4B5563;"><?= number_format($tp['unidades_vendidas'], 1) ?> un</td>
                                    <td style="text-align:right; font-weight:800; color:#059669;">S/ <?= number_format($tp['total_generado'], 2) ?></td>
                                </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tabla de Detalle Mensual -->
            <div class="rep-panel">
                <div class="rep-panel-header">
                    <h3><i class="fas fa-table-list" style="color:#2563EB;"></i> Desglose Detallado de Ventas Mensuales</h3>
                </div>
                <div class="rep-panel-body" style="padding:0; overflow-x:auto;">
                    <table class="rep-table">
                        <thead>
                            <tr>
                                <th>Mes</th>
                                <th style="text-align:right;">Total Facturado (S/)</th>
                                <th style="text-align:right;">Comprobantes</th>
                                <th style="text-align:right;">Ticket Promedio</th>
                                <th style="text-align:center;">Variación Mensual</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($tabla_mensual as $tm): ?>
                            <tr>
                                <td><strong style="color:#111827;"><?= htmlspecialchars($tm['mes']) ?></strong></td>
                                <td style="text-align:right; font-weight:800; color:#111827;">S/ <?= number_format($tm['monto'], 2) ?></td>
                                <td style="text-align:right; font-weight:600; color:#4B5563;"><?= $tm['cantidad'] ?></td>
                                <td style="text-align:right; font-weight:600; color:#6B7280;">S/ <?= number_format($tm['promedio'], 2) ?></td>
                                <td style="text-align:center;">
                                    <?php if($tm['variacion'] !== '—'): ?>
                                        <span class="badge-var <?= $tm['variacion_class'] ?>">
                                            <i class="fas <?= $tm['variacion_class']==='up' ? 'fa-arrow-up' : 'fa-arrow-down' ?>"></i>
                                            <?= $tm['variacion'] ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color:#9CA3AF;">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const ctx = document.getElementById('chartVentasMensuales').getContext('2d');
    
    const gradient = ctx.createLinearGradient(0, 0, 0, 300);
    gradient.addColorStop(0, 'rgba(227, 30, 36, 0.35)');
    gradient.addColorStop(1, 'rgba(227, 30, 36, 0.0)');

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($datos_grafico_labels) ?>,
            datasets: [{
                label: 'Facturación Mensual (S/)',
                data: <?= json_encode($datos_grafico_montos) ?>,
                backgroundColor: 'rgba(227, 30, 36, 0.85)',
                borderColor: '#E31E24',
                borderWidth: 1,
                borderRadius: 8,
                maxBarThickness: 40
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
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,0.05)' },
                    ticks: {
                        callback: function(value) {
                            return 'S/ ' + value.toLocaleString('es-PE');
                        },
                        font: { size: 11 }
                    }
                },
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 11, weight: 'bold' } }
                }
            }
        }
    });
});
</script>

<?php include 'partials/footer.php'; ?>
</body>
</html>
