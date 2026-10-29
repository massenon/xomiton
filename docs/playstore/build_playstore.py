#!/usr/bin/env python3
"""Générateur reproductible des visuels de la fiche Google Play de Xomiton.

Lit visuels.json (titres, textes alternatifs, source des écrans, couleurs en jetons),
capture les écrans du prototype mobile (prototype_mobile/index.html) avec Playwright,
compose les visuels sur fond de marque et exporte des PNG 24 bits SANS canal alpha :

    docs/playstore/assets/feature-graphic-1024x500.png
    docs/playstore/assets/screenshots/01-…png … 08-…png   (1080 x 1920)

puis vérifie chaque contrainte Google Play (dimensions, absence d'alpha, poids, ratio,
aire du texte, mots interdits, longueur des textes alternatifs, contraste AA, couleurs vives,
barre d'état) et écrit assets/rapport-controles.json. Code de sortie 1 si un contrôle échoue.

Usage (depuis n'importe où) :
    python3 docs/playstore/build_playstore.py                 # tout produire et vérifier
    python3 docs/playstore/build_playstore.py --verifier      # vérifier seulement
    python3 docs/playstore/build_playstore.py --langue en     # gabarit anglais (refusé tant que vide)
    python3 docs/playstore/build_playstore.py --seulement 02-sos

Dépendances : pip install playwright pillow ; Chromium : variable XM_CHROMIUM, sinon
/opt/pw-browsers/chromium-1194/chrome-linux/chrome puis /opt/pw-browsers/chromium.
"""
from __future__ import annotations

import argparse
import html
import json
import os
import re
import struct
import sys
import zlib
from colorsys import rgb_to_hsv
from pathlib import Path

from PIL import Image

ICI = Path(__file__).resolve().parent
CONFIG = ICI / "visuels.json"

# Icônes (trait 2, viewBox 24) : mêmes dessins que le prototype mobile (jeu de traits).
ICONES = {
    "shield": '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/>',
    "lock": '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
    "pin": '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
    "chat": '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
    "bot": '<rect x="4" y="8" width="16" height="12" rx="3"/><path d="M12 4v4M9 13v1M15 13v1M9 17h6"/>',
    "book": '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5v14Z"/><path d="M4 19.5A2.5 2.5 0 0 0 6.5 22H20v-5"/>',
    "users": '<circle cx="9" cy="8" r="4"/><path d="M2 21a7 7 0 0 1 14 0"/><path d="M16 4a4 4 0 0 1 0 8M22 21a7 7 0 0 0-4-6.3"/>',
    "eyeoff": '<path d="M9.9 4.2A10 10 0 0 1 12 4c6.5 0 10 8 10 8a17 17 0 0 1-2.2 3.2M6.6 6.6A17 17 0 0 0 2 12s3.5 8 10 8a10 10 0 0 0 5.4-1.6"/><path d="M2 2l20 20M9.9 9.9a3 3 0 0 0 4.2 4.2"/>',
    "wifioff": '<path d="M12 20h.01"/><path d="M8.5 16.4a5 5 0 0 1 7 0"/><path d="M2 8.8a15 15 0 0 1 4.2-2.7M22 8.8A15 15 0 0 0 10.7 4.1"/><path d="M5 12.9a10 10 0 0 1 5.2-2.7M19 12.9a10 10 0 0 0-2.4-1.7"/><path d="m2 2 20 20"/>',
}


# --------------------------------------------------------------------------- jetons
def charger_jetons(chemin: Path) -> dict[str, str]:
    """Lit les primitives --xm-*: #hex du fichier de jetons (clé sans préfixe)."""
    jetons: dict[str, str] = {}
    for nom, val in re.findall(r"--xm-([a-z0-9-]+)\s*:\s*(#[0-9A-Fa-f]{6})\s*;", chemin.read_text(encoding="utf-8")):
        jetons.setdefault(nom, val.upper())
    return jetons


