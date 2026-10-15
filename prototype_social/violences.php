<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/x_complement.php';
require_login();
$pageTitle = 'Comprendre les violences';
$nav = 'violences';
$cats = x_violence_categories();
$signs = [
    'physique' => ['Blessures inexpliquées ou cachées', 'Peur de rentrer chez soi', 'Isolement physique, séquestration'],
    'psychologique' => ['Insultes, humiliations, dévalorisation constante', 'Menaces de révéler l’orientation (outing)', 'Contrôle des fréquentations'],
    'sexuelle' => ['Rapports imposés ou « correctifs »', 'Attouchements non consentis', 'Chantage sexuel en échange d’un service'],
    'economique' => ['Privation d’argent ou de documents', 'Interdiction de travailler ou d’étudier', 'Expulsion du domicile'],
    'numerique' => ['Diffusion de photos sans accord', 'Harcèlement sur les réseaux', 'Surveillance du téléphone, doxxing'],
];
$cycle = [
    ['Tension', 'bi-cloud-lightning', 'warning', 'L’atmosphère devient pesante, on « marche sur des œufs ».'],
    ['Agression', 'bi-exclamation-triangle-fill', 'danger', 'Explosion : violence verbale, physique, sexuelle ou numérique.'],
    ['Justification', 'bi-chat-left-dots', 'secondary', '« C’est ta faute », excuses, minimisation, culpabilisation.'],
    ['Lune de miel', 'bi-heart-half', 'primary', 'Promesses, cadeaux, calme… jusqu’à la prochaine tension.'],
];
$quiz = [
    'Avez-vous peur de la réaction d’une personne de votre entourage ?',
    'Quelqu’un contrôle-t-il votre téléphone, vos messages ou vos sorties ?',
    'Vous a-t-on menacée de révéler votre orientation ou identité ?',
    'Vous a-t-on déjà humiliée, insultée ou rabaissée régulièrement ?',
    'Quelqu’un vous a-t-il forcée à un acte sexuel ou à un contact non voulu ?',
    'Vous prive-t-on d’argent, de documents ou de logement ?',
    'Avez-vous déjà été frappée, bousculée ou enfermée ?',
    'Vous sentez-vous coupable ou responsable de ce qui vous arrive, alors que vous n’avez rien demandé ?',
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<?php include("partials/title-meta.php"); ?>
	<?php include("partials/head-css.php"); ?>
	<style>.vstack > * { flex-grow:0 !important; }</style>
</head>
<body>
	<?php include("partials/header.php"); ?>
	<main id="contenu" tabindex="-1">
		<div class="container">
			<div class="row g-4">
				<?php include("partials/sidenav.php"); ?>
				<div class="col-lg-9 vstack gap-4">
					<?= flash_html() ?>

					<!-- Héro -->
					<div class="card overflow-hidden">
						<div class="row g-0">
							<div class="col-md-5"><div class="h-100" style="min-height:220px;background:url(<?= img('hero-13.jpg') ?>) center/cover"></div></div>
							<div class="col-md-7 card-body p-4">
								<span class="badge bg-danger-soft text-danger mb-2">Comprendre pour agir</span>
								<h1 class="h3">Comprendre les violences</h1>
								<p class="mb-3">Une violence basée sur le genre (VBG) vise une personne en raison de son genre, de son orientation ou de son identité. Vous n’êtes pas seule, et ce n’est jamais votre faute.</p>
								<div class="d-flex flex-wrap gap-2">
									<a class="btn btn-danger" href="sos.php"><i class="bi bi-broadcast me-1"></i>SOS</a>
									<a class="btn btn-primary" href="signalement.php"><i class="bi bi-megaphone me-1"></i>Signaler</a>
									<a class="btn btn-primary-soft" href="assistant.php"><i class="bi bi-robot me-1"></i>Parler à l’assistant</a>
								</div>
							</div>
						</div>
					</div>

					<!-- 5 formes -->
					<div>
						<h2 class="h5 mb-3">Les 5 formes de violences</h2>
						<div class="row g-3">
							<?php foreach ($cats as $c): ?>
							<div class="col-sm-6 col-xl-4">
								<div class="card card-body h-100">
									<div class="d-flex align-items-center gap-2 mb-2"><span class="icon-md bg-primary bg-opacity-10 text-primary rounded-circle"><i class="bi <?= e($c[2]) ?>"></i></span><h3 class="h6 mb-0"><?= e($c[1]) ?></h3></div>
									<p class="small text-secondary"><?= e($c[3]) ?></p>
									<div class="small fw-bold mb-1">Signes à repérer</div>
									<ul class="small mb-0 ps-3">
										<?php foreach ($signs[$c[0]] as $s): ?><li><?= e($s) ?></li><?php endforeach; ?>
									</ul>
								</div>
							</div>
							<?php endforeach; ?>
						</div>
					</div>

					<!-- Cycle -->
					<div class="card card-body">
						<h2 class="h5">Le cycle de la violence</h2>
						<p class="small text-secondary">Un schéma qui se répète et s’accélère souvent avec le temps. Le reconnaître aide à en sortir.</p>
						<div class="xm-cycle row g-3 position-relative">
							<?php foreach ($cycle as $i => $s): ?>
							<div class="col-6 col-lg-3">
								<div class="h-100 border rounded-3 p-3 text-center position-relative bg-<?= $s[2] ?> bg-opacity-10">
									<span class="position-absolute top-0 start-0 translate-middle badge rounded-pill bg-<?= $s[2] ?>"><?= $i + 1 ?></span>
									<i class="bi <?= $s[1] ?> fs-2 text-<?= $s[2] ?>"></i>
									<div class="fw-bold mt-1"><?= e($s[0]) ?></div>
									<div class="small text-secondary"><?= e($s[3]) ?></div>
								</div>
								<?php if ($i < 3): ?><div class="text-center d-none d-lg-block xm-cycle-arrow"><i class="bi bi-arrow-right"></i></div><?php endif; ?>
							</div>
							<?php endforeach; ?>
						</div>
						<div class="text-center small mt-3 text-secondary"><i class="bi bi-arrow-repeat me-1"></i>… puis retour à la tension : le cycle recommence.</div>
					</div>

					<!-- À faire / à ne pas faire -->
					<div class="row g-3">
						<div class="col-md-6"><div class="card card-body h-100 border-success">
							<h2 class="h6 text-success"><i class="bi bi-check-circle-fill me-1"></i>Que faire ?</h2>
							<ul class="small mb-0 ps-3 vstack gap-1">
								<li>Vous mettre en sécurité et prévenir une personne de confiance.</li>
								<li>Garder des preuves : captures d’écran, certificats médicaux, dates.</li>
								<li>Consulter un centre de santé (soins, contraception d’urgence, PEP sous 72 h).</li>
								<li>Contacter une personne de l’<a href="annuaire.php">annuaire</a> ou utiliser le <a href="signalement.php">signalement anonyme</a>.</li>
								<li>Préparer un <a href="urgences.php#plan">plan de sécurité</a>.</li>
							</ul></div></div>
						<div class="col-md-6"><div class="card card-body h-100 border-danger">
							<h2 class="h6 text-danger"><i class="bi bi-x-circle-fill me-1"></i>Que ne pas faire ?</h2>
							<ul class="small mb-0 ps-3 vstack gap-1">
								<li>Vous blâmer : la responsabilité est toujours celle de l’auteur·e.</li>
								<li>Payer un chantage ou négocier seule avec la personne violente.</li>
								<li>Supprimer les preuves ou les messages.</li>
								<li>Confronter l’auteur·e sans être en sécurité.</li>
								<li>Rester silencieuse en pensant que « ça passera ».</li>
							</ul></div></div>
					</div>

					<!-- Loi -->
					<div class="card card-body">
						<div class="d-flex align-items-start gap-3">
							<span class="icon-lg bg-info bg-opacity-10 text-info rounded-circle flex-shrink-0"><i class="bi bi-bank"></i></span>
							<div>
								<h2 class="h5">Que dit la loi ?</h2>
								<p class="small mb-2">Au Bénin, la <strong>loi n°2011-26 du 9 janvier 2012</strong> réprime les violences faites aux femmes : harcèlement sexuel, violences conjugales, viol, mariage forcé. Le Code pénal prévoit des circonstances aggravantes, et la Constitution garantit l’égalité devant la loi.</p>
								<a class="btn btn-sm btn-info-soft" href="lois-benin.php">Lire les lois et codes du Bénin <i class="bi bi-arrow-right ms-1"></i></a>
							</div>
						</div>
					</div>

					<!-- Auto-évaluation -->
					<div class="card" id="quiz">
						<div class="card-header"><h2 class="h5 mb-0"><i class="bi bi-clipboard2-check me-2 text-primary"></i>Auto-évaluation confidentielle</h2></div>
						<div class="card-body">
							<p class="small text-secondary">8 questions pour faire le point. Vos réponses restent sur cet écran : rien n’est enregistré ni transmis. Ce n’est pas un diagnostic.</p>
							<form id="quizForm" class="vstack gap-3">
								<?php foreach ($quiz as $i => $q): ?>
								<div class="d-sm-flex align-items-center justify-content-between gap-3 border-bottom pb-2">
									<div class="mb-2 mb-sm-0"><span class="text-secondary me-1"><?= $i + 1 ?>.</span><?= e($q) ?></div>
									<div class="btn-group flex-shrink-0" role="group" aria-label="Question <?= $i + 1 ?>">
										<input type="radio" class="btn-check" name="q<?= $i ?>" id="q<?= $i ?>y" value="1"><label class="btn btn-sm btn-outline-primary" for="q<?= $i ?>y">Oui</label>
										<input type="radio" class="btn-check" name="q<?= $i ?>" id="q<?= $i ?>n" value="0"><label class="btn btn-sm btn-outline-primary" for="q<?= $i ?>n">Non</label>
									</div>
								</div>
								<?php endforeach; ?>
								<div class="d-flex gap-2"><button class="btn btn-primary" type="submit">Voir mon résultat</button><button class="btn btn-light" type="reset" id="quizReset">Recommencer</button></div>
							</form>
							<div id="quizResult" class="alert mt-3 d-none" role="status"></div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</main>
	<style>
		.xm-cycle-arrow { position:absolute; right:-14px; top:50%; transform:translateY(-50%); z-index:2; color:var(--bs-secondary-color); font-size:1.4rem; }
		.xm-cycle > div { position:relative; }
	</style>
	<?php include("partials/modals.php"); include("partials/footer-scripts.php"); ?>
	<script>
	(function () {
		var f = document.getElementById('quizForm'), box = document.getElementById('quizResult');
		f.addEventListener('submit', function (e) {
			e.preventDefault();
			var answered = f.querySelectorAll('input[type=radio]:checked').length;
			if (answered < 8) { XM.toast('Merci de répondre aux 8 questions.', 'warning'); return; }
			var s = 0; f.querySelectorAll('input[type=radio]:checked').forEach(function (r) { s += +r.value; });
			var t, cls, msg;
			if (s <= 1) { cls = 'alert-success'; t = 'Peu d’indices de violence'; msg = 'Rien ne laisse penser à une situation préoccupante, mais si un doute persiste, parlez-en à une personne de confiance ou à l’assistant.'; }
			else if (s <= 4) { cls = 'alert-warning'; t = 'Quelques signes méritent votre attention'; msg = 'Certaines situations décrites peuvent être des formes de violence. Vous pouvez en parler à une pair animatrice ou utiliser le signalement anonyme.'; }
			else { cls = 'alert-danger'; t = 'Plusieurs signes préoccupants'; msg = 'Vos réponses évoquent une situation qui peut vous mettre en danger. Vous méritez d’être protégée : contactez une personne de l’annuaire, et en cas de danger immédiat, utilisez le bouton SOS.'; }
			box.className = 'alert mt-3 ' + cls;
			box.innerHTML = '<h3 class="h6 mb-1"></h3><p class="mb-2 small"></p><div class="d-flex flex-wrap gap-2"><a class="btn btn-sm btn-danger" href="sos.php">SOS</a><a class="btn btn-sm btn-primary" href="signalement.php">Signaler</a><a class="btn btn-sm btn-light" href="annuaire.php">Annuaire</a><a class="btn btn-sm btn-light" href="assistant.php">Assistant</a></div><p class="small mt-2 mb-0 opacity-75">Ce résultat est indicatif et ne remplace pas l’avis d’un·e professionnel·le. Il n’a pas été enregistré.</p>';
			box.querySelector('h3').textContent = t; box.querySelector('p').textContent = msg;
			box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
		});
		f.addEventListener('reset', function () { box.className = 'alert mt-3 d-none'; });
	})();
	</script>
</body>
</html>
