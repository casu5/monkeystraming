<?php
/**
 * config/database.php
 * Bootstrap mínimo: sesión + conexión DB + helpers estándar.
 */

declare(strict_types=1);

// Zona horaria (ajústala si deseas)
date_default_timezone_set('America/Lima');

// Arranca sesión de forma segura
if (session_status() === PHP_SESSION_NONE) {
    // Cookies seguras (si usas HTTPS, secure=true)
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}


$db_config = [
    'host'   => 'localhost',
    'user'   => 'root',
    'pass'   => '',
    'name'   => 'monkeystraming_2',
    'port'   => 3306,
    'charset'=> 'utf8mb4',
];



$conexion = new mysqli(
    $db_config['host'],
    $db_config['user'],
    $db_config['pass'],
    $db_config['name'],
    $db_config['port']
);

if ($conexion->connect_errno) {
    http_response_code(500);
    die("Error de conexión a BD: " . $conexion->connect_error);
}

$conexion->set_charset($db_config['charset']);

// ==========================
// SESIÓN OFICIAL (NORMALIZAR)
// ==========================
/**
 * Normaliza claves de sesión antiguas -> claves oficiales.
 * Esto evita inconsistencias entre user/admin mientras terminas de estandarizar.
 */
function normalizeSessionKeys(): void
{
    // Si ya existe user_id, no toques
    if (!empty($_SESSION['user_id'])) return;

    // Compatibilidad con claves viejas comunes
    if (!empty($_SESSION['usuario_id'])) {
        $_SESSION['user_id'] = $_SESSION['usuario_id'];
    }

    // Role
    if (empty($_SESSION['user_role'])) {
        if (!empty($_SESSION['role'])) $_SESSION['user_role'] = $_SESSION['role'];
        if (!empty($_SESSION['usuario_role'])) $_SESSION['user_role'] = $_SESSION['usuario_role'];
    }

    // Name/email/saldo (si existieran con otros nombres)
    if (empty($_SESSION['user_name']) && !empty($_SESSION['nombre'])) $_SESSION['user_name'] = $_SESSION['nombre'];
    if (empty($_SESSION['user_email']) && !empty($_SESSION['email'])) $_SESSION['user_email'] = $_SESSION['email'];
    if (!isset($_SESSION['user_saldo']) && isset($_SESSION['saldo'])) $_SESSION['user_saldo'] = $_SESSION['saldo'];
}
normalizeSessionKeys();

// ==========================
// HELPERS MÍNIMOS
// ==========================

/**
 * Acceso a la conexión global.
 */
function db(): mysqli
{
    /** @var mysqli $conexion */
    global $conexion;
    return $conexion;
}

/**
 * Limpieza estándar de input (string/array).
 * - trim
 * - quita null bytes
 * - elimina tags
 * - escapa HTML
 */
function cleanInput($value)
{
    if (is_array($value)) {
        $clean = [];
        foreach ($value as $k => $v) {
            $clean[$k] = cleanInput($v);
        }
        return $clean;
    }

    $value = (string)$value;
    $value = str_replace("\0", '', $value);
    $value = trim($value);
    $value = strip_tags($value);

    // Evita XSS en outputs directos
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Redirección segura.
 */
function redirectTo(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/**
 * Setea sesión oficial del usuario.
 * Úsalo en tu login-process cuando lo pidamos.
 */
function setUserSession(array $userRow): void
{
    $_SESSION['user_id']    = $userRow['id'] ?? null;
    $_SESSION['user_role']  = $userRow['role'] ?? 'user';
    $_SESSION['user_name']  = $userRow['nombre'] ?? ($userRow['name'] ?? '');
    $_SESSION['user_email'] = $userRow['email'] ?? '';
    $_SESSION['user_saldo'] = $userRow['saldo'] ?? 0;

    // Opcional: regenerar id para evitar session fixation
    session_regenerate_id(true);
}

/**
 * Verifica login.
 * $redirectUrl: a dónde mandarlo si no hay sesión.
 */
function requireLogin(string $redirectUrl = '/login.php'): void
{
    if (empty($_SESSION['user_id'])) {
        redirectTo($redirectUrl);
    }
}

/**
 * Verifica admin.
 * Acepta role 'admin' (case-insensitive).
 */
function requireAdmin(string $redirectUrl = '/login.php'): void
{
    requireLogin($redirectUrl);

    $role = strtolower((string)($_SESSION['user_role'] ?? ''));
    if ($role !== 'admin') {
        // Puedes mandarlo a dashboard o 403
        http_response_code(403);
        die('Acceso denegado (solo admin).');
    }
}

/**
 * Devuelve datos básicos del usuario actual (desde sesión).
 */
function currentUser(): array
{
    return [
        'id'    => $_SESSION['user_id'] ?? null,
        'role'  => $_SESSION['user_role'] ?? null,
        'name'  => $_SESSION['user_name'] ?? null,
        'email' => $_SESSION['user_email'] ?? null,
        'saldo' => $_SESSION['user_saldo'] ?? null,
    ];
}

/**
 * Cierra sesión de forma limpia.
 */
function logoutUser(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'], $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}
