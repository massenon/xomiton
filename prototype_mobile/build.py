#!/usr/bin/env python3
"""Construit les pages (fichiers uniques, ouvrables en double-clic) à partir de src/*.html :
remplace chaque {{INCLUDE:fichier}} par le contenu de src/partials/ (noyau commun des maquettes), puis chaque
{{IMG:fichier}} par l'image de src/images/ encodée en data URI.
src/simulateur.html -> index.html ; src/espace-pair.html -> espace-pair.html ; src/admin-mobile.html -> admin-mobile.html.
Usage : python3 build.py"""
import base64, pathlib, re

ROOT = pathlib.Path(__file__).resolve().parent
IMAGES, PARTIALS = ROOT / 'src' / 'images', ROOT / 'src' / 'partials'
OUTS = {'simulateur.html': 'index.html'}  # les autres pages gardent leur nom
MIME = {'.jpg': 'image/jpeg', '.jpeg': 'image/jpeg', '.png': 'image/png', '.webp': 'image/webp', '.svg': 'image/svg+xml'}

def data_uri(name: str) -> str:
    f = IMAGES / name
    return f'data:{MIME[f.suffix.lower()]};base64,' + base64.b64encode(f.read_bytes()).decode('ascii')

cache = {}
for src in sorted((ROOT / 'src').glob('*.html')):
    out = ROOT / OUTS.get(src.name, src.name)
    html = re.sub(r'\{\{INCLUDE:([\w.-]+)\}\}', lambda m: (PARTIALS / m.group(1)).read_text(encoding='utf-8'), src.read_text(encoding='utf-8'))
    html = re.sub(r'\{\{IMG:([\w./-]+)\}\}', lambda m: cache.setdefault(m.group(1), data_uri(m.group(1))), html)
    out.write_text(html, encoding='utf-8')
    print(f'{out.name} : {len(html) / 1024:.0f} Ko')
