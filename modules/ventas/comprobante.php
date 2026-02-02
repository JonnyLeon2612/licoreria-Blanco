<?php
// modules/ventas/comprobante.php
$page_title = "Comprobante de Venta";
include '../../config/db.php';
include '../../includes/header.php';

$id_venta = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_venta <= 0) {
    $_SESSION['error'] = "Venta no especificada";
    header("Location: index.php");
    exit();
}

// Obtener información de la venta
$venta = $pdo->prepare("
    SELECT v.*, c.nombre_cliente, c.rif_cedula, c.telefono, c.direccion,
           cc.saldo_dinero_usd as deuda_actual, cc.saldo_vacios as vacios_pendientes
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

// Obtener detalle de la venta
$detalle = $pdo->prepare("
    SELECT dv.*, p.nombre_producto, p.es_retornable
    FROM detalle_ventas dv
    JOIN productos p ON dv.id_producto = p.id_producto
    WHERE dv.id_venta = ?
    ORDER BY dv.id_detalle ASC
");
$detalle->execute([$id_venta]);
$detalle = $detalle->fetchAll();

// Obtener abonos relacionados
$abonos = $pdo->prepare("
    SELECT * FROM abonos 
    WHERE id_venta = ? OR (id_cliente = ? AND fecha_abono >= ?)
    ORDER BY fecha_abono ASC
");
$abonos->execute([$id_venta, $venta['id_cliente'], $venta['fecha_venta']]);
$abonos = $abonos->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0">
            <i class="bi bi-receipt text-primary"></i> Comprobante de Venta
        </h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../dashboard/index.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="index.php">Ventas</a></li>
                <li class="breadcrumb-item"><a href="historial.php">Historial</a></li>
                <li class="breadcrumb-item active">Venta #<?php echo str_pad($id_venta, 5, '0', STR_PAD_LEFT); ?></li>
            </ol>
        </nav>
    </div>
    <div>
        <button class="btn btn-outline-primary" onclick="window.print()">
            <i class="bi bi-printer"></i> Imprimir
        </button>
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="bi bi-plus-circle"></i> Nueva Venta
        </a>
    </div>
</div>

<!-- Comprobante principal -->
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <!-- Encabezado del comprobante -->
        <div class="row mb-4">
            <div class="col-md-6">
                <h2 class="text-primary">SIGIB Blanco</h2>
                <p class="mb-1">Sistema de Gestión Integral</p>
                <p class="mb-1">RIF: J-12345678-9</p>
                <p class="mb-1">Teléfono: (123) 456-7890</p>
                <p class="mb-0">Fecha: <?php echo date('d/m/Y H:i:s'); ?></p>
            </div>
            <div class="col-md-6 text-end">
                <h1 class="text-success">COMPROBANTE DE VENTA</h1>
                <h3 class="text-primary">#<?php echo str_pad($id_venta, 5, '0', STR_PAD_LEFT); ?></h3>
                <p class="mb-1">
                    <span class="badge bg-<?php 
                        echo $venta['estado_pago'] == 'Pagado' ? 'success' : 
                             ($venta['estado_pago'] == 'Pendiente' ? 'warning' : 'info'); 
                    ?> fs-6">
                        <?php echo $venta['estado_pago']; ?>
                    </span>
                </p>
                <p class="mb-0">Fecha: <?php echo date('d/m/Y H:i', strtotime($venta['fecha_venta'])); ?></p>
            </div>
        </div>
        
        <!-- Información del cliente -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bg-light">
                    <div class="card-body">
                        <h5 class="card-title"><i class="bi bi-person"></i> Información del Cliente</h5>
                        <div class="row">
                            <div class="col-md-4">
                                <strong>Nombre:</strong> <?php echo $venta['nombre_cliente']; ?>
                            </div>
                            <div class="col-md-4">
                                <strong>RIF/Cédula:</strong> <?php echo $venta['rif_cedula']; ?>
                            </div>
                            <div class="col-md-4">
                                <strong>Teléfono:</strong> <?php echo $venta['telefono'] ?: 'No especificado'; ?>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-12">
                                <strong>Dirección:</strong> <?php echo $venta['direccion'] ?: 'No especificada'; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Detalle de productos -->
        <div class="table-responsive mb-4">
            <table class="table table-bordered">
                <thead class="table-dark">
                    <tr>
                        <th class="text-center">#</th>
                        <th>Producto</th>
                        <th class="text-center">Cantidad</th>
                        <th class="text-end">Precio Unitario</th>
                        <th class="text-end">Subtotal</th>
                        <th class="text-center">Tipo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_productos = 0;
                    $total_vacios = 0;
                    foreach($detalle as $index => $item): 
                        $total_productos += $item['cantidad'];
                        if ($item['es_retornable']) {
                            $total_vacios += $item['cantidad'];
                        }
                    ?>
                    <tr>
                        <td class="text-center"><?php echo $index + 1; ?></td>
                        <td><?php echo $item['nombre_producto']; ?></td>
                        <td class="text-center"><?php echo number_format($item['cantidad']); ?></td>
                        <td class="text-end">$<?php echo number_format($item['precio_unitario'], 2); ?></td>
                        <td class="text-end">$<?php echo number_format($item['cantidad'] * $item['precio_unitario'], 2); ?></td>
                        <td class="text-center">
                            <?php if($item['es_retornable']): ?>
                                <span class="badge bg-info">Retornable</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Desechable</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Resumen de la venta -->
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="bi bi-info-circle"></i> Resumen de la Venta</h6>
                    </div>
                    <div class="card-body">
                        <div class="row mb-2">
                            <div class="col-6">Total Productos:</div>
                            <div class="col-6 text-end fw-bold"><?php echo number_format($total_productos); ?></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-6">Vacíos Despachados:</div>
                            <div class="col-6 text-end fw-bold"><?php echo number_format($venta['total_vacios_despachados']); ?></div>
                        </div>
                        <hr>
                        <div class="row mb-2">
                            <div class="col-6">Subtotal:</div>
                            <div class="col-6 text-end">$<?php echo number_format($venta['total_monto_usd'], 2); ?></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-6">IVA (16%):</div>
                            <div class="col-6 text-end">$0.00</div>
                        </div>
                        <div class="row">
                            <div class="col-6"><h5 class="mb-0">TOTAL:</h5></div>
                            <div class="col-6 text-end"><h5 class="mb-0 text-success">$<?php echo number_format($venta['total_monto_usd'], 2); ?></h5></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="bi bi-cash-coin"></i> Estado de Pago</h6>
                    </div>
                    <div class="card-body">
                        <div class="row mb-2">
                            <div class="col-6">Total Venta:</div>
                            <div class="col-6 text-end fw-bold">$<?php echo number_format($venta['total_monto_usd'], 2); ?></div>
                        </div>
                        
                        <?php 
                        $total_abonado = 0;
                        $total_vacios_devueltos = 0;
                        foreach($abonos as $abono) {
                            $total_abonado += $abono['monto_abonado_usd'];
                            $total_vacios_devueltos += $abono['vacios_devueltos'];
                        }
                        ?>
                        
                        <div class="row mb-2">
                            <div class="col-6">Total Abonado:</div>
                            <div class="col-6 text-end text-success">$<?php echo number_format($total_abonado, 2); ?></div>
                        </div>
                        
                        <div class="row mb-2">
                            <div class="col-6">Saldo Pendiente:</div>
                            <div class="col-6 text-end text-danger fw-bold">
                                $<?php echo number_format($venta['total_monto_usd'] - $total_abonado, 2); ?>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="row mb-2">
                            <div class="col-6">Vacíos Despachados:</div>
                            <div class="col-6 text-end"><?php echo number_format($venta['total_vacios_despachados']); ?></div>
                        </div>
                        
                        <div class="row mb-2">
                            <div class="col-6">Vacíos Devueltos:</div>
                            <div class="col-6 text-end text-success"><?php echo number_format($total_vacios_devueltos); ?></div>
                        </div>
                        
                        <div class="row">
                            <div class="col-6">Vacíos Pendientes:</div>
                            <div class="col-6 text-end text-warning fw-bold">
                                <?php echo number_format($venta['total_vacios_despachados'] - $total_vacios_devueltos); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Observaciones -->
        <div class="mt-4">
            <div class="card bg-light">
                <div class="card-body">
                    <h6 class="card-title"><i class="bi bi-chat-left-text"></i> Observaciones</h6>
                    <p class="mb-0">
                        <?php if($venta['estado_pago'] == 'Pagado'): ?>
                            <span class="text-success">✓ Venta pagada en su totalidad.</span>
                        <?php elseif($venta['estado_pago'] == 'Pendiente'): ?>
                            <span class="text-danger">● Venta pendiente de pago. Saldo pendiente: $<?php echo number_format($venta['total_monto_usd'] - $total_abonado, 2); ?></span>
                        <?php else: ?>
                            <span class="text-warning">● Venta abonada parcialmente.</span>
                        <?php endif; ?>
                        <br>
                        <?php if(($venta['total_vacios_despachados'] - $total_vacios_devueltos) > 0): ?>
                            <span class="text-warning">● Pendiente devolución de <?php echo $venta['total_vacios_despachados'] - $total_vacios_devueltos; ?> vacíos.</span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Firmas -->
        <div class="row mt-4">
            <div class="col-md-6 text-center">
                <p class="border-top pt-3">_________________________</p>
                <p class="mb-0"><strong>Firma del Cliente</strong></p>
                <p class="small text-muted">Recibí conforme</p>
            </div>
            <div class="col-md-6 text-center">
                <p class="border-top pt-3">_________________________</p>
                <p class="mb-0"><strong>Firma del Vendedor</strong></p>
                <p class="small text-muted">Autorizado por</p>
            </div>
        </div>
    </div>
</div>

<!-- Abonos relacionados -->
<?php if(count($abonos) > 0): ?>
<div class="card shadow-sm">
    <div class="card-header bg-info text-white">
        <h5 class="mb-0"><i class="bi bi-cash-stack"></i> Historial de Abonos</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Método</th>
                        <th class="text-end">Monto ($)</th>
                        <th class="text-center">Vacíos Devueltos</th>
                        <th>Referencia</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($abonos as $abono): ?>
                    <tr>
                        <td><?php echo date('d/m/Y H:i', strtotime($abono['fecha_abono'])); ?></td>
                        <td><?php echo $abono['metodo_pago']; ?></td>
                        <td class="text-end text-success fw-bold">$<?php echo number_format($abono['monto_abonado_usd'], 2); ?></td>
                        <td class="text-center">
                            <?php if($abono['vacios_devueltos'] > 0): ?>
                                <span class="badge bg-success">+<?php echo $abono['vacios_devueltos']; ?></span>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($abono['id_venta'] == $id_venta): ?>
                                <span class="badge bg-primary">Esta venta</span>
                            <?php else: ?>
                                <span class="text-muted">Otra venta</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Botones de acción -->
<div class="mt-4 text-center">
    <a href="../cobranza/index.php?cliente=<?php echo $venta['id_cliente']; ?>" class="btn btn-success">
        <i class="bi bi-cash-coin"></i> Registrar Pago
    </a>
    <a href="index.php?cliente=<?php echo $venta['id_cliente']; ?>" class="btn btn-primary">
        <i class="bi bi-cart-plus"></i> Nueva Venta para este Cliente
    </a>
    <a href="historial.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Volver al Historial
    </a>
</div>

<!-- Estilos para impresión -->
<style>
@media print {
    .navbar, .sidebar, .breadcrumb, .btn, footer {
        display: none !important;
    }
    
    .card {
        border: 1px solid #000 !important;
        box-shadow: none !important;
    }
    
    .card-header {
        background-color: #f8f9fa !important;
        color: #000 !important;
        border-bottom: 2px solid #000 !important;
    }
    
    .badge {
        border: 1px solid #000 !important;
    }
    
    body {
        font-size: 12pt;
        background: white !important;
        color: black !important;
    }
    
    .container-fluid {
        padding: 0 !important;
    }
}
</style>

<?php include '../../includes/footer.php'; ?>