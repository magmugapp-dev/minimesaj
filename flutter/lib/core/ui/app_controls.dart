import 'package:flutter/cupertino.dart';
import 'package:magmug/core/models/auth_models.dart';
import 'package:magmug/core/theme/app_colors.dart';
import 'package:magmug/core/theme/app_theme_tokens.dart';

class PressableScale extends StatefulWidget {
  final Widget child;
  final VoidCallback? onTap;
  final double scale;

  const PressableScale({
    super.key,
    required this.child,
    this.onTap,
    this.scale = 0.97,
  });

  @override
  State<PressableScale> createState() => _PressableScaleState();
}

class _PressableScaleState extends State<PressableScale> {
  bool _pressed = false;

  void _set(bool value) {
    if (!mounted) return;
    setState(() => _pressed = value);
  }

  @override
  Widget build(BuildContext context) {
    final enabled = widget.onTap != null;
    return GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTapDown: enabled ? (_) => _set(true) : null,
      onTapCancel: enabled ? () => _set(false) : null,
      onTapUp: enabled ? (_) => _set(false) : null,
      onTap: widget.onTap,
      child: AnimatedScale(
        scale: _pressed ? widget.scale : 1.0,
        duration: const Duration(milliseconds: 120),
        curve: Curves.easeOut,
        child: AnimatedOpacity(
          duration: const Duration(milliseconds: 120),
          opacity: enabled ? 1.0 : 0.55,
          child: widget.child,
        ),
      ),
    );
  }
}

class GradientButton extends StatelessWidget {
  final String label;
  final VoidCallback? onTap;

  const GradientButton({super.key, required this.label, this.onTap});

  @override
  Widget build(BuildContext context) {
    return PressableScale(
      onTap: onTap,
      child: Container(
        height: 55,
        decoration: BoxDecoration(
          gradient: AppColors.primary,
          borderRadius: BorderRadius.circular(AppRadius.pill),
          boxShadow: const [
            BoxShadow(
              color: AppColors.shadow,
              blurRadius: 24,
              offset: Offset(0, 8),
            ),
          ],
        ),
        alignment: Alignment.center,
        child: Text(
          label,
          style: const TextStyle(
            fontFamily: AppFont.family,
            fontWeight: FontWeight.w800,
            fontSize: 16,
            color: AppColors.white,
          ),
        ),
      ),
    );
  }
}

class SecondaryButton extends StatelessWidget {
  final String label;
  final VoidCallback? onTap;

  const SecondaryButton({super.key, required this.label, this.onTap});

  @override
  Widget build(BuildContext context) {
    return PressableScale(
      onTap: onTap,
      child: Container(
        height: 55,
        decoration: BoxDecoration(
          color: AppColors.grayField,
          borderRadius: BorderRadius.circular(AppRadius.pill),
        ),
        alignment: Alignment.center,
        child: Text(
          label,
          style: const TextStyle(
            fontFamily: AppFont.family,
            fontWeight: FontWeight.w600,
            fontSize: 16,
            color: AppColors.black,
          ),
        ),
      ),
    );
  }
}

class AuthButton extends StatelessWidget {
  final String label;
  final String iconAsset;
  final Color background;
  final Color labelColor;
  final VoidCallback? onTap;
  final bool elevated;
  final bool enabled;
  final bool loading;

  const AuthButton({
    super.key,
    required this.label,
    required this.iconAsset,
    required this.background,
    required this.labelColor,
    this.onTap,
    this.elevated = false,
    this.enabled = true,
    this.loading = false,
  });

