<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $text = trim($_POST['text'] ?? '');
    if ($text !== '') {
        $image = '';
        if (!empty($_FILES['photo']['tmp_name']) && is_uploaded_file($_FILES['photo']['tmp_name'])) {
            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true) && $_FILES['photo']['size'] < 5 * 1024 * 1024) {
                $dir = X_ROOT . '/assets/images/xomiton/uploads';
                if (!is_dir($dir)) mkdir($dir, 0775, true);
                $fn = 'u' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                move_uploaded_file($_FILES['photo']['tmp_name'], $dir . '/' . $fn);
                $image = 'uploads/' . $fn;
            }
        }
        $anon = !empty($_POST['anonymous']);
        store_append('posts', ['user' => $anon ? 0 : x_user()['id'], 'text' => mb_substr($text, 0, 3000), 'image' => $image, 'likes' => 0, 'comments' => 0, 'ts' => time(),
            'tag' => $_POST['tag'] ?? 'Témoignage', 'visibility' => $_POST['visibility'] ?? 'public', 'anonymous' => $anon]);
        x_log('Publication', mb_substr($text, 0, 60));
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Votre publication est en ligne.'];
    }
}
header('Location: index.php');
exit;
