import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/theme/xm_tokens.dart';
import '../../../core/l10n/app_strings.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/ui/xm_rainbow_deco.dart';
import '../../../core/ui/xm_widgets.dart';
import '../../../core/util/password_strength.dart';
import '../application/session_controller.dart';
import '../data/auth_api.dart';

/// Inscription en 4 phases (D4, Q12 c). La phase 1 crée le compte et ouvre la
/// session ; les phases 2 et 3 sont facultatives (« Passer ») et passent par
/// `PATCH me/profile` ; la phase 4 enregistre les deux consentements
/// facultatifs. Les consentements obligatoires (CGU + charte, confidentialité)
/// se donnent sur l'écran 1, jamais pré-cochés.
/// Q68 : âge minimal d'inscription, miroir de `xomiton.accounts.min_age` (web/config/xomiton.php). Seul le texte de la
/// case en dépend ; le serveur reste l'autorité (422 `age_confirmation_required`). Aucune date de naissance n'est saisie.
const int kMinRegistrationAge = 15;

/// Extrait le code d'un lien d'invitation collé (`…/inscription?invitation=XM-…`) ;
/// un code seul est rendu tel quel (≤ 40 caractères côté serveur, jamais journalisé).
String normalizeInvitation(String? raw) {
  final text = (raw ?? '').trim();
  if (text.isEmpty) return '';
  final uri = Uri.tryParse(text);
  final fromQuery = uri?.queryParameters['invitation'];
  return (fromQuery != null && fromQuery.isNotEmpty ? fromQuery : text).trim();
}

class SignupPage extends ConsumerStatefulWidget {
  /// [invitation] : code porté par le lien `/signup/:invitation` (même inscription que le public).
  const SignupPage({super.key, this.invitation});
  final String? invitation;

  @override
  ConsumerState<SignupPage> createState() => _SignupPageState();
}

class _SignupPageState extends ConsumerState<SignupPage> {
  final _scroll = ScrollController();
  int _step = 1;
  bool _busy = false;
  String? _formError;

  // Phase 1
  final _pseudo = TextEditingController();
  final _email = TextEditingController();
  final _password = TextEditingController();
  late final _invitation = TextEditingController(text: normalizeInvitation(widget.invitation));
  bool _cgu = false;
  bool _privacy = false;
  bool _age = false;
  final Map<String, String?> _fieldErrors = {};

  // Phase 2
  String? _pronouns;
  int? _zoneId;
  int? _communeId;

  // Phase 3
  final _trustedName = TextEditingController();
  final _trustedPhone = TextEditingController();
  bool _discreet = false;

  // Phase 4
  bool _sensitive = false;
  bool _newsletter = false;

  @override
  void dispose() {
    for (final c in [_pseudo, _email, _password, _invitation, _trustedName, _trustedPhone]) {
      c.dispose();
    }
    _scroll.dispose();
    super.dispose();
  }

