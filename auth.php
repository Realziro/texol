<?php
/**
 * auth.php - ONE include for login + role/permission handling.
 *
 * Put this file next to config.php and session_init.php (project root).
 * At the top of every page or API file, replace session_start() / the
 * session_init + config requires with just:
 *
 *     require_once __DIR__ . '/../auth.php';          // from a subfolder
 *
 * Then use:
 *     require_permission('tickets', 'view');           // block the page/API
 *     if (can('tickets', 'edit')) { ... }              // show/hide things
 *     $scope = permission_scope('tickets');            // 'all' | 'department' | 'own' | null
 *     $params = scope_filter_params('tickets');        // PostgREST filters for the scope
 *     auth_js_config();                                // echo inside <script> to share with JS
 *
 * Permissions come from the Supabase function get_effective_permissions()
 * (role_permissions + user_permissions). 'edit' also grants 'view'.
 */

require_once __DIR__ . '/session_init.php';   // the only place a session is started
require_once __DIR__ . '/config.php';

// ---------------------------------------------------------------------
// Settings
// ---------------------------------------------------------------------
if (!defined('AUTH_LOGIN_URL'))        define('AUTH_LOGIN_URL', 'login');
if (!defined('AUTH_PERMISSION_TTL'))   define('AUTH_PERMISSION_TTL', 60);   // seconds before permissions are re-read
if (!defined('AUTH_AUTO_REQUIRE_LOGIN')) define('AUTH_AUTO_REQUIRE_LOGIN', true);
// To use on login/register pages, define this BEFORE including auth.php:
//     define('AUTH_AUTO_REQUIRE_LOGIN', false);

// ---------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------
function auth_is_api_request(): bool
{
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $xhr    = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    $uri    = $_SERVER['REQUEST_URI'] ?? '';
    $path   = parse_url($uri, PHP_URL_PATH) ?: '';
    $script = basename($path);
    return stripos($accept, 'application/json') !== false
        || strtolower($xhr) === 'xmlhttprequest'
        || stripos($uri, '/api/') !== false
        || preg_match('/(?:_api\.php|^(?:notify|upload)_.*\.php$)/i', $script) === 1;
}

function auth_deny(int $status, string $message): void
{
    http_response_code($status);
    if (auth_is_api_request()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $message]);
    } else {
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . htmlspecialchars($message) . '</title></head>'
           . '<body style="font-family:system-ui;text-align:center;padding:60px">'
           . '<h2>' . htmlspecialchars($message) . '</h2>'
           . '<p><a href="index">Back to dashboard</a></p></body></html>';
    }
    exit;
}

// ---------------------------------------------------------------------
// Login
// ---------------------------------------------------------------------
function is_logged_in(): bool
{
    return !empty($_SESSION['user_email']);
}

function require_login(): void
{
    if (is_logged_in()) return;
    if (auth_is_api_request()) {
        auth_deny(401, 'Unauthorized');
    }
    header('Location: ' . AUTH_LOGIN_URL);
    exit;
}

function current_user_email(): string      { return $_SESSION['user_email'] ?? ''; }
function current_user_name(): string       { return $_SESSION['user_name'] ?? ''; }
function current_user_department(): string { return $_SESSION['user_department'] ?? ''; }
function is_admin(): bool
{
    return strtolower($_SESSION['user_role'] ?? '') === 'admin';
}
function is_hod(): bool
{
    return strtolower($_SESSION['user_role'] ?? '') === 'hod';
}

// ---------------------------------------------------------------------
// Supabase (server side, service role key - never sent to the browser)
// ---------------------------------------------------------------------
function auth_supabase(string $method, string $path, ?array $body = null): array
{
    if (!defined('SUPABASE_URL') || !defined('SUPABASE_SERVICE_ROLE_KEY')) {
        return ['code' => 0, 'data' => null];
    }
    $key = SUPABASE_SERVICE_ROLE_KEY;
    $ch = curl_init(rtrim(SUPABASE_URL, '/') . '/rest/v1/' . ltrim($path, '/'));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => [
            'apikey: ' . $key,
            'Authorization: Bearer ' . $key,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $response = curl_exec($ch);
    $code     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'data' => $response === false ? null : json_decode($response, true)];
}

// ---------------------------------------------------------------------
// Permissions
// ---------------------------------------------------------------------

/** Forget cached permissions (call after changing roles or on login). */
function auth_forget_permissions(): void
{
    unset($_SESSION['effective_permissions'], $_SESSION['effective_permissions_at']);
}

/**
 * Returns rows of [module, action, scope]. Cached in the session for
 * AUTH_PERMISSION_TTL seconds, so edits to a role apply within a minute
 * without logging out. Failed lookups are never cached.
 */
function auth_load_permissions(bool $force = false): array
{
    if (!is_logged_in()) return [];

    $fresh = isset($_SESSION['effective_permissions'], $_SESSION['effective_permissions_at'])
        && (time() - (int) $_SESSION['effective_permissions_at']) < AUTH_PERMISSION_TTL;
    if ($fresh && !$force) {
        return $_SESSION['effective_permissions'];
    }

    $res = auth_supabase('POST', 'rpc/get_effective_permissions', ['p_email' => current_user_email()]);
    if ($res['code'] === 200 && is_array($res['data'])) {
        // Same session key config.php's check_permission() reads, so both stay consistent
        $_SESSION['effective_permissions']    = $res['data'];
        $_SESSION['effective_permissions_at'] = time();
        return $res['data'];
    }

    // Lookup failed: keep the last known good copy, otherwise deny (and don't cache)
    return $_SESSION['effective_permissions'] ?? [];
}

