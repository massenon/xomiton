# -*- coding: utf-8 -*-
from kit import *
from structure import CHAPTERS
from ch01_03 import good, bad, ch_of
from ch04_06 import ribbons, waves

PH = HOME  # photographies de travail
PHOTOS = [PH + '07.jpg', PH + '01.png', PH + '02.png', PH + '03.jpg', PH + 'avatar1.png', PH + 'avatar2.png', PH + 'avatar3.png', PH + 'avatar4.png', IMG + '04.jpg', IMG + '05.jpg']
NOTE_PH = '<div class="dummy">Images de travail du projet : droits à confirmer</div>'

def blend(fg, alpha, bg='#FFFFFF'):
    a, b = rgb(fg), rgb(bg)
    return '#%02X%02X%02X' % tuple(round(a[i] * alpha + b[i] * (1 - alpha)) for i in range(3))

def ph(path, w='100%', h='100%', pos='center', extra='', fit='cover'):
    return img(path, w, h, fit=fit, pos=pos, extra=extra)

def placeholder(label, w='100%', h='100%', ic='camera'):
    return (f'<div style="width:{w};height:{h};background:repeating-linear-gradient(45deg,#F6F2F7 0 3mm,#EFE9F1 3mm 6mm);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2mm;color:#5B5670">'
            f'{icon(ic, 9, "#8E88A0")}<div style="font:700 7pt Arial;text-align:center;max-width:70%">{label}</div></div>')

