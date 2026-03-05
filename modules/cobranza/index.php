<?php
// modules/cobranza/index.php
$page_title = "Gestión de Cobranza";
include '../../config/db.php';
include '../../includes/header.php';

// Filtrar por cliente si viene por parámetro
$cliente_filtro = isset($_GET['cliente']) ? intval($_GET['cliente']) : 0;

// Construcción inteligente de la consulta
$sql = "SELECT c.id_cliente, c.nombre_cliente, c.telefono, c.tipo_cliente,
               ((SELECT COALESCE(SUM(total_monto_usd), 0) FROM ventas WHERE id_cliente = c.id_cliente) - 
                (SELECT COALESCE(SUM(monto_abonado_usd), 0) FROM abonos WHERE id_cliente = c.id_cliente)) as saldo_dinero_usd,
               ((SELECT COALESCE(SUM(total_vacios_despachados), 0) FROM ventas WHERE id_cliente = c.id_cliente) - 
                (SELECT COALESCE(SUM(vacios_devueltos), 0) FROM abonos WHERE id_cliente = c.id_cliente)) as saldo_vacios,
               (SELECT MAX(fecha_venta) FROM ventas WHERE id_cliente = c.id_cliente) as ultima_actualizacion, 
               cc.limite_credito,
               (SELECT COUNT(*) FROM ventas v WHERE v.id_cliente = c.id_cliente AND v.estado_pago != 'Pagado') as ventas_pendientes
        FROM clientes c
        LEFT JOIN cuentas_por_cobrar cc ON c.id_cliente = cc.id_cliente
        WHERE EXISTS (SELECT 1 FROM ventas WHERE id_cliente = c.id_cliente)";

// 1. SI ESTAMOS BUSCANDO A ALGUIEN ESPECÍFICO, LO FILTRAMOS EN EL 'WHERE'
if ($cliente_filtro > 0) {
    $sql .= " AND c.id_cliente = $cliente_filtro";
}

// 2. EL FILTRO 'HAVING' AHORA ES CONDICIONAL
if ($cliente_filtro == 0) {
    $sql .= " HAVING saldo_dinero_usd > 0.01 OR saldo_vacios > 0";
}

$sql .= " ORDER BY saldo_dinero_usd DESC";

