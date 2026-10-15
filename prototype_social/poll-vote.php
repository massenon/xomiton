<?php
/** Vote à un sondage (fil d'actualité). Répond en JSON (fetch) ou redirige vers le fil (sans JavaScript). */
require __DIR__ . '/inc/bootstrap.php';
require_login();
$ajax = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
$out = function ($ok, $msg, $post = null) use ($ajax) {
    if ($ajax) { header('Content-Type: application/json; charset=utf-8'); http_response_code($ok ? 200 : 422); echo json_encode(['ok' => $ok, 'message' => $msg, 'html' => $post ? x_render_poll($post) : null], JSON_UNESCAPED_UNICODE); exit; }
    $_SESSION['flash'] = ['type' => $ok ? 'success' : 'danger', 'msg' => $msg];
    header('Location: index.php' . ($post ? '#post-' . (int)$post['id'] : '')); exit;
};
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_ok()) $out(false, 'Session expirée, veuillez réessayer.');
$p = store_find('posts', $_POST['post'] ?? 0);
if (!$p || empty($p['poll'])) $out(false, 'Sondage introuvable.');
$poll = $p['poll']; $me = (string)x_user()['id'];
if (!empty($poll['ends']) && $poll['ends'] < time()) $out(false, 'Ce sondage est terminé.', $p);
if (!empty($_POST['undo'])) {
    unset($poll['votes'][$me]);
    $msg = 'Votre vote a été retiré.';
} else {
    $valid = array_column($poll['options'], 'id');
    $choice = array_values(array_unique(array_intersect(array_map('strval', (array)($_POST['choice'] ?? [])), $valid)));
    if (!$choice) $out(false, 'Choisissez au moins une réponse.', $p);
    if (empty($poll['multiple'])) $choice = [$choice[0]];
    $poll['votes'][$me] = $choice;
    $msg = 'Merci, votre vote est enregistré.';
}
if (empty($poll['votes'])) $poll['votes'] = new stdClass(); // garde un objet JSON
store_update('posts', $p['id'], ['poll' => $poll]);
$p['poll'] = json_decode(json_encode($poll), true);
$out(true, $msg, $p);
