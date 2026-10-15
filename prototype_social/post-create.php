<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();

/** Enregistre la photo téléversée (jpg, png, webp, gif, < 5 Mo) ; retourne le chemin relatif ou ''. */
function pc_upload_photo() {
    if (empty($_FILES['photo']['tmp_name']) || !is_uploaded_file($_FILES['photo']['tmp_name'])) return '';
    $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true) || $_FILES['photo']['size'] >= 5 * 1024 * 1024 || !@getimagesize($_FILES['photo']['tmp_name'])) return '';
    $dir = X_ROOT . '/assets/images/xomiton/uploads';
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $fn = 'u' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
    move_uploaded_file($_FILES['photo']['tmp_name'], $dir . '/' . $fn);
    return 'uploads/' . $fn;
}
function pc_back($type, $msg) { $_SESSION['flash'] = ['type' => $type, 'msg' => $msg]; header('Location: index.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $anon = !empty($_POST['anonymous']);
    $visibility = in_array($_POST['visibility'] ?? '', ['public', 'friends', 'me'], true) ? $_POST['visibility'] : 'public';
    $text = trim($_POST['text'] ?? '');

    if (($_POST['kind'] ?? '') === 'poll') {
        $question = trim($_POST['question'] ?? '');
        $labels = array_values(array_filter(array_map(fn($o) => mb_substr(trim((string)$o), 0, 80), (array)($_POST['options'] ?? [])), fn($o) => $o !== ''));
        if (mb_strlen($question) < 5 || mb_strlen($question) > 200) pc_back('danger', 'La question du sondage doit faire entre 5 et 200 caractères.');
        if (count($labels) < 2 || count($labels) > 6) pc_back('danger', 'Un sondage comporte entre 2 et 6 réponses.');
        if (count(array_unique(array_map('mb_strtolower', $labels))) !== count($labels)) pc_back('danger', 'Deux réponses du sondage sont identiques.');
        $days = (int)($_POST['duration'] ?? 7);
        $options = []; foreach ($labels as $i => $l) $options[] = ['id' => 'o' . ($i + 1), 'label' => $l];
        store_append('posts', ['user' => $anon ? 0 : x_user()['id'], 'text' => mb_substr($text, 0, 3000), 'image' => pc_upload_photo(), 'likes' => 0, 'comments' => 0, 'ts' => time(),
            'tag' => 'Sondage', 'visibility' => $visibility, 'anonymous' => $anon,
            'poll' => ['question' => $question, 'options' => $options, 'multiple' => !empty($_POST['multiple']), 'results_before_vote' => !empty($_POST['results_before_vote']),
                'ends' => in_array($days, [1, 3, 7, 14, 30], true) ? time() + $days * 86400 : null, 'votes' => new stdClass()]]);
        x_log('Sondage', mb_substr($question, 0, 60));
        pc_back('success', 'Votre sondage est en ligne.');
    }

    if ($text !== '') {
        store_append('posts', ['user' => $anon ? 0 : x_user()['id'], 'text' => mb_substr($text, 0, 3000), 'image' => pc_upload_photo(), 'likes' => 0, 'comments' => 0, 'ts' => time(),
            'tag' => $_POST['tag'] ?? 'Témoignage', 'visibility' => $visibility, 'anonymous' => $anon]);
        x_log('Publication', mb_substr($text, 0, 60));
        pc_back('success', 'Votre publication est en ligne.');
    }
}
header('Location: index.php');
exit;
