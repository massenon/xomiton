<?php
/**
 * Xomiton – module Newsletter : abonné·es, segmentation, campagnes, envoi par lots, suivi (ouvertures, clics,
 * désinscriptions, rebonds). S'appuie sur le service d'envoi inc/x_mailer.php.
 *
 * Collections : newsletter_subscribers, newsletter_campaigns.
 * Envoi : nl_process_due() envoie les campagnes dont l'heure est passée, par lots de NL_BATCH destinataires
 * (compatible hébergement mutualisé : appelé par la tâche cron cron/newsletter.php chaque minute
 * et, en secours, à l'ouverture de l'écran Newsletter).
 */
require_once __DIR__ . '/x_mailer.php';
const NL_BATCH = 200;

function nl_statuses() { return ['actif' => ['Actif', 'success'], 'en_attente' => ['En attente de confirmation', 'warning text-dark'], 'desabonne' => ['Désabonné·e', 'secondary'], 'rebond' => ['Rebond (bounce)', 'danger']]; }
function nl_interests() { return ['Santé', 'Droits', 'Bien-être', 'Événements', 'Ressources', 'Sécurité numérique']; }
function nl_tags() {
    $t = ['membre', 'pair', 'partenaire', 'presse', 'bailleur'];
    foreach (store_all('newsletter_subscribers') as $s) foreach ($s['tags'] ?? [] as $x) $t[] = $x;
    return array_values(array_unique($t));
}
function nl_badge($status) { $s = nl_statuses()[$status] ?? [$status, 'secondary']; return '<span class="badge bg-' . $s[1] . '">' . e($s[0]) . '</span>'; }
function nl_token() { return bin2hex(random_bytes(16)); }

/** Clé de signature des liens de suivi (générée une fois). */
function nl_secret() { $k = setting('nl_secret'); if (!$k) { $k = bin2hex(random_bytes(24)); setting_set('nl_secret', $k); } return $k; }
function nl_sign(...$parts) { return substr(hash_hmac('sha256', implode('|', $parts), nl_secret()), 0, 20); }

function nl_find_by_email($email) { foreach (store_all('newsletter_subscribers') as $s) if (strcasecmp($s['email'], $email) === 0) return $s; return null; }
function nl_find_by_token($t) { if (!is_string($t) || strlen($t) !== 32) return null; foreach (store_all('newsletter_subscribers') as $s) if (hash_equals($s['token'], $t)) return $s; return null; }

/** Abonné·es ciblé·es par un segment : toujours actifs, filtrés par tags, centres d'intérêt et date d'inscription. */
function nl_segment(array $seg, ?array $subs = null) {
    $subs = $subs ?? store_all('newsletter_subscribers');
    $tags = $seg['tags'] ?? []; $ints = $seg['interests'] ?? []; $since = $seg['since'] ?? ''; $until = $seg['until'] ?? '';
    return array_values(array_filter($subs, fn($s) => $s['status'] === 'actif'
        && (!$tags || array_intersect($tags, $s['tags'] ?? []))
        && (!$ints || array_intersect($ints, $s['interests'] ?? []))
        && ($since === '' || substr($s['created'], 0, 10) >= $since)
        && ($until === '' || substr($s['created'], 0, 10) <= $until)));
}
function nl_segment_label(array $seg) {
    $p = [];
    if (!empty($seg['tags'])) $p[] = 'tags : ' . implode(', ', $seg['tags']);
    if (!empty($seg['interests'])) $p[] = 'intérêts : ' . implode(', ', $seg['interests']);
    if (!empty($seg['since'])) $p[] = 'inscrit·es depuis le ' . date('d/m/Y', strtotime($seg['since']));
    if (!empty($seg['until'])) $p[] = 'jusqu’au ' . date('d/m/Y', strtotime($seg['until']));
    return $p ? implode(' · ', $p) : 'Tous les abonné·es actifs';
}

/** Statistiques d'une campagne (taux en %, calculés sur les e-mails délivrés). */
function nl_stats(array $c) {
    $sent = count($c['recipients'] ?? []); $b = count($c['bounces'] ?? []); $del = max(0, $sent - $b);
    $o = count((array)($c['opens'] ?? [])); $k = count((array)($c['clicks'] ?? [])); $u = count($c['unsubs'] ?? []);
    $pct = fn($a, $d) => $d ? round($a * 100 / $d, 1) : 0;
    $nClicks = 0; foreach ((array)($c['clicks'] ?? []) as $urls) $nClicks += count((array)$urls);
    return ['sent' => $sent, 'delivered' => $del, 'opens' => $o, 'clickers' => $k, 'clicks' => $nClicks, 'unsubs' => $u, 'bounces' => $b,
        'open_rate' => $pct($o, $del), 'click_rate' => $pct($k, $del), 'unsub_rate' => $pct($u, $del), 'bounce_rate' => $pct($b, $sent), 'ctor' => $pct($k, $o)];
}

