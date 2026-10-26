import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../app/theme/xm_tokens.dart';
import '../../../core/l10n/app_strings.dart';
import '../../../core/ui/xm_widgets.dart';
import '../../../core/util/dates.dart';
import '../application/home_providers.dart';
import '../data/home_models.dart';

class NotificationsPageScreen extends ConsumerWidget {
  const NotificationsPageScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final filter = ref.watch(notificationsFilterProvider);
    final state = ref.watch(notificationsProvider);
    final ctrl = ref.read(notificationsProvider.notifier);
    return Scaffold(
      appBar: AppBar(
        title: Text(context.t('notifications_page_title')),
        actions: [
          TextButton(
            onPressed: () async {
              try {
                await ctrl.markAllRead();
              } catch (e) {
                if (context.mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(errorMessage(context, e))));
                }
              }
            },
            child: Text(context.t('notifications_page_mark_all_read')),
          ),
        ],
      ),
      body: Column(children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: XmSpace.gutter, vertical: XmSpace.s2),
          child: SegmentedButton<String>(
            segments: [
              ButtonSegment(value: 'unread', label: Text(context.t('mobile_notifications_filter_unread'))),
              ButtonSegment(value: 'all', label: Text(context.t('mobile_notifications_filter_all'))),
            ],
            selected: {filter},
            onSelectionChanged: (s) => ref.read(notificationsFilterProvider.notifier).state = s.first,
          ),
        ),
        Expanded(
          child: state.when(
            loading: () => const LoadingView(),
            error: (e, _) => ErrorView(error: e, onRetry: () => ref.invalidate(notificationsProvider)),
            data: (page) => page.items.isEmpty
                ? EmptyView(context.t(filter == 'unread' ? 'notifications_page_empty_filter' : 'notifications_page_empty'))
                : RefreshIndicator(
                    onRefresh: () async => ref.invalidate(notificationsProvider),
                    child: ListView.separated(
                      itemCount: page.items.length + (page.hasMore ? 1 : 0),
                      separatorBuilder: (_, _) => const Divider(),
                      itemBuilder: (context, i) {
                        if (i == page.items.length) {
                          return TextButton(
                              onPressed: ctrl.loadMore,
                              child: Text(context.t('notifications_page_load_more')));
                        }
                        final n = page.items[i];
                        return Dismissible(
                          key: ValueKey('notif-${n.id}'),
                          direction: DismissDirection.endToStart,
                          background: Container(
                            color: context.xm.danger,
                            alignment: Alignment.centerRight,
                            padding: const EdgeInsets.only(right: XmSpace.s5),
                            child: Icon(Icons.delete_outline, color: context.xm.onSecondary),
                          ),
                          onDismissed: (_) => ctrl.remove(n),
                          child: _NotificationTile(item: n, onTap: () => ctrl.markRead(n)),
                        );
                      },
                    ),
                  ),
          ),
        ),
      ]),
    );
  }
}

class _NotificationTile extends StatelessWidget {
  const _NotificationTile({required this.item, required this.onTap});
  final NotificationItem item;
  final VoidCallback onTap;

  IconData get _icon => switch (item.category) {
        'messages' => Icons.chat_bubble_outline,
        'evenements' => Icons.event_outlined,
        'sos' || 'alertes' => Icons.warning_amber_outlined,
        'securite' => Icons.lock_outline,
        'communaute' => Icons.people_outline,
        _ => Icons.notifications_outlined,
      };

  @override
  Widget build(BuildContext context) {
    final t = Theme.of(context).textTheme;
    return Semantics(
      label: item.read ? null : context.t('notifications_page_unread'),
      child: ListTile(
        onTap: onTap,
        leading: Icon(_icon, color: item.read ? context.xm.textMuted : context.xm.primary),
        title: Text(item.title, style: item.read ? t.bodyMedium : t.labelLarge),
        subtitle: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          if (item.body != null && item.body!.isNotEmpty) Text(item.body!),
          if (item.createdAt != null) Text(relativeTime(context, item.createdAt!), style: t.labelSmall),
        ]),
        trailing: item.read
            ? null
            : Container(
                width: 10,
                height: 10,
                decoration: BoxDecoration(color: context.xm.primary, shape: BoxShape.circle),
              ),
      ),
    );
  }
}
