# -*- coding: utf-8 -*-
"""Noyau de mise en page de la charte Xomiton (A4 paysage). Inspiré des pages Olympic/Instacart :
pages de chapitre en aplat, index à gauche, pages de contenu en colonnes sur fond clair, panneaux de spécimen sombres."""
import re, colorsys
from structure import CHAPTERS, CH_COLORS

FONTS = '../fonts/'
IMG = '../../images/'
HOME = '../../xomiton/public/assets/images/homepage/'
ELT = '../../xomiton/public/assets/images/elements/'
LOGO = '../logo/'
ICONCSS = '../../xomiton/public/assets/vendor/bootstrap-icons/bootstrap-icons.css'

BRAND = {
    'blue': '#0433C3', 'red': '#E30E0E', 'ink': '#1B1630', 'bg': '#FBF7FA', 'white': '#FFFFFF',
    'violet': '#6F2C91', 'rose': '#C2255C', 'orange': '#B84B00', 'green': '#0B7A4B', 'amber': '#F0A800', 'sky': '#1864AB',
    'muted': '#5B5670', 'border': '#E4DCE6',
}

def rgb(h):
    h = h.lstrip('#')
    if len(h) == 3: h = ''.join(c * 2 for c in h)
    return tuple(int(h[i:i + 2], 16) for i in (0, 2, 4))

def hsl(h):
    r, g, b = [v / 255 for v in rgb(h)]
    hh, l, s = colorsys.rgb_to_hls(r, g, b)
    return '%d°, %d %%, %d %%' % (round(hh * 360), round(s * 100), round(l * 100))

def cmyk(h):
    r, g, b = [v / 255 for v in rgb(h)]
    k = 1 - max(r, g, b)
    if k >= 1: return '0, 0, 0, 100'
    c, m, y = [(1 - v - k) / (1 - k) for v in (r, g, b)]
    return '%d, %d, %d, %d' % tuple(round(v * 100) for v in (c, m, y, k))

def lum(h):
    def f(v):
        v /= 255
        return v / 12.92 if v <= 0.03928 else ((v + 0.055) / 1.055) ** 2.4
    r, g, b = rgb(h)
    return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b)

def cr(a, b):
    x, y = lum(a), lum(b)
    hi, lo = max(x, y), min(x, y)
    return round((hi + 0.05) / (lo + 0.05), 2)

