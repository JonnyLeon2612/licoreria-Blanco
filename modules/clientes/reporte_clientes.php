<?php
// modules/clientes/reporte_clientes.php
include '../../config/db.php';
date_default_timezone_set('America/Caracas');

// Consultar todos los clientes ordenados alfabéticamente
$sql = "SELECT * FROM clientes ORDER BY nombre_cliente ASC";
$clientes = $pdo->query($sql)->fetchAll();
$total_clientes = count($clientes);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Clientes</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #333; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .empresa { font-size: 18px; font-weight: bold; }
        .fecha { font-size: 10px; color: #666; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background-color: #f2f2f2; border-bottom: 1px solid #999; padding: 8px; text-align: left; }
        td { border-bottom: 1px solid #ddd; padding: 8px; vertical-align: top; }
        .text-center { text-align: center; }
        .badge { 
            padding: 3px 8px; border-radius: 10px; font-size: 10px; font-weight: bold; color: white;
            display: inline-block;
        }
        .bg-mayorista { background-color: #007bff; } /* Azul */
        .bg-detal { background-color: #6c757d; } /* Gris */
        
        /* Ocultar botón al imprimir */
        @media print {
            .no-print { display: none; }
        }
        .btn-print {
            padding: 10px 20px; background: #28a745; color: white; 
            border: none; cursor: pointer; border-radius: 5px; font-size: 14px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>

    <div class="no-print text-center">
        <button onclick="window.print()" class="btn-print">🖨️ Imprimir / Guardar como PDF</button>
    </div>

    <div class="header">
        <div class="empresa">ABASTO COMERCIAL BLANCO</div>
        <div>Directorio General de Clientes</div>
        <div class="fecha">Generado el: <?php echo date('d/m/Y h:i A'); ?></div>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Nombre / Razón Social</th>
                <th>RIF / Cédula</th>
                <th>Teléfono</th>
                <th class="text-center">Tipo</th>
                <th>Dirección</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $i = 1;
            foreach ($clientes as $c): 
                $tipoClass = ($c['tipo_cliente'] == 'Mayorista') ? 'bg-mayorista' : 'bg-detal';
            ?>
            <tr>
                <td><?php echo $i++; ?></td>
                <td><strong><?php echo strtoupper($c['nombre_cliente']); ?></strong></td>
                <td><?php echo $c['rif_cedula']; ?></td>
                <td><?php echo $c['telefono'] ?? '-'; ?></td>
                <td class="text-center">
                    <span class="badge <?php echo $tipoClass; ?>">
                        <?php echo $c['tipo_cliente']; ?>
                    </span>
                </td>
                <td><?php echo $c['direccion'] ?? 'No registrada'; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div style="margin-top: 20px; font-weight: bold;">
        Total Registrados: <?php echo $total_clientes; ?> clientes.
    </div>

    <div style="margin-top: 30px; font-size: 10px; text-align: center; color: #999;">
        SIGIB Licorería Blanco - Fin del Reporte
    </div>

</body>
</html>