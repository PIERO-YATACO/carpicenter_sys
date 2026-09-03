<?php
$initial = mb_substr(trim($d['nombre_personal']), 0, 1, 'UTF-8');
$avatarBg = '#EFF6FF';
$avatarColor = '#2563EB';

if ($d['categoria'] === 'ADMINISTRATIVO') {
    $avatarBg = '#FEF3C7';
    $avatarColor = '#D97706';
} elseif ($d['categoria'] === 'PRODUCCION') {
    $avatarBg = '#FFEDD5';
    $avatarColor = '#EA580C';
}
?>
<tr class="clean-row" data-rowid="<?= $d['id'] ?>" data-categoria="<?= $d['categoria'] ?>" data-nombre="<?= htmlspecialchars($d['nombre_personal']) ?>" data-area="<?= htmlspecialchars($d['area']) ?>" data-cuenta="<?= htmlspecialchars($d['cuenta_bancaria']) ?>" style="<?= !$d['incluido'] ? 'opacity:0.5;' : '' ?>">
    <!-- Inputs Ocultos para Guardado en Base de Datos -->
    <input type="hidden" name="detalles[<?= $d['id'] ?>][categoria]" value="<?= $d['categoria'] ?>">
    <input type="hidden" name="detalles[<?= $d['id'] ?>][incluido]" id="hid_incluido_<?= $d['id'] ?>" value="<?= $d['incluido'] ? '1' : '0' ?>">
    <input type="hidden" name="detalles[<?= $d['id'] ?>][sueldo_mensual]" id="hid_mensual_<?= $d['id'] ?>" value="<?= $d['sueldo_mensual'] ?>">
    <input type="hidden" name="detalles[<?= $d['id'] ?>][base_dia]" id="hid_basedia_<?= $d['id'] ?>" value="<?= $d['base_dia'] ?>">
    <input type="hidden" name="detalles[<?= $d['id'] ?>][base_semanal]" id="hid_base_<?= $d['id'] ?>" value="<?= $d['base_semanal'] ?>">
    <input type="hidden" name="detalles[<?= $d['id'] ?>][bono_comision]" id="hid_bono_<?= $d['id'] ?>" value="<?= $d['bono_comision'] ?>">
    <input type="hidden" name="detalles[<?= $d['id'] ?>][horas_extra]" id="hid_hextra_<?= $d['id'] ?>" value="<?= $d['horas_extra'] ?>">
    <input type="hidden" name="detalles[<?= $d['id'] ?>][pago_hora]" id="hid_pagohora_<?= $d['id'] ?>" value="<?= $d['pago_hora'] ?>">
    <input type="hidden" name="detalles[<?= $d['id'] ?>][descuento_falta]" id="hid_dfalta_<?= $d['id'] ?>" value="<?= $d['descuento_falta'] ?>">
    <input type="hidden" name="detalles[<?= $d['id'] ?>][descuento_prestamo]" id="hid_dprestamo_<?= $d['id'] ?>" value="<?= $d['descuento_prestamo'] ?>">
    <input type="hidden" name="detalles[<?= $d['id'] ?>][descuento_planilla]" id="hid_dplanilla_<?= $d['id'] ?>" value="<?= $d['descuento_planilla'] ?>">
    <input type="hidden" name="detalles[<?= $d['id'] ?>][total_descuentos]" id="hid_totdsctos_<?= $d['id'] ?>" value="<?= $d['total_descuentos'] ?>">
    <input type="hidden" name="detalles[<?= $d['id'] ?>][total_pagar]" id="hid_totpagar_<?= $d['id'] ?>" value="<?= $d['total_pagar'] ?>">

    <!-- 1. Colaborador (Avatar + Nombre + Cuenta) -->
    <td>
        <div style="display:flex; align-items:center; gap:10px;">
            <div class="worker-avatar" style="background:<?= $avatarBg ?>; color:<?= $avatarColor ?>;">
                <?= $initial ?>
            </div>
            <div>
                <strong style="font-size:0.87rem; color:#1E293B; display:block;"><?= htmlspecialchars($d['nombre_personal']) ?></strong>
                <span style="font-size:0.75rem; color:#94A3B8; font-family:monospace;"><?= htmlspecialchars($d['cuenta_bancaria'] ?: 'Sin cuenta') ?></span>
            </div>
        </div>
    </td>

    <!-- 2. Área -->
    <td>
        <span style="font-size:0.82rem; color:#475569; font-weight:600;"><?= htmlspecialchars($d['area']) ?></span>
    </td>

    <!-- 3. Sueldo Base Semanal -->
    <td style="text-align:right; font-weight:700; color:#1E293B;" id="view_base_<?= $d['id'] ?>">
        S/ <?= number_format($d['base_semanal'], 2) ?>
    </td>

    <!-- 4. Bonos -->
    <td style="text-align:right; font-weight:700; color:#059669;" id="view_bono_<?= $d['id'] ?>">
        S/ <?= number_format($d['bono_comision'], 2) ?>
    </td>

    <!-- 5. Descuentos -->
    <td style="text-align:right; font-weight:700; color:#DC2626;" id="view_dscto_<?= $d['id'] ?>">
        S/ <?= number_format($d['total_descuentos'], 2) ?>
    </td>

    <!-- 6. Total a Pagar -->
    <td style="text-align:right; font-weight:800; color:#059669; font-size:0.92rem;" id="view_pagar_<?= $d['id'] ?>">
        S/ <?= number_format($d['total_pagar'], 2) ?>
    </td>

    <!-- 7. Botón Acción / Desglose -->
    <td style="text-align:center;">
        <button type="button" class="btn-edit-worker" title="Editar / Ver Desglose" onclick="openWorkerCalc(<?= $d['id'] ?>)">
            <i class="fas fa-chevron-right"></i>
        </button>
    </td>
</tr>
