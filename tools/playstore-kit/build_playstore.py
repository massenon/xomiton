#!/usr/bin/env python3
"""Kit générique de production des visuels Google Play.

Produit, à partir d'un fichier de configuration JSON :
  - une image de présentation 1024 x 500 ;
  - N captures d'écran 1080 x 1920 (9:16) ;
tous en PNG 24 bits SANS canal alpha, puis vérifie les règles Google Play connues
(dimensions, alpha, poids, ratio, aire du texte, contraste AA, mots interdits, textes
alternatifs, couleurs vives, barre d'état) et écrit rapport-controles.json.
Code de sortie : 0 tout est conforme, 1 un contrôle échoue, 2 configuration ou usage invalide.

Sources d'écrans (au choix, voir README.md) :
  - "html"   : URL ou fichier HTML capturé avec Playwright (viewport, échelle, route, heure figée,
               masquage par sélecteur CSS, remplacements de noms propres) ;
  - "images" : captures déjà faites (PNG de l'émulateur) : le kit les encadre et recouvre la barre
               d'état par une barre propre.

Usage :
    python3 build_playstore.py --init mon-projet           # crée un projet exemple prêt à lancer
    python3 build_playstore.py --config visuels.json       # produire et vérifier (défaut : ./visuels.json)
    python3 build_playstore.py --verifier                  # vérifier seulement
    python3 build_playstore.py --seulement 02-liste        # ne régénérer que certains visuels
    python3 build_playstore.py --langue en --sortie dist   # autre langue, autre dossier de sortie

Dépendances : pip install playwright pillow. Chromium : variable PW_CHROMIUM ou XM_CHROMIUM,
sinon recherche automatique, sinon Chromium de Playwright (playwright install chromium).
Tous les chemins de la configuration sont relatifs au fichier de configuration.
"""
from __future__ import annotations

import argparse
import copy
import glob
import html
import json
import os
import re
import shutil
import struct
import sys
from colorsys import rgb_to_hsv
from pathlib import Path

from PIL import Image

KIT = Path(__file__).resolve().parent
VERSION_KIT = "1.0.0"
RE_HEX = re.compile(r"^#(?:[0-9A-Fa-f]{6}|[0-9A-Fa-f]{3})$")
RE_ID = re.compile(r"^[A-Za-z0-9][A-Za-z0-9_-]*$")


class ErreurConfig(Exception):
    """Configuration invalide : le message liste tous les problèmes trouvés."""


# --------------------------------------------------------------------------- valeurs par défaut
DEFAUT = {
    "langue_defaut": "fr",
    "sortie": "sortie",
    "cache": ".cache",
    "theme": {
        "palette": {},
        "tokens_css": None,
        "prefixe_jetons": "",
        "police": {"famille": "Segoe UI", "fichier": None, "graisses": "100 900",
                   "repli": "Roboto, 'Helvetica Neue', Arial, sans-serif"},
        "logo": None,
        "fond": {"de": "#2563EB", "vers": "#1E3A8A", "angle": 160},
        "couleurs": {"texte": "#FFFFFF", "puce_fond": "#FFFFFF", "puce_texte": "#111827",
                     "puce_icone_fond": "#2563EB", "puce_icone": "#FFFFFF", "cadre_appareil": "#111827"},
        "icones": {},
    },
    "source": {
        "type": "html", "url": None, "viewport": [390, 844], "echelle": 3, "locale": "fr-FR",
        "attente_ms": 700, "selecteur_ecran": None, "attendre_selecteur": None, "stockage_local": {},
        "masquer": [], "css": "", "js": "", "selecteur_heure": None, "remplacements": {},
        "police_dans_page": False,
    },
    "barre_etat": {"mode": "auto", "heure": "9:41", "hauteur_pct": 3.4, "couleur": "auto", "icones": "auto"},
    "mise_en_page": {"ecran_largeur": 730, "cadre_y": 365, "biseau": 16, "rayon_cadre": 92, "rayon_ecran": 76,
                     "titre_haut": 100, "titre_taille": 76, "trou_camera": False, "feature_telephone_largeur": 215},
    "contraintes": {
        "feature_graphic": {"largeur": 1024, "hauteur": 500, "poids_max_mo": 15},
        "captures": {"largeur": 1080, "hauteur": 1920, "nombre": 8, "poids_max_mo": 8, "texte_max_pct": 20},
        "alt_max": 140,
        "contraste_min": 4.5,
        "couleurs_vives": {"saturation_min": 0.5, "valeur_min": 0.25, "valeur_max": 0.95,
                           "fg_blanc_max": 0.25, "fg_noir_max": 0.03, "fg_saturation_min": 0.45},
        "mots_interdits_ajout": [],
    },
    "fiche": {"fichier": None, "textes": {}, "limites": {"nom": 30, "court": 80, "long": 4000}},
}

# Liste par défaut : classements, promotions, appels à l'action et mentions Google (règles de
# métadonnées et de visuels Google Play connues à l'écriture). Remplaçable par contraintes.mots_interdits.
MOTS_INTERDITS_DEFAUT = [
    # français
    "meilleur", "meilleure", "meilleurs", "meilleures", "n°1", "n° 1", "numéro 1", "le plus populaire",
    "nouveau", "nouvelle", "gratuit", "gratuite", "remise", "promo", "promotion", "soldes", "millions",
    "télécharger", "téléchargez", "installer", "installez", "essayer", "essayez", "google play", "top",
    # anglais
    "best", "number 1", "no. 1", "#1", "most popular", "new", "free", "discount", "sale", "promo",
    "millions", "download", "install", "try now", "play now", "install now", "download now",
    "google play", "play store", "top",
]
REGEX_INTERDITES_DEFAUT = [
    r"\bn\s?[°º]\s?1\b",
    r"(?<!\w)#\s?1(?!\w)",
    r"\bno\.?\s?1\b",
    r"\b\d+\s?(millions?|M)\s+(de\s+t[ée]l[ée]chargements|downloads)",
    r"\b(millions?|M)\s+(of\s+)?downloads",
]

# Icônes des puces (trait 2, viewBox 24). Ajouter les siennes avec theme.icones.
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
    "check": '<path d="M20 6 9 17l-5-5"/>',
    "heart": '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1 1.1L12 21l7.8-7.5 1-1.1a5.5 5.5 0 0 0 0-7.8Z"/>',
    "star": '<path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1Z"/>',
    "bell": '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/>',
    "calendar": '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
    "drop": '<path d="M12 2.7s7 6.4 7 11.3a7 7 0 0 1-14 0c0-4.9 7-11.3 7-11.3Z"/>',
    "leaf": '<path d="M11 20A7 7 0 0 1 4 13c0-6 7-10 16-10 0 9-4 16-10 16"/><path d="M4 21c3-6 6-9 11-12"/>',
    "sun": '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
    "home": '<path d="m3 11 9-8 9 8"/><path d="M5 10v10h14V10"/>',
    "user": '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
    "search": '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
}


def fusion(defauts: dict, valeurs: dict) -> dict:
    """Fusion récursive : les valeurs surchargent les défauts (les listes sont remplacées)."""
    sortie = copy.deepcopy(defauts)
    for cle, val in (valeurs or {}).items():
        if isinstance(val, dict) and isinstance(sortie.get(cle), dict):
            sortie[cle] = fusion(sortie[cle], val)
        else:
            sortie[cle] = copy.deepcopy(val)
    return sortie


