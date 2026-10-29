# Checklist de recette humaine

À remplir pendant la recette, avec le [guide de recette](GUIDE_RECETTE.md) (installation, lancement, comptes de démonstration). Une ligne = une action à faire et un résultat à constater. Dans la colonne **Statut**, remplacez `☐` par `✅` (conforme), `❌` (anomalie : la décrire en commentaire avec le gabarit du guide §11) ou `➖` (non testé, avec la raison). Les identifiants (VIS-01…) servent de référence dans les rapports d'anomalie.

Prérequis : `scripts/recette-local.sh` puis `scripts/recette-lancer.sh` ; ouvrir **http://localhost:8000** ; journal : `web/storage/logs/laravel.log`. Mots de passe de démonstration : guide §5.

- Recette réalisée par : ____________   Date : ____________   Commit (`git rev-parse --short HEAD`) : ____________
- Navigateur et version : ____________   Appareil mobile : ____________

## 1. Visiteur·se (sans compte)

Navigateur en navigation privée, aucun compte connecté.

| Réf. | Action | Résultat attendu | Statut | Commentaire |
|---|---|---|---|---|
| VIS-01 | Ouvrir `/`. | Page d'accueil complète et stylée, menu, bouton SOS visible, section rouge très clair « alerte » (`/#alerte`) ; aucune erreur rouge dans la console (F12). | ☐ | |
| VIS-02 | Ouvrir `/thematiques`, puis une fiche. | 29 thématiques ; la fiche est lisible, la FAQ s'affiche en bas. | ☐ | |
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

## 2. Inscription et connexion

Parcours commun à tous les rôles.

| Réf. | Action | Résultat attendu | Statut | Commentaire |
|---|---|---|---|---|
| AUT-01 | Ouvrir `/inscription`, remplir pseudo `recette1`, e-mail `recette1@exemple.bj`, mot de passe `Mot-de-passe-solide-2026!` **sans** cocher la case d'âge minimal (15 ans), cocher CGU et confidentialité, valider. | Refus avec un message demandant la confirmation de l'âge minimal ; aucun compte créé. | ☐ | |
| AUT-02 | Recommencer en cochant la case d'âge minimal. | Arrivée sur `/inscription/profil` ; étapes profil, sécurité et consentements facultatives (« Passer ») ; e-mail « Bienvenue » dans le journal. | ☐ | |
| AUT-03 | Lire le code dans le journal, le saisir sur `/code-de-verification` ; essayer d'abord un code faux. | Code faux : message d'erreur et compteur d'essais. Bon code : compte vérifié, arrivée sur `/fil`. « Renvoyer » respecte un délai. | ☐ | |
| AUT-04 | Se déconnecter, se reconnecter ; tester « Mot de passe oublié » (`/mot-de-passe-oublie`, code du journal, `/nouveau-mot-de-passe`). | Connexion et réinitialisation réussies ; mot de passe faible refusé. | ☐ | |
| AUT-05 | Se connecter avec `pair.attente@xomiton.bj` / `Pair123!`. | Connexion avec un bandeau « en attente de validation » ; compte membre seulement, pas d'accès à `/espace-pair`. | ☐ | |
| AUT-08 | `/inscription/profil` : chercher un choix « Type de membre / pair éducateur·rice ». | Aucun : l'inscription publique ne crée que des membres (le rôle pair vient d'une invitation ou de l'administration). | ☐ | |
| AUT-09 | Se connecter avec `equipe@xomiton.bj` / `Equipe123!` (code du journal). | Un seul login ; arrivée sur `/fil` ; les menus mènent à `/espace-pair` et `/admin` ; le réseau social reste utilisable. | ☐ | |
| AUT-06 | Enchaîner 6 connexions fausses en moins d'une minute. | Page « Trop de tentatives… Nouvelle tentative possible dans N secondes » (429). | ☐ | |
| AUT-07 | Ouvrir `/connexion`, supprimer les cookies du site (F12 › Application), envoyer le formulaire. | Page « Erreur 419 — Votre session a expiré… » sans perte de contrôle. | ☐ | |

