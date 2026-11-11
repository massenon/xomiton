<?php
require __DIR__ . '/../inc/bootstrap.php'; require_admin(); require_once __DIR__ . '/../inc/x_admin_sys.php'; require_once __DIR__ . '/../inc/x_mailer.php';
$pageTitle = 'Templates e-mails'; $adminPage = 'emails';
$cats = ['Authentification', 'Communauté', 'Alertes', 'Newsletter', 'Autre'];

function em_json($d, $code = 200) { http_response_code($code); header('Content-Type: application/json; charset=utf-8'); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
function em_slug_ok($s) { return (bool)preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $s) && strlen($s) <= 60; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ajax = !empty($_POST['ajax']);
    if (!csrf_ok()) { if ($ajax) em_json(['ok' => false, 'error' => 'Session expirée, rechargez la page.'], 419); xa_guard(); }
    $do = $_POST['do'] ?? '';

    /* Aperçu en direct (contenu non enregistré) */
    if ($do === 'preview') {
        $tpl = ['subject' => xa_post('subject'), 'preheader' => xa_post('preheader'), 'body' => x_mail_sanitize($_POST['body'] ?? '')];
        [$subject, $html] = x_mail_build($tpl, x_mail_sample_vars(), ['logo' => BASE . 'assets/images/xomiton/brand/logo-mono-blanc.svg']);
        $known = array_keys(x_mail_variables());
        $unknown = array_values(array_diff(x_mail_used_vars($tpl['subject'] . ' ' . $tpl['body']), $known));
        em_json(['ok' => true, 'subject' => $subject, 'html' => $html, 'unknown' => $unknown]);
    }
    /* Envoi de test réel (serveur SMTP configuré) avant validation des changements */
    if ($do === 'test') {
        $to = xa_post('to');
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) em_json(['ok' => false, 'error' => 'Adresse e-mail de test invalide.'], 422);
        $tpl = ['subject' => '[TEST] ' . xa_post('subject'), 'preheader' => xa_post('preheader'), 'body' => x_mail_sanitize($_POST['body'] ?? '')];
        if (trim(strip_tags($tpl['body'])) === '') em_json(['ok' => false, 'error' => 'Le corps du message est vide.'], 422);
        [$subject, $html] = x_mail_build($tpl, x_mail_sample_vars());
        $r = x_mail_send($to, $subject, $html, ['template' => xa_post('slug') ?: '(brouillon)', 'context' => 'test']);
        x_log('E-mail de test', ($r['ok'] ? 'Envoyé' : 'Échec') . ' : ' . xa_post('slug') . ' → ' . $to);
        if (!$r['ok']) em_json(['ok' => false, 'error' => $r['error']], 502);
        em_json(['ok' => true, 'message' => $r['transport'] === 'smtp' ? 'E-mail de test envoyé à ' . $to . ' via le serveur SMTP.' : 'Aucun serveur SMTP configuré : le test a été déposé dans la boîte d’envoi locale (data/mail_outbox.json).']);
    }
    if ($do === 'save') {
        $id = xa_post('id'); $name = xa_post('name'); $slug = strtolower(xa_post('slug')); $subject = xa_post('subject'); $body = x_mail_sanitize($_POST['body'] ?? '');
        $cat = in_array(xa_post('category'), $cats, true) ? xa_post('category') : 'Autre';
        $old = $id !== '' ? store_find('email_templates', $id) : null;
        if ($id !== '' && !$old) xa_back('danger', 'Template introuvable.');
        if ($old && !empty($old['system'])) $slug = $old['slug']; // slug verrouillé : le code applicatif y fait référence
        if (mb_strlen($name) < 3 || mb_strlen($name) > 80) xa_back('danger', 'Nom : 3 à 80 caractères.');
        if (!em_slug_ok($slug)) xa_back('danger', 'Slug invalide : minuscules, chiffres et tirets uniquement (ex. reinitialisation-mot-de-passe).');
        foreach (store_all('email_templates') as $t) if ($t['slug'] === $slug && (string)$t['id'] !== $id) xa_back('danger', 'Le slug « ' . $slug . ' » est déjà utilisé par « ' . $t['name'] . ' ».');
        if (mb_strlen($subject) < 3 || mb_strlen($subject) > 150) xa_back('danger', 'Objet : 3 à 150 caractères.');
        if (mb_strlen(trim(strip_tags($body))) < 10) xa_back('danger', 'Le corps du message doit contenir au moins 10 caractères.');
        $row = ['name' => $name, 'slug' => $slug, 'category' => $cat, 'subject' => $subject, 'preheader' => mb_substr(xa_post('preheader'), 0, 150), 'body' => $body,
            'active' => !empty($_POST['active']), 'updated' => date('Y-m-d H:i:s'), 'updated_by' => x_user()['email']];
        if ($old) { store_update('email_templates', $old['id'], $row); x_log('Template e-mail', 'Modifié : ' . $slug); xa_back('success', 'Template « ' . $name . ' » enregistré.'); }
        store_append('email_templates', $row + ['system' => false, 'created' => date('Y-m-d H:i:s')]); x_log('Template e-mail', 'Créé : ' . $slug);
        xa_back('success', 'Template « ' . $name . ' » créé.');
    }
    if ($do === 'delete') {
        $t = store_find('email_templates', xa_post('id')); if (!$t) xa_back('danger', 'Template introuvable.');
        if (!empty($t['system']) && xa_post('confirm_slug') !== $t['slug']) xa_back('danger', 'Template système : saisissez exactement son slug pour confirmer la suppression.');
        store_delete('email_templates', $t['id']); x_log('Template e-mail', 'Supprimé : ' . $t['slug']);
        xa_back('success', 'Template « ' . $t['name'] . ' » supprimé.' . (!empty($t['system']) ? ' Attention : les envois qui l’utilisaient échoueront tant qu’un template avec ce slug n’est pas recréé.' : ''));
    }
    if ($do === 'duplicate') {
        $t = store_find('email_templates', xa_post('id')); if (!$t) xa_back('danger', 'Template introuvable.');
        $slugs = array_column(store_all('email_templates'), 'slug'); $n = 2; while (in_array($t['slug'] . '-copie' . ($n > 2 ? '-' . $n : ''), $slugs, true)) $n++;
        unset($t['id']);
        store_append('email_templates', array_merge($t, ['name' => $t['name'] . ' (copie)', 'slug' => $t['slug'] . '-copie' . ($n > 2 ? '-' . $n : ''), 'system' => false, 'active' => false, 'created' => date('Y-m-d H:i:s'), 'updated' => date('Y-m-d H:i:s'), 'updated_by' => x_user()['email']]));
        x_log('Template e-mail', 'Dupliqué : ' . $t['slug']); xa_back('success', 'Copie créée (désactivée).');
    }
    if ($do === 'smtp') {
        $c = x_smtp_config();
        $host = xa_post('host'); $port = (int)xa_post('port', 587); $sec = in_array(xa_post('secure'), ['tls', 'ssl', 'none'], true) ? xa_post('secure') : 'tls';
        $from = xa_post('from_email'); $reply = xa_post('reply_to');
        if ($host !== '' && !preg_match('/^[a-z0-9.-]+$/i', $host)) xa_back('danger', 'Hôte SMTP invalide.', '#smtp');
        if ($port < 1 || $port > 65535) xa_back('danger', 'Port SMTP invalide.', '#smtp');
        if (!filter_var($from, FILTER_VALIDATE_EMAIL) || ($reply !== '' && !filter_var($reply, FILTER_VALIDATE_EMAIL))) xa_back('danger', 'Adresse d’expédition ou de réponse invalide.');
        setting_set('smtp', ['host' => $host, 'port' => $port, 'secure' => $sec, 'user' => xa_post('user'), 'pass' => ($_POST['pass'] ?? '') !== '' ? $_POST['pass'] : $c['pass'],
            'from_email' => $from, 'from_name' => xa_post('from_name') ?: 'Xomiton', 'reply_to' => $reply]);
        x_log('Paramètres', 'Configuration SMTP modifiée'); xa_back('success', $host ? 'Serveur SMTP enregistré. Faites un envoi de test pour le vérifier.' : 'SMTP désactivé : les e-mails seront déposés dans la boîte d’envoi locale.');
    }
    xa_back('danger', 'Action inconnue.');
}

