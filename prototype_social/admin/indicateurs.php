<?php
require __DIR__ . '/../inc/bootstrap.php';
require_admin();
require_once __DIR__ . '/../inc/x_admin1.php';
$pageTitle = 'Indicateurs de suivi';
$adminPage = 'indicateurs';

function xa_ind_defaults() {
    $d = function ($code, $label, $def, $formula, $source, $freq, $target, $unit, $calc, $better = 'up') { return compact('code', 'label', 'def', 'formula', 'source', 'freq', 'target', 'unit', 'calc', 'better') + ['builtin' => true]; };
    return [
        $d('IND-01', 'Cas enregistrés (VBG + VDH)', 'Nombre de dossiers enregistrés dans les registres sur la période.', 'Σ dossiers non archivés', 'Registres numériques', 'Mensuelle', 80, 'nb', 'cas_total'),
        $d('IND-02', 'Cas de VBG', 'Dossiers du registre VBG.', 'Σ dossiers kind = VBG', 'Registre VBG', 'Mensuelle', 55, 'nb', 'cas_vbg'),
        $d('IND-03', 'Cas de VDH', 'Dossiers du registre violations des droits humains.', 'Σ dossiers kind = VDH', 'Registre VDH', 'Mensuelle', 25, 'nb', 'cas_vdh'),
        $d('IND-04', '% de cas pris en charge en moins de 72 h', 'Part des cas pour lesquels le premier service est délivré dans les 72 h (simulation).', 'cas pris en charge ≤ 72 h / cas × 100', 'Registres + suivi', 'Mensuelle', 90, '%', 'pct_72h'),
        $d('IND-05', '% de survivant·es orienté·es', 'Part des cas orientés vers une personne ou structure de l’annuaire.', 'cas avec orientation / cas × 100', 'Registres', 'Mensuelle', 85, '%', 'pct_orientes'),
        $d('IND-06', '% de cas clos', 'Part des dossiers clôturés.', 'cas clos / cas × 100', 'Registres', 'Trimestrielle', 60, '%', 'pct_clos'),
        $d('IND-07', 'Pairs animateur·rices actifs', 'Pairs ayant enregistré au moins un dossier sur la période.', 'Σ pairs distincts', 'Registres', 'Trimestrielle', 6, 'nb', 'pairs'),
        $d('IND-08', 'Séances CCC / événements', 'Causeries, ateliers et événements organisés (module événements).', 'Σ événements', 'Réseau social', 'Mensuelle', 12, 'nb', 'seances'),
        $d('IND-09', 'Taux d’ouverture de la newsletter', 'Part des e-mails de campagne ouverts par les abonné·es.', 'ouvertures uniques / e-mails envoyés × 100', 'Newsletter', 'Mensuelle', 35, '%', 'nl_open'),
        $d('IND-10', '% d’alertes traitées dans les 24 h', 'Part des alertes reçues traitées dans les 24 h (simulation).', 'alertes traitées ≤ 24 h / alertes × 100', 'Alertes', 'Hebdomadaire', 80, '%', 'pct_alertes24'),
        $d('IND-11', 'Alertes reçues', 'Dénonciations, besoins et SOS reçus.', 'Σ alertes', 'Alertes', 'Hebdomadaire', 30, 'nb', 'alertes'),
        $d('IND-12', 'SOS reçus', 'Alertes de type SOS avec localisation GPS.', 'Σ alertes type = sos', 'Alertes', 'Hebdomadaire', 0, 'nb', 'sos', 'down'),
        $d('IND-13', '% de suivis réalisés', 'Étapes de suivi réalisées sur étapes planifiées.', 'suivis réalisés / suivis × 100', 'Suivi des victimes', 'Mensuelle', 80, '%', 'pct_suivis'),
        $d('IND-14', 'Survivant·es suivi·es', 'Dossiers distincts avec au moins une étape de suivi.', 'Σ codes dossier distincts', 'Suivi', 'Mensuelle', 50, 'nb', 'suivies'),
        $d('IND-15', 'Utilisateur·rices actif·ves', 'Comptes au statut actif.', 'Σ utilisateurs actifs', 'Utilisateurs', 'Mensuelle', 20, 'nb', 'users'),
        $d('IND-16', 'Couverture géographique', 'Part des 12 départements ayant enregistré au moins un cas.', 'départements avec cas / 12 × 100', 'Registres', 'Trimestrielle', 75, '%', 'couverture'),
    ];
}
function xa_ind_compute($calc, $x) {
    $cases = $x['cases']; $n = count($cases);
    $pct = fn($a, $b) => $b > 0 ? round($a * 100 / $b, 1) : 0;
    switch ($calc) {
        case 'cas_total': return $n;
        case 'cas_vbg': return count(array_filter($cases, fn($c) => $c['kind'] === 'VBG'));
        case 'cas_vdh': return count(array_filter($cases, fn($c) => $c['kind'] === 'VDH'));
        case 'pct_72h': return $pct(count(array_filter($cases, fn($c) => ($c['id'] * 7) % 10 < 8)), $n);
        case 'pct_orientes': return $pct(count(array_filter($cases, fn($c) => !empty($c['referral']))), $n);
        case 'pct_clos': return $pct(count(array_filter($cases, fn($c) => $c['status'] === 'clos')), $n);
        case 'pairs': return count(array_unique(array_column($cases, 'pair')));
        case 'seances': return count($x['events']);
        case 'nl_open': $sent = 0; $op = 0; foreach ($x['newsletter'] as $c) { $sent += count($c['recipients'] ?? []); $op += count($c['opens'] ?? []); } return $pct($op, $sent);
        case 'pct_alertes24': return $pct(count(array_filter($x['alertes'], fn($a) => $a['status'] === 'traite' && $a['id'] % 4 !== 0)), count($x['alertes']));
        case 'alertes': return count($x['alertes']);
        case 'sos': return count(array_filter($x['alertes'], fn($a) => $a['type'] === 'sos'));
        case 'pct_suivis': return $pct(count(array_filter($x['suivi'], fn($s) => $s['outcome'] === 'Réalisé')), count($x['suivi']));
        case 'suivies': return count(array_unique(array_column($x['suivi'], 'case_code')));
        case 'users': return count(array_filter($x['users'], fn($u) => $u['status'] === 'actif'));
        case 'couverture': return $pct(count(array_unique(array_column($cases, 'zone'))), 12);
    }
    return 0;
}

