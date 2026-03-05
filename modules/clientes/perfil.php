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

// 2. HISTORIAL MIXTO (Ventas y Abonos)
$historial = $pdo->prepare("
    SELECT 'VENTA' as tipo, fecha_venta as fecha, total_monto_usd as monto, total_vacios_despachados as vacios_salida, 
           0 as vacios_entrada, id_venta as referencia, estado_pago
    FROM ventas 
    WHERE id_cliente = ?
    UNION ALL
    SELECT 'ABONO' as tipo, fecha_abono as fecha, monto_abonado_usd as monto, 0 as vacios_salida, 
           vacios_devueltos as vacios_entrada, id_abono as referencia, metodo_pago as estado_pago
    FROM abonos 
    WHERE id_cliente = ?
    ORDER BY fecha DESC
    LIMIT 50
");
$historial->execute([$id_cliente, $id_cliente]);
$historial = $historial->fetchAll();

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
        <h6 class="fw-bold text-muted mb-2">HISTORIAL RECIENTE</h6>
        <?php foreach($historial as $mov): 
            $esVenta = $mov['tipo'] == 'VENTA';
            $bgIcon = $esVenta ? 'bg-primary bg-opacity-10 text-primary' : 'bg-success bg-opacity-10 text-success';
            $icon = $esVenta ? 'bi-bag' : 'bi-cash';
        ?>
        <div class="history-card-mobile">
            <div class="d-flex align-items-center">
                <div class="history-icon <?php echo $bgIcon; ?>">
                    <i class="bi <?php echo $icon; ?>"></i>
                </div>
                <div>
                    <div class="fw-bold text-dark"><?php echo $esVenta ? 'Compra' : 'Pago/Abono'; ?> #<?php echo $mov['referencia']; ?></div>
                    <small class="text-muted"><?php echo date('d/m h:i A', strtotime($mov['fecha'])); ?></small>
                </div>
            </div>
            
            <div class="text-end">
                <div class="fw-bold <?php echo $esVenta ? 'text-dark' : 'text-success'; ?>">
                    <?php echo $esVenta ? '-' : '+'; ?>$<?php echo number_format($mov['monto'], 2); ?>
                </div>
                
                <?php if($mov['vacios_entrada'] > 0): ?>
                    <span class="badge bg-warning text-dark border border-warning">
                        +<?php echo $mov['vacios_entrada']; ?> Vacíos
                    </span>
                <?php elseif($mov['vacios_salida'] > 0): ?>
                    <span class="badge bg-light text-muted border">
                        -<?php echo $mov['vacios_salida']; ?> Vacíos
                    </span>
                <?php else: ?>
                    <small class="text-muted"><?php echo $mov['estado_pago']; ?></small>
                <?php endif; ?>
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
                <table class="table table-hover datatable">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Referencia</th>
                            <th class="text-end">Monto $</th>
                            <th class="text-center">Vacíos</th>
                            <th>Estado/Detalle</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($historial as $movimiento): ?>
                        <tr>
                            <td><?php echo date('d/m/Y H:i', strtotime($movimiento['fecha'])); ?></td>
                            <td>
                                <?php if($movimiento['tipo'] == 'VENTA'): ?>
                                    <span class="badge bg-primary">VENTA</span>
                                <?php else: ?>
                                    <span class="badge bg-success">ABONO</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($movimiento['tipo'] == 'VENTA'): ?>
                                    Venta #<?php echo str_pad($movimiento['referencia'], 5, '0', STR_PAD_LEFT); ?>
                                <?php else: ?>
                                    Abono #<?php echo str_pad($movimiento['referencia'], 5, '0', STR_PAD_LEFT); ?>
                                <?php endif; ?>
                            </td>
                            <td class="text-end fw-bold">
                                <?php if((float)$movimiento['monto'] == 0): ?>
                                    <span class="text-muted small fw-normal">$0.00</span>
                                <?php elseif($movimiento['tipo'] == 'VENTA'): ?>
                                    <span class="text-danger">-$<?php echo number_format($movimiento['monto'], 2); ?></span>
                                <?php else: ?>
                                    <span class="text-success">+$<?php echo number_format($movimiento['monto'], 2); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if($movimiento['vacios_salida'] > 0): ?>
                                    <span class="badge bg-warning text-dark">-<?php echo $movimiento['vacios_salida']; ?></span>
                                <?php elseif($movimiento['vacios_entrada'] > 0): ?>
                                    <span class="badge bg-success">+<?php echo $movimiento['vacios_entrada']; ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($movimiento['tipo'] == 'VENTA'): ?>
                                    <span class="badge bg-<?php 
                                        echo $movimiento['estado_pago'] == 'Pagado' ? 'success' : 
                                                ($movimiento['estado_pago'] == 'Pendiente' ? 'warning' : 'info'); 
                                    ?>">
                                        <?php echo $movimiento['estado_pago']; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                                        <i class="bi bi-check2-all"></i> Recibido
                                    </span>
                                <?php endif; ?>
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