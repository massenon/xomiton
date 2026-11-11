import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/theme/xm_tokens.dart';
import '../../../core/l10n/app_strings.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/platform/legal_links.dart';
import '../../../core/platform/platform_services.dart';
import '../../../core/providers/core_providers.dart';
import '../../../core/ui/xm_rainbow_deco.dart';
import '../../../core/ui/xm_widgets.dart';
import '../application/session_controller.dart';
import '../data/auth_api.dart';
import '../data/registration_form.dart';

/// Extrait le code d'un lien d'invitation collé (`…/inscription?invitation=XM-…`) ;
/// un code seul est rendu tel quel (≤ 40 caractères côté serveur, jamais journalisé).
String normalizeInvitation(String? raw) {
  final text = (raw ?? '').trim();
  if (text.isEmpty) return '';
  final uri = Uri.tryParse(text);
  final fromQuery = uri?.queryParameters['invitation'];
  return (fromQuery != null && fromQuery.isNotEmpty ? fromQuery : text).trim();
}

/// Inscription en 3 étapes obligatoires, UN SEUL envoi à la fin (décision du
/// client, 11/11/2026 : logique de l'ancien formulaire, comme le web). Le compte
/// n'existe qu'au bouton final : aucun appel d'inscription avant, tout reste en
/// mémoire (y compris en revenant en arrière). Rien de ce qui est saisi n'est
/// journalisé. Après succès, la session s'ouvre et le compte attend la
/// validation de l'équipe (`/en-attente`).
class SignupPage extends ConsumerStatefulWidget {
  /// [invitation] : code porté par le lien `/signup/:invitation` (même inscription que le public).
  const SignupPage({super.key, this.invitation});
  final String? invitation;

  @override
  ConsumerState<SignupPage> createState() => _SignupPageState();
}

class _SignupPageState extends ConsumerState<SignupPage> {
  final _scroll = ScrollController();
  final _form = RegistrationForm();
  int _step = 1;
  bool _busy = false;
  String? _formError;
  final Map<String, String> _errors = {};

  final _firstName = TextEditingController();
  final _lastName = TextEditingController();
  final _pseudo = TextEditingController();
  final _email = TextEditingController();
  final _age = TextEditingController();
  late final _invitation = TextEditingController(text: normalizeInvitation(widget.invitation));
  final _city = TextEditingController();
  final _phone = TextEditingController();
  final _genderOther = TextEditingController();
  final _orientationOther = TextEditingController();
  final _allyOther = TextEditingController();
  final _password = TextEditingController();
  final _confirmation = TextEditingController();

  List<TextEditingController> get _controllers => [
        _firstName,
        _lastName,
        _pseudo,
        _email,
        _age,
        _invitation,
        _city,
        _phone,
        _genderOther,
        _orientationOther,
        _allyOther,
        _password,
        _confirmation,
      ];

  @override
  void dispose() {
    for (final c in _controllers) {
      c.dispose();
    }
    _scroll.dispose();
    super.dispose();
  }

  /// Recopie les champs de texte dans le modèle (les listes et cases y vont directement).
  void _collect() {
    _form
      ..firstName = _firstName.text
      ..lastName = _lastName.text
      ..pseudo = _pseudo.text
      ..email = _email.text
      ..age = _age.text
      ..invitation = normalizeInvitation(_invitation.text)
      ..city = _city.text
      ..phone = _phone.text
      ..genderIdentityOther = _genderOther.text
      ..orientationOther = _orientationOther.text
      ..allyRoleOther = _allyOther.text
      ..password = _password.text
      ..passwordConfirmation = _confirmation.text;
  }

