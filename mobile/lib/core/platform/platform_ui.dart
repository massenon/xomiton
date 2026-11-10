import 'package:flutter/foundation.dart';

/// Adaptations de plateforme décidables sans appareil (plan iOS §3, HIG).
/// Toutes prennent la plateforme en paramètre pour être testables.

bool isIosPlatform([TargetPlatform? platform]) => (platform ?? defaultTargetPlatform) == TargetPlatform.iOS;

/// HIG : une barre d'onglets compte 5 onglets au plus sur iPhone. La barre Membre
/// en a un sixième (« Espace pair » / « Admin » / « Espaces ») pour les comptes à
/// plusieurs espaces : sur iOS il est retiré de la barre ; le changement d'espace
/// reste accessible depuis la carte « Mes espaces » du profil.
const int kIosMaxTabs = 5;

bool showOtherSpaceTab([TargetPlatform? platform]) => !isIosPlatform(platform);