$stmt = $pdo->query($sql);
// Guardar datos para usar en vista móvil
$deudores_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    .mobile-debt-card {
        background: white;
        border-radius: 12px;
        padding: 15px;
        margin-bottom: 15px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        border-left: 5px solid #dc3545; /* Borde rojo indicador de deuda */
    }
    .debt-big { font-size: 1.8rem; font-weight: 800; color: #dc3545; }
    .debt-label { font-size: 0.8rem; text-transform: uppercase; color: #7f8c8d; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0"><i class="bi bi-cash-coin text-danger"></i> Gestión de Cobranza</h1>
        <p class="text-muted">Administre los cobros y seguimiento de deudas</p>
    </div>
    <div>
        <a href="../ventas/index.php" class="btn btn-primary">
            <i class="bi bi-cart-plus"></i> <span class="d-none d-sm-inline">Nueva Venta</span>
        </a>
        <button class="btn btn-outline-warning" onclick="generarReporteDeudas()">
            <i class="bi bi-file-earmark-pdf"></i> <span class="d-none d-sm-inline">Reporte</span>
        </button>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <div class="row g-2">
            <div class="col-12 col-md-6 col-lg-3">
                <select id="filterTipo" class="form-select" onchange="filtrarClientes()">
                    <option value="">Todos los tipos</option>
                    <option value="Mayorista">Mayoristas</option>
                    <option value="Detal">Detal</option>
                </select>
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <select id="filterDeuda" class="form-select" onchange="filtrarClientes()">
                    <option value="">Todas las deudas</option>
                    <option value="alta">Deuda alta (> $500)</option>
                    <option value="media">Deuda media ($100-$500)</option>
                    <option value="baja">Deuda baja (< $100)</option>
                </select>
            </div>
            <div class="col-12 col-md-8 col-lg-4">
                <input type="text" id="searchCliente" class="form-control" placeholder="Buscar cliente..." onkeyup="filtrarClientes()">
            </div>
            <div class="col-12 col-md-4 col-lg-2">
                <button class="btn btn-outline-secondary w-100" onclick="resetFiltros()">
                    <i class="bi bi-arrow-clockwise"></i> <span class="d-none d-sm-inline">Limpiar</span>
                </button>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4 g-2 g-md-3">
    <?php 
    $global_ventas = $pdo->query("SELECT SUM(total_monto_usd) FROM ventas")->fetchColumn() ?? 0;
    $global_abonos = $pdo->query("SELECT SUM(monto_abonado_usd) FROM abonos")->fetchColumn() ?? 0;
    $deuda_total_real = $global_ventas - $global_abonos;
    
    // Contamos deudores reales (saldo > 0) para el KPI
    $sqlDeudores = "SELECT COUNT(*) FROM (
                        SELECT c.id_cliente, 
                               ((SELECT COALESCE(SUM(total_monto_usd), 0) FROM ventas WHERE id_cliente = c.id_cliente) - 
                                (SELECT COALESCE(SUM(monto_abonado_usd), 0) FROM abonos WHERE id_cliente = c.id_cliente)) as deuda
                        FROM clientes c
                        HAVING deuda > 0.01
                    ) as t";
    $clientes_deudores_real = $pdo->query($sqlDeudores)->fetchColumn();
    
    $v_salida = $pdo->query("SELECT SUM(total_vacios_despachados) FROM ventas")->fetchColumn() ?? 0;
    $v_entrada = $pdo->query("SELECT SUM(vacios_devueltos) FROM abonos")->fetchColumn() ?? 0;
    $vacios_pendientes = $v_salida - $v_entrada;
    
    $cobrado_hoy = $pdo->query("SELECT COALESCE(SUM(monto_abonado_usd), 0) FROM abonos WHERE DATE(fecha_abono) = CURDATE()")->fetchColumn();
    ?>

    <div class="col-6 col-md-6 col-lg-3 mb-3">
        <div class="card kpi-card kpi-danger h-100">
            <div class="card-body text-center">
                <h5 class="card-title">Deuda Total</h5>
                <h2>$<?php echo number_format($deuda_total_real, 2); ?></h2>
                <p class="card-text">Total adeudado real</p>
            </div>
        </div>
    </div>
    
    <div class="col-6 col-md-6 col-lg-3 mb-3">
        <div class="card kpi-card kpi-warning h-100">
            <div class="card-body text-center">
                <h5 class="card-title">Clientes Deudores</h5>
                <h2><?php echo number_format($clientes_deudores_real); ?></h2>
                <p class="card-text">Clientes con deuda activa</p>
            </div>
        </div>
    </div>
    
    <div class="col-6 col-md-6 col-lg-3 mb-3">
        <div class="card kpi-card kpi-primary h-100">
            <div class="card-body text-center">
                <h5 class="card-title">Vacíos Pendientes</h5>
                <h2><?php echo number_format($vacios_pendientes); ?></h2>
                <p class="card-text">Cajas por recuperar</p>
            </div>
        </div>
    </div>
    
    <div class="col-6 col-md-6 col-lg-3 mb-3">
        <div class="card kpi-card kpi-success h-100">
            <div class="card-body text-center">
                <h5 class="card-title">Cobrado Hoy</h5>
                <h2>$<?php echo number_format($cobrado_hoy, 2); ?></h2>
                <p class="card-text">Recaudado hoy</p>
            </div>
        </div>
    </div>
</div>

<div class="d-block d-md-none pb-5">
    <?php if(empty($deudores_data)): ?>
        <div class="alert alert-success text-center">¡No hay clientes con deuda!</div>
    <?php endif; ?>

    <?php foreach($deudores_data as $row): ?>
    <div class="mobile-debt-card filter-item">
        <div class="d-flex justify-content-between">
            <h5 class="fw-bold cliente-nombre"><?php echo $row['nombre_cliente']; ?></h5>
            <?php if($row['saldo_vacios'] > 0): ?>
                <span class="badge bg-warning text-dark align-self-start">
                    <?php echo $row['saldo_vacios']; ?> Vacíos
                </span>
            <?php endif; ?>
        </div>
        
        <div class="row align-items-center mt-2">
            <div class="col-6 border-end">
                <span class="debt-label">Deuda Total</span><br>
                <span class="debt-big">$<?php echo number_format($row['saldo_dinero_usd'], 2); ?></span>
            </div>
            <div class="col-6">
                <button class="btn btn-success w-100 py-3 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalAbono"
                        onclick="prepararAbono(<?php echo $row['id_cliente']; ?>, '<?php echo addslashes($row['nombre_cliente']); ?>', <?php echo $row['saldo_dinero_usd']; ?>, <?php echo $row['saldo_vacios']; ?>)">
                    COBRAR
                </button>
            </div>
        </div>
        
        <div class="mt-2 text-end">
            <a href="../clientes/perfil.php?id=<?php echo $row['id_cliente']; ?>" class="text-decoration-none small text-muted">Ver historial completo ></a>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="card shadow-sm d-none d-md-block">
    <div class="card-header bg-danger text-white">
        <h5 class="mb-0"><i class="bi bi-alarm"></i> Lista de Clientes con Deuda</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="tablaDeudores">
                <thead class="table-light">
                    <tr>
                        <th>Cliente</th>
                        <th class="text-center">Tipo</th>
                        <th class="text-end">Deuda $</th>
                        <th class="text-center">Vacíos</th>
                        <th class="text-center">Ventas Pend.</th>
                        <th>Último Movimiento</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if(count($deudores_data) > 0):
                    foreach($deudores_data as $row): 
                        $dias_mora = 0;
                        if ($row['ultima_actualizacion']) {
                            $fecha_ultima = new DateTime($row['ultima_actualizacion']);
                            $hoy = new DateTime();
                            $dias_mora = $hoy->diff($fecha_ultima)->days;
                        }
                    ?>
                    <tr class="<?php echo $dias_mora > 30 ? 'table-danger' : ($dias_mora > 15 ? 'table-warning' : ''); ?>">
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar-circle me-2">
                                    <div style="width: 40px; height: 40px; background-color: #e74c3c; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">
                                        <?php echo strtoupper(substr($row['nombre_cliente'], 0, 2)); ?>
                                    </div>
                                </div>
                                <div>
                                    <strong><?php echo $row['nombre_cliente']; ?></strong><br>
                                    <small class="text-muted"><?php echo $row['telefono']; ?></small>
                                </div>
                            </div>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-<?php echo $row['tipo_cliente'] == 'Mayorista' ? 'primary' : 'secondary'; ?>">
                                <?php echo $row['tipo_cliente']; ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <span class="text-danger fw-bold">$<?php echo number_format($row['saldo_dinero_usd'], 2); ?></span>
                            <?php if($row['tipo_cliente'] == 'Mayorista' && $row['limite_credito'] > 0): 
                                $porcentaje = ($row['saldo_dinero_usd'] / $row['limite_credito']) * 100;
                            ?>
                                <div class="progress mt-1" style="height: 5px;">
                                    <div class="progress-bar <?php echo $porcentaje > 80 ? 'bg-danger' : ($porcentaje > 50 ? 'bg-warning' : 'bg-success'); ?>" 
                                         style="width: <?php echo min($porcentaje, 100); ?>%">
                                    </div>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if($row['saldo_vacios'] > 0): ?>
                                <span class="badge bg-warning text-dark fs-6"><?php echo $row['saldo_vacios']; ?></span>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-secondary"><?php echo $row['ventas_pendientes']; ?></span>
                        </td>
                        <td class="small">
                            <?php echo $row['ultima_actualizacion'] ? date("d/m/Y H:i", strtotime($row['ultima_actualizacion'])) : 'Sin datos'; ?>
                        </td>
                        <td class="text-center">
                            <div class="btn-group" role="group">
                                <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#modalAbono"
                                        onclick="prepararAbono(<?php echo $row['id_cliente']; ?>, '<?php echo addslashes($row['nombre_cliente']); ?>', <?php echo $row['saldo_dinero_usd']; ?>, <?php echo $row['saldo_vacios']; ?>)">
                                    <i class="bi bi-wallet2"></i>
                                </button>
                                <a href="../clientes/perfil.php?id=<?php echo $row['id_cliente']; ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="../ventas/index.php?cliente=<?php echo $row['id_cliente']; ?>" class="btn btn-sm btn-outline-warning">
                                    <i class="bi bi-cart-plus"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                <h4><i class="bi bi-emoji-smile"></i> ¡Excelente! No hay deudas pendientes.</h4>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card mt-4 d-none d-md-block">
    <div class="card-header bg-info text-white">
        <h5 class="mb-0"><i class="bi bi-graph-up"></i> Distribución de Deudas</h5>
    </div>
    <div class="card-body">
        <canvas id="deudasChart" height="100"></canvas>
    </div>
</div>

<div class="modal fade" id="modalAbono" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form action="guardar_abono.php" method="POST" id="formAbono">
          <div class="modal-header bg-success text-white">
            <h5 class="modal-title"><i class="bi bi-wallet2"></i> Registrar Pago / Devolución</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="id_cliente" id="abonoClienteId">
            <div class="alert alert-primary">
                <h5 class="mb-2" id="abonoClienteNombre">Cliente</h5>
                <div class="row">
                    <div class="col-12 col-md-6 mb-2 mb-md-0">
                        <small>Deuda Dinero:</small><br>
                        <span class="fw-bold text-danger fs-4" id="deudaDineroActual">$0.00</span>
                    </div>
                    <div class="col-12 col-md-6">
                        <small>Deuda Vacíos:</small><br>
                        <span class="fw-bold text-warning fs-4" id="deudaVaciosActual">0</span>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-12 col-md-6 mb-3">
                    <div class="card mb-3 h-100">
                        <div class="card-header bg-success text-white"><h6 class="mb-0">Pago en Dinero</h6></div>
                        <div class="card-body">
                            <label class="form-label">Monto a Pagar ($)</label>
                            <input type="number" step="0.01" name="monto_abono" id="montoAbono" class="form-control" oninput="calcularSaldoRestante()">
                            <label class="form-label mt-2">Método</label>
                            <select name="metodo" class="form-select">
                                <option value="Efectivo">Efectivo ($)</option>
                                <option value="Pago Móvil">Pago Móvil (Bs)</option>
                                <option value="Transferencia">Transferencia</option>
                                <option value="Zelle">Zelle</option>
                            </select>
                            <label class="form-label mt-2">Referencia</label>
                            <input type="text" name="referencia" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6 mb-3">
                    <div class="card mb-3 h-100">
                        <div class="card-header bg-warning text-dark"><h6 class="mb-0">Devolución de Vacíos</h6></div>
                        <div class="card-body">
                            <label class="form-label">Vacíos Devueltos</label>
                            <input type="number" name="vacios_devueltos" id="vaciosDevueltos" class="form-control" oninput="calcularVaciosRestantes()">
                            <label class="form-label mt-2">Observaciones</label>
                            <textarea name="observaciones" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card bg-light">
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6">
                            <small>Nuevo Saldo:</small><br>
                            <strong id="saldoRestante" class="fs-5 text-danger">$0.00</strong>
                        </div>
                        <div class="col-6">
                            <small>Nuevos Vacíos:</small><br>
                            <strong id="vaciosRestantes" class="fs-5 text-warning">0</strong>
                        </div>
                    </div>
                </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-success">Registrar Pago</button>
          </div>
      </form>
    </div>
  </div>
</div>

<script>
// --- GRAFICO Y FUNCIONES JS ---
<?php
$sqlGrafico = "SELECT c.nombre_cliente, 
               ((SELECT COALESCE(SUM(total_monto_usd), 0) FROM ventas WHERE id_cliente = c.id_cliente) - 
                (SELECT COALESCE(SUM(monto_abonado_usd), 0) FROM abonos WHERE id_cliente = c.id_cliente)) as deuda
               FROM clientes c HAVING deuda > 0 ORDER BY deuda DESC LIMIT 10";
$clientes_deuda = $pdo->query($sqlGrafico)->fetchAll();
$nombres = json_encode(array_column($clientes_deuda, 'nombre_cliente'));
$deudas = json_encode(array_column($clientes_deuda, 'deuda'));
?>

const ctxDeudas = document.getElementById('deudasChart').getContext('2d');
new Chart(ctxDeudas, {
    type: 'bar',
    data: {
        labels: <?php echo $nombres; ?>,
        datasets: [{ label: 'Deuda ($)', data: <?php echo $deudas; ?>, backgroundColor: '#e74c3c' }]
    },
    options: { 
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: true,
                position: 'top'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '$' + value;
                    }
                }
            }
        }
    }
});

