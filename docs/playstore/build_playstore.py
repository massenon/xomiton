#!/usr/bin/env python3
"""Générateur des visuels Google Play de Xomiton : délègue au kit générique `tools/playstore-kit/build_playstore.py`.

La configuration est `docs/playstore/visuels.json` (vrai logo couleur, vrai slogan, vraies captures du site en thème clair
dans `sources/`, jetons de `design-system/tokens/tokens.css`). Les sorties sont dans `docs/playstore/assets/` :
`feature-graphic-1024x500.png`, `screenshots/01-…png … 08-…png`, `icon-512.png` et `rapport-controles.json`.

    python3 docs/playstore/build_playstore.py                 # tout produire et vérifier
    python3 docs/playstore/build_playstore.py --verifier      # vérifier seulement
    python3 docs/playstore/build_playstore.py --seulement 03-sos feature-graphic icone
    python3 docs/playstore/build_playstore.py --regles        # règles Google codées et date de leur relecture

Les captures sources (`sources/*-390x844.png`) sont refaites par `capturer_site.py` (site Laravel lancé en local).
Toutes les options du kit sont acceptées (voir tools/playstore-kit/README.md).
"""
from __future__ import annotations

import sys
from pathlib import Path

ICI = Path(__file__).resolve().parent
KIT = ICI.parents[1] / "tools" / "playstore-kit"
sys.path.insert(0, str(KIT))
import build_playstore as kit  # noqa: E402

if __name__ == "__main__":
    args = sys.argv[1:]
    if "--config" not in args and "--init" not in args:
        args = ["--config", str(ICI / "visuels.json"), *args]
    sys.exit(kit.main(args))
