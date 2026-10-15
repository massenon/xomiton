# -*- coding: utf-8 -*-
"""Assemble la charte graphique Xomiton (A4 paysage) -> HTML -> PDF (Edge headless).
Usage : python build.py [--html-only]"""
import os, sys, importlib, re
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from kit import *
from structure import CHAPTERS
import front

HERE = os.path.dirname(os.path.abspath(__file__))
OUT_HTML = os.path.join(HERE, 'charte-graphique-xomiton.html')
OUT_PDF = os.path.join(os.path.dirname(HERE), 'Charte-graphique-Xomiton-v1.0.pdf')

MODULES = ['ch01_03', 'ch04_06', 'ch07_09', 'ch10_12', 'ch12_14']

def main():
    doc = Doc()
    front.cover(doc)
    front.statement(doc)
    front.toc(doc)
    for m in MODULES:
        if os.path.exists(os.path.join(HERE, m + '.py')):
            importlib.import_module(m).build(doc)
        else:
            print('(module absent, ignoré):', m)
    titles = {c[0]: '%d · %s' % (c[0], c[1]) for c in CHAPTERS}
    body = doc.build(titles)
    html = document(body)
    open(OUT_HTML, 'w', encoding='utf-8').write(html)
    print('pages:', len(doc.pages), '| HTML:', OUT_HTML)
    if '--html-only' in sys.argv:
        return
    from playwright.sync_api import sync_playwright
    with sync_playwright() as p:
        b = p.chromium.launch(channel='msedge')
        pg = b.new_page()
        pg.set_default_timeout(300000)
        pg.goto('file:///' + OUT_HTML.replace('\\', '/'), wait_until='load')
        pg.evaluate('document.fonts.ready')
        pg.wait_for_timeout(1500)
        kw = dict(path=OUT_PDF, width='297mm', height='210mm', print_background=True, prefer_css_page_size=True, margin={'top': '0', 'bottom': '0', 'left': '0', 'right': '0'})
        try:
            pg.pdf(tagged=True, **kw)
        except TypeError:
            pg.pdf(**kw)
        b.close()
    # signets (table des matières du PDF) + métadonnées
    import pymupdf
    d = pymupdf.open(OUT_PDF)
    toc = []
    for c in CHAPTERS:
        n = doc.sec_pages.get('ch%d' % c[0])
        if n:
            toc.append([1, '%d  %s' % (c[0], c[1]), n])
        for s in c[4]:
            k = '%d.%s' % (c[0], s[0])
            if k in doc.sec_pages:
                toc.append([2, s[1], doc.sec_pages[k]])
    d.set_toc(toc)
    d.set_metadata({'title': 'Charte graphique Xomiton v1.0', 'author': 'Xomiton', 'subject': 'Identité visuelle et design system', 'keywords': 'Xomiton, charte graphique, design system'})
    tmp = OUT_PDF + '.tmp'
    d.save(tmp, garbage=3, deflate=True); d.close()
    os.replace(tmp, OUT_PDF)
    d = pymupdf.open(OUT_PDF)
    print('PDF:', OUT_PDF, '| pages', d.page_count, '| taille', round(os.path.getsize(OUT_PDF) / 1048576, 1), 'Mo | format', d[0].rect.width, 'x', d[0].rect.height, 'pt')

if __name__ == '__main__':
    main()
