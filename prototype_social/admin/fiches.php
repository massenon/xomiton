<?php
require __DIR__ . '/../inc/bootstrap.php';
require_admin();
require_once __DIR__ . '/../inc/x_admin1.php';
$pageTitle = 'Fiches de référence VBG / VDH';
$adminPage = 'fiches';
$servicesList = ['ecoute' => 'Écoute / accueil', 'medical' => 'Prise en charge médicale', 'psy' => 'Soutien psychologique', 'juridique' => 'Appui juridique', 'hebergement' => 'Hébergement d’urgence', 'dignite' => 'Kit de dignité / matériel', 'securite' => 'Mise en sécurité'];
$cases = store_all('cases');
$caseByCode = []; foreach ($cases as $c) $caseByCode[$c['code']] = $c;
$ann = xa_annuaire_map();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) { xa_flash('danger', 'Session expirée.'); xa_redirect('fiches.php'); }
    $act = $_POST['action'] ?? '';
    if ($act === 'delete') { $id = (int)$_POST['id']; store_delete('fiches', $id); x_log('Fiche supprimée', '#' . $id); xa_flash('success', 'Fiche supprimée.'); xa_redirect('fiches.php'); }
    $code = trim($_POST['case_code'] ?? '');
    $err = [];
    if (!isset($caseByCode[$code])) $err[] = 'Dossier introuvable.';
    $fdate = trim($_POST['f_date'] ?? '');
    if (!xa_date_ok($fdate) || $fdate > date('Y-m-d')) $err[] = 'Date des faits invalide.';
    $desc = trim($_POST['f_description'] ?? '');
    if (mb_strlen($desc) < 20) $err[] = 'La description des faits doit contenir au moins 20 caractères.';
    $to = (int)($_POST['o_annuaire'] ?? 0);
    if ($to && !isset($ann[$to])) $err[] = 'Personne de l’annuaire inconnue.';
    if (empty($_POST['c_given'])) $err[] = 'Le consentement de la personne est obligatoire.';
    if ($err) { xa_flash('danger', implode(' ', $err)); xa_redirect('fiches.php', 'case=' . urlencode($code) . '#nouvelle'); }
    $c = $caseByCode[$code];
    $srv = array_values(array_intersect(array_keys($servicesList), (array)($_POST['services'] ?? [])));
    $n = count(store_all('fiches')) + 1;
    $row = store_append('fiches', [
        'ref' => sprintf('FR-%s-%04d', date('Y'), $n), 'case_code' => $code, 'kind' => $c['kind'], 'created' => date('Y-m-d H:i'), 'by' => x_user()['name'],
        'ident' => ['pseudo' => mb_substr(trim($_POST['i_pseudo'] ?? ''), 0, 60), 'sexe' => $c['sexe'], 'age' => $c['age'], 'population' => $c['population'], 'zone' => $c['zone'], 'commune' => $c['commune']],
        'faits' => ['date' => $fdate, 'lieu' => mb_substr(trim($_POST['f_lieu'] ?? ''), 0, 120), 'type' => $c['type'], 'auteur' => mb_substr(trim($_POST['f_auteur'] ?? ''), 0, 120), 'description' => mb_substr($desc, 0, 3000)],
        'services' => $srv, 'services_note' => mb_substr(trim($_POST['s_note'] ?? ''), 0, 1000),
        'orientation' => ['annuaire_id' => $to, 'label' => $to ? xa_routed_label($to, $ann) : '', 'date' => trim($_POST['o_date'] ?? ''), 'motif' => mb_substr(trim($_POST['o_motif'] ?? ''), 0, 500), 'urgence' => $_POST['o_urgence'] ?? 'normale'],
        'consent' => ['given' => true, 'mode' => in_array($_POST['c_mode'] ?? '', ['écrit', 'oral'], true) ? $_POST['c_mode'] : 'oral', 'partage' => !empty($_POST['c_partage']), 'nom' => mb_substr(trim($_POST['c_nom'] ?? ''), 0, 80)],
    ]);
    x_log('Fiche de référence créée', $row['ref'] . ' / ' . $code);
    xa_flash('success', 'Fiche ' . $row['ref'] . ' enregistrée.');
    xa_redirect('fiches.php', 'view=' . $row['id']);
}

$fiches = array_reverse(store_all('fiches'));
$view = ($vid = (int)xa_get('view', 0)) ? store_find('fiches', $vid) : null;
$selCode = xa_get('case');
$sel = $caseByCode[$selCode] ?? null;
$adminActions = '<a class="btn btn-primary btn-sm" href="#nouvelle"><i class="bi bi-plus-lg me-1"></i>Nouvelle fiche</a>';
include __DIR__ . '/../partials/admin-top.php';
?>
<?php if ($view):
	$vc = $view['ident']; ?>
