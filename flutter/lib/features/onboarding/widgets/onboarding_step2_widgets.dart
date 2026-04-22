import 'package:magmug/app_core.dart';

class OnboardingStep2View extends StatelessWidget {
  final Gender? selectedGender;
  final VoidCallback onBack;
  final VoidCallback onContinue;
  final VoidCallback onSelectFemale;
  final VoidCallback onSelectMale;

  const OnboardingStep2View({
    super.key,
    required this.selectedGender,
    required this.onBack,
    required this.onContinue,
    required this.onSelectFemale,
    required this.onSelectMale,
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
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const SizedBox(height: 8),
                      const StepProgressBar(currentStep: 2),
                      const SizedBox(height: 20),
                      Align(
                        alignment: Alignment.centerLeft,
                        child: CircleBackButton(onTap: onBack),
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
                        selected: selectedGender == Gender.female,
                        onTap: onSelectFemale,
                      ),
                      const SizedBox(height: 12),
                      GenderOption(
                        label: 'Erkek',
                        iconAsset: 'assets/images/icon_male.png',
                        iconBackground: const Color(0x145C6BFF),
                        selected: selectedGender == Gender.male,
                        onTap: onSelectMale,
                      ),
                      const SizedBox(height: 24),
                      GradientButton(
                        label: 'Devam Et',
                        onTap: selectedGender == null ? null : onContinue,
                      ),
                      const SizedBox(height: 16),
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