$all = store_all('email_templates');
usort($all, fn($a, $b) => strcmp($b['updated'] ?? '', $a['updated'] ?? ''));
$q = trim($_GET['q'] ?? ''); $fc = $_GET['cat'] ?? '';
$rows = array_values(array_filter($all, fn($t) => ($fc === '' || ($t['category'] ?? '') === $fc) && ($q === '' || mb_stripos($t['name'] . ' ' . $t['slug'], $q) !== false)));
$per = 10; [$nPages, $page] = xa_pages(count($rows), $per, (int)($_GET['p'] ?? 1));
$pageRows = array_slice($rows, ($page - 1) * $per, $per);
$log = store_all('mail_log'); $log30 = array_filter($log, fn($l) => strtotime($l['at']) >= strtotime('-30 days'));
$smtp = x_smtp_config(); $vars = x_mail_variables();

$adminHead = '<link rel="stylesheet" href="' . BASE . 'assets/vendor/quill/quill.snow.css">';
$adminActions = '<button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#mTpl"><i class="bi bi-plus-lg me-1"></i>Nouveau template</button>';
include __DIR__ . '/../partials/admin-top.php';
?>
<div class="row g-3 mb-4">
	<?= xa_kpi('Templates', count($all), 'bi-envelope-paper') ?>
	<?= xa_kpi('Templates système', count(array_filter($all, fn($t) => !empty($t['system']))), 'bi-shield-lock', 'violet', 'Protégés contre la suppression') ?>
	<?= xa_kpi('Envois (30 j)', count($log30), 'bi-send-check', 'success') ?>
	<?= xa_kpi('Échecs (30 j)', count(array_filter($log30, fn($l) => !$l['ok'])), 'bi-exclamation-octagon', 'danger') ?>
