import 'package:magmug/app_core.dart';
import 'package:magmug/features/onboarding/widgets/onboarding_photo_widgets.dart';

class OnboardingStep3View extends StatelessWidget {
  final String? photoPath;
  final String? fallbackPhotoUrl;
  final bool pickingPhoto;
  final bool submitting;
  final VoidCallback onBack;
  final VoidCallback onOpenPhotoPicker;
  final VoidCallback onContinue;
  final VoidCallback onSkip;

  const OnboardingStep3View({
    super.key,
    required this.photoPath,
    required this.fallbackPhotoUrl,
    required this.pickingPhoto,
    required this.submitting,
    required this.onBack,
    required this.onOpenPhotoPicker,
    required this.onContinue,
    required this.onSkip,
  });

  @override
  Widget build(BuildContext context) {
    return CupertinoPageScaffold(
      backgroundColor: AppColors.white,
      child: SafeArea(
        child: LayoutBuilder(
          builder: (context, constraints) {
            return SingleChildScrollView(
              physics: const BouncingScrollPhysics(),
              child: ConstrainedBox(
                constraints: BoxConstraints(minHeight: constraints.maxHeight),
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 24),
                  child: Column(
                    children: [
                      const SizedBox(height: 8),
                      const StepProgressBar(currentStep: 3),
                      const SizedBox(height: 20),
                      Align(
                        alignment: Alignment.centerLeft,
                        child: CircleBackButton(onTap: onBack),
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
                      OnboardingPhotoSlot(
                        photoPath: photoPath,
                        fallbackPhotoUrl: fallbackPhotoUrl,
                        busy: pickingPhoto,
                        onTap: submitting ? null : onOpenPhotoPicker,
                      ),
                      const SizedBox(height: 24),
                      GradientButton(
                        label: submitting ? 'Kaydediliyor...' : 'Devam Et',
                        onTap: submitting ? null : onContinue,
                      ),
                      const SizedBox(height: 12),
                      SecondaryButton(
                        label: 'Bunu Atla',
                        onTap: submitting ? null : onSkip,
                      ),
                      const SizedBox(height: 12),
                    ],
                  ),
                ),
              ),
            );
          },
        ),
      ),
    );
  }
}
