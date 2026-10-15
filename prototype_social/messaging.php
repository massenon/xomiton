<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();
require_once __DIR__ . '/inc/x_reseau.php';
$pageTitle = 'Messages';
$pageDesc = 'Messagerie Xomiton : échangez en toute confidentialité avec les pair·es, les professionnel·les et l’assistant.';
$nav = 'chat';
$me = x_user();
$mk = fn($from, $text, $min) => ['from' => $from, 'text' => $text, 'ago' => $min];
$convs = [];
$convs[] = ['id' => 'assistant', 'type' => 'assistant', 'name' => 'Assistant Xomiton', 'sub' => 'Assistant LLM sur les VBG et la sexualité', 'avatar' => img('icon.png'), 'online' => true, 'msgs' => [
	$mk('them', 'Bonjour, je suis l’assistant Xomiton. Vous pouvez me poser vos questions sur les violences, la santé sexuelle ou vos droits, de façon confidentielle. En cas de danger, utilisez le bouton SOS.', 120)]];
$people = [
	4 => [['them', 'Bonjour Awa, ton inscription à l’atelier de samedi est bien notée. 😊', 300], ['me', 'Merci Chantal ! Je viendrai avec une amie.', 280], ['them', 'Parfait, on se retrouve à 15h à Porto-Novo.', 270]],
	6 => [['them', 'Coucou ! Tu connais un centre de dépistage discret à Cotonou ?', 1500], ['me', 'Oui, la journée gratuite à Zogbo le 14 novembre, c’est confidentiel.', 1480]],
	10 => [['them', 'Merci pour ton témoignage d’hier, il m’a fait beaucoup de bien.', 4000]],
	12 => [['me', 'Tu as pu parler à la juriste ?', 9000], ['them', 'Oui, elle m’a bien orientée. Merci pour ton soutien. 💜', 8800]],
];
foreach ($people as $uid => $ms) {
	$u = x_user_by_id($uid); if (!$u) continue;
	$convs[] = ['id' => 'u' . $uid, 'type' => 'user', 'name' => $u['name'], 'sub' => x_role_label($u['role']) . ' · ' . $u['zone'], 'avatar' => x_avatar($u['avatar']), 'online' => $uid % 2 === 0, 'msgs' => array_map(fn($m) => $mk($m[0], $m[1], $m[2]), $ms)];
}
$gimg = ['hero-09.jpg', 'hero-10.jpg'];
$convs[] = ['id' => 'g1', 'type' => 'group', 'name' => 'Cercle des sœurs – Cotonou', 'sub' => '412 membres', 'avatar' => img($gimg[0]), 'online' => false, 'msgs' => [
	$mk('Estelle Dossou: ', 'Bonsoir toutes, quelqu’un pour la marche solidaire de dimanche ?', 600), $mk('Flora Kpadonou: ', 'Moi ! Je ramène des bouteilles d’eau.', 560)]];
$convs[] = ['id' => 'g2', 'type' => 'group', 'name' => 'Santé mentale & bien-être', 'sub' => '356 membres', 'avatar' => img($gimg[1]), 'online' => false, 'msgs' => [
	$mk('Chantal Hounkpatin: ', 'Rappel : exercice de respiration collectif jeudi à 18h en ligne.', 1900)]];
