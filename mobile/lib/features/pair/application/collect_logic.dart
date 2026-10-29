import '../data/pair_models.dart';

/// Valeurs fermées qui ne viennent pas d'un référentiel : natures et sexes (énumérations du serveur).
const kCaseKinds = ['VBG', 'VDH'];
const kSexes = ['femme', 'homme', 'autre'];

/// Étapes de la collecte (maquette P8) : Dossier · Lieu · Personne · Faits · Vérification.
enum CollectStep { dossier, lieu, personne, faits, verification }

/// Champs du formulaire et étape qui les porte.
const Map<String, CollectStep> kFieldStep = {
  'kind': CollectStep.dossier,
  'occurred_on': CollectStep.dossier,
  'zone_id': CollectStep.lieu,
  'commune_id': CollectStep.lieu,
  'population_id': CollectStep.personne,
  'sex': CollectStep.personne,
  'age_range': CollectStep.personne,
  'violence_type_id': CollectStep.faits,
  'referral': CollectStep.faits,
  'notes': CollectStep.faits,
};

/// Saisie d'une fiche. Les identifiants sont ceux des référentiels du serveur.
class CollectForm {
  const CollectForm({
    this.kind,
    this.occurredOn,
    this.zoneId,
    this.communeId,
    this.populationId,
    this.sex,
    this.ageRange,
    this.violenceTypeId,
    this.referral,
    this.notes = '',
  });

  final String? kind;

  /// `YYYY-MM-DD`.
  final String? occurredOn;
  final int? zoneId;
  final int? communeId;
  final int? populationId;
  final String? sex;
  final String? ageRange;
  final int? violenceTypeId;
  final String? referral;
  final String notes;

  CollectForm copyWith({
    String? kind,
    String? occurredOn,
    int? zoneId,
    int? communeId,
    int? populationId,
    String? sex,
    String? ageRange,
    int? violenceTypeId,
    String? referral,
    String? notes,
    bool clearCommune = false,
    bool clearReferral = false,
  }) => CollectForm(
    kind: kind ?? this.kind,
    occurredOn: occurredOn ?? this.occurredOn,
    zoneId: zoneId ?? this.zoneId,
    communeId: clearCommune ? null : (communeId ?? this.communeId),
    populationId: populationId ?? this.populationId,
    sex: sex ?? this.sex,
    ageRange: ageRange ?? this.ageRange,
    violenceTypeId: violenceTypeId ?? this.violenceTypeId,
    referral: clearReferral ? null : (referral ?? this.referral),
    notes: notes ?? this.notes,
  );

  /// Corps de `POST pair/cases` (§22) : champs vides omis, jamais de nom ni de coordonnées.
  Map<String, dynamic> toPayload() => {
    'kind': kind,
    'occurred_on': occurredOn,
    'zone_id': zoneId,
    'commune_id': ?communeId,
    'population_id': populationId,
    'sex': sex,
    'age_range': ageRange,
    'violence_type_id': violenceTypeId,
    if (referral != null && referral!.isNotEmpty) 'referral': referral,
    if (notes.trim().isNotEmpty) 'notes': notes.trim(),
  };

  /// Brouillon : tout sauf les notes (jamais de texte libre sur le disque hors file).
  Map<String, dynamic> toDraft() => {
    'kind': kind,
    'occurred_on': occurredOn,
    'zone_id': zoneId,
    'commune_id': communeId,
    'population_id': populationId,
    'sex': sex,
    'age_range': ageRange,
    'violence_type_id': violenceTypeId,
    'referral': referral,
  };

  static CollectForm fromMap(Map<String, dynamic> m, {String? notes}) => CollectForm(
    kind: m['kind'] as String?,
    occurredOn: m['occurred_on'] as String?,
    zoneId: (m['zone_id'] as num?)?.toInt(),
    communeId: (m['commune_id'] as num?)?.toInt(),
    populationId: (m['population_id'] as num?)?.toInt(),
    sex: m['sex'] as String?,
    ageRange: m['age_range'] as String?,
    violenceTypeId: (m['violence_type_id'] as num?)?.toInt(),
    referral: m['referral'] as String?,
    notes: notes ?? (m['notes'] as String? ?? ''),
  );

