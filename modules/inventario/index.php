<?php
// modules/inventario/index.php
$page_title = "Gestión de Inventario";
include '../../config/db.php';
include '../../includes/header.php';

// Consultar productos
$query = "SELECT * FROM productos ORDER BY stock_lleno ASC, nombre_producto ASC";
$stmt = $pdo->query($query);

// Estadísticas
$estadisticas = $pdo->query("
    SELECT 
        COUNT(*) as total_productos,
        SUM(stock_lleno) as total_stock_lleno,
        SUM(stock_vacio) as total_stock_vacio,
        SUM(stock_lleno * precio_venta_usd) as valor_inventario,
        SUM(CASE WHEN stock_lleno < 10 THEN 1 ELSE 0 END) as productos_bajo_stock,
        SUM(CASE WHEN stock_lleno = 0 THEN 1 ELSE 0 END) as productos_sin_stock
    FROM productos
")->fetch(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0"><i class="bi bi-box-seam-fill text-primary"></i> Gestión de Inventario</h1>
        <p class="text-muted">Control de stock y productos disponibles</p>
    </div>
    <div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalProducto">
            <i class="bi bi-plus-lg"></i> Agregar Producto
        </button>
        <button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalAjuste">
            <i class="bi bi-arrow-repeat"></i> Ajustar Stock
        </button>
        <button class="btn btn-outline-success" onclick="generarReporteInventario()">
            <i class="bi bi-file-earmark-excel"></i> Exportar
        </button>
    </div>
</div>

<!-- Estadísticas -->
<div class="row mb-4">
    <div class="col-xl-2 col-md-4 mb-3">
        <div class="card border-start border-primary border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h5 class="text-muted">Productos</h5>
                        <h3 class="mb-0"><?php echo number_format($estadisticas['total_productos']); ?></h3>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-box text-primary fs-3"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-2 col-md-4 mb-3">
        <div class="card border-start border-success border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h5 class="text-muted">Stock Lleno</h5>
                        <h3 class="mb-0"><?php echo number_format($estadisticas['total_stock_lleno']); ?></h3>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-check-circle text-success fs-3"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-2 col-md-4 mb-3">
        <div class="card border-start border-warning border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h5 class="text-muted">Stock Vacío</h5>
                        <h3 class="mb-0"><?php echo number_format($estadisticas['total_stock_vacio']); ?></h3>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-box-seam text-warning fs-3"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-start border-info border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h5 class="text-muted">Valor Inventario</h5>
                        <h3 class="mb-0">$<?php echo number_format($estadisticas['valor_inventario'], 2); ?></h3>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-currency-dollar text-info fs-3"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-start border-danger border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h5 class="text-muted">Bajo Stock</h5>
                        <h3 class="mb-0"><?php echo number_format($estadisticas['productos_bajo_stock']); ?></h3>
                        <small class="text-danger">
                            <?php echo number_format($estadisticas['productos_sin_stock']); ?> sin stock
                        </small>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-exclamation-triangle text-danger fs-3"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Alertas de stock bajo -->
<?php if($estadisticas['productos_bajo_stock'] > 0): ?>
<div class="alert alert-warning alert-custom mb-4">
    <div class="d-flex align-items-center">
        <i class="bi bi-exclamation-triangle fs-4 me-3"></i>
        <div>
            <h5 class="alert-heading">¡Atención! Productos con stock bajo</h5>
            <p class="mb-1">
                Hay <strong><?php echo $estadisticas['productos_bajo_stock']; ?> productos</strong> con stock menor a 10 unidades,
                incluyendo <strong><?php echo $estadisticas['productos_sin_stock']; ?> productos</strong> sin stock disponible.
            </p>
            <small>Considere realizar un pedido de reposición.</small>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Tabla de inventario -->
<div class="card shadow-sm">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="bi bi-list-columns"></i> Lista de Productos</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover datatable">
                <thead class="table-light">
                    <tr>
                        <th>Producto</th>
                        <th class="text-center">Tipo</th>
                        <th class="text-end">Precio ($)</th>
                        <th class="text-center">Stock Lleno</th>
                        <th class="text-center">Stock Vacío</th>
                        <th class="text-end">Valor Stock</th>
                        <th class="text-center">Estado</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $stmt->fetch(PDO::FETCH_ASSOC)): 
                        $valor_stock = $row['stock_lleno'] * $row['precio_venta_usd'];
                        $estado_stock = '';
                        $color_estado = '';
                        
                        if ($row['stock_lleno'] == 0) {
                            $estado_stock = 'Sin Stock';
                            $color_estado = 'danger';
                        } elseif ($row['stock_lleno'] < 5) {
                            $estado_stock = 'Muy Bajo';
                            $color_estado = 'danger';
                        } elseif ($row['stock_lleno'] < 10) {
                            $estado_stock = 'Bajo';
                            $color_estado = 'warning';
                        } elseif ($row['stock_lleno'] < 20) {
                            $estado_stock = 'Normal';
                            $color_estado = 'info';
                        } else {
                            $estado_stock = 'Óptimo';
                            $color_estado = 'success';
                        }
                    ?>
                    <tr class="<?php echo $row['stock_lleno'] == 0 ? 'table-danger' : ($row['stock_lleno'] < 5 ? 'table-warning' : ''); ?>">
                        <td>
                            <strong><?php echo $row['nombre_producto']; ?></strong><br>
                            <small class="text-muted"><?php echo $row['descripcion']; ?></small>
                        </td>
                        <td class="text-center">
                            <?php if($row['es_retornable']): ?>
                                <span class="badge bg-info">Retornable</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Desechable</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end fw-bold text-success">$<?php echo number_format($row['precio_venta_usd'], 2); ?></td>
                        <td class="text-center">
                            <span class="fw-bold <?php echo $row['stock_lleno'] < 10 ? 'text-danger' : 'text-success'; ?>">
                                <?php echo number_format($row['stock_lleno']); ?>
                            </span>
                            <small class="text-muted">cajas</small>
                        </td>
                        <td class="text-center">
                            <?php if($row['es_retornable']): ?>
                                <span class="fw-bold text-warning"><?php echo number_format($row['stock_vacio']); ?></span>
                                <small class="text-muted">vacíos</small>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end fw-bold">$<?php echo number_format($valor_stock, 2); ?></td>
                        <td class="text-center">
                            <span class="badge bg-<?php echo $color_estado; ?>"><?php echo $estado_stock; ?></span>
                        </td>
                        <td class="text-center">
                            <div class="btn-group btn-group-sm" role="group">
                                <button type="button" class="btn btn-outline-primary" 
                                        data-bs-toggle="tooltip" title="Editar"
                                        onclick="editarProducto(<?php echo $row['id_producto']; ?>)">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button type="button" class="btn btn-outline-warning" 
                                        data-bs-toggle="tooltip" title="Ajustar Stock"
                                        onclick="ajustarStock(<?php echo $row['id_producto']; ?>, '<?php echo addslashes($row['nombre_producto']); ?>', <?php echo $row['stock_lleno']; ?>, <?php echo $row['stock_vacio']; ?>)">
                                    <i class="bi bi-arrow-repeat"></i>
                                </button>
                                <button type="button" class="btn btn-outline-danger" 
                                        data-bs-toggle="tooltip" title="Eliminar"
                                        onclick="eliminarProducto(<?php echo $row['id_producto']; ?>, '<?php echo addslashes($row['nombre_producto']); ?>')">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Agregar Producto -->
