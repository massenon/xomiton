<?php
require __DIR__ . '/inc/bootstrap.php';
$pageTitle = 'Inscription';
$open = (bool)setting('registrations_open', true);
$errors = [];
$done = false;

if ($open && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) $errors[] = 'Session expirée, veuillez réessayer.';
    
    // Récupération des champs des 4 étapes
    $first_name = trim($_POST['first_name'] ?? ''); 
    $last_name = trim($_POST['last_name'] ?? '');
    $name = trim($first_name . ' ' . $last_name);
    $pseudo = trim($_POST['username'] ?? ''); 
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pwd = $_POST['password'] ?? ''; 
    $confirm_pwd = $_POST['confirm_password'] ?? '';
    $phone = trim($_POST['phone_number'] ?? '');
    
    // Validations internes
    if (mb_strlen($pseudo) < 3) $errors[] = 'Le pseudo doit contenir au moins 3 caractères.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Adresse e-mail invalide.';
    if ($pwd !== $confirm_pwd) $errors[] = 'Les mots de passe ne correspondent pas.';
    if (strlen($pwd) < 8 || !preg_match('/[A-Z]/', $pwd) || !preg_match('/\d/', $pwd)) {
        $errors[] = 'Mot de passe : 8 caractères min., une majuscule et un chiffre.';
    }
    if (empty($_POST['cgu'])) $errors[] = 'Vous devez accepter les conditions d\'utilisation.';
    
    foreach (store_all('users') as $r) {
        if (strtolower($r['email'] ?? '') === $email) $errors[] = 'Cette adresse e-mail est déjà utilisée.';
        if (strtolower($r['pseudo'] ?? '') === strtolower($pseudo)) $errors[] = 'Ce pseudonyme est déjà pris.';
    }

    if (!$errors) {
        $manual = (bool)setting('manual_validation', false);
        $type_membre = $_POST['type_membre'] ?? 'membre';
        $role = ($type_membre === 'pair_animateur') ? 'pair' : 'membre';
        
        $row = store_append('users', [
            'name' => $name, 
            'pseudo' => $pseudo, 
            'email' => $email, 
            'hash' => password_hash($pwd, PASSWORD_DEFAULT),
            'role' => $manual && $role === 'pair' ? 'membre' : $role, 
            'status' => ($manual || $role === 'pair') ? 'en_attente' : 'actif', 
            'zone' => $_POST['country_id'] ?? '', 
            'commune' => $_POST['city'] ?? '',
            'joined' => date('Y-m-d'), 
            'avatar' => random_int(0, 5), 
            'phone' => $phone, 
            'profile' => $type_membre,
            'gender' => $_POST['identite_genre'] ?? '',
            'orientation' => $_POST['orientation_sexuelle'] ?? ''
        ]);
        
        x_log('Inscription', $email);
        require_once __DIR__ . '/inc/x_mailer.php';
        x_mail_send_template('bienvenue', $email, ['user_name' => $name, 'user_first_name' => explode(' ', $name)[0], 'user_pseudo' => $pseudo, 'user_email' => $email, 'login_link' => x_site_url('sign-in.php')], ['context' => 'inscription']);
        $done = $row['status'] === 'actif' ? 'actif' : 'attente';
        if ($done === 'actif') { unset($row['hash']); $_SESSION['user'] = $row; session_regenerate_id(true); }
    }
}
$zones = x_zones();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<?php include("partials/title-meta.php"); ?>
	<?php include("partials/head-css.php"); ?>
    <style>
        .xm-stepper { counter-reset: step; }
        .xm-stepper__item { display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; font-weight: 600; color: var(--bs-secondary-color); }
        .xm-stepper__item.is-current { color: var(--bs-primary); }
        .xm-stepper__item.is-done { color: var(--bs-success); }
        .xm-stepper__dot { width: 28px; height: 28px; border-radius: 50%; background: var(--bs-tertiary-bg); display: flex; align-items: center; justify-content: center; font-size: 0.75rem; border: 1px solid var(--bs-border-color); }
        .xm-stepper__item.is-current .xm-stepper__dot { background: var(--bs-primary); color: #fff; border-color: var(--bs-primary); }
        .xm-stepper__item.is-done .xm-stepper__dot { background: var(--bs-success); color: #fff; border-color: var(--bs-success); }
    </style>
</head>
<body>
<main id="contenu" tabindex="-1">
	<div class="container-fluid p-0">
		<div class="row g-0 min-vh-100">
			<div class="col-lg-5 d-none d-lg-flex xm-auth-side align-items-end p-5" style="background-image:url(<?= img('hero-12.jpg') ?>)">
				<div class="text-white">
					<img src="<?= BASE ?>assets/images/xomiton/brand/logo-mono-blanc.svg" alt="Xomiton" height="44" class="mb-4">
					<h2 class="fw-bold">Rejoignez une communauté bienveillante.</h2>
					<ul class="list-unstyled lead">
						<li class="mb-2"><i class="bi bi-check-circle-fill me-2"></i>Pseudonyme : votre identité reste protégée</li>
						<li class="mb-2"><i class="bi bi-check-circle-fill me-2"></i>Ressources et outils disponibles hors connexion</li>
						<li class="mb-2"><i class="bi bi-check-circle-fill me-2"></i>Soutien psychologique, juridique et médical</li>
					</ul>
				</div>
			</div>
			<div class="col-lg-7 d-flex align-items-center">
				<div class="w-100 p-4 p-md-5 mx-auto" style="max-width:640px">
					<a href="landing.php" class="d-lg-none d-inline-block mb-4"><img src="<?= BASE ?>assets/images/logo.svg" alt="Xomiton" height="38"></a>

					<?php if (!$open): ?>
						<div class="text-center py-5">
							<div class="display-3 text-warning"><i class="bi bi-door-closed"></i></div>
							<h1 class="h3 fw-bold mt-3">Les inscriptions sont momentanément fermées</h1>
							<p class="text-secondary">L’équipe Xomiton a suspendu les nouvelles inscriptions. Revenez bientôt ou contactez-nous : <a href="mailto:<?= e(setting('contact_email', 'contact@xomiton.bj')) ?>"><?= e(setting('contact_email', 'contact@xomiton.bj')) ?></a>.</p>
							<a href="sign-in.php" class="btn btn-primary">Se connecter</a>
						</div>
					<?php elseif ($done): ?>
						<div class="text-center py-5">
							<div class="display-3 text-success"><i class="bi bi-patch-check-fill"></i></div>
							<h1 class="h3 fw-bold mt-3"><?= $done === 'actif' ? 'Bienvenue sur Xomiton !' : 'Inscription enregistrée' ?></h1>
							<p class="text-secondary"><?= $done === 'actif' ? 'Votre compte est actif.' : 'Votre compte sera activé après validation par l’équipe (24 à 48 h). Vous recevrez un e-mail.' ?></p>
							<a href="<?= $done === 'actif' ? 'index.php' : 'sign-in.php' ?>" class="btn btn-primary"><?= $done === 'actif' ? 'Accéder à mon fil' : 'Retour à la connexion' ?></a>
						</div>
					<?php else: ?>
						<h1 class="h2 fw-bold">Créer mon compte</h1>
						<p class="text-secondary">Un espace safe, confidentiel et gratuit. Déjà inscrit·e ? <a href="sign-in.php" class="fw-bold">Se connecter</a></p>
						
                        <?php if ($errors): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0 ps-3"><?php foreach ($errors as $er) echo '<li>' . e($er) . '</li>'; ?></ul>
                            </div>
                        <?php endif; ?>

						<form method="post" data-wizard novalidate>
							<?= csrf_field() ?>
                            
                            <!-- Stepper Navigation -->
                            <ol class="xm-stepper list-unstyled d-flex flex-wrap gap-3 mb-4" aria-label="Étapes">
                                <li class="xm-stepper__item" data-wz-item><span class="xm-stepper__dot" aria-hidden="true">1</span><span>Identité</span></li>
                                <li class="xm-stepper__item" data-wz-item><span class="xm-stepper__dot" aria-hidden="true">2</span><span>Contact et lieu</span></li>
                                <li class="xm-stepper__item" data-wz-item><span class="xm-stepper__dot" aria-hidden="true">3</span><span>Profil</span></li>
                                <li class="xm-stepper__item" data-wz-item><span class="xm-stepper__dot" aria-hidden="true">4</span><span>Sécurité</span></li>
                            </ol>

                            <!-- PHASE 1 : Identité -->
                            <section class="xm-wizard-step" aria-labelledby="s1">
                                <h2 id="s1" class="h4 mb-3">Qui es-tu ?</h2>
                                <div class="row g-3">
                                    <div class="col-sm-6 mb-0">
                                        <label class="form-label" for="first_name">Prénom</label>
                                        <input class="form-control" id="first_name" name="first_name" type="text" value="<?= e($_POST['first_name'] ?? '') ?>" autocomplete="given-name" required>
                                    </div>
                                    <div class="col-sm-6 mb-0">
                                        <label class="form-label" for="last_name">Nom</label>
                                        <input class="form-control" id="last_name" name="last_name" type="text" value="<?= e($_POST['last_name'] ?? '') ?>" autocomplete="family-name" required>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <div class="mb-3">
                                        <label class="form-label" for="username">Pseudo</label>
                                        <input class="form-control" id="username" name="username" type="text" value="<?= e($_POST['username'] ?? '') ?>" autocomplete="username" required aria-describedby="username-h" pattern="[A-Za-z0-9_]{3,32}">
                                        <div id="username-h" class="form-text">C'est ce que les autres verront.</div>
                                    </div>
                                </div>
                                <div class="row g-3">
                                    <div class="col-sm-4 mb-0">
                                        <label class="form-label" for="age">Âge</label>
                                        <input class="form-control" id="age" name="age" type="number" value="<?= e($_POST['age'] ?? '') ?>" inputmode="numeric" required aria-describedby="age-h" min="15" max="120">
                                        <div id="age-h" class="form-text">Réservé aux personnes de 15 ans et plus.</div>
                                    </div>
                                    <div class="col-sm-8">
                                        <div class="mb-0">
                                            <label class="form-label" for="pronoun">Pronom <span class="xm-hint fw-normal">(facultatif)</span></label>
                                            <select class="form-select" id="pronoun" name="pronoun">
                                                <option value="">Choisir…</option>
                                                <option value="elle">Elle</option>
                                                <option value="il">Il</option>
                                                <option value="iel">Iel</option>
                                                <option value="autre">Autre</option>
                                                <option value="nr">Je préfère ne pas le dire</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-end mt-4">
                                    <button type="button" class="btn btn-primary" data-wz-next>Continuer</button>
                                </div>
                            </section>

                            <!-- PHASE 2 : Contact et lieu -->
                            <section class="xm-wizard-step" aria-labelledby="s2" hidden>
                                <h2 id="s2" class="h4 mb-3">Comment te joindre ?</h2>
                                <div class="mb-3">
                                    <label class="form-label" for="email">Adresse e-mail</label>
                                    <input class="form-control" id="email" name="email" type="email" value="<?= e($_POST['email'] ?? '') ?>" autocomplete="email" required aria-describedby="email-h">
                                    <div id="email-h" class="form-text">Pour activer ton compte. Jamais partagée.</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="phone_number">Téléphone <span class="xm-hint fw-normal">(facultatif)</span></label>
                                    <input class="form-control" id="phone_number" name="phone_number" type="tel" placeholder="+229 …" value="<?= e($_POST['phone_number'] ?? '') ?>" autocomplete="tel" inputmode="tel" aria-describedby="phone_number-h">
                                    <div id="phone_number-h" class="form-text">Facultatif : permet la connexion par téléphone et le code par SMS.</div>
                                </div>
                                <div class="row g-3">
                                    <div class="col-sm-6">
                                        <div class="mb-0">
                                            <label class="form-label" for="country_id">Département / Pays</label>
                                            <select class="form-select" id="country_id" name="country_id" data-zone-select="#city" required>
                                                <option value="">Choisir…</option>
                                                <?php foreach ($zones as $z => $cs) echo '<option' . (($_POST['country_id'] ?? '') === $z ? ' selected' : '') . '>' . e($z) . '</option>'; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-sm-6 mb-0">
                                        <label class="form-label" for="city">Commune / Ville</label>
                                        <select class="form-select" id="city" name="city" required><option value="">Choisir…</option></select>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between mt-4">
                                    <button type="button" class="btn btn-light" data-wz-prev>Retour</button>
                                    <button type="button" class="btn btn-primary" data-wz-next>Continuer</button>
                                </div>
                            </section>

                            <!-- PHASE 3 : Profil -->
                            <section class="xm-wizard-step" aria-labelledby="s3" hidden>
                                <h2 id="s3" class="h4 mb-1">Ton profil</h2>
                                <p class="text-body-secondary small">Ces choix servent à te proposer des contenus adaptés. Tu peux répondre « Je préfère ne pas le dire » quand c'est proposé, et tout modifier plus tard.</p>
                                
                                <div class="mb-3">
                                    <label class="form-label" for="type_membre">Type de membre</label>
                                    <select class="form-select" id="type_membre" name="type_membre" required>
                                        <option value="">Choisir…</option>
                                        <option value="pair_animateur">Pair·e animateur·rice</option>
                                        <option value="membre">Membre de la communauté</option>
                                        <option value="allie">Allié·e</option>
                                        <option value="ressource">Personne ressource (juriste, psychologue, santé…)</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label" for="identite_genre">Identité de genre</label>
                                    <select class="form-select" id="identite_genre" name="identite_genre" required data-other="#identite_genre-autre-wrap">
                                        <option value="">Choisir…</option>
                                        <option value="femme_cis">Femme cisgenre</option>
                                        <option value="homme_cis">Homme cisgenre</option>
                                        <option value="femme_trans">Femme transgenre</option>
                                        <option value="homme_trans">Homme transgenre</option>
                                        <option value="non_binaire">Non binaire</option>
                                        <option value="intersexe">Intersexe</option>
                                        <option value="en_questionnement">En questionnement</option>
                                        <option value="autre">Autre</option>
                                    </select>
                                    <div id="identite_genre-autre-wrap" class="mt-2" hidden>
                                        <label class="form-label" for="identite_genre-autre">Précise (autre)</label>
                                        <input class="form-control" id="identite_genre-autre" name="identite_genre_autre">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label" for="orientation_sexuelle">Orientation sexuelle</label>
                                    <select class="form-select" id="orientation_sexuelle" name="orientation_sexuelle" required data-other="#orientation_sexuelle-autre-wrap">
                                        <option value="">Choisir…</option>
                                        <option value="lesbienne">Lesbienne</option>
                                        <option value="bisexuelle">Bisexuel·le</option>
                                        <option value="pansexuelle">Pansexuel·le</option>
                                        <option value="queer">Queer</option>
                                        <option value="hetero">Hétérosexuel·le</option>
                                        <option value="asexuelle">Asexuel·le</option>
                                        <option value="en_questionnement">En questionnement</option>
                                        <option value="autre">Autre</option>
                                        <option value="nr">Je préfère ne pas le dire</option>
                                    </select>
                                    <div id="orientation_sexuelle-autre-wrap" class="mt-2" hidden>
                                        <label class="form-label" for="orientation_sexuelle-autre">Précise (autre)</label>
                                        <input class="form-control" id="orientation_sexuelle-autre" name="orientation_sexuelle_autre">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label" for="statut_allie_ressource">Statut : allié·e ou ressource <span class="xm-hint fw-normal">(facultatif)</span></label>
                                    <select class="form-select" id="statut_allie_ressource" name="statut_allie_ressource" data-other="#statut_allie_ressource-autre-wrap">
                                        <option value="">Choisir…</option>
                                        <option value="personne_concernee">Personne concernée</option>
                                        <option value="allie">Allié·e</option>
                                        <option value="ressource">Ressource (professionnel·le)</option>
                                        <option value="autre">Autre</option>
                                    </select>
                                    <div id="statut_allie_ressource-autre-wrap" class="mt-2" hidden>
                                        <label class="form-label" for="statut_allie_ressource-autre">Précise (autre)</label>
                                        <input class="form-control" id="statut_allie_ressource-autre" name="statut_allie_ressource_autre">
                                    </div>
                                    <div class="form-text">Sert à orienter les personnes vers les bonnes ressources.</div>
                                </div>
                                
                                <div class="d-flex justify-content-between mt-4">
                                    <button type="button" class="btn btn-light" data-wz-prev>Retour</button>
                                    <button type="button" class="btn btn-primary" data-wz-next>Continuer</button>
                                </div>
                            </section>

                            <!-- PHASE 4 : Sécurité -->
                            <section class="xm-wizard-step" aria-labelledby="s4" hidden>
                                <h2 id="s4" class="h4 mb-3">Sécurise ton compte</h2>
                                <div class="mb-3">
                                    <label class="form-label" for="password">Mot de passe</label>
                                    <div class="input-group">
                                        <input class="form-control" id="password" name="password" type="password" autocomplete="new-password" required aria-describedby="password-h">
                                        <button class="btn btn-light border" type="button" data-pw-toggle="#password" aria-pressed="false" aria-label="Afficher le mot de passe"><i class="bi bi-eye" aria-hidden="true"></i></button>
                                    </div>
                                    <div class="progress mt-2" style="height:6px" aria-hidden="true"><div class="progress-bar" id="pwdBar" style="width:0%"></div></div>
                                    <div class="form-text" id="password-h">8 caractères minimum, une majuscule et un chiffre. Le collage est autorisé.</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="confirm_password">Confirmer le mot de passe</label>
                                    <div class="input-group">
                                        <input class="form-control" id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" required>
                                        <button class="btn btn-light border" type="button" data-pw-toggle="#confirm_password" aria-pressed="false" aria-label="Afficher le mot de passe"><i class="bi bi-eye" aria-hidden="true"></i></button>
                                    </div>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="cgu" name="cgu" value="1" required>
                                    <label class="form-check-label" for="cgu">J'accepte les <a href="privacy-and-terms.php" target="_blank">conditions d'utilisation</a> et la <a href="privacy-and-terms.php" target="_blank">politique de confidentialité</a>.</label>
                                </div>
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="conf" name="newsletter" value="1">
                                    <label class="form-check-label" for="conf">Je veux recevoir les actualités par e-mail <span class="xm-hint fw-normal">(facultatif)</span></label>
                                </div>
                                <div class="alert alert-info small" role="note"><i class="bi bi-shield-lock" aria-hidden="true"></i> Tes données de profil (genre, orientation) ne sont jamais visibles publiquement ni partagées sans ton accord.</div>
                                
                                <div class="d-flex justify-content-between mt-4">
                                    <button type="button" class="btn btn-light" data-wz-prev>Retour</button>
                                    <button type="submit" class="btn btn-primary">Créer mon compte</button>
                                </div>
                            </section>
						</form>
						
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
</main>
<?php $noSosFab = true; include("partials/footer-scripts.php"); ?>
<script>
XM.zones = <?= json_encode($zones, JSON_UNESCAPED_UNICODE) ?>;

// Script de la jauge de robustesse du mot de passe
const pw = document.getElementById('password'), bar = document.getElementById('pwdBar');
if (pw && bar) {
    pw.addEventListener('input', () => {
        let s = 0; const v = pw.value;
        if (v.length >= 8) s++; 
        if (/[A-Z]/.test(v)) s++; 
        if (/\d/.test(v)) s++; 
        if (/[^A-Za-z0-9]/.test(v)) s++;
        bar.style.width = (s * 25) + '%'; 
        bar.className = 'progress-bar ' + ['bg-danger','bg-danger','bg-warning','bg-info','bg-success'][s];
    });
}

// Gestion des champs select "Autre"
document.querySelectorAll('select[data-other]').forEach(s => {
    const t = document.querySelector(s.dataset.other);
    const u = () => { if(t) t.hidden = !/autre/i.test(s.value); };
    s.addEventListener('change', u);
    u();
});

// Afficher / masquer les mots de passe
document.querySelectorAll('[data-pw-toggle]').forEach(b => {
    b.addEventListener('click', () => {
        const i = document.querySelector(b.dataset.pwToggle);
        const show = i.type === 'password';
        i.type = show ? 'text' : 'password';
        b.setAttribute('aria-pressed', show);
        b.innerHTML = '<i class="bi bi-eye' + (show ? '-slash' : '') + '" aria-hidden="true"></i>';
    });
});

// Logique du formulaire en étapes (Wizard)
document.addEventListener('DOMContentLoaded', () => {
    const wizard = document.querySelector('[data-wizard]');
    if (!wizard) return;
    
    const steps = Array.from(wizard.querySelectorAll('.xm-wizard-step'));
    const items = Array.from(wizard.querySelectorAll('[data-wz-item]'));
    let currentStep = 0;

    const showStep = (n) => {
        currentStep = Math.max(0, Math.min(steps.length - 1, n));
        steps.forEach((s, idx) => s.hidden = (idx !== currentStep));
        items.forEach((it, idx) => {
            it.classList.toggle('is-current', idx === currentStep);
            it.classList.toggle('is-done', idx < currentStep);
            const dot = it.querySelector('.xm-stepper__dot');
            if (dot) dot.innerHTML = idx < currentStep ? '<i class="bi bi-check-lg"></i>' : (idx + 1);
        });
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    wizard.querySelectorAll('[data-wz-next]').forEach(btn => {
        btn.addEventListener('click', () => {
            const currentSection = steps[currentStep];
            const invalidFields = Array.from(currentSection.querySelectorAll('input[required], select[required]')).filter(input => !input.checkValidity());
            
            if (invalidFields.length > 0) {
                invalidFields[0].reportValidity();
                return;
            }
            showStep(currentStep + 1);
        });
    });

    wizard.querySelectorAll('[data-wz-prev]').forEach(btn => {
        btn.addEventListener('click', () => showStep(currentStep - 1));
    });

    showStep(0);
});
</script>
</body>
</html>