# --------------------------------------------------------------------------- couleurs
def charger_jetons(chemin: Path) -> dict[str, str]:
    """Lit les variables CSS `--nom: #RRGGBB;` d'un fichier de jetons (clé sans le « -- »)."""
    jetons: dict[str, str] = {}
    texte = chemin.read_text(encoding="utf-8")
    for nom, val in re.findall(r"--([A-Za-z0-9_-]+)\s*:\s*(#[0-9A-Fa-f]{6})\s*;", texte):
        jetons.setdefault(nom, val.upper())
    return jetons


class Couleurs:
    """Résout une couleur : hexadécimale directe, nom de la palette, ou jeton CSS facultatif."""

    def __init__(self, palette: dict[str, str] | None = None, jetons: dict[str, str] | None = None, prefixe: str = ""):
        self.palette = {k: v for k, v in (palette or {}).items()}
        self.jetons = jetons or {}
        self.prefixe = prefixe

    def __call__(self, nom: str) -> str:
        if not isinstance(nom, str):
            raise ErreurConfig(f"Couleur invalide : {nom!r} (texte attendu)")
        if RE_HEX.match(nom):
            h = nom.lstrip("#").upper()
            return "#" + (h if len(h) == 6 else "".join(c * 2 for c in h))
        if nom in self.palette:
            return self(self.palette[nom]) if self.palette[nom] != nom else nom
        for cle in (nom, self.prefixe + nom):
            if cle in self.jetons:
                return self.jetons[cle]
        raise ErreurConfig(f"Couleur inconnue : {nom!r} (ni hexadécimale #RRGGBB, ni dans theme.palette, ni dans les jetons CSS)")


def hex_rgb(h: str) -> tuple[int, int, int]:
    h = h.lstrip("#")
    return int(h[0:2], 16), int(h[2:4], 16), int(h[4:6], 16)


def rgb_hex(rgb) -> str:
    return "#%02X%02X%02X" % tuple(max(0, min(255, int(round(v)))) for v in rgb)


def melange(h: str, avec: str, t: float) -> str:
    a, b = hex_rgb(h), hex_rgb(avec)
    return rgb_hex([a[i] + (b[i] - a[i]) * t for i in range(3)])


def lum_rel(rgb) -> float:
    def c(v):
        v /= 255
        return v / 12.92 if v <= 0.03928 else ((v + 0.055) / 1.055) ** 2.4
    r, g, b = rgb
    return 0.2126 * c(r) + 0.7152 * c(g) + 0.0722 * c(b)


def contraste(l1: float, l2: float) -> float:
    a, b = max(l1, l2), min(l1, l2)
    return (a + 0.05) / (b + 0.05)


def pixels(im: Image.Image) -> list:
    """Pixels d'une image (compatible avec les versions de Pillow qui retirent getdata)."""
    f = getattr(im, "get_flattened_data", None) or im.getdata
    return list(f())


# --------------------------------------------------------------------------- validation de la configuration
def _est_nombre(v) -> bool:
    return isinstance(v, (int, float)) and not isinstance(v, bool)


