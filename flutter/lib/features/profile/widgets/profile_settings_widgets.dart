import 'package:magmug/app_core.dart';

class ProfileSheetHandle extends StatelessWidget {
  const ProfileSheetHandle({super.key});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Container(
        width: 48,
        height: 4,
        decoration: BoxDecoration(
          color: const Color(0xFFD4D4D4),
          borderRadius: BorderRadius.circular(8),
        ),
      ),
    );
  }
}

class ProfileSettingsTile extends StatelessWidget {
  final IconData icon;
  final String label;
  final String? trailingText;
  final String? badgeCount;
  final bool danger;
  final bool showDivider;
  final Color? accentColor;
  final Color? iconBackgroundColor;
  final VoidCallback onTap;

  const ProfileSettingsTile({
    super.key,
    required this.icon,
    required this.label,
    required this.onTap,
    this.trailingText,
    this.badgeCount,
    this.danger = false,
    this.showDivider = false,
    this.accentColor,
    this.iconBackgroundColor,
  });

  @override
  Widget build(BuildContext context) {
    final color =
        accentColor ?? (danger ? const Color(0xFFEF4444) : AppColors.black);
    final bg =
        iconBackgroundColor ??
        (danger ? const Color(0xFFFEF2F2) : AppColors.grayField);

    return Column(
      children: [
        PressableScale(
          onTap: onTap,
          scale: 0.99,
          child: Container(
            height: 62,
            padding: const EdgeInsets.symmetric(horizontal: 18),
            child: Row(
              children: [
                Container(
                  width: 34,
                  height: 34,
                  decoration: BoxDecoration(
                    color: bg,
                    borderRadius: BorderRadius.circular(10),
                  ),
                  alignment: Alignment.center,
                  child: Icon(icon, size: 16, color: color),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    label,
                    style: TextStyle(
                      fontFamily: AppFont.family,
                      fontWeight: FontWeight.w600,
                      fontSize: 14,
                      color: color,
                    ),
                  ),
                ),
                if (trailingText != null)
                  Text(
                    trailingText!,
                    style: const TextStyle(
                      fontFamily: AppFont.family,
                      fontWeight: FontWeight.w600,
                      fontSize: 12,
                      color: Color(0xFF999999),
                    ),
                  ),
                if (badgeCount != null) ...[
                  const SizedBox(width: 8),
                  Text(
                    badgeCount!,
                    style: const TextStyle(
                      fontFamily: AppFont.family,
                      fontWeight: FontWeight.w600,
                      fontSize: 12,
                      color: Color(0xFF999999),
                    ),
                  ),
                ],
                if (!danger) ...[
                  const SizedBox(width: 10),
                  const Icon(
                    CupertinoIcons.chevron_right,
                    size: 14,
                    color: Color(0xFFBBBBBB),
                  ),
                ],
              ],
            ),
          ),
        ),
        if (showDivider)
          Container(
            margin: const EdgeInsets.symmetric(horizontal: 18),
            height: 1,
            color: const Color(0xFFF0F0F0),
          ),
      ],
    );
  }
}

class ProfileSettingsGroup extends StatelessWidget {
  final List<Widget> children;

  const ProfileSettingsGroup({super.key, required this.children});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(children: children),
    );
  }
}
