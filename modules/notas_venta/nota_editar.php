<?php
require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../config/db.php';

if (!$is_admin) {
    die("Acceso denegado: Solo los Administradores pueden editar notas de venta.");
}

$id = $_GET['id'] ?? null;
if (!$id) die("ID de nota de venta no proporcionado.");

// Obtener cabecera de la nota de venta
$stmt = $db->prepare("SELECT * FROM notas_venta WHERE id = :id");
$stmt->execute([':id' => $id]);
$nota = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$nota) die("Nota de venta no encontrada.");
if ($nota['estado'] !== 'Activa') die("No se puede editar una nota de venta anulada.");

// Obtener detalles de la nota
$stmtDet = $db->prepare("SELECT * FROM notas_venta_detalle WHERE nota_id = :id ORDER BY id ASC");
$stmtDet->execute([':id' => $id]);
$detalles = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

// Obtener productos para autocompletar
$stmtProd = $db->query("SELECT id, nombre, codigo, precio_venta FROM productos ORDER BY nombre ASC");
$productos_inventario = $stmtProd->fetchAll(PDO::FETCH_ASSOC);

// Obtener locales/almacenes
$stmtLoc = $db->query("SELECT id, nombre, tipo FROM locales ORDER BY id ASC");
$locales_list = $stmtLoc->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Editar Nota de Venta ' . $nota['numero'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Nota de Venta <?= htmlspecialchars($nota['numero']) ?> - Carpicenter</title>
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
                    <h2>Editar Nota de Venta N° <?= htmlspecialchars($nota['numero']) ?></h2>
                    <p>Modificación de comprobante interno de venta</p>
                </div>
                <a href="nota_view.php?id=<?= $nota['id'] ?>" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Cancelar / Volver</a>
            </div>

            <form action="nota_update.php" method="POST" id="notaForm">
                <input type="hidden" name="id" value="<?= $nota['id'] ?>">

                <div class="grid-2-1">
                    <!-- Columna Izquierda: Datos y Detalles -->
                    <div style="display:flex; flex-direction:column; gap:1.5rem;">
                        
                        <!-- Datos del Comprobante -->
                        <div class="card-panel">
                            <div class="card-header">
                                <h3><i class="fas fa-file-invoice" style="color:var(--primary);margin-right:0.5rem;"></i>Datos del Comprobante</h3>
                            </div>
                            <div class="card-body-custom" style="padding: 1.5rem;">
                                <div class="form-row">
                                    <div>
                                        <label>Número Correlativo</label>
                                        <input type="text" name="numero" class="form-control" value="<?= htmlspecialchars($nota['numero']) ?>" readonly style="font-weight:bold; letter-spacing:1px; color:var(--primary-light);">
                                    </div>
                                    <div>
                                        <label>Establecimiento / Origen <span style="color:red">*</span></label>
                                        <select name="local_id" class="form-control" required>
                                            <?php foreach ($locales_list as $loc): ?>
                                            <option value="<?= $loc['id'] ?>" <?= ($nota['local_id'] == $loc['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($loc['nombre']) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label>Fecha <span style="color:red">*</span></label>
                                        <input type="date" name="fecha" class="form-control" value="<?= htmlspecialchars($nota['fecha']) ?>" required>
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
                                        <input type="text" name="cliente_nombre" class="form-control" value="<?= htmlspecialchars($nota['cliente_nombre']) ?>" required>
                                    </div>
                                    <div>
                                        <label>DNI / RUC</label>
                                        <input type="text" name="cliente_documento" class="form-control" maxlength="11" value="<?= htmlspecialchars($nota['cliente_documento'] ?? '') ?>" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11);">
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div>
                                        <label>Dirección</label>
                                        <input type="text" name="cliente_direccion" class="form-control" value="<?= htmlspecialchars($nota['cliente_direccion'] ?? '') ?>">
                                    </div>
                                    <div>
                                        <label>Teléfono / Celular</label>
                                        <input type="text" name="cliente_telefono" class="form-control" maxlength="9" value="<?= htmlspecialchars($nota['cliente_telefono'] ?? '') ?>" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 9);">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Detalle de Productos -->
                        <div class="card-panel">
                            <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
                                <h3><i class="fas fa-boxes-packing" style="color:var(--primary);margin-right:0.5rem;"></i>Detalle de Productos</h3>
                                <button type="button" class="btn btn-outline btn-sm" onclick="addEmptyRow()"><i class="fas fa-plus"></i> Agregar Fila</button>
                            </div>
                            <div class="card-body-custom" style="padding: 1rem;">
                                <div class="table-container">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th style="width: 45%;">Producto / Descripción</th>
                                                <th style="width: 15%;">Cantidad</th>
                                                <th style="width: 20%;">P. Unit (S/)</th>
                                                <th style="width: 15%;">Importe (S/)</th>
                                                <th style="width: 5%;"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="itemsBody">
                                            <!-- Pre-populated by JS -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Columna Derecha: Pago y Resumen -->
                    <div style="display:flex; flex-direction:column; gap:1.5rem;">
                        <div class="card-panel">
                            <div class="card-header">
                                <h3><i class="fas fa-cash-register" style="color:var(--primary);margin-right:0.5rem;"></i>Pago & Resumen</h3>
                            </div>
                            <div class="card-body-custom" style="padding: 1.5rem;">
                                <div style="margin-bottom: 1.2rem;">
                                    <label>Método de Pago</label>
                                    <select name="metodo_pago" class="form-control">
                                        <?php 
                                        $metodos = ['Efectivo', 'BCP', 'BBVA', 'Yape', 'Plin', 'Interbank', 'Visa', 'Mastercard', 'Otros Bancos'];
                                        foreach ($metodos as $m): 
                                        ?>
                                        <option value="<?= $m ?>" <?= (strcasecmp($nota['metodo_pago'], $m) === 0) ? 'selected' : '' ?>><?= $m ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div style="margin-bottom: 1.5rem;">
                                    <label>Observaciones</label>
                                    <textarea name="observaciones" class="form-control" rows="3" placeholder="Observaciones o notas adicionales..."><?= htmlspecialchars($nota['observaciones'] ?? '') ?></textarea>
                                </div>

                                <div style="background:var(--bg-secondary); padding:1rem; border-radius:8px; margin-bottom:1.5rem; text-align:right;">
                                    <span style="font-size:0.85rem; color:var(--text-secondary); display:block; margin-bottom:0.2rem;">TOTAL COMPROBANTE</span>
                                    <span id="lblTotal" style="font-size:1.8rem; font-weight:800; color:var(--primary-light);">S/ <?= number_format($nota['total'], 2) ?></span>
                                </div>

                                <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; padding:0.8rem;">
                                    <i class="fas fa-save"></i> Guardar Cambios
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const productosData = <?= json_encode($productos_inventario) ?>;
    const detallesExistentes = <?= json_encode($detalles) ?>;
    let rowCounter = 0;

    function addRow(cant = 1, desc = '', price = 0) {
        rowCounter++;
        const tbody = document.getElementById('itemsBody');
        const tr = document.createElement('tr');
        tr.id = `row_${rowCounter}`;

        let selectOptions = '<option value="">-- Seleccionar o escribir libremente --</option>';
        productosData.forEach(p => {
            const codeBadge = p.codigo ? `[${p.codigo}] ` : '';
            selectOptions += `<option value="${p.id}" data-codigo="${p.codigo || ''}" data-nombre="${p.nombre}" data-precio="${p.precio_venta}">${codeBadge}${p.nombre} (S/ ${parseFloat(p.precio_venta).toFixed(2)})</option>`;
        });

        tr.innerHTML = `
            <td>
                <select class="form-control" onchange="onSelectProd(this, ${rowCounter})" style="font-size:0.82rem; margin-bottom:0.3rem;">
                    ${selectOptions}
                </select>
                <input type="text" name="items[${rowCounter}][descripcion]" id="desc_${rowCounter}" class="form-control" placeholder="Descripción del producto o mueble" value="${desc}" required>
            </td>
            <td>
                <input type="number" min="1" step="1" name="items[${rowCounter}][cantidad]" id="cant_${rowCounter}" class="form-control" value="${cant}" oninput="calcRow(${rowCounter})" required>
            </td>
            <td>
                <input type="number" min="0" step="0.01" name="items[${rowCounter}][precio_unitario]" id="price_${rowCounter}" class="form-control" value="${parseFloat(price).toFixed(2)}" oninput="calcRow(${rowCounter})" required>
            </td>
            <td>
                <input type="text" id="imp_${rowCounter}" class="form-control" value="${(cant * price).toFixed(2)}" readonly style="font-weight:bold;">
            </td>
            <td style="text-align:center;">
                <button type="button" class="btn-icon" onclick="removeRow(${rowCounter})" style="color:var(--danger);"><i class="fas fa-trash"></i></button>
            </td>
        `;

        tbody.appendChild(tr);
        calcTotal();
    }

    function addEmptyRow() {
        addRow(1, '', 0);
    }

    function onSelectProd(select, idx) {
        const opt = select.options[select.selectedIndex];
        if (select.value) {
            document.getElementById(`desc_${idx}`).value = opt.dataset.nombre || '';
            document.getElementById(`price_${idx}`).value = parseFloat(opt.dataset.precio || 0).toFixed(2);
            calcRow(idx);
        }
    }

    function calcRow(idx) {
        const cant = parseFloat(document.getElementById(`cant_${idx}`)?.value || 0);
        const price = parseFloat(document.getElementById(`price_${idx}`)?.value || 0);
        const imp = cant * price;
        const impInput = document.getElementById(`imp_${idx}`);
        if (impInput) impInput.value = imp.toFixed(2);
        calcTotal();
    }

    function removeRow(idx) {
        const tr = document.getElementById(`row_${idx}`);
        if (tr) {
            tr.remove();
            calcTotal();
        }
    }

    function calcTotal() {
        let total = 0;
        document.querySelectorAll('input[id^="imp_"]').forEach(inp => {
            total += parseFloat(inp.value || 0);
        });
        document.getElementById('lblTotal').textContent = 'S/ ' + total.toFixed(2);
    }

    document.addEventListener('DOMContentLoaded', () => {
        if (detallesExistentes && detallesExistentes.length > 0) {
            detallesExistentes.forEach(d => {
                addRow(d.cantidad, d.descripcion, d.precio_unitario);
            });
        } else {
            addEmptyRow();
        }
    });
</script>
</body>
</html>
