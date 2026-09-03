<?php
require_once __DIR__ . '/../auth/check_auth.php';
require_once __DIR__ . '/../config/db.php';

$page_title = 'Cuentas por Pagar';
$page_subtitle = 'Gestión de letras, facturas y créditos con proveedores';

// ——— Consulta principal ———
$search   = $_GET['search']  ?? '';
$estado   = $_GET['estado']  ?? '';
$sql = "
    SELECT cp.*, p.nombre AS proveedor_nombre
    FROM cuentas_pagar cp
    LEFT JOIN proveedores p ON cp.proveedor_id = p.id
    WHERE 1=1
";
$params = [];
if (!empty($search)) {
    $sql .= " AND (p.nombre ILIKE :search OR cp.numero_operacion ILIKE :search OR cp.banco ILIKE :search)";
    $params[':search'] = '%' . $search . '%';
}
if (!empty($estado)) {
    $sql .= " AND cp.estado = :estado";
    $params[':estado'] = $estado;
}
$sql .= " ORDER BY cp.fecha_vencimiento ASC";

try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $cuentas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $cuentas = [];
    $error_msg = $e->getMessage();
}

// ——— Proveedores para el combo ———
$proveedores = $db->query("SELECT id, nombre FROM proveedores ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

// ——— Totales resumen ———
$totalPendiente = 0; $totalPagado = 0; $totalVencido = 0;
foreach ($cuentas as $c) {
    if ($c['estado'] === 'pendiente' || $c['estado'] === 'pago_parcial') {
        if ($c['fecha_vencimiento'] < date('Y-m-d')) $totalVencido += $c['monto'];
        else $totalPendiente += $c['monto'];
    }
    if ($c['estado'] === 'pagado') $totalPagado += $c['monto'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cuentas por Pagar — Carpicenter</title>
    <link rel="stylesheet" href="/carpicenter_sys/assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(4px);
            z-index: 200;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .modal-overlay.open {
            display: flex;
        }
        .modal-box {
            background: var(--bg-card);
            border-radius: 16px;
            border: 1px solid var(--border-color);
            width: 100%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            animation: fadeUp 0.25s ease;
            box-shadow: 0 15px 30px rgba(0,0,0,0.4);
        }
        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.2rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }
        .modal-body {
            padding: 1.5rem;
        }
        .modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 0.7rem;
        }
        .upload-area-sys {
            border: 2px dashed var(--border-color);
            border-radius: 8px;
            padding: 1.2rem;
            text-align: center;
            cursor: pointer;
            background: var(--bg-primary);
            transition: var(--transition);
        }
        .upload-area-sys:hover {
            border-color: var(--primary);
            background: var(--primary-alpha-5);
        }
        .file-thumb-sys {
            position: relative;
            width: 60px;
            height: 60px;
            border-radius: 6px;
            overflow: hidden;
            border: 1px solid var(--border-color);
        }
        .file-thumb-sys img { width: 100%; height: 100%; object-fit: cover; }
    </style>
</head>
<body>
<div class="app-wrapper">
    <?php include 'partials/sidebar.php'; ?>
    <div class="main-content">
        <?php include 'partials/header.php'; ?>
        <div class="page-content">

            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h2><i class="fas fa-money-bill-wave" style="color:var(--primary);margin-right:0.5rem;"></i>Cuentas por Pagar</h2>
                    <p>Gestión de letras, facturas y créditos con proveedores</p>
                </div>
                <button class="btn btn-primary" onclick="openModal()"><i class="fas fa-plus"></i> Nueva Cuenta</button>
            </div>

            <!-- Stats Counters -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon orange"><i class="fas fa-clock"></i></div>
                    <div class="stat-info">
                        <p>Pendiente</p>
                        <h3>S/ <?= number_format($totalPendiente, 2) ?></h3>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon red"><i class="fas fa-exclamation-triangle"></i></div>
                    <div class="stat-info">
                        <p>Vencido</p>
                        <h3>S/ <?= number_format($totalVencido, 2) ?></h3>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-info">
                        <p>Pagado</p>
                        <h3>S/ <?= number_format($totalPagado, 2) ?></h3>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon blue"><i class="fas fa-list-ol"></i></div>
                    <div class="stat-info">
                        <p>Total Registros</p>
                        <h3><?= count($cuentas) ?></h3>
                    </div>
                </div>
            </div>

            <!-- Filter Controls Form -->
            <form method="GET" style="display:flex;gap:0.8rem;align-items:flex-end;flex-wrap:wrap;margin-bottom:1.2rem;">
                <div class="search-box" style="flex:2;min-width:220px;">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Buscar por proveedor, banco, N° operación...">
                </div>
                <select name="estado" class="form-control" style="width:auto;min-width:150px;" onchange="this.form.submit()">
                    <option value="">Todos los Estados</option>
                    <option value="pendiente" <?= $estado === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                    <option value="pago_parcial" <?= $estado === 'pago_parcial' ? 'selected' : '' ?>>Pago Parcial</option>
                    <option value="pagado" <?= $estado === 'pagado' ? 'selected' : '' ?>>Pagado</option>
                </select>
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filtrar</button>
                <?php if($search || $estado): ?>
                    <a href="cuentas_pagar.php" class="btn btn-outline"><i class="fas fa-times"></i> Limpiar</a>
                <?php endif; ?>
            </form>

            <?php if(isset($error_msg)): ?>
                <div style="background:rgba(198,40,40,0.15);border:1px solid var(--primary);border-radius:8px;padding:1rem;margin-bottom:1rem;color:var(--primary-light);">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_msg) ?>
                </div>
            <?php endif; ?>

            <!-- Table Panel -->
            <div class="card-panel">
                <div class="card-body-custom" style="padding:0;">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Proveedor</th>
                                <th>Tipo</th>
                                <th>Monto</th>
                                <th>F. Emisión</th>
                                <th>F. Vencimiento</th>
                                <th>Banco</th>
                                <th>N° Operación</th>
                                <th>Estado</th>
                                <th>Docs</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($cuentas)): ?>
                            <tr>
                                <td colspan="11" style="text-align:center;padding:3rem;color:var(--text-muted);">
                                    <i class="fas fa-inbox" style="font-size:2rem;display:block;margin-bottom:0.8rem;"></i>
                                    No hay cuentas por pagar registradas.
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach($cuentas as $c): 
                            $vencida = ($c['estado'] !== 'pagado' && $c['fecha_vencimiento'] < date('Y-m-d'));
                            $badgeClass = $vencida ? 'badge-danger' : ($c['estado'] === 'pagado' ? 'badge-success' : ($c['estado'] === 'pago_parcial' ? 'badge-info' : 'badge-warning'));
                            $badgeLabel = $vencida ? 'VENCIDO' : strtoupper(str_replace('_',' ',$c['estado']));

                            $stmtDocs = $db->prepare("SELECT COUNT(*) FROM documentos_adjuntos WHERE referencia_id=:id AND tipo LIKE 'cp_%'");
                            $stmtDocs->execute([':id'=>$c['id']]);
                            $numDocs = $stmtDocs->fetchColumn();
                        ?>
                            <tr>
                                <td><?= $c['id'] ?></td>
                                <td><strong><?= htmlspecialchars($c['proveedor_nombre'] ?? '-') ?></strong></td>
                                <td><?= htmlspecialchars(ucfirst($c['tipo_credito'])) ?></td>
                                <td><strong>S/ <?= number_format($c['monto'], 2) ?></strong></td>
                                <td><?= date('d/m/Y', strtotime($c['fecha_emision'])) ?></td>
                                <td style="<?= $vencida ? 'color:var(--red-light);font-weight:600;' : '' ?>">
                                    <?= date('d/m/Y', strtotime($c['fecha_vencimiento'])) ?>
                                </td>
                                <td><?= htmlspecialchars($c['banco'] ?: '-') ?></td>
                                <td><?= htmlspecialchars($c['numero_operacion'] ?: '-') ?></td>
                                <td><span class="badge <?= $badgeClass ?>"><?= $badgeLabel ?></span></td>
                                <td style="text-align:center;">
                                    <?php if($numDocs > 0): ?>
                                        <span class="badge badge-info"><i class="fas fa-paperclip"></i> <?= $numDocs ?></span>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn-icon" title="Adjuntar documento" onclick="openUploadModal(<?= $c['id'] ?>)"><i class="fas fa-paperclip"></i></button>
                                    <button class="btn-icon" title="Editar Cuenta" onclick="editCuenta(<?= htmlspecialchars(json_encode($c)) ?>)"><i class="fas fa-edit"></i></button>
                                    <button class="btn-icon" title="Eliminar Cuenta" style="color:var(--red-light);" onclick="deleteCuenta(<?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['proveedor_nombre'])) ?>')"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- MODAL: Crear / Editar Cuenta por Pagar -->