def valider_config(brut: dict, base: Path) -> dict:
    """Valide et normalise la configuration ; lève ErreurConfig avec TOUS les problèmes trouvés."""
    err: list[str] = []
    if not isinstance(brut, dict):
        raise ErreurConfig("La configuration doit être un objet JSON.")
    cfg = fusion(DEFAUT, {k: v for k, v in brut.items() if k not in ("captures", "feature_graphic")})
    cfg["captures"] = copy.deepcopy(brut.get("captures"))
    cfg["feature_graphic"] = copy.deepcopy(brut.get("feature_graphic"))

    if not isinstance(cfg.get("application"), str) or not cfg["application"].strip():
        err.append("« application » : nom de l'application obligatoire (texte).")
    if not isinstance(cfg["captures"], list) or not cfg["captures"]:
        err.append("« captures » : liste non vide obligatoire.")
        cfg["captures"] = []
    fg = cfg["feature_graphic"]
    if not isinstance(fg, dict):
        err.append("« feature_graphic » : objet obligatoire.")
        fg = cfg["feature_graphic"] = {}
    langue0 = cfg["langue_defaut"]

    # thème et couleurs
    th = cfg["theme"]
    jetons: dict[str, str] = {}
    if th.get("tokens_css"):
        f = (base / th["tokens_css"]).resolve()
        if not f.is_file():
            err.append(f"« theme.tokens_css » : fichier introuvable : {f}")
        else:
            jetons = charger_jetons(f)
    C = Couleurs(th.get("palette"), jetons, th.get("prefixe_jetons", ""))

    def couleur_ok(chemin: str, valeur):
        try:
            C(valeur)
        except ErreurConfig as e:
            err.append(f"« {chemin} » : {e}")

    for k, v in th["couleurs"].items():
        couleur_ok(f"theme.couleurs.{k}", v)
    for k in ("de", "vers"):
        couleur_ok(f"theme.fond.{k}", th["fond"].get(k))
    if not _est_nombre(th["fond"].get("angle")):
        err.append("« theme.fond.angle » : nombre attendu (degrés).")
    icones = {**ICONES, **(th.get("icones") or {})}

    # source par défaut
    src0 = cfg["source"]
    if src0.get("type") not in ("html", "images"):
        err.append("« source.type » : « html » ou « images » attendu.")
    for chemin, v in (("source.viewport", src0["viewport"]),):
        if not (isinstance(v, list) and len(v) == 2 and all(isinstance(x, int) and x > 0 for x in v)):
            err.append(f"« {chemin} » : [largeur, hauteur] en pixels entiers attendu.")
    if not _est_nombre(src0["echelle"]) or src0["echelle"] <= 0:
        err.append("« source.echelle » : nombre > 0 attendu.")
    if not isinstance(src0["stockage_local"], dict) or not isinstance(src0["remplacements"], dict):
        err.append("« source.stockage_local » et « source.remplacements » : objets attendus.")
    if not isinstance(src0["masquer"], list):
        err.append("« source.masquer » : liste de sélecteurs CSS attendue.")

    # barre d'état
    be = cfg["barre_etat"]
    if be["mode"] not in ("auto", "source", "recouvrir", "aucune"):
        err.append("« barre_etat.mode » : auto, source, recouvrir ou aucune.")
    if not (_est_nombre(be["hauteur_pct"]) and 0 < be["hauteur_pct"] <= 15):
        err.append("« barre_etat.hauteur_pct » : nombre entre 0 et 15 (pourcentage de la hauteur de l'écran).")
    if be["couleur"] != "auto":
        couleur_ok("barre_etat.couleur", be["couleur"])
    if be["icones"] not in ("auto", "clair", "sombre"):
        err.append("« barre_etat.icones » : auto, clair ou sombre.")

    # contraintes
    c = cfg["contraintes"]
    n = c["captures"].get("nombre")
    if not (isinstance(n, int) and 2 <= n <= 8):
        err.append("« contraintes.captures.nombre » : entier de 2 à 8 (limites Google Play).")
    for chemin in ("alt_max", "contraste_min"):
        if not _est_nombre(c[chemin]) or c[chemin] <= 0:
            err.append(f"« contraintes.{chemin} » : nombre > 0 attendu.")
    for nom in ("mots_interdits", "mots_interdits_ajout", "mots_interdits_regex"):
        if nom in c and not (isinstance(c[nom], list) and all(isinstance(x, str) for x in c[nom])):
            err.append(f"« contraintes.{nom} » : liste de textes attendue.")
    for rx in c.get("mots_interdits_regex", []) if isinstance(c.get("mots_interdits_regex"), list) else []:
        try:
            re.compile(rx)
        except re.error as e:
            err.append(f"« contraintes.mots_interdits_regex » : expression invalide {rx!r} ({e}).")

    # textes (commun à l'image de présentation et aux captures)
    def valider_textes(etiq: str, textes, champs: tuple[str, ...], puces_refs: set[str] | None = None):
        if not isinstance(textes, dict) or langue0 not in textes:
            err.append(f"« {etiq}.textes » : un bloc par langue attendu, avec au moins « {langue0} » (langue_defaut).")
            return
        for lg, t in textes.items():
            if not isinstance(t, dict):
                err.append(f"« {etiq}.textes.{lg} » : objet attendu.")
                continue
            for ch in champs:
                if not isinstance(t.get(ch), str):
                    err.append(f"« {etiq}.textes.{lg}.{ch} » : texte attendu (chaîne vide = gabarit à remplir).")
            if puces_refs is not None:
                p = t.get("puces", {})
                if not isinstance(p, dict) or not all(isinstance(v, str) for v in p.values()):
                    err.append(f"« {etiq}.textes.{lg}.puces » : objet {{référence: texte}} attendu.")
                else:
                    for r in puces_refs - set(p):
                        err.append(f"« {etiq}.textes.{lg}.puces » : texte manquant pour la référence « {r} ».")

    def valider_fond(etiq: str, fond):
        if fond is None:
            return
        if not isinstance(fond, dict):
            err.append(f"« {etiq}.fond » : objet {{de, vers, angle}} attendu.")
            return
        for k in ("de", "vers"):
            if k in fond:
                couleur_ok(f"{etiq}.fond.{k}", fond[k])
        if "angle" in fond and not _est_nombre(fond["angle"]):
            err.append(f"« {etiq}.fond.angle » : nombre attendu.")

    # captures
    ids: set[str] = set()
    for i, e in enumerate(cfg["captures"]):
        etiq = f"captures[{i}]"
        if not isinstance(e, dict):
            err.append(f"« {etiq} » : objet attendu.")
            continue
        ident = e.get("id")
        if not isinstance(ident, str) or not RE_ID.match(ident):
            err.append(f"« {etiq}.id » : identifiant (lettres, chiffres, tiret) obligatoire.")
            continue
        etiq = f"captures[{ident}]"
        if ident in ids or ident == "feature-graphic":
            err.append(f"« {etiq} » : identifiant en double ou réservé.")
        ids.add(ident)
        e.setdefault("fichier", f"{ident}.png")
        if not (isinstance(e["fichier"], str) and e["fichier"].lower().endswith(".png") and "/" not in e["fichier"] and "\\" not in e["fichier"]):
            err.append(f"« {etiq}.fichier » : nom de fichier .png sans dossier.")
        valider_fond(etiq, e.get("fond"))
        e["fond"] = fusion(th["fond"], e.get("fond") or {})
        e.setdefault("puces", [])
        refs: set[str] = set()
        if not isinstance(e["puces"], list):
            err.append(f"« {etiq}.puces » : liste attendue.")
            e["puces"] = []
        for j, pz in enumerate(e["puces"]):
            pe = f"{etiq}.puces[{j}]"
            if not isinstance(pz, dict):
                err.append(f"« {pe} » : objet attendu.")
                continue
            if pz.get("icone") not in icones:
                err.append(f"« {pe}.icone » : icône inconnue {pz.get('icone')!r} (connues : {', '.join(sorted(icones))}).")
            if pz.get("cote") not in ("gauche", "droite"):
                err.append(f"« {pe}.cote » : « gauche » ou « droite ».")
            if not (_est_nombre(pz.get("y")) and 0 <= pz["y"] <= 1920):
                err.append(f"« {pe}.y » : position verticale en pixels (0 à 1920).")
            if not isinstance(pz.get("texte_ref"), str):
                err.append(f"« {pe}.texte_ref » : référence de texte obligatoire.")
            else:
                refs.add(pz["texte_ref"])
        valider_textes(etiq, e.get("textes"), ("titre", "alt"), refs)
        # source de l'écran : défauts globaux + surcharges de la capture
        s = e.get("source") or {}
        if not isinstance(s, dict):
            err.append(f"« {etiq}.source » : objet attendu.")
            s = {}
        fus = fusion(src0, {k: v for k, v in s.items() if k not in ("masquer",)})
        fus["masquer"] = list(src0.get("masquer", [])) + list(s.get("masquer", []) if isinstance(s.get("masquer", []), list) else [])
        e["source"] = fus
        if fus.get("type") == "images":
            if not isinstance(fus.get("image"), str):
                err.append(f"« {etiq}.source.image » : chemin de l'image (PNG de l'émulateur) obligatoire.")
        elif fus.get("type") == "html":
            if not isinstance(fus.get("url"), str):
                err.append(f"« {etiq}.source.url » (ou « source.url » global) : URL ou fichier HTML obligatoire.")
        # barre d'état de la capture
        b = fusion(be, e.get("barre_etat") or {})
        if b["mode"] == "auto":
            b["mode"] = "recouvrir" if fus.get("type") == "images" else ("source" if fus.get("selecteur_heure") else "aucune")
        if b["mode"] == "source" and fus.get("type") == "images":
            err.append(f"« {etiq}.barre_etat.mode » : « source » impossible avec des images ; utiliser « recouvrir » ou « aucune ».")
        if b["mode"] == "source" and fus.get("type") == "html" and not fus.get("selecteur_heure"):
            err.append(f"« {etiq}.barre_etat.mode » : « source » demande source.selecteur_heure (élément qui affiche l'heure).")
        e["barre_etat"] = b

    # image de présentation
    fg.setdefault("fichier", "feature-graphic-1024x500.png")
    if not (isinstance(fg["fichier"], str) and fg["fichier"].lower().endswith(".png") and "/" not in fg["fichier"] and "\\" not in fg["fichier"]):
        err.append("« feature_graphic.fichier » : nom de fichier .png sans dossier.")
    valider_fond("feature_graphic", fg.get("fond"))
    fg["fond"] = fusion(th["fond"], fg.get("fond") or {})
    valider_textes("feature_graphic", fg.get("textes"), ("slogan", "alt"))
    ordre = [e["id"] for e in cfg["captures"] if isinstance(e, dict) and isinstance(e.get("id"), str)]
    fg.setdefault("ecrans", {})
    if isinstance(fg["ecrans"], dict) and ordre:
        fg["ecrans"].setdefault("gauche", ordre[0])
        fg["ecrans"].setdefault("droite", ordre[min(1, len(ordre) - 1)])
        for cote in ("gauche", "droite"):
            if fg["ecrans"][cote] not in ids:
                err.append(f"« feature_graphic.ecrans.{cote} » : capture inconnue {fg['ecrans'][cote]!r}.")
    else:
        err.append("« feature_graphic.ecrans » : objet {gauche, droite} attendu.")
    if fg.get("halos") is not None:
        if not isinstance(fg["halos"], list):
            err.append("« feature_graphic.halos » : liste attendue.")
        else:
            for j, h in enumerate(fg["halos"]):
                if not isinstance(h, dict) or not all(k in h for k in ("couleur", "x", "y", "rayon", "opacite")):
                    err.append(f"« feature_graphic.halos[{j}] » : couleur, x, y (en %), rayon (px), opacite (0 à 1) obligatoires.")
                else:
                    couleur_ok(f"feature_graphic.halos[{j}].couleur", h["couleur"])
    # logo et police
    if th.get("logo") and not (base / th["logo"]).resolve().is_file():
        err.append(f"« theme.logo » : fichier introuvable : {(base / th['logo']).resolve()}")
    if th["police"].get("fichier") and not (base / th["police"]["fichier"]).resolve().is_file():
        err.append(f"« theme.police.fichier » : fichier introuvable : {(base / th['police']['fichier']).resolve()}")
    if not th["police"].get("famille"):
        err.append("« theme.police.famille » : nom de police obligatoire.")
    if err:
        raise ErreurConfig("\n".join(" - " + m for m in err))
    cfg["_jetons"] = jetons
    return cfg


