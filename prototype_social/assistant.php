<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/x_complement.php';
require_login();
$pageTitle = 'Assistant chat';
$nav = 'assistant';
$chips = ['Comment porter plainte ?', 'Que faire après une agression ?', 'Contraception d’urgence', 'Chantage à l’outing, que faire ?', 'Dépistage VIH / IST', 'Douleurs de règles', 'Je me sens angoissée', 'C’est quoi le consentement ?'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<?php include("partials/title-meta.php"); ?>
	<?php include("partials/head-css.php"); ?>
	<style>.vstack > * { flex-grow:0 !important; }</style>
	<style>
		#chatBox { height:min(56vh,520px); overflow-y:auto; scroll-behavior:smooth; }
		.xm-assistant-msg .bubble { white-space:pre-line; }
		.xm-assistant-msg.me .bubble { background:var(--xm-blue); color:#fff; }
		.xm-assistant-msg.bot .bubble { background:var(--bs-tertiary-bg); }
	</style>
</head>
<body>
	<?php include("partials/header.php"); ?>
	<main id="contenu" tabindex="-1">
		<div class="container">
			<div class="row g-4">
				<?php include("partials/sidenav.php"); ?>
				<div class="col-md-8 col-lg-6 vstack gap-3">
					<?= flash_html() ?>
					<div class="card">
						<div class="card-header d-flex align-items-center gap-3">
							<span class="icon-lg rounded-circle text-white" style="background:#6F2C91"><i class="bi bi-robot"></i></span>
							<div class="flex-grow-1"><h1 class="h5 mb-0">Assistant Xomiton</h1><small class="text-secondary">Spécialisé VBG, santé sexuelle et droits · <span data-xm-online-state class="badge bg-success">En ligne</span></small></div>
							<div class="dropdown">
								<button class="btn btn-sm btn-light" type="button" data-bs-toggle="dropdown" aria-label="Options"><i class="bi bi-three-dots"></i></button>
								<ul class="dropdown-menu dropdown-menu-end">
									<li><button class="dropdown-item" type="button" id="clearBtn"><i class="bi bi-trash me-2"></i>Effacer la conversation</button></li>
									<li><a class="dropdown-item" href="annuaire.php"><i class="bi bi-person-lines-fill me-2"></i>Parler à un humain</a></li>
								</ul>
							</div>
						</div>
						<div class="card-body vstack gap-3" id="chatBox" aria-live="polite"></div>
						<div class="px-3 pb-2 d-flex gap-2 overflow-auto" id="chips">
							<?php foreach ($chips as $c): ?><button type="button" class="btn btn-sm btn-primary-soft text-nowrap chip"><?= e($c) ?></button><?php endforeach; ?>
						</div>
						<div class="card-footer">
							<form id="chatForm" class="d-flex gap-2" autocomplete="off">
								<input class="form-control" id="chatInput" placeholder="Posez votre question…" aria-label="Votre message" maxlength="500">
								<button class="btn btn-primary" type="submit" aria-label="Envoyer"><i class="bi bi-send-fill"></i></button>
							</form>
						</div>
					</div>
				</div>
				<div class="col-lg-3 vstack gap-3">
					<div class="card card-body border-danger">
						<div class="fw-bold text-danger mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i>En danger ?</div>
						<p class="small mb-2">Si vous êtes en danger immédiat, n’attendez pas : envoyez une alerte.</p>
						<a class="btn btn-danger btn-sm w-100" href="sos.php">SOS avec ma position</a>
					</div>
					<div class="card card-body">
						<div class="fw-bold mb-1"><i class="bi bi-person-heart me-1 text-primary"></i>Parler à un humain</div>
						<p class="small mb-2">Une pair animatrice, une psychologue ou une juriste peut vous écouter.</p>
						<a class="btn btn-primary-soft btn-sm w-100" href="annuaire.php">Ouvrir l’annuaire</a>
					</div>
					<div class="small text-secondary"><i class="bi bi-shield-check me-1"></i>Cet assistant fournit des informations générales et <strong>ne remplace pas un·e professionnel·le</strong> de santé, de droit ou de soutien psychologique. La conversation est gardée uniquement dans cet onglet.</div>
				</div>
			</div>
		</div>
	</main>
	<?php include("partials/modals.php"); include("partials/footer-scripts.php"); ?>
	<script>
	(function () {
		var K = 'xm_assistant_v1', box = document.getElementById('chatBox'), input = document.getElementById('chatInput');
		var hist = [];
		try { hist = JSON.parse(sessionStorage.getItem(K) || '[]'); } catch (e) {}
		var hello = { who: 'bot', t: 'Bonjour, je suis l’assistant Xomiton. Je peux vous informer sur les violences, la santé sexuelle, vos droits et les services disponibles. Je ne remplace pas un·e professionnel·le. En cas de danger, utilisez le bouton SOS.', cta: [['SOS', 'sos.php']] };
		function save() { try { sessionStorage.setItem(K, JSON.stringify(hist)); } catch (e) {} }
		function render(m) {
			var w = document.createElement('div');
			w.className = 'd-flex ' + (m.who === 'me' ? 'justify-content-end' : 'justify-content-start');
			var inner = document.createElement('div'); inner.className = 'xm-assistant-msg ' + m.who;
			var b = document.createElement('div'); b.className = 'bubble rounded-3 px-3 py-2'; b.textContent = m.t; inner.appendChild(b);
			if (m.cta && m.cta.length) {
				var c = document.createElement('div'); c.className = 'mt-1 d-flex flex-wrap gap-1';
				m.cta.forEach(function (x) { var a = document.createElement('a'); a.className = 'btn btn-sm ' + (x[1] === 'sos.php' ? 'btn-danger' : 'btn-primary-soft'); a.href = x[1]; a.textContent = x[0]; c.appendChild(a); });
				inner.appendChild(c);
			}
			w.appendChild(inner); box.appendChild(w); box.scrollTop = box.scrollHeight;
		}
		function boot() { box.innerHTML = ''; (hist.length ? hist : [hello]).forEach(render); }
		function typing(on) {
			var el = document.getElementById('typing');
			if (on && !el) { el = document.createElement('div'); el.id = 'typing'; el.className = 'xm-typing px-3 py-2 rounded-3 bg-body-tertiary align-self-start'; el.innerHTML = '<span></span><span></span><span></span>'; box.appendChild(el); box.scrollTop = box.scrollHeight; }
			if (!on && el) el.remove();
		}
		function ask(text) {
			text = text.trim(); if (!text) return;
			if (!hist.length) hist.push(hello);
			var u = { who: 'me', t: text }; hist.push(u); render(u); save(); input.value = '';
			typing(true);
			setTimeout(function () {
				typing(false);
				var r = XM.assistant.reply(text);
				var m = { who: 'bot', t: r.a, cta: r.cta ? [r.cta] : [] };
				hist.push(m); render(m); save();
			}, 700 + Math.random() * 600);
		}
		document.getElementById('chatForm').addEventListener('submit', function (e) { e.preventDefault(); ask(input.value); });
		document.querySelectorAll('#chips .chip').forEach(function (c) { c.addEventListener('click', function () { ask(c.textContent); }); });
		document.getElementById('clearBtn').addEventListener('click', function () { hist = []; try { sessionStorage.removeItem(K); } catch (e) {} boot(); XM.toast('Conversation effacée.', 'secondary'); });
		boot();
	})();
	</script>
</body>
</html>
