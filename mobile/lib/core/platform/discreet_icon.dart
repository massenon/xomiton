import 'dart:io' show Platform;

import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

/// Mode discret : icône et nom neutres.
///
/// Android : deux `activity-alias` (voir AndroidManifest.xml), le natif active
/// l'un ou l'autre (« Notes »). iOS : icône alternative neutre `DiscreetIcon`
/// (Info.plist, `XomitonSecurityPlugin.swift`, NON COMPILÉ) ; le nom de
/// l'application ne peut pas être changé à l'exécution, il reste « Xomiton »,
/// et iOS affiche lui-même une alerte au changement d'icône. Même contrat de
/// canal sur les deux plateformes.
abstract interface class DiscreetIconService {
  bool get supported;
  Future<void> apply(bool discreet);
}

class ChannelDiscreetIcon implements DiscreetIconService {
  static const _channel = MethodChannel('app.xomiton/discreet');

  @override
  bool get supported => Platform.isAndroid || Platform.isIOS;

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

/// Clé du texte d'aide du réglage selon la plateforme (pur, testable).
String discreetHelpKey({required bool supported, required bool ios}) {
  if (!supported) return 'mobile_security_discreet_unsupported_help';
  return ios ? 'mobile_security_discreet_ios_help' : 'mobile_security_discreet_help';
}

final discreetIconProvider = Provider<DiscreetIconService>((_) => ChannelDiscreetIcon());
