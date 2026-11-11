import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'app/app.dart';
import 'core/l10n/app_strings.dart';
import 'core/providers/core_providers.dart';
import 'core/push/fcm_push_service.dart';
import 'core/push/push_registrar.dart';
import 'core/push/push_service.dart';
import 'core/storage/local_db.dart';
import 'core/storage/secret_store.dart';
import 'core/storage/token_store.dart';
import 'package:hive_flutter/hive_flutter.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await Hive.initFlutter();
  await initializeDateFormatting('fr');
  final secrets = FlutterSecretStore();
  final results = await Future.wait<Object>([
    SharedPreferences.getInstance(),
    LocalDb.open(secrets),
    TokenStore.load(secrets),
    AppStrings.loadFromAsset(),
    createPushService(), // Noop si Firebase n'est pas configuré
  ]);
  runApp(ProviderScope(
    overrides: [
      secretStoreProvider.overrideWithValue(secrets),
      sharedPreferencesProvider.overrideWithValue(results[0] as SharedPreferences),
      localDbProvider.overrideWithValue(results[1] as LocalDb),
      tokenStoreProvider.overrideWithValue(results[2] as TokenStore),
      pushServiceProvider.overrideWithValue(results[4] as PushService),
    ],
    child: XomitonApp(strings: results[3] as AppStrings),
  ));
}
