<?php
require __DIR__ . '/inc/x_public.php';
$member = (x_user() !== null);
$go = fn($p) => $member ? $p : 'sign-in.php?next=' . urlencode($p);
x_pub_open('Do Glegbe – Gestion de l’Hygiène Menstruelle', [
	'nav' => 'doglegbe', 'pubNav' => 'doglegbe', 'type' => 'article',
	'desc' => 'Do Glegbe est votre ressource pour gérer votre santé menstruelle avec dignité : informations fiables, compétences pratiques et soutien social.',
	'image' => 'doglegbexo.png', 'imageAlt' => 'Logo Do Glegbe : goutte de sang avec symbole de genre et lune',
	'breadcrumb' => [['Do Glegbe', 'do-glegbe.php']],
	'schema' => ['@type' => 'Article', 'headline' => 'Do Glegbe – Gestion de l’Hygiène Menstruelle', 'inLanguage' => 'fr-BJ', 'articleSection' => 'Hygiène menstruelle',
		'description' => 'Une bonne gestion de l’hygiène menstruelle est un droit humain fondamental, indispensable à la santé, au bien-être et à l’égalité des genres.',
		'author' => ['@type' => 'Organization', 'name' => 'Xomiton'], 'publisher' => ['@type' => 'Organization', 'name' => 'Xomiton']],
]);
if (!$member) echo '<div class="container py-4"><div class="row"><div class="col-12 vstack gap-4">';
?>
<nav aria-label="Fil d’Ariane"><ol class="breadcrumb mb-0 small">
	<li class="breadcrumb-item"><a href="landing.php">Accueil</a></li>
	<li class="breadcrumb-item active" aria-current="page">Do Glegbe</li>
</ol></nav>

<!-- Bannière -->
<section class="dg-hero rounded-4 p-4 p-lg-5" aria-labelledby="dgH1">
	<div class="row align-items-center g-4 position-relative" style="z-index:1">
		<div class="col-md-7 col-lg-8">
			<span class="badge bg-white text-danger mb-3"><i class="bi bi-droplet-half me-1" aria-hidden="true"></i>Gestion de l’Hygiène Menstruelle</span>
			<h1 class="display-5 fw-bold mb-3" id="dgH1">Do Glegbe</h1>
			<p class="lead mb-4">Votre guide complet pour une gestion saine et digne de l’hygiène menstruelle.</p>
			<div class="d-flex flex-wrap gap-2">
				<a class="btn btn-light fw-bold text-danger" href="<?= e($go('thematiques.php?cat=regles')) ?>">Explorer les fiches <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i></a>
				<a class="btn btn-outline-light" href="<?= e($go('events.php')) ?>">Voir les ateliers</a>
			</div>
		</div>
		<div class="col-md-5 col-lg-4">
			<div class="dg-logo-card"><img src="<?= img('doglegbexo.png') ?>" alt="Logo Do Glegbe : goutte de sang avec symbole de genre et lune" title="Do Glegbe" width="314" height="258"></div>
		</div>
	</div>
</section>

<!-- Qu'est-ce que -->
<section class="card card-body p-4 p-md-5" aria-labelledby="dgQuoi">
	<div class="dg-eyebrow mb-2">Notre mission</div>
	<h2 class="h4 mb-3" id="dgQuoi">Qu’est-ce que « Do Glegbe » ?</h2>
	<p class="dg-lead">Avoir ses règles est un simple fait biologique qui constitue trop souvent un obstacle à la santé, à la dignité et au respect des droits des filles et des femmes…</p>
	<p class="mb-0">« Do Glegbe » est une initiative dédiée à l’autonomisation des personnes menstruées. Notre mission est de briser les tabous et de fournir les informations, les compétences, les ressources, le soutien social et le matériel nécessaires pour gérer sa santé menstruelle en toute confiance et dignité.</p>
</section>

