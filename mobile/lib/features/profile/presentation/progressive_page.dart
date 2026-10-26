import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../app/theme/xm_tokens.dart';
import '../../../core/l10n/app_strings.dart';
import '../../../core/ui/xm_widgets.dart';
import '../../auth/application/session_controller.dart';
import '../../auth/data/auth_api.dart';
import '../data/account_api.dart';

final progressiveProvider = FutureProvider.autoDispose<Progressive>(
  (ref) => ref.watch(accountApiProvider).progressive(),
);

final _interestOptionsProvider = FutureProvider.autoDispose(
  (ref) => ref.watch(accountApiProvider).interestOptions(),
);

const _sensitiveGroups = {'gender_identity', 'orientation'};

/// Options à choix unique : (valeur API, clé de texte).
const Map<String, List<(String, String)>> _singleChoice = {
  'pronouns': [
    ('il', 'profile_progressive_groups_pronouns_options_il'),
    ('elle', 'profile_progressive_groups_pronouns_options_elle'),
    ('iel', 'profile_progressive_groups_pronouns_options_iel'),
    ('other', 'profile_progressive_groups_pronouns_options_other'),
  ],
  'member_type': [
    ('membre', 'auth_register_profile_member_types_membre'),
    ('pair', 'auth_register_profile_member_types_pair'),
  ],
  'ally_status': [
    ('allie', 'profile_progressive_groups_ally_status_options_ally'),
    ('ressource', 'profile_progressive_groups_ally_status_options_resource'),
    ('aucun', 'profile_progressive_groups_ally_status_options_none'),
  ],
  'gender_identity': [
    ('femme_cis', 'profile_progressive_groups_gender_identity_options_femme_cis'),
    ('femme_trans', 'profile_progressive_groups_gender_identity_options_femme_trans'),
    ('homme_cis', 'profile_progressive_groups_gender_identity_options_homme_cis'),
    ('homme_trans', 'profile_progressive_groups_gender_identity_options_homme_trans'),
    ('non_binaire', 'profile_progressive_groups_gender_identity_options_non_binaire'),
    ('intersexe', 'profile_progressive_groups_gender_identity_options_intersexe'),
    ('questionnement', 'profile_progressive_groups_gender_identity_options_questionnement'),
    ('autre', 'profile_progressive_groups_gender_identity_options_autre'),
  ],
  'orientation': [
    ('lesbienne', 'profile_progressive_groups_orientation_options_lesbienne'),
    ('bisexuelle', 'profile_progressive_groups_orientation_options_bisexuelle'),
    ('pansexuelle', 'profile_progressive_groups_orientation_options_pansexuelle'),
    ('queer', 'profile_progressive_groups_orientation_options_queer'),
    ('asexuelle', 'profile_progressive_groups_orientation_options_asexuelle'),
    ('heterosexuelle', 'profile_progressive_groups_orientation_options_heterosexuelle'),
    ('questionnement', 'profile_progressive_groups_orientation_options_questionnement'),
    ('autre', 'profile_progressive_groups_orientation_options_autre'),
  ],
  'age': [
    ('10-14', 'enums_reference_age_range_10_14'),
    ('15-17', 'enums_reference_age_range_15_17'),
    ('18-24', 'enums_reference_age_range_18_24'),
    ('25-34', 'enums_reference_age_range_25_34'),
    ('35-49', 'enums_reference_age_range_35_49'),
    ('50+', 'enums_reference_age_range_50plus'),
  ],
};

/// Champ de l'API écrit par chaque groupe.
const _apiField = {'age': 'age_range'};

