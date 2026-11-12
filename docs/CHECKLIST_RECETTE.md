# Checklist de recette humaine

> **Décision client du 11/11/2026 : âge minimal d'inscription 18 ans ; tous les nouveaux comptes sont validés par l'administration, sans exception ni réglage.** Les recettes AUT-01, ADM-15, MOB-12 et MOB-26 sont mises à jour (âge 18, compte toujours en attente).

À remplir pendant la recette, avec le [guide de recette](GUIDE_RECETTE.md) (installation, lancement, comptes de démonstration). Une ligne = une action à faire et un résultat à constater. Dans la colonne **Statut**, remplacez `☐` par `✅` (conforme), `❌` (anomalie : la décrire en commentaire avec le gabarit du guide §11) ou `➖` (non testé, avec la raison). Les identifiants (VIS-01…) servent de référence dans les rapports d'anomalie.

Prérequis : `scripts/recette-local.sh` puis `scripts/recette-lancer.sh` ; ouvrir **http://localhost:8000** ; journal : `web/storage/logs/laravel.log`. Identifiants de démonstration : à saisir à la main (aucun bouton de préremplissage sur les écrans de connexion), listés dans le guide §5.

- Recette réalisée par : ____________   Date : ____________   Commit (`git rev-parse --short HEAD`) : ____________
- Navigateur et version : ____________   Appareil mobile : ____________

## 1. Visiteur·se (sans compte)

Navigateur en navigation privée, aucun compte connecté.

| Réf. | Action | Résultat attendu | Statut | Commentaire |
|---|---|---|---|---|
| VIS-01 | Ouvrir `/`. | Page d'accueil complète et stylée, menu, bouton SOS visible, section rouge très clair « alerte » (`/#alerte`) ; aucune erreur rouge dans la console (F12). | ☐ | |
| VIS-02 | Ouvrir `/thematiques`, puis une fiche. | 37 thématiques (29 + les 8 fiches des violences numériques, P19) ; la fiche est lisible, la FAQ s'affiche en bas. | ☐ | |
| VIS-03 | Ouvrir `/ressources`, rechercher un mot, ouvrir une ressource ; ouvrir `/actualites`. | Liste, recherche et détail fonctionnent ; badge « Hors ligne » sur certaines ressources ; 17 ressources publiées. | ☐ | |
| VIS-04 | Ouvrir `/aide`, déplier plusieurs questions. | 28 questions, réponses lisibles. | ☐ | |
| VIS-05 | Ouvrir `/urgences`. | Numéros 117, 118 et ligne d'écoute `+229 01 97 38 88 47` ; chaque numéro est un lien `tel:`. | ☐ | |
| VIS-06 | S'inscrire à `/newsletter` (e-mail `nl1@exemple.bj`, un centre d'intérêt, case de consentement). | Message « Merci ! Un e-mail de confirmation… ». | ☐ | |
| VIS-07 | Lancer `php artisan mail:flush-outbox`, chercher « Confirmez votre inscription » dans le journal, ouvrir `/newsletter/confirmer/<jeton>`, confirmer. | Abonnement actif, visible dans `/admin/newsletter` › Abonné·es. Le lien `/newsletter/desinscription/<jeton>` désabonne en un clic. | ☐ | |
| VIS-08 | Ouvrir `/confidentialite`, `/lois`, `/violences`, `/do-glegbe`. | Les pages légales et d'information s'affichent. | ☐ | |
| VIS-09 | Ouvrir `/signaler` ; type « Signaler une violence », catégorie, description, département, étapes 4 et 5 facultatives, récapitulatif, **Envoyer**. | Page de confirmation « Ton signalement est envoyé » avec un code `SIG-XXXXXXXXXX` affiché **une seule fois** (le copier). | ☐ | |
| VIS-10 | Ouvrir `/suivi`, saisir le code ; puis un code inexistant. | Statut « Nouveau » et message « Reçu… » ; code inexistant : message neutre, sans fuite d'information. | ☐ | |
| VIS-11 | Ouvrir `/n-importe-quoi` et `/thematiques/x`. | Page « Erreur 404 – Xomiton » avec rappel du SOS et des urgences. | ☐ | |
| VIS-12 | Ouvrir `/sitemap.xml`. | Réponse 404 : normal en local (indexation interdite hors production). | ☐ | |
| VIS-13 | P19 : ouvrir `/violences-numeriques` (lien « Violences numériques » du pied de page). | 6 catégories, 23 sujets : 8 avec « Lire la fiche » et « Lire la BD », 15 « Bientôt disponible » **sans lien** ; boutons Signaler, SOS, urgences, « Lire les BD ». | ☐ | |
| VIS-14 | Ouvrir une fiche depuis le carrefour (ex. « Sextorsion »). | Fil d'Ariane Accueil › Violences numériques › Sextorsion ; avertissement de contenu ; 5 sections en accordéon ; 3 questions fréquentes ; carte « La BD de cette fiche » ; **aucun** bloc « Ce que dit la loi » tant que l'administration ne l'a pas rempli. | ☐ | |
| VIS-15 | Ouvrir `/bd`, puis une BD (`/bd/vn02`). | 8 BD (jamais bd1 à bd3) ; page de la BD : avertissement, planche 1 sur 4 avec sa légende, boutons « Planche précédente / suivante », flèches ← → du clavier, compteur annoncé ; lien vers la fiche, Signaler, SOS, urgences. Sans JavaScript : les 4 planches à la suite. `Ctrl+P` : les 4 planches, sans boutons. `/bd/bd1` → 404. | ☐ | |
| VIS-16 | Partager `/bd/vn02` et `/thematiques/sextorsion` (aperçu WhatsApp ou Facebook, ou outil de débogage Open Graph). | Aperçu avec le visuel carré de partage de la BD et le titre de la page. Non vérifiable en local (adresse non publique). | ☐ | |

## 2. Inscription et connexion

Parcours commun à tous les rôles.

| Réf. | Action | Résultat attendu | Statut | Commentaire |
|---|---|---|---|---|
| AUT-01 | Ouvrir `/inscription`, remplir pseudo `recette1`, e-mail `recette1@exemple.bj`, mot de passe `Mot-de-passe-solide-2026!` avec l'âge **17** (minimum 18 ans), cocher CGU et confidentialité, valider ; recommencer avec l'âge **18**. | À 17 ans : refus « Tu dois avoir au moins 18 ans pour créer un compte. », aucun compte créé ; à 18 ans : compte créé **en attente de validation**. | ☐ | |
| AUT-02 | Recommencer en cochant la case d'âge minimal. | Arrivée sur `/inscription/profil` ; étapes profil, sécurité et consentements facultatives (« Passer ») ; e-mail « Bienvenue » dans le journal. | ☐ | |
| AUT-03 | Lire le code dans le journal, le saisir sur `/code-de-verification` ; essayer d'abord un code faux. | Code faux : message d'erreur et compteur d'essais. Bon code : compte vérifié, arrivée sur `/fil`. « Renvoyer » respecte un délai. | ☐ | |
| AUT-04 | Se déconnecter, se reconnecter ; tester « Mot de passe oublié » (`/mot-de-passe-oublie`, code du journal, `/nouveau-mot-de-passe`). | Connexion et réinitialisation réussies ; mot de passe faible refusé. | ☐ | |
| AUT-05 | Se connecter avec `pair.attente@xomiton.app` / `Pair123!`. | Connexion avec un bandeau « en attente de validation » ; compte membre seulement, pas d'accès à `/espace-pair`. | ☐ | |
| AUT-08 | `/inscription/profil` : chercher un choix « Type de membre / pair animateur·rice ». | Aucun : l'inscription publique ne crée que des membres (le rôle pair vient d'une invitation ou de l'administration). | ☐ | |
| AUT-09 | Se connecter avec `equipe@xomiton.app` / `Equipe123!` (code du journal). | Un seul login ; arrivée sur `/fil` ; les menus mènent à `/espace-pair` et `/admin` ; le réseau social reste utilisable. | ☐ | |
| AUT-06 | Enchaîner 6 connexions fausses en moins d'une minute. | Page « Trop de tentatives… Nouvelle tentative possible dans N secondes » (429). | ☐ | |
| AUT-07 | Ouvrir `/connexion`, supprimer les cookies du site (F12 › Application), envoyer le formulaire. | Page « Erreur 419 — Votre session a expiré… » sans perte de contrôle. | ☐ | |

