/// Textes mobiles qui n'existent pas encore dans `web/lang/fr` (donc pas dans
/// `lib/l10n/app_fr.arb`). À migrer vers `web/lang/fr/mobile.php` puis à
/// régénérer par `php artisan lang:export` : cette table s'efface alors
/// d'elle-même (l'ARB prime toujours sur cette table).
const Map<String, String> kPendingStringsFr = {
  'mobile_onboarding_slide_1_title': 'Un espace safe, rien que pour nous',
  'mobile_onboarding_slide_1_body': 'Rejoins une communauté bienveillante de filles et de femmes LBTQ+ au Bénin, sous ton vrai nom ou sous pseudonyme.',
  'mobile_onboarding_slide_2_title': 'Du soutien quand tu en as besoin',
  'mobile_onboarding_slide_2_body': 'Pair·es éducateur·rices, juristes et psychologues t’écoutent en toute confidentialité.',
  'mobile_onboarding_slide_3_title': 'Apprendre à ton rythme',
  'mobile_onboarding_slide_3_body': 'Fiches, bandes dessinées et ateliers pour connaître tes droits et prendre soin de toi.',
  'mobile_onboarding_slide_4_title': 'Ta sécurité passe d’abord',
  'mobile_onboarding_slide_4_body': 'Un bouton SOS toujours à portée, le signalement anonyme et un mode discret.',
  'mobile_onboarding_carousel_label': 'Présentation de Xomiton',
  'mobile_onboarding_slide_label': 'Diapositive {current} sur {total}',
  'mobile_onboarding_start': 'Commencer',
  'mobile_onboarding_have_account': 'J’ai déjà un compte',
  'mobile_tab_sos': 'SOS',
  'mobile_nav_label': 'Navigation principale',
  'mobile_tile_learn_count': '{count, plural, =0{Aucune thématique} =1{{count} thématique} other{{count} thématiques}}',
  'mobile_resources_size_unknown': 'Poids inconnu',
  'mobile_resources_download_failed':
      'Le lien de téléchargement n’a pas pu être ouvert.',
  'mobile_stale_notice': 'Contenu enregistré sur cet appareil : il sera mis à jour au retour du réseau.',
  'mobile_notifications_filter_unread': 'Non lues',
  'mobile_notifications_filter_all': 'Toutes',
  'mobile_device_name_fallback': 'Appareil',
  'mobile_toast_session_closed': 'Ta session a été fermée. Reconnecte-toi.',
  'mobile_event_next': 'Prochain événement',
  // P15-04 : SOS, signalement, suivi (textes propres au mobile).
  'mobile_sos_queued_saved': 'Ton alerte est enregistrée sur ce téléphone et partira dès que le réseau revient, même si tu fermes l’application.',
  'mobile_report_waiting_title': 'En attente d’envoi',
  'mobile_report_add_files': 'Ajouter des fichiers',
  'mobile_reports_mine_title': 'Mes signalements',
  'mobile_reports_mine_empty': 'Aucun signalement lié à ton compte. Les signalements anonymes se retrouvent avec leur code de suivi.',
  // P15-05 : fil.
  'mobile_feed_load_more': 'Charger plus',
};
