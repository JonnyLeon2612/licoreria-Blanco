<?php
include '../../config/db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $id = intval($_POST['id']);

    try {

        $sql = "UPDATE productos 
                SET estado = 0 
                WHERE id_producto = ?";

        $pdo->prepare($sql)->execute([$id]);

        echo json_encode([
            'success' => true,
            'message' => 'Producto desactivado. Ya no aparecerá en el sistema.'
        ]);

    } catch (Exception $e) {

        echo json_encode([
            'success' => false,
            'message' => 'Error: '.$e->getMessage()
        ]);

    }
}
?>