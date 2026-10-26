import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/providers/core_providers.dart';
import '../data/home_api.dart';
import '../data/home_models.dart';

final dashboardProvider =
    FutureProvider.autoDispose<DashboardData>((ref) => ref.watch(homeApiProvider).dashboard());

class ThemesQuery {
  const ThemesQuery({this.categorie, this.q = ''});
  final String? categorie;
  final String q;

  @override
  bool operator ==(Object other) =>
      other is ThemesQuery && other.categorie == categorie && other.q == q;
  @override
  int get hashCode => Object.hash(categorie, q);
}

final themesProvider = FutureProvider.autoDispose.family<ThemeList, ThemesQuery>(
    (ref, query) => ref.watch(homeApiProvider).themes(categorie: query.categorie, q: query.q));

final themeDetailProvider = FutureProvider.autoDispose.family<ThemeDetailData, String>(
    (ref, slug) => ref.watch(homeApiProvider).theme(slug));

/// Compteur de la cloche : lecture immédiate puis toutes les 30 s (avec
/// `If-None-Match`, donc 304 la plupart du temps) tant que l'application est
/// en ligne et qu'une personne est connectée.
final unreadCountProvider = StreamProvider.autoDispose<int>((ref) async* {
  final api = ref.watch(homeApiProvider);
  final online = ref.watch(isOnlineProvider);
  if (!online) return;
  while (true) {
    try {
      yield await api.unreadCount();
    } catch (_) {}
    await Future<void>.delayed(const Duration(seconds: 30));
  }
});

class ResourcesQuery {
  const ResourcesQuery({this.categorie, this.q = ''});
  final String? categorie;
  final String q;
  @override
  bool operator ==(Object other) =>
      other is ResourcesQuery && other.categorie == categorie && other.q == q;
  @override
  int get hashCode => Object.hash(categorie, q);
}

/// Liste paginée des ressources : première page + « Charger plus ».
class ResourcesController
    extends AutoDisposeFamilyAsyncNotifier<PagedResources, ResourcesQuery> {
  @override
  Future<PagedResources> build(ResourcesQuery arg) =>
      ref.watch(homeApiProvider).resources(q: arg.q, categorie: arg.categorie);

  Future<void> loadMore() async {
    final cur = state.value;
    if (cur == null || !cur.hasMore) return;
    final next = await ref
        .read(homeApiProvider)
        .resources(q: arg.q, categorie: arg.categorie, page: cur.page + 1);
    state = AsyncData(PagedResources(
      items: [...cur.items, ...next.items],
      page: next.page,
      lastPage: next.lastPage,
      total: next.total,
      categories: cur.categories,
    ));
  }
}

final resourcesProvider = AsyncNotifierProvider.autoDispose
    .family<ResourcesController, PagedResources, ResourcesQuery>(ResourcesController.new);

/// Filtre des notifications : `unread` ou `all` (docs/API.md §7).
final notificationsFilterProvider = StateProvider.autoDispose<String>((ref) => 'unread');

class NotificationsController extends AutoDisposeAsyncNotifier<NotificationsPage> {
  @override
  Future<NotificationsPage> build() =>
      ref.watch(homeApiProvider).notifications(filter: ref.watch(notificationsFilterProvider));

  Future<void> loadMore() async {
    final cur = state.value;
    if (cur == null || !cur.hasMore) return;
    final next = await ref
        .read(homeApiProvider)
        .notifications(filter: ref.read(notificationsFilterProvider), page: cur.page + 1);
    state = AsyncData(NotificationsPage(
        items: [...cur.items, ...next.items], page: next.page, lastPage: next.lastPage));
  }

  Future<void> markRead(NotificationItem n) async {
    final cur = state.value;
    if (cur == null || n.read) return;
    await ref.read(homeApiProvider).markRead(n.id);
    state = AsyncData(NotificationsPage(
      items: [for (final i in cur.items) i.id == n.id ? i.asRead() : i],
      page: cur.page,
      lastPage: cur.lastPage,
    ));
    ref.invalidate(unreadCountProvider);
  }

  Future<void> markAllRead() async {
    await ref.read(homeApiProvider).markAllRead();
    ref.invalidateSelf();
    ref.invalidate(unreadCountProvider);
    ref.invalidate(dashboardProvider);
  }

  Future<void> remove(NotificationItem n) async {
    final cur = state.value;
    await ref.read(homeApiProvider).deleteNotification(n.id);
    if (cur != null) {
      state = AsyncData(NotificationsPage(
          items: cur.items.where((i) => i.id != n.id).toList(),
          page: cur.page,
          lastPage: cur.lastPage));
    }
    ref.invalidate(unreadCountProvider);
  }
}

final notificationsProvider =
    AsyncNotifierProvider.autoDispose<NotificationsController, NotificationsPage>(
        NotificationsController.new);
