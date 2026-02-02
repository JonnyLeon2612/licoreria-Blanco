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
    // --- CORRECCIÓN: CÁLCULO MATEMÁTICO EN TIEMPO REAL ---
    // En lugar de leer el saldo estático, calculamos: (Todo lo que debe) - (Todo lo que ha pagado)
    $stmt = $pdo->prepare("
        SELECT 
            (
              (SELECT COALESCE(SUM(total_monto_usd), 0) FROM ventas WHERE id_cliente = :id) - 
              (SELECT COALESCE(SUM(monto_abonado_usd), 0) FROM abonos WHERE id_cliente = :id)
            ) as saldo_dinero_usd,
            
            (
              (SELECT COALESCE(SUM(total_vacios_despachados), 0) FROM ventas WHERE id_cliente = :id) - 
              (SELECT COALESCE(SUM(vacios_devueltos), 0) FROM abonos WHERE id_cliente = :id)
            ) as saldo_vacios
    ");
    
    $stmt->execute([':id' => $id_cliente]);
    $deuda = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($deuda) {
        echo json_encode([
            'success' => true,
            'deuda' => floatval($deuda['saldo_dinero_usd']), // Ahora enviará el monto real calculado
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