import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/providers/core_providers.dart';
import 'admin_models.dart';

Map<String, dynamic> _map(Object? v) => v is Map ? Map<String, dynamic>.from(v) : <String, dynamic>{};
List<Map<String, dynamic>> _list(Object? v) =>
    v is List ? v.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList() : const [];
int? _int(Object? v) => v is num ? v.toInt() : int.tryParse('$v');

/// En-tête du jeton de confirmation à usage unique (API §23.1).
const kStepUpHeader = 'X-Step-Up-Token';

Options _stepUp(String? token) => Options(headers: {kStepUpHeader: ?token});

/// API de l'espace Admin (docs/API.md §23). Aucune copie locale : une lecture
/// est toujours faite au réseau, et rien n'est gardé sur l'appareil. Les
/// écritures portent une `Idempotency-Key` (posée par l'intercepteur) ; les
/// actions à fort impact portent en plus `X-Step-Up-Token`.
class AdminApi {
  AdminApi(this._dio);
  final Dio _dio;

  Future<Map<String, dynamic>> _data(Future<Response<dynamic>> r) async => _map(_map((await r).data)['data']);

  // --- confirmation et tableau de bord ---------------------------------------------------

  /// Mot de passe ressaisi → jeton à usage unique (5 min).
  Future<String> stepUp(String password) async {
    final d = await _data(_dio.post<dynamic>('admin/step-up', data: {'password': password}));
    return '${d['token']}';
  }

  Future<AdminDashboard> dashboard() async => AdminDashboard.fromJson(await _data(_dio.get<dynamic>('admin/dashboard')));

  // --- alertes ------------------------------------------------------------------------------

  Future<AdminAlertsPage> alerts({
    String? type,
    String? status,
    String? priority,
    bool unacknowledged = false,
    bool all = false,
    int page = 1,
  }) async {
    final res = await _dio.get<dynamic>(
      'admin/alerts',
      queryParameters: {
        'type': ?type,
        if (status != null && status.isNotEmpty) 'status': status,
        'priority': ?priority,
        if (unacknowledged) 'unacknowledged': 1,
        if (all) 'state': 'all',
        'page': page,
      },
    );
    final body = _map(res.data);
    final meta = _map(body['meta']);
    final items = [for (final r in _list(body['data'])) AdminAlert.fromJson(r)];
    return AdminAlertsPage(
      items: items,
      fresh: _int(meta['new']) ?? 0,
      page: _int(meta['current_page']) ?? page,
      lastPage: _int(meta['last_page']) ?? 1,
      total: _int(meta['total']),
    );
  }

  AdminAlertDetail _alert(Response<dynamic> r) => AdminAlertDetail.fromJson(_map(_map(r.data)['data']));

  String _a(String id, [String tail = '']) => 'admin/alerts/${Uri.encodeComponent(id)}$tail';

  Future<AdminAlertDetail> alert(String id) async => _alert(await _dio.get<dynamic>(_a(id)));
  Future<AdminAlertDetail> acknowledge(String id) async => _alert(await _dio.post<dynamic>(_a(id, '/acknowledge')));
  Future<AdminAlertDetail> take(String id) async => _alert(await _dio.post<dynamic>(_a(id, '/take')));
  Future<AdminAlertDetail> setStatus(String id, String status) async =>
      _alert(await _dio.post<dynamic>(_a(id, '/status'), data: {'status': status}));
  Future<AdminAlertDetail> close(String id) async => _alert(await _dio.post<dynamic>(_a(id, '/close')));
  Future<AdminAlertDetail> addNote(String id, String body) async =>
      _alert(await _dio.post<dynamic>(_a(id, '/notes'), data: {'body': body}));

  /// Réattribution : `step-up` obligatoire.
  Future<AdminAlertDetail> reassign(String id, int contactId, {required String stepUp}) async =>
      _alert(await _dio.post<dynamic>(_a(id, '/reassign'), data: {'contact_id': contactId}, options: _stepUp(stepUp)));

  Future<List<AdminContact>> contacts(String id, {bool allZones = false}) async {
    final res = await _dio.get<dynamic>(_a(id, '/contacts'), queryParameters: {if (allZones) 'all_zones': 1});
    return [for (final r in _list(_map(res.data)['data'])) AdminContact.fromJson(r)];
  }

  // --- inscriptions et demandes de rôle -----------------------------------------------------------

  /// Inscriptions à valider, puis demandes du rôle pair (file distincte, droit `peers.assign` :
  /// sans lui la file est vide, jamais une erreur).
  Future<List<AdminRegistration>> registrations() async {
    final out = <AdminRegistration>[];
    final res = await _dio.get<dynamic>('admin/registrations', queryParameters: {'per_page': 50});
    for (final r in _list(_map(res.data)['data'])) {
      out.add(AdminRegistration.fromRegistration(r));
    }
    try {
      final rr = await _dio.get<dynamic>('admin/role-requests', queryParameters: {'status': 'pending', 'per_page': 50});
      for (final r in _list(_map(rr.data)['data'])) {
        out.add(AdminRegistration.fromRoleRequest(r));
      }
    } on DioException catch (e) {
      if (e.response?.statusCode != 403) rethrow;
    }
    return out;
  }

  Future<void> approveRegistration(int userId) => _dio.post<dynamic>('admin/registrations/$userId/approve');

