<?php
/**
 * Xomiton – bootstrap du prototype (sessions, chemins, garde d'accès, helpers).
 * Inclure en tête de chaque page : require __DIR__.'/inc/bootstrap.php';  (ou ../inc/ depuis admin/)
 */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
mb_internal_encoding('UTF-8');
header('Content-Type: text/html; charset=utf-8');

define('X_ROOT', realpath(__DIR__ . '/..'));
define('X_DATA', X_ROOT . '/data');

/* Préfixe relatif vers la racine du prototype ('' à la racine, '../' dans admin/) */
$__rel = str_replace('\\', '/', substr(dirname(realpath($_SERVER['SCRIPT_FILENAME'])), strlen(X_ROOT)));
$__depth = $__rel === '' ? 0 : substr_count(trim($__rel, '/'), '/') + 1;
define('BASE', str_repeat('../', $__depth));

require_once __DIR__ . '/store.php';
require_once __DIR__ . '/data.php';
require_once __DIR__ . '/render.php';

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function img($f) { return BASE . 'assets/images/xomiton/' . $f; }
function asset($p) { return BASE . 'assets/' . $p; }
function url($p) { return BASE . $p; }

/* ---------- Session / authentification ---------- */
function x_user() { return $_SESSION['user'] ?? null; }
function x_is_admin() { $u = x_user(); return $u && in_array($u['role'], ['admin', 'superadmin'], true); }

function require_login() {
    if (!x_user()) {
        $_SESSION['flash'] = ['type' => 'warning', 'msg' => 'Connectez-vous pour accéder à cet espace.'];
        header('Location: ' . BASE . 'sign-in.php?next=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}
function require_admin() {
    require_login();
    if (!x_is_admin()) {
        $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Accès réservé à l’administration.'];
        header('Location: ' . BASE . 'index.php');
        exit;
    }
}
function flash() {
    if (!empty($_SESSION['flash'])) { $f = $_SESSION['flash']; unset($_SESSION['flash']); return $f; }
    return null;
}
function flash_html() {
    $f = flash();
    return $f ? '<div class="alert alert-' . e($f['type']) . ' alert-dismissible fade show" role="alert">' . e($f['msg']) . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button></div>' : '';
}
function csrf_token() { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
function csrf_field() { return '<input type="hidden" name="_csrf" value="' . csrf_token() . '">'; }
function csrf_ok() { return isset($_POST['_csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $_POST['_csrf']); }

function x_log($action, $detail = '') {
    $u = x_user();
    store_append('journal', ['at' => date('Y-m-d H:i:s'), 'user' => $u['email'] ?? 'anonyme', 'action' => $action, 'detail' => $detail]);
}
function x_initials($name) { $p = preg_split('/\s+/', trim($name)); return mb_strtoupper(mb_substr($p[0] ?? '', 0, 1) . mb_substr($p[1] ?? '', 0, 1)); }
function x_ago($ts) {
    $d = time() - (is_numeric($ts) ? $ts : strtotime($ts));
    if ($d < 60) return 'à l’instant'; if ($d < 3600) return floor($d / 60) . ' min';
    if ($d < 86400) return floor($d / 3600) . ' h'; return floor($d / 86400) . ' j';
}