## 3. Membre (`membre@xomiton.app`, `membre2@xomiton.app`)

Utiliser deux fenêtres privées pour les tests à deux.

| Réf. | Action | Résultat attendu | Statut | Commentaire |
|---|---|---|---|---|
| MEM-01 | Ouvrir `/fil` et publier un texte avec un thème et une visibilité. | La carte apparaît en haut, le compteur « Publications » augmente. | ☐ | |
| MEM-02 | Créer un sondage (question, deux réponses, durée) puis voter depuis le second compte. | Résultat affiché ; après l'échéance (planificateur lancé) le vote est refusé. | ☐ | |
| MEM-03 | Commenter, mettre « J'aime », partager, enregistrer (`/fil/enregistres`), ouvrir le détail `/fil/<id>`. | Chaque action est prise en compte et visible. | ☐ | |
| MEM-BD | P19 : après `php artisan xomiton:violences-numeriques:import --publish`, ouvrir `/fil` (web) et le fil de l'application ; relancer la commande. | 8 publications du compte « xomiton » (étiquette Ressource, publiques), chacune avec son texte, 4 planches (texte alternatif de chaque image lu) et le lien `/bd/vn0X` cliquable sur le web ; **aucune** notification reçue ; la seconde exécution n'ajoute rien. | ☐ | |
| MEM-04 | Publier avec « Anonyme » coché. | Le pseudo n'apparaît pas. Signaler une publication (menu ⋯) : elle arrive dans `/admin/moderation`. | ☐ | |
| MEM-05 | `/messages` › Nouvelle conversation › Message privé › `pair_chantal` ; écrire. | Message affiché ; le second compte reçoit la conversation (cloche et liste). Onglet « Non lues », recherche, groupe. | ☐ | |
| MEM-06 | `/groupes` › Créer un groupe (nom, description, Privé ou Public, zone). | Redirection vers `/groupes/<id>` et « Groupe créé » ; un autre compte peut rejoindre un groupe public. | ☐ | |
| MEM-07 | `/evenements` › Créer un événement (date obligatoire). | Listé sous « À venir » ; « Participer » depuis l'autre compte ; rappel de la veille (`events:remind`) dans le journal. | ☐ | |
| MEM-08 | `/forums` › Nouveau sujet, puis répondre depuis l'autre compte. | « Ton sujet est publié » ; la réponse apparaît. | ☐ | |
| MEM-09 | `/profil` : répondre au profil progressif (une question à la fois, « Passer ce groupe », « Plus tard »), changer l'avatar. | La jauge « Profil complété à X % » monte ; avatar mis à jour. | ☐ | |
| MEM-10 | `/parametres` › Confidentialité : changer la visibilité du profil, la zone affichée, « Trouvable dans la recherche ». | Le second compte constate le réglage (`/membres/<pseudo>`, `/recherche`). | ☐ | |
| MEM-11 | `/parametres` : changer le mot de passe, la langue, l'apparence (Clair, Sombre, Automatique), les notifications, la newsletter. | Chaque réglage est enregistré ; reconnexion avec le nouveau mot de passe. | ☐ | |
| MEM-12 | Paramètres › Sécurité : activer le **mode discret**, voir les appareils connectés, « Déconnecter tous les appareils ». | Titre d'onglet neutre, aperçus masqués ; appareils déconnectés. | ☐ | |
| MEM-13 | Paramètres › Mes données › Télécharger mes données ; lancer `queue:work --stop-when-empty` puis `mail:flush-outbox`. | Message « Ton export est en préparation… » ; lien `mes-donnees/export/` dans le journal, fichier JSON téléchargé (valable 48 h). | ☐ | |
| MEM-14 | Supprimer un compte jetable (`recette1`) : saisir `SUPPRIMER` et le mot de passe. | Déconnexion, connexion impossible ; anonymisation réelle après 30 jours (`php artisan accounts:anonymize --dry-run`). | ☐ | |
| MEM-15 | Avec `membre@xomiton.app`, ouvrir `/admin`, `/espace-pair`, `/collecte`. | Page « Erreur 403 » dans les trois cas. | ☐ | |
| MEM-16 | `/recherche?q=santé` puis `sante` ; onglets Tout, Ressources, Thématiques, Contacts, Membres. | Mêmes résultats avec et sans accent. | ☐ | |
| MEM-17 | `/assistant` : cliquer « Comment porter plainte ? ». | Réponse de la base de connaissances (aucun appel externe). | ☐ | |
| MEM-18 | `/assistant` : écrire « je veux mourir, je ne vois plus d'issue ». | Réponse de crise avec boutons SOS (position GPS), Appeler le 117 / 118, ligne d'écoute, Faire un signalement, Parler à un humain. « Garder cette conversation » est désactivé par défaut. | ☐ | |
| MEM-EMOJI | Sélecteur d'émojis (navigateur réel, Windows et téléphone). (a) Ouvrir « Créer une publication » : bouton « Ajouter un émoji » ; au clavier, Entrée ouvre le panneau, le focus arrive sur l'onglet, flèches gauche/droite changent de catégorie, Tab passe à la recherche puis à la grille, flèches déplacent dans la grille, Entrée insère, Échap ferme et rend le focus au bouton. (b) Placer le curseur au milieu du texte, insérer un émoji : il s'insère à cet endroit et le curseur reste après lui. (c) Taper « coeur » puis « cœur » : mêmes résultats. (d) Choisir une teinte de peau puis 👍 ; recharger : « Récents » en premier, teinte conservée. (e) Remplir presque jusqu'à la limite (3 000 caractères pour une publication, 1 000 pour un commentaire ou un message, 200 pour la question d'un sondage) puis insérer 🏳️‍🌈 (compte 4) : refus annoncé, rien d'inséré. (f) Même parcours dans « Créer un sondage » (question et texte), sous un commentaire et dans la messagerie. (g) Thème sombre, fenêtre à 360 px, zoom 200 % : aucun défilement horizontal de la page, cibles faciles à toucher. (h) Lecteur d'écran (NVDA ou VoiceOver) : « Émoji ajouté : … » est lu, chaque émoji est lu par son nom français. (i) Vérifier qu'aucun émoji de la liste écartée (D-EMOJI-2) n'apparaît ; noter l'aspect des drapeaux sous Windows (lettres : attendu). | Panneau utilisable au clavier et au toucher, insertion au curseur, limite respectée, aucun émoji écarté, textes en tutoiement ; non vérifié automatiquement : rendu réel, lecteur d'écran | ☐ | |
| MEM-IMG | Interface web et API (Q96, P7-10) : sur `/fil`, « Créer une publication » : choisir 1, puis 2, 3 et 4 photos en une ou plusieurs sélections ; en ajouter une 5e (refusée, dit pourquoi) ; saisir un texte alternatif sous chaque photo ; **réordonner avec les boutons « Monter » / « Descendre » au clavier** (Tab, Entrée) en écoutant l'annonce ; retirer une photo ; envoyer. Recommencer avec une photo de plus de 5 Mo, un fichier GIF, puis un texte alternatif refusé par le filtre de mots. Publier ensuite un sondage avec 2 photos. Sur la publication : cliquer ou activer au clavier une photo (visionneuse : ← / →, Échap, Tab piégé, focus rendu à la photo), à 360 px, en thème sombre, avec « réduire les animations ». Rouvrir `GET posts/{id}` : retirer une image par `remove_media` (API). Couper le réseau (mode avion) avec une photo choisie, puis envoyer. | 1 à 4 photos acceptées, ordre et textes alternatifs conservés, compteur « 2 / 4 », 5e photo refusée avec un message en tutoiement ; erreurs rattachées à la bonne photo (champ en erreur, résumé qui y mène) ; sondage avec photos en ligne ; galerie : 1 photo pleine largeur, 2 à 4 en grille, aucun défilement horizontal 360–430 px ; visionneuse annoncée « Photo 2 sur 4 » ; EXIF absent des fichiers servis ; l'ancien champ `image` fonctionne toujours ; sans réseau : message « Les photos et les vidéos ont besoin du réseau… », texte et photos gardés dans la modale, rien ne part. Non vérifié automatiquement : navigateurs et appareils réels, lecteurs d'écran (NVDA, VoiceOver, TalkBack), tactile. | ☐ | |
| MEM-VID | Interface web et API (Q96, P7-10) : « Créer une publication » → pastille « Vidéo » : choisir un MP4 (puis un WebM) de moins de 30 Mo. Constater **l'avertissement de vie privée avant l'envoi** et la case ; essayer d'envoyer sans la cocher, sans description, avec 3 caractères, puis avec 501 ; choisir un `.mov`, un fichier de plus de 30 Mo, un faux fichier renommé `.mp4` ; essayer d'ajouter des photos (champ désactivé et expliqué) et une vidéo dans un sondage (absente). Envoyer un vrai fichier proche de 30 Mo : progression annoncée, **Annuler** en cours d'envoi, puis renvoyer. Constater l'état « En attente de relecture » (badge, texte, « Retirer ma vidéo ») dans **Profil › Mes vidéos** (lien « Mes vidéos » du fil, ancre `#mes-videos`) ; avec un second compte, vérifier qu'elle n'apparaît ni dans le fil, ni dans le profil, ni dans la recherche (adresse directe : introuvable). La faire refuser en administration (motif) : badge « Vidéo refusée » et motif visibles de l'auteur·rice. La faire approuver : lecteur dans le fil (contrôles du navigateur, aucune lecture automatique, description lue par le lecteur d'écran), avance dans la vidéo. Retirer une vidéo en attente. Dépasser 5 vidéos en 24 h. Sans réseau : envoyer avec une vidéo choisie. | 202 pour une vidéo valide : message « Ta vidéo est bien reçue… », jamais ajoutée au fil ; chaque refus a son message clair (case, description, format, poids) avant tout envoi ; progression annoncée par paliers de 25 % ; annulation sans publication, vidéo et textes gardés ; 6e envoi du jour refusé avec le message du serveur ; invisible pour toute autre personne ; fichier supprimé au retrait ; sans réseau : même message que MEM-IMG, brouillon gardé. Non vérifié automatiquement : vrai envoi de 30 Mo et limites PHP de l'hébergement (`upload_max_filesize`, `post_max_size` ≥ 32 Mo), avance (`Range`) sur Safari iOS, lecture sur Android bas de gamme, lecteurs d'écran. | ☐ | |

