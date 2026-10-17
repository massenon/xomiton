<?php

declare(strict_types=1);

/*
 * Les 16 indicateurs intégrés du prototype (prototype_social/data/indicateurs.json), dans l'ordre d'affichage.
 * Colonnes : code, label, definition, formula, source, frequency (libellé du prototype), target, unit, calc_key, better.
 */
return [
    ['IND-01', 'Cas enregistrés (VBG + VDH)', 'Nombre de dossiers enregistrés dans les registres sur la période.', 'Σ dossiers non archivés', 'Registres numériques', 'Mensuelle', 80, 'nb', 'cas_total', 'up'],
    ['IND-02', 'Cas de VBG', 'Dossiers du registre VBG.', 'Σ dossiers kind = VBG', 'Registre VBG', 'Mensuelle', 55, 'nb', 'cas_vbg', 'up'],
    ['IND-03', 'Cas de VDH', 'Dossiers du registre violations des droits humains.', 'Σ dossiers kind = VDH', 'Registre VDH', 'Mensuelle', 25, 'nb', 'cas_vdh', 'up'],
    ['IND-04', '% de cas pris en charge en moins de 72 h', 'Part des cas pour lesquels le premier service est délivré dans les 72 h (simulation).', 'cas pris en charge ≤ 72 h / cas × 100', 'Registres + suivi', 'Mensuelle', 90, '%', 'pct_72h', 'up'],
    ['IND-05', '% de survivant·es orienté·es', 'Part des cas orientés vers une personne ou structure de l’annuaire.', 'cas avec orientation / cas × 100', 'Registres', 'Mensuelle', 85, '%', 'pct_orientes', 'up'],
    ['IND-06', '% de cas clos', 'Part des dossiers clôturés.', 'cas clos / cas × 100', 'Registres', 'Trimestrielle', 60, '%', 'pct_clos', 'up'],
    ['IND-07', 'Pairs animateur·rices actifs', 'Pairs ayant enregistré au moins un dossier sur la période.', 'Σ pairs distincts', 'Registres', 'Trimestrielle', 6, 'nb', 'pairs', 'up'],
    ['IND-08', 'Séances CCC / événements', 'Causeries, ateliers et événements organisés (module événements).', 'Σ événements', 'Réseau social', 'Mensuelle', 12, 'nb', 'seances', 'up'],
    ['IND-09', 'Taux d’ouverture de la newsletter', 'Part des e-mails de campagne ouverts par les abonné·es.', 'ouvertures uniques / e-mails envoyés × 100', 'Newsletter', 'Mensuelle', 35, '%', 'nl_open', 'up'],
    ['IND-10', '% d’alertes traitées dans les 24 h', 'Part des alertes reçues traitées dans les 24 h (simulation).', 'alertes traitées ≤ 24 h / alertes × 100', 'Alertes', 'Hebdomadaire', 80, '%', 'pct_alertes24', 'up'],
    ['IND-11', 'Alertes reçues', 'Dénonciations, besoins et SOS reçus.', 'Σ alertes', 'Alertes', 'Hebdomadaire', 30, 'nb', 'alertes', 'up'],
    ['IND-12', 'SOS reçus', 'Alertes de type SOS avec localisation GPS.', 'Σ alertes type = sos', 'Alertes', 'Hebdomadaire', 0, 'nb', 'sos', 'down'],
    ['IND-13', '% de suivis réalisés', 'Étapes de suivi réalisées sur étapes planifiées.', 'suivis réalisés / suivis × 100', 'Suivi des survivant·es', 'Mensuelle', 80, '%', 'pct_suivis', 'up'],
    ['IND-14', 'Survivant·es suivi·es', 'Dossiers distincts avec au moins une étape de suivi.', 'Σ codes dossier distincts', 'Suivi', 'Mensuelle', 50, 'nb', 'suivies', 'up'],
    ['IND-15', 'Utilisateur·rices actif·ves', 'Comptes au statut actif.', 'Σ utilisateurs actifs', 'Utilisateurs', 'Mensuelle', 20, 'nb', 'users', 'up'],
    ['IND-16', 'Couverture géographique', 'Part des 12 départements ayant enregistré au moins un cas.', 'départements avec cas / 12 × 100', 'Registres', 'Trimestrielle', 75, '%', 'couverture', 'up'],
];
