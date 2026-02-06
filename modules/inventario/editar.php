<?php
// modules/inventario/editar.php
include '../../config/db.php';

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $pdo->prepare("SELECT * FROM productos WHERE id_producto = ?");
    $stmt->execute([$id]);
    $p = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($p) {
?>
    <form action="guardar_edicion.php" method="POST">
        <input type="hidden" name="id_producto" value="<?php echo $p['id_producto']; ?>">
        
        <div class="modal-header bg-primary text-white">
            <h5 class="modal-title">Editar: <?php echo $p['nombre_producto']; ?></h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        
        <div class="modal-body">
            <div class="row">
                <div class="col-md-8 mb-3">
                    <label class="form-label">Nombre</label>
                    <input type="text" name="nombre" class="form-control" value="<?php echo $p['nombre_producto']; ?>" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Tipo</label>
                    <select name="retornable" class="form-select">
                        <option value="1" <?php if($p['es_retornable']) echo 'selected'; ?>>Retornable</option>
                        <option value="0" <?php if(!$p['es_retornable']) echo 'selected'; ?>>Desechable</option>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Precio ($)</label>
                    <input type="number" step="0.01" name="precio" class="form-control" value="<?php echo $p['precio_venta_usd']; ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Costo ($)</label>
                    <input type="number" step="0.01" name="costo" class="form-control" value="<?php echo $p['costo_usd']; ?>">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Descripción</label>
                <textarea name="descripcion" class="form-control" rows="2"><?php echo $p['descripcion']; ?></textarea>
            </div>

            <div class="row">
                <div class="col-6">
                    <label class="form-label">Stock Mínimo</label>
                    <input type="number" name="minimo" class="form-control" value="<?php echo $p['stock_minimo']; ?>">
                </div>
                <div class="col-6">
                    <label class="form-label">Stock Máximo</label>
                    <input type="number" name="maximo" class="form-control" value="<?php echo $p['stock_maximo']; ?>">
                </div>
            </div>
            
            <div class="alert alert-warning mt-3 small">
                <i class="bi bi-info-circle"></i> Para cambiar la cantidad de productos, usa el botón amarillo de "Ajustar Stock".
            </div>
        </div>
        
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary">Guardar Cambios</button>
        </div>
    </form>
<?php
    } else {
        echo "<div class='p-3 text-danger'>Producto no encontrado</div>";
    }
}
?>