  bool get isEmpty =>
      kind == null &&
      occurredOn == null &&
      zoneId == null &&
      communeId == null &&
      populationId == null &&
      sex == null &&
      ageRange == null &&
      violenceTypeId == null &&
      referral == null &&
      notes.isEmpty;
}

/// Erreur d'un champ : clé de texte + paramètres (message traduit dans l'interface).
class FieldError {
  const FieldError(this.key, [this.args = const {}]);
  final String key;
  final Map<String, Object?> args;
}

/// Contrôles locaux de la collecte : **les mêmes règles que le serveur**
/// (`CaseRules`, API §22), prises dans le catalogue en cache. Un champ
/// obligatoire, une valeur de liste et un format sont toujours contrôlés ; les
/// règles activables (`date_not_future`, `age_range`, `commune_in_zone`,
/// `min_len_20`) suivent le catalogue. Aucun référentiel n'est codé en dur.
class CollectValidator {
  const CollectValidator({required this.rules, required this.lists, required this.today});
  final ValidationRules rules;
  final CollectLists lists;

  /// Jour calendaire du Bénin.
  final DateTime today;

  static final RegExp _day = RegExp(r'^\d{4}-\d{2}-\d{2}$');

  DateTime? parseDay(String? s) {
    if (s == null || !_day.hasMatch(s)) return null;
    final d = DateTime.tryParse(s);
    if (d == null) return null;
    // `2026-02-31` est refusé : la date doit exister.
    return '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}' == s
        ? d
        : null;
  }

  Map<String, FieldError> validate(CollectForm f, {Set<CollectStep>? steps}) {
    final e = <String, FieldError>{};
    bool want(String field) => steps == null || steps.contains(kFieldStep[field]);
    const required = FieldError('peer_collecte_errors_required');
    const invalid = FieldError('peer_collecte_errors_invalid');

    if (want('kind')) {
      if (f.kind == null) {
        e['kind'] = required;
      } else if (!kCaseKinds.contains(f.kind)) {
        e['kind'] = invalid;
      }
    }
    if (want('occurred_on')) {
      if (f.occurredOn == null || f.occurredOn!.isEmpty) {
        e['occurred_on'] = required;
      } else {
        final d = parseDay(f.occurredOn);
        if (d == null) {
          e['occurred_on'] = const FieldError('peer_collecte_errors_date_invalid');
        } else if (rules.isActive('date_not_future') && d.isAfter(DateTime(today.year, today.month, today.day))) {
          e['occurred_on'] = const FieldError('peer_collecte_errors_date_future');
        }
      }
    }
    if (want('zone_id')) {
      if (f.zoneId == null) {
        e['zone_id'] = required;
      } else if (lists.zoneName(f.zoneId) == null) {
        e['zone_id'] = invalid;
      }
    }
    if (want('commune_id') && f.communeId != null) {
      final exists = lists.communeName(f.communeId) != null;
      if (!exists) {
        e['commune_id'] = invalid;
      } else if (rules.isActive('commune_in_zone') && !lists.communeInZone(f.communeId, f.zoneId)) {
        e['commune_id'] = const FieldError('peer_collecte_errors_commune_in_zone');
      }
    }
    if (want('population_id')) {
      if (f.populationId == null) {
        e['population_id'] = required;
      } else if (lists.populationLabel(f.populationId) == null) {
        e['population_id'] = invalid;
      }
    }
    if (want('sex')) {
      if (f.sex == null) {
        e['sex'] = required;
      } else if (!kSexes.contains(f.sex)) {
        e['sex'] = invalid;
      }
    }
    if (want('age_range')) {
      if (f.ageRange == null) {
        e['age_range'] = required;
      } else if (rules.ageRanges.isNotEmpty && !rules.ageRanges.any((a) => a.value == f.ageRange)) {
        // `age_range` active : la tranche doit tenir dans les bornes de plausibilité.
        e['age_range'] = invalid;
      }
    }
    if (want('violence_type_id')) {
      if (f.violenceTypeId == null) {
        e['violence_type_id'] = required;
      } else if (lists.violenceLabel(f.violenceTypeId) == null) {
        e['violence_type_id'] = invalid;
      }
    }
    if (want('referral') && f.referral != null && f.referral!.isNotEmpty) {
      if (rules.referrals.isNotEmpty && !rules.referrals.contains(f.referral)) e['referral'] = invalid;
    }
    if (want('notes') && f.notes.trim().isNotEmpty && rules.isActive('min_len_20')) {
      final min = rules.notesMinLength;
      if (f.notes.trim().length < min) {
        e['notes'] = FieldError('peer_collecte_errors_notes_min', {'min': min});
      }
    }
    return e;
  }

