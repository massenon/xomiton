<?php
require __DIR__ . '/../inc/bootstrap.php';
require_admin();
require_once __DIR__ . '/../inc/x_admin1.php';
require_once __DIR__ . '/../inc/x_admin_ui.php';
$pageTitle = 'Registres numériques';
$adminPage = 'registres';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) { xa_flash('danger', 'Session expirée, veuillez réessayer.'); xa_redirect('registres.php'); }
    $act = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($act === 'save') {
        $d = [];
        foreach (['code', 'kind', 'date', 'zone', 'commune', 'population', 'sexe', 'age', 'type', 'status', 'pair', 'referral', 'notes'] as $k) $d[$k] = trim((string)($_POST[$k] ?? ''));
        $err = [];
        $existing = $id ? store_find('cases', $id) : null;
        if ($d['code'] === '') $d['code'] = $existing['code'] ?? xa_next_case_code();
        if (!preg_match('/^VBG-\d{4}$/', $d['code'])) $err[] = 'Code dossier invalide (format VBG-0000).';
        foreach (store_all('cases') as $c) if ($c['code'] === $d['code'] && (int)$c['id'] !== $id) $err[] = 'Ce code dossier existe déjà.';
        if (!in_array($d['kind'], ['VBG', 'VDH'], true)) $err[] = 'Type de registre invalide.';
        if (!xa_date_ok($d['date']) || $d['date'] > date('Y-m-d')) $err[] = 'Date invalide ou postérieure à aujourd’hui.';
        if (!xa_commune_ok($d['zone'], $d['commune'])) $err[] = 'La commune n’appartient pas au département choisi.';
        if (!in_array($d['population'], x_populations(), true)) $err[] = 'Population invalide.';
        if (!in_array($d['sexe'], x_sexes(), true)) $err[] = 'Sexe invalide.';
        if (!in_array($d['age'], x_age_ranges(), true)) $err[] = 'Tranche d’âge invalide.';
        if (!isset(xa_viol_map()[$d['type']])) $err[] = 'Type de violence invalide.';
        if (!isset(xa_status_case()[$d['status']])) $err[] = 'Statut invalide.';
        if ($d['notes'] !== '' && mb_strlen($d['notes']) < 20) $err[] = 'Les notes doivent contenir au moins 20 caractères (ou rester vides).';
        $d['notes'] = mb_substr($d['notes'], 0, 2000);
        if ($err) { xa_flash('danger', implode(' ', $err)); xa_redirect('registres.php', (string)($_POST['qs'] ?? '')); }
        $d['ts'] = strtotime($d['date']);
        if ($id) { store_update('cases', $id, $d); x_log('Registre modifié', $d['code']); xa_flash('success', 'Dossier ' . $d['code'] . ' mis à jour.'); }
        else { store_append('cases', $d); x_log('Registre créé', $d['code']); xa_flash('success', 'Dossier ' . $d['code'] . ' enregistré.'); }
    } elseif ($act === 'status' && ($c = store_find('cases', $id))) {
        $st = $_POST['status'] ?? '';
        if (isset(xa_status_case()[$st])) { store_update('cases', $id, ['status' => $st]); x_log('Registre statut', $c['code'] . ' → ' . $st); xa_flash('success', 'Statut de ' . $c['code'] . ' : ' . xa_status_case()[$st][0] . '.'); }
    } elseif ($act === 'archive' && ($c = store_find('cases', $id))) {
        store_update('cases', $id, ['status' => 'archive']); x_log('Registre archivé', $c['code']); xa_flash('success', 'Dossier ' . $c['code'] . ' archivé (suppression logique).');
    }
    xa_redirect('registres.php', (string)($_POST['qs'] ?? ''));
}

