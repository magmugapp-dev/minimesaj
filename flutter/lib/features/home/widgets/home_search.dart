import 'package:magmug/app_core.dart';
import 'package:magmug/features/chat/chat_flow.dart';
import 'package:magmug/features/home/models/chat_preview.dart';
import 'package:magmug/features/home/providers/home_chats_provider.dart';
import 'package:magmug/features/home/widgets/home_chat_list.dart';

class HomeSearchBar extends StatelessWidget {
  final VoidCallback onTap;

  const HomeSearchBar({super.key, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return PressableScale(
      onTap: onTap,
      scale: 0.99,
      child: Container(
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
            Expanded(
              child: Text(
                'Kisi, mesaj @kullanici adi ara...',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  fontFamily: AppFont.family,
                  fontWeight: FontWeight.w400,
                  fontSize: 14,
                  color: AppColors.neutral600,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class HomeSearchScreen extends ConsumerStatefulWidget {
  const HomeSearchScreen({super.key});

  @override
  ConsumerState<HomeSearchScreen> createState() => _HomeSearchScreenState();
}

class _HomeSearchScreenState extends ConsumerState<HomeSearchScreen> {
  final TextEditingController _controller = TextEditingController();
  final FocusNode _focusNode = FocusNode();
  int _selectedFilter = 0;

  static const List<String> _filters = [
    'Hepsi',
    'Kisiler',
    'Mesajlar',
    'Online',
  ];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) {
        _focusNode.requestFocus();
      }
    });
  }

  @override
  void dispose() {
    _controller.dispose();
    _focusNode.dispose();
    super.dispose();
  }

  bool _matchesQuery(ChatPreview chat, String query) {
    if (query.isEmpty) {
      return true;
    }
    final lowerQuery = query.toLowerCase();
    final inName = chat.name.toLowerCase().contains(lowerQuery);
    final inMessage =
        (chat.lastMessage ?? '').toLowerCase().contains(lowerQuery) ||
        (chat.statusText ?? '').toLowerCase().contains(lowerQuery);

    switch (_selectedFilter) {
      case 1:
        return inName;
      case 2:
        return inMessage;
      case 3:
        return chat.online && (inName || inMessage);
      default:
        return inName || inMessage;
    }
  }

  @override
  Widget build(BuildContext context) {
    final query = _controller.text.trim();
    final chatsAsync = ref.watch(homeChatsProvider);
    final sourceChats = chatsAsync.asData?.value ?? const <ChatPreview>[];
    final isLoading = chatsAsync.isLoading;
    final hasError = chatsAsync.hasError;
    final filteredChats = sourceChats
        .where((chat) => _matchesQuery(chat, query))
        .toList(growable: false);

    return CupertinoPageScaffold(
      backgroundColor: AppColors.neutral100,
      child: SafeArea(
        bottom: false,
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
              child: Row(
                children: [
                  CircleBackButton(
                    filled: true,
                    onTap: () => Navigator.of(context).maybePop(),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Container(
                      height: 46,
                      padding: const EdgeInsets.symmetric(horizontal: 14),
                      decoration: BoxDecoration(
                        color: AppColors.white,
                        borderRadius: BorderRadius.circular(24),
                        boxShadow: const [
                          BoxShadow(
                            color: Color(0x08000000),
                            blurRadius: 8,
                            offset: Offset(0, 2),
                          ),
                        ],
                      ),
                      child: Row(
                        children: [
                          const Icon(
                            CupertinoIcons.search,
                            size: 20,
                            color: AppColors.neutral600,
                          ),
                          const SizedBox(width: 8),
                          Expanded(
                            child: CupertinoTextField(
                              controller: _controller,
                              focusNode: _focusNode,
                              onChanged: (_) => setState(() {}),
                              placeholder:
                                  'Kisi, mesaj veya @kullanici adi ara',
                              placeholderStyle: const TextStyle(
                                fontFamily: AppFont.family,
                                fontSize: 14,
                                color: AppColors.neutral600,
                              ),
                              style: const TextStyle(
                                fontFamily: AppFont.family,
                                fontSize: 14,
                                color: AppColors.black,
                              ),
                              decoration: const BoxDecoration(
                                color: Color(0x00000000),
                              ),
                              padding: EdgeInsets.zero,
                              cursorColor: AppColors.indigo,
                            ),
                          ),
                          if (query.isNotEmpty)
                            PressableScale(
                              onTap: () {
                                _controller.clear();
                                setState(() {});
                              },
                              scale: 0.9,
                              child: const Icon(
                                CupertinoIcons.clear_circled_solid,
                                size: 18,
                                color: AppColors.gray,
                              ),
                            ),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
            ),
            SizedBox(
              height: 38,
              child: ListView.separated(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                scrollDirection: Axis.horizontal,
                itemBuilder: (context, index) {
                  final selected = _selectedFilter == index;
                  return PressableScale(
                    onTap: () => setState(() => _selectedFilter = index),
                    scale: 0.97,
                    child: AnimatedContainer(
                      duration: const Duration(milliseconds: 160),
                      padding: const EdgeInsets.symmetric(
                        horizontal: 14,
                        vertical: 9,
                      ),
                      decoration: BoxDecoration(
                        color: selected ? AppColors.black : AppColors.white,
                        borderRadius: BorderRadius.circular(20),
                        border: Border.all(
                          color: selected
                              ? AppColors.black
                              : const Color(0xFFE7E7EA),
                        ),
                      ),
                      child: Text(
                        _filters[index],
                        style: TextStyle(
                          fontFamily: AppFont.family,
                          fontWeight: FontWeight.w700,
                          fontSize: 12.5,
                          color: selected ? AppColors.white : AppColors.black,
                        ),
                      ),
                    ),
                  );
                },
                separatorBuilder: (context, index) => const SizedBox(width: 8),
                itemCount: _filters.length,
              ),
            ),
            const SizedBox(height: 12),
            Expanded(
              child: isLoading
                  ? const Center(child: CupertinoActivityIndicator(radius: 14))
                  : hasError
                  ? const _SearchStatusState(
                      title: 'Sohbetler yuklenemedi',
                      subtitle:
                          'Arama sonuclarini gostermek icin sohbetlerin tekrar yuklenmesini bekliyoruz.',
                    )
                  : query.isEmpty
                  ? _SearchIdleState(
                      chats: sourceChats,
                      onSelectRecent: (value) {
                        _controller.text = value;
                        _controller.selection = TextSelection.collapsed(
                          offset: value.length,
                        );
                        setState(() {});
                      },
                    )
                  : filteredChats.isEmpty
                  ? _SearchEmptyState(query: query)
                  : _SearchResultsList(chats: filteredChats, query: query),
            ),
          ],
        ),
      ),
    );
  }
}

class _SearchIdleState extends StatelessWidget {
  final List<ChatPreview> chats;
  final ValueChanged<String> onSelectRecent;

  const _SearchIdleState({required this.chats, required this.onSelectRecent});

  @override
  Widget build(BuildContext context) {
    final suggestions = chats.take(4).toList(growable: false);
    final recentItems = suggestions
        .map((chat) {
          final username = chat.conversation?.peerUsername.trim() ?? '';
          if (username.isNotEmpty) {
            return username.startsWith('@') ? username : '@$username';
          }
          return chat.name;
        })
        .where((item) => item.isNotEmpty)
        .toSet()
        .toList(growable: false);

    return ListView(
      physics: const BouncingScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(16, 0, 16, 24),
      children: [
        if (recentItems.isNotEmpty) ...[
          const Text(
            'Son aramalar',
            style: TextStyle(
              fontFamily: AppFont.family,
              fontWeight: FontWeight.w800,
              fontSize: 15,
              color: AppColors.black,
            ),
          ),
          const SizedBox(height: 10),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: recentItems
                .map(
                  (item) => PressableScale(
                    onTap: () => onSelectRecent(item),
                    scale: 0.97,
                    child: Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 14,
                        vertical: 10,
                      ),
                      decoration: BoxDecoration(
                        color: AppColors.white,
                        borderRadius: BorderRadius.circular(20),
                        border: Border.all(color: const Color(0xFFEAEAEA)),
                      ),
                      child: Text(
                        item,
                        style: const TextStyle(
                          fontFamily: AppFont.family,
                          fontWeight: FontWeight.w600,
                          fontSize: 12.5,
                          color: AppColors.black,
                        ),
                      ),
                    ),
                  ),
                )
                .toList(),
          ),
          const SizedBox(height: 22),
        ],
        Container(
          padding: const EdgeInsets.all(18),
          decoration: BoxDecoration(
            color: AppColors.black,
            borderRadius: BorderRadius.circular(24),
          ),
          child: const Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Hizli bul',
                      style: TextStyle(
                        fontFamily: AppFont.family,
                        fontWeight: FontWeight.w800,
                        fontSize: 18,
                        color: AppColors.white,
                      ),
                    ),
                    SizedBox(height: 6),
                    Text(
                      'Aktif sohbetlerini, son mesajlari ve kullanici adlarini tek yerden ara.',
                      style: TextStyle(
                        fontFamily: AppFont.family,
                        fontSize: 12.5,
                        height: 1.45,
                        color: Color(0xCCFFFFFF),
                      ),
                    ),
                  ],
                ),
              ),
              SizedBox(width: 12),
              Icon(CupertinoIcons.sparkles, size: 28, color: AppColors.white),
            ],
          ),
        ),
        if (suggestions.isNotEmpty) ...[
          const SizedBox(height: 22),
          const Text(
            'Onerilen sohbetler',
            style: TextStyle(
              fontFamily: AppFont.family,
              fontWeight: FontWeight.w800,
              fontSize: 15,
              color: AppColors.black,
            ),
          ),
          const SizedBox(height: 8),
          ...suggestions.map(
            (chat) => Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: _SearchResultRow(chat: chat, query: ''),
            ),
          ),
        ],
      ],
    );
  }
}

