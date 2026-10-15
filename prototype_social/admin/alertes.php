<?php
require __DIR__ . '/../inc/bootstrap.php';
require_admin();
require_once __DIR__ . '/../inc/x_admin1.php';
$pageTitle = 'Alertes & signalements';
$adminPage = 'alertes';

/* ---------- Actions POST ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $back = ($_POST['back'] ?? '') === 'index.php' ? 'index.php' : 'alertes.php';
    $backQs = $back === 'alertes.php' ? (string)($_POST['qs'] ?? '') : '';
    if (!csrf_ok()) { xa_flash('danger', 'Session expirée, veuillez réessayer.'); xa_redirect($back, $backQs); }
    $id = (int)($_POST['id'] ?? 0);
    $al = store_find('alertes', $id);
    if (!$al) { xa_flash('danger', 'Alerte introuvable.'); xa_redirect($back, $backQs); }
    $u = x_user();
    $notes = $al['notes'] ?? [];
    $act = $_POST['action'] ?? '';
    if ($act === 'take') {
        $notes[] = ['at' => date('Y-m-d H:i'), 'by' => $u['name'], 'text' => 'Prise en charge par l’administration.'];
        store_update('alertes', $id, ['status' => 'en_cours', 'notes' => $notes]);
        x_log('Alerte prise en charge', '#' . $id);
        xa_flash('success', 'Alerte #' . $id . ' prise en charge.');
    } elseif ($act === 'status') {
        $st = $_POST['status'] ?? '';
        if (!isset(xa_status_alert()[$st])) { xa_flash('danger', 'Statut invalide.'); xa_redirect($back, $backQs); }
        $notes[] = ['at' => date('Y-m-d H:i'), 'by' => $u['name'], 'text' => 'Statut : ' . xa_status_alert()[$st][0]];
        store_update('alertes', $id, ['status' => $st, 'notes' => $notes, 'status_at' => time()]);
        x_log('Alerte statut', '#' . $id . ' → ' . $st);
        xa_flash('success', 'Statut mis à jour : ' . xa_status_alert()[$st][0] . '.');
    } elseif ($act === 'route') {
        $to = (int)($_POST['routed_to'] ?? 0);
        $map = xa_annuaire_map();
        if (!isset($map[$to])) { xa_flash('danger', 'Personne de l’annuaire introuvable.'); xa_redirect($back, $backQs); }
        $notes[] = ['at' => date('Y-m-d H:i'), 'by' => $u['name'], 'text' => 'Routé vers ' . xa_routed_label($to, $map)];
        store_update('alertes', $id, ['routed_to' => $to, 'notes' => $notes]);
        x_log('Alerte re-routée', '#' . $id . ' → annuaire #' . $to);
        xa_flash('success', 'Alerte routée vers ' . xa_routed_label($to, $map) . '.');
    } elseif ($act === 'note') {
        $txt = trim((string)($_POST['note'] ?? ''));
        if ($txt === '') { xa_flash('danger', 'La note est vide.'); xa_redirect($back, $backQs); }
        $notes[] = ['at' => date('Y-m-d H:i'), 'by' => $u['name'], 'text' => mb_substr($txt, 0, 1000), 'internal' => true];
        store_update('alertes', $id, ['notes' => $notes]);
        x_log('Alerte note interne', '#' . $id);
        xa_flash('success', 'Note interne ajoutée.');
    }
    xa_redirect($back, $backQs);
}

/* ---------- Filtres ---------- */
$fType = xa_get('type'); $fStatus = xa_get('statut'); $fPrio = xa_get('priorite'); $fZone = xa_get('zone'); $fQ = mb_strtolower(xa_get('q'));
$all = store_all('alertes');
usort($all, fn($a, $b) => ($b['created'] ?? 0) <=> ($a['created'] ?? 0));
$ann = xa_annuaire_map();
$match = function ($a, $ignoreType = false) use ($fType, $fStatus, $fPrio, $fZone, $fQ, $ann) {
    if (!$ignoreType && $fType && $a['type'] !== $fType) return false;
    if ($fStatus && $a['status'] !== $fStatus) return false;
    if ($fPrio && $a['priority'] !== $fPrio) return false;
    if ($fZone && $a['zone'] !== $fZone) return false;
    if ($fQ && mb_strpos(mb_strtolower($a['description'] . ' ' . ($a['commune'] ?? '') . ' ' . ($a['pseudo'] ?? '') . ' #' . $a['id'] . ' ' . xa_routed_label($a['routed_to'] ?? 0, $ann)), $fQ) === false) return false;
    return true;
};
$rows = array_values(array_filter($all, fn($a) => $match($a)));
$typeCounts = ['' => 0, 'denonciation' => 0, 'besoin' => 0, 'sos' => 0];
foreach ($all as $a) if ($match($a, true)) { $typeCounts['']++; $typeCounts[$a['type']]++; }