class Jetons:
    def __init__(self, table: dict[str, str]):
        self.t = table

    def __call__(self, nom: str) -> str:
        if nom not in self.t:
            raise SystemExit(f"Jeton inconnu : {nom} (absent de tokens.css)")
        return self.t[nom]


def hex_rgb(h: str) -> tuple[int, int, int]:
    h = h.lstrip("#")
    return int(h[0:2], 16), int(h[2:4], 16), int(h[4:6], 16)


def lum_rel(rgb) -> float:
    def c(v):
        v /= 255
        return v / 12.92 if v <= 0.03928 else ((v + 0.055) / 1.055) ** 2.4
    r, g, b = rgb
    return 0.2126 * c(r) + 0.7152 * c(g) + 0.0722 * c(b)


def contraste(l1: float, l2: float) -> float:
    a, b = max(l1, l2), min(l1, l2)
    return (a + 0.05) / (b + 0.05)


# --------------------------------------------------------------------------- navigateur
def chromium() -> str:
    for c in (os.environ.get("XM_CHROMIUM"), "/opt/pw-browsers/chromium-1194/chrome-linux/chrome", "/opt/pw-browsers/chromium"):
        if c and Path(c).is_file():
            return c
    raise SystemExit("Chromium introuvable : définir XM_CHROMIUM")


def uri(p: Path) -> str:
    return p.resolve().as_uri()


# --------------------------------------------------------------------------- capture du prototype
CSS_CAPTURE = """
.notch{display:none!important}
.device{background:transparent!important;box-shadow:none!important}
#deviceWrap{transform:none!important}
.toast,.toasts{display:none!important}
"""

JS_BARRE_ETAT = """(heure) => {
  const ck = document.getElementById('clock'); if (ck) ck.textContent = heure;
  // le prototype rafraichit l'heure toutes les 15 s : on fige
  window.setInterval = () => 0;
  const sb = document.querySelector('#screen .statusbar');
  // batterie pleine : le remplissage interne occupe tout le corps de la pile
  const bat = sb.querySelectorAll('svg')[2];
  const rects = bat.querySelectorAll('rect');
  rects[1].setAttribute('width', '19'); rects[1].setAttribute('height', '8');
  rects[0].setAttribute('opacity', '1');
  sb.dataset.propre = '1';
  return {heure: ck.textContent, svgs: sb.querySelectorAll('svg').length, remplissage: rects[1].getAttribute('width')};
}"""


def capturer_ecrans(cfg: dict, jetons: Jetons, cache: Path, ids: list[str] | None) -> dict:
    from playwright.sync_api import sync_playwright

    cap = cfg["capture"]
    proto = (ICI / cfg["prototype"]).resolve()
    police = (ICI / cfg["police_montserrat"]).resolve()
    font_css = ("@font-face{font-family:'Montserrat';font-weight:100 900;font-style:normal;src:url('%s') format('woff2');}" % uri(police))
    infos = {}
    (cache / "brut").mkdir(parents=True, exist_ok=True)
    with sync_playwright() as p:
        nav = p.chromium.launch(executable_path=chromium())
        for e in cfg["captures_ecrans"]:
            if ids and e["id"] not in ids:
                continue
            ctx = nav.new_context(viewport={"width": cap["viewport"][0], "height": cap["viewport"][1]},
                                  device_scale_factor=cap["device_scale"], locale="fr-FR")
            ctx.add_init_script("try{localStorage.setItem('xm-sim-v1',%s)}catch(e){}" % json.dumps(json.dumps(cap["etat_prototype"])))
            pg = ctx.new_page()
            pg.goto(uri(proto) + e["source"]["route"])
            pg.add_style_tag(content=font_css + CSS_CAPTURE + e["source"].get("css", ""))
            pg.wait_for_timeout(700)
            pg.evaluate("document.fonts.ready")
            if e["source"].get("js"):
                pg.evaluate(e["source"]["js"])
            # remplacements de texte (pseudonymes de démonstration) : voir visuels.json
            for a, b in cfg.get("remplacements", {}).items():
                pg.evaluate("""([a,b]) => { const w = document.createTreeWalker(document.getElementById('app'), NodeFilter.SHOW_TEXT);
                  while (w.nextNode()) { if (w.currentNode.nodeValue.includes(a)) w.currentNode.nodeValue = w.currentNode.nodeValue.split(a).join(b); } }""", [a, b])
            infos[e["id"]] = pg.evaluate(JS_BARRE_ETAT, cap["heure"])
            box = pg.locator("#screen").bounding_box()
            assert round(box["width"]) == cap["ecran"][0] and round(box["height"]) == cap["ecran"][1], f"écran {box} != {cap['ecran']}"
            pg.locator("#screen").screenshot(path=str(cache / "brut" / f"{e['id']}.png"), omit_background=False)
            ctx.close()
        nav.close()
    return infos


