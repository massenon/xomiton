import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/providers/core_providers.dart';
import '../../auth/application/session_controller.dart';
import 'ccc_repository.dart';

/// Lecture des outils CCC (docs/API.md §19 bis) par [CccRepository] : le serveur
/// répond `Cache-Control: no-store, private` (la publication prime), donc la
/// lecture hors ligne passe par une copie locale propre à l'application, jamais
/// par `ApiCache`. `GET ccc` renvoie le contenu complet de chaque outil.
final cccRepositoryProvider = Provider<CccRepository>((ref) {
  final Dio dio = ref.watch(dioProvider);
  final account = ref.watch(sessionProvider.select((s) => s.user?.id));
  return CccRepository(dio: dio, box: ref.watch(localDbProvider).privateCache, account: account);
});
