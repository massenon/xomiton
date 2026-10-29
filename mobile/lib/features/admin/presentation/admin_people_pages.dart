import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../../app/theme/xm_tokens.dart';
import '../../../core/l10n/app_strings.dart';
import '../../../core/ui/xm_widgets.dart';
import '../application/admin_providers.dart';
import '../data/admin_api.dart';
import '../data/admin_models.dart';
import 'admin_ui.dart';

String _day(BuildContext context, DateTime? d) =>
    d == null ? '' : DateFormat(AppStrings.of(context).raw('dates_formats_medium'), 'fr').format(d.toLocal());

// --- Inscriptions à valider (A4) -------------------------------------------------------------------

/// Inscriptions signalées, pairs invité·es et demandes du rôle pair (maquette
/// A4). Valider ou rejeter avec motif ; le refus exige la confirmation `step-up`.
class AdminRegistrationsPage extends ConsumerStatefulWidget {
  const AdminRegistrationsPage({super.key});

  @override
  ConsumerState<AdminRegistrationsPage> createState() => _AdminRegistrationsPageState();
}

class _AdminRegistrationsPageState extends ConsumerState<AdminRegistrationsPage> {
  String _tab = 'all';
  final Set<String> _busy = {};

  bool _show(AdminRegistration r) => switch (_tab) {
    'invited' => r.kind == RegistrationKind.invited,
    'request' => r.kind == RegistrationKind.roleRequest,
    'member' => r.kind == RegistrationKind.member,
    _ => true,
  };

  bool _isPair(AdminRegistration r) => r.kind != RegistrationKind.member;

  Future<void> _approve(AdminRegistration r) async {
    final ok = await confirmAdmin(
      context,
      title: context.t('mobile_admin_reg_validate_title', {'name': r.member.pseudo}),
      text: context.t(_isPair(r) ? 'mobile_admin_reg_validate_pair' : 'mobile_admin_reg_validate_member'),
      yes: context.t('mobile_admin_reg_validate'),
    );
    if (!ok || !mounted) return;
    setState(() => _busy.add(r.key));
    final api = ref.read(adminApiProvider);
    final done = r.isRoleRequest
        ? await runWithStepUp(context, ref, (t) => api.approveRoleRequest(r.requestId!, stepUp: t))
        : await runAdmin(context, ref, () => api.approveRegistration(r.member.id));
    if (!mounted) return;
    setState(() => _busy.remove(r.key));
    if (done) {
      ref.read(adminRegistrationsProvider.notifier).remove(r);
      showSnackText(
        context,
        context.t(_isPair(r) ? 'mobile_admin_reg_approved_pair' : 'mobile_admin_reg_approved_member', {'name': r.member.pseudo}),
      );
    }
  }

