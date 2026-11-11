/// Liens légaux et de contact, ouverts dans le navigateur (règles de l'App Store 5.1.1 et 1.2 :
/// politique de confidentialité et moyen de contact accessibles dans l'application).
/// Adresses publiques du site, aucune donnée personnelle.
const String kSiteOrigin = 'https://xomiton.app';

class LegalLink {
  const LegalLink(this.key, this.labelKey, this.path);
  final String key;
  final String labelKey;
  final String path;

  Uri get uri => Uri.parse('$kSiteOrigin$path');
}

const List<LegalLink> kLegalLinks = [
  LegalLink('privacy', 'mobile_legal_privacy', '/politique-de-confidentialite'),
  LegalLink('terms', 'mobile_legal_terms', '/conditions-utilisation'),
  // Les mentions légales portent les coordonnées de contact de l'éditeur.
  LegalLink('contact', 'mobile_legal_contact', '/mentions-legales'),
];
