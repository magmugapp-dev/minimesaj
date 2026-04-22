import 'package:magmug/app_core.dart';
import 'package:magmug/l10n/app_localizations.dart';

import 'package:magmug/features/profile/widgets/profile_settings_widgets.dart';

class ProfileAdaptiveBottomSheet extends StatelessWidget {
  final Widget child;
  final double maxHeightFactor;
  final bool scrollable;

  const ProfileAdaptiveBottomSheet({
    super.key,
    required this.child,
    this.maxHeightFactor = 0.86,
    this.scrollable = true,
  });

  @override
  Widget build(BuildContext context) {
    final viewInsets = MediaQuery.viewInsetsOf(context);
    final safeBottom = MediaQuery.paddingOf(context).bottom;
    final screenHeight = MediaQuery.sizeOf(context).height;
    final resolvedPadding = const EdgeInsets.fromLTRB(
      20,
      12,
      20,
      20,
    ).copyWith(bottom: 20 + safeBottom);
    final maxHeight = screenHeight * maxHeightFactor;

    return Align(
      alignment: Alignment.bottomCenter,
      child: AnimatedPadding(
        duration: const Duration(milliseconds: 220),
        curve: Curves.easeOut,
        padding: EdgeInsets.only(bottom: viewInsets.bottom),
        child: Container(
          width: double.infinity,
          decoration: const BoxDecoration(
            color: AppColors.white,
            borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
          ),
          child: ConstrainedBox(
            constraints: BoxConstraints(maxHeight: maxHeight),
            child: scrollable
                ? SingleChildScrollView(
                    physics: const BouncingScrollPhysics(),
                    padding: resolvedPadding,
                    child: child,
                  )
                : Padding(padding: resolvedPadding, child: child),
          ),
        ),
      ),
    );
  }
}

class ProfileConfirmSheet extends StatelessWidget {
  final String title;
  final String subtitle;
  final String confirmLabel;
  final bool destructive;
  final Color? confirmColor;
  final Future<void> Function()? onConfirm;

  const ProfileConfirmSheet({
    super.key,
    required this.title,
    required this.subtitle,
    required this.confirmLabel,
    this.destructive = false,
    this.confirmColor,
    this.onConfirm,
  });

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final confirmBg =
        confirmColor ?? (destructive ? const Color(0xFFEF4444) : null);
    final confirmGradient = confirmBg == null ? AppColors.primary : null;

    return Align(
      alignment: Alignment.bottomCenter,
      child: Container(
        width: double.infinity,
        decoration: const BoxDecoration(
          color: AppColors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
        ),
        child: SafeArea(
          top: false,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(24, 12, 24, 24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const ProfileSheetHandle(),
                const SizedBox(height: 24),
                Text(
                  title,
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    fontFamily: AppFont.family,
                    fontWeight: FontWeight.w800,
                    fontSize: 17,
                    color: AppColors.black,
                    height: 1.3,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  subtitle,
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    fontFamily: AppFont.family,
                    fontSize: 13.5,
                    height: 1.5,
                    color: Color(0xFF666666),
                  ),
                ),
                const SizedBox(height: 20),
                PressableScale(
                  onTap: () async {
                    if (onConfirm != null) {
                      await onConfirm!.call();
                      return;
                    }
                    if (context.mounted) {
                      Navigator.of(context).maybePop();
                    }
                  },
                  child: Container(
                    height: 52,
                    width: double.infinity,
                    decoration: BoxDecoration(
                      color: confirmBg,
                      gradient: confirmGradient,
                      borderRadius: BorderRadius.circular(26),
                    ),
                    alignment: Alignment.center,
                    child: Text(
                      confirmLabel,
                      style: const TextStyle(
                        fontFamily: AppFont.family,
                        fontWeight: FontWeight.w700,
                        fontSize: 15,
                        color: AppColors.white,
                      ),
                    ),
                  ),
                ),
                const SizedBox(height: 8),
                PressableScale(
                  onTap: () => Navigator.of(context).maybePop(),
                  scale: 0.99,
                  child: Container(
                    height: 52,
                    width: double.infinity,
                    decoration: BoxDecoration(
                      color: AppColors.grayField,
                      borderRadius: BorderRadius.circular(26),
                    ),
                    alignment: Alignment.center,
                    child: Text(
                      l10n.commonCancel,
                      style: const TextStyle(
                        fontFamily: AppFont.family,
                        fontWeight: FontWeight.w600,
                        fontSize: 15,
                        color: AppColors.black,
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
