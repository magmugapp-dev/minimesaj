import 'dart:async';
import 'dart:io';

import 'package:flutter/services.dart';
import 'package:magmug/app_core.dart';
import 'package:magmug/features/chat/chat_local_store.dart';
import 'package:magmug/features/chat/chat_realtime.dart';
import 'package:magmug/features/match/match_flow.dart';
import 'package:magmug/features/profile/widgets/profile_overview_widgets.dart'
    show openProfileMediaViewer;
import 'package:shared_preferences/shared_preferences.dart';

const Duration _conversationRefreshCooldown = Duration(seconds: 12);
const int _conversationMessagePageSize = 50;
final Map<int, DateTime> _lastConversationRefreshAt = <int, DateTime>{};
final Set<int> _conversationRefreshInFlight = <int>{};
final Map<int, AppMatchCandidate> _chatPeerProfileCache =
    <int, AppMatchCandidate>{};
final Map<int, int> _chatThemeSelectionCache = <int, int>{};

// =============================================================================

enum ChatBubbleSide { me, them }

enum ChatMessageType { text, image, audio, typing }

enum ReportTargetType { user, message }

const ChatPeer _emptyChatPeer = ChatPeer(
  name: 'Sohbet',
  handle: '',
  status: '',
  online: false,
);

@immutable
class ChatMessage {
  final int? id;
  final ChatBubbleSide side;
  final ChatMessageType type;
  final String? text;
  final String? translatedText;
  final String? translationLanguageName;
  final String? languageName;
  final String? asset;
  final Duration? duration;
  final String time;

  const ChatMessage({
    this.id,
    required this.side,
    required this.type,
    required this.time,
    this.text,
    this.translatedText,
    this.translationLanguageName,
    this.languageName,
    this.asset,
    this.duration,
  });

  const ChatMessage.typing({this.side = ChatBubbleSide.them})
    : id = null,
      type = ChatMessageType.typing,
      text = null,
      translatedText = null,
      translationLanguageName = null,
      languageName = null,
      asset = null,
      duration = null,
      time = '';
}

@immutable
class ChatPeer {
  final String name;
  final String handle;
  final String status;
  final String? avatarAsset;
  final String? avatarUrl;
  final String? languageCode;
  final String? languageName;
  final bool online;

  const ChatPeer({
    required this.name,
    required this.handle,
    required this.status,
    this.avatarAsset,
    this.avatarUrl,
    this.languageCode,
    this.languageName,
    this.online = true,
  });

  factory ChatPeer.fromConversation(AppConversationPreview conversation) {
    final username = conversation.peerUsername.trim();
    final normalizedHandle = username.isEmpty
        ? ''
        : (username.startsWith('@') ? username : '@$username');
    final baseStatus =
        conversation.statusText ??
        (conversation.online ? 'Cevrimici' : 'Aktif degil');
    final languageName = conversation.peerLanguageName?.trim();
    final status = languageName == null || languageName.isEmpty
        ? baseStatus
        : '$baseStatus • $languageName';

    return ChatPeer(
      name: conversation.peerName,
      handle: normalizedHandle,
      status: status,
      avatarUrl: conversation.peerProfileImageUrl,
      languageCode: conversation.peerLanguageCode,
      languageName: conversation.peerLanguageName,
      online: conversation.online,
    );
  }
}

ChatPeer _chatPeerFromCandidate(AppMatchCandidate candidate) {
  final username = candidate.username.trim();
  return ChatPeer(
    name: candidate.displayName,
    handle: username.isEmpty
        ? ''
        : (username.startsWith('@') ? username : '@$username'),
    status: candidate.online ? 'Cevrimici' : 'Aktif degil',
    avatarUrl: candidate.primaryImageUrl,
    online: candidate.online,
  );
}

ChatPeer _chatPeerFromGiftSender(AppGiftSender sender) {
  final username = sender.username.trim();
  return ChatPeer(
    name: sender.displayName,
    handle: username.isEmpty
        ? ''
        : (username.startsWith('@') ? username : '@$username'),
    status: 'Profil',
    avatarUrl: sender.profileImageUrl,
    online: false,
  );
}

final conversationMessagesProvider = FutureProvider.autoDispose
    .family<List<AppConversationMessage>, int>((ref, conversationId) async {
      final session = await ref.watch(appAuthProvider.future);
      final token = session?.token;
      if (token == null || token.trim().isEmpty) {
        return const [];
      }

      final localStore = ChatLocalStore.instance;
      final cachedMessages = await localStore.getConversationMessages(
        conversationId,
        limit: _conversationMessagePageSize,
      );
      if (cachedMessages.isNotEmpty) {
        if (_shouldRefreshConversation(conversationId)) {
          unawaited(
            _refreshConversationMessagesFromApi(
              ref,
              token: token,
              conversationId: conversationId,
              previous: cachedMessages,
            ),
          );
        }
        return cachedMessages;
      }

      final api = AppAuthApi();
      try {
        final messages = await api.fetchConversationMessages(
          token,
          conversationId: conversationId,
        );
        await localStore.upsertConversationMessages(messages);
        try {
          await api.markConversationRead(token, conversationId: conversationId);
          ref.read(conversationFeedRefreshProvider.notifier).state++;
        } catch (_) {}
        return messages;
      } catch (_) {
        if (cachedMessages.isNotEmpty) {
          return cachedMessages;
        }
        rethrow;
      } finally {
        api.close();
      }
    });

final chatPeerProfileProvider = FutureProvider.autoDispose
    .family<AppMatchCandidate, int>((ref, userId) async {
      ref.keepAlive();
      final cached = _chatPeerProfileCache[userId];
      if (cached != null) {
        return cached;
      }

      final session = await ref.watch(appAuthProvider.future);
      final token = session?.token;
      if (token == null || token.trim().isEmpty) {
        throw const ApiException(
          'Bu profili gormek icin once giris yapmalisin.',
        );
      }

      final api = AppAuthApi();
      try {
        final profile = await api.fetchDatingPeerProfile(token, userId: userId);
        _chatPeerProfileCache[userId] = profile;
        return profile;
      } finally {
        api.close();
      }
    });

final chatGiftsProvider = FutureProvider.autoDispose<List<AppGift>>((
  ref,
) async {
  final session = await ref.watch(appAuthProvider.future);
  final token = session?.token;
  if (token == null || token.trim().isEmpty) {
    return const [];
  }

  final api = AppAuthApi();
  try {
    return await api.fetchGifts(token);
  } finally {
    api.close();
  }
});

@immutable
class _ChatThemePalette {
  final String label;
  final Color background;
  final Color incomingBubble;
  final Color incomingText;
  final LinearGradient outgoingGradient;

  const _ChatThemePalette({
    required this.label,
    required this.background,
    required this.incomingBubble,
    required this.incomingText,
    required this.outgoingGradient,
  });
}

const List<_ChatThemePalette> _chatThemes = [
  _ChatThemePalette(
    label: 'Varsayilan',
    background: AppColors.white,
    incomingBubble: AppColors.grayField,
    incomingText: AppColors.black,
    outgoingGradient: LinearGradient(
      begin: Alignment.topLeft,
      end: Alignment.bottomRight,
      colors: [Color(0xFF5C6BFF), Color(0xFF7B6FFF)],
    ),
  ),
  _ChatThemePalette(
    label: 'Gece',
    background: Color(0xFF111118),
    incomingBubble: Color(0xFF222230),
    incomingText: AppColors.white,
    outgoingGradient: LinearGradient(
      begin: Alignment.topLeft,
      end: Alignment.bottomRight,
      colors: [Color(0xFF6EE7F9), Color(0xFF8B5CF6)],
    ),
  ),
  _ChatThemePalette(
    label: 'Gunbatimi',
    background: Color(0xFFFFF4ED),
    incomingBubble: Color(0xFFFFE0D2),
    incomingText: Color(0xFF4B2A22),
    outgoingGradient: LinearGradient(
      begin: Alignment.topLeft,
      end: Alignment.bottomRight,
      colors: [Color(0xFFFF7A59), Color(0xFFFFB86B)],
    ),
  ),
  _ChatThemePalette(
    label: 'Orman',
    background: Color(0xFFF0F8F1),
    incomingBubble: Color(0xFFDDEFE1),
    incomingText: Color(0xFF173322),
    outgoingGradient: LinearGradient(
      begin: Alignment.topLeft,
      end: Alignment.bottomRight,
      colors: [Color(0xFF16A34A), Color(0xFF84CC16)],
    ),
  ),
  _ChatThemePalette(
    label: 'Pamuk',
    background: Color(0xFFFFF7FB),
    incomingBubble: Color(0xFFFFE4F1),
    incomingText: Color(0xFF4A1931),
    outgoingGradient: LinearGradient(
      begin: Alignment.topLeft,
      end: Alignment.bottomRight,
      colors: [Color(0xFFEC4899), Color(0xFFF9A8D4)],
    ),
  ),
  _ChatThemePalette(
    label: 'Deniz',
    background: Color(0xFFEFF9FF),
    incomingBubble: Color(0xFFDDF1FF),
    incomingText: Color(0xFF123044),
    outgoingGradient: LinearGradient(
      begin: Alignment.topLeft,
      end: Alignment.bottomRight,
      colors: [Color(0xFF0EA5E9), Color(0xFF22D3EE)],
    ),
  ),
];

_ChatThemePalette _chatThemeForPeer(int? peerId) {
  if (peerId == null) {
    return _chatThemes.first;
  }
  final selected = _chatThemeSelectionCache[peerId];
  if (selected != null && selected >= 0 && selected < _chatThemes.length) {
    return _chatThemes[selected];
  }
  return _chatThemes[_defaultChatThemeIndex(peerId)];
}

int _defaultChatThemeIndex(int peerId) => peerId.abs() % _chatThemes.length;

Color _readableColorOn(Color background) {
  return background.computeLuminance() > 0.55
      ? AppColors.black
      : AppColors.white;
}

Color _mutedReadableColorOn(Color background) {
  return _readableColorOn(background).withValues(alpha: 0.64);
}

SystemUiOverlayStyle _systemOverlayForTheme(_ChatThemePalette theme) {
  final chromeColor = theme.incomingBubble;
  final isLightChrome = chromeColor.computeLuminance() > 0.55;

  return SystemUiOverlayStyle(
    statusBarColor: chromeColor,
    systemNavigationBarColor: chromeColor,
    statusBarIconBrightness: isLightChrome ? Brightness.dark : Brightness.light,
    statusBarBrightness: isLightChrome ? Brightness.light : Brightness.dark,
    systemNavigationBarIconBrightness: isLightChrome
        ? Brightness.dark
        : Brightness.light,
  );
}

String _chatThemePreferenceKey(int peerId) => 'chat_theme_peer_$peerId';

Future<int> _loadChatThemeIndex(int peerId) async {
  final cached = _chatThemeSelectionCache[peerId];
  if (cached != null && cached >= 0 && cached < _chatThemes.length) {
    return cached;
  }

  final prefs = await SharedPreferences.getInstance();
  final stored = prefs.getInt(_chatThemePreferenceKey(peerId));
  final value = stored != null && stored >= 0 && stored < _chatThemes.length
      ? stored
      : _defaultChatThemeIndex(peerId);
  _chatThemeSelectionCache[peerId] = value;
  return value;
}

Future<void> _saveChatThemeIndex(int peerId, int index) async {
  if (index < 0 || index >= _chatThemes.length) {
    return;
  }
  _chatThemeSelectionCache[peerId] = index;
  final prefs = await SharedPreferences.getInstance();
  await prefs.setInt(_chatThemePreferenceKey(peerId), index);
}

List<AppProfilePhoto> _galleryMediaForProfile(
  AppMatchCandidate? profile,
  String? fallbackAvatarUrl,
) {
  final media = profile?.photos ?? const <AppProfilePhoto>[];
  final visibleMedia = media
      .where((item) => item.isActive && item.displayUrl.trim().isNotEmpty)
      .toList();
  if (visibleMedia.isNotEmpty) {
    return visibleMedia;
  }

  final fallback = fallbackAvatarUrl?.trim();
  if (fallback == null || fallback.isEmpty) {
    return const <AppProfilePhoto>[];
  }

  return [
    AppProfilePhoto(
      id: 0,
      url: fallback,
      order: 0,
      isPrimary: true,
      isActive: true,
      mediaType: 'fotograf',
    ),
  ];
}

bool _shouldRefreshConversation(int conversationId) {
  if (_conversationRefreshInFlight.contains(conversationId)) {
    return false;
  }

  final lastRefresh = _lastConversationRefreshAt[conversationId];
  if (lastRefresh == null) {
    return true;
  }

  return DateTime.now().difference(lastRefresh) >= _conversationRefreshCooldown;
}

Future<void> _refreshConversationMessagesFromApi(
  Ref ref, {
  required String token,
  required int conversationId,
  required List<AppConversationMessage> previous,
}) async {
  _conversationRefreshInFlight.add(conversationId);
  final api = AppAuthApi();
  try {
    final messages = await api.fetchConversationMessages(
      token,
      conversationId: conversationId,
    );
    await ChatLocalStore.instance.upsertConversationMessages(messages);
    _lastConversationRefreshAt[conversationId] = DateTime.now();
    try {
      await api.markConversationRead(token, conversationId: conversationId);
      ref.read(conversationFeedRefreshProvider.notifier).state++;
    } catch (_) {}
    if (!_sameMessageList(previous, messages)) {
      ref.invalidate(conversationMessagesProvider(conversationId));
    }
  } catch (_) {
    // Keep cached messages visible when refresh fails.
  } finally {
    _conversationRefreshInFlight.remove(conversationId);
    api.close();
  }
}

bool _sameMessageList(
  List<AppConversationMessage> left,
  List<AppConversationMessage> right,
) {
  if (left.length != right.length) {
    return false;
  }

  for (var index = 0; index < left.length; index++) {
    final a = left[index];
    final b = right[index];
    if (a.id != b.id ||
        a.senderId != b.senderId ||
        a.senderName != b.senderName ||
        a.type != b.type ||
        a.text != b.text ||
        a.fileDuration != b.fileDuration ||
        a.isRead != b.isRead ||
        a.isAiGenerated != b.isAiGenerated ||
        a.languageCode != b.languageCode ||
        a.languageName != b.languageName ||
        a.translatedText != b.translatedText ||
        !_sameMessageMoment(a.createdAt, b.createdAt)) {
      return false;
    }
  }

  return true;
}

bool _sameMessageMoment(DateTime? left, DateTime? right) {
  if (left == null || right == null) {
    return left == right;
  }

  return left.millisecondsSinceEpoch == right.millisecondsSinceEpoch;
}

List<AppConversationMessage> _mergeConversationMessages(
  Iterable<AppConversationMessage> left,
  Iterable<AppConversationMessage> right,
) {
  final merged = <int, AppConversationMessage>{};

  for (final message in left) {
    merged[message.id] = message;
  }
  for (final message in right) {
    merged[message.id] = message;
  }

  final result = merged.values.toList(growable: false);
  result.sort((a, b) {
    final leftMoment = a.createdAt?.millisecondsSinceEpoch ?? 0;
    final rightMoment = b.createdAt?.millisecondsSinceEpoch ?? 0;
    final momentCompare = leftMoment.compareTo(rightMoment);
    if (momentCompare != 0) {
      return momentCompare;
    }

    return a.id.compareTo(b.id);
  });

  return result;
}

String _initialsOfName(String fullName) {
  final parts = fullName
      .trim()
      .split(RegExp(r'\s+'))
      .where((part) => part.isNotEmpty)
      .toList();
  if (parts.isEmpty) {
    return '?';
  }
  if (parts.length == 1) {
    final value = parts.first;
    return value.substring(0, value.length >= 2 ? 2 : 1).toUpperCase();
  }
  return (parts.first.substring(0, 1) + parts.last.substring(0, 1))
      .toUpperCase();
}

