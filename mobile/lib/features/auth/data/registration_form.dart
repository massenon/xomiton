import '../../../core/util/password_strength.dart';

/// Une entrée d'une liste fournie par le serveur (`{value, label}`).
class RegOption {
  const RegOption(this.value, this.label);
  final String value;
  final String label;

  factory RegOption.fromJson(Map<String, dynamic> j) => RegOption('${j['value']}', '${j['label']}');
}

/// Référentiels d'inscription (`GET referentials/registration`) : âge minimal et
/// listes. Rien n'est codé en dur côté application, le serveur fait foi.
class RegistrationReferentials {
  const RegistrationReferentials({
    required this.minAge,
    this.pronouns = const [],
    this.memberTypes = const [],
    this.countries = const [],
    this.zoneCountries = const [],
    this.allyRoles = const [],
    this.genderIdentities = const [],
    this.orientations = const [],
  });

  final int minAge;
  final List<RegOption> pronouns;
  final List<RegOption> memberTypes;
  final List<RegOption> countries;

  /// Codes ISO des pays qui ont des zones actives : zone puis commune ne sont proposées que pour eux.
  final List<String> zoneCountries;
  final List<RegOption> allyRoles;
  final List<RegOption> genderIdentities;
  final List<RegOption> orientations;

  factory RegistrationReferentials.fromJson(Map<String, dynamic> j) {
    List<RegOption> list(Object? v) => v is List
        ? [for (final e in v) if (e is Map) RegOption.fromJson(Map<String, dynamic>.from(e))]
        : const [];
    return RegistrationReferentials(
      minAge: (j['min_age'] as num?)?.toInt() ?? 0,
      pronouns: list(j['pronouns']),
      memberTypes: list(j['member_types']),
      countries: list(j['countries']),
      zoneCountries: j['zone_countries'] is List ? [for (final c in j['zone_countries'] as List) '$c'] : const [],
      allyRoles: list(j['ally_roles']),
      genderIdentities: list(j['gender_identities']),
      orientations: list(j['orientations']),
    );
  }
}

/// Valeurs `member_type` du contrat.
const String kMemberTypeMember = 'membre';
const String kMemberTypeAlly = 'allie';

/// Valeur « autre » des listes : impose un champ de précision.
const String kOtherValue = 'autre';

/// Pays proposé en premier dans la liste (les zones, elles, existent pour tout pays qui en a d'actives).
const String kBeninCode = 'BJ';

/// Nombre d'étapes de l'ancien formulaire.
const int kRegistrationSteps = 3;

/// Nature d'une erreur de validation locale ; l'écran la traduit (aucun texte ici).
enum RegIssue { required, minLength, format, email, ageNotNumber, ageTooLow, password, mismatch, otherRequired, consent }

class FormIssue {
  const FormIssue(this.kind, [this.param]);
  final RegIssue kind;

  /// Âge minimal ou longueur minimale selon [kind].
  final int? param;

  @override
  bool operator ==(Object other) => other is FormIssue && other.kind == kind && other.param == param;

  @override
  int get hashCode => Object.hash(kind, param);
}

/// Champs de chaque étape, sous le NOM D'API (les erreurs 422 portent ces clés).
const Map<int, List<String>> kRegistrationStepFields = {
  1: ['first_name', 'last_name', 'pseudo', 'email', 'age', 'invitation'],
  2: ['pronouns', 'country_code', 'city', 'phone', 'zone_id', 'commune_id', 'member_type'],
  3: [
    'gender_identity',
    'gender_identity_other',
    'orientation',
    'orientation_other',
    'sensitive_data',
    'ally_role',
    'ally_role_other',
    'password',
    'password_confirmation',
    'cgu',
    'privacy',
  ],
};

/// Étape qui contient un champ (nom d'API), ou null si inconnu.
int? stepOfField(String field) {
  for (final e in kRegistrationStepFields.entries) {
    if (e.value.contains(field)) return e.key;
  }
  return null;
}

/// Étape la plus basse qui contient un champ fautif (1 à 3), ou null.
int? firstFaultyStep(Iterable<String> fields) {
  int? best;
  for (final f in fields) {
    final s = stepOfField(f);
    if (s != null && (best == null || s < best)) best = s;
  }
  return best;
}

/// État du formulaire d'inscription en 3 étapes : tout reste en mémoire jusqu'à
/// l'envoi final. Aucune méthode ici n'écrit dans un journal.
class RegistrationForm {
  String firstName = '';
  String lastName = '';
  String pseudo = '';
  String email = '';
  String age = '';
  String invitation = '';

  String? pronouns;
  String? countryCode;
  String city = '';
  String phone = '';
  int? zoneId;
  int? communeId;
  String? memberType;

  String? genderIdentity;
  String genderIdentityOther = '';
  String? orientation;
  String orientationOther = '';
  bool sensitiveData = false;
  String? allyRole;
  String allyRoleOther = '';

