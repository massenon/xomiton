
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:uuid/uuid.dart';

import '../../../core/providers/core_providers.dart';
import '../../../core/sync/sync_providers.dart';
import '../../../core/sync/sync_queue.dart';
import '../../auth/application/session_controller.dart';
import 'collect_logic.dart';
import 'pair_providers.dart';

/// Issue d'un enregistrement : parti (code attribué), en file, ou refusé.
enum CollectOutcome { sent, queued, failed }

class CollectResult {
  const CollectResult({
    required this.outcome,
    required this.entryUuid,
    this.code,
    this.warnings = const [],
    this.errors = const {},
    this.failure,
    this.form,
  });
  final CollectOutcome outcome;
  final String entryUuid;
  final String? code;

  /// Avertissements de plausibilité rendus par le serveur (`meta.warnings`).
  final List<Map<String, dynamic>> warnings;
  final Map<String, List<String>> errors;
  final String? failure;

  /// La saisie, pour « Corriger et renvoyer ».
  final CollectForm? form;
}

class CollectState {
  const CollectState({
    this.form = const CollectForm(),
    this.step = 0,
    this.errors = const {},
    this.result,
    this.busy = false,
    this.restored = false,
  });
  final CollectForm form;
  final int step;
  final Map<String, FieldError> errors;
  final CollectResult? result;
  final bool busy;

  /// Un brouillon a été repris à l'ouverture.
  final bool restored;

  CollectState copyWith({
    CollectForm? form,
    int? step,
    Map<String, FieldError>? errors,
    CollectResult? result,
    bool clearResult = false,
    bool? busy,
    bool? restored,
  }) => CollectState(
    form: form ?? this.form,
    step: step ?? this.step,
    errors: errors ?? this.errors,
    result: clearResult ? null : (result ?? this.result),
    busy: busy ?? this.busy,
    restored: restored ?? this.restored,
  );
}

const _draftKey = 'draft:collecte';

/// Brouillon de collecte : **étape par étape**, dans le coffre chiffré du pair,
/// distinct de la file d'envoi. Sans les notes. Vidé à l'envoi, à la
/// déconnexion et à l'expiration (c'est le coffre du pair).
class CollectController extends Notifier<CollectState> {
  @override
  CollectState build() {
    // Un autre compte : la saisie du précédent ne reste jamais en mémoire.
    ref.watch(sessionProvider.select((s) => s.user?.id));
    final draft = ref.read(peerStoreProvider).json(_draftKey);
    if (draft == null) return const CollectState();
    return CollectState(
      form: CollectForm.fromMap(draft),
      step: ((draft['step'] as num?)?.toInt() ?? 0).clamp(0, CollectStep.values.length - 1),
      restored: true,
    );
  }

  Future<void> _saveDraft() async {
    final store = ref.read(peerStoreProvider);
    if (state.form.isEmpty) {
      await store.delete(_draftKey);
      return;
    }
    await store.putJson(_draftKey, {...state.form.toDraft(), 'step': state.step});
  }

  void update(CollectForm form) {
    // Une saisie corrigée efface son erreur.
    final errs = {...state.errors};
    state = state.copyWith(form: form, errors: errs, restored: state.restored);
    _saveDraft();
  }

  void clearError(String field) {
    if (!state.errors.containsKey(field)) return;
    state = state.copyWith(errors: {...state.errors}..remove(field));
  }

  void goTo(int step) {
    state = state.copyWith(step: step.clamp(0, CollectStep.values.length - 1), errors: {});
    _saveDraft();
  }

  /// Étape suivante si les champs de l'étape sont valables ; sinon les erreurs.
  bool next(CollectValidator v) {
    final cur = CollectStep.values[state.step];
    final errs = v.validate(state.form, steps: {cur});
    if (errs.isNotEmpty) {
      state = state.copyWith(errors: errs);
      return false;
    }
    goTo(state.step + 1);
    return true;
  }

