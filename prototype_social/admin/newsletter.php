<?php
require __DIR__ . '/../inc/bootstrap.php'; require_admin(); require_once __DIR__ . '/../inc/x_admin_sys.php'; require_once __DIR__ . '/../inc/x_newsletter.php';
$pageTitle = 'Newsletter'; $adminPage = 'newsletter';
$tab = in_array($_GET['tab'] ?? '', ['abonnes', 'campagnes'], true) ? $_GET['tab'] : 'apercu';
$ST = nl_statuses(); $INT = nl_interests();
$campVars = ['user_first_name' => 'Prénom', 'user_name' => 'Nom complet', 'user_email' => 'E-mail', 'site_name' => 'Nom de la plateforme', 'current_year' => 'Année', 'login_link' => 'Lien de connexion'];

function nl_json($d, $code = 200) { http_response_code($code); header('Content-Type: application/json; charset=utf-8'); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
function nl_back($type, $msg, $tab) { xa_back($type, $msg, 'tab=' . $tab); }
function nl_tags_in($v) { return array_values(array_unique(array_filter(array_map(fn($t) => mb_strtolower(trim($t)), is_array($v) ? $v : explode(',', (string)$v)), fn($t) => $t !== '' && mb_strlen($t) <= 30))); }
/** Filtres de la liste des abonné·es (GET). */
function nl_filtered(array $f) {
    return array_values(array_filter(store_all('newsletter_subscribers'), fn($s) => ($f['st'] === '' || $s['status'] === $f['st'])
        && ($f['tag'] === '' || in_array($f['tag'], $s['tags'] ?? [], true)) && ($f['int'] === '' || in_array($f['int'], $s['interests'] ?? [], true))
        && ($f['from'] === '' || substr($s['created'], 0, 10) >= $f['from']) && ($f['to'] === '' || substr($s['created'], 0, 10) <= $f['to'])
        && ($f['q'] === '' || mb_stripos($s['email'] . ' ' . ($s['name'] ?? ''), $f['q']) !== false)));
}
/** Abonné·e fictif·ve pour l'aperçu et le test. */
function nl_fake_sub($email = 'awa@exemple.bj') { return ['id' => 0, 'email' => $email, 'name' => 'Awa Houngbédji', 'token' => str_repeat('0', 32)]; }
function nl_draft_from_post() {
    return ['id' => 0, 'subject' => xa_post('subject'), 'preheader' => xa_post('preheader'), 'body' => x_mail_sanitize($_POST['body'] ?? ''), 'from_name' => xa_post('from_name'), 'reply_to' => xa_post('reply_to')];
}
$F = ['q' => trim($_GET['q'] ?? ''), 'st' => array_key_exists($_GET['st'] ?? '', $ST) ? $_GET['st'] : '', 'tag' => $_GET['tag'] ?? '', 'int' => $_GET['int'] ?? '', 'from' => $_GET['from'] ?? '', 'to' => $_GET['to'] ?? ''];

if (($_GET['export'] ?? '') === 'csv') {
    xa_csv('xomiton-newsletter-abonnes-' . date('Ymd') . '.csv', ['E-mail', 'Nom', 'Statut', 'Tags', 'Centres d’intérêt', 'Inscription', 'Confirmation', 'Source'],
        array_map(fn($s) => [$s['email'], $s['name'] ?? '', $ST[$s['status']][0] ?? $s['status'], implode(', ', $s['tags'] ?? []), implode(', ', $s['interests'] ?? []), $s['created'], $s['confirmed_at'] ?? '', $s['source'] ?? ''], nl_filtered($F)));
}
if (($_GET['export'] ?? '') === 'modele') {
    xa_csv('modele-import-newsletter.csv', ['E-mail', 'Nom', 'Tags', 'Centres d’intérêt'], [['exemple@mail.bj', 'Prénom NOM', 'partenaire, presse', 'Santé, Droits']]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ajax = !empty($_POST['ajax']);
    if (!csrf_ok()) { if ($ajax) nl_json(['ok' => false, 'error' => 'Session expirée, rechargez la page.'], 419); xa_guard(); }
    $do = $_POST['do'] ?? '';

    /* ----- Abonné·es ----- */
    if ($do === 'sub_save') {
        $id = xa_post('id'); $email = strtolower(xa_post('email')); $status = xa_post('status');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) nl_back('danger', 'Adresse e-mail invalide.', 'abonnes');
        if (!isset($ST[$status])) nl_back('danger', 'Statut invalide.', 'abonnes');
        $dup = nl_find_by_email($email); if ($dup && (string)$dup['id'] !== $id) nl_back('danger', 'Cette adresse est déjà inscrite.', 'abonnes');
        $row = ['email' => $email, 'name' => mb_substr(xa_post('name'), 0, 80), 'status' => $status, 'tags' => nl_tags_in($_POST['tags'] ?? ''), 'interests' => array_values(array_intersect((array)($_POST['interests'] ?? []), $INT))];
        if ($id !== '') { $old = store_find('newsletter_subscribers', $id); if (!$old) nl_back('danger', 'Abonné·e introuvable.', 'abonnes');
            if ($status === 'actif' && !$old['confirmed_at']) $row['confirmed_at'] = date('Y-m-d H:i:s');
            if ($status === 'desabonne' && $old['status'] !== 'desabonne') $row['unsub_at'] = date('Y-m-d H:i:s');
            store_update('newsletter_subscribers', $id, $row); x_log('Newsletter', 'Abonné·e modifié·e : ' . $email); nl_back('success', 'Abonné·e mis·e à jour.', 'abonnes'); }
        store_append('newsletter_subscribers', $row + ['source' => 'admin', 'created' => date('Y-m-d H:i:s'), 'confirmed_at' => $status === 'actif' ? date('Y-m-d H:i:s') : null, 'token' => nl_token(), 'consent' => 'Ajout manuel par ' . x_user()['email']]);
        x_log('Newsletter', 'Abonné·e ajouté·e : ' . $email); nl_back('success', 'Abonné·e ajouté·e.', 'abonnes');
    }
    if ($do === 'sub_delete') { $s = store_find('newsletter_subscribers', xa_post('id')); store_delete('newsletter_subscribers', xa_post('id')); x_log('Newsletter', 'Abonné·e supprimé·e (RGPD) : ' . ($s['email'] ?? '')); nl_back('success', 'Abonné·e supprimé·e définitivement.', 'abonnes'); }
    if ($do === 'sub_bulk') {
        $ids = array_map('strval', (array)($_POST['ids'] ?? [])); $op = xa_post('op'); $n = 0;
        if (!$ids) nl_back('warning', 'Sélectionnez au moins un·e abonné·e.', 'abonnes');
        foreach ($ids as $id) { $s = store_find('newsletter_subscribers', $id); if (!$s) continue; $n++;
            if ($op === 'delete') store_delete('newsletter_subscribers', $id);
            elseif (isset($ST[$op])) store_update('newsletter_subscribers', $id, ['status' => $op] + ($op === 'desabonne' ? ['unsub_at' => date('Y-m-d H:i:s')] : []));
            elseif ($op === 'tag' && ($t = nl_tags_in(xa_post('tag')))) store_update('newsletter_subscribers', $id, ['tags' => array_values(array_unique(array_merge($s['tags'] ?? [], $t)))]); }
        x_log('Newsletter', 'Action groupée « ' . $op . ' » sur ' . $n . ' abonné·es'); nl_back('success', 'Action appliquée à ' . $n . ' abonné·e(s).', 'abonnes');
    }
    if ($do === 'sub_import') {
        $f = $_FILES['csv']['tmp_name'] ?? ''; if (!$f || !is_uploaded_file($f)) nl_back('danger', 'Sélectionnez un fichier CSV.', 'abonnes');
        if (empty($_POST['consent'])) nl_back('danger', 'Attestez que les personnes importées ont donné leur consentement.', 'abonnes');
        $optin = !empty($_POST['optin']);
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', file_get_contents($f)); $first = strtok($raw, "\n");
        $delim = substr_count($first, ';') >= substr_count($first, ',') ? ';' : ',';
        $lines = array_map(fn($l) => str_getcsv($l, $delim), preg_split('/\r\n|\n|\r/', trim($raw)));
        if ($lines && !filter_var(trim($lines[0][0] ?? ''), FILTER_VALIDATE_EMAIL)) array_shift($lines);
        $ok = $dup = $ko = 0; $extraTags = nl_tags_in(xa_post('tag'));
        foreach ($lines as $c) {
            $email = strtolower(trim($c[0] ?? ''));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $ko++; continue; }
            if (nl_find_by_email($email)) { $dup++; continue; }
            $s = store_append('newsletter_subscribers', ['email' => $email, 'name' => mb_substr(trim($c[1] ?? ''), 0, 80), 'status' => $optin ? 'en_attente' : 'actif', 'tags' => array_values(array_unique(array_merge(nl_tags_in($c[2] ?? ''), $extraTags))),
                'interests' => array_values(array_intersect(array_map('trim', explode(',', $c[3] ?? '')), $INT)), 'source' => 'import', 'created' => date('Y-m-d H:i:s'), 'confirmed_at' => $optin ? null : date('Y-m-d H:i:s'), 'token' => nl_token(), 'consent' => 'Import CSV attesté par ' . x_user()['email']]);
            if ($optin) x_mail_send_template('newsletter-confirmation', $email, ['activation_link' => x_site_url('newsletter.php?a=confirm&t=' . $s['token']), 'unsubscribe_link' => nl_unsub_url($s)], ['context' => 'newsletter-optin']);
            $ok++;
        }
        x_log('Newsletter', "Import CSV : $ok ajouté(s), $dup doublon(s), $ko invalide(s)");
        nl_back($ok ? 'success' : 'warning', "Import terminé : $ok abonné·e(s) ajouté·e(s), $dup doublon(s) ignoré(s), $ko ligne(s) invalide(s)." . ($optin && $ok ? ' Un e-mail de confirmation leur a été envoyé.' : ''), 'abonnes');
    }

    /* ----- Campagnes ----- */
    if ($do === 'camp_preview') { [$subj, $html] = nl_build_email(nl_draft_from_post(), nl_fake_sub(), false); nl_json(['ok' => true, 'subject' => $subj, 'html' => str_replace(x_site_url('assets/'), BASE . 'assets/', $html)]); }
    if ($do === 'camp_test') {
        $to = xa_post('to'); if (!filter_var($to, FILTER_VALIDATE_EMAIL)) nl_json(['ok' => false, 'error' => 'Adresse e-mail de test invalide.'], 422);
        $c = nl_draft_from_post(); if (mb_strlen(trim(strip_tags($c['body']))) < 10) nl_json(['ok' => false, 'error' => 'Le corps du message est vide.'], 422);
        [$subj, $html] = nl_build_email($c, nl_fake_sub($to), false);
        $r = x_mail_send($to, '[TEST] ' . $subj, $html, ['from_name' => $c['from_name'] ?: 'Xomiton', 'reply_to' => $c['reply_to'], 'template' => 'newsletter', 'context' => 'test']);
        x_log('Newsletter', 'Envoi de test → ' . $to);
        nl_json($r['ok'] ? ['ok' => true, 'message' => $r['transport'] === 'smtp' ? 'Test envoyé à ' . $to . '.' : 'Aucun SMTP configuré : test déposé dans la boîte d’envoi locale.'] : ['ok' => false, 'error' => $r['error']], $r['ok'] ? 200 : 502);
    }
    if ($do === 'camp_save') {
        $id = xa_post('id'); $old = $id !== '' ? store_find('newsletter_campaigns', $id) : null;
        if ($id !== '' && (!$old || !in_array($old['status'], ['brouillon', 'planifiee'], true))) nl_back('danger', 'Cette campagne n’est plus modifiable.', 'campagnes');
        $c = nl_draft_from_post(); unset($c['id']); $mode = xa_post('mode', 'draft');
        if (mb_strlen($c['subject']) < 3 || mb_strlen($c['subject']) > 150) nl_back('danger', 'Objet : 3 à 150 caractères.', 'campagnes');
        if (mb_strlen($c['from_name']) < 2 || mb_strlen($c['from_name']) > 60) nl_back('danger', 'Nom de l’expéditeur : 2 à 60 caractères.', 'campagnes');
        if (!filter_var($c['reply_to'], FILTER_VALIDATE_EMAIL)) nl_back('danger', 'E-mail de réponse invalide.', 'campagnes');
        if (mb_strlen(trim(strip_tags($c['body']))) < 10) nl_back('danger', 'Le corps du message doit contenir au moins 10 caractères.', 'campagnes');
        $c['segment'] = array_filter(['tags' => nl_tags_in($_POST['seg_tags'] ?? []), 'interests' => array_values(array_intersect((array)($_POST['seg_interests'] ?? []), $INT)),
            'since' => preg_match('/^\d{4}-\d{2}-\d{2}$/', xa_post('seg_since')) ? xa_post('seg_since') : '', 'until' => preg_match('/^\d{4}-\d{2}-\d{2}$/', xa_post('seg_until')) ? xa_post('seg_until') : '']);
        $n = count(nl_segment($c['segment']));
        if ($mode !== 'draft' && !$n) nl_back('danger', 'Aucun·e abonné·e actif·ve ne correspond à ce segment : élargissez les critères.', 'campagnes');
        $c['status'] = 'brouillon'; $c['scheduled_at'] = null;
        if ($mode === 'schedule') { $t = strtotime(xa_post('scheduled_at')); if (!$t || $t < time() + 60) nl_back('danger', 'La date d’envoi programmée doit être dans le futur.', 'campagnes'); $c['status'] = 'planifiee'; $c['scheduled_at'] = date('Y-m-d H:i:00', $t); }
        if ($mode === 'now') { $c['status'] = 'planifiee'; $c['scheduled_at'] = date('Y-m-d H:i:s'); }
        $c['updated'] = date('Y-m-d H:i:s');
        if ($old) { store_update('newsletter_campaigns', $old['id'], $c); $cid = $old['id']; }
        else { $cid = store_append('newsletter_campaigns', $c + ['queue' => [], 'recipients' => [], 'bounces' => [], 'opens' => new stdClass(), 'clicks' => new stdClass(), 'unsubs' => [], 'created' => date('Y-m-d H:i:s'), 'created_by' => x_user()['email']])['id']; }
        x_log('Newsletter', 'Campagne ' . ['draft' => 'enregistrée', 'schedule' => 'programmée', 'now' => 'lancée'][$mode] . ' : ' . $c['subject']);
        if ($mode === 'now') { nl_process_due(); $c = store_find('newsletter_campaigns', $cid);
            nl_back('success', $c['status'] === 'envoyee' ? 'Campagne envoyée à ' . count($c['recipients']) . ' abonné·e(s).' : 'Envoi lancé : ' . count($c['recipients']) . ' e-mail(s) envoyé(s), la suite part par lots chaque minute.', 'campagnes'); }
        nl_back('success', $mode === 'schedule' ? 'Campagne programmée le ' . date('d/m/Y à H:i', strtotime($c['scheduled_at'])) . ' pour ' . $n . ' abonné·e(s).' : 'Brouillon enregistré.', 'campagnes');
    }
    if (in_array($do, ['camp_cancel', 'camp_delete', 'camp_duplicate'], true)) {
        $c = store_find('newsletter_campaigns', xa_post('id')); if (!$c) nl_back('danger', 'Campagne introuvable.', 'campagnes');
        if ($do === 'camp_cancel' && $c['status'] === 'planifiee') { store_update('newsletter_campaigns', $c['id'], ['status' => 'brouillon', 'scheduled_at' => null]); nl_back('success', 'Programmation annulée : la campagne repasse en brouillon.', 'campagnes'); }
        if ($do === 'camp_delete' && $c['status'] !== 'en_cours') { store_delete('newsletter_campaigns', $c['id']); x_log('Newsletter', 'Campagne supprimée : ' . $c['subject']); nl_back('success', 'Campagne supprimée.', 'campagnes'); }
        if ($do === 'camp_duplicate') { store_append('newsletter_campaigns', ['subject' => $c['subject'] . ' (copie)', 'from_name' => $c['from_name'], 'reply_to' => $c['reply_to'], 'preheader' => $c['preheader'] ?? '', 'body' => $c['body'], 'segment' => $c['segment'] ?? [], 'status' => 'brouillon', 'scheduled_at' => null,
            'queue' => [], 'recipients' => [], 'bounces' => [], 'opens' => new stdClass(), 'clicks' => new stdClass(), 'unsubs' => [], 'created' => date('Y-m-d H:i:s'), 'updated' => date('Y-m-d H:i:s'), 'created_by' => x_user()['email']]); nl_back('success', 'Copie créée en brouillon.', 'campagnes'); }
        nl_back('danger', 'Action impossible dans l’état actuel de la campagne.', 'campagnes');
    }
    if ($do === 'process') { $n = nl_process_due(); nl_back('success', $n ? $n . ' campagne(s) traitée(s).' : 'Aucun envoi en attente.', 'campagnes'); }
    xa_back('danger', 'Action inconnue.');
}

