import 'dart:async';
import 'dart:math' as math;

import 'package:magmug/app_core.dart';
import 'package:magmug/features/chat/chat_flow.dart';
import 'package:magmug/features/home/home_flow.dart';
import 'package:magmug/l10n/app_localizations.dart';
import 'package:magmug/features/payment/payment_result_flow.dart';
import 'package:magmug/features/payment/store_purchase_service.dart';

// =============================================================================

enum MatchGender { mixed, female, male }

enum MatchFoundTheme { superMatch, normal }

MatchFoundTheme _themeForMatchState(MatchState state) {
  return state.superMatchEnabled
      ? MatchFoundTheme.normal
      : MatchFoundTheme.superMatch;
}

@immutable
class MatchState {
  final int gemBalance;
  final int freeMatchesLeft;
  final int onlineCount;
  final int waitingLikes;
  final int startCost;
  final MatchGender gender;
  final bool superMatchEnabled;
  final int minAge;
  final int maxAge;
  final bool isLoading;
  final bool isStarting;
  final String? notice;
  final AppMatchCandidate? activeCandidate;

  const MatchState({
    this.gemBalance = 0,
    this.freeMatchesLeft = 0,
    this.onlineCount = 0,
    this.waitingLikes = 0,
    this.startCost = 8,
    this.gender = MatchGender.mixed,
    this.superMatchEnabled = false,
    this.minAge = 18,
    this.maxAge = 60,
    this.isLoading = true,
    this.isStarting = false,
    this.notice,
    this.activeCandidate,
  });

  MatchState copyWith({
    int? gemBalance,
    int? freeMatchesLeft,
    int? onlineCount,
    int? waitingLikes,
    int? startCost,
    MatchGender? gender,
    bool? superMatchEnabled,
    int? minAge,
    int? maxAge,
    bool? isLoading,
    bool? isStarting,
    String? notice,
    bool clearNotice = false,
    AppMatchCandidate? activeCandidate,
    bool clearActiveCandidate = false,
  }) {
    return MatchState(
      gemBalance: gemBalance ?? this.gemBalance,
      freeMatchesLeft: freeMatchesLeft ?? this.freeMatchesLeft,
      onlineCount: onlineCount ?? this.onlineCount,
      waitingLikes: waitingLikes ?? this.waitingLikes,
      startCost: startCost ?? this.startCost,
      gender: gender ?? this.gender,
      superMatchEnabled: superMatchEnabled ?? this.superMatchEnabled,
      minAge: minAge ?? this.minAge,
      maxAge: maxAge ?? this.maxAge,
      isLoading: isLoading ?? this.isLoading,
      isStarting: isStarting ?? this.isStarting,
      notice: clearNotice ? null : (notice ?? this.notice),
      activeCandidate: clearActiveCandidate
          ? null
          : (activeCandidate ?? this.activeCandidate),
    );
  }
}

class MatchNotifier extends Notifier<MatchState> {
  String? _bootstrappedToken;

  @override
  MatchState build() => const MatchState();

  Future<void> bootstrap({bool force = false}) async {
    final session = ref.read(appAuthProvider).asData?.value;
    final token = session?.token;
    final user = session?.user;

    if (token == null || token.trim().isEmpty) {
      state = const MatchState(isLoading: false);
      return;
    }

    if (!force && _bootstrappedToken == token) {
      return;
    }

    final shouldResetFiltersForAppLaunch = _bootstrappedToken != token;
    _bootstrappedToken = token;
    state = state.copyWith(
      gemBalance: user?.gemBalance ?? state.gemBalance,
      freeMatchesLeft: user?.freeMatchesLeft ?? state.freeMatchesLeft,
      gender: MatchGender.mixed,
      superMatchEnabled: false,
      minAge: 18,
      maxAge: 60,
      isLoading: true,
      clearNotice: true,
    );

    final api = AppAuthApi();
    try {
      if (shouldResetFiltersForAppLaunch) {
        await api.updateMatchPreferences(
          token,
          genderCode: _genderCode(MatchGender.mixed),
          ageCode: _ageCodeFromRange(18, 60),
          superMatchEnabled: false,
        );
      }

      final summary = await api.fetchMatchCenter(token);
      state = state.copyWith(
        gemBalance: summary.gemBalance,
        freeMatchesLeft: summary.freeMatchesLeft,
        onlineCount: summary.onlineCount,
        waitingLikes: summary.waitingLikes,
        startCost: summary.startCost,
        gender: _genderFromCode(summary.filters.genderCode),
        superMatchEnabled: summary.filters.superMatchEnabled,
        minAge: _ageRangeFromCode(summary.filters.ageCode).$1,
        maxAge: _ageRangeFromCode(summary.filters.ageCode).$2,
        isLoading: false,
      );
    } catch (error) {
      state = state.copyWith(
        isLoading: false,
        notice: AppAuthErrorFormatter.messageFrom(error),
      );
    } finally {
      api.close();
    }
  }

  Future<void> setGender(MatchGender v) async {
    state = state.copyWith(gender: v, clearNotice: true);
    await _syncPreferences();
  }

  Future<void> toggleSuperMatch() async {
    state = state.copyWith(
      superMatchEnabled: !state.superMatchEnabled,
      clearNotice: true,
    );
    await _syncPreferences();
  }

  Future<void> setAgeRange({required int minAge, required int maxAge}) async {
    state = state.copyWith(minAge: minAge, maxAge: maxAge, clearNotice: true);
    await _syncPreferences();
  }

  Future<void> refreshSummary() async {
    final session = ref.read(appAuthProvider).asData?.value;
    final token = session?.token;
    if (token == null || token.trim().isEmpty) {
      return;
    }

    final api = AppAuthApi();
    try {
      final summary = await api.fetchMatchCenter(token);
      state = state.copyWith(
        gemBalance: summary.gemBalance,
        freeMatchesLeft: summary.freeMatchesLeft,
        startCost: summary.startCost,
        onlineCount: summary.onlineCount,
        waitingLikes: summary.waitingLikes,
        gender: _genderFromCode(summary.filters.genderCode),
        superMatchEnabled: summary.filters.superMatchEnabled,
        minAge: _ageRangeFromCode(summary.filters.ageCode).$1,
        maxAge: _ageRangeFromCode(summary.filters.ageCode).$2,
        clearNotice: true,
      );
    } catch (error) {
      state = state.copyWith(notice: AppAuthErrorFormatter.messageFrom(error));
    } finally {
      api.close();
    }
  }

  Future<AppMatchStartResult?> startMatch() async {
    final session = ref.read(appAuthProvider).asData?.value;
    final token = session?.token;
    if (token == null || token.trim().isEmpty) {
      state = state.copyWith(notice: 'Aktif oturum bulunamadi.');
      return null;
    }

    state = state.copyWith(isStarting: true, clearNotice: true);
    final api = AppAuthApi();
    try {
      final result = await api.startMatch(token);
      state = state.copyWith(
        isStarting: false,
        gemBalance: result.gemBalance,
        freeMatchesLeft: result.freeMatchesLeft,
        startCost: result.startCost,
        activeCandidate: result.candidate,
        clearActiveCandidate: result.candidate == null,
      );
      await ref.read(appAuthProvider.notifier).refreshCurrentUser();
      return result;
    } catch (error) {
      state = state.copyWith(
        isStarting: false,
        notice: AppAuthErrorFormatter.messageFrom(error),
      );
      return null;
    } finally {
      api.close();
    }
  }

