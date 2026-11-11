# -*- coding: utf-8 -*-
"""Chapitre 7 (v1.1) : photographies fournies par le client, écrans d'ouverture, ton, mode discret.
Images : design-system/assets/ (copies optimisées) ; originaux dans images/ (contre-exemple de pastille)."""
from kit import *
from ch01_03 import good, bad
from ch07_09 import ph

A = S = R = ASSETS
NOTE_OK = '<div class="dummy">Licence d\'usage commercial confirmée par le client le 28 octobre 2026</div>'

GRAD = 'linear-gradient(to top,rgba(8,10,30,.88) 0%,rgba(8,10,30,.62) 45%,rgba(8,10,30,0) 72%)'


def pill(txt, col):
    return f'<span class="pill" style="background:{col}">{txt}</span>'


def tile(path, name, status, h, pos='50% 30%', w='100%'):
    return (f'<div style="width:{w}"><div style="height:{h}mm;border-radius:1.4mm;overflow:hidden">{ph(path, "100%", "100%", pos=pos)}</div>'
            f'<div class="note" style="margin-top:1mm;font-style:normal;color:#1B1630"><b>{name}</b></div><div style="margin-top:.6mm">{status}</div></div>')


def phone(inner, w=58, h=103):
    return (f'<div style="width:{w}mm;height:{h}mm;position:relative;overflow:hidden;border-radius:4mm;background:#0B0D1A;'
            f'box-shadow:0 1mm 4mm rgba(0,0,0,.3);flex:none">{inner}</div>')


def splash_ui(path, pos, grad=True, title='Une communauté près de toi', sub='Des personnes qui t\'écoutent, sans jugement.', dots=True):
    g = f'<div class="abs" style="left:0;right:0;bottom:0;height:72%;background:{GRAD}"></div>' if grad else ''
    d = ('<div class="abs" style="left:4mm;bottom:25mm;display:flex;gap:1.2mm"><i style="width:4mm;height:1.2mm;border-radius:1mm;background:#fff"></i>'
         '<i style="width:1.2mm;height:1.2mm;border-radius:1mm;background:rgba(255,255,255,.55)"></i><i style="width:1.2mm;height:1.2mm;border-radius:1mm;background:rgba(255,255,255,.55)"></i></div>') if dots else ''
    return (f'{ph(path, "100%", "100%", pos=pos)}{g}'
            f'<div class="abs" style="left:4mm;top:5mm">{logo_w("mono-blanc", 18)}</div>'
            f'<div class="abs mt" style="right:3mm;top:4.5mm;background:rgba(8,10,30,.4);color:#fff;font:700 5pt Montserrat;padding:1.4mm 2.6mm;border-radius:5mm">Passer</div>'
            f'<div class="abs mt" style="left:4mm;right:4mm;bottom:31mm;color:#fff;font:800 9.6pt/1.1 Montserrat">{title}</div>'
            f'<div class="abs" style="left:4mm;right:4mm;bottom:25.5mm;color:#fff;font:400 5.2pt/1.3 Arial;display:none">{sub}</div>'
            f'{d}<div class="abs mt" style="left:4mm;right:4mm;bottom:14mm;height:7.5mm;border-radius:2mm;background:#fff;color:#0433C3;font:700 6pt/7.5mm Montserrat;text-align:center">Suivant</div>')


