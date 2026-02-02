<?php
// modules/dashboard/index.php
$page_title = "Dashboard Principal";
include '../../config/db.php';
include '../../includes/header.php';

// KPIs principales
$kpis = [];

// 1. Deuda total
$kpis['deuda_total'] = $pdo->query("SELECT SUM(saldo_dinero_usd) as total FROM cuentas_por_cobrar")->fetchColumn() ?? 0;

// 2. Vacíos pendientes
$kpis['vacios_pendientes'] = $pdo->query("SELECT SUM(saldo_vacios) as total FROM cuentas_por_cobrar")->fetchColumn() ?? 0;

// 3. Stock bajo (menos de 10 unidades)
$kpis['stock_bajo'] = $pdo->query("SELECT COUNT(*) FROM productos WHERE stock_lleno < 10")->fetchColumn();

// 4. Ventas hoy
$kpis['ventas_hoy'] = $pdo->query("SELECT COUNT(*) FROM ventas WHERE DATE(fecha_venta) = CURDATE()")->fetchColumn();

// 5. Monto vendido hoy
$kpis['monto_hoy'] = $pdo->query("SELECT COALESCE(SUM(total_monto_usd), 0) FROM ventas WHERE DATE(fecha_venta) = CURDATE()")->fetchColumn();

// 6. Clientes con deuda
$kpis['clientes_deudores'] = $pdo->query("SELECT COUNT(DISTINCT id_cliente) FROM cuentas_por_cobrar WHERE saldo_dinero_usd > 0")->fetchColumn();

