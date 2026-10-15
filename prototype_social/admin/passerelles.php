<?php
require __DIR__ . '/../inc/bootstrap.php'; require_admin(); require_once __DIR__ . '/../inc/x_admin_sys.php';
$pageTitle = 'Passerelles réseaux sociaux'; $adminPage = 'passerelles';
$gw = x_social_gateways(); $bds = x_ccc_bd();

if (!file_exists(store_path('gateways'))) store_save('gateways', array_map(fn($g, $i) => ['id' => $i + 1, 'name' => $g[0], 'connected' => in_array($g[0], ['Facebook', 'Telegram'], true), 'since' => in_array($g[0], ['Facebook', 'Telegram'], true) ? '2026-08-12' : '', 'account' => in_array($g[0], ['Facebook', 'Telegram'], true) ? '@xomiton.bj' : ''], $gw, array_keys($gw)));
if (!file_exists(store_path('gateway_templates'))) store_save('gateway_templates', array_map(fn($t, $i) => ['id' => $i + 1, 'title' => $t[0], 'text' => $t[1]], [
    ['Prévention – consentement', 'Le consentement est libre, enthousiaste et réversible. Besoin d’en parler ? Xomiton est là, sans jugement.'],
    ['Prévention – dépistage', 'Se faire dépister, c’est prendre soin de soi. Gratuit et confidentiel dans nos centres partenaires.'],
    ['Aide – SOS', 'Tu es en danger ou tu connais quelqu’un qui l’est ? Utilise le bouton SOS Xomiton : une personne de confiance te répond.'],
], array_keys(range(1, 3))));
$chat = setting('gateway_chat', ['rooms' => true, 'moderated' => true, 'anonymous' => true, 'max_members' => 50]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    xa_guard();
    $do = $_POST['do'] ?? '';
    if ($do === 'connect') {
        $g = store_find('gateways', xa_post('id')); if (!$g) xa_back('danger', 'Passerelle inconnue.');
        if ($g['connected']) { store_update('gateways', $g['id'], ['connected' => false, 'since' => '', 'account' => '']); x_log('Passerelles', 'Déconnexion : ' . $g['name']); xa_back('warning', $g['name'] . ' déconnecté (simulation).'); }
        $acc = xa_post('account', '@xomiton.bj');
        if (!preg_match('/^@?[\w.\-]{3,40}$/u', $acc)) xa_back('danger', 'Identifiant de compte invalide.');
        store_update('gateways', $g['id'], ['connected' => true, 'since' => date('Y-m-d'), 'account' => '@' . ltrim($acc, '@')]); x_log('Passerelles', 'Connexion : ' . $g['name']); xa_back('success', $g['name'] . ' connecté (simulation).');
    }
    if ($do === 'schedule') {
        $kind = xa_post('kind'); $nets = array_values(array_intersect((array)($_POST['nets'] ?? []), array_column($gw, 0))); $date = xa_post('date'); $msg = xa_post('message');
        $conn = array_column(array_filter(store_all('gateways'), fn($g) => $g['connected']), 'name');
        $nets = array_values(array_intersect($nets, $conn));
        if (!$nets) xa_back('danger', 'Choisissez au moins un réseau connecté.');
        if (!strtotime($date) || strtotime($date) < time() - 60) xa_back('danger', 'La date de publication doit être dans le futur.');
        $ref = '';
        if ($kind === 'bd') { foreach ($bds as $b) if ($b[0] === xa_post('bd')) $ref = $b[1]; if (!$ref) xa_back('danger', 'Choisissez une BD SSR.'); }
        elseif ($kind === 'tpl') { $t = store_find('gateway_templates', xa_post('tpl')); if (!$t) xa_back('danger', 'Choisissez un message clé.'); $ref = $t['title']; $msg = $msg ?: $t['text']; }
        elseif ($kind === 'custom') { if (mb_strlen($msg) < 10) xa_back('danger', 'Message personnalisé trop court (10 car. min.).'); $ref = 'Message personnalisé'; }
        else xa_back('danger', 'Type de contenu invalide.');
        if (mb_strlen($msg) > 500) xa_back('danger', 'Message limité à 500 caractères.');
        store_append('gateway_posts', ['kind' => $kind, 'ref' => $ref, 'message' => $msg, 'networks' => $nets, 'date' => $date, 'status' => 'planifié', 'created' => date('Y-m-d H:i')]);
        x_log('Passerelles', 'Publication planifiée : ' . $ref . ' → ' . implode(', ', $nets)); xa_back('success', 'Publication planifiée sur ' . implode(', ', $nets) . ' (simulation).');
    }
    if ($do === 'post_status') { $s = xa_post('s') === 'publié' ? 'publié' : 'annulé'; store_update('gateway_posts', xa_post('id'), ['status' => $s]); xa_back('success', 'Statut mis à jour.'); }
    if ($do === 'post_del') { store_delete('gateway_posts', xa_post('id')); xa_back('success', 'Publication retirée de la file.'); }
    if ($do === 'chat') {
        $m = (int)xa_post('max_members', 50); if ($m < 5 || $m > 500) xa_back('danger', 'Taille de salon : entre 5 et 500 membres.');
        setting_set('gateway_chat', ['rooms' => !empty($_POST['rooms']), 'moderated' => !empty($_POST['moderated']), 'anonymous' => !empty($_POST['anonymous']), 'max_members' => $m]);
        x_log('Passerelles', 'Salons de discussion modifiés'); xa_back('success', 'Paramètres des salons enregistrés.');
    }
    if ($do === 'tpl_save') {
        $t = xa_post('title'); $x = xa_post('text'); if (mb_strlen($t) < 3 || mb_strlen($x) < 20 || mb_strlen($x) > 500) xa_back('danger', 'Titre (3 car. min.) et texte (20 à 500 car.) requis.');
        if (xa_post('id') !== '') store_update('gateway_templates', xa_post('id'), ['title' => $t, 'text' => $x]); else store_append('gateway_templates', ['title' => $t, 'text' => $x]);
        xa_back('success', 'Modèle enregistré.');
    }
    if ($do === 'tpl_del') { store_delete('gateway_templates', xa_post('id')); xa_back('success', 'Modèle supprimé.'); }
    xa_back('danger', 'Action inconnue.');
}

