import 'dart:io' show Platform;

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/scheduler.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../app/router.dart';
import '../ui/xm_widgets.dart';

/// Build de recette seulement (`--dart-define=ALLOW_SCREENSHOTS=true`) : les
/// testeurs ont besoin des captures. **Ignoré en `release`** : la production
/// n'autorise jamais la capture d'un écran sensible (plan §7.2).
const bool kAllowScreenshotsDefine = bool.fromEnvironment('ALLOW_SCREENSHOTS');

bool screenshotsAllowed({bool? define, bool? release}) =>
    (define ?? kAllowScreenshotsDefine) && !(release ?? kReleaseMode);

/// Écrans sensibles (plan §7.2) : tout `pair/**` et `admin/**` ; côté Membre,
/// les messages, la conversation, le signalement, le suivi d'une alerte, le SOS
/// et la sécurité. Le fil, l'accueil et les ressources restent capturables.
const List<String> kSecureLocationPrefixes = [
  '/pair',
  '/admin',
  '/pin-setup',
  '/messages',
  '/signaler',
  '/signalements',
  '/suivi',
  '/sos',
  '/alerte-sos',
  '/securite',
  '/appareils',
];

bool isSecureLocation(String path) => kSecureLocationPrefixes.any((p) => path == p || path.startsWith('$p/'));

/// Pose ou retire `FLAG_SECURE` (Android). iOS n'a pas d'équivalent : voir le
/// recouvrement du [ScreenSecurityGate] et la limite documentée (README).
abstract interface class ScreenSecurityService {
  Future<void> setSecure(bool secure);
}

class ChannelScreenSecurity implements ScreenSecurityService {
  static const _channel = MethodChannel('xomiton/secure');

  @override
  Future<void> setSecure(bool secure) async {
    try {
      if (!Platform.isAndroid) return;
      await _channel.invokeMethod<void>('setSecure', {'secure': secure});
    } catch (_) {
      // Plateforme sans canal (tests, bureau) : sans effet.
    }
  }
}

final screenSecurityProvider = Provider<ScreenSecurityService>((_) => ChannelScreenSecurity());

/// Autorise-t-on les captures ? Surchargeable dans les tests de recette.
final screenshotsAllowedProvider = Provider<bool>((_) => screenshotsAllowed());

/// Le drapeau est posé tant qu'au moins un écran sensible l'a demandé
/// (plusieurs écrans peuvent se recouvrir) et retiré dès que le dernier part.
class SecureScreenCoordinator {
  SecureScreenCoordinator(this._service, {required this.allowScreenshots});
  final ScreenSecurityService _service;
  final bool allowScreenshots;
  final Set<Object> _owners = {};
  bool? _applied;

  bool get active => _owners.isNotEmpty && !allowScreenshots;

  void acquire(Object owner) {
    _owners.add(owner);
    _apply();
  }

  void release(Object owner) {
    _owners.remove(owner);
    _apply();
  }

  void _apply() {
    final want = active;
    if (_applied == want) return;
    _applied = want;
    _service.setSecure(want);
  }
}

final secureScreenProvider = Provider<SecureScreenCoordinator>(
  (ref) => SecureScreenCoordinator(
    ref.watch(screenSecurityProvider),
    allowScreenshots: ref.watch(screenshotsAllowedProvider),
  ),
);

/// Écran sensible : le drapeau est posé tant que ce widget est affiché.
class SecureScreen extends ConsumerStatefulWidget {
  const SecureScreen({super.key, required this.child});
  final Widget child;

  @override
  ConsumerState<SecureScreen> createState() => _SecureScreenState();
}

class _SecureScreenState extends ConsumerState<SecureScreen> {
  late final SecureScreenCoordinator _coordinator = ref.read(secureScreenProvider);

  @override
  void initState() {
    super.initState();
    _coordinator.acquire(this);
  }

  @override
  void dispose() {
    _coordinator.release(this);
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => widget.child;
}

/// Au-dessus du routeur : pose le drapeau d'après l'adresse affichée (y compris
/// un écran poussé) et, sur un écran sensible, recouvre le contenu dès que
/// l'application quitte le premier plan (aperçu du sélecteur d'applications,
/// seul rempart sur iPhone).
class ScreenSecurityGate extends ConsumerStatefulWidget {
  const ScreenSecurityGate({super.key, required this.child});
  final Widget child;

  @override
  ConsumerState<ScreenSecurityGate> createState() => _ScreenSecurityGateState();
}

class _ScreenSecurityGateState extends ConsumerState<ScreenSecurityGate> with WidgetsBindingObserver {
  late final _delegate = ref.read(routerProvider).routerDelegate;
  late final SecureScreenCoordinator _coordinator = ref.read(secureScreenProvider);
  AppLifecycleState _lifecycle = AppLifecycleState.resumed;

  String get _path => _delegate.currentConfiguration.isEmpty ? '' : _delegate.state.matchedLocation;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _delegate.addListener(_onRoute);
    WidgetsBinding.instance.addPostFrameCallback((_) => _sync());
  }

  void _sync() {
    if (!mounted) return;
    if (isSecureLocation(_path)) {
      _coordinator.acquire(this);
    } else {
      _coordinator.release(this);
    }
  }

  void _onRoute() {
    if (!mounted) return;
    _sync();
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
  void didChangeAppLifecycleState(AppLifecycleState state) => setState(() => _lifecycle = state);

  @override
  void dispose() {
    _delegate.removeListener(_onRoute);
    WidgetsBinding.instance.removeObserver(this);
    _coordinator.release(this);
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final cover = _lifecycle != AppLifecycleState.resumed &&
        isSecureLocation(_path) &&
        !ref.watch(screenshotsAllowedProvider);
    return Stack(children: [
      widget.child,
      if (cover)
        Positioned.fill(
          key: const ValueKey('privacy-cover'),
          child: ExcludeSemantics(
            child: ColoredBox(color: Theme.of(context).colorScheme.surface, child: const Center(child: XmLogo(width: 120))),
          ),
        ),
    ]);
  }
}
