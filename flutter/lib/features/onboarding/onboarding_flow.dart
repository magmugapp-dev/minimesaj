import 'dart:io';

import 'package:google_sign_in/google_sign_in.dart';
import 'package:image_picker/image_picker.dart';
import 'package:magmug/app_core.dart';
import 'package:magmug/features/home/home_flow.dart';
import 'package:sign_in_with_apple/sign_in_with_apple.dart';

// =============================================================================
// Screen 1 — Onboard
// =============================================================================

class OnboardScreen extends StatelessWidget {
  const OnboardScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return const CupertinoPageScaffold(
      backgroundColor: AppColors.white,
      child: _OnboardBody(),
    );
  }
}

class _OnboardBody extends ConsumerStatefulWidget {
  const _OnboardBody();

  @override
  ConsumerState<_OnboardBody> createState() => _OnboardBodyState();
}

class _OnboardBodyState extends ConsumerState<_OnboardBody> {
  final AppAuthApi _authApi = AppAuthApi();
  late final GoogleSignIn _googleSignIn = GoogleSignIn(
    scopes: const ['email'],
    serverClientId: SocialAuthConfig.googleServerClientId,
  );
  SocialAuthProvider? _activeAuthProvider;
  AuthNoticeData? _authNotice;

  bool get _authLoading => _activeAuthProvider != null;

  String _providerLabel(SocialAuthProvider provider) {
    return switch (provider) {
      SocialAuthProvider.google => 'Google',
      SocialAuthProvider.apple => 'Apple',
    };
  }

  @override
  void dispose() {
    _authApi.close();
    super.dispose();
  }

  Future<
    ({
      String token,
      String? displayName,
      String? firstName,
      String? lastName,
      String? avatarUrl,
    })
  >
  _getProviderCredential(SocialAuthProvider provider) async {
    switch (provider) {
      case SocialAuthProvider.google:
        final account = await _googleSignIn.signIn();
        if (account == null) {
          throw const ApiException('Google girisi iptal edildi.');
        }

        final authentication = await account.authentication;
        final idToken = authentication.idToken;
        if (idToken == null || idToken.trim().isEmpty) {
          throw const ApiException('Google kimlik jetonu alinamadi.');
        }

        return (
          token: idToken,
          displayName: account.displayName,
          firstName: null,
          lastName: null,
          avatarUrl: account.photoUrl,
        );
      case SocialAuthProvider.apple:
        if (!await SignInWithApple.isAvailable()) {
          throw const ApiException(
            'Bu cihazda Apple ile giris kullanilamiyor.',
          );
        }

        final credential = await SignInWithApple.getAppleIDCredential(
          scopes: const [
            AppleIDAuthorizationScopes.email,
            AppleIDAuthorizationScopes.fullName,
          ],
        );

        if (credential.authorizationCode.trim().isEmpty) {
          throw const ApiException('Apple yetki kodu alinamadi.');
        }

        final displayName = [credential.givenName, credential.familyName]
            .whereType<String>()
            .map((part) => part.trim())
            .where((part) => part.isNotEmpty)
            .join(' ');

        return (
          token: credential.authorizationCode,
          displayName: displayName.isEmpty ? null : displayName,
          firstName: credential.givenName,
          lastName: credential.familyName,
          avatarUrl: null,
        );
    }
  }

  void _openHome(BuildContext context) {
    Navigator.of(context).pushAndRemoveUntil(
      cupertinoRoute(const HomeScreen(mode: HomeMode.list)),
      (route) => false,
    );
  }

