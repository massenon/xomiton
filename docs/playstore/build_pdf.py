#!/usr/bin/env python3
"""Génération reproductible des PDF du dossier de publication Google Play.

    PUBLICATION_PLAY_STORE.md  --pandoc--> HTML (UTF-8, lang="fr") --Chromium/Playwright--> PUBLICATION_PLAY_STORE.pdf
    build_fiche_docx.py        -----------> Fiche-publication-Play-Store-Xomiton.docx
    Fiche-publication-Play-Store-Xomiton.docx --LibreOffice--> Fiche-publication-Play-Store-Xomiton.pdf

Règles contre la corruption des accents (tiret cadratin, é, à, etc.) :
  * l'environnement est forcé en UTF-8 (LANG/LC_ALL=C.UTF-8, PYTHONUTF8=1) pour ce script et ses sous-processus ;
  * aucun texte non ASCII n'est passé en argument de commande : le titre vient d'un fichier de métadonnées
    pandoc (--metadata-file, JSON UTF-8) et le modèle HTML déclare <meta charset="utf-8"> ;
  * la police Montserrat (design-system/fonts) est embarquée dans le HTML ; repli : Inter/DejaVu Sans, aussi embarquées ;
  * le script ÉCHOUE (code 1) si un U+FFFD ou un motif de mojibake est trouvé dans les .md, le .docx
    (word/document.xml) ou le texte extrait des PDF.

Usage :  python3 docs/playstore/build_pdf.py [--verifier]   (--verifier : contrôles seulement)
Dépendances : pandoc, playwright (Chromium : XM_CHROMIUM, sinon /opt/pw-browsers/chromium), soffice, pymupdf, python-docx, fonttools.
"""
from __future__ import annotations

import base64
import json
import os
import re
import shutil
import subprocess
import sys
import tempfile
import zipfile
from pathlib import Path

# Environnement UTF-8 pour tout le processus et ses enfants.
os.environ.update({"LANG": "C.UTF-8", "LC_ALL": "C.UTF-8", "PYTHONUTF8": "1"})

ICI = Path(__file__).resolve().parent
RACINE = ICI.parent.parent
MD = ICI / "PUBLICATION_PLAY_STORE.md"
PDF_MD = ICI / "PUBLICATION_PLAY_STORE.pdf"
DOCX = ICI / "Fiche-publication-Play-Store-Xomiton.docx"
PDF_DOCX = ICI / "Fiche-publication-Play-Store-Xomiton.pdf"
POLICES = RACINE / "design-system" / "fonts"

TITRE = "Publication Play Store — Xomiton"  # tiret cadratin, jamais en argument de commande

# Mojibake : séquences UTF-8 relues en Latin-1/CP1252 (« Â » seul est légitime : « Âge »).
MOJIBAKE = re.compile("�|Ã[\u0080-¿ -ÿ]|â€|â\u0080|Â[\u0080-¿ -¿]")

MODELE = """<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>$title$</title>
<style>
$css$
</style>
</head>
<body>
<header class="page-titre"><p class="surtitre">Dossier de publication</p><p class="titre-doc">$title$</p></header>
$body$
</body>
</html>
"""