## 3. Membre (`membre@xomiton.bj`, `membre2@xomiton.bj`)

Utiliser deux fenêtres privées pour les tests à deux.

| Réf. | Action | Résultat attendu | Statut | Commentaire |
|---|---|---|---|---|
| MEM-01 | Ouvrir `/fil` et publier un texte avec un thème et une visibilité. | La carte apparaît en haut, le compteur « Publications » augmente. | ☐ | |
| MEM-02 | Créer un sondage (question, deux réponses, durée) puis voter depuis le second compte. | Résultat affiché ; après l'échéance (planificateur lancé) le vote est refusé. | ☐ | |
| MEM-03 | Commenter, mettre « J'aime », partager, enregistrer (`/fil/enregistres`), ouvrir le détail `/fil/<id>`. | Chaque action est prise en compte et visible. | ☐ | |
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
| MEM-15 | Avec `membre@xomiton.bj`, ouvrir `/admin`, `/espace-pair`, `/collecte`. | Page « Erreur 403 » dans les trois cas. | ☐ | |
| MEM-16 | `/recherche?q=santé` puis `sante` ; onglets Tout, Ressources, Thématiques, Contacts, Membres. | Mêmes résultats avec et sans accent. | ☐ | |
| MEM-17 | `/assistant` : cliquer « Comment porter plainte ? ». | Réponse de la base de connaissances (aucun appel externe). | ☐ | |
| MEM-18 | `/assistant` : écrire « je veux mourir, je ne vois plus d'issue ». | Réponse de crise avec boutons SOS (position GPS), Appeler le 117 / 118, ligne d'écoute, Faire un signalement, Parler à un humain. « Garder cette conversation » est désactivé par défaut. | ☐ | |

## 4. Pair éducateur·rice (`pair@xomiton.bj`)

| Réf. | Action | Résultat attendu | Statut | Commentaire |
|---|---|---|---|---|
| PAI-01 | Se connecter (code du journal), ouvrir `/espace-pair` et `/espace-pair/dossiers`. | Tableau de bord (dossiers, alertes routées) ; seuls ses dossiers sont visibles. | ☐ | |
| PAI-02 | Ouvrir `/collecte`, remplir un dossier (type, violence, département, population, sexe, âge) et **Enregistrer le dossier**. | Bandeau « En ligne / Aucune fiche en attente » ; dossier créé. | ☐ | |
| PAI-03 | Même formulaire, mode **Hors connexion** activé (F12 › Réseau), enregistrer. | Bandeau « Hors ligne · 1 fiche en attente ». | ☐ | |
| PAI-04 | Repasser **En ligne**. | En quelques secondes « Aucune fiche en attente » ; le dossier apparaît dans `/admin/registres` (compte admin). | ☐ | |
| PAI-05 | Ouvrir `/admin`. | Page « Erreur 403 ». | ☐ | |
| PAI-06 | Dans l'espace pair, consulter une alerte routée vers ce compte (créer d'abord un signalement, VIS-09). | L'alerte est visible, on peut l'accuser réception ; les autres alertes restent invisibles. | ☐ | |

## 5. Modérateur·rice (`moderateur@xomiton.bj`)

| Réf. | Action | Résultat attendu | Statut | Commentaire |
|---|---|---|---|---|
| MOD-01 | Se connecter (code du journal). | Accès à la modération, au réseau social et aux utilisateurs (suspension) ; pas aux registres, aux fiches ni aux paramètres. | ☐ | |
| MOD-02 | `/admin/moderation` : traiter la publication signalée en MEM-04 (confirmer ou rétablir). | La décision est enregistrée et la publication masquée ou rétablie. | ☐ | |
| MOD-03 | Consulter la liste des mots surveillés. | 7 mots listés. | ☐ | |
| MOD-04 | Ouvrir `/admin/registres` ou `/admin/parametres`. | Accès refusé (403). | ☐ | |

