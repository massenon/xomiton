<?php
/**
 * Xomiton – service d'envoi d'e-mails (équivalent prototype d'un Mailable Laravel).
 *
 *   x_mail_send_template('reinitialisation-mot-de-passe', 'awa@exemple.bj', ['user_name' => 'Awa', 'reset_link' => '…']);
 *
 * 1. cherche le template en base (collection email_templates) par son slug ;
 * 2. remplace les variables {{nom}} de l'objet et du corps (valeurs échappées) ;
 * 3. habille le corps dans la mise en page Xomiton ;
 * 4. envoie par le serveur SMTP configuré (Administration › Templates e-mails › SMTP).
 *    Sans serveur SMTP configuré, le message est déposé dans la boîte d'envoi locale
 *    (data/mail_outbox.json) : utile en prototype et en recette.
 * Chaque envoi est consigné dans data/mail_log.json.
 *
 * En Laravel : App\Mail\TemplateMail (Mailable) + App\Services\EmailTemplateService, mêmes étapes.
 */

/** URL absolue d'un chemin du prototype (liens et images des e-mails). */
function x_site_url($path = '') {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'xomiton.org') . preg_replace('#/admin$#', '', $dir) . '/' . ltrim($path, '/');
}

/** Catalogue des variables dynamiques : nom => [description, valeur fictive pour l'aperçu]. */
function x_mail_variables() {
    return [
        'user_name'         => ['Prénom et nom de la personne', 'Awa Houngbédji'],
        'user_first_name'   => ['Prénom de la personne', 'Awa'],
        'user_email'        => ['Adresse e-mail de la personne', 'awa@exemple.bj'],
        'user_pseudo'       => ['Pseudonyme', 'awa_h'],
        'activation_link'   => ['Lien d’activation du compte', 'https://xomiton.org/activation?t=7f3a9c'],
        'reset_link'        => ['Lien de réinitialisation du mot de passe', 'https://xomiton.org/reinitialiser?t=4be21d'],
        'otp_code'          => ['Code à usage unique (6 chiffres)', '482913'],
        'otp_expiry'        => ['Durée de validité du code', '10 minutes'],
        'login_link'        => ['Lien vers la connexion', 'https://xomiton.org/sign-in.php'],
        'alert_reference'   => ['Numéro d’alerte ou de signalement', '#A-0142'],
        'alert_zone'        => ['Zone de l’alerte', 'Littoral · Cotonou'],
        'event_title'       => ['Titre de l’événement', 'Atelier « Dire non sans culpabiliser »'],
        'event_date'        => ['Date de l’événement', 'samedi 24 octobre à 15 h'],
        'site_name'         => ['Nom de la plateforme', 'Xomiton'],
        'support_email'     => ['E-mail du support', 'contact@xomiton.bj'],
        'current_year'      => ['Année en cours', date('Y')],
        'unsubscribe_link'  => ['Lien de désinscription (newsletter)', 'https://xomiton.org/newsletter.php?u=…'],
    ];
}

/** Valeurs fictives pour l'aperçu et l'envoi de test. */
function x_mail_sample_vars() {
    $v = array_map(fn($x) => $x[1], x_mail_variables());
    $v['site_name'] = setting('site_name', 'Xomiton'); $v['support_email'] = setting('contact_email', 'contact@xomiton.bj');
    return $v;
}

/** Variables utilisées dans un texte : ['user_name', …]. */
function x_mail_used_vars($text) {
    preg_match_all('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', (string)$text, $m);
    return array_values(array_unique($m[1]));
}

/** Remplace {{variable}}. $escape = true pour du HTML (valeurs échappées) ; les variables inconnues restent visibles. */
function x_mail_render($text, array $vars, $escape = true) {
    $vars += ['site_name' => setting('site_name', 'Xomiton'), 'support_email' => setting('contact_email', 'contact@xomiton.bj'), 'current_year' => date('Y')];
    return preg_replace_callback('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', function ($m) use ($vars, $escape) {
        if (!array_key_exists($m[1], $vars)) return $m[0];
        return $escape ? htmlspecialchars((string)$vars[$m[1]], ENT_QUOTES, 'UTF-8') : (string)$vars[$m[1]];
    }, (string)$text);
}

