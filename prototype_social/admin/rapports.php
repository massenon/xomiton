<?php
require __DIR__ . '/../inc/bootstrap.php';
require_admin();
require_once __DIR__ . '/../inc/x_admin1.php';
$pageTitle = 'Rapports automatisés';
$adminPage = 'rapports';
$freqs = ['Hebdomadaire', 'Mensuelle', 'Trimestrielle', 'Semestrielle', 'Annuelle'];
$rtypes = ['mensuel' => 'Mensuel', 'trimestriel' => 'Trimestriel', 'semestriel' => 'Semestriel', 'annuel' => 'Annuel', 'personnalise' => 'Personnalisé'];
$fmts = ['xlsx' => 'Excel', 'pdf' => 'PDF', 'doc' => 'Word', 'csv' => 'CSV'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    if ($act === 'log_report') {
        header('Content-Type: application/json; charset=utf-8');
        if (!csrf_ok()) { http_response_code(403); echo json_encode(['ok' => false]); exit; }
        $row = store_append('rapports_generes', [
            'at' => date('Y-m-d H:i'), 'by' => x_user()['name'], 'title' => mb_substr(trim($_POST['title'] ?? ''), 0, 160),
            'rtype' => isset($rtypes[$_POST['rtype'] ?? '']) ? $_POST['rtype'] : 'personnalise', 'period' => mb_substr(trim($_POST['period'] ?? ''), 0, 60),
            'format' => isset($fmts[$_POST['format'] ?? '']) ? $_POST['format'] : 'apercu', 'cases' => (int)($_POST['cases'] ?? 0), 'dims' => mb_substr(trim($_POST['dims'] ?? ''), 0, 120),
        ]);
        x_log('Rapport généré', $row['title'] . ' (' . $row['format'] . ')');
        echo json_encode(['ok' => true, 'row' => $row, 'label' => $rtypes[$row['rtype']] ?? '', 'fmt' => $fmts[$row['format']] ?? 'Aperçu'], JSON_UNESCAPED_UNICODE); exit;
    }
    if (!csrf_ok()) { xa_flash('danger', 'Session expirée.'); xa_redirect('rapports.php'); }
    if ($act === 'plan_save') {
        $rec = array_values(array_filter(array_map('trim', preg_split('/[,;\s]+/', (string)($_POST['recipients'] ?? '')))));
        $bad = array_filter($rec, fn($m) => !filter_var($m, FILTER_VALIDATE_EMAIL));
        $err = [];
        if (!$rec) $err[] = 'Indiquez au moins un destinataire.';
        if ($bad) $err[] = 'Adresse(s) e-mail invalide(s) : ' . implode(', ', $bad) . '.';
        if (!isset($rtypes[$_POST['rtype'] ?? ''])) $err[] = 'Type de rapport invalide.';
        if (!in_array($_POST['freq'] ?? '', $freqs, true)) $err[] = 'Fréquence invalide.';
        $day = (int)($_POST['day'] ?? 1); if ($day < 1 || $day > 28) $err[] = 'Le jour d’envoi doit être compris entre 1 et 28.';
        $f = array_values(array_intersect(array_keys($fmts), (array)($_POST['formats'] ?? [])));
        if (!$f) $err[] = 'Choisissez au moins un format.';
        if ($err) { xa_flash('danger', implode(' ', $err)); xa_redirect('rapports.php', '#plan'); }
        $dims = array_values(array_intersect(['departement', 'commune', 'population', 'sexe', 'age'], (array)($_POST['pdims'] ?? [])));
        store_append('rapport_plans', ['rtype' => $_POST['rtype'], 'freq' => $_POST['freq'], 'day' => $day, 'formats' => $f, 'recipients' => $rec, 'dims' => $dims, 'active' => true, 'created' => date('Y-m-d H:i'), 'by' => x_user()['name']]);
        x_log('Planification rapport', $_POST['freq'] . ' → ' . implode(',', $rec));
        xa_flash('success', 'Génération automatique planifiée (' . strtolower($_POST['freq']) . ').');
    } elseif ($act === 'plan_toggle' && ($p = store_find('rapport_plans', (int)$_POST['id']))) {
        store_update('rapport_plans', $p['id'], ['active' => empty($p['active'])]); xa_flash('success', 'Planification mise à jour.');
    } elseif ($act === 'plan_delete') {
        store_delete('rapport_plans', (int)$_POST['id']); xa_flash('success', 'Planification supprimée.');
    } elseif ($act === 'hist_clear') {
        store_save('rapports_generes', []); xa_flash('success', 'Historique vidé.');
    }
    xa_redirect('rapports.php');
}

