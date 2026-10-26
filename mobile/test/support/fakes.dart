import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:xomiton/core/platform/platform_services.dart';
import 'package:xomiton/core/push/push_service.dart';

class FakeLocation implements LocationService {
  FakeLocation([this.point = const GeoPoint(6.3703, 2.3912, 12.4)]);
  GeoPoint? point;
  int calls = 0;
  @override
  Future<GeoPoint?> locate({
    Duration timeout = const Duration(seconds: 12),
  }) async {
    calls++;
    return point;
  }
}

class FakeLauncher implements LinkLauncher {
  final List<Uri> opened = [];
  @override
  Future<bool> open(Uri uri) async {
    opened.add(uri);
    return true;
  }
}

class FakeCalendar implements CalendarService {
  final List<Map<String, Object?>> added = [];
  @override
  Future<bool> addEvent({
    required String title,
    required DateTime start,
    DateTime? end,
    String? location,
    String? description,
  }) async {
    added.add({'title': title, 'location': location});
    return true;
  }
}

class FakePicker implements EvidencePicker {
  FakePicker(this.files);
  final List<PickedEvidence> files;
  @override
  Future<List<PickedEvidence>> pick() async => files;
}

/// Coupure réseau : le faux serveur lève cette erreur (mode avion).
DioException offlineError(RequestOptions o) => DioException(
  requestOptions: o,
  type: DioExceptionType.connectionError,
  message: 'offline',
);

/// Va vers une route de l'application en cours.
void goTo(WidgetTester tester, String location) {
  final ctx = tester.element(find.byType(Scaffold).first);
  GoRouter.of(ctx).go(location);
}

ProviderContainer containerOf(WidgetTester tester) =>
    ProviderScope.containerOf(tester.element(find.byType(Scaffold).first));

Future<void> tapVisible(WidgetTester tester, Finder f) async {
  if (f.evaluate().isEmpty) {
    await tester.scrollUntilVisible(
      f,
      300,
      scrollable: find.byType(Scrollable).first,
    );
  }
  await tester.ensureVisible(f);
  await tester.pump(const Duration(milliseconds: 300));
  await tester.tap(f);
}

/// Laisse passer les microtâches et les animations courtes.
Future<void> settle(WidgetTester tester, [int times = 6]) async {
  for (var i = 0; i < times; i++) {
    await tester.pump(const Duration(milliseconds: 100));
  }
}

Future<void> disposeApp(WidgetTester tester) async {
  await tester.pumpWidget(const SizedBox());
}

/// Service push de test : permission et jeton programmables.
class FakePush implements PushService {
  FakePush({this.grant = true, this.fcmToken = 'fcm-token-1', this.initial});
  bool grant;
  String? fcmToken;
  PushPayload? initial;
  int permissionAsks = 0;
  bool tokenDeleted = false;
  final refresh = StreamController<String>.broadcast();
  final opened = StreamController<PushPayload>.broadcast();

  @override
  bool get available => true;
  @override
  Future<bool> requestPermission() async {
    permissionAsks++;
    return grant;
  }

  @override
  Future<String?> token() async => fcmToken;
  @override
  Stream<String> get onTokenRefresh => refresh.stream;
  @override
  Stream<PushPayload> get onOpen => opened.stream;
  @override
  Future<PushPayload?> initialPayload() async => initial;
  @override
  Future<void> deleteToken() async => tokenDeleted = true;
}
