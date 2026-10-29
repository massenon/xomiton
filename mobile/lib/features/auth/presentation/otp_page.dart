import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/theme/xm_tokens.dart';
import '../../../core/l10n/app_strings.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/ui/xm_widgets.dart';
import '../application/session_controller.dart';
import '../data/auth_api.dart';

class ResetArgs {
  const ResetArgs({required this.email, required this.resetToken});
  final String email;
  final String resetToken;
}

/// Un seul écran pour la connexion, l'inscription et la réinitialisation
/// (`purpose`). Code à 6 chiffres, valable 10 minutes, 5 essais.
class OtpPage extends ConsumerStatefulWidget {
  const OtpPage({super.key, required this.purpose, required this.email});
  final OtpPurpose purpose;
  final String email;

  @override
  ConsumerState<OtpPage> createState() => _OtpPageState();
}

class _OtpPageState extends ConsumerState<OtpPage> {
  final _otpKey = GlobalKey<OtpFieldState>();
  String _code = '';
  String? _error;
  bool _busy = false;
  int _left = 30;
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    _startCountdown(30);
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  void _startCountdown(int seconds) {
    _timer?.cancel();
    setState(() => _left = seconds);
    _timer = Timer.periodic(const Duration(seconds: 1), (t) {
      if (!mounted) return t.cancel();
      setState(() => _left = (_left - 1).clamp(0, 9999));
      if (_left == 0) t.cancel();
    });
  }

  Future<void> _verify() async {
    if (_code.length < 6) {
      setState(() => _error = context.t('validation_size_string', {
            'attribute': context.t('validation_attributes_code'),
            'size': 6,
          }));
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final api = ref.read(authApiProvider);
      final r = await api.verifyOtp(widget.purpose, widget.email, _code);
      if (!mounted) return;
      switch (widget.purpose) {
        case OtpPurpose.login:
          await ref.read(sessionProvider.notifier).signIn(r.token!, r.user);
          if (mounted) context.go('/spaces'); // le routeur : sélecteur d'espace ou accueil Membre
        case OtpPurpose.signup:
          await ref.read(sessionProvider.notifier).refresh();
          if (!mounted) return;
          ScaffoldMessenger.of(context)
              .showSnackBar(SnackBar(content: Text(context.t('auth_otp_screen_verified'))));
          context.go('/home');
        case OtpPurpose.reset:
          context.go('/reset', extra: ResetArgs(email: widget.email, resetToken: r.resetToken ?? ''));
      }
    } catch (e) {
      if (!mounted) return;
      final a = ApiException.from(e);
      setState(() => _error = a.status == 429 ? null : errorMessage(context, e));
      _otpKey.currentState?.clear();
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _resend() async {
    try {
      final sent = await ref.read(authApiProvider).sendOtp(widget.purpose, widget.email);
      if (!mounted) return;
      _startCountdown(sent.resendAfter);
      setState(() => _error = null);
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(context.t('auth_otp_screen_resent'))));
    } catch (e) {
      if (!mounted) return;
      final a = ApiException.from(e);
      if (a.code == 'otp_too_soon' && a.retryAfter != null) _startCountdown(a.retryAfter!);
      setState(() => _error = a.status == 429 && a.code == 'too_many_requests' ? null : errorMessage(context, e));
    }
  }

  @override
  Widget build(BuildContext context) {
    final purposeKey = 'auth_otp_screen_purpose_${widget.purpose.name}';
    return Scaffold(
      appBar: AppBar(
        leading: BackButton(onPressed: () {
          if (context.canPop()) {
            context.pop();
          } else {
            context.go(widget.purpose == OtpPurpose.signup ? '/home' : '/login');
          }
        }),
      ),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(XmSpace.gutter),
          children: [
            Semantics(
              header: true,
              child: Text(context.t('auth_otp_screen_heading'), style: Theme.of(context).textTheme.headlineMedium),
            ),
            const SizedBox(height: XmSpace.s3),
            Text(context.t('auth_otp_screen_intro', {'email': widget.email, 'length': 6, 'minutes': 10})),
            const SizedBox(height: XmSpace.s2),
            Text(context.t(purposeKey), style: Theme.of(context).textTheme.bodySmall),
            const SizedBox(height: XmSpace.s5),
            if (_error != null) ...[XmAlert(_error!), const SizedBox(height: XmSpace.s4)],
            Semantics(
              container: true,
              label: context.t('auth_otp_screen_code_label', {'length': 6}),
              child: OtpField(key: _otpKey, onChanged: (v) => _code = v),
            ),
            const SizedBox(height: XmSpace.s3),
            Text(context.t('auth_otp_screen_not_received'), style: Theme.of(context).textTheme.labelSmall),
            const SizedBox(height: XmSpace.s5),
            FilledButton(
              onPressed: _busy ? null : _verify,
              child: _busy
                  ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2.5))
                  : Text(context.t('auth_otp_screen_submit')),
            ),
            const SizedBox(height: XmSpace.s3),
            TextButton(
              onPressed: _left > 0 ? null : _resend,
              child: Text(_left > 0
                  ? context.t('auth_otp_screen_resend_in', {'seconds': _left})
                  : context.t('auth_otp_screen_resend')),
            ),
            if (widget.purpose == OtpPurpose.signup)
              TextButton(
                onPressed: () => context.go('/home'),
                child: Text(context.t('auth_otp_screen_later')),
              ),
          ],
        ),
      ),
    );
  }
}
