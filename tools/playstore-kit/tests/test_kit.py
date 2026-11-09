"""Tests du kit playstore (sans réseau). Lancer : python3 -m pytest tests -q

Les tests unitaires n'ouvrent pas de navigateur ; les deux tests de bout en bout lancent Chromium
(PW_CHROMIUM / XM_CHROMIUM ou recherche automatique) et sont ignorés s'il est introuvable.
"""
from __future__ import annotations

import copy
import json
import shutil
import sys
from pathlib import Path

import pytest
from PIL import Image

KIT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(KIT))
import build_playstore as kit  # noqa: E402


def chromium_disponible() -> bool:
    try:
        from playwright.sync_api import sync_playwright
        with sync_playwright() as p:
            kit.lancer_navigateur(p).close()
        return True
    except BaseException:  # noqa: BLE001 - SystemExit inclus
        return False


# --------------------------------------------------------------------------- aides
def config_minimale(nombre: int = 2) -> dict:
    """Configuration valide la plus courte : source « images », deux captures."""
    def cap(i):
        return {"id": f"0{i}-ecran", "source": {"image": f"shots/{i}.png"},
                "textes": {"fr": {"titre": f"Titre {i}", "alt": f"Écran {i} de l'application.", "puces": {}}}}
    return {
        "application": "Test",
        "theme": {"fond": {"de": "#0F7A57", "vers": "#0F4A3A", "angle": 160}},
        "source": {"type": "images"},
        "contraintes": {"captures": {"nombre": nombre}},
        "feature_graphic": {"textes": {"fr": {"slogan": "Un slogan sobre", "alt": "Logo et slogan sur fond vert."}}},
        "captures": [cap(i) for i in range(1, nombre + 1)],
    }


def png_vif(chemin: Path, taille, mode="RGB"):
    chemin.parent.mkdir(parents=True, exist_ok=True)
    im = Image.new(mode, taille, (15, 122, 87) if mode == "RGB" else (15, 122, 87, 200))
    im.save(chemin, format="PNG")


@pytest.fixture
def projet(tmp_path):
    """Projet minimal avec des sorties conformes déjà écrites (pas de navigateur)."""
    cfg = kit.valider_config(config_minimale(), tmp_path)
    cfg["_langue"] = "fr"
    sortie = tmp_path / "sortie"
    png_vif(sortie / cfg["feature_graphic"]["fichier"], (1024, 500))
    for e in cfg["captures"]:
        png_vif(sortie / "screenshots" / e["fichier"], (1080, 1920))
    C = kit.Couleurs(cfg["theme"]["palette"], cfg["_jetons"])
    return cfg, C, tmp_path, sortie


def lancer_controles(projet, cfg=None):
    c, C, base, sortie = projet
    return kit.controler(cfg or c, C, base, "fr", sortie, {}, {})


def echecs(res):
    return [r for r in res if not r["ok"]]


# --------------------------------------------------------------------------- configuration
def test_projet_conforme_de_reference(projet):
    assert echecs(lancer_controles(projet)) == []


def test_config_invalide_refusee_par_la_validation(tmp_path):
    brut = config_minimale()
    brut["theme"]["couleurs"] = {"texte": "rose-fluo"}              # couleur inconnue
    brut["captures"][0]["puces"] = [{"icone": "licorne", "cote": "haut", "y": 99999, "texte_ref": "absent"}]
    brut["captures"][1]["id"] = brut["captures"][0]["id"]           # doublon
    del brut["application"]
    with pytest.raises(kit.ErreurConfig) as exc:
        kit.valider_config(brut, tmp_path)
    msg = str(exc.value)
    for attendu in ("application", "Couleur inconnue", "licorne", "gauche", "en double", "texte manquant"):
        assert attendu in msg, f"« {attendu} » absent du message : {msg}"


def test_config_invalide_refusee_par_la_ligne_de_commande(tmp_path, capsys):
    f = tmp_path / "visuels.json"
    f.write_text(json.dumps({"application": "X", "captures": []}), encoding="utf-8")
    assert kit.main(["--config", str(f)]) == 2
    assert "Configuration invalide" in capsys.readouterr().err
    f.write_text("{pas du json", encoding="utf-8")
    assert kit.main(["--config", str(f)]) == 2
    assert kit.main(["--config", str(tmp_path / "absent.json")]) == 2


