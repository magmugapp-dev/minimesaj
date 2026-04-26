import 'dart:async';

import 'package:google_sign_in/google_sign_in.dart';
import 'package:image_picker/image_picker.dart';
import 'package:magmug/app_core.dart';
import 'package:magmug/features/home/home_flow.dart';
import 'package:magmug/features/onboarding/onboarding_dialogs.dart';
import 'package:magmug/features/onboarding/onboarding_social_auth.dart';
import 'package:magmug/features/onboarding/widgets/onboarding_kvkk_widgets.dart';
import 'package:magmug/features/onboarding/widgets/onboarding_photo_widgets.dart';
import 'package:magmug/features/onboarding/widgets/onboarding_step1_widgets.dart';
import 'package:magmug/features/onboarding/widgets/onboarding_step2_widgets.dart';
import 'package:magmug/features/onboarding/widgets/onboarding_step3_widgets.dart';
import 'package:magmug/features/onboarding/widgets/onboarding_welcome_widgets.dart';

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

  bool get _authLoading => _activeAuthProvider != null;

  @override
  void dispose() {
    _authApi.close();
    super.dispose();
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
    });

    try {
      final credential = await getOnboardingSocialCredential(
        provider: provider,
        googleSignIn: _googleSignIn,
      );
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
          throw ApiException(
            AppRuntimeText.instance.t(
              'socialSessionTokenMissing',
              'Oturum jetonu alinamadi.',
            ),
          );
        }

        if (credential.displayName != null) {
          ref
              .read(onboardProvider.notifier)
              .prefillDisplayName(credential.displayName);
        }

        await ref
            .read(appAuthProvider.notifier)
            .setAuthenticatedSession(session);
        if (!context.mounted) return;

        setState(() {
          _activeAuthProvider = null;
        });
        _openHome(context);
        return;
      }

      final socialSession = result.socialSession;
      if (socialSession == null || socialSession.trim().isEmpty) {
        throw ApiException(
          AppRuntimeText.instance.t(
            'onboardingSessionCreateFailed',
            'Onboarding oturumu olusturulamadi.',
          ),
        );
      }

      ref
          .read(onboardProvider.notifier)
          .startSocialOnboarding(
            socialSession: socialSession,
            displayName: result.prefill?.displayName ?? credential.displayName,
            avatarUrl: result.prefill?.avatarUrl ?? credential.avatarUrl,
          );

      setState(() {
        _activeAuthProvider = null;
      });

      if (!context.mounted) return;
      Navigator.of(context).push(cupertinoRoute(const LoginStep1Screen()));
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _activeAuthProvider = null;
      });

      if (!context.mounted) return;

      await showOnboardingApiErrorModal(
        context,
        error,
        fallbackTitle:
            '${onboardingSocialProviderLabel(provider)} baglantisi kurulamadi',
        provider: provider,
      );
    }
  }

  void _openKvkk(BuildContext context) {
    Navigator.of(context).push(cupertinoRoute(const KvkkScreen()));
  }

  @override
  Widget build(BuildContext context) {
    return OnboardingWelcomeView(
      authLoading: _authLoading,
      activeAuthProvider: _activeAuthProvider,
      onGoogleTap: () => _goLogin(context, SocialAuthProvider.google),
      onAppleTap: () => _goLogin(context, SocialAuthProvider.apple),
      onTermsTap: () => _openKvkk(context),
    );
  }
}

// =============================================================================
// Screen 2 — Login Step 1 (Kisisel bilgiler)
// =============================================================================

class LoginStep1Screen extends ConsumerStatefulWidget {
  const LoginStep1Screen({super.key});

  @override
  ConsumerState<LoginStep1Screen> createState() => _LoginStep1ScreenState();
}

class _LoginStep1ScreenState extends ConsumerState<LoginStep1Screen> {
  OnboardingUsernameAvailabilityStatus _usernameStatus =
      OnboardingUsernameAvailabilityStatus.initial;

