import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/theme/xm_tokens.dart';
import '../../../features/auth/application/session_controller.dart';
import '../../../features/profile/presentation/settings_page.dart' show confirmAndSignOut;
import '../../l10n/app_strings.dart';
import '../../ui/xm_rainbow_deco.dart';
import '../../ui/xm_widgets.dart';
import '../app_space.dart';
import '../space_providers.dart';

extension AppSpaceText on AppSpace {
  String label(BuildContext c) => c.t('mobile_space_${name}_label');
  String shortLabel(BuildContext c) => c.t('mobile_space_${name}_short');
  String description(BuildContext c) => c.t('mobile_space_${name}_desc');

  IconData get icon => switch (this) {
        AppSpace.member => Icons.person_outline,
        AppSpace.peer => Icons.verified_user_outlined,
        AppSpace.admin => Icons.dashboard_outlined,
      };
}

/// Déclare l'espace d'un écran : quand un shell s'affiche, l'espace actif
/// devient le sien (si le serveur l'a déclaré au compte), pour que la pastille
/// d'en-tête et le relancement retombent sur le bon espace.
class SpaceScope extends ConsumerStatefulWidget {
  const SpaceScope({super.key, required this.space, required this.child, this.onEnter});
  final AppSpace space;
  final Widget child;

  /// Appelé une fois l'espace activé (premier accès : mode discret par défaut).
  final void Function(WidgetRef ref)? onEnter;

  @override
  ConsumerState<SpaceScope> createState() => _SpaceScopeState();
}

class _SpaceScopeState extends ConsumerState<SpaceScope> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      if (ref.read(activeSpaceProvider.notifier).select(widget.space)) widget.onEnter?.call(ref);
    });
  }

  @override
  Widget build(BuildContext context) => widget.child;
}

/// Une carte d'espace : icône, nom, description ; cible d'au moins 64 px.
class SpaceTile extends StatelessWidget {
  const SpaceTile({super.key, required this.space, required this.current, required this.onTap});
  final AppSpace space;
  final bool current;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final xm = context.xm;
    final theme = Theme.of(context);
    return Semantics(
      button: true,
      selected: current,
      label: '${space.label(context)}. ${space.description(context)}'
          '${current ? ' ${context.t('mobile_space_here')}' : ''}',
      excludeSemantics: true,
      child: Material(
        color: current ? xm.primarySubtle : xm.surface,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(XmSpace.radiusLg),
          side: BorderSide(color: current ? xm.primary : xm.border, width: 1.5),
        ),
        child: InkWell(
          key: ValueKey('space-tile-${space.apiName}'),
          borderRadius: BorderRadius.circular(XmSpace.radiusLg),
          onTap: onTap,
          child: ConstrainedBox(
            constraints: const BoxConstraints(minHeight: 64),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: XmSpace.s3, vertical: XmSpace.s3),
              child: Row(children: [
                Container(
                  width: 44,
                  height: 44,
                  decoration: BoxDecoration(color: xm.primary, borderRadius: BorderRadius.circular(14)),
                  child: Icon(space.icon, color: xm.onPrimary),
                ),
                const SizedBox(width: XmSpace.s3),
                Expanded(
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text(space.label(context), style: theme.textTheme.titleMedium),
                    Text(space.description(context), style: theme.textTheme.bodySmall),
                  ]),
                ),
                if (current)
                  Text(context.t('mobile_space_here'),
                      style: theme.textTheme.labelMedium?.copyWith(color: xm.primarySubtleText))
                else
                  Icon(Icons.chevron_right, color: xm.textMuted),
              ]),
            ),
          ),
        ),
      ),
    );
  }
}

/// Va dans l'espace choisi (jamais un espace que le serveur n'a pas déclaré).
void goToSpace(BuildContext context, WidgetRef ref, AppSpace space) {
  final route = chooseSpace(ref, space);
  if (route != null) context.go(route);
}

class _SpaceList extends ConsumerWidget {
  const _SpaceList({this.onChosen});
  final VoidCallback? onChosen;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final spaces = ref.watch(spacesProvider);
    final active = ref.watch(activeSpaceProvider);
    return Semantics(
      container: true,
      label: context.t('mobile_space_selector_group'),
      child: Column(children: [
        for (final s in spaces) ...[
          SpaceTile(
            space: s,
            current: s == active,
            onTap: () {
              final router = GoRouter.of(context); // avant la fermeture de la feuille
              final route = chooseSpace(ref, s);
              onChosen?.call();
              if (route != null) router.go(route);
            },
          ),
          const SizedBox(height: XmSpace.s3),
        ],
      ]),
    );
  }
}

