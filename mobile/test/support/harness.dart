import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:xomiton/app/app.dart';
import 'package:xomiton/core/l10n/app_strings.dart';
import 'package:xomiton/core/providers/core_providers.dart';
import 'package:xomiton/core/storage/local_db.dart';
import 'package:xomiton/core/storage/secret_store.dart';
import 'package:xomiton/core/storage/token_store.dart';
import 'package:xomiton/features/home/application/home_providers.dart';

typedef FakeHandler = FutureOr<ResponseBody> Function(RequestOptions o);

ResponseBody jsonBody(
  int status,
  Object? body, {
  Map<String, String> headers = const {},
}) {
  return ResponseBody.fromString(
    jsonEncode(body),
    status,
    headers: {
      Headers.contentTypeHeader: [Headers.jsonContentType],
      for (final e in headers.entries) e.key: [e.value],
    },
  );
}

ResponseBody notModified() => ResponseBody.fromString('', 304, headers: {});

/// Faux serveur : routes `"METHOD chemin"` → réponse. Garde toutes les requêtes.
class FakeApi implements HttpClientAdapter {
  FakeApi([Map<String, FakeHandler>? routes]) : routes = routes ?? {};

  final Map<String, FakeHandler> routes;
  final List<RequestOptions> requests = [];

  String _key(RequestOptions o) =>
      '${o.method} ${o.path.replaceFirst(RegExp(r'^/+'), '')}';

  List<RequestOptions> calls(String route) =>
      requests.where((r) => _key(r) == route).toList();

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    requests.add(options);
    final h = routes[_key(options)];
    if (h == null) {
      return jsonBody(404, {'message': 'Introuvable', 'code': 'not_found'});
    }
    return h(options);
  }

  @override
  void close({bool force = false}) {}
}

Map<String, dynamic> userJson({
  String role = 'membre',
  bool emailVerified = true,
  int completion = 43,
  bool pending = false,
}) => {
  'id': 12,
  'pseudo': 'Awa_S',
  'role': role,
  'status': 'actif',
  'email': 'awa@exemple.bj',
  'email_verified': emailVerified,
  'pending_validation': pending,
  'profile': {'completion': completion},
};

AppStrings loadStrings() =>
    AppStrings.fromArb(File('lib/l10n/app_fr.arb').readAsStringSync());

class TestEnv {
  TestEnv({
    required this.api,
    this.seen = true,
    this.token,
    this.cachedUser,
    this.online = true,
    this.unread = 0,
    this.extra = const [],
  });

  final FakeApi api;
  final bool seen;
  final String? token;
  final Map<String, dynamic>? cachedUser;
  final bool online;
  final int unread;
  final List<Override> extra;

  final secrets = MemorySecretStore();
  final db = LocalDb.memory();
  late final ToastLog toasts;

  List<Override> overrides(SharedPreferences prefs) => [
    sharedPreferencesProvider.overrideWithValue(prefs),
    secretStoreProvider.overrideWithValue(secrets),
    localDbProvider.overrideWithValue(db),
    tokenStoreProvider.overrideWithValue(TokenStore(secrets, initial: token)),
    httpAdapterProvider.overrideWithValue(api),
    connectivityStreamProvider.overrideWithValue(Stream<bool>.value(online)),
    unreadCountProvider.overrideWith((ref) => Stream.value(unread)),
    outboxRetryIntervalProvider.overrideWithValue(null),
    ...extra,
  ];

  Future<Widget> app() async {
    SharedPreferences.setMockInitialValues({'onboarding_seen': seen});
    final prefs = await SharedPreferences.getInstance();
    if (cachedUser != null) {
      await db.session.put('user', jsonEncode(cachedUser));
    }
    return ProviderScope(
      overrides: overrides(prefs),
      child: XomitonApp(strings: loadStrings()),
    );
  }
}

class ToastLog {
  final List<Object> items = [];
}

/// Écran de téléphone (412 × 880 dp).
void usePhone(dynamic tester) {
  tester.view.physicalSize = const Size(412 * 2.625, 880 * 2.625);
  tester.view.devicePixelRatio = 2.625;
}
