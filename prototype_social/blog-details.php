<?php
require __DIR__ . '/inc/x_public.php';
$arts = x_blog_articles();
$id = (int)($_GET['id'] ?? 1);
$a = null;
foreach ($arts as $x) if ($x['id'] === $id) $a = $x;
if (!$a) { header('Location: blog.php'); exit; }
$others = array_values(array_filter($arts, fn($x) => $x['id'] !== $id));
$body = [
	['Comprendre', "Ce sujet touche de nombreuses filles et femmes au Bénin. Le comprendre, c’est déjà reprendre du pouvoir : savoir nommer ce qui se passe permet de chercher de l’aide et de se protéger."],
	['Les repères essentiels', $a['summary'] . ' Ces repères sont issus des fiches thématiques de Xomiton, élaborées avec des pair·es animateur·rices et des professionnel·les.'],
	['Que faire concrètement ?', "Parlez-en à une personne de confiance, notez les faits (dates, lieux, preuves), puis contactez un·e professionnel·le de l’annuaire. Vous pouvez aussi utiliser le signalement anonyme ou, en cas de danger immédiat, le bouton SOS."],
	['Vous n’êtes pas seule', "La communauté Xomiton est là pour vous écouter sans jugement. Rejoignez un groupe, participez à un atelier ou posez vos questions à l’assistant."],
];
x_pub_open($a['title'], ['nav' => 'blog', 'pubNav' => 'blog', 'desc' => $a['summary'], 'type' => 'article',
	'image' => 'covers/th-' . (int)$a['theme'] . '.jpg', 'imageAlt' => 'Couverture de l’article : ' . $a['title'], 'modified' => $a['date'],
	'breadcrumb' => [['Actualités', 'blog.php'], [$a['cat'], 'blog.php?cat=' . urlencode($a['cat'])], [$a['title'], 'blog-details.php?id=' . (int)$a['id']]],
	'schema' => ['@type' => 'Article', 'headline' => $a['title'], 'description' => $a['summary'], 'inLanguage' => 'fr-BJ', 'articleSection' => $a['cat'],
		'datePublished' => $a['date'], 'author' => ['@type' => 'Person', 'name' => $a['author']], 'publisher' => ['@type' => 'Organization', 'name' => 'Xomiton']]]);
$member = (x_user() !== null);
if (!$member) echo '<div class="container py-4"><div class="row"><div class="col-12 vstack gap-4">';
?>
<nav aria-label="Fil d’Ariane"><ol class="breadcrumb mb-0 small">
	<li class="breadcrumb-item"><a href="blog.php">Actualités</a></li>
	<li class="breadcrumb-item"><a href="blog.php?cat=<?= urlencode($a['cat']) ?>"><?= e($a['cat']) ?></a></li>
	<li class="breadcrumb-item active" aria-current="page">Article</li>
</ol></nav>
<div class="row g-4">
	<div class="col-lg-8">
		<article class="card overflow-hidden">
			<div class="xm-ratio-16x9" style="max-height:420px"><img class="xm-img-cover" src="<?= img('covers/th-' . (int)$a['theme'] . '.jpg') ?>" alt="Couverture de l’article : <?= e($a['title']) ?>" title="<?= e($a['title']) ?>" width="1200" height="800"></div>
			<div class="card-body p-4">
				<span class="badge bg-<?= $a['color'] ?> bg-opacity-10 text-<?= $a['color'] ?> mb-2"><i class="bi <?= $a['icon'] ?> me-1"></i><?= e($a['cat']) ?></span>
				<h1 class="h3"><?= e($a['title']) ?></h1>
				<div class="small text-secondary mb-4"><i class="bi bi-person me-1"></i><?= e($a['author']) ?> · <?= x_fr_date($a['date']) ?> · <i class="bi bi-clock me-1"></i><?= $a['minutes'] ?> min de lecture</div>
				<?php foreach ($body as $b): ?>
					<h2 class="h5 mt-4"><?= e($b[0]) ?></h2>
					<p><?= e($b[1]) ?></p>
				<?php endforeach; ?>
				<div class="alert alert-info mt-4 mb-0"><i class="bi bi-info-circle me-1"></i>Ce contenu informe mais ne remplace pas l’avis d’un·e professionnel·le. En cas de danger, utilisez <a href="sos.php" class="alert-link">SOS</a> ou les <a href="urgences.php" class="alert-link">contacts d’urgence</a>.</div>
				<hr>
				<div class="d-flex flex-wrap gap-2">
					<a class="btn btn-light btn-sm" href="blog.php"><i class="bi bi-arrow-left me-1"></i>Toutes les actualités</a>
					<button type="button" class="btn btn-primary-soft btn-sm" id="shareBtn"><i class="bi bi-share me-1"></i>Partager</button>
				</div>
			</div>
		</article>
	</div>
	<div class="col-lg-4">
		<div class="card"><div class="card-header"><h6 class="mb-0">À lire aussi</h6></div>
			<div class="list-group list-group-flush">
				<?php foreach (array_slice($others, 0, 4) as $o): ?>
					<a class="list-group-item list-group-item-action d-flex gap-3" href="blog-details.php?id=<?= $o['id'] ?>">
						<img src="<?= img('covers/th-' . (int)$o['theme'] . '.jpg') ?>" width="64" height="64" class="rounded object-fit-cover flex-shrink-0" alt="Couverture : <?= e($o['title']) ?>" title="<?= e($o['title']) ?>" loading="lazy">
						<span class="small"><b><?= e($o['title']) ?></b><br><span class="text-secondary"><?= x_fr_date($o['date']) ?></span></span>
					</a>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
</div>
<?php if (!$member) echo '</div></div></div>'; ?>
<?php x_pub_close(['scripts' => <<<'JS'
<script>
document.getElementById('shareBtn').addEventListener('click', function () {
	if (navigator.share) { navigator.share({ title: document.title, url: location.href }).catch(function () {}); }
	else if (navigator.clipboard) { navigator.clipboard.writeText(location.href).then(function () { XM.toast('Lien copié.', 'success'); }, function () { XM.toast('Copie impossible.', 'warning'); }); }
});
</script>
JS]); ?>
