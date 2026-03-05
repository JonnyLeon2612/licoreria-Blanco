<?php
// modules/inventario/index.php
$page_title = "Gestión de Inventario";
include '../../config/db.php';
include '../../includes/header.php';

// --- LÓGICA DEL BUSCADOR ---
$busqueda = isset($_GET['q']) ? trim($_GET['q']) : '';

if ($busqueda != '') {
    // Si hay búsqueda, buscamos por nombre o código y QUITAMOS el límite para encontrar todo
    $sql = "SELECT * FROM productos 
            WHERE nombre_producto LIKE :q 
            OR id_producto = :id 
            ORDER BY stock_lleno ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':q' => "%$busqueda%",
        ':id' => $busqueda // Por si busca por ID exacto
    ]);
} else {
    // Si NO hay búsqueda, mostramos los primeros 50 para que cargue rápido en el cel
    $sql = "SELECT * FROM productos ORDER BY stock_lleno ASC LIMIT 50";
    $stmt = $pdo->query($sql);
}

$productos_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas base (Siguen igual)
$estadisticas = $pdo->query("
    SELECT 
        COUNT(*) as total_productos,
        SUM(stock_lleno) as total_stock_lleno,
        SUM(stock_lleno * precio_venta_usd) as valor_inventario,
        SUM(CASE WHEN stock_lleno < 10 THEN 1 ELSE 0 END) as productos_bajo_stock,
        SUM(CASE WHEN stock_lleno = 0 THEN 1 ELSE 0 END) as productos_sin_stock
    FROM productos
")->fetch(PDO::FETCH_ASSOC);

// Cálculo interno de vacíos
$total_vacios_real = 0;
foreach ($productos_data as $p) {
    $total_vacios_real += $p['stock_vacio'];
    if (stripos($p['nombre_producto'], 'GAVERA') !== false || 
        stripos($p['nombre_producto'], 'VACIO') !== false || 
        stripos($p['nombre_producto'], 'PLASTICO') !== false) {
        $total_vacios_real += $p['stock_lleno'];
    }
}
?>

<style>
    .inventory-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        margin-bottom: 20px;
        border: 1px solid #f0f0f0;
        overflow: hidden;
        position: relative;
    }
    .inventory-status-bar { position: absolute; left: 0; top: 0; bottom: 0; width: 6px; }
    .card-header-mobile { padding: 15px 15px 5px 20px; display: flex; justify-content: space-between; align-items: flex-start; }
    .product-title { font-size: 1.15rem; font-weight: 800; color: #2c3e50; line-height: 1.2; margin-bottom: 8px; }
    .stock-big-display { text-align: center; padding: 15px 0; background-color: #fcfcfc; border-top: 1px solid #eee; border-bottom: 1px solid #eee; cursor: pointer; }
    .stock-big-display:active { background-color: #f0f0f0; }
    .stock-number { font-size: 3rem; font-weight: 900; line-height: 1; display: block; letter-spacing: -1px; }
    .stock-label { font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; color: #7f8c8d; font-weight: 600; }
    .card-actions-mobile { display: flex; padding: 12px; gap: 12px; }
    .btn-action-mobile { flex: 1; padding: 12px; border-radius: 10px; font-weight: 700; font-size: 1rem; border: none; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
    .btn-adjust { background-color: #fff3cd; color: #856404; border: 1px solid #ffe69c; }
    .btn-edit { background-color: #e7f1ff; color: #0d6efd; border: 1px solid #cfe2ff; }
    
    /* ESTILO DEL BUSCADOR */
    .search-container { margin-bottom: 20px; }
    .search-input { border-radius: 50px 0 0 50px !important; border: 1px solid #ced4da; padding-left: 20px; }
    .search-btn { border-radius: 0 50px 50px 0 !important; padding-right: 20px; padding-left: 20px; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h3 mb-0"><i class="bi bi-box-seam-fill text-primary"></i> Inventario</h1>
    </div>
    <div>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalProducto">
            <i class="bi bi-plus-lg"></i> <span class="d-none d-sm-inline">Nuevo</span>
        </button>
        <button type="button" class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#modalAjuste">
            <i class="bi bi-arrow-repeat"></i> <span class="d-none d-sm-inline">Ajustar</span>
        </button>
        <button class="btn btn-outline-success btn-sm" onclick="generarReporteInventario()">
            <i class="bi bi-file-earmark-excel"></i>
        </button>
    </div>
</div>

<div class="search-container">
    <form action="" method="GET">
        <div class="input-group input-group-lg shadow-sm">
            <span class="input-group-text bg-white border-end-0 rounded-start-pill ps-3">
                <i class="bi bi-search text-muted"></i>
            </span>
            <input type="text" name="q" class="form-control border-start-0" 
                   placeholder="Buscar producto..." 
                   value="<?php echo htmlspecialchars($busqueda); ?>" 
                   style="font-size: 16px; height: 50px;"> <?php if($busqueda != ''): ?>
                <a href="index.php" class="btn btn-outline-secondary border-start-0" style="padding-top: 12px;">
                    <i class="bi bi-x-lg"></i>
                </a>
            <?php endif; ?>
            
            <button class="btn btn-primary rounded-end-pill px-4 search-btn" type="submit">Buscar</button>
        </div>
    </form>
</div>

<div class="row mb-4 g-2 g-md-3">
    <div class="col-6 col-sm-6 col-md-4 col-lg-3 col-xl-2 mb-2 mb-md-3">
        <div class="card border-start border-primary border-4 h-100">
            <div class="card-body p-2 p-md-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div><h6 class="text-muted small mb-1">Productos</h6><h3 class="mb-0 fw-bold"><?php echo number_format($estadisticas['total_productos']); ?></h3></div>
                    <i class="bi bi-box text-primary fs-1 opacity-25"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-sm-6 col-md-4 col-lg-3 col-xl-2 mb-2 mb-md-3">
        <div class="card border-start border-success border-4 h-100">
            <div class="card-body p-2 p-md-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div><h6 class="text-muted small mb-1">Cajas Llenas</h6><h3 class="mb-0 fw-bold"><?php echo number_format($estadisticas['total_stock_lleno']); ?></h3></div>
                    <i class="bi bi-check-circle text-success fs-1 opacity-25"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-sm-6 col-md-4 col-lg-3 col-xl-2 mb-2 mb-md-3">
        <div class="card border-start border-warning border-4 h-100">
            <div class="card-body p-2 p-md-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div><h6 class="text-muted small mb-1">Vacíos</h6><h3 class="mb-0 fw-bold"><?php echo number_format($total_vacios_real); ?></h3></div>
                    <i class="bi bi-box-seam text-warning fs-1 opacity-25"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-sm-6 col-md-6 col-lg-6 col-xl-3 mb-2 mb-md-3">
        <div class="card border-start border-info border-4 h-100">
            <div class="card-body p-2 p-md-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div><h6 class="text-muted small mb-1">Valor $</h6><h4 class="mb-0 fw-bold text-truncate">$<?php echo number_format($estadisticas['valor_inventario'], 2); ?></h4></div>
                    <i class="bi bi-currency-dollar text-info fs-1 opacity-25"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if($estadisticas['productos_bajo_stock'] > 0): ?>
<div class="alert alert-warning alert-custom mb-4 shadow-sm">
    <div class="d-flex align-items-center">
        <i class="bi bi-exclamation-triangle fs-1 me-3 text-warning"></i>
        <div>
            <h6 class="alert-heading fw-bold">Stock Bajo</h6>
            <p class="mb-0 small">Hay <strong><?php echo $estadisticas['productos_bajo_stock']; ?> productos</strong> por agotarse.</p>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card shadow-sm d-none d-md-block">
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
                        $estado_stock = ''; $color_estado = '';
                        
                        if ($row['stock_lleno'] == 0) {
                            $estado_stock = 'Sin Stock'; $color_estado = 'danger';
                        } elseif ($row['stock_lleno'] < 10) {
                            $estado_stock = 'Bajo'; $color_estado = 'warning';
                        } else {
                            $estado_stock = 'Óptimo'; $color_estado = 'success';
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
                        <td class="text-center"><span class="badge bg-<?php echo $color_estado; ?>"><?php echo $estado_stock; ?></span></td>
                        <td class="text-center">
                            <div class="btn-group btn-group-sm" role="group">
                                <button type="button" class="btn btn-outline-primary" onclick="editarProducto(<?php echo $row['id_producto']; ?>)"><i class="bi bi-pencil"></i></button>
                                <button type="button" class="btn btn-outline-warning" onclick="ajustarStock(<?php echo $row['id_producto']; ?>, '<?php echo addslashes($row['nombre_producto']); ?>', <?php echo $row['stock_lleno']; ?>, <?php echo $row['stock_vacio']; ?>)"><i class="bi bi-arrow-repeat"></i></button>
                                <button type="button" class="btn btn-outline-danger" onclick="eliminarProducto(<?php echo $row['id_producto']; ?>, '<?php echo addslashes($row['nombre_producto']); ?>')"><i class="bi bi-trash"></i></button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mobile-card-view d-block d-md-none pb-5"> 
    <?php if(empty($productos_data)): ?>
        <div class="text-center p-5 text-muted">
            <i class="bi bi-search fs-1"></i>
            <p class="mt-2">No se encontraron productos.</p>
        </div>
    <?php endif; ?>

    <?php foreach($productos_data as $row): 
        $valor_stock = $row['stock_lleno'] * $row['precio_venta_usd'];
        if ($row['stock_lleno'] == 0) {
            $color_hex = '#dc3545'; $stock_class = 'text-danger';
        } elseif ($row['stock_lleno'] < 10) {
            $color_hex = '#ffc107'; $stock_class = 'text-warning';
        } else {
            $color_hex = '#198754'; $stock_class = 'text-success';
        }
    ?>
    
    <div class="inventory-card">
        <div class="inventory-status-bar" style="background-color: <?php echo $color_hex; ?>;"></div>
        
        <div class="card-header-mobile">
            <div style="flex: 1; padding-right: 10px;">
                <div class="product-title"><?php echo $row['nombre_producto']; ?></div>
                <div class="d-flex gap-2">
                    <span class="badge bg-light text-dark border">$<?php echo number_format($row['precio_venta_usd'], 2); ?></span>
                    <?php if($row['es_retornable']): ?>
                        <span class="badge bg-info bg-opacity-10 text-info border border-info">Retornable</span>
                    <?php else: ?>
                        <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary">Desechable</span>
                    <?php endif; ?>
                </div>
            </div>
            <button class="btn btn-sm text-muted p-2" onclick="eliminarProducto(<?php echo $row['id_producto']; ?>, '<?php echo addslashes($row['nombre_producto']); ?>')">
                <i class="bi bi-trash"></i>
            </button>
        </div>

        <div class="stock-big-display" onclick="ajustarStock(<?php echo $row['id_producto']; ?>, '<?php echo addslashes($row['nombre_producto']); ?>', <?php echo $row['stock_lleno']; ?>, <?php echo $row['stock_vacio']; ?>)">
            <span class="stock-number <?php echo $stock_class; ?>"><?php echo number_format($row['stock_lleno']); ?></span>
            <span class="stock-label">Cajas Disponibles</span>
        </div>

        <div class="px-3 py-2 d-flex justify-content-between text-muted small bg-light border-top">
            <span>Valor: <strong>$<?php echo number_format($valor_stock, 2); ?></strong></span>
            <span>Vacíos: <strong><?php echo $row['stock_vacio']; ?></strong></span>
        </div>

        <div class="card-actions-mobile">
            <button class="btn-action-mobile btn-adjust" onclick="ajustarStock(<?php echo $row['id_producto']; ?>, '<?php echo addslashes($row['nombre_producto']); ?>', <?php echo $row['stock_lleno']; ?>, <?php echo $row['stock_vacio']; ?>)">
                <i class="bi bi-arrow-repeat fs-4"></i> Ajustar
            </button>
            <button class="btn-action-mobile btn-edit" onclick="editarProducto(<?php echo $row['id_producto']; ?>)">
                <i class="bi bi-pencil fs-4"></i> Editar
            </button>
        </div>
    </div>
    <?php endforeach; ?>
    
    <div style="height: 60px;"></div>
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
                <div class="col-12 col-md-8 mb-3">
                    <label class="form-label">Nombre del Producto <span class="text-danger">*</span></label>
                    <input type="text" name="nombre" class="form-control" required placeholder="Ej: Polar Pilsen 36 Und">
                </div>
                <div class="col-12 col-md-4 mb-3">
                    <label class="form-label">Tipo de Envase <span class="text-danger">*</span></label>
                    <select name="retornable" class="form-select" required>
                        <option value="1">Retornable (Pide Vacío)</option>
                        <option value="0">Desechable (No pide Vacío)</option>
                    </select>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Descripción</label>
                <textarea name="descripcion" class="form-control" rows="2"></textarea>
            </div>
            <div class="row">
                <div class="col-12 col-md-6 mb-3">
                    <label class="form-label">Precio de Venta ($) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" name="precio" class="form-control" required placeholder="0.00" min="0">
                </div>
                <div class="col-12 col-md-6 mb-3">
                    <label class="form-label">Costo ($)</label>
                    <input type="number" step="0.01" name="costo" class="form-control" placeholder="0.00" min="0">
                </div>
            </div>
            <hr>
            <h6>Inventario Inicial</h6>
            <div class="row">
                <div class="col-12 col-md-4 mb-3">
                    <label class="form-label text-success">Stock Lleno</label>
                    <input type="number" name="stock_lleno" class="form-control" value="0" min="0">
                </div>
                <input type="hidden" name="stock_vacio" value="0"> 
                <div class="col-12 col-md-4 mb-3">
                    <label class="form-label">Mínimo</label>
                    <input type="number" name="stock_minimo" class="form-control" value="10" min="0">
                </div>
                <div class="col-12 col-md-4 mb-3">
                    <label class="form-label">Máximo</label>
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
                <h5 id="ajusteProductoNombre" class="fw-bold">Producto</h5>
                <div class="bg-light p-2 rounded">
                    <small>Existencia Actual:</small><br>
                    <span id="stockLlenoActual" class="fw-bold fs-2">0</span>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Tipo de Ajuste</label>
                <select name="tipo_ajuste" class="form-select form-control-lg">
                    <option value="ENTRADA">Entrada de Mercancía (+)</option>
                    <option value="SALIDA">Salida / Merma (-)</option>
                    <option value="FISICO">Conteo Físico (=)</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold">Cantidad</label>
                <input type="number" name="cantidad" class="form-control form-control-lg text-center fw-bold" min="0" value="0" style="font-size: 24px;">
            </div>
            <div class="mb-3">
                <label class="form-label">Motivo</label>
                <textarea name="motivo" class="form-control" rows="2" required placeholder="Razón del ajuste..."></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">Responsable</label>
                <input type="text" name="responsable" class="form-control" value="<?php echo $_SESSION['usuario'] ?? 'Sistema'; ?>" required>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-warning w-100 fw-bold">APLICAR AJUSTE</button>
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
    Swal.fire({
        title: '¿Estás seguro?',
        text: "Se eliminará: " + nombre,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, eliminar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'eliminar.php',
                type: 'POST',
                data: { id: id },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire('¡Eliminado!', response.message, 'success').then(() => location.reload());
                    } else {
                        Swal.fire({
                            title: 'No se puede eliminar',
                            text: response.message + " ¿Desactivar en su lugar?",
                            icon: 'error',
                            showCancelButton: true,
                            confirmButtonText: 'Sí, Desactivar',
                            confirmButtonColor: '#f39c12'
                        }).then((resultDesactivar) => {
                            if (resultDesactivar.isConfirmed) {
                                $.ajax({
                                    url: 'desactivar.php',
                                    type: 'POST',
                                    data: { id: id },
                                    dataType: 'json',
                                    success: function(res) {
                                        if(res.success) {
                                            Swal.fire('Desactivado', res.message, 'success').then(() => location.reload());
                                        }
                                    }
                                });
                            }
                        });
                    }
                },
                error: function() { Swal.fire('Error', 'Fallo de conexión', 'error'); }
            });
        }
    })
}

document.getElementById('formProducto').addEventListener('submit', function(e) {
    const precio = parseFloat(document.querySelector('input[name="precio"]').value);
    const stockLleno = parseInt(document.querySelector('input[name="stock_lleno"]').value);
    if (precio < 0) { e.preventDefault(); alert('Precio no puede ser negativo.'); return; }
    if (stockLleno < 0) { e.preventDefault(); alert('Stock no puede ser negativo.'); return; }
});

// RESPONSIVE: Alternar vistas
$(document).ready(function() {
    function checkWidth() {
        if ($(window).width() < 768) {
            $('.mobile-card-view').show();
            $('.card.shadow-sm.d-none.d-md-block').hide(); 
        } else {
            $('.mobile-card-view').hide();
            $('.card.shadow-sm.d-none.d-md-block').show();
        }
    }
    checkWidth();
    $(window).resize(checkWidth);
});
</script>

<?php include '../../includes/footer.php'; ?>