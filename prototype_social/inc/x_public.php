<?php
/** Aides pour les pages publiques / mixtes (landing, aide, blog, hors ligne). */
require_once __DIR__ . '/bootstrap.php';

/** Articles du blog dérivés des thématiques (6 premiers choisis) et des événements. */
function x_blog_articles() {
	$pick = [1, 3, 21, 16, 19, 23];
	$cats = x_thematique_cats();
	$authors = ['Équipe Xomiton', 'Me Judith Houngbo', 'Chantal Hounkpatin', 'Brice Akplogan', 'Équipe Xomiton', 'Dr Rosine Adjovi'];
	$out = [];
	foreach ($pick as $k => $id) {
		$t = x_thematique($id);
		if (!$t) continue;
		$c = $cats[$t['cat']] ?? ['Actualité', 'bi-newspaper', 'primary'];
		$out[] = [
			'id' => $k + 1, 'theme' => $t['id'], 'title' => $t['title'], 'summary' => $t['summary'], 'image' => $t['image'],
			'cat' => $c[0], 'icon' => $c[1], 'color' => $c[2], 'minutes' => $t['minutes'],
			'author' => $authors[$k], 'date' => date('Y-m-d', strtotime('2026-10-10 -' . ($k * 6 + 1) . ' days')),
		];
	}
	return $out;
}

function x_fr_date($d) {
	$m = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
	$t = strtotime($d);
	return date('j', $t) . ' ' . $m[(int)date('n', $t) - 1] . ' ' . date('Y', $t);
}

/** Catégories de l'aide, associées à des mots-clés de la FAQ. */
function x_help_categories() {
	return [
		['compte', 'Compte & inscription', 'bi-person-circle', 'primary', ['xomiton', 'inscrire', 'données']],
		['signal', 'Signalement & urgences', 'bi-megaphone', 'danger', ['signaler', 'signalement', 'plainte', 'abus']],
		['soutien', 'Soutien & consultations', 'bi-heart-pulse', 'success', ['chat', 'consultations']],
		['communaute', 'Communauté & événements', 'bi-people', 'info', ['événement', 'LBTQ+']],
	];
}

/** Ouvre une page à mise en page double : espace membre si connecté·e, en-tête public sinon. */
function x_pub_open($title, array $o = []) {
	$pageTitle = $title;
	$pageDesc = $o['desc'] ?? null;
	$nav = $o['nav'] ?? '';
	$pubNav = $o['pubNav'] ?? '';
	$pageImage = $o['image'] ?? null;
	$pageImageAlt = $o['imageAlt'] ?? null;
	$pageType = $o['type'] ?? 'website';
	$breadcrumb = $o['breadcrumb'] ?? null;
	$pageSchema = $o['schema'] ?? null;
	$pageModified = $o['modified'] ?? null;
	$noSosFab = !empty($o['noSosFab']) || !x_user();
	$mode = !empty($o['forcePublic']) || !x_user() ? 'public' : 'member';
	$GLOBALS['__x_pub_mode'] = $mode;
	$GLOBALS['__x_pub_noSos'] = $noSosFab;
	echo '<!DOCTYPE html><html lang="fr"><head>';
	include __DIR__ . '/../partials/title-meta.php';
	include __DIR__ . '/../partials/head-css.php';
	echo ($o['head'] ?? '') . '</head><body>';
	if ($mode === 'public') {
		include __DIR__ . '/../partials/public-header.php';
		echo '<main id="contenu" tabindex="-1">';
	} else {
		include __DIR__ . '/../partials/header.php';
		echo '<main id="contenu" tabindex="-1"><div class="container"><div class="row g-4">';
		include __DIR__ . '/../partials/sidenav.php';
		echo '<div class="col-lg-9 vstack gap-4">';
	}
}

function x_pub_close(array $o = []) {
	$mode = $GLOBALS['__x_pub_mode'] ?? 'public';
	$noSosFab = $GLOBALS['__x_pub_noSos'] ?? true;
	if ($mode === 'public') {
		echo '</main>';
		include __DIR__ . '/../partials/public-footer.php';
	} else {
		echo '</div></div></div></main>';
		include __DIR__ . '/../partials/modals.php';
	}
	include __DIR__ . '/../partials/footer-scripts.php';
	echo ($o['scripts'] ?? '') . '</body></html>';
}

/** Catégorie d'aide (clé) pour la question FAQ d'index $i. */
function x_faq_cat($i) {
	$m = [0 => 'compte', 1 => 'compte', 2 => 'signal', 3 => 'signal', 4 => 'signal', 5 => 'soutien', 6 => 'soutien', 7 => 'communaute', 8 => 'communaute', 9 => 'compte'];
	return $m[$i] ?? 'compte';
}
