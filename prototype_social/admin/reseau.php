<?php
require __DIR__ . '/../inc/bootstrap.php'; require_admin(); require_once __DIR__ . '/../inc/x_admin_sys.php';
$pageTitle = 'Réseau social'; $adminPage = 'reseau';
$tabs = ['groups' => ['Groupes', 'bi-people', 'group-details.php'], 'pages' => ['Pages', 'bi-flag', ''], 'events' => ['Événements', 'bi-calendar-event', 'events.php'], 'forum_topics' => ['Forums', 'bi-chat-square-text', ''], 'posts' => ['Publications', 'bi-card-text', 'fil.php']];
$tab = isset($tabs[$_GET['t'] ?? '']) ? $_GET['t'] : 'groups';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    xa_guard();
    $do = $_POST['do'] ?? ''; $s = xa_post('store'); $id = xa_post('id');
    if (!isset($tabs[$s]) || $id === '') xa_back('danger', 'Requête invalide.');
    $row = store_find($s, $id);
    if (!$row) xa_back('danger', 'Élément introuvable.', 't=' . $s);
    $label = $row['name'] ?? $row['title'] ?? mb_substr($row['text'] ?? '#' . $id, 0, 40);
    if ($do === 'approve') { $v = empty($row['approved']) ? true : false; store_update($s, $id, ['approved' => $v]); x_log('Réseau', ($v ? 'Approbation : ' : 'Retrait d’approbation : ') . $label); xa_back('success', $v ? 'Élément approuvé.' : 'Approbation retirée.', 't=' . $s); }
    if ($do === 'feature') { $v = empty($row['featured']); store_update($s, $id, ['featured' => $v]); x_log('Réseau', ($v ? 'Mise en avant : ' : 'Fin de mise en avant : ') . $label); xa_back('success', $v ? 'Élément mis en avant.' : 'Mise en avant retirée.', 't=' . $s); }
    if ($do === 'delete') { store_delete($s, $id); x_log('Réseau', 'Suppression (' . $s . ') : ' . $label); xa_back('success', 'Élément supprimé.', 't=' . $s); }
    xa_back('danger', 'Action inconnue.');
}

$data = []; foreach ($tabs as $k => $_) $data[$k] = store_all($k);
$baseForums = x_forums();
$data['posts'] = array_reverse($data['posts']);
$authors = array_column(store_all('users'), null, 'id');

function rs_actions($store, $r) {
    $h = '<form method="post" class="d-inline-flex gap-1">' . csrf_field() . '<input type="hidden" name="store" value="' . e($store) . '"><input type="hidden" name="id" value="' . (int)$r['id'] . '">';
    $h .= '<button name="do" value="approve" class="btn btn-sm ' . (!empty($r['approved']) ? 'btn-success' : 'btn-light') . '" title="Approuver"><i class="bi bi-check2-circle"></i></button>';
    $h .= '<button name="do" value="feature" class="btn btn-sm ' . (!empty($r['featured']) ? 'btn-warning' : 'btn-light') . '" title="Mettre en avant"><i class="bi bi-star' . (!empty($r['featured']) ? '-fill' : '') . '"></i></button>';
    $h .= '<button name="do" value="delete" class="btn btn-sm btn-light text-danger" title="Supprimer" onclick="return confirm(\'Supprimer définitivement ?\')"><i class="bi bi-trash"></i></button></form>';
    return $h;
}
function rs_flags($r) { return (!empty($r['approved']) ? '<span class="badge bg-success me-1">Approuvé</span>' : '') . (!empty($r['featured']) ? '<span class="badge bg-warning text-dark">En avant</span>' : ''); }

include __DIR__ . '/../partials/admin-top.php';
?>
<div class="row g-3 mb-4">
	<?= xa_kpi('Groupes', count($data['groups']), 'bi-people') ?>
	<?= xa_kpi('Pages', count($data['pages']), 'bi-flag', 'violet') ?>
	<?= xa_kpi('Événements', count($data['events']), 'bi-calendar-event', 'success') ?>
	<?= xa_kpi('Publications', count($data['posts']), 'bi-card-text', 'warning') ?>
</div>
<ul class="nav nav-tabs mb-4 flex-nowrap overflow-auto">
	<?php foreach ($tabs as $k => $t): $n = $k === 'forum_topics' ? count($data[$k]) + count($baseForums) : count($data[$k]); ?>
	<li class="nav-item"><a class="nav-link text-nowrap <?= $tab === $k ? 'active' : '' ?>" href="?t=<?= $k ?>"><i class="bi <?= $t[1] ?> me-1"></i><?= e($t[0]) ?> <span class="badge bg-secondary-subtle text-secondary-emphasis"><?= $n ?></span></a></li>
	<?php endforeach; ?>
</ul>

<div class="card"><div class="xm-table-wrap"><table class="table table-hover align-middle mb-0">
<?php if ($tab === 'groups'): ?>
	<thead><tr><th>Groupe</th><th>Confidentialité</th><th>Zone</th><th class="text-end">Membres</th><th>État</th><th class="text-end">Actions</th></tr></thead><tbody>
	<?php foreach ($data['groups'] as $r): ?><tr><td><div class="d-flex gap-2 align-items-center"><img src="<?= img($r['image'] ?? 'hero-09.jpg') ?>" width="48" height="36" class="rounded object-fit-cover" alt=""><div><div class="fw-semibold"><?= e($r['name']) ?></div><div class="small text-secondary"><?= e($r['desc'] ?? '') ?></div></div></div></td><td><span class="badge bg-<?= ($r['privacy'] ?? '') === 'Privé' ? 'secondary' : 'info' ?>"><?= e($r['privacy'] ?? 'Public') ?></span></td><td><?= e($r['zone'] ?? '') ?></td><td class="text-end"><?= (int)($r['members'] ?? 0) ?></td><td><?= rs_flags($r) ?></td><td class="text-end"><?= rs_actions('groups', $r) ?></td></tr><?php endforeach; ?>
