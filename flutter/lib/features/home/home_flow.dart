import 'dart:io';

import 'package:magmug/app_core.dart';

// =============================================================================

enum HomeMode { empty, list, listWithBanner }

@immutable
class ChatPreview {
  final String name;
  final String? lastMessage;
  final String? statusText;
  final String time;
  final int unread;
  final bool myMessageRead;
  final bool online;
  final bool forceInitials;

  const ChatPreview({
    required this.name,
    required this.time,
    this.lastMessage,
    this.statusText,
    this.unread = 0,
    this.myMessageRead = false,
    this.online = false,
    this.forceInitials = false,
  });
}

const List<ChatPreview> _mockChats = [
  ChatPreview(
    name: 'Alara Gunes',
    lastMessage: 'Nasilsin?',
    time: '12:25',
    myMessageRead: true,
  ),
  ChatPreview(
    name: 'Fatih Durmaz',
    lastMessage: 'Bulusalim mi bro',
    time: '12:24',
    unread: 5,
    online: true,
  ),
  ChatPreview(
    name: 'Ferhat Tufekci',
    lastMessage: 'Para gonderir misin kanka',
    time: '12:25',
    myMessageRead: true,
  ),
  ChatPreview(
    name: 'Ferhat Tufekci',
    lastMessage: 'Para gonderir misin kanka',
    time: '12:25',
    myMessageRead: true,
    forceInitials: true,
  ),
  ChatPreview(
    name: 'Selim Ozbek',
    statusText: 'Yaziyor...',
    time: '12:25',
    online: true,
  ),
  ChatPreview(
    name: 'Yagmur Lale',
    lastMessage: 'Gunaydin',
    time: '12:24',
    unread: 1,
    online: true,
  ),
  ChatPreview(
    name: 'Mehmet Degirmenci',
    lastMessage: 'Para gonderir misin kanka',
    time: '12:25',
    myMessageRead: true,
  ),
  ChatPreview(
    name: 'Yagmur Duru',
    statusText: 'Sesli mesaj kaydediliyor...',
    time: '12:25',
  ),
];

class _AvatarColors {
  static const List<Color> palette = [
    Color(0xFFA594F9), // mor
    Color(0xFFFFB4C6), // pembe
    Color(0xFFFDB384), // peach
    Color(0xFFFF9794), // coral
    Color(0xFFAEDFF7), // light blue
    Color(0xFFB6E0B8), // green
    Color(0xFFFFE4A5), // yellow
    Color(0xFFC4C9FF), // lilac
    Color(0xFF9AA2B1), // slate
  ];

  static Color forName(String name) {
    var hash = 0;
    for (final rune in name.runes) {
      hash = (hash * 31 + rune) & 0x7fffffff;
    }
    return palette[hash % palette.length];
  }
}

String _initialsOf(String fullName) {
  final parts = fullName
      .trim()
      .split(RegExp(r'\s+'))
      .where((p) => p.isNotEmpty)
      .toList();
  if (parts.isEmpty) return '?';
  if (parts.length == 1) {
    final s = parts.first;
    return s.substring(0, s.length >= 2 ? 2 : 1).toUpperCase();
  }
  return (parts.first.substring(0, 1) + parts.last.substring(0, 1))
      .toUpperCase();
}

class _AvatarCircle extends StatelessWidget {
  final String name;
  final double size;
  final bool online;

  const _AvatarCircle({
    required this.name,
    this.size = 52,
    this.online = false,
  });

  @override
  Widget build(BuildContext context) {
    final base = _AvatarColors.forName(name);
    return SizedBox(
      width: size,
      height: size,
      child: Stack(
        children: [
          Container(
            width: size,
            height: size,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              gradient: LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: [base.withValues(alpha: 0.65), base],
              ),
            ),
            alignment: Alignment.center,
            child: Text(
              _initialsOf(name),
              style: TextStyle(
                fontFamily: AppFont.family,
                fontWeight: FontWeight.w700,
                fontSize: size * 0.36,
                color: AppColors.white,
                letterSpacing: 0.2,
              ),
            ),
          ),
          if (online)
            Positioned(
              right: 1,
              bottom: 1,
              child: Container(
                width: 12,
                height: 12,
                decoration: BoxDecoration(
                  color: AppColors.onlineGreen,
                  shape: BoxShape.circle,
                  border: Border.all(color: AppColors.neutral100, width: 2),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _HomeTopBar extends ConsumerWidget {
  const _HomeTopBar();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final data = ref.watch(onboardProvider);
    final displayName = data.name.isEmpty ? 'Sen' : data.name;

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
      child: Row(
        children: [
          _ProfileAvatar(name: displayName, photoPath: data.photoPath),
          const Spacer(),
          const _TopChip(label: 'Premium', showPlus: false),
          const SizedBox(width: 8),
          const _TopChip(label: '0', showPlus: true),
          const SizedBox(width: 8),
          const _BellChip(),
        ],
      ),
    );
  }
}

class _ProfileAvatar extends StatelessWidget {
  final String name;
  final String? photoPath;

  const _ProfileAvatar({required this.name, this.photoPath});

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
    return _AvatarCircle(name: name, size: 40);
  }
}

class _TopChip extends StatelessWidget {
  final String label;
  final bool showPlus;

  const _TopChip({required this.label, required this.showPlus});

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 40,
      padding: const EdgeInsets.symmetric(horizontal: 12),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(20),
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
          const SizedBox(width: 8),
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
      width: 40,
      height: 40,
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(20),
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
        size: 20,
        color: AppColors.neutral950,
      ),
    );
  }
}

