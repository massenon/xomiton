<?php
require __DIR__ . '/../inc/bootstrap.php'; require_admin(); require_once __DIR__ . '/../inc/x_admin_sys.php';
$pageTitle = 'Règles de validation'; $adminPage = 'validation';

/* Catalogue : clé => [libellé, description, formulaires concernés, exemple valide, exemple invalide, miroir serveur] */
$catalog = [
    'required'        => ['Champ obligatoire', 'Le champ ne peut pas être vide (espaces ignorés).', ['Tous les formulaires'], 'Cotonou', '', 'Oui'],
    'phone_bj'        => ['Téléphone béninois', 'Format +229 01 XX XX XX XX (8 à 10 chiffres, indicatif facultatif).', ['Inscription', 'Annuaire', 'Collecte', 'Profil'], '+229 01 97 38 88 47', '12-AB', 'Oui'],
    'email'           => ['Adresse e-mail valide', 'Structure nom@domaine.tld.', ['Inscription', 'Annuaire', 'Paramètres'], 'awa@xomiton.bj', 'awa@', 'Oui'],
    'date_not_future' => ['Date non postérieure à aujourd’hui', 'Empêche la saisie d’un événement ou d’une collecte datée du futur.', ['Collecte de données', 'Registres', 'Suivi'], '2026-01-15', '2099-01-01', 'Oui'],
    'age_range'       => ['Âge entre 10 et 99 ans', 'Valeur plausible pour les populations accompagnées.', ['Collecte de données', 'Fiches VBG/VDH'], '24', '150', 'Oui'],
    'min_len_20'      => ['Description d’au moins 20 caractères', 'Évite les signalements vides ou inexploitables.', ['Signalement', 'SOS', 'Besoins'], 'Menaces répétées par un voisin depuis une semaine.', 'Menaces', 'Oui'],
    'positive_int'    => ['Entier positif', 'Quantités de stock, effectifs, seuils.', ['Gestion de stock', 'Indicateurs'], '12', '-3', 'Oui'],
    'commune_in_zone' => ['Commune cohérente avec le département', 'La commune choisie doit appartenir au département sélectionné (liste en cascade).', ['Collecte', 'Inscription', 'Annuaire', 'Signalement'], 'Cotonou', '', 'Oui (liste fermée)'],
    'case_code'       => ['Code dossier VBG-0000', 'Format normalisé des codes de registre.', ['Registres', 'Suivi'], 'VBG-0042', 'vbg42', 'Oui'],
    'password_strong' => ['Mot de passe fort', '8 caractères minimum, une majuscule, un chiffre.', ['Inscription', 'Réinitialisation', 'Utilisateurs'], 'Xomiton2026', 'abc', 'Oui'],
];
$off = setting('rules_off', []); if (!is_array($off)) $off = [];
$plaus = setting('plausibility', ['age_min' => 10, 'age_max' => 99, 'max_past_years' => 5, 'lat' => [6.2, 12.5], 'lng' => [0.7, 3.9]]);
$dupFieldsAll = ['date' => 'Date', 'commune' => 'Commune', 'population' => 'Population', 'age' => 'Tranche d’âge', 'type' => 'Type de violence', 'sexe' => 'Sexe'];
$dupFields = setting('dup_fields', ['date', 'commune', 'population', 'age']); if (!is_array($dupFields)) $dupFields = ['date', 'commune', 'population', 'age'];
$dupOn = (bool)setting('dup_enabled', true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    xa_guard();
    $do = $_POST['do'] ?? '';
    if ($do === 'rules') {
        $on = array_map('strval', (array)($_POST['on'] ?? []));
        $newOff = array_values(array_diff(array_keys($catalog), $on));
        if (in_array('required', $newOff, true)) { $newOff = array_values(array_diff($newOff, ['required'])); $warn = ' La règle « obligatoire » ne peut pas être désactivée.'; }
        setting_set('rules_off', $newOff);
        x_log('Validation', 'Règles désactivées : ' . ($newOff ? implode(', ', $newOff) : 'aucune'));
        xa_back('success', 'Règles enregistrées (' . count($catalog) - count($newOff) . ' actives).' . ($warn ?? ''));
    }
    if ($do === 'plaus') {
        $amin = (int)xa_post('age_min', 10); $amax = (int)xa_post('age_max', 99); $py = (int)xa_post('max_past_years', 5);
        if ($amin < 0 || $amax > 120 || $amin >= $amax || $py < 1 || $py > 50) xa_back('danger', 'Bornes invalides (âge min < âge max ≤ 120 ; ancienneté 1 à 50 ans).');
        setting_set('plausibility', ['age_min' => $amin, 'age_max' => $amax, 'max_past_years' => $py, 'lat' => $plaus['lat'], 'lng' => $plaus['lng']]);
        x_log('Validation', "Plausibilité : âge $amin-$amax, ancienneté $py ans");
        xa_back('success', 'Règles de plausibilité enregistrées.');
    }
    if ($do === 'dup') {
        $f = array_values(array_intersect(array_keys($dupFieldsAll), (array)($_POST['fields'] ?? [])));
        if (count($f) < 2) xa_back('danger', 'Sélectionnez au moins deux champs pour détecter un doublon.');
        setting_set('dup_fields', $f); setting_set('dup_enabled', !empty($_POST['enabled']));
        x_log('Validation', 'Détection de doublons : ' . implode('+', $f));
        xa_back('success', 'Règle de détection des doublons enregistrée.');
    }
    xa_back('danger', 'Action inconnue.');
}