  Future<AppDirectMatchConversationResult?>
  startConversationWithActiveCandidate({int? candidateId}) async {
    final session = ref.read(appAuthProvider).asData?.value;
    final token = session?.token;
    final id = candidateId ?? state.activeCandidate?.id;
    if (token == null || token.trim().isEmpty || id == null) {
      return null;
    }

    final api = AppAuthApi();
    try {
      final result = await api.startMatchConversation(token, userId: id);
      if (state.activeCandidate?.id == id) {
        state = state.copyWith(clearActiveCandidate: true);
      }
      return result;
    } catch (error) {
      state = state.copyWith(notice: AppAuthErrorFormatter.messageFrom(error));
      rethrow;
    } finally {
      api.close();
    }
  }

  Future<void> skipActiveCandidate({int? candidateId}) async {
    final session = ref.read(appAuthProvider).asData?.value;
    final token = session?.token;
    final id = candidateId ?? state.activeCandidate?.id;
    if (token == null || token.trim().isEmpty || id == null) {
      return;
    }

    final api = AppAuthApi();
    try {
      await api.skipMatchCandidate(token, userId: id);
      if (state.activeCandidate?.id == id) {
        state = state.copyWith(clearActiveCandidate: true);
      }
    } catch (error) {
      state = state.copyWith(notice: AppAuthErrorFormatter.messageFrom(error));
      rethrow;
    } finally {
      api.close();
    }
  }

  void clearCandidate() {
    state = state.copyWith(clearActiveCandidate: true);
  }

  Future<void> _syncPreferences() async {
    final session = ref.read(appAuthProvider).asData?.value;
    final token = session?.token;
    if (token == null || token.trim().isEmpty) {
      return;
    }

    final api = AppAuthApi();
    try {
      await api.updateMatchPreferences(
        token,
        genderCode: _genderCode(state.gender),
        ageCode: _ageCodeFromRange(state.minAge, state.maxAge),
        superMatchEnabled: state.superMatchEnabled,
      );
      final summary = await api.fetchMatchCenter(token);
      state = state.copyWith(
        gemBalance: summary.gemBalance,
        freeMatchesLeft: summary.freeMatchesLeft,
        startCost: summary.startCost,
        onlineCount: summary.onlineCount,
        waitingLikes: summary.waitingLikes,
        gender: _genderFromCode(summary.filters.genderCode),
        superMatchEnabled: summary.filters.superMatchEnabled,
        minAge: _ageRangeFromCode(summary.filters.ageCode).$1,
        maxAge: _ageRangeFromCode(summary.filters.ageCode).$2,
        clearNotice: true,
      );
    } catch (error) {
      state = state.copyWith(notice: AppAuthErrorFormatter.messageFrom(error));
    } finally {
      api.close();
    }
  }
}

MatchGender _genderFromCode(String? code) {
  return switch ((code ?? 'tum').trim().toLowerCase()) {
    'kadin' => MatchGender.female,
    'erkek' => MatchGender.male,
    _ => MatchGender.mixed,
  };
}

String _genderCode(MatchGender gender) {
  return switch (gender) {
    MatchGender.female => 'kadin',
    MatchGender.male => 'erkek',
    MatchGender.mixed => 'tum',
  };
}

(int, int) _ageRangeFromCode(String? code) {
  return switch ((code ?? 'tum').trim().toLowerCase()) {
    '18_25' => (18, 25),
    '26_35' => (26, 35),
    '36_ustu' => (36, 60),
    _ => (18, 60),
  };
}

String _ageCodeFromRange(int minAge, int maxAge) {
  if (minAge <= 18 && maxAge <= 25) {
    return '18_25';
  }
  if (minAge >= 26 && maxAge <= 35) {
    return '26_35';
  }
  if (minAge >= 36) {
    return '36_ustu';
  }
  return 'tum';
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

class _MatchAvatar extends StatelessWidget {
  final double size;
  final String label;
  final String? imageUrl;

  const _MatchAvatar({required this.size, required this.label, this.imageUrl});

  @override
  Widget build(BuildContext context) {
    final normalizedImage = imageUrl?.trim();

    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: const Color(0xFFF3F4F6),
        boxShadow: const [
          BoxShadow(
            color: Color(0x26000000),
            blurRadius: 30,
            offset: Offset(0, 8),
          ),
        ],
      ),
      clipBehavior: Clip.antiAlias,
      child: SizedBox.expand(
        child: normalizedImage != null && normalizedImage.isNotEmpty
            ? Image.network(
                normalizedImage,
                fit: BoxFit.cover,
                errorBuilder: (context, error, stackTrace) => _buildFallback(),
              )
            : _buildFallback(),
      ),
    );
  }

  Widget _buildFallback() {
    return const SizedBox.expand(
      child: DecoratedBox(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [Color(0xFFD7DCEB), Color(0xFFEEF1F7)],
          ),
        ),
        child: Center(child: Text('')),
      ),
    );
  }
}

class _MatchTopBar extends ConsumerWidget {
  final bool solidTitle;

