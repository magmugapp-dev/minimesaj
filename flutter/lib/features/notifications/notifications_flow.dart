import 'dart:ui' show ImageFilter;

import 'package:magmug/app_core.dart';
import 'package:magmug/features/chat/chat_flow.dart';
import 'package:magmug/features/match/match_flow.dart';
import 'package:magmug/features/profile/profile_flow.dart';

// =============================================================================

enum NotificationIconKind { match, chat, shield, gift, bonus, expired, welcome }

@immutable
class NotificationItem {
  final String id;
  final String title;
  final String subtitle;
  final String time;
  final NotificationIconKind icon;
  final String? avatarAsset;
  final String? avatarUrl;
  final String? avatarInitialsName;
  final bool blurAvatar;
  final bool isUnread;
  final AppNotification? notification;

  const NotificationItem({
    required this.id,
    required this.title,
    required this.subtitle,
    required this.time,
    required this.icon,
    this.avatarAsset,
    this.avatarUrl,
    this.avatarInitialsName,
    this.blurAvatar = false,
    this.isUnread = false,
    this.notification,
  });
}

final appNotificationsProvider =
    FutureProvider.autoDispose<List<AppNotification>>((ref) async {
      ref.watch(notificationsFeedRefreshProvider);
      final session = await ref.watch(appAuthProvider.future);
      final token = session?.token;
      if (token == null || token.trim().isEmpty) {
        return const [];
      }

      final api = AppAuthApi();
      try {
        return await api.fetchNotifications(token);
      } finally {
        api.close();
      }
    });

// ------ Promo cards -----------------------------------------------------------

class _GemPromoCard extends StatelessWidget {
  const _GemPromoCard();

