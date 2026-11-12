import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:xomiton/core/providers/core_providers.dart';

import '../../support/fakes.dart';
import '../../support/harness.dart';
import 'feed_support.dart';

Future<void> _openPoll(WidgetTester tester, FakeApi api, {bool online = true}) async {
  await boot(tester, memberEnv(api, online: online), '/feed/new');
  await tester.tap(find.text(S.t('feed_compose_poll')));
  await settle(tester, 3);
}

void main() {
  setUpAll(() => initializeDateFormatting('fr'));

  testWidgets('bascule publication / sondage : titre, champs du sondage', (tester) async {
    await boot(tester, memberEnv(apiWith({})), '/feed/new');
    expect(field('compose-question'), findsNothing);
    await tester.tap(find.text(S.t('feed_compose_poll')));
    await settle(tester, 3);
    expect(field('compose-question'), findsOneWidget);
    expect(find.text(S.t('feed_poll_title')), findsOneWidget);
    expect(find.text(S.t('feed_poll_results_before_vote')), findsOneWidget);
    await disposeApp(tester);
  });

  testWidgets('réponses : 2 au départ (pas de retrait), ajout jusqu’à 6 puis refus annoncé, retrait annoncé', (tester) async {
    await _openPoll(tester, apiWith({}));
    expect(field('compose-option-1'), findsOneWidget);
    expect(field('compose-remove-option-0'), findsNothing, reason: 'minimum 2 réponses');
    for (var i = 3; i <= 6; i++) {
      await tapKey(tester, 'compose-add-option');
      expect(field('compose-option-${i - 1}'), findsOneWidget);
    }
    expect(
      find.descendant(of: field('compose-live'), matching: find.text(S.t('mobile_compose_poll_option_added', {'n': 6, 'count': 6, 'max': 6}))),
      findsOneWidget,
    );
    expect(field('compose-add-option'), findsNothing, reason: 'maximum 6');
    expect(find.text(S.t('mobile_compose_poll_max_reached', {'max': 6})), findsWidgets);
    await tapKey(tester, 'compose-remove-option-5');
    expect(field('compose-option-5'), findsNothing);
    expect(find.text(S.t('mobile_compose_poll_option_removed', {'count': 5})), findsOneWidget);
    expect(field('compose-add-option'), findsOneWidget);
    // Chaque bouton de retrait nomme sa réponse (Semantics / tooltip).
    await toTop(tester);
    expect(find.byTooltip(S.t('feed_poll_option_remove', {'n': 1})), findsOneWidget);
    await disposeApp(tester);
  });

  testWidgets('validation locale : erreurs sous les champs, rien n’est envoyé', (tester) async {
    final api = apiWith({});
    await _openPoll(tester, api);
    await tapKey(tester, 'compose-submit');
    expect(find.text(S.t('feed_poll_question_error', {'min': 5, 'max': 200})), findsWidgets);
    expect(find.text(S.t('feed_poll_options_error', {'min': 2})), findsWidgets);
    expect(api.calls('POST posts'), isEmpty);
    // Région live : l’erreur est annoncée.
    expect(find.descendant(of: field('compose-live'), matching: find.textContaining(S.t('feed_create_errors_title'))), findsOneWidget);

    await type(tester, 'compose-question', 'Quel thème pour la causerie ?');
    await type(tester, 'compose-option-0', 'Santé');
    await type(tester, 'compose-option-1', 'sante');
    await tapKey(tester, 'compose-submit');
    expect(find.text(S.t('feed_poll_options_duplicate')), findsWidgets);
    expect(api.calls('POST posts'), isEmpty);
    await disposeApp(tester);
  });

  testWidgets('envoi : JSON kind=poll par la boîte d’envoi (sans média), choix multiple, résultats avant le vote, durée', (tester) async {
    final api = apiWith({
      'POST posts': (_) => jsonBody(201, {'data': postJson('P8', body: 'Sondage', mine: true)}),
    });
    await _openPoll(tester, api);
    await type(tester, 'compose-question', 'Quel thème pour la causerie ?');
    await type(tester, 'compose-option-0', 'Santé');
    await type(tester, 'compose-option-1', 'Droits');
    await tapKey(tester, 'compose-add-option');
    await type(tester, 'compose-option-2', ''); // ligne vide : retirée à l’envoi
    await type(tester, 'compose-body', 'Aidez-nous à choisir.');
    await reveal(tester, find.text(S.t('feed_poll_multiple')));
    await tester.tap(find.text(S.t('feed_poll_multiple')));
    await reveal(tester, find.text(S.t('feed_poll_results_before_vote')));
    await tester.tap(find.text(S.t('feed_poll_results_before_vote')));
    await tapKey(tester, 'compose-submit');
    await settle(tester, 6);
    final b = api.calls('POST posts').single.data as Map;
    expect(b['kind'], 'poll');
    expect(b['body'], 'Aidez-nous à choisir.');
    expect(b['visibility'], 'public');
    expect(b['is_anonymous'], false);
    final poll = b['poll'] as Map;
    expect(poll['question'], 'Quel thème pour la causerie ?');
    expect(poll['options'], ['Santé', 'Droits']);
    expect(poll['multiple'], true);
    expect(poll['results_before_vote'], true);
    expect(poll['duration_days'], 7);
    expect(find.text(S.t('feed_page_title')), findsOneWidget, reason: 'retour au fil après l’envoi');
    expect(find.text(S.t('feed_create_errors_title')), findsNothing);
    await disposeApp(tester);
  });

  testWidgets('hors ligne : le sondage sans média suit la boîte d’envoi existante', (tester) async {
    var online = false;
    final api = apiWith({
      'POST posts': (o) {
        if (!online) throw offlineError(o);
        return jsonBody(201, {'data': postJson('P9')});
      },
    });
    final env = memberEnv(api, online: false);
    await boot(tester, env, '/feed/new');
    await tester.tap(find.text(S.t('feed_compose_poll')));
    await settle(tester, 3);
    await type(tester, 'compose-question', 'Quel thème pour la causerie ?');
    await type(tester, 'compose-option-0', 'Santé');
    await type(tester, 'compose-option-1', 'Droits');
    await tapKey(tester, 'compose-submit');
    await settle(tester, 8);
    expect(find.text(S.t('microcopy_offline_queued')), findsOneWidget);
    expect(env.db.outbox.keys, hasLength(1));
    online = true;
    unawaited(containerOf(tester).read(outboxProvider).flush());
    await settle(tester, 6);
    expect(api.calls('POST posts'), hasLength(2));
    expect(env.db.outbox.keys, isEmpty);
    await disposeApp(tester);
  });

  testWidgets('422 : l’erreur poll.options.N est rattachée à la bonne ligne, poll.question à la question', (tester) async {
    final api = apiWith({
      'POST posts': (_) => jsonBody(422, {
        'message': 'Corrige le sondage.',
        'code': 'validation_failed',
        'errors': {
          'poll.question': ['Ta question est refusée.'],
          'poll.options.1': ['Cette réponse est refusée.'],
        },
      }),
    });
    await _openPoll(tester, api);
    await tapKey(tester, 'compose-add-option');
    await type(tester, 'compose-question', 'Quel thème pour la causerie ?');
    await type(tester, 'compose-option-0', 'Santé');
    await type(tester, 'compose-option-1', ''); // vide : non envoyée
    await type(tester, 'compose-option-2', 'Droits');
    await tapKey(tester, 'compose-submit');
    await settle(tester, 6);
    expect(find.text('Ta question est refusée.'), findsWidgets);
    // N = 1 → deuxième réponse envoyée = « Droits » = ligne 3 (index 2).
    final row = find.ancestor(of: find.text('Cette réponse est refusée.'), matching: find.byType(TextField));
    expect(row, findsOneWidget);
    expect(tester.widget<TextField>(row).controller!.text, 'Droits');
    // Le brouillon reste.
    expect(tester.widget<TextField>(field('compose-option-0')).controller!.text, 'Santé');
    await disposeApp(tester);
  });

  testWidgets('mot refusé (content_rejected) : message bienveillant, brouillon conservé', (tester) async {
    final api = apiWith({
      'POST posts': (_) => jsonBody(422, {'message': 'Refusé', 'code': 'content_rejected'}),
    });
    await _openPoll(tester, api);
    await type(tester, 'compose-question', 'Quel thème pour la causerie ?');
    await type(tester, 'compose-option-0', 'Santé');
    await type(tester, 'compose-option-1', 'Droits');
    await tapKey(tester, 'compose-submit');
    await settle(tester, 6);
    expect(find.text(S.t('feed_create_bad_words')), findsWidgets);
    expect(tester.widget<TextField>(field('compose-question')).controller!.text, 'Quel thème pour la causerie ?');
    await disposeApp(tester);
  });
}
