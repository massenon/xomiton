import 'package:flutter_test/flutter_test.dart';
import 'package:xomiton/features/ccc/application/ccc_progress.dart';
import 'package:xomiton/features/ccc/data/ccc_models.dart';

import 'ccc_support.dart';

void main() {
  test('tous les textes CCC utilisés (y compris ceux construits : onglets, questions) existent', () {
    final keys = [
      for (final k in ['manuel', 'boites_images', 'bd', 'lois']) ...['ccc_tabs_$k'],
      for (var i = 0; i < 5; i++) 'ccc_images_tips_$i',
      for (var i = 0; i < 3; i++) 'ccc_comic_discuss_items_$i',
      'ccc_title', 'ccc_presentation_badge', 'ccc_presentation_notice', 'ccc_tabs_label', 'ccc_empty_hint',
      'ccc_manual_chapter_seen', 'ccc_manual_global_announce', 'ccc_show_announce', 'ccc_comic_page_announce',
      'menus_member_ccc', 'peer_home_shortcuts_ccc', 'mobile_ccc_forbidden', 'mobile_ccc_storage_unavailable',
    ];
    for (final k in keys) {
      expect(S.has(k), isTrue, reason: k);
    }
    expect(S.t('mobile_ccc_manual_progress_summary', {'done': 2, 'total': 5, 'percent': 40}), '2 sur 5 points vus · 40 %');
  });

  group('CccCatalog.fromJson', () {
    test('lit une réponse réaliste de GET ccc', () {
      final c = CccCatalog.fromJson(cccBody());
      expect(c.manual, hasLength(2));
      expect(c.manual.first.title, 'Module 1 : Poser un cadre sûr');
      expect(c.manual.first.durationMinutes, 45);
      expect(c.manual.first.chapters, ['Confidentialité', 'Écoute active', 'Limites du rôle']);
      expect(c.manual.last.durationMinutes, isNull);
      expect(c.manual.last.description, isNull);
      expect(c.imageBoxes, hasLength(2));
      expect(c.imageBoxes.first.description, 'Laisse chaque personne parler avant de réagir.');
      expect(c.imageBoxes.first.pages.first.alt, 'Deux amies assises sur un banc');
      expect(c.imageBoxes.first.pages.last.caption, isNull);
      expect(c.comics.single.pages, hasLength(3));
      expect(c.lawsCount, 12);
      expect(c.lawsUrl, Uri.parse('https://xomiton.test/lois'));
      expect(c.module('m2')?.title, 'Module 2 : Orienter');
      expect(c.imageBox('bi2')?.title, 'La pression des pairs');
      expect(c.comic('bd1')?.title, 'Awa et le centre de santé');
      expect(c.module('inconnu'), isNull);
    });

    test('lecture défensive : corps vide, types faux, éléments sans clé ni titre', () {
      expect(CccCatalog.fromJson(null).manual, isEmpty);
      expect(CccCatalog.fromJson('x').lawsCount, 0);
      expect(CccCatalog.fromJson({'data': []}).comics, isEmpty);
      final c = CccCatalog.fromJson({
        'data': {
          'manuel': [
            {'key': 'm1', 'title': 'Ok', 'chapters': ['A', '', 3, '  B  '], 'duration_minutes': 'abc'},
            {'title': 'Sans clé'},
            {'key': 'm3'},
            'pas un objet',
          ],
          'boites_images': [
            {
              'key': 'bi1',
              'title': 'Boîte',
              'cover': 'javascript:alert(1)',
              'pages': [
                {'image': 'file:///etc/passwd', 'alt': 'x'},
                {'image': 'https://x.test/a.jpg'},
                {'caption': 'sans image'},
                7,
              ],
            },
          ],
          'bd': 'oops',
        },
        'meta': {'laws': {'count': -3, 'url': 'ftp://x'}},
      });
      expect(c.manual.single.chapters, ['A', 'B']);
      expect(c.manual.single.durationMinutes, isNull);
      expect(c.imageBoxes.single.cover, isNull, reason: 'adresse non web ignorée');
      expect(c.imageBoxes.single.pages, hasLength(1));
      expect(c.imageBoxes.single.pages.single.alt, 'Boîte – 1', reason: 'alt absent : titre et rang, comme le serveur');
      expect(c.comics, isEmpty);
      expect(c.lawsCount, 0);
      expect(c.lawsUrl, isNull);
    });
  });

  group('progression du manuel', () {
    final modules = CccCatalog.fromJson(cccBody()).manual;

    test('clé par compte, comme le web', () {
      expect(cccProgressStorageKey(12), 'xm_ccc_manuel_v1:12');
      expect(cccProgressStorageKey(null), 'xm_ccc_manuel_v1');
    });

    test('cocher, décocher, trier, sans doublon ; l’ancien état n’est pas modifié', () {
      final a = toggleSeen(const {}, 'm1', 2, on: true);
      final b = toggleSeen(a, 'm1', 0, on: true);
      final c = toggleSeen(b, 'm1', 0, on: true);
      expect(c['m1'], [0, 2]);
      expect(a['m1'], [2]);
      expect(toggleSeen(c, 'm1', 0, on: false)['m1'], [2]);
      expect(toggleSeen(a, 'm1', 2, on: false).containsKey('m1'), isFalse);
    });

    test('totaux et pourcentage ; un rang qui n’existe plus ne compte pas', () {
      final seen = {'m1': [0, 1, 9], 'm2': [1], 'ancien': [0]};
      expect(seenCount(seen, modules.first), 2);
      final t = totals(seen, modules);
      expect(t.done, 3);
      expect(t.total, 5);
      expect(t.percent, 60);
      expect(percentOf(0, 0), 0);
    });

    test('décodage tolérant : corrompu, mauvais types, rangs négatifs', () {
      expect(decodeSeen(null), isEmpty);
      expect(decodeSeen('{pas du json'), isEmpty);
      expect(decodeSeen('[1,2]'), isEmpty);
      expect(decodeSeen('{"m1":[2,0,2,-1,"a",1.5],"m2":"x","m3":[]}'), {'m1': [0, 2]});
      expect(decodeSeen(encodeSeen({'m1': [1, 2]})), {'m1': [1, 2]});
    });
  });
}
