import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:uuid/uuid.dart';

import '../../../app/theme/xm_tokens.dart';
import '../../../core/l10n/app_strings.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/outbox/outbox.dart';
import '../../../core/providers/core_providers.dart';
import '../../../core/ui/xm_widgets.dart';
import '../../../core/util/dates.dart';
import '../../auth/application/session_controller.dart';
import '../data/community_api.dart';
import '../data/community_models.dart';

void _toast(BuildContext c, String text) =>
    ScaffoldMessenger.of(c).showSnackBar(SnackBar(content: Text(text)));

final conversationsFilterProvider = StateProvider.autoDispose<String>(
  (ref) => 'all',
);

final conversationsProvider = FutureProvider.autoDispose<List<Conversation>>(
  (ref) => ref
      .watch(communityApiProvider)
      .conversations(filter: ref.watch(conversationsFilterProvider)),
);

/// Onglet Messages : liste (sondée toutes les 15 s), filtre non lues, nouvelle conversation.
class MessagesTabPage extends ConsumerStatefulWidget {
  const MessagesTabPage({super.key});

  @override
  ConsumerState<MessagesTabPage> createState() => _MessagesTabPageState();
}

class _MessagesTabPageState extends ConsumerState<MessagesTabPage> {
  Timer? _poll;

  @override
  void initState() {
    super.initState();
    _poll = Timer.periodic(const Duration(seconds: 15), (_) {
      if (ref.read(isOnlineProvider)) ref.invalidate(conversationsProvider);
    });
  }

