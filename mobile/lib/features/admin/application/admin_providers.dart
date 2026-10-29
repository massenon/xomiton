import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../data/admin_api.dart';
import '../data/admin_models.dart';

/// Interrupteur du rafraîchissement automatique au premier plan (coupé dans les tests).
final adminPollEnabledProvider = Provider<bool>((_) => true);

/// Secondes entre deux relectures des alertes au premier plan (plan P15-17).
const kAdminAlertsPollSeconds = 10;

/// Minutes sans accusé avant l'escalade nationale d'un SOS (D-MOB-10).
const kSosEscalationMinutes = 5;

// --- tableau d'astreinte ---------------------------------------------------------------

/// Tableau d'astreinte (A1) : compteurs, SOS actifs, maintenance. Rien n'est écrit sur l'appareil.
class AdminDuty {
  const AdminDuty({required this.dashboard, this.sos = const [], this.stale = false});
  final AdminDashboard dashboard;

  /// SOS ouverts, les plus récents d'abord.
  final List<AdminAlert> sos;
  final bool stale;
}

class AdminDutyController extends AutoDisposeAsyncNotifier<AdminDuty> {
  @override
  Future<AdminDuty> build() => _load();

  Future<AdminDuty> _load() async {
    final api = ref.read(adminApiProvider);
    final d = await api.dashboard();
    var sos = const <AdminAlert>[];
    if (d.alerts != null && d.alerts!.sosActive > 0) {
      sos = (await api.alerts(type: 'sos')).items;
    }
    return AdminDuty(dashboard: d, sos: sos);
  }

  /// Relecture silencieuse (polling) : l'écran reste en place ; une erreur
  /// garde la dernière lecture, marquée périmée.
  Future<void> refresh() async {
    final cur = state.valueOrNull;
    try {
      state = AsyncData(await _load());
    } catch (e) {
      state = cur != null
          ? AsyncData(AdminDuty(dashboard: cur.dashboard, sos: cur.sos, stale: true))
          : AsyncError(e, StackTrace.current);
    }
  }

  Future<void> acknowledge(String id) async {
    await ref.read(adminApiProvider).acknowledge(id);
    ref.invalidate(adminAlertsProvider);
    await refresh();
  }
}

final adminDutyProvider = AsyncNotifierProvider.autoDispose<AdminDutyController, AdminDuty>(AdminDutyController.new);

// --- alertes ---------------------------------------------------------------------------------

/// Filtres de la liste (A2) : onglet (`all`, `sos`, `report`, `need`) et statut
/// (`''` = ouvertes, `all` = toutes, ou un statut précis).
class AdminAlertFilter {
  const AdminAlertFilter({this.type = 'all', this.status = '', this.query = ''});
  final String type;
  final String status;
  final String query;

  AdminAlertFilter copyWith({String? type, String? status, String? query}) =>
      AdminAlertFilter(type: type ?? this.type, status: status ?? this.status, query: query ?? this.query);

  bool get active => type != 'all' || status.isNotEmpty || query.isNotEmpty;
}

final adminAlertFilterProvider = StateProvider.autoDispose<AdminAlertFilter>((_) => const AdminAlertFilter());

class AdminAlertsController extends AutoDisposeAsyncNotifier<AdminAlertsPage> {
  @override
  Future<AdminAlertsPage> build() {
    final f = ref.watch(adminAlertFilterProvider);
    return _fetch(f);
  }

  Future<AdminAlertsPage> _fetch(AdminAlertFilter f, {int page = 1}) => ref
      .read(adminApiProvider)
      .alerts(
        type: f.type == 'all' ? null : f.type,
        // Par défaut les alertes ouvertes ; « Toutes » et un statut précis incluent les closes.
        status: f.status == 'all' ? null : f.status,
        all: f.status.isNotEmpty,
        page: page,
      );

  Future<void> refresh() async {
    final cur = state.valueOrNull;
    try {
      state = AsyncData(await _fetch(ref.read(adminAlertFilterProvider)));
    } catch (e) {
      if (cur == null) state = AsyncError(e, StackTrace.current);
    }
  }

  Future<void> loadMore() async {
    final cur = state.valueOrNull;
    if (cur == null || !cur.hasMore) return;
    final next = await _fetch(ref.read(adminAlertFilterProvider), page: cur.page + 1);
    state = AsyncData(
      AdminAlertsPage(
        items: [...cur.items, ...next.items],
        fresh: next.fresh,
        page: next.page,
        lastPage: next.lastPage,
        total: next.total,
      ),
    );
  }