def test_langue_absente_refusee(tmp_path, capsys):
    f = tmp_path / "visuels.json"
    f.write_text(json.dumps(config_minimale()), encoding="utf-8")
    assert kit.main(["--config", str(f), "--langue", "en"]) == 2
    assert "absente" in capsys.readouterr().err


def test_gabarit_vide_refuse(tmp_path, capsys):
    brut = config_minimale()
    brut["feature_graphic"]["textes"]["en"] = {"slogan": "", "alt": ""}
    for e in brut["captures"]:
        e["textes"]["en"] = {"titre": "", "alt": "", "puces": {}}
    f = tmp_path / "visuels.json"
    f.write_text(json.dumps(brut), encoding="utf-8")
    assert kit.main(["--config", str(f), "--langue", "en"]) == 2
    assert "non rempli" in capsys.readouterr().err


def test_modele_documente_est_valide(tmp_path):
    brut = json.loads((KIT / "visuels.example.json").read_text(encoding="utf-8"))
    # le modèle pointe vers des fichiers à fournir : on les crée vides pour tester la structure
    for chemin in (brut["theme"].get("logo"), brut["theme"]["police"].get("fichier"), brut["theme"].get("tokens_css")):
        if chemin:
            (tmp_path / chemin).parent.mkdir(parents=True, exist_ok=True)
            (tmp_path / chemin).write_text("--c: #112233;", encoding="utf-8")
    cfg = kit.valider_config(brut, tmp_path)
    assert len(cfg["captures"]) == brut["contraintes"]["captures"]["nombre"]


def test_couleurs_hex_palette_et_jetons(tmp_path):
    jetons = tmp_path / "tokens.css"
    jetons.write_text(":root{--brand-blue-500:#2563eb; --autre:#112233;}", encoding="utf-8")
    C = kit.Couleurs({"marque": "#0f7a57"}, kit.charger_jetons(jetons), "brand-")
    assert C("#abc") == "#AABBCC" and C("#0f7a57") == "#0F7A57"
    assert C("marque") == "#0F7A57" and C("blue-500") == "#2563EB" and C("autre") == "#112233"
    with pytest.raises(kit.ErreurConfig):
        C("inconnue")


# --------------------------------------------------------------------------- contrôles
def test_mot_interdit_detecte(projet):
    cfg = copy.deepcopy(projet[0])
    cfg["captures"][0]["textes"]["fr"]["titre"] = "Le meilleur jardin, gratuit"
    r = [x for x in lancer_controles(projet, cfg) if x["controle"] == "mots interdits" and x["visuel"] == "01-ecran/titre"][0]
    assert not r["ok"] and "meilleur" in r["detail"] and "gratuit" in r["detail"]


def test_mots_interdits_anglais_par_defaut_et_configurables(projet):
    cfg = projet[0]
    assert "free" in kit.mots_interdits_trouves("Totally FREE to use", cfg)
    assert kit.mots_interdits_trouves("No. 1 app, 5 millions de téléchargements", cfg)
    assert kit.mots_interdits_trouves("Un jardin sobre", cfg) == []
    cfg2 = copy.deepcopy(cfg)
    cfg2["contraintes"]["mots_interdits_ajout"] = ["jardin"]
    assert kit.mots_interdits_trouves("Un jardin sobre", cfg2) == ["jardin"]
    cfg3 = copy.deepcopy(cfg)
    cfg3["contraintes"]["mots_interdits"] = ["zorglub"]
    assert kit.mots_interdits_trouves("gratuit zorglub", cfg3) == ["zorglub"]


def test_alpha_detecte(projet):
    cfg, _, _, sortie = projet
    png_vif(sortie / "screenshots" / cfg["captures"][0]["fichier"], (1080, 1920), mode="RGBA")
    e = echecs(lancer_controles(projet))
    assert any(x["visuel"] == "01-ecran" and "alpha" in x["controle"] for x in e)


