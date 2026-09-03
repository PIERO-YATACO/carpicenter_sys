<?php
require_once __DIR__ . '/db.php';

try {
    // 1. Agregar columna codigo si no existe
    $db->exec("ALTER TABLE productos ADD COLUMN IF NOT EXISTS codigo VARCHAR(50)");
    
    // 2. Poblar codigos vacíos para los productos existentes
    $stmt = $db->query("SELECT id, codigo FROM productos ORDER BY id ASC");
    $prods = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt_upd = $db->prepare("UPDATE productos SET codigo = ? WHERE id = ?");
    $count = 0;
    foreach ($prods as $p) {
        if (empty($p['codigo'])) {
            $code = 'PRD-' . str_pad($p['id'], 3, '0', STR_PAD_LEFT);
            $stmt_upd->execute([$code, $p['id']]);
            $count++;
        }
    }
    
    // 3. Crear índice para búsquedas rápidas por código
    $db->exec("CREATE INDEX IF NOT EXISTS idx_productos_codigo ON productos(codigo)");
    
    echo "OK: Migracion de columna 'codigo' completada. Se asignaron {$count} codigos por defecto.\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