  String password = '';
  String passwordConfirmation = '';
  bool cgu = false;
  bool privacy = false;

  bool get isMember => memberType == kMemberTypeMember;
  bool get isAlly => memberType == kMemberTypeAlly;

  static final RegExp _pseudoPattern = RegExp(r'^[A-Za-z0-9_.\-]{1,40}$');
  static final RegExp _emailPattern = RegExp(r'^[^\s@]+@[^\s@]+\.[^\s@]+$');

  /// Âge saisi (entier), ou null s'il n'en est pas un.
  int? get ageValue => int.tryParse(age.trim());

  /// Erreurs de l'étape [step] (1 à 3), par nom d'API. Vide = étape valide.
  Map<String, FormIssue> validateStep(int step, {required int minAge}) {
    final e = <String, FormIssue>{};
    void required(String key, String? v) {
      if (v == null || v.trim().isEmpty) e[key] = const FormIssue(RegIssue.required);
    }

    switch (step) {
      case 1:
        required('first_name', firstName);
        required('last_name', lastName);
        final p = pseudo.trim();
        if (p.isEmpty) {
          e['pseudo'] = const FormIssue(RegIssue.required);
        } else if (p.length < 3) {
          e['pseudo'] = const FormIssue(RegIssue.minLength, 3);
        } else if (!_pseudoPattern.hasMatch(p)) {
          e['pseudo'] = const FormIssue(RegIssue.format);
        }
        final m = email.trim();
        if (m.isEmpty) {
          e['email'] = const FormIssue(RegIssue.required);
        } else if (!_emailPattern.hasMatch(m)) {
          e['email'] = const FormIssue(RegIssue.email);
        }
        if (age.trim().isEmpty) {
          e['age'] = const FormIssue(RegIssue.required);
        } else {
          final a = ageValue;
          if (a == null) {
            e['age'] = const FormIssue(RegIssue.ageNotNumber);
          } else if (a < minAge) {
            e['age'] = FormIssue(RegIssue.ageTooLow, minAge);
          }
        }
      case 2:
        required('pronouns', pronouns);
        required('country_code', countryCode);
        required('member_type', memberType);
      case 3:
        if (isMember) {
          required('orientation', orientation);
          required('gender_identity', genderIdentity);
          if (orientation == kOtherValue) required('orientation_other', orientationOther);
          if (genderIdentity == kOtherValue) required('gender_identity_other', genderIdentityOther);
          if (!sensitiveData) e['sensitive_data'] = const FormIssue(RegIssue.consent);
        } else if (isAlly) {
          required('ally_role', allyRole);
          if (allyRole == kOtherValue) required('ally_role_other', allyRoleOther);
        }
        if (password.isEmpty) {
          e['password'] = const FormIssue(RegIssue.required);
        } else if (!PasswordStrength(password).meetsPolicy) {
          e['password'] = const FormIssue(RegIssue.password);
        }
        if (passwordConfirmation.isEmpty) {
          e['password_confirmation'] = const FormIssue(RegIssue.required);
        } else if (passwordConfirmation != password) {
          e['password_confirmation'] = const FormIssue(RegIssue.mismatch);
        }
        if (!cgu) e['cgu'] = const FormIssue(RegIssue.consent);
        if (!privacy) e['privacy'] = const FormIssue(RegIssue.consent);
    }
    return e;
  }

  /// Corps de `POST auth/register` (contrat du 11/11/2026). Les champs qui ne
  /// concernent pas le profil choisi ne sont JAMAIS envoyés.
  Map<String, dynamic> toPayload() {
    String? text(String v) {
      final t = v.trim();
      return t.isEmpty ? null : t;
    }

    final member = isMember;
    final ally = isAlly;
    final inv = text(invitation);
    return <String, dynamic>{
      'first_name': firstName.trim(),
      'last_name': lastName.trim(),
      'pseudo': pseudo.trim(),
      'email': email.trim(),
      'age': ageValue,
      'pronouns': pronouns,
      'country_code': countryCode,
      'city': ?text(city),
      'phone': ?text(phone),
      if (zoneId != null) 'zone_id': zoneId,
      if (zoneId != null && communeId != null) 'commune_id': communeId,
      'member_type': memberType,
      if (member) ...{
        'gender_identity': genderIdentity,
        'orientation': orientation,
        if (genderIdentity == kOtherValue) 'gender_identity_other': genderIdentityOther.trim(),
        if (orientation == kOtherValue) 'orientation_other': orientationOther.trim(),
        'sensitive_data': sensitiveData,
      },
      if (ally) ...{
        'ally_role': allyRole,
        if (allyRole == kOtherValue) 'ally_role_other': allyRoleOther.trim(),
      },
      'password': password,
      'password_confirmation': passwordConfirmation,
      'cgu': cgu,
      'privacy': privacy,
      'invitation': ?inv,
    };
  }
}
