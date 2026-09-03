<?php
require_once __DIR__ . '/../../config/db.php';
$msgs = [];

// Fix FK constraint
$steps = [
    "Eliminar FK vieja" => "
DO \$\$
DECLARE r RECORD;
BEGIN
    FOR r IN
        SELECT tc.constraint_name
        FROM information_schema.table_constraints tc
        JOIN information_schema.key_column_usage kcu ON tc.constraint_name = kcu.constraint_name
        JOIN information_schema.referential_constraints rc ON tc.constraint_name = rc.constraint_name
        JOIN information_schema.table_constraints ccu ON rc.unique_constraint_name = ccu.constraint_name
        WHERE tc.table_name = 'piezas_modelo'
          AND tc.constraint_type = 'FOREIGN KEY'
          AND kcu.column_name = 'tablero_id'
          AND ccu.table_name <> 'despiece_tableros'
    LOOP
        EXECUTE 'ALTER TABLE piezas_modelo DROP CONSTRAINT ' || quote_ident(r.constraint_name);
    END LOOP;
END\$\$",
    "Agregar FK correcta" => "
DO \$\$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.table_constraints tc
        JOIN information_schema.key_column_usage kcu ON tc.constraint_name = kcu.constraint_name
        JOIN information_schema.referential_constraints rc ON tc.constraint_name = rc.constraint_name
        JOIN information_schema.table_constraints ccu ON rc.unique_constraint_name = ccu.constraint_name
        WHERE tc.table_name = 'piezas_modelo'
          AND kcu.column_name = 'tablero_id'
          AND ccu.table_name = 'despiece_tableros'
    ) THEN
        ALTER TABLE piezas_modelo
        ADD CONSTRAINT piezas_modelo_tablero_id_fkey
        FOREIGN KEY (tablero_id) REFERENCES despiece_tableros(id) ON DELETE SET NULL;
    END IF;
END\$\$",
];

foreach ($steps as $nombre => $sql) {
    try { $db->exec($sql); $msgs[] = "✅ $nombre"; }
    catch (PDOException $e) { $msgs[] = "❌ $nombre: " . $e->getMessage(); }
}
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8">
<style>body{font-family:sans-serif;background:#111;color:#eee;padding:2rem;} .ok{color:#66BB6A;} .err{color:#ef4444;} a{color:#42A5F5;}</style>
</head><body>
<h2>Fix FK - Hoja de Despiece</h2>
<?php foreach($msgs as $m): ?>
<p><?= htmlspecialchars($m) ?></p>
<?php endforeach; ?>
<br>
<a href="/carpicenter_sys/modules/despiece/modelos.php">→ Ir al Módulo</a>
<a href="fix_fk.php" style="margin-left:1rem;">↺ Reintentar</a>
</body></html>