def lexique_interdit(cfg: dict) -> tuple[list[str], list[str]]:
    c = cfg["contraintes"]
    mots = list(c["mots_interdits"]) if "mots_interdits" in c else list(MOTS_INTERDITS_DEFAUT)
    mots += c.get("mots_interdits_ajout", [])
    regex = list(c["mots_interdits_regex"]) if "mots_interdits_regex" in c else list(REGEX_INTERDITES_DEFAUT)
    return mots, regex


def mots_interdits_trouves(texte: str, cfg: dict) -> list[str]:
    mots, regex = lexique_interdit(cfg)
    bas = texte.lower()
    trouves = [m for m in dict.fromkeys(mots) if re.search(r"(?<!\w)" + re.escape(m.lower()) + r"(?!\w)", bas)]
    trouves += [m for m in regex if re.search(m, bas, re.I)]
    return trouves


# --------------------------------------------------------------------------- navigateur
def trouver_chromium() -> str | None:
    """PW_CHROMIUM, puis XM_CHROMIUM, puis recherche automatique ; None = Chromium de Playwright."""
    for var in ("PW_CHROMIUM", "XM_CHROMIUM"):
        v = os.environ.get(var)
        if v:
            if Path(v).is_file():
                return v
            raise SystemExit(f"{var} pointe vers un fichier introuvable : {v}")
    racines = [os.environ.get("PLAYWRIGHT_BROWSERS_PATH"), "/opt/pw-browsers", str(Path.home() / ".cache" / "ms-playwright"),
               str(Path.home() / "Library" / "Caches" / "ms-playwright"),
               str(Path(os.environ.get("LOCALAPPDATA", "")) / "ms-playwright") if os.environ.get("LOCALAPPDATA") else None]
    motifs = ["chromium-*/chrome-linux/chrome", "chromium-*/chrome-linux64/chrome", "chromium-*/chrome-win/chrome.exe",
              "chromium-*/chrome-win64/chrome.exe", "chromium-*/chrome-mac/Chromium.app/Contents/MacOS/Chromium",
              "chromium-*/chrome-mac-arm64/Chromium.app/Contents/MacOS/Chromium"]
    for racine in filter(None, racines):
        for motif in motifs:
            trouves = sorted(glob.glob(str(Path(racine) / motif)), reverse=True)
            for t in trouves:
                if Path(t).is_file():
                    return t
        p = Path(racine) / "chromium"
        if p.is_file():
            return str(p)
    usuels = ["/usr/bin/chromium", "/usr/bin/chromium-browser", "/usr/bin/google-chrome", "/usr/bin/google-chrome-stable",
              "/snap/bin/chromium", "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome",
              "/Applications/Chromium.app/Contents/MacOS/Chromium"]
    for var in ("PROGRAMFILES", "PROGRAMFILES(X86)", "LOCALAPPDATA"):
        if os.environ.get(var):
            usuels.append(str(Path(os.environ[var]) / "Google" / "Chrome" / "Application" / "chrome.exe"))
    for u in usuels:
        if Path(u).is_file():
            return u
    for nom in ("chromium", "chromium-browser", "google-chrome", "chrome"):
        w = shutil.which(nom)
        if w:
            return w
    return None


def lancer_navigateur(p):
    chemin = trouver_chromium()
    try:
        return p.chromium.launch(executable_path=chemin) if chemin else p.chromium.launch()
    except Exception as exc:  # noqa: BLE001 - message d'aide pour l'utilisateur
        raise SystemExit("Chromium introuvable ou impossible à lancer : définir PW_CHROMIUM (ou XM_CHROMIUM) vers l'exécutable, "
                         f"ou exécuter « playwright install chromium ». Détail : {str(exc).splitlines()[0]}")


def uri(p: Path) -> str:
    return p.resolve().as_uri()


def url_source(url: str, base: Path) -> str:
    if re.match(r"^[A-Za-z][A-Za-z0-9+.-]*://", url):
        return url
    return uri(base / url)


# --------------------------------------------------------------------------- capture HTML (Playwright)
JS_HEURE = """([sel, heure]) => {
  window.setInterval = () => 0;   // l'application ne rafraîchit plus l'heure
  if (!sel) return {heure: null};
  const el = document.querySelector(sel);
  if (!el) return {heure: null, erreur: 'sélecteur introuvable : ' + sel};
  el.textContent = heure;
  return {heure: el.textContent.trim()};
}"""

JS_REMPLACER = """([a, b]) => { const w = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
  while (w.nextNode()) { if (w.currentNode.nodeValue.includes(a)) w.currentNode.nodeValue = w.currentNode.nodeValue.split(a).join(b); } }"""


def css_police(cfg: dict, base: Path) -> tuple[str, str]:
    """(règle @font-face éventuelle, pile de polices CSS)."""
    p = cfg["theme"]["police"]
    face = ""
    if p.get("fichier"):
        f = (base / p["fichier"]).resolve()
        fmt = {".woff2": "woff2", ".woff": "woff", ".ttf": "truetype", ".otf": "opentype"}.get(f.suffix.lower(), "woff2")
        face = (f"@font-face{{font-family:'{p['famille']}';font-weight:{p['graisses']};font-style:normal;"
                f"src:url('{uri(f)}') format('{fmt}');}}")
    return face, f"'{p['famille']}',{p['repli']}"


def capturer_ecrans(cfg: dict, base: Path, cache: Path, ids: list[str] | None) -> dict[str, dict]:
    """Capture les écrans de source « html ». Retourne les infos de barre d'état par identifiant."""
    cibles = [e for e in cfg["captures"] if e["source"]["type"] == "html" and (not ids or e["id"] in ids)]
    infos: dict[str, dict] = {}
    if not cibles:
        return infos
    from playwright.sync_api import sync_playwright

    face, _ = css_police(cfg, base)
    (cache / "brut").mkdir(parents=True, exist_ok=True)
    with sync_playwright() as p:
        nav = lancer_navigateur(p)
        for e in cibles:
            s = e["source"]
            ctx = nav.new_context(viewport={"width": s["viewport"][0], "height": s["viewport"][1]},
                                  device_scale_factor=s["echelle"], locale=s["locale"])
            if s["stockage_local"]:
                script = "try{" + "".join(
                    "localStorage.setItem(%s,%s);" % (json.dumps(k), json.dumps(v if isinstance(v, str) else json.dumps(v)))
                    for k, v in s["stockage_local"].items()) + "}catch(e){}"
                ctx.add_init_script(script)
            pg = ctx.new_page()
            pg.goto(url_source(s["url"], base) + s.get("route", ""))
            css = (face if s["police_dans_page"] else "") + "".join(f"{sel}{{display:none!important}}" for sel in s["masquer"]) + s["css"]
            if css:
                pg.add_style_tag(content=css)
            if s.get("attendre_selecteur"):
                pg.wait_for_selector(s["attendre_selecteur"], timeout=15000)
            pg.wait_for_timeout(s["attente_ms"])
            pg.evaluate("document.fonts.ready")
            if s["js"]:
                pg.evaluate(s["js"])
            for a, b in s["remplacements"].items():
                pg.evaluate(JS_REMPLACER, [a, b])
            info = pg.evaluate(JS_HEURE, [s.get("selecteur_heure"), e["barre_etat"]["heure"]])
            info["mode"] = e["barre_etat"]["mode"]
            infos[e["id"]] = info
            dest = cache / "brut" / f"{e['id']}.png"
            if s.get("selecteur_ecran"):
                pg.locator(s["selecteur_ecran"]).screenshot(path=str(dest))
            else:
                pg.screenshot(path=str(dest))
            ctx.close()
        nav.close()
    return infos


