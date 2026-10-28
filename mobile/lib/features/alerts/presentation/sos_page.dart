import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/theme/xm_tokens.dart';
import '../../../core/l10n/app_strings.dart';
import '../../../core/platform/platform_services.dart';
import '../../../core/providers/core_providers.dart';
import '../../../core/ui/xm_widgets.dart';
import '../../auth/application/session_controller.dart';
import '../application/sos_controller.dart';
import '../data/alerts_api.dart';
import '../data/alerts_models.dart';
import 'sos_button.dart';

/// Écran SOS natif (P15-04). Accessible sans compte (RG-01) : onglet SOS pour
/// un·e membre, `/alerte-sos` pour un·e visiteur·se.
class SosPage extends ConsumerStatefulWidget {
  const SosPage({super.key, this.standalone = false});
  final bool standalone;

  @override
  ConsumerState<SosPage> createState() => _SosPageState();
}

class _SosPageState extends ConsumerState<SosPage> {
  late bool _share;
  final _phone = TextEditingController();
  final _message = TextEditingController();

  @override
  void initState() {
    super.initState();
    _share = ref.read(sosControllerProvider.notifier).shareDefault;
  }

  @override
  void dispose() {
    _phone.dispose();
    _message.dispose();
    super.dispose();
  }

  void _trigger() {
    final c = ref.read(sosControllerProvider.notifier)
      ..configure(share: _share, phone: _phone.text, message: _message.text);
    c.begin();
  }

