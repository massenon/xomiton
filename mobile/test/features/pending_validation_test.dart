import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:xomiton/app/router.dart';
import 'package:xomiton/core/network/api_exception.dart';
import 'package:xomiton/core/providers/core_providers.dart';
import 'package:xomiton/core/ui/xm_widgets.dart' show errorMessage;
import 'package:xomiton/features/auth/data/app_user.dart';

import '../support/fakes.dart';
import '../support/harness.dart';

final S = loadStrings();

Map<String, dynamic> _post() => {
      'id': 'P1',
      'author': {'id': 3, 'pseudo': 'Kafui', 'avatar_url': null, 'role': 'membre', 'anonymous': false},
      'is_mine': false,
      'body': 'Un récit',
      'tag': 'temoignage',
      'tag_label': 'Témoignage',
      'likes_count': 0,
      'comments_count': 0,
      'liked': false,
      'poll': null,
      'published_at': DateTime.now().subtract(const Duration(hours: 2)).toIso8601String(),
      'can': {'update': false, 'delete': false, 'report': true, 'comment': true},
    };

FakeApi api({bool stillPending = true, int status = 200}) => FakeApi({
      'GET me': (_) => jsonBody(200, {'data': userJson(pending: stillPending)}),
      'GET feed': (_) => jsonBody(200, {'data': [_post()], 'meta': {}}),
      'GET posts/P1': (_) => jsonBody(200, {'data': _post()}),
      'GET posts/P1/comments': (_) => jsonBody(200, {'data': [], 'meta': {}}),
      'GET dashboard': (_) => jsonBody(200, {
            'data': {
              'tiles': [],
              'counters': {'notifications': 0, 'messages': 0},
              'profile': {'completion': 43, 'complete': false},
              'featured': {'total': 0, 'items': []},
              'next_event': null,
              'peer': null,
            }
          }),
    });

TestEnv pendingEnv(FakeApi a, {bool online = true}) =>
    TestEnv(api: a, token: 'tok', cachedUser: userJson(pending: true), online: online);

Future<void> boot(WidgetTester tester, TestEnv env, [String? route]) async {
  usePhone(tester);
  addTearDown(tester.view.reset);
  await tester.pumpWidget(await env.app());
  await settle(tester, 6);
  if (route != null) {
    goTo(tester, route);
    await settle(tester, 10);
  }
}

