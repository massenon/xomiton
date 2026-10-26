import 'package:flutter/material.dart';
import 'package:flutter/scheduler.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../app/router.dart';
import '../../../app/theme/xm_tokens.dart';
import '../../../core/l10n/app_strings.dart';
import '../application/pin_lock.dart';

/// Écrans accessibles sans code (RG-01) : SOS, urgences, signalement anonyme.
const Set<String> kPinFreePrefixes = {
  '/sos',
  '/alerte-sos',
  '/urgences',
  '/signaler',
  '/suivi',
};

bool isPinFreeLocation(String path) => kPinFreePrefixes.any(path.startsWith);

/// Pavé numérique à 4 chiffres. `onComplete` reçoit le code ; changer la
/// `key` du widget le remet à zéro.
class PinEntry extends StatefulWidget {
  const PinEntry({super.key, required this.onComplete, this.enabled = true});
  final ValueChanged<String> onComplete;
  final bool enabled;

  @override
  State<PinEntry> createState() => _PinEntryState();
}

class _PinEntryState extends State<PinEntry> {
  String _value = '';

  void _press(String d) {
    if (!widget.enabled || _value.length >= 4) return;
    HapticFeedback.selectionClick();
    setState(() => _value += d);
    if (_value.length == 4) widget.onComplete(_value);
  }

  void _back() {
    if (_value.isEmpty) return;
    setState(() => _value = _value.substring(0, _value.length - 1));
  }

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    Widget key(String label, VoidCallback? onTap, {Key? k, String? semantics}) => Padding(
      padding: const EdgeInsets.all(4),
      child: SizedBox(
        width: 72,
        height: 56,
        child: Semantics(
          button: true,
          label: semantics ?? label,
          excludeSemantics: true,
          child: OutlinedButton(
            key: k,
            onPressed: widget.enabled ? onTap : null,
            style: OutlinedButton.styleFrom(padding: EdgeInsets.zero),
            child: label == '⌫' ? const Icon(Icons.backspace_outlined) : Text(label, style: Theme.of(context).textTheme.titleLarge),
          ),
        ),
      ),
    );
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Semantics(
          liveRegion: true,
          label: context.t('mobile_pin_digits_entered', {'count': _value.length}),
          excludeSemantics: true,
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              for (var i = 0; i < 4; i++)
                Container(
                  key: Key('pin-dot-$i'),
                  margin: const EdgeInsets.all(8),
                  width: 16,
                  height: 16,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: i < _value.length ? cs.primary : Colors.transparent,
                    border: Border.all(color: cs.primary, width: 2),
                  ),
                ),
            ],
          ),
        ),
        const SizedBox(height: XmSpace.s3),
        for (final row in const [
          ['1', '2', '3'],
          ['4', '5', '6'],
          ['7', '8', '9'],
        ])
          Row(
            mainAxisSize: MainAxisSize.min,
            children: [for (final d in row) key(d, () => _press(d), k: Key('pin-key-$d'))],
          ),
        Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            const SizedBox(width: 80),
            key('0', () => _press('0'), k: const Key('pin-key-0')),
            key('⌫', _back, k: const Key('pin-key-back'), semantics: context.t('mobile_pin_backspace')),
          ],
        ),
      ],
    );
  }
}

/// Saisie d'un code dans une feuille modale ; `null` si annulée.
Future<String?> askPin(BuildContext context, String titleKey, {String? textKey}) {
  return showModalBottomSheet<String>(
    context: context,
    isScrollControlled: true,
    showDragHandle: true,
    builder: (ctx) => SafeArea(
      child: Padding(
        padding: const EdgeInsets.all(XmSpace.s5),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(ctx.t(titleKey), style: Theme.of(ctx).textTheme.titleLarge, textAlign: TextAlign.center),
            if (textKey != null) ...[
              const SizedBox(height: XmSpace.s2),
              Text(ctx.t(textKey), textAlign: TextAlign.center),
            ],
            const SizedBox(height: XmSpace.s4),
            PinEntry(onComplete: (pin) => Navigator.of(ctx).pop(pin)),
          ],
        ),
      ),
    ),
  );
}

/// Choix d'un nouveau code, confirmé par une seconde saisie.
Future<String?> askNewPin(BuildContext context) async {
  while (true) {
    final first = await askPin(context, 'mobile_pin_set_title', textKey: 'mobile_pin_set_text');
    if (first == null || !context.mounted) return null;
    final second = await askPin(context, 'mobile_pin_confirm_title');
    if (second == null || !context.mounted) return null;
    if (first == second) return first;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(context.t('mobile_pin_mismatch'))));
  }
}