  Future<void> acknowledge(String id) async {
    await ref.read(adminApiProvider).acknowledge(id);
    ref.invalidate(adminDutyProvider);
    await refresh();
  }
}

final adminAlertsProvider = AsyncNotifierProvider.autoDispose<AdminAlertsController, AdminAlertsPage>(
  AdminAlertsController.new,
);

class AdminAlertController extends AutoDisposeFamilyAsyncNotifier<AdminAlertDetail, String> {
  @override
  Future<AdminAlertDetail> build(String arg) => ref.read(adminApiProvider).alert(arg);

  Future<void> _apply(Future<AdminAlertDetail> Function() call) async {
    final d = await call();
    state = AsyncData(d);
    ref.invalidate(adminAlertsProvider);
    ref.invalidate(adminDutyProvider);
  }

  Future<void> acknowledge() => _apply(() => ref.read(adminApiProvider).acknowledge(arg));
  Future<void> take() => _apply(() => ref.read(adminApiProvider).take(arg));
  Future<void> setStatus(String status) => _apply(() => ref.read(adminApiProvider).setStatus(arg, status));
  Future<void> close() => _apply(() => ref.read(adminApiProvider).close(arg));
  Future<void> addNote(String body) => _apply(() => ref.read(adminApiProvider).addNote(arg, body));
  Future<void> reassign(int contactId, String stepUp) =>
      _apply(() => ref.read(adminApiProvider).reassign(arg, contactId, stepUp: stepUp));
}

final adminAlertProvider = AsyncNotifierProvider.autoDispose
    .family<AdminAlertController, AdminAlertDetail, String>(AdminAlertController.new);

/// Contacts proposés à la réattribution (même département d'abord).
final adminContactsProvider = FutureProvider.autoDispose.family<List<AdminContact>, (String, bool)>(
  (ref, args) => ref.read(adminApiProvider).contacts(args.$1, allZones: args.$2),
);

// --- personnes, modération, maintenance ------------------------------------------------------------

class AdminRegistrationsController extends AutoDisposeAsyncNotifier<List<AdminRegistration>> {
  @override
  Future<List<AdminRegistration>> build() => ref.read(adminApiProvider).registrations();

  /// La ligne traitée disparaît tout de suite ; la liste est relue ensuite.
  void remove(AdminRegistration r) {
    final cur = state.valueOrNull;
    if (cur != null) state = AsyncData([for (final x in cur) if (x.key != r.key) x]);
    ref.invalidate(adminDutyProvider);
  }
}

final adminRegistrationsProvider =
    AsyncNotifierProvider.autoDispose<AdminRegistrationsController, List<AdminRegistration>>(
      AdminRegistrationsController.new,
    );

final adminMembersProvider = FutureProvider.autoDispose.family<List<AdminMember>, String>(
  (ref, q) => q.trim().length < 2 ? Future.value(const <AdminMember>[]) : ref.read(adminApiProvider).members(q.trim()),
);

final adminInvitationsProvider = FutureProvider.autoDispose<List<AdminInvitation>>(
  (ref) => ref.read(adminApiProvider).invitations(),
);

final adminReportsProvider = FutureProvider.autoDispose.family<List<AdminReport>, String>(
  (ref, state) => ref.read(adminApiProvider).reports(state: state),
);

final adminReportProvider = FutureProvider.autoDispose.family<AdminReport, String>(
  (ref, id) => ref.read(adminApiProvider).report(id),
);

final adminMaintenanceProvider = FutureProvider.autoDispose<AdminMaintenance>(
  (ref) => ref.read(adminApiProvider).maintenance(),
);

/// Notifications de l'administration : filtre (`all`, `unread`) et catégorie.
final adminNotifFilterProvider = StateProvider.autoDispose<String>((_) => 'all');
final adminNotifCategoryProvider = StateProvider.autoDispose<String>((_) => '');

final adminNotificationsProvider = FutureProvider.autoDispose<List<Map<String, dynamic>>>(
  (ref) => ref.read(adminApiProvider).notifications(
    filter: ref.watch(adminNotifFilterProvider),
    category: ref.watch(adminNotifCategoryProvider),
  ),
);

/// Compteurs seuls (menu « Gérer »).
final adminDashboardProvider = FutureProvider.autoDispose<AdminDashboard>(
  (ref) => ref.read(adminApiProvider).dashboard(),
);
