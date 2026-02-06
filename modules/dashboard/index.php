<?php
// modules/dashboard/index.php
$page_title = "Dashboard Principal";
include '../../config/db.php';
include '../../includes/header.php';

// --- LOGICA DE DATOS ---

// KPIs principales
$kpis = [];
$kpis['deuda_total'] = $pdo->query("SELECT SUM(saldo_dinero_usd) FROM cuentas_por_cobrar")->fetchColumn() ?? 0;
$kpis['vacios_pendientes'] = $pdo->query("SELECT SUM(saldo_vacios) FROM cuentas_por_cobrar")->fetchColumn() ?? 0;
$kpis['stock_bajo'] = $pdo->query("SELECT COUNT(*) FROM productos WHERE stock_lleno < 10")->fetchColumn();
$kpis['ventas_hoy'] = $pdo->query("SELECT COUNT(*) FROM ventas WHERE DATE(fecha_venta) = CURDATE()")->fetchColumn();
$kpis['monto_hoy'] = $pdo->query("SELECT COALESCE(SUM(total_monto_usd), 0) FROM ventas WHERE DATE(fecha_venta) = CURDATE()")->fetchColumn();
$kpis['clientes_deudores'] = $pdo->query("SELECT COUNT(DISTINCT id_cliente) FROM cuentas_por_cobrar WHERE saldo_dinero_usd > 0")->fetchColumn();

