<?php
require __DIR__ . '/inc/x_public.php';
$faq = x_faq();
$id = (int)($_GET['id'] ?? 1);
if ($id < 1 || $id > count($faq)) { header('Location: help.php'); exit; }
$i = $id - 1;
$f = $faq[$i];
$cats = x_help_categories();
$ck = x_faq_cat($i);
$cat = array_values(array_filter($cats, fn($c) => $c[0] === $ck))[0];
$more = [
	'compte' => ['Créez votre compte avec un pseudonyme : aucun nom réel n’est exigé.', 'Activez l’authentification renforcée avec un mot de passe fort (8 caractères minimum, majuscule, chiffre et symbole).', 'Vous pouvez à tout moment exporter ou supprimer vos données depuis Paramètres.'],
	'signal' => ['Choisissez le type : dénonciation, besoin ou SOS.', 'Le signalement peut être totalement anonyme ; vous recevez un code de suivi.', 'Il est routé vers les personnes ciblées de l’annuaire selon votre besoin et votre zone.', 'Hors connexion, il est conservé sur votre appareil puis envoyé automatiquement.'],
	'soutien' => ['Consultez l’annuaire pour trouver psychologues, juristes et points focaux.', 'L’assistant répond 24 h/24 et vous oriente vers un humain en cas de danger.', 'Les pair·es animateur·rices peuvent vous accompagner dans vos démarches.'],
	'communaute' => ['Rejoignez des groupes, pages et forums selon vos centres d’intérêt.', 'Confirmez votre participation aux événements depuis leur fiche.', 'Signalez tout comportement contraire à la charte de bienveillance.'],
][$ck];
x_pub_open($f[0], ['nav' => 'aide', 'pubNav' => 'aide']);
$member = (x_user() !== null);
if (!$member) echo '<div class="container py-4"><div class="row"><div class="col-12 vstack gap-4">';
?>
<nav aria-label="Fil d’Ariane"><ol class="breadcrumb mb-0 small">
	<li class="breadcrumb-item"><a href="help.php">Centre d’aide</a></li>
	<li class="breadcrumb-item"><a href="help.php?cat=<?= $ck ?>"><?= e($cat[1]) ?></a></li>
	<li class="breadcrumb-item active" aria-current="page">Article <?= $id ?></li>
</ol></nav>
<div class="row g-4">
	<div class="col-lg-8">
		<div class="card"><div class="card-body p-4">
			<span class="badge bg-<?= $cat[3] ?> bg-opacity-10 text-<?= $cat[3] ?> mb-2"><i class="bi <?= $cat[2] ?> me-1"></i><?= e($cat[1]) ?></span>
			<h1 class="h3"><?= e($f[0]) ?></h1>
			<p class="lead"><?= e($f[1]) ?></p>
			<h5 class="mt-4">À savoir</h5>
			<ul><?php foreach ($more as $m): ?><li class="mb-1"><?= e($m) ?></li><?php endforeach; ?></ul>
			<hr>
			<div class="d-flex flex-wrap align-items-center gap-3">
				<span class="small text-secondary">Cet article vous a-t-il aidé·e ?</span>
				<button class="btn btn-sm btn-success-soft" type="button" onclick="XM.toast('Merci pour votre retour !','success')"><i class="bi bi-hand-thumbs-up me-1"></i>Oui</button>
				<button class="btn btn-sm btn-danger-soft" type="button" onclick="XM.toast('Merci, nous allons améliorer cet article.','info')"><i class="bi bi-hand-thumbs-down me-1"></i>Non</button>
			</div>
		</div></div>
	</div>
	<div class="col-lg-4 vstack gap-3">
		<div class="card"><div class="card-header"><h6 class="mb-0">Autres questions</h6></div>
			<div class="list-group list-group-flush">
				<?php foreach ($faq as $j => $g): if ($j === $i) continue; if ($j > 6) break; ?>
					<a class="list-group-item list-group-item-action small" href="help-details.php?id=<?= $j + 1 ?>"><?= e($g[0]) ?></a>
				<?php endforeach; ?>
			</div>
		</div>
		<div class="card bg-danger bg-opacity-10 border-danger"><div class="card-body">
			<h6 class="text-danger">Besoin d’aide maintenant ?</h6>
			<a class="btn btn-danger btn-sm" href="sos.php">SOS</a> <a class="btn btn-light btn-sm" href="signalement.php">Signalement</a>
		</div></div>
	</div>
</div>
<?php if (!$member) echo '</div></div></div>'; ?>
<?php x_pub_close(); ?>
