import 'dart:ui' show ImageFilter;

import 'package:magmug/app_core.dart';

// =============================================================================

enum NotificationIconKind {
  match,
  chat,
  shield,
  likeYou,
  gift,
  bonus,
  expired,
  welcome,
}

@immutable
class NotificationItem {
  final String title;
  final String subtitle;
  final String time;
  final NotificationIconKind icon;
  final String? avatarAsset;
  final String? avatarInitialsName;
  final bool blurAvatar;

  const NotificationItem({
    required this.title,
    required this.subtitle,
    required this.time,
    required this.icon,
    this.avatarAsset,
    this.avatarInitialsName,
    this.blurAvatar = false,
  });
}

// ------ Promo cards -----------------------------------------------------------

class _GemPromoCard extends StatelessWidget {
  const _GemPromoCard();

  @override
  Widget build(BuildContext context) {
    return PressableScale(
      onTap: () {},
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
              height: 32,
              padding: const EdgeInsets.symmetric(horizontal: 14),
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
      onTap: () {},
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
              height: 32,
              padding: const EdgeInsets.symmetric(horizontal: 14),
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
      case NotificationIconKind.likeYou:
        bg = const Color(0xFFFFE7ED);
        child = const Icon(
          CupertinoIcons.heart_fill,
          size: 20,
          color: Color(0xFFFF6B8F),
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

  const _NotificationCard({required this.item});

  @override
  Widget build(BuildContext context) {
    return PressableScale(
      onTap: () {},
      scale: 0.99,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
        decoration: BoxDecoration(
          color: AppColors.white,
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
                    style: const TextStyle(
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
    if (item.avatarAsset != null) {
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
  const _NotificationsTopBar();

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
      child: Row(
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
          const Text(
            'Bildirimler',
            style: TextStyle(
              fontFamily: AppFont.family,
              fontWeight: FontWeight.w800,
              fontSize: 21.5,
              color: AppColors.black,
              letterSpacing: -0.5,
            ),
          ),
        ],
      ),
    );
  }
}

// ------ Screen: NotificationsScreen -------------------------------------------

class NotificationsScreen extends StatelessWidget {
  final bool empty;

  const NotificationsScreen({super.key, this.empty = false});

  @override
  Widget build(BuildContext context) {
    return CupertinoPageScaffold(
      backgroundColor: AppColors.neutral100,
      child: SafeArea(
        bottom: false,
        child: Column(
          children: [
            const _NotificationsTopBar(),
            Expanded(
              child: empty ? const _NotifEmptyBody() : const _NotifListBody(),
            ),
          ],
        ),
      ),
    );
  }
}

class _NotifEmptyBody extends StatelessWidget {
  const _NotifEmptyBody();

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        const Spacer(),
        Image.asset(
          'assets/images/notification_bell.png',
          width: 128,
          height: 128,
          fit: BoxFit.contain,
        ),
        const SizedBox(height: 20),
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
        const Spacer(flex: 2),
      ],
    );
  }
}

class _NotifListBody extends StatelessWidget {
  const _NotifListBody();

  static const List<NotificationItem> _today = [
    NotificationItem(
      icon: NotificationIconKind.match,
      title: 'Yeni eslesme!',
      subtitle: 'Eda seninle eslesmeyi kabul etti. Hemen mesaj gonder!',
      time: '12 dk once',
      avatarAsset: 'assets/images/portrait_eda.png',
    ),
    NotificationItem(
      icon: NotificationIconKind.chat,
      title: 'Eda sana mesaj gonderdi',
      subtitle: '"Bu hafta sonu ne yapiyorsun? Bulusali..."',
      time: '28 dk once',
      avatarAsset: 'assets/images/portrait_eda.png',
    ),
    NotificationItem(
      icon: NotificationIconKind.shield,
      title: 'Profil guvenlik ipucu',
      subtitle: 'Profilini tamamla ve daha fazla eslesme sansi yakala.',
      time: '3 sa once',
    ),
  ];

  static const List<NotificationItem> _yesterday = [
    NotificationItem(
      icon: NotificationIconKind.likeYou,
      title: 'Birisi seni begendi!',
      subtitle: "Kim oldugunu gormek icin Premium'a gec",
      time: 'Dun, 18:42',
      avatarAsset: 'assets/images/portrait_match.png',
      blurAvatar: true,
    ),
    NotificationItem(
      icon: NotificationIconKind.gift,
      title: 'Anna sana bir hediye gonderdi!',
      subtitle: 'Gul hediyesini kabul et ve tesekkur et',
      time: 'Dun, 14:20',
      avatarAsset: 'assets/images/portrait_match.png',
    ),
  ];

  static const List<NotificationItem> _thisWeek = [
    NotificationItem(
      icon: NotificationIconKind.bonus,
      title: 'Sana hediye geldi',
      subtitle: 'Her gun giris yap, ucretsiz tas kazan',
      time: '3 gun once',
    ),
    NotificationItem(
      icon: NotificationIconKind.expired,
      title: 'Eslesme suresi doldu',
      subtitle: 'Zeynep ile eslesme suresi doldu. Tekrar esles!',
      time: '4 gun once',
      avatarInitialsName: 'Zeynep Akbas',
    ),
    NotificationItem(
      icon: NotificationIconKind.chat,
      title: 'Zeynep sana mesaj gonderdi',
      subtitle: '"Fotograflarin cok guzel, neresi orasi?"',
      time: '5 gun once',
      avatarInitialsName: 'Zeynep Akbas',
    ),
    NotificationItem(
      icon: NotificationIconKind.welcome,
      title: "magmug'a hosgeldin!",
      subtitle: 'Profilini tamamla, eslesmeler seni bekliyor.',
      time: '1 hafta once',
    ),
  ];

  @override
  Widget build(BuildContext context) {
    return ListView(
      physics: const BouncingScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(16, 0, 16, 24),
      children: [
        const _NotifSectionHeader(label: 'BUGUN'),
        const _GemPromoCard(),
        ..._today.map(
          (n) => Padding(
            padding: const EdgeInsets.only(top: 8),
            child: _NotificationCard(item: n),
          ),
        ),
        const SizedBox(height: 20),
        const _NotifSectionHeader(label: 'DUN'),
        const _PremiumPromoCard(),
        ..._yesterday.map(
          (n) => Padding(
            padding: const EdgeInsets.only(top: 8),
            child: _NotificationCard(item: n),
          ),
        ),
        const SizedBox(height: 20),
        const _NotifSectionHeader(label: 'BU HAFTA'),
        ..._thisWeek.map(
          (n) => Padding(
            padding: const EdgeInsets.only(bottom: 8),
            child: _NotificationCard(item: n),
          ),
        ),
      ],
    );
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
