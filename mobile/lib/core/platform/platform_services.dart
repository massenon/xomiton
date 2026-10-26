import 'package:add_2_calendar/add_2_calendar.dart' as cal;
import 'package:file_picker/file_picker.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart';
import 'package:url_launcher/url_launcher.dart';

/// Accès au téléphone, derrière des interfaces : remplaçables dans les tests,
/// et rien n'est jamais écrit dans les journaux (position, numéros).

class GeoPoint {
  const GeoPoint(this.lat, this.lng, this.accuracy);
  final double lat;
  final double lng;
  final double accuracy;
}

abstract interface class LocationService {
  /// Position actuelle, ou `null` (refus, GPS éteint, délai dépassé) : jamais d'exception.
  Future<GeoPoint?> locate({Duration timeout = const Duration(seconds: 12)});
}

class GeolocatorLocationService implements LocationService {
  @override
  Future<GeoPoint?> locate({
    Duration timeout = const Duration(seconds: 12),
  }) async {
    try {
      if (!await Geolocator.isLocationServiceEnabled()) return null;
      var perm = await Geolocator.checkPermission();
      if (perm == LocationPermission.denied) {
        perm = await Geolocator.requestPermission();
      }
      if (perm == LocationPermission.denied ||
          perm == LocationPermission.deniedForever) {
        return null;
      }
      final p = await Geolocator.getCurrentPosition(
        locationSettings: LocationSettings(
          accuracy: LocationAccuracy.high,
          timeLimit: timeout,
        ),
      ).timeout(timeout + const Duration(seconds: 2));
      return GeoPoint(p.latitude, p.longitude, p.accuracy);
    } catch (_) {
      return null;
    }
  }
}

class PickedEvidence {
  const PickedEvidence(this.name, this.path, this.size);
  final String name;
  final String path;
  final int size;
}

abstract interface class EvidencePicker {
  Future<List<PickedEvidence>> pick();
}

class FilePickerEvidence implements EvidencePicker {
  @override
  Future<List<PickedEvidence>> pick() async {
    final r = await FilePicker.pickFiles(
      allowMultiple: true,
      type: FileType.custom,
      allowedExtensions: const [
        'jpg',
        'jpeg',
        'png',
        'webp',
        'pdf',
        'mp3',
        'm4a',
        'mp4',
        'ogg',
        'oga',
        'opus',
      ],
    );
    if (r == null) return const [];
    return [
      for (final f in r.files)
        if (f.path != null) PickedEvidence(f.name, f.path!, f.size),
    ];
  }
}

abstract interface class LinkLauncher {
  /// `tel:`, `https:`… `false` si rien ne peut l'ouvrir.
  Future<bool> open(Uri uri);
}

/// Schémas ouvrables hors de l'application : `https` (jamais `http`, `file`,
/// `intent`, `javascript`…) et `tel` (numéro nettoyé).
bool isAllowedExternalUri(Uri uri) {
  if (uri.scheme == 'https') return uri.host.isNotEmpty;
  if (uri.scheme == 'tel') return RegExp(r'^\+?\d{2,20}$').hasMatch(uri.path);
  return false;
}

class UrlLinkLauncher implements LinkLauncher {
  @override
  Future<bool> open(Uri uri) async {
    if (!isAllowedExternalUri(uri)) return false;
    try {
      return await launchUrl(uri, mode: LaunchMode.externalApplication);
    } catch (_) {
      return false;
    }
  }
}

abstract interface class CalendarService {
  Future<bool> addEvent({
    required String title,
    required DateTime start,
    DateTime? end,
    String? location,
    String? description,
  });
}

class Add2CalendarService implements CalendarService {
  @override
  Future<bool> addEvent({
    required String title,
    required DateTime start,
    DateTime? end,
    String? location,
    String? description,
  }) async {
    try {
      return await cal.Add2Calendar.addEvent2Cal(
        cal.Event(
          title: title,
          description: description,
          location: location,
          startDate: start,
          endDate: end ?? start.add(const Duration(hours: 1)),
        ),
      );
    } catch (_) {
      return false;
    }
  }
}

final locationServiceProvider = Provider<LocationService>(
  (ref) => GeolocatorLocationService(),
);
final evidencePickerProvider = Provider<EvidencePicker>(
  (ref) => FilePickerEvidence(),
);
final linkLauncherProvider = Provider<LinkLauncher>((ref) => UrlLinkLauncher());
final calendarServiceProvider = Provider<CalendarService>(
  (ref) => Add2CalendarService(),
);

/// `tel:` : chiffres et « + » seulement.
Uri telUri(String number) =>
    Uri(scheme: 'tel', path: number.replaceAll(RegExp(r'[^\d+]'), ''));
