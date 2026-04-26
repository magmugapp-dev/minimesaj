import 'package:magmug/app_core.dart';
import 'package:magmug/features/blocked_users/domain/entities/blocked_user.dart';
import 'package:magmug/features/profile/widgets/profile_settings_widgets.dart';
import 'package:magmug/l10n/app_localizations.dart';

class ProfileFaqItem extends StatefulWidget {
  final String question;
  final String answer;

  const ProfileFaqItem({
    super.key,
    required this.question,
    required this.answer,
  });

  @override
  State<ProfileFaqItem> createState() => _ProfileFaqItemState();
}

class _ProfileFaqItemState extends State<ProfileFaqItem> {
  bool _expanded = false;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          PressableScale(
            onTap: () => setState(() => _expanded = !_expanded),
            scale: 0.99,
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 4),
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      widget.question,
                      style: const TextStyle(
                        fontFamily: AppFont.family,
                        fontWeight: FontWeight.w700,
                        fontSize: 13.5,
                        color: AppColors.black,
                      ),
                    ),
                  ),
                  AnimatedRotation(
                    turns: _expanded ? 0.5 : 0,
                    duration: const Duration(milliseconds: 180),
                    child: const Icon(
                      CupertinoIcons.chevron_down,
                      size: 14,
                      color: Color(0xFF666666),
                    ),
                  ),
                ],
              ),
            ),
          ),
          AnimatedCrossFade(
            duration: const Duration(milliseconds: 180),
            firstChild: const SizedBox.shrink(),
            secondChild: Padding(
              padding: const EdgeInsets.only(top: 4, right: 22),
              child: Text(
                widget.answer,
                style: const TextStyle(
                  fontFamily: AppFont.family,
                  fontSize: 13,
                  height: 1.4,
                  color: Color(0xFF555555),
                ),
              ),
            ),
            crossFadeState: _expanded
                ? CrossFadeState.showSecond
                : CrossFadeState.showFirst,
          ),
        ],
      ),
    );
  }
}

class ProfileBlockedUserRow extends StatelessWidget {
  final BlockedUser user;
  final VoidCallback onUnblock;

  const ProfileBlockedUserRow({
    super.key,
    required this.user,
    required this.onUnblock,
  });

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;

    Widget avatar = AvatarCircle(name: user.displayName, size: 44);
    if (user.profileImageUrl != null && user.profileImageUrl!.isNotEmpty) {
      avatar = CachedAppImage(
        imageUrl: user.profileImageUrl,
        width: 44,
        height: 44,
        cacheWidth: 88,
        cacheHeight: 88,
        errorBuilder: (_) => AvatarCircle(name: user.displayName, size: 44),
      );
    }

    return Row(
      children: [
        ClipOval(child: avatar),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                user.displayName,
                style: const TextStyle(
                  fontFamily: AppFont.family,
                  fontWeight: FontWeight.w700,
                  fontSize: 14,
                  color: AppColors.black,
                ),
              ),
              Text(
                user.handle,
                style: const TextStyle(
                  fontFamily: AppFont.family,
                  fontSize: 12,
                  color: Color(0xFF999999),
                ),
              ),
            ],
          ),
        ),
        PressableScale(
          onTap: onUnblock,
          scale: 0.96,
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
            decoration: BoxDecoration(
              color: const Color(0x1AEF4444),
              borderRadius: BorderRadius.circular(20),
            ),
            child: Text(
              l10n.unblockAction,
              style: const TextStyle(
                fontFamily: AppFont.family,
                fontWeight: FontWeight.w700,
                fontSize: 12.5,
                color: Color(0xFFEF4444),
              ),
            ),
          ),
        ),
      ],
    );
  }
}

class ProfileBlockedUsersSheetView extends StatelessWidget {
  final String title;
  final String emptyMessage;
  final String retryLabel;
  final AsyncValue<List<BlockedUser>> blockedUsersAsync;
  final VoidCallback onRetry;
  final void Function(BlockedUser user) onUnblock;
  final String Function(Object error) errorMessageBuilder;

  const ProfileBlockedUsersSheetView({
    super.key,
    required this.title,
    required this.emptyMessage,
    required this.retryLabel,
    required this.blockedUsersAsync,
    required this.onRetry,
    required this.onUnblock,
    required this.errorMessageBuilder,
  });

  @override
  Widget build(BuildContext context) {
    return ConstrainedBox(
      constraints: const BoxConstraints(maxHeight: 420),
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
              fontSize: 18,
              color: AppColors.black,
            ),
          ),
          const SizedBox(height: 16),
          Flexible(
            child: blockedUsersAsync.when(
              data: (users) {
                if (users.isEmpty) {
                  return Center(
                    child: Padding(
                      padding: const EdgeInsets.symmetric(vertical: 24),
                      child: Text(
                        emptyMessage,
                        textAlign: TextAlign.center,
                        style: const TextStyle(
                          fontFamily: AppFont.family,
                          fontSize: 13.5,
                          height: 1.5,
                          color: AppColors.gray,
                        ),
                      ),
                    ),
                  );
                }

                return ListView.separated(
                  shrinkWrap: true,
                  physics: const BouncingScrollPhysics(),
                  itemCount: users.length,
                  separatorBuilder: (_, _) => const SizedBox(height: 12),
                  itemBuilder: (context, index) {
                    final user = users[index];
                    return ProfileBlockedUserRow(
                      user: user,
                      onUnblock: () => onUnblock(user),
                    );
                  },
                );
              },
              loading: () => const Center(
                child: Padding(
                  padding: EdgeInsets.symmetric(vertical: 24),
                  child: CupertinoActivityIndicator(radius: 14),
                ),
              ),
              error: (error, _) => Center(
                child: Padding(
                  padding: const EdgeInsets.symmetric(vertical: 8),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        errorMessageBuilder(error),
                        textAlign: TextAlign.center,
                        style: const TextStyle(
                          fontFamily: AppFont.family,
                          fontSize: 13.5,
                          height: 1.5,
                          color: AppColors.gray,
                        ),
                      ),
                      const SizedBox(height: 12),
                      PressableScale(
                        onTap: onRetry,
                        child: Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 14,
                            vertical: 10,
                          ),
                          decoration: BoxDecoration(
                            color: AppColors.grayField,
                            borderRadius: BorderRadius.circular(20),
                          ),
                          child: Text(
                            retryLabel,
                            style: const TextStyle(
                              fontFamily: AppFont.family,
                              fontWeight: FontWeight.w700,
                              fontSize: 12.5,
                              color: AppColors.black,
                            ),
                          ),
                        ),
                      ),
                    ],
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
