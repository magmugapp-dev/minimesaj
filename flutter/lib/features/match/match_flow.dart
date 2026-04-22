import 'package:magmug/app_core.dart';
import 'package:magmug/features/home/home_flow.dart';

// =============================================================================

enum MatchGender { mixed, female, male }

enum MatchFoundTheme { superMatch, normal }

@immutable
class MatchState {
  final int gemBalance;
  final int freeMatchesLeft;
  final MatchGender gender;
  final bool superMatchEnabled;

  const MatchState({
    this.gemBalance = 1323,
    this.freeMatchesLeft = 10,
    this.gender = MatchGender.mixed,
    this.superMatchEnabled = false,
  });

  MatchState copyWith({
    int? gemBalance,
    int? freeMatchesLeft,
    MatchGender? gender,
    bool? superMatchEnabled,
  }) {
    return MatchState(
      gemBalance: gemBalance ?? this.gemBalance,
      freeMatchesLeft: freeMatchesLeft ?? this.freeMatchesLeft,
      gender: gender ?? this.gender,
      superMatchEnabled: superMatchEnabled ?? this.superMatchEnabled,
    );
  }
}

class MatchNotifier extends Notifier<MatchState> {
  @override
  MatchState build() => const MatchState();

  void setGender(MatchGender v) => state = state.copyWith(gender: v);
  void toggleSuperMatch() =>
      state = state.copyWith(superMatchEnabled: !state.superMatchEnabled);
  void consumeFreeMatch() {
    if (state.freeMatchesLeft > 0) {
      state = state.copyWith(freeMatchesLeft: state.freeMatchesLeft - 1);
    }
  }
}

final matchProvider = NotifierProvider<MatchNotifier, MatchState>(
  MatchNotifier.new,
);

String _formatGem(int v) {
  final s = v.toString();
  final buf = StringBuffer();
  for (var i = 0; i < s.length; i++) {
    if (i > 0 && (s.length - i) % 3 == 0) buf.write('.');
    buf.write(s[i]);
  }
  return buf.toString();
}

class _MatchTopBar extends ConsumerWidget {
  final bool solidTitle;

  const _MatchTopBar({this.solidTitle = true});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final gem = ref.watch(matchProvider.select((s) => s.gemBalance));
    final titleColor = solidTitle ? AppColors.neutral950 : AppColors.white;
    final iconColor = solidTitle ? AppColors.neutral950 : AppColors.white;

    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
      child: Row(
        children: [
          PressableScale(
            onTap: () => Navigator.of(context).maybePop(),
            scale: 0.9,
            child: Padding(
              padding: const EdgeInsets.all(4),
              child: Icon(
                CupertinoIcons.chevron_back,
                size: 22,
                color: iconColor,
              ),
            ),
          ),
          const SizedBox(width: 6),
          Expanded(
            child: Text(
              'Eslesme modu',
              style: TextStyle(
                fontFamily: AppFont.family,
                fontWeight: FontWeight.w800,
                fontSize: 18,
                color: titleColor,
              ),
            ),
          ),
          _BalanceChip(amount: gem),
        ],
      ),
    );
  }
}

class _BalanceChip extends StatelessWidget {
  final int amount;

  const _BalanceChip({required this.amount});

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
            _formatGem(amount),
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

class _StatCard extends StatelessWidget {
  final String value;
  final String label;

  const _StatCard({required this.value, required this.label});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(20),
        boxShadow: const [
          BoxShadow(
            color: Color(0x08000000),
            blurRadius: 8,
            offset: Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          ShaderMask(
            shaderCallback: (bounds) => const LinearGradient(
              begin: Alignment.centerLeft,
              end: Alignment.centerRight,
              colors: [AppColors.indigo, AppColors.coral],
            ).createShader(bounds),
            child: Text(
              value,
              style: const TextStyle(
                fontFamily: AppFont.family,
                fontWeight: FontWeight.w800,
                fontSize: 28,
                color: AppColors.white,
                height: 1.1,
              ),
            ),
          ),
          const SizedBox(height: 6),
          Text(
            label,
            style: const TextStyle(
              fontFamily: AppFont.family,
              fontSize: 13,
              color: AppColors.black,
            ),
          ),
        ],
      ),
    );
  }
}

