import 'package:magmug/app_core.dart';
import 'package:magmug/features/home/models/chat_preview.dart';
import 'package:magmug/features/home/providers/home_chats_provider.dart';
import 'package:magmug/features/home/providers/home_discover_profiles_provider.dart';
import 'package:magmug/features/home/widgets/home_chat_list.dart';
import 'package:magmug/features/home/widgets/home_discover_banner.dart';
import 'package:magmug/features/home/widgets/home_search.dart';
import 'package:magmug/features/home/widgets/home_top_bar.dart';
import 'package:magmug/features/match/match_flow.dart';

export 'package:magmug/features/home/widgets/home_search.dart';

// =============================================================================

enum HomeMode { empty, list, listWithBanner }

class HomeScreen extends ConsumerWidget {
  final HomeMode mode;

  const HomeScreen({super.key, this.mode = HomeMode.list});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    void openMatchMode() {
      Navigator.of(context).push(
        cupertinoRoute(const MatchModeScreen(variant: MatchModeVariant.free)),
      );
    }

    return CupertinoPageScaffold(
      backgroundColor: AppColors.neutral100,
      child: SafeArea(
        bottom: false,
        child: LayoutBuilder(
          builder: (context, constraints) {
            final bannerInset = MediaQuery.paddingOf(context).bottom + 8;

            return Stack(
              children: [
                Column(
                  children: [
                    const HomeTopBar(),
                    Expanded(
                      child: Padding(
                        padding: const EdgeInsets.fromLTRB(16, 4, 16, 0),
                        child: _HomeContent(
                          mode: mode,
                          bottomInset: bannerInset,
                        ),
                      ),
                    ),
                  ],
                ),
                Positioned(
                  left: 16,
                  right: 16,
                  bottom: MediaQuery.paddingOf(context).bottom + 12,
                  child: HomeDiscoverBanner(
                    onTap: openMatchMode,
                    profiles: ref
                        .watch(homeDiscoverProfilesProvider)
                        .maybeWhen(
                          data: (profiles) => profiles,
                          orElse: () => const <AppMatchCandidate>[],
                        ),
                  ),
                ),
              ],
            );
          },
        ),
      ),
    );
  }
}

class _HomeContent extends ConsumerStatefulWidget {
  final HomeMode mode;
  final double bottomInset;

  const _HomeContent({required this.mode, this.bottomInset = 0});

  @override
  ConsumerState<_HomeContent> createState() => _HomeContentState();
}

class _HomeContentState extends ConsumerState<_HomeContent> {
  List<ChatPreview>? _lastChats;

  @override
  Widget build(BuildContext context) {
    final showBanner = widget.mode == HomeMode.listWithBanner;
    final showList = widget.mode != HomeMode.empty;
    final chatsAsync = ref.watch(homeChatsProvider);
    final loadedChats = chatsAsync.asData?.value;
    if (loadedChats != null) {
      _lastChats = loadedChats;
    }
    final visibleChats = loadedChats ?? _lastChats;

    void openSearch() {
      Navigator.of(context).push(cupertinoRoute(const HomeSearchScreen()));
    }

    return AnimatedSwitcher(
      duration: const Duration(milliseconds: 280),
      switchInCurve: Curves.easeOutCubic,
      child: Column(
        key: ValueKey(widget.mode),
        children: [
          if (showBanner) ...[
            const SizedBox(height: 8),
            const HomeAdBanner(),
            const SizedBox(height: 16),
          ] else
            const SizedBox(height: 8),
          HomeSearchBar(onTap: openSearch),
          const SizedBox(height: 12),
          Expanded(
            child: !showList
                ? HomeEmptyChatState(bottomInset: widget.bottomInset)
                : visibleChats != null
                ? (visibleChats.isEmpty
                      ? HomeEmptyChatState(bottomInset: widget.bottomInset)
                      : HomeChatList(
                          chats: visibleChats,
                          bottomInset: widget.bottomInset,
                        ))
                : chatsAsync.when(
                    data: (chats) => chats.isEmpty
                        ? HomeEmptyChatState(bottomInset: widget.bottomInset)
                        : HomeChatList(
                            chats: chats,
                            bottomInset: widget.bottomInset,
                          ),
                    loading: () => const Center(
                      child: CupertinoActivityIndicator(radius: 14),
                    ),
                    error: (_, _) =>
                        HomeEmptyChatState(bottomInset: widget.bottomInset),
                  ),
          ),
        ],
      ),
    );
  }
}

// =============================================================================
// Dev Index — tum ekranlari tek yerden acmak icin
