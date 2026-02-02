<?php
// modules/clientes/editar.php
include '../../config/db.php';

$id_cliente = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($_SERVER['REQUEST_METHOD'] == 'GET' && $id_cliente > 0) {
    // Obtener datos del cliente
    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id_cliente = ?");
    $stmt->execute([$id_cliente]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cliente) {
        echo '<div class="alert alert-danger">Cliente no encontrado</div>';
        exit();
    }
    
    // Obtener información de crédito
    $stmt = $pdo->prepare("SELECT limite_credito, dias_credito FROM cuentas_por_cobrar WHERE id_cliente = ?");
    $stmt->execute([$id_cliente]);
    $credito = $stmt->fetch(PDO::FETCH_ASSOC);
    ?>
    
    <form action="actualizar.php" method="POST">
        <input type="hidden" name="id_cliente" value="<?php echo $id_cliente; ?>">
        
        <div class="modal-header bg-primary text-white">
            <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Editar Cliente</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        
        <div class="modal-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Nombre del Cliente</label>
                    <input type="text" name="nombre" class="form-control" value="<?php echo htmlspecialchars($cliente['nombre_cliente']); ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">RIF o Cédula</label>
                    <input type="text" name="rif" class="form-control" value="<?php echo htmlspecialchars($cliente['rif_cedula']); ?>" required>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Teléfono</label>
                    <input type="text" name="telefono" class="form-control" value="<?php echo htmlspecialchars($cliente['telefono']); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Tipo de Cliente</label>
                    <select name="tipo" class="form-select" required>
                        <option value="Mayorista" <?php echo $cliente['tipo_cliente'] == 'Mayorista' ? 'selected' : ''; ?>>Mayorista (Crédito)</option>
                        <option value="Detal" <?php echo $cliente['tipo_cliente'] == 'Detal' ? 'selected' : ''; ?>>Detal (Contado)</option>
                    </select>
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Dirección</label>
                <textarea name="direccion" class="form-control" rows="2"><?php echo htmlspecialchars($cliente['direccion']); ?></textarea>
            </div>
            
            <?php if($cliente['tipo_cliente'] == 'Mayorista'): ?>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Límite de Crédito ($)</label>
                    <input type="number" name="limite_credito" class="form-control" 
                           value="<?php echo number_format($credito['limite_credito'] ?? 1000, 2, '.', ''); ?>" step="0.01">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Días de Crédito</label>
                    <input type="number" name="dias_credito" class="form-control" 
                           value="<?php echo $credito['dias_credito'] ?? 7; ?>">
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary">Actualizar</button>
        </div>
    </form>
    <?php
}
?>