class _SearchStatusState extends StatelessWidget {
  final String title;
  final String subtitle;

  const _SearchStatusState({required this.title, required this.subtitle});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 28),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              width: 84,
              height: 84,
              decoration: BoxDecoration(
                color: AppColors.white,
                shape: BoxShape.circle,
                boxShadow: const [
                  BoxShadow(
                    color: Color(0x0C000000),
                    blurRadius: 16,
                    offset: Offset(0, 6),
                  ),
                ],
              ),
              alignment: Alignment.center,
              child: const Icon(
                CupertinoIcons.chat_bubble_2,
                size: 34,
                color: AppColors.gray,
              ),
            ),
            const SizedBox(height: 18),
            Text(
              title,
              textAlign: TextAlign.center,
              style: const TextStyle(
                fontFamily: AppFont.family,
                fontWeight: FontWeight.w800,
                fontSize: 18,
                color: AppColors.black,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              subtitle,
              textAlign: TextAlign.center,
              style: const TextStyle(
                fontFamily: AppFont.family,
                fontSize: 13,
                height: 1.5,
                color: AppColors.neutral600,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _SearchEmptyState extends StatelessWidget {
  final String query;

  const _SearchEmptyState({required this.query});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 28),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              width: 84,
              height: 84,
              decoration: BoxDecoration(
                color: AppColors.white,
                shape: BoxShape.circle,
                boxShadow: const [
                  BoxShadow(
                    color: Color(0x0C000000),
                    blurRadius: 16,
                    offset: Offset(0, 6),
                  ),
                ],
              ),
              alignment: Alignment.center,
              child: const Icon(
                CupertinoIcons.search,
                size: 34,
                color: AppColors.gray,
              ),
            ),
            const SizedBox(height: 18),
            const Text(
              'Sonuc bulunamadi',
              style: TextStyle(
                fontFamily: AppFont.family,
                fontWeight: FontWeight.w800,
                fontSize: 18,
                color: AppColors.black,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              '"$query" icin eslesen kisi ya da mesaj yok. Farkli bir isim, mesaj parcasi veya @kullanici adi deneyin.',
              textAlign: TextAlign.center,
              style: const TextStyle(
                fontFamily: AppFont.family,
                fontSize: 13,
                height: 1.5,
                color: AppColors.neutral600,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _SearchResultsList extends StatelessWidget {
  final List<ChatPreview> chats;
  final String query;

  const _SearchResultsList({required this.chats, required this.query});

  @override
  Widget build(BuildContext context) {
    return ListView.separated(
      physics: const BouncingScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(16, 0, 16, 24),
      itemCount: chats.length + 1,
      separatorBuilder: (context, index) => const SizedBox(height: 8),
      itemBuilder: (context, index) {
        if (index == 0) {
          return Padding(
            padding: const EdgeInsets.only(bottom: 4),
            child: Text(
              '${chats.length} sonuc bulundu',
              style: const TextStyle(
                fontFamily: AppFont.family,
                fontWeight: FontWeight.w700,
                fontSize: 12.5,
                color: AppColors.neutral600,
              ),
            ),
          );
        }
        return _SearchResultRow(chat: chats[index - 1], query: query);
      },
    );
  }
}

class _SearchResultRow extends StatelessWidget {
  final ChatPreview chat;
  final String query;

  const _SearchResultRow({required this.chat, required this.query});

  @override
  Widget build(BuildContext context) {
    final snippet = (chat.lastMessage?.trim().isNotEmpty == true)
        ? chat.lastMessage!
        : (chat.statusText?.trim().isNotEmpty == true)
        ? chat.statusText!
        : 'Sohbeti ac';

    return PressableScale(
      onTap: () => Navigator.of(context).push(
        cupertinoRoute(
          ChatScreen(
            mode: ChatScreenMode.messages,
            conversation: chat.conversation,
          ),
        ),
      ),
      scale: 0.99,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
        decoration: BoxDecoration(
          color: AppColors.white,
          borderRadius: BorderRadius.circular(20),
          boxShadow: const [
            BoxShadow(
              color: Color(0x08000000),
              blurRadius: 8,
              offset: Offset(0, 2),
            ),
          ],
        ),
        child: Row(
          children: [
            HomeChatAvatar(chat: chat),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
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
                            fontWeight: FontWeight.w700,
                            fontSize: 14.5,
                            color: AppColors.black,
                          ),
                        ),
                      ),
                      if (chat.online)
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 8,
                            vertical: 4,
                          ),
                          decoration: BoxDecoration(
                            color: const Color(0x1422C55E),
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: const Text(
                            'Aktif',
                            style: TextStyle(
                              fontFamily: AppFont.family,
                              fontWeight: FontWeight.w700,
                              fontSize: 10.5,
                              color: AppColors.onlineGreen,
                            ),
                          ),
                        ),
                    ],
                  ),
                  const SizedBox(height: 4),
                  Text(
                    snippet,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontFamily: AppFont.family,
                      fontSize: 12.5,
                      height: 1.4,
                      color: AppColors.neutral600,
                    ),
                  ),
                  if (query.isNotEmpty) ...[
                    const SizedBox(height: 8),
                    Text(
                      'Eslesen ifade: $query',
                      style: const TextStyle(
                        fontFamily: AppFont.family,
                        fontWeight: FontWeight.w700,
                        fontSize: 11,
                        color: AppColors.brandBlue,
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