<div class="modal fade" id="modalProducto" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form action="guardar.php" method="POST" id="formProducto">
          <div class="modal-header bg-primary text-white">
            <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Agregar Nuevo Producto</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row">
                <div class="col-md-8 mb-3">
                    <label class="form-label">Nombre del Producto <span class="text-danger">*</span></label>
                    <input type="text" name="nombre" class="form-control" required 
                           placeholder="Ej: Polar Pilsen 36 Und, Polar Light 24 Und">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Tipo de Envase <span class="text-danger">*</span></label>
                    <select name="retornable" class="form-select" required>
                        <option value="1">Retornable (Pide Vacío)</option>
                        <option value="0">Desechable (No pide Vacío)</option>
                    </select>
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Descripción (Opcional)</label>
                <textarea name="descripcion" class="form-control" rows="2" 
                          placeholder="Detalles adicionales del producto..."></textarea>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Precio de Venta ($) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" name="precio" class="form-control" required 
                           placeholder="0.00" min="0">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Costo ($) (Opcional)</label>
                    <input type="number" step="0.01" name="costo" class="form-control" 
                           placeholder="0.00" min="0">
                </div>
            </div>
            
            <hr>
            <h6>Inventario Inicial</h6>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label text-success">Stock Lleno (Inicial)</label>
                    <input type="number" name="stock_lleno" class="form-control" value="0" min="0">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label text-warning">Stock Vacío (Inicial)</label>
                    <input type="number" name="stock_vacio" class="form-control" value="0" min="0">
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Stock Mínimo</label>
                    <input type="number" name="stock_minimo" class="form-control" value="10" min="0">
                    <small class="text-muted">Se generará alerta cuando el stock esté por debajo de este valor</small>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Stock Máximo</label>
                    <input type="number" name="stock_maximo" class="form-control" value="100" min="0">
                    <small class="text-muted">Cantidad máxima recomendada en inventario</small>
                </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary">Guardar Producto</button>
          </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Ajustar Stock -->
