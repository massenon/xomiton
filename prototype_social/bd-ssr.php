<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/x_complement.php';
require_login();
$pageTitle = 'Bandes dessinées SSR';
$nav = 'ccc';
$bds = x_ccc_bd();
$cur = $_GET['id'] ?? $bds[0][0];
$bd = $bds[0];
foreach ($bds as $b) if ($b[0] === $cur) $bd = $b;
$pans = ['hero-09.jpg', 'hero-10.jpg', 'hero-12.jpg', 'hero-13.jpg', 'hero-04.jpg', 'hero-03.jpg'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<?php include("partials/title-meta.php"); ?>
	<?php include("partials/head-css.php"); ?>
	<style>.vstack > * { flex-grow:0 !important; }</style>
	<style>.xm-comic-panel .cap { color:#1B1630; } #panelImg { aspect-ratio:16/10; object-fit:cover; width:100%; display:block; }</style>
</head>
<body>
	<?php include("partials/header.php"); ?>
	<main id="contenu" tabindex="-1">
		<div class="container">
			<div class="row g-4">
				<?php include("partials/sidenav.php"); ?>
				<div class="col-lg-9 vstack gap-4">
					<?= flash_html() ?>
					<nav aria-label="breadcrumb"><ol class="breadcrumb mb-0"><li class="breadcrumb-item"><a href="ccc.php">Outils CCC</a></li><li class="breadcrumb-item active">Bandes dessinées SSR</li></ol></nav>
					<div class="row g-3">
						<div class="col-lg-8">
							<h1 class="h4"><?= e($bd[1]) ?></h1>
							<div class="xm-comic-panel mb-3" id="reader" tabindex="0" aria-live="polite">
								<div class="position-relative"><img id="panelImg" src="<?= img($bd[2]) ?>" alt=""><span class="position-absolute top-0 start-0 m-2 badge bg-dark" id="pnum"></span></div>
								<div class="cap" id="cap"></div>
							</div>
							<div class="d-flex align-items-center gap-2">
								<button class="btn btn-primary-soft" type="button" id="prev"><i class="bi bi-chevron-left me-1"></i>Précédent</button>
								<div class="progress flex-grow-1" style="height:6px"><div class="progress-bar" id="pbar"></div></div>
								<button class="btn btn-primary" type="button" id="next">Suivant<i class="bi bi-chevron-right ms-1"></i></button>
							</div>
							<div class="d-flex flex-wrap gap-2 mt-3">
								<button class="btn btn-sm btn-light" type="button" id="share"><i class="bi bi-share me-1"></i>Partager</button>
								<span class="small text-secondary align-self-center"><i class="bi bi-keyboard me-1"></i>Utilisez les flèches ← → du clavier.</span>
							</div>
							<div class="card card-body mt-4" id="discuss" hidden>
								<h2 class="h6"><i class="bi bi-chat-heart me-1 text-primary"></i>Discutons de l’histoire</h2>
								<ul class="small mb-0"><li>Qu’a ressenti le personnage ?</li><li>Qu’aurions-nous fait à sa place ?</li><li>Quelles ressources de l’<a href="annuaire.php">annuaire</a> pouvaient l’aider ?</li></ul>
							</div>
						</div>
						<div class="col-lg-4">
							<div class="card">
								<div class="card-header"><h2 class="h6 mb-0">Toutes les histoires</h2></div>
								<div class="list-group list-group-flush">
									<?php foreach ($bds as $b): ?>
									<a class="list-group-item list-group-item-action d-flex align-items-center gap-3 <?= $b[0] === $bd[0] ? 'active' : '' ?>" href="?id=<?= e($b[0]) ?>">
										<img src="<?= img($b[2]) ?>" alt="" class="rounded" style="width:56px;height:42px;object-fit:cover">
										<span class="small fw-semibold"><?= e($b[1]) ?><span class="d-block fw-normal opacity-75"><?= count($b[3]) ?> planches</span></span>
									</a>
									<?php endforeach; ?>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</main>
	<?php include("partials/modals.php"); include("partials/footer-scripts.php"); ?>
	<script>
	(function () {
		var caps = <?= json_encode($bd[3], JSON_UNESCAPED_UNICODE) ?>, imgs = <?= json_encode(array_map('img', array_merge([$bd[2]], array_slice(array_values(array_diff($pans, [$bd[2]])), 0, 3)))) ?>;
		var i = 0, title = <?= json_encode($bd[1], JSON_UNESCAPED_UNICODE) ?>;
		var cap = document.getElementById('cap'), im = document.getElementById('panelImg');
		function show() {
			cap.textContent = caps[i]; im.src = imgs[i % imgs.length]; im.alt = 'Planche ' + (i + 1);
			document.getElementById('pnum').textContent = 'Planche ' + (i + 1) + ' / ' + caps.length;
			document.getElementById('pbar').style.width = (100 * (i + 1) / caps.length) + '%';
			document.getElementById('prev').disabled = i === 0;
			document.getElementById('next').innerHTML = i === caps.length - 1 ? 'Terminer<i class="bi bi-check2 ms-1"></i>' : 'Suivant<i class="bi bi-chevron-right ms-1"></i>';
			document.getElementById('discuss').hidden = i !== caps.length - 1;
		}
		function go(n) { i = Math.max(0, Math.min(caps.length - 1, i + n)); show(); }
		document.getElementById('prev').addEventListener('click', function () { go(-1); });
		document.getElementById('next').addEventListener('click', function () { if (i === caps.length - 1) XM.toast('Histoire terminée. Place à la discussion !', 'success'); else go(1); });
		document.addEventListener('keydown', function (e) {
			if (/input|textarea|select/i.test((e.target.tagName || ''))) return;
			if (e.key === 'ArrowRight') go(1); else if (e.key === 'ArrowLeft') go(-1);
		});
		document.getElementById('share').addEventListener('click', function () {
			var url = location.href, text = 'Bande dessinée Xomiton : ' + title;
			if (navigator.share) navigator.share({ title: title, text: text, url: url }).catch(function () {});
			else if (navigator.clipboard) navigator.clipboard.writeText(text + ' ' + url).then(function () { XM.toast('Lien copié dans le presse-papiers.', 'success'); }, function () { XM.toast(url, 'secondary'); });
			else XM.toast(url, 'secondary');
		});
		show();
	})();
	</script>
</body>
</html>
