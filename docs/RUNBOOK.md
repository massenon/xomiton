# Runbook d'exploitation (P17-05)

Hébergement cPanel mutualisé (D7) : pas de Node, pas de Redis, `QUEUE_CONNECTION=database`, `CACHE_STORE=file`, `SESSION_DRIVER=database`, cron chaque minute. Chemins ci-dessous : application dans `~/xomiton/web`, racine web du (sous-)domaine = `~/xomiton/web/public`. Checklist serveur : [DEPLOIEMENT_CPANEL.md](DEPLOIEMENT_CPANEL.md).

Convention : `PHP=/opt/cpanel/ea-php83/root/usr/bin/php` (le `php` du terminal peut être 8.2). Toute commande `artisan` se lance depuis `~/xomiton/web`. Statut : procédures **rédigées, à rejouer une fois en recette** (critère de P17-05).

## 1. Déploiement pas à pas

Poste local :
1. `composer install --no-dev --optimize-autoloader --no-interaction` (dans une copie propre ou après `composer install` classique si on revient en dev ; ne jamais envoyer `vendor/` de dev).
2. `npm ci && npm run build` ; contrôler `public/build/manifest.json` (le serveur n'a pas Node).
3. Constituer l'envoi : `web/` sans `.env`, `.git`, `node_modules`, `tests`, `storage/logs/*`, `storage/framework/*` ; **avec** `vendor/` et `public/build/`.

Serveur :
1. Envoyer par SFTP / Git cPanel dans `~/xomiton/web` (jamais dans `public_html`). Garder l'archive de l'envoi précédent (retour arrière, §3).
2. **`.env` de production** (créé une fois, `chmod 600`, jamais versionné), à partir de `.env.example` : `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://…`, `DB_*` (utilisateur MySQL dédié, droits limités à la base), `SANCTUM_STATEFUL_DOMAINS=<hôte d'APP_URL>`, `SESSION_ENCRYPT=true`, `QUEUE_CONNECTION=database`, `CACHE_STORE=file`, `SESSION_DRIVER=database`, `MAIL_*` ou SMTP dans Administration › Templates e-mails, `HEARTBEAT_URL`, `BACKUP_ARCHIVE_PASSWORD`, `BACKUP_DISK`, fournisseurs `WEBPUSH_DRIVER` / `FCM_DRIVER` / `SMS_PROVIDER`.
3. **Premier déploiement seulement** : `$PHP artisan key:generate`. Ne jamais le relancer sur un site en service (données chiffrées illisibles) : voir §8.
4. `scripts/deploy.sh --first` au premier déploiement, puis `scripts/deploy.sh` aux suivants. Le script vérifie PHP 8.3, extensions, `.env`, `public/build` ; sauvegarde la base, active `xomiton:maintenance`, lance `migrate --force`, `storage:link`, `config:cache`, `route:cache`, `view:cache`, `event:cache`, lève la maintenance et lance `xomiton:preflight`. À la main, c'est : `$PHP artisan migrate --force && $PHP artisan storage:link && $PHP artisan config:cache && $PHP artisan route:cache && $PHP artisan view:cache`.
5. **Permissions** : dossiers 755, fichiers 644, `storage/` et `bootstrap/cache/` en 775 (`chmod -R ug+rwX storage bootstrap/cache`), `.env` en 600, propriétaire = compte cPanel.
6. **Cron** (cPanel › Tâches Cron, chaque minute `* * * * *`) — les DEUX lignes, avec le binaire PHP 8.3 complet :
   ```
   * * * * * cd /home/COMPTE/xomiton/web && /opt/cpanel/ea-php83/root/usr/bin/php artisan schedule:run >> /dev/null 2>&1
   * * * * * cd /home/COMPTE/xomiton/web && /opt/cpanel/ea-php83/root/usr/bin/php artisan queue:work --stop-when-empty --max-time=50 --tries=3 >> /dev/null 2>&1
   ```
   Le planificateur lance les purges, `alerts:escalate`, `notifications:retry` puis `heartbeat:ping`. Vérifier « Entry Processes » (cPanel › Resource Usage) après 10 min.
7. `$PHP artisan xomiton:preflight` : zéro `FAIL` (le cron doit avoir tourné ; `--skip-cron` avant installation des lignes). Puis tests HTTP : `/`, `/sos`, `/up`, en-têtes (CSP, HSTS), `/build/*` en `immutable`.
8. Superviseur : créer la vérification Healthchecks.io / UptimeRobot (période 1 min, grâce 3 min, alerte e-mail + SMS), coller son URL dans `HEARTBEAT_URL`, puis `$PHP artisan config:cache`. Sondes HTTP externes sur `/` et `/sos`.

## 2. Mise à jour courante

`scripts/deploy.sh` après envoi (§1). Avant tout envoi : sauvegarde (§9), annonce si la maintenance dépasse 5 min. Un correctif sans migration ne demande pas de maintenance, mais `config:cache` / `view:cache` restent obligatoires.

## 3. Retour arrière

1. `scripts/deploy.sh --rollback` : maintenance, `migrate:rollback --step=N` (N = migrations appliquées par le dernier déploiement, noté dans `storage/app/deploy-last-migrations`), recréation des caches.
2. Remettre l'archive du code précédent (même `vendor/` et `public/build/`), relancer `scripts/deploy.sh --skip-preflight` puis `xomiton:preflight`.
3. Si une migration destructive a modifié des données : restaurer la sauvegarde pré-migration (§9) plutôt que `migrate:rollback`.
4. Si le script a échoué en cours de route, le site reste **en maintenance** : corriger puis relancer, ou `xomiton:maintenance off` après avoir décidé du retour arrière.

## 4. Alerte de signal de vie (cron arrêté)

Risque : SOS non escaladés. Délai de réaction visé : 15 min.
1. cPanel › Tâches Cron : les deux lignes sont-elles présentes et actives ? Un changement de version PHP a-t-il changé le chemin ?
2. Terminal : `$PHP artisan schedule:run -v` doit lister les tâches ; une exception s'affiche ici. `tail -n 100 storage/logs/laravel.log`.
3. `$PHP artisan xomiton:preflight` : la ligne « Cron » indique quel marqueur (`alerts:escalate` / `notifications:retry`) est périmé. Cron qui tourne mais marqueur absent = exception dans la commande : la cause est dans le journal.
4. Causes fréquentes : quota d'Entry Processes / CPU (cPanel › Resource Usage), disque plein (`df -h ~`, `du -sh storage/*`), mot de passe MySQL changé, verrou `withoutOverlapping` orphelin (`$PHP artisan cache:clear` si `storage/framework/cache` est cohérent, sinon supprimer `storage/framework/cache/data/*`).
5. Rattrapage : une fois le cron rétabli, `$PHP artisan alerts:escalate && $PHP artisan notifications:retry` ; ouvrir le tableau des alertes et traiter à la main les SOS sans accusé (§12).
6. Consigner l'incident (début, fin, SOS touchés).

## 5. SMTP indisponible (vider `mail_outbox`)

Les e-mails sans transport utilisable sont mis dans `mail_outbox` (5 tentatives maximum, 50 par minute via `mail:flush-outbox`).
1. Administration › Templates e-mails › test d'envoi ; vérifier hôte, port, chiffrement, mot de passe, quota horaire du compte e-mail cPanel (mutualisé : souvent 100–300 e-mails/h).
2. Corriger le SMTP (écran ci-dessus, ou `.env` + `config:cache`).
3. Vider : `$PHP artisan mail:flush-outbox --limit=50` répété jusqu'à épuisement, ou laisser le cron (50/min). Surveiller `select count(*) from mail_outbox;`.
4. Lignes à 5 tentatives : corriger la cause puis remettre `attempts` à 0 pour ces lignes, ou les supprimer si périmées (code OTP expiré, rappel passé).
5. Prolongé > 1 h : les e-mails de sécurité (OTP, réinitialisation) échouent, l'alerte SOS passe par push / SMS (§6) ; basculer sur un second SMTP (Brevo, Mailjet…).

## 6. Fournisseur push / SMS en échec

1. Identifier : `failed_jobs`, `notification_*` en échec dans Administration, `storage/logs/laravel.log` (erreurs masquées de `DeliveryError`).
2. Les échecs sont rejoués chaque minute par `notifications:retry` (SOS d'abord, SMS de repli). Un fournisseur en panne ne bloque pas l'enregistrement du SOS.
3. SMS : vérifier solde et clé (`SMS_API_KEY`), ou changer `SMS_PROVIDER` puis `config:cache`. Web Push / FCM : voir §8 pour les clés. Un pilote `log` signale qu'**aucun envoi réel n'a lieu** (`xomiton:preflight` le signale en WARN).
4. Pendant la panne : joindre les contacts de confiance et le point focal par téléphone pour tout SOS actif.
5. `$PHP artisan queue:failed` puis `queue:retry all` une fois le fournisseur rétabli ; `queue:flush` seulement après revue.

## 7. Mode maintenance

`$PHP artisan xomiton:maintenance on --minutes=30 --message="…" --reason="…"` ; `off --reason="…"` ; `status`. Applicatif (RG-14) : la liste blanche RG-01 (urgences, SOS) reste servie, admin et superadmin se connectent. Ne jamais utiliser `artisan down`. Commande utilisable sans l'écran d'administration (garde-fou anti-verrouillage).

## 8. Rotation des clés et secrets

**APP_KEY** (suspicion de fuite, compromission P17-01) :
1. Sauvegarde (§9) et `xomiton:maintenance on`.
2. Générer : `$PHP artisan key:generate --show`. Dans `.env` : `APP_PREVIOUS_KEYS=<ancienne APP_KEY>` puis `APP_KEY=<nouvelle>` ; `config:cache`.
3. `$PHP artisan xomiton:reencrypt --dry-run` (compte), puis `xomiton:reencrypt` **répété** jusqu'à « 0 restante » (50 s par appel, reprise automatique). Une sauvegarde terminée < 24 h est exigée (`--force` à éviter).
4. Retirer `APP_PREVIOUS_KEYS` seulement quand le dry-run est à zéro ; les sessions et cookies sont invalidés (reconnexion). Les archives ZIP de sauvegarde ne dépendent pas d'`APP_KEY` (ADR-22).
5. `xomiton:maintenance off`, `xomiton:preflight`.

**VAPID** (Web Push) : générer une nouvelle paire, mettre à jour `VAPID_PUBLIC_KEY` / `VAPID_PRIVATE_KEY`, `config:cache`. Les abonnements existants sont liés à l'ancienne clé publique : ils échouent puis sont supprimés par `notifications:retry`, les personnes se réabonnent à la prochaine visite. À planifier hors alerte active.
**FCM** : créer une nouvelle clé du compte de service Firebase, déposer le JSON **hors `public/`** (`chmod 600`), mettre à jour `FIREBASE_CREDENTIALS`, `config:cache`, vérifier un push de test, puis révoquer l'ancienne clé dans la console Google Cloud.
**HMAC newsletter** (`XOMITON_NEWSLETTER_KEY`) : la changer invalide les liens de suivi et de désabonnement des campagnes déjà envoyées : à ne faire qu'en cas de fuite, après avoir prévenu l'équipe.
**Passerelles** (`FACEBOOK_*`, `TELEGRAM_BOT_TOKEN`, `TIKTOK_*`), **SMS**, **DB**, **SMTP** : régénérer chez le fournisseur, modifier `.env`, `config:cache`, `queue:restart` inutile (workers courts), tester, révoquer l'ancien jeton.

## 9. Sauvegarde et restauration

- Automatique : `backups:run` (02:00) = `mysqldump` si possible, sinon archive ZIP chiffrée (JSON par table + `manifest.json`), vers `BACKUP_DISK` ; `scripts/backup.sh` (mysqldump + `storage/app`) à planifier à 01:30 avec copie hors serveur (`BACKUP_UPLOAD_CMD`) ; mysqldump cPanel (Sauvegardes) vers stockage externe chiffré.
- Manuelle avant mise à jour : `scripts/backup.sh`.
- **Restauration complète (mysqldump)** : `xomiton:maintenance on` ; `gunzip -c xomiton-db-AAAAMMJJ.sql.gz | mysql -h HOTE -u USER -p BASE` (phpMyAdmin si taille < limite) ; remettre `storage/app` depuis l'archive fichiers ; `$PHP artisan migrate --force` si la sauvegarde est antérieure au code ; `config:cache` ; `xomiton:maintenance off`. Si `APP_KEY` a changé depuis la sauvegarde : restaurer avec l'ancienne clé en `APP_PREVIOUS_KEYS` puis `xomiton:reencrypt` (§8).
- **Repli archive ZIP** : Administration › Sauvegardes › aperçu puis restauration confirmée (RG-13) ; mot de passe = `BACKUP_ARCHIVE_PASSWORD`. Les colonnes chiffrées sont exportées déchiffrées et rechiffrées avec la clé courante.
- Après toute restauration : `xomiton:preflight`, vérifier un compte, un récit, un SOS de test ; consigner perte de données maximale (RPO) et durée (RTO).
- Test de restauration en recette sur le chemin de production : critère de P17-04 (à dater ici : `____`).

## 10. Maintenance courante, purge et rétention

Planifiées (03:00 / 03:30) : OTP, journaux d'envoi (180 j), audit (90 j), anonymisation comptes (30 j) et dossiers (5 ans), sessions, sync, idempotence, rapports, exports RGPD, cache `file`. Contrôles hebdomadaires : `du -sh storage/framework/cache storage/logs`, `failed_jobs`, `mail_outbox`, quota disque, validité TLS, `composer audit` en local avant chaque envoi. Rotation des journaux : `storage/logs/` en `single` : tronquer ou archiver mensuellement.

## 11. Demandes RGPD (traitement manuel)

Délai : 30 jours. Vérifier l'identité par le canal du compte.
- **Export** : la personne l'obtient seule (`me/export`, lien signé, purgé après expiration). Pour une demande reçue hors site : ouvrir un export depuis son compte avec la coordination, jamais par e-mail non chiffré.
- **Effacement** : suppression du compte (anonymisation après 30 j par `accounts:anonymize`) ; la commande `accounts:anonymize` ne traite que les comptes supprimés depuis plus de 30 jours (`--dry-run` pour compter) : tout effacement plus rapide est une décision de la coordination, tracée dans le registre. Les dossiers actifs suivent leur rétention propre (5 ans clos, `cases:anonymize`).
- **Désinscription newsletter** : lien en un clic, ou Administration › Newsletter › Abonné·es › suppression RGPD.
- Consigner dans le registre des demandes (date, nature, action, personne qui a traité). Ne jamais citer un récit dans un ticket.

## 12. SOS non escaladé / garde

Un SOS actif sans accusé après 5 min ± 1 doit remonter au point focal national (RG-05). Si ce n'est pas le cas : §4 (cron), puis appeler le point focal et la personne de garde, ouvrir le SOS dans le tableau des alertes et l'accuser réception à la main. Planning de garde et contacts d'escalade : à renseigner par la coordination avant la production.

| Rôle | Nom | Téléphone | Relais |
|---|---|---|---|
| Garde technique (semaine) | | | |
| Point focal national | | | |
| Coordination | | | |
| Hébergeur (support cPanel) | | | |

## 13. Incident de sécurité

1. **Contenir** : `xomiton:maintenance on` si la plateforme est touchée ; changer mots de passe cPanel / FTP / SSH / base ; révoquer jetons API cPanel et clés SMTP / SMS.
2. **Préserver** : copier `storage/logs`, journaux d'accès Apache, `audit_logs` ; ne rien supprimer avant copie. Fichiers récents suspects : `find ~/xomiton ~/public_html -type f -mtime -7 -name '*.php' ! -path '*/vendor/*'` ; tâches cron et comptes FTP inconnus.
3. **Éradiquer** : redéployer le code depuis une source saine (jamais « nettoyer » en place), `composer install` propre, rotation de TOUS les secrets (§8, `APP_KEY` avec `xomiton:reencrypt`), invalidation des sessions (`sessions:purge` ou `truncate sessions`), révocation des appareils (`personal_access_tokens`).
4. **Notifier** : coordination immédiatement ; autorité de protection des données (APDP Bénin) et personnes concernées sans délai excessif (72 h visées) si des données personnelles sont exposées.
5. **Rapport** : chronologie, périmètre, données touchées, correctifs, actions préventives ; signé par la coordination (modèle de P17-01).
