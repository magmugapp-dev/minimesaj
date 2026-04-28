import 'package:magmug/app_core.dart';
import 'package:magmug/features/chat/chat_flow.dart';
import 'package:magmug/features/chat/chat_local_store.dart';
import 'package:magmug/features/home/widgets/home_avatar.dart';

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
          children: [
            const Icon(
              CupertinoIcons.search,
              size: 20,
              color: AppColors.neutral600,
            ),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                AppRuntimeText.instance.t(
                  'home.search.placeholder_short',
                  'Profil ID veya mesaj ara...',
                ),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
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
  Future<_SearchPayload>? _searchFuture;
  String _activeQuery = '';

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

  void _handleQueryChanged(String value) {
    final query = value.trim();
    setState(() {
      _activeQuery = query;
      _searchFuture = query.isEmpty ? null : _performSearch(query);
    });
  }

  Future<_SearchPayload> _performSearch(String query) async {
    final session = await ref.read(appAuthProvider.future);
    final token = session?.token;
    final ownerUserId = session?.user?.id;
    if (token == null || token.trim().isEmpty || ownerUserId == null) {
      return _SearchPayload.error(
        AppRuntimeText.instance.t(
          'auth.error.login_required_action',
          'Bu islemi yapmak icin once giris yapmalisin.',
        ),
      );
    }

    if (RegExp(r'^\d+$').hasMatch(query)) {
      final api = AppAuthApi();
      try {
        final profile = await api.fetchAiProfile(
          token,
          userId: int.parse(query),
        );
        return _SearchPayload.aiProfile(profile);
      } on ApiException catch (error) {
        return _SearchPayload.empty(error.message);
      } finally {
        api.close();
      }
    }

    final messages = await ChatLocalStore.instance.searchConversationMessages(
      ownerUserId: ownerUserId,
      query: query,
    );
    return _SearchPayload.messages(messages);
  }

  void _clear() {
    _controller.clear();
    _handleQueryChanged('');
  }

  @override
  Widget build(BuildContext context) {
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
                              onChanged: _handleQueryChanged,
                              placeholder: AppRuntimeText.instance.t(
                                'home.search.placeholder',
                                'Profil ID veya mesaj ara',
                              ),
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
                          if (_activeQuery.isNotEmpty)
                            PressableScale(
                              onTap: _clear,
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
            Expanded(child: _buildBody()),
          ],
        ),
      ),
    );
  }

  Widget _buildBody() {
    final query = _activeQuery;
    final future = _searchFuture;
    if (query.isEmpty || future == null) {
      return _SearchStatusState(
        icon: CupertinoIcons.search,
        title: AppRuntimeText.instance.t(
          'home.search.idle.title',
          'Aramaya basla',
        ),
        subtitle: AppRuntimeText.instance.t(
          'home.search.idle.subtitle',
          'Profil ID ile AI profili, metin ile kendi sohbet mesajlarini ara.',
        ),
      );
    }

    return FutureBuilder<_SearchPayload>(
      future: future,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          return const Center(child: CupertinoActivityIndicator(radius: 14));
        }
        if (snapshot.hasError) {
          return _SearchStatusState(
            icon: CupertinoIcons.exclamationmark_triangle,
            title: AppRuntimeText.instance.t(
              'home.search.error.title',
              'Arama tamamlanamadi',
            ),
            subtitle: AppAuthErrorFormatter.messageFrom(
              snapshot.error ??
                  AppRuntimeText.instance.t('commonError', 'Hata'),
            ),
          );
        }

        final payload = snapshot.data ?? const _SearchPayload();
        if (payload.errorMessage != null) {
          return _SearchStatusState(
            icon: CupertinoIcons.exclamationmark_triangle,
            title: AppRuntimeText.instance.t(
              'home.search.error.title',
              'Arama tamamlanamadi',
            ),
            subtitle: payload.errorMessage!,
          );
        }
        if (payload.emptyMessage != null) {
          return _SearchStatusState(
            icon: CupertinoIcons.search,
            title: AppRuntimeText.instance.t(
              'home.search.empty.title',
              'Sonuc bulunamadi',
            ),
            subtitle: payload.emptyMessage!,
          );
        }
        if (payload.isEmpty) {
          return _SearchStatusState(
            icon: CupertinoIcons.search,
            title: AppRuntimeText.instance.t(
              'home.search.empty.title',
              'Sonuc bulunamadi',
            ),
            subtitle: AppRuntimeText.instance.t(
              'home.search.empty.subtitle',
              '"{query}" icin eslesen AI profil ID veya mesaj yok.',
              args: {'query': query},
            ),
          );
        }

        return _SearchResultsList(payload: payload, query: query);
      },
    );
  }
}

@immutable
class _SearchPayload {
  final AppMatchCandidate? aiProfile;
  final List<ChatLocalMessageSearchResult> messages;
  final String? errorMessage;
  final String? emptyMessage;

  const _SearchPayload({
    this.aiProfile,
    this.messages = const <ChatLocalMessageSearchResult>[],
    this.errorMessage,
    this.emptyMessage,
  });

  factory _SearchPayload.aiProfile(AppMatchCandidate profile) {
    return _SearchPayload(aiProfile: profile);
  }

  factory _SearchPayload.messages(List<ChatLocalMessageSearchResult> messages) {
    return _SearchPayload(messages: messages);
  }

  factory _SearchPayload.empty(String message) {
    return _SearchPayload(emptyMessage: message);
  }

  factory _SearchPayload.error(String message) {
    return _SearchPayload(errorMessage: message);
  }

