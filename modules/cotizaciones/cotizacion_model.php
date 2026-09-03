<?php
require_once __DIR__ . '/../../config/db.php';

class CotizacionModel {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function getAll($userId = null, $userRole = null, $userLocalId = null, $filterVendedor = null, $filterLocal = null, $search = null, $estado = null) {
        $where = ["1=1"];
        $params = [];

        $isSeller = in_array(strtolower($userRole ?? ''), ['vendedor', 'vendedora']);

        // Regla de privacidad estricta: Vendedoras solo ven sus propias cotizaciones personales
        if ($isSeller) {
            $where[] = "(c.vendedor_id = :uid_seller OR c.usuario_id = :uid_seller)";
            $params[':uid_seller'] = $userId;
        } else {
            // Admin y Contabilidad pueden filtrar libremente por Vendedora o Tienda
            if (!empty($filterVendedor)) {
                $where[] = "c.vendedor_id = :f_vend";
                $params[':f_vend'] = $filterVendedor;
            }
            if (!empty($filterLocal)) {
                $where[] = "c.local_id = :f_loc";
                $params[':f_loc'] = $filterLocal;
            }
        }

        if (!empty($search)) {
            $where[] = "(c.numero ILIKE :s OR c.cliente_nombre ILIKE :s OR c.cliente_documento ILIKE :s)";
            $params[':s'] = "%$search%";
        }

        if (!empty($estado)) {
            $where[] = "c.estado = :est";
            $params[':est'] = $estado;
        }

        $whereClause = implode(" AND ", $where);

        $sql = "
            SELECT c.*, 
                   COALESCE(u.nombre_completo, u.username, c.vendedor_nombre, 'Sin Asignar') AS vendedor_display,
                   COALESCE(l.nombre, 'Sin Tienda') AS local_display,
                   COALESCE(uc.username, 'Sistema') AS digitado_por
            FROM cotizaciones c
            LEFT JOIN usuarios u ON c.vendedor_id = u.id
            LEFT JOIN locales l ON c.local_id = l.id
            LEFT JOIN usuarios uc ON c.usuario_id = uc.id
            WHERE $whereClause
            ORDER BY c.id DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        $stmt = $this->db->prepare("
            SELECT c.*, 
                   COALESCE(u.nombre_completo, u.username, c.vendedor_nombre, 'Sin Asignar') AS vendedor_display,
                   COALESCE(l.nombre, 'Sin Tienda') AS local_display
            FROM cotizaciones c
            LEFT JOIN usuarios u ON c.vendedor_id = u.id
            LEFT JOIN locales l ON c.local_id = l.id
            WHERE c.id = :id
        ");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $cotizacion = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($cotizacion) {
            $stmtDetalle = $this->db->prepare("SELECT * FROM cotizacion_detalle WHERE cotizacion_id = :id ORDER BY id ASC");
            $stmtDetalle->bindParam(':id', $id);
            $stmtDetalle->execute();
            $cotizacion['detalles'] = $stmtDetalle->fetchAll(PDO::FETCH_ASSOC);
        }

        return $cotizacion;
    }

