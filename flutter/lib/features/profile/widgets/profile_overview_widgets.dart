import 'dart:io';

import 'package:magmug/l10n/app_localizations.dart';
import 'package:magmug/app_core.dart';
import 'package:video_player/video_player.dart';

Future<void> openProfileMediaViewer(
  BuildContext context, {
  required List<AppProfilePhoto> media,
  required int initialIndex,
}) {
  if (media.isEmpty) {
    return Future.value();
  }

  return Navigator.of(context).push(
    CupertinoPageRoute<void>(
      builder: (_) => ProfileMediaViewerScreen(
        media: media,
        initialIndex: initialIndex,
      ),
      fullscreenDialog: true,
    ),
  );
}

String _profileInitials(String value) {
  final parts = value
      .trim()
      .split(RegExp(r'\s+'))
      .where((part) => part.isNotEmpty)
      .toList();
  if (parts.isEmpty) {
    return '?';
  }
  if (parts.length == 1) {
    final item = parts.first;
    return item.substring(0, item.length >= 2 ? 2 : 1).toUpperCase();
  }
  return (parts.first.substring(0, 1) + parts.last.substring(0, 1))
      .toUpperCase();
}

class _ProfileAvatarPlaceholder extends StatelessWidget {
  final String label;

  const _ProfileAvatarPlaceholder({required this.label});

  @override
  Widget build(BuildContext context) {
    return Container(
      color: const Color(0xFFF3F4F6),
      alignment: Alignment.center,
      child: Text(
        _profileInitials(label),
        style: const TextStyle(
          fontFamily: AppFont.family,
          fontWeight: FontWeight.w800,
          fontSize: 28,
          color: AppColors.neutral600,
        ),
      ),
    );
  }
}

class ProfileHeaderSection extends ConsumerWidget {
  final WidgetBuilder photoManagerSheetBuilder;
  final WidgetBuilder editProfileSheetBuilder;

  const ProfileHeaderSection({
    super.key,
    required this.photoManagerSheetBuilder,
    required this.editProfileSheetBuilder,
  });

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context)!;
    final user = ref.watch(appAuthProvider).asData?.value?.user;
    final data = ref.watch(onboardProvider);
    final localDisplayName = '${data.name} ${data.surname}'.trim();
    final displayName = user?.displayName.isNotEmpty == true
        ? user!.displayName
        : (localDisplayName.isNotEmpty
              ? localDisplayName
              : l10n.profileFallbackDisplayName);
    final username = user?.username.isNotEmpty == true
        ? user!.username
        : (data.username.isNotEmpty
              ? data.username
              : l10n.profileFallbackUsername);
    final remotePhoto = user?.profileImageUrl;
    final photo = data.photoPath;

    void openPhotoManager() {
      showCupertinoModalPopup<void>(
        context: context,
        builder: photoManagerSheetBuilder,
      );
    }

    return Column(
      children: [
        PressableScale(
          onTap: openPhotoManager,
          scale: 0.96,
          child: Stack(
            clipBehavior: Clip.none,
            children: [
              Container(
                width: 96,
                height: 96,
                decoration: const BoxDecoration(
                  shape: BoxShape.circle,
                  boxShadow: [
                    BoxShadow(
                      color: Color(0x14000000),
                      blurRadius: 16,
                      offset: Offset(0, 4),
                    ),
                  ],
                ),
                child: ClipOval(
                  child: remotePhoto != null && remotePhoto.isNotEmpty
                      ? Image.network(remotePhoto, fit: BoxFit.cover)
                      : photo != null
                      ? Image.file(File(photo), fit: BoxFit.cover)
                      : _ProfileAvatarPlaceholder(label: displayName),
                ),
              ),
              Positioned(
                right: -4,
                bottom: 4,
                child: Container(
                  width: 30,
                  height: 30,
                  decoration: BoxDecoration(
                    color: AppColors.black,
                    shape: BoxShape.circle,
                    border: Border.all(color: AppColors.neutral100, width: 3),
                  ),
                  alignment: Alignment.center,
                  child: const Icon(
                    CupertinoIcons.camera_fill,
                    size: 13,
                    color: AppColors.white,
                  ),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),
        Text(
          displayName,
          style: const TextStyle(
            fontFamily: AppFont.family,
            fontWeight: FontWeight.w800,
            fontSize: 20,
            color: AppColors.black,
          ),
        ),
        const SizedBox(height: 2),
        Text(
          '@$username',
          style: const TextStyle(
            fontFamily: AppFont.family,
            fontSize: 13,
            color: Color(0xFF999999),
          ),
        ),
        const SizedBox(height: 8),
        PressableScale(
          onTap: openPhotoManager,
          scale: 0.98,
          child: Text(
            l10n.profileChangePhoto,
            style: const TextStyle(
              fontFamily: AppFont.family,
              fontWeight: FontWeight.w700,
              fontSize: 12.5,
              color: AppColors.indigo,
            ),
          ),
        ),
        const SizedBox(height: 12),
        PressableScale(
          onTap: () => showCupertinoModalPopup<void>(
            context: context,
            builder: editProfileSheetBuilder,
          ),
          scale: 0.97,
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 10),
            decoration: BoxDecoration(
              color: AppColors.white,
              borderRadius: BorderRadius.circular(50),
              border: Border.all(color: const Color(0xFFE0E0E0)),
            ),
            child: Text(
              l10n.profileEditProfile,
              style: const TextStyle(
                fontFamily: AppFont.family,
                fontWeight: FontWeight.w700,
                fontSize: 13,
                color: AppColors.black,
              ),
            ),
          ),
        ),
      ],
    );
  }
}

