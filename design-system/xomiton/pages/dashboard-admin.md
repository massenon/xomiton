# Surcharge — Tableau de bord et administration (`/admin`)

> Prime sur MASTER.md. Skill *ui-ux-pro-max* : `--density 8` (dense/dashboard : espacement 8-32 px), `--variance 2`, `--motion 2`. Contenu des vues par rôle : `xomiton_implementation/04 M11`.

**Style** : fonctionnel et calme ; surfaces blanches sur fond `--xm-bg`, bordure 1 px, ombre `sm` ; **aucun arc-en-ciel** ; le rouge n'apparaît que pour les éléments critiques (alerte SOS, cas critique, action destructive).

| Élément | Règle |
|---|---|
| Grille | 12 colonnes Bootstrap ; en-tête de page (titre `h1` Montserrat 700 + actions) ; ligne de KPI (4-5 `x-stat`) ; zone principale 8/12 + panneau latéral 4/12 ; < 992 px : une colonne, panneau latéral sous le contenu |
| Barre latérale | Groupes : Pilotage · Cas & alertes · Annuaire · Contenus · Données · Rapports · Réseau social · Système ; repliable (icônes seules + `title`) ; offcanvas < 992 px ; entrée active : fond `--xm-primary-subtle`, texte `--xm-primary-subtle-text`, `aria-current` |
| Filtres globaux | Période, département/commune, population, sexe, tranche d'âge ; barre collante sous l'en-tête ; mémorisés par utilisateur (`saved_filters`) ; bouton « Réinitialiser » |
| KPI | Valeur Montserrat 800 28-32 px, libellé 14 px, tendance flèche + texte ; **chiffre masqué** « données masquées » si cellule < 5 |
| Alertes SOS actives | Bandeau en tête, fond `--xm-secondary-subtle`, icône cloche, bouton « Accuser réception » ; son selon `x-bell` ; en mouvement réduit : bandeau fixe |
| Cartes | Leaflet, fond neutre, séquentiel monochrome bleu, légende, alternative tableau |
| Tableaux | `x-datatable` ; badge d'urgence + statut en toutes lettres ; densité compacte possible (ligne 40 px) mais cible tactile 44 px sur mobile |
| Graphiques | Chart.js, 6 teintes catégorielles max, étiquettes directes, tableau de données repliable |
| États | Vide illustré, chargement par squelettes, erreur avec action « Réessayer » |

**Tests** : table de 10 000 lignes paginée < 800 ms (p95) ; focus clavier dans la barre latérale et les tableaux ; lecteur d'écran sur les KPI (valeur + libellé + tendance lus ensemble).
