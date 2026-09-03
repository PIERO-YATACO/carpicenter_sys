<?php
require_once __DIR__ . '/../../config/db.php';

$pasos   = [];
$errores = [];

$sql_steps = [

'Tabla despiece_tableros' => "
CREATE TABLE IF NOT EXISTS despiece_tableros (
    id               SERIAL PRIMARY KEY,
    nombre           VARCHAR(100) NOT NULL,
    espesor_mm       NUMERIC(5,2) NOT NULL DEFAULT 18,
    ancho_plancha_mm NUMERIC(7,2) NOT NULL DEFAULT 2440,
    largo_plancha_mm NUMERIC(7,2) NOT NULL DEFAULT 2140,
    precio_plancha   NUMERIC(10,2) DEFAULT 0,
    activo           BOOLEAN DEFAULT TRUE,
    created_at       TIMESTAMPTZ DEFAULT NOW()
);",

'Tabla productos_maestros' => "
CREATE TABLE IF NOT EXISTS productos_maestros (
    id               SERIAL PRIMARY KEY,
    codigo           VARCHAR(30) UNIQUE NOT NULL,
    nombre_modelo    VARCHAR(150) NOT NULL,
    descripcion      TEXT,
    categoria        VARCHAR(80),
    tiempo_fab_horas NUMERIC(5,2),
    activo           BOOLEAN DEFAULT TRUE,
    fecha_creacion   TIMESTAMPTZ DEFAULT NOW()
);",

'Tabla piezas_modelo' => "
CREATE TABLE IF NOT EXISTS piezas_modelo (
    id              SERIAL PRIMARY KEY,
    producto_id     INT NOT NULL REFERENCES productos_maestros(id) ON DELETE CASCADE,
    nro_pieza       SMALLINT,
    nombre_pieza    VARCHAR(150) NOT NULL,
    tablero_id      INT REFERENCES despiece_tableros(id),
    largo_final_mm  NUMERIC(7,2) NOT NULL DEFAULT 0,
    ancho_final_mm  NUMERIC(7,2) NOT NULL DEFAULT 0,
    espesor_mm      NUMERIC(5,2),
    cant_por_mueble NUMERIC(6,2) NOT NULL DEFAULT 1,
    tiene_veta      BOOLEAN DEFAULT FALSE,
    l1_canto_mm     NUMERIC(4,2) DEFAULT 0,
    l2_canto_mm     NUMERIC(4,2) DEFAULT 0,
    a1_canto_mm     NUMERIC(4,2) DEFAULT 0,
    a2_canto_mm     NUMERIC(4,2) DEFAULT 0,
    ranura_lado     VARCHAR(10),
    ranura_dist_mm  NUMERIC(6,2),
    ranura_prof_mm  NUMERIC(6,2),
    ranura_esp_mm   NUMERIC(6,2),
    perf_cant       SMALLINT DEFAULT 0,
    perf_lado       VARCHAR(10),
    perf_detalle    VARCHAR(200),
    notas           TEXT
);",

'Tabla insumos_modelo' => "
CREATE TABLE IF NOT EXISTS insumos_modelo (
    id                SERIAL PRIMARY KEY,
    producto_id       INT NOT NULL REFERENCES productos_maestros(id) ON DELETE CASCADE,
    nombre_insumo     VARCHAR(150) NOT NULL,
    cantidad_unitaria NUMERIC(10,4) NOT NULL DEFAULT 1,
    unidad_medida     VARCHAR(30) NOT NULL DEFAULT 'unidad',
    notas             TEXT
);",

'Tabla ordenes_produccion' => "
CREATE TABLE IF NOT EXISTS ordenes_produccion (
    id           SERIAL PRIMARY KEY,
    codigo_orden VARCHAR(30) UNIQUE NOT NULL,
    producto_id  INT NOT NULL REFERENCES productos_maestros(id),
    cantidad     INT NOT NULL CHECK (cantidad > 0),
    estado       VARCHAR(30) DEFAULT 'Pendiente',
    observaciones TEXT,
    created_at   TIMESTAMPTZ DEFAULT NOW(),
    updated_at   TIMESTAMPTZ DEFAULT NOW()
);",

'Ajustar columna tablero_id en piezas_modelo' => "
DO \$\$
BEGIN
    -- Renombrar material_id a tablero_id si existe con el nombre viejo
    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name = 'piezas_modelo' AND column_name = 'material_id'
    ) THEN
        ALTER TABLE piezas_modelo RENAME COLUMN material_id TO tablero_id;
    END IF;
    -- Agregar tablero_id si no existe ninguno de los dos
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name = 'piezas_modelo' AND column_name = 'tablero_id'
    ) THEN
        ALTER TABLE piezas_modelo ADD COLUMN tablero_id INT;
    END IF;
