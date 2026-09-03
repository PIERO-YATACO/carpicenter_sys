<?php
require_once __DIR__ . '/../../config/db.php';

class ContratoModel {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function generateNumero($serie = 'T003') {
        $stmt = $this->db->prepare("SELECT numero FROM contratos WHERE serie = :serie ORDER BY id DESC LIMIT 1");
        $stmt->execute([':serie' => $serie]);
        $lastNumero = $stmt->fetchColumn();

        if ($lastNumero) {
            $nextNum = intval($lastNumero) + 1;
            return str_pad($nextNum, 5, '0', STR_PAD_LEFT);
        }
        return '00912'; // Default seed matching sample talonario
    }

    public function getAll($filters = []) {
        $sql = "
            SELECT 
                c.*,
                cli.nombre as cliente_nombre,
                cli.dni_ruc as cliente_doc,
                cli.telefono as cliente_telefono,
                l.nombre as local_nombre,
                u.nombre_completo as vendedor_nombre,
                (SELECT COUNT(*) FROM contrato_detalles cd WHERE cd.contrato_id = c.id) as total_items
            FROM contratos c
            LEFT JOIN clientes cli ON c.cliente_id = cli.id
            LEFT JOIN locales l ON c.local_id = l.id
            LEFT JOIN usuarios u ON c.vendedor_id = u.id
            WHERE 1=1
        ";

        $params = [];

        if (!empty($filters['vendedor_id'])) {
            $sql .= " AND c.vendedor_id = :vendedor_id";
            $params[':vendedor_id'] = $filters['vendedor_id'];
        }

        if (!empty($filters['local_id'])) {
            $sql .= " AND c.local_id = :local_id";
            $params[':local_id'] = $filters['local_id'];
        }

        if (!empty($filters['estado'])) {
            $sql .= " AND c.estado_contrato = :estado";
            $params[':estado'] = $filters['estado'];
        }

        if (!empty($filters['prioridad'])) {
            $sql .= " AND c.prioridad = :prioridad";
            $params[':prioridad'] = $filters['prioridad'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (c.codigo_completo ILIKE :search OR cli.nombre ILIKE :search OR cli.dni_ruc ILIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $sql .= " ORDER BY c.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        $stmt = $this->db->prepare("
            SELECT 
                c.*,
                cli.nombre as cliente_nombre,
                cli.dni_ruc as cliente_doc,
                cli.telefono as cliente_telefono,
                cli.direccion as cliente_direccion_base,
                l.nombre as local_nombre,
                u.nombre_completo as vendedor_nombre
            FROM contratos c
            LEFT JOIN clientes cli ON c.cliente_id = cli.id
            LEFT JOIN locales l ON c.local_id = l.id
            LEFT JOIN usuarios u ON c.vendedor_id = u.id
            WHERE c.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $contrato = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($contrato) {
            // Get items
            $stmtDet = $this->db->prepare("
                SELECT cd.*, p.nombre as producto_nombre, p.codigo as producto_codigo, col.nombre as color_nombre, col.codigo as color_codigo
                FROM contrato_detalles cd
                LEFT JOIN productos p ON cd.producto_id = p.id
                LEFT JOIN colores col ON cd.color_id = col.id
                WHERE cd.contrato_id = :id
                ORDER BY cd.id ASC
            ");
            $stmtDet->execute([':id' => $id]);
            $contrato['detalles'] = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

            // Get abonos
            $stmtAb = $this->db->prepare("
                SELECT ca.*, u.nombre_completo as registrado_por 
                FROM contrato_abonos ca
                LEFT JOIN usuarios u ON ca.usuario_id = u.id
                WHERE ca.contrato_id = :id
                ORDER BY ca.id ASC
            ");
            $stmtAb->execute([':id' => $id]);
            $contrato['abonos'] = $stmtAb->fetchAll(PDO::FETCH_ASSOC);

            // Check signature from contrato first, or fallback to cotizacion
            if (empty($contrato['firma_digital']) && !empty($contrato['cotizacion_id'])) {
                $stmtCot = $this->db->prepare("SELECT firma_digital FROM cotizaciones WHERE id = ?");
                $stmtCot->execute([$contrato['cotizacion_id']]);
                $contrato['firma_digital'] = $stmtCot->fetchColumn();
            }
        }

        return $contrato;
    }

    public function getByToken($token) {
        $stmt = $this->db->prepare("
            SELECT 
                c.*,
                cli.nombre as cliente_nombre,
                cli.dni_ruc as cliente_doc,
                cli.telefono as cliente_telefono,
                cli.direccion as cliente_direccion_base,
                l.nombre as local_nombre,
                u.nombre_completo as vendedor_nombre
            FROM contratos c
            LEFT JOIN clientes cli ON c.cliente_id = cli.id
            LEFT JOIN locales l ON c.local_id = l.id
            LEFT JOIN usuarios u ON c.vendedor_id = u.id
            WHERE c.firma_token = :token
        ");
        $stmt->execute([':token' => $token]);
        $contrato = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($contrato) {
            $stmtDet = $this->db->prepare("
                SELECT cd.*, p.nombre as producto_nombre, p.codigo as producto_codigo, col.nombre as color_nombre, col.codigo as color_codigo
                FROM contrato_detalles cd
                LEFT JOIN productos p ON cd.producto_id = p.id
                LEFT JOIN colores col ON cd.color_id = col.id
                WHERE cd.contrato_id = :id
                ORDER BY cd.id ASC
            ");
            $stmtDet->execute([':id' => $contrato['id']]);
            $contrato['detalles'] = $stmtDet->fetchAll(PDO::FETCH_ASSOC);
        }

        return $contrato;
    }

    public function saveFirmaByToken($token, $firmaBase64) {
        $stmt = $this->db->prepare("
            UPDATE contratos 
            SET firma_digital = :firma, fecha_firma = NOW() 
            WHERE firma_token = :token
        ");
        return $stmt->execute([':firma' => $firmaBase64, ':token' => $token]);
    }

    public function create($data, $detalles) {
        try {
            $this->db->beginTransaction();

            $serie = !empty($data['serie']) ? strtoupper(trim($data['serie'])) : 'T003';
            $numero = !empty($data['numero']) ? trim($data['numero']) : $this->generateNumero($serie);
            $codigoCompleto = $serie . '-' . $numero;

            $costoMovilidad = floatval($data['costo_movilidad'] ?? 0);
            $total = floatval($data['monto_total']);
            $adelanto = floatval($data['monto_adelanto']);
            $saldo = max(0, $total - $adelanto);
            $token = !empty($data['firma_token']) ? $data['firma_token'] : bin2hex(random_bytes(16));
            $firmaDigital = !empty($data['firma_digital']) ? $data['firma_digital'] : null;

            // If not signed yet but linked to an already signed cotizacion, inherit signature
            if (empty($firmaDigital) && !empty($data['cotizacion_id'])) {
                $stmtCotSig = $this->db->prepare("SELECT firma_digital FROM cotizaciones WHERE id = ?");
                $stmtCotSig->execute([$data['cotizacion_id']]);
                $cSig = $stmtCotSig->fetchColumn();
                if ($cSig) {
                    $firmaDigital = $cSig;
                }
            }

            $stmt = $this->db->prepare("
                INSERT INTO contratos (
                    serie, numero, codigo_completo, cliente_id, vendedor_id, local_id,
                    fecha_entrega_estimada, tipo_entrega, direccion_entrega, referencia_entrega,
                    instalacion_incluida, prioridad, estado_contrato, estado_produccion,
                    monto_total, monto_adelanto, monto_saldo, metodo_pago, observaciones_generales, 
                    costo_movilidad, cotizacion_id, firma_token, firma_digital, fecha_firma
                ) VALUES (
                    :serie, :numero, :codigo_completo, :cliente_id, :vendedor_id, :local_id,
                    :fecha_entrega_estimada, :tipo_entrega, :direccion_entrega, :referencia_entrega,
                    :instalacion_incluida, :prioridad, :estado_contrato, :estado_produccion,
                    :monto_total, :monto_adelanto, :monto_saldo, :metodo_pago, :observaciones_generales, 
                    :costo_movilidad, :cotizacion_id, :firma_token, :firma_digital, :fecha_firma
                ) RETURNING id
            ");

            $stmt->execute([
                ':serie' => $serie,
                ':numero' => $numero,
                ':codigo_completo' => $codigoCompleto,
                ':cliente_id' => $data['cliente_id'],
                ':vendedor_id' => $data['vendedor_id'],
                ':local_id' => $data['local_id'],
                ':fecha_entrega_estimada' => !empty($data['fecha_entrega_estimada']) ? $data['fecha_entrega_estimada'] : null,
                ':tipo_entrega' => $data['tipo_entrega'] ?? 'Recojo en Tienda',
                ':direccion_entrega' => $data['direccion_entrega'] ?? null,
                ':referencia_entrega' => $data['referencia_entrega'] ?? null,
                ':instalacion_incluida' => !empty($data['instalacion_incluida']) ? 'true' : 'false',
                ':prioridad' => $data['prioridad'] ?? 'Normal',
                ':estado_contrato' => 'Pendiente',
                ':estado_produccion' => 'Pendiente',
                ':monto_total' => $total,
                ':monto_adelanto' => $adelanto,
                ':monto_saldo' => $saldo,
                ':metodo_pago' => $data['metodo_pago'] ?? 'Efectivo',
                ':observaciones_generales' => $data['observaciones_generales'] ?? '',
                ':costo_movilidad' => $costoMovilidad,
                ':cotizacion_id' => $data['cotizacion_id'] ?? null,
                ':firma_token' => $token,
                ':firma_digital' => $firmaDigital,
                ':fecha_firma' => $firmaDigital ? date('Y-m-d H:i:s') : null
            ]);

            $contratoId = $stmt->fetchColumn();

            // Insert details
            $stmtDet = $this->db->prepare("
                INSERT INTO contrato_detalles (
                    contrato_id, producto_id, descripcion, color_id, cantidad, precio_unitario, subtotal, observaciones_item, origen_item
                ) VALUES (
                    :contrato_id, :producto_id, :descripcion, :color_id, :cantidad, :precio_unitario, :subtotal, :observaciones_item, :origen_item
                )
            ");

            foreach ($detalles as $det) {
                if (empty($det['descripcion']) || floatval($det['precio_unitario']) < 0) continue;

                $cant = max(1, intval($det['cantidad']));
                $punit = floatval($det['precio_unitario']);
                $subtotal = $cant * $punit;
                $prodId = !empty($det['producto_id']) ? intval($det['producto_id']) : null;
                $colorId = !empty($det['color_id']) ? intval($det['color_id']) : null;
                $origenItem = !empty($det['origen_item']) ? trim($det['origen_item']) : 'Producción';

                $stmtDet->execute([
                    ':contrato_id' => $contratoId,
                    ':producto_id' => $prodId,
                    ':descripcion' => trim($det['descripcion']),
                    ':color_id' => $colorId,
                    ':cantidad' => $cant,
                    ':precio_unitario' => $punit,
                    ':subtotal' => $subtotal,
                    ':observaciones_item' => $det['observaciones_item'] ?? null,
                    ':origen_item' => $origenItem
                ]);

                // Reservar stock en inventario_local ÚNICAMENTE si el origen es 'Stock'
                if ($origenItem === 'Stock' && $prodId && $colorId && !empty($data['local_id'])) {
                    $localId = intval($data['local_id']);
                    $stmtCheckInv = $this->db->prepare("SELECT id FROM inventario_local WHERE producto_id = :p AND color_id = :c AND local_id = :l");
                    $stmtCheckInv->execute([':p' => $prodId, ':c' => $colorId, ':l' => $localId]);
                    $invId = $stmtCheckInv->fetchColumn();

                    if ($invId) {
                        $stmtUpdRes = $this->db->prepare("UPDATE inventario_local SET stock_reservado = COALESCE(stock_reservado, 0) + :cant WHERE id = :id");
                        $stmtUpdRes->execute([':cant' => $cant, ':id' => $invId]);
                    } else {
                        $stmtInsInv = $this->db->prepare("INSERT INTO inventario_local (producto_id, color_id, local_id, stock_actual, stock_reservado) VALUES (:p, :c, :l, 0, :cant)");
                        $stmtInsInv->execute([':p' => $prodId, ':c' => $colorId, ':l' => $localId, ':cant' => $cant]);
                    }
                }
            }

            // Register initial payment as first abono if > 0
            if ($adelanto > 0) {
                $stmtAb = $this->db->prepare("
                    INSERT INTO contrato_abonos (contrato_id, monto, metodo_pago, observacion, usuario_id)
                    VALUES (:contrato_id, :monto, :metodo_pago, 'Adelanto inicial de contrato', :usuario_id)
                ");
                $stmtAb->execute([
                    ':contrato_id' => $contratoId,
                    ':monto' => $adelanto,
                    ':metodo_pago' => $data['metodo_pago'] ?? 'Efectivo',
                    ':usuario_id' => $data['vendedor_id']
                ]);
            }

            $this->db->commit();
            return $contratoId;

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function update($contratoId, $data, $detalles) {
        try {
            $this->db->beginTransaction();

            $costoMovilidad = floatval($data['costo_movilidad'] ?? 0);
            $total = floatval($data['monto_total']);
            $adelanto = floatval($data['monto_adelanto']);
            $saldo = max(0, $total - $adelanto);

            // 1. Update contract main table, reset digital signature so the client signs the updated version
            $stmt = $this->db->prepare("
                UPDATE contratos SET
                    cliente_id = :cliente_id,
                    local_id = :local_id,
                    fecha_entrega_estimada = :fecha_entrega_estimada,
                    tipo_entrega = :tipo_entrega,
                    direccion_entrega = :direccion_entrega,
                    referencia_entrega = :referencia_entrega,
                    instalacion_incluida = :instalacion_incluida,
                    monto_total = :monto_total,
                    monto_adelanto = :monto_adelanto,
                    monto_saldo = :monto_saldo,
                    metodo_pago = :metodo_pago,
                    observaciones_generales = :observaciones_generales,
                    costo_movilidad = :costo_movilidad,
                    firma_digital = NULL,
                    fecha_firma = NULL
                WHERE id = :id
            ");

            $stmt->execute([
                ':cliente_id' => $data['cliente_id'],
                ':local_id' => $data['local_id'],
                ':fecha_entrega_estimada' => !empty($data['fecha_entrega_estimada']) ? $data['fecha_entrega_estimada'] : null,
                ':tipo_entrega' => $data['tipo_entrega'] ?? 'Recojo en Tienda',
                ':direccion_entrega' => $data['direccion_entrega'] ?? null,
                ':referencia_entrega' => $data['referencia_entrega'] ?? null,
                ':instalacion_incluida' => !empty($data['instalacion_incluida']) ? 'true' : 'false',
                ':monto_total' => $total,
                ':monto_adelanto' => $adelanto,
                ':monto_saldo' => $saldo,
                ':metodo_pago' => $data['metodo_pago'] ?? 'Efectivo',
                ':observaciones_generales' => $data['observaciones_generales'] ?? '',
                ':costo_movilidad' => $costoMovilidad,
                ':id' => $contratoId
            ]);

            // 2. Remove old details
            $stmtDel = $this->db->prepare("DELETE FROM contrato_detalles WHERE contrato_id = :id");
            $stmtDel->execute([':id' => $contratoId]);

            // 3. Re-insert new/modified details
            $stmtDet = $this->db->prepare("
                INSERT INTO contrato_detalles (
                    contrato_id, producto_id, descripcion, color_id, cantidad, precio_unitario, subtotal, observaciones_item, origen_item
                ) VALUES (
                    :contrato_id, :producto_id, :descripcion, :color_id, :cantidad, :precio_unitario, :subtotal, :observaciones_item, :origen_item
                )
            ");

            foreach ($detalles as $det) {
                if (empty($det['descripcion']) || floatval($det['precio_unitario']) < 0) continue;

                $cant = max(1, intval($det['cantidad']));
                $punit = floatval($det['precio_unitario']);
                $subtotal = $cant * $punit;
                $prodId = !empty($det['producto_id']) ? intval($det['producto_id']) : null;
                $colorId = !empty($det['color_id']) ? intval($det['color_id']) : null;
                $origenItem = !empty($det['origen_item']) ? trim($det['origen_item']) : 'Producción';

                $stmtDet->execute([
                    ':contrato_id' => $contratoId,
                    ':producto_id' => $prodId,
                    ':descripcion' => trim($det['descripcion']),
                    ':color_id' => $colorId,
                    ':cantidad' => $cant,
                    ':precio_unitario' => $punit,
                    ':subtotal' => $subtotal,
                    ':observaciones_item' => $det['observaciones_item'] ?? null,
                    ':origen_item' => $origenItem
                ]);
            }

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function addAbono($contratoId, $monto, $metodoPago, $observacion, $usuarioId) {
        try {
            $this->db->beginTransaction();

            $contrato = $this->getById($contratoId);
            if (!$contrato) throw new Exception("Contrato no encontrado");

            $monto = floatval($monto);
            if ($monto <= 0) throw new Exception("El monto debe ser mayor a 0");

            $nuevoAdelanto = floatval($contrato['monto_adelanto']) + $monto;
            $nuevoSaldo = max(0, floatval($contrato['monto_total']) - $nuevoAdelanto);

            // Record abono
            $stmtAb = $this->db->prepare("
                INSERT INTO contrato_abonos (contrato_id, monto, metodo_pago, observacion, usuario_id)
                VALUES (:contrato_id, :monto, :metodo_pago, :observacion, :usuario_id)
            ");
            $stmtAb->execute([
                ':contrato_id' => $contratoId,
                ':monto' => $monto,
                ':metodo_pago' => $metodoPago,
                ':observacion' => $observacion,
                ':usuario_id' => $usuarioId
            ]);

            // Update contract balances
            $stmtUpd = $this->db->prepare("
                UPDATE contratos 
                SET monto_adelanto = :adelanto, monto_saldo = :saldo 
                WHERE id = :id
            ");
            $stmtUpd->execute([
                ':adelanto' => $nuevoAdelanto,
                ':saldo' => $nuevoSaldo,
                ':id' => $contratoId
            ]);

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function updateEstado($contratoId, $estadoContrato, $estadoProduccion = null) {
        try {
            $this->db->beginTransaction();

            $contrato = $this->getById($contratoId);
            $estadoAnterior = $contrato['estado_contrato'] ?? '';

            $sql = "UPDATE contratos SET estado_contrato = :estado";
            $params = [':estado' => $estadoContrato, ':id' => $contratoId];

            if ($estadoProduccion !== null) {
                $sql .= ", estado_produccion = :prod";
                $params[':prod'] = $estadoProduccion;
            }

            $sql .= " WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $res = $stmt->execute($params);

            // Ajustar reservas e inventario según el cambio de estado
            if ($contrato && !empty($contrato['detalles']) && !empty($contrato['local_id'])) {
                $localId = intval($contrato['local_id']);
                
                foreach ($contrato['detalles'] as $det) {
                    $prodId = intval($det['producto_id'] ?? 0);
                    $colId = intval($det['color_id'] ?? 0);
                    $cant = intval($det['cantidad'] ?? 0);

                    if ($prodId && $colId && $cant > 0) {
                        // Si se anula el contrato -> liberar stock_reservado
                        if ($estadoContrato === 'Anulado' && $estadoAnterior !== 'Anulado') {
                            $stmtUpd = $this->db->prepare("
                                UPDATE inventario_local 
                                SET stock_reservado = GREATEST(COALESCE(stock_reservado, 0) - :cant, 0)
                                WHERE producto_id = :p AND color_id = :c AND local_id = :l
                            ");
                            $stmtUpd->execute([':cant' => $cant, ':p' => $prodId, ':c' => $colId, ':l' => $localId]);
                        }
                        // Si se entrega/despacha -> descontar físico y liberar reservado
                        elseif (($estadoContrato === 'Entregado' || $estadoContrato === 'Despachado') && $estadoAnterior !== 'Entregado' && $estadoAnterior !== 'Despachado') {
                            $stmtUpd = $this->db->prepare("
                                UPDATE inventario_local 
                                SET stock_actual = GREATEST(COALESCE(stock_actual, 0) - :cant, 0),
                                    stock_reservado = GREATEST(COALESCE(stock_reservado, 0) - :cant, 0)
                                WHERE producto_id = :p AND color_id = :c AND local_id = :l
                            ");
                            $stmtUpd->execute([':cant' => $cant, ':p' => $prodId, ':c' => $colId, ':l' => $localId]);
                        }
                    }
                }
            }

            $this->db->commit();
            return $res;

        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function deleteContrato($contratoId) {
        try {
            $this->db->beginTransaction();

            $contrato = $this->getById($contratoId);
            if (!$contrato) throw new Exception("Contrato no encontrado.");

            // 1. Liberar stock reservado en inventario_local si estaba activo
            if (!empty($contrato['detalles']) && !empty($contrato['local_id']) && $contrato['estado_contrato'] !== 'Anulado') {
                $localId = intval($contrato['local_id']);
                foreach ($contrato['detalles'] as $det) {
                    $prodId = intval($det['producto_id'] ?? 0);
                    $colId = intval($det['color_id'] ?? 0);
                    $cant = intval($det['cantidad'] ?? 0);

                    if ($prodId && $colId && $cant > 0) {
                        $stmtUpd = $this->db->prepare("
                            UPDATE inventario_local 
                            SET stock_reservado = GREATEST(COALESCE(stock_reservado, 0) - :cant, 0)
                            WHERE producto_id = :p AND color_id = :c AND local_id = :l
                        ");
                        $stmtUpd->execute([':cant' => $cant, ':p' => $prodId, ':c' => $colId, ':l' => $localId]);
                    }
                }
            }

            // 2. Eliminar abonos, detalles y cabecera de contrato
            $stmtDelAb = $this->db->prepare("DELETE FROM contrato_abonos WHERE contrato_id = :id");
            $stmtDelAb->execute([':id' => $contratoId]);

            $stmtDelDet = $this->db->prepare("DELETE FROM contrato_detalles WHERE contrato_id = :id");
            $stmtDelDet->execute([':id' => $contratoId]);

            $stmtDelMain = $this->db->prepare("DELETE FROM contratos WHERE id = :id");
            $stmtDelMain->execute([':id' => $contratoId]);

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }
}
