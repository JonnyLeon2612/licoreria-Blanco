<?php
// modules/cobranza/reporte_deudas.php
include '../../config/db.php';
date_default_timezone_set('America/Caracas');

// Consulta solo deudores reales
$sql = "SELECT c.nombre_cliente, c.telefono, c.tipo_cliente,
               (
                 (SELECT COALESCE(SUM(total_monto_usd), 0) FROM ventas WHERE id_cliente = c.id_cliente) - 
                 (SELECT COALESCE(SUM(monto_abonado_usd), 0) FROM abonos WHERE id_cliente = c.id_cliente)
               ) as saldo_dinero,
               (
                 (SELECT COALESCE(SUM(total_vacios_despachados), 0) FROM ventas WHERE id_cliente = c.id_cliente) - 
                 (SELECT COALESCE(SUM(vacios_devueltos), 0) FROM abonos WHERE id_cliente = c.id_cliente)
               ) as saldo_vacios,
               (SELECT MAX(fecha_venta) FROM ventas WHERE id_cliente = c.id_cliente) as ultima_compra
        FROM clientes c
        HAVING saldo_dinero > 0.01 OR saldo_vacios > 0
        ORDER BY saldo_dinero DESC";

$deudores = $pdo->query($sql)->fetchAll();
$total_deuda = 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Cuentas por Cobrar</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #333; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .empresa { font-size: 18px; font-weight: bold; }
        .fecha { font-size: 10px; color: #666; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background-color: #f2f2f2; border-bottom: 1px solid #999; padding: 8px; text-align: left; }
        td { border-bottom: 1px solid #ddd; padding: 8px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .total-row { font-weight: bold; background-color: #e6e6e6; }
        .danger { color: #cc0000; }
        
        /* Ocultar botón al imprimir */
        @media print {
            .no-print { display: none; }
        }
        .btn-print {
            padding: 10px 20px; background: #28a745; color: white; 
            border: none; cursor: pointer; border-radius: 5px; font-size: 14px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>

    <div class="no-print text-center">
        <button onclick="window.print()" class="btn-print">🖨️ Imprimir / Guardar como PDF</button>
    </div>

    <div class="header">
        <div class="empresa">ABASTO COMERCIAL BLANCO</div>
        <div>Reporte General de Morosidad y Cuentas por Cobrar</div>
        <div class="fecha">Generado el: <?php echo date('d/m/Y h:i A'); ?></div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Cliente</th>
                <th>Teléfono</th>
                <th>Tipo</th>
                <th class="text-center">Último Mov.</th>
                <th class="text-center">Deuda Vacíos</th>
                <th class="text-right">Deuda Total ($)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($deudores as $d): 
                $total_deuda += $d['saldo_dinero'];
                $fecha = $d['ultima_compra'] ? date('d/m/Y', strtotime($d['ultima_compra'])) : '-';
            ?>
            <tr>
                <td><strong><?php echo strtoupper($d['nombre_cliente']); ?></strong></td>
                <td><?php echo $d['telefono']; ?></td>
                <td><?php echo $d['tipo_cliente']; ?></td>
                <td class="text-center"><?php echo $fecha; ?></td>
                <td class="text-center">
                    <?php if($d['saldo_vacios'] > 0): ?>
                        <span style="color: orange; font-weight: bold;"><?php echo $d['saldo_vacios']; ?></span>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
                <td class="text-right danger">
                    $<?php echo number_format($d['saldo_dinero'], 2); ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="5" class="text-right">TOTAL POR COBRAR:</td>
                <td class="text-right">$<?php echo number_format($total_deuda, 2); ?></td>
            </tr>
        </tfoot>
    </table>

    <div style="margin-top: 30px; font-size: 10px; text-align: center; color: #999;">
        SIGIB Licorería Blanco - Fin del Reporte
    </div>

</body>
</html>