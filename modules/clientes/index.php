<?php
// modules/clientes/index.php
$page_title = "Gestión de Clientes";
include '../../config/db.php';
include '../../includes/header.php';

// Consulta para obtener clientes con datos de deuda (INTACTA)
$query = "
    SELECT c.*, cc.saldo_dinero_usd, cc.saldo_vacios, 
           (SELECT COUNT(*) FROM ventas v WHERE v.id_cliente = c.id_cliente) as total_compras,
           (SELECT MAX(fecha_venta) FROM ventas v WHERE v.id_cliente = c.id_cliente) as ultima_compra
    FROM clientes c
    LEFT JOIN cuentas_por_cobrar cc ON c.id_cliente = cc.id_cliente
    ORDER BY c.nombre_cliente ASC
";
$stmt = $pdo->query($query);
// Guardamos los datos para usar dos veces
$clientes_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    .mobile-client-card {
        background: white;
        border-radius: 12px;
        padding: 15px;
        margin-bottom: 15px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        border: 1px solid #f0f0f0;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    
    .client-header {
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    .client-avatar {
        width: 50px; height: 50px;
        background-color: #3498db;
        color: white;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-weight: 700; font-size: 1.2rem;
        flex-shrink: 0;
    }
    
    .client-info { flex-grow: 1; }
    .client-name { font-weight: 700; color: #333; font-size: 1.1rem; line-height: 1.2; }
    .client-sub { font-size: 0.85rem; color: #777; }
    
    .client-stats {
        display: flex;
        justify-content: space-between;
        background: #f8f9fa;
        padding: 10px;
        border-radius: 8px;
        font-size: 0.9rem;
    }
    
    .client-actions {
        display: flex;
        gap: 8px;
        margin-top: 5px;
    }
    
    .btn-mobile { flex: 1; padding: 8px; border-radius: 8px; font-weight: 600; font-size: 0.9rem; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0"><i class="bi bi-people-fill text-primary"></i> Clientes</h1>
    </div>
    <div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCliente">
            <i class="bi bi-plus-circle"></i> <span class="d-none d-sm-inline">Nuevo</span>
        </button>
        <button class="btn btn-success" onclick="exportarClientes()">
            <i class="bi bi-file-earmark-excel"></i> <span class="d-none d-sm-inline">Exportar</span>
        </button>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <div class="row g-2">
            <div class="col-12 col-md-6 col-lg-4">
                <input type="text" id="searchClientes" class="form-control" placeholder="Buscar cliente..." onkeyup="aplicarFiltros()">
            </div>
            <div class="col-6 col-md-3 col-lg-3">
                <select id="filterTipo" class="form-select" onchange="aplicarFiltros()">
                    <option value="">Todos los tipos</option>
                    <option value="Mayorista">Mayorista</option>
                    <option value="Detal">Detal</option>
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-3">
                <select id="filterDeuda" class="form-select" onchange="aplicarFiltros()">
                    <option value="">Todos los estados</option>
                    <option value="con_deuda">Con deuda</option>
                    <option value="sin_deuda">Sin deuda</option>
                </select>
            </div>
            <div class="col-12 col-md-6 col-lg-2">
                <button class="btn btn-outline-primary w-100" onclick="aplicarFiltros()">
                    <i class="bi bi-funnel"></i> <span class="d-none d-sm-inline">Filtrar</span>
                </button>
            </div>
        </div>
    </div>
</div>

<div class="d-block d-md-none pb-5">
    <?php foreach($clientes_data as $row): 
        $tieneDeuda = $row['saldo_dinero_usd'] > 0;
        $tieneVacios = $row['saldo_vacios'] > 0;
        $inicial = strtoupper(substr($row['nombre_cliente'], 0, 2));
        $colorAvatar = $tieneDeuda ? '#e74c3c' : '#3498db'; // Rojo si debe, Azul si no
    ?>
    <div class="mobile-client-card filter-item">
        <div class="client-header">
            <div class="client-avatar" style="background-color: <?php echo $colorAvatar; ?>;">
                <?php echo $inicial; ?>
            </div>
            <div class="client-info">
                <div class="client-name cliente-nombre"><?php echo $row['nombre_cliente']; ?></div>
                <div class="client-sub">
                    <span class="cliente-tipo"><?php echo $row['tipo_cliente']; ?></span> • 
                    <?php echo $row['rif_cedula']; ?>
                </div>
            </div>
        </div>

        <div class="client-stats">
            <div>
                <small class="text-muted d-block">Deuda</small>
                <span class="<?php echo $tieneDeuda ? 'text-danger fw-bold' : 'text-success'; ?> cliente-deuda-text">
                    <?php echo $tieneDeuda ? '$' . number_format($row['saldo_dinero_usd'], 2) : 'Al día'; ?>
                </span>
            </div>
            <div class="text-end">
                <small class="text-muted d-block">Vacíos</small>
                <span class="<?php echo $tieneVacios ? 'text-warning fw-bold' : 'text-muted'; ?>">
                    <?php echo $row['saldo_vacios']; ?>
                </span>
            </div>
        </div>

        <div class="client-actions">
            <?php if($row['telefono']): ?>
            <a href="tel:<?php echo $row['telefono']; ?>" class="btn btn-light border" style="width: 45px; display:flex; align-items:center; justify-content:center;">
                <i class="bi bi-telephone-fill text-success"></i>
            </a>
            <?php endif; ?>

            <a href="perfil.php?id=<?php echo $row['id_cliente']; ?>" class="btn btn-outline-primary btn-mobile">
                Ver
            </a>
            
            <a href="../ventas/index.php?cliente=<?php echo $row['id_cliente']; ?>" class="btn btn-primary btn-mobile">
                Vender
            </a>

            <?php if($tieneDeuda || $tieneVacios): ?>
            <a href="../cobranza/index.php?cliente=<?php echo $row['id_cliente']; ?>" class="btn btn-danger btn-mobile">
                Cobrar
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="card shadow-sm d-none d-md-block">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover datatable" id="tablaClientes">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Cliente / Negocio</th>
                        <th>RIF/Cédula</th>
                        <th>Contacto</th>
                        <th>Tipo</th>
                        <th class="text-end">Deuda $</th>
                        <th class="text-center">Vacíos</th>
                        <th>Compras</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($clientes_data as $row): 
                        $tieneDeuda = $row['saldo_dinero_usd'] > 0;
                        $tieneVacios = $row['saldo_vacios'] > 0;
                    ?>
                    <tr class="<?php echo $tieneDeuda ? 'table-warning' : ''; ?>">
                        <td><?php echo str_pad($row['id_cliente'], 4, '0', STR_PAD_LEFT); ?></td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar-circle me-2">
                                    <div style="width: 40px; height: 40px; background-color: #3498db; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">
                                        <?php echo strtoupper(substr($row['nombre_cliente'], 0, 2)); ?>
                                    </div>
                                </div>
                                <div>
                                    <strong><?php echo $row['nombre_cliente']; ?></strong><br>
                                    <small class="text-muted"><?php echo $row['direccion']; ?></small>
                                </div>
                            </div>
                        </td>
                        <td><code><?php echo $row['rif_cedula']; ?></code></td>
                        <td>
                            <?php if($row['telefono']): ?>
                                <a href="tel:<?php echo $row['telefono']; ?>" class="text-decoration-none">
                                    <i class="bi bi-telephone"></i> <?php echo $row['telefono']; ?>
                                </a>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $row['tipo_cliente'] == 'Mayorista' ? 'primary' : 'secondary'; ?>">
                                <?php echo $row['tipo_cliente']; ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <?php if($tieneDeuda): ?>
                                <span class="text-danger fw-bold">$<?php echo number_format($row['saldo_dinero_usd'], 2); ?></span>
                            <?php else: ?>
                                <span class="text-success">Al día</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if($tieneVacios): ?>
                                <span class="badge bg-warning text-dark"><?php echo $row['saldo_vacios']; ?></span>
                            <?php else: ?>
                                <span class="badge bg-success">0</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <small>
                                <?php echo $row['total_compras']; ?> compras<br>
                                <?php if($row['ultima_compra']): ?>
                                    <span class="text-muted">Última: <?php echo date('d/m/Y', strtotime($row['ultima_compra'])); ?></span>
                                <?php endif; ?>
                            </small>
                        </td>
                        <td>
                            <div class="btn-group" role="group">
                                <a href="perfil.php?id=<?php echo $row['id_cliente']; ?>" class="btn btn-sm btn-outline-primary" title="Ver perfil"><i class="bi bi-eye"></i></a>
                                <button class="btn btn-sm btn-outline-warning" title="Editar" onclick="editarCliente(<?php echo $row['id_cliente']; ?>)"><i class="bi bi-pencil"></i></button>
                                <?php if($tieneDeuda || $tieneVacios): ?>
                                <a href="../cobranza/index.php?cliente=<?php echo $row['id_cliente']; ?>" class="btn btn-sm btn-outline-danger" title="Cobrar"><i class="bi bi-cash"></i></a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalCliente" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form action="guardar.php" method="POST" id="formCliente">
          <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-person-plus"></i> Registrar Nuevo Cliente</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row">
                <div class="col-12 col-md-6 mb-3">
                    <label class="form-label">Nombre del Cliente / Negocio <span class="text-danger">*</span></label>
                    <input type="text" name="nombre" class="form-control" required>
                </div>
                <div class="col-12 col-md-6 mb-3">
                    <label class="form-label">RIF o Cédula <span class="text-danger">*</span></label>
                    <input type="text" name="rif" class="form-control" required>
                </div>
            </div>
            <div class="row">
                <div class="col-12 col-md-6 mb-3">
                    <label class="form-label">Teléfono</label>
                    <input type="text" name="telefono" class="form-control">
                </div>
                <div class="col-12 col-md-6 mb-3">
                    <label class="form-label">Tipo de Cliente <span class="text-danger">*</span></label>
                    <select name="tipo" class="form-select" required>
                        <option value="">-- Seleccione --</option>
                        <option value="Mayorista">Mayorista (Crédito)</option>
                        <option value="Detal">Detal (Contado)</option>
                    </select>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Dirección</label>
                <textarea name="direccion" class="form-control" rows="2"></textarea>
            </div>
            <div class="row">
                <div class="col-12 col-md-6 mb-3">
                    <label class="form-label">Límite de Crédito ($)</label>
                    <input type="number" name="limite_credito" class="form-control" value="1000" step="0.01">
                    <small class="text-muted">Solo para clientes Mayorista</small>
                </div>
                <div class="col-12 col-md-6 mb-3">
                    <label class="form-label">Días de Crédito</label>
                    <input type="number" name="dias_credito" class="form-control" value="7">
                    <small class="text-muted">Días para pagar (solo Mayorista)</small>
                </div>
            </div>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> Los clientes tipo "Mayorista" tendrán acceso a crédito. 
                Los clientes "Detal" deben pagar al contado.
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary">Guardar Cliente</button>
          </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="modalEditarCliente" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      </div>
  </div>
</div>

<script>
// FUNCIÓN CORREGIDA: Ahora abre el archivo correcto
function exportarClientes() {
    window.open('reporte_clientes.php', '_blank');
}

// Función Filtros (Actualizada para filtrar Tabla y Cards Móviles)
function aplicarFiltros() {
    const search = $('#searchClientes').val().toLowerCase();
    const tipo = $('#filterTipo').val();
    const deuda = $('#filterDeuda').val();
    
    // 1. Filtrado para vista desktop (TABLA)
    $('#tablaClientes tbody tr').each(function() {
        const text = $(this).text().toLowerCase();
        const tipoCliente = $(this).find('td:nth-child(5)').text();
        const tieneDeuda = $(this).find('td:nth-child(6)').text().includes('$');
        
        let show = true;
        if (search && !text.includes(search)) show = false;
        if (tipo && !tipoCliente.includes(tipo)) show = false;
        if (deuda === 'con_deuda' && !tieneDeuda) show = false;
        if (deuda === 'sin_deuda' && tieneDeuda) show = false;
        
        $(this).toggle(show);
    });
    
    // 2. Filtrado para vista móvil (CARDS)
    $('.mobile-client-card').each(function() {
        const nombre = $(this).find('.cliente-nombre').text().toLowerCase();
        const tipoCliente = $(this).find('.cliente-tipo').text();
        const deudaTexto = $(this).find('.cliente-deuda-text').text();
        const tieneDeuda = deudaTexto.includes('$');
        
        let show = true;
        if (search && !nombre.includes(search)) show = false;
        if (tipo && !tipoCliente.includes(tipo)) show = false;
        if (deuda === 'con_deuda' && !tieneDeuda) show = false;
        if (deuda === 'sin_deuda' && tieneDeuda) show = false;
        
        $(this).toggle(show);
    });
}

function editarCliente(id) {
    $.ajax({
        url: 'editar.php',
        type: 'GET',
        data: { id: id },
        success: function(response) {
            $('#modalEditarCliente .modal-content').html(response);
            $('#modalEditarCliente').modal('show');
        }
    });
}

$(function () { $('[data-bs-toggle="tooltip"]').tooltip(); });
</script>

<?php include '../../includes/footer.php'; ?>