/* ---- Rapport de qualité (store cases) ---- */
$cases = store_all('cases'); $n = max(1, count($cases));
$fields = ['zone' => 'Département', 'commune' => 'Commune', 'population' => 'Population', 'sexe' => 'Sexe', 'age' => 'Âge', 'type' => 'Type de violence', 'referral' => 'Orientation', 'notes' => 'Notes'];
$missing = [];
foreach ($fields as $k => $l) $missing[$k] = count(array_filter($cases, fn($c) => trim((string)($c[$k] ?? '')) === ''));
$groups = [];
foreach ($cases as $c) { $key = implode('|', array_map(fn($f) => $c[$f] ?? '', $dupFields)); $groups[$key][] = $c; }
$dups = array_filter($groups, fn($g) => count($g) > 1);
$dupCases = array_sum(array_map('count', $dups));
$future = count(array_filter($cases, fn($c) => !empty($c['date']) && strtotime($c['date']) > time()));
$old = count(array_filter($cases, fn($c) => !empty($c['date']) && strtotime($c['date']) < strtotime('-' . (int)$plaus['max_past_years'] . ' years')));
$completeness = 100 - round(array_sum($missing) / ($n * count($fields)) * 100, 1);
$score = max(0, round($completeness - ($dupCases / $n) * 20 - (($future + $old) / $n) * 30));

include __DIR__ . '/../partials/admin-top.php';
?>
<div class="row g-3 mb-4">
	<?= xa_kpi('Règles actives', (count($catalog) - count($off)) . ' / ' . count($catalog), 'bi-patch-check', 'success') ?>
	<?= xa_kpi('Complétude des dossiers', $completeness . ' %', 'bi-clipboard-check') ?>
	<?= xa_kpi('Doublons suspects', count($dups), 'bi-copy', 'warning', $dupCases . ' dossiers concernés') ?>
	<?= xa_kpi('Score qualité', $score . ' / 100', 'bi-speedometer', $score >= 80 ? 'success' : 'danger') ?>
</div>

