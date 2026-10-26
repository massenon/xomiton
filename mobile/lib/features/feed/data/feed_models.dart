Map<String, dynamic> _map(Object? v) =>
    v is Map ? Map<String, dynamic>.from(v) : <String, dynamic>{};
List<Map<String, dynamic>> _list(Object? v) => v is List
    ? v.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList()
    : const [];
int _int(Object? v, [int d = 0]) =>
    v is num ? v.toInt() : int.tryParse('$v') ?? d;
DateTime? _date(Object? v) => v is String ? DateTime.tryParse(v) : null;

class Author {
  const Author({
    this.id,
    required this.pseudo,
    this.avatarUrl,
    this.anonymous = false,
  });
  final int? id;
  final String pseudo;
  final String? avatarUrl;
  final bool anonymous;
  factory Author.fromJson(Map<String, dynamic> j) => Author(
    id: j['id'] is num ? (j['id'] as num).toInt() : null,
    pseudo: '${j['pseudo'] ?? ''}',
    avatarUrl: j['avatar_url'] as String?,
    anonymous: j['anonymous'] == true,
  );
}

class PollOption {
  const PollOption({
    required this.id,
    required this.label,
    this.votes,
    this.percentage,
    this.leading,
  });
  final int id;
  final String label;
  final int? votes;
  final num? percentage;
  final bool? leading;

  factory PollOption.fromJson(Map<String, dynamic> j) => PollOption(
    id: _int(j['id']),
    label: '${j['label'] ?? ''}',
    votes: j['votes_count'] is num ? (j['votes_count'] as num).toInt() : null,
    percentage: j['percentage'] as num?,
    leading: j['leading'] as bool?,
  );
}

/// Sondage (`PollResource`, RG-09) : jamais de vote individuel autre que le sien.
class Poll {
  const Poll({
    required this.question,
    required this.multiple,
    required this.closed,
    required this.votersCount,
    required this.resultsVisible,
    required this.hasVoted,
    required this.myChoices,
    required this.canVote,
    required this.options,
    this.endsAt,
  });
  final String question;
  final bool multiple;
  final bool closed;
  final int votersCount;
  final bool resultsVisible;
  final bool hasVoted;
  final List<int> myChoices;
  final bool canVote;
  final List<PollOption> options;
  final DateTime? endsAt;

  factory Poll.fromJson(Map<String, dynamic> j) => Poll(
    question: '${j['question'] ?? ''}',
    multiple: j['multiple'] == true,
    closed: j['closed'] == true,
    votersCount: _int(j['voters_count']),
    resultsVisible: j['results_visible'] == true,
    hasVoted: j['has_voted'] == true,
    myChoices: ((j['my_choices'] as List?) ?? const [])
        .map((e) => _int(e))
        .toList(),
    canVote: j['can_vote'] == true,
    options: _list(j['options']).map(PollOption.fromJson).toList(),
    endsAt: _date(j['ends_at']),
  );

  /// Vote optimiste : les totaux affichés sont recomptés localement en
  /// attendant la réponse du serveur (qui fait foi).
  Poll withVote(List<int> choices) {
    // Résultats masqués avant le vote (RG-09) : on ne peut rien recompter, on
    // marque seulement le choix en attendant les totaux du serveur.
    if (options.any((o) => o.votes == null)) {
      return Poll(
        question: question,
        multiple: multiple,
        closed: closed,
        votersCount: hasVoted ? votersCount : votersCount + 1,
        resultsVisible: false,
        hasVoted: true,
        myChoices: choices,
        canVote: canVote,
        endsAt: endsAt,
        options: options,
      );
    }
    final voters = hasVoted ? votersCount : votersCount + 1;
    final counts = {
      for (final o in options)
        o.id:
            (o.votes ?? 0) -
            (myChoices.contains(o.id) ? 1 : 0) +
            (choices.contains(o.id) ? 1 : 0),
    };
    final total = counts.values.fold<int>(0, (a, b) => a + b);
    final top = counts.values.fold<int>(0, (a, b) => a > b ? a : b);
    return Poll(
      question: question,
      multiple: multiple,
      closed: closed,
      votersCount: voters,
      resultsVisible: true,
      hasVoted: true,
      myChoices: choices,
      canVote: canVote,
      endsAt: endsAt,
      options: [
        for (final o in options)
          PollOption(
            id: o.id,
            label: o.label,
            votes: counts[o.id],
            percentage: total == 0
                ? 0
                : ((counts[o.id]! * 100) / total).round(),
            leading: top > 0 && counts[o.id] == top,
          ),
      ],
    );
  }

