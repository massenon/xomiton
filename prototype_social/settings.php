<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();
require_once __DIR__ . '/inc/x_reseau.php';
$me = x_user();
$rec = store_find('users', $me['id']) ?: $me;
$prefs = array_merge([
	'visibility' => 'communaute', 'show_zone' => 1, 'sos_gps' => 0, 'searchable' => 1,
	'notif_email' => 1, 'notif_push' => 1, 'notif_invit' => 1, 'notif_events' => 1, 'notif_msg' => 1, 'notif_digest' => 'hebdo',
	'lang' => 'fr', 'quick_exit' => 1, 'pin_on' => 0, 'pin_hash' => '', 'mask_notifs' => 1,
], $rec['prefs'] ?? []);

if (isset($_GET['download'])) {
	$out = ['exporté_le' => date('c'), 'profil' => array_diff_key($rec, ['hash' => 1]),
		'publications' => x_user_posts($me['id']), 'connexions' => array_map(fn($c) => $c['name'], x_connections($me['id'])),
		'activité' => array_values(array_filter(store_all('journal'), fn($j) => ($j['user'] ?? '') === $me['email']))];
	x_log('Données', 'Export des données personnelles');
	header('Content-Type: application/json; charset=utf-8');
	header('Content-Disposition: attachment; filename="mes-donnees-xomiton.json"');
	echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
	exit;
}

