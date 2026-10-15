"""
Vectorisation du logo Xomiton à partir de images/logo.png (240x66, seule source disponible).
- sépare les encres (bleu / rouge) par couleur, suréchantillonne x12, lisse, trace avec potrace (potracer)
- recolore avec les couleurs de marque validées (#0433C3, #E30E0E)
- produit un SVG par variante ; les PNG haute définition sont rendus ensuite par render_png.py
NB : c'est un REDESSIN VECTORIEL fidèle à logo.png, pas le fichier d'origine du/de la graphiste.
"""
import numpy as np, json, sys
from PIL import Image, ImageFilter
from scipy import ndimage
import potrace

SRC = r'C:\Projects\xomiton_laravel\images\logo.png'
OUT = r'C:\Projects\xomiton_laravel\design-system\logo'
K = 12  # facteur de suréchantillonnage

im = Image.open(SRC).convert('RGBA')
W, H = im.size
a = np.asarray(im).astype(float)
r, g, b, al = a[..., 0], a[..., 1], a[..., 2], a[..., 3] / 255.0

# poids par encre (souples) : bleu = dominante bleue, rouge = dominante rouge
blue = al * np.clip((b - r - 20) / 90.0, 0, 1)
red = al * np.clip((r - b - 20) / 90.0, 0, 1)


def mask_big(m, sigma=0.55):
    img = Image.fromarray((np.clip(m, 0, 1) * 255).astype('uint8'), 'L').resize((W * K, H * K), Image.BICUBIC)
    img = img.filter(ImageFilter.GaussianBlur(K * sigma))
    arr = np.asarray(img).astype(float) / 255.0
    return arr > 0.5


def trace(bm, turd=40, tol=0.9):
    bitmap = potrace.Bitmap(~bm)   # potracer trace les pixels False : on inverse pour tracer la forme
    plist = bitmap.trace(turdsize=turd, turnpolicy=potrace.POTRACE_TURNPOLICY_MINORITY, alphamax=1.25, opticurve=True, opttolerance=tol)
    return plist


def path_d(plist, ox=0.0):
    d = []
    for curve in plist:
        sp = curve.start_point
        d.append('M%.2f %.2f' % (sp.x / K - ox, sp.y / K))
        for seg in curve.segments:
            if seg.is_corner:
                d.append('L%.2f %.2f L%.2f %.2f' % (seg.c.x / K - ox, seg.c.y / K, seg.end_point.x / K - ox, seg.end_point.y / K))
            else:
                d.append('C%.2f %.2f %.2f %.2f %.2f %.2f' % (seg.c1.x / K - ox, seg.c1.y / K, seg.c2.x / K - ox, seg.c2.y / K, seg.end_point.x / K - ox, seg.end_point.y / K))
        d.append('Z')
    return ''.join(d)


bm_blue, bm_red = mask_big(blue, 0.95), mask_big(red, 0.7)

# --- composantes connexes pour isoler les éléments
def components(bm, min_px):
    lab, n = ndimage.label(bm)
    objs = ndimage.find_objects(lab)
    comps = []
    for i, sl in enumerate(objs, start=1):
        area = (lab[sl] == i).sum()
        if area >= min_px:
            comps.append((i, sl, area))
    comps.sort(key=lambda t: t[1][1].start)  # de gauche à droite
    return lab, comps

