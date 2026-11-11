import 'dart:convert';
import 'dart:math';
import 'dart:typed_data';

import 'package:hive/hive.dart';

import 'secret_store.dart';

/// Coffre clé/valeur de chaînes JSON : Hive chiffré en production, mémoire en test.
abstract interface class StringBox {
  String? get(String key);
  Iterable<String> get keys;
  Future<void> put(String key, String value);
  Future<void> delete(String key);
  Future<void> clear();
}

class HiveStringBox implements StringBox {
  HiveStringBox(this._box);
  final Box<String> _box;
  @override
  String? get(String key) => _box.get(key);
  @override
  Iterable<String> get keys => _box.keys.cast<String>();
  @override
  Future<void> put(String key, String value) => _box.put(key, value);
  @override
  Future<void> delete(String key) => _box.delete(key);
  @override
  Future<void> clear() => _box.clear();
}

/// Coffre dont la boîte sous-jacente peut être remplacée (effacement par
/// destruction de la clé : voir [LocalDb.clearPeer]). Les lecteurs gardent la
/// même référence.
class SwappableStringBox implements StringBox {
  SwappableStringBox(this._inner);
  StringBox _inner;
  void swap(StringBox next) => _inner = next;
  @override
  String? get(String key) => _inner.get(key);
  @override
  Iterable<String> get keys => _inner.keys;
  @override
  Future<void> put(String key, String value) => _inner.put(key, value);
  @override
  Future<void> delete(String key) => _inner.delete(key);
  @override
  Future<void> clear() => _inner.clear();
}

class MemoryStringBox implements StringBox {
  final Map<String, String> data = {};
  @override
  String? get(String key) => data[key];
  @override
  Iterable<String> get keys => data.keys.toList();
  @override
  Future<void> put(String key, String value) async => data[key] = value;
  @override
  Future<void> delete(String key) async => data.remove(key);
  @override
  Future<void> clear() async => data.clear();
}

/// Hive chiffré (AES-256). La clé est générée au premier lancement et gardée
/// dans le stockage sécurisé, comme le jeton Sanctum.
///
/// Coffres, tous des `Box<String>` (JSON) pour éviter les adaptateurs :
/// - `cache_public` : référentiels et contenus publics (survit à la déconnexion) ;
/// - `cache_private` : réponses propres au compte (vidé à la déconnexion) ;
/// - `session` : utilisateur de la session (vidé à la déconnexion) ;
/// - `outbox` : boîte d'envoi (SOS, signalements, messages, publications) ;
///   les entrées rattachées à un compte partent à la déconnexion, jamais un SOS
///   ni un signalement anonyme en attente ;
/// - `drafts` : brouillons (sans contact ni position), vidé à la déconnexion ;
/// - `pair_data` : données de l'espace Pair (dossiers, suivis, alertes, règles
///   de validation, file de synchronisation, brouillon de collecte). **Clé
///   propre à l'espace** (`SecretKeys.hiveKeyPeer`, distincte de celle des
///   coffres Membre) : vidé à la déconnexion, après 5 codes faux, après
///   `device_revoked` / `device_required`, après un effacement à distance et
///   après 14 jours sans contact. L'effacement détruit aussi la clé (la boîte
///   est supprimée du disque puis rouverte avec une clé neuve). L'espace Admin
///   ne persiste aucun contenu (plan §7.5) : il n'a donc pas de clé.
class LocalDb {
  LocalDb({
    required this.publicCache,
    required this.privateCache,
    required this.session,
    required this.outbox,
    required this.drafts,
    StringBox? peerData,
    Future<void> Function()? resetPeer,
  }) : peerData = peerData ?? MemoryStringBox(),
       _resetPeer = resetPeer; // ignore: prefer_initializing_formals

  LocalDb.memory()
    : publicCache = MemoryStringBox(),
      privateCache = MemoryStringBox(),
      session = MemoryStringBox(),
      outbox = MemoryStringBox(),
      drafts = MemoryStringBox(),
      peerData = MemoryStringBox(),
      _resetPeer = null;

  final StringBox publicCache;
  final StringBox privateCache;
  final StringBox session;
  final StringBox outbox;
  final StringBox drafts;
  final StringBox peerData;
  final Future<void> Function()? _resetPeer;

  static const peerBoxName = 'pair_data';

  static Future<LocalDb> open(SecretStore secrets, {String? path}) async {
    if (path != null) Hive.init(path);
    final cipher = HiveAesCipher(await _key(secrets, SecretKeys.hiveKey));
    Future<StringBox> box(String name) async => HiveStringBox(
      await Hive.openBox<String>(name, encryptionCipher: cipher),
    );
    // Coffre de l'espace Pair : clé propre. Une boîte ouverte avec l'ancienne
    // clé commune (avant P15-12, toujours vide) est recréée.
    Future<StringBox> openPeer() async {
      final key = HiveAesCipher(await _key(secrets, SecretKeys.hiveKeyPeer));
      try {
        return HiveStringBox(await Hive.openBox<String>(peerBoxName, encryptionCipher: key));
      } catch (_) {
        await Hive.deleteBoxFromDisk(peerBoxName);
        return HiveStringBox(await Hive.openBox<String>(peerBoxName, encryptionCipher: key));
      }
    }

    final peer = SwappableStringBox(await openPeer());
    return LocalDb(
      publicCache: await box('cache_public'),
      privateCache: await box('cache_private'),
      session: await box('session'),
      outbox: await box('outbox'),
      drafts: await box('drafts'),
      peerData: peer,
      resetPeer: () async {
        // Destruction de la clé : la boîte part du disque, une clé neuve est créée.
        await Hive.deleteBoxFromDisk(peerBoxName);
        await secrets.delete(SecretKeys.hiveKeyPeer);
        peer.swap(await openPeer());
      },
    );
  }

  static Future<Uint8List> _key(SecretStore secrets, String name) async {
    final stored = await secrets.read(name);
    if (stored != null) return base64Url.decode(stored);
    final rnd = Random.secure();
    final key = Uint8List.fromList(
      List<int>.generate(32, (_) => rnd.nextInt(256)),
    );
    await secrets.write(name, base64Url.encode(key));
    return key;
  }

  /// Données de l'espace Pair seulement (expiration hors contact, espace retiré).
  Future<void> clearPeer() async {
    await peerData.clear();
    await _resetPeer?.call();
  }

  /// Données propres au compte, à effacer à la déconnexion.
  Future<void> clearPrivate() async {
    await privateCache.clear();
    await clearPeer();
    await session.clear();
    await drafts.clear();
    // Boîte d'envoi : seules les entrées liées au compte (`owner`) partent.
    for (final k in outbox.keys.toList()) {
      try {
        final m = jsonDecode(outbox.get(k) ?? '{}') as Map;
        if (m['owner'] != null) await outbox.delete(k);
      } catch (_) {
        await outbox.delete(k);
      }
    }
  }
}