/* Envoi de secours si la tâche cron n'a pas tourné */
nl_process_due();

$subs = store_all('newsletter_subscribers'); $camps = store_all('newsletter_campaigns');
usort($camps, fn($a, $b) => strcmp($b['sent_at'] ?? $b['scheduled_at'] ?? $b['updated'], $a['sent_at'] ?? $a['scheduled_at'] ?? $a['updated']));
$cnt = array_count_values(array_column($subs, 'status'));
$sent = array_values(array_filter($camps, fn($c) => $c['status'] === 'envoyee'));
$agg = ['sent' => 0, 'delivered' => 0, 'opens' => 0, 'clickers' => 0, 'unsubs' => 0, 'bounces' => 0];
foreach ($sent as $c) { $s = nl_stats($c); foreach ($agg as $k => $v) $agg[$k] += $s[$k]; }
$pct = fn($a, $b) => $b ? round($a * 100 / $b, 1) : 0;
$kOpen = $pct($agg['opens'], $agg['delivered']); $kClick = $pct($agg['clickers'], $agg['delivered']); $kUnsub = $pct($agg['unsubs'], $agg['delivered']); $kBounce = $pct($agg['bounces'], $agg['sent']);
$tagsAll = nl_tags();
$csrfTok = csrf_token();

$adminHead = '<link rel="stylesheet" href="' . BASE . 'assets/vendor/quill/quill.snow.css">';
$adminActions = '<button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#mCamp"><i class="bi bi-plus-lg me-1"></i>Nouvelle campagne</button>';
include __DIR__ . '/../partials/admin-top.php';
?>
<div class="row g-3 mb-3">
	<div class="col-6 col-md-4 col-xl"><div class="card xm-stat h-100"><div class="card-body py-3"><div class="text-secondary small">Abonné·es actifs</div><div class="kpi-value"><?= (int)($cnt['actif'] ?? 0) ?></div><div class="small text-secondary"><?= (int)($cnt['en_attente'] ?? 0) ?> en attente de confirmation</div></div></div></div>
	<div class="col-6 col-md-4 col-xl"><div class="card xm-stat success h-100"><div class="card-body py-3"><div class="text-secondary small">Taux d’ouverture</div><div class="kpi-value"><?= $kOpen ?> %</div><div class="small text-secondary"><?= $agg['opens'] ?> ouvertures uniques</div></div></div></div>
	<div class="col-6 col-md-4 col-xl"><div class="card xm-stat violet h-100"><div class="card-body py-3"><div class="text-secondary small">Taux de clic</div><div class="kpi-value"><?= $kClick ?> %</div><div class="small text-secondary"><?= $agg['clickers'] ?> personnes ont cliqué</div></div></div></div>
	<div class="col-6 col-md-6 col-xl"><div class="card xm-stat warning h-100"><div class="card-body py-3"><div class="text-secondary small">Taux de désabonnement</div><div class="kpi-value"><?= $kUnsub ?> %</div><div class="small text-secondary"><?= $agg['unsubs'] ?> via les campagnes</div></div></div></div>
	<div class="col-12 col-md-6 col-xl"><div class="card xm-stat danger h-100"><div class="card-body py-3"><div class="text-secondary small">Taux de rebond</div><div class="kpi-value"><?= $kBounce ?> %</div><div class="small text-secondary"><?= $agg['bounces'] ?> adresses inexistantes</div></div></div></div>