def source_image(e: dict, base: Path, cache: Path) -> Path:
    """Chemin de l'image brute d'une capture (capturée, ou fournie telle quelle)."""
    if e["source"]["type"] == "images":
        return (base / e["source"]["image"]).resolve()
    return cache / "brut" / f"{e['id']}.png"


# --------------------------------------------------------------------------- composition HTML/CSS
def fond_css(f: dict, C: Couleurs) -> str:
    return f"linear-gradient({f['angle']}deg,{C(f['de'])} 0%,{C(f['vers'])} 100%)"


def svg_icone(cfg: dict, nom: str, taille: int) -> str:
    corps = {**ICONES, **cfg["theme"].get("icones", {})}[nom]
    return f'<svg viewBox="0 0 24 24" width="{taille}" height="{taille}" aria-hidden="true">{corps}</svg>'


def css_base(cfg: dict, C: Couleurs, base: Path) -> str:
    face, pile = css_police(cfg, base)
    co = cfg["theme"]["couleurs"]
    return f"""
{face}
*{{box-sizing:border-box;margin:0;padding:0}}
html,body{{margin:0}}
body{{font-family:{pile};color:{C(co['texte'])};overflow:hidden;position:relative}}
.notext [data-txt]{{color:transparent!important}}
.notext [data-txt] svg{{opacity:0}}
.ecran-img{{display:block;width:100%;height:100%;object-fit:cover;object-position:top}}
.cadre{{position:absolute;background:{C(co['cadre_appareil'])};box-shadow:0 40px 90px rgba(0,0,0,.40),0 0 0 2px rgba(255,255,255,.10) inset}}
.cadre .ecran{{position:absolute;overflow:hidden;background:#fff}}
.cadre .trou{{position:absolute;border-radius:50%;background:#0B0A14;box-shadow:0 0 0 2px #1d1b2e}}
.puce{{position:absolute;display:flex;align-items:center;gap:18px;background:{C(co['puce_fond'])};color:{C(co['puce_texte'])};
  border-radius:999px;box-shadow:0 22px 44px rgba(0,0,0,.32);font-weight:700;white-space:nowrap}}
.puce .ico{{flex:none;border-radius:50%;background:{C(co['puce_icone_fond'])};color:{C(co['puce_icone'])};display:grid;place-items:center}}
.puce svg{{fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}}
.halo{{position:absolute;border-radius:50%;filter:blur(2px)}}
.barre-propre{{position:absolute;left:0;top:0;width:100%;display:flex;align-items:center;justify-content:space-between;font-weight:600;
  font-variant-numeric:tabular-nums;z-index:5}}
.barre-propre .ic{{display:flex;align-items:center}}
.barre-propre svg{{display:block}}
"""