<div class="card mb-4" id="printSheet">
	<div class="card-header d-flex justify-content-between align-items-center no-print">
		<h2 class="h6 mb-0">Fiche <?= e($view['ref']) ?></h2>
		<div class="d-flex gap-2"><button class="btn btn-sm btn-outline-primary" onclick="window.print()"><i class="bi bi-printer me-1"></i>Imprimer (A4)</button><button class="btn btn-sm btn-primary" id="btnPdf"><i class="bi bi-filetype-pdf me-1"></i>Exporter en PDF</button><a class="btn btn-sm btn-light" href="fiches.php">Fermer</a></div>
	</div>
	<div class="card-body">
		<div class="xm-print-only mb-3 border-bottom pb-2"><img src="../assets/images/logo.svg" height="34" alt="Xomiton"><div class="small">Xomiton — Espace safe d’apprentissage et d’appui holistique · Fiche confidentielle</div></div>
		<h3 class="h5"><?= e($view['ref']) ?> — Fiche de référence <?= e($view['kind']) ?> · dossier <?= e($view['case_code']) ?></h3>
		<p class="small text-secondary">Établie le <?= e($view['created']) ?> par <?= e($view['by']) ?></p>
		<div class="row g-3">
			<div class="col-md-6"><h4 class="h6 text-primary">1. Identification pseudonymisée</h4><ul class="list-unstyled small mb-0">
				<li>Pseudo : <b><?= e($vc['pseudo'] ?: '—') ?></b></li><li>Sexe : <?= e($vc['sexe']) ?> · Âge : <?= e($vc['age']) ?> ans</li><li>Population : <?= e($vc['population']) ?></li><li>Localisation : <?= e($vc['zone']) ?> / <?= e($vc['commune']) ?></li></ul></div>
			<div class="col-md-6"><h4 class="h6 text-primary">2. Faits</h4><ul class="list-unstyled small mb-0">
				<li>Date : <?= e($view['faits']['date']) ?> · Lieu : <?= e($view['faits']['lieu'] ?: '—') ?></li><li>Type : <?= e(xa_viol_label($view['faits']['type'])) ?></li><li>Auteur présumé : <?= e($view['faits']['auteur'] ?: '—') ?></li><li class="mt-1"><?= nl2br(e($view['faits']['description'])) ?></li></ul></div>
			<div class="col-md-6"><h4 class="h6 text-primary">3. Services reçus</h4><ul class="small mb-1"><?php foreach ($view['services'] as $s) echo '<li>' . e($servicesList[$s] ?? $s) . '</li>'; if (!$view['services']) echo '<li class="list-unstyled">Aucun</li>'; ?></ul><div class="small"><?= e($view['services_note']) ?></div></div>
			<div class="col-md-6"><h4 class="h6 text-primary">4. Orientation (annuaire)</h4><ul class="list-unstyled small mb-0"><li>Vers : <b><?= e($view['orientation']['label'] ?: '—') ?></b></li><li>Date : <?= e($view['orientation']['date'] ?: '—') ?> · Urgence : <?= e($view['orientation']['urgence']) ?></li><li>Motif : <?= e($view['orientation']['motif'] ?: '—') ?></li></ul></div>
			<div class="col-12"><h4 class="h6 text-primary">5. Consentement</h4><p class="small mb-0">Consentement <?= e($view['consent']['mode']) ?> recueilli. Partage d’informations avec la personne référente : <b><?= $view['consent']['partage'] ? 'autorisé' : 'non autorisé' ?></b>. Signature / initiales : <?= e($view['consent']['nom'] ?: '—') ?></p></div>
		</div>
	</div>
</div>
<?php endif; ?>

