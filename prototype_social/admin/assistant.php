<?php
require __DIR__ . '/../inc/bootstrap.php'; require_admin(); require_once __DIR__ . '/../inc/x_admin_sys.php';
$pageTitle = 'Assistant LLM'; $adminPage = 'assistant';

$defaultPrompt = "Tu es l’assistant de Xomiton, un espace sûr pour les filles, les femmes et les personnes LBTQ+ au Bénin.\n\nTON : bienveillant, chaleureux, sans jugement, en français simple. Tutoie ou vouvoie selon l’interlocutrice. Phrases courtes.\n\nSUJETS AUTORISÉS : violences basées sur le genre (VBG), santé sexuelle et reproductive, contraception, IST/VIH, consentement, hygiène menstruelle, droits et lois du Bénin, santé mentale, orientation vers l’annuaire Xomiton.\n\nGARDE-FOUS :\n- Ne pose jamais de diagnostic médical ni psychologique et ne prescris aucun médicament.\n- Ne donne pas de conseil juridique définitif : explique la loi et oriente vers un·e juriste de l’annuaire.\n- Ne demande jamais d’informations permettant d’identifier la personne.\n- Refuse poliment les sujets hors périmètre.\n\nESCALADE : si la personne évoque un danger immédiat, des idées suicidaires ou une violence en cours, réponds avec empathie, rappelle le bouton SOS et propose de contacter un·e humain·e de l’annuaire.";
if (!file_exists(store_path('assistant_kb'))) {
    $seed = [['Que faire après une agression sexuelle ?', 'Vous n’êtes pas responsable. Si possible, ne vous lavez pas, consultez un centre de santé dans les 72 h (PEP, contraception d’urgence) et contactez un·e juriste ou psychologue de l’annuaire.', 'agression, viol, attouchement'],
        ['Comment porter plainte ?', 'La loi n°2011-26 réprime les violences faites aux femmes. Vous pouvez vous rendre au commissariat, à la gendarmerie ou auprès du procureur ; un·e juriste Xomiton peut vous accompagner.', 'plainte, police, justice, loi'],
        ['Qu’est-ce que le consentement ?', 'Un consentement libre, éclairé, enthousiaste, spécifique et réversible. Le silence, la peur ou l’alcool ne valent jamais consentement.', 'consentement'],
        ['Contraception d’urgence', 'À prendre le plus tôt possible, idéalement dans les 72 h. Disponible en centre de santé ou en pharmacie ; demandez conseil à un·e professionnel·le.', 'contraception, pilule, grossesse'],
        ['Chantage à l’outing', 'Ne répondez pas, ne payez pas, conservez les captures d’écran datées et utilisez le signalement anonyme. Un·e juriste peut agir pour faire retirer les contenus.', 'outing, chantage, menace en ligne'],
        ['Dépistage VIH / IST', 'Le dépistage est confidentiel et gratuit dans les centres partenaires. Après une prise de risque, la PEP doit démarrer dans les 72 h.', 'vih, ist, dépistage, sida']];
    store_save('assistant_kb', array_map(fn($r, $i) => ['id' => $i + 1, 'q' => $r[0], 'a' => $r[1], 'keywords' => $r[2], 'active' => true], $seed, array_keys($seed)));
}
$guard = [
    'no_diagnosis' => ['Interdire tout diagnostic médical ou psychologique', true],
    'no_pii' => ['Ne jamais demander d’informations identifiantes', true],
    'topic_filter' => ['Refuser les sujets hors périmètre (VBG, sexualité, droits, santé)', true],
    'sos_banner' => ['Afficher le rappel SOS pour tout message à risque', true],
    'log_anon' => ['Journaliser les conversations de façon anonymisée', true],
    'disclaimer' => ['Afficher l’avertissement « je ne remplace pas un·e professionnel·le »', true],
];
$curG = setting('assistant_guardrails', null); if (!is_array($curG)) $curG = array_map(fn($g) => $g[1], $guard);
$esc = setting('assistant_escalation', ['on' => true, 'keywords' => 'suicide, me tuer, il va me tuer, danger, urgence, frappée', 'target' => 'Point focal de la zone']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    xa_guard();
    $do = $_POST['do'] ?? '';
    if ($do === 'toggle') { $v = !empty($_POST['enabled']); setting_set('assistant_enabled', $v); x_log('Assistant', $v ? 'Activé' : 'Désactivé'); xa_back($v ? 'success' : 'warning', $v ? 'Assistant activé.' : 'Assistant désactivé : les membres voient un message de maintenance.'); }
    if ($do === 'prompt') {
        $p = trim($_POST['prompt'] ?? '');
        if (mb_strlen($p) < 100 || mb_strlen($p) > 6000) xa_back('danger', 'Le prompt doit contenir entre 100 et 6000 caractères.');
        if (!empty($_POST['reset'])) $p = $defaultPrompt;
        setting_set('assistant_prompt', $p); x_log('Assistant', 'Prompt système modifié'); xa_back('success', 'Prompt système enregistré.');
    }
    if ($do === 'guard') {
        $new = []; foreach ($guard as $k => $g) $new[$k] = !empty($_POST['g'][$k]);
        $kw = xa_post('keywords'); if (mb_strlen($kw) < 3) xa_back('danger', 'Indiquez au moins un mot-clé d’escalade.');
        setting_set('assistant_guardrails', $new); setting_set('assistant_escalation', ['on' => !empty($_POST['esc_on']), 'keywords' => $kw, 'target' => xa_post('target', 'Point focal de la zone')]);
        x_log('Assistant', 'Garde-fous modifiés'); xa_back('success', 'Garde-fous et escalade enregistrés.');
    }
    if ($do === 'kb_save') {
        $q = xa_post('q'); $a = xa_post('a');
        if (mb_strlen($q) < 5 || mb_strlen($a) < 20) xa_back('danger', 'Question (5 car. min.) et réponse (20 car. min.) requises.');
        $row = ['q' => $q, 'a' => $a, 'keywords' => xa_post('keywords'), 'active' => !empty($_POST['active'])];
        if (xa_post('id') !== '') store_update('assistant_kb', xa_post('id'), $row); else store_append('assistant_kb', $row);
        x_log('Assistant', 'Base de connaissances : ' . mb_substr($q, 0, 40)); xa_back('success', 'Entrée enregistrée.');
    }
    if ($do === 'kb_del') { store_delete('assistant_kb', xa_post('id')); xa_back('success', 'Entrée supprimée.'); }
    xa_back('danger', 'Action inconnue.');
}

