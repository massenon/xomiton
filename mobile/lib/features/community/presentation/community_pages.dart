import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/theme/xm_tokens.dart';
import '../../../core/l10n/app_strings.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/platform/platform_services.dart';
import '../../../core/providers/core_providers.dart';
import '../../../core/ui/xm_widgets.dart';
import '../../alerts/data/alerts_api.dart';
import '../../auth/application/session_controller.dart';
import '../data/community_api.dart';
import '../data/community_models.dart';

void _toast(BuildContext c, String text) =>
    ScaffoldMessenger.of(c).showSnackBar(SnackBar(content: Text(text)));

// --- Groupes ------------------------------------------------------------------

final groupsTabProvider = StateProvider.autoDispose<String>(
  (ref) => 'discover',
);
final groupsProvider = FutureProvider.autoDispose<List<Group>>(
  (ref) =>
      ref.watch(communityApiProvider).groups(tab: ref.watch(groupsTabProvider)),
);
final groupProvider = FutureProvider.autoDispose.family<Group, String>(
  (ref, id) => ref.watch(communityApiProvider).group(id),
);

class GroupsPage extends ConsumerWidget {
  const GroupsPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final tab = ref.watch(groupsTabProvider);
    final state = ref.watch(groupsProvider);
    return Scaffold(
      appBar: AppBar(title: Text(context.t('community_groups_title'))),
      body: SafeArea(
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.all(XmSpace.gutter),
              child: SegmentedButton<String>(
                segments: [
                  ButtonSegment(
                    value: 'discover',
                    label: Text(context.t('community_groups_tabs_discover')),
                  ),
                  ButtonSegment(
                    value: 'mine',
                    label: Text(context.t('community_groups_tabs_mine')),
                  ),
                ],
                selected: {tab},
                onSelectionChanged: (s) =>
                    ref.read(groupsTabProvider.notifier).state = s.first,
              ),
            ),
            Expanded(
              child: state.when(
                loading: () => const LoadingView(),
                error: (e, _) => ErrorView(
                  error: e,
                  onRetry: () => ref.invalidate(groupsProvider),
                ),
                data: (items) => items.isEmpty
                    ? EmptyView(
                        context.t(
                          tab == 'mine'
                              ? 'community_groups_empty_mine'
                              : 'community_groups_empty',
                        ),
                      )
                    : ListView(
                        children: [
                          for (final g in items)
                            ListTile(
                              title: Text(g.name),
                              subtitle: Text(
                                [
                                  context.t('community_groups_members', {
                                    'count': g.members,
                                  }),
                                  context.t(
                                    g.privacy == 'prive'
                                        ? 'community_groups_detail_kind_private'
                                        : 'community_groups_detail_kind_public',
                                  ),
                                ].join(' · '),
                              ),
                              trailing: const Icon(Icons.chevron_right),
                              onTap: () => context.push('/groupes/${g.id}'),
                            ),
                        ],
                      ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class GroupDetailPage extends ConsumerWidget {
  const GroupDetailPage({super.key, required this.id});
  final String id;

  Future<void> _act(
    BuildContext context,
    WidgetRef ref,
    Future<Group> Function() f,
    String okKey,
  ) async {
    try {
      await f();
      ref.invalidate(groupProvider(id));
      ref.invalidate(groupsProvider);
      if (context.mounted) _toast(context, context.t(okKey));
    } catch (e) {
      if (!context.mounted) return;
      final api = ApiException.from(e);
      _toast(
        context,
        api.code == 'group_last_admin'
            ? context.t('community_groups_errors_last_admin')
            : errorMessage(context, e),
      );
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(groupProvider(id));
    final api = ref.read(communityApiProvider);
    return Scaffold(
      appBar: AppBar(
        title: Text(state.valueOrNull?.name ?? context.t('community_groups_title')),
      ),
      body: SafeArea(
        child: state.when(
          loading: () => const LoadingView(),
          error: (e, _) => ErrorView(
            error: e,
            onRetry: () => ref.invalidate(groupProvider(id)),
          ),
          data: (g) => ListView(
            padding: const EdgeInsets.all(XmSpace.gutter),
            children: [
              Text(g.name, style: Theme.of(context).textTheme.headlineSmall),
              Text(
                [
                  context.t('community_groups_members', {'count': g.members}),
                  if (g.zone != null) g.zone!,
                  context.t(
                    g.privacy == 'prive'
                        ? 'community_groups_detail_kind_private'
                        : 'community_groups_detail_kind_public',
                  ),
                ].join(' · '),
              ),
              if (g.description != null) ...[
                const SizedBox(height: XmSpace.s3),
                Text(g.description!),
              ],
              const SizedBox(height: XmSpace.s4),
              if (g.isRequested)
                Text(context.t('community_groups_detail_pending')),
              if (g.canJoin)
                FilledButton(
                  onPressed: () => _act(
                    context,
                    ref,
                    () => api.joinGroup(id),
                    g.privacy == 'prive'
                        ? 'community_groups_toasts_requested'
                        : 'community_groups_toasts_joined',
                  ),
                  child: Text(
                    context.t(
                      g.privacy == 'prive'
                          ? 'community_groups_actions_request'
                          : 'community_groups_actions_join_group',
                    ),
                  ),
                ),
              if (g.canCancelRequest || g.canLeave)
                OutlinedButton(
                  onPressed: () => _act(
                    context,
                    ref,
                    () => api.leaveGroup(id),
                    g.canLeave
                        ? 'community_groups_toasts_left'
                        : 'community_groups_toasts_request_cancelled',
                  ),
                  child: Text(
                    context.t(
                      g.canLeave
                          ? 'community_groups_actions_leave'
                          : 'community_groups_actions_cancel_request',
                    ),
                  ),
                ),
              if (g.canDiscuss)
                TextButton.icon(
                  icon: const Icon(Icons.forum_outlined),
                  label: Text(context.t('community_groups_detail_discussion')),
                  onPressed: () async {
                    try {
                      final cid = await api.groupConversation(id);
                      if (context.mounted) context.push('/messages/$cid');
                    } catch (e) {
                      if (context.mounted) {
                        _toast(context, errorMessage(context, e));
                      }
                    }
                  },
                ),
              if (g.isMember) _GroupPosts(id: id),
              if (!g.isMember && g.privacy == 'prive')
                Text(context.t('community_groups_detail_private_hidden')),
            ],
          ),
        ),
      ),
    );
  }
}

final _groupPostsProvider = FutureProvider.autoDispose
    .family<List<Map<String, dynamic>>, String>(
      (ref, id) => ref.watch(communityApiProvider).groupPosts(id),
    );

class _GroupPosts extends ConsumerWidget {
  const _GroupPosts({required this.id});
  final String id;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final s = ref.watch(_groupPostsProvider(id));
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const SizedBox(height: XmSpace.s4),
        Text(
          context.t('community_groups_detail_posts'),
          style: Theme.of(context).textTheme.titleMedium,
        ),
        s.when(
          loading: () => const Padding(
            padding: EdgeInsets.all(XmSpace.s4),
            child: LoadingView(),
          ),
          error: (e, _) => ErrorView(
            error: e,
            onRetry: () => ref.invalidate(_groupPostsProvider(id)),
          ),
          data: (posts) => posts.isEmpty
              ? Text(context.t('community_groups_detail_posts_empty'))
              : Column(
                  children: [
                    for (final p in posts)
                      Card(
                        child: ListTile(
                          title: Text(
                            '${(p['author'] as Map?)?['pseudo'] ?? ''}',
                          ),
                          subtitle: Text(
                            '${p['body'] ?? ''}',
                            maxLines: 4,
                            overflow: TextOverflow.ellipsis,
                          ),
                          onTap: () => context.push('/feed/${p['id']}'),
                        ),
                      ),
                  ],
                ),
        ),
      ],
    );
  }
}

// --- Événements ----------------------------------------------------------------

final eventsFilterProvider = StateProvider.autoDispose<String>(
  (ref) => 'upcoming',
);
final eventsProvider = FutureProvider.autoDispose<List<EventItem>>(
  (ref) => ref
      .watch(communityApiProvider)
      .events(filter: ref.watch(eventsFilterProvider)),
);

class EventsPage extends ConsumerWidget {
  const EventsPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final filter = ref.watch(eventsFilterProvider);
    final state = ref.watch(eventsProvider);
    return Scaffold(
      appBar: AppBar(title: Text(context.t('community_events_title'))),
      body: SafeArea(
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.all(XmSpace.gutter),
              child: SegmentedButton<String>(
                segments: [
                  ButtonSegment(
                    value: 'upcoming',
                    label: Text(context.t('community_events_upcoming')),
                  ),
                  ButtonSegment(
                    value: 'going',
                    label: Text(context.t('community_events_mine')),
                  ),
                  ButtonSegment(
                    value: 'past',
                    label: Text(context.t('community_events_past')),
                  ),
                ],
                selected: {filter},
                onSelectionChanged: (s) =>
                    ref.read(eventsFilterProvider.notifier).state = s.first,
              ),
            ),
            Expanded(
              child: state.when(
                loading: () => const LoadingView(),
                error: (e, _) => ErrorView(
                  error: e,
                  onRetry: () => ref.invalidate(eventsProvider),
                ),
                data: (items) => items.isEmpty
                    ? EmptyView(
                        context.t(switch (filter) {
                          'going' => 'community_events_empty_mine',
                          'past' => 'community_events_empty_past',
                          _ => 'community_events_empty_upcoming',
                        }),
                      )
                    : ListView(
                        children: [
                          for (final e in items)
                            ListTile(
                              key: Key('event-${e.id}'),
                              title: Text(e.title),
                              subtitle: Text(
                                [
                                  if (e.dateLabel != null) e.dateLabel!,
                                  if (e.timeLabel != null) e.timeLabel!,
                                  if (e.locationLabel != null) e.locationLabel!,
                                ].join(' · '),
                              ),
                              trailing: e.going
                                  ? const Icon(Icons.check_circle_outline)
                                  : const Icon(Icons.chevron_right),
                              onTap: () => context.push('/evenements/${e.id}'),
                            ),
                        ],
                      ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

final eventProvider = FutureProvider.autoDispose.family<EventItem, String>(
  (ref, id) => ref.watch(communityApiProvider).event(id),
);

/// Détail d'un événement. L'adresse exacte et le lien n'existent dans la
/// réponse qu'après « Je participe » : tant qu'ils sont absents on l'indique.
class EventDetailPage extends ConsumerStatefulWidget {
  const EventDetailPage({super.key, required this.id});
  final String id;

  @override
  ConsumerState<EventDetailPage> createState() => _EventDetailPageState();
}

class _EventDetailPageState extends ConsumerState<EventDetailPage> {
  bool _reminder = true;
  bool _busy = false;

  Future<void> _rsvp(EventItem e, bool going) async {
    setState(() => _busy = true);
    try {
      final fresh = await ref
          .read(communityApiProvider)
          .rsvp(widget.id, going: going, reminder: _reminder);
      ref.invalidate(eventProvider(widget.id));
      ref.invalidate(eventsProvider);
      if (!mounted) return;
      if (going && fresh.startsAt != null) {
        // L'adresse (désormais livrée) rejoint le calendrier du téléphone.
        await ref
            .read(calendarServiceProvider)
            .addEvent(
              title: fresh.title,
              start: fresh.startsAt!,
              end: fresh.endsAt,
              location: fresh.address ?? fresh.place,
              description: fresh.description,
            );
      }
      if (mounted) {
        _toast(
          context,
          context.t(
            going
                ? (_reminder
                      ? 'community_events_toasts_attending'
                      : 'community_events_toasts_attending_no_reminder')
                : 'community_events_toasts_declined',
          ),
        );
      }
    } catch (err) {
      if (!mounted) return;
      final api = ApiException.from(err);
      _toast(
        context,
        api.code == 'event_finished'
            ? context.t('community_events_errors_past')
            : errorMessage(context, err),
      );
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(eventProvider(widget.id));
    return Scaffold(
      appBar: AppBar(
        title: Text(state.valueOrNull?.title ?? context.t('community_events_title')),
      ),
      body: SafeArea(
        child: state.when(
          loading: () => const LoadingView(),
          error: (e, _) => ErrorView(
            error: e,
            onRetry: () => ref.invalidate(eventProvider(widget.id)),
          ),
          data: (e) => ListView(
            padding: const EdgeInsets.all(XmSpace.gutter),
            children: [
              Text(e.title, style: Theme.of(context).textTheme.headlineSmall),
              const SizedBox(height: XmSpace.s2),
              Text(
                [
                  if (e.dateLabel != null) e.dateLabel!,
                  if (e.timeLabel != null) e.timeLabel!,
                ].join(' · '),
              ),
              if (e.locationLabel != null) Text(e.locationLabel!),
              if (e.organizer != null)
                Text(
                  context.t('community_events_detail_organizer', {
                    'name': e.organizer,
                  }),
                ),
              Text(
                context.t('community_events_participants', {
                  'count': e.goingCount,
                }),
                style: Theme.of(context).textTheme.bodySmall,
              ),
              const SizedBox(height: XmSpace.s3),
              if (e.address != null)
                ListTile(
                  key: const Key('event-address'),
                  contentPadding: EdgeInsets.zero,
                  leading: const Icon(Icons.place_outlined),
                  title: Text(context.t('community_events_detail_address')),
                  subtitle: Text(e.address!),
                ),
              if (e.onlineUrl != null)
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: const Icon(Icons.videocam_outlined),
                  title: Text(context.t('community_events_detail_online_link')),
                  subtitle: Text(e.onlineUrl!),
                  onTap: () => ref
                      .read(linkLauncherProvider)
                      .open(Uri.parse(e.onlineUrl!)),
                ),
              if (e.exactPlaceReserved &&
                  e.address == null &&
                  e.onlineUrl == null)
                Text(
                  context.t('community_events_detail_address_hidden'),
                  key: const Key('event-address-hidden'),
                ),
              const SizedBox(height: XmSpace.s3),
              Text(
                e.description?.isNotEmpty == true
                    ? e.description!
                    : context.t('community_events_detail_no_description'),
              ),
              const SizedBox(height: XmSpace.s4),
              if (!e.isPast || e.going) ...[
                if (!e.going)
                  SwitchListTile(
                    contentPadding: EdgeInsets.zero,
                    value: _reminder,
                    title: Text(
                      context.t('community_events_detail_reminder_toggle'),
                    ),
                    onChanged: (v) => setState(() => _reminder = v),
                  ),
                FilledButton.icon(
                  style: FilledButton.styleFrom(
                    minimumSize: const Size.fromHeight(52),
                  ),
                  icon: Icon(
                    e.going
                        ? Icons.event_busy_outlined
                        : Icons.event_available_outlined,
                  ),
                  onPressed: _busy ? null : () => _rsvp(e, !e.going),
                  label: Text(
                    context.t(
                      e.going
                          ? 'community_events_going_cancel'
                          : 'community_events_going',
                    ),
                  ),
                ),
              ] else
                Text(context.t('community_events_errors_past')),
            ],
          ),
        ),
      ),
    );
  }
}

// --- Assistant -------------------------------------------------------------------

class _Turn {
  _Turn(this.mine, this.text, [this.answer]);
  final bool mine;
  final String text;
  final AssistantAnswer? answer;
}

/// Assistant : garde-fous de crise. La réponse de crise est décidée par le
/// serveur (jamais de fournisseur externe) ; l'écran n'enregistre rien
/// (`persist` n'est jamais envoyé), affiche les numéros et liens fournis, et
/// hors réseau n'appelle pas l'API : texte hors ligne + numéro du cache.
class AssistantPage extends ConsumerStatefulWidget {
  const AssistantPage({super.key});

  @override
  ConsumerState<AssistantPage> createState() => _AssistantPageState();
}

class _AssistantPageState extends ConsumerState<AssistantPage> {
  final _text = TextEditingController();
  final List<_Turn> _turns = [];
  bool _busy = false;

  @override
  void dispose() {
    _text.dispose();
    super.dispose();
  }

  Future<void> _ask(String q) async {
    q = q.trim();
    if (q.length < 2 || _busy) return;
    _text.clear();
    setState(() {
      _turns.add(_Turn(true, q));
      _busy = true;
    });
    if (!ref.read(isOnlineProvider)) {
      final urg = await ref
          .read(alertsApiProvider)
          .emergency()
          .then<String?>((d) => d.firstService?.number)
          .catchError((Object _) => null);
      if (!mounted) return;
      setState(() {
        _busy = false;
        _turns.add(
          _Turn(
            false,
            urg == null
                ? context.t('components_assistant_offline_no_number')
                : context.t('components_assistant_offline', {'number': urg}),
            AssistantAnswer(
              text: '',
              kind: 'offline',
              emergency: false,
              suggestions: const [],
              links: const [],
            ),
          ),
        );
      });
      return;
    }
    try {
      final a = await ref.read(communityApiProvider).ask(q);
      if (mounted) setState(() => _turns.add(_Turn(false, a.text, a)));
    } catch (e) {
      if (!mounted) return;
      final api = ApiException.from(e);
      setState(
        () => _turns.add(
          _Turn(
            false,
            api.status == 429
                ? context.t('components_assistant_rate_limited', {
                    'seconds': api.retryAfter ?? 60,
                  })
                : context.t('components_assistant_error'),
          ),
        ),
      );
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _openLink(AssistantLink l) {
    final uri = Uri.tryParse(l.url);
    if (uri == null) return;
    if (uri.scheme == 'tel') {
      ref.read(linkLauncherProvider).open(uri);
      return;
    }
    final member = ref.read(sessionProvider).authenticated;
    switch (uri.path) {
      case '/sos':
        context.push(member ? '/sos' : '/alerte-sos');
      case '/signaler':
        context.push(
          uri.queryParameters['tab'] == 'besoin'
              ? '/signaler?type=need'
              : '/signaler',
        );
      case '/urgences':
        context.push('/urgences');
      default:
        ref.read(linkLauncherProvider).open(uri);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(context.t('assistant_page_title'))),
      body: SafeArea(
        child: Column(
          children: [
            Expanded(
              child: ListView(
                padding: const EdgeInsets.all(XmSpace.gutter),
                children: [
                  Text(context.t('assistant_page_welcome')),
                  const SizedBox(height: XmSpace.s2),
                  Text(
                    context.t('assistant_page_disclaimer'),
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                  const SizedBox(height: XmSpace.s3),
                  if (_turns.isEmpty)
                    Wrap(
                      spacing: XmSpace.s2,
                      runSpacing: XmSpace.s2,
                      children: [
                        for (var i = 0; i < 4; i++)
                          ActionChip(
                            label: Text(context.t('assistant_page_chips_$i')),
                            onPressed: () =>
                                _ask(context.t('assistant_page_chips_$i')),
                          ),
                      ],
                    ),
                  for (final t in _turns) _turn(context, t),
                  if (_busy) Text(context.t('assistant_page_typing')),
                ],
              ),
            ),
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
                      maxLength: 500,
                      textInputAction: TextInputAction.send,
                      onSubmitted: _ask,
                      decoration: InputDecoration(
                        counterText: '',
                        labelText: context.t('assistant_page_input_label'),
                        hintText: context.t('assistant_page_placeholder'),
                      ),
                    ),
                  ),
                  IconButton.filled(
                    tooltip: context.t('assistant_page_send'),
                    onPressed: _busy ? null : () => _ask(_text.text),
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

  Widget _turn(BuildContext context, _Turn t) {
    final xm = context.xm;
    final a = t.answer;
    final crisis = a != null && (a.emergency || a.kind == 'crisis');
    return Padding(
      padding: const EdgeInsets.only(top: XmSpace.s3),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            context.t(t.mine ? 'assistant_page_me' : 'assistant_page_bot'),
            style: Theme.of(context).textTheme.labelMedium,
          ),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(XmSpace.s3),
            decoration: BoxDecoration(
              color: crisis
                  ? xm.danger.withValues(alpha: 0.10)
                  : (t.mine ? xm.primarySubtle : xm.surfaceMuted),
              borderRadius: BorderRadius.circular(XmSpace.radiusMd),
              border: crisis ? Border.all(color: xm.danger) : null,
            ),
            child: Semantics(liveRegion: !t.mine, child: Text(t.text)),
          ),
          if (a != null && a.kind == 'offline')
            Padding(
              padding: const EdgeInsets.only(top: XmSpace.s2),
              child: OutlinedButton(
                onPressed: () => context.push('/urgences'),
                child: Text(context.t('components_assistant_offline_link')),
              ),
            ),
          if (a != null && a.links.isNotEmpty)
            Wrap(
              spacing: XmSpace.s2,
              children: [
                for (final l in a.links)
                  ActionChip(
                    backgroundColor: l.variant == 'danger'
                        ? xm.danger.withValues(alpha: 0.15)
                        : null,
                    label: Text(l.label),
                    onPressed: () => _openLink(l),
                  ),
              ],
            ),
          if (a != null && a.kind == 'fallback' && a.suggestions.isNotEmpty)
            Wrap(
              spacing: XmSpace.s2,
              children: [
                for (final s in a.suggestions)
                  ActionChip(label: Text(s), onPressed: () => _ask(s)),
              ],
            ),
        ],
      ),
    );
  }
}
