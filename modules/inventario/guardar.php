<?php
// modules/inventario/guardar.php
include '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion'] ?? '');
    $precio = floatval($_POST['precio']);
    $costo = !empty($_POST['costo']) ? floatval($_POST['costo']) : null;
    $retornable = intval($_POST['retornable']);
    $stock_lleno = intval($_POST['stock_lleno']);
    $stock_vacio = intval($_POST['stock_vacio']);
    $stock_minimo = intval($_POST['stock_minimo'] ?? 10);
    $stock_maximo = intval($_POST['stock_maximo'] ?? 100);

    try {
        // Validar que no exista producto con el mismo nombre
        $stmt = $pdo->prepare("SELECT id_producto FROM productos WHERE nombre_producto = ?");
        $stmt->execute([$nombre]);
        
        if ($stmt->rowCount() > 0) {
            $_SESSION['error'] = "Ya existe un producto con el nombre '$nombre'";
            header("Location: index.php");
            exit();
        }

        // Insertar producto
        $sql = "INSERT INTO productos (nombre_producto, descripcion, precio_venta_usd, costo_usd, 
                                       es_retornable, stock_lleno, stock_vacio, stock_minimo, stock_maximo) 
                VALUES (:nombre, :descripcion, :precio, :costo, :retornable, :lleno, :vacio, :minimo, :maximo)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':nombre' => $nombre,
            ':descripcion' => $descripcion,
            ':precio' => $precio,
            ':costo' => $costo,
            ':retornable' => $retornable,
            ':lleno'  => $stock_lleno,
            ':vacio'  => $stock_vacio,
            ':minimo' => $stock_minimo,
            ':maximo' => $stock_maximo
        ]);

        $id_producto = $pdo->lastInsertId();

        // Registrar movimiento en historial
        if ($stock_lleno > 0 || $stock_vacio > 0) {
            $sqlMovimiento = "INSERT INTO movimientos_inventario (id_producto, tipo_movimiento, cantidad, usuario, referencia) 
                              VALUES (:id, 'ENTRADA', :cantidad, :usuario, :referencia)";
            
            $stmtMovimiento = $pdo->prepare($sqlMovimiento);
            $stmtMovimiento->execute([
                ':id' => $id_producto,
                ':cantidad' => $stock_lleno + $stock_vacio,
                ':usuario' => $_SESSION['usuario'] ?? 'Sistema',
                ':referencia' => 'Creación de producto'
            ]);
        }

        $_SESSION['success'] = "Producto '$nombre' registrado exitosamente. ID: #" . str_pad($id_producto, 4, '0', STR_PAD_LEFT);
        
        header("Location: index.php");
        exit();

    } catch (PDOException $e) {
        $_SESSION['error'] = "Error al guardar producto: " . $e->getMessage();
        header("Location: index.php");
        exit();
    }
}
?>