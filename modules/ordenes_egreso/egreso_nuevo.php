<?php
require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../config/db.php';

if ($user_role === 'Producción') {
    die("Acceso denegado. La emisión de Orden de Egreso es exclusiva del área de Logística y Administración.");
}

// Generar correlativo automático
$stmtSeq = $db->query("SELECT numero FROM ordenes_egreso ORDER BY id DESC LIMIT 1");
$lastNum = $stmtSeq->fetchColumn();
if ($lastNum) {
    $numOnly = intval(preg_replace('/[^0-9]/', '', $lastNum)) + 1;
} else {
    $numOnly = 14152; // Matching exact format seed 00014152 from photo sample
}
$next_number = str_pad($numOnly, 8, '0', STR_PAD_LEFT);

// Obtener locales
$locales = $db->query("SELECT id, nombre, tipo FROM locales ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

// Obtener productos y colores para selectores
$productos = $db->query("SELECT id, nombre FROM productos ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
$colores = $db->query("SELECT id, nombre FROM colores ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

// Si viene con contrato_id o nota_id para importar datos
$import_contrato_id = $_GET['contrato_id'] ?? null;
$import_nota_id = $_GET['nota_id'] ?? null;
$items_preformated = [];
$preset_destino = '';
$preset_motivo = 'ENTREGA A CLIENTE POR CONTRATO';
$preset_origen_id = 1;
$preset_recepciona_nombre = '';
$preset_recepciona_dni = '';

if ($import_contrato_id) {
    $stmtC = $db->prepare("
        SELECT c.*, cli.nombre as cli_nombre, cli.dni_ruc as cli_doc, cli.direccion as cli_dir
        FROM contratos c
        LEFT JOIN clientes cli ON c.cliente_id = cli.id
        WHERE c.id = :id
    ");
    $stmtC->execute([':id' => $import_contrato_id]);
    $cData = $stmtC->fetch(PDO::FETCH_ASSOC);

    if ($cData) {
        $preset_origen_id = $cData['local_id'] ?? 1;
        $preset_destino = "CLIENTE: " . $cData['cli_nombre'] . " (" . ($cData['tipo_entrega'] ?? 'Entrega en tienda') . ")";
        $preset_recepciona_nombre = $cData['cli_nombre'];
        $preset_recepciona_dni = $cData['cli_doc'];

        $stmtCd = $db->prepare("
            SELECT cd.*, p.nombre as prod_nombre, col.nombre as color_nombre
            FROM contrato_detalles cd
            LEFT JOIN productos p ON cd.producto_id = p.id
            LEFT JOIN colores col ON cd.color_id = col.id
            WHERE cd.contrato_id = :id
        ");
        $stmtCd->execute([':id' => $import_contrato_id]);
        $detList = $stmtCd->fetchAll(PDO::FETCH_ASSOC);

        foreach ($detList as $cd) {
            $desc = $cd['prod_nombre'] ? ($cd['prod_nombre'] . ($cd['color_nombre'] ? ' ' . $cd['color_nombre'] : '')) : $cd['descripcion'];
            $items_preformated[] = [
                'producto_id' => $cd['producto_id'],
                'color_id' => $cd['color_id'],
                'descripcion' => $desc,
                'unidad' => 'un',
                'cantidad' => $cd['cantidad']
            ];
        }
    }
}

$page_title = 'Nueva Orden de Egreso';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Orden de Egreso - Carpicenter</title>
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
    </style>
</head>
<body>
<div class="app-wrapper">
    <?php include '../../views/partials/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../views/partials/header.php'; ?>
        <div class="page-content">
            <div class="page-header" style="margin-bottom: 1.5rem;">
                <div>
                    <h2>Emitir Orden de Egreso</h2>
                    <p>Salida física definitiva de inventario (Descuento 100% de Stock Físico)</p>
                </div>
                <a href="ordenes_egreso.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Volver a la Lista</a>
            </div>

            <form action="egreso_save.php" method="POST" id="egresoForm">
                <?php if ($import_contrato_id): ?>
                    <input type="hidden" name="contrato_id" value="<?= $import_contrato_id ?>">
                <?php endif; ?>
                <?php if ($import_nota_id): ?>
                    <input type="hidden" name="nota_venta_id" value="<?= $import_nota_id ?>">
                <?php endif; ?>

                <!-- Datos de Cabecera -->
                <div class="card-panel" style="margin-bottom:1.5rem;">
                    <div class="card-header">
                        <h3><i class="fas fa-file-export" style="color:var(--primary);margin-right:0.5rem;"></i>Datos de la Orden de Egreso</h3>
                    </div>
                    <div class="card-body-custom" style="padding: 1.5rem;">
                        <div class="form-row">
                            <div>
                                <label>Número Correlativo</label>
                                <input type="text" name="numero" class="form-control" value="<?= htmlspecialchars($next_number) ?>" readonly style="font-weight:bold; letter-spacing:1px; color:var(--primary-light);">
                            </div>
                            <div>
                                <label>Fecha de Emisión <span style="color:red">*</span></label>
                                <input type="date" name="fecha_emision" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div>
                                <label>Hora de Emisión</label>
                                <input type="text" name="hora_emision" class="form-control" value="<?= date('H:i:s') ?>" readonly>
                            </div>
                        </div>

                        <div class="form-row">
                            <div>
                                <label>Local / Almacén de Origen <span style="color:red">*</span></label>
                                <select name="local_origen_id" class="form-control" required>
                                    <?php foreach ($locales as $loc): ?>
                                    <option value="<?= $loc['id'] ?>" <?= ($preset_origen_id == $loc['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($loc['nombre']) ?> (<?= htmlspecialchars($loc['tipo']) ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label>Local / Cliente Destino <span style="color:red">*</span></label>
                                <input type="text" name="local_destino_nombre" class="form-control" placeholder="Ej: TIENDA 2, O CLIENTE: JUAN PÉREZ" value="<?= htmlspecialchars($preset_destino) ?>" required>
                            </div>
                            <div>
                                <label>Motivo del Egreso <span style="color:red">*</span></label>
                                <select name="motivo_egreso" class="form-control" required>
                                    <option value="ENTREGA A CLIENTE POR CONTRATO" <?= $preset_motivo === 'ENTREGA A CLIENTE POR CONTRATO' ? 'selected' : '' ?>>ENTREGA A CLIENTE POR CONTRATO</option>
                                    <option value="TRANSFERENCIA ENTRE ALMACENES">TRANSFERENCIA ENTRE ALMACENES</option>
                                    <option value="VENTA DIRECTA NOTA DE VENTA">VENTA DIRECTA NOTA DE VENTA</option>
                                    <option value="DESPACHO PROVINCIA AGENCIA MARVISUR / SHALOOM">DESPACHO PROVINCIA AGENCIA MARVISUR / SHALOOM</option>
                                    <option value="DESPACHO RUTA PROGRAMADA">DESPACHO RUTA PROGRAMADA</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div>
                                <label>Fecha Aprox. de Llegada / Entrega</label>
                                <input type="date" name="fecha_aprox_llegada" class="form-control" value="<?= date('Y-m-d') ?>">
                            </div>
                            <div>
                                <label>Recepcionado Por (Nombres y Apellidos) <span style="color:red">*</span></label>
                                <input type="text" name="recepcionado_nombre" class="form-control" placeholder="Nombre completo de quien recibe" value="<?= htmlspecialchars($preset_recepciona_nombre) ?>" required>
                            </div>
                            <div>
                                <label>DNI de quien Recepciona <span style="color:red">*</span></label>
                                <input type="text" name="recepcionado_dni" class="form-control" maxlength="8" placeholder="DNI (8 dígitos)" value="<?= htmlspecialchars($preset_recepciona_dni) ?>" oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,8);" required>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detalle de Productos -->
                <div class="card-panel">
                    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
                        <h3><i class="fas fa-boxes-stacked" style="color:var(--primary);margin-right:0.5rem;"></i>Productos de la Orden</h3>
                        <button type="button" class="btn btn-outline btn-sm" onclick="addEmptyRow()"><i class="fas fa-plus"></i> Agregar Producto</button>
                    </div>
                    <div class="card-body-custom" style="padding: 1rem;">
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th style="width: 50%;">Descripción del Producto / Mueble</th>
                                        <th style="width: 20%;">Unidad Medida</th>
                                        <th style="width: 20%;">Cantidad</th>
                                        <th style="width: 10%; text-align:center;">Acción</th>
                                    </tr>
                                </thead>
                                <tbody id="itemsBody">
                                    <!-- Pre-populated by JS -->
                                </tbody>
                            </table>
                        </div>

                        <div style="display:flex; justify-content:flex-end; gap:1rem; margin-top:1.5rem;">
                            <button type="submit" class="btn btn-primary" style="padding:0.8rem 2rem; font-size:1rem;">
                                <i class="fas fa-check-circle"></i> Emitir Orden & Descontar 100% Inventario
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const productosCatalog = <?= json_encode($productos) ?>;
    const coloresCatalog = <?= json_encode($colores) ?>;
    const preformattedItems = <?= json_encode($items_preformated) ?>;
    let rowIdx = 0;

    function addRow(prodId = '', colId = '', desc = '', unidad = 'un', cant = 1) {
        rowIdx++;
        const tbody = document.getElementById('itemsBody');
        const tr = document.createElement('tr');
        tr.id = `row_${rowIdx}`;

        let prodOptions = '<option value="">-- Seleccionar producto del catálogo (Opcional) --</option>';
        productosCatalog.forEach(p => {
            prodOptions += `<option value="${p.id}" ${prodId == p.id ? 'selected' : ''}>${p.nombre}</option>`;
        });

        let colOptions = '<option value="">-- Color (Opcional) --</option>';
        coloresCatalog.forEach(c => {
            colOptions += `<option value="${c.id}" ${colId == c.id ? 'selected' : ''}>${c.nombre}</option>`;
        });

        tr.innerHTML = `
            <td>
                <div style="display:flex; gap:0.5rem; margin-bottom:0.3rem;">
                    <select name="items[${rowIdx}][producto_id]" class="form-control" onchange="onSelectCatalogProd(this, ${rowIdx})" style="font-size:0.8rem;">
                        ${prodOptions}
                    </select>
                    <select name="items[${rowIdx}][color_id]" class="form-control" onchange="onSelectCatalogColor(this, ${rowIdx})" style="font-size:0.8rem; width:150px;">
                        ${colOptions}
                    </select>
                </div>
                <input type="text" name="items[${rowIdx}][descripcion]" id="desc_${rowIdx}" class="form-control" placeholder="Descripción de la orden" value="${desc}" required>
            </td>
            <td>
                <input type="text" name="items[${rowIdx}][unidad_medida]" class="form-control" value="${unidad}" required style="text-align:center;">
            </td>
            <td>
                <input type="number" min="0.1" step="0.1" name="items[${rowIdx}][cantidad]" class="form-control" value="${cant}" required style="font-weight:bold; text-align:right;">
            </td>
            <td style="text-align:center;">
                <button type="button" class="btn-icon" onclick="removeRow(${rowIdx})" style="color:var(--danger);"><i class="fas fa-trash"></i></button>
            </td>
        `;

        tbody.appendChild(tr);
    }

    function addEmptyRow() {
        addRow('', '', '', 'un', 1);
    }

    function onSelectCatalogProd(sel, idx) {
        const text = sel.options[sel.selectedIndex].text;
        const descInput = document.getElementById(`desc_${idx}`);
        if (sel.value) {
            descInput.value = text;
        }
    }

    function onSelectCatalogColor(sel, idx) {
        const colorName = sel.options[sel.selectedIndex].text;
        const descInput = document.getElementById(`desc_${idx}`);
        if (sel.value && !descInput.value.includes(colorName)) {
            descInput.value = descInput.value + ' ' + colorName;
        }
    }

    function removeRow(idx) {
        const tr = document.getElementById(`row_${idx}`);
        if (tr) tr.remove();
    }

    document.addEventListener('DOMContentLoaded', () => {
        if (preformattedItems && preformattedItems.length > 0) {
            preformattedItems.forEach(it => {
                addRow(it.producto_id, it.color_id, it.descripcion, it.unidad, it.cantidad);
            });
        } else {
            addEmptyRow();
        }
    });
</script>
</body>
</html>
