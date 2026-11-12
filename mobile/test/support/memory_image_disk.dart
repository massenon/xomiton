import 'package:xomiton/features/ccc/data/ccc_image_store.dart';

/// Disque d'images en mémoire (tests) : aucun accès au système de fichiers.
class MemoryImageDisk implements CccImageDisk {
  final Map<String, List<int>> files = {};
  bool available = true;

  @override
  Future<bool> prepare() async => available;
  @override
  Future<Map<String, int>> list() async => {for (final e in files.entries) e.key: e.value.length};
  @override
  Future<void> write(String name, List<int> bytes) async => files[name] = bytes;
  @override
  Future<void> delete(String name) async => files.remove(name);
  @override
  String? pathOf(String name) => available ? '/memory/$name' : null;
}