$gws = store_all('gateways'); $gwBy = array_column($gws, null, 'name');
$queue = array_reverse(store_all('gateway_posts')); $tpls = store_all('gateway_templates');
$icons = array_column(array_map(fn($g) => [$g[0], $g], $gw), 1, 0);
$connected = array_filter($gws, fn($g) => $g['connected']);
include __DIR__ . '/../partials/admin-top.php';
?>
<div class="alert alert-info d-flex gap-2"><i class="bi bi-info-circle-fill"></i><div><strong>Mode prototype :</strong> les connexions et publications sont <strong>simulées</strong> ; aucune donnée n’est envoyée aux réseaux sociaux.</div></div>

<div class="row g-3 mb-4">
	<?php foreach ($gws as $g): [$gn, $gi, $gc] = $icons[$g['name']] ?? [$g['name'], 'bi-share', '#0433C3']; ?>
	<div class="col-6 col-md-4 col-xl-2"><div class="card h-100 text-center"><div class="card-body d-flex flex-column">
		<div class="fs-1 mb-1" style="color:<?= e($gc) ?>"><i class="bi <?= e($gi) ?>"></i></div>
		<div class="fw-semibold"><?= e($g['name']) ?></div>
		<div class="small mb-2 <?= $g['connected'] ? 'text-success' : 'text-secondary' ?>"><?= $g['connected'] ? '<i class="bi bi-plug-fill"></i> ' . e($g['account']) : 'Non connecté' ?></div>
		<span class="badge bg-warning text-dark mb-2 mx-auto">Simulé</span>
		<form method="post" class="mt-auto"><?= csrf_field() ?><input type="hidden" name="do" value="connect"><input type="hidden" name="id" value="<?= (int)$g['id'] ?>">
			<?php if (!$g['connected']): ?><input class="form-control form-control-sm mb-2" name="account" placeholder="@compte" aria-label="Compte <?= e($g['name']) ?>"><?php endif; ?>
			<button class="btn btn-sm w-100 <?= $g['connected'] ? 'btn-outline-danger' : 'btn-primary' ?>"><?= $g['connected'] ? 'Déconnecter' : 'Connecter' ?></button></form>
	</div></div></div>
	<?php endforeach; ?>