</div>

<ul class="nav nav-tabs mb-4" role="tablist">
	<?php foreach (['apercu' => ['bi-graph-up', 'Statistiques'], 'abonnes' => ['bi-people', 'Abonné·es (' . count($subs) . ')'], 'campagnes' => ['bi-envelope-paper', 'Campagnes (' . count($camps) . ')']] as $k => $t): ?>
	<li class="nav-item" role="presentation"><a class="nav-link <?= $tab === $k ? 'active' : '' ?>" href="?tab=<?= $k ?>" <?= $tab === $k ? 'aria-current="page"' : '' ?>><i class="bi <?= $t[0] ?> me-1"></i><?= e($t[1]) ?></a></li>
	<?php endforeach; ?>
</ul>

<?php if ($tab === 'apercu'):
    $months = []; for ($i = 11; $i >= 0; $i--) { $m = date('Y-m', strtotime("first day of -$i month")); $months[$m] = ['in' => 0, 'out' => 0]; }
    foreach ($subs as $s) { $m = substr($s['created'], 0, 7); if (isset($months[$m])) $months[$m]['in']++; $u = substr($s['unsub_at'] ?? '', 0, 7); if (isset($months[$u])) $months[$u]['out']++; }
    $links = []; foreach ($sent as $c) foreach ((array)$c['clicks'] as $urls) foreach ((array)$urls as $u) $links[$u] = ($links[$u] ?? 0) + 1; arsort($links);
    $ints = array_fill_keys($INT, 0); foreach ($subs as $s) if ($s['status'] === 'actif') foreach ($s['interests'] ?? [] as $x) if (isset($ints[$x])) $ints[$x]++;