  Future<void> _reject(AdminRegistration r) async {
    final reason = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      showDragHandle: true,
      builder: (_) => ReasonSheet(
        title: context.t('mobile_admin_reg_reject_title', {'name': r.member.pseudo}),
        text: context.t('mobile_admin_reg_reject_text'),
        label: context.t('mobile_admin_reg_reject_reason'),
        yes: context.t('mobile_admin_reg_reject_yes'),
        shortKey: 'mobile_admin_reg_reject_reason_short',
        danger: true,
      ),
    );
    if (reason == null || !mounted) return;
    setState(() => _busy.add(r.key));
    final api = ref.read(adminApiProvider);
    final done = await runWithStepUp(
      context,
      ref,
      (t) => r.isRoleRequest
          ? api.rejectRoleRequest(r.requestId!, reason, stepUp: t)
          : api.rejectRegistration(r.member.id, reason, stepUp: t),
      reasonKey: 'mobile_admin_reg_reject_step_up',
    );
    if (!mounted) return;
    setState(() => _busy.remove(r.key));
    if (done) {
      ref.read(adminRegistrationsProvider.notifier).remove(r);
      showSnackText(context, context.t('mobile_admin_reg_rejected', {'name': r.member.pseudo}));
    }
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(adminRegistrationsProvider);
    final theme = Theme.of(context);
    return AdminDetailScaffold(
      title: context.t('mobile_admin_reg_title'),
      child: RefreshIndicator(
        onRefresh: () async => ref.invalidate(adminRegistrationsProvider),
        child: ListView(
          key: const ValueKey('admin-registrations'),
          padding: const EdgeInsets.all(XmSpace.gutter),
          physics: const AlwaysScrollableScrollPhysics(),
          children: [
            const AdminOfflineNotice(),
            Text(context.t('mobile_admin_reg_intro'), style: theme.textTheme.bodySmall),
            const SizedBox(height: XmSpace.s3),
            Semantics(
              container: true,
              label: context.t('mobile_admin_reg_tabs'),
              child: Wrap(
                spacing: XmSpace.s2,
                runSpacing: XmSpace.s2,
                children: [
                  for (final t in ['all', 'invited', 'request', 'member'])
                    ChoiceChip(
                      key: ValueKey('admin-reg-tab-$t'),
                      label: Text(context.t('mobile_admin_reg_tab_$t')),
                      selected: _tab == t,
                      materialTapTargetSize: MaterialTapTargetSize.padded,
                      onSelected: (_) => setState(() => _tab = t),
                    ),
                ],
              ),
            ),
            const SizedBox(height: XmSpace.s3),
            state.when(
              loading: () => const Padding(padding: EdgeInsets.all(XmSpace.s6), child: LoadingView()),
              error: (e, _) => AdminLoadError(error: e, onRetry: () => ref.invalidate(adminRegistrationsProvider)),
              data: (all) {
                final items = [for (final r in all) if (_show(r)) r];
                return Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Semantics(
                      liveRegion: true,
                      child: Text(context.t('mobile_admin_reg_count', {'count': items.length}), style: theme.textTheme.titleMedium),
                    ),
                    const SizedBox(height: XmSpace.s3),
                    if (items.isEmpty)
                      EmptyView(context.t('mobile_admin_reg_empty'))
                    else
                      for (final r in items)
                        Padding(
                          padding: const EdgeInsets.only(bottom: XmSpace.s3),
                          child: _RegistrationCard(
                            item: r,
                            busy: _busy.contains(r.key),
                            onApprove: () => _approve(r),
                            onReject: () => _reject(r),
                          ),
                        ),
                  ],
                );
              },
            ),
          ],
        ),
      ),
    );
  }
}

