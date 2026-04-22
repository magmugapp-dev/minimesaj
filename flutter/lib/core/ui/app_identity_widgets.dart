import 'package:flutter/cupertino.dart';
import 'package:magmug/core/theme/app_colors.dart';
import 'package:magmug/core/theme/app_theme_tokens.dart';

String formatGem(int value) {
  final text = value.toString();
  final buffer = StringBuffer();
  for (var index = 0; index < text.length; index++) {
    if (index > 0 && (text.length - index) % 3 == 0) {
      buffer.write('.');
    }
    buffer.write(text[index]);
  }
  return buffer.toString();
}

class BalanceChip extends StatelessWidget {
  final int amount;

  const BalanceChip({super.key, required this.amount});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(20),
        boxShadow: const [
          BoxShadow(
            color: Color(0x14000000),
            blurRadius: 8,
            offset: Offset(0, 2),
          ),
        ],
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Image.asset('assets/images/icon_diamond.png', width: 16, height: 16),
          const SizedBox(width: 8),
          Text(
            formatGem(amount),
            style: const TextStyle(
              fontFamily: AppFont.family,
              fontWeight: FontWeight.w800,
              fontSize: 13,
              color: AppColors.zinc900,
            ),
          ),
        ],
      ),
    );
  }
}

class AvatarCircle extends StatelessWidget {
  final String name;
  final double size;
  final bool online;

  const AvatarCircle({
    super.key,
    required this.name,
    this.size = 52,
    this.online = false,
  });

  @override
  Widget build(BuildContext context) {
    final base = avatarColorForName(name);
    return SizedBox(
      width: size,
      height: size,
      child: Stack(
        children: [
          Container(
            width: size,
            height: size,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              gradient: LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: [base.withValues(alpha: 0.65), base],
              ),
            ),
            alignment: Alignment.center,
            child: Text(
              initialsOf(name),
              style: TextStyle(
                fontFamily: AppFont.family,
                fontWeight: FontWeight.w700,
                fontSize: size * 0.36,
                color: AppColors.white,
                letterSpacing: 0.2,
              ),
            ),
          ),
          if (online)
            Positioned(
              right: 1,
              bottom: 1,
              child: Container(
                width: 12,
                height: 12,
                decoration: BoxDecoration(
                  color: AppColors.onlineGreen,
                  shape: BoxShape.circle,
                  border: Border.all(color: AppColors.neutral100, width: 2),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

Color avatarColorForName(String name) {
  const palette = [
    Color(0xFFA594F9),
    Color(0xFFFFB4C6),
    Color(0xFFFDB384),
    Color(0xFFFF9794),
    Color(0xFFAEDFF7),
    Color(0xFFB6E0B8),
    Color(0xFFFFE4A5),
    Color(0xFFC4C9FF),
    Color(0xFF9AA2B1),
  ];

  var hash = 0;
  for (final rune in name.runes) {
    hash = (hash * 31 + rune) & 0x7fffffff;
  }
  return palette[hash % palette.length];
}

String initialsOf(String fullName) {
  final parts = fullName
      .trim()
      .split(RegExp(r'\s+'))
      .where((part) => part.isNotEmpty)
      .toList();
  if (parts.isEmpty) return '?';
  if (parts.length == 1) {
    final value = parts.first;
    return value.substring(0, value.length >= 2 ? 2 : 1).toUpperCase();
  }
  return (parts.first.substring(0, 1) + parts.last.substring(0, 1))
      .toUpperCase();
}
