import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:xomiton/core/network/api_exception.dart';
import 'package:xomiton/core/storage/api_cache.dart';
import 'package:xomiton/features/feed/data/compose_logic.dart';
import 'package:xomiton/features/feed/data/feed_api.dart';

import '../../support/harness.dart';

late Directory tmp;

Future<String> _file(String name, [int size = 16]) async {
  final f = File('${tmp.path}/$name');
  await f.writeAsBytes(List.filled(size, 7));
  return f.path;
}

({FeedApi api, FakeApi fake}) _api(Map<String, FakeHandler> routes) {
  final fake = FakeApi(routes);
  final dio = Dio(BaseOptions(baseUrl: 'https://x.test/api/v1/'))..httpClientAdapter = fake;
  return (api: FeedApi(dio, _NoCache()), fake: fake);
}

class _NoCache implements ApiCache {
  @override
  dynamic noSuchMethod(Invocation i) => throw UnimplementedError();
}

Map<String, List<String>> _fields(FormData f) {
  final m = <String, List<String>>{};
  for (final e in f.fields) {
    m.putIfAbsent(e.key, () => []).add(e.value);
  }
  return m;
}

void main() {
  setUp(() => tmp = Directory.systemTemp.createTempSync('xm-feed'));
  tearDown(() => tmp.deleteSync(recursive: true));

  test('images[] dans l’ordre choisi, images_alt[] alignés sur images[]', () async {
    final p1 = await _file('a.jpg');
    final p2 = await _file('b.png');
    final form = await buildPostForm(ComposeInput(
      body: ' Nos photos ',
      anonymous: true,
      images: [
        ComposeImage(path: p2, name: 'b.png', size: 16, alt: 'Le groupe'),
        ComposeImage(path: p1, name: 'a.jpg', size: 16),
      ],
    ));
    final f = _fields(form);
    expect(f['kind'], ['post']);
    expect(f['body'], ['Nos photos']);
    expect(f['is_anonymous'], ['1']);
    expect(f['visibility'], ['public']);
    expect(form.files.map((e) => e.key), ['images[]', 'images[]']);
    expect(form.files.map((e) => e.value.filename), ['b.png', 'a.jpg']);
    expect(form.files.first.value.contentType.toString(), 'image/png');
    expect(f['images_alt[]'], ['Le groupe', ''], reason: 'images_alt[1] décrit images[1] ; vide = sans alt');
  });

  test('sans aucune description, aucun images_alt[] n’est envoyé', () async {
    final p = await _file('a.jpg');
    final form = await buildPostForm(ComposeInput(body: 'x', images: [ComposeImage(path: p, name: 'a.jpg', size: 16)]));
    expect(_fields(form).containsKey('images_alt[]'), isFalse);
  });

  test('sondage avec images : champs poll[...], lignes vides retirées', () async {
    final p = await _file('a.webp');
    final form = await buildPostForm(ComposeInput(
      poll: true,
      question: ' Quel thème ? ',
      options: const ['Santé', '', 'Droits'],
      multiple: true,
      resultsBeforeVote: true,
      duration: 0,
      visibility: 'followers',
      images: [ComposeImage(path: p, name: 'a.webp', size: 16)],
    ));
    final f = _fields(form);
    expect(f['kind'], ['poll']);
    expect(f['poll[question]'], ['Quel thème ?']);
    expect(f['poll[options][]'], ['Santé', 'Droits']);
    expect(f['poll[multiple]'], ['1']);
    expect(f['poll[results_before_vote]'], ['1']);
    expect(f['poll[duration_days]'], ['0']);
    expect(f.containsKey('body'), isFalse, reason: 'texte facultatif');
    expect(form.files.single.key, 'images[]');
  });

  test('vidéo : champ video + video_description, jamais d’images', () async {
    final p = await _file('v.mp4', 64);
    final form = await buildPostForm(ComposeInput(
      body: 'Notre atelier',
      video: ComposeVideo(path: p, name: 'v.mp4', size: 64),
      videoDescription: ' Des femmes en cercle ',
    ));
    expect(form.files.single.key, 'video');
    expect(form.files.single.value.contentType.toString(), 'video/mp4');
    expect(_fields(form)['video_description'], ['Des femmes en cercle']);
  });

  test('201 : publication en ligne ; Idempotency-Key et multipart, pas de rejeu automatique', () async {
    final p = await _file('a.jpg');
    final r = _api({
      'POST posts': (_) => jsonBody(201, {'data': {'id': 'P1', 'body': 'x', 'media': <Object>[]}}),
    });
    final out = await r.api.createWithMedia(
      ComposeInput(body: 'x', images: [ComposeImage(path: p, name: 'a.jpg', size: 16)]),
      idempotencyKey: 'abc',
    );
    expect(out.inReview, isFalse);
    expect(out.post!.id, 'P1');
    final call = r.fake.calls('POST posts').single;
    expect(call.headers['Idempotency-Key'], 'abc');
    expect(call.contentType, contains('multipart/form-data'));
    expect(call.extra['no_auto_replay'], true);
  });

  test('202 avec data.id : vidéo en relecture, pas une publication en ligne', () async {
    final p = await _file('v.mp4');
    final r = _api({
      'POST posts': (_) => jsonBody(202, {
        'data': {'id': 'V1', 'review_state': 'pending', 'review_label': 'En attente de relecture', 'media': <Object>[], 'published_at': null},
      }),
    });
    final out = await r.api.createWithMedia(ComposeInput(
      video: ComposeVideo(path: p, name: 'v.mp4', size: 16),
      videoDescription: 'Une description',
    ));
    expect(out.inReview, isTrue);
    expect(out.processing, isFalse);
    expect(out.post!.reviewState, 'pending');
  });

  test('202 d’idempotence (status processing, sans id) : ni en ligne ni en relecture', () async {
    final p = await _file('v.mp4');
    final r = _api({
      'POST posts': (_) => jsonBody(202, {'data': {'status': 'processing'}}),
    });
    final out = await r.api.createWithMedia(ComposeInput(
      video: ComposeVideo(path: p, name: 'v.mp4', size: 16),
      videoDescription: 'Une description',
    ));
    expect(out.processing, isTrue);
    expect(out.post, isNull);
  });

  test('422 : erreurs par champ lisibles par ApiException', () async {
    final p = await _file('a.jpg');
    final r = _api({
      'POST posts': (_) => jsonBody(422, {
        'message': 'Image illisible.',
        'code': 'validation_failed',
        'errors': {'images.1': ['Image illisible.']},
      }),
    });
    try {
      await r.api.createWithMedia(ComposeInput(body: 'x', images: [ComposeImage(path: p, name: 'a.jpg', size: 16)]));
      fail('422 attendu');
    } on DioException catch (e) {
      final api = ApiException.fromResponse(e);
      expect(api.status, 422);
      expect(api.errors['images.1'], ['Image illisible.']);
    }
  });

  test('GET posts/review : mes vidéos', () async {
    final r = _api({
      'GET posts/review': (_) => jsonBody(200, {
        'data': [
          {'id': 'V1', 'review_state': 'pending', 'media': <Object>[]},
          {'id': 'V2', 'review_state': 'rejected', 'review_reason': 'Hors sujet', 'media': <Object>[]},
        ],
      }),
    });
    final list = await r.api.myReview();
    expect(list.map((p) => p.id), ['V1', 'V2']);
    expect(list.last.reviewReason, 'Hors sujet');
  });
}