def test_dimension_fausse_detectee(projet):
    cfg, _, _, sortie = projet
    png_vif(sortie / cfg["feature_graphic"]["fichier"], (1000, 500))
    png_vif(sortie / "screenshots" / cfg["captures"][1]["fichier"], (1080, 1900))
    e = echecs(lancer_controles(projet))
    assert any(x["visuel"] == "feature-graphic" and x["controle"] == "dimensions" for x in e)
    assert any(x["visuel"] == "02-ecran" and x["controle"] == "dimensions" for x in e)
    assert any(x["visuel"] == "02-ecran" and "9:16" in x["controle"] for x in e)


def test_texte_alternatif_trop_long_ou_mal_forme(projet):
    cfg = copy.deepcopy(projet[0])
    cfg["captures"][0]["textes"]["fr"]["alt"] = "x" * 141
    cfg["captures"][1]["textes"]["fr"]["alt"] = "Image de la liste des plantes."
    e = echecs(lancer_controles(projet, cfg))
    assert any(x["visuel"] == "01-ecran/alt" and "140" in x["controle"] for x in e)
    assert any(x["visuel"] == "02-ecran/alt" and "image de" in x["controle"] for x in e)
    cfg["captures"][0]["textes"]["fr"]["alt"] = "y" * 140
    assert not any(x["visuel"] == "01-ecran/alt" for x in echecs(lancer_controles(projet, cfg)))


def test_poids_et_fichier_absent(projet):
    cfg, _, _, sortie = projet
    cfg2 = copy.deepcopy(cfg)
    cfg2["contraintes"]["captures"]["poids_max_mo"] = 0.0001
    assert any(x["controle"] == "poids" for x in echecs(lancer_controles(projet, cfg2)))
    (sortie / "screenshots" / cfg["captures"][0]["fichier"]).unlink()
    assert any(x["controle"] == "fichier présent" for x in echecs(lancer_controles(projet)))


def test_nombre_de_captures_et_fond_vif(projet):
    cfg = copy.deepcopy(projet[0])
    cfg["contraintes"]["captures"]["nombre"] = 8
    assert any(x["controle"] == "nombre de captures" for x in echecs(lancer_controles(projet, cfg)))
    cfg = copy.deepcopy(projet[0])
    cfg["captures"][0]["fond"] = {"de": "#FFFFFF", "vers": "#222222", "angle": 0}
    assert any("fond de marque vif" in x["controle"] for x in echecs(lancer_controles(projet, cfg)))


def test_fiche_longueurs_et_mots(projet):
    cfg = copy.deepcopy(projet[0])
    cfg["fiche"]["textes"] = {"nom": "N" * 31, "court": "Download now", "long": "ok"}
    e = echecs(lancer_controles(projet, cfg))
    assert any(x["visuel"] == "fiche/nom" and "30" in x["controle"] for x in e)
    assert any(x["visuel"] == "fiche/court" and x["controle"] == "mots interdits" for x in e)


def test_barre_etat_mesuree(projet):
    cfg, C, base, sortie = projet
    mauvais = {"01-ecran": {"mode": "recouvrir", "heure": "14:07", "svgs": 3, "remplissage": "19"}}
    res = kit.controler(cfg, C, base, "fr", sortie, {}, mauvais)
    assert any("barre d'état" in x["controle"] and not x["ok"] for x in res)
    bon = {"01-ecran": {"mode": "recouvrir", "heure": "9:41", "svgs": 3, "remplissage": "19"}}
    res = kit.controler(cfg, C, base, "fr", sortie, {}, bon)
    assert any("barre d'état" in x["controle"] and x["ok"] for x in res)


def test_aire_union_et_contraste():
    assert kit.aire_union([[0, 0, 10, 10], [5, 5, 15, 15]]) == pytest.approx(175)
    assert kit.contraste(kit.lum_rel((255, 255, 255)), kit.lum_rel((0, 0, 0))) == pytest.approx(21)


