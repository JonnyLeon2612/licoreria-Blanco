<?php
// modules/inventario/reporte_inventario.php
include '../../config/db.php';
date_default_timezone_set('America/Caracas');

// Consultamos todo el inventario
$sql = "SELECT * FROM productos ORDER BY nombre_producto ASC";
$productos = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// ---------------------------------------------------------
// CÁLCULOS (Matemática oculta antes de mostrar nada)
// ---------------------------------------------------------
$total_items = 0;
$valor_total_inventario = 0;
$existencia_total_llenos = 0;
$total_vacios_patio = 0; // Aquí acumularemos el dato para el jefe

foreach ($productos as $p) {
    $total_items++;
    
    // 1. Sumar existencias llenas y dinero
    $existencia_total_llenos += $p['stock_lleno'];
    $valor_total_inventario += ($p['stock_lleno'] * $p['precio_venta_usd']);

    // 2. Lógica Inteligente de Vacíos (La misma del Index)
    // Suma residuos viejos + stock de productos que sean plástico
    $es_plastico = (stripos($p['nombre_producto'], 'GAVERA') !== false || 
                    stripos($p['nombre_producto'], 'VACIO') !== false || 
                    stripos($p['nombre_producto'], 'PLASTICO') !== false);
    
    if ($es_plastico) {
        $total_vacios_patio += $p['stock_lleno'];
    }
    
    $total_vacios_patio += $p['stock_vacio']; // Sumar residuo oculto
}
// ---------------------------------------------------------
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Inventario</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 12px; color: #333; margin: 20px; }
        
        /* Cabecera */
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #444; padding-bottom: 10px; }
        .empresa { font-size: 20px; font-weight: bold; margin-bottom: 5px; text-transform: uppercase; }
        .subtitulo { font-size: 14px; color: #666; }
        
        /* Tabla */
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th { background-color: #f2f2f2; border-bottom: 2px solid #999; padding: 8px; text-align: left; font-size: 11px; text-transform: uppercase; }
        td { border-bottom: 1px solid #ddd; padding: 6px 8px; vertical-align: middle; }
        
        /* Alineaciones */
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .fw-bold { font-weight: bold; }
        
        /* Resaltados */
        .col-existencia { background-color: #e8f5e9; font-weight: bold; color: #1b5e20; }
        .badge { padding: 2px 6px; border-radius: 4px; font-size: 9px; color: white; display: inline-block; }
        .bg-ret { background-color: #17a2b8; } /* Azul */
        .bg-des { background-color: #6c757d; } /* Gris */

        /* SECCIÓN ESTÉTICA DE TOTALES (Lo que pediste) */
        .resumen-container {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
            border-top: 2px solid #333;
            padding-top: 15px;
        }
        .card-resumen {
            width: 30%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-align: center;
        }
        .card-titulo { font-size: 10px; text-transform: uppercase; color: #666; margin-bottom: 5px; }
        .card-valor { font-size: 16px; font-weight: bold; }
        
        .destacado-vacio { background-color: #fff3cd; border-color: #ffecb5; color: #856404; }
        .destacado-dinero { background-color: #d1ecf1; border-color: #bee5eb; color: #0c5460; }

        /* Botón imprimir */
        @media print { .no-print { display: none; } }
        .btn-print {
            position: fixed; top: 20px; right: 20px;
            padding: 10px 20px; background: #28a745; color: white; 
            border: none; cursor: pointer; border-radius: 5px; font-weight: bold;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>

    <button onclick="window.print()" class="btn-print no-print">🖨️ Imprimir</button>

    <div class="header">
        <div class="empresa">Abasto Comercial Blanco</div>
        <div class="subtitulo">Reporte General de Existencias</div>
        <div style="font-size: 10px; margin-top: 5px;">Generado el: <?php echo date('d/m/Y h:i A'); ?></div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 45%;">Producto</th>
                <th style="width: 15%;" class="text-center">Tipo</th>
                <th style="width: 15%;" class="text-right">Precio ($)</th>
                <th style="width: 10%;" class="text-center">Existencia</th>
                <th style="width: 10%;" class="text-right">Valor Total</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $i = 1;
            foreach ($productos as $p): 
                $valor_fila = $p['stock_lleno'] * $p['precio_venta_usd'];
            ?>
            <tr>
                <td><?php echo $i++; ?></td>
                <td>
                    <span class="fw-bold"><?php echo $p['nombre_producto']; ?></span>
                </td>
                <td class="text-center">
                    <?php if($p['es_retornable']): ?>
                        <span class="badge bg-ret">RETORNABLE</span>
                    <?php else: ?>
                        <span class="badge bg-des">DESECHABLE</span>
                    <?php endif; ?>
                </td>
                <td class="text-right"><?php echo number_format($p['precio_venta_usd'], 2); ?></td>
                
                <td class="text-center col-existencia"><?php echo number_format($p['stock_lleno']); ?></td>
                
                <td class="text-right"><?php echo number_format($valor_fila, 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="resumen-container">
        
        <div class="card-resumen">
            <div class="card-titulo">Unidades en Piso</div>
            <div class="card-valor"><?php echo number_format($existencia_total_llenos); ?></div>
        </div>

        <div class="card-resumen destacado-vacio">
            <div class="card-titulo">Total Vacíos</div>
            <div class="card-valor"><?php echo number_format($total_vacios_patio); ?></div>
        </div>

        <div class="card-resumen destacado-dinero">
            <div class="card-titulo">Capital Inventario</div>
            <div class="card-valor">$<?php echo number_format($valor_total_inventario, 2); ?></div>
        </div>

    </div>

    <div style="margin-top: 30px; text-align: center; font-size: 10px; color: #999;">
        Fin del Reporte - SIGIB Licorería Blanco
    </div>

</body>
</html>