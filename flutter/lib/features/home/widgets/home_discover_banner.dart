import 'package:magmug/app_core.dart';
import 'package:magmug/features/home/widgets/home_avatar.dart';

class HomeDiscoverBanner extends StatefulWidget {
  final VoidCallback? onTap;
  final List<AppMatchCandidate> profiles;

  const HomeDiscoverBanner({super.key, this.onTap, this.profiles = const []});

  @override
  State<HomeDiscoverBanner> createState() => _HomeDiscoverBannerState();
}

class _HomeDiscoverBannerState extends State<HomeDiscoverBanner>
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
      onTap: widget.onTap ?? () {},
      scale: 0.98,
      child: LayoutBuilder(
        builder: (context, constraints) {
          final compact = constraints.maxWidth < 320;
          final height = compact ? 64.0 : 72.0;
          final radius = compact ? 24.0 : 50.0;

          return Container(
            height: height,
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(radius),
              boxShadow: const [
                BoxShadow(
                  color: Color(0x09000000),
                  blurRadius: 18,
                  offset: Offset(0, 8),
                ),
                BoxShadow(
                  color: Color(0x0A3E2A72),
                  blurRadius: 14,
                  offset: Offset(0, 3),
                ),
              ],
            ),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(radius),
              child: Stack(
                fit: StackFit.expand,
                children: [
                  AnimatedBuilder(
                    animation: _controller,
                    builder: (context, child) {
                      final t = Curves.easeInOut.transform(_controller.value);
                      return Stack(
                        fit: StackFit.expand,
                        children: [
                          DecoratedBox(
                            decoration: BoxDecoration(
                              gradient: LinearGradient(
                                begin: Alignment(
                                  -1.0 + t * 0.4,
                                  -0.2 + t * 0.4,
                                ),
                                end: Alignment(1.0 + t * 0.4, 0.2 + t * 0.4),
                                colors: [
                                  Color.lerp(
                                    const Color(0xFFB879FF),
                                    const Color(0xFFFF8EC5),
                                    t,
                                  )!,
                                  Color.lerp(
                                    const Color(0xFFD07AF6),
                                    const Color(0xFFB879FF),
                                    t,
                                  )!,
                                  Color.lerp(
                                    const Color(0xFFFF8EC5),
                                    const Color(0xFFB879FF),
                                    t,
                                  )!,
                                ],
                              ),
                            ),
                          ),
                          Positioned(
                            left: -26 + (t * 78),
                            top: -18,
                            child: Container(
                              width: 118,
                              height: 118,
                              decoration: const BoxDecoration(
                                shape: BoxShape.circle,
                                gradient: RadialGradient(
                                  colors: [
                                    Color(0x3DFFE2F0),
                                    Color(0x00FFE2F0),
                                  ],
                                ),
                              ),
                            ),
                          ),
                          Positioned(
                            right: -40 + (t * 68),
                            bottom: -34,
                            child: Container(
                              width: 128,
                              height: 128,
                              decoration: const BoxDecoration(
                                shape: BoxShape.circle,
                                gradient: RadialGradient(
                                  colors: [
                                    Color(0x33FFD0E4),
                                    Color(0x00FFD0E4),
                                  ],
                                ),
                              ),
                            ),
                          ),
                          Positioned(
                            left: -30 + (t * 210),
                            top: -14,
                            bottom: -14,
                            child: Transform.rotate(
                              angle: -0.26,
                              child: Container(
                                width: 84,
                                decoration: const BoxDecoration(
                                  gradient: LinearGradient(
                                    begin: Alignment.topCenter,
                                    end: Alignment.bottomCenter,
                                    colors: [
                                      Color(0x00FFFFFF),
                                      Color(0x24FFF4DE),
                                      Color(0x00FFFFFF),
                                    ],
                                  ),
                                ),
                              ),
                            ),
                          ),
                        ],
                      );
                    },
                  ),
                  Opacity(
                    opacity: 0.22,
                    child: Image.asset(
                      'assets/images/banner_shimmer.png',
                      fit: BoxFit.cover,
                      alignment: Alignment.centerRight,
                    ),
                  ),
                  Container(
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(radius),
                      border: Border.all(color: const Color(0x0CF2E1C2)),
                    ),
                  ),
                  Padding(
                    padding: EdgeInsets.symmetric(
                      horizontal: compact ? 12 : 16,
                    ),
                    child: Row(
                      children: [
                        if (!compact) ...[
                          _DiscoverAvatars(profiles: widget.profiles),
                          const SizedBox(width: 12),
                        ],
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              Text(
                                'Yeni birilerini ke\u015ffet',
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: TextStyle(
                                  fontFamily: AppFont.family,
                                  fontWeight: FontWeight.w700,
                                  fontSize: compact ? 14 : 16,
                                  color: AppColors.white,
                                ),
                              ),
                              const SizedBox(height: 2),
                              Text(
                                'Y\u00fczlerce ki\u015fi online',
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: TextStyle(
                                  fontFamily: AppFont.family,
                                  fontWeight: FontWeight.w400,
                                  fontSize: compact ? 10 : 11,
                                  color: const Color(0xD8F2E6D2),
                                ),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(width: 8),
                        Icon(
                          CupertinoIcons.chevron_right,
                          size: compact ? 16 : 18,
                          color: const Color(0xCCFFFFFF),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}

class _DiscoverAvatars extends StatelessWidget {
  final List<AppMatchCandidate> profiles;

  const _DiscoverAvatars({required this.profiles});

  @override
  Widget build(BuildContext context) {
    final visibleProfiles = profiles
        .where((profile) => profile.primaryImageUrl?.trim().isNotEmpty == true)
        .take(4)
        .toList();
    const count = 4;

    return SizedBox(
      width: 40 + ((count - 1) * 24),
      height: 40,
      child: Stack(
        children: List.generate(count, (index) {
          final profile = index < visibleProfiles.length
              ? visibleProfiles[index]
              : null;
          return Positioned(
            left: index * 24,
            child: profile == null
                ? _initialAvatar(_fallbackName(index))
                : _profileAvatar(profile),
          );
        }),
      ),
    );
  }

  Widget _profileAvatar(AppMatchCandidate profile) {
    final imageUrl = profile.primaryImageUrl;
    if (imageUrl == null || imageUrl.trim().isEmpty) {
      return _initialAvatar(profile.displayName);
    }

    return _avatarShell(
      ClipOval(
        child: Image.network(
          imageUrl,
          width: 40,
          height: 40,
          fit: BoxFit.cover,
          errorBuilder: (_, _, _) => _initialAvatar(profile.displayName),
        ),
      ),
    );
  }

  Widget _initialAvatar(String name) {
    return _avatarShell(
      Container(
        width: 40,
        height: 40,
        color: homeAvatarColorForName(name),
        alignment: Alignment.center,
        child: Text(
          homeInitialsOf(name),
          style: const TextStyle(
            fontFamily: AppFont.family,
            fontWeight: FontWeight.w700,
            fontSize: 12,
            color: AppColors.white,
          ),
        ),
      ),
    );
  }

  Widget _avatarShell(Widget child) {
    return Container(
      width: 40,
      height: 40,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        border: Border.all(color: AppColors.white, width: 2),
      ),
      child: ClipOval(child: child),
    );
  }

  String _fallbackName(int index) {
    const names = ['Ayse Yilmaz', 'Burcu Eren', 'Cem Koc', 'Derya Acar'];
    return names[index % names.length];
  }
}