  /// Retrait optimiste : on repart des totaux sans la voix de la personne.
  Poll withoutVote() {
    return Poll(
      question: question,
      multiple: multiple,
      closed: closed,
      votersCount: votersCount > 0 ? votersCount - 1 : 0,
      resultsVisible: false,
      hasVoted: false,
      myChoices: const [],
      canVote: canVote,
      endsAt: endsAt,
      options: [
        for (final o in options)
          PollOption(id: o.id, label: o.label, votes: null, percentage: null),
      ],
    );
  }
}

class Post {
  const Post({
    required this.id,
    required this.author,
    required this.isMine,
    required this.body,
    required this.tag,
    required this.tagLabel,
    required this.likes,
    required this.comments,
    required this.liked,
    required this.canComment,
    required this.canDelete,
    required this.canReport,
    this.poll,
    this.publishedAt,
    this.imageUrl,
    this.moderated = false,
  });
  final String id;
  final Author author;
  final bool isMine;
  final String body;
  final String tag;
  final String tagLabel;
  final int likes;
  final int comments;
  final bool liked;
  final bool canComment;
  final bool canDelete;
  final bool canReport;
  final Poll? poll;
  final DateTime? publishedAt;
  final String? imageUrl;
  final bool moderated;

  factory Post.fromJson(Map<String, dynamic> j) {
    final can = _map(j['can']);
    final media = _list(j['media']);
    return Post(
      id: '${j['id']}',
      author: Author.fromJson(_map(j['author'])),
      isMine: j['is_mine'] == true,
      body: '${j['body'] ?? ''}',
      tag: '${j['tag'] ?? ''}',
      tagLabel: '${j['tag_label'] ?? ''}',
      likes: _int(j['likes_count']),
      comments: _int(j['comments_count']),
      liked: j['liked'] == true,
      canComment: can['comment'] != false,
      canDelete: can['delete'] == true,
      canReport: can['report'] == true,
      poll: j['poll'] is Map ? Poll.fromJson(_map(j['poll'])) : null,
      publishedAt: _date(j['published_at']),
      imageUrl: media.isEmpty ? null : media.first['url'] as String?,
      moderated: j['moderated'] == true,
    );
  }

  Post copyWith({bool? liked, int? likes, int? comments, Poll? poll}) => Post(
    id: id,
    author: author,
    isMine: isMine,
    body: body,
    tag: tag,
    tagLabel: tagLabel,
    likes: likes ?? this.likes,
    comments: comments ?? this.comments,
    liked: liked ?? this.liked,
    canComment: canComment,
    canDelete: canDelete,
    canReport: canReport,
    poll: poll ?? this.poll,
    publishedAt: publishedAt,
    imageUrl: imageUrl,
    moderated: moderated,
  );
}

class Comment {
  const Comment({
    required this.id,
    this.parentId,
    required this.body,
    required this.author,
    required this.isMine,
    this.createdAt,
    this.canDelete = false,
    this.canReport = false,
  });
  final int id;
  final int? parentId;
  final String body;
  final Author author;
  final bool isMine;
  final DateTime? createdAt;
  final bool canDelete;
  final bool canReport;

  factory Comment.fromJson(Map<String, dynamic> j) {
    final can = _map(j['can']);
    return Comment(
      id: _int(j['id']),
      parentId: j['parent_id'] is num ? (j['parent_id'] as num).toInt() : null,
      body: '${j['body'] ?? ''}',
      author: Author.fromJson(_map(j['author'])),
      isMine: j['is_mine'] == true,
      createdAt: _date(j['created_at']),
      canDelete: can['delete'] == true,
      canReport: can['report'] == true,
    );
  }
}

/// Page de liste par curseur (`meta.next_cursor`).
class FeedPage {
  const FeedPage({required this.items, this.nextCursor, this.stale = false});
  final List<Post> items;
  final String? nextCursor;
  final bool stale;
  bool get hasMore => nextCursor != null && nextCursor!.isNotEmpty;
}
