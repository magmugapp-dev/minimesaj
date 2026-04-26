import 'package:magmug/app_core.dart';
import 'package:magmug/features/chat/chat_flow.dart';
import 'package:magmug/features/home/models/chat_preview.dart';
import 'package:magmug/features/home/widgets/home_avatar.dart';

class HomeAdBanner extends StatelessWidget {
  const HomeAdBanner({super.key});

  @override
  Widget build(BuildContext context) {
    return const SizedBox.shrink();
  }
}

class HomeEmptyChatState extends StatelessWidget {
  final double bottomInset;

  const HomeEmptyChatState({super.key, this.bottomInset = 0});

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final compact = constraints.maxHeight < 640;
        final imageWidth = constraints.maxWidth < 360 ? 104.0 : 128.0;

        return Center(
          child: SingleChildScrollView(
            padding: EdgeInsets.fromLTRB(0, 16, 0, 16 + bottomInset),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Image.asset(
                  'assets/images/empty_chat.png',
                  width: imageWidth,
                  height: imageWidth * 0.84,
                  fit: BoxFit.contain,
                ),
                SizedBox(height: compact ? 16 : 24),
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
          ),
        );
      },
    );
  }
}

class HomeChatList extends StatelessWidget {
  final List<ChatPreview> chats;
  final double bottomInset;

  const HomeChatList({super.key, required this.chats, this.bottomInset = 0});

  @override
  Widget build(BuildContext context) {
    return ListView.separated(
      physics: const BouncingScrollPhysics(),
      padding: EdgeInsets.fromLTRB(0, 6, 0, 6 + bottomInset),
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

class HomeChatAvatar extends StatelessWidget {
  final ChatPreview chat;

  const HomeChatAvatar({super.key, required this.chat});

  @override
  Widget build(BuildContext context) {
    final avatarUrl = chat.avatarUrl;
    if (avatarUrl != null && avatarUrl.isNotEmpty && !chat.forceInitials) {
      return SizedBox(
        width: 52,
        height: 52,
        child: Stack(
          children: [
            ClipOval(
              child: CachedAppImage(
                imageUrl: avatarUrl,
                width: 52,
                height: 52,
                cacheWidth: 104,
                cacheHeight: 104,
                errorBuilder: (_) =>
                    HomeAvatarCircle(name: chat.name, online: false, size: 52),
              ),
            ),
            if (chat.online)
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

    return HomeAvatarCircle(name: chat.name, online: chat.online, size: 52);
  }
}

class _ChatRow extends StatelessWidget {
  final ChatPreview chat;

  const _ChatRow({required this.chat});

  @override
  Widget build(BuildContext context) {
    return PressableScale(
      onTap: () {
        Navigator.of(context).push(
          chatRoute(
            ChatScreen(
              mode: ChatScreenMode.messages,
              conversation: chat.conversation,
            ),
          ),
        );
      },
      scale: 0.99,
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 6),
        child: Row(
          children: [
            HomeChatAvatar(chat: chat),
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
