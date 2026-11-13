# BD « violences numériques » — lot 2 (15 sujets) : état et reprise

> **Terminé le 13/11/2026** : les 69 images restantes ont été générées à la remise à zéro des crédits (1 régénérée), les 15 JSON écrits et les sujets passés en `pilote` (23/23). Ce fichier reste comme trace des scénarios retenus.

Demande du client du 12/11/2026 : générer les BD des 15 sujets restants (P19). Production arrêtée le 12/11/2026 : **crédits IA Figma épuisés** (« Insufficient Figma AI credits for this operation », remise à zéro annoncée le **13/11/2026 à 00:00 UTC**).

Méthode identique au pilote : `design-system/bd/BIBLE_PERSONNAGES.md` (style, personnages, règles), connecteur Figma `generate_image` (`gpt-image-2.5-flare`), fichier Figma « Xomiton — BD violences numériques ». Images WebP ≤ 100 Ko dans `web/public/assets/images/violences-numeriques/<slug>/`. Un sujet n'est publié qu'avec son JSON complet et le statut `pilote` + sa clé dans `_categories.json` : les images partielles ci-dessous ne sont référencées nulle part.

## État au 12/11/2026

| Clé | Sujet | Images faites (nœuds Figma) | Reste |
|---|---|---|---|
| vn09 | cyberharcelement-pollupostage | couverture 3:3, planches 1–3 (3:12, 3:17, 3:20) | planche 4, partage, JSON |
| vn10 | trolling-raids-numeriques | — | 6 images, JSON |
| vn11 | forums-groupes-de-haine | — | 6 images, JSON |
| vn12 | diffamation-en-ligne | couverture 3:4, planches 1–3 (3:9, 3:13, 3:18) | planche 4, partage, JSON |
| vn13 | messages-non-sollicites | couverture 3:2, planches 1–4 (3:6, 3:10, 3:14, 3:21) | partage, JSON |
| vn14 | revenge-porn | — | 6 images, JSON |
| vn15 | sollicitation-sexuelle-technologies | — | 6 images, JSON |
| vn16 | surveillance-suivi-non-consenti | couverture 3:7, planches 1–3 (3:11, 3:16, 3:19) | planche 4, partage, JSON |
| vn17 | controle-des-appareils | — | 6 images, JSON |
| vn18 | manipulation-psychologique | — | 6 images, JSON |
| vn19 | refus-services-acces-bloques | couverture 3:5, planches 1–3 (3:8, 3:15, 3:22) | planche 4, partage, JSON |
| vn20 | piratage | — | 6 images, JSON |
| vn21 | vol-identite | — | 6 images, JSON |
| vn22 | doxing | — | 6 images, JSON |
| vn23 | intelligence-artificielle | — | 6 images, JSON |

**21 images faites, 69 à générer.**

## Scénarios retenus (à respecter à la reprise, pour la cohérence des images déjà faites)