/// Profil progressif : UN groupe à la fois, facultatif, « Je préfère ne pas le
/// dire » toujours possible. Les champs sensibles (identité de genre,
/// orientation) ne sont JAMAIS préremplis et exigent un consentement explicite.
class ProgressiveProfilePage extends ConsumerWidget {
  const ProgressiveProfilePage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(progressiveProvider);
    return Scaffold(
      appBar: AppBar(title: Text(context.t('profile_progressive_title'))),
      body: state.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(error: e, onRetry: () => ref.invalidate(progressiveProvider)),
        data: (p) => p.done || p.next == null
            ? Padding(
                padding: const EdgeInsets.all(XmSpace.gutter),
                child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                  Text(context.t('profile_progressive_done_title'), style: Theme.of(context).textTheme.titleLarge, textAlign: TextAlign.center),
                  const SizedBox(height: XmSpace.s3),
                  Text(context.t('profile_progressive_done_text'), textAlign: TextAlign.center),
                ]),
              )
            : _GroupForm(key: ValueKey(p.next), progressive: p, group: p.next!),
      ),
    );
  }
}

class _GroupForm extends ConsumerStatefulWidget {
  const _GroupForm({super.key, required this.progressive, required this.group});
  final Progressive progressive;
  final String group;

  @override
  ConsumerState<_GroupForm> createState() => _GroupFormState();
}

class _GroupFormState extends ConsumerState<_GroupForm> {
  String? _choice; // jamais prérempli
  final _other = TextEditingController();
  bool _consent = false; // explicite : décoché par défaut
  final Set<String> _interests = {};
  int? _zoneId;
  int? _communeId;
  bool _busy = false;

  bool get _sensitive => _sensitiveGroups.contains(widget.group);
  bool get _needsConsent => _sensitive && !widget.progressive.sensitiveConsent;

  bool get _hasValue => switch (widget.group) {
    'interests' => _interests.isNotEmpty,
    'location' => _zoneId != null,
    'pronouns' => _choice != null && (_choice != 'other' || _other.text.trim().isNotEmpty),
    _ => _choice != null,
  };

