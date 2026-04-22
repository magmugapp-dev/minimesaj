import 'package:magmug/app_core.dart';
import 'package:magmug/features/profile/profile_purchase_utils.dart';
import 'package:magmug/l10n/app_localizations.dart';

class ProfilePaywallAmbient extends StatelessWidget {
  const ProfilePaywallAmbient({super.key});

  @override
  Widget build(BuildContext context) {
    return IgnorePointer(
      child: Stack(
        children: [
          Positioned(
            left: -90,
            top: -10,
            child: _blob(260, const Color(0x44A855F7)),
          ),
          Positioned(
            right: -100,
            top: 110,
            child: _blob(230, const Color(0x33FF5FA2)),
          ),
          Positioned(
            left: -70,
            bottom: -70,
            child: _blob(260, const Color(0x227C6DF5)),
          ),
          Positioned(
            right: -50,
            bottom: 100,
            child: _blob(180, const Color(0x22F973B7)),
          ),
        ],
      ),
    );
  }

  Widget _blob(double size, Color color) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        gradient: RadialGradient(colors: [color, color.withValues(alpha: 0)]),
      ),
    );
  }
}

class ProfilePaywallHeader extends StatelessWidget {
  final VoidCallback onClose;

  const ProfilePaywallHeader({super.key, required this.onClose});

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;

    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 10, 16, 6),
      child: Row(
        children: [
          PressableScale(
            onTap: onClose,
            scale: 0.9,
            child: Container(
              width: 38,
              height: 38,
              decoration: BoxDecoration(
                color: const Color(0x14FFFFFF),
                shape: BoxShape.circle,
                border: Border.all(color: const Color(0x22FFFFFF)),
              ),
              alignment: Alignment.center,
              child: const Icon(
                CupertinoIcons.chevron_back,
                size: 18,
                color: AppColors.white,
              ),
            ),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Text(
              l10n.paywallHeaderTitle,
              style: const TextStyle(
                fontFamily: AppFont.family,
                fontWeight: FontWeight.w800,
                fontSize: 22,
                color: AppColors.white,
                letterSpacing: -0.5,
              ),
            ),
          ),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
            decoration: BoxDecoration(
              color: const Color(0x14FFFFFF),
              borderRadius: BorderRadius.circular(999),
              border: Border.all(color: const Color(0x22FFFFFF)),
            ),
            child: const Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(
                  CupertinoIcons.sparkles,
                  size: 14,
                  color: AppColors.indigo,
                ),
                SizedBox(width: 6),
                Text(
                  'magmug+',
                  style: TextStyle(
                    fontFamily: AppFont.family,
                    fontWeight: FontWeight.w800,
                    fontSize: 12,
                    color: AppColors.white,
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

class ProfilePaywallFeaturePill extends StatelessWidget {
  final IconData icon;
  final String label;

  const ProfilePaywallFeaturePill({
    super.key,
    required this.icon,
    required this.label,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      decoration: BoxDecoration(
        color: const Color(0x14FFFFFF),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: const Color(0x1FFFFFFF)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14, color: AppColors.white),
          const SizedBox(width: 6),
          Text(
            label,
            style: const TextStyle(
              fontFamily: AppFont.family,
              fontWeight: FontWeight.w700,
              fontSize: 12,
              color: AppColors.white,
            ),
          ),
        ],
      ),
    );
  }
}

class ProfilePlanCard extends StatelessWidget {
  final String title;
  final String priceMajor;
  final String priceMinor;
  final String currencyLabel;
  final String periodLabel;
  final String? badge;
  final bool featured;
  final bool selected;
  final VoidCallback onTap;

  const ProfilePlanCard({
    super.key,
    required this.title,
    required this.priceMajor,
    required this.priceMinor,
    required this.currencyLabel,
    required this.periodLabel,
    required this.onTap,
    required this.selected,
    this.badge,
    this.featured = false,
  });

  @override
  Widget build(BuildContext context) {
    final baseBorder = selected
        ? (featured ? const Color(0xFFFF89BE) : const Color(0xFF8B7BFF))
        : const Color(0x22FFFFFF);
    final background = selected
        ? const LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [Color(0xFF1A1A23), Color(0xFF15151C)],
          )
        : const LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [Color(0xFF121218), Color(0xFF0F0F14)],
          );

    return PressableScale(
      onTap: onTap,
      scale: 0.97,
      child: Container(
        width: double.infinity,
        padding: const EdgeInsets.fromLTRB(12, 12, 12, 12),
        decoration: BoxDecoration(
          gradient: background,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: baseBorder, width: selected ? 2 : 1),
          boxShadow: selected
              ? const [
                  BoxShadow(
                    color: Color(0x29000000),
                    blurRadius: 28,
                    offset: Offset(0, 14),
                  ),
                  BoxShadow(
                    color: Color(0x1FFF7BB0),
                    blurRadius: 14,
                    offset: Offset(0, 2),
                  ),
                ]
              : null,
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Text(
                    title,
                    maxLines: 2,
                    style: const TextStyle(
                      fontFamily: AppFont.family,
                      fontWeight: FontWeight.w800,
                      fontSize: 15,
                      height: 1.08,
                      color: AppColors.white,
                    ),
                  ),
                ),
                const SizedBox(width: 6),
                AnimatedContainer(
                  duration: const Duration(milliseconds: 180),
                  width: 20,
                  height: 20,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: selected
                        ? const Color(0xFFFF7BB0)
                        : const Color(0x00000000),
                    border: Border.all(
                      color: selected
                          ? const Color(0xFFFF7BB0)
                          : const Color(0x55FFFFFF),
                    ),
                  ),
                  child: selected
                      ? const Icon(
                          CupertinoIcons.check_mark,
                          size: 12,
                          color: AppColors.white,
                        )
                      : null,
                ),
              ],
            ),
            const SizedBox(height: 6),
            SizedBox(
              height: 18,
              child: badge != null
                  ? Align(
                      alignment: Alignment.centerLeft,
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 6,
                          vertical: 3,
                        ),
                        decoration: BoxDecoration(
                          gradient: featured
                              ? const LinearGradient(
                                  begin: Alignment.topLeft,
                                  end: Alignment.bottomRight,
                                  colors: [
                                    Color(0xFF8B7BFF),
                                    Color(0xFFFF7BB0),
                                  ],
                                )
                              : null,
                          color: featured ? null : const Color(0x18FFFFFF),
                          borderRadius: BorderRadius.circular(999),
                        ),
                        child: Text(
                          badge!,
                          style: const TextStyle(
                            fontFamily: AppFont.family,
                            fontWeight: FontWeight.w800,
                            fontSize: 8.5,
                            color: AppColors.white,
                            letterSpacing: 0.2,
                          ),
                        ),
                      ),
                    )
                  : null,
            ),
            const SizedBox(height: 8),
            Text(
              periodLabel,
              style: const TextStyle(
                fontFamily: AppFont.family,
                fontWeight: FontWeight.w600,
                fontSize: 11.5,
                color: Color(0xFF9E9EAC),
              ),
            ),
            const SizedBox(height: 8),
            RichText(
              text: TextSpan(
                children: [
                  TextSpan(
                    text: '$currencyLabel ',
                    style: const TextStyle(
                      fontFamily: AppFont.family,
                      fontWeight: FontWeight.w700,
                      fontSize: 11,
                      color: AppColors.white,
                    ),
                  ),
                  TextSpan(
                    text: priceMajor,
                    style: const TextStyle(
                      fontFamily: AppFont.family,
                      fontWeight: FontWeight.w800,
                      fontSize: 21,
                      height: 1,
                      color: AppColors.white,
                    ),
                  ),
                  TextSpan(
                    text: priceMinor,
                    style: const TextStyle(
                      fontFamily: AppFont.family,
                      fontWeight: FontWeight.w700,
                      fontSize: 11.5,
                      color: AppColors.white,
                    ),
                  ),
                ],
              ),
            ),
            const Spacer(),
          ],
        ),
      ),
    );
  }
}