# --------------------------------------------------------------------------- composition HTML
def css_base(cfg, J: Jetons) -> str:
    police = uri((ICI / cfg["police_montserrat"]).resolve())
    return f"""
@font-face{{font-family:'Montserrat';font-weight:100 900;font-style:normal;src:url('{police}') format('woff2');}}
*{{box-sizing:border-box;margin:0;padding:0}}
html,body{{margin:0}}
body{{font-family:'Montserrat','Segoe UI',Arial,sans-serif;color:{J(cfg['couleurs']['texte'])};overflow:hidden;position:relative}}
.notext [data-txt]{{color:transparent!important}}
.notext [data-txt] svg{{opacity:0}}
.ecran-img{{display:block;width:100%;height:100%}}
.cadre{{position:absolute;background:{J(cfg['couleurs']['cadre_appareil'])};box-shadow:0 40px 90px rgba(2,19,74,.45),0 0 0 2px rgba(255,255,255,.10) inset}}
.cadre .ecran{{position:absolute;overflow:hidden;background:#fff}}
.cadre .trou{{position:absolute;border-radius:50%;background:#0B0A14;box-shadow:0 0 0 2px #1d1b2e}}
.puce{{position:absolute;display:flex;align-items:center;gap:18px;background:{J(cfg['couleurs']['puce_fond'])};color:{J(cfg['couleurs']['puce_texte'])};
  border-radius:999px;box-shadow:0 22px 44px rgba(2,19,74,.38);font-weight:700;white-space:nowrap}}
.puce .ico{{flex:none;border-radius:50%;background:{J(cfg['couleurs']['puce_icone_fond'])};color:{J(cfg['couleurs']['puce_icone'])};display:grid;place-items:center}}
.puce svg{{fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}}
.halo{{position:absolute;border-radius:50%;filter:blur(2px)}}
"""


def fond_css(f: dict, J: Jetons) -> str:
    return f"linear-gradient({f['angle']}deg,{J(f['de'])} 0%,{J(f['vers'])} 100%)"


def svg_icone(nom: str, taille: int) -> str:
    return f'<svg viewBox="0 0 24 24" width="{taille}" height="{taille}" aria-hidden="true">{ICONES[nom]}</svg>'


