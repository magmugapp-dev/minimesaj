import 'dart:io';

import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/cupertino.dart';
import 'package:magmug/core/theme/app_colors.dart';

@visibleForTesting
({int? width, int? height}) resolveCachedAppImageDecodeSize({
  int? cacheWidth,
  int? cacheHeight,
}) {
  final safeWidth = cacheWidth != null && cacheWidth > 0 ? cacheWidth : null;
  final safeHeight = cacheHeight != null && cacheHeight > 0
      ? cacheHeight
      : null;

  if (safeWidth == null || safeHeight == null) {
    return (width: safeWidth, height: safeHeight);
  }

  if (safeWidth >= safeHeight) {
    return (width: safeWidth, height: null);
  }

  return (width: null, height: safeHeight);
}

class CachedAppImage extends StatelessWidget {
  final String? imageUrl;
  final double? width;
  final double? height;
  final BoxFit fit;
  final Alignment alignment;
  final bool gaplessPlayback;
  final int? cacheWidth;
  final int? cacheHeight;
  final WidgetBuilder? placeholderBuilder;
  final WidgetBuilder? errorBuilder;

  const CachedAppImage({
    super.key,
    required this.imageUrl,
    this.width,
    this.height,
    this.fit = BoxFit.cover,
    this.alignment = Alignment.center,
    this.gaplessPlayback = true,
    this.cacheWidth,
    this.cacheHeight,
    this.placeholderBuilder,
    this.errorBuilder,
  });

  @override
  Widget build(BuildContext context) {
    final source = imageUrl?.trim();
    final decodeSize = resolveCachedAppImageDecodeSize(
      cacheWidth: cacheWidth,
      cacheHeight: cacheHeight,
    );
    if (source == null || source.isEmpty) {
      return _fallback(context);
    }

    if (source.startsWith('http://') || source.startsWith('https://')) {
      return CachedNetworkImage(
        imageUrl: source,
        width: width,
        height: height,
        fit: fit,
        alignment: alignment,
        memCacheWidth: decodeSize.width,
        memCacheHeight: decodeSize.height,
        fadeInDuration: const Duration(milliseconds: 120),
        placeholder: (context, _) => _placeholder(context),
        errorWidget: (context, _, _) => _fallback(context),
      );
    }

    if (source.startsWith('assets/')) {
      return Image.asset(
        source,
        width: width,
        height: height,
        fit: fit,
        alignment: alignment,
        gaplessPlayback: gaplessPlayback,
        cacheWidth: decodeSize.width,
        cacheHeight: decodeSize.height,
        errorBuilder: (_, _, _) => _fallback(context),
      );
    }

    return Image.file(
      File(source),
      width: width,
      height: height,
      fit: fit,
      alignment: alignment,
      gaplessPlayback: gaplessPlayback,
      cacheWidth: decodeSize.width,
      cacheHeight: decodeSize.height,
      errorBuilder: (_, _, _) => _fallback(context),
    );
  }

  Widget _placeholder(BuildContext context) {
    return placeholderBuilder?.call(context) ??
        const ColoredBox(color: AppColors.grayField);
  }

  Widget _fallback(BuildContext context) {
    return errorBuilder?.call(context) ??
        const ColoredBox(color: AppColors.grayField);
  }
}
