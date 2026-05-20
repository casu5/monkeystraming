<?php
// processes/register-process.php
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre    = cleanInput($_POST['username'] ?? '');
    $email     = cleanInput($_POST['email'] ?? '');
    $password  = $_POST['password']  ?? '';
    $password2 = $_POST['password2'] ?? '';

    // Validaciones
    $errors = [];

    if (empty($nombre) || strlen($nombre) < 3) {
        $errors[] = "El nombre debe tener al menos 3 caracteres";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Email inválido";
    }

    if (strlen($password) < 6) {
        $errors[] = "La contraseña debe tener al menos 6 caracteres";
    }

    if ($password !== $password2) {
        $errors[] = "Las contraseñas no coinciden";
    }

    // Verificar si el email ya existe
    $sql_check = "SELECT id FROM usuarios WHERE email = ?";
    $stmt_check = $conexion->prepare($sql_check);
    $stmt_check->bind_param("s", $email);
    $stmt_check->execute();

    if ($stmt_check->get_result()->num_rows > 0) {
        $errors[] = "Este email ya está registrado";
    }

    // Si hay errores
    if (!empty($errors)) {
        $_SESSION['error'] = implode("<br>", $errors);
        redirect('../register.php');
    }

    // Hash de la contraseña
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insertar usuario
    $sql = "INSERT INTO usuarios (nombre, email, password, role, saldo) 
            VALUES (?, ?, ?, 'cliente', 0.00)";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("sss", $nombre, $email, $hashed_password);

    if ($stmt->execute()) {
        $_SESSION['success'] = "¡Cuenta creada exitosamente! Ahora puedes iniciar sesión.";
        redirect('../login.php');
    } else {
        $_SESSION['error'] = "Error al crear la cuenta. Por favor, intenta de nuevo.";
        redirect('../register.php');
    }
} else {
    redirect('../register.php');
}
