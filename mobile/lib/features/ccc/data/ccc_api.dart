import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/providers/core_providers.dart';
import 'ccc_models.dart';

/// Lecture des outils CCC (docs/API.md §19 bis). Le serveur répond
/// `Cache-Control: no-store, private` : la lecture suit toujours l'état de
/// publication choisi par l'équipe, donc rien n'est gardé hors ligne (pas de
/// passage par `ApiCache`, qui ne garde d'ailleurs pas les réponses `no-store`).
/// `GET ccc` renvoie déjà le contenu complet de chaque outil : `GET ccc/{key}`
/// n'est pas nécessaire aux écrans.
class CccApi {
  CccApi(this._dio);
  final Dio _dio;

  Future<CccCatalog> catalog() async {
    final res = await _dio.get<dynamic>('ccc');
    return CccCatalog.fromJson(res.data);
  }
}

final cccApiProvider = Provider<CccApi>((ref) => CccApi(ref.watch(dioProvider)));
