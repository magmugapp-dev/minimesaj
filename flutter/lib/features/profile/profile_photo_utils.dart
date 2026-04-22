import 'dart:io';

import 'package:magmug/app_core.dart';
import 'package:image_picker/image_picker.dart';
import 'package:magmug/l10n/app_localizations.dart';
import 'package:video_compress/video_compress.dart';

const int profileMaxSafeUploadBytes = 7 * 1024 * 1024;

enum ProfileMediaAction { makePrimary, delete }

String profileMediaLimitNotice(int maxLimit) {
  return 'En fazla $maxLimit medya yukleyebilirsin.';
}

AppProfilePhoto? resolvePrimaryProfilePhoto(List<AppProfilePhoto> photos) {
  for (final photo in photos) {
    if (photo.isPrimary) {
      return photo;
    }
  }

  return photos.isNotEmpty ? photos.first : null;
}

String resolveProfilePhotoPlaceholderLabel(
  AppUser? user,
  AppLocalizations l10n,
) {
  return [user?.displayName, user?.username]
      .whereType<String>()
      .map((value) => value.trim())
      .firstWhere(
        (value) => value.isNotEmpty,
        orElse: () => l10n.profileFallbackDisplayName,
      );
}

List<AppProfilePhoto> sortProfileMedia(List<AppProfilePhoto> photos) {
  return photos.toList()
    ..sort((left, right) => left.order.compareTo(right.order));
}

int resolveProfileMediaInitialIndex(
  List<AppProfilePhoto> media,
  AppProfilePhoto selected,
) {
  final initialIndex = media.indexWhere((item) => item.id == selected.id);
  return initialIndex < 0 ? 0 : initialIndex;
}

int profilePhotoCount(List<AppProfilePhoto> photos) {
  return photos.where((item) => item.isPhoto).length;
}

int profileVideoCount(List<AppProfilePhoto> photos) {
  return photos.where((item) => item.isVideo).length;
}

Future<String> prepareProfileMediaUploadPath(
  String originalPath, {
  required bool isVideo,
  int maxSafeUploadBytes = profileMaxSafeUploadBytes,
}) async {
  if (!isVideo) {
    return originalPath;
  }

  final sourceFile = File(originalPath);
  if (!await sourceFile.exists()) {
    return originalPath;
  }

  final sourceSizeBytes = await sourceFile.length();
  if (sourceSizeBytes <= maxSafeUploadBytes) {
    return originalPath;
  }

  try {
    final firstPassPath = await _compressProfileVideoOnce(
      originalPath,
      quality: VideoQuality.DefaultQuality,
    );
    if (firstPassPath == null || firstPassPath.trim().isEmpty) {
      return originalPath;
    }

    var bestPath = firstPassPath;
    var bestFile = File(bestPath);
    if (!await bestFile.exists()) {
      return originalPath;
    }

    var bestBytes = await bestFile.length();

    if (bestBytes > maxSafeUploadBytes) {
      final secondPassPath = await _compressProfileVideoOnce(
        bestPath,
        quality: VideoQuality.LowQuality,
      );
      if (secondPassPath != null && secondPassPath.trim().isNotEmpty) {
        final secondFile = File(secondPassPath);
        if (await secondFile.exists()) {
          final secondBytes = await secondFile.length();
          if (secondBytes > 0 && secondBytes < bestBytes) {
            bestPath = secondPassPath;
            bestBytes = secondBytes;
          }
        }
      }
    }

    final compressedFile = File(bestPath);
    if (!await compressedFile.exists()) {
      return originalPath;
    }

    final compressedBytes = await compressedFile.length();
    if (compressedBytes <= 0 || compressedBytes >= sourceSizeBytes) {
      return originalPath;
    }

    return bestPath;
  } catch (_) {
    return originalPath;
  }
}

Future<String?> pickProfileMediaPath(
  ImagePicker picker, {
  required ImageSource source,
  required bool isVideo,
}) async {
  if (isVideo) {
    final picked = await picker.pickVideo(source: source);
    return picked?.path;
  }

  final picked = await picker.pickImage(
    source: source,
    imageQuality: 90,
    maxWidth: 1800,
  );
  return picked?.path;
}

Future<String?> _compressProfileVideoOnce(
  String inputPath, {
  required VideoQuality quality,
}) async {
  final info = await VideoCompress.compressVideo(
    inputPath,
    quality: quality,
    includeAudio: true,
    deleteOrigin: false,
  );
  return info?.file?.path;
}

Future<ProfileMediaAction?> showProfileMediaActionSheet(
  BuildContext context, {
  required AppProfilePhoto photo,
  required AppLocalizations l10n,
}) {
  return showCupertinoModalPopup<ProfileMediaAction>(
    context: context,
    builder: (sheetContext) => CupertinoActionSheet(
      title: Text(
        l10n.profileMediaActionTitle,
        style: const TextStyle(fontFamily: AppFont.family),
      ),
      actions: [
        if (photo.isPhoto && !photo.isPrimary)
          CupertinoActionSheetAction(
            onPressed: () =>
                Navigator.of(sheetContext).pop(ProfileMediaAction.makePrimary),
            child: Text(
              l10n.profileMakePrimary,
              style: const TextStyle(fontFamily: AppFont.family),
            ),
          ),
        CupertinoActionSheetAction(
          isDestructiveAction: true,
          onPressed: () =>
              Navigator.of(sheetContext).pop(ProfileMediaAction.delete),
          child: Text(
            l10n.profileDeleteMedia,
            style: const TextStyle(fontFamily: AppFont.family),
          ),
        ),
      ],
      cancelButton: CupertinoActionSheetAction(
        onPressed: () => Navigator.of(sheetContext).pop(),
        child: Text(
          l10n.commonCancel,
          style: const TextStyle(fontFamily: AppFont.family),
        ),
      ),
    ),
  );
}
