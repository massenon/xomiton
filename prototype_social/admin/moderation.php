<?php
require __DIR__ . '/../inc/bootstrap.php'; require_admin(); require_once __DIR__ . '/../inc/x_admin_sys.php';
$pageTitle = 'Modération'; $adminPage = 'moderation';

/* Seeds en code si les fichiers n'existent pas encore */
if (!file_exists(store_path('moderation_reports'))) {
    $uids = array_values(array_filter(array_column(store_all('users'), 'id'), fn($i) => $i > 2)); $u = fn($k) => $uids[$k % max(1, count($uids))] ?? 3;
    $now = time(); $seed = [
        ['kind' => 'publication', 'target' => 'Message moqueur sur l’orientation d’une membre dans le fil.', 'user_id' => $u(1), 'reason' => 'Homophobie', 'reporter' => 'Membre anonyme', 'ago' => 3],
        ['kind' => 'commentaire', 'target' => 'Commentaire insultant sous le témoignage « Retrouver confiance ».', 'user_id' => $u(2), 'reason' => 'Insultes', 'reporter' => 'Pair animateur·rice', 'ago' => 7],
        ['kind' => 'utilisateur', 'target' => 'Profil envoyant des messages privés répétés et non sollicités.', 'user_id' => $u(3), 'reason' => 'Harcèlement', 'reporter' => '3 membres', 'ago' => 20],
        ['kind' => 'publication', 'target' => 'Publication partageant le numéro de téléphone d’une autre membre.', 'user_id' => $u(4), 'reason' => 'Données personnelles', 'reporter' => 'Membre', 'ago' => 26],
        ['kind' => 'commentaire', 'target' => 'Lien suspect vers un site de rencontres payant.', 'user_id' => $u(5), 'reason' => 'Spam', 'reporter' => 'Système (mots interdits)', 'ago' => 30],
        ['kind' => 'publication', 'target' => 'Conseil médical dangereux sur l’arrêt d’un traitement ARV.', 'user_id' => $u(6), 'reason' => 'Désinformation santé', 'reporter' => 'Pair animateur·rice', 'ago' => 48],
        ['kind' => 'utilisateur', 'target' => 'Compte se faisant passer pour une juriste de l’annuaire.', 'user_id' => $u(7), 'reason' => 'Usurpation', 'reporter' => 'Admin', 'ago' => 55],
        ['kind' => 'commentaire', 'target' => 'Menace voilée envers une participante d’un groupe privé.', 'user_id' => $u(0), 'reason' => 'Menaces', 'reporter' => 'Membre', 'ago' => 70],
    ];
    $rows = []; foreach ($seed as $i => $s) { $rows[] = ['id' => $i + 1, 'kind' => $s['kind'], 'target' => $s['target'], 'user_id' => $s['user_id'], 'reason' => $s['reason'], 'reporter' => $s['reporter'], 'status' => 'en_attente', 'action' => '', 'created' => $now - $s['ago'] * 3600]; }
    store_save('moderation_reports', $rows);
}
if (!file_exists(store_path('bad_words'))) { store_save('bad_words', array_map(fn($w, $i) => ['id' => $i + 1, 'word' => $w, 'severity' => $i < 3 ? 'bloquer' : 'signaler'], ['sale pédé', 'gouine', 'va mourir', 'tarée', 'casse-toi', 'argent facile', 'clique ici'], array_keys(range(1, 7)))); }

