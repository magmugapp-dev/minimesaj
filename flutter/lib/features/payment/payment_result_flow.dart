import 'package:magmug/app_core.dart';

enum PaymentResultTone { success, pending, failure }

class PaymentResultScreen extends StatelessWidget {
  final PaymentResultTone tone;
  final String badge;
  final String title;
  final String subtitle;
  final String productLabel;
  final String amountLabel;
  final String statusLabel;
  final String primaryLabel;
  final String? secondaryLabel;
  final String? footnote;
  final VoidCallback? onPrimaryTap;
  final VoidCallback? onSecondaryTap;

  const PaymentResultScreen({
    super.key,
    required this.tone,
    required this.badge,
    required this.title,
    required this.subtitle,
    required this.productLabel,
    required this.amountLabel,
    required this.statusLabel,
    required this.primaryLabel,
    this.secondaryLabel,
    this.footnote,
    this.onPrimaryTap,
    this.onSecondaryTap,
  });

  @override
  Widget build(BuildContext context) {
    return CupertinoPageScaffold(
      backgroundColor: const Color(0xFFF7F7FB),
      child: Stack(
        children: [
          Positioned.fill(child: _PaymentResultAmbient(tone: tone)),
          SafeArea(
            child: Column(
              children: [
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
                  child: Row(
                    children: [
                      PressableScale(
                        onTap: () => Navigator.of(context).maybePop(),
                        scale: 0.92,
                        child: Container(
                          width: 36,
                          height: 36,
                          decoration: BoxDecoration(
                            color: AppColors.white.withValues(alpha: 0.88),
                            shape: BoxShape.circle,
                          ),
                          alignment: Alignment.center,
                          child: const Icon(
                            CupertinoIcons.xmark,
                            size: 17,
                            color: AppColors.black,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                Expanded(
                  child: SingleChildScrollView(
                    physics: const BouncingScrollPhysics(),
                    padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
                    child: _PaymentResultContent(
                      tone: tone,
                      badge: badge,
                      title: title,
                      subtitle: subtitle,
                      productLabel: productLabel,
                      amountLabel: amountLabel,
                      statusLabel: statusLabel,
                      primaryLabel: primaryLabel,
                      secondaryLabel: secondaryLabel,
                      footnote: footnote,
                      onPrimaryTap:
                          onPrimaryTap ??
                          () => Navigator.of(context).maybePop(),
                      onSecondaryTap:
                          onSecondaryTap ??
                          (secondaryLabel != null
                              ? () => Navigator.of(context).maybePop()
                              : null),
                    ),
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

class PaymentResultSheet extends StatelessWidget {
  final PaymentResultTone tone;
  final String badge;
  final String title;
  final String subtitle;
  final String productLabel;
  final String amountLabel;
  final String statusLabel;
  final String primaryLabel;
  final String? secondaryLabel;
  final String? footnote;
  final VoidCallback? onPrimaryTap;
  final VoidCallback? onSecondaryTap;

  const PaymentResultSheet({
    super.key,
    required this.tone,
    required this.badge,
    required this.title,
    required this.subtitle,
    required this.productLabel,
    required this.amountLabel,
    required this.statusLabel,
    required this.primaryLabel,
    this.secondaryLabel,
    this.footnote,
    this.onPrimaryTap,
    this.onSecondaryTap,
  });

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final maxHeight = constraints.maxHeight.isFinite
            ? constraints.maxHeight * 0.92
            : MediaQuery.sizeOf(context).height * 0.92;

        return Align(
          alignment: Alignment.bottomCenter,
          child: Container(
            width: double.infinity,
            constraints: BoxConstraints(maxHeight: maxHeight),
            decoration: const BoxDecoration(
              color: AppColors.white,
              borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
            ),
            child: SafeArea(
              top: false,
              child: SingleChildScrollView(
                physics: const BouncingScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Container(
                      width: 48,
                      height: 4,
                      decoration: BoxDecoration(
                        color: const Color(0xFFD4D4D4),
                        borderRadius: BorderRadius.circular(8),
                      ),
                    ),
                    const SizedBox(height: 18),
                    _PaymentResultContent(
                      tone: tone,
                      badge: badge,
                      title: title,
                      subtitle: subtitle,
                      productLabel: productLabel,
                      amountLabel: amountLabel,
                      statusLabel: statusLabel,
                      primaryLabel: primaryLabel,
                      secondaryLabel: secondaryLabel,
                      footnote: footnote,
                      onPrimaryTap:
                          onPrimaryTap ??
                          () => Navigator.of(context).maybePop(),
                      onSecondaryTap:
                          onSecondaryTap ??
                          (secondaryLabel != null
                              ? () => Navigator.of(context).maybePop()
                              : null),
                    ),
                  ],
                ),
              ),
            ),
          ),
        );
      },
    );
  }
}

class _PaymentResultContent extends StatelessWidget {
  final PaymentResultTone tone;
  final String badge;
  final String title;
  final String subtitle;
  final String productLabel;
  final String amountLabel;
  final String statusLabel;
  final String primaryLabel;
  final String? secondaryLabel;
  final String? footnote;
  final VoidCallback onPrimaryTap;
  final VoidCallback? onSecondaryTap;

  const _PaymentResultContent({
    required this.tone,
    required this.badge,
    required this.title,
    required this.subtitle,
    required this.productLabel,
    required this.amountLabel,
    required this.statusLabel,
    required this.primaryLabel,
    required this.onPrimaryTap,
    this.secondaryLabel,
    this.footnote,
    this.onSecondaryTap,
  });

  @override
  Widget build(BuildContext context) {
    final spec = _toneSpec(tone);

    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 136,
          height: 136,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            gradient: RadialGradient(
              colors: [spec.glowColor, const Color(0x00FFFFFF)],
            ),
          ),
          alignment: Alignment.center,
          child: Container(
            width: 92,
            height: 92,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: spec.iconBackground,
              boxShadow: [
                BoxShadow(
                  color: spec.shadowColor,
                  blurRadius: 28,
                  offset: const Offset(0, 10),
                ),
              ],
            ),
            alignment: Alignment.center,
            child: Icon(spec.icon, size: 42, color: spec.iconColor),
          ),
        ),
        const SizedBox(height: 18),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
          decoration: BoxDecoration(
            color: spec.badgeBackground,
            borderRadius: BorderRadius.circular(999),
          ),
          child: Text(
            badge,
            style: TextStyle(
              fontFamily: AppFont.family,
              fontWeight: FontWeight.w800,
              fontSize: 10.5,
              letterSpacing: 0.4,
              color: spec.badgeForeground,
            ),
          ),
        ),
        const SizedBox(height: 14),
        Text(
          title,
          textAlign: TextAlign.center,
          style: const TextStyle(
            fontFamily: AppFont.family,
            fontWeight: FontWeight.w800,
            fontSize: 24,
            height: 1.22,
            color: AppColors.black,
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
            color: AppColors.neutral600,
          ),
        ),
        const SizedBox(height: 20),
        Container(
          width: double.infinity,
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: AppColors.white,
            borderRadius: BorderRadius.circular(22),
            border: Border.all(color: const Color(0xFFEAEAF0)),
          ),
          child: Column(
            children: [
              _PaymentSummaryRow(label: 'Urun', value: productLabel),
              const SizedBox(height: 12),
              _PaymentSummaryRow(label: 'Tutar', value: amountLabel),
              const SizedBox(height: 12),
              _PaymentSummaryRow(label: 'Durum', value: statusLabel),
            ],
          ),
        ),
        const SizedBox(height: 14),
        Container(
          width: double.infinity,
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: spec.noteBackground,
            borderRadius: BorderRadius.circular(18),
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(spec.noteIcon, size: 18, color: spec.noteIconColor),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  footnote ?? spec.defaultNote,
                  style: TextStyle(
                    fontFamily: AppFont.family,
                    fontWeight: FontWeight.w600,
                    fontSize: 12.5,
                    height: 1.45,
                    color: spec.noteTextColor,
                  ),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 18),
        GradientButton(label: primaryLabel, onTap: onPrimaryTap),
        if (secondaryLabel != null) ...[
          const SizedBox(height: 8),
          SecondaryButton(label: secondaryLabel!, onTap: onSecondaryTap),
        ],
      ],
    );
  }
}

class _PaymentSummaryRow extends StatelessWidget {
  final String label;
  final String value;

  const _PaymentSummaryRow({required this.label, required this.value});

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Text(
          label,
          style: const TextStyle(
            fontFamily: AppFont.family,
            fontWeight: FontWeight.w600,
            fontSize: 12,
            color: AppColors.neutral500,
          ),
        ),
        const Spacer(),
        Flexible(
          child: Text(
            value,
            textAlign: TextAlign.right,
            style: const TextStyle(
              fontFamily: AppFont.family,
              fontWeight: FontWeight.w800,
              fontSize: 13.5,
              color: AppColors.black,
            ),
          ),
        ),
      ],
    );
  }
}

