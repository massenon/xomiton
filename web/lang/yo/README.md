# Yoruba (yorùbá) — locale prévue, non activée

Code de locale : `yo` (ISO 639-1). Statut : **prévue** (`config('xomiton.locales_planned')`), absente de `config('xomiton.locales')` tant que la traduction n'est pas complète (Q40, `IMPLEMENTATION_PLAN.md` §7). Le sélecteur de langue et `PATCH /api/v1/me/settings {locale}` refusent `yo` jusque-là ; l'interface affiche « Yorùbá (bientôt) ».

## Procédure de traduction

1. **Copier la structure française** : chaque fichier de `lang/fr/*.php` et `lang/fr/enums/*.php` doit exister ici avec les **mêmes clés** (les valeurs manquantes retombent sur le français grâce à `APP_FALLBACK_LOCALE=fr`, mais une locale n'est « complète » que sans repli).
   ```bash
   cd web
   for f in lang/fr/*.php lang/fr/enums/*.php; do mkdir -p "lang/yo/$(dirname "${f#lang/fr/}")"; cp "$f" "lang/yo/${f#lang/fr/}"; done
   ```
2. **Traduire les valeurs, jamais les clés ni les placeholders** (`:count`, `:name`…). Les pluriels gardent la syntaxe Laravel `{0} …|{1} …|[2,*] …`.
3. **Respecter le registre de chaque fichier** (tu / vous, `VOICE_AND_TONE.md` §3) et le vocabulaire (`signalement`, jamais « dénonciation » à l'écran, Q29 ; sigle `LBTQ+`). Les écrans de crise (`alerts.php`, `errors.php`) sont relus par une personne formée à l'accueil des victimes de violences. Les signes diacritiques du yoruba (ẹ, ọ, ṣ, tons) sont conservés en UTF-8 : vérifier le rendu avec la police Montserrat.
4. **Dates et nombres** : adapter `dates.php` (mois, jours, formats ICU) ; `App\Support\LocaleFormatter` utilise la locale intl `yo-BJ` avec repli `yo-NG` puis `fr-BJ` (`config('xomiton.intl_locales')`).
5. **Exporter** pour Vue et Flutter : `php artisan lang:export --locale=yo` produit `resources/js/lang/yo.json` et `mobile/lib/l10n/app_yo.arb` ; `php artisan lang:export --locale=yo --check` vérifie en CI que les exports sont à jour.
6. **Activer** : ajouter `'yo'` à `config('xomiton.locales')` et le retirer de `locales_planned` **uniquement** quand `lang/yo` couvre 100 % des clés de `lang/fr` (test `LangConsistencyTest` : « une locale listée est complète ») et que `flutter gen-l10n` passe sur l'ARB.

Relecture : deux locuteur·rices natif·ves, dont une personne de l'association ; glossaire commun fon / yoruba / français à constituer dans `docs/` avant la première livraison.
