<?php
// modules/ventas/comprobante.php
include '../../config/db.php';

$id_venta = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_venta <= 0) {
    $_SESSION['error'] = "Venta no especificada";
    header("Location: index.php");
    exit();
}

// 1. OBTENEMOS LOS DATOS
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

// --- LÓGICA WHATSAPP QUE SÍ FUNCIONA ---
$telefono_final = preg_replace('/[^0-9]/', '', $venta['telefono']);
if (substr($telefono_final, 0, 1) == '0') {
    $telefono_final = '58' . substr($telefono_final, 1);
}

// --- CONSTRUCCIÓN DEL MENSAJE PROFESIONAL CON HORA ---
$nombre_cliente = strtoupper($venta['nombre_cliente']);
$nro_ticket = str_pad($id_venta, 5, '0', STR_PAD_LEFT);
$fecha_ticket = date('d/m/Y', strtotime($venta['fecha_venta']));
$hora_ticket = date('h:i A', strtotime($venta['fecha_venta'])); // <--- HORA AÑADIDA
$total_monto = number_format($venta['total_monto_usd'], 2);

$mensaje  = "*LICORERÍA BLANCO* \n\n";
$mensaje .= "Estimado(a): *{$nombre_cliente}*\n";
$mensaje .= "Adjuntamos el detalle de su compra:\n\n";
$mensaje .= " *Ticket:* #{$nro_ticket}\n";
$mensaje .= " *Fecha:* {$fecha_ticket}\n";
$mensaje .= " *Hora:* {$hora_ticket}\n"; // <--- SE MUESTRA EN EL MENSAJE
$mensaje .= " *TOTAL:* \${$total_monto}\n";

// Si debe vacíos, agregamos la advertencia al mensaje
if ($venta['saldo_vacios'] > 0) {
    $mensaje .= "\n *RECORDATORIO:* Tiene un saldo pendiente de *{$venta['saldo_vacios']} vacíos/cajas* por devolver.\n";
}

$mensaje .= "\n✅ _¡Gracias por su preferencia!_";

$mensaje_codificado = urlencode($mensaje);

// Detectamos Móvil
$es_movil = preg_match("/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i", $_SERVER["HTTP_USER_AGENT"]);