?>
<div class="row g-4 mb-4">
	<div class="col-xl-8"><div class="card h-100"><div class="card-header"><h2 class="h6 mb-0">Inscriptions et désinscriptions (12 mois)</h2></div><div class="card-body" style="height:280px"><canvas id="nlGrowth" aria-label="Inscriptions et désinscriptions par mois"></canvas></div></div></div>
	<div class="col-xl-4"><div class="card h-100"><div class="card-header"><h2 class="h6 mb-0">Abonné·es par statut</h2></div><div class="card-body" style="height:280px"><canvas id="nlStatus" aria-label="Répartition des abonné·es par statut"></canvas></div></div></div>
	<div class="col-xl-8"><div class="card h-100"><div class="card-header"><h2 class="h6 mb-0">Performance des campagnes envoyées</h2></div>
		<div class="xm-table-wrap"><table class="table table-hover align-middle mb-0"><thead><tr><th>Campagne</th><th class="text-end">Envois</th><th class="text-end">Ouverture</th><th class="text-end">Clic</th><th class="text-end">Désabo.</th><th class="text-end">Rebond</th></tr></thead><tbody>
		<?php foreach ($sent as $c): $s = nl_stats($c); ?>
			<tr><td><div class="fw-semibold"><?= e($c['subject']) ?></div><div class="small text-secondary"><?= e(date('d/m/Y', strtotime($c['sent_at']))) ?></div></td><td class="text-end"><?= $s['sent'] ?></td>
			<td class="text-end"><?= $s['open_rate'] ?> %<div class="progress mt-1" style="height:4px"><div class="progress-bar bg-success" style="width:<?= $s['open_rate'] ?>%"></div></div></td>
			<td class="text-end"><?= $s['click_rate'] ?> %<div class="progress mt-1" style="height:4px"><div class="progress-bar" style="width:<?= $s['click_rate'] ?>%"></div></div></td>
			<td class="text-end"><?= $s['unsub_rate'] ?> %</td><td class="text-end"><?= $s['bounce_rate'] ?> %</td></tr>
		<?php endforeach; if (!$sent): ?><tr><td colspan="6" class="text-center text-secondary py-4">Aucune campagne envoyée pour l’instant.</td></tr><?php endif; ?>
		</tbody></table></div></div></div>
	<div class="col-xl-4"><div class="card h-100"><div class="card-header"><h2 class="h6 mb-0">Liens les plus cliqués</h2></div><ul class="list-group list-group-flush small">
		<?php foreach (array_slice($links, 0, 6, true) as $u => $n): ?><li class="list-group-item d-flex justify-content-between gap-2"><span class="text-truncate" title="<?= e($u) ?>"><?= e(preg_replace('#^https?://#', '', $u)) ?></span><span class="badge bg-primary"><?= $n ?></span></li><?php endforeach; if (!$links): ?><li class="list-group-item text-secondary">Aucun clic enregistré.</li><?php endif; ?>
		</ul><div class="card-body border-top"><h3 class="h6">Centres d’intérêt (actifs)</h3>
		<?php $mx = max(1, max($ints)); foreach ($ints as $k => $v): ?><div class="mb-2"><div class="d-flex justify-content-between small"><span><?= e($k) ?></span><span><?= $v ?></span></div><div class="progress" style="height:6px"><div class="progress-bar" style="width:<?= round($v * 100 / $mx) ?>%"></div></div></div><?php endforeach; ?>
		</div></div></div>
</div>
<?php
    $chart = ['m' => array_map(fn($m) => date('m/y', strtotime($m . '-01')), array_keys($months)), 'in' => array_column($months, 'in'), 'out' => array_column($months, 'out'),
        'st' => array_map(fn($k) => $ST[$k][0], array_keys($ST)), 'stv' => array_map(fn($k) => (int)($cnt[$k] ?? 0), array_keys($ST))];
    $nlChart = '<script>(function(){var d=' . json_encode($chart, JSON_UNESCAPED_UNICODE) . ',C=XM_COLORS;
new Chart(document.getElementById("nlGrowth"),{type:"bar",data:{labels:d.m,datasets:[{label:"Inscriptions",data:d.in,backgroundColor:C[0]},{label:"Désinscriptions",data:d.out,backgroundColor:C[4]}]},options:{responsive:true,maintainAspectRatio:false,scales:{y:{beginAtZero:true,ticks:{precision:0}}}}});
new Chart(document.getElementById("nlStatus"),{type:"doughnut",data:{labels:d.st,datasets:[{data:d.stv,backgroundColor:[C[3],C[4],"#8E88A0",C[1]]}]},options:{responsive:true,maintainAspectRatio:false}});})();</script>';
endif; ?>

<?php if ($tab === 'abonnes'):
    $rows = nl_filtered($F); usort($rows, fn($a, $b) => strcmp($b['created'], $a['created']));
    $per = 20; [, $page] = xa_pages(count($rows), $per, (int)($_GET['p'] ?? 1)); $pageRows = array_slice($rows, ($page - 1) * $per, $per);
    $qs = array_filter($F + ['tab' => 'abonnes']);
?>
<div class="card mb-4">
	<div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
		<h2 class="h6 mb-0">Liste des inscrit·es</h2>
		<div class="d-flex flex-wrap gap-2">
			<button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#mSub"><i class="bi bi-person-plus me-1"></i>Ajouter</button>
			<button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#mImport"><i class="bi bi-upload me-1"></i>Importer CSV</button>
			<a class="btn btn-outline-primary btn-sm" href="?<?= e(http_build_query($qs + ['export' => 'csv'])) ?>"><i class="bi bi-download me-1"></i>Exporter CSV<?= count($rows) !== count($subs) ? ' (' . count($rows) . ')' : '' ?></a>
		</div>
	</div>
	<div class="card-body border-bottom">
		<form class="row g-2" method="get" role="search"><input type="hidden" name="tab" value="abonnes">
			<div class="col-12 col-lg-3"><label class="visually-hidden" for="fq">Rechercher</label><input type="search" class="form-control form-control-sm" id="fq" name="q" value="<?= e($F['q']) ?>" placeholder="E-mail ou nom…"></div>
			<div class="col-6 col-md-3 col-lg-2"><label class="visually-hidden" for="fst">Statut</label><select class="form-select form-select-sm" id="fst" name="st"><option value="">Tous les statuts</option><?php foreach ($ST as $k => $v): ?><option value="<?= $k ?>" <?= $F['st'] === $k ? 'selected' : '' ?>><?= e($v[0]) ?></option><?php endforeach; ?></select></div>
			<div class="col-6 col-md-3 col-lg-2"><label class="visually-hidden" for="ftag">Tag</label><select class="form-select form-select-sm" id="ftag" name="tag"><option value="">Tous les tags</option><?php foreach ($tagsAll as $t): ?><option <?= $F['tag'] === $t ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?></select></div>
			<div class="col-6 col-md-3 col-lg-2"><label class="visually-hidden" for="fint">Centre d’intérêt</label><select class="form-select form-select-sm" id="fint" name="int"><option value="">Tous les intérêts</option><?php foreach ($INT as $t): ?><option <?= $F['int'] === $t ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?></select></div>
			<div class="col-6 col-md-3 col-lg-1"><label class="visually-hidden" for="ffrom">Inscrit·e depuis</label><input type="date" class="form-control form-control-sm" id="ffrom" name="from" value="<?= e($F['from']) ?>" title="Inscrit·e depuis"></div>
			<div class="col-6 col-md-3 col-lg-1"><label class="visually-hidden" for="fto">Jusqu’au</label><input type="date" class="form-control form-control-sm" id="fto" name="to" value="<?= e($F['to']) ?>" title="Jusqu’au"></div>
			<div class="col-6 col-md-3 col-lg-1 d-grid"><button class="btn btn-sm btn-primary-soft">Filtrer</button></div>
		</form>
	</div>
	<form method="post" id="bulkForm"><?= csrf_field() ?><input type="hidden" name="do" value="sub_bulk">
	<div class="px-3 py-2 border-bottom d-flex flex-wrap gap-2 align-items-center small d-none" data-bulk-bar>
		<span><b data-bulk-count>0</b> sélectionné·e(s)</span>
		<select class="form-select form-select-sm w-auto" name="op" aria-label="Action groupée"><option value="actif">Marquer actif</option><option value="desabonne">Désabonner</option><option value="tag">Ajouter le tag…</option><option value="delete">Supprimer définitivement</option></select>
		<input class="form-control form-control-sm w-auto" name="tag" placeholder="tag" aria-label="Tag à ajouter">
		<button class="btn btn-sm btn-primary" data-confirm-bulk>Appliquer</button>
	</div>
	<div class="xm-table-wrap"><table class="table table-hover align-middle mb-0">
		<thead><tr><th style="width:36px"><input class="form-check-input" type="checkbox" data-check-all aria-label="Tout sélectionner"></th><th>Abonné·e</th><th>Inscription</th><th>Statut</th><th class="d-none d-lg-table-cell">Tags · intérêts</th><th class="text-end">Actions</th></tr></thead>
		<tbody>
		<?php foreach ($pageRows as $s): ?>
			<tr>
				<td><input class="form-check-input" type="checkbox" name="ids[]" value="<?= (int)$s['id'] ?>" aria-label="Sélectionner <?= e($s['email']) ?>"></td>
				<td><div class="fw-semibold"><?= e($s['name'] ?: '—') ?></div><div class="small text-secondary"><?= e($s['email']) ?></div></td>
				<td class="small text-nowrap"><?= e(date('d/m/Y', strtotime($s['created']))) ?><div class="text-secondary"><?= e(['footer' => 'Formulaire', 'import' => 'Import CSV', 'admin' => 'Ajout manuel'][$s['source'] ?? ''] ?? '') ?></div></td>
				<td><?= nl_badge($s['status']) ?></td>
				<td class="d-none d-lg-table-cell"><?php foreach ($s['tags'] ?? [] as $t): ?><span class="badge bg-dark-subtle text-dark-emphasis me-1">#<?= e($t) ?></span><?php endforeach; ?><?php foreach ($s['interests'] ?? [] as $t): ?><span class="badge bg-primary-subtle text-primary-emphasis me-1"><?= e($t) ?></span><?php endforeach; ?></td>
				<td class="text-end text-nowrap">
					<button type="button" class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#mSub" data-sub='<?= e(json_encode(['id' => $s['id'], 'email' => $s['email'], 'name' => $s['name'] ?? '', 'status' => $s['status'], 'tags' => implode(', ', $s['tags'] ?? []), 'interests' => $s['interests'] ?? []], JSON_UNESCAPED_UNICODE)) ?>' title="Modifier" aria-label="Modifier <?= e($s['email']) ?>"><i class="bi bi-pencil"></i></button>
					<button type="button" class="btn btn-sm btn-light text-danger" data-bs-toggle="modal" data-bs-target="#mSubDel" data-id="<?= (int)$s['id'] ?>" data-email="<?= e($s['email']) ?>" title="Supprimer" aria-label="Supprimer <?= e($s['email']) ?>"><i class="bi bi-trash"></i></button>
				</td>
			</tr>
		<?php endforeach; if (!$pageRows): ?><tr><td colspan="6" class="text-center text-secondary py-4"><i class="bi bi-people fs-3 d-block mb-1"></i>Aucun·e abonné·e ne correspond aux filtres.</td></tr><?php endif; ?>
		</tbody></table></div>
	</form>
	<?= xa_pagebar(count($rows), $per, $page, $qs) ?>
