/// Règles de l'écran de publication, **alignées sur le serveur**
/// (`StorePostRequest`, `FeedMediaLimits`, docs/API.md §8.3 et §8.7).
///
/// Aucune API n'expose encore les plafonds au mobile (le web les reçoit en props Inertia :
/// `FeedComposerOptions.limits`) : ce sont donc les valeurs par défaut du serveur. Le serveur
/// reste juge : un 422 est toujours rattaché aux champs (voir [mapComposeErrors]).
class FeedLimits {
  const FeedLimits._();

  static const bodyMax = 3000;
  static const questionMin = 5;
  static const questionMax = 200;
  static const optionsMin = 2;
  static const optionsMax = 6;
  static const optionMax = 80;
  static const durations = [1, 3, 7, 14, 30, 0];
  static const defaultDuration = 7;

  /// `xomiton.feed.max_images` (4 par défaut).
  static const maxImages = 4;
  static const imageMaxBytes = 5 * 1024 * 1024;
  static const imageExtensions = ['jpg', 'jpeg', 'png', 'webp'];
  static const altMax = 255;

  /// `xomiton.feed.video_max_mb` (30 Mo par défaut ; l'hébergement peut imposer moins).
  static const videoMaxBytes = 30 * 1024 * 1024;
  static const videoExtensions = ['mp4', 'webm'];
  static const videoDescriptionMin = 5;
  static const videoDescriptionMax = 500;
}

/// Longueur en points de code, comme le serveur (`mb_strlen`).
int codePoints(String s) => s.runes.length;

String fileExtension(String name) {
  final i = name.lastIndexOf('.');
  return i < 0 ? '' : name.substring(i + 1).toLowerCase();
}

const _accents = {
  'à': 'a', 'â': 'a', 'ä': 'a', 'á': 'a', 'ã': 'a', 'å': 'a',
  'ç': 'c',
  'é': 'e', 'è': 'e', 'ê': 'e', 'ë': 'e',
  'î': 'i', 'ï': 'i', 'í': 'i', 'ì': 'i',
  'ô': 'o', 'ö': 'o', 'ó': 'o', 'ò': 'o', 'õ': 'o',
  'ù': 'u', 'û': 'u', 'ü': 'u', 'ú': 'u',
  'ÿ': 'y', 'ñ': 'n', 'œ': 'oe', 'æ': 'ae',
};

/// Casse et accents ignorés : deux réponses « Santé » et « sante » sont identiques.
String foldForCompare(String s) {
  final b = StringBuffer();
  for (final r in s.trim().toLowerCase().runes) {
    final c = String.fromCharCode(r);
    b.write(_accents[c] ?? c);
  }
  return b.toString();
}

/// Image choisie pour la publication, avec son texte alternatif.
class ComposeImage {
  const ComposeImage({
    required this.path,
    required this.name,
    required this.size,
    this.alt = '',
  });
  final String path;
  final String name;
  final int size;
  final String alt;

  ComposeImage withAlt(String v) =>
      ComposeImage(path: path, name: name, size: size, alt: v);
}

class ComposeVideo {
  const ComposeVideo({
    required this.path,
    required this.name,
    required this.size,
  });
  final String path;
  final String name;
  final int size;
}

/// Ce que l'utilisateur·rice a saisi, prêt à être contrôlé puis envoyé.
class ComposeInput {
  const ComposeInput({
    this.poll = false,
    this.body = '',
    this.question = '',
    this.options = const [],
    this.multiple = false,
    this.resultsBeforeVote = false,
    this.duration = FeedLimits.defaultDuration,
    this.visibility = 'public',
    this.anonymous = false,
    this.images = const [],
    this.video,
    this.videoDescription = '',
  });
  final bool poll;
  final String body;
  final String question;

  /// Une entrée par ligne de réponse (les lignes vides sont retirées à l'envoi).
  final List<String> options;
  final bool multiple;
  final bool resultsBeforeVote;
  final int duration;
  final String visibility;
  final bool anonymous;
  final List<ComposeImage> images;
  final ComposeVideo? video;
  final String videoDescription;

  bool get hasMedia => images.isNotEmpty || video != null;

  /// Index (dans [options]) des réponses envoyées : les lignes vides sont retirées,
  /// et `poll.options.N` du serveur désigne la N-ième réponse envoyée.
  List<int> get sentOptionRows => [
    for (var i = 0; i < options.length; i++)
      if (options[i].trim().isNotEmpty) i,
  ];

  List<String> get sentOptions => [
    for (final i in sentOptionRows) options[i].trim(),
  ];
}

typedef Translate = String Function(String key, [Map<String, Object?> args]);

