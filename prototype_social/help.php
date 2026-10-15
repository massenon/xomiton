<?php
require __DIR__ . '/inc/x_public.php';
$faq = x_faq();
$cats = x_help_categories();
$q = trim($_GET['q'] ?? '');
$cat = $_GET['cat'] ?? '';
x_pub_open('Centre d’aide', ['nav' => 'aide', 'pubNav' => 'aide', 'desc' => 'Centre d’aide Xomiton : questions fréquentes sur le compte, les signalements, le soutien et la communauté.']);
$member = (x_user() !== null);
if (!$member) echo '<div class="container py-4"><div class="row"><div class="col-12 vstack gap-4">';
$rows = [];
foreach ($faq as $i => $f) {
	$k = x_faq_cat($i);
	if ($cat !== '' && $k !== $cat) continue;
	if ($q !== '' && mb_stripos($f[0] . ' ' . $f[1], $q) === false) continue;
	$rows[] = [$i, $f, $k];
}
?>
<div class="card overflow-hidden">
	<div class="p-4 p-lg-5 text-white text-center xm-hero-bg xm-hero-page">
		<h1 class="h2 fw-bold text-white">Comment pouvons-nous vous aider ?</h1>
		<form class="mx-auto mt-3" style="max-width:560px" method="get" action="help.php" role="search">
			<?php if ($cat): ?><input type="hidden" name="cat" value="<?= e($cat) ?>"><?php endif; ?>
			<div class="input-group input-group-lg">
				<span class="input-group-text bg-body"><i class="bi bi-search"></i></span>
				<input type="search" class="form-control" name="q" value="<?= e($q) ?>" placeholder="Rechercher : signalement, données, SOS…" aria-label="Rechercher dans l’aide" id="helpSearch">
				<button class="btn btn-danger" type="submit">Chercher</button>
			</div>
		</form>
	</div>
</div>

<div class="row g-3">
	<?php foreach ($cats as $c): $n = 0; foreach ($faq as $i => $f) if (x_faq_cat($i) === $c[0]) $n++; ?>
		<div class="col-6 col-lg-3">
			<a href="help.php?cat=<?= $c[0] ?>" class="card h-100 text-reset text-center xm-card-hover <?= $cat === $c[0] ? 'border-primary border-2' : '' ?>">
				<div class="card-body">
					<span class="xm-feature-icon mx-auto mb-2 bg-<?= $c[3] ?> bg-opacity-10 text-<?= $c[3] ?>"><i class="bi <?= $c[2] ?>"></i></span>
					<h6 class="mb-1"><?= e($c[1]) ?></h6>
					<small class="text-secondary"><?= $n ?> article<?= $n > 1 ? 's' : '' ?></small>
				</div>
			</a>
		</div>
	<?php endforeach; ?>
</div>

<div class="card">
	<div class="card-header d-flex justify-content-between align-items-center">
		<h5 class="mb-0"><?= $cat ? e(array_values(array_filter($cats, fn($c) => $c[0] === $cat))[0][1] ?? 'Questions') : 'Questions fréquentes' ?></h5>
		<?php if ($q !== '' || $cat !== ''): ?><a href="help.php" class="btn btn-sm btn-light"><i class="bi bi-x-lg me-1"></i>Effacer les filtres</a><?php endif; ?>
	</div>
	<div class="card-body">
		<?php if (!$rows): ?>
			<div class="text-center text-secondary py-4"><i class="bi bi-search fs-1 d-block mb-2"></i>Aucun résultat pour « <?= e($q) ?> ». Essayez un autre mot ou contactez l’équipe.</div>
		<?php else: ?>
			<div class="accordion accordion-flush" id="helpAcc">
				<?php foreach ($rows as $n => [$i, $f, $k]): ?>
					<div class="accordion-item">
						<h2 class="accordion-header"><button class="accordion-button <?= $n ? 'collapsed' : '' ?> fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#h<?= $i ?>"><?= e($f[0]) ?></button></h2>
						<div id="h<?= $i ?>" class="accordion-collapse collapse <?= $n ? '' : 'show' ?>" data-bs-parent="#helpAcc">
							<div class="accordion-body">
								<p><?= e($f[1]) ?></p>
								<a class="small" href="help-details.php?id=<?= $i + 1 ?>">Lire la suite <i class="bi bi-arrow-right"></i></a>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</div>

<div class="card border-danger"><div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
	<div><h5 class="mb-1 text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Vous êtes en danger ?</h5><p class="mb-0 small">Ne cherchez pas dans l’aide : agissez maintenant.</p></div>
	<div class="d-flex gap-2"><a class="btn btn-danger" href="sos.php"><i class="bi bi-broadcast me-1"></i>SOS</a><a class="btn btn-outline-danger" href="urgences.php">Contacts d’urgence</a><a class="btn btn-light" href="signalement.php">Signaler</a></div>
</div></div>
<?php if (!$member) echo '</div></div></div>'; ?>
<?php x_pub_close(); ?>
