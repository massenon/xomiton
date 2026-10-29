import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_svg/flutter_svg.dart';

import '../../app/theme/xm_tokens.dart';
import '../l10n/app_strings.dart';
import '../network/api_exception.dart';
import '../util/password_strength.dart';

class XmLogo extends StatelessWidget {
  const XmLogo({super.key, this.width = 120, this.white = false});
  final double width;
  final bool white;

  @override
  Widget build(BuildContext context) => Semantics(
        label: context.t('common_logo_alt'),
        image: true,
        child: ExcludeSemantics(
          child: SvgPicture.asset(
            white ? 'assets/images/logo-mono-blanc.svg' : 'assets/images/logo-couleur.svg',
            width: width,
          ),
        ),
      );
}

/// Bandeau d'erreur (rôle « alert », annoncé par les lecteurs d'écran).
class XmAlert extends StatelessWidget {
  const XmAlert(this.message, {super.key, this.icon = Icons.error_outline, this.tone});
  final String message;
  final IconData icon;
  final Color? tone;

  @override
  Widget build(BuildContext context) {
    final c = tone ?? context.xm.danger;
    return Semantics(
      liveRegion: true,
      container: true,
      child: Container(
        padding: const EdgeInsets.all(XmSpace.s3),
        decoration: BoxDecoration(
          color: c.withValues(alpha: 0.10),
          borderRadius: BorderRadius.circular(XmSpace.radiusMd),
          border: Border.all(color: c.withValues(alpha: 0.5)),
        ),
        child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Icon(icon, size: 20, color: c),
          const SizedBox(width: XmSpace.s2),
          Expanded(child: Text(message, style: Theme.of(context).textTheme.bodyMedium)),
        ]),
      ),
    );
  }
}

/// Message d'erreur affichable d'une exception réseau ou d'API.
String errorMessage(BuildContext context, Object error) {
  final e = ApiException.from(error);
  if (e.isNetwork) return context.t('microcopy_error_unreachable');
  final server = (e.message != null && e.message!.isNotEmpty) ? e.message! : null;
  switch (e.status) {
    case 401:
      return context.t('auth_api_unauthenticated');
    case 403:
      // Compte suspendu, étape de confirmation manquante, appareil ou espace
      // retiré : le serveur sait dire pourquoi (message français), sinon texte générique.
      return server ?? context.t('microcopy_error_forbidden');
    case 404:
      return context.t('microcopy_error_not_found');
    case 413:
      return context.t('errors_api_payload_too_large');
    case 422:
      // Message français du serveur, sinon premier message de champ, sinon texte générique.
      if (server != null) return server;
      for (final list in e.errors.values) {
        if (list.isNotEmpty) return list.first;
      }
      return context.t('microcopy_error_form');
    case 429:
      return context.t('errors_api_throttled', {'seconds': e.retryAfter ?? 60});
    case 503:
      return e.code == 'maintenance'
          ? context.t('errors_api_maintenance')
          : (server ?? context.t('errors_api_server'));
  }
  if (server != null) return server;
  if ((e.status ?? 0) >= 500) return context.t('errors_api_server');
  return context.t('microcopy_error_generic');
}

class PasswordField extends StatefulWidget {
  const PasswordField({
    super.key,
    required this.controller,
    required this.label,
    this.hint,
    this.autofill = AutofillHints.password,
    this.errorText,
    this.onChanged,
    this.textInputAction,
    this.onSubmitted,
  });

  final TextEditingController controller;
  final String label;
  final String? hint;
  final String autofill;
  final String? errorText;
  final ValueChanged<String>? onChanged;
  final TextInputAction? textInputAction;
  final ValueChanged<String>? onSubmitted;

  @override
  State<PasswordField> createState() => _PasswordFieldState();
}

class _PasswordFieldState extends State<PasswordField> {
  bool _hidden = true;

