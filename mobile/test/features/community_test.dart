import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:xomiton/core/platform/platform_services.dart';
import 'package:xomiton/core/providers/core_providers.dart';

import '../support/fakes.dart';
import '../support/harness.dart';

final S = loadStrings();

Map<String, dynamic> conv({bool canSend = true, bool blockedByMe = false}) => {
  'id': '01CONV',
  'kind': 'private',
  'name': 'Kafui',
  'other': {'id': 7, 'pseudo': 'Kafui'},
  'last_message': null,
  'unread_count': 0,
  'can_send': canSend,
  'blocked_by_me': blockedByMe,
  'muted': false,
};

Map<String, dynamic> msg(int id, String body, String uuid) => {
  'id': id,
  'client_uuid': uuid,
  'body': body,
  'deleted': false,
  'mine': true,
  'author': {'id': 12, 'pseudo': 'Awa_S'},
  'created_at': DateTime.now().toIso8601String(),
  'status': 'sent',
};

TestEnv member(
  FakeApi api, {
  bool online = true,
  List<Override> extra = const [],
}) => TestEnv(
  api: api,
  token: 'tok',
  cachedUser: userJson(),
  online: online,
  extra: extra,
);

FakeApi apiWith(Map<String, FakeHandler> extra) => FakeApi({
  'GET me': (_) => jsonBody(200, {'data': userJson()}),
  ...extra,
});

Future<void> boot(WidgetTester tester, TestEnv env, String route) async {
  usePhone(tester);
  addTearDown(tester.view.reset);
  await tester.pumpWidget(await env.app());
  await settle(tester);
  goTo(tester, route);
  await settle(tester, 10);
}

