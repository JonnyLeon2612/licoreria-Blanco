<?php
// modules/inventario/ajustar_stock.php
include '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_producto = intval($_POST['id_producto']);
    $tipo_ajuste = $_POST['tipo_ajuste'];
    $motivo = trim($_POST['motivo']);
    $responsable = trim($_POST['responsable']);
    
    try {
        $pdo->beginTransaction();
        
        // Obtener información actual del producto
        $stmt = $pdo->prepare("SELECT nombre_producto, stock_lleno, stock_vacio FROM productos WHERE id_producto = ?");
        $stmt->execute([$id_producto]);
        $producto = $stmt->fetch();
        
        if (!$producto) {
            throw new Exception("Producto no encontrado");
        }
        
        $stock_lleno_actual = intval($producto['stock_lleno']);
        $stock_vacio_actual = intval($producto['stock_vacio']);
        $cantidad_total = 0;
        $nuevo_stock_lleno = $stock_lleno_actual;
        $nuevo_stock_vacio = $stock_vacio_actual;
        
        // Procesar según tipo de ajuste
        switch ($tipo_ajuste) {
            case 'ENTRADA':
                $cantidad_lleno_entrada = intval($_POST['cantidad_lleno_entrada'] ?? 0);
                $cantidad_vacio_entrada = intval($_POST['cantidad_vacio_entrada'] ?? 0);
                
                $nuevo_stock_lleno += $cantidad_lleno_entrada;
                $nuevo_stock_vacio += $cantidad_vacio_entrada;
                $cantidad_total = $cantidad_lleno_entrada + $cantidad_vacio_entrada;
                break;
                
            case 'SALIDA':
                $cantidad_lleno_salida = intval($_POST['cantidad_lleno_salida'] ?? 0);
                $cantidad_vacio_salida = intval($_POST['cantidad_vacio_salida'] ?? 0);
                
                // Validar que haya suficiente stock
                if ($cantidad_lleno_salida > $stock_lleno_actual) {
                    throw new Exception("No hay suficiente stock lleno. Disponible: $stock_lleno_actual, Solicitado: $cantidad_lleno_salida");
                }
                
                if ($cantidad_vacio_salida > $stock_vacio_actual) {
                    throw new Exception("No hay suficiente stock vacío. Disponible: $stock_vacio_actual, Solicitado: $cantidad_vacio_salida");
                }
                
                $nuevo_stock_lleno -= $cantidad_lleno_salida;
                $nuevo_stock_vacio -= $cantidad_vacio_salida;
                $cantidad_total = $cantidad_lleno_salida + $cantidad_vacio_salida;
                break;
                
            case 'TRANSFERENCIA':
                $cantidad_transferir = intval($_POST['cantidad_lleno_a_vacio'] ?? 0);
                
                if ($cantidad_transferir > $stock_lleno_actual) {
                    throw new Exception("No hay suficiente stock lleno para transferir. Disponible: $stock_lleno_actual, Solicitado: $cantidad_transferir");
                }
                
                $nuevo_stock_lleno -= $cantidad_transferir;
                $nuevo_stock_vacio += $cantidad_transferir;
                $cantidad_total = $cantidad_transferir;
                break;
                
            case 'DANADO':
                $cantidad_danado = intval($_POST['cantidad_lleno_salida'] ?? 0);
                
                if ($cantidad_danado > $stock_lleno_actual) {
                    throw new Exception("No hay suficiente stock lleno para marcar como dañado. Disponible: $stock_lleno_actual, Solicitado: $cantidad_danado");
                }
                
                $nuevo_stock_lleno -= $cantidad_danado;
                $cantidad_total = $cantidad_danado;
                break;
                
            case 'FISICO':
                $nuevo_stock_lleno = intval($_POST['cantidad_lleno_salida'] ?? $stock_lleno_actual);
                $nuevo_stock_vacio = intval($_POST['cantidad_vacio_salida'] ?? $stock_vacio_actual);
                $cantidad_total = abs($nuevo_stock_lleno - $stock_lleno_actual) + abs($nuevo_stock_vacio - $stock_vacio_actual);
                break;
        }
        
        // Validar que no queden valores negativos
        if ($nuevo_stock_lleno < 0 || $nuevo_stock_vacio < 0) {
            throw new Exception("No se permiten valores negativos en el stock");
        }
        
        // Actualizar stock del producto
        $sqlUpdate = "UPDATE productos SET stock_lleno = :lleno, stock_vacio = :vacio WHERE id_producto = :id";
        $stmtUpdate = $pdo->prepare($sqlUpdate);
        $stmtUpdate->execute([
            ':lleno' => $nuevo_stock_lleno,
            ':vacio' => $nuevo_stock_vacio,
            ':id' => $id_producto
        ]);
        
        // Registrar movimiento en historial
        $sqlMovimiento = "INSERT INTO movimientos_inventario (id_producto, tipo_movimiento, cantidad, usuario, referencia, stock_anterior_lleno, stock_anterior_vacio, stock_nuevo_lleno, stock_nuevo_vacio) 
                          VALUES (:id, :tipo, :cantidad, :usuario, :referencia, :anterior_lleno, :anterior_vacio, :nuevo_lleno, :nuevo_vacio)";
        
        $stmtMovimiento = $pdo->prepare($sqlMovimiento);
        $stmtMovimiento->execute([
            ':id' => $id_producto,
            ':tipo' => $tipo_ajuste,
            ':cantidad' => $cantidad_total,
            ':usuario' => $responsable,
            ':referencia' => $motivo,
            ':anterior_lleno' => $stock_lleno_actual,
            ':anterior_vacio' => $stock_vacio_actual,
            ':nuevo_lleno' => $nuevo_stock_lleno,
            ':nuevo_vacio' => $nuevo_stock_vacio
        ]);
        
        $pdo->commit();
        
        $_SESSION['success'] = "Stock ajustado exitosamente para '{$producto['nombre_producto']}'. " .
                              "Stock lleno: $stock_lleno_actual → $nuevo_stock_lleno. " .
                              "Stock vacío: $stock_vacio_actual → $nuevo_stock_vacio.";
        
        header("Location: index.php");
        exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error al ajustar stock: " . $e->getMessage();
        header("Location: index.php");
        exit();
    }
}
?>