class _SearchBar extends StatelessWidget {
  const _SearchBar();

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 44,
      padding: const EdgeInsets.symmetric(horizontal: 16),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(55),
        boxShadow: const [
          BoxShadow(
            color: Color(0x08000000),
            blurRadius: 8,
            offset: Offset(0, 2),
          ),
        ],
      ),
      child: Row(
        children: const [
          Icon(CupertinoIcons.search, size: 20, color: AppColors.neutral600),
          SizedBox(width: 8),
          Text(
            'Kisi, mesaj @kullanici adi ara...',
            style: TextStyle(
              fontFamily: AppFont.family,
              fontWeight: FontWeight.w400,
              fontSize: 14,
              color: AppColors.neutral600,
            ),
          ),
        ],
      ),
    );
  }
}

class _AdBanner extends StatelessWidget {
  const _AdBanner();

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 140,
      decoration: BoxDecoration(
        color: const Color(0xFFD9D9D9),
        borderRadius: BorderRadius.circular(18),
      ),
      alignment: Alignment.center,
      child: const Text(
        'Banner alani',
        style: TextStyle(
          fontFamily: AppFont.family,
          fontWeight: FontWeight.w500,
          fontSize: 12,
          color: Color(0xFF9C9C9C),
          letterSpacing: 0.5,
        ),
      ),
    );
  }
}

class _EmptyChatState extends StatelessWidget {
  const _EmptyChatState();

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Image.asset(
            'assets/images/empty_chat.png',
            width: 128,
            height: 108,
            fit: BoxFit.contain,
          ),
          const SizedBox(height: 24),
          const Text(
            'Henuz sohbet yok',
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
    );
  }
}

class _ChatRow extends StatelessWidget {
  final ChatPreview chat;

  const _ChatRow({required this.chat});

  @override
  Widget build(BuildContext context) {
    return PressableScale(
      onTap: () {},
      scale: 0.99,
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 6),
        child: Row(
          children: [
            _AvatarCircle(name: chat.name, online: chat.online, size: 52),
            const SizedBox(width: 16),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          chat.name,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            fontFamily: AppFont.family,
                            fontWeight: FontWeight.w600,
                            fontSize: 15,
                            color: AppColors.neutral950,
                          ),
                        ),
                      ),
                      const SizedBox(width: 8),
                      Text(
                        chat.time,
                        style: TextStyle(
                          fontFamily: AppFont.family,
                          fontWeight: FontWeight.w400,
                          fontSize: 11,
                          color: chat.unread > 0
                              ? AppColors.neutral950
                              : AppColors.neutral500,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 4),
                  _ChatSubLine(chat: chat),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ChatSubLine extends StatelessWidget {
  final ChatPreview chat;

  const _ChatSubLine({required this.chat});

  @override
  Widget build(BuildContext context) {
    final hasStatus = chat.statusText != null;
    final hasUnread = chat.unread > 0;
    final messageColor = hasUnread
        ? AppColors.neutral950
        : AppColors.neutral500;

    return Row(
      children: [
        if (chat.myMessageRead && !hasStatus) ...[
          Image.asset('assets/images/icon_tick.png', width: 14, height: 14),
          const SizedBox(width: 4),
        ],
        Expanded(
          child: Text(
            hasStatus ? chat.statusText! : (chat.lastMessage ?? ''),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              fontFamily: AppFont.family,
              fontWeight: FontWeight.w400,
              fontSize: 13,
              color: hasStatus ? AppColors.brandBlue : messageColor,
            ),
          ),
        ),
        if (hasUnread) ...[
          const SizedBox(width: 8),
          Container(
            constraints: const BoxConstraints(minWidth: 18, minHeight: 18),
            padding: const EdgeInsets.symmetric(horizontal: 5),
            decoration: BoxDecoration(
              color: AppColors.brandBlue,
              borderRadius: BorderRadius.circular(9),
            ),
            alignment: Alignment.center,
            child: Text(
              '${chat.unread}',
              style: const TextStyle(
                fontFamily: AppFont.family,
                fontWeight: FontWeight.w500,
                fontSize: 11,
                color: AppColors.white,
                height: 1.0,
              ),
            ),
          ),
        ],
      ],
    );
  }
}

class _DiscoverBanner extends StatelessWidget {
  final VoidCallback? onTap;

