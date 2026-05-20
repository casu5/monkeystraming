<?php
// includes/auth.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('cleanInput')) {
    function cleanInput(?string $value): string
    {
        $value = trim((string)$value);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
        $value = strip_tags($value);
        return $value;
    }
}

if (!function_exists('redirect')) {
    function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }
}

if (!function_exists('isLoggedIn')) {
    function isLoggedIn(): bool
    {
        return !empty($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0;
    }
}

if (!function_exists('normalizeUserRole')) {
    function normalizeUserRole(?string $role): string
    {
        $role = strtolower(trim((string)$role));
        if ($role === 'user' || $role === 'usuario' || $role === 'customer') return 'cliente';
        if ($role === 'seller') return 'vendedor';
        if (in_array($role, ['admin', 'vendedor', 'cliente'], true)) return $role;
        return 'cliente';
    }
}

if (!function_exists('requireLogin')) {
    function requireLogin(?string $redirectTo = null): void
    {
        if (isLoggedIn()) return;

        if ($redirectTo === null) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host   = $_SERVER['HTTP_HOST'] ?? '';
            $uri    = $_SERVER['REQUEST_URI'] ?? '';
            $redirectTo = $scheme . '://' . $host . $uri;
        }

        redirect('login.php?redirect=' . urlencode($redirectTo));
    }
}

if (!function_exists('requireAdmin')) {
    function requireAdmin(): void
    {
        requireRole('admin');
    }
}

if (!function_exists('requireRole')) {
    function requireRole($roles): void
    {
        requireLogin();

        $allowed = is_array($roles) ? $roles : [$roles];
        $allowed = array_map('normalizeUserRole', $allowed);
        $role = normalizeUserRole($_SESSION['user_role'] ?? '');

        if (!in_array($role, $allowed, true)) {
            http_response_code(403);
            die('Acceso denegado');
        }
    }
}

if (!function_exists('hasRole')) {
    function hasRole($roles): bool
    {
        if (!isLoggedIn()) return false;

        $allowed = is_array($roles) ? $roles : [$roles];
        $allowed = array_map('normalizeUserRole', $allowed);
        $role = normalizeUserRole($_SESSION['user_role'] ?? '');

        return in_array($role, $allowed, true);
    }
}

if (!function_exists('getCurrentUser')) {
    /**
     * Lee el usuario desde BD y sincroniza llaves oficiales:
     * user_id, user_role, user_name, user_email, user_saldo
     *
     * Requiere $conexion (mysqli) ya creado en config/database.php
     */
    function getCurrentUser(): ?array
    {
        if (!isLoggedIn()) return null;
        if (!isset($GLOBALS['conexion']) || !($GLOBALS['conexion'] instanceof mysqli)) return null;

        $conexion = $GLOBALS['conexion'];
        $userId   = (int)$_SESSION['user_id'];

        $stmt = $conexion->prepare("SELECT * FROM usuarios WHERE id = ? LIMIT 1");
        if (!$stmt) return null;

        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) return null;

        $name  = $user['nombre'] ?? $user['full_name'] ?? $user['name'] ?? '';
        $email = $user['email'] ?? '';
        $saldo = isset($user['saldo']) ? (float)$user['saldo'] : (isset($user['balance']) ? (float)$user['balance'] : 0.0);
        $role  = normalizeUserRole($user['rol'] ?? $user['role'] ?? $user['user_role'] ?? ($_SESSION['user_role'] ?? 'cliente'));

        $_SESSION['user_id']    = $userId;
        $_SESSION['user_name']  = (string)$name;
        $_SESSION['user_email'] = (string)$email;
        $_SESSION['user_saldo'] = $saldo;
        $_SESSION['user_role']  = $role;

        $user['role'] = $role;
        $user['rol'] = $role;

        return $user;
    }
}