<div class="row g-4">
	<div class="col-xl-7" id="nouvelle">
		<div class="card"><div class="card-header"><h2 class="h6 mb-0">Nouvelle fiche de référence</h2></div>
		<div class="card-body">
			<form method="post" data-xm-validate novalidate>
				<?= csrf_field() ?>
				<div class="row g-3">
					<div class="col-md-6"><label class="form-label" for="fCase">Dossier (code)</label>
						<select class="form-select" id="fCase" name="case_code" required><option value="">— Choisir —</option><?php foreach ($cases as $c): if ($c['status'] === 'archive') continue; ?><option value="<?= e($c['code']) ?>" <?= $selCode === $c['code'] ? 'selected' : '' ?>><?= e($c['code']) ?> · <?= e($c['kind']) ?> · <?= e($c['commune']) ?></option><?php endforeach; ?></select></div>
					<div class="col-md-6"><label class="form-label" for="iPseudo">Pseudonyme</label><input class="form-control" id="iPseudo" name="i_pseudo" maxlength="60" placeholder="Prénom d’emprunt"></div>
					<div class="col-12"><div class="alert alert-light border small mb-0" id="caseInfo">Sélectionnez un dossier : les données d’identification pseudonymisées sont reprises automatiquement.</div></div>
					<div class="col-12"><h3 class="h6 text-primary mb-0">Faits</h3></div>
					<div class="col-md-4"><label class="form-label" for="fDate">Date des faits</label><input type="date" class="form-control" id="fDate" name="f_date" required data-rule="date_not_future" max="<?= date('Y-m-d') ?>"></div>
					<div class="col-md-4"><label class="form-label" for="fLieu">Lieu</label><input class="form-control" id="fLieu" name="f_lieu" maxlength="120"></div>
					<div class="col-md-4"><label class="form-label" for="fAut">Auteur présumé (lien)</label><input class="form-control" id="fAut" name="f_auteur" maxlength="120" placeholder="ex. membre de la famille"></div>
					<div class="col-12"><label class="form-label" for="fDesc">Description des faits</label><textarea class="form-control" id="fDesc" name="f_description" rows="3" required data-rule="min_len_20"></textarea></div>
					<div class="col-12"><h3 class="h6 text-primary mb-1">Services reçus</h3>
						<div class="row g-1"><?php foreach ($servicesList as $k => $l): ?><div class="col-sm-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="services[]" value="<?= $k ?>" id="s_<?= $k ?>"><label class="form-check-label" for="s_<?= $k ?>"><?= e($l) ?></label></div></div><?php endforeach; ?></div>
						<input class="form-control form-control-sm mt-2" name="s_note" maxlength="1000" placeholder="Précisions sur les services (facultatif)"></div>
					<div class="col-12"><h3 class="h6 text-primary mb-0">Orientation vers l’annuaire</h3></div>
					<div class="col-md-6"><label class="form-label" for="oAnn">Personne / structure</label><select class="form-select" id="oAnn" name="o_annuaire"><option value="0">— Aucune —</option><?php foreach ($ann as $a): if (empty($a['active'])) continue; ?><option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?> — <?= e($a['role']) ?> (<?= e($a['zone']) ?>)</option><?php endforeach; ?></select></div>
					<div class="col-md-3"><label class="form-label" for="oDate">Date</label><input type="date" class="form-control" id="oDate" name="o_date" value="<?= date('Y-m-d') ?>"></div>
					<div class="col-md-3"><label class="form-label" for="oUrg">Urgence</label><select class="form-select" id="oUrg" name="o_urgence"><option>normale</option><option>haute</option><option>critique</option></select></div>
					<div class="col-12"><input class="form-control" name="o_motif" maxlength="500" placeholder="Motif de l’orientation"></div>
					<div class="col-12"><h3 class="h6 text-primary mb-1">Consentement</h3>
						<div class="form-check"><input class="form-check-input" type="checkbox" name="c_given" value="1" id="cGiven" required><label class="form-check-label" for="cGiven">La personne a donné son consentement éclairé à l’enregistrement de cette fiche.</label></div>
						<div class="form-check"><input class="form-check-input" type="checkbox" name="c_partage" value="1" id="cPart"><label class="form-check-label" for="cPart">Elle autorise le partage des informations avec la personne référente.</label></div>
						<div class="row g-2 mt-1"><div class="col-sm-6"><select class="form-select form-select-sm" name="c_mode" aria-label="Mode de consentement"><option value="oral">Consentement oral</option><option value="écrit">Consentement écrit</option></select></div><div class="col-sm-6"><input class="form-control form-control-sm" name="c_nom" maxlength="80" placeholder="Initiales / signature"></div></div></div>
				</div>
				<div class="mt-3 text-end"><button class="btn btn-primary"><i class="bi bi-save me-1"></i>Enregistrer la fiche</button></div>
			</form>
		</div></div>
	</div>
	<div class="col-xl-5">
		<div class="card"><div class="card-header"><h2 class="h6 mb-0"><?= count($fiches) ?> fiche(s) enregistrée(s)</h2></div>
			<div class="list-group list-group-flush">
				<?php if (!$fiches): ?><div class="list-group-item text-secondary">Aucune fiche pour le moment.</div><?php endif; ?>
				<?php foreach ($fiches as $f): ?>
				<div class="list-group-item d-flex justify-content-between align-items-center gap-2">
					<div><div class="fw-semibold"><?= e($f['ref']) ?> <span class="badge bg-primary"><?= e($f['kind']) ?></span></div><div class="small text-secondary"><?= e($f['case_code']) ?> · <?= e($f['created']) ?> · <?= e($f['orientation']['label'] ?: 'sans orientation') ?></div></div>
					<div class="text-nowrap"><a class="btn btn-sm btn-light" href="?view=<?= (int)$f['id'] ?>" title="Voir / imprimer"><i class="bi bi-printer"></i></a>
					<form method="post" class="d-inline" onsubmit="return confirm('Supprimer cette fiche ?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$f['id'] ?>"><button class="btn btn-sm btn-light text-danger" title="Supprimer"><i class="bi bi-trash"></i></button></form></div>
				</div>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