class ProfilePaywallCta extends StatelessWidget {
  final String label;
  final VoidCallback? onTap;

  const ProfilePaywallCta({
    super.key,
    required this.label,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return PressableScale(
      onTap: onTap,
      child: Container(
        height: 58,
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(20),
          gradient: const LinearGradient(
            begin: Alignment.centerLeft,
            end: Alignment.centerRight,
            colors: [Color(0xFF8B7BFF), Color(0xFFFF7BB0)],
          ),
          boxShadow: const [
            BoxShadow(
              color: Color(0x44C8508D),
              blurRadius: 24,
              offset: Offset(0, 10),
            ),
          ],
        ),
        alignment: Alignment.center,
        child: Text(
          label,
          style: const TextStyle(
            fontFamily: AppFont.family,
            fontWeight: FontWeight.w800,
            fontSize: 15.5,
            color: AppColors.white,
          ),
        ),
      ),
    );
  }
}

class ProfilePaywallLegal extends StatelessWidget {
  const ProfilePaywallLegal({super.key});

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 24),
      child: Text.rich(
        TextSpan(
          style: const TextStyle(
            fontFamily: AppFont.family,
            fontSize: 11,
            color: Color(0xFF7E7E8D),
          ),
          children: [
            TextSpan(text: l10n.paywallLegalPrefix),
            TextSpan(
              text: l10n.privacyTitle,
              style: const TextStyle(
                color: AppColors.white,
                decoration: TextDecoration.underline,
              ),
            ),
            TextSpan(text: l10n.paywallLegalAnd),
            TextSpan(
              text: l10n.termsTitle,
              style: const TextStyle(
                color: AppColors.white,
                decoration: TextDecoration.underline,
              ),
            ),
          ],
        ),
        textAlign: TextAlign.center,
      ),
    );
  }
}