/** Mise en page HTML des e-mails (tableaux + styles en ligne pour les clients de messagerie). */
function x_mail_layout($bodyHtml, array $o = []) {
    $site = e(setting('site_name', 'Xomiton'));
    $pre = isset($o['preheader']) ? '<div style="display:none;max-height:0;overflow:hidden;opacity:0">' . e($o['preheader']) . '</div>' : '';
    $logo = $o['logo'] ?? x_site_url('assets/images/xomiton/brand/logo-mono-blanc.svg');
    $unsub = !empty($o['unsubscribe']) ? '<p style="margin:12px 0 0"><a href="' . e($o['unsubscribe']) . '" style="color:#6B6880">Se désinscrire de la newsletter</a></p>' : '';
    $pixel = !empty($o['pixel']) ? '<img src="' . e($o['pixel']) . '" width="1" height="1" alt="" style="display:block;border:0">' : '';
    return '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $site . '</title></head>'
        . '<body style="margin:0;padding:0;background:#F4F3F8;font-family:Arial,Helvetica,sans-serif;color:#1B1830">' . $pre
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F4F3F8"><tr><td align="center" style="padding:24px 12px">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#FFFFFF;border-radius:12px;overflow:hidden">'
        . '<tr><td style="background:#0433C3;padding:20px 28px"><img src="' . e($logo) . '" alt="' . $site . '" height="32" style="display:block;height:32px;border:0"></td></tr>'
        . '<tr><td style="padding:28px;font-size:15px;line-height:1.6">' . $bodyHtml . '</td></tr>'
        . '<tr><td style="padding:18px 28px;background:#FAF9FC;border-top:1px solid #ECEAF2;font-size:12px;line-height:1.5;color:#6B6880">'
        . $site . ' — espace safe d’apprentissage et d’appui holistique, Bénin.<br>Besoin d’aide ? <a href="mailto:' . e(setting('contact_email', 'contact@xomiton.bj')) . '" style="color:#0433C3">' . e(setting('contact_email', 'contact@xomiton.bj')) . '</a>'
        . $unsub . '</td></tr></table>' . $pixel . '</td></tr></table></body></html>';
}

/** Configuration SMTP (paramètres plateforme). */
function x_smtp_config() {
    $c = setting('smtp', []);
    return (is_array($c) ? $c : []) + ['host' => '', 'port' => 587, 'secure' => 'tls', 'user' => '', 'pass' => '', 'from_email' => setting('contact_email', 'contact@xomiton.bj'), 'from_name' => setting('site_name', 'Xomiton'), 'reply_to' => ''];
}

/**
 * Envoi bas niveau. Retourne ['ok' => bool, 'transport' => 'smtp'|'outbox', 'error' => ?string].
 * $o : from_name, from_email, reply_to, text (version texte), headers (tableau d'en-têtes supplémentaires).
 */
function x_mail_send($to, $subject, $html, array $o = []) {
    $cfg = x_smtp_config();
    $fromEmail = $o['from_email'] ?? $cfg['from_email']; $fromName = $o['from_name'] ?? $cfg['from_name']; $reply = $o['reply_to'] ?? $cfg['reply_to'];
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return x_mail_log($to, $subject, ['ok' => false, 'transport' => '-', 'error' => 'Adresse destinataire invalide.'], $o);
    if ($cfg['host'] === '') {
        store_append('mail_outbox', ['at' => date('Y-m-d H:i:s'), 'to' => $to, 'from' => $fromName . ' <' . $fromEmail . '>', 'reply_to' => $reply, 'subject' => $subject, 'html' => $html]);
        return x_mail_log($to, $subject, ['ok' => true, 'transport' => 'outbox', 'error' => null], $o);
    }
    $res = x_smtp_deliver($cfg, $fromEmail, $fromName, $reply, $to, $subject, $html, $o);
    return x_mail_log($to, $subject, $res + ['transport' => 'smtp'], $o);
}

function x_mail_log($to, $subject, array $res, array $o) {
    store_append('mail_log', ['at' => date('Y-m-d H:i:s'), 'to' => $to, 'subject' => mb_substr($subject, 0, 140), 'template' => $o['template'] ?? '', 'context' => $o['context'] ?? '', 'transport' => $res['transport'], 'ok' => $res['ok'], 'error' => $res['error'] ?? null]);
    return $res;
}

