import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:xomiton/app/router.dart';
import 'package:xomiton/core/l10n/app_strings.dart';
import 'package:xomiton/core/maintenance/maintenance.dart';
import 'package:xomiton/core/network/api_exception.dart';
import 'package:xomiton/core/sync/sync_providers.dart';
import 'package:xomiton/core/ui/xm_widgets.dart';
import 'package:xomiton/features/alerts/data/alerts_api.dart';
import 'package:xomiton/features/home/data/home_api.dart';
import 'package:xomiton/features/shell/presentation/maintenance_page.dart';

import '../support/fakes.dart';
import '../support/harness.dart';

final S = loadStrings();

Map<String, dynamic> maintenance503({bool overdue = false, String? endsAt = '2026-10-29T13:30:00+00:00'}) => {
  'message': 'Plateforme en maintenance. Réessayer dans quelques minutes.',
  'code': 'maintenance',
  'retry_after': 600,
  'ends_at': endsAt,
  'overdue': overdue,
  'timezone': 'Africa/Porto-Novo',
  'maintenance_message': 'Mise à jour de la base : on revient vite.',
};

Map<String, dynamic> statusBody({required bool maintenance}) => {
  'data': {
    'status': maintenance ? 'maintenance' : 'ok',
    'maintenance': {'active': maintenance, 'message': null, 'ends_at': null, 'overdue': false},
    'emergency_numbers': [
      {'label': 'Police secours', 'number': '117'},
      {'label': 'Pompiers', 'number': '118'},
    ],
    'timezone': 'Africa/Porto-Novo',
  },
};

String? redirect(
  String path, {
  bool auth = true,
  bool maintenance = true,
  bool team = false,
}) => appRedirect(
  authenticated: auth,
  onboardingSeen: true,
  uri: Uri.parse(path),
  maintenance: maintenance,
  teamAccount: team,
);

