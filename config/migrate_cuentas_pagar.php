<?php
/**
 * migrate_cuentas_pagar.php
 * Crea las tablas: cuentas_pagar y documentos_adjuntos (si no existen)
 */
require_once __DIR__ . '/db.php';

try {
    $db->beginTransaction();

    // ── Tabla cuentas_pagar ──
    $db->exec("
        CREATE TABLE IF NOT EXISTS cuentas_pagar (
            id                SERIAL PRIMARY KEY,
            proveedor_id      INT            REFERENCES proveedores(id) ON DELETE SET NULL,
            tipo_credito      VARCHAR(50)    NOT NULL DEFAULT 'letra',
            monto             DECIMAL(12,2)  NOT NULL DEFAULT 0,
            fecha_emision     DATE           NOT NULL,
            fecha_vencimiento DATE           NOT NULL,
            banco             VARCHAR(100),
            numero_operacion  VARCHAR(100),
            estado            VARCHAR(30)    NOT NULL DEFAULT 'pendiente',
            observaciones     TEXT,
            created_at        TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
            updated_at        TIMESTAMP      DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // ── Tabla documentos_adjuntos ──
    $db->exec("
        CREATE TABLE IF NOT EXISTS documentos_adjuntos (
            id            SERIAL PRIMARY KEY,
            tipo          VARCHAR(80)   NOT NULL,
            referencia_id INT           NOT NULL,
            ruta          VARCHAR(255)  NOT NULL,
            fecha_subida  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // Índices útiles
    $db->exec("CREATE INDEX IF NOT EXISTS idx_cp_proveedor  ON cuentas_pagar (proveedor_id);");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_cp_estado     ON cuentas_pagar (estado);");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_cp_vencimiento ON cuentas_pagar (fecha_vencimiento);");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_da_ref        ON documentos_adjuntos (referencia_id);");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_da_tipo       ON documentos_adjuntos (tipo);");

    $db->commit();
    echo "<b style='color:green;'>✅ Migración exitosa.</b> Tablas <code>cuentas_pagar</code> y <code>documentos_adjuntos</code> creadas correctamente.";

} catch (Exception $e) {
    $db->rollBack();
    echo "<b style='color:red;'>❌ Error en migración:</b> " . $e->getMessage();
}
?>