## 4. Pair animateur·rice (`pair@xomiton.app`)

| Réf. | Action | Résultat attendu | Statut | Commentaire |
|---|---|---|---|---|
| PAI-01 | Se connecter (code du journal), ouvrir `/espace-pair` et `/espace-pair/dossiers`. | Tableau de bord (dossiers, alertes routées) ; seuls ses dossiers sont visibles. | ☐ | |
| PAI-02 | Ouvrir `/collecte`, remplir un dossier (type, violence, département, population, sexe, âge) et **Enregistrer le dossier**. | Bandeau « En ligne / Aucune fiche en attente » ; dossier créé. | ☐ | |
| PAI-03 | Même formulaire, mode **Hors connexion** activé (F12 › Réseau), enregistrer. | Bandeau « Hors ligne · 1 fiche en attente ». | ☐ | |
| PAI-04 | Repasser **En ligne**. | En quelques secondes « Aucune fiche en attente » ; le dossier apparaît dans `/admin/registres` (compte admin). | ☐ | |
| PAI-05 | Ouvrir `/admin`. | Page « Erreur 403 ». | ☐ | |
| PAI-06 | Dans l'espace pair, consulter une alerte routée vers ce compte (créer d'abord un signalement, VIS-09). | L'alerte est visible, on peut l'accuser réception ; les autres alertes restent invisibles. | ☐ | |

## 5. Modérateur·rice (`moderateur@xomiton.app`)

| Réf. | Action | Résultat attendu | Statut | Commentaire |
|---|---|---|---|---|
| MOD-01 | Se connecter (code du journal). | Accès à la modération, au réseau social et aux utilisateurs (suspension) ; pas aux registres, aux fiches ni aux paramètres. | ☐ | |
| MOD-02 | `/admin/moderation` : traiter la publication signalée en MEM-04 (confirmer ou rétablir). | La décision est enregistrée et la publication masquée ou rétablie. | ☐ | |
| MOD-03 | Consulter la liste des mots surveillés. | 7 mots listés. | ☐ | |
| MOD-04 | Ouvrir `/admin/registres` ou `/admin/parametres`. | Accès refusé (403). | ☐ | |

## 6. Administrateur·rice et super-administrateur·rice (`moderation@xomiton.app`, `admin@xomiton.app`)

Un code de vérification est demandé à chaque connexion.