<div class="row g-4">
	<div class="col-xl-8">
		<form method="post" class="card mb-4" id="rulesForm">
			<?= csrf_field() ?><input type="hidden" name="do" value="rules">
			<div class="card-header d-flex justify-content-between align-items-center"><h2 class="h6 mb-0">Catalogue des règles de saisie</h2><button class="btn btn-primary btn-sm">Enregistrer</button></div>
			<div class="xm-table-wrap">
				<table class="table align-middle mb-0">
					<thead><tr><th style="width:70px">Active</th><th>Règle</th><th>S’applique à</th><th>Serveur</th><th></th></tr></thead>
					<tbody>
					<?php foreach ($catalog as $k => $r): ?>
						<tr>
							<td><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="on[]" value="<?= $k ?>" <?= in_array($k, $off, true) ? '' : 'checked' ?> <?= $k === 'required' ? 'data-locked' : '' ?> aria-label="Activer <?= e($r[0]) ?>"></div></td>
							<td><div class="fw-semibold"><?= e($r[0]) ?> <code class="small"><?= $k ?></code></div><div class="small text-secondary"><?= e($r[1]) ?></div>
								<div class="small mt-1"><span class="text-success"><i class="bi bi-check2"></i> <?= e($r[3]) ?></span> <?php if ($r[4] !== ''): ?> · <span class="text-danger"><i class="bi bi-x"></i> <?= e($r[4]) ?></span><?php endif; ?></div></td>
							<td><?php foreach ($r[2] as $f): ?><span class="badge bg-secondary-subtle text-secondary-emphasis me-1 mb-1"><?= e($f) ?></span><?php endforeach; ?></td>
							<td class="small"><?= e($r[5]) ?></td>
							<td><button type="button" class="btn btn-sm btn-light" data-try="<?= $k ?>" data-ex="<?= e($r[4] !== '' ? $r[4] : $r[3]) ?>">Tester</button></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<div class="card-footer small text-secondary">L’état est enregistré côté serveur (<code>rules_off</code>) et recopié dans le navigateur (<code>localStorage xm_rules_off</code>) pour que les formulaires le respectent immédiatement.</div>
		</form>

		<div class="card mb-4">
			<div class="card-header"><h2 class="h6 mb-0"><i class="bi bi-flask me-2"></i>Bac à sable de validation</h2></div>
			<div class="card-body">
				<form class="row g-3" id="sandbox" onsubmit="return false" novalidate>
					<div class="col-md-5"><label class="form-label">Règle</label><select class="form-select" id="sbRule"><?php foreach ($catalog as $k => $r): ?><option value="<?= $k ?>"><?= e($r[0]) ?></option><?php endforeach; ?></select></div>
					<div class="col-md-7"><label class="form-label">Valeur à tester</label><input class="form-control" id="sbVal" placeholder="Tapez une valeur…" autocomplete="off"></div>
					<div class="col-12"><div id="sbOut" class="alert alert-secondary mb-0" role="status">Saisissez une valeur pour voir le résultat en direct.</div></div>
				</form>
			</div>
		</div>
	</div>

	<div class="col-xl-4">
		<div class="card mb-4">
			<div class="card-header"><h2 class="h6 mb-0">Détection des doublons</h2></div>
			<form method="post" class="card-body">
				<?= csrf_field() ?><input type="hidden" name="do" value="dup">
				<div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="enabled" value="1" id="dupOn" <?= $dupOn ? 'checked' : '' ?>><label class="form-check-label" for="dupOn">Alerter lors d’une saisie suspecte</label></div>
				<p class="small text-secondary mb-2">Deux dossiers sont suspects s’ils ont en commun :</p>
				<?php foreach ($dupFieldsAll as $k => $l): ?><div class="form-check"><input class="form-check-input" type="checkbox" name="fields[]" value="<?= $k ?>" id="df<?= $k ?>" <?= in_array($k, $dupFields, true) ? 'checked' : '' ?>><label class="form-check-label" for="df<?= $k ?>"><?= e($l) ?></label></div><?php endforeach; ?>
				<button class="btn btn-primary btn-sm mt-3">Enregistrer</button>
			</form>
		</div>

		<div class="card mb-4">
			<div class="card-header"><h2 class="h6 mb-0">Règles de plausibilité</h2></div>
			<form method="post" class="card-body" data-xm-validate>
				<?= csrf_field() ?><input type="hidden" name="do" value="plaus">
				<div class="row g-2 mb-2"><div class="col-6"><label class="form-label small">Âge min.</label><input class="form-control form-control-sm" name="age_min" value="<?= (int)$plaus['age_min'] ?>" data-rule="positive_int"></div>
				<div class="col-6"><label class="form-label small">Âge max.</label><input class="form-control form-control-sm" name="age_max" value="<?= (int)$plaus['age_max'] ?>" data-rule="positive_int"></div></div>
				<label class="form-label small">Ancienneté maximale d’une date (ans)</label><input class="form-control form-control-sm mb-3" name="max_past_years" value="<?= (int)$plaus['max_past_years'] ?>" data-rule="positive_int">
				<table class="table table-sm small mb-3"><tbody>
					<tr><td>Âge</td><td><?= (int)$plaus['age_min'] ?>–<?= (int)$plaus['age_max'] ?> ans</td></tr>
					<tr><td>Date postérieure à aujourd’hui</td><td>Refusée (<?= $future ?> trouvée<?= $future > 1 ? 's' : '' ?>)</td></tr>
					<tr><td>Date de plus de <?= (int)$plaus['max_past_years'] ?> ans</td><td>Avertissement (<?= $old ?>)</td></tr>
					<tr><td>Coordonnées GPS</td><td>Bénin : lat <?= $plaus['lat'][0] ?>–<?= $plaus['lat'][1] ?>, lng <?= $plaus['lng'][0] ?>–<?= $plaus['lng'][1] ?></td></tr>
					<tr><td>Quantité de stock</td><td>Entier ≥ 0</td></tr>
				</tbody></table>
				<button class="btn btn-primary btn-sm">Enregistrer</button>
			</form>
		</div>

		<div class="card">
			<div class="card-header d-flex justify-content-between"><h2 class="h6 mb-0">Erreurs de saisie évitées</h2><span class="badge bg-secondary">Exemple</span></div>
			<div class="card-body"><canvas id="errChart" height="220" aria-label="Erreurs de saisie par règle (exemple)"></canvas></div>
		</div>
	</div>
</div>

