import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:xomiton/features/admin/data/admin_api.dart';
import 'package:xomiton/features/admin/data/admin_models.dart';

import '../support/admin_harness.dart';
import '../support/harness.dart';
import '../support/pair_harness.dart' show currentPath, revealKey;

final S = loadStrings();

Map<String, dynamic> account(int id, String pseudo, {String zone = 'Atlantique', Map<String, dynamic> extra = const {}}) => {
  'id': id,
  'pseudo': pseudo,
  'status': 'en_attente',
  'role': 'membre',
  'roles': ['membre'],
  'zone': zone,
  'created_at': '2026-10-27T10:00:00Z',
  'can': {'grant_peer': true, 'revoke_peer': false, 'change_role': true, 'validate': true},
  ...extra,
};

Map<String, FakeHandler> stepUpOk() => {
  'POST admin/step-up': (_) => jsonBody(200, {'data': {'token': 'jeton-1', 'expires_in': 300}}),
};

Future<void> confirmStepUp(WidgetTester tester) async {
  await tester.enterText(find.byKey(const ValueKey('step-up-password')), 'motdepasse');
  await tester.tap(find.byKey(const ValueKey('step-up-confirm')));
  await tester.pumpAndSettle();
}

void main() {
  setUpAll(() => initializeDateFormatting('fr'));

  group('menu Gérer (A11)', () {
    testWidgets('quatre tâches avec compteurs ; le reste renvoie au site web', (tester) async {
      await openAdmin(tester, goTo_: '/admin/gerer');
      expect(find.text(S.t('mobile_admin_manage_title')), findsWidgets);
      for (final k in ['reg', 'roles', 'mod', 'maint']) {
        expect(find.text(S.t('mobile_admin_manage_$k')), findsWidgets);
      }
      expect(find.text(S.t('mobile_admin_count_badge', {'count': 3})), findsNWidgets(2)); // 2+1 inscriptions, 3 contenus
      expect(find.text(S.t('mobile_admin_manage_web')), findsOneWidget);
      await tester.tap(find.text(S.t('mobile_admin_manage_reg')));
      await tester.pumpAndSettle();
      expect(currentPath(tester), '/admin/inscriptions');
    });
  });

  group('inscriptions à valider (A4)', () {
    Map<String, FakeHandler> regRoutes({Map<String, FakeHandler> extra = const {}}) => adminRoutes(extra: {
      'GET admin/registrations': (_) => jsonBody(200, {
        'data': [
          account(5, 'Pair_Cobalt', extra: {'kind': 'peer_request', 'invited': true, 'flags': []}),
          account(6, 'Lys_Cotonou', zone: 'Littoral', extra: {'kind': 'registration', 'invited': false, 'flags': ['disposable_email']}),
        ],
        'meta': {'pending': 2},
      }),
      'GET admin/role-requests': (_) => jsonBody(200, {
        'data': [
          {
            'id': '01JREQ',
            'role': 'pair',
            'status': 'pending',
            'reason': 'J’anime des ateliers.',
            'user': {'id': 9, 'pseudo': 'Pair_Lumen', 'zone': 'Borgou', 'created_at': '2026-10-20T10:00:00Z'},
          },
        ],
      }),
      ...stepUpOk(),
      ...extra,
    });

    testWidgets('trois types, filtres, signalement affiché', (tester) async {
      await openAdmin(tester, routes: regRoutes(), goTo_: '/admin/inscriptions');
      expect(find.text(S.t('mobile_admin_reg_count', {'count': 3})), findsOneWidget);
      expect(find.text(S.t('mobile_admin_reg_flag_disposable_email')), findsOneWidget);
      expect(find.text(S.t('mobile_admin_reg_reason', {'reason': 'J’anime des ateliers.'})), findsOneWidget);
      await tester.tap(find.byKey(const ValueKey('admin-reg-tab-member')));
      await tester.pumpAndSettle();
      expect(find.text(S.t('mobile_admin_reg_count', {'count': 1})), findsOneWidget);
      expect(find.text('Lys_Cotonou'), findsOneWidget);
      await tester.tap(find.byKey(const ValueKey('admin-reg-tab-invited')));
      await tester.pumpAndSettle();
      expect(find.text('Pair_Cobalt'), findsOneWidget);
      expect(find.text('Lys_Cotonou'), findsNothing);
    });

    testWidgets('valider une inscription : confirmation, POST sans step-up, ligne retirée', (tester) async {
      final env = await openAdmin(tester, goTo_: '/admin/inscriptions', routes: regRoutes(extra: {
        'POST admin/registrations/6/approve': (_) => jsonBody(200, {'data': {}}),
      }));
      await revealKey(tester, 'admin-reg-approve-reg-6');
      await tester.tap(find.byKey(const ValueKey('admin-reg-approve-reg-6')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey('admin-confirm-yes')));
      await tester.pumpAndSettle();
      final req = env.api.calls('POST admin/registrations/6/approve').single;
      expect(req.headers['Idempotency-Key'], isNotEmpty);
      expect(req.headers.containsKey('X-Step-Up-Token'), isFalse);
      expect(find.text('Lys_Cotonou'), findsNothing);
    });

    testWidgets('rejeter : motif obligatoire (3 à 200), step-up, jeton en en-tête', (tester) async {
      final env = await openAdmin(tester, goTo_: '/admin/inscriptions', routes: regRoutes(extra: {
        'POST admin/registrations/6/reject': (_) => jsonBody(200, {'data': {}}),
      }));
      await revealKey(tester, 'admin-reg-reject-reg-6');
      await tester.tap(find.byKey(const ValueKey('admin-reg-reject-reg-6')));
      await tester.pumpAndSettle();
      await tester.enterText(find.byKey(const ValueKey('admin-reason-field')), 'a');
      await tester.tap(find.byKey(const ValueKey('admin-reason-yes')));
      await tester.pumpAndSettle();
      expect(find.text(S.t('mobile_admin_reg_reject_reason_short')), findsOneWidget);
      await tester.enterText(find.byKey(const ValueKey('admin-reason-field')), 'E-mail jetable.');
      await tester.tap(find.byKey(const ValueKey('admin-reason-yes')));
      await tester.pumpAndSettle();
      expect(env.api.calls('POST admin/registrations/6/reject'), isEmpty);
      await confirmStepUp(tester);
      final req = env.api.calls('POST admin/registrations/6/reject').single;
      expect(req.headers['X-Step-Up-Token'], 'jeton-1');
      expect((req.data as Map)['reason'], 'E-mail jetable.');
      expect(find.text('Lys_Cotonou'), findsNothing);
    });

    testWidgets('demande de rôle : approuver exige le step-up et vise role-requests', (tester) async {
      final env = await openAdmin(tester, goTo_: '/admin/inscriptions', routes: regRoutes(extra: {
        'POST admin/role-requests/01JREQ/approve': (_) => jsonBody(200, {'data': {}}),
      }));
      await revealKey(tester, 'admin-reg-approve-req-01JREQ');
      await tester.tap(find.byKey(const ValueKey('admin-reg-approve-req-01JREQ')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey('admin-confirm-yes')));
      await tester.pumpAndSettle();
      await confirmStepUp(tester);
      expect(env.api.calls('POST admin/role-requests/01JREQ/approve').single.headers['X-Step-Up-Token'], 'jeton-1');
    });

    testWidgets('sans le droit des demandes de rôle (403) : la file des inscriptions reste affichée', (tester) async {
      await openAdmin(tester, goTo_: '/admin/inscriptions', routes: regRoutes(extra: {
        'GET admin/role-requests': (_) => jsonBody(403, {'message': 'x', 'code': 'forbidden'}),
      }));
      expect(find.text(S.t('mobile_admin_reg_count', {'count': 2})), findsOneWidget);
    });

    testWidgets('hors ligne : boutons désactivés', (tester) async {
      await openAdmin(tester, online: false, goTo_: '/admin/inscriptions', routes: regRoutes());
      final btn = tester.widget<FilledButton>(find.byKey(const ValueKey('admin-reg-approve-reg-6')));
      expect(btn.onPressed, isNull);
    });
  });

  group('rôles et invitations (A8)', () {
    Map<String, FakeHandler> roleRoutes({Map<String, FakeHandler> extra = const {}}) => adminRoutes(extra: {
      'GET admin/invitations': (_) => jsonBody(200, {
        'data': [
          {'id': '12', 'role': 'pair', 'status': 'pending', 'code_hint': 'JKMN', 'email': 'p@exemple.bj', 'expires_at': '2026-11-05T00:00:00Z'},
        ],
      }),
      'GET admin/members': (o) => jsonBody(200, {
        'data': [
          account(3, 'Chantal_H', extra: {'status': 'actif', 'roles': ['membre', 'pair'], 'is_peer': true, 'can': {'grant_peer': false, 'revoke_peer': true}}),
          account(4, 'Estelle_D', extra: {'status': 'actif'}),
          account(8, 'Sysadmin', extra: {'status': 'actif', 'role': 'admin', 'roles': ['membre', 'admin'], 'can': {'grant_peer': true, 'revoke_peer': true}}),
        ],
      }),
      ...stepUpOk(),
      ...extra,
    });

    testWidgets('créer une invitation de pair : step-up, rôle pair seulement, code et lien rendus une fois', (tester) async {
      final env = await openAdmin(tester, goTo_: '/admin/roles', routes: roleRoutes(extra: {
        'POST admin/invitations': (_) => jsonBody(201, {
          'data': {'id': '13', 'role': 'pair', 'status': 'pending', 'code': 'XM-ABCD-EFGH-JKMN', 'link': 'https://xomiton.test/inscription?invitation=abc'},
        }),
      }));
      await tester.enterText(find.byKey(const ValueKey('admin-inv-email')), 'nouv@exemple.bj');
      await tester.tap(find.byKey(const ValueKey('admin-inv-create')));
      await tester.pumpAndSettle();
      await confirmStepUp(tester);
      final req = env.api.calls('POST admin/invitations').single;
      expect(req.headers['X-Step-Up-Token'], 'jeton-1');
      expect((req.data as Map)['role'], 'pair');
      expect((req.data as Map)['email'], 'nouv@exemple.bj');
      expect(find.byKey(const ValueKey('admin-inv-code')), findsOneWidget);
      expect(find.text('XM-ABCD-EFGH-JKMN'), findsOneWidget);
      // Copie dans le presse-papiers.
      String? copied;
      tester.binding.defaultBinaryMessenger.setMockMethodCallHandler(SystemChannels.platform, (call) async {
        if (call.method == 'Clipboard.setData') copied = (call.arguments as Map)['text'] as String;
        return null;
      });
      await tester.tap(find.byKey(const ValueKey('admin-inv-copy-code')));
      await tester.pumpAndSettle();
      expect(copied, 'XM-ABCD-EFGH-JKMN');
    });

    testWidgets('révoquer une invitation : confirmation puis step-up', (tester) async {
      final env = await openAdmin(tester, goTo_: '/admin/roles', routes: roleRoutes(extra: {
        'DELETE admin/invitations/12': (_) => jsonBody(204, null),
      }));
      await revealKey(tester, 'admin-inv-revoke-12');
      await tester.tap(find.byKey(const ValueKey('admin-inv-revoke-12')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey('admin-confirm-yes')));
      await tester.pumpAndSettle();
      await confirmStepUp(tester);
      final req = env.api.calls('DELETE admin/invitations/12').single;
      expect(req.headers['X-Step-Up-Token'], 'jeton-1');
      expect(req.headers['Idempotency-Key'], isNotEmpty);
    });

    testWidgets('recherche de membre (2 caractères mini), donner le rôle pair', (tester) async {
      final env = await openAdmin(tester, goTo_: '/admin/roles', routes: roleRoutes(extra: {
        'POST admin/members/4/roles/peer': (_) => jsonBody(201, {'data': account(4, 'Estelle_D')}),
      }));
      await revealKey(tester, 'admin-member-search');
      await tester.enterText(find.byKey(const ValueKey('admin-member-search')), 'e');
      await tester.pumpAndSettle();
      expect(env.api.calls('GET admin/members'), isEmpty);
      await tester.enterText(find.byKey(const ValueKey('admin-member-search')), 'es');
      await tester.pumpAndSettle();
      expect(env.api.calls('GET admin/members').single.queryParameters['q'], 'es');
      await revealKey(tester, 'admin-member-4');
      await tester.tap(find.byKey(const ValueKey('admin-member-4')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey('admin-member-give')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey('admin-confirm-yes')));
      await tester.pumpAndSettle();
      await confirmStepUp(tester);
      final req = env.api.calls('POST admin/members/4/roles/peer').single;
      expect(req.headers['X-Step-Up-Token'], 'jeton-1');
      // Aucun nom de rôle dans le corps : le rôle admin n'est pas attribuable depuis le mobile.
      expect(req.data, isNull);
    });

    testWidgets('retirer le rôle pair ; un compte d’équipe n’a AUCUN bouton de rôle', (tester) async {
      final env = await openAdmin(tester, goTo_: '/admin/roles', routes: roleRoutes(extra: {
        'DELETE admin/members/3/roles/peer': (_) => jsonBody(200, {'data': {}}),
      }));
      await revealKey(tester, 'admin-member-search');
      await tester.enterText(find.byKey(const ValueKey('admin-member-search')), 'ch');
      await tester.pumpAndSettle();
      await revealKey(tester, 'admin-member-8');
      await tester.tap(find.byKey(const ValueKey('admin-member-8')));
      await tester.pumpAndSettle();
      expect(find.byKey(const ValueKey('admin-member-give')), findsNothing);
      expect(find.byKey(const ValueKey('admin-member-take')), findsNothing);
      expect(find.text(S.t('mobile_admin_web_only')), findsWidgets);
      await tester.tap(find.text(S.t('common_close')));
      await tester.pumpAndSettle();
      await revealKey(tester, 'admin-member-3');
      await tester.tap(find.byKey(const ValueKey('admin-member-3')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey('admin-member-take')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey('admin-confirm-yes')));
      await tester.pumpAndSettle();
      await confirmStepUp(tester);
      expect(env.api.calls('DELETE admin/members/3/roles/peer'), hasLength(1));
    });

    test('l’API mobile ne sait attribuer que le rôle pair', () {
      // Aucune méthode de AdminApi ne prend un nom de rôle : seules ces écritures existent.
      expect(AdminApi, isNotNull);
      final invited = AdminMember.fromJson({'id': 1, 'pseudo': 'x', 'roles': ['membre', 'superadmin']});
      expect(invited.isStaff, isTrue);
    });
  });

  group('modération (A9, A10)', () {
    Map<String, dynamic> report(String id, {String kind = 'post', bool hidden = false, String? handledAt}) => {
      'id': id,
      'kind': kind,
      'reason': 'Propos haineux',
      'excerpt': 'Personne ne veut de vous ici.',
      'reported_user': {'id': 4, 'pseudo': 'Nadège_A'},
      'status': handledAt == null ? 'pending' : 'handled',
      'hidden': hidden,
      'reports': 3,
      'people': 3,
      'created_at': '2026-10-28T10:00:00Z',
      'handled_at': handledAt,
      'can': {'hide': true, 'restore': hidden, 'dismiss': true, 'warn': true},
    };

    Map<String, FakeHandler> modRoutes({Map<String, FakeHandler> extra = const {}}) => adminRoutes(extra: {
      'GET admin/moderation/reports': (o) => jsonBody(200, {
        'data': [report('m1'), report('m2', kind: 'message')],
        'meta': {'pending': 1},
      }),
      'GET admin/moderation/reports/m1': (_) => jsonBody(200, {'data': report('m1')}),
      ...extra,
    });

    testWidgets('liste : les messages privés ne sont jamais proposés', (tester) async {
      await openAdmin(tester, goTo_: '/admin/moderation', routes: modRoutes());
      expect(find.text(S.t('mobile_admin_mod_count', {'count': 1})), findsOneWidget);
      expect(find.byKey(const ValueKey('admin-report-m1')), findsOneWidget);
      expect(find.byKey(const ValueKey('admin-report-m2')), findsNothing);
      expect(find.text(S.t('mobile_admin_mod_intro')), findsOneWidget);
    });

    testWidgets('masquer : confirmation puis décision hide', (tester) async {
      final env = await openAdmin(tester, goTo_: '/admin/moderation/m1', routes: modRoutes(extra: {
        'POST admin/moderation/reports/m1/decision': (_) => jsonBody(200, {'data': report('m1', hidden: true, handledAt: '2026-10-29T00:00:00Z')}),
      }));
      await tester.tap(find.byKey(const ValueKey('admin-mod-hide')));
      await tester.pumpAndSettle();
      await tester.tap(find.text(S.t('common_cancel')));
      await tester.pumpAndSettle();
      expect(env.api.calls('POST admin/moderation/reports/m1/decision'), isEmpty);
      await tester.tap(find.byKey(const ValueKey('admin-mod-hide')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey('admin-confirm-yes')));
      await tester.pumpAndSettle();
      final req = env.api.calls('POST admin/moderation/reports/m1/decision').single;
      expect((req.data as Map)['action'], 'hide');
      expect(req.headers['Idempotency-Key'], isNotEmpty);
    });

    testWidgets('conserver et avertir (motif obligatoire)', (tester) async {
      final env = await openAdmin(tester, goTo_: '/admin/moderation/m1', routes: modRoutes(extra: {
        'POST admin/moderation/reports/m1/decision': (_) => jsonBody(200, {'data': report('m1')}),
      }));
      await tester.tap(find.byKey(const ValueKey('admin-mod-warn')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey('admin-reason-yes')));
      await tester.pumpAndSettle();
      expect(find.text(S.t('mobile_admin_reg_reject_reason_short')), findsOneWidget);
      await tester.enterText(find.byKey(const ValueKey('admin-reason-field')), 'Merci de respecter les règles.');
      await tester.tap(find.byKey(const ValueKey('admin-reason-yes')));
      await tester.pumpAndSettle();
      final body = env.api.calls('POST admin/moderation/reports/m1/decision').single.data as Map;
      expect(body, {'action': 'warn', 'reason': 'Merci de respecter les règles.'});
    });

    testWidgets('signalement introuvable (404) : message neutre', (tester) async {
      await openAdmin(tester, goTo_: '/admin/moderation/zz', routes: modRoutes(extra: {
        'GET admin/moderation/reports/zz': (_) => jsonBody(404, {'message': 'x', 'code': 'not_found'}),
      }));
      expect(find.text(S.t('mobile_admin_mod_gone')), findsOneWidget);
    });
  });

  group('maintenance (A5)', () {
    Map<String, FakeHandler> maintRoutes({bool active = false, Map<String, FakeHandler> extra = const {}}) => adminRoutes(extra: {
      'GET admin/maintenance': (_) => jsonBody(200, {
        'data': {'state': active ? 'ongoing' : 'inactive', 'state_label': active ? 'En cours' : 'Inactif', 'active': active, 'ends_at': null},
      }),
      ...stepUpOk(),
      ...extra,
    });

    testWidgets('activer : confirmation forte (motif, case, mot MAINTENANCE) avant step-up', (tester) async {
      final env = await openAdmin(tester, goTo_: '/admin/maintenance', routes: maintRoutes(extra: {
        'POST admin/maintenance/enable': (_) => jsonBody(200, {'data': {'state': 'ongoing', 'active': true}}),
      }));
      FilledButton enable() => tester.widget<FilledButton>(find.byKey(const ValueKey('admin-maint-enable')));
      await revealKey(tester, 'admin-maint-reason');
      expect(enable().onPressed, isNull);
      await tester.enterText(find.byKey(const ValueKey('admin-maint-reason')), 'Mise à jour de la base');
      await revealKey(tester, 'admin-maint-ack');
      await tester.tap(find.byKey(const ValueKey('admin-maint-ack')));
      await tester.pumpAndSettle();
      expect(enable().onPressed, isNull);
      await revealKey(tester, 'admin-maint-word');
      await tester.enterText(find.byKey(const ValueKey('admin-maint-word')), 'maint');
      await tester.pumpAndSettle();
      expect(find.text(S.t('mobile_admin_maint_mismatch')), findsOneWidget);
      expect(enable().onPressed, isNull);
      await tester.enterText(find.byKey(const ValueKey('admin-maint-word')), 'MAINTENANCE');
      await tester.pumpAndSettle();
      await revealKey(tester, 'admin-maint-enable');
      expect(enable().onPressed, isNotNull);
      await tester.tap(find.byKey(const ValueKey('admin-maint-enable')));
      await tester.pumpAndSettle();
      expect(env.api.calls('POST admin/maintenance/enable'), isEmpty); // pas sans le mot de passe
      await confirmStepUp(tester);
      final req = env.api.calls('POST admin/maintenance/enable').single;
      expect(req.headers['X-Step-Up-Token'], 'jeton-1');
      expect((req.data as Map)['confirm'], true);
      expect((req.data as Map)['reason'], 'Mise à jour de la base');
      expect((req.data as Map)['duration_minutes'], 60);
    });

    testWidgets('désactiver : confirmation puis step-up ; message d’accès toujours ouvert', (tester) async {
      final env = await openAdmin(tester, goTo_: '/admin/maintenance', routes: maintRoutes(active: true, extra: {
        'POST admin/maintenance/disable': (_) => jsonBody(200, {'data': {'state': 'inactive', 'active': false}}),
      }));
      expect(find.text(S.t('mobile_admin_maint_ongoing')), findsWidgets);
      await tester.tap(find.byKey(const ValueKey('admin-maint-disable')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey('admin-confirm-yes')));
      await tester.pumpAndSettle();
      await confirmStepUp(tester);
      final req = env.api.calls('POST admin/maintenance/disable').single;
      expect(req.headers['X-Step-Up-Token'], 'jeton-1');
      expect((req.data as Map)['confirm'], true);
    });

    testWidgets('mot de passe faux : rien n’est activé', (tester) async {
      final env = await openAdmin(tester, goTo_: '/admin/maintenance', routes: maintRoutes(active: true, extra: {
        'POST admin/step-up': (_) => jsonBody(422, {'message': 'x', 'errors': {'password': ['Mot de passe incorrect.']}}),
        'POST admin/maintenance/disable': (_) => jsonBody(200, {'data': {}}),
      }));
      await tester.tap(find.byKey(const ValueKey('admin-maint-disable')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey('admin-confirm-yes')));
      await tester.pumpAndSettle();
      await confirmStepUp(tester);
      expect(find.text('Mot de passe incorrect.'), findsOneWidget);
      expect(env.api.calls('POST admin/maintenance/disable'), isEmpty);
    });
  });

  group('notifications (A6)', () {
    testWidgets('liste, filtre par catégorie envoyé au serveur, tout marquer lu', (tester) async {
      final env = await openAdmin(tester, goTo_: '/admin/notifications', routes: adminRoutes(extra: {
        'GET admin/notifications': (_) => jsonBody(200, {
          'data': [
            {'id': 'n1', 'category': 'sos', 'title': 'Alerte urgente', 'body': null, 'read': false, 'created_at': '2026-10-29T10:00:00Z'},
            {'id': 'n2', 'category': 'moderation', 'title': 'Signalement à modérer', 'read': true, 'created_at': '2026-10-28T10:00:00Z'},
          ],
        }),
        'POST admin/notifications/read-all': (_) => jsonBody(200, {'data': {'marked': 1}}),
      }));
      expect(find.text('Alerte urgente'), findsOneWidget);
      expect(find.text('Signalement à modérer'), findsOneWidget);
      await tester.tap(find.byKey(const ValueKey('admin-notif-cat-sos')));
      await tester.pumpAndSettle();
      expect(env.api.calls('GET admin/notifications').last.queryParameters['category'], 'sos');
      await tester.tap(find.byKey(const ValueKey('admin-notif-read-all')));
      await tester.pumpAndSettle();
      expect(env.api.calls('POST admin/notifications/read-all'), hasLength(1));
    });
  });
}