function xa_alert_rows_html($rows, $ann) {
    ob_start();
    if (!$rows) echo '<tr><td colspan="9" class="text-center text-secondary py-4">Aucune alerte ne correspond aux filtres.</td></tr>';
    $types = xa_types_alert();
    foreach ($rows as $a):
        $t = $types[$a['type']] ?? [$a['type'], 'bi-bell'];
        $payload = $a + ['routed_label' => xa_routed_label($a['routed_to'] ?? 0, $ann), 'type_label' => $t[0], 'category_label' => xa_viol_label($a['category'] ?? ''), 'date_label' => date('d/m/Y H:i', $a['created'])];
        $needs = [];
        foreach (($a['needs'] ?? []) as $n) $needs[] = x_needs()[$n] ?? $n;
        $payload['needs_labels'] = $needs;
        $border = ['critique' => 'danger', 'haute' => 'warning', 'normale' => 'secondary'][$a['priority']] ?? 'secondary'; ?>
		<tr style="border-left:4px solid var(--bs-<?= $border ?>)" data-id="<?= (int)$a['id'] ?>">
			<td class="fw-semibold">#<?= (int)$a['id'] ?></td>
			<td><span class="badge <?= $a['type'] === 'sos' ? 'bg-danger' : 'bg-primary bg-opacity-10 text-primary' ?>"><i class="bi <?= $t[1] ?> me-1"></i><?= e($t[0]) ?></span></td>
			<td><?= xa_badge(xa_prio(), $a['priority']) ?></td>
			<td><?= e($a['zone']) ?><div class="small text-secondary"><?= e($a['commune'] ?? '') ?></div></td>
			<td class="small" style="max-width:260px"><?= e(mb_strimwidth($a['description'], 0, 80, '…')) ?><?php if (!empty($a['anonymous'])): ?> <span class="badge bg-dark">anonyme</span><?php endif; ?></td>
			<td class="small">
				<?php if ($a['type'] === 'sos' && isset($a['lat'])): ?>
					<a href="<?= e(xa_maps_link($a['lat'], $a['lng'])) ?>" target="_blank" rel="noopener" class="text-danger"><i class="bi bi-geo-alt-fill"></i> <?= e(number_format($a['lat'], 4)) ?>, <?= e(number_format($a['lng'], 4)) ?></a>
				<?php else: ?><span class="text-secondary">—</span><?php endif; ?>
			</td>
			<td class="small"><?= e(xa_routed_label($a['routed_to'] ?? 0, $ann)) ?></td>
			<td><?= xa_badge(xa_status_alert(), $a['status']) ?><div class="small text-secondary"><?= e(date('d/m H:i', $a['created'])) ?></div></td>
			<td class="text-end text-nowrap">
				<button type="button" class="btn btn-sm btn-primary-soft" data-alert="<?= e(json_encode($payload, JSON_UNESCAPED_UNICODE)) ?>"><i class="bi bi-eye"></i> Détail</button>
			</td>
		</tr>
	<?php endforeach;
    return ob_get_clean();
}

/* ---------- Export CSV ---------- */
if (xa_get('export') === 'csv') {
    $out = [];
    foreach ($rows as $a) $out[] = [$a['id'], date('Y-m-d H:i', $a['created']), $a['type'], $a['priority'], $a['status'], $a['zone'], $a['commune'] ?? '', $a['category'] ?? '', $a['description'], $a['lat'] ?? '', $a['lng'] ?? '', xa_routed_label($a['routed_to'] ?? 0, $ann)];
    x_log('Export CSV alertes', count($out) . ' lignes');
    xa_csv('alertes_' . date('Ymd') . '.csv', ['ID', 'Date', 'Type', 'Priorité', 'Statut', 'Département', 'Commune', 'Catégorie', 'Description', 'Latitude', 'Longitude', 'Routé vers'], $out);
}

/* ---------- JSON pour auto-refresh ---------- */
if (xa_get('json') === '1') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['html' => xa_alert_rows_html($rows, $ann), 'counts' => $typeCounts, 'total' => count($rows), 'new' => count(array_filter($all, fn($a) => $a['status'] === 'nouveau')), 'at' => date('H:i:s')], JSON_UNESCAPED_UNICODE);
    exit;
}