END
\$\$;",

'Corregir FK tablero_id en piezas_modelo' => "
DO \$\$
DECLARE
    r RECORD;
BEGIN
    -- Eliminar TODAS las FK del campo tablero_id que no apunten a despiece_tableros
    FOR r IN
        SELECT tc.constraint_name
        FROM information_schema.table_constraints tc
        JOIN information_schema.key_column_usage kcu
            ON tc.constraint_name = kcu.constraint_name
        JOIN information_schema.referential_constraints rc
            ON tc.constraint_name = rc.constraint_name
        JOIN information_schema.table_constraints ccu
            ON rc.unique_constraint_name = ccu.constraint_name
        WHERE tc.table_name = 'piezas_modelo'
          AND tc.constraint_type = 'FOREIGN KEY'
          AND kcu.column_name = 'tablero_id'
          AND ccu.table_name <> 'despiece_tableros'
    LOOP
        EXECUTE 'ALTER TABLE piezas_modelo DROP CONSTRAINT ' || quote_ident(r.constraint_name);
    END LOOP;
    -- Agregar FK correcta si no existe
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.table_constraints tc
        JOIN information_schema.key_column_usage kcu ON tc.constraint_name = kcu.constraint_name
        JOIN information_schema.referential_constraints rc ON tc.constraint_name = rc.constraint_name
        JOIN information_schema.table_constraints ccu ON rc.unique_constraint_name = ccu.constraint_name
        WHERE tc.table_name = 'piezas_modelo'
          AND tc.constraint_type = 'FOREIGN KEY'
          AND kcu.column_name = 'tablero_id'
          AND ccu.table_name = 'despiece_tableros'
    ) THEN
        ALTER TABLE piezas_modelo
        ADD CONSTRAINT piezas_modelo_tablero_id_fkey
        FOREIGN KEY (tablero_id) REFERENCES despiece_tableros(id);
    END IF;
END
\$\$;",

'Índices de rendimiento' => "
CREATE INDEX IF NOT EXISTS idx_piezas_producto  ON piezas_modelo(producto_id);
CREATE INDEX IF NOT EXISTS idx_insumos_producto ON insumos_modelo(producto_id);
CREATE INDEX IF NOT EXISTS idx_ordenes_producto ON ordenes_produccion(producto_id);",

