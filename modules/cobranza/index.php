<?php
// modules/cobranza/index.php
$page_title = "Gestión de Cobranza";
include '../../config/db.php';
include '../../includes/header.php';

// Filtrar por cliente si viene por parámetro
$cliente_filtro = isset($_GET['cliente']) ? intval($_GET['cliente']) : 0;

// Consulta: Traer clientes que deban algo
$sql = "SELECT c.id_cliente, c.nombre_cliente, c.telefono, c.tipo_cliente,
               cc.saldo_dinero_usd, cc.saldo_vacios, cc.ultima_actualizacion, cc.limite_credito,
               (SELECT COUNT(*) FROM ventas v WHERE v.id_cliente = c.id_cliente AND v.estado_pago != 'Pagado') as ventas_pendientes
        FROM clientes c
        JOIN cuentas_por_cobrar cc ON c.id_cliente = cc.id_cliente
        WHERE (cc.saldo_dinero_usd > 0 OR cc.saldo_vacios > 0)";
        
if ($cliente_filtro > 0) {
    $sql .= " AND c.id_cliente = $cliente_filtro";
}

$sql .= " ORDER BY cc.saldo_dinero_usd DESC";

$stmt = $pdo->query($sql);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0"><i class="bi bi-cash-coin text-danger"></i> Gestión de Cobranza</h1>
        <p class="text-muted">Administre los cobros y seguimiento de deudas</p>
    </div>
    <div>
        <a href="../ventas/index.php" class="btn btn-primary">
            <i class="bi bi-cart-plus"></i> Nueva Venta
        </a>
        <button class="btn btn-outline-warning" onclick="generarReporteDeudas()">
            <i class="bi bi-file-earmark-pdf"></i> Reporte
        </button>
    </div>
</div>

<!-- Filtros -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row g-2">
            <div class="col-md-3">
                <select id="filterTipo" class="form-select" onchange="filtrarClientes()">
                    <option value="">Todos los tipos</option>
                    <option value="Mayorista">Mayoristas</option>
                    <option value="Detal">Detal</option>
                </select>
            </div>
            <div class="col-md-3">
                <select id="filterDeuda" class="form-select" onchange="filtrarClientes()">
                    <option value="">Todas las deudas</option>
                    <option value="alta">Deuda alta (> $500)</option>
                    <option value="media">Deuda media ($100-$500)</option>
                    <option value="baja">Deuda baja (< $100)</option>
                </select>
            </div>
            <div class="col-md-4">
                <input type="text" id="searchCliente" class="form-control" placeholder="Buscar cliente..." onkeyup="filtrarClientes()">
            </div>
            <div class="col-md-2">
                <button class="btn btn-outline-secondary w-100" onclick="resetFiltros()">
                    <i class="bi bi-arrow-clockwise"></i> Limpiar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Resumen de deudas -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card kpi-card kpi-danger">
            <div class="card-body text-center">
                <h5 class="card-title">Deuda Total</h5>
                <?php 
                $deuda_total = $pdo->query("SELECT SUM(saldo_dinero_usd) as total FROM cuentas_por_cobrar WHERE saldo_dinero_usd > 0")->fetchColumn() ?? 0;
                ?>
                <h2>$<?php echo number_format($deuda_total, 2); ?></h2>
                <p class="card-text">Total adeudado por clientes</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card kpi-card kpi-warning">
            <div class="card-body text-center">
                <h5 class="card-title">Clientes Deudores</h5>
                <?php 
                $clientes_deudores = $pdo->query("SELECT COUNT(DISTINCT id_cliente) as total FROM cuentas_por_cobrar WHERE saldo_dinero_usd > 0")->fetchColumn() ?? 0;
                ?>
                <h2><?php echo number_format($clientes_deudores); ?></h2>
                <p class="card-text">Clientes con deuda activa</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card kpi-card kpi-primary">
            <div class="card-body text-center">
                <h5 class="card-title">Vacíos Pendientes</h5>
                <?php 
                $vacios_pendientes = $pdo->query("SELECT SUM(saldo_vacios) as total FROM cuentas_por_cobrar WHERE saldo_vacios > 0")->fetchColumn() ?? 0;
                ?>
                <h2><?php echo number_format($vacios_pendientes); ?></h2>
                <p class="card-text">Cajas por recuperar</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card kpi-card kpi-success">
            <div class="card-body text-center">
                <h5 class="card-title">Cobrado Hoy</h5>
                <?php 
                $cobrado_hoy = $pdo->query("SELECT COALESCE(SUM(monto_abonado_usd), 0) as total FROM abonos WHERE DATE(fecha_abono) = CURDATE()")->fetchColumn();
                ?>
                <h2>$<?php echo number_format($cobrado_hoy, 2); ?></h2>
                <p class="card-text">Recaudado en el día</p>
            </div>
        </div>
    </div>