Color _peerAvatarColor(String name) {
  const palette = [
    Color(0xFFA594F9),
    Color(0xFFFFB4C6),
    Color(0xFFFDB384),
    Color(0xFFFF9794),
    Color(0xFFAEDFF7),
    Color(0xFFB6E0B8),
    Color(0xFFFFE4A5),
    Color(0xFFC4C9FF),
    Color(0xFF9AA2B1),
  ];

  var hash = 0;
  for (final rune in name.runes) {
    hash = (hash * 31 + rune) & 0x7fffffff;
  }
  return palette[hash % palette.length];
}

class _ChatPeerAvatar extends StatelessWidget {
  final ChatPeer peer;
  final double size;
  final bool showOnline;

  const _ChatPeerAvatar({
    required this.peer,
    required this.size,
    this.showOnline = false,
  });

  @override
  Widget build(BuildContext context) {
    Widget avatar;
    final avatarUrl = peer.avatarUrl;
    final avatarAsset = peer.avatarAsset;

    if (avatarUrl != null && avatarUrl.isNotEmpty) {
      avatar = ClipOval(
        child: avatarUrl.startsWith('http')
            ? Image.network(
                avatarUrl,
                width: size,
                height: size,
                fit: BoxFit.cover,
                gaplessPlayback: true,
              )
            : Image.file(
                File(avatarUrl),
                width: size,
                height: size,
                fit: BoxFit.cover,
                errorBuilder: (_, _, _) {
                  final base = _peerAvatarColor(peer.name);
                  return Container(
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
                      _initialsOfName(peer.name),
                      style: TextStyle(
                        fontFamily: AppFont.family,
                        fontWeight: FontWeight.w700,
                        fontSize: size * 0.34,
                        color: AppColors.white,
                      ),
                    ),
                  );
                },
              ),
      );
    } else if (avatarAsset != null && avatarAsset.isNotEmpty) {
      avatar = ClipOval(
        child: Image.asset(
          avatarAsset,
          width: size,
          height: size,
          fit: BoxFit.cover,
        ),
      );
    } else {
      final base = _peerAvatarColor(peer.name);
      avatar = Container(
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
          _initialsOfName(peer.name),
          style: TextStyle(
            fontFamily: AppFont.family,
            fontWeight: FontWeight.w700,
            fontSize: size * 0.36,
            color: AppColors.white,
          ),
        ),
      );
    }

    if (!showOnline || !peer.online) {
      return SizedBox(width: size, height: size, child: avatar);
    }

    return SizedBox(
      width: size,
      height: size,
      child: Stack(
        children: [
          avatar,
          Positioned(
            right: 0,
            bottom: 0,
            child: Container(
              width: size * 0.3,
              height: size * 0.3,
              decoration: BoxDecoration(
                color: const Color(0xFF2DD4A0),
                shape: BoxShape.circle,
                border: Border.all(color: AppColors.white, width: 2),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

// ------ Chat header / app bar -------------------------------------------------

class _ChatAppBar extends StatelessWidget {
  final ChatPeer peer;
  final _ChatThemePalette theme;
  final VoidCallback? onAvatarTap;
  final VoidCallback? onGiftTap;
  final VoidCallback? onMoreTap;

  const _ChatAppBar({
    required this.peer,
    required this.theme,
    this.onAvatarTap,
    this.onGiftTap,
    this.onMoreTap,
  });

  @override
  Widget build(BuildContext context) {
    final hasStatus = peer.status.trim().isNotEmpty;
    final chromeColor = theme.incomingBubble;
    final iconColor = _readableColorOn(chromeColor);
    final subtleIconColor = _mutedReadableColorOn(chromeColor);

    return Container(
      height: 64,
      padding: const EdgeInsets.symmetric(horizontal: 12),
      decoration: BoxDecoration(
        color: chromeColor,
        border: Border(
          bottom: BorderSide(
            color: iconColor.withValues(alpha: 0.08),
            width: 1,
          ),
        ),
      ),
      child: Row(
        children: [
          PressableScale(
            onTap: () => Navigator.of(context).maybePop(),
            scale: 0.9,
            child: Padding(
              padding: const EdgeInsets.all(6),
              child: Icon(
                CupertinoIcons.chevron_back,
                size: 22,
                color: iconColor,
              ),
            ),
          ),
          const SizedBox(width: 4),
          PressableScale(
            onTap: onAvatarTap ?? () {},
            scale: 0.95,
            child: _ChatPeerAvatar(peer: peer, size: 40, showOnline: true),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: PressableScale(
              onTap: onAvatarTap ?? () {},
              scale: 0.99,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    peer.name,
                    style: TextStyle(
                      fontFamily: AppFont.family,
                      fontWeight: FontWeight.w800,
                      fontSize: 16,
                      color: iconColor,
                    ),
                  ),
                  if (hasStatus)
                    Text(
                      peer.status,
                      style: TextStyle(
                        fontFamily: AppFont.family,
                        fontWeight: FontWeight.w600,
                        fontSize: 11.5,
                        color: peer.online
                            ? const Color(0xFF2DD4A0)
                            : subtleIconColor,
                      ),
                    ),
                ],
              ),
            ),
          ),
          if (onGiftTap != null) ...[
            _GiftStarChip(onTap: onGiftTap!),
            const SizedBox(width: 8),
          ],
          if (onMoreTap != null)
            PressableScale(
              onTap: onMoreTap!,
              scale: 0.92,
              child: Container(
                width: 38,
                height: 38,
                decoration: BoxDecoration(
                  color: theme.background,
                  borderRadius: BorderRadius.circular(19),
                ),
                alignment: Alignment.center,
                child: Icon(
                  CupertinoIcons.ellipsis_vertical,
                  size: 18,
                  color: _readableColorOn(theme.background),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _GiftStarChip extends StatelessWidget {
  final VoidCallback onTap;

  const _GiftStarChip({required this.onTap});

  @override
  Widget build(BuildContext context) {
    return PressableScale(
      onTap: onTap,
      scale: 0.92,
      child: Container(
        width: 38,
        height: 38,
        decoration: BoxDecoration(
          color: const Color(0xFFFFF7E8),
          borderRadius: BorderRadius.circular(19),
          border: Border.all(color: const Color(0xFFFFE7BA)),
        ),
        alignment: Alignment.center,
        child: const Icon(
          CupertinoIcons.gift_fill,
          size: 19,
          color: Color(0xFFFF9F0A),
        ),
      ),
    );
  }
}

// ------ Chat bubbles ----------------------------------------------------------

class _MessageBubble extends StatelessWidget {
  final ChatMessage message;
  final ChatPeer peer;
  final _ChatThemePalette theme;
  final VoidCallback? onReport;

  const _MessageBubble({
    required this.message,
    required this.peer,
    required this.theme,
    this.onReport,
  });

  @override
  Widget build(BuildContext context) {
    final isMe = message.side == ChatBubbleSide.me;

    Widget content;
    switch (message.type) {
      case ChatMessageType.typing:
        content = const _TypingBubble();
      case ChatMessageType.image:
        content = _ImageBubble(asset: message.asset!);
      case ChatMessageType.audio:
        content = _AudioBubble(
          isMe: isMe,
          duration: message.duration ?? const Duration(),
        );
      case ChatMessageType.text:
        content = _TextBubble(
          isMe: isMe,
          text: message.text ?? '',
          translatedText: message.translatedText,
          translationLanguageName: message.translationLanguageName,
          theme: theme,
        );
    }

    final bubble = Row(
      crossAxisAlignment: CrossAxisAlignment.end,
      mainAxisAlignment: isMe ? MainAxisAlignment.end : MainAxisAlignment.start,
      children: [
        if (!isMe) ...[
          _ChatPeerAvatar(peer: peer, size: 30),
          const SizedBox(width: 8),
        ],
        Flexible(
          child: Column(
            crossAxisAlignment: isMe
                ? CrossAxisAlignment.end
                : CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              content,
              if (message.time.isNotEmpty) ...[
                const SizedBox(height: 4),
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 4),
                  child: Text(
                    message.time,
                    style: const TextStyle(
                      fontFamily: AppFont.family,
                      fontSize: 10,
                      color: Color(0xFFCCCCCC),
                    ),
                  ),
                ),
              ],
            ],
          ),
        ),
      ],
    );

    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 6, 16, 6),
      child: onReport == null
          ? bubble
          : GestureDetector(
              behavior: HitTestBehavior.opaque,
              onLongPress: onReport,
              child: bubble,
            ),
    );
  }
}

void _openMessageReportSheet(
  BuildContext context, {
  required int messageId,
  required String peerName,
}) {
  showCupertinoModalPopup<void>(
    context: context,
    builder: (_) => ReportSheet(
      targetType: ReportTargetType.message,
      targetId: messageId,
      targetDisplayName: peerName,
    ),
  );
}

void _showMessageActions(
  BuildContext context, {
  required AppConversationMessage message,
  required ChatPeer peer,
  VoidCallback? onTranslate,
}) {
  showCupertinoModalPopup<void>(
    context: context,
    builder: (sheetContext) => CupertinoActionSheet(
      title: Text(
        peer.name,
        style: const TextStyle(fontFamily: AppFont.family),
      ),
      message: const Text(
        'Bu mesajla ilgili islem secin.',
        style: TextStyle(fontFamily: AppFont.family),
      ),
      actions: [
        if (onTranslate != null)
          CupertinoActionSheetAction(
            onPressed: () {
              Navigator.of(sheetContext).pop();
              onTranslate();
            },
            child: Text(
              message.translatedText == null ? 'Cevir' : 'Ceviriyi Yenile',
              style: const TextStyle(fontFamily: AppFont.family),
            ),
          ),
        CupertinoActionSheetAction(
          isDestructiveAction: true,
          onPressed: () {
            Navigator.of(sheetContext).pop();
            _openMessageReportSheet(
              context,
              messageId: message.id,
              peerName: peer.name,
            );
          },
          child: const Text(
            'Bu Mesaji Sikayet Et',
            style: TextStyle(fontFamily: AppFont.family),
          ),
        ),
      ],
      cancelButton: CupertinoActionSheetAction(
        onPressed: () => Navigator.of(sheetContext).pop(),
        child: const Text(
          'Vazgec',
          style: TextStyle(fontFamily: AppFont.family),
        ),
      ),
    ),
  );
}

class _TextBubble extends StatelessWidget {
  final bool isMe;
  final String text;
  final String? translatedText;
  final String? translationLanguageName;
  final _ChatThemePalette theme;

  const _TextBubble({
    required this.isMe,
    required this.text,
    this.translatedText,
    this.translationLanguageName,
    required this.theme,
  });

  @override
  Widget build(BuildContext context) {
    final bg = isMe
        ? BoxDecoration(gradient: theme.outgoingGradient)
        : BoxDecoration(color: theme.incomingBubble);
    final radius = isMe
        ? const BorderRadius.only(
            topLeft: Radius.circular(20),
            topRight: Radius.circular(20),
            bottomLeft: Radius.circular(20),
            bottomRight: Radius.circular(6),
          )
        : const BorderRadius.only(
            topLeft: Radius.circular(20),
            topRight: Radius.circular(20),
            bottomLeft: Radius.circular(6),
            bottomRight: Radius.circular(20),
          );
    final textColor = isMe ? AppColors.white : theme.incomingText;

    final translation = translatedText?.trim();

    return Container(
      constraints: const BoxConstraints(maxWidth: 258),
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 11),
      decoration: bg.copyWith(borderRadius: radius),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            text,
            style: TextStyle(
              fontFamily: AppFont.family,
              fontWeight: FontWeight.w500,
              fontSize: 14,
              height: 1.45,
              color: textColor,
            ),
          ),
          if (!isMe && translation != null && translation.isNotEmpty) ...[
            const SizedBox(height: 10),
            Container(
              height: 1,
              color: textColor.withValues(alpha: 0.14),
            ),
            const SizedBox(height: 8),
            Text(
              translationLanguageName == null
                  ? 'Ceviri'
                  : 'Ceviri • $translationLanguageName',
              style: TextStyle(
                fontFamily: AppFont.family,
                fontWeight: FontWeight.w700,
                fontSize: 10.5,
                color: textColor.withValues(alpha: 0.62),
              ),
            ),
            const SizedBox(height: 4),
            Text(
              translation,
              style: TextStyle(
                fontFamily: AppFont.family,
                fontWeight: FontWeight.w600,
                fontSize: 13,
                height: 1.42,
                color: textColor,
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _ImageBubble extends StatelessWidget {
  final String asset;

  const _ImageBubble({required this.asset});

  @override
  Widget build(BuildContext context) {
    final imageWidget = asset.startsWith('http')
        ? Image.network(
            asset,
            fit: BoxFit.cover,
            gaplessPlayback: true,
            errorBuilder: (_, _, _) => _imagePlaceholder(),
          )
        : asset.startsWith('assets/')
        ? Image.asset(asset, fit: BoxFit.cover)
        : Image.file(
            File(asset),
            fit: BoxFit.cover,
            errorBuilder: (_, _, _) => _imagePlaceholder(),
          );

    return ClipRRect(
      borderRadius: BorderRadius.circular(20),
      child: SizedBox(width: 198, height: 220, child: imageWidget),
    );
  }

  Widget _imagePlaceholder() {
    return Container(
      color: AppColors.grayField,
      alignment: Alignment.center,
      child: const Icon(CupertinoIcons.photo, size: 28, color: AppColors.gray),
    );
  }
}

class _AudioBubble extends StatelessWidget {
  final bool isMe;
  final Duration duration;

  const _AudioBubble({required this.isMe, required this.duration});

  static const List<double> _bars = [
    0.35,
    0.55,
    0.7,
    0.9,
    0.65,
    0.4,
    0.15,
    0.15,
    0.1,
    0.35,
    0.55,
    0.8,
    0.65,
    0.75,
    0.7,
    0.45,
    0.1,
    0.25,
    0.4,
    0.75,
    0.85,
    1.0,
    0.85,
  ];

  @override
  Widget build(BuildContext context) {
    final bg = isMe
        ? const BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: [Color(0xFF5C6BFF), Color(0xFF7B6FFF)],
            ),
          )
        : const BoxDecoration(color: AppColors.grayField);
    final radius = isMe
        ? const BorderRadius.only(
            topLeft: Radius.circular(20),
            topRight: Radius.circular(20),
            bottomLeft: Radius.circular(20),
            bottomRight: Radius.circular(6),
          )
        : const BorderRadius.only(
            topLeft: Radius.circular(20),
            topRight: Radius.circular(20),
            bottomLeft: Radius.circular(6),
            bottomRight: Radius.circular(20),
          );
    final minutes = duration.inMinutes.remainder(60).toString().padLeft(1, '0');
    final seconds = duration.inSeconds.remainder(60).toString().padLeft(2, '0');
    final playBg = isMe
        ? AppColors.white.withValues(alpha: 0.2)
        : AppColors.black;
    final barColor = isMe
        ? AppColors.white.withValues(alpha: 0.45)
        : const Color(0xFFC4C4C4);
    final timeColor = isMe
        ? AppColors.white.withValues(alpha: 0.6)
        : const Color(0xFF999999);

    return Container(
      width: 216,
      height: 54,
      padding: const EdgeInsets.symmetric(horizontal: 10),
      decoration: bg.copyWith(borderRadius: radius),
      child: Row(
        children: [
          Container(
            width: 34,
            height: 34,
            decoration: BoxDecoration(
              color: playBg,
              borderRadius: BorderRadius.circular(17),
            ),
            alignment: Alignment.center,
            child: Icon(
              CupertinoIcons.play_fill,
              size: 14,
              color: AppColors.white,
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: CustomPaint(
              size: const Size.fromHeight(22),
              painter: _WaveformPainter(bars: _bars, color: barColor),
            ),
          ),
          const SizedBox(width: 8),
          Text(
            '$minutes:$seconds',
            style: TextStyle(
              fontFamily: AppFont.family,
              fontWeight: FontWeight.w700,
              fontSize: 12,
              color: timeColor,
            ),
          ),
        ],
      ),
    );
  }
}

class _WaveformPainter extends CustomPainter {
  final List<double> bars;
  final Color color;

  _WaveformPainter({required this.bars, required this.color});

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = color
      ..strokeCap = StrokeCap.round
      ..strokeWidth = 2.5;
    final gap = size.width / bars.length;
    final midY = size.height / 2;
    for (var i = 0; i < bars.length; i++) {
      final h = bars[i] * size.height;
      final x = i * gap + gap / 2;
      canvas.drawLine(Offset(x, midY - h / 2), Offset(x, midY + h / 2), paint);
    }
  }

  @override
  bool shouldRepaint(covariant _WaveformPainter old) =>
      old.color != color || old.bars != bars;
}

class _TypingBubble extends StatefulWidget {
  const _TypingBubble();

  @override
  State<_TypingBubble> createState() => _TypingBubbleState();
}

class _TypingBubbleState extends State<_TypingBubble>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1200),
    )..repeat();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 35,
      width: 67,
      decoration: BoxDecoration(
        color: AppColors.grayField,
        borderRadius: BorderRadius.circular(20),
      ),
      child: AnimatedBuilder(
        animation: _controller,
        builder: (context, _) {
          return Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: List.generate(3, (i) {
              final t = ((_controller.value + i * 0.2) % 1.0);
              final scale = 0.6 + (1.0 - (t - 0.5).abs() * 2).clamp(0, 1) * 0.6;
              return Padding(
                padding: const EdgeInsets.symmetric(horizontal: 2.5),
                child: Transform.scale(
                  scale: scale.toDouble(),
                  child: Container(
                    width: 7,
                    height: 7,
                    decoration: const BoxDecoration(
                      color: Color(0xFFCCCCCC),
                      shape: BoxShape.circle,
                    ),
                  ),
                ),
              );
            }),
          );
        },
      ),
    );
  }
}

enum ChatInputVariant { empty, full }

class _ChatInputBar extends StatelessWidget {
  final ChatInputVariant variant;
  final _ChatThemePalette theme;
  final TextEditingController? controller;
  final VoidCallback? onSend;
  final VoidCallback? onLeadingTap;
  final VoidCallback? onMicTap;
  final bool canSend;
  final bool isSending;
  final String? errorText;

  const _ChatInputBar({
    this.variant = ChatInputVariant.empty,
    required this.theme,
    this.controller,
    this.onSend,
    this.onLeadingTap,
    this.onMicTap,
    this.canSend = false,
    this.isSending = false,
    this.errorText,
  });

  @override
  Widget build(BuildContext context) {
    final isEmpty = variant == ChatInputVariant.empty;
    final hasComposer = controller != null;
    final canUseSend = hasComposer && canSend;
    final barColor = theme.incomingBubble;
    final fieldColor = theme.background;
    final barIconColor = _readableColorOn(barColor);
    final fieldTextColor = _readableColorOn(fieldColor);
    final placeholderColor = _mutedReadableColorOn(fieldColor);
    final sendColor = canUseSend || onMicTap != null
        ? theme.outgoingGradient.colors.first
        : fieldColor;
    final sendIconColor = _readableColorOn(sendColor);

    return Container(
      padding: const EdgeInsets.fromLTRB(14, 12, 14, 16),
      decoration: BoxDecoration(color: barColor),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (errorText != null && errorText!.trim().isNotEmpty) ...[
            Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: Align(
                alignment: Alignment.centerLeft,
                child: Text(
                  errorText!,
                  style: const TextStyle(
                    fontFamily: AppFont.family,
                    fontSize: 12,
                    color: Color(0xFFEF4444),
                  ),
                ),
              ),
            ),
          ],
          Row(
            children: [
              _circleButton(
                icon: isEmpty ? CupertinoIcons.camera : CupertinoIcons.photo,
                bg: fieldColor,
                iconColor: fieldTextColor,
                onTap: onLeadingTap,
              ),
              const SizedBox(width: 8),
              Expanded(
                child: hasComposer
                    ? CupertinoTextField(
                        controller: controller,
                        placeholder: 'Mesaj yaz...',
                        textInputAction: TextInputAction.send,
                        onSubmitted: (_) => onSend?.call(),
                        padding: const EdgeInsets.symmetric(
                          horizontal: 16,
                          vertical: 12,
                        ),
                        decoration: BoxDecoration(
                          color: fieldColor,
                          borderRadius: BorderRadius.circular(24),
                        ),
                        style: TextStyle(
                          fontFamily: AppFont.family,
                          fontWeight: FontWeight.w500,
                          fontSize: 14.5,
                          color: fieldTextColor,
                        ),
                        placeholderStyle: TextStyle(
                          fontFamily: AppFont.family,
                          fontWeight: FontWeight.w500,
                          fontSize: 14.5,
                          color: placeholderColor,
                        ),
                      )
                    : Container(
                        height: 44,
                        padding: const EdgeInsets.symmetric(horizontal: 16),
                        decoration: BoxDecoration(
                          color: fieldColor,
                          borderRadius: BorderRadius.circular(24),
                        ),
                        child: Row(
                          children: [
                            Expanded(
                              child: Text(
                                isEmpty ? 'Message...' : 'Mesaj yaz...',
                                style: TextStyle(
                                  fontFamily: AppFont.family,
                                  fontWeight: FontWeight.w500,
                                  fontSize: 14.5,
                                  color: placeholderColor,
                                ),
                              ),
                            ),
                            if (isEmpty) ...[
                              const SizedBox(width: 8),
                              Icon(
                                CupertinoIcons.paperclip,
                                size: 18,
                                color: placeholderColor,
                              ),
                            ],
                          ],
                        ),
                      ),
              ),
              const SizedBox(width: 8),
              _circleButton(
                icon: canUseSend
                    ? CupertinoIcons.arrow_up
                    : CupertinoIcons.mic_fill,
                bg: sendColor,
                iconColor: canUseSend || onMicTap != null
                    ? sendIconColor
                    : barIconColor,
                onTap: canUseSend && !isSending ? onSend : onMicTap,
                child: canUseSend && isSending
                    ? const CupertinoActivityIndicator(radius: 9)
                    : null,
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _circleButton({
    required IconData icon,
    required Color bg,
    required Color iconColor,
    VoidCallback? onTap,
    Widget? child,
  }) {
    return PressableScale(
      onTap: onTap,
      scale: onTap == null ? 1 : 0.92,
      child: Container(
        width: 44,
        height: 44,
        decoration: BoxDecoration(
          color: bg,
          borderRadius: BorderRadius.circular(22),
        ),
        alignment: Alignment.center,
        child: child ?? Icon(icon, size: 19, color: iconColor),
      ),
    );
  }
}

// ------ Chat Screen -----------------------------------------------------------

enum ChatScreenMode { empty, messages }

class ChatScreen extends ConsumerStatefulWidget {
  final ChatScreenMode mode;
  final AppConversationPreview? conversation;

  const ChatScreen({
    super.key,
    this.mode = ChatScreenMode.messages,
    this.conversation,
  });

  @override
  ConsumerState<ChatScreen> createState() => _ChatScreenState();
}

class _ChatScreenState extends ConsumerState<ChatScreen> {
  late final TextEditingController _messageController;
  ChatRealtimeSubscription? _realtimeSubscription;
  bool _isSending = false;
  bool? _mutedOverride;
  bool? _blockedOverride;
  int? _themeIndex;
  String? _inputError;
  String? _peerStatusOverride;
  int? _realtimeConversationId;
  String? _realtimeToken;

  @override
  void initState() {
    super.initState();
    _messageController = TextEditingController()
      ..addListener(_handleInputChange);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) {
        return;
      }
      unawaited(_bindRealtimeSubscription());
      unawaited(_loadThemeSelection());
    });
  }

  @override
  void didUpdateWidget(covariant ChatScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.conversation?.id != widget.conversation?.id) {
      _themeIndex = null;
      _peerStatusOverride = null;
      unawaited(_bindRealtimeSubscription(force: true));
      unawaited(_loadThemeSelection());
    }
  }

  @override
  void dispose() {
    final realtimeSubscription = _realtimeSubscription;
    _realtimeSubscription = null;
    if (realtimeSubscription != null) {
      unawaited(realtimeSubscription.dispose());
    }
    _messageController
      ..removeListener(_handleInputChange)
      ..dispose();
    super.dispose();
  }

  void _handleInputChange() {
    if (mounted) {
      setState(() {
        if (_inputError != null && _messageController.text.trim().isNotEmpty) {
          _inputError = null;
        }
      });
    }
  }

  ChatPeer get _peer {
    final conversation = widget.conversation;
    if (conversation != null) {
      final basePeer = ChatPeer.fromConversation(conversation);
      if (_peerStatusOverride != null && _peerStatusOverride!.trim().isNotEmpty) {
        return ChatPeer(
          name: basePeer.name,
          handle: basePeer.handle,
          status: _peerStatusOverride!,
          avatarAsset: basePeer.avatarAsset,
          avatarUrl: basePeer.avatarUrl,
          languageCode: basePeer.languageCode,
          languageName: basePeer.languageName,
          online: basePeer.online,
        );
      }
      return basePeer;
    }
    return _emptyChatPeer;
  }

  Future<void> _loadThemeSelection() async {
    final peerId = widget.conversation?.peerId;
    if (peerId == null) {
      return;
    }
    final index = await _loadChatThemeIndex(peerId);
    if (!mounted || widget.conversation?.peerId != peerId) {
      return;
    }
    setState(() => _themeIndex = index);
  }

  Future<void> _selectTheme(int index) async {
    final peerId = widget.conversation?.peerId;
    if (peerId == null || index < 0 || index >= _chatThemes.length) {
      return;
    }
    setState(() => _themeIndex = index);
    await _saveChatThemeIndex(peerId, index);
  }

  Future<void> _bindRealtimeSubscription({bool force = false}) async {
    if (!mounted) {
      return;
    }

    final conversation = widget.conversation;
    final authState = ref.read(appAuthProvider).asData?.value;
    final token = authState?.token;

    if (conversation == null || token == null || token.trim().isEmpty) {
      await _disposeRealtimeSubscription();
      return;
    }

    if (!force &&
        _realtimeSubscription != null &&
        _realtimeConversationId == conversation.id &&
        _realtimeToken == token) {
      return;
    }

    await _disposeRealtimeSubscription();

    try {
      final subscription = await ChatRealtimeService.instance
          .subscribeToConversation(
            token: token,
            conversationId: conversation.id,
            onEvent: _handleRealtimeEvent,
          );

      if (!mounted) {
        await subscription?.dispose();
        return;
      }

      _realtimeSubscription = subscription;
      _realtimeConversationId = conversation.id;
      _realtimeToken = token;
    } catch (error) {
      debugPrint('Chat realtime subscribe error: $error');
    }
  }

  Future<void> _disposeRealtimeSubscription() async {
    final realtimeSubscription = _realtimeSubscription;
    _realtimeSubscription = null;
    _realtimeConversationId = null;
    _realtimeToken = null;
    await realtimeSubscription?.dispose();
  }

  void _handleRealtimeEvent(ChatRealtimeEvent event) {
    if (!mounted) {
      return;
    }

    if (event.type == ChatRealtimeEventType.aiStatus) {
      final nextStatusText = event.payload['status_text']?.toString();
      setState(() {
        _peerStatusOverride =
            nextStatusText == null || nextStatusText.trim().isEmpty
            ? null
            : nextStatusText;
      });
    }

    ref.invalidate(conversationMessagesProvider(event.conversationId));
    ref.read(conversationFeedRefreshProvider.notifier).state++;
  }

  Future<void> _sendMessage() async {
    final conversation = widget.conversation;
    final authState = ref.read(appAuthProvider).asData?.value;
    final text = _messageController.text.trim();

    if (conversation == null ||
        authState == null ||
        text.isEmpty ||
        _isSending) {
      return;
    }

    setState(() {
      _isSending = true;
      _inputError = null;
    });

    final api = AppAuthApi();
    try {
      final sentMessage = await api.sendConversationMessage(
        authState.token,
        conversationId: conversation.id,
        text: text,
      );
      await ChatLocalStore.instance.upsertConversationMessage(sentMessage);
      _messageController.clear();
      ref.invalidate(conversationMessagesProvider(conversation.id));
      ref.read(conversationFeedRefreshProvider.notifier).state++;
    } catch (error) {
      if (!mounted) {
        return;
      }
      final message = AppAuthErrorFormatter.messageFrom(error);
      if (error is BlockedByUserApiException ||
          message.toLowerCase().contains('engelledi')) {
        await showCupertinoDialog<void>(
          context: context,
          builder: (dialogContext) => CupertinoAlertDialog(
            title: const Text(
              'Mesaj Gonderilemedi',
              style: TextStyle(fontFamily: AppFont.family),
            ),
            content: Padding(
              padding: const EdgeInsets.only(top: 8),
              child: Text(
                message,
                style: const TextStyle(fontFamily: AppFont.family),
              ),
            ),
            actions: [
              CupertinoDialogAction(
                onPressed: () => Navigator.of(dialogContext).pop(),
                child: const Text(
                  'Tamam',
                  style: TextStyle(fontFamily: AppFont.family),
                ),
              ),
            ],
          ),
        );
        return;
      }
      setState(() {
        _inputError = message;
      });
    } finally {
      api.close();
      if (mounted) {
        setState(() {
          _isSending = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final hasConversation = widget.conversation != null;
    final peerProfile = hasConversation
        ? ref
              .watch(chatPeerProfileProvider(widget.conversation!.peerId))
              .asData
              ?.value
        : null;
    final isMuted = _mutedOverride ?? peerProfile?.muted ?? false;
    final isBlocked = _blockedOverride ?? peerProfile?.blocked ?? false;
    final theme = _themeIndex == null
        ? _chatThemeForPeer(widget.conversation?.peerId)
        : _chatThemes[_themeIndex!];

    ref.listen<AsyncValue<AppAuthState?>>(appAuthProvider, (previous, next) {
      if (!mounted) {
        return;
      }

      final previousToken = previous?.asData?.value?.token;
      final nextToken = next.asData?.value?.token;
      if (previousToken != nextToken) {
        unawaited(_bindRealtimeSubscription(force: true));
      }
    });

    void openProfile() {
      if (!hasConversation) {
        return;
      }
      Navigator.of(context).push(
        cupertinoRoute(
          ChatProfileScreen(
            peer: _peer,
            conversation: widget.conversation,
            selectedThemeIndex: _themeIndex,
            onThemeSelected: _selectTheme,
          ),
        ),
      );
    }

    void openGift() {
      if (!hasConversation) {
        return;
      }
      showCupertinoModalPopup<void>(
        context: context,
        builder: (_) => GiftSheet(
          targetUserId: widget.conversation!.peerId,
          peerName: _peer.name,
        ),
      );
    }

    void openMute() {
      if (!hasConversation) {
        return;
      }
      showCupertinoModalPopup<void>(
        context: context,
        builder: (_) => MuteConversationSheet(
          targetUserId: widget.conversation!.peerId,
          peerName: _peer.name,
          initiallyMuted: isMuted,
          onChanged: (muted) => setState(() => _mutedOverride = muted),
        ),
      );
    }

    void openAttachmentMenu() {
      if (!hasConversation) {
        return;
      }
      showCupertinoModalPopup<void>(
        context: context,
        builder: (_) => MediaAttachmentSheet(peerName: _peer.name),
      );
    }

    void openRecorder() {
      if (!hasConversation) {
        return;
      }
      showCupertinoModalPopup<void>(
        context: context,
        builder: (_) => VoiceRecorderSheet(peerName: _peer.name),
      );
    }

    void openMore() {
      if (!hasConversation) {
        return;
      }
      showCupertinoModalPopup<void>(
        context: context,
        builder: (ctx) => CupertinoActionSheet(
          actions: [
            CupertinoActionSheetAction(
              onPressed: () {
                Navigator.of(ctx).pop();
                openMute();
              },
              child: Text(
                isMuted ? 'Sessizden Cikar' : 'Sessize Al',
                style: const TextStyle(fontFamily: AppFont.family),
              ),
            ),
            CupertinoActionSheetAction(
              onPressed: () {
                Navigator.of(ctx).pop();
                showCupertinoModalPopup<void>(
                  context: context,
                  builder: (_) => ReportSheet(
                    targetId: widget.conversation?.peerId,
                    targetDisplayName: _peer.name,
                  ),
                );
              },
              child: const Text(
                'Sikayet Et',
                style: TextStyle(
                  fontFamily: AppFont.family,
                  color: Color(0xFFEF4444),
                ),
              ),
            ),
            CupertinoActionSheetAction(
              isDestructiveAction: !isBlocked,
              onPressed: () {
                Navigator.of(ctx).pop();
                showCupertinoModalPopup<void>(
                  context: context,
                  builder: (_) => BlockConfirmSheet(
                    targetUserId: widget.conversation?.peerId,
                    targetDisplayName: _peer.name,
                    initiallyBlocked: isBlocked,
                    onChanged: (blocked) =>
                        setState(() => _blockedOverride = blocked),
                  ),
                );
              },
              child: Text(
                isBlocked ? 'Engelden Cikar' : 'Engelle',
                style: const TextStyle(fontFamily: AppFont.family),
              ),
            ),
          ],
          cancelButton: CupertinoActionSheetAction(
            onPressed: () => Navigator.of(ctx).pop(),
            child: const Text(
              'Vazgec',
              style: TextStyle(fontFamily: AppFont.family),
            ),
          ),
        ),
      );
    }

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: _systemOverlayForTheme(theme),
      child: CupertinoPageScaffold(
        backgroundColor: theme.incomingBubble,
        child: SafeArea(
          bottom: false,
          child: Column(
            children: [
              _ChatAppBar(
                peer: _peer,
                theme: theme,
                onAvatarTap: hasConversation ? openProfile : null,
                onGiftTap: hasConversation ? openGift : null,
                onMoreTap: hasConversation ? openMore : null,
              ),
              Expanded(
                child: ColoredBox(
                  color: theme.background,
                  child: widget.mode == ChatScreenMode.empty
                      ? const _ChatEmptyBody()
                      : _ChatMessagesBody(
                          peer: _peer,
                          conversation: widget.conversation,
                          theme: theme,
                        ),
                ),
              ),
              _ChatInputBar(
                variant: widget.mode == ChatScreenMode.empty
                    ? ChatInputVariant.empty
                    : ChatInputVariant.full,
                theme: theme,
                controller: widget.conversation == null
                    ? null
                    : _messageController,
                onSend: _sendMessage,
                onLeadingTap: openAttachmentMenu,
                onMicTap: openRecorder,
                canSend:
                    _messageController.text.trim().isNotEmpty && !_isSending,
                isSending: _isSending,
                errorText: _inputError,
              ),
              ColoredBox(
                color: theme.incomingBubble,
                child: SizedBox(height: MediaQuery.paddingOf(context).bottom),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ChatEmptyBody extends StatelessWidget {
  const _ChatEmptyBody();

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final compact = constraints.maxHeight < 620;

        return Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Spacer(),
            Image.asset(
              'assets/images/hello_mascot.png',
              width: compact ? 180 : 220,
              height: compact ? 210 : 260,
              fit: BoxFit.contain,
            ),
            const SizedBox(height: 8),
            const Text(
              'Bir Selam Ver!',
              style: TextStyle(
                fontFamily: AppFont.family,
                fontWeight: FontWeight.w800,
                fontSize: 17,
                color: AppColors.black,
              ),
            ),
            const SizedBox(height: 4),
            const Text(
              'Hadi bu firsati kacirma...',
              style: TextStyle(
                fontFamily: AppFont.family,
                fontSize: 13.5,
                height: 1.5,
                color: Color(0xFF666666),
              ),
            ),
            const Spacer(flex: 2),
          ],
        );
      },
    );
  }
}

class _ChatMessagesBody extends StatelessWidget {
  final ChatPeer peer;
  final AppConversationPreview? conversation;
  final _ChatThemePalette theme;

  const _ChatMessagesBody({
    required this.peer,
    required this.theme,
    this.conversation,
  });

  @override
  Widget build(BuildContext context) {
    if (conversation == null) {
      return const _ChatEmptyBody();
    }

    return _LiveChatMessagesBody(
      conversation: conversation!,
      peer: peer,
      theme: theme,
    );
  }
}

class _LiveChatMessagesBody extends ConsumerStatefulWidget {
  final AppConversationPreview conversation;
  final ChatPeer peer;
  final _ChatThemePalette theme;

  const _LiveChatMessagesBody({
    required this.conversation,
    required this.peer,
    required this.theme,
  });

  @override
  ConsumerState<_LiveChatMessagesBody> createState() =>
      _LiveChatMessagesBodyState();
}

class _LiveChatMessagesBodyState extends ConsumerState<_LiveChatMessagesBody> {
  late final ScrollController _scrollController;
  List<AppConversationMessage> _messages = const <AppConversationMessage>[];
  bool _initialLoading = true;
  bool _loadingOlder = false;
  bool _refreshingLatest = false;
  bool _hasMoreOlder = true;
  int _highestFetchedPage = 0;
  String? _errorText;
  String? _aiStatus;
  int? _lastHandledRefreshTick;

  @override
  void initState() {
    super.initState();
    _scrollController = ScrollController()..addListener(_handleScroll);
    _lastHandledRefreshTick = ref.read(conversationFeedRefreshProvider);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) {
        return;
      }
      unawaited(_loadInitialMessages());
    });
  }

  @override
  void didUpdateWidget(covariant _LiveChatMessagesBody oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.conversation.id != widget.conversation.id) {
      _messages = const <AppConversationMessage>[];
      _initialLoading = true;
      _loadingOlder = false;
      _refreshingLatest = false;
      _hasMoreOlder = true;
      _highestFetchedPage = 0;
      _errorText = null;
      _lastHandledRefreshTick = ref.read(conversationFeedRefreshProvider);
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (!mounted) {
          return;
        }
        unawaited(_loadInitialMessages());
      });
    }
  }

  @override
  void dispose() {
    _scrollController
      ..removeListener(_handleScroll)
      ..dispose();
    super.dispose();
  }

  void _handleScroll() {
    if (!_scrollController.hasClients ||
        _scrollController.position.pixels > 96) {
      return;
    }
    unawaited(_loadOlderMessages());
  }

  bool _isNearBottom() {
    if (!_scrollController.hasClients) {
      return true;
    }

    return _scrollController.position.maxScrollExtent -
            _scrollController.position.pixels <=
        88;
  }

  void _scrollToBottom({bool animated = true}) {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted || !_scrollController.hasClients) {
        return;
      }

      final target = _scrollController.position.maxScrollExtent;
      if (animated) {
        _scrollController.animateTo(
          target,
          duration: const Duration(milliseconds: 220),
          curve: Curves.easeOutCubic,
        );
        return;
      }

      _scrollController.jumpTo(target);
    });
  }

  Future<void> _markConversationRead(String token) async {
    final api = AppAuthApi();
    try {
      await api.markConversationRead(
        token,
        conversationId: widget.conversation.id,
      );
    } catch (_) {
      // Keep chat readable when read receipts fail.
    } finally {
      api.close();
    }
  }

  Future<void> _loadInitialMessages() async {
    final authState = ref.read(appAuthProvider).asData?.value;
    final token = authState?.token;
    final conversationId = widget.conversation.id;

    if (token == null || token.trim().isEmpty) {
      if (!mounted) {
        return;
      }
      setState(() {
        _initialLoading = false;
        _errorText = 'Mesajlari gormek icin once giris yapmalisin.';
      });
      return;
    }

    setState(() {
      _initialLoading = true;
      _errorText = null;
      _messages = const <AppConversationMessage>[];
      _loadingOlder = false;
      _refreshingLatest = false;
      _hasMoreOlder = true;
      _highestFetchedPage = 0;
    });

    final localStore = ChatLocalStore.instance;
    final cachedMessages = await localStore.getConversationMessages(
      conversationId,
      limit: _conversationMessagePageSize,
    );
    if (!mounted || widget.conversation.id != conversationId) {
      return;
    }

    if (cachedMessages.isNotEmpty) {
      setState(() {
        _messages = cachedMessages;
        _initialLoading = false;
        _errorText = null;
      });
      _scrollToBottom(animated: false);
    }

    final api = AppAuthApi();
    try {
      final page = await api.fetchConversationMessagesPage(
        token,
        conversationId: conversationId,
        page: 1,
      );
      if (!mounted || widget.conversation.id != conversationId) {
        return;
      }

      await localStore.upsertConversationMessages(page.messages);
      unawaited(_markConversationRead(token));

      setState(() {
        _messages = _mergeConversationMessages(const [], page.messages);
        _initialLoading = false;
        _errorText = null;
        _highestFetchedPage = 1;
        _hasMoreOlder = page.hasMore;
        _aiStatus = page.aiStatus;
      });
      _scrollToBottom(animated: false);
    } catch (error) {
      if (!mounted || widget.conversation.id != conversationId) {
        return;
      }

      if (cachedMessages.isNotEmpty) {
        setState(() => _initialLoading = false);
      } else {
        setState(() {
          _initialLoading = false;
          _errorText = AppAuthErrorFormatter.messageFrom(error);
        });
      }
    } finally {
      api.close();
    }
  }

  Future<void> _refreshLatestMessages({bool scrollToBottom = false}) async {
    if (_refreshingLatest) {
      return;
    }

    final authState = ref.read(appAuthProvider).asData?.value;
    final token = authState?.token;
    final conversationId = widget.conversation.id;
    if (token == null || token.trim().isEmpty) {
      return;
    }

    _refreshingLatest = true;
    final api = AppAuthApi();
    try {
      final page = await api.fetchConversationMessagesPage(
        token,
        conversationId: conversationId,
        page: 1,
      );
      if (!mounted || widget.conversation.id != conversationId) {
        return;
      }

      await ChatLocalStore.instance.upsertConversationMessages(page.messages);
      unawaited(_markConversationRead(token));

      final previousLastId = _messages.isNotEmpty ? _messages.last.id : null;
      final mergedMessages = _mergeConversationMessages(
        _messages,
        page.messages,
      );
      final nextLastId = mergedMessages.isNotEmpty
          ? mergedMessages.last.id
          : null;

      setState(() {
        _messages = mergedMessages;
        _errorText = null;
        _aiStatus = page.aiStatus;
        if (_highestFetchedPage == 0) {
          _highestFetchedPage = 1;
          _hasMoreOlder = page.hasMore;
        }
      });

      if (scrollToBottom || previousLastId != nextLastId) {
        _scrollToBottom(animated: previousLastId != null);
      }
    } catch (_) {
      // Keep the current message list visible when refresh fails.
    } finally {
      _refreshingLatest = false;
      api.close();
    }
  }

  Future<void> _loadOlderMessages() async {
    if (_loadingOlder || !_hasMoreOlder || _highestFetchedPage == 0) {
      return;
    }

    final authState = ref.read(appAuthProvider).asData?.value;
    final token = authState?.token;
    final conversationId = widget.conversation.id;
    if (token == null || token.trim().isEmpty) {
      return;
    }

    final previousOffset = _scrollController.hasClients
        ? _scrollController.position.pixels
        : 0.0;
    final previousMaxExtent = _scrollController.hasClients
        ? _scrollController.position.maxScrollExtent
        : 0.0;
    final nextPageNumber = _highestFetchedPage + 1;

    setState(() => _loadingOlder = true);

    final api = AppAuthApi();
    try {
      final page = await api.fetchConversationMessagesPage(
        token,
        conversationId: conversationId,
        page: nextPageNumber,
      );
      if (!mounted || widget.conversation.id != conversationId) {
        return;
      }

      await ChatLocalStore.instance.upsertConversationMessages(page.messages);

      setState(() {
        _messages = _mergeConversationMessages(page.messages, _messages);
        _loadingOlder = false;
        _errorText = null;
        _highestFetchedPage = nextPageNumber;
        _hasMoreOlder = page.hasMore;
      });

      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (!mounted || !_scrollController.hasClients) {
          return;
        }

        final newMaxExtent = _scrollController.position.maxScrollExtent;
        final delta = newMaxExtent - previousMaxExtent;
        _scrollController.jumpTo(previousOffset + delta);
      });
    } catch (_) {
      if (!mounted || widget.conversation.id != conversationId) {
        return;
      }

      setState(() => _loadingOlder = false);
    } finally {
      api.close();
    }
  }

  Future<void> _translateMessage(AppConversationMessage message) async {
    final authState = ref.read(appAuthProvider).asData?.value;
    final token = authState?.token;
    if (token == null || token.trim().isEmpty) {
      return;
    }

    final api = AppAuthApi();
    try {
      final translated = await api.translateConversationMessage(
        token,
        message: message,
      );
      await ChatLocalStore.instance.upsertConversationMessage(translated);
      if (!mounted || widget.conversation.id != message.conversationId) {
        return;
      }

      setState(() {
        _messages = _messages
            .map((item) => item.id == translated.id ? translated : item)
            .toList(growable: false);
      });
    } catch (error) {
      if (!mounted) {
        return;
      }
      await showCupertinoDialog<void>(
        context: context,
        builder: (dialogContext) => CupertinoAlertDialog(
          title: const Text(
            'Ceviri alinamadi',
            style: TextStyle(fontFamily: AppFont.family),
          ),
          content: Padding(
            padding: const EdgeInsets.only(top: 8),
            child: Text(
              AppAuthErrorFormatter.messageFrom(error),
              style: const TextStyle(fontFamily: AppFont.family),
            ),
          ),
          actions: [
            CupertinoDialogAction(
              onPressed: () => Navigator.of(dialogContext).pop(),
              child: const Text(
                'Tamam',
                style: TextStyle(fontFamily: AppFont.family),
              ),
            ),
          ],
        ),
      );
    } finally {
      api.close();
    }
  }

  @override
  Widget build(BuildContext context) {
    final currentUserId = ref.watch(appAuthProvider).asData?.value?.user?.id;
    if (currentUserId == null) {
      return const Center(child: CupertinoActivityIndicator(radius: 14));
    }

    final refreshTick = ref.watch(conversationFeedRefreshProvider);
    if (_lastHandledRefreshTick != refreshTick) {
      _lastHandledRefreshTick = refreshTick;
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (!mounted) {
          return;
        }
        unawaited(_refreshLatestMessages(scrollToBottom: _isNearBottom()));
      });
    }

    if (_initialLoading && _messages.isEmpty) {
      return const Center(child: CupertinoActivityIndicator(radius: 14));
    }

    if (_errorText != null && _messages.isEmpty) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 24),
          child: Text(
            _errorText!,
            textAlign: TextAlign.center,
            style: const TextStyle(
              fontFamily: AppFont.family,
              fontSize: 13,
              color: AppColors.neutral600,
            ),
          ),
        ),
      );
    }

    if (_messages.isEmpty) {
      if (_aiStatus == 'typing' || _aiStatus == 'queued') {
        return ListView(
          controller: _scrollController,
          physics: const BouncingScrollPhysics(),
          padding: const EdgeInsets.only(top: 16, bottom: 16),
          children: [
            _MessageBubble(
              message: const ChatMessage.typing(),
              peer: widget.peer,
              theme: widget.theme,
            ),
          ],
        );
      }

      return const _ChatEmptyBody();
    }

    final uiMessages = _messages
        .map((message) => _chatMessageFromApi(message, currentUserId))
        .toList(growable: true);

    if (_aiStatus == 'typing' || _aiStatus == 'queued') {
      uiMessages.add(const ChatMessage.typing());
    }
    final extraTopItemCount = _loadingOlder ? 1 : 0;

    return ListView.builder(
      controller: _scrollController,
      physics: const BouncingScrollPhysics(),
      padding: const EdgeInsets.only(top: 16, bottom: 16),
      itemCount: uiMessages.length + extraTopItemCount,
      itemBuilder: (context, index) {
        if (_loadingOlder && index == 0) {
          return const Padding(
            padding: EdgeInsets.only(bottom: 10),
            child: Center(child: CupertinoActivityIndicator(radius: 11)),
          );
        }

        final messageIndex = index - extraTopItemCount;
        final uiMessage = uiMessages[messageIndex];
        if (messageIndex >= _messages.length) {
          return _MessageBubble(
            message: uiMessage,
            peer: widget.peer,
            theme: widget.theme,
          );
        }

        final apiMessage = _messages[messageIndex];
        final canReportMessage =
            !apiMessage.isFromUser(currentUserId) && uiMessage.id != null;
        final canTranslateMessage =
            canReportMessage &&
            apiMessage.type == 'metin' &&
            (apiMessage.text?.trim().isNotEmpty ?? false);

        return _MessageBubble(
          message: uiMessage,
          peer: widget.peer,
          theme: widget.theme,
          onReport: canReportMessage
              ? () => _showMessageActions(
                  context,
                  message: apiMessage,
                  peer: widget.peer,
                  onTranslate: canTranslateMessage
                      ? () => _translateMessage(apiMessage)
                      : null,
                )
              : null,
        );
      },
    );
  }
}

