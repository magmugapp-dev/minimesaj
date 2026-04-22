import 'dart:io';

import 'package:magmug/app_core.dart';
import 'package:magmug/features/notifications/notifications_flow.dart';
import 'package:magmug/features/profile/profile_flow.dart';
import 'package:magmug/features/home/widgets/home_avatar.dart';

class HomeTopBar extends ConsumerWidget {
  const HomeTopBar({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(appAuthProvider).asData?.value?.user;
    final data = ref.watch(onboardProvider);
    final displayName = user?.firstName.isNotEmpty == true
        ? user!.firstName
        : (data.name.isEmpty ? 'Sen' : data.name);
    final profileImageUrl = user?.profileImageUrl;
    final gemAmount = user?.gemBalance ?? 0;

    void openProfile() {
      Navigator.of(context).push(cupertinoRouteFromLeft(const ProfileScreen()));
    }

    void openPremium() {
      Navigator.of(context).push(cupertinoRoute(const PaywallScreen()));
    }

    void openGems() {
      showCupertinoModalPopup<void>(
        context: context,
        builder: (_) => const JetonPurchaseSheet(),
      );
    }

    void openNotifications() {
      Navigator.of(context).push(cupertinoRoute(const NotificationsScreen()));
    }

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
      child: LayoutBuilder(
        builder: (context, constraints) {
          final compact = constraints.maxWidth < 360;

          return Row(
            children: [
              PressableScale(
                onTap: openProfile,
                scale: 0.94,
                child: _ProfileAvatar(
                  name: displayName,
                  photoPath: data.photoPath,
                  imageUrl: profileImageUrl,
                ),
              ),
              const Spacer(),
              PressableScale(
                onTap: openPremium,
                scale: 0.96,
                child: _TopChip(
                  label: compact ? 'Pro' : 'Premium',
                  showPlus: false,
                ),
              ),
              const SizedBox(width: 8),
              PressableScale(
                onTap: openGems,
                scale: 0.96,
                child: _TopChip(label: '$gemAmount', showPlus: true),
              ),
              const SizedBox(width: 8),
              PressableScale(
                onTap: openNotifications,
                scale: 0.96,
                child: const _BellChip(),
              ),
            ],
          );
        },
      ),
    );
  }
}

class _ProfileAvatar extends StatelessWidget {
  final String name;
  final String? photoPath;
  final String? imageUrl;

  const _ProfileAvatar({required this.name, this.photoPath, this.imageUrl});

  @override
  Widget build(BuildContext context) {
    if (photoPath != null) {
      return ClipOval(
        child: Image.file(
          File(photoPath!),
          width: 40,
          height: 40,
          fit: BoxFit.cover,
        ),
      );
    }
    if (imageUrl != null && imageUrl!.isNotEmpty) {
      return ClipOval(
        child: Image.network(
          imageUrl!,
          width: 40,
          height: 40,
          fit: BoxFit.cover,
        ),
      );
    }
    return HomeAvatarCircle(name: name, size: 40);
  }
}

class _TopChip extends StatelessWidget {
  final String label;
  final bool showPlus;

  const _TopChip({required this.label, required this.showPlus});

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 38,
      padding: const EdgeInsets.symmetric(horizontal: 11),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(19),
        boxShadow: const [
          BoxShadow(
            color: Color(0x08000000),
            blurRadius: 6,
            offset: Offset(0, 2),
          ),
        ],
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Image.asset('assets/images/icon_diamond.png', width: 16, height: 16),
          const SizedBox(width: 7),
          Text(
            label,
            style: const TextStyle(
              fontFamily: AppFont.family,
              fontWeight: FontWeight.w600,
              fontSize: 13,
              color: AppColors.zinc900,
            ),
          ),
          if (showPlus) ...[
            const SizedBox(width: 4),
            Image.asset(
              'assets/images/icon_plus_circle.png',
              width: 16,
              height: 16,
            ),
          ],
        ],
      ),
    );
  }
}

class _BellChip extends StatelessWidget {
  const _BellChip();

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 38,
      height: 38,
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(19),
        boxShadow: const [
          BoxShadow(
            color: Color(0x08000000),
            blurRadius: 6,
            offset: Offset(0, 2),
          ),
        ],
      ),
      alignment: Alignment.center,
      child: const Icon(
        CupertinoIcons.bell,
        size: 18,
        color: AppColors.neutral950,
      ),
    );
  }
}
