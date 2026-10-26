import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/date_symbol_data_local.dart';

import '../support/harness.dart';

final S = loadStrings();

/// Fait défiler jusqu'au widget (animation incluse) puis le touche.
Future<void> tapVisible(WidgetTester tester, Finder f) async {
  await tester.ensureVisible(f);
  await tester.pumpAndSettle();
  await tester.tap(f);
}

Future<void> showText(WidgetTester tester, String text) async {
  await tester.scrollUntilVisible(find.text(text), 250, scrollable: find.byType(Scrollable).first);
  await tester.pumpAndSettle();
}

Map<String, dynamic> theme(int id, String slug, String title, {String cat = 'vbg'}) => {
      'id': id,
      'slug': slug,
      'title': title,
      'summary': 'Résumé de $title',
      'cover_url': null,
      'reading_minutes': 6,
      'category': {'slug': cat, 'label': cat == 'vbg' ? 'Violences' : 'Droits', 'icon': 'x', 'color': 'y'},
      'published_at': '2026-10-01T10:00:00+01:00',
      'url': 'https://x/thematiques/$slug',
    };

Map<String, dynamic> dashboard({Map<String, dynamic>? peer, bool complete = false}) => {
      'user': {'id': 12, 'pseudo': 'Awa_S'},
      'tiles': [
        {'key': 'report', 'route': '/sighub', 'count': null},
        {'key': 'emergency', 'route': '/urgences', 'count': null},
        {'key': 'assistant', 'route': '/assistant', 'count': null},
        {'key': 'learn', 'route': '/thematiques', 'count': 5},
      ],
      'counters': {'notifications': 3, 'messages': 0},
      'profile': {'completion': 43, 'complete': complete},
      'featured': {
        'total': 2,
        'items': [theme(1, 'comprendre-les-vbg', 'Comprendre les VBG'), theme(2, 'tes-droits', 'Connaître tes droits', cat: 'droits')],
      },
      'next_event': {'id': '01J', 'title': 'Atelier « Dire non »', 'starts_at': '2026-11-07T15:00:00+01:00', 'ends_at': null, 'is_online': true},
      'peer': peer,
    };

Future<void> pumpHome(WidgetTester tester, TestEnv env) async {
  usePhone(tester);
  addTearDown(tester.view.reset);
  await tester.pumpWidget(await env.app());
  await tester.pumpAndSettle();
}

TestEnv signedIn(FakeApi api, {bool online = true, int unread = 3}) =>
    TestEnv(api: api, token: 'tok', cachedUser: userJson(), online: online, unread: unread);

