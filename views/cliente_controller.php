<?php
require_once __DIR__ . '/../auth/check_auth.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// GET: single client data for edit
if ($action === 'get') {
    $id = $_GET['id'] ?? null;
    $stmt = $db->prepare("SELECT * FROM clientes WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $c = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode($c ?: []);
    exit;
}

// GET/POST: delete client
if ($action === 'delete') {
    $id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID de cliente inválido.']);
        exit;
    }
    try {
        // 1. Eliminar archivos físicos de documentos adjuntos asociados
        $docs = $db->prepare("SELECT ruta FROM documentos_adjuntos WHERE referencia_id = :id AND tipo LIKE 'cliente_%'");
        $docs->execute([':id' => $id]);
        while ($doc = $docs->fetch(PDO::FETCH_ASSOC)) {
            $absPath = __DIR__ . '/../' . ltrim($doc['ruta'], '/');
            if (file_exists($absPath)) @unlink($absPath);
        }
        $db->prepare("DELETE FROM documentos_adjuntos WHERE referencia_id = :id AND tipo LIKE 'cliente_%'")->execute([':id' => $id]);

        // 2. Desvincular de tablas relacionadas (para no romper integridad)
        $db->prepare("UPDATE ventas SET cliente_id = NULL WHERE cliente_id = :id")->execute([':id' => $id]);
        $db->prepare("UPDATE contratos SET cliente_id = NULL WHERE cliente_id = :id")->execute([':id' => $id]);
        $db->prepare("UPDATE guias_remision SET cliente_id = NULL WHERE cliente_id = :id")->execute([':id' => $id]);

        // 3. Eliminar de la tabla clientes
        $stmt = $db->prepare("DELETE FROM clientes WHERE id = :id");
        $stmt->execute([':id' => $id]);
        echo json_encode(['success' => true, 'message' => 'Cliente eliminado correctamente.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error al eliminar cliente: ' . $e->getMessage()]);
    }
    exit;
}

