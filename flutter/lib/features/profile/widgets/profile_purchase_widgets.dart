import 'package:magmug/app_core.dart';
import 'package:magmug/features/profile/widgets/profile_settings_widgets.dart';
import 'package:magmug/l10n/app_localizations.dart';

class ProfileJetonPackCard extends StatelessWidget {
  static const Color _selectedColor = AppColors.indigo;

  final AppCreditPackage option;
  final bool selected;
  final VoidCallback onTap;

  const ProfileJetonPackCard({
    super.key,
    required this.option,
    required this.selected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;

    return PressableScale(
      onTap: onTap,
      scale: 0.97,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 180),
        padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 18),
        decoration: BoxDecoration(
          color: selected ? const Color(0xFFF3F7FF) : AppColors.white,
          borderRadius: BorderRadius.circular(22),
          border: Border.all(
            color: selected ? _selectedColor : const Color(0xFFF0F0F0),
            width: selected ? 2 : 1,
          ),
          boxShadow: const [
            BoxShadow(
              color: Color(0x05000000),
              blurRadius: 10,
              offset: Offset(0, 4),
            ),
          ],
        ),
        child: Stack(
          clipBehavior: Clip.none,
          children: [
            Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      RichText(
                        text: TextSpan(
                          style: const TextStyle(
                            fontFamily: AppFont.family,
                            color: AppColors.black,
                          ),
                          children: [
                            TextSpan(
                              text: '${option.credits}',
                              style: const TextStyle(
                                fontWeight: FontWeight.w800,
                                fontSize: 16,
                              ),
                            ),
                            const TextSpan(text: ' '),
                            TextSpan(
                              text: l10n.jetonCreditsUnit,
                              style: const TextStyle(
                                fontWeight: FontWeight.w700,
                                fontSize: 16,
                              ),
                            ),
                          ],
                        ),
                      ),
                      if (option.badgeLabel != null) ...[
                        const SizedBox(height: 4),
                        Text(
                          option.badgeLabel!,
                          style: const TextStyle(
                            fontFamily: AppFont.family,
                            fontSize: 13,
                            color: AppColors.neutral500,
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
                const SizedBox(width: 16),
                Text(
                  option.displayPrice,
                  style: TextStyle(
                    fontFamily: AppFont.family,
                    fontWeight: FontWeight.w800,
                    fontSize: 18,
                    color: selected ? _selectedColor : AppColors.black,
                  ),
                ),
              ],
            ),
            if (option.isRecommended)
              Positioned(
                top: -28,
                left: 0,
                right: 0,
                child: Center(
                  child: Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 14,
                      vertical: 5,
                    ),
                    decoration: BoxDecoration(
                      color: _selectedColor,
                      borderRadius: BorderRadius.circular(999),
                    ),
                    child: Text(
                      l10n.jetonMostPopular,
                      style: const TextStyle(
                        fontFamily: AppFont.family,
                        fontWeight: FontWeight.w800,
                        fontSize: 11,
                        color: AppColors.white,
                      ),
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class ProfileSheetInfoCard extends StatelessWidget {
  final String message;

  const ProfileSheetInfoCard({super.key, required this.message});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: const Color(0xFFF7F7FB),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: const Color(0xFFE6E7EC)),
      ),
      child: Text(
        message,
        textAlign: TextAlign.center,
        style: const TextStyle(
          fontFamily: AppFont.family,
          fontSize: 14,
          color: AppColors.neutral600,
          height: 1.4,
        ),
      ),
    );
  }
}

class ProfileRestorePurchaseStep extends StatelessWidget {
  final String index;
  final String title;
  final String description;

  const ProfileRestorePurchaseStep({
    super.key,
    required this.index,
    required this.title,
    required this.description,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.grayField,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 28,
            height: 28,
            decoration: const BoxDecoration(
              color: AppColors.black,
              shape: BoxShape.circle,
            ),
            alignment: Alignment.center,
            child: Text(
              index,
              style: const TextStyle(
                fontFamily: AppFont.family,
                fontWeight: FontWeight.w800,
                fontSize: 12,
                color: AppColors.white,
              ),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    fontFamily: AppFont.family,
                    fontWeight: FontWeight.w700,
                    fontSize: 13.5,
                    color: AppColors.black,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  description,
                  style: const TextStyle(
                    fontFamily: AppFont.family,
                    fontSize: 12,
                    height: 1.45,
                    color: AppColors.neutral600,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class ProfileRestorePurchasesSheetView extends StatelessWidget {
  final String title;
  final String subtitle;
  final String stepOneTitle;
  final String stepOneDescription;
  final String stepTwoTitle;
  final String stepTwoDescription;
  final String stepThreeTitle;
  final String stepThreeDescription;
  final String? notice;
  final bool success;
  final String primaryActionLabel;
  final VoidCallback? onPrimaryAction;
  final String closeLabel;
  final VoidCallback onClose;

  const ProfileRestorePurchasesSheetView({
    super.key,
    required this.title,
    required this.subtitle,
    required this.stepOneTitle,
    required this.stepOneDescription,
    required this.stepTwoTitle,
    required this.stepTwoDescription,
    required this.stepThreeTitle,
    required this.stepThreeDescription,
    required this.notice,
    required this.success,
    required this.primaryActionLabel,
    required this.onPrimaryAction,
    required this.closeLabel,
    required this.onClose,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const ProfileSheetHandle(),
          const SizedBox(height: 18),
          Text(
            title,
            style: const TextStyle(
              fontFamily: AppFont.family,
              fontWeight: FontWeight.w800,
              fontSize: 20,
              color: AppColors.black,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            subtitle,
            style: const TextStyle(
              fontFamily: AppFont.family,
              fontSize: 12.5,
              color: AppColors.neutral600,
            ),
          ),
          const SizedBox(height: 16),
          ProfileRestorePurchaseStep(
            index: '1',
            title: stepOneTitle,
            description: stepOneDescription,
          ),
          const SizedBox(height: 10),
          ProfileRestorePurchaseStep(
            index: '2',
            title: stepTwoTitle,
            description: stepTwoDescription,
          ),
          const SizedBox(height: 10),
          ProfileRestorePurchaseStep(
            index: '3',
            title: stepThreeTitle,
            description: stepThreeDescription,
          ),
          if (notice != null) ...[
            const SizedBox(height: 16),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: success
                    ? const Color(0xFFEFFBF5)
                    : const Color(0xFFF7F7FB),
                borderRadius: BorderRadius.circular(14),
                border: Border.all(
                  color: success
                      ? const Color(0xFFB7E8CA)
                      : const Color(0x00000000),
                ),
              ),
              child: Text(
                notice!,
                style: TextStyle(
                  fontFamily: AppFont.family,
                  fontWeight: success ? FontWeight.w700 : FontWeight.w500,
                  fontSize: 12.5,
                  height: 1.45,
                  color: success
                      ? const Color(0xFF18794E)
                      : AppColors.neutral600,
                ),
              ),
            ),
          ],
          const SizedBox(height: 18),
          GradientButton(label: primaryActionLabel, onTap: onPrimaryAction),
          const SizedBox(height: 8),
          SecondaryButton(label: closeLabel, onTap: onClose),
        ],
      ),
    );
  }
}

class ProfileJetonPurchaseSheetView extends StatelessWidget {
  final String title;
  final String subtitle;
  final AsyncValue<List<AppCreditPackage>> packagesAsync;
  final List<AppCreditPackage> packages;
  final int selectedIndex;
  final ValueChanged<int> onSelectPackage;
  final String loadingErrorMessage;
  final String emptyMessage;
  final String primaryActionLabel;
  final VoidCallback? onPrimaryAction;
  final String infoText;

  const ProfileJetonPurchaseSheetView({
    super.key,
    required this.title,
    required this.subtitle,
    required this.packagesAsync,
    required this.packages,
    required this.selectedIndex,
    required this.onSelectPackage,
    required this.loadingErrorMessage,
    required this.emptyMessage,
    required this.primaryActionLabel,
    required this.onPrimaryAction,
    required this.infoText,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(32)),
      ),
      child: SafeArea(
        top: false,
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 16),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const ProfileSheetHandle(),
              const SizedBox(height: 20),
              Align(
                alignment: Alignment.centerLeft,
                child: Text(
                  title,
                  style: const TextStyle(
                    fontFamily: AppFont.family,
                    fontWeight: FontWeight.w800,
                    fontSize: 24,
                    color: AppColors.black,
                  ),
                ),
              ),
              const SizedBox(height: 6),
              Align(
                alignment: Alignment.centerLeft,
                child: Text(
                  subtitle,
                  style: const TextStyle(
                    fontFamily: AppFont.family,
                    fontSize: 14,
                    color: AppColors.neutral600,
                  ),
                ),
              ),
              const SizedBox(height: 20),
              if (packagesAsync.isLoading && packages.isEmpty)
                const Padding(
                  padding: EdgeInsets.symmetric(vertical: 32),
                  child: Center(child: CupertinoActivityIndicator()),
                )
              else if (packagesAsync.hasError && packages.isEmpty)
                ProfileSheetInfoCard(message: loadingErrorMessage)
              else if (packages.isEmpty)
                ProfileSheetInfoCard(message: emptyMessage)
              else
                ...List.generate(packages.length, (index) {
                  final option = packages[index];
                  return Padding(
                    padding: EdgeInsets.only(
                      top: option.isRecommended ? 16 : 0,
                      bottom: index == packages.length - 1 ? 0 : 12,
                    ),
                    child: ProfileJetonPackCard(
                      option: option,
                      selected: selectedIndex == index,
                      onTap: () => onSelectPackage(index),
                    ),
                  );
                }),
              const SizedBox(height: 20),
              GradientButton(label: primaryActionLabel, onTap: onPrimaryAction),
              const SizedBox(height: 10),
              Text(
                infoText,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  fontFamily: AppFont.family,
                  fontSize: 12,
                  color: AppColors.neutral600,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