$charte = [['bi-heart', 'Bienveillance', 'Écoutez sans juger, parlez avec respect, même en désaccord.'], ['bi-shield-lock', 'Confidentialité', 'Ne partagez jamais l’identité, le téléphone ou les photos d’une autre personne.'],
    ['bi-rainbow', 'Tolérance zéro', 'Aucune homophobie, transphobie, biphobie ni discrimination.'], ['bi-slash-circle', 'Pas de violence', 'Menaces, insultes et harcèlement entraînent un avertissement puis une suspension.'],
    ['bi-patch-check', 'Information fiable', 'Pas de conseils médicaux ou juridiques non vérifiés ; orientez vers l’annuaire.'], ['bi-ban', 'Pas de spam', 'Ni publicité, ni liens commerciaux, ni sollicitations non désirées.']];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    xa_guard();
    $do = $_POST['do'] ?? '';
    if ($do === 'act') {
        $r = store_find('moderation_reports', xa_post('id')); $a = xa_post('action');
        if (!$r || !in_array($a, ['ignorer', 'masquer', 'avertir', 'suspendre'], true)) xa_back('danger', 'Action invalide.');
        $u = store_find('users', $r['user_id']); $msg = 'Signalement traité (' . $a . ').';
        if ($a === 'suspendre') {
            if (!$u) xa_back('danger', 'Utilisateur introuvable.');
            if (in_array($u['role'], ['admin', 'superadmin'], true)) xa_back('danger', 'Un compte d’administration ne peut pas être suspendu depuis la modération.');
            store_update('users', $u['id'], ['status' => 'suspendu']); $msg = 'Utilisateur ' . $u['name'] . ' suspendu.';
        }
        if ($a === 'avertir' && $u) store_update('users', $u['id'], ['warnings' => (int)($u['warnings'] ?? 0) + 1]);
        store_update('moderation_reports', $r['id'], ['status' => 'traité', 'action' => $a, 'handled_by' => x_user()['name'], 'handled_at' => date('Y-m-d H:i')]);
        x_log('Modération', ucfirst($a) . ' – signalement #' . $r['id'] . ' (' . $r['reason'] . ')'); xa_back('success', $msg);
    }
    if ($do === 'reopen') { store_update('moderation_reports', xa_post('id'), ['status' => 'en_attente', 'action' => '']); xa_back('success', 'Signalement rouvert.'); }
    if ($do === 'word_add') {
        $w = mb_strtolower(xa_post('word')); $sev = xa_post('severity') === 'bloquer' ? 'bloquer' : 'signaler';
        if (mb_strlen($w) < 3 || mb_strlen($w) > 40) xa_back('danger', 'Le mot ou l’expression doit faire entre 3 et 40 caractères.');
        foreach (store_all('bad_words') as $b) if ($b['word'] === $w) xa_back('warning', 'Ce mot est déjà dans la liste.');
        store_append('bad_words', ['word' => $w, 'severity' => $sev]); x_log('Modération', 'Mot interdit ajouté'); xa_back('success', 'Mot ajouté à la liste.');
    }
    if ($do === 'word_del') { store_delete('bad_words', xa_post('id')); xa_back('success', 'Mot retiré.'); }
    xa_back('danger', 'Action inconnue.');
}

$reports = array_reverse(store_all('moderation_reports')); $words = store_all('bad_words');
$f = $_GET['f'] ?? 'en_attente';
$list = array_values(array_filter($reports, fn($r) => $f === 'tous' || $r['status'] === $f));
$open = count(array_filter($reports, fn($r) => $r['status'] === 'en_attente'));
$users = array_column(store_all('users'), null, 'id');
$kindIcon = ['publication' => 'bi-card-text', 'commentaire' => 'bi-chat-left-text', 'utilisateur' => 'bi-person-exclamation'];
$actCls = ['ignorer' => 'secondary', 'masquer' => 'info', 'avertir' => 'warning text-dark', 'suspendre' => 'danger'];
include __DIR__ . '/../partials/admin-top.php';
?>
<div class="row g-3 mb-4">
	<?= xa_kpi('À traiter', $open, 'bi-flag', 'danger') ?>
	<?= xa_kpi('Traités', count($reports) - $open, 'bi-check2-all', 'success') ?>
	<?= xa_kpi('Suspensions', count(array_filter($reports, fn($r) => $r['action'] === 'suspendre')), 'bi-person-slash', 'warning') ?>
	<?= xa_kpi('Mots surveillés', count($words), 'bi-funnel', 'violet') ?>
