import 'package:flutter_test/flutter_test.dart';
import 'package:xomiton/features/feed/data/compose_logic.dart';

import '../../support/feed_fakes.dart';
import '../../support/harness.dart';

final _s = loadStrings();
String _t(String k, [Map<String, Object?> a = const {}]) => _s.t(k, a);

Map<String, String> _v(ComposeInput c) => validateCompose(c, _t);

void main() {
  group('sondage : mêmes bornes que le serveur', () {
    test('question de 5 à 200 caractères (points de code)', () {
      expect(_v(const ComposeInput(poll: true, question: 'Court', options: ['a', 'b'])), isNot(contains('poll.question')));
      expect(_v(const ComposeInput(poll: true, question: 'Non', options: ['a', 'b'])), contains('poll.question'));
      expect(_v(ComposeInput(poll: true, question: 'x' * 201, options: const ['a', 'b'])), contains('poll.question'));
      expect(_v(ComposeInput(poll: true, question: 'x' * 200, options: const ['a', 'b'])), isNot(contains('poll.question')));
      // 5 émojis = 5 points de code (et 10 unités UTF-16).
      expect(_v(const ComposeInput(poll: true, question: '😀😀😀😀😀', options: ['a', 'b'])), isNot(contains('poll.question')));
    });

    test('2 à 6 réponses, lignes vides retirées', () {
      const q = 'Quel thème pour la causerie ?';
      expect(_v(const ComposeInput(poll: true, question: q, options: ['Santé', '', '  '])), contains('poll.options'));
      expect(_v(const ComposeInput(poll: true, question: q, options: ['Santé', '', 'Droits'])), isEmpty);
      expect(_v(ComposeInput(poll: true, question: q, options: List.generate(6, (i) => 'R$i'))), isEmpty);
      expect(_v(ComposeInput(poll: true, question: q, options: List.generate(7, (i) => 'R$i'))), contains('poll.options'));
    });

    test('réponses distinctes, casse et accents ignorés', () {
      const q = 'Quel thème pour la causerie ?';
      final e = _v(const ComposeInput(poll: true, question: q, options: ['Santé', 'sante']));
      expect(e['poll.options'], _t('feed_poll_options_duplicate'));
      expect(foldForCompare('  ÉCOLE '), 'ecole');
    });

    test('réponse de plus de 80 caractères signalée sur sa ligne', () {
      final e = _v(ComposeInput(poll: true, question: 'Quel thème pour la causerie ?', options: ['a', 'x' * 81]));
      expect(e.keys, contains('poll.options.row1'));
    });

    test('texte d’accompagnement facultatif pour un sondage, obligatoire sans média pour une publication', () {
      expect(_v(const ComposeInput(poll: true, question: 'Quel thème pour la causerie ?', options: ['a', 'b'])), isEmpty);
      expect(_v(const ComposeInput()), contains('body'));
      expect(_v(ComposeInput(images: [img(1)])), isEmpty, reason: 'média seul accepté');
      expect(_v(ComposeInput(body: 'x' * 3001)), contains('body'));
    });

    test('anonyme + abonné·es refusé', () {
      expect(_v(const ComposeInput(body: 'Salut', anonymous: true, visibility: 'followers')), contains('visibility'));
    });
  });

  group('erreurs 422 rattachées aux champs', () {
    test('poll.options.N désigne la N-ième réponse ENVOYÉE (lignes vides ignorées)', () {
      const sent = ComposeInput(poll: true, question: 'Quel thème ?', options: ['A', '', 'B', 'C']);
      final r = mapComposeErrors({
        'poll.question': ['Entre 5 et 200 caractères.'],
        'poll.options.1': ['Trop long.'],
        'poll.options.9': ['Inconnue.'],
        'images.2': ['Illisible.'],
        'images_alt.0': ['Trop long.'],
        'group': ['Tu dois être membre du groupe.'],
      }, sent);
      expect(r.fields['poll.question'], 'Entre 5 et 200 caractères.');
      expect(r.fields['poll.options.row2'], 'Trop long.', reason: 'N=1 → deuxième réponse envoyée = ligne 2');
      expect(r.fields['poll.options'], 'Inconnue.');
      expect(r.fields['images.2'], 'Illisible.');
      expect(r.fields['images_alt.0'], 'Trop long.');
      expect(r.rest, ['Tu dois être membre du groupe.']);
    });
  });

  group('images', () {
    test('plafond de 4, formats JPG / PNG / WebP, 5 Mo, description ≤ 255', () {
      expect(_v(ComposeInput(body: 'a', images: [for (var i = 0; i < 4; i++) img(i)])), isEmpty);
      expect(_v(ComposeInput(body: 'a', images: [for (var i = 0; i < 5; i++) img(i)])), contains('images'));
      expect(_v(ComposeInput(body: 'a', images: [img(1, name: 'doc.pdf')])), contains('images.0'));
      expect(_v(ComposeInput(body: 'a', images: [img(1), img(2, size: FeedLimits.imageMaxBytes + 1)])).keys, ['images.1']);
      expect(_v(ComposeInput(body: 'a', images: [img(1).withAlt('x' * 256)])), contains('images_alt.0'));
    });
  });

  group('vidéo', () {
    ComposeVideo video({String name = 'a.mp4', int size = 1000}) => ComposeVideo(path: '/tmp/a', name: name, size: size);

    test('mp4 ou webm, 30 Mo au plus', () {
      expect(_v(ComposeInput(video: video(), videoDescription: 'Une description claire')), isEmpty);
      expect(_v(ComposeInput(video: video(name: 'a.webm'), videoDescription: 'Une description claire')), isEmpty);
      expect(_v(ComposeInput(video: video(name: 'a.avi'), videoDescription: 'Une description claire')), contains('video'));
      expect(_v(ComposeInput(video: video(size: FeedLimits.videoMaxBytes + 1), videoDescription: 'Une description claire')), contains('video'));
      expect(_v(ComposeInput(video: video(size: FeedLimits.videoMaxBytes), videoDescription: 'Une description claire')), isEmpty);
    });

    test('description obligatoire, 5 à 500 caractères', () {
      expect(_v(ComposeInput(video: video())), contains('video_description'));
      expect(_v(ComposeInput(video: video(), videoDescription: '   ')), contains('video_description'));
      expect(_v(ComposeInput(video: video(), videoDescription: 'Court')), isEmpty);
      expect(_v(ComposeInput(video: video(), videoDescription: 'Cour')), contains('video_description'));
      expect(_v(ComposeInput(video: video(), videoDescription: 'x' * 501)), contains('video_description'));
    });

    test('exclusive : ni avec des images ni dans un sondage', () {
      final withImages = _v(ComposeInput(video: video(), videoDescription: 'Une description', images: [img(1)]));
      expect(withImages['video'], _t('feed_video_not_with_images'));
      final inPoll = _v(ComposeInput(poll: true, question: 'Quel thème ?', options: const ['a', 'b'], video: video(), videoDescription: 'Une description'));
      expect(inPoll['video'], _t('feed_video_not_in_poll'));
    });
  });

  test('formatBytes', () {
    expect(formatBytes(512), '512 o');
    expect(formatBytes(2048), '2 Ko');
    expect(formatBytes(3 * 1024 * 1024 + 512 * 1024), '3,5 Mo');
  });
}