// Guardamos el link en una variable PHP para pasarlo a Javascript luego
if ($es_movil) {
    $link_ws = "https://api.whatsapp.com/send?phone={$telefono_final}&text={$mensaje_codificado}";
} else {
    $link_ws = "https://web.whatsapp.com/send?phone={$telefono_final}&text={$mensaje_codificado}";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket #<?php echo str_pad($id_venta, 5, '0', STR_PAD_LEFT); ?></title>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    <style>
        /* --- ESTILO RESPONSIVE PARA QUE SE VEA BIEN EN CELULAR --- */
        body {
            font-family: Arial, sans-serif; 
            font-size: 10px;
            line-height: 1.1;
            background-color: #f0f0f0; /* Fondo gris para resaltar el ticket en pantalla */
            margin: 0;
            padding: 10px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* El Ticket visual */
        .ticket-container {
            background-color: #fff;
            width: 46mm; 
            padding: 5px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2); /* Sombrita para que se vea pro */
            margin-bottom: 20px;
        }

        /* Ajustes de impresión (Para que salga bien en el papel) */
        @media print {
            body {
                background-color: #fff;
                margin: 0;
                padding: 0;
            }
            .ticket-container {
                box-shadow: none;
                width: 46mm;
                margin: 0;
                padding: 0;
            }
            .no-print { display: none !important; }
            @page {
                size: 58mm auto;
                margin: 0;
            }
        }

        .text-center { text-align: center; }
        .text-end { text-align: right; }
        .fw-bold { font-weight: bold; }
        .d-flex { display: flex; justify-content: space-between; }
        
        .divider {
            border-top: 1px dashed #000;
            margin: 5px 0;
            width: 100%;
        }

        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th { text-align: left; border-bottom: 1px dashed #000; font-size: 8px; }
        td { padding: 2px 0; vertical-align: top; font-size: 9px; word-wrap: break-word; }
        
        .logo { 
            max-width: 30mm;
            height: auto; 
            margin-bottom: 5px; 
            filter: grayscale(100%);
        }

        /* Botones grandes y fáciles de tocar en celular */
        .no-print {
            width: 100%;
            max-width: 300px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .btn {
            background: #333;
            color: #fff;
            text-decoration: none;
            padding: 12px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: bold;
            border: none;
            cursor: pointer;
            text-align: center;
            width: 100%;
            display: block;
            box-sizing: border-box;
        }
        .btn-green { background: #25D366; }
        .btn-blue { background: #007bff; }
        .btn-gray { background: #6c757d; }
    </style>
</head>
<body>

    <div class="no-print">
        <button onclick="descargarYEnviar()" class="btn btn-green">📱 WhatsApp + Foto</button>
        
        <button onclick="window.print()" class="btn btn-blue">🖨️ Imprimir</button>
        <a href="javascript:history.back()" class="btn btn-gray">🔙 Volver</a>
    </div>

    <div class="ticket-container" id="area-ticket">
        
        <div class="text-center">
            <?php if(file_exists('../../assets/img/logo.png')): ?>
                <img src="../../assets/img/logo.png" class="logo" alt="LOGO"><br>
            <?php endif; ?>
            
            <span class="fw-bold" style="font-size: 12px;">ABASTO COMERCIAL BLANCO</span><br>
            <span style="font-size: 9px;">Urb. Michelena, Local A5</span><br>        
        </div>

        <div style="font-size: 9px; text-align: left;">
            Tlf: 424-457.27.16
        </div>

        <div class="fw-bold" style="font-size: 9px; text-align: left;">
            Exp. AV-MY-16035 &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; V-015946489
        </div>

        <div class="divider"></div>

        <div style="font-size: 10px;">
            <div class="d-flex">
                <span><span class="fw-bold">Ticket:</span> <?php echo str_pad($id_venta, 4, '0', STR_PAD_LEFT); ?></span>
                <span><span class="fw-bold">Fecha:</span> <?php echo date('d/m/y', strtotime($venta['fecha_venta'])); ?></span>
            </div>
            
            <div style="margin-top: 3px;">
                <span class="fw-bold">Cliente:</span> <?php echo strtoupper($venta['nombre_cliente']); ?>
            </div>
            
            <div class="d-flex">
                <span><span class="fw-bold">Direc:</span> <?php echo strtoupper($venta['direccion'] ?? 'NO REG'); ?></span>
            </div>
        </div>
        
        <div class="divider"></div>

        <table>
            <thead>
                <tr>
                    <th style="width: 35%;">Producto</th>
                    <th style="width: 12%;" class="text-center">cant</th>
                    <th style="width: 23%;" class="text-end">Precio</th>
                    <th style="width: 30%;" class="text-end">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($detalles as $d): ?>
                <tr>
                    <td><?php echo strtoupper($d['nombre_producto']); ?></td>
                    <td class="text-center"><?php echo $d['cantidad']; ?></td>
                    <td class="text-end"><?php echo number_format($d['precio_unitario'], 2); ?></td>
                    <td class="text-end"><?php echo number_format($d['cantidad'] * $d['precio_unitario'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="divider"></div>

        <div class="text-end" style="font-size: 10px;">
            Subtotal: <?php echo number_format($venta['total_monto_usd'], 2); ?>
        </div>
        <div class="d-flex fw-bold" style="font-size: 12px; margin-top: 2px;">
            <span>TOTAL:</span>
            <span>$<?php echo number_format($venta['total_monto_usd'], 2); ?></span>
        </div>

        <?php if($venta['saldo_vacios'] > 0): ?>
        <div style="margin-top: 5px; border-top: 1px dotted #000; padding-top: 2px;">
            <div class="d-flex fw-bold" style="font-size: 10px;">
                <span>Vacios pendientes:</span>
                <span><?php echo $venta['saldo_vacios']; ?></span>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="divider"></div>

        <div class="text-center" style="font-size: 9px;">
            SU LICORERIA<br>
            ABASTO COMERCIAL BLANCO<br>
            GRACIAS POR TU COMPRA
        </div>
        
        <br>
        <div class="text-center">.</div>
    </div>

    <script>
        function descargarYEnviar() {
            // 1. Mostrar estado de carga en el botón
            var btn = document.querySelector('.btn-green');
            var textoOriginal = btn.innerHTML;
            btn.innerHTML = "📸 Creando Imagen...";
            btn.disabled = true;

            // 2. Tomar foto del ticket (ID: area-ticket)
            html2canvas(document.querySelector("#area-ticket"), {
                scale: 2, // Mejor calidad de imagen
                backgroundColor: "#ffffff" // Fondo blanco obligatorio
            }).then(canvas => {
                
                // 3. Descargar la imagen automáticamente
                var enlace = document.createElement('a');
                enlace.download = 'Ticket_<?php echo $id_venta; ?>.png';
                enlace.href = canvas.toDataURL("image/png");
                document.body.appendChild(enlace);
                enlace.click();
                document.body.removeChild(enlace);

                // 4. Abrir WhatsApp (Usando tu lógica de ventana única)
                setTimeout(function(){
                    var linkWs = '<?php echo $link_ws; ?>';
                    window.open(linkWs, 'whatsapp_unico');
                    
                    // Restaurar botón
                    btn.innerHTML = textoOriginal;
                    btn.disabled = false;
                }, 800); // Pequeña pausa para asegurar que la descarga inicie primero
            }).catch(function(error) {
                alert("Error al crear imagen: " + error);
                btn.innerHTML = textoOriginal;
                btn.disabled = false;
            });
        }
    </script>
</body>
</html>