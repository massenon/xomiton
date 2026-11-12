import 'dart:async';
import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:uuid/uuid.dart';

import '../../../app/theme/xm_tokens.dart';
import '../../../core/emoji/emoji_logic.dart';
import '../../../core/emoji/emoji_picker_sheet.dart';
import '../../../core/l10n/app_strings.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/ui/xm_widgets.dart';
import '../../auth/application/pending_validation.dart';
import '../../auth/presentation/pending_widgets.dart';
import '../application/feed_controller.dart';
import '../data/compose_logic.dart';
import '../data/media_picker.dart';

class _ImageEntry {
  _ImageEntry(this.image) : alt = TextEditingController();
  final ComposeImage image;
  final TextEditingController alt;
}

/// Publication ou sondage (docs/API.md §8.3, §8.7).
///
/// - Texte et sondage **sans média** : boîte d'envoi (hors connexion, file locale, rejeu sans doublon).
/// - Images (jusqu'à 4, texte alternatif par image, ordre au choix) et vidéo (une seule, description
///   obligatoire, relue avant parution) : envoi direct en multipart, **réseau obligatoire** ; le
///   brouillon reste à l'écran en cas d'échec. Les fichiers ne sont jamais gardés hors ligne.
class CreatePostPage extends ConsumerStatefulWidget {
  const CreatePostPage({super.key});

  @override
  ConsumerState<CreatePostPage> createState() => _CreatePostPageState();
}

class _CreatePostPageState extends ConsumerState<CreatePostPage> {
  final _scroll = ScrollController();
  final _body = TextEditingController();
  final _question = TextEditingController();
  final _videoDesc = TextEditingController();
  final _options = [TextEditingController(), TextEditingController()];
  final _images = <_ImageEntry>[];
  ComposeVideo? _video;
  bool _poll = false;
  bool _anonymous = false;
  bool _multiple = false;
  bool _resultsBeforeVote = false;
  int _duration = FeedLimits.defaultDuration;
  String _visibility = 'public';
  Map<String, String> _errors = {};
  List<String> _general = [];
  bool _busy = false;
  int? _progress;
  int _announcedStep = 0;
  CancelToken? _cancel;
  bool _cancelledByUser = false;
  String _live = '';
  String? _lastFingerprint;
  String? _lastKey;

  @override
  void dispose() {
    _scroll.dispose();
    _body.dispose();
    _question.dispose();
    _videoDesc.dispose();
    for (final o in _options) {
      o.dispose();
    }
    for (final i in _images) {
      i.alt.dispose();
    }
    super.dispose();
  }

  /// Retour au fil (ouverture directe de l'écran, lien profond : rien à dépiler).
  void _leave() {
    if (context.canPop()) {
      context.pop();
    } else {
      context.go('/feed');
    }
  }

  void _announce(String text) {
    if (mounted) setState(() => _live = text);
  }

  void _toTop() {
    if (_scroll.hasClients) {
      _scroll.animateTo(
        0,
        duration: const Duration(milliseconds: 200),
        curve: Curves.easeOut,
      );
    }
  }

  ComposeInput _input() => ComposeInput(
    poll: _poll,
    body: _body.text,
    question: _question.text,
    options: [for (final o in _options) o.text],
    multiple: _multiple,
    resultsBeforeVote: _resultsBeforeVote,
    duration: _duration,
    visibility: _visibility,
    anonymous: _anonymous,
    images: [for (final i in _images) i.image.withAlt(i.alt.text)],
    video: _poll ? null : _video,
    videoDescription: _videoDesc.text,
  );

  // --- sondage -------------------------------------------------------------

  void _addOption() {
    if (_options.length >= FeedLimits.optionsMax) {
      _announce(
        context.t('mobile_compose_poll_max_reached', {
          'max': FeedLimits.optionsMax,
        }),
      );
      return;
    }
    setState(() => _options.add(TextEditingController()));
    _announce(
      context.t('mobile_compose_poll_option_added', {
        'n': _options.length,
        'count': _options.length,
        'max': FeedLimits.optionsMax,
      }),
    );
  }

