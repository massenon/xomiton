#!/usr/bin/env python3
"""Construit index.html (fichier unique, ouvrable en double-clic) à partir de src/simulateur.html :
remplace chaque {{IMG:fichier}} par l'image de src/images/ encodée en data URI.
Usage : python3 build.py"""
import base64, pathlib, re

ROOT = pathlib.Path(__file__).resolve().parent
SRC, IMAGES, OUT = ROOT / 'src' / 'simulateur.html', ROOT / 'src' / 'images', ROOT / 'index.html'
MIME = {'.jpg': 'image/jpeg', '.jpeg': 'image/jpeg', '.png': 'image/png', '.webp': 'image/webp', '.svg': 'image/svg+xml'}

def data_uri(name: str) -> str:
    f = IMAGES / name
    return f'data:{MIME[f.suffix.lower()]};base64,' + base64.b64encode(f.read_bytes()).decode('ascii')

html = SRC.read_text(encoding='utf-8')
cache = {}
out = re.sub(r'\{\{IMG:([\w.-]+)\}\}', lambda m: cache.setdefault(m.group(1), data_uri(m.group(1))), html)
OUT.write_text(out, encoding='utf-8')
print(f'{OUT.name} : {len(out) / 1024:.0f} Ko, {len(cache)} images embarquées')