def html_capture(cfg, J, e, texte, brut: Path) -> str:
    W, H = 1080, 1920
    ecran_w = 730
    k = ecran_w / cfg["capture"]["ecran"][0]
    ecran_h = round(cfg["capture"]["ecran"][1] * k)
    biseau = 16
    cadre_w, cadre_h = ecran_w + 2 * biseau, ecran_h + 2 * biseau
    cadre_x, cadre_y = (W - cadre_w) // 2, 365
    rayon_cadre, rayon_ecran = 92, 76
    puces = ""
    for pz in e.get("puces", []):
        txt = html.escape(texte["puces"][pz["texte_ref"]])
        cote = "left:36px" if pz["cote"] == "gauche" else "right:36px"
        puces += (f'<div class="puce" data-area style="{cote};top:{pz["y"]}px;padding:16px 38px 16px 16px;font-size:34px;">'
                  f'<span class="ico" style="width:76px;height:76px;">{svg_icone(pz["icone"], 42)}</span><span data-txt>{txt}</span></div>')
    halos = (f'<div class="halo" style="left:-260px;top:1120px;width:700px;height:700px;background:radial-gradient(circle,{J("blue-300")}55,transparent 68%)"></div>'
             f'<div class="halo" style="right:-300px;top:-120px;width:760px;height:760px;background:radial-gradient(circle,{J("violet-700")}88,transparent 68%)"></div>')
    return f"""<!doctype html><html lang="fr"><head><meta charset="utf-8"><style>{css_base(cfg, J)}
body{{width:{W}px;height:{H}px;background:{fond_css(e['fond'], J)}}}
.titre{{position:absolute;left:70px;right:70px;top:100px;text-align:center;font-weight:800;font-size:76px;line-height:1.14;letter-spacing:-.01em;text-wrap:balance}}
</style></head><body>
{halos}
<h1 class="titre" data-txt>{html.escape(texte['titre'])}</h1>
<div class="cadre" style="left:{cadre_x}px;top:{cadre_y}px;width:{cadre_w}px;height:{cadre_h}px;border-radius:{rayon_cadre}px">
  <div class="ecran" style="left:{biseau}px;top:{biseau}px;width:{ecran_w}px;height:{ecran_h}px;border-radius:{rayon_ecran}px">
    <img class="ecran-img" src="{uri(brut)}" alt=""></div>
  <div class="trou" style="left:{cadre_w//2-12}px;top:{biseau+22}px;width:24px;height:24px"></div>
</div>
{puces}
</body></html>"""


def html_feature(cfg, J, caps: dict[str, Path], texte) -> str:
    fg = cfg["feature_graphic"]
    W, H = 1024, 500
    logo = uri((ICI / cfg["logo_blanc"]).resolve())

    def tel(nom, x, y, rot):
        brut = caps[fg["ecrans"][nom]]
        w, b = 215, 8
        sh = round(w * 844 / 390)
        return (f'<div class="cadre" style="left:{x}px;top:{y}px;width:{w+2*b}px;height:{sh+2*b}px;border-radius:44px;transform:rotate({rot}deg);transform-origin:center;'
                f'box-shadow:0 26px 60px rgba(2,19,74,.5)"><div class="ecran" style="left:{b}px;top:{b}px;width:{w}px;height:{sh}px;border-radius:36px">'
                f'<img class="ecran-img" src="{uri(brut)}" alt=""></div></div>')

    halos = "".join(
        f'<div class="halo" style="left:calc({h["x"]}% - {h["rayon"]}px);top:calc({h["y"]}% - {h["rayon"]}px);width:{2*h["rayon"]}px;height:{2*h["rayon"]}px;'
        f'background:radial-gradient(circle,{J(h["couleur"])}{int(h["opacite"]*255):02X},transparent 68%)"></div>' for h in fg["halos"])
    return f"""<!doctype html><html lang="fr"><head><meta charset="utf-8"><style>{css_base(cfg, J)}
body{{width:{W}px;height:{H}px;background:{fond_css(fg['fond'], J)}}}
.centre{{position:absolute;left:50%;top:0;height:100%;width:520px;transform:translateX(-50%);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:30px}}
.centre img{{width:420px;display:block}}
.slogan{{font-weight:700;font-size:31px;line-height:1.25;letter-spacing:0;text-align:center}}
</style></head><body>
{halos}
{tel('gauche', -62, 118, -8)}
{tel('droite', W-215-16+62-8, 118, 8)}
<div class="centre"><img src="{logo}" alt=""><p class="slogan" data-txt>{html.escape(texte['slogan'])}</p></div>
</body></html>"""