<!-- Pourquoi la GHM -->
<section class="card card-body p-4 p-md-5" aria-labelledby="dgPourquoi">
	<div class="dg-eyebrow mb-2">Un droit humain fondamental</div>
	<h2 class="h4 mb-2" id="dgPourquoi">Pourquoi la gestion de l’hygiène menstruelle est-elle essentielle ?</h2>
	<p class="text-secondary mb-4">Une bonne gestion de l’hygiène menstruelle est indispensable à la santé, au bien-être et à l’égalité des genres. Elle permet de :</p>
	<div class="row g-3">
		<?php foreach ([
			['bi-shield-plus', 'Prévenir les infections', 'Utiliser des protections propres et les changer régulièrement est crucial pour éviter les infections de l’appareil reproducteur.'],
			['bi-people', 'Assurer la participation sociale', 'Avoir accès à des protections et à des installations sanitaires adéquates permet de poursuivre ses études, son travail et ses activités sociales sans interruption.'],
			['bi-emoji-smile', 'Renforcer la confiance en soi', 'Comprendre son corps et savoir gérer ses menstruations contribue à une meilleure estime de soi et à un plus grand bien-être psychologique.'],
			['bi-chat-heart', 'Lutter contre la stigmatisation', 'Parler ouvertement des menstruations aide à déconstruire les mythes et les tabous qui entraînent discrimination et exclusion.'],
		] as $b): ?>
		<div class="col-md-6"><div class="dg-benefit"><span class="dg-ico" aria-hidden="true"><i class="bi <?= $b[0] ?>"></i></span><div><b><?= $b[1] ?></b><span class="small text-secondary"><?= $b[2] ?></span></div></div></div>
		<?php endforeach; ?>
	</div>
</section>

<!-- Services -->
<section aria-labelledby="dgServices">
	<div class="dg-eyebrow mb-2">Nos services et ressources</div>
	<h2 class="h4 mb-3" id="dgServices">À travers Do Glegbe, Xomiton s’engage à vous fournir</h2>
	<div class="row g-3">
		<?php foreach ([
			['1', 'Informations fiables', 'Des articles et guides sur le cycle menstruel, les différents types de protections (serviettes, tampons, coupes, culottes menstruelles) et la gestion de la douleur.', 'thematiques.php?cat=regles', 'Lire les fiches'],
			['2', 'Compétences pratiques', 'Des ateliers (en ligne et en présentiel) pour apprendre à utiliser et entretenir les protections réutilisables, et pour mieux comprendre son corps.', 'events.php', 'Voir les ateliers'],
			['3', 'Soutien social', 'Des groupes de discussion sécurisés pour partager ses expériences, poser des questions sans jugement et briser le sentiment d’isolement.', 'groups.php', 'Rejoindre un groupe'],
		] as $s): ?>
		<div class="col-md-4"><div class="dg-step d-flex flex-column">
			<span class="dg-num" aria-hidden="true"><?= $s[0] ?></span>
			<h3 class="h6"><?= $s[1] ?></h3>
			<p class="small text-secondary flex-grow-1"><?= $s[2] ?></p>
			<a class="small fw-bold text-danger" href="<?= e($go($s[3])) ?>"><?= $s[4] ?> <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
		</div></div>
		<?php endforeach; ?>
	</div>
</section>

<!-- Engagez-vous -->
<section class="card card-body text-center p-4 p-md-5 border-0 dg-section" aria-labelledby="dgEngage">
	<h2 class="h4" id="dgEngage">Engagez-vous avec nous</h2>
	<p class="mx-auto mb-4" style="max-width:640px">La santé menstruelle est l’affaire de touxtes. Explorez nos ressources, participez à nos événements et rejoignez la conversation.</p>
	<p class="dg-quote mx-auto mb-4 text-start" style="max-width:640px">Ensemble, créons un monde où les menstruations ne sont plus un obstacle, mais une partie naturelle et saine de la vie.</p>
	<div class="d-flex flex-wrap gap-2 justify-content-center">
		<a class="btn btn-dg" href="<?= e($go('thematiques.php?cat=regles')) ?>">Explorer les thématiques liées</a>
		<a class="btn btn-outline-danger" href="<?= e($go('forums.php')) ?>">Poser une question</a>
		<a class="btn btn-outline-secondary" href="urgences.php">Besoin d’aide ?</a>
	</div>
</section>
<?php if (!$member) echo '</div></div></div>'; ?>
<?php x_pub_close(); ?>
