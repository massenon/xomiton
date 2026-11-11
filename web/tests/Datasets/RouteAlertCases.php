<?php

declare(strict_types=1);

/*
 * Table de vérité du routage RG-04 (spécification P5-01 §3.1.5) : R1 à R13 = parité stricte avec `route_alert()`
 * (prototype_social/api.php l.12-25) ; R10, R14, R15 = point focal national d'abord (RG-04) ; R17, R18 = garde (P1-17) ;
 * R21, R22 = premier par identifiant hors garde (le test Feature nomme les fiches dans l'ordre inverse des identifiants).
 * Consommée par le test unitaire (AlertRouter, sans base) et le test Feature (RouteAlert, annuaire en base).
 * Annuaire : [id, rôle, département|null, options] ; options : inactive, deleted, national, on_duty (garde focal).
 */

$juriste = 'juriste';
$psy = 'psychologue';
$gyn = 'gynecologue';
$pf = 'point_focal';

dataset('route_alert_cases', [
    'R1 rôle et département' => [['soutien_juridique'], 'Littoral', [[2, $juriste, 'Littoral'], [4, $pf, 'Ouémé']], 2, 'role_zone'],
    'R2 ordre des besoins (psy d’abord)' => [['soutien_psychologique', 'soutien_juridique'], 'Littoral', [[1, $psy, 'Littoral'], [2, $juriste, 'Littoral']], 1, 'role_zone'],
    'R3 ordre des besoins (juriste d’abord)' => [['soutien_juridique', 'soutien_psychologique'], 'Littoral', [[1, $psy, 'Littoral'], [2, $juriste, 'Littoral']], 2, 'role_zone'],
    'R4 besoin sans rôle sauté' => [['hebergement', 'soutien_juridique'], 'Littoral', [[2, $juriste, 'Littoral'], [7, $pf, 'Littoral']], 2, 'role_zone'],
    'R5 rôle inactif → point focal du département' => [['soutien_juridique'], 'Littoral', [[2, $juriste, 'Littoral', ['inactive']], [7, $pf, 'Littoral'], [9, $juriste, 'Ouémé']], 7, 'focal_zone'],
    'R6 premier identifiant' => [['soutien_juridique'], 'Littoral', [[2, $juriste, 'Littoral'], [8, $juriste, 'Littoral']], 2, 'role_zone'],
    'R7 besoin sans rôle → point focal' => [['hebergement'], 'Littoral', [[2, $juriste, 'Littoral'], [7, $pf, 'Littoral']], 7, 'focal_zone'],
    'R8 aucun besoin → point focal' => [[], 'Littoral', [[2, $juriste, 'Littoral'], [7, $pf, 'Littoral']], 7, 'focal_zone'],
    'R9 SOS → point focal du département' => [['securite'], 'Borgou', [[5, $pf, 'Borgou'], [10, $pf, null, ['national']]], 5, 'focal_zone'],
    'R10 point focal national' => [['soutien_medical'], 'Zou', [[3, $gyn, 'Littoral'], [4, $pf, 'Ouémé'], [10, $pf, null, ['national']]], 10, 'focal_national'],
    'R11 sans national → premier point focal' => [['soutien_medical'], 'Zou', [[3, $gyn, 'Littoral'], [4, $pf, 'Ouémé'], [5, $pf, 'Borgou']], 4, 'focal_national'],
    'R12 premier contact actif' => [['soutien_medical'], 'Zou', [[1, $psy, 'Littoral'], [2, $juriste, 'Littoral']], 1, 'first_active'],
    'R13 annuaire vide ou inactif' => [['soutien_juridique'], 'Littoral', [[2, $juriste, 'Littoral', ['inactive']]], null, null],
    'R14 SOS sans département → national' => [['securite'], null, [[2, $juriste, 'Littoral'], [7, $pf, 'Littoral'], [10, $pf, null, ['national']]], 10, 'focal_national'],
    'R15 sans département ni national' => [['soutien_juridique'], null, [[2, $juriste, 'Littoral'], [4, $pf, 'Ouémé']], 4, 'focal_national'],
    'R16 contact supprimé ignoré' => [['soutien_juridique'], 'Littoral', [[2, $juriste, 'Littoral', ['deleted']], [7, $pf, 'Littoral']], 7, 'focal_zone'],
    'R17 le rôle prime sur la garde' => [['soutien_juridique'], 'Littoral', [[2, $juriste, 'Littoral'], [11, $pf, 'Littoral', ['on_duty']]], 2, 'role_zone'],
    'R18 remplaçant·e de garde' => [['hebergement'], 'Littoral', [[7, $pf, 'Littoral'], [11, $pf, 'Littoral', ['on_duty']]], 11, 'focal_zone'],
    'R19 besoin en double' => [['soutien_juridique', 'soutien_juridique'], 'Littoral', [[2, $juriste, 'Littoral']], 2, 'role_zone'],
    'R20 slug inconnu sauté' => [['inconnu', 'soutien_medical'], 'Littoral', [[3, $gyn, 'Littoral']], 3, 'role_zone'],
    'R21 deux points focaux du département' => [['hebergement'], 'Littoral', [[1, $pf, 'Littoral'], [2, $pf, 'Littoral']], 1, 'focal_zone'],
    'R22 deux fiches nationales' => [['soutien_medical'], 'Zou', [[3, $pf, null, ['national']], [6, $pf, null, ['national']]], 3, 'focal_national'],
]);
