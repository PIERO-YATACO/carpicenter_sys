<?php
require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/transferencia_model.php';

$model = new TransferenciaModel($db);

// Filter list based on role
if ($user_role === 'Super Admin') {
    $transferencias = $model->getAll();
} else {
    $transferencias = $model->getAll($user_local_id);
}

// Estadísticas
$totTrans = count($transferencias);
$totTransito = 0;
$totCompletadas = 0;
foreach ($transferencias as $t) {
    if ($t['estado'] === 'En Tránsito') $totTransito++;
    if ($t['estado'] === 'Completada' || $t['estado'] === 'Confirmada') $totCompletadas++;
}

$page_title = 'Transferencias entre Tiendas';
$page_subtitle = 'Envío, recepción y confirmación de stock entre locales y almacén central';
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
        /* ===== TRANSFERENCIAS PREMIUM ===== */
        .trans-hero {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.4rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .trans-hero-title h1 {
            font-size: 1.45rem;
            font-weight: 800;
            color: #111827;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .trans-hero-title p {
            color: #6B7280;
            font-size: 0.84rem;
            margin: 0.25rem 0 0 0;
        }

        /* KPIs */
        .trans-kpis-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 1.2rem;
            margin-bottom: 1.3rem;
        }
        .trans-kpi-card {
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
        .trans-kpi-card:hover {
            transform: translateY(-2px);
        }
        .trans-kpi-icon {
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
        .icon-amber-bg { background: linear-gradient(135deg, rgba(217,119,6,0.12) 0%, rgba(245,158,11,0.2) 100%); color: #D97706; }
        .icon-emerald-bg { background: linear-gradient(135deg, rgba(5,150,105,0.12) 0%, rgba(16,185,129,0.2) 100%); color: #059669; }

        .trans-kpi-info span.label {
            font-size: 0.74rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
            color: #6B7280;
            display: block;
            margin-bottom: 0.15rem;
        }
        .trans-kpi-info h3 {
            font-size: 1.35rem;
            font-weight: 800;
            color: #111827;
            margin: 0;
            line-height: 1.2;
        }
        .trans-kpi-info span.sub-tag {
            font-size: 0.74rem;
            font-weight: 600;
            display: inline-block;
            margin-top: 0.25rem;
            padding: 2px 8px;
            border-radius: 20px;
        }

        /* Filter Panel */
        .trans-filter-card {
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
        .trans-search-box {
            position: relative;
            flex: 1;
            min-width: 250px;
            max-width: 400px;
        }
        .trans-search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #9CA3AF;
            font-size: 0.9rem;
        }
        .trans-input {
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
        .trans-input:focus {
            background: #FFFFFF;
            border-color: #E31E24;
            box-shadow: 0 0 0 3px rgba(227,30,36,0.1);
        }

        /* Table Card */
        .trans-table-card {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        }
        .trans-table-header-title {
            padding: 1rem 1.4rem;
            border-bottom: 1px solid #F3F4F6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .trans-table-header-title h3 {
            font-size: 0.95rem;
            font-weight: 700;
            color: #111827;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .trans-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        .trans-table th {
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
        .trans-table td {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid #F3F4F6;
            font-size: 0.85rem;
            color: #374151;
            vertical-align: middle;
        }
        .trans-table tbody tr:hover {
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
        .store-pill {
            background: #EFF6FF;
            color: #2563EB;
            padding: 2px 7px;
            border-radius: 6px;
            font-size: 0.76rem;
            font-weight: 700;
            border: 1px solid #BFDBFE;
            display: inline-flex;
            align-items: center;
            gap: 4px;
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
        .status-pill.completada, .status-pill.confirmada { background: rgba(5,150,105,0.1); color: #059669; border: 1px solid rgba(5,150,105,0.25); }
        .status-pill.en-transito { background: rgba(217,119,6,0.1); color: #D97706; border: 1px solid rgba(217,119,6,0.25); }
        .status-pill.cancelada { background: rgba(220,38,38,0.1); color: #DC2626; border: 1px solid rgba(220,38,38,0.25); }

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
    </style>
</head>
<body>
<div class="app-wrapper">
    <?php include '../../views/partials/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../views/partials/header.php'; ?>
        <div class="page-content">

            <!-- Header de la Página -->
            <div class="trans-hero">
                <div class="trans-hero-title">
                    <h1><i class="fas fa-truck-loading" style="color:#E31E24;"></i> Transferencias</h1>
                    <p>Reabastecimiento y traslado de inventario entre almacenes y tiendas</p>
                </div>
                <?php if($user_role === 'Super Admin' || $user_role === 'Almacén'): ?>
                    <a href="transferencia_form.php" class="btn btn-primary" style="font-weight:600; padding:0.55rem 1.2rem; border-radius:10px; box-shadow:0 4px 12px rgba(227,30,36,0.25);">
                        <i class="fas fa-plus" style="margin-right:6px;"></i> Nueva Transferencia
                    </a>
                <?php endif; ?>
            </div>
            
            <?php if(isset($_GET['msg']) && $_GET['msg'] == 'creada'): ?>
                <div style="background:#10B981; color:#fff; padding:0.85rem 1.25rem; border-radius:10px; margin-bottom:1.2rem; display:flex; justify-content:space-between; align-items:center; font-weight:600;">
                    <div><i class="fas fa-check-circle" style="margin-right:8px;"></i> Transferencia creada y despachada con éxito.</div>
                    <button type="button" onclick="this.parentElement.remove()" style="background:none; border:none; color:#fff; font-size:1.2rem; cursor:pointer;">&times;</button>
                </div>
            <?php endif; ?>

            <!-- KPI Cards -->
            <div class="trans-kpis-grid">
                <div class="trans-kpi-card">
                    <div class="trans-kpi-icon icon-indigo-bg">
                        <i class="fas fa-dolly"></i>
                    </div>
                    <div class="trans-kpi-info">
                        <span class="label">Total Transferencias</span>
                        <h3 style="color:#4F46E5;"><?= $totTrans ?></h3>
                        <span class="sub-tag" style="background:#EEF2FF; color:#4F46E5;">Despachos</span>
                    </div>
                </div>

                <div class="trans-kpi-card">
                    <div class="trans-kpi-icon icon-amber-bg">
                        <i class="fas fa-truck-fast"></i>
                    </div>
                    <div class="trans-kpi-info">
                        <span class="label">En Tránsito</span>
                        <h3 style="color:#D97706;"><?= $totTransito ?></h3>
                        <span class="sub-tag" style="background:#FFFBEB; color:#D97706;">Pendientes de recepción</span>
                    </div>
                </div>

                <div class="trans-kpi-card">
                    <div class="trans-kpi-icon icon-emerald-bg">
                        <i class="fas fa-check-double"></i>
                    </div>
                    <div class="trans-kpi-info">
                        <span class="label">Confirmadas</span>
                        <h3 style="color:#059669;"><?= $totCompletadas ?></h3>
                        <span class="sub-tag" style="background:#ECFDF5; color:#059669;">Stock recibido</span>
                    </div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="trans-filter-card">
                <div class="trans-search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="transSearchInput" class="trans-input" placeholder="Buscar por código, origen o destino..." onkeyup="filterTransferencias()">
                </div>
                <div style="font-size:0.8rem; color:#6B7280; font-weight:500;">
                    Filtrando en tiempo real
                </div>
            </div>

            <!-- Tabla de Transferencias -->
            <div class="trans-table-card">
                <div class="trans-table-header-title">
                    <h3><i class="fas fa-truck-loading" style="color:#E31E24;"></i> Historial de Movimientos de Stock</h3>
                    <span style="font-size:0.8rem; color:#6B7280; font-weight:500;">
                        Mostrando <?= count($transferencias) ?> transferencias
                    </span>
                </div>

                <div style="overflow-x:auto;">
                    <table class="trans-table" id="transTable">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Almacén Origen</th>
                                <th>Almacén Destino</th>
                                <th>Fecha Envío</th>
                                <th>Estado</th>
                                <th style="text-align:center;">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($transferencias)): ?>
                                <tr>
                                    <td colspan="6" style="text-align:center; padding:3.5rem; color:#9CA3AF;">
                                        <i class="fas fa-truck-loading" style="font-size:2.5rem; margin-bottom:0.8rem; opacity:0.35; display:block;"></i>
                                        No hay transferencias registradas en esta sede.
                                    </td>
                                </tr>
                            <?php else: foreach($transferencias as $t): 
                                $isTransito = ($t['estado'] === 'En Tránsito');
                                $isCompletada = ($t['estado'] === 'Completada' || $t['estado'] === 'Confirmada');
                            ?>
                            <tr>
                                <td><span class="doc-badge"><?= htmlspecialchars($t['codigo']) ?></span></td>
                                <td><span class="store-pill"><i class="fas fa-box"></i> <?= htmlspecialchars($t['origen_nombre']) ?></span></td>
                                <td><span class="store-pill" style="background:#F0FDF4; color:#059669; border-color:#BBF7D0;"><i class="fas fa-location-dot"></i> <?= htmlspecialchars($t['destino_nombre']) ?></span></td>
                                <td style="font-size:0.83rem;"><?= date('d/m/Y H:i', strtotime($t['fecha_envio'])) ?></td>
                                <td>
                                    <?php if($isTransito): ?>
                                        <span class="status-pill en-transito"><i class="fas fa-truck-moving"></i> En Tránsito</span>
                                    <?php elseif($isCompletada): ?>
                                        <span class="status-pill completada"><i class="fas fa-check-double"></i> Confirmada</span>
                                    <?php else: ?>
                                        <span class="status-pill cancelada"><?= htmlspecialchars($t['estado']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;">
                                    <a href="transferencia_view.php?id=<?= $t['id'] ?>" class="btn-action-soft view" title="Ver Detalles y Confirmar">
                                        <i class="fas fa-eye"></i>
                                    </a>
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

<script>
function filterTransferencias() {
    const input = document.getElementById('transSearchInput').value.toLowerCase();
    const rows = document.querySelectorAll('#transTable tbody tr');
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(input) ? '' : 'none';
    });
}
</script>

<?php include '../../views/partials/footer.php'; ?>
</body>
</html>
