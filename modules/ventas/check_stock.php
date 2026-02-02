<?php
// modules/ventas/check_stock.php
include '../../config/db.php';

header('Content-Type: application/json');

$id_producto = isset($_GET['id']) ? intval($_GET['id']) : 0;
$cantidad = isset($_GET['cantidad']) ? intval($_GET['cantidad']) : 0;
$excluir = isset($_GET['excluir']) ? intval($_GET['excluir']) : 0;

if ($id_producto <= 0 || $cantidad <= 0) {
    echo json_encode(['disponible' => false, 'stock' => 0]);
    exit();
}

try {
    // Obtener stock actual
    $stmt = $pdo->prepare("SELECT stock_lleno FROM productos WHERE id_producto = ?");
    $stmt->execute([$id_producto]);
    $producto = $stmt->fetch();
    
    if (!$producto) {
        echo json_encode(['disponible' => false, 'stock' => 0]);
        exit();
    }
    
    $stock_disponible = $producto['stock_lleno'];
    
    // Si hay una cantidad a excluir (porque ya está en el carrito)
    if ($excluir > 0) {
        $stock_disponible += $excluir;
    }
    
    $disponible = ($stock_disponible >= $cantidad);
    
    echo json_encode([
        'disponible' => $disponible,
        'stock' => $stock_disponible,
        'solicitado' => $cantidad,
        'faltante' => max(0, $cantidad - $stock_disponible)
    ]);
    
} catch (Exception $e) {
    echo json_encode(['disponible' => false, 'error' => $e->getMessage()]);
}
?>