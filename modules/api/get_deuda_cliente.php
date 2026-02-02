<?php
// modules/api/get_deuda_cliente.php
include '../../config/db.php';

header('Content-Type: application/json');

$id_cliente = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_cliente <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID de cliente no válido']);
    exit();
}

try {
    $stmt = $pdo->prepare("
        SELECT saldo_dinero_usd, saldo_vacios 
        FROM cuentas_por_cobrar 
        WHERE id_cliente = ?
    ");
    $stmt->execute([$id_cliente]);
    $deuda = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($deuda) {
        echo json_encode([
            'success' => true,
            'deuda' => floatval($deuda['saldo_dinero_usd']),
            'vacios' => intval($deuda['saldo_vacios'])
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'deuda' => 0,
            'vacios' => 0
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>