void main() {
  setUpAll(() => initializeDateFormatting('fr'));

  group('tableau de bord', () {
    testWidgets('tuiles, à la une, prochain événement, complétion du profil', (tester) async {
      final api = FakeApi({
        'GET me': (_) => jsonBody(200, {'data': userJson()}),
        'GET dashboard': (_) => jsonBody(200, {'data': dashboard()}, headers: {'ETag': '"d1"'}),
      });
      await pumpHome(tester, signedIn(api));
      expect(find.text(S.t('peer_home_greeting', {'name': 'Awa_S'})), findsOneWidget);
      // Cloche : badge du compteur.
      expect(find.text('3'), findsOneWidget);
      expect(find.text(S.t('feed_quick_report')), findsOneWidget);
      expect(find.text(S.t('feed_quick_emergency')), findsOneWidget);
      expect(find.text(S.t('feed_quick_assistant')), findsOneWidget);
      expect(find.text(S.t('mobile_tile_learn_count', {'count': 5})), findsOneWidget);
      expect(find.text(S.t('profile_completion', {'percent': 43})), findsOneWidget);
      await showText(tester, 'Comprendre les VBG');
      expect(find.text('Comprendre les VBG'), findsOneWidget);
      await showText(tester, 'Atelier « Dire non »');
      expect(find.text('Atelier « Dire non »'), findsOneWidget);
      // Pas de section pair pour un membre.
      expect(find.text(S.t('peer_home_tasks_title')), findsNothing);
    });

    testWidgets('espace pair : KPI et tâches', (tester) async {
      final api = FakeApi({
        'GET me': (_) => jsonBody(200, {'data': userJson(role: 'pair')}),
        'GET dashboard': (_) => jsonBody(200, {
              'data': dashboard(peer: {
                'kpi': {'collected': 12, 'this_month': 3, 'in_progress': 4, 'closed': 8},
                'routed_alerts': {'open': 2, 'new': 1, 'items': []},
                'tasks': [
                  {'key': 'alerts_to_take', 'count': 1},
                  {'key': 'cases_to_contact', 'count': 2},
                ],
              })
            }, headers: {'Cache-Control': 'no-store, private'}),
      });
      final env = TestEnv(api: api, token: 'tok', cachedUser: userJson(role: 'pair'));
      await pumpHome(tester, env);
      expect(find.text(S.t('peer_home_tasks_title')), findsOneWidget);
      expect(find.text('12'), findsOneWidget);
      expect(find.text(S.t('peer_home_tasks_alerts_to_take', {'count': 1})), findsOneWidget);
      expect(find.text(S.t('peer_home_tasks_cases_to_contact', {'count': 2})), findsOneWidget);
      // Section pair : no-store, rien n'est gardé sur l'appareil.
      expect(env.db.privateCache.get('dashboard'), isNull);
    });

    testWidgets('hors ligne : copie Hive servie, bandeau « hors connexion » visible', (tester) async {
      var offline = false;
      final api = FakeApi({
        'GET me': (_) => jsonBody(200, {'data': userJson()}),
        'GET dashboard': (o) {
          if (offline) throw DioException.connectionError(requestOptions: o, reason: 'x');
          return jsonBody(200, {'data': dashboard()}, headers: {'ETag': '"d1"'});
        },
      });
      final env = signedIn(api, online: false);
      await env.db.privateCache.put(
          'dashboard',
          '{"etag":"\\"d1\\"","body":{"data":${_json(dashboard())}}}');
      offline = true;
      await pumpHome(tester, env);
      expect(find.text(S.t('menus_member_more_offline_banner')), findsOneWidget);
      expect(find.text(S.t('mobile_stale_notice')), findsOneWidget);
      await showText(tester, 'Comprendre les VBG');
      expect(find.text('Comprendre les VBG'), findsOneWidget);
    });

    testWidgets('e-mail non confirmé : rappel avec accès au code', (tester) async {
      final api = FakeApi({
        'GET me': (_) => jsonBody(200, {'data': userJson(emailVerified: false)}),
        'GET dashboard': (_) => jsonBody(200, {'data': dashboard()}),
      });
      final env = TestEnv(api: api, token: 'tok', cachedUser: userJson(emailVerified: false));
      await pumpHome(tester, env);
      expect(find.text(S.t('auth_verify_banner_title')), findsOneWidget);
      await tester.tap(find.text(S.t('auth_verify_banner_enter_code')));
      await tester.pumpAndSettle();
      expect(find.text(S.t('auth_otp_screen_heading')), findsOneWidget);
    });

    testWidgets('barre d’onglets : Accueil · Fil · SOS · Messages · Profil', (tester) async {
      final api = FakeApi({
        'GET me': (_) => jsonBody(200, {'data': userJson()}),
        'GET dashboard': (_) => jsonBody(200, {'data': dashboard()}),
      });
      await pumpHome(tester, signedIn(api));
      for (final k in ['menus_mobile_home', 'menus_mobile_feed', 'mobile_tab_sos', 'menus_mobile_messages', 'menus_mobile_profile']) {
        expect(find.descendant(of: find.byType(NavigationBar), matching: find.text(S.t(k))), findsOneWidget);
      }
    });
  });

  group('thématiques', () {
    FakeApi themesApi() => FakeApi({
          'GET me': (_) => jsonBody(200, {'data': userJson()}),
          'GET dashboard': (_) => jsonBody(200, {'data': dashboard()}),
          'GET thematiques': (o) {
            if (o.headers['If-None-Match'] == '"t1"' && o.queryParameters['categorie'] == null && o.queryParameters['q'] == null) {
              return notModified();
            }
            final only = o.queryParameters['categorie'];
            final all = [theme(1, 'comprendre-les-vbg', 'Comprendre les VBG'), theme(2, 'tes-droits', 'Connaître tes droits', cat: 'droits')];
            final items = only == null ? all : all.where((t) => (t['category'] as Map)['slug'] == only).toList();
            return jsonBody(200, {
              'data': items,
              'meta': {
                'total': items.length,
                'total_all': 2,
                'has_more': false,
                'categorie': only,
                'categories': [
                  {'slug': 'vbg', 'label': 'Violences', 'icon': 'a', 'color': 'b', 'count': 1},
                  {'slug': 'droits', 'label': 'Droits', 'icon': 'a', 'color': 'b', 'count': 1},
                ],
              }
            }, headers: {'ETag': '"t1"'});
          },
          'GET thematiques/comprendre-les-vbg': (_) => jsonBody(200, {
                'data': {
                  ...theme(1, 'comprendre-les-vbg', 'Comprendre les VBG'),
                  'content_warning': 'Cette fiche aborde des sujets difficiles.',
                  'body': '<p>Introduction</p>',
                  'sections': [
                    {'title': 'Reconnaître les signes', 'body': '<p>Premier signe</p><ul><li>un</li></ul>'},
                  ],
                  'contacts': [
                    {'id': 9, 'name': 'Mme Sèna', 'role_label': 'Juriste', 'org': 'ONG X', 'phone': '+229 01 97 00 00 00', 'availability': 'Lun-Ven'},
                  ],
                  'related': [theme(2, 'tes-droits', 'Connaître tes droits', cat: 'droits')],
                  'primary_resource': null,
                  'faqs': [
                    {'id': 1, 'question': 'Que faire en premier ?', 'answer': '<p>Te mettre en sécurité.</p>'}
                  ],
                }
              }, headers: {'ETag': '"f1"'}),
        });

    testWidgets('liste, filtre par catégorie (param categorie), revalidation ETag', (tester) async {
      final api = themesApi();
      final env = signedIn(api);
      await pumpHome(tester, env);
      await tapVisible(tester, find.text(S.t('feed_featured_see_all')));
      await tester.pumpAndSettle();
      expect(find.text(S.t('pages_thematiques_title')), findsWidgets);
      expect(find.text('Comprendre les VBG'), findsOneWidget);
      expect(find.text('Connaître tes droits'), findsOneWidget);
      expect(find.text(S.t('pages_thematiques_filter_all', {'count': 2})), findsOneWidget);

      await tapVisible(tester, find.widgetWithText(ChoiceChip, 'Droits (1)'));
      await tester.pumpAndSettle();
      expect(api.calls('GET thematiques').last.queryParameters['categorie'], 'droits');
      expect(find.text('Comprendre les VBG'), findsNothing);

      // La copie Hive existe pour la liste complète, avec son ETag.
      expect(env.db.publicCache.get('thematiques'), contains('t1'));
    });

    testWidgets('fiche : avertissement, sections, FAQ, contact appelable, aucune donnée en dur', (tester) async {
      final api = themesApi();
      await pumpHome(tester, signedIn(api));
      await showText(tester, 'Comprendre les VBG');
      await tester.tap(find.text('Comprendre les VBG').first);
      await tester.pumpAndSettle();
      expect(find.text('Cette fiche aborde des sujets difficiles.'), findsOneWidget);
      expect(find.text('Introduction'), findsOneWidget);
      expect(find.text('Reconnaître les signes'), findsOneWidget);
      expect(find.text('Premier signe\n\n• un'), findsOneWidget);
      expect(find.text(S.t('pages_thematiques_contacts_label')), findsOneWidget);
      expect(find.text('Mme Sèna'), findsOneWidget);
      expect(find.byTooltip('${S.t('pages_thematiques_call')} Mme Sèna'), findsOneWidget);
      await tester.scrollUntilVisible(find.text('Que faire en premier ?'), 300, scrollable: find.byType(Scrollable).first);
      expect(find.text('Que faire en premier ?'), findsOneWidget);
    });
  });

  group('ressources', () {
    testWidgets('catalogue paginé, poids du fichier, téléchargement par lien signé', (tester) async {
      final api = FakeApi({
        'GET me': (_) => jsonBody(200, {'data': userJson()}),
        'GET dashboard': (_) => jsonBody(200, {'data': dashboard()}),
        'GET ressources': (o) {
          final page = int.parse('${o.queryParameters['page'] ?? 1}');
          return jsonBody(200, {
            'data': [
              {
                'id': page * 10 + 1,
                'slug': 'r$page',
                'title': 'Ressource page $page',
                'kind': 'fiche',
                'kind_label': 'Fiche',
                'summary': null,
                'category': {'slug': 'vbg', 'label': 'Violences'},
                'cover_url': null,
                'file_size': 430080,
                'file_size_label': '420 Ko',
                'downloadable': true,
                'external_url': null,
              }
            ],
            'meta': {
              'current_page': page,
              'last_page': 2,
              'per_page': 20,
              'total': 2,
              'categories': [{'slug': 'vbg', 'label': 'Violences', 'count': 2}],
            }
          });
        },
      });
      final env = signedIn(api);
      await pumpHome(tester, env);
      // Ouverture directe de l'écran Ressources.
      GoRouter.of(tester.element(find.byType(Scaffold).first)).push('/ressources');
      await tester.pumpAndSettle();
      expect(find.text('Ressource page 1'), findsOneWidget);
      expect(find.textContaining('420 Ko'), findsOneWidget);
      expect(find.text(S.t('common_download')), findsOneWidget);

      await tester.tap(find.text(S.t('common_load_more')));
      await tester.pumpAndSettle();
      expect(find.text('Ressource page 2'), findsOneWidget);
      expect(api.calls('GET ressources').last.queryParameters['page'], 2);
    });
  });

  group('notifications', () {
    testWidgets('non lues par défaut, lecture au toucher, tout marquer comme lu', (tester) async {
      var readAll = false;
      final api = FakeApi({
        'GET me': (_) => jsonBody(200, {'data': userJson()}),
        'GET dashboard': (_) => jsonBody(200, {'data': dashboard()}),
        'GET notifications': (o) => jsonBody(200, {
              'data': readAll
                  ? []
                  : [
                      {'id': 'n1', 'type': 'x', 'category': 'messages', 'title': 'Chantal t’a écrit', 'body': 'Salut', 'read': false, 'created_at': DateTime.now().subtract(const Duration(minutes: 5)).toIso8601String()},
                      {'id': 'n2', 'type': 'x', 'category': 'evenements', 'title': 'Atelier samedi', 'body': null, 'read': false, 'created_at': DateTime.now().subtract(const Duration(hours: 3)).toIso8601String()},
                    ],
              'meta': {'current_page': 1, 'last_page': 1, 'filter': o.queryParameters['filter']},
            }),
        'POST notifications/n1/read': (_) => jsonBody(200, {'data': {}}),
        'POST notifications/read-all': (_) {
          readAll = true;
          return jsonBody(200, {'data': {'marked': 2, 'unread': 0}});
        },
      });
      await pumpHome(tester, signedIn(api));
      await tester.tap(find.byIcon(Icons.notifications_outlined).first);
      await tester.pumpAndSettle();
      expect(find.text(S.t('notifications_page_title')), findsOneWidget);
      expect(api.calls('GET notifications').first.queryParameters['filter'], 'unread');
      expect(find.text('Chantal t’a écrit'), findsOneWidget);
      expect(find.text(S.t('dates_relative_minutes', {'count': 5})), findsOneWidget);

      await tester.tap(find.text('Chantal t’a écrit'));
      await tester.pumpAndSettle();
      expect(api.calls('POST notifications/n1/read'), hasLength(1));

      await tester.tap(find.text(S.t('mobile_notifications_filter_all')));
      await tester.pumpAndSettle();
      expect(api.calls('GET notifications').last.queryParameters['filter'], 'all');

      await tester.tap(find.text(S.t('notifications_page_mark_all_read')));
      await tester.pumpAndSettle();
      expect(api.calls('POST notifications/read-all'), hasLength(1));
      expect(find.text(S.t('notifications_page_empty')), findsOneWidget);
    });
  });

  testWidgets('déconnexion depuis le profil : retour à la connexion, privé vidé', (tester) async {
    final api = FakeApi({
      'GET me': (_) => jsonBody(200, {'data': userJson()}),
      'GET dashboard': (_) => jsonBody(200, {'data': dashboard()}, headers: {'ETag': '"d1"'}),
      'POST auth/logout': (_) => jsonBody(200, {'data': {'logged_out': true}}),
    });
    final env = signedIn(api);
    await pumpHome(tester, env);
    await tester.tap(find.descendant(of: find.byType(NavigationBar), matching: find.text(S.t('menus_mobile_profile'))));
    await tester.pumpAndSettle();
    await tester.tap(find.text(S.t('buttons_logout')));
    await tester.pumpAndSettle();
    expect(find.text(S.t('auth_login_heading')), findsOneWidget);
    expect(env.db.privateCache.get('dashboard'), isNull);
    expect(env.db.session.get('user'), isNull);
  });
}

String _json(Object o) => jsonEncode(o);
