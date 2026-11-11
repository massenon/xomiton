#!/usr/bin/env python3
"""Kit générique de production des visuels Google Play.

Produit, à partir d'un fichier de configuration JSON :
  - une image de présentation 1024 x 500 ;
  - N captures d'écran 1080 x 1920 (9:16) ;
  - (facultatif) des captures de tablettes 7 et 10 pouces, portrait 9:16 et/ou paysage 16:9 ;
tous en PNG 24 bits SANS canal alpha ;
  - (facultatif) l'icône 512 x 512 (PNG 32 bits, carré plein sans coins arrondis ni ombre) ;
puis vérifie les règles Google Play connues (dimensions, alpha, poids, ratio, aire du texte,
contraste AA, mots interdits, textes alternatifs, couleurs vives, barre d'état, icône) et écrit
rapport-controles.json. Les règles codées sont lues dans google_rules.json (version et date de
vérification) ; le kit n'interroge jamais Google : aucun accès réseau.
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
    python3 build_playstore.py --icone                     # seulement l'icône 512 x 512 (bloc « icone »)
    python3 build_playstore.py --regles                    # liste les règles Google codées et leur date de vérification

Dépendances : pip install playwright pillow. Chromium : variable PW_CHROMIUM ou XM_CHROMIUM,
sinon recherche automatique, sinon Chromium de Playwright (playwright install chromium).
Tous les chemins de la configuration sont relatifs au fichier de configuration.
"""
from __future__ import annotations

import argparse
import copy
import datetime
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
VERSION_KIT = "1.1.0"
REGLES_FICHIER = KIT / "google_rules.json"   # règles Google codées, version et date de vérification
RE_HEX = re.compile(r"^#(?:[0-9A-Fa-f]{6}|[0-9A-Fa-f]{3})$")
RE_ID = re.compile(r"^[A-Za-z0-9][A-Za-z0-9_-]*$")


MODES_BARRE = ("recouvrir", "source", "aucune", "auto")   # « auto » = recouvrir (compatibilité)


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
        # Inter (licence OFL) est embarquée dans fonts/ : utilisée par défaut, sans dépendre des polices système.
        # « fichier » (une police) ou « fichiers » ([{fichier, graisse}]) pointent vers une autre police embarquée.
        "police": {"famille": "Inter", "fichier": None, "fichiers": None, "graisses": "100 900",
                   "repli": "'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif"},
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
        "police_dans_page": True,
    },
    "barre_etat": {"mode": "recouvrir", "heure": "9:41", "hauteur_pct": 3.4, "couleur": "auto", "icones": "auto",
                   "selecteur": None},
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


# --------------------------------------------------------------------------- règles Google (google_rules.json)
def charger_regles() -> dict:
    """Lit google_rules.json (règles codées, version, date de vérification). Aucun accès réseau."""
    try:
        r = json.loads(REGLES_FICHIER.read_text(encoding="utf-8"))
    except FileNotFoundError:
        raise ErreurConfig(f"Fichier de règles introuvable : {REGLES_FICHIER} (copier le kit complet, google_rules.json compris).")
    except (json.JSONDecodeError, OSError) as exc:
        raise ErreurConfig(f"google_rules.json illisible : {exc}")
    try:
        datetime.date.fromisoformat(r["date_verification"])
        assert isinstance(r["regles"], list) and r["version"]
    except (KeyError, ValueError, AssertionError, TypeError):
        raise ErreurConfig("google_rules.json invalide : « version », « date_verification » (AAAA-MM-JJ) et « regles » sont obligatoires.")
    return r


def valeurs_regle(regles: dict, ident: str) -> dict:
    for r in regles["regles"]:
        if r.get("id") == ident:
            return r.get("valeurs", {})
    raise ErreurConfig(f"google_rules.json : règle « {ident} » absente.")


def aujourdhui() -> datetime.date:
    return datetime.date.today()


def etat_regles(regles: dict, jour: datetime.date | None = None) -> dict:
    """Âge de la dernière vérification des règles et avertissement éventuel (jamais un échec)."""
    jour = jour or aujourdhui()
    date = datetime.date.fromisoformat(regles["date_verification"])
    age = (jour - date).days
    limite = int(regles.get("validite_jours", 180))
    avert = None
    if age > limite:
        avert = (f"Les règles Google codées ont été vérifiées le {date.isoformat()} ({age} jours, limite {limite}) : "
                 "relire l'aide de la Play Console avant publication, puis mettre à jour google_rules.json (date incluse). "
                 "Le kit n'interroge pas Google.")
    elif age < 0:
        avert = f"La date de vérification des règles ({date.isoformat()}) est dans le futur : à corriger dans google_rules.json."
    return {"version": regles["version"], "date_verification": date.isoformat(), "age_jours": age, "validite_jours": limite,
            "perimee": age > limite, "avertissement": avert}


def texte_regles(regles: dict, etat: dict) -> str:
    """Liste lisible des règles codées (option --regles)."""
    lignes = [f"Règles Google Play codées dans le kit : version {regles['version']}, vérifiées le {etat['date_verification']} "
              f"({etat['age_jours']} jours ; avertissement au-delà de {etat['validite_jours']}).",
              regles.get("statut", ""), ""]
    for r in regles["regles"]:
        lignes.append(f"- {r['id']} : {r['libelle']}")
        lignes.append(f"    valeurs : {json.dumps(r.get('valeurs', {}), ensure_ascii=False)}")
        lignes.append(f"    source  : {r.get('source', '')}")
        if r.get("a_confirmer"):
            lignes.append(f"    À CONFIRMER : {r['a_confirmer']}")
    lignes.append("")
    lignes.append("Sources : " + " ; ".join(f"{k} = {v}" for k, v in regles.get("sources", {}).items()))
    if etat["avertissement"]:
        lignes += ["", "AVERTISSEMENT : " + etat["avertissement"]]
    return "\n".join(lignes)


def defauts_depuis_regles(defaut: dict, regles: dict) -> dict:
    """Défauts de configuration dont les valeurs numériques viennent de google_rules.json."""
    d = copy.deepcopy(defaut)
    fg, ph = valeurs_regle(regles, "feature_graphic"), valeurs_regle(regles, "captures_telephone")
    tx, md, alt = valeurs_regle(regles, "texte_dans_les_visuels"), valeurs_regle(regles, "metadonnees"), valeurs_regle(regles, "texte_alternatif")
    c = d["contraintes"]
    c["feature_graphic"] = {"largeur": fg["largeur"], "hauteur": fg["hauteur"], "poids_max_mo": fg["poids_max_mo"]}
    c["captures"]["poids_max_mo"] = ph["poids_max_mo"]
    c["captures"]["texte_max_pct"] = tx["texte_max_pct"]
    c["contraste_min"] = tx["contraste_min"]
    c["alt_max"] = alt["alt_max"]
    d["fiche"]["limites"] = {"nom": md["nom_max"], "court": md["court_max"], "long": md["long_max"]}
    return d

DEFAUT_TABLETTES = {
    "appareils": ["7", "10"],
    "orientations": ["portrait"],
    "formats": {"7": {"portrait": [600, 960], "paysage": [960, 600]},
                "10": {"portrait": [800, 1280], "paysage": [1280, 800]}},
    "echelle": 2,
    "canvas": {"portrait": [1080, 1920], "paysage": [1920, 1080]},
    "source": {},
    "barre_etat": {},
}
DEFAUT_ICONE = {"source": None, "fichier": "icon-512.png", "fond": "#FFFFFF", "zone_securite_pct": 80,
                "contraste_min": 3.0, "ecart_motif": 48, "transparence_partielle_max_pct": 0.5}

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