<div class="modal fade" id="modalAjuste" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form action="ajustar_stock.php" method="POST" id="formAjuste">
          <input type="hidden" name="id_producto" id="ajusteProductoId">
          
          <div class="modal-header bg-warning text-dark">
            <h5 class="modal-title"><i class="bi bi-arrow-repeat"></i> Ajustar Stock</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3 text-center">
                <h5 id="ajusteProductoNombre">Producto</h5>
                <div class="row">
                    <div class="col-6">
                        <small>Stock Lleno Actual:</small><br>
                        <span id="stockLlenoActual" class="fw-bold">0</span>
                    </div>
                    <div class="col-6">
                        <small>Stock Vacío Actual:</small><br>
                        <span id="stockVacioActual" class="fw-bold">0</span>
                    </div>
                </div>
            </div>
            
            <hr>
            
            <div class="mb-3">
                <label class="form-label">Tipo de Ajuste</label>
                <select name="tipo_ajuste" class="form-select" onchange="mostrarCamposAjuste()">
                    <option value="ENTRADA">Entrada de Mercancía</option>
                    <option value="SALIDA">Salida por Ajuste</option>
                    <option value="TRANSFERENCIA">Transferencia Lleno-Vacío</option>
                    <option value="DANADO">Producto Dañado</option>
                    <option value="FISICO">Conteo Físico</option>
                </select>
            </div>
            
            <div id="camposEntrada" class="ajuste-campos">
                <div class="mb-3">
                    <label class="form-label">Cantidad a Agregar (Stock Lleno)</label>
                    <input type="number" name="cantidad_lleno_entrada" class="form-control" min="0" value="0">
                </div>
                <div class="mb-3">
                    <label class="form-label">Cantidad Vacíos Recibidos</label>
                    <input type="number" name="cantidad_vacio_entrada" class="form-control" min="0" value="0">
                </div>
            </div>
            
            <div id="camposSalida" class="ajuste-campos d-none">
                <div class="mb-3">
                    <label class="form-label">Cantidad a Retirar (Stock Lleno)</label>
                    <input type="number" name="cantidad_lleno_salida" class="form-control" min="0" value="0">
                </div>
                <div class="mb-3">
                    <label class="form-label">Cantidad Vacíos Retirados</label>
                    <input type="number" name="cantidad_vacio_salida" class="form-control" min="0" value="0">
                </div>
            </div>
            
            <div id="camposTransferencia" class="ajuste-campos d-none">
                <div class="mb-3">
                    <label class="form-label">Vaciar Stock Lleno</label>
                    <input type="number" name="cantidad_lleno_a_vacio" class="form-control" min="0" value="0">
                    <small class="text-muted">Convierte stock lleno en stock vacío</small>
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Motivo del Ajuste</label>
                <textarea name="motivo" class="form-control" rows="2" required 
                          placeholder="Ej: Reposición de inventario, Ajuste por conteo físico..."></textarea>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Responsable</label>
                <input type="text" name="responsable" class="form-control" 
                       value="<?php echo $_SESSION['usuario'] ?? 'Sistema'; ?>" required>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-warning">Aplicar Ajuste</button>
          </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Editar Producto -->
<div class="modal fade" id="modalEditarProducto" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <!-- Se carga dinámicamente -->
    </div>
  </div>
</div>

<script>
// Función para generar reporte de inventario
function generarReporteInventario() {
    window.open('reporte_inventario.php', '_blank');
}