CSS = r"""
@font-face{font-family:'Montserrat';font-style:normal;font-weight:100 900;src:url('FONTS/Montserrat-latin-normal.woff2') format('woff2');unicode-range:U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD}
@font-face{font-family:'Montserrat';font-style:normal;font-weight:100 900;src:url('FONTS/Montserrat-latin-ext-normal.woff2') format('woff2');unicode-range:U+0100-02BA,U+02BD-02C5,U+02C7-02CC,U+02CE-02D7,U+02DD-02FF,U+0304,U+0308,U+0329,U+1D00-1DBF,U+1E00-1E9F,U+1EF2-1EFF,U+2020,U+20A0-20AB,U+20AD-20C0,U+2113,U+2C60-2C7F,U+A720-A7FF}
@font-face{font-family:'Montserrat';font-style:normal;font-weight:100 900;src:url('FONTS/Montserrat-vietnamese-normal.woff2') format('woff2');unicode-range:U+0102-0103,U+0110-0111,U+0128-0129,U+0168-0169,U+01A0-01A1,U+01AF-01B0,U+0300-0301,U+0303-0304,U+0308-0309,U+0323,U+0329,U+1EA0-1EF9,U+20AB}
@font-face{font-family:'Montserrat';font-style:italic;font-weight:100 900;src:url('FONTS/Montserrat-latin-italic.woff2') format('woff2');unicode-range:U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD}
@page{size:297mm 210mm;margin:0}
*{box-sizing:border-box}
:root{--blue:#0433C3;--red:#E30E0E;--ink:#1B1630;--bg:#F6F2F7;--white:#fff;--muted:#5B5670;--line:#D8CFDC;--violet:#6F2C91;--rose:#C2255C;--orange:#B84B00;--green:#0B7A4B;--amber:#F0A800;--sky:#1864AB;--blue50:#EBEFFA;--red50:#FDECEC}
html,body{margin:0;padding:0;background:#888}
body{font-family:Arial,'Liberation Sans',Helvetica,sans-serif;color:var(--ink);-webkit-print-color-adjust:exact;print-color-adjust:exact}
.page{width:297mm;height:210mm;position:relative;overflow:hidden;background:var(--bg);page-break-after:always;break-after:page}
.page.white{background:#fff}.page.dark{background:#111}.page.ink{background:var(--ink);color:#fff}
.mt{font-family:'Montserrat','Segoe UI',Arial,sans-serif}
h1,h2,h3,h4{font-family:'Montserrat','Segoe UI',Arial,sans-serif;margin:0}
.hd{position:absolute;left:12mm;top:11mm;width:66mm}
.hd b{display:block;font:800 9.2pt/1.15 'Montserrat',Arial,sans-serif;text-transform:uppercase;letter-spacing:.01em}
.hd i{display:block;margin-top:1.2mm;font:normal 6.4pt/1.2 Arial,sans-serif;color:var(--muted);text-transform:none}
.page.dark .hd i,.page.ink .hd i{color:#bdb8c8}
.cols{position:absolute;left:84mm;top:11mm;right:12mm;display:grid;grid-template-columns:1fr 1fr;gap:7mm;font-size:8.5pt;line-height:1.5}
.cols.c1{grid-template-columns:1fr}.cols.c3{grid-template-columns:1fr 1fr 1fr;gap:6mm}
.cols p{margin:0 0 2.2mm}.cols h4{font:800 9pt/1.2 'Montserrat',Arial,sans-serif;margin:0 0 1.2mm}
.cols .lead{font:600 10.6pt/1.33 'Montserrat',Arial,sans-serif;letter-spacing:-.005em}
.vis{position:absolute;left:12mm;right:12mm;top:44mm;bottom:15mm;background:#fff;border-radius:2.2mm;overflow:hidden}
.vis.tall{top:34mm}.vis.short{top:62mm}.vis.nobg{background:transparent}.vis.dk{background:#111;color:#fff}.vis.tint{background:var(--blue50)}
.side .cols{left:12mm;top:62mm;right:auto;width:62mm;grid-template-columns:1fr;font-size:7.5pt}
.side .vis{left:80mm;top:12mm;bottom:15mm}
.ft{position:absolute;left:12mm;right:12mm;bottom:6mm;display:flex;gap:6mm;align-items:baseline;font:normal 6.2pt Arial,sans-serif;color:var(--muted)}
.ft b{font:700 6.6pt 'Montserrat',Arial,sans-serif;color:var(--ink);min-width:7mm}.ft .sp{flex:1}
.page.dark .ft,.page.ink .ft,.page.dv .ft{color:rgba(255,255,255,.75)}.page.dark .ft b,.page.ink .ft b,.page.dv .ft b{color:#fff}
/* chapitre */
.dv{color:#fff}
.dv .num{position:absolute;left:12mm;top:9mm;font:800 58pt/1 'Montserrat',Arial,sans-serif}
.dv .rule{position:absolute;left:82mm;top:0;bottom:0;width:.3mm;background:rgba(255,255,255,.4)}
.dv .hbox{position:absolute;left:88mm;top:10mm;right:12mm}
.dv h1{font:800 54pt/1.0 'Montserrat',Arial,sans-serif;letter-spacing:-.02em}
.dv .en{margin-top:5mm;font:normal 9pt Arial,sans-serif;opacity:.8}
.dv .idx{position:absolute;left:12mm;right:12mm;top:78mm;font-size:10pt;line-height:1}
.dv .idx div{display:flex;height:7.3mm;align-items:center}
.dv .idx span{width:70mm;font-family:'Montserrat',Arial,sans-serif;font-weight:700;font-size:9.5pt}
.dv .idx em{font-style:normal;font-size:6.5pt;opacity:.75;margin-left:3mm}
/* éléments */
.card{background:#fff;border-radius:2mm;padding:4mm}
.lbl{font:700 6.2pt/1.2 Arial,sans-serif;color:var(--muted);text-transform:uppercase;letter-spacing:.06em}
.tag{display:inline-block;font:700 6.2pt Arial;padding:.6mm 2mm;border-radius:10mm;background:var(--blue50);color:#021C6B}
.ok,.no{position:absolute;top:2.5mm;left:2.5mm;width:5.4mm;height:5.4mm;border-radius:50%;color:#fff;font:700 8pt/5.4mm Arial;text-align:center}
.ok{background:var(--green)}.no{background:var(--red)}
.frame{position:relative;border:.3mm solid var(--line);border-radius:1.6mm;background:#fff;display:flex;align-items:center;justify-content:center;overflow:hidden}
.frame small{position:absolute;bottom:1.8mm;left:3mm;right:3mm;font:normal 6pt/1.25 Arial;color:var(--muted)}
.frame.bad{border-color:var(--red)}.frame.good{border-color:var(--green)}
table.t{border-collapse:collapse;width:100%;font-size:7.2pt}
table.t th{font:700 6.4pt Arial;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);text-align:left;padding:1.5mm 2mm;border-bottom:.35mm solid var(--ink)}
table.t td{padding:1.6mm 2mm;border-bottom:.2mm solid var(--line);vertical-align:top}
.pill{display:inline-block;padding:.5mm 2mm;border-radius:10mm;font:700 6.4pt Arial;color:#fff}
.grid{display:grid;gap:4mm}
.abs{position:absolute}
.bi{line-height:1}
.note{font:italic 6.4pt/1.3 Arial;color:var(--muted)}
.dummy{position:absolute;right:4mm;bottom:3mm;z-index:5;font:700 5.8pt Arial;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;border:.25mm solid var(--line);padding:.5mm 1.6mm;border-radius:10mm;background:#fff}
""".replace('FONTS/', FONTS)