MESURES_JS = """() => {
  const out = [];
  document.querySelectorAll('[data-txt]').forEach(el => {
    const cs = getComputedStyle(el);
    const rects = []; const rg = document.createRange(); rg.selectNodeContents(el);
    for (const r of rg.getClientRects()) rects.push([r.left, r.top, r.right, r.bottom]);
    out.push({texte: el.textContent.trim(), couleur: cs.color, taille: parseFloat(cs.fontSize), rects, aire_seule: false});
  });
  // pastilles : tout le cadre compte dans l'aire du texte (pas dans le contraste)
  document.querySelectorAll('[data-area]').forEach(el => { const r = el.getBoundingClientRect(); out.push({texte: el.textContent.trim(), couleur: 'rgb(255,255,255)', taille: 0, rects: [[r.left, r.top, r.right, r.bottom]], aire_seule: true}); });
  return out;
}"""


def composer(cfg, J, cfg_lang: str, cache: Path, sortie: Path, ids) -> dict:
    from playwright.sync_api import sync_playwright

    tmp = cache / "compo"
    tmp.mkdir(parents=True, exist_ok=True)
    (sortie / "screenshots").mkdir(parents=True, exist_ok=True)
    caps = {e["id"]: (cache / "brut" / f"{e['id']}.png") for e in cfg["captures_ecrans"]}
    mesures = {}
    with sync_playwright() as p:
        nav = p.chromium.launch(executable_path=chromium())
        taches = []
        if not ids or "feature-graphic" in ids:
            fg = cfg["feature_graphic"]
            taches.append(("feature-graphic", (1024, 500), html_feature(cfg, J, caps, fg["textes"][cfg_lang]), sortie / fg["fichier"]))
        for e in cfg["captures_ecrans"]:
            if ids and e["id"] not in ids:
                continue
            taches.append((e["id"], (1080, 1920), html_capture(cfg, J, e, e["textes"][cfg_lang], caps[e["id"]]), sortie / "screenshots" / e["fichier"]))
        for ident, (w, h), code, dest in taches:
            pg = nav.new_page(viewport={"width": w, "height": h}, device_scale_factor=1)
            f = tmp / f"{ident}.html"
            f.write_text(code, encoding="utf-8")
            pg.goto(uri(f))
            pg.evaluate("document.fonts.ready")
            pg.wait_for_timeout(250)
            m = pg.evaluate(MESURES_JS)
            brut_png = tmp / f"{ident}.rgba.png"
            pg.screenshot(path=str(brut_png))
            pg.evaluate("document.body.classList.add('notext')")
            pg.wait_for_timeout(100)
            sans_txt = tmp / f"{ident}.notext.png"
            pg.screenshot(path=str(sans_txt))
            pg.close()
            im = Image.open(brut_png).convert("RGB")  # 24 bits, sans canal alpha
            im.save(dest, format="PNG", optimize=True)
            mesures[ident] = {"textes": m, "sans_texte": str(sans_txt)}
        nav.close()
    (cache / "mesures.json").write_text(json.dumps(mesures, ensure_ascii=False), encoding="utf-8")
    return mesures


# --------------------------------------------------------------------------- contrôles
def lire_ihdr(chemin: Path):
    d = chemin.read_bytes()
    assert d[:8] == b"\x89PNG\r\n\x1a\n", "pas un PNG"
    w, h, prof, typ, _, _, _ = struct.unpack(">IIBBBBB", d[16:29])
    morceaux = set()
    i = 8
    while i < len(d):
        ln = struct.unpack(">I", d[i:i + 4])[0]
        morceaux.add(d[i + 4:i + 8].decode("latin1"))
        i += 12 + ln
    return w, h, prof, typ, morceaux


