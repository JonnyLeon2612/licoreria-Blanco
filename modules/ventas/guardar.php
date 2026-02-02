<?php
// modules/ventas/guardar.php
include '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    $id_cliente = intval($_POST['id_cliente']);
    $total_venta = floatval($_POST['total_venta']);
    $monto_pagado = floatval($_POST['monto_pagado']);
    
    $total_vacios_esperados = intval($_POST['total_vacios_esperados']);
    $vacios_recibidos = intval($_POST['vacios_recibidos']);
    
    // Decodificar el JSON de productos
    $productos = json_decode($_POST['detalle_productos'], true);

    if (empty($productos)) {
        $_SESSION['error'] = "Error: No hay productos en la venta.";
        header("Location: index.php");
        exit();
    }

    try {
        $pdo->beginTransaction();

        // 1. Validar stock antes de procesar
        foreach ($productos as $item) {
            $id_producto = intval($item['id']);
            $cantidad = intval($item['cantidad']);
            
            $stmt = $pdo->prepare("SELECT stock_lleno, nombre_producto FROM productos WHERE id_producto = ?");
            $stmt->execute([$id_producto]);
            $producto = $stmt->fetch();
            
            if (!$producto) {
                throw new Exception("Producto no encontrado: ID {$id_producto}");
            }
            
            if ($producto['stock_lleno'] < $cantidad) {
                throw new Exception("Stock insuficiente para {$producto['nombre_producto']}. Disponible: {$producto['stock_lleno']}, Solicitado: {$cantidad}");
            }
        }

        // 2. Calcular Deudas
        $deuda_dinero = 0;
        $deuda_vacios = 0;
        $estado = 'Pagado';

        // Si pagó menos del total -> Deuda Dinero
        if ($monto_pagado < $total_venta) {
            $deuda_dinero = $total_venta - $monto_pagado;
            $estado = 'Pendiente';
        }

        // Si entregó menos vacíos de los que se llevó -> Deuda Vacíos
        if ($vacios_recibidos < $total_vacios_esperados) {
            $deuda_vacios = $total_vacios_esperados - $vacios_recibidos;
            if ($estado == 'Pagado') {
                $estado = 'Abonado';
            }
        }

        // 3. Validar límite de crédito si es cliente mayorista
        $stmt = $pdo->prepare("
            SELECT c.tipo_cliente, cc.limite_credito, cc.saldo_dinero_usd 
            FROM clientes c 
            JOIN cuentas_por_cobrar cc ON c.id_cliente = cc.id_cliente 
            WHERE c.id_cliente = ?
        ");
        $stmt->execute([$id_cliente]);
        $cliente_info = $stmt->fetch();
        
        if ($cliente_info && $cliente_info['tipo_cliente'] == 'Mayorista') {
            $limite_credito = floatval($cliente_info['limite_credito']);
            $deuda_actual = floatval($cliente_info['saldo_dinero_usd']);
            
            if (($deuda_actual + $deuda_dinero) > $limite_credito && $limite_credito > 0) {
                throw new Exception("El cliente excede su límite de crédito. Límite: $" . number_format($limite_credito, 2) . 
                                  ", Deuda actual: $" . number_format($deuda_actual, 2) . 
                                  ", Nueva deuda: $" . number_format($deuda_dinero, 2));
            }
        }

        // 4. Insertar Venta
        $sqlVenta = "INSERT INTO ventas (id_cliente, total_monto_usd, total_vacios_despachados, estado_pago) 
                     VALUES (:cliente, :total, :vacios, :estado)";
        $stmt = $pdo->prepare($sqlVenta);
        $stmt->execute([
            ':cliente' => $id_cliente,
            ':total' => $total_venta,
            ':vacios' => $total_vacios_esperados,
            ':estado' => $estado
        ]);
        
        $id_venta = $pdo->lastInsertId();

        // 5. Insertar Detalle y Actualizar Stock
        $sqlDetalle = "INSERT INTO detalle_ventas (id_venta, id_producto, cantidad, precio_unitario) VALUES (?, ?, ?, ?)";
        $sqlStock = "UPDATE productos SET stock_lleno = stock_lleno - ? WHERE id_producto = ?";
        
        $stmtDetalle = $pdo->prepare($sqlDetalle);
        $stmtStock = $pdo->prepare($sqlStock);

        foreach ($productos as $item) {
            // Guardar detalle
            $stmtDetalle->execute([
                $id_venta, 
                intval($item['id']), 
                intval($item['cantidad']), 
                floatval($item['precio'])
            ]);
            
            // Restar inventario
            $stmtStock->execute([
                intval($item['cantidad']), 
                intval($item['id'])
            ]);
            
            // Si el producto es retornable, sumar vacíos recibidos al stock de vacíos
            if ($item['esRetornable'] && $vacios_recibidos > 0) {
                // Distribuir vacíos recibidos proporcionalmente entre productos retornables
                $proporcion = $item['cantidad'] / $total_vacios_esperados;
                $vacios_para_este_producto = floor($vacios_recibidos * $proporcion);
                
                if ($vacios_para_este_producto > 0) {
                    $sqlVaciar = "UPDATE productos SET stock_vacio = stock_vacio + ? WHERE id_producto = ?";
                    $stmtVaciar = $pdo->prepare($sqlVaciar);
                    $stmtVaciar->execute([$vacios_para_este_producto, intval($item['id'])]);
                }
            }
        }

        // 6. ACTUALIZAR CUENTAS POR COBRAR
        $sqlDeuda = "UPDATE cuentas_por_cobrar 
                     SET saldo_dinero_usd = saldo_dinero_usd + :dinero, 
                         saldo_vacios = saldo_vacios + :vacios,
                         ultima_actualizacion = NOW()
                     WHERE id_cliente = :cliente";
        
        $stmtDeuda = $pdo->prepare($sqlDeuda);
        $stmtDeuda->execute([
            ':dinero' => $deuda_dinero,
            ':vacios' => $deuda_vacios,
            ':cliente' => $id_cliente
        ]);

        // 7. Registrar abono si pagó algo
        if ($monto_pagado > 0) {
            $sqlAbono = "INSERT INTO abonos (id_cliente, id_venta, monto_abonado_usd, vacios_devueltos, metodo_pago) 
                         VALUES (:cliente, :venta, :monto, :vacios, :metodo)";
            $stmtAbono = $pdo->prepare($sqlAbono);
            $stmtAbono->execute([
                ':cliente' => $id_cliente,
                ':venta' => $id_venta,
                ':monto' => $monto_pagado,
                ':vacios' => $vacios_recibidos,
                ':metodo' => 'EFECTIVO' // Por defecto
            ]);
        }

        $pdo->commit();
        
        // Guardar información para mostrar en comprobante
        $_SESSION['venta_procesada'] = [
            'id_venta' => $id_venta,
            'id_cliente' => $id_cliente,
            'total' => $total_venta,
            'pagado' => $monto_pagado,
            'deuda' => $deuda_dinero,
            'vacios_pendientes' => $deuda_vacios
        ];
        
        // Redirigir al comprobante
        header("Location: comprobante.php?id=" . $id_venta);
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error en la transacción: " . $e->getMessage();
        header("Location: index.php");
        exit();
    }
}
?>