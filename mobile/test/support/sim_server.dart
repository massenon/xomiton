import 'package:dio/dio.dart';
import 'package:xomiton/features/admin/application/admin_providers.dart' show adminPollEnabledProvider;

import '../features/pair_alerts_test.dart' show alertDetail;
import '../features/pair_cases_test.dart' show caseJson;
import 'admin_harness.dart';
import 'fakes.dart';
import 'harness.dart';
import 'pair_harness.dart';

/// Une alerte du serveur simulé : accusée par l'équipe, un acteur après l'autre.
class SimAlert {
  SimAlert(this.id);
  final String id;
  String status = 'nouveau';
  final Set<String> ackBy = {};
}

/// Serveur simulé de bout en bout (adaptateur Dio) : **un seul état** partagé
/// par tous les espaces. Ce qu'un pair accuse, l'admin le voit ; un droit
/// retiré ici ferme l'espace là ; la maintenance ou une coupure touche toutes
/// les routes. Sert aux scénarios de `test/integration/`.
class SimServer {
  SimServer(this.account, {List<String> alertIds = const ['a1']}) {
    for (final id in alertIds) {
      alerts[id] = SimAlert(id);
    }
  }

  /// Corps de `GET me` / `auth/login` (`userJson`, `peerUser`, `adminUser`…).
  Map<String, dynamic> account;

  /// Coupure réseau : toute requête échoue comme en mode avion.
  bool down = false;

  /// Maintenance (RG-14) : 503 pour tout compte hors équipe, hors parcours de crise.
  bool maintenance = false;

  /// L'API `pair/*` répond 403 `forbidden` quand le droit a été retiré.
  bool peerAccess = true;

  /// Ordre d'effacement à distance annoncé par `heartbeat`.
  bool wipeRequested = false;

  final alerts = <String, SimAlert>{};
  String caseStatus = 'ouvert';

  /// Réponse de `sync/push` par élément ; défaut : `created`.
  Map<String, dynamic> Function(Map item)? pushResult;
  final pushed = <Map>[];
  final resolutions = <Map>[];
  final conflicts = <Map<String, dynamic>>[];
  final wiped = <String>[];

  bool get team => const ['admin', 'superadmin'].contains(account['role']);
  bool get hasPeer => (account['spaces'] as List?)?.contains('peer') ?? false;

  ResponseBody _maintenance() => jsonBody(503, {
    'message': 'Plateforme en maintenance. Réessayer dans quelques minutes.',
    'code': 'maintenance',
    'retry_after': 600,
    'ends_at': null,
    'overdue': false,
    'timezone': 'Africa/Porto-Novo',
    'maintenance_message': 'Mise à jour en cours.',
  });

  FakeHandler _guard(FakeHandler h, {bool crisis = false}) => (o) {
    if (down) throw offlineError(o);
    if (maintenance && !team && !crisis) return _maintenance();
    return h(o);
  };

  Map<String, dynamic> _pairRow(SimAlert a) => alertRow(a.id, status: a.status, ack: a.ackBy.contains('pair'));
  Map<String, dynamic> _pairDetail(SimAlert a) {
    final acked = a.ackBy.contains('pair');
    return alertDetail(
      a.id,
      status: a.status,
      ack: acked,
      can: {'acknowledge': !acked, 'take': true, 'close': acked, 'statuses': ['pris_en_charge', 'en_cours']},
    );
  }

  Map<String, dynamic> _adminDetail(SimAlert a) => adminAlertDetail(
    a.id,
    status: a.status,
    ack: a.ackBy.isNotEmpty,
    can: {
      // L'accusé de l'admin est le sien : celui du pair a déjà arrêté l'escalade, pas le suivi d'équipe.
      'acknowledge': !a.ackBy.contains('admin'),
      'take': true,
      'close': true,
      'reassign': true,
      'note': true,
      'statuses': ['pris_en_charge', 'en_cours', 'traite', 'clos'],
    },
  );

