import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:uuid/uuid.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/providers/core_providers.dart';
import '../../../core/sync/sync_providers.dart';
import '../../../core/sync/sync_queue.dart';
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
  const PeerHome({
    required this.section,
    this.onDuty = false,
    this.stale = false,
  });
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
    return PeerHome(
      section: _localSection(store.rows('cases'), store.rows('alerts')),
      stale: true,
    );
  }
});

PeerSection _localSection(
  List<Map<String, dynamic>> cases,
  List<Map<String, dynamic>> alerts,
) {
  final now = DateTime.now();
  final live = [
    for (final c in cases)
      if (c['status'] != 'archive') c,
  ];
  final month = live.where((c) {
    final d = DateTime.tryParse('${c['created_at']}')?.toLocal();
    return d != null && d.year == now.year && d.month == now.month;
  }).length;
  final open = alerts
      .where((a) => a['status'] != 'clos' && a['status'] != 'traite')
      .toList();
  final fresh = open
      .where((a) => a['status'] == 'nouveau' && a['acknowledged'] != true)
      .length;
  final toContact = live.where((c) => c['status'] == 'ouvert').length;
  return PeerSection(
    kpi: PeerKpi(
      collected: live.length,
      thisMonth: month,
      inProgress: live
          .where((c) => c['status'] == 'ouvert' || c['status'] == 'en_cours')
          .length,
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
          PeerAlertsPage(
            items: cur.items,
            newCount: cur.newCount,
            page: cur.page,
            lastPage: cur.lastPage,
            stale: true,
            onDuty: cur.onDuty,
          ),
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

final peerAlertsProvider =
    AsyncNotifierProvider.autoDispose<PeerAlertsController, PeerAlertsPage>(
      PeerAlertsController.new,
    );

class PeerAlertController
    extends AutoDisposeFamilyAsyncNotifier<PeerAlertDetail, String> {
  @override
  Future<PeerAlertDetail> build(String arg) =>
      ref.watch(pairApiProvider).alert(arg);

  Future<void> _apply(Future<PeerAlertDetail> Function() call) async {
    final d = await call();
    state = AsyncData(d);
    ref.invalidate(peerAlertsProvider);
    ref.invalidate(peerHomeProvider);
  }

  Future<void> acknowledge() =>
      _apply(() => ref.read(pairApiProvider).acknowledge(arg));

  /// Prendre en charge = statut `en_cours` (vaut accusé s'il manque).
  Future<void> take() =>
      _apply(() => ref.read(pairApiProvider).setStatus(arg, 'en_cours'));
  Future<void> markTreated() =>
      _apply(() => ref.read(pairApiProvider).setStatus(arg, 'traite'));
  Future<void> close() =>
      _apply(() => ref.read(pairApiProvider).setStatus(arg, 'clos'));
}

final peerAlertProvider = AsyncNotifierProvider.autoDispose
    .family<PeerAlertController, PeerAlertDetail, String>(
      PeerAlertController.new,
    );

// --- règles et listes ------------------------------------------------------------

/// Règles actives (ETag) : relues si le réseau est là, copie chiffrée sinon.
final validationRulesProvider = FutureProvider.autoDispose<ValidationRules>((
  ref,
) async {
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

// --- dossiers ------------------------------------------------------------------

class CasesQuery {
  const CasesQuery({this.q = '', this.status = '', this.kind = ''});
  final String q;
  final String status;
  final String kind;
  bool get filtered =>
      q.trim().isNotEmpty || status.isNotEmpty || kind.isNotEmpty;

  @override
  bool operator ==(Object other) =>
      other is CasesQuery &&
      other.q == q &&
      other.status == status &&
      other.kind == kind;
  @override
  int get hashCode => Object.hash(q, status, kind);
}

class PeerCasesController
    extends AutoDisposeFamilyAsyncNotifier<PeerCasesPage, CasesQuery> {
  @override
  Future<PeerCasesPage> build(CasesQuery arg) => ref
      .watch(pairApiProvider)
      .cases(q: arg.q, status: arg.status, kind: arg.kind);

  Future<void> loadMore() async {
    final cur = state.valueOrNull;
    if (cur == null || !cur.hasMore) return;
    final next = await ref
        .read(pairApiProvider)
        .cases(
          q: arg.q,
          status: arg.status,
          kind: arg.kind,
          page: cur.page + 1,
        );
    state = AsyncData(
      PeerCasesPage(
        items: [...cur.items, ...next.items],
        page: next.page,
        lastPage: next.lastPage,
        total: next.total,
      ),
    );
  }
}

final peerCasesProvider = AsyncNotifierProvider.autoDispose
    .family<PeerCasesController, PeerCasesPage, CasesQuery>(
      PeerCasesController.new,
    );

/// Dossiers en file (collecte hors ligne), mis à jour avec la file.
final queuedCasesProvider = Provider.autoDispose<List<PeerCase>>((ref) {
  ref.watch(syncQueueEntriesProvider);
  return ref.watch(pairApiProvider).queuedCases();
});

final peerCaseProvider = FutureProvider.autoDispose.family<PeerCase, String>((
  ref,
  code,
) {
  // La fiche se relit quand la file change (suivi parti, confirmé).
  ref.watch(syncQueueEntriesProvider);
  return ref.watch(pairApiProvider).caseDetail(code);
});

/// Issue d'une écriture : faite tout de suite, ou gardée dans la file.
enum ActionOutcome { done, queued }

/// Écritures du pair sur ses dossiers. En ligne : immédiat ; sans réseau : la
/// file chiffrée, qui repart seule (jamais de doublon : l'uuid est la clé).
class PairActions {
  PairActions(this.ref);
  final Ref ref;

  Dio get _dio => ref.read(dioProvider);

  /// Change le statut (`PATCH pair/cases/{code}`) ; hors réseau, modification
  /// en file avec la valeur d'origine (`base`) pour détecter un conflit.
  Future<ActionOutcome> changeStatus(PeerCase c, String status) async {
    try {
      await _dio.patch<dynamic>(
        'pair/cases/${Uri.encodeComponent(c.code)}',
        data: {'status': status},
      );
      ref.invalidate(peerCaseProvider(c.code));
      ref.invalidate(peerHomeProvider);
      return ActionOutcome.done;
    } on DioException catch (e) {
      if (!ApiException.from(e).isNetwork) rethrow;
      await ref
          .read(syncQueueProvider)
          .save(
            QueueEntry(
              uuid: const Uuid().v4(),
              kind: QueueKind.caseUpdate,
              createdAt: DateTime.now(),
              caseCode: c.code,
              payload: {
                'code': c.code,
                'changes': {'status': status},
                'base': {'status': c.status},
              },
              summary: {'code': c.code, 'field': 'status', 'value': status},
            ),
          );
      return ActionOutcome.queued;
    }
  }

  /// « Marquer comme réalisée » (`PATCH …/followups/{id}`), sinon en file.
  Future<ActionOutcome> markDone(PeerCase c, FollowupItem f) async {
    try {
      await _dio.patch<dynamic>(
        'pair/cases/${Uri.encodeComponent(c.code)}/followups/${f.id}',
        data: {'outcome': 'realise'},
      );
      ref.invalidate(peerCaseProvider(c.code));
      return ActionOutcome.done;
    } on DioException catch (e) {
      if (!ApiException.from(e).isNetwork) rethrow;
      await ref
          .read(syncQueueProvider)
          .save(
            QueueEntry(
              uuid: const Uuid().v4(),
              kind: QueueKind.followupDone,
              createdAt: DateTime.now(),
              caseCode: c.code,
              payload: {'id': f.id, 'outcome': 'realise'},
              summary: {'code': c.code, 'step': f.step},
            ),
          );
      return ActionOutcome.queued;
    }
  }

  /// Ajoute une étape : écrite d'abord dans la file (clé d'idempotence), puis
  /// envoyée tout de suite si le réseau est là. Rend l'entrée telle qu'elle est
  /// après l'envoi : `sent`, `failed` (erreurs par champ) ou toujours en file.
  Future<QueueEntry> addFollowup(
    String caseCode,
    Map<String, dynamic> payload,
  ) async {
    final q = ref.read(syncQueueProvider);
    final entry = QueueEntry(
      uuid: const Uuid().v4(),
      kind: QueueKind.followupCreate,
      createdAt: DateTime.now(),
      caseCode: caseCode,
      payload: payload,
      summary: {'code': caseCode, 'step': payload['step']},
    );
    await q.save(entry);
    await _flush();
    ref.invalidate(peerCaseProvider(caseCode));
    return q.find(entry.uuid) ?? entry;
  }

  /// Envoie la file maintenant (attend la fin d'un passage en cours).
  Future<void> _flush() async {
    if (!ref.read(isOnlineProvider)) return;
    final engine = ref.read(syncEngineProvider);
    for (var i = 0; i < 100 && engine.running; i++) {
      await Future<void>.delayed(const Duration(milliseconds: 100));
    }
    await engine.sync(force: true, pushOnly: true);
  }
}

final pairActionsProvider = Provider<PairActions>(PairActions.new);