const AUTH_SCOPE_RANK = ['own' => 1, 'department' => 2, 'all' => 3];

/**
 * Widest scope the user has for module+action, or null if none.
 * 'edit' implies 'view'. A stored action of 'all' is a wildcard.
 */
function permission_scope(string $module, string $action = 'view'): ?string
{
    if (!is_logged_in()) return null;

    $accepted = [$action, 'all'];
    if ($action === 'view') $accepted[] = 'edit';

    $best = null;
    foreach (auth_load_permissions() as $p) {
        if (($p['module'] ?? '') !== $module) continue;
        if (!in_array($p['action'] ?? '', $accepted, true)) continue;
        $scope = $p['scope'] ?? 'all';
        if ($best === null || (AUTH_SCOPE_RANK[$scope] ?? 0) > (AUTH_SCOPE_RANK[$best] ?? 0)) {
            $best = $scope;
        }
    }
    return $best;
}

function can(string $module, string $action = 'view'): bool
{
    return permission_scope($module, $action) !== null;
}

/** Block the page or API call unless the user has the permission. Returns the scope. */
function require_permission(string $module, string $action = 'view'): string
{
    require_login();
    $scope = permission_scope($module, $action);
    if ($scope === null) {
        auth_deny(403, 'Access denied');
    }
    return $scope;
}

/** Require ANY of several permissions, e.g. require_any_permission([['tickets','view'],['mytickets','view']]) */
function require_any_permission(array $pairs): void
{
    require_login();
    foreach ($pairs as [$module, $action]) {
        if (can($module, $action ?? 'view')) return;
    }
    auth_deny(403, 'Access denied');
}

/**
 * PostgREST query params that enforce the user's scope for a module.
 *   all        -> []
 *   department -> ['department' => 'ilike.<dept>']
 *   own        -> ['or' => '(col1.eq."email",col2.eq."email")']
 * No permission -> a filter that matches nothing.
 *
 * $deptColumn / $ownColumns depend on the table (tickets: department, requested_by).
 */
function scope_filter_params(string $module, string $action = 'view', string $deptColumn = 'department', array $ownColumns = ['requested_by']): array
{
    $scope = permission_scope($module, $action);
    if ($scope === 'all') return [];
    if ($scope === 'department') {
        $dept = current_user_department();
        return $dept === '' ? ['id' => 'is.null'] : [$deptColumn => 'ilike.' . str_replace(['*', ','], '', $dept)];
    }
    if ($scope === 'own') {
        $email = str_replace('"', '', current_user_email());
        $parts = array_map(fn($c) => $c . '.eq."' . $email . '"', $ownColumns);
        return ['or' => '(' . implode(',', $parts) . ')'];
    }
    return ['id' => 'is.null'];   // no access: matches nothing
}

/** All of the user's permissions as module => [action => scope] (for menus / JS). */
function permission_map(): array
{
    $map = [];
    foreach (auth_load_permissions() as $p) {
        $map[$p['module']][$p['action']] = $p['scope'] ?? 'all';
    }
    return $map;
}

/** Echo inside a <script> tag to give JS the user and flags, e.g. window.AUTH.can('tickets','edit') */
function auth_js_config(array $modules = []): void
{
    $flags = [];
    foreach ($modules as $m) {
        $flags[$m] = ['view' => permission_scope($m, 'view'), 'edit' => permission_scope($m, 'edit')];
    }
    $data = [
        'email'      => current_user_email(),
        'name'       => current_user_name(),
        'department' => current_user_department(),
        'isAdmin'    => is_admin(),
        'perms'      => $flags,
    ];
    echo 'window.AUTH = ' . json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';'
       . 'window.AUTH.scope = function(m,a){var p=(window.AUTH.perms[m]||{});return p[a||"view"]||null;};'
    . 'window.AUTH.can = function(m,a){return window.AUTH.scope(m,a)!==null;};';
}

// ---------------------------------------------------------------------
// Notifications: who should get an email for an event
// ---------------------------------------------------------------------
/** Returns [['email'=>..., 'full_name'=>...], ...] using role_notifications. */
function notification_recipients(string $eventKey, string $department = ''): array
{
    $res = auth_supabase('POST', 'rpc/get_notification_recipients', [
        'p_event'      => $eventKey,
        'p_department' => $department,
    ]);
    return ($res['code'] === 200 && is_array($res['data'])) ? $res['data'] : [];
}

// ---------------------------------------------------------------------
// CSRF (use in forms/APIs that change data)
// ---------------------------------------------------------------------
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_check(): void
{
    $sent = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        auth_deny(403, 'Invalid request token');
    }
}

// ---------------------------------------------------------------------
// Default behaviour: every page that includes this file requires login
// ---------------------------------------------------------------------
if (AUTH_AUTO_REQUIRE_LOGIN) {
    require_login();
}
