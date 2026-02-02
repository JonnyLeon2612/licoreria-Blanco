<?php
// modules/ventas/historial.php
$page_title = "Historial de Ventas";
include '../../config/db.php';
include '../../includes/header.php';

// CONSULTA INTELIGENTE: Trae la venta, el cliente y la suma real de sus abonos
$sql = "SELECT v.*, c.nombre_cliente,
               (SELECT COALESCE(SUM(monto_abonado_usd), 0) FROM abonos WHERE id_cliente = v.id_cliente) as total_pagado_cliente
        FROM ventas v 
        LEFT JOIN clientes c ON v.id_cliente = c.id_cliente 
        ORDER BY v.fecha_venta DESC";
$ventas = $pdo->query($sql)->fetchAll();
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h1 class="h3 mb-0"><i class="bi bi-clock-history text-primary"></i> Historial de Ventas</h1>
        <p class="text-muted small mb-0">Gestión de transacciones realizadas</p>
    </div>
    <a href="index.php" class="btn btn-success w-100 w-md-auto shadow-sm">
        <i class="bi bi-cart-plus"></i> Nueva Venta
    </a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-2 p-md-3">
        <div class="table-responsive">
            <table id="tablaHistorial" class="table table-hover align-middle w-100">
                <thead class="table-light">
                    <tr>
                        <th class="text-nowrap">ID</th>
                        <th class="text-nowrap">Fecha</th>
                        <th>Cliente</th>
                        <th class="text-end">Total ($)</th>
                        <th class="text-center">Estado Real</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ventas as $v): 
                        $monto_total = floatval($v['total_monto_usd'] ?? 0);
                        
                        // LÓGICA DE ESTADO VISUAL:
                        // Si el sistema ya lo marcó como 'Pagado' O si el cliente no debe nada en general (por el barrido), lo mostramos verde.
                        // (Nota: Como el barrido usa una 'bolsa general', es difícil saber venta por venta exacta sin una tabla intermedia, 
                        // pero confiaremos en el estado de la base de datos que ya corregimos con guardar_abono.php)
                        
                        $estado = $v['estado_pago'] ?? 'Pendiente';
                        $clase_badge = 'bg-danger'; // Por defecto Deuda
                        $texto_badge = 'Deuda';

                        if ($estado == 'Pagado') {
                            $clase_badge = 'bg-success';
                            $texto_badge = 'Pagado';
                        } elseif ($estado == 'Abonado') {
                            $clase_badge = 'bg-info';
                            $texto_badge = 'Abonado';
                        }
                        
                        $fecha = isset($v['fecha_venta']) ? date('d/m/y h:i A', strtotime($v['fecha_venta'])) : 'N/A';
                    ?>
                        <tr>
                            <td><span class="badge bg-light text-dark border">#<?php echo str_pad($v['id_venta'], 5, '0', STR_PAD_LEFT); ?></span></td>
                            <td class="text-nowrap small"><?php echo $fecha; ?></td>
                            <td class="fw-bold"><?php echo $v['nombre_cliente'] ?? 'Público General'; ?></td>
                            <td class="text-end fw-bold text-dark">$<?php echo number_format($monto_total, 2); ?></td>
                            <td class="text-center">
                                <span class="badge rounded-pill <?php echo $clase_badge; ?> w-100">
                                    <?php echo $texto_badge; ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="btn-group shadow-sm">
                                    <a href="../clientes/perfil.php?id=<?php echo $v['id_cliente']; ?>" class="btn btn-sm btn-outline-primary" title="Ver Perfil">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="comprobante.php?id=<?php echo $v['id_venta']; ?>" class="btn btn-sm btn-outline-secondary" title="Imprimir Comprobante">
                                        <i class="bi bi-printer"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#tablaHistorial').DataTable({
        "order": [[ 0, "desc" ]], // Ordenar por ID descendente
        "responsive": true,
        "language": {
            "url": "https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json"
        }
    });
});
</script>

<?php include '../../includes/footer.php'; ?>