<?php elseif ($tab === 'pages'): ?>
	<thead><tr><th>Page</th><th>Catégorie</th><th class="text-end">Abonné·es</th><th>État</th><th class="text-end">Actions</th></tr></thead><tbody>
	<?php foreach ($data['pages'] as $r): ?><tr><td><div class="d-flex gap-2 align-items-center"><img src="<?= img($r['image'] ?? 'hero-09.jpg') ?>" width="48" height="36" class="rounded object-fit-cover" alt=""><div><div class="fw-semibold"><?= e($r['name']) ?></div><div class="small text-secondary"><?= e($r['desc'] ?? '') ?></div></div></div></td><td><?= e($r['category'] ?? '') ?></td><td class="text-end"><?= number_format((int)($r['followers'] ?? 0), 0, ',', ' ') ?></td><td><?= rs_flags($r) ?></td><td class="text-end"><?= rs_actions('pages', $r) ?></td></tr><?php endforeach; ?>
<?php elseif ($tab === 'events'): ?>
	<thead><tr><th>Événement</th><th>Date</th><th>Lieu</th><th class="text-end">Participant·es</th><th>État</th><th class="text-end">Actions</th></tr></thead><tbody>
	<?php foreach ($data['events'] as $r): ?><tr><td><div class="fw-semibold"><?= e($r['title']) ?></div><div class="small text-secondary"><?= e($r['cat'] ?? '') ?> · <?= e($r['organizer'] ?? '') ?></div></td><td class="text-nowrap"><?= e(xa_fr_date($r['date'] ?? '')) ?> <?= e($r['time'] ?? '') ?></td><td><?= e($r['place'] ?? '') ?></td><td class="text-end"><?= (int)($r['going'] ?? 0) ?></td><td><?= rs_flags($r) ?></td><td class="text-end"><?= rs_actions('events', $r) ?></td></tr><?php endforeach; ?>
<?php elseif ($tab === 'forum_topics'): ?>
	<thead><tr><th>Forum / sujet</th><th>Description</th><th class="text-end">Sujets</th><th class="text-end">Messages</th><th>État</th><th class="text-end">Actions</th></tr></thead><tbody>
	<?php foreach ($baseForums as $f): ?><tr><td><i class="bi <?= $f[3] ?> me-2 text-primary"></i><span class="fw-semibold"><?= e($f[1]) ?></span> <span class="badge bg-secondary-subtle text-secondary-emphasis">Forum de base</span></td><td class="small text-secondary"><?= e($f[2]) ?></td><td class="text-end"><?= $f[4] ?></td><td class="text-end"><?= $f[5] ?></td><td></td><td class="text-end text-secondary small">Catégorie fixe</td></tr><?php endforeach; ?>
	<?php foreach ($data['forum_topics'] as $r): ?><tr><td class="fw-semibold"><?= e($r['title'] ?? $r['name'] ?? 'Sujet #' . $r['id']) ?></td><td class="small text-secondary"><?= e(mb_substr($r['text'] ?? $r['desc'] ?? '', 0, 100)) ?></td><td class="text-end">—</td><td class="text-end"><?= (int)($r['replies'] ?? 0) ?></td><td><?= rs_flags($r) ?></td><td class="text-end"><?= rs_actions('forum_topics', $r) ?></td></tr><?php endforeach; ?>
	<?php if (!$data['forum_topics']): ?><tr><td colspan="6" class="text-center text-secondary small py-3">Aucun sujet créé par les membres pour le moment.</td></tr><?php endif; ?>
<?php else: ?>
	<thead><tr><th>Publication</th><th>Auteur·e</th><th>Date</th><th class="text-end">J’aime / com.</th><th>État</th><th class="text-end">Actions</th></tr></thead><tbody>
	<?php foreach ($data['posts'] as $r): $au = $authors[$r['user'] ?? 0] ?? null; ?><tr><td style="max-width:360px"><div class="d-flex gap-2"><?php if (!empty($r['image'])): ?><img src="<?= img($r['image']) ?>" width="48" height="36" class="rounded object-fit-cover" alt=""><?php endif; ?><div><div><?= e(mb_substr($r['text'] ?? '', 0, 120)) ?></div><?php if (!empty($r['tag'])): ?><span class="badge bg-primary-subtle text-primary-emphasis"><?= e($r['tag']) ?></span><?php endif; ?></div></div></td><td class="small"><?= e($au['name'] ?? 'Inconnu·e') ?></td><td class="small text-nowrap"><?= e(xa_fr_date($r['ts'] ?? 0)) ?></td><td class="text-end"><?= (int)($r['likes'] ?? 0) ?> / <?= (int)($r['comments'] ?? 0) ?></td><td><?= rs_flags($r) ?></td><td class="text-end"><?= rs_actions('posts', $r) ?></td></tr><?php endforeach; ?>
<?php endif; ?>
	</tbody></table></div></div>
<?php include __DIR__ . '/../partials/admin-bottom.php';