  Future<void> _goLogin(
    BuildContext context,
    SocialAuthProvider provider,
  ) async {
    if (_authLoading) return;
    setState(() {
      _activeAuthProvider = provider;
      _authNotice = AuthNoticeData(
        tone: AuthNoticeTone.info,
        title: '${_providerLabel(provider)} ile baglaniliyor',
        message:
            'Hesabin kontrol ediliyor. Gerekirse profil tamamlama adimlarina gecilecek.',
      );
    });

    try {
      final credential = await _getProviderCredential(provider);
      final result = await _authApi.socialLogin(
        provider: provider,
        token: credential.token,
        firstName: credential.firstName,
        lastName: credential.lastName,
        avatarUrl: credential.avatarUrl,
      );

      if (!mounted) return;

      if (result.status == SocialAuthResultStatus.authenticated) {
        final session = result.session;
        if (session == null || session.token.trim().isEmpty) {
          throw const ApiException('Oturum jetonu alinamadi.');
        }

        if (credential.displayName != null) {
          ref
              .read(onboardProvider.notifier)
              .prefillDisplayName(credential.displayName);
        }

        await ref
            .read(appAuthProvider.notifier)
            .setAuthenticatedSession(session);
        if (!mounted) return;

        setState(() {
          _activeAuthProvider = null;
          _authNotice = null;
        });
        _openHome(context);
        return;
      }

      final socialSession = result.socialSession;
      if (socialSession == null || socialSession.trim().isEmpty) {
        throw const ApiException('Onboarding oturumu olusturulamadi.');
      }

      ref
          .read(onboardProvider.notifier)
          .startSocialOnboarding(
            socialSession: socialSession,
            displayName: result.prefill?.displayName ?? credential.displayName,
          );

      setState(() {
        _activeAuthProvider = null;
        _authNotice = const AuthNoticeData(
          tone: AuthNoticeTone.info,
          title: 'Profil bilgilerini tamamla',
          message:
              'Bu hesap ilk kez goruluyor. Devam etmek icin profil bilgilerini tamamla.',
        );
      });

      Navigator.of(context).push(cupertinoRoute(const LoginStep1Screen()));
    } catch (error) {
      if (!mounted) return;
      final message = AppAuthErrorFormatter.messageFrom(
        error,
        provider: provider,
      );
      setState(() {
        _activeAuthProvider = null;
        _authNotice = AuthNoticeData(
          tone: AuthNoticeTone.error,
          title: '${_providerLabel(provider)} baglantisi baslatilamadi',
          message: message,
          retryProvider: provider,
        );
      });
    }
  }

  void _openKvkk(BuildContext context) {
    Navigator.of(context).push(cupertinoRoute(const KvkkScreen()));
  }

  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        const Positioned.fill(child: _AmbientBackground()),
        SafeArea(
          child: LayoutBuilder(
            builder: (context, constraints) {
              final compact = constraints.maxHeight < 760;
              final topPadding = compact ? 16.0 : 32.0;
              final mascotHeight = compact ? 170.0 : 250.0;

              return SingleChildScrollView(
                physics: const BouncingScrollPhysics(),
                child: ConstrainedBox(
                  constraints: BoxConstraints(minHeight: constraints.maxHeight),
                  child: Padding(
                    padding: EdgeInsets.fromLTRB(24, topPadding, 24, 24),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        SizedBox(height: compact ? 8 : 24),
                        Text(
                          'magmug',
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            fontFamily: AppFont.family,
                            fontWeight: FontWeight.w800,
                            fontSize: compact ? 44 : 52,
                            height: 1.1,
                            color: AppColors.black,
                            letterSpacing: -2.5,
                          ),
                        ),
                        const SizedBox(height: 8),
                        const _GradientTaglineText(),
                        SizedBox(height: compact ? 8 : 12),
                        SizedBox(
                          height: mascotHeight,
                          child: Center(
                            child: Image.asset(
                              'assets/images/mascot.png',
                              fit: BoxFit.contain,
                            ),
                          ),
                        ),
                        SizedBox(height: compact ? 10 : 16),
                        Text(
                          'Flortlerin icin ozel, guvenli\nve sana ozel bir alan',
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            fontFamily: AppFont.family,
                            fontWeight: FontWeight.w800,
                            fontSize: compact ? 18 : 20,
                            height: 1.35,
                            color: AppColors.black,
                            letterSpacing: -0.4,
                          ),
                        ),
                        SizedBox(height: compact ? 18 : 24),
                        AuthButton(
                          label: 'Google ile devam et',
                          iconAsset: 'assets/images/icon_google.png',
                          background: AppColors.white,
                          labelColor: AppColors.black,
                          enabled:
                              !_authLoading ||
                              _activeAuthProvider == SocialAuthProvider.google,
                          loading:
                              _activeAuthProvider == SocialAuthProvider.google,
                          onTap: () =>
                              _goLogin(context, SocialAuthProvider.google),
                        ),
                        const SizedBox(height: 12),
                        AuthButton(
                          label: 'Apple ile devam et',
                          iconAsset: 'assets/images/icon_apple.png',
                          background: AppColors.black,
                          labelColor: AppColors.white,
                          elevated: true,
                          enabled:
                              !_authLoading ||
                              _activeAuthProvider == SocialAuthProvider.apple,
                          loading:
                              _activeAuthProvider == SocialAuthProvider.apple,
                          onTap: () =>
                              _goLogin(context, SocialAuthProvider.apple),
                        ),
                        if (_authNotice != null) ...[
                          const SizedBox(height: 12),
                          AuthNoticeCard(
                            data: _authNotice!,
                            onDismiss: () => setState(() => _authNotice = null),
                            onRetry: _authNotice!.retryProvider == null
                                ? null
                                : () => _goLogin(
                                    context,
                                    _authNotice!.retryProvider!,
                                  ),
                          ),
                        ],
                        const SizedBox(height: 14),
                        _TermsLine(onTap: () => _openKvkk(context)),
                      ],
                    ),
                  ),
                ),
              );
            },
          ),
        ),
      ],
    );
  }
}

