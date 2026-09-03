<?php
require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/contrato_model.php';

$id = $_GET['id'] ?? null;
if (!$id) die("ID de contrato no especificado.");

$model = new ContratoModel($db);
$contrato = $model->getById($id);

if (!$contrato) die("Contrato no encontrado.");

$isSeller = in_array(strtolower($user_role ?? ''), ['vendedor', 'vendedora']);
if ($isSeller && !empty($contrato['vendedor_id']) && $contrato['vendedor_id'] != ($_SESSION['user_id'] ?? 0)) {
    die("Acceso restringido: Solo puedes consultar los contratos emitidos por tu usuario.");
}

$page_title = 'Contrato N° ' . $contrato['codigo_completo'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - Carpicenter</title>
    <link rel="stylesheet" href="/carpicenter_sys/assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .view-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.2rem; margin-bottom: 1.5rem; }
        .info-card {
            background: var(--bg-card); border: 1px solid var(--border-color);
            border-radius: 14px; padding: 1.3rem;
        }
        .info-card h4 {
            font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted);
            letter-spacing: 0.5px; margin: 0 0 0.6rem 0; font-weight: 700;
        }
        .info-card p { margin: 0 0 0.3rem 0; font-size: 0.9rem; }

        .modal-abono {
            display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.6); z-index: 9999;
            align-items: center; justify-content: center;
        }
        .modal-abono-content {
            background: var(--bg-card); border: 1px solid var(--border-color);
            border-radius: 16px; width: 100%; max-width: 450px; padding: 1.5rem;
        }
    </style>