/** Liens de suivi et de désinscription d'un·e abonné·e pour une campagne. */
function nl_unsub_url(array $s, $cid = 0) { return x_site_url('newsletter.php?a=unsub&t=' . $s['token'] . ($cid ? '&c=' . (int)$cid : '')); }
function nl_open_url($cid, $sid) { return x_site_url('newsletter.php?a=open&c=' . (int)$cid . '&s=' . (int)$sid . '&h=' . nl_sign('o', $cid, $sid)); }
function nl_click_url($cid, $sid, $url) { return x_site_url('newsletter.php?a=click&c=' . (int)$cid . '&s=' . (int)$sid . '&u=' . rawurlencode($url) . '&h=' . nl_sign('c', $cid, $sid, $url)); }

/** E-mail personnalisé d'une campagne : variables, liens suivis, pixel d'ouverture, lien de désinscription obligatoire. */
function nl_build_email(array $c, array $s, $track = true) {
    $first = trim(explode(' ', $s['name'] ?? '')[0]) ?: 'à vous';
    $vars = ['user_name' => $s['name'] ?: $s['email'], 'user_first_name' => $first, 'user_email' => $s['email'], 'unsubscribe_link' => nl_unsub_url($s, $c['id'] ?? 0), 'login_link' => x_site_url('sign-in.php')];
    $body = x_mail_render($c['body'], $vars);
    if ($track && !empty($c['id'])) {
        $body = preg_replace_callback('#href\s*=\s*"(https?://[^"]+)"#i', function ($m) use ($c, $s) {
            $u = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
            return str_contains($u, 'newsletter.php?a=unsub') ? $m[0] : 'href="' . e(nl_click_url($c['id'], $s['id'], $u)) . '"';
        }, $body);
    }
    $html = x_mail_layout($body, ['preheader' => x_mail_render($c['preheader'] ?? '', $vars, false) ?: null, 'unsubscribe' => $vars['unsubscribe_link'], 'pixel' => $track && !empty($c['id']) ? nl_open_url($c['id'], $s['id']) : null]);
    return [x_mail_render($c['subject'], $vars, false), $html, $vars['unsubscribe_link']];
}

/** Envoie un lot de la campagne $c (destinataires pas encore traités). Retourne la campagne mise à jour. */
function nl_send_batch(array $c) {
    $subs = []; foreach (store_all('newsletter_subscribers') as $s) $subs[$s['id']] = $s;
    if (($c['status'] ?? '') !== 'en_cours') { $c['queue'] = array_column(nl_segment($c['segment'] ?? [], array_values($subs)), 'id'); $c['recipients'] = []; $c['bounces'] = []; $c['status'] = 'en_cours'; $c['started_at'] = date('Y-m-d H:i:s'); }
    $batch = array_splice($c['queue'], 0, NL_BATCH);
    foreach ($batch as $sid) {
        $s = $subs[$sid] ?? null; if (!$s || $s['status'] !== 'actif') continue;
        [$subject, $html, $unsub] = nl_build_email($c, $s);
        $r = x_mail_send($s['email'], $subject, $html, ['from_name' => $c['from_name'], 'reply_to' => $c['reply_to'], 'template' => 'newsletter#' . $c['id'], 'context' => 'newsletter',
            'headers' => ['List-Unsubscribe' => '<' . $unsub . '>', 'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click', 'Precedence' => 'bulk']]);
        $c['recipients'][] = $sid;
        // Rebond : adresse refusée par le serveur (ou domaine de test « .invalid » / « bounce » dans la boîte d'envoi locale).
        if (!$r['ok'] || preg_match('/(\.invalid|@bounce\.)/i', $s['email'])) {
            $c['bounces'][] = $sid;
            store_update('newsletter_subscribers', $sid, ['status' => 'rebond', 'bounced_at' => date('Y-m-d H:i:s'), 'bounce_reason' => $r['error'] ?? 'Adresse inexistante']);
        }
    }
    if (!$c['queue']) { $c['status'] = 'envoyee'; $c['sent_at'] = date('Y-m-d H:i:s'); }
    store_update('newsletter_campaigns', $c['id'], $c);
    return $c;
}

