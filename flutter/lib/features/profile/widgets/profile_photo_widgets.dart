import 'dart:io';

import 'package:magmug/app_core.dart';
import 'package:magmug/features/profile/widgets/profile_settings_widgets.dart';

String _profilePhotoInitials(String value) {
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

class ProfileImagePlaceholder extends StatelessWidget {
  final String label;

  const ProfileImagePlaceholder({super.key, required this.label});

  @override
  Widget build(BuildContext context) {
    return Container(
      color: const Color(0xFFF3F4F6),
      alignment: Alignment.center,
      child: Text(
        _profilePhotoInitials(label),
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

class ProfileNetworkImage extends StatelessWidget {
  final String imageUrl;
  final Widget placeholder;
  final BoxFit fit;

  const ProfileNetworkImage({
    super.key,
    required this.imageUrl,
    required this.placeholder,
    this.fit = BoxFit.cover,
  });

  @override
  Widget build(BuildContext context) {
    return CachedAppImage(
      imageUrl: imageUrl,
      fit: fit,
      errorBuilder: (_) => placeholder,
    );
  }
}

class ProfileManagerActionButton extends StatelessWidget {
  final IconData icon;
  final String label;
  final VoidCallback? onTap;

  const ProfileManagerActionButton({
    super.key,
    required this.icon,
    required this.label,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final isDisabled = onTap == null;

    return PressableScale(
      onTap: onTap,
      scale: 0.98,
      child: AnimatedOpacity(
        duration: const Duration(milliseconds: 180),
        opacity: isDisabled ? 0.45 : 1,
        child: Container(
          height: 84,
          decoration: BoxDecoration(
            color: AppColors.white,
            borderRadius: BorderRadius.circular(18),
            border: Border.all(color: const Color(0xFFE8E8EC)),
            boxShadow: const [
              BoxShadow(
                color: Color(0x12000000),
                blurRadius: 18,
                offset: Offset(0, 10),
              ),
            ],
          ),
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 10),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                width: 34,
                height: 34,
                decoration: BoxDecoration(
                  color: const Color(0xFFF4F6FF),
                  borderRadius: BorderRadius.circular(12),
                ),
                alignment: Alignment.center,
                child: Icon(icon, size: 18, color: AppColors.indigo),
              ),
              const SizedBox(height: 10),
              Text(
                label,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  fontFamily: AppFont.family,
                  fontWeight: FontWeight.w700,
                  fontSize: 12.5,
                  color: AppColors.black,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class ProfileVideoTilePlaceholder extends StatelessWidget {
  const ProfileVideoTilePlaceholder({super.key});

  @override
  Widget build(BuildContext context) {
    return Container(
      color: const Color(0xFFF3F4F6),
      alignment: Alignment.center,
      child: const Icon(
        CupertinoIcons.videocam_fill,
        size: 24,
        color: Color(0xFF9CA3AF),
      ),
    );
  }
}

class ProfileEmptyGalleryTile extends StatelessWidget {
  const ProfileEmptyGalleryTile({super.key});

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: AppColors.grayField,
        borderRadius: BorderRadius.circular(14),
      ),
    );
  }
}

class ProfilePendingUploadTile extends StatelessWidget {
  final String path;
  final bool isVideo;
  final double progress;

  const ProfilePendingUploadTile({
    super.key,
    required this.path,
    required this.isVideo,
    required this.progress,
  });

  @override
  Widget build(BuildContext context) {
    final progressLabel = '${(progress * 100).round().clamp(0, 100)}%';

    return ClipRRect(
      borderRadius: BorderRadius.circular(14),
      child: Stack(
        fit: StackFit.expand,
        children: [
          isVideo
              ? const ProfileVideoTilePlaceholder()
              : Image.file(File(path), fit: BoxFit.cover),
          Container(color: const Color(0x99000000)),
          Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const CupertinoActivityIndicator(color: AppColors.white),
                const SizedBox(height: 10),
                Text(
                  progressLabel,
                  style: const TextStyle(
                    fontFamily: AppFont.family,
                    fontWeight: FontWeight.w800,
                    fontSize: 18,
                    color: AppColors.white,
                  ),
                ),
              ],
            ),
          ),
          Positioned(
            left: 8,
            right: 8,
            bottom: 10,
            child: ClipRRect(
              borderRadius: BorderRadius.circular(999),
              child: SizedBox(
                height: 6,
                child: Stack(
                  children: [
                    Container(color: const Color(0x33FFFFFF)),
                    FractionallySizedBox(
                      widthFactor: progress.clamp(0, 1).toDouble(),
                      alignment: Alignment.centerLeft,
                      child: Container(color: AppColors.white),
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

class ProfileRemoteMediaTile extends StatelessWidget {
  final AppProfilePhoto photo;
  final VoidCallback onTap;
  final VoidCallback onMoreTap;
  final String primaryBadgeLabel;
  final String videoBadgeLabel;

  const ProfileRemoteMediaTile({
    super.key,
    required this.photo,
    required this.onTap,
    required this.onMoreTap,
    required this.primaryBadgeLabel,
    required this.videoBadgeLabel,
  });

  @override
  Widget build(BuildContext context) {
    return PressableScale(
      onTap: onTap,
      scale: 0.98,
      child: ClipRRect(
        borderRadius: BorderRadius.circular(14),
        child: Stack(
          fit: StackFit.expand,
          children: [
            if (photo.isPhoto || photo.previewUrl != null)
              ProfileNetworkImage(
                imageUrl: photo.displayUrl,
                fit: BoxFit.cover,
                placeholder: const ProfileVideoTilePlaceholder(),
              )
            else
              const ProfileVideoTilePlaceholder(),
            if (photo.isPrimary)
              Positioned(
                left: 6,
                top: 6,
                child: Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 7,
                    vertical: 4,
                  ),
                  decoration: BoxDecoration(
                    color: const Color(0xB3111111),
                    borderRadius: BorderRadius.circular(999),
                  ),
                  child: Text(
                    primaryBadgeLabel,
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
                right: 6,
                bottom: 6,
                child: Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 7,
                    vertical: 4,
                  ),
                  decoration: BoxDecoration(
                    color: const Color(0xB3111111),
                    borderRadius: BorderRadius.circular(999),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Icon(
                        CupertinoIcons.videocam_fill,
                        size: 12,
                        color: AppColors.white,
                      ),
                      const SizedBox(width: 4),
                      Text(
                        videoBadgeLabel,
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
            Positioned(
              right: 6,
              top: 6,
              child: PressableScale(
                onTap: onMoreTap,
                scale: 0.96,
                child: Container(
                  width: 28,
                  height: 28,
                  decoration: BoxDecoration(
                    color: const Color(0xB3111111),
                    borderRadius: BorderRadius.circular(999),
                  ),
                  alignment: Alignment.center,
                  child: const Icon(
                    CupertinoIcons.ellipsis,
                    size: 15,
                    color: AppColors.white,
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

class ProfilePhotoManagerSummary extends StatelessWidget {
  final Widget preview;
  final String primaryText;
  final String secondaryText;

  const ProfilePhotoManagerSummary({
    super.key,
    required this.preview,
    required this.primaryText,
    required this.secondaryText,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Container(
          width: 92,
          height: 92,
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
          child: ClipOval(child: preview),
        ),
        const SizedBox(width: 16),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                primaryText,
                style: const TextStyle(
                  fontFamily: AppFont.family,
                  fontWeight: FontWeight.w700,
                  fontSize: 15,
                  color: AppColors.black,
                ),
              ),
              const SizedBox(height: 6),
              Text(
                secondaryText,
                style: const TextStyle(
                  fontFamily: AppFont.family,
                  fontSize: 12,
                  height: 1.45,
                  color: AppColors.neutral600,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class ProfilePhotoManagerPreview extends StatelessWidget {
  final AppProfilePhoto? primaryPhoto;
  final String? fallbackImageUrl;
  final String placeholderLabel;

  const ProfilePhotoManagerPreview({
    super.key,
    required this.primaryPhoto,
    required this.fallbackImageUrl,
    required this.placeholderLabel,
  });

  @override
  Widget build(BuildContext context) {
    if (primaryPhoto != null && primaryPhoto!.url.isNotEmpty) {
      return CachedAppImage(
        imageUrl: primaryPhoto!.url,
        fit: BoxFit.cover,
      );
    }

    if (fallbackImageUrl != null && fallbackImageUrl!.isNotEmpty) {
      return CachedAppImage(
        imageUrl: fallbackImageUrl!,
        fit: BoxFit.cover,
      );
    }

    return ProfileImagePlaceholder(label: placeholderLabel);
  }
}

class ProfilePhotoManagerActionsRow extends StatelessWidget {
  final String takePhotoLabel;
  final String pickPhotoLabel;
  final String pickVideoLabel;
  final VoidCallback? onTakePhoto;
  final VoidCallback? onPickPhoto;
  final VoidCallback? onPickVideo;

  const ProfilePhotoManagerActionsRow({
    super.key,
    required this.takePhotoLabel,
    required this.pickPhotoLabel,
    required this.pickVideoLabel,
    required this.onTakePhoto,
    required this.onPickPhoto,
    required this.onPickVideo,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: ProfileManagerActionButton(
            icon: CupertinoIcons.camera_fill,
            label: takePhotoLabel,
            onTap: onTakePhoto,
          ),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: ProfileManagerActionButton(
            icon: CupertinoIcons.photo_fill_on_rectangle_fill,
            label: pickPhotoLabel,
            onTap: onPickPhoto,
          ),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: ProfileManagerActionButton(
            icon: CupertinoIcons.videocam_fill,
            label: pickVideoLabel,
            onTap: onPickVideo,
          ),
        ),
      ],
    );
  }
}

class ProfilePhotoGallerySection extends StatelessWidget {
  final String title;
  final String countLabel;
  final bool isLoading;
  final List<Widget> tiles;

  const ProfilePhotoGallerySection({
    super.key,
    required this.title,
    required this.countLabel,
    required this.isLoading,
    required this.tiles,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Expanded(
              child: Text(
                title,
                style: const TextStyle(
                  fontFamily: AppFont.family,
                  fontWeight: FontWeight.w800,
                  fontSize: 15,
                  color: AppColors.black,
                ),
              ),
            ),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
              decoration: BoxDecoration(
                color: AppColors.grayField,
                borderRadius: BorderRadius.circular(999),
              ),
              child: Text(
                countLabel,
                style: const TextStyle(
                  fontFamily: AppFont.family,
                  fontWeight: FontWeight.w700,
                  fontSize: 11.5,
                  color: AppColors.black,
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        LayoutBuilder(
          builder: (context, constraints) {
            final tileSize = (constraints.maxWidth - 16) / 3;
            final gridHeight = tileSize * 2 + 70;
            return SizedBox(
              height: gridHeight,
              child: isLoading
                  ? const Center(child: CupertinoActivityIndicator(radius: 12))
                  : GridView.count(
                      physics: const NeverScrollableScrollPhysics(),
                      crossAxisCount: 3,
                      mainAxisSpacing: 8,
                      crossAxisSpacing: 8,
                      children: tiles,
                    ),
            );
          },
        ),
      ],
    );
  }
}

List<Widget> buildProfilePhotoGalleryTiles({
  required List<AppProfilePhoto> photos,
  required int maxLimit,
  required String? pendingUploadPath,
  required bool pendingUploadIsVideo,
  required double pendingUploadProgress,
  required String primaryBadgeLabel,
  required String videoBadgeLabel,
  required void Function(AppProfilePhoto photo) onPhotoTap,
  required void Function(AppProfilePhoto photo) onPhotoMoreTap,
}) {
  final tiles = <Widget>[];

  if (pendingUploadPath != null) {
    tiles.add(
      ProfilePendingUploadTile(
        path: pendingUploadPath,
        isVideo: pendingUploadIsVideo,
        progress: pendingUploadProgress,
      ),
    );
  }

  for (final photo in photos) {
    if (tiles.length >= maxLimit) {
      break;
    }

    tiles.add(
      ProfileRemoteMediaTile(
        photo: photo,
        onTap: () => onPhotoTap(photo),
        onMoreTap: () => onPhotoMoreTap(photo),
        primaryBadgeLabel: primaryBadgeLabel,
        videoBadgeLabel: videoBadgeLabel,
      ),
    );
  }

  while (tiles.length < maxLimit) {
    tiles.add(const ProfileEmptyGalleryTile());
  }

  return tiles;
}

class ProfilePhotoNoticeCard extends StatelessWidget {
  final String text;

  const ProfilePhotoNoticeCard({super.key, required this.text});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFFF7F7FB),
        borderRadius: BorderRadius.circular(14),
      ),
      child: Text(
        text,
        style: const TextStyle(
          fontFamily: AppFont.family,
          fontSize: 12,
          height: 1.45,
          color: AppColors.neutral600,
        ),
      ),
    );
  }
}

class ProfilePhotoManagerSheetView extends StatelessWidget {
  final String title;
  final String subtitle;
  final Widget preview;
  final String summaryTitle;
  final String summarySubtitle;
  final String takePhotoLabel;
  final String pickPhotoLabel;
  final String pickVideoLabel;
  final VoidCallback? onTakePhoto;
  final VoidCallback? onPickPhoto;
  final VoidCallback? onPickVideo;
  final String galleryTitle;
  final String galleryCountLabel;
  final bool isGalleryLoading;
  final List<Widget> galleryTiles;
  final String? notice;
  final String doneLabel;
  final VoidCallback? onDone;

  const ProfilePhotoManagerSheetView({
    super.key,
    required this.title,
    required this.subtitle,
    required this.preview,
    required this.summaryTitle,
    required this.summarySubtitle,
    required this.takePhotoLabel,
    required this.pickPhotoLabel,
    required this.pickVideoLabel,
    required this.onTakePhoto,
    required this.onPickPhoto,
    required this.onPickVideo,
    required this.galleryTitle,
    required this.galleryCountLabel,
    required this.isGalleryLoading,
    required this.galleryTiles,
    required this.notice,
    required this.doneLabel,
    required this.onDone,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
      child: SafeArea(
        top: false,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const ProfileSheetHandle(),
            const SizedBox(height: 18),
            Text(
              title,
              style: const TextStyle(
                fontFamily: AppFont.family,
                fontWeight: FontWeight.w800,
                fontSize: 20,
                color: AppColors.black,
              ),
            ),
            const SizedBox(height: 6),
            Text(
              subtitle,
              style: const TextStyle(
                fontFamily: AppFont.family,
                fontSize: 12.5,
                color: AppColors.neutral600,
              ),
            ),
            const SizedBox(height: 18),
            ProfilePhotoManagerSummary(
              preview: preview,
              primaryText: summaryTitle,
              secondaryText: summarySubtitle,
            ),
            const SizedBox(height: 18),
            ProfilePhotoManagerActionsRow(
              takePhotoLabel: takePhotoLabel,
              pickPhotoLabel: pickPhotoLabel,
              pickVideoLabel: pickVideoLabel,
              onTakePhoto: onTakePhoto,
              onPickPhoto: onPickPhoto,
              onPickVideo: onPickVideo,
            ),
            const SizedBox(height: 18),
            ProfilePhotoGallerySection(
              title: galleryTitle,
              countLabel: galleryCountLabel,
              isLoading: isGalleryLoading,
              tiles: galleryTiles,
            ),
            if (notice != null) ...[
              const SizedBox(height: 12),
              ProfilePhotoNoticeCard(text: notice!),
            ],
            const SizedBox(height: 16),
            GradientButton(label: doneLabel, onTap: onDone),
          ],
        ),
      ),
    );
  }
}
