"""Rend chaque SVG du dossier en PNG haute définition (fond transparent) avec Edge headless."""
import json, os, re, glob
from playwright.sync_api import sync_playwright

D = os.path.dirname(os.path.abspath(__file__))
PNG = os.path.join(D, 'png')
os.makedirs(PNG, exist_ok=True)

def vb(path):
    s = open(path, encoding='utf-8').read()
    m = re.search(r'viewBox="([-\d. ]+)"', s)
    x, y, w, h = [float(v) for v in m.group(1).split()]
    return w, h

# largeur cible en pixels (HD)
TARGET = {
    'logo-': 6000, 'logo-vertical-': 4000, 'symbole-': 3000, 'icone-appli-': 2048,
}
def target_w(name):
    for k in ('logo-vertical-', 'icone-appli-', 'symbole-', 'logo-'):
        if name.startswith(k):
            return TARGET[k]
    return 3000

with sync_playwright() as p:
    b = p.chromium.launch(channel='msedge')
    for svg in sorted(glob.glob(os.path.join(D, '*.svg'))):
        name = os.path.splitext(os.path.basename(svg))[0]
        w, h = vb(svg)
        tw = target_w(name)
        th = round(tw * h / w)
        sizes = [(tw, th)]
        if tw > 1200:
            sizes.append((1200, round(1200 * h / w)))   # version web
        for (W, H) in sizes:
            pg = b.new_page(viewport={'width': W, 'height': H}, device_scale_factor=1)
            markup = open(svg, encoding='utf-8').read().replace('<svg ', f'<svg width="{W}" height="{H}" style="display:block" ', 1)
            pg.set_content(f'<html><body style="margin:0;background:transparent">{markup}</body></html>')
            pg.wait_for_timeout(250)
            out = os.path.join(PNG, f'{name}-{W}w.png')
            pg.screenshot(path=out, omit_background=True, clip={'x': 0, 'y': 0, 'width': W, 'height': H})
            pg.close()
            print(os.path.basename(out), W, 'x', H, round(os.path.getsize(out) / 1024), 'Ko')
    b.close()
