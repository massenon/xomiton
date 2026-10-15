<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/x_complement.php';
require_login();
$pageTitle = 'Annuaire';
$nav = 'annuaire';
$zones = x_zones();

/* Demande de rappel : crée une alerte de type « besoin » routée vers la personne choisie */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $back = 'annuaire.php';
    if (!csrf_ok()) { $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Session expirée, veuillez réessayer.']; header('Location: ' . $back); exit; }
    $target = store_find('annuaire', $_POST['target'] ?? 0);
    $contact = trim($_POST['contact'] ?? '');
    if (!$target || empty($target['active'])) { $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Personne introuvable dans l’annuaire.']; header('Location: ' . $back); exit; }
    if ($contact === '') { $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Indiquez un numéro ou une adresse pour être rappelé·e.']; header('Location: ' . $back); exit; }
    $anon = !empty($_POST['anonymous']);
    $row = store_append('alertes', x_alert_row([
        'type' => 'besoin', 'anonymous' => $anon, 'pseudo' => $anon ? '' : (x_user()['pseudo'] ?? ''),
        'category' => '', 'description' => 'Demande de rappel : ' . mb_substr(trim($_POST['message'] ?? '') ?: 'Je souhaite être rappelé·e.', 0, 1000),
        'needs' => ['soutien_psychologique'], 'zone' => $target['zone'] ?? '', 'commune' => $target['commune'] ?? '',
        'contact' => $contact, 'source' => 'annuaire', 'routed_to' => (int)$target['id'],
    ]));
    x_log('Demande de rappel', 'Alerte #' . $row['id'] . ' → ' . $target['name']);
    $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Demande envoyée à ' . $target['name'] . ' (' . $target['role'] . '). Vous serez rappelé·e au ' . $contact . '.'];
    header('Location: ' . $back); exit;
}

$all = array_values(array_filter(store_all('annuaire'), fn($r) => !empty($r['active'])));
$fr = $_GET['role'] ?? ''; $fz = $_GET['zone'] ?? ''; $fc = $_GET['commune'] ?? ''; $q = trim($_GET['q'] ?? '');
$rows = array_values(array_filter($all, function ($r) use ($fr, $fz, $fc, $q) {
    if ($fr !== '' && $r['role'] !== $fr) return false;
    if ($fz !== '' && ($r['zone'] ?? '') !== $fz) return false;
    if ($fc !== '' && ($r['commune'] ?? '') !== $fc) return false;
    if ($q !== '' && mb_stripos(($r['name'] ?? '') . ' ' . ($r['org'] ?? '') . ' ' . ($r['role'] ?? '') . ' ' . ($r['commune'] ?? ''), $q) === false) return false;
    return true;
}));
$pos = ['Alibori' => [11.6, 2.8], 'Atacora' => [10.5, 1.6], 'Borgou' => [9.6, 2.8], 'Donga' => [9.7, 1.7], 'Collines' => [8.2, 2.3], 'Zou' => [7.2, 2.1],
    'Plateau' => [7.0, 2.65], 'Ouémé' => [6.6, 2.65], 'Atlantique' => [6.65, 2.15], 'Littoral' => [6.37, 2.42], 'Mono' => [6.8, 1.65], 'Couffo' => [7.1, 1.85]];
$byZone = [];
foreach ($all as $r) { $z = $r['zone'] ?? ''; $byZone[$z] = ($byZone[$z] ?? 0) + 1; }
$roleColors = ['Point focal' => 'primary', 'Juriste' => 'info', 'Psychologue' => 'success', 'Gynécologue' => 'danger', 'Responsable de réseau' => 'warning'];
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
					<div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
						<div><h1 class="h3 mb-0">Annuaire dynamique</h1><p class="text-secondary mb-0">Points focaux, juristes, psychologues et responsables de réseaux près de chez vous.</p></div>
						<a class="btn btn-danger-soft" href="urgences.php"><i class="bi bi-telephone-fill me-1"></i>Urgences</a>
					</div>

					<div class="row g-4">
						<div class="col-xl-7">
							<form method="get" class="card card-body h-100">
								<div class="row g-2">
									<div class="col-12"><div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span><input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="Nom, organisation, commune…" aria-label="Recherche"></div></div>
									<div class="col-sm-4"><select class="form-select" name="role" aria-label="Rôle"><option value="">Rôle</option><?php foreach (x_roles_annuaire() as $r): ?><option <?= $fr === $r ? 'selected' : '' ?>><?= e($r) ?></option><?php endforeach; ?></select></div>
									<div class="col-sm-4"><select class="form-select" name="zone" id="fZone" data-zone-select="#fCommune" aria-label="Département"><option value="">Département</option><?php foreach ($zones as $z => $cs): ?><option <?= $fz === $z ? 'selected' : '' ?>><?= e($z) ?></option><?php endforeach; ?></select></div>
									<div class="col-sm-4"><select class="form-select" name="commune" id="fCommune" aria-label="Commune"><option value="">Commune</option><?php if ($fz && isset($zones[$fz])) foreach ($zones[$fz] as $c): ?><option <?= $fc === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select></div>
									<div class="col-12 d-flex gap-2"><button class="btn btn-primary" type="submit"><i class="bi bi-funnel me-1"></i>Filtrer</button><a class="btn btn-light" href="annuaire.php">Réinitialiser</a><span class="ms-auto align-self-center small text-secondary"><?= count($rows) ?> résultat(s)</span></div>
								</div>
							</form>
						</div>
						<div class="col-xl-5">
							<div class="card card-body h-100 p-2">
								<div class="xm-map" style="height:230px;background:linear-gradient(135deg,rgba(4,51,195,.08),rgba(227,14,14,.08))" aria-label="Carte schématique du Bénin">
									<?php foreach ($byZone as $z => $n): if (!isset($pos[$z])): continue; endif; $y = (12.5 - $pos[$z][0]) / (12.5 - 6.1) * 100; $x = ($pos[$z][1] - 0.9) / (3.9 - 0.9) * 100; ?>
									<a href="?zone=<?= urlencode($z) ?>" title="<?= e($z) ?> : <?= $n ?> contact(s)" style="position:absolute;left:<?= round($x) ?>%;top:<?= round($y) ?>%;transform:translate(-50%,-50%)" class="badge rounded-pill bg-<?= $fz === $z ? 'danger' : 'primary' ?> text-decoration-none"><?= $n ?></a>
									<?php endforeach; ?>
									<span class="position-absolute top-0 end-0 small text-secondary p-1">Bénin · par département</span>
								</div>
							</div>
						</div>
					</div>

					<div class="row g-3">
						<?php foreach ($rows as $r): $col = $roleColors[$r['role']] ?? 'secondary'; ?>
						<div class="col-md-6">
							<div class="card h-100">
								<div class="card-body">
									<div class="d-flex gap-3">
										<div class="avatar avatar-lg flex-shrink-0"><img class="avatar-img rounded-circle" src="<?= x_avatar((int)$r['id']) ?>" alt=""></div>
										<div class="min-w-0">
											<h2 class="h6 mb-1"><?= e($r['name']) ?></h2>
											<span class="badge bg-<?= $col ?> bg-opacity-10 text-<?= $col ?>"><?= e($r['role']) ?></span>
											<div class="small text-secondary mt-1"><?= e($r['org'] ?? '') ?></div>
											<div class="small"><i class="bi bi-geo-alt me-1"></i><?= e($r['commune'] ?? '') ?>, <?= e($r['zone'] ?? '') ?></div>
											<div class="small"><i class="bi bi-clock me-1"></i><?= e($r['availability'] ?? '') ?></div>
										</div>
									</div>
									<div class="d-flex flex-wrap gap-2 mt-3">
										<a class="btn btn-sm btn-success" href="<?= e(x_tel($r['phone'])) ?>"><i class="bi bi-telephone-fill me-1"></i>Appeler</a>
										<a class="btn btn-sm btn-success-soft" href="<?= e(x_wa($r['whatsapp'] ?? $r['phone'])) ?>" target="_blank" rel="noopener"><i class="bi bi-whatsapp me-1"></i>WhatsApp</a>
										<a class="btn btn-sm btn-light" href="mailto:<?= e($r['email']) ?>"><i class="bi bi-envelope me-1"></i>E-mail</a>
									</div>
								</div>
								<div class="card-footer bg-transparent">
									<button class="btn btn-sm btn-primary-soft w-100 cb-btn" type="button" data-bs-toggle="modal" data-bs-target="#cbModal" data-id="<?= (int)$r['id'] ?>" data-name="<?= e($r['name']) ?>" data-role="<?= e($r['role']) ?>"><i class="bi bi-telephone-inbound me-1"></i>Demander à être rappelé·e</button>
								</div>
							</div>
						</div>
						<?php endforeach; ?>
					</div>
					<?php if (!$rows): ?><div class="card card-body text-center text-secondary"><i class="bi bi-person-x fs-1"></i><p class="mb-1">Aucun contact ne correspond à ces critères.</p><a href="annuaire.php">Voir tout l’annuaire</a></div><?php endif; ?>
				</div>
			</div>
		</div>
	</main>

	<!-- Modale : demande de rappel -->
	<div class="modal fade" id="cbModal" tabindex="-1" aria-labelledby="cbTitle" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<form class="modal-content" method="post" data-xm-validate>
				<?= csrf_field() ?>
				<input type="hidden" name="target" id="cbTarget">
				<div class="modal-header"><h2 class="modal-title h5" id="cbTitle">Demande de rappel</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
				<div class="modal-body vstack gap-3">
					<p class="small mb-0">Vous serez rappelé·e par <strong id="cbWho"></strong>. Votre demande est transmise comme un « besoin » à cette personne uniquement.</p>
					<div><label class="form-label" for="cbContact">Votre numéro ou e-mail</label><input class="form-control" id="cbContact" name="contact" required placeholder="+229 01 …"><div class="form-text">Utilisé uniquement pour ce rappel.</div></div>
					<div><label class="form-label" for="cbMsg">Message (facultatif)</label><textarea class="form-control" id="cbMsg" name="message" rows="3" placeholder="Que souhaitez-vous dire ?"></textarea></div>
					<div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="cbAnon" name="anonymous" value="1" checked><label class="form-check-label" for="cbAnon">Ne pas transmettre mon pseudo</label></div>
				</div>
				<div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Annuler</button><button class="btn btn-primary" type="submit">Envoyer la demande</button></div>
			</form>
		</div>
	</div>

	<?php include("partials/modals.php"); include("partials/footer-scripts.php"); x_zones_script(); ?>
	<script>
	document.querySelectorAll('.cb-btn').forEach(function (b) {
		b.addEventListener('click', function () {
			document.getElementById('cbTarget').value = b.dataset.id;
			document.getElementById('cbWho').textContent = b.dataset.name + ' (' + b.dataset.role + ')';
		});
	});
	</script>
</body>
</html>
