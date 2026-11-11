#!/usr/bin/env python3
"""Génère les icônes Android de Xomiton depuis design-system/logo/icone-appli-bleu.svg.

Sans réseau ni outil externe : seul Pillow est nécessaire (`pip install pillow`).
Le script lit le SVG (un fond arrondi + un tracé de rosace en `M C Z` absolus),
trace la rosace en suréchantillonnage puis écrit, sous
`android/app/src/main/res/` :

- `mipmap-*/ic_launcher.png` : icône classique (carré arrondi bleu, rosace blanche) ;
- `mipmap-*/ic_launcher_foreground.png` : premier plan de l'icône adaptative
  (rosace blanche seule, dans la zone de sécurité de 66 dp sur 108 dp) ; il sert
  aussi d'icône monochrome (Android 13, icônes thématiques) ;
- `drawable-*dpi/splash_mark.png` et `drawable-night-*dpi/splash_mark.png` :
  la rosace de l'écran de démarrage (bleue en clair, blanche en sombre) ;
- `../ic_launcher-playstore.png` : icône 512 × 512 de la fiche Play (plein cadre).

Usage : `python3 tool/android_icons.py` depuis `mobile/`.
"""
import re
from pathlib import Path

from PIL import Image, ImageDraw

ROOT = Path(__file__).resolve().parents[1]
SVG = ROOT.parent / 'design-system' / 'logo' / 'icone-appli-bleu.svg'
RES = ROOT / 'android' / 'app' / 'src' / 'main' / 'res'

DENSITIES = {'mdpi': 1.0, 'hdpi': 1.5, 'xhdpi': 2.0, 'xxhdpi': 3.0, 'xxxhdpi': 4.0}
MASTER = 2048
SS = 2  # suréchantillonnage supplémentaire pour l'anticrénelage


def read_svg():
    s = SVG.read_text(encoding='utf-8')
    vb = [float(v) for v in re.search(r'viewBox="([-\d. ]+)"', s).group(1).split()]
    rx = float(re.search(r'<rect[^>]*\brx="([\d.]+)"', s).group(1))
    fill = re.search(r'<rect[^>]*\bfill="(#[0-9A-Fa-f]{6})"', s).group(1)
    d = re.search(r'<path[^>]*\bd="([^"]+)"', s).group(1)
    return vb, rx, fill, d


def flatten(d, steps=24):
    """Sous-tracés (listes de points) d'un `d` fait de M, C et Z absolus."""
    toks = re.findall(r'[MCZ]|-?\d+(?:\.\d+)?', d)
    subs, cur, i, pt = [], [], 0, (0.0, 0.0)
    while i < len(toks):
        t = toks[i]
        if t == 'M':
            if cur:
                subs.append(cur)
            pt = (float(toks[i + 1]), float(toks[i + 2]))
            cur = [pt]
            i += 3
        elif t == 'C':
            i += 1
            while i < len(toks) and toks[i] not in 'MCZ':
                p1 = (float(toks[i]), float(toks[i + 1]))
                p2 = (float(toks[i + 2]), float(toks[i + 3]))
                p3 = (float(toks[i + 4]), float(toks[i + 5]))
                for k in range(1, steps + 1):
                    u = k / steps
                    a, b, c, e = (1 - u) ** 3, 3 * (1 - u) ** 2 * u, 3 * (1 - u) * u ** 2, u ** 3
                    cur.append((a * pt[0] + b * p1[0] + c * p2[0] + e * p3[0],
                                a * pt[1] + b * p1[1] + c * p2[1] + e * p3[1]))
                pt = p3
                i += 6
        else:  # Z
            i += 1
    if cur:
        subs.append(cur)
    return subs


def rosace_mask(size, vb, subs):
    """Masque L (255 = rosace) du symbole seul, étiré sur tout le carré du SVG."""
    big = size * SS
    m = Image.new('L', (big, big), 0)
    dr = ImageDraw.Draw(m)
    k = big / vb[2]
    for sub in subs:
        dr.polygon([((x - vb[0]) * k, (y - vb[1]) * k) for x, y in sub], fill=255)
    return m.resize((size, size), Image.LANCZOS)


def hex_rgb(h):
    return tuple(int(h[i:i + 2], 16) for i in (1, 3, 5))


def tinted(mask, rgb):
    im = Image.new('RGBA', mask.size, rgb + (0,))
    im.putalpha(mask)
    return im


def main():
    vb, rx, fill, d = read_svg()
    blue = hex_rgb(fill)
    subs = flatten(d)
    full = rosace_mask(MASTER, vb, subs)
    bbox = full.getbbox()
    glyph = full.crop(bbox)  # rosace rognée, proportions d'origine
    ratio = rx / vb[2]

    def legacy(px):
        big = px * SS
        bg = Image.new('RGBA', (big, big), (0, 0, 0, 0))
        ImageDraw.Draw(bg).rounded_rectangle((0, 0, big - 1, big - 1), radius=round(big * ratio), fill=blue + (255,))
        bg = bg.resize((px, px), Image.LANCZOS)
        return Image.alpha_composite(bg, tinted(rosace_mask(px, vb, subs), (255, 255, 255)))

    def place(px, share, rgb):
        """Rosace centrée, de largeur `share` × px, sur fond transparent."""
        w = round(px * share)
        h = round(w * glyph.height / glyph.width)
        g = glyph.resize((w, h), Image.LANCZOS)
        canvas = Image.new('L', (px, px), 0)
        canvas.paste(g, ((px - w) // 2, (px - h) // 2))
        return tinted(canvas, rgb)

    for name, scale in DENSITIES.items():
        out = RES / f'mipmap-{name}'
        out.mkdir(parents=True, exist_ok=True)
        legacy(round(48 * scale)).save(out / 'ic_launcher.png', optimize=True)
        # 108 dp, rosace de 50 dp : dans la zone de sécurité (66 dp).
        place(round(108 * scale), 50 / 108, (255, 255, 255)).save(out / 'ic_launcher_foreground.png', optimize=True)
        for night, rgb in (('', blue), ('night-', (255, 255, 255))):
            sp = RES / f'drawable-{night}{name}'
            sp.mkdir(parents=True, exist_ok=True)
            place(round(96 * scale), 1.0, rgb).save(sp / 'splash_mark.png', optimize=True)

    store = Image.new('RGBA', (512, 512), blue + (255,))
    store = Image.alpha_composite(store, place(512, 0.54, (255, 255, 255)))
    store.convert('RGB').save(RES.parent / 'ic_launcher-playstore.png', optimize=True)
    print('Icônes écrites dans', RES)


if __name__ == '__main__':
    main()