'Función fn_bom_piezas' => "
CREATE OR REPLACE FUNCTION fn_bom_piezas(p_producto_id INT, p_cantidad INT)
RETURNS TABLE (
    nro                   SMALLINT,
    nombre_pieza          VARCHAR,
    material              VARCHAR,
    largo_corte_mm        NUMERIC,
    ancho_corte_mm        NUMERIC,
    espesor_mm            NUMERIC,
    cant_por_mueble       NUMERIC,
    cant_total            NUMERIC,
    l1_canto_mm           NUMERIC,
    l2_canto_mm           NUMERIC,
    a1_canto_mm           NUMERIC,
    a2_canto_mm           NUMERIC,
    ml_tapacanto_sin_desp NUMERIC,
    ml_tapacanto_con_desp NUMERIC,
    area_pieza_m2         NUMERIC,
    area_total_m2         NUMERIC,
    planchas_necesarias   INT,
    tiene_ranura          BOOLEAN,
    tiene_perforacion     BOOLEAN,
    notas                 TEXT
)
LANGUAGE plpgsql AS \$\$
DECLARE v_area_plancha NUMERIC;
BEGIN
    SELECT COALESCE(AVG((ancho_plancha_mm/1000.0)*(largo_plancha_mm/1000.0)), 5.2216)
    INTO v_area_plancha FROM despiece_tableros WHERE activo = TRUE;

    RETURN QUERY
    SELECT
        pm.nro_pieza,
        pm.nombre_pieza::VARCHAR,
        COALESCE(t.nombre, 'Sin material')::VARCHAR,
        (pm.largo_final_mm
            - CASE WHEN pm.a1_canto_mm >= 1 THEN pm.a1_canto_mm ELSE 0 END
            - CASE WHEN pm.a2_canto_mm >= 1 THEN pm.a2_canto_mm ELSE 0 END
        )::NUMERIC(8,2),
        (pm.ancho_final_mm
            - CASE WHEN pm.l1_canto_mm >= 1 THEN pm.l1_canto_mm ELSE 0 END
            - CASE WHEN pm.l2_canto_mm >= 1 THEN pm.l2_canto_mm ELSE 0 END
        )::NUMERIC(8,2),
        COALESCE(pm.espesor_mm, t.espesor_mm, 18)::NUMERIC(5,2),
        pm.cant_por_mueble::NUMERIC(6,2),
        (pm.cant_por_mueble * p_cantidad)::NUMERIC(8,2),
        pm.l1_canto_mm::NUMERIC(4,2),
        pm.l2_canto_mm::NUMERIC(4,2),
        pm.a1_canto_mm::NUMERIC(4,2),
        pm.a2_canto_mm::NUMERIC(4,2),
        ROUND((
            (CASE WHEN pm.l1_canto_mm > 0 THEN pm.largo_final_mm ELSE 0 END) +
            (CASE WHEN pm.l2_canto_mm > 0 THEN pm.largo_final_mm ELSE 0 END) +
            (CASE WHEN pm.a1_canto_mm > 0 THEN pm.ancho_final_mm ELSE 0 END) +
            (CASE WHEN pm.a2_canto_mm > 0 THEN pm.ancho_final_mm ELSE 0 END)
        ) / 1000.0 * (pm.cant_por_mueble * p_cantidad), 3),
        ROUND((
            (CASE WHEN pm.l1_canto_mm > 0 THEN pm.largo_final_mm ELSE 0 END) +
            (CASE WHEN pm.l2_canto_mm > 0 THEN pm.largo_final_mm ELSE 0 END) +
            (CASE WHEN pm.a1_canto_mm > 0 THEN pm.ancho_final_mm ELSE 0 END) +
            (CASE WHEN pm.a2_canto_mm > 0 THEN pm.ancho_final_mm ELSE 0 END)
        ) / 1000.0 * (pm.cant_por_mueble * p_cantidad) * 1.10, 3),
        ROUND((pm.largo_final_mm/1000.0)*(pm.ancho_final_mm/1000.0), 4),
        ROUND((pm.largo_final_mm/1000.0)*(pm.ancho_final_mm/1000.0)*(pm.cant_por_mueble*p_cantidad), 4),
        CEIL((pm.largo_final_mm/1000.0)*(pm.ancho_final_mm/1000.0)*(pm.cant_por_mueble*p_cantidad)/v_area_plancha)::INT,
        (pm.ranura_lado IS NOT NULL),
        (pm.perf_cant > 0),
        pm.notas
    FROM piezas_modelo pm
    LEFT JOIN despiece_tableros t ON pm.tablero_id = t.id
    WHERE pm.producto_id = p_producto_id
    ORDER BY pm.nro_pieza;
END;
\$\$;",

'Función fn_bom_insumos' => "
CREATE OR REPLACE FUNCTION fn_bom_insumos(p_producto_id INT, p_cantidad INT)
RETURNS TABLE (
    nombre_insumo   VARCHAR,
    cant_por_mueble NUMERIC,
    cant_total      NUMERIC,
    unidad_medida   VARCHAR,
    notas           TEXT
)
LANGUAGE sql AS \$\$
    SELECT im.nombre_insumo::VARCHAR, im.cantidad_unitaria,
           (im.cantidad_unitaria * p_cantidad)::NUMERIC(10,4),
           im.unidad_medida::VARCHAR, im.notas
    FROM insumos_modelo im
    WHERE im.producto_id = p_producto_id
    ORDER BY im.nombre_insumo;