def parse_rgb(css: str):
    m = re.findall(r"[\d.]+", css)
    return tuple(int(float(x)) for x in m[:3])


def aire_union(rects) -> float:
    """Aire de l'union de rectangles (algorithme par balayage sur coordonnées compressées)."""
    if not rects:
        return 0.0
    xs = sorted({x for r in rects for x in (r[0], r[2])})
    total = 0.0
    for a, b in zip(xs, xs[1:]):
        iv = sorted((r[1], r[3]) for r in rects if r[0] < b and r[2] > a)
        cur_s = cur_e = None
        h = 0.0
        for s, e in iv:
            if cur_e is None or s > cur_e:
                if cur_e is not None:
                    h += cur_e - cur_s
                cur_s, cur_e = s, e
            else:
                cur_e = max(cur_e, e)
        if cur_e is not None:
            h += cur_e - cur_s
        total += h * (b - a)
    return total


def textes_visibles(cfg, langue) -> list[tuple[str, str]]:
    """(étiquette, texte) de tous les textes publics des visuels : titres, puces, slogan, alt."""
    out = []
    fg = cfg["feature_graphic"]["textes"][langue]
    out += [("feature-graphic/slogan", fg["slogan"]), ("feature-graphic/alt", fg["alt"])]
    for e in cfg["captures_ecrans"]:
        t = e["textes"][langue]
        out.append((f"{e['id']}/titre", t["titre"]))
        out.append((f"{e['id']}/alt", t["alt"]))
        out += [(f"{e['id']}/puce:{k}", v) for k, v in t["puces"].items()]
    return out


def mots_interdits_trouves(texte: str, cfg) -> list[str]:
    c = cfg["contraintes"]
    bas = texte.lower()
    trouves = [m for m in c["mots_interdits"] if re.search(r"(?<![\wéèêàç])" + re.escape(m) + r"(?![\wéèêàç])", bas)]
    trouves += [m for m in c["mots_interdits_regex_extra"] if re.search(m, bas, re.I)]
    return trouves


def fond_vif(fond: dict, J: Jetons) -> tuple[bool, str]:
    """Le fond d'une capture est un dégradé de deux jetons : saturés (> 0,5) et ni noirs ni blancs (valeur 0,25 à 0,95)."""
    det = []
    bon = True
    for cle in ("de", "vers"):
        r, g, b = hex_rgb(J(fond[cle]))
        _, sa, va = rgb_to_hsv(r / 255, g / 255, b / 255)
        bon = bon and sa > 0.5 and 0.25 <= va <= 0.95
        det.append(f"{fond[cle]} s={sa:.2f} v={va:.2f}")
    return bon, " ; ".join(det)