class Doc:
    def __init__(self):
        self.pages = []      # dict(html, sec, ch)
        self.sec_pages = {}  # sec_key -> first page number
        self.cur_ch = None

    def add(self, html, sec=None, cls='', dark=False):
        n = len(self.pages) + 1
        if sec and sec not in self.sec_pages:
            self.sec_pages[sec] = n
        self.pages.append({'html': html, 'sec': sec, 'cls': cls, 'ch': self.cur_ch})
        return n

    # ---- pages « structure »
    def divider(self, ch):
        num, fr, en, color, secs = ch
        self.cur_ch = num
        col = CH_COLORS[color]
        rows = ''.join('<div><b class="mt" style="width:12mm;font-weight:800;font-size:9pt">[[PN:%d.%s]]</b><span>%s<em>%s</em></span></div>' % (num, s[0], s[1], s[2]) for s in secs)
        rows = rows.replace('<div><b class="mt" style="width:12mm;font-weight:800;font-size:9pt">', '<div><b class="mt" style="width:76mm;font-weight:800;font-size:9pt">')
        rows = rows.replace('<span>', '<span style="width:auto">')
        html = ('<div class="page dv" style="background:%s"><div class="num">%d</div><div class="rule"></div>'
                '<div class="hbox"><h1>%s</h1><div class="en">%s</div></div><div class="idx">%s</div>{{FOOT}}</div>') % (col, num, fr, en if en != fr else '', rows)
        self.add(html, sec='ch%d' % num, cls='dv')

    def section(self, ch, sec, title, en, cols, vis, variant='', visclass='', bg='', fresh_title=True, sec_key=None):
        """Page de contenu : titre à gauche, colonnes de texte en haut, grand visuel sous les colonnes."""
        key = '%d.%s' % (ch, sec) if sec else None
        cols_html = ''
        if cols:
            n = len(cols)
            cc = 'c1' if n == 1 else ('c3' if n >= 3 else '')
            cols_html = '<div class="cols %s">%s</div>' % (cc, ''.join('<div>%s</div>' % c for c in cols))
        hd = '<div class="hd"><b>%s</b><i>%s</i></div>' % (title, en) if title else ''
        html = '<div class="page %s %s" %s>%s%s<div class="vis %s">%s</div>{{FOOT}}</div>' % (
            variant, bg, '', hd, cols_html, visclass, vis)
        self.add(html, sec=key)

    def free(self, ch, sec, html, cls='', title=None, en=None, dark=False, key_title=True):
        key = '%d.%s' % (ch, sec) if sec else None
        hd = '<div class="hd"><b>%s</b><i>%s</i></div>' % (title, en or '') if title else ''
        self.add('<div class="page %s">%s%s{{FOOT}}</div>' % (cls, hd, html), sec=key)

    # ---- assemblage
    def build(self, chapter_titles):
        total = len(self.pages)
        out = []
        for i, p in enumerate(self.pages, start=1):
            html = p['html']
            ch = p['ch']
            chname = chapter_titles.get(ch, '')
            foot = '<div class="ft"><b>%d</b><span>Charte graphique Xomiton</span><span class="sp"></span><span>%s</span><span>Version 1.0 · octobre 2026</span></div>' % (i, chname)
            if p['cls'] == 'cover':
                foot = ''
            html = html.replace('{{FOOT}}', foot)
            out.append(html)
        body = '\n'.join(out)
        body = re.sub(r'\[\[PN:([0-9a-z.]+)\]\]', lambda m: str(self.sec_pages.get(m.group(1), '—')), body)
        return body