class _GradientTaglineText extends StatelessWidget {
  const _GradientTaglineText();

  @override
  Widget build(BuildContext context) {
    const baseStyle = TextStyle(
      fontFamily: AppFont.family,
      fontWeight: FontWeight.w300,
      fontSize: 22,
      height: 1.4,
      color: AppColors.gray,
    );
    return Column(
      children: [
        const Text('Mesajlasmanin', style: baseStyle),
        ShaderMask(
          shaderCallback: (bounds) => const LinearGradient(
            begin: Alignment.centerLeft,
            end: Alignment.centerRight,
            colors: [AppColors.indigo, AppColors.peach, AppColors.coral],
          ).createShader(bounds),
          child: const Text(
            'yeni hali',
            style: TextStyle(
              fontFamily: AppFont.family,
              fontWeight: FontWeight.w700,
              fontSize: 22,
              height: 1.4,
              color: AppColors.white,
            ),
          ),
        ),
      ],
    );
  }
}

class _AmbientBackground extends StatelessWidget {
  const _AmbientBackground();

  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        Positioned(
          left: -60,
          top: 40,
          child: _ambientBlob(180, const Color(0x1F5C6BFF)),
        ),
        Positioned(
          right: -40,
          top: 120,
          child: _ambientBlob(160, const Color(0x1FFF9794)),
        ),
        Positioned(
          left: 40,
          bottom: 260,
          child: _ambientBlob(140, const Color(0x1AFDB384)),
        ),
        Positioned(
          left: -40,
          bottom: -40,
          child: _ambientBlob(240, const Color(0x2A5C6BFF)),
        ),
        Positioned(
          right: -60,
          bottom: -20,
          child: _ambientBlob(220, const Color(0x2AFF9794)),
        ),
      ],
    );
  }

  Widget _ambientBlob(double size, Color color) {
    return IgnorePointer(
      child: Container(
        width: size,
        height: size,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          gradient: RadialGradient(colors: [color, color.withValues(alpha: 0)]),
        ),
      ),
    );
  }
}

class _TermsLine extends StatefulWidget {
  final VoidCallback onTap;

  const _TermsLine({required this.onTap});

  @override
  State<_TermsLine> createState() => _TermsLineState();
}

class _TermsLineState extends State<_TermsLine> {
  late final TapGestureRecognizer _recognizer;

  @override
  void initState() {
    super.initState();
    _recognizer = TapGestureRecognizer()..onTap = widget.onTap;
  }

