<?php
// modules/ventas/comprobante.php
include '../../config/db.php';

$id_venta = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_venta <= 0) {
    $_SESSION['error'] = "Venta no especificada";
    header("Location: index.php");
    exit();
}

// 1. OBTENEMOS LOS DATOS (Agregué c.direccion a la consulta)
$venta = $pdo->prepare("
    SELECT v.*, c.nombre_cliente, c.rif_cedula, c.telefono, c.direccion,
           cc.saldo_dinero_usd, cc.saldo_vacios
    FROM ventas v
    JOIN clientes c ON v.id_cliente = c.id_cliente
    JOIN cuentas_por_cobrar cc ON v.id_cliente = cc.id_cliente
    WHERE v.id_venta = ?
");
$venta->execute([$id_venta]);
$venta = $venta->fetch(PDO::FETCH_ASSOC);

if (!$venta) {
    $_SESSION['error'] = "Venta no encontrada";
    header("Location: index.php");
    exit();
}

$detalle = $pdo->prepare("
    SELECT dv.*, p.nombre_producto 
    FROM detalle_ventas dv
    JOIN productos p ON dv.id_producto = p.id_producto
    WHERE dv.id_venta = ?
");
$detalle->execute([$id_venta]);
$detalles = $detalle->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket #<?php echo str_pad($id_venta, 5, '0', STR_PAD_LEFT); ?></title>
    <style>
        /* --- CONFIGURACIÓN PARA IMPRESORA TÉRMICA --- */
        @page {
            size: 80mm auto; 
            margin: 0;
        }

        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            line-height: 1.2;
            width: 74mm; /* Ancho seguro */
            margin: 0 auto;
            padding: 3mm;
            background-color: #fff;
            color: #000;
        }

        .text-center { text-align: center; }
        .text-end { text-align: right; }
        .fw-bold { font-weight: bold; }
        .d-flex { display: flex; justify-content: space-between; }
        
        .divider {
            border-top: 1px dashed #000;
            margin: 8px 0;
            width: 100%;
        }

        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; border-bottom: 1px dashed #000; font-size: 11px; }
        td { padding: 4px 0; vertical-align: top; }
        
        .logo { 
            max-width: 40mm; 
            height: auto; 
            margin-bottom: 5px; 
            filter: grayscale(100%);
        }

        /* --- BOTONES (NO IMPRIMIR) --- */
        .no-print {
            padding: 15px;
            text-align: center;
            background: #f0f0f0;
            border-bottom: 1px solid #ccc;
            margin-bottom: 10px;
        }
        
        .btn {
            background: #333;
            color: #fff;
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 4px;
            font-size: 14px;
            display: inline-block;
            margin: 0 5px;
            border: none;
            cursor: pointer;
        }
        .btn-green { background: #25D366; }
        .btn-blue { background: #007bff; }

        @media print {
            .no-print { display: none !important; }
            body { padding: 0; width: 100%; }
        }
    </style>
</head>
<body>

    <div class="no-print">
        <button onclick="window.print()" class="btn btn-blue">🖨️ Imprimir</button>
        <a href="javascript:history.back()" class="btn">🔙 Volver</a>
        
        <?php 
        $mensaje = "Hola *" . $venta['nombre_cliente'] . "*,\nAdjunto comprobante de pago #".str_pad($id_venta, 5, '0', STR_PAD_LEFT)."\nTotal: $" . number_format($venta['total_monto_usd'], 2);
        ?>
        <a href="https://wa.me/<?php echo $venta['telefono']; ?>?text=<?php echo urlencode($mensaje); ?>" target="_blank" class="btn btn-green">📱 WhatsApp</a>
    </div>

    <div class="ticket-content">
        
        <div class="text-center">
            <?php if(file_exists('../../assets/img/logo.png')): ?>
                <img src="../../assets/img/logo.png" class="logo" alt="LOGO"><br>
            <?php endif; ?>
            
            <span class="fw-bold" style="font-size: 14px;">ABASTO COMERCIAL BLANCO</span><br>
            <span style="font-size: 11px;">RIF: J-12345678-9</span><br>
            <span style="font-size: 11px;">Urb. Michelena, Local A5</span><br>
            <span style="font-size: 11px;">Valencia - Carabobo</span>
        </div>

        <div class="divider"></div>

        <div style="font-size: 11px;">
            <div class="d-flex">
                <span>Fecha: <?php echo date('d/m/Y', strtotime($venta['fecha_venta'])); ?></span>
                <span>Hora: <?php echo date('H:i', strtotime($venta['fecha_venta'])); ?></span>
            </div>
            <div>Ticket #: <strong><?php echo str_pad($id_venta, 5, '0', STR_PAD_LEFT); ?></strong></div>
            <div>Cliente: <?php echo strtoupper(substr($venta['nombre_cliente'], 0, 20)); ?></div>
            <div>Dir: <?php echo strtoupper(substr($venta['direccion'] ?? 'NO REGISTRADA', 0, 25)); ?></div>
        </div>

        <div class="divider"></div>

        <table>
            <thead>
                <tr>
                    <th style="width: 50%;">DESC</th>
                    <th style="width: 15%;" class="text-center">CANT</th>
                    <th style="width: 35%;" class="text-end">TOTAL</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($detalles as $d): ?>
                <tr>
                    <td><?php echo strtoupper(substr($d['nombre_producto'], 0, 18)); ?></td>
                    <td class="text-center"><?php echo $d['cantidad']; ?></td>
                    <td class="text-end">$<?php echo number_format($d['cantidad'] * $d['precio_unitario'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="divider"></div>

        <div class="d-flex fw-bold" style="font-size: 14px;">
            <span>TOTAL A PAGAR:</span>
            <span>$<?php echo number_format($venta['total_monto_usd'], 2); ?></span>
        </div>

        <?php if($venta['saldo_vacios'] > 0): ?>
        <div class="divider"></div>
        <div class="text-center fw-bold">
            *** PENDIENTE POR DEVOLVER ***<br>
            <?php echo $venta['saldo_vacios']; ?> CAJAS/VACÍOS
        </div>
        <?php endif; ?>

        <?php if($venta['saldo_dinero_usd'] > 0): ?>
        <div class="text-center" style="margin-top: 5px; font-size: 11px;">
            Saldo Deudor Actual: $<?php echo number_format($venta['saldo_dinero_usd'], 2); ?>
        </div>
        <?php endif; ?>

        <div class="divider"></div>

        <div class="text-center" style="font-size: 11px;">
            SU LICORERIA<br>
        ABASTO COMERCIAL BLANCO<br>
        GRACIAS POR TU COMPRA
        </div>
        
        <br><br>
    </div>

</body>
</html>