import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:xomiton/core/providers/core_providers.dart';
import 'package:xomiton/core/storage/api_cache.dart';
import 'package:xomiton/features/feed/data/compose_logic.dart';
import 'package:xomiton/features/feed/data/feed_api.dart';
import 'package:xomiton/features/feed/data/media_picker.dart';
import 'package:xomiton/features/feed/presentation/media_widgets.dart';

/// Sélecteur de médias de test : fichiers programmables, jamais le vrai sélecteur.
class FakeMediaPicker implements MediaPicker {
  FakeMediaPicker({this.images = const [], this.video});
  List<ComposeImage> images;
  ComposeVideo? video;
  int imageCalls = 0;
  int videoCalls = 0;

  @override
  Future<List<ComposeImage>> pickImages() async {
    imageCalls++;
    return images;
  }

  @override
  Future<ComposeVideo?> pickVideo() async {
    videoCalls++;
    return video;
  }
}

ComposeImage img(int n, {int size = 1000, String? name}) =>
    ComposeImage(path: '/tmp/xm-$n.jpg', name: name ?? 'photo$n.jpg', size: size);

/// Moteur vidéo de test : aucun décodage, état pilotable.
class FakeVideoEngine extends ChangeNotifier implements VideoEngine {
  FakeVideoEngine(this.url, {this.failInit = false});
  final Uri url;
  final bool failInit;
  bool initialized = false;
  bool playing = false;
  bool disposed = false;
  int plays = 0;
  int pauses = 0;
  Duration pos = Duration.zero;
  Duration dur = const Duration(seconds: 90);
  final List<Duration> seeks = [];

  @override
  Future<void> initialize() async {
    if (failInit) throw StateError('init');
    initialized = true;
    notifyListeners();
  }

  @override
  bool get isInitialized => initialized;
  @override
  bool get isPlaying => playing;
  @override
  bool get hasError => false;
  @override
  Duration get position => pos;
  @override
  Duration get duration => dur;
  @override
  Future<void> play() async {
    plays++;
    playing = true;
    notifyListeners();
  }

  @override
  Future<void> pause() async {
    pauses++;
    playing = false;
    notifyListeners();
  }

  @override
  Future<void> seekTo(Duration position) async {
    seeks.add(position);
    pos = position;
    notifyListeners();
  }

  @override
  Widget buildView() => const SizedBox(
    key: Key('fake-video-view'),
    height: 120,
    child: ColoredBox(color: Colors.black),
  );

  @override
  Future<void> dispose() async {
    disposed = true;
    super.dispose();
  }
}

/// Fabrique qui garde les moteurs créés (et peut en faire échouer les premiers).
class EngineLog {
  final List<FakeVideoEngine> engines = [];
  int failFirst = 0;
  VideoEngine make(Uri url) {
    final e = FakeVideoEngine(url, failInit: engines.length < failFirst);
    engines.add(e);
    return e;
  }
}

/// `FeedApi` dont l'envoi multipart est pilotable : progression, attente, échec, annulation.
class SpyFeedApi extends FeedApi {
  SpyFeedApi(super.dio, super.cache);

  final List<ComposeInput> sent = [];
  final List<String?> keys = [];
  Completer<CreatedPost>? gate;
  Object? failWith;
  CreatedPost result = const CreatedPost(inReview: false);
  List<int> progress = const [];

  @override
  Future<CreatedPost> createWithMedia(
    ComposeInput c, {
    String? idempotencyKey,
    CancelToken? cancel,
    void Function(int sent, int total)? onProgress,
  }) async {
    sent.add(c);
    keys.add(idempotencyKey);
    for (final p in progress) {
      onProgress?.call(p, 100);
    }
    final g = gate;
    if (g != null) {
      cancel?.whenCancel.then((_) {
        if (!g.isCompleted) {
          g.completeError(
            DioException(
              requestOptions: RequestOptions(path: 'posts'),
              type: DioExceptionType.cancel,
            ),
          );
        }
      });
      return g.future;
    }
    if (failWith != null) throw failWith!;
    return result;
  }
}

Override spyFeedApi(SpyFeedApi Function(Dio, ApiCache) make) =>
    feedApiProvider.overrideWith(
      (ref) => make(ref.watch(dioProvider), ref.watch(apiCacheProvider)),
    );