CSS = """
@page { size: A4; margin: 20mm 18mm 22mm 18mm; }
:root { --primaire:#0433C3; --texte:#1B1630; --attenue:#5B5670; --filet:#E4DCE6; --fond:#FBF7FA; }
html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
body { font-family: "Montserrat", "Inter", "DejaVu Sans", sans-serif; font-size: 9.6pt; line-height: 1.55;
       color: var(--texte); font-weight: 400; text-rendering: optimizeLegibility; font-kerning: normal; }
.page-titre { border-bottom: 3px solid var(--primaire); margin-bottom: 14pt; padding-bottom: 8pt; text-align: left; }
.surtitre { margin: 0; font-size: 8pt; letter-spacing: .12em; text-transform: uppercase; color: var(--attenue); font-weight: 600; }
.titre-doc { margin: 2pt 0 0; font-size: 20pt; font-weight: 700; color: var(--primaire); line-height: 1.2; }
h1, h2, h3, h4 { font-family: "Montserrat", "Inter", sans-serif; text-align: left; hyphens: manual; break-after: avoid;
                 line-height: 1.25; text-wrap: balance; }
h1 { font-size: 15pt; margin: 14pt 0 8pt; }
h2 { font-size: 13pt; color: var(--primaire); margin: 20pt 0 8pt; padding-bottom: 3pt; border-bottom: 1px solid var(--filet); }
h3 { font-size: 10.8pt; margin: 14pt 0 5pt; }
h4 { font-size: 9.8pt; margin: 10pt 0 4pt; }
/* Paragraphes de contenu : justifiés avec césure française. */
p { margin: 0 0 7pt; text-align: justify; hyphens: auto; -webkit-hyphens: auto; text-wrap: pretty;
    orphans: 3; widows: 3; overflow-wrap: break-word; }
a, code { overflow-wrap: anywhere; word-break: normal; }
a { color: var(--primaire); text-decoration: none; }
code { font-family: "DejaVu Sans Mono", monospace; font-size: .84em; color: var(--primaire); background: #EEF1FB;
       padding: 0 2pt; border-radius: 2pt; hyphens: manual; }
pre { background: #F4F2F8; border: 1px solid var(--filet); border-radius: 4pt; padding: 7pt 9pt; text-align: left;
      white-space: pre-wrap; overflow-wrap: anywhere; break-inside: avoid; }
pre code { background: none; padding: 0; color: var(--texte); }
/* Listes courtes, tableaux, légendes : alignés à gauche. */
ul, ol { margin: 0 0 8pt; padding-left: 16pt; text-align: left; }
li { margin: 0 0 3pt; text-align: left; hyphens: manual; }
li p { text-align: left; }
table { border-collapse: collapse; width: 100%; margin: 6pt 0 12pt; font-size: 8.3pt; line-height: 1.4; text-align: left; }
th, td { border: 1px solid var(--filet); padding: 4pt 5pt; vertical-align: top; text-align: left; hyphens: manual;
         overflow-wrap: break-word; word-break: normal; }
td code { overflow-wrap: anywhere; font-size: .8em; }
th { background: var(--primaire); color: #fff; font-weight: 600; }
tr { break-inside: avoid; }
thead { display: table-header-group; }
tbody tr:nth-child(even) td { background: var(--fond); }
td p, th p { text-align: left; margin: 0; hyphens: manual; }
blockquote { margin: 8pt 0 10pt; padding: 7pt 11pt; border-left: 4px solid var(--primaire); background: #EAF0FF; break-inside: avoid; }
blockquote p { margin: 0 0 5pt; font-size: 9.2pt; }
blockquote p:last-child { margin-bottom: 0; }
figcaption, caption { text-align: left; font-size: 8pt; color: var(--attenue); }
input[type=checkbox] { margin: 0 5pt 0 0; vertical-align: -1pt; }
img { max-width: 100%; }
hr { border: 0; border-top: 1px solid var(--filet); margin: 12pt 0; }
"""


def env_utf8() -> dict[str, str]:
    env = dict(os.environ)
    env.update({"LANG": "C.UTF-8", "LC_ALL": "C.UTF-8", "PYTHONUTF8": "1"})
    return env


def lancer(cmd: list[str], **kw) -> subprocess.CompletedProcess:
    r = subprocess.run(cmd, env=kw.pop("env", env_utf8()), capture_output=True, text=True, encoding="utf-8", **kw)
    if r.returncode != 0:
        raise SystemExit(f"Échec : {' '.join(cmd[:3])}…\n{r.stdout}\n{r.stderr}")
    return r


# --------------------------------------------------------------------------- polices
def police_face(nom: str, fichier: Path, plages: str, style: str) -> str:
    donnees = base64.b64encode(fichier.read_bytes()).decode("ascii")
    return (f'@font-face {{ font-family: "{nom}"; font-style: {style}; font-weight: 100 900; '
            f'src: url(data:font/woff2;base64,{donnees}) format("woff2"); unicode-range: {plages}; }}\n')