$f = ['kind' => xa_get('kind'), 'zone' => xa_get('zone'), 'status' => xa_get('statut'), 'type' => xa_get('type'), 'pop' => xa_get('population'), 'from' => xa_get('du'), 'to' => xa_get('au'), 'q' => mb_strtolower(xa_get('q'))];
$all = store_all('cases');
usort($all, fn($a, $b) => strcmp($b['date'], $a['date']) ?: $b['id'] <=> $a['id']);
$counts = array_fill_keys(array_keys(xa_status_case()), 0);
foreach ($all as $c) $counts[$c['status']] = ($counts[$c['status']] ?? 0) + 1;
$rows = array_values(array_filter($all, function ($c) use ($f) {
    if ($f['status'] === '' ? $c['status'] === 'archive' : $c['status'] !== $f['status']) return false;
    if ($f['kind'] && $c['kind'] !== $f['kind']) return false;
    if ($f['zone'] && $c['zone'] !== $f['zone']) return false;
    if ($f['type'] && $c['type'] !== $f['type']) return false;
    if ($f['pop'] && $c['population'] !== $f['pop']) return false;
    if ($f['from'] && $c['date'] < $f['from']) return false;
    if ($f['to'] && $c['date'] > $f['to']) return false;
    if ($f['q'] && mb_strpos(mb_strtolower($c['code'] . ' ' . $c['commune'] . ' ' . $c['pair'] . ' ' . $c['notes']), $f['q']) === false) return false;
    return true;
}));
if (xa_get('export') === 'csv') {
    $out = array_map(fn($c) => [$c['code'], $c['kind'], $c['date'], $c['zone'], $c['commune'], $c['population'], $c['sexe'], $c['age'], xa_viol_label($c['type']), $c['status'], $c['pair'], $c['referral'], $c['notes']], $rows);
    x_log('Export CSV registres', count($out) . ' lignes');
    xa_csv('registre_' . date('Ymd') . '.csv', ['Code', 'Registre', 'Date', 'Département', 'Commune', 'Population', 'Sexe', 'Âge', 'Type de violence', 'Statut', 'Pair', 'Orientation', 'Notes'], $out);
}
$perPage = 15; $page = max(1, (int)xa_get('p', 1)); $pages = max(1, (int)ceil(count($rows) / $perPage)); $page = min($page, $pages);
$slice = array_slice($rows, ($page - 1) * $perPage, $perPage);
$nextCode = xa_next_case_code();

$adminActions = '<button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#caseModal" id="btnNew"><i class="bi bi-plus-lg me-1"></i>Nouveau dossier</button>';
include __DIR__ . '/../partials/admin-top.php';
$qsNow = $_SERVER['QUERY_STRING'] ?? '';
?>
<div class="d-flex flex-wrap gap-2 mb-3">
	<a href="registres.php" class="btn btn-sm <?= $f['status'] === '' ? 'btn-primary' : 'btn-outline-primary' ?>">Actifs <span class="badge bg-light text-dark"><?= $counts['ouvert'] + $counts['en_cours'] + $counts['clos'] ?></span></a>
	<?php foreach (xa_status_case() as $k => $v): ?>
		<a href="?statut=<?= $k ?>" class="btn btn-sm <?= $f['status'] === $k ? 'btn-primary' : 'btn-outline-secondary' ?>"><?= e($v[0]) ?> <span class="badge bg-light text-dark"><?= (int)$counts[$k] ?></span></a>
	<?php endforeach; ?>
</div>

