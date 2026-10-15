<?php
require __DIR__ . '/../inc/bootstrap.php'; require_admin(); require_once __DIR__ . '/../inc/x_admin_sys.php';
$pageTitle = 'Utilisateurs & rôles'; $adminPage = 'utilisateurs';
$me = x_user(); $super = xa_is_super();
$roleLbl = ['membre' => 'Membre', 'pair' => 'Pair animateur·rice', 'admin' => 'Administrateur·rice', 'superadmin' => 'Super-admin'];
$assignable = $super ? array_keys($roleLbl) : ['membre', 'pair'];

function usr_temp_pwd() { $c = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789'; $p = ''; for ($i = 0; $i < 8; $i++) $p .= $c[random_int(0, strlen($c) - 1)]; return $p . 'A7'; }

if (($_GET['export'] ?? '') === 'csv') {
    xa_csv('xomiton-utilisateurs-' . date('Ymd') . '.csv', ['ID', 'Nom', 'Pseudo', 'E-mail', 'Rôle', 'Statut', 'Département', 'Inscrit le'],
        array_map(fn($u) => [$u['id'], $u['name'], $u['pseudo'], $u['email'], $u['role'], $u['status'], $u['zone'] ?? '', $u['joined'] ?? ''], store_all('users')));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    xa_guard();
    $do = $_POST['do'] ?? ''; $id = xa_post('id'); $u = $id !== '' ? store_find('users', $id) : null;
    if ($do === 'add') {
        $name = xa_post('name'); $pseudo = xa_post('pseudo'); $email = strtolower(xa_post('email')); $role = xa_post('role', 'membre'); $pwd = $_POST['password'] ?? '';
        if (mb_strlen($name) < 3 || !preg_match('/^[a-z0-9_.]{3,20}$/i', $pseudo) || !filter_var($email, FILTER_VALIDATE_EMAIL)) xa_back('danger', 'Nom, pseudonyme (3-20 caractères) et e-mail valides requis.');
        if (strlen($pwd) < 8 || !preg_match('/[A-Z]/', $pwd) || !preg_match('/\d/', $pwd)) xa_back('danger', 'Mot de passe : 8 caractères min., une majuscule et un chiffre.');
        if (!in_array($role, $assignable, true)) xa_back('danger', 'Seul un·e super-admin peut attribuer un rôle d’administration.');
        foreach (store_all('users') as $r) if (strtolower($r['email']) === $email || strtolower($r['pseudo']) === strtolower($pseudo)) xa_back('danger', 'E-mail ou pseudonyme déjà utilisé.');
        store_append('users', ['name' => $name, 'pseudo' => $pseudo, 'email' => $email, 'hash' => password_hash($pwd, PASSWORD_DEFAULT), 'role' => $role, 'status' => 'actif', 'zone' => xa_post('zone'), 'commune' => '', 'joined' => date('Y-m-d'), 'avatar' => random_int(0, 5), 'phone' => xa_post('phone')]);
        x_log('Utilisateurs', 'Création : ' . $email . ' (' . $role . ')'); xa_back('success', 'Compte créé pour ' . $name . '.');
    }
    if (!$u) xa_back('danger', 'Utilisateur introuvable.');
    $isAdminTarget = in_array($u['role'], ['admin', 'superadmin'], true);
    if ($isAdminTarget && !$super && (int)$u['id'] !== (int)$me['id']) xa_back('danger', 'Seul un·e super-admin peut modifier un compte d’administration.');
    if ($do === 'validate') { store_update('users', $id, ['status' => 'actif']); x_log('Utilisateurs', 'Validation : ' . $u['email']); xa_back('success', 'Compte de ' . $u['name'] . ' validé.'); }
    if ($do === 'suspend') { if ((int)$id === (int)$me['id']) xa_back('danger', 'Vous ne pouvez pas suspendre votre propre compte.'); store_update('users', $id, ['status' => 'suspendu']); x_log('Utilisateurs', 'Suspension : ' . $u['email']); xa_back('warning', 'Compte suspendu.'); }
    if ($do === 'reactivate') { store_update('users', $id, ['status' => 'actif']); x_log('Utilisateurs', 'Réactivation : ' . $u['email']); xa_back('success', 'Compte réactivé.'); }
    if ($do === 'role') {
        $role = xa_post('role');
        if (!in_array($role, $assignable, true)) xa_back('danger', 'Seul un·e super-admin peut attribuer un rôle d’administration.');
        if ((int)$id === (int)$me['id'] && $role !== $u['role']) xa_back('danger', 'Vous ne pouvez pas modifier votre propre rôle.');
        store_update('users', $id, ['role' => $role]); x_log('Utilisateurs', 'Rôle ' . $u['email'] . ' : ' . $u['role'] . ' → ' . $role); xa_back('success', 'Rôle mis à jour.');
    }
    if ($do === 'reset') { $p = usr_temp_pwd(); store_update('users', $id, ['hash' => password_hash($p, PASSWORD_DEFAULT)]); x_log('Utilisateurs', 'Réinitialisation mot de passe : ' . $u['email']); xa_back('info', 'Mot de passe temporaire de ' . $u['name'] . ' : ' . $p . ' — affiché une seule fois, à transmettre de manière sécurisée.'); }
    if ($do === 'delete') {
        if ((int)$id === (int)$me['id']) xa_back('danger', 'Vous ne pouvez pas supprimer votre propre compte.');
        store_delete('users', $id); x_log('Utilisateurs', 'Suppression : ' . $u['email']); xa_back('success', 'Compte supprimé.');
    }
    xa_back('danger', 'Action inconnue.');
}

$all = store_all('users');
$fr = $_GET['role'] ?? ''; $fs = $_GET['st'] ?? ''; $fz = $_GET['zone'] ?? ''; $fq = trim($_GET['q'] ?? '');
$rows = array_values(array_filter($all, fn($u) => ($fr === '' || $u['role'] === $fr) && ($fs === '' || $u['status'] === $fs) && ($fz === '' || ($u['zone'] ?? '') === $fz) && ($fq === '' || stripos($u['name'] . $u['pseudo'] . $u['email'], $fq) !== false)));
$cs = array_count_values(array_column($all, 'status')); $cr = array_count_values(array_column($all, 'role'));
$pending = array_filter($all, fn($u) => $u['status'] === 'en_attente');
$perm = [ // module => [membre, pair, admin, superadmin]
    'Réseau social (fil, chat, groupes)' => [2, 2, 2, 2], 'Signalement / SOS' => [2, 2, 2, 2], 'Ressources & outils CCC (lecture)' => [2, 2, 2, 2],
    'Collecte de données & registres' => [0, 2, 2, 2], 'Fiches VBG / VDH' => [0, 1, 2, 2], 'Suivi des victimes' => [0, 1, 2, 2],
    'Annuaire dynamique' => [1, 1, 2, 2], 'Rapports & indicateurs' => [0, 1, 2, 2], 'Modération & contenu' => [0, 0, 2, 2], 'Utilisateurs (membres, pairs)' => [0, 0, 2, 2],
    'Rôles d’administration' => [0, 0, 0, 2], 'Paramètres système & purge du journal' => [0, 0, 1, 2],
];
$pc = [0 => '<span class="text-danger" title="Aucun accès"><i class="bi bi-x-circle"></i></span>', 1 => '<span class="text-warning" title="Lecture / limité"><i class="bi bi-eye"></i></span>', 2 => '<span class="text-success" title="Accès complet"><i class="bi bi-check-circle-fill"></i></span>'];

$adminActions = '<button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#mUser"><i class="bi bi-person-plus me-1"></i>Utilisateur</button>';
include __DIR__ . '/../partials/admin-top.php';
?>
<div class="row g-3 mb-4">
	<?= xa_kpi('Comptes', count($all), 'bi-people') ?>
	<?= xa_kpi('Actifs', $cs['actif'] ?? 0, 'bi-person-check', 'success') ?>
	<?= xa_kpi('En attente', $cs['en_attente'] ?? 0, 'bi-hourglass-split', 'warning') ?>
	<?= xa_kpi('Suspendus', $cs['suspendu'] ?? 0, 'bi-person-slash', 'danger') ?>
</div>

<?php if ($pending): ?>
<div class="card border-warning mb-4">
	<div class="card-header bg-warning-subtle"><h2 class="h6 mb-0"><i class="bi bi-hourglass-split me-2"></i>Comptes à valider (<?= count($pending) ?>)</h2></div>
	<ul class="list-group list-group-flush">
	<?php foreach ($pending as $u): ?>
		<li class="list-group-item d-flex flex-wrap align-items-center gap-3">
			<img src="<?= x_avatar($u['avatar'] ?? 0) ?>" class="rounded-circle" width="40" height="40" alt="">
			<div class="flex-grow-1"><div class="fw-semibold"><?= e($u['name']) ?> <small class="text-secondary">@<?= e($u['pseudo']) ?></small></div><div class="small text-secondary"><?= e($u['email']) ?> · <?= e($u['zone'] ?? '') ?> · demande : <?= e($roleLbl[$u['profile'] ?? $u['role']] ?? ($u['profile'] ?? $u['role'])) ?></div></div>
			<form method="post" class="d-flex gap-2"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
				<button name="do" value="validate" class="btn btn-sm btn-success"><i class="bi bi-check-lg me-1"></i>Valider</button>
				<button name="do" value="delete" class="btn btn-sm btn-outline-danger" onclick="return confirm('Rejeter et supprimer cette demande ?')">Rejeter</button></form>
		</li>
	<?php endforeach; ?>
	</ul>
</div>
<?php endif; ?>

<div class="card mb-4">
	<div class="card-header d-flex flex-wrap justify-content-between gap-2 align-items-center"><h2 class="h6 mb-0">Tous les comptes</h2><a class="btn btn-outline-secondary btn-sm" href="?export=csv"><i class="bi bi-filetype-csv me-1"></i>CSV</a></div>
	<div class="card-body border-bottom">
		<form class="row g-2" method="get">
			<div class="col-12 col-md-3"><input type="search" name="q" class="form-control form-control-sm" placeholder="Nom, pseudo, e-mail…" value="<?= e($fq) ?>"></div>
			<div class="col-6 col-md-3"><select name="role" class="form-select form-select-sm"><option value="">Tous les rôles</option><?php foreach ($roleLbl as $k => $l): ?><option value="<?= $k ?>" <?= $fr === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
			<div class="col-6 col-md-2"><select name="st" class="form-select form-select-sm"><option value="">Tous statuts</option><?php foreach (['actif', 'en_attente', 'suspendu'] as $s): ?><option value="<?= $s ?>" <?= $fs === $s ? 'selected' : '' ?>><?= e(str_replace('_', ' ', $s)) ?></option><?php endforeach; ?></select></div>
			<div class="col-6 col-md-3"><select name="zone" class="form-select form-select-sm"><?= xa_zone_options($fz, true) ?></select></div>
			<div class="col-6 col-md-1 d-grid"><button class="btn btn-sm btn-primary-soft">OK</button></div>
		</form>
	</div>
	<div class="xm-table-wrap">
		<table class="table table-hover align-middle mb-0">
			<thead><tr><th>Utilisateur</th><th>Rôle</th><th>Statut</th><th>Zone</th><th>Inscrit</th><th class="text-end">Actions</th></tr></thead>
			<tbody>
			<?php foreach ($rows as $u): $self = (int)$u['id'] === (int)$me['id']; $locked = in_array($u['role'], ['admin', 'superadmin'], true) && !$super && !$self; ?>
				<tr>
					<td><div class="d-flex align-items-center gap-2"><img src="<?= x_avatar($u['avatar'] ?? 0) ?>" class="rounded-circle" width="36" height="36" alt=""><div><div class="fw-semibold"><?= e($u['name']) ?><?= $self ? ' <span class="badge bg-info">vous</span>' : '' ?></div><div class="small text-secondary"><?= e($u['email']) ?></div></div></div></td>
					<td>
						<form method="post" class="d-flex gap-1"><?= csrf_field() ?><input type="hidden" name="do" value="role"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
							<select name="role" class="form-select form-select-sm" style="min-width:150px" onchange="this.form.submit()" <?= ($self || $locked) ? 'disabled' : '' ?> aria-label="Rôle de <?= e($u['name']) ?>">
								<?php foreach ($roleLbl as $k => $l): ?><option value="<?= $k ?>" <?= $u['role'] === $k ? 'selected' : '' ?> <?= in_array($k, $assignable, true) || $u['role'] === $k ? '' : 'disabled' ?>><?= e($l) ?></option><?php endforeach; ?>
							</select></form>
					</td>
					<td><?= xa_status_badge($u['status']) ?></td>
					<td class="small"><?= e($u['zone'] ?? '') ?><div class="text-secondary"><?= e($u['commune'] ?? '') ?></div></td>
					<td class="small text-nowrap"><?= e(xa_fr_date($u['joined'] ?? '')) ?></td>
					<td class="text-end text-nowrap">
						<?php if (!$locked): ?>
						<form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
							<?php if ($u['status'] === 'en_attente'): ?><button name="do" value="validate" class="btn btn-sm btn-success" title="Valider"><i class="bi bi-check-lg"></i></button><?php endif; ?>
							<?php if ($u['status'] === 'suspendu'): ?><button name="do" value="reactivate" class="btn btn-sm btn-light" title="Réactiver"><i class="bi bi-arrow-counterclockwise"></i></button>
							<?php elseif (!$self): ?><button name="do" value="suspend" class="btn btn-sm btn-light" title="Suspendre" onclick="return confirm('Suspendre ce compte ?')"><i class="bi bi-pause-circle"></i></button><?php endif; ?>
							<button name="do" value="reset" class="btn btn-sm btn-light" title="Réinitialiser le mot de passe" onclick="return confirm('Générer un mot de passe temporaire ?')"><i class="bi bi-key"></i></button>
							<?php if (!$self): ?><button name="do" value="delete" class="btn btn-sm btn-light text-danger" title="Supprimer" onclick="return confirm('Supprimer définitivement ce compte ?')"><i class="bi bi-trash"></i></button><?php endif; ?>
						</form>
						<?php else: ?><span class="small text-secondary"><i class="bi bi-lock"></i> super-admin</span><?php endif; ?>
					</td>
				</tr>
			<?php endforeach; if (!$rows): ?><tr><td colspan="6" class="text-center text-secondary py-4">Aucun compte ne correspond.</td></tr><?php endif; ?>
			</tbody>
		</table>
	</div>
</div>

<div class="card">
	<div class="card-header"><h2 class="h6 mb-0">Matrice des permissions (RBAC)</h2></div>
	<div class="xm-table-wrap"><table class="table table-sm align-middle text-center mb-0">
		<thead><tr><th class="text-start">Module</th><?php foreach ($roleLbl as $k => $l): ?><th><?= e($l) ?><div class="small fw-normal text-secondary"><?= (int)($cr[$k] ?? 0) ?> compte(s)</div></th><?php endforeach; ?></tr></thead>
		<tbody><?php foreach ($perm as $m => $v): ?><tr><td class="text-start"><?= e($m) ?></td><?php foreach ($v as $x): ?><td><?= $pc[$x] ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody>
	</table></div>
	<div class="card-footer small text-secondary"><span class="me-3"><?= $pc[2] ?> complet</span><span class="me-3"><?= $pc[1] ?> limité / lecture</span><span><?= $pc[0] ?> aucun accès</span></div>
</div>

<div class="modal fade" id="mUser" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><form class="modal-content" method="post" data-xm-validate autocomplete="off">
	<?= csrf_field() ?><input type="hidden" name="do" value="add">
	<div class="modal-header"><h5 class="modal-title">Ajouter un utilisateur</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
	<div class="modal-body row g-3">
		<div class="col-12"><label class="form-label">Nom complet *</label><input class="form-control" name="name" required></div>
		<div class="col-md-6"><label class="form-label">Pseudonyme *</label><input class="form-control" name="pseudo" required></div>
		<div class="col-md-6"><label class="form-label">Téléphone</label><input class="form-control" name="phone" data-rule="phone_bj"></div>
		<div class="col-12"><label class="form-label">E-mail *</label><input class="form-control" name="email" required data-rule="email"></div>
		<div class="col-md-6"><label class="form-label">Mot de passe *</label><input class="form-control" type="password" name="password" required data-rule="password_strong" autocomplete="new-password"></div>
		<div class="col-md-6"><label class="form-label">Rôle</label><select class="form-select" name="role"><?php foreach ($roleLbl as $k => $l): ?><option value="<?= $k ?>" <?= in_array($k, $assignable, true) ? '' : 'disabled' ?>><?= e($l) ?><?= in_array($k, $assignable, true) ? '' : ' (super-admin)' ?></option><?php endforeach; ?></select></div>
		<div class="col-12"><label class="form-label">Département</label><select class="form-select" name="zone"><?= xa_zone_options() ?></select></div>
	</div>
	<div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button class="btn btn-primary">Créer le compte</button></div>
</form></div></div>
<?php $adminScripts = xa_common_js(); include __DIR__ . '/../partials/admin-bottom.php';