$first = $convs[0]['id'];
if (isset($_GET['with'])) foreach ($convs as $c) if ($c['id'] === 'u' . (int)$_GET['with']) $first = $c['id'];
if (isset($_GET['with']) && $first === $convs[0]['id'] && (int)$_GET['with'] > 0) {
	$u = x_user_by_id((int)$_GET['with']);
	if ($u && (int)$u['id'] !== (int)$me['id']) { $convs[] = ['id' => 'u' . $u['id'], 'type' => 'user', 'name' => $u['name'], 'sub' => x_role_label($u['role']) . ' · ' . $u['zone'], 'avatar' => x_avatar($u['avatar']), 'online' => false, 'msgs' => []]; $first = 'u' . $u['id']; }
}
$now = time();
foreach ($convs as &$c) foreach ($c['msgs'] as &$m) { $m['time'] = date('H:i', $now - $m['ago'] * 60); } unset($m, $c);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<?php include("partials/title-meta.php"); ?>
	<?php include("partials/head-css.php"); ?>
	<style>
		.xm-chat { height: calc(100vh - 170px); min-height: 540px; }
		.xm-chat-list .conv { cursor: pointer; border: 0; text-align: start; }
		.xm-chat-list .conv.active { background: rgba(4,51,195,.1); }
		.xm-chat-msgs { overflow-y: auto; }
		.xm-bubble { max-width: 78%; border-radius: 1rem; padding: .5rem .85rem; }
		.xm-bubble.them { background: var(--bs-tertiary-bg); border-bottom-left-radius: .25rem; }
		.xm-bubble.me { background: #0433C3; color: #fff; border-bottom-right-radius: .25rem; }
		.xm-bubble small { opacity: .7; font-size: .7rem; }
		@media (max-width: 767.98px) { .xm-chat { height: calc(100vh - 130px); } .xm-chat.show-pane .xm-chat-list { display: none !important; } .xm-chat:not(.show-pane) .xm-chat-pane { display: none !important; } }
	</style>
</head>
<body>
	<?php include("partials/header.php"); ?>
	<main id="contenu" tabindex="-1">
		<div class="container">
			<div class="row g-4">
				<div class="col-12">
					<?= flash_html() ?>
					<div class="card xm-chat d-flex flex-row overflow-hidden" id="xmChat">
						<!-- Liste -->
						<div class="xm-chat-list border-end d-flex flex-column col-12 col-md-4 col-lg-3 flex-shrink-0">
							<div class="p-3 border-bottom">
								<div class="d-flex justify-content-between align-items-center mb-2"><h1 class="h5 mb-0">Messages</h1><span class="small text-success" title="Messagerie chiffrée"><i class="bi bi-lock-fill me-1"></i>Chiffrée</span></div>
								<input class="form-control form-control-sm" type="search" id="convSearch" placeholder="Rechercher une conversation…" aria-label="Rechercher une conversation">
							</div>
							<div class="overflow-auto flex-grow-1" id="convList"></div>
							<div class="p-2 small text-secondary border-top"><i class="bi bi-shield-lock me-1"></i>Messagerie chiffrée de bout en bout (simulation).</div>
						</div>
						<!-- Fil -->
						<div class="xm-chat-pane d-flex flex-column flex-grow-1 overflow-hidden">
							<div class="d-flex align-items-center gap-2 p-3 border-bottom">
								<button class="btn btn-light btn-sm d-md-none" type="button" id="chatBack" aria-label="Retour aux conversations"><i class="bi bi-arrow-left"></i></button>
								<div class="avatar avatar-sm"><img class="avatar-img rounded-circle" id="chatAvatar" src="" alt="Avatar de la conversation" title="Avatar de la conversation"></div>
								<div class="flex-grow-1 overflow-hidden"><div class="fw-bold text-truncate" id="chatName"></div><div class="small text-secondary text-truncate" id="chatSub"></div></div>
								<button class="btn btn-sm btn-light text-danger" type="button" data-bs-toggle="modal" data-bs-target="#modalReportChat"><i class="bi bi-flag me-1"></i><span class="d-none d-sm-inline">Signaler</span></button>
							</div>
							<div class="xm-chat-msgs flex-grow-1 p-3 vstack gap-2" id="chatMsgs" aria-live="polite"></div>
							<form class="p-3 border-top d-flex align-items-center gap-2" id="chatForm" autocomplete="off">
								<div class="dropup"><button class="btn btn-light icon-md" type="button" data-bs-toggle="dropdown" aria-label="Émojis" aria-expanded="false"><i class="bi bi-emoji-smile"></i></button>
									<div class="dropdown-menu p-2" style="min-width:220px"><div class="d-flex flex-wrap gap-1" id="emojiBox"><?php foreach (['😊', '💜', '🌈', '🙏', '👍', '😢', '🤗', '💪', '🔒', '✨'] as $em): ?><button type="button" class="btn btn-light btn-sm fs-5" data-emoji="<?= $em ?>"><?= $em ?></button><?php endforeach; ?></div></div></div>
								<label class="btn btn-light icon-md mb-0" for="chatFile" title="Joindre un fichier" aria-label="Joindre un fichier"><i class="bi bi-paperclip"></i></label><input type="file" id="chatFile" class="d-none">
								<input class="form-control" type="text" id="chatInput" placeholder="Écrire un message…" aria-label="Message" maxlength="1000">
								<button class="btn btn-primary" type="submit" aria-label="Envoyer"><i class="bi bi-send-fill"></i></button>
							</form>
						</div>
					</div>
				</div>
			</div>
		</div>
	</main>

	<div class="modal fade" id="modalReportChat" tabindex="-1" aria-labelledby="modalReportChatLabel" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered"><div class="modal-content">
			<div class="modal-header"><h5 class="modal-title" id="modalReportChatLabel">Signaler cette conversation</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
			<div class="modal-body">
				<p class="small text-secondary">Votre signalement est transmis à l’équipe de modération, de façon confidentielle.</p>
				<label class="form-label" for="reportReason">Motif</label>
				<select class="form-select mb-3" id="reportReason"><option>Harcèlement ou insultes</option><option>Contenu discriminatoire (homophobie, transphobie)</option><option>Menaces ou chantage</option><option>Partage d’informations privées</option><option>Autre</option></select>
				<textarea class="form-control" id="reportNote" rows="3" placeholder="Précisions (facultatif)"></textarea>
				<div class="alert alert-warning small mt-3 mb-0">En danger ? Utilisez <a class="alert-link" href="sos.php">SOS</a> ou le <a class="alert-link" href="signalement.php">signalement anonyme</a>.</div>
			</div>
			<div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button type="button" class="btn btn-danger" id="reportSend">Envoyer le signalement</button></div>
		</div></div>
	</div>

	<?php include("partials/modals.php"); ?>
	<?php include("partials/footer-scripts.php"); ?>
	<script>
	(function () {
		var convs = <?= json_encode($convs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
		var cur = <?= json_encode($first) ?>;
		var box = document.getElementById('xmChat'), list = document.getElementById('convList'), msgs = document.getElementById('chatMsgs');
		var input = document.getElementById('chatInput');
		function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
		function now() { var d = new Date(); return ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2); }
		function get(id) { return convs.filter(function (c) { return c.id === id; })[0]; }
		function renderList() {
			var q = document.getElementById('convSearch').value.toLowerCase();
			list.innerHTML = convs.filter(function (c) { return c.name.toLowerCase().indexOf(q) > -1; }).map(function (c) {
				var last = c.msgs.length ? c.msgs[c.msgs.length - 1] : { text: 'Nouvelle conversation', time: '' };
				return '<button type="button" class="conv list-group-item list-group-item-action d-flex gap-2 align-items-center p-3 w-100 bg-transparent' + (c.id === cur ? ' active' : '') + '" data-id="' + c.id + '">' +
					'<div class="avatar avatar-sm' + (c.online ? ' avatar-story' : '') + '"><img class="avatar-img rounded-circle" src="' + c.avatar + '" alt="Avatar de ' + c.name.replace(/"/g, '') + '" title="' + c.name.replace(/"/g, '') + '"></div>' +
					'<div class="overflow-hidden flex-grow-1"><div class="d-flex justify-content-between"><strong class="text-truncate small">' + esc(c.name) + '</strong><span class="small text-secondary ms-1">' + esc(last.time) + '</span></div>' +
					'<div class="small text-secondary text-truncate">' + esc((last.from && last.from !== 'me' && last.from !== 'them' ? last.from : '') + last.text) + '</div></div></button>';
			}).join('');
		}
		function bubble(m) {
			var mine = m.from === 'me';
			var who = (!mine && m.from !== 'them') ? '<small class="d-block fw-bold">' + esc(m.from.replace(/: $/, '')) + '</small>' : '';
			return '<div class="d-flex ' + (mine ? 'justify-content-end' : '') + '"><div class="xm-bubble ' + (mine ? 'me' : 'them') + '">' + who + '<span style="white-space:pre-wrap">' + esc(m.text) + '</span><small class="d-block text-end">' + esc(m.time) + '</small></div></div>';
		}
		function renderPane() {
			var c = get(cur);
			document.getElementById('chatName').textContent = c.name; document.getElementById('chatSub').textContent = c.sub;
			document.getElementById('chatAvatar').src = c.avatar;
			msgs.innerHTML = c.msgs.length ? c.msgs.map(bubble).join('') : '<p class="text-center text-secondary my-auto">Commencez la conversation. Vos messages sont chiffrés.</p>';
			msgs.scrollTop = msgs.scrollHeight;
		}
		function push(c, m) { c.msgs.push(m); if (c.id === cur) { renderPane(); } renderList(); }
		function reply(c, text) {
			var typing = document.createElement('div'); typing.className = 'small text-secondary'; typing.textContent = c.name.split(' ')[0] + ' écrit…'; if (c.id === cur) msgs.appendChild(typing);
			setTimeout(function () {
				var r;
				if (c.type === 'assistant' && window.XM && XM.assistant && XM.assistant.reply) { try { r = XM.assistant.reply(text); } catch (e) {} }
				if (r && typeof r === 'object') r = r.text || r.answer || r.message;
				if (!r) r = c.type === 'assistant' ? 'Merci pour votre message. Je suis là pour vous écouter. Pouvez-vous préciser votre situation ? Si vous êtes en danger, utilisez le bouton SOS.' : ['Merci pour ton message 💜', 'Je te réponds dès que possible.', 'D’accord, on en parle bientôt !'][Math.floor(Math.random() * 3)];
				push(c, { from: c.type === 'group' ? c.name.split(' ')[0] + ': ' : 'them', text: String(r).replace(/<[^>]+>/g, ''), time: now() });
			}, 900 + Math.random() * 700);
		}
		list.addEventListener('click', function (ev) { var b = ev.target.closest('.conv'); if (!b) return; cur = b.dataset.id; renderList(); renderPane(); box.classList.add('show-pane'); });
		document.getElementById('chatBack').addEventListener('click', function () { box.classList.remove('show-pane'); });
		document.getElementById('convSearch').addEventListener('input', renderList);
		document.getElementById('emojiBox').addEventListener('click', function (ev) { var b = ev.target.closest('[data-emoji]'); if (b) { input.value += b.dataset.emoji; input.focus(); } });
		document.getElementById('chatFile').addEventListener('change', function () { if (this.files[0]) { push(get(cur), { from: 'me', text: '📎 ' + this.files[0].name, time: now() }); this.value = ''; } });
		document.getElementById('chatForm').addEventListener('submit', function (ev) {
			ev.preventDefault(); var t = input.value.trim(); if (!t) return; input.value = '';
			var c = get(cur); push(c, { from: 'me', text: t, time: now() }); reply(c, t);
		});
		document.getElementById('reportSend').addEventListener('click', function () {
			bootstrap.Modal.getInstance(document.getElementById('modalReportChat')).hide();
			if (window.XM && XM.toast) XM.toast('Signalement transmis à la modération. Merci.');
		});
		renderList(); renderPane();
		if (location.search.indexOf('with=') > -1) box.classList.add('show-pane');
	})();
	</script>
</body>
</html>
