import 'dart:convert';

import 'package:dio/dio.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/storage/local_db.dart';
import 'ccc_models.dart';

/// Durée pendant laquelle la copie locale de `GET ccc` peut être servie sans réseau
/// (D-MOB-19). Au-delà, elle n'est plus affichée (message « copie trop ancienne »)
/// et supprimée : un outil retiré par l'équipe ne doit pas survivre indéfiniment
/// sur un téléphone qui ne se synchronise plus. Seule constante à changer.
const Duration kCccOfflineRetention = Duration(days: 30);

/// Tolérance sur l'horloge : une copie datée de plus d'une heure dans le futur
/// (horloge reculée) est considérée comme périmée, comme le coffre du pair.
const Duration _clockSkew = Duration(hours: 1);

/// Préfixe des clés de copie dans la boîte privée (vidée à la déconnexion).
const String kCccCopyKeyPrefix = 'ccc:v1:';

String cccCopyKey(int account) => '$kCccCopyKeyPrefix$account';

/// Résultat d'une lecture : le catalogue, et d'où il vient.
class CccLoad {
  const CccLoad({required this.catalog, required this.syncedAt, required this.fromCopy});
  final CccCatalog catalog;

  /// Date de la dernière synchronisation réussie dont vient ce contenu.
  final DateTime syncedAt;

  /// Vrai si le réseau a échoué et que la copie locale est servie.
  final bool fromCopy;
}

/// La copie locale existe mais dépasse [kCccOfflineRetention] : elle n'est pas servie.
class CccCopyExpiredException implements Exception {
  const CccCopyExpiredException(this.syncedAt);
  final DateTime syncedAt;
  @override
  String toString() => 'CccCopyExpiredException($syncedAt)';
}

/// Copie locale de la dernière réponse réussie de `GET ccc`, **par compte**.
///
/// Le serveur répond `no-store, private` pour que la lecture suive la publication :
/// `ApiCache` n'en garde donc rien. La copie est volontairement à part et suit
/// cette règle : réseau d'abord ; un succès **remplace intégralement** la copie
/// (un outil masqué disparaît à la prochaine synchro réussie) ; panne réseau,
/// délai dépassé ou 5xx → copie (si elle a moins de [kCccOfflineRetention]) ;
/// 401 / 403 / 404 → jamais la copie (le droit a changé), qui est purgée.
class CccRepository {
  CccRepository({required this.dio, required this.box, required this.account, DateTime Function()? now})
      : now = now ?? DateTime.now;

  final Dio dio;
  final StringBox box;
  final int? account;
  final DateTime Function() now;

  Future<CccLoad> load() async {
    try {
      final res = await dio.get<dynamic>('ccc');
      final catalog = CccCatalog.fromJson(res.data);
      final at = now();
      await _save(res.data, at);
      return CccLoad(catalog: catalog, syncedAt: at, fromCopy: false);
    } on DioException catch (e) {
      final api = ApiException.from(e);
      final s = api.status;
      if (!api.isNetwork && (s == 401 || s == 403 || s == 404)) {
        await purge();
        rethrow;
      }
      if (api.isNetwork || (s != null && s >= 500)) {
        final copy = await _readCopy();
        if (copy != null) return copy;
      }
      rethrow;
    }
  }

  /// Copie servable, `null` s'il n'y en a pas ; [CccCopyExpiredException] si trop ancienne.
  Future<CccLoad?> _readCopy() async {
    final account = this.account;
    if (account == null) return null;
    final key = cccCopyKey(account);
    final raw = box.get(key);
    if (raw == null) return null;
    try {
      final m = jsonDecode(raw) as Map;
      final at = DateTime.parse(m['synced_at'] as String);
      final age = now().difference(at);
      if (age > kCccOfflineRetention || age < -_clockSkew) {
        await box.delete(key);
        throw CccCopyExpiredException(at);
      }
      return CccLoad(catalog: CccCatalog.fromJson(m['body']), syncedAt: at, fromCopy: true);
    } on CccCopyExpiredException {
      rethrow;
    } catch (_) {
      await box.delete(key); // copie illisible : comme s'il n'y en avait pas
      return null;
    }
  }

  Future<void> _save(Object? body, DateTime at) async {
    final account = this.account;
    if (account == null) return;
    try {
      await box.put(cccCopyKey(account), jsonEncode({'synced_at': at.toUtc().toIso8601String(), 'body': body}));
      // Appareil partagé : la copie d'un autre compte n'a rien à faire ici.
      for (final k in box.keys.toList()) {
        if (k.startsWith(kCccCopyKeyPrefix) && k != cccCopyKey(account)) await box.delete(k);
      }
    } catch (_) {
      // Écriture refusée : la lecture réseau reste valable, sans copie.
    }
  }

  /// Supprime la copie du compte courant.
  Future<void> purge() async {
    final account = this.account;
    if (account == null) return;
    try {
      await box.delete(cccCopyKey(account));
    } catch (_) {}
  }
}

/// Toutes les adresses d'images d'un catalogue (couvertures et planches), sans doublon.
Set<String> cccImageUrls(CccCatalog c) => {
      for (final t in [...c.imageBoxes, ...c.comics]) ...[?t.cover, for (final p in t.pages) p.image],
    };

/// Adresses d'images d'un outil.
Set<String> cccToolImageUrls(PagedTool t) => {?t.cover, for (final p in t.pages) p.image};