def valider_config(brut: dict, base: Path, partiel: bool = False) -> dict:
    """Valide et normalise la configuration ; lève ErreurConfig avec TOUS les problèmes trouvés.
    `partiel` : production de l'icône seule (--icone) ; captures et image de présentation ne sont pas exigées."""
    err: list[str] = []
    if not isinstance(brut, dict):
        raise ErreurConfig("La configuration doit être un objet JSON.")
    regles = charger_regles()
    if partiel:   # gabarit minimal valide : l'icône seule n'exige ni captures ni image de présentation
        lg = brut.get("langue_defaut", "fr")
        brut = {**brut, "captures": [{"id": f"x{i}", "source": {"type": "images", "image": "x.png"},
                                      "textes": {lg: {"titre": "x", "alt": "x", "puces": {}}}} for i in (1, 2)],
                "feature_graphic": {"textes": {lg: {"slogan": "x", "alt": "x"}}}, "contraintes": {"captures": {"nombre": 2}},
                "tablettes": None}
    cfg = fusion(defauts_depuis_regles(DEFAUT, regles),
                 {k: v for k, v in brut.items() if k not in ("captures", "feature_graphic", "tablettes", "icone")})
    cfg["captures"] = copy.deepcopy(brut.get("captures"))
    cfg["feature_graphic"] = copy.deepcopy(brut.get("feature_graphic"))
    cfg["_regles"] = regles

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
    if be["mode"] not in MODES_BARRE:
        err.append("« barre_etat.mode » : recouvrir (défaut), source, aucune (ou auto = recouvrir).")
    if be.get("selecteur") is not None and not isinstance(be["selecteur"], str):
        err.append("« barre_etat.selecteur » : sélecteur CSS de la barre d'état de la page (texte) ou null.")
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

    def resoudre_source_barre(e: dict, etiq: str, src_base: dict, be_base: dict) -> None:
        """Source de l'écran (défauts + surcharges de la capture) et barre d'état de la capture."""
        s = e.get("source") or {}
        if not isinstance(s, dict):
            err.append(f"« {etiq}.source » : objet attendu.")
            s = {}
        fus = fusion(src_base, {k: v for k, v in s.items() if k not in ("masquer",)})
        fus["masquer"] = list(src_base.get("masquer", [])) + list(s.get("masquer", []) if isinstance(s.get("masquer", []), list) else [])
        e["source"] = fus
        if fus.get("type") == "images":
            if not isinstance(fus.get("image"), str):
                err.append(f"« {etiq}.source.image » : chemin de l'image (PNG de l'émulateur) obligatoire.")
        elif fus.get("type") == "html":
            if not isinstance(fus.get("url"), str):
                err.append(f"« {etiq}.source.url » (ou « source.url » global) : URL ou fichier HTML obligatoire.")
        b = fusion(be_base, e.get("barre_etat") or {})
        if b["mode"] not in MODES_BARRE:
            err.append(f"« {etiq}.barre_etat.mode » : recouvrir, source, aucune (ou auto).")
        if b["mode"] == "auto":
            b["mode"] = "recouvrir"
        if b["mode"] == "source" and fus.get("type") == "images":
            err.append(f"« {etiq}.barre_etat.mode » : « source » impossible avec des images ; utiliser « recouvrir » ou « aucune ».")
        if b["mode"] == "source" and fus.get("type") == "html" and not fus.get("selecteur_heure"):
            err.append(f"« {etiq}.barre_etat.mode » : « source » demande source.selecteur_heure (élément qui affiche l'heure).")
        e["barre_etat"] = b

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
        e["genre"], e["cle"], e["canvas"], e["dossier"] = "telephone", ident, [1080, 1920], "screenshots"
        resoudre_source_barre(e, etiq, src0, be)

    # image de présentation
    fg.setdefault("fichier", "feature-graphic-1024x500.png")
    if not (isinstance(fg["fichier"], str) and fg["fichier"].lower().endswith(".png") and "/" not in fg["fichier"] and "\\" not in fg["fichier"]):
        err.append("« feature_graphic.fichier » : nom de fichier .png sans dossier.")
    valider_fond("feature_graphic", fg.get("fond"))
    fg["fond"] = fusion(th["fond"], fg.get("fond") or {})
    valider_textes("feature_graphic", fg.get("textes"), ("slogan", "alt"))
    if fg.get("ecran_web") is not None and not (isinstance(fg["ecran_web"], str) and (base / fg["ecran_web"]).resolve().is_file()):
        err.append(f"« feature_graphic.ecran_web » : image introuvable : {(base / str(fg['ecran_web'])).resolve()}")
    if fg.get("disposition") not in (None, "centre", "logo-gauche"):
        err.append("« feature_graphic.disposition » : « centre » (défaut) ou « logo-gauche ».")
    vit = fg.get("vitrine")
    if vit is not None:
        # composition « vitrine » : description, 5 puces de fonctionnalités, capture mobile, domaine, ruban ; sortie sociale facultative
        if not isinstance(vit, dict):
            err.append("« feature_graphic.vitrine » : objet attendu.")
        else:
            if not fg.get("ecran_web"):
                err.append("« feature_graphic.ecran_web » : obligatoire avec « vitrine ».")
            for ch in ("ecran_mobile",):
                if not (isinstance(vit.get(ch), str) and (base / vit[ch]).resolve().is_file()):
                    err.append(f"« feature_graphic.vitrine.{ch} » : image introuvable.")
            if not isinstance(vit.get("domaine"), str):
                err.append("« feature_graphic.vitrine.domaine » : texte attendu.")
            pu = vit.get("puces")
            if not (isinstance(pu, list) and pu and all(isinstance(d, dict) and isinstance(d.get("icone"), str)
                                                          and (base / d["icone"]).resolve().is_file() for d in pu)):
                err.append("« feature_graphic.vitrine.puces » : liste de {icone: fichier .svg, ton?: « alerte »} attendue.")
            rg = vit.get("rangees", [2, 3])
            if isinstance(pu, list) and not (isinstance(rg, list) and sum(rg) == len(pu)):
                err.append("« feature_graphic.vitrine.rangees » : nombres de puces par rangée dont la somme égale le nombre de puces.")
            if not (isinstance(vit.get("ruban"), list) and len(vit["ruban"]) >= 2):
                err.append("« feature_graphic.vitrine.ruban » : liste d'au moins 2 couleurs attendue.")
            ss = vit.get("sortie_sociale")
            if ss is not None and not (isinstance(ss, dict) and isinstance(ss.get("fichier"), str) and str(ss["fichier"]).lower().endswith(".png")
                                        and isinstance(ss.get("largeur"), int) and isinstance(ss.get("hauteur"), int)):
                err.append("« feature_graphic.vitrine.sortie_sociale » : {fichier: .png, largeur, hauteur} attendu.")
            for lg, t in (fg.get("textes") or {}).items():
                if isinstance(t, dict):
                    if not isinstance(t.get("description"), str):
                        err.append(f"« feature_graphic.textes.{lg}.description » : texte attendu avec « vitrine ».")
                    f_ = t.get("fonctions")
                    if not (isinstance(f_, list) and isinstance(pu, list) and len(f_) == len(pu) and all(isinstance(x, str) for x in f_)):
                        err.append(f"« feature_graphic.textes.{lg}.fonctions » : un libellé par puce attendu.")
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
    # tablettes 7 et 10 pouces (facultatif)
    cfg["_tablettes"] = []
    cfg["tablettes"] = None
    tb_brut = brut.get("tablettes")
    if tb_brut is not None:
        rt = valeurs_regle(regles, "captures_tablette")
        if not isinstance(tb_brut, dict):
            err.append("« tablettes » : objet attendu.")
        else:
            tb = fusion(DEFAUT_TABLETTES, {k: v for k, v in tb_brut.items() if k != "captures"})
            tb["captures"] = copy.deepcopy(tb_brut.get("captures"))
            cfg["tablettes"] = tb
            valides_ap, valides_or = ("7", "10"), ("portrait", "paysage")
            if not (isinstance(tb["appareils"], list) and tb["appareils"] and all(a in valides_ap for a in tb["appareils"])):
                err.append("« tablettes.appareils » : liste non vide parmi « 7 » et « 10 ».")
                tb["appareils"] = []
            if not (isinstance(tb["orientations"], list) and tb["orientations"] and all(o in valides_or for o in tb["orientations"])):
                err.append("« tablettes.orientations » : liste non vide parmi « portrait » et « paysage ».")
                tb["orientations"] = []
            if not _est_nombre(tb["echelle"]) or tb["echelle"] <= 0:
                err.append("« tablettes.echelle » : nombre > 0 attendu.")
            for ori in tb["orientations"]:
                cv = tb["canvas"].get(ori)
                if not (isinstance(cv, list) and len(cv) == 2 and all(isinstance(x, int) for x in cv)):
                    err.append(f"« tablettes.canvas.{ori} » : [largeur, hauteur] en pixels entiers attendu.")
                else:
                    w, h = cv
                    exact = (w * 16 == h * 9) if ori == "portrait" else (w * 9 == h * 16)
                    if not exact:
                        err.append(f"« tablettes.canvas.{ori} » : {w}x{h} n'est pas du {'9:16' if ori == 'portrait' else '16:9'}.")
                    if not all(rt["cote_min"] <= x <= rt["cote_max"] for x in cv):
                        err.append(f"« tablettes.canvas.{ori} » : côtés de {rt['cote_min']} à {rt['cote_max']} px attendus ({w}x{h}).")
                for ap in tb["appareils"]:
                    vp = tb["formats"].get(ap, {}).get(ori)
                    if not (isinstance(vp, list) and len(vp) == 2 and all(isinstance(x, int) and x > 0 for x in vp)):
                        err.append(f"« tablettes.formats.{ap}.{ori} » : viewport [largeur, hauteur] en pixels CSS entiers attendu.")
            caps_t = tb["captures"]
            if not isinstance(caps_t, list):
                err.append("« tablettes.captures » : liste obligatoire.")
                caps_t = []
            elif not (rt["nombre_min"] <= len(caps_t) <= rt["nombre_max"]):
                err.append(f"« tablettes.captures » : {rt['nombre_min']} à {rt['nombre_max']} captures par jeu quand les tablettes "
                           f"sont demandées ({len(caps_t)} fournies).")
            if not isinstance(tb["source"], dict) or not isinstance(tb["barre_etat"], dict):
                err.append("« tablettes.source » et « tablettes.barre_etat » : objets attendus.")
                tb["source"], tb["barre_etat"] = {}, {}
            src_t = fusion(src0, {k: v for k, v in tb["source"].items() if k != "masquer"})
            src_t["masquer"] = list(src0.get("masquer", [])) + [m for m in tb["source"].get("masquer", []) if isinstance(m, str)]
            be_t = fusion(be, tb["barre_etat"])
            ids_t: set[str] = set()
            for i, cap in enumerate(caps_t):
                etiq = f"tablettes.captures[{i}]"
                if not isinstance(cap, dict) or not isinstance(cap.get("id"), str) or not RE_ID.match(cap["id"]):
                    err.append(f"« {etiq}.id » : identifiant (lettres, chiffres, tiret) obligatoire.")
                    continue
                etiq = f"tablettes.captures[{cap['id']}]"
                if cap["id"] in ids_t:
                    err.append(f"« {etiq} » : identifiant en double.")
                ids_t.add(cap["id"])
                cap.setdefault("fichier", f"{cap['id']}.png")
                if not (isinstance(cap["fichier"], str) and cap["fichier"].lower().endswith(".png") and "/" not in cap["fichier"] and "\\" not in cap["fichier"]):
                    err.append(f"« {etiq}.fichier » : nom de fichier .png sans dossier.")
                valider_fond(etiq, cap.get("fond"))
                valider_textes(etiq, cap.get("textes"), ("titre", "alt"))
                for ap in tb["appareils"]:
                    for ori in tb["orientations"]:
                        e = {"id": f"tablette-{ap}-{ori}/{cap['id']}", "genre": "tablette", "appareil": ap, "orientation": ori,
                             "capture": cap["id"], "fichier": cap["fichier"], "dossier": f"tablettes/{ap}-pouces-{ori}",
                             "cle": f"tablette-{ap}-{ori}__{cap['id']}", "puces": [],
                             "canvas": tb["canvas"].get(ori), "textes": cap.get("textes"),
                             "fond": fusion(th["fond"], cap.get("fond") or {}),
                             "source": copy.deepcopy(cap.get("source") or {}), "barre_etat": copy.deepcopy(cap.get("barre_etat") or {})}
                        resoudre_source_barre(e, etiq, src_t, be_t)
                        vp = tb["formats"].get(ap, {}).get(ori)
                        if isinstance(vp, list):
                            e["source"]["viewport"] = list(vp)
                        e["source"]["echelle"] = tb["echelle"]
                        cfg["_tablettes"].append(e)

    # icône 512 x 512 (facultatif ; obligatoire pour --icone)
    ic_brut = brut.get("icone")
    cfg["icone"] = None
    if ic_brut is not None:
        if not isinstance(ic_brut, dict):
            err.append("« icone » : objet attendu.")
        else:
            ic = fusion(DEFAUT_ICONE, ic_brut)
            cfg["icone"] = ic
            if not isinstance(ic["source"], str):
                err.append("« icone.source » : chemin du SVG ou du PNG carré obligatoire.")
            elif not (base / ic["source"]).resolve().is_file():
                err.append(f"« icone.source » : fichier introuvable : {(base / ic['source']).resolve()}")
            elif (base / ic["source"]).suffix.lower() not in (".svg", ".png"):
                err.append("« icone.source » : SVG ou PNG attendu.")
            if not (isinstance(ic["fichier"], str) and ic["fichier"].lower().endswith(".png") and "/" not in ic["fichier"] and "\\" not in ic["fichier"]):
                err.append("« icone.fichier » : nom de fichier .png sans dossier.")
            couleur_ok("icone.fond", ic["fond"])
            for k in ("zone_securite_pct", "contraste_min", "ecart_motif", "transparence_partielle_max_pct"):
                if not _est_nombre(ic[k]) or ic[k] <= 0:
                    err.append(f"« icone.{k} » : nombre > 0 attendu.")

    # logo et police
    if th.get("logo") and not (base / th["logo"]).resolve().is_file():
        err.append(f"« theme.logo » : fichier introuvable : {(base / th['logo']).resolve()}")
    if th["police"].get("fichier") and not (base / th["police"]["fichier"]).resolve().is_file():
        err.append(f"« theme.police.fichier » : fichier introuvable : {(base / th['police']['fichier']).resolve()}")
    if th["police"].get("fichiers") is not None:
        pf = th["police"]["fichiers"]
        if not (isinstance(pf, list) and pf and all(isinstance(x, dict) and isinstance(x.get("fichier"), str) for x in pf)):
            err.append("« theme.police.fichiers » : liste de {fichier, graisse} attendue.")
        else:
            for x in pf:
                if not (base / x["fichier"]).resolve().is_file():
                    err.append(f"« theme.police.fichiers » : fichier introuvable : {(base / x['fichier']).resolve()}")
    for f, _ in (polices_declarees(cfg, base) if not err else []):
        if not f.is_file():
            err.append(f"« theme.police » : fichier de police introuvable : {f} (copier le dossier fonts/ du kit)")
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