</div>

<div class="row g-4 mb-4">
	<div class="col-xl-6">
		<form method="post" class="card h-100" data-xm-validate><?= csrf_field() ?><input type="hidden" name="do" value="schedule">
			<div class="card-header"><h2 class="h6 mb-0"><i class="bi bi-calendar-plus me-2"></i>Planifier une publication</h2></div>
			<div class="card-body row g-3">
				<div class="col-12"><label class="form-label">Contenu</label>
					<div class="btn-group w-100" role="group"><input type="radio" class="btn-check" name="kind" id="kBd" value="bd" checked><label class="btn btn-outline-primary btn-sm" for="kBd">BD SSR</label>
						<input type="radio" class="btn-check" name="kind" id="kTpl" value="tpl"><label class="btn btn-outline-primary btn-sm" for="kTpl">Message clé</label>
						<input type="radio" class="btn-check" name="kind" id="kCu" value="custom"><label class="btn btn-outline-primary btn-sm" for="kCu">Personnalisé</label></div></div>
				<div class="col-12" data-k="bd"><select class="form-select" name="bd"><?php foreach ($bds as $b): ?><option value="<?= e($b[0]) ?>"><?= e($b[1]) ?></option><?php endforeach; ?></select></div>
				<div class="col-12 d-none" data-k="tpl"><select class="form-select" name="tpl"><?php foreach ($tpls as $t): ?><option value="<?= (int)$t['id'] ?>"><?= e($t['title']) ?></option><?php endforeach; ?></select></div>
				<div class="col-12"><label class="form-label">Message d’accompagnement</label><textarea class="form-control" name="message" rows="3" maxlength="500" placeholder="Texte publié avec le contenu (obligatoire pour « Personnalisé »)"></textarea></div>
				<div class="col-12"><label class="form-label">Réseaux ciblés <small class="text-secondary">(connectés uniquement)</small></label><div class="d-flex flex-wrap gap-3">
					<?php foreach ($gws as $g): ?><div class="form-check"><input class="form-check-input" type="checkbox" name="nets[]" value="<?= e($g['name']) ?>" id="n<?= (int)$g['id'] ?>" <?= $g['connected'] ? '' : 'disabled' ?>><label class="form-check-label <?= $g['connected'] ? '' : 'text-secondary' ?>" for="n<?= (int)$g['id'] ?>"><?= e($g['name']) ?></label></div><?php endforeach; ?></div></div>
				<div class="col-md-6"><label class="form-label">Date et heure</label><input type="datetime-local" class="form-control" name="date" value="<?= date('Y-m-d\TH:i', strtotime('+1 day 09:00')) ?>" required></div>
			</div>
			<div class="card-footer text-end"><button class="btn btn-primary btn-sm" <?= $connected ? '' : 'disabled' ?>>Planifier</button></div>
		</form>
	</div>
	<div class="col-xl-6">
		<div class="card h-100"><div class="card-header"><h2 class="h6 mb-0">File de publication (<?= count($queue) ?>)</h2></div>
			<ul class="list-group list-group-flush" style="max-height:420px;overflow:auto">
				<?php foreach ($queue as $q): $sc = ['planifié' => 'info', 'publié' => 'success', 'annulé' => 'secondary'][$q['status']] ?? 'secondary'; ?>
				<li class="list-group-item"><div class="d-flex flex-wrap gap-2 align-items-center"><span class="fw-semibold"><?= e($q['ref']) ?></span><span class="badge bg-<?= $sc ?>"><?= e($q['status']) ?></span><span class="small text-secondary ms-auto"><?= e(str_replace('T', ' ', $q['date'])) ?></span></div>
					<div class="small text-secondary"><?= e(implode(' · ', $q['networks'])) ?></div><?php if ($q['message']): ?><div class="small"><?= e(mb_substr($q['message'], 0, 110)) ?></div><?php endif; ?>
					<form method="post" class="mt-1 d-flex gap-2"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$q['id'] ?>">
						<?php if ($q['status'] === 'planifié'): ?><button name="do" value="post_status" onclick="this.form.s.value='publié'" class="btn btn-sm btn-outline-success">Publier maintenant</button><button name="do" value="post_status" onclick="this.form.s.value='annulé'" class="btn btn-sm btn-outline-secondary">Annuler</button><input type="hidden" name="s" value=""><?php endif; ?>
						<button name="do" value="post_del" class="btn btn-sm btn-link text-danger ms-auto">Retirer</button></form></li>
				<?php endforeach; if (!$queue): ?><li class="list-group-item text-center text-secondary py-4">Aucune publication planifiée.</li><?php endif; ?>
			</ul></div>
	</div>
