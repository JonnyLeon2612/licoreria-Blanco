<?php
// modules/ventas/index.php
$page_title = "Registro de Ventas";
include '../../config/db.php';
include '../../includes/header.php';

// Obtener ID de cliente si viene por parámetro
$cliente_filtro = isset($_GET['cliente']) ? intval($_GET['cliente']) : 0;

// Cargar Clientes para el select
$sql_clientes = "SELECT * FROM clientes ORDER BY nombre_cliente ASC";
$clientes = $pdo->query($sql_clientes)->fetchAll();

// Cargar Productos con Stock > 0
$sql_productos = "SELECT * FROM productos WHERE stock_lleno > 0 ORDER BY nombre_producto ASC";
$productos = $pdo->query($sql_productos)->fetchAll();

// Obtener cliente específico si hay filtro
$cliente_especifico = null;
if ($cliente_filtro > 0) {
    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id_cliente = ?");
    $stmt->execute([$cliente_filtro]);
    $cliente_especifico = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0"><i class="bi bi-cart-plus text-success"></i> Registro de Ventas</h1>
        <p class="text-muted">Complete el formulario para procesar una nueva venta</p>
    </div>
    <div>
        <a href="historial.php" class="btn btn-outline-primary">
            <i class="bi bi-clock-history"></i> Historial
        </a>
        <a href="../dashboard/index.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Dashboard
        </a>
    </div>
</div>

<!-- Alerta si cliente tiene deuda -->
<?php if ($cliente_filtro > 0 && $cliente_especifico): 
    $deuda = $pdo->query("SELECT saldo_dinero_usd, saldo_vacios FROM cuentas_por_cobrar WHERE id_cliente = $cliente_filtro")->fetch();
    if ($deuda['saldo_dinero_usd'] > 0 || $deuda['saldo_vacios'] > 0): ?>
    <div class="alert alert-warning alert-custom">
        <div class="d-flex align-items-center">
            <i class="bi bi-exclamation-triangle fs-4 me-3"></i>
            <div>
                <h5 class="alert-heading">Cliente con deuda pendiente</h5>
                <p class="mb-1">
                    <strong><?php echo $cliente_especifico['nombre_cliente']; ?></strong> tiene una deuda de 
                    <span class="text-danger fw-bold">$<?php echo number_format($deuda['saldo_dinero_usd'], 2); ?></span> 
                    y debe <span class="text-warning fw-bold"><?php echo $deuda['saldo_vacios']; ?> vacíos</span>.
                </p>
                <small>Considere cobrar la deuda antes de realizar una nueva venta.</small>
            </div>
        </div>
    </div>
    <?php endif; ?>
<?php endif; ?>

<div class="row">
    <!-- Formulario de Venta -->
    <div class="col-lg-5">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-cart-plus"></i> Nueva Venta</h5>
            </div>
            <div class="card-body">
                <form id="formVenta" action="guardar.php" method="POST">
                    
                    <!-- Información del Cliente -->
                    <div class="mb-4">
                        <h6 class="border-bottom pb-2">
                            <i class="bi bi-person"></i> Información del Cliente
                        </h6>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Cliente <span class="text-danger">*</span></label>
                            <select name="id_cliente" id="clienteSelect" class="form-select" required 
                                    onchange="cargarDeudaCliente(this.value)">
                                <option value="">-- Seleccione Cliente --</option>
                                <?php foreach($clientes as $c): 
                                    $selected = ($cliente_filtro == $c['id_cliente']) ? 'selected' : '';
                                ?>
                                    <option value="<?php echo $c['id_cliente']; ?>" <?php echo $selected; ?>>
                                        <?php echo $c['nombre_cliente']; ?> (<?php echo $c['tipo_cliente']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Información de deuda del cliente (se carga dinámicamente) -->
                        <div id="infoDeudaCliente" class="d-none">
                            <div class="alert alert-light border">
                                <div class="row small">
                                    <div class="col-6">
                                        <span class="text-muted">Deuda actual:</span><br>
                                        <span id="deudaActual" class="fw-bold">$0.00</span>
                                    </div>
                                    <div class="col-6">
                                        <span class="text-muted">Vacíos pendientes:</span><br>
                                        <span id="vaciosPendientes" class="fw-bold">0</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Selección de Productos -->
                    <div class="mb-4">
                        <h6 class="border-bottom pb-2">
                            <i class="bi bi-box"></i> Selección de Productos
                        </h6>
                        
                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Producto</label>
                                <select id="productoSelect" class="form-select">
                                    <option value="">-- Seleccione Producto --</option>
                                    <?php foreach($productos as $p): ?>
                                        <option value="<?php echo $p['id_producto']; ?>" 
                                                data-precio="<?php echo $p['precio_venta_usd']; ?>"
                                                data-retornable="<?php echo $p['es_retornable']; ?>"
                                                data-nombre="<?php echo htmlspecialchars($p['nombre_producto']); ?>"
                                                data-stock="<?php echo $p['stock_lleno']; ?>">
                                            <?php echo $p['nombre_producto']; ?> 
                                            ($<?php echo $p['precio_venta_usd']; ?>)
                                            <span class="text-muted">(Stock: <?php echo $p['stock_lleno']; ?>)</span>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Cantidad (Cajas)</label>
                                <input type="number" id="cantidadInput" class="form-control" min="1" value="1" max="100">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="button" class="btn btn-success w-100" onclick="agregarProducto()">
                                    <i class="bi bi-plus-lg"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Info del producto seleccionado -->
                        <div id="infoProducto" class="alert alert-info d-none">
                            <div class="row small">
                                <div class="col-6">
                                    <span class="text-muted">Stock disponible:</span><br>
                                    <span id="stockDisponible" class="fw-bold">0</span>
                                </div>
                                <div class="col-6">
                                    <span class="text-muted">Tipo:</span><br>
                                    <span id="tipoProducto" class="fw-bold">-</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Resumen y Pago -->
                    <div class="mb-4">
                        <h6 class="border-bottom pb-2">
                            <i class="bi bi-calculator"></i> Resumen y Pago
                        </h6>
                        
                        <div class="card bg-light border">
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-6">
                                        <span class="fw-bold">Total a Pagar:</span>
                                    </div>
                                    <div class="col-6 text-end">
                                        <span class="fw-bold text-success fs-5" id="totalMontoDisplay">$0.00</span>
                                        <input type="hidden" name="total_venta" id="totalVentaInput">
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-6">
                                        <span class="fw-bold text-danger">Vacíos a Devolver:</span>
                                    </div>
                                    <div class="col-6 text-end">
                                        <span class="fw-bold text-danger fs-5" id="totalVaciosDisplay">0</span>
                                        <input type="hidden" name="total_vacios_esperados" id="totalVaciosInput">
                                    </div>
                                </div>
                                
                                <hr>
                                
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="bi bi-cash-coin text-success"></i> Dinero Recibido ($)
                                    </label>
                                    <input type="number" step="0.01" name="monto_pagado" id="montoPagado" 
                                           class="form-control" required placeholder="0.00" oninput="calcularCambio()">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="bi bi-box-arrow-in-down text-warning"></i> Vacíos Entregados por Cliente
                                    </label>
                                    <input type="number" name="vacios_recibidos" id="vaciosRecibidos" 
                                           class="form-control" required placeholder="0" oninput="calcularVaciosPendientes()">
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-6">
                                        <span class="text-muted">Cambio:</span><br>
                                        <span id="cambioDisplay" class="fw-bold">$0.00</span>
                                    </div>
                                    <div class="col-6">
                                        <span class="text-muted">Vacíos pendientes:</span><br>
                                        <span id="vaciosPendientesDisplay" class="fw-bold text-danger">0</span>
                                    </div>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-success btn-lg">
                                        <i class="bi bi-check-circle"></i> Procesar Venta
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Detalle de productos en JSON -->
                    <input type="hidden" name="detalle_productos" id="detalleProductosJSON">
                </form>
            </div>
        </div>
    </div>

    <!-- Carrito de Productos -->
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i class="bi bi-cart-check"></i> Detalle del Pedido</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Producto</th>
                                <th class="text-center">Cant.</th>
                                <th class="text-end">Precio U.</th>
                                <th class="text-end">Subtotal</th>
                                <th class="text-center">Vacíos</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="tablaProductos">
                            <!-- Productos se agregan aquí dinámicamente -->
                        </tbody>
                    </table>
                </div>
                
                <!-- Mensaje cuando no hay productos -->
                <div id="emptyMessage" class="text-center p-5 text-muted">
                    <i class="bi bi-cart-x" style="font-size: 3rem;"></i>
                    <h5 class="mt-3">No hay productos agregados</h5>
                    <p class="mb-0">Seleccione productos del panel izquierdo</p>
                </div>
                
                <!-- Resumen del carrito -->
                <div id="resumenCarrito" class="p-3 bg-light border-top d-none">
                    <div class="row">
                        <div class="col-6">
                            <span class="fw-bold">Subtotal:</span>
                        </div>
                        <div class="col-6 text-end">
                            <span class="fw-bold" id="subtotalDisplay">$0.00</span>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <span class="fw-bold">Total productos:</span>
                        </div>
                        <div class="col-6 text-end">
                            <span class="fw-bold" id="totalProductosDisplay">0</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Resumen rápido -->
        <div class="card shadow-sm mt-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bi bi-lightning-charge"></i> Ventas Rápidas</h5>
            </div>
            <div class="card-body">
                <div class="row g-2">
                    <?php 
                    // Productos más vendidos para acceso rápido
                    $productos_rapidos = $pdo->query("
                        SELECT p.* FROM productos p 
                        WHERE p.stock_lleno > 0 
                        ORDER BY p.nombre_producto ASC 
                        LIMIT 6
                    ")->fetchAll();
                    
                    foreach($productos_rapidos as $p): ?>
                    <div class="col-md-4">
                        <button type="button" class="btn btn-outline-primary w-100 text-start" 
                                onclick="agregarProductoRapido(<?php echo $p['id_producto']; ?>, '<?php echo htmlspecialchars($p['nombre_producto']); ?>', <?php echo $p['precio_venta_usd']; ?>, <?php echo $p['es_retornable']; ?>)">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="d-block fw-bold"><?php echo $p['nombre_producto']; ?></small>
                                    <small class="text-muted">$<?php echo $p['precio_venta_usd']; ?></small>
                                </div>
                                <i class="bi bi-plus-circle"></i>
                            </div>
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Variables globales
let carrito = [];
let totalVenta = 0;
let totalVacios = 0;
let subtotal = 0;
let totalProductos = 0;

// Actualizar información del producto seleccionado
document.getElementById('productoSelect').addEventListener('change', function() {
    const option = this.options[this.selectedIndex];
    if (option.value) {
        const stock = option.getAttribute('data-stock');
        const retornable = option.getAttribute('data-retornable') == "1";
        
        document.getElementById('stockDisponible').textContent = stock;
        document.getElementById('tipoProducto').textContent = retornable ? 'Retornable' : 'Desechable';
        document.getElementById('infoProducto').classList.remove('d-none');
        document.getElementById('cantidadInput').max = parseInt(stock);
    } else {
        document.getElementById('infoProducto').classList.add('d-none');
    }
});

// Función para agregar producto al carrito
function agregarProducto() {
    const select = document.getElementById('productoSelect');
    const cantidad = parseInt(document.getElementById('cantidadInput').value);
    
    if(select.value === "" || cantidad < 1) {
        alert("Seleccione un producto y cantidad válida");
        return;
    }

    const option = select.options[select.selectedIndex];
    const stock = parseInt(option.getAttribute('data-stock'));
    
    if (cantidad > stock) {
        alert(`Stock insuficiente. Solo hay ${stock} unidades disponibles.`);
        return;
    }

    const id = select.value;
    const nombre = option.getAttribute('data-nombre');
    const precio = parseFloat(option.getAttribute('data-precio'));
    const esRetornable = option.getAttribute('data-retornable') == "1";

    const subtotalItem = precio * cantidad;
    const vacios = esRetornable ? cantidad : 0;

    // Verificar si el producto ya está en el carrito
    const indexExistente = carrito.findIndex(item => item.id == id);
    
    if (indexExistente >= 0) {
        // Actualizar cantidad si ya existe
        carrito[indexExistente].cantidad += cantidad;
        carrito[indexExistente].subtotal += subtotalItem;
        carrito[indexExistente].vacios += vacios;
    } else {
        // Agregar nuevo producto
        carrito.push({ 
            id, 
            nombre, 
            cantidad, 
            precio, 
            subtotal: subtotalItem, 
            vacios,
            esRetornable 
        });
    }

    actualizarCarrito();
    
    // Resetear inputs
    select.value = "";
    document.getElementById('cantidadInput').value = 1;
    document.getElementById('infoProducto').classList.add('d-none');
}

// Función para agregar producto rápido
function agregarProductoRapido(id, nombre, precio, esRetornable) {
    const cantidad = 1;
    const subtotalItem = precio * cantidad;
    const vacios = esRetornable ? cantidad : 0;

    // Verificar stock primero
    fetch(`check_stock.php?id=${id}&cantidad=${cantidad}`)
        .then(response => response.json())
        .then(data => {
            if (!data.disponible) {
                alert(`Stock insuficiente. Solo hay ${data.stock} unidades disponibles.`);
                return;
            }

            // Verificar si el producto ya está en el carrito
            const indexExistente = carrito.findIndex(item => item.id == id);
            
            if (indexExistente >= 0) {
                carrito[indexExistente].cantidad += cantidad;
                carrito[indexExistente].subtotal += subtotalItem;
                carrito[indexExistente].vacios += vacios;
            } else {
                carrito.push({ 
                    id, 
                    nombre, 
                    cantidad, 
                    precio, 
                    subtotal: subtotalItem, 
                    vacios,
                    esRetornable: esRetornable == 1 
                });
            }

            actualizarCarrito();
        });
}

// Función para actualizar el carrito
function actualizarCarrito() {
    const tbody = document.getElementById('tablaProductos');
    const emptyMsg = document.getElementById('emptyMessage');
    const resumen = document.getElementById('resumenCarrito');
    
    tbody.innerHTML = "";
    
    totalVenta = 0;
    totalVacios = 0;
    subtotal = 0;
    totalProductos = 0;

    if(carrito.length > 0) {
        emptyMsg.style.display = 'none';
        resumen.classList.remove('d-none');
        
        carrito.forEach((item, index) => {
            totalVenta += item.subtotal;
            totalVacios += item.vacios;
            subtotal += item.subtotal;
            totalProductos += item.cantidad;

            tbody.innerHTML += `
                <tr>
                    <td>${item.nombre}</td>
                    <td class="text-center">
                        <div class="input-group input-group-sm" style="width: 100px;">
                            <button class="btn btn-outline-secondary" onclick="cambiarCantidad(${index}, -1)">-</button>
                            <input type="number" class="form-control text-center" value="${item.cantidad}" 
                                   onchange="actualizarCantidad(${index}, this.value)" min="1">
                            <button class="btn btn-outline-secondary" onclick="cambiarCantidad(${index}, 1)">+</button>
                        </div>
                    </td>
                    <td class="text-end">$${item.precio.toFixed(2)}</td>
                    <td class="text-end fw-bold">$${item.subtotal.toFixed(2)}</td>
                    <td class="text-center">
                        ${item.vacios > 0 ? '<span class="badge bg-warning text-dark">'+item.vacios+'</span>' : '<span class="text-muted">-</span>'}
                    </td>
                    <td>
                        <button class="btn btn-sm btn-danger" onclick="eliminarItem(${index})">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        });
    } else {
        emptyMsg.style.display = 'block';
        resumen.classList.add('d-none');
    }

    // Actualizar displays
    document.getElementById('totalMontoDisplay').textContent = "$" + totalVenta.toFixed(2);
    document.getElementById('totalVaciosDisplay').textContent = totalVacios;
    document.getElementById('subtotalDisplay').textContent = "$" + subtotal.toFixed(2);
    document.getElementById('totalProductosDisplay').textContent = totalProductos;

    // Actualizar inputs ocultos
    document.getElementById('totalVentaInput').value = totalVenta;
    document.getElementById('totalVaciosInput').value = totalVacios;
    document.getElementById('detalleProductosJSON').value = JSON.stringify(carrito);
    
    // Calcular cambio y vacíos pendientes
    calcularCambio();
    calcularVaciosPendientes();
}

// Función para cambiar cantidad
function cambiarCantidad(index, delta) {
    const item = carrito[index];
    const nuevaCantidad = item.cantidad + delta;
    
    if (nuevaCantidad < 1) {
        eliminarItem(index);
        return;
    }
    
    // Verificar stock
    fetch(`check_stock.php?id=${item.id}&cantidad=${nuevaCantidad}&excluir=${item.cantidad}`)
        .then(response => response.json())
        .then(data => {
            if (!data.disponible) {
                alert(`Stock insuficiente. Solo hay ${data.stock} unidades disponibles.`);
                return;
            }
            
            item.cantidad = nuevaCantidad;
            item.subtotal = item.precio * nuevaCantidad;
            item.vacios = item.esRetornable ? nuevaCantidad : 0;
            
            actualizarCarrito();
        });
}

// Función para actualizar cantidad manualmente
function actualizarCantidad(index, value) {
    const nuevaCantidad = parseInt(value);
    
    if (isNaN(nuevaCantidad) || nuevaCantidad < 1) {
        eliminarItem(index);
        return;
    }
    
    cambiarCantidad(index, nuevaCantidad - carrito[index].cantidad);
}

// Función para eliminar producto del carrito
function eliminarItem(index) {
    if (confirm("¿Está seguro de eliminar este producto del carrito?")) {
        carrito.splice(index, 1);
        actualizarCarrito();
    }
}

// Función para cargar deuda del cliente
function cargarDeudaCliente(idCliente) {
    if (!idCliente) {
        document.getElementById('infoDeudaCliente').classList.add('d-none');
        return;
    }
    
    fetch(`../api/get_deuda_cliente.php?id=${idCliente}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('deudaActual').textContent = "$" + parseFloat(data.deuda).toFixed(2);
                document.getElementById('vaciosPendientes').textContent = data.vacios;
                document.getElementById('infoDeudaCliente').classList.remove('d-none');
            }
        });
}

// Función para calcular cambio
function calcularCambio() {
    const pagado = parseFloat(document.getElementById('montoPagado').value) || 0;
    const cambio = pagado - totalVenta;
    
    if (cambio >= 0) {
        document.getElementById('cambioDisplay').textContent = "$" + cambio.toFixed(2);
        document.getElementById('cambioDisplay').className = "fw-bold text-success";
    } else {
        document.getElementById('cambioDisplay').textContent = "$" + Math.abs(cambio).toFixed(2);
        document.getElementById('cambioDisplay').className = "fw-bold text-danger";
    }
}

// Función para calcular vacíos pendientes
function calcularVaciosPendientes() {
    const recibidos = parseInt(document.getElementById('vaciosRecibidos').value) || 0;
    const pendientes = totalVacios - recibidos;
    
    document.getElementById('vaciosPendientesDisplay').textContent = pendientes;
    
    if (pendientes > 0) {
        document.getElementById('vaciosPendientesDisplay').className = "fw-bold text-danger";
    } else if (pendientes < 0) {
        document.getElementById('vaciosPendientesDisplay').textContent = "Exceso: " + Math.abs(pendientes);
        document.getElementById('vaciosPendientesDisplay').className = "fw-bold text-warning";
    } else {
        document.getElementById('vaciosPendientesDisplay').className = "fw-bold text-success";
    }
}

// Validar formulario antes de enviar
document.getElementById('formVenta').addEventListener('submit', function(e) {
    if (carrito.length === 0) {
        e.preventDefault();
        alert("Debe agregar al menos un producto a la venta");
        return;
    }
    
    const cliente = document.getElementById('clienteSelect').value;
    if (!cliente) {
        e.preventDefault();
        alert("Debe seleccionar un cliente");
        return;
    }
    
    const pagado = parseFloat(document.getElementById('montoPagado').value) || 0;
    if (pagado < 0) {
        e.preventDefault();
        alert("El monto pagado no puede ser negativo");
        return;
    }
    
    // Confirmar si hay deuda pendiente
    if (totalVenta > pagado) {
        if (!confirm(`El cliente pagará $${pagado.toFixed(2)} pero debe $${totalVenta.toFixed(2)}. ¿Desea crear una deuda de $${(totalVenta - pagado).toFixed(2)}?`)) {
            e.preventDefault();
        }
    }
});

// Inicializar si hay cliente filtrado
<?php if ($cliente_filtro > 0): ?>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('clienteSelect').value = <?php echo $cliente_filtro; ?>;
    cargarDeudaCliente(<?php echo $cliente_filtro; ?>);
});
<?php endif; ?>
</script>

<?php include '../../includes/footer.php'; ?>