<div class="card mb-3"><div class="card-body">
	<form method="get" class="row g-2 align-items-end">
		<input type="hidden" name="statut" value="<?= e($f['status']) ?>">
		<div class="col-12 col-md-3"><label class="form-label small mb-1">Recherche</label><input type="search" name="q" class="form-control form-control-sm" value="<?= e(xa_get('q')) ?>" placeholder="Code, commune, pair…"></div>
		<div class="col-6 col-md-2 col-lg-1"><label class="form-label small mb-1">Registre</label><select name="kind" class="form-select form-select-sm"><option value="">Tous</option><option <?= $f['kind'] === 'VBG' ? 'selected' : '' ?>>VBG</option><option <?= $f['kind'] === 'VDH' ? 'selected' : '' ?>>VDH</option></select></div>
		<div class="col-6 col-md-3 col-lg-2"><label class="form-label small mb-1">Département</label><select name="zone" class="form-select form-select-sm"><option value="">Tous</option><?php foreach (array_keys(x_zones()) as $z): ?><option <?= $f['zone'] === $z ? 'selected' : '' ?>><?= e($z) ?></option><?php endforeach; ?></select></div>
		<div class="col-6 col-md-4 col-lg-2"><label class="form-label small mb-1">Violence</label><select name="type" class="form-select form-select-sm"><option value="">Toutes</option><?php foreach (xa_viol_map() as $k => $l): ?><option value="<?= $k ?>" <?= $f['type'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
		<div class="col-6 col-md-4 col-lg-2"><label class="form-label small mb-1">Population</label><select name="population" class="form-select form-select-sm"><option value="">Toutes</option><?php foreach (x_populations() as $p): ?><option <?= $f['pop'] === $p ? 'selected' : '' ?>><?= e($p) ?></option><?php endforeach; ?></select></div>
		<div class="col-6 col-md-2 col-lg-1"><label class="form-label small mb-1">Du</label><input type="date" name="du" class="form-control form-control-sm" value="<?= e($f['from']) ?>"></div>
		<div class="col-6 col-md-2 col-lg-1"><label class="form-label small mb-1">Au</label><input type="date" name="au" class="form-control form-control-sm" value="<?= e($f['to']) ?>"></div>
		<div class="col-12 d-flex gap-2"><button class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Filtrer</button><a class="btn btn-light btn-sm" href="registres.php">Réinitialiser</a><a class="btn btn-outline-primary btn-sm ms-auto" href="?<?= e(xa_qs(['export' => 'csv', 'p' => ''])) ?>"><i class="bi bi-filetype-csv me-1"></i>Export CSV (<?= count($rows) ?>)</a></div>
	</form>
</div></div>

<div class="card">
	<div class="xm-table-wrap">
	<table data-xm-nopage class="table table-hover align-middle mb-0">
		<thead><tr><th>Code</th><th>Reg.</th><th>Date</th><th>Zone</th><th>Population</th><th>Sexe / âge</th><th>Violence</th><th>Statut</th><th>Pair</th><th></th></tr></thead>
		<tbody>
		<?php if (!$slice): ?><tr><td colspan="10" class="text-center text-secondary py-4">Aucun dossier.</td></tr><?php endif; ?>
		<?php foreach ($slice as $c): ?>
			<tr>
				<td class="fw-semibold"><?= e($c['code']) ?></td>
				<td><span class="badge bg-<?= $c['kind'] === 'VBG' ? 'primary' : 'info text-dark' ?>"><?= e($c['kind']) ?></span></td>
				<td class="text-nowrap"><?= e(date('d/m/Y', strtotime($c['date']))) ?></td>
				<td><?= e($c['zone']) ?><div class="small text-secondary"><?= e($c['commune']) ?></div></td>
				<td class="small"><?= e($c['population']) ?></td>
				<td class="small"><?= e($c['sexe']) ?><div class="text-secondary"><?= e($c['age']) ?> ans</div></td>
				<td class="small"><?= e(xa_viol_label($c['type'])) ?></td>
				<td><?= xa_badge(xa_status_case(), $c['status']) ?></td>
				<td class="small"><?= e($c['pair']) ?></td>
				<td class="text-end text-nowrap">
					<button class="btn btn-sm btn-light" title="Voir" data-view="<?= e(json_encode($c, JSON_UNESCAPED_UNICODE)) ?>"><i class="bi bi-eye"></i></button>
					<button class="btn btn-sm btn-light" title="Modifier" data-edit="<?= e(json_encode($c, JSON_UNESCAPED_UNICODE)) ?>"><i class="bi bi-pencil"></i></button>
					<?php if ($c['status'] !== 'archive'): ?>
					<form method="post" class="d-inline" onsubmit="return confirm('Archiver le dossier <?= e($c['code']) ?> ?')"><?= csrf_field() ?><input type="hidden" name="action" value="archive"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="qs" value="<?= e($qsNow) ?>"><button class="btn btn-sm btn-light text-danger" title="Archiver"><i class="bi bi-archive"></i></button></form>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	</div>
	<?= xa_pagebar(count($rows), $perPage, $page, array_diff_key($_GET, ['p' => 1])) ?>
</div>

<!-- Ajout / édition -->
<div class="modal fade" id="caseModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
		<form method="post" data-xm-validate data-xm-offline="collecte" novalidate>
			<?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="cmId" value=""><input type="hidden" name="qs" value="<?= e($qsNow) ?>">
			<div class="modal-header"><h5 class="modal-title" id="cmTitle">Nouveau dossier</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
			<div class="modal-body"><div class="row g-3">
				<div class="col-md-4"><label class="form-label" for="cmCode">Code dossier</label><input class="form-control" id="cmCode" name="code" data-rule="case_code" value="<?= e($nextCode) ?>" placeholder="VBG-0000"></div>
				<div class="col-md-4"><label class="form-label" for="cmKind">Registre</label><select class="form-select" id="cmKind" name="kind" required><option>VBG</option><option>VDH</option></select></div>
				<div class="col-md-4"><label class="form-label" for="cmDate">Date des faits / de l’accueil</label><input type="date" class="form-control" id="cmDate" name="date" required data-rule="date_not_future" max="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>"></div>
				<div class="col-md-4"><label class="form-label" for="cmZone">Département</label><select class="form-select" id="cmZone" name="zone" required data-zone-select="#cmCommune"><option value="">—</option><?php foreach (array_keys(x_zones()) as $z): ?><option><?= e($z) ?></option><?php endforeach; ?></select></div>
				<div class="col-md-4"><label class="form-label" for="cmCommune">Commune</label><select class="form-select" id="cmCommune" name="commune" required><option value="">—</option></select></div>
				<div class="col-md-4"><label class="form-label" for="cmType">Type de violence</label><select class="form-select" id="cmType" name="type" required><option value="">—</option><?php foreach (xa_viol_map() as $k => $l): ?><option value="<?= $k ?>"><?= e($l) ?></option><?php endforeach; ?></select></div>
				<div class="col-md-6"><label class="form-label" for="cmPop">Population</label><select class="form-select" id="cmPop" name="population" required><option value="">—</option><?php foreach (x_populations() as $p): ?><option><?= e($p) ?></option><?php endforeach; ?></select></div>
				<div class="col-md-3"><label class="form-label" for="cmSexe">Sexe</label><select class="form-select" id="cmSexe" name="sexe" required><option value="">—</option><?php foreach (x_sexes() as $s): ?><option><?= e($s) ?></option><?php endforeach; ?></select></div>
				<div class="col-md-3"><label class="form-label" for="cmAge">Tranche d’âge</label><select class="form-select" id="cmAge" name="age" required><option value="">—</option><?php foreach (x_age_ranges() as $s): ?><option><?= e($s) ?></option><?php endforeach; ?></select></div>
				<div class="col-md-4"><label class="form-label" for="cmStatus">Statut</label><select class="form-select" id="cmStatus" name="status" required><?php foreach (xa_status_case() as $k => $v): ?><option value="<?= $k ?>"><?= e($v[0]) ?></option><?php endforeach; ?></select></div>
				<div class="col-md-4"><label class="form-label" for="cmPair">Pair référent·e</label><input class="form-control" id="cmPair" name="pair" list="pairList" value="<?= e(x_user()['name']) ?>"><datalist id="pairList"><?php foreach (array_unique(array_column($all, 'pair')) as $p): ?><option value="<?= e($p) ?>"><?php endforeach; ?></datalist></div>
				<div class="col-md-4"><label class="form-label" for="cmRef">Orientation</label><select class="form-select" id="cmRef" name="referral"><option value="">—</option><?php foreach (array_unique(array_merge(x_roles_annuaire(), array_column($all, 'referral'))) as $r): ?><option><?= e($r) ?></option><?php endforeach; ?></select></div>
				<div class="col-12"><label class="form-label" for="cmNotes">Notes (pseudonymisées, 20 caractères min.)</label><textarea class="form-control" id="cmNotes" name="notes" rows="3" data-rule="min_len_20" placeholder="Ne jamais saisir de nom ni de coordonnées de la personne."></textarea></div>
			</div></div>
			<div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button class="btn btn-primary">Enregistrer</button></div>
		</form>
	</div></div>
</div>

<!-- Détail -->
<div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog"><div class="modal-content">
		<div class="modal-header"><h5 class="modal-title" id="vmTitle"></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
		<div class="modal-body"><dl class="row mb-3" id="vmBody"></dl>
			<form method="post" class="input-group input-group-sm"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" id="vmId"><input type="hidden" name="qs" value="<?= e($qsNow) ?>">
				<span class="input-group-text">Changer le statut</span>
				<select name="status" id="vmStatus" class="form-select"><?php foreach (xa_status_case() as $k => $v): ?><option value="<?= $k ?>"><?= e($v[0]) ?></option><?php endforeach; ?></select>
				<button class="btn btn-primary">Appliquer</button>
			</form>
		</div>
		<div class="modal-footer"><a class="btn btn-outline-primary btn-sm" id="vmSuivi" href="#"><i class="bi bi-person-heart me-1"></i>Suivi</a><a class="btn btn-outline-primary btn-sm" id="vmFiche" href="#"><i class="bi bi-file-earmark-medical me-1"></i>Fiche de référence</a></div>
	</div></div>
</div>

<?php
$adminScripts = '<script>
XM.zones = ' . json_encode(x_zones(), JSON_UNESCAPED_UNICODE) . ';
(function(){
  const esc = s => String(s == null ? "" : s).replace(/[&<>"]/g, c => ({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;"}[c]));
  const viol = ' . json_encode(xa_viol_map(), JSON_UNESCAPED_UNICODE) . ', stat = ' . json_encode(array_map(fn($v) => $v[0], xa_status_case()), JSON_UNESCAPED_UNICODE) . ';
  const $ = id => document.getElementById(id);
  const cm = new bootstrap.Modal($("caseModal")), vm = new bootstrap.Modal($("viewModal"));
  const next = ' . json_encode($nextCode) . ';
  $("btnNew").addEventListener("click", () => {
    $("cmTitle").textContent = "Nouveau dossier"; $("cmId").value = ""; $("cmCode").value = next;
    $("caseModal").querySelector("form").querySelectorAll(".is-invalid,.is-valid").forEach(e => e.classList.remove("is-invalid","is-valid"));
  });
  document.addEventListener("click", ev => {
    const ed = ev.target.closest("[data-edit]");
    if (ed) {
      const c = JSON.parse(ed.dataset.edit);
      $("cmTitle").textContent = "Modifier " + c.code; $("cmId").value = c.id;
      ["code","kind","date","zone","type","population","sexe","age","status","pair","notes"].forEach(k => { const el = $("caseModal").querySelector("[name=" + k + "]"); if (el) el.value = c[k] || ""; });
      $("cmCommune").innerHTML = "<option value=\"\">—</option>" + (XM.zones[c.zone]||[]).map(x => "<option" + (x===c.commune?" selected":"") + ">" + x + "</option>").join("");
      $("cmRef").value = c.referral || "";
      cm.show(); return;
    }
    const v = ev.target.closest("[data-view]");
    if (v) {
      const c = JSON.parse(v.dataset.view);
      $("vmTitle").textContent = "Dossier " + c.code; $("vmId").value = c.id; $("vmStatus").value = c.status;
      const rows = [["Registre", c.kind],["Date", c.date],["Zone", c.zone + " / " + c.commune],["Population", c.population],["Sexe / âge", c.sexe + " · " + c.age + " ans"],["Violence", viol[c.type]||c.type],["Statut", stat[c.status]],["Pair", c.pair],["Orientation", c.referral||"—"],["Notes", c.notes||"—"]];
      $("vmBody").innerHTML = rows.map(r => "<dt class=\"col-4 small text-secondary\">" + r[0] + "</dt><dd class=\"col-8\">" + esc(r[1]) + "</dd>").join("");
      $("vmSuivi").href = "suivi.php?case=" + encodeURIComponent(c.code); $("vmFiche").href = "fiches.php?case=" + encodeURIComponent(c.code);
      vm.show();
    }
  });
})();
</script>';
include __DIR__ . '/../partials/admin-bottom.php';
