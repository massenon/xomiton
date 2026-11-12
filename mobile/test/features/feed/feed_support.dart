import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:xomiton/core/l10n/app_strings.dart';
import 'package:xomiton/core/storage/api_cache.dart';
import 'package:xomiton/features/feed/data/media_picker.dart';
import 'package:xomiton/features/feed/presentation/media_widgets.dart';

import '../../support/feed_fakes.dart';
import '../../support/fakes.dart';
import '../../support/harness.dart';

final S = loadStrings();

Map<String, dynamic> postJson(
  String id, {
  String body = 'Un récit',
  List<Map<String, dynamic>> media = const [],
  String? reviewState,
  String? reviewLabel,
  String? reviewReason,
  bool mine = false,
  bool canComment = true,
}) => {
  'id': id,
  'author': {'id': mine ? 12 : 3, 'pseudo': mine ? 'Awa_S' : 'Kafui', 'avatar_url': null, 'role': 'membre', 'anonymous': false},
  'is_mine': mine,
  'body': body,
  'tag': 'temoignage',
  'tag_label': 'Témoignage',
  'likes_count': 2,
  'comments_count': 1,
  'liked': false,
  'poll': null,
  'media': media,
  'review_state': reviewState,
  'review_label': reviewLabel,
  'review_reason': reviewReason,
  'published_at': reviewState == null || reviewState == 'approved' ? DateTime.now().subtract(const Duration(hours: 2)).toIso8601String() : null,
  'can': {'update': false, 'delete': mine, 'report': !mine, 'comment': canComment},
};

Map<String, dynamic> imageJson(int pos, {String? alt, int? w, int? h}) => {
  'kind': 'image',
  'url': 'https://x.test/media/img$pos.webp?signature=s',
  'alt': alt,
  'width': w ?? 1600,
  'height': h ?? 1200,
  'position': pos,
  'mime': 'image/webp',
  'size': 1000,
};

Map<String, dynamic> videoJson({String alt = 'Des femmes assises en cercle.', String url = 'https://x.test/media/fil/V1/video?viewer=12&expires=1&signature=s'}) => {
  'kind': 'video',
  'url': url,
  'alt': alt,
  'width': null,
  'height': null,
  'position': 0,
  'mime': 'video/mp4',
  'size': 8192,
};

FakeApi apiWith(Map<String, FakeHandler> extra) => FakeApi({
  'GET me': (_) => jsonBody(200, {'data': userJson()}),
  'GET feed': (_) => jsonBody(200, {'data': <Object>[], 'meta': {}}),
  ...extra,
});

TestEnv memberEnv(
  FakeApi api, {
  bool online = true,
  FakeMediaPicker? picker,
  SpyFeedApi Function(Dio dio, ApiCache cache)? spy,
  EngineLog? engines,
}) => TestEnv(
  api: api,
  token: 'tok',
  cachedUser: userJson(),
  online: online,
  extra: [
    if (picker != null) mediaPickerProvider.overrideWithValue(picker),
    if (spy != null) spyFeedApi(spy),
    if (engines != null) videoEngineFactoryProvider.overrideWithValue(engines.make),
  ],
);

/// [tall] : écran très haut (le formulaire est une liste qui ne construit que le visible).
Future<void> boot(WidgetTester tester, TestEnv env, String route, {bool tall = true}) async {
  usePhone(tester);
  if (tall) tester.view.physicalSize = const Size(412 * 2.625, 3200 * 2.625);
  addTearDown(tester.view.reset);
  await tester.pumpWidget(await env.app());
  await settle(tester);
  goTo(tester, route);
  await settle(tester, 10);
}

Finder field(String key) => find.byKey(Key(key));

final composeScrollable = find.descendant(of: find.byKey(const Key('compose-list')), matching: find.byType(Scrollable)).first;

/// La liste ne construit que ce qui est visible : on fait défiler jusqu'au champ.
Future<void> reveal(WidgetTester tester, Finder f) async {
  if (f.evaluate().isEmpty) {
    await tester.scrollUntilVisible(f, 300, scrollable: composeScrollable);
  }
  await tester.ensureVisible(f);
  await tester.pump();
}

Future<void> toTop(WidgetTester tester) async {
  await tester.drag(find.byType(ListView).first, const Offset(0, 3000));
  await settle(tester, 3);
}

Future<void> type(WidgetTester tester, String key, String text) async {
  await reveal(tester, field(key));
  await tester.enterText(field(key), text);
  await tester.pump();
}

Future<void> tapKey(WidgetTester tester, String key) async {
  await reveal(tester, field(key));
  await tester.tap(field(key));
  await settle(tester, 3);
}

bool hasText(String t) => find.text(t).evaluate().isNotEmpty;

LocalizationsDelegate<AppStrings> loadedDelegate() => AppStringsDelegate(S);