$enabled = (bool)setting('assistant_enabled', true);
$prompt = setting('assistant_prompt', $defaultPrompt);
$kb = store_all('assistant_kb');
$flagged = [['Il y a 2 h', 'Danger immédiat évoqué', '« mon voisin me menace avec un couteau… »', 'Escaladée vers le point focal'], ['Hier', 'Idées suicidaires', '« je n’en peux plus, je veux disparaître »', 'Escaladée + contact psychologue'], ['Hier', 'Demande hors périmètre', '« donne-moi une ordonnance »', 'Refusée'], ['Il y a 3 j', 'Données identifiantes', '« voici mon adresse complète… »', 'Message masqué']];

$adminActions = '<a class="btn btn-outline-primary btn-sm" href="../assistant.php" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1"></i>Voir côté membre</a>';
include __DIR__ . '/../partials/admin-top.php';
?>
<div class="card mb-4 <?= $enabled ? 'border-success' : 'border-danger' ?>"><div class="card-body d-flex flex-wrap align-items-center gap-3">
	<form method="post" class="d-flex align-items-center gap-3 flex-grow-1"><?= csrf_field() ?><input type="hidden" name="do" value="toggle">
		<div class="form-check form-switch fs-3 mb-0"><input class="form-check-input" type="checkbox" role="switch" name="enabled" value="1" <?= $enabled ? 'checked' : '' ?> onchange="this.form.submit()" aria-label="Activer l’assistant"></div>
		<div><div class="fw-bold"><?= $enabled ? 'Assistant actif' : 'Assistant désactivé' ?></div><div class="small text-secondary">Assistant spécialisé VBG, santé sexuelle et droits (démonstration : réponses guidées par mots-clés).</div></div></form>
	<span class="badge bg-<?= $enabled ? 'success' : 'danger' ?> fs-6"><?= $enabled ? 'ON' : 'OFF' ?></span>
</div></div>

