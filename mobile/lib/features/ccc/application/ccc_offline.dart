import 'dart:async';
import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:path_provider/path_provider.dart';

import '../data/ccc_image_store.dart';
import '../data/ccc_models.dart';
import '../data/ccc_repository.dart';

/// Client des images : **sans jeton** (voir [CccImageStore]). Surchargé dans les tests.
final cccImageDioProvider = Provider<Dio>(
  (ref) => Dio(BaseOptions(connectTimeout: const Duration(seconds: 15), receiveTimeout: const Duration(seconds: 30))),
);

/// Dossier des images hors ligne : stockage de l'application (jamais le cache que le système peut vider).
final cccImageDiskProvider = Provider<CccImageDisk>(
  (ref) => DirectoryImageDisk(() async => Directory('${(await getApplicationSupportDirectory()).path}/ccc_images')),
);

final cccImageStoreProvider = Provider<CccImageStore>(
  (ref) => CccImageStore(disk: ref.watch(cccImageDiskProvider), dio: ref.watch(cccImageDioProvider)),
);

enum CccDownloadPhase { idle, running, done, cancelled }

class CccImagesState {
  const CccImagesState({
    this.ready = false,
    this.supported = false,
    this.names = const {},
    this.phase = CccDownloadPhase.idle,
    this.done = 0,
    this.total = 0,
    this.failed = 0,
    this.full = false,
  });

  /// Disque interrogé (avant : on ne sait pas encore ce qui est enregistré).
  final bool ready;

  /// Faux si l'appareil ne donne aucun dossier : aucune offre de téléchargement.
  final bool supported;
  final Set<String> names;
  final CccDownloadPhase phase;
  final int done;
  final int total;
  final int failed;
  final bool full;

  bool has(String url) => names.contains(CccImageStore.nameFor(url));

  /// Toutes les images de [urls] sont sur disque (et il y en a au moins une).
  bool hasAll(Set<String> urls) => supported && urls.isNotEmpty && urls.every(has);

  CccImagesState copyWith({
    bool? ready,
    bool? supported,
    Set<String>? names,
    CccDownloadPhase? phase,
    int? done,
    int? total,
    int? failed,
    bool? full,
  }) =>
      CccImagesState(
        ready: ready ?? this.ready,
        supported: supported ?? this.supported,
        names: names ?? this.names,
        phase: phase ?? this.phase,
        done: done ?? this.done,
        total: total ?? this.total,
        failed: failed ?? this.failed,
        full: full ?? this.full,
      );
}

/// Images CCC enregistrées : état du disque et téléchargement à la demande
/// (bouton de l'écran CCC ; jamais silencieux : l'application n'a pas de réglage
/// « Wi-Fi seulement », D-MOB-19).
class CccImagesController extends Notifier<CccImagesState> {
  CancelToken? _cancel;
  Future<void>? _init;

  CccImageStore get _store => ref.read(cccImageStoreProvider);

  @override
  CccImagesState build() {
    ref.onDispose(() => _cancel?.cancel('dispose'));
    _init = _load();
    return const CccImagesState();
  }

  Future<void> _load() async {
    try {
      final ok = await _store.prepare();
      final names = ok ? await _store.names() : <String>{};
      state = state.copyWith(ready: true, supported: ok, names: names);
    } catch (_) {
      state = state.copyWith(ready: true, supported: false);
    }
  }

  Future<void> _refreshNames() async {
    try {
      state = state.copyWith(names: await _store.names());
    } catch (_) {}
  }

  /// Télécharge les images de [urls], une à une, jusqu'à la fin ou à [cancel].
  Future<void> download(Set<String> urls) async {
    await _init;
    if (!state.supported || state.phase == CccDownloadPhase.running || urls.isEmpty) return;
    final token = _cancel = CancelToken();
    state = state.copyWith(phase: CccDownloadPhase.running, done: 0, total: urls.length, failed: 0, full: false);
    CccDownloadResult? result;
    try {
      result = await _store.download(
        urls,
        cancel: token,
        onProgress: (d, t) => state = state.copyWith(done: d, total: t),
      );
    } catch (_) {
      result = CccDownloadResult(failed: urls.length);
    }
    await _refreshNames();
    state = state.copyWith(
      phase: result.cancelled ? CccDownloadPhase.cancelled : CccDownloadPhase.done,
      failed: result.failed,
      full: result.full,
    );
  }

  void cancel() => _cancel?.cancel('user');

  /// Après une synchro réussie : retire les images qui ne sont plus dans la réponse.
  Future<void> prune(Set<String> keepUrls) async {
    await _init;
    if (!state.supported || state.phase == CccDownloadPhase.running) return;
    try {
      if (await _store.prune(keepUrls) > 0) await _refreshNames();
    } catch (_) {}
  }

  /// Déconnexion : tout part.
  Future<void> clear() async {
    await _init;
    _cancel?.cancel('clear');
    try {
      if (state.supported) await _store.clear();
    } catch (_) {}
    state = state.copyWith(names: const {}, phase: CccDownloadPhase.idle, done: 0, total: 0, failed: 0, full: false);
  }
}

final cccImagesProvider = NotifierProvider<CccImagesController, CccImagesState>(CccImagesController.new);

/// Un outil à planches est « disponible hors ligne » quand toutes ses images sont sur disque.
bool cccToolOffline(CccImagesState s, PagedTool t) => s.hasAll(cccToolImageUrls(t));
