<?php
require_once __DIR__ . '/../../config/db.php';

$token = $_GET['token'] ?? '';
if (empty($token)) {
    die("Token de firma no válido o enlace incompleto.");
}

$stmt = $db->prepare("SELECT * FROM cotizaciones WHERE firma_token = ?");
$stmt->execute([$token]);
$cotizacion = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cotizacion) {
    die("El documento no existe o el enlace ha caducado.");
}

// Fetch items details
$stmtDet = $db->prepare("SELECT * FROM cotizacion_detalle WHERE cotizacion_id = ? ORDER BY id ASC");
$stmtDet->execute([$cotizacion['id']]);
$detalles = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

$ya_firmado = !empty($cotizacion['firma_digital']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Firma de Cotización <?= htmlspecialchars($cotizacion['numero']) ?> — Industrias Carpicenter</title>
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
            background: #EFF6FF;
            color: #2563EB;
            border: 1px solid #BFDBFE;
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
        .fin-total-hero {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.2rem 0;
        }
        .fin-total-hero .total-lbl {
            font-size: 0.95rem;
            font-weight: 800;
            color: #0F172A;
        }
        .fin-total-hero .total-num {
            font-size: 1.3rem;
            font-weight: 900;
            color: #2563EB;
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
            background: linear-gradient(135deg, #1565C0 0%, #0D47A1 100%);
            color: #FFFFFF;
            border: none;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 800;
            cursor: pointer;
            text-decoration: none;
            box-shadow: 0 6px 16px rgba(21,101,192,0.25);
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
            <i class="fas fa-file-invoice"></i> <?= htmlspecialchars($cotizacion['tipo_documento'] ?? 'Cotización') ?>
        </div>
        <h1 class="doc-title">N° <?= htmlspecialchars($cotizacion['numero']) ?></h1>
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
                    ¡Documento Aprobado y Firmado!
                </h2>
                <p style="font-size:0.86rem; color:#64748B; line-height:1.45;">
                    Estimado(a) <strong><?= htmlspecialchars($cotizacion['cliente_nombre']) ?></strong>, tu firma de aprobación ha sido registrada con éxito en nuestros registros.
                </p>

                <!-- Certification Details -->
                <div class="cert-card">
                    <span style="font-size:0.72rem; text-transform:uppercase; font-weight:800; color:#64748B; letter-spacing:0.5px; display:block;">
                        <i class="fas fa-certificate" style="color:#059669;"></i> Firma Digital de Conformidad
                    </span>
                    <div class="cert-img-wrap">
                        <img src="<?= htmlspecialchars($cotizacion['firma_digital']) ?>" alt="Firma del Cliente">
                    </div>
                    <div style="font-size:0.75rem; color:#059669; font-weight:700; margin-top:0.55rem; display:flex; align-items:center; justify-content:center; gap:5px;">
                        <i class="fas fa-shield-halved"></i> Conformidad Registrada
                    </div>
                </div>

                <!-- Financial Quick Recap -->
                <div class="info-panel" style="text-align:left; margin-bottom:1.3rem;">
                    <div class="data-row">
                        <span class="lbl">Cliente:</span>
                        <span class="val"><?= htmlspecialchars($cotizacion['cliente_nombre']) ?></span>
                    </div>
                    <div class="data-row">
                        <span class="lbl">Documento:</span>
                        <span class="val"><?= htmlspecialchars($cotizacion['cliente_documento'] ?: '—') ?></span>
                    </div>
                    <div class="data-row" style="border-top:1px dashed #CBD5E1; padding-top:4px; margin-top:4px;">
                        <span class="lbl" style="font-weight:700; color:#2563EB;">Monto Total Presupuestado:</span>
                        <span class="val" style="color:#2563EB; font-size:1.05rem;">S/ <?= number_format($cotizacion['total'], 2) ?></span>
                    </div>
                </div>

                <!-- Action Button to View/Download PDF -->
                <a href="/carpicenter_sys/modules/cotizaciones/cotizacion_view.php?id=<?= $cotizacion['id'] ?>" target="_blank" class="btn-action-primary">
                    <i class="fas fa-file-pdf"></i> Ver Cotización en Formato Oficial PDF
                </a>
            </div>

        <?php else: ?>
            <!-- Signing State -->

            <!-- Block: Client Info -->
            <div class="info-panel">
                <div class="info-panel-title">
                    <i class="fas fa-user-check" style="color:#2563EB;"></i> Presupuesto Dirigido a
                </div>
                <div class="data-row">
                    <span class="lbl">Cliente:</span>
                    <span class="val"><?= htmlspecialchars($cotizacion['cliente_nombre']) ?></span>
                </div>
                <div class="data-row">
                    <span class="lbl">DNI / RUC:</span>
                    <span class="val"><?= htmlspecialchars($cotizacion['cliente_documento'] ?: '—') ?></span>
                </div>
                <?php if (!empty($cotizacion['cliente_telefono'])): ?>
                <div class="data-row">
                    <span class="lbl">Teléfono:</span>
                    <span class="val"><?= htmlspecialchars($cotizacion['cliente_telefono']) ?></span>
                </div>
                <?php endif; ?>
                <div class="data-row">
                    <span class="lbl">Fecha de Emisión:</span>
                    <span class="val"><?= date('d/m/Y', strtotime($cotizacion['fecha'])) ?></span>
                </div>
                <?php if (!empty($cotizacion['fecha_validez'])): ?>
                <div class="data-row">
                    <span class="lbl">Válido Hasta:</span>
                    <span class="val" style="color:#D97706;"><?= date('d/m/Y', strtotime($cotizacion['fecha_validez'])) ?></span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Block: Products Details -->
            <div class="info-panel">
                <div class="info-panel-title">
                    <i class="fas fa-couch" style="color:#2563EB;"></i> Muebles & Especificaciones
                </div>
                <?php foreach ($detalles as $item): ?>
                <div class="product-item-card">
                    <div class="prod-head">
                        <span><?= htmlspecialchars($item['cantidad']) ?>x <?= htmlspecialchars($item['descripcion']) ?></span>
                        <span>S/ <?= number_format($item['subtotal'], 2) ?></span>
                    </div>
                    <div class="prod-specs-txt">
                        <?php if (!empty($item['color'])): ?>
                            <div><strong>Color:</strong> <?= htmlspecialchars($item['color']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($item['especificaciones'])): ?>
                            <div><?= nl2br(htmlspecialchars($item['especificaciones'])) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Block: Financial Summary -->
            <div class="financial-box">
                <div class="fin-total-hero">
                    <span class="total-lbl">Importe Total Presupuestado:</span>
                    <span class="total-num">S/ <?= number_format($cotizacion['total'], 2) ?></span>
                </div>
            </div>

            <!-- Legal Notice -->
            <div class="legal-notice">
                <p><strong>Aprobación de Presupuesto:</strong> Al firmar digitalmente en el recuadro inferior, usted aprueba las especificaciones técnicas, acabados y condiciones comerciales detalladas en esta cotización.</p>
            </div>

            <!-- Signature Pad -->
            <div class="signature-section">
                <div class="sig-header-txt">
                    <i class="fas fa-pen-nib" style="color:#2563EB;"></i> Firma Digital de Aprobación
                </div>
                <p class="sig-sub-txt">Dibuja tu firma con el dedo en el recuadro inferior:</p>

                <div class="canvas-touch-wrapper">
                    <canvas id="signature-pad" class="canvas-pad"></canvas>
                    <div class="hint-overlay" id="padHint">
                        <i class="fas fa-fingerprint"></i> Dibuja tu firma aquí
                    </div>
                </div>

                <div>
                    <button type="button" class="btn-action-secondary" id="clear-signature">
                        <i class="fas fa-eraser"></i> Limpiar y Volver a Firmar
                    </button>
                </div>

                <div style="margin-top:1.1rem; text-align:left; font-size:0.8rem; color:#475569;">
                    <label style="display:flex; align-items:flex-start; gap:8px; cursor:pointer;">
                        <input type="checkbox" id="chkConformidad" checked style="margin-top:3px; accent-color:#2563EB;">
                        <span>Declaro mi total aprobación con los detalles y precios presentados en esta cotización.</span>
                    </label>
                </div>

                <button type="button" class="btn-action-primary" id="save-signature">
                    <i class="fas fa-circle-check"></i> Confirmar y Enviar Firma
                </button>
            </div>

        <?php endif; ?>

        <div class="footer-brand-note">
            Industrias Carpicenter S.A.C. · Sistema Digital de Cotizaciones y Ventas
        </div>

    </div>
</div>

<?php if (!$ya_firmado): ?>
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const canvas = document.getElementById('signature-pad');
    if (!canvas) return;

    const hint = document.getElementById('padHint');
    const chkConformidad = document.getElementById('chkConformidad');
    const saveBtn = document.getElementById('save-signature');
    const clearBtn = document.getElementById('clear-signature');

    function resizeCanvas() {
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        canvas.width = canvas.offsetWidth * ratio;
        canvas.height = canvas.offsetHeight * ratio;
        canvas.getContext("2d").scale(ratio, ratio);
    }
    window.addEventListener('resize', resizeCanvas);
    resizeCanvas();

    const signaturePad = new SignaturePad(canvas, {
        backgroundColor: 'rgba(255, 255, 255, 0)',
        penColor: 'rgb(15, 23, 42)',
        minWidth: 2,
        maxWidth: 3.5
    });

    signaturePad.addEventListener("beginStroke", () => {
        if (hint) hint.style.display = 'none';
    });

    clearBtn.addEventListener('click', () => {
        signaturePad.clear();
        if (hint) hint.style.display = 'flex';
    });

    saveBtn.addEventListener('click', () => {
        if (signaturePad.isEmpty()) {
            alert("⚠️ Por favor realiza tu firma con el dedo en el recuadro antes de confirmar.");
            return;
        }
        if (!chkConformidad.checked) {
            alert("⚠️ Debes aceptar la declaración de aprobación para continuar.");
            return;
        }

        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando firma...';

        const dataURL = signaturePad.toDataURL('image/png');

        fetch('cotizacion_controller.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=guardar_firma&token=<?= urlencode($token) ?>&firma_digital=' + encodeURIComponent(dataURL)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                alert('Error: ' + (data.message || 'No se pudo guardar la firma.'));
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fas fa-circle-check"></i> Confirmar y Enviar Firma';
            }
        })
        .catch((error) => {
            alert("Error de conexión al enviar la firma.");
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i class="fas fa-circle-check"></i> Confirmar y Enviar Firma';
        });
    });
});
</script>
<?php endif; ?>

</body>
</html>