  Future<void> _send(Map<String, Object?> body, String savedKey) async {
    setState(() => _busy = true);
    try {
      await ref.read(accountApiProvider).patchProfile(body);
      ref.invalidate(progressiveProvider);
      await ref.read(sessionProvider.notifier).refresh();
      if (mounted) {
        setState(() => _busy = false);
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(context.t(savedKey))));
      }
    } catch (e) {
      if (mounted) {
        setState(() => _busy = false);
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(errorMessage(context, e))));
      }
    }
  }

  Future<void> _save() {
    final g = widget.group;
    final field = _apiField[g] ?? g;
    final body = <String, Object?>{
      if (g == 'interests') 'interests': _interests.toList()
      else if (g == 'location') ...{'zone_id': _zoneId, 'commune_id': _communeId}
      else if (g == 'pronouns') 'pronouns': _choice == 'other' ? _other.text.trim() : _choice
      else field: _choice,
      if (_needsConsent) 'sensitive_consent': true,
    };
    return _send(body, 'profile_progressive_saved');
  }

  /// « Je préfère ne pas le dire » : le groupe est marqué traité, sans valeur.
  Future<void> _skip() => _send({'answered_groups': [widget.group]}, 'profile_progressive_skipped');

  @override
  void dispose() {
    _other.dispose();
    super.dispose();
  }

  Widget _input(BuildContext context) {
    final g = widget.group;
    final options = _singleChoice[g];
    if (options != null) {
      return Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          RadioGroup<String>(
            groupValue: _choice,
            onChanged: (v) => setState(() => _choice = v),
            child: Column(children: [
              for (final o in options)
                RadioListTile<String>(
                  key: Key('progressive-option-${o.$1}'),
                  contentPadding: EdgeInsets.zero,
                  value: o.$1,
                  title: Text(context.t(o.$2)),
                ),
            ]),
          ),
          if (g == 'pronouns' && _choice == 'other')
            TextField(
              controller: _other,
              maxLength: 30,
              onChanged: (_) => setState(() {}),
              decoration: InputDecoration(labelText: context.t('profile_progressive_groups_pronouns_other_placeholder')),
            ),
        ],
      );
    }
    if (g == 'interests') {
      final opts = ref.watch(_interestOptionsProvider);
      return opts.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(error: e, onRetry: () => ref.invalidate(_interestOptionsProvider)),
        data: (list) => Wrap(spacing: XmSpace.s2, runSpacing: XmSpace.s2, children: [
          for (final o in list)
            FilterChip(
              label: Text(o.label),
              selected: _interests.contains(o.slug),
              onSelected: (v) => setState(() => v ? _interests.add(o.slug) : _interests.remove(o.slug)),
            ),
        ]),
      );
    }
    // location
    final zones = ref.watch(zonesProvider);
    return zones.when(
      loading: () => const LoadingView(),
      error: (e, _) => ErrorView(error: e, onRetry: () => ref.invalidate(zonesProvider)),
      data: (list) {
        final zone = list.where((z) => z.id == _zoneId).firstOrNull;
        return Column(children: [
          DropdownButtonFormField<int>(
            initialValue: _zoneId,
            decoration: InputDecoration(labelText: context.t('profile_progressive_groups_location_zone')),
            items: [for (final z in list) DropdownMenuItem(value: z.id, child: Text(z.name))],
            onChanged: (v) => setState(() {
              _zoneId = v;
              _communeId = null;
            }),
          ),
          const SizedBox(height: XmSpace.s3),
          DropdownButtonFormField<int>(
            key: ValueKey(_zoneId),
            initialValue: _communeId,
            decoration: InputDecoration(labelText: context.t('profile_progressive_groups_location_commune')),
            items: [for (final c in zone?.communes ?? const <Commune>[]) DropdownMenuItem(value: c.id, child: Text(c.name))],
            onChanged: zone == null ? null : (v) => setState(() => _communeId = v),
          ),
        ]);
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final p = widget.progressive;
    final g = widget.group;
    final canSave = !_busy && _hasValue && (!_needsConsent || _consent);
    return ListView(
      padding: const EdgeInsets.all(XmSpace.gutter),
      children: [
        Text(
          context.t('profile_progressive_step', {'current': p.answered + 1, 'total': p.groups.length}),
          style: Theme.of(context).textTheme.labelMedium,
        ),
        const SizedBox(height: XmSpace.s2),
        LinearProgressIndicator(value: p.groups.isEmpty ? 0 : p.answered / p.groups.length),
        const SizedBox(height: XmSpace.s4),
        Text(context.t('profile_progressive_groups_${g}_title'), style: Theme.of(context).textTheme.titleLarge),
        const SizedBox(height: XmSpace.s2),
        Text(context.t('profile_progressive_groups_${g}_why'), style: Theme.of(context).textTheme.bodySmall),
        const SizedBox(height: XmSpace.s4),
        _input(context),
        if (_needsConsent) ...[
          const SizedBox(height: XmSpace.s3),
          CheckboxListTile(
            key: const Key('progressive-consent'),
            contentPadding: EdgeInsets.zero,
            controlAffinity: ListTileControlAffinity.leading,
            value: _consent,
            onChanged: (v) => setState(() => _consent = v ?? false),
            title: Text(context.t('profile_progressive_sensitive_consent')),
          ),
          if (_hasValue && !_consent)
            Text(context.t('profile_progressive_sensitive_required'), style: TextStyle(color: context.xm.danger)),
        ],
        const SizedBox(height: XmSpace.s5),
        FilledButton(
          key: const Key('progressive-save'),
          onPressed: canSave ? _save : null,
          child: Text(context.t('profile_progressive_save_group')),
        ),
        const SizedBox(height: XmSpace.s2),
        OutlinedButton(
          key: const Key('progressive-skip'),
          onPressed: _busy ? null : _skip,
          child: Text(context.t('profile_progressive_prefer_not')),
        ),
      ],
    );
  }
}