void main() {
  setUpAll(() => initializeDateFormatting('fr'));

  group('messages', () {
    testWidgets('liste des conversations avec non lus', (tester) async {
      final api = apiWith({
        'GET conversations': (_) => jsonBody(200, {
          'data': [
            {
              ...conv(),
              'unread_count': 2,
              'last_message': {
                'id': 1,
                'preview': 'Coucou',
                'preview_hidden': false,
                'mine': false,
                'at': '2026-10-26T10:00:00+01:00',
              },
              'last_message_at': DateTime.now().toIso8601String(),
            },
          ],
          'meta': {'poll_seconds': 15},
        }),
      });
      await boot(tester, member(api), '/messages');
      expect(find.text('Kafui'), findsOneWidget);
      expect(find.text('Coucou'), findsOneWidget);
      expect(find.text('2'), findsOneWidget);
      await disposeApp(tester);
    });

    testWidgets('nouvelle conversation → envoi avec client_uuid → bloquer', (
      tester,
    ) async {
      var blocked = false;
      final api = apiWith({
        'GET conversations': (_) =>
            jsonBody(200, {'data': <Object>[], 'meta': {}}),
        'GET conversations/recipients': (_) => jsonBody(200, {
          'data': [
            {'id': 7, 'pseudo': 'Kafui', 'avatar_url': null},
          ],
        }),
        'POST conversations': (_) => jsonBody(201, {'data': conv()}),
        'GET conversations/01CONV': (_) => jsonBody(200, {
          'data': conv(blockedByMe: blocked, canSend: !blocked),
        }),
        'GET conversations/01CONV/messages': (_) => jsonBody(200, {
          'data': <Object>[],
          'meta': {'next_cursor': null, 'last_id': 0},
        }),
        'POST conversations/01CONV/read': (_) => jsonBody(200, {
          'data': {'unread_count': 0},
        }),
        'POST conversations/01CONV/messages': (o) {
          final d = o.data as Map;
          return jsonBody(201, {
            'data': msg(41, '${d['body']}', '${d['client_uuid']}'),
            'meta': {'duplicate': false},
          });
        },
        'POST conversations/01CONV/block': (_) {
          blocked = true;
          return jsonBody(200, {
            'data': conv(blockedByMe: true, canSend: false),
          });
        },
      });
      final env = member(api);
      await boot(tester, env, '/messages/new');
      expect(find.text('Kafui'), findsOneWidget);
      await tester.tap(find.byKey(const Key('person-7')));
      await settle(tester, 10);
      expect(api.calls('POST conversations').single.data, {
        'kind': 'private',
        'user_id': 7,
      });
      expect(
        find.text(S.t('community_messages_empty_conversation')),
        findsOneWidget,
      );

      await tester.enterText(find.byType(TextField), 'Bonjour Kafui');
      await tester.tap(find.byTooltip(S.t('community_messages_send')));
      await settle(tester, 8);
      final post = api.calls('POST conversations/01CONV/messages').single;
      final body = post.data as Map;
      expect(body['client_uuid'], matches(RegExp(r'^[0-9a-f-]{36}$')));
      expect(
        post.headers['Idempotency-Key'],
        body['client_uuid'],
        reason: 'client_uuid = Idempotency-Key',
      );
      expect(find.text('Bonjour Kafui'), findsOneWidget);
      expect(env.db.outbox.keys, isEmpty);

      // Bloquer depuis le menu de la conversation.
      await tester.tap(find.byTooltip(S.t('community_messages_menu')));
      await settle(tester, 4);
      await tester.tap(find.text(S.t('community_messages_actions_block')));
      await settle(tester, 4);
      await tester.tap(find.text(S.t('common_yes')));
      await settle(tester, 6);
      expect(api.calls('POST conversations/01CONV/block'), hasLength(1));
      expect(
        find.text(S.t('community_messages_blocked_notice')),
        findsOneWidget,
      );
      expect(
        find.byTooltip(S.t('community_messages_send')),
        findsNothing,
        reason: 'plus de saisie',
      );
      await disposeApp(tester);
    });

    testWidgets('signaler la conversation avec un motif', (tester) async {
      final api = apiWith({
        'GET conversations/01CONV': (_) => jsonBody(200, {'data': conv()}),
        'GET conversations/01CONV/messages': (_) =>
            jsonBody(200, {'data': <Object>[], 'meta': {}}),
        'POST conversations/01CONV/read': (_) => jsonBody(200, {'data': {}}),
        'POST conversations/01CONV/report': (_) => jsonBody(201, {
          'data': {'reported': true},
        }),
      });
      await boot(tester, member(api), '/messages/01CONV');
      await tester.tap(find.byTooltip(S.t('community_messages_menu')));
      await settle(tester, 4);
      await tester.tap(find.text(S.t('community_messages_actions_report')));
      await settle(tester, 4);
      await tester.tap(find.byType(DropdownButtonFormField<String>));
      await settle(tester, 4);
      await tester.tap(
        find.text(S.t('enums_feed_report_reason_harcelement')).last,
      );
      await settle(tester, 4);
      await tester.tap(find.text(S.t('community_messages_report_submit')));
      await settle(tester, 6);
      expect(
        (api.calls('POST conversations/01CONV/report').single.data
            as Map)['reason'],
        'harcelement',
      );
      await disposeApp(tester);
    });

    testWidgets(
      'hors ligne : message grisé « En attente d’envoi », envoyé une seule fois au retour du réseau',
      (tester) async {
        var online = false;
        final api = apiWith({
          'GET conversations/01CONV': (_) => jsonBody(200, {'data': conv()}),
          'GET conversations/01CONV/messages': (_) => jsonBody(200, {
            'data': <Object>[],
            'meta': {'last_id': 0},
          }),
          'POST conversations/01CONV/read': (_) => jsonBody(200, {'data': {}}),
          'POST conversations/01CONV/messages': (o) {
            if (!online) throw offlineError(o);
            final d = o.data as Map;
            return jsonBody(201, {
              'data': msg(50, '${d['body']}', '${d['client_uuid']}'),
              'meta': {'duplicate': false},
            });
          },
        });
        final env = member(api, online: false);
        await boot(tester, env, '/messages/01CONV');
        expect(
          find.text(S.t('community_messages_offline_notice')),
          findsOneWidget,
        );
        await tester.enterText(find.byType(TextField), 'Message du train');
        await tester.tap(find.byTooltip(S.t('community_messages_send')));
        await settle(tester, 8);
        expect(find.text('Message du train'), findsOneWidget);
        expect(find.text(S.t('community_messages_pending')), findsOneWidget);
        final uuid = env.db.outbox.keys.single;

        online = true;
        unawaited(containerOf(tester).read(outboxProvider).flush());
        await settle(tester, 8);
        expect(find.text(S.t('community_messages_pending')), findsNothing);
        expect(
          find.text('Message du train'),
          findsOneWidget,
          reason: 'une seule bulle, jamais de doublon',
        );
        final calls = api.calls('POST conversations/01CONV/messages');
        expect(calls, hasLength(2));
        expect(calls.map((c) => c.headers['Idempotency-Key']).toSet(), {uuid});
        expect(calls.map((c) => (c.data as Map)['client_uuid']).toSet(), {
          uuid,
        });
        expect(env.db.outbox.keys, isEmpty);
        await disposeApp(tester);
      },
    );
  });

  group('groupes et événements', () {
    testWidgets('groupes : découvrir, rejoindre un groupe public', (
      tester,
    ) async {
      var joined = false;
      Map<String, dynamic> group() => {
        'id': '01GRP',
        'name': 'Cercle de Cotonou',
        'description': 'Entraide',
        'privacy': 'public',
        'members_count': 12,
        'membership': joined ? {'status': 'actif', 'role': 'membre'} : null,
        'can': {
          'join': !joined,
          'leave': joined,
          'discuss': joined,
          'cancel_request': false,
        },
      };
      final api = apiWith({
        'GET groups': (_) => jsonBody(200, {
          'data': [group()],
          'meta': {'tab': 'discover'},
        }),
        'GET groups/01GRP': (_) => jsonBody(200, {'data': group()}),
        'POST groups/01GRP/join': (_) {
          joined = true;
          return jsonBody(200, {'data': group()});
        },
        'GET groups/01GRP/posts': (_) =>
            jsonBody(200, {'data': <Object>[], 'meta': {}}),
      });
      await boot(tester, member(api), '/groupes');
      await tester.tap(find.text('Cercle de Cotonou'));
      await settle(tester, 10);
      await tester.tap(find.text(S.t('community_groups_actions_join_group')));
      await settle(tester, 8);
      expect(api.calls('POST groups/01GRP/join'), hasLength(1));
      expect(find.text(S.t('community_groups_actions_leave')), findsOneWidget);
      await disposeApp(tester);
    });

    testWidgets(
      'événement : adresse exacte réservée, livrée après « Je participe » + calendrier',
      (tester) async {
        var going = false;
        Map<String, dynamic> event() => {
          'id': '01EVT',
          'title': 'Atelier « Dire non »',
          'starts_at': '2026-11-07T15:00:00+01:00',
          'ends_at': '2026-11-07T17:00:00+01:00',
          'date_label': 'samedi 7 novembre 2026',
          'time_label': '15 h',
          'place': 'Cotonou',
          'location_label': 'Cotonou',
          'is_past': false,
          'going_count': going ? 9 : 8,
          'rsvp': going ? {'status': 'going', 'reminder': true} : null,
          if (going) 'address': 'Rue 12, quartier Ganhi',
          if (!going) 'exact_place_reserved': true,
        };
        final api = apiWith({
          'GET events': (_) => jsonBody(200, {
            'data': [event()],
            'meta': {'filter': 'upcoming'},
          }),
          'GET events/01EVT': (_) => jsonBody(200, {'data': event()}),
          'POST events/01EVT/rsvp': (_) {
            going = true;
            return jsonBody(200, {'data': event()});
          },
        });
        final cal = FakeCalendar();
        await boot(
          tester,
          member(api, extra: [calendarServiceProvider.overrideWithValue(cal)]),
          '/evenements',
        );
        await tester.tap(find.byKey(const Key('event-01EVT')));
        await settle(tester, 10);
        expect(find.byKey(const Key('event-address-hidden')), findsOneWidget);
        expect(find.text('Rue 12, quartier Ganhi'), findsNothing);

        await tester.tap(find.text(S.t('community_events_going')));
        await settle(tester, 10);
        expect(api.calls('POST events/01EVT/rsvp').single.data, {
          'going': true,
          'reminder': true,
        });
        expect(find.text('Rue 12, quartier Ganhi'), findsOneWidget);
        expect(find.byKey(const Key('event-address-hidden')), findsNothing);
        expect(cal.added.single, {
          'title': 'Atelier « Dire non »',
          'location': 'Rue 12, quartier Ganhi',
        });
        await disposeApp(tester);
      },
    );

    testWidgets(
      'détail d’un événement : une erreur serveur ne plante pas l’écran',
      (tester) async {
        final api = apiWith({
          'GET events': (_) => jsonBody(200, {
            'data': <Map<String, dynamic>>[],
            'meta': {'filter': 'upcoming'},
          }),
          'GET events/01EVT': (_) => jsonBody(500, {'message': 'Erreur'}),
        });
        await boot(tester, member(api), '/evenements/01EVT');
        expect(tester.takeException(), isNull);
        expect(find.text(S.t('community_events_title')), findsWidgets);
        await disposeApp(tester);
      },
    );
  });

  group('assistant', () {
    testWidgets(
      'crise : réponse du serveur, numéros et SOS en un geste, rien n’est enregistré',
      (tester) async {
        final launcher = FakeLauncher();
        final api = apiWith({
          'POST assistant/question': (_) => jsonBody(200, {
            'data': {
              'text': 'Tu n’es pas seule. Appelle le 117.',
              'kind': 'crisis',
              'emergency': true,
              'suggestions': <Object>[],
              'links': [
                {'label': 'SOS', 'url': '/sos', 'variant': 'danger'},
                {
                  'label': 'Appeler le 117',
                  'url': 'tel:117',
                  'variant': 'primary-soft',
                },
              ],
            },
          }),
        });
        await boot(
          tester,
          member(
            api,
            extra: [linkLauncherProvider.overrideWithValue(launcher)],
          ),
          '/assistant',
        );
        await tester.enterText(find.byType(TextField), 'je veux mourir');
        await tester.tap(find.byTooltip(S.t('assistant_page_send')));
        await settle(tester, 8);
        expect(find.text('Tu n’es pas seule. Appelle le 117.'), findsOneWidget);
        final body = api.calls('POST assistant/question').single.data as Map;
        expect(body, {
          'question': 'je veux mourir',
        }, reason: 'ni persist ni conversation_id');
        await tester.tap(find.text('Appeler le 117'));
        expect(launcher.opened.single.toString(), 'tel:117');
        await tester.tap(find.text('SOS'));
        await settle(tester, 8);
        expect(find.text(S.t('components_sos_label')), findsWidgets);
        await disposeApp(tester);
      },
    );

    testWidgets(
      'hors ligne : aucun appel à l’API, numéro du cache et lien vers les urgences',
      (tester) async {
        final api = apiWith({
          'GET urgences': (_) => jsonBody(200, {
            'data': {
              'services': [
                {'label': 'Police secours', 'number': '117', 'tel': 'tel:117'},
              ],
              'contacts': <Object>[],
            },
          }),
        });
        await boot(tester, member(api, online: false), '/assistant');
        await tester.enterText(
          find.byType(TextField),
          'Comment porter plainte ?',
        );
        await tester.tap(find.byTooltip(S.t('assistant_page_send')));
        await settle(tester, 8);
        expect(api.calls('POST assistant/question'), isEmpty);
        expect(
          find.text(S.t('components_assistant_offline', {'number': '117'})),
          findsOneWidget,
        );
        expect(
          find.text(S.t('components_assistant_offline_link')),
          findsOneWidget,
        );
        await disposeApp(tester);
      },
    );
  });
}
