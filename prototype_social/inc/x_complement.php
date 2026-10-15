<?php
/** Helpers locaux aux modules complémentaires (violences, urgences, signalement, SOS, CCC, pair…). */

/** En-tête public minimal (pages accessibles sans compte). */
function x_public_header() { ?>
<div class="xm-offline-bar" id="xmOfflineBar"><i class="bi bi-wifi-off me-1"></i> Vous êtes hors connexion — vos saisies sont conservées et seront synchronisées automatiquement.</div>
<header class="navbar-light fixed-top bg-mode header-static">
	<nav class="navbar navbar-expand-lg">
		<div class="container">
			<a class="navbar-brand" href="<?= BASE ?>landing.php">
				<img class="light-mode-item navbar-brand-item" src="<?= BASE ?>assets/images/logo.svg" alt="Xomiton, logo (mode clair)" title="Xomiton">
				<img class="dark-mode-item navbar-brand-item" src="<?= BASE ?>assets/images/logo-dark.svg" alt="Xomiton, logo (mode sombre)" title="Xomiton">
			</a>
			<div class="ms-auto d-flex align-items-center gap-2">
				<a class="btn btn-sm btn-danger-soft d-none d-sm-inline-block" href="<?= BASE ?>urgences.php"><i class="bi bi-telephone-fill me-1"></i>Urgences</a>
				<a class="btn btn-sm btn-primary-soft" href="<?= BASE ?>sign-in.php">Se connecter</a>
				<a class="btn btn-sm btn-primary d-none d-sm-inline-block" href="<?= BASE ?>sign-up.php">Créer un compte</a>
			</div>
		</div>
	</nav>
</header>
<?php }

/** Script d'initialisation des communes (XM.zones) – à placer après footer-scripts. */
function x_zones_script() {
    echo '<script>XM.zones = ' . json_encode(x_zones(), JSON_UNESCAPED_UNICODE) . ';</script>' . "\n";
}

function x_tel($p) { return 'tel:' . preg_replace('/[^0-9+]/', '', (string)$p); }
function x_wa($p) { return 'https://wa.me/' . preg_replace('/[^0-9]/', '', (string)$p); }

/** Libellé d'une catégorie de violence. */
function x_violence_label($id) { foreach (x_violence_categories() as $c) if ($c[0] === $id) return $c[1]; return (string)$id; }

/** Réserve aux pairs/admins : redirige les autres. */
function x_require_pair() {
    require_login();
    if (!in_array(x_user()['role'] ?? '', ['pair', 'admin', 'superadmin'], true)) {
        $_SESSION['flash'] = ['type' => 'warning', 'msg' => 'Cet espace est réservé aux pairs animateur·rices.'];
        header('Location: index.php'); exit;
    }
}

/** Route une alerte vers une personne précise de l'annuaire (même schéma que api.php). */
function x_alert_row(array $o) {
    return array_merge([
        'type' => 'besoin', 'anonymous' => false, 'pseudo' => '', 'category' => '', 'description' => '', 'needs' => [], 'zone' => '', 'commune' => '',
        'lat' => null, 'lng' => null, 'contact' => '', 'status' => 'nouveau', 'priority' => 'normale', 'created' => time(), 'source' => 'web', 'routed_to' => null,
    ], $o);
}
