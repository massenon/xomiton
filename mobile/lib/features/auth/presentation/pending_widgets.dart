import 'package:flutter/material.dart';

import '../../../app/theme/xm_tokens.dart';
import '../../../core/l10n/app_strings.dart';
import '../../../core/ui/xm_widgets.dart';

/// Explication affichée à la place (ou au-dessus) d'une action fermée tant que
/// le compte attend la validation de l'équipe.
class PendingNotice extends StatelessWidget {
  const PendingNotice({super.key, this.messageKey = 'mobile_pending_action_blocked'});
  final String messageKey;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.all(XmSpace.gutter),
        child: XmAlert(context.t(messageKey), icon: Icons.hourglass_empty, tone: context.xm.info),
      );
}