  @override
  Widget build(BuildContext context) {
    final canTap = enabled && !loading && onTap != null;

    return PressableScale(
      onTap: canTap ? onTap : null,
      child: Container(
        height: 52,
        decoration: BoxDecoration(
          color: background,
          borderRadius: BorderRadius.circular(AppRadius.pill),
          border: background == AppColors.white
              ? Border.all(color: const Color(0xFFF0F0F0))
              : null,
          boxShadow: elevated
              ? const [
                  BoxShadow(
                    color: AppColors.shadow,
                    blurRadius: 20,
                    offset: Offset(0, 4),
                  ),
                ]
              : null,
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            if (loading)
              const SizedBox(
                width: 20,
                height: 20,
                child: CupertinoActivityIndicator(radius: 10),
              )
            else
              Image.asset(iconAsset, width: 20, height: 20),
            const SizedBox(width: 12),
            Text(
              label,
              style: TextStyle(
                fontFamily: AppFont.family,
                fontWeight: FontWeight.w600,
                fontSize: 15,
                color: labelColor,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class AuthNoticeCard extends StatelessWidget {
  final AuthNoticeData data;
  final VoidCallback onDismiss;
  final VoidCallback? onRetry;

  const AuthNoticeCard({
    super.key,
    required this.data,
    required this.onDismiss,
    this.onRetry,
  });

  @override
  Widget build(BuildContext context) {
    final palette = switch (data.tone) {
      AuthNoticeTone.info => (
        background: const Color(0xFFF2F7FF),
        border: const Color(0xFFD6E7FF),
        icon: CupertinoIcons.info_circle_fill,
        iconColor: AppColors.brandBlue,
      ),
      AuthNoticeTone.success => (
        background: const Color(0xFFF1FBF5),
        border: const Color(0xFFD1F0DB),
        icon: CupertinoIcons.check_mark_circled_solid,
        iconColor: AppColors.onlineGreen,
      ),
      AuthNoticeTone.error => (
        background: const Color(0xFFFFF4F4),
        border: const Color(0xFFF4D3D3),
        icon: CupertinoIcons.exclamationmark_circle_fill,
        iconColor: AppColors.coral,
      ),
    };

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: palette.background,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: palette.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(palette.icon, size: 20, color: palette.iconColor),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      data.title,
                      style: const TextStyle(
                        fontFamily: AppFont.family,
                        fontWeight: FontWeight.w700,
                        fontSize: 14,
                        color: AppColors.black,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      data.message,
                      style: const TextStyle(
                        fontFamily: AppFont.family,
                        fontSize: 12.5,
                        height: 1.45,
                        color: AppColors.neutral600,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              PressableScale(
                onTap: onDismiss,
                scale: 0.92,
                child: const Icon(
                  CupertinoIcons.xmark,
                  size: 16,
                  color: AppColors.neutral500,
                ),
              ),
            ],
          ),
          if (onRetry != null) ...[
            const SizedBox(height: 12),
            PressableScale(
              onTap: onRetry,
              child: Container(
                height: 38,
                padding: const EdgeInsets.symmetric(horizontal: 14),
                decoration: BoxDecoration(
                  color: AppColors.white,
                  borderRadius: BorderRadius.circular(AppRadius.pill),
                  border: Border.all(color: palette.border),
                ),
                alignment: Alignment.center,
                child: const Text(
                  'Tekrar dene',
                  style: TextStyle(
                    fontFamily: AppFont.family,
                    fontWeight: FontWeight.w700,
                    fontSize: 13,
                    color: AppColors.black,
                  ),
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class StepProgressBar extends StatelessWidget {
  final int currentStep;

  const StepProgressBar({super.key, required this.currentStep});

  static const int _totalSteps = 4;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: List.generate(_totalSteps, (index) {
        final active = index < currentStep;
        return Expanded(
          child: Padding(
            padding: EdgeInsets.only(right: index == _totalSteps - 1 ? 0 : 6),
            child: _StepBarSegment(active: active, delay: index * 70),
          ),
        );
      }),
    );
  }
}

class _StepBarSegment extends StatelessWidget {
  final bool active;
  final int delay;

  const _StepBarSegment({required this.active, required this.delay});

  @override
  Widget build(BuildContext context) {
    return TweenAnimationBuilder<double>(
      tween: Tween(begin: 0, end: active ? 1 : 0),
      duration: Duration(milliseconds: 450 + delay),
      curve: Curves.easeOutCubic,
      builder: (context, value, _) {
        return Stack(
          children: [
            Container(
              height: 4,
              decoration: BoxDecoration(
                color: AppColors.grayProgress,
                borderRadius: BorderRadius.circular(2),
              ),
            ),
            FractionallySizedBox(
              widthFactor: value,
              child: Container(
                height: 4,
                decoration: BoxDecoration(
                  color: AppColors.indigo,
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
            ),
          ],
        );
      },
    );
  }
}

class CircleBackButton extends StatelessWidget {
  final VoidCallback? onTap;
  final bool filled;

  const CircleBackButton({super.key, this.onTap, this.filled = false});

  @override
  Widget build(BuildContext context) {
    final bg = filled ? AppColors.grayField : AppColors.white;
    return PressableScale(
      onTap: onTap ?? () => Navigator.of(context).maybePop(),
      child: Container(
        width: 40,
        height: 40,
        decoration: BoxDecoration(
          color: bg,
          shape: BoxShape.circle,
          boxShadow: filled
              ? null
              : const [
                  BoxShadow(
                    color: Color(0x0F000000),
                    blurRadius: 8,
                    offset: Offset(0, 2),
                  ),
                ],
        ),
        alignment: Alignment.center,
        child: const Icon(
          CupertinoIcons.chevron_back,
          size: 20,
          color: AppColors.black,
        ),
      ),
    );
  }
}

class LabeledField extends StatefulWidget {
  final String label;
  final String? initialValue;
  final String? placeholder;
  final void Function(String) onChanged;
  final TextCapitalization capitalization;

  const LabeledField({
    super.key,
    required this.label,
    required this.onChanged,
    this.initialValue,
    this.placeholder,
    this.capitalization = TextCapitalization.words,
  });

  @override
  State<LabeledField> createState() => _LabeledFieldState();
}

class _LabeledFieldState extends State<LabeledField> {
  late final TextEditingController _controller;
  final FocusNode _focus = FocusNode();
  bool _focused = false;

  @override
  void initState() {
    super.initState();
    _controller = TextEditingController(text: widget.initialValue ?? '');
    _focus.addListener(() {
      if (!mounted) return;
      setState(() => _focused = _focus.hasFocus);
    });
  }

  @override
  void dispose() {
    _controller.dispose();
    _focus.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          widget.label,
          style: const TextStyle(
            fontFamily: AppFont.family,
            fontWeight: FontWeight.w600,
            fontSize: 12,
            letterSpacing: 1.0,
            color: AppColors.gray,
          ),
        ),
        const SizedBox(height: 8),
        AnimatedContainer(
          duration: const Duration(milliseconds: 200),
          curve: Curves.easeOut,
          height: 54,
          decoration: BoxDecoration(
            color: AppColors.grayField,
            borderRadius: BorderRadius.circular(AppRadius.field),
            border: Border.all(
              color: _focused ? AppColors.indigo : const Color(0x00000000),
              width: 1.6,
            ),
          ),
          child: CupertinoTextField(
            controller: _controller,
            focusNode: _focus,
            onChanged: widget.onChanged,
            textCapitalization: widget.capitalization,
            placeholder: widget.placeholder,
            placeholderStyle: const TextStyle(
              fontFamily: AppFont.family,
              color: AppColors.gray,
              fontSize: 16,
              fontWeight: FontWeight.w500,
            ),
            style: const TextStyle(
              fontFamily: AppFont.family,
              color: AppColors.black,
              fontSize: 16,
              fontWeight: FontWeight.w600,
            ),
            cursorColor: AppColors.indigo,
            decoration: const BoxDecoration(color: Color(0x00000000)),
            padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 16),
          ),
        ),
      ],
    );
  }
}

class GenderOption extends StatelessWidget {
  final String label;
  final String iconAsset;
  final Color iconBackground;
  final bool selected;
  final VoidCallback onTap;

  const GenderOption({
    super.key,
    required this.label,
    required this.iconAsset,
    required this.iconBackground,
    required this.selected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return PressableScale(
      onTap: onTap,
      scale: 0.98,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        curve: Curves.easeOut,
        height: 88,
        decoration: BoxDecoration(
          color: AppColors.grayField,
          borderRadius: BorderRadius.circular(AppRadius.card),
          border: Border.all(
            color: selected ? AppColors.indigo : const Color(0x00000000),
            width: 2,
          ),
        ),
        padding: const EdgeInsets.symmetric(horizontal: 20),
        child: Row(
          children: [
            Container(
              width: 48,
              height: 48,
              decoration: BoxDecoration(
                color: iconBackground,
                borderRadius: BorderRadius.circular(14),
              ),
              alignment: Alignment.center,
              child: Image.asset(iconAsset, width: 24, height: 24),
            ),
            const SizedBox(width: 16),
            Text(
              label,
              style: const TextStyle(
                fontFamily: AppFont.family,
                fontWeight: FontWeight.w700,
                fontSize: 16,
                color: AppColors.black,
              ),
            ),
            const Spacer(),
            AnimatedSwitcher(
              duration: const Duration(milliseconds: 200),
              switchInCurve: Curves.easeOut,
              transitionBuilder: (child, animation) {
                return ScaleTransition(
                  scale: animation,
                  child: FadeTransition(opacity: animation, child: child),
                );
              },
              child: selected
                  ? Container(
                      key: const ValueKey('on'),
                      width: 24,
                      height: 24,
                      decoration: const BoxDecoration(
                        color: AppColors.indigo,
                        shape: BoxShape.circle,
                      ),
                      alignment: Alignment.center,
                      child: const Icon(
                        CupertinoIcons.check_mark,
                        size: 14,
                        color: AppColors.white,
                      ),
                    )
                  : Container(
                      key: const ValueKey('off'),
                      width: 24,
                      height: 24,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        border: Border.all(
                          color: AppColors.grayBorder,
                          width: 2,
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
