<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/x_complement.php';
$pub = !x_user();
if ($pub) $noSosFab = true;
$pageTitle = 'Contacts d’urgence';
$nav = 'urgences';
$ann = array_filter(store_all('annuaire'), fn($r) => !empty($r['active']) && (!empty($r['urgent']) || in_array($r['role'], ['Psychologue', 'Juriste'], true)));
$services = [
    ['Police secours', '117', 'bi-shield-fill-check', 'primary'],
    ['Pompiers / secours', '118', 'bi-fire', 'danger'],
    ['Gendarmerie nationale', '+229 01 21 30 00 00', 'bi-shield-lock-fill', 'primary'],
    ['Ligne d’écoute Xomiton', '+229 01 97 38 88 47', 'bi-headset', 'success'],
];
$lieux = [
    ['Office central de protection des mineurs (OCPM)', 'Cotonou', 'Pour les victimes de moins de 18 ans.'],
    ['Centre de promotion sociale (CPS)', 'Dans chaque commune', 'Accueil, écoute, orientation et hébergement temporaire.'],
    ['Brigade de protection de la famille', 'Cotonou et chefs-lieux', 'Dépôt de plainte pour violences domestiques.'],
    ['Centre de santé partenaire', 'Selon votre zone', 'Soins, certificat médical, PEP, contraception d’urgence.'],
];
$plan = [
    'J’ai repéré un lieu sûr où aller (amie, famille, association).',
    'J’ai mémorisé 2 numéros d’urgence importants.',
    'Mes documents (pièce d’identité, acte de naissance, carnet de santé) sont dans un endroit accessible ou en copie chez une personne de confiance.',
    'J’ai de l’argent (même un peu) et de quoi me déplacer.',
    'J’ai prévenu une personne de confiance de ma situation.',
    'Je sais comment partir rapidement (sortie, transport).',
    'J’ai convenu d’un mot-code avec une personne de confiance pour demander de l’aide.',
    'Je sauvegarde mes preuves (captures, certificats) en lieu sûr.',
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<?php include("partials/title-meta.php"); ?>
	<?php include("partials/head-css.php"); ?>
	<style>.vstack > * { flex-grow:0 !important; }</style>
</head>
<body>
	<?php if ($pub) x_public_header(); else include("partials/header.php"); ?>
	<main id="contenu" tabindex="-1">
		<div class="container">
			<div class="row g-4">
				<?php if (!$pub) include("partials/sidenav.php"); ?>
				<div class="<?= $pub ? 'col-lg-9 mx-auto' : 'col-lg-9' ?> vstack gap-4">
					<?= flash_html() ?>
					<div class="alert alert-danger d-flex align-items-center gap-3 mb-0" role="alert">
						<i class="bi bi-exclamation-triangle-fill fs-3"></i>
						<div class="flex-grow-1"><strong>En danger immédiat ?</strong> Appelez le 117 ou envoyez une alerte SOS avec votre position.</div>
						<a class="btn btn-danger" href="sos.php">SOS</a>
					</div>

					<div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
						<div><h1 class="h3 mb-0">Contacts d’urgence</h1><p class="text-secondary mb-0">Appelez directement depuis votre téléphone. Appels gratuits ou à tarif réduit selon l’opérateur.</p></div>
						<button class="btn btn-dark" type="button" id="quickExit"><i class="bi bi-box-arrow-up-right me-1"></i>Quitter rapidement</button>
					</div>

					<!-- Boutons d'appel -->
					<div class="row g-3">
						<?php foreach ($services as $s): ?>
						<div class="col-sm-6">
							<a class="card card-body flex-row align-items-center gap-3 xm-card-hover text-decoration-none border-<?= $s[3] ?>" href="<?= e(x_tel($s[1])) ?>">
								<span class="icon-xl bg-<?= $s[3] ?> text-white rounded-circle flex-shrink-0"><i class="bi <?= $s[2] ?> fs-4"></i></span>
								<span><span class="d-block fw-bold"><?= e($s[0]) ?></span><span class="d-block h4 mb-0 text-<?= $s[3] ?>"><?= e($s[1]) ?></span></span>
								<i class="bi bi-telephone-outbound-fill ms-auto fs-4 text-<?= $s[3] ?>"></i>
							</a>
						</div>
						<?php endforeach; ?>
					</div>
					<p class="small text-secondary mt-n2"><i class="bi bi-info-circle me-1"></i>Numéros donnés à titre d’exemple dans ce prototype : à vérifier et remplacer avant la mise en production.</p>

					<!-- Annuaire urgent -->
					<div>
						<div class="d-flex justify-content-between align-items-center mb-2"><h2 class="h5 mb-0">Psychologues et juristes de l’annuaire</h2><?php if (!$pub): ?><a class="small" href="annuaire.php">Tout l’annuaire</a><?php endif; ?></div>
						<div class="row g-3">
							<?php foreach ($ann as $r): ?>
							<div class="col-md-6">
								<div class="card card-body h-100">
									<div class="d-flex gap-3">
										<div class="avatar avatar-md flex-shrink-0"><img class="avatar-img rounded-circle" src="<?= x_avatar($r['id']) ?>" alt=""></div>
										<div class="flex-grow-1 min-w-0">
											<div class="fw-bold text-truncate"><?= e($r['name']) ?></div>
											<div class="small text-secondary"><span class="badge bg-primary bg-opacity-10 text-primary"><?= e($r['role']) ?></span> <?= e($r['commune']) ?>, <?= e($r['zone']) ?></div>
											<div class="small mt-1"><i class="bi bi-clock me-1"></i><?= e($r['availability']) ?></div>
										</div>
									</div>
									<div class="d-flex gap-2 mt-3">
										<a class="btn btn-sm btn-success flex-fill" href="<?= e(x_tel($r['phone'])) ?>"><i class="bi bi-telephone-fill me-1"></i>Appeler</a>
										<a class="btn btn-sm btn-success-soft flex-fill" href="<?= e(x_wa($r['whatsapp'] ?? $r['phone'])) ?>" target="_blank" rel="noopener"><i class="bi bi-whatsapp me-1"></i>WhatsApp</a>
									</div>
								</div>
							</div>
							<?php endforeach; if (!$ann): ?><div class="col-12 text-secondary">Aucun contact disponible pour le moment.</div><?php endif; ?>
						</div>
					</div>

					<!-- Lieux -->
					<div>
						<h2 class="h5">Où aller ? <span class="badge bg-warning text-dark ms-1">exemples</span></h2>
						<div class="row g-3">
							<?php foreach ($lieux as $l): ?>
							<div class="col-md-6"><div class="card card-body h-100"><div class="fw-bold"><i class="bi bi-geo-alt-fill text-danger me-1"></i><?= e($l[0]) ?></div><div class="small text-secondary"><?= e($l[1]) ?></div><div class="small mt-1"><?= e($l[2]) ?></div></div></div>
							<?php endforeach; ?>
						</div>
					</div>

					<!-- Plan de sécurité -->
					<div class="card" id="plan">
						<div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
							<h2 class="h5 mb-0"><i class="bi bi-clipboard2-heart me-2 text-primary"></i>Mon plan de sécurité</h2>
							<div class="d-flex gap-2 no-print"><span class="badge bg-primary-soft text-primary align-self-center" id="planCount">0/<?= count($plan) ?></span><button class="btn btn-sm btn-primary-soft" type="button" onclick="window.print()"><i class="bi bi-printer me-1"></i>Imprimer</button><button class="btn btn-sm btn-light" type="button" id="planReset">Réinitialiser</button></div>
						</div>
						<div class="card-body">
							<div class="progress mb-3" style="height:6px"><div class="progress-bar" id="planBar" style="width:0"></div></div>
							<div class="vstack gap-2" id="planList">
								<?php foreach ($plan as $i => $p): ?>
								<div class="form-check"><input class="form-check-input" type="checkbox" id="plan<?= $i ?>" data-i="<?= $i ?>"><label class="form-check-label" for="plan<?= $i ?>"><?= e($p) ?></label></div>
								<?php endforeach; ?>
							</div>
							<p class="small text-secondary mt-3 mb-0">Cette liste reste sur votre appareil (aucune donnée envoyée). Imprimez-la ou gardez-la en lieu sûr.</p>
						</div>
					</div>

					<div class="d-flex flex-wrap gap-2">
						<a class="btn btn-primary" href="signalement.php"><i class="bi bi-megaphone me-1"></i>Signaler</a>
						<a class="btn btn-primary-soft" href="assistant.php"><i class="bi bi-robot me-1"></i>Assistant chat</a>
						<?php if ($pub): ?><a class="btn btn-light" href="sign-in.php">Se connecter</a><?php endif; ?>
					</div>
				</div>
			</div>
		</div>
	</main>
	<?php if (!$pub) include("partials/modals.php"); include("partials/footer-scripts.php"); ?>
	<?php if ($pub) x_public_quick_exit(); ?>
	<script>
	(function () {
		document.getElementById('quickExit').addEventListener('click', function () { window.location.replace('https://www.google.com'); });
		var K = 'xm_plan_v1', st = {};
		try { st = JSON.parse(localStorage.getItem(K) || '{}'); } catch (e) {}
		var boxes = document.querySelectorAll('#planList input');
		function refresh() {
			var n = 0; boxes.forEach(function (b) { if (b.checked) n++; });
			document.getElementById('planCount').textContent = n + '/' + boxes.length;
			document.getElementById('planBar').style.width = (100 * n / boxes.length) + '%';
		}
		boxes.forEach(function (b) {
			b.checked = !!st[b.dataset.i];
			b.addEventListener('change', function () { st[b.dataset.i] = b.checked; try { localStorage.setItem(K, JSON.stringify(st)); } catch (e) {} refresh(); });
		});
		document.getElementById('planReset').addEventListener('click', function () { st = {}; try { localStorage.removeItem(K); } catch (e) {} boxes.forEach(function (b) { b.checked = false; }); refresh(); });
		refresh();
	})();
	</script>
</body>
</html>