class _PaymentResultAmbient extends StatelessWidget {
  final PaymentResultTone tone;

  const _PaymentResultAmbient({required this.tone});

  @override
  Widget build(BuildContext context) {
    final spec = _toneSpec(tone);

    return IgnorePointer(
      child: Stack(
        children: [
          Positioned(
            left: -80,
            top: -90,
            child: Container(
              width: 280,
              height: 260,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                gradient: RadialGradient(
                  colors: [spec.glowColor, const Color(0x00FFFFFF)],
                ),
              ),
            ),
          ),
          Positioned(
            right: -110,
            top: 60,
            child: Container(
              width: 260,
              height: 240,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                gradient: RadialGradient(
                  colors: [spec.badgeBackground, const Color(0x00FFFFFF)],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

({
  IconData icon,
  IconData noteIcon,
  Color iconColor,
  Color iconBackground,
  Color shadowColor,
  Color glowColor,
  Color badgeBackground,
  Color badgeForeground,
  Color noteBackground,
  Color noteIconColor,
  Color noteTextColor,
  String defaultNote,
})
_toneSpec(PaymentResultTone tone) {
  return switch (tone) {
    PaymentResultTone.success => (
      icon: CupertinoIcons.check_mark,
      noteIcon: CupertinoIcons.check_mark_circled_solid,
      iconColor: const Color(0xFF18794E),
      iconBackground: const Color(0xFFEAF8F0),
      shadowColor: const Color(0x2618794E),
      glowColor: const Color(0x3D8CD7A9),
      badgeBackground: const Color(0xFFEAF8F0),
      badgeForeground: const Color(0xFF18794E),
      noteBackground: const Color(0xFFEFFBF5),
      noteIconColor: const Color(0xFF18794E),
      noteTextColor: const Color(0xFF18794E),
      defaultNote:
          'Odeme onayi tamamlandi. Uygulama ici haklarin birkac saniye icinde hesabina yansir.',
    ),
    PaymentResultTone.pending => (
      icon: CupertinoIcons.time,
      noteIcon: CupertinoIcons.clock_fill,
      iconColor: const Color(0xFF946300),
      iconBackground: const Color(0xFFFFF4DA),
      shadowColor: const Color(0x26946300),
      glowColor: const Color(0x3DFDCB6E),
      badgeBackground: const Color(0xFFFFF4DA),
      badgeForeground: const Color(0xFF946300),
      noteBackground: const Color(0xFFFFF8E8),
      noteIconColor: const Color(0xFF946300),
      noteTextColor: const Color(0xFF946300),
      defaultNote:
          'Magaza islemi hala dogruluyor olabilir. Cikis yapsan bile sonuc tamamlandiginda haklarin otomatik eklenir.',
    ),
    PaymentResultTone.failure => (
      icon: CupertinoIcons.exclamationmark_circle,
      noteIcon: CupertinoIcons.info_circle_fill,
      iconColor: const Color(0xFFC52222),
      iconBackground: const Color(0xFFFFEAEA),
      shadowColor: const Color(0x26C52222),
      glowColor: const Color(0x3DFF9B9B),
      badgeBackground: const Color(0xFFFFEAEA),
      badgeForeground: const Color(0xFFC52222),
      noteBackground: const Color(0xFFFFF3F3),
      noteIconColor: const Color(0xFFC52222),
      noteTextColor: const Color(0xFFC52222),
      defaultNote:
          'Kart limiti, baglanti problemi veya magaza iptali nedeniyle islem tamamlanmamis olabilir. Tekrar deneyebilir veya destek ekibine yazabilirsin.',
    ),
  };
}
