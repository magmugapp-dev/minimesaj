import 'package:magmug/app_core.dart';

class OnboardingWelcomeView extends StatelessWidget {
  final bool authLoading;
  final SocialAuthProvider? activeAuthProvider;
  final VoidCallback onGoogleTap;
  final VoidCallback onAppleTap;
  final VoidCallback onTermsTap;

  const OnboardingWelcomeView({
    super.key,
    required this.authLoading,
    required this.activeAuthProvider,
    required this.onGoogleTap,
    required this.onAppleTap,
    required this.onTermsTap,
  });

  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        const Positioned.fill(child: OnboardingAmbientBackground()),
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
                          AppRuntimeText.instance.t('app.name.lower', 'magmug'),
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
                        const OnboardingGradientTaglineText(),
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
                          AppRuntimeText.instance.t(
                            'onboardingWelcomeHeroCopy',
                            'Flortlerin icin ozel, guvenli\nve sana ozel bir alan',
                          ),
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
                          label: AppRuntimeText.instance.t(
                            'onboarding.auth.google',
                            'Google ile devam et',
                          ),
                          iconAsset: 'assets/images/icon_google.png',
                          background: AppColors.white,
                          labelColor: AppColors.black,
                          enabled:
                              !authLoading ||
                              activeAuthProvider == SocialAuthProvider.google,
                          loading:
                              activeAuthProvider == SocialAuthProvider.google,
                          onTap: onGoogleTap,
                        ),
                        const SizedBox(height: 12),
                        AuthButton(
                          label: AppRuntimeText.instance.t(
                            'onboarding.auth.apple',
                            'Apple ile devam et',
                          ),
                          iconAsset: 'assets/images/icon_apple.png',
                          background: AppColors.black,
                          labelColor: AppColors.white,
                          elevated: true,
                          enabled:
                              !authLoading ||
                              activeAuthProvider == SocialAuthProvider.apple,
                          loading:
                              activeAuthProvider == SocialAuthProvider.apple,
                          onTap: onAppleTap,
                        ),
                        const SizedBox(height: 14),
                        OnboardingTermsLine(onTap: onTermsTap),
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

class OnboardingGradientTaglineText extends StatelessWidget {
  const OnboardingGradientTaglineText({super.key});

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
        Text(
          AppRuntimeText.instance.t(
            'onboardingWelcomeTaglinePrefix',
            'Mesajlasmanin',
          ),
          style: baseStyle,
        ),
        ShaderMask(
          shaderCallback: (bounds) => const LinearGradient(
            begin: Alignment.centerLeft,
            end: Alignment.centerRight,
            colors: [AppColors.indigo, AppColors.peach, AppColors.coral],
          ).createShader(bounds),
          child: Text(
            AppRuntimeText.instance.t(
              'onboardingWelcomeTaglineHighlight',
              'yeni hali',
            ),
            style: const TextStyle(
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

class OnboardingAmbientBackground extends StatelessWidget {
  const OnboardingAmbientBackground({super.key});

  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        const Positioned.fill(
          child: DecoratedBox(
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topCenter,
                end: Alignment.bottomCenter,
                stops: [0, 0.38, 0.7, 1],
                colors: [
                  Color(0xFFFFFEFF),
                  Color(0xFFFFF6F6),
                  Color(0xFFF2AFC6),
                  Color(0xFF8A83FF),
                ],
              ),
            ),
          ),
        ),
        Positioned(
          left: -70,
          top: 120,
          child: _ambientBlob(240, const Color(0x225F92FF)),
        ),
        Positioned(
          right: -20,
          top: 150,
          child: _ambientBlob(180, const Color(0x20FFA6C6)),
        ),
        Positioned(
          left: -50,
          bottom: 140,
          child: _ambientBlob(220, const Color(0x2DFFFFFF)),
        ),
        Positioned(
          left: -80,
          bottom: -40,
          child: _ambientBlob(300, const Color(0x3A88A6FF)),
        ),
        Positioned(
          right: -70,
          bottom: -30,
          child: _ambientBlob(320, const Color(0x40A96CFF)),
        ),
        Positioned(
          left: 30,
          right: 30,
          bottom: 170,
          child: _ambientBlob(280, const Color(0x40FFA3B5)),
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

class OnboardingTermsLine extends StatefulWidget {
  final VoidCallback onTap;

  const OnboardingTermsLine({super.key, required this.onTap});

  @override
  State<OnboardingTermsLine> createState() => _OnboardingTermsLineState();
}

class _OnboardingTermsLineState extends State<OnboardingTermsLine> {
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
          color: AppColors.black,
        ),
        children: [
          TextSpan(
            text: AppRuntimeText.instance.t(
              'onboarding.terms.prefix',
              'Devam ederek ',
            ),
          ),
          TextSpan(
            text: AppRuntimeText.instance.t(
              'onboarding.terms.link',
              'Kullanim Sartlarini',
            ),
            style: const TextStyle(
              fontWeight: FontWeight.w700,
              color: AppColors.black,
              decoration: TextDecoration.underline,
            ),
            recognizer: _recognizer,
          ),
          TextSpan(
            text: AppRuntimeText.instance.t(
              'onboarding.terms.suffix',
              ' kabul etmis olursunuz',
            ),
          ),
        ],
      ),
      textAlign: TextAlign.center,
    );
  }
}
