<?php
// modules/cobranza/guardar_abono.php
include '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_cliente = intval($_POST['id_cliente']);
    $monto = !empty($_POST['monto_abono']) ? floatval($_POST['monto_abono']) : 0;
    $vacios = !empty($_POST['vacios_devueltos']) ? intval($_POST['vacios_devueltos']) : 0;
    $metodo = $_POST['metodo'] ?? 'Efectivo';
    $referencia = $_POST['referencia'] ?? '';
    $estado_vacios = $_POST['estado_vacios'] ?? 'BUENO';
    $observaciones = $_POST['observaciones'] ?? '';

    try {
        $pdo->beginTransaction();

        // 1. Verificar deuda actual
        $stmt = $pdo->prepare("SELECT saldo_dinero_usd, saldo_vacios FROM cuentas_por_cobrar WHERE id_cliente = ?");
        $stmt->execute([$id_cliente]);
        $deuda_actual = $stmt->fetch();
        
        if (!$deuda_actual) {
            throw new Exception("Cliente no encontrado en cuentas por cobrar");
        }

        // 2. Validar montos (no permitir pagos mayores a la deuda sin confirmación)
        $deuda_dinero = floatval($deuda_actual['saldo_dinero_usd']);
        $deuda_vacios = intval($deuda_actual['saldo_vacios']);
        
        // Ajustar montos si exceden la deuda
        $monto_ajustado = min($monto, $deuda_dinero);
        $vacios_ajustados = min($vacios, $deuda_vacios);
        
        if ($monto > $deuda_dinero) {
            $_SESSION['warning'] = "El monto pagado ($" . number_format($monto, 2) . ") excede la deuda actual ($" . number_format($deuda_dinero, 2) . "). Se registrará solo $" . number_format($monto_ajustado, 2);
        }
        
        if ($vacios > $deuda_vacios) {
            $_SESSION['warning'] = ($_SESSION['warning'] ?? '') . " Los vacíos devueltos (" . $vacios . ") exceden los pendientes (" . $deuda_vacios . "). Se registrarán solo " . $vacios_ajustados;
        }

        // 3. Registrar en Historial de Abonos
        $sqlHistorial = "INSERT INTO abonos (id_cliente, monto_abonado_usd, vacios_devueltos, metodo_pago, referencia, estado_vacios, observaciones) 
                         VALUES (:id, :monto, :vacios, :metodo, :ref, :estado, :obs)";
        $stmtHist = $pdo->prepare($sqlHistorial);
        $stmtHist->execute([
            ':id' => $id_cliente,
            ':monto' => $monto_ajustado,
            ':vacios' => $vacios_ajustados,
            ':metodo' => $metodo,
            ':ref' => $referencia,
            ':estado' => $estado_vacios,
            ':obs' => $observaciones
        ]);

        $id_abono = $pdo->lastInsertId();

        // 4. Actualizar vacíos en inventario si se devolvieron
        if ($vacios_ajustados > 0) {
            // Obtener productos retornables
            $productos_retornables = $pdo->query("SELECT id_producto FROM productos WHERE es_retornable = 1")->fetchAll();
            
            if (count($productos_retornables) > 0) {
                // Distribuir vacíos proporcionalmente entre productos retornables
                $vacios_por_producto = floor($vacios_ajustados / count($productos_retornables));
                $vacios_restantes = $vacios_ajustados % count($productos_retornables);
                
                foreach ($productos_retornables as $index => $producto) {
                    $cantidad = $vacios_por_producto + ($index < $vacios_restantes ? 1 : 0);
                    
                    if ($cantidad > 0) {
                        $sqlUpdateVaciar = "UPDATE productos SET stock_vacio = stock_vacio + ? WHERE id_producto = ?";
                        $stmtUpdate = $pdo->prepare($sqlUpdateVaciar);
                        $stmtUpdate->execute([$cantidad, $producto['id_producto']]);
                    }
                }
            }
        }

        // 5. Restar de la Deuda Actual
        $sqlUpdate = "UPDATE cuentas_por_cobrar 
                      SET saldo_dinero_usd = GREATEST(0, saldo_dinero_usd - :monto),
                          saldo_vacios = GREATEST(0, saldo_vacios - :vacios),
                          ultima_actualizacion = NOW()
                      WHERE id_cliente = :id";
        
        $stmtUpd = $pdo->prepare($sqlUpdate);
        $stmtUpd->execute([
            ':monto' => $monto_ajustado,
            ':vacios' => $vacios_ajustados,
            ':id' => $id_cliente
        ]);

        // 6. Actualizar estado de ventas si quedaron pagadas
        if ($monto_ajustado > 0) {
            // Obtener ventas pendientes del cliente
            $ventas_pendientes = $pdo->prepare("
                SELECT id_venta, total_monto_usd, 
                       (SELECT COALESCE(SUM(monto_abonado_usd), 0) FROM abonos WHERE id_venta = ventas.id_venta) as total_abonado
                FROM ventas 
                WHERE id_cliente = ? AND estado_pago != 'Pagado'
                ORDER BY fecha_venta ASC
            ");
            $ventas_pendientes->execute([$id_cliente]);
            $ventas = $ventas_pendientes->fetchAll();
            
            $monto_disponible = $monto_ajustado;
            
            foreach ($ventas as $venta) {
                $saldo_pendiente = $venta['total_monto_usd'] - $venta['total_abonado'];
                
                if ($monto_disponible >= $saldo_pendiente && $saldo_pendiente > 0) {
                    // Marcar venta como pagada
                    $sqlUpdateVenta = "UPDATE ventas SET estado_pago = 'Pagado' WHERE id_venta = ?";
                    $stmtVenta = $pdo->prepare($sqlUpdateVenta);
                    $stmtVenta->execute([$venta['id_venta']]);
                    
                    $monto_disponible -= $saldo_pendiente;
                }
            }
        }

        $pdo->commit();

        // 7. Obtener información del cliente para el mensaje
        $stmtCliente = $pdo->prepare("SELECT nombre_cliente FROM clientes WHERE id_cliente = ?");
        $stmtCliente->execute([$id_cliente]);
        $cliente = $stmtCliente->fetch();
        
        $_SESSION['success'] = "Pago registrado exitosamente para " . $cliente['nombre_cliente'] . 
                             ". Abono #" . str_pad($id_abono, 5, '0', STR_PAD_LEFT);
        
        if ($monto_ajustado < $monto || $vacios_ajustados < $vacios) {
            $_SESSION['warning'] = ($_SESSION['warning'] ?? '') . 
                                 " Se ajustaron los valores para no exceder la deuda actual.";
        }

        header("Location: index.php");
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error al registrar el pago: " . $e->getMessage();
        header("Location: index.php");
        exit();
    }
}
?>