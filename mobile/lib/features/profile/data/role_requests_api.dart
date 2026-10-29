import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:uuid/uuid.dart';

import '../../../core/providers/core_providers.dart';

/// Statuts d'une demande de rôle (`me/role-requests`, D-MOB-15).
enum RoleRequestStatus {
  pending,
  approved,
  rejected,
  cancelled,
  unknown;

  static RoleRequestStatus parse(Object? v) => switch (v) {
        'pending' => pending,
        'approved' => approved,
        'rejected' => rejected,
        'cancelled' => cancelled,
        _ => unknown,
      };
}

/// Demande du rôle pair vue par la personne qui l'a faite : jamais le compte
/// qui a décidé (docs/API.md §23, `RoleRequestResource`).
class RoleRequest {
  const RoleRequest({
    required this.id,
    required this.role,
    required this.status,
    this.reason,
    this.decisionReason,
    this.createdAt,
    this.decidedAt,
  });

  final String id;
  final String role;
  final RoleRequestStatus status;
  final String? reason;

  /// Motif de la décision, écrit pour la personne (refus).
  final String? decisionReason;
  final DateTime? createdAt;
  final DateTime? decidedAt;

  bool get isPending => status == RoleRequestStatus.pending;

  factory RoleRequest.fromJson(Map<String, dynamic> j) => RoleRequest(
        id: (j['id'] as String?) ?? '',
        role: (j['role'] as String?) ?? 'pair',
        status: RoleRequestStatus.parse(j['status']),
        reason: j['reason'] as String?,
        decisionReason: j['decision_reason'] as String?,
        createdAt: DateTime.tryParse((j['created_at'] as String?) ?? ''),
        decidedAt: DateTime.tryParse((j['decided_at'] as String?) ?? ''),
      );
}

/// `GET me/role-requests` : mes demandes (récentes d'abord) et `meta.can_request`
/// (faux : déjà pair·e, ou une demande est en cours).
class RoleRequestsView {
  const RoleRequestsView({required this.items, required this.canRequest});
  final List<RoleRequest> items;
  final bool canRequest;

  RoleRequest? get pending => items.where((e) => e.isPending).firstOrNull;

  /// Dernière demande refusée (pour afficher le motif), tant qu'aucune n'est en cours.
  RoleRequest? get lastRejected =>
      pending != null ? null : items.where((e) => e.status == RoleRequestStatus.rejected).firstOrNull;

  factory RoleRequestsView.fromJson(Map<String, dynamic> body) {
    final data = body['data'];
    final meta = body['meta'];
    return RoleRequestsView(
      items: [
        if (data is List)
          for (final e in data)
            if (e is Map) RoleRequest.fromJson(Map<String, dynamic>.from(e)),
      ],
      canRequest: meta is Map && meta['can_request'] == true,
    );
  }
}

/// Demande du rôle pair depuis le profil. Jamais d'auto-attribution : la
/// décision est celle d'un·e admin ; le corps n'envoie que le motif facultatif
/// (≤ 200 caractères) et la requête porte un `Idempotency-Key` (obligatoire).
class RoleRequestsApi {
  RoleRequestsApi(this._dio);
  final Dio _dio;

  static const int reasonMax = 200;

  Future<RoleRequestsView> list() async {
    final res = await _dio.get<dynamic>('me/role-requests');
    return RoleRequestsView.fromJson(Map<String, dynamic>.from(res.data as Map));
  }

  /// `idempotencyKey` fourni par l'appelant : un nouvel essai après coupure
  /// réseau réutilise la même clé (jamais de doublon).
  Future<RoleRequest> request({String? reason, String? idempotencyKey}) async {
    final text = reason?.trim();
    final res = await _dio.post<dynamic>(
      'me/role-requests',
      data: {if (text != null && text.isNotEmpty) 'reason': text.length > reasonMax ? text.substring(0, reasonMax) : text},
      options: Options(headers: {'Idempotency-Key': idempotencyKey ?? const Uuid().v4()}),
    );
    final data = (res.data as Map)['data'];
    return RoleRequest.fromJson(Map<String, dynamic>.from(data as Map));
  }
}

final roleRequestsApiProvider = Provider<RoleRequestsApi>((ref) => RoleRequestsApi(ref.watch(dioProvider)));

/// Mes demandes de rôle ; relu à l'ouverture de la carte « Mes espaces ».
final roleRequestsProvider = FutureProvider.autoDispose<RoleRequestsView>(
  (ref) => ref.watch(roleRequestsApiProvider).list(),
);
