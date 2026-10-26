import 'dart:io' show Platform;

import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

/// Mode discret : icône et nom neutres (« Notes »).
///
/// Android : deux `activity-alias` (voir AndroidManifest.xml), le natif active
/// l'un ou l'autre. iOS : le nom d'une application ne peut pas être changé à
/// l'exécution et l'icône alternative n'est pas livrée (reste documenté dans
/// README) ; l'interface le dit honnêtement.
abstract interface class DiscreetIconService {
  bool get supported;
  Future<void> apply(bool discreet);
}

class ChannelDiscreetIcon implements DiscreetIconService {
  static const _channel = MethodChannel('bj.xomiton/discreet');

  @override
  bool get supported => Platform.isAndroid;

  @override
  Future<void> apply(bool discreet) async {
    if (!supported) return;
    try {
      await _channel.invokeMethod<void>('setDiscreet', {'discreet': discreet});
    } on PlatformException {
      // L'icône n'a pas pu changer : les réglages du compte restent valables.
    }
  }
}

final discreetIconProvider = Provider<DiscreetIconService>((_) => ChannelDiscreetIcon());
