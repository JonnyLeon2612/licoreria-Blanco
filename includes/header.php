<?php
// includes/header.php
if (!isset($pdo)) {
    require_once '../config/db.php';
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title><?php echo SITE_NAME; ?> - <?php echo $page_title ?? 'Sistema de Gestión'; ?></title>

    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Chart.js para gráficos -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- DataTables para tablas avanzadas -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

    <!-- Estilos personalizados -->
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
        }
        
        /* --- Efecto de Marca de Agua Global --- */
        body::before {
            content: "";
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            /* Ajusta la ruta según el nombre de tu carpeta principal */
            background-image: url('../../assets/img/image.png');
            background-repeat: no-repeat;
            background-position: right;
            background-size: 70%;
            /* Ajusta este porcentaje para cambiar el tamaño */
            opacity: 0.08;
            /* Controla el nivel de difuminado (0.05 a 0.1 es ideal) */
            filter: grayscale(100%);
            /* Opcional: convierte el logo a blanco y negro */
            z-index: -1;
            /* Asegura que esté detrás de las tablas y tarjetas */
            pointer-events: none;
            /* Permite que el ratón ignore el logo y haga clic en botones */
        }

        /* Ajuste necesario para que el contenedor principal sea transparente */
        body {
            position: relative;
            min-height: 100vh;
            background-color: #0066ff;
            /* Mantenemos tu color de fondo suave */
        }

        main {
            background: transparent !important;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            padding-top: 70px;
        }

        .navbar-brand {
            font-weight: 800;
            font-size: 1.5rem;
        }

        .card {
            border-radius: 10px;
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .card-header {
            border-radius: 10px 10px 0 0 !important;
            font-weight: 600;
        }

        .badge {
            font-size: 0.8em;
            padding: 0.5em 0.8em;
        }

        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            padding: 70px 0 0;
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
            background-color: var(--primary-color);
        }

        .sidebar-sticky {
            position: relative;
            top: 0;
            height: calc(100vh - 70px);
            padding-top: .5rem;
            overflow-x: hidden;
            overflow-y: auto;
        }

        .sidebar .nav-link {
            color: #fff;
            padding: 1rem;
            margin: 0.2rem 1rem;
            border-radius: 8px;
            transition: all 0.3s;
        }

        .sidebar .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
            transform: translateX(5px);
        }

        .sidebar .nav-link.active {
            background-color: var(--secondary-color);
            font-weight: 600;
        }

        .sidebar .nav-link i {
            width: 25px;
            text-align: center;
        }

        .kpi-card {
            text-align: center;
            padding: 1.5rem;
            border-radius: 12px;
            color: white;
            min-height: 180px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .kpi-card h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0.5rem 0;
        }

        .kpi-card .kpi-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
        }

        .kpi-danger {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
        }

        .kpi-warning {
            background: linear-gradient(135deg, #f39c12, #e67e22);
        }

        .kpi-success {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
        }

        .kpi-primary {
            background: linear-gradient(135deg, #3498db, #2980b9);
        }

        .kpi-purple {
            background: linear-gradient(135deg, #8e44ad, #9b59b6);
        }

        .alert-custom {
            border-left: 5px solid;
            border-radius: 8px;
        }

        .table th {
            font-weight: 600;
            background-color: #f8f9fa;
        }

        .btn {
            border-radius: 6px;
            font-weight: 500;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3498db, #2980b9);
            border: none;
        }

        .btn-success {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            border: none;
        }

        .btn-warning {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            border: none;
        }

        .btn-danger {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            border: none;
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 10px 10px 0 0;
        }

        .navbar {
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            background-color: white !important;
        }

        .navbar-dark .navbar-nav .nav-link {
            color: var(--primary-color) !important;
            font-weight: 500;
        }

        .navbar-dark .navbar-brand {
            color: var(--primary-color) !important;
        }

        .status-badge {
            padding: 0.5em 1em;
            border-radius: 20px;
        }

        .status-paid {
            background-color: #d4edda;
            color: #155724;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-partial {
            background-color: #cce5ff;
            color: #004085;
        }

        /* =========== RESPONSIVE MOBILE FIRST =========== */
        @media (max-width: 768px) {
            body {
                padding-top: 60px !important;
                overflow-x: hidden;
            }
            
            .navbar-brand {
                font-size: 1.1rem !important;
            }
            
            .navbar-brand img {
                height: 35px !important;
                margin-right: 5px !important;
            }
            
            .navbar-nav .nav-link {
                padding: 0.5rem 1rem !important;
                font-size: 0.9rem;
            }
            
            .sidebar {
                display: none;
            }
            
            main {
                margin-left: 0 !important;
                width: 100% !important;
                padding: 10px !important;
            }
            
            .container-fluid {
                padding-left: 10px !important;
                padding-right: 10px !important;
            }
            
            .kpi-card {
                padding: 1rem !important;
                min-height: 140px;
            }
            
            .kpi-card h2 {
                font-size: 1.8rem !important;
                margin: 0.3rem 0 !important;
            }
            
            .kpi-card .kpi-icon {
                font-size: 1.5rem !important;
                margin-bottom: 0.5rem !important;
            }
            
            /* Tablas responsivas */
            .table-responsive {
                border: 1px solid #dee2e6;
                border-radius: 8px;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            .table-responsive table {
                margin-bottom: 0;
                min-width: 600px;
            }
            
            /* Botones táctiles */
            .btn, .form-control, .form-select, .form-check-input {
                min-height: 44px !important;
                font-size: 16px !important;
            }
            
            .btn-sm {
                min-height: 36px !important;
                padding: 0.25rem 0.75rem !important;
            }
            
            .card {
                margin-bottom: 15px;
            }
            
            .card-body {
                padding: 1rem;
            }
            
            h1, .h1 {
                font-size: 1.5rem !important;
            }
            
            h2, .h2 {
                font-size: 1.3rem !important;
            }
            
            h3, .h3 {
                font-size: 1.2rem !important;
            }
            
            .modal-dialog {
                margin: 10px;
            }
            
            .modal-content {
                border-radius: 10px;
            }
            
            .form-label {
                font-size: 0.9rem;
                margin-bottom: 0.3rem;
            }
            
            .mb-4 {
                margin-bottom: 1rem !important;
            }
            
            .pt-4 {
                padding-top: 1rem !important;
            }
            
            /* Fix para Select2 en móvil */
            .select2-container {
                width: 100% !important;
            }
            
            /* Fix para DataTables en móvil */
            .dataTables_wrapper .dataTables_filter input {
                min-height: 44px !important;
                font-size: 16px !important;
                width: 100% !important;
                margin-bottom: 10px;
            }
        }

        @media (max-width: 576px) {
            body {
                padding-top: 56px !important;
            }
            
            .navbar-brand span {
                display: none;
            }
            
            .navbar-brand img {
                margin-right: 0 !important;
            }
            
            .col-xl-3.col-md-6.mb-4 {
                flex: 0 0 100% !important;
                max-width: 100% !important;
            }
            
            .fs-4 {
                font-size: 1.1rem !important;
            }
            
            .fs-5 {
                font-size: 1rem !important;
            }
            
            .col-xl-2, .col-xl-3 {
                flex: 0 0 100% !important;
                max-width: 100% !important;
            }
        }

        @media (max-width: 375px) {
            .navbar-brand {
                font-size: 0.9rem !important;
            }
            
            .btn-group .btn {
                padding: 0.25rem 0.5rem !important;
                font-size: 0.8rem !important;
            }
        }

        /* Vista tipo cards para móvil */
        .mobile-card-view {
            display: none;
        }

        @media (max-width: 768px) {
            .mobile-card-view {
                display: block;
            }
            
            .mobile-card-view .table-responsive {
                display: none;
            }
            
            .mobile-card {
                border: 1px solid #dee2e6;
                border-radius: 8px;
                padding: 15px;
                margin-bottom: 10px;
                background: white;
            }
            
            .mobile-card .card-row {
                display: flex;
                justify-content: space-between;
                margin-bottom: 8px;
            }
            
            .mobile-card .card-label {
                font-weight: 600;
                color: #6c757d;
            }
            
            .mobile-card .card-value {
                text-align: right;
            }
            
            .mobile-card .card-actions {
                margin-top: 10px;
                display: flex;
                gap: 5px;
            }
        }

        /* Botón flotante para móvil */
        .mobile-fab {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1050;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            box-shadow: 0 4px 8px rgba(0,0,0,0.3);
            display: none;
        }

        @media (max-width: 768px) {
            .mobile-fab {
                display: flex;
                align-items: center;
                justify-content: center;
            }
        }

        /* BARRA DE NAVEGACIÓN INFERIOR (SOLO MÓVIL) */
        .bottom-nav {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            background: #fff;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
            z-index: 1000;
            height: 65px;
            justify-content: space-around;
            align-items: center;
            padding-bottom: env(safe-area-inset-bottom); /* Para el borde del iPhone */
        }

        .bottom-nav-item {
            text-decoration: none;
            color: #6c757d;
            display: flex;
            flex-direction: column;
            align-items: center;
            font-size: 10px;
            flex-grow: 1;
        }

        .bottom-nav-item i {
            font-size: 24px;
            margin-bottom: 2px;
        }

        .bottom-nav-item.active {
            color: #0d6efd; /* Color primario */
            font-weight: bold;
        }

        /* Mostrar solo en móvil */
        @media (max-width: 768px) {
            .bottom-nav { 
                display: flex; 
            }
            
            body { 
                padding-bottom: 80px !important; /* Espacio para que no tape contenido */
            }
            
            .navbar { 
                display: none !important; 
            }
        }
    </style>
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="../dashboard/index.php">
                <img src="../../assets/img/logo.png" alt="Logo" style="height: 50px; margin-right: 0px; vertical-align: middle;">
                <span class="d-none d-sm-inline">SIGIB Licoreria Blanco</span>
            </a>

            <!-- Botón para mobile -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Navbar items -->
            <div class="collapse navbar-collapse" id="navbarMain">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../dashboard/index.php">
                            <i class="bi bi-speedometer2"></i> <span class="d-none d-md-inline">Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../clientes/index.php">
                            <i class="bi bi-people"></i> <span class="d-none d-md-inline">Clientes</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../inventario/index.php">
                            <i class="bi bi-boxes"></i> <span class="d-none d-md-inline">Inventario</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../ventas/index.php">
                            <i class="bi bi-cart-plus"></i> <span class="d-none d-md-inline">Ventas</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../cobranza/index.php">
                            <i class="bi bi-cash-coin"></i> <span class="d-none d-md-inline">Cobranza</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Sidebar (solo para desktop) -->
    <div class="d-none d-lg-block col-lg-2 sidebar">
        <div class="sidebar-sticky">
            <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-white-50">
                <span>Navegación Principal</span>
            </h6>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' && strpos($_SERVER['REQUEST_URI'], 'dashboard') ? 'active' : ''; ?>"
                        href="../dashboard/index.php">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo strpos($_SERVER['REQUEST_URI'], 'clientes') ? 'active' : ''; ?>"
                        href="../clientes/index.php">
                        <i class="bi bi-people-fill"></i> Clientes
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo strpos($_SERVER['REQUEST_URI'], 'inventario') ? 'active' : ''; ?>"
                        href="../inventario/index.php">
                        <i class="bi bi-box-seam-fill"></i> Inventario
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo strpos($_SERVER['REQUEST_URI'], 'ventas') ? 'active' : ''; ?>"
                        href="../ventas/index.php">
                        <i class="bi bi-cart-check-fill"></i> Ventas
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo strpos($_SERVER['REQUEST_URI'], 'cobranza') ? 'active' : ''; ?>"
                        href="../cobranza/index.php">
                        <i class="bi bi-cash-stack"></i> Cobranza
                    </a>
                </li>
            </ul>

        </div>
    </div>

    <!-- Contenido Principal -->
    <main class="col-lg-10 ms-lg-auto px-md-4">
        <div class="container-fluid pt-4">
            <!-- Mostrar mensajes de sesión -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['success']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['error']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['warning'])): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['warning']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['warning']); ?>
            <?php endif; ?>

<!-- Bottom Navigation (Solo para móviles) -->
<div class="bottom-nav">
    <a href="../dashboard/index.php" class="bottom-nav-item <?php echo strpos($_SERVER['REQUEST_URI'], 'dashboard') ? 'active' : ''; ?>">
        <i class="bi bi-speedometer2"></i>
        <span>Inicio</span>
    </a>
    <a href="../ventas/index.php" class="bottom-nav-item <?php echo strpos($_SERVER['REQUEST_URI'], 'ventas') ? 'active' : ''; ?>">
        <i class="bi bi-cart-plus"></i>
        <span>Vender</span>
    </a>
    <a href="../clientes/index.php" class="bottom-nav-item <?php echo strpos($_SERVER['REQUEST_URI'], 'clientes') ? 'active' : ''; ?>">
        <i class="bi bi-people"></i>
        <span>Clientes</span>
    </a>
    <a href="../cobranza/index.php" class="bottom-nav-item <?php echo strpos($_SERVER['REQUEST_URI'], 'cobranza') ? 'active' : ''; ?>">
        <i class="bi bi-cash-stack"></i>
        <span>Cobrar</span>
    </a>
<a href="../inventario/index.php" class="bottom-nav-item <?php echo strpos($_SERVER['REQUEST_URI'], 'inventario') ? 'active' : ''; ?>">
    <i class="bi bi-box-seam"></i>
    <span>Inventario</span>
</a>
</div>