import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:video_player/video_player.dart';

import '../../../app/theme/xm_tokens.dart';
import '../../../core/l10n/app_strings.dart';
import '../data/feed_api.dart';
import '../data/feed_models.dart';

// ---------------------------------------------------------------------------
// Galerie d'images
// ---------------------------------------------------------------------------

/// Texte alternatif lu par le lecteur d'écran : celui de l'auteur·rice, sinon un libellé qui
/// dit la position et l'absence de description.
String imageSemanticLabel(BuildContext c, PostMedia m, int index, int total) =>
    m.alt ??
    c.t('mobile_gallery_image_alt_missing', {'n': index + 1, 'total': total});

/// Images d'une publication : 1 en plein cadre, 2 à 4 en grille. Chaque vignette est un
/// bouton (« Ouvrir en plein écran ») dont le libellé est le texte alternatif.
class PostGallery extends StatelessWidget {
  const PostGallery({super.key, required this.images});
  final List<PostMedia> images;

  @override
  Widget build(BuildContext context) {
    if (images.isEmpty) return const SizedBox.shrink();
    final n = images.length;
    Widget tile(int i, {double? aspect}) => _GalleryTile(
      images: images,
      index: i,
      aspect: aspect,
    );
    Widget body;
    if (n == 1) {
      final m = images.first;
      final ratio = (m.width != null && m.height != null && m.height! > 0)
          ? (m.width! / m.height!).clamp(0.75, 1.9)
          : 16 / 9;
      body = tile(0, aspect: ratio.toDouble());
    } else {
      const gap = 4.0;
      body = LayoutBuilder(
        builder: (context, box) {
          final w = (box.maxWidth - gap) / 2;
          return Wrap(
            spacing: gap,
            runSpacing: gap,
            children: [
              for (var i = 0; i < n; i++)
                SizedBox(
                  width: (n == 3 && i == 0) ? box.maxWidth : w,
                  child: tile(i, aspect: (n == 3 && i == 0) ? 2.0 : 1.0),
                ),
            ],
          );
        },
      );
    }
    return Padding(
      padding: const EdgeInsets.only(top: XmSpace.s2),
      child: Semantics(
        container: true,
        explicitChildNodes: true,
        label: context.t('mobile_gallery_label', {'count': n}),
        child: ClipRRect(
          borderRadius: BorderRadius.circular(XmSpace.radiusMd),
          child: body,
        ),
      ),
    );
  }
}

class _GalleryTile extends StatelessWidget {
  const _GalleryTile({
    required this.images,
    required this.index,
    this.aspect,
  });
  final List<PostMedia> images;
  final int index;
  final double? aspect;

  @override
  Widget build(BuildContext context) {
    final m = images[index];
    return Semantics(
      key: Key('gallery-tile-$index'),
      button: true,
      image: true,
      label: imageSemanticLabel(context, m, index, images.length),
      onTapHint: context.t('mobile_gallery_open_hint'),
      excludeSemantics: true,
      child: InkWell(
        onTap: () => Navigator.of(context, rootNavigator: true).push(
          MaterialPageRoute<void>(
            fullscreenDialog: true,
            builder: (_) => ImageViewerPage(images: images, initial: index),
          ),
        ),
        child: AspectRatio(
          aspectRatio: aspect ?? 1,
          child: _NetImage(url: m.url, fit: BoxFit.cover),
        ),
      ),
    );
  }
}

class _NetImage extends StatelessWidget {
  const _NetImage({required this.url, required this.fit});
  final String url;
  final BoxFit fit;

  @override
  Widget build(BuildContext context) => Image.network(
    url,
    fit: fit,
    width: double.infinity,
    height: double.infinity,
    excludeFromSemantics: true,
    errorBuilder: (c, _, _) => ColoredBox(
      color: c.xm.surfaceMuted,
      child: Center(
        child: Icon(Icons.broken_image_outlined, color: c.xm.textMuted),
      ),
    ),
    loadingBuilder: (c, child, p) => p == null
        ? child
        : ColoredBox(
            color: c.xm.surfaceMuted,
            child: const Center(child: CircularProgressIndicator()),
          ),
  );
}

