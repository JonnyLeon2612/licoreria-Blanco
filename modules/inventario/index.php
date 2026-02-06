<?php
// modules/inventario/index.php
$page_title = "Gestión de Inventario";
include '../../config/db.php';
include '../../includes/header.php';

// Consultar productos
$query = "SELECT * FROM productos ORDER BY stock_lleno ASC, nombre_producto ASC";
$stmt = $pdo->query($query);
// Guardamos los datos en un array para poder usarlos dos veces (uno para calcular, otro para la tabla)
$productos_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas base
$estadisticas = $pdo->query("
    SELECT 
        COUNT(*) as total_productos,
        SUM(stock_lleno) as total_stock_lleno,
        0 as total_stock_vacio, 
        SUM(stock_lleno * precio_venta_usd) as valor_inventario,
        SUM(CASE WHEN stock_lleno < 10 THEN 1 ELSE 0 END) as productos_bajo_stock,
        SUM(CASE WHEN stock_lleno = 0 THEN 1 ELSE 0 END) as productos_sin_stock
    FROM productos
")->fetch(PDO::FETCH_ASSOC);

// ------------------------------------------------------------------------------------
// CÁLCULO INTERNO (MATEMÁTICA) - ESTO NO AFECTA EL DISEÑO
// ------------------------------------------------------------------------------------
$total_vacios_real = 0;
foreach ($productos_data as $p) {
    // 1. Sumamos lo que haya quedado en la columna vieja (basura o histórico)
    $total_vacios_real += $p['stock_vacio'];

    // 2. Si el producto se llama GAVERA o VACIO, sumamos su stock lleno al total de vacíos
    if (stripos($p['nombre_producto'], 'GAVERA') !== false || 
        stripos($p['nombre_producto'], 'VACIO') !== false || 
        stripos($p['nombre_producto'], 'PLASTICO') !== false) {
        $total_vacios_real += $p['stock_lleno'];
    }
}
// ------------------------------------------------------------------------------------
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
                        <h5 class="text-muted">Total Vacíos</h5> <h3 class="mb-0"><?php echo number_format($total_vacios_real); ?></h3> </div>
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

<?php if($estadisticas['productos_bajo_stock'] > 0): ?>
<div class="alert alert-warning alert-custom mb-4">
    <div class="d-flex align-items-center">
        <i class="bi bi-exclamation-triangle fs-4 me-3"></i>
        <div>
            <h5 class="alert-heading">¡Atención! Productos con stock bajo</h5>
            <p class="mb-1">
                Hay <strong><?php echo $estadisticas['productos_bajo_stock']; ?> productos</strong> con stock menor a 10 unidades.
            </p>
        </div>
    </div>
</div>
<?php endif; ?>

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
                        <th class="text-end">Valor Stock</th>
                        <th class="text-center">Estado</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($productos_data as $row): 
                        $valor_stock = $row['stock_lleno'] * $row['precio_venta_usd'];
                        $estado_stock = '';
                        $color_estado = '';
                        
                        if ($row['stock_lleno'] == 0) {
                            $estado_stock = 'Sin Stock';
                            $color_estado = 'danger';
                        } elseif ($row['stock_lleno'] < 10) {
                            $estado_stock = 'Bajo';
                            $color_estado = 'warning';
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
                            <span class="fw-bold <?php echo $row['stock_lleno'] < 10 ? 'text-danger' : 'text-success'; ?> fs-5">
                                <?php echo number_format($row['stock_lleno']); ?>
                            </span>
                            <small class="text-muted">cajas</small>
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
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

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
                <div class="col-md-4 mb-3">
                    <label class="form-label text-success">Stock Lleno (Inicial)</label>
                    <input type="number" name="stock_lleno" class="form-control" value="0" min="0">
                </div>
                <input type="hidden" name="stock_vacio" value="0"> 
                
                <div class="col-md-4 mb-3">
                    <label class="form-label">Stock Mínimo</label>
                    <input type="number" name="stock_minimo" class="form-control" value="10" min="0">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Stock Máximo</label>
                    <input type="number" name="stock_maximo" class="form-control" value="100" min="0">
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
                <div class="row justify-content-center">
                    <div class="col-6">
                        <small>Existencia Actual:</small><br>
                        <span id="stockLlenoActual" class="fw-bold fs-4">0</span>
                    </div>
                </div>
            </div>
            
            <hr>
            
            <div class="mb-3">
                <label class="form-label">Tipo de Ajuste</label>
                <select name="tipo_ajuste" class="form-select">
                    <option value="ENTRADA">Entrada de Mercancía</option>
                    <option value="SALIDA">Salida por Ajuste</option>
                    <option value="FISICO">Conteo Físico</option>
                </select>
            </div>
            
            <div class="mb-3">
                <label class="form-label fw-bold">Cantidad a Ajustar</label>
                <input type="number" name="cantidad" class="form-control" min="0" value="0">
            </div>
            
            <div class="mb-3">
                <label class="form-label">Motivo del Ajuste</label>
                <textarea name="motivo" class="form-control" rows="2" required 
                          placeholder="Ej: Reposición de inventario..."></textarea>
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

<div class="modal fade" id="modalEditarProducto" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
    </div>
  </div>
</div>

<script>
function generarReporteInventario() {
    window.open('reporte_inventario.php', '_blank');
}

function ajustarStock(id, nombre, stockLleno) {
    document.getElementById('ajusteProductoId').value = id;
    document.getElementById('ajusteProductoNombre').innerText = nombre;
    document.getElementById('stockLlenoActual').innerText = stockLleno;
    document.querySelector('input[name="cantidad"]').value = 0;
    $('#modalAjuste').modal('show');
}

function mostrarCamposAjuste() {
    // Simplificado
}

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

function eliminarProducto(id, nombre) {
    // 1. Preguntar bonito con SweetAlert
    Swal.fire({
        title: '¿Estás seguro?',
        text: "Vas a eliminar el producto: " + nombre,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33', // Rojo peligro
        cancelButtonColor: '#3085d6', // Azul cancelar
        confirmButtonText: 'Sí, eliminarlo',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            // 2. Si dice que SÍ, llamamos al archivo PHP
            $.ajax({
                url: 'eliminar.php',
                type: 'POST',
                data: { id: id },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // 3a. ÉXITO (Check Verde)
                        Swal.fire(
                            response.title,
                            response.message,
                            'success'
                        ).then(() => {
                            location.reload(); // Recarga la página para ver cambios
                        });
                    } else {
                        // 3b. ERROR DE PROTECCIÓN (X Roja)
                        Swal.fire(
                            response.title,
                            response.message,
                            'error'
                        );
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Hubo un problema de conexión con el servidor', 'error');
                }
            });
        }
    })
}