  @override
  Widget build(BuildContext context) {
    final s = ref.watch(sosControllerProvider);
    final ctrl = ref.read(sosControllerProvider.notifier);
    final online = ref.watch(isOnlineProvider);
    final body = switch (s.phase) {
      SosPhase.idle => _idle(context),
      SosPhase.countdown => _countdown(context, s, ctrl),
      SosPhase.locating => _locating(context, ctrl),
      SosPhase.manual => _manual(context, s, ctrl),
      SosPhase.sending => _busy(context, 'alerts_sos_sending'),
      SosPhase.queued => _queued(context),
      SosPhase.sent => _SosSent(status: s.status!, state: s),
      SosPhase.cancelled => _cancelled(context, ctrl),
      SosPhase.failed => _failed(context, ctrl),
    };
    return Scaffold(
      appBar: widget.standalone
          ? AppBar(title: Text(context.t('alerts_sos_title')))
          : null,
      body: SafeArea(
        child: Column(
          children: [
            if (widget.standalone && !online) const OfflineBanner(),
            Expanded(
              child: ListView(
                padding: const EdgeInsets.all(XmSpace.gutter),
                children: [
                  if (!widget.standalone && s.phase == SosPhase.idle)
                    Text(
                      context.t('alerts_sos_title'),
                      style: Theme.of(context).textTheme.headlineMedium,
                    ),
                  body,
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _idle(BuildContext context) {
    final guest = !ref.watch(sessionProvider).authenticated;
    final urg = ref.watch(emergencyProvider).valueOrNull;
    final number = urg?.firstService;
    return Column(
      children: [
        const SizedBox(height: XmSpace.s3),
        Text(context.t('alerts_sos_intro')),
        const SizedBox(height: XmSpace.s5),
        Center(
          child: SosHoldButton(
            onTriggered: _trigger,
            confirmText: context.t(
              _share
                  ? 'alerts_sos_confirm_shared'
                  : 'alerts_sos_confirm_unshared',
            ),
          ),
        ),
        const SizedBox(height: XmSpace.s3),
        Text(context.t('alerts_sos_button_help'), textAlign: TextAlign.center),
        const SizedBox(height: XmSpace.s1),
        Text(
          context.t('alerts_sos_alternatives'),
          textAlign: TextAlign.center,
          style: Theme.of(context).textTheme.bodySmall,
        ),
        const SizedBox(height: XmSpace.s2),
        Text(
          context.t('alerts_sos_countdown_hint', {
            'seconds': sosCountdownSeconds,
          }),
          textAlign: TextAlign.center,
          style: Theme.of(context).textTheme.bodySmall,
        ),
        const SizedBox(height: XmSpace.s4),
        SwitchListTile(
          value: _share,
          onChanged: (v) {
            setState(() => _share = v);
            if (guest) {
              ref.read(sharedPreferencesProvider).setBool(kSosSharePref, v);
            }
          },
          title: Text(context.t('alerts_sos_share')),
          subtitle: Text(context.t('alerts_sos_share_help')),
          contentPadding: EdgeInsets.zero,
        ),
        ExpansionTile(
          title: Text(context.t('alerts_sos_details')),
          tilePadding: EdgeInsets.zero,
          children: [
            TextField(
              controller: _phone,
              keyboardType: TextInputType.phone,
              autofillHints: const [AutofillHints.telephoneNumber],
              decoration: InputDecoration(
                labelText: context.t('alerts_sos_phone'),
                hintText: context.t('alerts_sos_phone_placeholder'),
              ),
            ),
            const SizedBox(height: XmSpace.s3),
            TextField(
              controller: _message,
              maxLines: 3,
              maxLength: 1000,
              decoration: InputDecoration(
                labelText: context.t('alerts_sos_message'),
                hintText: context.t('alerts_sos_message_placeholder'),
              ),
            ),
          ],
        ),
        const SizedBox(height: XmSpace.s3),
        if (number != null)
          CallButton(
            label: context.t('alerts_sos_doubt', {'number': number.number}),
            number: number.number,
          ),
        const SizedBox(height: XmSpace.s2),
        Wrap(
          spacing: XmSpace.s2,
          children: [
            TextButton(
              onPressed: () => context.push('/urgences'),
              child: Text(context.t('alerts_sos_all_contacts')),
            ),
            TextButton(
              onPressed: () => context.push('/signaler'),
              child: Text(context.t('alerts_sos_done_report')),
            ),
          ],
        ),
      ],
    );
  }

  Widget _countdown(BuildContext context, SosState s, SosController ctrl) =>
      Column(
        children: [
          const SizedBox(height: XmSpace.s5),
          Semantics(
            liveRegion: true,
            child: Text(
              context.t('components_sos_countdown_title'),
              style: Theme.of(context).textTheme.headlineSmall,
              textAlign: TextAlign.center,
            ),
          ),
          const SizedBox(height: XmSpace.s4),
          Text(
            '${s.countdown}',
            key: const Key('sos-countdown'),
            style: Theme.of(context).textTheme.displayLarge
                ?.copyWith(color: context.xm.danger),
          ),
          const SizedBox(height: XmSpace.s3),
          Text(
            context.t('components_sos_countdown', {'s': s.countdown}),
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: XmSpace.s5),
          FilledButton(
            style: FilledButton.styleFrom(
              minimumSize: const Size.fromHeight(56),
            ),
            onPressed: ctrl.cancelCountdown,
            child: Text(context.t('components_sos_cancel')),
          ),
        ],
      );

  Widget _busy(BuildContext context, String key) => Padding(
    padding: const EdgeInsets.only(top: XmSpace.s7),
    child: Column(
      children: [
        const CircularProgressIndicator(),
        const SizedBox(height: XmSpace.s4),
        Semantics(liveRegion: true, child: Text(context.t(key))),
      ],
    ),
  );

  Widget _locating(BuildContext context, SosController ctrl) => Column(
    children: [
      _busy(context, 'alerts_sos_locating'),
      const SizedBox(height: XmSpace.s2),
      Text(context.t('alerts_sos_locating_help'), textAlign: TextAlign.center),
      const SizedBox(height: XmSpace.s4),
      OutlinedButton(
        onPressed: ctrl.skipLocate,
        child: Text(context.t('alerts_sos_send_without_position')),
      ),
    ],
  );

  Widget _manual(BuildContext context, SosState s, SosController ctrl) {
    final refs = ref.watch(referentialsProvider).valueOrNull;
    final zones = refs?.zones ?? const <Zone>[];
    final zone = zones.where((z) => z.id == s.zoneId).firstOrNull;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(
          context.t('alerts_sos_manual_title'),
          style: Theme.of(context).textTheme.titleLarge,
        ),
        const SizedBox(height: XmSpace.s2),
        Text(context.t('alerts_sos_manual_text')),
        const SizedBox(height: XmSpace.s4),
        DropdownButtonFormField<int>(
          isExpanded: true,
          key: ValueKey('zone-${s.zoneId}'),
          initialValue: zone?.id,
          decoration: InputDecoration(
            labelText: context.t('alerts_sos_manual_zone'),
          ),
          hint: Text(context.t('alerts_sos_manual_choose')),
          items: [
            for (final z in zones)
              DropdownMenuItem(value: z.id, child: Text(z.name)),
          ],
          onChanged: (v) => ctrl.setZone(v),
        ),
        if (zone != null && zone.communes.isNotEmpty) ...[
          const SizedBox(height: XmSpace.s3),
          DropdownButtonFormField<int>(
            isExpanded: true,
            key: ValueKey('commune-${s.zoneId}-${s.communeId}'),
            initialValue: zone.communes.any((c) => c.id == s.communeId)
                ? s.communeId
                : null,
            decoration: InputDecoration(
              labelText: context.t('alerts_sos_manual_commune'),
            ),
            hint: Text(context.t('alerts_sos_manual_choose')),
            items: [
              for (final c in zone.communes)
                DropdownMenuItem(value: c.id, child: Text(c.name)),
            ],
            onChanged: (v) => ctrl.setZone(s.zoneId, v),
          ),
        ],
        const SizedBox(height: XmSpace.s3),
        FilledButton(
          style: FilledButton.styleFrom(
            backgroundColor: context.xm.danger,
            minimumSize: const Size.fromHeight(52),
          ),
          onPressed: ctrl.sendNow,
          child: Text(context.t('alerts_sos_manual_send')),
        ),
        TextButton(
          onPressed: ctrl.abortManual,
          child: Text(context.t('components_sos_cancel')),
        ),
      ],
    );
  }

  Widget _queued(BuildContext context) {
    final urg = ref.watch(emergencyProvider).valueOrNull;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const SizedBox(height: XmSpace.s4),
        Icon(Icons.cloud_off_outlined, size: 48, color: context.xm.warning),
        const SizedBox(height: XmSpace.s3),
        Text(
          context.t('alerts_sos_queued_title'),
          style: Theme.of(context).textTheme.headlineSmall,
          textAlign: TextAlign.center,
        ),
        const SizedBox(height: XmSpace.s2),
        Text(context.t('alerts_sos_queued_text'), textAlign: TextAlign.center),
        const SizedBox(height: XmSpace.s2),
        Text(context.t('mobile_sos_queued_saved'), textAlign: TextAlign.center),
        const SizedBox(height: XmSpace.s4),
        for (final svc in urg?.services ?? const <EmergencyService>[])
          Padding(
            padding: const EdgeInsets.only(bottom: XmSpace.s2),
            child: CallButton(
              label: '${svc.label} · ${svc.number}',
              number: svc.number,
            ),
          ),
      ],
    );
  }

  Widget _cancelled(BuildContext context, SosController ctrl) => Column(
    children: [
      const SizedBox(height: XmSpace.s5),
      Icon(Icons.check_circle_outline, size: 48, color: context.xm.success),
      const SizedBox(height: XmSpace.s3),
      Text(
        context.t('alerts_sos_done_undone_title'),
        style: Theme.of(context).textTheme.headlineSmall,
      ),
      const SizedBox(height: XmSpace.s2),
      Text(context.t('alerts_sos_done_undone'), textAlign: TextAlign.center),
      const SizedBox(height: XmSpace.s4),
      OutlinedButton(
        onPressed: ctrl.reset,
        child: Text(context.t('alerts_sos_done_new')),
      ),
    ],
  );

  Widget _failed(BuildContext context, SosController ctrl) {
    final urg = ref.watch(emergencyProvider).valueOrNull;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const SizedBox(height: XmSpace.s4),
        XmAlert(context.t('alerts_sos_failed_text')),
        const SizedBox(height: XmSpace.s2),
        Text(
          context.t('alerts_sos_failed_title'),
          style: Theme.of(context).textTheme.titleLarge,
        ),
        const SizedBox(height: XmSpace.s3),
        for (final svc in urg?.services ?? const <EmergencyService>[])
          Padding(
            padding: const EdgeInsets.only(bottom: XmSpace.s2),
            child: CallButton(
              label: '${svc.label} · ${svc.number}',
              number: svc.number,
            ),
          ),
        FilledButton(
          onPressed: () async {
            await ctrl.discardFailed();
            ctrl.begin();
          },
          child: Text(context.t('alerts_sos_failed_retry')),
        ),
      ],
    );
  }
}

/// Alerte partie : prévenu·es, appel, repentir (60 s) et « Je suis en sécurité ».
class _SosSent extends ConsumerStatefulWidget {
  const _SosSent({required this.status, required this.state});
  final SosStatus status;
  final SosState state;

  @override
  ConsumerState<_SosSent> createState() => _SosSentState();
}

class _SosSentState extends ConsumerState<_SosSent> {
  Timer? _clock;

  @override
  void initState() {
    super.initState();
    // Le délai de repentir se compte à la seconde.
    _clock = Timer.periodic(const Duration(seconds: 1), (_) {
      if (mounted && _remaining() >= 0) setState(() {});
    });
  }

  @override
  void dispose() {
    _clock?.cancel();
    super.dispose();
  }

  int _remaining() {
    final until = widget.status.canCancelUntil;
    if (until == null) return -1;
    return until.difference(DateTime.now()).inSeconds;
  }

  @override
  Widget build(BuildContext context) {
    final st = widget.status;
    final ctrl = ref.read(sosControllerProvider.notifier);
    final urg = ref.watch(emergencyProvider).valueOrNull;
    final phone = st.primaryPhone ?? urg?.firstService?.number;
    final left = _remaining();
    final canCancel =
        !st.cancelled && !st.safe && (st.canCancelUntil == null || left > 0);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const SizedBox(height: XmSpace.s4),
        Icon(Icons.check_circle, size: 56, color: context.xm.success),
        const SizedBox(height: XmSpace.s3),
        Semantics(
          liveRegion: true,
          child: Text(
            context.t('alerts_sos_done_title'),
            style: Theme.of(context).textTheme.headlineSmall,
            textAlign: TextAlign.center,
          ),
        ),
        const SizedBox(height: XmSpace.s2),
        Text(context.t('alerts_sos_done_text'), textAlign: TextAlign.center),
        const SizedBox(height: XmSpace.s2),
        Text(
          context.t(
            st.located
                ? 'alerts_sos_done_position_joined'
                : 'alerts_sos_done_position_no',
          ),
          textAlign: TextAlign.center,
          style: Theme.of(context).textTheme.bodySmall,
        ),
        if (st.acknowledged) ...[
          const SizedBox(height: XmSpace.s2),
          XmAlert(
            context.t('alerts_sos_done_acknowledged', {
              'name': st.primaryName ?? '',
            }),
            icon: Icons.check,
            tone: context.xm.success,
          ),
        ],
        const SizedBox(height: XmSpace.s4),
        if (phone != null)
          CallButton(
            label: context.t('alerts_sos_done_call_number', {'number': phone}),
            number: phone,
          ),
        if (st.notified.isNotEmpty) ...[
          const SizedBox(height: XmSpace.s4),
          Text(
            context.t('alerts_notified_title'),
            style: Theme.of(context).textTheme.titleMedium,
          ),
          for (final n in st.notified)
            ListTile(
              contentPadding: EdgeInsets.zero,
              dense: true,
              leading: Icon(
                n.state == 'seen' ? Icons.visibility : Icons.send_outlined,
                size: 20,
              ),
              title: Text(n.label),
              subtitle: Text(
                [
                  if (n.role != null) n.role!,
                  if (n.stateLabel != null) n.stateLabel!,
                ].join(' · '),
              ),
            ),
        ],
        const SizedBox(height: XmSpace.s4),
        Text(
          context.t('alerts_sos_done_while_waiting'),
          style: Theme.of(context).textTheme.titleMedium,
        ),
        for (var i = 1; i <= 4; i++)
          if (i != 3)
            Padding(
              padding: const EdgeInsets.only(top: XmSpace.s1),
              child: Text('• ${context.t('alerts_sos_done_steps_$i')}'),
            ),
        const SizedBox(height: XmSpace.s4),
        if (widget.state.error != null)
          XmAlert(context.t('alerts_sos_done_action_failed')),
        if (!st.safe)
          FilledButton.icon(
            style: FilledButton.styleFrom(
              backgroundColor: context.xm.success,
              minimumSize: const Size.fromHeight(52),
            ),
            icon: const Icon(Icons.shield_outlined),
            onPressed: widget.state.actionBusy ? null : ctrl.markSafe,
            label: Text(context.t('alerts_sos_done_safe')),
          )
        else
          XmAlert(
            context.t('alerts_sos_done_safe_confirmed'),
            icon: Icons.shield,
            tone: context.xm.success,
          ),
        if (canCancel) ...[
          const SizedBox(height: XmSpace.s2),
          OutlinedButton(
            onPressed: widget.state.actionBusy ? null : ctrl.cancelSent,
            child: Text(context.t('alerts_sos_done_cancel')),
          ),
          if (st.canCancelUntil != null)
            Padding(
              padding: const EdgeInsets.only(top: XmSpace.s1),
              child: Text(
                context.t('alerts_sos_done_undo', {'seconds': left}),
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ),
        ],
        TextButton(
          onPressed: ctrl.reset,
          child: Text(context.t('alerts_sos_done_new')),
        ),
      ],
    );
  }
}

/// Bouton d'appel `tel:` (numéro venu de l'API ou du cache).
class CallButton extends ConsumerWidget {
  const CallButton({super.key, required this.label, required this.number});
  final String label;
  final String number;

  @override
  Widget build(BuildContext context, WidgetRef ref) => OutlinedButton.icon(
    style: OutlinedButton.styleFrom(minimumSize: const Size.fromHeight(52)),
    icon: const Icon(Icons.call_outlined),
    label: Text(label),
    onPressed: () => ref.read(linkLauncherProvider).open(telUri(number)),
  );
}
