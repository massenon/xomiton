<?php
require __DIR__ . '/inc/x_public.php';
x_pub_open('Charte, confidentialité et conditions', ['pubNav' => '', 'nav' => '', 'forcePublic' => true, 'desc' => 'Charte de bienveillance, politique de confidentialité et conditions d’utilisation de Xomiton.']);
$sections = [
	['charte', 'Charte de bienveillance', 'bi-heart'],
	['confidentialite', 'Confidentialité et données', 'bi-shield-lock'],
	['conditions', 'Conditions d’utilisation', 'bi-file-text'],
	['signalement', 'Signalement et modération', 'bi-flag'],
];
?>
<section class="py-5 text-white xm-hero-bg xm-hero-page">
	<div class="container">
		<h1 class="fw-bold text-white">Charte, confidentialité et conditions</h1>
		<p class="mb-0 xm-hero-sub">Dernière mise à jour : <?= x_fr_date('2026-10-01') ?> · Version de démonstration</p>
	</div>
</section>
<div class="container py-5">
	<div class="row g-4">
		<aside class="col-lg-3">
			<div class="card position-sticky" style="top:90px">
				<div class="list-group list-group-flush" id="legalNav">
					<?php foreach ($sections as $s): ?>
						<a class="list-group-item list-group-item-action" href="#<?= $s[0] ?>"><i class="bi <?= $s[2] ?> me-2 text-primary"></i><?= $s[1] ?></a>
					<?php endforeach; ?>
				</div>
			</div>
		</aside>
		<div class="col-lg-9 vstack gap-4">
			<div class="card" id="charte" style="scroll-margin-top:90px"><div class="card-body p-4">
				<h2 class="h4"><i class="bi bi-heart text-danger me-2"></i>Charte de bienveillance</h2>
				<p>Xomiton est un espace sûr. En l’utilisant, je m’engage à :</p>
				<ul>
					<li>respecter chaque personne, quelle que soit son orientation sexuelle, son identité de genre, son âge, son origine ou sa situation ;</li>
					<li>ne tenir aucun propos homophobe, transphobe, sexiste, violent ou discriminatoire ;</li>
					<li>ne jamais révéler l’identité, l’orientation ou les informations privées d’une autre personne (pas d’« outing ») ;</li>
					<li>écouter sans juger et garder confidentiel ce qui est partagé dans les groupes ;</li>
					<li>ne pas utiliser la plateforme pour du harcèlement, du chantage, de la publicité ou des contenus illégaux.</li>
				</ul>
				<div class="alert alert-info mb-0"><i class="bi bi-info-circle me-1"></i>Tout manquement peut entraîner un avertissement, une suspension ou une exclusion décidés par l’équipe de modération.</div>
			</div></div>

			<div class="card" id="confidentialite" style="scroll-margin-top:90px"><div class="card-body p-4">
				<h2 class="h4"><i class="bi bi-shield-lock text-primary me-2"></i>Confidentialité et données</h2>
				<div class="row g-3">
					<?php foreach ([
						['Données collectées', 'Pseudonyme, adresse e-mail, zone géographique et, si vous le choisissez, des informations de profil. Aucun nom réel n’est exigé.'],
						['Signaler', 'Aucune donnée d’identification n’est conservée : seul le contenu de votre signalement et le code de suivi sont enregistrés.'],
						['Localisation GPS', 'Partagée uniquement quand vous déclenchez un SOS ou l’autorisez explicitement ; jamais en arrière-plan.'],
						['Hors connexion', 'Les saisies en attente sont stockées sur votre appareil, puis transmises de façon sécurisée à la synchronisation.'],
						['Vos droits', 'Accès, rectification, export et suppression de vos données depuis Paramètres, ou sur demande à l’équipe.'],
						['Partage', 'Vos informations ne sont jamais vendues. Elles ne sont transmises qu’aux personnes de l’annuaire que vous ciblez.'],
					] as $c): ?>
						<div class="col-md-6"><div class="border rounded-3 p-3 h-100"><h6><?= $c[0] ?></h6><p class="small text-secondary mb-0"><?= $c[1] ?></p></div></div>
					<?php endforeach; ?>
				</div>
			</div></div>

			<div class="card" id="conditions" style="scroll-margin-top:90px"><div class="card-body p-4">
				<h2 class="h4"><i class="bi bi-file-text text-primary me-2"></i>Conditions d’utilisation</h2>
				<ol>
					<li><b>Accès.</b> Xomiton s’adresse aux filles et femmes, aux personnes LBTQ+ et à leurs alliés respectueux. Les inscriptions peuvent être ouvertes ou suspendues par l’administration.</li>
					<li><b>Compte.</b> Vous êtes responsable de la confidentialité de votre mot de passe. Utilisez un mot de passe fort.</li>
					<li><b>Contenus.</b> Vous restez propriétaire de ce que vous publiez et nous autorisez à l’afficher dans la plateforme selon vos réglages de visibilité.</li>
					<li><b>Information, pas avis médical ou juridique.</b> Les ressources et l’assistant informent mais ne remplacent pas un·e professionnel·le. En cas de danger, utilisez le bouton SOS ou les contacts d’urgence.</li>
					<li><b>Disponibilité.</b> La plateforme est mise à jour périodiquement ; des interruptions brèves peuvent survenir.</li>
				</ol>
			</div></div>

			<div class="card" id="signalement" style="scroll-margin-top:90px"><div class="card-body p-4">
				<h2 class="h4"><i class="bi bi-flag text-danger me-2"></i>Signalement et modération</h2>
				<p>Vous pouvez signaler un contenu ou une personne à tout moment. L’équipe examine chaque signalement dans la confidentialité et peut vous orienter vers un·e professionnel·le de l’annuaire.</p>
				<div class="d-flex flex-wrap gap-2">
					<a class="btn btn-danger" href="signalement.php"><i class="bi bi-megaphone me-1"></i>Signaler</a>
					<a class="btn btn-primary-soft" href="help.php">Centre d’aide</a>
					<a class="btn btn-light" href="landing.php">Retour à l’accueil</a>
				</div>
			</div></div>
		</div>
	</div>
</div>
<?php x_pub_close(); ?>