</div>

<?php
$adminScripts = '<script src="../assets/vendor/jspdf/jspdf.umd.min.js"></script><script src="../assets/vendor/jspdf/jspdf.plugin.autotable.min.js"></script><script>
(function(){
  const cases = ' . json_encode($caseByCode, JSON_UNESCAPED_UNICODE) . ', viol = ' . json_encode(xa_viol_map(), JSON_UNESCAPED_UNICODE) . ';
  const sel = document.getElementById("fCase"), info = document.getElementById("caseInfo");
  function upd(){ const c = cases[sel.value]; if (!c) { info.textContent = "Sélectionnez un dossier : les données d’identification pseudonymisées sont reprises automatiquement."; return; }
    info.innerHTML = "<b>" + c.code + "</b> · " + c.sexe + ", " + c.age + " ans · " + c.population + " · " + c.zone + " / " + c.commune + " · " + (viol[c.type]||c.type);
    const d = document.getElementById("fDate"); if (!d.value) d.value = c.date; }
  sel.addEventListener("change", upd); upd();
  const FICHE = ' . json_encode($view, JSON_UNESCAPED_UNICODE) . ', SRV = ' . json_encode($servicesList, JSON_UNESCAPED_UNICODE) . ';
  const b = document.getElementById("btnPdf");
  if (b && FICHE) b.addEventListener("click", () => {
    const { jsPDF } = window.jspdf; const doc = new jsPDF({ unit: "mm", format: "a4" });
    doc.setFontSize(16); doc.setTextColor(4,51,195); doc.text("Xomiton — Fiche de référence " + FICHE.kind, 14, 18);
    doc.setFontSize(10); doc.setTextColor(60); doc.text(FICHE.ref + " · dossier " + FICHE.case_code + " · établie le " + FICHE.created + " par " + FICHE.by, 14, 25);
    doc.text("Document confidentiel — usage strictement professionnel", 14, 30);
    const i = FICHE.ident, f = FICHE.faits, o = FICHE.orientation, c = FICHE.consent;
    const sec = (title, rows, y) => { doc.autoTable({ startY: y, head: [[{ content: title, colSpan: 2 }]], body: rows, theme: "grid", headStyles: { fillColor: [4,51,195] }, columnStyles: { 0: { cellWidth: 45, fontStyle: "bold" } }, styles: { fontSize: 9 } }); return doc.lastAutoTable.finalY + 6; };
    let y = 36;
    y = sec("1. Identification pseudonymisée", [["Pseudo", i.pseudo||"—"],["Sexe / âge", i.sexe + " · " + i.age + " ans"],["Population", i.population],["Localisation", i.zone + " / " + i.commune]], y);
    y = sec("2. Faits", [["Date / lieu", f.date + " · " + (f.lieu||"—")],["Type", viol[f.type]||f.type],["Auteur présumé", f.auteur||"—"],["Description", f.description]], y);
    y = sec("3. Services reçus", [["Services", (FICHE.services.map(s => SRV[s]||s).join(", ")||"Aucun")],["Précisions", FICHE.services_note||"—"]], y);
    y = sec("4. Orientation (annuaire)", [["Vers", o.label||"—"],["Date / urgence", (o.date||"—") + " · " + o.urgence],["Motif", o.motif||"—"]], y);
    y = sec("5. Consentement", [["Consentement", c.mode + " recueilli"],["Partage d’informations", c.partage ? "Autorisé" : "Non autorisé"],["Signature / initiales", c.nom||"—"]], y);
    doc.save(FICHE.ref + ".pdf");
  });
})();
</script>';
include __DIR__ . '/../partials/admin-bottom.php';
