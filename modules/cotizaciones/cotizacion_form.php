<?php
require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/cotizacion_model.php';

$model = new CotizacionModel($db);
$id = $_GET['id'] ?? null;
$duplicate_id = $_GET['duplicate_id'] ?? null;
$cotizacion = null;
$detalles = [];
$isDuplicate = false;
$duplicateOriginalNumero = '';

if ($id) {
    $cotizacion = $model->getById($id);
    if ($cotizacion) {
        $detalles = $cotizacion['detalles'];
    }
} elseif ($duplicate_id) {
    $cotizacionBase = $model->getById($duplicate_id);
    if ($cotizacionBase) {
        $isDuplicate = true;
        $duplicateOriginalNumero = $cotizacionBase['numero'];
        $cotizacion = $cotizacionBase;
        // Asignar automáticamente el nuevo correlativo secuencial para no perjudicar la numeración
        $cotizacion['numero'] = $model->generateNextNumero();
        $cotizacion['fecha'] = date('Y-m-d');
        $cotizacion['fecha_validez'] = date('Y-m-d', strtotime('+15 days'));
        $cotizacion['estado'] = 'Pendiente';
        $detalles = $cotizacionBase['detalles'];
    }
}

// Obtener catálogo de clientes registrados para autocompletar
$stmtClientes = $db->query("SELECT id, nombre, dni_ruc, telefono, direccion FROM clientes ORDER BY nombre ASC");
$clientes_db = $stmtClientes ? $stmtClientes->fetchAll(PDO::FETCH_ASSOC) : [];