</div>

<div class="card mb-4" id="liste">
	<div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
		<h2 class="h6 mb-0">Templates d’e-mails</h2>
		<form class="d-flex flex-wrap gap-2" method="get" role="search">
			<div class="input-group input-group-sm" style="width:auto">
				<span class="input-group-text bg-transparent"><i class="bi bi-search" aria-hidden="true"></i></span>
				<input type="search" class="form-control" name="q" value="<?= e($q) ?>" placeholder="Nom ou slug…" aria-label="Rechercher un template par nom ou slug" data-em-search>
			</div>
			<select name="cat" class="form-select form-select-sm w-auto" aria-label="Filtrer par catégorie" onchange="this.form.submit()"><option value="">Toutes les catégories</option><?php foreach ($cats as $c): ?><option <?= $fc === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select>
			<button class="btn btn-sm btn-primary-soft">Filtrer</button>
			<?php if ($q !== '' || $fc !== ''): ?><a class="btn btn-sm btn-light" href="emails.php">Réinitialiser</a><?php endif; ?>
		</form>
	</div>
	<div class="xm-table-wrap">
		<table class="table table-hover align-middle mb-0">
			<thead><tr><th>Nom</th><th>Slug</th><th class="d-none d-lg-table-cell">Objet</th><th>Dernière modification</th><th class="text-end">Actions</th></tr></thead>
			<tbody data-em-rows>
			<?php foreach ($pageRows as $t): $fill = e(json_encode($t, JSON_UNESCAPED_UNICODE)); ?>
				<tr data-em-text="<?= e(mb_strtolower($t['name'] . ' ' . $t['slug'])) ?>" class="<?= empty($t['active']) ? 'text-secondary' : '' ?>">
					<td>
						<div class="fw-semibold"><?= e($t['name']) ?></div>
						<span class="badge bg-primary-subtle text-primary-emphasis"><?= e($t['category'] ?? 'Autre') ?></span>
						<?php if (!empty($t['system'])): ?><span class="badge bg-dark-subtle text-dark-emphasis" title="Utilisé par le code de la plateforme"><i class="bi bi-shield-lock me-1"></i>Système</span><?php endif; ?>
						<?php if (empty($t['active'])): ?><span class="badge bg-secondary">Désactivé</span><?php endif; ?>
					</td>
					<td><code class="small"><?= e($t['slug']) ?></code></td>
					<td class="small d-none d-lg-table-cell text-truncate" style="max-width:260px" title="<?= e($t['subject']) ?>"><?= e($t['subject']) ?></td>
					<td class="small text-nowrap"><?= e(date('d/m/Y H:i', strtotime($t['updated']))) ?><div class="text-secondary"><?= e($t['updated_by'] ?? '') ?></div></td>
					<td class="text-end text-nowrap">
						<button class="btn btn-sm btn-light" data-em-preview="<?= (int)$t['id'] ?>" data-em-title="<?= e($t['name']) ?>" data-bs-toggle="modal" data-bs-target="#mPreview" title="Prévisualiser" aria-label="Prévisualiser <?= e($t['name']) ?>"><i class="bi bi-eye"></i></button>
						<button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#mTpl" data-tpl='<?= $fill ?>' title="Modifier" aria-label="Modifier <?= e($t['name']) ?>"><i class="bi bi-pencil"></i></button>
						<button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#mTpl" data-tpl='<?= $fill ?>' data-focus-test title="Envoyer un test" aria-label="Envoyer un test de <?= e($t['name']) ?>"><i class="bi bi-send"></i></button>
						<form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="do" value="duplicate"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><button class="btn btn-sm btn-light" title="Dupliquer" aria-label="Dupliquer <?= e($t['name']) ?>"><i class="bi bi-copy"></i></button></form>
						<button class="btn btn-sm btn-light text-danger" data-bs-toggle="modal" data-bs-target="#mDelete" data-del-id="<?= (int)$t['id'] ?>" data-del-name="<?= e($t['name']) ?>" data-del-slug="<?= e($t['slug']) ?>" data-del-system="<?= !empty($t['system']) ? '1' : '0' ?>" title="Supprimer" aria-label="Supprimer <?= e($t['name']) ?>"><i class="bi bi-trash"></i></button>
					</td>
				</tr>
			<?php endforeach; if (!$pageRows): ?>
				<tr><td colspan="5" class="text-center text-secondary py-4"><i class="bi bi-envelope-x fs-3 d-block mb-1"></i>Aucun template ne correspond à la recherche.</td></tr>
			<?php endif; ?>
			<tr class="d-none" data-em-empty><td colspan="5" class="text-center text-secondary py-4">Aucun template sur cette page ne correspond.</td></tr>
			</tbody>
		</table>
	</div>
	<?= xa_pagebar(count($rows), $per, $page, array_filter(['q' => $q, 'cat' => $fc])) ?>
