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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - <?php echo $page_title ?? 'Sistema de Gestión'; ?></title>

    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

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

        @media (max-width: 768px) {
            .sidebar {
                display: none;
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
                SIGIB Licoreria Blanco
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
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../clientes/index.php">
                            <i class="bi bi-people"></i> Clientes
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../inventario/index.php">
                            <i class="bi bi-boxes"></i> Inventario
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../ventas/index.php">
                            <i class="bi bi-cart-plus"></i> Ventas
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../cobranza/index.php">
                            <i class="bi bi-cash-coin"></i> Cobranza
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
    <main class="col-lg-10 ms-auto px-md-4">
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