  void _toTop() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scroll.hasClients) _scroll.jumpTo(0);
    });
  }

  void _goTo(int step) {
    setState(() {
      _step = step;
      _formError = null;
    });
    _toTop();
  }

  void _back() {
    if (_busy) return;
    if (_step > 1) {
      _goTo(_step - 1);
    } else {
      context.go('/login');
    }
  }

  String _issueText(FormIssue i, int minAge) => switch (i.kind) {
        RegIssue.required || RegIssue.otherRequired => context.t('mobile_register_err_required'),
        RegIssue.minLength => context.t('mobile_register_err_min_length', {'min': i.param ?? 3}),
        RegIssue.format => context.t('mobile_register_err_pseudo_format'),
        RegIssue.email => context.t('mobile_register_err_email'),
        RegIssue.ageNotNumber => context.t('mobile_register_err_age_number'),
        RegIssue.ageTooLow => context.t('mobile_register_err_age_low', {'min_age': i.param ?? minAge}),
        RegIssue.password => context.t('mobile_register_password_hint'),
        RegIssue.mismatch => context.t('mobile_register_err_mismatch'),
        RegIssue.consent => context.t('mobile_register_err_consent'),
      };

  /// Valide l'étape [step] ; affiche les erreurs et renvoie vrai si elle est bonne.
  bool _validate(int step, int minAge) {
    _collect();
    final issues = _form.validateStep(step, minAge: minAge);
    setState(() {
      for (final f in kRegistrationStepFields[step]!) {
        _errors.remove(f);
      }
      issues.forEach((k, v) => _errors[k] = _issueText(v, minAge));
      _formError = issues.isEmpty ? null : context.t('mobile_register_fix_errors');
    });
    if (issues.isNotEmpty) _toTop();
    return issues.isEmpty;
  }

  void _next(int minAge) {
    if (_validate(_step, minAge)) _goTo(_step + 1);
  }

  Future<void> _submit(int minAge) async {
    if (_busy) return;
    // Toutes les étapes sont revalidées : la première fautive se rouvre.
    for (var s = 1; s <= kRegistrationSteps; s++) {
      if (!_validate(s, minAge)) {
        if (s != _step) _goTo(s);
        return;
      }
    }
    if (!ref.read(isOnlineProvider)) {
      setState(() => _formError = context.t('mobile_register_offline'));
      _toTop();
      return;
    }
    setState(() {
      _busy = true;
      _formError = null;
    });
    try {
      final result = await ref.read(authApiProvider).register(_form);
      // Le compte est créé et connecté : on ne garde plus le mot de passe en mémoire.
      _password.clear();
      _confirmation.clear();
      _form
        ..password = ''
        ..passwordConfirmation = '';
      await ref.read(sessionProvider.notifier).signIn(result.token, result.user);
      if (mounted) context.go('/en-attente');
    } catch (e) {
      if (!mounted) return;
      _onError(e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  /// 422 : chaque erreur sous son champ, l'étape du premier champ fautif se rouvre.
  void _onError(Object e) {
    final api = ApiException.from(e);
    if (api.isNetwork) {
      setState(() => _formError = context.t('mobile_register_offline'));
      _toTop();
      return;
    }
    if (api.isValidation && api.errors.isNotEmpty) {
      final known = api.errors.keys.where((k) => stepOfField(k) != null).toList();
      final step = firstFaultyStep(known);
      setState(() {
        for (final k in known) {
          _errors[k] = api.errors[k]!.first;
        }
        final other = api.errors.entries.where((x) => stepOfField(x.key) == null).toList();
        _formError = other.isNotEmpty ? other.first.value.first : context.t('mobile_register_fix_errors');
        if (step != null) _step = step;
      });
      _toTop();
      return;
    }
    setState(() => _formError = errorMessage(context, e));
    _toTop();
  }

  void _clear(String field) {
    if (_errors.containsKey(field)) setState(() => _errors.remove(field));
  }

  @override
  Widget build(BuildContext context) {
    final refs = ref.watch(registrationReferentialsProvider);
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) _back();
      },
      child: Scaffold(
        appBar: AppBar(
          leading: BackButton(onPressed: _back),
          title: Text(context.t('auth_register_step_of', {'current': _step, 'total': kRegistrationSteps})),
        ),
        body: XmAuthDecor(
          child: SafeArea(
            top: false,
            child: refs.when(
              loading: () => const LoadingView(),
              error: (e, _) => ErrorView(error: e, onRetry: () => ref.invalidate(registrationReferentialsProvider)),
              data: (r) => _buildForm(context, r),
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildForm(BuildContext context, RegistrationReferentials r) {
    final title = context.t('mobile_register_step_$_step');
    return ListView(
      controller: _scroll,
      padding: const EdgeInsets.all(XmSpace.gutter),
      children: [
        _Stepper(step: _step),
        const SizedBox(height: XmSpace.s4),
        Semantics(
          header: true,
          liveRegion: true,
          label: context.t('mobile_register_step_semantics', {'current': _step, 'total': kRegistrationSteps, 'title': title}),
          excludeSemantics: true,
          child: Text(title, style: Theme.of(context).textTheme.headlineSmall),
        ),
        const SizedBox(height: XmSpace.s2),
        Text(context.t('mobile_register_required_hint'), style: Theme.of(context).textTheme.bodySmall),
        const SizedBox(height: XmSpace.s4),
        if (_formError != null) ...[XmAlert(_formError!), const SizedBox(height: XmSpace.s4)],
        switch (_step) {
          1 => _basicStep(context, r),
          2 => _identityStep(context, r),
          _ => _detailsStep(context, r),
        },
      ],
    );
  }

  String _req(String key) => '${context.t(key)} *';

  Widget _gap() => const SizedBox(height: XmSpace.s4);

  Widget _text(
    String field,
    TextEditingController c,
    String label, {
    String? helper,
    TextInputType? keyboard,
    Iterable<String>? autofill,
    TextInputAction action = TextInputAction.next,
    int? maxLength,
    Key? key,
  }) =>
      TextField(
        key: key ?? ValueKey('reg-$field'),
        controller: c,
        keyboardType: keyboard,
        autofillHints: autofill,
        textInputAction: action,
        autocorrect: false,
        maxLength: maxLength,
        onChanged: (_) => field == 'invitation' ? setState(() => _errors.remove(field)) : _clear(field),
        decoration: InputDecoration(
          labelText: label,
          helperText: helper,
          helperMaxLines: 3,
          counterText: maxLength == null ? null : '',
          errorText: _errors[field],
          errorMaxLines: 3,
        ),
      );

  Widget _dropdown(
    String field,
    String label,
    List<RegOption> options,
    String? value,
    ValueChanged<String?> onChanged,
  ) {
    final current = options.any((o) => o.value == value) ? value : null;
    return DropdownButtonFormField<String>(
      key: ValueKey('reg-$field-$current'),
      initialValue: current,
      isExpanded: true,
      decoration: InputDecoration(labelText: label, errorText: _errors[field], errorMaxLines: 3),
      hint: Text(context.t('mobile_register_choose')),
      items: [for (final o in options) DropdownMenuItem(value: o.value, child: Text(o.label))],
      onChanged: (v) {
        _clear(field);
        onChanged(v);
      },
    );
  }

  Widget _consent(String field, ValueKey<String> key, bool value, ValueChanged<bool> onChanged, Widget title) {
    final error = _errors[field];
    final danger = context.xm.danger;
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      CheckboxListTile(
        key: key,
        contentPadding: EdgeInsets.zero,
        controlAffinity: ListTileControlAffinity.leading,
        value: value,
        onChanged: (v) {
          _clear(field);
          onChanged(v ?? false);
        },
        title: title,
      ),
      if (error != null)
        Semantics(
          liveRegion: true,
          child: Padding(
            padding: const EdgeInsets.only(left: XmSpace.s7, bottom: XmSpace.s2),
            child: Text(error, style: Theme.of(context).textTheme.bodySmall?.copyWith(color: danger)),
          ),
        ),
    ]);
  }

  Widget _busyChild(String key) => _busy
      ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator.adaptive(strokeWidth: 2.5))
      : Text(context.t(key));

  Widget _nav(int minAge, {required bool last}) => Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        FilledButton(
          key: ValueKey(last ? 'reg-submit' : 'reg-next'),
          style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(48)),
          onPressed: _busy ? null : () => last ? _submit(minAge) : _next(minAge),
          child: last ? _busyChild('mobile_register_submit') : Text(context.t('common_next')),
        ),
        const SizedBox(height: XmSpace.s3),
        if (_step > 1)
          OutlinedButton(
            key: const ValueKey('reg-previous'),
            style: OutlinedButton.styleFrom(minimumSize: const Size.fromHeight(48)),
            onPressed: _busy ? null : _back,
            child: Text(context.t('mobile_register_previous')),
          )
        else
          TextButton(
            style: TextButton.styleFrom(minimumSize: const Size.fromHeight(48)),
            onPressed: () => context.go('/login'),
            child: Text(context.t('auth_register_sign_in')),
          ),
      ]);

  // Étape 1 : informations de base.
  Widget _basicStep(BuildContext context, RegistrationReferentials r) {
    return AutofillGroup(
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        _text('first_name', _firstName, _req('mobile_register_first_name'), autofill: const [AutofillHints.givenName]),
        _gap(),
        _text('last_name', _lastName, _req('mobile_register_last_name'), autofill: const [AutofillHints.familyName]),
        _gap(),
        _text('pseudo', _pseudo, _req('mobile_register_pseudo'),
            helper: context.t('mobile_register_pseudo_hint'), autofill: const [AutofillHints.newUsername]),
        _gap(),
        _text('email', _email, _req('mobile_register_email'),
            helper: context.t('mobile_register_email_hint'),
            keyboard: TextInputType.emailAddress,
            autofill: const [AutofillHints.email]),
        _gap(),
        _text('age', _age, _req('mobile_register_age'),
            helper: context.t('mobile_register_age_hint', {'min_age': r.minAge}),
            keyboard: TextInputType.number,
            maxLength: 3),
        _gap(),
        if (_invitation.text.trim().isNotEmpty) ...[
          XmAlert(context.t('mobile_signup_invitation_banner'), icon: Icons.mark_email_read_outlined, tone: context.xm.info),
          const SizedBox(height: XmSpace.s3),
        ],
        _text('invitation', _invitation, context.t('mobile_signup_invitation_label'),
            helper: context.t('mobile_signup_invitation_hint'),
            key: const ValueKey('signup-invitation'),
            action: TextInputAction.done,
            maxLength: 200),
        const SizedBox(height: XmSpace.s5),
        _nav(r.minAge, last: false),
      ]),
    );
  }

  // Étape 2 : profil et identité.
  Widget _identityStep(BuildContext context, RegistrationReferentials r) {
    // Zone puis commune : facultatives, proposées si le pays choisi a des zones actives (routage du SOS par zone).
    final country = _form.countryCode;
    final zones = country != null && r.zoneCountries.contains(country) ? ref.watch(zonesProvider) : null;
    // Le serveur met le Bénin en premier ; on le garantit sans toucher à l'ordre du reste.
    final countries = [
      ...r.countries.where((c) => c.value == kBeninCode),
      ...r.countries.where((c) => c.value != kBeninCode),
    ];
    return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      _dropdown('pronouns', _req('mobile_register_pronouns'), r.pronouns, _form.pronouns,
          (v) => setState(() => _form.pronouns = v)),
      _gap(),
      _dropdown('country_code', _req('mobile_register_country'), countries, _form.countryCode, (v) {
        setState(() {
          if (v != _form.countryCode) {
            _form.zoneId = null;
            _form.communeId = null;
          }
          _form.countryCode = v;
        });
      }),
      _gap(),
      _text('city', _city, context.t('mobile_register_city'), autofill: const [AutofillHints.addressCity]),
      _gap(),
      _text('phone', _phone, context.t('mobile_register_phone'),
          keyboard: TextInputType.phone, autofill: const [AutofillHints.telephoneNumber]),
      if (zones != null) ...[
        _gap(),
        zones.when(
          loading: () => const Padding(padding: EdgeInsets.all(XmSpace.s4), child: LoadingView()),
          error: (e, _) => ErrorView(error: e, onRetry: () => ref.invalidate(zonesProvider)),
          data: (all) {
            final list = all.where((z) => z.countryCode == country).toList();
            if (list.isEmpty) return const SizedBox.shrink();
            final zone = list.where((z) => z.id == _form.zoneId).firstOrNull;
            return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              DropdownButtonFormField<int>(
                key: ValueKey('zone-${_form.zoneId}'),
                initialValue: _form.zoneId,
                isExpanded: true,
                decoration: InputDecoration(labelText: context.t('mobile_register_zone'), errorText: _errors['zone_id']),
                hint: Text(context.t('mobile_register_choose')),
                items: [for (final z in list) DropdownMenuItem(value: z.id, child: Text(z.name))],
                onChanged: (v) => setState(() {
                  _errors.remove('zone_id');
                  _form.zoneId = v;
                  _form.communeId = null;
                }),
              ),
              _gap(),
              DropdownButtonFormField<int>(
                key: ValueKey('commune-${_form.zoneId}-${_form.communeId}'),
                initialValue: _form.communeId,
                isExpanded: true,
                decoration: InputDecoration(
                  labelText: context.t('mobile_register_commune'),
                  helperText: zone == null ? context.t('auth_register_profile_choose_zone_first') : null,
                  errorText: _errors['commune_id'],
                ),
                hint: Text(context.t('mobile_register_choose')),
                items: [
                  for (final c in zone?.communes ?? const <Commune>[]) DropdownMenuItem(value: c.id, child: Text(c.name)),
                ],
                onChanged: zone == null
                    ? null
                    : (v) => setState(() {
                          _errors.remove('commune_id');
                          _form.communeId = v;
                        }),
              ),
            ]);
          },
        ),
      ],
      _gap(),
      _memberType(context, r),
      const SizedBox(height: XmSpace.s5),
      _nav(r.minAge, last: false),
    ]);
  }

  Widget _memberType(BuildContext context, RegistrationReferentials r) {
    final error = _errors['member_type'];
    return Semantics(
      container: true,
      label: _req('mobile_register_member_type'),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(_req('mobile_register_member_type'), style: Theme.of(context).textTheme.labelLarge),
        RadioGroup<String>(
          groupValue: _form.memberType,
          onChanged: (v) => setState(() {
            _errors.remove('member_type');
            _form.memberType = v;
          }),
          child: Column(children: [
            for (final o in r.memberTypes)
              RadioListTile<String>(
                key: ValueKey('member-type-${o.value}'),
                contentPadding: EdgeInsets.zero,
                value: o.value,
                title: Text(o.label),
              ),
          ]),
        ),
        if (error != null)
          Semantics(
            liveRegion: true,
            child: Text(error, style: Theme.of(context).textTheme.bodySmall?.copyWith(color: context.xm.danger)),
          ),
      ]),
    );
  }

  // Étape 3 : détails (membre ou allié·e) et sécurité.
  Widget _detailsStep(BuildContext context, RegistrationReferentials r) {
    final terms = context.t('mobile_register_cgu_terms');
    final charter = context.t('mobile_register_cgu_charter');
    final policy = context.t('mobile_register_privacy_policy');
    final legal = {for (final l in kLegalLinks) l.key: l};
    Widget readLink(String key, String textKey) => Align(
          alignment: Alignment.centerLeft,
          child: TextButton.icon(
            style: TextButton.styleFrom(minimumSize: const Size(48, 48)),
            icon: const Icon(Icons.open_in_new, size: 18),
            label: Text(context.t(textKey)),
            onPressed: () async {
              final messenger = ScaffoldMessenger.of(context);
              final failed = context.t('mobile_legal_open_failed');
              final ok = await ref.read(linkLauncherProvider).open(legal[key]!.uri);
              if (!ok) {
                messenger
                  ..hideCurrentSnackBar()
                  ..showSnackBar(SnackBar(content: Text(failed)));
              }
            },
          ),
        );
    return AutofillGroup(
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        if (_form.isMember) ...[
          Text(context.t('mobile_register_member_details'), style: Theme.of(context).textTheme.bodySmall),
          _gap(),
          _dropdown('orientation', _req('mobile_register_orientation'), r.orientations, _form.orientation,
              (v) => setState(() => _form.orientation = v)),
          if (_form.orientation == kOtherValue) ...[
            _gap(),
            _text('orientation_other', _orientationOther, _req('mobile_register_orientation_other')),
          ],
          _gap(),
          _dropdown('gender_identity', _req('mobile_register_gender_identity'), r.genderIdentities, _form.genderIdentity,
              (v) => setState(() => _form.genderIdentity = v)),
          if (_form.genderIdentity == kOtherValue) ...[
            _gap(),
            _text('gender_identity_other', _genderOther, _req('mobile_register_gender_identity_other')),
          ],
          _gap(),
          _consent('sensitive_data', const ValueKey('consent-sensitive'), _form.sensitiveData,
              (v) => setState(() => _form.sensitiveData = v), Text(context.t('mobile_register_sensitive_data'))),
        ] else if (_form.isAlly) ...[
          _dropdown('ally_role', _req('mobile_register_ally_role'), r.allyRoles, _form.allyRole,
              (v) => setState(() => _form.allyRole = v)),
          if (_form.allyRole == kOtherValue) ...[
            _gap(),
            _text('ally_role_other', _allyOther, _req('mobile_register_ally_role_other')),
          ],
        ],
        _gap(),
        PasswordField(
          key: const ValueKey('reg-password'),
          controller: _password,
          label: _req('mobile_register_password'),
          hint: context.t('mobile_register_password_hint'),
          autofill: AutofillHints.newPassword,
          errorText: _errors['password'],
          textInputAction: TextInputAction.next,
          onChanged: (_) => setState(() => _errors.remove('password')),
        ),
        const SizedBox(height: XmSpace.s3),
        PasswordStrengthMeter(_password.text),
        _gap(),
        PasswordField(
          key: const ValueKey('reg-password_confirmation'),
          controller: _confirmation,
          label: _req('mobile_register_password_confirmation'),
          autofill: AutofillHints.newPassword,
          errorText: _errors['password_confirmation'],
          textInputAction: TextInputAction.done,
          onChanged: (_) => setState(() => _errors.remove('password_confirmation')),
        ),
        _gap(),
        _consent('cgu', const ValueKey('consent-cgu'), _form.cgu, (v) => setState(() => _form.cgu = v),
            Text('${context.t('mobile_register_cgu', {'terms': terms, 'charter': charter})} *')),
        readLink('terms', 'mobile_register_read_terms'),
        _consent('privacy', const ValueKey('consent-privacy'), _form.privacy, (v) => setState(() => _form.privacy = v),
            Text('${context.t('mobile_register_privacy', {'policy': policy})} *')),
        readLink('privacy', 'mobile_register_read_privacy'),
        const SizedBox(height: XmSpace.s3),
        Text(context.t('mobile_register_intro_final'), style: Theme.of(context).textTheme.bodySmall),
        const SizedBox(height: XmSpace.s3),
        _nav(r.minAge, last: true),
      ]),
    );
  }
}

