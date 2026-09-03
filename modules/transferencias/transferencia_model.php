<?php
require_once __DIR__ . '/../../config/db.php';

class TransferenciaModel {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function getLocales() {
        $stmt = $this->db->query("SELECT * FROM locales ORDER BY id");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProductosStockLocal($local_id) {
        $stmt = $this->db->prepare("
            SELECT p.id, c.id as color_id, p.nombre, p.codigo, c.nombre as color_nombre, c.codigo as color_codigo, COALESCE(il.stock_actual, 0) as stock
            FROM inventario_local il
            JOIN productos p ON il.producto_id = p.id
            JOIN colores c ON il.color_id = c.id
            WHERE il.local_id = :local_id AND COALESCE(il.stock_actual, 0) > 0
            ORDER BY p.nombre, c.nombre
        ");
        $stmt->execute([':local_id' => $local_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProductoInfo($id) {
        $stmt = $this->db->prepare("SELECT id, nombre, codigo, precio_venta FROM productos WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getAll($local_id = null) {
        $sql = "
            SELECT t.*, 
                   lo.nombre as origen_nombre, 
                   ld.nombre as destino_nombre 
            FROM transferencias t
            LEFT JOIN locales lo ON t.local_origen_id = lo.id
            LEFT JOIN locales ld ON t.local_destino_id = ld.id
        ";
        $params = [];
        if ($local_id) {
            $sql .= " WHERE t.local_origen_id = :lid OR t.local_destino_id = :lid2";
            $params[':lid'] = $local_id;
            $params[':lid2'] = $local_id;
        }
        $sql .= " ORDER BY t.fecha_creacion DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        $stmt = $this->db->prepare("
            SELECT t.*, 
                   lo.nombre as origen_nombre, 
                   ld.nombre as destino_nombre 
            FROM transferencias t
            LEFT JOIN locales lo ON t.local_origen_id = lo.id
            LEFT JOIN locales ld ON t.local_destino_id = ld.id
            WHERE t.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $transferencia = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($transferencia) {
            $stmtDetalle = $this->db->prepare("
                SELECT td.*, p.nombre as producto_nombre, p.codigo as producto_codigo, c.nombre as color_nombre, c.codigo as color_codigo 
                FROM transferencia_detalles td
                LEFT JOIN productos p ON td.producto_id = p.id
                LEFT JOIN colores c ON td.color_id = c.id
                WHERE td.transferencia_id = :id
            ");
            $stmtDetalle->execute([':id' => $id]);
            $transferencia['detalles'] = $stmtDetalle->fetchAll(PDO::FETCH_ASSOC);
        }

        return $transferencia;
    }

    public function generateCodigo() {
        $stmt = $this->db->query("SELECT codigo FROM transferencias ORDER BY id DESC LIMIT 1");
        $lastCodigo = $stmt->fetchColumn();
        
        if ($lastCodigo) {
            preg_match('/\d+/', $lastCodigo, $matches);
            if (!empty($matches)) {
                $nextNum = intval($matches[0]) + 1;
                return 'TR-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
            }
        }
        return 'TR-0001';
    }

    public function create($data, $detalles) {
        try {
            $this->db->beginTransaction();

            $codigo = $this->generateCodigo();

            // Obtener nombres de locales para la auditoría
            $stmtLocO = $this->db->prepare("SELECT nombre FROM locales WHERE id = :id");
            $stmtLocO->execute([':id' => $data['local_origen_id']]);
            $origenNombre = $stmtLocO->fetchColumn() ?: 'Local Origen';

            $stmtLocD = $this->db->prepare("SELECT nombre FROM locales WHERE id = :id");
            $stmtLocD->execute([':id' => $data['local_destino_id']]);
            $destinoNombre = $stmtLocD->fetchColumn() ?: 'Local Destino';

            // Insert Transferencia
            $stmt = $this->db->prepare("
                INSERT INTO transferencias (codigo, local_origen_id, local_destino_id, estado, motivo, observaciones) 
                VALUES (:codigo, :origen, :destino, 'En Tránsito', :motivo, :observaciones) RETURNING id
            ");
            $stmt->execute([
                ':codigo' => $codigo,
                ':origen' => $data['local_origen_id'],
                ':destino' => $data['local_destino_id'],
                ':motivo' => $data['motivo'],
                ':observaciones' => $data['observaciones']
            ]);
            $transId = $stmt->fetchColumn();

            // Insert Detalles and Subtract Stock from Origen
            $stmtDet = $this->db->prepare("
                INSERT INTO transferencia_detalles (transferencia_id, producto_id, color_id, cantidad_enviada) 
                VALUES (:trans_id, :prod_id, :color_id, :cantidad)
            ");
            $stmtSubStock = $this->db->prepare("
                UPDATE inventario_local SET stock_actual = GREATEST(COALESCE(stock_actual, 0) - :cantidad, 0) 
                WHERE local_id = :origen AND producto_id = :prod_id AND color_id = :color_id
                RETURNING stock_actual
            ");

            $stmtKardex = $this->db->prepare("
                INSERT INTO kardex (tipo_movimiento, producto_id, color_id, local_id, cantidad, stock_resultante, motivo, documento_referencia, usuario_id)
                VALUES ('Salida', :p, :c, :l, :cant, :stock_res, :motivo, :doc_ref, :u)
            ");

            $userId = $_SESSION['user_id'] ?? null;

            foreach ($detalles as $det) {
                if ($det['cantidad'] <= 0) continue;

                $stmtDet->execute([
                    ':trans_id' => $transId,
                    ':prod_id' => $det['producto_id'],
                    ':color_id' => $det['color_id'],
                    ':cantidad' => $det['cantidad']
                ]);

                // Subtract from origin
                $stmtSubStock->execute([
                    ':cantidad' => $det['cantidad'],
                    ':origen' => $data['local_origen_id'],
                    ':prod_id' => $det['producto_id'],
                    ':color_id' => $det['color_id']
                ]);
                $stockResultante = $stmtSubStock->fetchColumn() ?: 0;

                // Registrar Salida por Transferencia en Kardex
                $stmtKardex->execute([
                    ':p' => $det['producto_id'],
                    ':c' => $det['color_id'],
                    ':l' => $data['local_origen_id'],
                    ':cant' => $det['cantidad'],
                    ':stock_res' => $stockResultante,
                    ':motivo' => 'Envío por Transferencia hacia ' . $destinoNombre . (!empty($data['motivo']) ? ' (' . $data['motivo'] . ')' : ''),
                    ':doc_ref' => 'Transferencia ' . $codigo,
                    ':u' => $userId
                ]);
            }

            $this->db->commit();
            return $transId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function confirmReception($id, $cantidadesRecibidas) {
        try {
            $this->db->beginTransaction();

            $trans = $this->getById($id);
            if (!$trans || $trans['estado'] !== 'En Tránsito') {
                throw new Exception("La transferencia no se puede confirmar.");
            }

            // Nombres de locales
            $stmtLocO = $this->db->prepare("SELECT nombre FROM locales WHERE id = :id");
            $stmtLocO->execute([':id' => $trans['local_origen_id']]);
            $origenNombre = $stmtLocO->fetchColumn() ?: 'Local Origen';

            // Update Transferencia
            $stmt = $this->db->prepare("
                UPDATE transferencias 
                SET estado = 'Completada', fecha_recepcion = CURRENT_TIMESTAMP 
                WHERE id = :id
            ");
            $stmt->execute([':id' => $id]);

            // Update Detalles and Add Stock to Destino
            $stmtUpdateDet = $this->db->prepare("
                UPDATE transferencia_detalles 
                SET cantidad_recibida = :recibida 
                WHERE id = :det_id
            ");
            $stmtAddStock = $this->db->prepare("
                INSERT INTO inventario_local (producto_id, color_id, local_id, stock_actual) 
                VALUES (:prod_id, :color_id, :destino, :cantidad)
                ON CONFLICT (producto_id, local_id, color_id) 
                DO UPDATE SET stock_actual = inventario_local.stock_actual + :cantidad
                RETURNING stock_actual
            ");

            $stmtKardex = $this->db->prepare("
                INSERT INTO kardex (tipo_movimiento, producto_id, color_id, local_id, cantidad, stock_resultante, motivo, documento_referencia, usuario_id)
                VALUES ('Entrada', :p, :c, :l, :cant, :stock_res, :motivo, :doc_ref, :u)
            ");

            $userId = $_SESSION['user_id'] ?? null;

            foreach ($cantidadesRecibidas as $det_id => $recibida) {
                // Find detail info
                $detStmt = $this->db->prepare("SELECT producto_id, color_id FROM transferencia_detalles WHERE id = :det_id AND transferencia_id = :trans_id");
                $detStmt->execute([':det_id' => $det_id, ':trans_id' => $id]);
                $detInfo = $detStmt->fetch(PDO::FETCH_ASSOC);

                if ($detInfo) {
                    $stmtUpdateDet->execute([
                        ':recibida' => $recibida,
                        ':det_id' => $det_id
                    ]);

                    if ($recibida > 0) {
                        $stmtAddStock->execute([
                            ':prod_id' => $detInfo['producto_id'],
                            ':color_id' => $detInfo['color_id'],
                            ':destino' => $trans['local_destino_id'],
                            ':cantidad' => $recibida
                        ]);
                        $stockDestinoRes = $stmtAddStock->fetchColumn() ?: $recibida;

                        // Registrar Entrada por Transferencia en Kardex
                        $stmtKardex->execute([
                            ':p' => $detInfo['producto_id'],
                            ':c' => $detInfo['color_id'],
                            ':l' => $trans['local_destino_id'],
                            ':cant' => $recibida,
                            ':stock_res' => $stockDestinoRes,
                            ':motivo' => 'Recepción de Transferencia desde ' . $origenNombre,
                            ':doc_ref' => 'Transferencia ' . $trans['codigo'],
                            ':u' => $userId
                        ]);
                    }
                }
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
?>
