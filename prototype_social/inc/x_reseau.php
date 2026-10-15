<?php
/** Helpers du module Réseau social (groupes, pages, événements, forums, invitations, profil). */

function x_role_label($role) {
    return ['membre' => 'Membre', 'pair' => 'Pair animateur·rice', 'admin' => 'Équipe Xomiton', 'superadmin' => 'Équipe Xomiton'][$role] ?? 'Membre';
}

/** Ensemble d'identifiants persisté en session (adhésions, abonnements, participations). */
function x_set_all($key, array $default = []) {
    if (!isset($_SESSION['x_sets'][$key])) { $_SESSION['x_sets'][$key] = $default; }
    return $_SESSION['x_sets'][$key];
}
function x_set_has($key, $id, array $default = []) { return in_array((int)$id, x_set_all($key, $default), true); }
function x_set_toggle($key, $id, array $default = []) {
    $s = x_set_all($key, $default); $id = (int)$id;
    if (in_array($id, $s, true)) { $s = array_values(array_diff($s, [$id])); $on = false; }
    else { $s[] = $id; $on = true; }
    $_SESSION['x_sets'][$key] = $s;
    return $on;
}

function x_months_fr() { return ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.']; }
function x_date_fr($d, $withYear = true) {
    $t = is_numeric($d) ? (int)$d : strtotime($d);
    if (!$t) return '';
    return date('j', $t) . ' ' . x_months_fr()[(int)date('n', $t) - 1] . ($withYear ? ' ' . date('Y', $t) : '');
}

/** Redirection avec message flash. */
function x_redirect($url, $type = null, $msg = null) {
    if ($type) { $_SESSION['flash'] = ['type' => $type, 'msg' => $msg]; }
    header('Location: ' . $url);
    exit;
}

/** Colonne de droite standard des pages membre. */
function x_right_column() {
    $events = array_slice(store_all('events'), 0, 3);
    ob_start(); ?>
	<div class="col-lg-3">
		<div class="row g-4">
			<div class="col-sm-6 col-lg-12"><?= x_render_who_to_follow(4) ?></div>
			<div class="col-sm-6 col-lg-12">
				<div class="card">
					<div class="card-header pb-0 border-0 d-flex justify-content-between"><h5 class="card-title mb-0">Événements</h5><a class="small" href="<?= BASE ?>events.php">Tout voir</a></div>
					<div class="card-body">
						<?php foreach ($events as $ev): ?>
						<div class="d-flex mb-3">
							<div class="avatar"><img class="avatar-img rounded-2" src="<?= img($ev['image']) ?>" alt="Visuel de l’événement : <?= e($ev['title']) ?>" title="<?= e($ev['title']) ?>" loading="lazy"></div>
							<div class="ms-2"><h6 class="card-title mb-0"><a href="<?= BASE ?>event-details.php?id=<?= (int)$ev['id'] ?>"><?= e($ev['title']) ?></a></h6><p class="small mb-0"><?= e(x_date_fr($ev['date'], false)) ?> · <?= e($ev['place']) ?></p></div>
						</div>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
		</div>
	</div>
<?php
    return ob_get_clean();
}

/** Début / fin de page membre standard. */
function x_page_start($title, $nav) {
    $GLOBALS['pageTitle'] = $title; $GLOBALS['nav'] = $nav;
}

/** Sujets de forum : amorçage automatique de 6 sujets si la collection est vide. */
function x_forum_topics() {
    $rows = store_all('forum_topics');
    if (!$rows && !file_exists(store_path('forum_topics'))) {
        $now = time();
        $seed = [
            ['forum' => 'f1', 'title' => 'Comment parler à ma famille sans me mettre en danger ?', 'body' => "Je voudrais être plus honnête avec mes proches mais j'ai peur de leur réaction. Comment avez-vous préparé cette conversation, ou décidé de ne pas la faire ?", 'user' => 6, 'views' => 214],
            ['forum' => 'f2', 'title' => 'Quelles preuves conserver avant de porter plainte ?', 'body' => "On m'a conseillé de garder les messages et de faire un certificat médical. Y a-t-il d'autres preuves utiles, et comment les sauvegarder en sécurité ?", 'user' => 12, 'views' => 158],
            ['forum' => 'f3', 'title' => 'Centre de dépistage gratuit et discret à Parakou', 'body' => "Je cherche un centre où l'accueil est respectueux pour les personnes LBTQ+. Des adresses de confiance ?", 'user' => 10, 'views' => 97],
            ['forum' => 'f4', 'title' => 'Créer un cercle d\'entraide dans mon quartier', 'body' => "Nous sommes 5 amies et voulons formaliser un petit cercle de parole et d'épargne solidaire. Des conseils pour l'organiser ?", 'user' => 14, 'views' => 121],
            ['forum' => 'f5', 'title' => 'Premiers pas pour lancer une petite activité génératrice de revenus', 'body' => "Je veux vendre des produits transformés sur le marché de Bohicon. Comment démarrer avec peu de capital ?", 'user' => 15, 'views' => 143],
            ['forum' => 'f3', 'title' => 'Techniques de respiration avant une prise de parole', 'body' => "J'ai des crises d'angoisse quand je dois parler en groupe. Quels exercices vous aident vraiment ?", 'user' => 17, 'views' => 76],
        ];
        foreach ($seed as $i => $s) { $s['id'] = $i + 1; $s['ts'] = $now - ($i + 1) * 7200 - $i * 3600; $rows[] = $s; }
        store_save('forum_topics', $rows);
        $reps = [
            [1, 4, "Prends ton temps. Tu peux commencer par une personne de confiance et choisir un lieu où tu te sens en sécurité. Rien ne t'oblige à tout dire."],
            [1, 12, "Pour moi, écrire d'abord ce que je voulais dire m'a beaucoup aidée. Et j'avais prévu un plan B chez une amie."],
            [2, 4, "Messages, photos, certificat médical, noms des témoins et dates. Fais une copie sur une clé ou envoie-la à une personne de confiance."],
            [3, 8, "Demande conseil à un point focal via l'annuaire, ils connaissent les centres accueillants."],
            [4, 16, "Commencez par fixer des règles simples : confidentialité, écoute sans jugement, tour de parole."],
            [6, 4, "La respiration carrée marche bien : 4 secondes d'inspiration, 4 de pause, 4 d'expiration, 4 de pause."],
        ];
        $out = [];
        foreach ($reps as $i => $r) $out[] = ['id' => $i + 1, 'topic' => $r[0], 'user' => $r[1], 'text' => $r[2], 'ts' => $now - 3600 * (6 - $i)];
        store_save('forum_replies', $out);
    }
    return $rows;
}

/** Connexions d'un·e membre : invitations acceptées + réseau de base du prototype. */
function x_connections($uid) {
    $ids = [];
    foreach (store_all('invitations') as $i) {
        if ($i['status'] !== 'acceptee') continue;
        if ((int)$i['from_user'] === (int)$uid) $ids[] = (int)$i['to_user'];
        if ((int)$i['to_user'] === (int)$uid) $ids[] = (int)$i['from_user'];
    }
    foreach ([4, 6, 10, 12, 14, 16] as $b) $ids[] = $b;
    $out = [];
    foreach (array_unique($ids) as $id) {
        if ($id === (int)$uid) continue;
        $u = x_user_by_id($id);
        if ($u && $u['status'] === 'actif') $out[] = $u;
    }
    return $out;
}
function x_user_posts($uid) {
    $p = array_values(array_filter(store_all('posts'), fn($x) => (int)$x['user'] === (int)$uid && empty($x['anonymous'])));
    usort($p, fn($a, $b) => $b['ts'] <=> $a['ts']);
    return $p;
}
/** Images Xomiton disponibles pour la médiathèque. */
function x_media_images() {
    $f = [];
    for ($i = 1; $i <= 16; $i++) $f[] = sprintf('hero-%02d.jpg', $i);
    return array_merge($f, ['3995814.jpg', '4137441.jpg', '6033855.jpg', '7150569.jpg', '04.jpg', '07.jpg', 'about-us.jpg']);
}

/** Colonne latérale des pages de profil : présentation, médias, connexions. */
function x_profile_side() {
    $u = x_user();
    $conn = array_slice(x_connections($u['id']), 0, 6);
    $media = array_slice(x_media_images(), 0, 6);
    ob_start(); ?>
	<div class="col-lg-4">
		<div class="row g-4">
			<div class="col-md-6 col-lg-12"><div class="card">
				<div class="card-header border-0 pb-0"><h5 class="card-title mb-0">Présentation</h5></div>
				<div class="card-body position-relative pt-0">
					<p class="mt-3">Membre de l’espace safe Xomiton. <?= e($u['bio'] ?? 'Ici, on apprend, on s’entraide et on avance ensemble.') ?></p>
					<ul class="list-unstyled mt-3 mb-0">
						<li class="mb-2"><i class="bi bi-calendar-date fa-fw pe-1"></i> Inscrit·e le <strong><?= e(x_date_fr($u['joined'] ?? 'now')) ?></strong></li>
						<li class="mb-2"><i class="bi bi-geo-alt fa-fw pe-1"></i> Zone : <strong><?= e($u['zone'] ?? '') ?></strong></li>
						<li><i class="bi bi-shield-check fa-fw pe-1"></i> Profil : <strong><?= e(x_role_label($u['role'])) ?></strong></li>
					</ul>
				</div>
			</div></div>
			<div class="col-md-6 col-lg-12"><div class="card">
				<div class="card-header d-flex justify-content-between border-0 pb-0"><h5 class="card-title mb-0">Médias</h5><a class="btn btn-primary-soft btn-sm" href="my-profile-media.php">Tout voir</a></div>
				<div class="card-body"><div class="row g-2">
					<?php foreach ($media as $m): ?><div class="col-4"><div class="xm-ratio-1x1 rounded"><img class="xm-img-cover" src="<?= img($m) ?>" alt="Média partagé par ce profil" title="Média du profil" loading="lazy"></div></div><?php endforeach; ?>
				</div></div>
			</div></div>
			<div class="col-md-6 col-lg-12"><div class="card">
				<div class="card-header d-flex justify-content-between border-0 pb-0"><h5 class="card-title mb-0">Connexions</h5><a class="btn btn-primary-soft btn-sm" href="my-profile-connections.php">Tout voir</a></div>
				<div class="card-body"><div class="row g-3">
					<?php foreach ($conn as $c): ?><div class="col-4 text-center"><div class="avatar avatar-lg mb-1"><img class="avatar-img rounded-circle" src="<?= x_avatar($c['avatar']) ?>" alt="Avatar de <?= e($c['name']) ?>" title="<?= e($c['name']) ?>"></div><div class="small text-truncate"><?= e(explode(' ', $c['name'])[0]) ?></div></div><?php endforeach; ?>
				</div></div>
			</div></div>
		</div>
	</div>
<?php
    return ob_get_clean();
}
