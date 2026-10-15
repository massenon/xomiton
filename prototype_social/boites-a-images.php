<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/x_complement.php';
require_login();
$pageTitle = 'Boîtes à images';
$nav = 'ccc';
$boxes = x_ccc_boites();
$questions = ['Que voyez-vous sur cette image ?', 'Que ressent chaque personne ?', 'Qu’est-ce qui pourrait se passer ensuite ?', 'Que feriez-vous à sa place ?', 'Qui pourrait aider, et comment ?'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<?php include("partials/title-meta.php"); ?>
	<?php include("partials/head-css.php"); ?>
	<style>.vstack > * { flex-grow:0 !important; }</style>
	<style>
		#slideshow { position:fixed; inset:0; z-index:2100; background:#0E1020; color:#fff; display:flex; flex-direction:column; }
		#slideshow[hidden] { display:none; }
		#slideshow .stage { flex:1; min-height:0; display:flex; align-items:center; justify-content:center; padding:1rem; }
		#slideshow img { max-width:100%; max-height:100%; object-fit:contain; border-radius:1rem; }
		#slideshow .guide { background:rgba(255,255,255,.08); padding:1rem 1.5rem; }
	</style>
</head>
<body>
	<?php include("partials/header.php"); ?>
	<main id="contenu" tabindex="-1">
		<div class="container">
			<div class="row g-4">
				<?php include("partials/sidenav.php"); ?>
				<div class="col-lg-9 vstack gap-4">
					<?= flash_html() ?>
					<nav aria-label="breadcrumb"><ol class="breadcrumb mb-0"><li class="breadcrumb-item"><a href="ccc.php">Outils CCC</a></li><li class="breadcrumb-item active">Boîtes à images</li></ol></nav>
					<div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
						<div><h1 class="h3 mb-0">Boîtes à images</h1><p class="text-secondary mb-0">Touchez une carte pour voir le guide de l’animateur·rice. Utilisez le mode animation pour projeter les images au groupe.</p></div>
						<button class="btn btn-primary" type="button" id="startShow"><i class="bi bi-fullscreen me-1"></i>Mode animation</button>
					</div>

					<div class="row g-3">
						<?php foreach ($boxes as $i => $b): ?>
						<div class="col-sm-6 col-xl-4">
							<div class="xm-flip" tabindex="0" role="button" aria-label="Retourner la carte : <?= e($b[0]) ?>">
								<div class="xm-flip-inner" style="min-height:300px">
									<div class="xm-flip-front card">
										<img class="xm-img-cover" src="<?= img($b[1]) ?>" alt="" loading="lazy">
										<div class="position-absolute bottom-0 start-0 end-0 p-3 text-white" style="background:linear-gradient(transparent,rgba(0,0,0,.75))"><span class="badge bg-primary mb-1">Image <?= $i + 1 ?></span><div class="fw-bold"><?= e($b[0]) ?></div></div>
										<span class="position-absolute top-0 end-0 m-2 badge bg-dark bg-opacity-50"><i class="bi bi-arrow-repeat"></i> guide</span>
									</div>
									<div class="xm-flip-back">
										<div><div class="fw-bold text-primary mb-2"><?= e($b[0]) ?></div><p class="small mb-2"><?= e($b[2]) ?></p><div class="small text-secondary"><i class="bi bi-people me-1"></i>10 à 15 min · petit groupe</div></div>
									</div>
								</div>
							</div>
						</div>
						<?php endforeach; ?>
					</div>

					<div class="card card-body">
						<h2 class="h5"><i class="bi bi-lightbulb me-1 text-warning"></i>Questions types pour animer</h2>
						<ol class="mb-0 small vstack gap-1"><?php foreach ($questions as $q): ?><li><?= e($q) ?></li><?php endforeach; ?></ol>
						<p class="small text-secondary mt-3 mb-0">Rappel : créez un cadre sûr (confidentialité, respect, droit de ne pas répondre) et orientez via l’<a href="annuaire.php">annuaire</a> si une personne se confie.</p>
					</div>
				</div>
			</div>
		</div>
	</main>

	<div id="slideshow" hidden role="dialog" aria-modal="true" aria-label="Mode animation">
		<div class="d-flex justify-content-between align-items-center p-3"><span class="badge bg-primary" id="ssCount"></span><button class="btn btn-light btn-sm" type="button" id="ssClose"><i class="bi bi-x-lg me-1"></i>Fermer (Échap)</button></div>
		<div class="stage"><img id="ssImg" src="" alt=""></div>
		<div class="guide">
			<div class="d-flex flex-wrap gap-3 align-items-center">
				<div class="flex-grow-1"><div class="h5 mb-1" id="ssTitle"></div><div class="small opacity-75" id="ssGuide"></div></div>
				<div class="d-flex gap-2"><button class="btn btn-outline-light" type="button" id="ssPrev" aria-label="Précédente"><i class="bi bi-chevron-left"></i></button><button class="btn btn-light" type="button" id="ssNext" aria-label="Suivante"><i class="bi bi-chevron-right"></i></button></div>
			</div>
		</div>
	</div>

	<?php include("partials/modals.php"); include("partials/footer-scripts.php"); ?>
	<script>
	(function () {
		var data = <?= json_encode(array_map(fn($b) => ['t' => $b[0], 'i' => img($b[1]), 'g' => $b[2]], $boxes), JSON_UNESCAPED_UNICODE) ?>;
		var idx = 0, ss = document.getElementById('slideshow');
		function show() {
			var d = data[idx];
			document.getElementById('ssImg').src = d.i; document.getElementById('ssImg').alt = d.t;
			document.getElementById('ssTitle').textContent = d.t; document.getElementById('ssGuide').textContent = d.g;
			document.getElementById('ssCount').textContent = (idx + 1) + ' / ' + data.length;
		}
		function open() { idx = 0; show(); ss.hidden = false; document.body.style.overflow = 'hidden'; if (ss.requestFullscreen) ss.requestFullscreen().catch(function () {}); }
		function close() { ss.hidden = true; document.body.style.overflow = ''; if (document.fullscreenElement && document.exitFullscreen) document.exitFullscreen().catch(function () {}); }
		function step(n) { idx = (idx + n + data.length) % data.length; show(); }
		document.getElementById('startShow').addEventListener('click', open);
		document.getElementById('ssClose').addEventListener('click', close);
		document.getElementById('ssPrev').addEventListener('click', function () { step(-1); });
		document.getElementById('ssNext').addEventListener('click', function () { step(1); });
		document.addEventListener('keydown', function (e) {
			if (!ss.hidden) { if (e.key === 'ArrowRight') step(1); else if (e.key === 'ArrowLeft') step(-1); else if (e.key === 'Escape') close(); }
			else if ((e.key === 'Enter' || e.key === ' ') && e.target.classList && e.target.classList.contains('xm-flip')) { e.preventDefault(); e.target.classList.toggle('flipped'); }
		});
		document.addEventListener('fullscreenchange', function () { if (!document.fullscreenElement && !ss.hidden) close(); });
	})();
	</script>
</body>
</html>