class _RegistrationCard extends ConsumerWidget {
  const _RegistrationCard({required this.item, required this.busy, required this.onApprove, required this.onReject});
  final AdminRegistration item;
  final bool busy;
  final VoidCallback onApprove;
  final VoidCallback onReject;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final m = item.member;
    final online = ref.watch(adminOfflineProvider);
    final kind = switch (item.kind) {
      RegistrationKind.invited => 'invited',
      RegistrationKind.roleRequest => 'request',
      RegistrationKind.member => 'member',
    };
    return Card(
      key: ValueKey('admin-reg-${item.key}'),
      child: Padding(
        padding: const EdgeInsets.all(XmSpace.s4),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Wrap(
              spacing: XmSpace.s2,
              runSpacing: XmSpace.s2,
              children: [
                PairBadge(
                  context.t('mobile_admin_reg_tab_$kind'),
                  tone: item.kind == RegistrationKind.member ? PairTone.neutral : PairTone.primary,
                ),
                for (final f in item.flags)
                  PairBadge(
                    AppStrings.of(context).has('mobile_admin_reg_flag_$f') ? context.t('mobile_admin_reg_flag_$f') : f,
                    tone: PairTone.warning,
                    icon: Icons.flag_outlined,
                  ),
              ],
            ),
            const SizedBox(height: XmSpace.s2),
            Text(m.pseudo, style: theme.textTheme.titleSmall),
            Text(
              [
                if (m.zone != null) m.zone!,
                if (m.createdAt != null) context.t('mobile_admin_reg_joined', {'date': _day(context, m.createdAt)}),
              ].join(' · '),
              style: theme.textTheme.bodySmall,
            ),
            Text(context.t('mobile_admin_reg_kind_$kind'), style: theme.textTheme.bodySmall),
            if (item.reason != null) Text(context.t('mobile_admin_reg_reason', {'reason': item.reason}), style: theme.textTheme.bodySmall),
            const SizedBox(height: XmSpace.s3),
            Wrap(
              spacing: XmSpace.s2,
              runSpacing: XmSpace.s2,
              children: [
                FilledButton(
                  key: ValueKey('admin-reg-approve-${item.key}'),
                  style: FilledButton.styleFrom(minimumSize: const Size(0, XmSpace.targetMin)),
                  onPressed: busy || online ? null : onApprove,
                  child: Text(context.t('mobile_admin_reg_validate')),
                ),
                OutlinedButton(
                  key: ValueKey('admin-reg-reject-${item.key}'),
                  style: OutlinedButton.styleFrom(minimumSize: const Size(0, XmSpace.targetMin)),
                  onPressed: busy || online ? null : onReject,
                  child: Text(context.t('mobile_admin_reg_reject')),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

/// Feuille de saisie d'un motif écrit pour la personne (3 à 200 caractères).
class ReasonSheet extends StatefulWidget {
  const ReasonSheet({
    super.key,
    required this.title,
    required this.text,
    required this.label,
    required this.yes,
    required this.shortKey,
    this.danger = false,
    this.min = 3,
    this.max = 200,
  });
  final String title;
  final String text;
  final String label;
  final String yes;
  final String shortKey;
  final bool danger;
  final int min;
  final int max;

  @override
  State<ReasonSheet> createState() => _ReasonSheetState();
}

class _ReasonSheetState extends State<ReasonSheet> {
  final _c = TextEditingController();
  String? _error;

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext ctx) => Padding(
    padding: EdgeInsets.fromLTRB(XmSpace.gutter, 0, XmSpace.gutter, MediaQuery.viewInsetsOf(ctx).bottom + XmSpace.s4),
    child: SingleChildScrollView(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Semantics(header: true, child: Text(widget.title, style: Theme.of(ctx).textTheme.titleLarge)),
          const SizedBox(height: XmSpace.s2),
          Text(widget.text, style: Theme.of(ctx).textTheme.bodyMedium),
          const SizedBox(height: XmSpace.s3),
          TextField(
            key: const ValueKey('admin-reason-field'),
            controller: _c,
            maxLines: 3,
            minLines: 2,
            maxLength: widget.max,
            decoration: InputDecoration(labelText: widget.label, errorText: _error),
          ),
          const SizedBox(height: XmSpace.s2),
          Row(
            children: [
              Expanded(
                child: TextButton(
                  style: TextButton.styleFrom(minimumSize: const Size(0, XmSpace.targetMin)),
                  onPressed: () => Navigator.pop(ctx),
                  child: Text(ctx.t('common_cancel')),
                ),
              ),
              const SizedBox(width: XmSpace.s2),
              Expanded(
                child: FilledButton(
                  key: const ValueKey('admin-reason-yes'),
                  style: FilledButton.styleFrom(
                    minimumSize: const Size(0, XmSpace.targetMin),
                    backgroundColor: widget.danger ? ctx.xm.danger : null,
                    foregroundColor: widget.danger ? ctx.xm.onSecondary : null,
                  ),
                  onPressed: () {
                    final v = _c.text.trim();
                    if (v.length < widget.min) {
                      setState(() => _error = ctx.t(widget.shortKey));
                      return;
                    }
                    Navigator.pop(ctx, v);
                  },
                  child: Text(widget.yes),
                ),
              ),
            ],
          ),
        ],
      ),
    ),
  );
}

// --- Rôles et invitations (A8) -------------------------------------------------------------------------

/// Invitations de pairs (lien / code à usage unique), recherche d'un membre,
/// don et retrait du rôle pair. Le rôle admin n'est jamais proposé.
class AdminRolesPage extends ConsumerStatefulWidget {
  const AdminRolesPage({super.key});

  @override
  ConsumerState<AdminRolesPage> createState() => _AdminRolesPageState();
}

class _AdminRolesPageState extends ConsumerState<AdminRolesPage> {
  final _email = TextEditingController();
  final _note = TextEditingController();
  final _query = TextEditingController();
  String _q = '';
  bool _busy = false;

  @override
  void dispose() {
    _email.dispose();
    _note.dispose();
    _query.dispose();
    super.dispose();
  }

  Future<void> _create() async {
    setState(() => _busy = true);
    AdminInvitation? created;
    final ok = await runWithStepUp(
      context,
      ref,
      (t) async => created = await ref
          .read(adminApiProvider)
          .createInvitation(email: _email.text.trim(), note: _note.text.trim(), stepUp: t),
      reasonKey: 'mobile_admin_inv_step_up',
    );
    if (!mounted) return;
    setState(() => _busy = false);
    if (ok && created != null) {
      _email.clear();
      _note.clear();
      ref.invalidate(adminInvitationsProvider);
      await showModalBottomSheet<void>(
        context: context,
        isScrollControlled: true,
        useSafeArea: true,
        showDragHandle: true,
        builder: (_) => _InvitationCreated(invitation: created!),
      );
    }
  }

  Future<void> _revoke(AdminInvitation i) async {
    final ok = await confirmAdmin(
      context,
      title: context.t('mobile_admin_inv_revoke_title'),
      text: context.t('mobile_admin_inv_revoke_text'),
      yes: context.t('mobile_admin_inv_revoke'),
      danger: true,
    );
    if (!ok || !mounted) return;
    final done = await runWithStepUp(
      context,
      ref,
      (t) => ref.read(adminApiProvider).revokeInvitation(i.id, stepUp: t),
      done: 'mobile_admin_inv_revoked',
    );
    if (done) ref.invalidate(adminInvitationsProvider);
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final online = ref.watch(adminOfflineProvider);
    final invites = ref.watch(adminInvitationsProvider);
    final members = ref.watch(adminMembersProvider(_q));
    return AdminDetailScaffold(
      title: context.t('mobile_admin_roles_title'),
      child: ListView(
        key: const ValueKey('admin-roles'),
        padding: const EdgeInsets.all(XmSpace.gutter),
        children: [
          const AdminOfflineNotice(),
          Text(context.t('mobile_admin_roles_intro'), style: theme.textTheme.bodySmall),
          const SizedBox(height: XmSpace.s4),
          SectionHeading(context.t('mobile_admin_inv_title')),
          const SizedBox(height: XmSpace.s1),
          Text(context.t('mobile_admin_inv_help'), style: theme.textTheme.bodySmall),
          const SizedBox(height: XmSpace.s3),
          TextField(
            key: const ValueKey('admin-inv-email'),
            controller: _email,
            keyboardType: TextInputType.emailAddress,
            autocorrect: false,
            decoration: InputDecoration(
              labelText: context.t('mobile_admin_inv_email'),
              helperText: context.t('mobile_admin_inv_email_hint'),
              helperMaxLines: 2,
            ),
          ),
          const SizedBox(height: XmSpace.s3),
          TextField(
            key: const ValueKey('admin-inv-note'),
            controller: _note,
            maxLength: 120,
            decoration: InputDecoration(labelText: context.t('mobile_admin_inv_note')),
          ),
          const SizedBox(height: XmSpace.s2),
          FilledButton(
            key: const ValueKey('admin-inv-create'),
            style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(XmSpace.targetMin)),
            onPressed: _busy || online ? null : _create,
            child: Text(context.t('mobile_admin_inv_create')),
          ),
          const SizedBox(height: XmSpace.s5),
          SectionHeading(context.t('mobile_admin_inv_pending')),
          const SizedBox(height: XmSpace.s2),
          invites.when(
            loading: () => const Padding(padding: EdgeInsets.all(XmSpace.s4), child: LoadingView()),
            error: (e, _) => AdminLoadError(error: e, onRetry: () => ref.invalidate(adminInvitationsProvider)),
            data: (list) => list.isEmpty
                ? Text(context.t('mobile_admin_inv_none'), style: theme.textTheme.bodySmall)
                : Column(
                    children: [
                      for (final i in list)
                        Card(
                          key: ValueKey('admin-inv-${i.id}'),
                          child: ListTile(
                            minTileHeight: 56,
                            title: Text(i.email ?? i.note ?? context.t('mobile_admin_inv_title')),
                            subtitle: Text(
                              context.t('mobile_admin_inv_ends', {
                                'hint': i.codeHint ?? '—',
                                'date': _day(context, i.expiresAt),
                              }),
                            ),
                            trailing: TextButton(
                              key: ValueKey('admin-inv-revoke-${i.id}'),
                              style: TextButton.styleFrom(minimumSize: const Size(XmSpace.targetMin, XmSpace.targetMin)),
                              onPressed: online ? null : () => _revoke(i),
                              child: Text(context.t('mobile_admin_inv_revoke')),
                            ),
                          ),
                        ),
                    ],
                  ),
          ),
          const SizedBox(height: XmSpace.s5),
          SectionHeading(context.t('mobile_admin_search_title')),
          const SizedBox(height: XmSpace.s2),
          TextField(
            key: const ValueKey('admin-member-search'),
            controller: _query,
            textInputAction: TextInputAction.search,
            decoration: InputDecoration(
              labelText: context.t('mobile_admin_search_label'),
              helperText: context.t('mobile_admin_search_hint'),
              prefixIcon: const Icon(Icons.search),
            ),
            onSubmitted: (v) => setState(() => _q = v.trim()),
            onChanged: (v) {
              if (v.trim().length >= 2 && v.trim() != _q) setState(() => _q = v.trim());
              if (v.trim().length < 2 && _q.isNotEmpty) setState(() => _q = '');
            },
          ),
          const SizedBox(height: XmSpace.s2),
          if (_q.length >= 2)
            members.when(
              loading: () => const Padding(padding: EdgeInsets.all(XmSpace.s4), child: LoadingView()),
              error: (e, _) => AdminLoadError(error: e, onRetry: () => ref.invalidate(adminMembersProvider(_q))),
              data: (list) => list.isEmpty
                  ? EmptyView(context.t('mobile_admin_search_empty'))
                  : Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Semantics(
                          liveRegion: true,
                          child: Text(context.t('mobile_admin_search_count', {'count': list.length}), style: theme.textTheme.titleSmall),
                        ),
                        for (final m in list) _MemberTile(member: m, query: _q),
                      ],
                    ),
            ),
          const SizedBox(height: XmSpace.s3),
          Text(context.t('mobile_admin_web_only'), style: theme.textTheme.bodySmall),
        ],
      ),
    );
  }
}