// Obtener catálogo de vendedoras/usuarios y locales para asignación comercial
$stmtVendedores = $db->query("
    SELECT u.id, u.username, u.nombre_completo, r.nombre as rol_nombre, u.local_id, l.nombre as local_nombre
    FROM usuarios u
    LEFT JOIN usuario_roles ur ON u.id = ur.usuario_id
    LEFT JOIN roles r ON ur.rol_id = r.id
    LEFT JOIN locales l ON u.local_id = l.id
    WHERE u.estado = 'Activo'
    ORDER BY u.nombre_completo ASC
");
$vendedores_list = $stmtVendedores ? $stmtVendedores->fetchAll(PDO::FETCH_ASSOC) : [];

$stmtLocales = $db->query("SELECT id, nombre, tipo FROM locales ORDER BY nombre ASC");
$locales_list = $stmtLocales ? $stmtLocales->fetchAll(PDO::FETCH_ASSOC) : [];

// Obtener catálogo de productos del sistema
$stmtProds = $db->query("
    SELECT p.id, p.nombre, p.codigo, p.precio_venta, 
    COALESCE(NULLIF(p.imagen_url, ''), (SELECT imagen_url FROM producto_colores WHERE producto_id = p.id AND imagen_url IS NOT NULL AND imagen_url != '' LIMIT 1)) AS imagen_url 
    FROM productos p 
    ORDER BY p.nombre ASC
");
$productos_db = $stmtProds->fetchAll(PDO::FETCH_ASSOC);

// Obtener mapa de colores por producto
$stmtProdColors = $db->query("
    SELECT pc.producto_id, c.nombre AS color_nombre, c.codigo AS color_codigo, pc.codigo AS codigo_variante, pc.imagen_url
    FROM producto_colores pc
    JOIN colores c ON pc.color_id = c.id
    ORDER BY c.nombre ASC
");
$product_colors = [];
while ($row = $stmtProdColors->fetch(PDO::FETCH_ASSOC)) {
    $product_colors[$row['producto_id']][] = [
        'nombre' => $row['color_nombre'],
        'codigo' => $row['color_codigo'],
        'codigo_variante' => $row['codigo_variante'],
        'imagen_url' => $row['imagen_url']
    ];
}

// Obtener lista general de colores
$all_colors = $db->query("SELECT id, nombre, codigo FROM colores ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

if ($id) {
    $page_title = 'Editar Cotización: ' . htmlspecialchars($cotizacion['numero'] ?? '');
} elseif ($isDuplicate) {
    $page_title = 'Duplicar / Nueva Versión de Cotización';
} else {
    $page_title = 'Nueva Cotización';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - Carpicenter</title>
    <link rel="stylesheet" href="/carpicenter_sys/assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .product-list-container {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 1rem;
        }
        .product-list-container table {
            width: 100%;
            border-collapse: collapse;
        }
        .product-list-container th, .product-list-container td {
            padding: 0.8rem;
            border-bottom: 1px solid var(--border-color);
            vertical-align: top;
        }
        .product-list-container th {
            background-color: var(--bg-color);
            text-align: left;
        }
        .img-cell-box {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
        }
        .img-preview-box {
            width: 65px;
            height: 65px;
            border: 1px dashed var(--border-color);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background: #fafafa;
            transition: all 0.2s;
        }
        .img-preview-box:focus, .img-preview-box:hover {
            outline: none;
            border-color: var(--primary);
            background: #f0f8ff;
        }
        .img-preview-box img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            object-position: center;
        }
        .btn-upload-img {
            font-size: 0.72rem;
            padding: 2px 6px;
            border-radius: 4px;
            background: #f0f0f0;
            border: 1px solid #ccc;
            cursor: pointer;
            color: #333;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }
        .btn-upload-img:hover {
            background: #e0e0e0;
        }
        .form-row { display: flex; gap: 1rem; margin-bottom: 1rem; }
        .form-row > * { flex: 1; }
        .product-select {
            font-weight: 600;
            margin-bottom: 0.4rem;
        }
        .color-row {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.4rem;
        }
        .color-row select, .color-row input {
            flex: 1;
        }
        .client-autocomplete-bar {
            background: linear-gradient(135deg, rgba(227, 30, 36, 0.05) 0%, rgba(33, 150, 243, 0.05) 100%);
            border: 1px solid rgba(227, 30, 36, 0.2);
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 1.2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }
    </style>
</head>
<body>
<div class="app-wrapper">
    <?php include __DIR__ . '/../../views/partials/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/../../views/partials/header.php'; ?>
        <div class="page-content">
            <div class="page-header" style="justify-content: space-between; margin-bottom: 1rem;">
                <div>
                    <h2 style="margin: 0;"><?= $page_title ?></h2>
                    <p style="margin: 2px 0 0 0; color: var(--text-muted); font-size: 0.85rem;">
                        <?= $isDuplicate ? 'Generando una nueva versión/cotización con su propio número correlativo independiente' : 'Registra o actualiza los datos de la cotización' ?>
                    </p>
                </div>
                <a href="cotizaciones.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Volver a Cotizaciones</a>
            </div>

            <?php if ($isDuplicate): ?>
            <div style="background: rgba(33, 150, 243, 0.1); border-left: 4px solid #2196F3; border-radius: 8px; padding: 12px 16px; margin-bottom: 1.2rem; display: flex; align-items: center; justify-content: space-between; gap: 10px;">
                <div>
                    <strong style="color: #1976D2;"><i class="fas fa-info-circle"></i> Duplicando Cotización N° <?= htmlspecialchars($duplicateOriginalNumero) ?></strong>
                    <div style="font-size: 0.82rem; color: var(--text-muted); margin-top: 2px;">
                        Se ha asignado automáticamente el nuevo correlativo <strong><?= htmlspecialchars($cotizacion['numero']) ?></strong>. La cotización original permanece intacta.
                    </div>
                </div>
                <span class="badge badge-info" style="font-size: 0.75rem;"><i class="fas fa-copy"></i> Modo Duplicar</span>
            </div>
            <?php endif; ?>

            <form action="cotizacion_controller.php" method="POST" enctype="multipart/form-data" id="cotizacionForm">
                <input type="hidden" name="action" value="<?= ($id && !$isDuplicate) ? 'update' : 'create' ?>">
                <?php if ($id && !$isDuplicate): ?>
                <input type="hidden" name="id" value="<?= $id ?>">
                <?php endif; ?>

                <div class="card-panel">
                    <div class="card-header"><h3><i class="fas fa-file-signature" style="color:var(--primary);margin-right:0.5rem;"></i>Datos de la Cotización</h3></div>
                    <div class="card-body-custom" style="padding: 1.5rem;">
                        
                        <!-- Barra de Búsqueda Rápida de Cliente Registrado -->
                        <div class="client-autocomplete-bar">
                            <div style="font-size: 0.88rem; font-weight: 700; color: var(--primary); display: flex; align-items: center; gap: 6px;">
                                <i class="fas fa-user-check"></i> Autocompletar Cliente Registrado:
                            </div>
                            <div style="flex: 1; min-width: 260px; max-width: 480px;">
                                <select id="selectClienteExistente" class="form-control" onchange="onSelectClienteRegistrado(this)" style="font-size: 0.88rem;">
                                    <option value="">-- Buscar / Seleccionar cliente por nombre o DNI --</option>
                                    <?php foreach ($clientes_db as $cli): ?>
                                    <option value="<?= $cli['id'] ?>"
                                            data-nombre="<?= htmlspecialchars($cli['nombre']) ?>"
                                            data-doc="<?= htmlspecialchars($cli['dni_ruc'] ?? '') ?>"
                                            data-tel="<?= htmlspecialchars($cli['telefono'] ?? '') ?>"
                                            data-dir="<?= htmlspecialchars($cli['direccion'] ?? '') ?>">
                                        <?= htmlspecialchars($cli['nombre']) ?> <?= !empty($cli['dni_ruc']) ? '('.$cli['dni_ruc'].')' : '' ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div style="flex: 0 0 250px;">
                                <label>N° Cotización <span style="color:red">*</span></label>
                                <input type="text" name="numero" class="form-control" value="<?= htmlspecialchars($cotizacion['numero'] ?? $model->generateNextNumero()) ?>" required placeholder="Ej: 2026 008 004" style="font-weight: bold; color: var(--primary);">
                            </div>
                            <div>
                                <label>Nombre / Razón Social <span style="color:red">*</span></label>
                                <input type="text" name="cliente_nombre" id="cli_nombre" class="form-control" value="<?= htmlspecialchars($cotizacion['cliente_nombre'] ?? '') ?>" required placeholder="Nombre del cliente o empresa" oninput="onTypingClientInfo()">
                            </div>
                            <div>
                                <label>DNI / RUC</label>
                                <input type="text" name="cliente_documento" id="cli_doc" class="form-control" value="<?= htmlspecialchars($cotizacion['cliente_documento'] ?? '') ?>" maxlength="11" placeholder="DNI (8) o RUC (11)..." oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11); onTypingClientInfo();">
                            </div>
                            <div>
                                <label>Teléfono / Celular</label>
                                <input type="text" name="cliente_telefono" id="cli_tel" class="form-control" value="<?= htmlspecialchars($cotizacion['cliente_telefono'] ?? '') ?>" placeholder="Ej: 987654321" maxlength="15">
                            </div>
                        </div>
                        <div class="form-group" style="margin-bottom: 1rem;">
                            <label>Dirección</label>
                            <input type="text" name="cliente_direccion" id="cli_dir" class="form-control" value="<?= htmlspecialchars($cotizacion['cliente_direccion'] ?? '') ?>" placeholder="Dirección del cliente...">
                        </div>
                        <!-- Asignación Comercial: Vendedora y Tienda -->
                        <div class="form-row" style="background: #F8FAFC; border: 1.5px solid #E2E8F0; padding: 12px 16px; border-radius: 10px; margin-bottom: 1rem; align-items: flex-end;">
                            <div style="flex: 1;">
                                <label style="font-weight: 700; color: #1E293B; margin-bottom: 4px; display: block;">
                                    <i class="fas fa-user-tag" style="color:#E31E24; margin-right:4px;"></i> Vendedora / Asesora Comercial <span style="color:red">*</span>
                                </label>
                                <select name="vendedor_id" id="form_vendedor_id" class="form-control" onchange="onSelectVendedor(this)" style="font-weight: 600;">
                                    <option value="">-- Seleccionar Vendedora --</option>
                                    <?php foreach ($vendedores_list as $v): 
                                        $currentVendId = $cotizacion['vendedor_id'] ?? ($_SESSION['user_id'] ?? 0);
                                        $selectedV = ($currentVendId == $v['id']) ? 'selected' : '';
                                    ?>
                                        <option value="<?= $v['id'] ?>" data-local="<?= $v['local_id'] ?? '' ?>" data-nombre="<?= htmlspecialchars($v['nombre_completo'] ?: $v['username']) ?>" <?= $selectedV ?>>
                                            <?= htmlspecialchars($v['nombre_completo'] ?: $v['username']) ?> (<?= htmlspecialchars($v['rol_nombre'] ?? 'Personal') ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" name="vendedor_nombre" id="form_vendedor_nombre" value="<?= htmlspecialchars($cotizacion['vendedor_nombre'] ?? '') ?>">
                            </div>
                            <div style="flex: 1;">
                                <label style="font-weight: 700; color: #1E293B; margin-bottom: 4px; display: block;">
                                    <i class="fas fa-shop" style="color:#2563EB; margin-right:4px;"></i> Tienda / Sede Asignada
                                </label>
                                <select name="local_id" id="form_local_id" class="form-control" style="font-weight: 600;">
                                    <option value="">-- Sin Tienda Específica / General --</option>
                                    <?php foreach ($locales_list as $loc): 
                                        $currentLocId = $cotizacion['local_id'] ?? ($_SESSION['local_id'] ?? 0);
                                        $selectedL = ($currentLocId == $loc['id']) ? 'selected' : '';
                                    ?>
                                        <option value="<?= $loc['id'] ?>" <?= $selectedL ?>>
                                            <?= htmlspecialchars($loc['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div>
                                <label>Fecha de Emisión</label>
                                <input type="date" name="fecha" class="form-control" value="<?= $cotizacion['fecha'] ?? date('Y-m-d') ?>" required>
                            </div>
                            <div>
                                <label>Válido Hasta</label>
                                <input type="date" name="fecha_validez" class="form-control" value="<?= $cotizacion['fecha_validez'] ?? date('Y-m-d', strtotime('+15 days')) ?>">
                            </div>
                            <div>
                                <label>Estado de Cotización</label>
                                <select name="estado" class="form-control">
                                    <option value="Pendiente" <?= ($cotizacion['estado'] ?? '') == 'Pendiente' ? 'selected' : '' ?>>Pendiente</option>
                                    <option value="Aprobada" <?= ($cotizacion['estado'] ?? '') == 'Aprobada' ? 'selected' : '' ?>>Aprobada (Aceptada)</option>
                                    <option value="Facturada" <?= ($cotizacion['estado'] ?? '') == 'Facturada' ? 'selected' : '' ?>>Facturada</option>
                                    <option value="Rechazada" <?= ($cotizacion['estado'] ?? '') == 'Rechazada' ? 'selected' : '' ?>>Rechazada</option>
                                    <option value="Anulada" <?= ($cotizacion['estado'] ?? '') == 'Anulada' ? 'selected' : '' ?>>Anulada (No concretada)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Datalist para colores autocompletables -->
                <datalist id="colorsDatalist">
                    <?php foreach ($all_colors as $col): 
                        $cName = is_array($col) ? ($col['nombre'] ?? '') : $col;
                    ?>
                    <option value="<?= htmlspecialchars($cName) ?>"></option>
                    <?php endforeach; ?>
                </datalist>

                <div class="card-panel" style="margin-top: 1.5rem;">
                    <div class="card-header">
                        <h3>Detalle de Productos y Colores</h3>
                        <button type="button" class="btn btn-success btn-sm" onclick="addProductRow()"><i class="fas fa-plus"></i> Agregar Fila</button>
                    </div>
                    <div class="card-body-custom" style="padding: 0;">
                        <div class="product-list-container">
                            <table id="productsTable">
                                <thead>
                                    <tr>
                                        <th style="width: 12%; text-align: center;">Imagen</th>
                                        <th style="width: 8%;">Cant.</th>
                                        <th style="width: 42%;">Producto, Color y Descripción</th>
                                        <th style="width: 14%;">P. Unitario (S/)</th>
                                        <th style="width: 14%;">Subtotal (S/)</th>
                                        <th style="width: 10%; text-align: center;">Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Dynamic rows will go here -->
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="4" style="text-align: right; font-weight: bold; color: var(--text-muted);">Gastos Logísticos (Opcional): S/</td>
                                        <td>
                                            <input type="number" step="0.01" name="gastos_logisticos" id="gastosLogisticos" class="form-control" value="<?= htmlspecialchars($cotizacion['gastos_logisticos'] ?? '0.00') ?>" oninput="calculateTotal()">
                                        </td>
                                        <td></td>
                                    </tr>
                                    <tr>
                                        <td colspan="4" style="text-align: right; font-weight: bold; color: var(--text-muted);">Modificación por Orden de Compra (Opcional): S/</td>
                                        <td>
                                            <input type="number" step="0.01" name="modificacion_orden_compra" id="modificacionOrdenCompra" class="form-control" value="<?= htmlspecialchars($cotizacion['modificacion_orden_compra'] ?? '0.00') ?>" oninput="calculateTotal()">
                                        </td>
                                        <td></td>
                                    </tr>
                                    <tr>
                                        <td colspan="4" style="text-align: right; font-weight: bold; color: var(--text-muted);">Movilidad (Opcional): S/</td>
                                        <td>
                                            <input type="number" step="0.01" name="movilidad" id="movilidad" class="form-control" value="<?= htmlspecialchars($cotizacion['movilidad'] ?? '0.00') ?>" oninput="calculateTotal()">
                                        </td>
                                        <td></td>
                                    </tr>
                                    <tr>
                                        <td colspan="4" style="text-align: right; font-weight: bold; font-size: 1.2rem;">TOTAL: S/</td>
                                        <td>
                                            <input type="number" step="0.01" name="total" id="granTotal" class="form-control" value="0.00" readonly style="font-weight: bold; font-size: 1.2rem;">
                                        </td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card-panel" style="margin-top: 1.5rem;">
                    <div class="card-header"><h3><i class="fas fa-info-circle" style="color:var(--primary);margin-right:0.5rem;"></i>Información Adicional</h3></div>
                    <div class="card-body-custom" style="padding: 1.5rem;">
                        <div class="form-group" style="margin-bottom: 1rem;">
                            <label>Comentarios / Observaciones</label>
                            <textarea name="observaciones" class="form-control" rows="3"><?= $cotizacion['observaciones'] ?? 'La presente cotización se relaciona a la siguiente modalidad de pago. 50% de adelanto y 50% contraentrega.' ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Condiciones (Rojo en el diseño)</label>
                            <textarea name="condiciones" class="form-control" rows="3"><?= $cotizacion['condiciones'] ?? 'PERIODO DE ENTREGA: 07 a 15 días hábiles (Producción Nacional) DISPONIBILIDAD INMEDIATA DE ACUERDO A STOCK (Importación). Todos los precios brindados incluyen IGV. NO INCLUYE MOVILIDAD. GARANTÍA 1 AÑO.' ?></textarea>
                        </div>
                        
                        <div style="text-align: right; margin-top: 1rem;">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar Cotización</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    let rowIndex = 0;
    const initialDetalles = <?= json_encode($detalles) ?>;
    const catalogProducts = <?= json_encode($productos_db) ?>;
    const productColorsMap = <?= json_encode($product_colors) ?>;
    const allColorsList = <?= json_encode($all_colors) ?>;
    const registeredClients = <?= json_encode($clientes_db) ?>;

    function onSelectClienteRegistrado(selectEl) {
        const opt = selectEl.options[selectEl.selectedIndex];
        if (!opt || !opt.value) return;

        const nombre = opt.dataset.nombre || '';
        const doc = opt.dataset.doc || '';
        const tel = opt.dataset.tel || '';
        const dir = opt.dataset.dir || '';

        if (nombre) document.getElementById('cli_nombre').value = nombre;
        if (doc) document.getElementById('cli_doc').value = doc;
        if (tel) document.getElementById('cli_tel').value = tel;
        if (dir) document.getElementById('cli_dir').value = dir;
    }

    function onTypingClientInfo() {
        const docVal = (document.getElementById('cli_doc').value || '').trim();
        if (docVal.length >= 8) {
            const found = registeredClients.find(c => (c.dni_ruc || '').trim() === docVal);
            if (found) {
                if (!document.getElementById('cli_nombre').value.trim()) document.getElementById('cli_nombre').value = found.nombre;
                if (!document.getElementById('cli_tel').value.trim() && found.telefono) document.getElementById('cli_tel').value = found.telefono;
                if (!document.getElementById('cli_dir').value.trim() && found.direccion) document.getElementById('cli_dir').value = found.direccion;
                const sel = document.getElementById('selectClienteExistente');
                if (sel) sel.value = found.id;
            }
        }
    }

    function addProductRow(data = {}) {
        const tbody = document.querySelector('#productsTable tbody');
        const tr = document.createElement('tr');
        tr.dataset.index = rowIndex;

        // Determine main image for this item if product_id is provided
        let mainImgUrl = data.imagen || '';
        if (data.producto_id) {
            const p = catalogProducts.find(prod => parseInt(prod.id) === parseInt(data.producto_id));
            if (p) mainImgUrl = p.imagen_url || data.imagen || '';
        }
        tr.dataset.mainImg = mainImgUrl;

        // Build clean product dropdown options
        let optionsHtml = `<option value="">-- Seleccionar producto --</option>`;
        catalogProducts.forEach(p => {
            const selected = (data.producto_id && parseInt(data.producto_id) === parseInt(p.id)) ? 'selected' : '';
            const codeTag = p.codigo ? `[${p.codigo}] ` : '';
            optionsHtml += `<option value="${p.id}" ${selected}>${codeTag}${p.nombre} (S/ ${parseFloat(p.precio_venta).toFixed(2)})</option>`;
        });
        
        const imgSrc = data.imagen || mainImgUrl || '';
        const imgDisplay = imgSrc ? `<img src="${imgSrc}" alt="Producto">` : `<i class="fas fa-image" style="color:#aaa;"></i>`;

        tr.innerHTML = `
            <td>
                <div class="img-cell-box">
                    <div class="img-preview-box" id="imgBox_${rowIndex}" tabindex="0" onpaste="onPasteImage(event, ${rowIndex})" title="Haz clic aquí y presiona Ctrl+V para pegar una imagen" style="cursor: pointer;">
                        ${imgDisplay}
                    </div>
                    <input type="hidden" name="productos[${rowIndex}][producto_id]" id="prodId_${rowIndex}" value="${data.producto_id || ''}">
                    <input type="hidden" name="productos[${rowIndex}][imagen]" id="prodImg_${rowIndex}" value="${imgSrc}">
                    <label for="fileInput_${rowIndex}" class="btn-upload-img" title="Subir foto personalizada para reventa o producto externo">
                        <i class="fas fa-camera"></i> Subir Foto
                    </label>
                    <input type="file" name="producto_imagen_${rowIndex}" id="fileInput_${rowIndex}" accept="image/*" style="display:none;" onchange="onFileSelected(this, ${rowIndex})">
                </div>
            </td>
            <td>
                <input type="number" name="productos[${rowIndex}][cantidad]" class="form-control qty-input" value="${data.cantidad || 1}" min="1" required oninput="calculateRow(this)">
            </td>
            <td>
                <select class="form-control product-select" onchange="onSelectCatalogProduct(this, ${rowIndex})">
                    ${optionsHtml}
                </select>

                <div class="color-row">
                    <select id="colorSelect_${rowIndex}" class="form-control" onchange="onColorSelectChange(this, ${rowIndex})">
                        <option value="">-- Seleccionar Color --</option>
                    </select>
                    <input type="text" name="productos[${rowIndex}][color]" id="colorInput_${rowIndex}" class="form-control" list="colorsDatalist" value="${data.color || ''}" placeholder="Escribir color manual o personalizado...">
                </div>

                <input type="text" name="productos[${rowIndex}][descripcion]" id="desc_${rowIndex}" class="form-control" style="margin-top: 0.4rem;" value="${data.descripcion || ''}" placeholder="Nombre / Descripción del producto" required>
                <textarea name="productos[${rowIndex}][especificaciones]" class="form-control" style="margin-top: 0.4rem; font-size: 0.88em;" placeholder="Especificaciones adicionales (ej. Medidas, acabado...)" rows="2">${data.especificaciones || ''}</textarea>
            </td>
            <td>
                <input type="number" step="0.01" name="productos[${rowIndex}][precio_unitario]" id="price_${rowIndex}" class="form-control price-input" value="${data.precio_unitario || '0.00'}" required oninput="calculateRow(this)">
                <small style="font-size:0.75rem; color:#666;">Editable (rebajas)</small>
            </td>
            <td>
                <input type="number" step="0.01" name="productos[${rowIndex}][subtotal]" class="form-control subtotal-input" value="${data.subtotal || '0.00'}" readonly>
            </td>
            <td style="text-align:center;">
                <button type="button" class="btn-icon" onclick="removeRow(this)" title="Eliminar fila"><i class="fas fa-trash" style="color:var(--danger);"></i></button>
            </td>
        `;

        tbody.appendChild(tr);
        
        // Populate colors dropdown for this row
        populateColorDropdown(rowIndex, data.producto_id, data.color);

        // If data was passed without explicit dropdown selection, attempt to match name
        if (!data.producto_id && data.descripcion) {
            const matched = catalogProducts.find(p => p.nombre.trim().toLowerCase() === data.descripcion.trim().toLowerCase());
            if (matched) {
                const selectEl = tr.querySelector('.product-select');
                selectEl.value = matched.id;
                tr.dataset.mainImg = matched.imagen_url || '';
                populateColorDropdown(rowIndex, matched.id, data.color);
            }
        }

        rowIndex++;
        calculateTotal();
    }

    function populateColorDropdown(index, prodId, currentColor = '') {
        const colorSelect = document.getElementById(`colorSelect_${index}`);
        if (!colorSelect) return;

        let html = `<option value="">-- Seleccionar Color --</option>`;
        let colorsAvailable = [];

        if (prodId && productColorsMap[prodId]) {
            colorsAvailable = productColorsMap[prodId];
        }

        if (colorsAvailable.length > 0) {
            colorsAvailable.forEach(c => {
                const isSelected = (currentColor && currentColor.trim().toLowerCase() === c.nombre.trim().toLowerCase()) ? 'selected' : '';
                const codeBadge = c.codigo ? `[${c.codigo}] ` : '';
                html += `<option value="${c.nombre}" data-img="${c.imagen_url || ''}" ${isSelected}>${codeBadge}${c.nombre}</option>`;
            });
        } else {
            // General colors list fallback
            allColorsList.forEach(col => {
                const colName = typeof col === 'object' ? col.nombre : col;
                const colCode = typeof col === 'object' && col.codigo ? `[${col.codigo}] ` : '';
                const isSelected = (currentColor && currentColor.trim().toLowerCase() === colName.trim().toLowerCase()) ? 'selected' : '';
                html += `<option value="${colName}" ${isSelected}>${colCode}${colName}</option>`;
            });
        }

        colorSelect.innerHTML = html;
    }

    function onSelectCatalogProduct(selectEl, index) {
        const tr = selectEl.closest('tr');
        const prodId = selectEl.value;
        const prodIdInput = document.getElementById(`prodId_${index}`);
        const prodImgInput = document.getElementById(`prodImg_${index}`);
        const imgBox = document.getElementById(`imgBox_${index}`);
        const descInput = document.getElementById(`desc_${index}`);
        const priceInput = document.getElementById(`price_${index}`);

        if (!prodId) {
            prodIdInput.value = '';
            tr.dataset.mainImg = '';
            populateColorDropdown(index, null);
            return;
        }

        const selectedProd = catalogProducts.find(p => parseInt(p.id) === parseInt(prodId));
        if (selectedProd) {
            prodIdInput.value = selectedProd.id;
            descInput.value = selectedProd.nombre;
            
            // Store main product image on row dataset
            const mainImgUrl = selectedProd.imagen_url || '';
            tr.dataset.mainImg = mainImgUrl;
            
            if (parseFloat(selectedProd.precio_venta) > 0 || parseFloat(priceInput.value) === 0) {
                priceInput.value = parseFloat(selectedProd.precio_venta).toFixed(2);
            }

            // Set main image
            prodImgInput.value = mainImgUrl;
            if (mainImgUrl) {
                imgBox.innerHTML = `<img src="${mainImgUrl}" alt="Producto">`;
            } else {
                imgBox.innerHTML = `<i class="fas fa-image" style="color:#aaa;"></i>`;
            }

            // Update color dropdown for this product
            populateColorDropdown(index, selectedProd.id);

            calculateRow(priceInput);
        }
    }

    function onColorSelectChange(selectEl, index) {
        const tr = selectEl.closest('tr');
        const selectedOption = selectEl.options[selectEl.selectedIndex];
        const colorVal = selectEl.value;
        const colorInput = document.getElementById(`colorInput_${index}`);
        const prodImgInput = document.getElementById(`prodImg_${index}`);
        const imgBox = document.getElementById(`imgBox_${index}`);

        if (colorVal) {
            colorInput.value = colorVal;

            // Check if specific color has a custom image, otherwise fall back to main product image!
            const colorImg = selectedOption.dataset.img || '';
            const mainImg = tr.dataset.mainImg || '';
            const finalImg = colorImg ? colorImg : mainImg;

            if (finalImg) {
                prodImgInput.value = finalImg;
                imgBox.innerHTML = `<img src="${finalImg}" alt="${colorVal}">`;
            } else {
                imgBox.innerHTML = `<i class="fas fa-image" style="color:#aaa;"></i>`;
            }
        }
    }

    function onSelectVendedor(sel) {
        const opt = sel.options[sel.selectedIndex];
        if (!opt) return;
        const localId = opt.getAttribute('data-local');
        const nombre = opt.getAttribute('data-nombre');
        if (nombre) {
            document.getElementById('form_vendedor_nombre').value = nombre;
        }
        if (localId) {
            const localSel = document.getElementById('form_local_id');
            if (localSel) {
                localSel.value = localId;
            }
        }
    }

    function onFileSelected(input, index) {
        if (input.files && input.files[0]) {
            const file = input.files[0];
            const imgBox = document.getElementById(`imgBox_${index}`);
            const reader = new FileReader();
            reader.onload = function(e) {
                imgBox.innerHTML = `<img src="${e.target.result}" alt="Foto Subida">`;
            };
            reader.readAsDataURL(file);
        }
    }

    function onPasteImage(event, index) {
        const items = (event.clipboardData || event.originalEvent.clipboardData).items;
        for (let i = 0; i < items.length; i++) {
            if (items[i].type.indexOf("image") === 0) {
                const file = items[i].getAsFile();
                
                // Assign the pasted file to the hidden file input
                const fileInput = document.getElementById(`fileInput_${index}`);
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(file);
                fileInput.files = dataTransfer.files;

                // Preview the image
                const imgBox = document.getElementById(`imgBox_${index}`);
                const reader = new FileReader();
                reader.onload = function(e) {
                    imgBox.innerHTML = `<img src="${e.target.result}" alt="Foto Pegada">`;
                };
                reader.readAsDataURL(file);
                
                event.preventDefault();
                break;
            }
        }
    }

    function removeRow(btn) {
        btn.closest('tr').remove();
        calculateTotal();
    }

    function calculateRow(element) {
        const tr = element.closest('tr');
        const qty = parseFloat(tr.querySelector('.qty-input').value) || 0;
        const price = parseFloat(tr.querySelector('.price-input').value) || 0;
        const subtotal = qty * price;
        tr.querySelector('.subtotal-input').value = subtotal.toFixed(2);
        calculateTotal();
    }

    function calculateTotal() {
        let total = 0;
        document.querySelectorAll('.subtotal-input').forEach(input => {
            total += parseFloat(input.value) || 0;
        });

        const gastosLog = parseFloat(document.getElementById('gastosLogisticos').value) || 0;
        const modOrd = parseFloat(document.getElementById('modificacionOrdenCompra').value) || 0;
        const mov = parseFloat(document.getElementById('movilidad').value) || 0;

        total += gastosLog + modOrd + mov;

        document.getElementById('granTotal').value = total.toFixed(2);
    }

    window.onload = () => {
        if (initialDetalles.length > 0) {
            initialDetalles.forEach(det => addProductRow(det));
        } else {
            addProductRow();
        }

        const isEditMode = <?= !empty($id) ? 'true' : 'false' ?>;
        const fechaInput = document.querySelector('input[name="fecha"]');
        const numInput = document.querySelector('input[name="numero"]');
        if (fechaInput && numInput && !isEditMode) {
            fechaInput.addEventListener('change', function() {
                if (!this.value) return;
                const parts = this.value.split('-');
                if (parts.length === 3) {
                    const year = parts[0];
                    const month = parts[1].padStart(3, '0');
                    const currentVal = numInput.value.trim();
                    const match = currentVal.match(/^(\d{4})\s+(\d{3})\s+(\d{1,6})$/);
                    if (match) {
                        numInput.value = `${year} ${month} ${match[3]}`;
                    }
                }
            });
        }
    };
</script>
</body>
</html>
