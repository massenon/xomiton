import 'dart:async';
import 'dart:io' show Platform;

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/scheduler.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../app/router.dart';
import '../network/app_toast.dart';
import '../providers/core_providers.dart';
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

/// Pose ou retire `FLAG_SECURE` (Android). iOS n'a pas d'équivalent : le plugin
/// natif `XomitonSecurityPlugin.swift` (NON COMPILÉ) recouvre l'écran dès la
/// sortie du premier plan et pendant un enregistrement d'écran sur un écran
/// sensible ; une capture d'écran ne peut qu'être détectée (voir
/// [ScreenCaptureSource]). Limite documentée (README, plan iOS §4.6).
abstract interface class ScreenSecurityService {
  Future<void> setSecure(bool secure);
}

class ChannelScreenSecurity implements ScreenSecurityService {
  static const _channel = MethodChannel('xomiton/secure');

  @override
  Future<void> setSecure(bool secure) async {
    try {
      if (!(Platform.isAndroid || Platform.isIOS)) return;
      await _channel.invokeMethod<void>('setSecure', {'secure': secure});
    } catch (_) {
      // Plateforme sans canal (tests, bureau) : sans effet.
    }
  }
}

final screenSecurityProvider = Provider<ScreenSecurityService>((_) => ChannelScreenSecurity());

/// Événement natif de capture (iOS seulement).
sealed class ScreenCaptureEvent {
  const ScreenCaptureEvent();
}

/// L'utilisateur·rice vient de faire une capture d'écran (détection après coup).
class ScreenshotTaken extends ScreenCaptureEvent {
  const ScreenshotTaken();
}

/// Début ou fin d'un enregistrement ou d'une recopie d'écran (`UIScreen.isCaptured`).
class CaptureChanged extends ScreenCaptureEvent {
  const CaptureChanged(this.captured);
  final bool captured;
}

abstract interface class ScreenCaptureSource {
  Stream<ScreenCaptureEvent> get events;
  Future<bool> isCaptured();
}

/// Canal `app.xomiton/capture` : le natif appelle `screenshot` et `captureChanged`
/// {captured: bool} ; Dart peut interroger `isCaptured`. Inactif hors iOS (aucun
/// gestionnaire installé, flux muet).
class ChannelScreenCapture implements ScreenCaptureSource {
  ChannelScreenCapture({bool? enabled, MethodChannel? channel})
      : _channel = channel ?? const MethodChannel('app.xomiton/capture'),
        _enabled = enabled ?? (!kIsWeb && Platform.isIOS) {
    if (_enabled) _channel.setMethodCallHandler(_onCall);
  }

  final MethodChannel _channel;
  final bool _enabled;
  final StreamController<ScreenCaptureEvent> _controller = StreamController<ScreenCaptureEvent>.broadcast();

  Future<dynamic> _onCall(MethodCall call) async {
    switch (call.method) {
      case 'screenshot':
        _controller.add(const ScreenshotTaken());
      case 'captureChanged':
        final args = call.arguments;
        _controller.add(CaptureChanged(args is Map && args['captured'] == true));
    }
    return null;
  }

  @override
  Stream<ScreenCaptureEvent> get events => _controller.stream;

  @override
  Future<bool> isCaptured() async {
    if (!_enabled) return false;
    try {
      return await _channel.invokeMethod<bool>('isCaptured') ?? false;
    } catch (_) {
      return false;
    }
  }
}

final screenCaptureProvider = Provider<ScreenCaptureSource>((_) => ChannelScreenCapture());

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
  bool _captured = false;
  StreamSubscription<ScreenCaptureEvent>? _captureSub;

  String get _path => _delegate.currentConfiguration.isEmpty ? '' : _delegate.state.matchedLocation;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _delegate.addListener(_onRoute);
    WidgetsBinding.instance.addPostFrameCallback((_) => _sync());
    final capture = ref.read(screenCaptureProvider);
    _captureSub = capture.events.listen(_onCapture);
    capture.isCaptured().then((v) {
      if (mounted && v != _captured) setState(() => _captured = v);
    });
  }

  void _onCapture(ScreenCaptureEvent e) {
    if (!mounted) return;
    switch (e) {
      case ScreenshotTaken():
        // Détection après coup : on prévient, seulement sur un écran sensible.
        if (isSecureLocation(_path) && !ref.read(screenshotsAllowedProvider)) {
          ref.read(toastBusProvider).emit(const ScreenshotToast());
        }
      case CaptureChanged(:final captured):
        setState(() => _captured = captured);
    }
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
    _captureSub?.cancel();
    _delegate.removeListener(_onRoute);
    WidgetsBinding.instance.removeObserver(this);
    _coordinator.release(this);
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    // Hors premier plan, ou enregistrement d'écran en cours (iOS) : écran sensible recouvert.
    final cover = (_lifecycle != AppLifecycleState.resumed || _captured) &&
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