FONTS_KIT = KIT / "fonts"
INTER_EMBARQUEE = [("Inter-Regular.woff2", "400"), ("Inter-SemiBold.woff2", "600"),
                   ("Inter-Bold.woff2", "700"), ("Inter-ExtraBold.woff2", "800")]
FORMATS_POLICE = {".woff2": "woff2", ".woff": "woff", ".ttf": "truetype", ".otf": "opentype"}


def polices_declarees(cfg: dict, base: Path) -> list[tuple[Path, str]]:
    """Fichiers de police chargés dans les visuels, avec leur graisse : ceux de la configuration
    (fichiers, fichier), sinon Inter embarquée dans fonts/ quand la famille est « Inter », sinon aucun
    (police système : le rendu dépend alors de la machine)."""
    p = cfg["theme"]["police"]
    if p.get("fichiers"):
        return [((base / x["fichier"]).resolve(), str(x.get("graisse", "400"))) for x in p["fichiers"]]
    if p.get("fichier"):
        return [((base / p["fichier"]).resolve(), str(p["graisses"]))]
    if str(p.get("famille", "")).strip().lower() == "inter":
        return [(FONTS_KIT / nom, g) for nom, g in INTER_EMBARQUEE]
    return []


def css_police(cfg: dict, base: Path, depuis: Path | None = None) -> tuple[str, str]:
    """(règles @font-face, pile de polices CSS). `depuis` : dossier de la page qui les utilise, pour des
    URL relatives (kit copiable) ; sans lui, URL absolues (page distante ou injectée)."""
    p = cfg["theme"]["police"]
    face = ""
    for f, graisse in polices_declarees(cfg, base):
        url = Path(os.path.relpath(f, depuis)).as_posix() if depuis is not None and _meme_lecteur(f, depuis) else uri(f)
        face += (f"@font-face{{font-family:'{p['famille']}';font-weight:{graisse};font-style:normal;font-display:block;"
                 f"src:url('{url}') format('{FORMATS_POLICE.get(f.suffix.lower(), 'woff2')}');}}")
    return face, f"'{p['famille']}',{p['repli']}"


def _meme_lecteur(a: Path, b: Path) -> bool:
    return os.path.splitdrive(str(a))[0].lower() == os.path.splitdrive(str(b.resolve()))[0].lower()


JS_POLICES = """() => [...document.fonts].map(f => [f.family.replace(/["']/g, ''), f.weight, f.status])"""

JS_ZONE = """([selBarre, pct, selHeure, selEcran]) => {
  const vh = window.innerHeight, vw = window.innerWidth;
  let top = 0, haut = vh;
  if (selEcran) { const el = document.querySelector(selEcran); if (el) { const r = el.getBoundingClientRect(); top = r.top; haut = r.height; } }
  let zone = haut * pct / 100, erreur = null;
  if (selBarre) {
    const el = document.querySelector(selBarre);
    if (el) zone = Math.max(zone, el.getBoundingClientRect().bottom - top); else erreur = 'sélecteur de barre introuvable : ' + selBarre;
  }
  const heureEl = selHeure ? document.querySelector(selHeure) : null;
  const w = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
  const textes = [];
  while (w.nextNode()) {
    const n = w.currentNode, t = n.nodeValue.trim(), p = n.parentElement;
    if (!t || !p || ['SCRIPT', 'STYLE', 'NOSCRIPT', 'TITLE'].includes(p.tagName)) continue;
    const cs = getComputedStyle(p);
    if (cs.display === 'none' || cs.visibility === 'hidden' || parseFloat(cs.opacity) === 0) continue;
    const rg = document.createRange(); rg.selectNodeContents(n);
    for (const r of rg.getClientRects()) {
      if (r.width < 1 || r.height < 1 || r.right <= 0 || r.left >= vw) continue;
      const h0 = r.top - top, h1 = r.bottom - top;
      if (h1 <= 0 || h0 >= zone) continue;
      textes.push({texte: t, coupe: h0 < zone && h1 > zone + 0.5, heure: !!(heureEl && heureEl.contains(p))});
      break;
    }
  }
  return {zone_css: zone, hauteur_css: haut, pct_effectif: 100 * zone / haut, textes, erreur};
}"""


def captures_a_traiter(cfg: dict, ids: list[str] | None = None, tablettes: bool = True) -> list[dict]:
    """Captures téléphone (filtrées par identifiant) puis captures tablettes (toutes, si `tablettes` est
    demandé ou si aucun filtre ; identifiant de filtre : « tablettes »)."""
    sortie = [e for e in cfg["captures"] if not ids or e["id"] in ids]
    if tablettes and (not ids or "tablettes" in ids):
        sortie += cfg.get("_tablettes", [])
    return sortie


def capturer_ecrans(cfg: dict, base: Path, cache: Path, ids: list[str] | None) -> dict[str, dict]:
    """Capture les écrans de source « html » (téléphone et tablettes). Retourne, par identifiant, l'heure lue
    et l'analyse de la zone de la barre d'état de la page (textes présents sous la barre d'état)."""
    cibles = [e for e in captures_a_traiter(cfg, ids) if e["source"]["type"] == "html"]
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
            bar = e["barre_etat"]
            info["zone"] = pg.evaluate(JS_ZONE, [bar.get("selecteur"), bar["hauteur_pct"], s.get("selecteur_heure"), s.get("selecteur_ecran")])
            infos[e["id"]] = info
            dest = cache / "brut" / f"{e['cle']}.png"
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
    return cache / "brut" / f"{e['cle']}.png"


# --------------------------------------------------------------------------- composition HTML/CSS
def fond_css(f: dict, C: Couleurs) -> str:
    return f"linear-gradient({f['angle']}deg,{C(f['de'])} 0%,{C(f['vers'])} 100%)"


