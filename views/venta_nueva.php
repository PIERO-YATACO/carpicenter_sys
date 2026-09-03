<?php
require_once __DIR__ . '/../auth/check_auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../modules/cotizaciones/cotizacion_model.php';

$cotizacion_id = $_GET['cotizacion_id'] ?? null;
$cotizacion = null;
$detalles = [];

if ($cotizacion_id) {
    $cotModel = new CotizacionModel($db);
    $cotizacion = $cotModel->getById($cotizacion_id);
    if ($cotizacion) {
        $detalles = $cotizacion['detalles'];
    }
}

$page_title = 'Registrar Venta / Comprobante';
$page_subtitle = 'Generar facturas, boletas o comprobantes de venta';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - Carpicenter</title>
    <link rel="stylesheet" href="/carpicenter_sys/assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .form-row { display: flex; gap: 1rem; margin-bottom: 1rem; }
        .form-row > * { flex: 1; }
        .table-container {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 1.5rem;
        }
        .table-container table {
            width: 100%;
            border-collapse: collapse;
        }
        .table-container th, .table-container td {
            padding: 0.8rem 1rem;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.88rem;
        }
        .table-container th {
            background-color: var(--bg-secondary);
            text-align: left;
            color: var(--text-secondary);
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
        }
        .readonly-value {
            padding: 0.7rem 1rem;
            background: rgba(255,255,255,0.03);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
<div class="app-wrapper">
    <?php include 'partials/sidebar.php'; ?>
    <div class="main-content">
        <?php include 'partials/header.php'; ?>
        <div class="page-content">
            <div class="page-header" style="margin-bottom: 1.5rem;">
                <div>
                    <h2>Registrar Venta</h2>
                    <p><?= $cotizacion ? "Generando comprobante desde Cotización {$cotizacion['numero']}" : "Registrar nueva venta directa" ?></p>
                </div>
                <a href="/carpicenter_sys/modules/cotizaciones/cotizaciones.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Volver</a>
            </div>

            <form action="venta_nueva_process.php" method="POST" id="ventaForm">
                <?php if ($cotizacion_id): ?>
                <input type="hidden" name="cotizacion_id" value="<?= $cotizacion_id ?>">
                <?php endif; ?>

                <div class="grid-2-1">
                    <!-- Columna Izquierda: Datos del Cliente y Detalles del Comprobante -->
                    <div style="display:flex; flex-direction:column; gap:1.5rem;">
                        
                        <!-- Datos del Comprobante -->
                        <div class="card-panel">
                            <div class="card-header">
                                <h3><i class="fas fa-file-invoice-dollar" style="color:var(--primary);margin-right:0.5rem;"></i>Datos del Comprobante</h3>
                            </div>
                            <div class="card-body-custom" style="padding: 1.5rem;">
                                <div class="form-row">
                                    <div>
                                        <label>Tipo de Comprobante <span style="color:red">*</span></label>
                                        <select name="tipo_comprobante" id="tipo_comprobante" class="form-control" onchange="handleTipoChange()" required>
                                            <option value="FACTURA">FACTURA</option>
                                            <option value="BOLETA">BOLETA</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label>Serie <span style="color:red">*</span></label>
                                        <select name="serie" id="serie" class="form-control" onchange="fetchNextNumber()" required>
                                            <!-- Dyn populated -->
                                        </select>
                                    </div>
                                    <div>
                                        <label>Número Correlativo</label>
                                        <input type="text" name="numero" id="numero" class="form-control" readonly style="font-weight:bold; letter-spacing:1px; color:var(--primary-light);">
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div>
                                        <label>Fecha de Emisión <span style="color:red">*</span></label>
                                        <input type="date" name="fecha_emision" class="form-control" value="<?= date('Y-m-d') ?>" required>
                                    </div>
                                    <div>
                                        <label>Fecha de Pago (Opcional)</label>
                                        <input type="date" name="fecha_pago" class="form-control">
                                    </div>
                                    <div>
                                        <label>Estado de Pago <span style="color:red">*</span></label>
                                        <select name="estado_pago" class="form-control" required>
                                            <option value="PENDIENTE" selected>PENDIENTE</option>
                                            <option value="PAGADO">PAGADO</option>
                                            <option value="VENCIDO">VENCIDO</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Datos del Cliente -->
                        <div class="card-panel">
                            <div class="card-header">
                                <h3><i class="fas fa-user" style="color:var(--primary);margin-right:0.5rem;"></i>Datos del Cliente</h3>
                            </div>
                            <div class="card-body-custom" style="padding: 1.5rem;">
                                <div class="form-row">
                                    <div>
                                        <label>Nombre / Razón Social <span style="color:red">*</span></label>
                                        <input type="text" name="cliente_nombre" class="form-control" value="<?= $cotizacion ? htmlspecialchars($cotizacion['cliente_nombre']) : '' ?>" required>
                                    </div>
                                    <div>
                                        <label>DNI / RUC <span style="color:red">*</span></label>
                                        <input type="text" name="cliente_documento" class="form-control" value="<?= $cotizacion ? htmlspecialchars($cotizacion['cliente_documento']) : '' ?>" required>
                                    </div>
                                </div>
                                <div class="form-group" style="margin-bottom:0;">
                                    <label>Dirección</label>
                                    <input type="text" name="cliente_direccion" class="form-control" value="<?= $cotizacion ? htmlspecialchars($cotizacion['cliente_direccion']) : '' ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Detalle de Productos -->
                        <div class="card-panel">
                            <div class="card-header">
                                <h3><i class="fas fa-boxes" style="color:var(--primary);margin-right:0.5rem;"></i>Productos / Detalles</h3>
                            </div>
                            <div class="card-body-custom" style="padding: 0;">
                                <div class="table-container">
                                    <table id="productosVenta">
                                        <thead>
                                            <tr>
                                                <th style="width: 10%;">Cant</th>
                                                <th>Descripción / Producto</th>
                                                <th style="width: 20%; text-align:right;">P. Unitario</th>
                                                <th style="width: 20%; text-align:right;">Importe</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($detalles)): ?>
                                                <?php foreach ($detalles as $i => $det): ?>
                                                    <tr>
                                                        <td>
                                                            <input type="hidden" name="productos[<?= $i ?>][producto_id]" value="<?= $det['producto_id'] ?>">
                                                            <input type="hidden" name="productos[<?= $i ?>][cantidad]" value="<?= $det['cantidad'] ?>">
                                                            <?= $det['cantidad'] ?>
                                                        </td>
                                                        <td>
                                                            <input type="hidden" name="productos[<?= $i ?>][descripcion]" value="<?= htmlspecialchars($det['descripcion']) ?>">
                                                            <strong><?= htmlspecialchars($det['descripcion']) ?></strong>
                                                            <?php if (!empty($det['especificaciones'])): ?>
                                                                <div style="font-size:0.8rem; color:var(--text-muted); margin-top:2px;">
                                                                    <?= htmlspecialchars($det['especificaciones']) ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td style="text-align:right;">
                                                            <input type="hidden" name="productos[<?= $i ?>][precio_unitario]" value="<?= $det['precio_unitario'] ?>">
                                                            S/ <?= number_format($det['precio_unitario'], 2) ?>
                                                        </td>
                                                        <td style="text-align:right; font-weight:bold;">
                                                            S/ <?= number_format($det['subtotal'], 2) ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="4" style="text-align:center; color:var(--text-muted);">Sin productos. La venta debe originarse desde una cotización válida.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Columna Derecha: Totales y SUNAT Info -->
                    <div style="display:flex; flex-direction:column; gap:1.5rem;">
                        
                        <!-- Panel de Totales -->
                        <div class="card-panel">
                            <div class="card-header">
                                <h3>Resumen Financiero</h3>
                            </div>
                            <div class="card-body-custom" style="padding: 1.5rem; display:flex; flex-direction:column; gap:1rem;">
                                <div style="display:flex; justify-content:space-between; font-size:0.95rem;">
                                    <span>Subtotal (Sin IGV):</span>
                                    <span>S/ <?= number_format(($cotizacion ? $cotizacion['total'] / 1.18 : 0), 2) ?></span>
                                </div>
                                <div style="display:flex; justify-content:space-between; font-size:0.95rem;">
                                    <span>IGV (18%):</span>
                                    <span>S/ <?= number_format(($cotizacion ? $cotizacion['total'] - ($cotizacion['total'] / 1.18) : 0), 2) ?></span>
                                </div>
                                <hr style="border:0; border-top:1px solid var(--border-color);">
                                <div style="display:flex; justify-content:space-between; font-weight:bold; font-size:1.4rem; color:var(--text-primary);">
                                    <span>TOTAL:</span>
                                    <span>S/ <?= number_format(($cotizacion ? $cotizacion['total'] : 0), 2) ?></span>
                                </div>
                                <input type="hidden" name="total" value="<?= $cotizacion ? $cotizacion['total'] : 0 ?>">

                                <div style="margin-top:1.5rem;">
                                    <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; padding:0.8rem; font-size:1rem;" <?= empty($detalles) ? 'disabled' : '' ?>>
                                        <i class="fas fa-check-circle"></i> Confirmar Venta
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Preparación SUNAT -->
                        <div class="card-panel">
                            <div class="card-header">
                                <h3><i class="fas fa-university" style="color:var(--primary);margin-right:0.5rem;"></i>Integración SUNAT</h3>
                            </div>
                            <div class="card-body-custom" style="padding:1.2rem; font-size:0.85rem; line-height:1.5;">
                                <p style="margin-bottom:0.8rem; color:var(--text-secondary);">
                                    Este comprobante se guardará localmente en estado de facturación interna.
                                </p>
                                <div style="display:flex; align-items:center; gap:0.5rem; background:rgba(255,255,255,0.03); border:1px solid var(--border-color); padding:0.6rem; border-radius:8px; margin-bottom:0.8rem;">
                                    <span class="badge badge-warning" style="font-size:0.65rem;">NO_ENVIADO</span>
                                    <span style="color:var(--text-muted);">Estado SUNAT Inicial</span>
                                </div>
                                <p style="color:var(--text-muted); font-size:0.78rem;">
                                    La comunicación externa y envío del ticket XML/CDR se habilitará en la siguiente fase de desarrollo.
                                </p>
                            </div>
                        </div>

                    </div>
                </div>

            </form>
        </div>
    </div>
