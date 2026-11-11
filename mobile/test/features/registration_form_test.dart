import 'package:flutter_test/flutter_test.dart';
import 'package:xomiton/features/auth/data/registration_form.dart';

RegistrationForm base() => RegistrationForm()
  ..firstName = ' Awa '
  ..lastName = 'Sossou'
  ..pseudo = 'Awa_S'
  ..email = 'awa@exemple.bj'
  ..age = '24'
  ..pronouns = 'elle'
  ..countryCode = 'BJ'
  ..password = 'Secret0Fort1'
  ..passwordConfirmation = 'Secret0Fort1'
  ..cgu = true
  ..privacy = true;

RegistrationForm member() => base()
  ..memberType = kMemberTypeMember
  ..genderIdentity = 'femme'
  ..orientation = 'lesbienne'
  ..sensitiveData = true;

RegistrationForm ally() => base()
  ..memberType = kMemberTypeAlly
  ..allyRole = 'enseignant';

void main() {
  group('étape 1', () {
    test('tout est obligatoire', () {
      final e = RegistrationForm().validateStep(1, minAge: 18);
      expect(e.keys, containsAll(['first_name', 'last_name', 'pseudo', 'email', 'age']));
      expect(e.values.every((i) => i.kind == RegIssue.required), isTrue);
    });

    test('pseudo : 3 caractères et format', () {
      final f = base()..pseudo = 'ab';
      expect(f.validateStep(1, minAge: 18)['pseudo'], const FormIssue(RegIssue.minLength, 3));
      f.pseudo = 'a b!';
      expect(f.validateStep(1, minAge: 18)['pseudo']!.kind, RegIssue.format);
      f.pseudo = 'Awa.S-1_x';
      expect(f.validateStep(1, minAge: 18).containsKey('pseudo'), isFalse);
    });

    test('e-mail invalide', () {
      expect((base()..email = 'awa@').validateStep(1, minAge: 18)['email']!.kind, RegIssue.email);
    });

    test('âge : entier, au moins min_age du serveur (valeur du serveur, 18 aujourd’hui, jamais en dur)', () {
      expect((base()..age = 'vingt').validateStep(1, minAge: 18)['age']!.kind, RegIssue.ageNotNumber);
      expect((base()..age = '24.5').validateStep(1, minAge: 18)['age']!.kind, RegIssue.ageNotNumber);
      expect((base()..age = '18').validateStep(1, minAge: 18).containsKey('age'), isFalse);
      expect((base()..age = '17').validateStep(1, minAge: 18)['age'], const FormIssue(RegIssue.ageTooLow, 18));
      expect((base()..age = '20').validateStep(1, minAge: 21)['age'], const FormIssue(RegIssue.ageTooLow, 21));
      expect((base()..age = '17').validateStep(1, minAge: 18)['age']!.param, 18);
      expect(base().validateStep(1, minAge: 18), isEmpty);
    });
  });

  group('étape 2', () {
    test('pronoms, pays et « vous êtes » obligatoires ; ville, téléphone, zone facultatifs', () {
      final e = RegistrationForm().validateStep(2, minAge: 18);
      expect(e.keys.toSet(), {'pronouns', 'country_code', 'member_type'});
      expect(member().validateStep(2, minAge: 18), isEmpty);
    });
  });

  group('étape 3', () {
    test('membre : orientation, identité de genre et consentement sensibles obligatoires', () {
      final f = base()..memberType = kMemberTypeMember;
      expect(f.validateStep(3, minAge: 18).keys, containsAll(['orientation', 'gender_identity', 'sensitive_data']));
      expect(f.validateStep(3, minAge: 18).containsKey('ally_role'), isFalse);
      expect(member().validateStep(3, minAge: 18), isEmpty);
    });

    test('membre : « autre » impose la précision', () {
      final f = member()
        ..orientation = kOtherValue
        ..genderIdentity = kOtherValue;
      expect(f.validateStep(3, minAge: 18).keys.toSet(), {'orientation_other', 'gender_identity_other'});
      f
        ..orientationOther = 'Pansexuelle'
        ..genderIdentityOther = '  ';
      expect(f.validateStep(3, minAge: 18).keys.toSet(), {'gender_identity_other'});
    });

    test('allié·e : statut obligatoire, « autre » impose la précision, aucune donnée sensible demandée', () {
      final f = base()..memberType = kMemberTypeAlly;
      expect(f.validateStep(3, minAge: 18).keys, contains('ally_role'));
      expect(f.validateStep(3, minAge: 18).containsKey('sensitive_data'), isFalse);
      f.allyRole = kOtherValue;
      expect(f.validateStep(3, minAge: 18).keys, contains('ally_role_other'));
      expect(ally().validateStep(3, minAge: 18), isEmpty);
    });

    test('mot de passe : politique, confirmation identique, deux cases obligatoires', () {
      final f = member()
        ..password = 'court'
        ..passwordConfirmation = 'autre'
        ..cgu = false
        ..privacy = false;
      final e = f.validateStep(3, minAge: 18);
      expect(e['password']!.kind, RegIssue.password);
      expect(e['password_confirmation']!.kind, RegIssue.mismatch);
      expect(e['cgu']!.kind, RegIssue.consent);
      expect(e['privacy']!.kind, RegIssue.consent);
    });
  });

  group('charge utile (contrat du 11/11/2026)', () {
    test('membre : champs du profil membre, rien de l’allié·e', () {
      final p = (member()
            ..city = ' Cotonou '
            ..phone = ''
            ..zoneId = 1
            ..communeId = 3
            ..allyRole = 'enseignant')
          .toPayload();
      expect(p['first_name'], 'Awa');
      expect(p['age'], 24);
      expect(p['member_type'], 'membre');
      expect(p['gender_identity'], 'femme');
      expect(p['orientation'], 'lesbienne');
      expect(p['sensitive_data'], true);
      expect(p['city'], 'Cotonou');
      expect(p.containsKey('phone'), isFalse);
      expect(p['zone_id'], 1);
      expect(p['commune_id'], 3);
      expect(p.containsKey('ally_role'), isFalse);
      expect(p.containsKey('gender_identity_other'), isFalse);
      expect(p.containsKey('invitation'), isFalse);
      expect(p['cgu'], true);
      expect(p['privacy'], true);
    });

    test('allié·e : statut seulement ; « autre » envoie la précision', () {
      final p = (ally()
            ..allyRole = kOtherValue
            ..allyRoleOther = 'Bénévole')
          .toPayload();
      expect(p['member_type'], 'allie');
      expect(p['ally_role'], 'autre');
      expect(p['ally_role_other'], 'Bénévole');
      expect(p.containsKey('sensitive_data'), isFalse);
      expect(p.containsKey('orientation'), isFalse);
      expect(p.containsKey('gender_identity'), isFalse);
    });

    test('zone et commune ne partent que pour le Bénin', () {
      final p = (member()
            ..countryCode = 'SN'
            ..zoneId = 1
            ..communeId = 3)
          .toPayload();
      expect(p.containsKey('zone_id'), isFalse);
      expect(p.containsKey('commune_id'), isFalse);
    });

    test('invitation envoyée seulement si renseignée', () {
      expect((member()..invitation = 'XM-AAAA').toPayload()['invitation'], 'XM-AAAA');
    });
  });

  group('étape d’un champ fautif', () {
    test('chaque champ du contrat appartient à une étape', () {
      expect(stepOfField('first_name'), 1);
      expect(stepOfField('invitation'), 1);
      expect(stepOfField('country_code'), 2);
      expect(stepOfField('member_type'), 2);
      expect(stepOfField('commune_id'), 2);
      expect(stepOfField('gender_identity_other'), 3);
      expect(stepOfField('ally_role'), 3);
      expect(stepOfField('password'), 3);
      expect(stepOfField('cgu'), 3);
      expect(stepOfField('inconnu'), isNull);
    });

    test('la plus basse étape fautive est rouverte', () {
      expect(firstFaultyStep(['cgu', 'email', 'pronouns']), 1);
      expect(firstFaultyStep(['cgu', 'country_code']), 2);
      expect(firstFaultyStep(['rien']), isNull);
    });
  });

  test('référentiels : lecture du JSON du contrat', () {
    final r = RegistrationReferentials.fromJson({
      'min_age': 16,
      'pronouns': [
        {'value': 'elle', 'label': 'Elle'}
      ],
      'countries': [
        {'value': 'BJ', 'label': 'Bénin'}
      ],
    });
    expect(r.minAge, 16);
    expect(r.pronouns.single.value, 'elle');
    expect(r.countries.single.label, 'Bénin');
    expect(r.allyRoles, isEmpty);
  });
}
