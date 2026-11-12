import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../app/theme/xm_tokens.dart';
import '../../../core/l10n/app_strings.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/ui/xm_widgets.dart';
import '../data/engagement_api.dart';

/// PDF choisi sur l'appareil (remplaçable dans les tests).
typedef PickedPdf = ({String path, String name});

final pdfPickerProvider = Provider<Future<PickedPdf?> Function()>((ref) => () async {
  final r = await FilePicker.platform.pickFiles(type: FileType.custom, allowedExtensions: const ['pdf']);
  final f = r?.files.firstOrNull;
  if (f == null || f.path == null) return null;
  return (path: f.path!, name: f.name);
});

final proposalOptionsProvider = FutureProvider.autoDispose<ContributeOptions>(
  (ref) => ref.watch(engagementApiProvider).proposalOptions(),
);

final suggestionOptionsProvider = FutureProvider.autoDispose<ContributeOptions>(
  (ref) => ref.watch(engagementApiProvider).suggestionOptions(),
);

/// Confirmation d'envoi commune aux deux formulaires.
class _Sent extends StatelessWidget {
  const _Sent({required this.text, required this.again, required this.onAgain});
  final String text;
  final String again;
  final VoidCallback onAgain;

  @override
  Widget build(BuildContext context) => Semantics(
    liveRegion: true,
    child: Column(
      key: const Key('contribute-sent'),
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Icon(Icons.check_circle_outline),
            const SizedBox(width: XmSpace.s2),
            Expanded(child: Text(text)),
          ],
        ),
        const SizedBox(height: XmSpace.s3),
        OutlinedButton(onPressed: onAgain, child: Text(again)),
      ],
    ),
  );
}

/// « Proposer une ressource » (P18-12) : titre, type, résumé, catégorie, lien
/// ou PDF, crédit. La proposition n'est jamais publique : l'équipe la relit.
class ResourceProposalPage extends ConsumerStatefulWidget {
  const ResourceProposalPage({super.key});

  @override
  ConsumerState<ResourceProposalPage> createState() => _ResourceProposalPageState();
}

class _ResourceProposalPageState extends ConsumerState<ResourceProposalPage> {
  final _title = TextEditingController();
  final _summary = TextEditingController();
  final _link = TextEditingController();
  String? _kind;
  String _category = '';
  bool _credit = false;
  PickedPdf? _file;
  bool _busy = false;
  bool _sent = false;
  Map<String, String> _errors = const {};

  @override
  void dispose() {
    _title.dispose();
    _summary.dispose();
    _link.dispose();
    super.dispose();
  }