class _MemberTile extends ConsumerWidget {
  const _MemberTile({required this.member, required this.query});
  final AdminMember member;
  final String query;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final m = member;
    return Card(
      key: ValueKey('admin-member-${m.id}'),
      child: ListTile(
        minTileHeight: 56,
        title: Text(m.pseudo),
        subtitle: Text([if (m.zone != null) m.zone!, _roleLabel(context, m)].join(' · ')),
        trailing: const Icon(Icons.chevron_right),
        onTap: () => showModalBottomSheet<void>(
          context: context,
          isScrollControlled: true,
          useSafeArea: true,
          showDragHandle: true,
          builder: (_) => _MemberSheet(member: m, query: query),
        ),
      ),
    );
  }
}

String _roleLabel(BuildContext context, AdminMember m) {
  if (m.isStaff) return context.t('mobile_admin_staff');
  return m.isPeer ? context.t('mobile_admin_peer') : context.t('mobile_admin_member');
}

class _MemberSheet extends ConsumerStatefulWidget {
  const _MemberSheet({required this.member, required this.query});
  final AdminMember member;
  final String query;

  @override
  ConsumerState<_MemberSheet> createState() => _MemberSheetState();
}

class _MemberSheetState extends ConsumerState<_MemberSheet> {
  bool _busy = false;