  @override
  Widget build(BuildContext context) {
    return PressableScale(
      onTap: () {
        showCupertinoModalPopup<void>(
          context: context,
          builder: (_) => const JetonPurchaseSheet(),
        );
      },
      scale: 0.99,
      child: Container(
        padding: const EdgeInsets.fromLTRB(16, 14, 16, 14),
        decoration: BoxDecoration(
          color: AppColors.black,
          borderRadius: BorderRadius.circular(20),
          boxShadow: const [
            BoxShadow(
              color: Color(0x1F000000),
              blurRadius: 16,
              offset: Offset(0, 4),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Image.asset(
                  'assets/images/icon_diamond.png',
                  width: 14,
                  height: 14,
                ),
                const SizedBox(width: 6),
                const Text(
                  'OZEL FIRSAT',
                  style: TextStyle(
                    fontFamily: AppFont.family,
                    fontWeight: FontWeight.w700,
                    fontSize: 10.5,
                    color: Color(0xFFE8B84E),
                    letterSpacing: 1.2,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 6),
            const Text(
              'Bugun 50 tas al, 20 tas hediye kazan!',
              style: TextStyle(
                fontFamily: AppFont.family,
                fontWeight: FontWeight.w800,
                fontSize: 15,
                color: AppColors.white,
                height: 1.25,
              ),
            ),
            const SizedBox(height: 4),
            const Text(
              'Sinirli sure ile gecerli',
              style: TextStyle(
                fontFamily: AppFont.family,
                fontSize: 12,
                color: Color(0xFFBABABA),
              ),
            ),
            const SizedBox(height: 12),
            Container(
              constraints: const BoxConstraints(minHeight: 32),
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 7),
              decoration: BoxDecoration(
                color: AppColors.white.withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(20),
              ),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: const [
                  Text(
                    'Satin Al',
                    style: TextStyle(
                      fontFamily: AppFont.family,
                      fontWeight: FontWeight.w700,
                      fontSize: 13,
                      color: AppColors.white,
                    ),
                  ),
                  SizedBox(width: 6),
                  Icon(
                    CupertinoIcons.chevron_right,
                    size: 14,
                    color: AppColors.white,
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _PremiumPromoCard extends StatelessWidget {
  const _PremiumPromoCard();

  @override
  Widget build(BuildContext context) {
    return PressableScale(
      onTap: () {
        Navigator.of(context).push(cupertinoRoute(const PaywallScreen()));
      },
      scale: 0.99,
      child: Container(
        padding: const EdgeInsets.fromLTRB(16, 14, 16, 14),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(20),
          gradient: const LinearGradient(
            begin: Alignment.centerLeft,
            end: Alignment.centerRight,
            colors: [Color(0xFFFF9EC4), Color(0xFFB194F9)],
          ),
          boxShadow: const [
            BoxShadow(
              color: Color(0x29B194F9),
              blurRadius: 16,
              offset: Offset(0, 6),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'PREMIUM',
              style: TextStyle(
                fontFamily: AppFont.family,
                fontWeight: FontWeight.w700,
                fontSize: 10.5,
                color: Color(0xFFFFE6F0),
                letterSpacing: 1.2,
              ),
            ),
            const SizedBox(height: 6),
            const Text(
              'Sinirsiz mesaj ve ozel ozellikler seni bekliyor',
              style: TextStyle(
                fontFamily: AppFont.family,
                fontWeight: FontWeight.w800,
                fontSize: 15,
                color: AppColors.white,
                height: 1.25,
              ),
            ),
            const SizedBox(height: 12),
            Container(
              constraints: const BoxConstraints(minHeight: 32),
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 7),
              decoration: BoxDecoration(
                color: AppColors.white.withValues(alpha: 0.22),
                borderRadius: BorderRadius.circular(20),
              ),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: const [
                  Text(
                    'Kesfet',
                    style: TextStyle(
                      fontFamily: AppFont.family,
                      fontWeight: FontWeight.w700,
                      fontSize: 13,
                      color: AppColors.white,
                    ),
                  ),
                  SizedBox(width: 6),
                  Icon(
                    CupertinoIcons.chevron_right,
                    size: 14,
                    color: AppColors.white,
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// ------ Notification icon badge ----------------------------------------------

class _NotifIconBadge extends StatelessWidget {
  final NotificationIconKind kind;

  const _NotifIconBadge({required this.kind});

  @override
  Widget build(BuildContext context) {
    late Color bg;
    Widget child;
    switch (kind) {
      case NotificationIconKind.match:
        bg = const Color(0xFFE8EBFF);
        child = const Icon(
          CupertinoIcons.heart_fill,
          size: 20,
          color: AppColors.indigo,
        );
      case NotificationIconKind.chat:
        bg = const Color(0xFFF0F0F2);
        child = const Icon(
          CupertinoIcons.chat_bubble_fill,
          size: 20,
          color: Color(0xFF666666),
        );
      case NotificationIconKind.shield:
        bg = const Color(0xFFF0F0F2);
        child = const Icon(
          CupertinoIcons.shield_fill,
          size: 20,
          color: Color(0xFF666666),
        );
      case NotificationIconKind.gift:
        bg = const Color(0xFFFFE7E7);
        child = const Icon(
          CupertinoIcons.gift_fill,
          size: 20,
          color: Color(0xFFEF4444),
        );
      case NotificationIconKind.bonus:
        bg = const Color(0xFFE4F8EA);
        child = const Icon(
          CupertinoIcons.bolt_fill,
          size: 20,
          color: Color(0xFF22C55E),
        );
      case NotificationIconKind.expired:
        bg = const Color(0xFFF0F0F2);
        child = const Icon(
          CupertinoIcons.clock,
          size: 20,
          color: Color(0xFF666666),
        );
      case NotificationIconKind.welcome:
        bg = const Color(0xFFFFF3D0);
        child = const Icon(
          CupertinoIcons.hand_raised_fill,
          size: 20,
          color: Color(0xFFD97706),
        );
    }
    return Container(
      width: 40,
      height: 40,
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(20),
      ),
      alignment: Alignment.center,
      child: child,
    );
  }
}

// ------ Notification card -----------------------------------------------------

class _NotificationCard extends StatelessWidget {
  final NotificationItem item;
  final VoidCallback? onTap;

  const _NotificationCard({required this.item, this.onTap});

  @override
  Widget build(BuildContext context) {
    return PressableScale(
      onTap: onTap ?? () {},
      scale: 0.99,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
        decoration: BoxDecoration(
          color: item.isUnread ? const Color(0xFFF8F8FF) : AppColors.white,
          borderRadius: BorderRadius.circular(18),
          boxShadow: const [
            BoxShadow(
              color: Color(0x08000000),
              blurRadius: 6,
              offset: Offset(0, 1),
            ),
          ],
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            _NotifIconBadge(kind: item.icon),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    item.title,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      fontFamily: AppFont.family,
                      fontWeight: FontWeight.w700,
                      fontSize: 14,
                      color: AppColors.black,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    item.subtitle,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontFamily: AppFont.family,
                      fontSize: 12.5,
                      height: 1.4,
                      color: Color(0xFF666666),
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    item.time,
                    style: const TextStyle(
                      fontFamily: AppFont.family,
                      fontSize: 11,
                      color: Color(0xFFAAAAAA),
                    ),
                  ),
                ],
              ),
            ),
            if (item.avatarAsset != null ||
                item.avatarInitialsName != null) ...[
              const SizedBox(width: 10),
              _NotifAvatar(item: item),
            ],
          ],
        ),
      ),
    );
  }
}

class _NotifAvatar extends StatelessWidget {
  final NotificationItem item;

  const _NotifAvatar({required this.item});

  @override
  Widget build(BuildContext context) {
    Widget avatar;
    if (item.avatarUrl != null && item.avatarUrl!.trim().isNotEmpty) {
      avatar = ClipOval(
        child: Image.network(
          item.avatarUrl!,
          width: 40,
          height: 40,
          fit: BoxFit.cover,
        ),
      );
    } else if (item.avatarAsset != null) {
      avatar = ClipOval(
        child: Image.asset(
          item.avatarAsset!,
          width: 40,
          height: 40,
          fit: BoxFit.cover,
        ),
      );
    } else {
      avatar = AvatarCircle(name: item.avatarInitialsName ?? '?', size: 40);
    }

    if (item.blurAvatar) {
      return SizedBox(
        width: 40,
        height: 40,
        child: Stack(
          children: [
            avatar,
            Positioned.fill(
              child: ClipOval(
                child: BackdropFilter(
                  filter: ImageFilter.blur(sigmaX: 6, sigmaY: 6),
                  child: Container(
                    color: AppColors.white.withValues(alpha: 0.25),
                    alignment: Alignment.center,
                    child: const Text(
                      '?',
                      style: TextStyle(
                        fontFamily: AppFont.family,
                        fontWeight: FontWeight.w800,
                        fontSize: 14,
                        color: Color(0xFF666666),
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      );
    }

    // Gradient halkaya sahip (eda/anna gibi aktif kisiler icin hafif renkli ring)
    return Container(
      width: 44,
      height: 44,
      padding: const EdgeInsets.all(2),
      decoration: const BoxDecoration(
        shape: BoxShape.circle,
        gradient: LinearGradient(
          colors: [Color(0xFF5C6BFF), Color(0xFF2DD4A0)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
      ),
      child: Container(
        decoration: const BoxDecoration(
          color: AppColors.white,
          shape: BoxShape.circle,
        ),
        padding: const EdgeInsets.all(1.5),
        child: ClipOval(child: avatar),
      ),
    );
  }
}

// ------ Top bar ---------------------------------------------------------------

class _NotificationsTopBar extends StatelessWidget {
  final bool hasUnread;
  final VoidCallback? onMarkAllRead;

  const _NotificationsTopBar({this.hasUnread = false, this.onMarkAllRead});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
      child: LayoutBuilder(
        builder: (context, constraints) {
          final compact = constraints.maxWidth < 360;

          return Row(
            children: [
              PressableScale(
                onTap: () => Navigator.of(context).maybePop(),
                scale: 0.9,
                child: Container(
                  width: 36,
                  height: 36,
                  decoration: const BoxDecoration(
                    color: AppColors.white,
                    shape: BoxShape.circle,
                    boxShadow: [
                      BoxShadow(
                        color: Color(0x0F000000),
                        blurRadius: 6,
                        offset: Offset(0, 2),
                      ),
                    ],
                  ),
                  alignment: Alignment.center,
                  child: const Icon(
                    CupertinoIcons.chevron_back,
                    size: 18,
                    color: AppColors.black,
                  ),
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Text(
                  'Bildirimler',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    fontFamily: AppFont.family,
                    fontWeight: FontWeight.w800,
                    fontSize: compact ? 19 : 21.5,
                    color: AppColors.black,
                    letterSpacing: -0.5,
                  ),
                ),
              ),
              if (hasUnread) ...[
                const SizedBox(width: 8),
                PressableScale(
                  onTap: onMarkAllRead ?? () {},
                  scale: 0.94,
                  child: const Padding(
                    padding: EdgeInsets.symmetric(horizontal: 4, vertical: 8),
                    child: Text(
                      'Tumunu oku',
                      style: TextStyle(
                        fontFamily: AppFont.family,
                        fontWeight: FontWeight.w700,
                        fontSize: 13,
                        color: AppColors.indigo,
                      ),
                    ),
                  ),
                ),
              ],
            ],
          );
        },
      ),
    );
  }
}

// ------ Screen: NotificationsScreen -------------------------------------------

class NotificationsScreen extends ConsumerWidget {
  final bool empty;

  const NotificationsScreen({super.key, this.empty = false});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    if (empty) {
      return const CupertinoPageScaffold(
        backgroundColor: AppColors.neutral100,
        child: SafeArea(
          bottom: false,
          child: Column(
            children: [
              _NotificationsTopBar(),
              Expanded(child: _NotifEmptyBody()),
            ],
          ),
        ),
      );
    }

    final notificationsAsync = ref.watch(appNotificationsProvider);
    final hasUnread =
        notificationsAsync.asData?.value.any((item) => !item.isRead) == true;

    return CupertinoPageScaffold(
      backgroundColor: AppColors.neutral100,
      child: SafeArea(
        bottom: false,
        child: Column(
          children: [
            _NotificationsTopBar(
              hasUnread: hasUnread,
              onMarkAllRead: hasUnread
                  ? () => _markAllAsRead(context, ref)
                  : null,
            ),
            Expanded(
              child: _NotifListBody(notificationsAsync: notificationsAsync),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _markAllAsRead(BuildContext context, WidgetRef ref) async {
    final session = ref.read(appAuthProvider).asData?.value;
    final token = session?.token;
    if (token == null || token.trim().isEmpty) {
      return;
    }

    final api = AppAuthApi();
    try {
      await api.markAllNotificationsRead(token);
      ref.read(notificationsFeedRefreshProvider.notifier).state++;
    } catch (error) {
      if (!context.mounted) {
        return;
      }
      showCupertinoDialog<void>(
        context: context,
        builder: (context) => CupertinoAlertDialog(
          title: const Text('Hata'),
          content: Text(AppAuthErrorFormatter.messageFrom(error)),
          actions: [
            CupertinoDialogAction(
              onPressed: () => Navigator.of(context).pop(),
              child: const Text('Tamam'),
            ),
          ],
        ),
      );
    } finally {
      api.close();
    }
  }
}

class _NotifEmptyBody extends StatelessWidget {
  const _NotifEmptyBody();

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final compact = constraints.maxHeight < 640;
        final imageSize = constraints.maxWidth < 360 ? 104.0 : 128.0;

        return SingleChildScrollView(
          physics: const BouncingScrollPhysics(),
          padding: EdgeInsets.symmetric(vertical: compact ? 24 : 40),
          child: ConstrainedBox(
            constraints: BoxConstraints(minHeight: constraints.maxHeight),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Image.asset(
                  'assets/images/notification_bell.png',
                  width: imageSize,
                  height: imageSize,
                  fit: BoxFit.contain,
                ),
                SizedBox(height: compact ? 16 : 20),
                const Text(
                  'Henuz bildirim yok',
                  style: TextStyle(
                    fontFamily: AppFont.family,
                    fontWeight: FontWeight.w700,
                    fontSize: 16,
                    color: AppColors.neutral950,
                  ),
                ),
                const SizedBox(height: 8),
                const Padding(
                  padding: EdgeInsets.symmetric(horizontal: 32),
                  child: Text(
                    "Birinin ID'sini yada ismini aratarak sohbete basla veya\nKesfet'ten yeni kisilerle esles",
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      fontFamily: AppFont.family,
                      fontSize: 13,
                      height: 1.4,
                      color: AppColors.neutral600,
                    ),
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}

class _NotifListBody extends ConsumerWidget {
  final AsyncValue<List<AppNotification>> notificationsAsync;

  const _NotifListBody({required this.notificationsAsync});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return notificationsAsync.when(
      data: (notifications) {
        if (notifications.isEmpty) {
          return const _NotifEmptyBody();
        }

        final today = <NotificationItem>[];
        final yesterday = <NotificationItem>[];
        final thisWeek = <NotificationItem>[];
        final earlier = <NotificationItem>[];

        for (final notification in notifications) {
          final item = _itemFromNotification(notification);
          final bucket = _bucketLabel(notification.createdAt);
          switch (bucket) {
            case 'BUGUN':
              today.add(item);
            case 'DUN':
              yesterday.add(item);
            case 'BU HAFTA':
              thisWeek.add(item);
            default:
              earlier.add(item);
          }
        }

        return ListView(
          physics: const BouncingScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 24),
          children: [
            if (today.isNotEmpty) ...[
              const _NotifSectionHeader(label: 'BUGUN'),
              const _GemPromoCard(),
              ...today.map(
                (item) => Padding(
                  padding: const EdgeInsets.only(top: 8),
                  child: _NotificationCard(
                    item: item,
                    onTap: () => _openNotification(context, ref, item),
                  ),
                ),
              ),
              const SizedBox(height: 20),
            ],
            if (yesterday.isNotEmpty) ...[
              const _NotifSectionHeader(label: 'DUN'),
              const _PremiumPromoCard(),
              ...yesterday.map(
                (item) => Padding(
                  padding: const EdgeInsets.only(top: 8),
                  child: _NotificationCard(
                    item: item,
                    onTap: () => _openNotification(context, ref, item),
                  ),
                ),
              ),
              const SizedBox(height: 20),
            ],
            if (thisWeek.isNotEmpty) ...[
              const _NotifSectionHeader(label: 'BU HAFTA'),
              ...thisWeek.map(
                (item) => Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: _NotificationCard(
                    item: item,
                    onTap: () => _openNotification(context, ref, item),
                  ),
                ),
              ),
            ],
            if (earlier.isNotEmpty) ...[
              const SizedBox(height: 12),
              const _NotifSectionHeader(label: 'DAHA ONCE'),
              ...earlier.map(
                (item) => Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: _NotificationCard(
                    item: item,
                    onTap: () => _openNotification(context, ref, item),
                  ),
                ),
              ),
            ],
          ],
        );
      },
      loading: () =>
          const Center(child: CupertinoActivityIndicator(radius: 14)),
      error: (error, _) => Center(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 24),
          child: Text(
            AppAuthErrorFormatter.messageFrom(error),
            textAlign: TextAlign.center,
            style: const TextStyle(
              fontFamily: AppFont.family,
              fontSize: 13,
              color: AppColors.neutral600,
            ),
          ),
        ),
      ),
    );
  }

  NotificationItem _itemFromNotification(AppNotification notification) {
    final type = notification.type?.trim().toLowerCase();
    final message = notification.message.trim();
    final avatarName = notification.avatarName;

    return NotificationItem(
      id: notification.id,
      title: notification.title,
      subtitle: message.isEmpty ? 'Yeni bildirim' : message,
      time: _formatNotificationTime(notification.createdAt),
      icon: switch (type) {
        'yeni_mesaj' => NotificationIconKind.chat,
        'yeni_eslesme' => NotificationIconKind.match,
        'hediye' || 'hediye_alindi' => NotificationIconKind.gift,
        'bonus' => NotificationIconKind.bonus,
        'sure_doldu' => NotificationIconKind.expired,
        'guvenlik' => NotificationIconKind.shield,
        _ => NotificationIconKind.welcome,
      },
      avatarUrl: notification.avatarUrl,
      avatarInitialsName: avatarName,
      blurAvatar: false,
      isUnread: !notification.isRead,
      notification: notification,
    );
  }

  String _bucketLabel(DateTime? dateTime) {
    if (dateTime == null) {
      return 'DAHA ONCE';
    }

    final now = DateTime.now();
    final target = DateTime(dateTime.year, dateTime.month, dateTime.day);
    final today = DateTime(now.year, now.month, now.day);
    final dayDifference = today.difference(target).inDays;

    if (dayDifference <= 0) {
      return 'BUGUN';
    }
    if (dayDifference == 1) {
      return 'DUN';
    }
    if (dayDifference < 7) {
      return 'BU HAFTA';
    }
    return 'DAHA ONCE';
  }

  String _formatNotificationTime(DateTime? dateTime) {
    if (dateTime == null) {
      return 'Az once';
    }

    final now = DateTime.now();
    final difference = now.difference(dateTime);
    if (difference.inMinutes < 1) {
      return 'Simdi';
    }
    if (difference.inMinutes < 60) {
      return '${difference.inMinutes} dk once';
    }
    if (difference.inHours < 24 &&
        now.day == dateTime.day &&
        now.month == dateTime.month &&
        now.year == dateTime.year) {
      return '${difference.inHours} sa once';
    }

    final yesterday = now.subtract(const Duration(days: 1));
    final minute = dateTime.minute.toString().padLeft(2, '0');
    final hour = dateTime.hour.toString().padLeft(2, '0');
    if (yesterday.day == dateTime.day &&
        yesterday.month == dateTime.month &&
        yesterday.year == dateTime.year) {
      return 'Dun, $hour:$minute';
    }
    if (difference.inDays < 7) {
      return '${difference.inDays} gun once';
    }

    final day = dateTime.day.toString().padLeft(2, '0');
    final month = dateTime.month.toString().padLeft(2, '0');
    return '$day.$month.${dateTime.year}';
  }

  Future<void> _openNotification(
    BuildContext context,
    WidgetRef ref,
    NotificationItem item,
  ) async {
    final notification = item.notification;
    if (notification == null) {
      return;
    }

    final session = ref.read(appAuthProvider).asData?.value;
    final token = session?.token;
    final currentUserId = session?.user?.id;
    if (token == null || token.trim().isEmpty || currentUserId == null) {
      return;
    }

    final api = AppAuthApi();
    try {
      if (!notification.isRead) {
        await api.markNotificationRead(token, notification.id);
        ref.read(notificationsFeedRefreshProvider.notifier).state++;
      }

      final route = notification.route?.trim().toLowerCase();
      if (route == 'chat') {
        final conversationId = notification.conversationId;
        if (conversationId == null) {
          return;
        }

        final conversations = await api.fetchConversations(
          token,
          currentUserId: currentUserId,
        );
        AppConversationPreview? conversation;
        for (final item in conversations) {
          if (item.id == conversationId) {
            conversation = item;
            break;
          }
        }
        if (!context.mounted || conversation == null) {
          return;
        }

        await Navigator.of(context).push(
          cupertinoRoute(
            ChatScreen(
              mode: ChatScreenMode.messages,
              conversation: conversation,
            ),
          ),
        );
        return;
      }

      if (route == 'matches') {
        if (!context.mounted) {
          return;
        }

        await Navigator.of(context).push(
          cupertinoRoute(const MatchFoundScreen(theme: MatchFoundTheme.normal)),
        );
        return;
      }

      if (route == 'wallet') {
        if (!context.mounted) {
          return;
        }

        await showCupertinoModalPopup<void>(
          context: context,
          builder: (_) => const JetonPurchaseSheet(),
        );
        return;
      }

      if (route == 'incoming_likes') {
        if (!context.mounted) {
          return;
        }

        await Navigator.of(context).push(cupertinoRoute(const PaywallScreen()));
      }
    } catch (error) {
      if (!context.mounted) {
        return;
      }
      showCupertinoDialog<void>(
        context: context,
        builder: (context) => CupertinoAlertDialog(
          title: const Text('Hata'),
          content: Text(AppAuthErrorFormatter.messageFrom(error)),
          actions: [
            CupertinoDialogAction(
              onPressed: () => Navigator.of(context).pop(),
              child: const Text('Tamam'),
            ),
          ],
        ),
      );
    } finally {
      api.close();
    }
  }
}

class _NotifSectionHeader extends StatelessWidget {
  final String label;

  const _NotifSectionHeader({required this.label});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(left: 6, bottom: 8),
      child: Text(
        label,
        style: const TextStyle(
          fontFamily: AppFont.family,
          fontWeight: FontWeight.w700,
          fontSize: 11,
          color: Color(0xFFAAAAAA),
          letterSpacing: 1.2,
        ),
      ),
    );
  }
}

// =============================================================================
// Profile & Settings module + Paywall + Jeton
