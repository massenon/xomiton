import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../app/theme/xm_tokens.dart';
import '../../../core/l10n/app_strings.dart';
import '../application/sos_controller.dart';

/// Bouton SOS (Q21 b) : maintien de 2 s, ou double appui puis confirmation
/// (alternative sans maintien : lecteur d'écran, motricité). 96 dp, rouge.
class SosHoldButton extends StatefulWidget {
  const SosHoldButton({
    super.key,
    required this.onTriggered,
    required this.confirmText,
  });

  final VoidCallback onTriggered;
  final String confirmText;

  @override
  State<SosHoldButton> createState() => _SosHoldButtonState();
}

class _SosHoldButtonState extends State<SosHoldButton>
    with SingleTickerProviderStateMixin {
  // L'animation n'est que visuelle (elle peut être réduite par l'accessibilité) :
  // c'est le minuteur qui décide du maintien de 2 s.
  late final AnimationController _hold = AnimationController(
    vsync: this,
    duration: sosHoldDuration,
    animationBehavior: AnimationBehavior.preserve,
  );
  Timer? _timer;

  void _down() {
    _timer?.cancel();
    _hold.forward(from: 0);
    _timer = Timer(sosHoldDuration, () {
      HapticFeedback.heavyImpact();
      if (mounted) _hold.value = 0;
      widget.onTriggered();
    });
  }

  void _up() {
    _timer?.cancel();
    if (mounted) _hold.value = 0;
  }

  @override
  void dispose() {
    _timer?.cancel();
    _hold.dispose();
    super.dispose();
  }

  Future<void> _confirm() async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        title: Text(c.t('components_sos_confirm_title')),
        content: Text(widget.confirmText),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(c, false),
            child: Text(c.t('components_sos_cancel')),
          ),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: c.xm.danger),
            onPressed: () => Navigator.pop(c, true),
            child: Text(c.t('components_sos_send')),
          ),
        ],
      ),
    );
    if (ok == true) widget.onTriggered();
  }

  @override
  Widget build(BuildContext context) {
    final xm = context.xm;
    return Semantics(
      button: true,
      label: context.t('components_sos_label'),
      hint: context.t('components_sos_help'),
      onTap: _confirm,
      excludeSemantics: true,
      child: GestureDetector(
        onDoubleTap: _confirm,
        child: Listener(
          onPointerDown: (_) => _down(),
          onPointerUp: (_) => _up(),
          onPointerCancel: (_) => _up(),
          child: AnimatedBuilder(
            animation: _hold,
            builder: (context, _) => SizedBox(
              width: 160,
              height: 160,
              child: Stack(
                alignment: Alignment.center,
                children: [
                  SizedBox(
                    width: 160,
                    height: 160,
                    child: CircularProgressIndicator(
                      value: _hold.value,
                      strokeWidth: 8,
                      color: xm.danger,
                      backgroundColor: xm.danger.withValues(alpha: 0.18),
                    ),
                  ),
                  Container(
                    width: 128,
                    height: 128,
                    decoration: BoxDecoration(
                      color: xm.danger,
                      shape: BoxShape.circle,
                    ),
                    alignment: Alignment.center,
                    child: Text(
                      context.t('components_sos_label'),
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 34,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