def build(doc):
    # =============================== CHAPITRE 7 : PHOTOGRAPHIE ===============================
    doc.divider(ch_of(7)); C = 7
    doc.section(C, 'diversity', 'Représenter la diversité', 'Representing diversity',
        ['<p class="lead">Montrer la communauté telle qu\'elle est : plurielle.</p>',
         '<p>Les photographies montrent des personnes de <b>teintes de peau, d\'âges, de morphologies, d\'expressions de genre et de rôles variés</b> : membres, pair·es éducateur·rice·s, professionnel·les, allié·e·s. Aucun stéréotype (tenue, posture, rôle) ; pas de personne unique qui « représente » tout le monde.</p>',
         '<p><b>Consentement écrit et libre</b> pour toute personne reconnaissable, retirable à tout moment. <b>Jamais</b> de photographie identifiable d\'une personne qui a subi des violences, ni de mise en scène de violence.</p>'],
        f'<div style="display:grid;grid-template-columns:repeat(5,1fr);grid-template-rows:1fr 1fr;gap:1.2mm;height:100%;padding:1.2mm">{"".join(ph(p, "100%", "100%") for p in PHOTOS)}</div>{NOTE_PH}')
    spec_rows = [('Cadrage', 'Plans rapprochés et moyens, à hauteur d\'yeux ; mains, détails et plans de dos pour protéger les identités'), ('Lumière', 'Naturelle, douce ; pas de flash frontal dur ni de contre-jour sombre sur les visages'),
                 ('Couleur', 'Chaude et naturelle ; saturation fidèle ; pas de filtre de couleur plaqué'), ('Arrière-plan', 'Simple ou flou ; lieux réels (rue, atelier, salle) ; pas de fond de studio impersonnel'),
                 ('Taille', 'Au moins 2400 px sur le grand côté pour l\'impression, 1920 px pour le web, fichier d\'origine conservé')]
    rows = ''.join(f'<tr><td><b>{a}</b></td><td>{b}</td></tr>' for a, b in spec_rows)
    doc.section(C, 'style', 'Style', 'Style',
        ['<p class="lead">Chaleureux, naturel, sur le vif.</p>',
         '<p>Une photographie Xomiton donne l\'impression d\'une rencontre, pas d\'une publicité : spontanée, proche, lumineuse. Elle sert le propos (apprendre, être écoutée, agir) et ne décore pas.</p>'],
        f'<div style="display:grid;grid-template-columns:1.1fr 1fr;height:100%"><div style="display:grid;grid-template-columns:1fr 1fr;gap:1.2mm;padding:1.2mm">{ph(PH + "07.jpg", pos="40% 35%", extra="grid-row:span 2;")}{ph(PHOTOS[1])}{ph(PHOTOS[8])}</div>'
        f'<div style="padding:7mm 8mm"><div class="lbl" style="margin-bottom:2mm">Critères</div><table class="t" style="font-size:8.4pt">{rows}</table></div></div>{NOTE_PH}')
    doc.section(C, 'action', 'Action', 'Action',
        ['<p>L\'action montre la <b>mobilisation et le collectif</b> : ateliers, rencontres, accompagnement, événements. Le mouvement est naturel (marcher, parler, partager un écran), pas posé.</p>',
         '<p><b>Contexte de risque.</b> Quand la visibilité peut exposer des personnes (contexte légal ou social défavorable), on privilégie les plans de dos, les mains, les silhouettes, les détails d\'objets, ou un flou volontaire. Cette règle prime sur toute considération esthétique.</p>'],
        f'<div style="display:grid;grid-template-columns:1.4fr 1fr 1fr;grid-template-rows:1fr 1fr;gap:1.2mm;height:100%;padding:1.2mm">{ph(PHOTOS[3], extra="grid-row:span 2;")}{ph(PHOTOS[7])}{ph(PHOTOS[6])}{placeholder("Plan de dos, mains, détails : à produire")}{placeholder("Atelier de pair·es : à produire", ic="people")}</div>{NOTE_PH}')
    doc.section(C, 'emotion', 'Émotion', 'Emotion',
        ['<p>Quatre émotions guident le choix : <b>joie</b>, <b>solidarité</b>, <b>calme</b>, <b>détermination</b>. On évite la pitié, la détresse mise en scène et la victimisation : la personne est présentée comme actrice de son parcours, pas comme un cas.</p>'],
        f'<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:3mm;padding:4mm;height:100%">'
        + ''.join(f'<div style="position:relative;display:flex;flex-direction:column"><div style="flex:1;overflow:hidden;border-radius:1.6mm">{ph(p)}</div><div class="mt" style="font:800 10pt Montserrat;margin-top:2.5mm">{t}</div><div style="font-size:7pt;color:#5B5670;line-height:1.4">{d}</div></div>' for p, t, d in
                  ((PHOTOS[0], 'Joie', 'Sourire franc, énergie, couleurs vives.'), (PHOTOS[1], 'Solidarité', 'Gestes partagés, proximité, complicité.'), (PHOTOS[4], 'Calme', 'Regard posé, ambiance apaisée, lumière douce.'), (PHOTOS[7], 'Détermination', 'Regard tourné vers l\'avant, posture droite.')))
        + f'</div>{NOTE_PH}')
    doc.section(C, 'heritage', 'Culture et lieux', 'Heritage',
        ['<p>Ancrer Xomiton dans son territoire : <b>lieux, tissus, architecture, marchés et paysages du Bénin</b>, vus avec respect, sans folklore ni cliché. Les lieux montrés sont des lieux sûrs et publics ; jamais de lieu qui permettrait de localiser une personne ou une structure d\'accueil à protéger.</p>',
         '<p class="note">Aucune photographie de lieux béninois n\'existe dans les ressources actuelles : les cadres ci-contre sont des emplacements à produire.</p>'],
        f'<div style="display:grid;grid-template-columns:repeat(4,1fr);grid-template-rows:1fr 1fr;gap:1.2mm;height:100%;padding:1.2mm">{placeholder("Tissus et motifs (pagnes)", ic="grid-3x3-gap")}{placeholder("Architecture, rues de Cotonou et Porto-Novo", ic="buildings")}{placeholder("Marchés, vie quotidienne", ic="basket")}{placeholder("Paysages, lagunes, côte", ic="tree")}{placeholder("Lieux de rencontre sûrs", ic="house-heart")}{placeholder("Cérémonies et fêtes", ic="stars")}{placeholder("Mains au travail, savoir-faire", ic="hand-index-thumb")}{placeholder("Écoles et centres de santé", ic="mortarboard")}</div>')
    doc.section(C, 'editorial', 'Éditorial', 'Editorial',
        ['<p>En contexte éditorial (article, rapport, actualité), la photographie est <b>légendée et créditée</b>, avec un texte alternatif descriptif. Formats de recadrage : 16:9 (une, bannière), 3:2 (article), 1:1 (carte, avatar), 4:5 (réseaux sociaux).</p>'],
        f'<div style="display:grid;grid-template-columns:1.5fr 1fr;gap:6mm;padding:6mm 8mm;height:100%"><div class="card" style="background:#fff;border:.2mm solid #e4dce6;padding:0;overflow:hidden;display:flex;flex-direction:column"><div style="height:62%;overflow:hidden">{ph(PHOTOS[0], pos="40% 40%")}</div>'
        '<div style="padding:4mm 5mm"><div class="lbl" style="color:#E30E0E">Actualités</div><div class="mt" style="font:800 15pt/1.15 Montserrat;margin:1.5mm 0 1.5mm">Une journée pour parler librement</div><div style="font:400 8pt/1.5 Arial;color:#3b3650">Légende : participante à l\'atelier du 12 novembre. Crédit : [auteur·rice], avec accord écrit.</div></div></div>'
        '<div><div class="lbl" style="margin-bottom:2.5mm">Formats de recadrage</div><div style="display:flex;gap:3mm;align-items:flex-end;flex-wrap:wrap">'
        + ''.join(f'<div><div style="width:{w}mm;height:{h}mm;overflow:hidden;border-radius:1mm">{ph(PHOTOS[0], pos="40% 40%")}</div><div class="note">{n}</div></div>' for w, h, n in ((36, 20.2, '16:9'), (30, 20, '3:2'), (22, 22, '1:1'), (18, 22.5, '4:5')))
        + '</div><table class="t" style="margin-top:6mm;font-size:8pt"><tr><th>Élément</th><th>Obligatoire</th></tr><tr><td>Texte alternatif (alt)</td><td>Oui, descriptif</td></tr><tr><td>Titre (title)</td><td>Oui, utile</td></tr><tr><td>Légende et crédit</td><td>Oui</td></tr><tr><td>Consentement archivé</td><td>Oui</td></tr></table></div></div>' + NOTE_PH)
    fmts = [('Image héros', '1920 × 1080', '16:9', 'AVIF/WebP + JPEG, ≤ 250 Ko'), ('Carte article', '800 × 533', '3:2', 'AVIF/WebP, ≤ 80 Ko'), ('Aperçu de partage (OG)', '1200 × 630', '1,91:1', 'JPEG, ≤ 300 Ko'), ('Avatar', '256 × 256', '1:1', 'WebP, ≤ 20 Ko'), ('Couverture de profil', '1500 × 500', '3:1', 'WebP, ≤ 120 Ko')]
    rows = ''.join(f'<tr><td><b>{a}</b></td><td>{b}</td><td>{c}</td><td>{d}</td></tr>' for a, b, c, d in fmts)
    doc.section(C, 'digital', 'Usage numérique', 'Digital application',
        ['<p>Sur le web et l\'application : formats <b>AVIF puis WebP</b> avec repli JPEG, plusieurs tailles (<code>srcset</code> et <code>sizes</code>), <code>width</code> et <code>height</code> renseignés (aucun décalage de mise en page), <code>loading="lazy"</code> sauf l\'image principale, <code>alt</code> et <code>title</code> obligatoires. Avec peu de données, une version basse définition floutée s\'affiche d\'abord.</p>'],
        f'<div style="display:grid;grid-template-columns:1.4fr 1fr;gap:8mm;padding:6mm 8mm;height:100%"><div><div class="lbl" style="margin-bottom:2mm">Formats de référence</div><table class="t" style="font-size:8.6pt"><tr><th>Usage</th><th>Dimensions (px)</th><th>Ratio</th><th>Format, poids</th></tr>{rows}</table></div>'
        '<div class="card" style="background:#F6F2F7"><div class="lbl">Modèle de balise</div><pre style="font:400 7.2pt/1.5 Consolas,monospace;margin:2.5mm 0 0;white-space:pre-wrap;color:#1B1630">&lt;picture&gt;\n  &lt;source type="image/avif" srcset="a-800.avif 800w, a-1600.avif 1600w" sizes="(min-width:992px) 50vw, 100vw"&gt;\n  &lt;source type="image/webp" srcset="a-800.webp 800w, a-1600.webp 1600w"&gt;\n  &lt;img src="a-800.jpg" width="800" height="533"\n       alt="Deux femmes se prennent en photo, souriantes"\n       title="Atelier du 12 novembre"\n       loading="lazy" decoding="async"&gt;\n&lt;/picture&gt;</pre></div></div>')
    sc = blend('#1B1630', .62, '#FFFFFF')
    doc.section(C, 'adding', 'Ajouter des éléments', 'Adding elements',
        ['<p>Texte, logo et boutons se posent sur une <b>zone calme</b> de la photographie ou sur un <b>voile</b> encre. Avec un voile de 62 %, le pire cas (photo blanche dessous) donne un fond de <b>' + sc + '</b>, soit <b>' + str(cr('#FFFFFF', sc)) + ':1</b> pour du texte blanc : au-dessus du seuil de 4,5:1. Un dégradé du bas vers le haut protège un titre en pied d\'image.</p>'],
        '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:3mm;padding:4mm;height:100%">'
        + good(f'<div style="position:relative;width:100%;height:100%">{ph(PHOTOS[0], pos="40% 40%")}<div style="position:absolute;inset:0;background:linear-gradient(to top,rgba(27,22,48,.85),rgba(27,22,48,0) 65%)"></div><div class="abs mt" style="left:5mm;bottom:5mm;right:5mm;color:#fff;font:800 14pt/1.15 Montserrat">Tu n\'es pas seule.</div></div>', 'Dégradé de pied : titre lisible', 88, 120, '#fff', 'width:100%;height:100%')
        + good(f'<div style="position:relative;width:100%;height:100%">{ph(PHOTOS[3])}<div style="position:absolute;inset:0;background:rgba(27,22,48,.62)"></div><div class="abs" style="left:50%;top:50%;transform:translate(-50%,-50%)">{logo_w("mono-blanc", 40)}</div></div>', 'Voile 62 % et logo blanc', 88, 120, '#fff', 'width:100%;height:100%')
        + bad(f'<div style="position:relative;width:100%;height:100%">{ph(PHOTOS[1])}<div class="abs mt" style="left:5mm;top:40%;right:5mm;color:#fff;font:800 14pt/1.15 Montserrat">Texte blanc sur zone claire sans voile</div></div>', 'Sans voile : illisible par endroits', 88, 120, '#fff', 'width:100%;height:100%')
        + '</div>' + NOTE_PH, visclass='tint')
    base_f = 'filter:none'
    doc.section(C, 'post', 'Post-traitement', 'Post-processing',
        ['<p>Retouches <b>mesurées</b> : recadrage, exposition, balance des blancs vers le chaud, contraste et saturation dans une limite de ±10 %. Pas de lissage de peau, pas de filtre de style, pas de modification de morphologie.</p>',
         '<p><b>Protection des personnes</b> : suppression des métadonnées (EXIF, géolocalisation) à l\'import, flou ou pixellisation des visages quand le consentement manque, conservation de l\'original en lieu privé. Un visage flouté l\'est de façon irréversible dans le fichier diffusé.</p>'],
        '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:3mm;padding:4mm;height:100%">'
        + good(f'<div style="width:100%;height:100%;overflow:hidden">{ph(PHOTOS[1], extra="filter:brightness(.82) saturate(.8)")}</div>', 'Original', 88, 120, '#fff', 'width:100%;height:100%')
        + good(f'<div style="width:100%;height:100%;overflow:hidden">{ph(PHOTOS[1], extra="filter:brightness(1.06) contrast(1.06) saturate(1.08)")}</div>', 'Retouche mesurée (±10 %)', 88, 120, '#fff', 'width:100%;height:100%')
        + good(f'<div style="width:100%;height:100%;overflow:hidden">{ph(PHOTOS[1], extra="filter:blur(7px);transform:scale(1.08)")}</div>', 'Visage flouté (consentement absent)', 88, 120, '#fff', 'width:100%;height:100%')
        + '</div>' + NOTE_PH, visclass='tint')

    from ch07b import build7b; build7b(doc)

    # =============================== CHAPITRE 8 : ILLUSTRATIONS ===============================
    doc.divider(ch_of(8)); C = 8
    doc.section(C, 'concept', 'Concept', 'Concept',
        ['<p class="lead">Des personnages divers dans des scènes de solidarité.</p>',
         '<p>Les illustrations plates complètent la photographie quand on ne veut pas montrer de visages réels : sujets sensibles, éducation sexuelle et reproductive, droits, états vides. Elles sont <b>chaleureuses</b>, <b>inclusives</b> et <b>sans violence</b>. Elles ne montrent jamais d\'agression ni de victime en détresse.</p>'],
        f'<div style="display:grid;grid-template-columns:1.2fr 1fr 1fr;gap:1.2mm;height:100%;padding:1.2mm;background:#fff">{ph(IMG + "hero-10.jpg", pos="75% 50%")}{ph(IMG + "hero-09.jpg", pos="50% 70%")}{ph(HOME + "hero-04.jpg", pos="50% 50%")}</div>')
    pal = [('#F9ECF4', 'Rose pâle (fond)'), ('#FFD6D6', 'Rose (fond)'), ('#9B3B9B', 'Violet'), ('#A06AF0', 'Lilas'), ('#F97B20', 'Orange'), ('#FFD24A', 'Jaune'), ('#00BF40', 'Vert'), ('#7AB2FF', 'Bleu clair'), ('#2A2650', 'Marine'), ('#6B1A10', 'Peau profonde'), ('#CF6B50', 'Peau chaude'), ('#FFA586', 'Peau claire')]
    chips = ''.join(f'<div style="flex:1"><div style="height:30mm;background:{h};border-radius:1.6mm;border:.2mm solid #e4dce6"></div><div class="mt" style="font:700 6.8pt Montserrat;margin-top:1.5mm">{n}</div><div style="font-size:6pt;color:#5B5670">{h}</div></div>' for h, n in pal)
    doc.section(C, 'style', 'Style et couleur', 'Style and Colour',
        ['<p>Style <b>plat</b> : aplats de couleur, sans contour ni dégradé complexe, formes arrondies, visages simples. Palette relevée dans les illustrations du projet (échantillonnage) : fonds rose pâle, violets, orange, jaune, vert, bleu clair, marine et plusieurs teintes de peau.</p>',
         '<p>Cette palette est <b>décorative</b> : elle ne se mélange pas aux couleurs fonctionnelles de l\'interface (bleu d\'action, rouge d\'attention). Les teintes de peau ne se modifient jamais pour « coller à la palette ».</p>'],
        f'<div style="padding:6mm 8mm"><div class="lbl" style="margin-bottom:3mm">Palette des illustrations (valeurs approchées)</div><div style="display:flex;gap:2.2mm">{chips}</div>'
        f'<div style="display:flex;gap:4mm;margin-top:8mm;height:75mm">{ph(IMG + "hero-09.jpg", "33%", "100%", pos="50% 70%", extra="border-radius:1.6mm")}{ph(IMG + "hero-12.jpg", "33%", "100%", pos="50% 70%", extra="border-radius:1.6mm")}{ph(HOME + "7150569.jpg", "33%", "100%", extra="border-radius:1.6mm")}</div></div>')
    el = lambda n: f'<img src="{ELT}{n}.svg" style="height:38mm;max-width:100%" alt="">'
    doc.section(C, 'types', 'Types d\'illustrations', 'Illustration types',
        ['<p>Trois types, pour trois usages : les <b>scènes de personnages</b> (héros, bandeaux), les <b>éléments décoratifs</b> (formes et pictos en aplat, fonds) et les <b>schémas pédagogiques</b> (corps, cycles, démarches), ces derniers restant à produire avec des professionnel·les.</p>'],
        f'<div style="display:grid;grid-template-columns:1.5fr 1fr 1fr;gap:4mm;padding:5mm;height:100%">'
        f'<div class="card" style="background:#fff;border:.2mm solid #e4dce6"><div class="lbl">A · Scènes de personnages</div><div style="display:grid;grid-template-columns:1fr 1fr;gap:2mm;margin-top:3mm">{ph(IMG + "hero-09.jpg", "100%", "60mm", pos="50% 65%", extra="border-radius:1.2mm")}{ph(IMG + "6033855.jpg", "100%", "60mm", pos="50% 40%", extra="border-radius:1.2mm")}{ph(IMG + "3767283.jpg", "100%", "60mm", pos="50% 40%", extra="border-radius:1.2mm")}{ph(HOME + "4137441.jpg", "100%", "60mm", pos="50% 45%", extra="border-radius:1.2mm")}</div><div class="note" style="margin-top:2mm">Héros, bandeaux, cartes de thématique.</div></div>'
        f'<div class="card" style="background:#fff;border:.2mm solid #e4dce6"><div class="lbl">B · Éléments décoratifs</div><div style="display:grid;grid-template-columns:1fr 1fr;gap:3mm;margin-top:3mm;align-items:center;justify-items:center">{el("01")}{el("02")}{el("03")}{el("06")}{el("07")}{el("09")}</div><div class="note" style="margin-top:2mm">Fonds, séparateurs, puces de section.</div></div>'
        f'<div class="card" style="background:#fff;border:.2mm solid #e4dce6"><div class="lbl">C · Schémas pédagogiques</div>{placeholder("À produire avec des professionnel·les : cycle menstruel, démarches de signalement, parcours d\'aide", "100%", "118mm", "diagram-3")}<div class="note" style="margin-top:2mm">Contenus validés avant publication.</div></div></div>')
    doc.section(C, 'combining', 'Combiner les types', 'Combining illustrations types',
        ['<p>On peut combiner une <b>scène</b> et des <b>éléments décoratifs</b>, ou des éléments et des rubans, mais jamais <b>photographie et illustration dans une même composition</b> (deux univers qui se concurrencent). Une illustration reste à son échelle d\'origine, sans recoloration.</p>'],
        '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:3mm;padding:4mm;height:100%">'
        + good(f'<div style="position:relative;width:100%;height:100%;background:#F9ECF4;overflow:hidden">{ph(IMG + "hero-09.jpg", pos="50% 70%")}<img src="{ELT}06.svg" style="position:absolute;right:4mm;top:4mm;height:30mm;opacity:.7" alt=""></div>', 'Scène + élément décoratif', 88, 120, '#fff', 'width:100%;height:100%')
        + good(f'<div style="position:relative;width:100%;height:100%;overflow:hidden;background:#fff">{waves(100, 120, ["#F6B8CB", "#6F2C91", "#0433C3"], 8, 22, 15)}<img src="{ELT}09.svg" style="position:absolute;left:50%;top:60%;transform:translate(-50%,-50%);height:34mm" alt=""></div>', 'Éléments + rubans', 88, 120, '#fff', 'width:100%;height:100%')
        + bad(f'<div style="position:relative;width:100%;height:100%;overflow:hidden">{ph(PHOTOS[3])}<img src="{HOME}personnage-2.png" style="position:absolute;right:3mm;bottom:3mm;height:46mm" alt=""></div>', 'Photographie + illustration', 88, 120, '#fff', 'width:100%;height:100%')
        + '</div>', visclass='tint')
    dd = lambda inner, lab, ok: (good if ok else bad)(inner, lab, 66, 56, '#fff', 'width:100%;height:100%')
    doc.section(C, 'typesguide', 'Recommandations par type', 'Illustration types guidance',
        ['<p>Chaque type a ses précautions. <b>Scènes</b> : personnages divers, expressions positives, pas d\'agression ; sans texte intégré à l\'image (un texte incrusté ne se traduit pas et n\'est pas lu par les lecteurs d\'écran). <b>Éléments</b> : en retrait, <code>aria-hidden</code>. <b>Schémas</b> : exacts, validés, avec description textuelle complète.</p>'],
        '<div style="display:grid;grid-template-columns:repeat(4,1fr);grid-template-rows:1fr;gap:3mm;padding:4mm;height:100%">'
        + dd(ph(IMG + 'hero-12.jpg', pos='50% 70%'), 'Personnages divers, expressions positives', True)
        + dd(ph(HOME + 'hero-14.jpg', pos='50% 60%'), 'Texte anglais intégré à l\'image (à éviter)', False)
        + dd(f'<img src="{ELT}01.svg" style="height:34mm" alt="">', 'Élément décoratif, discret', True)
        + dd(ph(HOME + '3995814.jpg', pos='50% 50%'), 'Mise en scène d\'agression et texte : à ne pas utiliser', False)
        + '</div>', visclass='tint')
    idx_files = [(HOME + 'hero-01.jpg', 'hero-01'), (HOME + 'hero-02.jpg', 'hero-02'), (IMG + 'hero-04.jpg', 'hero-04'), (IMG + 'hero-09.jpg', 'hero-09'), (IMG + 'hero-10.jpg', 'hero-10'), (IMG + 'hero-12.jpg', 'hero-12'), (IMG + 'hero-13.jpg', 'hero-13'), (HOME + 'hero-14.jpg', 'hero-14'),
                 (HOME + 'hero-03.jpg', 'hero-03'), (HOME + 'hero-05.jpg', 'hero-05'), (HOME + 'hero-06.jpg', 'hero-06'), (HOME + 'hero-07.jpg', 'hero-07'), (HOME + 'hero-08.jpg', 'hero-08'), (HOME + 'hero-11.jpg', 'hero-11'), (HOME + 'hero-15.jpg', 'hero-15'), (HOME + 'hero-16.jpg', 'hero-16'),
                 (IMG + '3767283.jpg', '3767283'), (IMG + '6033855.jpg', '6033855'), (HOME + '4137441.jpg', '4137441'), (HOME + '7150569.jpg', '7150569'), (IMG + 'profile-cover.jpg', 'profile-cover'), (IMG + 'sign-in.jpg', 'sign-in'), (IMG + 'about-us.jpg', 'about-us'), (HOME + 'personnage-1.png', 'personnage-1')]
    tiles = ''.join(f'<div><div style="height:24mm;background:#F9ECF4;border-radius:1.2mm;overflow:hidden">{ph(p, '100%', '100%', fit='contain')}</div><div class="note" style="margin-top:1mm">{n}</div></div>' for p, n in idx_files)
    doc.section(C, 'index', 'Index des illustrations', 'Illustration index',
        ['<p>Illustrations disponibles dans le projet (dossiers <code>images/</code> et <code>public/assets/images/</code>). Les <b>droits d\'usage de chaque fichier sont à confirmer</b> avant diffusion. Les fichiers contenant du texte ou une mise en scène d\'agression (hero-14, 3995814) sont déconseillés.</p>'],
        f'<div style="display:grid;grid-template-columns:repeat(8,1fr);gap:2.4mm;padding:4mm;height:100%;align-content:start">{tiles}</div>')
    doc.section(C, 'cropping', 'Recadrage', 'Cropping',
        ['<p>Une illustration se recadre autour de son <b>sujet</b> (personnes et visages), jamais en coupant un visage ou un geste. Le grand fichier hero-10 (3104 × 1490 px) garde son sujet à droite : on conserve cette zone dans tous les formats, quitte à décaler le point focal (<code>object-position</code>).</p>'],
        f'<div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:4mm;padding:5mm;align-items:center;height:100%">'
        f'<div><div style="aspect-ratio:16/9;overflow:hidden;border-radius:1.6mm;position:relative">{ph(IMG + "hero-10.jpg", pos="80% 50%")}<span class="ok" style="z-index:2">&#10003;</span></div><div class="note" style="margin-top:2mm">16:9 · sujet conservé à droite</div></div>'
        f'<div><div style="aspect-ratio:1;overflow:hidden;border-radius:1.6mm;position:relative">{ph(IMG + "hero-10.jpg", pos="92% 50%")}<span class="ok" style="z-index:2">&#10003;</span></div><div class="note" style="margin-top:2mm">1:1 · focale décalée</div></div>'
        f'<div><div style="aspect-ratio:1;overflow:hidden;border-radius:1.6mm;position:relative">{ph(IMG + "hero-10.jpg", pos="0% 50%")}<span class="no" style="z-index:2">&#10005;</span></div><div class="note" style="margin-top:2mm">1:1 · sujet perdu</div></div></div>')
    doc.section(C, 'applications', 'Applications', 'Applications',
        ['<p>Exemples d\'emploi : bandeau d\'accueil, page de connexion (image à gauche), état vide dans l\'application, visuel d\'actualité. Toujours accompagnées d\'un <code>alt</code> descriptif ou d\'<code>aria-hidden</code> si purement décoratives.</p>'],
        f'<div style="display:grid;grid-template-columns:1.6fr 1.2fr .9fr;gap:5mm;padding:6mm;height:100%;align-items:center">'
        f'<div style="background:#fff;border-radius:2mm;overflow:hidden;box-shadow:0 1mm 4mm rgba(0,0,0,.2);aspect-ratio:16/10;position:relative;display:flex"><div style="flex:1;padding:6mm"><div class="mt" style="font:800 14pt/1.1 Montserrat">Xomiton, votre espace safe</div><div style="font:400 7.5pt/1.5 Arial;margin:2mm 0 3mm;color:#3b3650">Apprendre, être écoutée, agir en sécurité.</div><span class="mt" style="background:#0433C3;color:#fff;font:700 7pt Montserrat;padding:2mm 5mm;border-radius:1.6mm">Rejoignez-nous</span></div><div style="flex:1;position:relative">{ph(IMG + "hero-12.jpg", pos="50% 60%")}</div></div>'
        f'<div style="background:#fff;border-radius:2mm;overflow:hidden;box-shadow:0 1mm 4mm rgba(0,0,0,.2);aspect-ratio:4/3;display:flex"><div style="flex:1;position:relative">{ph(IMG + "sign-in.jpg", pos="50% 50%")}</div><div style="flex:1;padding:5mm"><div class="mt" style="font:800 11pt Montserrat">Connexion</div><div style="height:6mm;border:.3mm solid #ccc;border-radius:1.4mm;margin-top:3mm"></div><div style="height:6mm;border:.3mm solid #ccc;border-radius:1.4mm;margin-top:2mm"></div><div style="height:7mm;background:#0433C3;border-radius:1.4mm;margin-top:3mm"></div></div></div>'
        f'<div style="background:#FBF7FA;border-radius:3mm;overflow:hidden;box-shadow:0 1mm 4mm rgba(0,0,0,.2);aspect-ratio:9/16;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:4mm;text-align:center"><div style="width:100%;height:36mm">{ph(HOME + "personnage-2.jpg", fit="contain")}</div><div class="mt" style="font:800 9pt/1.2 Montserrat;margin-top:3mm">Aucun message pour l\'instant</div></div></div>')
    doc.section(C, 'guidance', 'Recommandations', 'Guidance',
        ['<p>À faire : choisir des scènes positives, légender, respecter les proportions, garder le sujet dans le cadre. À éviter : recolorer, étirer, ajouter un contour, utiliser une illustration contenant du texte ou une agression, mélanger plusieurs styles d\'illustration sur une même page, utiliser une illustration comme seul porteur d\'une information.</p>'],
        '<div style="display:grid;grid-template-columns:repeat(4,1fr);grid-template-rows:1fr;gap:3mm;padding:4mm;height:100%">'
        + dd(ph(IMG + 'hero-09.jpg', pos='50% 70%'), 'Proportions et couleurs d\'origine', True)
        + dd(ph(IMG + 'hero-09.jpg', pos='50% 70%', extra='filter:hue-rotate(160deg) saturate(1.4)'), 'Ne pas recolorer', False)
        + dd(ph(IMG + 'hero-09.jpg', pos='50% 70%', extra='transform:scale(1.45,1)'), 'Ne pas étirer', False)
        + dd(f'<div style="display:flex;gap:2mm;width:100%;height:100%">{ph(IMG + "hero-09.jpg", "50%", "100%", pos="50% 70%")}{ph(IMG + "6033855.jpg", "50%", "100%", pos="50% 30%")}</div>', 'Pas de styles mélangés (à valider)', False)
        + '</div>', visclass='tint')

    # =============================== CHAPITRE 9 : PICTOGRAMMES ===============================
    doc.divider(ch_of(9)); C = 9
    def ic(n, s=9, c='#0433C3', extra=''):
        return icon(n, s, c, extra)
    def picto_card(n, lab, desc='', size=19, col='#0433C3', bg='#fff'):
        return (f'<div style="background:{bg};border:.2mm solid #e4dce6;border-radius:1.8mm;padding:6mm;display:flex;flex-direction:column;align-items:flex-start;justify-content:center;gap:1mm">{ic(n, size, col)}'
                f'<div class="mt" style="font:800 10.5pt/1.15 Montserrat;margin-top:3mm">{lab}</div><div style="font-size:8pt;line-height:1.4;color:#5B5670">{desc}</div></div>')
    def pgrid(items, cols=4, rows=3):
        return f'<div style="display:grid;grid-template-columns:repeat({cols},1fr);grid-template-rows:repeat({rows},1fr);gap:3mm;padding:4mm;height:100%">{"".join(picto_card(*i) for i in items)}</div>'
    U = [('shield-check', 'Sécurité', 'Protection, confidentialité'), ('shield-exclamation', 'Signaler', 'Dénonciation, signalement'), ('telephone-fill', 'Appeler', 'Contact direct'), ('geo-alt-fill', 'Lieu', 'Position, structure proche'),
         ('chat-heart', 'Écoute', 'Chat, conseil de pair·e'), ('people-fill', 'Communauté', 'Groupes, réseau'), ('book-fill', 'Ressources', 'Fiches, manuels, BD'), ('calendar-event', 'Événements', 'Ateliers, rencontres'),
         ('lock-fill', 'Confidentialité', 'Données protégées'), ('eye-slash-fill', 'Anonymat', 'Sans nom ni compte'), ('envelope-heart-fill', 'Newsletter', 'Actualités par e-mail'), ('bell-fill', 'Alertes', 'Notifications, cloche')]
    doc.section(C, 'universal', 'Pictogrammes universels', 'Universal pictograms',
        ['<p class="lead">Un jeu de douze pictogrammes pour les fonctions essentielles.</p>',
         '<p>Famille : <b>Bootstrap Icons</b> (déjà utilisée par le thème Social, licence MIT), traits arrondis, deux états (contour et plein). C\'est la <b>seule</b> bibliothèque autorisée : on ne mélange pas Font Awesome, émojis ou autres jeux. Le SOS n\'est pas un pictogramme : c\'est un bouton avec le mot « SOS ».</p>'],
        pgrid(U, 4, 3))
    rows_m = [('Logo horizontal', '', ''), ]
    sizes_html = ''.join(f'<div style="text-align:center"><div style="width:{s*0.2646*1.5+4:.1f}mm;height:{s*0.2646*1.5+4:.1f}mm;border:.25mm dashed #E30E0E;display:flex;align-items:center;justify-content:center;margin:0 auto">{ic("shield-check", s*0.2646, "#0433C3")}</div><div class="note" style="margin-top:1.5mm">{s} px</div></div>' for s in (16, 20, 24, 32, 48))
    doc.section(C, 'clear', 'Zone de protection et taille minimale', 'Clear space & minimum size',
        ['<p>Un pictogramme a une zone de protection de <b>1/4 de sa taille</b> de chaque côté. Taille minimale : <b>16 px</b> pour un pictogramme décoratif accompagné d\'un libellé, <b>24 px</b> pour un pictogramme seul (cible tactile 44 × 44 px autour). Plus petit, le trait devient illisible.</p>'],
        f'<div style="display:flex;gap:10mm;justify-content:center;align-items:center;height:100%;padding:8mm">{sizes_html}<div style="width:70mm;margin-left:8mm"><div class="lbl">Cible tactile</div><div style="margin-top:3mm;display:flex;align-items:center;justify-content:center;width:44px;height:44px;border:.3mm dashed #0433C3;border-radius:2mm;width:11.6mm;height:11.6mm">{ic("telephone-fill", 6.3)}</div><div class="note" style="margin-top:2mm">44 × 44 px minimum autour d\'un pictogramme seul</div></div></div>')
    H = [('heart-pulse', 'Santé', 'Soins, bien-être'), ('droplet-fill', 'Hygiène menstruelle', 'Do Glegbe'), ('capsule', 'Médicaments', 'Traitements, contraception'), ('hospital', 'Centre de santé', 'Structure de soin'),
         ('bandaid', 'Premiers soins', 'Urgence médicale'), ('clipboard2-pulse', 'Consultation', 'Rendez-vous, suivi'), ('person-hearts', 'Relations', 'Sexualité, consentement'), ('thermometer-half', 'Symptômes', 'Repérer, comprendre')]
    doc.section(C, 'health', 'Thématique : santé et SSR', 'Summer sports',
        ['<p>Famille de pictogrammes pour la <b>santé sexuelle et reproductive</b> (SSR) et la santé menstruelle. Même trait, même taille, même couleur que le jeu universel.</p>',
         '<p class="note">Cette page remplace la rubrique « Summer sports » du sommaire d\'origine, propre au sport : les trois pages suivantes déclinent les pictogrammes par thématique Xomiton.</p>'],
        pgrid(H, 4, 2))
    R = [('bank', 'Institutions', 'Justice, mairie, commissariat'), ('hammer', 'Droit', 'Recours, procédure'), ('file-earmark-text', 'Textes de loi', 'Codes et lois du Bénin'), ('shield-lock', 'Protection', 'Mesures de sécurité'),
         ('person-check', 'Accompagnement', 'Juriste, assistance'), ('journal-bookmark', 'Manuel', 'Droits expliqués'), ('flag', 'Signalement', 'Déclarer un fait'), ('incognito', 'Anonymat', 'Témoigner sans être connue')]
    doc.section(C, 'rights', 'Thématique : droits et protection', 'Winter sports',
        ['<p>Famille pour les <b>droits humains</b>, la <b>protection</b> et la <b>justice</b> : lois, procédures, accompagnement juridique, signalement. Aucun pictogramme de balance de la justice n\'existe dans la bibliothèque : on emploie « Institutions » et « Droit ».</p>'],
        pgrid(R, 4, 2))
    Cm = [('people', 'Groupe', 'Entraide, discussion'), ('chat-square-text', 'Forum', 'Échanges thématiques'), ('calendar-heart', 'Rencontre', 'Atelier, événement'), ('megaphone', 'Campagne', 'Sensibilisation'),
          ('person-raised-hand', 'Prendre la parole', 'Question, témoignage'), ('emoji-smile', 'Humeur', 'Réaction, soutien'), ('hand-thumbs-up', 'Soutien', 'Réaction positive'), ('balloon-heart', 'Célébration', 'Fête, victoire')]
    doc.section(C, 'community', 'Thématique : communauté et événements', 'Demonstration sports',
        ['<p>Famille pour la <b>vie de la communauté</b> : groupes, forums, ateliers, campagnes, réactions. Les pictogrammes d\'humeur (émoji) restent des <b>réactions</b> de l\'interface, jamais des icônes de navigation.</p>'],
        pgrid(Cm, 4, 2))
    F = [('search', 'Rechercher'), ('funnel', 'Filtrer'), ('download', 'Télécharger'), ('share', 'Partager'), ('pencil', 'Modifier'), ('trash', 'Supprimer'), ('plus-circle', 'Ajouter'), ('check-circle', 'Valider'),
         ('x-circle', 'Fermer'), ('info-circle', 'Information'), ('exclamation-triangle', 'Attention'), ('gear', 'Réglages'), ('envelope', 'Message'), ('house', 'Accueil'), ('person-circle', 'Profil'), ('list', 'Menu')]
    cells = ''.join(f'<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:3mm;padding:3mm;border:.2mm solid #e4dce6;border-radius:1.8mm;background:#fff">{ic(n, 13, "#1B1630")}<div style="font:700 8.6pt Arial">{l}</div></div>' for n, l in F)
    doc.section(C, 'functional', 'Pictogrammes fonctionnels', 'Functional pictograms',
        ['<p>Les pictogrammes d\'interface (actions, navigation, états) sont en <b>encre</b> et s\'accompagnent d\'un libellé visible. Un pictogramme <b>seul</b> exige un nom accessible (<code>aria-label</code> ou texte masqué visuellement) et une infobulle.</p>'],
        f'<div style="display:grid;grid-template-columns:repeat(8,1fr);grid-template-rows:repeat(2,1fr);gap:3mm;padding:5mm;height:100%">{cells}</div>')
    pairs = [('heart', 'heart-fill', 'J\'aime'), ('bell', 'bell-fill', 'Alertes'), ('bookmark', 'bookmark-fill', 'Enregistrer'), ('house', 'house-fill', 'Accueil'), ('person', 'person-fill', 'Profil'), ('star', 'star-fill', 'Favori')]
    cells = ''.join(f'<div style="text-align:center"><div style="display:flex;gap:6mm;justify-content:center;padding:9mm;background:#fff;border:.2mm solid #e4dce6;border-radius:1.8mm">{ic(a, 19, "#0433C3")}{ic(b, 19, "#0433C3")}</div><div class="note" style="margin-top:2mm;font-size:8pt">{l} : contour / plein</div></div>' for a, b, l in pairs)
    doc.section(C, 'versions', 'Versions', 'Versions',
        ['<p>Deux versions : <b>contour</b> (par défaut, état au repos) et <b>plein</b> (état actif ou sélectionné). On ne mélange pas les deux à l\'identique dans une même barre : l\'élément actif est plein, les autres en contour. Le changement d\'état s\'accompagne d\'un changement de couleur ou d\'un texte, jamais de la forme seule.</p>'],
        f'<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:5mm;padding:8mm;align-content:center;height:100%">{cells}</div>')
    bgs = [('#0433C3', '#FFFFFF', 'Cercle bleu', '50%'), ('#EBEFFA', '#021C6B', 'Cercle bleu doux', '50%'), ('#FDECEC', '#9F0A0A', 'Carré arrondi rouge doux', '3mm'), ('#1B1630', '#FFFFFF', 'Cercle encre', '50%'), ('#F6F2F7', '#1B1630', 'Carré neutre', '3mm'), ('#E30E0E', '#FFFFFF', 'Cercle rouge (attention)', '50%')]
    cells = ''.join(f'<div style="text-align:center"><div style="width:36mm;height:36mm;margin:0 auto;background:{b};border-radius:{r};display:flex;align-items:center;justify-content:center">{ic("shield-check", 17, f)}</div><div class="mt" style="font:700 8.4pt Montserrat;margin-top:3mm">{n}</div><div class="note" style="font-size:7pt">{f} sur {b} : {cr(f, b)}:1</div></div>' for b, f, n, r in bgs)
    doc.section(C, 'backgrounds', 'Fonds', 'Backgrounds',
        ['<p>Un pictogramme peut se poser dans un <b>conteneur</b> (cercle ou carré arrondi) pour gagner en présence. Le contraste du pictogramme sur son conteneur est au moins de <b>3:1</b> (composant d\'interface). Le conteneur rouge est réservé à l\'attention (alerte, SOS).</p>'],
        f'<div style="display:grid;grid-template-columns:repeat(6,1fr);gap:4mm;padding:8mm;align-content:center;height:100%">{cells}</div>')
    cols_i = [('#0433C3', 'Bleu : action, lien'), ('#1B1630', 'Encre : neutre, interface'), ('#FFFFFF', 'Blanc : sur fond bleu, encre ou rouge'), ('#E30E0E', 'Rouge : attention seulement'), ('#0B7A4B', 'Vert : succès'), ('#B84B00', 'Orange : élevé')]
    cells = ''.join(f'<tr><td><div style="width:11mm;height:11mm;background:{"#1B1630" if c == "#FFFFFF" else "#fff"};border:.2mm solid #e4dce6;border-radius:1.2mm;display:flex;align-items:center;justify-content:center">{ic("shield-check", 6, c)}</div></td><td><b>{n}</b></td><td>{c}</td><td>{cr(c, "#FFFFFF") if c != "#FFFFFF" else cr(c, "#1B1630")}:1</td></tr>' for c, n in cols_i)
    doc.section(C, 'colour', 'Couleur des pictogrammes', 'Pictogram Colour',
        ['<p>Un pictogramme n\'a qu\'<b>une seule couleur</b>, tirée de la palette fonctionnelle. Contraste d\'au moins 3:1 avec le fond (critère WCAG pour les composants d\'interface). La couleur seule ne porte pas le sens : le pictogramme est toujours accompagné d\'un libellé ou d\'un nom accessible.</p>'],
        f'<div style="padding:7mm 9mm"><table class="t" style="font-size:9pt"><tr><th>Aperçu</th><th>Couleur et usage</th><th>Valeur</th><th>Contraste</th></tr>{cells}</table></div>')
    grid_svg = ('<svg viewBox="0 0 48 48" style="width:96mm;height:96mm;background:#fff"><g stroke="#E4DCE6" stroke-width=".15">' + ''.join(f'<path d="M{i*2} 0V48M0 {i*2}H48"/>' for i in range(25)) + '</g>'
                '<rect x="4" y="4" width="40" height="40" fill="none" stroke="#0433C3" stroke-width=".25" stroke-dasharray=".8 .6"/><rect x="2" y="2" width="44" height="44" fill="none" stroke="#E30E0E" stroke-width=".25"/>'
                '<circle cx="24" cy="24" r="16" fill="none" stroke="#E4DCE6" stroke-width=".2"/><line x1="24" y1="2" x2="24" y2="46" stroke="#E4DCE6" stroke-width=".2"/><line x1="2" y1="24" x2="46" y2="24" stroke="#E4DCE6" stroke-width=".2"/></svg>')
    doc.section(C, 'grid', 'Alignement et grille', 'Alignment and grid',
        ['<p>Chaque pictogramme s\'inscrit dans une grille de <b>24 px</b>. La zone utile fait 20 px (marge de 2 px), le trait mesure 1,5 à 2 px, les angles sont arrondis. Les pictogrammes se centrent sur la grille, pas sur leur contenu. Dans un texte, ils s\'alignent sur la <b>ligne de base</b> au centre de la hauteur des capitales.</p>'],
        f'<div style="display:flex;gap:14mm;align-items:center;justify-content:center;height:100%;padding:6mm"><div style="position:relative;width:96mm;height:96mm">{grid_svg}<div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center">{ic("shield-check", 62, "#0433C3", "opacity:.88")}</div></div>'
        '<div style="width:80mm"><div class="lbl">Légende</div><div style="margin-top:3mm;font-size:8pt;line-height:1.9"><span style="display:inline-block;width:6mm;border-top:.4mm solid #E30E0E;vertical-align:middle;margin-right:2mm"></span>Grille 24 px (cadre complet)<br><span style="display:inline-block;width:6mm;border-top:.4mm dashed #0433C3;vertical-align:middle;margin-right:2mm"></span>Zone utile 20 px<br><span style="display:inline-block;width:6mm;border-top:.4mm solid #E4DCE6;vertical-align:middle;margin-right:2mm"></span>Repères de centrage<br><br><b>Trait</b> : 1,5 à 2 px · <b>Angles</b> : arrondis · <b>Pas d\'effet</b></div></div></div>')
    doc.section(C, 'containers', 'Retirer les conteneurs', 'Removing containers',
        ['<p>Dans les interfaces denses (barres d\'outils, tableaux, listes), on <b>retire le conteneur</b> et on garde le pictogramme nu pour alléger l\'écran. On le garde dans les zones d\'accueil, les cartes de thématique et les états vides.</p>'],
        '<div style="display:grid;grid-template-columns:1fr 1fr;gap:5mm;padding:6mm;height:100%">'
        '<div class="card" style="background:#F6F2F7"><div class="lbl">Avec conteneurs : zones d\'accueil</div><div style="display:flex;gap:6mm;margin-top:10mm;justify-content:center">'
        + ''.join(f'<div style="text-align:center"><div style="width:20mm;height:20mm;border-radius:50%;background:#EBEFFA;display:flex;align-items:center;justify-content:center">{ic(n, 10, "#0433C3")}</div><div class="mt" style="font:700 7.4pt Montserrat;margin-top:2mm">{l}</div></div>' for n, l in (('book-fill', 'Ressources'), ('people-fill', 'Communauté'), ('shield-check', 'Sécurité')))
        + '</div></div><div class="card" style="background:#F6F2F7"><div class="lbl">Sans conteneur : barre d\'outils dense</div><div style="margin-top:10mm;background:#fff;border:.2mm solid #e4dce6;border-radius:1.6mm;padding:3mm 5mm;display:flex;gap:7mm;align-items:center">'
        + ''.join(f'<div style="display:flex;align-items:center;gap:1.6mm;font:700 7.6pt Arial">{ic(n, 4.6, "#1B1630")}{l}</div>' for n, l in (('search', 'Rechercher'), ('funnel', 'Filtrer'), ('download', 'Exporter'), ('printer', 'Imprimer')))
        + '</div></div></div>')
    doc.section(C, 'applications', 'Applications', 'Applications',
        ['<p>Exemples : barre d\'onglets de l\'application (cinq entrées au plus, le SOS au centre), liste d\'annuaire, carte de ressource.</p>'],
        f'<div style="display:flex;gap:12mm;align-items:center;justify-content:center;height:100%;padding:6mm">'
        f'<div style="width:62mm;height:128mm;background:#FBF7FA;border-radius:6mm;border:.5mm solid #1B1630;position:relative;overflow:hidden;box-shadow:0 1mm 5mm rgba(0,0,0,.2)"><div style="padding:5mm"><div class="mt" style="font:800 11pt Montserrat">Annuaire</div>'
        + ''.join(f'<div style="display:flex;gap:3mm;align-items:center;background:#fff;border-radius:2mm;padding:2.6mm;margin-top:3mm">{ic(n, 6, "#0433C3")}<div><div class="mt" style="font:800 7.4pt Montserrat">{t}</div><div style="font-size:6pt;color:#5B5670">{d}</div></div></div>' for n, t, d in (('telephone-fill', 'Psychologue', 'Cotonou · 9 h-17 h'), ('hammer', 'Juriste', 'Porto-Novo · 24/7'), ('hospital', 'Centre de santé', 'Abomey-Calavi')))
        + '</div><div style="position:absolute;left:0;right:0;bottom:0;height:19mm;background:#fff;border-top:.2mm solid #e4dce6;display:flex;justify-content:space-around;align-items:center;padding:0 3mm">'
        + ''.join(f'<div style="text-align:center;color:{"#0433C3" if a else "#5B5670"}">{ic(n, 5.4, "currentColor")}<div style="font:700 5.4pt Arial;margin-top:1mm">{l}</div></div>' for n, l, a in (('house-fill', 'Accueil', 1), ('book', 'Ressources', 0)))
        + '<div class="mt" style="width:15mm;height:15mm;border-radius:50%;background:#E30E0E;color:#fff;display:flex;align-items:center;justify-content:center;font:800 8pt Montserrat;margin-top:-8mm;box-shadow:0 1mm 3mm rgba(0,0,0,.3)">SOS</div>'
        + ''.join(f'<div style="text-align:center;color:#5B5670">{ic(n, 5.4, "currentColor")}<div style="font:700 5.4pt Arial;margin-top:1mm">{l}</div></div>' for n, l in (('chat-dots-fill', 'Messages'), ('person-circle', 'Profil')))
        + '</div></div><div style="width:80mm"><div class="lbl">Règles de la barre d\'onglets</div><ul style="font-size:8.4pt;line-height:1.7;padding-left:4mm;margin-top:2mm"><li>Cinq entrées au plus</li><li>Actif : pictogramme plein et couleur bleue</li><li>SOS au centre, 15 mm, jamais masqué</li><li>Libellé toujours visible</li><li>Cible tactile 44 × 44 px</li></ul></div></div>')
    doc.section(C, 'guidance', 'Recommandations', 'Guidance',
        ['<p>Une bibliothèque, un trait, une couleur par pictogramme, un libellé toujours. Jamais d\'émoji comme icône de navigation, jamais de pictogramme étiré, jamais de pictogramme seul sans nom accessible, jamais de sens porté par la couleur seule.</p>'],
        '<div style="display:grid;grid-template-columns:repeat(4,1fr);grid-template-rows:1fr;gap:3mm;padding:4mm;height:100%">'
        + dd(f'<div style="display:flex;gap:3mm;align-items:center">{ic("shield-check", 10)}<div class="mt" style="font:700 8pt Montserrat">Sécurité</div></div>', 'Pictogramme + libellé', True)
        + dd('<div style="display:flex;gap:3mm;align-items:center;font-size:18pt">🔒 <span class="mt" style="font:700 8pt Montserrat">Sécurité</span></div>', 'Émoji comme icône', False)
        + dd(f'<div style="transform:scale(2,.8)">{ic("shield-check", 10)}</div>', 'Pictogramme étiré', False)
        + dd(f'<div style="display:flex;gap:3mm">{ic("shield-check", 9, "#0433C3")}{ic("telephone-fill", 9, "#E30E0E")}{ic("book-fill", 9, "#B84B00")}{ic("people-fill", 9, "#0B7A4B")}</div>', 'Plusieurs couleurs sans raison', False)
        + '</div>', visclass='tint')


def fit_contain(p):
    return '100%'