  Future<void> _submit(ContributeOptions o) async {
    final kind = _kind ?? (o.kinds.isNotEmpty ? o.kinds.first.value : 'lien');
    setState(() {
      _busy = true;
      _errors = const {};
    });
    try {
      await ref.read(engagementApiProvider).proposeResource(
        title: _title.text.trim(),
        kind: kind,
        summary: _summary.text.trim(),
        categoryId: _category,
        link: kind == 'pdf' ? null : _link.text.trim(),
        filePath: kind == 'pdf' ? _file?.path : null,
        fileName: _file?.name,
        credit: _credit,
      );
      if (mounted) setState(() => _sent = true);
    } catch (e) {
      final api = ApiException.from(e);
      if (!mounted) return;
      if (api.isValidation && api.errors.isNotEmpty) {
        setState(() => _errors = {for (final k in api.errors.keys) k: api.firstError(k) ?? ''});
      } else {
        ScaffoldMessenger.of(context)
          ..hideCurrentSnackBar()
          ..showSnackBar(SnackBar(content: Text(errorMessage(context, e))));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(proposalOptionsProvider);
    return Scaffold(
      appBar: AppBar(title: Text(context.t('community_proposals_title'))),
      body: SafeArea(
        child: state.when(
          loading: () => const LoadingView(),
          error: (e, _) => ErrorView(error: e, onRetry: () => ref.invalidate(proposalOptionsProvider)),
          data: (o) {
            final kind = _kind ?? (o.kinds.isNotEmpty ? o.kinds.first.value : 'lien');
            return ListView(
              padding: const EdgeInsets.all(XmSpace.gutter),
              children: [
                Text(context.t('community_proposals_intro')),
                const SizedBox(height: XmSpace.s4),
                if (_sent)
                  _Sent(
                    text: context.t('community_proposals_sent'),
                    again: context.t('community_proposals_again'),
                    onAgain: () => setState(() {
                      _sent = false;
                      _title.clear();
                      _summary.clear();
                      _link.clear();
                      _file = null;
                    }),
                  )
                else ...[
                  TextField(
                    key: const Key('proposal-title'),
                    controller: _title,
                    maxLength: o.limit('title_max', 150),
                    decoration: InputDecoration(
                      labelText: context.t('community_proposals_fields_title'),
                      errorText: _errors['title'],
                    ),
                  ),
                  const SizedBox(height: XmSpace.s2),
                  DropdownButtonFormField<String>(
                    key: const Key('proposal-kind'),
                    initialValue: kind,
                    decoration: InputDecoration(
                      labelText: context.t('community_proposals_fields_kind'),
                      errorText: _errors['kind'],
                    ),
                    items: [for (final c in o.kinds) DropdownMenuItem(value: c.value, child: Text(c.label))],
                    onChanged: (v) => setState(() => _kind = v),
                  ),
                  const SizedBox(height: XmSpace.s3),
                  TextField(
                    key: const Key('proposal-summary'),
                    controller: _summary,
                    minLines: 3,
                    maxLines: 6,
                    maxLength: o.limit('summary_max', 500),
                    decoration: InputDecoration(
                      labelText: context.t('community_proposals_fields_summary'),
                      helperText: context.t('community_proposals_hints_summary', {
                        'min': o.limit('summary_min', 20),
                        'max': o.limit('summary_max', 500),
                      }),
                      errorText: _errors['summary'],
                    ),
                  ),
                  const SizedBox(height: XmSpace.s2),
                  DropdownButtonFormField<String>(
                    initialValue: _category,
                    decoration: InputDecoration(
                      labelText: context.t('community_proposals_fields_category'),
                      errorText: _errors['category_id'],
                    ),
                    items: [
                      DropdownMenuItem(value: '', child: Text(context.t('community_proposals_no_category'))),
                      for (final c in o.categories) DropdownMenuItem(value: c.value, child: Text(c.label)),
                    ],
                    onChanged: (v) => setState(() => _category = v ?? ''),
                  ),
                  const SizedBox(height: XmSpace.s3),
                  if (kind == 'pdf') ...[
                    OutlinedButton.icon(
                      key: const Key('proposal-file'),
                      icon: const Icon(Icons.picture_as_pdf_outlined),
                      label: Text(_file?.name ?? context.t('community_proposals_fields_file')),
                      onPressed: () async {
                        final picked = await ref.read(pdfPickerProvider)();
                        if (picked != null && mounted) setState(() => _file = picked);
                      },
                    ),
                    Padding(
                      padding: const EdgeInsets.only(top: XmSpace.s1),
                      child: Text(
                        _errors['file'] ??
                            context.t('community_proposals_hints_file', {
                              'max': (o.limit('file_max_kb', 10240) / 1024).round(),
                            }),
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          color: _errors['file'] != null ? Theme.of(context).colorScheme.error : null,
                        ),
                      ),
                    ),
                  ] else
                    TextField(
                      key: const Key('proposal-link'),
                      controller: _link,
                      keyboardType: TextInputType.url,
                      decoration: InputDecoration(
                        labelText: context.t('community_proposals_fields_link'),
                        helperText: context.t('community_proposals_hints_link'),
                        errorText: _errors['external_url'],
                      ),
                    ),
                  const SizedBox(height: XmSpace.s2),
                  SwitchListTile(
                    contentPadding: EdgeInsets.zero,
                    value: _credit,
                    title: Text(context.t('community_proposals_fields_credit')),
                    subtitle: Text(context.t('community_proposals_hints_credit')),
                    onChanged: (v) => setState(() => _credit = v),
                  ),
                  const SizedBox(height: XmSpace.s3),
                  FilledButton.icon(
                    key: const Key('proposal-submit'),
                    icon: const Icon(Icons.send_outlined),
                    label: Text(context.t('community_proposals_submit')),
                    onPressed: _busy ? null : () => _submit(o),
                  ),
                ],
              ],
            );
          },
        ),
      ),
    );
  }
}

/// « Boîte à idées » (P18-14) : sujet, message ≤ 1000, envoi anonyme possible.
class SuggestionPage extends ConsumerStatefulWidget {
  const SuggestionPage({super.key});