class _RadarAvatar extends StatefulWidget {
  const _RadarAvatar();

  @override
  State<_RadarAvatar> createState() => _RadarAvatarState();
}

class _RadarAvatarState extends State<_RadarAvatar>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(seconds: 3),
    )..repeat();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 344,
      height: 344,
      child: Stack(
        alignment: Alignment.center,
        children: [
          AnimatedBuilder(
            animation: _controller,
            builder: (context, _) => CustomPaint(
              size: const Size(344, 344),
              painter: _RadarPainter(phase: _controller.value),
            ),
          ),
          ClipOval(
            child: Image.asset(
              'assets/images/portrait_self.png',
              width: 160,
              height: 160,
              fit: BoxFit.cover,
            ),
          ),
        ],
      ),
    );
  }
}

class _RadarPainter extends CustomPainter {
  final double phase;

  _RadarPainter({required this.phase});

  @override
  void paint(Canvas canvas, Size size) {
    final center = Offset(size.width / 2, size.height / 2);
    const baseColor = Color(0xFFE5E5E5);

    final rings = [
      (radius: 100.0, width: 1.5, opacity: 1.0),
      (radius: 132.0, width: 1.0, opacity: 0.8),
      (radius: 172.0, width: 0.6, opacity: 0.5),
    ];
    for (final ring in rings) {
      final p = Paint()
        ..color = baseColor.withValues(alpha: ring.opacity)
        ..style = PaintingStyle.stroke
        ..strokeWidth = ring.width;
      canvas.drawCircle(center, ring.radius, p);
    }

    // Pulse ring â€” expanding
    final pulseRadius = 100 + (phase * 72);
    final pulseOpacity = (1 - phase) * 0.5;
    final pulsePaint = Paint()
      ..color = AppColors.indigo.withValues(alpha: pulseOpacity)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 2;
    canvas.drawCircle(center, pulseRadius, pulsePaint);
  }

  @override
  bool shouldRepaint(covariant _RadarPainter old) => old.phase != phase;
}

class _StartMatchButton extends StatelessWidget {
  final String label;
  final int? gemCost;
  final VoidCallback onTap;

  const _StartMatchButton({
    required this.label,
    required this.onTap,
    this.gemCost,
  });

