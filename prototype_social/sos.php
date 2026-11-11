<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/x_complement.php';
$pub = !x_user();
$noSosFab = true;
$pageTitle = 'SOS – alerte GPS';
$nav = 'sos';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<?php include("partials/title-meta.php"); ?>
	<?php include("partials/head-css.php"); ?>
	<style>.vstack > * { flex-grow:0 !important; }</style>
	<style>
		.sos-btn { width:min(280px,70vw); aspect-ratio:1; border-radius:50%; border:0; background:radial-gradient(circle at 35% 30%,#ff4a4a,#E30E0E 60%,#a80808); color:#fff; font-weight:800; font-size:3rem; letter-spacing:.08em; box-shadow:0 0 0 12px rgba(227,14,14,.15),0 14px 40px rgba(227,14,14,.45); animation:sosPulse 2s infinite; }
		.sos-btn:disabled { filter:grayscale(.6); animation:none; }
		.sos-btn small { display:block; font-size:.85rem; font-weight:600; letter-spacing:0; }
		@keyframes sosPulse { 0%,100% { box-shadow:0 0 0 12px rgba(227,14,14,.15),0 14px 40px rgba(227,14,14,.45); } 50% { box-shadow:0 0 0 26px rgba(227,14,14,.05),0 14px 40px rgba(227,14,14,.45); } }
		.sos-count { font-size:6rem; font-weight:800; line-height:1; color:var(--xm-red); }
		.sos-map { height:200px; border-radius:.5rem; position:relative; overflow:hidden; background-color:rgba(4,51,195,.06); background-image:linear-gradient(rgba(4,51,195,.15) 1px,transparent 1px),linear-gradient(90deg,rgba(4,51,195,.15) 1px,transparent 1px); background-size:32px 32px; }
		.sos-map .pin { position:absolute; left:50%; top:50%; width:22px; height:22px; background:var(--xm-red); border:3px solid #fff; border-radius:50% 50% 50% 0; transform:translate(-50%,-100%) rotate(-45deg); box-shadow:0 4px 10px rgba(0,0,0,.35); }
		.sos-map .ring { position:absolute; left:50%; top:50%; width:90px; height:90px; margin:-45px 0 0 -45px; border-radius:50%; background:rgba(227,14,14,.12); border:1px dashed var(--xm-red); }
	</style>
</head>
<body>
	<?php if ($pub) x_public_header(); else include("partials/header.php"); ?>
	<main id="contenu" tabindex="-1">
		<div class="container">
			<div class="row g-4">
				<?php if (!$pub) include("partials/sidenav.php"); ?>
				<div class="<?= $pub ? 'col-lg-8 mx-auto' : 'col-lg-9' ?> vstack gap-4">
					<?= flash_html() ?>
					<div class="text-center">
						<h1 class="h3">SOS – Alerte d’urgence</h1>
						<p class="text-secondary mb-0">Une alerte avec votre position est envoyée à la personne de l’annuaire la plus proche (point focal, juriste, psychologue…).</p>
					</div>

					<!-- Étape 1 : bouton -->
					<div class="card card-body text-center py-5" id="stIdle">
						<div class="mb-4"><button type="button" class="sos-btn" id="sosBtn" aria-label="Envoyer une alerte SOS">SOS<small>Appuyer pour alerter</small></button></div>
						<div class="mx-auto" style="max-width:520px">
							<div class="form-check text-start mb-2"><input class="form-check-input" type="checkbox" id="consent" checked><label class="form-check-label small" for="consent"><strong>Consentement :</strong> j’accepte de partager ma position GPS et mon message avec les personnes ciblées de l’annuaire, uniquement pour recevoir de l’aide. Mes données ne sont pas utilisées à d’autres fins.</label></div>
							<div class="input-group input-group-sm mt-2"><span class="input-group-text">Téléphone (facultatif)</span><input class="form-control" id="sosPhone" placeholder="+229 01 …" inputmode="tel" aria-label="Téléphone pour vous rappeler"></div>
							<p class="small text-secondary mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>Après l’appui, vous avez 3 secondes pour annuler en cas d’erreur.</p>
						</div>
					</div>

					<!-- Étape 2 : compte à rebours -->
					<div class="card card-body text-center py-5" id="stCount" hidden>
						<p class="h5">Envoi de l’alerte dans…</p>
						<div class="sos-count my-3" id="cnt" aria-live="assertive">3</div>
						<div><button class="btn btn-dark btn-lg" type="button" id="cancelBtn"><i class="bi bi-x-circle me-1"></i>Annuler</button></div>
					</div>

					<!-- Étape 3 : localisation / envoi -->
					<div class="card card-body text-center py-5" id="stWait" hidden>
						<div class="spinner-border text-danger mx-auto mb-3" role="status"><span class="visually-hidden">Chargement…</span></div>
						<p class="h6 mb-0" id="waitMsg">Récupération de votre position…</p>
					</div>

					<!-- Fallback GPS refusé -->
					<div class="card card-body" id="stManual" hidden>
						<div class="alert alert-warning"><i class="bi bi-geo-alt-fill me-1"></i><strong>Position GPS indisponible.</strong> Indiquez votre zone pour que l’alerte soit routée vers la bonne personne.</div>
						<div class="row g-2">
							<div class="col-sm-6"><label class="form-label" for="mzone">Département</label><select class="form-select" id="mzone" data-zone-select="#mcommune"><option value="">Choisir…</option><?php foreach (x_zones() as $z => $cs): ?><option><?= e($z) ?></option><?php endforeach; ?></select></div>
							<div class="col-sm-6"><label class="form-label" for="mcommune">Commune</label><select class="form-select" id="mcommune"><option value="">—</option></select></div>
						</div>
						<div class="d-flex gap-2 mt-3"><button class="btn btn-danger" type="button" id="manualSend"><i class="bi bi-send me-1"></i>Envoyer sans position</button><button class="btn btn-light" type="button" id="manualCancel">Annuler</button></div>
					</div>

					<!-- Étape 4 : résultat -->
					<div class="card" id="stDone" hidden>
						<div class="card-body">
							<div class="text-center">
								<span class="icon-xl text-white rounded-circle d-inline-flex bg-success" id="doneIcon"><i class="bi bi-check-lg fs-2"></i></span>
								<h2 class="h4 mt-3" id="doneTitle">Alerte envoyée</h2>
								<div class="alert alert-primary d-inline-block text-start" id="doneRoute"></div>
							</div>
							<div class="row g-3 mt-1" id="posBlock">
								<div class="col-md-6">
									<div class="sos-map"><div class="ring"></div><div class="pin"></div></div>
								</div>
								<div class="col-md-6">
									<div class="fw-bold mb-1">Votre position</div>
									<div class="font-monospace" id="posTxt"></div>
									<div class="small text-secondary mb-2" id="posAcc"></div>
									<a class="btn btn-sm btn-primary-soft" id="posLink" target="_blank" rel="noopener" href="#"><i class="bi bi-map me-1"></i>Voir sur Google Maps</a>
								</div>
							</div>
							<hr>
							<h3 class="h6">En attendant l’aide</h3>
							<div class="vstack gap-2">
								<div class="xm-step"><span class="num">1</span><div>Restez, si possible, dans un lieu public ou éclairé, loin de la personne qui vous menace.</div></div>
								<div class="xm-step"><span class="num">2</span><div>Gardez votre téléphone chargé et allumé : la personne contactée va vous appeler.</div></div>
								<div class="xm-step"><span class="num">3</span><div>En danger de mort, appelez aussi le <a href="tel:117"><strong>117</strong></a>.</div></div>
								<div class="xm-step"><span class="num">4</span><div>Ne supprimez rien : messages, photos et traces peuvent servir de preuves.</div></div>
							</div>
							<div class="d-flex flex-wrap gap-2 mt-3">
								<a class="btn btn-danger-soft" href="urgences.php">Contacts d’urgence</a>
								<a class="btn btn-primary-soft" href="signalement.php">Compléter par un signalement</a>
								<button class="btn btn-light" type="button" id="resetBtn">Nouvelle alerte</button>
							</div>
						</div>
					</div>

					<div class="small text-secondary text-center">En cas de doute, appelez le <a href="tel:117">117</a> · <a href="urgences.php">Tous les contacts d’urgence</a></div>
				</div>
			</div>
		</div>
	</main>
	<?php if (!$pub) include("partials/modals.php"); include("partials/footer-scripts.php"); x_zones_script(); ?>
	<script>
	(function () {
		var $ = function (s) { return document.querySelector(s); };
		var steps = ['stIdle', 'stCount', 'stWait', 'stManual', 'stDone'];
		function go(id) { steps.forEach(function (s) { $('#' + s).hidden = (s !== id); }); window.scrollTo({ top: 0, behavior: 'smooth' }); }
		var timer = null, pos = null;

		$('#sosBtn').addEventListener('click', function () {
			if (!$('#consent').checked) { XM.toast('Cochez le consentement pour partager votre position, ou utilisez le 117.', 'warning'); return; }
			var n = 3; $('#cnt').textContent = n; go('stCount');
			timer = setInterval(function () {
				n--; $('#cnt').textContent = n;
				if (n <= 0) { clearInterval(timer); locate(); }
			}, 1000);
		});
		$('#cancelBtn').addEventListener('click', function () { clearInterval(timer); go('stIdle'); XM.toast('Alerte annulée.', 'secondary'); });

		function locate() {
			go('stWait'); $('#waitMsg').textContent = 'Récupération de votre position…';
			XM.getPosition().then(function (p) { pos = p; send({ lat: p.lat, lng: p.lng, acc: p.acc }); })
				.catch(function () { pos = null; go('stManual'); });
		}
		$('#manualCancel').addEventListener('click', function () { go('stIdle'); });
		$('#manualSend').addEventListener('click', function () { send({ zone: $('#mzone').value, commune: $('#mcommune').value }); });

		function send(extra) {
			go('stWait'); $('#waitMsg').textContent = 'Envoi de l’alerte…';
			var data = Object.assign({ type: 'sos', category: '', needs: ['securite'], anonymous: false, contact: $('#sosPhone').value.trim(),
				description: 'ALERTE SOS' + (extra.lat ? ' – position GPS jointe (± ' + extra.acc + ' m).' : ' – position GPS indisponible.') }, extra);
			delete data.acc;
			if (!navigator.onLine) { XM.queue.add('sos', data); done(null, true); return; }
			fetch(XM.base + 'api.php?action=alert', { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin', body: JSON.stringify(data) })
				.then(function (r) { return r.json(); })
				.then(function (j) { if (!j.ok) throw new Error(); done(j.routed, false); })
				.catch(function () { XM.queue.add('sos', data); done(null, true); });
		}
		function done(route, queued) {
			$('#doneIcon').className = 'icon-xl text-white rounded-circle d-inline-flex ' + (queued ? 'bg-warning' : 'bg-success');
			$('#doneTitle').textContent = queued ? 'Alerte enregistrée hors connexion' : 'Alerte envoyée';
			var r = $('#doneRoute'); r.textContent = '';
			if (route) { r.innerHTML = '<i class="bi bi-person-check me-1"></i>Alerte envoyée à <strong></strong>'; r.querySelector('strong').textContent = route.name + ', ' + route.role + (route.phone ? ' · ' + route.phone : ''); }
			else if (queued) r.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Pas de réseau : l’alerte partira automatiquement dès que la connexion reviendra. <strong>Appelez le 117 si possible.</strong>';
			else r.innerHTML = '<i class="bi bi-inbox me-1"></i>Alerte reçue par l’équipe Xomiton.';
			$('#posBlock').hidden = !pos;
			if (pos) {
				$('#posTxt').textContent = pos.lat + ', ' + pos.lng; $('#posAcc').textContent = 'Précision : ± ' + pos.acc + ' m';
				$('#posLink').href = 'https://maps.google.com/?q=' + pos.lat + ',' + pos.lng;
			}
			go('stDone');
		}
		$('#resetBtn').addEventListener('click', function () { pos = null; go('stIdle'); });
	})();
	</script>
</body>
</html>