- **vn09 cyberharcèlement** — Mariam (stand de réparation, marché de Cotonou), Kossi, Tantie Rosine, Nadia. 1 : son téléphone déborde de bulles et d'avatars anonymes ; 2 : elle coupe les notifications, range captures et comptes dans un dossier verrouillé et un carnet ; 3 : cour sous un manguier, Rosine la soutient, Kossi montre des réglages de protection ; **4 (à faire)** : avec Nadia, elle signale et bloque.
- **vn10 trolling et raids** — Sena, militante LBTQ+ à Porto-Novo ; Afi et Kossi solidaires ; la modération agit ; jamais la foule haineuse en gros plan.
- **vn11 groupes de haine** — Kossi ajouté à un groupe WhatsApp sexiste : il quitte le groupe et le signale, avec Afi, Tantie Rosine et Nadia.
- **vn12 diffamation** « Ce qu’on dit de Mariam » — marché de Cotonou. 1 : mensonges sur Mariam dans un groupe Facebook et des statuts ; 2 : elle ne répond pas à chaud, garde captures, liens, dates ; 3 : elle se confie à Tantie Rosine ; **4 (à faire)** : avec Nadia au stand, signalement (drapeau, bouclier validé), la publication se dissout, une cliente revient.
- **vn13 messages non sollicités** — Mariam, Tantie Rosine, Nadia, marché de Cotonou ; planches faites ; **partage (à faire)** : Mariam et Nadia sous un bouclier.
- **vn14 revenge porn** — Kossi reçoit le contenu dans un groupe WhatsApp, refuse de le partager, prévient Sena avec délicatesse et le ou la soutient ; demandes de retrait aux plateformes ; Nadia accompagne. Aucune image intime, même suggérée.
- **vn15 sollicitation sexuelle** — Afi à Porto-Novo reçoit des demandes insistantes : refuse, pose ses limites, bloque, signale ; en parle à Tantie Rosine ou Mariam ; Nadia guide vers l'aide.
- **vn16 surveillance** « Qui lit mes messages ? » — Tantie Rosine, Porto-Novo. 1 : son mari lui répète ce qu'elle a écrit à sa sœur ; 2 : elle repère les signes (batterie, chaleur, appli inconnue), ne supprime rien, note tout ; 3 : chez Mariam au marché : « Ne supprime rien tout de suite, ça pourrait l'alerter. Plan d'abord. » ; **4 (à faire)** : depuis le téléphone de Mariam, Rosine contacte Nadia, plan de sécurité (tablette : bouclier, cadenas, cœur), signalement.
- **vn17 contrôle des appareils** « Chez moi, c'est chez moi » — Mariam et Kossi, appartement à Cotonou : caméra Wi-Fi installée par l'ex ; elle ne débranche rien sous le coup de la colère, note les heures ; liste des objets connectés avec Kossi ; réinitialisation et nouveaux mots de passe après un plan de sécurité avec Nadia.
- **vn18 manipulation psychologique** « Garder le lien » — Afi, Sena, Tantie Rosine : le copain d'Afi a bloqué Sena et sa mère ; phrases qui isolent ; mot code et heures d'appel chez Rosine ; avec Nadia, nommer l'emprise, signaler, garder le lien par le téléphone de Sena.
- **vn19 accès bloqués** « Mes accès, ma liberté » — Mariam et Tantie Rosine, Porto-Novo. 1 : portefeuille Mobile Money, messagerie et comptes communs bloqués ; 2 : captures, puis kiosque Mobile Money avec sa pièce d'identité ; 3 : plan de sécurité en icônes avec Rosine ; **4 (à faire)** : Mariam soulagée (bouclier validé, clé), Nadia et l'ordinateur (drapeau, cœur), signalement et annuaire.
- **vn20 piratage** — Tantie Rosine, WhatsApp piraté par un code demandé dans un faux message ; Kossi l'aide depuis un appareil sûr (mots de passe, double authentification, contacts prévenus) ; Nadia.
- **vn21 vol d'identité** — Tantie Rosine : rappel de prêt sur une ligne Mobile Money ouverte avec sa carte d'identité photocopiée ; elle ne paie rien, garde le SMS ; Mariam explique la contestation en agence officielle (aucun opérateur réel nommé) ; ligne fermée, signalement avec Nadia, photocopies annotées.
- **vn22 doxing** — Afi, Porto-Novo, avec Kossi, Tantie Rosine, Nadia : adresse, numéro et photos d'une rencontre associative LBTQ+ publiés (risque d'outing) ; sécurité d'abord, pas seule ; plan de sécurité (où dormir, qui prévenir, mot code) ; signalement, demande de retrait, bouton SOS à portée.
- **vn23 intelligence artificielle** — Afi : sa vidéo TikTok envahie de commentaires identiques de comptes automatisés ; Sena l'aide à reconnaître les robots (comptes récents, sans photo, messages identiques) ; ne pas répondre, preuves, signalement ; IA représentée par des icônes abstraites, jamais un robot.

## Reprise

1. Générer les 69 images restantes (respect strict de la bible et des scénarios ci-dessus ; 6 images par sujet complet).
2. Convertir en WebP ≤ 100 Ko (1200 px ; partage 1080 × 1080).
3. Écrire les 15 JSON au format des pilotes (`legal: null`, liens d'aide réels : `/signaler`, `/suivi`, `/sos`, `/urgences`, `/aide`, `/inscription` ; `/annuaire` réservé aux membres).
4. Passer chaque sujet en `pilote` avec sa clé dans `_categories.json`, puis `php artisan xomiton:violences-numeriques:import --publish`.