/** Traite les campagnes planifiées arrivées à échéance et les envois en cours (un lot par campagne). */
function nl_process_due() {
    $done = 0;
    foreach (store_all('newsletter_campaigns') as $c) {
        $due = ($c['status'] === 'planifiee' && !empty($c['scheduled_at']) && strtotime($c['scheduled_at']) <= time()) || $c['status'] === 'en_cours';
        if (!$due) continue;
        $c = nl_send_batch($c); $done++;
        if ($c['status'] === 'envoyee') x_log('Newsletter', 'Campagne envoyée : ' . $c['subject'] . ' (' . count($c['recipients']) . ' destinataires)');
    }
    return $done;
}

/** Inscription depuis le formulaire public (double opt-in). Retourne [ok, message]. */
function nl_subscribe($email, $name = '', array $interests = [], $source = 'footer') {
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return [false, 'Adresse e-mail invalide.'];
    $interests = array_values(array_intersect($interests, nl_interests()));
    $s = nl_find_by_email($email);
    $msg = 'Merci ! Un e-mail de confirmation vient de vous être envoyé. Cliquez sur le lien qu’il contient pour activer votre inscription.';
    if ($s && $s['status'] === 'actif') return [true, $msg]; // réponse identique : pas d'énumération des abonné·es
    if ($s) { store_update('newsletter_subscribers', $s['id'], ['status' => 'en_attente', 'token' => nl_token(), 'interests' => $interests ?: ($s['interests'] ?? [])]); $s = store_find('newsletter_subscribers', $s['id']); }
    else $s = store_append('newsletter_subscribers', ['email' => $email, 'name' => mb_substr(trim($name), 0, 80), 'status' => 'en_attente', 'tags' => [], 'interests' => $interests, 'source' => $source, 'created' => date('Y-m-d H:i:s'), 'confirmed_at' => null, 'token' => nl_token(), 'consent' => date('Y-m-d H:i:s') . ' · ' . ($_SERVER['REMOTE_ADDR'] ?? '')]);
    x_mail_send_template('newsletter-confirmation', $email, ['activation_link' => x_site_url('newsletter.php?a=confirm&t=' . $s['token']), 'unsubscribe_link' => nl_unsub_url($s), 'user_email' => $email], ['context' => 'newsletter-optin']);
    return [true, $msg];
}

/** Formulaire d'inscription (pied de page public). */
function nl_footer_form() {
    $f = $_SESSION['nl_flash'] ?? null; unset($_SESSION['nl_flash']);
    ob_start(); ?>
	<h6 class="text-white">Newsletter</h6>
	<p class="small opacity-75 mb-2">Actualités, ressources et événements de la communauté, une à deux fois par mois. Désinscription en un clic.</p>
	<?php if ($f): ?><div class="alert alert-<?= $f[0] ? 'success' : 'danger' ?> py-2 small mb-2" role="status"><?= e($f[1]) ?></div><?php endif; ?>
	<form class="xm-nl-form" method="post" action="<?= BASE ?>newsletter.php" novalidate>
		<?= csrf_field() ?><input type="hidden" name="a" value="subscribe"><input type="hidden" name="back" value="<?= e(basename($_SERVER['SCRIPT_NAME'])) ?>">
		<div class="visually-hidden" aria-hidden="true"><label for="nlWebsite">Ne pas remplir</label><input id="nlWebsite" name="website" tabindex="-1" autocomplete="off"></div>
		<label class="visually-hidden" for="nlEmail">Votre adresse e-mail</label>
		<div class="input-group">
			<input class="form-control" type="email" id="nlEmail" name="email" placeholder="Votre adresse e-mail" autocomplete="email" required>
			<button class="btn btn-primary" type="submit"><i class="bi bi-send me-1" aria-hidden="true"></i>S’inscrire</button>
		</div>
		<div class="form-check small mt-2">
			<input class="form-check-input" type="checkbox" id="nlConsent" name="consent" value="1" required>
			<label class="form-check-label opacity-75" for="nlConsent">J’accepte de recevoir la newsletter Xomiton et j’ai lu la <a class="text-white text-decoration-underline" href="<?= BASE ?>privacy-and-terms.php#confidentialite">politique de confidentialité</a>.</label>
		</div>
	</form>
<?php
    return ob_get_clean();
}
