<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/x_complement.php';
$pub = !x_user();
if ($pub) $noSosFab = true;
$pageTitle = 'Signaler';
$nav = 'signalement';
$postId = (int)($_GET['post'] ?? 0);
$prefill = '';
if ($postId) $prefill = 'Signalement de la publication n°' . $postId . ' : ';
$startTab = ($_GET['tab'] ?? '') === 'besoin' ? 'besoin' : 'denonciation';
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
				<div class="<?= $pub ? 'col-lg-8 mx-auto' : 'col-lg-9' ?> vstack gap-4">
					<?= flash_html() ?>
					<div class="card overflow-hidden">
						<div class="row g-0">
							<div class="col-md-8 card-body p-4">
								<span class="badge bg-primary-soft text-primary mb-2"><i class="bi bi-incognito me-1"></i>Sans compte · sans trace</span>
								<h1 class="h3">Signaler</h1>
								<p class="mb-0">Dénoncez une violence ou exprimez un besoin. Votre message est transmis uniquement aux personnes ciblées de l’annuaire (point focal, juriste, psychologue…). Aucune adresse IP ni identité n’est demandée.</p>
							</div>
							<div class="col-md-4 d-none d-md-block"><div class="h-100" style="min-height:140px;background:url(<?= img('hero-10.jpg') ?>) center/cover"></div></div>
						</div>
					</div>

					<div id="formWrap">
						<div class="d-flex flex-wrap gap-2 mb-3" id="typeTabs" role="tablist">
							<button class="btn flex-fill btn-primary-soft" type="button" data-type="denonciation"><i class="bi bi-megaphone me-1"></i>Dénonciation</button>
							<button class="btn flex-fill btn-primary-soft" type="button" data-type="besoin"><i class="bi bi-hand-thumbs-up me-1"></i>Besoins</button>
							<a class="btn flex-fill btn-outline-danger" href="sos.php"><i class="bi bi-broadcast me-1"></i>SOS (danger immédiat)</a>
						</div>

						<form id="sigForm" class="card card-body vstack gap-3" data-xm-validate data-xm-offline="signalement" novalidate>
							<p class="small text-secondary mb-0" id="typeHelp"></p>
							<div class="row g-3">
								<div class="col-md-6">
									<label class="form-label" for="category">Type de violence <span class="text-danger">*</span></label>
									<select class="form-select" id="category" name="category" required>
										<option value="">Choisir…</option>
										<?php foreach (x_violence_categories() as $c): ?><option value="<?= e($c[0]) ?>"><?= e($c[1]) ?></option><?php endforeach; ?>
									</select>
								</div>
								<div class="col-md-6">
									<label class="form-label" for="date">Date des faits</label>
									<input class="form-control" type="date" id="date" name="date" data-rule="date_not_future" max="<?= date('Y-m-d') ?>">
								</div>
								<div class="col-md-6">
									<label class="form-label" for="zone">Département <span class="text-danger">*</span></label>
									<select class="form-select" id="zone" name="zone" data-zone-select="#commune" required>
										<option value="">Choisir…</option>
										<?php foreach (x_zones() as $z => $cs): ?><option><?= e($z) ?></option><?php endforeach; ?>
									</select>
								</div>
								<div class="col-md-6">
									<label class="form-label" for="commune">Commune</label>
									<select class="form-select" id="commune" name="commune"><option value="">—</option></select>
								</div>
							</div>
							<div>
								<label class="form-label" for="description">Description <span class="text-danger">*</span></label>
								<textarea class="form-control" id="description" name="description" rows="5" required data-rule="required|min_len_20" placeholder="Décrivez ce qui s’est passé ou ce dont vous avez besoin (20 caractères minimum). Évitez de donner votre nom si vous souhaitez rester anonyme."><?= e($prefill) ?></textarea>
							</div>

							<fieldset id="needsBox">
								<legend class="form-label fs-6 mb-2">Besoins spécifiques <span class="text-secondary small" id="needsHint">(facultatif)</span></legend>
								<div class="row g-2">
									<?php foreach (x_needs() as $k => $label): ?>
									<div class="col-sm-6"><div class="form-check"><input class="form-check-input need" type="checkbox" id="need_<?= e($k) ?>" value="<?= e($k) ?>"><label class="form-check-label" for="need_<?= e($k) ?>"><?= e($label) ?></label></div></div>
									<?php endforeach; ?>
								</div>
								<div class="xm-validation-msg" id="needsErr">Choisissez au moins un besoin.</div>
							</fieldset>

							<div>
								<label class="form-label" for="files">Pièces jointes (facultatif)</label>
								<input class="form-control" type="file" id="files" multiple accept="image/*,.pdf">
								<div class="form-text">Dans ce prototype, seuls les noms de fichiers sont transmis.</div>
							</div>

							<div class="border rounded-3 p-3">
								<div class="form-check form-switch mb-0">
									<input class="form-check-input" type="checkbox" role="switch" id="anon" checked>
									<label class="form-check-label fw-semibold" for="anon">Rester anonyme</label>
								</div>
								<div id="contactBox" class="mt-3" hidden>
									<label class="form-label" for="contact">Comment vous recontacter ? (téléphone ou e-mail)</label>
									<input class="form-control" id="contact" name="contact" data-rule="phone_or_email" placeholder="+229 01 … ou nom@exemple.com" autocomplete="off">
								</div>
							</div>

							<div class="d-flex flex-wrap align-items-center gap-2">
								<button class="btn btn-primary-soft" type="button" id="gpsBtn"><i class="bi bi-geo-alt me-1"></i>Joindre ma position</button>
								<span class="small text-secondary" id="gpsInfo">Facultatif — aide à vous localiser pour l’intervention.</span>
							</div>

							<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center border-top pt-3">
								<span class="small text-secondary"><span data-xm-online-state class="badge bg-success">En ligne</span> · hors connexion, votre message est mis en attente puis envoyé automatiquement.</span>
								<button class="btn btn-primary btn-lg" type="submit" id="sendBtn"><i class="bi bi-send me-1"></i>Envoyer le signalement</button>
							</div>
						</form>
					</div>

					<!-- Confirmation -->
					<div id="done" class="card card-body text-center py-5" hidden>
						<div class="mb-3"><span class="icon-xl bg-success text-white rounded-circle d-inline-flex" id="doneIcon"><i class="bi bi-check-lg fs-2"></i></span></div>
						<h2 class="h4" id="doneTitle">Signalement envoyé</h2>
						<p class="mb-1">Numéro de référence : <strong class="fs-5" id="doneRef"></strong></p>
						<p class="text-secondary small">Conservez ce numéro : il vous permettra d’obtenir des nouvelles auprès de votre contact.</p>
						<div class="alert alert-primary text-start mx-auto" style="max-width:480px" id="doneRoute"></div>
						<div class="d-flex flex-wrap justify-content-center gap-2">
							<a class="btn btn-danger" href="sos.php">Je suis en danger : SOS</a>
							<a class="btn btn-primary-soft" href="urgences.php">Contacts d’urgence</a>
							<button class="btn btn-light" type="button" id="againBtn">Faire un autre signalement</button>
						</div>
					</div>
				</div>
			</div>
		</div>
	</main>
	<?php if (!$pub) include("partials/modals.php"); include("partials/footer-scripts.php"); x_zones_script(); ?>
	<?php if ($pub) x_public_quick_exit(); ?>
	<script>
	(function () {
		var $ = function (s) { return document.querySelector(s); };
		var form = $('#sigForm'), type = <?= json_encode($startTab) ?>, pos = null;
		var HELP = {
			denonciation: 'Vous signalez une violence subie ou dont vous êtes témoin. Les informations sur les faits sont essentielles.',
			besoin: 'Vous exprimez un besoin d’aide (soutien, hébergement, accompagnement…). Cochez au moins un besoin.'
		};
		XM.rules.phone_or_email = { test: function (v) { return v === '' || XM.rules.email.test(v) || XM.rules.phone_bj.test(v); }, msg: 'Numéro de téléphone ou e-mail invalide.' };

		function setType(t) {
			type = t;
			document.querySelectorAll('#typeTabs [data-type]').forEach(function (b) { var on = b.dataset.type === t; b.classList.toggle('btn-primary', on); b.classList.toggle('btn-primary-soft', !on); b.setAttribute('aria-selected', on); });
			$('#typeHelp').textContent = HELP[t];
			$('#needsHint').textContent = t === 'besoin' ? '(obligatoire)' : '(facultatif)';
			$('#category').required = (t === 'denonciation');
			$('#needsErr').classList.remove('show');
		}
		document.querySelectorAll('#typeTabs [data-type]').forEach(function (b) { b.addEventListener('click', function () { setType(b.dataset.type); }); });
		setType(type);

		$('#anon').addEventListener('change', function () { $('#contactBox').hidden = this.checked; if (this.checked) $('#contact').value = ''; });

		$('#gpsBtn').addEventListener('click', function () {
			var info = $('#gpsInfo'); info.textContent = 'Localisation en cours…';
			XM.getPosition().then(function (p) {
				pos = p; info.innerHTML = '<i class="bi bi-check-circle-fill text-success me-1"></i>Position jointe : ' + p.lat + ', ' + p.lng + ' (± ' + p.acc + ' m)';
			}).catch(function () { pos = null; info.textContent = 'Position indisponible (accès refusé ou non supporté). Vous pouvez envoyer sans.'; });
		});

		function collect() {
			var files = Array.prototype.map.call($('#files').files, function (f) { return f.name; });
			var d = $('#description').value.trim();
			if (files.length) d += '\n[Pièces jointes : ' + files.join(', ') + ']';
			var needs = Array.prototype.map.call(document.querySelectorAll('.need:checked'), function (c) { return c.value; });
			var anon = $('#anon').checked;
			var o = { type: type, category: $('#category').value, date: $('#date').value, zone: $('#zone').value, commune: $('#commune').value,
				description: d, needs: needs, anonymous: anon, contact: anon ? '' : $('#contact').value.trim() };
			if (pos) { o.lat = pos.lat; o.lng = pos.lng; }
			return o;
		}
		function show(ref, title, route, queued) {
			$('#formWrap').hidden = true; $('#done').hidden = false;
			$('#doneRef').textContent = ref; $('#doneTitle').textContent = title;
			$('#doneIcon').className = 'icon-xl text-white rounded-circle d-inline-flex ' + (queued ? 'bg-warning' : 'bg-success');
			$('#doneRoute').innerHTML = '';
			var h = document.createElement('div');
			if (route) { h.innerHTML = '<div class="fw-bold mb-1"><i class="bi bi-person-check me-1"></i>Transmis à</div><div></div>'; h.lastChild.textContent = route.name + ' — ' + route.role + (route.phone ? ' · ' + route.phone : ''); }
			else if (queued) h.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Vous êtes hors connexion : votre signalement est enregistré sur cet appareil et sera envoyé automatiquement au retour du réseau.';
			else h.innerHTML = '<i class="bi bi-inbox me-1"></i>Votre signalement a été reçu par l’équipe Xomiton, qui le dirigera vers la bonne personne.';
			$('#doneRoute').appendChild(h);
			window.scrollTo({ top: 0, behavior: 'smooth' });
		}
		form.addEventListener('submit', function (e) {
			e.preventDefault(); e.stopImmediatePropagation();
			if (!XM.validateForm(form)) { XM.toast('Veuillez corriger les champs signalés.', 'danger'); return; }
			var data = collect();
			if (type === 'besoin' && !data.needs.length) { $('#needsErr').classList.add('show'); XM.toast('Choisissez au moins un besoin.', 'danger'); return; }
			if (!navigator.onLine) {
				XM.queue.add('signalement', data);
				show('EN-ATTENTE-' + Math.random().toString(36).slice(2, 6).toUpperCase(), 'Signalement enregistré hors connexion', null, true);
				return;
			}
			var btn = $('#sendBtn'); btn.disabled = true;
			fetch(XM.base + 'api.php?action=alert', { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin', body: JSON.stringify(data) })
				.then(function (r) { return r.json(); })
				.then(function (j) {
					if (!j.ok) throw new Error(j.error || 'Erreur');
					show('SIG-' + String(j.id).padStart(5, '0'), 'Signalement envoyé', j.routed, false);
				})
				.catch(function () { XM.queue.add('signalement', data); show('EN-ATTENTE-' + Math.random().toString(36).slice(2, 6).toUpperCase(), 'Signalement mis en attente', null, true); })
				.finally(function () { btn.disabled = false; });
		}, true);
		$('#againBtn').addEventListener('click', function () { form.reset(); pos = null; $('#gpsInfo').textContent = 'Facultatif — aide à vous localiser pour l’intervention.'; $('#contactBox').hidden = true; $('#commune').innerHTML = '<option value="">—</option>'; $('#done').hidden = true; $('#formWrap').hidden = false; setType(type); });
	})();
	</script>
</body>
</html>