def verifier(cfg, J, langue, sortie: Path, cache: Path, infos_barre: dict | None, ids=None) -> bool:
    c = cfg["contraintes"]
    res: list[dict] = []

    def ok(visuel, controle, passe, detail):
        res.append({"visuel": visuel, "controle": controle, "ok": bool(passe), "detail": detail})

    mesures = json.loads((cache / "mesures.json").read_text(encoding="utf-8")) if (cache / "mesures.json").exists() else {}
    cibles = [("feature-graphic", sortie / cfg["feature_graphic"]["fichier"], c["feature_graphic"])]
    cibles += [(e["id"], sortie / "screenshots" / e["fichier"], c["captures"]) for e in cfg["captures_ecrans"]]

    ok("ensemble", "nombre de captures", len(cfg["captures_ecrans"]) == c["captures"]["nombre"], f"{len(cfg['captures_ecrans'])} (attendu {c['captures']['nombre']})")
    for ident, chemin, regle in cibles:
        if ids and ident not in ids and ident != "feature-graphic":
            continue
        if ids and ident == "feature-graphic" and "feature-graphic" not in ids:
            continue
        if not chemin.exists():
            ok(ident, "fichier présent", False, str(chemin))
            continue
        w, h, prof, typ, morc = lire_ihdr(chemin)
        ok(ident, "dimensions", (w, h) == (regle["largeur"], regle["hauteur"]), f"{w}x{h}")
        ok(ident, "PNG 24 bits sans alpha", prof == 8 and typ == 2 and "tRNS" not in morc and "PLTE" not in morc, f"profondeur {prof}, type de couleur {typ} (2 = RVB), blocs {sorted(morc - {'IHDR','IDAT','IEND'})}")
        mo = chemin.stat().st_size / 1048576
        ok(ident, "poids", mo < regle["poids_max_mo"], f"{mo:.2f} Mo (< {regle['poids_max_mo']} Mo)")
        if ident != "feature-graphic":
            ok(ident, "ratio 9:16", abs(w / h - 9 / 16) < 1e-9, f"{w}/{h}")
        im = Image.open(chemin)
        ok(ident, "mode Pillow RGB", im.mode == "RGB", im.mode)
        m = mesures.get(ident)
        if m:
            rects = [r for t in m["textes"] for r in t["rects"]]
            aire = aire_union(rects)
            pct = 100 * aire / (w * h)
            lim = c["captures"]["texte_max_pct"]
            ok(ident, f"aire du texte <= {lim} %", pct <= lim, f"{pct:.1f} %")
            # contraste du texte sur le fond réel (rendu sans texte), au pixel le plus défavorable
            sans = Image.open(m["sans_texte"]).convert("RGB")
            pire = 99.0
            for t in m["textes"]:
                if t.get("aire_seule"):
                    continue
                col = lum_rel(parse_rgb(t["couleur"]))
                for (x0, y0, x1, y1) in t["rects"]:
                    box = (max(0, int(x0)), max(0, int(y0)), min(w, int(x1) + 1), min(h, int(y1) + 1))
                    if box[2] <= box[0] or box[3] <= box[1]:
                        continue
                    zone = sans.crop(box).convert("RGB")
                    lums = sorted(lum_rel(px) for px in set(zone.getdata()))
                    # 99e centile côté défavorable (texte clair : fond le plus clair ; texte foncé : fond le plus sombre)
                    if col > 0.5:
                        bg = lums[min(len(lums) - 1, int(len(lums) * 0.995))]
                    else:
                        bg = lums[int(len(lums) * 0.005)]
                    pire = min(pire, contraste(col, bg))
            ok(ident, f"contraste texte >= {c['contraste_min']}:1", pire >= c["contraste_min"], f"pire cas {pire:.2f}:1")
        # couleurs vives (évite blanc pur, noir, gris foncé dominants)
        pt = im.resize((128, 128 * h // w))
        px = list(pt.getdata())
        n = len(px)
        blanc = sum(1 for r, g, b in px if min(r, g, b) > 240) / n
        noir = sum(1 for r, g, b in px if max(r, g, b) < 40) / n
        sat = sum(rgb_to_hsv(r / 255, g / 255, b / 255)[1] for r, g, b in px) / n
        if ident == "feature-graphic":
            ok(ident, "couleurs vives (blanc < 25 %, noir < 3 %, saturation moyenne > 0,45)", blanc < 0.25 and noir < 0.03 and sat > 0.45,
               f"blanc {blanc*100:.0f} %, noir {noir*100:.1f} %, saturation {sat:.2f}")
        else:
            e = next(x for x in cfg["captures_ecrans"] if x["id"] == ident)
            vif, det = fond_vif(e["fond"], J)
            ok(ident, "fond de marque vif (dégradé de jetons saturés, ni noir ni blanc)", vif, det)
        if infos_barre and ident in infos_barre:
            i = infos_barre[ident]
            ok(ident, "barre d'état propre (9:41, signal, Wi-Fi, batterie pleine, aucun opérateur ni notification)",
               i["heure"] == cfg["capture"]["heure"] and i["svgs"] == 3 and i["remplissage"] == "19", json.dumps(i))

    langue_txt = textes_visibles(cfg, langue)
    for etiq, t in langue_txt:
        if not t:
            ok(etiq, "texte renseigné", False, "vide (gabarit non rempli)")
            continue
        interdits = mots_interdits_trouves(t, cfg)
        ok(etiq, "mots interdits", not interdits, ", ".join(interdits) or "aucun")
        if etiq.endswith("/alt"):
            ok(etiq, f"alt <= {c['alt_max']} caractères", len(t) <= c["alt_max"], f"{len(t)}")
            ok(etiq, "alt sans « image de »", not re.match(r"\s*(image|photo|capture)\s+(de|d')", t, re.I), t[:40])

    # fiche : description courte/longue marquées dans le dossier de publication
    fiche = ICI / "PUBLICATION_PLAY_STORE.md"
    if fiche.exists():
        md = fiche.read_text(encoding="utf-8")
        for cle, lim in (("nom", 30), ("court", 80), ("long", 4000)):
            mm = re.search(rf"<!--DEBUT:{cle}-->\s*(.*?)\s*<!--FIN:{cle}-->", md, re.S)
            if mm:
                t = mm.group(1).strip()
                ok(f"fiche/{cle}", f"longueur <= {lim}", len(t) <= lim, f"{len(t)}")
                interd = mots_interdits_trouves(t, cfg)
                ok(f"fiche/{cle}", "mots interdits", not interd, ", ".join(interd) or "aucun")
            else:
                ok(f"fiche/{cle}", "balises DEBUT/FIN présentes", False, "absentes")

    rapport = sortie / "rapport-controles.json"
    rapport.write_text(json.dumps({"langue": langue, "resultats": res}, ensure_ascii=False, indent=1), encoding="utf-8")
    echecs = [r for r in res if not r["ok"]]
    for r in res:
        print(("OK   " if r["ok"] else "ECHEC"), f"{r['visuel']:<34} {r['controle'][:70]:<70} {r['detail'][:90]}")
    print(f"\n{len(res) - len(echecs)}/{len(res)} contrôles passés" + ("" if not echecs else f", {len(echecs)} échec(s)"))
    return not echecs


# --------------------------------------------------------------------------- principal
def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--config", default=str(CONFIG))
    ap.add_argument("--langue", default="fr", choices=["fr", "en"])
    ap.add_argument("--verifier", action="store_true", help="vérifier sans régénérer")
    ap.add_argument("--seulement", nargs="*", help="identifiants à régénérer (ex. 02-sos feature-graphic)")
    a = ap.parse_args()

    cfg = json.loads(Path(a.config).read_text(encoding="utf-8"))
    J = Jetons(charger_jetons((ICI / cfg["tokens_css"]).resolve()))
    cache = ICI / cfg["cache"]
    sortie = ICI / cfg["sortie"] / ("" if a.langue == "fr" else a.langue)
    cache.mkdir(parents=True, exist_ok=True)

    vides = [e for e, t in textes_visibles(cfg, a.langue) if not t]
    if vides:
        print(f"Gabarit « {a.langue} » non rempli ({len(vides)} textes vides, ex. {vides[0]}). "
              "Renseigner visuels.json et fournir un prototype traduit avant de générer.", file=sys.stderr)
        return 2

    infos = {}
    if not a.verifier:
        infos = capturer_ecrans(cfg, J, cache, a.seulement)
        (cache / "barre-etat.json").write_text(json.dumps(infos), encoding="utf-8")
        composer(cfg, J, a.langue, cache, sortie, a.seulement)
    elif (cache / "barre-etat.json").exists():
        infos = json.loads((cache / "barre-etat.json").read_text(encoding="utf-8"))
    ok = verifier(cfg, J, a.langue, sortie, cache, infos, a.seulement)
    return 0 if ok else 1


if __name__ == "__main__":
    sys.exit(main())