  void back() => goTo(state.step - 1);

  Future<void> clearDraft() async {
    await ref.read(peerStoreProvider).delete(_draftKey);
    state = const CollectState();
  }

  /// Nouvelle fiche, repartie de zéro.
  void restart() => state = const CollectState();

  /// Reprend la saisie d'une fiche refusée pour la corriger.
  void reopen(CollectForm form, {Map<String, List<String>> errors = const {}}) {
    final map = <String, FieldError>{
      for (final e in errors.entries)
        if (kFieldStep.containsKey(e.key)) e.key: FieldError('_raw', {'text': e.value.first}),
    };
    final first = map.keys.map((k) => kFieldStep[k]!.index).fold<int?>(null, (a, b) => a == null || b < a ? b : a);
    state = CollectState(form: form, step: first ?? 0, errors: map);
    _saveDraft();
  }

  /// Enregistre : valide tout, écrit dans la file chiffrée (uuid = `client_uuid`
  /// = `Idempotency-Key` : jamais de doublon, même après un rejeu), envoie tout
  /// de suite si le réseau est là. Sans réseau, la fiche attend sur l'appareil.
  Future<CollectResult?> submit(CollectValidator v, {Map<String, String> summary = const {}}) async {
    if (state.busy) return null;
    final errs = v.validate(state.form);
    if (errs.isNotEmpty) {
      final first = v.firstStepWithError(errs)!;
      state = state.copyWith(errors: errs, step: first.index);
      return null;
    }
    state = state.copyWith(busy: true, errors: {});
    final uuid = const Uuid().v4();
    final queue = ref.read(syncQueueProvider);
    final form = state.form;
    await queue.save(
      QueueEntry(
        uuid: uuid,
        kind: QueueKind.caseCreate,
        createdAt: DateTime.now(),
        payload: {...form.toPayload(), 'client_uuid': uuid},
        summary: {'kind': form.kind, 'occurred_on': form.occurredOn, ...summary},
      ),
    );
    await ref.read(peerStoreProvider).delete(_draftKey);
    var offline = !ref.read(isOnlineProvider);
    if (!offline) {
      final engine = ref.read(syncEngineProvider);
      for (var i = 0; i < 100 && engine.running; i++) {
        await Future<void>.delayed(const Duration(milliseconds: 100));
      }
      await engine.sync(force: true, pushOnly: true);
      offline = engine.state.offline;
    }
    final e = queue.find(uuid);
    final CollectResult result;
    if (e != null && e.status == QueueStatus.sent) {
      result = CollectResult(
        outcome: CollectOutcome.sent,
        entryUuid: uuid,
        code: e.resultCode,
        warnings: e.warnings,
        form: form,
      );
    } else if (e != null && e.status == QueueStatus.failed) {
      result = CollectResult(
        outcome: CollectOutcome.failed,
        entryUuid: uuid,
        errors: e.errors,
        failure: e.failure,
        form: form,
      );
    } else {
      result = CollectResult(outcome: CollectOutcome.queued, entryUuid: uuid, form: form);
    }
    state = state.copyWith(result: result, busy: false);
    ref.invalidate(peerHomeProvider);
    return result;
  }

  /// Retire une fiche refusée de la file (la personne la corrige : elle repart
  /// sous un nouvel envoi) ou la ferme.
  Future<void> discardEntry(String uuid) => ref.read(syncQueueProvider).remove(uuid);
}

/// Pas d'`autoDispose` : la saisie survit à un détour par la file de synchronisation
/// (« Corriger »), et repart de zéro à la déconnexion ou au changement de compte.
final collectControllerProvider = NotifierProvider<CollectController, CollectState>(CollectController.new);

/// Fiches connues de l'appareil (doublons suspects).
final knownCasesProvider = Provider.autoDispose<List<Map<String, dynamic>>>(
  (ref) => ref.watch(peerStoreProvider).rows('cases'),
);