def couleur_barre(chemin: Path, pct: float) -> str:
    """Couleur de fond juste sous la barre d'état d'une image (médiane d'une ligne de pixels)."""
    im = Image.open(chemin).convert("RGB")
    w, h = im.size
    y = min(h - 1, int(h * pct / 100) + 3)
    ligne = im.crop((int(w * 0.05), y, int(w * 0.95), y + 1))
    px = pixels(ligne)
    med = [sorted(p[i] for p in px)[len(px) // 2] for i in range(3)]
    return rgb_hex(med)


def html_barre(cfg: dict, e: dict, brut: Path, ecran_h: int) -> str:
    """Barre d'état propre superposée à l'image : heure figée, signal, Wi-Fi, batterie pleine, rien d'autre."""
    b = e["barre_etat"]
    if b["mode"] != "recouvrir":
        return ""
    h = ecran_h * b["hauteur_pct"] / 100
    fond = b["couleur"] if b["couleur"] != "auto" else couleur_barre(brut, b["hauteur_pct"])
    fond = Couleurs()(fond) if RE_HEX.match(fond) else fond
    if b["icones"] == "auto":
        encre = "#111111" if lum_rel(hex_rgb(fond)) > 0.4 else "#FFFFFF"
    else:
        encre = "#111111" if b["icones"] == "sombre" else "#FFFFFF"
    ih = round(h * 0.42)
    signal = (f'<svg class="sig" viewBox="0 0 18 12" height="{ih}" fill="{encre}" style="margin-right:{round(h*.22)}px">'
              '<rect x="0" y="8" width="3" height="4" rx=".6"/><rect x="5" y="5.5" width="3" height="6.5" rx=".6"/>'
              '<rect x="10" y="3" width="3" height="9" rx=".6"/><rect x="15" y="0" width="3" height="12" rx=".6"/></svg>')
    wifi = (f'<svg class="wifi" viewBox="0 0 18 13" height="{ih}" fill="none" stroke="{encre}" stroke-width="1.9" stroke-linecap="round" '
            f'style="margin-right:{round(h*.22)}px"><path d="M1.5 4.6a10.5 10.5 0 0 1 15 0"/><path d="M4.2 7.6a6.6 6.6 0 0 1 9.6 0"/>'
            f'<circle cx="9" cy="11" r="1.3" fill="{encre}" stroke="none"/></svg>')
    batterie = (f'<svg class="bat" viewBox="0 0 26 12" height="{ih}" fill="none"><rect x=".5" y=".5" width="22" height="11" rx="3" stroke="{encre}" opacity=".55"/>'
                f'<rect class="plein" x="2" y="2" width="19" height="8" rx="1.8" fill="{encre}"/><rect x="24" y="4" width="1.8" height="4" rx=".8" fill="{encre}" opacity=".55"/></svg>')
    return (f'<div class="barre-propre" data-barre="1" style="height:{h:.1f}px;background:{fond};color:{encre};font-size:{h*0.5:.1f}px;padding:0 {round(h*0.8)}px">'
            f'<span class="h">{html.escape(b["heure"])}</span><span class="ic">{signal}{wifi}{batterie}</span></div>')


JS_BARRE_COMPOSEE = """() => {
  const b = document.querySelector('.barre-propre'); if (!b) return null;
  const plein = b.querySelector('.bat .plein');
  return {heure: b.querySelector('.h').textContent.trim(), svgs: b.querySelectorAll('svg').length,
          remplissage: plein ? plein.getAttribute('width') : null};
}"""


def halos_capture(e: dict, C: Couleurs, W: int, H: int) -> str:
    clair = melange(C(e["fond"]["de"]), "#FFFFFF", 0.45)
    sombre = melange(C(e["fond"]["vers"]), "#000000", 0.35)
    return (f'<div class="halo" style="left:-260px;top:{round(H*0.583)}px;width:700px;height:700px;background:radial-gradient(circle,{clair}55,transparent 68%)"></div>'
            f'<div class="halo" style="right:-300px;top:-120px;width:760px;height:760px;background:radial-gradient(circle,{sombre}88,transparent 68%)"></div>')


def html_capture(cfg: dict, C: Couleurs, base: Path, e: dict, texte: dict, brut: Path) -> str:
    ml = cfg["mise_en_page"]
    W, H = 1080, 1920
    iw, ih = Image.open(brut).size
    ecran_w = ml["ecran_largeur"]
    ecran_h = round(ecran_w * ih / iw)
    biseau = ml["biseau"]
    cadre_w, cadre_h = ecran_w + 2 * biseau, ecran_h + 2 * biseau
    cadre_x = (W - cadre_w) // 2
    puces = ""
    for pz in e["puces"]:
        txt = html.escape(texte["puces"][pz["texte_ref"]])
        cote = "left:36px" if pz["cote"] == "gauche" else "right:36px"
        puces += (f'<div class="puce" data-area style="{cote};top:{pz["y"]}px;padding:16px 38px 16px 16px;font-size:34px;">'
                  f'<span class="ico" style="width:76px;height:76px;">{svg_icone(cfg, pz["icone"], 42)}</span><span data-txt>{txt}</span></div>')
    trou = (f'<div class="trou" style="left:{cadre_w//2-12}px;top:{biseau+22}px;width:24px;height:24px"></div>' if ml["trou_camera"] else "")
    return f"""<!doctype html><html lang="{html.escape(cfg['_langue'])}"><head><meta charset="utf-8"><style>{css_base(cfg, C, base)}
body{{width:{W}px;height:{H}px;background:{fond_css(e['fond'], C)}}}
.titre{{position:absolute;left:70px;right:70px;top:{ml['titre_haut']}px;text-align:center;font-weight:800;font-size:{ml['titre_taille']}px;line-height:1.14;letter-spacing:-.01em;text-wrap:balance}}
</style></head><body>
{halos_capture(e, C, W, H)}
<h1 class="titre" data-txt>{html.escape(texte['titre'])}</h1>
<div class="cadre" style="left:{cadre_x}px;top:{ml['cadre_y']}px;width:{cadre_w}px;height:{cadre_h}px;border-radius:{ml['rayon_cadre']}px">
  <div class="ecran" style="left:{biseau}px;top:{biseau}px;width:{ecran_w}px;height:{ecran_h}px;border-radius:{ml['rayon_ecran']}px">
    <img class="ecran-img" src="{uri(brut)}" alt="">{html_barre(cfg, e, brut, ecran_h)}</div>
  {trou}
</div>
{puces}
</body></html>"""


def html_feature(cfg: dict, C: Couleurs, base: Path, caps: dict[str, Path], texte: dict) -> str:
    fg = cfg["feature_graphic"]
    ml = cfg["mise_en_page"]
    W, H = 1024, 500
    logo = cfg["theme"].get("logo")
    en_logo = f'<img src="{uri((base / logo))}" alt="">' if logo else f'<p class="nom" data-txt>{html.escape(cfg["application"])}</p>'

    def tel(cote, x, y, rot):
        brut = caps[fg["ecrans"][cote]]
        w, b = ml["feature_telephone_largeur"], 8
        iw, ih = Image.open(brut).size
        sh = round(w * ih / iw)
        return (f'<div class="cadre" style="left:{x}px;top:{y}px;width:{w+2*b}px;height:{sh+2*b}px;border-radius:44px;transform:rotate({rot}deg);transform-origin:center;'
                f'box-shadow:0 26px 60px rgba(0,0,0,.5)"><div class="ecran" style="left:{b}px;top:{b}px;width:{w}px;height:{sh}px;border-radius:36px">'
                f'<img class="ecran-img" src="{uri(brut)}" alt=""></div></div>')

    halos_cfg = fg.get("halos")
    if halos_cfg is None:
        halos_cfg = [{"couleur": melange(C(fg["fond"]["de"]), "#FFFFFF", 0.45), "x": 14, "y": 10, "rayon": 380, "opacite": 0.35},
                     {"couleur": melange(C(fg["fond"]["vers"]), "#000000", 0.35), "x": 92, "y": 105, "rayon": 420, "opacite": 0.55}]
    halos = "".join(
        f'<div class="halo" style="left:calc({h["x"]}% - {h["rayon"]}px);top:calc({h["y"]}% - {h["rayon"]}px);width:{2*h["rayon"]}px;height:{2*h["rayon"]}px;'
        f'background:radial-gradient(circle,{C(h["couleur"])}{int(h["opacite"]*255):02X},transparent 68%)"></div>' for h in halos_cfg)
    lw = ml["feature_telephone_largeur"]
    return f"""<!doctype html><html lang="{html.escape(cfg['_langue'])}"><head><meta charset="utf-8"><style>{css_base(cfg, C, base)}
body{{width:{W}px;height:{H}px;background:{fond_css(fg['fond'], C)}}}
.centre{{position:absolute;left:50%;top:0;height:100%;width:520px;transform:translateX(-50%);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:30px}}
.centre img{{max-width:420px;max-height:150px;display:block}}
.nom{{font-weight:800;font-size:64px;letter-spacing:-.01em;text-align:center}}
.slogan{{font-weight:700;font-size:31px;line-height:1.25;letter-spacing:0;text-align:center}}
</style></head><body>
{halos}
{tel('gauche', -62, 118, -8)}
{tel('droite', W - lw - 16 + 62 - 8, 118, 8)}
<div class="centre">{en_logo}<p class="slogan" data-txt>{html.escape(texte['slogan'])}</p></div>
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


def composer(cfg: dict, C: Couleurs, base: Path, cache: Path, sortie: Path, ids) -> tuple[dict, dict]:
    """Compose et exporte les visuels. Retourne (mesures de texte, infos de barre d'état composées)."""
    from playwright.sync_api import sync_playwright

    langue = cfg["_langue"]
    tmp = cache / "compo"
    tmp.mkdir(parents=True, exist_ok=True)
    (sortie / "screenshots").mkdir(parents=True, exist_ok=True)
    caps = {e["id"]: source_image(e, base, cache) for e in cfg["captures"]}
    for ident, chemin in caps.items():
        if not chemin.is_file():
            raise SystemExit(f"Image source introuvable pour « {ident} » : {chemin}")
        try:
            Image.open(chemin).verify()
        except Exception as exc:  # noqa: BLE001
            raise SystemExit(f"Image source illisible pour « {ident} » : {chemin} ({exc})")
    mesures: dict = {}
    barres: dict = {}
    with sync_playwright() as p:
        nav = lancer_navigateur(p)
        taches = []
        if not ids or "feature-graphic" in ids:
            fg = cfg["feature_graphic"]
            taches.append(("feature-graphic", (1024, 500), html_feature(cfg, C, base, caps, fg["textes"][langue]), sortie / fg["fichier"], None))
        for e in cfg["captures"]:
            if ids and e["id"] not in ids:
                continue
            taches.append((e["id"], (1080, 1920), html_capture(cfg, C, base, e, e["textes"][langue], caps[e["id"]]),
                           sortie / "screenshots" / e["fichier"], e))
        for ident, (w, h), code, dest, e in taches:
            pg = nav.new_page(viewport={"width": w, "height": h}, device_scale_factor=1)
            f = tmp / f"{ident}.html"
            f.write_text(code, encoding="utf-8")
            pg.goto(uri(f))
            pg.evaluate("document.fonts.ready")
            pg.wait_for_timeout(250)
            mesures[ident] = {"textes": pg.evaluate(MESURES_JS)}
            if e is not None and e["barre_etat"]["mode"] == "recouvrir":
                info = pg.evaluate(JS_BARRE_COMPOSEE) or {}
                info["mode"] = "recouvrir"
                barres[ident] = info
            brut_png = tmp / f"{ident}.rgba.png"
            pg.screenshot(path=str(brut_png))
            pg.evaluate("document.body.classList.add('notext')")
            pg.wait_for_timeout(100)
            sans_txt = tmp / f"{ident}.notext.png"
            pg.screenshot(path=str(sans_txt))
            pg.close()
            dest.parent.mkdir(parents=True, exist_ok=True)
            Image.open(brut_png).convert("RGB").save(dest, format="PNG", optimize=True)  # 24 bits, sans canal alpha
            mesures[ident]["sans_texte"] = str(sans_txt)
        nav.close()
    return mesures, barres


# --------------------------------------------------------------------------- contrôles
def lire_ihdr(chemin: Path):
    d = chemin.read_bytes()
    if d[:8] != b"\x89PNG\r\n\x1a\n":
        raise ValueError("pas un PNG")
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
    """Aire de l'union de rectangles (balayage sur coordonnées compressées)."""
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


def textes_visibles(cfg: dict, langue: str) -> list[tuple[str, str]]:
    """(étiquette, texte) de tous les textes publics des visuels : slogan, titres, puces, alt."""
    fg = cfg["feature_graphic"]["textes"][langue]
    out = [("feature-graphic/slogan", fg["slogan"]), ("feature-graphic/alt", fg["alt"])]
    for e in cfg["captures"]:
        t = e["textes"][langue]
        out.append((f"{e['id']}/titre", t["titre"]))
        out.append((f"{e['id']}/alt", t["alt"]))
        out += [(f"{e['id']}/puce:{k}", v) for k, v in t.get("puces", {}).items()]
    return out


def langues_manquantes(cfg: dict, langue: str) -> list[str]:
    manq = []
    if langue not in cfg["feature_graphic"]["textes"]:
        manq.append("feature_graphic")
    manq += [e["id"] for e in cfg["captures"] if langue not in e["textes"]]
    return manq


def fond_vif(fond: dict, C: Couleurs, v: dict) -> tuple[bool, str]:
    """Le fond d'une capture est un dégradé de deux couleurs saturées et ni noires ni blanches."""
    det, bon = [], True
    for cle in ("de", "vers"):
        r, g, b = hex_rgb(C(fond[cle]))
        _, sa, va = rgb_to_hsv(r / 255, g / 255, b / 255)
        bon = bon and sa > v["saturation_min"] and v["valeur_min"] <= va <= v["valeur_max"]
        det.append(f"{C(fond[cle])} s={sa:.2f} v={va:.2f}")
    return bon, " ; ".join(det)


def controler(cfg: dict, C: Couleurs, base: Path, langue: str, sortie: Path, mesures: dict, infos_barre: dict, ids=None) -> list[dict]:
    """Tous les contrôles de conformité. Retourne la liste {visuel, controle, ok, detail}."""
    c = cfg["contraintes"]
    vifs = c["couleurs_vives"]
    res: list[dict] = []

    def ok(visuel, controle, passe, detail):
        res.append({"visuel": visuel, "controle": controle, "ok": bool(passe), "detail": detail})

    cibles = [("feature-graphic", sortie / cfg["feature_graphic"]["fichier"], c["feature_graphic"])]
    cibles += [(e["id"], sortie / "screenshots" / e["fichier"], c["captures"]) for e in cfg["captures"]]

    ok("ensemble", "nombre de captures", len(cfg["captures"]) == c["captures"]["nombre"],
       f"{len(cfg['captures'])} (attendu {c['captures']['nombre']})")
    for ident, chemin, regle in cibles:
        if ids and ident not in ids:
            continue
        if not chemin.exists():
            ok(ident, "fichier présent", False, str(chemin))
            continue
        try:
            w, h, prof, typ, morc = lire_ihdr(chemin)
        except (ValueError, struct.error) as exc:
            ok(ident, "fichier PNG lisible", False, str(exc))
            continue
        ok(ident, "dimensions", (w, h) == (regle["largeur"], regle["hauteur"]), f"{w}x{h} (attendu {regle['largeur']}x{regle['hauteur']})")
        ok(ident, "PNG 24 bits sans alpha", prof == 8 and typ == 2 and "tRNS" not in morc and "PLTE" not in morc,
           f"profondeur {prof}, type de couleur {typ} (2 = RVB), blocs {sorted(morc - {'IHDR', 'IDAT', 'IEND'})}")
        mo = chemin.stat().st_size / 1048576
        ok(ident, "poids", mo < regle["poids_max_mo"], f"{mo:.2f} Mo (< {regle['poids_max_mo']} Mo)")
        if ident != "feature-graphic":
            ok(ident, "ratio 9:16", abs(w / h - 9 / 16) < 1e-9, f"{w}/{h}")
        im = Image.open(chemin)
        ok(ident, "mode Pillow RGB", im.mode == "RGB", im.mode)
        m = mesures.get(ident)
        if m:
            rects = [r for t in m["textes"] for r in t["rects"]]
            pct = 100 * aire_union(rects) / (w * h)
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
                    lums = sorted(lum_rel(px) for px in set(pixels(sans.crop(box))))
                    # texte clair : fond le plus clair (99,5e centile) ; texte foncé : fond le plus sombre
                    bg = lums[min(len(lums) - 1, int(len(lums) * 0.995))] if col > 0.5 else lums[int(len(lums) * 0.005)]
                    pire = min(pire, contraste(col, bg))
            ok(ident, f"contraste texte >= {c['contraste_min']}:1", pire >= c["contraste_min"], f"pire cas {pire:.2f}:1")
        # couleurs vives (évite blanc pur, noir, gris foncé dominants)
        px = pixels(im.convert("RGB").resize((128, max(1, 128 * h // w))))
        n = len(px)
        blanc = sum(1 for r, g, b in px if min(r, g, b) > 240) / n
        noir = sum(1 for r, g, b in px if max(r, g, b) < 40) / n
        sat = sum(rgb_to_hsv(r / 255, g / 255, b / 255)[1] for r, g, b in px) / n
        if ident == "feature-graphic":
            ok(ident, f"couleurs vives (blanc < {vifs['fg_blanc_max']*100:.0f} %, noir < {vifs['fg_noir_max']*100:.0f} %, saturation moyenne > {vifs['fg_saturation_min']})",
               blanc < vifs["fg_blanc_max"] and noir < vifs["fg_noir_max"] and sat > vifs["fg_saturation_min"],
               f"blanc {blanc*100:.0f} %, noir {noir*100:.1f} %, saturation {sat:.2f}")
        else:
            e = next(x for x in cfg["captures"] if x["id"] == ident)
            vif, det = fond_vif(e["fond"], C, vifs)
            ok(ident, "fond de marque vif (dégradé de couleurs saturées, ni noir ni blanc)", vif, det)
            b = e["barre_etat"]
            i = infos_barre.get(ident) if infos_barre else None
            if b["mode"] == "aucune":
                ok(ident, "barre d'état propre", True, "sans barre d'état dans la source (mode « aucune »)")
            elif i is not None:
                if b["mode"] == "recouvrir":
                    bon = i.get("heure") == b["heure"] and i.get("svgs") == 3 and i.get("remplissage") == "19"
                else:
                    bon = i.get("heure") == b["heure"]
                lib = (f"barre d'état propre ({b['heure']}, signal, Wi-Fi, batterie pleine, aucun opérateur ni notification)" if b["mode"] == "recouvrir"
                       else f"barre d'état : heure figée à {b['heure']} (le reste de la barre vient de la source)")
                ok(ident, lib, bon, json.dumps(i, ensure_ascii=False))

    for etiq, t in textes_visibles(cfg, langue):
        if ids and etiq.split("/")[0] not in ids:
            continue
        if not t:
            ok(etiq, "texte renseigné", False, "vide (gabarit non rempli)")
            continue
        interdits = mots_interdits_trouves(t, cfg)
        ok(etiq, "mots interdits", not interdits, ", ".join(interdits) or "aucun")
        if etiq.endswith("/alt"):
            ok(etiq, f"alt <= {c['alt_max']} caractères", len(t) <= c["alt_max"], f"{len(t)}")
            ok(etiq, "alt sans « image de »", not re.match(r"\s*(image|photo|picture|screenshot|capture)\s+(de|d'|d’|of)\b", t, re.I), t[:40])

    # fiche : nom et descriptions (fichier à balises DEBUT/FIN et/ou textes inline)
    fiche = cfg["fiche"]
    textes_fiche: dict[str, str] = dict(fiche.get("textes") or {})
    if fiche.get("fichier"):
        f = (base / fiche["fichier"]).resolve()
        if not f.exists():
            ok("fiche", "fichier présent", False, str(f))
        else:
            md = f.read_text(encoding="utf-8")
            for cle in fiche["limites"]:
                mm = re.search(rf"<!--DEBUT:{re.escape(cle)}-->\s*(.*?)\s*<!--FIN:{re.escape(cle)}-->", md, re.S)
                if mm:
                    textes_fiche.setdefault(cle, mm.group(1).strip())
                else:
                    ok(f"fiche/{cle}", "balises DEBUT/FIN présentes", False, "absentes")
    for cle, t in textes_fiche.items():
        lim = fiche["limites"].get(cle)
        if lim is not None:
            ok(f"fiche/{cle}", f"longueur <= {lim}", len(t) <= lim, f"{len(t)}")
        interd = mots_interdits_trouves(t, cfg)
        ok(f"fiche/{cle}", "mots interdits", not interd, ", ".join(interd) or "aucun")
    return res


def verifier(cfg, C, base, langue, sortie: Path, cache: Path, infos_barre, ids=None, quiet=False) -> bool:
    mf = cache / f"mesures-{langue}.json"
    mesures = json.loads(mf.read_text(encoding="utf-8")) if mf.exists() else {}
    res = controler(cfg, C, base, langue, sortie, mesures, infos_barre or {}, ids)
    sortie.mkdir(parents=True, exist_ok=True)
    (sortie / "rapport-controles.json").write_text(
        json.dumps({"kit": "playstore-kit", "version_kit": VERSION_KIT, "application": cfg["application"], "langue": langue, "resultats": res},
                   ensure_ascii=False, indent=1), encoding="utf-8")
    echecs = [r for r in res if not r["ok"]]
    if not quiet:
        for r in res:
            print(("OK   " if r["ok"] else "ECHEC"), f"{r['visuel']:<34} {r['controle'][:70]:<70} {r['detail'][:90]}")
        print(f"\n{len(res) - len(echecs)}/{len(res)} contrôles passés" + ("" if not echecs else f", {len(echecs)} échec(s)"))
    return not echecs


# --------------------------------------------------------------------------- projet exemple
def initialiser(dest: Path) -> int:
    src = KIT / "example"
    if not src.is_dir():
        print(f"Dossier exemple introuvable : {src} (copier le kit complet, pas seulement le script).", file=sys.stderr)
        return 2
    if dest.exists() and any(dest.iterdir()):
        print(f"Le dossier {dest} n'est pas vide : choisir un dossier neuf.", file=sys.stderr)
        return 2
    shutil.copytree(src, dest, ignore=shutil.ignore_patterns("sortie", ".cache", "__pycache__"), dirs_exist_ok=True)
    cfg = dest / "visuels.json"
    print(f"Projet exemple créé dans {dest}\nSuite :\n  1. éditer {cfg} (textes, couleurs, source des écrans)\n"
          f"  2. python3 {Path(__file__).name} --config {cfg}")
    return 0


# --------------------------------------------------------------------------- principal
def main(argv: list[str] | None = None) -> int:
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--config", default="visuels.json", help="fichier de configuration JSON (défaut : ./visuels.json)")
    ap.add_argument("--langue", default=None, help="langue des textes (défaut : langue_defaut de la configuration)")
    ap.add_argument("--verifier", action="store_true", help="vérifier sans régénérer")
    ap.add_argument("--seulement", nargs="+", metavar="ID", help="identifiants à régénérer (ex. 02-liste feature-graphic)")
    ap.add_argument("--sortie", metavar="DIR", help="dossier de sortie (remplace « sortie » de la configuration)")
    ap.add_argument("--init", metavar="DIR", help="crée un projet exemple prêt à lancer dans DIR puis s'arrête")
    a = ap.parse_args(argv)

    if a.init:
        return initialiser(Path(a.init).resolve())

    chemin = Path(a.config).resolve()
    if not chemin.is_file():
        print(f"Configuration introuvable : {chemin}\nCréer un projet exemple avec : --init DIR", file=sys.stderr)
        return 2
    base = chemin.parent
    try:
        brut = json.loads(chemin.read_text(encoding="utf-8"))
        cfg = valider_config(brut, base)
    except json.JSONDecodeError as exc:
        print(f"Configuration invalide : JSON illisible ({exc}).", file=sys.stderr)
        return 2
    except ErreurConfig as exc:
        print(f"Configuration invalide ({chemin.name}) :\n{exc}", file=sys.stderr)
        return 2

    langue = a.langue or cfg["langue_defaut"]
    cfg["_langue"] = langue
    manq = langues_manquantes(cfg, langue)
    if manq:
        print(f"Langue « {langue} » absente des textes de : {', '.join(manq)}.", file=sys.stderr)
        return 2
    inconnus = [i for i in (a.seulement or []) if i != "feature-graphic" and i not in {e["id"] for e in cfg["captures"]}]
    if inconnus:
        print(f"Identifiants inconnus pour --seulement : {', '.join(inconnus)}", file=sys.stderr)
        return 2
    C = Couleurs(cfg["theme"].get("palette"), cfg["_jetons"], cfg["theme"].get("prefixe_jetons", ""))
    cache = (base / cfg["cache"]).resolve()
    sortie = (Path(a.sortie).resolve() if a.sortie else (base / cfg["sortie"]).resolve())
    if langue != cfg["langue_defaut"]:
        sortie = sortie / langue
    cache.mkdir(parents=True, exist_ok=True)

    vides = [e for e, t in textes_visibles(cfg, langue) if not t]
    if vides:
        print(f"Gabarit « {langue} » non rempli ({len(vides)} textes vides, ex. {vides[0]}). Renseigner la configuration avant de générer.",
              file=sys.stderr)
        return 2

    def lire(nom):
        f = cache / nom
        return json.loads(f.read_text(encoding="utf-8")) if f.exists() else {}

    if not a.verifier:
        infos = capturer_ecrans(cfg, base, cache, a.seulement)
        mesures, barres = composer(cfg, C, base, cache, sortie, a.seulement)
        infos.update(barres)
        # fusion avec le cache : un --seulement ne doit pas effacer les mesures des autres visuels
        (cache / f"mesures-{langue}.json").write_text(json.dumps({**lire(f"mesures-{langue}.json"), **mesures}, ensure_ascii=False), encoding="utf-8")
        (cache / f"barre-etat-{langue}.json").write_text(json.dumps({**lire(f"barre-etat-{langue}.json"), **infos}, ensure_ascii=False), encoding="utf-8")
    ok = verifier(cfg, C, base, langue, sortie, cache, lire(f"barre-etat-{langue}.json"), a.seulement)
    return 0 if ok else 1


if __name__ == "__main__":
    sys.exit(main())
