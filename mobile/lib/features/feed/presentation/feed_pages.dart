import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/theme/xm_tokens.dart';
import '../../../core/l10n/app_strings.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/ui/xm_widgets.dart';
import '../../../core/util/dates.dart';
import '../application/feed_controller.dart';
import '../data/feed_api.dart';
import '../data/feed_models.dart';

void _toast(BuildContext c, String text) =>
    ScaffoldMessenger.of(c).showSnackBar(SnackBar(content: Text(text)));

/// Onglet Fil : liste par curseur, tirer pour rafraîchir, « Charger plus ».
class FeedTabPage extends ConsumerWidget {
  const FeedTabPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    ref.watch(feedRefreshOnOutboxProvider);
    final state = ref.watch(feedProvider);
    return Scaffold(
      appBar: AppBar(title: Text(context.t('feed_page_title'))),
      floatingActionButton: FloatingActionButton.extended(
        heroTag: 'fab-feed-new',
        onPressed: () => context.push('/feed/new'),
        icon: const Icon(Icons.edit_outlined),
        label: Text(context.t('feed_compose_label')),
      ),
      body: state.when(
        loading: () => const LoadingView(),
        error: (e, _) =>
            ErrorView(error: e, onRetry: () => ref.invalidate(feedProvider)),
        data: (feed) => RefreshIndicator(
          onRefresh: () async => ref.invalidate(feedProvider),
          child: feed.items.isEmpty
              ? ListView(
                  children: [EmptyView(context.t('microcopy_empty_feed'))],
                )
              : ListView.builder(
                  padding: const EdgeInsets.fromLTRB(
                    XmSpace.gutter,
                    XmSpace.s2,
                    XmSpace.gutter,
                    96,
                  ),
                  itemCount: feed.items.length + 1,
                  itemBuilder: (context, i) {
                    if (i == feed.items.length) {
                      if (!feed.hasMore) {
                        return const SizedBox(height: XmSpace.s4);
                      }
                      return Padding(
                        padding: const EdgeInsets.all(XmSpace.s3),
                        child: OutlinedButton(
                          onPressed: feed.loadingMore
                              ? null
                              : () => ref
                                    .read(feedProvider.notifier)
                                    .loadMore()
                                    .catchError((Object e) {
                                      if (context.mounted) {
                                        _toast(
                                          context,
                                          errorMessage(context, e),
                                        );
                                      }
                                    }),
                          child: Text(context.t('mobile_feed_load_more')),
                        ),
                      );
                    }
                    return PostCard(post: feed.items[i]);
                  },
                ),
        ),
      ),
    );
  }
}