  const _MatchTopBar({this.solidTitle = true});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final int gem = ref.watch(matchProvider.select((s) => s.gemBalance));
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

class _MatchModeTopBar extends ConsumerWidget {
  const _MatchModeTopBar();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final gem = ref.watch(matchProvider.select((s) => s.gemBalance));

    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 6),
      child: Row(
        children: [
          PressableScale(
            onTap: () => Navigator.of(context).maybePop(),
            scale: 0.9,
            child: const Padding(
              padding: EdgeInsets.all(4),
              child: Icon(
                CupertinoIcons.chevron_back,
                size: 21,
                color: AppColors.neutral950,
              ),
            ),
          ),
          const SizedBox(width: 8),
          const Expanded(
            child: Text(
              'Eslesme modu',
              style: TextStyle(
                fontFamily: AppFont.family,
                fontWeight: FontWeight.w800,
                fontSize: 17,
                color: AppColors.neutral950,
                letterSpacing: -0.3,
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
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: const Color(0xFFE9EBEF)),
        boxShadow: const [
          BoxShadow(
            color: Color(0x12000000),
            blurRadius: 20,
            offset: Offset(0, 8),
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
              fontSize: 14,
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
      constraints: const BoxConstraints(minHeight: 84),
      padding: const EdgeInsets.fromLTRB(18, 16, 18, 16),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: const Color(0xFFE9EBEF)),
        boxShadow: const [
          BoxShadow(
            color: Color(0x10000000),
            blurRadius: 20,
            offset: Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          ShaderMask(
            shaderCallback: (bounds) => const LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: [Color(0xFFE6A7FF), Color(0xFF8E7BFF)],
            ).createShader(bounds),
            child: Text(
              _formatGem(int.tryParse(value) ?? 0),
              style: const TextStyle(
                fontFamily: AppFont.family,
                fontWeight: FontWeight.w800,
                fontSize: 24,
                color: AppColors.white,
                height: 1.1,
              ),
            ),
          ),
          const SizedBox(height: 8),
          Text(
            label,
            style: const TextStyle(
              fontFamily: AppFont.family,
              fontSize: 14,
              color: AppColors.black,
              height: 1.2,
            ),
          ),
        ],
      ),
    );
  }
}

class _RadarAvatar extends ConsumerStatefulWidget {
  const _RadarAvatar();

  @override
  ConsumerState<_RadarAvatar> createState() => _RadarAvatarState();
}

class _RadarAvatarState extends ConsumerState<_RadarAvatar>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 4200),
    )..repeat();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final authUser = ref.watch(appAuthProvider).asData?.value?.user;
    final screen = MediaQuery.sizeOf(context);
    final radarSize = math.max(
      266.0,
      math.min(math.min(screen.width - 12, screen.height * 0.54), 410.0),
    );
    final portraitSize = radarSize * (160 / 344);

    return SizedBox(
      width: radarSize,
      height: radarSize,
      child: Stack(
        alignment: Alignment.center,
        children: [
          AnimatedBuilder(
            animation: _controller,
            builder: (context, _) => CustomPaint(
              size: Size.square(radarSize),
              painter: _RadarPainter(phase: _controller.value),
            ),
          ),
          _MatchAvatar(
            size: portraitSize,
            label: authUser?.displayName ?? 'Sen',
            imageUrl: authUser?.profileImageUrl,
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
    final scale = size.shortestSide / 300;
    final minRadius = 68.0 * scale;
    final maxRadius = 138.0 * scale;
    final radiusSpan = maxRadius - minRadius;

    const radarLineColor = Color.fromARGB(255, 205, 216, 230);

    for (final offset in const [0.0, 1 / 3, 2 / 3]) {
      final shiftedPhase = (phase + offset) % 1.0;
      final fadeInProgress = shiftedPhase < 0.16 ? shiftedPhase / 0.16 : 1.0;
      final fadeIn = Curves.easeOutCubic.transform(fadeInProgress);
      final fadeOutProgress = shiftedPhase <= 0.74
          ? 0.0
          : (shiftedPhase - 0.74) / 0.26;
      final fadeOut = 1 - Curves.easeInOutCubic.transform(fadeOutProgress);
      final pulseRadius = minRadius + (radiusSpan * shiftedPhase);
      final pulseOpacity = 0.095 + (fadeIn * fadeOut * 0.17);
      final pulsePaint = Paint()
        ..color = radarLineColor.withValues(alpha: pulseOpacity)
        ..style = PaintingStyle.stroke
        ..strokeWidth = (2.28 - (shiftedPhase * 0.3)) * scale;
      canvas.drawCircle(center, pulseRadius, pulsePaint);
    }
  }

  @override
  bool shouldRepaint(covariant _RadarPainter old) => old.phase != phase;
}

class _StartMatchButton extends StatefulWidget {
  final String label;
  final int? gemCost;
  final VoidCallback onTap;
  final bool whiteBorder;

  const _StartMatchButton({
    required this.label,
    required this.onTap,
    this.gemCost,
    this.whiteBorder = false,
  });

  @override
  State<_StartMatchButton> createState() => _StartMatchButtonState();
}

class _StartMatchButtonState extends State<_StartMatchButton>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 2600),
    )..repeat(reverse: true);
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return PressableScale(
      onTap: widget.onTap,
      child: AnimatedBuilder(
        animation: _controller,
        builder: (context, child) {
          final t = Curves.easeInOut.transform(_controller.value);
          return Container(
            height: 58,
            padding: const EdgeInsets.symmetric(horizontal: 16),
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment(-1.0 + t * 0.4, -0.2 + t * 0.4),
                end: Alignment(1.0 + t * 0.4, 0.2 + t * 0.4),
                colors: [
                  Color.lerp(
                    const Color(0xFFB879FF),
                    const Color(0xFFFF8EC5),
                    t,
                  )!,
                  Color.lerp(
                    const Color(0xFFFF8EC5),
                    const Color(0xFFB879FF),
                    t,
                  )!,
                ],
              ),
              borderRadius: BorderRadius.circular(999),
              border: widget.whiteBorder
                  ? Border.all(
                      color: AppColors.white.withValues(alpha: 0.9),
                      width: 1.4,
                    )
                  : null,
              boxShadow: [
                BoxShadow(
                  color: Color.lerp(
                    const Color(0x22CF86FF),
                    const Color(0x22FFB0D8),
                    t,
                  )!,
                  blurRadius: 22,
                  offset: const Offset(0, 10),
                ),
              ],
            ),
            child: child,
          );
        },
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text(
              widget.label,
              style: const TextStyle(
                fontFamily: AppFont.family,
                fontWeight: FontWeight.w800,
                fontSize: 16,
                color: AppColors.white,
              ),
            ),
            if (widget.gemCost != null) ...[
              const SizedBox(width: 12),
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 10,
                  vertical: 6,
                ),
                decoration: BoxDecoration(
                  color: AppColors.white.withValues(alpha: 0.94),
                  borderRadius: BorderRadius.circular(999),
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
                      '${widget.gemCost} tas',
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
        constraints: const BoxConstraints(minHeight: 52),
        padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 2),
        decoration: BoxDecoration(
          color: AppColors.white,
          borderRadius: BorderRadius.circular(999),
          border: Border.all(color: const Color(0xFFE9EBEF)),
          boxShadow: const [
            BoxShadow(
              color: Color(0x0F000000),
              blurRadius: 18,
              offset: Offset(0, 7),
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
                  fontSize: 16,
                  color: AppColors.neutral950,
                ),
              ),
            ),
            const Icon(
              CupertinoIcons.chevron_up,
              size: 18,
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

class MatchModeScreen extends ConsumerStatefulWidget {
  final MatchModeVariant variant;

  const MatchModeScreen({super.key, this.variant = MatchModeVariant.free});

  @override
  ConsumerState<MatchModeScreen> createState() => _MatchModeScreenState();
}

class _MatchModeScreenState extends ConsumerState<MatchModeScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(matchProvider.notifier).bootstrap();
    });
  }

  @override
  Widget build(BuildContext context) {
    final matchState = ref.watch(matchProvider);
    final isFree = matchState.freeMatchesLeft > 0;

    Future<void> showInfo(String message) async {
      await showCupertinoDialog<void>(
        context: context,
        builder: (dialogContext) => CupertinoAlertDialog(
          title: const Text('Bilgi'),
          content: Text(message),
          actions: [
            CupertinoDialogAction(
              onPressed: () => Navigator.of(dialogContext).pop(),
              child: const Text('Tamam'),
            ),
          ],
        ),
      );
    }

    Future<void> openGenderSheet() async {
      await showCupertinoModalPopup<void>(
        context: context,
        builder: (_) => const GenderFilterSheet(),
      );
      if (!context.mounted) {
        return;
      }
      await ref.read(matchProvider.notifier).refreshSummary();
    }

    Future<void> openAgeSheet() async {
      await showCupertinoModalPopup<void>(
        context: context,
        builder: (_) => const AgeFilterSheet(),
      );
      if (!context.mounted) {
        return;
      }
      await ref.read(matchProvider.notifier).refreshSummary();
    }

    Future<void> onStart() async {
      final theme = _themeForMatchState(matchState);

      final result = await ref.read(matchProvider.notifier).startMatch();
      if (!context.mounted || result == null) {
        return;
      }

      final navigator = Navigator.of(context);

      switch (result.status) {
        case AppMatchStartStatus.candidateFound:
          final candidate = result.candidate;
          if (candidate == null) {
            await showInfo('Uygun aday bilgisi okunamadi.');
            return;
          }
          await navigator.push(
            cupertinoRoute(MatchingScreen(theme: theme, candidate: candidate)),
          );
          break;
        case AppMatchStartStatus.insufficientCredits:
          showCupertinoModalPopup<void>(
            context: context,
            builder: (_) => PurchaseSheet(requiredCredits: result.startCost),
          );
          break;
        case AppMatchStartStatus.noCandidate:
          await showInfo(
            result.message ?? 'Su anda filtrelerine uygun eslesme bulunamadi.',
          );
          break;
      }
    }

    return CupertinoPageScaffold(
      backgroundColor: const Color(0xFFF7F8F9),
      child: DecoratedBox(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [Color(0xFFF7F8F9), Color(0xFFFFFFFF)],
          ),
        ),
        child: SafeArea(
          bottom: false,
          child: LayoutBuilder(
            builder: (context, constraints) {
              final sectionWidth = constraints.maxWidth - 32;
              final pairedWidth = sectionWidth > 340
                  ? (sectionWidth - 12) / 2
                  : sectionWidth;
              final radarHeight = math.min(
                math.max(constraints.maxHeight * 0.44, 300.0),
                430.0,
              );
              final bottomInset = MediaQuery.paddingOf(context).bottom + 18;

              return SizedBox(
                height: constraints.maxHeight,
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Column(
                      children: [
                        const _MatchModeTopBar(),
                        const SizedBox(height: 12),
                        Padding(
                          padding: const EdgeInsets.symmetric(horizontal: 16),
                          child: Wrap(
                            spacing: 12,
                            runSpacing: 12,
                            children: [
                              SizedBox(
                                width: pairedWidth,
                                child: _StatCard(
                                  value: '${matchState.waitingLikes}',
                                  label: 'Seni bekleyen kisi',
                                ),
                              ),
                              SizedBox(
                                width: pairedWidth,
                                child: _StatCard(
                                  value: '${matchState.onlineCount}',
                                  label: 'Kisi simdi cevrimici',
                                ),
                              ),
                            ],
                          ),
                        ),
                        SizedBox(height: constraints.maxHeight < 720 ? 16 : 24),
                        SizedBox(
                          height: radarHeight,
                          child: const Padding(
                            padding: EdgeInsets.symmetric(horizontal: 16),
                            child: Align(
                              alignment: Alignment.center,
                              child: _RadarAvatar(),
                            ),
                          ),
                        ),
                      ],
                    ),
                    Padding(
                      padding: EdgeInsets.fromLTRB(16, 8, 16, bottomInset),
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          if (matchState.notice != null &&
                              matchState.notice!.isNotEmpty) ...[
                            _MatchInfoCard(message: matchState.notice!),
                            const SizedBox(height: 14),
                          ],
                          _StartMatchButton(
                            label: matchState.isStarting
                                ? 'E\u015fle\u015fme aran\u0131yor...'
                                : 'E\u015fle\u015fmeyi Ba\u015flat!',
                            gemCost: isFree ? null : matchState.startCost,
                            onTap: matchState.isLoading || matchState.isStarting
                                ? () {}
                                : onStart,
                          ),
                          const SizedBox(height: 16),
                          Wrap(
                            spacing: 12,
                            runSpacing: 12,
                            children: [
                              SizedBox(
                                width: pairedWidth,
                                child: _FilterPill(
                                  leading: const _GenderSymbolLeading(),
                                  label: 'Cinsiyet',
                                  onTap: openGenderSheet,
                                ),
                              ),
                              SizedBox(
                                width: pairedWidth,
                                child: _FilterPill(
                                  leading: const Text(
                                    '\u{1F370}',
                                    style: TextStyle(fontSize: 18),
                                  ),
                                  label: 'Ya\u015f',
                                  onTap: openAgeSheet,
                                ),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              );
            },
          ),
        ),
      ),
    );
  }
}

class _AdaptiveMatchSheet extends StatelessWidget {
  final Widget child;

  const _AdaptiveMatchSheet({required this.child});

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
              color: AppColors.neutral100,
              borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
            ),
            child: SafeArea(
              top: false,
              child: SingleChildScrollView(
                physics: const BouncingScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
                child: child,
              ),
            ),
          ),
        );
      },
    );
  }
}