  @override
  void dispose() {
    _recognizer.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Text.rich(
      TextSpan(
        style: const TextStyle(
          fontFamily: AppFont.family,
          fontSize: 12,
          color: AppColors.gray,
        ),
        children: [
          const TextSpan(text: 'Devam ederek '),
          TextSpan(
            text: 'Kullanim Sartlarini',
            style: const TextStyle(
              fontWeight: FontWeight.w700,
              color: AppColors.black,
              decoration: TextDecoration.underline,
            ),
            recognizer: _recognizer,
          ),
          const TextSpan(text: ' kabul etmis olursunuz'),
        ],
      ),
      textAlign: TextAlign.center,
    );
  }
}

// =============================================================================
// Screen 2 — Login Step 1 (Kisisel bilgiler)
// =============================================================================

class LoginStep1Screen extends ConsumerWidget {
  const LoginStep1Screen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final data = ref.watch(onboardProvider);
    final notifier = ref.read(onboardProvider.notifier);

    void onContinue() {
      FocusScope.of(context).unfocus();
      Navigator.of(context).push(cupertinoRoute(const LoginStep2Screen()));
    }

    return CupertinoPageScaffold(
      backgroundColor: AppColors.white,
      child: SafeArea(
        child: Column(
          children: [
            const SizedBox(height: 8),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 24),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const StepProgressBar(currentStep: 1),
                  const SizedBox(height: 20),
                  Align(
                    alignment: Alignment.centerLeft,
                    child: CircleBackButton(
                      onTap: () => Navigator.of(context).maybePop(),
                    ),
                  ),
                  const SizedBox(height: 20),
                  const Text(
                    'Magmug hesabini\nolusturalim',
                    style: TextStyle(
                      fontFamily: AppFont.family,
                      fontWeight: FontWeight.w800,
                      fontSize: 27.3,
                      height: 32.2 / 27.3,
                      color: AppColors.black,
                      letterSpacing: -1,
                    ),
                  ),
                  const SizedBox(height: 10),
                  const Text(
                    'Gercek adini yaz, boylece insanlar seni taniyabilir',
                    style: TextStyle(
                      fontFamily: AppFont.family,
                      fontSize: 14,
                      color: AppColors.gray,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 24),
            Expanded(
              child: ListView(
                padding: const EdgeInsets.symmetric(horizontal: 24),
                keyboardDismissBehavior:
                    ScrollViewKeyboardDismissBehavior.onDrag,
                children: [
                  LabeledField(
                    label: 'ISIM',
                    initialValue: data.name,
                    placeholder: 'Adini gir...',
                    onChanged: notifier.setName,
                  ),
                  const SizedBox(height: 16),
                  LabeledField(
                    label: 'SOYISIM',
                    initialValue: data.surname,
                    placeholder: 'Soyisim gir...',
                    onChanged: notifier.setSurname,
                  ),
                  const SizedBox(height: 16),
                  LabeledField(
                    label: 'KULLANICI ADI',
                    initialValue: data.username,
                    placeholder: 'Kullanici adi belirle...',
                    capitalization: TextCapitalization.none,
                    onChanged: notifier.setUsername,
                  ),
                  const SizedBox(height: 16),
                  _YearField(
                    value: data.birthYear,
                    onChanged: notifier.setBirthYear,
                  ),
                  const SizedBox(height: 10),
                  const Text(
                    'Yasin diger kullanicilara gosterilecek ancak dogum tarihin gizli kalacak',
                    style: TextStyle(
                      fontFamily: AppFont.family,
                      fontSize: 12,
                      height: 1.45,
                      color: AppColors.gray,
                    ),
                  ),
                  const SizedBox(height: 40),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(24, 8, 24, 24),
              child: GradientButton(
                label: 'Devam Et',
                onTap: data.step1Valid ? onContinue : null,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _YearField extends StatelessWidget {
  final int? value;
  final ValueChanged<int> onChanged;

  const _YearField({required this.value, required this.onChanged});

  Future<void> _openPicker(BuildContext context) async {
    FocusScope.of(context).unfocus();
    final now = DateTime.now().year;
    final years = List<int>.generate(now - 1940 + 1, (i) => now - i);
    int selected = value ?? (now - 25);
    final initialIndex = years.indexOf(selected).clamp(0, years.length - 1);

    await showCupertinoModalPopup<void>(
      context: context,
      builder: (ctx) {
        return Container(
          height: 300,
          decoration: const BoxDecoration(
            color: AppColors.white,
            borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
          ),
          child: Column(
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 10, 16, 4),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    CupertinoButton(
                      padding: EdgeInsets.zero,
                      onPressed: () => Navigator.of(ctx).pop(),
                      child: const Text(
                        'Vazgec',
                        style: TextStyle(
                          fontFamily: AppFont.family,
                          color: AppColors.gray,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ),
                    const Text(
                      'Dogum Yili',
                      style: TextStyle(
                        fontFamily: AppFont.family,
                        fontWeight: FontWeight.w700,
                        fontSize: 16,
                        color: AppColors.black,
                      ),
                    ),
                    CupertinoButton(
                      padding: EdgeInsets.zero,
                      onPressed: () {
                        onChanged(selected);
                        Navigator.of(ctx).pop();
                      },
                      child: const Text(
                        'Tamam',
                        style: TextStyle(
                          fontFamily: AppFont.family,
                          color: AppColors.indigo,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              Expanded(
                child: CupertinoPicker(
                  itemExtent: 40,
                  scrollController: FixedExtentScrollController(
                    initialItem: initialIndex,
                  ),
                  onSelectedItemChanged: (i) => selected = years[i],
                  children: years
                      .map(
                        (y) => Center(
                          child: Text(
                            '$y',
                            style: const TextStyle(
                              fontFamily: AppFont.family,
                              fontWeight: FontWeight.w600,
                              fontSize: 20,
                              color: AppColors.black,
                            ),
                          ),
                        ),
                      )
                      .toList(),
                ),
              ),
            ],
          ),
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final display = value == null ? 'Dogum yilini sec' : '$value';
    final color = value == null ? AppColors.gray : AppColors.black;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'DOGUM YILI',
          style: TextStyle(
            fontFamily: AppFont.family,
            fontWeight: FontWeight.w600,
            fontSize: 12,
            letterSpacing: 1.0,
            color: AppColors.gray,
          ),
        ),
        const SizedBox(height: 8),
        PressableScale(
          onTap: () => _openPicker(context),
          scale: 0.99,
          child: Container(
            height: 54,
            padding: const EdgeInsets.symmetric(horizontal: 18),
            decoration: BoxDecoration(
              color: AppColors.grayField,
              borderRadius: BorderRadius.circular(AppRadius.field),
            ),
            child: Row(
              children: [
                Expanded(
                  child: Text(
                    display,
                    style: TextStyle(
                      fontFamily: AppFont.family,
                      fontWeight: value == null
                          ? FontWeight.w500
                          : FontWeight.w600,
                      fontSize: 16,
                      color: color,
                    ),
                  ),
                ),
                const Icon(
                  CupertinoIcons.chevron_down,
                  size: 16,
                  color: AppColors.gray,
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

// =============================================================================
// Screen 3 — Login Step 2 (Cinsiyet)
// =============================================================================

class LoginStep2Screen extends ConsumerWidget {
  const LoginStep2Screen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final gender = ref.watch(onboardProvider.select((d) => d.gender));
    final notifier = ref.read(onboardProvider.notifier);

    void onContinue() {
      Navigator.of(context).push(cupertinoRoute(const LoginStep3Screen()));
    }

    return CupertinoPageScaffold(
      backgroundColor: AppColors.white,
      child: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const SizedBox(height: 8),
              const StepProgressBar(currentStep: 2),
              const SizedBox(height: 20),
              Align(
                alignment: Alignment.centerLeft,
                child: CircleBackButton(
                  onTap: () => Navigator.of(context).maybePop(),
                ),
              ),
              const SizedBox(height: 20),
              const Text(
                'Cinsiyetini\nbelirle',
                style: TextStyle(
                  fontFamily: AppFont.family,
                  fontWeight: FontWeight.w800,
                  fontSize: 27.3,
                  height: 32.2 / 27.3,
                  color: AppColors.black,
                  letterSpacing: -1,
                ),
              ),
              const SizedBox(height: 10),
              const Text(
                'Bu bilgi profilinde gosterilecek',
                style: TextStyle(
                  fontFamily: AppFont.family,
                  fontSize: 14,
                  color: AppColors.gray,
                ),
              ),
              const SizedBox(height: 32),
              GenderOption(
                label: 'Kadin',
                iconAsset: 'assets/images/icon_female.png',
                iconBackground: const Color(0x14FF9794),
                selected: gender == Gender.female,
                onTap: () => notifier.setGender(Gender.female),
              ),
              const SizedBox(height: 12),
              GenderOption(
                label: 'Erkek',
                iconAsset: 'assets/images/icon_male.png',
                iconBackground: const Color(0x145C6BFF),
                selected: gender == Gender.male,
                onTap: () => notifier.setGender(Gender.male),
              ),
              const Spacer(),
              GradientButton(
                label: 'Devam Et',
                onTap: gender == null ? null : onContinue,
              ),
              const SizedBox(height: 16),
            ],
          ),
        ),
      ),
    );
  }
}

// =============================================================================
// Screen 4 — Login Step 3 (Fotograf)
// =============================================================================

class LoginStep3Screen extends ConsumerStatefulWidget {
  const LoginStep3Screen({super.key});

  @override
  ConsumerState<LoginStep3Screen> createState() => _LoginStep3ScreenState();
}

class _LoginStep3ScreenState extends ConsumerState<LoginStep3Screen> {
  final AppAuthApi _authApi = AppAuthApi();
  final ImagePicker _picker = ImagePicker();
  bool _pickingPhoto = false;
  bool _submitting = false;
  AuthNoticeData? _submitNotice;

  @override
  void dispose() {
    _authApi.close();
    super.dispose();
  }

  Future<void> _pickFrom(ImageSource source) async {
    if (_pickingPhoto || _submitting) return;
    setState(() => _pickingPhoto = true);
    try {
      final file = await _picker.pickImage(
        source: source,
        imageQuality: 85,
        maxWidth: 1600,
      );
      if (file != null) {
        ref.read(onboardProvider.notifier).setPhoto(file.path);
      }
    } catch (_) {
      // permission reddedilmis olabilir — sessizce gec
    } finally {
      if (mounted) setState(() => _pickingPhoto = false);
    }
  }

  void _openPickerSheet() {
    showCupertinoModalPopup<void>(
      context: context,
      builder: (ctx) => CupertinoActionSheet(
        title: const Text(
          'Fotograf kaynagi',
          style: TextStyle(fontFamily: AppFont.family),
        ),
        actions: [
          CupertinoActionSheetAction(
            onPressed: () {
              Navigator.of(ctx).pop();
              _pickFrom(ImageSource.camera);
            },
            child: const Text(
              'Kamera',
              style: TextStyle(fontFamily: AppFont.family),
            ),
          ),
          CupertinoActionSheetAction(
            onPressed: () {
              Navigator.of(ctx).pop();
              _pickFrom(ImageSource.gallery);
            },
            child: const Text(
              'Galeri',
              style: TextStyle(fontFamily: AppFont.family),
            ),
          ),
        ],
        cancelButton: CupertinoActionSheetAction(
          isDefaultAction: true,
          onPressed: () => Navigator.of(ctx).pop(),
          child: const Text(
            'Vazgec',
            style: TextStyle(fontFamily: AppFont.family),
          ),
        ),
      ),
    );
  }

  Future<void> _finish({bool skipped = false}) async {
    if (_submitting) return;

    final notifier = ref.read(onboardProvider.notifier);
    if (skipped) {
      notifier.clearPhoto();
    }

    final data = ref.read(onboardProvider);
    if (!data.hasSocialSession) {
      Navigator.of(context).pushAndRemoveUntil(
        cupertinoRoute(const HomeScreen(mode: HomeMode.list)),
        (route) => false,
      );
      return;
    }

    setState(() {
      _submitting = true;
      _submitNotice = null;
    });

    try {
      final session = await _authApi.completeSocialRegistration(
        ref.read(onboardProvider),
      );
      await ref.read(appAuthProvider.notifier).setAuthenticatedSession(session);
      notifier.clearSocialSession();
      if (!mounted) return;

      Navigator.of(context).pushAndRemoveUntil(
        cupertinoRoute(const HomeScreen(mode: HomeMode.list)),
        (route) => false,
      );
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _submitNotice = AuthNoticeData(
          tone: AuthNoticeTone.error,
          title: 'Kayit tamamlanamadi',
          message: error is ApiException
              ? error.message
              : 'Profil bilgileri kaydedilirken bir hata olustu.',
        );
      });
    } finally {
      if (mounted) {
        setState(() => _submitting = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final photoPath = ref.watch(onboardProvider.select((d) => d.photoPath));

    return CupertinoPageScaffold(
      backgroundColor: AppColors.white,
      child: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 24),
          child: Column(
            children: [
              const SizedBox(height: 8),
              const StepProgressBar(currentStep: 3),
              const SizedBox(height: 20),
              Align(
                alignment: Alignment.centerLeft,
                child: CircleBackButton(
                  onTap: () => Navigator.of(context).maybePop(),
                ),
              ),
              const SizedBox(height: 20),
              const Text(
                'Bir fotografini\nekle',
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontFamily: AppFont.family,
                  fontWeight: FontWeight.w800,
                  fontSize: 27.3,
                  height: 32.2 / 27.3,
                  color: AppColors.black,
                  letterSpacing: -1,
                ),
              ),
              const SizedBox(height: 10),
              const Text(
                'Yuzunun net gorunduugu bir fotograf sec',
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontFamily: AppFont.family,
                  fontSize: 14,
                  color: AppColors.gray,
                ),
              ),
              const SizedBox(height: 32),
              _PhotoSlot(
                photoPath: photoPath,
                busy: _pickingPhoto,
                onTap: _submitting ? null : _openPickerSheet,
              ),
              const Spacer(),
              if (_submitNotice != null) ...[
                AuthNoticeCard(
                  data: _submitNotice!,
                  onDismiss: () => setState(() => _submitNotice = null),
                ),
                const SizedBox(height: 12),
              ],
              GradientButton(
                label: _submitting ? 'Kaydediliyor...' : 'Devam Et',
                onTap: _submitting ? null : () => _finish(),
              ),
              const SizedBox(height: 12),
              SecondaryButton(
                label: 'Bunu Atla',
                onTap: _submitting ? null : () => _finish(skipped: true),
              ),
              const SizedBox(height: 12),
            ],
          ),
        ),
      ),
    );
  }
}

class _PhotoSlot extends StatelessWidget {
  final String? photoPath;
  final bool busy;
  final VoidCallback? onTap;

  const _PhotoSlot({
    required this.photoPath,
    required this.busy,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return PressableScale(
      onTap: onTap,
      scale: 0.98,
      child: SizedBox(
        width: 180,
        height: 180,
        child: Stack(
          alignment: Alignment.center,
          children: [
            AnimatedSwitcher(
              duration: const Duration(milliseconds: 280),
              switchInCurve: Curves.easeOut,
              transitionBuilder: (child, anim) =>
                  FadeTransition(opacity: anim, child: child),
              child: photoPath == null
                  ? const _DashedPhotoPlaceholder(key: ValueKey('empty'))
                  : ClipOval(
                      key: const ValueKey('photo'),
                      child: Image.file(
                        File(photoPath!),
                        width: 180,
                        height: 180,
                        fit: BoxFit.cover,
                      ),
                    ),
            ),
            Positioned(
              right: 6,
              bottom: 6,
              child: Container(
                width: 40,
                height: 40,
                decoration: const BoxDecoration(
                  color: AppColors.indigo,
                  shape: BoxShape.circle,
                  boxShadow: [
                    BoxShadow(
                      color: AppColors.shadow,
                      blurRadius: 14,
                      offset: Offset(0, 4),
                    ),
                  ],
                ),
                alignment: Alignment.center,
                child: busy
                    ? const CupertinoActivityIndicator(color: AppColors.white)
                    : const Icon(
                        CupertinoIcons.add,
                        size: 22,
                        color: AppColors.white,
                      ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _DashedPhotoPlaceholder extends StatelessWidget {
  const _DashedPhotoPlaceholder({super.key});

  @override
  Widget build(BuildContext context) {
    return CustomPaint(
      size: const Size(180, 180),
      painter: _DashedCirclePainter(),
      child: const Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(CupertinoIcons.camera, size: 28, color: AppColors.gray),
            SizedBox(height: 8),
            Text(
              'Fotograf Sec',
              style: TextStyle(
                fontFamily: AppFont.family,
                fontWeight: FontWeight.w500,
                fontSize: 14,
                color: AppColors.gray,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _DashedCirclePainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final bgPaint = Paint()..color = AppColors.grayField;
    final center = Offset(size.width / 2, size.height / 2);
    final radius = size.width / 2;
    canvas.drawCircle(center, radius, bgPaint);

    final dashPaint = Paint()
      ..color = AppColors.grayBorder
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1.6;

    const dashCount = 48;
    const gap = 0.45;
    const dashArc = 6.28318530718 / dashCount;
    for (var i = 0; i < dashCount; i++) {
      final start = i * dashArc;
      final sweep = dashArc * (1 - gap);
      canvas.drawArc(
        Rect.fromCircle(center: center, radius: radius - 1),
        start,
        sweep,
        false,
        dashPaint,
      );
    }
  }

  @override
  bool shouldRepaint(covariant _DashedCirclePainter oldDelegate) => false;
}

// =============================================================================
// Screen 5 — KVKK Aydinlatma Metni
// =============================================================================

class KvkkScreen extends StatelessWidget {
  const KvkkScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return CupertinoPageScaffold(
      backgroundColor: AppColors.white,
      child: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const SizedBox(height: 8),
              Row(
                children: [
                  CircleBackButton(
                    filled: true,
                    onTap: () => Navigator.of(context).maybePop(),
                  ),
                  const SizedBox(width: 12),
                  const Expanded(
                    child: Text(
                      'KVKK Aydinlatma Metni',
                      style: TextStyle(
                        fontFamily: AppFont.family,
                        fontWeight: FontWeight.w800,
                        fontSize: 20,
                        color: AppColors.black,
                        letterSpacing: -0.4,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 20),
              Expanded(
                child: ListView(
                  physics: const BouncingScrollPhysics(),
                  children: const [
                    _KvkkSection(
                      title: 'Veri Sorumlusu',
                      body:
                          'magmug Teknoloji A.S. olarak kisisel verilerinizin korunmasina buyuk onem veriyoruz. 6698 sayili Kisisel Verilerin Korunmasi Kanunu kapsaminda aydinlatma yukumlulugumuzu yerine getirmekteyiz.',
                    ),
                    _KvkkSection(
                      title: 'Islenen Kisisel Veriler',
                      body:
                          'Kimlik bilgileri (ad, soyad, dogum tarihi), iletisim bilgileri (e-posta, telefon), konum verileri, gorsel veriler (profil fotograflari), uygulama kullanim verileri.',
                    ),
                    _KvkkSection(
                      title: 'Isleme Amaclari',
                      body:
                          'Hizmet sunumu, kullanici deneyiminin iyilestirilmesi, yasal yukumluluklerin yerine getirilmesi, guvenligin saglanmasi.',
                    ),
                    _KvkkSection(
                      title: 'Aktarim',
                      body:
                          'Kisisel verileriniz, yasal zorunluluklar disinda yurt ici veya yurt disina aktarilmamaktadir.',
                    ),
                    _KvkkSection(
                      title: 'Haklariniz',
                      body:
                          'Kisisel verilerinizin islenip islenmedigini ogrenme, duzeltilmesini isteme, silinmesini talep etme haklarina sahipsiniz. Basvurularinizi destek sayfamiz uzerinden iletebilirsiniz.',
                    ),
                    SizedBox(height: 24),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _KvkkSection extends StatelessWidget {
  final String title;
  final String body;

  const _KvkkSection({required this.title, required this.body});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 18),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: const TextStyle(
              fontFamily: AppFont.family,
              fontWeight: FontWeight.w700,
              fontSize: 14,
              color: AppColors.black,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            body,
            style: const TextStyle(
              fontFamily: AppFont.family,
              fontSize: 14,
              height: 1.55,
              color: AppColors.black,
            ),
          ),
        ],
      ),
    );
  }
}