$adminActions = '<a class="btn btn-outline-primary btn-sm d-none d-md-inline-flex" href="?' . e(xa_qs(['export' => 'csv'])) . '"><i class="bi bi-filetype-csv me-1"></i>Export CSV</a>';
include __DIR__ . '/../partials/admin-top.php';
$nNew = count(array_filter($all, fn($a) => $a['status'] === 'nouveau'));
$nSos = count(array_filter($all, fn($a) => $a['type'] === 'sos' && $a['status'] !== 'traite'));
$nCrit = count(array_filter($all, fn($a) => $a['priority'] === 'critique' && $a['status'] !== 'traite'));
$nDone = count(array_filter($all, fn($a) => $a['status'] === 'traite'));
?>
<div class="row g-3 mb-3">
	<div class="col-6 col-lg-3"><div class="card xm-stat danger"><div class="card-body"><div class="small text-secondary">Nouvelles alertes</div><div class="kpi-value" id="kpiNew"><?= $nNew ?></div></div></div></div>
	<div class="col-6 col-lg-3"><div class="card xm-stat danger"><div class="card-body"><div class="small text-secondary">SOS actifs</div><div class="kpi-value"><?= $nSos ?></div></div></div></div>
	<div class="col-6 col-lg-3"><div class="card xm-stat warning"><div class="card-body"><div class="small text-secondary">Priorité critique ouvertes</div><div class="kpi-value"><?= $nCrit ?></div></div></div></div>
	<div class="col-6 col-lg-3"><div class="card xm-stat success"><div class="card-body"><div class="small text-secondary">Traitées</div><div class="kpi-value"><?= $nDone ?></div></div></div></div>
</div>

<div class="card mb-3">
	<div class="card-body">
		<ul class="nav nav-pills mb-3 flex-wrap gap-1" id="typeTabs">
			<?php foreach (['' => 'Toutes', 'sos' => 'SOS', 'denonciation' => 'Dénonciations', 'besoin' => 'Besoins'] as $k => $l): ?>
				<li class="nav-item"><a class="nav-link <?= $fType === $k ? 'active' : '' ?>" href="?<?= e(xa_qs(['type' => $k])) ?>"><?= e($l) ?> <span class="badge bg-light text-dark ms-1" data-count="<?= e($k) ?>"><?= (int)$typeCounts[$k] ?></span></a></li>
			<?php endforeach; ?>
		</ul>
		<form method="get" class="row g-2 align-items-end" id="filters">
			<input type="hidden" name="type" value="<?= e($fType) ?>">
			<div class="col-12 col-md-3"><label class="form-label small mb-1">Recherche</label><input type="search" name="q" value="<?= e(xa_get('q')) ?>" class="form-control form-control-sm" placeholder="Mot-clé, n°, commune…"></div>
			<div class="col-6 col-md-2"><label class="form-label small mb-1">Statut</label>
				<select name="statut" class="form-select form-select-sm"><option value="">Tous</option><?php foreach (xa_status_alert() as $k => $v): ?><option value="<?= $k ?>" <?= $fStatus === $k ? 'selected' : '' ?>><?= e($v[0]) ?></option><?php endforeach; ?></select></div>
			<div class="col-6 col-md-2"><label class="form-label small mb-1">Priorité</label>
				<select name="priorite" class="form-select form-select-sm"><option value="">Toutes</option><?php foreach (xa_prio() as $k => $v): ?><option value="<?= $k ?>" <?= $fPrio === $k ? 'selected' : '' ?>><?= e($v[0]) ?></option><?php endforeach; ?></select></div>
			<div class="col-6 col-md-2"><label class="form-label small mb-1">Département</label>
				<select name="zone" class="form-select form-select-sm"><option value="">Tous</option><?php foreach (array_keys(x_zones()) as $z): ?><option <?= $fZone === $z ? 'selected' : '' ?>><?= e($z) ?></option><?php endforeach; ?></select></div>
			<div class="col-6 col-md-3 d-flex gap-2"><button class="btn btn-primary btn-sm flex-fill"><i class="bi bi-funnel me-1"></i>Filtrer</button><a class="btn btn-light btn-sm" href="alertes.php">Réinitialiser</a></div>
		</form>
	</div>
</div>