class ProfilePaywallInfoCard extends StatelessWidget {
  final String message;

  const ProfilePaywallInfoCard({super.key, required this.message});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: const Color(0x14FFFFFF),
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: const Color(0x26FFFFFF)),
      ),
      child: Text(
        message,
        textAlign: TextAlign.center,
        style: const TextStyle(
          fontFamily: AppFont.family,
          fontSize: 14,
          color: AppColors.white,
          height: 1.4,
        ),
      ),
    );
  }
}

class ProfilePaywallScreenView extends StatelessWidget {
  final VoidCallback onClose;
  final String heroBadge;
  final String heroTitle;
  final String heroSubtitle;
  final String voiceFeatureLabel;
  final String videoFeatureLabel;
  final String boostFeatureLabel;
  final String plansTitle;
  final bool isLoading;
  final bool hasError;
  final bool isEmpty;
  final String loadingErrorMessage;
  final String emptyMessage;
  final List<AppSubscriptionPackage> packages;
  final int selectedIndex;
  final ValueChanged<int> onSelectPackage;
  final String ctaLabel;
  final VoidCallback? onCtaTap;

  const ProfilePaywallScreenView({
    super.key,
    required this.onClose,
    required this.heroBadge,
    required this.heroTitle,
    required this.heroSubtitle,
    required this.voiceFeatureLabel,
    required this.videoFeatureLabel,
    required this.boostFeatureLabel,
    required this.plansTitle,
    required this.isLoading,
    required this.hasError,
    required this.isEmpty,
    required this.loadingErrorMessage,
    required this.emptyMessage,
    required this.packages,
    required this.selectedIndex,
    required this.onSelectPackage,
    required this.ctaLabel,
    required this.onCtaTap,
  });

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;