</div>
<?php endif; ?>

<?php if ($tab === 'campagnes'):
    $cs = ['brouillon' => ['Brouillon', 'secondary'], 'planifiee' => ['Programmée', 'info'], 'en_cours' => ['Envoi en cours', 'warning text-dark'], 'envoyee' => ['Envoyée', 'success']];
?>
<div class="card mb-4">
	<div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
		<h2 class="h6 mb-0">Campagnes</h2>
		<form method="post" class="d-flex gap-2"><?= csrf_field() ?><input type="hidden" name="do" value="process"><button class="btn btn-outline-primary btn-sm" title="Envoie les campagnes arrivées à échéance (normalement fait par la tâche cron chaque minute)"><i class="bi bi-arrow-repeat me-1"></i>Traiter la file d’envoi</button></form>
	</div>
	<div class="xm-table-wrap"><table class="table table-hover align-middle mb-0">
		<thead><tr><th>Campagne</th><th>Statut</th><th>Envoi</th><th class="d-none d-md-table-cell">Segment</th><th class="text-end">Destinataires</th><th class="text-end">Ouv. · Clic</th><th class="text-end">Actions</th></tr></thead><tbody>
		<?php foreach ($camps as $c): $s = nl_stats($c); $est = in_array($c['status'], ['brouillon', 'planifiee'], true) ? count(nl_segment($c['segment'] ?? [])) : $s['sent']; $editable = in_array($c['status'], ['brouillon', 'planifiee'], true);
			$fill = e(json_encode(['id' => $c['id'], 'subject' => $c['subject'], 'from_name' => $c['from_name'], 'reply_to' => $c['reply_to'], 'preheader' => $c['preheader'] ?? '', 'body' => $c['body'], 'segment' => $c['segment'] ?? [], 'status' => $c['status'], 'scheduled_at' => $c['scheduled_at'] ? date('Y-m-d\TH:i', strtotime($c['scheduled_at'])) : ''], JSON_UNESCAPED_UNICODE)); ?>
			<tr>
				<td><div class="fw-semibold"><?= e($c['subject']) ?></div><div class="small text-secondary">De : <?= e($c['from_name']) ?> · réponse : <?= e($c['reply_to']) ?></div></td>
				<td><span class="badge bg-<?= $cs[$c['status']][1] ?>"><?= $cs[$c['status']][0] ?></span><?php if ($c['status'] === 'en_cours'): ?><div class="small text-secondary"><?= count($c['recipients']) ?> / <?= count($c['recipients']) + count($c['queue'] ?? []) ?></div><?php endif; ?></td>
				<td class="small text-nowrap"><?= $c['status'] === 'envoyee' ? e(date('d/m/Y H:i', strtotime($c['sent_at']))) : ($c['scheduled_at'] ? '<i class="bi bi-clock me-1"></i>' . e(date('d/m/Y H:i', strtotime($c['scheduled_at']))) : '<span class="text-secondary">—</span>') ?></td>
				<td class="small d-none d-md-table-cell"><?= e(nl_segment_label($c['segment'] ?? [])) ?></td>
				<td class="text-end"><?= $est ?></td>
				<td class="text-end small text-nowrap"><?= $c['status'] === 'envoyee' ? $s['open_rate'] . ' % · ' . $s['click_rate'] . ' %' : '—' ?></td>
				<td class="text-end text-nowrap">
					<?php if ($c['status'] === 'envoyee'): ?><button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#mStats" data-stats='<?= e(json_encode(['subject' => $c['subject'], 'sent_at' => date('d/m/Y H:i', strtotime($c['sent_at'])), 'segment' => nl_segment_label($c['segment'] ?? []), 's' => $s, 'links' => array_count_values(array_merge([], ...array_map(fn($u) => (array)$u, array_values((array)$c['clicks']))))], JSON_UNESCAPED_UNICODE)) ?>' title="Statistiques" aria-label="Statistiques de <?= e($c['subject']) ?>"><i class="bi bi-bar-chart"></i></button><?php endif; ?>
					<button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#mCamp" data-camp='<?= $fill ?>' title="<?= $editable ? 'Modifier' : 'Voir' ?>" aria-label="<?= $editable ? 'Modifier' : 'Voir' ?> <?= e($c['subject']) ?>"><i class="bi <?= $editable ? 'bi-pencil' : 'bi-eye' ?>"></i></button>
					<form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
						<?php if ($c['status'] === 'planifiee'): ?><button class="btn btn-sm btn-light" name="do" value="camp_cancel" title="Annuler la programmation" aria-label="Annuler la programmation"><i class="bi bi-calendar-x"></i></button><?php endif; ?>
						<button class="btn btn-sm btn-light" name="do" value="camp_duplicate" title="Dupliquer" aria-label="Dupliquer"><i class="bi bi-copy"></i></button>
						<?php if ($c['status'] !== 'en_cours'): ?><button class="btn btn-sm btn-light text-danger" name="do" value="camp_delete" data-confirm-click="Supprimer la campagne « <?= e($c['subject']) ?> » et ses statistiques ?" title="Supprimer" aria-label="Supprimer"><i class="bi bi-trash"></i></button><?php endif; ?>
					</form>
				</td>
			</tr>
		<?php endforeach; if (!$camps): ?><tr><td colspan="7" class="text-center text-secondary py-4">Aucune campagne. Créez la première avec « Nouvelle campagne ».</td></tr><?php endif; ?>
		</tbody></table></div>
	<div class="card-footer small text-secondary"><i class="bi bi-info-circle me-1"></i>Envoi par lots de <?= NL_BATCH ?> e-mails par minute (tâche cron <code>php cron/newsletter.php</code>). Chaque e-mail contient automatiquement un lien de désinscription unique et l’en-tête <code>List-Unsubscribe</code>.</div>
</div>
<?php endif; ?>