def build7b(doc):
    C = 7

    # ---------------------------------------------------------------- 1. fournies
    il = [(A + 'illustration-selfie-sourire-drapeau.jpg', 'selfie-sourire-drapeau', pill('Illustration', '#0B7A4B'), '50% 30%'),
          (A + 'illustration-drapeau-ciel-urbain.jpg', 'drapeau-ciel-urbain', pill('Illustration', '#0B7A4B'), '40% 30%'),
          (A + 'illustration-profil-fond-rose.jpg', 'profil-fond-rose', pill('Illustration · IA', '#B84B00'), '50% 30%'),
          (A + 'illustration-couple-drapeau-fond-terracotta.jpg', 'couple-fond-terracotta', pill('Illustration', '#0B7A4B'), '50% 25%')]
    sp = [(S + 'splash-paillettes-drapeau.jpg', 'paillettes-drapeau', pill('Alternative', '#0433C3'), '50% 28%'),
          (S + 'splash-couple-etreinte-drapeau.jpg', 'couple-etreinte', pill('Alternative', '#0433C3'), '50% 30%'),
          (S + 'splash-regard-lumiere-chaude.jpg', 'regard-lumiere-chaude', pill('Alternative · IA', '#B84B00'), '50% 30%'),
          (S + 'splash-lumiere-doree-drapeau.jpg', 'lumiere-doree-drapeau', pill('Alternative', '#0433C3'), '50% 30%'),
          (R + 'reference-texte-incruste-a-eviter.jpg', 'texte-incruste', pill('Écartée : texte', '#E30E0E'), '50% 50%'),
          (R + 'reference-bouche-scotchee-campagne-seulement.jpg', 'bouche-scotchee', pill('Écartée : anxiogène', '#E30E0E'), '50% 30%')]
    vis = ('<div style="padding:4mm 5mm;height:100%">'
           '<div class="lbl" style="margin-bottom:1.6mm">Illustrations · assets/illustrations/ (4)</div>'
           '<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:3mm">' + ''.join(tile(p, n, s, 40, pos) for p, n, s, pos in il) + '</div>'
           '<div class="lbl" style="margin:3.4mm 0 1.6mm">Écrans d\'ouverture · assets/splash/ (4 alternatives) et references-a-eviter/ (2)</div>'
           '<div style="display:grid;grid-template-columns:repeat(6,1fr);gap:3mm">' + ''.join(tile(p, n, s, 54, pos) for p, n, s, pos in sp) + '</div></div>' + NOTE_OK)
    doc.section(C, 'provided', 'Photographies fournies et droits', 'Supplied photographs and rights',
        ['<p class="lead">Dix photographies fournies par le client, utilisables pour illustrer l\'esprit de Xomiton.</p>',
         '<p>Ce sont des <b>photographies de personnes</b>, ni captures de design system ni maquettes de splash. <b>Droits d\'image : licence d\'usage commercial confirmée par le client le 28 octobre 2026</b>, preuve archivée hors dépôt. Originaux intacts dans <code>images/</code> ; copies WebP dans <code>design-system/assets/</code>.</p>'
         '<p>Pastilles de la banque d\'images retirées par recadrage. Les deux images « Ai » sont <b>générées par IA</b>.</p>'],
        vis)

    # ---------------------------------------------------------------- 2. ouverture
    g1 = good(phone(splash_ui(S + 'splash-paillettes-drapeau.jpg', '50% 18%'), 60, 106), 'Dégradé de lisibilité, regard en haut', 64, 112, '#fff', 'width:64mm')
    g1 = good(phone(splash_ui(S + 'splash-paillettes-drapeau.jpg', '50% 18%'), 60, 106), 'Dégradé : texte blanc ≥ 4,5:1', 64, 118, '#fff')
    b1 = bad(phone(splash_ui(S + 'splash-couple-etreinte-drapeau.jpg', '50% 40%', grad=False), 60, 106), 'Sans dégradé : texte illisible', 64, 118, '#fff')
    # schéma de cadrage 9:16 sur l'original portrait
    frame = (f'<div style="width:60mm;height:106mm;position:relative;overflow:hidden;background:#111;border-radius:1.6mm">{ph(S + "splash-lumiere-doree-drapeau.jpg", pos="50% 30%")}'
             '<div class="abs" style="left:0;right:0;top:33.3%;border-top:.3mm dashed rgba(255,255,255,.85)"></div><div class="abs" style="left:0;right:0;top:66.6%;border-top:.3mm dashed rgba(255,255,255,.85)"></div>'
             '<div class="abs" style="left:33.3%;top:0;bottom:0;border-left:.3mm dashed rgba(255,255,255,.55)"></div><div class="abs" style="left:66.6%;top:0;bottom:0;border-left:.3mm dashed rgba(255,255,255,.55)"></div>'
             '<div class="abs mt" style="left:3mm;top:3mm;background:#fff;color:#1B1630;font:700 6pt Montserrat;padding:.8mm 2mm;border-radius:5mm">Regard : tiers supérieur</div>'
             '<div class="abs mt" style="left:3mm;bottom:3mm;background:#fff;color:#1B1630;font:700 6pt Montserrat;padding:.8mm 2mm;border-radius:5mm">Texte et boutons : tiers inférieur</div></div>')
    g2 = good(frame, 'Cadrage 9:16, règle des tiers', 64, 118, '#fff')
    rows = [('Format', '9:16 portrait, plein écran, sous la barre d\'état'), ('Sujet', 'Visage entier, regard dans le tiers supérieur ; jamais coupé'), ('Texte', 'Titre ≥ 20 pt gras, 2 lignes au plus, dans le tiers inférieur'),
            ('Dégradé', 'Encre rgb(8 10 30) : 88 % en bas, ≥ 62 % jusqu\'à 45 % de la hauteur, 0 % à 72 %'), ('Contraste', 'Texte blanc ≥ 4,5:1 sur le pire cas (photographie blanche : 5,6:1)'), ('Boutons', 'Cibles ≥ 44 px, « Passer » sur pastille encre à 40 %')]
    tr = ''.join(f'<tr><td style="width:17mm"><b>{a}</b></td><td>{b}</td></tr>' for a, b in rows)
    vis = (f'<div style="display:flex;gap:5mm;padding:4mm 5mm;height:100%;align-items:flex-start">{g1}{b1}{g2}'
           f'<div style="flex:1;padding-left:2mm"><div class="lbl" style="margin-bottom:2mm">Règles de l\'écran d\'ouverture</div><table class="t" style="font-size:7pt">{tr}</table></div></div>' + NOTE_OK)
    doc.section(C, 'opening', 'Écrans d\'ouverture : cadrage et lisibilité', 'Opening screens',
        ['<p class="lead">Une photographie, un dégradé, un message : le texte se lit avant l\'image.</p>',
         '<p>Le splash et l\'onboarding utilisent une photographie <b>portrait 9:16</b> recadrée sur le regard, avec un <b>dégradé de lisibilité</b> sous le texte. <b>Aucun texte n\'est incrusté dans la photographie</b> : le titre est du texte réel, traduisible et lu par les lecteurs d\'écran. Pas de défilement automatique, mouvement coupé par <code>prefers-reduced-motion</code>.</p>'],
        vis, visclass='tint')

    # ---------------------------------------------------------------- 3. ton
    def tonal(path, t, d, pos):
        return (f'<div style="flex:1"><div style="height:42mm;border-radius:1.6mm;overflow:hidden;position:relative">{ph(path, "100%", "100%", pos=pos)}<span class="ok" style="z-index:2">&#10003;</span></div>'
                f'<div class="mt" style="font:800 9pt Montserrat;margin-top:2mm">{t}</div><div style="font-size:6.6pt;line-height:1.4;color:#5B5670">{d}</div></div>')
    tones = ''.join([tonal(S + 'splash-lumiere-doree-drapeau.jpg', 'Espoir', 'Lumière chaude, regard vers l\'avant.', '50% 25%'),
                     tonal(A + 'illustration-selfie-sourire-drapeau.jpg', 'Joie', 'Sourire franc, extérieur, couleurs naturelles.', '30% 20%'),
                     tonal(S + 'splash-couple-etreinte-drapeau.jpg', 'Solidarité', 'Proximité et soutien, sans mise en scène.', '50% 40%')])

    def nope(inner, lab, h=42):
        return (f'<div style="flex:1"><div style="height:{h}mm;border-radius:1.6mm;overflow:hidden;position:relative;border:.3mm solid #E30E0E">{inner}<span class="no" style="z-index:2">&#10005;</span></div>'
                f'<div style="font-size:6.6pt;line-height:1.4;color:#1B1630;margin-top:2mm">{lab}</div></div>')
    badge = (f'<div style="position:absolute;inset:0;overflow:hidden"><img src="{ASSETS}pastille-banque-images.jpg" alt="" style="width:100%;height:100%;object-fit:cover;object-position:0 100%"></div>')
    nopes = ''.join([nope(ph(R + 'reference-texte-incruste-a-eviter.jpg', pos='50% 70%'), '<b>Texte incrusté</b> (anglais, non lu par les lecteurs d\'écran) et nudité.'),
                     nope(ph(R + 'reference-bouche-scotchee-campagne-seulement.jpg', pos='50% 30%'), '<b>Bouches scotchées</b> : silence imposé. Campagne de sensibilisation seulement, jamais à l\'ouverture.'),
                     nope(badge, '<b>Pastille de banque d\'images</b> (couronne, « Ai ») : à rogner avant tout usage.')])
    vis = ('<div style="padding:4mm 5mm;height:100%"><div class="lbl" style="margin-bottom:2mm;color:#0B7A4B">Le ton : joie calme, solidarité, dignité</div>'
           f'<div style="display:flex;gap:4mm">{tones}</div>'
           '<div class="lbl" style="margin:4mm 0 2mm;color:#E30E0E">À éviter</div>'
           f'<div style="display:flex;gap:4mm">{nopes}</div></div>' + NOTE_OK)
    doc.section(C, 'tone', 'Ton, interdits et accessibilité', 'Tone, prohibitions and accessibility',
        ['<p><b>Ton.</b> Joie calme, solidarité, dignité ; personnes actrices, jamais victimes ; un seul visuel dominant par écran. <b>À éviter</b> aussi : nudité, mise en scène de violence ou de détresse, arc-en-ciel décoratif sur SOS, signalement et dossiers, image IA présentée comme une vraie personne.</p>',
         '<p><b>Accessibilité.</b> <code>alt</code> obligatoire et descriptif (« Deux femmes enlacées devant un drapeau arc-en-ciel »), <code>alt=""</code> si décorative. <b>Consentement</b> écrit, libre et retirable pour toute personne identifiable sur un contenu sensible.</p>'],
        vis, visclass='tint')

    # ---------------------------------------------------------------- 4. discret + poids
    neutral = (f'<div class="abs" style="inset:0;background:#0433C3"></div>{sym("rosace-couleur", 70, "position:absolute;right:-26mm;bottom:-10mm;filter:brightness(0) invert(1);opacity:.1")}'
               f'<div class="abs" style="left:0;right:0;top:38mm;display:flex;justify-content:center">{logo_w("mono-blanc", 34)}</div>')
    d1 = good(phone(neutral, 56, 100), 'Mode discret : logo sur fond uni', 60, 112, '#fff')
    d2 = bad(phone(splash_ui(S + 'splash-paillettes-drapeau.jpg', '50% 18%', dots=False), 56, 100), 'Visages et drapeau : à écarter en mode discret', 60, 112, '#fff')
    bars = [('Carrousel actuel (4 photos)', 247, '#1B1630'), ('+ 4 alternatives retenues', 201, '#0433C3'), ('Total si toutes ajoutées', 448, '#6F2C91'), ('Plafond d\'images du splash', 700, '#E30E0E')]
    bh = ''.join(f'<div style="margin-top:2.4mm"><div style="display:flex;justify-content:space-between;font:700 6.6pt Arial"><span>{n}</span><span>{v} Ko</span></div><div style="height:3.6mm;background:#EFE9F1;border-radius:1mm;margin-top:.8mm"><div style="width:{v/7:.1f}%;height:100%;background:{c};border-radius:1mm"></div></div></div>' for n, v, c in bars)
    vis = (f'<div style="display:flex;gap:6mm;padding:5mm 6mm;height:100%;align-items:flex-start">{d1}{d2}'
           '<div style="flex:1"><div class="lbl" style="margin-bottom:1mm">Poids des images du splash</div>' + bh +
           '<table class="t" style="margin-top:6mm;font-size:7pt"><tr><th>Décision</th><th>Motif</th></tr>'
           '<tr><td><b>Code du splash inchangé</b></td><td>quatre diapositives liées à quatre textes ; en ajouter exige des textes validés et la revue de discrétion</td></tr>'
           '<tr><td><b>4 alternatives prêtes</b></td><td>assets/splash/, WebP 37 à 61 Ko, sous le plafond de 700 Ko</td></tr>'
           '<tr><td><b>2 références écartées</b></td><td>texte incrusté ; bouches scotchées</td></tr></table></div></div>' + NOTE_OK)
    doc.section(C, 'discreet', 'Mode discret et poids des visuels', 'Discreet mode and weight',
        ['<p class="lead">Un écran de lancement ne doit pas désigner la personne qui l\'ouvre.</p>',
         '<p>Visages réels et drapeau sur l\'écran de lancement exposent la personne si quelqu\'un regarde son téléphone. Le <b>mode discret</b>, proposé dès la première ouverture, le remplace par le logo sur fond uni et neutralise aussi les visuels des notifications.</p>',
         '<p><b>Poids.</b> WebP sans métadonnées, 900 px au plus, 100 Ko au plus chacune ; total d\'images du splash sous <b>700 Ko</b>.</p>'],
        vis, visclass='tint')
