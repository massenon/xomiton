#!/usr/bin/env python3
"""Capture les pages du VRAI site Laravel (thème clair, 390 x 844, échelle x3) pour les visuels Play Store.

Le site doit tourner en local (voir PUBLICATION_PLAY_STORE.md §« Captures ») : base SQLite temporaire, `migrate:fresh --seed`,
`php artisan serve` sur http://127.0.0.1:8000, APP_DEBUG=false. Aucun fichier du dépôt ni `.env` n'est modifié.
Les captures sont des pages WEB à largeur mobile, pas l'application Flutter.

    python3 docs/playstore/capturer_site.py [http://127.0.0.1:8000]
"""
import os, sys
from pathlib import Path
from playwright.sync_api import sync_playwright

BASE = (sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8000").rstrip("/")
SORTIE = Path(__file__).resolve().parent / "sources"
CHROMIUM = os.environ.get("PW_CHROMIUM", "/opt/pw-browsers/chromium")
PUBLIQUES = {"accueil": "/", "signaler": "/signaler", "sos": "/sos", "ressources": "/ressources",
             "thematiques": "/thematiques", "urgences": "/urgences", "aide": "/aide", "actualites": "/actualites"}
# Pages publiques seulement : l'espace membre (assistant, fil, messages) n'est pas capturé, son gabarit mobile web étant
# conçu pour l'application et montrant des avatars photo.

# Défilement (px) avant la capture : pour montrer le contenu plutôt que l'espace d'en-tête de certaines pages.
DEFILEMENT = {"ressources": 520, "actualites": 230, "aide": 835, "thematiques": 0}
# Numéro de démonstration de l'annuaire (jeu de données local) : masqué, un numéro fictif n'a pas à figurer sur une capture.
JS_MASQUE = "document.querySelectorAll('a[href^=\"tel:+229\"]').forEach(a => { (a.closest('.card, li, article') || a).style.visibility = 'hidden'; })"
CSS = "*{caret-color:transparent!important}"

def preparer(pg, y=0):
    """Charge les images différées, puis défile jusqu'à `y` (sans animation) avant la capture."""
    pg.add_style_tag(content="html{scroll-behavior:auto!important}")
    pg.evaluate("document.querySelectorAll('img[loading=lazy]').forEach(i => i.loading = 'eager')")
    h = pg.evaluate("document.documentElement.scrollHeight")
    for pos in range(0, h, 400):
        pg.evaluate(f"window.scrollTo(0,{pos})")
        pg.wait_for_timeout(120)
    pg.evaluate("window.scrollTo(0,0)")
    pg.wait_for_timeout(300)
    pg.evaluate(JS_MASQUE)
    pg.evaluate(f"window.scrollTo(0,{y})")
    pg.wait_for_timeout(500)


def contexte(nav):
    return nav.new_context(viewport={"width": 390, "height": 844}, device_scale_factor=3, locale="fr-FR",
                           color_scheme="light", reduced_motion="reduce")

with sync_playwright() as p:
    nav = p.chromium.launch(executable_path=CHROMIUM if Path(CHROMIUM).is_file() else None)
    ctx = contexte(nav)
    pg = ctx.new_page()
    for nom, chemin in PUBLIQUES.items():
        rep = pg.goto(BASE + chemin, wait_until="networkidle")
        assert rep and rep.status == 200, f"{chemin} : HTTP {rep and rep.status} (site mal lancé ? base non migrée ?)"
        pg.add_style_tag(content=CSS)
        preparer(pg, DEFILEMENT.get(nom, 0))
        pg.screenshot(path=str(SORTIE / f"{nom}-390x844.png"))
        print("capturé", nom)
    nav.close()