  @override
  void dispose() {
    _poll?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final filter = ref.watch(conversationsFilterProvider);
    final state = ref.watch(conversationsProvider);
    return Scaffold(
      appBar: AppBar(title: Text(context.t('community_messages_title'))),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => context.push('/messages/new'),
        icon: const Icon(Icons.edit_outlined),
        label: Text(context.t('community_messages_new')),
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.symmetric(
              horizontal: XmSpace.gutter,
              vertical: XmSpace.s2,
            ),
            child: SegmentedButton<String>(
              segments: [
                ButtonSegment(
                  value: 'all',
                  label: Text(context.t('community_messages_filters_all')),
                ),
                ButtonSegment(
                  value: 'unread',
                  label: Text(context.t('community_messages_filters_unread')),
                ),
              ],
              selected: {filter},
              onSelectionChanged: (s) =>
                  ref.read(conversationsFilterProvider.notifier).state =
                      s.first,
            ),
          ),
          Expanded(
            child: state.when(
              loading: () => const LoadingView(),
              error: (e, _) => ErrorView(
                error: e,
                onRetry: () => ref.invalidate(conversationsProvider),
              ),
              data: (items) => RefreshIndicator(
                onRefresh: () async => ref.invalidate(conversationsProvider),
                child: items.isEmpty
                    ? ListView(
                        children: [
                          EmptyView(context.t('community_messages_empty_list')),
                        ],
                      )
                    : ListView.separated(
                        padding: const EdgeInsets.only(bottom: 96),
                        itemCount: items.length,
                        separatorBuilder: (_, _) => const Divider(height: 1),
                        itemBuilder: (context, i) {
                          final c = items[i];
                          return ListTile(
                            key: Key('conv-${c.id}'),
                            leading: CircleAvatar(
                              child: Text(
                                c.name.isEmpty ? '?' : c.name[0].toUpperCase(),
                              ),
                            ),
                            title: Text(
                              c.name,
                              style: TextStyle(
                                fontWeight: c.unread > 0
                                    ? FontWeight.w700
                                    : null,
                              ),
                            ),
                            subtitle: Text(
                              c.previewHidden
                                  ? context.t(
                                      'community_messages_preview_hidden',
                                    )
                                  : (c.preview ?? ''),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                            trailing: Column(
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: [
                                if (c.lastAt != null)
                                  Text(
                                    relativeTime(context, c.lastAt!),
                                    style: Theme.of(context)
                                        .textTheme
                                        .bodySmall,
                                  ),
                                if (c.unread > 0)
                                  Semantics(
                                    label: context.t(
                                      'community_messages_unread',
                                      {'count': c.unread},
                                    ),
                                    child: Badge(label: Text('${c.unread}')),
                                  ),
                              ],
                            ),
                            onTap: () => context.push('/messages/${c.id}'),
                          );
                        },
                      ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Nouvelle conversation : connexions d'abord, recherche par pseudonyme, puis `POST conversations`.
class NewConversationPage extends ConsumerStatefulWidget {
  const NewConversationPage({super.key});

  @override
  ConsumerState<NewConversationPage> createState() =>
      _NewConversationPageState();
}

class _NewConversationPageState extends ConsumerState<NewConversationPage> {
  final _q = TextEditingController();
  List<Recipient> _people = const [];
  bool _loading = true;
  Object? _error;
  Timer? _debounce;

  @override
  void initState() {
    super.initState();
    _search('');
  }

  @override
  void dispose() {
    _q.dispose();
    _debounce?.cancel();
    super.dispose();
  }

  Future<void> _search(String q) async {
    try {
      final r = await ref.read(communityApiProvider).recipients(q.trim());
      if (mounted) {
        setState(() {
          _people = r;
          _loading = false;
          _error = null;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _error = e;
          _loading = false;
        });
      }
    }
  }

  Future<void> _start(Recipient p) async {
    try {
      final c = await ref.read(communityApiProvider).startPrivate(p.id);
      ref.invalidate(conversationsProvider);
      if (mounted) context.pushReplacement('/messages/${c.id}');
    } catch (e) {
      if (!mounted) return;
      final api = ApiException.from(e);
      _toast(
        context,
        api.code == 'recipient_unavailable'
            ? context.t('community_messages_with_unavailable')
            : errorMessage(context, e),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(context.t('community_messages_new_dialog_title')),
      ),
      body: SafeArea(
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.all(XmSpace.gutter),
              child: TextField(
                controller: _q,
                onChanged: (v) {
                  _debounce?.cancel();
                  _debounce = Timer(const Duration(milliseconds: 300), () {
                    if (v.trim().length == 1) return;
                    _search(v);
                  });
                },
                decoration: InputDecoration(
                  labelText: context.t('community_messages_new_dialog_search'),
                  hintText: context.t(
                    'community_messages_new_dialog_search_placeholder',
                  ),
                  helperText: context.t(
                    'community_messages_new_dialog_search_hint',
                    {'min': 2},
                  ),
                  prefixIcon: const Icon(Icons.search),
                ),
              ),
            ),
            Expanded(
              child: _loading
                  ? const LoadingView()
                  : _error != null
                  ? ErrorView(error: _error!, onRetry: () => _search(_q.text))
                  : _people.isEmpty
                  ? EmptyView(
                      context.t('community_messages_new_dialog_no_people'),
                    )
                  : ListView(
                      children: [
                        if (_q.text.trim().isEmpty)
                          Padding(
                            padding: const EdgeInsets.symmetric(
                              horizontal: XmSpace.gutter,
                            ),
                            child: Text(
                              context.t(
                                'community_messages_new_dialog_suggestions',
                              ),
                              style: Theme.of(context).textTheme.titleSmall,
                            ),
                          ),
                        for (final p in _people)
                          ListTile(
                            key: Key('person-${p.id}'),
                            leading: CircleAvatar(
                              child: Text(
                                p.pseudo.isEmpty
                                    ? '?'
                                    : p.pseudo[0].toUpperCase(),
                              ),
                            ),
                            title: Text(p.pseudo),
                            subtitle: Text(
                              context.t('community_messages_new_dialog_start', {
                                'name': p.pseudo,
                              }),
                            ),
                            onTap: () => _start(p),
                          ),
                      ],
                    ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Conversation : historique, sondage court (4 s), envoi avec `client_uuid`
/// et file locale hors ligne (horloge grisée « En attente d'envoi »).
class ConversationPage extends ConsumerStatefulWidget {
  const ConversationPage({super.key, required this.id});
  final String id;

  @override
  ConsumerState<ConversationPage> createState() => _ConversationPageState();
}

class _ConversationPageState extends ConsumerState<ConversationPage> {
  final _text = TextEditingController();
  Conversation? _conv;
  List<Message> _messages = [];
  String? _older;
  bool _loading = true;
  Object? _error;
  Timer? _poll;
  StreamSubscription<OutboxResult>? _sub;
  bool _polling = false;

  int get _lastId => _messages.isEmpty
      ? 0
      : _messages.map((m) => m.id).reduce((a, b) => a > b ? a : b);

  @override
  void initState() {
    super.initState();
    _load();
    _sub = ref.read(outboxProvider).results.listen((r) {
      if (r.entry.type == OutboxType.message &&
          r.entry.target == widget.id &&
          mounted) {
        _merge([Message.fromJson(r.data)]);
      }
    });
    _poll = Timer.periodic(const Duration(seconds: 4), (_) => _pollOnce());
  }

  @override
  void dispose() {
    _poll?.cancel();
    _sub?.cancel();
    _text.dispose();
    super.dispose();
  }

  void _merge(List<Message> incoming) {
    final byId = {for (final m in _messages) m.id: m};
    for (final m in incoming) {
      byId[m.id] = m;
    }
    final list = byId.values.toList()..sort((a, b) => a.id.compareTo(b.id));
    setState(() => _messages = list);
  }

  Future<void> _load() async {
    final api = ref.read(communityApiProvider);
    try {
      final c = await api.conversation(widget.id);
      final h = await api.messages(widget.id);
      if (!mounted) return;
      setState(() {
        _conv = c;
        _messages = h.items.reversed.toList();
        _older = h.next;
        _loading = false;
        _error = null;
      });
      unawaited(
        api
            .markRead(widget.id)
            .then((_) => ref.invalidate(conversationsProvider))
            .catchError((Object _) {}),
      );
    } catch (e) {
      if (mounted) {
        setState(() {
          _error = e;
          _loading = false;
        });
      }
    }
  }

  Future<void> _pollOnce() async {
    if (_polling || _loading || _error != null || !ref.read(isOnlineProvider)) {
      return;
    }
    _polling = true;
    try {
      final r = await ref
          .read(communityApiProvider)
          .messages(widget.id, after: _lastId);
      if (mounted && r.items.isNotEmpty) _merge(r.items);
    } catch (_) {
      // Réseau coupé : prochain tour.
    } finally {
      _polling = false;
    }
  }

  Future<void> _loadOlder() async {
    if (_older == null) return;
    final r = await ref
        .read(communityApiProvider)
        .messages(widget.id, cursor: _older);
    if (!mounted) return;
    setState(() => _older = r.next);
    _merge(r.items);
  }

  Future<void> _send() async {
    final body = _text.text.trim();
    if (body.isEmpty) {
      _toast(context, context.t('community_messages_errors_empty'));
      return;
    }
    if (body.length > 1000) {
      _toast(
        context,
        context.t('community_messages_errors_too_long', {'max': 1000}),
      );
      return;
    }
    _text.clear();
    final entry = OutboxEntry(
      uuid: const Uuid().v4(),
      type: OutboxType.message,
      payload: {'body': body},
      createdAt: DateTime.now(),
      owner: ref.read(sessionProvider).user?.id,
      target: widget.id,
    );
    // Écrit d'abord en local (Hive chiffré) : un message n'est jamais perdu.
    await ref.read(outboxProvider).sendNow(entry);
  }

  Future<void> _menu(String v) async {
    final api = ref.read(communityApiProvider);
    try {
      switch (v) {
        case 'block':
          final ok = await _confirm(
            context.t('community_messages_confirm_block_title'),
            context.t('community_messages_confirm_block', {
              'name': _conv?.name ?? '',
            }),
          );
          if (ok != true) return;
          final c = await api.block(widget.id, on: true);
          if (mounted) {
            setState(() => _conv = c);
            _toast(context, context.t('community_messages_toasts_blocked'));
          }
        case 'unblock':
          final c = await api.block(widget.id, on: false);
          if (mounted) {
            setState(() => _conv = c);
            _toast(context, context.t('community_messages_toasts_unblocked'));
          }
        case 'hide':
          await api.hide(widget.id);
          ref.invalidate(conversationsProvider);
          if (mounted) {
            _toast(context, context.t('community_messages_toasts_hidden'));
            context.pop();
          }
        case 'report':
          await _report();
      }
    } catch (e) {
      if (mounted) _toast(context, errorMessage(context, e));
    }
  }

  Future<bool?> _confirm(String title, String text) => showDialog<bool>(
    context: context,
    builder: (c) => AlertDialog(
      title: Text(title),
      content: Text(text),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(c, false),
          child: Text(c.t('common_cancel')),
        ),
        FilledButton(
          onPressed: () => Navigator.pop(c, true),
          child: Text(c.t('common_yes')),
        ),
      ],
    ),
  );

  Future<void> _report() async {
    String? reason;
    final details = TextEditingController();
    final ok = await showDialog<bool>(
      context: context,
      builder: (c) => StatefulBuilder(
        builder: (c, setS) => AlertDialog(
          title: Text(c.t('community_messages_report_title')),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(c.t('community_messages_report_intro')),
                const SizedBox(height: XmSpace.s3),
                DropdownButtonFormField<String>(
                  isExpanded: true,
                  initialValue: reason,
                  decoration: InputDecoration(
                    labelText: c.t('community_messages_report_reason'),
                  ),
                  items: [
                    for (final r in const [
                      'harcelement',
                      'discrimination',
                      'menaces',
                      'donnees_personnelles',
                      'desinformation',
                      'usurpation',
                      'spam',
                      'autre',
                    ])
                      DropdownMenuItem(
                        value: r,
                        child: Text(c.t('enums_feed_report_reason_$r')),
                      ),
                  ],
                  onChanged: (v) => setS(() => reason = v),
                ),
                TextField(
                  controller: details,
                  maxLength: 500,
                  decoration: InputDecoration(
                    labelText: c.t('community_messages_report_details'),
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
              child: Text(c.t('community_messages_report_submit')),
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
          .read(communityApiProvider)
          .reportConversation(widget.id, reason!, details: text);
      if (mounted) {
        _toast(context, context.t('community_messages_toasts_reported'));
      }
    } catch (e) {
      if (mounted) {
        final api = ApiException.from(e);
        _toast(
          context,
          api.code == 'nothing_to_report'
              ? context.t('community_messages_api_nothing_to_report')
              : errorMessage(context, e),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final online = ref.watch(isOnlineProvider);
    final pending =
        (ref.watch(outboxEntriesProvider).valueOrNull ?? const <OutboxEntry>[])
            .where((e) => e.type == OutboxType.message && e.target == widget.id)
            .toList();
    final c = _conv;
    return Scaffold(
      appBar: AppBar(
        title: Text(c?.name ?? ''),
        actions: [
          if (c != null)
            PopupMenuButton<String>(
              tooltip: context.t('community_messages_menu'),
              onSelected: _menu,
              itemBuilder: (x) => [
                if (c.isPrivate)
                  PopupMenuItem(
                    value: c.blockedByMe ? 'unblock' : 'block',
                    child: Text(
                      x.t(
                        c.blockedByMe
                            ? 'community_messages_actions_unblock'
                            : 'community_messages_actions_block',
                      ),
                    ),
                  ),
                PopupMenuItem(
                  value: 'report',
                  child: Text(x.t('community_messages_actions_report')),
                ),
                PopupMenuItem(
                  value: 'hide',
                  child: Text(x.t('community_messages_actions_hide')),
                ),
              ],
            ),
        ],
      ),
      body: SafeArea(
        child: _loading
            ? const LoadingView()
            : _error != null
            ? ErrorView(
                error: _error!,
                onRetry: () {
                  setState(() => _loading = true);
                  _load();
                },
              )
            : Column(
                children: [
                  if (!online)
                    XmAlert(
                      context.t('community_messages_offline_notice'),
                      icon: Icons.cloud_off_outlined,
                    ),
                  Expanded(
                    child: ListView(
                      padding: const EdgeInsets.all(XmSpace.gutter),
                      children: [
                        if (_older != null)
                          TextButton(
                            onPressed: _loadOlder,
                            child: Text(
                              context.t('community_messages_load_older'),
                            ),
                          ),
                        if (_messages.isEmpty && pending.isEmpty)
                          EmptyView(
                            context.t('community_messages_empty_conversation'),
                          ),
                        for (final m in _messages)
                          _bubble(
                            context,
                            m.deleted
                                ? context.t('community_messages_deleted')
                                : (m.body ?? ''),
                            mine: m.mine,
                            at: m.createdAt,
                            author: m.author,
                          ),
                        for (final e in pending) _pendingBubble(context, e),
                      ],
                    ),
                  ),
                  if (c != null && !c.canSend)
                    Padding(
                      padding: const EdgeInsets.all(XmSpace.gutter),
                      child: Text(
                        context.t(
                          c.blockedByMe
                              ? 'community_messages_blocked_notice'
                              : 'community_messages_blocked_by_notice',
                        ),
                      ),
                    )
                  else
                    Padding(
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
                              minLines: 1,
                              maxLines: 4,
                              decoration: InputDecoration(
                                labelText: context.t(
                                  'community_messages_compose_label',
                                ),
                                hintText: context.t(
                                  'community_messages_compose',
                                ),
                              ),
                            ),
                          ),
                          IconButton.filled(
                            tooltip: context.t('community_messages_send'),
                            onPressed: _send,
                            icon: const Icon(Icons.send),
                          ),
                        ],
                      ),
                    ),
                ],
              ),
      ),
    );
  }

  Widget _bubble(
    BuildContext context,
    String text, {
    required bool mine,
    DateTime? at,
    String? author,
  }) {
    final xm = context.xm;
    return Align(
      alignment: mine ? Alignment.centerRight : Alignment.centerLeft,
      child: Container(
        margin: const EdgeInsets.symmetric(vertical: 3),
        padding: const EdgeInsets.symmetric(
          horizontal: XmSpace.s3,
          vertical: XmSpace.s2,
        ),
        constraints: BoxConstraints(
          maxWidth: MediaQuery.sizeOf(context).width * 0.8,
        ),
        decoration: BoxDecoration(
          color: mine ? xm.primarySubtle : xm.surfaceMuted,
          borderRadius: BorderRadius.circular(XmSpace.radiusLg),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (!mine && author != null && author.isNotEmpty)
              Text(author, style: Theme.of(context).textTheme.labelSmall),
            Text(text),
            if (at != null)
              Text(
                formatDateTime(context, at),
                style: Theme.of(context).textTheme.labelSmall,
              ),
          ],
        ),
      ),
    );
  }

  /// Message en file : horloge grisée, « En attente d'envoi » ; refusé → « Non envoyé ».
  Widget _pendingBubble(BuildContext context, OutboxEntry e) {
    final failed = !e.isPending;
    final xm = context.xm;
    return Align(
      alignment: Alignment.centerRight,
      child: Container(
        key: Key('pending-${e.uuid}'),
        margin: const EdgeInsets.symmetric(vertical: 3),
        padding: const EdgeInsets.symmetric(
          horizontal: XmSpace.s3,
          vertical: XmSpace.s2,
        ),
        decoration: BoxDecoration(
          color: xm.surfaceMuted,
          border: Border.all(color: failed ? xm.danger : xm.border),
          borderRadius: BorderRadius.circular(XmSpace.radiusLg),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            Text('${e.payload['body'] ?? ''}'),
            Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(
                  failed ? Icons.error_outline : Icons.schedule,
                  size: 14,
                  color: failed ? xm.danger : xm.textMuted,
                ),
                const SizedBox(width: 4),
                Text(
                  context.t(
                    failed
                        ? 'community_messages_failed'
                        : 'community_messages_pending',
                  ),
                  style: Theme.of(context).textTheme.labelSmall
                      ?.copyWith(color: xm.textMuted),
                ),
                if (failed) ...[
                  IconButton(
                    visualDensity: VisualDensity.compact,
                    tooltip: context.t('community_messages_retry'),
                    icon: const Icon(Icons.refresh, size: 18),
                    onPressed: () async {
                      await ref.read(outboxProvider).retry(e.uuid);
                      ref.read(outboxProvider).flush();
                    },
                  ),
                  IconButton(
                    visualDensity: VisualDensity.compact,
                    tooltip: context.t('community_messages_remove_pending'),
                    icon: const Icon(Icons.delete_outline, size: 18),
                    onPressed: () => ref.read(outboxProvider).remove(e.uuid),
                  ),
                ],
              ],
            ),
          ],
        ),
      ),
    );
  }
}