## 6. Administrateur·rice et super-administrateur·rice (`moderation@xomiton.bj`, `admin@xomiton.bj`)

Un code de vérification est demandé à chaque connexion.

| Réf. | Action | Résultat attendu | Statut | Commentaire |
|---|---|---|---|---|
| ADM-01 | Ouvrir `/admin`. | Tableau de bord : tuiles (Nouvelles alertes, SOS actifs, Comptes…), graphiques, menu complet ; Ctrl+K ouvre la recherche globale. | ☐ | |
| ADM-02 | Dans la console, taper `window.__nav = 1`, puis cliquer Alertes, Registres, Indicateurs, Maintenance, Journal, Tableau de bord. | L'URL change, `window.__nav` vaut toujours 1 (pas de rechargement), barre bleue fine en haut, une seule entrée active ; les liens « Site public », « Déconnexion » et les téléchargements rechargent la page. | ☐ | |
| ADM-03 | `/admin/alertes` : filtres, actualisation automatique (10 s), accuser réception, notes, **Exporter CSV**. | Les actions fonctionnent ; le statut changé s'affiche dans `/suivi` côté visiteur. | ☐ | |
| ADM-04 | `/admin/registres` : **Nouveau dossier** ; `/admin/fiches`. | Le récit n'apparaît jamais dans la liste. L'édition d'une fiche déjà créée n'est pas disponible (voir la section 14). | ☐ | |
| ADM-05 | `/admin/utilisateurs` : filtrer par rôle, **Créer un compte**, suspendre puis réactiver, **valider** le pair en attente (`pair_en_attente`). | Le compte validé se connecte sans bandeau et garde le rôle membre en plus du rôle pair ; la matrice des permissions est en lecture seule. | ☐ | |
| ADM-14 | `/admin/utilisateurs` › ouvrir `membre@xomiton.bj` › **Donner le rôle de pair** puis **Retirer le rôle de pair**. | Le compte reste membre dans les deux cas ; il reçoit une notification ; les deux actions sont dans `/admin/journal` (`roles.peer_granted`, `roles.peer_revoked`). | ☐ | |
| ADM-15 | `/admin/inscriptions` › **Inviter dans l'équipe** (rôle pair), copier le lien, l'ouvrir en navigation privée, s'inscrire (case « 15 ans » cochée), confirmer le code. | Compte membre créé, puis rôle pair ajouté à la confirmation ; le lien ne marche plus une seconde fois ; l'admin ne voit pas les rôles d'administration (super-admin seulement). | ☐ | |
| ADM-16 | Avec `membre@xomiton.bj`, appeler `POST /api/v1/me/role-requests` (`Idempotency-Key`, motif court) ; répéter avec une autre clé ; puis `/admin/inscriptions` › « Demandes du rôle de pair·e » › **Approuver** (ou **Refuser** avec motif). | Première demande 201, seconde 422 `request_open` ; le rôle n'est pas donné avant la décision ; après approbation le compte est membre ET pair, notifié, `roles.request_approved` au journal ; un refus garde le compte membre et envoie le motif. | ☐ | |
| ADM-17 | S'inscrire avec `test@mailinator.com`, puis avec une adresse ordinaire ; ouvrir `/admin/inscriptions`. | Seul le premier compte est « en attente » avec le motif « Adresse e-mail jetable » ; le second est actif après confirmation de l'e-mail ; avec « Tous les nouveaux comptes » les deux attendent. | ☐ | |
| ADM-18 | Avec `equipe@xomiton.bj` sur l'appli mobile (ou `PUT /api/v1/admin/members/{id}/role` avec `role: admin`). | Refusé (403) : le rôle admin ne se donne que depuis le site web par un·e super-admin. | ☐ | |
| ADM-06 | `/admin/contenu` : onglets Ressources, Thématiques, FAQ, Catégories ; **Nouvelle ressource** ; « Passer en brouillon ». | La ressource en brouillon disparaît de `/ressources`. | ☐ | |
| ADM-07 | `/admin/newsletter` › Campagnes › Nouvelle campagne : objet, corps, segment, e-mail dans « Envoyer le test ». | Toast « Test mis en file d'envoi » ; après `mail:flush-outbox`, sujet `[TEST] …` dans le journal. | ☐ | |
| ADM-08 | `/admin/sauvegardes` : **Sauvegarder maintenant**. | Archive chiffrée listée et téléchargeable ; avertissement normal « mot de passe d'archive non défini ». | ☐ | |
| ADM-09 | `/admin/parametres` : saisir une valeur hors bornes ; `/admin/journal` : consulter, filtrer, exporter. | Valeur refusée ; le journal montre vos actions (`auth.otp_verified`, `alerts.sos_triggered`…). | ☐ | |
| ADM-10 | `/admin/emails` : ouvrir plusieurs modèles, voir l'aperçu, essai d'envoi. | Aperçu dans le gabarit premium, essai visible dans le journal. | ☐ | |
| ADM-11 | `/admin/passerelles`. | Passerelles sans clés : « publication manuelle » (aucune publication réelle). | ☐ | |
| ADM-12 | `/admin/maintenance` : activer (motif, durée, case « Je confirme »), vérifier en navigation privée, puis **désactiver**. | Voir la section « Mode maintenance » ci-dessous. | ☐ | |
| ADM-13 | Comparer avec `moderation@xomiton.bj` (admin) : essayer ce qui est réservé au super-admin (restaurer ou supprimer une sauvegarde, rôles d'administration). | Actions réservées refusées ou masquées pour l'admin. | ☐ | |

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

## 10. Mobile (Flutter, compte `membre@xomiton.bj`)

Émulateur Android ou téléphone ; API `http://10.0.2.2:8000/api/v1` par défaut. Non vérifié sur appareil à ce jour.

| Réf. | Action | Résultat attendu | Statut | Commentaire |
|---|---|---|---|---|
| MOB-01 | `flutter pub get`, `flutter analyze`, `flutter test`. | « No issues found! » et « All tests passed! » (152 tests). | ☐ | |
| MOB-02 | `flutter run` : parcourir le carrousel plein écran (photos, Suivant, balayage, points, Passer) ; sur la 4e diapositive, « Créer un compte » et « Se connecter ». | 4 photos plein écran, titres en tu lisibles sur la photo ; « Passer » et « Se connecter » mènent à la connexion, « Créer un compte » à l'inscription. | ☐ | |
| MOB-03 | Créer un compte (avec la case d'âge minimal), saisir le code du journal. | Arrivée sur l'accueil (tuiles Apprendre, Signaler, Urgences, Assistant). | ☐ | |
| MOB-04 | Se connecter, se déconnecter, se reconnecter ; tester le mot de passe oublié. | Connexion avec code si nouvel appareil. | ☐ | |
| MOB-05 | Onglet SOS : maintenir, annuler, puis envoyer ; refuser la position. | Même comportement que le web ; l'alerte part sans position ; ligne visible dans `/admin/alertes`. | ☐ | |
| MOB-06 | Signalement anonyme puis `/suivi` ; répéter sans réseau. | Code de suivi affiché ; sans réseau l'envoi est mis en file et part au retour. | ☐ | |
| MOB-07 | Fil : lire, publier, commenter, « J'aime » ; Messages : écrire depuis le web et le mobile. | Les contenus se retrouvent des deux côtés. | ☐ | |
| MOB-08 | Profil, profil progressif, paramètres, sécurité (mode discret, masquage des aperçus, appareils), suppression de compte. | Chaque écran répond ; mode discret : nom « Notes » du lanceur Android (quelques secondes). | ☐ | |
| MOB-09 | Sécurité › activer le code PIN (4 chiffres), fermer et rouvrir l'application ; saisir 5 PIN faux. | PIN demandé ; au 5e échec, session fermée (reconnexion mot de passe + code). SOS, urgences et signalement restent accessibles sans PIN. | ☐ | |
| MOB-10 | Ouvrir les maquettes `prototype_mobile/espace-pair.html` et `admin-mobile.html` (aussi depuis le menu P1 à P11 et A1 à A7 de `index.html`). | Les deux maquettes s'affichent, sans bascule de thème ; à valider par la coordination (elles ne sont pas encore dans l'application). | ☐ | |
| MOB-11 | Ouvrir `prototype_mobile/index.html` : bouton ☰ (replier, rouvrir, recharger), replier un module, ouvrir un écran en moins de 10 s ; chercher une bascule de thème. | Menu mémorisé après rechargement ; 30 écrans numérotés 01 à 30 + Espace pair et Admin mobile ; thème clair, aucune bascule de thème. | ☐ | |
| MOB-12 | Prototype, écran Inscription (`#/signup`) : bouton avant et après la case « J'ai 15 ans ou plus », puis « J'ai moins de 15 ans ». | « Continuer » désactivé tant que la case n'est pas cochée ; refus bienveillant sans création de compte. | ☐ | |
| MOB-13 | Prototype, `#/postpublication` : saisir un long texte. | Le champ occupe la largeur à côté de l'avatar, hauteur minimale 120 px, croît sans déborder du châssis. | ☐ | |

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
| Édition d'une fiche VBG / VDH déjà créée | Impossible (création seulement) | P11-02 |
| Envoi réel des e-mails | Aucun SMTP configuré par défaut : e-mails consignés dans le journal | RUNBOOK §5 |
| Mesures de performance sur l'hébergement cible | Non réalisées (p95, Lighthouse, extensions PHP, `mysqldump`) ; rien n'a tourné sur le serveur cPanel | P16-02, P17 |
| Déploiement, DNS, HTTPS, cron, supervision, test de restauration | À faire sur le serveur (assainissement de l'hébergement compromis d'abord) | P17-01 à P17-06 |
| Navigateurs et appareils | Tests automatisés sous Chromium seulement ; Firefox, Safari, 320 px et lecteurs d'écran réels non couverts ; comparaison « à l'identique » au prototype aux 4 largeurs non faite | P16-05, P16-07 |
| Autres fonctions partielles | PWA pair, indicateurs de l'espace pair, médiathèque / outils CCC / « Mes signalements », PIN sur le web, filtres enregistrés de l'administration, balise de statistiques « Contenu » des pages publiques | ETAT_DES_LIEUX §4.1 |
| Textes et décisions juridiques | Textes d'accueil, de confidentialité, d'aide, âge minimal, quiz santé : à valider par le client ; conformité légale dépendante d'avis extérieurs | DECISIONS_A_VALIDER |

## Synthèse de la recette

| Parcours | Lignes | ✅ | ❌ | ➖ |
|---|---|---|---|---|
| 1. Visiteur·se | 12 | | | |
| 2. Inscription et connexion | 7 | | | |
| 3. Membre | 18 | | | |
| 4. Pair éducateur·rice | 6 | | | |
| 5. Modérateur·rice | 4 | | | |
| 6. Administrateur·rice et super-administrateur·rice | 13 | | | |
| 7. SOS et alerte | 9 | | | |
| 8. E-mails | 6 | | | |
| 9. Notifications | 7 | | | |
| 10. Mobile | 10 | | | |
| 11. Accessibilité | 6 | | | |
| 12. Performance | 4 | | | |
| 13. Sécurité visible | 10 | | | |

Décision : ☐ recette acceptée · ☐ acceptée avec réserves · ☐ refusée. Nom, date et signature : ____________