<div class="modal-overlay" id="modalCuenta">
    <div class="modal-box">
        <div class="modal-header">
            <h3 id="modalTitle"><i class="fas fa-plus-circle" style="color:var(--primary);margin-right:0.5rem;"></i>Nueva Cuenta por Pagar</h3>
            <button onclick="closeModal()" style="background:none;border:none;color:var(--text-muted);font-size:1.2rem;cursor:pointer;"><i class="fas fa-times"></i></button>
        </div>
        <form id="formCuenta" action="cuentas_pagar_process.php" method="POST" enctype="multipart/form-data">
            <div class="modal-body">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="id" id="formId" value="">

                <div class="form-row">
                    <div class="form-group">
                        <label for="fProveedor">Proveedor <span style="color:var(--primary-light)">*</span></label>
                        <select name="proveedor_id" id="fProveedor" class="form-control" required>
                            <option value="">Selecciona un Proveedor</option>
                            <?php foreach($proveedores as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="fTipo">Tipo de crédito <span style="color:var(--primary-light)">*</span></label>
                        <select name="tipo_credito" id="fTipo" class="form-control" required>
                            <option value="letra">Letra</option>
                            <option value="factura">Factura</option>
                            <option value="otro">Otro</option>
                        </select>
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
                    <div class="form-group">
                        <label for="fMonto">Monto (S/) <span style="color:var(--primary-light)">*</span></label>
                        <input type="number" name="monto" id="fMonto" class="form-control" step="0.01" min="0" placeholder="0.00" required>
                    </div>
                    <div class="form-group">
                        <label for="fFechaEmision">Fecha Emisión <span style="color:var(--primary-light)">*</span></label>
                        <input type="date" name="fecha_emision" id="fFechaEmision" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="fFechaVencimiento">Fecha Vencimiento <span style="color:var(--primary-light)">*</span></label>
                        <input type="date" name="fecha_vencimiento" id="fFechaVencimiento" class="form-control" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="fBanco">Banco</label>
                        <input type="text" name="banco" id="fBanco" class="form-control" placeholder="Ej: BCP, BBVA, Scotiabank">
                    </div>
                    <div class="form-group">
                        <label for="fNumOp">N° Operación / Letra</label>
                        <input type="text" name="numero_operacion" id="fNumOp" class="form-control" placeholder="Número de letra u operación">
                    </div>
                </div>

                <div class="form-group">
                    <label for="fEstado">Estado de Pago</label>
                    <select name="estado" id="fEstado" class="form-control">
                        <option value="pendiente">Pendiente</option>
                        <option value="pago_parcial">Pago Parcial</option>
                        <option value="pagado">Pagado</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="fObs">Observaciones</label>
                    <textarea name="observaciones" id="fObs" class="form-control" rows="2" placeholder="Notas adicionales o condiciones de crédito..."></textarea>
                </div>

                <div class="form-group">
                    <label>Adjuntar Documentos (PDF, JPG, PNG)</label>
                    <div class="upload-area-sys" id="dropZone" onclick="document.getElementById('fileInput').click()">
                        <i class="fas fa-cloud-upload-alt" style="font-size:1.5rem;color:var(--text-muted);margin-bottom:0.3rem;"></i>
                        <p style="font-size:0.8rem;color:var(--text-secondary);margin:0;">Haz clic o arrastra tus archivos aquí</p>
                    </div>
                    <input type="file" id="fileInput" name="documentos[]" multiple accept=".pdf,.jpg,.jpeg,.png" style="display:none;">
                    <div class="file-preview" id="filePreview" style="display:flex;gap:0.5rem;margin-top:0.5rem;"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeModal()" class="btn btn-outline">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar Cuenta</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: Adjuntar Documento a Cuenta Existente -->
<div class="modal-overlay" id="modalUpload">
    <div class="modal-box" style="max-width:450px;">
        <div class="modal-header">
            <h3><i class="fas fa-paperclip" style="color:var(--primary);margin-right:0.5rem;"></i>Adjuntar Documento</h3>
            <button onclick="closeUploadModal()" style="background:none;border:none;color:var(--text-muted);font-size:1.2rem;cursor:pointer;"><i class="fas fa-times"></i></button>
        </div>
        <form id="formUpload" action="/carpicenter_sys/upload_document.php" method="POST" enctype="multipart/form-data">
            <div class="modal-body">
                <input type="hidden" name="referencia_id" id="uploadRefId">
                <input type="hidden" name="tipo" value="cp_documento">

                <div class="form-group">
                    <label>Tipo de Documento</label>
                    <select name="subtipo" class="form-control">
                        <option value="letra">Letra de cambio</option>
                        <option value="factura_proveedor">Factura Proveedor</option>
                        <option value="cargo_entrega">Cargo de Entrega</option>
                        <option value="otro">Otro</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Seleccionar Archivo</label>
                    <div class="upload-area-sys" onclick="document.getElementById('fileInput2').click()">
                        <i class="fas fa-file-upload" style="font-size:1.5rem;color:var(--text-muted);margin-bottom:0.3rem;"></i>
                        <p style="font-size:0.8rem;color:var(--text-secondary);margin:0;">Selecciona un archivo PDF, JPG o PNG</p>
                    </div>
                    <input type="file" id="fileInput2" name="documento" accept=".pdf,.jpg,.jpeg,.png" style="display:none;">
                    <div id="filePreview2" style="margin-top:0.5rem;"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeUploadModal()" class="btn btn-outline">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Subir Documento</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: Eliminar Cuenta -->
<div class="modal-overlay" id="modalDelete">
    <div class="modal-box" style="max-width:400px;text-align:center;">
        <div class="modal-header">
            <h3><i class="fas fa-exclamation-triangle" style="color:var(--primary);margin-right:0.5rem;"></i>Confirmar Eliminación</h3>
            <button onclick="document.getElementById('modalDelete').classList.remove('open')" style="background:none;border:none;color:var(--text-muted);font-size:1.2rem;cursor:pointer;"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <p id="deleteMsg" style="color:var(--text-secondary);font-size:0.9rem;"></p>
        </div>
        <div class="modal-footer" style="justify-content:center;">
            <form id="formDelete" action="cuentas_pagar_process.php" method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="deleteId">
                <button type="button" onclick="document.getElementById('modalDelete').classList.remove('open')" class="btn btn-outline">Cancelar</button>
                <button type="submit" class="btn btn-primary" style="background:var(--primary);"><i class="fas fa-trash"></i> Eliminar</button>
            </form>
        </div>
    </div>
</div>

<script>
function openModal() {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus-circle" style="color:var(--primary);margin-right:0.5rem;"></i>Nueva Cuenta por Pagar';
    document.getElementById('formCuenta').reset();
    document.getElementById('formId').value = '';
    document.getElementById('formAction').value = 'create';
    document.getElementById('filePreview').innerHTML = '';
    document.getElementById('fFechaEmision').value = new Date().toISOString().substring(0,10);
    document.getElementById('modalCuenta').classList.add('open');
}

function closeModal() {
    document.getElementById('modalCuenta').classList.remove('open');
}

function openUploadModal(id) {
    document.getElementById('uploadRefId').value = id;
    document.getElementById('modalUpload').classList.add('open');
}

function closeUploadModal() {
    document.getElementById('modalUpload').classList.remove('open');
}

function deleteCuenta(id, nombre) {
    document.getElementById('deleteId').value = id;
    document.getElementById('deleteMsg').textContent = `¿Estás seguro de eliminar la cuenta por pagar del proveedor ${nombre}?`;
    document.getElementById('modalDelete').classList.add('open');
}

function editCuenta(data) {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit" style="color:var(--primary);margin-right:0.5rem;"></i>Editar Cuenta por Pagar';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('formId').value = data.id;
    document.getElementById('fProveedor').value = data.proveedor_id;
    document.getElementById('fTipo').value = data.tipo_credito;
    document.getElementById('fMonto').value = data.monto;
    document.getElementById('fFechaEmision').value = data.fecha_emision ? data.fecha_emision.substring(0,10) : '';
    document.getElementById('fFechaVencimiento').value = data.fecha_vencimiento ? data.fecha_vencimiento.substring(0,10) : '';
    document.getElementById('fBanco').value = data.banco || '';
    document.getElementById('fNumOp').value = data.numero_operacion || '';
    document.getElementById('fEstado').value = data.estado;
    document.getElementById('fObs').value = data.observaciones || '';
    document.getElementById('modalCuenta').classList.add('open');
}

document.querySelectorAll('.modal-overlay').forEach(m => {
    m.addEventListener('click', function(e) {
        if (e.target === m) m.classList.remove('open');
    });
});
</script>
</body>
</html>
