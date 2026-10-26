import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:uuid/uuid.dart';

import '../../../core/outbox/outbox.dart';
import '../../../core/providers/core_providers.dart';
import '../../auth/application/session_controller.dart';
import '../data/feed_api.dart';
import '../data/feed_models.dart';

class FeedState {
  const FeedState({
    this.items = const [],
    this.next,
    this.stale = false,
    this.loadingMore = false,
  });
  final List<Post> items;
  final String? next;
  final bool stale;
  final bool loadingMore;
  bool get hasMore => next != null && next!.isNotEmpty;

  FeedState copy({
    List<Post>? items,
    Object? next = _k,
    bool? stale,
    bool? loadingMore,
  }) => FeedState(
    items: items ?? this.items,
    next: identical(next, _k) ? this.next : next as String?,
    stale: stale ?? this.stale,
    loadingMore: loadingMore ?? this.loadingMore,
  );
}

const Object _k = Object();

/// Fil par curseur. Les gestes (j'aime, vote) sont optimistes et reviennent en
/// arrière si le serveur refuse.
class FeedController extends AsyncNotifier<FeedState> {
  @override
  Future<FeedState> build() async {
    ref.watch(sessionProvider.select((s) => s.user?.id));
    final page = await ref.watch(feedApiProvider).feed();
    return FeedState(
      items: page.items,
      next: page.nextCursor,
      stale: page.stale,
    );
  }

  FeedApi get _api => ref.read(feedApiProvider);

  Future<void> loadMore() async {
    final cur = state.value;
    if (cur == null || !cur.hasMore || cur.loadingMore) return;
    state = AsyncData(cur.copy(loadingMore: true));
    try {
      final page = await _api.feed(cursor: cur.next);
      final seen = cur.items.map((p) => p.id).toSet();
      state = AsyncData(
        cur.copy(
          items: [
            ...cur.items,
            ...page.items.where((p) => !seen.contains(p.id)),
          ],
          next: page.nextCursor,
          loadingMore: false,
        ),
      );
    } catch (_) {
      state = AsyncData(cur.copy(loadingMore: false));
      rethrow;
    }
  }

  Post? byId(String id) =>
      state.value?.items.where((p) => p.id == id).firstOrNull;

  void _patch(String id, Post Function(Post) f) {
    final cur = state.value;
    if (cur == null) return;
    state = AsyncData(
      cur.copy(items: [for (final p in cur.items) p.id == id ? f(p) : p]),
    );
  }

  /// Ouvre une publication absente du fil (lien partagé, retour d'une notification).
  Future<Post> ensure(String id) async {
    final known = byId(id);
    if (known != null) return known;
    final p = await _api.post(id);
    final cur = state.value ?? const FeedState();
    state = AsyncData(cur.copy(items: [p, ...cur.items]));
    return p;
  }

  Future<void> toggleLike(Post post) async {
    final on = !post.liked;
    _patch(
      post.id,
      (p) => p.copyWith(
        liked: on,
        likes: (p.likes + (on ? 1 : -1)).clamp(0, 1 << 30),
      ),
    );
    try {
      final r = await _api.like(post.id, on: on);
      _patch(post.id, (p) => p.copyWith(liked: r.liked, likes: r.likes));
    } catch (_) {
      _patch(post.id, (p) => p.copyWith(liked: post.liked, likes: post.likes));
      rethrow;
    }
  }

  Future<void> vote(Post post, List<int> choices) async {
    final poll = post.poll;
    if (poll == null || choices.isEmpty) return;
    _patch(post.id, (p) => p.copyWith(poll: poll.withVote(choices)));
    try {
      final fresh = await _api.vote(post.id, choices);
      _patch(post.id, (p) => p.copyWith(poll: fresh));
    } catch (_) {
      _patch(post.id, (p) => p.copyWith(poll: poll));
      rethrow;
    }
  }

  Future<void> removeVote(Post post) async {
    final poll = post.poll;
    if (poll == null) return;
    _patch(post.id, (p) => p.copyWith(poll: poll.withoutVote()));
    try {
      final fresh = await _api.removeVote(post.id);
      _patch(post.id, (p) => p.copyWith(poll: fresh));
    } catch (_) {
      _patch(post.id, (p) => p.copyWith(poll: poll));
      rethrow;
    }
  }

  Future<void> delete(Post post) async {
    await _api.deletePost(post.id);
    final cur = state.value;
    if (cur != null) {
      state = AsyncData(
        cur.copy(items: cur.items.where((p) => p.id != post.id).toList()),
      );
    }
  }

  void bumpComments(String id, int count) =>
      _patch(id, (p) => p.copyWith(comments: count));

  /// Publie (ou met en file hors connexion). `true` : en ligne ; `false` : en attente.
  Future<bool> publish(Map<String, dynamic> payload) async {
    final session = ref.read(sessionProvider);
    final entry = OutboxEntry(
      uuid: const Uuid().v4(),
      type: OutboxType.post,
      payload: payload,
      createdAt: DateTime.now(),
      owner: session.user?.id,
    );
    final outbox = ref.read(outboxProvider);
    final res = await outbox.sendNow(entry);
    if (res != null) {
      ref.invalidateSelf();
      return true;
    }
    final left = outbox.find(entry.uuid);
    if (left != null && left.status == OutboxStatus.failed) {
      final err = outbox.errorOf(entry.uuid);
      await outbox.remove(entry.uuid);
      throw err ?? StateError('post_failed');
    }
    return false;
  }
}

final feedProvider = AsyncNotifierProvider<FeedController, FeedState>(
  FeedController.new,
);

/// Une publication déjà publiée hors connexion est publiée au rejeu : on recharge le fil.
final feedRefreshOnOutboxProvider = Provider<void>((ref) {
  ref.listen(outboxResultsProvider, (_, next) {
    if (next.valueOrNull?.entry.type == OutboxType.post) {
      ref.invalidate(feedProvider);
    }
  });
});
