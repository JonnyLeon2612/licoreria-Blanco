<?php
// modules/clientes/perfil.php
$page_title = "Perfil del Cliente";
include '../../config/db.php';
include '../../includes/header.php';

$id_cliente = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_cliente <= 0) {
    header("Location: index.php");
    exit();
}

// 1. OBTENER INFORMACIÓN Y CALCULAR DEUDA (Lógica estándar)
$cliente = $pdo->prepare("
    SELECT c.*, 
           (
             (SELECT COALESCE(SUM(total_monto_usd), 0) FROM ventas WHERE id_cliente = c.id_cliente) - 
             (SELECT COALESCE(SUM(monto_abonado_usd), 0) FROM abonos WHERE id_cliente = c.id_cliente)
           ) as deuda_real,
           (
             (SELECT COALESCE(SUM(total_vacios_despachados), 0) FROM ventas WHERE id_cliente = c.id_cliente) - 
             (SELECT COALESCE(SUM(vacios_devueltos), 0) FROM abonos WHERE id_cliente = c.id_cliente)
           ) as vacios_reales,
           (SELECT COUNT(*) FROM ventas WHERE id_cliente = c.id_cliente) as total_ventas,
           (SELECT SUM(total_monto_usd) FROM ventas WHERE id_cliente = c.id_cliente) as monto_total_ventas,
           (SELECT MIN(fecha_venta) FROM ventas WHERE id_cliente = c.id_cliente) as primera_compra,
           cc.limite_credito, cc.dias_credito
    FROM clientes c
    LEFT JOIN cuentas_por_cobrar cc ON c.id_cliente = cc.id_cliente
    WHERE c.id_cliente = ?
");
$cliente->execute([$id_cliente]);
$cliente = $cliente->fetch(PDO::FETCH_ASSOC);

if (!$cliente) {
    $_SESSION['error'] = "Cliente no encontrado";
    header("Location: index.php");
    exit();
}

// 2. HISTORIAL MIXTO (ESTADO DE CUENTA)
$historial_query = $pdo->prepare("
    SELECT 'VENTA' as tipo, fecha_venta as fecha, total_monto_usd as monto, total_vacios_despachados as vacios_salida, 
           0 as vacios_entrada, id_venta as referencia, estado_pago as detalle, NULL as id_venta_abono
    FROM ventas 
    WHERE id_cliente = ?
    UNION ALL
    SELECT 'ABONO' as tipo, fecha_abono as fecha, monto_abonado_usd as monto, 0 as vacios_salida, 
           vacios_devueltos as vacios_entrada, id_abono as referencia, metodo_pago as detalle, id_venta as id_venta_abono
    FROM abonos 
    WHERE id_cliente = ?
    ORDER BY fecha ASC
");
$historial_query->execute([$id_cliente, $id_cliente]);
$historial_asc = $historial_query->fetchAll();

// Calculamos el saldo progresivo (Como un estado de cuenta bancario)
$saldo_dinero = 0;
$saldo_vacios = 0;
$historial_formateado = [];

foreach($historial_asc as $mov) {
    if($mov['tipo'] == 'VENTA') {
        $saldo_dinero += (float)$mov['monto'];
        $saldo_vacios += (int)$mov['vacios_salida'];
    } else {
        $saldo_dinero -= (float)$mov['monto'];
        $saldo_vacios -= (int)$mov['vacios_entrada'];
        if($saldo_dinero < 0.01) $saldo_dinero = 0; // Evitar negativos por céntimos
        if($saldo_vacios < 0) $saldo_vacios = 0;
    }
    $mov['saldo_dinero_momento'] = $saldo_dinero;
    $mov['saldo_vacios_momento'] = $saldo_vacios;
    $historial_formateado[] = $mov;
}

// Invertimos el array para que lo más reciente salga de primero
$historial = array_reverse($historial_formateado);

// 3. VENTAS PENDIENTES
$ventas_pendientes = $pdo->prepare("
    SELECT id_venta, fecha_venta, total_monto_usd, total_vacios_despachados, estado_pago
    FROM ventas 
    WHERE id_cliente = ? AND estado_pago != 'Pagado'
    ORDER BY fecha_venta DESC
");
$ventas_pendientes->execute([$id_cliente]);
$ventas_pendientes = $ventas_pendientes->fetchAll();
?>

<style>
    @media (max-width: 768px) {
        .mobile-profile-header {
            background: white; border-radius: 0 0 20px 20px; padding: 20px;
            text-align: center; margin-bottom: 20px; margin-top: -10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        .big-avatar {
            width: 80px; height: 80px; background: #2c3e50; color: white;
            border-radius: 50%; font-size: 2.5rem; font-weight: bold;
            display: flex; align-items: center; justify-content: center; margin: 0 auto 10px;
        }
        .stats-grid { display: flex; gap: 10px; margin-top: 20px; }
        .stat-card {
            flex: 1; background: #f8f9fa; padding: 10px; border-radius: 12px;
            text-align: center; border: 1px solid #eee;
        }
        .stat-val { font-size: 1.2rem; font-weight: 800; display: block; }
        .stat-lbl { font-size: 0.75rem; text-transform: uppercase; color: #777; }
        
        .action-buttons-mobile { display: flex; gap: 10px; margin-bottom: 20px; padding: 0 15px; }
        .btn-app {
            flex: 1; padding: 15px; border-radius: 12px; border: none;
            font-weight: 700; color: white; box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            display: flex; align-items: center; justify-content: center; gap: 8px; font-size: 1rem;
            text-decoration: none;
        }
        
        .history-card-mobile {
            background: white; border-radius: 12px; padding: 12px; margin-bottom: 10px;
            border: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center;
        }
        .history-icon {
            width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem; margin-right: 12px;
        }
    }
</style>

<div class="d-block d-md-none pb-5">
    
    <div class="mobile-profile-header">
        <div class="big-avatar">
            <?php echo strtoupper(substr($cliente['nombre_cliente'], 0, 1)); ?>
        </div>
        <h4 class="fw-bold m-0"><?php echo $cliente['nombre_cliente']; ?></h4>
        <div class="text-muted small"><?php echo $cliente['rif_cedula']; ?></div>
        
        <?php if($cliente['telefono']): ?>
            <a href="tel:<?php echo $cliente['telefono']; ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3 mt-2">
                <i class="bi bi-telephone-fill"></i> Llamar
            </a>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-val text-danger">$<?php echo number_format($cliente['deuda_real'], 2); ?></span>
                <span class="stat-lbl">Deuda</span>
            </div>
            <div class="stat-card">
                <span class="stat-val text-warning text-dark"><?php echo $cliente['vacios_reales']; ?></span>
                <span class="stat-lbl">Vacíos</span>
            </div>
        </div>
    </div>

    <div class="action-buttons-mobile">
        <a href="../ventas/index.php?cliente=<?php echo $id_cliente; ?>" class="btn-app bg-primary">
            <i class="bi bi-cart-plus-fill"></i> VENDER
        </a>
        <a href="../cobranza/index.php?cliente=<?php echo $id_cliente; ?>" class="btn-app bg-success">
            <i class="bi bi-wallet-fill"></i> COBRAR
        </a>
    </div>

    <?php if(count($ventas_pendientes) > 0): ?>
    <div class="px-3 mb-4">
        <h6 class="fw-bold text-danger mb-2">⚠️ PENDIENTES DE PAGO</h6>
        <?php foreach($ventas_pendientes as $vp): ?>
        <div class="history-card-mobile border-start border-4 border-danger">
            <div>
                <div class="fw-bold text-dark">Venta #<?php echo $vp['id_venta']; ?></div>
                <small class="text-muted"><?php echo date('d/m h:i A', strtotime($vp['fecha_venta'])); ?></small>
            </div>
            <div class="text-end">
                <div class="fw-bold text-danger">$<?php echo number_format($vp['total_monto_usd'], 2); ?></div>
                <span class="badge bg-danger">Pendiente</span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

<div class="px-3 pb-5">
        <h6 class="fw-bold text-muted mb-2">ESTADO DE CUENTA</h6>
        <?php foreach($historial as $mov): 
            $esVenta = $mov['tipo'] == 'VENTA';
            $bgIcon = $esVenta ? 'bg-danger bg-opacity-10 text-danger' : 'bg-success bg-opacity-10 text-success';
            $icon = $esVenta ? 'bi-cart-dash' : 'bi-cash-coin';
        ?>
        <div class="history-card-mobile position-relative overflow-hidden p-0 mb-3">
            <div style="position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background-color: <?php echo $esVenta ? '#dc3545' : '#198754'; ?>;"></div>
            
            <div class="d-flex justify-content-between align-items-start p-3 pb-2 ps-4">
                <div class="d-flex align-items-center">
                    <div class="history-icon <?php echo $bgIcon; ?> me-3" style="width:35px; height:35px; font-size:1.1rem; border-radius:8px;">
                        <i class="bi <?php echo $icon; ?>"></i>
                    </div>
                    <div>
                        <div class="fw-bold text-dark" style="font-size: 0.95rem;">
                            <?php echo $esVenta ? 'Factura #' . str_pad($mov['referencia'], 4, '0', STR_PAD_LEFT) : 'Recibo #' . str_pad($mov['referencia'], 4, '0', STR_PAD_LEFT); ?>
                        </div>
                        <small class="text-muted d-block" style="font-size: 0.75rem;"><?php echo date('d/m/y h:i A', strtotime($mov['fecha'])); ?></small>
                        <?php if(!$esVenta && $mov['id_venta_abono']): ?>
                            <small class="text-primary" style="font-size: 0.7rem;">Abono a Factura #<?php echo $mov['id_venta_abono']; ?></small>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="text-end">
                    <div class="fw-bold <?php echo $esVenta ? 'text-danger' : 'text-success'; ?>" style="font-size: 1rem;">
                        <?php echo $esVenta ? 'Cargo' : 'Abono'; ?><br>
                        $<?php echo number_format($mov['monto'], 2); ?>
                    </div>
                </div>
            </div>
            
            <div class="bg-light px-3 py-2 d-flex justify-content-between align-items-center border-top">
                <span class="small text-muted fw-bold">SALDO RESTANTE:</span>
                <span class="fw-bold text-primary fs-5">$<?php echo number_format($mov['saldo_dinero_momento'], 2); ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="d-none d-md-block">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">
                <i class="bi bi-person-circle text-primary"></i> Perfil del Cliente
            </h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../dashboard/index.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="index.php">Clientes</a></li>
                    <li class="breadcrumb-item active"><?php echo $cliente['nombre_cliente']; ?></li>
                </ol>
            </nav>
        </div>
        <div>
            <a href="../ventas/index.php?cliente=<?php echo $id_cliente; ?>" class="btn btn-primary">
                <i class="bi bi-cart-plus"></i> Nueva Venta
            </a>
            <a href="../cobranza/index.php?cliente=<?php echo $id_cliente; ?>" class="btn btn-success">
                <i class="bi bi-cash-coin"></i> Registrar Pago
            </a>
            <a href="javascript:history.back()" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Regresar
            </a>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-info-circle"></i> Información del Cliente</h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <div class="avatar-circle mb-3" style="margin: 0 auto;">
                            <div style="width: 100px; height: 100px; background-color: #3498db; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 2rem;">
                                <?php echo strtoupper(substr($cliente['nombre_cliente'], 0, 2)); ?>
                            </div>
                        </div>
                        <h4><?php echo $cliente['nombre_cliente']; ?></h4>
                        <span class="badge bg-<?php echo $cliente['tipo_cliente'] == 'Mayorista' ? 'primary' : 'secondary'; ?> fs-6">
                            <?php echo $cliente['tipo_cliente']; ?>
                        </span>
                    </div>
                    
                    <div class="mb-3">
                        <label class="small text-muted">RIF/Cédula</label>
                        <p class="mb-0 fw-bold"><?php echo $cliente['rif_cedula']; ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="small text-muted">Teléfono</label>
                        <p class="mb-0">
                            <?php if($cliente['telefono']): ?>
                                <a href="tel:<?php echo $cliente['telefono']; ?>" class="text-decoration-none">
                                    <i class="bi bi-telephone"></i> <?php echo $cliente['telefono']; ?>
                                </a>
                            <?php else: ?>
                                <span class="text-muted">No especificado</span>
                            <?php endif; ?>
                        </p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="small text-muted">Dirección</label>
                        <p class="mb-0"><?php echo $cliente['direccion'] ?: 'No especificada'; ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="small text-muted">Cliente desde</label>
                        <p class="mb-0 fw-bold">
                            <?php if(isset($cliente['primera_compra'])): ?>
                                <?php echo date('d/m/Y', strtotime($cliente['primera_compra'])); ?>
                            <?php else: ?>
                                <span class="text-muted">Sin compras aún</span>
                            <?php endif; ?>
                        </p>
                    </div>
                    
                    <div class="mt-4">
                        <a href="#" class="btn btn-outline-primary btn-sm w-100" onclick="editarCliente(<?php echo $id_cliente; ?>)">
                            <i class="bi bi-pencil"></i> Editar Información
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-8 mb-4">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <div class="card kpi-card kpi-danger h-100">
                        <div class="card-body text-center">
                            <div class="kpi-icon">
                                <i class="bi bi-cash-coin"></i>
                            </div>
                            <h5 class="card-title">Deuda Dinero</h5>
                            <h2>$<?php echo number_format($cliente['deuda_real'], 2); ?></h2>
                            <p class="card-text">Pendiente por cobrar</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <div class="card kpi-card kpi-warning h-100">
                        <div class="card-body text-center">
                            <div class="kpi-icon">
                                <i class="bi bi-box-seam"></i>
                            </div>
                            <h5 class="card-title">Vacíos Pendientes</h5>
                            <h2><?php echo number_format($cliente['vacios_reales']); ?></h2>
                            <p class="card-text">Cajas por devolver</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if($cliente['tipo_cliente'] == 'Mayorista'): ?>
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="bi bi-credit-card"></i> Información de Crédito</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-4">
                            <label class="small text-muted">Límite de Crédito</label>
                            <h4>$<?php echo number_format($cliente['limite_credito'] ?? 0, 2); ?></h4>
                        </div>
                        <div class="col-md-4">
                            <label class="small text-muted">Crédito Disponible</label>
                            <?php 
                            $limite = $cliente['limite_credito'] ?? 0;
                            $disponible = $limite - $cliente['deuda_real'];
                            $porcentaje = $limite > 0 ? ($cliente['deuda_real'] / $limite) * 100 : 0;
                            ?>
                            <h4 class="<?php echo $disponible < ($limite * 0.3) ? 'text-danger' : 'text-success'; ?>">
                                $<?php echo number_format($disponible, 2); ?>
                            </h4>
                            <div class="progress">
                                <div class="progress-bar <?php echo $porcentaje > 80 ? 'bg-danger' : ($porcentaje > 50 ? 'bg-warning' : 'bg-success'); ?>" 
                                     role="progressbar" 
                                     style="width: <?php echo min($porcentaje, 100); ?>%">
                                    <?php echo round($porcentaje); ?>%
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="small text-muted">Días de Crédito</label>
                            <h4><?php echo $cliente['dias_credito'] ?? 7; ?> días</h4>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i class="bi bi-graph-up"></i> Estadísticas</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-4">
                            <label class="small text-muted">Total de Ventas</label>
                            <h3><?php echo number_format($cliente['total_ventas']); ?></h3>
                        </div>
                        <div class="col-md-4">
                            <label class="small text-muted">Monto Total Comprado</label>
                            <h3>$<?php echo number_format($cliente['monto_total_ventas'] ?? 0, 2); ?></h3>
                        </div>
                        <div class="col-md-4">
                            <label class="small text-muted">Ticket Promedio</label>
                            <?php 
                            $ticket_promedio = $cliente['total_ventas'] > 0 
                                ? ($cliente['monto_total_ventas'] / $cliente['total_ventas']) 
                                : 0;
                            ?>
                            <h3>$<?php echo number_format($ticket_promedio, 2); ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if(count($ventas_pendientes) > 0): ?>
    <div class="card mb-4">
        <div class="card-header bg-warning text-dark">
            <h5 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Ventas Pendientes de Pago</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Venta #</th>
                            <th>Fecha</th>
                            <th class="text-end">Monto Original</th>
                            <th class="text-center">Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($ventas_pendientes as $venta): ?>
                        <tr>
                            <td>#<?php echo str_pad($venta['id_venta'], 5, '0', STR_PAD_LEFT); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($venta['fecha_venta'])); ?></td>
                            <td class="text-end fw-bold">$<?php echo number_format($venta['total_monto_usd'], 2); ?></td>
                            <td class="text-center">
                                <span class="badge bg-<?php echo $venta['estado_pago'] == 'Abonado' ? 'info' : 'danger'; ?>">
                                    <?php echo $venta['estado_pago']; ?>
                                </span>
                            </td>
                            <td>
                                <a href="../ventas/comprobante.php?id=<?php echo $venta['id_venta']; ?>" 
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-receipt"></i> Ver
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

<div class="card">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0"><i class="bi bi-clock-history"></i> Historial de Transacciones</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                
                <table class="table table-hover table-bordered align-middle datatable">
                    <thead class="table-light">
                        <tr>
                            <th>Fecha y Hora</th>
                            <th>Concepto</th>
                            <th class="text-danger text-end">Cargos (Ventas)</th>
                            <th class="text-success text-end">Abonos (Pagos)</th>
                            <th class="text-primary text-end fw-bold">Saldo Actual</th>
                            <th class="text-center bg-warning bg-opacity-10">Deuda Vacíos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($historial as $mov): ?>
                        <tr>
                            <td>
                                <span class="d-none"><?php echo strtotime($mov['fecha']); ?></span>
                                <?php echo date('d/m/Y h:i A', strtotime($mov['fecha'])); ?>
                            </td>
                            <td>
                                <?php if($mov['tipo'] == 'VENTA'): ?>
                                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger">Factura #<?php echo str_pad($mov['referencia'], 5, '0', STR_PAD_LEFT); ?></span>
                                    <small class="d-block text-muted mt-1">Estado: <?php echo $mov['detalle']; ?></small>
                                <?php else: ?>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success">Recibo Abono #<?php echo str_pad($mov['referencia'], 5, '0', STR_PAD_LEFT); ?></span>
                                    <?php if($mov['id_venta_abono']): ?>
                                        <small class="d-block text-primary mt-1">Va a Factura #<?php echo str_pad($mov['id_venta_abono'], 5, '0', STR_PAD_LEFT); ?></small>
                                    <?php else: ?>
                                        <small class="d-block text-muted mt-1">Método: <?php echo $mov['detalle']; ?></small>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td class="text-end text-danger fw-bold">
                                <?php echo $mov['tipo'] == 'VENTA' ? '$'.number_format($mov['monto'], 2) : ''; ?>
                            </td>
                            <td class="text-end text-success fw-bold">
                                <?php echo $mov['tipo'] == 'ABONO' ? '$'.number_format($mov['monto'], 2) : ''; ?>
                            </td>
                            <td class="text-end fw-bold text-primary bg-light" style="font-size: 1.1rem;">
                                $<?php echo number_format($mov['saldo_dinero_momento'], 2); ?>
                            </td>
                            <td class="text-center bg-warning bg-opacity-10">
                                <?php if($mov['tipo'] == 'VENTA' && $mov['vacios_salida'] > 0): ?>
                                    <span class="text-danger small">+<?php echo $mov['vacios_salida']; ?> prestados</span><br>
                                <?php elseif($mov['tipo'] == 'ABONO' && $mov['vacios_entrada'] > 0): ?>
                                    <span class="text-success small">-<?php echo $mov['vacios_entrada']; ?> devueltos</span><br>
                                <?php endif; ?>
                                <strong>Debe: <?php echo $mov['saldo_vacios_momento']; ?></strong>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEditarCliente" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content"></div>
    </div>
</div>

<script>
// Función para llamar al modal de edición
function editarCliente(id) {
    $.ajax({
        url: 'editar.php',
        type: 'GET',
        data: { id: id },
        success: function(response) {
            $('#modalEditarCliente .modal-content').html(response);
            $('#modalEditarCliente').modal('show');
        }
    });
}
</script>

<?php include '../../includes/footer.php'; ?>