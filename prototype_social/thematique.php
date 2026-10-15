<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();
require_once __DIR__ . '/inc/x_reseau.php';
require_once __DIR__ . '/inc/x_thematiques_content.php';
$t = x_thematique($_GET['id'] ?? 0);
if (!$t) { x_redirect('thematiques.php', 'warning', 'Cette thématique est introuvable.'); }
$cats = x_thematique_cats();
$c = $cats[$t['cat']];
$content = x_thematique_content($t);
$related = array_values(array_filter(x_thematiques(), fn($r) => $r['cat'] === $t['cat'] && $r['id'] !== $t['id']));
$related = array_slice($related, 0, 3);
if (count($related) < 3) {
    foreach (x_thematiques() as $r) { if ($r['id'] !== $t['id'] && $r['cat'] !== $t['cat'] && count($related) < 3) $related[] = $r; }
}
$pageTitle = $t['title'];
$pageDesc = $content['intro'];
$pageImage = $t['cover'];
$pageImageAlt = 'Couverture de la fiche : ' . $t['title'];
$pageType = 'article';
$breadcrumb = [['Thématiques', 'thematiques.php'], [$c[0], 'thematiques.php?cat=' . $t['cat']], [$t['title'], 'thematique.php?id=' . (int)$t['id']]];
$pageSchema = ['@type' => 'Article', 'headline' => $t['title'], 'description' => $content['intro'], 'inLanguage' => 'fr-BJ',
	'articleSection' => $c[0], 'timeRequired' => 'PT' . (int)$t['minutes'] . 'M',
	'author' => ['@type' => 'Organization', 'name' => 'Xomiton'], 'publisher' => ['@type' => 'Organization', 'name' => 'Xomiton']];
$nav = 'thematiques';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<?php include("partials/title-meta.php"); ?>
	<?php include("partials/head-css.php"); ?>
</head>
<body>
	<?php include("partials/header.php"); ?>
	<main id="contenu" tabindex="-1">
		<div class="container">
			<div class="row g-4">
				<?php include("partials/sidenav.php"); ?>
				<div class="col-md-8 col-lg-6 vstack gap-4">
					<?= flash_html() ?>
					<nav aria-label="breadcrumb"><ol class="breadcrumb mb-0 small">
						<li class="breadcrumb-item"><a href="thematiques.php">Thématiques</a></li>
						<li class="breadcrumb-item"><a href="thematiques.php?cat=<?= e($t['cat']) ?>"><?= e($c[0]) ?></a></li>
						<li class="breadcrumb-item active" aria-current="page">Fiche <?= (int)$t['id'] ?></li>
					</ol></nav>

					<article class="card overflow-hidden">
						<div class="xm-ratio-16x9"><img class="xm-img-cover" src="<?= img($t['cover']) ?>" alt="Couverture de la fiche : <?= e($t['title']) ?>" title="<?= e($t['title']) ?>" width="1200" height="800"></div>
						<div class="card-body">
							<span class="badge bg-<?= $c[2] ?> bg-opacity-10 text-<?= $c[2] ?> mb-2"><i class="bi <?= $c[1] ?> me-1"></i><?= e($c[0]) ?></span>
							<h1 class="h4"><?= e($t['title']) ?></h1>
							<p class="small text-secondary"><i class="bi bi-clock me-1"></i><?= (int)$t['minutes'] ?> min de lecture · Fiche pédagogique Xomiton</p>
							<p class="lead fs-6"><?= e($content['intro']) ?></p>
							<?php foreach ($content['paragraphs'] as $p): ?><p><?= e($p) ?></p><?php endforeach; ?>

							<h2 class="h5 mt-4"><i class="bi bi-list-check text-primary me-2"></i>Points clés</h2>
							<ul class="list-group list-group-flush mb-3">
								<?php foreach ($content['points'] as $pt): ?><li class="list-group-item px-0"><i class="bi bi-check-circle-fill text-success me-2"></i><?= e($pt) ?></li><?php endforeach; ?>
							</ul>

							<h2 class="h5 mt-4"><i class="bi bi-signpost-split text-primary me-2"></i>Que faire ?</h2>
							<ol class="mb-3">
								<?php foreach ($content['steps'] as $s): ?><li class="mb-1"><?= e($s) ?></li><?php endforeach; ?>
							</ol>

							<h2 class="h5 mt-4"><i class="bi bi-bank text-primary me-2"></i>Cadre légal</h2>
							<div class="vstack gap-2 mb-3">
								<?php foreach ($content['legal'] as $l): ?>
								<div class="border rounded p-3"><div class="fw-bold small"><?= e($l[0]) ?></div><div class="small text-secondary"><?= e($l[1]) ?></div></div>
								<?php endforeach; ?>
							</div>
							<p class="small text-secondary">Ces informations sont éducatives et ne remplacent pas un conseil juridique personnalisé. Consultez un·e juriste de l’annuaire pour votre situation.</p>

							<div class="alert alert-primary mt-4 mb-0">
								<h2 class="h6"><i class="bi bi-lightbulb me-2"></i>À retenir</h2>
								<p class="mb-0"><?= e($content['retenir']) ?></p>
							</div>
						</div>
					</article>
				</div>

				<div class="col-lg-3">
					<div class="row g-4">
						<div class="col-sm-6 col-lg-12">
							<div class="card border-primary">
								<div class="card-header pb-0 border-0"><h5 class="card-title mb-0">Besoin d’aide ?</h5></div>
								<div class="card-body">
									<div class="vstack gap-2">
										<?php foreach ($content['contacts'] as $k): ?>
										<a class="btn btn-sm btn-primary-soft text-start" href="<?= e($k[1]) ?>"><i class="bi <?= e($k[2]) ?> me-2"></i><?= e($k[0]) ?></a>
										<?php endforeach; ?>
									</div>
								</div>
							</div>
						</div>
						<div class="col-sm-6 col-lg-12">
							<div class="card">
								<div class="card-header pb-0 border-0"><h5 class="card-title mb-0">Thématiques liées</h5></div>
								<div class="card-body">
									<?php foreach ($related as $r): ?>
									<div class="d-flex mb-3">
										<a class="flex-shrink-0 rounded-2 overflow-hidden d-block" style="width:88px" href="thematique.php?id=<?= (int)$r['id'] ?>" tabindex="-1" aria-hidden="true"><div class="xm-ratio-16x9"><img class="xm-img-cover" src="<?= img($r['cover']) ?>" alt="Couverture : <?= e($r['title']) ?>" title="<?= e($r['title']) ?>" loading="lazy"></div></a>
										<div class="ms-3"><h6 class="card-title mb-0 lh-sm"><a href="thematique.php?id=<?= (int)$r['id'] ?>"><?= e($r['title']) ?></a></h6><p class="small mb-0 text-secondary"><?= (int)$r['minutes'] ?> min</p></div>
									</div>
									<?php endforeach; ?>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</main>
	<?php include("partials/modals.php"); ?>
	<?php include("partials/footer-scripts.php"); ?>
</body>
</html>
