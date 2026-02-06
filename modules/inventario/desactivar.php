<?php
// modules/inventario/desactivar.php
include '../../config/db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = intval($_POST['id']);

    try {
        // Obtenemos el nombre actual para no repetir "(DESCONTINUADO)" si ya lo tiene
        $stmt = $pdo->prepare("SELECT nombre_producto FROM productos WHERE id_producto = ?");
        $stmt->execute([$id]);
        $prod = $stmt->fetch();
        
        if (strpos($prod['nombre_producto'], '(DESCONTINUADO)') !== false) {
            echo json_encode(['success' => false, 'message' => 'Este producto ya está desactivado.']);
            exit();
        }

        // ACTUALIZAMOS: Ponemos Stock en 0 y cambiamos el nombre
        $sql = "UPDATE productos SET 
                stock_lleno = 0, 
                stock_minimo = 0,
                nombre_producto = CONCAT('(DESCONTINUADO) ', nombre_producto) 
                WHERE id_producto = ?";
        
        $pdo->prepare($sql)->execute([$id]);

        echo json_encode(['success' => true, 'message' => 'Producto desactivado. Ya no aparecerá con stock.']);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}
?>