\$\$;",

'Función fn_bom_resumen_compras' => "
CREATE OR REPLACE FUNCTION fn_bom_resumen_compras(p_producto_id INT, p_cantidad INT)
RETURNS TABLE (
    material           VARCHAR,
    area_total_m2      NUMERIC,
    planchas_totales   INT,
    ml_tapacanto_total NUMERIC
)
LANGUAGE plpgsql AS \$\$
BEGIN
    RETURN QUERY
    SELECT
        COALESCE(t.nombre, 'Sin material')::VARCHAR AS material,
        ROUND(SUM((pm.largo_final_mm/1000.0)*(pm.ancho_final_mm/1000.0)*(pm.cant_por_mueble*p_cantidad)), 4) AS area_total_m2,
        CEIL(SUM((pm.largo_final_mm/1000.0)*(pm.ancho_final_mm/1000.0)*(pm.cant_por_mueble*p_cantidad))
             / COALESCE((t.ancho_plancha_mm/1000.0)*(t.largo_plancha_mm/1000.0), 5.2216))::INT AS planchas_totales,
        ROUND(SUM((
            (CASE WHEN pm.l1_canto_mm > 0 THEN pm.largo_final_mm ELSE 0 END) +
            (CASE WHEN pm.l2_canto_mm > 0 THEN pm.largo_final_mm ELSE 0 END) +
            (CASE WHEN pm.a1_canto_mm > 0 THEN pm.ancho_final_mm ELSE 0 END) +
            (CASE WHEN pm.a2_canto_mm > 0 THEN pm.ancho_final_mm ELSE 0 END)
        ) / 1000.0 * (pm.cant_por_mueble * p_cantidad) * 1.10), 3) AS ml_tapacanto_total
    FROM piezas_modelo pm
    LEFT JOIN despiece_tableros t ON pm.tablero_id = t.id
    WHERE pm.producto_id = p_producto_id
    GROUP BY t.nombre, t.ancho_plancha_mm, t.largo_plancha_mm
    ORDER BY 2 DESC;
END;
\$\$;",

'Tableros de ejemplo' => "
INSERT INTO despiece_tableros (nombre, espesor_mm, ancho_plancha_mm, largo_plancha_mm, precio_plancha)
VALUES
    ('Melamina Blanco 18mm', 18, 2440, 2140, 85.00),
    ('Melamina Gris 18mm',   18, 2440, 2140, 90.00),
    ('Melamina Negro 18mm',  18, 2440, 2140, 92.00),
    ('MDF 15mm',             15, 2440, 2140, 70.00),
    ('Triplay 4mm',           4, 2440, 1220, 35.00)
ON CONFLICT DO NOTHING;",

];

foreach ($sql_steps as $nombre => $sql) {
    try {
        $db->exec($sql);
        $pasos[] = ['ok' => true, 'nombre' => $nombre];
    } catch (PDOException $e) {
        $pasos[] = ['ok' => false, 'nombre' => $nombre, 'error' => $e->getMessage()];
        $errores[] = $nombre;
    }
}