  bool get isEmpty =>
      errorMessage == null &&
      aiProfile == null &&
      messages.isEmpty &&
      emptyMessage == null;
}

class _SearchResultsList extends StatelessWidget {
  final _SearchPayload payload;
  final String query;

  const _SearchResultsList({required this.payload, required this.query});

  @override
  Widget build(BuildContext context) {
    final itemCount =
        (payload.aiProfile == null ? 0 : 1) + payload.messages.length;

    return ListView.separated(
      physics: const BouncingScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(16, 0, 16, 24),
      itemCount: itemCount + 1,
      separatorBuilder: (context, index) => const SizedBox(height: 8),
      itemBuilder: (context, index) {
        if (index == 0) {
          return Padding(
            padding: const EdgeInsets.only(bottom: 4),
            child: Text(
              AppRuntimeText.instance.t(
                'home.search.results_count',
                '{count} sonuc bulundu',
                args: {'count': itemCount},
              ),
              style: const TextStyle(
                fontFamily: AppFont.family,
                fontWeight: FontWeight.w700,
                fontSize: 12.5,
                color: AppColors.neutral600,
              ),
            ),
          );
        }

        final resultIndex = index - 1;
        final aiProfile = payload.aiProfile;
        if (aiProfile != null && resultIndex == 0) {
          return _AiProfileResultRow(profile: aiProfile);
        }

        final messageIndex = resultIndex - (aiProfile == null ? 0 : 1);
        return _MessageResultRow(
          result: payload.messages[messageIndex],
          query: query,
        );
      },
    );
  }
}

class _AiProfileResultRow extends StatelessWidget {
  final AppMatchCandidate profile;

  const _AiProfileResultRow({required this.profile});

  @override
  Widget build(BuildContext context) {
    final username = profile.username.trim();
    final handle = username.isEmpty
        ? ''
        : (username.startsWith('@') ? username : '@$username');
    final peer = ChatPeer(
      name: profile.displayName,
      handle: handle,
      status: profile.online
          ? AppRuntimeText.instance.t('chat.status.online', 'Cevrimici')
          : AppRuntimeText.instance.t('chat.status.inactive', 'Aktif degil'),
      avatarUrl: profile.primaryImageUrl,
      online: profile.online,
    );

    return PressableScale(
      onTap: () => Navigator.of(context).push(
        cupertinoRoute(
          ChatProfileScreen(peer: peer, profileUserId: profile.id),
        ),
      ),
      scale: 0.99,
      child: _SearchResultShell(
        leading: HomeAvatarCircle(name: profile.displayName, size: 44),
        title: profile.displayName,
        badge: AppRuntimeText.instance.t(
          'home.search.result.ai_profile',
          'AI profil sonucu',
        ),
        subtitle: handle.isEmpty
            ? AppRuntimeText.instance.t(
                'home.search.profile_id',
                'Profil ID: {id}',
                args: {'id': profile.id},
              )
            : '$handle - ${AppRuntimeText.instance.t('home.search.profile_id', 'Profil ID: {id}', args: {'id': profile.id})}',
      ),
    );
  }
}

class _MessageResultRow extends StatelessWidget {
  final ChatLocalMessageSearchResult result;
  final String query;

  const _MessageResultRow({required this.result, required this.query});

  @override
  Widget build(BuildContext context) {
    final conversation = result.conversation;
    final message = result.message;
    final snippet = message.text?.trim().isNotEmpty == true
        ? message.text!.trim()
        : AppRuntimeText.instance.t('chat.message.open_chat', 'Sohbeti ac');

    return PressableScale(
      onTap: () => Navigator.of(context).push(
        chatRoute(
          ChatScreen(mode: ChatScreenMode.messages, conversation: conversation),
        ),
      ),
      scale: 0.99,
      child: _SearchResultShell(
        leading: HomeAvatarCircle(name: conversation.peerName, size: 44),
        title: conversation.peerName,
        badge: AppRuntimeText.instance.t(
          'home.search.result.message',
          'Mesaj sonucu',
        ),
        subtitle: snippet,
        footer: AppRuntimeText.instance.t(
          'home.search.matching_phrase',
          'Eslesen ifade: {query}',
          args: {'query': query},
        ),
      ),
    );
  }
}

class _SearchResultShell extends StatelessWidget {
  final Widget leading;
  final String title;
  final String badge;
  final String subtitle;
  final String? footer;

  const _SearchResultShell({
    required this.leading,
    required this.title,
    required this.badge,
    required this.subtitle,
    this.footer,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
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
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          leading,
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        title,
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
                    const SizedBox(width: 8),
                    Flexible(
                      child: Text(
                        badge,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        textAlign: TextAlign.right,
                        style: const TextStyle(
                          fontFamily: AppFont.family,
                          fontWeight: FontWeight.w700,
                          fontSize: 10.5,
                          color: AppColors.brandBlue,
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 4),
                Text(
                  subtitle,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontFamily: AppFont.family,
                    fontSize: 12.5,
                    height: 1.4,
                    color: AppColors.neutral600,
                  ),
                ),
                if (footer != null) ...[
                  const SizedBox(height: 8),
                  Text(
                    footer!,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
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
    );
  }
}

class _SearchStatusState extends StatelessWidget {
  final IconData icon;
  final String title;
  final String subtitle;

  const _SearchStatusState({
    required this.icon,
    required this.title,
    required this.subtitle,
  });

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
              child: Icon(icon, size: 34, color: AppColors.gray),
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