<div class="card mt-4">
	<div class="card-header d-flex justify-content-between align-items-center"><h2 class="h6 mb-0">Rapport de qualité des données (<?= count($cases) ?> dossiers)</h2><button class="btn btn-outline-secondary btn-sm no-print" onclick="window.print()"><i class="bi bi-printer me-1"></i>Imprimer</button></div>
	<div class="card-body">
		<div class="row g-4">
			<div class="col-lg-5">
				<h3 class="h6">Champs manquants</h3>
				<?php foreach ($fields as $k => $l): $pc = round($missing[$k] / $n * 100, 1); ?>
					<div class="mb-2"><div class="d-flex justify-content-between small"><span><?= e($l) ?></span><span><?= $missing[$k] ?> · <?= $pc ?> %</span></div>
					<div class="progress" style="height:6px"><div class="progress-bar <?= $pc > 10 ? 'bg-danger' : ($pc > 3 ? 'bg-warning' : 'bg-success') ?>" style="width:<?= max(2, $pc) ?>%"></div></div></div>
				<?php endforeach; ?>
			</div>
			<div class="col-lg-7">
				<h3 class="h6">Doublons suspects <small class="text-secondary">(clé : <?= e(implode(' + ', array_map(fn($f) => $dupFieldsAll[$f], $dupFields))) ?>)</small></h3>
				<?php if (!$dups): ?><p class="text-success mb-0"><i class="bi bi-check-circle me-1"></i>Aucun doublon suspect.</p><?php else: ?>
				<div class="xm-table-wrap"><table class="table table-sm align-middle">
					<thead><tr><th>Dossiers</th><th>Date</th><th>Commune</th><th>Population</th><th>Âge</th></tr></thead>
					<tbody><?php foreach (array_slice($dups, 0, 12) as $g): $c0 = $g[0]; ?>
						<tr><td><?= e(implode(', ', array_column($g, 'code'))) ?></td><td><?= e($c0['date'] ?? '') ?></td><td><?= e($c0['commune'] ?? '') ?></td><td class="small"><?= e($c0['population'] ?? '') ?></td><td><?= e($c0['age'] ?? '') ?></td></tr>
					<?php endforeach; ?></tbody></table></div>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>
<?php
ob_start(); ?>
<script>
(function () {
  var $ = function (s) { return document.querySelector(s); };
  /* Synchronise l'état serveur vers le navigateur (honoré par XM.validateField au chargement suivant). */
  var serverOff = <?= json_encode(array_values($off)) ?>;
  try { localStorage.setItem('xm_rules_off', JSON.stringify(serverOff)); } catch (e) {}
  function run() {
    var key = $('#sbRule').value, v = $('#sbVal').value, def = window.XM && XM.rules && XM.rules[key], out = $('#sbOut');
    if (!def) return;
    var disabled = serverOff.indexOf(key) > -1;
    var live = $('#rulesForm input[value="' + key + '"]');
    if (live && !live.checked) disabled = true;
    if (disabled) { out.className = 'alert alert-warning mb-0'; out.textContent = 'Règle désactivée : la valeur serait acceptée sans contrôle.'; return; }
    if (v === '' && key !== 'required') { out.className = 'alert alert-secondary mb-0'; out.textContent = 'Valeur vide : acceptée (champ facultatif).'; return; }
    var ok = def.test(v);
    out.className = 'alert mb-0 alert-' + (ok ? 'success' : 'danger');
    out.innerHTML = (ok ? '<i class="bi bi-check-circle me-1"></i>Valeur acceptée.' : '<i class="bi bi-x-circle me-1"></i>') + (ok ? '' : '');
    if (!ok) out.appendChild(document.createTextNode(def.msg));
  }
  $('#sbRule').addEventListener('change', run); $('#sbVal').addEventListener('input', run);
  document.querySelectorAll('[data-try]').forEach(function (b) { b.addEventListener('click', function () {
    $('#sbRule').value = b.dataset.try; $('#sbVal').value = b.dataset.ex; run(); $('#sandbox').scrollIntoView({ behavior: 'smooth', block: 'center' }); }); });
  var lk = document.querySelector('[data-locked]'); if (lk) lk.addEventListener('change', function () { lk.checked = true; XM.toast('La règle « obligatoire » reste toujours active.', 'warning'); });
  new Chart($('#errChart'), { type: 'bar', data: { labels: ['Téléphone', 'Date future', 'Champ vide', 'Âge', 'E-mail', 'Mot de passe'],
    datasets: [{ label: 'Erreurs bloquées (30 j)', data: [42, 17, 63, 9, 21, 14], backgroundColor: XM_COLORS[0] }] }, options: { indexAxis: 'y', plugins: { legend: { display: false } } } });
})();
</script>
<?php $adminScripts = xa_common_js() . ob_get_clean();
include __DIR__ . '/../partials/admin-bottom.php';
