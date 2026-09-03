<?php
require_once __DIR__ . '/../auth/check_auth.php';
require_once __DIR__ . '/../config/db.php';

$venta_id = $_GET['venta_id'] ?? null;
$venta_seleccionada = null;

if ($venta_id) {
    $stmtV = $db->prepare("
        SELECT v.*, c.nombre as cliente_nombre, c.dni_ruc as cliente_documento, c.direccion as cliente_direccion
        FROM ventas v
        LEFT JOIN clientes c ON v.cliente_id = c.id
        WHERE v.id = :id
    ");
    $stmtV->execute([':id' => $venta_id]);
    $venta_seleccionada = $stmtV->fetch(PDO::FETCH_ASSOC);
}

// Obtener todas las ventas para el selector
$stmtAllVentas = $db->query("
    SELECT v.id, v.tipo_comprobante, v.serie, v.numero, c.nombre as cliente_nombre
    FROM ventas v
    LEFT JOIN clientes c ON v.cliente_id = c.id
    ORDER BY v.fecha DESC
");
$all_ventas = $stmtAllVentas->fetchAll(PDO::FETCH_ASSOC);

// Auto-generar código de guía
$stmtLastGR = $db->query("
    SELECT codigo FROM guias_remision 
    WHERE codigo ~ '^GR-[0-9]+$'
    ORDER BY id DESC LIMIT 1
");
$last_gr = $stmtLastGR->fetchColumn();
if ($last_gr) {
    $num = intval(str_replace('GR-', '', $last_gr)) + 1;
} else {
    $num = 1;
}
$next_codigo = 'GR-' . str_pad($num, 4, '0', STR_PAD_LEFT);

$page_title = 'Nueva Guía de Remisión';
$page_subtitle = 'Generar documento de traslado de mercadería';
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
                    <h2>Registrar Guía de Remisión</h2>
                    <p>Crea una nueva guía para el despacho de pedidos</p>
                </div>
                <a href="guias.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Volver</a>
            </div>

            <form action="guia_nueva_process.php" method="POST" id="guiaForm">
                
                <div class="grid-2-1">
                    
                    <!-- Columna Izquierda: Detalles de la Guía -->
                    <div style="display:flex; flex-direction:column; gap:1.5rem;">
                        
                        <div class="card-panel">
                            <div class="card-header">
                                <h3><i class="fas fa-truck-loading" style="color:var(--primary);margin-right:0.5rem;"></i>Datos de Traslado</h3>
                            </div>
                            <div class="card-body-custom" style="padding: 1.5rem;">
                                
                                <div class="form-row">
                                    <div>
                                        <label>Código Guía</label>
                                        <input type="text" name="codigo" class="form-control" value="<?= htmlspecialchars($next_codigo) ?>" readonly style="font-weight:bold; color:var(--primary-light);">
                                    </div>
                                    <div>
                                        <label>Asociar a una Venta (Opcional)</label>
                                        <select name="venta_id" id="venta_id" class="form-control" onchange="autoFillFromSale()">
                                            <option value="">-- Sin Comprobante / Venta Libre --</option>
                                            <?php foreach ($all_ventas as $vt): 
                                                $lbl = !empty($vt['tipo_comprobante']) ? "{$vt['tipo_comprobante']} {$vt['serie']}-{$vt['numero']}" : "Interna VTA-{$vt['id']}";
                                                $selected = ($venta_id && $venta_id == $vt['id']) ? 'selected' : '';
                                            ?>
                                                <option value="<?= $vt['id'] ?>" <?= $selected ?> data-cliente="<?= htmlspecialchars($vt['cliente_nombre']) ?>">
                                                    <?= htmlspecialchars($lbl) ?> (<?= htmlspecialchars($vt['cliente_nombre']) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div>
                                        <label>Destinatario (Cliente / Razón Social) <span style="color:red">*</span></label>
                                        <input type="text" name="destinatario_nombre" id="destinatario_nombre" class="form-control" value="<?= $venta_seleccionada ? htmlspecialchars($venta_seleccionada['cliente_nombre']) : '' ?>" required>
                                    </div>
                                    <div>
                                        <label>Documento de Identidad (DNI / RUC)</label>
                                        <input type="text" name="destinatario_documento" id="destinatario_documento" class="form-control" value="<?= $venta_seleccionada ? htmlspecialchars($venta_seleccionada['cliente_documento']) : '' ?>">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Punto de Partida (Dirección de Origen)</label>
                                    <input type="text" name="punto_partida" class="form-control" value="Calle Unión Mz L1 Lt 33 Parque Industrial, Villa El Salvador, Lima">
                                </div>

                                <div class="form-group">
                                    <label>Punto de Llegada (Dirección de Destino) <span style="color:red">*</span></label>
                                    <input type="text" name="punto_llegada" id="punto_llegada" class="form-control" value="<?= $venta_seleccionada ? htmlspecialchars($venta_seleccionada['cliente_direccion']) : '' ?>" required>
                                </div>

                                <div class="form-row">
                                    <div>
                                        <label>Motivo de Traslado</label>
                                        <select name="motivo_traslado" class="form-control">
                                            <option value="Venta" selected>Venta</option>
                                            <option value="Traslado entre locales">Traslado entre locales</option>
                                            <option value="Compra">Compra</option>
                                            <option value="Devolución">Devolución</option>
                                            <option value="Otros">Otros</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label>Fecha de Emisión</label>
                                        <input type="date" name="fecha_emision" class="form-control" value="<?= date('Y-m-d') ?>" required>
                                    </div>
                                </div>

                                <div class="form-group" style="margin-bottom:0;">
                                    <label>Observaciones</label>
                                    <textarea name="observaciones" class="form-control" rows="3" placeholder="Ingresa notas adicionales para el transportista..."></textarea>
                                </div>

                            </div>
                        </div>

                    </div>

                    <!-- Columna Derecha: Resumen de Reglas y Confirmación -->
                    <div style="display:flex; flex-direction:column; gap:1.5rem;">
                        
                        <div class="card-panel">
                            <div class="card-header">
                                <h3>Acciones</h3>
                            </div>
                            <div class="card-body-custom" style="padding: 1.5rem;">
                                <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; padding:0.8rem; font-size:1rem;">
                                    <i class="fas fa-save"></i> Guardar Guía
                                </button>
                            </div>
                        </div>

                        <div class="card-panel">
                            <div class="card-header">
                                <h3>Regla de Facturación</h3>
                            </div>
                            <div class="card-body-custom" style="padding:1.2rem; font-size:0.85rem; line-height:1.5;">
                                <p style="margin-bottom:0.8rem; color:var(--text-secondary);">
                                    El estado de facturación se establece de la siguiente manera:
                                </p>
                                <div style="display:flex; flex-direction:column; gap:0.5rem; background:rgba(255,255,255,0.03); border:1px solid var(--border-color); padding:0.8rem; border-radius:8px;">
                                    <div style="display:flex; align-items:center; gap:0.5rem;">
                                        <span class="badge badge-success" style="font-size:0.65rem;">FACTURADA</span>
                                        <span style="color:var(--text-muted);">Si tiene venta asociada</span>
                                    </div>
                                    <div style="display:flex; align-items:center; gap:0.5rem;">
                                        <span class="badge badge-warning" style="font-size:0.65rem;">NO_FACTURADA</span>
                                        <span style="color:var(--text-muted);">Si no tiene venta asociada</span>
                                    </div>
                                </div>
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
    // Variable con datos de ventas para autofill en JS si se cambia select
    const ventasData = <?= json_encode($all_ventas) ?>;

    function autoFillFromSale() {
        const ventaId = document.getElementById('venta_id').value;
        if (!ventaId) return;

        // AJAX para obtener detalles completos de la venta y autocompletar destinatario y dirección
        fetch(`/carpicenter_sys/views/venta_view_json.php?id=${ventaId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('destinatario_nombre').value = data.cliente_nombre || '';
                    document.getElementById('destinatario_documento').value = data.cliente_documento || '';
                    document.getElementById('punto_llegada').value = data.cliente_direccion || '';
                }
            })
            .catch(err => console.error("Error al autocompletar desde venta:", err));
    }
</script>
</body>
</html>