  void _handleUsernameStatusChanged(
    OnboardingUsernameAvailabilityStatus status,
  ) {
    if (_usernameStatus == status || !mounted) {
      return;
    }

    setState(() {
      _usernameStatus = status;
    });
  }

  @override
  Widget build(BuildContext context) {
    final data = ref.watch(onboardProvider);
    final notifier = ref.read(onboardProvider.notifier);

    void onContinue() {
      FocusScope.of(context).unfocus();
      Navigator.of(context).push(cupertinoRoute(const LoginStep2Screen()));
    }

    return OnboardingStep1View(
      data: data,
      onBack: () => Navigator.of(context).maybePop(),
      onContinue: onContinue,
      onNameChanged: notifier.setName,
      onSurnameChanged: notifier.setSurname,
      onUsernameChanged: notifier.setUsername,
      onUsernameStatusChanged: _handleUsernameStatusChanged,
      onBirthYearChanged: notifier.setBirthYear,
      usernameStatus: _usernameStatus,
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

    return OnboardingStep2View(
      selectedGender: gender,
      onBack: () => Navigator.of(context).maybePop(),
      onContinue: onContinue,
      onSelectFemale: () => notifier.setGender(Gender.female),
      onSelectMale: () => notifier.setGender(Gender.male),
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

  @override
  void dispose() {
    _authApi.close();
    super.dispose();
  }

  Future<void> _pickFrom(ImageSource source) async {
    if (_pickingPhoto || _submitting) return;
    final onboardNotifier = ref.read(onboardProvider.notifier);
    setState(() => _pickingPhoto = true);
    try {
      final file = await _picker.pickImage(
        source: source,
        imageQuality: 85,
        maxWidth: 1600,
      );
      if (!mounted) return;
      if (file != null) {
        onboardNotifier.setPhoto(file.path);
      }
    } catch (_) {
      // permission reddedilmis olabilir — sessizce gec
    } finally {
      if (mounted) setState(() => _pickingPhoto = false);
    }
  }

  void _openPickerSheet() {
    showOnboardingPhotoSourceSheet(
      context,
      onCameraTap: () => _pickFrom(ImageSource.camera),
      onGalleryTap: () => _pickFrom(ImageSource.gallery),
    );
  }

  Future<void> _finish({bool skipped = false}) async {
    if (_submitting) return;

    final notifier = ref.read(onboardProvider.notifier);
    final authNotifier = ref.read(appAuthProvider.notifier);
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
    });

    try {
      final session = await _authApi.completeSocialRegistration(data);
      await authNotifier.setAuthenticatedSession(session);
      notifier.clearSocialSession();
      if (!mounted) return;

      Navigator.of(context).pushAndRemoveUntil(
        cupertinoRoute(const HomeScreen(mode: HomeMode.list)),
        (route) => false,
      );
    } catch (error) {
      if (!mounted) return;
      await showOnboardingApiErrorModal(
        context,
        error,
        fallbackTitle: error is AppUpdateRequiredException
            ? 'Guncelleme gerekli'
            : 'Kayit tamamlanamadi',
      );
    } finally {
      if (mounted) {
        setState(() => _submitting = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final data = ref.watch(onboardProvider);

    return OnboardingStep3View(
      photoPath: data.photoPath,
      fallbackPhotoUrl: data.socialAvatarUrl,
      pickingPhoto: _pickingPhoto,
      submitting: _submitting,
      onBack: () => Navigator.of(context).maybePop(),
      onOpenPhotoPicker: _openPickerSheet,
      onContinue: () => _finish(),
      onSkip: () => _finish(skipped: true),
    );
  }
}

// =============================================================================
// Screen 5 — KVKK Aydinlatma Metni
// =============================================================================

class KvkkScreen extends StatelessWidget {
  const KvkkScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return OnboardingKvkkView(onBack: () => Navigator.of(context).maybePop());
  }
}
