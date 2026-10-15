<?php
require __DIR__ . '/inc/x_public.php';
$arts = x_blog_articles();
$cat = $_GET['cat'] ?? '';
$cats = array_values(array_unique(array_column($arts, 'cat')));
if ($cat !== '') $arts = array_values(array_filter($arts, fn($a) => $a['cat'] === $cat));
$events = array_slice(store_all('events'), 0, 3);
[$arts, $moreBtn] = x_paginate($arts, 6);
x_pub_open('Actualités', ['image' => 'covers/th-' . ($arts[0]['theme'] ?? 1) . '.jpg', 'breadcrumb' => [['Actualités', 'blog.php']], 'nav' => 'blog', 'pubNav' => 'blog', 'desc' => 'Actualités et articles de Xomiton : droits, santé, soutien et autonomie.']);
$member = (x_user() !== null);
if (!$member) echo '<div class="container py-4"><div class="row"><div class="col-12 vstack gap-4">';
?>
<div class="d-flex flex-wrap align-items-end justify-content-between gap-2">
	<div><h1 class="h3 mb-1">Actualités</h1><p class="text-secondary mb-0">Articles, guides et nouvelles de la communauté Xomiton.</p></div>
	<div class="d-flex flex-wrap gap-1">
		<a class="btn btn-sm <?= $cat === '' ? 'btn-primary' : 'btn-light' ?>" href="blog.php">Tout</a>
		<?php foreach ($cats as $c): ?><a class="btn btn-sm <?= $cat === $c ? 'btn-primary' : 'btn-light' ?>" href="blog.php?cat=<?= urlencode($c) ?>"><?= e($c) ?></a><?php endforeach; ?>
	</div>
</div>

<div class="row g-4">
	<div class="col-lg-8">
		<div class="row g-4" id="liste">
			<?php foreach ($arts as $n => $a): $feat = ($n === 0 && $cat === ''); ?>
				<div class="<?= $feat ? 'col-12' : 'col-md-6' ?>">
					<article class="card h-100 xm-card-hover overflow-hidden">
						<a href="blog-details.php?id=<?= $a['id'] ?>" tabindex="-1" aria-hidden="true"><div class="<?= $feat ? 'xm-ratio-16x9' : 'xm-ratio-3x2' ?>"><img class="xm-img-cover" src="<?= img('covers/th-' . (int)$a['theme'] . '.jpg') ?>" alt="Couverture de l’article : <?= e($a['title']) ?>" title="<?= e($a['title']) ?>"<?= $feat ? '' : ' loading="lazy"' ?> width="1200" height="800"></div></a>
						<div class="card-body">
							<span class="badge bg-<?= $a['color'] ?> bg-opacity-10 text-<?= $a['color'] ?> mb-2"><i class="bi <?= $a['icon'] ?> me-1"></i><?= e($a['cat']) ?></span>
							<h2 class="<?= $feat ? 'h4' : 'h6' ?>"><a class="text-reset" href="blog-details.php?id=<?= $a['id'] ?>"><?= e($a['title']) ?></a></h2>
							<p class="small text-secondary"><?= e($a['summary']) ?></p>
						</div>
						<div class="card-footer small text-secondary d-flex justify-content-between"><span><?= e($a['author']) ?> · <?= x_fr_date($a['date']) ?></span><span><i class="bi bi-clock me-1"></i><?= $a['minutes'] ?> min</span></div>
					</article>
				</div>
			<?php endforeach; ?>
			<?php if (!$arts): ?><div class="col-12 text-center text-secondary py-5">Aucun article dans cette catégorie.</div><?php endif; ?>
		</div>
			<?= $moreBtn ?>
	</div>
	<div class="col-lg-4 vstack gap-4">
		<div class="card"><div class="card-header"><h6 class="mb-0"><i class="bi bi-calendar-event me-1"></i>Prochains événements</h6></div>
			<div class="list-group list-group-flush">
				<?php foreach ($events as $ev): ?>
					<a class="list-group-item list-group-item-action d-flex gap-3 align-items-center" href="<?= $member ? 'event-details.php?id=' . (int)$ev['id'] : 'sign-in.php' ?>">
						<img src="<?= img($ev['image']) ?>" width="56" height="56" class="rounded object-fit-cover" alt="Visuel de l’événement : <?= e($ev['title']) ?>" title="<?= e($ev['title']) ?>" loading="lazy">
						<span class="small"><b><?= e($ev['title']) ?></b><br><span class="text-secondary"><?= x_fr_date($ev['date']) ?> · <?= e($ev['place']) ?></span></span>
					</a>
				<?php endforeach; ?>
			</div>
		</div>
		<div class="card"><div class="card-body text-center">
			<img src="<?= img('personnage-2.png') ?>" height="90" alt="Illustration : un personnage souriant du centre d’aide Xomiton" title="Centre d’aide Xomiton" class="mb-2">
			<h6>Une question ?</h6>
			<p class="small text-secondary">Consultez le centre d’aide ou discutez avec l’assistant.</p>
			<a class="btn btn-primary-soft btn-sm" href="help.php">Centre d’aide</a>
		</div></div>
	</div>
</div>
<?php if (!$member) echo '</div></div></div>'; ?>
<?php x_pub_close(); ?>
