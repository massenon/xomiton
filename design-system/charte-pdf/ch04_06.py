# -*- coding: utf-8 -*-
from kit import *
from structure import CHAPTERS
from ch01_03 import good, bad, ch_of

def ribbons(w=297, h=210, cx=168, cy=104, r0=18, step=13.2, sw=12.2, colors=None, rot=0):
    colors = colors or ['#F6B8CB', '#6F2C91', '#0433C3', '#1B1630', '#E30E0E', '#F0A800']
    n = len(colors)
    ps = ''
    for i, c in enumerate(colors):
        r = r0 + (n - 1 - i) * step
        ps += f'<path d="M-8 {cy - r:.2f} H{cx} A{r:.2f} {r:.2f} 0 0 1 {cx + r:.2f} {cy} V{h + 12}" fill="none" stroke="{c}" stroke-width="{sw}"/>'
    return f'<svg viewBox="0 0 {w} {h}" preserveAspectRatio="xMidYMid slice" style="position:absolute;inset:0;width:100%;height:100%;transform:rotate({rot}deg)">{ps}</svg>'

def waves(w=200, h=120, colors=None, sw=9, y0=20, gap=14):
    colors = colors or ['#0433C3', '#E30E0E', '#1B1630', '#6F2C91']
    ps = ''
    for i, c in enumerate(colors):
        y = y0 + i * gap
        ps += f'<path d="M-10 {y} C {w*0.2} {y-26}, {w*0.35} {y+30}, {w*0.55} {y+6} S {w*0.85} {y-24}, {w+10} {y+8}" fill="none" stroke="{c}" stroke-width="{sw}" stroke-linecap="round"/>'
    return f'<svg viewBox="0 0 {w} {h}" preserveAspectRatio="xMidYMid slice" style="position:absolute;inset:0;width:100%;height:100%">{ps}</svg>'

def lockup(label, variant='couleur', w=62, dark=False):
    col = '#fff' if dark else '#1B1630'
    rule = 'rgba(255,255,255,.55)' if dark else '#1B1630'
    x = w * 0.1425          # x = hauteur de la rosace (mm)
    return (f'<div style="display:flex;align-items:center;gap:{0.6*x:.2f}mm">{logo_w(variant, w)}'
            f'<div style="width:.35mm;height:{x:.2f}mm;background:{rule}"></div>'
            f'<div class="mt" style="font:700 {x*0.5/0.7*2.835:.1f}pt/1 Montserrat;color:{col};white-space:nowrap;margin-left:0">{label}</div></div>')