def css_polices() -> str:
    """Embarque Montserrat (latin + latin-ext, normal et italique) s'il est présent dans design-system/fonts."""
    plages = {
        "latin": "U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, "
                 "U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD",
        "latin-ext": "U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, "
                     "U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF",
    }
    css = ""
    for sous_ensemble, p in plages.items():
        for style in ("normal", "italic"):
            f = POLICES / f"Montserrat-{sous_ensemble}-{style}.woff2"
            if f.exists():
                css += police_face("Montserrat", f, p, style)
            else:
                print(f"Avertissement : {f.name} absent, repli sur Inter/DejaVu Sans.", file=sys.stderr)
    return css


# --------------------------------------------------------------------------- Markdown -> PDF
def chromium() -> str:
    candidats = [os.environ.get("XM_CHROMIUM", ""), "/opt/pw-browsers/chromium-1194/chrome-linux/chrome",
                 "/opt/pw-browsers/chromium"]
    for c in candidats:
        if c and Path(c).is_file():
            return c
    for base in sorted(Path("/opt/pw-browsers").glob("chromium-*/chrome-linux/chrome")):
        return str(base)
    raise SystemExit("Chromium introuvable (variable XM_CHROMIUM).")


def md_vers_pdf(travail: Path) -> None:
    from playwright.sync_api import sync_playwright

    meta = travail / "meta.json"
    meta.write_text(json.dumps({"title": TITRE, "lang": "fr"}, ensure_ascii=False),
                    encoding="utf-8")
    modele = travail / "modele.html"
    modele.write_text(MODELE.replace("$css$", css_polices() + CSS), encoding="utf-8")  # CSS injecté tel quel (pandoc l'échapperait)
    html = travail / "dossier.html"
    lancer(["pandoc", "-f", "gfm+hard_line_breaks", "-t", "html5", "--standalone", "--wrap=none",
            f"--metadata-file={meta}", f"--template={modele}", "-o", str(html), str(MD)])
    contenu = html.read_text(encoding="utf-8")
    if 'charset="utf-8"' not in contenu or '<html lang="fr">' not in contenu:
        raise SystemExit("HTML invalide : meta charset ou lang manquant.")
    # Le premier <h1> du Markdown reprend le titre : il reste, le bandeau de titre le précède.
    pied = ('<div style="width:100%;font-size:7.5pt;font-family:Inter,sans-serif;color:#5B5670;padding:0 18mm;'
            'display:flex;justify-content:space-between;"><span>Xomiton · Publication Play Store</span>'
            '<span>Page <span class="pageNumber"></span> sur <span class="totalPages"></span></span></div>')
    with sync_playwright() as pw:
        nav = pw.chromium.launch(executable_path=chromium(), args=["--no-sandbox", "--lang=fr-FR"])
        page = nav.new_page()
        page.goto(html.as_uri(), wait_until="load")
        page.evaluate("document.fonts.ready")
        page.emulate_media(media="print")
        page.pdf(path=str(PDF_MD), format="A4", print_background=True, prefer_css_page_size=True,
                 display_header_footer=True, header_template="<span></span>", footer_template=pied,
                 margin={"top": "20mm", "bottom": "22mm", "left": "18mm", "right": "18mm"})
        nav.close()
    print(f"OK : {PDF_MD.relative_to(RACINE)}")


# --------------------------------------------------------------------------- DOCX -> PDF
def installer_montserrat_ttf(dossier: Path) -> Path:
    """Crée des instances statiques Montserrat (Regular, Bold, Italic, Bold Italic) à partir des woff2 variables,
    nommées « Montserrat », dans un dossier temporaire que LibreOffice voit via un fonts.conf dédié."""
    from fontTools.ttLib import TTFont
    from fontTools.varLib import instancer

    cible = dossier / "fonts"
    cible.mkdir()
    for style in ("normal", "italic"):
        f = POLICES / f"Montserrat-latin-{style}.woff2"
        if not f.exists():
            continue
        for graisse, nom_graisse in ((400, "Regular"), (700, "Bold")):
            police = TTFont(f)
            police.flavor = None
            police = instancer.instantiateVariableFont(police, {"wght": graisse}, inplace=False)
            sous = ("Bold Italic" if graisse == 700 else "Italic") if style == "italic" else nom_graisse
            tab = police["name"]
            for rec in list(tab.names):
                if rec.nameID in (16, 17, 21, 22, 25):
                    tab.removeNames(nameID=rec.nameID)
            for ident, val in ((1, "Montserrat"), (2, sous), (4, f"Montserrat {sous}"),
                               (6, "Montserrat-" + sous.replace(" ", ""))):
                tab.setName(val, ident, 3, 1, 0x409)
                tab.setName(val, ident, 1, 0, 0)
            os2 = police["OS/2"]
            os2.usWeightClass = graisse
            bits = (0x20 if graisse == 700 else 0) | (0x01 if style == "italic" else 0)
            os2.fsSelection = (os2.fsSelection & ~0x61) | (bits or 0x40)
            police["head"].macStyle = (1 if graisse == 700 else 0) | (2 if style == "italic" else 0)
            police.save(cible / f"Montserrat-{sous.replace(' ', '')}.ttf")
    conf = dossier / "fonts.conf"
    conf.write_text(f'<?xml version="1.0"?><fontconfig><include ignore_missing="yes">/etc/fonts/fonts.conf</include>'
                    f'<dir>{cible}</dir></fontconfig>', encoding="utf-8")
    return conf