</div>
<?php include 'partials/footer.php'; ?>

<script>
    function handleTipoChange() {
        const tipo = document.getElementById('tipo_comprobante').value;
        const serieSelect = document.getElementById('serie');
        
        // Limpiar
        serieSelect.innerHTML = '';
        
        if (tipo === 'FACTURA') {
            const opt = document.createElement('option');
            opt.value = 'F001';
            opt.text = 'F001 (Factura)';
            serieSelect.add(opt);
        } else if (tipo === 'BOLETA') {
            const opt = document.createElement('option');
            opt.value = 'B001';
            opt.text = 'B001 (Boleta)';
            serieSelect.add(opt);
        }
        
        fetchNextNumber();
    }

    function fetchNextNumber() {
        const tipo = document.getElementById('tipo_comprobante').value;
        const serie = document.getElementById('serie').value;
        const numInput = document.getElementById('numero');
        
        if (!tipo || !serie) return;
        
        fetch(`/carpicenter_sys/views/get_next_number.php?tipo=${tipo}&serie=${serie}`)
            .then(res => res.json())
            .then(data => {
                if (data.next_number) {
                    numInput.value = data.next_number;
                } else {
                    console.error(data.error);
                }
            })
            .catch(err => console.error("Error al obtener número correlativo:", err));
    }

    window.onload = () => {
        handleTipoChange();
    };
</script>
</body>
</html>
