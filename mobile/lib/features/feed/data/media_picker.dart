import 'package:file_picker/file_picker.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'compose_logic.dart';

/// Choix des images et de la vidéo, derrière une interface (remplaçable dans les tests).
/// Pas de prise de photo ni de vidéo directe : aucune dépendance « caméra » dans l'application
/// (à décider avec le client, voir docs/IMPLEMENTATION_PLAN.md P7-11).
abstract interface class MediaPicker {
  /// Au plus [max] images (le sélecteur n'impose pas de plafond : le contrôle est local).
  Future<List<ComposeImage>> pickImages();
  Future<ComposeVideo?> pickVideo();
}

class FilePickerMedia implements MediaPicker {
  @override
  Future<List<ComposeImage>> pickImages() async {
    final r = await FilePicker.platform.pickFiles(
      allowMultiple: true,
      type: FileType.custom,
      allowedExtensions: FeedLimits.imageExtensions,
    );
    if (r == null) return const [];
    return [
      for (final f in r.files)
        if (f.path != null)
          ComposeImage(path: f.path!, name: f.name, size: f.size),
    ];
  }

  @override
  Future<ComposeVideo?> pickVideo() async {
    final r = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: FeedLimits.videoExtensions,
    );
    final f = r?.files.firstOrNull;
    if (f == null || f.path == null) return null;
    return ComposeVideo(path: f.path!, name: f.name, size: f.size);
  }
}

final mediaPickerProvider = Provider<MediaPicker>((ref) => FilePickerMedia());