</div>

<!-- Tabla de clientes deudores -->
<div class="card shadow-sm">
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
                    <?php while($row = $stmt->fetch(PDO::FETCH_ASSOC)): 
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
                            <?php if($dias_mora > 0): ?>
                                <br>
                                <small class="text-<?php echo $dias_mora > 30 ? 'danger' : ($dias_mora > 15 ? 'warning' : 'muted'); ?>">
                                    <?php echo $dias_mora; ?> días
                                </small>
                            <?php endif; ?>
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
                                <small class="text-muted">Límite: $<?php echo number_format($row['limite_credito'], 2); ?></small>
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
                            <?php if($row['ventas_pendientes'] > 0): ?>
                                <span class="badge bg-danger"><?php echo $row['ventas_pendientes']; ?></span>
                            <?php else: ?>
                                <span class="text-muted">0</span>
                            <?php endif; ?>
                        </td>
                        <td class="small">
                            <?php echo date("d/m/Y", strtotime($row['ultima_actualizacion'])); ?><br>
                            <span class="text-muted"><?php echo date("H:i", strtotime($row['ultima_actualizacion'])); ?></span>
                        </td>
                        <td class="text-center">
                            <div class="btn-group" role="group">
                                <button class="btn btn-sm btn-success" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#modalAbono"
                                        onclick="prepararAbono(<?php echo $row['id_cliente']; ?>, '<?php echo addslashes($row['nombre_cliente']); ?>', <?php echo $row['saldo_dinero_usd']; ?>, <?php echo $row['saldo_vacios']; ?>)"
                                        title="Registrar pago">
                                    <i class="bi bi-wallet2"></i>
                                </button>
                                <a href="../clientes/perfil.php?id=<?php echo $row['id_cliente']; ?>" 
                                   class="btn btn-sm btn-outline-primary"
                                   title="Ver perfil">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="../ventas/index.php?cliente=<?php echo $row['id_cliente']; ?>" 
                                   class="btn btn-sm btn-outline-warning"
                                   title="Nueva venta">
                                    <i class="bi bi-cart-plus"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    
                    <?php if($stmt->rowCount() == 0): ?>
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

<!-- Gráfico de deudas por cliente -->
<div class="card mt-4">
    <div class="card-header bg-info text-white">
        <h5 class="mb-0"><i class="bi bi-graph-up"></i> Distribución de Deudas</h5>
    </div>
    <div class="card-body">
        <canvas id="deudasChart" height="100"></canvas>
    </div>
</div>

