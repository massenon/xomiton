<?php
/**
 * Newsletter – points d'entrée publics :
 *   POST a=subscribe        inscription (pied de page), double opt-in
 *   GET  a=confirm&t=       confirmation de l'inscription
 *   GET  a=unsub&t=[&c=]    page de désinscription ; POST = désinscription (aussi en « one-click » RFC 8058)
 *   GET  a=open&c&s&h       pixel d'ouverture
 *   GET  a=click&c&s&u&h    suivi de clic puis redirection (lien signé : pas de redirection ouverte)
 */
require __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/x_newsletter.php';
require_once __DIR__ . '/inc/x_public.php';
$a = $_REQUEST['a'] ?? '';

/* Mise à jour atomique d'une campagne (statistiques) */
function nl_track($cid, callable $fn) { $c = store_find('newsletter_campaigns', $cid); if ($c) { $c = $fn($c); store_update('newsletter_campaigns', $c['id'], $c); } }

if ($a === 'open') {
    $c = (int)($_GET['c'] ?? 0); $s = (int)($_GET['s'] ?? 0);
    if (hash_equals(nl_sign('o', $c, $s), (string)($_GET['h'] ?? ''))) nl_track($c, function ($x) use ($s) { $o = (array)($x['opens'] ?? []); if (!isset($o[$s])) $o[$s] = date('Y-m-d H:i:s'); $x['opens'] = $o; return $x; });
    header('Content-Type: image/gif'); header('Cache-Control: no-store');
    echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'); exit;
}
if ($a === 'click') {
    $c = (int)($_GET['c'] ?? 0); $s = (int)($_GET['s'] ?? 0); $u = (string)($_GET['u'] ?? '');
    if (!preg_match('#^https?://#i', $u) || !hash_equals(nl_sign('c', $c, $s, $u), (string)($_GET['h'] ?? ''))) { http_response_code(400); exit('Lien invalide.'); }
    nl_track($c, function ($x) use ($s, $u) {
        $k = (array)($x['clicks'] ?? []); $k[$s] = array_values(array_unique(array_merge((array)($k[$s] ?? []), [$u]))); $x['clicks'] = $k;
        $o = (array)($x['opens'] ?? []); if (!isset($o[$s])) $o[$s] = date('Y-m-d H:i:s'); $x['opens'] = $o; // un clic vaut ouverture (images bloquées)
        return $x;
    });
    header('Location: ' . $u, true, 302); exit;
}
if ($a === 'subscribe' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $back = preg_replace('/[^a-z0-9_.-]/i', '', $_POST['back'] ?? 'landing.php') ?: 'landing.php';
    if (!csrf_ok()) $r = [false, 'Session expirée, veuillez réessayer.'];
    elseif (($_POST['website'] ?? '') !== '') $r = [true, 'Merci !']; // pot de miel anti-robots
    elseif (empty($_POST['consent'])) $r = [false, 'Cochez la case de consentement pour vous inscrire.'];
    elseif (($_SESSION['nl_last'] ?? 0) > time() - 20) $r = [false, 'Patientez quelques secondes avant une nouvelle inscription.'];
    else { $_SESSION['nl_last'] = time(); $r = nl_subscribe($_POST['email'] ?? '', $_POST['name'] ?? '', (array)($_POST['interests'] ?? [])); }
    $_SESSION['nl_flash'] = $r;
    header('Location: ' . $back . '#newsletter'); exit;
}

