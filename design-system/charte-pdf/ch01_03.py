# -*- coding: utf-8 -*-
from kit import *
from structure import CHAPTERS

def ch_of(n): return [c for c in CHAPTERS if c[0] == n][0]

def good(content, label, w=60, h=40, bg='#fff', extra=''):
    return '<div class="frame good" style="width:%smm;height:%smm;background:%s;%s">%s<span class="ok">&#10003;</span><small>%s</small></div>' % (w, h, bg, extra, content, label)

def bad(content, label, w=60, h=40, bg='#fff', extra='', lc=None):
    return '<div class="frame bad" style="width:%smm;height:%smm;background:%s;%s">%s<span class="no">&#10005;</span><small style="%s">%s</small></div>' % (w, h, bg, extra, content, ('color:' + lc) if lc else '', label)

def build(doc):
    # =============================== CHAPITRE 1 ===============================
    doc.divider(ch_of(1))
    C = 1
    pill = lambda ic, t, d, col: ('<div class="card" style="flex:1"><div style="width:11mm;height:11mm;border-radius:50%%;background:%s;color:#fff;display:flex;align-items:center;justify-content:center">%s</div>'
                                  '<div class="mt" style="font:800 10.5pt/1.15 Montserrat;margin:3mm 0 1.5mm">%s</div><div style="font-size:7.4pt;line-height:1.5;color:#3b3650">%s</div></div>') % (col, icon(ic, 6, '#fff'), t, d)
    doc.section(C, 'brand', 'La marque Xomiton', 'The Xomiton brand',
        ['<p class="lead">Soutenir, informer, protéger : un espace sûr, chaleureux et clair où chaque voix peut s\'exprimer.</p>',
         '<p>Xomiton est un <b>espace safe d\'apprentissage et d\'appui holistique</b> pour les filles, les femmes et les personnes LBTQ+. Il réunit de l\'information fiable, un réseau d\'entraide entre pair·es, des professionnel·les (juridique, psychologique, santé) et un dispositif de signalement et d\'alerte.</p>'
         '<p>La marque tient une promesse simple : <b>tu n\'es pas seule, ton anonymat est protégé, des personnes de confiance t\'écoutent</b>. Tout ce qui est dessiné, écrit ou programmé pour Xomiton doit pouvoir être reconnu comme venant de la même maison.</p>'],
        '<div style="display:flex;height:100%%"><div style="flex:1.05;padding:7mm;display:flex;flex-direction:column;gap:4mm;justify-content:center">'
        '<div class="lbl">Trois missions (page À propos du site)</div><div style="display:flex;gap:4mm">%s%s%s</div></div>'
        '<div style="flex:.95;position:relative">%s</div></div>' % (
            pill('people-fill', 'Conseil par et pour des pair·es', 'Des conseiller·ère·s issu·e·s de la communauté écoutent, soutiennent et orientent vers une aide spécialisée.', BRAND['blue']),
            pill('shield-fill-check', 'Antenne de signalement', 'Signaler une violence ou une discrimination pour être soutenue et orientée vers les bons services.', BRAND['red']),
            pill('bar-chart-line-fill', 'Visibiliser et prévenir', 'Chaque signalement, même anonyme, documente la réalité et améliore la prévention.', BRAND['violet']),
            img(HOME + '07.jpg', '100%', '100%', pos='45% 40%')), visclass='short')

    col4 = lambda t, h, p: '<div style="flex:1;padding:6mm 5mm;border-left:.25mm solid #d8cfdc"><div class="lbl">%s</div><div class="mt" style="font:800 12pt/1.2 Montserrat;margin:3mm 0">%s</div><div style="font-size:7.6pt;line-height:1.55;color:#3b3650">%s</div></div>' % (t, h, p)
    doc.section(C, 'framework', 'Cadre de marque', 'Brand framework',
        ['<p class="lead">Un cadre conçu pour donner un but à chaque expression de la marque.</p>'],
        '<div style="display:flex;height:100%%;padding:4mm 6mm">%s%s%s%s</div><div class="dummy">Formulations à valider avec Xomiton</div>' % (
            col4('Raison d\'être', 'Offrir un espace où chacune peut apprendre, être écoutée et agir en sécurité.', 'Soutenir, informer, protéger : trois verbes qui guident le contenu, le service et le design. Le produit existe pour que la personne en difficulté trouve de l\'aide plus vite, pas pour qu\'elle admire une interface.'),
            col4('Vision', 'Une communauté où personne n\'affronte seule la violence ou la discrimination.', 'Un réseau de pair·es, de professionnel·les et de lieux sûrs, accessible depuis un téléphone, même avec peu de données et peu de réseau.'),
            col4('Positionnement', 'Un espace safe d\'apprentissage et d\'appui holistique.', 'Information fiable, pair-aidance, orientation vers des professionnel·les, signalement et alerte SOS : une réponse complète plutôt qu\'un service isolé.'),
            col4('Valeurs', 'Sécurité · Bienveillance · Confidentialité · Inclusion · Pair-aidance · Droits humains · Rigueur', 'Chaque décision de design est testée contre ces sept valeurs. En cas de conflit, la sécurité de la personne l\'emporte.')),
        visclass='')

    carac = [('heart-fill', 'Chaleureuse', 'Fond blanc cassé rosé, illustrations plates et inclusives, coins arrondis, ton direct et doux.'),
             ('shield-lock-fill', 'Sûre', 'Peu d\'éléments par écran, hiérarchie nette, bleu stable pour agir, rouge réservé à l\'attention.'),
             ('chat-square-text-fill', 'Claire', 'Titres Montserrat marqués, texte Arial lisible, phrases courtes, aucun jargon.'),
             ('people-fill', 'Inclusive', 'Personnages et photographies divers, aucun stéréotype, accessibilité WCAG 2.2 AA.'),
             ('eye-slash-fill', 'Discrète', 'Mode discret, titres neutres possibles, rien de sensible à l\'écran de verrouillage.')]
    rows = ''.join('<div style="display:flex;gap:3.5mm;padding:2.6mm 0;border-bottom:.2mm solid #e4dce6"><div style="width:9mm;height:9mm;flex:none;border-radius:50%%;background:%s;display:flex;align-items:center;justify-content:center">%s</div><div><div class="mt" style="font:800 9.4pt/1.1 Montserrat">%s</div><div style="font-size:7.3pt;line-height:1.45;color:#3b3650;margin-top:.8mm">%s</div></div></div>' % (c, icon(i, 5, '#fff'), t, d)
                   for (i, t, d), c in zip(carac, [BRAND['rose'], BRAND['blue'], BRAND['sky'], BRAND['violet'], BRAND['ink']]))
    doc.section(C, 'characteristics', 'Caractéristiques de la marque', 'Brand characteristics',
        ['<p>Cinq qualités se traduisent en règles visuelles. Elles s\'appliquent à tous les supports : site, application, rapports, réseaux sociaux, impression.</p>'],
        '<div style="display:flex;height:100%%"><div style="flex:1.1;padding:3mm 7mm">%s</div>'
        '<div style="flex:1;display:grid;grid-template-columns:1fr 1fr;grid-template-rows:1fr 1fr;gap:1.5mm;padding:1.5mm">%s%s%s%s</div></div>' % (
            rows, img(HOME + '01.png', '100%', '100%'), img(IMG + '04.jpg', '100%', '100%'), img(HOME + '03.jpg', '100%', '100%'), img(HOME + '07.jpg', '100%', '100%')))

    tiles = [('Symbole', 'rosace-couleur', 2, 'full'), ('Couleur', None, 3, 'xcolour'), ('Logos', 'logo', 4, 'tier1'), ('Typographie', 'type', 5, 'headline'),
             ('Éléments graphiques', 'arcs', 6, 'field'), ('Photographie', 'photo', 7, 'diversity'), ('Illustrations', 'illus', 8, 'concept'), ('Pictogrammes', 'picto', 9, 'universal'),
             ('Infographies', 'chart', 10, 'highlight'), ('Composition', 'comp', 11, 'grids')]
    def tilevis(kind):
        if kind == 'rosace-couleur': return sym('rosace-couleur', 22)
        if kind is None: return '<div style="display:flex;gap:1.5mm"><div style="width:9mm;height:22mm;background:#0433C3;border-radius:1mm"></div><div style="width:9mm;height:22mm;background:#E30E0E;border-radius:1mm"></div><div style="width:9mm;height:22mm;background:#1B1630;border-radius:1mm"></div><div style="width:9mm;height:22mm;background:#FBF7FA;border:.2mm solid #ccc;border-radius:1mm"></div></div>'
        if kind == 'logo': return logo_w('couleur', 40)
        if kind == 'type': return '<span class="mt" style="font:800 40pt/1 Montserrat">Aa</span><span style="font:400 40pt/1 Arial;margin-left:2mm">Aa</span>'
        if kind == 'arcs': return arcs_mini()
        if kind == 'photo': return img(HOME + '02.png', '44mm', '24mm')
        if kind == 'illus': return img(IMG + 'hero-09.jpg', '44mm', '24mm', pos='50% 60%')
        if kind == 'picto': return '<div style="display:flex;gap:3mm;color:#0433C3">%s%s%s%s</div>' % tuple(icon(n, 9) for n in ('shield-check', 'geo-alt-fill', 'telephone-fill', 'book-fill'))
        if kind == 'chart': return '<div style="display:flex;gap:1.6mm;align-items:flex-end;height:22mm"><div style="width:6mm;height:10mm;background:#0433C3"></div><div style="width:6mm;height:16mm;background:#6F2C91"></div><div style="width:6mm;height:22mm;background:#E30E0E"></div><div style="width:6mm;height:13mm;background:#0B7A4B"></div></div>'
        if kind == 'comp': return '<div style="width:42mm;height:24mm;border:.3mm solid #1B1630;display:grid;grid-template-columns:repeat(6,1fr);gap:1mm;padding:1mm"><div style="background:#E4DCE6;grid-column:span 2"></div><div style="background:#E4DCE6;grid-column:span 4"></div></div>'
    grid = ''.join('<div class="card" style="height:42mm;position:relative;display:flex;flex-direction:column;justify-content:space-between"><div class="lbl">Chapitre %d · p. [[PN:%d.%s]]</div><div style="align-self:center">%s</div><div class="mt" style="font:800 9pt Montserrat">%s</div></div>' % (n, n, s, tilevis(k), t) for t, k, n, s in tiles)
    doc.section(C, 'elements', 'Éléments du système', 'System elements',
        ['<p class="lead">Dix familles d\'éléments forment un système cohérent.</p>',
         '<p>Chaque famille est décrite dans un chapitre de cette charte. Les fichiers correspondants (jetons de design, logos, feuille de style, composants) se trouvent dans le dossier <b>design-system</b> du projet.</p>'],
        '<div style="display:grid;grid-template-columns:repeat(5,1fr);gap:3mm;padding:3.5mm;height:100%%">%s</div>' % grid, visclass='nobg')

    # =============================== CHAPITRE 2 ===============================
    doc.divider(ch_of(2))
    C = 2
    anat = ('<div style="display:flex;gap:6mm;font-size:6.8pt;line-height:1.4;margin-top:8mm">'
            '<div style="flex:1"><i style="display:inline-block;width:3mm;height:3mm;background:#0433C3;border-radius:50%"></i> <b>Le mot « xomiton »</b><br>Lettres arrondies et épaisses, en bleu : la confiance, la stabilité.</div>'
            '<div style="flex:1"><i style="display:inline-block;width:3mm;height:3mm;background:#E30E0E;border-radius:50%"></i> <b>La rosace (premier « o »)</b><br>Quatre formes en tourbillon qui se tiennent : des personnes réunies. <span class="note">Lecture proposée, à valider.</span></div>'
            '<div style="flex:1"><i style="display:inline-block;width:3mm;height:3mm;background:#E30E0E;border-radius:50%"></i> <b>Le symbole de genre (second « o »)</b><br>Cercle, flèche et croix réunis : la diversité des identités de genre. <span class="note">Lecture proposée, à valider.</span></div></div>')
    doc.section(C, 'full', 'Symbole Xomiton : couleur', 'Full colour',
        ['<p>Le logo en couleur est la version préférée. Elle associe le <b>bleu primaire</b> (#0433C3) et le <b>rouge secondaire</b> (#E30E0E) sur fond clair.</p>'
         '<p>Le logo est un dessin : il n\'est jamais recomposé dans une police. Il se compose du mot « xomiton », d\'une rosace rouge à quatre formes qui remplace le premier « o », et d\'un symbole rouge qui remplace le second.</p>',
         '<p><b>Fichiers.</b> SVG (vectoriel, à privilégier) et PNG haute définition à fond transparent : 6000 px de large pour le logo horizontal, 4000 px pour le vertical, 3000 px pour les symboles, 2048 px pour les icônes d\'application.</p>'
         '<p class="note">Le dessin vectoriel a été reconstitué à partir du seul fichier disponible (logo.png, 240 × 66 px). Il reste à faire valider par la personne qui a conçu le logo ou à remplacer par son fichier source.</p>'],
        '<div style="height:100%%;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:6mm">%s%s</div>' % (logo_w('couleur', 170), anat))
    mono = lambda v, bgc, lab: '<div class="frame" style="flex:1;background:%s;height:100%%;border:0;position:relative;display:flex;align-items:center;justify-content:center">%s<small style="color:%s;opacity:.85;left:4mm">%s</small></div>' % (bgc, logo_w(v, 76), '#fff' if bgc in ('#0433C3', '#1B1630') else '#5B5670', lab)
    doc.section(C, 'mono', 'Symbole Xomiton : monochrome', 'Monochrome',
        ['<p>Les versions monochromes servent quand une seule couleur est disponible : tampon, gravure, fax, broderie, impression une couleur, filigrane.</p>'
         '<p>Trois teintes seulement : <b>encre</b> (#1B1630), <b>bleu</b> (#0433C3) et <b>blanc</b>. Le blanc se place sur le bleu, l\'encre ou une photographie voilée.</p>'],
        '<div style="display:grid;grid-template-columns:1fr 1fr;grid-template-rows:1fr 1fr;height:100%%;gap:1mm">%s%s%s%s</div>' % (
            mono('mono-encre', '#FBF7FA', 'Monochrome encre, sur fond clair'), mono('mono-bleu', '#FFFFFF', 'Monochrome bleu, sur fond blanc'),
            mono('mono-blanc', '#0433C3', 'Monochrome blanc, sur bleu'), mono('mono-blanc', '#1B1630', 'Monochrome blanc, sur encre')))

    clear = ('<div style="height:100%%;display:flex;align-items:center;justify-content:space-around;padding:6mm">'
             '<div style="position:relative;width:150mm;height:60mm;display:flex;align-items:center;justify-content:center">'
             '<div style="position:absolute;inset:0;border:.3mm dashed #E30E0E"></div>'
             '<div style="position:absolute;left:11mm;right:11mm;top:11mm;bottom:11mm;border:.3mm solid #0433C3"></div>'
             '<div style="position:absolute;left:0;top:50%%;width:11mm;height:0;border-top:.3mm solid #E30E0E"></div><span class="mt" style="position:absolute;left:3.2mm;top:calc(50%% - 6mm);font-size:8pt;font-weight:800;color:#E30E0E">x</span>'
             '<div style="position:absolute;right:0;top:50%%;width:11mm;border-top:.3mm solid #E30E0E"></div><span class="mt" style="position:absolute;right:3.2mm;top:calc(50%% - 6mm);font-size:8pt;font-weight:800;color:#E30E0E">x</span>'
             '<div style="position:absolute;top:0;left:50%%;height:11mm;border-left:.3mm solid #E30E0E"></div><span class="mt" style="position:absolute;top:3.4mm;left:calc(50%% + 1.6mm);font-size:8pt;font-weight:800;color:#E30E0E">x</span>'
             '<div style="position:absolute;bottom:0;left:50%%;height:11mm;border-left:.3mm solid #E30E0E"></div><span class="mt" style="position:absolute;bottom:3.4mm;left:calc(50%% + 1.6mm);font-size:8pt;font-weight:800;color:#E30E0E">x</span>'
             '%s</div>'
             '<div style="width:92mm"><div class="lbl">Tailles minimales</div>'
             '<div style="display:flex;align-items:flex-end;gap:7mm;margin:3mm 0 5mm"><div>%s<div class="note" style="margin-top:1.5mm">20 mm · 96 px</div></div><div>%s<div class="note" style="margin-top:1.5mm">30 mm · 144 px</div></div><div>%s<div class="note" style="margin-top:1.5mm">6 mm · 24 px</div></div></div>'
             '<table class="t"><tr><th>Élément</th><th>Impression</th><th>Écran</th></tr><tr><td>Logo horizontal</td><td>20 mm</td><td>96 px de large</td></tr><tr><td>Logo vertical</td><td>14 mm</td><td>64 px de large</td></tr><tr><td>Rosace seule</td><td>6 mm</td><td>24 px</td></tr></table></div></div>') % (
        logo_w('couleur', 128), logo_w('couleur', 20), logo_w('couleur', 30), sym('rosace-couleur', 6))
    doc.section(C, 'isolation', 'Isolation et taille minimale', 'Isolation and minimum size',
        ['<p>Une <b>zone de protection</b> libre de tout élément entoure le logo. Sa mesure <b>x</b> est la hauteur de la rosace rouge (environ un tiers de la hauteur totale du logo, soit un peu plus de la moitié du mot). Aucun texte, bord de page, image ou autre logo ne pénètre dans cette zone.</p>'],
        clear + '', variant='')
    cr_blue_white = cr('#0433C3', '#FFFFFF'); cr_white_blue = cr('#FFFFFF', '#0433C3'); cr_white_ink = cr('#FFFFFF', '#1B1630'); cr_dark = cr('#FF6B6B', '#0E1020')
    def bgtile(v, bgc, lab, ratio, fg='#5B5670'):
        return '<div class="frame" style="background:%s;border:.25mm solid #d8cfdc;height:100%%;position:relative">%s<small style="color:%s;left:4mm">%s<br><b>Contraste lettres : %s</b></small></div>' % (bgc, logo_w(v, 62), fg, lab, ratio)
    doc.section(C, 'backgrounds', 'Fonds', 'Backgrounds',
        ['<p>Le logo se pose sur six fonds : blanc, fond de page rosé, bleu Xomiton, encre, violet et photographie voilée. Sur fond sombre, on utilise la <b>variante sombre</b> : lettres blanches, rosace et symbole en rouge clair (#FF6B6B), car le bleu et le rouge de marque ne contrastent pas assez sur le noir.</p>'
         '<p>Le logo en couleur ne se pose jamais sur le rouge, ni sur un fond coloré saturé.</p>'],
        '<div style="display:grid;grid-template-columns:repeat(3,1fr);grid-template-rows:1fr 1fr;gap:1mm;height:100%%">%s%s%s%s%s%s</div>' % (
            bgtile('couleur', '#FFFFFF', 'Blanc : logo couleur', '%s:1 (bleu)' % cr_blue_white),
            bgtile('couleur', '#FBF7FA', 'Fond de page : logo couleur', '%s:1 (bleu)' % cr('#0433C3', '#FBF7FA')),
            bgtile('mono-blanc', '#0433C3', 'Bleu Xomiton : logo blanc', '%s:1' % cr_white_blue, '#fff'),
            bgtile('sombre', '#0E1020', 'Fond sombre : variante sombre', '%s:1 (rosace)' % cr_dark, '#bbb'),
            bgtile('mono-blanc', '#6F2C91', 'Violet : logo blanc', '%s:1' % cr('#FFFFFF', '#6F2C91'), '#fff'),
            bgtile('mono-blanc', '#1B1630', 'Encre : logo blanc', '%s:1' % cr_white_ink, '#fff')))
    photo = lambda f, scrim, v, w=70, pos='center': ('<div style="position:relative;height:100%%;overflow:hidden">%s<div style="position:absolute;inset:0;background:%s"></div><div style="position:absolute;left:50%%;top:50%%;transform:translate(-50%%,-50%%)">%s</div></div>' % (img(f, '100%', '100%', pos=pos), scrim, logo_w(v, w)))
    doc.section(C, 'photos', 'Le symbole sur les photographies', 'Xomiton symbol on photos',
        ['<p>Sur une photographie, le logo blanc se pose sur un <b>voile</b> encre (opacité 55 à 70 %) qui garantit au moins 4,5:1 sous le logo, ou sur une zone calme de l\'image. Le logo en couleur ne se pose jamais sur une photographie.</p>'
         '<p>Le logo ne cache jamais un visage, et reste dans sa zone de protection.</p>',
         '<p class="note">Les photographies ci-contre sont des images de travail présentes dans le projet ; leurs droits d\'usage sont à confirmer avant toute diffusion.</p>'],
        '<div style="display:flex;gap:2mm;height:100%%;padding:2mm"><div style="flex:1;position:relative;border-radius:1.5mm;overflow:hidden">%s<span class="ok" style="z-index:3">&#10003;</span></div><div style="flex:1;position:relative;border-radius:1.5mm;overflow:hidden">%s<span class="no" style="z-index:3">&#10005;</span></div><div style="flex:1;position:relative;border-radius:1.5mm;overflow:hidden">%s<span class="no" style="z-index:3">&#10005;</span></div></div>' % (
            photo(HOME + '07.jpg', 'linear-gradient(rgba(27,22,48,.62),rgba(27,22,48,.62))', 'mono-blanc', 56, '40% 40%'),
            photo(HOME + '07.jpg', 'transparent', 'couleur', 56, '40% 40%'),
            photo(HOME + '03.jpg', 'transparent', 'mono-blanc', 56)))
    dont = lambda inner, lab, bgc='#fff': bad(inner, lab, 64, 44, bgc)
    lg = lambda s='', w=44: '<img src="%slogo-couleur.svg" style="width:%smm;%s" alt="">' % (LOGO, w, s)
    doc.section(C, 'guidance', 'Recommandations', 'Guidance',
        ['<p>Le logo n\'est pas modifiable. Il est interdit de changer ses couleurs, ses proportions ou le positionnement de ses éléments, d\'y ajouter un effet, de le placer sur un fond qui nuit à sa lisibilité.</p>'],
        '<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:3mm;padding:4mm;height:100%%;align-content:start">%s%s%s%s%s%s%s%s</div>' % (
            good(lg(w=44), 'Logo couleur sur fond clair', 64, 44),
            dont(lg('transform:scaleX(1.45)'), 'Ne pas déformer'),
            dont(lg('transform:rotate(-14deg)'), 'Ne pas pivoter'),
            dont(lg('filter:hue-rotate(110deg) saturate(1.3)'), 'Ne pas changer les couleurs'),
            dont(lg('filter:drop-shadow(1.2mm 1.2mm 0.6mm rgba(0,0,0,.55))'), 'Ne pas ajouter d\'ombre'),
            bad(lg(w=44), 'Pas de logo couleur sur le rouge', 64, 44, '#E30E0E', '', '#fff'),
            dont(lg('outline:.5mm solid #1B1630;padding:1mm'), 'Ne pas ajouter de contour'),
            dont(lg(w=44) + '<div style="position:absolute;inset:0;background:' + 'repeating-linear-gradient(45deg,rgba(4,51,195,.25) 0 4mm,rgba(227,14,14,.25) 4mm 8mm,rgba(240,168,0,.3) 8mm 12mm)' + '"></div>', 'Pas de fond agité')), visclass='tint')

    # =============================== CHAPITRE 3 ===============================
    doc.divider(ch_of(3))
    C = 3
    doc.section(C, 'xcolour', 'Les couleurs Xomiton', 'Xomiton colour',
        ['<p class="lead">Un bleu pour agir, un rouge pour l\'attention.</p>',
         '<p>Le <b>bleu Xomiton</b> (#0433C3) est la couleur de l\'action : boutons, liens, navigation active, anneau de focus. Le <b>rouge Xomiton</b> (#E30E0E) est la couleur de l\'attention : SOS, signalement, urgence critique, suppression. Il n\'est jamais décoratif.</p>',
         '<p>Le blanc, l\'encre (#1B1630) et le fond rosé (#FBF7FA) portent le reste de l\'interface : <b>60 % de neutres, 30 % de bleu, 10 % de rouge au plus</b>.</p>'
         '<p class="note">*CMJN : conversion mathématique indicative. Les valeurs d\'impression (Pantone, CMJN) sont à valider sur épreuve avec l\'imprimeur.</p>'],
        '<div style="display:flex;gap:7mm;justify-content:center;align-items:center;height:100%%;padding:0 8mm">%s%s%s%s%s</div>' % (
            swatch('Bleu Xomiton', BRAND['blue'], 'Couleur primaire · action'),
            swatch('Rouge Xomiton', BRAND['red'], 'Couleur secondaire · attention'),
            swatch('Encre', BRAND['ink'], 'Texte, fonds sombres', extra=''),
            swatch('Fond rosé', BRAND['bg'], 'Fond de page', extra='border:.3mm solid #ccc;'),
            swatch('Blanc', '#FFFFFF', 'Cartes, surfaces', extra='border:.3mm solid #ccc;')))
    fn = lambda n, h, d, tx='#fff': '<div style="flex:1"><div style="height:30mm;border-radius:2mm;background:%s;color:%s;padding:3mm;display:flex;align-items:flex-end;font:800 9pt Montserrat">%s</div><div style="font-size:6.5pt;line-height:1.5;margin-top:2mm;color:#5B5670"><b style="color:#1B1630">%s</b><br>%s<br>Contraste avec le texte : <b>%s:1</b></div></div>' % (h, tx, n, h, d, cr(h, tx))
    urg = ''.join('<div style="flex:1;text-align:center"><span class="pill" style="background:%s;color:%s;font-size:7pt;padding:1.2mm 3mm">%s %s</span></div>' % (c, t, icon(i, 3.2, t), n) for n, c, t, i in (('Faible', '#1864AB', '#fff', 'info-circle-fill'), ('Moyenne', '#F0A800', '#1B1630', 'exclamation-triangle-fill'), ('Élevée', '#B84B00', '#fff', 'exclamation-octagon-fill'), ('Critique', '#E30E0E', '#fff', 'bell-fill')))
    stat = ''.join('<div style="display:flex;align-items:center;gap:2mm;font-size:6.8pt"><i style="width:3.2mm;height:3.2mm;border-radius:50%%;background:%s;display:inline-block"></i>%s</div>' % (c, n) for n, c in (('Nouveau', '#0433C3'), ('Assigné', '#6F2C91'), ('En cours', '#1864AB'), ('Attente de réponse', '#F0A800'), ('Résolu', '#0B7A4B'), ('Clos', '#5B5670'), ('Non résolu', '#E30E0E')))
    doc.section(C, 'functional', 'Couleurs fonctionnelles', 'Medal colour',
        ['<p>Les couleurs fonctionnelles portent un <b>sens d\'état</b> : succès, alerte, information, danger. Elles ne servent jamais à décorer. Le rouge de danger est le rouge de marque : il garde donc un sens unique, « attention ».</p>'
         '<p><b>Jamais la couleur seule.</b> Chaque état associe une couleur, une icône et un mot. Les niveaux d\'urgence reprennent ceux du dispositif de cas : faible, moyenne, élevée, critique.</p>',
         '<p class="note">Cette page remplace la rubrique « Medal colour » du sommaire d\'origine, propre au sport.</p>'],
        '<div style="padding:6mm 8mm;height:100%%"><div style="display:flex;gap:5mm">%s%s%s%s</div><div style="display:grid;grid-template-columns:1.2fr 1fr;gap:8mm;margin-top:7mm"><div><div class="lbl" style="margin-bottom:2.5mm">Niveaux d\'urgence</div><div style="display:flex;gap:3mm">%s</div></div><div><div class="lbl" style="margin-bottom:2.5mm">Statuts de dossier</div><div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5mm 4mm">%s</div></div></div></div>' % (
            fn('Succès', '#0B7A4B', 'Enregistré, résolu, « en sécurité »'), fn('Alerte', '#F0A800', 'Attention modérée', '#1B1630'), fn('Information', '#1864AB', 'Aide, contexte'), fn('Danger', '#E30E0E', 'SOS, critique, suppression'), urg, stat), visclass='short')
    def scale(name, base_key, tints):
        cells = ''.join('<div style="flex:1"><div style="height:19mm;background:%s;border-radius:1mm;%s"></div><div class="mt" style="font:700 6.4pt Montserrat;margin-top:1.2mm">%s</div><div style="font-size:5.8pt;color:#5B5670">%s</div></div>' % (h, 'outline:.5mm solid #1B1630;outline-offset:.6mm;' if k == '500' else '', k, h) for k, h in tints)
        return '<div><div class="mt" style="font:800 8.5pt Montserrat;margin-bottom:2mm">%s</div><div style="display:flex;gap:1.3mm">%s</div></div>' % (name, cells)
    bl = [('50', '#EBEFFA'), ('100', '#D7DEF5'), ('200', '#AFBEEC'), ('300', '#8299E1'), ('400', '#4366D2'), ('500', '#0433C3'), ('600', '#032BA6'), ('700', '#032488'), ('800', '#021C6B'), ('900', '#02134A')]
    rd = [('50', '#FDECEC'), ('100', '#FBD8D8'), ('200', '#F6B2B2'), ('300', '#F18686'), ('400', '#EA4A4A'), ('500', '#E30E0E'), ('600', '#C10C0C'), ('700', '#9F0A0A'), ('800', '#7D0808'), ('900', '#560505')]
    acc = ''.join('<div style="flex:1"><div style="height:13mm;border-radius:1mm;background:%s"></div><div class="mt" style="font:700 6.4pt Montserrat;margin-top:1.2mm">%s</div><div style="font-size:5.8pt;color:#5B5670">%s</div></div>' % (h, n, h) for n, h in (('Violet', '#6F2C91'), ('Rose', '#C2255C'), ('Orange', '#B84B00'), ('Vert', '#0B7A4B'), ('Ambre', '#F0A800'), ('Bleu info', '#1864AB'), ('Texte atténué', '#5B5670'), ('Bordure', '#E4DCE6'), ('Bleu sombre', '#7C97FF'), ('Rouge sombre', '#FF6B6B')))
    doc.section(C, 'extended', 'Couleurs étendues', 'Extended colour',
        ['<p>Chaque couleur de marque se décline en échelle de 50 à 900. Les teintes claires servent aux fonds doux, les teintes foncées aux textes sur fond clair. Les accents (violet, rose, orange) distinguent des <b>catégories</b>, jamais une action.</p>'
         '<p>Pour le thème sombre, le bleu et le rouge passent à #7C97FF et #FF6B6B : leurs rapports de contraste sur #0E1020 sont de 6,94:1 et 6,80:1, contre 2,04:1 et 3,89:1 pour les couleurs d\'origine.</p>'],
        '<div style="padding:5mm 7mm;display:flex;flex-direction:column;gap:5mm">%s%s<div><div class="mt" style="font:800 8.5pt Montserrat;margin-bottom:2mm">Accents, neutres et variantes sombres</div><div style="display:flex;gap:1.3mm">%s</div></div></div>' % (scale('Bleu Xomiton', 'blue', bl), scale('Rouge Xomiton', 'red', rd), acc))
    pairs = [('#0433C3', '#FFFFFF', 'Bleu sur blanc'), ('#FFFFFF', '#0433C3', 'Blanc sur bleu'), ('#E30E0E', '#FFFFFF', 'Rouge sur blanc'), ('#FFFFFF', '#E30E0E', 'Blanc sur rouge'), ('#0433C3', '#E30E0E', 'Bleu sur rouge'), ('#1B1630', '#FBF7FA', 'Texte sur fond de page'),
             ('#5B5670', '#FBF7FA', 'Texte atténué sur fond'), ('#1B1630', '#F0A800', 'Encre sur alerte'), ('#0433C3', '#0E1020', 'Bleu sur sombre'), ('#7C97FF', '#0E1020', 'Bleu clair sur sombre'), ('#FF6B6B', '#0E1020', 'Rouge clair sur sombre')]
    def verdict(v): return ('AAA', '#0B7A4B') if v >= 7 else (('AA', '#0B7A4B') if v >= 4.5 else (('AA grand texte', '#B84B00') if v >= 3 else ('Interdit', '#E30E0E')))
    rows = ''.join('<tr><td><span style="display:inline-block;width:11mm;height:5.6mm;border-radius:1mm;background:%s;color:%s;font:800 6.5pt/5.6mm Montserrat;text-align:center">Aa</span></td><td>%s</td><td><b>%s:1</b></td><td><span class="pill" style="background:%s">%s</span></td></tr>' % (b, f, n, cr(f, b), verdict(cr(f, b))[1], verdict(cr(f, b))[0]) for f, b, n in pairs)
    doc.section(C, 'combining', 'Associer les couleurs', 'Combining colour',
        ['<p>La règle <b>60-30-10</b> fixe les proportions : 60 % de neutres, 30 % de bleu, 10 % de rouge au plus. Un écran qui comporte plus de deux éléments rouges est un défaut de conception : le rouge perd son sens.</p>'
         '<p>Le tableau donne les rapports de contraste calculés des associations usuelles. Texte normal : 4,5:1 minimum ; grand texte et composants d\'interface : 3:1.</p>'],
        '<div style="display:flex;gap:9mm;padding:6mm 8mm;height:100%%"><div style="flex:.9"><div class="lbl" style="margin-bottom:3mm">Proportions</div>'
        '<div style="display:flex;height:22mm;border-radius:2mm;overflow:hidden"><div style="flex:60;background:#FBF7FA;border:.3mm solid #ccc;display:flex;align-items:flex-end;padding:2mm;font:800 8pt Montserrat">60 %% neutres</div><div style="flex:30;background:#0433C3;color:#fff;display:flex;align-items:flex-end;padding:2mm;font:800 8pt Montserrat">30 %% bleu</div><div style="flex:10;background:#E30E0E;color:#fff;display:flex;align-items:flex-end;padding:1mm;font:800 6pt Montserrat">10 %%</div></div>'
        '<div style="margin-top:6mm;display:flex;gap:4mm"><div style="flex:1;height:40mm;border-radius:2mm;background:#fff;border:.25mm solid #ddd;padding:3mm;position:relative"><span class="ok">&#10003;</span><div class="mt" style="margin:8mm 0 0;font:800 9pt Montserrat;color:#1B1630">Titre</div><div style="width:24mm;height:7mm;border-radius:1.4mm;background:#0433C3;margin-top:3mm"></div><div style="width:12mm;height:7mm;border-radius:1.4mm;background:#E30E0E;margin-top:2mm"></div></div>'
        '<div style="flex:1;height:40mm;border-radius:2mm;background:#E30E0E;padding:3mm;position:relative"><span class="no">&#10005;</span><div class="mt" style="margin:8mm 0 0;font:800 9pt Montserrat;color:#0433C3">Titre</div><div style="width:24mm;height:7mm;border-radius:1.4mm;background:#0433C3;margin-top:3mm"></div></div></div></div>'
        '<div style="flex:1.1"><div class="lbl" style="margin-bottom:2mm">Associations et contrastes</div><table class="t"><tr><th>Aperçu</th><th>Association</th><th>Rapport</th><th>Niveau</th></tr>%s</table></div></div>' % rows)
    mini = lambda bgc, fg, title, extra='': '<div style="height:100%%;background:%s;color:%s;padding:3mm;position:relative">%s<div class="mt" style="font:800 9pt/1.15 Montserrat;margin-top:8mm">%s</div>%s</div>' % (bgc, fg, extra, title, '')
    doc.section(C, 'guidance', 'Recommandations', 'Guidance',
        ['<p>Six réflexes évitent l\'essentiel des erreurs : un seul rouge par intention, jamais de bleu sur rouge, pas d\'arc-en-ciel sur les écrans de crise, jamais de gris sur gris, une couleur toujours doublée d\'un mot, et le thème sombre utilise ses propres variantes.</p>'],
        '<div style="display:grid;grid-template-columns:repeat(3,1fr);grid-template-rows:1fr 1fr;gap:3mm;padding:4mm;height:100%%">%s%s%s%s%s%s</div>' % (
            good('<div style="text-align:center"><span class="pill" style="background:#E30E0E;font-size:9pt;padding:2.6mm 7mm">SOS</span></div>', 'Rouge : une seule intention par écran', 90, 48, '#fff', 'width:100%;height:100%'),
            bad('<div style="display:flex;gap:2mm"><span class="pill" style="background:#E30E0E">Envoyer</span><span class="pill" style="background:#E30E0E">Retour</span><span class="pill" style="background:#E30E0E">Aide</span></div>', 'Rouge décoratif ou répété', 90, 48, '#fff', 'width:100%;height:100%'),
            bad('<div style="background:linear-gradient(90deg,#E30E0E,#F0A800,#0B7A4B,#1864AB,#6F2C91);width:80%;height:14mm;border-radius:2mm;display:flex;align-items:center;justify-content:center;color:#fff;font:800 9pt Montserrat">Signaler un abus</div>', 'Pas d\'arc-en-ciel sur un écran de crise', 90, 48, '#fff', 'width:100%;height:100%'),
            bad('<div style="background:#EEE;color:#9a9a9a;padding:4mm;font-size:8pt">Texte gris clair sur fond gris</div>', 'Jamais de gris sur gris (&lt; 4,5:1)', 90, 48, '#fff', 'width:100%;height:100%'),
            good('<div style="display:flex;align-items:center;gap:2mm;font:700 8pt Arial;color:#9F0A0A;background:#FDECEC;border:.3mm solid #E30E0E;padding:2.5mm 4mm;border-radius:1.6mm">%s Critique : sans réponse depuis 24 h</div>' % icon('bell-fill', 4, '#9F0A0A'), 'Couleur + icône + mot', 90, 48, '#fff', 'width:100%;height:100%'),
            good('<div style="background:#0E1020;width:100%%;height:100%%;display:flex;align-items:center;justify-content:center;gap:3mm"><span class="pill" style="background:#7C97FF;color:#0E1020;font-size:8pt;padding:2mm 5mm">Continuer</span><span class="pill" style="background:#FF6B6B;color:#0E1020;font-size:8pt;padding:2mm 5mm">SOS</span></div>', 'Thème sombre : variantes claires', 90, 48, '#0E1020', 'width:100%;height:100%')), visclass='tint')


def arcs_mini():
    cols = ['#F6B8CB', '#6F2C91', '#0433C3', '#1B1630', '#E30E0E', '#F0A800']
    ps = ''.join('<path d="M-2 %.1f H30 A%.1f %.1f 0 0 1 %.1f 30 V48" fill="none" stroke="%s" stroke-width="4"/>' % (30 - (6 + i * 4.4), 6 + i * 4.4, 6 + i * 4.4, 30 + 6 + i * 4.4, c) for i, c in enumerate(reversed(cols)))
    return '<svg viewBox="0 0 66 46" style="width:40mm;height:26mm">%s</svg>' % ps
