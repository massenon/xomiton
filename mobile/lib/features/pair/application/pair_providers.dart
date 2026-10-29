import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/providers/core_providers.dart';
import '../../../core/sync/sync_providers.dart';
import '../../home/data/home_api.dart';
import '../../home/data/home_models.dart';
import '../data/pair_api.dart';
import '../data/pair_models.dart';

/// Interrupteur du rafraîchissement automatique des alertes (désactivé dans les tests).
final peerPollEnabledProvider = Provider<bool>((_) => true);

/// Secondes entre deux relectures de la liste des alertes au premier plan :
/// 10 s pour une personne de garde, 30 s sinon (plan P15-13).
int peerPollSeconds({required bool onDuty}) => onDuty ? 10 : 30;

// --- accueil ------------------------------------------------------------------

class PeerHome {
  const PeerHome({required this.section, this.onDuty = false, this.stale = false});
  final PeerSection section;
  final bool onDuty;

  /// Chiffres recalculés depuis la copie locale (hors ligne).
  final bool stale;
}

/// Accueil du pair : `GET dashboard` (section `peer`) ; hors ligne, chiffres
/// recalculés depuis les dossiers et alertes gardés sur l'appareil.
final peerHomeProvider = FutureProvider.autoDispose<PeerHome>((ref) async {
  final store = ref.watch(peerStoreProvider);
  try {
    final d = await ref.watch(homeApiProvider).dashboard();
    final peer = d.peer;
    if (peer != null) return PeerHome(section: peer, onDuty: peer.onDuty);
    throw const ApiException(status: 403, code: 'forbidden');
  } on DioException catch (e) {
    if (!ApiException.from(e).isNetwork) rethrow;
    return PeerHome(section: _localSection(store.rows('cases'), store.rows('alerts')), stale: true);
  }
});

PeerSection _localSection(List<Map<String, dynamic>> cases, List<Map<String, dynamic>> alerts) {
  final now = DateTime.now();
  final live = [for (final c in cases) if (c['status'] != 'archive') c];
  final month = live.where((c) {
    final d = DateTime.tryParse('${c['created_at']}')?.toLocal();
    return d != null && d.year == now.year && d.month == now.month;
  }).length;
  final open = alerts.where((a) => a['status'] != 'clos' && a['status'] != 'traite').toList();
  final fresh = open.where((a) => a['status'] == 'nouveau' && a['acknowledged'] != true).length;
  final toContact = live.where((c) => c['status'] == 'ouvert').length;
  return PeerSection(
    kpi: PeerKpi(
      collected: live.length,
      thisMonth: month,
      inProgress: live.where((c) => c['status'] == 'ouvert' || c['status'] == 'en_cours').length,
      closed: live.where((c) => c['status'] == 'clos').length,
    ),
    routedOpen: open.length,
    routedNew: fresh,
    tasks: [
      if (fresh > 0) PeerTask('alerts_to_take', fresh),
      if (toContact > 0) PeerTask('cases_to_contact', toContact),
    ],
  );
}

// --- alertes ------------------------------------------------------------------

class PeerAlertsController extends AutoDisposeAsyncNotifier<PeerAlertsPage> {
  @override
  Future<PeerAlertsPage> build() => ref.watch(pairApiProvider).alerts();

  /// Relecture silencieuse (polling) : la liste affichée reste en place pendant
  /// le chargement ; une erreur réseau garde la dernière liste, marquée périmée.
  Future<void> refresh() async {
    final cur = state.valueOrNull;
    try {
      final next = await ref.read(pairApiProvider).alerts();
      state = AsyncData(next);
    } catch (e) {
      if (cur != null) {
        state = AsyncData(
          PeerAlertsPage(items: cur.items, newCount: cur.newCount, page: cur.page, lastPage: cur.lastPage, stale: true, onDuty: cur.onDuty),
        );
      } else {
        state = AsyncError(e, StackTrace.current);
      }
    }
  }

  Future<void> loadMore() async {
    final cur = state.valueOrNull;
    if (cur == null || !cur.hasMore) return;
    final next = await ref.read(pairApiProvider).alerts(page: cur.page + 1);
    state = AsyncData(
      PeerAlertsPage(
        items: [...cur.items, ...next.items],
        newCount: next.newCount,
        page: next.page,
        lastPage: next.lastPage,
        total: next.total,
        onDuty: next.onDuty ?? cur.onDuty,
      ),
    );
  }

  /// Accusé direct depuis la liste ; la ligne est remplacée par la réponse.
  Future<void> acknowledge(String id) async {
    final d = await ref.read(pairApiProvider).acknowledge(id);
    final cur = state.valueOrNull;
    if (cur == null) return;
    state = AsyncData(
      PeerAlertsPage(
        items: [for (final a in cur.items) a.id == id ? d.summary : a],
        newCount: cur.items.where((a) => a.id != id && a.isNew).length,
        page: cur.page,
        lastPage: cur.lastPage,
        total: cur.total,
        onDuty: cur.onDuty,
      ),
    );
    ref.invalidate(peerHomeProvider);
  }
}

final peerAlertsProvider = AsyncNotifierProvider.autoDispose<PeerAlertsController, PeerAlertsPage>(
  PeerAlertsController.new,
);

class PeerAlertController extends AutoDisposeFamilyAsyncNotifier<PeerAlertDetail, String> {
  @override
  Future<PeerAlertDetail> build(String arg) => ref.watch(pairApiProvider).alert(arg);

  Future<void> _apply(Future<PeerAlertDetail> Function() call) async {
    final d = await call();
    state = AsyncData(d);
    ref.invalidate(peerAlertsProvider);
    ref.invalidate(peerHomeProvider);
  }

  Future<void> acknowledge() => _apply(() => ref.read(pairApiProvider).acknowledge(arg));

  /// Prendre en charge = statut `en_cours` (vaut accusé s'il manque).
  Future<void> take() => _apply(() => ref.read(pairApiProvider).setStatus(arg, 'en_cours'));
  Future<void> markTreated() => _apply(() => ref.read(pairApiProvider).setStatus(arg, 'traite'));
  Future<void> close() => _apply(() => ref.read(pairApiProvider).setStatus(arg, 'clos'));
}

final peerAlertProvider =
    AsyncNotifierProvider.autoDispose.family<PeerAlertController, PeerAlertDetail, String>(PeerAlertController.new);

// --- règles et listes ------------------------------------------------------------

/// Règles actives (ETag) : relues si le réseau est là, copie chiffrée sinon.
final validationRulesProvider = FutureProvider.autoDispose<ValidationRules>((ref) async {
  final api = ref.watch(pairApiProvider);
  if (ref.watch(isOnlineProvider)) {
    try {
      final entry = await ref.watch(syncEngineProvider).refreshRules();
      if (entry != null) return ValidationRules.fromBody(entry['body']);
    } catch (_) {}
  }
  return api.rulesLocal();
});

final collectListsProvider = FutureProvider.autoDispose<CollectLists>(
  (ref) => ref.watch(pairApiProvider).lists(),
);