// ------ Sheet: Purchase --------------------------------------------------------

class PurchaseSheet extends ConsumerStatefulWidget {
  final int requiredCredits;

  const PurchaseSheet({super.key, this.requiredCredits = 8});

  @override
  ConsumerState<PurchaseSheet> createState() => _PurchaseSheetState();
}

class _PurchaseSheetState extends ConsumerState<PurchaseSheet> {
  int _selected = 1;
  final StorePurchaseService _purchaseService = StorePurchaseService();
  bool _isPurchasing = false;

  void _openPurchaseResult({
    required PaymentResultTone tone,
    required String badge,
    required String title,
    required String subtitle,
    required AppCreditPackage selectedPackage,
  }) {
    final rootNavigator = Navigator.of(context, rootNavigator: true);
    final l10n = AppLocalizations.of(context)!;

    showCupertinoModalPopup<void>(
      context: rootNavigator.context,
      builder: (_) => PaymentResultSheet(
        tone: tone,
        badge: badge,
        title: title,
        subtitle: subtitle,
        productLabel: '${selectedPackage.credits} Kredi',
        amountLabel: selectedPackage.displayPrice,
        statusLabel: tone == PaymentResultTone.success
            ? 'Kredi hesaba tanimlandi'
            : 'Secilen paket hazir',
        primaryLabel: l10n.commonDone,
      ),
    );
  }

  Future<void> _purchaseCredits(AppCreditPackage selectedPackage) async {
    final authState = ref.read(appAuthProvider).asData?.value;
    final token = authState?.token;

    if (token == null || token.trim().isEmpty) {
      _openPurchaseResult(
        tone: PaymentResultTone.failure,
        badge: 'OTURUM GEREKLI',
        title: 'Jeton satin alimi baslatilamadi',
        subtitle: 'Devam etmek icin once oturum acman gerekiyor.',
        selectedPackage: selectedPackage,
      );
      return;
    }

    setState(() => _isPurchasing = true);
    final result = await _purchaseService.purchase(
      token: token,
      productCode: selectedPackage.storeProductCode ?? '',
      kind: StorePurchaseKind.creditPack,
      amount: selectedPackage.price,
      currency: selectedPackage.currency,
    );
    if (!mounted) {
      return;
    }

    setState(() => _isPurchasing = false);

    if (result.isSuccess) {
      await ref.read(appAuthProvider.notifier).refreshCurrentUser();
      if (!mounted) {
        return;
      }
      Navigator.of(context, rootNavigator: true).pop();
      _openPurchaseResult(
        tone: PaymentResultTone.success,
        badge: 'ODEME BASARILI',
        title: 'Kredi paketin hazir',
        subtitle: 'Krediler hesabina eklendi. Eslesmeye devam edebilirsin.',
        selectedPackage: selectedPackage,
      );
      return;
    }

    if (result.status == StorePurchaseStatus.cancelled) {
      return;
    }

    _openPurchaseResult(
      tone: PaymentResultTone.failure,
      badge: 'ODEME BASARISIZ',
      title: 'Jeton satin alimi tamamlanamadi',
      subtitle: result.message,
      selectedPackage: selectedPackage,
    );
  }

