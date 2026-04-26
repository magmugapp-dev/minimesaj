import 'dart:ui' show ImageFilter;

import 'package:magmug/app_core.dart';
import 'package:magmug/features/chat/chat_local_store.dart';
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

final appNotificationsProvider = FutureProvider<List<AppNotification>>((
  ref,
) async {
  ref.watch(notificationsFeedRefreshProvider);
  final session = await ref.watch(appAuthProvider.future);
  final token = session?.token;
  final ownerUserId = session?.user?.id;
  if (token == null || token.trim().isEmpty || ownerUserId == null) {
    return const [];
  }

  return AppRepository.instance.notifications(
    token: token,
    ownerUserId: ownerUserId,
  );
});

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
        child: CachedAppImage(
          imageUrl: item.avatarUrl,
          width: 40,
          height: 40,
          cacheWidth: 80,
          cacheHeight: 80,
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
                  AppRuntimeText.instance.t(
                    'notifications.title',
                    'Bildirimler',
                  ),
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
                  child: Padding(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 4,
                      vertical: 8,
                    ),
                    child: Text(
                      AppRuntimeText.instance.t(
                        'notifications.mark_all_read',
                        'Tumunu oku',
                      ),
                      style: const TextStyle(
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
      final ownerUserId = session?.user?.id;
      if (ownerUserId != null) {
        AppRepository.instance.invalidateNotifications(ownerUserId);
      }
      ref.read(notificationsFeedRefreshProvider.notifier).state++;
    } catch (error) {
      if (!context.mounted) {
        return;
      }
      showCupertinoDialog<void>(
        context: context,
        builder: (context) => CupertinoAlertDialog(
          title: Text(AppRuntimeText.instance.t('commonError', 'Hata')),
          content: Text(AppAuthErrorFormatter.messageFrom(error)),
          actions: [
            CupertinoDialogAction(
              onPressed: () => Navigator.of(context).pop(),
              child: Text(AppRuntimeText.instance.t('commonOk', 'Tamam')),
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
                Text(
                  AppRuntimeText.instance.t(
                    'notifications.empty.title',
                    'Henuz bildirim yok',
                  ),
                  style: const TextStyle(
                    fontFamily: AppFont.family,
                    fontWeight: FontWeight.w700,
                    fontSize: 16,
                    color: AppColors.neutral950,
                  ),
                ),
                const SizedBox(height: 8),
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 32),
                  child: Text(
                    AppRuntimeText.instance.t(
                      'notifications.empty.subtitle',
                      'Bildirimlerin burada gorunecek.',
                    ),
                    textAlign: TextAlign.center,
                    style: const TextStyle(
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
        final today = notifications
            .where((notification) => _isToday(notification.createdAt))
            .map(_itemFromNotification)
            .toList();

        if (today.isEmpty) {
          return const _NotifEmptyBody();
        }

        return ListView(
          physics: const BouncingScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 24),
          children: [
            _NotifSectionHeader(
              label: AppRuntimeText.instance.t(
                'notifications.section.today',
                'BUGUN',
              ),
            ),
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
      title: _notificationTitle(notification, type),
      subtitle: message.isEmpty
          ? AppRuntimeText.instance.t(
              'notifications.fallback_message',
              'Yeni bildirim',
            )
          : message,
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

  String _notificationTitle(AppNotification notification, String? type) {
    final runtime = AppRuntimeText.instance;
    return switch (type) {
      'yeni_eslesme' => runtime.t(
        'notifications.type.new_match',
        'Yeni eslesme',
      ),
      'yeni_mesaj' => runtime.t('notifications.type.new_message', 'Yeni mesaj'),
      'hediye' ||
      'hediye_alindi' => runtime.t('notifications.type.gift', 'Yeni hediye'),
      'bonus' => runtime.t('notifications.type.bonus', 'Bonus'),
      'sure_doldu' => runtime.t('notifications.type.expired', 'Sure doldu'),
      'guvenlik' => runtime.t('notifications.type.security', 'Guvenlik'),
      _ =>
        notification.title.trim().isNotEmpty
            ? notification.title.trim()
            : runtime.t('notifications.type.default', 'Bildirim'),
    };
  }

  bool _isToday(DateTime? dateTime) {
    if (dateTime == null) {
      return false;
    }

    final now = DateTime.now();
    final target = DateTime(dateTime.year, dateTime.month, dateTime.day);
    final today = DateTime(now.year, now.month, now.day);
    return target == today;
  }

  String _formatNotificationTime(DateTime? dateTime) {
    final runtime = AppRuntimeText.instance;
    if (dateTime == null) {
      return runtime.t('notifications.time.just_now', 'Az once');
    }

    final now = DateTime.now();
    final difference = now.difference(dateTime);
    if (difference.inMinutes < 1) {
      return runtime.t('notifications.time.now', 'Simdi');
    }
    if (difference.inMinutes < 60) {
      return runtime.t(
        'notifications.time.minutes_ago',
        '{count} dk once',
        args: {'count': difference.inMinutes},
      );
    }
    if (difference.inHours < 24 &&
        now.day == dateTime.day &&
        now.month == dateTime.month &&
        now.year == dateTime.year) {
      return runtime.t(
        'notifications.time.hours_ago',
        '{count} sa once',
        args: {'count': difference.inHours},
      );
    }

    final yesterday = now.subtract(const Duration(days: 1));
    final minute = dateTime.minute.toString().padLeft(2, '0');
    final hour = dateTime.hour.toString().padLeft(2, '0');
    if (yesterday.day == dateTime.day &&
        yesterday.month == dateTime.month &&
        yesterday.year == dateTime.year) {
      return runtime.t(
        'notifications.time.yesterday_at',
        'Dun, {time}',
        args: {'time': '$hour:$minute'},
      );
    }
    if (difference.inDays < 7) {
      return runtime.t(
        'notifications.time.days_ago',
        '{count} gun once',
        args: {'count': difference.inDays},
      );
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
        AppRepository.instance.invalidateNotifications(currentUserId);
        ref.read(notificationsFeedRefreshProvider.notifier).state++;
      }

      final route = notification.route?.trim().toLowerCase();
      if (route == 'chat') {
        final conversationId = notification.conversationId;
        if (conversationId == null) {
          return;
        }

        var conversation = await ChatLocalStore.instance.getConversationPreview(
          conversationId,
          ownerUserId: currentUserId,
        );
        if (conversation == null) {
          await AppCacheSyncCoordinator.instance.reconcile(
            token: token,
            ownerUserId: currentUserId,
            force: true,
          );
          conversation = await ChatLocalStore.instance.getConversationPreview(
            conversationId,
            ownerUserId: currentUserId,
          );
        }
        if (conversation == null) {
          final bootstrap = await AppBootstrapCoordinator.instance.bootstrap(
            token,
          );
          if (bootstrap.user.id != currentUserId) {
            return;
          }
          for (final item in bootstrap.conversations) {
            if (item.id == conversationId) {
              conversation = item;
              break;
            }
          }
        }
        if (!context.mounted || conversation == null) {
          return;
        }

        await Navigator.of(context).push(
          chatRoute(
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
          title: Text(AppRuntimeText.instance.t('commonError', 'Hata')),
          content: Text(AppAuthErrorFormatter.messageFrom(error)),
          actions: [
            CupertinoDialogAction(
              onPressed: () => Navigator.of(context).pop(),
              child: Text(AppRuntimeText.instance.t('commonOk', 'Tamam')),
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
