import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:crypto/crypto.dart';
import 'package:dio/dio.dart';

/// Taille maximale d'une image enregistrée (au-delà : ignorée, comptée en échec).
const int kCccImageMaxFileBytes = 5 * 1024 * 1024;

/// Place maximale occupée par toutes les images CCC hors ligne.
const int kCccImageMaxTotalBytes = 100 * 1024 * 1024;

/// Disque des images : fichiers nommés par [CccImageStore.nameFor]. Abstraction
/// pour tester la logique sans disque réel.
abstract interface class CccImageDisk {
  /// `false` si aucun dossier n'est disponible sur cet appareil.
  Future<bool> prepare();

  /// Nom → taille en octets des fichiers complets.
  Future<Map<String, int>> list();
  Future<void> write(String name, List<int> bytes);
  Future<void> delete(String name);

  /// Chemin d'un fichier (après [prepare]), `null` si non pris en charge.
  String? pathOf(String name);
}

/// Disque réel : un dossier (ex. `…/ccc_images`). Écriture en deux temps
/// (`.part` puis renommage) : jamais de fichier à moitié écrit lu comme complet.
class DirectoryImageDisk implements CccImageDisk {
  DirectoryImageDisk(this._resolve);
  final Future<Directory?> Function() _resolve;
  Directory? _dir;

  @override
  Future<bool> prepare() async {
    if (_dir != null) return true;
    try {
      final d = await _resolve();
      if (d == null) return false;
      await d.create(recursive: true);
      _dir = d;
      return true;
    } catch (_) {
      return false;
    }
  }

  @override
  Future<Map<String, int>> list() async {
    final d = _dir;
    if (d == null) return {};
    final out = <String, int>{};
    await for (final e in d.list(followLinks: false)) {
      if (e is! File) continue;
      final name = e.uri.pathSegments.last;
      if (name.endsWith('.part')) {
        await e.delete(); // reste d'un téléchargement interrompu
      } else {
        out[name] = await e.length();
      }
    }
    return out;
  }

  @override
  Future<void> write(String name, List<int> bytes) async {
    final tmp = File('${_dir!.path}/$name.part');
    await tmp.writeAsBytes(bytes, flush: true);
    await tmp.rename('${_dir!.path}/$name');
  }

  @override
  Future<void> delete(String name) async {
    final f = File('${_dir!.path}/$name');
    if (await f.exists()) await f.delete();
  }

  @override
  String? pathOf(String name) => _dir == null ? null : '${_dir!.path}/$name';
}

/// Bilan d'un téléchargement.
class CccDownloadResult {
  const CccDownloadResult({this.saved = 0, this.failed = 0, this.full = false, this.cancelled = false});
  final int saved;
  final int failed;

  /// Le plafond [kCccImageMaxTotalBytes] a arrêté le téléchargement.
  final bool full;
  final bool cancelled;
}

/// Cache disque des images CCC : téléchargement séquentiel et annulable, avec
/// taille maximale par fichier et au total, purge des images orphelines.
/// Le client [dio] est **sans jeton** : les images sont publiques et le jeton
/// Sanctum ne doit jamais partir vers une autre adresse que l'API.
class CccImageStore {
  CccImageStore({
    required this.disk,
    required this.dio,
    this.maxFileBytes = kCccImageMaxFileBytes,
    this.maxTotalBytes = kCccImageMaxTotalBytes,
  });

  final CccImageDisk disk;
  final Dio dio;
  final int maxFileBytes;
  final int maxTotalBytes;

  /// Nom de fichier d'une adresse : empreinte SHA-256 (jamais l'adresse en clair).
  static String nameFor(String url) => sha256.convert(utf8.encode(url)).toString();

  Future<bool> prepare() => disk.prepare();

  /// Noms des images présentes sur le disque.
  Future<Set<String>> names() async => (await disk.list()).keys.toSet();

  String? pathFor(String url) => disk.pathOf(nameFor(url));

  /// Télécharge [urls] une à une (celles déjà présentes sont comptées sans être relues).
  /// [onProgress] reçoit (traitées, total) après chaque image.
  Future<CccDownloadResult> download(
    Iterable<String> urls, {
    CancelToken? cancel,
    void Function(int done, int total)? onProgress,
  }) async {
    final list = urls.toSet().toList();
    final existing = await disk.list();
    var used = existing.values.fold<int>(0, (a, b) => a + b);
    var done = 0, saved = 0, failed = 0;
    var full = false;
    for (final url in list) {
      if (cancel?.isCancelled ?? false) return CccDownloadResult(saved: saved, failed: failed, full: full, cancelled: true);
      final name = nameFor(url);
      if (existing.containsKey(name)) {
        done++;
        onProgress?.call(done, list.length);
        continue;
      }
      final bytes = await _fetch(url, cancel);
      if (cancel?.isCancelled ?? false) return CccDownloadResult(saved: saved, failed: failed, full: full, cancelled: true);
      if (bytes == null) {
        failed++;
      } else if (used + bytes.length > maxTotalBytes) {
        full = true;
        failed += list.length - done; // le reste n'est pas enregistré
        onProgress?.call(list.length, list.length);
        break;
      } else {
        try {
          await disk.write(name, bytes);
          used += bytes.length;
          saved++;
        } catch (_) {
          failed++;
        }
      }
      done++;
      onProgress?.call(done, list.length);
    }
    return CccDownloadResult(saved: saved, failed: failed, full: full);
  }

  Future<List<int>?> _fetch(String url, CancelToken? cancel) async {
    final file = CancelToken();
    cancel?.whenCancel.then((e) {
      if (!file.isCancelled) file.cancel('cancelled');
    });
    try {
      final res = await dio.get<List<int>>(
        url,
        cancelToken: file,
        options: Options(responseType: ResponseType.bytes, followRedirects: true),
        onReceiveProgress: (received, _) {
          if (received > maxFileBytes && !file.isCancelled) file.cancel('too_big');
        },
      );
      final type = res.headers.value(Headers.contentTypeHeader) ?? '';
      final data = res.data;
      if (res.statusCode == null || res.statusCode! < 200 || res.statusCode! >= 300) return null;
      if (!type.toLowerCase().startsWith('image/') || data == null || data.isEmpty) return null;
      if (data.length > maxFileBytes) return null;
      return data;
    } catch (_) {
      return null;
    }
  }

  /// Supprime les images dont l'adresse n'est pas dans [keepUrls] ; renvoie le nombre supprimé.
  Future<int> prune(Set<String> keepUrls) async {
    final keep = keepUrls.map(nameFor).toSet();
    var n = 0;
    for (final name in (await disk.list()).keys) {
      if (!keep.contains(name)) {
        await disk.delete(name);
        n++;
      }
    }
    return n;
  }

  Future<void> clear() async {
    for (final name in (await disk.list()).keys) {
      await disk.delete(name);
    }
  }
}