class _Stepper extends StatelessWidget {
  const _Stepper({required this.step});
  final int step;

  @override
  Widget build(BuildContext context) {
    final xm = context.xm;
    final style = Theme.of(context).textTheme.labelMedium;
    return Semantics(
      container: true,
      label: context.t('mobile_register_step_semantics', {
        'current': step,
        'total': kRegistrationSteps,
        'title': context.t('mobile_register_step_$step'),
      }),
      excludeSemantics: true,
      child: Column(children: [
        Row(children: [
          for (var i = 1; i <= kRegistrationSteps; i++)
            Expanded(
              child: Container(
                height: 6,
                margin: EdgeInsets.only(right: i < kRegistrationSteps ? 6 : 0),
                decoration: BoxDecoration(
                  color: i <= step ? xm.primary : xm.border,
                  borderRadius: BorderRadius.circular(3),
                ),
              ),
            ),
        ]),
        const SizedBox(height: XmSpace.s2),
        Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          for (var i = 1; i <= kRegistrationSteps; i++)
            Expanded(
              child: Padding(
                padding: const EdgeInsets.only(right: 4),
                child: Text(
                  context.t('mobile_register_step_$i'),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: style?.copyWith(
                    fontWeight: i == step ? FontWeight.w700 : FontWeight.w500,
                    color: i == step ? xm.text : xm.textMuted,
                  ),
                ),
              ),
            ),
        ]),
      ]),
    );
  }
}
