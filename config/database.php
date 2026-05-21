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

    if (!empty($_SESSION['user_role'])) {
        $_SESSION['user_role'] = normalizeUserRole((string)$_SESSION['user_role']);
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
 * Roles oficiales del marketplace.
 * Compatibilidad: "user" queda como "cliente".
 */
function normalizeUserRole(?string $role): string
{
    $role = strtolower(trim((string)$role));

    if ($role === 'user' || $role === 'usuario' || $role === 'customer') {
        return 'cliente';
    }

    if ($role === 'seller') {
        return 'vendedor';
    }

    if (in_array($role, ['admin', 'vendedor', 'cliente'], true)) {
        return $role;
    }

    return 'cliente';
}

function roleLabel(?string $role): string
{
    $role = normalizeUserRole($role);
    if ($role === 'admin') return 'Administrador';
    if ($role === 'vendedor') return 'Vendedor';
    return 'Cliente';
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
function appBasePath(): string
{
    $docRoot = isset($_SERVER['DOCUMENT_ROOT'])
        ? str_replace('\\', '/', rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/\\'))
        : '';
    $projectRoot = str_replace('\\', '/', dirname(__DIR__));

    if ($docRoot !== '' && str_starts_with($projectRoot, $docRoot)) {
        $base = substr($projectRoot, strlen($docRoot));
        return '/' . trim($base, '/');
    }

    return '';
}

function appUrl(string $url): string
{
    if (preg_match('~^https?://~i', $url)) {
        return $url;
    }

    if ($url !== '' && $url[0] === '/') {
        $base = appBasePath();
        if ($base !== '' && !str_starts_with($url, $base . '/')) {
            return $base . $url;
        }
    }

    return $url;
}

function redirectTo(string $url): void
{
    header('Location: ' . appUrl($url));
    exit;
}

/**
 * Setea sesión oficial del usuario.
 * Úsalo en tu login-process cuando lo pidamos.
 */
function setUserSession(array $userRow): void
{
    $_SESSION['user_id']    = $userRow['id'] ?? null;
    $_SESSION['user_role']  = normalizeUserRole($userRow['role'] ?? 'cliente');
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
        if (str_contains($redirectUrl, 'login.php') && !str_contains($redirectUrl, 'redirect=')) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? '';
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            $currentUrl = ($host !== '') ? ($scheme . '://' . $host . $uri) : $uri;
            $redirectUrl .= (str_contains($redirectUrl, '?') ? '&' : '?') . 'redirect=' . urlencode($currentUrl);
        }
        redirectTo($redirectUrl);
    }
}

/**
 * Verifica admin.
 * Acepta role 'admin' (case-insensitive).
 */
function requireAdmin(string $redirectUrl = '/login.php'): void
{
    requireRole('admin', $redirectUrl);
}

function requireRole($roles, string $redirectUrl = '/login.php'): void
{
    requireLogin($redirectUrl);

    $allowed = is_array($roles) ? $roles : [$roles];
    $allowed = array_map('normalizeUserRole', $allowed);
    $role = normalizeUserRole($_SESSION['user_role'] ?? '');

    if (!in_array($role, $allowed, true)) {
        http_response_code(403);
        die('Acceso denegado.');
    }
}

function hasRole($roles): bool
{
    if (empty($_SESSION['user_id'])) return false;

    $allowed = is_array($roles) ? $roles : [$roles];
    $allowed = array_map('normalizeUserRole', $allowed);
    $role = normalizeUserRole($_SESSION['user_role'] ?? '');

    return in_array($role, $allowed, true);
}

function isAdmin(): bool
{
    return hasRole('admin');
}

function isSeller(): bool
{
    return hasRole('vendedor');
}

function isCustomer(): bool
{
    return hasRole('cliente');
}

/**
 * Devuelve datos básicos del usuario actual (desde sesión).
 */
function currentUser(): array
{
    return [
        'id'    => $_SESSION['user_id'] ?? null,
        'role'  => normalizeUserRole($_SESSION['user_role'] ?? null),
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