</head>
<body>
<div class="app-wrapper">
    <?php include '../../views/partials/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../views/partials/header.php'; ?>
        <div class="page-content">

            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h2>
                        <i class="fas fa-file-contract" style="color:var(--primary); margin-right:0.5rem;"></i>
                        Contrato N° <?= htmlspecialchars($contrato['codigo_completo']) ?>
                    </h2>
                    <p>Emisión: <?= date('d/m/Y H:i', strtotime($contrato['fecha_emision'])) ?> &nbsp;·&nbsp; Sucursal: <strong><?= htmlspecialchars($contrato['local_nombre'] ?? '—') ?></strong></p>
                </div>
                <div style="display:flex; flex-wrap:wrap; gap:0.6rem; align-items:center;">
                    <a href="contratos.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Volver</a>
                    
                    <?php 
                    $phoneC = preg_replace('/[^0-9]/', '', $contrato['cliente_telefono'] ?? '');
                    if (empty($contrato['firma_digital']) && $contrato['estado_contrato'] !== 'Anulado'): 
                        $linkFirma = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . "/carpicenter_sys/modules/contratos/firma_contrato.php?token=" . urlencode($contrato['firma_token'] ?? '');
                        $msgWs = urlencode("Hola *" . $contrato['cliente_nombre'] . "*, aquí tienes el enlace para revisar y firmar digitalmente tu Contrato de Venta N° " . $contrato['codigo_completo'] . " en Industrias Carpicenter:\n\n" . $linkFirma . "\n\n(Puedes firmar directamente con tu dedo en la pantalla de tu celular).");
                        $wsUrlFirma = (strlen($phoneC) >= 9) ? "https://wa.me/" . ((strlen($phoneC) === 9) ? '51' . $phoneC : $phoneC) . "?text=" . $msgWs : "https://api.whatsapp.com/send?text=" . $msgWs;
                    ?>
                        <a href="<?= $wsUrlFirma ?>" target="_blank" class="btn btn-outline" style="color:#059669; border-color:#059669; background:rgba(5,150,105,0.08); font-weight:700;" title="Enviar enlace de firma al WhatsApp del cliente">
                            <i class="fab fa-whatsapp"></i> Enviar Link de Firma
                        </a>
                        <a href="/carpicenter_sys/modules/contratos/firma_contrato.php?token=<?= urlencode($contrato['firma_token'] ?? '') ?>" target="_blank" class="btn btn-outline" style="color:#2563EB; border-color:#2563EB; font-weight:700;" title="Firmar ahora en esta pantalla táctil o con mouse">
                            <i class="fas fa-pen-nib"></i> Firmar en Pantalla
                        </a>
                    <?php elseif (!empty($contrato['firma_digital']) && $contrato['estado_contrato'] !== 'Anulado'): 
                        $linkPdf = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . "/carpicenter_sys/modules/contratos/contrato_print.php?id=" . $contrato['id'];
                        $msgWsPdf = urlencode("Hola *" . $contrato['cliente_nombre'] . "*, aquí tienes la copia digital de tu Contrato N° " . $contrato['codigo_completo'] . " firmado en Industrias Carpicenter:\n\n" . $linkPdf);
                        $wsUrlPdf = (strlen($phoneC) >= 9) ? "https://wa.me/" . ((strlen($phoneC) === 9) ? '51' . $phoneC : $phoneC) . "?text=" . $msgWsPdf : "https://api.whatsapp.com/send?text=" . $msgWsPdf;
                    ?>
                        <a href="<?= $wsUrlPdf ?>" target="_blank" class="btn btn-outline" style="color:#059669; border-color:#059669; background:rgba(5,150,105,0.08); font-weight:700;" title="Enviar PDF firmado al cliente por WhatsApp">
                            <i class="fab fa-whatsapp"></i> Enviar a WhatsApp
                        </a>
                    <?php endif; ?>

                    <?php if ($contrato['estado_contrato'] !== 'Anulado' && $contrato['estado_contrato'] !== 'Entregado'): ?>
                        <a href="contrato_form.php?id=<?= $contrato['id'] ?>" class="btn btn-outline" style="color:#2563EB; border-color:#2563EB; font-weight:700;" title="Editar o agregar más productos a este contrato">
                            <i class="fas fa-pen"></i> Editar Contrato
                        </a>
                    <?php endif; ?>

                    <a href="contrato_print.php?id=<?= $contrato['id'] ?>" target="_blank" class="btn btn-primary">
                        <i class="fas fa-print"></i> Imprimir / PDF
                    </a>
                    <?php if ($is_admin): ?>
                        <?php if ($contrato['estado_contrato'] !== 'Anulado'): ?>
                            <a href="contrato_controller.php?action=anular&id=<?= $contrato['id'] ?>" class="btn btn-outline" style="color:#FFA726; border-color:#FFA726;" onclick="return confirm('¿Estás seguro de ANULAR este contrato? Se liberará el stock reservado.');">
                                <i class="fas fa-ban"></i> Anular
                            </a>
                        <?php endif; ?>
                        <a href="contrato_controller.php?action=delete&id=<?= $contrato['id'] ?>" class="btn btn-outline" style="color:#EF5350; border-color:#EF5350;" onclick="return confirm('⚠️ ¿Estás seguro de ELIMINAR permanentemente este contrato de prueba? Esta acción borra todo el registro.');">
                            <i class="fas fa-trash-alt"></i> Eliminar
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (isset($_GET['msg']) && $_GET['msg'] === 'creado'): ?>
            <div style="background:rgba(46,125,50,0.15); color:#66BB6A; border:1px solid rgba(46,125,50,0.3); padding:1rem; border-radius:10px; margin-bottom:1.5rem;">
                <i class="fas fa-check-circle"></i> Contrato emitido y registrado exitosamente.
            </div>
            <?php endif; ?>

            <?php if (isset($_GET['msg']) && $_GET['msg'] === 'edit_ok'): ?>
            <div style="background:rgba(46,125,50,0.15); color:#66BB6A; border:1px solid rgba(46,125,50,0.3); padding:1rem; border-radius:10px; margin-bottom:1.5rem;">
                <i class="fas fa-check-circle"></i> Contrato actualizado exitosamente. Puedes reenviar el enlace de firma para que el cliente confirme la nueva versión.
            </div>
            <?php endif; ?>

            <?php if (isset($_GET['msg']) && $_GET['msg'] === 'abono_ok'): ?>
            <div style="background:rgba(46,125,50,0.15); color:#66BB6A; border:1px solid rgba(46,125,50,0.3); padding:1rem; border-radius:10px; margin-bottom:1.5rem;">
                <i class="fas fa-check-circle"></i> Abono de saldo registrado correctamente.
            </div>
            <?php endif; ?>

            <!-- 3 Columns Header Cards -->
            <div class="view-grid-3">
                <div class="info-card">
                    <h4><i class="fas fa-user"></i> DATOS DEL CLIENTE</h4>
                    <p><strong><?= htmlspecialchars($contrato['cliente_nombre'] ?? 'Cliente General') ?></strong></p>
                    <p><i class="fas fa-id-card" style="color:var(--text-muted);"></i> Doc: <?= htmlspecialchars($contrato['cliente_doc'] ?: 'Sin documento') ?></p>
                    <p><i class="fab fa-whatsapp" style="color:#66BB6A;"></i> Tel: <?= htmlspecialchars($contrato['cliente_telefono'] ?: 'Sin teléfono') ?></p>
                    <div style="margin-top:8px;">
                        <?php if (!empty($contrato['firma_digital'])): ?>
                            <span style="color:#059669; font-weight:700; font-size:0.75rem; background:rgba(5,150,105,0.1); padding:3px 8px; border-radius:6px; border:1px solid rgba(5,150,105,0.2);"><i class="fas fa-check-circle"></i> Firmado Digitalmente</span>
                        <?php else: ?>
                            <span style="color:#D97706; font-weight:600; font-size:0.75rem; background:rgba(217,119,6,0.1); padding:3px 8px; border-radius:6px; border:1px solid rgba(217,119,6,0.2);"><i class="fas fa-pen-nib"></i> Pendiente de Firma</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="info-card">
                    <h4><i class="fas fa-truck"></i> DATOS DE ENTREGA</h4>
                    <p><strong><?= htmlspecialchars($contrato['tipo_entrega']) ?></strong></p>
                    <p><i class="fas fa-calendar-day" style="color:var(--primary);"></i> F. Estimada: <strong><?= !empty($contrato['fecha_entrega_estimada']) ? date('d/m/Y', strtotime($contrato['fecha_entrega_estimada'])) : 'Sin fecha' ?></strong></p>
                    <?php if ($contrato['tipo_entrega'] === 'Delivery'): ?>
                    <p style="font-size:0.82rem; color:var(--text-muted);"><i class="fas fa-location-dot"></i> Dir: <?= htmlspecialchars($contrato['direccion_entrega'] ?: '—') ?></p>
                    <?php endif; ?>
                </div>

                <div class="info-card">
                    <h4><i class="fas fa-rotate"></i> ESTADO & SEGUIMIENTO</h4>
                    <form action="contrato_controller.php" method="POST" style="margin-bottom:0.5rem;">
                        <input type="hidden" name="action" value="update_estado">
                        <input type="hidden" name="contrato_id" value="<?= $contrato['id'] ?>">
                        <div style="display:flex; gap:0.5rem; align-items:center;">
                            <select name="estado_contrato" class="form-control" style="font-size:0.85rem;" onchange="this.form.submit()">
                                <option value="Pendiente" <?= ($contrato['estado_contrato'] === 'Pendiente') ? 'selected' : '' ?>>Pendiente</option>
                                <option value="En Producción" <?= ($contrato['estado_contrato'] === 'En Producción') ? 'selected' : '' ?>>En Producción</option>
                                <option value="Listo para Entrega" <?= ($contrato['estado_contrato'] === 'Listo para Entrega') ? 'selected' : '' ?>>Listo para Entrega</option>
                                <option value="Entregado" <?= ($contrato['estado_contrato'] === 'Entregado') ? 'selected' : '' ?>>Entregado</option>
                                <option value="Anulado" <?= ($contrato['estado_contrato'] === 'Anulado') ? 'selected' : '' ?>>Anulado</option>
                            </select>
                        </div>
                    </form>
                    <p style="font-size:0.8rem; color:var(--text-muted);">Vendedor: <?= htmlspecialchars($contrato['vendedor_nombre'] ?? 'Sistema') ?></p>
                </div>
            </div>

            <!-- Items Table -->
            <div class="card-panel" style="margin-bottom:1.5rem;">
                <div class="card-header">
                    <h3><i class="fas fa-boxes-stacked" style="color:var(--primary); margin-right:0.5rem;"></i>Muebles & Productos en el Contrato</h3>
                </div>
                <div class="card-body-custom" style="padding:0;">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>CANT.</th>
                                <th>DESCRIPCIÓN / MUEBLE</th>
                                <th>ORIGEN / DESTINO</th>
                                <th>COLOR / ACABADO</th>
                                <th>P. UNITARIO</th>
                                <th>IMPORTE</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($contrato['detalles'] as $det): ?>
                            <tr>
                                <td><strong><?= $det['cantidad'] ?></strong></td>
                                <td>
                                    <?php if (!empty($det['producto_codigo'])): ?>
                                        <span class="doc-badge" style="font-size:0.75rem; margin-right:4px; font-weight:700;"><?= htmlspecialchars($det['producto_codigo']) ?></span>
                                    <?php endif; ?>
                                    <strong><?= htmlspecialchars($det['descripcion']) ?></strong>
                                    <?php if (!empty($det['observaciones_item'])): ?>
                                    <br><small style="color:var(--text-muted);"><i class="fas fa-info-circle"></i> <?= htmlspecialchars($det['observaciones_item']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $orig = $det['origen_item'] ?? 'Producción';
                                    if ($orig === 'Stock') {
                                        echo '<span class="badge badge-success" style="font-size:0.75rem;"><i class="fas fa-boxes"></i> Stock en Inventario</span>';
                                    } elseif ($orig === 'Proveedor') {
                                        echo '<span class="badge badge-warning" style="font-size:0.75rem; background:#EF6C00; color:#fff;"><i class="fas fa-truck-field"></i> Compra a Proveedor</span>';
                                    } else {
                                        echo '<span class="badge badge-info" style="font-size:0.75rem;"><i class="fas fa-hammer"></i> Fabricación (Almacén Principal)</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($det['color_nombre'] ?? '—') ?>
                                    <?php if (!empty($det['color_codigo'])): ?>
                                        <span class="doc-badge" style="font-size:0.68rem; margin-left:4px; font-weight:700;"><?= htmlspecialchars($det['color_codigo']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= formatearMonto($det['precio_unitario']) ?></td>
                                <td><strong><?= formatearMonto($det['subtotal']) ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Financial Summary & Payments History -->
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem;">
                <!-- Balances Card -->
                <div class="card-panel">
                    <div class="card-header">
                        <h3><i class="fas fa-wallet" style="color:var(--primary); margin-right:0.5rem;"></i>Resumen de Estado Financiero</h3>
                    </div>
                    <div class="card-body-custom" style="padding:1.5rem;">
                        <?php if (floatval($contrato['costo_movilidad'] ?? 0) > 0): ?>
                        <div style="display:flex; justify-content:space-between; padding:0.4rem 0; border-bottom:1px dashed var(--border-color); font-size:0.9rem;">
                            <span>SUBTOTAL PRODUCTOS:</span>
                            <span><?= formatearMonto(floatval($contrato['monto_total']) - floatval($contrato['costo_movilidad'])) ?></span>
                        </div>
                        <div style="display:flex; justify-content:space-between; padding:0.4rem 0; border-bottom:1px dashed var(--border-color); font-size:0.9rem;">
                            <span>MOVILIDAD / FLETE:</span>
                            <span style="font-weight:bold; color:var(--primary);"><?= formatearMonto($contrato['costo_movilidad']) ?></span>
                        </div>
                        <?php endif; ?>
                        <div style="display:flex; justify-content:space-between; padding:0.6rem 0; border-bottom:1px solid var(--border-color);">
                            <span>TOTAL CONTRATO:</span>
                            <strong style="font-size:1.1rem;"><?= formatearMonto($contrato['monto_total']) ?></strong>
                        </div>
                        <div style="display:flex; justify-content:space-between; padding:0.6rem 0; border-bottom:1px solid var(--border-color); color:#66BB6A;">
                            <span>A CUENTA (ADELANTOS):</span>
                            <strong style="font-size:1.1rem;"><?= formatearMonto($contrato['monto_adelanto']) ?></strong>
                        </div>
                        <div style="display:flex; justify-content:space-between; padding:0.8rem 0; font-size:1.2rem; font-weight:800; color:<?= floatval($contrato['monto_saldo']) > 0 ? '#EF5350' : '#66BB6A' ?>;">
                            <span>SALDO PENDIENTE:</span>
                            <span><?= formatearMonto($contrato['monto_saldo']) ?></span>
                        </div>

                        <?php if (floatval($contrato['monto_saldo']) > 0 && $contrato['estado_contrato'] !== 'Anulado'): ?>
                        <div style="margin-top:1.2rem; text-align:right;">
                            <button type="button" class="btn btn-success" onclick="openAbonoModal()">
                                <i class="fas fa-hand-holding-dollar"></i> Registrar Abono de Saldo
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Abonos History -->
                <div class="card-panel">
                    <div class="card-header">
                        <h3><i class="fas fa-receipt" style="color:var(--primary); margin-right:0.5rem;"></i>Historial de Pagos / Abonos</h3>
                    </div>
                    <div class="card-body-custom" style="padding:0;">
                        <table class="table-custom">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Monto</th>
                                    <th>Método</th>
                                    <th>Detalle</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($contrato['abonos'] as $ab): ?>
                                <tr>
                                    <td><?= date('d/m/Y H:i', strtotime($ab['fecha'])) ?></td>
                                    <td><strong style="color:#66BB6A;">S/ <?= number_format($ab['monto'], 2) ?></strong></td>
                                    <td><?= htmlspecialchars($ab['metodo_pago']) ?></td>
                                    <td><small style="color:var(--text-muted);"><?= htmlspecialchars($ab['observacion']) ?></small></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($contrato['abonos'])): ?>
                                <tr><td colspan="4" style="text-align:center; color:var(--text-muted); padding:2rem;">Sin abonos registrados.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Modal Registrar Abono -->
<div class="modal-abono" id="modalAbono">
    <div class="modal-abono-content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
            <h3 style="margin:0; font-size:1.1rem;"><i class="fas fa-hand-holding-dollar" style="color:#66BB6A;"></i> Registrar Abono a Cuenta</h3>
            <button type="button" class="btn-icon" onclick="closeAbonoModal()"><i class="fas fa-times"></i></button>
        </div>
        <form action="contrato_controller.php" method="POST">
            <input type="hidden" name="action" value="add_abono">
            <input type="hidden" name="contrato_id" value="<?= $contrato['id'] ?>">

            <div style="margin-bottom:1rem;">
                <label>Saldo Actual Pendiente: <strong style="color:#EF5350;">S/ <?= number_format($contrato['monto_saldo'], 2) ?></strong></label>
            </div>

            <div style="margin-bottom:1rem;">
                <label>Monto a Abonar (S/) <span style="color:red">*</span></label>
                <input type="number" step="0.01" min="0.1" max="<?= $contrato['monto_saldo'] ?>" name="monto" class="form-control" value="<?= $contrato['monto_saldo'] ?>" required style="font-size:1.1rem; font-weight:700;">
            </div>

            <div style="margin-bottom:1rem;">
                <label>Método de Pago <span style="color:red">*</span></label>
                <select name="metodo_pago" class="form-control" required>
                    <option value="BBVA">BBVA</option>
                    <option value="BCP">BCP</option>
                    <option value="Interbank">Interbank</option>
                    <option value="Plin">Plin</option>
                    <option value="Yape">Yape</option>
                    <option value="Efectivo" selected>Efectivo</option>
                    <option value="Mastercard">Mastercard</option>
                    <option value="Visa">Visa</option>
                    <option value="Otros Bancos">Otros Bancos</option>
                </select>
            </div>

            <div style="margin-bottom:1.5rem;">
                <label>Observación / Comprobante</label>
                <input type="text" name="observacion" class="form-control" placeholder="Ej: Pago final contra entrega / Operación #1234">
            </div>

            <div style="text-align:right;">
                <button type="button" class="btn btn-outline" onclick="closeAbonoModal()">Cancelar</button>
                <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Guardar Abono</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openAbonoModal() {
        document.getElementById('modalAbono').style.display = 'flex';
    }
    function closeAbonoModal() {
        document.getElementById('modalAbono').style.display = 'none';
    }
</script>
</body>
</html>
