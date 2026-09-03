<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/contrato_model.php';

$token = $_GET['token'] ?? '';
if (empty($token)) {
    die("Token de firma no válido o enlace incompleto.");
}

$model = new ContratoModel($db);
$contrato = $model->getByToken($token);

if (!$contrato) {
    die("El contrato no existe o el enlace ha caducado.");
}

// Handle AJAX signature POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['firma_base64'])) {
    header('Content-Type: application/json');
    $firma = trim($_POST['firma_base64']);
    if (empty($firma) || strpos($firma, 'data:image') !== 0) {
        echo json_encode(['success' => false, 'message' => 'Firma no válida.']);
        exit;
    }
    $ok = $model->saveFirmaByToken($token, $firma);
    if ($ok) {
        echo json_encode(['success' => true, 'message' => 'Firma registrada correctamente.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'No se pudo guardar la firma.']);
    }
    exit;
}

$ya_firmado = !empty($contrato['firma_digital']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Firma de Contrato <?= htmlspecialchars($contrato['codigo_completo']) ?> — Industrias Carpicenter</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #F1F5F9;
            color: #0F172A;
            -webkit-font-smoothing: antialiased;
            padding: 1.2rem 0.8rem;
        }

        .main-card {
            max-width: 580px;
            margin: 0 auto;
            background: #FFFFFF;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.06), 0 1px 3px rgba(0,0,0,0.04);
            border: 1px solid #E2E8F0;
        }

        /* Top Header Brand */
        .brand-header {
            background: #FFFFFF;
            padding: 1.5rem 1.2rem 1.2rem;
            text-align: center;
            border-bottom: 1px solid #F1F5F9;
            position: relative;
        }
        .brand-header img.brand-logo {
            height: 46px;
            max-width: 85%;
            object-fit: contain;
            display: inline-block;
            margin-bottom: 0.85rem;
        }
        .doc-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #FEF2F2;
            color: #DC2626;
            border: 1px solid #FECACA;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.76rem;
            font-weight: 800;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .doc-title {
            font-size: 1.2rem;
            font-weight: 900;
            color: #0F172A;
            margin: 0.5rem 0 0.2rem;
        }
        .doc-subtitle {
            font-size: 0.82rem;
            color: #64748B;
        }

        .card-body-content {
            padding: 1.4rem 1.3rem;
        }

        /* Information Panels */
        .info-panel {
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 14px;
            padding: 1.1rem 1.2rem;
            margin-bottom: 1.2rem;
        }
        .info-panel-title {
            font-size: 0.76rem;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            font-weight: 800;
            color: #64748B;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .data-row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-bottom: 0.45rem;
            font-size: 0.88rem;
        }
        .data-row:last-child {
            margin-bottom: 0;
        }
        .data-row .lbl {
            color: #64748B;
            font-weight: 500;
            font-size: 0.82rem;
        }
        .data-row .val {
            color: #0F172A;
            font-weight: 700;
            text-align: right;
        }

        /* Products list */
        .product-item-card {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 10px;
            padding: 0.8rem 0.95rem;
            margin-bottom: 0.55rem;
        }
        .product-item-card:last-child {
            margin-bottom: 0;
        }
        .prod-head {
            display: flex;
            justify-content: space-between;
            font-weight: 700;
            font-size: 0.88rem;
            color: #0F172A;
            margin-bottom: 3px;
        }
        .prod-specs-txt {
            font-size: 0.78rem;
            color: #64748B;
            line-height: 1.4;
        }

        /* Financial summary */
        .financial-box {
            background: #FFFFFF;
            border: 1.5px solid #E2E8F0;
            border-radius: 14px;
            padding: 1.1rem;
            margin-bottom: 1.2rem;
        }
        .fin-line {
            display: flex;
            justify-content: space-between;
            font-size: 0.88rem;
            padding: 4px 0;
        }
        .fin-total-hero {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0 0.2rem;
            margin-top: 0.5rem;
            border-top: 1px dashed #CBD5E1;
        }
        .fin-total-hero .total-lbl {
            font-size: 0.95rem;
            font-weight: 800;
            color: #0F172A;
        }
        .fin-total-hero .total-num {
            font-size: 1.25rem;
            font-weight: 900;
            color: #0F172A;
        }

        /* Signature Canvas Box */
        .signature-section {
            background: #FFFFFF;
            border: 1.5px solid #E2E8F0;
            border-radius: 16px;
            padding: 1.2rem;
            margin-bottom: 1.2rem;
            text-align: center;
        }
        .sig-header-txt {
            font-weight: 800;
            font-size: 0.95rem;
            color: #0F172A;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin-bottom: 3px;
        }
        .sig-sub-txt {
            font-size: 0.8rem;
            color: #64748B;
            margin-bottom: 0.9rem;
        }
        .canvas-touch-wrapper {
            position: relative;
            background: #FAFAFA;
            border: 2px dashed #CBD5E1;
            border-radius: 12px;
            overflow: hidden;
            touch-action: none;
        }
        .canvas-pad {
            display: block;
            width: 100%;
            height: 220px;
            cursor: crosshair;
        }
        .hint-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: #94A3B8;
            font-size: 0.85rem;
            font-weight: 600;
            pointer-events: none;
            user-select: none;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* Buttons */
        .btn-action-primary {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 1rem 1.3rem;
            background: linear-gradient(135deg, #E31E24 0%, #B71C1C 100%);
            color: #FFFFFF;
            border: none;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 800;
            cursor: pointer;
            text-decoration: none;
            box-shadow: 0 6px 16px rgba(227,30,36,0.25);
            transition: all 0.2s ease;
            margin-top: 0.85rem;
        }
        .btn-action-primary:hover {
            opacity: 0.95;
            transform: translateY(-1px);
        }
        .btn-action-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .btn-action-secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 0.55rem 1rem;
            background: #F1F5F9;
            color: #475569;
            border: 1px solid #CBD5E1;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 700;
            cursor: pointer;
            margin-top: 0.6rem;
            transition: all 0.15s ease;
        }
        .btn-action-secondary:hover {
            background: #E2E8F0;
            color: #0F172A;
        }

        /* Legal clauses note */
        .legal-notice {
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 10px;
            padding: 0.8rem 0.9rem;
            font-size: 0.73rem;
            color: #64748B;
            line-height: 1.45;
            margin-bottom: 1.1rem;
        }

        /* Success screen card */
        .success-hero-card {
            text-align: center;
            padding: 2rem 1.2rem 1.5rem;
        }
        .success-seal-badge {
            width: 72px;
            height: 72px;
            background: #ECFDF5;
            border: 3px solid #10B981;
            color: #059669;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-bottom: 1rem;
            box-shadow: 0 4px 15px rgba(16,185,129,0.2);
            animation: popIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        @keyframes popIn {
            0% { transform: scale(0.5); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }

        .cert-card {
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 14px;
            padding: 1.2rem;
            margin: 1.3rem 0;
            text-align: center;
        }
        .cert-img-wrap {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 10px;
            padding: 0.75rem;
            margin-top: 0.6rem;
            display: inline-block;
            max-width: 100%;
        }
        .cert-img-wrap img {
            max-width: 220px;
            max-height: 80px;
            display: block;
            margin: 0 auto;
        }

        .footer-brand-note {
            text-align: center;
            font-size: 0.73rem;
            color: #94A3B8;
            margin-top: 1.3rem;
            padding-top: 0.8rem;
            border-top: 1px solid #F1F5F9;
        }
    </style>
</head>
<body>

<div class="main-card">
    
    <!-- Top Corporate Header -->
    <div class="brand-header">
        <div>
            <img src="/carpicenter_sys/assets/img/logo_text_brand.png" alt="INDUSTRIAS CARPICENTER" class="brand-logo">
        </div>
        <div class="doc-tag">
            <i class="fas fa-file-contract"></i> Contrato de Venta a Pedido
        </div>
        <h1 class="doc-title">N° <?= htmlspecialchars($contrato['codigo_completo']) ?></h1>
        <p class="doc-subtitle">Industrias Carpicenter S.A.C. · RUC: 20555889616</p>
    </div>

    <div class="card-body-content">

        <?php if ($ya_firmado): ?>
            <!-- Signed State -->
            <div class="success-hero-card">
                <div class="success-seal-badge">
                    <i class="fas fa-check"></i>
                </div>
                <h2 style="font-size:1.35rem; font-weight:900; color:#0F172A; margin-bottom:0.35rem;">
                    ¡Contrato Firmado y Confirmado!
                </h2>
                <p style="font-size:0.86rem; color:#64748B; line-height:1.45;">
                    Estimado(a) <strong><?= htmlspecialchars($contrato['cliente_nombre']) ?></strong>, tu firma de conformidad ha sido registrada exitosamente en el sistema de Carpicenter.
                </p>

                <!-- Certification Details -->
                <div class="cert-card">
                    <span style="font-size:0.72rem; text-transform:uppercase; font-weight:800; color:#64748B; letter-spacing:0.5px; display:block;">
                        <i class="fas fa-certificate" style="color:#059669;"></i> Firma Digital Registrada
                    </span>
                    <div class="cert-img-wrap">
                        <img src="<?= htmlspecialchars($contrato['firma_digital']) ?>" alt="Firma del Cliente">
                    </div>
                    <div style="font-size:0.75rem; color:#059669; font-weight:700; margin-top:0.55rem; display:flex; align-items:center; justify-content:center; gap:5px;">
                        <i class="fas fa-shield-halved"></i> Conformidad Registrada <?= !empty($contrato['fecha_firma']) ? 'el ' . date('d/m/Y H:i', strtotime($contrato['fecha_firma'])) : '' ?>
                    </div>
                </div>

                <!-- Financial Quick Recap -->
                <div class="info-panel" style="text-align:left; margin-bottom:1.3rem;">
                    <div class="data-row">
                        <span class="lbl">Total del Contrato:</span>
                        <span class="val" style="font-size:0.95rem;">S/ <?= number_format($contrato['monto_total'], 2) ?></span>
                    </div>
                    <div class="data-row">
                        <span class="lbl">Abono Adelantado (A Cuenta):</span>
                        <span class="val" style="color:#059669;">S/ <?= number_format($contrato['monto_adelanto'], 2) ?></span>
                    </div>
                    <div class="data-row" style="border-top:1px dashed #CBD5E1; padding-top:4px; margin-top:4px;">
                        <span class="lbl" style="font-weight:700; color:#DC2626;">Saldo Pendiente contra entrega:</span>
                        <span class="val" style="color:#DC2626; font-size:1.05rem;">S/ <?= number_format($contrato['monto_saldo'], 2) ?></span>
                    </div>
                </div>

                <!-- Action Button to View/Download PDF -->
                <a href="/carpicenter_sys/modules/contratos/contrato_print.php?id=<?= $contrato['id'] ?>" target="_blank" class="btn-action-primary">
                    <i class="fas fa-file-pdf"></i> Ver / Descargar Contrato en PDF
                </a>
            </div>

        <?php else: ?>
            <!-- Signing State -->

            <!-- Block: Client Info -->
            <div class="info-panel">
                <div class="info-panel-title">
                    <i class="fas fa-user-check" style="color:#DC2626;"></i> Información del Cliente
                </div>
                <div class="data-row">
                    <span class="lbl">Cliente:</span>
                    <span class="val"><?= htmlspecialchars($contrato['cliente_nombre']) ?></span>
                </div>
                <div class="data-row">
                    <span class="lbl">DNI / RUC:</span>
                    <span class="val"><?= htmlspecialchars($contrato['cliente_doc'] ?: '—') ?></span>
                </div>
                <div class="data-row">
                    <span class="lbl">Teléfono:</span>
                    <span class="val"><?= htmlspecialchars($contrato['cliente_telefono'] ?: '—') ?></span>
                </div>
                <div class="data-row">
                    <span class="lbl">Entrega:</span>
                    <span class="val"><?= htmlspecialchars($contrato['tipo_entrega']) ?></span>
                </div>
                <?php if (!empty($contrato['fecha_entrega_estimada'])): ?>
                <div class="data-row">
                    <span class="lbl">Fecha Estimada:</span>
                    <span class="val" style="color:#D97706;"><?= date('d/m/Y', strtotime($contrato['fecha_entrega_estimada'])) ?></span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Block: Products Details -->
            <div class="info-panel">
                <div class="info-panel-title">
                    <i class="fas fa-couch" style="color:#2563EB;"></i> Detalle del Pedido
                </div>
                <?php foreach ($contrato['detalles'] as $item): ?>
                <div class="product-item-card">
                    <div class="prod-head">
                        <span><?= htmlspecialchars($item['cantidad']) ?>x <?= htmlspecialchars($item['descripcion']) ?></span>
                        <span>S/ <?= number_format($item['subtotal'], 2) ?></span>
                    </div>
                    <div class="prod-specs-txt">
                        <?php if (!empty($item['color_nombre'])): ?>
                            <div><strong>Color:</strong> <?= htmlspecialchars($item['color_nombre']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($item['observaciones_item'])): ?>
                            <div><strong>Detalles:</strong> <?= htmlspecialchars($item['observaciones_item']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Block: Financial Summary -->
            <div class="financial-box">
                <div class="fin-line">
                    <span style="color:#64748B;">Total del Contrato:</span>
                    <span style="font-weight:700;">S/ <?= number_format($contrato['monto_total'], 2) ?></span>
                </div>
                <div class="fin-line">
                    <span style="color:#059669; font-weight:600;">A Cuenta (Adelanto Pagado):</span>
                    <span style="color:#059669; font-weight:700;">S/ <?= number_format($contrato['monto_adelanto'], 2) ?></span>
                </div>
                <div class="fin-total-hero">
                    <span class="total-lbl" style="color:#DC2626;">Saldo Pendiente:</span>
                    <span class="total-num" style="color:#DC2626;">S/ <?= number_format($contrato['monto_saldo'], 2) ?></span>
                </div>
            </div>

            <!-- Legal Notice -->
            <div class="legal-notice">
                <p><strong>Cláusula de Conformidad:</strong> Al firmar, usted confirma el modelo, medidas, colores y condiciones pactadas en este contrato según lo estipulado por el Código Civil.</p>
            </div>

            <!-- Signature Pad -->
            <div class="signature-section">
                <div class="sig-header-txt">
                    <i class="fas fa-pen-nib" style="color:#E31E24;"></i> Firma Digital del Cliente
                </div>
                <p class="sig-sub-txt">Dibuja tu firma con el dedo en el recuadro inferior:</p>

                <div class="canvas-touch-wrapper">
                    <canvas id="signatureCanvas" class="canvas-pad"></canvas>
                    <div class="hint-overlay" id="padHint">
                        <i class="fas fa-fingerprint"></i> Dibuja tu firma aquí
                    </div>
                </div>

                <div>
                    <button type="button" class="btn-action-secondary" id="btnClearSignature">
                        <i class="fas fa-eraser"></i> Limpiar y Volver a Firmar
                    </button>
                </div>

                <div style="margin-top:1.1rem; text-align:left; font-size:0.8rem; color:#475569;">
                    <label style="display:flex; align-items:flex-start; gap:8px; cursor:pointer;">
                        <input type="checkbox" id="chkConformidad" checked style="margin-top:3px; accent-color:#E31E24;">
                        <span>Declaro mi total conformidad con las especificaciones del pedido y los términos del contrato.</span>
                    </label>
                </div>

                <button type="button" class="btn-action-primary" id="btnSubmitSignature">
                    <i class="fas fa-circle-check"></i> Confirmar y Firmar Contrato
                </button>
            </div>

        <?php endif; ?>

        <div class="footer-brand-note">
            Industrias Carpicenter S.A.C. · Sistema Digital de Ventas y Pedidos
        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const canvas = document.getElementById('signatureCanvas');
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    const hint = document.getElementById('padHint');
    const btnClear = document.getElementById('btnClearSignature');
    const btnSubmit = document.getElementById('btnSubmitSignature');
    const chkConformidad = document.getElementById('chkConformidad');

    let isDrawing = false;
    let hasDrawn = false;

    function resizeCanvas() {
        const rect = canvas.getBoundingClientRect();
        const ratio = window.devicePixelRatio || 1;
        canvas.width = rect.width * ratio;
        canvas.height = rect.height * ratio;
        ctx.scale(ratio, ratio);
        ctx.strokeStyle = '#0F172A';
        ctx.lineWidth = 2.8;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
    }
    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);

    function getPos(e) {
        const rect = canvas.getBoundingClientRect();
        if (e.touches && e.touches.length > 0) {
            return {
                x: e.touches[0].clientX - rect.left,
                y: e.touches[0].clientY - rect.top
            };
        }
        return {
            x: e.clientX - rect.left,
            y: e.clientY - rect.top
        };
    }

    function startDrawing(e) {
        isDrawing = true;
        hasDrawn = true;
        if (hint) hint.style.display = 'none';
        const pos = getPos(e);
        ctx.beginPath();
        ctx.moveTo(pos.x, pos.y);
        e.preventDefault();
    }

    function draw(e) {
        if (!isDrawing) return;
        const pos = getPos(e);
        ctx.lineTo(pos.x, pos.y);
        ctx.stroke();
        e.preventDefault();
    }

    function stopDrawing() {
        if (isDrawing) {
            ctx.closePath();
            isDrawing = false;
        }
    }

    canvas.addEventListener('touchstart', startDrawing, { passive: false });
    canvas.addEventListener('touchmove', draw, { passive: false });
    canvas.addEventListener('touchend', stopDrawing, { passive: false });

    canvas.addEventListener('mousedown', startDrawing);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', stopDrawing);
    canvas.addEventListener('mouseleave', stopDrawing);

    btnClear.addEventListener('click', () => {
        const ratio = window.devicePixelRatio || 1;
        ctx.clearRect(0, 0, canvas.width / ratio, canvas.height / ratio);
        hasDrawn = false;
        if (hint) hint.style.display = 'flex';
    });

    btnSubmit.addEventListener('click', async () => {
        if (!hasDrawn) {
            alert('⚠️ Por favor, realiza tu firma con el dedo en el recuadro antes de confirmar.');
            return;
        }
        if (!chkConformidad.checked) {
            alert('⚠️ Debes aceptar la declaración de conformidad para continuar.');
            return;
        }

        btnSubmit.disabled = true;
        btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando firma...';

        const dataUrl = canvas.toDataURL('image/png');

        try {
            const formData = new FormData();
            formData.append('firma_base64', dataUrl);

            const res = await fetch('firma_contrato.php?token=<?= urlencode($token) ?>', {
                method: 'POST',
                body: formData
            });

            const json = await res.json();
            if (json.success) {
                window.location.reload();
            } else {
                alert('Error: ' + (json.message || 'No se pudo guardar la firma.'));
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = '<i class="fas fa-circle-check"></i> Confirmar y Firmar Contrato';
            }
        } catch (err) {
            alert('Error de conexión al enviar la firma.');
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = '<i class="fas fa-circle-check"></i> Confirmar y Firmar Contrato';
        }
    });
});
</script>
</body>
</html>