def test_chromium_variables_d_environnement(tmp_path, monkeypatch):
    faux = tmp_path / "chrome"
    faux.write_text("", encoding="utf-8")
    monkeypatch.delenv("PW_CHROMIUM", raising=False)
    monkeypatch.setenv("XM_CHROMIUM", str(faux))
    assert kit.trouver_chromium() == str(faux)
    monkeypatch.setenv("PW_CHROMIUM", str(tmp_path / "absent"))
    with pytest.raises(SystemExit):
        kit.trouver_chromium()


def test_init_cree_un_projet_valide(tmp_path):
    dest = tmp_path / "nouveau"
    assert kit.main(["--init", str(dest)]) == 0
    assert (dest / "visuels.json").is_file() and (dest / "app" / "index.html").is_file()
    assert not (dest / "sortie").exists()
    kit.valider_config(json.loads((dest / "visuels.json").read_text(encoding="utf-8")), dest)
    assert kit.main(["--init", str(dest)]) == 2     # dossier non vide : refusé


# --------------------------------------------------------------------------- bout en bout (Chromium)
pytestmark_e2e = pytest.mark.skipif(not chromium_disponible(), reason="Chromium introuvable")


@pytestmark_e2e
def test_exemple_de_bout_en_bout(tmp_path):
    """L'exemple fourni produit 1 image de présentation + 8 captures qui passent tous les contrôles."""
    shutil.copytree(KIT / "example", tmp_path / "ex", ignore=shutil.ignore_patterns("sortie", ".cache"))
    code = kit.main(["--config", str(tmp_path / "ex" / "visuels.json")])
    assert code == 0
    sortie = tmp_path / "ex" / "sortie"
    assert (sortie / "feature-graphic-1024x500.png").is_file()
    caps = sorted((sortie / "screenshots").glob("*.png"))
    assert len(caps) == 8
    for f in [sortie / "feature-graphic-1024x500.png", *caps]:
        im = Image.open(f)
        assert im.mode == "RGB" and im.size in ((1024, 500), (1080, 1920))
    rapport = json.loads((sortie / "rapport-controles.json").read_text(encoding="utf-8"))
    assert rapport["resultats"] and all(r["ok"] for r in rapport["resultats"])
    # --verifier relit les sorties sans régénérer
    assert kit.main(["--config", str(tmp_path / "ex" / "visuels.json"), "--verifier"]) == 0
    # --seulement ne régénère qu'une capture et garde le reste conforme
    assert kit.main(["--config", str(tmp_path / "ex" / "visuels.json"), "--seulement", "04-rappels"]) == 0


@pytestmark_e2e
def test_images_deja_capturees_barre_recouverte(tmp_path):
    """Source « images » : captures d'émulateur avec une barre d'état chargée, recouverte par une barre propre."""
    from PIL import ImageDraw
    brut = config_minimale(nombre=2)
    brut["barre_etat"] = {"heure": "9:41"}
    (tmp_path / "shots").mkdir()
    for i in (1, 2):
        im = Image.new("RGB", (1080, 2400), (250, 247, 240))
        d = ImageDraw.Draw(im)
        d.rectangle((0, 0, 1080, 90), fill=(250, 247, 240))
        d.text((40, 30), "17:52  Operateur  12 %", fill=(0, 0, 0))   # barre d'état à cacher
        d.rectangle((60, 300, 1020, 700), fill=(31, 122, 92))
        im.save(tmp_path / "shots" / f"{i}.png")
    f = tmp_path / "visuels.json"
    f.write_text(json.dumps(brut), encoding="utf-8")
    assert kit.main(["--config", str(f), "--sortie", str(tmp_path / "dist")]) == 0
    rapport = json.loads((tmp_path / "dist" / "rapport-controles.json").read_text(encoding="utf-8"))
    barres = [r for r in rapport["resultats"] if "barre d'état" in r["controle"]]
    assert len(barres) == 2 and all(r["ok"] for r in barres)
    # la barre d'origine est bien recouverte : plus de texte sombre dans la bande supérieure de l'écran composé
    comp = Image.open(tmp_path / "dist" / "screenshots" / "01-ecran.png").convert("RGB")
    assert comp.size == (1080, 1920)