  const _DiscoverBanner({this.onTap});

  @override
  Widget build(BuildContext context) {
    return PressableScale(
      onTap: onTap ?? () {},
      scale: 0.98,
      child: Container(
        height: 72,
        decoration: BoxDecoration(
          color: AppColors.black,
          borderRadius: BorderRadius.circular(50),
        ),
        child: ClipRRect(
          borderRadius: BorderRadius.circular(50),
          child: Stack(
            fit: StackFit.expand,
            children: [
              Opacity(
                opacity: 0.85,
                child: Image.asset(
                  'assets/images/banner_shimmer.png',
                  fit: BoxFit.cover,
                  alignment: Alignment.centerRight,
                ),
              ),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: Row(
                  children: [
                    const _DiscoverAvatars(),
                    const SizedBox(width: 16),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: const [
                          Text(
                            'Yeni birilerini kesfet',
                            style: TextStyle(
                              fontFamily: AppFont.family,
                              fontWeight: FontWeight.w700,
                              fontSize: 16,
                              color: AppColors.white,
                            ),
                          ),
                          SizedBox(height: 2),
                          Text(
                            '538 kisi online',
                            style: TextStyle(
                              fontFamily: AppFont.family,
                              fontWeight: FontWeight.w400,
                              fontSize: 11,
                              color: Color(0xCCFFFFFF),
                            ),
                          ),
                        ],
                      ),
                    ),
                    const Icon(
                      CupertinoIcons.chevron_right,
                      size: 20,
                      color: AppColors.white,
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _DiscoverAvatars extends StatelessWidget {
  const _DiscoverAvatars();

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 92,
      height: 40,
      child: Stack(
        children: [
          Positioned(left: 0, child: _miniAvatar('Ayse Yilmaz')),
          Positioned(left: 26, child: _miniAvatar('Burcu Eren')),
          Positioned(left: 52, child: _miniAvatar('Cem Koc')),
        ],
      ),
    );
  }

  Widget _miniAvatar(String name) {
    return Container(
      width: 40,
      height: 40,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        border: Border.all(color: AppColors.white, width: 2),
        color: _AvatarColors.forName(name),
      ),
      alignment: Alignment.center,
      child: Text(
        _initialsOf(name),
        style: const TextStyle(
          fontFamily: AppFont.family,
          fontWeight: FontWeight.w700,
          fontSize: 12,
          color: AppColors.white,
        ),
      ),
    );
  }
}

class HomeScreen extends StatelessWidget {
  final HomeMode mode;

  const HomeScreen({super.key, this.mode = HomeMode.list});

  @override
  Widget build(BuildContext context) {
    return CupertinoPageScaffold(
      backgroundColor: AppColors.neutral100,
      child: SafeArea(
        bottom: false,
        child: Stack(
          children: [
            Column(
              children: [
                const _HomeTopBar(),
                Expanded(
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(16, 4, 16, 0),
                    child: _HomeContent(mode: mode),
                  ),
                ),
                SizedBox(height: MediaQuery.paddingOf(context).bottom + 88),
              ],
            ),
            Positioned(
              left: 16,
              right: 16,
              bottom: MediaQuery.paddingOf(context).bottom + 12,
              child: _DiscoverBanner(onTap: () {}),
            ),
          ],
        ),
      ),
    );
  }
}

class _HomeContent extends StatelessWidget {
  final HomeMode mode;

  const _HomeContent({required this.mode});

  @override
  Widget build(BuildContext context) {
    final showBanner = mode == HomeMode.listWithBanner;
    final showList = mode != HomeMode.empty;

    return AnimatedSwitcher(
      duration: const Duration(milliseconds: 280),
      switchInCurve: Curves.easeOutCubic,
      child: Column(
        key: ValueKey(mode),
        children: [
          if (showBanner) ...[
            const SizedBox(height: 8),
            const _AdBanner(),
            const SizedBox(height: 16),
          ] else
            const SizedBox(height: 8),
          const _SearchBar(),
          const SizedBox(height: 12),
          Expanded(
            child: showList
                ? _ChatList(chats: _mockChats)
                : const _EmptyChatState(),
          ),
        ],
      ),
    );
  }
}

class _ChatList extends StatelessWidget {
  final List<ChatPreview> chats;

  const _ChatList({required this.chats});

  @override
  Widget build(BuildContext context) {
    return ListView.separated(
      physics: const BouncingScrollPhysics(),
      padding: const EdgeInsets.symmetric(vertical: 6),
      itemCount: chats.length,
      separatorBuilder: (context, index) => Container(
        margin: const EdgeInsets.only(left: 68),
        height: 0.6,
        color: const Color(0xFFE7E7E7),
      ),
      itemBuilder: (context, index) => _ChatRow(chat: chats[index]),
    );
  }
}

// =============================================================================
// Dev Index â€” tum ekranlari tek yerden acmak icin