// Ventas de los últimos 7 días para gráfico
$ventas_7dias = $pdo->query("
    SELECT DATE(fecha_venta) as fecha, 
           COUNT(*) as cantidad, 
           SUM(total_monto_usd) as monto
    FROM ventas 
    WHERE fecha_venta >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(fecha_venta)
    ORDER BY fecha ASC
")->fetchAll();

// Productos más vendidos (top 5)
$top_productos = $pdo->query("
    SELECT p.nombre_producto, SUM(dv.cantidad) as total_vendido
    FROM detalle_ventas dv
    JOIN productos p ON dv.id_producto = p.id_producto
    JOIN ventas v ON dv.id_venta = v.id_venta
    WHERE v.fecha_venta >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY p.id_producto
    ORDER BY total_vendido DESC
    LIMIT 5
")->fetchAll();

// Clientes con mayor deuda (top 5)
$clientes_morosos = $pdo->query("
    SELECT c.nombre_cliente, cc.saldo_dinero_usd, cc.saldo_vacios
    FROM clientes c
    JOIN cuentas_por_cobrar cc ON c.id_cliente = cc.id_cliente
    WHERE cc.saldo_dinero_usd > 0
    ORDER BY cc.saldo_dinero_usd DESC
    LIMIT 5
")->fetchAll();

// Alertas de stock bajo
$alertas_stock = $pdo->query("
    SELECT nombre_producto, stock_lleno, precio_venta_usd
    FROM productos 
    WHERE stock_lleno < 10
    ORDER BY stock_lleno ASC
    LIMIT 5
")->fetchAll();

// Últimas ventas
$ultimas_ventas = $pdo->query("
    SELECT v.id_venta, c.nombre_cliente, v.total_monto_usd, v.fecha_venta, v.estado_pago
    FROM ventas v
    JOIN clientes c ON v.id_cliente = c.id_cliente
    ORDER BY v.fecha_venta DESC
    LIMIT 10
")->fetchAll();
?>

<div class="row mb-4">
    <div class="col-12">
        <h1 class="h3 mb-0">
            <i class="bi bi-speedometer2 text-primary"></i> Dashboard Principal
        </h1>
        <p class="text-muted">Resumen completo del negocio en tiempo real</p>
    </div>
</div>

<!-- KPIs principales -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card kpi-card kpi-danger">
            <div class="card-body">
                <div class="kpi-icon">
                    <i class="bi bi-cash-coin"></i>
                </div>
                <h5 class="card-title">Deuda Total</h5>
                <h2>$<?php echo number_format($kpis['deuda_total'], 2); ?></h2>
                <p class="card-text"><?php echo $kpis['clientes_deudores']; ?> clientes morosos</p>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card kpi-card kpi-warning">
            <div class="card-body">
                <div class="kpi-icon">
                    <i class="bi bi-box-seam"></i>
                </div>
                <h5 class="card-title">Vacíos Pendientes</h5>
                <h2><?php echo number_format($kpis['vacios_pendientes']); ?></h2>
                <p class="card-text">Cajas por recuperar</p>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card kpi-card kpi-success">
            <div class="card-body">
                <div class="kpi-icon">
                    <i class="bi bi-cart-check"></i>
                </div>
                <h5 class="card-title">Ventas Hoy</h5>
                <h2>$<?php echo number_format($kpis['monto_hoy'], 2); ?></h2>
                <p class="card-text"><?php echo $kpis['ventas_hoy']; ?> transacciones</p>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card kpi-card kpi-purple">
            <div class="card-body">
                <div class="kpi-icon">
                    <i class="bi bi-exclamation-triangle"></i>
                </div>
                <h5 class="card-title">Stock Bajo</h5>
                <h2><?php echo $kpis['stock_bajo']; ?></h2>
                <p class="card-text">Productos con stock < 10</p>
            </div>
        </div>
    </div>
</div>

<!-- Segunda fila: Gráficos -->
<div class="row mb-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-graph-up"></i> Ventas últimos 7 días</h5>
            </div>
            <div class="card-body">
                <canvas id="ventasChart" height="250"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-trophy"></i> Productos Más Vendidos</h5>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <?php foreach($top_productos as $index => $producto): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span class="fw-bold"><?php echo $index + 1; ?>. <?php echo $producto['nombre_producto']; ?></span>
                        <span class="badge bg-primary rounded-pill"><?php echo $producto['total_vendido']; ?> cajas</span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Tercera fila: Tablas -->
<div class="row">
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="bi bi-alarm"></i> Clientes con Mayor Deuda</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Cliente</th>
                                <th class="text-end">Deuda $</th>
                                <th class="text-end">Vacíos</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($clientes_morosos as $cliente): ?>
                            <tr>
                                <td><?php echo $cliente['nombre_cliente']; ?></td>
                                <td class="text-end text-danger fw-bold">$<?php echo number_format($cliente['saldo_dinero_usd'], 2); ?></td>
                                <td class="text-end"><span class="badge bg-warning text-dark"><?php echo $cliente['saldo_vacios']; ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(count($clientes_morosos) == 0): ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted py-3">
                                    <i class="bi bi-emoji-smile fs-4"></i>
                                    <p class="mb-0">¡No hay clientes morosos!</p>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Alertas de Stock Bajo</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Producto</th>
                                <th class="text-center">Stock</th>
                                <th class="text-end">Precio</th>
                                <th class="text-center">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($alertas_stock as $producto): ?>
                            <tr>
                                <td><?php echo $producto['nombre_producto']; ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?php echo $producto['stock_lleno'] < 5 ? 'danger' : 'warning'; ?>">
                                        <?php echo $producto['stock_lleno']; ?>
                                    </span>
                                </td>
                                <td class="text-end">$<?php echo number_format($producto['precio_venta_usd'], 2); ?></td>
                                <td class="text-center">
                                    <a href="../inventario/index.php" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-arrow-right"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(count($alertas_stock) == 0): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-3">
                                    <i class="bi bi-check-circle fs-4"></i>
                                    <p class="mb-0">Todo el stock está bien</p>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Cuarta fila: Últimas ventas -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bi bi-clock-history"></i> Últimas Ventas</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table datatable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Cliente</th>
                                <th>Fecha</th>
                                <th class="text-end">Monto</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($ultimas_ventas as $venta): ?>
                            <tr>
                                <td>#<?php echo str_pad($venta['id_venta'], 5, '0', STR_PAD_LEFT); ?></td>
                                <td><?php echo $venta['nombre_cliente']; ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($venta['fecha_venta'])); ?></td>
                                <td class="text-end fw-bold">$<?php echo number_format($venta['total_monto_usd'], 2); ?></td>
                                <td>
                                    <?php if($venta['estado_pago'] == 'Pagado'): ?>
                                        <span class="status-badge status-paid">Pagado</span>
                                    <?php elseif($venta['estado_pago'] == 'Pendiente'): ?>
                                        <span class="status-badge status-pending">Pendiente</span>
                                    <?php else: ?>
                                        <span class="status-badge status-partial">Abonado</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="../ventas/comprobante.php?id=<?php echo $venta['id_venta']; ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-receipt"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Gráfico de ventas
const ctx = document.getElementById('ventasChart').getContext('2d');

// Preparar datos del gráfico
const fechas = <?php echo json_encode(array_column($ventas_7dias, 'fecha')); ?>;
const montos = <?php echo json_encode(array_column($ventas_7dias, 'monto')); ?>;
const cantidades = <?php echo json_encode(array_column($ventas_7dias, 'cantidad')); ?>;

// Formatear fechas para mostrar
const fechasFormateadas = fechas.map(fecha => {
    const d = new Date(fecha);
    return d.getDate() + '/' + (d.getMonth() + 1);
});

const ventasChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: fechasFormateadas,
        datasets: [{
            label: 'Monto ($)',
            data: montos,
            backgroundColor: 'rgba(54, 162, 235, 0.7)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1,
            yAxisID: 'y'
        }, {
            label: 'Cantidad Ventas',
            data: cantidades,
            type: 'line',
            borderColor: 'rgba(255, 99, 132, 1)',
            backgroundColor: 'rgba(255, 99, 132, 0.2)',
            borderWidth: 2,
            pointRadius: 4,
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        interaction: {
            mode: 'index',
            intersect: false,
        },
        stacked: false,
        scales: {
            x: {
                grid: {
                    display: false
                }
            },
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                title: {
                    display: true,
                    text: 'Monto ($)'
                },
                ticks: {
                    callback: function(value) {
                        return '$' + value;
                    }
                }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                title: {
                    display: true,
                    text: 'Cantidad Ventas'
                },
                grid: {
                    drawOnChartArea: false,
                },
            }
        },
        plugins: {
            tooltip: {
                callbacks: {
                    label: function(context) {
                        let label = context.dataset.label || '';
                        if (label) {
                            label += ': ';
                        }
                        if (context.datasetIndex === 0) {
                            label += '$' + context.parsed.y.toFixed(2);
                        } else {
                            label += context.parsed.y + ' ventas';
                        }
                        return label;
                    }
                }
            }
        }
    }
});

// Actualizar automáticamente cada 60 segundos
setTimeout(function() {
    location.reload();
}, 60000);
</script>

<?php include '../../includes/footer.php'; ?>