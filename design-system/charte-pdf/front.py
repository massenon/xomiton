# -*- coding: utf-8 -*-
from kit import *
from structure import CHAPTERS, CH_COLORS

def arcs_svg(w=297, h=210, cx=168, cy=104, r0=18, step=13.2, sw=12.2, colors=None, flip=False):
    colors = colors or ['#F6B8CB', '#6F2C91', '#0433C3', '#1B1630', '#E30E0E', '#F0A800']
    paths = []
    n = len(colors)
    for i, c in enumerate(colors):
        r = r0 + (n - 1 - i) * step   # le plus grand rayon = première couleur (en haut)
        y = cy - r
        paths.append('<path d="M-6 %.2f H%.2f A%.2f %.2f 0 0 1 %.2f %.2f V%d" fill="none" stroke="%s" stroke-width="%.2f"/>' % (y, cx, r, r, cx + r, cy, h + 10, c, sw))
    return '<svg viewBox="0 0 %d %d" style="position:absolute;left:0;top:0;width:%dmm;height:%dmm">%s</svg>' % (w, h, w, h, ''.join(paths))

def cover(doc):
    doc.cur_ch = None
    html = ('<div class="page white" style="background:#fff">' + arcs_svg() +
            '<div class="abs" style="left:12mm;top:11mm">%s</div>' % logo('couleur', 12) +
            '<div class="abs mt" style="right:12mm;top:12mm;font-size:7.5pt;font-weight:700;text-align:right;line-height:1.5">Version 1.0<br>Octobre 2026</div>'
            '<div class="abs" style="left:12mm;top:122mm;width:150mm"><h1 style="font:800 40pt/0.98 Montserrat;letter-spacing:-.02em;text-transform:uppercase">Charte<br>graphique</h1>'
            '<div class="mt" style="margin-top:5mm;font:600 11pt/1.35 Montserrat;max-width:110mm">Xomiton : votre espace safe d\'apprentissage et d\'appui holistique</div>'
            '<div style="margin-top:3mm;font-size:8pt;color:#5B5670;max-width:115mm;line-height:1.45">Référentiel de l\'identité visuelle, du design system et des règles d\'usage, pour toute personne qui produit un support au nom de Xomiton.</div></div>'
            '</div>')
    doc.add(html, cls='cover')

def statement(doc):
    html = ('<div class="page ink" style="background:#0433C3;color:#fff">'
            '<div class="abs mt" style="left:12mm;top:14mm;right:46mm;font:600 19.5pt/1.32 Montserrat;letter-spacing:-.01em">'
            'Xomiton accompagne des personnes en situation de vulnérabilité. Une identité qui varie d\'un support à l\'autre abîme la confiance avant même qu\'on ait lu une ligne, et une interface confuse ou alarmante peut coûter du temps à quelqu\'un qui en manque.<br><br>'
            'Ces règles sont donc des conditions de sécurité et d\'accessibilité, pas des préférences esthétiques. Elles tiennent en une phrase : <u>la sécurité de la personne passe avant l\'esthétique.</u>'
            '</div>'
            '<div class="abs" style="left:12mm;bottom:22mm;width:150mm;font-size:8pt;line-height:1.5;opacity:.88">Les valeurs chiffrées de ce document (rapports de contraste, tailles minimales, durées) sont opposables : elles ont été calculées, pas estimées. '
            'Chaque chapitre suit le même ordre : le sens d\'abord, la règle ensuite, les interdits en dernière page.</div>'
            '<div class="abs" style="right:12mm;top:14mm;opacity:.95">%s</div>{{FOOT}}</div>' % sym('rosace-blanc', 26))
    doc.add(html, cls='ink')

def toc(doc):
    doc.cur_ch = None
    def block(ch):
        num, fr, en, color, secs = ch
        col = CH_COLORS[color]
        rows = ''.join('<div style="display:flex;gap:2mm;font-size:7.2pt;line-height:1.65;border-bottom:.15mm dotted #bbb"><span style="flex:1">%s</span><b class="mt" style="font-size:7pt">[[PN:%d.%s]]</b></div>' % (s[1], num, s[0]) for s in secs)
        return ('<div><div style="display:flex;align-items:baseline;gap:3mm;border-top:.7mm solid %s;padding-top:2mm"><span class="mt" style="font:800 22pt/1 Montserrat;color:%s">%d</span>'
                '<span class="mt" style="font:800 10.5pt/1.1 Montserrat;flex:1">%s</span><b class="mt" style="font-size:7pt">[[PN:ch%d]]</b></div>'
                '<div style="margin-top:2mm">%s</div></div>') % (col, col, num, fr, num, rows)
    for part, chs in (('Sommaire (1/3)', CHAPTERS[:6]), ('Sommaire (2/3)', CHAPTERS[6:11]), ('Sommaire (3/3)', CHAPTERS[11:])):
        grid = '<div class="abs" style="left:12mm;right:12mm;top:30mm;display:grid;grid-template-columns:repeat(3,1fr);gap:6mm 9mm">%s</div>' % ''.join(block(c) for c in chs)
        html = '<div class="page"><div class="hd" style="width:200mm"><b style="font-size:20pt;line-height:1">%s</b></div>%s{{FOOT}}</div>' % (part, grid)
        doc.add(html, sec='toc' if '1/3' in part else None)