  void _removeOption(int i) {
    if (_options.length <= FeedLimits.optionsMin) return;
    setState(() {
      _options.removeAt(i).dispose();
      _errors = {
        for (final e in _errors.entries)
          if (!e.key.startsWith('poll.options.row')) e.key: e.value,
      };
    });
    _announce(
      context.t('mobile_compose_poll_option_removed', {'count': _options.length}),
    );
  }

  void _setPoll(bool v) {
    setState(() {
      _poll = v;
      _errors = {};
      _general = [];
    });
    if (v && _video != null) {
      setState(() {
        _video = null;
        _videoDesc.clear();
      });
      _announce(context.t('mobile_compose_video_removed_for_poll'));
    }
  }

  // --- images --------------------------------------------------------------

  Future<void> _pickImages() async {
    final picked = await ref.read(mediaPickerProvider).pickImages();
    if (!mounted || picked.isEmpty) return;
    final known = _images.map((i) => i.image.path).toSet();
    final fresh = picked.where((p) => !known.contains(p.path)).toList();
    final room = FeedLimits.maxImages - _images.length;
    final kept = fresh.take(room).toList();
    setState(() {
      _images.addAll(kept.map(_ImageEntry.new));
      _errors = {
        for (final e in _errors.entries)
          if (!e.key.startsWith('images')) e.key: e.value,
      };
    });
    _announce(
      fresh.length > room
          ? context.t('mobile_compose_images_trimmed', {
              'max': FeedLimits.maxImages,
            })
          : context.t('mobile_compose_image_added', {'count': kept.length}),
    );
  }

  void _moveImage(int i, int delta) {
    final j = i + delta;
    if (j < 0 || j >= _images.length) return;
    setState(() {
      final e = _images.removeAt(i);
      _images.insert(j, e);
      _errors = {
        for (final e in _errors.entries)
          if (!e.key.startsWith('images')) e.key: e.value,
      };
    });
    _announce(
      context.t('mobile_compose_image_moved', {
        'pos': j + 1,
        'total': _images.length,
      }),
    );
  }

  void _removeImage(int i) {
    setState(() {
      _images.removeAt(i).alt.dispose();
      _errors = {
        for (final e in _errors.entries)
          if (!e.key.startsWith('images')) e.key: e.value,
      };
    });
    _announce(
      context.t('mobile_compose_image_removed', {
        'count': _images.length,
        'max': FeedLimits.maxImages,
      }),
    );
  }

  // --- vidéo ---------------------------------------------------------------

  Future<void> _pickVideo() async {
    final v = await ref.read(mediaPickerProvider).pickVideo();
    if (!mounted || v == null) return;
    setState(() {
      _video = v;
      _errors.remove('video');
    });
    _announce(context.t('mobile_compose_video_picked'));
  }

  void _removeVideo() {
    setState(() {
      _video = null;
      _videoDesc.clear();
      _errors.remove('video');
      _errors.remove('video_description');
    });
    _announce(context.t('mobile_compose_video_removed'));
  }