if (!store_all('indicateurs')) store_save('indicateurs', xa_ind_defaults());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) { xa_flash('danger', 'Session expirée.'); xa_redirect('indicateurs.php'); }
    if (($_POST['action'] ?? '') === 'delete') {
        $i = store_find('indicateurs', (int)$_POST['id']);
        if ($i && empty($i['builtin'])) { store_delete('indicateurs', $i['id']); x_log('Indicateur supprimé', $i['code']); xa_flash('success', 'Indicateur supprimé.'); }
        else xa_flash('danger', 'Les indicateurs standards ne peuvent pas être supprimés.');
        xa_redirect('indicateurs.php');
    }
    $d = [];
    foreach (['code', 'label', 'def', 'formula', 'source', 'freq', 'unit'] as $k) $d[$k] = mb_substr(trim((string)($_POST[$k] ?? '')), 0, 300);
    $err = [];
    if ($d['label'] === '') $err[] = 'Le libellé est obligatoire.';
    if (!is_numeric($_POST['target'] ?? '') || !is_numeric($_POST['value'] ?? '')) $err[] = 'Cible et valeur actuelle doivent être numériques.';
    if (!in_array($d['freq'], ['Hebdomadaire', 'Mensuelle', 'Trimestrielle', 'Semestrielle', 'Annuelle'], true)) $err[] = 'Fréquence invalide.';
    if (!in_array($d['unit'], ['nb', '%'], true)) $err[] = 'Unité invalide.';
    if ($err) { xa_flash('danger', implode(' ', $err)); xa_redirect('indicateurs.php'); }
    if ($d['code'] === '') $d['code'] = 'IND-C' . sprintf('%02d', count(store_all('indicateurs')) + 1);
    $d += ['target' => (float)$_POST['target'], 'manual' => (float)$_POST['value'], 'calc' => 'manuel', 'better' => ($_POST['better'] ?? 'up') === 'down' ? 'down' : 'up', 'builtin' => false];
    store_append('indicateurs', $d);
    x_log('Indicateur ajouté', $d['code']);
    xa_flash('success', 'Indicateur « ' . $d['label'] . ' » ajouté.');
    xa_redirect('indicateurs.php');
}

