import 'package:flutter/widgets.dart';
import 'package:intl/intl.dart';

import '../l10n/app_strings.dart';

/// Formats venant de l'ARB (`dates_formats_*`), jamais écrits en dur.
/// Prérequis : `initializeDateFormatting('fr')` au démarrage.
String formatDateTime(BuildContext context, DateTime d) {
  final s = AppStrings.of(context);
  final local = d.toLocal();
  final pattern = local.minute == 0
      ? s.raw('dates_formats_datetime_text')
      : s.raw('dates_formats_datetime_text_minutes');
  return DateFormat(pattern, 'fr').format(local);
}

/// « il y a 5 min », « il y a 3 h », « hier », sinon date courte.
String relativeTime(BuildContext context, DateTime d, {DateTime? now}) {
  final s = AppStrings.of(context);
  final n = now ?? DateTime.now();
  final diff = n.difference(d);
  if (diff.inMinutes < 1) return s.t('dates_relative_now');
  if (diff.inMinutes < 60) return s.t('dates_relative_minutes', {'count': diff.inMinutes});
  if (diff.inHours < 24) return s.t('dates_relative_hours', {'count': diff.inHours});
  if (diff.inDays == 1) return s.t('dates_relative_yesterday');
  if (diff.inDays < 7) return s.t('dates_relative_days', {'count': diff.inDays});
  return DateFormat(s.raw('dates_formats_medium'), 'fr').format(d.toLocal());
}
