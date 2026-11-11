#!/usr/bin/env python3
"""Génère les icônes iOS de Xomiton depuis design-system/logo/icone-appli-bleu.svg.

ÉCRIT SANS MAC : les PNG produits ici n'ont pas été vérifiés dans Xcode.

Réutilise le tracé de `tool/android_icons.py` (lecture du SVG, rosace, anticrénelage) ;
seul Pillow est nécessaire (`pip install pillow`). Écrit :

- `ios/Runner/Assets.xcassets/AppIcon.appiconset/Icon-App-*.png` : toutes les
  tailles du `Contents.json` (iPhone, plus iPad et App Store 1024 déjà déclarés
  par ce fichier). **Plein cadre, sans transparence, sans coins arrondis** : iOS
  applique lui-même le masque. Mode RVB (pas de canal alpha), exigence de l'App Store.
- `ios/Runner/Assets.xcassets/LaunchMark.imageset/` : rosace de l'écran de
  lancement (bleue en clair, blanche en sombre) et son `Contents.json`.
- `ios/Runner/Assets.xcassets/LaunchBackground.colorset/Contents.json` : fond de
  l'écran de lancement (#FBF7FA en clair, #0E1020 en sombre, comme Android).
- `ios/Runner/AlternateIcons/DiscreetIcon@2x.png` et `@3x.png` : icône
  alternative du mode discret (déclarée dans `Info.plist` sous
  `CFBundleAlternateIcons`, nom `DiscreetIcon`). Neutre et générique : trois
  disques translucides sur un fond ardoise. Elle n'imite ni « Notes » ni aucune
  application Apple (pas de bloc-notes jaune, pas de lignes, pas d'engrenage, pas
  de calculatrice) et ne porte pas la marque.

Usage : `python3 tool/ios_icons.py` depuis `mobile/`.
"""
import importlib.util
import json
from pathlib import Path

from PIL import Image, ImageDraw

ROOT = Path(__file__).resolve().parents[1]
IOS = ROOT / 'ios' / 'Runner'
ICONSET = IOS / 'Assets.xcassets' / 'AppIcon.appiconset'
ASSETS = IOS / 'Assets.xcassets'
ALT = IOS / 'AlternateIcons'

_spec = importlib.util.spec_from_file_location('android_icons', Path(__file__).with_name('android_icons.py'))
android = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(android)

SS = 2


def pixel_size(entry):
    """Taille en pixels d'une entrée du Contents.json (`20x20` + `@2x`)."""
    base = float(entry['size'].split('x')[0])
    return round(base * int(entry['scale'].rstrip('x')))


def build_icon(px, blue, glyph_place):
    im = Image.new('RGBA', (px, px), blue + (255,))
    im = Image.alpha_composite(im, glyph_place(px, 0.54, (255, 255, 255)))
    return im.convert('RGB')  # aucun canal alpha


def discreet_icon(px):
    """Ardoise neutre, trois disques translucides : volontairement sans signification."""
    big = px * SS
    base = Image.new('RGB', (big, big), (0x5B, 0x67, 0x78))
    layer = Image.new('RGBA', (big, big), (0, 0, 0, 0))
    d = ImageDraw.Draw(layer)
    r = big * 0.2
    for cx, cy, a in ((0.40, 0.42, 70), (0.60, 0.42, 70), (0.50, 0.60, 90)):
        d.ellipse((big * cx - r, big * cy - r, big * cx + r, big * cy + r), fill=(255, 255, 255, a))
    out = Image.alpha_composite(base.convert('RGBA'), layer)
    return out.resize((px, px), Image.LANCZOS).convert('RGB')


def write_json(path, data):
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(data, indent=2, ensure_ascii=False) + '\n', encoding='utf-8')


def main():
    vb, _rx, fill, d = android.read_svg()
    blue = android.hex_rgb(fill)
    subs = android.flatten(d)
    full = android.rosace_mask(android.MASTER, vb, subs)
    glyph = full.crop(full.getbbox())

    def place(px, share, rgb):
        w = round(px * share)
        h = round(w * glyph.height / glyph.width)
        g = glyph.resize((w, h), Image.LANCZOS)
        canvas = Image.new('L', (px, px), 0)
        canvas.paste(g, ((px - w) // 2, (px - h) // 2))
        return android.tinted(canvas, rgb)

    contents = json.loads((ICONSET / 'Contents.json').read_text(encoding='utf-8'))
    for entry in contents['images']:
        build_icon(pixel_size(entry), blue, place).save(ICONSET / entry['filename'], optimize=True)

    # Écran de lancement : rosace 96 pt (@1x/@2x/@3x), claire et sombre.
    mark = ASSETS / 'LaunchMark.imageset'
    mark.mkdir(parents=True, exist_ok=True)
    images = []
    for scale in (1, 2, 3):
        for suffix, rgb, appearance in (('', blue, None), ('-dark', (255, 255, 255), 'dark')):
            name = f'LaunchMark{suffix}@{scale}x.png'
            place(96 * scale, 1.0, rgb).save(mark / name, optimize=True)
            item = {'idiom': 'universal', 'filename': name, 'scale': f'{scale}x'}
            if appearance:
                item['appearances'] = [{'appearance': 'luminosity', 'value': appearance}]
            images.append(item)
    write_json(mark / 'Contents.json', {'images': images, 'info': {'author': 'xcode', 'version': 1}})

    def color(r, g, b):
        return {'color-space': 'srgb', 'components': {'red': f'{r / 255:.3f}', 'green': f'{g / 255:.3f}',
                                                      'blue': f'{b / 255:.3f}', 'alpha': '1.000'}}

    write_json(ASSETS / 'LaunchBackground.colorset' / 'Contents.json', {
        'colors': [
            {'idiom': 'universal', 'color': color(0xFB, 0xF7, 0xFA)},
            {'idiom': 'universal', 'appearances': [{'appearance': 'luminosity', 'value': 'dark'}],
             'color': color(0x0E, 0x10, 0x20)},
        ],
        'info': {'author': 'xcode', 'version': 1},
    })

    ALT.mkdir(parents=True, exist_ok=True)
    for scale in (2, 3):
        discreet_icon(60 * scale).save(ALT / f'DiscreetIcon@{scale}x.png', optimize=True)
    print('Icônes iOS écrites dans', IOS)


if __name__ == '__main__':
    main()