$from = xa_get('du'); $to = xa_get('au'); $zone = xa_get('zone');
$cases = array_filter(store_all('cases'), fn($c) => $c['status'] !== 'archive' && (!$zone || $c['zone'] === $zone));
$codes = array_column($cases, 'code');
$alertes = array_filter(store_all('alertes'), fn($a) => !$zone || $a['zone'] === $zone);
$suivi = array_filter(store_all('suivi'), fn($s) => !$zone || in_array($s['case_code'], $codes, true));
$base = ['newsletter' => store_all('newsletter_campaigns'), 'users' => store_all('users'), 'events' => store_all('events')];
$mk = function ($f, $t) use ($cases, $alertes, $suivi, $base) {
    $in = fn($d) => (!$f || $d >= $f) && (!$t || $d <= $t);
    return $base + [
        'cases' => array_values(array_filter($cases, fn($c) => $in($c['date']))),
        'alertes' => array_values(array_filter($alertes, fn($a) => $in(date('Y-m-d', $a['created'])))),
        'suivi' => array_values(array_filter($suivi, fn($s) => $in($s['date']))),
    ];
};
$ctx = $mk($from, $to);
$months = xa_last_months(6);
$mctx = []; foreach ($months as $ym => $l) $mctx[$ym] = $mk($ym . '-01', $ym . '-31');

$inds = store_all('indicateurs');
$freqF = xa_get('freq');
if ($freqF) $inds = array_values(array_filter($inds, fn($i) => $i['freq'] === $freqF));

function xa_spark($vals) {
    $w = 90; $h = 26; $n = count($vals); if ($n < 2) return '';
    $mn = min($vals); $mx = max($vals); $rg = ($mx - $mn) ?: 1; $pts = [];
    foreach ($vals as $i => $v) $pts[] = round($i * $w / ($n - 1), 1) . ',' . round($h - 2 - ($v - $mn) * ($h - 4) / $rg, 1);
    return '<svg width="' . $w . '" height="' . $h . '" viewBox="0 0 ' . $w . ' ' . $h . '" aria-hidden="true"><polyline fill="none" stroke="#0433C3" stroke-width="2" points="' . implode(' ', $pts) . '"/></svg>';
}
$rowsOut = [];
foreach ($inds as $i) {
    $manual = $i['calc'] === 'manuel';
    $val = $manual ? (float)$i['manual'] : xa_ind_compute($i['calc'], $ctx);
    $trend = $manual ? [] : array_map(fn($m) => xa_ind_compute($i['calc'], $m), array_values($mctx));
    $t = (float)$i['target'];
    if (($i['better'] ?? 'up') === 'down') $p = $t > 0 ? max(0, 100 - max(0, $val - $t) / $t * 100) : ($val == 0 ? 100 : max(0, 100 - $val * 25));
    else $p = $t > 0 ? min(100, $val / $t * 100) : 100;
    $rowsOut[] = compact('i', 'val', 'trend', 'p');
}
if (xa_get('export') === 'csv') {
    xa_csv('indicateurs_' . date('Ymd') . '.csv', ['Code', 'Libellé', 'Formule', 'Source', 'Fréquence', 'Cible', 'Valeur', 'Unité', 'Atteinte %'], array_map(fn($r) => [$r['i']['code'], $r['i']['label'], $r['i']['formula'], $r['i']['source'], $r['i']['freq'], $r['i']['target'], $r['val'], $r['i']['unit'], round($r['p'])], $rowsOut));
}
$adminActions = '<button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#indModal"><i class="bi bi-plus-lg me-1"></i>Indicateur personnalisé</button>';
include __DIR__ . '/../partials/admin-top.php';
$ok = count(array_filter($rowsOut, fn($r) => $r['p'] >= 100)); $mid = count(array_filter($rowsOut, fn($r) => $r['p'] >= 70 && $r['p'] < 100)); $low = count($rowsOut) - $ok - $mid;
?>
<div class="row g-3 mb-3">
	<div class="col-4"><div class="card xm-stat success"><div class="card-body"><div class="small text-secondary">Cible atteinte</div><div class="kpi-value"><?= $ok ?></div></div></div></div>
	<div class="col-4"><div class="card xm-stat warning"><div class="card-body"><div class="small text-secondary">En progression (≥ 70 %)</div><div class="kpi-value"><?= $mid ?></div></div></div></div>
	<div class="col-4"><div class="card xm-stat danger"><div class="card-body"><div class="small text-secondary">À renforcer</div><div class="kpi-value"><?= $low ?></div></div></div></div>