/// Contrôle local : champ du serveur (`poll.question`, `images.1`, `video_description`…) →
/// message. Vide = rien à corriger.
Map<String, String> validateCompose(ComposeInput c, Translate t) {
  final e = <String, String>{};
  final body = c.body.trim();
  if (codePoints(body) > FeedLimits.bodyMax) {
    e['body'] = t('mobile_compose_body_too_long', {'max': FeedLimits.bodyMax});
  }
  if (c.anonymous && c.visibility == 'followers') {
    e['visibility'] = t('feed_create_anonymous_followers');
  }
  if (c.video != null && (c.poll || c.images.isNotEmpty)) {
    e['video'] = t(c.poll ? 'feed_video_not_in_poll' : 'feed_video_not_with_images');
  }
  if (c.poll) {
    final q = c.question.trim();
    final n = codePoints(q);
    if (n < FeedLimits.questionMin || n > FeedLimits.questionMax) {
      e['poll.question'] = t('feed_poll_question_error', {
        'min': FeedLimits.questionMin,
        'max': FeedLimits.questionMax,
      });
    }
    final rows = c.sentOptionRows;
    if (rows.length < FeedLimits.optionsMin) {
      e['poll.options'] = t('feed_poll_options_error', {
        'min': FeedLimits.optionsMin,
      });
    } else if (rows.length > FeedLimits.optionsMax) {
      e['poll.options'] = t('feed_poll_options_count_error', {
        'min': FeedLimits.optionsMin,
        'max': FeedLimits.optionsMax,
      });
    } else {
      final seen = <String>{};
      var duplicate = false;
      for (final i in rows) {
        final text = c.options[i].trim();
        if (codePoints(text) > FeedLimits.optionMax) {
          e['poll.options.row$i'] = t('mobile_compose_option_too_long', {
            'max': FeedLimits.optionMax,
          });
        }
        if (!seen.add(foldForCompare(text))) duplicate = true;
      }
      if (duplicate) e['poll.options'] = t('feed_poll_options_duplicate');
    }
  } else if (body.isEmpty && !c.hasMedia) {
    e['body'] = t('feed_errors_text_required');
  }
  if (c.images.length > FeedLimits.maxImages) {
    e['images'] = t('feed_api_images_max', {'max': FeedLimits.maxImages});
  }
  for (var i = 0; i < c.images.length; i++) {
    final img = c.images[i];
    if (!FeedLimits.imageExtensions.contains(fileExtension(img.name))) {
      e['images.$i'] = t('feed_create_photo_type');
    } else if (img.size > FeedLimits.imageMaxBytes) {
      e['images.$i'] = t('feed_toasts_image_too_large', {
        'max': FeedLimits.imageMaxBytes ~/ (1024 * 1024),
      });
    }
    if (codePoints(img.alt.trim()) > FeedLimits.altMax) {
      e['images_alt.$i'] = t('mobile_compose_alt_too_long', {
        'max': FeedLimits.altMax,
      });
    }
  }
  final v = c.video;
  if (v != null && !e.containsKey('video')) {
    if (!FeedLimits.videoExtensions.contains(fileExtension(v.name))) {
      e['video'] = t('mobile_compose_video_type');
    } else if (v.size > FeedLimits.videoMaxBytes) {
      e['video'] = t('mobile_compose_video_too_big', {
        'max': FeedLimits.videoMaxBytes ~/ (1024 * 1024),
      });
    }
  }
  if (v != null) {
    final n = codePoints(c.videoDescription.trim());
    if (n == 0) {
      e['video_description'] = t('feed_video_description_required');
    } else if (n < FeedLimits.videoDescriptionMin ||
        n > FeedLimits.videoDescriptionMax) {
      e['video_description'] = t('feed_video_description_length', {
        'min': FeedLimits.videoDescriptionMin,
        'max': FeedLimits.videoDescriptionMax,
      });
    }
  }
  return e;
}

/// Rattache les erreurs 422 du serveur aux champs de l'écran. `poll.options.N` (N = rang parmi les
/// réponses envoyées) devient `poll.options.rowI` (I = ligne affichée). Les clés inconnues sont
/// rendues dans `rest` (message général).
({Map<String, String> fields, List<String> rest}) mapComposeErrors(
  Map<String, List<String>> server,
  ComposeInput sent,
) {
  final fields = <String, String>{};
  final rest = <String>[];
  final rows = sent.sentOptionRows;
  server.forEach((key, list) {
    if (list.isEmpty) return;
    var k = key;
    final m = RegExp(r'^poll\.options\.(\d+)$').firstMatch(key);
    if (m != null) {
      final n = int.parse(m.group(1)!);
      k = n < rows.length ? 'poll.options.row${rows[n]}' : 'poll.options';
    }
    const known = {
      'body',
      'visibility',
      'poll.question',
      'poll.options',
      'poll.duration_days',
      'images',
      'video',
      'video_description',
    };
    final known2 =
        known.contains(k) ||
        k.startsWith('poll.options.row') ||
        RegExp(r'^images(_alt)?\.\d+$').hasMatch(k);
    if (known2) {
      fields[k] = list.first;
    } else {
      rest.add(list.first);
    }
  });
  return (fields: fields, rest: rest);
}

/// Texte lisible d'une taille en octets (« 4,2 Mo »).
String formatBytes(int bytes) {
  if (bytes < 1024) return '$bytes o';
  if (bytes < 1024 * 1024) {
    return '${(bytes / 1024).toStringAsFixed(0)} Ko';
  }
  final mo = (bytes / (1024 * 1024)).toStringAsFixed(1).replaceAll('.', ',');
  return '$mo Mo';
}
