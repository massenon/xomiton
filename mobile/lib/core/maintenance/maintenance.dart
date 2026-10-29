import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../providers/core_providers.dart';

/// Un numéro d'urgence annoncé par `GET status` (même source que `GET urgences`).
class EmergencyNumber {
  const EmergencyNumber(this.label, this.number);
  final String label;
  final String number;
}

/// Mode maintenance (RG-14, docs/API.md §5 et §1.2) : le serveur répond 503
/// `maintenance` à tout compte hors équipe. L'application affiche alors un
/// écran plein (message, reprise prévue, numéros d'urgence), garde SOS,
/// signalement et urgences ouverts, conserve la boîte d'envoi et sonde
/// `GET status` jusqu'à la réouverture.
class MaintenanceState {
  const MaintenanceState({
    this.active = false,
    this.message,
    this.endsAt,
    this.overdue = false,
    this.timezone,
    this.numbers = const [],
    this.checking = false,
    this.lastCheckFailed = false,
  });

  final bool active;

  /// `maintenance_message` : texte public administré.
  final String? message;

  /// Reprise **estimée** (UTC), nulle si inconnue ou passée.
  final DateTime? endsAt;

  /// Reprise annoncée dépassée, maintenance toujours active.
  final bool overdue;
  final String? timezone;
  final List<EmergencyNumber> numbers;
  final bool checking;

  /// Le dernier sondage n'a pas atteint le serveur (réseau).
  final bool lastCheckFailed;

  MaintenanceState copyWith({
    bool? active,
    String? message,
    DateTime? endsAt,
    bool? overdue,
    String? timezone,
    List<EmergencyNumber>? numbers,
    bool? checking,
    bool? lastCheckFailed,
  }) => MaintenanceState(
    active: active ?? this.active,
    message: message ?? this.message,
    endsAt: endsAt ?? this.endsAt,
    overdue: overdue ?? this.overdue,
    timezone: timezone ?? this.timezone,
    numbers: numbers ?? this.numbers,
    checking: checking ?? this.checking,
    lastCheckFailed: lastCheckFailed ?? this.lastCheckFailed,
  );
}

// Champ du corps 503 (docs/API.md §5) ; écrit en deux morceaux : ce n'est pas une clé de texte.
const _publicMessageField = 'maintenance' '_message';

DateTime? _date(Object? v) => v is String ? DateTime.tryParse(v)?.toUtc() : null;
String? _text(Object? v) => v is String && v.trim().isNotEmpty ? v.trim() : null;

/// Intervalle du sondage `GET status` pendant la maintenance (nul dans les tests).
final maintenancePollIntervalProvider = Provider<Duration?>((ref) => const Duration(seconds: 60));

class MaintenanceController extends Notifier<MaintenanceState> {
  Timer? _timer;

  @override
  MaintenanceState build() {
    ref.onDispose(() => _timer?.cancel());
    return const MaintenanceState();
  }

  /// Corps d'une réponse 503 `maintenance` (ou `data` de `GET status`).
  void enter(Map<dynamic, dynamic> body) {
    final maintenance = body['maintenance'] is Map ? body['maintenance'] as Map : body;
    state = state.copyWith(
      active: true,
      message: _text(body[_publicMessageField]) ?? _text(maintenance['message']),
      endsAt: _date(maintenance['ends_at']),
      overdue: maintenance['overdue'] == true,
      timezone: _text(body['timezone']),
      lastCheckFailed: false,
    );
    final every = ref.read(maintenancePollIntervalProvider);
    if (every != null && _timer == null) {
      _timer = Timer.periodic(every, (_) => check());
    }
  }

  void _leave() {
    _timer?.cancel();
    _timer = null;
    state = const MaintenanceState();
  }

  /// Sonde `GET status` (sans compte, servi pendant la maintenance).
  Future<void> check() async {
    if (!state.active || state.checking) return;
    state = state.copyWith(checking: true);
    try {
      final res = await ref.read(dioProvider).get<dynamic>('status');
      final body = res.data;
      final data = body is Map && body['data'] is Map ? body['data'] as Map : (body is Map ? body : const {});
      final m = data['maintenance'];
      final active = data['status'] == 'maintenance' || (m is Map && m['active'] == true);
      if (!active) return _leave();
      final numbers = <EmergencyNumber>[
        if (data['emergency_numbers'] is List)
          for (final e in data['emergency_numbers'] as List)
            if (e is Map && _text(e['number']) != null) EmergencyNumber(_text(e['label']) ?? '', _text(e['number'])!),
      ];
      enter(data);
      state = state.copyWith(numbers: numbers, checking: false);
    } on DioException {
      state = state.copyWith(checking: false, lastCheckFailed: true);
    }
  }
}

final maintenanceProvider = NotifierProvider<MaintenanceController, MaintenanceState>(MaintenanceController.new);