// Función para ajustar stock
function ajustarStock(id, nombre, stockLleno, stockVacio) {
    document.getElementById('ajusteProductoId').value = id;
    document.getElementById('ajusteProductoNombre').innerText = nombre;
    document.getElementById('stockLlenoActual').innerText = stockLleno;
    document.getElementById('stockVacioActual').innerText = stockVacio;
    
    // Resetear campos
    document.querySelectorAll('.ajuste-campos').forEach(el => {
        el.classList.add('d-none');
    });
    document.getElementById('camposEntrada').classList.remove('d-none');
    
    $('#modalAjuste').modal('show');
}

// Función para mostrar campos según tipo de ajuste
function mostrarCamposAjuste() {
    const tipo = document.querySelector('select[name="tipo_ajuste"]').value;
    
    // Ocultar todos los campos
    document.querySelectorAll('.ajuste-campos').forEach(el => {
        el.classList.add('d-none');
    });
    
    // Mostrar campos correspondientes
    if (tipo === 'ENTRADA') {
        document.getElementById('camposEntrada').classList.remove('d-none');
    } else if (tipo === 'SALIDA') {
        document.getElementById('camposSalida').classList.remove('d-none');
    } else if (tipo === 'TRANSFERENCIA') {
        document.getElementById('camposTransferencia').classList.remove('d-none');
    } else {
        document.getElementById('camposSalida').classList.remove('d-none');
    }
}

// Función para editar producto
function editarProducto(id) {
    $.ajax({
        url: 'editar.php',
        type: 'GET',
        data: { id: id },
        success: function(response) {
            $('#modalEditarProducto .modal-content').html(response);
            $('#modalEditarProducto').modal('show');
        }
    });
}

// Función para eliminar producto
function eliminarProducto(id, nombre) {
    if (confirm(`¿Está seguro de eliminar el producto "${nombre}"?\n\nEsta acción no se puede deshacer y eliminará todas las referencias del producto.`)) {
        $.ajax({
            url: 'eliminar.php',
            type: 'POST',
            data: { id: id },
            success: function(response) {
                const result = JSON.parse(response);
                if (result.success) {
                    alert(result.message);
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            }
        });
    }
}

// Validar formulario de producto
document.getElementById('formProducto').addEventListener('submit', function(e) {
    const precio = parseFloat(document.querySelector('input[name="precio"]').value);
    const stockLleno = parseInt(document.querySelector('input[name="stock_lleno"]').value);
    
    if (precio < 0) {
        e.preventDefault();
        alert('El precio no puede ser negativo.');
        return;
    }
    
    if (stockLleno < 0) {
        e.preventDefault();
        alert('El stock inicial no puede ser negativo.');
        return;
    }
});

// Validar formulario de ajuste
document.getElementById('formAjuste').addEventListener('submit', function(e) {
    const tipo = document.querySelector('select[name="tipo_ajuste"]').value;
    const stockLlenoActual = parseInt(document.getElementById('stockLlenoActual').textContent);
    const stockVacioActual = parseInt(document.getElementById('stockVacioActual').textContent);
    
    if (tipo === 'SALIDA') {
        const cantidadLleno = parseInt(document.querySelector('input[name="cantidad_lleno_salida"]').value) || 0;
        const cantidadVacio = parseInt(document.querySelector('input[name="cantidad_vacio_salida"]').value) || 0;
        
        if (cantidadLleno > stockLlenoActual) {
            e.preventDefault();
            alert(`No puede retirar ${cantidadLleno} unidades cuando solo hay ${stockLlenoActual} en stock.`);
            return;
        }
        
        if (cantidadVacio > stockVacioActual) {
            e.preventDefault();
            alert(`No puede retirar ${cantidadVacio} vacíos cuando solo hay ${stockVacioActual} en stock.`);
            return;
        }
    }
    
    if (tipo === 'TRANSFERENCIA') {
        const cantidadTransferir = parseInt(document.querySelector('input[name="cantidad_lleno_a_vacio"]').value) || 0;
        
        if (cantidadTransferir > stockLlenoActual) {
            e.preventDefault();
            alert(`No puede transferir ${cantidadTransferir} unidades cuando solo hay ${stockLlenoActual} en stock.`);
            return;
        }
    }
});
</script>

<?php include '../../includes/footer.php'; ?>