  @override
  Widget build(BuildContext context) {
    final packagesAsync = ref.watch(appCreditPackagesProvider);
    final packages = packagesAsync.asData?.value ?? const <AppCreditPackage>[];
    var selectedIndex = _selected;
    if (packages.isNotEmpty && selectedIndex >= packages.length) {
      final recommendedIndex = packages.indexWhere(
        (package) => package.isRecommended,
      );
      selectedIndex = recommendedIndex >= 0 ? recommendedIndex : 0;
    }
    final selectedPackage = packages.isEmpty ? null : packages[selectedIndex];

    return _AdaptiveMatchSheet(
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
          Text(
            'Devam etmek icin ${widget.requiredCredits} tas gerekli.',
            style: const TextStyle(
              fontFamily: AppFont.family,
              fontSize: 14,
              color: AppColors.neutral600,
            ),
          ),
          const SizedBox(height: 20),
          if (packagesAsync.isLoading && packages.isEmpty)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 32),
              child: CupertinoActivityIndicator(),
            )
          else if (packagesAsync.hasError && packages.isEmpty)
            const _MatchInfoCard(
              message: 'Kredi paketleri su anda yuklenemiyor.',
            )
          else if (packages.isEmpty)
            const _MatchInfoCard(
              message: 'Su anda satin alinabilir kredi paketi bulunmuyor.',
            )
          else
            ...List.generate(packages.length, (index) {
              final package = packages[index];
              return Padding(
                padding: EdgeInsets.only(
                  bottom: index == packages.length - 1 ? 0 : 10,
                ),
                child: _CreditOption(
                  title: '${package.credits} Kredi',
                  subtitle: package.badgeLabel,
                  price: package.displayPrice,
                  selected: selectedIndex == index,
                  popular: package.isRecommended,
                  onTap: () => setState(() => _selected = index),
                ),
              );
            }),
          const SizedBox(height: 20),
          GradientButton(
            label: _isPurchasing ? 'Satin alma isleniyor...' : 'Satin Al',
            onTap: _isPurchasing || selectedPackage == null
                ? null
                : () => _purchaseCredits(selectedPackage),
          ),
          const SizedBox(height: 10),
          const Center(
            child: Text(
              'Odeme magazada tamamlandiginda paket otomatik olarak hesabina tanimlanir.',
              textAlign: TextAlign.center,
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
  final String title;
  final String? subtitle;
  final String price;
  final bool selected;
  final bool popular;
  final VoidCallback onTap;

  const _CreditOption({
    required this.title,
    this.subtitle,
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
                              text: title.split(' ').first,
                              style: const TextStyle(
                                fontWeight: FontWeight.w800,
                                fontSize: 18,
                              ),
                            ),
                            TextSpan(
                              text: ' ${title.split(' ').skip(1).join(' ')}',
                              style: TextStyle(
                                fontWeight: FontWeight.w500,
                                fontSize: 14,
                              ),
                            ),
                          ],
                        ),
                      ),
                      if (subtitle != null) ...[
                        const SizedBox(height: 2),
                        Text(
                          subtitle!,
                          style: const TextStyle(
                            fontFamily: AppFont.family,
                            fontSize: 12,
                            color: AppColors.neutral600,
                          ),
                        ),
                      ],
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

class _MatchInfoCard extends StatelessWidget {
  final String message;

  const _MatchInfoCard({required this.message});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(18),
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

// ------ Sheet: Gender Filter ---------------------------------------------------

class GenderFilterSheet extends ConsumerWidget {
  const GenderFilterSheet({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final gender = ref.watch(matchProvider.select((s) => s.gender));
    final notifier = ref.read(matchProvider.notifier);

    return _AdaptiveMatchSheet(
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
          LayoutBuilder(
            builder: (context, constraints) {
              final cardWidth = (constraints.maxWidth - 16) / 3;

              return Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  SizedBox(
                    width: cardWidth,
                    child: _GenderCard(
                      label: 'T\u00fcm\u00fc',
                      asset: 'assets/images/icon_filter_female.png',
                      secondaryAsset: 'assets/images/icon_filter_male.png',
                      selected: gender == MatchGender.mixed,
                      onTap: () => notifier.setGender(MatchGender.mixed),
                    ),
                  ),
                  SizedBox(
                    width: cardWidth,
                    child: _GenderCard(
                      label: 'Kadinlar',
                      asset: 'assets/images/icon_filter_female.png',
                      selected: gender == MatchGender.female,
                      onTap: () => notifier.setGender(MatchGender.female),
                    ),
                  ),
                  SizedBox(
                    width: cardWidth,
                    child: _GenderCard(
                      label: 'Erkekler',
                      asset: 'assets/images/icon_filter_male.png',
                      selected: gender == MatchGender.male,
                      onTap: () => notifier.setGender(MatchGender.male),
                    ),
                  ),
                ],
              );
            },
          ),
          const SizedBox(height: 24),
          GradientButton(
            label: 'Tercihleri Uygula',
            onTap: () => Navigator.of(context).maybePop(),
          ),
        ],
      ),
    );
  }
}

class AgeFilterSheet extends ConsumerStatefulWidget {
  const AgeFilterSheet({super.key});

  @override
  ConsumerState<AgeFilterSheet> createState() => _AgeFilterSheetState();
}

class _AgeFilterSheetState extends ConsumerState<AgeFilterSheet> {
  late int _minAge;
  late int _maxAge;
  bool _isApplying = false;

  @override
  void initState() {
    super.initState();
    final state = ref.read(matchProvider);
    _minAge = state.minAge;
    _maxAge = state.maxAge;
  }