def svg_icone(cfg: dict, nom: str, taille: int) -> str:
    corps = {**ICONES, **cfg["theme"].get("icones", {})}[nom]
    return f'<svg viewBox="0 0 24 24" width="{taille}" height="{taille}" aria-hidden="true">{corps}</svg>'


def css_base(cfg: dict, C: Couleurs, base: Path) -> str:
    face, pile = css_police(cfg, base, cfg.get("_compo_dir"))
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


def html_barre(cfg: dict, e: dict, brut: Path, ecran_h: int, zone: dict | None = None) -> str:
    """Barre d'état propre superposée à l'image (par-dessus toute barre existante) : heure figée, signal,
    Wi-Fi, batterie pleine, rien d'autre. Hauteur : celle de la barre de la page quand elle a été mesurée
    (`zone`, source HTML), sinon `hauteur_pct`. Couleurs d'après le fond de l'écran sous la barre."""
    b = e["barre_etat"]
    if b["mode"] != "recouvrir":
        return ""
    pct = zone["pct_effectif"] if zone and zone.get("pct_effectif") else b["hauteur_pct"]
    h = ecran_h * pct / 100
    fond = b["couleur"] if b["couleur"] != "auto" else couleur_barre(brut, pct)
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
  const rc = el => { const r = el.getBoundingClientRect(); return [r.left, r.top, r.right, r.bottom]; };
  return {heure: b.querySelector('.h').textContent.trim(), svgs: b.querySelectorAll('svg').length,
          remplissage: plein ? plein.getAttribute('width') : null,
          texte_barre: b.textContent.trim(), fond: getComputedStyle(b).backgroundColor,
          bande: {rect: rc(b), libres: [rc(b.querySelector('.h')), rc(b.querySelector('.ic'))]}};
}"""


def halos_capture(e: dict, C: Couleurs, W: int, H: int) -> str:
    de, vers = C(e["fond"]["de"]), C(e["fond"]["vers"])
    if lum_rel(hex_rgb(de)) > 0.6:      # fond clair : halos clairs teintés (jamais de gris sale)
        clair, sombre, a1, a2 = melange(de, "#FFFFFF", 0.7), melange(vers, "#8299E1", 0.35), "99", "55"
    else:
        clair, sombre, a1, a2 = melange(de, "#FFFFFF", 0.45), melange(vers, "#000000", 0.35), "55", "88"
    return (f'<div class="halo" style="left:-260px;top:{round(H*0.583)}px;width:700px;height:700px;background:radial-gradient(circle,{clair}{a1},transparent 68%)"></div>'
            f'<div class="halo" style="right:-300px;top:-120px;width:760px;height:760px;background:radial-gradient(circle,{sombre}{a2},transparent 68%)"></div>')


def html_capture(cfg: dict, C: Couleurs, base: Path, e: dict, texte: dict, brut: Path, zone: dict | None = None) -> str:
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
    <img class="ecran-img" src="{uri(brut)}" alt="">{html_barre(cfg, e, brut, ecran_h, zone)}</div>
  {trou}
</div>
{puces}
</body></html>"""


def html_tablette(cfg: dict, C: Couleurs, base: Path, e: dict, texte: dict, brut: Path, zone: dict | None = None) -> str:
    """Capture de tablette : titre court, écran dans un cadre neutre, portrait 9:16 ou paysage 16:9."""
    W, H = e["canvas"]
    iw, ih = Image.open(brut).size
    a = iw / ih
    taille = round(min(W, H) * (0.07 if W < H else 0.062))
    haut = round(H * 0.05)
    lignes = 2 if W < H else 1
    zone_titre = haut + round(taille * 1.14 * lignes) + round(H * 0.02)
    bis, rayon_cadre, rayon_ecran = 14, 44, 30
    dispo_h = H - zone_titre - round(H * 0.03) - 2 * bis
    dispo_w = W - 2 * round(W * 0.07) - 2 * bis
    ecran_w = int(min(dispo_w, dispo_h * a))
    ecran_h = round(ecran_w / a)
    cadre_w, cadre_h = ecran_w + 2 * bis, ecran_h + 2 * bis
    return f"""<!doctype html><html lang="{html.escape(cfg['_langue'])}"><head><meta charset="utf-8"><style>{css_base(cfg, C, base)}
body{{width:{W}px;height:{H}px;background:{fond_css(e['fond'], C)}}}
.titre{{position:absolute;left:{round(W*0.06)}px;right:{round(W*0.06)}px;top:{haut}px;text-align:center;font-weight:800;font-size:{taille}px;line-height:1.14;letter-spacing:-.01em;text-wrap:balance}}
</style></head><body>
{halos_capture(e, C, W, H)}
<h1 class="titre" data-txt>{html.escape(texte['titre'])}</h1>
<div class="cadre" style="left:{(W - cadre_w) // 2}px;top:{zone_titre}px;width:{cadre_w}px;height:{cadre_h}px;border-radius:{rayon_cadre}px">
  <div class="ecran" style="left:{bis}px;top:{bis}px;width:{ecran_w}px;height:{ecran_h}px;border-radius:{rayon_ecran}px">
    <img class="ecran-img" src="{uri(brut)}" alt="">{html_barre(cfg, e, brut, ecran_h, zone)}</div>
</div>
</body></html>"""