    return CupertinoPageScaffold(
      backgroundColor: const Color(0xFF09090D),
      child: Stack(
        children: [
          const Positioned.fill(child: ProfilePaywallAmbient()),
          SafeArea(
            child: Column(
              children: [
                ProfilePaywallHeader(onClose: onClose),
                Expanded(
                  child: SingleChildScrollView(
                    physics: const BouncingScrollPhysics(),
                    padding: const EdgeInsets.fromLTRB(16, 6, 16, 18),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Container(
                          width: double.infinity,
                          padding: const EdgeInsets.fromLTRB(20, 20, 20, 20),
                          decoration: BoxDecoration(
                            borderRadius: BorderRadius.circular(28),
                            gradient: const LinearGradient(
                              begin: Alignment.topLeft,
                              end: Alignment.bottomRight,
                              colors: [
                                Color(0xFF120F22),
                                Color(0xFF241333),
                                Color(0xFF4C173F),
                              ],
                            ),
                            boxShadow: const [
                              BoxShadow(
                                color: Color(0x40000000),
                                blurRadius: 36,
                                offset: Offset(0, 20),
                              ),
                              BoxShadow(
                                color: Color(0x26C43D84),
                                blurRadius: 18,
                                offset: Offset(0, 6),
                              ),
                            ],
                            border: Border.all(color: const Color(0x26FFFFFF)),
                          ),
                          child: Stack(
                            children: [
                              Positioned(
                                right: -14,
                                top: -18,
                                child: Container(
                                  width: 140,
                                  height: 140,
                                  decoration: const BoxDecoration(
                                    shape: BoxShape.circle,
                                    gradient: RadialGradient(
                                      colors: [
                                        Color(0x33FF8BC2),
                                        Color(0x00FF8BC2),
                                      ],
                                    ),
                                  ),
                                ),
                              ),
                              Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Row(
                                    children: [
                                      Container(
                                        padding: const EdgeInsets.symmetric(
                                          horizontal: 10,
                                          vertical: 5,
                                        ),
                                        decoration: BoxDecoration(
                                          color: const Color(0x1FFFFFFF),
                                          borderRadius: BorderRadius.circular(
                                            999,
                                          ),
                                          border: Border.all(
                                            color: const Color(0x26FFFFFF),
                                          ),
                                        ),
                                        child: Text(
                                          heroBadge,
                                          style: const TextStyle(
                                            fontFamily: AppFont.family,
                                            fontWeight: FontWeight.w800,
                                            fontSize: 10.5,
                                            color: AppColors.white,
                                            letterSpacing: 0.7,
                                          ),
                                        ),
                                      ),
                                      const Spacer(),
                                      Container(
                                        width: 58,
                                        height: 58,
                                        decoration: BoxDecoration(
                                          gradient: const LinearGradient(
                                            begin: Alignment.topLeft,
                                            end: Alignment.bottomRight,
                                            colors: [
                                              Color(0x33FFFFFF),
                                              Color(0x14FFFFFF),
                                            ],
                                          ),
                                          borderRadius: BorderRadius.circular(
                                            20,
                                          ),
                                          border: Border.all(
                                            color: const Color(0x22FFFFFF),
                                          ),
                                        ),
                                        alignment: Alignment.center,
                                        child: const Icon(
                                          CupertinoIcons.videocam_fill,
                                          size: 26,
                                          color: AppColors.white,
                                        ),
                                      ),
                                    ],
                                  ),
                                  const SizedBox(height: 16),
                                  Text(
                                    heroTitle,
                                    style: const TextStyle(
                                      fontFamily: AppFont.family,
                                      fontWeight: FontWeight.w800,
                                      fontSize: 30,
                                      height: 1.02,
                                      color: AppColors.white,
                                      letterSpacing: -1,
                                    ),
                                  ),
                                  const SizedBox(height: 9),
                                  SizedBox(
                                    width: 250,
                                    child: Text(
                                      heroSubtitle,
                                      style: const TextStyle(
                                        fontSize: 13,
                                        height: 1.42,
                                        color: Color(0xF2FFFFFF),
                                      ),
                                    ),
                                  ),
                                  const SizedBox(height: 16),
                                  Wrap(
                                    spacing: 7,
                                    runSpacing: 7,
                                    children: [
                                      ProfilePaywallFeaturePill(
                                        icon: CupertinoIcons.phone_fill,
                                        label: voiceFeatureLabel,
                                      ),
                                      ProfilePaywallFeaturePill(
                                        icon: CupertinoIcons.video_camera_solid,
                                        label: videoFeatureLabel,
                                      ),
                                      ProfilePaywallFeaturePill(
                                        icon: CupertinoIcons.sparkles,
                                        label: boostFeatureLabel,
                                      ),
                                    ],
                                  ),
                                ],
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 20),
                        Text(
                          plansTitle,
                          style: const TextStyle(
                            fontFamily: AppFont.family,
                            fontWeight: FontWeight.w800,
                            fontSize: 22,
                            color: AppColors.white,
                            letterSpacing: -0.5,
                          ),
                        ),
                        const SizedBox(height: 22),
                        if (isLoading)
                          const SizedBox(
                            height: 214,
                            child: Center(child: CupertinoActivityIndicator()),
                          )
                        else if (hasError)
                          ProfilePaywallInfoCard(message: loadingErrorMessage)
                        else if (isEmpty)
                          ProfilePaywallInfoCard(message: emptyMessage)
                        else
                          SizedBox(
                            height: 214,
                            child: SingleChildScrollView(
                              scrollDirection: Axis.horizontal,
                              physics: const BouncingScrollPhysics(),
                              child: Row(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  for (
                                    var index = 0;
                                    index < packages.length;
                                    index++
                                  ) ...[
                                    if (index > 0) const SizedBox(width: 10),
                                    Builder(
                                      builder: (context) {
                                        final package = packages[index];
                                        final priceParts = profilePriceParts(
                                          package.displayPrice,
                                        );
                                        return SizedBox(
                                          width: 132,
                                          height: 214,
                                          child: ProfilePlanCard(
                                            title: subscriptionPlanTitle(
                                              package,
                                              l10n,
                                            ),
                                            priceMajor: priceParts.$1,
                                            priceMinor: priceParts.$2,
                                            currencyLabel: package.currency,
                                            periodLabel:
                                                subscriptionPeriodTitle(
                                                  package,
                                                  l10n,
                                                ),
                                            badge: package.badgeLabel,
                                            featured: package.isRecommended,
                                            selected: selectedIndex == index,
                                            onTap: () => onSelectPackage(index),
                                          ),
                                        );
                                      },
                                    ),
                                  ],
                                ],
                              ),
                            ),
                          ),
                      ],
                    ),
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
                  child: ProfilePaywallCta(label: ctaLabel, onTap: onCtaTap),
                ),
                const ProfilePaywallLegal(),
                SizedBox(height: MediaQuery.paddingOf(context).bottom + 8),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
