<?php
require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/transferencia_model.php';

$id = $_GET['id'] ?? null;
if (!$id) die("ID requerido");

$model = new TransferenciaModel($db);
$transferencia = $model->getById($id);

if (!$transferencia) die("Transferencia no encontrada.");

$page_title = 'Detalle de Transferencia ' . $transferencia['codigo'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - Carpicenter</title>
    <link rel="stylesheet" href="/carpicenter_sys/assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<div class="app-wrapper">
    <?php include '../../views/partials/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../views/partials/header.php'; ?>
        <div class="page-content">
            <div class="page-header" style="justify-content: flex-end; margin-bottom: 1rem;">
                <a href="transferencias.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Volver a Transferencias</a>
            </div>

            <?php if(isset($_GET['msg']) && $_GET['msg'] == 'confirmada'): ?>
            <div style="background:#e8f5e9; color:#2e7d32; padding:1rem; border-radius:5px; margin-bottom:1rem;">
                <i class="fas fa-check-circle"></i> Recepción confirmada exitosamente. El inventario ha sido actualizado.
            </div>
            <?php endif; ?>

            <div class="grid-3" style="grid-template-columns: 1fr 1fr 1fr; gap:1.5rem; margin-bottom:1.5rem;">
                <div class="card-panel" style="margin:0;">
                    <div class="card-body-custom" style="padding:1.5rem;">
                        <h4 style="margin:0 0 0.5rem 0; color:var(--text-muted); font-size:0.9rem;">ESTADO</h4>
                        <?php if($transferencia['estado'] == 'En Tránsito'): ?>
                            <span class="badge badge-warning" style="font-size:1.1rem; padding:0.5rem 1rem;"><i class="fas fa-truck-moving"></i> En Tránsito</span>
                        <?php elseif($transferencia['estado'] == 'Completada'): ?>
                            <span class="badge badge-success" style="font-size:1.1rem; padding:0.5rem 1rem;"><i class="fas fa-check-double"></i> Confirmada</span>
                        <?php else: ?>
                            <span class="badge badge-danger" style="font-size:1.1rem; padding:0.5rem 1rem;"><?= $transferencia['estado'] ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-panel" style="margin:0;">
                    <div class="card-body-custom" style="padding:1.5rem;">
                        <h4 style="margin:0 0 0.5rem 0; color:var(--text-muted); font-size:0.9rem;">RUTA</h4>
                        <p style="margin:0; font-weight:bold; font-size:1.1rem;">
                            <i class="fas fa-store" style="color:var(--primary);"></i> <?= htmlspecialchars($transferencia['origen_nombre']) ?> 
                            <i class="fas fa-arrow-right" style="margin:0 0.5rem; color:#ccc;"></i> 
                            <i class="fas fa-store-alt" style="color:#2e7d32;"></i> <?= htmlspecialchars($transferencia['destino_nombre']) ?>
                        </p>
                    </div>
                </div>
                <div class="card-panel" style="margin:0;">
                    <div class="card-body-custom" style="padding:1.5rem;">
                        <h4 style="margin:0 0 0.5rem 0; color:var(--text-muted); font-size:0.9rem;">FECHAS</h4>
                        <p style="margin:0 0 0.3rem 0;"><strong>Envío:</strong> <?= date('d/m/Y H:i', strtotime($transferencia['fecha_envio'])) ?></p>
                        <p style="margin:0;"><strong>Recepción:</strong> <?= $transferencia['fecha_recepcion'] ? date('d/m/Y H:i', strtotime($transferencia['fecha_recepcion'])) : '<span style="color:#f57c00;">Pendiente</span>' ?></p>
                    </div>
                </div>
            </div>

            <form action="transferencia_controller.php" method="POST" id="confirmForm">
                <input type="hidden" name="action" value="confirm">
                <input type="hidden" name="transferencia_id" value="<?= $id ?>">

                <div class="card-panel">
                    <div class="card-header"><h3><i class="fas fa-box-open" style="color:var(--primary);margin-right:0.5rem;"></i>Productos en la Transferencia</h3></div>
                    <div class="card-body-custom" style="padding: 0;">
                        <table class="table-custom">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th style="text-align:center;">Cantidad Enviada</th>
                                    <th style="text-align:center; width:200px;">Cantidad Recibida</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($transferencia['detalles'] as $det): ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars($det['producto_nombre']) ?>
                                        <br><small style="color:var(--text-muted);"><i class="fas fa-palette"></i> Color: <strong><?= htmlspecialchars($det['color_nombre']) ?></strong></small>
                                    </td>
                                    <td style="text-align:center; font-weight:bold; font-size:1.1rem;"><?= $det['cantidad_enviada'] ?></td>
                                    <td style="text-align:center;">
                                        <?php if($transferencia['estado'] == 'En Tránsito'): ?>
                                            <input type="number" name="recibida[<?= $det['id'] ?>]" class="form-control" value="<?= $det['cantidad_enviada'] ?>" min="0" max="<?= $det['cantidad_enviada'] ?>" style="text-align:center;">
                                        <?php else: ?>
                                            <span style="font-weight:bold; font-size:1.1rem; color:<?= $det['cantidad_enviada']==$det['cantidad_recibida'] ? '#2e7d32' : 'red' ?>;"><?= $det['cantidad_recibida'] ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php if($transferencia['estado'] == 'En Tránsito' && ($user_role === 'Super Admin' || $user_local_id == $transferencia['local_destino_id'])): ?>
                <div style="text-align: right; margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-success" onclick="return confirm('¿Confirma que ha recibido estos productos? El inventario de su local se actualizará.')">
                        <i class="fas fa-check"></i> Confirmar Recepción
                    </button>
                </div>
                <?php endif; ?>
            </form>

        </div>
    </div>
</div>
</body>
</html>
