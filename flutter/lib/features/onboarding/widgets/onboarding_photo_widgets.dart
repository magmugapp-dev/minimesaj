import 'dart:io';

import 'package:magmug/app_core.dart';

Future<void> showOnboardingPhotoSourceSheet(
  BuildContext context, {
  required VoidCallback onCameraTap,
  required VoidCallback onGalleryTap,
}) {
  return showCupertinoModalPopup<void>(
    context: context,
    builder: (ctx) => CupertinoActionSheet(
      title: const Text(
        'Fotograf kaynagi',
        style: TextStyle(fontFamily: AppFont.family),
      ),
      actions: [
        CupertinoActionSheetAction(
          onPressed: () {
            Navigator.of(ctx).pop();
            onCameraTap();
          },
          child: const Text(
            'Kamera',
            style: TextStyle(fontFamily: AppFont.family),
          ),
        ),
        CupertinoActionSheetAction(
          onPressed: () {
            Navigator.of(ctx).pop();
            onGalleryTap();
          },
          child: const Text(
            'Galeri',
            style: TextStyle(fontFamily: AppFont.family),
          ),
        ),
      ],
      cancelButton: CupertinoActionSheetAction(
        isDefaultAction: true,
        onPressed: () => Navigator.of(ctx).pop(),
        child: const Text(
          'Vazgec',
          style: TextStyle(fontFamily: AppFont.family),
        ),
      ),
    ),
  );
}

class OnboardingPhotoSlot extends StatelessWidget {
  final String? photoPath;
  final String? fallbackPhotoUrl;
  final bool busy;
  final VoidCallback? onTap;

  const OnboardingPhotoSlot({
    super.key,
    required this.photoPath,
    required this.fallbackPhotoUrl,
    required this.busy,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final availableWidth = MediaQuery.sizeOf(context).width - 96;
    final slotSize = availableWidth.clamp(152.0, 180.0).toDouble();

    return PressableScale(
      onTap: onTap,
      scale: 0.98,
      child: SizedBox(
        width: slotSize,
        height: slotSize,
        child: Stack(
          alignment: Alignment.center,
          children: [
            AnimatedSwitcher(
              duration: const Duration(milliseconds: 280),
              switchInCurve: Curves.easeOut,
              transitionBuilder: (child, anim) =>
                  FadeTransition(opacity: anim, child: child),
              child: photoPath != null
                  ? ClipOval(
                      key: const ValueKey('photo'),
                      child: Image.file(
                        File(photoPath!),
                        width: slotSize,
                        height: slotSize,
                        fit: BoxFit.cover,
                      ),
                    )
                  : fallbackPhotoUrl != null && fallbackPhotoUrl!.isNotEmpty
                  ? ClipOval(
                      key: const ValueKey('fallback-photo'),
                      child: Image.network(
                        fallbackPhotoUrl!,
                        width: slotSize,
                        height: slotSize,
                        fit: BoxFit.cover,
                        errorBuilder: (_, _, _) =>
                            OnboardingDashedPhotoPlaceholder(size: slotSize),
                      ),
                    )
                  : OnboardingDashedPhotoPlaceholder(
                      key: const ValueKey('empty'),
                      size: slotSize,
                    ),
            ),
            Positioned(
              right: 6,
              bottom: 6,
              child: Container(
                width: 40,
                height: 40,
                decoration: const BoxDecoration(
                  color: AppColors.indigo,
                  shape: BoxShape.circle,
                  boxShadow: [
                    BoxShadow(
                      color: AppColors.shadow,
                      blurRadius: 14,
                      offset: Offset(0, 4),
                    ),
                  ],
                ),
                alignment: Alignment.center,
                child: busy
                    ? const CupertinoActivityIndicator(color: AppColors.white)
                    : const Icon(
                        CupertinoIcons.add,
                        size: 22,
                        color: AppColors.white,
                      ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class OnboardingDashedPhotoPlaceholder extends StatelessWidget {
  final double size;

  const OnboardingDashedPhotoPlaceholder({super.key, required this.size});

  @override
  Widget build(BuildContext context) {
    return CustomPaint(
      size: Size(size, size),
      painter: const OnboardingDashedCirclePainter(),
      child: const Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(CupertinoIcons.camera, size: 28, color: AppColors.gray),
            SizedBox(height: 8),
            Text(
              'Fotograf Sec',
              style: TextStyle(
                fontFamily: AppFont.family,
                fontWeight: FontWeight.w500,
                fontSize: 14,
                color: AppColors.gray,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class OnboardingDashedCirclePainter extends CustomPainter {
  const OnboardingDashedCirclePainter();

  @override
  void paint(Canvas canvas, Size size) {
    final bgPaint = Paint()..color = AppColors.grayField;
    final center = Offset(size.width / 2, size.height / 2);
    final radius = size.width / 2;
    canvas.drawCircle(center, radius, bgPaint);

    final dashPaint = Paint()
      ..color = AppColors.grayBorder
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1.6;

    const dashCount = 48;
    const gap = 0.45;
    const dashArc = 6.28318530718 / dashCount;
    for (var i = 0; i < dashCount; i++) {
      final start = i * dashArc;
      final sweep = dashArc * (1 - gap);
      canvas.drawArc(
        Rect.fromCircle(center: center, radius: radius - 1),
        start,
        sweep,
        false,
        dashPaint,
      );
    }
  }

  @override
  bool shouldRepaint(covariant OnboardingDashedCirclePainter oldDelegate) =>
      false;
}
