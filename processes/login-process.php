<?php
// processes/login-process.php
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = cleanInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validaciones
    if ($email === '' || $password === '') {
        $_SESSION['error'] = "Por favor completa todos los campos";
        redirect('../login.php');
    }

    // Buscar usuario
    $sql = "SELECT id, nombre, email, password, role, saldo, estado 
            FROM usuarios 
            WHERE email = ?";
    if ($stmt = $conexion->prepare($sql)) {
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $usuario = $result->fetch_assoc();

            // Verificar estado
            if (!in_array(strtolower((string)($usuario['estado'] ?? 'activo')), ['activo', 'active', '1', ''], true)) {
                $_SESSION['error'] = "Tu cuenta está suspendida o eliminada";
                redirect('../login.php');
            }

            // Verificar contraseña
            if (password_verify($password, $usuario['password'])) {
                session_regenerate_id(true);
                // Crear sesión
                $_SESSION['user_id']    = $usuario['id'];
                $_SESSION['user_name']  = $usuario['nombre'];
                $_SESSION['user_email'] = $usuario['email'];
                $_SESSION['user_role']  = normalizeUserRole($usuario['role'] ?? 'cliente');
                $_SESSION['user_saldo'] = $usuario['saldo'];
                $_SESSION['logged_in']  = true;

                // Actualizar último login
                $update_sql = "UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?";
                if ($update_stmt = $conexion->prepare($update_sql)) {
                    $update_stmt->bind_param("i", $usuario['id']);
                    $update_stmt->execute();
                }

                

              
                if ($_SESSION['user_role'] === 'admin') {
                    redirect('../admin/index.php');
                } elseif ($_SESSION['user_role'] === 'vendedor') {
                    redirect('../vendedor/dashboard.php');
                } else {
                    redirect('../user/dashboard.php');
                }
            } else {
                $_SESSION['error'] = "Contraseña incorrecta";
                redirect('../login.php');
            }
        } else {
            $_SESSION['error'] = "Usuario no encontrado";
            redirect('../login.php');
        }

        $stmt->close();
    } else {
        $_SESSION['error'] = "Error interno al procesar el inicio de sesión.";
        redirect('../login.php');
    }
} else {
    redirect('../login.php');
}