// 1. Ventas 7 días
$ventas_7dias = $pdo->query("
    SELECT DATE(fecha_venta) as fecha, 
           COUNT(*) as cantidad, 
           SUM(total_monto_usd) as monto
    FROM ventas 
    WHERE fecha_venta >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(fecha_venta)
    ORDER BY fecha ASC
")->fetchAll(PDO::FETCH_ASSOC);

// 2. Top Productos (FILTRADO: SIN DESCONTINUADOS)
$top_productos = $pdo->query("
    SELECT p.nombre_producto, SUM(dv.cantidad) as total_vendido
    FROM detalle_ventas dv
    JOIN productos p ON dv.id_producto = p.id_producto
    JOIN ventas v ON dv.id_venta = v.id_venta
    WHERE v.fecha_venta >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    AND p.nombre_producto NOT LIKE '%(DESCONTINUADO)%' 
    GROUP BY p.id_producto
    ORDER BY total_vendido DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Otros datos
// ÚNICO CAMBIO EN CONSULTA: Agregué c.id_cliente
$clientes_morosos = $pdo->query("SELECT c.id_cliente, c.nombre_cliente, cc.saldo_dinero_usd, cc.saldo_vacios FROM clientes c JOIN cuentas_por_cobrar cc ON c.id_cliente = cc.id_cliente WHERE cc.saldo_dinero_usd > 0 ORDER BY cc.saldo_dinero_usd DESC LIMIT 5")->fetchAll();
$alertas_stock = $pdo->query("SELECT nombre_producto, stock_lleno, precio_venta_usd FROM productos WHERE stock_lleno < 10 ORDER BY stock_lleno ASC LIMIT 5")->fetchAll();
$ultimas_ventas = $pdo->query("SELECT v.id_venta, c.nombre_cliente, v.total_monto_usd, v.fecha_venta, v.estado_pago FROM ventas v JOIN clientes c ON v.id_cliente = c.id_cliente ORDER BY v.fecha_venta DESC LIMIT 10")->fetchAll();
?>

<style>
    .kpi-card { height: 100%; } 
    .table td { vertical-align: middle; }
    .h-100 { height: 100% !important; }
</style>

<div class="row mb-4">
    <div class="col-12">
        <h1 class="h3 mb-0">
            <i class="bi bi-speedometer2 text-primary"></i> Dashboard Principal
        </h1>
        <p class="text-muted">Resumen completo del negocio en tiempo real</p>
    </div>
</div>

<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card kpi-card kpi-danger">
            <div class="card-body">
                <div class="kpi-icon"><i class="bi bi-cash-coin"></i></div>
                <h5 class="card-title">Deuda Total</h5>
                <h2>$<?php echo number_format($kpis['deuda_total'], 2); ?></h2>
                <p class="card-text"><?php echo $kpis['clientes_deudores']; ?> clientes morosos</p>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card kpi-card kpi-warning">
            <div class="card-body">
                <div class="kpi-icon"><i class="bi bi-box-seam"></i></div>
                <h5 class="card-title">Vacíos Pendientes</h5>
                <h2><?php echo number_format($kpis['vacios_pendientes']); ?></h2>
                <p class="card-text">Cajas por recuperar</p>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card kpi-card kpi-success">
            <div class="card-body">
                <div class="kpi-icon"><i class="bi bi-cart-check"></i></div>
                <h5 class="card-title">Ventas Hoy</h5>
                <h2>$<?php echo number_format($kpis['monto_hoy'], 2); ?></h2>
                <p class="card-text"><?php echo $kpis['ventas_hoy']; ?> transacciones</p>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card kpi-card kpi-purple">
            <div class="card-body">
                <div class="kpi-icon"><i class="bi bi-exclamation-triangle"></i></div>
                <h5 class="card-title">Stock Bajo</h5>
                <h2><?php echo $kpis['stock_bajo']; ?></h2>
                <p class="card-text">Productos con stock < 10</p>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-8 mb-3">
        <div class="card h-100">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-graph-up"></i> Ventas últimos 7 días</h5>
            </div>
            <div class="card-body">
                <canvas id="ventasChart" style="max-height: 300px;"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4 mb-3">
        <div class="card h-100">
            <div class="card-header bg-light">
                <h5 class="mb-0 text-muted fs-6"><i class="bi bi-table"></i> Datos Semanales</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 text-center align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Fecha</th>
                                <th>Cant.</th>
                                <th class="text-end">Monto</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($ventas_7dias)): ?>
                                <tr><td colspan="3" class="text-muted py-3">Sin datos</td></tr>
                            <?php else: ?>
                                <?php foreach($ventas_7dias as $dia): ?>
                                <tr>
                                    <td><?php echo date('d/m', strtotime($dia['fecha'])); ?></td>
                                    <td><span class="badge bg-secondary rounded-pill"><?php echo $dia['cantidad']; ?></span></td>
                                    <td class="text-end fw-bold text-success">$<?php echo number_format($dia['monto'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-5 mb-3">
        <div class="card h-100">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-pie-chart-fill"></i> Distribución Top 5 (Activos)</h5>
            </div>
            <div class="card-body d-flex justify-content-center align-items-center">
                <div style="width: 100%; max-width: 320px;">
                    <canvas id="topProductsChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-7 mb-3">
        <div class="card h-100">
            <div class="card-header bg-light">
                <h5 class="mb-0 text-muted fs-6"><i class="bi bi-list-ol"></i> Ranking (30 Días)</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width:10px">#</th>
                                <th>Producto</th>
                                <th class="text-center">Cajas</th>
                                <th class="text-center">%</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_top = 0;
                            foreach($top_productos as $p) { $total_top += $p['total_vendido']; }

                            foreach($top_productos as $index => $prod): 
                                $porc = ($total_top > 0) ? ($prod['total_vendido'] / $total_top) * 100 : 0;
                                $colores = ['#198754', '#20c997', '#ffc107', '#0dcaf0', '#6c757d'];
                                $color = $colores[$index % 5];
                            ?>
                            <tr>
                                <td>
                                    <span class="badge rounded-circle p-2" style="background-color: <?php echo $color; ?>; width: 25px; height: 25px; display: flex; align-items: center; justify-content: center;">
                                        <?php echo $index + 1; ?>
                                    </span>
                                </td>
                                <td class="fw-bold text-secondary"><?php echo $prod['nombre_producto']; ?></td>
                                <td class="text-center fs-5 fw-bold"><?php echo $prod['total_vendido']; ?></td>
                                <td class="text-center text-muted small"><?php echo number_format($porc, 1); ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="bi bi-alarm"></i> Clientes con Mayor Deuda</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Cliente</th>
                                <th class="text-end">Deuda</th>
                                <th class="text-end">Vacíos</th>
                                <th class="text-center">Acción</th> </tr>
                        </thead>
                        <tbody>
                            <?php foreach($clientes_morosos as $c): ?>
                            <tr>
                                <td><?php echo $c['nombre_cliente']; ?></td>
                                <td class="text-end text-danger fw-bold">$<?php echo number_format($c['saldo_dinero_usd'], 2); ?></td>
                                <td class="text-end"><span class="badge bg-warning text-dark"><?php echo $c['saldo_vacios']; ?></span></td>
                                <td class="text-center">
                                    <a href="../cobranza/index.php?id_cliente=<?php echo $c['id_cliente']; ?>" class="btn btn-sm btn-outline-danger" title="Cobrar">
                                        <i class="bi bi-cash-stack"></i>
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
    
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Alertas de Stock Bajo</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light"><tr><th>Producto</th><th class="text-center">Stock</th><th class="text-end">Acción</th></tr></thead>
                        <tbody>
                            <?php foreach($alertas_stock as $p): ?>
                            <tr>
                                <td><?php echo str_replace('(DESCONTINUADO)', '<small class="text-danger">(Desc.)</small>', $p['nombre_producto']); ?></td>
                                <td class="text-center"><span class="badge bg-danger"><?php echo $p['stock_lleno']; ?></span></td>
                                <td class="text-end"><a href="../inventario/index.php" class="btn btn-sm btn-outline-dark"><i class="bi bi-arrow-right"></i></a></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bi bi-clock-history"></i> Últimas Ventas</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table datatable align-middle">
                        <thead>
                            <tr><th>ID</th><th>Cliente</th><th>Fecha</th><th class="text-end">Monto</th><th>Estado</th><th>Acciones</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach($ultimas_ventas as $v): ?>
                            <tr>
                                <td>#<?php echo str_pad($v['id_venta'], 5, '0', STR_PAD_LEFT); ?></td>
                                <td><?php echo $v['nombre_cliente']; ?></td>
                                <td><?php echo date('d/m H:i', strtotime($v['fecha_venta'])); ?></td>
                                <td class="text-end fw-bold">$<?php echo number_format($v['total_monto_usd'], 2); ?></td>
                                <td>
                                    <?php if($v['estado_pago'] == 'Pagado'): ?><span class="badge bg-success">Pagado</span>
                                    <?php elseif($v['estado_pago'] == 'Pendiente'): ?><span class="badge bg-danger">Pendiente</span>
                                    <?php else: ?><span class="badge bg-warning text-dark">Abonado</span><?php endif; ?>
                                </td>
                                <td><a href="../ventas/comprobante.php?id=<?php echo $v['id_venta']; ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-receipt"></i></a></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// --- GRAFICO 1: MIXTO (BARRAS + LINEA) ---
const ctx = document.getElementById('ventasChart').getContext('2d');
const fechas = <?php echo json_encode(array_column($ventas_7dias, 'fecha')); ?>;
const montos = <?php echo json_encode(array_column($ventas_7dias, 'monto')); ?>;
const cantidades = <?php echo json_encode(array_column($ventas_7dias, 'cantidad')); ?>;

const fechasFmt = fechas.map(f => { let d = new Date(f); return d.getDate() + '/' + (d.getMonth()+1); });

new Chart(ctx, {
    type: 'bar',
    data: {
        labels: fechasFmt,
        datasets: [
            {
                label: 'Monto ($)',
                data: montos,
                backgroundColor: 'rgba(13, 110, 253, 0.6)', 
                borderColor: 'rgba(13, 110, 253, 1)',
                borderWidth: 1,
                order: 2,
                yAxisID: 'y'
            },
            {
                label: 'Cant. Ventas',
                data: cantidades,
                type: 'line', 
                borderColor: '#dc3545',
                backgroundColor: '#dc3545',
                borderWidth: 3,
                pointBackgroundColor: '#fff',
                pointBorderColor: '#dc3545',
                pointRadius: 5,
                tension: 0.3,
                order: 1,
                yAxisID: 'y1' 
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        scales: {
            y: {
                type: 'linear', display: true, position: 'left',
                title: { display: true, text: 'Dinero ($)' },
                grid: { borderDash: [2,2] }
            },
            y1: {
                type: 'linear', display: true, position: 'right',
                title: { display: true, text: 'Transacciones' },
                grid: { drawOnChartArea: false }, 
                ticks: { stepSize: 1 }
            },
            x: { grid: { display: false } }
        }
    }
});

// --- GRAFICO 2: TORTA (TOP PRODUCTOS) ---
const ctxPie = document.getElementById('topProductsChart').getContext('2d');
const labelsPie = <?php echo json_encode(array_column($top_productos, 'nombre_producto')); ?>;
const dataPie = <?php echo json_encode(array_column($top_productos, 'total_vendido')); ?>;

new Chart(ctxPie, {
    type: 'doughnut',
    data: {
        labels: labelsPie,
        datasets: [{
            data: dataPie,
            backgroundColor: ['#198754', '#20c997', '#ffc107', '#0dcaf0', '#6c757d'],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false } 
        }
    }
});

setTimeout(() => location.reload(), 60000);
</script>

<?php include '../../includes/footer.php'; ?>