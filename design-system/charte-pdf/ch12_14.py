# -*- coding: utf-8 -*-
"""Chapitres 12 (Supports de communication), 13 (Data visualisation), 14 (Contact)."""
from kit import *
from structure import CHAPTERS
from ch01_03 import good, bad, ch_of
from ch04_06 import ribbons, waves
from ch07_09 import placeholder, ph
from ch10_12 import donut, legend, hbars, line, tilemap, FAKE, CAT

SH = 'box-shadow:0 1mm 4mm rgba(0,0,0,.22)'
MONTHS = ['J', 'F', 'M', 'A', 'M', 'J', 'J', 'A', 'S', 'O', 'N', 'D']
VALS = [42, 48, 51, 47, 63, 70, 66, 82, 91, 88, 97, 112]

def shot(inner, w, h, bg='#fff', extra=''):
    """Maquette de support : cadre à échelle réduite avec ombre."""
    return f'<div style="width:{w}mm;height:{h}mm;position:relative;overflow:hidden;background:{bg};{SH};flex:none;{extra}">{inner}</div>'

def row(items, gap=6, pad='6mm', align='center'):
    return f'<div style="display:flex;gap:{gap}mm;padding:{pad};height:100%;align-items:{align};justify-content:center">{"".join(items)}</div>'

def cap(t):
    return f'<div class="note" style="margin-top:2mm;max-width:60mm">{t}</div>'

def block(inner, caption):
    return f'<div style="text-align:left">{inner}{cap(caption)}</div>'

