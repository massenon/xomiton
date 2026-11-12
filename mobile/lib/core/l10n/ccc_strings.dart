/// Textes propres à la lecture mobile des outils CCC, absents de `web/lang/fr/ccc.php`
/// (clés `mobile_*` seulement, règle testée par `strings_test`). Les autres textes
/// viennent de l'ARB exporté depuis `ccc.php`.
const Map<String, String> kCccStringsFr = {
  'mobile_ccc_forbidden': 'Ton compte n’a pas accès aux outils CCC pour le moment.',
  'mobile_ccc_storage_unavailable':
      'Ton téléphone ne permet pas de garder ta progression : elle sera perdue en quittant l’écran.',
  'mobile_ccc_laws_unavailable':
      'Retrouve les lois et codes du Bénin dans la rubrique « Lois » du site Xomiton.',
  'mobile_ccc_laws_open_failed': 'Impossible d’ouvrir la page des lois pour le moment.',
  'mobile_ccc_comic_public_open_failed': 'Impossible d’ouvrir la page de la BD pour le moment.',
  'mobile_ccc_show_hint': 'Glisse vers la gauche ou la droite pour changer d’image.',
  'mobile_ccc_comic_hint': 'Glisse vers la gauche ou la droite pour changer de planche.',
  'mobile_ccc_manual_progress_summary': '{done} sur {total} points vus · {percent} %',
  'mobile_ccc_reset_confirm_title': 'Effacer ta progression ?',
  'mobile_ccc_reset_confirm_body': 'Les points que tu as cochés seront décochés sur ce téléphone.',
};
