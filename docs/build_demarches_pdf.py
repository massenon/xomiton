#!/usr/bin/env python3
"""Génère docs/DEMARCHES_CLIENT.pdf à partir de docs/DEMARCHES_CLIENT.md (pandoc + Chromium, UTF-8).

Réutilise les styles, polices et fonctions de docs/playstore/build_pdf.py (importé, non modifié).
Aucun texte non ASCII n'est passé en argument de commande ; le script échoue si U+FFFD ou un mojibake est détecté.
Usage : python3 docs/build_demarches_pdf.py
"""
from __future__ import annotations

import importlib.util
import json
import os
import re
import sys
import tempfile
from pathlib import Path

os.environ.update({"LANG": "C.UTF-8", "LC_ALL": "C.UTF-8", "PYTHONUTF8": "1"})
ICI = Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location("build_pdf", ICI / "playstore" / "build_pdf.py")
bp = importlib.util.module_from_spec(spec)
spec.loader.exec_module(bp)

MD = ICI / "DEMARCHES_CLIENT.md"
PDF = ICI / "DEMARCHES_CLIENT.pdf"
TITRE = "Vos quatre démarches : tableau de bord"


def main() -> None:
    from playwright.sync_api import sync_playwright

    texte = MD.read_text(encoding="utf-8")
    if bp.MOJIBAKE.search(texte):
        raise SystemExit("Caractère corrompu dans le Markdown.")
    with tempfile.TemporaryDirectory() as d:
        travail = Path(d)
        meta = travail / "meta.json"
        meta.write_text(json.dumps({"title": TITRE, "lang": "fr"}, ensure_ascii=False), encoding="utf-8")
        modele = travail / "modele.html"
        m = bp.MODELE.replace("Dossier de publication", "Xomiton · Démarches du client")
        modele.write_text(m.replace("$css$", bp.css_polices() + bp.CSS), encoding="utf-8")
        html = travail / "doc.html"
        bp.lancer(["pandoc", "-f", "gfm+hard_line_breaks", "-t", "html5", "--standalone", "--wrap=none",
                   f"--metadata-file={meta}", f"--template={modele}", "-o", str(html), str(MD)])
        pied = ('<div style="width:100%;font-size:7.5pt;font-family:Inter,sans-serif;color:#5B5670;padding:0 18mm;'
                'display:flex;justify-content:space-between;"><span>Xomiton · Démarches du client</span>'
                '<span>Page <span class="pageNumber"></span> sur <span class="totalPages"></span></span></div>')
        with sync_playwright() as pw:
            nav = pw.chromium.launch(executable_path=bp.chromium(), args=["--no-sandbox", "--lang=fr-FR"])
            page = nav.new_page()
            page.goto(html.as_uri(), wait_until="load")
            page.evaluate("document.fonts.ready")
            page.emulate_media(media="print")
            page.pdf(path=str(PDF), format="A4", print_background=True, prefer_css_page_size=True,
                     display_header_footer=True, header_template="<span></span>", footer_template=pied,
                     margin={"top": "20mm", "bottom": "22mm", "left": "18mm", "right": "18mm"})
            nav.close()
    import fitz  # pymupdf

    doc = fitz.open(PDF)
    extrait = "".join(p.get_text() for p in doc)
    if "�" in extrait or bp.MOJIBAKE.search(extrait):
        raise SystemExit("U+FFFD ou mojibake dans le PDF.")
    print(f"OK : {PDF.name}, {len(doc)} pages, aucun U+FFFD")


if __name__ == "__main__":
    sys.exit(main())