function eliminarProducto(id, nombre) {
    Swal.fire({
        title: '¿Estás seguro?',
        text: "Se eliminará el producto: " + nombre,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            // INTENTO DE ELIMINAR
            $.ajax({
                url: 'eliminar.php',
                type: 'POST',
                data: { id: id },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // CASO 1: SE BORRÓ (Porque era nuevo)
                        Swal.fire('¡Eliminado!', response.message, 'success')
                        .then(() => location.reload());
                    } else {
                        // CASO 2: NO SE PUDO BORRAR (Tiene historial) -> OFRECEMOS DESACTIVAR
                        Swal.fire({
                            title: 'No se puede eliminar',
                            text: response.message + " ¿Quieres desactivarlo para que no estorbe?",
                            icon: 'error',
                            showCancelButton: true,
                            confirmButtonText: '⚠️ Sí, Desactivar',
                            confirmButtonColor: '#f39c12', // Color Naranja de advertencia
                            cancelButtonText: 'No, dejar así'
                        }).then((resultDesactivar) => {
                            if (resultDesactivar.isConfirmed) {
                                // LLAMAMOS A DESACTIVAR
                                $.ajax({
                                    url: 'desactivar.php',
                                    type: 'POST',
                                    data: { id: id },
                                    dataType: 'json',
                                    success: function(res) {
                                        if(res.success) {
                                            Swal.fire('Desactivado', res.message, 'success')
                                            .then(() => location.reload());
                                        }
                                    }
                                });
                            }
                        });
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Fallo de conexión', 'error');
                }
            });
        }
    })
}

document.getElementById('formProducto').addEventListener('submit', function(e) {
    const precio = parseFloat(document.querySelector('input[name="precio"]').value);
    const stockLleno = parseInt(document.querySelector('input[name="stock_lleno"]').value);
    if (precio < 0) { e.preventDefault(); alert('El precio no puede ser negativo.'); return; }
    if (stockLleno < 0) { e.preventDefault(); alert('El stock inicial no puede ser negativo.'); return; }
});
</script>

<?php include '../../includes/footer.php'; ?>