</div>
<div class="card mb-3"><div class="card-body">
	<form method="get" class="row g-2 align-items-end">
		<div class="col-6 col-md-2"><label class="form-label small mb-1">Du</label><input type="date" name="du" class="form-control form-control-sm" value="<?= e($from) ?>"></div>
		<div class="col-6 col-md-2"><label class="form-label small mb-1">Au</label><input type="date" name="au" class="form-control form-control-sm" value="<?= e($to) ?>"></div>
		<div class="col-6 col-md-3"><label class="form-label small mb-1">Département</label><select name="zone" class="form-select form-select-sm"><option value="">Tous</option><?php foreach (array_keys(x_zones()) as $z): ?><option <?= $zone === $z ? 'selected' : '' ?>><?= e($z) ?></option><?php endforeach; ?></select></div>
		<div class="col-6 col-md-2"><label class="form-label small mb-1">Fréquence</label><select name="freq" class="form-select form-select-sm"><option value="">Toutes</option><?php foreach (['Hebdomadaire', 'Mensuelle', 'Trimestrielle', 'Semestrielle', 'Annuelle'] as $f): ?><option <?= $freqF === $f ? 'selected' : '' ?>><?= $f ?></option><?php endforeach; ?></select></div>
		<div class="col-12 col-md-3 d-flex gap-2"><button class="btn btn-primary btn-sm flex-fill">Appliquer</button><a class="btn btn-light btn-sm" href="indicateurs.php">Réinit.</a><a class="btn btn-outline-primary btn-sm" href="?<?= e(xa_qs(['export' => 'csv'])) ?>" title="Export CSV"><i class="bi bi-filetype-csv"></i></a></div>
	</form>
	<div class="small text-secondary mt-2">La tendance (courbe) montre les 6 derniers mois, sur le périmètre de zone choisi. Certaines valeurs (délais 72 h / 24 h) sont simulées dans ce prototype.</div>