<div class="card">
	<div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
		<h2 class="h6 mb-0"><span id="totalRows"><?= count($rows) ?></span> alerte(s)</h2>
		<span class="small text-secondary"><i class="bi bi-arrow-repeat me-1"></i>Actualisation automatique toutes les 30 s · dernière : <span id="lastRefresh"><?= date('H:i:s') ?></span></span>
	</div>
	<div class="xm-table-wrap">
		<table class="table table-hover align-middle mb-0">
			<thead><tr><th>N°</th><th>Type</th><th>Priorité</th><th>Zone</th><th>Description</th><th>GPS</th><th>Routé vers</th><th>Statut</th><th></th></tr></thead>
			<tbody id="alertBody"><?= xa_alert_rows_html($rows, $ann) ?></tbody>
		</table>
	</div>
</div>

<!-- Détail -->
<div class="modal fade" id="alertModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-scrollable">
		<div class="modal-content">
			<div class="modal-header"><h5 class="modal-title" id="amTitle">Alerte</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
			<div class="modal-body">
				<div class="row g-3">
					<div class="col-lg-7">
						<div class="d-flex flex-wrap gap-2 mb-2" id="amBadges"></div>
						<p id="amDesc" class="mb-2"></p>
						<dl class="row small mb-2" id="amMeta"></dl>
						<div id="amGps" class="mb-3"></div>
						<h6>Notes & historique</h6>
						<ul class="list-unstyled small" id="amNotes"></ul>
					</div>
					<div class="col-lg-5">
						<form method="post" class="border rounded p-2 mb-3">
							<?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" class="amId"><input type="hidden" name="qs" value="<?= e($_SERVER['QUERY_STRING'] ?? '') ?>">
							<label class="form-label small mb-1">Changer le statut</label>
							<div class="input-group input-group-sm">
								<select name="status" id="amStatus" class="form-select"><?php foreach (xa_status_alert() as $k => $v): ?><option value="<?= $k ?>"><?= e($v[0]) ?></option><?php endforeach; ?></select>
								<button class="btn btn-primary">Appliquer</button>
							</div>
						</form>
						<form method="post" class="border rounded p-2 mb-3">
							<?= csrf_field() ?><input type="hidden" name="action" value="route"><input type="hidden" name="id" class="amId"><input type="hidden" name="qs" value="<?= e($_SERVER['QUERY_STRING'] ?? '') ?>">
							<label class="form-label small mb-1">Re-router vers une personne de l’annuaire</label>
							<select id="amRole" class="form-select form-select-sm mb-1"><option value="">Tous les rôles</option><?php foreach (x_roles_annuaire() as $r): ?><option><?= e($r) ?></option><?php endforeach; ?></select>
							<div class="form-check small mb-1"><input class="form-check-input" type="checkbox" id="amSameZone" checked><label class="form-check-label" for="amSameZone">Même département uniquement</label></div>
							<div class="input-group input-group-sm">
								<select name="routed_to" id="amRoute" class="form-select" required>
									<?php foreach ($ann as $a): if (empty($a['active'])) continue; ?><option value="<?= (int)$a['id'] ?>" data-role="<?= e($a['role']) ?>" data-zone="<?= e($a['zone']) ?>"><?= e($a['name']) ?> — <?= e($a['role']) ?> (<?= e($a['zone']) ?>)</option><?php endforeach; ?>
								</select>
								<button class="btn btn-outline-primary">Router</button>
							</div>
						</form>
						<form method="post" class="border rounded p-2">
							<?= csrf_field() ?><input type="hidden" name="action" value="note"><input type="hidden" name="id" class="amId"><input type="hidden" name="qs" value="<?= e($_SERVER['QUERY_STRING'] ?? '') ?>">
							<label class="form-label small mb-1" for="amNote">Note interne</label>
							<textarea name="note" id="amNote" rows="3" class="form-control form-control-sm mb-1" required placeholder="Visible uniquement par l’équipe"></textarea>
							<button class="btn btn-sm btn-outline-secondary w-100">Ajouter la note</button>
						</form>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<?php
