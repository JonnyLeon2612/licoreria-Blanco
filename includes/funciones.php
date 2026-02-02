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
    
    try {
        $fecha_dt = new DateTime($fecha);
        $hoy = new DateTime();
        $diferencia = $hoy->diff($fecha_dt);
        return $diferencia->days;
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Obtener estado de stock basado en cantidad
 */
function estado_stock($cantidad, $minimo = 10) {
    if ($cantidad <= 0) {
        return ['texto' => 'Sin Stock', 'color' => 'danger'];
    } elseif ($cantidad < 5) {
        return ['texto' => 'Muy Bajo', 'color' => 'danger'];
    } elseif ($cantidad < $minimo) {
        return ['texto' => 'Bajo', 'color' => 'warning'];
    } else {
        return ['texto' => 'Óptimo', 'color' => 'success'];
    }
}

/**
 * Obtener estado de pago basado en monto total y pagado
 */
function estado_pago_venta($total, $pagado) {
    if ($pagado >= $total && $total > 0) {
        return ['texto' => 'Pagado', 'color' => 'success', 'icono' => 'check-circle'];
    } elseif ($pagado > 0) {
        return ['texto' => 'Abonado', 'color' => 'info', 'icono' => 'clock'];
    } else {
        return ['texto' => 'Pendiente', 'color' => 'danger', 'icono' => 'exclamation-triangle'];
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
    return true; // Acceso total por ahora
}

/* ==========================================================================
   FUNCIONES DEL DASHBOARD (UNIFICADAS A PDO)
   ========================================================================== */

/**
 * Obtener Deuda Total de todos los clientes
 */
function obtenerDeudaTotal() {
    global $pdo;
    $sql = "SELECT SUM(saldo_dinero_usd) FROM cuentas_por_cobrar";
    return $pdo->query($sql)->fetchColumn() ?? 0;
}

/**
 * Obtener número de clientes con deuda pendiente
 */
function obtenerClientesMorosos() {
    global $pdo;
    $sql = "SELECT COUNT(DISTINCT id_cliente) FROM cuentas_por_cobrar WHERE saldo_dinero_usd > 0";
    return $pdo->query($sql)->fetchColumn() ?? 0;
}

/**
 * Obtener sumatoria de ventas realizadas hoy
 */
function obtenerVentasHoy() {
    global $pdo;
    $hoy = date('Y-m-d');
    $sql = "SELECT SUM(total_venta) FROM ventas WHERE DATE(fecha_venta) = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$hoy]);
    return $stmt->fetchColumn() ?? 0;
}

/**
 * Obtener cantidad de productos con stock crítico
 */
function obtenerStockBajo() {
    global $pdo;
    // Se considera bajo si es menor o igual a 10 cajas
    $sql = "SELECT COUNT(*) FROM productos WHERE stock_lleno <= 10";
    return $pdo->query($sql)->fetchColumn() ?? 0;
}

/**
 * Obtener total de envases vacíos que deben los clientes
 */
function obtenerVaciosPendientes() {
    global $pdo;
    $sql = "SELECT SUM(saldo_vacios) FROM cuentas_por_cobrar WHERE saldo_vacios > 0";
    return $pdo->query($sql)->fetchColumn() ?? 0;
}
?>