ChatMessage _chatMessageFromApi(
  AppConversationMessage message,
  int currentUserId,
) {
  final type = switch (message.type) {
    'foto' || 'gorsel' => ChatMessageType.image,
    'ses' => ChatMessageType.audio,
    _ => ChatMessageType.text,
  };

  return ChatMessage(
    id: message.id,
    side: message.isFromUser(currentUserId)
        ? ChatBubbleSide.me
        : ChatBubbleSide.them,
    type: type,
    text: message.text,
    translatedText: message.translatedText,
    translationLanguageName: message.translationTargetLanguageName,
    languageName: message.languageName,
    asset: message.fileUrl,
    duration: message.fileDuration,
    time: _formatChatClock(message.createdAt),
  );
}

String _formatChatClock(DateTime? value) {
  if (value == null) {
    return '';
  }

  final local = value.toLocal();
  final hour = local.hour.toString().padLeft(2, '0');
  final minute = local.minute.toString().padLeft(2, '0');
  return '$hour:$minute';
}

// ------ Chat Profile Screen ---------------------------------------------------

class ChatProfileScreen extends ConsumerStatefulWidget {
  final ChatPeer? peer;
  final AppConversationPreview? conversation;
  final int? profileUserId;
  final int? selectedThemeIndex;
  final ValueChanged<int>? onThemeSelected;