  /// Ramène les messages d'erreur à l'écran (ils sont en haut de la liste).
  void _showErrors() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scroll.hasClients) _scroll.jumpTo(0);
    });
  }

  void _back() {
    if (_step > 1 && _step < 5) {
      setState(() {
        _step--;
        _formError = null;
      });
    } else {
      context.go('/login');
    }
  }

  Future<T?> _run<T>(Future<T> Function() action) async {
    setState(() {
      _busy = true;
      _formError = null;
    });
    try {
      return await action();
    } catch (e) {
      if (!mounted) return null;
      final api = ApiException.from(e);
      setState(() {
        for (final entry in api.errors.entries) {
          _fieldErrors[entry.key] = entry.value.first;
        }
        _formError = (api.status == 429 || (api.isValidation && api.errors.isNotEmpty))
            ? null
            : errorMessage(context, e);
        if (api.code == 'consent_required') _formError = api.message;
      });
      _showErrors();
      return null;
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  bool _validateAccount() {
    final errs = <String, String?>{};
    final pseudo = _pseudo.text.trim();
    if (pseudo.isEmpty) {
      errs['pseudo'] = vRequired(context, 'pseudo');
    } else if (pseudo.length < 3) {
      errs['pseudo'] = vMin(context, 'pseudo', 3);
    } else if (!RegExp(r'^[A-Za-z0-9_.\-]{1,40}$').hasMatch(pseudo)) {
      errs['pseudo'] = vRegex(context, 'pseudo');
    }
    final email = _email.text.trim();
    if (email.isEmpty) {
      errs['email'] = vRequired(context, 'email');
    } else if (!kEmailPattern.hasMatch(email)) {
      errs['email'] = vEmail(context, 'email');
    }
    if (_password.text.isEmpty) {
      errs['password'] = vRequired(context, 'password');
    } else if (!PasswordStrength(_password.text).meetsPolicy) {
      errs['password'] = context.t('auth_register_account_password_hint');
    }
    setState(() {
      _fieldErrors
        ..clear()
        ..addAll(errs);
      _formError = !(_cgu && _privacy)
          ? context.t('auth_consent_required')
          : (_age ? null : context.t('auth_age_confirmation_required', {'min_age': kMinRegistrationAge}));
    });
    final ok = errs.isEmpty && _cgu && _privacy && _age;
    if (!ok) _showErrors();
    return ok;
  }

  Future<void> _submitAccount() async {
    if (!_validateAccount()) return;
    final result = await _run(() => ref.read(authApiProvider).register(
          pseudo: _pseudo.text,
          email: _email.text,
          password: _password.text,
          cgu: _cgu,
          privacy: _privacy,
          ageConfirmed: _age,
          invitation: normalizeInvitation(_invitation.text),
        ));
    if (result == null || !mounted) return;
    // Le compte est créé et connecté : le routeur ne doit pas nous sortir de
    // l'inscription (les phases suivantes sont des écrans « connectés »).
    await ref.read(sessionProvider.notifier).signIn(result.token, result.user);
    if (mounted) setState(() => _step = 2);
  }

  Future<void> _submitProfile() async {
    final body = <String, dynamic>{
      if (_pronouns != null) 'pronouns': _pronouns,
      if (_zoneId != null) 'zone_id': _zoneId,
      if (_communeId != null) 'commune_id': _communeId,
    };
    if (body.isNotEmpty) {
      final r = await _run(() => ref.read(authApiProvider).patchProfile(body));
      if (r == null || !mounted) return;
      await ref.read(sessionProvider.notifier).refresh();
    }
    if (mounted) setState(() => _step = 3);
  }

  Future<void> _submitSafety() async {
    final name = _trustedName.text.trim();
    final phone = _trustedPhone.text.trim();
    final body = <String, dynamic>{
      if (name.isNotEmpty) 'trusted_contact_name': name,
      if (phone.isNotEmpty) 'trusted_contact_phone': phone,
      if (_discreet) 'discreet_mode': true,
    };
    if (body.isNotEmpty) {
      final r = await _run(() => ref.read(authApiProvider).patchProfile(body));
      if (r == null || !mounted) return;
    }
    if (mounted) setState(() => _step = 4);
  }

  Future<void> _finish() async {
    final ok = await _run(() async {
      final api = ref.read(authApiProvider);
      if (_sensitive) await api.patchProfile({'sensitive_consent': true});
      if (_newsletter) await api.patchNewsletter(subscribed: true);
      return true;
    });
    if (ok == null || !mounted) return;
    final email = ref.read(sessionProvider).user?.email ?? _email.text.trim();
    context.go('/otp?purpose=signup&email=${Uri.encodeQueryComponent(email)}');
  }

  @override
  Widget build(BuildContext context) {
    final optional = _step == 2 || _step == 3;
    final titleKey = optional ? 'auth_register_step_of_optional' : 'auth_register_step_of';
    final headings = [
      'auth_register_account_heading',
      'auth_register_profile_heading',
      'auth_register_security_heading',
      'auth_register_consents_heading',
    ];
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) _back();
      },
      child: Scaffold(
        appBar: AppBar(
          leading: BackButton(onPressed: _back),
          title: Text(context.t(titleKey, {'current': _step, 'total': 4})),
          actions: [
            if (optional)
              TextButton(
                onPressed: _busy ? null : () => setState(() => _step++),
                child: Text(context.t('auth_register_skip')),
              ),
          ],
        ),
        body: XmAuthDecor(
          child: SafeArea(
          top: false,
          child: ListView(
            controller: _scroll,
            padding: const EdgeInsets.all(XmSpace.gutter),
            children: [
              _Stepper(step: _step),
              const SizedBox(height: XmSpace.s4),
              Semantics(
                header: true,
                child: Text(context.t(headings[_step - 1]), style: Theme.of(context).textTheme.headlineSmall),
              ),
              const SizedBox(height: XmSpace.s4),
              if (_formError != null) ...[XmAlert(_formError!), const SizedBox(height: XmSpace.s4)],
              switch (_step) {
                1 => _accountStep(context),
                2 => _profileStep(context),
                3 => _safetyStep(context),
                _ => _consentsStep(context),
              },
            ],
          ),
        ),
        ),
      ),
    );
  }

  Widget _busyChild(String key) => _busy
      ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2.5))
      : Text(context.t(key));

  Widget _accountStep(BuildContext context) {
    final terms = context.t('auth_register_account_cgu_terms');
    final charter = context.t('auth_register_account_cgu_charter');
    final policy = context.t('auth_register_account_privacy_policy');
    return AutofillGroup(
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        TextField(
          controller: _pseudo,
          autofillHints: const [AutofillHints.newUsername],
          textInputAction: TextInputAction.next,
          autocorrect: false,
          decoration: InputDecoration(
            labelText: context.t('auth_register_account_pseudo'),
            helperText: context.t('auth_register_account_pseudo_hint'),
            helperMaxLines: 3,
            errorText: _fieldErrors['pseudo'],
            errorMaxLines: 3,
          ),
        ),
        const SizedBox(height: XmSpace.s4),
        TextField(
          controller: _email,
          keyboardType: TextInputType.emailAddress,
          autofillHints: const [AutofillHints.email],
          textInputAction: TextInputAction.next,
          autocorrect: false,
          decoration: InputDecoration(
            labelText: context.t('auth_register_account_email'),
            helperText: context.t('auth_register_account_email_hint'),
            helperMaxLines: 3,
            errorText: _fieldErrors['email'],
            errorMaxLines: 3,
          ),
        ),
        const SizedBox(height: XmSpace.s4),
        PasswordField(
          controller: _password,
          label: context.t('auth_register_account_password'),
          hint: context.t('auth_register_account_password_hint'),
          autofill: AutofillHints.newPassword,
          errorText: _fieldErrors['password'],
          onChanged: (_) => setState(() {}),
        ),
        const SizedBox(height: XmSpace.s3),
        PasswordStrengthMeter(_password.text),
        const SizedBox(height: XmSpace.s4),
        if (_invitation.text.trim().isNotEmpty) ...[
          XmAlert(context.t('mobile_signup_invitation_banner'), icon: Icons.mark_email_read_outlined, tone: context.xm.info),
          const SizedBox(height: XmSpace.s3),
        ],
        TextField(
          key: const ValueKey('signup-invitation'),
          controller: _invitation,
          textInputAction: TextInputAction.next,
          autocorrect: false,
          enableSuggestions: false,
          maxLength: 200,
          onChanged: (_) => setState(() => _fieldErrors.remove('invitation')),
          decoration: InputDecoration(
            labelText: context.t('mobile_signup_invitation_label'),
            helperText: context.t('mobile_signup_invitation_hint'),
            helperMaxLines: 3,
            counterText: '',
            errorText: _fieldErrors['invitation'],
            errorMaxLines: 3,
          ),
        ),
        const SizedBox(height: XmSpace.s4),
        CheckboxListTile(
          key: const ValueKey('consent-cgu'),
          contentPadding: EdgeInsets.zero,
          controlAffinity: ListTileControlAffinity.leading,
          value: _cgu,
          onChanged: (v) => setState(() => _cgu = v ?? false),
          title: Text(context.t('auth_register_account_cgu', {'terms': terms, 'charter': charter})),
        ),
        CheckboxListTile(
          key: const ValueKey('consent-privacy'),
          contentPadding: EdgeInsets.zero,
          controlAffinity: ListTileControlAffinity.leading,
          value: _privacy,
          onChanged: (v) => setState(() => _privacy = v ?? false),
          title: Text(context.t('auth_register_account_privacy', {'policy': policy})),
        ),
        CheckboxListTile(
          key: const ValueKey('consent-age'),
          contentPadding: EdgeInsets.zero,
          controlAffinity: ListTileControlAffinity.leading,
          value: _age,
          onChanged: (v) => setState(() => _age = v ?? false),
          title: Text(context.t('auth_register_account_age', {'min_age': kMinRegistrationAge})),
        ),
        const SizedBox(height: XmSpace.s4),
        FilledButton(
            onPressed: _busy ? null : _submitAccount, child: _busyChild('auth_register_account_submit')),
        const SizedBox(height: XmSpace.s3),
        TextButton(onPressed: () => context.go('/login'), child: Text(context.t('auth_register_sign_in'))),
      ]),
    );
  }

  Widget _profileStep(BuildContext context) {
    final zones = ref.watch(zonesProvider);
    const pronounValues = {
      'elle': 'auth_register_profile_pronouns_options_elle',
      'iel': 'auth_register_profile_pronouns_options_iel',
      'il': 'auth_register_profile_pronouns_options_il',
      'autre': 'auth_register_profile_pronouns_options_autre',
      'prefer_not': 'auth_register_profile_pronouns_options_nr',
    };
    return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      Text(context.t('auth_register_profile_intro'), style: Theme.of(context).textTheme.bodySmall),
      const SizedBox(height: XmSpace.s4),
      Text(context.t('auth_register_profile_pronouns'), style: Theme.of(context).textTheme.labelMedium),
      const SizedBox(height: XmSpace.s2),
      Wrap(spacing: XmSpace.s2, runSpacing: XmSpace.s2, children: [
        for (final e in pronounValues.entries)
          ChoiceChip(
            label: Text(context.t(e.value)),
            selected: _pronouns == e.key,
            onSelected: (v) => setState(() => _pronouns = v ? e.key : null),
          ),
      ]),
      const SizedBox(height: XmSpace.s4),
      zones.when(
        loading: () => const Padding(padding: EdgeInsets.all(XmSpace.s4), child: LoadingView()),
        error: (e, _) => ErrorView(error: e, onRetry: () => ref.invalidate(zonesProvider)),
        data: (list) {
          final zone = list.where((z) => z.id == _zoneId).firstOrNull;
          return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            DropdownButtonFormField<int>(
              key: ValueKey('zone-$_zoneId'),
              initialValue: _zoneId,
              isExpanded: true,
              decoration: InputDecoration(labelText: context.t('auth_register_profile_zone')),
              hint: Text(context.t('auth_register_profile_choose')),
              items: [for (final z in list) DropdownMenuItem(value: z.id, child: Text(z.name))],
              onChanged: (v) => setState(() {
                _zoneId = v;
                _communeId = null;
              }),
            ),
            const SizedBox(height: XmSpace.s4),
            DropdownButtonFormField<int>(
              key: ValueKey('commune-$_zoneId-$_communeId'),
              initialValue: _communeId,
              isExpanded: true,
              decoration: InputDecoration(
                labelText: context.t('auth_register_profile_commune'),
                helperText: zone == null ? context.t('auth_register_profile_choose_zone_first') : null,
              ),
              hint: Text(context.t('auth_register_profile_choose')),
              items: [
                for (final c in zone?.communes ?? const <Commune>[])
                  DropdownMenuItem(value: c.id, child: Text(c.name)),
              ],
              onChanged: zone == null ? null : (v) => setState(() => _communeId = v),
            ),
          ]);
        },
      ),
      const SizedBox(height: XmSpace.s5),
      FilledButton(onPressed: _busy ? null : _submitProfile, child: _busyChild('common_next')),
    ]);
  }

  Widget _safetyStep(BuildContext context) {
    return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      Text(context.t('auth_register_security_intro'), style: Theme.of(context).textTheme.bodySmall),
      const SizedBox(height: XmSpace.s4),
      TextField(
        controller: _trustedName,
        textInputAction: TextInputAction.next,
        decoration: InputDecoration(
          labelText: context.t('auth_register_security_trusted_contact_name'),
          errorText: _fieldErrors['trusted_contact_name'],
        ),
      ),
      const SizedBox(height: XmSpace.s4),
      TextField(
        controller: _trustedPhone,
        keyboardType: TextInputType.phone,
        autofillHints: const [AutofillHints.telephoneNumber],
        decoration: InputDecoration(
          labelText: context.t('auth_register_security_trusted_contact_phone'),
          helperText: context.t('auth_register_security_trusted_contact_phone_hint'),
          helperMaxLines: 3,
          errorText: _fieldErrors['trusted_contact_phone'],
          errorMaxLines: 3,
        ),
      ),
      const SizedBox(height: XmSpace.s3),
      SwitchListTile(
        contentPadding: EdgeInsets.zero,
        value: _discreet,
        onChanged: (v) => setState(() => _discreet = v),
        title: Text(context.t('auth_register_security_discreet_mode')),
        subtitle: Text(context.t('auth_register_security_discreet_mode_hint')),
      ),
      const SizedBox(height: XmSpace.s4),
      FilledButton(onPressed: _busy ? null : _submitSafety, child: _busyChild('common_next')),
    ]);
  }

  Widget _consentsStep(BuildContext context) {
    return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      Text(context.t('auth_register_consents_intro'), style: Theme.of(context).textTheme.bodySmall),
      const SizedBox(height: XmSpace.s3),
      CheckboxListTile(
        key: const ValueKey('consent-sensitive'),
        contentPadding: EdgeInsets.zero,
        controlAffinity: ListTileControlAffinity.leading,
        value: _sensitive,
        onChanged: (v) => setState(() => _sensitive = v ?? false),
        title: Text(context.t('auth_register_consents_sensitive_data')),
      ),
      CheckboxListTile(
        key: const ValueKey('consent-newsletter'),
        contentPadding: EdgeInsets.zero,
        controlAffinity: ListTileControlAffinity.leading,
        value: _newsletter,
        onChanged: (v) => setState(() => _newsletter = v ?? false),
        title: Text(context.t('auth_register_consents_newsletter')),
      ),
      const SizedBox(height: XmSpace.s4),
      FilledButton(onPressed: _busy ? null : _finish, child: _busyChild('auth_register_finish')),
    ]);
  }
}

class _Stepper extends StatelessWidget {
  const _Stepper({required this.step});
  final int step;

  @override
  Widget build(BuildContext context) {
    final xm = context.xm;
    return ExcludeSemantics(
      child: Row(children: [
        for (var i = 1; i <= 4; i++)
          Expanded(
            child: Container(
              height: 6,
              margin: EdgeInsets.only(right: i < 4 ? 6 : 0),
              decoration: BoxDecoration(
                color: i <= step ? xm.primary : xm.border,
                borderRadius: BorderRadius.circular(3),
              ),
            ),
          ),
      ]),
    );
  }
}