$cases = array_values(array_map(fn($c) => ['code' => $c['code'], 'kind' => $c['kind'], 'date' => $c['date'], 'zone' => $c['zone'], 'commune' => $c['commune'], 'population' => $c['population'], 'sexe' => $c['sexe'], 'age' => $c['age'], 'type' => $c['type'], 'status' => $c['status']],
    array_filter(store_all('cases'), fn($c) => $c['status'] !== 'archive')));
$alertes = array_map(fn($a) => ['type' => $a['type'], 'status' => $a['status'], 'priority' => $a['priority'], 'zone' => $a['zone'], 'date' => date('Y-m-d', $a['created'])], store_all('alertes'));
$suivi = array_map(fn($s) => ['case' => $s['case_code'], 'date' => $s['date'], 'outcome' => $s['outcome']], store_all('suivi'));
$history = array_reverse(store_all('rapports_generes'));
$plans = array_reverse(store_all('rapport_plans'));
include __DIR__ . '/../partials/admin-top.php';
?>
<div class="row g-4">
	<div class="col-xl-4">
		<div class="card mb-4"><div class="card-header"><h2 class="h6 mb-0"><i class="bi bi-sliders me-1"></i>Paramètres du rapport</h2></div><div class="card-body">
			<form id="repForm" onsubmit="return false">
				<div class="mb-2"><label class="form-label" for="rType">Type de rapport</label><select class="form-select" id="rType"><?php foreach ($rtypes as $k => $l): ?><option value="<?= $k ?>"><?= e($l) ?></option><?php endforeach; ?></select></div>
				<div class="row g-2 mb-2"><div class="col-6"><label class="form-label" for="rFrom">Du</label><input type="date" class="form-control" id="rFrom"></div><div class="col-6"><label class="form-label" for="rTo">Au</label><input type="date" class="form-control" id="rTo"></div></div>
				<div class="mb-2"><label class="form-label" for="rTitle">Titre</label><input class="form-control" id="rTitle" maxlength="120"></div>
				<fieldset class="mb-2"><legend class="form-label fs-6">Désagrégation</legend>
					<?php foreach (['departement' => 'Zone : département', 'commune' => 'Zone : commune', 'population' => 'Type de population', 'sexe' => 'Sexe', 'age' => 'Tranche d’âge'] as $k => $l): ?>
					<div class="form-check"><input class="form-check-input dim" type="checkbox" value="<?= $k ?>" id="d_<?= $k ?>" <?= in_array($k, ['departement', 'sexe', 'age']) ? 'checked' : '' ?>><label class="form-check-label" for="d_<?= $k ?>"><?= e($l) ?></label></div>
					<?php endforeach; ?></fieldset>
				<fieldset class="mb-3"><legend class="form-label fs-6">Indicateurs / sections à inclure</legend>
					<?php foreach (['kpi' => 'Chiffres clés', 'evol' => 'Évolution mensuelle des cas', 'viol' => 'Répartition par type de violence', 'statut' => 'Statut des dossiers', 'registre' => 'Registres VBG / VDH', 'orient' => 'Orientation (services)', 'alertes' => 'Alertes & SOS', 'suivi' => 'Suivi des victimes'] as $k => $l): ?>
					<div class="form-check"><input class="form-check-input sec" type="checkbox" value="<?= $k ?>" id="s_<?= $k ?>" checked><label class="form-check-label" for="s_<?= $k ?>"><?= e($l) ?></label></div>
					<?php endforeach; ?></fieldset>
				<button type="button" class="btn btn-primary w-100" id="btnGen"><i class="bi bi-lightning-charge me-1"></i>Générer le rapport</button>
			</form>
		</div></div>

		<div class="card" id="plan"><div class="card-header"><h2 class="h6 mb-0"><i class="bi bi-calendar-check me-1"></i>Planification automatique</h2></div><div class="card-body">
			<form method="post" novalidate>
				<?= csrf_field() ?><input type="hidden" name="action" value="plan_save">
				<div class="row g-2 mb-2">
					<div class="col-6"><label class="form-label" for="pType">Rapport</label><select class="form-select form-select-sm" id="pType" name="rtype"><?php foreach ($rtypes as $k => $l): ?><option value="<?= $k ?>"><?= e($l) ?></option><?php endforeach; ?></select></div>
					<div class="col-6"><label class="form-label" for="pFreq">Fréquence</label><select class="form-select form-select-sm" id="pFreq" name="freq"><?php foreach ($freqs as $f): ?><option><?= $f ?></option><?php endforeach; ?></select></div>
					<div class="col-6"><label class="form-label" for="pDay">Jour d’envoi (1-28)</label><input type="number" min="1" max="28" value="1" class="form-control form-control-sm" id="pDay" name="day"></div>
					<div class="col-6"><label class="form-label d-block">Formats</label><?php foreach ($fmts as $k => $l): ?><div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="formats[]" value="<?= $k ?>" id="pf_<?= $k ?>" <?= $k === 'pdf' ? 'checked' : '' ?>><label class="form-check-label small" for="pf_<?= $k ?>"><?= e($l) ?></label></div><?php endforeach; ?></div>
				</div>
				<div class="mb-2"><label class="form-label small mb-1">Désagrégation</label><div><?php foreach (['departement' => 'Département', 'commune' => 'Commune', 'population' => 'Population', 'sexe' => 'Sexe', 'age' => 'Âge'] as $k => $l): ?><div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="pdims[]" value="<?= $k ?>" id="pd_<?= $k ?>" <?= $k !== 'commune' ? 'checked' : '' ?>><label class="form-check-label small" for="pd_<?= $k ?>"><?= $l ?></label></div><?php endforeach; ?></div></div>
				<div class="mb-2"><label class="form-label" for="pRec">Destinataires (e-mails séparés par une virgule)</label><textarea class="form-control form-control-sm" id="pRec" name="recipients" rows="2" required placeholder="direction@xomiton.bj, bailleur@exemple.org"></textarea></div>
				<button class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-plus-lg me-1"></i>Planifier</button>
			</form>
			<hr>
			<?php if (!$plans): ?><p class="small text-secondary mb-0">Aucune planification.</p><?php endif; ?>
			<?php foreach ($plans as $p): ?>
			<div class="d-flex justify-content-between align-items-start gap-2 mb-2 small">
				<div><span class="badge bg-<?= $p['active'] ? 'success' : 'secondary' ?>"><?= $p['active'] ? 'Actif' : 'En pause' ?></span> <b><?= e($rtypes[$p['rtype']] ?? $p['rtype']) ?></b> · <?= e(strtolower($p['freq'])) ?>, le <?= (int)$p['day'] ?><div class="text-secondary"><?= e(implode(', ', array_map(fn($f) => $fmts[$f] ?? $f, $p['formats']))) ?> → <?= e(implode(', ', $p['recipients'])) ?></div></div>
				<div class="text-nowrap"><form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="plan_toggle"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn btn-sm btn-light" title="Activer / mettre en pause"><i class="bi bi-<?= $p['active'] ? 'pause' : 'play' ?>"></i></button></form><form method="post" class="d-inline" onsubmit="return confirm('Supprimer cette planification ?')"><?= csrf_field() ?><input type="hidden" name="action" value="plan_delete"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn btn-sm btn-light text-danger" title="Supprimer"><i class="bi bi-trash"></i></button></form></div>
			</div>
			<?php endforeach; ?>
		</div></div>
	</div>

	<div class="col-xl-8">
		<div id="repEmpty" class="card"><div class="card-body text-center text-secondary py-5"><i class="bi bi-file-earmark-bar-graph display-5"></i><p class="mt-2 mb-0">Choisissez la période et la désagrégation puis cliquez sur « Générer le rapport ».</p></div></div>
		<div id="repOut" class="d-none">
			<div class="card mb-3"><div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
				<div><h2 class="h5 mb-0" id="repTitle"></h2><div class="small text-secondary" id="repMeta"></div></div>
				<div class="d-flex flex-wrap gap-2"><button class="btn btn-success btn-sm" data-export="xlsx"><i class="bi bi-file-earmark-excel me-1"></i>Excel</button><button class="btn btn-danger btn-sm" data-export="pdf"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</button><button class="btn btn-primary btn-sm" data-export="doc"><i class="bi bi-file-earmark-word me-1"></i>Word</button><button class="btn btn-outline-secondary btn-sm" data-export="csv"><i class="bi bi-filetype-csv me-1"></i>CSV</button></div>
			</div></div>
			<div class="row g-3 mb-3" id="repKpis"></div>
			<div class="row g-3 mb-3" id="repCharts"></div>
			<div id="repTables"></div>
		</div>

		<div class="card mt-4"><div class="card-header d-flex justify-content-between align-items-center"><h2 class="h6 mb-0"><i class="bi bi-clock-history me-1"></i>Historique des rapports générés</h2>
			<?php if ($history): ?><form method="post" onsubmit="return confirm('Vider l’historique ?')"><?= csrf_field() ?><input type="hidden" name="action" value="hist_clear"><button class="btn btn-sm btn-light">Vider</button></form><?php endif; ?></div>
			<div class="xm-table-wrap"><table class="table table-sm align-middle mb-0"><thead><tr><th>Date</th><th>Rapport</th><th>Période</th><th>Format</th><th>Dossiers</th><th>Par</th></tr></thead><tbody id="histBody">
				<?php foreach ($history as $h): ?><tr><td class="small text-nowrap"><?= e($h['at']) ?></td><td><?= e($h['title']) ?><div class="small text-secondary"><?= e($rtypes[$h['rtype']] ?? '') ?></div></td><td class="small"><?= e($h['period']) ?></td><td><span class="badge bg-secondary"><?= e($fmts[$h['format']] ?? 'Aperçu') ?></span></td><td><?= (int)$h['cases'] ?></td><td class="small"><?= e($h['by']) ?></td></tr><?php endforeach; ?>
				<tr id="histEmpty" class="<?= $history ? 'd-none' : '' ?>"><td colspan="6" class="text-center text-secondary py-3">Aucun rapport généré pour l’instant.</td></tr>
			</tbody></table></div></div>
	</div>