<div class="row g-4 mb-4">
	<div class="col-xl-7">
		<form method="post" class="card h-100" data-xm-validate><?= csrf_field() ?><input type="hidden" name="do" value="prompt">
			<div class="card-header d-flex justify-content-between align-items-center"><h2 class="h6 mb-0">Prompt système</h2><span class="small text-secondary" id="pCount"></span></div>
			<div class="card-body"><textarea class="form-control font-monospace small" name="prompt" id="promptTa" rows="16" required><?= e($prompt) ?></textarea>
				<div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="reset" value="1" id="rst"><label class="form-check-label small" for="rst">Rétablir le prompt par défaut</label></div></div>
			<div class="card-footer text-end"><button class="btn btn-primary btn-sm">Enregistrer</button></div>
		</form>
	</div>
	<div class="col-xl-5">
		<form method="post" class="card h-100"><?= csrf_field() ?><input type="hidden" name="do" value="guard">
			<div class="card-header"><h2 class="h6 mb-0">Garde-fous & escalade vers un humain</h2></div>
			<div class="card-body">
				<?php foreach ($guard as $k => $g): ?><div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" role="switch" name="g[<?= $k ?>]" value="1" id="g<?= $k ?>" <?= !empty($curG[$k]) ? 'checked' : '' ?>><label class="form-check-label" for="g<?= $k ?>"><?= e($g[0]) ?></label></div><?php endforeach; ?>
				<hr>
				<div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" role="switch" name="esc_on" value="1" id="escOn" <?= !empty($esc['on']) ? 'checked' : '' ?>><label class="form-check-label fw-semibold" for="escOn">Escalade vers un·e humain·e</label></div>
				<label class="form-label small">Mots-clés déclencheurs</label><textarea class="form-control form-control-sm mb-2" name="keywords" rows="2"><?= e($esc['keywords']) ?></textarea>
				<label class="form-label small">Destinataire</label><select class="form-select form-select-sm" name="target"><?php foreach (['Point focal de la zone', 'Psychologue de la zone', 'Juriste de la zone', 'Tous les points focaux'] as $t): ?><option <?= $esc['target'] === $t ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?></select>
			</div>
			<div class="card-footer text-end"><button class="btn btn-primary btn-sm">Enregistrer</button></div>
		</form>
	</div>
</div>

<div class="row g-4 mb-4">
	<div class="col-xl-7">
		<div class="card h-100">
			<div class="card-header d-flex justify-content-between align-items-center"><h2 class="h6 mb-0">Base de connaissances (<?= count($kb) ?>)</h2><button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#mKb"><i class="bi bi-plus-lg me-1"></i>Entrée</button></div>
			<ul class="list-group list-group-flush">
				<?php foreach ($kb as $r): ?><li class="list-group-item d-flex gap-2 align-items-start">
					<div class="flex-grow-1"><div class="fw-semibold"><?= e($r['q']) ?> <?= empty($r['active']) ? '<span class="badge bg-secondary">inactive</span>' : '' ?></div><div class="small text-secondary"><?= e($r['a']) ?></div><div class="small mt-1"><?php foreach (array_filter(array_map('trim', explode(',', $r['keywords']))) as $kw): ?><span class="badge bg-primary-subtle text-primary-emphasis me-1"><?= e($kw) ?></span><?php endforeach; ?></div></div>
					<button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#mKb" data-fill='<?= e(json_encode($r, JSON_UNESCAPED_UNICODE)) ?>' aria-label="Modifier"><i class="bi bi-pencil"></i></button>
					<form method="post" data-confirm="Supprimer cette entrée ?"><?= csrf_field() ?><input type="hidden" name="do" value="kb_del"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-light text-danger" aria-label="Supprimer"><i class="bi bi-trash"></i></button></form>
				</li><?php endforeach; ?>
			</ul>
		</div>
	</div>
	<div class="col-xl-5">
		<div class="card h-100">
			<div class="card-header"><h2 class="h6 mb-0"><i class="bi bi-terminal me-2"></i>Console de test</h2></div>
			<div class="card-body d-flex flex-column">
				<div id="tChat" class="flex-grow-1 border rounded p-2 mb-2 bg-body-tertiary" style="min-height:220px;max-height:300px;overflow:auto" aria-live="polite"><div class="small text-secondary">Posez une question de test (ex. « comment porter plainte ? »).</div></div>
				<form id="tForm" class="input-group"><input class="form-control" id="tIn" placeholder="Votre message de test…" autocomplete="off"><button class="btn btn-primary" aria-label="Envoyer"><i class="bi bi-send"></i></button></form>
			</div>
		</div>
	</div>
</div>

