<?php
$id = (int)($_GET['id'] ?? 0);
header('Location: ' . ($id > 0 ? 'thematique.php?id=' . $id : 'ressources.php'));
exit;
