<?php
// modules/inventario/guardar_edicion.php
include '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = intval($_POST['id_producto']);
    $nombre = trim($_POST['nombre']);
    $precio = floatval($_POST['precio']);
    $costo = floatval($_POST['costo']);
    $retornable = intval($_POST['retornable']);
    $desc = trim($_POST['descripcion']);
    $min = intval($_POST['minimo']);
    $max = intval($_POST['maximo']);

    try {
        $sql = "UPDATE productos SET 
                nombre_producto = ?, 
                precio_venta_usd = ?, 
                costo_usd = ?, 
                es_retornable = ?, 
                descripcion = ?, 
                stock_minimo = ?, 
                stock_maximo = ? 
                WHERE id_producto = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nombre, $precio, $costo, $retornable, $desc, $min, $max, $id]);

        $_SESSION['swal_success'] = "Producto actualizado correctamente.";
        
    } catch (Exception $e) {
        $_SESSION['swal_error'] = "Error al actualizar: " . $e->getMessage();
    }

    header("Location: index.php");
    exit();
}
?>