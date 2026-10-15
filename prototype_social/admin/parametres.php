<?php
require __DIR__ . '/../inc/bootstrap.php'; require_admin(); require_once __DIR__ . '/../inc/x_admin_sys.php';
$pageTitle = 'Paramètres'; $adminPage = 'parametres';
$langs = ['fr' => 'Français', 'en' => 'English', 'fon' => 'Fon', 'yo' => 'Yoruba'];
$tzs = ['Africa/Porto-Novo' => 'Bénin (Africa/Porto-Novo, UTC+1)', 'Africa/Lagos' => 'Lagos (UTC+1)', 'Africa/Abidjan' => 'Abidjan (UTC+0)', 'UTC' => 'UTC'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    xa_guard();
    $do = $_POST['do'] ?? '';
    if ($do === 'general') {
        $name = xa_post('site_name'); $mail = xa_post('contact_email'); $tz = xa_post('timezone', 'Africa/Porto-Novo');
        $sel = array_values(array_intersect(array_keys($langs), (array)($_POST['languages'] ?? [])));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 40) xa_back('danger', 'Nom de la plateforme : 2 à 40 caractères.');
        if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) xa_back('danger', 'Adresse e-mail de contact invalide.');
        if (!isset($tzs[$tz]) || !in_array('fr', $sel, true)) xa_back('danger', 'Fuseau invalide, ou le français a été désélectionné (langue obligatoire).');
        setting_set('site_name', $name); setting_set('contact_email', $mail); setting_set('timezone', $tz); setting_set('languages', $sel); setting_set('default_lang', 'fr');
        foreach (['anonymous_reports', 'gps_sos', 'offline_sync', 'maintenance'] as $k) setting_set($k, !empty($_POST[$k]));
        x_log('Paramètres', 'Paramètres généraux modifiés'); xa_back('success', 'Paramètres généraux enregistrés.');
    }
    if ($do === 'security') {
        $len = (int)xa_post('pwd_min_len', 8); $exp = (int)xa_post('pwd_expiry_days', 0); $sess = (int)xa_post('session_minutes', 120); $m = xa_post('twofa_method', 'sms');
        if ($len < 8 || $len > 64 || $exp < 0 || $exp > 365 || $sess < 5 || $sess > 1440 || !in_array($m, ['sms', 'app', 'email'], true)) xa_back('danger', 'Valeurs hors limites (mot de passe 8–64, expiration 0–365 j, session 5–1440 min).');
        setting_set('password_policy', ['min_len' => $len, 'upper' => !empty($_POST['pwd_upper']), 'digit' => !empty($_POST['pwd_digit']), 'symbol' => !empty($_POST['pwd_symbol']), 'expiry_days' => $exp]);
        setting_set('session_minutes', $sess); setting_set('twofa', ['on' => !empty($_POST['twofa_on']), 'method' => $m, 'admins_only' => !empty($_POST['twofa_admins'])]);
        x_log('Paramètres', 'Sécurité modifiée'); xa_back('success', 'Paramètres de sécurité enregistrés.');
    }
    xa_back('danger', 'Action inconnue.');
}