    public function generateNextNumero($fecha = null) {
        $timestamp = !empty($fecha) ? strtotime($fecha) : time();
        if (!$timestamp) {
            $timestamp = time();
        }
        $year = date('Y', $timestamp);
        $month = str_pad(date('n', $timestamp), 3, '0', STR_PAD_LEFT);

        // Obtener el correlativo numérico secuencial más alto registrado
        $stmt = $this->db->query("SELECT numero FROM cotizaciones");
        $maxCorrelativo = 0;
        if ($stmt) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $num = trim($row['numero'] ?? '');
                // Captura el último grupo de dígitos (ej: "2026 008 016" -> 16, "2026-008-016" -> 16)
                if (preg_match('/(\d{1,6})$/', $num, $matches)) {
                    $val = (int)$matches[1];
                    if ($val > $maxCorrelativo) {
                        $maxCorrelativo = $val;
                    }
                }
            }
        }

        $nextCorrelativo = str_pad($maxCorrelativo + 1, 3, '0', STR_PAD_LEFT);
        return "{$year} {$month} {$nextCorrelativo}";
    }

    public function create($data, $detalles) {
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                INSERT INTO cotizaciones (
                    numero, cliente_nombre, cliente_documento, cliente_telefono, cliente_direccion, 
                    fecha, fecha_validez, total, observaciones, condiciones, estado, 
                    gastos_logisticos, modificacion_orden_compra, movilidad, 
                    usuario_id, vendedor_id, local_id, vendedor_nombre
                ) VALUES (
                    :numero, :cliente_nombre, :cliente_documento, :cliente_telefono, :cliente_direccion, 
                    :fecha, :fecha_validez, :total, :observaciones, :condiciones, :estado, 
                    :gastos_logisticos, :modificacion_orden_compra, :movilidad, 
                    :usuario_id, :vendedor_id, :local_id, :vendedor_nombre
                ) RETURNING id
            ");
            
            $stmt->execute([
                ':numero' => $data['numero'],
                ':cliente_nombre' => $data['cliente_nombre'],
                ':cliente_documento' => $data['cliente_documento'],
                ':cliente_telefono' => $data['cliente_telefono'] ?? null,
                ':cliente_direccion' => $data['cliente_direccion'],
                ':fecha' => $data['fecha'],
                ':fecha_validez' => $data['fecha_validez'],
                ':total' => $data['total'],
                ':observaciones' => $data['observaciones'],
                ':condiciones' => $data['condiciones'],
                ':estado' => $data['estado'] ?? 'Pendiente',
                ':gastos_logisticos' => $data['gastos_logisticos'] ?? 0,
                ':modificacion_orden_compra' => $data['modificacion_orden_compra'] ?? 0,
                ':movilidad' => $data['movilidad'] ?? 0,
                ':usuario_id' => $data['usuario_id'] ?? null,
                ':vendedor_id' => !empty($data['vendedor_id']) ? $data['vendedor_id'] : null,
                ':local_id' => !empty($data['local_id']) ? $data['local_id'] : null,
                ':vendedor_nombre' => $data['vendedor_nombre'] ?? null
            ]);

            $cotizacionId = $stmt->fetchColumn();

            $stmtDet = $this->db->prepare("
                INSERT INTO cotizacion_detalle (
                    cotizacion_id, producto_id, descripcion, cantidad, precio_unitario, subtotal, especificaciones, imagen, color
                ) VALUES (
                    :cotizacion_id, :producto_id, :descripcion, :cantidad, :precio_unitario, :subtotal, :especificaciones, :imagen, :color
                )
            ");

            foreach ($detalles as $det) {
                $stmtDet->execute([
                    ':cotizacion_id' => $cotizacionId,
                    ':producto_id' => $det['producto_id'] ?: null,
                    ':descripcion' => $det['descripcion'],
                    ':cantidad' => $det['cantidad'],
                    ':precio_unitario' => $det['precio_unitario'],
                    ':subtotal' => $det['subtotal'],
                    ':especificaciones' => $det['especificaciones'] ?? null,
                    ':imagen' => $det['imagen'] ?? null,
                    ':color' => $det['color'] ?? null
                ]);
            }

            $this->db->commit();
            return $cotizacionId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function update($id, $data, $detalles) {
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                UPDATE cotizaciones SET 
                    numero = :numero, 
                    cliente_nombre = :cliente_nombre, 
                    cliente_documento = :cliente_documento, 
                    cliente_telefono = :cliente_telefono, 
                    cliente_direccion = :cliente_direccion, 
                    fecha = :fecha, 
                    fecha_validez = :fecha_validez, 
                    total = :total, 
                    observaciones = :observaciones, 
                    condiciones = :condiciones, 
                    estado = :estado, 
                    gastos_logisticos = :gastos_logisticos, 
                    modificacion_orden_compra = :modificacion_orden_compra, 
                    movilidad = :movilidad,
                    vendedor_id = :vendedor_id,
                    local_id = :local_id,
                    vendedor_nombre = :vendedor_nombre
                WHERE id = :id
            ");
            
            $stmt->execute([
                ':id' => $id,
                ':numero' => $data['numero'],
                ':cliente_nombre' => $data['cliente_nombre'],
                ':cliente_documento' => $data['cliente_documento'],
                ':cliente_telefono' => $data['cliente_telefono'] ?? null,
                ':cliente_direccion' => $data['cliente_direccion'],
                ':fecha' => $data['fecha'],
                ':fecha_validez' => $data['fecha_validez'],
                ':total' => $data['total'],
                ':observaciones' => $data['observaciones'],
                ':condiciones' => $data['condiciones'],
                ':estado' => $data['estado'] ?? 'Pendiente',
                ':gastos_logisticos' => $data['gastos_logisticos'] ?? 0,
                ':modificacion_orden_compra' => $data['modificacion_orden_compra'] ?? 0,
                ':movilidad' => $data['movilidad'] ?? 0,
                ':vendedor_id' => !empty($data['vendedor_id']) ? $data['vendedor_id'] : null,
                ':local_id' => !empty($data['local_id']) ? $data['local_id'] : null,
                ':vendedor_nombre' => $data['vendedor_nombre'] ?? null
            ]);

            // Eliminar detalles antiguos
            $stmtDel = $this->db->prepare("DELETE FROM cotizacion_detalle WHERE cotizacion_id = :id");
            $stmtDel->execute([':id' => $id]);

            // Insertar nuevos detalles
            $stmtDet = $this->db->prepare("
                INSERT INTO cotizacion_detalle (
                    cotizacion_id, producto_id, descripcion, cantidad, precio_unitario, subtotal, especificaciones, imagen, color
                ) VALUES (
                    :cotizacion_id, :producto_id, :descripcion, :cantidad, :precio_unitario, :subtotal, :especificaciones, :imagen, :color
                )
            ");

            foreach ($detalles as $det) {
                $stmtDet->execute([
                    ':cotizacion_id' => $id,
                    ':producto_id' => $det['producto_id'] ?: null,
                    ':descripcion' => $det['descripcion'],
                    ':cantidad' => $det['cantidad'],
                    ':precio_unitario' => $det['precio_unitario'],
                    ':subtotal' => $det['subtotal'],
                    ':especificaciones' => $det['especificaciones'] ?? null,
                    ':imagen' => $det['imagen'] ?? null,
                    ':color' => $det['color'] ?? null
                ]);
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function delete($id) {
        $stmtDet = $this->db->prepare("DELETE FROM cotizacion_detalle WHERE cotizacion_id = :id");
        $stmtDet->execute([':id' => $id]);
        
        $stmt = $this->db->prepare("DELETE FROM cotizaciones WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}
