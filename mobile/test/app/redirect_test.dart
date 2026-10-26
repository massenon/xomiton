import 'package:flutter_test/flutter_test.dart';
import 'package:xomiton/app/router.dart';

String? r(String path, {bool auth = false, bool seen = false}) => appRedirect(
  authenticated: auth,
  onboardingSeen: seen,
  uri: Uri.parse(path),
);

void main() {
  group('sans session', () {
    test('onboarding pas vu : tout renvoie au carrousel', () {
      expect(r('/home'), '/splash');
      expect(r('/thematiques'), '/splash');
      expect(r('/splash'), isNull);
    });

    test('onboarding vu : connexion à la place du carrousel', () {
      expect(r('/splash', seen: true), '/login');
      expect(r('/home', seen: true), '/login');
      expect(r('/notifications', seen: true), '/login');
    });

    test('écrans d’authentification toujours ouverts', () {
      for (final p in [
        '/login',
        '/signup',
        '/forgot',
        '/reset',
        '/otp?purpose=login&email=a%40b.bj',
      ]) {
        expect(r(p, seen: true), isNull, reason: p);
        expect(r(p), isNull, reason: p);
      }
    });

    test('SOS, signalement, suivi et urgences : accessibles sans compte, même avant l’onboarding', () {
      for (final p in [
        '/alerte-sos',
        '/signaler',
        '/signaler?type=need',
        '/suivi',
        '/urgences',
      ]) {
        expect(r(p), isNull, reason: p);
        expect(r(p, seen: true), isNull, reason: p);
      }
      expect(
        r('/sos', seen: true),
        '/alerte-sos',
        reason: 'l’onglet SOS est réservé aux membres',
      );
      expect(r('/messages', seen: true), '/login');
      expect(r('/feed', seen: true), '/login');
    });
  });

  group('avec session', () {
    test('les écrans invités renvoient à l’accueil', () {
      for (final p in ['/splash', '/login', '/forgot', '/reset']) {
        expect(r(p, auth: true, seen: true), '/home', reason: p);
      }
    });

    test('l’inscription et l’OTP d’inscription restent ouverts (compte créé en phase 1)', () {
      expect(r('/signup', auth: true), isNull);
      expect(r('/otp?purpose=signup&email=a', auth: true), isNull);
      expect(r('/otp?purpose=login&email=a', auth: true), '/home');
    });

    test('l’écran SOS plein devient l’onglet SOS', () {
      expect(r('/alerte-sos', auth: true), '/sos');
      expect(r('/signaler', auth: true), isNull);
      expect(r('/sos', auth: true), isNull);
    });

    test('le reste est accessible', () {
      expect(r('/home', auth: true), isNull);
      expect(r('/thematiques/ma-fiche', auth: true), isNull);
    });
  });
}