  /// Étape (la première) qui porte une erreur.
  CollectStep? firstStepWithError(Map<String, FieldError> errors) {
    CollectStep? first;
    for (final k in errors.keys) {
      final s = kFieldStep[k];
      if (s != null && (first == null || s.index < first.index)) first = s;
    }
    return first;
  }

  /// Avertissements de plausibilité **non bloquants** (RG-07), calculés avant
  /// l'envoi comme le fait le serveur à l'enregistrement : date trop ancienne
  /// (`plausibility.max_past_years`, 5 par défaut) et doublon suspect parmi les
  /// dossiers déjà sur l'appareil (`duplicates.fields`).
  List<CollectWarning> warnings(CollectForm f, List<Map<String, dynamic>> knownCases) {
    final out = <CollectWarning>[];
    final d = parseDay(f.occurredOn);
    if (d != null && rules.isActive('plausibility')) {
      final years = (rules.rule('plausibility')?.params['max_past_years'] as num?)?.toInt() ?? 5;
      final limit = DateTime(today.year - years, today.month, today.day);
      if (d.isBefore(limit)) out.add(CollectWarning('cases_warnings_date_old', {'years': years}));
    }
    if (rules.isActive('duplicates')) {
      final fields = _duplicateColumns();
      final mine = <String, Object?>{
        'occurred_on': f.occurredOn,
        'commune_id': f.communeId,
        'population_id': f.populationId,
        'age_range': f.ageRange,
        'violence_type_id': f.violenceTypeId,
        'sex': f.sex,
      };
      // Un champ vide ne prouve aucun doublon.
      if (fields.every((c) => mine[c] != null)) {
        final codes = <String>[
          for (final c in knownCases)
            if (c['status'] != 'archive' && fields.every((col) => '${c[col]}' == '${mine[col]}')) '${c['code']}',
        ]..sort();
        if (codes.isNotEmpty) {
          out.add(CollectWarning('cases_warnings_duplicate_suspected', {'count': codes.length, 'codes': codes.take(5).join(', ')}));
        }
      }
    }
    return out;
  }

  List<String> _duplicateColumns() {
    const map = {
      'date': 'occurred_on',
      'commune': 'commune_id',
      'population': 'population_id',
      'age': 'age_range',
      'type': 'violence_type_id',
      'sexe': 'sex',
    };
    final raw = rules.rule('duplicates')?.params['fields'];
    final cols = <String>[
      if (raw is List)
        for (final x in raw)
          if (map.containsKey(x)) map['$x']!,
    ];
    return cols.isEmpty ? const ['occurred_on', 'commune_id', 'population_id', 'age_range'] : cols.toSet().toList();
  }
}

class CollectWarning {
  const CollectWarning(this.key, this.args);
  final String key;
  final Map<String, Object?> args;
}
