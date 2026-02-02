<?php
// modules/clientes/guardar.php
include '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = trim($_POST['nombre']);
    $rif = trim($_POST['rif']);
    $telefono = trim($_POST['telefono'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $tipo = $_POST['tipo'];
    $limite_credito = floatval($_POST['limite_credito'] ?? 1000);
    $dias_credito = intval($_POST['dias_credito'] ?? 7);

    try {
        // Validar que el RIF no exista
        $stmt = $pdo->prepare("SELECT id_cliente FROM clientes WHERE rif_cedula = ?");
        $stmt->execute([$rif]);
        
        if ($stmt->rowCount() > 0) {
            $_SESSION['error'] = "El RIF/Cédula ya está registrado";
            header("Location: index.php");
            exit();
        }

        $pdo->beginTransaction();

        // Insertar cliente
        $sql = "INSERT INTO clientes (nombre_cliente, rif_cedula, telefono, direccion, tipo_cliente) 
                VALUES (:nombre, :rif, :telefono, :direccion, :tipo)";
        $stmt = $pdo->prepare($sql);
        
        $stmt->execute([
            ':nombre' => $nombre,
            ':rif' => $rif,
            ':telefono' => $telefono,
            ':direccion' => $direccion,
            ':tipo' => $tipo
        ]);

        $id_cliente = $pdo->lastInsertId();

        // Crear cuenta por cobrar
        $sqlCuenta = "INSERT INTO cuentas_por_cobrar (id_cliente, saldo_dinero_usd, saldo_vacios, limite_credito, dias_credito, ultima_actualizacion) 
                      VALUES (:id, 0.00, 0, :limite, :dias, NOW())";
        $stmtCuenta = $pdo->prepare($sqlCuenta);
        $stmtCuenta->execute([
            ':id' => $id_cliente,
            ':limite' => $tipo == 'Mayorista' ? $limite_credito : 0,
            ':dias' => $tipo == 'Mayorista' ? $dias_credito : 0
        ]);

        $pdo->commit();

        $_SESSION['success'] = "Cliente registrado exitosamente. ID: #" . str_pad($id_cliente, 4, '0', STR_PAD_LEFT);
        
        // Redirigir al perfil del nuevo cliente
        header("Location: perfil.php?id=" . $id_cliente);
        exit();

    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error al guardar: " . $e->getMessage();
        header("Location: index.php");
        exit();
    }
}
?>