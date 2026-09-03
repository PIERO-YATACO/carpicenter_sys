<?php
require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/transferencia_model.php';

if ($user_role !== 'Super Admin' && $user_role !== 'Almacén') {
    die("Acceso denegado. Solo Almacén puede realizar envíos.");
}

$model = new TransferenciaModel($db);
$locales = $model->getLocales();

$page_title = 'Nueva Transferencia';
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

            <form action="transferencia_controller.php" method="POST" id="transferForm" onsubmit="return validateForm()">
                <input type="hidden" name="action" value="create">

                <div class="card-panel">
                    <div class="card-header"><h3><i class="fas fa-map-marker-alt" style="color:var(--primary);margin-right:0.5rem;"></i>Detalles de Ruta</h3></div>
                    <div class="card-body-custom" style="padding: 1.5rem;">
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem;">
                            <div>
                                <label>Local de Origen <span style="color:red">*</span></label>
                                <select name="local_origen_id" id="local_origen" class="form-control" required onchange="loadStock()">
                                    <option value="">Seleccione Origen...</option>
                                    <?php foreach($locales as $l): ?>
                                    <option value="<?= $l['id'] ?>" <?= ($user_role === 'Almacén' && $l['nombre'] === 'Almacén Principal') ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($l['nombre']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label>Local de Destino <span style="color:red">*</span></label>
                                <select name="local_destino_id" id="local_destino" class="form-control" required>
                                    <option value="">Seleccione Destino...</option>
                                    <?php foreach($locales as $l): ?>
                                    <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem;">
                            <div class="form-group">
                                <label>Motivo del Movimiento</label>
                                <select name="motivo" class="form-control">
                                    <option value="Transferencia entre almacenes">Transferencia entre almacenes</option>
                                    <option value="Devolución">Devolución</option>
                                    <option value="Ingreso sin comprobante">Ingreso sin comprobante</option>
                                    <option value="Obsequio">Obsequio</option>
                                    <option value="Reposición de stock">Reposición de stock</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Observaciones / Transportista</label>
                                <input type="text" name="observaciones" class="form-control" placeholder="Nombre del transportista, placa, etc.">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-panel" style="margin-top: 1.5rem;">
                    <div class="card-header"><h3><i class="fas fa-boxes" style="color:var(--primary);margin-right:0.5rem;"></i>Productos a Enviar</h3></div>
                    <div class="card-body-custom" style="padding: 0;">
                        <table class="table-custom" id="productsTable">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Stock Disponible en Origen</th>
                                    <th style="width: 150px;">Cantidad a Enviar</th>
                                </tr>
                            </thead>
                            <tbody id="productsBody">
                                <tr><td colspan="3" style="text-align:center;">Seleccione un Local de Origen para ver productos con stock.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div style="text-align: right; margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-primary" id="btnSubmit" disabled><i class="fas fa-paper-plane"></i> Procesar y Enviar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function loadStock() {
        const origenId = document.getElementById('local_origen').value;
        const tbody = document.getElementById('productsBody');
        const btnSubmit = document.getElementById('btnSubmit');

        if (!origenId) {
            tbody.innerHTML = '<tr><td colspan="3" style="text-align:center;">Seleccione un Local de Origen para ver productos con stock.</td></tr>';
            btnSubmit.disabled = true;
            return;
        }

        tbody.innerHTML = '<tr><td colspan="3" style="text-align:center;"><i class="fas fa-spinner fa-spin"></i> Cargando stock...</td></tr>';
        
        fetch(`transferencia_controller.php?action=get_stock&local_id=${origenId}`)
            .then(res => res.json())
            .then(data => {
                if (data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="3" style="text-align:center; color:red;">No hay productos con stock en este local.</td></tr>';
                    btnSubmit.disabled = true;
                    return;
                }

                let html = '';
                data.forEach(p => {
                    const codeProd = p.codigo ? `<span class="doc-badge" style="font-size:0.75rem; font-weight:700; margin-right:4px;">${p.codigo}</span>` : '';
                    const codeCol = p.color_codigo ? `<span class="doc-badge" style="font-size:0.68rem; margin-left:4px;">${p.color_codigo}</span>` : '';
                    html += `
                    <tr>
                        <td>
                            <div style="font-weight:700;">${codeProd}${p.nombre}</div>
                            <small style="color:var(--text-muted);"><i class="fas fa-palette"></i> Color: <strong>${p.color_nombre}</strong> ${codeCol}</small>
                        </td>
                        <td><strong>${p.stock}</strong></td>
                        <td>
                            <input type="number" name="productos[${p.id}_${p.color_id}]" class="form-control" value="0" min="0" max="${p.stock}" onchange="checkQty(this, ${p.stock})">
                        </td>
                    </tr>
                    `;
                });
                tbody.innerHTML = html;
                btnSubmit.disabled = false;
            })
            .catch(err => {
                tbody.innerHTML = '<tr><td colspan="3" style="text-align:center; color:red;">Error al cargar stock.</td></tr>';
                console.error(err);
            });
    }

    function checkQty(input, maxStock) {
        if (parseInt(input.value) > maxStock) {
            alert('La cantidad no puede superar el stock disponible.');
            input.value = maxStock;
        }
        if (parseInt(input.value) < 0) input.value = 0;
    }

    function validateForm() {
        const origen = document.getElementById('local_origen').value;
        const destino = document.getElementById('local_destino').value;
        
        if (origen === destino) {
            alert("El local de origen y destino no pueden ser el mismo.");
            return false;
        }

        let totalQty = 0;
        document.querySelectorAll('input[name^="productos"]').forEach(input => {
            totalQty += parseInt(input.value) || 0;
        });

        if (totalQty === 0) {
            alert("Debe enviar al menos 1 producto.");
            return false;
        }

        return confirm("¿Está seguro de enviar esta transferencia? El stock se descontará del origen y quedará En Tránsito.");
    }
</script>
</body>
</html>