def docx_vers_pdf(travail: Path) -> None:
    lancer([sys.executable, "-I", str(ICI / "build_fiche_docx.py")])
    conf = installer_montserrat_ttf(travail)
    env = env_utf8()
    env["FONTCONFIG_FILE"] = str(conf)
    sortie = travail / "pdf"
    sortie.mkdir()
    soffice = shutil.which("soffice") or shutil.which("libreoffice")
    if not soffice:
        raise SystemExit("LibreOffice (soffice) introuvable.")
    lancer([soffice, f"-env:UserInstallation={(travail / 'profil').as_uri()}", "--headless", "--convert-to", "pdf",
            "--outdir", str(sortie), str(DOCX)], env=env, timeout=300)
    shutil.copyfile(sortie / (DOCX.stem + ".pdf"), PDF_DOCX)
    print(f"OK : {PDF_DOCX.relative_to(RACINE)}")


# --------------------------------------------------------------------------- contrôles
def anomalies(texte: str, origine: str) -> list[str]:
    return [f"{origine} : ligne {n} : {m.group(0)!r} dans « {l.strip()[:70]} »"
            for n, l in enumerate(texte.splitlines(), 1) for m in [MOJIBAKE.search(l)] if m]


def texte_pdf(chemin: Path) -> str:
    import pymupdf

    with pymupdf.open(chemin) as doc:
        return "\n".join(p.get_text() for p in doc)


def verifier() -> int:
    problemes: list[str] = []
    for md in sorted(ICI.glob("*.md")):
        problemes += anomalies(md.read_text(encoding="utf-8"), md.name)
    with zipfile.ZipFile(DOCX) as z:
        problemes += anomalies(z.read("word/document.xml").decode("utf-8"), f"{DOCX.name}:word/document.xml")
    for pdf in (PDF_MD, PDF_DOCX):
        texte = texte_pdf(pdf)
        problemes += anomalies(texte, pdf.name)
        import pymupdf
        with pymupdf.open(pdf) as d:
            if MOJIBAKE.search(d.metadata.get("title") or ""):
                problemes.append(f"{pdf.name} : titre PDF corrompu ({d.metadata.get('title')!r})")
        for attendu in ("é", "è", "à", "ç"):
            if attendu not in texte:
                problemes.append(f"{pdf.name} : caractère {attendu!r} absent du texte extrait")
    debut = texte_pdf(PDF_MD)[:400]
    if TITRE not in debut:
        problemes.append(f"{PDF_MD.name} : titre attendu absent ({debut[:80]!r})")
    if problemes:
        print("ÉCHEC : caractères corrompus détectés (U+FFFD ou mojibake) :", file=sys.stderr)
        print("\n".join(problemes[:40]), file=sys.stderr)
        return 1
    print("Contrôles OK : aucun U+FFFD ni mojibake (.md, .docx, 2 PDF) ; titre avec tiret cadratin intact.")
    return 0


def main() -> int:
    if "--verifier" not in sys.argv:
        with tempfile.TemporaryDirectory(prefix="xm-pdf-") as t:
            md_vers_pdf(Path(t))
        with tempfile.TemporaryDirectory(prefix="xm-docx-") as t:
            docx_vers_pdf(Path(t))
    return verifier()


if __name__ == "__main__":
    sys.exit(main())