  @override
  Widget build(BuildContext context) => TextField(
        controller: widget.controller,
        obscureText: _hidden,
        autofillHints: [widget.autofill],
        enableSuggestions: false,
        autocorrect: false,
        onChanged: widget.onChanged,
        textInputAction: widget.textInputAction,
        onSubmitted: widget.onSubmitted,
        decoration: InputDecoration(
          labelText: widget.label,
          helperText: widget.hint,
          helperMaxLines: 3,
          errorText: widget.errorText,
          errorMaxLines: 3,
          suffixIcon: IconButton(
            tooltip: context.t(_hidden ? 'auth_login_show_password' : 'auth_login_hide_password'),
            icon: Icon(_hidden ? Icons.visibility_outlined : Icons.visibility_off_outlined),
            onPressed: () => setState(() => _hidden = !_hidden),
          ),
        ),
      );
}

/// Jauge de solidité : texte + couleur + règles (jamais la couleur seule).
class PasswordStrengthMeter extends StatelessWidget {
  const PasswordStrengthMeter(this.password, {super.key});
  final String password;

  @override
  Widget build(BuildContext context) {
    final s = PasswordStrength(password);
    final xm = context.xm;
    final color = [xm.border, xm.danger, xm.warning, xm.info, xm.success][s.score];
    final label = context.t(s.labelKey);
    Widget rule(String key, bool ok) => Row(children: [
          Icon(ok ? Icons.check_circle : Icons.radio_button_unchecked,
              size: 16, color: ok ? xm.success : xm.textMuted),
          const SizedBox(width: 6),
          Expanded(child: Text(context.t(key), style: Theme.of(context).textTheme.bodySmall)),
        ]);
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      ExcludeSemantics(
        child: Row(children: [
          for (var i = 1; i <= 4; i++)
            Expanded(
              child: Container(
                height: 6,
                margin: EdgeInsets.only(right: i < 4 ? 4 : 0),
                decoration: BoxDecoration(
                  color: i <= s.score ? color : xm.border,
                  borderRadius: BorderRadius.circular(3),
                ),
              ),
            ),
        ]),
      ),
      const SizedBox(height: 6),
      Semantics(
        liveRegion: true,
        child: Text('${context.t('auth_password_meter_label')}$label',
            style: Theme.of(context).textTheme.labelMedium),
      ),
      const SizedBox(height: 4),
      rule('auth_password_rules_length', s.length),
      rule('auth_password_rules_uppercase', RegExp(r'[A-Z]').hasMatch(password)),
      rule('auth_password_rules_lowercase', RegExp(r'[a-z]').hasMatch(password)),
      rule('auth_password_rules_digit', s.digit),
      rule('auth_password_rules_symbol', s.symbol),
    ]);
  }
}

/// Saisie du code à 6 chiffres : 6 cases, collage du code complet, saisie
/// automatique `oneTimeCode`.
class OtpField extends StatefulWidget {
  const OtpField({super.key, required this.onChanged, this.length = 6, this.autofocus = true});
  final ValueChanged<String> onChanged;
  final int length;
  final bool autofocus;

  @override
  State<OtpField> createState() => OtpFieldState();
}

class OtpFieldState extends State<OtpField> {
  late final List<TextEditingController> _c =
      List.generate(widget.length, (_) => TextEditingController());
  late final List<FocusNode> _f = List.generate(widget.length, (_) => FocusNode());

  String get value => _c.map((e) => e.text).join();

  void clear() {
    for (final c in _c) {
      c.clear();
    }
    _f.first.requestFocus();
    widget.onChanged('');
  }

  @override
  void dispose() {
    for (final c in _c) {
      c.dispose();
    }
    for (final f in _f) {
      f.dispose();
    }
    super.dispose();
  }

  void _fill(String digits, int from) {
    final d = digits.replaceAll(RegExp(r'\D'), '');
    for (var j = 0; j < d.length && from + j < widget.length; j++) {
      _c[from + j].text = d[j];
    }
    final next = (from + d.length).clamp(0, widget.length - 1);
    _f[next].requestFocus();
    widget.onChanged(value);
  }