  Future<void> _give(AdminMember m) async {
    final ok = await confirmAdmin(
      context,
      title: context.t('mobile_admin_member_give_title', {'name': m.pseudo}),
      text: context.t('mobile_admin_member_give_text', {'name': m.pseudo}),
      yes: context.t('mobile_admin_member_give_yes'),
    );
    if (!ok || !mounted) return;
    setState(() => _busy = true);
    final nav = Navigator.of(context);
    final done = await runWithStepUp(
      context,
      ref,
      (t) => ref.read(adminApiProvider).grantPeerRole(m.id, stepUp: t),
      reasonKey: 'mobile_admin_member_step_up',
    );
    if (!mounted) return;
    setState(() => _busy = false);
    if (done) {
      ref.invalidate(adminMembersProvider(widget.query));
      nav.pop();
      if (mounted) showSnackText(context, context.t('mobile_admin_member_gave', {'name': m.pseudo}));
    }
  }

  Future<void> _take(AdminMember m) async {
    final ok = await confirmAdmin(
      context,
      title: context.t('mobile_admin_member_take_title', {'name': m.pseudo}),
      text: context.t('mobile_admin_member_take_text', {'name': m.pseudo}),
      yes: context.t('mobile_admin_member_take_yes'),
      danger: true,
    );
    if (!ok || !mounted) return;
    setState(() => _busy = true);
    final nav = Navigator.of(context);
    final done = await runWithStepUp(
      context,
      ref,
      (t) => ref.read(adminApiProvider).revokePeerRole(m.id, stepUp: t),
      reasonKey: 'mobile_admin_member_step_up',
    );
    if (!mounted) return;
    setState(() => _busy = false);
    if (done) {
      ref.invalidate(adminMembersProvider(widget.query));
      nav.pop();
      if (mounted) showSnackText(context, context.t('mobile_admin_member_took', {'name': m.pseudo}));
    }
  }

