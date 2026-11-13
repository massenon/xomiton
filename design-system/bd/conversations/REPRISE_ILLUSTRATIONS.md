# Conversations en BD — illustrations (P20-11) : état et reprise

Demande du client du 13/11/2026 : générer les 60 illustrations des 12 conversations modèles. Production arrêtée le 13/11/2026 : **crédits IA Figma épuisés** après le lot 2 des BD « violences numériques » (« Insufficient Figma AI credits for this operation », remise à zéro annoncée le **14/11/2026 à 00:00 UTC**).

## État au 13/11/2026

- Fait : planche de référence des 4 personnages ajoutés (`casting-reference.webp`, nœud Figma `5:2`).
- À faire : **les 60 cases** (12 modèles × 5), aucune n'a été générée (tous les appels ont été refusés, rien n'a été débité).
- Méthode : brief de production (style de la bible, casting de `web/database/seeders/data/content/conversations_bd/_casting.json`, tiers supérieur calme pour les bulles, aucun texte ni bulle dans l'image, pas de drapeau comme icône de signalement), WebP 1200 px ≤ 100 Ko dans `web/public/assets/images/conversations-bd/<slug>/case-N.webp`, puis repositionnement des bulles sur l'image réelle dans les JSON des modèles, puis `php artisan xomiton:conversations-bd:import` (un modèle est publié dès que ses 5 images existent).

## Choix déjà fixés (à respecter à la reprise)

**Placement gauche / droite** (la première personne qui parle est à gauche) :
- M01 : case 1 Rodrigue à gauche (il parle le premier, contrairement à la scène écrite), Fifamè à droite ; cases 2 à 4 Fifamè à gauche.
- M03 : cases 2 et 4 montrent Tantie Rosine **et** Fifamè ; case 3 Tantie Rosine à gauche (bulle de cri).
- M04 : c1 Ablavi | Marc ; c2 Ablavi | Kossi ; c3 Kossi à gauche ; c4 Ablavi | Marc ; c5 Kossi | Ablavi.
- M05 : c1 et c2 Yasmine seule (haut droit libre pour les messages) ; c3 Yasmine | Ablavi ; c4 Ablavi | Yasmine ; c5 Yasmine | Ablavi ; le responsable de stage n'apparaît jamais.
- M06 : c1 agent d'accueil | Yasmine (Rachad derrière elle) ; c2 Rachad à gauche ; c3 Yasmine | agent ; c4 agent | Yasmine ; c5 Yasmine | Rachad.
- M07 : c1 collègue (femme), collègue (homme), Kossi, puis Joël ; c2 et c4 Joël | Ablavi ; c3 Ablavi | Kossi.
- M08 : c1 Sena | Yasmine ; c3 à c5 Yasmine | Sena.
- M05 c5 et M06 c5 : garder aussi une bande calme **en bas** pour le cartouche.
- M09 c4 : triangle d'alerte et bouclier à l'écran, pas de drapeau.

**Personnages secondaires** (descriptions à reprendre à l'identique) :
- Rodrigue : 17-year-old West African boy, dark brown skin, short cropped hair, Benin school uniform (khaki short-sleeved shirt and dark navy trousers), slim build, friendly ordinary face.
- Marc : man in his fifties, medium-dark brown skin, greying hair, light grey shirt and navy tie.
- Collègue de M04 : woman in her thirties, hair in a bun, orange blouse.
- Rachad : 27-year-old man, short twists, light grey t-shirt, khaki trousers.
- Agent d'accueil : man in his forties, pale green tunic, no badge.
- Collègues de M07 : woman in her thirties, dark brown skin, neat low bun, coral-orange blouse ; man in his forties, medium-dark skin, short greying hair, light grey shirt.

**À surveiller** : sur la planche de référence, Joël porte une courte barbe que sa description ne mentionne pas ; ajouter « short beard » à sa consigne pour rester cohérent avec la planche.

## Reprise

Reprise automatique programmée le 14/11/2026 à 00:15 UTC : un appel de test, puis les 60 cases par lots (M01–M03, M04–M06, M07–M09, M10–M12), contrôle visuel, conversion, repositionnement des bulles, tests `tests/Feature/ConversationsBd`, import, commit et push sur la branche de travail.