$adminScripts = '<script>
(function(){
  const esc = s => String(s == null ? "" : s).replace(/[&<>"\']/g, c => ({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","\'":"&#39;"}[c]));
  const stLab = {nouveau:["Nouveau","danger"], en_cours:["En cours","warning"], traite:["Traité","success"]};
  const prLab = {critique:["Critique","danger"], haute:["Haute","warning"], normale:["Normale","secondary"]};
  const modalEl = document.getElementById("alertModal"); const modal = new bootstrap.Modal(modalEl);
  function filterRoute(a){
    const role = document.getElementById("amRole").value, same = document.getElementById("amSameZone").checked;
    const sel = document.getElementById("amRoute"); let first = null;
    [...sel.options].forEach(o => { const ok = (!role || o.dataset.role === role) && (!same || !window._curZone || o.dataset.zone === window._curZone); o.hidden = !ok; o.disabled = !ok; if (ok && !first) first = o; });
    if (sel.selectedOptions[0] && sel.selectedOptions[0].disabled && first) sel.value = first.value;
  }
  document.getElementById("amRole").addEventListener("change", filterRoute);
  document.getElementById("amSameZone").addEventListener("change", filterRoute);
  document.addEventListener("click", ev => {
    const b = ev.target.closest("[data-alert]"); if (!b) return;
    const a = JSON.parse(b.dataset.alert); window._curZone = a.zone;
    document.getElementById("amTitle").textContent = "Alerte #" + a.id + " — " + a.type_label;
    document.querySelectorAll(".amId").forEach(i => i.value = a.id);
    document.getElementById("amBadges").innerHTML = `<span class="badge bg-${stLab[a.status][1]} ${stLab[a.status][1]==="warning"?"text-dark":""}">${stLab[a.status][0]}</span><span class="badge bg-${prLab[a.priority][1]} ${prLab[a.priority][1]==="warning"?"text-dark":""}">Priorité ${prLab[a.priority][0].toLowerCase()}</span>` + (a.anonymous ? `<span class="badge bg-dark">Anonyme</span>` : "") + `<span class="badge bg-light text-dark">${esc(a.category_label)}</span>`;
    document.getElementById("amDesc").textContent = a.description;
    document.getElementById("amMeta").innerHTML = `<dt class="col-4">Reçue le</dt><dd class="col-8">${esc(a.date_label)}</dd><dt class="col-4">Zone</dt><dd class="col-8">${esc(a.zone)} / ${esc(a.commune||"—")}</dd><dt class="col-4">Source</dt><dd class="col-8">${esc(a.source||"web")}</dd><dt class="col-4">Besoins</dt><dd class="col-8">${(a.needs_labels||[]).map(esc).join(", ")||"—"}</dd><dt class="col-4">Pseudo</dt><dd class="col-8">${esc(a.pseudo||"—")}</dd><dt class="col-4">Routé vers</dt><dd class="col-8"><i class="bi bi-person-check text-primary"></i> ${esc(a.routed_label)}</dd>`;
    document.getElementById("amGps").innerHTML = a.lat ? `<a class="btn btn-sm btn-outline-danger" target="_blank" rel="noopener" href="https://www.google.com/maps?q=${a.lat},${a.lng}"><i class="bi bi-geo-alt-fill me-1"></i>Voir sur Google Maps (${(+a.lat).toFixed(4)}, ${(+a.lng).toFixed(4)})</a>` : "";
    document.getElementById("amNotes").innerHTML = (a.notes||[]).slice().reverse().map(n => `<li class="border-start border-3 ps-2 mb-2 ${n.internal?"border-warning":"border-primary"}"><div class="text-secondary">${esc(n.at)} · ${esc(n.by)}${n.internal?" · note interne":""}</div>${esc(n.text)}</li>`).join("") || `<li class="text-secondary">Aucune note.</li>`;
    document.getElementById("amStatus").value = a.status;
    document.getElementById("amRoute").value = a.routed_to; filterRoute();
    modal.show();
  });
  /* Auto-refresh 30 s */
  setInterval(async () => {
    if (modalEl.classList.contains("show") || document.hidden) return;
    try {
      const qs = new URLSearchParams(location.search); qs.set("json","1"); qs.delete("export");
      const r = await fetch("alertes.php?" + qs.toString(), {headers:{Accept:"application/json"}}); if (!r.ok) return;
      const j = await r.json();
      document.getElementById("alertBody").innerHTML = j.html;
      document.getElementById("totalRows").textContent = j.total;
      document.getElementById("kpiNew").textContent = j.new;
      document.getElementById("lastRefresh").textContent = j.at;
      Object.keys(j.counts).forEach(k => { const el = document.querySelector("[data-count=\'" + k + "\']"); if (el) el.textContent = j.counts[k]; });
    } catch (e) {}
  }, 30000);
})();
</script>';
include __DIR__ . '/../partials/admin-bottom.php';
