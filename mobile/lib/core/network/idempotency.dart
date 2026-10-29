/// Routes qui exigent un `Idempotency-Key` (docs/API.md §1.3, ADR-32) :
/// `POST signalements`, `POST sos`, `POST sync/push`, `POST me/role-requests` (D-MOB-15).
bool needsIdempotencyKey(String method, String path) {
  if (method.toUpperCase() != 'POST') return false;
  var p = path;
  final q = p.indexOf('?');
  if (q >= 0) p = p.substring(0, q);
  p = p.replaceAll(RegExp(r'^/+|/+$'), '');
  p = p.replaceFirst(RegExp(r'^(api/)?v1/'), '');
  return p == 'signalements' || p == 'sos' || p == 'sync/push' || p == 'me/role-requests';
}

/// Résultat d'un élément de `POST sync/push` : 2xx, 409 et 4xx sortent de la
/// boîte d'envoi ; `duplicate` est un succès (déjà reçu : jamais de doublon).
enum SyncItemOutcome { created, updated, duplicate, conflict, rejected, unknown }

SyncItemOutcome syncOutcome(String? result) => switch (result) {
      'created' => SyncItemOutcome.created,
      'updated' => SyncItemOutcome.updated,
      'duplicate' => SyncItemOutcome.duplicate,
      'conflict' => SyncItemOutcome.conflict,
      'rejected' => SyncItemOutcome.rejected,
      _ => SyncItemOutcome.unknown,
    };

bool isSyncSuccess(String? result) {
  final o = syncOutcome(result);
  return o == SyncItemOutcome.created ||
      o == SyncItemOutcome.updated ||
      o == SyncItemOutcome.duplicate;
}

/// Faut-il garder l'élément dans la boîte d'envoi ? `429` et `5xx` : oui, il
/// repart dans un nouveau lot ; tout le reste (2xx, 409, autres 4xx) : non.
bool keepInOutbox(int? status, String? code) {
  if (status == null) return true;
  if (status == 429) return true;
  if (status >= 500) return true;
  return code == 'too_many_requests' || code == 'retry_later';
}
