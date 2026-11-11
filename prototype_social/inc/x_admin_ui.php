<?php
/** Composants d'interface de l'administration : pagination serveur « Précédent / 1 … 11 / Suivant ». */

function xa_pages($total, $per, $page) {
    $n = max(1, (int)ceil($total / $per));
    return [$n, max(1, min($n, $page))];
}
/** Fenêtre de pagination : 1 … 4 5 6 … 11 */
function xa_page_window($n, $page) {
    $set = array_unique(array_filter([1, 2, $page - 1, $page, $page + 1, $n - 1, $n], fn($i) => $i >= 1 && $i <= $n));
    sort($set);
    $out = []; $prev = 0;
    foreach ($set as $i) { if ($i - $prev > 1) $out[] = '…'; $out[] = $i; $prev = $i; }
    return $out;
}
/** Boutons Précédent / 1 … 11 / Suivant (liens GET ?p=). */
function xa_pager($total, $per, $page, $params = []) {
    [$n, $page] = xa_pages($total, $per, $page);
    $link = fn($i) => '?' . e(http_build_query(array_merge($params, ['p' => $i])));
    $h = '<nav aria-label="Pagination du tableau"><ul class="pagination pagination-sm mb-0 flex-wrap xm-dt-pager">';
    $h .= '<li class="page-item ' . ($page <= 1 ? 'disabled' : '') . '"><a class="page-link" href="' . $link($page - 1) . '"' . ($page <= 1 ? ' tabindex="-1" aria-disabled="true"' : '') . '>Précédent</a></li>';
    foreach (xa_page_window($n, $page) as $i) {
        if ($i === '…') { $h .= '<li class="page-item disabled"><span class="page-link">…</span></li>'; continue; }
        $h .= '<li class="page-item ' . ($i === $page ? 'active' : '') . '"><a class="page-link" href="' . $link($i) . '"' . ($i === $page ? ' aria-current="page"' : '') . '>' . $i . '</a></li>';
    }
    $h .= '<li class="page-item ' . ($page >= $n ? 'disabled' : '') . '"><a class="page-link" href="' . $link($page + 1) . '"' . ($page >= $n ? ' tabindex="-1" aria-disabled="true"' : '') . '>Suivant</a></li>';
    return $h . '</ul></nav>';
}
/** Pied de carte complet : « Affichage de 1 à 10 sur 57 entrées » + pagination. */
function xa_pagebar($total, $per, $page, $params = []) {
    [$n, $page] = xa_pages($total, $per, $page);
    $from = $total ? ($page - 1) * $per + 1 : 0; $to = min($total, $page * $per);
    return '<div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2 xm-dt-bar"><span class="small text-secondary">Affichage de ' . $from . ' à ' . $to . ' sur ' . $total . ' entrée' . ($total > 1 ? 's' : '') . '</span>' . xa_pager($total, $per, $page, $params) . '</div>';
}


/**
 * Notifications de l'administration : éléments à traiter (non lus par défaut) et derniers éléments traités (lus).
 * Chaque entrée : [id, icône, couleur, texte, lien, horodatage, lu].
 */
function xa_notifications() {
    $N = [];
    $add = function ($id, $icon, $color, $text, $link, $ts, $read) use (&$N) { $N[] = ['id' => $id, 'icon' => $icon, 'color' => $color, 'text' => $text, 'link' => $link, 'ts' => (int)$ts, 'read' => $read]; };
    $al = store_all('alertes'); usort($al, fn($a, $b) => $b['created'] <=> $a['created']);
    $nNew = 0; $nDone = 0;
    foreach ($al as $a) {
        $t = ($a['type'] ?? '') === 'denonciation' ? 'Nouveau signalement' : (($a['type'] ?? '') === 'sos' ? 'Alerte SOS' : 'Nouvelle demande');
        if (($a['status'] ?? '') === 'nouveau' && $nNew < 5) { $nNew++; $add('al' . $a['id'], 'bi-bell-fill', 'danger', $t . ' · ' . ($a['category'] ?? ''), 'alertes.php', $a['created'], false); }
        elseif (($a['status'] ?? '') === 'traite' && $nDone < 3) { $nDone++; $add('al' . $a['id'], 'bi-check2-circle', 'success', 'Alerte traitée · ' . ($a['category'] ?? ''), 'alertes.php', $a['created'], true); }
    }
    foreach (array_slice(array_filter(store_all('users'), fn($u) => ($u['status'] ?? '') === 'en_attente'), 0, 4) as $u)
        $add('us' . $u['id'], 'bi-person-plus-fill', 'primary', 'Inscription à valider · ' . $u['name'], 'utilisateurs.php', strtotime($u['joined'] ?? 'now'), false);
    foreach (array_slice(array_filter(store_all('moderation_reports'), fn($m) => ($m['status'] ?? '') === 'en_attente'), 0, 3) as $m)
        $add('mo' . $m['id'], 'bi-shield-exclamation', 'warning', 'Signalement à modérer · ' . ($m['reason'] ?? ''), 'moderation.php', $m['created'], false);
    foreach (array_slice(store_all('sync_conflicts'), 0, 2) as $c)
        $add('sc' . $c['id'], 'bi-arrow-left-right', 'info', 'Conflit de synchronisation · ' . ($c['code'] ?? ''), 'synchronisation.php', strtotime($c['at'] ?? 'now'), false);
    usort($N, fn($a, $b) => $b['ts'] <=> $a['ts']);
    return $N;
}