</div>

<?php
$js = <<<'JS'
(function(){
  const D = __DATA__;
  const $ = id => document.getElementById(id);
  const esc = s => String(s == null ? "" : s).replace(/[&<>"]/g, c => ({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;"}[c]));
  const fmtD = d => d.split("-").reverse().join("/");
  const pad = n => String(n).padStart(2, "0");
  const iso = d => d.getFullYear() + "-" + pad(d.getMonth() + 1) + "-" + pad(d.getDate());
  const MOIS = ["janv.","févr.","mars","avr.","mai","juin","juil.","août","sept.","oct.","nov.","déc."];
  const DIMS = { departement: ["Département", c => c.zone], commune: ["Commune", c => c.commune], population: ["Type de population", c => c.population], sexe: ["Sexe", c => c.sexe], age: ["Tranche d’âge", c => c.age] };
  const NAMES = { rType: {mensuel:"Rapport mensuel", trimestriel:"Rapport trimestriel", semestriel:"Rapport semestriel", annuel:"Rapport annuel", personnalise:"Rapport personnalisé"} };
  let R = null, charts = [];

  function setPeriod() {
    const t = $("rType").value, n = new Date(); let f, e;
    if (t === "mensuel") { f = new Date(n.getFullYear(), n.getMonth(), 1); e = new Date(n.getFullYear(), n.getMonth() + 1, 0); }
    else if (t === "trimestriel") { const q = Math.floor(n.getMonth() / 3) * 3; f = new Date(n.getFullYear(), q, 1); e = new Date(n.getFullYear(), q + 3, 0); }
    else if (t === "semestriel") { const q = n.getMonth() < 6 ? 0 : 6; f = new Date(n.getFullYear(), q, 1); e = new Date(n.getFullYear(), q + 6, 0); }
    else if (t === "annuel") { f = new Date(n.getFullYear(), 0, 1); e = new Date(n.getFullYear(), 11, 31); }
    else return;
    $("rFrom").value = iso(f); $("rTo").value = iso(e);
    $("rTitle").value = NAMES.rType[t];
  }
  $("rType").addEventListener("change", setPeriod);
  ["rFrom","rTo"].forEach(i => $(i).addEventListener("change", () => { $("rType").value = "personnalise"; }));
  $("rType").value = "annuel"; setPeriod();

  function countBy(rows, fn, order) {
    const m = {}; rows.forEach(r => { const k = fn(r); m[k] = (m[k] || 0) + 1; });
    let keys = Object.keys(m); if (order) keys = order.filter(k => k in m).concat(keys.filter(k => !order.includes(k))); else keys.sort((a, b) => m[b] - m[a]);
    return keys.map(k => [k, m[k]]);
  }
  function months(f, t) { const out = []; let d = new Date(f.slice(0,7) + "-01T00:00:00"); const end = new Date(t.slice(0,7) + "-01T00:00:00"); while (d <= end && out.length < 48) { out.push(d.getFullYear() + "-" + pad(d.getMonth() + 1)); d = new Date(d.getFullYear(), d.getMonth() + 1, 1); } return out; }
  const pct = (a, b) => b ? (Math.round(a * 1000 / b) / 10).toString().replace(".", ",") + " %" : "0 %";

  function build() {
    const f = $("rFrom").value, t = $("rTo").value;
    if (!f || !t || f > t) { XM.toast("Période invalide : vérifiez les dates.", "danger"); return null; }
    const dims = [...document.querySelectorAll(".dim:checked")].map(i => i.value);
    const secs = [...document.querySelectorAll(".sec:checked")].map(i => i.value);
    const inP = d => d >= f && d <= t;
    const cs = D.cases.filter(c => inP(c.date)), al = D.alertes.filter(a => inP(a.date)), su = D.suivi.filter(s => inP(s.date));
    const viol = D.viol, order = Object.keys(viol);
    const rep = { title: $("rTitle").value.trim() || "Rapport Xomiton", rtype: $("rType").value, period: fmtD(f) + " – " + fmtD(t), from: f, to: t, generated: new Date().toLocaleString("fr-FR"), n: cs.length, dims, kpis: [], charts: [], tables: [] };
    const vbg = cs.filter(c => c.kind === "VBG").length, vdh = cs.length - vbg;
    if (secs.includes("kpi")) {
      rep.kpis = [["Dossiers enregistrés", cs.length], ["Registre VBG", vbg], ["Registre VDH", vdh], ["Dossiers clos", pct(cs.filter(c => c.status === "clos").length, cs.length)], ["Alertes reçues", al.length], ["SOS", al.filter(a => a.type === "sos").length], ["Suivis réalisés", pct(su.filter(s => s.outcome === "Réalisé").length, su.length)]];
      rep.tables.push({ title: "Chiffres clés", head: ["Indicateur", "Valeur"], rows: rep.kpis.map(k => [k[0], String(k[1])]) });
    }
    if (secs.includes("evol")) {
      const ms = months(f, t); const m = {}; cs.forEach(c => { const k = c.date.slice(0,7); m[k] = (m[k] || 0) + 1; });
      const lab = ms.map(k => MOIS[+k.slice(5) - 1] + " " + k.slice(2,4));
      rep.charts.push({ id: "c_evol", title: "Évolution mensuelle des cas", type: "line", labels: lab, data: ms.map(k => m[k] || 0) });
      rep.tables.push({ title: "Évolution mensuelle", head: ["Mois", "Dossiers"], rows: ms.map((k, i) => [lab[i], String(m[k] || 0)]) });
    }
    if (secs.includes("viol")) {
      const r = countBy(cs, c => viol[c.type] || c.type, order.map(k => viol[k]));
      rep.charts.push({ id: "c_viol", title: "Types de violence", type: "doughnut", labels: r.map(x => x[0]), data: r.map(x => x[1]) });
      rep.tables.push({ title: "Répartition par type de violence", head: ["Type", "Dossiers", "%"], rows: r.map(x => [x[0], String(x[1]), pct(x[1], cs.length)]) });
    }
    if (secs.includes("statut")) {
      const r = countBy(cs, c => D.statuts[c.status] || c.status);
      rep.charts.push({ id: "c_stat", title: "Statut des dossiers", type: "bar", labels: r.map(x => x[0]), data: r.map(x => x[1]) });
      rep.tables.push({ title: "Statut des dossiers", head: ["Statut", "Dossiers", "%"], rows: r.map(x => [x[0], String(x[1]), pct(x[1], cs.length)]) });
    }
    if (secs.includes("registre")) {
      rep.tables.push({ title: "Registres VBG / VDH", head: ["Registre", "Dossiers", "%"], rows: [["VBG", String(vbg), pct(vbg, cs.length)], ["VDH", String(vdh), pct(vdh, cs.length)]] });
    }
    if (secs.includes("orient")) {
      const r = countBy(cs, c => c.referral || "Non orienté");
      rep.tables.push({ title: "Orientation (services)", head: ["Orientation", "Dossiers", "%"], rows: r.map(x => [x[0], String(x[1]), pct(x[1], cs.length)]) });
    }
    dims.forEach((d, idx) => {
      const [label, fn] = DIMS[d]; const ord = d === "age" ? D.ages : d === "sexe" ? D.sexes : null;
      const r = countBy(cs, fn, ord);
      if (idx < 3) rep.charts.push({ id: "c_d" + d, title: "Dossiers par " + label.toLowerCase(), type: "bar", labels: r.map(x => x[0]), data: r.map(x => x[1]) });
      const rows = r.map(([k]) => { const sub = cs.filter(c => fn(c) === k); const a = sub.filter(c => c.kind === "VBG").length; return [k, String(a), String(sub.length - a), String(sub.length), pct(sub.length, cs.length)]; });
      rows.push(["Total", String(vbg), String(vdh), String(cs.length), "100 %"]);
      rep.tables.push({ title: "Désagrégation par " + label.toLowerCase(), head: [label, "VBG", "VDH", "Total", "%"], rows });
      const hd = [label].concat(order.map(k => viol[k]), ["Total"]);
      const rows2 = r.map(([k]) => { const sub = cs.filter(c => fn(c) === k); return [k].concat(order.map(o => String(sub.filter(c => c.type === o).length)), [String(sub.length)]); });
      rows2.push(["Total"].concat(order.map(o => String(cs.filter(c => c.type === o).length)), [String(cs.length)]));
      rep.tables.push({ title: label + " × type de violence", head: hd, rows: rows2 });
    });
    if (dims.length >= 2) {
      const [l1, f1] = DIMS[dims[0]], [l2, f2] = DIMS[dims[1]];
      const k1 = countBy(cs, f1, dims[0] === "age" ? D.ages : dims[0] === "sexe" ? D.sexes : null).map(x => x[0]);
      const k2 = countBy(cs, f2, dims[1] === "age" ? D.ages : dims[1] === "sexe" ? D.sexes : null).map(x => x[0]);
      const rows = k1.map(a => [a].concat(k2.map(b => String(cs.filter(c => f1(c) === a && f2(c) === b).length)), [String(cs.filter(c => f1(c) === a).length)]));
      rep.tables.push({ title: l1 + " × " + l2.toLowerCase(), head: [l1].concat(k2, ["Total"]), rows });
    }
    if (secs.includes("alertes")) {
      const types = {denonciation:"Dénonciation", besoin:"Besoin", sos:"SOS"}, st = {nouveau:"Nouveau", en_cours:"En cours", traite:"Traité"};
      const rows = Object.keys(types).map(k => { const s = al.filter(a => a.type === k); return [types[k]].concat(Object.keys(st).map(x => String(s.filter(a => a.status === x).length)), [String(s.length)]); });
      rep.tables.push({ title: "Alertes par type et statut", head: ["Type", "Nouveau", "En cours", "Traité", "Total"], rows });
    }
    if (secs.includes("suivi")) {
      const r = countBy(su, s => s.outcome);
      rep.tables.push({ title: "Suivi des victimes", head: ["Résultat", "Étapes", "%"], rows: r.map(x => [x[0], String(x[1]), pct(x[1], su.length)]) });
    }
    return rep;
  }

  function render(rep) {
    R = rep; charts.forEach(c => c.destroy()); charts = [];
    $("repEmpty").classList.add("d-none"); $("repOut").classList.remove("d-none");
    $("repTitle").textContent = rep.title; $("repMeta").textContent = "Période : " + rep.period + " · généré le " + rep.generated + " · " + rep.n + " dossier(s)";
    $("repKpis").innerHTML = rep.kpis.map(k => `<div class="col-6 col-md-4 col-xxl-3"><div class="card xm-stat"><div class="card-body py-2"><div class="small text-secondary">${esc(k[0])}</div><div class="kpi-value">${esc(k[1])}</div></div></div></div>`).join("");
    $("repCharts").innerHTML = rep.charts.map(c => `<div class="col-md-6"><div class="card h-100"><div class="card-header small fw-semibold">${esc(c.title)}</div><div class="card-body" style="height:260px"><canvas id="${c.id}"></canvas></div></div></div>`).join("");
    rep.charts.forEach(c => {
      const pal = XM_COLORS, multi = c.type === "doughnut";
      charts.push(new Chart($(c.id), { type: c.type, data: { labels: c.labels, datasets: [{ label: "Dossiers", data: c.data, backgroundColor: multi ? c.labels.map((_, i) => pal[i % pal.length]) : (c.type === "line" ? "rgba(4,51,195,.15)" : pal[0]), borderColor: c.type === "line" ? pal[0] : undefined, fill: c.type === "line", tension: .3 }] }, options: { responsive: true, maintainAspectRatio: false, animation: false, plugins: { legend: { display: multi } }, scales: multi ? {} : { y: { beginAtZero: true, ticks: { precision: 0 } } } } }));
    });
    $("repTables").innerHTML = rep.tables.map(t => `<div class="card mb-3"><div class="card-header small fw-semibold">${esc(t.title)}</div><div class="xm-table-wrap"><table class="table table-sm mb-0"><thead><tr>${t.head.map(h => `<th>${esc(h)}</th>`).join("")}</tr></thead><tbody>${t.rows.map(r => `<tr>${r.map((c, i) => `<td class="${i ? "text-end" : ""}">${esc(c)}</td>`).join("")}</tr>`).join("")}</tbody></table></div></div>`).join("");
  }

  async function logIt(fmt) {
    const fd = new FormData(); fd.append("_csrf", XM.csrf); fd.append("action", "log_report"); fd.append("title", R.title); fd.append("rtype", R.rtype); fd.append("period", R.period); fd.append("format", fmt); fd.append("cases", R.n); fd.append("dims", R.dims.join(","));
    try {
      const j = await (await fetch("rapports.php", { method: "POST", body: fd })).json();
      if (j.ok) { const tr = document.createElement("tr"); const h = j.row; tr.innerHTML = `<td class="small text-nowrap">${esc(h.at)}</td><td>${esc(h.title)}<div class="small text-secondary">${esc(j.label)}</div></td><td class="small">${esc(h.period)}</td><td><span class="badge bg-secondary">${esc(j.fmt)}</span></td><td>${h.cases}</td><td class="small">${esc(h.by)}</td>`; $("histBody").prepend(tr); $("histEmpty").classList.add("d-none"); }
    } catch (e) {}
  }
  function slug() { return (R.title || "rapport").toLowerCase().normalize("NFD").replace(/[̀-ͯ]/g, "").replace(/[^a-z0-9]+/g, "-").replace(/^-|-$/g, "") + "_" + R.from + "_" + R.to; }
  function download(blob, name) { const a = document.createElement("a"); a.href = URL.createObjectURL(blob); a.download = name; document.body.appendChild(a); a.click(); setTimeout(() => { URL.revokeObjectURL(a.href); a.remove(); }, 500); }
  function chartImg(id) { const c = $(id); if (!c) return null; const o = document.createElement("canvas"); o.width = c.width; o.height = c.height; const x = o.getContext("2d"); x.fillStyle = "#fff"; x.fillRect(0, 0, o.width, o.height); x.drawImage(c, 0, 0); return { url: o.toDataURL("image/png"), w: o.width, h: o.height }; }

  function exportXlsx() {
    const wb = XLSX.utils.book_new();
    const head = [[R.title], ["Période", R.period], ["Généré le", R.generated], ["Dossiers", R.n], []].concat(R.kpis.map(k => [k[0], k[1]]));
    XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(head), "Synthèse");
    const used = new Set(["Synthèse"]);
    R.tables.forEach((t, i) => { let n = t.title.replace(/[\\\/\?\*\[\]:]/g, " ").slice(0, 28); if (used.has(n)) n = n.slice(0, 25) + " " + (i + 1); used.add(n); const ws = XLSX.utils.aoa_to_sheet([t.head].concat(t.rows.map(r => r.map((c, j) => j && /^-?\d+$/.test(c) ? +c : c)))); ws["!cols"] = t.head.map((h, j) => ({ wch: j ? 14 : 30 })); XLSX.utils.book_append_sheet(wb, ws, n); });
    XLSX.writeFile(wb, slug() + ".xlsx");
  }
  function exportPdf() {
    const { jsPDF } = window.jspdf; const doc = new jsPDF({ unit: "mm", format: "a4" }); const W = 210;
    doc.setFillColor(4, 51, 195); doc.rect(0, 0, W, 26, "F"); doc.setTextColor(255); doc.setFontSize(18); doc.text(R.title, 14, 12);
    doc.setFontSize(10); doc.text("Xomiton — Espace safe d’apprentissage et d’appui holistique", 14, 19);
    doc.setTextColor(40); doc.text("Période : " + R.period + "   ·   Généré le " + R.generated + "   ·   " + R.n + " dossier(s)", 14, 34);
    let y = 40;
    if (R.kpis.length) { doc.autoTable({ startY: y, head: [["Chiffres clés", ""]], body: R.kpis.map(k => [k[0], String(k[1])]), theme: "striped", headStyles: { fillColor: [4, 51, 195] }, styles: { fontSize: 9 }, columnStyles: { 1: { halign: "right" } } }); y = doc.lastAutoTable.finalY + 6; }
    const imgs = R.charts.map(c => ({ c, i: chartImg(c.id) })).filter(x => x.i);
    for (let k = 0; k < imgs.length; k += 2) {
      const w = 88, hh = w * 0.62; if (y + hh + 8 > 285) { doc.addPage(); y = 14; }
      [0, 1].forEach(j => { const it = imgs[k + j]; if (!it) return; const x = 14 + j * 94; doc.setFontSize(9); doc.text(it.c.title, x, y); doc.addImage(it.i.url, "PNG", x, y + 2, w, hh); });
      y += hh + 10;
    }
    R.tables.forEach(t => { if (t.title === "Chiffres clés") return; doc.autoTable({ startY: y, head: [[{ content: t.title, colSpan: t.head.length, styles: { fillColor: [4, 51, 195] } }], t.head], body: t.rows, theme: "grid", headStyles: { fillColor: [90, 110, 190] }, styles: { fontSize: 8 }, margin: { left: 14, right: 14 }, didParseCell: d => { if (d.section === "body" && d.column.index > 0) d.cell.styles.halign = "right"; } }); y = doc.lastAutoTable.finalY + 6; });
    const n = doc.getNumberOfPages(); for (let p = 1; p <= n; p++) { doc.setPage(p); doc.setFontSize(8); doc.setTextColor(120); doc.text("Xomiton — confidentiel · page " + p + "/" + n, 14, 292); }
    doc.save(slug() + ".pdf");
  }
  function exportDoc() {
    let h = `<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word"><head><meta charset="utf-8"><title>${esc(R.title)}</title><style>body{font-family:Calibri,Arial;font-size:11pt}h1{color:#0433C3}table{border-collapse:collapse;margin:8pt 0;width:100%}th,td{border:1px solid #999;padding:3pt 5pt;font-size:10pt}th{background:#E6EBFA}</style></head><body><h1>${esc(R.title)}</h1><p>Période : ${esc(R.period)} — généré le ${esc(R.generated)} — ${R.n} dossier(s)</p>`;
    R.charts.forEach(c => { const i = chartImg(c.id); if (i) h += `<p><b>${esc(c.title)}</b><br><img src="${i.url}" width="480" height="${Math.round(480 * i.h / i.w)}"></p>`; });
    R.tables.forEach(t => { h += `<h3>${esc(t.title)}</h3><table><tr>${t.head.map(x => `<th>${esc(x)}</th>`).join("")}</tr>${t.rows.map(r => `<tr>${r.map(c => `<td>${esc(c)}</td>`).join("")}</tr>`).join("")}</table>`; });
    download(new Blob(["﻿" + h + "</body></html>"], { type: "application/msword" }), slug() + ".doc");
  }
  function exportCsv() {
    const q = c => '"' + String(c).replace(/"/g, '""') + '"';
    let s = [R.title, "Période;" + R.period, "Généré le;" + R.generated, ""].join("\n") + "\n";
    R.tables.forEach(t => { s += q(t.title) + "\n" + t.head.map(q).join(";") + "\n" + t.rows.map(r => r.map(q).join(";")).join("\n") + "\n\n"; });
    download(new Blob(["﻿" + s], { type: "text/csv;charset=utf-8" }), slug() + ".csv");
  }
  $("btnGen").addEventListener("click", () => { const r = build(); if (r) { render(r); logIt("apercu"); XM.toast("Rapport généré : " + r.n + " dossier(s).", "success"); } });
  document.querySelectorAll("[data-export]").forEach(b => b.addEventListener("click", () => {
    if (!R) return; const f = b.dataset.export;
    try { ({ xlsx: exportXlsx, pdf: exportPdf, doc: exportDoc, csv: exportCsv })[f](); logIt(f); } catch (e) { console.warn(e); XM.toast("Échec de l’export : " + e.message, "danger"); }
  }));
})();
JS;
$data = ['cases' => $cases, 'alertes' => $alertes, 'suivi' => $suivi, 'viol' => xa_viol_map(), 'statuts' => array_map(fn($v) => $v[0], xa_status_case()), 'ages' => x_age_ranges(), 'sexes' => x_sexes()];
$adminScripts = '<script src="../assets/vendor/xlsx/xlsx.full.min.js"></script><script src="../assets/vendor/jspdf/jspdf.umd.min.js"></script><script src="../assets/vendor/jspdf/jspdf.plugin.autotable.min.js"></script><script>' . str_replace('__DATA__', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG), $js) . '</script>';
include __DIR__ . '/../partials/admin-bottom.php';