</div>

<div class="row g-4">
	<div class="col-xl-6">
		<form method="post" class="card h-100" id="smtp" data-xm-validate><?= csrf_field() ?><input type="hidden" name="do" value="smtp">
			<div class="card-header d-flex justify-content-between align-items-center"><h2 class="h6 mb-0"><i class="bi bi-hdd-network me-2"></i>Serveur SMTP</h2><?= $smtp['host'] ? '<span class="badge bg-success">Configuré</span>' : '<span class="badge bg-warning text-dark">Boîte d’envoi locale</span>' ?></div>
			<div class="card-body row g-3">
				<div class="col-md-6"><label class="form-label small" for="sHost">Hôte</label><input class="form-control form-control-sm" id="sHost" name="host" value="<?= e($smtp['host']) ?>" placeholder="mail.xomiton.org"></div>
				<div class="col-6 col-md-3"><label class="form-label small" for="sPort">Port</label><input class="form-control form-control-sm" id="sPort" name="port" value="<?= (int)$smtp['port'] ?>" data-rule="positive_int"></div>
				<div class="col-6 col-md-3"><label class="form-label small" for="sSec">Chiffrement</label><select class="form-select form-select-sm" id="sSec" name="secure"><?php foreach (['tls' => 'STARTTLS', 'ssl' => 'SSL/TLS', 'none' => 'Aucun'] as $k => $l): ?><option value="<?= $k ?>" <?= $smtp['secure'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
				<div class="col-md-6"><label class="form-label small" for="sUser">Identifiant</label><input class="form-control form-control-sm" id="sUser" name="user" value="<?= e($smtp['user']) ?>" autocomplete="off"></div>
				<div class="col-md-6"><label class="form-label small" for="sPass">Mot de passe</label><input type="password" class="form-control form-control-sm" id="sPass" name="pass" placeholder="<?= $smtp['pass'] ? '•••••••• (inchangé)' : '' ?>" autocomplete="new-password"></div>
				<div class="col-md-6"><label class="form-label small" for="sFrom">E-mail d’expédition *</label><input class="form-control form-control-sm" id="sFrom" name="from_email" value="<?= e($smtp['from_email']) ?>" required data-rule="email"></div>
				<div class="col-md-6"><label class="form-label small" for="sName">Nom d’expédition</label><input class="form-control form-control-sm" id="sName" name="from_name" value="<?= e($smtp['from_name']) ?>"></div>
				<div class="col-12"><label class="form-label small" for="sReply">E-mail de réponse</label><input class="form-control form-control-sm" id="sReply" name="reply_to" value="<?= e($smtp['reply_to']) ?>" data-rule="email"></div>
				<p class="small text-secondary mb-0">Laissez l’hôte vide pour déposer les e-mails dans la boîte d’envoi locale (<code>data/mail_outbox.json</code>) au lieu de les envoyer.</p>
			</div>
			<div class="card-footer text-end"><button class="btn btn-primary btn-sm">Enregistrer</button></div>
		</form>
	</div>
	<div class="col-xl-6">
		<div class="card h-100"><div class="card-header"><h2 class="h6 mb-0"><i class="bi bi-clock-history me-2"></i>Derniers envois</h2></div>
			<div class="xm-table-wrap"><table class="table table-sm align-middle mb-0"><thead><tr><th>Date</th><th>Destinataire</th><th>Template</th><th>Résultat</th></tr></thead><tbody>
			<?php foreach (array_slice(array_reverse($log), 0, 8) as $l): ?>
				<tr><td class="small text-nowrap"><?= e(date('d/m H:i', strtotime($l['at']))) ?></td><td class="small"><?= e($l['to']) ?></td><td class="small"><code><?= e($l['template'] ?: '—') ?></code><?= ($l['context'] ?? '') === 'test' ? ' <span class="badge bg-info-subtle text-info-emphasis">test</span>' : '' ?></td>
				<td><?= $l['ok'] ? '<span class="badge bg-success">' . ($l['transport'] === 'smtp' ? 'SMTP' : 'Boîte locale') . '</span>' : '<span class="badge bg-danger" title="' . e($l['error'] ?? '') . '">Échec</span>' ?></td></tr>
			<?php endforeach; if (!$log): ?><tr><td colspan="4" class="text-center text-secondary small py-4">Aucun envoi pour l’instant.</td></tr><?php endif; ?>
			</tbody></table></div>
		</div>
	</div>
</div>

<!-- Modale : créer / modifier un template -->
<div class="modal fade" id="mTpl" tabindex="-1" aria-labelledby="mTplTitle" aria-hidden="true"><div class="modal-dialog modal-xl modal-fullscreen-lg-down modal-dialog-scrollable"><form class="modal-content" method="post" id="tplForm" novalidate>
	<?= csrf_field() ?><input type="hidden" name="do" value="save"><input type="hidden" name="id"><input type="hidden" name="body">
	<div class="modal-header"><h5 class="modal-title" id="mTplTitle">Nouveau template</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
	<div class="modal-body">
		<div class="row g-4">
			<div class="col-lg-8">
				<div class="row g-3">
					<div class="col-md-6"><label class="form-label" for="tName">Nom *</label><input class="form-control" id="tName" name="name" required minlength="3" maxlength="80" placeholder="Réinitialisation du mot de passe"></div>
					<div class="col-md-6"><label class="form-label" for="tSlug">Slug unique *</label><input class="form-control font-monospace" id="tSlug" name="slug" required pattern="[a-z0-9]+(-[a-z0-9]+)*" maxlength="60" placeholder="reinitialisation-mot-de-passe" aria-describedby="tSlugHelp"><div class="form-text" id="tSlugHelp" data-slug-help>Minuscules, chiffres et tirets. Identifiant utilisé par le code pour envoyer cet e-mail.</div></div>
					<div class="col-md-8"><label class="form-label" for="tSubject">Objet *</label><input class="form-control" id="tSubject" name="subject" required minlength="3" maxlength="150" data-var-target placeholder="{{site_name}} : choisissez un nouveau mot de passe"></div>
					<div class="col-md-4"><label class="form-label" for="tCat">Catégorie</label><select class="form-select" id="tCat" name="category"><?php foreach ($cats as $c): ?><option><?= e($c) ?></option><?php endforeach; ?></select></div>
					<div class="col-12"><label class="form-label" for="tPre">Texte d’aperçu <span class="small text-secondary">(affiché après l’objet dans la boîte de réception)</span></label><input class="form-control" id="tPre" name="preheader" maxlength="150" data-var-target></div>
					<div class="col-12">
						<div class="d-flex justify-content-between align-items-end mb-1">
							<label class="form-label mb-0" id="tBodyLabel">Corps du message *</label>
							<div class="btn-group btn-group-sm" role="group" aria-label="Mode d’édition">
								<button type="button" class="btn btn-outline-primary active" data-mode="visual" aria-pressed="true"><i class="bi bi-type me-1"></i>Visuel</button>
								<button type="button" class="btn btn-outline-primary" data-mode="html" aria-pressed="false"><i class="bi bi-code-slash me-1"></i>HTML</button>
							</div>
						</div>
						<div id="tEditor" style="min-height:260px" aria-labelledby="tBodyLabel"></div>
						<textarea class="form-control font-monospace small d-none" id="tHtml" rows="12" aria-label="Code HTML du corps"></textarea>
						<div class="invalid-feedback d-block d-none" data-body-error>Le corps du message doit contenir au moins 10 caractères.</div>
					</div>
					<div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="active" value="1" id="tActive" checked><label class="form-check-label" for="tActive">Template actif (utilisable par la plateforme)</label></div></div>
				</div>
			</div>
			<div class="col-lg-4">
				<div class="card bg-body-tertiary border-0 mb-3">
					<div class="card-body">
						<h6 class="mb-1"><i class="bi bi-braces me-1"></i>Variables dynamiques</h6>
						<p class="small text-secondary mb-2">Cliquez pour insérer à l’emplacement du curseur (corps, objet ou texte d’aperçu).</p>
						<input type="search" class="form-control form-control-sm mb-2" placeholder="Filtrer les variables…" aria-label="Filtrer les variables" data-var-filter>
						<div class="list-group list-group-flush rounded overflow-auto" style="max-height:290px" data-var-list>
							<?php foreach ($vars as $k => $v): ?>
							<button type="button" class="list-group-item list-group-item-action py-2 px-2" data-var="<?= e($k) ?>" title="Insérer {{<?= e($k) ?>}} — ex. : <?= e($v[1]) ?>"><code class="small">{{<?= e($k) ?>}}</code><span class="d-block small text-body-secondary lh-sm"><?= e($v[0]) ?></span></button>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
				<div class="card border-0 bg-body-tertiary mb-3"><div class="card-body">
					<h6><i class="bi bi-eye me-1"></i>Prévisualisation</h6>
					<p class="small text-secondary mb-2">Rendu HTML réel avec des données fictives, sans enregistrer.</p>
					<button type="button" class="btn btn-sm btn-outline-primary w-100" data-live-preview><i class="bi bi-window me-1"></i>Prévisualiser</button>
				</div></div>
				<div class="card border-0 bg-body-tertiary"><div class="card-body">
					<h6><i class="bi bi-send me-1"></i>Envoyer un test</h6>
					<p class="small text-secondary mb-2">Envoie la version en cours d’édition via <?= $smtp['host'] ? 'le serveur SMTP <b>' . e($smtp['host']) . '</b>' : 'la boîte d’envoi locale (aucun SMTP configuré)' ?>.</p>
					<label class="visually-hidden" for="tTestTo">Adresse de test</label>
					<div class="input-group input-group-sm"><input type="email" class="form-control" id="tTestTo" value="<?= e(x_user()['email']) ?>" autocomplete="email"><button type="button" class="btn btn-primary" data-send-test>Envoyer</button></div>
					<div class="small mt-2" role="status" aria-live="polite" data-test-result></div>
				</div></div>
			</div>
		</div>
	</div>
	<div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Enregistrer</button></div>
</form></div></div>

<!-- Modale : prévisualisation -->
<div class="modal fade" id="mPreview" tabindex="-1" aria-labelledby="mPreviewTitle" aria-hidden="true"><div class="modal-dialog modal-xl modal-fullscreen-lg-down modal-dialog-scrollable"><div class="modal-content">
	<div class="modal-header flex-wrap gap-2">
		<div class="me-auto"><h5 class="modal-title" id="mPreviewTitle">Prévisualisation</h5><div class="small text-secondary">Objet : <strong data-pv-subject>—</strong></div></div>
		<div class="btn-group btn-group-sm" role="group" aria-label="Largeur d’affichage">
			<button type="button" class="btn btn-outline-primary active" data-pv-width="100%"><i class="bi bi-display me-1"></i>Bureau</button>
			<button type="button" class="btn btn-outline-primary" data-pv-width="390px"><i class="bi bi-phone me-1"></i>Mobile</button>
		</div>
		<button type="button" class="btn-close ms-2" data-bs-dismiss="modal" aria-label="Fermer"></button>
	</div>
	<div class="modal-body bg-body-tertiary">
		<div class="alert alert-warning small d-none" data-pv-unknown></div>
		<div class="mx-auto" style="max-width:100%;transition:width .2s" data-pv-frame-wrap><iframe title="Rendu de l’e-mail" sandbox="" class="w-100 border rounded bg-white" style="height:70vh" data-pv-frame></iframe></div>
		<p class="small text-secondary mt-2 mb-0"><i class="bi bi-info-circle me-1"></i>Données fictives : <?= e(implode(' · ', array_map(fn($k) => '{{' . $k . '}} = ' . $vars[$k][1], ['user_name', 'otp_code', 'alert_reference']))) ?>…</p>
	</div>
</div></div></div>

<!-- Modale : suppression sécurisée -->
<div class="modal fade" id="mDelete" tabindex="-1" aria-labelledby="mDeleteTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><form class="modal-content" method="post">
	<?= csrf_field() ?><input type="hidden" name="do" value="delete"><input type="hidden" name="id" data-del-input-id>
	<div class="modal-header border-0"><h5 class="modal-title text-danger" id="mDeleteTitle"><i class="bi bi-exclamation-triangle me-2"></i>Supprimer ce template ?</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
	<div class="modal-body pt-0">
		<p>Vous allez supprimer <strong data-del-label></strong>. Cette action est définitive.</p>
		<div class="alert alert-danger small d-none" data-del-system-warn>
			<strong>Template système.</strong> La plateforme l’utilise pour des e-mails critiques (réinitialisation de mot de passe, codes, alertes…). Sans lui, ces envois échoueront.
			<label class="form-label mt-2 mb-1" for="delSlug">Pour confirmer, saisissez son slug : <code data-del-slug></code></label>
			<input class="form-control form-control-sm font-monospace" id="delSlug" name="confirm_slug" autocomplete="off">
		</div>
	</div>
	<div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button class="btn btn-danger" data-del-submit><i class="bi bi-trash me-1"></i>Supprimer définitivement</button></div>
</form></div></div>
<?php
$adminScripts = '<script src="' . BASE . 'assets/vendor/quill/quill.js"></script>' . <<<'JS'
<script>
(function () {
  var form = document.getElementById('tplForm'), modal = document.getElementById('mTpl');
  var html = document.getElementById('tHtml'), mode = 'visual', lastTarget = null, slugTouched = false;
  var csrf = form.querySelector('[name=_csrf]').value;
  var quill = new Quill('#tEditor', { theme: 'snow', placeholder: 'Rédigez votre message…', modules: { toolbar: [[{ header: [1, 2, 3, false] }], ['bold', 'italic', 'underline'], [{ color: [] }], [{ list: 'ordered' }, { list: 'bullet' }], [{ align: [] }], ['link', 'image'], ['clean']] } });
  var getBody = function () { return mode === 'html' ? html.value : (quill.getText().trim() ? quill.root.innerHTML : ''); };
  var setBody = function (h) { quill.setContents([]); quill.clipboard.dangerouslyPasteHTML(h || ''); html.value = h || ''; };
  var slugify = function (s) { return s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 60); };
  var setMode = function (m) {
    if (m === mode) return;
    if (m === 'html') html.value = getBody(); else setBody(html.value);
    mode = m;
    document.getElementById('tEditor').classList.toggle('d-none', m === 'html'); quill.getModule('toolbar').container.classList.toggle('d-none', m === 'html');
    html.classList.toggle('d-none', m !== 'html');
    form.querySelectorAll('[data-mode]').forEach(function (b) { var on = b.dataset.mode === m; b.classList.toggle('active', on); b.setAttribute('aria-pressed', on); });
  };
  form.querySelectorAll('[data-mode]').forEach(function (b) { b.addEventListener('click', function () { setMode(b.dataset.mode); }); });

  /* Ouverture : nouveau ou modification (données de la ligne) */
  modal.addEventListener('show.bs.modal', function (ev) {
    var btn = ev.relatedTarget, t = {}; try { t = btn && btn.dataset.tpl ? JSON.parse(btn.dataset.tpl) : {}; } catch (e) {}
    form.reset(); form.classList.remove('was-validated'); setMode('visual'); slugTouched = !!t.id; lastTarget = null;
    ['id', 'name', 'slug', 'subject', 'preheader', 'category'].forEach(function (k) { if (form.elements[k]) form.elements[k].value = t[k] || (k === 'category' ? 'Autre' : ''); });
    form.elements.active.checked = t.id ? !!t.active : true;
    var locked = !!t.system; form.elements.slug.readOnly = locked;
    form.querySelector('[data-slug-help]').textContent = locked ? 'Template système : le slug est verrouillé car le code de la plateforme y fait référence.' : 'Minuscules, chiffres et tirets. Identifiant utilisé par le code pour envoyer cet e-mail.';
    document.getElementById('mTplTitle').textContent = t.id ? 'Modifier : ' + t.name : 'Nouveau template';
    setBody(t.body || ''); form.querySelector('[data-test-result]').textContent = '';
    form.querySelector('[data-body-error]').classList.add('d-none');
    if (btn && btn.hasAttribute('data-focus-test')) modal.addEventListener('shown.bs.modal', function f() { document.getElementById('tTestTo').focus(); modal.removeEventListener('shown.bs.modal', f); });
  });
  form.elements.name.addEventListener('input', function () { if (!slugTouched && !form.elements.slug.readOnly) form.elements.slug.value = slugify(this.value); });
  form.elements.slug.addEventListener('input', function () { slugTouched = true; this.value = this.value.toLowerCase().replace(/[^a-z0-9-]/g, '-'); });

  /* Guide des variables : insertion à l'emplacement du curseur */
  form.querySelectorAll('[data-var-target]').forEach(function (i) { i.addEventListener('focus', function () { lastTarget = i; }); });
  quill.on('selection-change', function (r) { if (r) lastTarget = null; });
  html.addEventListener('focus', function () { lastTarget = html; });
  form.querySelector('[data-var-list]').addEventListener('click', function (e) {
    var b = e.target.closest('[data-var]'); if (!b) return;
    var tag = '{{' + b.dataset.var + '}}';
    if (lastTarget) {
      var s = lastTarget.selectionStart || lastTarget.value.length, en = lastTarget.selectionEnd || s;
      lastTarget.value = lastTarget.value.slice(0, s) + tag + lastTarget.value.slice(en); lastTarget.focus(); lastTarget.setSelectionRange(s + tag.length, s + tag.length);
    } else if (mode === 'visual') {
      var r = quill.getSelection(true); quill.insertText(r ? r.index : quill.getLength() - 1, tag, 'user'); quill.setSelection((r ? r.index : quill.getLength()) + tag.length);
    }
  });
  form.querySelector('[data-var-filter]').addEventListener('input', function () {
    var v = this.value.toLowerCase(); form.querySelectorAll('[data-var]').forEach(function (b) { b.hidden = v && b.textContent.toLowerCase().indexOf(v) < 0; });
  });

  /* Enregistrement : validation côté client */
  form.addEventListener('submit', function (e) {
    var body = getBody(), tmp = document.createElement('div'); tmp.innerHTML = body;
    var bodyOk = tmp.textContent.trim().length >= 10;
    form.querySelector('[data-body-error]').classList.toggle('d-none', bodyOk);
    form.elements.body.value = body;
    if (!form.checkValidity() || !bodyOk) { e.preventDefault(); form.classList.add('was-validated'); (form.querySelector(':invalid') || document.getElementById('tEditor')).focus(); }
  });

  var post = function (data) {
    var fd = new FormData(); fd.append('_csrf', csrf); fd.append('ajax', '1');
    Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
    return fetch('emails.php', { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
  };
  var draft = function () { return { subject: form.elements.subject.value, preheader: form.elements.preheader.value, body: getBody(), slug: form.elements.slug.value }; };

  /* Prévisualisation */
  var pv = document.getElementById('mPreview'), frame = pv.querySelector('[data-pv-frame]');
  var showPreview = function (title, subject, src, srcdoc, unknown) {
    document.getElementById('mPreviewTitle').textContent = 'Prévisualisation : ' + title;
    pv.querySelector('[data-pv-subject]').textContent = subject;
    var w = pv.querySelector('[data-pv-unknown]'); w.classList.toggle('d-none', !(unknown && unknown.length));
    if (unknown && unknown.length) w.textContent = 'Variables inconnues (resteront telles quelles) : ' + unknown.map(function (u) { return '{{' + u + '}}'; }).join(', ');
    if (srcdoc !== null) { frame.removeAttribute('src'); frame.srcdoc = srcdoc; } else { frame.removeAttribute('srcdoc'); frame.src = src; }
  };
  form.querySelector('[data-live-preview]').addEventListener('click', function () {
    post(Object.assign({ do: 'preview' }, draft())).then(function (d) {
      showPreview(form.elements.name.value || 'brouillon', d.subject, null, d.html, d.unknown);
      bootstrap.Modal.getOrCreateInstance(pv).show();
    });
  });
  pv.addEventListener('show.bs.modal', function (ev) {
    var b = ev.relatedTarget; if (!b || !b.dataset.emPreview) return;
    var row = b.closest('tr'), tpl = JSON.parse(row.querySelector('[data-tpl]').dataset.tpl);
    post({ do: 'preview', subject: tpl.subject, preheader: tpl.preheader || '', body: tpl.body }).then(function (d) { showPreview(tpl.name, d.subject, null, d.html, d.unknown); });
  });
  pv.querySelectorAll('[data-pv-width]').forEach(function (b) { b.addEventListener('click', function () {
    pv.querySelectorAll('[data-pv-width]').forEach(function (x) { x.classList.toggle('active', x === b); });
    pv.querySelector('[data-pv-frame-wrap]').style.width = b.dataset.pvWidth;
  }); });

  /* Envoi de test */
  form.querySelector('[data-send-test]').addEventListener('click', function () {
    var out = form.querySelector('[data-test-result]'), btn = this, to = document.getElementById('tTestTo').value.trim();
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(to)) { out.className = 'small mt-2 text-danger'; out.textContent = 'Adresse e-mail invalide.'; return; }
    btn.disabled = true; out.className = 'small mt-2 text-secondary'; out.textContent = 'Envoi en cours…';
    post(Object.assign({ do: 'test', to: to }, draft())).then(function (d) {
      out.className = 'small mt-2 ' + (d.ok ? 'text-success' : 'text-danger'); out.textContent = d.ok ? d.message : d.error;
      if (window.XM && XM.toast) XM.toast(d.ok ? 'Test envoyé' : 'Échec de l’envoi');
    }).catch(function () { out.className = 'small mt-2 text-danger'; out.textContent = 'Erreur réseau.'; }).finally(function () { btn.disabled = false; });
  });

  /* Suppression sécurisée */
  var del = document.getElementById('mDelete'), delSlug = document.getElementById('delSlug'), expected = '';
  del.addEventListener('show.bs.modal', function (ev) {
    var b = ev.relatedTarget, sys = b.dataset.delSystem === '1'; expected = sys ? b.dataset.delSlug : '';
    del.querySelector('[data-del-input-id]').value = b.dataset.delId; del.querySelector('[data-del-label]').textContent = '« ' + b.dataset.delName + ' »';
    del.querySelector('[data-del-slug]').textContent = b.dataset.delSlug; del.querySelector('[data-del-system-warn]').classList.toggle('d-none', !sys);
    delSlug.value = ''; delSlug.required = sys; del.querySelector('[data-del-submit]').disabled = sys;
  });
  delSlug.addEventListener('input', function () { del.querySelector('[data-del-submit]').disabled = expected !== '' && this.value.trim() !== expected; });

  /* Recherche instantanée sur la page affichée (la recherche serveur couvre toutes les pages) */
  var s = document.querySelector('[data-em-search]');
  s.addEventListener('input', function () {
    var v = this.value.trim().toLowerCase(), n = 0;
    document.querySelectorAll('[data-em-text]').forEach(function (r) { var ok = !v || r.dataset.emText.indexOf(v) > -1; r.classList.toggle('d-none', !ok); if (ok) n++; });
    document.querySelector('[data-em-empty]').classList.toggle('d-none', n > 0 || !document.querySelector('[data-em-text]'));
  });
})();
</script>
JS;
include __DIR__ . '/../partials/admin-bottom.php';
