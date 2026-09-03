<?php
require_once __DIR__ . '/db.php';

try {
    $db->beginTransaction();

    // 1. Delete unnecessary or duplicate locales
    // First update any users or inventory linked to obsolete locales to Almacén Principal (id=1)
    $db->exec("UPDATE usuarios SET local_id = 1 WHERE local_id NOT IN (1, 2, 3, 5, 6, 7)");
    $db->exec("UPDATE inventario_local SET local_id = 1 WHERE local_id NOT IN (1, 2, 3, 5, 6, 7)");

    // Remove obsolete locales
    $db->exec("DELETE FROM locales WHERE nombre IN ('Taller de Producción', 'Distribuidor')");

    // 2. Desired list of 6 locales
    $desiredLocales = [
        ['nombre' => 'Almacén Principal', 'tipo' => 'Almacen'],
        ['nombre' => 'Almacén Auxiliar',  'tipo' => 'Almacen'],
        ['nombre' => 'Tienda 1',           'tipo' => 'Tienda'],
        ['nombre' => 'Tienda 2',           'tipo' => 'Tienda'],
        ['nombre' => 'Tienda 3',           'tipo' => 'Tienda'],
        ['nombre' => 'Tienda 4',           'tipo' => 'Tienda'],
    ];

    foreach ($desiredLocales as $loc) {
        $stmt = $db->prepare("SELECT id FROM locales WHERE nombre = :nombre LIMIT 1");
        $stmt->execute([':nombre' => $loc['nombre']]);
        $exists = $stmt->fetchColumn();

        if (!$exists) {
            $stmtIns = $db->prepare("INSERT INTO locales (nombre, tipo) VALUES (:nombre, :tipo)");
            $stmtIns->execute([':nombre' => $loc['nombre'], ':tipo' => $loc['tipo']]);
            echo "Agregado: {$loc['nombre']} ({$loc['tipo']})\n";
        } else {
            $stmtUpd = $db->prepare("UPDATE locales SET tipo = :tipo WHERE id = :id");
            $stmtUpd->execute([':tipo' => $loc['tipo'], ':id' => $exists]);
            echo "Existente actualizado: {$loc['nombre']}\n";
        }
    }

    $db->commit();
    echo "\nActualización de locales completada con éxito.\n";

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    die("Error al actualizar locales: " . $e->getMessage() . "\n");
}