<!-- Modale : abonné·e -->
<div class="modal fade" id="mSub" tabindex="-1" aria-labelledby="mSubTitle" aria-hidden="true"><div class="modal-dialog"><form class="modal-content" method="post" data-xm-validate>
	<?= csrf_field() ?><input type="hidden" name="do" value="sub_save"><input type="hidden" name="id">
	<div class="modal-header"><h5 class="modal-title" id="mSubTitle">Ajouter un·e abonné·e</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
	<div class="modal-body row g-3">
		<div class="col-12"><label class="form-label" for="subEmail">E-mail *</label><input class="form-control" id="subEmail" name="email" required data-rule="email"></div>
		<div class="col-md-6"><label class="form-label" for="subName">Nom</label><input class="form-control" id="subName" name="name" maxlength="80"></div>
		<div class="col-md-6"><label class="form-label" for="subStatus">Statut</label><select class="form-select" id="subStatus" name="status"><?php foreach ($ST as $k => $v): ?><option value="<?= $k ?>"><?= e($v[0]) ?></option><?php endforeach; ?></select></div>
		<div class="col-12"><label class="form-label" for="subTags">Tags <span class="small text-secondary">(séparés par des virgules)</span></label><input class="form-control" id="subTags" name="tags" list="tagList" placeholder="partenaire, presse"><datalist id="tagList"><?php foreach ($tagsAll as $t): ?><option value="<?= e($t) ?>"><?php endforeach; ?></datalist></div>
		<fieldset class="col-12"><legend class="form-label fs-6">Centres d’intérêt</legend><?php foreach ($INT as $i => $t): ?><div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="interests[]" value="<?= e($t) ?>" id="si<?= $i ?>"><label class="form-check-label" for="si<?= $i ?>"><?= e($t) ?></label></div><?php endforeach; ?></fieldset>
	</div>
	<div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button class="btn btn-primary">Enregistrer</button></div>
</form></div></div>

<!-- Modale : suppression d'un·e abonné·e -->
<div class="modal fade" id="mSubDel" tabindex="-1" aria-labelledby="mSubDelTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><form class="modal-content" method="post">
	<?= csrf_field() ?><input type="hidden" name="do" value="sub_delete"><input type="hidden" name="id">
	<div class="modal-header border-0"><h5 class="modal-title text-danger" id="mSubDelTitle"><i class="bi bi-exclamation-triangle me-2"></i>Supprimer définitivement ?</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
	<div class="modal-body pt-0"><p class="mb-1">L’adresse <strong data-email></strong> et son historique seront effacés (droit à l’effacement RGPD).</p><p class="small text-secondary mb-0">Pour simplement arrêter les envois, préférez le statut « Désabonné·e ».</p></div>
	<div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button class="btn btn-danger">Supprimer</button></div>
</form></div></div>

<!-- Modale : import CSV -->
<div class="modal fade" id="mImport" tabindex="-1" aria-labelledby="mImportTitle" aria-hidden="true"><div class="modal-dialog"><form class="modal-content" method="post" enctype="multipart/form-data">
	<?= csrf_field() ?><input type="hidden" name="do" value="sub_import">
	<div class="modal-header"><h5 class="modal-title" id="mImportTitle">Importer des abonné·es (CSV)</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
	<div class="modal-body">
		<p class="small text-secondary">Colonnes : E-mail ; Nom ; Tags (séparés par des virgules) ; Centres d’intérêt. Les doublons et adresses invalides sont ignorés. <a href="?export=modele">Télécharger le modèle</a>.</p>
		<label class="visually-hidden" for="impFile">Fichier CSV</label><input type="file" id="impFile" name="csv" accept=".csv,text/csv" class="form-control mb-3" required>
		<label class="form-label small" for="impTag">Ajouter un tag à toutes les lignes (facultatif)</label><input class="form-control form-control-sm mb-3" id="impTag" name="tag" placeholder="salon-2026">
		<div class="form-check"><input class="form-check-input" type="checkbox" name="optin" value="1" id="impOptin" checked><label class="form-check-label small" for="impOptin">Envoyer un e-mail de confirmation (double opt-in, recommandé)</label></div>
		<div class="form-check"><input class="form-check-input" type="checkbox" name="consent" value="1" id="impConsent" required><label class="form-check-label small" for="impConsent">J’atteste que ces personnes ont consenti à recevoir la newsletter (RGPD).</label></div>
	</div>
	<div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button class="btn btn-primary">Importer</button></div>
</form></div></div>

<!-- Modale : campagne -->
<div class="modal fade" id="mCamp" tabindex="-1" aria-labelledby="mCampTitle" aria-hidden="true"><div class="modal-dialog modal-xl modal-fullscreen-lg-down modal-dialog-scrollable"><form class="modal-content" method="post" id="campForm" novalidate>
	<?= csrf_field() ?><input type="hidden" name="do" value="camp_save"><input type="hidden" name="id"><input type="hidden" name="body">
	<div class="modal-header"><h5 class="modal-title" id="mCampTitle">Nouvelle campagne</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
	<div class="modal-body">
		<div class="alert alert-info small d-none" data-camp-readonly><i class="bi bi-lock me-1"></i>Campagne envoyée : lecture seule. Dupliquez-la pour la réutiliser.</div>
		<div class="row g-4">
			<div class="col-lg-8">
				<div class="row g-3">
					<div class="col-12"><label class="form-label" for="cSubject">Objet du mail *</label><input class="form-control" id="cSubject" name="subject" required minlength="3" maxlength="150" data-var-target></div>
					<div class="col-md-6"><label class="form-label" for="cFrom">Nom de l’expéditeur *</label><input class="form-control" id="cFrom" name="from_name" required minlength="2" maxlength="60" value="Xomiton"></div>
					<div class="col-md-6"><label class="form-label" for="cReply">E-mail de réponse *</label><input type="email" class="form-control" id="cReply" name="reply_to" required value="<?= e(setting('contact_email', 'contact@xomiton.bj')) ?>"></div>
					<div class="col-12"><label class="form-label" for="cPre">Texte d’aperçu</label><input class="form-control" id="cPre" name="preheader" maxlength="150" data-var-target></div>
					<div class="col-12">
						<label class="form-label" id="cBodyLabel">Corps du message *</label>
						<div id="cEditor" style="min-height:280px" aria-labelledby="cBodyLabel"></div>
						<div class="invalid-feedback d-block d-none" data-body-error>Le corps du message doit contenir au moins 10 caractères.</div>
						<div class="form-text"><i class="bi bi-shield-check me-1"></i>Le lien de désinscription unique (RGPD) est ajouté automatiquement en bas de chaque e-mail.</div>
					</div>
				</div>
			</div>
			<div class="col-lg-4 vstack gap-3">
				<div class="card border-0 bg-body-tertiary"><div class="card-body">
					<h6><i class="bi bi-funnel me-1"></i>Segment ciblé</h6>
					<fieldset class="mb-2"><legend class="form-label small mb-1">Tags <span class="text-secondary">(au moins un)</span></legend><div class="d-flex flex-wrap gap-1" id="cTags">
						<?php foreach ($tagsAll as $i => $t): ?><input type="checkbox" class="btn-check" name="seg_tags[]" value="<?= e($t) ?>" id="ct<?= $i ?>" autocomplete="off"><label class="btn btn-sm btn-outline-primary rounded-pill" for="ct<?= $i ?>">#<?= e($t) ?></label><?php endforeach; ?></div></fieldset>
					<fieldset class="mb-2"><legend class="form-label small mb-1">Centres d’intérêt <span class="text-secondary">(au moins un)</span></legend><div class="d-flex flex-wrap gap-1" id="cInts">
						<?php foreach ($INT as $i => $t): ?><input type="checkbox" class="btn-check" name="seg_interests[]" value="<?= e($t) ?>" id="ci<?= $i ?>" autocomplete="off"><label class="btn btn-sm btn-outline-primary rounded-pill" for="ci<?= $i ?>"><?= e($t) ?></label><?php endforeach; ?></div></fieldset>
					<div class="row g-2"><div class="col-6"><label class="form-label small mb-1" for="cSince">Inscrit·es depuis</label><input type="date" class="form-control form-control-sm" id="cSince" name="seg_since"></div><div class="col-6"><label class="form-label small mb-1" for="cUntil">Jusqu’au</label><input type="date" class="form-control form-control-sm" id="cUntil" name="seg_until"></div></div>
					<div class="mt-2 small" role="status" aria-live="polite"><i class="bi bi-people me-1"></i><b data-seg-count>0</b> abonné·e(s) actif·ves ciblé·es</div>
				</div></div>
				<div class="card border-0 bg-body-tertiary"><div class="card-body">
					<h6><i class="bi bi-braces me-1"></i>Personnalisation</h6>
					<div class="d-flex flex-wrap gap-1" data-var-list><?php foreach ($campVars as $k => $l): ?><button type="button" class="btn btn-sm btn-light" data-var="<?= $k ?>" title="<?= e($l) ?>"><code>{{<?= $k ?>}}</code></button><?php endforeach; ?></div>
				</div></div>
				<div class="card border-0 bg-body-tertiary"><div class="card-body">
					<h6><i class="bi bi-send-check me-1"></i>Envoi de test</h6>
					<div class="input-group input-group-sm"><label class="visually-hidden" for="cTestTo">Adresse de test</label><input type="email" class="form-control" id="cTestTo" value="<?= e(x_user()['email']) ?>"><button type="button" class="btn btn-primary" data-camp-test>M’envoyer</button></div>
					<div class="small mt-2" role="status" aria-live="polite" data-camp-test-result></div>
					<button type="button" class="btn btn-sm btn-outline-primary w-100 mt-2" data-camp-preview><i class="bi bi-eye me-1"></i>Prévisualiser</button>
				</div></div>
				<div class="card border-0 bg-body-tertiary" data-camp-sendbox><div class="card-body">
					<h6><i class="bi bi-calendar-event me-1"></i>Envoi et planification</h6>
					<div class="form-check"><input class="form-check-input" type="radio" name="mode" value="draft" id="mDraft" checked><label class="form-check-label small" for="mDraft">Enregistrer en brouillon</label></div>
					<div class="form-check"><input class="form-check-input" type="radio" name="mode" value="now" id="mNow"><label class="form-check-label small" for="mNow">Envoyer immédiatement</label></div>
					<div class="form-check"><input class="form-check-input" type="radio" name="mode" value="schedule" id="mSched"><label class="form-check-label small" for="mSched">Programmer à une date et heure précises</label></div>
					<label class="visually-hidden" for="cWhen">Date et heure d’envoi</label><input type="datetime-local" class="form-control form-control-sm mt-2" id="cWhen" name="scheduled_at" disabled min="<?= date('Y-m-d\TH:i', time() + 300) ?>">
				</div></div>
			</div>
		</div>
	</div>
	<div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button class="btn btn-primary" data-camp-submit><i class="bi bi-check2 me-1"></i><span>Enregistrer</span></button></div>
