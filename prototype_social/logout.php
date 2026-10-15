<?php
require __DIR__ . '/inc/bootstrap.php';
if (x_user()) x_log('Déconnexion');
$_SESSION = [];
$_SESSION['flash'] = ['type' => 'success', 'msg' => 'Vous êtes déconnecté·e. À bientôt !'];
header('Location: sign-in.php');
exit;
