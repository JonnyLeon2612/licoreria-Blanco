<?php
// modules/ventas/comprobante.php
include '../../config/db.php';

$id_venta = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_venta <= 0) {
    $_SESSION['error'] = "Venta no especificada";
    header("Location: index.php");
    exit();
}

/* ============================
   OBTENER DATOS
============================ */
$venta = $pdo->prepare("
    SELECT v.*, c.nombre_cliente, c.rif_cedula, c.telefono, c.direccion,
           COALESCE(cc.saldo_dinero_usd, 0) as saldo_dinero_usd, 
           COALESCE(cc.saldo_vacios, 0) as saldo_vacios,
           COALESCE((SELECT SUM(vacios_devueltos) FROM abonos WHERE id_venta = v.id_venta), 0) as vacios_recibidos_hoy,
           COALESCE((SELECT SUM(monto_abonado_usd) FROM abonos WHERE id_venta = v.id_venta), 0) as dinero_recibido_hoy
    FROM ventas v
    JOIN clientes c ON v.id_cliente = c.id_cliente
    LEFT JOIN cuentas_por_cobrar cc ON v.id_cliente = cc.id_cliente
    WHERE v.id_venta = ?
");
$venta->execute([$id_venta]);
$venta = $venta->fetch(PDO::FETCH_ASSOC);

if (!$venta) {
    $_SESSION['error'] = "Venta no encontrada";
    header("Location: index.php");
    exit();
}

// Usamos COALESCE para mantener nombres históricos
$detalle = $pdo->prepare("
    SELECT dv.*, COALESCE(dv.nombre_historico, p.nombre_producto) as nombre_producto 
    FROM detalle_ventas dv
    JOIN productos p ON dv.id_producto = p.id_producto
    WHERE dv.id_venta = ?
");
$detalle->execute([$id_venta]);
$detalles = $detalle->fetchAll();

/* ============================
   WHATSAPP
============================ */

// Evitamos errores si el cliente (como Venta en Puerta) no tiene teléfono registrado
$telefono_final = preg_replace('/[^0-9]/', '', (string)$venta['telefono']);
if (substr($telefono_final, 0, 1) == '0') {
    $telefono_final = '58' . substr($telefono_final, 1);
}

$nombre_cliente = strtoupper($venta['nombre_cliente']);
$nro_ticket = str_pad($id_venta, 4, '0', STR_PAD_LEFT);
$fecha_ticket = date('Y-m-d', strtotime($venta['fecha_venta'])); // Formato igual al Excel
$hora_ticket = date('h:i A', strtotime($venta['fecha_venta']));
$total_monto = $venta['total_monto_usd'] + 0; // Para quitar ceros extra si es exacto

$mensaje  = "*LICORERÍA BLANCO*\n\n";
$mensaje .= "Cliente: *{$nombre_cliente}*\n";
$mensaje .= "Ticket: {$nro_ticket}\n";
$mensaje .= "Fecha: {$fecha_ticket}\n";
$mensaje .= "TOTAL: \${$total_monto}\n";
if ($venta['saldo_vacios'] > 0) {
    $mensaje .= "Vacíos: {$venta['saldo_vacios']}\n";
}
$mensaje .= "\n✅ Gracias por su compra";

$mensaje_codificado = urlencode($mensaje);
$es_movil = preg_match("/android|iphone|ipad/i", $_SERVER["HTTP_USER_AGENT"]);

$link_ws = $es_movil
    ? "https://api.whatsapp.com/send?phone={$telefono_final}&text={$mensaje_codificado}"
    : "https://web.whatsapp.com/send?phone={$telefono_final}&text={$mensaje_codificado}";

// Función rápida para formatear números como en Excel (sin ceros inútiles)
function formatoExcel($num) {
    $n = round($num, 2);
    return (floor($n) == $n) ? number_format($n, 0, '', '') : number_format($n, 2, '.', '');
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ticket</title>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<style>
/* ============================
   CONFIGURACIÓN TÉRMICA ESTILO EXCEL
============================ */
* {
    box-sizing: border-box;
}

body {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 9px;
    background: #f0f0f0;
    margin: 0;
    padding: 10px;
    display: flex;
    flex-direction: column;
    align-items: center;
}

/* Área exacta que respeta el cabezal térmico */
.ticket-container {
    background: #fff;
    width: 42mm;
    padding: 2mm;
    margin-bottom: 15px;
    color: #000;
}

@media print {
    body {
        background: #fff;
        margin: 0;
        padding: 0;
        display: block; /* EVITA QUE SE CORTE EL TICKET ABAJO */
    }
    .ticket-container {
        width: 42mm;
        margin: 0;
        padding: 0;
    }
    .no-print { display: none !important; }
    @page { size: 58mm auto; margin: 0mm; }
}

/* Tablas cuadradas tipo Excel */
table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
}
td, th {
    font-size: 9px;
    padding: 1px 0;
    vertical-align: top;
    word-wrap: break-word;
    font-weight: normal; /* Excel no usa negritas en los headers */
}

.text-center { text-align: center; }
.text-right { text-align: right; }

/* Logotipo */
.logo {
    max-width: 24mm;
    margin-bottom: 2px;
    display: block;
    margin-left: auto;
    margin-right: auto;
}

/* Botones para UI */
.no-print {
    width: 100%; max-width: 300px;
    display: flex; flex-direction: column; gap: 10px; margin-bottom: 10px;
}
.btn {
    padding: 12px; border: none; border-radius: 8px; font-weight: bold;
    color: #fff; cursor: pointer; text-align: center; text-decoration: none;
}
.btn-green { background: #25D366; }
.btn-blue { background: #007bff; }
.btn-gray { background: #6c757d; }
.btn-outline { background: #fff; color: #333; border: 1px solid #ccc; }
</style>
</head>

<body>

<div class="no-print">
    <a href="<?php echo $link_ws; ?>" target="_blank" class="btn btn-green">📱 Abrir en WhatsApp</a>
    <button onclick="descargarSoloImagen()" class="btn btn-outline">📸 Descargar Imagen del Ticket</button>
    <button onclick="window.print()" class="btn btn-blue">🖨️ Imprimir Ticket</button>
    <a href="javascript:history.back()" class="btn btn-gray">🔙 Volver</a>
</div>

<div class="ticket-container" id="area-ticket">

    <?php if(file_exists('../../assets/img/logo.png')): ?>
        <img src="../../assets/img/logo.png" class="logo">
    <?php endif; ?>

<div class="text-center" style="margin-bottom: 4px;">
        <strong>ABASTO COMERCIAL BLANCO</strong><br>
        "Urb. Michelena, Local A5"<br>
        Tlt. 424-457.27.16<br>
        Exp. AV-MY-16035 &nbsp;|&nbsp; V-015946489
    </div>

    <table style="margin-bottom: 4px; border-bottom: 1px dashed #000; padding-bottom: 2px;">
        <tr>
            <td style="width: 40%; padding-bottom: 2px;">
                Ticket: <strong><?php echo $nro_ticket; ?></strong>
            </td>
            <td style="width: 60%; padding-bottom: 2px;" class="text-right">
                Fecha: <?php echo $fecha_ticket; ?>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                Cliente: <?php echo $nombre_cliente; ?><br>
                R.I.F: <?php echo $venta['rif_cedula']; ?>
            </td>
        </tr>
    </table>

    <table style="margin-bottom: 4px; border-bottom: 1px dashed #000; padding-bottom: 2px;">
        <thead>
            <tr>
                <th style="width: 45%; text-align: left;">Producto</th>
                <th style="width: 15%;" class="text-center">cant</th>
                <th style="width: 20%;" class="text-right">Precio</th>
                <th style="width: 20%;" class="text-right">Total</th>
            </tr>
        </thead>
        <tbody >
            <?php foreach($detalles as $d): ?>
            <tr>
                <td><?php echo strtoupper($d['nombre_producto']); ?></td>
                <td class="text-center"><?php echo $d['cantidad']; ?></td>
                <td class="text-right"><?php echo formatoExcel($d['precio_unitario']); ?></td>
                <td class="text-right"><?php echo formatoExcel($d['cantidad'] * $d['precio_unitario']); ?></td>
            </tr>
            <?php endforeach; ?>
            
            <tr><td colspan="4" style="height: 10px;"></td></tr>

            <tr>
                <td colspan="3" class="text-right">Subtotal:</td>
                <td class="text-right"><?php echo formatoExcel($venta['total_monto_usd']); ?></td>
            </tr>
            <tr>
                <td colspan="3" class="text-right">Total:</td>
                <td class="text-right"><?php echo formatoExcel($venta['total_monto_usd']); ?></td>
            </tr>
            <tr>
                <td colspan="3" class="text-right">Pago recibido:</td>
                <td class="text-right"><?php echo formatoExcel($venta['dinero_recibido_hoy']); ?></td>
            </tr>

        </tbody>
    </table>
            
            <?php if($venta['saldo_vacios'] > 0): ?>
            <tr><td colspan="4" style="height: 5px;"></td></tr>
            
            <tr>
                <td colspan="4" class="text-right">Vacíos a devolver:</td>
                 <td class="text-right"><?php echo formatoExcel($venta['saldo_vacios']); ?></td>
            </tr>
            <?php endif; ?>

            
    <div style="height: 10px;"></div>

    <div class="text-center">
        SU LICORERIA<br>
        ABASTO COMERCIAL BLANCO<br>
        GRACIAS POR TU COMPRA
    </div>

</div>

<script>
function descargarSoloImagen() {
    var btn = document.querySelector('.btn-outline');
    var textoOriginal = btn.innerHTML;
    btn.innerHTML = "Procesando...";
    btn.disabled = true;

    html2canvas(document.querySelector("#area-ticket"), {
        scale: 2, 
        backgroundColor: "#ffffff" 
    }).then(canvas => {
        var enlace = document.createElement('a');
        enlace.download = 'Ticket_<?php echo $id_venta; ?>.png';
        enlace.href = canvas.toDataURL("image/png");
        document.body.appendChild(enlace);
        enlace.click();
        document.body.removeChild(enlace);

        btn.innerHTML = textoOriginal;
        btn.disabled = false;
    }).catch(error => {
        alert("Error al generar imagen");
        btn.innerHTML = textoOriginal;
        btn.disabled = false;
    });
}
</script>

</body>
</html>