$pp = setting('password_policy', ['min_len' => 8, 'upper' => true, 'digit' => true, 'symbol' => false, 'expiry_days' => 0]);
$tf = setting('twofa', ['on' => false, 'method' => 'sms', 'admins_only' => true]);
$curLangs = setting('languages', ['fr']); if (!is_array($curLangs)) $curLangs = ['fr'];
$curTz = setting('timezone', 'Africa/Porto-Novo');
$flags = ['anonymous_reports' => ['Signaler', 'Permet de signaler sans compte ni identité.', true], 'gps_sos' => ['Localisation GPS pour le SOS', 'Joint la position (avec consentement) aux alertes SOS.', true], 'offline_sync' => ['Hors connexion & synchronisation', 'Mise en file d’attente locale et envoi automatique.', true], 'maintenance' => ['Mode maintenance', 'Bloque la connexion des non-administrateur·rices.', false]];
include __DIR__ . '/../partials/admin-top.php';
?>
<div class="row g-4">
	<div class="col-xl-8">
		<form method="post" class="card mb-4" data-xm-validate><?= csrf_field() ?><input type="hidden" name="do" value="general">
			<div class="card-header"><h2 class="h6 mb-0">Paramètres généraux</h2></div>
			<div class="card-body row g-3">
				<div class="col-md-6"><label class="form-label">Nom de la plateforme *</label><input class="form-control" name="site_name" value="<?= e(setting('site_name', 'Xomiton')) ?>" required></div>
				<div class="col-md-6"><label class="form-label">E-mail de contact *</label><input class="form-control" name="contact_email" value="<?= e(setting('contact_email', '')) ?>" required data-rule="email"></div>
				<div class="col-md-6"><label class="form-label">Fuseau horaire</label><select class="form-select" name="timezone"><?php foreach ($tzs as $k => $l): ?><option value="<?= $k ?>" <?= $curTz === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
				<div class="col-md-6"><label class="form-label d-block">Langues proposées</label><?php foreach ($langs as $k => $l): ?><div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="languages[]" value="<?= $k ?>" id="l<?= $k ?>" <?= in_array($k, $curLangs, true) ? 'checked' : '' ?>><label class="form-check-label" for="l<?= $k ?>"><?= e($l) ?></label></div><?php endforeach; ?></div>
				<div class="col-12"><hr class="my-0"></div>
				<?php foreach ($flags as $k => $f): ?><div class="col-md-6"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="<?= $k ?>" value="1" id="f<?= $k ?>" <?= setting($k, $f[2]) ? 'checked' : '' ?>><label class="form-check-label fw-semibold" for="f<?= $k ?>"><?= e($f[0]) ?></label><div class="small text-secondary"><?= e($f[1]) ?></div></div></div><?php endforeach; ?>
			</div><div class="card-footer text-end"><button class="btn btn-primary btn-sm">Enregistrer</button></div></form>

		<form method="post" class="card" data-xm-validate><?= csrf_field() ?><input type="hidden" name="do" value="security">
			<div class="card-header"><h2 class="h6 mb-0">Sécurité</h2></div>
			<div class="card-body row g-3">
				<div class="col-md-4"><label class="form-label">Longueur min. du mot de passe</label><input class="form-control" name="pwd_min_len" value="<?= (int)$pp['min_len'] ?>" data-rule="positive_int"></div>
				<div class="col-md-4"><label class="form-label">Expiration (jours, 0 = jamais)</label><input class="form-control" name="pwd_expiry_days" value="<?= (int)$pp['expiry_days'] ?>" data-rule="positive_int"></div>
				<div class="col-md-4"><label class="form-label">Durée de session (minutes)</label><input class="form-control" name="session_minutes" value="<?= (int)setting('session_minutes', 120) ?>" data-rule="positive_int"></div>
				<div class="col-12 d-flex flex-wrap gap-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="pwd_upper" value="1" id="pU" <?= $pp['upper'] ? 'checked' : '' ?>><label class="form-check-label" for="pU">Majuscule</label></div><div class="form-check"><input class="form-check-input" type="checkbox" name="pwd_digit" value="1" id="pD" <?= $pp['digit'] ? 'checked' : '' ?>><label class="form-check-label" for="pD">Chiffre</label></div><div class="form-check"><input class="form-check-input" type="checkbox" name="pwd_symbol" value="1" id="pS" <?= $pp['symbol'] ? 'checked' : '' ?>><label class="form-check-label" for="pS">Symbole</label></div></div>
				<div class="col-12"><hr class="my-0"></div>
				<div class="col-md-4"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="twofa_on" value="1" id="t2" <?= !empty($tf['on']) ? 'checked' : '' ?>><label class="form-check-label fw-semibold" for="t2">Double authentification <span class="badge bg-warning text-dark">fictive</span></label></div></div>
				<div class="col-md-4"><select class="form-select form-select-sm" name="twofa_method" aria-label="Méthode 2FA"><?php foreach (['sms' => 'Code par SMS', 'app' => 'Application d’authentification', 'email' => 'Code par e-mail'] as $k => $l): ?><option value="<?= $k ?>" <?= $tf['method'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
				<div class="col-md-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="twofa_admins" value="1" id="t2a" <?= !empty($tf['admins_only']) ? 'checked' : '' ?>><label class="form-check-label" for="t2a">Réservée à l’administration</label></div></div>
			</div><div class="card-footer text-end"><button class="btn btn-primary btn-sm">Enregistrer</button></div></form>
	</div>
	<div class="col-xl-4">
		<div class="card mb-4"><div class="card-header"><h2 class="h6 mb-0">Identité visuelle</h2></div><div class="card-body">
			<div class="p-4 rounded border mb-3 text-center bg-body-tertiary"><img src="../assets/images/logo.svg" height="44" alt="Logo Xomiton (couleur)"><div class="small text-secondary mt-2">Logo couleur</div></div>
			<div class="p-4 rounded mb-3 text-center" style="background:#0E1020"><img src="../assets/images/logo-dark.svg" height="44" alt="Logo Xomiton (blanc)"><div class="small mt-2" style="color:#8E88A0">Logo sur fond sombre</div></div>
			<div class="p-4 rounded mb-3 text-center xm-hero-bg"><img src="../assets/images/xomiton/brand/logo-mono-blanc.svg" height="44" alt="Logo Xomiton monochrome"><div class="small mt-2 text-white-50">Monochrome blanc</div></div>
			<div class="d-flex gap-2"><?php foreach (['#0433C3' => 'Bleu', '#E30E0E' => 'Rouge', '#6F2C91' => 'Violet', '#F0A800' => 'Or', '#0B7A4B' => 'Vert'] as $c => $l): ?><div class="flex-fill text-center"><div class="rounded" style="height:36px;background:<?= $c ?>"></div><div class="small text-secondary"><?= $l ?></div></div><?php endforeach; ?></div></div></div>
		<div class="card"><div class="card-header"><h2 class="h6 mb-0">Informations système</h2></div><ul class="list-group list-group-flush small">
			<li class="list-group-item d-flex justify-content-between"><span>Version</span><strong>v<?= e(setting('platform_version', '1.0')) ?></strong></li>
			<li class="list-group-item d-flex justify-content-between"><span>PHP</span><strong><?= e(PHP_VERSION) ?></strong></li>
			<li class="list-group-item d-flex justify-content-between"><span>Stockage</span><strong>JSON (prototype)</strong></li>
			<li class="list-group-item d-flex justify-content-between"><span>Dernière mise à jour</span><strong><?= e(xa_fr_date(setting('last_update', ''))) ?></strong></li></ul></div>
	</div>
</div>
<?php include __DIR__ . '/../partials/admin-bottom.php';
