import 'package:flutter_test/flutter_test.dart';
import 'package:xomiton/app/router.dart';
import 'package:xomiton/core/auth_spaces/app_space.dart';
import 'package:xomiton/core/network/interceptors.dart';
import 'package:xomiton/features/auth/data/app_user.dart';

import '../support/harness.dart';

void main() {
  group('AppUser : rôles cumulés et espaces lus de GET me', () {
    test('membre simple : un seul espace', () {
      final u = AppUser.fromJson(userJson(roles: ['membre'], spaces: ['member']));
      expect(u.spaces, [AppSpace.member]);
      expect(u.hasSeveralSpaces, isFalse);
      expect(u.isPeer, isFalse);
    });

    test('pair validée : membre ET pair', () {
      final u = AppUser.fromJson(userJson(role: 'pair', roles: ['membre', 'pair'], spaces: ['member', 'peer'], permissions: ['peer_space.access']));
      expect(u.spaces, [AppSpace.member, AppSpace.peer]);
      expect(u.isPeer, isTrue);
      expect(u.hasSpace(AppSpace.admin), isFalse);
      expect(u.can('peer_space.access'), isTrue);
      expect(u.can('admin_mobile.access'), isFalse);
    });

    test('jamais de confiance côté client : le rôle seul ne donne aucun espace', () {
      // `role: admin` sans `spaces` (copie ancienne, réponse tronquée) : espace Membre uniquement.
      final u = AppUser.fromJson(userJson(role: 'admin'));
      expect(u.spaces, [AppSpace.member]);
      expect(u.roles, containsAll(['membre', 'admin']));
      expect(u.hasSpace(AppSpace.admin), isFalse);
    });

    test('pair en attente : le serveur ne lui déclare que Membre', () {
      final u = AppUser.fromJson(userJson(role: 'pair', roles: ['membre', 'pair'], spaces: ['member'], pending: true));
      expect(u.spaces, [AppSpace.member]);
      expect(u.isPeer, isTrue);
    });

    test('valeurs inconnues ignorées, Membre toujours présent, ordre stable, sans doublon', () {
      final u = AppUser.fromJson(userJson(spaces: ['admin', 'peer', 'superuser', 'admin', 'member']));
      expect(u.spaces, [AppSpace.member, AppSpace.peer, AppSpace.admin]);
      final noMember = AppUser.fromJson(userJson(spaces: ['admin']));
      expect(noMember.spaces, [AppSpace.member, AppSpace.admin]);
    });

    test('copie locale : roles, spaces et permissions survivent à la sérialisation', () {
      final u = AppUser.fromJson(userJson(roles: ['membre', 'admin'], spaces: ['member', 'admin'], permissions: ['admin_mobile.access']));
      final back = AppUser.fromJson(u.toJson());
      expect(back.spaces, u.spaces);
      expect(back.roles, u.roles);
      expect(back.permissions, u.permissions);
    });
  });

  group('AppSpace', () {
    test('espace propriétaire d’un chemin', () {
      expect(AppSpace.forPath('/pair/alertes'), AppSpace.peer);
      expect(AppSpace.forPath('/pair'), AppSpace.peer);
      expect(AppSpace.forPath('/admin/astreinte'), AppSpace.admin);
      expect(AppSpace.forPath('/home'), isNull);
      expect(AppSpace.forPath('/pairs-de-confiance'), isNull, reason: 'seul le préfixe exact /pair/ compte');
      expect(AppSpace.forPath('/administration'), isNull);
    });

    test('arrivées de chaque espace et noms d’API', () {
      expect(AppSpace.member.homeRoute, '/home');
      expect(AppSpace.peer.homeRoute, '/pair/accueil');
      expect(AppSpace.admin.homeRoute, '/admin/astreinte');
      expect([for (final s in AppSpace.values) s.apiName], ['member', 'peer', 'admin']);
      expect(AppSpace.fromApi('peer'), AppSpace.peer);
      expect(AppSpace.fromApi('pair'), isNull);
    });
  });

  test('--dart-define=SPACES : variante de build (Membre toujours présent)', () {
    expect(parseBuildSpaces('member,pair,admin'), AppSpace.values.toSet());
    expect(parseBuildSpaces('member'), {AppSpace.member});
    expect(parseBuildSpaces('pair'), {AppSpace.member, AppSpace.peer});
    expect(parseBuildSpaces(' admin , inconnu '), {AppSpace.member, AppSpace.admin});
    expect(parseBuildSpaces(''), {AppSpace.member});
    expect(parseBuildSpaces(kBuildSpacesDefine), AppSpace.values.toSet(), reason: 'défaut : les trois espaces');
  });

  test('403 forbidden : seules les routes pair/* et admin/* relisent GET me', () {
    expect(isSpaceApiPath('pair/alerts'), isTrue);
    expect(isSpaceApiPath('/admin/duty'), isTrue);
    expect(isSpaceApiPath('/api/v1/admin/alerts/01J/acknowledge'), isTrue);
    expect(isSpaceApiPath('me/devices'), isFalse);
    expect(isSpaceApiPath('feed'), isFalse);
  });

  group('appRedirect : garde par espace (plan §5.3)', () {
    String? r(
      String path, {
      Set<AppSpace> spaces = const {AppSpace.member},
      AppSpace active = AppSpace.member,
      bool pending = false,
      bool auth = true,
    }) =>
        appRedirect(
          authenticated: auth,
          onboardingSeen: true,
          uri: Uri.parse(path),
          spaces: spaces,
          activeSpace: active,
          spaceChoicePending: pending,
        );
    const both = {AppSpace.member, AppSpace.peer};
    const all = {AppSpace.member, AppSpace.peer, AppSpace.admin};

    test('membre simple : /pair/** et /admin/** refusés, retour à l’accueil Membre', () {
      for (final p in ['/pair/accueil', '/pair/dossiers', '/admin/astreinte', '/admin/gerer', '/admin/alertes']) {
        expect(r(p), '/home', reason: p);
      }
      expect(r('/home'), isNull);
      expect(r('/feed'), isNull);
    });

    test('pair : /pair/** ouvert, /admin/** refusé', () {
      expect(r('/pair/alertes', spaces: both), isNull);
      expect(r('/admin/astreinte', spaces: both), '/home');
    });

    test('admin sans rôle pair : /admin/** ouvert, /pair/** refusé', () {
      const adm = {AppSpace.member, AppSpace.admin};
      expect(r('/admin/gerer', spaces: adm), isNull);
      expect(r('/pair/accueil', spaces: adm), '/home');
    });

    test('pair + admin : les deux ouverts', () {
      expect(r('/pair/accueil', spaces: all), isNull);
      expect(r('/admin/astreinte', spaces: all), isNull);
    });

    test('retrait d’un droit en cours de session : l’espace n’est plus atteignable', () {
      expect(r('/pair/accueil', spaces: both), isNull);
      expect(r('/pair/accueil', spaces: const {AppSpace.member}, active: AppSpace.peer), '/home');
    });

    test('première arrivée après connexion : sélecteur si plusieurs espaces, sinon accueil', () {
      expect(r('/login', spaces: both, pending: true), '/spaces');
      expect(r('/splash', spaces: all, pending: true), '/spaces');
      expect(r('/otp?purpose=login&email=a%40b.bj', spaces: both, pending: true), '/spaces');
      expect(r('/login', pending: true), '/home');
      expect(r('/spaces', spaces: both, pending: true), isNull);
      expect(r('/spaces'), '/home', reason: 'un seul espace : pas de sélecteur');
    });

    test('relancement (pas de choix en attente) : dernier espace actif, s’il est toujours déclaré', () {
      expect(r('/splash', spaces: both, active: AppSpace.peer), '/pair/accueil');
      expect(r('/login', spaces: all, active: AppSpace.admin), '/admin/astreinte');
      expect(r('/login', spaces: const {AppSpace.member}, active: AppSpace.admin), '/home');
    });

    test('SOS, urgences et signalement : jamais soumis à un espace ni à un choix', () {
      for (final p in ['/alerte-sos', '/signaler', '/urgences', '/suivi']) {
        expect(r(p, auth: false), isNull, reason: p);
      }
      expect(r('/urgences', spaces: both, pending: true), isNull);
      expect(r('/alerte-sos', spaces: both, pending: true), '/sos');
    });

    test('lien d’invitation : ouvert sans session, et la session créée à la phase 1 ne l’en sort pas', () {
      expect(r('/signup/XM-ABCD-EFGH-JKMN', auth: false), isNull);
      expect(r('/signup', auth: false), isNull);
      expect(r('/signup/XM-ABCD-EFGH-JKMN'), isNull);
    });
  });
}