  Future<bool> _confirmPrivacy() async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        title: Text(c.t('mobile_compose_privacy_title')),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(c.t('feed_video_privacy_notice'), key: const Key('privacy-notice')),
              const SizedBox(height: XmSpace.s3),
              Text(c.t('feed_video_review_notice')),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(c, false),
            child: Text(c.t('common_cancel')),
          ),
          FilledButton(
            key: const Key('privacy-confirm'),
            onPressed: () => Navigator.pop(c, true),
            child: Text(c.t('mobile_compose_privacy_confirm')),
          ),
        ],
      ),
    );
    return ok == true;
  }

  // --- envoi ---------------------------------------------------------------

  String _fingerprint(ComposeInput c) => [
    c.poll,
    c.body.trim(),
    c.question.trim(),
    c.sentOptions.join('|'),
    c.multiple,
    c.resultsBeforeVote,
    c.duration,
    c.visibility,
    c.anonymous,
    for (final i in c.images) '${i.path}:${i.size}:${i.alt.trim()}',
    '${c.video?.path}:${c.video?.size}',
    c.videoDescription.trim(),
  ].join('\u0001');

  Future<void> _submit() async {
    final input = _input();
    final errors = validateCompose(input, context.t);
    if (errors.isNotEmpty) {
      setState(() {
        _errors = errors;
        _general = [];
      });
      _announce(
        '${context.t('feed_create_errors_title')} ${errors.values.first}',
      );
      _toTop();
      return;
    }
    if (input.video != null && !await _confirmPrivacy()) return;
    if (!mounted) return;
    setState(() {
      _busy = true;
      _errors = {};
      _general = [];
      _progress = null;
      _announcedStep = 0;
      _cancelledByUser = false;
    });
    try {
      if (input.hasMedia) {
        final fp = _fingerprint(input);
        if (fp != _lastFingerprint || _lastKey == null) {
          _lastFingerprint = fp;
          _lastKey = const Uuid().v4();
        }
        _cancel = CancelToken();
        final r = await ref
            .read(feedProvider.notifier)
            .publishWithMedia(
              input,
              idempotencyKey: _lastKey,
              cancel: _cancel,
              onProgress: _onProgress,
            );
        if (!mounted) return;
        final String msg;
        if (r.processing) {
          msg = context.t('mobile_compose_processing');
        } else if (r.inReview) {
          msg = context.t('feed_video_submitted');
        } else {
          msg = context.t(
            _poll ? 'feed_toasts_poll_published' : 'feed_toasts_published',
          );
        }
        _lastFingerprint = null;
        _lastKey = null;
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg)));
        _leave();
      } else {
        final payload = <String, dynamic>{
          'kind': _poll ? 'poll' : 'post',
          if (input.body.trim().isNotEmpty) 'body': input.body.trim(),
          'visibility': _visibility,
          'is_anonymous': _anonymous,
          if (_poll)
            'poll': {
              'question': input.question.trim(),
              'options': input.sentOptions,
              'multiple': _multiple,
              'results_before_vote': _resultsBeforeVote,
              'duration_days': _duration,
            },
        };
        final online = await ref.read(feedProvider.notifier).publish(payload);
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              context.t(
                online
                    ? (_poll
                          ? 'feed_toasts_poll_published'
                          : 'feed_toasts_published')
                    : 'microcopy_offline_queued',
              ),
            ),
          ),
        );
        _leave();
      }
    } catch (e) {
      if (!mounted) return;
      _handleError(e, input);
    } finally {
      _cancel = null;
      if (mounted) setState(() => _busy = false);
    }
  }

  void _onProgress(int sent, int total) {
    if (!mounted || total <= 0) return;
    final pct = (sent * 100 ~/ total).clamp(0, 100);
    setState(() => _progress = pct);
    for (final step in const [25, 50, 75, 100]) {
      if (pct >= step && _announcedStep < step) {
        _announcedStep = step;
        _announce(context.t('mobile_compose_upload_progress', {'percent': step}));
      }
    }
  }

  void _cancelUpload() {
    _cancelledByUser = true;
    _cancel?.cancel();
  }

  void _handleError(Object e, ComposeInput sent) {
    if (_cancelledByUser) {
      _announce(context.t('mobile_compose_upload_cancelled'));
      setState(() => _general = [context.t('mobile_compose_upload_cancelled')]);
      _toTop();
      return;
    }
    final api = ApiException.from(e);
    final general = <String>[];
    var fields = <String, String>{};
    if (api.isNetwork) {
      general.add(
        context.t(
          sent.hasMedia
              ? 'mobile_compose_media_needs_network'
              : 'microcopy_error_unreachable',
        ),
      );
    } else if (api.isValidation && api.errors.isNotEmpty) {
      final m = mapComposeErrors(api.errors, sent);
      fields = m.fields;
      general.addAll(m.rest);
    } else if (api.code == 'content_blocked' || api.code == 'content_rejected') {
      general.add(context.t('feed_create_bad_words'));
    } else {
      general.add(errorMessage(context, e));
    }
    setState(() {
      _errors = fields;
      _general = general;
    });
    _announce('${context.t('feed_create_errors_title')} ${[...fields.values, ...general].first}');
    _toTop();
  }

  // --- interface -----------------------------------------------------------

  @override
  Widget build(BuildContext context) {
    if (ref.watch(pendingValidationProvider)) {
      return Scaffold(
        appBar: AppBar(title: Text(context.t('feed_create_title'))),
        body: const SafeArea(child: PendingNotice()),
      );
    }
    final messages = [..._general, ..._errors.values];
    return Scaffold(
      appBar: AppBar(
        title: Text(context.t(_poll ? 'feed_poll_title' : 'feed_create_title')),
      ),
      body: SafeArea(
        child: Column(
          children: [
            // Région « live » hors de la liste (qui ne construit que ce qui est visible) :
            // annonces d'ajout, de retrait, de déplacement et de progression.
            Semantics(
              liveRegion: true,
              container: true,
              child: _live.isEmpty
                  ? const SizedBox.shrink()
                  : Container(
                      key: const Key('compose-live'),
                      width: double.infinity,
                      color: context.xm.primarySubtle,
                      padding: const EdgeInsets.symmetric(
                        horizontal: XmSpace.gutter,
                        vertical: XmSpace.s1,
                      ),
                      child: Text(
                        _live,
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                    ),
            ),
            Expanded(
              child: ListView(
                key: const Key('compose-list'),
                controller: _scroll,
                padding: const EdgeInsets.all(XmSpace.gutter),
                children: [
                  SegmentedButton<bool>(
                    segments: [
                      ButtonSegment(
                        value: false,
                        label: Text(context.t('feed_create_title')),
                      ),
                      ButtonSegment(
                        value: true,
                        label: Text(context.t('feed_compose_poll')),
                      ),
                    ],
                    selected: {_poll},
                    onSelectionChanged: _busy ? null : (s) => _setPoll(s.first),
                  ),
                  const SizedBox(height: XmSpace.s3),
                  Text(
                    context.t('feed_create_help_notice'),
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                  const SizedBox(height: XmSpace.s3),
                  if (messages.isNotEmpty) ...[
                    _ErrorSummary(
                      title: context.t('feed_create_errors_title'),
                      messages: messages,
                    ),
                    const SizedBox(height: XmSpace.s3),
                  ],
                  if (_poll) ..._pollFields(context),
                  TextField(
                    key: const Key('compose-body'),
                    controller: _body,
                    minLines: 4,
                    maxLines: 8,
                    maxLength: FeedLimits.bodyMax,
                    inputFormatters: const [
                      CodePointLimitFormatter(FeedLimits.bodyMax),
                    ],
                    decoration: InputDecoration(
                      suffixIcon: EmojiButton(
                        controller: _body,
                        maxLength: FeedLimits.bodyMax,
                      ),
                      labelText: context.t(
                        _poll ? 'feed_poll_text' : 'feed_create_text',
                      ),
                      hintText: context.t(
                        _poll
                            ? 'feed_poll_text_placeholder'
                            : 'feed_create_text_placeholder',
                      ),
                      errorText: _errors['body'],
                    ),
                  ),
                  const SizedBox(height: XmSpace.s3),
                  ..._imageSection(context),
                  if (!_poll) ..._videoSection(context),
                  DropdownButtonFormField<String>(
                    isExpanded: true,
                    initialValue: _visibility,
                    decoration: InputDecoration(
                      labelText: context.t('feed_create_visibility'),
                      errorText: _errors['visibility'],
                    ),
                    items: [
                      for (final v in const ['public', 'followers', 'me'])
                        DropdownMenuItem(
                          value: v,
                          child: Text(
                            context.t('feed_create_visibility_options_$v'),
                          ),
                        ),
                    ],
                    onChanged: (v) =>
                        setState(() => _visibility = v ?? 'public'),
                  ),
                  SwitchListTile.adaptive(
                    contentPadding: EdgeInsets.zero,
                    value: _anonymous,
                    title: Text(context.t('feed_create_anonymous')),
                    subtitle: Text(context.t('feed_create_anonymous_help')),
                    onChanged: (v) => setState(() => _anonymous = v),
                  ),
                  const SizedBox(height: XmSpace.s3),
                  if (_busy && _progress != null) _uploadBar(context),
                  FilledButton(
                    key: const Key('compose-submit'),
                    style: FilledButton.styleFrom(
                      minimumSize: const Size.fromHeight(52),
                    ),
                    onPressed: _busy ? null : _submit,
                    child: Text(
                      context.t(
                        _poll ? 'feed_poll_submit' : 'feed_create_submit',
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _uploadBar(BuildContext context) {
    final pct = _progress ?? 0;
    return Padding(
      padding: const EdgeInsets.only(bottom: XmSpace.s3),
      child: Semantics(
        container: true,
        label: context.t('mobile_compose_upload_progress', {'percent': pct}),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            ExcludeSemantics(
              child: LinearProgressIndicator(
                key: const Key('compose-progress'),
                value: pct / 100,
              ),
            ),
            const SizedBox(height: XmSpace.s1),
            Row(
              children: [
                Expanded(
                  child: Text(
                    context.t('mobile_compose_upload_progress', {
                      'percent': pct,
                    }),
                    key: const Key('compose-progress-text'),
                  ),
                ),
                TextButton(
                  key: const Key('compose-cancel-upload'),
                  style: TextButton.styleFrom(minimumSize: const Size(48, 48)),
                  onPressed: _cancelUpload,
                  child: Text(context.t('mobile_compose_upload_cancel')),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  List<Widget> _pollFields(BuildContext context) {
    final rows = _options.length;
    return [
      TextField(
        key: const Key('compose-question'),
        controller: _question,
        maxLength: FeedLimits.questionMax,
        inputFormatters: const [CodePointLimitFormatter(FeedLimits.questionMax)],
        decoration: InputDecoration(
          labelText: context.t('feed_poll_question'),
          hintText: context.t('feed_poll_question_placeholder'),
          suffixIcon: EmojiButton(
            controller: _question,
            maxLength: FeedLimits.questionMax,
          ),
          errorText: _errors['poll.question'],
        ),
      ),
      const SizedBox(height: XmSpace.s2),
      Semantics(
        header: true,
        child: Text(
          '${context.t('feed_poll_options')} ${context.t('feed_poll_options_hint', {'min': FeedLimits.optionsMin, 'max': FeedLimits.optionsMax})}',
          style: Theme.of(context).textTheme.titleSmall,
        ),
      ),
      if (_errors['poll.options'] != null)
        Padding(
          padding: const EdgeInsets.only(top: XmSpace.s1),
          child: Text(
            _errors['poll.options']!,
            style: TextStyle(color: context.xm.danger),
          ),
        ),
      const SizedBox(height: XmSpace.s2),
      for (var i = 0; i < rows; i++)
        Padding(
          padding: const EdgeInsets.only(bottom: XmSpace.s2),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: TextField(
                  key: Key('compose-option-$i'),
                  controller: _options[i],
                  maxLength: FeedLimits.optionMax,
                  inputFormatters: const [
                    CodePointLimitFormatter(FeedLimits.optionMax),
                  ],
                  decoration: InputDecoration(
                    labelText: context.t('feed_poll_option_label', {
                      'n': i + 1,
                    }),
                    counterText: '',
                    errorText: _errors['poll.options.row$i'],
                  ),
                ),
              ),
              if (rows > FeedLimits.optionsMin)
                IconButton(
                  key: Key('compose-remove-option-$i'),
                  tooltip: context.t('feed_poll_option_remove', {'n': i + 1}),
                  constraints: const BoxConstraints(
                    minWidth: 48,
                    minHeight: 48,
                  ),
                  icon: const Icon(Icons.remove_circle_outline),
                  onPressed: () => _removeOption(i),
                ),
            ],
          ),
        ),
      if (rows < FeedLimits.optionsMax)
        TextButton.icon(
          key: const Key('compose-add-option'),
          icon: const Icon(Icons.add),
          label: Text(context.t('feed_poll_option_add')),
          onPressed: _addOption,
        )
      else
        Text(
          context.t('mobile_compose_poll_max_reached', {
            'max': FeedLimits.optionsMax,
          }),
          style: Theme.of(context).textTheme.bodySmall,
        ),
      SwitchListTile.adaptive(
        contentPadding: EdgeInsets.zero,
        value: _multiple,
        title: Text(context.t('feed_poll_multiple')),
        onChanged: (v) => setState(() => _multiple = v),
      ),
      SwitchListTile.adaptive(
        contentPadding: EdgeInsets.zero,
        value: _resultsBeforeVote,
        title: Text(context.t('feed_poll_results_before_vote')),
        onChanged: (v) => setState(() => _resultsBeforeVote = v),
      ),
      DropdownButtonFormField<int>(
        isExpanded: true,
        initialValue: _duration,
        decoration: InputDecoration(
          labelText: context.t('feed_poll_duration'),
          errorText: _errors['poll.duration_days'],
        ),
        items: [
          for (final d in FeedLimits.durations)
            DropdownMenuItem(
              value: d,
              child: Text(context.t('feed_poll_durations_$d')),
            ),
        ],
        onChanged: (v) =>
            setState(() => _duration = v ?? FeedLimits.defaultDuration),
      ),
      const SizedBox(height: XmSpace.s1),
      Text(
        context.t('feed_poll_privacy'),
        style: Theme.of(context).textTheme.bodySmall,
      ),
      const SizedBox(height: XmSpace.s3),
    ];
  }

  List<Widget> _imageSection(BuildContext context) {
    final n = _images.length;
    final full = n >= FeedLimits.maxImages;
    final blockedByVideo = _video != null;
    return [
      Row(
        children: [
          Expanded(
            child: Semantics(
              header: true,
              child: Text(
                context.t('mobile_compose_images_title'),
                style: Theme.of(context).textTheme.titleSmall,
              ),
            ),
          ),
          Semantics(
            label: context.t('mobile_compose_images_counter_sr', {
              'count': n,
              'max': FeedLimits.maxImages,
            }),
            excludeSemantics: true,
            child: Text(
              context.t('mobile_compose_images_counter', {
                'count': n,
                'max': FeedLimits.maxImages,
              }),
              key: const Key('compose-images-counter'),
            ),
          ),
        ],
      ),
      Text(
        context.t('mobile_compose_images_help', {
          'max': FeedLimits.imageMaxBytes ~/ (1024 * 1024),
        }),
        style: Theme.of(context).textTheme.bodySmall,
      ),
      if (_errors['images'] != null)
        Text(_errors['images']!, style: TextStyle(color: context.xm.danger)),
      for (var i = 0; i < n; i++) _imageTile(context, i),
      Align(
        alignment: AlignmentDirectional.centerStart,
        child: OutlinedButton.icon(
          key: const Key('compose-add-images'),
          style: OutlinedButton.styleFrom(minimumSize: const Size(48, 48)),
          icon: const Icon(Icons.add_photo_alternate_outlined),
          label: Text(context.t('mobile_compose_images_add')),
          onPressed: (full || blockedByVideo || _busy) ? null : _pickImages,
        ),
      ),
      if (blockedByVideo)
        Text(
          context.t('mobile_compose_images_excl_video'),
          style: Theme.of(context).textTheme.bodySmall,
        )
      else if (full)
        Text(
          context.t('mobile_compose_images_full', {
            'max': FeedLimits.maxImages,
          }),
          style: Theme.of(context).textTheme.bodySmall,
        ),
      const SizedBox(height: XmSpace.s3),
    ];
  }

  Widget _imageTile(BuildContext context, int i) {
    final e = _images[i];
    final n = _images.length;
    final err = _errors['images.$i'];
    return Card(
      key: Key('compose-image-$i'),
      margin: const EdgeInsets.only(top: XmSpace.s2),
      child: Padding(
        padding: const EdgeInsets.all(XmSpace.s2),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Semantics(
                  image: true,
                  label: context.t('mobile_compose_image_preview', {
                    'n': i + 1,
                    'name': e.image.name,
                  }),
                  excludeSemantics: true,
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(XmSpace.radiusSm),
                    child: SizedBox(
                      width: 72,
                      height: 72,
                      child: Image.file(
                        File(e.image.path),
                        fit: BoxFit.cover,
                        cacheWidth: 216,
                        excludeFromSemantics: true,
                        errorBuilder: (c, _, _) => ColoredBox(
                          color: c.xm.surfaceMuted,
                          child: const Icon(Icons.image_outlined),
                        ),
                      ),
                    ),
                  ),
                ),
                const SizedBox(width: XmSpace.s2),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        context.t('mobile_compose_image_position', {
                          'n': i + 1,
                          'total': n,
                        }),
                        style: Theme.of(context).textTheme.titleSmall,
                      ),
                      Text(
                        '${e.image.name} · ${formatBytes(e.image.size)}',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                    ],
                  ),
                ),
              ],
            ),
            if (err != null)
              Text(err, style: TextStyle(color: context.xm.danger)),
            TextField(
              key: Key('compose-image-alt-$i'),
              controller: e.alt,
              maxLength: FeedLimits.altMax,
              inputFormatters: const [CodePointLimitFormatter(FeedLimits.altMax)],
              decoration: InputDecoration(
                labelText: context.t('mobile_compose_image_alt_label', {
                  'n': i + 1,
                }),
                helperText: context.t('feed_create_image_alt_help'),
                helperMaxLines: 2,
                counterText: '',
                errorText: _errors['images_alt.$i'],
              ),
            ),
            Wrap(
              children: [
                IconButton(
                  key: Key('compose-image-up-$i'),
                  tooltip: context.t('mobile_compose_image_move_up', {
                    'n': i + 1,
                  }),
                  constraints: const BoxConstraints(
                    minWidth: 48,
                    minHeight: 48,
                  ),
                  icon: const Icon(Icons.arrow_upward),
                  onPressed: i == 0 ? null : () => _moveImage(i, -1),
                ),
                IconButton(
                  key: Key('compose-image-down-$i'),
                  tooltip: context.t('mobile_compose_image_move_down', {
                    'n': i + 1,
                  }),
                  constraints: const BoxConstraints(
                    minWidth: 48,
                    minHeight: 48,
                  ),
                  icon: const Icon(Icons.arrow_downward),
                  onPressed: i == n - 1 ? null : () => _moveImage(i, 1),
                ),
                IconButton(
                  key: Key('compose-image-remove-$i'),
                  tooltip: context.t('mobile_compose_image_remove', {
                    'n': i + 1,
                  }),
                  constraints: const BoxConstraints(
                    minWidth: 48,
                    minHeight: 48,
                  ),
                  icon: const Icon(Icons.delete_outline),
                  onPressed: _busy ? null : () => _removeImage(i),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  List<Widget> _videoSection(BuildContext context) {
    final v = _video;
    final blockedByImages = _images.isNotEmpty;
    return [
      Semantics(
        header: true,
        child: Text(
          context.t('mobile_compose_video_title'),
          style: Theme.of(context).textTheme.titleSmall,
        ),
      ),
      Text(
        context.t('mobile_compose_video_help', {
          'max': FeedLimits.videoMaxBytes ~/ (1024 * 1024),
        }),
        style: Theme.of(context).textTheme.bodySmall,
      ),
      if (v == null) ...[
        Align(
          alignment: AlignmentDirectional.centerStart,
          child: OutlinedButton.icon(
            key: const Key('compose-pick-video'),
            style: OutlinedButton.styleFrom(minimumSize: const Size(48, 48)),
            icon: const Icon(Icons.videocam_outlined),
            label: Text(context.t('mobile_compose_video_pick')),
            onPressed: (blockedByImages || _busy) ? null : _pickVideo,
          ),
        ),
        if (blockedByImages)
          Text(
            context.t('mobile_compose_video_excl_images'),
            style: Theme.of(context).textTheme.bodySmall,
          ),
      ] else ...[
        Card(
          margin: const EdgeInsets.only(top: XmSpace.s2),
          child: Padding(
            padding: const EdgeInsets.all(XmSpace.s2),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  context.t('mobile_compose_video_selected', {
                    'name': v.name,
                    'size': formatBytes(v.size),
                  }),
                  key: const Key('compose-video-info'),
                ),
                if (_errors['video'] != null)
                  Text(
                    _errors['video']!,
                    style: TextStyle(color: context.xm.danger),
                  ),
                const SizedBox(height: XmSpace.s2),
                TextField(
                  key: const Key('compose-video-desc'),
                  controller: _videoDesc,
                  minLines: 2,
                  maxLines: 5,
                  maxLength: FeedLimits.videoDescriptionMax,
                  inputFormatters: const [
                    CodePointLimitFormatter(FeedLimits.videoDescriptionMax),
                  ],
                  decoration: InputDecoration(
                    labelText: context.t('mobile_compose_video_description'),
                    helperText: context.t(
                      'mobile_compose_video_description_help',
                      {
                        'min': FeedLimits.videoDescriptionMin,
                        'max': FeedLimits.videoDescriptionMax,
                      },
                    ),
                    helperMaxLines: 3,
                    errorText: _errors['video_description'],
                    suffixIcon: EmojiButton(
                      controller: _videoDesc,
                      maxLength: FeedLimits.videoDescriptionMax,
                    ),
                  ),
                ),
                const SizedBox(height: XmSpace.s2),
                XmAlert(
                  context.t('feed_video_privacy_notice'),
                  icon: Icons.privacy_tip_outlined,
                  tone: context.xm.warning,
                ),
                const SizedBox(height: XmSpace.s2),
                Text(
                  context.t('feed_video_review_notice'),
                  style: Theme.of(context).textTheme.bodySmall,
                ),
                Wrap(
                  children: [
                    TextButton.icon(
                      key: const Key('compose-change-video'),
                      icon: const Icon(Icons.swap_horiz),
                      label: Text(context.t('mobile_compose_video_change')),
                      onPressed: _busy ? null : _pickVideo,
                    ),
                    TextButton.icon(
                      key: const Key('compose-video-remove'),
                      icon: const Icon(Icons.delete_outline),
                      label: Text(context.t('mobile_compose_video_remove')),
                      onPressed: _busy ? null : _removeVideo,
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ],
      const SizedBox(height: XmSpace.s3),
    ];
  }
}

class _ErrorSummary extends StatelessWidget {
  const _ErrorSummary({required this.title, required this.messages});
  final String title;
  final List<String> messages;

  @override
  Widget build(BuildContext context) => XmAlert(
    messages.length == 1
        ? messages.first
        : '$title\n${messages.map((m) => '• $m').join('\n')}',
  );
}