/// Écran de verrouillage. SOS, urgences et signalement anonyme restent
/// accessibles sans code.
class PinLockScreen extends ConsumerStatefulWidget {
  const PinLockScreen({super.key});

  @override
  ConsumerState<PinLockScreen> createState() => _PinLockScreenState();
}

class _PinLockScreenState extends ConsumerState<PinLockScreen> {
  int _round = 0;
  bool _busy = false;
  bool _exhausted = false;

  Future<void> _submit(String pin) async {
    setState(() => _busy = true);
    final res = await ref.read(pinLockProvider.notifier).unlock(pin);
    if (!mounted) return;
    setState(() {
      _busy = false;
      _round++;
      _exhausted = res.exhausted;
    });
  }

  @override
  Widget build(BuildContext context) {
    final remaining = ref.watch(pinLockProvider.select((s) => s.remaining));
    final router = ref.read(routerProvider);
    Widget free(String key, IconData icon, VoidCallback onTap, Key k) => TextButton.icon(
      key: k,
      onPressed: onTap,
      icon: Icon(icon),
      label: Text(context.t(key)),
    );
    return Material(
      color: Theme.of(context).scaffoldBackgroundColor,
      child: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(XmSpace.s5),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Icon(Icons.lock_outline, size: 40),
                const SizedBox(height: XmSpace.s3),
                Text(context.t('mobile_pin_unlock_title'), style: Theme.of(context).textTheme.titleLarge, textAlign: TextAlign.center),
                const SizedBox(height: XmSpace.s2),
                Text(context.t('mobile_pin_unlock_text'), textAlign: TextAlign.center),
                const SizedBox(height: XmSpace.s4),
                PinEntry(key: ValueKey(_round), enabled: !_busy && !_exhausted, onComplete: _submit),
                const SizedBox(height: XmSpace.s3),
                if (_exhausted)
                  Text(context.t('mobile_pin_exhausted'), key: const Key('pin-exhausted'), textAlign: TextAlign.center, style: TextStyle(color: context.xm.danger))
                else if (remaining != null)
                  Text(
                    context.t('mobile_pin_wrong', {'count': remaining}),
                    key: const Key('pin-wrong'),
                    textAlign: TextAlign.center,
                    style: TextStyle(color: context.xm.danger),
                  ),
                const Divider(height: XmSpace.s6),
                Text(context.t('mobile_pin_free_title'), style: Theme.of(context).textTheme.labelLarge),
                Wrap(
                  alignment: WrapAlignment.center,
                  children: [
                    free('mobile_tab_sos', Icons.sos, () => router.go('/sos'), const Key('pin-free-sos')),
                    free('alerts_emergency_title', Icons.phone_in_talk_outlined, () => router.push('/urgences'), const Key('pin-free-emergency')),
                    free('feed_quick_report', Icons.flag_outlined, () => router.push('/signaler'), const Key('pin-free-report')),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// Recouvre l'application tant que le verrou est actif, sauf sur les écrans
/// de sécurité. Observe aussi le cycle de vie : retour en avant-plan après
/// le délai ⇒ verrouillage.
class PinGate extends ConsumerStatefulWidget {
  const PinGate({super.key, required this.child});
  final Widget child;

  @override
  ConsumerState<PinGate> createState() => _PinGateState();
}

class _PinGateState extends ConsumerState<PinGate> with WidgetsBindingObserver {
  late final _delegate = ref.read(routerProvider).routerDelegate;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _delegate.addListener(_onRoute);
  }

  void _onRoute() {
    if (!mounted) return;
    // Le routeur notifie parfois pendant une construction : on diffère alors.
    if (SchedulerBinding.instance.schedulerPhase == SchedulerPhase.persistentCallbacks) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) setState(() {});
      });
    } else {
      setState(() {});
    }
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    final ctrl = ref.read(pinLockProvider.notifier);
    if (state == AppLifecycleState.paused || state == AppLifecycleState.hidden) {
      ctrl.onBackground();
    } else if (state == AppLifecycleState.resumed) {
      ctrl.onForeground();
    }
  }

  @override
  void dispose() {
    _delegate.removeListener(_onRoute);
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final locked = ref.watch(pinLockProvider.select((s) => s.locked));
    // Écran du dessus (y compris poussé avec `push`).
    final path = _delegate.currentConfiguration.isEmpty ? '' : _delegate.state.matchedLocation;
    final show = locked && !isPinFreeLocation(path);
    return Stack(
      children: [
        ExcludeSemantics(excluding: show, child: IgnorePointer(ignoring: show, child: widget.child)),
        if (show) const Positioned.fill(child: PinLockScreen()),
      ],
    );
  }
}
