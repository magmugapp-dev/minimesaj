import 'package:magmug/app_core.dart';
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
            final compact = constraints.maxHeight < 700;
            final bannerHeight = compact ? 64.0 : 72.0;

            return Stack(
              children: [
                Column(
                  children: [
                    const HomeTopBar(),
                    Expanded(
                      child: Padding(
                        padding: const EdgeInsets.fromLTRB(16, 4, 16, 0),
                        child: _HomeContent(mode: mode),
                      ),
                    ),
                    SizedBox(
                      height:
                          MediaQuery.paddingOf(context).bottom +
                          bannerHeight +
                          16,
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

class _HomeContent extends ConsumerWidget {
  final HomeMode mode;

  const _HomeContent({required this.mode});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final showBanner = mode == HomeMode.listWithBanner;
    final showList = mode != HomeMode.empty;
    final chatsAsync = ref.watch(homeChatsProvider);

    void openSearch() {
      Navigator.of(context).push(cupertinoRoute(const HomeSearchScreen()));
    }

    return AnimatedSwitcher(
      duration: const Duration(milliseconds: 280),
      switchInCurve: Curves.easeOutCubic,
      child: Column(
        key: ValueKey(mode),
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
                ? const HomeEmptyChatState()
                : chatsAsync.when(
                    data: (chats) => chats.isEmpty
                        ? const HomeEmptyChatState()
                        : HomeChatList(chats: chats),
                    loading: () => const Center(
                      child: CupertinoActivityIndicator(radius: 14),
                    ),
                    error: (_, _) => const HomeEmptyChatState(),
                  ),
          ),
        ],
      ),
    );
  }
}

// =============================================================================
// Dev Index — tum ekranlari tek yerden acmak icin