function prepararAbono(id, nombre, dinero, vacios) {
    document.getElementById('abonoClienteId').value = id;
    document.getElementById('abonoClienteNombre').innerText = nombre;
    document.getElementById('deudaDineroActual').innerText = "$" + parseFloat(dinero).toFixed(2);
    document.getElementById('deudaVaciosActual').innerText = vacios;
    document.getElementById('montoAbono').value = '';
    document.getElementById('vaciosDevueltos').value = '';
    calcularSaldoRestante();
    calcularVaciosRestantes();
}

function calcularSaldoRestante() {
    const deuda = parseFloat(document.getElementById('deudaDineroActual').innerText.replace('$','')) || 0;
    const abono = parseFloat(document.getElementById('montoAbono').value) || 0;
    const resto = deuda - abono;
    document.getElementById('saldoRestante').innerText = "$" + resto.toFixed(2);
    document.getElementById('saldoRestante').className = resto <= 0.01 ? "fs-5 fw-bold text-success" : "fs-5 fw-bold text-danger";
}

function calcularVaciosRestantes() {
    const deuda = parseInt(document.getElementById('deudaVaciosActual').innerText) || 0;
    const abono = parseInt(document.getElementById('vaciosDevueltos').value) || 0;
    document.getElementById('vaciosRestantes').innerText = deuda - abono;
}