def build(doc):
    # =============================== CHAPITRE 4 ===============================
    doc.divider(ch_of(4)); C = 4
    grid = ('<div style="position:absolute;inset:0;background-image:linear-gradient(#e8e0eb .2mm,transparent .2mm),linear-gradient(90deg,#e8e0eb .2mm,transparent .2mm);background-size:8mm 8mm"></div>')
    doc.section(C, 'tier1', 'Niveau 1 : le logo Xomiton', 'Tier 1',
        ['<p class="lead">Le logo horizontal est la signature par défaut.</p>',
         '<p>Il se compose du mot « xomiton » (bleu), de la rosace (rouge) et du symbole de genre (rouge). Les proportions sont fixes : on ne déplace ni n\'agrandit un élément séparément. Le logo horizontal s\'utilise partout où la largeur le permet : en-tête du site, rapports, e-mails, documents.</p>'],
        f'<div style="position:relative;height:100%;display:flex;align-items:center;justify-content:center">{grid}<div style="position:relative">{logo_w("couleur", 190)}</div>'
        '<div class="abs mt" style="left:6mm;bottom:5mm;font:700 6.5pt Montserrat;color:#5B5670">Grille de construction : module de 8 mm (représentatif, non contractuel)</div></div>')
    doc.section(C, None, 'Niveau 1 : le logo Xomiton (suite)', 'Tier 1',
        ['<p>Le <b>logo vertical</b> sert quand la hauteur domine (affiche, kakémono, réseaux sociaux). La <b>rosace seule</b> est le symbole des petits formats : avatar, favicon, icône d\'application, filigrane. Elle ne s\'emploie jamais seule quand l\'espace permet le logo complet.</p>'],
        f'<div style="display:grid;grid-template-columns:1.3fr 1fr 1.2fr;height:100%">'
        f'<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;border-right:.25mm solid #e4dce6"><div class="lbl" style="margin-bottom:5mm">Logo vertical</div><img src="{LOGO}logo-vertical-couleur.svg" style="width:70mm" alt=""></div>'
        f'<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;border-right:.25mm solid #e4dce6"><div class="lbl" style="margin-bottom:5mm">Rosace seule</div>{sym("rosace-couleur", 46)}</div>'
        f'<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:5mm"><div class="lbl">Icônes d\'application</div><div style="display:flex;gap:5mm">{appicon("bleu", 30)}{appicon("blanc", 30, "border:.25mm solid #e4dce6;border-radius:6.6mm")}{appicon("sombre", 30)}</div><div class="note">Bleu · blanc · sombre — 2048 × 2048 px</div></div></div>')
    names = [('App', 'couleur'), ('Ressources', 'couleur'), ('Do Glegbe', 'couleur'), ('Annuaire', 'couleur'), ('Admin', 'couleur')]
    doc.section(C, 'tier2', 'Niveau 2 : les programmes et produits', 'Tier 2',
        ['<p class="lead">Un seul gabarit pour nommer un produit ou un programme.</p>',
         '<p>Le logo, un filet vertical fin, puis le nom en <b>Montserrat Bold</b>. Le filet fait la hauteur de la rosace ; le nom a pour hauteur de capitale la moitié de la hauteur de la rosace ; l\'espace de chaque côté du filet est égal à 0,6 x. Les noms sont courts (un ou deux mots) et jamais en capitales.</p>'
         '<p class="note">« Do Glegbe » existe déjà (initiative sur la gestion de l\'hygiène menstruelle). « App », « Ressources », « Annuaire » et « Admin » sont des propositions à valider.</p>'],
        f'<div style="display:grid;grid-template-columns:1fr 1fr;grid-template-rows:repeat(3,1fr);height:100%;gap:1mm;background:#e4dce6">'
        + ''.join(f'<div style="background:#fff;display:flex;align-items:center;justify-content:center">{lockup("Xomiton " + n if False else n, v, 66)}</div>' for n, v in names)
        + f'<div style="background:#1B1630;display:flex;align-items:center;justify-content:center">{lockup("App", "sombre", 66, True)}</div></div>')
    cols_bg = [('#FFFFFF', 'Blanc'), ('#FBF7FA', 'Fond rosé'), ('#0433C3', 'Bleu'), ('#1B1630', 'Encre'), ('#0E1020', 'Sombre')]
    variants = [('couleur', 'Couleur', [1, 1, 0, 0, 0]), ('sombre', 'Sombre', [0, 0, 0, 1, 1]), ('mono-encre', 'Mono encre', [1, 1, 0, 0, 0]), ('mono-bleu', 'Mono bleu', [1, 1, 0, 0, 0]), ('mono-blanc', 'Mono blanc', [0, 0, 1, 1, 1])]
    head = ''.join(f'<div class="lbl" style="text-align:center;align-self:end;padding-bottom:1mm">{n}</div>' for _, n in cols_bg)
    cells = ''
    for v, name, ok in variants:
        cells += f'<div class="mt" style="font:800 7.5pt Montserrat;align-self:center">{name}</div>'
        for (bgc, _), o in zip(cols_bg, ok):
            mark = f'<span class="ok" style="left:auto;right:1.6mm;top:1.6mm;width:4mm;height:4mm;font-size:6pt;line-height:4mm">&#10003;</span>' if o else '<span class="no" style="left:auto;right:1.6mm;top:1.6mm;width:4mm;height:4mm;font-size:6pt;line-height:4mm">&#10005;</span>'
            cells += f'<div style="position:relative;height:25mm;background:{bgc};border:.25mm solid #d8cfdc;border-radius:1.4mm;display:flex;align-items:center;justify-content:center"><div style="opacity:{1 if o else .5}">{logo_w(v, 34)}</div>{mark}</div>'
    doc.section(C, 'colourways', 'Déclinaisons de couleur', 'Colourways',
        ['<p>Cinq versions du logo couvrent tous les fonds. La coche indique les associations autorisées : le choix de la version dépend uniquement du fond. En cas de doute, on prend la version <b>couleur sur fond blanc</b>.</p>'],
        f'<div style="display:grid;grid-template-columns:22mm repeat(5,1fr);gap:2.4mm;padding:5mm 6mm;height:100%;align-content:start"><div></div>{head}{cells}</div>')
    doc.section(C, 'guidance', 'Recommandations', 'Guidance',
        ['<p>Pour un produit ou un programme, on utilise le gabarit du niveau 2 sans le modifier. On ne crée pas de nouveau logo, on n\'associe pas d\'autre logo sans respecter la zone de protection, et le logo d\'un partenaire n\'est jamais plus grand que celui de Xomiton.</p>'],
        '<div style="display:grid;grid-template-columns:repeat(3,1fr);grid-template-rows:1fr 1fr;gap:3mm;padding:4mm;height:100%">'
        + good(lockup('Ressources', 'couleur', 42), 'Gabarit respecté', 90, 50, '#fff', 'width:100%;height:100%')
        + bad(f'<div style="display:flex;align-items:center;gap:1mm">{logo_w("couleur", 38)}<span style="font:italic 700 9pt Georgia;color:#E30E0E;margin-left:2mm">Ressources</span></div>', 'Autre police, autre couleur', 90, 50, '#fff', 'width:100%;height:100%')
        + bad(f'<div style="display:flex;align-items:center;gap:6mm">{logo_w("couleur", 30)}<div class="mt" style="font:800 18pt Montserrat;color:#1B1630">APP</div></div>', 'Nom trop grand, en capitales', 90, 50, '#fff', 'width:100%;height:100%')
        + good(f'<div style="display:flex;align-items:center;gap:8mm">{logo_w("couleur", 38)}<div style="width:.3mm;height:9mm;background:#bbb"></div><div class="mt" style="font:700 6.6pt Montserrat;color:#5B5670;text-align:center">Avec le soutien de<br>[logo partenaire]</div></div>', 'Partenaire : plus petit, séparé par un filet', 90, 50, '#fff', 'width:100%;height:100%')
        + bad(f'<div style="display:flex;align-items:center;gap:2mm">{logo_w("couleur", 24)}<div style="width:36mm;height:12mm;background:#ddd;display:flex;align-items:center;justify-content:center;font:700 8pt Arial;color:#666">[partenaire]</div></div>', 'Partenaire plus grand que Xomiton', 90, 50, '#fff', 'width:100%;height:100%')
        + bad(f'<div style="display:flex;flex-direction:column;align-items:center;gap:1mm">{logo_w("couleur", 40)}<div class="mt" style="font:700 7pt Montserrat;color:#0433C3;margin-top:-1mm">App</div></div>', 'Nom collé sous le logo, sans filet', 90, 50, '#fff', 'width:100%;height:100%')
        + '</div>', visclass='tint')
    desig = [('Xomiton', 'Nom de la marque. Une majuscule initiale, jamais « XOMITON » en texte courant, jamais « Xomiton® ».', 'Xomiton accompagne…'),
             ('Xomiton App', 'Application mobile et PWA. Deux mots, A majuscule.', 'Télécharger Xomiton App'),
             ('Do Glegbe', 'Initiative sur la gestion de l\'hygiène menstruelle. Deux mots, majuscule à chaque mot.', 'Do Glegbe, gérer sa santé menstruelle'),
             ('SOS', 'Alerte d\'urgence avec position. En capitales (sigle). Bouton rouge de 64 px.', 'Maintiens SOS pour alerter'),
             ('Signaler', 'Libellé public du dispositif de signalement (« dénonciation » reste un terme interne). À valider.', 'Signaler un abus'),
             ('Annuaire', 'Liste des personnes et services d\'aide. Remplace « Contacts d\'urgence » dans le menu. À valider.', 'Trouver un·e professionnel·le')]
    rows = ''.join(f'<tr><td class="mt" style="font:800 8pt Montserrat;width:30mm">{a}</td><td>{b}</td><td style="color:#5B5670;width:55mm">{c}</td></tr>' for a, b, c in desig)
    doc.section(C, 'designations', 'Désignations et mentions', 'Designations and labels',
        ['<p>La <b>signature</b> de marque est « Soutenir, informer, protéger. » (titre de la page À propos du site). Elle suit le logo, en Montserrat Bold, sans guillemets, point final inclus.</p>',
         '<p><b>Mention légale courante</b> : « © 2026 Xomiton. Tous droits réservés. » (déjà présente en pied de page du site). <b>Adresse</b> : xomiton.org pour le site, app.xomiton.org pour l\'application actuelle.</p>'],
        f'<div style="padding:5mm 7mm"><table class="t" style="font-size:8.6pt"><tr><th>Désignation</th><th>Règle d\'emploi</th><th>Exemple</th></tr>{rows}</table>'
        f'<div style="margin-top:6mm;display:flex;gap:8mm;align-items:center;border-top:.25mm solid #e4dce6;padding-top:5mm">{logo_w("couleur", 50)}<div class="mt" style="font:700 10pt Montserrat;color:#1B1630">Soutenir, informer, protéger.</div></div></div>')

    # =============================== CHAPITRE 5 ===============================
    doc.divider(ch_of(5)); C = 5
    def spec(lines, small=''):
        return f'<div style="background:#111;color:#fff;height:100%;padding:8mm 10mm;display:flex;flex-direction:column;justify-content:space-between"><div>{lines}</div><div style="font:normal 6.6pt/1.5 Arial;color:#bdb8c8">{small}</div></div>'
    doc.section(C, 'headline', 'Xomiton Headline : Montserrat', 'Xomiton Headline',
        ['<p class="lead">Montserrat porte les titres, les boutons, la navigation et les chiffres clés.</p>',
         '<p>Police géométrique, large et lisible. On utilise trois graisses : <b>800</b> (titres de page), <b>700</b> (titres de section, boutons), <b>600</b> (sous-titres courts, étiquettes). Jamais la 800 sous 24 px, jamais de capitales continues sur plus de quatre mots, un seul titre de niveau 1 par page.</p>',
         '<p>Police libre (licence SIL OFL), <b>auto-hébergée</b> en WOFF2 variable (graisse 100 à 900), sous-ensembles latin, latin étendu et vietnamien. Aucun appel à Google Fonts en production.</p>'],
        spec('<div class="mt" style="font:800 58pt/1.0 Montserrat;letter-spacing:-.02em">Soutenir,<br>informer,<br>protéger.</div>'
             '<div class="mt" style="font:700 22pt/1.2 Montserrat;margin-top:6mm;color:#8299E1">ABCDEFGHIJKLMNOPQRSTUVWXYZ<br>abcdefghijklmnopqrstuvwxyz 0123456789 &amp; ! ? @ € « » ’</div>',
             'Montserrat 800 / 700 / 600 · interlignage titres 1,2 · approche −1 % sur les très grands titres'), visclass='dk')
    doc.section(C, 'sans', 'Xomiton Sans : Arial', 'Xomiton Sans',
        ['<p class="lead">Arial porte les sous-titres, les chapeaux et le texte courant.</p>',
         '<p>Police système, présente sur la plupart des ordinateurs : aucun téléchargement, affichage immédiat, ce qui compte avec peu de données. Deux graisses : <b>Regular</b> (400) et <b>Bold</b> (700). Pas d\'italique en texte courant.</p>',
         '<p class="note">Sur Android (majorité des utilisateur·rice·s), Arial est remplacée par Roboto ou Liberation Sans. La mise en page ne doit jamais dépendre de la largeur exacte d\'Arial ; test sur appareil réel obligatoire.</p>'],
        spec('<div style="font:400 40pt/1.15 Arial;letter-spacing:0">Un espace d\'échange, de soutien<br>et d\'actions pour votre bien-être.</div>'
             '<div style="font:700 17pt/1.35 Arial;margin-top:7mm;color:#8299E1">ABCDEFGHIJKLMNOPQRSTUVWXYZ<br>abcdefghijklmnopqrstuvwxyz 0123456789 &amp; ! ? @ € « » ’</div>'
             '<div style="font:400 11pt/1.6 Arial;margin-top:7mm;max-width:150mm;color:#e9e6f0">Texte courant en 16 px (12 pt), interlignage 1,6. Nous répondons à toutes vos questions, gratuitement et en toute confidentialité. Si vous êtes en danger, appuyez sur SOS.</div>',
             'Arial 400 / 700 · texte 16 px minimum à l\'écran · pile : Arial, "Liberation Sans", Helvetica, sans-serif'), visclass='dk')
    tests = 'ɖ ɛ ɔ ƒ ṣ ǎ ẹ ọ œ é è ê ç'
    doc.section(C, 'serif', 'Polices de secours et langues locales', 'Xomiton Serif',
        ['<p>Xomiton n\'utilise <b>aucune police à empattements</b> : cette page remplace la rubrique « Serif » du sommaire d\'origine par ce qui compte vraiment ici : les <b>polices de secours</b> et les <b>langues locales</b>.</p>',
         '<p>Le fon et le yoruba demandent des caractères que toutes les polices n\'ont pas. Contrôle effectué sur les fichiers : <b>Montserrat</b> couvre ɖ ɛ ɔ ƒ ṣ ǎ œ dans ses sous-ensembles latin et latin étendu, et ẹ ọ grâce au sous-ensemble vietnamien. <b>Arial (Windows)</b> les couvre tous. Aucune des deux ne contient le symbole de genre ⚧ ni la flèche → de Montserrat : on utilise une icône.</p>'],
        f'<div style="padding:6mm 8mm"><div class="lbl">Test de glyphes (rendu réel)</div>'
        f'<div style="display:grid;grid-template-columns:1fr 1fr;gap:6mm;margin:3mm 0 6mm"><div class="card" style="background:#F6F2F7"><div class="lbl">Montserrat 700</div><div class="mt" style="font:700 26pt/1.3 Montserrat">{tests}</div></div><div class="card" style="background:#F6F2F7"><div class="lbl">Arial 400</div><div style="font:400 26pt/1.3 Arial">{tests}</div></div></div>'
        '<div class="lbl" style="margin-bottom:2mm">Piles de polices (CSS)</div><table class="t"><tr><th>Rôle</th><th>Pile</th><th>Si la première manque</th></tr>'
        '<tr><td><b>Titres</b></td><td>"Montserrat", "Segoe UI", Arial, sans-serif</td><td>Segoe UI (Windows), puis Arial</td></tr>'
        '<tr><td><b>Sous-titres et texte</b></td><td>Arial, "Liberation Sans", Helvetica, sans-serif</td><td>Liberation Sans (Linux), Helvetica (Apple), police sans empattement du système (Android : Roboto)</td></tr>'
        '<tr><td><b>Chiffres de tableaux</b></td><td>idem, avec chiffres tabulaires</td><td>—</td></tr></table>'
        '<p class="note" style="margin-top:3mm">Roboto (Android) n\'a pas été contrôlée glyphe par glyphe : à tester sur un appareil réel avec des textes en fon et en yoruba.</p></div>')
    doc.section(C, 'opentype', 'Fonctionnalités OpenType', 'Opentype features',
        ['<p>Contrôle des fichiers Montserrat distribués par Google (sous-ensemble latin) : fonctionnalités présentes <b>tnum</b> (chiffres tabulaires), <b>pnum</b> (chiffres proportionnels), <b>frac</b>, <b>numr</b>, <b>dnom</b>, <b>liga</b>, <b>ccmp</b>, <b>locl</b>.</p>',
         '<p><b>Règle :</b> chiffres tabulaires (<code>font-variant-numeric: tabular-nums</code>) dans les tableaux, les KPI et les colonnes de montants ; chiffres proportionnels dans le texte courant. Les chiffres alignés évitent que les colonnes « bougent » quand une valeur change.</p>'],
        '<div style="display:grid;grid-template-columns:1fr 1fr;gap:6mm;padding:6mm 8mm">'
        '<div class="card" style="background:#F6F2F7"><div class="lbl">Chiffres proportionnels (pnum)</div><div class="mt" style="font:700 34pt/1.3 Montserrat;font-feature-settings:\'pnum\' 1,\'tnum\' 0">1 111<br>8 888<br>1 234 567</div></div>'
        '<div class="card" style="background:#F6F2F7"><div class="lbl">Chiffres tabulaires (tnum)</div><div class="mt" style="font:700 34pt/1.3 Montserrat;font-feature-settings:\'tnum\' 1">1 111<br>8 888<br>1 234 567</div></div>'
        '<div class="card" style="background:#F6F2F7"><div class="lbl">Fractions (frac)</div><div class="mt" style="font:700 34pt/1.3 Montserrat;font-feature-settings:\'frac\' 1">1/2 3/4 12/25</div></div>'
        '<div class="card" style="background:#F6F2F7"><div class="lbl">Usage : tableau de bord</div><table class="t" style="font:700 17pt Montserrat;font-variant-numeric:tabular-nums"><tr><td>Cas ouverts</td><td style="text-align:right">1 284</td></tr><tr><td>SOS 24 h</td><td style="text-align:right">17</td></tr><tr><td>Réponse (min)</td><td style="text-align:right">4,5</td></tr></table></div></div>')
    sizes = [('Très grand titre (display)', 'Montserrat 800', '36–56 px', '1,0 à 1,1', '−1 %'), ('Titre 1', 'Montserrat 800', '32–44 px', '1,2', '−0,5 %'), ('Titre 2', 'Montserrat 700', '26–34 px', '1,2', '0'), ('Titre 3', 'Montserrat 700', '22–26 px', '1,25', '0'),
             ('Sous-titre (lead)', 'Arial 400', '18–21 px', '1,5', '0'), ('Texte courant', 'Arial 400', '16 px', '1,6', '0'), ('Petit texte', 'Arial 400', '14 px', '1,5', '0'), ('Étiquette en capitales', 'Arial 700', '11–12 px', '1,3', '+6 %')]
    rows = ''.join(f'<tr><td><b>{a}</b></td><td>{b}</td><td>{c}</td><td>{d}</td><td>{e}</td></tr>' for a, b, c, d, e in sizes)
    doc.section(C, 'tracking', 'Approche, crénage, interlignage', 'Tracking, kerning, leading',
        ['<p><b>Approche</b> (espacement uniforme) : on ne la modifie que pour les très grands titres (−1 %) et les étiquettes en capitales (+6 %). <b>Crénage</b> (paires de lettres) : toujours activé, jamais désactivé. <b>Interlignage</b> : 1,2 pour les titres, 1,6 pour le texte, jamais en dessous de 1,5 pour un paragraphe.</p>'],
        f'<div style="padding:6mm 8mm;display:grid;grid-template-columns:1.3fr 1fr;gap:9mm"><div><table class="t" style="font-size:8.6pt"><tr><th>Niveau</th><th>Police</th><th>Taille</th><th>Interligne</th><th>Approche</th></tr>{rows}</table></div>'
        '<div><div class="lbl">Interlignage</div><div style="margin-top:2mm;display:flex;gap:4mm"><div class="card" style="flex:1;background:#F6F2F7;font:400 10pt/1.0 Arial;position:relative"><span class="no" style="position:static;display:inline-block;margin-bottom:2mm">&#10005;</span><br>Interligne 1,0 : les lignes se touchent et fatiguent la lecture sur petit écran.</div>'
        '<div class="card" style="flex:1;background:#F6F2F7;font:400 10pt/1.6 Arial"><span class="ok" style="position:static;display:inline-block;margin-bottom:2mm">&#10003;</span><br>Interligne 1,6 : le texte respire et reste lisible, même sur un petit téléphone.</div></div>'
        '<div class="lbl" style="margin-top:6mm">Approche des grands titres</div><div style="display:flex;gap:4mm;margin-top:2mm"><div class="card" style="flex:1;background:#F6F2F7"><div class="mt" style="font:800 34pt/1 Montserrat;letter-spacing:.04em">Protéger</div><div class="note">+4 % : trop lâche</div></div><div class="card" style="flex:1;background:#F6F2F7"><div class="mt" style="font:800 34pt/1 Montserrat;letter-spacing:-.01em">Protéger</div><div class="note">−1 % : correct</div></div></div></div></div>')
    para = 'Xomiton est un espace safe d\'apprentissage et d\'appui holistique. Il réunit de l\'information fiable, un réseau d\'entraide entre pair·es et des professionnel·les, pour que chacune puisse se faire entendre en toute sécurité et trouver le soutien dont elle a besoin.'
    doc.section(C, 'alignment', 'Alignement des paragraphes', 'Paragraph alignment',
        ['<p>Le texte est <b>aligné à gauche, au fer à droite irrégulier</b>. Jamais justifié (les espaces irréguliers créent des « rivières » qui gênent surtout les personnes dyslexiques). Le centrage est réservé aux titres courts (deux lignes au plus) et aux blocs d\'appel à l\'action.</p>'],
        '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:4mm;padding:5mm;height:100%">'
        + good(f'<p style="font:400 10.5pt/1.65 Arial;text-align:left;margin:0;padding:11mm 5mm 4mm">{para}</p>', 'Aligné à gauche', 88, 80, '#fff', 'width:100%;height:100%;align-items:flex-start')
        + bad(f'<p style="font:400 10.5pt/1.65 Arial;text-align:justify;margin:0;padding:11mm 5mm 4mm">{para}</p>', 'Justifié : rivières', 88, 80, '#fff', 'width:100%;height:100%;align-items:flex-start')
        + bad(f'<p style="font:400 10.5pt/1.65 Arial;text-align:center;margin:0;padding:11mm 5mm 4mm">{para}</p>', 'Centré sur un long texte', 88, 80, '#fff', 'width:100%;height:100%;align-items:flex-start')
        + '</div>', visclass='tint')
    doc.section(C, 'rags', 'Fins de ligne et veuves', 'Rags and widows',
        ['<p>On évite un mot seul sur la dernière ligne (« veuve ») et les fins de ligne en dents de scie. En CSS : <code>text-wrap: balance</code> pour les titres, <code>text-wrap: pretty</code> pour les paragraphes. En français, une <b>espace insécable</b> précède les signes « : ; ? ! » et les guillemets, et sépare un nombre de son unité (« 24 h », « 4,5:1 »).</p>'],
        '<div style="display:grid;grid-template-columns:1fr 1fr;gap:5mm;padding:5mm;height:100%">'
        + good('<div class="mt" style="font:800 28pt/1.15 Montserrat;width:86mm;text-wrap:balance;padding:3mm">Un soutien à portée de main, partout et tout le temps</div>', 'Titre équilibré (text-wrap: balance)', 125, 62, '#fff', 'width:100%')
        + bad('<div class="mt" style="font:800 28pt/1.15 Montserrat;width:86mm;padding:3mm">Un soutien à portée de main, partout et tout le temps</div>', 'Sans équilibrage : un mot isolé possible', 125, 62, '#fff', 'width:100%')
        + good('<p style="font:400 11pt/1.6 Arial;width:104mm;text-wrap:pretty;margin:0;padding:3mm">Signalez un abus en quelques minutes : vous pouvez rester anonyme et suivre votre demande avec un code, sans créer de compte&nbsp;!</p>', 'Paragraphe (text-wrap: pretty) et espace insécable', 125, 62, '#fff', 'width:100%')
        + bad('<p style="font:400 11pt/1.6 Arial;width:104mm;margin:0;padding:3mm">Signalez un abus en quelques minutes : vous pouvez rester anonyme et suivre votre demande avec un code, sans créer de compte !</p>', 'Le « ! » peut passer seul à la ligne', 125, 62, '#fff', 'width:100%')
        + '</div>', visclass='tint')
    doc.section(C, 'linelength', 'Longueur de ligne', 'Line length',
        ['<p>La longueur idéale est de <b>45 à 75 caractères par ligne</b>, soit au plus <b>68 ch</b> (<code>max-width: 68ch</code>) pour le texte courant. Une ligne trop longue fait perdre le fil ; trop courte, elle hache la lecture. Sur mobile, la largeur de l\'écran suffit.</p>'],
        '<div style="display:grid;grid-template-columns:1.15fr 1fr .6fr;gap:6mm;padding:5mm;height:100%">'
        + bad('<div><div style="font:400 9.4pt/1.5 Arial;padding:11mm 5mm 3mm">Xomiton est un espace safe d\'apprentissage et d\'appui holistique qui réunit de l\'information fiable, un réseau d\'entraide entre pair·es et des professionnel·les pour que chacune puisse se faire entendre en toute sécurité et trouver le soutien dont elle a besoin au moment où elle en a besoin.</div><div style="border-top:.4mm solid #E30E0E;margin:0 3mm;position:relative"><span class="note">≈ 120 caractères : trop long</span></div></div>', '', 100, 78, '#fff', 'width:100%;height:100%;align-items:flex-start')
        + good('<div style="width:100%"><div style="font:400 9.8pt/1.6 Arial;max-width:68ch;padding:11mm 5mm 3mm">Xomiton est un espace safe d\'apprentissage et d\'appui holistique qui réunit de l\'information fiable, un réseau d\'entraide entre pair·es et des professionnel·les pour que chacune puisse se faire entendre en toute sécurité et trouver le soutien dont elle a besoin.</div><div style="border-top:.4mm solid #0B7A4B;margin:0 3mm;width:68ch;max-width:calc(100% - 6mm)"><span class="note">≤ 68 caractères : lisible</span></div></div>', '', 100, 78, '#fff', 'width:100%;height:100%;align-items:flex-start')
        + bad('<div style="font:400 9.8pt/1.6 Arial;width:30mm;padding:11mm 3mm 3mm">Xomiton est un espace safe d\'apprentissage et d\'appui holistique qui réunit de l\'information fiable.</div>', 'Trop court (&lt; 30)', 60, 78, '#fff', 'width:100%;height:100%;align-items:flex-start')
        + '</div>', visclass='tint')
    pairs = [('#1B1630', '#FBF7FA', 'Texte courant', 'Arial 400 · 16 px'), ('#5B5670', '#FBF7FA', 'Texte atténué', 'Arial 400 · 14 px'), ('#0433C3', '#FBF7FA', 'Lien', 'Arial 400, souligné'), ('#FFFFFF', '#0433C3', 'Titre sur bleu', 'Montserrat 800'),
             ('#FFFFFF', '#E30E0E', 'Texte sur rouge', 'Montserrat 700 · 14 px et plus'), ('#EDEAF7', '#0E1020', 'Texte, thème sombre', 'Arial 400 · 16 px'), ('#7C97FF', '#0E1020', 'Lien, thème sombre', 'Arial 400, souligné'), ('#1B1630', '#F0A800', 'Texte sur alerte', 'Arial 700')]
    cells = ''.join(f'<div style="background:{bg};color:{fg};padding:4mm;border-radius:1.6mm;border:.2mm solid #d8cfdc"><div class="mt" style="font:800 11pt/1.15 Montserrat">{n}</div><div style="font:400 8pt/1.4 Arial;margin-top:1mm;opacity:.9">{d}</div><div style="font:700 7pt Arial;margin-top:3mm">{fg} sur {bg} · {cr(fg, bg)}:1</div></div>' for fg, bg, n, d in pairs)
    doc.section(C, 'typecolour', 'Couleur du texte', 'Typography colour',
        ['<p>Le texte courant est en <b>encre</b> (#1B1630), jamais en noir pur. Le texte atténué (#5B5670) reste à 6,57:1 sur le fond de page. Le rouge n\'est pas une couleur de texte courant : à 4,84:1 sur blanc, il est réservé aux libellés d\'attention de 14 px et plus, jamais au corps de texte.</p>'],
        f'<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:3mm;padding:5mm;height:100%;align-content:start">{cells}</div>', visclass='tint')
    doc.section(C, 'typecombining', 'Associer les polices', 'Combining typefaces',
        ['<p>Montserrat pour ce qu\'on <b>lit d\'un coup d\'œil</b> (titres, boutons, navigation, chiffres clés), Arial pour ce qu\'on <b>lit en continu</b> (chapeaux, paragraphes, formulaires). Un seul niveau de titre par bloc, et jamais plus de deux familles par page.</p>'],
        f'<div style="display:grid;grid-template-columns:1.1fr 1fr;gap:0;height:100%"><div style="padding:8mm 9mm;background:#fff"><div style="font:700 7pt Arial;letter-spacing:.07em;text-transform:uppercase;color:#E30E0E">Annuaire</div>'
        '<div class="mt" style="font:800 27pt/1.1 Montserrat;margin:2mm 0 3mm;letter-spacing:-.01em">Trouver une personne de confiance près de chez vous</div>'
        '<div style="font:400 12pt/1.5 Arial;color:#3b3650;max-width:110mm">Juristes, psychologues, gynécologues et pair·es éducateur·rice·s vous écoutent, gratuitement et en toute confidentialité.</div>'
        '<div style="display:flex;gap:4mm;margin-top:6mm"><span class="mt" style="background:#0433C3;color:#fff;font:700 9pt Montserrat;padding:3mm 7mm;border-radius:2mm">Voir l\'annuaire</span><span class="mt" style="border:.4mm solid #0433C3;color:#0433C3;font:700 9pt Montserrat;padding:3mm 7mm;border-radius:2mm">Signaler un abus</span></div></div>'
        '<div style="background:#F6F2F7;padding:8mm 9mm"><div class="lbl">Hiérarchie</div><table class="t" style="margin-top:3mm"><tr><td>Étiquette</td><td>Arial 700, capitales, +6 %</td></tr><tr><td>Titre</td><td>Montserrat 800, interligne 1,1</td></tr><tr><td>Chapeau</td><td>Arial 400, 1,5</td></tr><tr><td>Boutons</td><td>Montserrat 700</td></tr><tr><td>Corps</td><td>Arial 400, 1,6, ≤ 68 ch</td></tr></table></div></div>')

    # =============================== CHAPITRE 6 ===============================
    doc.divider(ch_of(6)); C = 6
    big = lambda s, st: f'<img src="{LOGO}symbole-rosace-couleur.svg" style="position:absolute;width:{s}mm;{st}" alt="">'
    doc.section(C, 'field', 'La rosace', 'Field of play',
        ['<p class="lead">Une forme tirée du logo pour habiller les pages.</p>',
         '<p>La rosace (quatre formes en tourbillon) devient un <b>élément graphique</b> : très grande et recadrée, répétée en motif ou isolée. Elle apporte de la chaleur aux couvertures, aux bandeaux et aux états vides. Elle n\'est jamais utilisée sur les écrans de signalement, de SOS ou de dossier.</p>'],
        '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1mm;height:100%;background:#fff">'
        f'<div style="position:relative;background:#0433C3;overflow:hidden">{big(190, "right:-60mm;top:-35mm;opacity:.9;filter:brightness(0) invert(1);opacity:.16")}<div class="abs mt" style="left:7mm;bottom:7mm;color:#fff;font:800 17pt/1.1 Montserrat">Agrandie<br>et recadrée</div></div>'
        f'<div style="position:relative;background:#FBF7FA;overflow:hidden;background-image:url({LOGO}symbole-rosace-couleur.svg);background-size:30mm;background-position:2mm 2mm;"><div style="position:absolute;inset:0;background:rgba(251,247,250,.82)"></div><div class="abs mt" style="left:7mm;bottom:7mm;font:800 17pt/1.1 Montserrat">Motif<br>répété, atténué</div></div>'
        f'<div style="position:relative;background:#F6B8CB;overflow:hidden">{big(120, "left:50%;top:44%;transform:translate(-50%,-50%) rotate(90deg);filter:brightness(0) invert(1)")}<div class="abs mt" style="left:7mm;bottom:7mm;font:800 17pt/1.1 Montserrat;color:#1B1630">Isolée,<br>en réserve</div></div></div>')
    crop = lambda off, lab, ok: (good if ok else bad)(f'<div style="position:relative;width:100%;height:100%;overflow:hidden;background:#0433C3">{big(64, off + ";filter:brightness(0) invert(1);opacity:.9")}</div>', lab, 88, 62, '#fff', 'width:100%;height:100%')
    doc.section(C, 'fieldcrop', 'Recadrage de la rosace', 'Field of play cropping',
        ['<p>On peut recadrer la rosace, à condition de garder visibles <b>au moins la moitié de sa surface</b> et son <b>centre</b> : elle doit rester reconnaissable. On la fait pivoter par pas de 90° seulement, jamais en miroir (le sens du tourbillon fait partie du dessin).</p>'],
        '<div style="display:grid;grid-template-columns:repeat(3,1fr);grid-template-rows:1fr 1fr;gap:3mm;padding:4mm;height:100%">'
        + crop('left:50%;top:50%;transform:translate(-50%,-50%)', 'Entière, centrée', True)
        + crop('right:-14mm;bottom:-14mm', 'Recadrée, centre visible', True)
        + crop('left:50%;top:50%;transform:translate(-50%,-50%) rotate(90deg)', 'Pivotée de 90°', True)
        + crop('right:-52mm;top:-10mm', 'Trop coupée : centre perdu', False)
        + crop('left:50%;top:50%;transform:translate(-50%,-50%) scaleX(-1)', 'Miroir interdit', False)
        + crop('left:50%;top:50%;transform:translate(-50%,-50%) rotate(37deg)', 'Rotation libre interdite', False)
        + '</div>', visclass='tint')
    doc.section(C, 'lines', 'Les rubans', 'Sport lines',
        ['<p class="lead">Des bandes parallèles qui s\'incurvent.</p>',
         '<p>Les rubans rappellent les rubans arc-en-ciel des illustrations, mais dans la palette de marque. Toutes les bandes ont <b>la même largeur</b>, sont <b>parallèles</b> et ne se chevauchent pas. De trois à six bandes. Le bleu et le rouge ne se touchent pas directement : une bande d\'encre ou de blanc les sépare.</p>'],
        '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1mm;height:100%;background:#fff">'
        f'<div style="position:relative;overflow:hidden">{ribbons(100, 120, 62, 50, 10, 11, 9.5, ["#0433C3", "#FFFFFF", "#E30E0E"])}<div class="abs lbl" style="left:5mm;bottom:5mm">Courbe · 3 bandes</div></div>'
        f'<div style="position:relative;overflow:hidden;background:#FBF7FA">{waves(100, 120, ["#0433C3", "#1B1630", "#E30E0E", "#6F2C91"], 9, 24, 17)}<div class="abs lbl" style="left:5mm;bottom:5mm">Ondes · 4 bandes</div></div>'
        f'<div style="position:relative;overflow:hidden">{ribbons(100, 120, 40, 60, 8, 10, 8.6, ["#F6B8CB", "#6F2C91", "#0433C3", "#1B1630", "#E30E0E", "#F0A800"])}<div class="abs lbl" style="left:5mm;bottom:5mm">Courbe · 6 bandes (couverture)</div></div></div>')
    doc.section(C, 'combining', 'Combiner les éléments', 'Combining graphics',
        ['<p>Une page porte <b>un seul élément graphique dominant</b> : rubans <i>ou</i> rosace, jamais les deux à pleine échelle. Le logo reste dans sa zone de protection, le texte sur une zone calme, avec un contraste d\'au moins 4,5:1.</p>'],
        '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:3mm;padding:4mm;height:100%">'
        + good(f'<div style="position:relative;width:100%;height:100%;background:#fff;overflow:hidden">{ribbons(100, 62, 74, 30, 6, 7.5, 6.5, ["#F6B8CB", "#6F2C91", "#0433C3", "#1B1630"])}<div class="abs" style="left:6mm;top:6mm">{logo_w("couleur", 28)}</div></div>', 'Rubans + logo', 88, 66, '#fff', 'width:100%;height:100%')
        + good(f'<div style="position:relative;width:100%;height:100%;background:#0433C3;overflow:hidden">{big(110, "right:-30mm;top:-25mm;filter:brightness(0) invert(1);opacity:.16")}<div class="abs" style="left:6mm;top:6mm">{logo_w("mono-blanc", 28)}</div><div class="abs mt" style="left:6mm;bottom:10mm;color:#fff;font:800 14pt/1.15 Montserrat">Soutenir,<br>informer,<br>protéger.</div></div>', 'Rosace en filigrane + logo blanc', 88, 66, '#fff', 'width:100%;height:100%')
        + bad(f'<div style="position:relative;width:100%;height:100%;background:#fff;overflow:hidden">{ribbons(100, 62, 74, 30, 6, 7.5, 6.5, ["#F6B8CB", "#6F2C91", "#0433C3", "#1B1630"])}{big(70, "left:10mm;top:10mm")}<div class="abs" style="left:34mm;top:28mm">{logo_w("couleur", 28)}</div></div>', 'Deux éléments dominants, logo gêné', 88, 66, '#fff', 'width:100%;height:100%')
        + '</div>', visclass='tint')
    doc.section(C, 'guidance', 'Recommandations', 'Guidance',
        ['<p>Les éléments graphiques sont décoratifs : on les retire sans perte d\'information. Ils sont <b>interdits</b> sur les écrans de signalement, de SOS, de dossier et de tableau de bord, et ne passent jamais derrière un texte courant sans voile garantissant le contraste.</p>'],
        '<div style="display:grid;grid-template-columns:repeat(4,1fr);grid-template-rows:1fr;gap:3mm;padding:4mm;height:100%">'
        + good(f'<div style="position:relative;width:100%;height:100%;overflow:hidden">{ribbons(70, 50, 48, 24, 5, 5.5, 5, ["#F6B8CB", "#6F2C91", "#0433C3"])}</div>', 'Couverture, bandeau, accueil', 66, 46, '#fff', 'width:100%;height:100%')
        + good(f'<div style="position:relative;width:100%;height:100%;overflow:hidden;background:#FBF7FA">{big(34, "right:-6mm;bottom:-6mm;opacity:.25")}<div class="mt abs" style="left:4mm;top:5mm;font:800 8pt Montserrat">Aucun résultat</div></div>', 'État vide, discret', 66, 46, '#fff', 'width:100%;height:100%')
        + bad(f'<div style="position:relative;width:100%;height:100%;overflow:hidden;background:#fff">{ribbons(70, 50, 40, 24, 5, 5.5, 5, ["#F6B8CB", "#6F2C91", "#0433C3"])}<div class="abs" style="left:3mm;top:3mm;right:3mm;background:#E30E0E;color:#fff;border-radius:6mm;text-align:center;font:800 8pt Montserrat;padding:3mm;margin-top:12mm">SOS</div></div>', 'Décor derrière un écran de crise', 66, 46, '#fff', 'width:100%;height:100%')
        + bad(f'<div style="position:relative;width:100%;height:100%;overflow:hidden;background:#fff">{ribbons(70, 50, 40, 24, 5, 5.5, 5, ["#F6B8CB", "#6F2C91", "#0433C3"])}<div class="abs" style="left:4mm;top:4mm;right:4mm;font:400 6.4pt/1.4 Arial">Texte courant posé directement sur les rubans, illisible par endroits.</div></div>', 'Texte sans voile sur le décor', 66, 46, '#fff', 'width:100%;height:100%')
        + '</div>', visclass='tint')
    doc.section(C, 'modifying', 'Modifier', 'Modifying',
        ['<p>Modifications <b>autorisées</b> : changer l\'échelle, recadrer, pivoter la rosace par pas de 90°, choisir un jeu de couleurs dans la palette (page suivante : ordre des teintes), changer l\'opacité d\'un filigrane (10 à 25 %). <b>Interdites</b> : déformer, mettre en miroir, ajouter un contour, un dégradé ou une ombre, remplir avec une photographie, animer en boucle sans bouton de pause.</p>'],
        '<div style="display:grid;grid-template-columns:1fr 1fr;gap:5mm;padding:5mm;height:100%">'
        '<div class="card" style="background:#EAF6EF"><div class="mt" style="font:800 9pt Montserrat;color:#0B7A4B">Autorisé</div><table class="t" style="margin-top:3mm;font-size:9.6pt"><tr><td>Échelle</td><td>Proportions conservées</td></tr><tr><td>Recadrage</td><td>≥ 50 % visible, centre visible</td></tr><tr><td>Rotation</td><td>Pas de 90°</td></tr><tr><td>Couleurs</td><td>Jeux de la palette uniquement</td></tr><tr><td>Opacité</td><td>10 à 25 % en filigrane</td></tr></table></div>'
        '<div class="card" style="background:#FDECEC"><div class="mt" style="font:800 9pt Montserrat;color:#9F0A0A">Interdit</div><table class="t" style="margin-top:3mm;font-size:9.6pt"><tr><td>Déformation</td><td>Étirer, écraser, incliner</td></tr><tr><td>Miroir</td><td>Inverse le sens du tourbillon</td></tr><tr><td>Effets</td><td>Contour, ombre, dégradé, flou</td></tr><tr><td>Remplissage</td><td>Photographie ou motif dans la forme</td></tr><tr><td>Animation</td><td>Boucle sans pause ni mouvement réduit</td></tr></table></div></div>')
    doc.section(C, 'applications', 'Applications', 'Applications',
        ['<p>Exemples d\'application des éléments graphiques sur quelques supports : couverture de rapport, visuel carré pour les réseaux, bandeau d\'en-tête de page web, état vide dans l\'application.</p>'],
        '<div style="display:grid;grid-template-columns:1.3fr 1fr 1.6fr 1fr;gap:4mm;padding:5mm;height:100%;align-items:center">'
        f'<div style="aspect-ratio:210/297;position:relative;overflow:hidden;background:#fff;box-shadow:0 1mm 4mm rgba(0,0,0,.25)">{ribbons(70, 100, 50, 38, 6, 6.8, 6, ["#F6B8CB", "#6F2C91", "#0433C3", "#1B1630", "#E30E0E"])}<div class="abs" style="left:4mm;bottom:5mm;right:4mm"><div class="mt" style="font:800 9pt/1 Montserrat">RAPPORT<br>ANNUEL</div><div style="font:400 4.6pt Arial;margin-top:1.4mm">Xomiton · 2026</div></div></div>'
        f'<div style="aspect-ratio:1;position:relative;overflow:hidden;background:#0433C3;box-shadow:0 1mm 4mm rgba(0,0,0,.25)">{big(70, "right:-14mm;top:-12mm;filter:brightness(0) invert(1);opacity:.16")}<div class="abs mt" style="left:4mm;bottom:4mm;right:4mm;color:#fff;font:800 11pt/1.1 Montserrat">Tu n\'es<br>pas seule.</div><div class="abs" style="left:4mm;top:4mm">{logo_w("mono-blanc", 18)}</div></div>'
        f'<div style="aspect-ratio:3/1;position:relative;overflow:hidden;background:#fff;box-shadow:0 1mm 4mm rgba(0,0,0,.25)">{waves(150, 50, ["#F6B8CB", "#6F2C91", "#0433C3"], 5, 12, 10)}<div class="abs mt" style="left:5mm;bottom:5mm;font:800 11pt/1.1 Montserrat">Ressources</div></div>'
        f'<div style="aspect-ratio:9/16;position:relative;overflow:hidden;background:#FBF7FA;box-shadow:0 1mm 4mm rgba(0,0,0,.25);border-radius:3mm">{big(50, "right:-12mm;bottom:8mm;opacity:.2")}<div class="abs mt" style="left:4mm;top:14mm;font:800 8pt/1.2 Montserrat">Aucun message pour l\'instant</div></div></div>')
