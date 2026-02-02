<?php
// modules/cobranza/guardar_abono.php
session_start();
include '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_cliente = intval($_POST['id_cliente']);
    $monto_recibido = !empty($_POST['monto_abono']) ? floatval($_POST['monto_abono']) : 0;
    $vacios = !empty($_POST['vacios_devueltos']) ? intval($_POST['vacios_devueltos']) : 0;
    $metodo = $_POST['metodo'] ?? 'Efectivo';
    $referencia = $_POST['referencia'] ?? '';
    $estado_vacios = $_POST['estado_vacios'] ?? 'BUENO';
    $observaciones = $_POST['observaciones'] ?? '';

    try {
        $pdo->beginTransaction();

        // 1. REGISTRAR EL NUEVO ABONO
        // Guardamos el dinero que acaba de entrar
        $sqlHistorial = "INSERT INTO abonos (id_cliente, monto_abonado_usd, vacios_devueltos, metodo_pago, referencia, estado_vacios, observaciones) 
                         VALUES (:id, :monto, :vacios, :metodo, :ref, :estado, :obs)";
        $stmtHist = $pdo->prepare($sqlHistorial);
        $stmtHist->execute([
            ':id' => $id_cliente,
            ':monto' => $monto_recibido,
            ':vacios' => $vacios,
            ':metodo' => $metodo,
            ':ref' => $referencia,
            ':estado' => $estado_vacios,
            ':obs' => $observaciones
        ]);

        // =================================================================================
        // 2. CORRECCIÓN: BARRIDO HISTÓRICO (La Solución Definitiva)
        // =================================================================================
        
        // PASO A: Sumamos CUÁNTO DINERO ha pagado este cliente en toda su historia (incluyendo lo de hoy)
        $stmtTotalPagado = $pdo->prepare("SELECT COALESCE(SUM(monto_abonado_usd), 0) FROM abonos WHERE id_cliente = ?");
        $stmtTotalPagado->execute([$id_cliente]);
        $bolsa_de_dinero = floatval($stmtTotalPagado->fetchColumn());

        // PASO B: Traemos TODAS las ventas del cliente, desde la más vieja a la más nueva
        $stmtVentas = $pdo->prepare("SELECT id_venta, total_monto_usd FROM ventas WHERE id_cliente = ? ORDER BY fecha_venta ASC");
        $stmtVentas->execute([$id_cliente]);
        $todas_las_ventas = $stmtVentas->fetchAll();

        // PASO C: Repartimos la "bolsa de dinero" factura por factura
        foreach ($todas_las_ventas as $v) {
            $monto_factura = floatval($v['total_monto_usd']);
            
            // Tolerancia de 0.01 para evitar errores de decimales
            if ($bolsa_de_dinero >= ($monto_factura - 0.01)) {
                // Si la bolsa cubre esta factura completa:
                $nuevo_estado = 'Pagado';
                $bolsa_de_dinero -= $monto_factura; // Restamos lo que costó esta factura y seguimos a la siguiente
            } elseif ($bolsa_de_dinero > 0.01) {
                // Si queda algo de dinero pero NO alcanza para toda la factura:
                $nuevo_estado = 'Abonado';
                $bolsa_de_dinero = 0; // Se acabó el dinero
            } else {
                // Si la bolsa ya está vacía:
                $nuevo_estado = 'Pendiente'; // Es deuda total
            }

            // Actualizamos el estado real en la base de datos
            $pdo->prepare("UPDATE ventas SET estado_pago = ? WHERE id_venta = ?")
                ->execute([$nuevo_estado, $v['id_venta']]);
        }

        // =================================================================================

        // 3. ACTUALIZAR TABLA DE SALDOS (Por mantenimiento)
        $pdo->prepare("UPDATE cuentas_por_cobrar 
                       SET saldo_dinero_usd = GREATEST(0, saldo_dinero_usd - :monto), 
                           saldo_vacios = GREATEST(0, saldo_vacios - :vacios), 
                           ultima_actualizacion = NOW() 
                       WHERE id_cliente = :id")
            ->execute([
                ':monto' => $monto_recibido, 
                ':vacios' => $vacios, 
                ':id' => $id_cliente
            ]);

        $pdo->commit();

        $_SESSION['swal_success'] = "Pago registrado. Las cuentas se han recalculado correctamente.";
        header("Location: index.php");
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['swal_error'] = "Error: " . $e->getMessage();
        header("Location: index.php");
        exit();
    }
}
?>