/// Feuille « Choisir mon espace » (pastille d'en-tête, onglet « Espaces », profil).
Future<void> showSpaceSheet(BuildContext context) {
  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    showDragHandle: true,
    builder: (ctx) => Consumer(
      builder: (ctx, ref, _) {
        final roles = (ref.watch(sessionProvider).user?.spaces ?? const <AppSpace>[])
            .map((s) => s.label(ctx))
            .join(', ');
        return SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(XmSpace.gutter, 0, XmSpace.gutter, XmSpace.s4),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Semantics(
              header: true,
              child: Text(ctx.t('mobile_space_choose_title'), style: Theme.of(ctx).textTheme.titleLarge),
            ),
            const SizedBox(height: XmSpace.s2),
            Text(ctx.t('mobile_space_choose_text', {'roles': roles}), style: Theme.of(ctx).textTheme.bodySmall),
            const SizedBox(height: XmSpace.s4),
            _SpaceList(onChosen: () => Navigator.of(ctx).pop()),
            SizedBox(
              width: double.infinity,
              child: TextButton(
                key: const ValueKey('space-sheet-close'),
                style: TextButton.styleFrom(minimumSize: const Size.fromHeight(48)),
                onPressed: () => Navigator.of(ctx).pop(),
                child: Text(ctx.t('common_close')),
              ),
            ),
          ]),
        );
      },
    ),
  );
}

/// Pastille d'en-tête « Membre ▾ » : n'existe que pour un compte à plusieurs espaces.
class SpaceSwitcherPill extends ConsumerWidget {
  const SpaceSwitcherPill({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    if (!ref.watch(hasSeveralSpacesProvider)) return const SizedBox.shrink();
    final active = ref.watch(activeSpaceProvider);
    final xm = context.xm;
    return Semantics(
      button: true,
      label: context.t('mobile_space_pill_label', {'space': active.label(context)}),
      excludeSemantics: true,
      child: Material(
        color: xm.primarySubtle,
        shape: StadiumBorder(side: BorderSide(color: xm.primary, width: 1.5)),
        child: InkWell(
          key: const ValueKey('space-pill'),
          customBorder: const StadiumBorder(),
          onTap: () => showSpaceSheet(context),
          child: ConstrainedBox(
            constraints: const BoxConstraints(minHeight: XmSpace.targetMin),
            child: Padding(
              padding: const EdgeInsets.only(left: XmSpace.s3, right: XmSpace.s2),
              child: Row(mainAxisSize: MainAxisSize.min, children: [
                Icon(active.icon, size: 18, color: xm.primarySubtleText),
                const SizedBox(width: 6),
                Text(active.shortLabel(context),
                    style: Theme.of(context).textTheme.labelLarge?.copyWith(color: xm.primarySubtleText)),
                Icon(Icons.expand_more, size: 18, color: xm.primarySubtleText),
              ]),
            ),
          ),
        ),
      ),
    );
  }
}

/// Écran « Choix de l'espace », juste après la connexion d'un compte à
/// plusieurs espaces (maquette 31). Sinon : retour direct à l'accueil membre
/// (le routeur renvoie).
class SpaceSelectorPage extends ConsumerWidget {
  const SpaceSelectorPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(sessionProvider).user;
    final theme = Theme.of(context);
    return Scaffold(
      body: XmAuthDecor(
        child: SafeArea(
          top: false,
          child: ListView(
            padding: const EdgeInsets.all(XmSpace.gutter),
            children: [
              const SizedBox(height: XmSpace.s5),
              const Align(alignment: Alignment.centerLeft, child: XmLogo(width: 120)),
              const SizedBox(height: XmSpace.s5),
              Semantics(
                header: true,
                child: Text(
                  context.t('mobile_space_selector_greeting', {'name': user?.pseudo ?? ''}),
                  style: theme.textTheme.headlineMedium,
                ),
              ),
              const SizedBox(height: XmSpace.s2),
              Text(context.t('mobile_space_selector_text'), style: theme.textTheme.bodyMedium),
              const SizedBox(height: XmSpace.s5),
              const _SpaceList(),
              Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Icon(Icons.info_outline, size: 18, color: context.xm.textMuted),
                const SizedBox(width: XmSpace.s2),
                Expanded(child: Text(context.t('mobile_space_selector_hint'), style: theme.textTheme.bodySmall)),
              ]),
              const SizedBox(height: XmSpace.s4),
              Center(
                child: TextButton(
                  key: const ValueKey('spaces-logout'),
                  style: TextButton.styleFrom(minimumSize: const Size(XmSpace.targetMin, 48)),
                  onPressed: () => confirmAndSignOut(context, ref),
                  child: Text(context.t('buttons_logout')),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