</div>

<div class="row g-4">
	<div class="col-xl-5">
		<form method="post" class="card h-100"><?= csrf_field() ?><input type="hidden" name="do" value="chat" >
			<div class="card-header"><h2 class="h6 mb-0">Salons de discussion entre pairs</h2></div>
			<div class="card-body">
				<div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" role="switch" name="rooms" value="1" id="cRooms" <?= !empty($chat['rooms']) ? 'checked' : '' ?>><label class="form-check-label" for="cRooms">Autoriser les salons de discussion entre pairs</label></div>
				<div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" role="switch" name="moderated" value="1" id="cMod" <?= !empty($chat['moderated']) ? 'checked' : '' ?>><label class="form-check-label" for="cMod">Modération automatique (mots interdits)</label></div>
				<div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" name="anonymous" value="1" id="cAn" <?= !empty($chat['anonymous']) ? 'checked' : '' ?>><label class="form-check-label" for="cAn">Participation sous pseudonyme</label></div>
				<label class="form-label small">Taille maximale d’un salon</label><input class="form-control form-control-sm" name="max_members" value="<?= (int)$chat['max_members'] ?>" data-rule="positive_int">
			</div>
			<div class="card-footer text-end"><button class="btn btn-primary btn-sm">Enregistrer</button></div>
		</form>
	</div>
	<div class="col-xl-7">
		<div class="card h-100"><div class="card-header d-flex justify-content-between align-items-center"><h2 class="h6 mb-0">Modèles de messages clés</h2><button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#mTpl"><i class="bi bi-plus-lg me-1"></i>Modèle</button></div>
			<ul class="list-group list-group-flush"><?php foreach ($tpls as $t): ?><li class="list-group-item d-flex gap-2"><div class="flex-grow-1"><div class="fw-semibold"><?= e($t['title']) ?></div><div class="small text-secondary"><?= e($t['text']) ?></div></div>
				<button class="btn btn-sm btn-light align-self-start" data-bs-toggle="modal" data-bs-target="#mTpl" data-fill='<?= e(json_encode($t, JSON_UNESCAPED_UNICODE)) ?>' aria-label="Modifier"><i class="bi bi-pencil"></i></button>
				<form method="post" data-confirm="Supprimer ce modèle ?"><?= csrf_field() ?><input type="hidden" name="do" value="tpl_del"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><button class="btn btn-sm btn-light text-danger" aria-label="Supprimer"><i class="bi bi-trash"></i></button></form></li><?php endforeach; ?></ul></div>
	</div>
</div>

<div class="modal fade" id="mTpl" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><form class="modal-content" method="post" data-xm-validate data-fill-form>
	<?= csrf_field() ?><input type="hidden" name="do" value="tpl_save"><input type="hidden" name="id">
	<div class="modal-header"><h5 class="modal-title" data-title-new="Nouveau modèle" data-title-edit="Modifier le modèle">Nouveau modèle</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
	<div class="modal-body row g-3"><div class="col-12"><label class="form-label">Titre *</label><input class="form-control" name="title" required></div><div class="col-12"><label class="form-label">Texte * <small class="text-secondary">(500 car. max.)</small></label><textarea class="form-control" name="text" rows="4" maxlength="500" required data-rule="min_len_20"></textarea></div></div>
	<div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button class="btn btn-primary">Enregistrer</button></div>
</form></div></div>
<?php
$adminScripts = xa_common_js() . '<script>document.querySelectorAll("input[name=kind]").forEach(function(r){r.addEventListener("change",function(){document.querySelectorAll("[data-k]").forEach(function(d){d.classList.toggle("d-none",d.dataset.k!==r.value);});});});</script>';
include __DIR__ . '/../partials/admin-bottom.php';