  @override
  Widget build(BuildContext context) {
    return AutofillGroup(
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          for (var i = 0; i < widget.length; i++)
            SizedBox(
              width: 46,
              child: Semantics(
                label: context.t('auth_otp_screen_digit', {'n': i + 1, 'length': widget.length}),
                child: TextField(
                  key: ValueKey('otp-$i'),
                  controller: _c[i],
                  focusNode: _f[i],
                  autofocus: widget.autofocus && i == 0,
                  textAlign: TextAlign.center,
                  keyboardType: TextInputType.number,
                  autofillHints: i == 0 ? const [AutofillHints.oneTimeCode] : null,
                  style: Theme.of(context).textTheme.titleLarge,
                  inputFormatters: [
                    // Le collage du code complet remplit toutes les cases.
                    TextInputFormatter.withFunction((old, v) {
                      final d = v.text.replaceAll(RegExp(r'\D'), '');
                      if (d.length > 1) {
                        WidgetsBinding.instance.addPostFrameCallback((_) => _fill(d, i));
                        return old;
                      }
                      return v.copyWith(text: d, selection: TextSelection.collapsed(offset: d.length));
                    }),
                  ],
                  decoration: const InputDecoration(contentPadding: EdgeInsets.symmetric(vertical: 12)),
                  onChanged: (t) {
                    if (t.isNotEmpty && i < widget.length - 1) _f[i + 1].requestFocus();
                    if (t.isEmpty && i > 0) _f[i - 1].requestFocus();
                    widget.onChanged(value);
                  },
                ),
              ),
            ),
        ],
      ),
    );
  }
}

/// Bandeau « hors connexion » (état toujours visible, jamais un simple toast).
class OfflineBanner extends StatelessWidget {
  const OfflineBanner({super.key});

  @override
  Widget build(BuildContext context) => Semantics(
        liveRegion: true,
        child: Container(
          width: double.infinity,
          color: context.xm.warning.withValues(alpha: 0.25),
          padding: const EdgeInsets.symmetric(horizontal: XmSpace.gutter, vertical: XmSpace.s2),
          child: Row(children: [
            Icon(Icons.cloud_off_outlined, size: 18, color: context.xm.text),
            const SizedBox(width: XmSpace.s2),
            Expanded(
                child: Text(context.t('menus_member_more_offline_banner'),
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(color: context.xm.text))),
          ]),
        ),
      );
}

class LoadingView extends StatelessWidget {
  const LoadingView({super.key});
  @override
  Widget build(BuildContext context) => Center(
        child: Semantics(
          label: context.t('microcopy_loading_a11y'),
          child: const CircularProgressIndicator(),
        ),
      );
}

class ErrorView extends StatelessWidget {
  const ErrorView({super.key, required this.error, required this.onRetry});
  final Object error;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Center(
        child: Padding(
          padding: const EdgeInsets.all(XmSpace.s5),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            Text(context.t('microcopy_error_title'),
                style: Theme.of(context).textTheme.titleMedium, textAlign: TextAlign.center),
            const SizedBox(height: XmSpace.s2),
            Text(errorMessage(context, error), textAlign: TextAlign.center),
            const SizedBox(height: XmSpace.s4),
            OutlinedButton(
                style: OutlinedButton.styleFrom(minimumSize: const Size(160, 48)),
                onPressed: onRetry,
                child: Text(context.t('common_retry'))),
          ]),
        ),
      );
}

class EmptyView extends StatelessWidget {
  const EmptyView(this.message, {super.key});
  final String message;
  @override
  Widget build(BuildContext context) => Center(
        child: Padding(
          padding: const EdgeInsets.all(XmSpace.s5),
          child: Text(message, textAlign: TextAlign.center, style: Theme.of(context).textTheme.bodySmall),
        ),
      );
}

/// Messages de validation locale, repris de `validation.php` (mêmes textes que
/// le serveur) ; `attr` = clé de `validation_attributes_*`.
String vRequired(BuildContext c, String attr) =>
    c.t('validation_required', {'attribute': c.t('validation_attributes_$attr')});
String vEmail(BuildContext c, String attr) =>
    c.t('validation_email', {'attribute': c.t('validation_attributes_$attr')});
String vMin(BuildContext c, String attr, int min) => c.t(
    'validation_min_string', {'attribute': c.t('validation_attributes_$attr'), 'min': min});
String vRegex(BuildContext c, String attr) =>
    c.t('validation_regex', {'attribute': c.t('validation_attributes_$attr')});
String vConfirmed(BuildContext c, String attr) =>
    c.t('validation_confirmed', {'attribute': c.t('validation_attributes_$attr')});

final RegExp kEmailPattern = RegExp(r'^[^\s@]+@[^\s@]+\.[^\s@]+$');