</form></div></div>

<!-- Modale : aperçu de campagne -->
<div class="modal fade" id="mCampPreview" tabindex="-1" aria-labelledby="mCampPreviewTitle" aria-hidden="true"><div class="modal-dialog modal-lg modal-fullscreen-md-down modal-dialog-scrollable"><div class="modal-content">
	<div class="modal-header"><div><h5 class="modal-title" id="mCampPreviewTitle">Aperçu</h5><div class="small text-secondary">Objet : <strong data-pv-subject></strong></div></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
	<div class="modal-body bg-body-tertiary p-2"><iframe title="Aperçu de la campagne" sandbox="" class="w-100 border rounded bg-white" style="height:70vh" data-pv-frame></iframe></div>
</div></div></div>

<!-- Modale : statistiques d'une campagne -->
<div class="modal fade" id="mStats" tabindex="-1" aria-labelledby="mStatsTitle" aria-hidden="true"><div class="modal-dialog modal-lg"><div class="modal-content">
	<div class="modal-header"><div><h5 class="modal-title" id="mStatsTitle">Statistiques</h5><div class="small text-secondary" data-st-meta></div></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
	<div class="modal-body"><div class="row g-2 mb-3" data-st-kpis></div><h3 class="h6">Entonnoir</h3><div class="mb-3" data-st-funnel></div><h3 class="h6">Clics par lien</h3><ul class="list-group small" data-st-links></ul></div>
