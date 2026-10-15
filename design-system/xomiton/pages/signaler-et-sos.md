# Surcharge — Signaler (`/signaler`) et SOS (`/sos`)

> Prime sur MASTER.md. Règle de la charte : **la sécurité de la personne passe avant l'esthétique.**

**Style** : le plus sobre du produit. **Variance** 1-2 (centré, une colonne), **mouvement** nul hors anneau de progression du SOS, **densité** faible (espacement 24-48 px). Fond `--xm-bg` uni, **aucune illustration arc-en-ciel**, aucun décor flou, aucun effet de verre.

## Signaler (formulaire en étapes)
- Une seule colonne, largeur 640 px max, **une question par écran** sur mobile ; stepper « Étape 2 sur 6 » avec `aria-current="step"` ; bouton « Retour » toujours présent ; **sauvegarde locale à chaque étape** (IndexedDB), message « Tu peux t'arrêter et reprendre plus tard ».
- Ton calme (voir microcopy `xomiton_implementation/11 §6`), aucun mot alarmiste ; texte de consigne ≥ 18 px.
- Anonymat en tête de formulaire dans un encadré `.alert-info` : ce que nous faisons de tes données, qui les voit.
- Contact : choix explicite *anonyme* / *contactable* ; canal préféré (SMS, WhatsApp, appel, e-mail).
- Preuves facultatives, avec avertissement de suppression des métadonnées.
- Récapitulatif relisible avant envoi ; **code de suivi** affiché en grand + copier + imprimer.
- Un seul bouton primaire par étape (« Continuer », puis « Envoyer mon signalement »). **Pas de rouge** pour « Envoyer » (le rouge ne sert qu'à l'accès SOS) ; le bleu rassure.
- Bouton « Quitter ce site » toujours visible.

## SOS
- `x-sos-button` 64 px (public : bas droite ; mobile : centre de la barre d'onglets). Page `/sos` : un seul grand bouton 96 px, texte « Maintiens 2 secondes pour envoyer ton alerte », case « Partager ma position » cochée par défaut avec explication.
- Maintien 2 s → décompte 5 s annulable (bouton « Annuler » 52 px, haut contraste) → « Ton alerte est partie » (vert, `aria-live="assertive"`) + liste des personnes prévenues + **bouton d'appel du contact principal** + « Je suis en sécurité ».
- **Alternatives** : double appui + confirmation ; commande clavier ; compatible lecteur d'écran.
- Mode hors ligne : « Tu es hors ligne. Ton alerte partira dès que le réseau revient » + bouton d'appel direct vers les numéros d'urgence mis en cache.
- Écran de verrouillage : notifications neutres (« Nouveau message »), aucun détail.
- Le rouge est autorisé ici (bouton SOS, décompte) car c'est précisément son sens ; **aucun autre élément rouge** à l'écran.
