<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/x_complement.php';
require_login();
$pageTitle = 'Manuel du pair animateur·rice';
$nav = 'ccc';
$mods = x_ccc_manuel();
$quiz = [
    'm1' => [
        ['Face à une personne qui se confie, la meilleure posture est…', ['Donner des conseils immédiatement', 'Écouter sans juger et respecter son rythme', 'Minimiser pour la rassurer'], 1],
        ['Que faire d’une information confiée par une personne ?', ['La garder confidentielle, sauf danger vital et avec son accord pour orienter', 'La partager avec le groupe pour aider', 'La publier anonymement'], 0],
        ['Prendre soin de soi en tant que pair animateur·rice, c’est…', ['Un luxe', 'Une condition pour durer et bien accompagner', 'Inutile si on est motivé·e'], 1],
    ],
    'm2' => [
        ['Laquelle n’est PAS une forme de VBG ?', ['Chantage à l’outing', 'Diffusion de photos intimes sans accord', 'Désaccord courtois entre amies'], 2],
        ['Dans le cycle de la violence, la « lune de miel » est…', ['La fin de la violence', 'Une phase de promesses avant une nouvelle tension', 'Une preuve que la violence n’était pas grave'], 1],
        ['Un signe d’alerte possible est…', ['Isolement et peur de rentrer chez soi', 'Une nouvelle coiffure', 'Un changement d’emploi du temps choisi'], 0],
    ],
    'm3' => [
        ['Le consentement doit être…', ['Libre, éclairé et réversible', 'Donné une fois pour toutes', 'Déduit du silence'], 0],
        ['Après une prise de risque de VIH, la PEP doit débuter…', ['Dans les 72 heures', 'Dans le mois', 'Quand on a le temps'], 0],
        ['Seul le préservatif protège à la fois des grossesses et des IST.', ['Vrai', 'Faux, d’autres méthodes protègent aussi des IST', 'On ne peut pas savoir'], 0],
    ],
    'm4' => [
        ['Pour orienter une personne, on utilise d’abord…', ['L’annuaire dynamique (même zone, bon rôle)', 'Son propre réseau personnel uniquement', 'Aucune orientation'], 0],
        ['En cas de danger immédiat, quel outil utiliser ?', ['Le signalement anonyme', 'Le SOS avec position GPS', 'Un message dans un forum'], 1],
        ['Une fiche de référence sert à…', ['Tracer l’orientation et assurer le suivi', 'Rendre public le cas', 'Remplacer la plainte'], 0],
    ],
    'm5' => [
        ['Avec une boîte à images, on commence par…', ['Expliquer la bonne réponse', 'Demander ce que chacun·e voit et ressent', 'Distribuer un test'], 1],
        ['Un groupe difficile se gère en…', ['Rappelant les règles du groupe et en reformulant', 'Excluant la personne', 'Ignorant les tensions'], 0],
        ['Évaluer une séance permet de…', ['Mesurer ce qui a été compris et améliorer la suite', 'Noter les participant·es', 'Remplir un registre sans utilité'], 0],
    ],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<?php include("partials/title-meta.php"); ?>
	<?php include("partials/head-css.php"); ?>
	<style>.vstack > * { flex-grow:0 !important; }</style>
	<style>@media print { .accordion-collapse { display:block !important; height:auto !important; } .quiz { display:none; } }</style>
</head>
<body>
	<?php include("partials/header.php"); ?>
	<main id="contenu" tabindex="-1">
		<div class="container">
			<div class="row g-4">
				<?php include("partials/sidenav.php"); ?>
				<div class="col-lg-9 vstack gap-4">
					<?= flash_html() ?>
					<nav aria-label="breadcrumb" class="no-print"><ol class="breadcrumb mb-0"><li class="breadcrumb-item"><a href="ccc.php">Outils CCC</a></li><li class="breadcrumb-item active">Manuel du pair animateur·rice</li></ol></nav>
					<div class="card overflow-hidden">
						<div class="row g-0">
							<div class="col-md-8 card-body p-4">
								<h1 class="h3">Manuel du pair animateur·rice</h1>
								<p class="text-secondary">Cochez les points vus, passez le quiz de fin de module et imprimez votre récapitulatif.</p>
								<div class="d-flex align-items-center gap-3"><div class="progress flex-grow-1" style="height:10px"><div class="progress-bar bg-success" id="gBar" style="width:0"></div></div><strong id="gPct">0 %</strong></div>
								<div class="d-flex flex-wrap gap-2 mt-3 no-print"><button class="btn btn-primary" type="button" onclick="window.print()"><i class="bi bi-printer me-1"></i>Télécharger / imprimer le récapitulatif</button><button class="btn btn-light" type="button" id="resetAll">Réinitialiser ma progression</button></div>
							</div>
							<div class="col-md-4 d-none d-md-block"><div class="h-100" style="min-height:160px;background:url(<?= img('hero-03.jpg') ?>) center/cover"></div></div>
						</div>
					</div>

					<div class="accordion" id="mods">
						<?php foreach ($mods as $mi => $m): $id = $m[0]; ?>
						<div class="accordion-item" data-mod="<?= e($id) ?>">
							<h2 class="accordion-header">
								<button class="accordion-button <?= $mi ? 'collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#c-<?= e($id) ?>" aria-expanded="<?= $mi ? 'false' : 'true' ?>">
									<span class="me-auto fw-semibold"><?= e($m[1]) ?></span>
									<span class="badge bg-secondary-subtle text-secondary-emphasis me-2"><i class="bi bi-clock me-1"></i><?= (int)$m[3] ?> min</span>
									<span class="badge bg-success-subtle text-success-emphasis me-2 mod-prog">0/<?= count($m[2]) ?></span>
								</button>
							</h2>
							<div id="c-<?= e($id) ?>" class="accordion-collapse collapse <?= $mi ? '' : 'show' ?>" data-bs-parent="#mods">
								<div class="accordion-body">
									<h3 class="h6">Contenu du module</h3>
									<div class="vstack gap-2 mb-4">
										<?php foreach ($m[2] as $i => $topic): ?>
										<div class="form-check"><input class="form-check-input topic" type="checkbox" id="t-<?= e($id) ?>-<?= $i ?>" data-key="<?= e($id) ?>-<?= $i ?>"><label class="form-check-label" for="t-<?= e($id) ?>-<?= $i ?>"><?= e($topic) ?></label></div>
										<?php endforeach; ?>
									</div>
									<div class="border rounded-3 p-3 bg-body-tertiary quiz" data-mod="<?= e($id) ?>">
										<h3 class="h6"><i class="bi bi-patch-question me-1 text-primary"></i>Quiz de fin de module</h3>
										<?php foreach ($quiz[$id] as $qi => $q): ?>
										<div class="mb-3 q" data-ok="<?= (int)$q[2] ?>">
											<div class="fw-semibold small mb-1"><?= $qi + 1 ?>. <?= e($q[0]) ?></div>
											<?php foreach ($q[1] as $oi => $opt): ?>
											<div class="form-check"><input class="form-check-input" type="radio" name="<?= e($id) ?>-q<?= $qi ?>" id="<?= e($id) ?>-q<?= $qi ?>-<?= $oi ?>" value="<?= $oi ?>"><label class="form-check-label small" for="<?= e($id) ?>-q<?= $qi ?>-<?= $oi ?>"><?= e($opt) ?></label></div>
											<?php endforeach; ?>
											<div class="small mt-1 fb"></div>
										</div>
										<?php endforeach; ?>
										<div class="d-flex align-items-center gap-3 no-print"><button class="btn btn-sm btn-primary check-quiz" type="button">Valider mes réponses</button><span class="quiz-score small fw-bold"></span></div>
									</div>
								</div>
							</div>
						</div>
						<?php endforeach; ?>
					</div>

					<div class="card card-body xm-print-only-block">
						<h2 class="h5">Mon récapitulatif</h2>
						<div id="summary" class="small"></div>
					</div>
				</div>
			</div>
		</div>
	</main>
	<?php include("partials/modals.php"); include("partials/footer-scripts.php"); ?>
	<script>
	(function () {
		var K = 'xm_manuel_v1', S = { t: {}, q: {} };
		try { S = Object.assign(S, JSON.parse(localStorage.getItem(K) || '{}')); } catch (e) {}
		var topics = document.querySelectorAll('.topic');
		function save() { try { localStorage.setItem(K, JSON.stringify(S)); } catch (e) {} }
		function refresh() {
			var done = 0, lines = [];
			document.querySelectorAll('#mods .accordion-item').forEach(function (it) {
				var tp = it.querySelectorAll('.topic'), n = 0; tp.forEach(function (b) { if (b.checked) n++; });
				done += n;
				var p = it.querySelector('.mod-prog'); p.textContent = n + '/' + tp.length;
				p.className = 'badge me-2 mod-prog ' + (n === tp.length ? 'bg-success' : 'bg-success-subtle text-success-emphasis');
				var sc = S.q[it.dataset.mod];
				lines.push('<div class="mb-1"><strong>' + it.querySelector('.accordion-button .fw-semibold').textContent + '</strong> : ' + n + '/' + tp.length + ' points vus' + (sc != null ? ' · quiz ' + sc + '/3' : ' · quiz non passé') + '</div>');
			});
			var pct = Math.round(100 * done / topics.length);
			document.getElementById('gBar').style.width = pct + '%'; document.getElementById('gPct').textContent = pct + ' %';
			document.getElementById('summary').innerHTML = lines.join('') + '<div class="mt-2 text-secondary">Édité le ' + new Date().toLocaleDateString('fr-FR') + '</div>';
		}
		topics.forEach(function (b) { b.checked = !!S.t[b.dataset.key]; b.addEventListener('change', function () { S.t[b.dataset.key] = b.checked; save(); refresh(); }); });
		document.querySelectorAll('.check-quiz').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var box = btn.closest('.quiz'), qs = box.querySelectorAll('.q'), score = 0, all = true;
				qs.forEach(function (q) {
					var sel = q.querySelector('input:checked'), fb = q.querySelector('.fb');
					if (!sel) { all = false; fb.textContent = 'Choisissez une réponse.'; fb.className = 'small mt-1 fb text-warning'; return; }
					var ok = +sel.value === +q.dataset.ok; if (ok) score++;
					fb.textContent = ok ? 'Bonne réponse.' : 'Pas tout à fait : la bonne réponse est « ' + q.querySelectorAll('label')[+q.dataset.ok + 0].textContent + ' ».';
					fb.className = 'small mt-1 fb ' + (ok ? 'text-success' : 'text-danger');
				});
				if (!all) return;
				box.querySelector('.quiz-score').textContent = 'Score : ' + score + '/' + qs.length;
				S.q[box.dataset.mod] = score; save(); refresh();
				XM.toast('Quiz terminé : ' + score + '/' + qs.length, score === qs.length ? 'success' : 'primary');
			});
		});
		document.querySelectorAll('.quiz').forEach(function (b) { var s = S.q[b.dataset.mod]; if (s != null) b.querySelector('.quiz-score').textContent = 'Dernier score : ' + s + '/3'; });
		document.getElementById('resetAll').addEventListener('click', function () { S = { t: {}, q: {} }; try { localStorage.removeItem(K); } catch (e) {} topics.forEach(function (b) { b.checked = false; }); document.querySelectorAll('.quiz input:checked').forEach(function (i) { i.checked = false; }); document.querySelectorAll('.quiz .fb,.quiz-score').forEach(function (f) { f.textContent = ''; }); refresh(); });
		refresh();
	})();
	</script>
</body>
</html>
