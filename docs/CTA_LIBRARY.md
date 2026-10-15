---
name: xomiton-cta-library
description: Bibliothèque des appels à l'action de Xomiton — libellé, objectif, style de bouton, emplacement et page de destination, par étape du parcours (découverte, inscription, engagement, aide urgente, administration). À charger avant de placer ou renommer un bouton ou un lien d'action.
---

# Bibliothèque de CTA

Règles : un CTA principal par section (`btn-primary`), un CTA secondaire au plus (`btn-outline-*` ou `btn-primary-soft`) ; **rouge uniquement pour SOS, signaler et supprimer** ; libellé ≤ 4 mots, verbe à l'infinitif (vouvoiement public) ou à l'impératif tutoyé (app) selon [VOICE_AND_TONE.md](VOICE_AND_TONE.md).

## 1. Découverte et inscription
| Libellé | Style | Emplacement | Destination |
|---|---|---|---|
| Créer un compte | primaire | En-tête, hero, CTA final | `/inscription` |
| Se connecter | contour / lien | En-tête, hero, CTA final | `/connexion` |
| Mon espace | primaire | Hero (si connecté·e) | `/fil` |
| Rejoignez-nous maintenant | primaire | Section réseau social | `/inscription` |
| Explorer les thématiques | primaire doux | « Je me pose des questions » | `/thematiques` |
| Lire la suite | lien | Cartes de ressources | fiche |
| Lire les actualités | contour | Section ressources | `/ressources` |
| Découvrir Do Glegbe | primaire | Section Do Glegbe | `/do-glegbe` |
| Voir tout le centre d'aide | lien | FAQ | `/aide` |
| S'inscrire (newsletter) | primaire | Pied de page | double opt-in |

## 2. Aide urgente
| Libellé | Style | Emplacement | Destination |
|---|---|---|---|
| SOS avec position GPS | **danger** | Pied de page, flottant | `/sos` |
| Signaler un abus / Faire un signalement | danger doux (public) · primaire (formulaire) | Accueil, fil, sighub | `/signaler` |
| Contacts d'urgence | contour | Section alerte, colonne droite | `/urgences` |
| Discuter avec l'assistant | primaire | Section expert·es | `/assistant` |
| J'ai besoin d'aide | primaire | Fin de fiche thématique | sighub |
| Envoyer mon signalement | **primaire (bleu)** | Dernière étape du formulaire | confirmation |
| Je suis en sécurité | contour | Après un SOS | tableau de bord |

## 3. Engagement communauté
Publier · Publier le sondage · Voter · Je participe · Rejoindre · Suivre · Inviter · Partager (lien) · Commenter.

## 4. Administration
| Libellé | Style | Emplacement |
|---|---|---|
| + Nouveau template · + Nouvelle campagne · + Contact | primaire `btn-sm` | Barre supérieure (actions de page) |
| Importer CSV · Exporter CSV | contour `btn-sm` | En-tête de la carte liste |
| Filtrer | primaire doux | Barre de filtres |
| Sauvegarder maintenant | primaire `btn-sm` | Carte Sauvegardes |
| Programmer l'envoi · Envoyer maintenant · Enregistrer le brouillon | primaire (libellé selon le mode) | Pied de modale campagne |
| Traiter la file d'envoi | contour | Liste des campagnes |
| Supprimer définitivement · Restaurer | danger | Modales de confirmation |