def html_feature(cfg: dict, C: Couleurs, base: Path, caps: dict[str, Path], texte: dict) -> str:
    fg = cfg["feature_graphic"]
    ml = cfg["mise_en_page"]
    W, H = 1024, 500
    if fg.get("vitrine"):
        return html_vitrine(cfg, C, base, texte, W, H)
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
    accroche = texte.get("accroche")
    accroche_html = f'<p class="accroche" data-txt>{html.escape(accroche)}</p>' if accroche else ""
    if fg.get("disposition") == "logo-gauche":
        # Logo et slogan à gauche (centre de gravité proche du milieu de l'image), une capture web et une capture mobile
        # réelles à droite, en bordure. Les bords peuvent être rognés par Google : rien d'essentiel à moins de 30 px.
        web = fg.get("ecran_web")
        web_html = ""
        if web:
            wp = (base / web).resolve()
            ww = 440
            iw, ih = Image.open(wp).size
            wh = round(ww * ih / iw)
            web_html = (f'<div class="cadre" style="left:{W - ww - 8 + 40}px;top:44px;width:{ww + 16}px;height:{wh + 16}px;border-radius:22px;'
                        f'box-shadow:0 22px 50px rgba(2,19,74,.28)"><div class="ecran" style="left:8px;top:8px;width:{ww}px;height:{wh}px;border-radius:15px">'
                        f'<img class="ecran-img" src="{uri(wp)}" alt=""></div></div>')
        brut = caps[fg["ecrans"]["droite"]]
        pw, b = 168, 7
        iw, ih = Image.open(brut).size
        sh = round(pw * ih / iw)
        mob = (f'<div class="cadre" style="left:{W - pw - 2*b - 60}px;top:{H - 270}px;width:{pw + 2*b}px;height:{sh + 2*b}px;border-radius:34px;'
               f'box-shadow:0 22px 50px rgba(2,19,74,.34)"><div class="ecran" style="left:{b}px;top:{b}px;width:{pw}px;height:{sh}px;border-radius:27px">'
               f'<img class="ecran-img" src="{uri(brut)}" alt=""></div></div>')
        return f"""<!doctype html><html lang="{html.escape(cfg['_langue'])}"><head><meta charset="utf-8"><style>{css_base(cfg, C, base)}
body{{width:{W}px;height:{H}px;background:{fond_css(fg['fond'], C)}}}
.centre{{position:absolute;left:56px;top:0;height:100%;width:430px;display:flex;flex-direction:column;align-items:flex-start;justify-content:center;gap:22px}}
.centre img{{width:400px;display:block}}
.slogan{{font-weight:700;font-size:27px;line-height:1.25;color:{C(fg.get('couleur_slogan', cfg['theme']['couleurs']['texte']))}}}
.accroche{{font-weight:600;font-size:17px;letter-spacing:.01em;color:{C(fg.get('couleur_accroche', cfg['theme']['couleurs']['texte']))}}}
</style></head><body>
{halos}
{web_html}
{mob}
<div class="centre">{en_logo}<p class="slogan" data-txt>{html.escape(texte['slogan'])}</p>{accroche_html}</div>
</body></html>"""
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
<div class="centre">{en_logo}<p class="slogan" data-txt>{html.escape(texte['slogan'])}</p>{accroche_html}</div>
</body></html>"""


def html_vitrine(cfg: dict, C: Couleurs, base: Path, texte: dict, W: int, H: int) -> str:
    """Composition « vitrine » (disposition logo-gauche avec bloc `vitrine`) : fond blanc, logo, slogan, description, puces de
    fonctionnalités, domaine, fenêtre de navigateur (capture web réelle) et téléphone (capture mobile réelle), ruban arc-en-ciel.
    Dessinée sur une grille de 1280 x 640 puis mise à l'échelle largeur (s = W / 1280) : l'image de présentation Google Play
    (1024 x 500) et l'aperçu social (1280 x 640) ont la même composition. Toutes les tailles sont des pixels réels (mesurables)."""
    fg = cfg["feature_graphic"]
    v = fg["vitrine"]
    s = W / 1280
    k = (H / s) / 640            # facteur vertical pour les positions (le contenu n'est pas déformé)

    def u(x: float) -> str:
        return f"{x * s:.2f}px"

    def uy(y: float) -> str:
        return f"{y * s * k:.2f}px"

    logo = cfg["theme"].get("logo")
    en_logo = f'<img class="logo" src="{uri(base / logo)}" alt="">' if logo else ""
    co = cfg["theme"]["couleurs"]
    web = (base / fg["ecran_web"]).resolve()
    mob = (base / v["ecran_mobile"]).resolve()
    ww = 524
    iw, ih = Image.open(web).size
    wh = round(ww * ih / iw)
    bar = 30
    pw, b = 174, 7
    miw, mih = Image.open(mob).size
    sh = round(pw * mih / miw)
    ph = sh + 2 * b
    ruban = ", ".join(C(c) for c in v["ruban"])
    ink, encre2 = C(v.get("couleur_slogan", "blue-900")), C(v.get("couleur_texte", "neutral-600"))
    puces_html = []
    for i, label in enumerate(texte["fonctions"]):
        d = v["puces"][i]
        svg = (base / d["icone"]).read_text(encoding="utf-8")
        svg = re.sub(r'\s(width|height)="[^"]*"', "", svg, count=2).replace("<svg", '<svg aria-hidden="true"', 1)
        ton = "alerte" if d.get("ton") == "alerte" else "normal"
        puces_html.append(f'<span class="puce-f {ton}">{svg}<span data-txt data-chip>{html.escape(label)}</span></span>')
    rangees = v.get("rangees", [2, 3])
    lignes, i = [], 0
    for n in rangees:
        lignes.append('<div class="rang">' + "".join(puces_html[i:i + n]) + "</div>")
        i += n
    top_nav = 83
    return f"""<!doctype html><html lang="{html.escape(cfg['_langue'])}"><head><meta charset="utf-8"><style>{css_base(cfg, C, base)}
body{{width:{W}px;height:{H}px;background:#FFFFFF}}
.lueur{{position:absolute;left:{u(560)};top:{uy(40)};width:{u(760)};height:{u(560)};border-radius:50%;
  background:radial-gradient(closest-side,{C(v.get('lueur', 'blue-100'))}AA,transparent)}}
.gauche{{position:absolute;left:{u(64)};top:{uy(88)};width:{u(560)};display:flex;flex-direction:column;align-items:flex-start}}
.logo{{width:{u(386)};display:block;margin-left:{u(8)}}}
.slogan{{margin-top:{u(34)};font-weight:800;font-size:{u(38)};line-height:1.2;color:{ink};letter-spacing:-.005em}}
.desc{{margin-top:{u(20)};font-weight:500;font-size:{u(21)};line-height:1.45;color:{encre2};max-width:{u(500)}}}
.chips{{margin-top:{u(18)};display:flex;flex-direction:column;gap:{u(10)}}}
.rang{{display:flex;gap:{u(10)}}}
.puce-f{{display:flex;align-items:center;gap:{u(8)};height:{u(38)};padding:0 {u(16)} 0 {u(13)};border-radius:999px;white-space:nowrap;
  font-weight:600;font-size:{u(17)};border:{max(1, round(s))}px solid {C(v.get('puce_bordure', 'blue-200'))};background:{C(v.get('puce_fond', 'blue-50'))};
  color:{C(v.get('puce_texte', 'blue-800'))}}}
.puce-f svg{{width:{u(19)};height:{u(19)};flex:none;fill:currentColor;color:{C(v.get('puce_icone', 'blue-500'))}}}
.puce-f.alerte{{border-color:{C(v.get('alerte_bordure', 'red-200'))};background:{C(v.get('alerte_fond', 'red-50'))};color:{C(v.get('alerte_texte', 'red-700'))}}}
.puce-f.alerte svg{{color:{C(v.get('alerte_icone', 'red-500'))}}}
.domaine{{position:absolute;left:{u(64)};bottom:{u(46)};font-weight:700;font-size:{u(20)};color:{C(v.get('couleur_domaine', 'blue-500'))}}}
.fenetre{{position:absolute;left:{u(640)};top:{uy(top_nav)};width:{u(ww + 2)};height:{u(wh + bar + 2)};border-radius:{u(14)};overflow:hidden;
  background:#fff;border:{u(1)} solid {C('blue-100')};box-shadow:0 {u(18)} {u(46)} rgba(2,19,74,.16)}}
.fenetre .tete{{height:{u(bar)};background:{C('blue-50')};display:flex;align-items:center;gap:{u(7)};padding:0 {u(12)}}}
.fenetre .pt{{width:{u(10)};height:{u(10)};border-radius:50%;background:{C('blue-200')}}}
.fenetre .url{{margin-left:{u(12)};flex:1;height:{u(16)};border-radius:999px;background:#fff;border:{u(1)} solid {C('blue-100')}}}
.fenetre img{{display:block;width:{u(ww)};height:{u(wh)}}}
.tel{{position:absolute;left:{u(1000)};top:{uy(182)};width:{u(pw + 2 * b)};height:{u(ph)};border-radius:{u(30)};background:{C('blue-900')};
  box-shadow:0 {u(20)} {u(46)} rgba(2,19,74,.30)}}
.tel .ec{{position:absolute;left:{u(b)};top:{u(b)};width:{u(pw)};height:{u(sh)};border-radius:{u(23)};overflow:hidden;background:#fff}}
.tel img{{display:block;width:100%;height:100%;object-fit:cover;object-position:top}}
.ruban{{position:absolute;left:0;right:0;bottom:0;height:{u(8)};background:linear-gradient(90deg,{ruban})}}
</style></head><body>
<div class="lueur"></div>
<div class="fenetre"><div class="tete"><i class="pt"></i><i class="pt"></i><i class="pt"></i><i class="url"></i></div><img src="{uri(web)}" alt=""></div>
<div class="tel"><div class="ec"><img src="{uri(mob)}" alt=""></div></div>
<div class="gauche">{en_logo}<p class="slogan" data-txt>{html.escape(texte['slogan']).replace("&#x27;", "’")}</p>
<p class="desc" data-txt>{html.escape(texte['description'])}</p><div class="chips">{''.join(lignes)}</div></div>
<p class="domaine" data-txt>{html.escape(v['domaine'])}</p>
<div class="ruban"></div>
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


def composer(cfg: dict, C: Couleurs, base: Path, cache: Path, sortie: Path, ids, infos_pages: dict | None = None) -> tuple[dict, dict]:
    """Compose et exporte les visuels. Retourne (mesures de texte, infos de barre d'état composées)."""
    from playwright.sync_api import sync_playwright

    langue = cfg["_langue"]
    tmp = cache / "compo"
    tmp.mkdir(parents=True, exist_ok=True)
    cfg["_compo_dir"] = tmp.resolve()       # URL de police relatives à ce dossier (kit copiable)
    infos_pages = infos_pages or {}
    traitees = captures_a_traiter(cfg, ids)
    caps = {e["id"]: source_image(e, base, cache) for e in cfg["captures"]}
    for e in traitees:
        caps[e["id"]] = source_image(e, base, cache)
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
            sortie.mkdir(parents=True, exist_ok=True)
            taches.append(("feature-graphic", (1024, 500), html_feature(cfg, C, base, caps, fg["textes"][langue]), sortie / fg["fichier"], None))
            ss = (fg.get("vitrine") or {}).get("sortie_sociale")
            if ss:   # même composition, autre taille (aperçu social GitHub) : une seule configuration
                taches.append(("apercu-social", (ss["largeur"], ss["hauteur"]),
                               html_vitrine(cfg, C, base, fg["textes"][langue], ss["largeur"], ss["hauteur"]), (base / ss["fichier"]).resolve(), None))
        for e in traitees:
            zone = (infos_pages.get(e["id"]) or {}).get("zone")
            fabrique = html_tablette if e["genre"] == "tablette" else html_capture
            taches.append((e["id"], tuple(e["canvas"]), fabrique(cfg, C, base, e, e["textes"][langue], caps[e["id"]], zone),
                           sortie / e["dossier"] / e["fichier"], e))
        for ident, (w, h), code, dest, e in taches:
            pg = nav.new_page(viewport={"width": w, "height": h}, device_scale_factor=1)
            f = tmp / (ident.replace("/", "__") + ".html")
            f.write_text(code, encoding="utf-8")
            pg.goto(uri(f))
            pg.evaluate("document.fonts.ready")
            pg.wait_for_timeout(250)
            mesures[ident] = {"textes": pg.evaluate(MESURES_JS), "polices": pg.evaluate(JS_POLICES)}
            if e is not None and e["barre_etat"]["mode"] == "recouvrir":
                info = pg.evaluate(JS_BARRE_COMPOSEE) or {}
                info["mode"] = "recouvrir"
                info["rayon"] = cfg["mise_en_page"]["rayon_ecran"] if e["genre"] == "telephone" else 30
                barres[ident] = info
            brut_png = tmp / (ident.replace("/", "__") + ".rgba.png")
            pg.screenshot(path=str(brut_png))
            pg.evaluate("document.body.classList.add('notext')")
            pg.wait_for_timeout(100)
            sans_txt = tmp / (ident.replace("/", "__") + ".notext.png")
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
    if fg.get("accroche") is not None:
        out.append(("feature-graphic/accroche", fg["accroche"]))
    if fg.get("description") is not None:
        out.append(("feature-graphic/description", fg["description"]))
    out += [(f"feature-graphic/fonction:{i + 1}", x) for i, x in enumerate(fg.get("fonctions") or [])]
    for e in cfg["captures"]:
        t = e["textes"][langue]
        out.append((f"{e['id']}/titre", t["titre"]))
        out.append((f"{e['id']}/alt", t["alt"]))
        out += [(f"{e['id']}/puce:{k}", v) for k, v in t.get("puces", {}).items()]
    vus: set[str] = set()
    for e in cfg.get("_tablettes", []):         # un jeu de textes par capture de tablette (partagé par les formats)
        if e["capture"] in vus:
            continue
        vus.add(e["capture"])
        t = e["textes"][langue]
        out.append((f"tablettes/{e['capture']}/titre", t["titre"]))
        out.append((f"tablettes/{e['capture']}/alt", t["alt"]))
    return out


def langues_manquantes(cfg: dict, langue: str) -> list[str]:
    manq = []
    if langue not in cfg["feature_graphic"]["textes"]:
        manq.append("feature_graphic")
    manq += [e["id"] for e in cfg["captures"] if langue not in e["textes"]]
    manq += sorted({f"tablettes/{e['capture']}" for e in cfg.get("_tablettes", []) if langue not in e["textes"]})
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


def textes_zone(zone: dict, sans_heure: bool) -> list[str]:
    """Textes de la page présents dans la zone de la barre d'état (hors élément d'heure si `sans_heure`)."""
    return [t["texte"] for t in (zone or {}).get("textes", []) if not (sans_heure and t.get("heure"))]


def bande_propre(im: Image.Image, info: dict, tolerance: int = 8) -> tuple[bool, str]:
    """Détection de la bande : dans la zone de la barre d'état de la capture finale, hors heure et icônes
    (et hors coins arrondis de l'écran), tous les pixels ont la couleur de la barre propre."""
    b = info["bande"]
    x0, y0, x1, y1 = (int(round(v)) for v in b["rect"])
    r = int(info.get("rayon", 0))
    fond = parse_rgb(info["fond"])
    rgb = im.convert("RGB")
    libres = [(l - 6, t - 6, rr + 6, bb + 6) for l, t, rr, bb in b["libres"]]
    px = rgb.load()
    pire, n = 0, 0
    for y in range(max(0, y0), min(rgb.size[1], y1)):
        for x in range(max(0, x0 + r), min(rgb.size[0], x1 - r)):
            if any(l <= x < rr and t <= y < bb for l, t, rr, bb in libres):
                continue
            n += 1
            d = max(abs(px[x, y][i] - fond[i]) for i in range(3))
            pire = max(pire, d)
    return pire <= tolerance, f"{n} pixels contrôlés, écart maximal à la couleur de la barre {pire} (tolérance {tolerance})"


def controler_barre(res_ok, ident: str, e: dict, i: dict | None, im: Image.Image) -> None:
    """Contrôles de la barre d'état d'une capture (téléphone ou tablette), selon son mode."""
    b = e["barre_etat"]
    mode = b["mode"]
    zone = (i or {}).get("zone")
    if mode == "aucune":
        if zone is None:
            res_ok(ident, "barre d'état propre", True, "sans barre d'état dans la source (mode « aucune »)")
        else:
            extra = textes_zone(zone, False)
            res_ok(ident, "bande supérieure de l'écran : aucun texte (mode « aucune »)", not extra,
                   "aucun texte" if not extra else "texte dans la zone : " + " | ".join(extra)[:80])
        return
    if i is None:
        return
    if mode == "recouvrir":
        bon = (i.get("heure") == b["heure"] and i.get("svgs") == 3 and i.get("remplissage") == "19"
               and i.get("texte_barre", b["heure"]) == b["heure"])
        res_ok(ident, f"barre d'état propre ({b['heure']}, signal, Wi-Fi, batterie pleine, aucun opérateur ni notification)", bon,
               json.dumps({k: v for k, v in i.items() if k not in ("zone", "bande")}, ensure_ascii=False))
        if zone is not None:
            coupes = [t["texte"] for t in zone.get("textes", []) if t.get("coupe")]
            couverts = textes_zone(zone, False)
            if coupes or zone.get("erreur"):
                det = zone.get("erreur") or ("texte coupé par le bord de la barre : " + " | ".join(coupes)[:70])
            else:
                det = f"{len(couverts)} texte(s) recouvert(s) : " + (" | ".join(couverts)[:70] or "aucun")
            res_ok(ident, "bande supérieure de l'écran : texte de la page entièrement recouvert par la barre propre",
                   not coupes and not zone.get("erreur"), det)
        if "bande" in i:
            bon2, det = bande_propre(im, i)
            res_ok(ident, "bande supérieure de l'écran : seule la barre propre est visible (détection de la bande)", bon2, det)
    else:
        res_ok(ident, f"barre d'état : heure figée à {b['heure']} (le reste de la barre vient de la source)", i.get("heure") == b["heure"],
               json.dumps({k: v for k, v in i.items() if k != "zone"}, ensure_ascii=False))
        if zone is not None:
            extra = textes_zone(zone, True)
            res_ok(ident, f"bande supérieure de l'écran : aucun texte autre que l'heure ({b['heure']})", not extra and not zone.get("erreur"),
                   zone.get("erreur") or ("aucun autre texte" if not extra else "texte en trop : " + " | ".join(extra)[:80]))


def controler(cfg: dict, C: Couleurs, base: Path, langue: str, sortie: Path, mesures: dict, infos_barre: dict, ids=None,
              info_icone: dict | None = None) -> list[dict]:
    """Tous les contrôles de conformité. Retourne la liste {visuel, controle, ok, detail}."""
    c = cfg["contraintes"]
    vifs = c["couleurs_vives"]
    res: list[dict] = []

    def ok(visuel, controle, passe, detail):
        res.append({"visuel": visuel, "controle": controle, "ok": bool(passe), "detail": detail})

    rt = valeurs_regle(cfg["_regles"], "captures_tablette") if cfg.get("_regles") else {}
    cibles = [("feature-graphic", sortie / cfg["feature_graphic"]["fichier"], c["feature_graphic"], None)]
    ss = (cfg["feature_graphic"].get("vitrine") or {}).get("sortie_sociale")
    if ss:
        cibles.append(("apercu-social", (base / ss["fichier"]).resolve(),
                       {"largeur": ss["largeur"], "hauteur": ss["hauteur"], "poids_max_mo": ss.get("poids_max_mo", 1)}, None))
    cibles += [(e["id"], sortie / e["dossier"] / e["fichier"], c["captures"], e) for e in cfg["captures"]]
    cibles += [(e["id"], sortie / e["dossier"] / e["fichier"],
                {"largeur": e["canvas"][0], "hauteur": e["canvas"][1], "poids_max_mo": rt["poids_max_mo"]}, e)
               for e in cfg.get("_tablettes", []) if not ids or "tablettes" in ids]

    ok("ensemble", "nombre de captures", len(cfg["captures"]) == c["captures"]["nombre"],
       f"{len(cfg['captures'])} (attendu {c['captures']['nombre']})")
    if cfg.get("_tablettes") and (not ids or "tablettes" in ids):
        jeux: dict[str, int] = {}
        for e in cfg["_tablettes"]:
            jeux.setdefault(e["id"].split("/")[0], 0)
            jeux[e["id"].split("/")[0]] += int((sortie / e["dossier"] / e["fichier"]).exists())
        for jeu, n in jeux.items():
            ok(jeu, f"nombre de captures de tablette ({rt['nombre_min']} à {rt['nombre_max']})", rt["nombre_min"] <= n <= rt["nombre_max"], f"{n}")
    polices = [(ident, m.get("polices")) for ident, m in mesures.items() if isinstance(m, dict) and "polices" in m
               and (not ids or ident in ids or ident.startswith("tablette-") and "tablettes" in ids)]
    if polices:
        # une graisse déclarée mais non utilisée reste « unloaded » : seul « error », ou aucune face chargée, est un échec
        manquantes = [ident for ident, p in polices if p and (any(f[2] == "error" for f in p) or not any(f[2] == "loaded" for f in p))]
        sans_face = [ident for ident, p in polices if not p]
        ok("ensemble", "police chargée depuis un fichier embarqué (rendu indépendant des polices système)", not manquantes and not sans_face,
           f"{len(polices) - len(manquantes) - len(sans_face)}/{len(polices)} visuels ; "
           + ("police système utilisée dans : " + ", ".join(sans_face[:3]) if sans_face else "")
           + ("police non chargée dans : " + ", ".join(manquantes[:3]) if manquantes else ""))
    for ident, chemin, regle, e_cap in cibles:
        if ids and ident not in ids and not (ident.startswith("tablette-") and "tablettes" in ids) \
                and not (ident == "apercu-social" and "feature-graphic" in ids):
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
        tablette = e_cap is not None and e_cap["genre"] == "tablette"
        if tablette:
            paysage = e_cap["orientation"] == "paysage"
            ok(ident, "ratio 16:9 (paysage)" if paysage else "ratio 9:16 (portrait)",
               (w * 9 == h * 16) if paysage else (w * 16 == h * 9), f"{w}/{h}")
            ok(ident, f"côtés de {rt['cote_min']} à {rt['cote_max']} px", all(rt["cote_min"] <= x <= rt["cote_max"] for x in (w, h)), f"{w}x{h}")
        elif ident not in ("feature-graphic", "apercu-social"):
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
        if ident in ("feature-graphic", "apercu-social"):
            ok(ident, f"couleurs vives (blanc < {vifs['fg_blanc_max']*100:.0f} %, noir < {vifs['fg_noir_max']*100:.0f} %, saturation moyenne > {vifs['fg_saturation_min']})",
               blanc < vifs["fg_blanc_max"] and noir < vifs["fg_noir_max"] and sat > vifs["fg_saturation_min"],
               f"blanc {blanc*100:.0f} %, noir {noir*100:.1f} %, saturation {sat:.2f}")
        else:
            vif, det = fond_vif(e_cap["fond"], C, vifs)
            ok(ident, "fond de marque vif (dégradé de couleurs saturées, ni noir ni blanc)", vif, det)
            controler_barre(ok, ident, e_cap, infos_barre.get(ident) if infos_barre else None, im)

    if cfg.get("icone") and (not ids or "icone" in ids):
        res += controler_icone(sortie / cfg["icone"]["fichier"], cfg["icone"], cfg["_regles"], info_icone)

    vit = cfg["feature_graphic"].get("vitrine")
    if vit and (not ids or "feature-graphic" in ids):
        tf = cfg["feature_graphic"]["textes"][langue]
        fonctions = tf.get("fonctions", [])
        ok("feature-graphic", "puces de fonctionnalités (5)", len(fonctions) == 5 and all(x.strip() for x in fonctions), f"{len(fonctions)}")
        norm = lambda x: x.lower().replace("’", "'")  # noqa: E731
        manq = [x for x in fonctions if norm(x) not in norm(tf["alt"])]
        ok("feature-graphic", "texte alternatif citant les fonctionnalités des puces", not manq, ", ".join(manq) or "toutes citées")
        for ident in ("feature-graphic", "apercu-social"):
            m = mesures.get(ident)
            if not m or (ident == "apercu-social" and not vit.get("sortie_sociale")):
                continue
            tailles = [t["taille"] for t in m["textes"] if t["texte"] in fonctions]
            mini = min(tailles) if tailles else 0
            ok(ident, "puces : police effective >= 13 px", len(tailles) == len(fonctions) and mini >= 13, f"{mini:.1f} px")
            petits = [t["taille"] for t in m["textes"] if 0 < t["taille"] < 13]
            ok(ident, "aucun texte sous 13 px effectifs", not petits, f"min {min([t['taille'] for t in m['textes'] if t['taille'] > 0] or [0]):.1f} px")

    for etiq, t in textes_visibles(cfg, langue):
        if ids and not ((etiq.startswith("tablettes/") and "tablettes" in ids) or etiq.split("/")[0] in ids):
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


def ecrire_rapport(cfg, langue, sortie: Path, res: list, etat_regles_: dict, fichier="rapport-controles.json", liste_regles=None) -> None:
    sortie.mkdir(parents=True, exist_ok=True)
    rapport = {"kit": "playstore-kit", "version_kit": VERSION_KIT, "application": cfg["application"], "langue": langue,
               "regles_google": {k: etat_regles_[k] for k in ("version", "date_verification", "age_jours", "validite_jours", "perimee")},
               "avertissements": [etat_regles_["avertissement"]] if etat_regles_["avertissement"] else [],
               "resultats": res}
    if liste_regles is not None:
        rapport["regles_google"]["liste"] = liste_regles
    (sortie / fichier).write_text(json.dumps(rapport, ensure_ascii=False, indent=1), encoding="utf-8")


def afficher(res: list) -> bool:
    echecs = [r for r in res if not r["ok"]]
    for r in res:
        print(("OK   " if r["ok"] else "ECHEC"), f"{r['visuel']:<34} {r['controle'][:70]:<70} {r['detail'][:90]}")
    print(f"\n{len(res) - len(echecs)}/{len(res)} contrôles passés" + ("" if not echecs else f", {len(echecs)} échec(s)"))
    return not echecs


def verifier(cfg, C, base, langue, sortie: Path, cache: Path, infos_barre, ids=None, quiet=False,
             etat_regles_: dict | None = None, info_icone: dict | None = None, liste_regles=None) -> bool:
    mf = cache / f"mesures-{langue}.json"
    mesures = json.loads(mf.read_text(encoding="utf-8")) if mf.exists() else {}
    res = controler(cfg, C, base, langue, sortie, mesures, infos_barre or {}, ids, info_icone)
    ecrire_rapport(cfg, langue, sortie, res, etat_regles_ or etat_regles(cfg["_regles"]), liste_regles=liste_regles)
    if quiet:
        return all(r["ok"] for r in res)
    return afficher(res)


# --------------------------------------------------------------------------- icône 512 x 512
ID_ICONE = "icon-512"


def rendre_svg(svg: Path, taille: int, cache: Path) -> Image.Image:
    """Rend un SVG carré en RGBA (fond transparent conservé) avec Chromium."""
    from playwright.sync_api import sync_playwright

    page = cache / "icone"
    page.mkdir(parents=True, exist_ok=True)
    f = page / "icone.html"
    f.write_text(f'<!doctype html><meta charset="utf-8"><style>html,body{{margin:0;background:transparent}}'
                 f'img{{display:block;width:{taille}px;height:{taille}px}}</style><img id="i" src="{uri(svg)}" alt="">', encoding="utf-8")
    sortie = page / "rendu.png"
    with sync_playwright() as p:
        nav = lancer_navigateur(p)
        pg = nav.new_page(viewport={"width": taille, "height": taille}, device_scale_factor=1)
        pg.goto(uri(f))
        pg.wait_for_function("document.getElementById('i').complete")
        nat = pg.evaluate("[document.getElementById('i').naturalWidth, document.getElementById('i').naturalHeight]")
        if not nat[0] or not nat[1] or abs(nat[0] / nat[1] - 1) > 0.01:
            nav.close()
            raise SystemExit(f"La source de l'icône doit être un SVG carré ({nat[0]}x{nat[1]} lu) : {svg}")
        pg.screenshot(path=str(sortie), omit_background=True)
        nav.close()
    return Image.open(sortie).convert("RGBA")


def produire_icone(cfg: dict, C: Couleurs, base: Path, cache: Path, sortie: Path) -> dict:
    """Produit icon-512.png : 512 x 512, PNG 32 bits, carré plein. Fond plein (couleur configurable) si la
    source est transparente. Retourne les mesures de la source pour les contrôles."""
    ic = cfg["icone"]
    taille = valeurs_regle(cfg["_regles"], "icone")["taille"]
    src = (base / ic["source"]).resolve()
    if src.suffix.lower() == ".svg":
        im = rendre_svg(src, taille, cache)
    else:
        im = Image.open(src).convert("RGBA")
        if im.size[0] != im.size[1]:
            raise SystemExit(f"La source de l'icône doit être carrée ({im.size[0]}x{im.size[1]} lu) : {src}")
        if im.size[0] != taille:
            im = im.resize((taille, taille), Image.LANCZOS)
    alpha = pixels(im.getchannel("A"))
    n = len(alpha)
    transparents = sum(1 for a in alpha if a < 255)
    partiels = sum(1 for a in alpha if 0 < a < 255)
    info = {"source": str(ic["source"]), "taille_source": [im.size[0], im.size[1]], "transparente": transparents > 0,
            "transparence_partielle_pct": round(100 * partiels / n, 3), "fond_applique": None}
    if transparents:
        fond = C(ic["fond"])
        plein = Image.new("RGBA", im.size, hex_rgb(fond) + (255,))
        plein.alpha_composite(im)
        im = plein
        info["fond_applique"] = fond
    sortie.mkdir(parents=True, exist_ok=True)
    im.save(sortie / ic["fichier"], format="PNG", optimize=True)   # RGBA 8 bits : PNG 32 bits
    return info


def _fond_interpole(im: Image.Image):
    """Fonction (x, y) -> couleur du fond estimée par interpolation des quatre coins (dégradés linéaires compris)."""
    w, h = im.size
    px = im.convert("RGB").load()

    def coin(x0, y0):
        pts = [px[x0 + dx, y0 + dy] for dx in range(3, 8) for dy in range(3, 8)]
        return [sum(p[i] for p in pts) / len(pts) for i in range(3)]
    hg, hd, bg, bd = coin(0, 0), coin(w - 8, 0), coin(0, h - 8), coin(w - 8, h - 8)

    def f(x, y):
        u, v = x / (w - 1), y / (h - 1)
        return [(hg[i] * (1 - u) + hd[i] * u) * (1 - v) + (bg[i] * (1 - u) + bd[i] * u) * v for i in range(3)]
    return f


def analyser_motif(im: Image.Image, ic: dict) -> dict:
    """Motif = pixels qui s'écartent du fond estimé. Mesures : zone de sécurité, contraste, présence."""
    w, h = im.size
    rgb = im.convert("RGB")
    px = rgb.load()
    fond = _fond_interpole(rgb)
    cx, cy = (w - 1) / 2, (h - 1) / 2
    rayon_max = ic["zone_securite_pct"] / 100 * w / 2
    motif, dehors, dmax = [], 0, 0.0
    for y in range(h):
        for x in range(w):
            f = fond(x, y)
            p = px[x, y]
            if max(abs(p[0] - f[0]), abs(p[1] - f[1]), abs(p[2] - f[2])) > ic["ecart_motif"]:
                motif.append(p)
                d = ((x - cx) ** 2 + (y - cy) ** 2) ** 0.5
                dmax = max(dmax, d)
                if d > rayon_max:
                    dehors += 1
    out = {"motif_pixels": len(motif), "hors_zone": dehors, "distance_max": round(dmax, 1), "rayon_zone": round(rayon_max, 1)}
    if motif:
        moy = [sum(p[i] for p in motif) / len(motif) for i in range(3)]
        coins = [fond(0, 0), fond(w - 1, 0), fond(0, h - 1), fond(w - 1, h - 1)]
        fond_moy = [sum(c[i] for c in coins) / 4 for i in range(3)]
        out["contraste"] = round(contraste(lum_rel(moy), lum_rel(fond_moy)), 2)
    return out


def controler_icone(chemin: Path, ic: dict, regles: dict, info: dict | None) -> list[dict]:
    """Contrôles automatiques de l'icône : dimensions, PNG 32 bits, poids, carré plein, coins, ombre, marge, contraste."""
    res: list[dict] = []
    v = valeurs_regle(regles, "icone")

    def ok(controle, passe, detail):
        res.append({"visuel": ID_ICONE, "controle": controle, "ok": bool(passe), "detail": detail})

    if not chemin.exists():
        ok("fichier présent", False, str(chemin))
        return res
    try:
        w, h, prof, typ, _ = lire_ihdr(chemin)
    except (ValueError, struct.error) as exc:
        ok("fichier PNG lisible", False, str(exc))
        return res
    t = v["taille"]
    ok(f"dimensions {t}x{t}", (w, h) == (t, t), f"{w}x{h}")
    ok("PNG 32 bits (RVB + alpha, 8 bits par canal)", prof == v["profondeur"] and typ == v["type_couleur_png"],
       f"profondeur {prof}, type de couleur {typ} (6 = RVBA)")
    ko = chemin.stat().st_size / 1024
    ok(f"poids <= {v['poids_max_ko']} Ko", ko <= v["poids_max_ko"], f"{ko:.0f} Ko")
    im = Image.open(chemin)
    if im.mode != "RGBA" or (w, h) != (t, t):
        ok("lecture RGBA", False, f"mode {im.mode}")
        return res
    alpha = pixels(im.getchannel("A"))
    mini = min(alpha)
    ok("carré plein : aucun pixel transparent", mini == 255, f"alpha minimal {mini}")
    rgb = im.convert("RGB")
    px = rgb.load()
    ecarts = []
    for (x, y), (x2, y2) in (((2, 2), (60, 60)), ((t - 3, 2), (t - 61, 60)), ((2, t - 3), (60, t - 61)), ((t - 3, t - 3), (t - 61, t - 61))):
        a, b = px[x, y], px[x2, y2]
        ecarts.append(sum((a[i] - b[i]) ** 2 for i in range(3)) ** 0.5)
    ok("pas de coins arrondis (les coins ont la couleur du fond voisin)", max(ecarts) <= 30, f"écart maximal des coins {max(ecarts):.0f} (limite 30)")
    if info is not None:
        lim = ic["transparence_partielle_max_pct"]
        ok(f"pas d'ombre ni de transparence douce dans la source (<= {lim} % de pixels)", info.get("transparence_partielle_pct", 0) <= lim,
           f"{info.get('transparence_partielle_pct')} % de pixels semi-transparents"
           + (f" ; fond plein {info['fond_applique']} appliqué sous la source transparente" if info.get("fond_applique") else ""))
    mo = analyser_motif(im, ic)
    ok("motif visible", mo["motif_pixels"] > 0, f"{mo['motif_pixels']} pixels de motif")
    if mo["motif_pixels"]:
        ok(f"marge de sécurité : motif dans un cercle de {ic['zone_securite_pct']:.0f} % du côté", mo["hors_zone"] == 0,
           f"distance maximale {mo['distance_max']} px (limite {mo['rayon_zone']} px), {mo['hors_zone']} pixel(s) dehors")
        ok(f"contraste motif / fond >= {ic['contraste_min']}:1", mo["contraste"] >= ic["contraste_min"], f"{mo['contraste']}:1")
    return res


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
    ap.add_argument("--config", default=None, help="fichier de configuration JSON (défaut : ./visuels.json)")
    ap.add_argument("--langue", default=None, help="langue des textes (défaut : langue_defaut de la configuration)")
    ap.add_argument("--verifier", action="store_true", help="vérifier sans régénérer")
    ap.add_argument("--seulement", nargs="+", metavar="ID", help="identifiants à régénérer (ex. 02-liste feature-graphic tablettes icone)")
    ap.add_argument("--sortie", metavar="DIR", help="dossier de sortie (remplace « sortie » de la configuration)")
    ap.add_argument("--init", metavar="DIR", help="crée un projet exemple prêt à lancer dans DIR puis s'arrête")
    ap.add_argument("--icone", action="store_true", help="produit et vérifie seulement l'icône 512 x 512 (bloc « icone » de la configuration)")
    ap.add_argument("--regles", action="store_true", help="affiche les règles Google codées (google_rules.json) et leur date de vérification")
    a = ap.parse_args(argv)

    try:
        regles = charger_regles()
    except ErreurConfig as exc:
        print(str(exc), file=sys.stderr)
        return 2
    etat = etat_regles(regles)
    if etat["avertissement"]:
        print("AVERTISSEMENT : " + etat["avertissement"], file=sys.stderr)
    if a.regles:
        print(texte_regles(regles, etat))
        if not (a.verifier or a.icone or a.config or a.seulement):
            return 0
    if a.init:
        return initialiser(Path(a.init).resolve())

    chemin = Path(a.config or "visuels.json").resolve()
    if not chemin.is_file():
        print(f"Configuration introuvable : {chemin}\nCréer un projet exemple avec : --init DIR", file=sys.stderr)
        return 2
    base = chemin.parent
    try:
        brut = json.loads(chemin.read_text(encoding="utf-8"))
        cfg = valider_config(brut, base, partiel=a.icone)
    except json.JSONDecodeError as exc:
        print(f"Configuration invalide : JSON illisible ({exc}).", file=sys.stderr)
        return 2
    except ErreurConfig as exc:
        print(f"Configuration invalide ({chemin.name}) :\n{exc}", file=sys.stderr)
        return 2

    langue = a.langue or cfg["langue_defaut"]
    cfg["_langue"] = langue
    C = Couleurs(cfg["theme"].get("palette"), cfg["_jetons"], cfg["theme"].get("prefixe_jetons", ""))
    cache = (base / cfg["cache"]).resolve()
    sortie = (Path(a.sortie).resolve() if a.sortie else (base / cfg["sortie"]).resolve())
    cache.mkdir(parents=True, exist_ok=True)
    liste = regles["regles"] if a.regles else None

    def lire(nom):
        f = cache / nom
        return json.loads(f.read_text(encoding="utf-8")) if f.exists() else {}

    # ---- icône seule
    if a.icone:
        if not cfg["icone"]:
            print("Configuration invalide : le bloc « icone » (source, fond…) est absent.", file=sys.stderr)
            return 2
        if not a.verifier:
            info = produire_icone(cfg, C, base, cache, sortie)
            (cache / "icone-info.json").write_text(json.dumps(info, ensure_ascii=False), encoding="utf-8")
        res = controler_icone(sortie / cfg["icone"]["fichier"], cfg["icone"], regles, lire("icone-info.json") or None)
        ecrire_rapport(cfg, langue, sortie, res, etat, "rapport-icone.json", liste)
        return 0 if afficher(res) else 1

    manq = langues_manquantes(cfg, langue)
    if manq:
        print(f"Langue « {langue} » absente des textes de : {', '.join(manq)}.", file=sys.stderr)
        return 2
    valides = {"feature-graphic", "tablettes", "icone"} | {e["id"] for e in cfg["captures"]}
    inconnus = [i for i in (a.seulement or []) if i not in valides]
    if inconnus:
        print(f"Identifiants inconnus pour --seulement : {', '.join(inconnus)}", file=sys.stderr)
        return 2
    if "tablettes" in (a.seulement or []) and not cfg["_tablettes"]:
        print("--seulement tablettes : le bloc « tablettes » est absent de la configuration.", file=sys.stderr)
        return 2
    if "icone" in (a.seulement or []) and not cfg["icone"]:
        print("--seulement icone : le bloc « icone » est absent de la configuration.", file=sys.stderr)
        return 2
    if langue != cfg["langue_defaut"]:
        sortie = sortie / langue

    vides = [e for e, t in textes_visibles(cfg, langue) if not t]
    if vides:
        print(f"Gabarit « {langue} » non rempli ({len(vides)} textes vides, ex. {vides[0]}). Renseigner la configuration avant de générer.",
              file=sys.stderr)
        return 2

    seul = a.seulement
    if not a.verifier:
        if not seul or set(seul) - {"icone"}:
            ids_img = [i for i in seul if i != "icone"] if seul else None
            infos_nouv = capturer_ecrans(cfg, base, cache, ids_img)
            infos = {k: {**v} for k, v in lire(f"barre-etat-{langue}.json").items()}
            for k, v in infos_nouv.items():
                infos[k] = v
            mesures, barres = composer(cfg, C, base, cache, sortie, ids_img, infos)
            for k, v in barres.items():
                infos[k] = {**infos.get(k, {}), **v}
            # fusion avec le cache : un --seulement ne doit pas effacer les mesures des autres visuels
            (cache / f"mesures-{langue}.json").write_text(json.dumps({**lire(f"mesures-{langue}.json"), **mesures}, ensure_ascii=False), encoding="utf-8")
            (cache / f"barre-etat-{langue}.json").write_text(json.dumps(infos, ensure_ascii=False), encoding="utf-8")
        if cfg["icone"] and (not seul or "icone" in seul):
            info = produire_icone(cfg, C, base, cache, sortie)
            (cache / "icone-info.json").write_text(json.dumps(info, ensure_ascii=False), encoding="utf-8")
    ok = verifier(cfg, C, base, langue, sortie, cache, lire(f"barre-etat-{langue}.json"), a.seulement, etat_regles_=etat,
                  info_icone=lire("icone-info.json") or None, liste_regles=liste)
    return 0 if ok else 1


if __name__ == "__main__":
    sys.exit(main())