  Map<String, FakeHandler> routes() {
    final r = <String, FakeHandler>{
      ...pairRoutes(),
      ...adminRoutes(),
      'GET me': (_) => jsonBody(200, {'data': account}),
      'POST auth/login': (_) => jsonBody(200, {
        'data': {'token': '1|abc', 'token_type': 'Bearer', 'user': account, 'device_token': 'd', 'device_token_expires_at': 'x'},
      }),
      'GET status': (_) => jsonBody(200, {
        'data': {
          'status': maintenance ? 'maintenance' : 'ok',
          'maintenance': {'active': maintenance, 'message': null, 'ends_at': null, 'overdue': false},
          'emergency_numbers': [
            {'label': 'Police secours', 'number': '117'},
          ],
          'timezone': 'Africa/Porto-Novo',
        },
      }),
      'GET dashboard': (_) => jsonBody(200, {
        'data': {
          'tiles': [],
          'counters': {'notifications': 0, 'messages': 0},
          'profile': {'completion': 100, 'complete': true},
          'featured': {'total': 0, 'items': []},
          'next_event': null,
          'peer': hasPeer ? peerSection() : null,
        },
      }),
      'POST heartbeat': (_) => jsonBody(200, {
        'data': {
          'server_time': DateTime.now().toUtc().toIso8601String(),
          'open_conflicts': conflicts.length,
          'rules_updated_at': null,
          'wipe': wipeRequested ? {'requested_at': 'x', 'reason': 'admin'} : null,
        },
      }),
      'GET pair/alerts': (_) => !peerAccess
          ? jsonBody(403, {'message': 'Refusé', 'code': 'forbidden'})
          : jsonBody(200, {
              'data': [for (final a in alerts.values) _pairRow(a)],
              'meta': {'state': 'open', 'new': alerts.values.where((a) => !a.ackBy.contains('pair')).length, 'on_duty': false},
            }),
      'GET admin/alerts': (_) => jsonBody(200, {
        'data': [for (final a in alerts.values) adminAlertRow(a.id, status: a.status, ack: a.ackBy.isNotEmpty)],
        'meta': {'current_page': 1, 'last_page': 1, 'total': alerts.length, 'new': alerts.values.where((a) => a.ackBy.isEmpty).length},
      }),
      'GET pair/cases': (_) => jsonBody(200, {
        'data': [caseJson('VBG-0042', status: caseStatus)],
        'meta': {'current_page': 1, 'last_page': 1, 'total': 1},
      }),
      'GET pair/cases/VBG-0042': (_) => jsonBody(200, {'data': caseJson('VBG-0042', status: caseStatus)}),
      'PATCH pair/cases/VBG-0042': (o) {
        caseStatus = '${(o.data as Map)['status']}';
        return jsonBody(200, {'data': caseJson('VBG-0042', status: caseStatus)});
      },
      'POST sync/push': (o) {
        final items = ((o.data as Map)['items'] as List).cast<Map>();
        pushed.addAll(items);
        return jsonBody(200, {
          'data': {
            'items': [
              for (final i in items)
                pushResult?.call(i) ?? {'uuid': i['uuid'], 'result': 'created', 'status': 201, 'reference': 'VBG-0099'},
            ],
          },
        });
      },
      'GET sync/conflicts': (_) => jsonBody(200, {'data': conflicts, 'meta': {'open': conflicts.length}}),
      'POST sync/conflicts/42/resolve': (o) {
        resolutions.add(Map<String, dynamic>.from(o.data as Map));
        conflicts.removeWhere((c) => c['id'] == 42);
        return jsonBody(200, {
          'data': {'id': 42, 'status': 'resolu', 'resolution': (o.data as Map)['resolution']},
          'meta': {'open': conflicts.length},
        });
      },
      'POST me/devices/dev-1/wiped': (_) {
        wiped.add('dev-1');
        wipeRequested = false;
        return jsonBody(200, {'data': {'wipe': null}});
      },
    };
    for (final a in alerts.values) {
      r['GET pair/alerts/${a.id}'] = (_) => !peerAccess
          ? jsonBody(403, {'message': 'Refusé', 'code': 'forbidden'})
          : jsonBody(200, {'data': _pairDetail(a)});
      r['POST pair/alerts/${a.id}/acknowledge'] = (_) {
        if (!peerAccess) return jsonBody(403, {'message': 'Refusé', 'code': 'forbidden'});
        a.ackBy.add('pair');
        a.status = 'pris_en_charge';
        return jsonBody(200, {'data': _pairDetail(a), 'meta': {'acknowledged_now': true}});
      };
      r['GET admin/alerts/${a.id}'] = (_) => jsonBody(200, {'data': _adminDetail(a)});
      r['POST admin/alerts/${a.id}/acknowledge'] = (_) {
        a.ackBy.add('admin');
        a.status = 'pris_en_charge';
        return jsonBody(200, {'data': _adminDetail(a)});
      };
    }
    // Parcours de crise (RG-01) et état : servis pendant la maintenance.
    const crisis = {'GET status', 'POST sos', 'POST signalements', 'GET urgences', 'POST heartbeat'};
    return {for (final e in r.entries) e.key: _guard(e.value, crisis: crisis.contains(e.key))};
  }

  FakeApi api() => FakeApi(routes());
}

/// Surcharges communes aux scénarios (sondage admin coupé : pas de minuterie).
final simOverrides = [adminPollEnabledProvider.overrideWithValue(false)];
