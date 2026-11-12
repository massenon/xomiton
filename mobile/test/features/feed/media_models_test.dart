import 'package:flutter_test/flutter_test.dart';
import 'package:xomiton/features/feed/data/feed_models.dart';

/// JSON d'exemple de docs/API.md §8.4 et §8.7.
Map<String, dynamic> _base() => {
  'id': '01JC',
  'author': {'id': 12, 'pseudo': 'Awa_S', 'anonymous': false},
  'is_mine': true,
  'body': 'Nos photos de samedi.',
  'tag': 'temoignage',
  'tag_label': 'Témoignage',
  'visibility': 'public',
  'moderated': false,
  'likes_count': 0,
  'comments_count': 0,
  'liked': false,
  'saved': false,
  'review_state': null,
  'review_label': null,
  'review_reason': null,
  'poll': null,
  'published_at': '2026-11-12T06:12:17+00:00',
  'can': {'update': true, 'delete': true, 'report': false, 'comment': true},
};

Map<String, dynamic> _image(int pos, String? alt) => {
  'kind': 'image',
  'url': 'https://x.test/media/local/images/2026/11/img$pos.webp?expires=1&signature=s',
  'alt': alt,
  'width': 1600,
  'height': 1200,
  'position': pos,
  'mime': 'image/webp',
  'size': 184233,
};

void main() {
  test('publication ordinaire : aucun champ de relecture, aucun média', () {
    final p = Post.fromJson({..._base(), 'media': <Object>[]});
    expect(p.media, isEmpty);
    expect(p.reviewState, isNull);
    expect(p.inReview, isFalse);
    expect(p.imageUrl, isNull);
    expect(p.video, isNull);
  });

  test('plusieurs images : ordre par position, alt nul gardé nul', () {
    final p = Post.fromJson({
      ..._base(),
      'media': [_image(1, 'Le groupe rassemblé'), _image(0, 'Atelier en plein air'), _image(2, null)],
    });
    expect(p.media.map((m) => m.position), [0, 1, 2]);
    expect(p.images, hasLength(3));
    expect(p.images.first.alt, 'Atelier en plein air');
    expect(p.images.last.alt, isNull);
    expect(p.images.first.width, 1600);
    expect(p.images.first.mime, 'image/webp');
    expect(p.images.first.size, 184233);
    expect(p.imageUrl, contains('img0.webp'));
    expect(p.video, isNull);
  });

  test('vidéo en relecture (202) : état, libellé, description, dimensions nulles', () {
    final p = Post.fromJson({
      ..._base(),
      'body': 'Notre atelier en vidéo.',
      'review_state': 'pending',
      'review_label': 'En attente de relecture',
      'published_at': null,
      'can': {'update': false, 'delete': true, 'report': false, 'comment': false},
      'media': [
        {
          'kind': 'video',
          'url': 'https://x.test/media/fil/01M7/video?expires=1&viewer=1&signature=s',
          'alt': 'Des femmes assises en cercle qui discutent d’un projet de coopérative.',
          'width': null,
          'height': null,
          'position': 0,
          'mime': 'video/mp4',
          'size': 8192,
        },
      ],
    });
    expect(p.reviewState, kReviewPending);
    expect(p.reviewLabel, 'En attente de relecture');
    expect(p.inReview, isTrue);
    expect(p.publishedAt, isNull);
    expect(p.canComment, isFalse);
    expect(p.canDelete, isTrue);
    expect(p.video, isNotNull);
    expect(p.video!.isVideo, isTrue);
    expect(p.video!.width, isNull);
    expect(p.video!.alt, startsWith('Des femmes'));
    expect(p.images, isEmpty);
    expect(p.imageUrl, isNull);
  });

  test('vidéo approuvée : plus en relecture', () {
    final p = Post.fromJson({
      ..._base(),
      'review_state': 'approved',
      'review_label': 'Vidéo publiée',
      'media': [
        {'kind': 'video', 'url': 'https://x.test/v', 'alt': 'Une description', 'position': 0, 'mime': 'video/mp4', 'size': 1},
      ],
    });
    expect(p.reviewState, kReviewApproved);
    expect(p.inReview, isFalse);
    expect(p.video, isNotNull);
  });

  test('vidéo refusée : motif lisible, media vide une fois le fichier purgé', () {
    final p = Post.fromJson({
      ..._base(),
      'review_state': 'rejected',
      'review_label': 'Vidéo refusée',
      'review_reason': 'Vie privée (lieu, visage ou information personnelle visible) — On voit le portail de ta maison.',
      'media': <Object>[],
      'published_at': null,
      'can': {'update': false, 'delete': true, 'report': false, 'comment': false},
    });
    expect(p.reviewState, kReviewRejected);
    expect(p.reviewReason, contains('portail de ta maison'));
    expect(p.inReview, isTrue);
    expect(p.media, isEmpty);
    expect(p.video, isNull);
  });

  test('copyWith garde les médias et la relecture', () {
    final p = Post.fromJson({
      ..._base(),
      'review_state': 'pending',
      'review_label': 'En attente de relecture',
      'media': [_image(0, 'Une image')],
    }).copyWith(liked: true, likes: 1);
    expect(p.liked, isTrue);
    expect(p.media, hasLength(1));
    expect(p.reviewState, 'pending');
    expect(p.reviewLabel, 'En attente de relecture');
  });
}