  @override
  Widget build(BuildContext context) {
    final notifier = ref.read(matchProvider.notifier);
    final selectedCode = _ageCodeFromRange(_minAge, _maxAge);
    final ageOptions = <({String code, String label, int minAge, int maxAge})>[
      (
        code: 'tum',
        label: 'Dengeli ya\u015f e\u015fle\u015fmesi',
        minAge: 18,
        maxAge: 60,
      ),
      (code: '18_25', label: '18 - 25', minAge: 18, maxAge: 25),
      (code: '26_35', label: '26 - 35', minAge: 26, maxAge: 35),
      (code: '36_ustu', label: '36 ve \u00fcst\u00fc', minAge: 36, maxAge: 60),
    ];

    return _AdaptiveMatchSheet(
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
            'Ya\u015f Filtresi',
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
              'Ya\u015f tercihiniz',
              style: TextStyle(
                fontFamily: AppFont.family,
                fontSize: 12,
                color: AppColors.neutral600,
              ),
            ),
          ),
          const SizedBox(height: 10),
          Container(
            decoration: BoxDecoration(
              color: AppColors.white,
              borderRadius: BorderRadius.circular(22),
            ),
            child: Column(
              children: [
                for (var index = 0; index < ageOptions.length; index++) ...[
                  _AgeOptionRow(
                    label: ageOptions[index].label,
                    selected: selectedCode == ageOptions[index].code,
                    onTap: () {
                      setState(() {
                        _minAge = ageOptions[index].minAge;
                        _maxAge = ageOptions[index].maxAge;
                      });
                    },
                  ),
                  if (index != ageOptions.length - 1)
                    const Padding(
                      padding: EdgeInsets.only(left: 58),
                      child: SizedBox(
                        height: 1,
                        child: ColoredBox(color: Color(0xFFF0F0F3)),
                      ),
                    ),
                ],
              ],
            ),
          ),
          const SizedBox(height: 24),
          GradientButton(
            label: _isApplying ? 'Guncelleniyor...' : 'Ya\u015f\u0131 Uygula',
            onTap: _isApplying
                ? null
                : () async {
                    setState(() => _isApplying = true);
                    await notifier.setAgeRange(
                      minAge: _minAge,
                      maxAge: _maxAge,
                    );
                    if (!context.mounted) {
                      return;
                    }
                    Navigator.of(context).maybePop();
                  },
          ),
        ],
      ),
    );
  }
}

class _AgeOptionRow extends StatelessWidget {
  final String label;
  final bool selected;
  final VoidCallback onTap;