/** Client SMTP minimal (AUTH LOGIN, STARTTLS ou SSL implicite), sans dépendance. */
function x_smtp_deliver(array $cfg, $fromEmail, $fromName, $reply, $to, $subject, $html, array $o) {
    $host = ($cfg['secure'] === 'ssl' ? 'ssl://' : '') . $cfg['host'];
    $fp = @stream_socket_client($host . ':' . (int)$cfg['port'], $errno, $errstr, 10);
    if (!$fp) return ['ok' => false, 'error' => 'Connexion SMTP impossible : ' . $errstr];
    stream_set_timeout($fp, 15);
    $read = function () use ($fp) { $d = ''; while (($l = fgets($fp, 515)) !== false) { $d .= $l; if (isset($l[3]) && $l[3] === ' ') break; } return $d; };
    $cmd = function ($c, $expect) use ($fp, $read) { if ($c !== null) fwrite($fp, $c . "\r\n"); $r = $read(); if (!in_array((int)substr($r, 0, 3), (array)$expect, true)) throw new RuntimeException(trim($r) ?: 'Réponse SMTP vide'); return $r; };
    try {
        $cmd(null, 220); $ehlo = 'EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'xomiton.local');
        $cmd($ehlo, 250);
        if ($cfg['secure'] === 'tls') {
            $cmd('STARTTLS', 220);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new RuntimeException('Échec de la négociation TLS');
            $cmd($ehlo, 250);
        }
        if ($cfg['user'] !== '') { $cmd('AUTH LOGIN', 334); $cmd(base64_encode($cfg['user']), 334); $cmd(base64_encode($cfg['pass']), 235); }
        $cmd('MAIL FROM:<' . $fromEmail . '>', 250); $cmd('RCPT TO:<' . $to . '>', [250, 251]); $cmd('DATA', 354);
        $b = 'b' . bin2hex(random_bytes(8)); $enc = fn($s) => '=?UTF-8?B?' . base64_encode($s) . '?=';
        $text = $o['text'] ?? trim(preg_replace("/\n{3,}/", "\n\n", html_entity_decode(strip_tags(preg_replace(['#<head.*?</head>#is', '#<div style="display:none[^>]*>.*?</div>#is', '/<(br|\/p|\/tr|\/h\d|\/li)[^>]*>/i'], ['', '', "\n"], $html)), ENT_QUOTES, 'UTF-8')));
        $h = ['Date: ' . date('r'), 'From: ' . $enc($fromName) . ' <' . $fromEmail . '>', 'To: <' . $to . '>', 'Subject: ' . $enc($subject), 'MIME-Version: 1.0',
            'Message-ID: <' . bin2hex(random_bytes(8)) . '@' . substr(strrchr($fromEmail, '@'), 1) . '>', 'Content-Type: multipart/alternative; boundary="' . $b . '"'];
        if ($reply) $h[] = 'Reply-To: <' . $reply . '>';
        foreach ($o['headers'] ?? [] as $k => $v) $h[] = $k . ': ' . $v;
        $body = "--$b\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($text))
              . "--$b\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html)) . "--$b--";
        $cmd(implode("\r\n", $h) . "\r\n\r\n" . $body . "\r\n.", 250);
        fwrite($fp, "QUIT\r\n"); fclose($fp);
        return ['ok' => true, 'error' => null];
    } catch (Throwable $t) {
        @fclose($fp);
        return ['ok' => false, 'error' => 'SMTP : ' . $t->getMessage()];
    }
}

/** Template par slug (ou null). */
function x_mail_template($slug) {
    foreach (store_all('email_templates') as $t) if ($t['slug'] === $slug) return $t;
    return null;
}

/** Rendu complet d'un template (objet + HTML final) avec des variables. */
function x_mail_build(array $tpl, array $vars, array $layout = []) {
    return [x_mail_render($tpl['subject'], $vars, false), x_mail_layout(x_mail_render($tpl['body'], $vars), $layout + ['preheader' => x_mail_render($tpl['preheader'] ?? '', $vars, false) ?: null])];
}

/** Point d'entrée applicatif : envoie le template $slug à $to en remplaçant les variables. */
function x_mail_send_template($slug, $to, array $vars = [], array $o = []) {
    $tpl = x_mail_template($slug);
    if (!$tpl) return x_mail_log($to, '(template introuvable : ' . $slug . ')', ['ok' => false, 'transport' => '-', 'error' => 'Template « ' . $slug . ' » introuvable.'], $o);
    if (isset($tpl['active']) && !$tpl['active']) return x_mail_log($to, $tpl['subject'], ['ok' => false, 'transport' => '-', 'error' => 'Template désactivé.'], $o);
    [$subject, $html] = x_mail_build($tpl, $vars);
    return x_mail_send($to, $subject, $html, $o + ['template' => $slug]);
}

/** Nettoie le HTML saisi dans l'éditeur (scripts, iframes, attributs on*, liens javascript:). */
function x_mail_sanitize($html) {
    $html = preg_replace('#<(script|iframe|object|embed|style|form|input|button)\b[^>]*>.*?</\1>#is', '', (string)$html);
    $html = preg_replace('#<(script|iframe|object|embed|style|form|input|button|link|meta)\b[^>]*/?>#is', '', $html);
    $html = preg_replace('#\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html);
    return preg_replace('#(href|src)\s*=\s*(["\'])\s*(javascript|vbscript|data):[^"\']*\2#i', '$1="#"', $html);
}
