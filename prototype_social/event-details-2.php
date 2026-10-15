<?php
$id = (int)($_GET["id"] ?? 0);
header("Location: event-details.php" . ($id ? "?id=" . $id : ""));
exit;
