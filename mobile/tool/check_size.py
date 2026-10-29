#!/usr/bin/env python3
"""Budget de taille des APK (NFR-P1). Usage : check_size.py <debug|release> <fichier.apk>.

Lit tool/size_budget.json, affiche la taille, l'ajoute au résumé GitHub Actions
(`$GITHUB_STEP_SUMMARY`) et sort en erreur si le plafond est dépassé ou si la
taille dépasse la base de référence de plus de `tolerance_pct` %.
"""
import json
import os
import sys
from pathlib import Path

kind, apk = sys.argv[1], Path(sys.argv[2])
budget = json.loads((Path(__file__).parent / 'size_budget.json').read_text(encoding='utf-8'))
mb = apk.stat().st_size / 1024 / 1024
cap = budget[f'{kind}_apk_max_mb']
lines = [f'| APK {kind} | {mb:.1f} Mo | plafond {cap} Mo |']
errors = []
if mb > cap:
    errors.append(f'{mb:.1f} Mo > plafond de {cap} Mo')
base = budget.get(f'baseline_{kind}_apk_mb')
if base:
    limit = base * (1 + budget['tolerance_pct'] / 100)
    lines[0] = lines[0][:-2] + f', base {base} Mo (+{budget["tolerance_pct"]} % = {limit:.1f} Mo) |'
    if mb > limit:
        errors.append(f'{mb:.1f} Mo > base {base} Mo + {budget["tolerance_pct"]} %')
print(lines[0])
summary = os.environ.get('GITHUB_STEP_SUMMARY')
if summary:
    with open(summary, 'a', encoding='utf-8') as f:
        f.write('| Fichier | Taille | Budget |\n|---|---|---|\n' + lines[0] + '\n')
if errors:
    print('Budget de taille dépassé : ' + ' ; '.join(errors), file=sys.stderr)
    sys.exit(1)