void main() {
  setUpAll(() => initializeDateFormatting('fr'));

  group('modèle et erreurs', () {
    test('AppUser lit pending_validation de GET me', () {
      expect(AppUser.fromJson(userJson(pending: true)).pendingValidation, isTrue);
      expect(AppUser.fromJson(userJson()).pendingValidation, isFalse);
    });

    test('403 « en attente » reconnu par son code, pas un 403 ordinaire', () {
      expect(const ApiException(status: 403, code: 'account_pending_validation').isPendingValidation, isTrue);
      expect(const ApiException(status: 403, code: 'forbidden').isPendingValidation, isFalse);
      expect(const ApiException(status: 422, code: 'pending').isPendingValidation, isFalse);
    });

    test('redirection : arrivée sur /en-attente tant que le compte attend, accueil ensuite', () {
      String? r(String p, {required bool pending}) => appRedirect(
            authenticated: true,
            onboardingSeen: true,
            uri: Uri.parse(p),
            pendingValidation: pending,
          );
      expect(r('/login', pending: true), '/en-attente');
      expect(r('/splash', pending: true), '/en-attente');
      expect(r('/en-attente', pending: true), isNull);
      expect(r('/home', pending: true), isNull, reason: 'la lecture reste ouverte');
      expect(r('/sos', pending: true), isNull);
      expect(r('/en-attente', pending: false), '/home');
      expect(r('/login', pending: false), '/home');
      // Sans session, l'écran d'attente n'existe pas.
      expect(appRedirect(authenticated: false, onboardingSeen: true, uri: Uri.parse('/en-attente')), '/login');
    });
  });

  group('écran d’attente', () {
    testWidgets('reprise de l’application avec un compte en attente : écran clair, options permises seulement', (tester) async {
      await boot(tester, pendingEnv(api()));
      expect(find.text(S.t('mobile_pending_title')), findsOneWidget);
      expect(find.text(S.t('mobile_pending_allowed_sos')), findsOneWidget);
      expect(find.text(S.t('mobile_pending_allowed_read')), findsOneWidget);
      // Le bouton « Vérifier maintenant » fait une cible d'au moins 48 dp.
      expect(tester.getSize(find.byKey(const ValueKey('pending-check'))).height, greaterThanOrEqualTo(48));
      await disposeApp(tester);
    });

    testWidgets('« Vérifier maintenant » : toujours en attente → message, GET me relu', (tester) async {
      final a = api();
      await boot(tester, pendingEnv(a));
      final before = a.calls('GET me').length;
      await tester.tap(find.byKey(const ValueKey('pending-check')));
      await settle(tester, 6);
      expect(a.calls('GET me').length, greaterThan(before));
      expect(find.text(S.t('mobile_pending_still')), findsOneWidget);
      expect(find.text(S.t('mobile_pending_title')), findsOneWidget);
      await disposeApp(tester);
    });

    testWidgets('tirer pour rafraîchir relit GET me', (tester) async {
      final a = api();
      await boot(tester, pendingEnv(a));
      final before = a.calls('GET me').length;
      await tester.fling(find.byType(ListView).first, const Offset(0, 400), 1000);
      await settle(tester, 8);
      expect(a.calls('GET me').length, greaterThan(before));
      await disposeApp(tester);
    });

    testWidgets('l’équipe a validé : au prochain GET me, l’accueil s’ouvre et le bandeau disparaît', (tester) async {
      var validated = false;
      final a = api();
      a.routes['GET me'] = (_) => jsonBody(200, {'data': userJson(pending: !validated)});
      await boot(tester, pendingEnv(a));
      expect(find.text(S.t('mobile_pending_title')), findsOneWidget);
      validated = true;
      await tester.tap(find.byKey(const ValueKey('pending-check')));
      await settle(tester, 10);
      expect(find.text(S.t('mobile_pending_title')), findsNothing);
      expect(find.text(S.t('peer_home_greeting', {'name': 'Awa_S'})), findsOneWidget);
      expect(find.text(S.t('auth_pending')), findsNothing);
      await disposeApp(tester);
    });

    testWidgets('accueil d’un compte en attente : bandeau et lien vers l’écran d’attente', (tester) async {
      await boot(tester, pendingEnv(api()), '/home');
      expect(find.text(S.t('auth_pending')), findsOneWidget);
      await tester.tap(find.text(S.t('mobile_pending_see_more')));
      await settle(tester, 6);
      expect(find.text(S.t('mobile_pending_title')), findsOneWidget);
      await disposeApp(tester);
    });
  });

  group('actions fermées avec explication', () {
    testWidgets('fil : lecture ouverte, bouton « Publier » grisé, explication visible', (tester) async {
      await boot(tester, pendingEnv(api()), '/feed');
      expect(find.text('Un récit'), findsOneWidget);
      expect(find.text(S.t('mobile_pending_action_blocked')), findsOneWidget);
      final fab = tester.widget<FloatingActionButton>(find.byType(FloatingActionButton));
      expect(fab.onPressed, isNull);
      await disposeApp(tester);
    });

    testWidgets('compte validé : bouton « Publier » actif, aucune explication', (tester) async {
      await boot(tester, TestEnv(api: api(stillPending: false), token: 'tok', cachedUser: userJson()), '/feed');
      expect(find.text(S.t('mobile_pending_action_blocked')), findsNothing);
      expect(tester.widget<FloatingActionButton>(find.byType(FloatingActionButton)).onPressed, isNotNull);
      await disposeApp(tester);
    });

    testWidgets('écrire une publication, un message : explication au lieu du formulaire', (tester) async {
      await boot(tester, pendingEnv(api()), '/feed/new');
      expect(find.text(S.t('mobile_pending_action_blocked')), findsOneWidget);
      expect(find.byType(TextField), findsNothing);
      goTo(tester, '/messages/new');
      await settle(tester, 8);
      expect(find.text(S.t('mobile_pending_action_blocked')), findsOneWidget);
      expect(find.byType(TextField), findsNothing);
      await disposeApp(tester);
    });

    testWidgets('onglet Messages : explication à la place de la liste', (tester) async {
      await boot(tester, pendingEnv(api()), '/messages');
      expect(find.text(S.t('mobile_pending_action_blocked')), findsOneWidget);
      await disposeApp(tester);
    });

    testWidgets('commenter : explication à la place du champ de commentaire', (tester) async {
      await boot(tester, pendingEnv(api()), '/feed/P1');
      expect(find.text(S.t('mobile_pending_action_blocked')), findsOneWidget);
      expect(find.text(S.t('feed_comments_field_label')), findsNothing);
      await disposeApp(tester);
    });
  });

  group('403 « compte en attente » du serveur', () {
    testWidgets('message du serveur affiché tel quel ; sinon texte de l’application ; GET me relu', (tester) async {
      final a = api();
      a.routes['POST posts'] = (_) => jsonBody(403, {
            'message': 'Ton compte doit d’abord être validé par l’équipe.',
            'code': 'account_pending_validation',
          });
      await boot(tester, pendingEnv(a));
      final c = ProviderScope.containerOf(tester.element(find.byType(MaterialApp)));
      final before = a.calls('GET me').length;
      Object? caught;
      await tester.runAsync(() async {
        try {
          await c.read(dioProvider).post<dynamic>('posts', data: {});
        } catch (e) {
          caught = e;
        }
        await Future<void>.delayed(const Duration(milliseconds: 50));
      });
      expect(caught, isA<DioException>());
      final api0 = ApiException.from(caught!);
      expect(api0.isPendingValidation, isTrue);
      expect(a.calls('GET me').length, greaterThan(before), reason: 'le statut est relu');
      final ctx = tester.element(find.byType(Scaffold).first);
      expect(errorMessage(ctx, caught!), 'Ton compte doit d’abord être validé par l’équipe.');
      expect(errorMessage(ctx, DioException(
        requestOptions: RequestOptions(path: 'posts'),
        error: const ApiException(status: 403, code: 'account_pending_validation'),
      )), S.t('mobile_pending_forbidden'));
      await disposeApp(tester);
    });
  });
}