  @override
  Widget build(BuildContext context) {
    return PressableScale(
      onTap: onTap,
      child: Container(
        height: 56,
        padding: const EdgeInsets.symmetric(horizontal: 8),
        decoration: BoxDecoration(
          gradient: AppColors.primary,
          borderRadius: BorderRadius.circular(40),
          boxShadow: const [
            BoxShadow(
              color: AppColors.shadow,
              blurRadius: 24,
              offset: Offset(0, 8),
            ),
          ],
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text(
              label,
              style: const TextStyle(
                fontFamily: AppFont.family,
                fontWeight: FontWeight.w800,
                fontSize: 16,
                color: AppColors.white,
              ),
            ),
            if (gemCost != null) ...[
              const SizedBox(width: 12),
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 10,
                  vertical: 6,
                ),
                decoration: BoxDecoration(
                  color: AppColors.white,
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Image.asset(
                      'assets/images/icon_diamond.png',
                      width: 14,
                      height: 14,
                    ),
                    const SizedBox(width: 6),
                    Text(
                      '$gemCost tas',
                      style: const TextStyle(
                        fontFamily: AppFont.family,
                        fontWeight: FontWeight.w600,
                        fontSize: 13,
                        color: AppColors.zinc900,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _FilterPill extends StatelessWidget {
  final Widget leading;
  final String label;
  final VoidCallback onTap;

  const _FilterPill({
    required this.leading,
    required this.label,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return PressableScale(
      onTap: onTap,
      scale: 0.98,
      child: Container(
        height: 52,
        padding: const EdgeInsets.symmetric(horizontal: 16),
        decoration: BoxDecoration(
          color: AppColors.white,
          borderRadius: BorderRadius.circular(55),
          boxShadow: const [
            BoxShadow(
              color: Color(0x08000000),
              blurRadius: 8,
              offset: Offset(0, 2),
            ),
          ],
        ),
        child: Row(
          children: [
            leading,
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                label,
                style: const TextStyle(
                  fontFamily: AppFont.family,
                  fontWeight: FontWeight.w600,
                  fontSize: 14,
                  color: AppColors.neutral950,
                ),
              ),
            ),
            const Icon(
              CupertinoIcons.chevron_up,
              size: 16,
              color: AppColors.neutral950,
            ),
          ],
        ),
      ),
    );
  }
}

class _GenderSymbolLeading extends StatelessWidget {
  const _GenderSymbolLeading();

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 22,
      height: 22,
      child: Stack(
        children: [
          Positioned(
            left: 0,
            top: 0,
            child: Image.asset(
              'assets/images/icon_filter_female.png',
              width: 13,
              height: 13,
            ),
          ),
          Positioned(
            right: 0,
            bottom: 0,
            child: Image.asset(
              'assets/images/icon_filter_male.png',
              width: 13,
              height: 13,
            ),
          ),
        ],
      ),
    );
  }
}

// ------ Screen: Match Mode (free / paid) ---------------------------------------

enum MatchModeVariant { free, paid }

class MatchModeScreen extends ConsumerWidget {
  final MatchModeVariant variant;

  const MatchModeScreen({super.key, this.variant = MatchModeVariant.free});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final isFree = variant == MatchModeVariant.free;
    final free = ref.watch(matchProvider.select((s) => s.freeMatchesLeft));

    void openGenderSheet() {
      showCupertinoModalPopup<void>(
        context: context,
        builder: (_) => const GenderFilterSheet(),
      );
    }

    void openAgeSheet() {
      showCupertinoModalPopup<void>(
        context: context,
        builder: (ctx) => Container(
          height: 260,
          decoration: const BoxDecoration(
            color: AppColors.white,
            borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
          ),
          padding: const EdgeInsets.all(24),
          alignment: Alignment.center,
          child: const Text(
            'Yas filtresi (yakinda)',
            style: TextStyle(
              fontFamily: AppFont.family,
              fontWeight: FontWeight.w700,
              fontSize: 16,
              color: AppColors.neutral950,
            ),
          ),
        ),
      );
    }

    void onStart() {
      if (isFree) {
        ref.read(matchProvider.notifier).consumeFreeMatch();
        Navigator.of(context).push(cupertinoRoute(const MatchingScreen()));
      } else {
        showCupertinoModalPopup<void>(
          context: context,
          builder: (_) => const PurchaseSheet(),
        );
      }
    }

    return CupertinoPageScaffold(
      backgroundColor: AppColors.neutral100,
      child: SafeArea(
        bottom: false,
        child: Column(
          children: [
            const _MatchTopBar(),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: Row(
                children: const [
                  Expanded(
                    child: _StatCard(
                      value: '538',
                      label: 'Kisi simdi cevrimici',
                    ),
                  ),
                  SizedBox(width: 16),
                  Expanded(
                    child: _StatCard(value: '58', label: 'Seni bekleyen kisi'),
                  ),
                ],
              ),
            ),
            const Expanded(child: Center(child: _RadarAvatar())),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: _StartMatchButton(
                label: 'Eslesmeyi Baslat!',
                gemCost: isFree ? null : 8,
                onTap: onStart,
              ),
            ),
            const SizedBox(height: 16),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: Row(
                children: [
                  Expanded(
                    child: _FilterPill(
                      leading: const _GenderSymbolLeading(),
                      label: 'Cinsiyet',
                      onTap: openGenderSheet,
                    ),
                  ),
                  const SizedBox(width: 16),
                  Expanded(
                    child: _FilterPill(
                      leading: const Text('🎂', style: TextStyle(fontSize: 18)),
                      label: 'Yas',
                      onTap: openAgeSheet,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: _FreeInfoBox(isFree: isFree, freeLeft: free),
            ),
            SizedBox(height: MediaQuery.paddingOf(context).bottom + 16),
          ],
        ),
      ),
    );
  }
}

class _FreeInfoBox extends StatelessWidget {
  final bool isFree;
  final int freeLeft;

  const _FreeInfoBox({required this.isFree, required this.freeLeft});

  @override
  Widget build(BuildContext context) {
    if (isFree) {
      return Container(
        width: double.infinity,
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        decoration: BoxDecoration(
          color: const Color(0xFFE5E5E5),
          borderRadius: BorderRadius.circular(20),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text.rich(
              TextSpan(
                style: const TextStyle(
                  fontFamily: AppFont.family,
                  fontWeight: FontWeight.w500,
                  fontSize: 12,
                  color: Color(0xFF171717),
                ),
                children: [
                  const TextSpan(text: 'Bu gun icin '),
                  TextSpan(
                    text: '$freeLeft',
                    style: const TextStyle(fontWeight: FontWeight.w800),
                  ),
                  const TextSpan(text: ' ucretsiz eslesme hakkin kaldi'),
                ],
              ),
            ),
            const SizedBox(height: 4),
            const Text(
              "Her gece 00:00'da eslesme haklariniz tekrardan yenilenir.",
              style: TextStyle(
                fontFamily: AppFont.family,
                fontSize: 12,
                color: AppColors.neutral600,
              ),
            ),
          ],
        ),
      );
    }
    return Column(
      children: const [
        Text(
          'Bu gunluk ucretsiz eslesme haklariniz kullanildi.',
          textAlign: TextAlign.center,
          style: TextStyle(
            fontFamily: AppFont.family,
            fontWeight: FontWeight.w500,
            fontSize: 12,
            color: Color(0xFF171717),
          ),
        ),
        SizedBox(height: 4),
        Text(
          "Bu gece 00:00'da eslesme haklariniz tekrardan yenilenecek.",
          textAlign: TextAlign.center,
          style: TextStyle(
            fontFamily: AppFont.family,
            fontSize: 12,
            color: AppColors.neutral600,
          ),
        ),
      ],
    );
  }
}

// ------ Sheet: Purchase --------------------------------------------------------

class PurchaseSheet extends StatefulWidget {
  const PurchaseSheet({super.key});

  @override
  State<PurchaseSheet> createState() => _PurchaseSheetState();
}

class _PurchaseSheetState extends State<PurchaseSheet> {
  int _selected = 1;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        color: AppColors.neutral100,
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Center(
            child: Container(
              width: 48,
              height: 4,
              decoration: BoxDecoration(
                color: const Color(0xFFD4D4D4),
                borderRadius: BorderRadius.circular(8),
              ),
            ),
          ),
          const SizedBox(height: 20),
          const Text(
            'Iste size ozel oneri!',
            style: TextStyle(
              fontFamily: AppFont.family,
              fontWeight: FontWeight.w800,
              fontSize: 22,
              color: AppColors.neutral950,
            ),
          ),
          const SizedBox(height: 6),
          const Text(
            'Devam etmek icin 8 tas gerekli.',
            style: TextStyle(
              fontFamily: AppFont.family,
              fontSize: 14,
              color: AppColors.neutral600,
            ),
          ),
          const SizedBox(height: 20),
          _CreditOption(
            credits: 25,
            perMessage: '~2.00 TL / mesaj',
            price: '49.99 TL',
            selected: _selected == 0,
            popular: false,
            onTap: () => setState(() => _selected = 0),
          ),
          const SizedBox(height: 10),
          _CreditOption(
            credits: 60,
            perMessage: '~1.67 TL / mesaj',
            price: '99.99 TL',
            selected: _selected == 1,
            popular: true,
            onTap: () => setState(() => _selected = 1),
          ),
          const SizedBox(height: 10),
          _CreditOption(
            credits: 150,
            perMessage: '~1.33 TL / mesaj',
            price: '199.99 TL',
            selected: _selected == 2,
            popular: false,
            onTap: () => setState(() => _selected = 2),
          ),
          const SizedBox(height: 20),
          GradientButton(
            label: 'Satin Al',
            onTap: () => Navigator.of(context).maybePop(),
          ),
          const SizedBox(height: 10),
          const Center(
            child: Text(
              'Lorem ipsum',
              style: TextStyle(
                fontFamily: AppFont.family,
                fontSize: 12,
                color: AppColors.neutral600,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _CreditOption extends StatelessWidget {
  final int credits;
  final String perMessage;
  final String price;
  final bool selected;
  final bool popular;
  final VoidCallback onTap;

  const _CreditOption({
    required this.credits,
    required this.perMessage,
    required this.price,
    required this.selected,
    required this.popular,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return PressableScale(
      onTap: onTap,
      scale: 0.99,
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          AnimatedContainer(
            duration: const Duration(milliseconds: 180),
            padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
            decoration: BoxDecoration(
              color: selected ? const Color(0x1A2B7FFF) : AppColors.white,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(
                color: selected
                    ? const Color(0xFF2B7FFF)
                    : const Color(0x00000000),
                width: 1.5,
              ),
            ),
            child: Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text.rich(
                        TextSpan(
                          style: const TextStyle(
                            fontFamily: AppFont.family,
                            color: AppColors.neutral950,
                          ),
                          children: [
                            TextSpan(
                              text: '$credits',
                              style: const TextStyle(
                                fontWeight: FontWeight.w800,
                                fontSize: 18,
                              ),
                            ),
                            const TextSpan(
                              text: ' kredi',
                              style: TextStyle(
                                fontWeight: FontWeight.w500,
                                fontSize: 14,
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        perMessage,
                        style: const TextStyle(
                          fontFamily: AppFont.family,
                          fontSize: 12,
                          color: AppColors.neutral600,
                        ),
                      ),
                    ],
                  ),
                ),
                Text(
                  price,
                  style: TextStyle(
                    fontFamily: AppFont.family,
                    fontWeight: FontWeight.w800,
                    fontSize: 18,
                    color: selected
                        ? const Color(0xFF2B7FFF)
                        : AppColors.neutral950,
                  ),
                ),
              ],
            ),
          ),
          if (popular)
            Positioned(
              top: -10,
              right: 24,
              child: Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 10,
                  vertical: 4,
                ),
                decoration: BoxDecoration(
                  color: const Color(0xFF2B7FFF),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: const Text(
                  'EN POPULER',
                  style: TextStyle(
                    fontFamily: AppFont.family,
                    fontWeight: FontWeight.w800,
                    fontSize: 10,
                    color: AppColors.white,
                    letterSpacing: 0.5,
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

// ------ Sheet: Gender Filter ---------------------------------------------------

class GenderFilterSheet extends ConsumerWidget {
  const GenderFilterSheet({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final gender = ref.watch(matchProvider.select((s) => s.gender));
    final superOn = ref.watch(matchProvider.select((s) => s.superMatchEnabled));
    final notifier = ref.read(matchProvider.notifier);

    return Container(
      decoration: const BoxDecoration(
        color: AppColors.neutral100,
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Center(
            child: Container(
              width: 48,
              height: 4,
              decoration: BoxDecoration(
                color: const Color(0xFFD4D4D4),
                borderRadius: BorderRadius.circular(8),
              ),
            ),
          ),
          const SizedBox(height: 24),
          const Text(
            'Cinsiyet Filtresi',
            style: TextStyle(
              fontFamily: AppFont.family,
              fontWeight: FontWeight.w800,
              fontSize: 22,
              color: AppColors.neutral950,
            ),
          ),
          const SizedBox(height: 14),
          const Padding(
            padding: EdgeInsets.symmetric(horizontal: 4),
            child: Text(
              'Cinsiyet tercihiniz',
              style: TextStyle(
                fontFamily: AppFont.family,
                fontSize: 12,
                color: AppColors.neutral600,
              ),
            ),
          ),
          const SizedBox(height: 8),
          Row(
            children: [
              Expanded(
                child: _GenderCard(
                  label: 'Tumu Karisik',
                  asset: 'assets/images/avatar_card_mixed.png',
                  selected: gender == MatchGender.mixed,
                  onTap: () => notifier.setGender(MatchGender.mixed),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: _GenderCard(
                  label: 'Kadinlar',
                  asset: 'assets/images/avatar_card_female.png',
                  selected: gender == MatchGender.female,
                  onTap: () => notifier.setGender(MatchGender.female),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: _GenderCard(
                  label: 'Erkekler',
                  asset: 'assets/images/avatar_card_mixed.png',
                  selected: gender == MatchGender.male,
                  onTap: () => notifier.setGender(MatchGender.male),
                ),
              ),
            ],
          ),
          const SizedBox(height: 28),
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: const [
                    Text(
                      'Super Eslesme',
                      style: TextStyle(
                        fontFamily: AppFont.family,
                        fontWeight: FontWeight.w800,
                        fontSize: 16,
                        color: AppColors.neutral950,
                      ),
                    ),
                    SizedBox(height: 4),
                    Text(
                      'Super Eslesme, sana daha uyumlu kisileri karsina cikartabilir.',
                      style: TextStyle(
                        fontFamily: AppFont.family,
                        fontSize: 13,
                        color: AppColors.neutral600,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 16),
              _SoftSwitch(
                value: superOn,
                onChanged: (_) => notifier.toggleSuperMatch(),
              ),
            ],
          ),
          const SizedBox(height: 24),
          GradientButton(
            label: 'Eslesmeyi Baslat!',
            onTap: () {
              Navigator.of(context).maybePop();
              Navigator.of(
                context,
              ).push(cupertinoRoute(const MatchingScreen()));
            },
          ),
        ],
      ),
    );
  }
}

class _GenderCard extends StatelessWidget {
  final String label;
  final String asset;
  final bool selected;
  final VoidCallback onTap;

  const _GenderCard({
    required this.label,
    required this.asset,
    required this.selected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return PressableScale(
      onTap: onTap,
      scale: 0.97,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 180),
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: selected ? const Color(0x1A2B7FFF) : AppColors.white,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: selected ? const Color(0xFF2B7FFF) : const Color(0x00000000),
            width: 1.5,
          ),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            SizedBox(
              height: 64,
              child: Image.asset(asset, fit: BoxFit.contain),
            ),
            const SizedBox(height: 12),
            Text(
              label,
              style: const TextStyle(
                fontFamily: AppFont.family,
                fontWeight: FontWeight.w700,
                fontSize: 13,
                color: AppColors.neutral950,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _SoftSwitch extends StatelessWidget {
  final bool value;
  final ValueChanged<bool> onChanged;

  const _SoftSwitch({required this.value, required this.onChanged});

  @override
  Widget build(BuildContext context) {
    return PressableScale(
      onTap: () => onChanged(!value),
      scale: 0.95,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        width: 48,
        height: 28,
        padding: const EdgeInsets.all(3),
        decoration: BoxDecoration(
          color: value ? AppColors.indigo : const Color(0xFFE5E5E5),
          borderRadius: BorderRadius.circular(24),
        ),
        child: AnimatedAlign(
          duration: const Duration(milliseconds: 220),
          curve: Curves.easeOutCubic,
          alignment: value ? Alignment.centerRight : Alignment.centerLeft,
          child: Container(
            width: 22,
            height: 22,
            decoration: const BoxDecoration(
              color: AppColors.white,
              shape: BoxShape.circle,
              boxShadow: [
                BoxShadow(
                  color: Color(0x1F000000),
                  blurRadius: 4,
                  offset: Offset(0, 2),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

// ------ Screen: Matching (loading) --------------------------------------------

class MatchingScreen extends StatefulWidget {
  final bool withAd;

  const MatchingScreen({super.key, this.withAd = false});

  @override
  State<MatchingScreen> createState() => _MatchingScreenState();
}

class _MatchingScreenState extends State<MatchingScreen> {
  bool _adVisible = true;

  @override
  Widget build(BuildContext context) {
    return CupertinoPageScaffold(
      backgroundColor: AppColors.neutral100,
      child: Stack(
        children: [
          if (!widget.withAd) const Positioned.fill(child: _AmbientFullBg()),
          SafeArea(
            child: widget.withAd
                ? _buildAdBody(context)
                : _buildEmojiBody(context),
          ),
        ],
      ),
    );
  }

  Widget _buildEmojiBody(BuildContext context) {
    return Column(
      children: [
        const Spacer(),
        const Text('😝', style: TextStyle(fontSize: 64, height: 1.0)),
        const SizedBox(height: 24),
        const Text(
          'Senin icin en uygunu\nbuluyoruz...',
          textAlign: TextAlign.center,
          style: TextStyle(
            fontFamily: AppFont.family,
            fontWeight: FontWeight.w800,
            fontSize: 24,
            height: 1.25,
            color: AppColors.neutral950,
            letterSpacing: -0.5,
          ),
        ),
        const SizedBox(height: 8),
        const Text(
          'Merhaba de ve nazik ol',
          style: TextStyle(
            fontFamily: AppFont.family,
            fontSize: 14,
            color: AppColors.neutral600,
          ),
        ),
        const Spacer(),
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
          child: _StopButton(onTap: () => Navigator.of(context).maybePop()),
        ),
      ],
    );
  }

  Widget _buildAdBody(BuildContext context) {
    return Column(
      children: [
        Expanded(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: AnimatedOpacity(
              duration: const Duration(milliseconds: 220),
              opacity: _adVisible ? 1.0 : 0.0,
              child: _AdCard(onClose: () => setState(() => _adVisible = false)),
            ),
          ),
        ),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16),
          child: Container(
            width: double.infinity,
            padding: const EdgeInsets.fromLTRB(16, 12, 12, 12),
            decoration: BoxDecoration(
              color: const Color(0xFFE5E5E5),
              borderRadius: BorderRadius.circular(20),
            ),
            child: Row(
              children: [
                Expanded(
                  child: Text.rich(
                    TextSpan(
                      style: const TextStyle(
                        fontFamily: AppFont.family,
                        fontSize: 12,
                        color: AppColors.neutral950,
                        height: 1.35,
                      ),
                      children: [
                        const TextSpan(
                          text: 'Magmug Premium',
                          style: TextStyle(fontWeight: FontWeight.w700),
                        ),
                        const TextSpan(
                          text: "'da tum reklamlardan kurtulursun.",
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 12,
                    vertical: 8,
                  ),
                  decoration: BoxDecoration(
                    color: AppColors.white,
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Image.asset(
                        'assets/images/icon_diamond.png',
                        width: 14,
                        height: 14,
                      ),
                      const SizedBox(width: 6),
                      const Text(
                        'Premium',
                        style: TextStyle(
                          fontFamily: AppFont.family,
                          fontWeight: FontWeight.w700,
                          fontSize: 13,
                          color: AppColors.zinc900,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 12),
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
          child: _StopButton(onTap: () => Navigator.of(context).maybePop()),
        ),
      ],
    );
  }
}

class _AmbientFullBg extends StatelessWidget {
  const _AmbientFullBg();

  @override
  Widget build(BuildContext context) {
    return IgnorePointer(
      child: Stack(
        children: [
          const DecoratedBox(
            decoration: BoxDecoration(color: AppColors.neutral100),
            child: SizedBox.expand(),
          ),
          Positioned(
            left: -80,
            top: -40,
            child: _blob(320, const Color(0x40FF9794)),
          ),
          Positioned(
            right: -100,
            top: 200,
            child: _blob(360, const Color(0x40A594F9)),
          ),
          Positioned(
            left: -60,
            bottom: 80,
            child: _blob(280, const Color(0x33FDB384)),
          ),
          Positioned(
            right: -80,
            bottom: -60,
            child: _blob(340, const Color(0x3AC4C9FF)),
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

class _AdCard extends StatelessWidget {
  final VoidCallback onClose;

  const _AdCard({required this.onClose});

  @override
  Widget build(BuildContext context) {
    return ClipRRect(
      borderRadius: BorderRadius.circular(24),
      child: Stack(
        children: [
          Positioned.fill(
            child: Image.asset(
              'assets/images/ad_sample.png',
              fit: BoxFit.cover,
            ),
          ),
          Positioned(
            top: 12,
            right: 12,
            child: PressableScale(
              onTap: onClose,
              scale: 0.94,
              child: Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 12,
                  vertical: 8,
                ),
                decoration: BoxDecoration(
                  color: AppColors.white,
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: const [
                    Text(
                      'Reklami Kapat',
                      style: TextStyle(
                        fontFamily: AppFont.family,
                        fontWeight: FontWeight.w600,
                        fontSize: 13,
                        color: AppColors.zinc900,
                      ),
                    ),
                    SizedBox(width: 6),
                    Icon(
                      CupertinoIcons.xmark,
                      size: 14,
                      color: AppColors.zinc900,
                    ),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _StopButton extends StatelessWidget {
  final VoidCallback onTap;

  const _StopButton({required this.onTap});

  @override
  Widget build(BuildContext context) {
    return PressableScale(
      onTap: onTap,
      child: Container(
        height: 56,
        decoration: BoxDecoration(
          color: AppColors.white,
          borderRadius: BorderRadius.circular(40),
          boxShadow: const [
            BoxShadow(
              color: Color(0x14000000),
              blurRadius: 12,
              offset: Offset(0, 4),
            ),
          ],
        ),
        alignment: Alignment.center,
        child: const Text(
          'Durdur',
          style: TextStyle(
            fontFamily: AppFont.family,
            fontWeight: FontWeight.w600,
            fontSize: 16,
            color: AppColors.neutral600,
          ),
        ),
      ),
    );
  }
}

// ------ Screen: Match Found ---------------------------------------------------

class MatchFoundScreen extends StatelessWidget {
  final MatchFoundTheme theme;

  const MatchFoundScreen({super.key, this.theme = MatchFoundTheme.superMatch});

  @override
  Widget build(BuildContext context) {
    final isSuper = theme == MatchFoundTheme.superMatch;
    final gradient = isSuper
        ? const LinearGradient(
            begin: Alignment.topRight,
            end: Alignment.bottomLeft,
            colors: [Color(0xFFFFB4C6), Color(0xFFA594F9), Color(0xFF7C6DF5)],
            stops: [0.0, 0.55, 1.0],
          )
        : const LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [Color(0xFFFFC77A), Color(0xFFF5A524), Color(0xFFCC6A00)],
            stops: [0.0, 0.55, 1.0],
          );

    return CupertinoPageScaffold(
      backgroundColor: AppColors.black,
      child: Stack(
        children: [
          Positioned.fill(
            child: DecoratedBox(decoration: BoxDecoration(gradient: gradient)),
          ),
          SafeArea(
            child: Column(
              children: [
                const _MatchTopBar(solidTitle: false),
                const SizedBox(height: 40),
                const _MatchPairAvatars(),
                const SizedBox(height: 20),
                const Text(
                  'Anna, 23',
                  style: TextStyle(
                    fontFamily: AppFont.family,
                    fontWeight: FontWeight.w800,
                    fontSize: 26,
                    color: AppColors.white,
                    letterSpacing: -0.5,
                  ),
                ),
                const Spacer(),
                Text(
                  isSuper ? 'Super\nEslesme' : 'Eslesme',
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    fontFamily: AppFont.family,
                    fontWeight: FontWeight.w800,
                    fontSize: 44,
                    height: 1.05,
                    color: AppColors.white,
                    letterSpacing: -1.5,
                  ),
                ),
                const SizedBox(height: 16),
                const Text(
                  'Sen ve Asli birbirinizi begendiniz.',
                  style: TextStyle(
                    fontFamily: AppFont.family,
                    fontSize: 16,
                    color: AppColors.white,
                  ),
                ),
                const Spacer(),
                Padding(
                  padding: const EdgeInsets.fromLTRB(24, 0, 24, 12),
                  child: GradientButton(
                    label: 'Mesaj Gonder',
                    onTap: () {
                      Navigator.of(context).pushAndRemoveUntil(
                        cupertinoRoute(const HomeScreen(mode: HomeMode.list)),
                        (r) => r.isFirst,
                      );
                    },
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.fromLTRB(24, 0, 24, 24),
                  child: _StopButton(
                    onTap: () => Navigator.of(context).maybePop(),
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

class _MatchPairAvatars extends StatelessWidget {
  const _MatchPairAvatars();

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 230,
      height: 130,
      child: Stack(
        children: [
          Positioned(
            left: 0,
            child: _circledAvatar('assets/images/portrait_self.png'),
          ),
          Positioned(
            left: 100,
            child: _circledAvatar('assets/images/portrait_match.png'),
          ),
        ],
      ),
    );
  }

  Widget _circledAvatar(String asset) {
    return Container(
      width: 130,
      height: 130,
      decoration: const BoxDecoration(
        shape: BoxShape.circle,
        boxShadow: [
          BoxShadow(
            color: Color(0x26000000),
            blurRadius: 30,
            offset: Offset(0, 8),
          ),
        ],
      ),
      child: ClipOval(
        child: Container(
          padding: const EdgeInsets.all(5),
          color: AppColors.white,
          child: ClipOval(child: Image.asset(asset, fit: BoxFit.cover)),
        ),
      ),
    );
  }
}

// =============================================================================
// Message module â€” sohbet ekrani + profil + hediye/sikayet/engelle sheet'leri