  const _AgeOptionRow({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return PressableScale(
      onTap: onTap,
      scale: 0.99,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 17),
        child: Row(
          children: [
            AnimatedContainer(
              duration: const Duration(milliseconds: 180),
              width: 28,
              height: 28,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: selected ? const Color(0xFF3B82F6) : AppColors.white,
                border: Border.all(
                  color: selected
                      ? const Color(0xFF3B82F6)
                      : const Color(0xFFD9DCE2),
                  width: 1.8,
                ),
              ),
              child: selected
                  ? const Icon(
                      CupertinoIcons.check_mark,
                      size: 16,
                      color: AppColors.white,
                    )
                  : null,
            ),
            const SizedBox(width: 16),
            Expanded(
              child: Text(
                label,
                style: const TextStyle(
                  fontFamily: AppFont.family,
                  fontWeight: FontWeight.w500,
                  fontSize: 15,
                  color: AppColors.neutral950,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _GenderCard extends StatelessWidget {
  final String label;
  final String asset;
  final String? secondaryAsset;
  final bool selected;
  final VoidCallback onTap;

  const _GenderCard({
    required this.label,
    required this.asset,
    this.secondaryAsset,
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
        padding: const EdgeInsets.fromLTRB(14, 14, 14, 16),
        decoration: BoxDecoration(
          color: selected ? const Color(0xFFEAF1FF) : AppColors.white,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: selected ? const Color(0xFF3B82F6) : const Color(0xFFF0F0F3),
            width: selected ? 1.5 : 1,
          ),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            SizedBox(
              height: 60,
              child: Center(
                child: secondaryAsset == null
                    ? Image.asset(asset, width: 52, height: 52)
                    : SizedBox(
                        width: 70,
                        height: 54,
                        child: Stack(
                          alignment: Alignment.center,
                          children: [
                            Positioned(
                              left: 4,
                              child: Image.asset(asset, width: 42, height: 42),
                            ),
                            Positioned(
                              right: 4,
                              child: Image.asset(
                                secondaryAsset!,
                                width: 42,
                                height: 42,
                              ),
                            ),
                          ],
                        ),
                      ),
              ),
            ),
            const SizedBox(height: 10),
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

// ------ Screen: Matching (loading) --------------------------------------------

class MatchingScreen extends ConsumerStatefulWidget {
  final bool withAd;
  final MatchFoundTheme theme;
  final AppMatchCandidate candidate;

  const MatchingScreen({
    super.key,
    this.withAd = false,
    this.theme = MatchFoundTheme.normal,
    required this.candidate,
  });

  @override
  ConsumerState<MatchingScreen> createState() => _MatchingScreenState();
}

class _MatchingScreenState extends ConsumerState<MatchingScreen> {
  bool _adVisible = true;
  Timer? _resultTimer;
  Timer? _countdownTimer;
  late int _secondsRemaining;

  @override
  void initState() {
    super.initState();
    _secondsRemaining = ((_resultDelay.inMilliseconds + 999) ~/ 1000).clamp(
      1,
      99,
    );
    _resultTimer = Timer(_resultDelay, _openResult);
    _countdownTimer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (!mounted || _secondsRemaining <= 1) {
        timer.cancel();
        return;
      }
      setState(() => _secondsRemaining--);
    });
  }

  @override
  void dispose() {
    _resultTimer?.cancel();
    _countdownTimer?.cancel();
    super.dispose();
  }

  Duration get _resultDelay => widget.withAd
      ? const Duration(seconds: 3)
      : const Duration(milliseconds: 2200);

  void _openResult() {
    if (!mounted) {
      return;
    }

    Navigator.of(context).pushReplacement(
      cupertinoRoute(
        MatchFoundScreen(theme: widget.theme, candidate: widget.candidate),
      ),
    );
  }

  Future<void> _showInfo(String message) async {
    await showCupertinoDialog<void>(
      context: context,
      builder: (dialogContext) => CupertinoAlertDialog(
        title: const Text('Bilgi'),
        content: Text(message),
        actions: [
          CupertinoDialogAction(
            onPressed: () => Navigator.of(dialogContext).pop(),
            child: const Text('Tamam'),
          ),
        ],
      ),
    );
  }

  Future<void> _repeatMatch() async {
    _resultTimer?.cancel();
    _countdownTimer?.cancel();

    final result = await ref.read(matchProvider.notifier).startMatch();
    if (!mounted || result == null) {
      return;
    }

    switch (result.status) {
      case AppMatchStartStatus.candidateFound:
        final candidate = result.candidate;
        if (candidate == null) {
          await _showInfo('Uygun aday bilgisi okunamadi.');
          return;
        }
        Navigator.of(context).pushReplacement(
          cupertinoRoute(
            MatchingScreen(
              theme: _themeForMatchState(ref.read(matchProvider)),
              candidate: candidate,
            ),
          ),
        );
        break;
      case AppMatchStartStatus.insufficientCredits:
        await showCupertinoModalPopup<void>(
          context: context,
          builder: (_) => PurchaseSheet(requiredCredits: result.startCost),
        );
        break;
      case AppMatchStartStatus.noCandidate:
        await _showInfo(
          result.message ?? 'Su anda filtrelerine uygun eslesme bulunamadi.',
        );
        break;
    }
  }

  void _stopMatching() {
    _resultTimer?.cancel();
    _countdownTimer?.cancel();
    ref.read(matchProvider.notifier).clearCandidate();
    Navigator.of(context).maybePop();
  }

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
    return LayoutBuilder(
      builder: (context, constraints) {
        final compact = constraints.maxHeight < 640;

        return SingleChildScrollView(
          physics: const BouncingScrollPhysics(),
          child: ConstrainedBox(
            constraints: BoxConstraints(minHeight: constraints.maxHeight),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Padding(
                    padding: EdgeInsets.only(top: compact ? 32 : 56),
                    child: Column(
                      children: [
                        Text(
                          '\u{1F61D}',
                          style: TextStyle(
                            fontSize: compact ? 52 : 64,
                            height: 1.0,
                          ),
                        ),
                        SizedBox(height: compact ? 18 : 24),
                        Text(
                          'Senin icin en uygunu\nbuluyoruz...',
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            fontFamily: AppFont.family,
                            fontWeight: FontWeight.w800,
                            fontSize: compact ? 21 : 24,
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
                      ],
                    ),
                  ),
                  TweenAnimationBuilder<double>(
                    tween: Tween(begin: 0.2, end: 1),
                    duration: _resultDelay,
                    builder: (context, value, _) {
                      return Column(
                        children: [
                          Container(
                            height: 8,
                            decoration: BoxDecoration(
                              color: AppColors.white.withValues(alpha: 0.45),
                              borderRadius: BorderRadius.circular(999),
                            ),
                            child: Align(
                              alignment: Alignment.centerLeft,
                              child: FractionallySizedBox(
                                widthFactor: value,
                                child: Container(
                                  decoration: BoxDecoration(
                                    color: const Color(0xFFF5A524),
                                    borderRadius: BorderRadius.circular(999),
                                  ),
                                ),
                              ),
                            ),
                          ),
                        ],
                      );
                    },
                  ),
                  Padding(
                    padding: const EdgeInsets.only(bottom: 16),
                    child: _SecondaryActionButton(
                      label: 'Durdur',
                      onTap: _stopMatching,
                    ),
                  ),
                ],
              ),
            ),
          ),
        );
      },
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
          child: _SecondaryActionButton(label: 'Tekrar', onTap: _repeatMatch),
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
            child: DecoratedBox(
              decoration: const BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                  colors: [Color(0xFFF3E8FF), Color(0xFFFFF1F2)],
                ),
              ),
              child: Padding(
                padding: const EdgeInsets.fromLTRB(20, 20, 20, 24),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisAlignment: MainAxisAlignment.end,
                  children: const [
                    Text(
                      'Reklamli devam',
                      style: TextStyle(
                        fontFamily: AppFont.family,
                        fontWeight: FontWeight.w800,
                        fontSize: 24,
                        color: AppColors.zinc900,
                      ),
                    ),
                    SizedBox(height: 8),
                    Text(
                      'Kesfete ara vermeden devam etmek icin bu alanda gosterilen reklami tamamlayabilirsin.',
                      style: TextStyle(
                        fontFamily: AppFont.family,
                        fontSize: 14,
                        height: 1.4,
                        color: AppColors.neutral600,
                      ),
                    ),
                  ],
                ),
              ),
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

class _SecondaryActionButton extends StatelessWidget {
  final String label;
  final VoidCallback onTap;

  const _SecondaryActionButton({required this.label, required this.onTap});

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
        child: Text(
          label,
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

class MatchFoundScreen extends ConsumerStatefulWidget {
  final MatchFoundTheme theme;
  final AppMatchCandidate? candidate;

  const MatchFoundScreen({
    super.key,
    this.theme = MatchFoundTheme.normal,
    this.candidate,
  });

  @override
  ConsumerState<MatchFoundScreen> createState() => _MatchFoundScreenState();
}

class _MatchFoundScreenState extends ConsumerState<MatchFoundScreen> {
  Timer? _repeatTimer;
  Timer? _countdownTimer;
  int _secondsRemaining = 6;
  bool _isSubmitting = false;

  @override
  void initState() {
    super.initState();
    _startRepeatCountdown();
  }

  @override
  void dispose() {
    _repeatTimer?.cancel();
    _countdownTimer?.cancel();
    super.dispose();
  }

  void _startRepeatCountdown() {
    _repeatTimer?.cancel();
    _countdownTimer?.cancel();
    _secondsRemaining = 6;

    _repeatTimer = Timer(const Duration(seconds: 6), () {
      unawaited(_repeatMatch(autoTriggered: true));
    });
    _countdownTimer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (!mounted || _secondsRemaining <= 1) {
        timer.cancel();
        return;
      }
      setState(() => _secondsRemaining--);
    });
  }

  void _stopRepeatCountdown() {
    _repeatTimer?.cancel();
    _countdownTimer?.cancel();
  }

  Future<AppConversationPreview?> _findConversationForCandidate({
    required String token,
    required int currentUserId,
    required int peerId,
    int? matchId,
  }) async {
    final api = AppAuthApi();
    try {
      final conversations = await api.fetchConversations(
        token,
        currentUserId: currentUserId,
      );

      for (final item in conversations) {
        if (matchId != null && item.matchId == matchId) {
          return item;
        }
      }

      for (final item in conversations) {
        if (item.peerId == peerId) {
          return item;
        }
      }

      return null;
    } finally {
      api.close();
    }
  }

  Future<void> _showInfo(String message) async {
    await showCupertinoDialog<void>(
      context: context,
      builder: (dialogContext) => CupertinoAlertDialog(
        title: const Text('Bilgi'),
        content: Text(message),
        actions: [
          CupertinoDialogAction(
            onPressed: () => Navigator.of(dialogContext).pop(),
            child: const Text('Tamam'),
          ),
        ],
      ),
    );
  }

  Future<void> _repeatMatch({bool autoTriggered = false}) async {
    if (_isSubmitting) {
      return;
    }

    _stopRepeatCountdown();
    setState(() => _isSubmitting = true);

    final notifier = ref.read(matchProvider.notifier);
    if (!autoTriggered) {
      try {
        await notifier.skipActiveCandidate(candidateId: widget.candidate?.id);
      } catch (_) {
        if (!mounted) {
          return;
        }
        setState(() => _isSubmitting = false);
        _startRepeatCountdown();
        return;
      }
    }

    final result = await notifier.startMatch();
    if (!mounted) {
      return;
    }

    setState(() => _isSubmitting = false);

    if (result == null) {
      _startRepeatCountdown();
      return;
    }

    switch (result.status) {
      case AppMatchStartStatus.candidateFound:
        final candidate = result.candidate;
        if (candidate == null) {
          await _showInfo('Uygun aday bilgisi okunamadi.');
          _startRepeatCountdown();
          return;
        }
        Navigator.of(context).pushReplacement(
          cupertinoRoute(
            MatchingScreen(
              theme: _themeForMatchState(ref.read(matchProvider)),
              candidate: candidate,
            ),
          ),
        );
        break;
      case AppMatchStartStatus.insufficientCredits:
        await showCupertinoModalPopup<void>(
          context: context,
          builder: (_) => PurchaseSheet(requiredCredits: result.startCost),
        );
        if (mounted && !autoTriggered) {
          _startRepeatCountdown();
        }
        break;
      case AppMatchStartStatus.noCandidate:
        await _showInfo(
          result.message ?? 'Su anda filtrelerine uygun eslesme bulunamadi.',
        );
        if (mounted && !autoTriggered) {
          _startRepeatCountdown();
        }
        break;
    }
  }

  Future<void> _messageCandidate() async {
    if (_isSubmitting) {
      return;
    }

    final session = ref.read(appAuthProvider).asData?.value;
    final token = session?.token;
    final currentUserId = session?.user?.id;
    final AppMatchCandidate? candidate =
        widget.candidate ?? ref.read(matchProvider).activeCandidate;

    if (token == null ||
        token.trim().isEmpty ||
        currentUserId == null ||
        candidate == null) {
      await _showInfo('Sohbet acilamadi. Lutfen tekrar dene.');
      return;
    }

    _stopRepeatCountdown();
    setState(() => _isSubmitting = true);

    AppConversationPreview? conversationToOpen;
    String? messageToShow;
    bool openHomeAfterMessage = false;
    bool restartCountdown = false;

    try {
      final result = await ref
          .read(matchProvider.notifier)
          .startConversationWithActiveCandidate(candidateId: candidate.id);
      if (!mounted || result == null) {
        restartCountdown = true;
        return;
      }

      conversationToOpen = await _findConversationForCandidate(
        token: token,
        currentUserId: currentUserId,
        peerId: candidate.id,
        matchId: result.matchId,
      );

      if (conversationToOpen == null) {
        messageToShow =
            result.message ??
            'Sohbet hazir ama listeye duserken kisa bir gecikme oldu. Mesajlar ekranina yonlendiriliyorsun.';
        openHomeAfterMessage = true;
      }
    } catch (error) {
      messageToShow = AppAuthErrorFormatter.messageFrom(error);
      restartCountdown = true;
    } finally {
      if (mounted) {
        setState(() => _isSubmitting = false);
      }
    }

    if (!mounted) {
      return;
    }

    if (conversationToOpen != null) {
      Navigator.of(context).pushAndRemoveUntil(
        cupertinoRoute(
          ChatScreen(
            mode: ChatScreenMode.messages,
            conversation: conversationToOpen,
          ),
        ),
        (route) => route.isFirst,
      );
      return;
    }

    final visibleMessage = messageToShow?.trim();
    if (visibleMessage != null && visibleMessage.isNotEmpty) {
      await _showInfo(visibleMessage);
      if (!mounted) {
        return;
      }
    }

    if (openHomeAfterMessage) {
      Navigator.of(context).pushAndRemoveUntil(
        cupertinoRoute(const HomeScreen(mode: HomeMode.list)),
        (route) => route.isFirst,
      );
      return;
    }

    if (restartCountdown) {
      _startRepeatCountdown();
    }
  }

  @override
  Widget build(BuildContext context) {
    final authUser = ref.watch(appAuthProvider).asData?.value?.user;
    final AppMatchCandidate? candidate = widget.candidate;
    final isSuper = widget.theme == MatchFoundTheme.superMatch;
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
            child: LayoutBuilder(
              builder: (context, constraints) {
                final compact = constraints.maxHeight < 720;

                return SingleChildScrollView(
                  physics: const BouncingScrollPhysics(),
                  child: ConstrainedBox(
                    constraints: BoxConstraints(
                      minHeight: constraints.maxHeight,
                    ),
                    child: Padding(
                      padding: const EdgeInsets.only(bottom: 24),
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const _MatchTopBar(solidTitle: false),
                          Padding(
                            padding: EdgeInsets.symmetric(
                              horizontal: 24,
                              vertical: compact ? 20 : 32,
                            ),
                            child: Column(
                              children: [
                                _MatchPairAvatars(candidate: candidate),
                                const SizedBox(height: 20),
                                Text(
                                  candidate?.displayName ??
                                      authUser?.displayName ??
                                      'Yeni eslesme',
                                  textAlign: TextAlign.center,
                                  style: const TextStyle(
                                    fontFamily: AppFont.family,
                                    fontWeight: FontWeight.w800,
                                    fontSize: 26,
                                    color: AppColors.white,
                                    letterSpacing: -0.5,
                                  ),
                                ),
                                SizedBox(height: compact ? 20 : 32),
                                Text(
                                  isSuper ? 'Super\nEslesme' : 'Eslesme',
                                  textAlign: TextAlign.center,
                                  style: TextStyle(
                                    fontFamily: AppFont.family,
                                    fontWeight: FontWeight.w800,
                                    fontSize: compact ? 36 : 44,
                                    height: 1.05,
                                    color: AppColors.white,
                                    letterSpacing: -1.5,
                                  ),
                                ),
                                const SizedBox(height: 16),
                                const Text(
                                  'Bu aday senin icin hazir. Mesaj atabilir ya da gec diyerek yeni bir aday arayabilirsin.',
                                  textAlign: TextAlign.center,
                                  style: TextStyle(
                                    fontFamily: AppFont.family,
                                    fontSize: 16,
                                    color: AppColors.white,
                                  ),
                                ),
                                SizedBox(height: compact ? 22 : 34),
                                _MatchCountdownCircle(
                                  secondsRemaining: _secondsRemaining,
                                ),
                              ],
                            ),
                          ),
                          Padding(
                            padding: const EdgeInsets.symmetric(horizontal: 24),
                            child: Column(
                              children: [
                                _StartMatchButton(
                                  label: _isSubmitting
                                      ? 'Isleniyor...'
                                      : 'Mesaj At',
                                  onTap: _isSubmitting
                                      ? () {}
                                      : _messageCandidate,
                                  whiteBorder: true,
                                ),
                                const SizedBox(height: 12),
                                _SecondaryActionButton(
                                  label: _isSubmitting ? 'Isleniyor...' : 'Gec',
                                  onTap: _isSubmitting ? () {} : _repeatMatch,
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

class _MatchCountdownCircle extends StatelessWidget {
  final int secondsRemaining;

  const _MatchCountdownCircle({required this.secondsRemaining});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 118,
      height: 118,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: AppColors.white.withValues(alpha: 0.16),
        border: Border.all(
          color: AppColors.white.withValues(alpha: 0.24),
          width: 1.4,
        ),
        boxShadow: const [
          BoxShadow(
            color: Color(0x22000000),
            blurRadius: 22,
            offset: Offset(0, 10),
          ),
        ],
      ),
      alignment: Alignment.center,
      child: Text(
        '${secondsRemaining}s',
        style: const TextStyle(
          fontFamily: AppFont.family,
          fontWeight: FontWeight.w900,
          fontSize: 34,
          color: AppColors.white,
          letterSpacing: -1.2,
        ),
      ),
    );
  }
}

class _MatchPairAvatars extends ConsumerWidget {
  final AppMatchCandidate? candidate;

  const _MatchPairAvatars({this.candidate});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final authUser = ref.watch(appAuthProvider).asData?.value?.user;
    final width = math.min(MediaQuery.sizeOf(context).width * 0.58, 230.0);
    final avatarSize = width * (130 / 230);
    final overlap = width * (100 / 230);

    return SizedBox(
      width: width,
      height: avatarSize,
      child: Stack(
        children: [
          Positioned(
            left: 0,
            child: _MatchAvatar(
              size: avatarSize,
              label: authUser?.displayName ?? 'Sen',
              imageUrl: authUser?.profileImageUrl,
            ),
          ),
          Positioned(
            left: overlap,
            child: _MatchAvatar(
              size: avatarSize,
              label: candidate?.displayName ?? 'Eslesme',
              imageUrl: candidate?.primaryImageUrl,
            ),
          ),
        ],
      ),
    );
  }
}

// =============================================================================
// Message module ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â sohbet ekrani + profil + hediye/sikayet/engelle sheet'leri