  /// Refus d'une inscription : motif écrit pour la personne, `step-up`.
  Future<void> rejectRegistration(int userId, String reason, {required String stepUp}) =>
      _dio.post<dynamic>('admin/registrations/$userId/reject', data: {'reason': reason}, options: _stepUp(stepUp));

  Future<void> approveRoleRequest(String id, {required String stepUp}) =>
      _dio.post<dynamic>('admin/role-requests/${Uri.encodeComponent(id)}/approve', options: _stepUp(stepUp));

  Future<void> rejectRoleRequest(String id, String reason, {required String stepUp}) => _dio.post<dynamic>(
    'admin/role-requests/${Uri.encodeComponent(id)}/reject',
    data: {'reason': reason},
    options: _stepUp(stepUp),
  );

  // --- membres, rôle pair, invitations --------------------------------------------------------------

  Future<List<AdminMember>> members(String q) async {
    final res = await _dio.get<dynamic>('admin/members', queryParameters: {'q': q});
    return [for (final r in _list(_map(res.data)['data'])) AdminMember.fromJson(r)];
  }

  /// Le SEUL rôle attribuable depuis le mobile est « pair » : il n'existe aucune
  /// méthode qui prenne un nom de rôle (le rôle admin ne se donne que sur le web).
  Future<void> grantPeerRole(int memberId, {required String stepUp}) =>
      _dio.post<dynamic>('admin/members/$memberId/roles/peer', options: _stepUp(stepUp));

  Future<void> revokePeerRole(int memberId, {String? reason, required String stepUp}) => _dio.delete<dynamic>(
    'admin/members/$memberId/roles/peer',
    data: {if (reason != null && reason.isNotEmpty) 'reason': reason},
    options: _stepUp(stepUp),
  );

  Future<List<AdminInvitation>> invitations({String status = 'pending'}) async {
    final res = await _dio.get<dynamic>('admin/invitations', queryParameters: {'status': status});
    return [for (final r in _list(_map(res.data)['data'])) AdminInvitation.fromJson(r)];
  }

  /// Invitation d'un·e pair (rôle fixé à `pair`). Le code et le lien ne sont
  /// rendus qu'une fois.
  Future<AdminInvitation> createInvitation({String? email, String? note, required String stepUp}) async {
    final d = await _data(
      _dio.post<dynamic>(
        'admin/invitations',
        data: {
          'role': 'pair',
          if (email != null && email.isNotEmpty) 'email': email,
          if (note != null && note.isNotEmpty) 'note': note,
        },
        options: _stepUp(stepUp),
      ),
    );
    return AdminInvitation.fromJson(d);
  }

  Future<void> revokeInvitation(String id, {required String stepUp}) =>
      _dio.delete<dynamic>('admin/invitations/${Uri.encodeComponent(id)}', options: _stepUp(stepUp));

  // --- modération ------------------------------------------------------------------------------------

  Future<List<AdminReport>> reports({String state = 'pending'}) async {
    final res = await _dio.get<dynamic>('admin/moderation/reports', queryParameters: {'state': state});
    return [
      for (final r in _list(_map(res.data)['data']))
        // Un message privé n'est jamais proposé, même si le serveur en laissait passer un.
        if (AdminReport.fromJson(r) case final rep when !rep.isPrivateMessage) rep,
    ];
  }

  Future<AdminReport> report(String id) async =>
      AdminReport.fromJson(await _data(_dio.get<dynamic>('admin/moderation/reports/${Uri.encodeComponent(id)}')));

  /// `action` : `hide`, `keep` ou `warn` (`reason` obligatoire pour `warn`).
  Future<void> decide(String id, String action, {String? reason}) => _dio.post<dynamic>(
    'admin/moderation/reports/${Uri.encodeComponent(id)}/decision',
    data: {'action': action, if (reason != null && reason.isNotEmpty) 'reason': reason},
  );

  Future<void> restore(String id) =>
      _dio.post<dynamic>('admin/moderation/reports/${Uri.encodeComponent(id)}/restore');

  // --- maintenance -------------------------------------------------------------------------------------

  Future<AdminMaintenance> maintenance() async =>
      AdminMaintenance.fromJson(await _data(_dio.get<dynamic>('admin/maintenance')));

  Future<AdminMaintenance> enableMaintenance({
    required String reason,
    int? durationMinutes,
    String? message,
    required String stepUp,
  }) async => AdminMaintenance.fromJson(
    await _data(
      _dio.post<dynamic>(
        'admin/maintenance/enable',
        data: {
          'reason': reason,
          'duration_minutes': ?durationMinutes,
          if (message != null && message.isNotEmpty) 'message': message,
          'confirm': true,
        },
        options: _stepUp(stepUp),
      ),
    ),
  );

  Future<AdminMaintenance> disableMaintenance({required String stepUp}) async => AdminMaintenance.fromJson(
    await _data(_dio.post<dynamic>('admin/maintenance/disable', data: {'confirm': true}, options: _stepUp(stepUp))),
  );

  // --- notifications --------------------------------------------------------------------------------------

  Future<List<Map<String, dynamic>>> notifications({String filter = 'all', String? category}) async {
    final res = await _dio.get<dynamic>(
      'admin/notifications',
      queryParameters: {'filter': filter, if (category != null && category.isNotEmpty) 'category': category},
    );
    return _list(_map(res.data)['data']);
  }

  Future<void> markAllNotificationsRead() => _dio.post<dynamic>('admin/notifications/read-all');
}

final adminApiProvider = Provider<AdminApi>((ref) => AdminApi(ref.watch(dioProvider)));