<!-- Modal para registrar abono -->
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
                    <div class="col-md-6">
                        <small>Deuda Dinero:</small><br>
                        <span class="fw-bold text-danger fs-4" id="deudaDineroActual">$0.00</span>
                    </div>
                    <div class="col-md-6">
                        <small>Deuda Vacíos:</small><br>
                        <span class="fw-bold text-warning fs-4" id="deudaVaciosActual">0</span>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-header bg-success text-white">
                            <h6 class="mb-0"><i class="bi bi-cash-coin"></i> Pago en Dinero</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Monto a Pagar ($)</label>
                                <input type="number" step="0.01" name="monto_abono" id="montoAbono" 
                                       class="form-control" placeholder="0.00" 
                                       oninput="calcularSaldoRestante()">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Método de Pago</label>
                                <select name="metodo" class="form-select">
                                    <option value="Efectivo">Efectivo ($)</option>
                                    <option value="Pago Móvil">Pago Móvil (Bs)</option>
                                    <option value="Transferencia">Transferencia Bancaria</option>
                                    <option value="Zelle">Zelle</option>
                                    <option value="Cheque">Cheque</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Referencia / N° de Comprobante</label>
                                <input type="text" name="referencia" class="form-control" 
                                       placeholder="Ej: TRANSFER-001, PM-123456">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-header bg-warning text-dark">
                            <h6 class="mb-0"><i class="bi bi-box-seam"></i> Devolución de Vacíos</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Vacíos Devueltos</label>
                                <input type="number" name="vacios_devueltos" id="vaciosDevueltos" 
                                       class="form-control" placeholder="0" 
                                       oninput="calcularVaciosRestantes()">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Estado de los Vacíos</label>
                                <select name="estado_vacios" class="form-select">
                                    <option value="BUENO">Buen estado</option>
                                    <option value="REGULAR">Estado regular</option>
                                    <option value="MALO">Mal estado / Roto</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Observaciones</label>
                                <textarea name="observaciones" class="form-control" rows="2" 
                                          placeholder="Observaciones sobre la devolución..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Resumen -->
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0"><i class="bi bi-calculator"></i> Resumen del Pago</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 text-center">
                            <small>Saldo después del pago:</small><br>
                            <span id="saldoRestante" class="fw-bold fs-4 text-danger">$0.00</span>
                        </div>
                        <div class="col-md-4 text-center">
                            <small>Vacios después de devolución:</small><br>
                            <span id="vaciosRestantes" class="fw-bold fs-4 text-warning">0</span>
                        </div>
                        <div class="col-md-4 text-center">
                            <small>Fecha del pago:</small><br>
                            <span class="fw-bold"><?php echo date('d/m/Y'); ?></span>
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

<!-- Modal para ver historial de pagos -->
<div class="modal fade" id="modalHistorial" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <!-- Se carga dinámicamente -->
    </div>
  </div>
</div>

<script>
// Datos para el gráfico
<?php
$clientes_deuda = $pdo->query("
    SELECT c.nombre_cliente, cc.saldo_dinero_usd
    FROM clientes c
    JOIN cuentas_por_cobrar cc ON c.id_cliente = cc.id_cliente
    WHERE cc.saldo_dinero_usd > 0
    ORDER BY cc.saldo_dinero_usd DESC
    LIMIT 10
")->fetchAll();

$nombres = json_encode(array_column($clientes_deuda, 'nombre_cliente'));
$deudas = json_encode(array_column($clientes_deuda, 'saldo_dinero_usd'));
?>

// Gráfico de deudas
const ctxDeudas = document.getElementById('deudasChart').getContext('2d');
const deudasChart = new Chart(ctxDeudas, {
    type: 'bar',
    data: {
        labels: <?php echo $nombres; ?>,
        datasets: [{
            label: 'Deuda ($)',
            data: <?php echo $deudas; ?>,
            backgroundColor: 'rgba(231, 76, 60, 0.7)',
            borderColor: 'rgba(231, 76, 60, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '$' + value;
                    }
                }
            },
            x: {
                ticks: {
                    maxRotation: 45,
                    minRotation: 45
                }
            }
        },
        plugins: {
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return 'Deuda: $' + context.parsed.y.toFixed(2);
                    }
                }
            }
        }
    }
});

// Función para preparar el modal de abono
function prepararAbono(id, nombre, dinero, vacios) {
    document.getElementById('abonoClienteId').value = id;
    document.getElementById('abonoClienteNombre').innerText = nombre;
    document.getElementById('deudaDineroActual').innerText = "$" + parseFloat(dinero).toFixed(2);
    document.getElementById('deudaVaciosActual').innerText = vacios;
    
    // Resetear valores
    document.getElementById('montoAbono').value = '';
    document.getElementById('vaciosDevueltos').value = '';
    
    // Calcular valores iniciales
    calcularSaldoRestante();
    calcularVaciosRestantes();
}