// GET: ficha HTML 360° with Expediente Digital & Commercial History
if ($action === 'ficha') {
    header('Content-Type: text/html');
    $id = intval($_GET['id'] ?? 0);
    $stmt = $db->prepare("SELECT * FROM clientes WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $c = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$c) { echo '<p style="color:red; text-align:center; padding:2rem;">Cliente no encontrado.</p>'; exit; }

    // 1. Contratos del cliente
    $stmtCont = $db->prepare("
        SELECT id, codigo_completo, fecha_entrega_estimada, monto_total, monto_adelanto, monto_saldo, estado_contrato, estado_produccion, created_at 
        FROM contratos 
        WHERE cliente_id = :id 
        ORDER BY id DESC
    ");
    $stmtCont->execute([':id' => $id]);
    $contratos = $stmtCont->fetchAll(PDO::FETCH_ASSOC);

    // 2. Cotizaciones del cliente
    $cots = $db->prepare("
        SELECT id, numero, fecha, total, estado 
        FROM cotizaciones 
        WHERE (cliente_documento IS NOT NULL AND cliente_documento = :doc AND cliente_documento <> '')
           OR cliente_nombre = :nombre
        ORDER BY id DESC LIMIT 10
    ");
    $cots->execute([':doc' => $c['dni_ruc'], ':nombre' => $c['nombre']]);
    $cotizaciones = $cots->fetchAll(PDO::FETCH_ASSOC);

    // 3. Notas de Venta del cliente
    $nvs = $db->prepare("
        SELECT id, numero, fecha, total, metodo_pago, estado 
        FROM notas_venta 
        WHERE (cliente_documento IS NOT NULL AND cliente_documento = :doc AND cliente_documento <> '') OR cliente_nombre = :nombre 
        ORDER BY id DESC LIMIT 10
    ");
    $nvs->execute([':doc' => $c['dni_ruc'], ':nombre' => $c['nombre']]);
    $notasVenta = $nvs->fetchAll(PDO::FETCH_ASSOC);

    // 4. Expediente Digital (Documentos Adjuntos)
    $docsStmt = $db->prepare("
        SELECT id, tipo, ruta, fecha_subida 
        FROM documentos_adjuntos 
        WHERE referencia_id = :id AND tipo LIKE 'cliente_%' 
        ORDER BY id DESC
    ");
    $docsStmt->execute([':id' => $id]);
    $documentos = $docsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Totales Financieros 360°
    $totalContratado = array_sum(array_column($contratos, 'monto_total'));
    $totalAdelantos = array_sum(array_column($contratos, 'monto_adelanto'));
    $totalSaldoPendiente = array_sum(array_column($contratos, 'monto_saldo'));

    $isEmpresa = (($c['tipo_cliente'] ?? '') === 'Empresa') || (strtolower($c['tipo_doc'] ?? '') === 'ruc') || (strlen($c['dni_ruc'] ?? '') === 11);
    $tipoLabel = $isEmpresa ? 'Empresa' : 'Persona Natural';
    
    // WhatsApp direct link
    $phoneClean = preg_replace('/[^0-9]/', '', $c['telefono'] ?? '');
    $wsUrl = null;
    if (strlen($phoneClean) >= 9) {
        $phoneNum = (strlen($phoneClean) === 9) ? '51' . $phoneClean : $phoneClean;
        $wsUrl = "https://wa.me/" . $phoneNum;
    }

    ob_start();
    ?>
    <div style="padding: 1.2rem;">

        <!-- 1. CABECERA PRINCIPAL DEL CLIENTE -->
        <div style="background:var(--bg-primary); border-radius:14px; padding:1.2rem 1.4rem; margin-bottom:1.2rem; border:1px solid var(--border-color);">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:1rem; flex-wrap:wrap;">
                <div style="display:flex; align-items:center; gap:1.2rem;">
                    <div style="width:56px; height:56px; border-radius:50%; background:<?= $isEmpresa ? 'rgba(21,101,192,0.15)' : 'rgba(198,40,40,0.15)' ?>; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:1.3rem; color:<?= $isEmpresa ? '#42A5F5' : 'var(--primary-light)' ?>; border:2px solid <?= $isEmpresa ? '#42A5F5' : 'var(--primary)' ?>; flex-shrink:0;">
                        <?= strtoupper(substr($c['nombre'], 0, 1)) ?>
                    </div>
                    <div>
                        <h3 style="margin:0 0 0.3rem 0; font-size:1.2rem; font-weight:800; color:var(--text-color);"><?= htmlspecialchars($c['nombre']) ?></h3>
                        <?php if($c['razon_social']): ?>
                            <div style="font-size:0.85rem; color:var(--text-muted); font-weight:600; margin-bottom:0.3rem;"><i class="fas fa-building" style="color:#42A5F5;"></i> <?= htmlspecialchars($c['razon_social']) ?></div>
                        <?php endif; ?>
                        <div style="display:flex; gap:0.6rem; align-items:center; flex-wrap:wrap; font-size:0.8rem;">
                            <span class="badge <?= $isEmpresa ? 'badge-info' : 'badge-primary' ?>" style="font-size:0.72rem; padding:0.2rem 0.6rem;">
                                <i class="fas <?= $isEmpresa ? 'fa-building' : 'fa-user' ?>"></i> <?= $tipoLabel ?>
                            </span>
                            <span style="color:var(--text-muted); font-weight:600;">
                                <i class="fas fa-id-card"></i> <?= htmlspecialchars($c['tipo_doc'] ?? 'DNI') ?>: <strong><?= htmlspecialchars($c['dni_ruc'] ?? '—') ?></strong>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- ACCIONES RÁPIDAS -->
                <div style="display:flex; gap:0.6rem; align-items:center;">
                    <?php if($wsUrl): ?>
                        <a href="<?= $wsUrl ?>" target="_blank" class="btn" style="background:#25D366; color:#fff; font-weight:700; font-size:0.83rem; border-radius:8px; padding:0.5rem 1rem; text-decoration:none; display:inline-flex; align-items:center; gap:0.4rem; box-shadow:0 2px 8px rgba(37,211,102,0.3);" title="Enviar mensaje por WhatsApp Web">
                            <i class="fab fa-whatsapp" style="font-size:1.1rem;"></i> WhatsApp
                        </a>
                    <?php endif; ?>
                    <?php if(!empty($c['email'])): ?>
                        <a href="mailto:<?= htmlspecialchars($c['email']) ?>" class="btn btn-outline" style="font-size:0.82rem; padding:0.5rem 0.9rem; display:inline-flex; align-items:center; gap:0.4rem;">
                            <i class="fas fa-envelope"></i> Correo
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- DETALLES DE CONTACTO -->
            <div style="display:flex; gap:1.8rem; align-items:center; flex-wrap:wrap; margin-top:1rem; padding-top:0.9rem; border-top:1px dashed var(--border-color); font-size:0.83rem;">
                <div><i class="fas fa-phone" style="color:var(--primary); width:18px;"></i> <strong>Teléfono:</strong> <?= htmlspecialchars($c['telefono'] ?? 'No registrado') ?></div>
                <div style="flex:1; min-width:220px;"><i class="fas fa-map-marker-alt" style="color:var(--primary); width:18px;"></i> <strong>Dirección:</strong> <?= htmlspecialchars(($c['direccion'] ?? '') . ($c['ciudad'] ? ', '.$c['ciudad'] : '')) ?: 'No registrada' ?></div>
                <div><i class="fas fa-calendar-check" style="color:var(--primary); width:18px;"></i> <strong>Registrado:</strong> <?= $c['created_at'] ? date('d/m/Y', strtotime($c['created_at'])) : '—' ?></div>
            </div>
        </div>

        <!-- 2. RESUMEN FINANCIERO 360° -->
        <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:1rem; margin-bottom:1.2rem;">
            <div style="background:var(--bg-primary); border:1px solid var(--border-color); border-radius:12px; padding:1rem; text-align:center;">
                <div style="font-size:0.72rem; color:var(--text-muted); text-transform:uppercase; font-weight:700; letter-spacing:0.04em;">Total Contratado</div>
                <div style="font-size:1.4rem; font-weight:800; color:var(--primary-light); margin:0.3rem 0 0.1rem 0;">S/ <?= number_format($totalContratado, 2) ?></div>
                <div style="font-size:0.73rem; color:var(--text-muted);"><?= count($contratos) ?> contrato(s) activos</div>
            </div>
            <div style="background:var(--bg-primary); border:1px solid var(--border-color); border-radius:12px; padding:1rem; text-align:center;">
                <div style="font-size:0.72rem; color:var(--text-muted); text-transform:uppercase; font-weight:700; letter-spacing:0.04em;">Total Pagado / Adelanto</div>
                <div style="font-size:1.4rem; font-weight:800; color:#2e7d32; margin:0.3rem 0 0.1rem 0;">S/ <?= number_format($totalAdelantos, 2) ?></div>
                <div style="font-size:0.73rem; color:#2e7d32; font-weight:600;"><i class="fas fa-check-circle"></i> Cobrado efectivamente</div>
            </div>
            <div style="background:var(--bg-primary); border:1px solid var(--border-color); border-radius:12px; padding:1rem; text-align:center;">
                <div style="font-size:0.72rem; color:var(--text-muted); text-transform:uppercase; font-weight:700; letter-spacing:0.04em;">Saldo Pendiente de Cobro</div>
                <div style="font-size:1.4rem; font-weight:800; color:<?= $totalSaldoPendiente > 0 ? '#ef5350' : '#2e7d32' ?>; margin:0.3rem 0 0.1rem 0;">S/ <?= number_format($totalSaldoPendiente, 2) ?></div>
                <div style="font-size:0.73rem; color:<?= $totalSaldoPendiente > 0 ? '#ef5350' : '#2e7d32' ?>; font-weight:600;">
                    <?= $totalSaldoPendiente > 0 ? '⚠️ Pendiente por cobrar' : '✅ Sin deuda' ?>
                </div>
            </div>
        </div>

        <?php if($c['notas']): ?>
        <div style="background:rgba(249,168,37,0.08); border:1px solid rgba(249,168,37,0.25); border-radius:10px; padding:0.7rem 1rem; margin-bottom:1.2rem; font-size:0.83rem;">
            <i class="fas fa-sticky-note" style="color:#F9A825; margin-right:0.4rem;"></i> <strong>Notas del Cliente:</strong> <?= htmlspecialchars($c['notas']) ?>
        </div>
        <?php endif; ?>

        <!-- 3. NAVEGACIÓN PESTAÑAS (TABS) -->
        <div style="display:flex; border-bottom:2px solid var(--border-color); margin-bottom:1.2rem; gap:0.5rem;">
            <button class="ficha-tab-btn active" onclick="switchFichaTab('tabHistorial', this)" style="padding:10px 18px; border:none; background:none; font-weight:700; font-size:0.88rem; color:var(--primary); border-bottom:3px solid var(--primary); cursor:pointer;">
                <i class="fas fa-file-contract"></i> Historial Comercial (<?= count($contratos) + count($cotizaciones) + count($notasVenta) ?>)
            </button>
            <button class="ficha-tab-btn" onclick="switchFichaTab('tabExpediente', this)" style="padding:10px 18px; border:none; background:none; font-weight:600; font-size:0.88rem; color:var(--text-muted); border-bottom:3px solid transparent; cursor:pointer;">
                <i class="fas fa-folder-open"></i> Expediente Digital (<?= count($documentos) ?> archivos)
            </button>
        </div>

        <!-- PESTAÑA 1: HISTORIAL COMERCIAL -->
        <div id="tabHistorial" class="ficha-tab-content" style="display:block;">
            <div style="margin-bottom:1.5rem;">
                <h4 style="margin:0 0 0.8rem 0; font-size:0.88rem; font-weight:800; color:var(--text-color); text-transform:uppercase;">
                    <i class="fas fa-hammer" style="color:var(--primary);"></i> Contratos de Fabricación (<?= count($contratos) ?>)
                </h4>
                <?php if(empty($contratos)): ?>
                    <div style="font-size:0.82rem; color:var(--text-muted); text-align:center; padding:1.2rem; background:var(--bg-primary); border-radius:10px; border:1px solid var(--border-color);">
                        No hay contratos registrados para este cliente.
                    </div>
                <?php else: ?>
                    <div style="background:var(--bg-primary); border:1px solid var(--border-color); border-radius:10px; overflow:hidden;">
                        <table class="cli-table" style="font-size:0.82rem;">
                            <thead>
                                <tr>
                                    <th>N° Contrato</th>
                                    <th>Fecha</th>
                                    <th>Estado Contrato</th>
                                    <th>Fabricación Taller</th>
                                    <th>Monto Total</th>
                                    <th>Adelanto</th>
                                    <th>Saldo Pendiente</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($contratos as $cont): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($cont['codigo_completo']) ?></strong></td>
                                    <td><?= date('d/m/Y', strtotime($cont['created_at'])) ?></td>
                                    <td><span class="badge badge-info" style="font-size:0.7rem;"><?= htmlspecialchars($cont['estado_contrato']) ?></span></td>
                                    <td>
                                        <?php 
                                        $prodSt = $cont['estado_produccion'] ?? 'Pendiente';
                                        $pClass = 'badge-warning';
                                        if($prodSt === 'Terminado en Almacén' || $prodSt === 'Fabricación Completa (Listo para Logística)') $pClass = 'badge-success';
                                        elseif($prodSt === 'En Fabricación') $pClass = 'badge-info';
                                        ?>
                                        <span class="badge <?= $pClass ?>" style="font-size:0.7rem;"><?= htmlspecialchars($prodSt) ?></span>
                                    </td>
                                    <td><strong>S/ <?= number_format($cont['monto_total'], 2) ?></strong></td>
                                    <td style="color:#2e7d32; font-weight:600;">S/ <?= number_format($cont['monto_adelanto'], 2) ?></td>
                                    <td style="color:<?= $cont['monto_saldo'] > 0 ? '#ef5350' : '#2e7d32' ?>; font-weight:700;">
                                        S/ <?= number_format($cont['monto_saldo'], 2) ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- COTIZACIONES Y NOTAS DE VENTA -->
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                <div>
                    <h4 style="margin:0 0 0.6rem 0; font-size:0.82rem; font-weight:800; color:var(--text-muted); text-transform:uppercase;">
                        <i class="fas fa-file-invoice"></i> Cotizaciones Emitidas (<?= count($cotizaciones) ?>)
                    </h4>
                    <?php if(empty($cotizaciones)): ?>
                        <p style="font-size:0.8rem; color:var(--text-muted); font-style:italic;">Sin cotizaciones anteriores.</p>
                    <?php else: ?>
                        <div style="display:grid; gap:0.4rem;">
                            <?php foreach($cotizaciones as $cot): ?>
                            <div style="background:var(--bg-primary); padding:8px 12px; border-radius:8px; display:flex; justify-content:space-between; align-items:center; border:1px solid var(--border-color); font-size:0.82rem;">
                                <span><strong><?= htmlspecialchars($cot['numero']) ?></strong> <small style="color:var(--text-muted);">(<?= date('d/m/Y', strtotime($cot['fecha'])) ?>)</small></span>
                                <strong style="color:var(--primary-light);">S/ <?= number_format($cot['total'], 2) ?></strong>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div>
                    <h4 style="margin:0 0 0.6rem 0; font-size:0.82rem; font-weight:800; color:var(--text-muted); text-transform:uppercase;">
                        <i class="fas fa-receipt"></i> Notas de Venta (<?= count($notasVenta) ?>)
                    </h4>
                    <?php if(empty($notasVenta)): ?>
                        <p style="font-size:0.8rem; color:var(--text-muted); font-style:italic;">Sin notas de venta registradas.</p>
                    <?php else: ?>
                        <div style="display:grid; gap:0.4rem;">
                            <?php foreach($notasVenta as $nv): ?>
                            <div style="background:var(--bg-primary); padding:8px 12px; border-radius:8px; display:flex; justify-content:space-between; align-items:center; border:1px solid var(--border-color); font-size:0.82rem;">
                                <span><strong><?= htmlspecialchars($nv['numero']) ?></strong> <small style="color:var(--text-muted);">(<?= date('d/m/Y', strtotime($nv['fecha'])) ?>)</small></span>
                                <strong style="color:#2e7d32;">S/ <?= number_format($nv['total'], 2) ?></strong>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- PESTAÑA 2: EXPEDIENTE DIGITAL (ARCHIVOS ADJUNTOS) -->
        <div id="tabExpediente" class="ficha-tab-content" style="display:none;">
            
            <!-- EXPLICACIÓN Y FORMULARIO DE SUBIDA -->
            <div style="background:var(--bg-primary); border:1px solid var(--border-color); border-radius:12px; padding:1.2rem; margin-bottom:1.4rem;">
                <h4 style="margin:0 0 0.4rem 0; font-size:0.92rem; font-weight:800;">
                    <i class="fas fa-cloud-upload-alt" style="color:var(--primary);"></i> Subir Archivo o Documento Físico
                </h4>
                <p style="font-size:0.78rem; color:var(--text-muted); margin-bottom:1rem;">
                    Adjunta copias escaneadas o fotografías de DNI, Contratos firmados, Vouchers de pago o Planos de muebles para mantener la evidencia resguardada en la ficha del cliente.
                </p>

                <form id="formUploadClienteDoc" onsubmit="uploadClienteDoc(event, <?= $id ?>)">
                    <div style="display:grid; grid-template-columns: 1fr 1fr 130px; gap:0.8rem; align-items:end;">
                        <div>
                            <label style="font-size:0.75rem; font-weight:700; color:var(--text-muted); display:block; margin-bottom:0.3rem;">Tipo de Documento *</label>
                            <select name="subtipo" class="form-control" style="font-size:0.83rem;" required>
                                <option value="dni">🪪 DNI / RUC / Ficha RUC</option>
                                <option value="contrato">📑 Contrato Firmado</option>
                                <option value="voucher">💳 Voucher / Comprobante de Pago</option>
                                <option value="plano">📐 Plano / Boceto / Medidas</option>
                                <option value="otro">📋 Otro Documento</option>
                            </select>
                        </div>
                        <div>
                            <label style="font-size:0.75rem; font-weight:700; color:var(--text-muted); display:block; margin-bottom:0.3rem;">Archivo (PDF, JPG, PNG máx 5MB) *</label>
                            <input type="file" name="documento" class="form-control" style="font-size:0.8rem; padding:5px;" accept="image/jpeg,image/png,application/pdf" required>
                        </div>
                        <div>
                            <button type="submit" id="btnUploadClienteDoc" class="btn btn-success btn-sm" style="width:100%; padding:8px 0; font-size:0.83rem; font-weight:700;">
                                <i class="fas fa-upload"></i> Subir Archivo
                            </button>
                        </div>
                    </div>
                    <div id="msgUploadClienteDoc" style="display:none;"></div>
                </form>
            </div>

            <!-- ARCHIVOS RESPALDADOS EN EL SISTEMA -->
            <h4 style="margin:0 0 0.8rem 0; font-size:0.9rem; font-weight:800; color:var(--text-color);">
                <i class="fas fa-archive" style="color:var(--primary);"></i> Documentos Guardados en el Expediente (<?= count($documentos) ?>)
            </h4>
            
            <?php if(empty($documentos)): ?>
                <div style="text-align:center; padding:2.5rem 1rem; background:var(--bg-primary); border-radius:12px; border:1px solid var(--border-color); color:var(--text-muted); font-size:0.85rem;">
                    <i class="fas fa-folder-open" style="font-size:2.2rem; margin-bottom:0.6rem; display:block; opacity:0.4;"></i>
                    No hay documentos físicos o imágenes adjuntas aún en el expediente de este cliente.
                </div>
            <?php else: ?>
                <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); gap:1rem;">
                    <?php 
                    $subtipoLabels = [
                        'cliente_dni' => ['icon' => 'fa-id-card', 'label' => 'DNI / RUC', 'color' => '#1565C0'],
                        'cliente_contrato' => ['icon' => 'fa-file-signature', 'label' => 'Contrato Firmado', 'color' => '#C62828'],
                        'cliente_voucher' => ['icon' => 'fa-file-invoice-dollar', 'label' => 'Voucher de Pago', 'color' => '#2E7D32'],
                        'cliente_plano' => ['icon' => 'fa-drafting-compass', 'label' => 'Plano / Boceto', 'color' => '#E65100'],
                        'cliente_otro' => ['icon' => 'fa-paperclip', 'label' => 'Otro Documento', 'color' => '#616161']
                    ];

                    foreach($documentos as $doc):
                        $info = $subtipoLabels[$doc['tipo']] ?? ['icon' => 'fa-file', 'label' => 'Documento', 'color' => '#333'];
                        $ext = strtolower(pathinfo($doc['ruta'], PATHINFO_EXTENSION));
                        $isImg = in_array($ext, ['jpg', 'jpeg', 'png']);
                        $docUrl = '/carpicenter_sys/' . ltrim($doc['ruta'], '/');
                    ?>
                    <div style="background:var(--bg-primary); border:1px solid var(--border-color); border-radius:12px; padding:0.9rem; display:flex; flex-direction:column; justify-content:space-between;">
                        <div>
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.6rem;">
                                <span class="badge" style="background:<?= $info['color'] ?>; color:#fff; font-size:0.7rem; padding:4px 8px;">
                                    <i class="fas <?= $info['icon'] ?>"></i> <?= $info['label'] ?>
                                </span>
                                <small style="font-size:0.7rem; color:var(--text-muted);"><?= date('d/m/Y', strtotime($doc['fecha_subida'])) ?></small>
                            </div>

                            <?php if($isImg): ?>
                                <div style="height:100px; border-radius:8px; overflow:hidden; background:#000; margin-bottom:0.6rem; display:flex; align-items:center; justify-content:center;">
                                    <img src="<?= $docUrl ?>" alt="Doc" style="max-height:100%; max-width:100%; object-fit:contain;">
                                </div>
                            <?php else: ?>
                                <div style="height:100px; border-radius:8px; background:rgba(21,101,192,0.08); margin-bottom:0.6rem; display:flex; flex-direction:column; align-items:center; justify-content:center; color:#1565C0;">
                                    <i class="fas fa-file-pdf" style="font-size:2.2rem; margin-bottom:0.3rem;"></i>
                                    <span style="font-size:0.72rem; font-weight:700;">Documento PDF</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div style="display:flex; gap:0.4rem; justify-content:space-between; align-items:center; margin-top:0.4rem;">
                            <a href="<?= $docUrl ?>" target="_blank" class="btn btn-outline btn-sm" style="font-size:0.75rem; padding:5px 8px; flex:1; text-align:center;">
                                <i class="fas fa-external-link-alt"></i> Ver / Abrir
                            </a>
                            <button type="button" class="btn btn-sm" style="background:none; border:none; color:#ef5350; cursor:pointer; padding:5px 8px;" onclick="deleteClienteDoc(<?= $doc['id'] ?>, <?= $id ?>)" title="Eliminar archivo">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- JS TABS & UPLOAD HANDLER -->
    <script>
    function switchFichaTab(tabId, btn) {
        document.querySelectorAll('.ficha-tab-content').forEach(el => el.style.display = 'none');
        document.querySelectorAll('.ficha-tab-btn').forEach(el => {
            el.style.color = 'var(--text-muted)';
            el.style.borderBottom = '3px solid transparent';
            el.style.fontWeight = '600';
        });
        document.getElementById(tabId).style.display = 'block';
        btn.style.color = 'var(--primary)';
        btn.style.borderBottom = '3px solid var(--primary)';
        btn.style.fontWeight = '700';
    }

    function uploadClienteDoc(e, clienteId) {
        e.preventDefault();
        const form = document.getElementById('formUploadClienteDoc');
        const btn = document.getElementById('btnUploadClienteDoc');
        const msg = document.getElementById('msgUploadClienteDoc');

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Subiendo...';
        msg.style.display = 'none';

        const formData = new FormData(form);
        formData.append('referencia_id', clienteId);
        formData.append('tipo', 'cliente');

        fetch('/carpicenter_sys/upload_document.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(res => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-upload"></i> Subir Archivo';
            if (res.success) {
                verCliente(clienteId);
            } else {
                msg.style.cssText = 'display:block; background:#ffebee; color:#c62828; padding:8px 12px; border-radius:6px; font-size:0.8rem; margin-top:10px; border:1px solid #ef9a9a;';
                msg.innerHTML = '<i class="fas fa-exclamation-circle"></i> Error: ' + (res.error || 'No se pudo cargar el archivo.');
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-upload"></i> Subir Archivo';
            alert('Error al subir el archivo.');
        });
    }

    function deleteClienteDoc(docId, clienteId) {
        if (!confirm('¿Está seguro de eliminar este documento adjunto del expediente?')) return;
        const fd = new FormData();
        fd.append('action', 'delete_doc');
        fd.append('doc_id', docId);
        fd.append('cliente_id', clienteId);

        fetch('/carpicenter_sys/views/cliente_controller.php', {
            method: 'POST',
            body: fd
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                verCliente(clienteId);
            } else {
                alert('Error: ' + res.message);
            }
        });
    }
    </script>
    <?php
    echo ob_get_clean();
    exit;
}

// POST: delete_doc action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'delete_doc')) {
    $doc_id = intval($_POST['doc_id'] ?? 0);
    $cliente_id = intval($_POST['cliente_id'] ?? 0);
    try {
        $stmt = $db->prepare("SELECT id, ruta FROM documentos_adjuntos WHERE id = :id AND referencia_id = :cid AND tipo LIKE 'cliente_%'");
        $stmt->execute([':id' => $doc_id, ':cid' => $cliente_id]);
        $doc = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($doc) {
            $absPath = __DIR__ . '/../' . ltrim($doc['ruta'], '/');
            if (file_exists($absPath)) {
                @unlink($absPath);
            }
            $stmtDel = $db->prepare("DELETE FROM documentos_adjuntos WHERE id = :id");
            $stmtDel->execute([':id' => $doc_id]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Documento no encontrado.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// POST: create / update / delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? null;

    if ($action === 'delete') {
        try {
            $db->prepare("DELETE FROM clientes WHERE id = :id")->execute([':id' => $id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    $fields = [
        'nombre'       => trim($_POST['nombre'] ?? ''),
        'dni_ruc'      => trim($_POST['dni_ruc'] ?? ''),
        'tipo_doc'     => $_POST['tipo_doc'] ?? 'DNI',
        'tipo_cliente' => $_POST['tipo_cliente'] ?? 'Persona Natural',
        'razon_social' => trim($_POST['razon_social'] ?? ''),
        'telefono'     => trim($_POST['telefono'] ?? ''),
        'email'        => trim($_POST['email'] ?? ''),
        'direccion'    => trim($_POST['direccion'] ?? ''),
        'ciudad'       => trim($_POST['ciudad'] ?? ''),
        'estado'       => $_POST['estado'] ?? 'Activo',
        'notas'        => trim($_POST['notas'] ?? '')
    ];

    if (empty($fields['nombre'])) {
        echo json_encode(['success' => false, 'message' => 'El nombre es obligatorio.']);
        exit;
    }

    try {
        if ($action === 'create') {
            $sql = "INSERT INTO clientes (nombre, dni_ruc, tipo_doc, tipo_cliente, razon_social, telefono, email, direccion, ciudad, estado, notas)
                    VALUES (:nombre,:dni_ruc,:tipo_doc,:tipo_cliente,:razon_social,:telefono,:email,:direccion,:ciudad,:estado,:notas)";
        } else {
            $sql = "UPDATE clientes SET nombre=:nombre, dni_ruc=:dni_ruc, tipo_doc=:tipo_doc, tipo_cliente=:tipo_cliente,
                    razon_social=:razon_social, telefono=:telefono, email=:email, direccion=:direccion,
                    ciudad=:ciudad, estado=:estado, notas=:notas WHERE id=:id";
            $fields[':id'] = $id;
        }
        $stmt = $db->prepare($sql);
        // rename keys
        $p = [];
        foreach ($fields as $k => $v) $p[":$k"] = $v;
        if ($action === 'update') $p[':id'] = $id;
        $stmt->execute($p);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