class PremiumPromoBanner extends StatelessWidget {
  final VoidCallback onTap;

  const PremiumPromoBanner({super.key, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;

    return PressableScale(
      onTap: onTap,
      scale: 0.99,
      child: Container(
        height: 96,
        padding: const EdgeInsets.symmetric(horizontal: 16),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(24),
          gradient: const LinearGradient(
            begin: Alignment.centerLeft,
            end: Alignment.centerRight,
            colors: [Color(0xFF7C6DF5), Color(0xFFB194F9), Color(0xFFFF9EC4)],
          ),
          boxShadow: const [
            BoxShadow(
              color: Color(0x297C6DF5),
              blurRadius: 16,
              offset: Offset(0, 6),
            ),
          ],
        ),
        child: Row(
          children: [
            Image.asset(
              'assets/images/promo_char.png',
              width: 80,
              height: 80,
              fit: BoxFit.contain,
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Text(
                l10n.profilePromoTitle,
                style: TextStyle(
                  fontFamily: AppFont.family,
                  fontWeight: FontWeight.w800,
                  fontSize: 15,
                  color: AppColors.white,
                  height: 1.25,
                ),
              ),
            ),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
              decoration: BoxDecoration(
                color: AppColors.white,
                borderRadius: BorderRadius.circular(24),
              ),
              child: Text(
                l10n.profilePromoAction,
                style: TextStyle(
                  fontFamily: AppFont.family,
                  fontWeight: FontWeight.w700,
                  fontSize: 13,
                  color: Color(0xFF171717),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class ProfileMediaSection extends ConsumerStatefulWidget {
  final WidgetBuilder managerSheetBuilder;

  const ProfileMediaSection({super.key, required this.managerSheetBuilder});

  @override
  ConsumerState<ProfileMediaSection> createState() =>
      _ProfileMediaSectionState();
}

class _ProfileMediaSectionState extends ConsumerState<ProfileMediaSection> {
  final AppAuthApi _authApi = AppAuthApi();
  int _tab = 0;
  bool _loading = true;
  String? _notice;
  List<AppProfilePhoto> _photos = const [];

  @override
  void initState() {
    super.initState();
    _loadPhotos();
  }

  @override
  void dispose() {
    _authApi.close();
    super.dispose();
  }

  Future<void> _loadPhotos() async {
    final token = ref.read(appAuthProvider).asData?.value?.token;
    if (token == null || token.trim().isEmpty) {
      if (!mounted) return;
      setState(() {
        _photos = const [];
        _loading = false;
      });
      return;
    }

    setState(() {
      _loading = true;
      _notice = null;
    });

    try {
      final photos = await _authApi.fetchProfilePhotos(token);
      if (!mounted) return;
      setState(() => _photos = photos);
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _notice = AppAuthErrorFormatter.messageFrom(error);
      });
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  Future<void> _openManagerSheet() async {
    await showCupertinoModalPopup<void>(
      context: context,
      builder: widget.managerSheetBuilder,
    );
    await _loadPhotos();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final isAuthenticated =
        ref.watch(appAuthProvider).asData?.value?.token.trim().isNotEmpty ==
        true;
    final mediaTiles = isAuthenticated ? _remoteTiles() : const <Widget>[];
    final emptyMessage = switch (_tab) {
      2 => l10n.profileNoUploadedVideos,
      1 => l10n.profileNoUploadedPhotos,
      _ => l10n.profileNoUploadedMedia,
    };

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        children: [
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      l10n.profileMediaManagementTitle,
                      style: const TextStyle(
                        fontFamily: AppFont.family,
                        fontWeight: FontWeight.w800,
                        fontSize: 15,
                        color: AppColors.black,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      l10n.profileMediaManagementSubtitle,
                      style: const TextStyle(
                        fontFamily: AppFont.family,
                        fontSize: 11.5,
                        color: AppColors.gray,
                      ),
                    ),
                  ],
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 10,
                  vertical: 7,
                ),
                decoration: BoxDecoration(
                  color: AppColors.grayField,
                  borderRadius: BorderRadius.circular(999),
                ),
                child: Text(
                  '${_photos.length}/$kProfileMediaLimit',
                  style: const TextStyle(
                    fontFamily: AppFont.family,
                    fontWeight: FontWeight.w700,
                    fontSize: 11.5,
                    color: AppColors.black,
                  ),
                ),
              ),
              const SizedBox(width: 8),
              PressableScale(
                onTap: _openManagerSheet,
                scale: 0.97,
                child: Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 12,
                    vertical: 8,
                  ),
                  decoration: BoxDecoration(
                    color: AppColors.grayField,
                    borderRadius: BorderRadius.circular(999),
                  ),
                  child: Text(
                    l10n.profileManage,
                    style: const TextStyle(
                      fontFamily: AppFont.family,
                      fontWeight: FontWeight.w700,
                      fontSize: 12,
                      color: AppColors.black,
                    ),
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          ProfileMediaTabBar(
            selected: _tab,
            onChanged: (i) => setState(() => _tab = i),
          ),
          const SizedBox(height: 14),
          AspectRatio(
            aspectRatio: 3 / 2,
            child: _loading && isAuthenticated
                ? const Center(child: CupertinoActivityIndicator(radius: 14))
                : mediaTiles.isEmpty
                ? _emptyMediaState(emptyMessage)
                : GridView.count(
                    shrinkWrap: true,
                    physics: const NeverScrollableScrollPhysics(),
                    crossAxisCount: 3,
                    mainAxisSpacing: 6,
                    crossAxisSpacing: 6,
                    children: mediaTiles,
                  ),
          ),
          if (_notice != null) ...[
            const SizedBox(height: 12),
            Text(
              _notice!,
              style: const TextStyle(
                fontFamily: AppFont.family,
                fontSize: 12,
                height: 1.4,
                color: Color(0xFFEF4444),
              ),
            ),
          ],
        ],
      ),
    );
  }

  List<Widget> _remoteTiles() {
    final visiblePhotos = _visibleMedia.take(kProfileMediaLimit).toList();
    final tiles = visiblePhotos.map(_networkMediaTile).toList();
    while (tiles.length < kProfileMediaLimit) {
      tiles.add(_emptyTile());
    }
    return tiles;
  }

  List<AppProfilePhoto> get _visibleMedia {
    switch (_tab) {
      case 1:
        return _photos.where((item) => item.isPhoto).toList();
      case 2:
        return _photos.where((item) => item.isVideo).toList();
      default:
        return _photos;
    }
  }

  Widget _networkMediaTile(AppProfilePhoto photo) {
    final l10n = AppLocalizations.of(context)!;
    final media = _visibleMedia;
    final initialIndex = media.indexWhere((item) => item.id == photo.id);

    return PressableScale(
      onTap: () => openProfileMediaViewer(
        context,
        media: media,
        initialIndex: initialIndex < 0 ? 0 : initialIndex,
      ),
      scale: 0.98,
      child: ClipRRect(
        borderRadius: BorderRadius.circular(10),
        child: Stack(
          fit: StackFit.expand,
          children: [
            if (photo.isPhoto || photo.previewUrl != null)
              Image.network(
                photo.displayUrl,
                fit: BoxFit.cover,
                errorBuilder: (_, _, _) => _videoPlaceholder(),
              )
            else
              _videoPlaceholder(),
            if (photo.isPrimary)
              Positioned(
                left: 5,
                top: 5,
                child: Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 6,
                    vertical: 3,
                  ),
                  decoration: BoxDecoration(
                    color: const Color(0xA6111111),
                    borderRadius: BorderRadius.circular(999),
                  ),
                  child: Text(
                    l10n.profileBadgePrimary,
                    style: const TextStyle(
                      fontFamily: AppFont.family,
                      fontWeight: FontWeight.w700,
                      fontSize: 10,
                      color: AppColors.white,
                    ),
                  ),
                ),
              ),
            if (photo.isVideo)
              Positioned(
                left: 5,
                bottom: 5,
                child: Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 6,
                    vertical: 3,
                  ),
                  decoration: BoxDecoration(
                    color: const Color(0xA6111111),
                    borderRadius: BorderRadius.circular(999),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Icon(
                        CupertinoIcons.videocam_fill,
                        size: 10,
                        color: AppColors.white,
                      ),
                      const SizedBox(width: 4),
                      Text(
                        l10n.profileBadgeVideo,
                        style: const TextStyle(
                          fontFamily: AppFont.family,
                          fontWeight: FontWeight.w700,
                          fontSize: 10,
                          color: AppColors.white,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget _videoPlaceholder() {
    return Container(
      color: const Color(0xFFF3F4F6),
      alignment: Alignment.center,
      child: const Icon(
        CupertinoIcons.videocam_fill,
        size: 26,
        color: Color(0xFF9CA3AF),
      ),
    );
  }

  Widget _emptyTile() {
    return Container(
      decoration: BoxDecoration(
        color: AppColors.grayField,
        borderRadius: BorderRadius.circular(10),
      ),
    );
  }

  Widget _emptyMediaState(String message) {
    return Container(
      decoration: BoxDecoration(
        color: AppColors.grayField,
        borderRadius: BorderRadius.circular(14),
      ),
      alignment: Alignment.center,
      padding: const EdgeInsets.symmetric(horizontal: 24),
      child: Text(
        message,
        textAlign: TextAlign.center,
        style: const TextStyle(
          fontFamily: AppFont.family,
          fontWeight: FontWeight.w600,
          fontSize: 13,
          color: AppColors.gray,
        ),
      ),
    );
  }
}

class ProfileMediaTabBar extends StatelessWidget {
  final int selected;
  final ValueChanged<int> onChanged;

  const ProfileMediaTabBar({
    super.key,
    required this.selected,
    required this.onChanged,
  });

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final labels = [
      l10n.profileMediaAll,
      l10n.profileMediaPhotos,
      l10n.profileMediaVideos,
    ];
    return Container(
      height: 37,
      padding: const EdgeInsets.all(3),
      decoration: BoxDecoration(
        color: const Color(0xFFF2F2F4),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        children: List.generate(3, (i) {
          final isOn = selected == i;
          return Expanded(
            child: PressableScale(
              onTap: () => onChanged(i),
              scale: 0.98,
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 180),
                decoration: BoxDecoration(
                  color: isOn ? AppColors.black : const Color(0x00000000),
                  borderRadius: BorderRadius.circular(10),
                ),
                alignment: Alignment.center,
                child: Text(
                  labels[i],
                  style: TextStyle(
                    fontFamily: AppFont.family,
                    fontWeight: FontWeight.w600,
                    fontSize: 12,
                    color: isOn ? AppColors.white : const Color(0xFF999999),
                  ),
                ),
              ),
            ),
          );
        }),
      ),
    );
  }
}

class ProfileMediaViewerScreen extends StatefulWidget {
  final List<AppProfilePhoto> media;
  final int initialIndex;

  const ProfileMediaViewerScreen({
    super.key,
    required this.media,
    required this.initialIndex,
  });

  @override
  State<ProfileMediaViewerScreen> createState() =>
      _ProfileMediaViewerScreenState();
}

class _ProfileMediaViewerScreenState extends State<ProfileMediaViewerScreen> {
  late final PageController _pageController;
  late int _currentIndex;

  @override
  void initState() {
    super.initState();
    _currentIndex = widget.initialIndex;
    _pageController = PageController(initialPage: widget.initialIndex);
  }

  @override
  void dispose() {
    _pageController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final media = widget.media;
    final currentItem = media[_currentIndex];

    return CupertinoPageScaffold(
      backgroundColor: AppColors.black,
      child: Stack(
        children: [
          PageView.builder(
            controller: _pageController,
            itemCount: media.length,
            onPageChanged: (value) => setState(() => _currentIndex = value),
            itemBuilder: (_, index) => _ProfileMediaViewerPage(media: media[index]),
          ),
          SafeArea(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
              child: Row(
                children: [
                  PressableScale(
                    onTap: () => Navigator.of(context).maybePop(),
                    scale: 0.95,
                    child: Container(
                      width: 38,
                      height: 38,
                      decoration: BoxDecoration(
                        color: const Color(0x44111111),
                        borderRadius: BorderRadius.circular(999),
                      ),
                      alignment: Alignment.center,
                      child: const Icon(
                        CupertinoIcons.xmark,
                        size: 18,
                        color: AppColors.white,
                      ),
                    ),
                  ),
                  const Spacer(),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 12,
                      vertical: 7,
                    ),
                    decoration: BoxDecoration(
                      color: const Color(0x44111111),
                      borderRadius: BorderRadius.circular(999),
                    ),
                    child: Text(
                      '${_currentIndex + 1}/${media.length}',
                      style: const TextStyle(
                        fontFamily: AppFont.family,
                        fontWeight: FontWeight.w700,
                        fontSize: 12,
                        color: AppColors.white,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
          Positioned(
            left: 0,
            right: 0,
            bottom: 28,
            child: Center(
              child: Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 14,
                  vertical: 8,
                ),
                decoration: BoxDecoration(
                  color: const Color(0x44111111),
                  borderRadius: BorderRadius.circular(999),
                ),
                child: Text(
                  currentItem.isVideo ? 'Video' : 'Fotograf',
                  style: const TextStyle(
                    fontFamily: AppFont.family,
                    fontWeight: FontWeight.w700,
                    fontSize: 12,
                    color: AppColors.white,
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _ProfileMediaViewerPage extends StatefulWidget {
  final AppProfilePhoto media;

  const _ProfileMediaViewerPage({required this.media});

  @override
  State<_ProfileMediaViewerPage> createState() => _ProfileMediaViewerPageState();
}

class _ProfileMediaViewerPageState extends State<_ProfileMediaViewerPage> {
  VideoPlayerController? _videoController;
  Future<void>? _initializeVideoFuture;

  @override
  void initState() {
    super.initState();
    if (widget.media.isVideo) {
      _videoController = VideoPlayerController.networkUrl(
        Uri.parse(widget.media.url),
      );
      _initializeVideoFuture = _videoController!.initialize().then((_) {
        _videoController!
          ..setLooping(true)
          ..play();
      });
    }
  }

  @override
  void dispose() {
    _videoController?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (widget.media.isVideo) {
      return _buildVideo();
    }

    return Center(
      child: InteractiveViewer(
        minScale: 1,
        maxScale: 4,
        child: Image.network(
          widget.media.url,
          fit: BoxFit.contain,
          errorBuilder: (_, _, _) => const _ProfileMediaViewerFallback(
            icon: CupertinoIcons.photo,
            label: 'Medya yuklenemedi.',
          ),
        ),
      ),
    );
  }

  Widget _buildVideo() {
    final controller = _videoController;
    if (controller == null || _initializeVideoFuture == null) {
      return const _ProfileMediaViewerFallback(
        icon: CupertinoIcons.exclamationmark_triangle,
        label: 'Video acilamadi.',
      );
    }

    return FutureBuilder<void>(
      future: _initializeVideoFuture,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          return const Center(
            child: CupertinoActivityIndicator(radius: 14),
          );
        }
        if (snapshot.hasError || !controller.value.isInitialized) {
          return const _ProfileMediaViewerFallback(
            icon: CupertinoIcons.exclamationmark_triangle,
            label: 'Video acilamadi.',
          );
        }

        return Center(
          child: Stack(
            alignment: Alignment.center,
            children: [
              AspectRatio(
                aspectRatio: controller.value.aspectRatio,
                child: VideoPlayer(controller),
              ),
              PressableScale(
                onTap: () {
                  if (!mounted) {
                    return;
                  }
                  setState(() {
                    if (controller.value.isPlaying) {
                      controller.pause();
                    } else {
                      controller.play();
                    }
                  });
                },
                scale: 0.95,
                child: Container(
                  width: 68,
                  height: 68,
                  decoration: BoxDecoration(
                    color: const Color(0x55111111),
                    borderRadius: BorderRadius.circular(999),
                  ),
                  alignment: Alignment.center,
                  child: Icon(
                    controller.value.isPlaying
                        ? CupertinoIcons.pause_fill
                        : CupertinoIcons.play_fill,
                    size: 28,
                    color: AppColors.white,
                  ),
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}

class _ProfileMediaViewerFallback extends StatelessWidget {
  final IconData icon;
  final String label;

  const _ProfileMediaViewerFallback({
    required this.icon,
    required this.label,
  });

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 30, color: AppColors.white),
          const SizedBox(height: 12),
          Text(
            label,
            style: const TextStyle(
              fontFamily: AppFont.family,
              fontWeight: FontWeight.w600,
              fontSize: 13,
              color: AppColors.white,
            ),
          ),
        ],
      ),
    );
  }
}
