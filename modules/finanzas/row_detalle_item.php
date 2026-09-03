<tr class="payroll-row" data-categoria="<?= $d['categoria'] ?>" data-rowid="<?= $d['id'] ?>">
    <!-- A: N° -->
    <td style="text-align:center; font-weight:700; color:#94A3B8;"><?= $num++ ?></td>
    
    <!-- B: Nombre y Apellido -->
    <td style="font-weight:700; color:#1E293B; white-space:nowrap;">
        <input type="hidden" name="detalles[<?= $d['id'] ?>][categoria]" value="<?= $d['categoria'] ?>">
        <?= htmlspecialchars($d['nombre_personal']) ?>
        <?php if($d['tipo_trabajador'] === 'EVENTUAL'): ?>
            <span style="font-size:0.65rem; background:#F1F5F9; color:#64748B; padding:1px 5px; border-radius:4px; margin-left:4px; font-weight:700;">Eventual</span>
        <?php endif; ?>
    </td>
    
    <!-- C: Área -->
    <td style="font-size:0.75rem; font-weight:600; color:#64748B; text-align:center;"><?= htmlspecialchars($d['area']) ?></td>
    
    <!-- D: Nº de Cuenta BCP / Yape -->
    <td style="text-align:center;">
        <span style="font-family:monospace; font-weight:600; font-size:0.75rem; color:#475569; background:#F8FAFC; padding:2px 6px; border-radius:4px; display:inline-block; border:1px solid #E2E8F0;">
            <?= htmlspecialchars($d['cuenta_bancaria'] ?: '—') ?>
        </span>
    </td>

    <!-- Switch Activar/Desactivar -->
    <td style="text-align:center;">
        <label class="switch-toggle" title="Incluir en el pago de esta semana">
            <input type="checkbox" name="detalles[<?= $d['id'] ?>][incluido]" id="incluido_<?= $d['id'] ?>" value="1" <?= $d['incluido'] ? 'checked' : '' ?> onchange="recalcRow(<?= $d['id'] ?>)">
            <span class="slider-round"></span>
        </label>
    </td>

    <!-- E: MENSUAL (Auto-calculado bloqueado) -->
    <td>
        <input type="number" step="0.01" name="detalles[<?= $d['id'] ?>][sueldo_mensual]" id="mensual_<?= $d['id'] ?>" class="cell-inp cell-auto" value="<?= number_format($d['sueldo_mensual'] ?: ($d['base_semanal'] * 4), 2, '.', '') ?>" readonly tabindex="-1">
    </td>

    <!-- F: BASE X DIA (Auto-calculado bloqueado) -->
    <td>
        <input type="number" step="0.01" name="detalles[<?= $d['id'] ?>][base_dia]" id="basedia_<?= $d['id'] ?>" class="cell-inp cell-auto" value="<?= number_format($d['base_dia'] ?: ($d['base_semanal'] / 6), 2, '.', '') ?>" readonly tabindex="-1">
    </td>

    <!-- G: BASE SEMANAL (Principal: ella lo escribe y todo se calcula) -->
    <td>
        <input type="number" step="0.01" name="detalles[<?= $d['id'] ?>][base_semanal]" id="base_<?= $d['id'] ?>" class="cell-inp cell-base-input" value="<?= number_format($d['base_semanal'], 2, '.', '') ?>" oninput="onBaseSemanalChange(<?= $d['id'] ?>)">
    </td>

    <!-- H: BONO / PRESTAMO -->
    <td>
        <input type="number" step="0.01" name="detalles[<?= $d['id'] ?>][bono_comision]" id="bono_<?= $d['id'] ?>" class="cell-inp" value="<?= number_format($d['bono_comision'], 2, '.', '') ?>" placeholder="0.00" oninput="recalcRow(<?= $d['id'] ?>)">
    </td>

    <!-- I: H. EXTRA / DOMINGO -->
    <td>
        <input type="number" step="0.5" name="detalles[<?= $d['id'] ?>][horas_extra]" id="hextra_<?= $d['id'] ?>" class="cell-inp" value="<?= $d['horas_extra'] ?>" placeholder="0" oninput="recalcRow(<?= $d['id'] ?>)">
    </td>

    <!-- J: PAGO X HORA (Auto-calculado bloqueado = Base Dia / 10) -->
    <td>
        <input type="number" step="0.01" name="detalles[<?= $d['id'] ?>][pago_hora]" id="pagohora_<?= $d['id'] ?>" class="cell-inp cell-auto" value="<?= number_format($d['pago_hora'] ?: (($d['base_semanal'] / 6) / 10), 2, '.', '') ?>" readonly tabindex="-1">
    </td>

    <!-- K: FALTA X HORA (Monto de descuento directo en Soles - Rojo) -->
    <td>
        <input type="number" step="0.01" name="detalles[<?= $d['id'] ?>][descuento_falta]" id="dfalta_<?= $d['id'] ?>" class="cell-inp cell-dscto-input" value="<?= number_format($d['descuento_falta'] ?? 0, 2, '.', '') ?>" placeholder="0.00" oninput="recalcRow(<?= $d['id'] ?>)">
    </td>

    <!-- L: DSTO. PRESTAMO (Descuento Préstamo / Adelanto - Rojo) -->
    <td>
        <input type="number" step="0.01" name="detalles[<?= $d['id'] ?>][descuento_prestamo]" id="dprestamo_<?= $d['id'] ?>" class="cell-inp cell-dscto-input" value="<?= number_format($d['descuento_prestamo'] ?? 0, 2, '.', '') ?>" placeholder="0.00" oninput="recalcRow(<?= $d['id'] ?>)">
    </td>

    <!-- M: DESCUENTO X PLANILLA (Descuento Planilla - Rojo) -->
    <td>
        <input type="number" step="0.01" name="detalles[<?= $d['id'] ?>][descuento_planilla]" id="dplanilla_<?= $d['id'] ?>" class="cell-inp cell-dscto-input" value="<?= number_format($d['descuento_planilla'] ?? 0, 2, '.', '') ?>" placeholder="0.00" oninput="recalcRow(<?= $d['id'] ?>)">
    </td>

    <!-- N: TOTAL DSCTOS (Auto = K + L + M - Rojo) -->
    <td>
        <input type="number" step="0.01" name="detalles[<?= $d['id'] ?>][total_descuentos]" id="totdsctos_<?= $d['id'] ?>" class="cell-inp cell-dscto-total" value="<?= number_format($d['total_descuentos'], 2, '.', '') ?>" readonly tabindex="-1">
    </td>

    <!-- O: TOTAL A PAGAR (Auto = (G+H)+(I*J)-N - Verde) -->
    <td>
        <input type="number" step="0.01" name="detalles[<?= $d['id'] ?>][total_pagar]" id="totpagar_<?= $d['id'] ?>" class="cell-inp cell-neto-pay" value="<?= number_format($d['total_pagar'], 2, '.', '') ?>" readonly tabindex="-1">
    </td>
</tr>
