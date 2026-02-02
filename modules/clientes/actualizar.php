<?php
// modules/clientes/actualizar.php
include '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_cliente = intval($_POST['id_cliente']);
    $nombre = trim($_POST['nombre']);
    $rif = trim($_POST['rif']);
    $telefono = trim($_POST['telefono'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $tipo = $_POST['tipo'];
    $limite_credito = floatval($_POST['limite_credito'] ?? 0);
    $dias_credito = intval($_POST['dias_credito'] ?? 0);

    try {
        // Validar que el RIF no exista en otro cliente
        $stmt = $pdo->prepare("SELECT id_cliente FROM clientes WHERE rif_cedula = ? AND id_cliente != ?");
        $stmt->execute([$rif, $id_cliente]);
        
        if ($stmt->rowCount() > 0) {
            $_SESSION['error'] = "El RIF/Cédula ya está registrado en otro cliente";
            header("Location: index.php");
            exit();
        }

        $pdo->beginTransaction();

        // Actualizar cliente
        $sql = "UPDATE clientes SET 
                nombre_cliente = :nombre,
                rif_cedula = :rif,
                telefono = :telefono,
                direccion = :direccion,
                tipo_cliente = :tipo
                WHERE id_cliente = :id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':nombre' => $nombre,
            ':rif' => $rif,
            ':telefono' => $telefono,
            ':direccion' => $direccion,
            ':tipo' => $tipo,
            ':id' => $id_cliente
        ]);

        // Actualizar información de crédito
        if ($tipo == 'Mayorista') {
            $sqlCredito = "UPDATE cuentas_por_cobrar SET 
                          limite_credito = :limite,
                          dias_credito = :dias
                          WHERE id_cliente = :id";
            
            $stmtCredito = $pdo->prepare($sqlCredito);
            $stmtCredito->execute([
                ':limite' => $limite_credito,
                ':dias' => $dias_credito,
                ':id' => $id_cliente
            ]);
        } else {
            // Si cambia de Mayorista a Detal, eliminar límite
            $sqlCredito = "UPDATE cuentas_por_cobrar SET 
                          limite_credito = 0,
                          dias_credito = 0
                          WHERE id_cliente = :id";
            
            $stmtCredito = $pdo->prepare($sqlCredito);
            $stmtCredito->execute([':id' => $id_cliente]);
        }

        $pdo->commit();

        $_SESSION['success'] = "Cliente actualizado exitosamente";
        header("Location: perfil.php?id=" . $id_cliente);
        exit();

    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error al actualizar: " . $e->getMessage();
        header("Location: index.php");
        exit();
    }
}
?>