function x_save_user(array $rec, array $patch) {
	store_update('users', $rec['id'], $patch);
	$fresh = store_find('users', $rec['id']);
	if ($fresh) $_SESSION['user'] = array_merge($_SESSION['user'], $fresh);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!csrf_ok()) x_redirect('settings.php', 'danger', 'Session expirée, veuillez réessayer.');
	$act = $_POST['action'] ?? '';
	if ($act === 'profile') {
		$name = trim($_POST['name'] ?? ''); $pseudo = trim($_POST['pseudo'] ?? ''); $phone = trim($_POST['phone'] ?? '');
		$zone = $_POST['zone'] ?? ''; $commune = $_POST['commune'] ?? '';
		if (mb_strlen($name) < 2) x_redirect('settings.php#profil', 'danger', 'Le nom est obligatoire.');
		if (!preg_match('/^[A-Za-z0-9_.-]{3,30}$/', $pseudo)) x_redirect('settings.php#profil', 'danger', 'Le pseudonyme doit contenir 3 à 30 caractères (lettres, chiffres, _ . -).');
		if ($phone !== '' && !preg_match('/^(\+229)?[\s0-9]{8,14}$/', $phone)) x_redirect('settings.php#profil', 'danger', 'Numéro de téléphone béninois invalide.');
		foreach (store_all('users') as $o) if ((int)$o['id'] !== (int)$me['id'] && strcasecmp($o['pseudo'] ?? '', $pseudo) === 0) x_redirect('settings.php#profil', 'danger', 'Ce pseudonyme est déjà utilisé.');
		if (!isset(x_zones()[$zone])) $zone = $rec['zone'];
		if (!in_array($commune, x_zones()[$zone] ?? [], true)) $commune = x_zones()[$zone][0] ?? '';
		x_save_user($rec, ['name' => $name, 'pseudo' => $pseudo, 'phone' => $phone, 'zone' => $zone, 'commune' => $commune, 'bio' => mb_substr(trim($_POST['bio'] ?? ''), 0, 200)]);
		x_log('Paramètres', 'Profil mis à jour');
		x_redirect('settings.php#profil', 'success', 'Votre profil a été mis à jour.');
	}
	if ($act === 'password') {
		$cur = $_POST['current'] ?? ''; $new = $_POST['new'] ?? ''; $conf = $_POST['confirm'] ?? '';
		if (empty($rec['hash']) || !password_verify($cur, $rec['hash'])) x_redirect('settings.php#motdepasse', 'danger', 'Le mot de passe actuel est incorrect.');
		if (strlen($new) < 8 || !preg_match('/[A-Z]/', $new) || !preg_match('/[0-9]/', $new) || !preg_match('/[^A-Za-z0-9]/', $new)) x_redirect('settings.php#motdepasse', 'danger', 'Le nouveau mot de passe doit contenir 8 caractères, une majuscule, un chiffre et un symbole.');
		if ($new !== $conf) x_redirect('settings.php#motdepasse', 'danger', 'La confirmation ne correspond pas.');
		x_save_user($rec, ['hash' => password_hash($new, PASSWORD_DEFAULT)]);
		x_log('Mot de passe', 'Mot de passe modifié');
		x_redirect('settings.php#motdepasse', 'success', 'Mot de passe modifié avec succès.');
	}
	if (in_array($act, ['privacy', 'notifications', 'language', 'security'], true)) {
		$p = $prefs;
		if ($act === 'privacy') {
			$p['visibility'] = in_array($_POST['visibility'] ?? '', ['public', 'communaute', 'connexions'], true) ? $_POST['visibility'] : 'communaute';
			foreach (['show_zone', 'sos_gps', 'searchable'] as $k) $p[$k] = empty($_POST[$k]) ? 0 : 1;
		}
		if ($act === 'notifications') {
			foreach (['notif_email', 'notif_push', 'notif_invit', 'notif_events', 'notif_msg'] as $k) $p[$k] = empty($_POST[$k]) ? 0 : 1;
			$p['notif_digest'] = in_array($_POST['notif_digest'] ?? '', ['jamais', 'quotidien', 'hebdo'], true) ? $_POST['notif_digest'] : 'hebdo';
		}
		if ($act === 'language') $p['lang'] = in_array($_POST['lang'] ?? '', ['fr', 'fon', 'yo', 'en'], true) ? $_POST['lang'] : 'fr';
		if ($act === 'security') {
			$p['quick_exit'] = empty($_POST['quick_exit']) ? 0 : 1; $p['mask_notifs'] = empty($_POST['mask_notifs']) ? 0 : 1;
			$pin = trim($_POST['pin'] ?? '');
			if (!empty($_POST['pin_on'])) {
				if ($pin !== '') {
					if (!preg_match('/^\d{4}$/', $pin)) x_redirect('settings.php#securite', 'danger', 'Le code PIN doit comporter exactement 4 chiffres.');
					$p['pin_hash'] = password_hash($pin, PASSWORD_DEFAULT);
				}
				if (empty($p['pin_hash'])) x_redirect('settings.php#securite', 'danger', 'Définissez un code PIN à 4 chiffres pour l’activer.');
				$p['pin_on'] = 1;
			} else { $p['pin_on'] = 0; }
		}
		x_save_user($rec, ['prefs' => $p]);
		x_log('Paramètres', 'Section « ' . $act . ' » enregistrée');
		$anchor = ['privacy' => 'confidentialite', 'notifications' => 'notifications', 'language' => 'langue', 'security' => 'securite'][$act];
		x_redirect('settings.php#' . $anchor, 'success', 'Paramètres enregistrés.');
	}
	if ($act === 'delete') {
		if (trim($_POST['confirm_text'] ?? '') !== 'SUPPRIMER') x_redirect('settings.php#supprimer', 'danger', 'Tapez SUPPRIMER pour confirmer.');
		if (str_ends_with($me['email'] ?? '', '@xomiton.bj')) x_redirect('settings.php#supprimer', 'warning', 'Compte de démonstration : la suppression est simulée et le compte est conservé.');
		x_save_user($rec, ['status' => 'supprime']);
		x_log('Compte', 'Suppression du compte');
		session_destroy();
		session_start();
		x_redirect('landing.php', 'success', 'Votre compte a été supprimé. Vos données seront effacées sous 30 jours.');
	}
}

$pageTitle = 'Paramètres';
$nav = 'settings';
$rec = store_find('users', $me['id']) ?: $rec;
$zones = x_zones();
$sw = function ($name, $label, $checked, $hint = '') { $id = 'sw_' . $name;
	return '<div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" id="' . $id . '" name="' . $name . '" value="1" ' . ($checked ? 'checked' : '') . '><label class="form-check-label" for="' . $id . '">' . $label . ($hint ? '<span class="d-block small text-secondary">' . $hint . '</span>' : '') . '</label></div>'; };