void main() {
  setUpAll(() => initializeDateFormatting('fr'));

  group('mode maintenance : garde du routeur (RG-14, RG-01)', () {
    test('un membre est ramené à l’écran de maintenance', () {
      for (final p in ['/home', '/feed', '/messages', '/profile', '/notifications', '/pair/accueil']) {
        expect(redirect(p), '/maintenance', reason: p);
      }
      expect(redirect('/home', auth: false), '/maintenance');
    });

    test('SOS, signalement, suivi, urgences et connexion restent ouverts', () {
      for (final p in ['/sos', '/alerte-sos', '/signaler', '/suivi', '/urgences', '/login', '/forgot', '/otp']) {
        expect(redirect(p, auth: p == '/sos'), anyOf(isNull, isNot('/maintenance')), reason: p);
      }
    });

    test('l’équipe (admin, superadmin) garde l’accès complet', () {
      expect(redirect('/home', team: true), isNull);
      expect(redirect('/maintenance', team: true), '/home');
    });

    test('maintenance levée : on quitte l’écran', () {
      expect(redirect('/maintenance', maintenance: false), '/home');
      expect(redirect('/home', maintenance: false), isNull);
    });
  });

  group('mode maintenance : parcours dans l’application', () {
    Future<(FakeApi, TestEnv)> boot(WidgetTester tester, {required Map<String, FakeHandler> routes}) async {
      final api = FakeApi({'GET me': (_) => jsonBody(200, {'data': userJson()}), ...routes});
      final env = TestEnv(
        api: api,
        token: 'tok',
        cachedUser: userJson(),
        extra: [maintenancePollIntervalProvider.overrideWithValue(null)],
      );
      usePhone(tester);
      addTearDown(tester.view.reset);
      await tester.pumpWidget(await env.app());
      await settle(tester, 10);
      return (api, env);
    }

    testWidgets('un 503 « maintenance » ouvre l’écran plein : message public, reprise, numéros d’urgence', (tester) async {
      var open = false;
      final (api, _) = await boot(
        tester,
        routes: {
          'GET dashboard': (_) => open ? jsonBody(200, {'data': {'tiles': [], 'featured': {'items': []}}}) : jsonBody(503, maintenance503(), headers: {'Retry-After': '600'}),
          'GET status': (_) => jsonBody(200, statusBody(maintenance: !open)),
        },
      );
      expect(find.byKey(const Key('maintenance-title')), findsOneWidget);
      expect(find.text('Mise à jour de la base : on revient vite.'), findsOneWidget);
      // 13 h 30 UTC = 14 h 30 à Porto-Novo (fuseau envoyé par le serveur).
      expect(find.textContaining('14 h 30'), findsOneWidget);
      expect(find.byKey(const Key('maintenance-sos')), findsOneWidget);
      expect(find.byKey(const Key('maintenance-report')), findsOneWidget);
      expect(find.byKey(const Key('maintenance-emergency')), findsOneWidget);

      // Sondage : toujours en maintenance, les numéros du serveur apparaissent.
      await tester.tap(find.byKey(const Key('maintenance-retry')));
      await settle(tester, 5);
      expect(api.calls('GET status'), hasLength(1));
      expect(find.byKey(const Key('maintenance-number-117')), findsOneWidget);
      expect(find.byKey(const Key('maintenance-number-118')), findsOneWidget);

      // Réouverture : l’écran disparaît tout seul, la navigation reprend.
      open = true;
      await tester.tap(find.byKey(const Key('maintenance-retry')));
      await settle(tester, 8);
      expect(find.byKey(const Key('maintenance-title')), findsNothing);
      await disposeApp(tester);
    });

    testWidgets('reprise dépassée : texte honnête, jamais une heure passée', (tester) async {
      await boot(
        tester,
        routes: {'GET dashboard': (_) => jsonBody(503, maintenance503(overdue: true, endsAt: null))},
      );
      expect(find.byKey(const Key('maintenance-overdue')), findsOneWidget);
      expect(find.text(S.t('maintenance_status_overdue')), findsOneWidget);
      expect(find.byKey(const Key('maintenance-ends')), findsNothing);
      await disposeApp(tester);
    });

    testWidgets('le SOS reste atteignable depuis l’écran de maintenance', (tester) async {
      await boot(
        tester,
        routes: {'GET dashboard': (_) => jsonBody(503, maintenance503())},
      );
      await tester.tap(find.byKey(const Key('maintenance-sos')));
      await settle(tester, 8);
      expect(find.byKey(const Key('maintenance-title')), findsNothing);
      final ctx = tester.element(find.byType(Scaffold).first);
      expect(GoRouter.of(ctx).state.uri.path, '/sos');
      await disposeApp(tester);
    });

    testWidgets('la connexion de l’équipe reste proposée sans session', (tester) async {
      final api = FakeApi({'GET dashboard': (_) => jsonBody(503, maintenance503())});
      final env = TestEnv(api: api, extra: [maintenancePollIntervalProvider.overrideWithValue(null)]);
      usePhone(tester);
      addTearDown(tester.view.reset);
      await tester.pumpWidget(await env.app());
      await settle(tester, 6);
      final c = ProviderScope.containerOf(tester.element(find.byType(MaterialApp)));
      c.read(maintenanceProvider.notifier).enter(maintenance503());
      GoRouter.of(tester.element(find.byType(Scaffold).first)).go('/signup');
      await settle(tester, 8);
      await tester.scrollUntilVisible(find.byKey(const Key('maintenance-team-login')), 200);
      expect(find.byKey(const Key('maintenance-team-login')), findsOneWidget);
      await disposeApp(tester);
    });
  });

  test('heure de reprise : fuseau du serveur, jour ajouté si ce n’est pas aujourd’hui', () {
    final now = DateTime.utc(2026, 10, 29, 9);
    expect(maintenanceEndLabel(DateTime.utc(2026, 10, 29, 13, 30), 'Africa/Porto-Novo', now: now), '14 h 30');
    expect(maintenanceEndLabel(DateTime.utc(2026, 10, 30, 8, 5), 'Africa/Porto-Novo', now: now), '30/10 à 9 h 05');
  });

  group('messages d’erreur (401, 403, 422, 429, 503, réseau)', () {
    ApiException ex(int status, {String? code, String? message, Map<String, List<String>> errors = const {}, int? retry}) =>
        ApiException(status: status, code: code, message: message, errors: errors, retryAfter: retry);

    Future<String> msg(WidgetTester tester, Object error) async {
      late String out;
      await tester.pumpWidget(
        MaterialApp(
          locale: AppStrings.locale,
          localizationsDelegates: [AppStringsDelegate(S), GlobalMaterialLocalizations.delegate, GlobalWidgetsLocalizations.delegate, GlobalCupertinoLocalizations.delegate],
          supportedLocales: const [AppStrings.locale],
          home: Builder(
            builder: (c) {
              out = errorMessage(c, error);
              return const SizedBox();
            },
          ),
        ),
      );
      return out;
    }

    testWidgets('chaque statut a un texte clair', (tester) async {
      expect(await msg(tester, ex(401)), S.t('auth_api_unauthenticated'));
      expect(await msg(tester, ex(403)), S.t('microcopy_error_forbidden'));
      expect(await msg(tester, ex(403, message: 'Ton compte est suspendu.')), 'Ton compte est suspendu.');
      expect(await msg(tester, ex(404)), S.t('microcopy_error_not_found'));
      expect(await msg(tester, ex(422, errors: {'email': ['Adresse invalide.']})), 'Adresse invalide.');
      expect(await msg(tester, ex(422)), S.t('microcopy_error_form'));
      expect(await msg(tester, ex(429, retry: 42)), S.t('errors_api_throttled', {'seconds': 42}));
      expect(await msg(tester, ex(503, code: 'maintenance')), S.t('errors_api_maintenance'));
      expect(await msg(tester, ex(500)), S.t('errors_api_server'));
      expect(await msg(tester, const ApiException(isNetwork: true)), S.t('microcopy_error_unreachable'));
    });
  });

  group('newsletter', () {
    Map<String, dynamic> prefs({bool on = false, List<String> interests = const [], bool pending = false}) => {
      'data': {
        'subscribed': on,
        'status': on ? 'actif' : null,
        'pending_confirmation': pending,
        'interests': interests,
        'available_interests': [
          {'slug': 'sante', 'label': 'Santé'},
          {'slug': 'droits', 'label': 'Droits'},
        ],
      },
    };

    testWidgets('abonnement puis centre d’intérêt, depuis les paramètres', (tester) async {
      var state = prefs();
      final api = FakeApi({
        'GET me': (_) => jsonBody(200, {'data': userJson()}),
        'GET me/newsletter': (_) => jsonBody(200, state),
        'GET me/settings': (_) => jsonBody(200, {'data': {'theme': 'light', 'sounds': true, 'notifications': true, 'locale': 'fr'}}),
        'PATCH me/newsletter': (o) {
          final body = o.data as Map;
          state = prefs(on: body['subscribed'] ?? true, interests: ((body['interests'] as List?) ?? const []).cast<String>());
          return jsonBody(200, state);
        },
      });
      final env = TestEnv(api: api, token: 'tok', cachedUser: userJson());
      usePhone(tester);
      addTearDown(tester.view.reset);
      await tester.pumpWidget(await env.app());
      await settle(tester, 6);
      final ctx = tester.element(find.byType(Scaffold).first);
      GoRouter.of(ctx).push('/newsletter');
      await settle(tester, 8);
      expect(find.text(S.t('account_newsletter_subscribed')), findsOneWidget);
      // Centres d'intérêt désactivés tant qu'on n'est pas abonné·e.
      expect(tester.widget<FilterChip>(find.byKey(const Key('newsletter-interest-sante'))).onSelected, isNull);

      await tester.tap(find.byKey(const Key('newsletter-switch')));
      await settle(tester, 8);
      expect(api.calls('PATCH me/newsletter').single.data, {'subscribed': true});
      await tester.tap(find.byKey(const Key('newsletter-interest-sante')));
      await settle(tester, 8);
      expect(api.calls('PATCH me/newsletter').last.data, {'interests': ['sante']});
      expect(tester.widget<FilterChip>(find.byKey(const Key('newsletter-interest-sante'))).selected, isTrue);
      await disposeApp(tester);
    });

    testWidgets('422 du serveur : son message est affiché, rien ne change', (tester) async {
      final api = FakeApi({
        'GET me': (_) => jsonBody(200, {'data': userJson()}),
        'GET me/newsletter': (_) => jsonBody(200, prefs()),
        'PATCH me/newsletter': (_) => jsonBody(422, {'message': S.t('account_newsletter_bounced'), 'code': 'newsletter_bounced'}),
      });
      final env = TestEnv(api: api, token: 'tok', cachedUser: userJson());
      usePhone(tester);
      addTearDown(tester.view.reset);
      await tester.pumpWidget(await env.app());
      await settle(tester, 6);
      GoRouter.of(tester.element(find.byType(Scaffold).first)).push('/newsletter');
      await settle(tester, 8);
      await tester.tap(find.byKey(const Key('newsletter-switch')));
      await settle(tester, 8);
      expect(find.text(S.t('account_newsletter_bounced')), findsOneWidget);
      await disposeApp(tester);
    });
  });

  group('hors ligne : collections communes tirées par sync/pull', () {
    testWidgets('urgences : sans réseau ni copie d’API, les fiches et numéros tirés servent', (tester) async {
      final api = FakeApi({
        'GET me': (_) => jsonBody(200, {'data': userJson()}),
        'GET urgences': (o) => throw offlineError(o),
        'GET thematiques': (o) => throw offlineError(o),
        'GET thematiques/droits': (o) => throw offlineError(o),
      });
      final env = TestEnv(api: api, token: 'tok', cachedUser: userJson(), online: false);
      usePhone(tester);
      addTearDown(tester.view.reset);
      await tester.pumpWidget(await env.app());
      await settle(tester, 4);
      final c = ProviderScope.containerOf(tester.element(find.byType(MaterialApp)));
      final store = c.read(commonStoreProvider);
      await store.putJson('emergency_numbers', {
        'items': [
          {'label': 'Police secours', 'number': '117'},
        ],
      });
      await store.put('emergency_contacts', 5, {'id': 5, 'name': 'Me Adjovi', 'role_label': 'Juriste', 'phone': '+22997000000'});
      await store.put('thematiques', 9, {'id': 9, 'slug': 'droits', 'title': 'Connaître tes droits', 'summary': 'Les recours', 'body': '<p>Texte</p>', 'sections': []});

      await tester.runAsync(() async {
        final emergency = await c.read(alertsApiProvider).emergency();
        expect(emergency.stale, isTrue);
        expect(emergency.firstService?.number, '117');
        expect(emergency.contacts.single.name, 'Me Adjovi');

        final themes = await c.read(homeApiProvider).themes();
        expect(themes.items.single.title, 'Connaître tes droits');
        expect((await c.read(homeApiProvider).themes(q: 'zzz')).items, isEmpty);
        final detail = await c.read(homeApiProvider).theme('droits');
        expect(detail.body, '<p>Texte</p>');
      });
      await disposeApp(tester);
    });

    testWidgets('sans réseau et sans rien de tiré : l’erreur réseau reste visible', (tester) async {
      final api = FakeApi({
        'GET me': (_) => jsonBody(200, {'data': userJson()}),
        'GET urgences': (o) => throw offlineError(o),
      });
      final env = TestEnv(api: api, token: 'tok', cachedUser: userJson(), online: false);
      usePhone(tester);
      addTearDown(tester.view.reset);
      await tester.pumpWidget(await env.app());
      await settle(tester, 4);
      final c = ProviderScope.containerOf(tester.element(find.byType(MaterialApp)));
      await tester.runAsync(() async {
        await expectLater(c.read(alertsApiProvider).emergency(), throwsA(isA<DioException>()));
      });
      await disposeApp(tester);
    });
  });

  test('plus aucune page d’attente : aucune route /soon dans le code', () {
    for (final f in [
      'lib/app/router.dart',
      'lib/features/home/presentation/dashboard_page.dart',
      'lib/features/home/presentation/themes_pages.dart',
      'lib/features/pair/presentation/pair_home_page.dart',
    ]) {
      expect(File(f).readAsStringSync().contains('/soon'), isFalse, reason: f);
    }
  });
}
