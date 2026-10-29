import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/theme/xm_tokens.dart';
import '../../../core/l10n/app_strings.dart';
import '../../profile/application/settings_providers.dart';
import '../application/pin_lock.dart';
import 'pin_widgets.dart';

/// Code PIN obligatoire pour entrer dans l'espace Pair ou Admin (D-MOB-6) :
/// le routeur y envoie tant qu'aucun code n'existe. Le code reste sur le
/// téléphone ; le SOS, les urgences et le signalement restent sans code (RG-01).
/// [from] : l'écran demandé, rouvert une fois le code choisi.
class PinSetupPage extends ConsumerStatefulWidget {
  const PinSetupPage({super.key, this.from});
  final String? from;

  @override
  ConsumerState<PinSetupPage> createState() => _PinSetupPageState();
}

class _PinSetupPageState extends ConsumerState<PinSetupPage> {
  bool _busy = false;

  /// Destination après le code : jamais une adresse extérieure à l'application.
  String get _target {
    final f = widget.from;
    return (f != null && f.startsWith('/') && !f.startsWith('//')) ? f : '/home';
  }

  Future<void> _choose() async {
    final pin = await askNewPin(context);
    if (pin == null || !mounted) return;
    setState(() => _busy = true);
    await ref.read(pinLockProvider.notifier).enable(pin);
    // Le serveur ne connaît que « code activé » ; en cas d'échec réseau, SecurityController le recale.
    try {
      await ref.read(securityProvider.notifier).setPinEnabled(true);
    } catch (_) {}
    if (!mounted) return;
    setState(() => _busy = false);
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(context.t('mobile_pin_enabled'))));
    context.go(_target);
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) context.go('/home');
      },
      child: Scaffold(
        appBar: AppBar(
          leading: BackButton(key: const ValueKey('pin-setup-back'), onPressed: () => context.go('/home')),
        ),
        body: SafeArea(
          child: ListView(
            padding: const EdgeInsets.all(XmSpace.gutter),
            children: [
              Icon(Icons.lock_outline, size: 40, color: context.xm.primary),
              const SizedBox(height: XmSpace.s3),
              Semantics(header: true, child: Text(context.t('mobile_pin_required_title'), style: theme.textTheme.headlineMedium)),
              const SizedBox(height: XmSpace.s3),
              Text(context.t('mobile_pin_required_text'), style: theme.textTheme.bodyLarge),
              const SizedBox(height: XmSpace.s5),
              FilledButton(
                key: const ValueKey('pin-setup-choose'),
                style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(52)),
                onPressed: _busy ? null : _choose,
                child: Text(context.t('mobile_pin_required_choose')),
              ),
              const SizedBox(height: XmSpace.s3),
              TextButton(
                key: const ValueKey('pin-setup-member'),
                style: TextButton.styleFrom(minimumSize: const Size.fromHeight(48)),
                onPressed: () => context.go('/home'),
                child: Text(context.t('mobile_pin_required_back')),
              ),
              const SizedBox(height: XmSpace.s4),
              Text(context.t('mobile_pin_free_title'), style: theme.textTheme.labelLarge),
              Row(children: [
                TextButton(onPressed: () => context.push('/urgences'), child: Text(context.t('alerts_emergency_title'))),
              ]),
            ],
          ),
        ),
      ),
    );
  }
}