  const ChatProfileScreen({
    super.key,
    this.peer,
    this.conversation,
    this.profileUserId,
    this.selectedThemeIndex,
    this.onThemeSelected,
  });

  @override
  ConsumerState<ChatProfileScreen> createState() => _ChatProfileScreenState();
}

class _ChatProfileScreenState extends ConsumerState<ChatProfileScreen> {
  bool? _mutedOverride;
  bool? _blockedOverride;

  @override
  Widget build(BuildContext context) {
    final peerId = widget.profileUserId ?? widget.conversation?.peerId;
    final profileAsync = peerId == null
        ? null
        : ref.watch(chatPeerProfileProvider(peerId));
    final profile = profileAsync?.asData?.value;
    final fallbackPeer =
        widget.peer ??
        (widget.conversation != null
            ? ChatPeer.fromConversation(widget.conversation!)
            : _emptyChatPeer);
    final peer = profile != null
        ? _chatPeerFromCandidate(profile)
        : fallbackPeer;
    final galleryMedia = _galleryMediaForProfile(profile, peer.avatarUrl);
    final isMuted = _mutedOverride ?? profile?.muted ?? false;
    final isBlocked = _blockedOverride ?? profile?.blocked ?? false;

    void openGift() {
      if (peerId == null) {
        return;
      }
      showCupertinoModalPopup<void>(
        context: context,
        builder: (_) => GiftSheet(targetUserId: peerId, peerName: peer.name),
      );
    }

    void openMute() {
      if (peerId == null) {
        return;
      }
      showCupertinoModalPopup<void>(
        context: context,
        builder: (_) => MuteConversationSheet(
          targetUserId: peerId,
          peerName: peer.name,
          initiallyMuted: isMuted,
          onChanged: (muted) => setState(() => _mutedOverride = muted),
        ),
      );
    }

    return CupertinoPageScaffold(
      backgroundColor: AppColors.neutral100,
      child: SafeArea(
        child: Column(
          children: [
            Container(
              height: 52,
              padding: const EdgeInsets.symmetric(horizontal: 12),
              alignment: Alignment.centerLeft,
              decoration: const BoxDecoration(
                color: AppColors.neutral100,
                border: Border(
                  bottom: BorderSide(color: Color(0xFFE9E9EE), width: 1),
                ),
              ),
              child: PressableScale(
                onTap: () => Navigator.of(context).maybePop(),
                scale: 0.9,
                child: const Padding(
                  padding: EdgeInsets.all(8),
                  child: Icon(
                    CupertinoIcons.chevron_back,
                    size: 22,
                    color: AppColors.black,
                  ),
                ),
              ),
            ),
            Expanded(
              child: ListView(
                physics: const BouncingScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(12, 12, 12, 24),
                children: [
                  Center(
                    child: Container(
                      width: 90,
                      height: 90,
                      decoration: const BoxDecoration(
                        shape: BoxShape.circle,
                        boxShadow: [
                          BoxShadow(
                            color: Color(0x14000000),
                            blurRadius: 16,
                            offset: Offset(0, 4),
                          ),
                        ],
                      ),
                      child: ClipOval(
                        child: _ChatPeerAvatar(peer: peer, size: 90),
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                  Center(
                    child: Text(
                      peer.name,
                      style: const TextStyle(
                        fontFamily: AppFont.family,
                        fontWeight: FontWeight.w800,
                        fontSize: 20,
                        color: AppColors.black,
                        letterSpacing: -0.5,
                      ),
                    ),
                  ),
                  const SizedBox(height: 2),
                  Center(
                    child: Text(
                      peer.handle,
                      style: const TextStyle(
                        fontFamily: AppFont.family,
                        fontSize: 13,
                        color: Color(0xFF999999),
                      ),
                    ),
                  ),
                  const SizedBox(height: 4),
                  Center(
                    child: Text(
                      peer.status,
                      style: const TextStyle(
                        fontFamily: AppFont.family,
                        fontWeight: FontWeight.w700,
                        fontSize: 12,
                        color: Color(0xFF2DD4A0),
                      ),
                    ),
                  ),
                  const SizedBox(height: 20),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      _ProfileActionChip(
                        label: 'Hediye Gonder',
                        gradient: false,
                        icon: const Icon(
                          CupertinoIcons.gift_fill,
                          size: 20,
                          color: AppColors.black,
                        ),
                        onTap: openGift,
                      ),
                      const SizedBox(width: 18),
                      _ProfileActionChip(
                        label: isMuted ? 'Sessizden Cikar' : 'Sessize Al',
                        gradient: false,
                        icon: Icon(
                          isMuted
                              ? CupertinoIcons.bell_slash_fill
                              : CupertinoIcons.bell,
                          size: 20,
                          color: AppColors.black,
                        ),
                        onTap: openMute,
                      ),
                    ],
                  ),
                  const SizedBox(height: 24),
                  _PeerProfileMediaSection(
                    media: galleryMedia,
                    loading: profileAsync?.isLoading == true,
                  ),
                  if ((profile?.receivedGifts ?? const <AppReceivedGift>[])
                      .isNotEmpty) ...[
                    const SizedBox(height: 24),
                    _GiftSendersSection(gifts: profile!.receivedGifts),
                  ],
                  const SizedBox(height: 24),
                  _ChatThemeSection(
                    peerId: peerId,
                    selectedIndex: widget.selectedThemeIndex,
                    onThemeSelected: widget.onThemeSelected,
                  ),
                  const SizedBox(height: 24),
                  _DangerListCard(
                    isBlocked: isBlocked,
                    onBlock: () => showCupertinoModalPopup<void>(
                      context: context,
                      builder: (_) => BlockConfirmSheet(
                        targetUserId: peerId,
                        targetDisplayName: peer.name,
                        initiallyBlocked: isBlocked,
                        onChanged: (blocked) =>
                            setState(() => _blockedOverride = blocked),
                      ),
                    ),
                    onReport: () => showCupertinoModalPopup<void>(
                      context: context,
                      builder: (_) => ReportSheet(
                        targetId: peerId,
                        targetDisplayName: peer.name,
                      ),
                    ),
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

class _ProfileActionChip extends StatelessWidget {
  final String label;
  final Widget icon;
  final bool gradient;
  final VoidCallback onTap;

  const _ProfileActionChip({
    required this.label,
    required this.icon,
    required this.gradient,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        PressableScale(
          onTap: onTap,
          scale: 0.92,
          child: Container(
            width: 50,
            height: 50,
            decoration: BoxDecoration(
              gradient: gradient
                  ? const LinearGradient(
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                      colors: [Color(0xFFA594F9), Color(0xFF7C6DF5)],
                    )
                  : null,
              color: gradient ? null : const Color(0xFFEEEEF0),
              shape: BoxShape.circle,
              boxShadow: gradient
                  ? const [
                      BoxShadow(
                        color: Color(0x3C7C6DF5),
                        blurRadius: 10,
                        offset: Offset(0, 3),
                      ),
                    ]
                  : null,
            ),
            alignment: Alignment.center,
            child: gradient
                ? Transform.rotate(angle: -0.35, child: icon)
                : icon,
          ),
        ),
        const SizedBox(height: 8),
        Text(
          label,
          style: const TextStyle(
            fontFamily: AppFont.family,
            fontWeight: FontWeight.w600,
            fontSize: 11,
            color: Color(0xFF555555),
          ),
        ),
      ],
    );
  }
}

class _GiftSendersSection extends StatelessWidget {
  final List<AppReceivedGift> gifts;

  const _GiftSendersSection({required this.gifts});

  @override
  Widget build(BuildContext context) {
    final senderEntries = <int, _GiftSenderEntry>{};

    for (final gift in gifts) {
      final sender = gift.sender;
      final senderId = sender?.id;
      if (sender == null || senderId == null || senderId <= 0) {
        continue;
      }

      final current = senderEntries[senderId];
      senderEntries[senderId] = _GiftSenderEntry(
        sender: sender,
        totalGiftCount: (current?.totalGiftCount ?? 0) + 1,
        giftCounts: _mergeGiftCounts(current?.giftCounts, gift),
      );
    }

    if (senderEntries.isEmpty) {
      return const SizedBox.shrink();
    }

    final entries = senderEntries.values.toList();
    entries.sort((a, b) => b.totalGiftCount.compareTo(a.totalGiftCount));

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Hediye Gonderenler',
            style: TextStyle(
              fontFamily: AppFont.family,
              fontWeight: FontWeight.w800,
              fontSize: 15,
              color: AppColors.black,
            ),
          ),
          const SizedBox(height: 12),
          ListView.separated(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            itemCount: entries.length,
            separatorBuilder: (_, _) => const SizedBox(height: 10),
            itemBuilder: (context, index) {
              final entry = entries[index];
              return _GiftSenderListItem(
                entry: entry,
                onTap: () => Navigator.of(context).push(
                  cupertinoRoute(
                    ChatProfileScreen(
                      peer: _chatPeerFromGiftSender(entry.sender),
                      profileUserId: entry.sender.id,
                    ),
                  ),
                ),
              );
            },
          ),
        ],
      ),
    );
  }

  Map<String, _GiftTypeCount> _mergeGiftCounts(
    Map<String, _GiftTypeCount>? current,
    AppReceivedGift gift,
  ) {
    final next = <String, _GiftTypeCount>{...?current};
    final key = '${gift.giftId ?? 0}:${gift.name}:${gift.icon}';
    final existing = next[key];

    next[key] = _GiftTypeCount(
      giftId: gift.giftId,
      name: gift.name,
      icon: gift.icon,
      count: (existing?.count ?? 0) + 1,
    );

    return next;
  }
}

class _GiftTypeCount {
  final int? giftId;
  final String name;
  final String icon;
  final int count;

  const _GiftTypeCount({
    required this.giftId,
    required this.name,
    required this.icon,
    required this.count,
  });
}

class _GiftSenderEntry {
  final AppGiftSender sender;
  final int totalGiftCount;
  final Map<String, _GiftTypeCount> giftCounts;

  const _GiftSenderEntry({
    required this.sender,
    required this.totalGiftCount,
    required this.giftCounts,
  });

  List<_GiftTypeCount> get sortedGiftCounts {
    final values = giftCounts.values.toList();
    values.sort((a, b) => b.count.compareTo(a.count));
    return values;
  }
}

class _GiftSenderListItem extends StatelessWidget {
  final _GiftSenderEntry entry;
  final VoidCallback onTap;

  const _GiftSenderListItem({required this.entry, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return PressableScale(
      onTap: onTap,
      scale: 0.98,
      child: Container(
        width: double.infinity,
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: AppColors.grayField,
          borderRadius: BorderRadius.circular(18),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _GiftSenderAvatar(sender: entry.sender, size: 44),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    entry.sender.displayName,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontFamily: AppFont.family,
                      fontWeight: FontWeight.w800,
                      fontSize: 13,
                      color: AppColors.black,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    '${entry.totalGiftCount} hediye gonderdi',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontFamily: AppFont.family,
                      fontSize: 11,
                      color: AppColors.gray,
                    ),
                  ),
                  const SizedBox(height: 10),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      for (final giftCount in entry.sortedGiftCounts)
                        _GiftCountBadge(giftCount: giftCount),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(width: 8),
            const Padding(
              padding: EdgeInsets.only(top: 4),
              child: Icon(
                CupertinoIcons.chevron_forward,
                size: 16,
                color: AppColors.gray,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _GiftCountBadge extends StatelessWidget {
  final _GiftTypeCount giftCount;

  const _GiftCountBadge({required this.giftCount});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            '${giftCount.count}',
            style: const TextStyle(
              fontFamily: AppFont.family,
              fontWeight: FontWeight.w800,
              fontSize: 12,
              color: AppColors.black,
            ),
          ),
          const SizedBox(width: 6),
          Text(giftCount.icon, style: const TextStyle(fontSize: 14, height: 1)),
        ],
      ),
    );
  }
}

class _GiftSenderAvatar extends StatelessWidget {
  final AppGiftSender? sender;
  final double size;

  const _GiftSenderAvatar({this.sender, this.size = 34});

  @override
  Widget build(BuildContext context) {
    final imageUrl = sender?.profileImageUrl?.trim();
    final initialSource = sender?.displayName.trim();
    final initial = initialSource == null || initialSource.isEmpty
        ? '?'
        : initialSource.substring(0, 1).toUpperCase();

    return ClipOval(
      child: Container(
        width: size,
        height: size,
        color: const Color(0xFFE8E8FF),
        child: imageUrl == null || imageUrl.isEmpty
            ? Center(
                child: Text(
                  initial,
                  style: TextStyle(
                    fontFamily: AppFont.family,
                    fontWeight: FontWeight.w800,
                    fontSize: size * 0.38,
                    color: AppColors.indigo,
                  ),
                ),
              )
            : Image.network(
                imageUrl,
                fit: BoxFit.cover,
                errorBuilder: (_, _, _) => Center(
                  child: Text(
                    initial,
                    style: TextStyle(
                      fontFamily: AppFont.family,
                      fontWeight: FontWeight.w800,
                      fontSize: size * 0.38,
                      color: AppColors.indigo,
                    ),
                  ),
                ),
              ),
      ),
    );
  }
}

enum _PeerMediaTab { all, photos, videos }

class _PeerProfileMediaSection extends StatefulWidget {
  final List<AppProfilePhoto> media;
  final bool loading;

  const _PeerProfileMediaSection({required this.media, required this.loading});

  @override
  State<_PeerProfileMediaSection> createState() =>
      _PeerProfileMediaSectionState();
}

class _PeerProfileMediaSectionState extends State<_PeerProfileMediaSection> {
  _PeerMediaTab _tab = _PeerMediaTab.all;

  List<AppProfilePhoto> get _visibleMedia {
    switch (_tab) {
      case _PeerMediaTab.photos:
        return widget.media.where((item) => item.isPhoto).toList();
      case _PeerMediaTab.videos:
        return widget.media.where((item) => item.isVideo).toList();
      case _PeerMediaTab.all:
        return widget.media;
    }
  }

  @override
  Widget build(BuildContext context) {
    final visibleMedia = _visibleMedia;
    final tiles = visibleMedia.map(_networkMediaTile).toList();

    return Column(
      children: [
        _MediaTabBar(
          selected: _tab,
          onChanged: (tab) => setState(() => _tab = tab),
        ),
        const SizedBox(height: 12),
        Container(
          padding: const EdgeInsets.all(8),
          decoration: BoxDecoration(
            color: AppColors.white,
            borderRadius: BorderRadius.circular(20),
          ),
          child: LayoutBuilder(
            builder: (context, constraints) {
              if (widget.loading) {
                return const SizedBox(
                  height: 96,
                  child: Center(child: CupertinoActivityIndicator(radius: 12)),
                );
              }

              if (tiles.isEmpty) {
                return Container(
                  height: 96,
                  alignment: Alignment.center,
                  padding: const EdgeInsets.symmetric(horizontal: 24),
                  decoration: BoxDecoration(
                    color: AppColors.grayField,
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: const Text(
                    'Bu sekmede medya yok.',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      fontFamily: AppFont.family,
                      fontWeight: FontWeight.w600,
                      fontSize: 13,
                      color: AppColors.gray,
                    ),
                  ),
                );
              }

              final tileSize = (constraints.maxWidth - 16) / 3;
              final rowCount = (tiles.length / 3).ceil();
              final gridHeight = tileSize * rowCount + 8 * (rowCount - 1);
              return SizedBox(
                height: gridHeight,
                child: GridView.count(
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  crossAxisCount: 3,
                  crossAxisSpacing: 8,
                  mainAxisSpacing: 8,
                  children: tiles,
                ),
              );
            },
          ),
        ),
      ],
    );
  }

  Widget _networkMediaTile(AppProfilePhoto media) {
    final currentMedia = _visibleMedia;
    final initialIndex = currentMedia.indexWhere((item) => item.id == media.id);

    return PressableScale(
      onTap: () => openProfileMediaViewer(
        context,
        media: currentMedia,
        initialIndex: initialIndex < 0 ? 0 : initialIndex,
      ),
      scale: 0.98,
      child: ClipRRect(
        borderRadius: BorderRadius.circular(10),
        child: Stack(
          fit: StackFit.expand,
          children: [
            if (media.isPhoto || media.previewUrl != null)
              Image.network(
                media.displayUrl,
                fit: BoxFit.cover,
                gaplessPlayback: true,
                errorBuilder: (_, _, _) => _videoPlaceholder(),
              )
            else
              _videoPlaceholder(),
            if (media.isVideo) _videoBadge(),
          ],
        ),
      ),
    );
  }

  Widget _videoBadge() {
    return Positioned(
      left: 5,
      bottom: 5,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 3),
        decoration: BoxDecoration(
          color: const Color(0xA6111111),
          borderRadius: BorderRadius.circular(999),
        ),
        child: const Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              CupertinoIcons.videocam_fill,
              size: 10,
              color: AppColors.white,
            ),
            SizedBox(width: 4),
            Text(
              'Video',
              style: TextStyle(
                fontFamily: AppFont.family,
                fontWeight: FontWeight.w700,
                fontSize: 10,
                color: AppColors.white,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _videoPlaceholder() {
    return Container(
      color: const Color(0xFFF3F4F6),
      alignment: Alignment.center,
      child: const Icon(
        CupertinoIcons.videocam_fill,
        size: 26,
        color: Color(0xFF9CA3AF),
      ),
    );
  }
}

class _MediaTabBar extends StatelessWidget {
  final _PeerMediaTab selected;
  final ValueChanged<_PeerMediaTab> onChanged;

  const _MediaTabBar({required this.selected, required this.onChanged});

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 37,
      padding: const EdgeInsets.all(3),
      decoration: BoxDecoration(
        color: const Color(0xFFF2F2F4),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        children: [
          _MediaTabItem(
            label: 'Tumu',
            selected: selected == _PeerMediaTab.all,
            onTap: () => onChanged(_PeerMediaTab.all),
          ),
          _MediaTabItem(
            label: 'Fotograflar',
            selected: selected == _PeerMediaTab.photos,
            onTap: () => onChanged(_PeerMediaTab.photos),
          ),
          _MediaTabItem(
            label: 'Videolar',
            selected: selected == _PeerMediaTab.videos,
            onTap: () => onChanged(_PeerMediaTab.videos),
          ),
        ],
      ),
    );
  }
}

class _MediaTabItem extends StatelessWidget {
  final String label;
  final bool selected;
  final VoidCallback onTap;

  const _MediaTabItem({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: PressableScale(
        onTap: onTap,
        scale: 0.98,
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 180),
          decoration: BoxDecoration(
            color: selected ? AppColors.black : const Color(0x00000000),
            borderRadius: BorderRadius.circular(10),
          ),
          alignment: Alignment.center,
          child: Text(
            label,
            style: TextStyle(
              fontFamily: AppFont.family,
              fontWeight: FontWeight.w600,
              fontSize: 12,
              color: selected ? AppColors.white : const Color(0xFF999999),
            ),
          ),
        ),
      ),
    );
  }
}

class _ChatThemeSection extends StatefulWidget {
  final int? peerId;
  final int? selectedIndex;
  final ValueChanged<int>? onThemeSelected;

  const _ChatThemeSection({
    required this.peerId,
    this.selectedIndex,
    this.onThemeSelected,
  });

  @override
  State<_ChatThemeSection> createState() => _ChatThemeSectionState();
}

class _ChatThemeSectionState extends State<_ChatThemeSection> {
  late int _selected;

  @override
  void initState() {
    super.initState();
    _selected =
        widget.selectedIndex ??
        (widget.peerId == null ? 0 : _defaultChatThemeIndex(widget.peerId!));
  }

  @override
  void didUpdateWidget(covariant _ChatThemeSection oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.selectedIndex != widget.selectedIndex ||
        oldWidget.peerId != widget.peerId) {
      _selected =
          widget.selectedIndex ??
          (widget.peerId == null ? 0 : _defaultChatThemeIndex(widget.peerId!));
    }
  }

  void _selectTheme(int index) {
    setState(() => _selected = index);
    widget.onThemeSelected?.call(index);
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(16, 18, 16, 18),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Sohbet Temasi',
            style: TextStyle(
              fontFamily: AppFont.family,
              fontWeight: FontWeight.w700,
              fontSize: 16,
              color: AppColors.black,
            ),
          ),
          const SizedBox(height: 4),
          const Text(
            'Mesajlasma gorunumunu degistir',
            style: TextStyle(
              fontFamily: AppFont.family,
              fontSize: 12,
              color: Color(0xFF999999),
            ),
          ),
          const SizedBox(height: 16),
          GridView.builder(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            itemCount: _chatThemes.length,
            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: 3,
              crossAxisSpacing: 8,
              mainAxisSpacing: 8,
              childAspectRatio: 0.72,
            ),
            itemBuilder: (context, i) => _ThemeCard(
              index: i,
              selected: _selected == i,
              onTap: () => _selectTheme(i),
            ),
          ),
        ],
      ),
    );
  }
}