<div class="row g-4">
	<div class="col-xl-5"><div class="card h-100"><div class="card-header d-flex justify-content-between"><h2 class="h6 mb-0">Statistiques de conversation</h2><span class="badge bg-secondary">Exemple</span></div>
		<div class="card-body"><div class="row text-center mb-3"><div class="col-4"><div class="kpi-value">1 248</div><div class="small text-secondary">conversations</div></div><div class="col-4"><div class="kpi-value">92 %</div><div class="small text-secondary">résolues</div></div><div class="col-4"><div class="kpi-value">3,1 %</div><div class="small text-secondary">escaladées</div></div></div><canvas id="aChart" height="190" aria-label="Sujets les plus abordés (exemple)"></canvas></div></div></div>
	<div class="col-xl-7"><div class="card h-100"><div class="card-header d-flex justify-content-between"><h2 class="h6 mb-0">Conversations signalées</h2><span class="badge bg-secondary">Exemple</span></div>
		<div class="xm-table-wrap"><table class="table align-middle mb-0"><thead><tr><th>Quand</th><th>Motif</th><th>Extrait</th><th>Traitement</th></tr></thead><tbody>
		<?php foreach ($flagged as $f): ?><tr><td class="text-nowrap small"><?= e($f[0]) ?></td><td><span class="badge bg-danger-subtle text-danger-emphasis"><?= e($f[1]) ?></span></td><td class="small fst-italic"><?= e($f[2]) ?></td><td class="small"><?= e($f[3]) ?></td></tr><?php endforeach; ?></tbody></table></div></div></div>
</div>

<div class="modal fade" id="mKb" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><form class="modal-content" method="post" data-xm-validate data-fill-form>
	<?= csrf_field() ?><input type="hidden" name="do" value="kb_save"><input type="hidden" name="id">
	<div class="modal-header"><h5 class="modal-title" data-title-new="Nouvelle entrée" data-title-edit="Modifier l’entrée">Nouvelle entrée</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
	<div class="modal-body row g-3">
		<div class="col-12"><label class="form-label">Question *</label><input class="form-control" name="q" required></div>
		<div class="col-12"><label class="form-label">Réponse *</label><textarea class="form-control" name="a" rows="5" required data-rule="min_len_20"></textarea></div>
		<div class="col-12"><label class="form-label">Mots-clés (séparés par des virgules)</label><input class="form-control" name="keywords"></div>
		<div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="active" value="1" id="kbAct" checked><label class="form-check-label" for="kbAct">Entrée active</label></div></div>
	</div>
	<div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button class="btn btn-primary">Enregistrer</button></div>
</form></div></div>
<?php
$kbJs = json_encode(array_values(array_filter($kb, fn($r) => !empty($r['active']))), JSON_UNESCAPED_UNICODE);
$escJs = json_encode(array_values(array_filter(array_map('trim', explode(',', $esc['keywords'])))), JSON_UNESCAPED_UNICODE);
ob_start(); ?>
<script>
(function () {
  var KB = <?= $kbJs ?>, ESC = <?= !empty($esc['on']) ? $escJs : '[]' ?>;
  var ta = document.getElementById('promptTa'), pc = document.getElementById('pCount');
  function cnt() { pc.textContent = ta.value.length + ' caractères'; } ta.addEventListener('input', cnt); cnt();
  var chat = document.getElementById('tChat');
  function bubble(t, me) { var d = document.createElement('div'); d.className = 'xm-assistant-msg small p-2 rounded mb-2 ' + (me ? 'bg-primary text-white ms-auto' : 'bg-body border'); d.textContent = t; chat.appendChild(d); chat.scrollTop = chat.scrollHeight; return d; }
  document.getElementById('tForm').addEventListener('submit', function (e) {
    e.preventDefault(); var i = document.getElementById('tIn'), t = i.value.trim(); if (!t) return; i.value = ''; bubble(t, true);
    var low = t.toLowerCase(), ans = null, esc = ESC.some(function (k) { return low.indexOf(k.toLowerCase()) > -1; });
    KB.forEach(function (r) { if (!ans && r.keywords.split(',').some(function (k) { k = k.trim().toLowerCase(); return k && low.indexOf(k) > -1; })) ans = r.a; });
    if (!ans && window.XM && XM.assistant) ans = XM.assistant.reply(t).a;
    var b = bubble(ans || 'Je n’ai pas de réponse à cela.', false);
    if (esc) { var w = bubble('Escalade déclenchée : un·e humain·e de l’annuaire serait alerté·e (simulation).', false); w.className += ' border-danger text-danger'; }
  });
  new Chart(document.getElementById('aChart'), { type: 'doughnut', data: { labels: ['VBG', 'Contraception', 'Droits & plainte', 'Santé mentale', 'IST / VIH'], datasets: [{ data: [34, 22, 18, 16, 10], backgroundColor: XM_COLORS }] } });
})();
</script>
<?php $adminScripts = xa_common_js() . ob_get_clean();
include __DIR__ . '/../partials/admin-bottom.php';