$total_ok = count(array_filter($pasos, fn($p) => $p['ok']));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Migración — Despiece Carpicenter</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Inter',sans-serif;background:#0f0f1a;color:#f0f0f0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem}
        .container{max-width:700px;width:100%}
        .header{text-align:center;margin-bottom:2rem}
        .header h1{font-size:1.5rem;font-weight:700;margin-bottom:.3rem}
        .header p{color:#a0a0b0;font-size:.85rem}
        .badge{display:inline-flex;align-items:center;gap:.5rem;background:#C62828;color:#fff;padding:.4rem 1rem;border-radius:20px;font-size:.75rem;font-weight:600;letter-spacing:1px;margin-bottom:1rem}
        .card{background:#1a1a2e;border:1px solid #2a2a40;border-radius:16px;padding:1.8rem;margin-bottom:1.5rem}
        .step{display:flex;align-items:flex-start;gap:.8rem;padding:.55rem 0;border-bottom:1px solid rgba(255,255,255,.05);font-size:.85rem}
        .step:last-child{border-bottom:none}
        .step-icon{width:22px;height:22px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.7rem;flex-shrink:0;margin-top:1px}
        .ok{background:rgba(46,125,50,.2);color:#66BB6A}
        .err{background:rgba(198,40,40,.2);color:#ef4444}
        .err-msg{font-size:.72rem;color:#ef4444;margin-top:.2rem;background:rgba(198,40,40,.08);padding:.3rem .5rem;border-radius:4px;font-family:monospace;word-break:break-all}
        .summary{display:flex;align-items:center;gap:1rem;padding:1rem 1.4rem;border-radius:12px;font-weight:600;font-size:.9rem}
        .success{background:rgba(46,125,50,.15);border:1px solid rgba(46,125,50,.4);color:#66BB6A}
        .partial{background:rgba(249,168,37,.12);border:1px solid rgba(249,168,37,.35);color:#F9A825}
        .summary i{font-size:1.4rem}
        .btn-group{display:flex;gap:.8rem;margin-top:1.5rem;flex-wrap:wrap}
        .btn{display:inline-flex;align-items:center;gap:.5rem;padding:.7rem 1.4rem;border-radius:10px;font-size:.85rem;font-weight:600;text-decoration:none;border:none;cursor:pointer}
        .btn-primary{background:#C62828;color:#fff}.btn-primary:hover{background:#E53935}
        .btn-outline{background:transparent;border:1px solid #2a2a40;color:#a0a0b0}.btn-outline:hover{border-color:#f0f0f0;color:#f0f0f0}
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div class="badge"><i class="fas fa-database"></i> MIGRACIÓN SQL v2</div>
        <h1>Sistema de Hoja de Despiece</h1>
        <p>Creando tablas y funciones en PostgreSQL · <code>carpicenter_db</code></p>
    </div>

    <div class="summary <?= empty($errores) ? 'success' : 'partial' ?>">
        <i class="fas <?= empty($errores) ? 'fa-check-circle' : 'fa-exclamation-triangle' ?>"></i>
        <div>
            <strong><?= $total_ok ?> de <?= count($pasos) ?> pasos completados.</strong>
            <?php if (!empty($errores)): ?>
            <div style="font-size:.78rem;font-weight:400;margin-top:.2rem;">Errores en: <?= implode(', ', $errores) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card" style="margin-top:1.2rem;">
        <?php foreach ($pasos as $paso): ?>
        <div class="step">
            <div class="step-icon <?= $paso['ok'] ? 'ok' : 'err' ?>">
                <i class="fas <?= $paso['ok'] ? 'fa-check' : 'fa-times' ?>"></i>
            </div>
            <div style="flex:1;">
                <?= htmlspecialchars($paso['nombre']) ?>
                <?php if (!$paso['ok']): ?>
                <div class="err-msg"><?= htmlspecialchars($paso['error']) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="btn-group">
        <?php if (empty($errores)): ?>
        <a href="/carpicenter_sys/modules/despiece/modelos.php" class="btn btn-primary">
            <i class="fas fa-drafting-compass"></i> Ir al Módulo de Despiece
        </a>
        <?php else: ?>
        <a href="migrate_despiece.php" class="btn btn-primary"><i class="fas fa-sync"></i> Reintentar</a>
        <?php endif; ?>
        <a href="/carpicenter_sys/views/dashboard.php" class="btn btn-outline"><i class="fas fa-home"></i> Dashboard</a>
    </div>

    <?php if (empty($errores)): ?>
    <p style="font-size:.72rem;color:#5a5a65;margin-top:1.2rem;text-align:center;">
        <i class="fas fa-shield-alt"></i> Elimina este archivo después de la migración:
        <code style="color:#a0a0b0;">modules/despiece/migrate_despiece.php</code>
    </p>
    <?php endif; ?>
</div>
</body>
</html>