$sections = ['profil' => ['bi-person', 'Profil'], 'motdepasse' => ['bi-key', 'Mot de passe'], 'confidentialite' => ['bi-lock', 'Confidentialité'], 'notifications' => ['bi-bell', 'Notifications'],
	'langue' => ['bi-translate', 'Langue'], 'apparence' => ['bi-palette', 'Apparence'], 'securite' => ['bi-shield-lock', 'Sécurité'], 'donnees' => ['bi-download', 'Mes données'], 'supprimer' => ['bi-trash', 'Supprimer mon compte']];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<?php include("partials/title-meta.php"); ?>
	<?php include("partials/head-css.php"); ?>
</head>
<body>
	<?php include("partials/header.php"); ?>
	<main id="contenu" tabindex="-1">
		<div class="container">
			<div class="row g-4">
				<?php include("partials/sidenav.php"); ?>
				<div class="col-lg-9">
					<div class="row g-4">
						<div class="col-lg-3">
							<div class="card position-sticky" style="top:80px"><div class="list-group list-group-flush rounded">
								<?php foreach ($sections as $k => $s): ?><a class="list-group-item list-group-item-action <?= $k === 'supprimer' ? 'text-danger' : '' ?>" href="#<?= $k ?>"><i class="bi <?= $s[0] ?> me-2"></i><?= $s[1] ?></a><?php endforeach; ?>
							</div></div>
						</div>
						<div class="col-lg-9 vstack gap-4">
							<?= flash_html() ?>
							<div><h1 class="h4 mb-1">Paramètres</h1><p class="small text-secondary mb-0">Gérez votre compte, votre confidentialité et votre sécurité.</p></div>

							<section class="card" id="profil">
								<div class="card-header border-0 pb-0"><h2 class="h6 mb-0">Profil</h2></div>
								<div class="card-body">
									<form method="post" data-xm-validate><?= csrf_field() ?><input type="hidden" name="action" value="profile">
										<div class="row g-3">
											<div class="col-md-6"><label class="form-label" for="s_name">Nom complet</label><input class="form-control" id="s_name" name="name" required value="<?= e($rec['name']) ?>"></div>
											<div class="col-md-6"><label class="form-label" for="s_pseudo">Pseudonyme</label><div class="input-group"><span class="input-group-text">@</span><input class="form-control" id="s_pseudo" name="pseudo" required minlength="3" value="<?= e($rec['pseudo'] ?? '') ?>"></div></div>
											<div class="col-md-6"><label class="form-label" for="s_email">E-mail</label><input class="form-control" id="s_email" value="<?= e($rec['email']) ?>" disabled></div>
											<div class="col-md-6"><label class="form-label" for="s_phone">Téléphone</label><input class="form-control" id="s_phone" name="phone" data-rule="phone_bj" placeholder="+229 01 XX XX XX XX" value="<?= e($rec['phone'] ?? '') ?>"></div>
											<div class="col-md-6"><label class="form-label" for="s_zone">Département</label><select class="form-select" id="s_zone" name="zone" data-zone-select><?php foreach ($zones as $z => $cs): ?><option <?= ($rec['zone'] ?? '') === $z ? 'selected' : '' ?>><?= e($z) ?></option><?php endforeach; ?></select></div>
											<div class="col-md-6"><label class="form-label" for="s_commune">Commune</label><select class="form-select" id="s_commune" name="commune"><?php foreach ($zones[$rec['zone']] ?? [] as $c): ?><option <?= ($rec['commune'] ?? '') === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select></div>
											<div class="col-12"><label class="form-label" for="s_bio">Présentation (200 caractères max.)</label><textarea class="form-control" id="s_bio" name="bio" rows="2" maxlength="200"><?= e($rec['bio'] ?? '') ?></textarea></div>
										</div>
										<div class="text-end mt-3"><button class="btn btn-primary" type="submit">Enregistrer le profil</button></div>
									</form>
								</div>
							</section>

							<section class="card" id="motdepasse">
								<div class="card-header border-0 pb-0"><h2 class="h6 mb-0">Mot de passe</h2></div>
								<div class="card-body">
									<form method="post" data-xm-validate><?= csrf_field() ?><input type="hidden" name="action" value="password">
										<div class="row g-3">
											<div class="col-12"><label class="form-label" for="p_cur">Mot de passe actuel</label><input class="form-control" type="password" id="p_cur" name="current" required autocomplete="current-password"></div>
											<div class="col-md-6"><label class="form-label" for="p_new">Nouveau mot de passe</label><input class="form-control" type="password" id="p_new" name="new" required data-rule="password_strong" autocomplete="new-password"></div>
											<div class="col-md-6"><label class="form-label" for="p_conf">Confirmation</label><input class="form-control" type="password" id="p_conf" name="confirm" required autocomplete="new-password"></div>
										</div>
										<div class="form-text">8 caractères minimum, avec une majuscule, un chiffre et un symbole.</div>
										<div class="text-end mt-3"><button class="btn btn-primary" type="submit">Changer le mot de passe</button></div>
									</form>
								</div>
							</section>

							<section class="card" id="confidentialite">
								<div class="card-header border-0 pb-0"><h2 class="h6 mb-0">Confidentialité</h2></div>
								<div class="card-body">
									<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="privacy">
										<div class="mb-3"><label class="form-label" for="c_vis">Qui peut voir mon profil ?</label>
											<select class="form-select" id="c_vis" name="visibility"><option value="public" <?= $prefs['visibility'] === 'public' ? 'selected' : '' ?>>Tout le monde</option><option value="communaute" <?= $prefs['visibility'] === 'communaute' ? 'selected' : '' ?>>Membres de la communauté</option><option value="connexions" <?= $prefs['visibility'] === 'connexions' ? 'selected' : '' ?>>Mes connexions uniquement</option></select></div>
										<?= $sw('show_zone', 'Afficher ma zone sur mon profil', $prefs['show_zone']) ?>
										<?= $sw('searchable', 'Apparaître dans les suggestions « Qui suivre »', $prefs['searchable']) ?>
										<?= $sw('sos_gps', 'Consentement SOS : autoriser le partage de ma position GPS lors d’une alerte', $prefs['sos_gps'], 'La position n’est transmise qu’au moment où vous déclenchez une alerte, aux personnes ciblées de l’annuaire.') ?>
										<div class="text-end"><button class="btn btn-primary" type="submit">Enregistrer</button></div>
									</form>
								</div>
							</section>

							<section class="card" id="notifications">
								<div class="card-header border-0 pb-0"><h2 class="h6 mb-0">Notifications</h2></div>
								<div class="card-body">
									<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="notifications">
										<?= $sw('notif_invit', 'Invitations et nouvelles connexions', $prefs['notif_invit']) ?>
										<?= $sw('notif_msg', 'Nouveaux messages', $prefs['notif_msg']) ?>
										<?= $sw('notif_events', 'Rappels d’événements', $prefs['notif_events']) ?>
										<?= $sw('notif_push', 'Notifications dans l’application', $prefs['notif_push']) ?>
										<?= $sw('notif_email', 'Notifications par e-mail', $prefs['notif_email']) ?>
										<div class="mb-3"><label class="form-label" for="n_dig">Résumé d’activité</label><select class="form-select" id="n_dig" name="notif_digest"><?php foreach (['jamais' => 'Jamais', 'quotidien' => 'Quotidien', 'hebdo' => 'Hebdomadaire'] as $k => $l): ?><option value="<?= $k ?>" <?= $prefs['notif_digest'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
										<div class="text-end"><button class="btn btn-primary" type="submit">Enregistrer</button></div>
									</form>
								</div>
							</section>

							<section class="card" id="langue">
								<div class="card-header border-0 pb-0"><h2 class="h6 mb-0">Langue</h2></div>
								<div class="card-body">
									<form method="post" class="row g-3 align-items-end"><?= csrf_field() ?><input type="hidden" name="action" value="language">
										<div class="col-md-8"><label class="form-label" for="l_lang">Langue de l’interface</label><select class="form-select" id="l_lang" name="lang"><?php foreach (['fr' => 'Français', 'fon' => 'Fon (bientôt)', 'yo' => 'Yoruba (bientôt)', 'en' => 'English (bientôt)'] as $k => $l): ?><option value="<?= $k ?>" <?= $prefs['lang'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
										<div class="col-md-4"><button class="btn btn-primary w-100" type="submit">Enregistrer</button></div>
									</form>
								</div>
							</section>

							<section class="card" id="apparence">
								<div class="card-header border-0 pb-0"><h2 class="h6 mb-0">Apparence</h2></div>
								<div class="card-body">
									<p class="small text-secondary">Le thème est mémorisé sur cet appareil.</p>
									<div class="btn-group" role="group" aria-label="Thème">
										<button type="button" class="btn btn-outline-primary" data-theme-set="light"><i class="bi bi-sun me-1"></i>Clair</button>
										<button type="button" class="btn btn-outline-primary" data-theme-set="dark"><i class="bi bi-moon-stars me-1"></i>Sombre</button>
										<button type="button" class="btn btn-outline-primary" data-theme-set="auto"><i class="bi bi-circle-half me-1"></i>Automatique</button>
									</div>
								</div>
							</section>

							<section class="card" id="securite">
								<div class="card-header border-0 pb-0"><h2 class="h6 mb-0">Sécurité</h2></div>
								<div class="card-body">
									<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="security">
										<?= $sw('quick_exit', 'Afficher le bouton « Sortie rapide »', $prefs['quick_exit'], 'Quitte immédiatement le site et ouvre une page neutre.') ?>
										<?= $sw('mask_notifs', 'Masquer le contenu sensible dans les notifications', $prefs['mask_notifs']) ?>
										<?= $sw('pin_on', 'Verrouiller l’application avec un code PIN', $prefs['pin_on'], 'Fonction de démonstration : le code est enregistré (haché) mais ne verrouille pas encore l’application.') ?>
										<div class="mb-3"><label class="form-label" for="s_pin">Code PIN à 4 chiffres</label><input class="form-control" style="max-width:180px" id="s_pin" name="pin" inputmode="numeric" maxlength="4" pattern="\d{4}" placeholder="<?= $prefs['pin_hash'] ? '••••  (laisser vide pour conserver)' : '0000' ?>" autocomplete="off"></div>
										<div class="text-end"><button class="btn btn-primary" type="submit">Enregistrer</button></div>
									</form>
								</div>
							</section>

							<section class="card" id="donnees">
								<div class="card-header border-0 pb-0"><h2 class="h6 mb-0">Télécharger mes données</h2></div>
								<div class="card-body"><p class="small text-secondary">Obtenez une copie de votre profil, de vos publications et de votre activité au format JSON.</p><a class="btn btn-primary-soft" href="settings.php?download=1"><i class="bi bi-download me-1"></i>Télécharger mes données</a></div>
							</section>

							<section class="card border-danger" id="supprimer">
								<div class="card-header border-0 pb-0"><h2 class="h6 mb-0 text-danger">Supprimer mon compte</h2></div>
								<div class="card-body">
									<p class="small">Cette action est définitive : votre profil et vos publications seront supprimés sous 30 jours. Vos signalements anonymes déjà transmis sont conservés pour le suivi des cas.</p>
									<form method="post" onsubmit="return confirm('Supprimer définitivement votre compte ?');"><?= csrf_field() ?><input type="hidden" name="action" value="delete">
										<div class="row g-2 align-items-end"><div class="col-md-8"><label class="form-label" for="d_conf">Tapez <strong>SUPPRIMER</strong> pour confirmer</label><input class="form-control" id="d_conf" name="confirm_text" autocomplete="off"></div><div class="col-md-4"><button class="btn btn-danger w-100" type="submit">Supprimer mon compte</button></div></div>
									</form>
								</div>
							</section>
						</div>
					</div>
				</div>
			</div>
		</div>
	</main>
	<?php include("partials/modals.php"); ?>
	<?php include("partials/footer-scripts.php"); ?>
	<script>
	(function () {
		var cur = 'light'; try { cur = localStorage.getItem('theme') || 'light'; } catch (e) {}
		document.querySelectorAll('[data-theme-set]').forEach(function (b) {
			if (b.dataset.themeSet === cur) b.classList.add('active');
			b.addEventListener('click', function () {
				var t = b.dataset.themeSet;
				try { localStorage.setItem('theme', t); } catch (e) {}
				var eff = t === 'auto' ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light') : t;
				document.documentElement.setAttribute('data-bs-theme', eff);
				document.querySelectorAll('[data-theme-set]').forEach(function (x) { x.classList.toggle('active', x === b); });
				if (window.XM && XM.toast) XM.toast('Thème appliqué');
			});
		});
	})();
	</script>
</body>
</html>
