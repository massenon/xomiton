import 'package:flutter/foundation.dart';

/// Outils CCC en lecture (docs/API.md §19 bis). Modèles immuables, lecture
/// défensive : un élément sans clé ou sans titre est ignoré, un champ de mauvais
/// type devient « absent », jamais une exception. Aucun champ n'est inventé :
/// ce sont ceux de `CccToolResource` (`key, family, title, description, cover,
/// duration_minutes, chapters, pages{position, image, caption, alt}`, et pour une
/// BD `public_url` : page publique `/bd/{key}` si elle est publique, P19-04).

String? _text(Object? v) {
  if (v is! String) return null;
  final t = v.trim();
  return t.isEmpty ? null : t;
}

int? _int(Object? v) => v is num ? v.toInt() : null;

/// Adresse web d'une image : http(s) seulement (jamais `file:` ni `javascript:`).
String? _webUrl(Object? v) {
  final t = _text(v);
  if (t == null) return null;
  final uri = Uri.tryParse(t);
  if (uri == null || !(uri.scheme == 'http' || uri.scheme == 'https') || uri.host.isEmpty) return null;
  return t;
}

List<Map<String, dynamic>> _maps(Object? v) => v is List
    ? [for (final e in v) if (e is Map) Map<String, dynamic>.from(e)]
    : const [];

/// Module du manuel du pair animateur·rice (famille `manuel`).
@immutable
class ManualModule {
  const ManualModule({
    required this.key,
    required this.title,
    required this.chapters,
    this.description,
    this.durationMinutes,
  });

  final String key;
  final String title;
  final String? description;
  final int? durationMinutes;

  /// Points du module, dans l'ordre du serveur (jamais triés) ; le rang (à partir de 0) identifie le point vu.
  final List<String> chapters;

  static ManualModule? maybe(Map<String, dynamic> j) {
    final key = _text(j['key']);
    final title = _text(j['title']);
    if (key == null || title == null) return null;
    final minutes = _int(j['duration_minutes']);
    return ManualModule(
      key: key,
      title: title,
      description: _text(j['description']),
      durationMinutes: minutes != null && minutes > 0 ? minutes : null,
      chapters: [
        if (j['chapters'] is List)
          for (final c in j['chapters'] as List) ?_text(c),
      ],
    );
  }
}

/// Planche d'une boîte à images ou d'une BD. `alt` n'est jamais vide côté serveur ; à défaut, le titre.
@immutable
class CccPage {
  const CccPage({required this.position, required this.image, required this.alt, this.caption});

  final int position;
  final String image;
  final String? caption;
  final String alt;

  static CccPage? maybe(Map<String, dynamic> j, {required int rank, required String fallbackAlt}) {
    final image = _webUrl(j['image']);
    if (image == null) return null;
    return CccPage(
      position: _int(j['position']) ?? rank,
      image: image,
      caption: _text(j['caption']),
      alt: _text(j['alt']) ?? fallbackAlt,
    );
  }
}

/// Boîte à images (famille `boites_images`) ou bande dessinée (famille `bd`) : un titre et des planches.
@immutable
class PagedTool {
  const PagedTool({
    required this.key,
    required this.title,
    required this.pages,
    this.description,
    this.cover,
    this.publicUrl,
  });

  final String key;
  final String title;

  /// BD publique (P19-04) : adresse de sa page publique `/bd/{key}` (http(s) seulement), sinon `null`.
  final Uri? publicUrl;

  /// Boîte à images : consigne de l'animateur·rice (affichée en mode présentation seulement) ;
  /// BD : présentation de l'histoire.
  final String? description;
  final String? cover;
  final List<CccPage> pages;

  static PagedTool? maybe(Map<String, dynamic> j) {
    final key = _text(j['key']);
    final title = _text(j['title']);
    if (key == null || title == null) return null;
    final pages = <CccPage>[];
    for (final p in _maps(j['pages'])) {
      final page = CccPage.maybe(p, rank: pages.length + 1, fallbackAlt: '$title – ${pages.length + 1}');
      if (page != null) pages.add(page);
    }
    return PagedTool(
      key: key,
      title: title,
      description: _text(j['description']),
      cover: _webUrl(j['cover']),
      publicUrl: switch (_webUrl(j['public_url'])) { final String url => Uri.parse(url), null => null },
      pages: pages,
    );
  }
}

/// Réponse de `GET ccc` : outils publiés par famille et nombre de textes de loi publiés.
@immutable
class CccCatalog {
  const CccCatalog({
    this.manual = const [],
    this.imageBoxes = const [],
    this.comics = const [],
    this.lawsCount = 0,
    this.lawsUrl,
  });

  final List<ManualModule> manual;
  final List<PagedTool> imageBoxes;
  final List<PagedTool> comics;
  final int lawsCount;

  /// Adresse de `/lois` donnée par le serveur (http(s) seulement), ou `null`.
  final Uri? lawsUrl;

  ManualModule? module(String key) => manual.where((m) => m.key == key).firstOrNull;
  PagedTool? imageBox(String key) => imageBoxes.where((m) => m.key == key).firstOrNull;
  PagedTool? comic(String key) => comics.where((m) => m.key == key).firstOrNull;

  /// `body` = enveloppe complète `{data: {manuel, boites_images, bd}, meta: {laws}}`.
  factory CccCatalog.fromJson(Object? body) {
    final root = body is Map ? body : const {};
    final data = root['data'] is Map ? Map<String, dynamic>.from(root['data'] as Map) : const <String, dynamic>{};
    final meta = root['meta'] is Map ? Map<String, dynamic>.from(root['meta'] as Map) : const <String, dynamic>{};
    final laws = meta['laws'] is Map ? Map<String, dynamic>.from(meta['laws'] as Map) : const <String, dynamic>{};
    final url = _webUrl(laws['url']);
    final count = _int(laws['count']) ?? 0;
    return CccCatalog(
      manual: [for (final j in _maps(data['manuel'])) ?ManualModule.maybe(j)],
      imageBoxes: [for (final j in _maps(data['boites_images'])) ?PagedTool.maybe(j)],
      comics: [for (final j in _maps(data['bd'])) ?PagedTool.maybe(j)],
      lawsCount: count < 0 ? 0 : count,
      lawsUrl: url == null ? null : Uri.parse(url),
    );
  }
}