  @override
  Widget build(BuildContext context) {
    final m = widget.member;
    final theme = Theme.of(context);
    final blocked = ref.watch(adminOfflineProvider) || _busy;
    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(XmSpace.gutter, 0, XmSpace.gutter, XmSpace.s4),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Semantics(header: true, child: Text(m.pseudo, style: theme.textTheme.titleLarge)),
          const SizedBox(height: XmSpace.s2),
          Wrap(
            spacing: XmSpace.s2,
            children: [
              PairBadge(context.t('mobile_admin_member')),
              if (m.isPeer) PairBadge(context.t('mobile_admin_peer'), tone: PairTone.primary),
              if (m.isStaff) PairBadge(context.t('mobile_admin_staff'), tone: PairTone.violet),
            ],
          ),
          const SizedBox(height: XmSpace.s2),
          Text(
            [
              if (m.zone != null) m.zone!,
              if (m.createdAt != null) context.t('mobile_admin_member_joined', {'date': _day(context, m.createdAt)}),
            ].join(' · '),
            style: theme.textTheme.bodySmall,
          ),
          const SizedBox(height: XmSpace.s4),
          if (!m.isStaff && !m.isPeer && m.can.grantPeer)
            FilledButton(
              key: const ValueKey('admin-member-give'),
              style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(XmSpace.targetMin)),
              onPressed: blocked ? null : () => _give(m),
              child: Text(context.t('mobile_admin_member_give')),
            ),
          if (!m.isStaff && m.isPeer && m.can.revokePeer)
            OutlinedButton(
              key: const ValueKey('admin-member-take'),
              style: OutlinedButton.styleFrom(minimumSize: const Size.fromHeight(XmSpace.targetMin)),
              onPressed: blocked ? null : () => _take(m),
              child: Text(context.t('mobile_admin_member_take')),
            ),
          if (m.isStaff) Text(context.t('mobile_admin_web_only'), style: theme.textTheme.bodySmall),
          SizedBox(
            width: double.infinity,
            child: TextButton(
              style: TextButton.styleFrom(minimumSize: const Size.fromHeight(XmSpace.targetMin)),
              onPressed: () => Navigator.pop(context),
              child: Text(context.t('common_close')),
            ),
          ),
        ],
      ),
    );
  }
}

/// Code et lien de l'invitation : rendus UNE seule fois par le serveur.
class _InvitationCreated extends StatelessWidget {
  const _InvitationCreated({required this.invitation});
  final AdminInvitation invitation;

  Future<void> _copy(BuildContext context, String value) async {
    await Clipboard.setData(ClipboardData(text: value));
    if (context.mounted) showSnackText(context, context.t('mobile_admin_inv_copied'));
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final i = invitation;
    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(XmSpace.gutter, 0, XmSpace.gutter, XmSpace.s4),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Semantics(header: true, child: Text(context.t('mobile_admin_inv_created'), style: theme.textTheme.titleLarge)),
          const SizedBox(height: XmSpace.s2),
          XmAlert(context.t('mobile_admin_inv_once'), icon: Icons.info_outline, tone: context.xm.warning),
          const SizedBox(height: XmSpace.s3),
          if (i.code != null) ...[
            Text(context.t('mobile_admin_inv_code'), style: theme.textTheme.bodySmall),
            SelectableText(i.code!, key: const ValueKey('admin-inv-code'), style: theme.textTheme.titleMedium),
            OutlinedButton.icon(
              key: const ValueKey('admin-inv-copy-code'),
              style: OutlinedButton.styleFrom(minimumSize: const Size(0, XmSpace.targetMin)),
              icon: const Icon(Icons.copy_outlined),
              label: Text(context.t('mobile_admin_inv_copy_code')),
              onPressed: () => _copy(context, i.code!),
            ),
            const SizedBox(height: XmSpace.s3),
          ],
          if (i.link != null) ...[
            Text(context.t('mobile_admin_inv_link'), style: theme.textTheme.bodySmall),
            SelectableText(i.link!, key: const ValueKey('admin-inv-link')),
            OutlinedButton.icon(
              key: const ValueKey('admin-inv-copy-link'),
              style: OutlinedButton.styleFrom(minimumSize: const Size(0, XmSpace.targetMin)),
              icon: const Icon(Icons.link),
              label: Text(context.t('mobile_admin_inv_copy_link')),
              onPressed: () => _copy(context, i.link!),
            ),
          ],
          const SizedBox(height: XmSpace.s3),
          SizedBox(
            width: double.infinity,
            child: TextButton(
              style: TextButton.styleFrom(minimumSize: const Size.fromHeight(XmSpace.targetMin)),
              onPressed: () => Navigator.pop(context),
              child: Text(context.t('common_close')),
            ),
          ),
        ],
      ),
    );
  }
}