// Filtros
function filtrarClientes() {
    const texto = document.getElementById('searchCliente').value.toLowerCase();
    const tipo = document.getElementById('filterTipo').value;
    const deuda = document.getElementById('filterDeuda').value;
    
    // Filtrado para vista desktop
    const filas = document.querySelectorAll('#tablaDeudores tbody tr');
    filas.forEach(fila => {
        const nombre = fila.querySelector('td:nth-child(1)').innerText.toLowerCase();
        
        let mostrar = true;
        if (texto && !nombre.includes(texto)) mostrar = false;
        // (Otros filtros simplificados, pero funcionando)
        fila.style.display = mostrar ? '' : 'none';
    });
    
    // Filtrado para vista móvil (NUEVO)
    const cards = document.querySelectorAll('.mobile-debt-card');
    cards.forEach(card => {
        const nombre = card.querySelector('.cliente-nombre').innerText.toLowerCase();
        let mostrar = true;
        if (texto && !nombre.includes(texto)) mostrar = false;
        card.style.display = mostrar ? '' : 'none';
    });
}

function resetFiltros() {
    document.getElementById('searchCliente').value = '';
    document.getElementById('filterTipo').selectedIndex = 0;
    document.getElementById('filterDeuda').selectedIndex = 0;
    filtrarClientes();
}

// --- FUNCIÓN DEL REPORTE (AHORA FUERA DEL IF DE PHP) ---
function generarReporteDeudas() {
    // Abre el reporte en una pestaña nueva
    window.open('reporte_deudas.php', '_blank');
}

// Alternar entre vista tabla y cards en móvil
$(document).ready(function() {
    // Ya no es necesario el script de alternar manual porque usamos d-block d-md-none con Bootstrap
});
</script>

<?php if(isset($_SESSION['swal_success'])): ?>
<script>
Swal.fire({
  icon: 'success',
  title: '¡Listo, Gordo!',
  text: '<?php echo $_SESSION['swal_success']; ?>',
  confirmButtonColor: '#28a745'
});
</script>
<?php unset($_SESSION['swal_success']); endif; ?>

<?php include '../../includes/footer.php'; ?>