</div></div>
<div class="card"><div class="xm-table-wrap"><table class="table align-middle mb-0">
	<thead><tr><th>Code</th><th>Indicateur</th><th class="d-none d-lg-table-cell">Source · fréquence</th><th class="text-end">Valeur</th><th style="min-width:160px">Cible</th><th class="d-none d-md-table-cell">Tendance</th><th></th></tr></thead>
	<tbody>
	<?php foreach ($rowsOut as $r): $i = $r['i']; $col = $r['p'] >= 100 ? 'success' : ($r['p'] >= 70 ? 'warning' : 'danger'); ?>
		<tr>
			<td class="small fw-semibold"><?= e($i['code']) ?></td>
			<td><a href="#" class="fw-semibold text-body text-decoration-none" data-bs-toggle="popover" data-bs-trigger="focus" tabindex="0" data-bs-html="true" data-bs-title="<?= e($i['code']) ?>" data-bs-content="<?= e('<b>Définition :</b> ' . e($i['def']) . '<br><b>Formule :</b> ' . e($i['formula'])) ?>"><?= e($i['label']) ?> <i class="bi bi-info-circle text-secondary small"></i></a></td>
			<td class="small d-none d-lg-table-cell"><?= e($i['source']) ?><div class="text-secondary"><?= e($i['freq']) ?></div></td>
			<td class="text-end fw-bold text-nowrap"><?= e(rtrim(rtrim(number_format($r['val'], 1, ',', ' '), '0'), ',')) ?><?= $i['unit'] === '%' ? ' %' : '' ?></td>
			<td><div class="progress" style="height:8px" role="progressbar" aria-valuenow="<?= round($r['p']) ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar bg-<?= $col ?>" style="width:<?= round($r['p']) ?>%"></div></div><div class="small text-secondary"><?= ($i['better'] ?? 'up') === 'down' ? '≤ ' : '' ?><?= e($i['target']) ?><?= $i['unit'] === '%' ? ' %' : '' ?> · <?= round($r['p']) ?> %</div></td>
			<td class="d-none d-md-table-cell"><?= $r['trend'] ? xa_spark($r['trend']) : '<span class="small text-secondary">saisie manuelle</span>' ?></td>
			<td class="text-end"><?php if (empty($i['builtin'])): ?><form method="post" onsubmit="return confirm('Supprimer cet indicateur ?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$i['id'] ?>"><button class="btn btn-sm btn-light text-danger" title="Supprimer"><i class="bi bi-trash"></i></button></form><?php endif; ?></td>
		</tr>
	<?php endforeach; if (!$rowsOut): ?><tr><td colspan="7" class="text-center text-secondary py-4">Aucun indicateur.</td></tr><?php endif; ?>
	</tbody></table></div></div>

<div class="modal fade" id="indModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg"><div class="modal-content">
	<form method="post" data-xm-validate novalidate>
		<?= csrf_field() ?>
		<div class="modal-header"><h5 class="modal-title">Nouvel indicateur personnalisé</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
		<div class="modal-body"><div class="row g-3">
			<div class="col-md-3"><label class="form-label" for="iCode">Code</label><input class="form-control" id="iCode" name="code" maxlength="12" placeholder="auto"></div>
			<div class="col-md-9"><label class="form-label" for="iLabel">Libellé</label><input class="form-control" id="iLabel" name="label" required maxlength="150"></div>
			<div class="col-12"><label class="form-label" for="iDef">Définition</label><textarea class="form-control" id="iDef" name="def" rows="2"></textarea></div>
			<div class="col-md-6"><label class="form-label" for="iFor">Formule</label><input class="form-control" id="iFor" name="formula"></div>
			<div class="col-md-6"><label class="form-label" for="iSrc">Source</label><input class="form-control" id="iSrc" name="source"></div>
			<div class="col-md-3"><label class="form-label" for="iFreq">Fréquence</label><select class="form-select" id="iFreq" name="freq"><?php foreach (['Hebdomadaire', 'Mensuelle', 'Trimestrielle', 'Semestrielle', 'Annuelle'] as $f): ?><option><?= $f ?></option><?php endforeach; ?></select></div>
			<div class="col-md-3"><label class="form-label" for="iUnit">Unité</label><select class="form-select" id="iUnit" name="unit"><option value="nb">Nombre</option><option value="%">Pourcentage</option></select></div>
			<div class="col-md-2"><label class="form-label" for="iTarget">Cible</label><input class="form-control" id="iTarget" name="target" required data-rule="positive_int" inputmode="numeric"></div>
			<div class="col-md-2"><label class="form-label" for="iVal">Valeur</label><input class="form-control" id="iVal" name="value" required data-rule="positive_int" inputmode="numeric"></div>
			<div class="col-md-2"><label class="form-label" for="iBetter">Sens</label><select class="form-select" id="iBetter" name="better"><option value="up">Hausse</option><option value="down">Baisse</option></select></div>
		</div></div>
		<div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button class="btn btn-primary">Ajouter</button></div>
	</form></div></div></div>
<?php
$adminScripts = '<script>document.querySelectorAll("[data-bs-toggle=popover]").forEach(el => { el.addEventListener("click", e => e.preventDefault()); new bootstrap.Popover(el); });</script>';
include __DIR__ . '/../partials/admin-bottom.php';
