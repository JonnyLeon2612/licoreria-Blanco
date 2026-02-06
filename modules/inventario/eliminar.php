<?php
// modules/inventario/eliminar.php
include '../../config/db.php';
header('Content-Type: application/json'); // Importante para que JS lo entienda

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = intval($_POST['id']);

    try {
        // 1. REVISIÓN DE SEGURIDAD (No borrar si tiene historial)
        $checkVentas = $pdo->prepare("SELECT COUNT(*) FROM detalle_ventas WHERE id_producto = ?");
        $checkVentas->execute([$id]);
        $ventas = $checkVentas->fetchColumn();

        $checkMovs = $pdo->prepare("SELECT COUNT(*) FROM movimientos_inventario WHERE id_producto = ?");
        $checkMovs->execute([$id]);
        $movimientos = $checkMovs->fetchColumn();

        // 2. DECISIÓN
        if ($ventas > 0 || $movimientos > 0) {
            // ERROR: Tiene historial
            echo json_encode([
                'success' => false, 
                'title' => '¡No se puede eliminar!',
                'message' => "Este producto tiene historial ($ventas ventas registradas).\n\nMejor edítalo y cámbiale el nombre a '(INACTIVO)'."
            ]);
        } else {
            // ÉXITO: Está limpio, se borra
            $stmt = $pdo->prepare("DELETE FROM productos WHERE id_producto = ?");
            $stmt->execute([$id]);

            echo json_encode([
                'success' => true, 
                'title' => '¡Eliminado!',
                'message' => 'El producto ha sido borrado correctamente.'
            ]);
        }

    } catch (Exception $e) {
        echo json_encode([
            'success' => false, 
            'title' => 'Error de Sistema',
            'message' => $e->getMessage()
        ]);
    }
}
?>