  @override
  ConsumerState<SuggestionPage> createState() => _SuggestionPageState();
}

class _SuggestionPageState extends ConsumerState<SuggestionPage> {
  final _message = TextEditingController();
  String? _category;
  bool _anonymous = false;
  bool _busy = false;
  bool _sent = false;
  Map<String, String> _errors = const {};

  @override
  void dispose() {
    _message.dispose();
    super.dispose();
  }

  Future<void> _submit(ContributeOptions o) async {
    setState(() {
      _busy = true;
      _errors = const {};
    });
    try {
      await ref.read(engagementApiProvider).suggest(
        category: _category ?? (o.categories.isNotEmpty ? o.categories.first.value : 'autre'),
        message: _message.text.trim(),
        anonymous: _anonymous,
      );
      if (mounted) setState(() => _sent = true);
    } catch (e) {
      final api = ApiException.from(e);
      if (!mounted) return;
      if (api.isValidation && api.errors.isNotEmpty) {
        setState(() => _errors = {for (final k in api.errors.keys) k: api.firstError(k) ?? ''});
      } else {
        ScaffoldMessenger.of(context)
          ..hideCurrentSnackBar()
          ..showSnackBar(SnackBar(content: Text(errorMessage(context, e))));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(suggestionOptionsProvider);
    return Scaffold(
      appBar: AppBar(title: Text(context.t('community_suggestions_title'))),
      body: SafeArea(
        child: state.when(
          loading: () => const LoadingView(),
          error: (e, _) => ErrorView(error: e, onRetry: () => ref.invalidate(suggestionOptionsProvider)),
          data: (o) => ListView(
            padding: const EdgeInsets.all(XmSpace.gutter),
            children: [
              Text(context.t('community_suggestions_intro')),
              const SizedBox(height: XmSpace.s4),
              if (_sent)
                _Sent(
                  text: context.t('community_suggestions_sent'),
                  again: context.t('community_suggestions_again'),
                  onAgain: () => setState(() {
                    _sent = false;
                    _message.clear();
                    _anonymous = false;
                  }),
                )
              else ...[
                DropdownButtonFormField<String>(
                  key: const Key('suggestion-category'),
                  initialValue: _category ?? (o.categories.isNotEmpty ? o.categories.first.value : null),
                  decoration: InputDecoration(
                    labelText: context.t('community_suggestions_fields_category'),
                    errorText: _errors['category'],
                  ),
                  items: [for (final c in o.categories) DropdownMenuItem(value: c.value, child: Text(c.label))],
                  onChanged: (v) => setState(() => _category = v),
                ),
                const SizedBox(height: XmSpace.s3),
                TextField(
                  key: const Key('suggestion-message'),
                  controller: _message,
                  minLines: 4,
                  maxLines: 8,
                  maxLength: o.limit('message_max', 1000),
                  decoration: InputDecoration(
                    labelText: context.t('community_suggestions_fields_message'),
                    helperText: context.t('community_suggestions_hints_message', {
                      'min': o.limit('message_min', 10),
                      'max': o.limit('message_max', 1000),
                    }),
                    helperMaxLines: 3,
                    errorText: _errors['message'],
                  ),
                ),
                SwitchListTile(
                  key: const Key('suggestion-anonymous'),
                  contentPadding: EdgeInsets.zero,
                  value: _anonymous,
                  title: Text(context.t('community_suggestions_fields_anonymous')),
                  subtitle: Text(context.t('community_suggestions_hints_anonymous')),
                  onChanged: (v) => setState(() => _anonymous = v),
                ),
                const SizedBox(height: XmSpace.s3),
                FilledButton.icon(
                  key: const Key('suggestion-submit'),
                  icon: const Icon(Icons.send_outlined),
                  label: Text(context.t('community_suggestions_submit')),
                  onPressed: _busy ? null : () => _submit(o),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}
