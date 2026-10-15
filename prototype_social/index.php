<?php
require __DIR__ . '/inc/bootstrap.php';
$pageTitle = "Votre espace safe d'apprentissage et d'appui holistique";
$pageDesc = "Filles et femmes LBTQ+ ? Rejoignez notre communauté bienveillante et accédez à des ressources, trouvez du soutien et faites entendre votre voix en toute sécurité.";
$pubNav = 'accueil';
$noSosFab = true;
$themes = array_slice(x_thematiques(), 0, 6);
$cats = x_thematique_cats();
$faq = array_slice(x_faq(), 0, 8);
$gateways = x_social_gateways();
$u = x_user();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<?php include "partials/title-meta.php"; include "partials/head-css.php"; ?>
	<style>
		:root { --xm-blue: #0433C3; --xm-red: #E30E0E; }
		html { scroll-behavior: smooth; }
		section[id] { scroll-margin-top: 80px; }
		.lp-hero { background: radial-gradient(1200px 500px at 85% -10%, rgba(111,44,145,.35), transparent 60%), linear-gradient(135deg, #0433C3 0%, #0A2A9A 55%, #3A1D7A 100%); color: #fff; position: relative; overflow: hidden; }
		.lp-hero::after { content: ""; position: absolute; width: 520px; height: 520px; right: -160px; bottom: -220px; border-radius: 50%; background: rgba(255,255,255,.06); }
		.lp-hero h1 { color: #fff; font-weight: 800; letter-spacing: -.02em; line-height: 1.1; }
		.lp-hero .btn-outline-light { border-color: #fff; color: #fff; }
		.lp-hero .btn-outline-light:hover, .lp-hero .btn-outline-light:focus { background: #fff; border-color: #fff; color: var(--xm-blue); }
		.lp-hero .lead { color: rgba(255,255,255,.88); }
		.lp-kicker { display: inline-flex; gap: .5rem; align-items: center; background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.25); padding: .35rem .85rem; border-radius: 999px; font-size: .8rem; font-weight: 600; }
		.lp-photos { position: relative; min-height: 440px; }
		.lp-photos .ph { position: absolute; object-fit: cover; border-radius: 1.5rem; box-shadow: 0 20px 50px rgba(0,0,0,.35); border: 4px solid rgba(255,255,255,.9); }
		.lp-photos .ph1 { width: 62%; height: 360px; right: 0; top: 0; }
		.lp-photos .ph2 { width: 46%; height: 250px; left: 0; bottom: 10px; }
		.lp-photos .badge-float { position: absolute; background: var(--bs-body-bg); color: var(--bs-body-color); border-radius: 1rem; padding: .6rem .9rem; box-shadow: 0 10px 30px rgba(0,0,0,.25); font-size: .8rem; font-weight: 600; }
		.lp-section { padding: 4.5rem 0; }
		.lp-title { font-weight: 800; letter-spacing: -.01em; }
		.lp-eyebrow { color: var(--xm-blue); font-weight: 700; text-transform: uppercase; letter-spacing: .08em; font-size: .78rem; }
		[data-bs-theme=dark] .lp-eyebrow { color: #8FA6FF; }
		.lp-card { border: 1px solid var(--bs-border-color); border-radius: 1.1rem; transition: transform .2s, box-shadow .2s; height: 100%; }
		.lp-card:hover { transform: translateY(-4px); box-shadow: 0 14px 34px rgba(4,51,195,.14); }
		.lp-ico { width: 52px; height: 52px; border-radius: 14px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.5rem; }
		.lp-stats { margin-top: -3.2rem; position: relative; z-index: 2; }
		.lp-stat { text-align: center; padding: 1.2rem .5rem; }
		.lp-stat b { display: block; font-size: 1.8rem; color: var(--xm-blue); font-weight: 800; }
		[data-bs-theme=dark] .lp-stat b { color: #8FA6FF; }
		/* Mockups */
		.phone { width: 270px; max-width: 100%; aspect-ratio: 9/18.5; border: 9px solid #12172b; border-radius: 2.2rem; overflow: hidden; position: relative; background: var(--bs-body-bg); box-shadow: 0 25px 60px rgba(4,51,195,.3); margin: 0 auto; }
		.phone::before { content: ""; position: absolute; top: 6px; left: 50%; translate: -50% 0; width: 70px; height: 14px; background: #12172b; border-radius: 10px; z-index: 5; }
		.phone .scr { position: absolute; inset: 0; display: flex; flex-direction: column; font-size: .72rem; }
		.phone .bar { background: var(--xm-blue); color: #fff; padding: 1.6rem .8rem .6rem; font-weight: 700; display: flex; align-items: center; gap: .5rem; }
		.phone .bar img { width: 24px; height: 24px; border-radius: 50%; object-fit: cover; }
		.phone .body { flex: 1; padding: .7rem; display: flex; flex-direction: column; gap: .45rem; overflow: hidden; background: var(--bs-tertiary-bg); }
		.bub { max-width: 82%; padding: .45rem .65rem; border-radius: .9rem; line-height: 1.3; }
		.bub.in { background: var(--bs-body-bg); border: 1px solid var(--bs-border-color); border-bottom-left-radius: .2rem; }
		.bub.out { background: var(--xm-blue); color: #fff; margin-left: auto; border-bottom-right-radius: .2rem; }
		.phone .inp { padding: .5rem .7rem; border-top: 1px solid var(--bs-border-color); background: var(--bs-body-bg); display: flex; justify-content: space-between; color: var(--bs-secondary-color); }
		.sos-screen { background: linear-gradient(180deg, #E30E0E, #8E0909); color: #fff; align-items: center; justify-content: center; text-align: center; padding: 1.5rem; gap: .8rem; }
		.sos-btn { width: 130px; height: 130px; border-radius: 50%; background: #fff; color: var(--xm-red); font-size: 2rem; font-weight: 800; display: flex; align-items: center; justify-content: center; box-shadow: 0 0 0 14px rgba(255,255,255,.18), 0 0 0 30px rgba(255,255,255,.1); animation: lpp 2s infinite; }
		@keyframes lpp { 50% { box-shadow: 0 0 0 20px rgba(255,255,255,.18), 0 0 0 42px rgba(255,255,255,.06); } }
		.laptop { max-width: 560px; margin: 0 auto; }
		.laptop .lid { border: 10px solid #12172b; border-bottom-width: 14px; border-radius: 1rem 1rem .3rem .3rem; background: var(--bs-body-bg); overflow: hidden; aspect-ratio: 16/10; box-shadow: 0 25px 60px rgba(4,51,195,.25); display: flex; font-size: .65rem; }
		.laptop .base { height: 14px; background: linear-gradient(#c9ccd8, #9fa3b5); border-radius: 0 0 1.2rem 1.2rem; width: 112%; margin-left: -6%; }
		.laptop .side { width: 24%; background: #0E1020; color: #c9c6dc; padding: .6rem; display: flex; flex-direction: column; gap: .35rem; }
		.laptop .side span { display: block; padding: .2rem .4rem; border-radius: .3rem; }
		.laptop .side span.on { background: rgba(124,151,255,.2); color: #fff; }
		.laptop .main { flex: 1; padding: .7rem; background: var(--bs-tertiary-bg); display: flex; flex-direction: column; gap: .5rem; }
		.laptop .kpis { display: grid; grid-template-columns: repeat(4, 1fr); gap: .4rem; }
		.laptop .kpis div { background: var(--bs-body-bg); border-left: 3px solid var(--xm-blue); padding: .35rem .45rem; border-radius: .3rem; }
		.laptop .kpis div:nth-child(2) { border-color: var(--xm-red); } .laptop .kpis div:nth-child(3) { border-color: #0B7A4B; } .laptop .kpis div:nth-child(4) { border-color: #F0A800; }
		.laptop .kpis b { display: block; font-size: .95rem; }
		.laptop .chart { flex: 1; background: var(--bs-body-bg); border-radius: .4rem; padding: .5rem; display: flex; align-items: flex-end; gap: .35rem; }
		.laptop .chart i { flex: 1; background: linear-gradient(var(--xm-blue), #7C97FF); border-radius: .2rem .2rem 0 0; }
		.laptop .chart i.r { background: linear-gradient(var(--xm-red), #ff8d8d); }
		.lp-step { position: relative; padding-left: 3.2rem; }
		.lp-step .n { position: absolute; left: 0; top: 0; width: 2.4rem; height: 2.4rem; border-radius: 50%; background: var(--xm-blue); color: #fff; font-weight: 800; display: flex; align-items: center; justify-content: center; }
		.lp-band-red .btn-outline-light, .lp-band .btn-outline-light { background: transparent; border-color: #fff; color: #fff; }
		.lp-band-red .btn-outline-light:hover, .lp-band-red .btn-outline-light:focus, .lp-band-red .btn-outline-light:active { background: #fff; border-color: #fff; color: #E30E0E; }
		.lp-band .btn-outline-light:hover, .lp-band .btn-outline-light:focus, .lp-band .btn-outline-light:active { background: #fff; border-color: #fff; color: #0433C3; }
		.xm-chat-fab { position: fixed; right: 18px; bottom: 78px; z-index: 1040; width: 56px; height: 56px; border-radius: 50%; padding: 0; display: inline-flex; align-items: center; justify-content: center; font-size: 1.5rem; box-shadow: 0 8px 24px rgba(4,51,195,.4); }
		#offcanvasChat .msgs { overflow-y: auto; }
		#offcanvasChat .bub { max-width: 88%; padding: .55rem .8rem; border-radius: 1rem; font-size: .9rem; white-space: pre-line; }
		#offcanvasChat .bub.me { align-self: flex-end; background: #0433C3; color: #fff; border-bottom-right-radius: .25rem; }
		#offcanvasChat .bub.bot { align-self: flex-start; background: var(--bs-tertiary-bg); border-bottom-left-radius: .25rem; }
		.lp-mock { border-radius: 1.6rem; box-shadow: 0 24px 60px rgba(4,51,195,.18); height: auto; background: #F3F5FD; }
		.lp-mocks { position: relative; display: flex; justify-content: center; padding-bottom: 2.5rem; }
		.lp-mocks .m1 { width: 56%; position: relative; z-index: 1; }
		.lp-mocks .m2 { width: 56%; margin: 3rem 0 0 -12%; }
		@media (max-width: 575px) { .lp-mocks .m1, .lp-mocks .m2 { width: 62%; } .lp-mocks .m2 { margin-left: -24%; } }
		.lp-band { background: linear-gradient(135deg, #0433C3, #6F2C91 70%, #C2255C); color: #fff; border-radius: 1.5rem; }
		.lp-band-red { background: linear-gradient(135deg, #8E0909, #E30E0E); color: #fff; border-radius: 1.5rem; }
		.lp-gw { display: flex; align-items: center; gap: .6rem; padding: .7rem 1rem; border: 1px solid var(--bs-border-color); border-radius: .9rem; font-weight: 600; background: var(--bs-body-bg); }
		.lp-gw i { font-size: 1.4rem; }
		.lp-img { border-radius: 1.4rem; object-fit: cover; width: 100%; box-shadow: 0 16px 40px rgba(4,51,195,.18); }
		.lp-th img { height: 170px; object-fit: cover; border-radius: 1rem 1rem 0 0; }
		.lp-testi { border-left: 4px solid var(--xm-red); }
		.accordion-button:not(.collapsed) { color: var(--xm-blue); background: rgba(4,51,195,.06); }
		.lp-pill { display: inline-flex; align-items: center; gap: .4rem; padding: .35rem .8rem; border-radius: 999px; background: rgba(4,51,195,.08); color: var(--xm-blue); font-weight: 600; font-size: .82rem; margin: .2rem; }
		[data-bs-theme=dark] .lp-pill { color: #9DB1FF; background: rgba(124,151,255,.14); }
		@media (max-width: 991px) { .lp-photos { min-height: 380px; } .lp-photos .ph1 { height: 300px; } .lp-photos .ph2 { height: 200px; } .lp-section { padding: 3rem 0; } }
	</style>
</head>
<body>
	<?php include "partials/public-header.php"; ?>

	<main id="contenu" tabindex="-1">
		<!-- HERO -->
		<section class="lp-hero">
			<div class="container py-5">
				<div class="row align-items-center g-5 py-lg-4">
					<div class="col-lg-6">
						<span class="lp-kicker mb-3"><i class="bi bi-shield-heart"></i> Espace sûr · confidentiel · bienveillant</span>
						<h1 class="display-5 mb-3">Xomiton : Votre Espace safe d’apprentissage et d’appui holistique</h1>
						<p class="lead mb-4">Filles et femmes LBTQ+ ? Rejoignez notre communauté bienveillante et accédez à des ressources, trouvez du soutien et faites entendre votre voix en toute sécurité.</p>
						<div class="d-flex flex-wrap gap-2 mb-4">
							<?php if ($u): ?>
								<a class="btn btn-light btn-lg fw-bold text-primary" href="fil.php"><i class="bi bi-house-heart me-2"></i>Mon espace</a>
							<?php else: ?>
								<a class="btn btn-light btn-lg fw-bold text-primary" href="sign-up.php"><i class="bi bi-person-plus me-2"></i>Créer un compte</a>
								<a class="btn btn-outline-light btn-lg" href="sign-in.php"><i class="bi bi-box-arrow-in-right me-2"></i>Se connecter</a>
							<?php endif; ?>
							<a class="btn btn-danger btn-lg fw-bold" href="signalement.php"><i class="bi bi-megaphone me-2"></i>Signaler</a>
						</div>
						<p class="fst-italic mb-0" style="color:rgba(255,255,255,.9)"><i class="bi bi-chat-quote-fill me-2"></i>« Enfin un espace où je me sens en sécurité et comprise. »</p>
					</div>
					<div class="col-lg-6">
						<div class="lp-photos">
							<img class="ph ph1" src="<?= img('05.jpg') ?>" alt="Deux femmes qui se soutiennent">
							<img class="ph ph2" src="<?= img('04.jpg') ?>" alt="Communauté Xomiton">
							<div class="badge-float" style="left:4%;top:12%"><i class="bi bi-heart-fill text-danger me-1"></i> Un.e conseiller.ère est disponible</div>
							<div class="badge-float" style="right:2%;bottom:6%"><i class="bi bi-geo-alt-fill text-primary me-1"></i> Votre signalement a été reçu</div>
						</div>
					</div>
				</div>
			</div>
			<div class="xm-rainbow-line"></div>
		</section>

		<!-- VALEURS -->
		<section class="lp-section" id="valeurs">
			<div class="container">
				<div class="text-center mx-auto mb-5" style="max-width:760px">
					<h2 class="lp-title">Un soutien à portée de main</h2>
					<p class="text-secondary mb-0">Un espace d’échange, de soutien et d’actions pour votre bien-être.<br>Nous répondons à toutes vos questions, gratuitement et en toute confidentialité.</p>
				</div>
				<div class="row g-4 g-lg-5">
					<?php foreach ([
						['02.svg', 'Icône de ressources', 'Thématiques &amp; Ressources', 'Explorez nos fiches d’informations complètes sur les droits humains, la lutte contre les VBG, la santé sexuelle et reproductive et bien d’autres sujets essentiels.'],
						['07.svg', 'Icône de communauté', 'Réseau et Communautés', 'Connectez-vous avec d’autres membres, partagez vos expériences et bâtissez des liens solides au sein de nos groupes de discussion thématiques et sécurisés.'],
						['01.svg', 'Icône de sécurité', 'Confidentialité et Sécurité', 'Vos échanges sont protégés. Signalez les abus en toute confiance, votre anonymat est garanti et nos conseiller(ère)s sont disponibles pour vous écouter et vous assister.'],
					] as $v): ?>
						<div class="col-md-4 text-center">
							<img class="mb-4" style="height:100px" src="<?= asset('images/elements/' . $v[0]) ?>" alt="<?= $v[1] ?>">
							<h4><?= $v[2] ?></h4>
							<p class="mb-0"><?= $v[3] ?></p>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</section>

		<!-- JE ME POSE DES QUESTIONS -->
		<section class="lp-section pt-0" id="questions">
			<div class="container">
				<div class="row g-4 g-lg-5 align-items-center">
					<div class="col-lg-4">
						<h2 class="lp-title">Je me pose des questions</h2>
						<p class="mb-4">Tu as une question sur l’orientation sexuelle et l’identité de genre, les VBG, la SSR ou les droits humains en général ? Tu es lesbienne, bi, trans, queers, intersexe ou en questionnement ?</p>
						<a class="btn btn-primary" href="thematiques.php">Explorer les thématiques</a>
					</div>
					<div class="col-lg-8">
						<div class="card card-body bg-light shadow-none border-0 p-4 p-sm-5 pb-sm-0 overflow-hidden" style="border-radius:1.1rem">
							<div class="row g-4 align-items-center">
								<div class="col-md-6">
									<img style="height:50px" src="<?= asset('images/elements/05.svg') ?>" alt="">
									<h4 class="mt-4">Je veux témoigner ou signaler un abus</h4>
									<p class="mb-4">Tu as vécu ou été témoin de violences ou de discrimination ? Signale-nous ton cas pour que nous puissions t’assister et t’accompagner.</p>
									<a class="btn btn-danger mb-3" href="signalement.php">Signaler un abus</a>
								</div>
								<div class="col-md-6 text-end">
									<img class="img-fluid rounded-top-4" style="max-height:300px;object-fit:cover;width:100%" src="<?= img('6033855.jpg') ?>" alt="Signaler un abus">
								</div>
							</div>
						</div>
					</div>
					<?php foreach ([
						['03.svg', 'Chat d’écoute et d’assistance', 'Discutez en direct avec nos expert.e.s pour obtenir des réponses immédiates, un conseil ou simplement une oreille attentive. Votre bien-être est notre priorité.'],
						['09.svg', 'Actualités et Événements', 'Restez informés des dernières nouvelles, des campagnes de sensibilisation, des ateliers et des événements organisés par et pour la communauté.'],
						['06.svg', 'Forums de discussion et Groupes de soutien', 'Trouvez rapidement des réponses aux questions fréquentes sur vos droits, les démarches à suivre et les ressources disponibles sur Xomiton.'],
					] as $c): ?>
					<div class="col-md-4">
						<div class="card card-body bg-light shadow-none border-0 p-4 p-lg-5 h-100" style="border-radius:1.1rem">
							<div><img style="height:50px" src="<?= asset('images/elements/' . $c[0]) ?>" alt=""></div>
							<h4 class="mt-4"><?= $c[1] ?></h4>
							<p class="mb-0"><?= $c[2] ?></p>
						</div>
					</div>
					<?php endforeach; ?>
				</div>
			</div>
		</section>

		<!-- RESEAU SOCIAL -->
		<section class="lp-section bg-light" id="reseau">
			<div class="container">
				<div class="row align-items-center g-5">
					<div class="col-lg-6 order-lg-2">
						<div class="lp-eyebrow mb-2">Rejoignez-nous maintenant</div>
						<h2 class="lp-title mb-3">Échangez, partagez, grandissez ensemble</h2>
						<p class="text-secondary">Partagez vos expériences, participez à des discussions enrichissantes, et contribuez à faire de Xomiton un espace d’accompagnement puissant. Chaque voix compte pour bâtir un avenir plus juste et inclusif.</p>
						<div class="row g-3 mt-1">
							<?php foreach ([
								['bi-newspaper', 'Fil d’actualité', 'Publiez, commentez, partagez témoignages et ressources.'],
								['bi-chat-dots', 'Chat Messenger', 'Messages privés et conversations de groupe en temps réel.'],
								['bi-people', 'Groupes & pages', 'Cercles thématiques, pages d’organisations partenaires.'],
								['bi-calendar-event', 'Événements', 'Ateliers, webinaires, dépistages, self-défense.'],
								['bi-chat-square-text', 'Forums', 'Parler librement, droits, santé, sororité.'],
								['bi-person-plus', 'Qui suivre', 'Suggestions bienveillantes et invitations.'],
							] as $f): ?>
								<div class="col-sm-6">
									<div class="lp-card p-3 bg-body">
										<i class="bi <?= $f[0] ?> fs-4 text-primary"></i>
										<h6 class="mt-2 mb-1"><?= $f[1] ?></h6>
										<p class="small text-secondary mb-0"><?= $f[2] ?></p>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
					<div class="col-lg-6 order-lg-1">
						<div class="lp-mocks">
							<img class="lp-mock m1" src="<?= img('mockup-B.gif') ?>" alt="Aperçu animé : fil d’actualité, fiche thématique et parcours" width="480" height="640">
							<img class="lp-mock m2" src="<?= img('mockup-C.gif') ?>" alt="Aperçu animé : discussion dans le cercle sororité" width="480" height="640">
						</div>
					</div>
				</div>
			</div>
		</section>

		<!-- RESSOURCES -->
		<section class="lp-section" id="ressources">
			<div class="container">
				<div class="text-center mx-auto mb-5" style="max-width:700px">
					<div class="lp-eyebrow mb-2">Ressources &amp; thématiques</div>
					<h2 class="lp-title">Apprendre pour mieux se protéger et décider</h2>
					<p class="text-secondary">Fiches claires et fiables sur les violences, la santé, le droit, la sexualité et l’autonomie, rédigées pour vous.</p>
				</div>
				<div class="row g-4">
					<?php foreach ($themes as $t): $c = $cats[$t['cat']] ?? ['Thème', 'bi-grid', 'primary']; ?>
						<div class="col-md-6 col-lg-4">
							<a class="lp-card lp-th d-block bg-body text-reset overflow-hidden" href="sign-in.php?next=thematiques.php">
								<img class="w-100" src="<?= img($t['cover']) ?>" alt="Couverture de la fiche : <?= e($t['title']) ?>" title="<?= e($t['title']) ?>" loading="lazy" width="640" height="427">
								<div class="p-3">
									<span class="badge bg-<?= $c[2] ?> bg-opacity-10 text-<?= $c[2] ?> mb-2"><i class="bi <?= $c[1] ?> me-1"></i><?= e($c[0]) ?></span>
									<h6 class="mb-1"><?= e($t['title']) ?></h6>
									<p class="small text-secondary mb-2"><?= e($t['summary']) ?></p>
									<span class="small fw-bold text-primary d-inline-flex align-items-center"><i class="bi bi-arrow-right me-1" aria-hidden="true"></i>Lire la suite</span>
								</div>
							</a>
						</div>
					<?php endforeach; ?>
				</div>
				<div class="text-center mt-4">
					<a class="btn btn-primary-soft" href="blog.php">Lire les actualités <i class="bi bi-arrow-right ms-1"></i></a>
				</div>
			</div>
		</section>

		<!-- SIGNALEMENT + SOS -->
		<section class="lp-section pt-0" id="alerte">
			<div class="container">
				<div class="lp-band-red p-4 p-lg-5">
					<div class="row align-items-center g-5">
						<div class="col-lg-6">
							<div class="lp-kicker mb-3"><i class="bi bi-broadcast"></i> Alerte &amp; signalement</div>
							<h2 class="lp-title text-white mb-3" style="font-size:clamp(1.35rem,2.3vw,1.85rem)">En danger ? Besoin d’aide ?<br>Alertez en quelques secondes.</h2>
							<p style="color:rgba(255,255,255,.9)">Dénonciation, expression d’un besoin ou SOS avec votre position GPS : votre message est routé automatiquement vers les bonnes personnes de l’annuaire (point focal, juriste, psychologue…).</p>
							<ul class="list-unstyled mb-4">
								<li class="mb-2"><i class="bi bi-check-circle-fill me-2"></i>Signalement 100 % anonyme possible</li>
								<li class="mb-2"><i class="bi bi-check-circle-fill me-2"></i>Localisation partagée uniquement avec votre accord</li>
							</ul>
							<div class="d-flex flex-wrap gap-2">
								<a class="btn btn-light fw-bold text-danger" href="signalement.php"><i class="bi bi-megaphone me-1"></i>Faire un signalement</a>
								<a class="btn btn-outline-light" href="urgences.php"><i class="bi bi-telephone me-1"></i>Contacts d’urgence</a>
							</div>
						</div>
						<div class="col-lg-6">
							<div class="phone"><div class="scr sos-screen">
								<div class="fw-bold">Alerte SOS</div>
								<div class="sos-btn">SOS</div>
								<div><i class="bi bi-geo-alt-fill me-1"></i>Position : Cotonou, Littoral</div>
								<div class="opacity-75">Envoi vers : point focal · psychologue · juriste</div>
							</div></div>
						</div>
					</div>
				</div>
			</div>
		</section>

		<!-- NOS EXPERTS -->
		<section class="py-4 py-sm-5 position-relative" id="assistant">
			<div class="position-absolute top-0 start-0 mt-n5 pt-n5 d-none d-lg-block">
				<img class="h-300px blur-9" src="<?= asset('images/elements/06.svg') ?>" alt="">
			</div>
			<div class="container">
				<div class="row">
					<div class="col-lg-10 mx-auto">
						<div class="row g-4 align-items-center position-relative z-index-1">
							<div class="col-md-6">
								<div class="me-4">
									<h2 class="h1 mb-4" style="font-size:clamp(1.6rem,2.6vw,2.15rem)">Nos Expert.e.s sont<br>prêt.e.s à vous aider</h2>
									<p>Nous disposons des Expert.e.s juridique, psychologique, gynécologue avec des approches thérapeutiques pour vos besoins.</p>
									<div class="mb-3 d-flex align-items-start">
										<img class="h-40px" src="<?= asset('images/elements/04.svg') ?>" alt="">
										<div class="ms-4"><h5 class="mt-2">Besoin de parler à quelqu’un ?</h5><p class="mb-0">Assistance en ligne 24 h/24 et 7 j/7 pour vous apporter un soutien.</p></div>
									</div>
									<div class="mb-3 d-flex align-items-start">
										<img class="h-40px" src="<?= asset('images/elements/10.svg') ?>" alt="">
										<div class="ms-4"><h5 class="mt-2">Conseils personnalisés</h5><p class="mb-0">C’est anonyme et entièrement gratuit. Si vous avez besoin de parler à quelqu’un, nous sommes là pour vous écouter et vous aider à vous sentir mieux.</p></div>
									</div>
									<a class="btn btn-primary" data-bs-toggle="offcanvas" href="#offcanvasChat" role="button" aria-controls="offcanvasChat"><i class="bi bi-chat-heart me-2"></i>Discuter avec l’assistant</a>
								</div>
							</div>
							<div class="col-md-6 col-lg-5 position-relative">
								<img class="w-100 lp-mock" src="<?= img('mockup-A.gif') ?>" alt="Aperçu animé de l’assistant Xomiton : question, réponse, mise en relation avec un·e conseiller·ère" width="480" height="640">
							</div>
						</div>
					</div>
				</div>
			</div>
		</section>

		<!-- DO GLEGBE : GESTION DE L'HYGIENE MENSTRUELLE -->
		<section class="lp-section dg-section" id="fonctionnalites" aria-labelledby="dgTitre">
			<div class="container position-relative">
				<div class="row align-items-center g-4 g-lg-5">
					<div class="col-lg-4">
						<div class="dg-logo-card">
							<img src="<?= img('doglegbexo.png') ?>" alt="Logo Do Glegbe : goutte de sang avec symbole de genre et lune" title="Do Glegbe – Gestion de l’Hygiène Menstruelle" width="314" height="258" loading="lazy">
							<span class="dg-badge"><i class="bi bi-droplet-half me-1" aria-hidden="true"></i>Droit fondamental</span>
						</div>
					</div>
					<div class="col-lg-8">
						<div class="dg-eyebrow mb-2">Do Glegbe · La Gestion de l’Hygiène Menstruelle</div>
						<h2 class="dg-lead mb-3" id="dgTitre">Avoir ses règles est un simple fait biologique qui constitue trop souvent un obstacle à la santé, à la dignité et au respect des droits des filles et des femmes…</h2>
						<p class="text-secondary mb-3">Une bonne gestion de l’hygiène menstruelle est un droit humain fondamental, indispensable à la santé, au bien-être et à l’égalité des genres. Elle permet de :</p>
						<div class="row g-3 mb-4">
							<?php foreach ([['bi-shield-plus', 'Prévenir les infections'], ['bi-people', 'Assurer la participation sociale'], ['bi-emoji-smile', 'Renforcer la confiance en soi'], ['bi-chat-heart', 'Lutter contre la stigmatisation']] as $dg): ?>
							<div class="col-sm-6"><div class="dg-benefit"><span class="dg-ico" aria-hidden="true"><i class="bi <?= $dg[0] ?>"></i></span><b class="align-self-center"><?= $dg[1] ?></b></div></div>
							<?php endforeach; ?>
						</div>
						<div class="d-flex flex-wrap gap-2">
							<a class="btn btn-dg" href="do-glegbe.php">Découvrir Do Glegbe <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i></a>
							<a class="btn btn-outline-danger" href="sign-in.php?next=thematiques.php%3Fcat%3Dregles">Fiches sur les règles</a>
						</div>
					</div>
				</div>
			</div>
		</section>

		<!-- FAQ -->
		<section class="lp-section" id="faq">
			<div class="container" style="max-width:860px">
				<div class="text-center mb-5">
					<div class="lp-eyebrow mb-2">Questions fréquentes</div>
					<h2 class="lp-title">Vous vous demandez…</h2>
				</div>
				<div class="accordion" id="lpFaq">
					<?php foreach ($faq as $i => $f): ?>
						<div class="accordion-item">
							<h3 class="accordion-header"><button class="accordion-button <?= $i ? 'collapsed' : '' ?> fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#faq<?= $i ?>" aria-expanded="<?= $i ? 'false' : 'true' ?>"><?= e($f[0]) ?></button></h3>
							<div id="faq<?= $i ?>" class="accordion-collapse collapse <?= $i ? '' : 'show' ?>" data-bs-parent="#lpFaq"><div class="accordion-body"><?= e($f[1]) ?></div></div>
						</div>
					<?php endforeach; ?>
				</div>
				<div class="text-center mt-4"><a href="help.php" class="btn btn-primary-soft">Voir tout le centre d’aide</a></div>
			</div>
		</section>

		<!-- CTA FINAL -->
		<section class="py-4 py-sm-5">
			<div class="container">
				<div class="card card-body bg-light shadow-none border-0 p-4 p-sm-5 text-center">
					<div class="col-lg-8 mx-auto">
						<h2 class="h1">Prête à nous rejoindre ?</h2>
						<p class="lead mb-4">Créez votre compte Xomiton et accédez instantanément à un réseau d’entraide et d’information conçu pour vous.</p>
						<div class="d-flex flex-wrap justify-content-center gap-2">
							<a class="btn btn-primary btn-lg" href="sign-up.php">Créer un compte</a>
							<a class="btn btn-outline-primary btn-lg" href="sign-in.php">Se connecter</a>
						</div>
						<ul class="nav nav-divider justify-content-center mt-4">
							<li class="nav-item"> Gratuit</li>
							<li class="nav-item"> 100% Sécurisé </li>
						</ul>
					</div>
				</div>
			</div>
		</section>
	</main>

	<?php include "partials/public-footer.php"; ?>
	<button class="xm-chat-fab btn btn-primary no-print" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasChat" aria-controls="offcanvasChat" aria-label="Ouvrir l’assistant"><i class="bi bi-chat-heart-fill" aria-hidden="true"></i></button>
	<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasChat" aria-labelledby="offcanvasChatLabel" style="--bs-offcanvas-width:420px">
		<div class="offcanvas-header border-bottom">
			<div class="d-flex align-items-center gap-2"><span class="icon-lg rounded-circle text-white bg-primary"><i class="bi bi-robot"></i></span><div><h5 class="offcanvas-title mb-0" id="offcanvasChatLabel">Assistant Xomiton</h5><small class="text-secondary">VBG, santé sexuelle et droits · <span class="text-success">en ligne</span></small></div></div>
			<button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fermer"></button>
		</div>
		<div class="offcanvas-body d-flex flex-column p-0">
			<div class="msgs flex-grow-1 p-3 d-flex flex-column gap-2" id="lpChatBox" aria-live="polite"></div>
			<div class="px-3 pb-2 d-flex gap-2 overflow-auto">
				<?php foreach (['Comment porter plainte ?', 'Contraception d’urgence', 'Que faire après une agression ?'] as $q): ?><button type="button" class="btn btn-sm btn-primary-soft text-nowrap lp-chip"><?= e($q) ?></button><?php endforeach; ?>
			</div>
			<form id="lpChatForm" class="border-top p-3 d-flex gap-2" autocomplete="off">
				<input class="form-control" id="lpChatInput" placeholder="Posez votre question…" aria-label="Votre message" maxlength="500">
				<button class="btn btn-primary" type="submit" aria-label="Envoyer"><i class="bi bi-send-fill"></i></button>
			</form>
			<p class="small text-secondary px-3 pb-3 mb-0">En danger immédiat ? <a class="text-danger fw-bold" href="sos.php">Envoyer un SOS</a></p>
		</div>
	</div>
	<?php include "partials/footer-scripts.php"; ?>
	<script>
	(function () {
		var box = document.getElementById('lpChatBox'), input = document.getElementById('lpChatInput');
		function add(who, text, cta) {
			var d = document.createElement('div'); d.className = 'bub ' + who; d.textContent = text; box.appendChild(d);
			if (cta) { var a = document.createElement('a'); a.className = 'btn btn-sm align-self-start ' + (cta[1] === 'sos.php' ? 'btn-danger' : 'btn-primary-soft'); a.href = cta[1]; a.textContent = cta[0]; box.appendChild(a); }
			box.scrollTop = box.scrollHeight;
		}
		function ask(t) { t = t.trim(); if (!t) return; add('me', t); input.value = ''; setTimeout(function () { var r = XM.assistant.reply(t); add('bot', r.a, r.cta); }, 500); }
		add('bot', 'Bonjour, je suis l’assistant Xomiton. Posez-moi vos questions sur les violences, le consentement, la santé sexuelle et vos droits : je réponds sans jugement, en toute confidentialité.');
		document.getElementById('lpChatForm').addEventListener('submit', function (e) { e.preventDefault(); ask(input.value); });
		document.querySelectorAll('.lp-chip').forEach(function (c) { c.addEventListener('click', function () { ask(c.textContent); }); });
	})();
	</script>
</body>
</html>