/// Image en plein écran : glisser ou boutons Précédente / Suivante, pincer pour zoomer,
/// compteur « 2 / 4 » annoncé à chaque changement, texte alternatif sous l'image, Fermer.
class ImageViewerPage extends StatefulWidget {
  const ImageViewerPage({super.key, required this.images, this.initial = 0});
  final List<PostMedia> images;
  final int initial;

  @override
  State<ImageViewerPage> createState() => _ImageViewerPageState();
}

class _ImageViewerPageState extends State<ImageViewerPage> {
  late final PageController _pages = PageController(initialPage: widget.initial);
  late int _index = widget.initial;

  @override
  void dispose() {
    _pages.dispose();
    super.dispose();
  }

  void _go(int i) {
    if (i < 0 || i >= widget.images.length) return;
    _pages.animateToPage(
      i,
      duration: const Duration(milliseconds: 200),
      curve: Curves.easeOut,
    );
  }

  @override
  Widget build(BuildContext context) {
    final n = widget.images.length;
    final m = widget.images[_index];
    final counter = context.t('mobile_gallery_counter', {
      'n': _index + 1,
      'total': n,
    });
    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        backgroundColor: Colors.black,
        foregroundColor: Colors.white,
        automaticallyImplyLeading: false,
        leading: IconButton(
          tooltip: context.t('common_close'),
          constraints: const BoxConstraints(minWidth: 48, minHeight: 48),
          icon: const Icon(Icons.close),
          onPressed: () => Navigator.of(context).maybePop(),
        ),
        title: Semantics(
          liveRegion: true,
          label: context.t('mobile_gallery_viewer_title', {
            'n': _index + 1,
            'total': n,
          }),
          excludeSemantics: true,
          child: Text(counter, key: const Key('viewer-counter')),
        ),
      ),
      body: SafeArea(
        child: Column(
          children: [
            Expanded(
              child: PageView.builder(
                controller: _pages,
                itemCount: n,
                onPageChanged: (i) => setState(() => _index = i),
                itemBuilder: (c, i) => Semantics(
                  image: true,
                  label: imageSemanticLabel(c, widget.images[i], i, n),
                  excludeSemantics: true,
                  child: InteractiveViewer(
                    maxScale: 4,
                    child: Center(
                      child: Image.network(
                        widget.images[i].url,
                        fit: BoxFit.contain,
                        excludeFromSemantics: true,
                        errorBuilder: (c, _, _) => Text(
                          c.t('mobile_gallery_load_failed'),
                          style: const TextStyle(color: Colors.white),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            ),
            if (m.alt != null)
              Padding(
                padding: const EdgeInsets.fromLTRB(
                  XmSpace.gutter,
                  XmSpace.s2,
                  XmSpace.gutter,
                  0,
                ),
                child: Text(
                  m.alt!,
                  key: const Key('viewer-alt'),
                  style: const TextStyle(color: Colors.white),
                ),
              ),
            if (n > 1)
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  IconButton(
                    tooltip: context.t('mobile_gallery_prev'),
                    color: Colors.white,
                    constraints: const BoxConstraints(
                      minWidth: 56,
                      minHeight: 56,
                    ),
                    onPressed: _index == 0 ? null : () => _go(_index - 1),
                    icon: const Icon(Icons.chevron_left, size: 32),
                  ),
                  Text(
                    context.t('mobile_gallery_swipe_hint'),
                    style: const TextStyle(color: Colors.white70, fontSize: 12),
                  ),
                  IconButton(
                    tooltip: context.t('mobile_gallery_next'),
                    color: Colors.white,
                    constraints: const BoxConstraints(
                      minWidth: 56,
                      minHeight: 56,
                    ),
                    onPressed: _index == n - 1 ? null : () => _go(_index + 1),
                    icon: const Icon(Icons.chevron_right, size: 32),
                  ),
                ],
              ),
          ],
        ),
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// Lecteur vidéo
// ---------------------------------------------------------------------------

/// Moteur de lecture, derrière une interface : `video_player` (ExoPlayer / AVPlayer) en production,
/// un faux dans les tests. Jamais de lecture automatique.
abstract interface class VideoEngine implements Listenable {
  Future<void> initialize();
  bool get isInitialized;
  bool get isPlaying;
  bool get hasError;
  Duration get position;
  Duration get duration;
  Future<void> play();
  Future<void> pause();
  Future<void> seekTo(Duration position);
  Widget buildView();
  Future<void> dispose();
}

typedef VideoEngineFactory = VideoEngine Function(Uri url);

/// `video_player` : le lien signé (`GET /media/fil/{ulid}/video?viewer&expires&signature`) se lit
/// sans jeton et accepte `Range` (avance rapide).
class VideoPlayerEngine implements VideoEngine {
  VideoPlayerEngine(Uri url) : _c = VideoPlayerController.networkUrl(url);
  final VideoPlayerController _c;

  @override
  Future<void> initialize() => _c.initialize();
  @override
  bool get isInitialized => _c.value.isInitialized;
  @override
  bool get isPlaying => _c.value.isPlaying;
  @override
  bool get hasError => _c.value.hasError;
  @override
  Duration get position => _c.value.position;
  @override
  Duration get duration => _c.value.duration;
  @override
  Future<void> play() => _c.play();
  @override
  Future<void> pause() => _c.pause();
  @override
  Future<void> seekTo(Duration position) => _c.seekTo(position);
  @override
  Widget buildView() => AspectRatio(
    aspectRatio: _c.value.aspectRatio > 0 ? _c.value.aspectRatio : 16 / 9,
    child: VideoPlayer(_c),
  );
  @override
  void addListener(VoidCallback listener) => _c.addListener(listener);
  @override
  void removeListener(VoidCallback listener) => _c.removeListener(listener);
  @override
  Future<void> dispose() => _c.dispose();
}

final videoEngineFactoryProvider = Provider<VideoEngineFactory>(
  (ref) => VideoPlayerEngine.new,
);

/// Une seule vidéo à la fois : quand une lecture démarre, les autres se mettent en pause.
final ValueNotifier<Object?> _activePlayer = ValueNotifier<Object?>(null);

String formatDuration(Duration d) {
  final h = d.inHours;
  final m = d.inMinutes.remainder(60);
  final s = d.inSeconds.remainder(60).toString().padLeft(2, '0');
  return h > 0 ? '$h:${m.toString().padLeft(2, '0')}:$s' : '$m:$s';
}

/// Lecture orale d'une durée : « 1 minute 30 secondes ».
String spokenDuration(BuildContext c, Duration d) =>
    c.t('mobile_video_spoken', {
      'minutes': d.inMinutes,
      'seconds': d.inSeconds.remainder(60),
    });

/// Vidéo d'une publication : image d'attente + bouton « Lire la vidéo » (le lecteur n'est créé et
/// le fichier n'est demandé qu'au premier appui : le lien signé dure 15 min et rien n'est
/// téléchargé pour rien), puis lecture / pause, barre de position, durée, plein écran. La
/// description de la vidéo reste affichée en légende. Le contrôleur est libéré à la fermeture.
class PostVideoPlayer extends ConsumerStatefulWidget {
  const PostVideoPlayer({super.key, required this.postId, required this.media});
  final String postId;
  final PostMedia media;

  @override
  ConsumerState<PostVideoPlayer> createState() => _PostVideoPlayerState();
}

class _PostVideoPlayerState extends ConsumerState<PostVideoPlayer>
    with WidgetsBindingObserver {
  VideoEngine? _engine;
  bool _loading = false;
  bool _failed = false;
  String _status = '';

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _activePlayer.addListener(_onActive);
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _activePlayer.removeListener(_onActive);
    if (identical(_activePlayer.value, this)) _activePlayer.value = null;
    _release();
    super.dispose();
  }

  void _release() {
    final e = _engine;
    _engine = null;
    if (e != null) {
      e.removeListener(_tick);
      unawaited(e.dispose());
    }
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state != AppLifecycleState.resumed) unawaited(_engine?.pause());
  }

  void _onActive() {
    if (!identical(_activePlayer.value, this) && (_engine?.isPlaying ?? false)) {
      unawaited(_engine!.pause());
    }
  }

  void _tick() {
    if (mounted) setState(() {});
  }

  Future<void> _start({bool fresh = false}) async {
    setState(() {
      _loading = true;
      _failed = false;
      _status = context.t('mobile_video_loading');
    });
    var url = widget.media.url;
    try {
      if (fresh) {
        // Le lien signé a expiré (15 min) : on redemande la publication pour en obtenir un neuf.
        final p = await ref.read(feedApiProvider).post(widget.postId);
        url = p.video?.url ?? url;
      }
      _release();
      final engine = ref.read(videoEngineFactoryProvider)(Uri.parse(url));
      _engine = engine;
      engine.addListener(_tick);
      await engine.initialize();
      if (!mounted) return;
      if (engine.hasError) throw StateError('video');
      _activePlayer.value = this;
      await engine.play();
      if (!mounted) return;
      setState(() {
        _loading = false;
        _status = context.t('mobile_video_playing');
      });
    } catch (_) {
      _release();
      if (!mounted) return;
      setState(() {
        _loading = false;
        _failed = true;
        _status = context.t('mobile_video_error');
      });
    }
  }

  Future<void> _toggle() async {
    final e = _engine;
    if (e == null) return;
    if (e.isPlaying) {
      await e.pause();
      if (mounted) setState(() => _status = context.t('mobile_video_paused'));
    } else {
      _activePlayer.value = this;
      await e.play();
      if (mounted) setState(() => _status = context.t('mobile_video_playing'));
    }
  }

  void _fullscreen() {
    final e = _engine;
    if (e == null) return;
    Navigator.of(context, rootNavigator: true).push(
      MaterialPageRoute<void>(
        fullscreenDialog: true,
        builder: (_) => VideoFullscreenPage(
          engine: e,
          description: widget.media.alt,
          onToggle: _toggle,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final desc = widget.media.alt;
    final e = _engine;
    final ready = e != null && e.isInitialized && !_failed;
    return Padding(
      padding: const EdgeInsets.only(top: XmSpace.s2),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Semantics(
            container: true,
            explicitChildNodes: true,
            label: context.t('mobile_video_region', {
              'description': desc ?? '',
            }),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(XmSpace.radiusMd),
              child: ColoredBox(
                color: Colors.black,
                child: ready
                    ? _controls(context, e)
                    : _poster(context),
              ),
            ),
          ),
          // Statut annoncé (chargement, lecture, pause, erreur).
          Semantics(
            liveRegion: true,
            child: _status.isEmpty
                ? const SizedBox.shrink()
                : Padding(
                    padding: const EdgeInsets.only(top: XmSpace.s1),
                    child: Text(
                      _status,
                      key: const Key('video-status'),
                      style: Theme.of(context).textTheme.bodySmall,
                    ),
                  ),
          ),
          if (desc != null)
            Padding(
              padding: const EdgeInsets.only(top: XmSpace.s1),
              child: Text(
                desc,
                key: const Key('video-description'),
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ),
        ],
      ),
    );
  }

  Widget _poster(BuildContext context) => AspectRatio(
    aspectRatio: 16 / 9,
    child: Center(
      child: _loading
          ? const CircularProgressIndicator()
          : Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                IconButton.filled(
                  key: const Key('video-play'),
                  tooltip: context.t(
                    _failed ? 'common_retry' : 'mobile_video_play',
                  ),
                  constraints: const BoxConstraints(
                    minWidth: 64,
                    minHeight: 64,
                  ),
                  iconSize: 36,
                  onPressed: () => _start(fresh: _failed),
                  icon: Icon(_failed ? Icons.refresh : Icons.play_arrow),
                ),
                const SizedBox(height: XmSpace.s2),
                Text(
                  context.t(_failed ? 'common_retry' : 'mobile_video_play'),
                  style: const TextStyle(color: Colors.white),
                ),
              ],
            ),
    ),
  );

  Widget _controls(BuildContext context, VideoEngine e) {
    final pos = e.position;
    final dur = e.duration;
    final max = dur.inMilliseconds.toDouble();
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        e.buildView(),
        Row(
          children: [
            IconButton(
              key: const Key('video-toggle'),
              tooltip: context.t(
                e.isPlaying ? 'mobile_video_pause' : 'mobile_video_play',
              ),
              color: Colors.white,
              constraints: const BoxConstraints(minWidth: 48, minHeight: 48),
              icon: Icon(e.isPlaying ? Icons.pause : Icons.play_arrow),
              onPressed: _toggle,
            ),
            Expanded(
              child: Semantics(
                label: context.t('mobile_video_seek'),
                value: context.t('mobile_video_position', {
                  'position': spokenDuration(context, pos),
                  'duration': spokenDuration(context, dur),
                }),
                excludeSemantics: true,
                child: Slider(
                  key: const Key('video-seek'),
                  value: pos.inMilliseconds.toDouble().clamp(0, max <= 0 ? 1 : max),
                  max: max <= 0 ? 1 : max,
                  onChanged: max <= 0
                      ? null
                      : (v) => e.seekTo(Duration(milliseconds: v.round())),
                ),
              ),
            ),
            Text(
              '${formatDuration(pos)} / ${formatDuration(dur)}',
              key: const Key('video-time'),
              style: const TextStyle(color: Colors.white, fontSize: 12),
            ),
            IconButton(
              key: const Key('video-fullscreen'),
              tooltip: context.t('mobile_video_fullscreen'),
              color: Colors.white,
              constraints: const BoxConstraints(minWidth: 48, minHeight: 48),
              icon: const Icon(Icons.fullscreen),
              onPressed: _fullscreen,
            ),
          ],
        ),
      ],
    );
  }
}

/// Plein écran : le même moteur, sans second chargement ; Fermer ramène à la publication.
class VideoFullscreenPage extends StatefulWidget {
  const VideoFullscreenPage({
    super.key,
    required this.engine,
    this.description,
    required this.onToggle,
  });
  final VideoEngine engine;
  final String? description;
  final Future<void> Function() onToggle;

