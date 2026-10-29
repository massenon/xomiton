import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:xomiton/core/storage/api_cache.dart';
import 'package:xomiton/core/storage/local_db.dart';
import 'package:xomiton/core/sync/collection_store.dart';
import 'package:xomiton/core/sync/sync_queue.dart';
import 'package:xomiton/features/pair/data/pair_api.dart';

import '../support/fakes.dart';
import '../support/harness.dart';

PairApi api(FakeApi fake) {
  final db = LocalDb.memory();
  final dio = Dio(BaseOptions(baseUrl: 'https://api.test/api/v1/'))..httpClientAdapter = fake;
  return PairApi(
    dio,
    ApiCache(dio: dio, publicBox: db.publicCache, privateBox: db.privateCache),
    CollectionStore(db.peerData),
    SyncQueue(db.peerData),
  );
}

void main() {
  test('GET pair/collect-options alimente toutes les listes de la collecte (forme de l’API §22)', () async {
    final fake = FakeApi({
      'GET pair/collect-options': (_) => jsonBody(200, {
        'data': {
          'kinds': [{'value': 'VBG', 'label': 'Violence basée sur le genre'}],
          'zones': [
            {'id': 8, 'name': 'Collines', 'communes': [{'id': 31, 'name': 'Savè'}]},
          ],
          'populations': [{'id': 2, 'label': 'Femmes cis'}],
          'sexes': [{'value': 'femme', 'label': 'Femme'}],
          'violence_types': [{'id': 4, 'label': 'Violence physique'}],
          'age_ranges': [{'value': '18-24', 'label': '18-24 ans'}],
          'referrals': ['Centre de santé'],
          'followup_steps': ['Premier entretien'],
          'notes_min_length': 20,
          'followup_outcomes': [{'value': 'realise', 'label': 'Réalisé'}],
        },
      }, headers: {'ETag': '"o1"'}),
    });
    final l = await api(fake).lists();
    expect(l.zones.single.name, 'Collines');
    expect(l.zones.single.communes.single.label, 'Savè');
    expect(l.populations.single.label, 'Femmes cis');
    expect(l.violenceTypes.single.id, 4);
    expect(l.sexes.single.value, 'femme');
    expect(l.ageRanges.single.label, '18-24 ans');
    expect(l.referrals, ['Centre de santé']);
    expect(l.followupSteps, ['Premier entretien']);
    expect(l.kinds.single.value, 'VBG');
    // Plus besoin des référentiels publics.
    expect(fake.calls('GET zones'), isEmpty);
    expect(fake.calls('GET violence-types'), isEmpty);
  });

  test('serveur sans collect-options (403 / 404) : repli sur zones et violence-types', () async {
    final fake = FakeApi({
      'GET pair/collect-options': (_) => jsonBody(403, {'message': 'x', 'code': 'forbidden'}),
      'GET zones': (_) => jsonBody(200, {
        'data': [
          {'id': 1, 'name': 'Littoral', 'communes': [{'id': 3, 'name': 'Cotonou'}]},
        ],
      }),
      'GET violence-types': (_) => jsonBody(200, {
        'data': [{'id': 5, 'label': 'Violence sexuelle'}],
      }),
    });
    final l = await api(fake).lists();
    expect(l.zones.single.name, 'Littoral');
    expect(l.violenceTypes.single.label, 'Violence sexuelle');
    expect(l.populations, isEmpty);
    expect(l.sexes, isEmpty);
  });
}
