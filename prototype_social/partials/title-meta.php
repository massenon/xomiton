<?php
require_once __DIR__ . '/../inc/bootstrap.php';
/*
 * Balises <head> communes : titre, description, canonical, robots, favicons, Open Graph, Twitter Card, JSON-LD.
 * Variables optionnelles posées par la page avant l'include :
 *   $pageTitle, $pageDesc, $pageImage (chemin sous assets/images/xomiton/ ou URL), $pageImageAlt,
 *   $pageType ('website'|'article'), $pageIndex (bool), $breadcrumb ([[libellé, url relative], …]),
 *   $pageSchema (tableau JSON-LD supplémentaire : Article, CollectionPage…), $pageModified (Y-m-d).
 */
$__scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$__origin = $__scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'xomiton.org');
$__dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
$__root = $__origin . preg_replace('#/admin$#', '', $__dir) . '/';
$__file = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
$__keep = array_intersect_key($_GET, array_flip(['id', 'cat', 'forum', 'tab']));
$__canonical = $__root . ($__file === 'index.php' && !$__keep ? '' : $__file) . ($__keep ? '?' . http_build_query($__keep) : '');

$__site = 'Xomiton';
$__title = ($pageTitle ?? 'Accueil');
$__fullTitle = mb_stripos($__title, 'Xomiton') !== false ? $__title : $__title . ' – ' . $__site;
$__desc = trim(preg_replace('/\s+/', ' ', strip_tags($pageDesc ?? "Xomiton : votre espace safe d'apprentissage et d'appui holistique pour les filles et femmes LBTQ+ au Bénin.")));
if (mb_strlen($__desc) > 160) $__desc = rtrim(mb_substr($__desc, 0, 157), " ,;:.") . '…';

$__imgRaw = $pageImage ?? 'covers/og-default.jpg';
$__img = preg_match('#^https?://#', $__imgRaw) ? $__imgRaw : $__root . 'assets/images/xomiton/' . ltrim($__imgRaw, '/');
$__imgAlt = $pageImageAlt ?? ('Xomiton – ' . $__title);
$__type = $pageType ?? 'website';
// Pages membres : non indexées ; pages publiques (visiteur·se non connecté·e) : indexées.
$__index = $pageIndex ?? !x_user();
$__robots = $__index ? 'index, follow, max-image-preview:large' : 'noindex, nofollow';

$__crumbs = [['Accueil', 'index.php']];
foreach (($breadcrumb ?? [[$__title, $__file]]) as $__c) $__crumbs[] = $__c;
$__list = [];
foreach ($__crumbs as $__i => $__c) {
	$__list[] = ['@type' => 'ListItem', 'position' => $__i + 1, 'name' => $__c[0], 'item' => preg_match('#^https?://#', $__c[1]) ? $__c[1] : $__root . $__c[1]];
}
$__graph = [
	['@type' => 'Organization', '@id' => $__root . '#org', 'name' => $__site, 'url' => $__root . 'index.php',
		'logo' => $__root . 'assets/images/xomiton/brand/icone-appli-bleu.svg',
		'description' => "Espace safe d'apprentissage et d'appui holistique pour les filles et femmes LBTQ+ au Bénin."],
	['@type' => 'WebSite', '@id' => $__root . '#site', 'url' => $__root . 'index.php', 'name' => $__site, 'inLanguage' => 'fr-BJ',
		'publisher' => ['@id' => $__root . '#org'],
		'potentialAction' => ['@type' => 'SearchAction', 'target' => $__root . 'ressources.php?q={search_term_string}', 'query-input' => 'required name=search_term_string']],
	['@type' => 'WebPage', '@id' => $__canonical . '#page', 'url' => $__canonical, 'name' => $__fullTitle, 'description' => $__desc, 'inLanguage' => 'fr-BJ',
		'isPartOf' => ['@id' => $__root . '#site'], 'primaryImageOfPage' => ['@type' => 'ImageObject', 'url' => $__img],
		'breadcrumb' => ['@id' => $__canonical . '#breadcrumb']],
	['@type' => 'BreadcrumbList', '@id' => $__canonical . '#breadcrumb', 'itemListElement' => $__list],
];
if (!empty($pageSchema)) {
	$pageSchema += ['image' => [$__img], 'mainEntityOfPage' => ['@id' => $__canonical . '#page'], 'datePublished' => $pageModified ?? '2026-01-15', 'dateModified' => $pageModified ?? date('Y-m-d')];
	$__graph[] = $pageSchema;
}
?>
<title><?= e($__fullTitle) ?></title>

<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<meta name="author" content="Xomiton">
<meta name="description" content="<?= e($__desc) ?>">
<meta name="robots" content="<?= e($__robots) ?>">
<meta name="theme-color" content="#0433C3">
<link rel="canonical" href="<?= e($__canonical) ?>">
<link rel="manifest" href="<?= BASE ?>manifest.webmanifest">

<!-- Favicon : icône d'application Xomiton (rosace blanche sur bleu #0433C3, design-system/logo/icone-appli-bleu) -->
<link rel="icon" href="<?= BASE ?>assets/images/favicon.ico" sizes="16x16 32x32 48x48">
<link rel="icon" type="image/svg+xml" href="<?= BASE ?>assets/images/xomiton/brand/icone-appli-bleu.svg">
<link rel="icon" type="image/png" sizes="32x32" href="<?= BASE ?>assets/images/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="<?= BASE ?>assets/images/favicon-16x16.png">
<link rel="apple-touch-icon" sizes="180x180" href="<?= BASE ?>assets/images/apple-touch-icon.png">

<!-- Open Graph (Facebook, WhatsApp, LinkedIn) -->
<meta property="og:site_name" content="<?= e($__site) ?>">
<meta property="og:locale" content="fr_FR">
<meta property="og:type" content="<?= e($__type) ?>">
<meta property="og:title" content="<?= e($__fullTitle) ?>">
<meta property="og:description" content="<?= e($__desc) ?>">
<meta property="og:url" content="<?= e($__canonical) ?>">
<meta property="og:image" content="<?= e($__img) ?>">
<meta property="og:image:alt" content="<?= e($__imgAlt) ?>">

<!-- Twitter Card -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($__fullTitle) ?>">
<meta name="twitter:description" content="<?= e($__desc) ?>">
<meta name="twitter:image" content="<?= e($__img) ?>">
<meta name="twitter:image:alt" content="<?= e($__imgAlt) ?>">

<!-- Schema.org JSON-LD -->
<script type="application/ld+json"><?= json_encode(['@context' => 'https://schema.org', '@graph' => $__graph], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>

<!-- Police Montserrat auto-hébergée -->
<link rel="stylesheet" href="<?= BASE ?>assets/css/xomiton-fonts.css">