class PostCard extends ConsumerWidget {
  const PostCard({super.key, required this.post, this.detail = false});
  final Post post;
  final bool detail;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final ctrl = ref.read(feedProvider.notifier);
    final a = post.author;
    final name = a.anonymous
        ? context.t('feed_post_anonymous_author')
        : a.pseudo;
    return Card(
      margin: const EdgeInsets.symmetric(vertical: XmSpace.s2),
      child: InkWell(
        onTap: detail ? null : () => context.push('/feed/${post.id}'),
        child: Padding(
          padding: const EdgeInsets.all(XmSpace.s3),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  CircleAvatar(
                    radius: 18,
                    child: Text(name.isEmpty ? '?' : name[0].toUpperCase()),
                  ),
                  const SizedBox(width: XmSpace.s2),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          name,
                          style: Theme.of(context).textTheme.titleSmall,
                        ),
                        if (post.publishedAt != null)
                          Text(
                            relativeTime(context, post.publishedAt!),
                            style: Theme.of(context).textTheme.bodySmall,
                          ),
                      ],
                    ),
                  ),
                  if (post.tagLabel.isNotEmpty)
                    Chip(
                      label: Text(post.tagLabel),
                      visualDensity: VisualDensity.compact,
                    ),
                  PopupMenuButton<String>(
                    tooltip: context.t('feed_post_menu'),
                    onSelected: (v) async {
                      try {
                        if (v == 'delete') {
                          await ctrl.delete(post);
                          if (context.mounted) {
                            _toast(context, context.t('feed_toasts_deleted'));
                            if (detail) context.pop();
                          }
                        } else if (v == 'report') {
                          await showReportDialog(context, ref, id: post.id);
                        }
                      } catch (e) {
                        if (context.mounted) {
                          _toast(context, errorMessage(context, e));
                        }
                      }
                    },
                    itemBuilder: (c) => [
                      if (post.canReport)
                        PopupMenuItem(
                          value: 'report',
                          child: Text(c.t('feed_post_report')),
                        ),
                      if (post.canDelete)
                        PopupMenuItem(
                          value: 'delete',
                          child: Text(c.t('feed_post_delete')),
                        ),
                    ],
                  ),
                ],
              ),
              if (post.moderated)
                Padding(
                  padding: const EdgeInsets.only(top: XmSpace.s2),
                  child: Text(
                    context.t('feed_post_moderated'),
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                ),
              if (post.body.isNotEmpty) ...[
                const SizedBox(height: XmSpace.s2),
                Text(
                  post.body,
                  maxLines: detail ? null : 8,
                  overflow: detail ? null : TextOverflow.ellipsis,
                ),
              ],
              if (post.poll != null) PollView(post: post),
              const SizedBox(height: XmSpace.s2),
              Wrap(
                children: [
                  TextButton.icon(
                    onPressed: () async {
                      try {
                        await ctrl.toggleLike(post);
                      } catch (e) {
                        if (context.mounted) {
                          _toast(context, errorMessage(context, e));
                        }
                      }
                    },
                    icon: Icon(
                      post.liked ? Icons.favorite : Icons.favorite_border,
                      color: post.liked ? context.xm.danger : null,
                    ),
                    label: Text(
                      context.t('feed_post_likes', {'count': post.likes}),
                    ),
                  ),
                  TextButton.icon(
                    onPressed: detail
                        ? null
                        : () => context.push('/feed/${post.id}'),
                    icon: const Icon(Icons.mode_comment_outlined),
                    label: Text(
                      context.t('feed_post_comments', {'count': post.comments}),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Sondage : vote optimiste, retrait via « Retirer mon vote ».
class PollView extends ConsumerStatefulWidget {
  const PollView({super.key, required this.post});
  final Post post;

  @override
  ConsumerState<PollView> createState() => _PollViewState();
}

class _PollViewState extends ConsumerState<PollView> {
  final Set<int> _picked = {};

  Future<void> _run(Future<void> Function() f, String okKey) async {
    try {
      await f();
      if (mounted) _toast(context, context.t(okKey));
    } catch (e) {
      if (!mounted) return;
      final api = ApiException.from(e);
      _toast(
        context,
        api.code == 'poll_closed'
            ? context.t('feed_poll_already_closed')
            : context.t('feed_poll_vote_failed'),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final poll = widget.post.poll!;
    final ctrl = ref.read(feedProvider.notifier);
    final showResults = poll.resultsVisible || poll.hasVoted || poll.closed;
    return Padding(
      padding: const EdgeInsets.only(top: XmSpace.s3),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(poll.question, style: Theme.of(context).textTheme.titleSmall),
          Text(
            context.t(
              poll.multiple
                  ? 'feed_poll_multiple_choice'
                  : 'feed_poll_single_choice',
            ),
            style: Theme.of(context).textTheme.bodySmall,
          ),
          const SizedBox(height: XmSpace.s2),
          for (final o in poll.options)
            _option(context, poll, o, showResults, ctrl),
          Text(
            context.t('feed_poll_participants', {'count': poll.votersCount}),
            style: Theme.of(context).textTheme.bodySmall,
          ),
          if (poll.closed)
            Text(
              context.t('feed_poll_closed'),
              style: Theme.of(context).textTheme.bodySmall,
            ),
          if (poll.canVote && !poll.hasVoted && poll.multiple)
            FilledButton(
              onPressed: _picked.isEmpty
                  ? null
                  : () => _run(
                      () => ctrl.vote(widget.post, _picked.toList()),
                      'feed_poll_voted',
                    ),
              child: Text(context.t('feed_poll_vote')),
            ),
          if (poll.hasVoted && !poll.closed)
            TextButton(
              onPressed: () => _run(
                () => ctrl.removeVote(widget.post),
                'feed_poll_vote_removed',
              ),
              child: Text(context.t('feed_poll_remove_vote')),
            ),
        ],
      ),
    );
  }

  Widget _option(
    BuildContext context,
    Poll poll,
    PollOption o,
    bool showResults,
    FeedController ctrl,
  ) {
    final mine = poll.myChoices.contains(o.id);
    final canTap = poll.canVote && !poll.hasVoted && !poll.closed;
    final pct = (o.percentage ?? 0).toDouble();
    return Padding(
      padding: const EdgeInsets.only(bottom: XmSpace.s2),
      child: Semantics(
        button: canTap,
        selected: mine,
        label:
            '${o.label}${mine ? ' ${context.t('feed_poll_my_choice_sr')}' : ''}',
        child: InkWell(
          borderRadius: BorderRadius.circular(XmSpace.radiusMd),
          onTap: !canTap
              ? null
              : () {
                  if (poll.multiple) {
                    setState(
                      () => _picked.contains(o.id)
                          ? _picked.remove(o.id)
                          : _picked.add(o.id),
                    );
                  } else {
                    _run(
                      () => ctrl.vote(widget.post, [o.id]),
                      'feed_poll_voted',
                    );
                  }
                },
          child: Container(
            constraints: const BoxConstraints(minHeight: XmSpace.targetMin),
            padding: const EdgeInsets.symmetric(
              horizontal: XmSpace.s3,
              vertical: XmSpace.s2,
            ),
            decoration: BoxDecoration(
              border: Border.all(
                color: mine || _picked.contains(o.id)
                    ? context.xm.primary
                    : context.xm.border,
              ),
              borderRadius: BorderRadius.circular(XmSpace.radiusMd),
            ),
            child: Stack(
              alignment: Alignment.centerLeft,
              children: [
                if (showResults)
                  Positioned.fill(
                    child: FractionallySizedBox(
                      alignment: Alignment.centerLeft,
                      widthFactor: (pct / 100).clamp(0, 1),
                      child: ColoredBox(color: context.xm.primarySubtle),
                    ),
                  ),
                Row(
                  children: [
                    Expanded(child: Text(o.label)),
                    if (showResults && o.percentage != null)
                      Text(
                        context.t('feed_poll_percent', {
                          'value': o.percentage!.round(),
                        }),
                        key: Key('poll-pct-${o.id}'),
                      ),
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

const _reportReasons = [
  'harcelement',
  'discrimination',
  'menaces',
  'donnees_personnelles',
  'desinformation',
  'usurpation',
  'spam',
  'autre',
];

/// Signalement d'une publication ou d'un commentaire (motifs de `ContentReportReason`).
Future<void> showReportDialog(
  BuildContext context,
  WidgetRef ref, {
  required String id,
  bool comment = false,
}) async {
  String? reason;
  final details = TextEditingController();
  final ok = await showDialog<bool>(
    context: context,
    builder: (c) => StatefulBuilder(
      builder: (c, setS) => AlertDialog(
        title: Text(
          c.t(comment ? 'feed_report_comment_title' : 'feed_report_title'),
        ),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(c.t('feed_report_intro')),
              const SizedBox(height: XmSpace.s3),
              DropdownButtonFormField<String>(
                isExpanded: true,
                initialValue: reason,
                decoration: InputDecoration(
                  labelText: c.t('feed_report_reason'),
                ),
                items: [
                  for (final r in _reportReasons)
                    DropdownMenuItem(
                      value: r,
                      child: Text(c.t('enums_feed_report_reason_$r')),
                    ),
                ],
                onChanged: (v) => setS(() => reason = v),
              ),
              const SizedBox(height: XmSpace.s2),
              TextField(
                controller: details,
                maxLength: 500,
                decoration: InputDecoration(
                  labelText: c.t('feed_report_details'),
                  hintText: c.t('feed_report_details_placeholder'),
                ),
              ),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(c, false),
            child: Text(c.t('common_cancel')),
          ),
          FilledButton(
            onPressed: reason == null ? null : () => Navigator.pop(c, true),
            child: Text(c.t('feed_report_submit')),
          ),
        ],
      ),
    ),
  );
  final text = details.text.trim();
  details.dispose();
  if (ok != true || reason == null) return;
  try {
    await ref
        .read(feedApiProvider)
        .report(id, reason!, details: text, comment: comment);
    if (context.mounted) _toast(context, context.t('feed_toasts_reported'));
  } catch (e) {
    if (context.mounted) _toast(context, errorMessage(context, e));
  }
}

/// Publication ou sondage ; hors connexion, elle part par la boîte d'envoi.
class CreatePostPage extends ConsumerStatefulWidget {
  const CreatePostPage({super.key});

  @override
  ConsumerState<CreatePostPage> createState() => _CreatePostPageState();
}

class _CreatePostPageState extends ConsumerState<CreatePostPage> {
  final _scroll = ScrollController();
  final _body = TextEditingController();
  final _question = TextEditingController();
  final _options = [TextEditingController(), TextEditingController()];
  bool _poll = false;
  bool _anonymous = false;
  bool _multiple = false;
  int _duration = 7;
  String _visibility = 'public';
  String? _error;
  bool _busy = false;

  @override
  void dispose() {
    _scroll.dispose();
    _body.dispose();
    _question.dispose();
    for (final o in _options) {
      o.dispose();
    }
    super.dispose();
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

  Future<void> _submit() async {
    final body = _body.text.trim();
    final opts = _options
        .map((o) => o.text.trim())
        .where((t) => t.isNotEmpty)
        .toList();
    String? err;
    if (_poll) {
      final q = _question.text.trim();
      if (q.length < 5 || q.length > 200) {
        err = context.t('feed_poll_question_error', {'min': 5, 'max': 200});
      } else if (opts.length < 2) {
        err = context.t('feed_poll_options_error', {'min': 2});
      } else if (opts.map((o) => o.toLowerCase()).toSet().length !=
          opts.length) {
        err = context.t('feed_poll_options_duplicate');
      }
    } else if (body.isEmpty) {
      err = context.t('feed_create_text');
    }
    if (_anonymous && _visibility == 'followers') {
      err = context.t('feed_create_anonymous_followers');
    }
    if (err != null) {
      setState(() => _error = err);
      _toTop();
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    final payload = <String, dynamic>{
      'kind': _poll ? 'poll' : 'post',
      if (body.isNotEmpty) 'body': body,
      'visibility': _visibility,
      'is_anonymous': _anonymous,
      if (_poll)
        'poll': {
          'question': _question.text.trim(),
          'options': opts,
          'multiple': _multiple,
          'results_before_vote': false,
          'duration_days': _duration,
        },
    };
    try {
      final online = await ref.read(feedProvider.notifier).publish(payload);
      if (!mounted) return;
      _toast(
        context,
        context.t(
          online
              ? (_poll ? 'feed_toasts_poll_published' : 'feed_toasts_published')
              : 'microcopy_offline_queued',
        ),
      );
      context.pop();
    } catch (e) {
      if (!mounted) return;
      final api = ApiException.from(e);
      setState(
        () => _error = api.code == 'content_blocked'
            ? context.t('feed_create_bad_words')
            : errorMessage(context, e),
      );
      _toTop();
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(context.t(_poll ? 'feed_poll_title' : 'feed_create_title')),
      ),
      body: SafeArea(
        child: ListView(
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
              onSelectionChanged: (s) => setState(() => _poll = s.first),
            ),
            const SizedBox(height: XmSpace.s3),
            Text(
              context.t('feed_create_help_notice'),
              style: Theme.of(context).textTheme.bodySmall,
            ),
            const SizedBox(height: XmSpace.s3),
            if (_error != null) ...[
              XmAlert(_error!),
              const SizedBox(height: XmSpace.s3),
            ],
            if (_poll) ...[
              TextField(
                controller: _question,
                maxLength: 200,
                decoration: InputDecoration(
                  labelText: context.t('feed_poll_question'),
                  hintText: context.t('feed_poll_question_placeholder'),
                ),
              ),
              for (var i = 0; i < _options.length; i++)
                Padding(
                  padding: const EdgeInsets.only(bottom: XmSpace.s2),
                  child: TextField(
                    controller: _options[i],
                    maxLength: 80,
                    decoration: InputDecoration(
                      labelText: context.t('feed_poll_option_label', {
                        'n': i + 1,
                      }),
                      counterText: '',
                    ),
                  ),
                ),
              if (_options.length < 6)
                TextButton.icon(
                  icon: const Icon(Icons.add),
                  label: Text(context.t('feed_poll_option_add')),
                  onPressed: () =>
                      setState(() => _options.add(TextEditingController())),
                ),
              SwitchListTile(
                contentPadding: EdgeInsets.zero,
                value: _multiple,
                title: Text(context.t('feed_poll_multiple')),
                onChanged: (v) => setState(() => _multiple = v),
              ),
              DropdownButtonFormField<int>(
                isExpanded: true,
                initialValue: _duration,
                decoration: InputDecoration(
                  labelText: context.t('feed_poll_duration'),
                ),
                items: [
                  for (final d in const [1, 3, 7, 14, 30, 0])
                    DropdownMenuItem(
                      value: d,
                      child: Text(context.t('feed_poll_durations_$d')),
                    ),
                ],
                onChanged: (v) => setState(() => _duration = v ?? 7),
              ),
              const SizedBox(height: XmSpace.s3),
            ],
            TextField(
              controller: _body,
              minLines: 4,
              maxLines: 8,
              maxLength: 3000,
              decoration: InputDecoration(
                labelText: context.t(
                  _poll ? 'feed_poll_text' : 'feed_create_text',
                ),
                hintText: context.t(
                  _poll
                      ? 'feed_poll_text_placeholder'
                      : 'feed_create_text_placeholder',
                ),
              ),
            ),
            DropdownButtonFormField<String>(
              isExpanded: true,
              initialValue: _visibility,
              decoration: InputDecoration(
                labelText: context.t('feed_create_visibility'),
              ),
              items: [
                for (final v in const ['public', 'followers', 'me'])
                  DropdownMenuItem(
                    value: v,
                    child: Text(context.t('feed_create_visibility_options_$v')),
                  ),
              ],
              onChanged: (v) => setState(() => _visibility = v ?? 'public'),
            ),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              value: _anonymous,
              title: Text(context.t('feed_create_anonymous')),
              subtitle: Text(context.t('feed_create_anonymous_help')),
              onChanged: (v) => setState(() => _anonymous = v),
            ),
            const SizedBox(height: XmSpace.s3),
            FilledButton(
              style: FilledButton.styleFrom(
                minimumSize: const Size.fromHeight(52),
              ),
              onPressed: _busy ? null : _submit,
              child: Text(
                context.t(_poll ? 'feed_poll_submit' : 'feed_create_submit'),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Détail d'une publication et ses commentaires.
class PostDetailPage extends ConsumerStatefulWidget {
  const PostDetailPage({super.key, required this.id});
  final String id;

  @override
  ConsumerState<PostDetailPage> createState() => _PostDetailPageState();
}

class _PostDetailPageState extends ConsumerState<PostDetailPage> {
  final _text = TextEditingController();
  List<Comment> _comments = [];
  String? _next;
  Object? _error;
  bool _loading = true;
  bool _sending = false;
  int? _replyTo;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _text.dispose();
    super.dispose();
  }

  Future<void> _load({bool more = false}) async {
    try {
      await ref.read(feedProvider.future);
      await ref.read(feedProvider.notifier).ensure(widget.id);
      final r = await ref
          .read(feedApiProvider)
          .comments(widget.id, cursor: more ? _next : null);
      if (!mounted) return;
      setState(() {
        _comments = more ? [..._comments, ...r.items] : r.items;
        _next = r.next;
        _loading = false;
        _error = null;
      });
    } catch (e) {
      if (mounted) {
        setState(() {
          _error = e;
          _loading = false;
        });
      }
    }
  }

  Future<void> _send() async {
    final t = _text.text.trim();
    if (t.length < 2) {
      _toast(context, context.t('feed_comments_too_short'));
      return;
    }
    setState(() => _sending = true);
    try {
      final c = await ref
          .read(feedApiProvider)
          .addComment(widget.id, t, parentId: _replyTo);
      if (!mounted) return;
      _text.clear();
      setState(() {
        _comments = [..._comments, c];
        _replyTo = null;
      });
      ref.read(feedProvider.notifier).bumpComments(widget.id, _comments.length);
      _toast(context, context.t('feed_comments_posted'));
    } catch (e) {
      if (mounted) _toast(context, errorMessage(context, e));
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final post = ref
        .watch(feedProvider)
        .value
        ?.items
        .where((p) => p.id == widget.id)
        .firstOrNull;
    return Scaffold(
      appBar: AppBar(title: Text(context.t('feed_comments_title'))),
      body: SafeArea(
        child: _loading
            ? const LoadingView()
            : (post == null
                  ? ErrorView(
                      error: _error ?? StateError('not_found'),
                      onRetry: () {
                        setState(() => _loading = true);
                        _load();
                      },
                    )
                  : Column(
                      children: [
                        Expanded(
                          child: ListView(
                            padding: const EdgeInsets.all(XmSpace.gutter),
                            children: [
                              PostCard(post: post, detail: true),
                              Text(
                                context.t('feed_comments_count', {
                                  'count': post.comments,
                                }),
                                style: Theme.of(context).textTheme.titleSmall,
                              ),
                              if (_comments.isEmpty)
                                EmptyView(context.t('feed_comments_empty')),
                              for (final c in _comments)
                                _commentTile(context, c),
                              if (_next != null)
                                TextButton(
                                  onPressed: () => _load(more: true),
                                  child: Text(
                                    context.t('feed_comments_load_next'),
                                  ),
                                ),
                            ],
                          ),
                        ),
                        if (post.canComment) _composer(context),
                      ],
                    )),
      ),
    );
  }

  Widget _commentTile(BuildContext context, Comment c) {
    final name = c.author.anonymous
        ? context.t('feed_comments_anonymous')
        : c.author.pseudo;
    return Padding(
      padding: EdgeInsets.only(
        left: c.parentId == null ? 0 : XmSpace.s5,
        top: XmSpace.s2,
      ),
      child: ListTile(
        contentPadding: EdgeInsets.zero,
        title: Text(name, style: Theme.of(context).textTheme.titleSmall),
        subtitle: Text(c.body),
        trailing: PopupMenuButton<String>(
          onSelected: (v) async {
            if (v == 'reply') {
              setState(() => _replyTo = c.id);
            } else if (v == 'delete') {
              try {
                await ref.read(feedApiProvider).deleteComment(c.id);
                if (mounted) {
                  setState(
                    () => _comments = _comments
                        .where((x) => x.id != c.id)
                        .toList(),
                  );
                }
              } catch (e) {
                if (context.mounted) _toast(context, errorMessage(context, e));
              }
            } else if (v == 'report') {
              await showReportDialog(
                context,
                ref,
                id: '${c.id}',
                comment: true,
              );
            }
          },
          itemBuilder: (x) => [
            PopupMenuItem(
              value: 'reply',
              child: Text(x.t('feed_comments_reply')),
            ),
            if (c.canReport)
              PopupMenuItem(
                value: 'report',
                child: Text(x.t('feed_comments_report')),
              ),
            if (c.canDelete)
              PopupMenuItem(
                value: 'delete',
                child: Text(x.t('feed_comments_delete')),
              ),
          ],
        ),
      ),
    );
  }

  Widget _composer(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(
      XmSpace.gutter,
      XmSpace.s2,
      XmSpace.gutter,
      XmSpace.s2,
    ),
    child: Row(
      children: [
        Expanded(
          child: TextField(
            controller: _text,
            maxLength: 1000,
            decoration: InputDecoration(
              counterText: '',
              labelText: context.t('feed_comments_field_label'),
              hintText: context.t('feed_comments_placeholder'),
            ),
          ),
        ),
        IconButton.filled(
          tooltip: context.t('feed_comments_send'),
          onPressed: _sending ? null : _send,
          icon: const Icon(Icons.send),
        ),
      ],
    ),
  );
}