def build(doc):
    # =============================== CHAPITRE 12 : SUPPORTS DE COMMUNICATION ===============================
    doc.divider(ch_of(12)); C = 12

    # ---- principes
    prios = [('shield-lock-fill', 'La sécurité d\'abord', 'Aucun support ne met une personne en danger : pas de visage identifiable sans accord, pas de mention qui révèle un parcours.', '#0433C3'),
             ('eye-fill', 'Lisible en un coup d\'œil', 'Un message, un geste attendu. Contraste et taille tenus à 3 mètres comme sur un téléphone.', '#6F2C91'),
             ('people-fill', 'Reconnaissable', 'Logo, couleurs et Montserrat suffisent : la rosace et les rubans font le reste.', '#B84B00'),
             ('heart-fill', 'Chaleureux, jamais alarmiste', 'On rassure, on explique, on oriente. Le rouge reste réservé à l\'urgence.', '#E30E0E')]
    cells = ''.join(f'<div class="card" style="border:.2mm solid #e4dce6;display:flex;flex-direction:column;gap:3mm;padding:6mm"><div style="width:16mm;height:16mm;border-radius:50%;background:{c};display:flex;align-items:center;justify-content:center">{icon(i, 8, "#fff")}</div><div class="mt" style="font:800 10.5pt/1.2 Montserrat">{t}</div><div style="font:400 8.2pt/1.5 Arial;color:#3b3650">{d}</div></div>' for i, t, d, c in prios)
    doc.section(C, 'principles', 'Principes et priorités', 'Communication principles',
        ['<p class="lead">Chaque support doit se reconnaître et se lire sans effort, sans jamais exposer personne.</p>',
         '<p>Ce chapitre applique la charte aux supports courants : papeterie, réseaux sociaux, partage et e-mails, présentations, imprimés et goodies. Les mises en page de base (grilles, formats, espacements) sont au chapitre 11 ; les graphiques au chapitre 13.</p>'],
        f'<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:5mm;padding:7mm;height:100%;align-content:center">{cells}</div>')

    # ---- papeterie
    letter = shot(f'<div class="abs" style="left:7mm;top:7mm">{logo_w("couleur", 26)}</div>'
                  '<div class="abs" style="left:7mm;top:30mm;width:60mm;font:400 4.6pt/1.6 Arial;color:#3b3650">Madame, Monsieur,<br><br>Texte de la lettre en Arial 10 pt, interligne 1,5, aligné à gauche. Le bloc adresse s\'aligne sur la marge gauche, comme le logo.<br><br>Cordialement,</div>'
                  '<div class="abs" style="left:7mm;bottom:7mm;right:7mm;border-top:.25mm solid #D8CFDC;padding-top:2mm;font:400 4pt Arial;color:#5B5670">Xomiton · xomiton.org · [adresse à renseigner]</div>'
                  f'{sym("rosace-couleur", 40, "position:absolute;right:-14mm;bottom:12mm;opacity:.12")}', 74, 105)
    card = shot(f'<div class="abs" style="left:5mm;top:5mm">{logo_w("couleur", 22)}</div><div class="abs mt" style="left:5mm;bottom:5mm;font:800 6.4pt/1.3 Montserrat">Prénom Nom<br><span style="font:400 5pt Arial">Fonction · prenom@xomiton.org</span></div>', 55, 35) \
        + '<div style="height:3mm"></div>' + shot(f'{sym("rosace-couleur", 70, "position:absolute;right:-18mm;top:-18mm;filter:brightness(0) invert(1);opacity:.16")}<div class="abs" style="left:5mm;top:5mm">{logo_w("mono-blanc", 22)}</div><div class="abs" style="left:5mm;bottom:5mm;color:#fff;font:400 5pt/1.5 Arial">xomiton.org</div>', 55, 35, '#0433C3')
    badge = shot(f'<div style="height:11mm;background:#0433C3"></div><div class="abs" style="left:4mm;top:4mm">{logo_w("mono-blanc", 24)}</div><div style="padding:4mm"><div style="width:20mm;height:20mm;border-radius:50%;background:#EBEFFA;display:flex;align-items:center;justify-content:center">{icon("person-fill", 11, "#0433C3")}</div><div class="mt" style="font:800 7.6pt/1.2 Montserrat;margin-top:3mm">Prénom Nom</div><div style="font:400 5pt Arial;color:#5B5670">Conseiller·ère pair·e</div></div><div class="abs" style="left:0;right:0;bottom:0;height:2.4mm;background:#E30E0E"></div>', 54, 86)
    doc.section(C, 'stationery', 'Papeterie et badges', 'Stationery',
        ['<p>Le <b>papier à en-tête</b> porte le logo en haut à gauche, texte aligné sur la même marge, pied de page en filet fin. La <b>carte de visite</b> (85 × 55 mm) existe en deux faces : blanche avec logo couleur, bleue avec logo blanc. Le <b>badge</b> d\'équipe affiche prénom, fonction et bandeau bleu ; le liseré rouge du bas signale « personne habilitée ».</p>',
         '<p>Papier non couché blanc ou ivoire, impression bleu Pantone à valider sur épreuve. Aucune mention de thématique sensible sur l\'enveloppe : voir « Supports discrets ».</p>'],
        row([block(letter, 'Papier à en-tête A4 (réduit)'), block(card, 'Carte de visite 85 × 55 mm, deux faces'), block(badge, 'Badge d\'équipe 54 × 86 mm')], 14) + FAKE)

    # ---- réseaux sociaux
    sq = shot(f'{ph(IMG + "hero-09.jpg", pos="50% 60%")}<div class="abs" style="left:0;right:0;bottom:0;background:linear-gradient(transparent,rgba(14,16,32,.85));height:55%"></div><div class="abs mt" style="left:5mm;right:5mm;bottom:11mm;color:#fff;font:800 12pt/1.05 Montserrat">Tu n\'es pas seule.</div><div class="abs" style="left:5mm;bottom:4mm">{logo_w("mono-blanc", 22)}</div>', 56, 56)
    vert = shot(f'<div style="position:absolute;inset:0;background:#0433C3"></div>{sym("rosace-couleur", 90, "position:absolute;right:-30mm;top:-10mm;filter:brightness(0) invert(1);opacity:.13")}<div class="abs mt" style="left:5mm;right:5mm;top:14mm;color:#fff;font:800 13pt/1.05 Montserrat">Que faire<br>en cas<br>d\'agression ?</div><div class="abs" style="left:5mm;right:5mm;top:37mm;color:#fff;font:400 5.6pt/1.6 Arial">1. Se mettre en sécurité<br>2. Demander de l\'aide<br>3. Appeler les autorités<br>4. Signaler le cas</div><div class="abs" style="left:5mm;bottom:5mm">{logo_w("mono-blanc", 22)}</div>', 48, 60)
    story = shot(f'{ph(HOME + "hero-12.jpg", pos="50% 50%")}<div class="abs" style="left:4mm;right:4mm;bottom:20mm;background:#fff;border-radius:2mm;padding:3mm"><div class="mt" style="font:800 7pt/1.2 Montserrat">Un espace safe pour apprendre</div><div style="font:400 5pt Arial;color:#5B5670;margin-top:1mm">Glisse pour découvrir</div></div><div class="abs" style="left:4mm;top:5mm">{logo_w("mono-blanc", 20)}</div><div class="abs" style="left:4mm;right:4mm;top:0;height:30mm;border:0"></div>', 34, 60)
    avat = shot(f'<div style="position:absolute;inset:0;background:#0433C3;display:flex;align-items:center;justify-content:center">{sym("rosace-blanc", 30)}</div>', 30, 30, extra='border-radius:50%')
    cover = shot(f'<div style="position:absolute;inset:0;background:#EBEFFA"></div>{waves(100, 22, ["#0433C3", "#6F2C91", "#E30E0E"], 2.4, 4, 5)}<div class="abs" style="left:5mm;top:7mm">{logo_w("couleur", 28)}</div>', 100, 26)
    doc.section(C, 'social', 'Réseaux sociaux', 'Social media',
        ['<p>Formats : <b>carré 1080 × 1080</b>, <b>vertical 1080 × 1350</b>, <b>story 1080 × 1920</b> (zone sûre : 250 px libres en haut et en bas), <b>couverture 1500 × 500</b>, <b>avatar</b> carré (la rosace seule, centrée, 30 % de marge). Un message en quatre mots au plus, logo toujours présent, contraste de 4,5:1 minimum.</p>',
         '<p>Chaque image accompagne un texte alternatif et, pour une vidéo, des sous-titres. Pas de message qui dépend d\'un seul réseau : le partage se fait par lien, sans connexion à un réseau social.</p>'],
        row([block(sq, 'Publication carrée'), block(vert, 'Publication verticale 4:5'), block(story, 'Story 9:16'),
             f'<div style="display:flex;flex-direction:column;gap:5mm">{block(cover, "Couverture 3:1")}{block(avat, "Avatar rond")}</div>'], 9) + FAKE)

    # ---- aperçus de partage et e-mails
    og = shot(f'<div style="position:absolute;inset:0;background:#0433C3"></div>{sym("rosace-couleur", 90, "position:absolute;right:-22mm;top:-14mm;filter:brightness(0) invert(1);opacity:.14")}<div class="abs" style="left:6mm;top:6mm">{logo_w("mono-blanc", 30)}</div><div class="abs mt" style="left:6mm;bottom:6mm;right:30mm;color:#fff;font:800 10.5pt/1.05 Montserrat">Votre espace safe d\'apprentissage</div>', 80, 42)
    mail = shot(f'<div style="height:8mm;background:#fff;border-bottom:.2mm solid #e4dce6;display:flex;align-items:center;padding:0 5mm">{logo_w("couleur", 22)}</div><div style="padding:5mm"><div class="mt" style="font:800 9pt/1.15 Montserrat">Nouvelle ressource : connaître ses droits</div><div style="font:400 5.2pt/1.6 Arial;color:#3b3650;margin:2mm 0 3mm">Bonjour, une nouvelle fiche est disponible. Elle se lit en cinq minutes.</div><div style="display:inline-block;background:#0433C3;color:#fff;font:700 5.6pt Montserrat;padding:2.4mm 5mm;border-radius:1.2mm">Lire la fiche</div></div><div class="abs" style="left:5mm;right:5mm;bottom:4mm;border-top:.2mm solid #D8CFDC;padding-top:2mm;font:400 4pt/1.5 Arial;color:#5B5670">Tu reçois ce message car tu t\'es abonné·e. Se désabonner en un clic · Confidentialité</div>', 70, 80, '#FBF7FA')
    meta = ('<div style="font:400 7.4pt/1.7 ui-monospace,Consolas,monospace;background:#1B1630;color:#EDEAF7;border-radius:2mm;padding:5mm;width:68mm">'
            '&lt;meta property="og:title" content="Xomiton"&gt;<br>&lt;meta property="og:image"<br>&nbsp;&nbsp;content=".../og/accueil.jpg"&gt;<br>&lt;meta property="og:image:alt"<br>&nbsp;&nbsp;content="Logo Xomiton sur fond bleu"&gt;<br>&lt;meta name="twitter:card"<br>&nbsp;&nbsp;content="summary_large_image"&gt;</div>')
    doc.section(C, 'share', 'Aperçus de partage et e-mails', 'Share previews and email',
        ['<p>L\'<b>aperçu de partage</b> (OpenGraph et Twitter) mesure <b>1200 × 630 px</b> : fond bleu, logo blanc, titre en Montserrat 800, rosace en filigrane ; une image par type de page, avec un texte alternatif. Poids visé : moins de 300 Ko.</p>',
         '<p>L\'<b>e-mail</b> tient en une colonne de 600 px : logo, un titre, deux phrases, un bouton bleu. Texte réel (pas de message sous forme d\'image), alternative texte, lien de désabonnement en un clic. Les e-mails qui concernent un dossier ne contiennent aucun détail du signalement.</p>'],
        row([block(og, 'Aperçu de partage 1200 × 630 (réduit)'), block(mail, 'E-mail d\'information (600 px)'), block(meta, 'Balises à renseigner par page')], 8) + FAKE)

    # ---- présentations
    sl_title = shot(f'<div style="position:absolute;inset:0;background:#0433C3"></div>{sym("rosace-couleur", 80, "position:absolute;right:-20mm;top:-18mm;filter:brightness(0) invert(1);opacity:.14")}<div class="abs" style="left:6mm;top:6mm">{logo_w("mono-blanc", 26)}</div><div class="abs mt" style="left:6mm;bottom:8mm;color:#fff;font:800 14pt/1.05 Montserrat">Titre de la<br>présentation</div>', 80, 45)
    sl_content = shot(f'<div class="abs mt" style="left:6mm;top:6mm;width:42mm;font:800 8pt/1.15 Montserrat">Un message par diapositive</div><div class="abs" style="left:6mm;top:18mm;width:34mm;font:400 5.6pt/1.7 Arial;color:#3b3650">• Trois points au plus<br>• Corps du texte : 24 pt<br>• Un visuel dominant</div><div class="abs" style="right:6mm;top:8mm;bottom:8mm;width:30mm;border-radius:2mm;overflow:hidden">{ph(IMG + "hero-13.jpg", pos="30% 50%")}</div><div class="abs" style="left:6mm;bottom:3mm;font:400 4pt Arial;color:#5B5670">Xomiton · 3</div>', 80, 45)
    sl_data = shot(f'<div class="abs mt" style="left:6mm;top:5mm;font:800 8pt/1.1 Montserrat">Les signalements ont doublé en un an</div><div class="abs" style="left:6mm;right:6mm;top:12mm">{line(VALS, 150, 62, "#0433C3", MONTHS)}</div>', 80, 45)
    sl_end = shot(f'<div style="position:absolute;inset:0;background:#1B1630"></div><div class="abs" style="left:6mm;top:6mm">{logo_w("sombre", 26)}</div><div class="abs mt" style="left:6mm;bottom:9mm;color:#fff;font:800 12pt/1.1 Montserrat">Merci.</div><div class="abs" style="left:6mm;bottom:4mm;color:#bdb8c8;font:400 5pt Arial">xomiton.org · [adresse à renseigner]</div>', 80, 45)
    doc.section(C, 'slides', 'Présentations', 'Presentations',
        ['<p>Format <b>16:9</b> (1920 × 1080). Quatre gabarits : titre (aplat bleu, rosace en filigrane), contenu (titre en Montserrat 800, texte en Arial 24 pt minimum, un visuel), données (une idée, un graphique du chapitre 13), clôture (fond encre, contacts). Pas de transitions animées, pas plus de trois puces.</p>',
         '<p>Les présentations exportées en PDF sont balisées et portent un titre de document ; les images ont une alternative textuelle.</p>'],
        f'<div style="display:grid;grid-template-columns:1fr 1fr;gap:7mm;padding:8mm 20mm;height:100%;align-content:center;justify-items:center">{sl_title}{sl_content}{sl_data}{sl_end}</div>' + FAKE)

    # ---- imprimés
    fold = ''.join(shot(c, 40, 56) for c in [
        f'<div style="position:absolute;inset:0;background:#0433C3"></div>{sym("rosace-couleur", 56, "position:absolute;right:-20mm;bottom:-12mm;filter:brightness(0) invert(1);opacity:.14")}<div class="abs" style="left:5mm;top:5mm">{logo_w("mono-blanc", 24)}</div><div class="abs mt" style="left:5mm;bottom:10mm;color:#fff;font:800 9.4pt/1.05 Montserrat">Un espace<br>safe pour<br>toutes et tous</div>',
        '<div style="padding:6mm"><div class="mt" style="font:800 8pt Montserrat">Que faire ?</div><div style="font:400 5pt/1.8 Arial;margin-top:3mm;color:#3b3650">1. Se mettre en sécurité<br>2. Demander de l\'aide<br>3. Appeler les autorités<br>4. Signaler le cas</div></div>',
        f'<div style="padding:6mm"><div class="mt" style="font:800 8pt Montserrat">Nous joindre</div><div style="font:400 5pt/1.8 Arial;margin-top:3mm;color:#3b3650">xomiton.org<br>[numéro à renseigner]</div><div style="margin-top:4mm;width:16mm;height:16mm;background:repeating-conic-gradient(#1B1630 0 25%,#fff 0 50%) 0 0/4mm 4mm"></div><div class="note" style="margin-top:1mm">Code QR (exemple)</div></div>'])
    poster = shot(f'<div style="position:absolute;inset:0;background:#0433C3"></div>{ribbons(70, 100, 50, 80, 6, 6.8, 6, ["#F6B8CB", "#6F2C91", "#0433C3", "#1B1630", "#E30E0E"])}<div class="abs" style="left:5mm;top:5mm">{logo_w("mono-blanc", 24)}</div><div class="abs mt" style="left:5mm;bottom:8mm;color:#fff;font:800 14pt/1.0 Montserrat">Tu n\'es<br>pas seule.</div>', 50, 71)
    tote = shot(f'<div style="position:absolute;left:8mm;right:8mm;top:-4mm;height:20mm;border:2mm solid #8E88A0;border-bottom:0;border-radius:12mm 12mm 0 0"></div><div class="abs" style="left:0;right:0;top:14mm;bottom:0;background:#F3EDF4"></div><div class="abs" style="left:0;right:0;top:26mm;display:flex;justify-content:center">{sym("rosace-couleur", 18)}</div><div class="abs mt" style="left:0;right:0;top:48mm;text-align:center;font:800 8pt Montserrat;color:#0433C3">xomiton</div>', 46, 62, '#fff')
    sticker = '<div style="display:flex;gap:4mm;margin-top:4mm">' + ''.join(shot(f'<div style="position:absolute;inset:0;background:{b};display:flex;align-items:center;justify-content:center">{sym(s, 12)}</div>', 18, 18, extra='border-radius:50%') for b, s in (('#fff', 'rosace-couleur'), ('#0433C3', 'rosace-blanc'), ('#1B1630', 'rosace-blanc'))) + '</div>'
    doc.section(C, 'print', 'Dépliants, affiches et goodies', 'Leaflets, posters and merchandise',
        ['<p>Le <b>dépliant à trois volets</b> (A4 plié en trois, 99 mm par volet) place le message en couverture, la démarche « Que faire ? » au centre, les contacts et un code QR au dos. L\'<b>affiche</b> suit la règle des quatre mots. Les <b>goodies</b> (sac, autocollants, badges) portent la rosace seule ou le logo, jamais de slogan long.</p>',
         '<p>Impression : papier recyclé ou certifié, bleu et rouge aplats à valider sur épreuve ; code QR avec marge de 4 modules et lien court sans suivi publicitaire.</p>'],
        row([block(f'<div style="display:flex;gap:1mm">{fold}</div>', 'Dépliant 3 volets (extérieur et intérieur, réduits)'), block(poster, 'Affiche A3 (réduite)'), f'<div>{block(tote, "Sac en toile")}{sticker}{cap("Autocollants : rosace sur fond blanc, bleu ou encre")}</div>'], 6) + FAKE)

    # ---- supports discrets
    env_std = shot(f'<div class="abs" style="left:5mm;top:5mm">{logo_w("couleur", 20)}</div><div class="abs" style="right:6mm;bottom:6mm;font:400 5pt/1.5 Arial;width:34mm;color:#3b3650">Prénom Nom<br>Adresse<br>Code postal Ville</div>', 78, 44)
    env_dis = shot('<div class="abs" style="left:5mm;top:5mm;font:400 4.6pt Arial;color:#5B5670">Expéditeur : [nom neutre]</div><div class="abs" style="right:6mm;bottom:6mm;font:400 5pt/1.5 Arial;width:34mm;color:#3b3650">Prénom Nom<br>Adresse<br>Code postal Ville</div>', 78, 44)
    sms = shot('<div style="padding:5mm"><div style="background:#fff;border-radius:2.4mm;padding:3mm;font:400 5.4pt/1.5 Arial;width:46mm;border:.2mm solid #e4dce6">Rappel : ton rendez-vous est confirmé. Réponds OUI ou NON.</div><div class="note" style="margin-top:2mm">Notification neutre : ni nom de l\'association, ni thème.</div></div>', 70, 36, '#F6F2F7')
    doc.section(C, 'discreet', 'Supports discrets', 'Discreet materials',
        ['<p>Une personne peut vivre avec quelqu\'un qui ne doit pas savoir qu\'elle contacte Xomiton. Pour chaque envoi (courrier, SMS, notification, e-mail), on prévoit une <b>variante neutre</b> : expéditeur au nom neutre, objet sans thème, aucun logo ni mot sensible, contenu limité à l\'action à faire.</p>',
         '<p>Par défaut, tout message adressé à une personne suivie est neutre ; la personne peut choisir de recevoir la version marquée. L\'application propose aussi une icône et un nom d\'application discrets, et le bouton « Quitter ce site » (chapitre 9 du design system).</p>'],
        row([block(good(env_std, 'Enveloppe marquée (diffusion publique)', 82, 52, '#fff', 'width:82mm'), 'Pour les envois publics et partenaires'), block(good(env_dis, 'Enveloppe neutre (envoi à une personne suivie)', 82, 52, '#fff', 'width:82mm'), 'Pour toute personne suivie'), block(sms, 'SMS ou notification neutre')], 9) + FAKE)

    # ---- ton et rédaction
    pairs = [('Tu n\'es pas seul·e, nous sommes là.', 'Alerte : situation de violence détectée !', 'On rassure et on propose, on ne dramatise pas.'),
             ('Tu peux signaler sans te présenter.', 'Vous devez impérativement vous identifier.', 'On dit ce qui est possible et simple.'),
             ('Nous avons reçu ton message. Une personne te répond sous 24 h.', 'Votre demande est en cours de traitement.', 'On annonce le délai concret.'),
             ('Ton alerte est partie.', 'Opération effectuée avec succès.', 'On confirme avec des mots humains.')]
    rows = ''.join(f'<tr><td style="color:#0B7A4B"><b>✓</b> {a}</td><td style="color:#9F0A0A"><b>✕</b> {b}</td><td>{c}</td></tr>' for a, b, c in pairs)
    doc.section(C, 'tone', 'Ton et rédaction', 'Tone and writing',
        ['<p>Xomiton parle <b>simplement, avec chaleur et précision</b>. Phrases courtes (20 mots au plus), un verbe d\'action par bouton, pas de jargon médical ou juridique sans explication, aucune formule culpabilisante. Les chiffres s\'écrivent avec une espace insécable (1 284, 4,5 %).</p>',
         '<p><b>À valider :</b> le choix entre tutoiement et vouvoiement, et le système d\'écriture inclusive, sont des décisions encore ouvertes. Les exemples ci-contre tutoient, par hypothèse ; tant que le choix n\'est pas fait, on rédige à l\'infinitif ou à la forme neutre.</p>'],
        f'<div style="padding:7mm 9mm"><table class="t" style="font-size:8.6pt"><tr><th style="width:36%">On écrit</th><th style="width:30%">On évite</th><th>Pourquoi</th></tr>{rows}</table></div>')

    # ---- liste de contrôle
    items = [('Logo', 'zone de protection respectée, taille minimale tenue (24 px ou 12 mm), bonne variante pour le fond'),
             ('Couleurs', 'bleu pour l\'action, rouge uniquement pour l\'urgence, jamais bleu sur rouge'),
             ('Contraste', '4,5:1 pour le texte courant, 3:1 pour les grands titres et les éléments graphiques'),
             ('Typographie', 'Montserrat pour les titres, Arial pour le texte, corps lisible à la distance prévue'),
             ('Images', 'droits confirmés, accord écrit des personnes, texte alternatif rédigé'),
             ('Sécurité', 'aucun détail qui identifie une personne, variante discrète prévue si envoi à une personne suivie'),
             ('Accessibilité', 'sous-titres, document balisé, aucune information portée par la couleur seule'),
             ('Contenu', 'ton relu, chiffres sourcés et datés, contacts à jour'),
             ('Technique', 'poids de l\'image, format, lien court sans suivi, code QR testé')]
    lis = ''.join(f'<div style="display:flex;gap:4mm;padding:2.4mm 0;border-bottom:.2mm solid #E4DCE6"><span style="width:5mm;height:5mm;border:.4mm solid #0433C3;border-radius:1mm;flex:none;margin-top:.6mm"></span><div style="font:400 8.4pt/1.45 Arial"><b class="mt" style="font-weight:800">{a}.</b> {b}.</div></div>' for a, b in items)
    doc.section(C, 'checklist', 'Liste de contrôle avant diffusion', 'Release checklist',
        ['<p>Avant de publier ou d\'imprimer, une personne autre que l\'auteur·rice relit ce support avec la liste ci-contre. Pour un support qui concerne les violences ou les signalements, la relecture inclut une personne de l\'équipe psychosociale.</p>'],
        f'<div style="padding:7mm 12mm;columns:2;column-gap:14mm">{lis}</div>')

    # =============================== CHAPITRE 13 : DATA VISUALISATION ===============================
    doc.divider(ch_of(13)); C = 13
    pr = [('bullseye', 'Un message', 'Chaque graphique répond à une seule question, écrite dans son titre.'),
          ('rulers', 'Honnête', 'Axe à zéro pour les barres, échelles identiques pour comparer, pas de 3D.'),
          ('eye', 'Lisible sans couleur', 'Étiquettes directes, motifs ou formes en plus de la couleur.'),
          ('shield-check', 'Protecteur', 'Les petits effectifs sont masqués : moins de 5 personnes, pas de valeur.')]
    cells = ''.join(f'<div class="card" style="border:.2mm solid #e4dce6;display:flex;flex-direction:column;gap:3mm;padding:6mm"><div style="width:15mm;height:15mm;border-radius:50%;background:#6F2C91;display:flex;align-items:center;justify-content:center">{icon(i, 7.5, "#fff")}</div><div class="mt" style="font:800 10.5pt Montserrat">{t}</div><div style="font:400 8.2pt/1.5 Arial;color:#3b3650">{d}</div></div>' for i, t, d in pr)
    doc.section(C, 'principles', 'Principes de la data visualisation', 'Data visualisation principles',
        ['<p class="lead">Une donnée bien montrée aide à décider ; mal montrée, elle trompe ou expose.</p>',
         '<p>Le chapitre 10 traite des infographies de communication. Ce chapitre fixe les règles des <b>graphiques de données</b> : rapports, tableau de bord, exports. Ils reposent sur Chart.js et sur les jetons de couleur du design system.</p>'],
        f'<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:5mm;padding:7mm;height:100%;align-content:center">{cells}</div>' + FAKE)

    # ---- choisir
    mini_bar = '<svg viewBox="0 0 40 24" style="width:20mm"><rect x="3" y="10" width="7" height="12" fill="#0433C3"/><rect x="14" y="4" width="7" height="18" fill="#0433C3"/><rect x="25" y="13" width="7" height="9" fill="#0433C3"/></svg>'
    mini_line = '<svg viewBox="0 0 40 24" style="width:20mm"><polyline points="3,20 12,14 21,16 30,7 38,4" fill="none" stroke="#0433C3" stroke-width="1.6"/></svg>'
    mini_donut = '<div style="width:14mm;height:14mm;border-radius:50%;background:conic-gradient(#0433C3 0 55%,#6F2C91 55% 80%,#C2255C 80% 100%);position:relative"><div style="position:absolute;inset:3.6mm;background:#fff;border-radius:50%"></div></div>'
    mini_scatter = '<svg viewBox="0 0 40 24" style="width:20mm"><g fill="#0433C3"><circle cx="6" cy="19" r="1.6"/><circle cx="12" cy="15" r="1.6"/><circle cx="18" cy="16" r="1.6"/><circle cx="24" cy="9" r="1.6"/><circle cx="31" cy="11" r="1.6"/><circle cx="36" cy="5" r="1.6"/></g></svg>'
    mini_table = '<svg viewBox="0 0 40 24" style="width:20mm"><g fill="none" stroke="#8E88A0" stroke-width=".8"><rect x="3" y="3" width="34" height="18"/><line x1="3" y1="9" x2="37" y2="9"/><line x1="3" y1="15" x2="37" y2="15"/><line x1="16" y1="3" x2="16" y2="21"/></g></svg>'
    mini_map = '<div style="width:20mm;display:grid;grid-template-columns:repeat(3,1fr);gap:.6mm">' + ''.join(f'<i style="height:3.4mm;background:{c}"></i>' for c in ('#EBEFFA', '#AFBEEC', '#4366D2', '#4366D2', '#032BA6', '#AFBEEC')) + '</div>'
    ch_rows = [(mini_bar, 'Comparer des catégories', 'Barres horizontales à base zéro, triées', 'Signalements par département'),
               (mini_line, 'Suivre une évolution', 'Courbe, 12 points au plus, extrémités étiquetées', 'Signalements par mois'),
               (mini_donut, 'Montrer une répartition', 'Anneau (6 parts au plus) ou barre empilée', 'Dossiers par statut'),
               (mini_scatter, 'Relier deux mesures', 'Nuage de points, axes légendés', 'Âge et délai de réponse'),
               (mini_map, 'Situer sur le territoire', 'Carte en tuiles, échelle bleue', 'Signalements par département'),
               (mini_table, 'Donner des valeurs exactes', 'Tableau trié, alignement des nombres à droite', 'Liste des cas')]
    rr = ''.join(f'<tr><td style="width:26mm">{a}</td><td><b>{b}</b></td><td>{c}</td><td style="color:#5B5670">{d}</td></tr>' for a, b, c, d in ch_rows)
    doc.section(C, 'choose', 'Choisir le bon graphique', 'Choosing the chart',
        ['<p>On part de la <b>question</b>, pas du graphique. Le tableau ci-contre donne le choix par défaut ; en cas de doute, un tableau vaut mieux qu\'un graphique ambigu. Interdits : camembert de plus de six parts, 3D, double axe vertical, graphique en aires empilées pour comparer.</p>'],
        f'<div style="padding:6mm 9mm"><table class="t" style="font-size:8.6pt"><tr><th>Forme</th><th>Question</th><th>Choix par défaut</th><th>Exemple Xomiton</th></tr>{rr}</table></div>')

    # ---- palettes
    def chips(cols, w=15):
        return ''.join(f'<div style="width:{w}mm;text-align:center"><div style="height:13mm;background:{c};border-radius:1.4mm"></div><div style="font:700 5.6pt Arial;margin-top:1mm">{c}</div></div>' for c in cols)
    seq = ['#EBEFFA', '#AFBEEC', '#4366D2', '#032BA6', '#02134A']
    div = ['#02134A', '#4366D2', '#F3EDF4', '#E58A8A', '#9F0A0A']
    gray = ''.join(f'<div style="width:15mm;text-align:center"><div style="height:13mm;background:{c};filter:grayscale(1);border-radius:1.4mm"></div></div>' for c in CAT)
    doc.section(C, 'palettes', 'Palettes de données', 'Data palettes',
        ['<p>Trois palettes, trois usages. <b>Catégorielle</b> : six couleurs au plus, choisies pour rester distinguables en niveaux de gris (bleu, violet, rose, orange, vert, gris). <b>Séquentielle</b> : cinq paliers de bleu pour une quantité ordonnée. <b>Divergente</b> : bleu foncé vers rouge foncé autour d\'un neutre, seulement quand une valeur centrale a un sens (écart à l\'objectif).</p>',
         '<p>Le rouge ne désigne jamais une catégorie ordinaire : il signale un écart ou un seuil. Les jetons correspondants sont <code>--xm-chart-1</code> à <code>-6</code>.</p>'],
        f'<div style="padding:6mm 9mm;display:grid;grid-template-columns:1fr;gap:5mm"><div><div class="lbl" style="margin-bottom:1.6mm">Catégorielle (6)</div><div style="display:flex;gap:2.4mm">{chips(CAT)}</div><div class="lbl" style="margin:2mm 0 1mm">Même palette en niveaux de gris : toutes les teintes restent séparables</div><div style="display:flex;gap:2.4mm">{gray}</div></div>'
        f'<div style="display:grid;grid-template-columns:1fr 1fr;gap:10mm"><div><div class="lbl" style="margin-bottom:1.6mm">Séquentielle (bleu)</div><div style="display:flex;gap:2.4mm">{chips(seq)}</div></div><div><div class="lbl" style="margin-bottom:1.6mm">Divergente (écart à l\'objectif)</div><div style="display:flex;gap:2.4mm">{chips(div)}</div></div></div></div>')

    # ---- anatomie
    ann = lambda n, t, x, y: f'<span style="position:absolute;left:{x}mm;top:{y}mm;width:5mm;height:5mm;border-radius:50%;background:#E30E0E;color:#fff;font:800 6pt/5mm Montserrat;text-align:center">{n}</span>'
    leg = ['<b>1. Titre-message</b> : la conclusion, pas le sujet (« Les signalements ont doublé »).', '<b>2. Sous-titre</b> : mesure, période, unité.', '<b>3. Série mise en avant</b> : une couleur d\'accent ; le reste en gris.', '<b>4. Étiquettes directes</b> : première et dernière valeur, légende au plus près.', '<b>5. Axe</b> : unité écrite, repères discrets, base zéro pour les barres.', '<b>6. Source et date</b> : « Source : Xomiton, données au 30/09/2026 ».']
    doc.section(C, 'anatomy', 'Anatomie d\'un graphique', 'Chart anatomy',
        ['<p>Chaque graphique porte les six éléments numérotés ci-contre. Le titre dit la conclusion, la couleur d\'accent désigne ce qu\'il faut voir, la source rend la donnée vérifiable.</p>'],
        f'<div style="display:grid;grid-template-columns:1.25fr 1fr;gap:9mm;padding:7mm 9mm;height:100%;align-items:center"><div class="card" style="border:.2mm solid #e4dce6;position:relative;padding:7mm 7mm 11mm"><div class="mt" style="font:800 13pt/1.1 Montserrat">Les signalements ont doublé en un an</div><div style="font:400 7.6pt Arial;color:#5B5670;margin:1mm 0 3mm">Signalements par mois, 2026</div>{line(VALS, 150, 66, "#0433C3", MONTHS)}<div style="font:400 6pt Arial;color:#5B5670;margin-top:2mm">Source : Xomiton, données au 30/09/2026 (fictives)</div>{ann(1, "", -2.5, 7)}{ann(2, "", -2.5, 16)}{ann(3, "", 58, 56)}{ann(4, "", 112, 31)}{ann(5, "", -2.5, 68)}{ann(6, "", -2.5, 77)}</div><div style="font:400 8.4pt/1.6 Arial">{"<br>".join(leg)}</div></div>' + FAKE)

    # ---- tableaux de bord
    k = lambda l, v, d, c='#0433C3': f'<div style="background:#fff;border-radius:1.6mm;padding:3mm"><div style="font:400 5pt Arial;color:#5B5670">{l}</div><div class="mt" style="font:800 15pt/1.1 Montserrat;color:{c}">{v}</div><div style="font:700 5pt Arial;color:#5B5670">{d}</div></div>'
    dash = (f'<div style="display:flex;width:170mm;height:100mm;background:#F6F2F7;border:.4mm solid #1B1630;border-radius:2mm;overflow:hidden;{SH}">'
            '<div style="width:24mm;background:#fff;border-right:.2mm solid #e4dce6;padding:4mm 3mm"><div style="height:2.4mm;background:#0433C3;border-radius:1mm"></div>' + ''.join('<div style="height:2.4mm;background:#E4DCE6;border-radius:1mm;margin-top:4mm"></div>' for _ in range(5)) + '</div>'
            '<div style="flex:1;padding:4mm"><div style="display:flex;justify-content:space-between;align-items:center"><div class="mt" style="font:800 9pt Montserrat">Tableau de bord</div><div style="font:700 5pt Arial;background:#fff;border:.2mm solid #D8CFDC;padding:1mm 3mm;border-radius:10mm">30 derniers jours ▾</div></div>'
            '<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:2.4mm;margin-top:3mm">' + k('Cas ouverts', '1 284', '+12 %') + k('SOS 24 h', '17', '15 accusés', '#E30E0E') + k('Réponse médiane', '4,5 min', '−0,8 min') + k('Sans réponse', '3', 'à traiter') + '</div>'
            f'<div style="display:grid;grid-template-columns:1.4fr 1fr;gap:2.4mm;margin-top:2.4mm"><div style="background:#fff;border-radius:1.6mm;padding:3mm"><div class="mt" style="font:800 6pt Montserrat">Cas par mois</div>{line(VALS, 110, 46, "#0433C3", MONTHS)}</div><div style="background:#fff;border-radius:1.6mm;padding:3mm"><div class="mt" style="font:800 6pt Montserrat;margin-bottom:2mm">Par statut</div>{donut([("Nouveau", 214, "#0433C3"), ("En cours", 402, "#C2255C"), ("Résolu", 311, "#0B7A4B")], 30)}</div></div></div></div>')
    rules = ['<b>Lecture en F</b> : les indicateurs d\'attention (SOS, sans réponse) en haut à gauche.', '<b>Quatre indicateurs</b> au plus dans la première ligne, puis deux graphiques.', '<b>Filtres</b> en haut, toujours visibles, avec la période affichée.', '<b>Le rouge n\'apparaît que sur un indicateur d\'urgence.</b>', '<b>Chaque carte a un lien</b> « Voir le détail » vers le tableau correspondant.', '<b>Actualisation</b> : l\'heure de dernière mise à jour est affichée.']
    doc.section(C, 'dashboard', 'Tableaux de bord', 'Dashboards',
        ['<p>Le tableau de bord d\'équipe répond à trois questions : <b>qu\'est-ce qui est urgent ? où en sommes-nous ? où est le retard ?</b> Les composants sont ceux du design system (<code>x-stat</code>, <code>x-chart</code>, <code>x-datatable</code>).</p>'],
        f'<div style="display:grid;grid-template-columns:1.5fr 1fr;gap:8mm;padding:6mm 9mm;height:100%;align-items:center"><div>{dash}</div><div style="font:400 8.4pt/1.7 Arial">{"<br>".join(rules)}</div></div>' + FAKE)

    # ---- accessibilité
    tbl = '<table class="t" style="font-size:7.4pt"><caption style="text-align:left;font:700 7pt Arial;margin-bottom:1mm">Signalements par département (équivalent du graphique)</caption><tr><th scope="col">Département</th><th scope="col">Signalements</th></tr><tr><td>Littoral</td><td>131</td></tr><tr><td>Atlantique</td><td>118</td></tr><tr><td>Ouémé</td><td>88</td></tr><tr><td>Couffo</td><td>données masquées</td></tr></table>'
    code = ('<div style="font:400 7pt/1.7 ui-monospace,Consolas,monospace;background:#1B1630;color:#EDEAF7;border-radius:2mm;padding:5mm">'
            '&lt;figure role="group" aria-labelledby="t1"&gt;<br>&nbsp;&nbsp;&lt;figcaption id="t1"&gt;Signalements par mois&lt;/figcaption&gt;<br>&nbsp;&nbsp;&lt;canvas role="img"<br>&nbsp;&nbsp;&nbsp;&nbsp;aria-label="Hausse de 42 à 112 en un an"&gt;&lt;/canvas&gt;<br>&nbsp;&nbsp;&lt;details&gt;&lt;summary&gt;Voir les données&lt;/summary&gt;<br>&nbsp;&nbsp;&nbsp;&nbsp;&lt;table&gt;…&lt;/table&gt;&lt;/details&gt;<br>&lt;/figure&gt;</div>')
    pat = ''.join(f'<div style="display:flex;align-items:center;gap:2mm;margin:1.4mm 0;font:400 7.4pt Arial"><i style="width:14mm;height:5mm;border-radius:.8mm;display:inline-block;border:.2mm solid #1B1630;background:{b}"></i>{t}</div>' for b, t in (('#0433C3', 'Bleu : plein'), ('repeating-linear-gradient(45deg,#6F2C91 0 1mm,#fff 1mm 2mm)', 'Violet : hachures'), ('radial-gradient(#C2255C 28%,transparent 30%) 0 0/2mm 2mm,#fff', 'Rose : points')))
    doc.section(C, 'access', 'Accessibilité des graphiques', 'Chart accessibility',
        ['<p>Un graphique est un contenu : il doit être compris sans la vue et sans la couleur. Chaque graphique a un <b>titre</b>, une <b>alternative courte</b> qui donne la conclusion, et un <b>tableau de données</b> équivalent accessible. Les séries se distinguent aussi par une forme, un motif ou une étiquette. Les infobulles se déclenchent au clavier comme à la souris.</p>',
         '<p>Contrastes : 3:1 minimum pour les éléments graphiques, 4,5:1 pour tout texte. Pas d\'animation au chargement quand l\'utilisateur demande un mouvement réduit.</p>'],
        f'<div style="display:grid;grid-template-columns:1fr 1.2fr 1fr;gap:8mm;padding:7mm 9mm;height:100%;align-items:start"><div><div class="lbl" style="margin-bottom:2mm">Tableau équivalent</div>{tbl}</div><div><div class="lbl" style="margin-bottom:2mm">Structure HTML</div>{code}</div><div><div class="lbl" style="margin-bottom:2mm">Motifs en plus de la couleur</div>{pat}</div></div>')

    # ---- confidentialité
    pv = [('Petits effectifs', 'Moins de 5 personnes dans un groupe : « données masquées », jamais 0.', 'shield-lock'),
          ('Recoupements', 'Pas de croisement de trois critères ou plus (âge × quartier × type) qui isole une personne.', 'intersect'),
          ('Arrondis', 'Les totaux publics sont arrondis (à 5 près) quand les effectifs sont faibles.', 'calculator'),
          ('Exports', 'Les exports de données personnelles sont réservés aux rôles habilités et tracés.', 'download'),
          ('Cartes', 'Pas de point exact : le niveau le plus fin est la commune, le département en public.', 'geo-alt'),
          ('Durée', 'Les chiffres publics portent une période ; les données sources suivent la durée de conservation.', 'clock-history')]
    cells = ''.join(f'<div class="card" style="border:.2mm solid #e4dce6;padding:5mm;display:flex;gap:4mm"><div style="flex:none;width:12mm;height:12mm;border-radius:50%;background:#EBEFFA;display:flex;align-items:center;justify-content:center">{icon(i, 6, "#0433C3")}</div><div><div class="mt" style="font:800 9.4pt Montserrat">{t}</div><div style="font:400 7.8pt/1.5 Arial;color:#3b3650;margin-top:1mm">{d}</div></div></div>' for t, d, i in pv)
    doc.section(C, 'privacy', 'Confidentialité des données', 'Data privacy',
        ['<p>Dans un contexte de violences, une statistique peut identifier quelqu\'un. Les règles ci-contre s\'appliquent à tout graphique, tableau ou carte qui sort de l\'équipe habilitée. Les seuils sont des propositions à valider avec la personne chargée de la protection des données.</p>'],
        f'<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:5mm;padding:7mm 9mm;height:100%;align-content:center">{cells}</div>' + FAKE)

    # ---- états
    st_empty = shot(f'<div style="height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2mm">{icon("bar-chart", 11, "#8E88A0")}<div class="mt" style="font:800 8pt Montserrat">Pas encore de données</div><div style="font:400 5.6pt Arial;color:#5B5670;text-align:center;width:44mm">Les premiers signalements apparaîtront ici.</div></div>', 62, 46)
    st_load = shot('<div style="padding:5mm"><div style="height:3mm;width:30mm;background:#E4DCE6;border-radius:1mm"></div><div style="display:flex;gap:2mm;align-items:flex-end;height:26mm;margin-top:5mm">' + ''.join(f'<div style="flex:1;height:{h}%;background:#E4DCE6;border-radius:1mm 1mm 0 0"></div>' for h in (40, 65, 50, 80, 55, 90, 60)) + '</div><div class="note" style="margin-top:2mm">Chargement (squelette) : réserve la place</div></div>', 62, 46)
    st_err = shot(f'<div style="height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2mm">{icon("exclamation-circle", 11, "#9F0A0A")}<div class="mt" style="font:800 8pt Montserrat">Impossible d\'afficher le graphique</div><div style="font:400 5.6pt Arial;color:#5B5670">Réessaie dans un instant.</div><div style="background:#0433C3;color:#fff;font:700 5.6pt Montserrat;padding:1.6mm 4mm;border-radius:1mm">Réessayer</div></div>', 62, 46)
    st_part = shot(f'<div style="padding:4mm"><div style="background:#FFF4D6;border-radius:1.2mm;padding:1.6mm 2.4mm;font:700 5.4pt Arial;color:#5B3A00">Données incomplètes depuis le 28/09</div>{line(VALS[:8], 100, 40, "#0433C3")}</div>', 62, 46)
    doc.section(C, 'states', 'États : vide, chargement, erreur', 'States',
        ['<p>Quatre états à prévoir pour chaque graphique : <b>vide</b> (une phrase et ce qui va suivre), <b>chargement</b> (un squelette qui réserve la place, pas de décalage de mise en page), <b>erreur</b> (une action « Réessayer », jamais un code technique) et <b>données partielles</b> (un bandeau qui dit ce qui manque). Un message d\'état est annoncé aux lecteurs d\'écran (<code>aria-live</code>).</p>'],
        f'<div style="display:grid;grid-template-columns:1fr 1fr;gap:6mm;padding:6mm;height:100%;align-content:center;justify-items:center">{block(st_empty, "Vide")}{block(st_load, "Chargement")}{block(st_err, "Erreur")}{block(st_part, "Données partielles")}</div>')

    # ---- Chart.js
    cjs = ('<div style="font:400 7.4pt/1.65 ui-monospace,Consolas,monospace;background:#1B1630;color:#EDEAF7;border-radius:2mm;padding:6mm">'
           '<span style="color:#9DB1FF">const</span> css = getComputedStyle(document.documentElement);<br>'
           '<span style="color:#9DB1FF">const</span> token = n =&gt; css.getPropertyValue(n).trim();<br><br>'
           'Chart.defaults.font.family = token(<span style="color:#FFC94D">\'--xm-font-body\'</span>);<br>'
           'Chart.defaults.color = token(<span style="color:#FFC94D">\'--xm-text-muted\'</span>);<br>'
           'Chart.defaults.borderColor = token(<span style="color:#FFC94D">\'--xm-border\'</span>);<br>'
           'Chart.defaults.animation = matchMedia(<span style="color:#FFC94D">\'(prefers-reduced-motion: reduce)\'</span>).matches<br>'
           '&nbsp;&nbsp;? <span style="color:#9DB1FF">false</span> : { duration: 300 };<br><br>'
           '<span style="color:#9DB1FF">const</span> palette = [1,2,3,4,5,6].map(i =&gt; token(<span style="color:#FFC94D">`--xm-chart-${i}`</span>));</div>')
    notes = ['<b>Une seule source de couleur</b> : les jetons CSS, relus à chaque changement de thème (clair/sombre).', '<b>Texte</b> : police Arial, 12 px au moins, titres en Montserrat dans le HTML autour, pas dans le canvas.', '<b>Mouvement réduit</b> : animations coupées.', '<b>Redimensionnement</b> : <code>responsive: true</code>, hauteur réservée pour éviter le décalage.', '<b>Alternative</b> : chaque graphique est suivi de son tableau (voir « Accessibilité des graphiques »).', '<b>Hors Chart.js</b> : les graphiques d\'un PDF ou d\'un e-mail sont générés côté serveur en SVG avec les mêmes jetons.']
    doc.section(C, 'chartjs', 'Mise en œuvre avec Chart.js', 'Implementation with Chart.js',
        ['<p>Le tableau de bord utilise Chart.js, configuré une fois pour tout le site à partir des jetons. Le composant <code>x-chart</code> enveloppe le canvas, son titre, son alternative et son tableau.</p>'],
        f'<div style="display:grid;grid-template-columns:1.25fr 1fr;gap:8mm;padding:7mm 9mm;height:100%;align-items:center"><div>{cjs}</div><div style="font:400 8.4pt/1.65 Arial">{"<br>".join(notes)}</div></div>')

    # ---- recommandations
    dd = lambda inner, lab, ok: (good if ok else bad)(inner, lab, 66, 56, '#fff', 'width:100%;height:100%')
    doc.section(C, 'guidance', 'Recommandations', 'Guidance',
        ['<p>Les erreurs les plus fréquentes : trop de couleurs, titre qui décrit au lieu de conclure, graphique sans source, rouge utilisé pour une catégorie, petit effectif affiché. En cas de doute, simplifier : une série, une couleur d\'accent, un titre-message.</p>'],
        '<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:3mm;padding:4mm;height:100%">'
        + dd('<div style="width:80%"><div class="mt" style="font:800 7.4pt/1.2 Montserrat">Les signalements ont doublé</div><div style="margin-top:2mm">' + line(VALS, 100, 40, '#0433C3') + '</div></div>', 'Titre-message, une couleur d\'accent', True)
        + dd('<div style="width:80%"><div class="mt" style="font:800 7.4pt/1.2 Montserrat">Signalements</div><div style="margin-top:2mm">' + ''.join(line([42 + i * 4 + j * 3 for j in range(8)], 100, 20, c) for i, c in enumerate(['#E30E0E', '#F0A800', '#0B7A4B', '#1864AB', '#6F2C91', '#C2255C'])) + '</div></div>', 'Six séries, arc-en-ciel, titre vague', False)
        + dd('<div style="width:80%">' + hbars([('Couffo', 3), ('Mono', 29), ('Zou', 69)], 69, '#0433C3', 6.4) + '</div>', 'Valeur 3 affichée : effectif trop petit', False)
        + dd('<div style="width:80%">' + hbars([('Couffo', 0), ('Mono', 29), ('Zou', 69)], 69, '#0433C3', 6.4).replace('<b style="width:10mm;font-variant-numeric:tabular-nums">0</b>', '<b style="width:10mm;font-size:5.4pt">masqué</b>') + '<div class="note">Source : Xomiton, 30/09/2026</div></div>', 'Valeur masquée, source indiquée', True)
        + '</div>' + FAKE, visclass='tint')

    # =============================== CHAPITRE 14 : CONTACT ===============================
    doc.divider(ch_of(14))
    doc.section(14, 'contact', 'Informations de contact', 'Contact information',
        ['<p class="lead">Une question sur l\'usage de la charte ? Écrivez avant de produire.</p>',
         '<p>Tout cas non prévu par ce document ne s\'improvise pas : on décrit l\'usage (support, public, capture), la règle qui pose question et la solution envisagée. La réponse est consignée dans le dossier <code>docs/adr</code> et, si besoin, la charte est mise à jour.</p>'],
        f'<div style="display:grid;grid-template-columns:1.1fr 1fr;gap:10mm;padding:8mm 10mm;height:100%"><div><div class="lbl" style="margin-bottom:3mm">Contact</div><table class="t" style="font-size:9pt"><tr><td><b>Organisation</b></td><td>Xomiton</td></tr><tr><td><b>Site</b></td><td>xomiton.org</td></tr><tr><td><b>Application actuelle</b></td><td>app.xomiton.org</td></tr><tr><td><b>Responsable de la charte</b></td><td>[à désigner]</td></tr><tr><td><b>Adresse de contact</b></td><td>[à renseigner]</td></tr><tr><td><b>Relecture des écrans de crise et des supports sensibles</b></td><td>[personne de l\'équipe psychosociale, à désigner]</td></tr></table>'
        f'<div style="margin-top:8mm">{logo_w("couleur", 60)}</div></div>'
        '<div><div class="lbl" style="margin-bottom:3mm">Fichiers du système</div><table class="t" style="font-size:8.4pt"><tr><td><b>logo/</b></td><td>SVG et PNG haute définition (6000 px)</td></tr><tr><td><b>tokens/design-tokens.json</b></td><td>Jetons de design (trois couches)</td></tr><tr><td><b>tokens/tokens.css</b></td><td>Variables CSS, thèmes clair et sombre</td></tr><tr><td><b>tokens/social-xomiton.css</b></td><td>Surcharge du thème Social (Bootstrap 5)</td></tr><tr><td><b>tokens/components.css</b></td><td>Styles des composants x-*</td></tr><tr><td><b>blade/</b></td><td>Composants Blade et catalogue vivant</td></tr><tr><td><b>preview/index.html</b></td><td>Aperçu vivant des composants</td></tr><tr><td><b>fonts/</b></td><td>Montserrat (WOFF2, licence SIL OFL)</td></tr></table>'
        '<div class="lbl" style="margin:7mm 0 2mm">Historique</div><table class="t" style="font-size:8.4pt"><tr><th>Version</th><th>Date</th><th>Objet</th></tr><tr><td><b>1.0</b></td><td>8 octobre 2026</td><td>Première édition, complétée le jour même par les chapitres 12 (supports de communication) et 13 (data visualisation)</td></tr></table>'
        '<p class="note" style="margin-top:6mm">Colophon : titres en Montserrat (SIL OFL), texte en Arial, pictogrammes Bootstrap Icons (MIT). Le dessin vectoriel du logo est une reconstitution à partir de logo.png (240 × 66 px), à valider par la personne qui l\'a conçu. Les photographies et illustrations montrées sont des images de travail dont les droits sont à confirmer. Les chiffres, maquettes et coordonnées des chapitres 10, 12 et 13 sont fictifs ou à renseigner.</p></div></div>')
