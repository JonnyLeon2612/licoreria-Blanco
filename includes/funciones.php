<?php
// includes/funciones.php

/**
 * Formatear número como moneda
 */
function formato_moneda($valor) {
    return '$' . number_format($valor, 2, '.', ',');
}

/**
 * Formatear número con separadores de miles
 */
function formato_numero($valor) {
    return number_format($valor, 0, '.', ',');
}

/**
 * Calcular edad en días desde una fecha
 */
function dias_desde($fecha) {
    if (!$fecha) return 0;
    
    $fecha_dt = new DateTime($fecha);
    $hoy = new DateTime();
    $diferencia = $hoy->diff($fecha_dt);
    
    return $diferencia->days;
}

/**
 * Obtener estado de stock basado en cantidad
 */
function estado_stock($cantidad, $minimo = 10) {
    if ($cantidad == 0) {
        return ['texto' => 'Sin Stock', 'color' => 'danger'];
    } elseif ($cantidad < 5) {
        return ['texto' => 'Muy Bajo', 'color' => 'danger'];
    } elseif ($cantidad < $minimo) {
        return ['texto' => 'Bajo', 'color' => 'warning'];
    } elseif ($cantidad < ($minimo * 2)) {
        return ['texto' => 'Normal', 'color' => 'info'];
    } else {
        return ['texto' => 'Óptimo', 'color' => 'success'];
    }
}

/**
 * Obtener estado de pago basado en estado y días de mora
 */
function estado_pago($estado, $fecha_ultimo_pago = null) {
    switch ($estado) {
        case 'Pagado':
            return ['texto' => 'Pagado', 'color' => 'success', 'icono' => 'check-circle'];
        case 'Abonado':
            return ['texto' => 'Abonado', 'color' => 'info', 'icono' => 'clock'];
        case 'Pendiente':
            $dias_mora = $fecha_ultimo_pago ? dias_desde($fecha_ultimo_pago) : 0;
            
            if ($dias_mora > 30) {
                return ['texto' => 'Vencido (' . $dias_mora . 'd)', 'color' => 'danger', 'icono' => 'exclamation-triangle'];
            } elseif ($dias_mora > 15) {
                return ['texto' => 'Atrasado (' . $dias_mora . 'd)', 'color' => 'warning', 'icono' => 'exclamation-circle'];
            } else {
                return ['texto' => 'Pendiente', 'color' => 'warning', 'icono' => 'clock'];
            }
        default:
            return ['texto' => $estado, 'color' => 'secondary', 'icono' => 'question-circle'];
    }
}

/**
 * Validar si un RIF es válido (formato básico)
 */
function validar_rif($rif) {
    $patron = '/^[JGVEP][-]?\d{8}[-]?\d$/';
    return preg_match($patron, $rif);
}

/**
 * Generar código único para transacción
 */
function generar_codigo($tipo = 'VENTA', $id = 0) {
    $prefijo = '';
    
    switch ($tipo) {
        case 'VENTA': $prefijo = 'VEN'; break;
        case 'ABONO': $prefijo = 'ABO'; break;
        case 'PRODUCTO': $prefijo = 'PRO'; break;
        case 'CLIENTE': $prefijo = 'CLI'; break;
        default: $prefijo = 'DOC';
    }
    
    $fecha = date('Ymd');
    $id_formateado = str_pad($id, 6, '0', STR_PAD_LEFT);
    
    return $prefijo . '-' . $fecha . '-' . $id_formateado;
}

/**
 * Enviar notificación (simulada)
 */
function enviar_notificacion($cliente_id, $tipo, $mensaje) {
    global $pdo;
    
    // En una implementación real, aquí se integraría con WhatsApp, Email, SMS, etc.
    // Por ahora, solo registramos en la base de datos
    
    try {
        $sql = "INSERT INTO notificaciones (id_cliente, tipo, mensaje, fecha_envio, estado) 
                VALUES (:cliente, :tipo, :mensaje, NOW(), 'PENDIENTE')";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':cliente' => $cliente_id,
            ':tipo' => $tipo,
            ':mensaje' => $mensaje
        ]);
        
        return true;
    } catch (Exception $e) {
        error_log("Error enviando notificación: " . $e->getMessage());
        return false;
    }
}

/**
 * Calcular porcentaje de progreso
 */
function calcular_porcentaje($actual, $total) {
    if ($total <= 0) return 0;
    return min(100, ($actual / $total) * 100);
}

/**
 * Obtener color para porcentaje
 */
function color_porcentaje($porcentaje) {
    if ($porcentaje >= 80) return 'danger';
    if ($porcentaje >= 60) return 'warning';
    if ($porcentaje >= 40) return 'info';
    return 'success';
}

/**
 * Sanitizar entrada de datos
 */
function sanitizar($dato) {
    if (is_array($dato)) {
        return array_map('sanitizar', $dato);
    }
    
    return htmlspecialchars(trim($dato), ENT_QUOTES, 'UTF-8');
}

/**
 * Verificar permisos de usuario (simplificado)
 */
function tiene_permiso($permiso) {
    // En una implementación real, verificaría contra roles de usuario
    // Por ahora, todos los usuarios tienen acceso completo
    return true;
}
function obtenerDeudaTotal() {
    global $conexion;
    $query = "SELECT SUM(deuda) as total FROM clientes WHERE estado = 'Activo' OR estado = 'Moroso'";
    $result = mysqli_query($conexion, $query);
    $row = mysqli_fetch_assoc($result);
    return $row['total'] ?? 0;
}

// Obtener número de clientes morosos
function obtenerClientesMorosos() {
    global $conexion;
    $query = "SELECT COUNT(*) as total FROM clientes WHERE deuda > 0 AND (estado = 'Activo' OR estado = 'Moroso')";
    $result = mysqli_query($conexion, $query);
    $row = mysqli_fetch_assoc($result);
    return $row['total'] ?? 0;
}

// Obtener ventas de hoy
function obtenerVentasHoy() {
    global $conexion;
    $hoy = date('Y-m-d');
    $query = "SELECT SUM(total) as total FROM ventas WHERE fecha = '$hoy' AND estado = 'Completado'";
    $result = mysqli_query($conexion, $query);
    $row = mysqli_fetch_assoc($result);
    return $row['total'] ?? 0;
}

// Obtener productos con stock bajo
function obtenerStockBajo() {
    global $conexion;
    $query = "SELECT COUNT(*) as total FROM productos WHERE stock <= stock_minimo";
    $result = mysqli_query($conexion, $query);
    $row = mysqli_fetch_assoc($result);
    return $row['total'] ?? 0;
}

// Obtener vacíos pendientes
function obtenerVaciosPendientes() {
    global $conexion;
    $query = "SELECT SUM(vacios) as total FROM clientes WHERE vacios > 0";
    $result = mysqli_query($conexion, $query);
    $row = mysqli_fetch_assoc($result);
    return $row['total'] ?? 0;
}
?>