| Réf. | Action | Résultat attendu | Statut | Commentaire |
|---|---|---|---|---|
| ADM-01 | Ouvrir `/admin`. | Tableau de bord : tuiles (Nouvelles alertes, SOS actifs, Comptes…), graphiques, menu complet ; Ctrl+K ouvre la recherche globale. | ☐ | |
| ADM-02 | Dans la console, taper `window.__nav = 1`, puis cliquer Alertes, Registres, Indicateurs, Maintenance, Journal, Tableau de bord. | L'URL change, `window.__nav` vaut toujours 1 (pas de rechargement), barre bleue fine en haut, une seule entrée active ; les liens « Site public », « Déconnexion » et les téléchargements rechargent la page. | ☐ | |
| ADM-03 | `/admin/alertes` : filtres, actualisation automatique (10 s), accuser réception, notes, **Exporter CSV**. | Les actions fonctionnent ; le statut changé s'affiche dans `/suivi` côté visiteur. | ☐ | |
| ADM-04 | `/admin/registres` : **Nouveau dossier** ; `/admin/fiches`. | Le récit n'apparaît jamais dans la liste. Une fiche déjà créée se modifie (bouton crayon) dans la même modale : le récit, l'auteur·e, les précisions et le signataire n'y sont jamais affichés (vide = inchangé). | ☐ | |
| ADM-05 | `/admin/utilisateurs` : filtrer par rôle, **Créer un compte**, suspendre puis réactiver, **valider** le pair en attente (`pair_en_attente`). | Le compte validé se connecte sans bandeau et garde le rôle membre en plus du rôle pair ; la matrice des permissions est en lecture seule. | ☐ | |
| ADM-14 | `/admin/utilisateurs` › ouvrir `membre@xomiton.app` › **Donner le rôle de pair** puis **Retirer le rôle de pair**. | Le compte reste membre dans les deux cas ; il reçoit une notification ; les deux actions sont dans `/admin/journal` (`roles.peer_granted`, `roles.peer_revoked`). | ☐ | |
| ADM-15 | `/admin/inscriptions` › **Inviter dans l'équipe** (rôle pair), copier le lien, l'ouvrir en navigation privée, s'inscrire (âge 18 ou plus), puis valider le compte depuis `/admin/inscriptions`. | Compte membre créé en attente, puis rôle pair ajouté à la validation par l'équipe ; le lien ne marche plus une seconde fois ; l'admin ne voit pas les rôles d'administration (super-admin seulement). | ☐ | |
| ADM-16 | Avec `membre@xomiton.app`, appeler `POST /api/v1/me/role-requests` (`Idempotency-Key`, motif court) ; répéter avec une autre clé ; puis `/admin/inscriptions` › « Demandes du rôle de pair·e » › **Approuver** (ou **Refuser** avec motif). | Première demande 201, seconde 422 `request_open` ; le rôle n'est pas donné avant la décision ; après approbation le compte est membre ET pair, notifié, `roles.request_approved` au journal ; un refus garde le compte membre et envoie le motif. | ☐ | |
| ADM-17 | S'inscrire avec `test@mailinator.com`, puis avec une adresse ordinaire ; ouvrir `/admin/inscriptions`. | Seul le premier compte est « en attente » avec le motif « Adresse e-mail jetable » ; le second est actif après confirmation de l'e-mail ; avec « Tous les nouveaux comptes » les deux attendent. | ☐ | |
| ADM-18 | Avec `equipe@xomiton.app` sur l'appli mobile (ou `PUT /api/v1/admin/members/{id}/role` avec `role: admin`). | Refusé (403) : le rôle admin ne se donne que depuis le site web par un·e super-admin. | ☐ | |
| ADM-06 | `/admin/contenu` : onglets Ressources, Thématiques, FAQ, Catégories ; **Nouvelle ressource** ; « Passer en brouillon ». | La ressource en brouillon disparaît de `/ressources`. | ☐ | |
| ADM-07 | `/admin/newsletter` › Campagnes › Nouvelle campagne : objet, corps, segment, e-mail dans « Envoyer le test ». | Toast « Test mis en file d'envoi » ; après `mail:flush-outbox`, sujet `[TEST] …` dans le journal. | ☐ | |
| ADM-08 | `/admin/sauvegardes` : **Sauvegarder maintenant**. | Archive chiffrée listée et téléchargeable ; avertissement normal « mot de passe d'archive non défini ». | ☐ | |
| ADM-09 | `/admin/parametres` : saisir une valeur hors bornes ; `/admin/journal` : consulter, filtrer, exporter. | Valeur refusée ; le journal montre vos actions (`auth.otp_verified`, `alerts.sos_triggered`…). | ☐ | |
| ADM-10 | `/admin/emails` : ouvrir plusieurs modèles, voir l'aperçu, essai d'envoi. | Aperçu dans le gabarit premium, essai visible dans le journal. | ☐ | |
| ADM-BD | P19 : `/admin/ccc` › BD SSR : interrupteur « Page publique » d'une BD `vn0X` puis de `bd1` ; `/admin/contenu` › Thématiques › « Sextorsion » : remplir « Références juridiques », enregistrer, ouvrir la fiche, puis relancer `php artisan xomiton:violences-numeriques:import`. | BD rendue privée → `/bd/vn0X` en 404, rendue publique → page servie ; références affichées sous « Ce que dit la loi » avec « Informations validées par l’administration de Xomiton. », HTML nettoyé ; l'import ne les efface pas. | ☐ | |
| ADM-11 | `/admin/passerelles`. | Passerelles sans clés : « publication manuelle » (aucune publication réelle). | ☐ | |
| ADM-12 | `/admin/maintenance` : activer (motif, durée, case « Je confirme »), vérifier en navigation privée, puis **désactiver**. | Voir la section « Mode maintenance » ci-dessous. | ☐ | |
| ADM-13 | Comparer avec `moderation@xomiton.app` (admin) : essayer ce qui est réservé au super-admin (restaurer ou supprimer une sauvegarde, rôles d'administration). | Actions réservées refusées ou masquées pour l'admin. | ☐ | |
| ADM-VID | `/admin/moderation/videos` (« Vidéos à relire », compte `moderateur`) : lire une vidéo envoyée par un membre, **Approuver** ; en relire une autre et **Refuser** avec le motif « Vie privée » et un texte libre ; essayer « Autre » sans texte ; ouvrir l'écran avec un compte membre ou pair ; essayer de relire sa propre vidéo. | Lecteur natif utilisable au clavier, description lue ; approbation : vidéo en ligne et message « Ta vidéo a été publiée » chez l'auteur·rice ; refus : motif et texte reçus par l'auteur·rice, fichier supprimé après 7 jours (`php artisan videos:purge-rejected`) ; motif obligatoire ; 403 pour un membre ou un pair ; sa propre vidéo ne se relit pas ; cloche « Vidéo à relire » à chaque envoi ; journal d'audit sans contenu. Non vérifié automatiquement : lecteur d'écran, rendu réel à 4 largeurs. | ☐ | |

### Mode maintenance : détail de ADM-12

| Réf. | Action | Résultat attendu | Statut | Commentaire |
|---|---|---|---|---|
| MNT-01 | Activer la maintenance (motif interne, durée, case « Je confirme », **Activer maintenant**). | Bandeau rouge « Mode maintenance actif… » et décompte. | ☐ | |
| MNT-02 | En navigation privée (visiteur·se) : ouvrir `/sos`, `/signaler`, `/suivi`, `/urgences`, `/connexion`, `/newsletter`, `/up`. | Toutes répondent normalement (200) ; un SOS et un signalement envoyés pendant la maintenance partent. | ☐ | |
| MNT-03 | En navigation privée : ouvrir `/`, `/inscription`, `/thematiques`, `/aide`, `/fil`, `/admin`. | Page « Maintenance en cours — Une pause pour mieux vous accueillir » (503), rappel SOS / Signaler / Urgences, compte à rebours. | ☐ | |
| MNT-04 | Les membres connectés sont-ils déconnectés ? L'admin garde-t-il `/admin` et `/fil` ? « Voir comme un visiteur ». | Membres déconnectés ; admin conservé ; aperçu fidèle. | ☐ | |
| MNT-05 | **Désactiver** (bouton vert) et rouvrir `/`. | `/` redevient accessible (200). Planifier aussi une fenêtre (début, fin, annonce préalable) : elle s'applique à l'heure. | ☐ | |

## 7. SOS et alerte

Planificateur lancé (`schedule:work`) pour l'escalade.

| Réf. | Action | Résultat attendu | Statut | Commentaire |
|---|---|---|---|---|
| SOS-01 | Ouvrir `/sos` et maintenir le bouton rouge 2 secondes (souris ou doigt). | Décompte « Envoi de ton alerte dans 5… Tu peux encore annuler ». | ☐ | |
| SOS-02 | Pendant le décompte, cliquer **Annuler l'alerte**. | « Alerte annulée » ; `Alert::count()` inchangé ; aucune ligne `alerts.dispatched`. | ☐ | |
| SOS-03 | Recommencer et ne rien toucher ; autoriser puis refuser le partage de position. | Attente de la position **8 secondes au maximum**, puis « Ton alerte est partie » (avec ou sans position), boutons Appeler le 117 et Je suis en sécurité, annulation encore possible environ une minute. | ☐ | |
| SOS-04 | Sans maintien : double clic ou Entrée sur le bouton, puis confirmation. | L'alerte part de la même façon (accessibilité clavier). | ☐ | |
| SOS-05 | Lire le journal : `grep -a "alerts\.\\|sms:log" web/storage/logs/laravel.log \\| tail`. | `alerts.sos_created`, `alerts.dispatched … sos_triggered`, `[sms:log]`. | ☐ | |
| SOS-06 | Compte admin : `/admin`. | Bandeau rouge « 1 alerte SOS active » et tuile « SOS actifs ». Le bouton **Accuser réception** fait disparaître le bandeau. | ☐ | |
| SOS-07 | Laisser un SOS sans accusé 5 minutes (planificateur lancé). | Ligne `alerts.dispatched … sos_unacknowledged` dans le journal (escalade nationale : tous les admins et tous les pair·es, au-delà des admins de garde et des pair·es de la zone prévenus à l'envoi, D-MOB-10). L'accusé d'un destinataire avant 5 minutes empêche l'escalade. | ☐ | |
| SOS-08 | Connecté, utiliser le bouton rond flottant SOS. | Envoi immédiat, sans attente de position, relié au compte. | ☐ | |
| SOS-09 | `/signaler` hors ligne (F12 › Réseau › Hors connexion), puis retour en ligne. | L'envoi part au retour du réseau et affiche son code de suivi. | ☐ | |

## 8. E-mails

Lecture dans `web/storage/logs/laravel.log` (mode journal).

| Réf. | Action | Résultat attendu | Statut | Commentaire |
|---|---|---|---|---|
| EML-01 | Demander un code de connexion. | E-mail « Ton code de vérification Xomiton » dans le journal (objet sans code ni donnée personnelle) et ligne `[otp:log] Code NNNNNN pour …` (local). | ☐ | |
| EML-02 | S'inscrire (AUT-02). | E-mail « Bienvenue » reçu. | ☐ | |
| EML-03 | Mot de passe oublié. | E-mail avec le code et le lien de réinitialisation. | ☐ | |
| EML-04 | Confirmation de newsletter (VIS-07). | E-mail « Confirmez votre inscription » après `mail:flush-outbox`. | ☐ | |
| EML-05 | Aperçu des 12 modèles dans `/admin/emails`. | Design premium cohérent : en-tête, bouton, pied de page, lien de désinscription pour la newsletter. | ☐ | |
| EML-06 | Relire les textes des e-mails. | Tutoiement et ton conformes à [VOICE_AND_TONE.md](VOICE_AND_TONE.md) ; aucun mot de passe en clair. | ☐ | |

## 9. Notifications (cloche, push, SMS)

En mode journal, rien n'est envoyé vraiment.

| Réf. | Action | Résultat attendu | Statut | Commentaire |
|---|---|---|---|---|
| NOT-01 | Recevoir un message privé (MEM-05). | Pastille sur la cloche, notification dans la liste, marquée lue à l'ouverture. | ☐ | |
| NOT-02 | Déclencher un SOS (SOS-03). | Lignes `[sms:log]` et `[webpush:log]` / `[fcm:log]` dans le journal ; aucun envoi réel. | ☐ | |
| NOT-03 | `php artisan notifications:test-sms +229XXXXXXXX --yes`. | « Pilote SMS configuré : log » puis « Envoi accepté par le pilote » ; ligne `[sms:log]`, numéro masqué. | ☐ | |
| NOT-04 | `php artisan notifications:test-push JETON --urgent --yes`. | « Pilote FCM configuré : log » ; ligne `[fcm:log]`. | ☐ | |
| NOT-05 | `php artisan assistant:test-llm --yes`. | Refus explicite tant que `ASSISTANT_LLM_ENABLED` est faux. | ☐ | |
| NOT-06 | (Facultatif, avec vos clés) activer FCM, Vonage ou Twilio (guide §8) et relancer NOT-03 et NOT-04. | SMS et notification reçus sur votre appareil dans la minute. | ☐ | |
| NOT-07 | Paramètres › Notifications : décocher un canal pour un thème. | Le réglage est conservé. Le résumé quotidien ou hebdomadaire n'a pas d'effet (voir la section 14). | ☐ | |

## 10. Mobile (Flutter, compte `membre@xomiton.app`)

Émulateur Android ou téléphone ; API `http://10.0.2.2:8000/api/v1` par défaut. Non vérifié sur appareil à ce jour.

| Réf. | Action | Résultat attendu | Statut | Commentaire |
|---|---|---|---|---|
| MOB-01 | `flutter pub get`, `flutter analyze`, `flutter test`. | « No issues found! » et « All tests passed! » (454 tests au 2026-10-29, dont 13 scénarios de bout en bout dans `test/integration/`). | ☐ | |
| MOB-02 | `flutter run` : parcourir le carrousel plein écran (photos, Suivant, balayage, points, Passer) ; sur la 6e diapositive, « Créer un compte » et « Se connecter ». | 6 diapositives aux textes validés le 29/10/2026 (Un espace à toi · Ta parole compte · En sécurité · Une communauté qui te comprend · Des réponses fiables · Ta vie privée d'abord), une photo chacune, titres en tu lisibles sur la photo (les deux visuels basse résolution en bandeau fondu, sans pixels visibles) ; « Passer » et « Se connecter » mènent à la connexion, « Créer un compte » à l'inscription. | ☐ | |
| MOB-03 | Créer un compte (avec la case d'âge minimal), saisir le code du journal. | Arrivée sur l'accueil (tuiles Apprendre, Signaler, Urgences, Assistant). | ☐ | |
| MOB-04 | Se connecter, se déconnecter, se reconnecter ; tester le mot de passe oublié. | Connexion avec code si nouvel appareil. | ☐ | |
| MOB-05 | Onglet SOS : maintenir, annuler, puis envoyer ; refuser la position. | Même comportement que le web ; l'alerte part sans position ; ligne visible dans `/admin/alertes`. | ☐ | |
| MOB-06 | Signalement anonyme puis `/suivi` ; répéter sans réseau. | Code de suivi affiché ; sans réseau l'envoi est mis en file et part au retour. | ☐ | |
| MOB-07 | Fil : lire, publier, commenter, « J'aime » ; Messages : écrire depuis le web et le mobile. | Les contenus se retrouvent des deux côtés. | ☐ | |
| MOB-08 | Profil, profil progressif, paramètres, sécurité (mode discret, masquage des aperçus, appareils), suppression de compte. | Chaque écran répond ; mode discret : nom « Notes » du lanceur Android (quelques secondes). | ☐ | |
| MOB-09 | Sécurité › activer le code PIN (4 chiffres), fermer et rouvrir l'application ; saisir 5 PIN faux. | PIN demandé ; au 5e échec, session fermée (reconnexion mot de passe + code). SOS, urgences et signalement restent accessibles sans PIN. | ☐ | |
| MOB-10 | Ouvrir les maquettes `prototype_mobile/espace-pair.html` et `admin-mobile.html` (aussi depuis le menu P1 à P11 et A1 à A7 de `index.html`). | Les deux maquettes s'affichent, sans bascule de thème ; à valider par la coordination (elles ne sont pas encore dans l'application). | ☐ | |
| MOB-11 | Ouvrir `prototype_mobile/index.html` : bouton ☰ (replier, rouvrir, recharger), replier un module, ouvrir un écran en moins de 10 s ; chercher une bascule de thème. | Menu mémorisé après rechargement ; 30 écrans numérotés 01 à 30 + Espace pair et Admin mobile ; thème clair, aucune bascule de thème. | ☐ | |
| MOB-12 | Prototype, écran Inscription (`#/signup`) : prototype historique (non modifié, règle d'origine de 15 ans) : sans objet pour la recette ; la règle de 18 ans se teste sur AUT-01 et dans l'application. | Sans objet. | ☐ | |
| MOB-13 | Prototype, `#/postpublication` : saisir un long texte. | Le champ occupe la largeur à côté de l'avatar, hauteur minimale 120 px, croît sans déborder du châssis. | ☐ | |
| MOB-14 | Connexion avec `membre@`, `pair@`, `admin@xomiton.app` (appareil neuf), **même écran de connexion pour tous**. Code livré le 2026-10-29 (P15-10) : recette sur appareil à faire. | `membre@` arrive au tableau de bord ; `pair@` et `admin@` voient le **sélecteur d'espace** (Membre + Pair, Membre + Admin) puis changent d'espace par la pastille d'en-tête, Profil › Mes espaces ou l'onglet « Espace pair » / « Admin » ; `pair.attente@` voit le bandeau, pas d'espace Pair. | ☐ | Pages Pair (P15-13 à P15-15) et Admin (P15-17, P15-18, P15-24) livrées. |
| MOB-24 | Retirer `admin_mobile.access` (ou le rôle pair) au compte pendant qu'il est connecté, puis ouvrir un écran de son espace. Partie client livrée le 2026-10-29. | Prochaine requête 403 : `GET me` relu, espace retiré, message « Cet espace n'est plus disponible pour ce compte », retour à l'accueil Membre, aucune donnée résiduelle. | ☐ | Test automatique : `test/features/spaces_test.dart`. |
| MOB-15 | Espace pair : tenter une capture d'écran sur `/pair/dossiers`, sur un écran admin, sur le fil, sur Messages. Code livré le 2026-10-29 (P15-11) : recette sur appareil à faire. | Impossible sur Pair, Admin, Messages, signalement, SOS et Sécurité (Android : `FLAG_SECURE`) ; possible sur le fil et l'accueil ; aperçu masqué dans le sélecteur d'applications ; iPhone : recouvrement opaque en arrière-plan (limite documentée, pas de blocage de capture). | ☐ | Build de recette : `ALLOW_SCREENSHOTS=true` (ignoré en release). |
| MOB-16 | Premier accès à l'espace Pair ou Admin : choix du code obligatoire ; quitter l'application 45 s puis 61 s (Pair), 31 s (Admin) ; saisir 5 codes faux. Code livré le 2026-10-29 (P15-11). | Écran « Un code pour protéger cet espace » avant toute donnée ; code redemandé après 60 s (Pair) et 30 s (Admin) ; au 5e échec, session fermée et données Pair absentes à la reconnexion ; le code ne peut pas être désactivé dans Sécurité ; SOS, urgences et signalement accessibles sans code. | ☐ | |
| MOB-26 | Inscription publique, puis inscription par lien d'invitation de pair (`/signup/XM-…`) et par code collé. Code livré le 2026-10-29 (P15-10). | L'inscription publique ne propose que « membre » (âge de 18 ans minimum exigé) ; l'invitation suit les mêmes écrans, avec bandeau et code prérempli ; un code invalide ou déjà utilisé affiche le message du serveur sous le champ ; le compte arrive ensuite dans « Inscriptions à valider ». | ☐ | La demande du rôle pair depuis Profil › Mes espaces (D-MOB-15) est à tester ici aussi. |
| MOB-28 | Espace pair, onglet Alertes : recevoir une alerte routée (SOS de test) ; ouvrir le détail, accuser réception, prendre en charge, clore ; ouvrir la position. Code livré le 2026-10-29 (P15-13) : recette sur appareil à faire. | Liste relue toute seule (10 s de garde, 30 s sinon) ; boutons selon les droits du serveur ; contact visible après l'accusé ; l'escalade s'arrête (`/admin/alertes`) ; **avertissement avant d'ouvrir Maps** ; alerte d'un autre pair = écran « n'existe plus ». | ☐ | Tests : `test/features/pair_alerts_test.dart`. |
| MOB-29 | Espace pair, Dossiers : rechercher un code, filtrer, ouvrir une fiche (5 sections, frise), ajouter un suivi (réseau coupé, puis rétabli), changer le statut, marquer une étape réalisée. Code livré le 2026-10-29 (P15-14). | Liste identique au web (mêmes filtres), notes jamais en liste ; suivi « En file » puis confirmé sans doublon ; dossier d'un autre pair introuvable (404 neutre) ; fiche archivée en lecture seule. | ☐ | Tests : `test/features/pair_cases_test.dart`. |
| MOB-18 | Pair : mode avion, collecte complète d'un dossier (5 étapes), puis réseau ; répéter l'envoi à la main. Code livré le 2026-10-29 (P15-15). | « Fiche en file sur cet appareil » ; au retour du réseau le dossier est créé avec son code `VBG-…` / `VDH-…`, **un seul** dossier côté serveur même après un rejeu ; avertissement de plausibilité (date de plus de 5 ans, doublon) affiché sans bloquer ; refus du serveur = « À corriger » avec l'erreur sur le champ. | ☐ | Tests : `test/features/pair_collect_test.dart`. |
| MOB-19 | Deux appareils modifient le même champ d'un dossier hors ligne, puis se reconnectent. Code livré le 2026-10-29 (P15-15). | Conflit visible sur la file de synchronisation ; le pair ouvre l'écran, **choisit** « valeur du serveur » ou « mon appareil », confirme ; la valeur retenue est appliquée tout de suite sans validation par l'équipe ; l'entrée passe à « Tranché par toi ». | ☐ | Serveur : `POST sync/conflicts/{id}/resolve`. Tests : `test/features/pair_sync_test.dart`. |
| MOB-20 | Un·e admin désactive une règle de validation (ex. notes de 20 caractères) ; le pair rouvre la collecte avec du réseau. Moteur livré le 2026-10-29 (P15-12). | Règle appliquée après le prochain contact (`rules_updated_at` du signal de vie) ; sans réseau, la copie chiffrée sert ; un refus du serveur sur une règle changée entre-temps revient en « À corriger ». | ☐ | |
| MOB-30 | Pair : « Effacer à distance » depuis le web (Hors connexion & synchro), puis ouvrir l'application ; et retirer le rôle pair. Moteur livré le 2026-10-29 (P15-12). | Au prochain signal de vie : accusé envoyé, coffre du pair vidé (clé détruite), retour à la connexion ; un autre compte sur le même téléphone ne voit rien du précédent. | ☐ | Recoupe MOB-23. |
| MOB-31 | Admin : un SOS de test est créé ; ouvrir le tableau d'astreinte, observer le bandeau, accuser réception, ouvrir le détail. Code livré le 2026-10-29 (P15-17). | Le SOS apparaît en moins de 15 s (relecture 10 s) ; compte à rebours d'escalade (5 min) puis « Escalade arrêtée » après l'accusé (vérifier `/admin/alertes` côté web : escalade stoppée, ligne d'audit `source=mobile`) ; position **arrondie** (~1 km) ; **avertissement avant Maps** ; personnes prévenues avec bouton d'appel ; aucune donnée admin gardée sur le téléphone (réglages Android : le stockage de l'application ne grossit pas). | ☐ | Tests : `test/features/admin_alerts_test.dart`. |
| MOB-32 | Admin : mode avion sur le tableau d'astreinte, puis tenter d'accuser réception, de réattribuer ; réattribuer avec un mauvais puis le bon mot de passe ; attendre 5 min avant de valider une action à fort impact. | Bandeau « Hors connexion… appelez directement la personne » ; actions grisées ou refusées avec message clair, aucune requête d'écriture ; mot de passe faux = erreur dans la boîte, rien n'est fait ; jeton expiré ou déjà servi = « Recommencez l'action ». | ☐ | |
| MOB-33 | Admin › Gérer : valider une inscription signalée, rejeter une demande de rôle avec motif, inviter un·e pair (copier le code et le lien, l'utiliser pour s'inscrire), révoquer une invitation, donner puis retirer le rôle pair, tenter de trouver un moyen de donner le rôle admin, masquer / conserver / avertir sur un contenu signalé, activer puis désactiver la maintenance. Code livré le 2026-10-29 (P15-18, P15-24). | Chaque action à fort impact redemande le mot de passe ; code et lien affichés **une seule fois** ; aucune option de rôle admin (texte « uniquement depuis le site web ») ; retrait du rôle pair : l'espace pair se ferme à la prochaine relecture et l'appareil est effacé ; aucun message privé dans la modération ; maintenance : le public est bloqué, SOS et signalement restent ouverts (RG-14). | ☐ | Tests : `test/features/admin_manage_test.dart`. |
| MOB-34 | Push d'équipe sur appareil réel (application fermée, en arrière-plan, au premier plan) : SOS de test (admin de garde, pair de la zone), inscription à valider, contenu signalé ; avec mode discret (aperçus masqués) puis sans. Code livré le 2026-10-29 (P15-19). | Notification sur le canal Android **« Astreinte »** pour les alertes ; toucher ouvre le bon écran **après le code PIN** et bascule d'espace ; aperçus masqués : « Xomiton » et « Alerte urgente » / « Nouvelle alerte » / « Nouvelle demande » / « Signalement à modérer » (jamais SOS, lieu ni motif) ; un compte sans l'espace n'ouvre rien ; modification d'une règle ou d'un dossier côté web : le pair se resynchronise sans notification visible ; SOS reçu en moins de 30 s sur 4G. | ☐ | Tests : `test/core/push_team_test.dart`. iOS : APNs à faire. |
| MOB-35 | Mode maintenance : un·e admin active la maintenance sur le web ; sur un compte membre ouvert, ouvrir l'application ; toucher SOS, Signaler, Contacts d'urgence ; attendre ou toucher « Vérifier maintenant » ; désactiver la maintenance ; se connecter avec un compte admin pendant la maintenance. Code livré le 2026-10-29 (P15-23) : recette sur appareil à faire. | Écran « Maintenance en cours » avec le message public et la reprise prévue (jamais une heure passée) ; SOS, signalement et urgences s'ouvrent ; les numéros d'urgence du serveur s'affichent ; à la réouverture l'écran se retire seul et l'accueil revient, la boîte d'envoi part ; l'admin entre et travaille normalement. | ☐ | |
| MOB-36 | Hors ligne après une première connexion (collections tirées) : mode avion, ouvrir Contacts d'urgence, Thématiques, une fiche thématique ; puis Paramètres › Newsletter (abonner, choisir un centre d'intérêt, adresse non confirmée). Code livré le 2026-10-29 (P15-23). | Urgences et thématiques lisibles sans réseau avec l'avis « enregistré sur l'appareil » ; Newsletter : abonnement actif ou message de confirmation d'adresse, refus 422 affiché tel quel ; aucune tuile « bientôt ». | ☐ | |
| MOB-37 | Android release (humain) : suivre `docs/BUILD_ANDROID_WINDOWS.md` (Flutter, SDK, `flutter doctor`, émulateur ou téléphone, `flutter run`, `flutter build apk --debug`) ; créer la clé et `key.properties` ; `flutter build appbundle --release` ; ouvrir le manifeste fusionné ; installer sur Android 8, 10 et 14 (installation propre puis mise à jour). Configuration livrée le 2026-10-29 (P15-22), jamais construite à ce jour. | Build sans erreur ; permissions du manifeste fusionné = INTERNET, ACCESS_NETWORK_STATE, localisation, POST_NOTIFICATIONS, VIBRATE ; nom « Xomiton », icône adaptative bleue, écran de démarrage clair et sombre ; HTTPS seul en release ; captures impossibles sur les écrans sensibles ; la CI (analyse, tests, APK debug, budget de taille) est verte. | ☐ | |
| MOB-38 | Outils CCC (membre `membre@xomiton.app` puis pair `pair@xomiton.app`) : Profil › « Outils CCC interactifs » (pair : raccourci du tableau de bord et tâche « Préparer la prochaine séance »). Parcourir les 4 onglets ; Manuel : ouvrir un module, cocher des points, revenir, fermer et rouvrir l'application ; Boîtes à images : « Mode animation » (glisser et boutons, fermer) ; BD : lire jusqu'à « Terminer » ; Lois : ouvrir la page. Lecture hors ligne : voir MOB-39. Code livré le 11/11/2026 : recette sur appareil à faire. | Progression par module et globale conservée sur le téléphone (jamais envoyée), propre à chaque compte ; mode animation en plein écran, barres système de retour à la fermeture ; la consigne de l'animateur·rice n'apparaît que chez le pair ; un outil masqué dans `/admin/ccc` disparaît à la réouverture ; sans réseau et sans copie enregistrée : message d'erreur avec « Réessayer ». Avec TalkBack : cases annoncées « Point vu : … » cochées ou non, progression annoncée à chaque changement, texte alternatif de chaque planche, « Image n sur N » annoncé. | ☐ | |
| MOB-39 | Outils CCC hors ligne (D-MOB-19), appareil réel. (a) Connecté, ouvrir `/ccc` (copie enregistrée), onglet « Boîtes à images » : « Télécharger pour lire hors ligne », attendre la fin (essayer aussi « Annuler le téléchargement » en cours). (b) **Mode avion** : rouvrir `/ccc` : bandeau « Hors ligne · Contenu enregistré le … » + « Actualiser » ; manuel, cocher des points ; « Mode animation » plein écran (glisser, boutons) ; lecteur de BD ; indicateur « Disponible hors ligne » sur les outils dont toutes les images sont enregistrées ; toucher « Actualiser » en restant hors ligne (la copie reste). (c) Réseau rétabli, « Actualiser » : le bandeau disparaît. (d) **Outil masqué** : sur le web, `/admin/ccc`, masquer une boîte à images ou une BD ; dans l'application, « Actualiser » (ou rouvrir `/ccc`) avec réseau : l'outil disparaît ; couper le réseau et rouvrir : il ne revient pas. (e) **Droit retiré** : retirer `ccc_tools.view` au compte, rouvrir avec réseau (accès refusé), couper le réseau, rouvrir : toujours rien. (f) **Déconnexion** : se déconnecter, mode avion, se reconnecter avec un autre compte impossible sans réseau ; vérifier qu'après reconnexion le premier compte ne voit pas de copie tant qu'il n'a pas synchronisé. (g) Copie de plus de 30 jours (avancer l'horloge du téléphone de 31 jours, mode avion) : message « La copie enregistrée date de plus de 30 jours… » et « Réessayer », sans contenu. Avec TalkBack / VoiceOver : bandeau annoncé à l'ouverture, « Actualiser » atteignable seul, progression du téléchargement annoncée par paliers (zone live), « Disponible hors ligne » lu avec le titre de l'outil. | Hors ligne, tout le kit déjà synchronisé se lit (manuel, animation, BD, images enregistrées), date de synchro visible, jamais seulement la couleur ; un outil masqué disparaît dès la synchro réussie suivante et ne revient pas hors ligne ; accès retiré ou déconnexion = plus aucune copie ; copie périmée non servie ; aucun téléchargement d'images sans appui sur le bouton. | ☐ | |
| MOB-EMOJI | Sélecteur d'émojis dans l'application, appareil réel (TalkBack et VoiceOver). Créer une publication, un sondage (question et texte), un commentaire et un message : bouton « Ajouter un émoji » (48 dp), feuille avec onglets, recherche (« coeur » = « cœur »), récents, teinte de peau ; insertion à la position du curseur, curseur après l'émoji ; texte presque plein puis 🏳️‍🌈 : refus annoncé ; taille de police du système au maximum : grille agrandie sans texte coupé ; thème sombre ; TalkBack lit le nom français de chaque émoji et « Émoji ajouté : … ». | Idem web ; non vérifié : appareil réel | ☐ | |
| MOB-POLL | Sondage dans l'application, appareil réel (TalkBack et VoiceOver). Fil › Créer › « Sondage » : question de 4 caractères puis « Publier le sondage » : erreur annoncée sous la question ; une seule réponse : erreur ; « Santé » et « sante » : refus « Deux réponses… identiques » ; ajouter jusqu'à 6 réponses (annonce « Réponse N ajoutée… », bouton masqué au maximum), retirer une réponse (bouton « Retirer la réponse N », annonce) ; choix multiple, « Afficher les résultats avant le vote », durée « Sans date de fin » ; publier : le sondage apparaît dans le fil et se vote. Avec 2 images : mêmes champs, images jointes. **Mode avion** : un sondage sans image part en file d'attente (« Publication enregistrée hors connexion »), puis en ligne au retour du réseau, sans doublon ; avec une image : message « Pas de réseau », brouillon conservé. Compte sans `polls.create` : 403 lisible |
| MOB-IMG | Images multiples, appareil réel. Publication : « Ajouter des images », en choisir 6 d'un coup : 4 gardées et message « Tu ne peux joindre que 4 images », bouton grisé à « 4 / 4 » ; vignettes avec aperçu, texte alternatif par image (TalkBack lit « Description de l'image 2 »), **Monter / Descendre** (annonce « Image déplacée : elle est maintenant en position 2 sur 4 », boutons grisés aux extrémités), **Retirer** (annonce et compteur) ; un PDF ou une image de plus de 5 Mo : erreur sous l'image concernée, rien d'envoyé ; publier : les images apparaissent dans l'ordre choisi. Fil et détail : 1 image pleine largeur, 2 à 4 en grille ; TalkBack lit le texte alternatif (ou « Image 2 sur 4, sans description ») et « Ouvrir en plein écran » ; plein écran : glisser, boutons Précédente / Suivante, compteur « 2 / 4 » annoncé, pincer pour zoomer, texte alternatif sous l'image, Fermer. **Mode avion** avec images : message clair, brouillon conservé, rien en file ; coupure réseau pendant l'envoi puis nouvel envoi : une seule publication (même clé d'idempotence) |
| MOB-VID | Vidéo, appareil réel (Android et iPhone) et vrai serveur. Publication › « Choisir une vidéo » (mp4 ou webm ; un .mov ou un .avi est refusé) : nom et poids affichés, avertissement de vie privée visible ; « Choisir » désactivé si des images sont jointes (et l'inverse), absent d'un sondage ; description vide : erreur ; vidéo de plus de 30 Mo : erreur locale ; « Publier » : **boîte d'avertissement**, Annuler n'envoie rien, Confirmer envoie ; **progression annoncée** (25, 50, 75, 100 %), « Annuler l'envoi » arrête et garde le brouillon ; fin : « Ta vidéo est bien reçue », rien n'apparaît dans le fil. 6e vidéo du jour : message de limite (429). Fil › icône « Mes vidéos » : la vidéo « En attente de relecture » (aide, pas de J'aime ni commentaire), lecture sans attendre (propriétaire) ; le modérateur la refuse avec un motif : badge « Vidéo refusée » et motif ; « Retirer » : confirmation puis disparition ; le modérateur approuve une autre : elle apparaît dans le fil pour les autres. Lecteur : aucune lecture automatique ; « Lire la vidéo », pause, curseur de position (TalkBack lit « 0 min 30 s sur 1 min 30 s »), plein écran, description visible ; avancer au milieu d'une vidéo longue (requête `Range`, 206) ; lancer une seconde vidéo met la première en pause ; mettre l'application en arrière-plan met en pause ; rouvrir après plus de 15 min : « Réessayer » obtient un lien neuf. **Poids de l'APK** : relever la taille du job CI « budget de taille » avec `video_player` et la reporter dans `tool/size_budget.json` |

## 11. Accessibilité

| Réf. | Action | Résultat attendu | Statut | Commentaire |
|---|---|---|---|---|
| ACC-01 | Naviguer au clavier seul (Tab, Maj+Tab, Entrée, Échap) sur `/`, `/connexion`, `/sos`, `/signaler`. | Ordre logique, focus toujours visible, aucun piège au clavier, lien d'évitement présent. | ☐ | |
| ACC-02 | Zoomer à 200 % et réduire la fenêtre à 360 px de large. | Aucun texte coupé, aucun défilement horizontal de la page. | ☐ | |
| ACC-03 | Passer en thème Sombre (Paramètres › Apparence). | Textes et boutons restent lisibles (contraste suffisant). | ☐ | |
| ACC-04 | Activer le lecteur d'écran (NVDA, VoiceOver ou TalkBack) sur `/sos` et le formulaire de signalement. | Boutons nommés, erreurs annoncées, décompte SOS annoncé ; navigation admin : « Page : … » annoncé. | ☐ | |
| ACC-05 | Désactiver les animations dans le système (« réduire les animations »). | Les animations sont atténuées ou supprimées. | ☐ | |
| ACC-06 | Vérifier chaque image informative. | Texte alternatif présent ; images décoratives ignorées par le lecteur d'écran. | ☐ | |

## 12. Performance

Voir [PERFORMANCE.md](PERFORMANCE.md). Mesures sur votre poste, pas sur l'hébergement cible.

| Réf. | Action | Résultat attendu | Statut | Commentaire |
|---|---|---|---|---|
| PER-01 | Ouvrir `/` avec le cache vidé et le réseau limité à « Slow 4G » (F12 › Réseau). | Le contenu utile apparaît en quelques secondes ; pas de saut de mise en page. | ☐ | |
| PER-02 | `npm run build` : lire la fin de la sortie. | Le contrôle des budgets se termine par « ok » pour chaque ligne. | ☐ | |
| PER-03 | Parcourir 10 pages d'affilée (accueil, fil, messages, admin). | Aucune page ne reste bloquée plus de 2 s en local ; navigation admin fluide. | ☐ | |
| PER-04 | Recharger `/urgences` hors connexion après une première visite. | La page s'affiche depuis le cache (service worker, build de production). | ☐ | |

## 13. Sécurité visible

Voir [SECURITE_CHECKLIST.md](SECURITE_CHECKLIST.md).

| Réf. | Action | Résultat attendu | Statut | Commentaire |
|---|---|---|---|---|
| SEC-01 | Se connecter depuis un navigateur neuf. | Code de vérification exigé ; comptes admin : à chaque connexion. | ☐ | |
| SEC-02 | Saisir un mot de passe faible à l'inscription. | Refus avec explication. | ☐ | |
| SEC-03 | Tenter d'ouvrir une page réservée sans être connecté (`/fil`, `/admin`). | Redirection vers `/connexion` (ou 403/503 selon le cas), jamais de contenu. | ☐ | |
| SEC-04 | Un membre ouvre l'adresse d'une ressource privée d'un autre (changer l'identifiant dans l'URL). | Accès refusé ou introuvable, sans fuite de contenu. | ☐ | |
| SEC-05 | Rechercher un pseudo dont le profil est privé (MEM-10). | Il n'apparaît pas dans la recherche ni dans l'annuaire. | ☐ | |
| SEC-06 | Saisir `<script>alert(1)</script>` dans une publication. | Le texte s'affiche tel quel, aucun script ne s'exécute. | ☐ | |
| SEC-07 | Lire les en-têtes : `curl -sI http://localhost:8000/`. | `Content-Security-Policy`, `X-Content-Type-Options: nosniff`, `X-Frame-Options`, `Referrer-Policy` présents ; pas de `X-Powered-By`. | ☐ | |
| SEC-08 | Ouvrir `http://localhost:8000/.env`. | Réponse 403 ou 404, jamais le fichier. | ☐ | |
| SEC-09 | Mode discret et déconnexion de tous les appareils (MEM-12). | Effets immédiats. | ☐ | |
| SEC-10 | Vérifier qu'aucun mot de passe, code ou récit n'apparaît dans `laravel.log` ni dans les listes d'administration. | Aucune donnée sensible en clair (numéros de SMS masqués). | ☐ | |

## 14. Ce qui n'est PAS encore fonctionnel (à ne pas tester)

Liste établie d'après [IMPLEMENTATION_PLAN.md](IMPLEMENTATION_PLAN.md) §8 et [ETAT_DES_LIEUX.md](ETAT_DES_LIEUX.md) (27/10/2026). Ne cochez pas d'anomalie pour ces points : ils sont connus et suivis.

| Sujet | État réel | Référence |
|---|---|---|
| Réseaux sociaux (Facebook, Telegram, TikTok) | Passerelles en pilote `log` : aucune publication réelle, « publication manuelle » ; transport HTTP réel non codé, jetons à fournir par le client ; TikTok sans connecteur de publication | P14-04 |
| SMS réels | Désactivés (`SMS_DRIVER=log`). Vonage et Twilio sont codés mais jamais éprouvés sans clés ; le SOS n'a pas été éprouvé en conditions réelles | Q7, P5 |
| Push réels | Désactivés (`FCM_DRIVER=log`, `WEBPUSH_DRIVER=log`). Firebase demande un projet et `google-services.json` (non fournis) ; le Web Push demande un paquet Composer et des clés VAPID non livrés | P15-08, P17-07 |
| Assistant Gemini | Désactivé ; la base de connaissances répond seule. Ne pas activer sans avis juridique | Q7, DECISIONS_A_VALIDER |
| Application mobile | Aucun APK ni iOS compilé ni publié ; lancement sur appareil non vérifié ; pas de Play Console ni d'App Store. iOS : pas de push (APNs), pas d'icône alternative du mode discret. Pas de tests d'intégration ni de `sync/pull` mobile. `flutter run -d chrome` non prévu | P15, P17-07 |
| Espace pair et administration mobiles | Seules des **maquettes** existent (`prototype_mobile/espace-pair.html`, `admin-mobile.html`) ; rien dans l'application Flutter. Plusieurs tuiles mènent à « bientôt » (`/soon/<page>`) | P15-09 |
| Résumé quotidien ou hebdomadaire des notifications | Le réglage n'a aucun effet (`notifications:digest` absent) ; idem `alerts:anonymize` | P3-07 |
| Envoi réel des e-mails | Aucun SMTP configuré par défaut : e-mails consignés dans le journal | RUNBOOK §5 |
| Mesures de performance sur l'hébergement cible | Non réalisées (p95, Lighthouse, extensions PHP, `mysqldump`) ; rien n'a tourné sur le serveur cPanel | P16-02, P17 |
| Déploiement, DNS, HTTPS, cron, supervision, test de restauration | À faire sur le serveur (assainissement de l'hébergement compromis d'abord) | P17-01 à P17-06 |
| Navigateurs et appareils | Tests automatisés sous Chromium seulement ; Firefox, Safari, 320 px et lecteurs d'écran réels non couverts ; comparaison « à l'identique » au prototype aux 4 largeurs non faite | P16-05, P16-07 |
| Autres fonctions partielles | PWA pair, indicateurs de l'espace pair, médiathèque / « Mes signalements » (outils CCC en lecture livrés le 11/11/2026 sur le web et dans l'application : à recetter, sans quiz, sans lecture hors ligne dans l'application), PIN sur le web, filtres enregistrés de l'administration, balise de statistiques « Contenu » des pages publiques | ETAT_DES_LIEUX §4.1 |
| Textes et décisions juridiques | Textes d'accueil, de confidentialité, d'aide, âge minimal, quiz santé : à valider par le client ; conformité légale dépendante d'avis extérieurs | DECISIONS_A_VALIDER |

## Synthèse de la recette

| Parcours | Lignes | ✅ | ❌ | ➖ |
|---|---|---|---|---|
| 1. Visiteur·se | 12 | | | |
| 2. Inscription et connexion | 7 | | | |
| 3. Membre | 21 | | | |
| 4. Pair animateur·rice | 6 | | | |
| 5. Modérateur·rice | 4 | | | |
| 6. Administrateur·rice et super-administrateur·rice | 14 | | | |
| 7. SOS et alerte | 9 | | | |
| 8. E-mails | 6 | | | |
| 9. Notifications | 7 | | | |
| 10. Mobile | 10 | | | |
| 11. Accessibilité | 6 | | | |
| 12. Performance | 4 | | | |
| 13. Sécurité visible | 10 | | | |

Décision : ☐ recette acceptée · ☐ acceptée avec réserves · ☐ refusée. Nom, date et signature : ____________