// Función para calcular saldo restante
function calcularSaldoRestante() {
    const deudaActual = parseFloat(document.getElementById('deudaDineroActual').textContent.replace('$', '')) || 0;
    const montoAbono = parseFloat(document.getElementById('montoAbono').value) || 0;
    const saldoRestante = deudaActual - montoAbono;
    
    const elemento = document.getElementById('saldoRestante');
    elemento.textContent = "$" + saldoRestante.toFixed(2);
    
    if (saldoRestante <= 0) {
        elemento.className = "fw-bold fs-4 text-success";
    } else if (saldoRestante <= (deudaActual * 0.3)) {
        elemento.className = "fw-bold fs-4 text-warning";
    } else {
        elemento.className = "fw-bold fs-4 text-danger";
    }
}

// Función para calcular vacíos restantes
function calcularVaciosRestantes() {
    const vaciosActual = parseInt(document.getElementById('deudaVaciosActual').textContent) || 0;
    const vaciosDevueltos = parseInt(document.getElementById('vaciosDevueltos').value) || 0;
    const vaciosRestantes = vaciosActual - vaciosDevueltos;
    
    const elemento = document.getElementById('vaciosRestantes');
    elemento.textContent = vaciosRestantes;
    
    if (vaciosRestantes <= 0) {
        elemento.className = "fw-bold fs-4 text-success";
    } else if (vaciosRestantes <= (vaciosActual * 0.3)) {
        elemento.className = "fw-bold fs-4 text-warning";
    } else {
        elemento.className = "fw-bold fs-4 text-danger";
    }
}

// Función para filtrar clientes
function filtrarClientes() {
    const tipo = $('#filterTipo').val();
    const deuda = $('#filterDeuda').val();
    const search = $('#searchCliente').val().toLowerCase();
    
    $('#tablaDeudores tbody tr').each(function() {
        const tipoCliente = $(this).find('td:nth-child(2) span.badge').text();
        const montoDeuda = parseFloat($(this).find('td:nth-child(3) span').text().replace('$', '').replace(',', ''));
        const nombreCliente = $(this).find('td:nth-child(1) strong').text().toLowerCase();
        
        let show = true;
        
        // Filtro por tipo
        if (tipo && tipoCliente !== tipo) show = false;
        
        // Filtro por monto de deuda
        if (deuda === 'alta' && montoDeuda <= 500) show = false;
        if (deuda === 'media' && (montoDeuda <= 100 || montoDeuda > 500)) show = false;
        if (deuda === 'baja' && montoDeuda >= 100) show = false;
        
        // Filtro por búsqueda
        if (search && !nombreCliente.includes(search)) show = false;
        
        if (show) {
            $(this).show();
        } else {
            $(this).hide();
        }
    });
}

// Función para resetear filtros
function resetFiltros() {
    $('#filterTipo').val('');
    $('#filterDeuda').val('');
    $('#searchCliente').val('');
    $('#tablaDeudores tbody tr').show();
}

// Función para generar reporte de deudas
function generarReporteDeudas() {
    window.open('reporte_deudas.php', '_blank');
}

// Validar formulario de abono
document.getElementById('formAbono').addEventListener('submit', function(e) {
    const montoAbono = parseFloat(document.getElementById('montoAbono').value) || 0;
    const vaciosDevueltos = parseInt(document.getElementById('vaciosDevueltos').value) || 0;
    
    if (montoAbono <= 0 && vaciosDevueltos <= 0) {
        e.preventDefault();
        alert('Debe registrar al menos un pago o devolución de vacíos.');
        return;
    }
    
    const deudaActual = parseFloat(document.getElementById('deudaDineroActual').textContent.replace('$', '')) || 0;
    if (montoAbono > deudaActual) {
        if (!confirm('El monto a pagar es mayor que la deuda actual. ¿Desea continuar?')) {
            e.preventDefault();
        }
    }
});
</script>

<?php include '../../includes/footer.php'; ?>