class _ThemeCard extends StatelessWidget {
  final int index;
  final bool selected;
  final VoidCallback onTap;

  const _ThemeCard({
    required this.index,
    required this.selected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final theme = _chatThemes[index % _chatThemes.length];
    return PressableScale(
      onTap: onTap,
      scale: 0.97,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 180),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: selected ? AppColors.indigo : const Color(0xFFE8E8E8),
            width: 2,
          ),
          boxShadow: selected
              ? const [
                  BoxShadow(
                    color: Color(0x1F5C6BFF),
                    blurRadius: 12,
                    offset: Offset(0, 4),
                  ),
                ]
              : null,
        ),
        child: ClipRRect(
          borderRadius: BorderRadius.circular(14),
          child: Column(
            children: [
              Expanded(
                child: CustomPaint(
                  painter: _ThemePreviewPainter(
                    index: index,
                    showCheck: selected,
                  ),
                  size: Size.infinite,
                ),
              ),
              Container(
                height: 26,
                color: theme.background,
                alignment: Alignment.center,
                child: Text(
                  theme.label,
                  style: TextStyle(
                    fontFamily: AppFont.family,
                    fontWeight: FontWeight.w700,
                    fontSize: 10,
                    color: theme.incomingText,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ThemePreviewPainter extends CustomPainter {
  final int index;
  final bool showCheck;

  _ThemePreviewPainter({required this.index, required this.showCheck});

  @override
  void paint(Canvas canvas, Size size) {
    final theme = _chatThemes[index % _chatThemes.length];
    final bgPaint = Paint();
    bgPaint.color = theme.background;
    canvas.drawRect(Offset.zero & size, bgPaint);

    final leftBubbleColor = theme.incomingBubble;
    final rightBubbleShader = theme.outgoingGradient;

    final bar1 = Rect.fromLTWH(7, size.height - 46, size.width - 42, 10);
    canvas.drawRRect(
      RRect.fromRectAndCorners(
        bar1,
        topLeft: const Radius.circular(7),
        topRight: const Radius.circular(7),
        bottomLeft: const Radius.circular(3),
        bottomRight: const Radius.circular(7),
      ),
      Paint()..color = leftBubbleColor,
    );
    final bar2 = Rect.fromLTWH(
      size.width / 2 + 6,
      size.height - 33,
      size.width / 2 - 13,
      10,
    );
    final p2 = Paint();
    if (index == 2) {
      p2.color = AppColors.white.withValues(alpha: 0.3);
    } else {
      p2.shader = rightBubbleShader.createShader(bar2);
    }
    canvas.drawRRect(
      RRect.fromRectAndCorners(
        bar2,
        topLeft: const Radius.circular(7),
        topRight: const Radius.circular(7),
        bottomLeft: const Radius.circular(7),
        bottomRight: const Radius.circular(3),
      ),
      p2,
    );
    final bar3 = Rect.fromLTWH(7, size.height - 20, size.width - 66, 10);
    canvas.drawRRect(
      RRect.fromRectAndCorners(
        bar3,
        topLeft: const Radius.circular(7),
        topRight: const Radius.circular(7),
        bottomLeft: const Radius.circular(3),
        bottomRight: const Radius.circular(7),
      ),
      Paint()..color = leftBubbleColor,
    );

    if (showCheck) {
      final checkBg = Paint()..color = AppColors.indigo;
      canvas.drawCircle(Offset(size.width - 15, 15), 9, checkBg);
      final checkPaint = Paint()
        ..color = AppColors.white
        ..style = PaintingStyle.stroke
        ..strokeWidth = 1.5
        ..strokeCap = StrokeCap.round;
      final path = Path()
        ..moveTo(size.width - 19, 15)
        ..lineTo(size.width - 16, 18)
        ..lineTo(size.width - 11, 12);
      canvas.drawPath(path, checkPaint);
    }
  }

  @override
  bool shouldRepaint(covariant _ThemePreviewPainter old) =>
      old.index != index || old.showCheck != showCheck;
}

class _DangerListCard extends StatelessWidget {
  final bool isBlocked;
  final VoidCallback onBlock;
  final VoidCallback onReport;

  const _DangerListCard({
    required this.isBlocked,
    required this.onBlock,
    required this.onReport,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Column(
        children: [
          _DangerListRow(
            icon: isBlocked
                ? CupertinoIcons.check_mark_circled
                : CupertinoIcons.nosign,
            label: isBlocked ? 'Engelden Cikar' : 'Engelle',
            onTap: onBlock,
          ),
          Container(
            height: 1,
            margin: const EdgeInsets.symmetric(horizontal: 20),
            color: const Color(0xFFF0F0F0),
          ),
          _DangerListRow(
            icon: CupertinoIcons.exclamationmark_triangle,
            label: 'Sikayet Et',
            onTap: onReport,
          ),
        ],
      ),
    );
  }
}

class _DangerListRow extends StatelessWidget {
  final IconData icon;
  final String label;
  final VoidCallback onTap;

  const _DangerListRow({
    required this.icon,
    required this.label,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return PressableScale(
      onTap: onTap,
      scale: 0.99,
      child: Container(
        height: 52,
        padding: const EdgeInsets.symmetric(horizontal: 20),
        alignment: Alignment.centerLeft,
        child: Row(
          children: [
            Icon(icon, size: 18, color: const Color(0xFFEF4444)),
            const SizedBox(width: 12),
            Text(
              label,
              style: const TextStyle(
                fontFamily: AppFont.family,
                fontWeight: FontWeight.w600,
                fontSize: 14.5,
                color: Color(0xFFEF4444),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ChatSheetHandle extends StatelessWidget {
  const _ChatSheetHandle();

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Container(
        width: 48,
        height: 4,
        decoration: BoxDecoration(
          color: const Color(0xFFD4D4D4),
          borderRadius: BorderRadius.circular(8),
        ),
      ),
    );
  }
}

class _AdaptiveChatSheet extends StatelessWidget {
  final Widget child;
  final EdgeInsets padding;

  const _AdaptiveChatSheet({
    required this.child,
    this.padding = const EdgeInsets.fromLTRB(16, 12, 16, 24),
  });

  @override
  Widget build(BuildContext context) {
    final viewInsets = MediaQuery.viewInsetsOf(context);
    final safeBottom = MediaQuery.paddingOf(context).bottom;
    final screenHeight = MediaQuery.sizeOf(context).height;
    final resolvedPadding = padding.copyWith(
      bottom: padding.bottom + safeBottom,
    );
    final maxHeight = screenHeight * 0.86;

    return Align(
      alignment: Alignment.bottomCenter,
      child: AnimatedPadding(
        duration: const Duration(milliseconds: 220),
        curve: Curves.easeOut,
        padding: EdgeInsets.only(bottom: viewInsets.bottom),
        child: Container(
          width: double.infinity,
          decoration: const BoxDecoration(
            color: AppColors.white,
            borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
          ),
          child: ConstrainedBox(
            constraints: BoxConstraints(maxHeight: maxHeight),
            child: SingleChildScrollView(
              physics: const BouncingScrollPhysics(),
              padding: resolvedPadding,
              child: child,
            ),
          ),
        ),
      ),
    );
  }
}

class MuteConversationSheet extends ConsumerStatefulWidget {
  final int targetUserId;
  final String peerName;
  final bool initiallyMuted;
  final ValueChanged<bool>? onChanged;

  const MuteConversationSheet({
    super.key,
    required this.targetUserId,
    required this.peerName,
    this.initiallyMuted = false,
    this.onChanged,
  });

  @override
  ConsumerState<MuteConversationSheet> createState() =>
      _MuteConversationSheetState();
}

class _MuteConversationSheetState extends ConsumerState<MuteConversationSheet> {
  int _selected = 1;
  bool _submitting = false;
  String? _notice;

  static const List<String> _options = [
    '1 saat',
    '8 saat',
    '1 gun',
    'Her zaman',
  ];

  static const List<String> _codes = ['1_saat', '8_saat', '1_gun', 'suresiz'];

  Future<void> _applyMute() async {
    if (_submitting) {
      return;
    }
    final token = ref.read(appAuthProvider).asData?.value?.token;
    if (token == null || token.trim().isEmpty) {
      setState(() => _notice = 'Bu islemi yapmak icin once giris yapmalisin.');
      return;
    }

    setState(() {
      _submitting = true;
      _notice = null;
    });

    final api = AppAuthApi();
    try {
      if (widget.initiallyMuted) {
        await api.unmuteUser(token, userId: widget.targetUserId);
        widget.onChanged?.call(false);
      } else {
        await api.muteUser(
          token,
          userId: widget.targetUserId,
          durationCode: _codes[_selected],
        );
        widget.onChanged?.call(true);
      }
      if (!mounted) {
        return;
      }
      _chatPeerProfileCache.remove(widget.targetUserId);
      ref.invalidate(chatPeerProfileProvider(widget.targetUserId));
      unawaited(ref.read(appAuthProvider.notifier).refreshCurrentUser());
      unawaited(ref.read(matchProvider.notifier).refreshSummary());
      Navigator.of(context).maybePop();
    } catch (error) {
      if (!mounted) {
        return;
      }
      setState(() => _notice = AppAuthErrorFormatter.messageFrom(error));
    } finally {
      api.close();
      if (mounted) {
        setState(() => _submitting = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    if (widget.initiallyMuted) {
      return _AdaptiveChatSheet(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const _ChatSheetHandle(),
            const SizedBox(height: 18),
            const Text(
              'Sessizden Cikar',
              style: TextStyle(
                fontFamily: AppFont.family,
                fontWeight: FontWeight.w800,
                fontSize: 20,
                color: AppColors.black,
              ),
            ),
            const SizedBox(height: 6),
            Text(
              '${widget.peerName} icin bildirimleri yeniden acmak ister misin?',
              style: const TextStyle(
                fontFamily: AppFont.family,
                fontSize: 12.5,
                color: AppColors.neutral600,
              ),
            ),
            if (_notice != null) ...[
              const SizedBox(height: 12),
              Text(
                _notice!,
                style: const TextStyle(
                  fontFamily: AppFont.family,
                  fontSize: 12,
                  color: Color(0xFFEF4444),
                ),
              ),
            ],
            const SizedBox(height: 18),
            GradientButton(
              label: _submitting ? 'Kaldiriliyor...' : 'Sessizden cikar',
              onTap: _submitting ? null : _applyMute,
            ),
          ],
        ),
      );
    }

    return _AdaptiveChatSheet(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const _ChatSheetHandle(),
          const SizedBox(height: 18),
          const Text(
            'Sessize Al',
            style: TextStyle(
              fontFamily: AppFont.family,
              fontWeight: FontWeight.w800,
              fontSize: 20,
              color: AppColors.black,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            '${widget.peerName} icin bildirimleri ne kadar susturmak istiyorsun?',
            style: const TextStyle(
              fontFamily: AppFont.family,
              fontSize: 12.5,
              color: AppColors.neutral600,
            ),
          ),
          const SizedBox(height: 16),
          ...List.generate(_options.length, (index) {
            final selected = _selected == index;
            return Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: PressableScale(
                onTap: () => setState(() => _selected = index),
                scale: 0.98,
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 160),
                  padding: const EdgeInsets.symmetric(
                    horizontal: 16,
                    vertical: 14,
                  ),
                  decoration: BoxDecoration(
                    color: AppColors.grayField,
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(
                      color: selected
                          ? AppColors.indigo
                          : const Color(0x00000000),
                      width: 1.5,
                    ),
                  ),
                  child: Row(
                    children: [
                      Expanded(
                        child: Text(
                          _options[index],
                          style: const TextStyle(
                            fontFamily: AppFont.family,
                            fontWeight: FontWeight.w700,
                            fontSize: 14,
                            color: AppColors.black,
                          ),
                        ),
                      ),
                      if (selected)
                        const Icon(
                          CupertinoIcons.check_mark_circled_solid,
                          size: 18,
                          color: AppColors.indigo,
                        ),
                    ],
                  ),
                ),
              ),
            );
          }),
          const SizedBox(height: 10),
          if (_notice != null) ...[
            Text(
              _notice!,
              style: const TextStyle(
                fontFamily: AppFont.family,
                fontSize: 12,
                color: Color(0xFFEF4444),
              ),
            ),
            const SizedBox(height: 10),
          ],
          GradientButton(
            label: _submitting ? 'Uygulaniyor...' : 'Sessize almayi uygula',
            onTap: _submitting ? null : _applyMute,
          ),
        ],
      ),
    );
  }
}

class MediaAttachmentSheet extends StatelessWidget {
  final String peerName;

  const MediaAttachmentSheet({super.key, required this.peerName});

  @override
  Widget build(BuildContext context) {
    Widget item({
      required IconData icon,
      required String title,
      required String subtitle,
    }) {
      return Padding(
        padding: const EdgeInsets.only(bottom: 10),
        child: PressableScale(
          onTap: () => Navigator.of(context).maybePop(),
          scale: 0.98,
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
            decoration: BoxDecoration(
              color: AppColors.grayField,
              borderRadius: BorderRadius.circular(18),
            ),
            child: Row(
              children: [
                Container(
                  width: 42,
                  height: 42,
                  decoration: BoxDecoration(
                    color: AppColors.white,
                    borderRadius: BorderRadius.circular(14),
                  ),
                  alignment: Alignment.center,
                  child: Icon(icon, size: 20, color: AppColors.black),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        title,
                        style: const TextStyle(
                          fontFamily: AppFont.family,
                          fontWeight: FontWeight.w700,
                          fontSize: 14,
                          color: AppColors.black,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        subtitle,
                        style: const TextStyle(
                          fontFamily: AppFont.family,
                          fontSize: 12,
                          color: AppColors.neutral600,
                        ),
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

    return _AdaptiveChatSheet(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const _ChatSheetHandle(),
          const SizedBox(height: 18),
          const Text(
            'Medya Ekle',
            style: TextStyle(
              fontFamily: AppFont.family,
              fontWeight: FontWeight.w800,
              fontSize: 20,
              color: AppColors.black,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            '$peerName ile sohbete bir sey ekle.',
            style: const TextStyle(
              fontFamily: AppFont.family,
              fontSize: 12.5,
              color: AppColors.neutral600,
            ),
          ),
          const SizedBox(height: 16),
          item(
            icon: CupertinoIcons.camera_fill,
            title: 'Kamera',
            subtitle: 'Anlik fotograf cek ve gonder',
          ),
          item(
            icon: CupertinoIcons.photo_fill_on_rectangle_fill,
            title: 'Galeri',
            subtitle: 'Fotograf veya ekran goruntusu sec',
          ),
          item(
            icon: CupertinoIcons.videocam_fill,
            title: 'Video',
            subtitle: 'Kisa bir klip paylas',
          ),
          item(
            icon: CupertinoIcons.paperclip,
            title: 'Dosya',
            subtitle: 'Belge veya baska bir icerik ekle',
          ),
        ],
      ),
    );
  }
}

class VoiceRecorderSheet extends StatefulWidget {
  final String peerName;

  const VoiceRecorderSheet({super.key, required this.peerName});

  @override
  State<VoiceRecorderSheet> createState() => _VoiceRecorderSheetState();
}

class _VoiceRecorderSheetState extends State<VoiceRecorderSheet> {
  Timer? _timer;
  int _elapsed = 0;
  bool _recordingStopped = false;
  bool _sending = false;

  @override
  void initState() {
    super.initState();
    _timer = Timer.periodic(const Duration(seconds: 1), (_) {
      if (!mounted || _recordingStopped) {
        return;
      }
      setState(() => _elapsed++);
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  Future<void> _sendRecording() async {
    setState(() => _sending = true);
    await Future<void>.delayed(const Duration(milliseconds: 900));
    if (!mounted) {
      return;
    }
    Navigator.of(context).maybePop();
  }

  @override
  Widget build(BuildContext context) {
    final minutes = (_elapsed ~/ 60).toString().padLeft(1, '0');
    final seconds = (_elapsed % 60).toString().padLeft(2, '0');

    return _AdaptiveChatSheet(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const _ChatSheetHandle(),
          const SizedBox(height: 18),
          Text(
            _recordingStopped ? 'Kayit Hazir' : 'Ses Kaydi',
            style: const TextStyle(
              fontFamily: AppFont.family,
              fontWeight: FontWeight.w800,
              fontSize: 20,
              color: AppColors.black,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            _recordingStopped
                ? '${widget.peerName} icin kaydi gonderebilirsin.'
                : 'Konusmaya basla, kayit otomatik ilerliyor.',
            style: const TextStyle(
              fontFamily: AppFont.family,
              fontSize: 12.5,
              color: AppColors.neutral600,
            ),
          ),
          const SizedBox(height: 18),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(18),
            decoration: BoxDecoration(
              color: AppColors.grayField,
              borderRadius: BorderRadius.circular(22),
            ),
            child: Column(
              children: [
                Text(
                  '$minutes:$seconds',
                  style: const TextStyle(
                    fontFamily: AppFont.family,
                    fontWeight: FontWeight.w800,
                    fontSize: 28,
                    color: AppColors.black,
                  ),
                ),
                const SizedBox(height: 14),
                SizedBox(
                  height: 36,
                  child: CustomPaint(
                    size: const Size(double.infinity, 36),
                    painter: _WaveformPainter(
                      bars: const [
                        0.12,
                        0.2,
                        0.38,
                        0.62,
                        0.48,
                        0.78,
                        0.34,
                        0.56,
                        0.82,
                        0.41,
                        0.22,
                        0.51,
                        0.74,
                        0.44,
                        0.18,
                        0.35,
                        0.68,
                        0.5,
                      ],
                      color: _recordingStopped
                          ? AppColors.indigo
                          : AppColors.coral,
                    ),
                  ),
                ),
                const SizedBox(height: 14),
                Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Container(
                      width: 10,
                      height: 10,
                      decoration: BoxDecoration(
                        color: _recordingStopped
                            ? AppColors.indigo
                            : AppColors.coral,
                        shape: BoxShape.circle,
                      ),
                    ),
                    const SizedBox(width: 8),
                    Text(
                      _recordingStopped
                          ? 'Kayit durduruldu'
                          : 'Kayit devam ediyor',
                      style: const TextStyle(
                        fontFamily: AppFont.family,
                        fontWeight: FontWeight.w600,
                        fontSize: 12.5,
                        color: AppColors.neutral600,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(height: 18),
          if (!_recordingStopped)
            GradientButton(
              label: 'Kaydi Durdur',
              onTap: () {
                _timer?.cancel();
                setState(() => _recordingStopped = true);
              },
            )
          else
            GradientButton(
              label: _sending ? 'Gonderiliyor...' : 'Kaydi Gonder',
              onTap: _sending ? null : _sendRecording,
            ),
          const SizedBox(height: 8),
          SecondaryButton(
            label: 'Vazgec',
            onTap: () => Navigator.of(context).maybePop(),
          ),
        ],
      ),
    );
  }
}

// ------ Sheet: Gift -----------------------------------------------------------

class GiftSheet extends ConsumerStatefulWidget {
  final int targetUserId;
  final String peerName;

  const GiftSheet({
    super.key,
    required this.targetUserId,
    required this.peerName,
  });

  @override
  ConsumerState<GiftSheet> createState() => _GiftSheetState();
}

class _GiftSheetState extends ConsumerState<GiftSheet> {
  int _selectedGift = 0;
  bool _sending = false;
  String? _notice;
  int? _gemOverride;

  Future<void> _sendGift(List<AppGift> gifts) async {
    if (_sending || gifts.isEmpty) {
      return;
    }
    final token = ref.read(appAuthProvider).asData?.value?.token;
    if (token == null || token.trim().isEmpty) {
      setState(() => _notice = 'Hediye gondermek icin once giris yapmalisin.');
      return;
    }

    final selectedIndex = _selectedGift.clamp(0, gifts.length - 1).toInt();
    final gift = gifts[selectedIndex];
    setState(() {
      _sending = true;
      _notice = null;
    });

    final api = AppAuthApi();
    try {
      final currentGem = await api.sendGift(
        token,
        receiverUserId: widget.targetUserId,
        giftId: gift.id,
      );
      if (!mounted) {
        return;
      }
      if (currentGem != null) {
        setState(() => _gemOverride = currentGem);
        await ref.read(appAuthProvider.notifier).setGemBalance(currentGem);
      }
      if (!mounted) {
        return;
      }
      _chatPeerProfileCache.remove(widget.targetUserId);
      ref.invalidate(chatPeerProfileProvider(widget.targetUserId));
      unawaited(ref.read(appAuthProvider.notifier).refreshCurrentUser());
      unawaited(ref.read(matchProvider.notifier).refreshSummary());
      Navigator.of(context).maybePop();
    } catch (error) {
      if (!mounted) {
        return;
      }
      setState(() => _notice = AppAuthErrorFormatter.messageFrom(error));
    } finally {
      api.close();
      if (mounted) {
        setState(() => _sending = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final authGemBalance = ref.watch(
      appAuthProvider.select(
        (session) => session.asData?.value?.user?.gemBalance,
      ),
    );
    final matchGemBalance = ref.watch(
      matchProvider.select((s) => s.gemBalance),
    );
    final gem = _gemOverride ?? authGemBalance ?? matchGemBalance;
    final giftsAsync = ref.watch(chatGiftsProvider);
    final gifts = giftsAsync.asData?.value ?? const <AppGift>[];
    final hasGifts = gifts.isNotEmpty;
    final safeSelectedIndex = hasGifts
        ? _selectedGift.clamp(0, gifts.length - 1).toInt()
        : 0;
    final selectedCost = hasGifts ? gifts[safeSelectedIndex].cost : 0;

    return _AdaptiveChatSheet(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 20),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Center(
            child: Container(
              width: 48,
              height: 4,
              decoration: BoxDecoration(
                color: const Color(0xFFD4D4D4),
                borderRadius: BorderRadius.circular(8),
              ),
            ),
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Text(
                      'Hediye Gonder',
                      style: TextStyle(
                        fontFamily: AppFont.family,
                        fontWeight: FontWeight.w800,
                        fontSize: 20,
                        color: AppColors.black,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      '${widget.peerName} icin bir hediye sec',
                      style: const TextStyle(
                        fontFamily: AppFont.family,
                        fontSize: 12.5,
                        color: Color(0xFF999999),
                      ),
                    ),
                  ],
                ),
              ),
              BalanceChip(amount: gem),
            ],
          ),
          const SizedBox(height: 16),
          giftsAsync.when(
            loading: () => const SizedBox(
              height: 170,
              child: Center(child: CupertinoActivityIndicator(radius: 13)),
            ),
            error: (error, _) => _GiftStateMessage(
              message: AppAuthErrorFormatter.messageFrom(error),
            ),
            data: (items) {
              if (items.isEmpty) {
                return const _GiftStateMessage(
                  message: 'Su anda gonderilebilir hediye bulunmuyor.',
                );
              }

              return GridView.builder(
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                  crossAxisCount: 3,
                  mainAxisSpacing: 10,
                  crossAxisSpacing: 10,
                  childAspectRatio: 0.92,
                ),
                itemCount: items.length,
                itemBuilder: (context, i) {
                  final selected = safeSelectedIndex == i;
                  return _GiftTile(
                    gift: items[i],
                    selected: selected,
                    onTap: () => setState(() => _selectedGift = i),
                  );
                },
              );
            },
          ),
          const SizedBox(height: 20),
          Container(height: 1, color: const Color(0xFFF0F0F0)),
          const SizedBox(height: 16),
          if (_notice != null) ...[
            const SizedBox(height: 10),
            Text(
              _notice!,
              textAlign: TextAlign.center,
              style: const TextStyle(
                fontFamily: AppFont.family,
                fontSize: 12,
                color: Color(0xFFEF4444),
              ),
            ),
          ],
          _SendGiftButton(
            cost: selectedCost,
            sending: _sending,
            enabled: hasGifts,
            onTap: _sending || !hasGifts ? null : () => _sendGift(gifts),
          ),
        ],
      ),
    );
  }
}

class _GiftStateMessage extends StatelessWidget {
  final String message;

  const _GiftStateMessage({required this.message});

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 150,
      alignment: Alignment.center,
      padding: const EdgeInsets.symmetric(horizontal: 20),
      decoration: BoxDecoration(
        color: AppColors.grayField,
        borderRadius: BorderRadius.circular(18),
      ),
      child: Text(
        message,
        textAlign: TextAlign.center,
        style: const TextStyle(
          fontFamily: AppFont.family,
          fontWeight: FontWeight.w600,
          fontSize: 13,
          color: AppColors.gray,
        ),
      ),
    );
  }
}

class _GiftTile extends StatelessWidget {
  final AppGift gift;
  final bool selected;
  final VoidCallback onTap;

  const _GiftTile({
    required this.gift,
    required this.selected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return PressableScale(
      onTap: onTap,
      scale: 0.96,
      child: Stack(
        children: [
          AnimatedContainer(
            duration: const Duration(milliseconds: 180),
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: selected ? const Color(0x145C6BFF) : AppColors.grayField,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(
                color: selected ? AppColors.indigo : const Color(0x00000000),
                width: 1.5,
              ),
            ),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text(
                  gift.icon,
                  style: const TextStyle(fontSize: 34, height: 1.1),
                ),
                const SizedBox(height: 6),
                Text(
                  gift.name,
                  style: const TextStyle(
                    fontFamily: AppFont.family,
                    fontWeight: FontWeight.w700,
                    fontSize: 13,
                    color: AppColors.black,
                  ),
                ),
                const SizedBox(height: 4),
                Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    const Icon(
                      CupertinoIcons.bolt_fill,
                      size: 12,
                      color: AppColors.indigo,
                    ),
                    const SizedBox(width: 2),
                    Text(
                      '${gift.cost}',
                      style: const TextStyle(
                        fontFamily: AppFont.family,
                        fontWeight: FontWeight.w700,
                        fontSize: 12,
                        color: AppColors.indigo,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
          if (selected)
            const Positioned(
              top: 6,
              right: 6,
              child: Icon(
                CupertinoIcons.check_mark_circled_solid,
                size: 18,
                color: AppColors.indigo,
              ),
            ),
        ],
      ),
    );
  }
}

class _SendGiftButton extends StatelessWidget {
  final int cost;
  final bool sending;
  final bool enabled;
  final VoidCallback? onTap;

  const _SendGiftButton({
    required this.cost,
    required this.sending,
    this.enabled = true,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return PressableScale(
      onTap: onTap,
      child: Container(
        height: 56,
        padding: const EdgeInsets.symmetric(horizontal: 12),
        decoration: BoxDecoration(
          gradient: enabled ? AppColors.primary : null,
          color: enabled ? null : const Color(0xFFE5E7EB),
          borderRadius: BorderRadius.circular(40),
          boxShadow: const [
            BoxShadow(
              color: AppColors.shadow,
              blurRadius: 24,
              offset: Offset(0, 8),
            ),
          ],
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text(
              sending ? 'Gonderiliyor...' : 'Gonder',
              style: const TextStyle(
                fontFamily: AppFont.family,
                fontWeight: FontWeight.w800,
                fontSize: 16,
                color: AppColors.white,
              ),
            ),
            const SizedBox(width: 12),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
              decoration: BoxDecoration(
                color: AppColors.white,
                borderRadius: BorderRadius.circular(20),
              ),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Image.asset(
                    'assets/images/icon_diamond.png',
                    width: 14,
                    height: 14,
                  ),
                  const SizedBox(width: 6),
                  Text(
                    '$cost tas',
                    style: const TextStyle(
                      fontFamily: AppFont.family,
                      fontWeight: FontWeight.w600,
                      fontSize: 13,
                      color: AppColors.zinc900,
                    ),
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

// ------ Sheet: Report ---------------------------------------------------------

class ReportSheet extends ConsumerStatefulWidget {
  final ReportTargetType targetType;
  final int? targetId;
  final String targetDisplayName;

  const ReportSheet({
    super.key,
    this.targetType = ReportTargetType.user,
    this.targetId,
    this.targetDisplayName = 'Bu kullanici',
  });

  @override
  ConsumerState<ReportSheet> createState() => _ReportSheetState();
}

class _ReportSheetState extends ConsumerState<ReportSheet> {
  int? _selected;
  bool _submitting = false;
  String? _notice;
  late final TextEditingController _descriptionController;

  static const List<String> _options = [
    'Uygunsuz icerik',
    'Sahte profil',
    'Taciz veya zorbalik',
    'Diger',
  ];

  @override
  void initState() {
    super.initState();
    _descriptionController = TextEditingController();
  }

  @override
  void dispose() {
    _descriptionController.dispose();
    super.dispose();
  }

  Future<void> _submitReport() async {
    if (_submitting || _selected == null) {
      return;
    }

    final targetId = widget.targetId;
    if (targetId == null) {
      setState(() {
        _notice = widget.targetType == ReportTargetType.user
            ? 'Bu kullaniciyi sikayet etmek icin aktif bir sohbet gerekli.'
            : 'Bu mesaji sikayet etmek icin gecerli bir mesaj gerekli.';
      });
      return;
    }

    final token = ref.read(appAuthProvider).asData?.value?.token;
    if (token == null || token.trim().isEmpty) {
      setState(() {
        _notice = 'Bu islemi yapmak icin once giris yapmalisin.';
      });
      return;
    }

    setState(() {
      _submitting = true;
      _notice = null;
    });

    final api = AppAuthApi();
    try {
      await api.submitReport(
        token,
        targetType: widget.targetType == ReportTargetType.user
            ? 'user'
            : 'mesaj',
        targetId: targetId,
        category: _options[_selected!],
        description: _descriptionController.text,
      );
      if (!mounted) {
        return;
      }
      await showCupertinoDialog<void>(
        context: context,
        builder: (dialogContext) => CupertinoAlertDialog(
          title: const Text('Sikayet alindi'),
          content: const Text('Bildiriminiz inceleme ekibine iletildi.'),
          actions: [
            CupertinoDialogAction(
              onPressed: () => Navigator.of(dialogContext).pop(),
              child: const Text('Tamam'),
            ),
          ],
        ),
      );
      if (!mounted) {
        return;
      }
      Navigator.of(context).maybePop();
    } catch (error) {
      if (!mounted) {
        return;
      }
      setState(() {
        _notice = AppAuthErrorFormatter.messageFrom(error);
      });
    } finally {
      api.close();
      if (mounted) {
        setState(() => _submitting = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final objectLabel = widget.targetType == ReportTargetType.user
        ? widget.targetDisplayName
        : 'secilen mesaj';
    final subtitle =
        '$objectLabel icin bir sebep secin. Aciklama alani istege baglidir.';

    return _AdaptiveChatSheet(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 48,
            height: 4,
            decoration: BoxDecoration(
              color: const Color(0xFFD4D4D4),
              borderRadius: BorderRadius.circular(8),
            ),
          ),
          const SizedBox(height: 20),
          const Text(
            'Sikayet Et',
            style: TextStyle(
              fontFamily: AppFont.family,
              fontWeight: FontWeight.w800,
              fontSize: 18,
              color: AppColors.black,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            subtitle,
            textAlign: TextAlign.center,
            style: const TextStyle(
              fontFamily: AppFont.family,
              fontSize: 13,
              color: Color(0xFF999999),
            ),
          ),
          const SizedBox(height: 16),
          ...List.generate(
            _options.length,
            (i) => Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: _ReportOption(
                label: _options[i],
                selected: _selected == i,
                onTap: () => setState(() => _selected = i),
              ),
            ),
          ),
          Container(
            constraints: const BoxConstraints(minHeight: 92),
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: AppColors.grayField,
              borderRadius: BorderRadius.circular(14),
            ),
            child: CupertinoTextField(
              controller: _descriptionController,
              maxLines: 3,
              placeholder: 'Isterseniz kisa bir aciklama ekleyin...',
              placeholderStyle: const TextStyle(
                fontFamily: AppFont.family,
                color: Color(0xFF999999),
                fontSize: 14,
              ),
              style: const TextStyle(
                fontFamily: AppFont.family,
                fontSize: 14,
                color: AppColors.black,
              ),
              decoration: const BoxDecoration(color: Color(0x00000000)),
              padding: EdgeInsets.zero,
            ),
          ),
          if (_notice != null) ...[
            const SizedBox(height: 12),
            Text(
              _notice!,
              textAlign: TextAlign.center,
              style: const TextStyle(
                fontFamily: AppFont.family,
                fontSize: 12,
                height: 1.4,
                color: Color(0xFFEF4444),
              ),
            ),
          ],
          const SizedBox(height: 16),
          PressableScale(
            onTap: _selected == null || _submitting ? null : _submitReport,
            child: Container(
              height: 52,
              decoration: BoxDecoration(
                color: _selected == null || _submitting
                    ? const Color(0xFFEEEEEE)
                    : const Color(0xFFEF4444),
                borderRadius: BorderRadius.circular(26),
              ),
              alignment: Alignment.center,
              child: Text(
                _submitting ? 'Gonderiliyor...' : 'Sikayet Et',
                style: TextStyle(
                  fontFamily: AppFont.family,
                  fontWeight: FontWeight.w700,
                  fontSize: 15,
                  color: _selected == null || _submitting
                      ? const Color(0xFF999999)
                      : AppColors.white,
                ),
              ),
            ),
          ),
          const SizedBox(height: 8),
          PressableScale(
            onTap: () => Navigator.of(context).maybePop(),
            scale: 0.99,
            child: Container(
              height: 44,
              alignment: Alignment.center,
              child: const Text(
                'Vazgec',
                style: TextStyle(
                  fontFamily: AppFont.family,
                  fontWeight: FontWeight.w600,
                  fontSize: 14,
                  color: Color(0xFF666666),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _ReportOption extends StatelessWidget {
  final String label;
  final bool selected;
  final VoidCallback onTap;

  const _ReportOption({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return PressableScale(
      onTap: onTap,
      scale: 0.99,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 160),
        height: 52,
        padding: const EdgeInsets.symmetric(horizontal: 16),
        decoration: BoxDecoration(
          color: AppColors.grayField,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(
            color: selected ? AppColors.indigo : const Color(0x00000000),
            width: 1.5,
          ),
        ),
        child: Row(
          children: [
            Expanded(
              child: Text(
                label,
                style: const TextStyle(
                  fontFamily: AppFont.family,
                  fontWeight: FontWeight.w600,
                  fontSize: 14.5,
                  color: AppColors.black,
                ),
              ),
            ),
            AnimatedSwitcher(
              duration: const Duration(milliseconds: 180),
              transitionBuilder: (child, anim) =>
                  ScaleTransition(scale: anim, child: child),
              child: selected
                  ? Container(
                      key: const ValueKey('on'),
                      width: 22,
                      height: 22,
                      decoration: const BoxDecoration(
                        color: AppColors.indigo,
                        shape: BoxShape.circle,
                      ),
                      alignment: Alignment.center,
                      child: const Icon(
                        CupertinoIcons.check_mark,
                        size: 12,
                        color: AppColors.white,
                      ),
                    )
                  : Container(
                      key: const ValueKey('off'),
                      width: 22,
                      height: 22,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        border: Border.all(
                          color: const Color(0xFFD4D4D4),
                          width: 1.5,
                        ),
                      ),
                    ),
            ),
          ],
        ),
      ),
    );
  }
}

// ------ Sheet: Block Confirm --------------------------------------------------

class BlockConfirmSheet extends ConsumerStatefulWidget {
  final int? targetUserId;
  final String targetDisplayName;
  final bool initiallyBlocked;
  final ValueChanged<bool>? onChanged;

  const BlockConfirmSheet({
    super.key,
    required this.targetUserId,
    required this.targetDisplayName,
    this.initiallyBlocked = false,
    this.onChanged,
  });

  @override
  ConsumerState<BlockConfirmSheet> createState() => _BlockConfirmSheetState();
}

class _BlockConfirmSheetState extends ConsumerState<BlockConfirmSheet> {
  bool _submitting = false;
  String? _notice;

  Future<void> _toggleBlockUser() async {
    final targetUserId = widget.targetUserId;
    if (_submitting) {
      return;
    }
    if (targetUserId == null) {
      Navigator.of(context).maybePop();
      return;
    }

    final token = ref.read(appAuthProvider).asData?.value?.token;
    if (token == null || token.trim().isEmpty) {
      setState(() {
        _notice = 'Bu islemi yapmak icin once giris yapmalisin.';
      });
      return;
    }

    setState(() {
      _submitting = true;
      _notice = null;
    });

    final api = AppAuthApi();
    try {
      if (widget.initiallyBlocked) {
        await api.unblockUser(token, userId: targetUserId);
        widget.onChanged?.call(false);
      } else {
        await api.blockUser(token, userId: targetUserId);
        widget.onChanged?.call(true);
      }
      _chatPeerProfileCache.remove(targetUserId);
      ref.invalidate(chatPeerProfileProvider(targetUserId));
      unawaited(ref.read(appAuthProvider.notifier).refreshCurrentUser());
      unawaited(ref.read(matchProvider.notifier).refreshSummary());
      ref.read(conversationFeedRefreshProvider.notifier).state++;
      if (!mounted) {
        return;
      }
      Navigator.of(context).maybePop();
    } catch (error) {
      if (!mounted) {
        return;
      }
      setState(() {
        _notice = AppAuthErrorFormatter.messageFrom(error);
      });
    } finally {
      api.close();
      if (mounted) {
        setState(() => _submitting = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final canSubmit = widget.targetUserId != null;
    final subtitle =
        _notice ??
        (canSubmit
            ? (widget.initiallyBlocked
                  ? '${widget.targetDisplayName} engeli kaldirilacak.'
                  : '${widget.targetDisplayName} size mesaj gonderemeyecek ve profilinizi goremeyecek.')
            : 'Bu kullaniciyi engellemek icin aktif bir sohbet gerekli.');

    return _AdaptiveChatSheet(
      padding: const EdgeInsets.fromLTRB(24, 12, 24, 24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 48,
            height: 4,
            decoration: BoxDecoration(
              color: const Color(0xFFD4D4D4),
              borderRadius: BorderRadius.circular(8),
            ),
          ),
          const SizedBox(height: 24),
          Container(
            width: 56,
            height: 56,
            decoration: BoxDecoration(
              color: const Color(0x1FEF4444),
              shape: BoxShape.circle,
            ),
            alignment: Alignment.center,
            child: const Icon(
              CupertinoIcons.nosign,
              size: 28,
              color: Color(0xFFEF4444),
            ),
          ),
          const SizedBox(height: 16),
          Text(
            widget.initiallyBlocked
                ? 'Engeli kaldirmak istediginize emin misiniz?'
                : 'Engellemek istediginize emin misiniz?',
            textAlign: TextAlign.center,
            style: const TextStyle(
              fontFamily: AppFont.family,
              fontWeight: FontWeight.w700,
              fontSize: 17,
              color: AppColors.black,
              height: 1.3,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            subtitle,
            textAlign: TextAlign.center,
            style: TextStyle(
              fontFamily: AppFont.family,
              fontSize: 13.5,
              height: 1.5,
              color: _notice == null
                  ? const Color(0xFF666666)
                  : const Color(0xFFEF4444),
            ),
          ),
          const SizedBox(height: 24),
          PressableScale(
            onTap: canSubmit
                ? _toggleBlockUser
                : () => Navigator.of(context).maybePop(),
            child: Container(
              height: 52,
              width: double.infinity,
              decoration: BoxDecoration(
                color: canSubmit
                    ? const Color(0xFFEF4444)
                    : const Color(0xFFBDBDBD),
                borderRadius: BorderRadius.circular(14),
              ),
              alignment: Alignment.center,
              child: Text(
                _submitting
                    ? (widget.initiallyBlocked
                          ? 'Kaldiriliyor...'
                          : 'Engelleniyor...')
                    : (canSubmit
                          ? (widget.initiallyBlocked
                                ? 'Engelden Cikar'
                                : 'Engelle')
                          : 'Tamam'),
                style: TextStyle(
                  fontFamily: AppFont.family,
                  fontWeight: FontWeight.w700,
                  fontSize: 15,
                  color: AppColors.white,
                ),
              ),
            ),
          ),
          const SizedBox(height: 8),
          PressableScale(
            onTap: () => Navigator.of(context).maybePop(),
            scale: 0.99,
            child: Container(
              height: 52,
              width: double.infinity,
              decoration: BoxDecoration(
                color: AppColors.grayField,
                borderRadius: BorderRadius.circular(14),
              ),
              alignment: Alignment.center,
              child: const Text(
                'Vazgec',
                style: TextStyle(
                  fontFamily: AppFont.family,
                  fontWeight: FontWeight.w600,
                  fontSize: 15,
                  color: AppColors.black,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

// =============================================================================
// Notifications module ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â 2 varyasyon (empty / dolu)
