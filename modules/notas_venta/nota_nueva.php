<?php
require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../config/db.php';

// Obtener el siguiente correlativo
try {
    $stmt = $db->query("
        SELECT numero 
        FROM notas_venta 
        WHERE numero LIKE 'T001-%'
        ORDER BY id DESC 
        LIMIT 1
    ");
    $last_numero = $stmt->fetchColumn();
    if ($last_numero) {
        $parts = explode('-', $last_numero);
        $num = intval($parts[1]) + 1;
    } else {
        $num = 1;
    }
    $next_number = 'T001-' . str_pad($num, 6, '0', STR_PAD_LEFT);

    // Obtener productos para el autocompletador
    $stmtProd = $db->query("SELECT id, nombre, codigo, precio_venta FROM productos ORDER BY nombre ASC");
    $productos_inventario = $stmtProd->fetchAll(PDO::FETCH_ASSOC);

    // Obtener locales/almacenes
    $stmtLoc = $db->query("SELECT id, nombre, tipo FROM locales ORDER BY id ASC");
    $locales_list = $stmtLoc->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error en base de datos: " . $e->getMessage());
}

$page_title = 'Nueva Nota de Venta';
$page_subtitle = 'Crear comprobante interno de venta libre';
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
        .form-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem;
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
    <?php include '../../views/partials/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../views/partials/header.php'; ?>
        <div class="page-content">
            <div class="page-header" style="margin-bottom: 1.5rem;">
                <div>
                    <h2>Registrar Nota de Venta</h2>
                    <p>Comprobante de uso interno y rápido</p>
                </div>
                <a href="notas_venta.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Volver</a>
            </div>

            <form action="nota_save.php" method="POST" id="notaForm">
                <div class="grid-2-1">
                    <!-- Columna Izquierda: Datos de la Nota y Detalles -->
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
                                        <input type="text" name="numero" id="numero" class="form-control" value="<?= htmlspecialchars($next_number) ?>" readonly style="font-weight:bold; letter-spacing:1px; color:var(--primary-light);">
                                    </div>
                                    <div>
                                        <label>Establecimiento / Origen <span style="color:red">*</span></label>
                                        <select name="local_id" class="form-control" required>
                                            <?php foreach ($locales_list as $loc): ?>
                                            <option value="<?= $loc['id'] ?>" <?= ($user_local_id == $loc['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($loc['nombre']) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label>Fecha <span style="color:red">*</span></label>
                                        <input type="date" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>" required>
                                    </div>
                                    <div>
                                        <label>Vendedor</label>
                                        <div class="readonly-value" style="font-weight: 500; height: calc(100% - 1.5rem); display: flex; align-items: center;">
                                            <?= htmlspecialchars($_SESSION['nombre_completo'] ?? $_SESSION['username'] ?? 'Vendedor') ?>
                                        </div>
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
                                        <input type="text" name="cliente_nombre" class="form-control" placeholder="Nombre completo del cliente" required>
                                    </div>
                                    <div>
                                        <label>DNI / RUC (Opcional)</label>
                                        <input type="text" name="cliente_documento" class="form-control" placeholder="DNI o RUC">
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div>
                                        <label>Dirección (Opcional)</label>
                                        <input type="text" name="cliente_direccion" class="form-control" placeholder="Dirección del cliente">
                                    </div>
                                    <div>
                                        <label>Teléfono (Opcional)</label>
                                        <input type="text" name="cliente_telefono" class="form-control" placeholder="Teléfono/Celular">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Detalle de Productos -->
                        <div class="card-panel">
                            <div class="card-header">
                                <h3><i class="fas fa-boxes" style="color:var(--primary);margin-right:0.5rem;"></i>Productos / Detalles</h3>
                                <button type="button" class="btn btn-success btn-sm" onclick="addItemRow()"><i class="fas fa-plus"></i> Agregar Fila</button>
                            </div>
                            <div class="card-body-custom" style="padding: 0;">
                                <div class="table-container">
                                    <table id="detallesTable">
                                        <thead>
                                            <tr>
                                                <th style="width: 10%;">Cant</th>
                                                <th>Descripción / Producto</th>
                                                <th style="width: 20%; text-align:right;">P. Unitario</th>
                                                <th style="width: 20%; text-align:right;">Importe</th>
                                                <th style="width: 10%; text-align:center;">Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Dynamic rows populated via JS -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Columna Derecha: Totales y Método de Pago -->
                    <div style="display:flex; flex-direction:column; gap:1.5rem;">
                        
                        <!-- Panel de Totales -->
                        <div class="card-panel">
                            <div class="card-header">
                                <h3>Resumen Financiero</h3>
                            </div>
                            <div class="card-body-custom" style="padding: 1.5rem; display:flex; flex-direction:column; gap:1rem;">
                                <div style="display:flex; justify-content:space-between; font-size:0.95rem;">
                                    <span>Subtotal (Sin IGV):</span>
                                    <span id="labelSubtotal">S/ 0.00</span>
                                </div>
                                <div style="display:flex; justify-content:space-between; font-size:0.95rem;">
                                    <span>IGV (18%):</span>
                                    <span id="labelIgv">S/ 0.00</span>
                                </div>
                                <hr style="border:0; border-top:1px solid var(--border-color);">
                                <div style="display:flex; justify-content:space-between; font-weight:bold; font-size:1.4rem; color:var(--text-primary);">
                                    <span>TOTAL:</span>
                                    <span id="labelTotal">S/ 0.00</span>
                                </div>
                                <input type="hidden" name="total" id="inputTotal" value="0.00">

                                <div style="margin-top:1.5rem;">
                                    <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; padding:0.8rem; font-size:1rem;">
                                        <i class="fas fa-check-circle"></i> Guardar Nota
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Método de Pago -->
                        <div class="card-panel">
                            <div class="card-header">
                                <h3><i class="fas fa-credit-card" style="color:var(--primary);margin-right:0.5rem;"></i>Método de Pago</h3>
                            </div>
                            <div class="card-body-custom" style="padding: 1.5rem;">
                                <div class="form-group">
                                    <label>Seleccionar Método <span style="color:red">*</span></label>
                                    <select name="metodo_pago" class="form-control" required>
                                        <option value="Efectivo" selected>Efectivo</option>
                                        <option value="Yape">Yape</option>
                                        <option value="Plin">Plin</option>
                                        <option value="Transferencia">Transferencia</option>
                                        <option value="Otros">Otros</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Observaciones -->
                        <div class="card-panel">
                            <div class="card-header">
                                <h3><i class="fas fa-comment-alt" style="color:var(--primary);margin-right:0.5rem;"></i>Observaciones</h3>
                            </div>
                            <div class="card-body-custom" style="padding: 1.5rem;">
                                <div class="form-group" style="margin-bottom:0;">
                                    <textarea name="observaciones" class="form-control" rows="3" placeholder="Comentarios adicionales..."></textarea>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Datalist para autocompletar productos -->
<datalist id="productos-datalist">
    <?php foreach($productos_inventario as $prod): 
        $codeTag = !empty($prod['codigo']) ? '[' . $prod['codigo'] . '] ' : '';
    ?>
        <option value="<?= htmlspecialchars($codeTag . $prod['nombre']) ?>" data-price="<?= $prod['precio_venta'] ?>"></option>
    <?php endforeach; ?>
</datalist>

<script>
    let itemIndex = 0;

    // Agregar fila
    function addItemRow(data = {}) {
        const tbody = document.querySelector('#detallesTable tbody');
        const tr = document.createElement('tr');
        
        tr.innerHTML = `
            <td>
                <input type="number" name="items[${itemIndex}][cantidad]" class="form-control qty-input" value="${data.cantidad || 1}" min="0.01" step="any" required onchange="calculateRow(this)" style="padding: 0.5rem;">
            </td>
            <td>
                <input type="text" name="items[${itemIndex}][descripcion]" class="form-control desc-input" value="${data.descripcion || ''}" list="productos-datalist" placeholder="Escribe o selecciona un producto" required oninput="onProductDescriptionChange(this)" style="padding: 0.5rem;">
            </td>
            <td>
                <input type="number" step="0.01" name="items[${itemIndex}][precio_unitario]" class="form-control price-input" value="${data.precio_unitario || '0.00'}" required onchange="calculateRow(this)" style="text-align:right; padding: 0.5rem;">
            </td>
            <td>
                <input type="number" step="0.01" name="items[${itemIndex}][importe]" class="form-control subtotal-input" value="${data.importe || '0.00'}" readonly style="text-align:right; font-weight:bold; padding: 0.5rem; background: rgba(255,255,255,0.02);">
            </td>
            <td style="text-align:center;">
                <button type="button" class="btn-icon" onclick="removeRow(this)"><i class="fas fa-trash" style="color:var(--danger);"></i></button>
            </td>
        `;
        tbody.appendChild(tr);
        itemIndex++;
        calculateTotal();
    }

    // Remover fila
    function removeRow(btn) {
        const tbody = document.querySelector('#detallesTable tbody');
        if (tbody.rows.length > 1) {
            btn.closest('tr').remove();
            calculateTotal();
        } else {
            alert("Debe haber al menos un producto en el detalle.");
        }
    }

    // Cuando cambia el input de descripción, autocompletar el precio si coincide
    function onProductDescriptionChange(input) {
        const val = input.value;
        const datalist = document.getElementById('productos-datalist');
        const options = datalist.querySelectorAll('option');
        for (let i = 0; i < options.length; i++) {
            if (options[i].value === val) {
                const price = options[i].getAttribute('data-price');
                const tr = input.closest('tr');
                const priceInput = tr.querySelector('.price-input');
                priceInput.value = parseFloat(price).toFixed(2);
                calculateRow(priceInput);
                break;
            }
        }
    }

    // Calcular fila individual
    function calculateRow(element) {
        const tr = element.closest('tr');
        const qty = parseFloat(tr.querySelector('.qty-input').value) || 0;
        const price = parseFloat(tr.querySelector('.price-input').value) || 0;
        const subtotal = qty * price;
        tr.querySelector('.subtotal-input').value = subtotal.toFixed(2);
        calculateTotal();
    }

    // Calcular el total de la nota
    function calculateTotal() {
        let total = 0;
        document.querySelectorAll('.subtotal-input').forEach(input => {
            total += parseFloat(input.value) || 0;
        });
        
        const subtotalSinIgv = total / 1.18;
        const igv = total - subtotalSinIgv;

        document.getElementById('labelSubtotal').innerText = 'S/ ' + subtotalSinIgv.toFixed(2);
        document.getElementById('labelIgv').innerText = 'S/ ' + igv.toFixed(2);
        document.getElementById('labelTotal').innerText = 'S/ ' + total.toFixed(2);
        document.getElementById('inputTotal').value = total.toFixed(2);
    }

    // Cargar fila por defecto al inicio
    window.onload = () => {
        addItemRow();
    };
</script>

<?php include '../../views/partials/footer.php'; ?>
</body>
</html>