/* Pages de confirmation et de désinscription */
$s = nl_find_by_token($_REQUEST['t'] ?? '');
$state = 'invalid'; $cid = (int)($_REQUEST['c'] ?? 0);
if ($s && $a === 'confirm') {
    if ($s['status'] !== 'actif') { store_update('newsletter_subscribers', $s['id'], ['status' => 'actif', 'confirmed_at' => date('Y-m-d H:i:s')]); x_log('Newsletter', 'Inscription confirmée : ' . $s['email']); }
    $state = 'confirmed';
} elseif ($s && $a === 'unsub') {
    $state = $s['status'] === 'desabonne' ? 'unsubscribed' : 'ask';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $state === 'ask') { // POST simple (bouton ou « one-click » des messageries) : le jeton suffit
        store_update('newsletter_subscribers', $s['id'], ['status' => 'desabonne', 'unsub_at' => date('Y-m-d H:i:s'), 'unsub_reason' => mb_substr(trim($_POST['reason'] ?? ''), 0, 200)]);
        if ($cid) nl_track($cid, function ($x) use ($s) { $x['unsubs'] = array_values(array_unique(array_merge($x['unsubs'] ?? [], [$s['id']]))); return $x; });
        x_log('Newsletter', 'Désinscription : ' . $s['email']);
        $state = 'unsubscribed';
    }
}
x_pub_open('Newsletter', ['desc' => 'Gérez votre inscription à la newsletter Xomiton.', 'forcePublic' => true]);
?>
<section class="py-5"><div class="container"><div class="row justify-content-center"><div class="col-md-8 col-lg-6">
	<div class="card card-body p-4 p-sm-5 text-center">
	<?php if ($state === 'confirmed'): ?>
		<i class="bi bi-envelope-check text-success display-5 mb-2" aria-hidden="true"></i>
		<h1 class="h3">Inscription confirmée</h1>
		<p class="text-secondary">Merci ! Vous recevrez désormais la newsletter Xomiton à l’adresse <strong><?= e($s['email']) ?></strong>.</p>
		<a class="btn btn-primary" href="landing.php">Retour à l’accueil</a>
	<?php elseif ($state === 'ask'): ?>
		<i class="bi bi-envelope-dash text-primary display-5 mb-2" aria-hidden="true"></i>
		<h1 class="h3">Se désinscrire de la newsletter</h1>
		<p class="text-secondary">Vous ne recevrez plus la newsletter à l’adresse <strong><?= e($s['email']) ?></strong>. Les e-mails liés à votre compte (sécurité, mot de passe) ne sont pas concernés.</p>
		<form method="post" class="text-start">
			<input type="hidden" name="a" value="unsub"><input type="hidden" name="t" value="<?= e($s['token']) ?>"><input type="hidden" name="c" value="<?= $cid ?>">
			<label class="form-label small" for="nlReason">Pourquoi partez-vous ? <span class="text-secondary">(facultatif)</span></label>
			<select class="form-select mb-3" id="nlReason" name="reason"><option value="">—</option><option>Trop d’e-mails</option><option>Contenu non pertinent</option><option>Je ne me suis pas inscrit·e</option><option>Autre</option></select>
			<div class="d-grid"><button class="btn btn-danger">Confirmer la désinscription</button></div>
		</form>
	<?php elseif ($state === 'unsubscribed'): ?>
		<i class="bi bi-check2-circle text-success display-5 mb-2" aria-hidden="true"></i>
		<h1 class="h3">Vous êtes désinscrit·e</h1>
		<p class="text-secondary">L’adresse <strong><?= e($s['email']) ?></strong> ne recevra plus la newsletter. Vous pouvez vous réinscrire à tout moment depuis le bas de chaque page.</p>
		<a class="btn btn-primary-soft" href="landing.php#newsletter">Me réinscrire</a>
	<?php else: ?>
		<i class="bi bi-link-45deg text-warning display-5 mb-2" aria-hidden="true"></i>
		<h1 class="h3">Lien invalide ou expiré</h1>
		<p class="text-secondary">Ce lien ne correspond à aucune inscription. Vous pouvez vous inscrire à nouveau depuis le bas de la page d’accueil.</p>
		<a class="btn btn-primary" href="landing.php#newsletter">Retour à l’accueil</a>
	<?php endif; ?>
	</div>
</div></div></div></section>
<?php x_pub_close();