</div>
<div class="row g-4">
	<div class="col-xl-8">
		<div class="card">
			<div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2"><h2 class="h6 mb-0">File de modération</h2>
				<div class="btn-group btn-group-sm"><?php foreach (['en_attente' => 'À traiter', 'traité' => 'Traités', 'tous' => 'Tous'] as $k => $l): ?><a class="btn btn-outline-primary <?= $f === $k ? 'active' : '' ?>" href="?f=<?= urlencode($k) ?>"><?= $l ?></a><?php endforeach; ?></div></div>
			<ul class="list-group list-group-flush">
			<?php foreach ($list as $r): $u = $users[$r['user_id']] ?? null; ?>
				<li class="list-group-item">
					<div class="d-flex gap-3">
						<div class="fs-4 text-primary"><i class="bi <?= $kindIcon[$r['kind']] ?? 'bi-flag' ?>"></i></div>
						<div class="flex-grow-1">
							<div class="d-flex flex-wrap gap-2 align-items-center mb-1"><span class="badge bg-danger-subtle text-danger-emphasis"><?= e($r['reason']) ?></span><span class="badge bg-secondary-subtle text-secondary-emphasis"><?= e($r['kind']) ?></span>
								<?= $r['status'] === 'traité' ? '<span class="badge bg-' . $actCls[$r['action']] . '">' . e($r['action']) . '</span>' : '<span class="badge bg-warning text-dark">à traiter</span>' ?><span class="small text-secondary ms-auto">il y a <?= x_ago($r['created']) ?></span></div>
							<div><?= e($r['target']) ?></div>
							<div class="small text-secondary mt-1">Auteur·e : <strong><?= e($u['name'] ?? 'Inconnu·e') ?></strong><?= $u && $u['status'] === 'suspendu' ? ' <span class="badge bg-danger">suspendu·e</span>' : '' ?><?= !empty($u['warnings']) ? ' · ' . (int)$u['warnings'] . ' avertissement(s)' : '' ?> · signalé par <?= e($r['reporter']) ?></div>
							<?php if ($r['status'] === 'en_attente'): ?>
							<form method="post" class="d-flex flex-wrap gap-2 mt-2"><?= csrf_field() ?><input type="hidden" name="do" value="act"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
								<button name="action" value="ignorer" class="btn btn-sm btn-outline-secondary">Ignorer</button>
								<button name="action" value="masquer" class="btn btn-sm btn-outline-info">Masquer</button>
								<button name="action" value="avertir" class="btn btn-sm btn-outline-warning">Avertir</button>
								<button name="action" value="suspendre" class="btn btn-sm btn-outline-danger" onclick="return confirm('Suspendre l’utilisateur concerné ?')">Suspendre l’utilisateur</button></form>
							<?php else: ?>
							<form method="post" class="mt-2"><?= csrf_field() ?><input type="hidden" name="do" value="reopen"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-link p-0">Rouvrir</button> <span class="small text-secondary">· traité par <?= e($r['handled_by'] ?? '') ?> le <?= e($r['handled_at'] ?? '') ?></span></form>
							<?php endif; ?>
						</div>
					</div>
				</li>
			<?php endforeach; if (!$list): ?><li class="list-group-item text-center text-secondary py-4"><i class="bi bi-emoji-smile me-1"></i>Rien à modérer ici.</li><?php endif; ?>
			</ul>
		</div>
	</div>
	<div class="col-xl-4">
		<div class="card mb-4">
			<div class="card-header"><h2 class="h6 mb-0">Mots interdits</h2></div>
			<form method="post" class="card-body border-bottom row g-2" data-xm-validate><?= csrf_field() ?><input type="hidden" name="do" value="word_add">
				<div class="col-12"><input class="form-control form-control-sm" name="word" placeholder="Mot ou expression" required></div>
				<div class="col-7"><select class="form-select form-select-sm" name="severity"><option value="signaler">Signaler pour revue</option><option value="bloquer">Bloquer la publication</option></select></div>
				<div class="col-5 d-grid"><button class="btn btn-sm btn-primary">Ajouter</button></div></form>
			<ul class="list-group list-group-flush" style="max-height:320px;overflow:auto">
				<?php foreach ($words as $w): ?><li class="list-group-item d-flex align-items-center gap-2"><span class="flex-grow-1"><?= e($w['word']) ?></span><span class="badge bg-<?= $w['severity'] === 'bloquer' ? 'danger' : 'warning text-dark' ?>"><?= e($w['severity']) ?></span>
					<form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="word_del"><input type="hidden" name="id" value="<?= (int)$w['id'] ?>"><button class="btn btn-sm btn-light text-danger" aria-label="Retirer <?= e($w['word']) ?>"><i class="bi bi-x-lg"></i></button></form></li><?php endforeach; ?>
			</ul>
		</div>
		<div class="card">
			<div class="card-header"><h2 class="h6 mb-0">Charte de bienveillance</h2></div>
			<ul class="list-group list-group-flush"><?php foreach ($charte as $c): ?><li class="list-group-item d-flex gap-3"><i class="bi <?= $c[0] ?> fs-5 text-primary"></i><div><div class="fw-semibold"><?= e($c[1]) ?></div><div class="small text-secondary"><?= e($c[2]) ?></div></div></li><?php endforeach; ?></ul>
			<div class="card-footer small text-secondary">Sanctions : avertissement → suspension temporaire → exclusion.</div>
		</div>
	</div>
</div>
<?php include __DIR__ . '/../partials/admin-bottom.php';
