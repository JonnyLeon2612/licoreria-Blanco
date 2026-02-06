<?php
// modules/inventario/ajustar_stock.php
include '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. Recibir datos
    $id_producto = filter_input(INPUT_POST, 'id_producto', FILTER_VALIDATE_INT);
    $cantidad = filter_input(INPUT_POST, 'cantidad', FILTER_VALIDATE_INT);
    $tipo_ajuste = $_POST['tipo_ajuste'] ?? '';
    $referencia_usuario = trim($_POST['referencia']); // "Factura 123"
    $responsable = trim($_POST['responsable']);

    if (!$id_producto || $cantidad === false || $cantidad < 0) {
        $_SESSION['swal_error'] = "Error: Datos inválidos.";
        header("Location: index.php"); exit();
    }

    try {
        $pdo->beginTransaction();

        // 2. Bloquear y Consultar
        $stmt = $pdo->prepare("SELECT nombre_producto, stock_lleno FROM productos WHERE id_producto = ? FOR UPDATE");
        $stmt->execute([$id_producto]);
        $producto = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$producto) throw new Exception("Producto no encontrado.");

        $stock_actual = intval($producto['stock_lleno']);
        $nuevo_stock = $stock_actual;
        $cantidad_historial = $cantidad;
        $detalle_sistema = ""; 

        // 3. Calcular
        switch ($tipo_ajuste) {
            case 'ENTRADA':
                if ($cantidad == 0) throw new Exception("Cantidad debe ser mayor a 0.");
                $nuevo_stock = $stock_actual + $cantidad;
                $detalle_sistema = " (+Ingreso)";
                break;

            case 'SALIDA':
                if ($cantidad == 0) throw new Exception("Cantidad debe ser mayor a 0.");
                if ($cantidad > $stock_actual) throw new Exception("Stock insuficiente ($stock_actual).");
                $nuevo_stock = $stock_actual - $cantidad;
                $detalle_sistema = " (-Retiro)";
                break;

            case 'FISICO':
                if ($cantidad == $stock_actual) {
                    $pdo->rollBack();
                    $_SESSION['swal_warning'] = "El stock físico coincide con el sistema. Sin cambios.";
                    header("Location: index.php"); exit();
                }
                $nuevo_stock = $cantidad;
                $diferencia = $nuevo_stock - $stock_actual;
                $cantidad_historial = abs($diferencia);
                
                $detalle_sistema = ($diferencia > 0) 
                    ? " (Ajuste: Sobrante +$diferencia)" 
                    : " (Ajuste: Faltante $diferencia)";
                break;
        }

        // 4. Crear la Referencia Final para el Historial
        // Ej: "Factura #450 (+Ingreso)"
        $referencia_final = $referencia_usuario . $detalle_sistema;

        // 5. Actualizar Producto
        $pdo->prepare("UPDATE productos SET stock_lleno = ? WHERE id_producto = ?")
            ->execute([$nuevo_stock, $id_producto]);

        // 6. Guardar Historial
        $historial = $pdo->prepare("INSERT INTO movimientos_inventario 
            (id_producto, tipo_movimiento, cantidad, usuario, referencia, stock_anterior_lleno, stock_nuevo_lleno) 
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        $historial->execute([
            $id_producto,
            $tipo_ajuste,
            $cantidad_historial,
            $responsable,
            $referencia_final,
            $stock_actual,
            $nuevo_stock
        ]);

        $pdo->commit();
        $_SESSION['swal_success'] = "Movimiento registrado: <b>{$producto['nombre_producto']}</b>.<br>Stock: $stock_actual ➝ $nuevo_stock";

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['swal_error'] = $e->getMessage();
    }

    header("Location: index.php");
    exit();
}
?>