</div></div></div>
<?php
$segData = array_map(fn($s) => [$s['tags'] ?? [], $s['interests'] ?? [], substr($s['created'], 0, 10)], array_values(array_filter($subs, fn($s) => $s['status'] === 'actif')));
$adminScripts = ($nlChart ?? '') . '<script src="' . BASE . 'assets/vendor/quill/quill.js"></script><script>window.NL_SEG=' . json_encode($segData, JSON_UNESCAPED_UNICODE) . ';</script>' . <<<'JS'
<script>
(function () {
  var $ = function (s, r) { return (r || document).querySelector(s); }, $$ = function (s, r) { return [].slice.call((r || document).querySelectorAll(s)); };
  /* Modale abonné·e */
  var ms = $('#mSub');
  ms.addEventListener('show.bs.modal', function (ev) {
    var f = $('form', ms), d = {}; try { d = ev.relatedTarget && ev.relatedTarget.dataset.sub ? JSON.parse(ev.relatedTarget.dataset.sub) : {}; } catch (e) {}
    f.reset(); f.elements.id.value = d.id || ''; f.elements.email.value = d.email || ''; f.elements.name.value = d.name || ''; f.elements.status.value = d.status || 'actif'; f.elements.tags.value = d.tags || '';
    $$('[name="interests[]"]', f).forEach(function (c) { c.checked = (d.interests || []).indexOf(c.value) > -1; });
    $('#mSubTitle').textContent = d.id ? 'Modifier l’abonné·e' : 'Ajouter un·e abonné·e';
  });
  var md = $('#mSubDel');
  md.addEventListener('show.bs.modal', function (ev) { $('[name=id]', md).value = ev.relatedTarget.dataset.id; $('[data-email]', md).textContent = ev.relatedTarget.dataset.email; });
  /* Sélection groupée */
  var bf = $('#bulkForm');
  if (bf) {
    var upd = function () { var n = $$('[name="ids[]"]:checked', bf).length; $('[data-bulk-count]', bf).textContent = n; $('[data-bulk-bar]', bf).classList.toggle('d-none', !n); };
    bf.addEventListener('change', function (e) { if (e.target.matches('[data-check-all]')) $$('[name="ids[]"]', bf).forEach(function (c) { c.checked = e.target.checked; }); upd(); });
    bf.addEventListener('submit', function (e) { var op = bf.elements.op.value; if (op === 'delete' && !confirm('Supprimer définitivement les abonné·es sélectionné·es ?')) e.preventDefault(); });
  }
  document.addEventListener('click', function (e) { var b = e.target.closest('[data-confirm-click]'); if (b && !confirm(b.dataset.confirmClick)) e.preventDefault(); });

  /* Modale campagne */
  var cf = $('#campForm'), mc = $('#mCamp'), lastTarget = null, readonly = false;
  var quill = new Quill('#cEditor', { theme: 'snow', placeholder: 'Rédigez votre newsletter…', modules: { toolbar: [[{ header: [1, 2, 3, false] }], ['bold', 'italic', 'underline'], [{ color: [] }], [{ list: 'ordered' }, { list: 'bullet' }], [{ align: [] }], ['link', 'image'], ['clean']] } });
  var body = function () { return quill.getText().trim() ? quill.root.innerHTML : ''; };
  var seg = function () {
    var tags = $$('#cTags input:checked').map(function (o) { return o.value; }), ints = $$('#cInts input:checked').map(function (o) { return o.value; }), since = $('#cSince').value, until = $('#cUntil').value;
    var n = NL_SEG.filter(function (s) { return (!tags.length || tags.some(function (t) { return s[0].indexOf(t) > -1; })) && (!ints.length || ints.some(function (t) { return s[1].indexOf(t) > -1; })) && (!since || s[2] >= since) && (!until || s[2] <= until); }).length;
    $('[data-seg-count]').textContent = n; return n;
  };
  ['#cTags', '#cInts', '#cSince', '#cUntil'].forEach(function (s) { $(s).addEventListener('change', seg); });
  var modeUpd = function () {
    var m = cf.elements.mode.value; $('#cWhen').disabled = m !== 'schedule' || readonly; $('#cWhen').required = m === 'schedule';
    $('[data-camp-submit] span').textContent = { draft: 'Enregistrer le brouillon', now: 'Envoyer maintenant', schedule: 'Programmer l’envoi' }[m];
  };
  $$('[name=mode]', cf).forEach(function (r) { r.addEventListener('change', modeUpd); });
  mc.addEventListener('show.bs.modal', function (ev) {
    var d = {}; try { d = ev.relatedTarget && ev.relatedTarget.dataset.camp ? JSON.parse(ev.relatedTarget.dataset.camp) : {}; } catch (e) {}
    cf.reset(); cf.classList.remove('was-validated'); cf.elements.id.value = d.id || '';
    ['subject', 'from_name', 'reply_to', 'preheader'].forEach(function (k) { if (d[k] !== undefined) cf.elements[k].value = d[k]; });
    var sg = d.segment || {};
    $$('#cTags input').forEach(function (o) { o.checked = (sg.tags || []).indexOf(o.value) > -1; }); $$('#cInts input').forEach(function (o) { o.checked = (sg.interests || []).indexOf(o.value) > -1; });
    $('#cSince').value = sg.since || ''; $('#cUntil').value = sg.until || '';
    quill.setContents([]); if (d.body) quill.clipboard.dangerouslyPasteHTML(d.body);
    if (d.status === 'planifiee') { cf.elements.mode.value = 'schedule'; $('#cWhen').value = d.scheduled_at; }
    readonly = d.status === 'envoyee' || d.status === 'en_cours';
    quill.enable(!readonly); $$('input,select,textarea', cf).forEach(function (i) { if (['_csrf', 'do', 'id', 'body'].indexOf(i.name) < 0 && i.id !== 'cTestTo') i.disabled = readonly; });
    $('[data-camp-readonly]').classList.toggle('d-none', !readonly); $('[data-camp-submit]').classList.toggle('d-none', readonly); $('[data-camp-sendbox]').classList.toggle('d-none', readonly);
    $('#mCampTitle').textContent = d.id ? (readonly ? 'Campagne : ' : 'Modifier : ') + d.subject : 'Nouvelle campagne';
    $('[data-camp-test-result]').textContent = ''; $('[data-body-error]').classList.add('d-none'); seg(); modeUpd();
  });
  $$('[data-var-target]', cf).forEach(function (i) { i.addEventListener('focus', function () { lastTarget = i; }); });
  quill.on('selection-change', function (r) { if (r) lastTarget = null; });
  $('[data-var-list]', cf).addEventListener('click', function (e) {
    var b = e.target.closest('[data-var]'); if (!b || readonly) return; var tag = '{{' + b.dataset.var + '}}';
    if (lastTarget) { var s = lastTarget.selectionStart || 0; lastTarget.value = lastTarget.value.slice(0, s) + tag + lastTarget.value.slice(lastTarget.selectionEnd || s); lastTarget.focus(); }
    else { var r = quill.getSelection(true); quill.insertText(r ? r.index : 0, tag, 'user'); }
  });
  cf.addEventListener('submit', function (e) {
    var b = body(), t = document.createElement('div'); t.innerHTML = b; var ok = t.textContent.trim().length >= 10;
    $('[data-body-error]').classList.toggle('d-none', ok); cf.elements.body.value = b;
    var m = cf.elements.mode.value;
    if (!cf.checkValidity() || !ok) { e.preventDefault(); cf.classList.add('was-validated'); var inv = $(':invalid', cf); (inv || $('#cEditor .ql-editor')).focus(); return; }
    if (m !== 'draft' && !seg()) { e.preventDefault(); alert('Aucun·e abonné·e actif·ve ne correspond à ce segment.'); return; }
    if (m === 'now' && !confirm('Envoyer maintenant cette campagne à ' + seg() + ' abonné·e(s) ?')) e.preventDefault();
  });
  var csrf = cf.elements._csrf.value;
  var post = function (o) { var fd = new FormData(); fd.append('_csrf', csrf); fd.append('ajax', '1'); Object.keys(o).forEach(function (k) { fd.append(k, o[k]); }); return fetch('newsletter.php', { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }); };
  var draft = function () { return { subject: cf.elements.subject.value, preheader: cf.elements.preheader.value, from_name: cf.elements.from_name.value, reply_to: cf.elements.reply_to.value, body: body() }; };
  $('[data-camp-preview]').addEventListener('click', function () {
    post(Object.assign({ do: 'camp_preview' }, draft())).then(function (d) { var p = $('#mCampPreview'); $('[data-pv-subject]', p).textContent = d.subject; $('[data-pv-frame]', p).srcdoc = d.html; bootstrap.Modal.getOrCreateInstance(p).show(); });
  });
  $('[data-camp-test]').addEventListener('click', function () {
    var out = $('[data-camp-test-result]'), btn = this; btn.disabled = true; out.className = 'small mt-2 text-secondary'; out.textContent = 'Envoi en cours…';
    post(Object.assign({ do: 'camp_test', to: $('#cTestTo').value.trim() }, draft())).then(function (d) { out.className = 'small mt-2 ' + (d.ok ? 'text-success' : 'text-danger'); out.textContent = d.ok ? d.message : d.error; })
      .catch(function () { out.className = 'small mt-2 text-danger'; out.textContent = 'Erreur réseau.'; }).finally(function () { btn.disabled = false; });
  });

  /* Statistiques d'une campagne */
  var mst = $('#mStats');
  mst.addEventListener('show.bs.modal', function (ev) {
    var d = JSON.parse(ev.relatedTarget.dataset.stats), s = d.s, esc = function (t) { var x = document.createElement('div'); x.textContent = t; return x.innerHTML; };
    $('#mStatsTitle').textContent = d.subject; $('[data-st-meta]').textContent = 'Envoyée le ' + d.sent_at + ' · ' + d.segment;
    var k = [['Taux d’ouverture', s.open_rate + ' %', 'success'], ['Taux de clic', s.click_rate + ' %', 'primary'], ['Désabonnement', s.unsub_rate + ' %', 'warning'], ['Rebond', s.bounce_rate + ' %', 'danger']];
    $('[data-st-kpis]').innerHTML = k.map(function (x) { return '<div class="col-6 col-md-3"><div class="border rounded p-2 h-100"><div class="small text-secondary">' + x[0] + '</div><div class="fw-bold fs-5 text-' + x[2] + '">' + x[1] + '</div></div></div>'; }).join('');
    var f = [['Envoyés', s.sent], ['Délivrés', s.delivered], ['Ouverts', s.opens], ['Ont cliqué', s.clickers]];
    $('[data-st-funnel]').innerHTML = f.map(function (x) { var p = s.sent ? Math.round(x[1] * 100 / s.sent) : 0; return '<div class="mb-2"><div class="d-flex justify-content-between small"><span>' + x[0] + '</span><span>' + x[1] + ' (' + p + ' %)</span></div><div class="progress" style="height:8px"><div class="progress-bar" style="width:' + p + '%"></div></div></div>'; }).join('');
    var l = Object.keys(d.links || {}).sort(function (a, b) { return d.links[b] - d.links[a]; });
    $('[data-st-links]').innerHTML = l.length ? l.map(function (u) { return '<li class="list-group-item d-flex justify-content-between gap-2"><span class="text-truncate">' + esc(u) + '</span><span class="badge bg-primary">' + d.links[u] + '</span></li>'; }).join('') : '<li class="list-group-item text-secondary">Aucun clic.</li>';
  });
})();
</script>
JS;
include __DIR__ . '/../partials/admin-bottom.php';