def swatch(name, hexv, role, size=44, txt=None, extra=''):
    t = txt or ('#fff' if lum(hexv) < 0.35 else '#1B1630')
    rg = rgb(hexv)
    return ('<div style="width:%dmm;text-align:left"><div style="width:%dmm;height:%dmm;border-radius:50%%;background:%s;%s"></div>'
            '<div class="mt" style="font-weight:800;font-size:8.5pt;margin-top:2.2mm">%s</div>'
            '<div style="font-size:6.4pt;line-height:1.55;color:var(--muted)">%s<br>HEX %s<br>RVB %d, %d, %d<br>HSL %s<br>CMJN* %s</div></div>'
            % (size, size, size, hexv, extra, name, role, hexv, rg[0], rg[1], rg[2], hsl(hexv), cmyk(hexv)))

def logo(variant='couleur', h=14, extra=''):
    return '<img src="%slogo-%s.svg" style="height:%smm;display:block;%s" alt="Xomiton">' % (LOGO, variant, h, extra)

def logo_w(variant='couleur', w=60, extra=''):
    return '<img src="%slogo-%s.svg" style="width:%smm;display:block;%s" alt="Xomiton">' % (LOGO, variant, w, extra)

def sym(variant='rosace-couleur', s=20, extra=''):
    return '<img src="%ssymbole-%s.svg" style="width:%smm;height:%smm;display:block;%s" alt="">' % (LOGO, variant, s, s, extra)

def appicon(variant='bleu', s=20, extra=''):
    return '<img src="%sicone-appli-%s.svg" style="width:%smm;height:%smm;display:block;%s" alt="">' % (LOGO, variant, s, s, extra)

def icon(name, size=6, color='currentColor', extra=''):
    return '<i class="bi bi-%s" style="font-size:%smm;color:%s;%s"></i>' % (name, size, color, extra)

def img(path, w=None, h=None, fit='cover', pos='center', extra=''):
    st = 'object-fit:%s;object-position:%s;' % (fit, pos)
    if w: st += 'width:%s;' % w
    if h: st += 'height:%s;' % h
    return '<img src="%s" style="%s%s" alt="">' % (path, st, extra)

def document(body):
    return ('<!doctype html><html lang="fr"><head><meta charset="utf-8"><title>Charte graphique Xomiton v1.0</title>'
            '<link rel="stylesheet" href="%s"><style>%s</style></head><body>%s</body></html>' % (ICONCSS, CSS, body))