lab_r, comps_r = components(bm_red, (K * K) * 12)
lab_b, comps_b = components(bm_blue, (K * K) * 6)
print('composantes rouges:', [(c[1][1].start // K, c[1][1].stop // K, int(c[2] // (K * K))) for c in comps_r])
print('composantes bleues:', [(c[1][1].start // K, c[1][1].stop // K, int(c[2] // (K * K))) for c in comps_b])

def comp_path(lab, comp):
    i, sl, area = comp
    m = (lab == i)
    return trace(m), sl

paths_blue = []
for c in comps_b:
    pl, sl = comp_path(lab_b, c)
    paths_blue.append((path_d(pl), sl))
paths_red = []
for c in comps_r:
    pl, sl = comp_path(lab_r, c)
    paths_red.append((path_d(pl), sl))

# repérage : la rosace = toutes les composantes rouges sauf la dernière (4 pétales déconnectés), symbole = dernière
rosace_ids = [c[0] for c in comps_r[:-1]]
rosace_mask = np.isin(lab_r, rosace_ids)
ys, xs = np.where(rosace_mask)
rosace_sl = (slice(int(ys.min()), int(ys.max()) + 1), slice(int(xs.min()), int(xs.max()) + 1))
rosace_d = path_d(trace(rosace_mask))
symbol_d, symbol_sl = paths_red[-1]
paths_red = [(rosace_d, rosace_sl), (symbol_d, symbol_sl)]
blue_all = ''.join(p for p, _ in paths_blue)

bbox = lambda sl: (sl[1].start / K, sl[0].start / K, sl[1].stop / K, sl[0].stop / K)
rb, sb = bbox(rosace_sl), bbox(symbol_sl)
print('rosace bbox', rb, 'symbole bbox', sb)

BLUE, RED = '#0433C3', '#E30E0E'
VARIANTS = {
    # nom : (couleur lettres, couleur rosace+symbole, fond ou None)
    'logo-couleur':        (BLUE, RED, None),
    'logo-sombre':         ('#FFFFFF', '#FF6B6B', None),
    'logo-mono-encre':     ('#1B1630', '#1B1630', None),
    'logo-mono-blanc':     ('#FFFFFF', '#FFFFFF', None),
    'logo-mono-bleu':      (BLUE, BLUE, None),
}
PAD = 6.0
minx = min(b[0] for b in [bbox(sl) for _, sl in paths_blue + paths_red]) - PAD
miny = min(b[1] for b in [bbox(sl) for _, sl in paths_blue + paths_red]) - PAD
maxx = max(b[2] for b in [bbox(sl) for _, sl in paths_blue + paths_red]) + PAD
maxy = max(b[3] for b in [bbox(sl) for _, sl in paths_blue + paths_red]) + PAD
vw, vh = maxx - minx, maxy - miny
print('viewBox', minx, miny, vw, vh)

def svg_h(letters, accent, bg=None):
    bgr = '<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f" fill="%s"/>' % (minx, miny, vw, vh, bg) if bg else ''
    return ('<svg xmlns="http://www.w3.org/2000/svg" viewBox="%.2f %.2f %.2f %.2f" role="img" aria-labelledby="t">'
            '<title id="t">Xomiton</title>%s<path fill="%s" d="%s"/><path fill="%s" d="%s"/><path fill="%s" d="%s"/></svg>'
            % (minx, miny, vw, vh, bgr, letters, blue_all, accent, rosace_d, accent, symbol_d))

for name, (l, ac, bg) in VARIANTS.items():
    open(f'{OUT}\\{name}.svg', 'w', encoding='utf-8').write(svg_h(l, ac, bg))

# --- symbole seul (rosace) : carré avec marge
rx0, ry0, rx1, ry1 = rb
rw, rh = rx1 - rx0, ry1 - ry0
side = max(rw, rh) * 1.0
cx, cy = (rx0 + rx1) / 2, (ry0 + ry1) / 2
def svg_symbol(fill, bg=None, pad_ratio=0.0, rounded=0):
    s = side * (1 + pad_ratio * 2)
    x0, y0 = cx - s / 2, cy - s / 2
    bgr = ('<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f" rx="%.2f" fill="%s"/>' % (x0, y0, s, s, s * rounded, bg)) if bg else ''
    return ('<svg xmlns="http://www.w3.org/2000/svg" viewBox="%.2f %.2f %.2f %.2f" role="img" aria-labelledby="t"><title id="t">Xomiton, rosace</title>%s<path fill="%s" d="%s"/></svg>'
            % (x0, y0, s, s, bgr, fill, rosace_d))
open(f'{OUT}\\symbole-rosace-couleur.svg', 'w', encoding='utf-8').write(svg_symbol(RED))
open(f'{OUT}\\symbole-rosace-blanc.svg', 'w', encoding='utf-8').write(svg_symbol('#FFFFFF'))
open(f'{OUT}\\symbole-rosace-encre.svg', 'w', encoding='utf-8').write(svg_symbol('#1B1630'))
open(f'{OUT}\\icone-appli-bleu.svg', 'w', encoding='utf-8').write(svg_symbol('#FFFFFF', BLUE, 0.42, 0.22))
open(f'{OUT}\\icone-appli-blanc.svg', 'w', encoding='utf-8').write(svg_symbol(RED, '#FFFFFF', 0.42, 0.22))
open(f'{OUT}\\icone-appli-sombre.svg', 'w', encoding='utf-8').write(svg_symbol('#FF6B6B', '#0E1020', 0.42, 0.22))

# --- symbole ⚧ seul
sx0, sy0, sx1, sy1 = sb
sw, sh = sx1 - sx0, sy1 - sy0
ss = max(sw, sh)
scx, scy = (sx0 + sx1) / 2, (sy0 + sy1) / 2
open(f'{OUT}\\symbole-genre-couleur.svg', 'w', encoding='utf-8').write(
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="%.2f %.2f %.2f %.2f" role="img"><title>Xomiton, symbole</title><path fill="%s" d="%s"/></svg>'
    % (scx - ss / 2, scy - ss / 2, ss, ss, RED, symbol_d))

# --- logo vertical : rosace au-dessus du mot (mot réduit à 100 %, rosace agrandie)
gap = 10.0
big = 1.9
rosace_w, rosace_h = rw * big, rh * big
total_w = max(vw, rosace_w + 2 * PAD)
total_h = rosace_h + gap + vh
def svg_vertical(letters, accent):
    dx = (total_w - vw) / 2
    rx = (total_w - rosace_w) / 2
    return ('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %.2f %.2f" role="img"><title>Xomiton</title>'
            '<g transform="translate(%.2f %.2f) scale(%.3f) translate(%.2f %.2f)"><path fill="%s" d="%s"/></g>'
            '<g transform="translate(%.2f %.2f) translate(%.2f %.2f)"><path fill="%s" d="%s"/><path fill="%s" d="%s"/><path fill="%s" d="%s"/></g></svg>'
            % (total_w, total_h, rx, 0, big, -rx0, -ry0, accent, rosace_d,
               dx, rosace_h + gap, -minx, -miny, letters, blue_all, accent, rosace_d, accent, symbol_d))
open(f'{OUT}\\logo-vertical-couleur.svg', 'w', encoding='utf-8').write(svg_vertical(BLUE, RED))
open(f'{OUT}\\logo-vertical-sombre.svg', 'w', encoding='utf-8').write(svg_vertical('#FFFFFF', '#FF6B6B'))

json.dump({'viewBox': [minx, miny, vw, vh], 'ratio': vw / vh, 'rosace': rb, 'symbole': sb}, open(f'{OUT}\\logo-meta.json', 'w'))
print('OK')
