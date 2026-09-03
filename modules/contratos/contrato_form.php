<?php
require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/contrato_model.php';

$model = new ContratoModel($db);

// Fetch clients
$clientes = $db->query("SELECT id, nombre, dni_ruc, telefono, direccion FROM clientes ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch products & colors for dropdowns
$productos = $db->query("SELECT id, nombre, codigo, precio_venta FROM productos ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
$colores = $db->query("SELECT id, nombre, codigo FROM colores ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
$locales = $db->query("SELECT id, nombre FROM locales ORDER BY tipo DESC, nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

$nextNumero = $model->generateNumero('T003');

$edit_id = $_GET['id'] ?? null;
$contrato = null;
$cotizacion_id = $_GET['cotizacion_id'] ?? null;
$cotizacion = null;
$items_to_render = [];

if ($edit_id) {
    $contrato = $model->getById($edit_id);
    if (!$contrato) die("Contrato no encontrado.");
    $page_title = 'Editar Contrato N° ' . $contrato['codigo_completo'];

    if (!empty($contrato['detalles'])) {
        foreach ($contrato['detalles'] as $d) {
            $items_to_render[] = [
                'producto_id' => $d['producto_id'] ?? '',
                'descripcion' => $d['descripcion'] ?? '',
                'color_id' => $d['color_id'] ?? '',
                'color_custom' => '',
                'cantidad' => $d['cantidad'] ?? 1,
                'precio_unitario' => $d['precio_unitario'] ?? 0,
                'observaciones_item' => $d['observaciones_item'] ?? '',
                'origen_item' => $d['origen_item'] ?? 'Producción'
            ];
        }
    }
} elseif ($cotizacion_id) {
    $stmtCot = $db->prepare("SELECT * FROM cotizaciones WHERE id = ?");
    $stmtCot->execute([$cotizacion_id]);
    $cotizacion = $stmtCot->fetch(PDO::FETCH_ASSOC);

    if ($cotizacion) {
        $stmtDet = $db->prepare("SELECT * FROM cotizacion_detalle WHERE cotizacion_id = ?");
        $stmtDet->execute([$cotizacion_id]);
        $cotizacion_detalles = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

        foreach ($cotizacion_detalles as $d) {
            $items_to_render[] = [
                'producto_id' => $d['producto_id'] ?? '',
                'descripcion' => $d['descripcion'] ?? '',
                'color_id' => $d['color_id'] ?? '',
                'color_custom' => $d['color'] ?? '',
                'cantidad' => $d['cantidad'] ?? 1,
                'precio_unitario' => $d['precio_unitario'] ?? 0,
                'observaciones_item' => $d['especificaciones'] ?? '',
                'origen_item' => 'Producción'
            ];
        }
    }
    $page_title = 'Nuevo Contrato de Venta';
} else {
    $page_title = 'Nuevo Contrato de Venta';
}

// Pre-fill values
$serie = $contrato['serie'] ?? 'T003';
$numero = $contrato['numero'] ?? $nextNumero;
$local_id_sel = $contrato['local_id'] ?? ($user_local_id ?? 1);
$cliente_id_val = $contrato['cliente_id'] ?? '';
$cliente_nombre_val = $contrato['cliente_nombre'] ?? ($cotizacion['cliente_nombre'] ?? '');
$cliente_doc_val = $contrato['cliente_doc'] ?? ($cotizacion['cliente_documento'] ?? '');
$cliente_tel_val = $contrato['cliente_telefono'] ?? ($cotizacion['cliente_telefono'] ?? '');
$cliente_dir_val = $contrato['direccion_entrega'] ?? ($contrato['cliente_direccion_base'] ?? ($cotizacion['cliente_direccion'] ?? ''));

$tipo_entrega_val = $contrato['tipo_entrega'] ?? 'Tienda 1';
$fecha_entrega_val = !empty($contrato['fecha_entrega_estimada']) ? date('Y-m-d', strtotime($contrato['fecha_entrega_estimada'])) : date('Y-m-d', strtotime('+7 days'));
$dir_entrega_val = $contrato['direccion_entrega'] ?? '';
$ref_entrega_val = $contrato['referencia_entrega'] ?? '';
$costo_movilidad_val = $contrato['costo_movilidad'] ?? ($cotizacion['movilidad'] ?? 0);

$monto_adelanto_val = $contrato['monto_adelanto'] ?? '';
$observaciones_gen_val = $contrato['observaciones_generales'] ?? ($cotizacion['observaciones'] ?? '');
$metodos_pago_sel = !empty($contrato['metodo_pago']) ? array_map('trim', explode(',', $contrato['metodo_pago'])) : ['Efectivo'];
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
        .form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1.1rem; }
        .form-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.1rem; }
        .form-grid-4 { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 1.1rem; }
        
        .form-section-card {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 12px;
            padding: 1.3rem 1.5rem;
            margin-bottom: 1.3rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }

        .section-header-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: #1E293B;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.1rem;
            border-bottom: 1px solid #F1F5F9;
            padding-bottom: 0.6rem;
        }
        .section-header-title span.title-text {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #1E293B;
        }
        .section-header-title span.title-badge {
            font-size: 0.75rem;
            font-weight: 600;
            color: #64748B;
            background: #F1F5F9;
            padding: 2px 8px;
            border-radius: 6px;
        }

        .form-group-clean {
            margin-bottom: 0.85rem;
        }
        .form-group-clean label {
            display: block;
            font-size: 0.78rem;
            font-weight: 700;
            color: #475569;
            margin-bottom: 0.35rem;
            letter-spacing: 0.3px;
        }
        .req-star { color: #DC2626; margin-left: 2px; }

        /* Item Row Grid */
        .item-row-card {
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 10px;
            padding: 1rem 1.2rem;
            margin-bottom: 0.85rem;
            position: relative;
            transition: all 0.2s ease;
        }
        .item-row-card:hover {
            border-color: #CBD5E1;
            background: #FFFFFF;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }
        .item-grid-top {
            display: grid;
            grid-template-columns: 2.2fr 1.4fr 0.8fr 1fr 1fr auto;
            gap: 0.85rem;
            align-items: flex-start;
        }
        @media (max-width: 992px) {
            .item-grid-top {
                grid-template-columns: 1fr;
            }
            .form-grid-2, .form-grid-3, .form-grid-4 {
                grid-template-columns: 1fr;
            }
        }

        .btn-remove-row {
            background: #FEF2F2;
            color: #DC2626;
            border: 1px solid #FECACA;
            border-radius: 8px;
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.15s ease;
            margin-top: 1.4rem;
        }
        .btn-remove-row:hover {
            background: #DC2626;
            color: #FFFFFF;
        }

        /* Financial summary card */
        .summary-box {
            background: #FFFFFF;
            border: 1.5px solid #E2E8F0;
            border-radius: 12px;
            padding: 1.3rem 1.5rem;
            margin-top: 1.2rem;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.45rem 0;
            font-size: 0.9rem;
        }
        .summary-row.total-row {
            border-top: 1.5px solid #CBD5E1;
            border-bottom: 1.5px solid #CBD5E1;
            padding: 0.75rem 0;
            font-size: 1.15rem;
            font-weight: 800;
        }

        .payment-pills-container {
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem;
            margin-top: 0.35rem;
        }
        .payment-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            background: #F1F5F9;
            border: 1px solid #E2E8F0;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            color: #334155;
            cursor: pointer;
            transition: all 0.15s ease;
            user-select: none;
        }
        .payment-pill input[type="checkbox"] {
            margin: 0;
            cursor: pointer;
        }
    </style>
</head>
<body>
<div class="app-wrapper">
    <?php include '../../views/partials/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../views/partials/header.php'; ?>
        <div class="page-content">

            <!-- Header -->
            <div class="page-header" style="margin-bottom:1.3rem;">
                <div>
                    <h2><i class="fas fa-file-signature" style="color:var(--primary); margin-right:0.5rem;"></i> <?= $page_title ?></h2>
                    <p><?= $edit_id ? 'Modifica los productos, colores, flete o pagos del contrato' : 'Emisión directa de talonario de venta y contratos a pedido para clientes' ?></p>
                </div>
                <a href="<?= $edit_id ? 'contrato_view.php?id='.$edit_id : 'contratos.php' ?>" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>
            </div>

            <form action="contrato_controller.php" method="POST" id="formContrato" onsubmit="return validateContratoForm()">
                <input type="hidden" name="action" value="<?= $edit_id ? 'edit' : 'create' ?>">
                <?php if ($edit_id): ?>
                    <input type="hidden" name="contrato_id" value="<?= $contrato['id'] ?>">
                <?php endif; ?>
                <?php if ($cotizacion_id): ?>
                    <input type="hidden" name="cotizacion_id" value="<?= htmlspecialchars($cotizacion_id) ?>">
                <?php endif; ?>

                <!-- Block 1: Datos Principales del Talonario -->
                <div class="form-section-card">
                    <div class="section-header-title">
                        <span class="title-text"><i class="fas fa-receipt" style="color:#2563EB;"></i> 1. Identificación del Contrato</span>
                        <span class="title-badge">Serie & Sucursal</span>
                    </div>
                    <div class="form-grid-3">
                        <div class="form-group-clean">
                            <label>Serie de Talonario <span class="req-star">*</span></label>
                            <input type="text" name="serie" id="serieInput" class="form-control" value="<?= htmlspecialchars($serie) ?>" <?= $edit_id ? 'readonly' : 'required' ?>>
                        </div>
                        <div class="form-group-clean">
                            <label>N° Correlativo de Contrato <span class="req-star">*</span></label>
                            <input type="text" name="numero" id="numeroInput" class="form-control" value="<?= htmlspecialchars($numero) ?>" readonly style="font-weight:700; color:#0F172A;">
                        </div>
                        <div class="form-group-clean">
                            <label>Tienda / Sucursal Emisora <span class="req-star">*</span></label>
                            <select name="local_id" class="form-control" required>
                                <?php foreach ($locales as $loc): ?>
                                <option value="<?= $loc['id'] ?>" <?= ($local_id_sel == $loc['id']) ? 'selected' : '' ?>><?= htmlspecialchars($loc['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Block 2: Datos del Cliente -->
                <div class="form-section-card">
                    <div class="section-header-title">
                        <span class="title-text"><i class="fas fa-user-tag" style="color:#059669;"></i> 2. Datos del Cliente</span>
                        <span class="title-badge">Obligatorio</span>
                    </div>
                    
                    <?php if (!$edit_id): ?>
                    <div class="form-group-clean" style="margin-bottom:1rem;">
                        <label>Buscar Cliente Registrado (Opcional)</label>
                        <select id="selectClienteExistente" class="form-control" onchange="fillClienteData(this.value)">
                            <option value="">-- Seleccionar cliente registrado --</option>
                            <?php foreach ($clientes as $cli): ?>
                            <option value="<?= $cli['id'] ?>" 
                                    data-nombre="<?= htmlspecialchars($cli['nombre']) ?>"
                                    data-doc="<?= htmlspecialchars($cli['dni_ruc'] ?? '') ?>"
                                    data-tel="<?= htmlspecialchars($cli['telefono'] ?? '') ?>"
                                    data-dir="<?= htmlspecialchars($cli['direccion'] ?? '') ?>">
                                <?= htmlspecialchars($cli['nombre']) ?> <?= $cli['dni_ruc'] ? '('.$cli['dni_ruc'].')' : '' ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <input type="hidden" name="cliente_id" id="cliente_id" value="<?= htmlspecialchars($cliente_id_val) ?>">
                    
                    <div class="form-grid-3" style="margin-bottom:1rem;">
                        <div class="form-group-clean">
                            <label>Nombre / Razón Social <span class="req-star">*</span></label>
                            <input type="text" name="cliente_nombre" id="cli_nombre" class="form-control" value="<?= htmlspecialchars($cliente_nombre_val) ?>" required>
                        </div>
                        <div class="form-group-clean">
                            <label>DNI / RUC <span class="req-star">*</span></label>
                            <input type="text" name="cliente_doc" id="cli_doc" class="form-control" maxlength="11" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11);" value="<?= htmlspecialchars($cliente_doc_val) ?>" required>
                        </div>
                        <div class="form-group-clean">
                            <label>Teléfono / Celular <span class="req-star">*</span></label>
                            <input type="text" name="cliente_telefono" id="cli_tel" class="form-control" maxlength="9" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 9);" value="<?= htmlspecialchars($cliente_tel_val) ?>" required>
                        </div>
                    </div>

                    <div class="form-group-clean">
                        <label>Dirección del Cliente <span class="req-star">*</span></label>
                        <input type="text" name="cliente_direccion" id="cli_dir" class="form-control" value="<?= htmlspecialchars($cliente_dir_val) ?>" required>
                    </div>
                </div>

                <!-- Block 3: Entrega & Despacho -->
                <div class="form-section-card">
                    <div class="section-header-title">
                        <span class="title-text"><i class="fas fa-truck" style="color:#D97706;"></i> 3. Información de Entrega & Despacho</span>
                    </div>
                    <div class="form-grid-2" style="margin-bottom:1rem;">
                        <div class="form-group-clean">
                            <label>Método de Entrega <span class="req-star">*</span></label>
                            <select name="tipo_entrega" id="tipoEntrega" class="form-control" onchange="toggleDeliveryFields()" required>
                                <option value="Tienda 1" <?= ($tipo_entrega_val === 'Tienda 1') ? 'selected' : '' ?>>Tienda 1</option>
                                <option value="Tienda 2" <?= ($tipo_entrega_val === 'Tienda 2') ? 'selected' : '' ?>>Tienda 2</option>
                                <option value="Tienda 3" <?= ($tipo_entrega_val === 'Tienda 3') ? 'selected' : '' ?>>Tienda 3</option>
                                <option value="Tienda 4" <?= ($tipo_entrega_val === 'Tienda 4') ? 'selected' : '' ?>>Tienda 4</option>
                                <option value="Ruta Programada" <?= ($tipo_entrega_val === 'Ruta Programada') ? 'selected' : '' ?>>Ruta Programada</option>
                                <option value="Delivery" <?= ($tipo_entrega_val === 'Delivery') ? 'selected' : '' ?>>Delivery (Envío a Domicilio)</option>
                                <option value="Provincia - Agencia Marvisur" <?= ($tipo_entrega_val === 'Provincia - Agencia Marvisur') ? 'selected' : '' ?>>Provincia (Agencia Marvisur)</option>
                                <option value="Provincia - Agencia Shalom" <?= ($tipo_entrega_val === 'Provincia - Agencia Shalom') ? 'selected' : '' ?>>Provincia (Agencia Shalom)</option>
                            </select>
                        </div>
                        <div class="form-group-clean">
                            <label>Fecha Estimada de Entrega <span class="req-star">*</span></label>
                            <input type="date" name="fecha_entrega_estimada" class="form-control" value="<?= htmlspecialchars($fecha_entrega_val) ?>" required>
                        </div>
                    </div>

                    <div id="deliveryFieldsBlock" style="display:none;" class="form-grid-3">
                        <div class="form-group-clean">
                            <label>Dirección de Entrega / Agencia</label>
                            <input type="text" name="direccion_entrega" id="dir_entrega" class="form-control" value="<?= htmlspecialchars($dir_entrega_val) ?>">
                        </div>
                        <div class="form-group-clean">
                            <label>Referencia de Ubicación / Destino</label>
                            <input type="text" name="referencia_entrega" class="form-control" value="<?= htmlspecialchars($ref_entrega_val) ?>">
                        </div>
                        <div class="form-group-clean">
                            <label>Costo Movilidad / Flete S/</label>
                            <input type="number" step="0.01" min="0" name="costo_movilidad" id="costoMovilidadInput" class="form-control" placeholder="0.00" oninput="calculateTotals()" value="<?= htmlspecialchars($costo_movilidad_val) ?>" style="font-weight:700; color:#0F172A;">
                        </div>
                    </div>
                </div>

                <!-- Block 4: Detalle de Productos del Contrato -->
                <div class="form-section-card">
                    <div class="section-header-title">
                        <span class="title-text"><i class="fas fa-boxes-stacked" style="color:#4F46E5;"></i> 4. Detalle de Muebles & Pedidos</span>
                        <button type="button" class="btn btn-outline" onclick="addItemRow()" style="padding:0.4rem 0.85rem; font-size:0.8rem; border-radius:8px;">
                            <i class="fas fa-plus" style="margin-right:4px;"></i> Agregar Mueble
                        </button>
                    </div>
                    <div id="itemsContainer">
                        <!-- Rows injected by JS -->
                    </div>
                </div>

                <!-- Block 5: Resumen Financiero & Adelanto Obligatorio -->
                <div class="summary-box">
                    <div class="section-header-title">
                        <span class="title-text"><i class="fas fa-coins" style="color:#059669;"></i> 5. Resumen Financiero & Abono a Cuenta</span>
                    </div>
                    
                    <div class="form-group-clean" style="margin-bottom:1.1rem;">
                        <label>Método de Pago del Abono <span class="req-star">*</span></label>
                        <div class="payment-pills-container">
                            <?php 
                            $metodosLista = ['Efectivo', 'BCP', 'BBVA', 'Yape', 'Plin', 'Interbank', 'Visa', 'Mastercard', 'Otros Bancos'];
                            foreach ($metodosLista as $m): 
                            ?>
                            <label class="payment-pill">
                                <input type="checkbox" name="metodos_pago[]" value="<?= $m ?>" <?= in_array($m, $metodos_pago_sel) ? 'checked' : '' ?>> <?= $m ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="form-grid-2" style="margin-bottom:1.2rem;">
                        <div class="form-group-clean">
                            <label>Monto Adelanto (A CUENTA S/) <span class="req-star">*</span></label>
                            <input type="number" step="0.01" min="0.01" name="monto_adelanto" id="montoAdelantoInput" class="form-control" placeholder="0.00" oninput="calculateTotals()" value="<?= htmlspecialchars($monto_adelanto_val) ?>" style="font-size:1.1rem; font-weight:700; color:#059669;" required>
                            <small style="font-size:0.75rem; color:#64748B;">Monto entregado por el cliente</small>
                        </div>
                        <div class="form-group-clean">
                            <label>Observaciones / Especificaciones Generales</label>
                            <input type="text" name="observaciones_generales" class="form-control" value="<?= htmlspecialchars($observaciones_gen_val) ?>">
                        </div>
                    </div>

                    <div style="max-width:380px; margin-left:auto; background:#F8FAFC; padding:1.1rem 1.3rem; border-radius:10px; border:1px solid #E2E8F0;">
                        <div class="summary-row">
                            <span>Subtotal Muebles:</span>
                            <span id="lblSubtotalProds" style="font-weight:600; color:#1E293B;">S/ 0.00</span>
                        </div>
                        <div class="summary-row">
                            <span>Movilidad / Flete:</span>
                            <span id="lblFlete" style="font-weight:600; color:#1E293B;">S/ 0.00</span>
                        </div>
                        <div class="summary-row total-row">
                            <span>TOTAL CONTRATO:</span>
                            <strong id="lblTotalContract" style="color:#0F172A;">S/ 0.00</strong>
                            <input type="hidden" name="monto_total" id="montoTotalInput" value="0.00">
                        </div>
                        <div class="summary-row">
                            <span>A Cuenta (Adelanto):</span>
                            <strong id="lblAdelanto" style="color:#059669; font-size:1.05rem;">S/ 0.00</strong>
                        </div>
                        <div class="summary-row" style="padding-top:0.6rem; border-top:1px dashed #CBD5E1;">
                            <span style="font-weight:700; color:#DC2626;">SALDO PENDIENTE:</span>
                            <strong id="lblSaldo" style="font-size:1.15rem; color:#DC2626;">S/ 0.00</strong>
                        </div>
                    </div>

                    <div style="text-align:right; margin-top:1.5rem;">
                        <button type="submit" class="btn btn-primary" style="padding:0.75rem 2rem; font-size:0.95rem; border-radius:8px; font-weight:700;">
                            <i class="fas fa-check-circle" style="margin-right:6px;"></i> <?= $edit_id ? 'Guardar Cambios del Contrato' : 'Emitir y Guardar Contrato' ?>
                        </button>
                    </div>
                </div>

            </form>
        </div>
    </div>
</div>

<script>
    const productosData = <?= json_encode($productos) ?>;
    const coloresData = <?= json_encode($colores) ?>;
    const initialItems = <?= json_encode($items_to_render) ?>;
    let itemCounter = 0;

    function fillClienteData(cliId) {
        if (!cliId) {
            document.getElementById('cliente_id').value = '';
            document.getElementById('cli_nombre').value = '';
            document.getElementById('cli_doc').value = '';
            document.getElementById('cli_tel').value = '';
            document.getElementById('cli_dir').value = '';
            return;
        }

        const select = document.getElementById('selectClienteExistente');
        const opt = select.options[select.selectedIndex];

        document.getElementById('cliente_id').value = cliId;
        document.getElementById('cli_nombre').value = opt.dataset.nombre || '';
        document.getElementById('cli_doc').value = opt.dataset.doc || '';
        document.getElementById('cli_tel').value = opt.dataset.tel || '';
        document.getElementById('cli_dir').value = opt.dataset.dir || '';
    }

    function toggleDeliveryFields() {
        const val = document.getElementById('tipoEntrega').value;
        const block = document.getElementById('deliveryFieldsBlock');
        const requiresAddress = ['Delivery', 'Provincia - Agencia Marvisur', 'Provincia - Agencia Shalom', 'Ruta Programada'].includes(val);
        block.style.display = requiresAddress ? 'grid' : 'none';
    }

    function addItemRow(data = null) {
        itemCounter++;
        const container = document.getElementById('itemsContainer');
        const row = document.createElement('div');
        row.className = 'item-row-card';
        row.id = `itemRow_${itemCounter}`;

        let prodOptions = '<option value="">-- Seleccionar Producto --</option>';
        productosData.forEach(p => {
            const isSel = (data && data.producto_id == p.id) ? 'selected' : '';
            const codeBadge = p.codigo ? `[${p.codigo}] ` : '';
            prodOptions += `<option value="${p.id}" data-precio="${p.precio_venta}" data-codigo="${p.codigo || ''}" data-nombre="${p.nombre}" ${isSel}>${codeBadge}${p.nombre}</option>`;
        });

        let colorOptions = '<option value="">-- Seleccionar Color --</option>';
        coloresData.forEach(c => {
            const isSel = (data && data.color_id == c.id) ? 'selected' : '';
            const codeBadge = c.codigo ? `[${c.codigo}] ` : '';
            colorOptions += `<option value="${c.id}" data-codigo="${c.codigo || ''}" ${isSel}>${codeBadge}${c.nombre}</option>`;
        });

        const prodNameVal = data ? (data.descripcion || '') : '';
        const specsVal = data ? (data.observaciones_item || '') : '';
        const colorCustomVal = data ? (data.color_custom || '') : '';
        const cantVal = data ? (data.cantidad || 1) : 1;
        const priceVal = data ? (data.precio_unitario || '') : '';
        const subtotalVal = (cantVal * (priceVal || 0)).toFixed(2);
        const origenVal = data ? (data.origen_item || 'Producción') : 'Producción';

        row.innerHTML = `
            <div class="item-grid-top">
                <div class="form-group-clean">
                    <label>Producto / Mueble <span class="req-star">*</span></label>
                    <select class="form-control" onchange="onSelectProduct(this, ${itemCounter})" style="margin-bottom:6px;">
                        ${prodOptions}
                    </select>
                    <input type="text" name="detalles[${itemCounter}][nombre_producto]" id="prodName_${itemCounter}" class="form-control" placeholder="Ingresar nombre de producto" value="${prodNameVal}" required style="margin-bottom:6px; font-weight:600;">
                    <textarea name="detalles[${itemCounter}][especificaciones]" id="prodSpecs_${itemCounter}" class="form-control" rows="2" placeholder="Ingresar especificaciones">${specsVal}</textarea>
                    <input type="hidden" name="detalles[${itemCounter}][producto_id]" id="prodId_${itemCounter}" value="${data ? (data.producto_id || '') : ''}">
                </div>

                <div class="form-group-clean">
                    <label>Color / Acabado</label>
                    <select name="detalles[${itemCounter}][color_id]" id="colorSelect_${itemCounter}" class="form-control" onchange="onSelectColor(this, ${itemCounter})" style="margin-bottom:6px;">
                        ${colorOptions}
                    </select>
                    <input type="text" name="detalles[${itemCounter}][color_custom]" id="colorCustom_${itemCounter}" class="form-control" placeholder="Ingresar color" value="${colorCustomVal}">
                </div>

                <div class="form-group-clean">
                    <label>Cantidad <span class="req-star">*</span></label>
                    <input type="number" min="1" name="detalles[${itemCounter}][cantidad]" id="cant_${itemCounter}" class="form-control" value="${cantVal}" oninput="calcRowSubtotal(${itemCounter})" required>
                </div>

                <div class="form-group-clean">
                    <label>P. Unitario S/ <span class="req-star">*</span></label>
                    <input type="number" step="0.01" min="0" name="detalles[${itemCounter}][precio_unitario]" id="price_${itemCounter}" class="form-control" placeholder="0.00" value="${priceVal}" oninput="calcRowSubtotal(${itemCounter})" required style="font-weight:600;">
                </div>

                <div class="form-group-clean">
                    <label>Importe S/</label>
                    <input type="text" id="subtotal_${itemCounter}" class="form-control" value="${subtotalVal}" readonly style="background:#F1F5F9; font-weight:700; color:#0F172A;">
                    <input type="hidden" name="detalles[${itemCounter}][origen_item]" value="${origenVal}">
                </div>

                <div>
                    <button type="button" class="btn-remove-row" onclick="removeRow(${itemCounter})" title="Eliminar Mueble">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `;

        container.appendChild(row);
        calculateTotals();
    }

    function onSelectProduct(sel, id) {
        const opt = sel.options[sel.selectedIndex];
        const nameInput = document.getElementById(`prodName_${id}`);
        const idInput = document.getElementById(`prodId_${id}`);
        const priceInput = document.getElementById(`price_${id}`);

        if (sel.value) {
            nameInput.value = opt.dataset.nombre || '';
            idInput.value = sel.value;
            if (opt.dataset.precio && parseFloat(opt.dataset.precio) > 0) {
                priceInput.value = parseFloat(opt.dataset.precio).toFixed(2);
            }
        } else {
            idInput.value = '';
        }
        calcRowSubtotal(id);
    }

    function onSelectColor(sel, id) {
        const customInput = document.getElementById(`colorCustom_${id}`);
        if (sel.value) {
            customInput.value = '';
        }
    }

    function calcRowSubtotal(id) {
        const cant = parseFloat(document.getElementById(`cant_${id}`).value) || 0;
        const price = parseFloat(document.getElementById(`price_${id}`).value) || 0;
        const subtotal = cant * price;
        document.getElementById(`subtotal_${id}`).value = subtotal.toFixed(2);
        calculateTotals();
    }

    function removeRow(id) {
        const container = document.getElementById('itemsContainer');
        if (container.children.length <= 1) {
            alert('El contrato debe tener al menos un mueble o producto.');
            return;
        }
        const row = document.getElementById(`itemRow_${id}`);
        if (row) row.remove();
        calculateTotals();
    }

    function calculateTotals() {
        let subtotalProds = 0;
        const container = document.getElementById('itemsContainer');
        const rows = container.getElementsByClassName('item-row-card');

        for (let r of rows) {
            const subInput = r.querySelector('input[id^="subtotal_"]');
            if (subInput) {
                subtotalProds += parseFloat(subInput.value) || 0;
            }
        }

        const flete = parseFloat(document.getElementById('costoMovilidadInput').value) || 0;
        const total = subtotalProds + flete;
        const adelanto = parseFloat(document.getElementById('montoAdelantoInput').value) || 0;
        const saldo = Math.max(0, total - adelanto);

        document.getElementById('lblSubtotalProds').innerText = 'S/ ' + subtotalProds.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('lblFlete').innerText = 'S/ ' + flete.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('lblTotalContract').innerText = 'S/ ' + total.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('montoTotalInput').value = total.toFixed(2);
        document.getElementById('lblAdelanto').innerText = 'S/ ' + adelanto.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('lblSaldo').innerText = 'S/ ' + saldo.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function validateContratoForm() {
        const total = parseFloat(document.getElementById('montoTotalInput').value) || 0;
        const adelanto = parseFloat(document.getElementById('montoAdelantoInput').value) || 0;

        if (total <= 0) {
            alert('⚠️ El monto total del contrato debe ser mayor a S/ 0.00.');
            return false;
        }

        if (adelanto <= 0) {
            alert('⚠️ Debe ingresar un pago adelantado (A CUENTA) obligatorio mayor a S/ 0.00.');
            document.getElementById('montoAdelantoInput').focus();
            return false;
        }

        return true;
    }

    document.addEventListener('DOMContentLoaded', () => {
        toggleDeliveryFields();

        if (initialItems && initialItems.length > 0) {
            initialItems.forEach(item => addItemRow(item));
        } else {
            addItemRow();
        }
    });
</script>
</body>
</html>