  @override
  State<VideoFullscreenPage> createState() => _VideoFullscreenPageState();
}

class _VideoFullscreenPageState extends State<VideoFullscreenPage> {
  @override
  void initState() {
    super.initState();
    widget.engine.addListener(_tick);
  }

  @override
  void dispose() {
    widget.engine.removeListener(_tick);
    super.dispose();
  }

  void _tick() {
    if (mounted) setState(() {});
  }

  @override
  Widget build(BuildContext context) {
    final e = widget.engine;
    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        backgroundColor: Colors.black,
        foregroundColor: Colors.white,
        automaticallyImplyLeading: false,
        leading: IconButton(
          key: const Key('video-fullscreen-close'),
          tooltip: context.t('mobile_video_exit_fullscreen'),
          constraints: const BoxConstraints(minWidth: 48, minHeight: 48),
          icon: const Icon(Icons.close),
          onPressed: () => Navigator.of(context).maybePop(),
        ),
      ),
      body: SafeArea(
        child: Column(
          children: [
            Expanded(child: Center(child: e.buildView())),
            if (widget.description != null)
              Padding(
                padding: const EdgeInsets.all(XmSpace.gutter),
                child: Text(
                  widget.description!,
                  style: const TextStyle(color: Colors.white),
                ),
              ),
            IconButton(
              tooltip: context.t(
                e.isPlaying ? 'mobile_video_pause' : 'mobile_video_play',
              ),
              color: Colors.white,
              constraints: const BoxConstraints(minWidth: 56, minHeight: 56),
              iconSize: 32,
              icon: Icon(e.isPlaying ? Icons.pause : Icons.play_arrow),
              onPressed: widget.onToggle,
            ),
          ],
        ),
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// État de relecture (auteur·rice)
// ---------------------------------------------------------------------------

/// « En attente de relecture » / « Vidéo refusée » + motif + « Retirer ». Affiché à l'auteur·rice
/// seulement : le serveur ne renvoie `review_state` et `review_reason` qu'à elle (et à la modération).
class ReviewPanel extends StatelessWidget {
  const ReviewPanel({super.key, required this.post, required this.onWithdraw});
  final Post post;
  final Future<void> Function() onWithdraw;

  @override
  Widget build(BuildContext context) {
    final state = post.reviewState;
    if (state != kReviewPending && state != kReviewRejected) {
      return const SizedBox.shrink();
    }
    final rejected = state == kReviewRejected;
    final tone = rejected ? context.xm.danger : context.xm.warning;
    final label =
        post.reviewLabel ??
        context.t(rejected ? 'feed_video_states_rejected' : 'feed_video_states_pending');
    return Padding(
      padding: const EdgeInsets.only(top: XmSpace.s2),
      child: Semantics(
        container: true,
        explicitChildNodes: true,
        child: Container(
          key: Key('review-${post.id}'),
          padding: const EdgeInsets.all(XmSpace.s3),
          decoration: BoxDecoration(
            color: tone.withValues(alpha: 0.10),
            borderRadius: BorderRadius.circular(XmSpace.radiusMd),
            border: Border.all(color: tone.withValues(alpha: 0.5)),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Icon(
                    rejected ? Icons.block : Icons.hourglass_top,
                    size: 20,
                    color: tone,
                  ),
                  const SizedBox(width: XmSpace.s2),
                  Expanded(
                    child: Text(
                      label,
                      key: const Key('review-label'),
                      style: Theme.of(context).textTheme.titleSmall,
                    ),
                  ),
                ],
              ),
              if (rejected &&
                  post.reviewReason != null &&
                  post.reviewReason!.isNotEmpty)
                Padding(
                  padding: const EdgeInsets.only(top: XmSpace.s1),
                  child: Text(
                    post.reviewReason!,
                    key: const Key('review-reason'),
                  ),
                ),
              Padding(
                padding: const EdgeInsets.only(top: XmSpace.s1),
                child: Text(
                  context.t(
                    rejected
                        ? 'mobile_review_rejected_help'
                        : 'mobile_review_pending_help',
                  ),
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              ),
              if (post.canDelete)
                Align(
                  alignment: AlignmentDirectional.centerEnd,
                  child: TextButton.icon(
                    key: const Key('review-withdraw'),
                    style: TextButton.styleFrom(
                      minimumSize: const Size(48, 48),
                    ),
                    icon: const Icon(Icons.delete_outline),
                    label: Text(context.t('mobile_review_remove')),
                    onPressed: () async {
                      final ok = await showDialog<bool>(
                        context: context,
                        builder: (c) => AlertDialog(
                          title: Text(c.t('mobile_review_remove_confirm_title')),
                          content: Text(c.t('mobile_review_remove_confirm_body')),
                          actions: [
                            TextButton(
                              onPressed: () => Navigator.pop(c, false),
                              child: Text(c.t('common_cancel')),
                            ),
                            FilledButton(
                              onPressed: () => Navigator.pop(c, true),
                              child: Text(c.t('mobile_review_remove')),
                            ),
                          ],
                        ),
                      );
                      if (ok == true) await onWithdraw();
                    },
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}
