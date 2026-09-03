<?php
require_once __DIR__ . '/../config/db.php';

$count = $db->query("SELECT COUNT(*) FROM finanzas_cuadre_caja")->fetchColumn();
echo "CUADRES_COUNT: $count\n";

// Si hay 0, insertamos el ejemplo exacto del Excel
if ($count == 0) {
    $db->beginTransaction();
    $stmt = $db->prepare("
        INSERT INTO finanzas_cuadre_caja (
            codigo, titulo, area, tienda, encargado,
            fecha_inicio, fecha_fin,
            autorizacion_responsable, fecha_aut_responsable,
            pagar_a, autorizacion_direccion,
            saldo_anterior, total_ingreso,
            total_salida_produccion, total_salida_melamine,
            total_salida_servicio, total_salida_combustible,
            total_salida_movilidad, total_salida_otros,
            total_egreso, saldo_final,
            observacion, estado
        ) VALUES (
            'CC-2026-001', 'CUADRE DE CAJA TIENDAS 1-AGO-26', 'ADMINISTRATIVO', 'GENERAL', 'NAOMI',
            '2026-08-01', '2026-08-01',
            'CARPICENTER', '2026-08-01',
            'DIRECCIÓN / CAJA', 'AUTORIZADO',
            98.75, 9065.75,
            0.00, 0.00,
            0.00, 0.00,
            0.00, 8980.00,
            8980.00, 85.75,
            'Cuadre de caja inicial según formato de Excel oficial', 'CERRADO'
        ) RETURNING id
    ");
    $stmt->execute();
    $cuadre_id = $stmt->fetchColumn();

    // Entradas
    $entradas = [
        ['fecha' => '2026-08-01', 'tipo' => 'ENTRADA', 'categoria' => 'SALDO_ANTERIOR', 'desc' => 'SALDO ANTERIOR', 'just' => '', 'monto' => 98.75],
        ['fecha' => '2026-08-01', 'tipo' => 'ENTRADA', 'categoria' => 'TIENDA_1', 'desc' => 'TIENDA 1', 'just' => '1824-1825-1826', 'monto' => 6880.00],
        ['fecha' => '2026-08-01', 'tipo' => 'ENTRADA', 'categoria' => 'TIENDA_2', 'desc' => 'TIENDA 2', 'just' => '1921-1922', 'monto' => 880.00],
        ['fecha' => '2026-08-01', 'tipo' => 'ENTRADA', 'categoria' => 'TIENDA_3', 'desc' => 'TIENDA 3', 'just' => '1399-1400', 'monto' => 1207.00],
        ['fecha' => '2026-08-01', 'tipo' => 'ENTRADA', 'categoria' => 'TIENDA_4', 'desc' => 'TIENDA 4', 'just' => '', 'monto' => 0.00],
    ];

    $stmtDet = $db->prepare("INSERT INTO finanzas_cuadre_detalle (cuadre_id, fecha, tipo, categoria, detalle, descripcion, nro_justificante, monto) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($entradas as $e) {
        $stmtDet->execute([$cuadre_id, $e['fecha'], $e['tipo'], $e['categoria'], $e['desc'], $e['desc'], $e['just'], $e['monto']]);
    }

    // Salidas
    $salidas = [
        ['fecha' => '2026-08-01', 'tipo' => 'SALIDA', 'categoria' => 'OTROS', 'desc' => 'POR DEPOSITO A CUENTA CARPI BCP', 'just' => '8015', 'monto' => 4310.00],
        ['fecha' => '2026-08-01', 'tipo' => 'SALIDA', 'categoria' => 'OTROS', 'desc' => 'POR DEPOSITO A CUENTA CARPI BCP', 'just' => '8016', 'monto' => 2280.00],
        ['fecha' => '2026-08-01', 'tipo' => 'SALIDA', 'categoria' => 'OTROS', 'desc' => 'POR DEPOSITO A CUENTA CARPI BCP', 'just' => '8017', 'monto' => 2070.00],
        ['fecha' => '2026-08-01', 'tipo' => 'SALIDA', 'categoria' => 'OTROS', 'desc' => 'POR DEPOSITO A CUENTA CARPI IBK', 'just' => '784', 'monto' => 320.00],
    ];

    foreach ($salidas as $s) {
        $stmtDet->execute([$cuadre_id, $s['fecha'], $s['tipo'], $s['categoria'], $s['desc'], $s['desc'], $s['just'], $s['monto']]);
    }

    $db->commit();
    echo "SEED_EXITOSO: Cuadre de ejemplo insertado con ID $cuadre_id.\n";
}
