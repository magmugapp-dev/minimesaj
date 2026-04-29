import 'dart:async';
import 'dart:io';
import 'dart:math' as math;

import 'package:flutter/services.dart';
import 'package:image_picker/image_picker.dart';
import 'package:just_audio/just_audio.dart';
import 'package:magmug/app_core.dart';
import 'package:magmug/core/ai/flutter_ai_turn_processor.dart';
import 'package:magmug/features/chat/chat_local_store.dart';
import 'package:magmug/features/chat/chat_realtime.dart';
import 'package:magmug/features/chat/chat_translation_policy.dart';
import 'package:magmug/features/match/match_flow.dart';
import 'package:magmug/features/profile/widgets/profile_overview_widgets.dart'
    show openProfileMediaViewer;
import 'package:path/path.dart' as path;
import 'package:path_provider/path_provider.dart';
import 'package:record/record.dart';

const Duration _conversationReadReceiptCooldown = Duration(seconds: 5);
const int _conversationMessagePageSize = 50;
final Map<int, DateTime> _lastConversationReadMarkedAt = <int, DateTime>{};
final Set<int> _conversationReadMarkInFlight = <int>{};
final Map<int, AppMatchCandidate> _chatPeerProfileCache =
    <int, AppMatchCandidate>{};
final Map<int, int> _chatThemeSelectionCache = <int, int>{};
int _clientMessageSequence = 0;
bool _chatRuntimeCacheCleanerRegistered = false;

void clearChatRuntimeCaches() {
  _lastConversationReadMarkedAt.clear();
  _conversationReadMarkInFlight.clear();
  _chatPeerProfileCache.clear();
  _chatThemeSelectionCache.clear();
}

void _ensureChatRuntimeCacheCleanerRegistered() {
  if (_chatRuntimeCacheCleanerRegistered) {
    return;
  }
  AppRuntimeCacheRegistry.register(clearChatRuntimeCaches);
  _chatRuntimeCacheCleanerRegistered = true;
}

String _newClientMessageId() {
  final sequence = _clientMessageSequence++;
  return 'msg-${DateTime.now().microsecondsSinceEpoch}-$sequence';
}

// =============================================================================

enum ChatBubbleSide { me, them }

enum ChatMessageType { text, image, audio, typing }

enum ReportTargetType { user, message }

ChatPeer get _emptyChatPeer => ChatPeer(
  name: AppRuntimeText.instance.t('chat.title.default', 'Sohbet'),
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
  final String deliveryStatus;

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
    this.deliveryStatus = 'sent',
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
      deliveryStatus = 'sent',
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
        (conversation.online
            ? AppRuntimeText.instance.t('chat.status.online', 'Cevrimici')
            : AppRuntimeText.instance.t('chat.status.inactive', 'Aktif degil'));
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
    status: candidate.online
        ? AppRuntimeText.instance.t('chat.status.online', 'Cevrimici')
        : AppRuntimeText.instance.t('chat.status.inactive', 'Aktif degil'),
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
    status: AppRuntimeText.instance.t('chat.status.profile', 'Profil'),
    avatarUrl: sender.profileImageUrl,
    online: false,
  );
}

final conversationMessagesProvider =
    FutureProvider.family<List<AppConversationMessage>, int>((
      ref,
      conversationId,
    ) async {
      _ensureChatRuntimeCacheCleanerRegistered();
      final session = await ref.watch(appAuthProvider.future);
      final token = session?.token;
      final userId = session?.user?.id;
      if (token == null || token.trim().isEmpty || userId == null) {
        return const [];
      }

      final localStore = ChatLocalStore.instance;
      final cachedMessages = await localStore.getConversationMessages(
        conversationId,
        ownerUserId: userId,
        limit: _conversationMessagePageSize,
      );
      if (cachedMessages.isNotEmpty) {
        unawaited(
          _markConversationReadLocalAndRemote(
            ref,
            token: token,
            userId: userId,
            conversationId: conversationId,
          ),
        );
        return cachedMessages;
      }

      final api = AppAuthApi();
      try {
        final page = await api.fetchMobileConversationMessages(
          token,
          conversationId: conversationId,
          limit: _conversationMessagePageSize,
        );
        await localStore.upsertConversationMessages(
          page.messages,
          ownerUserId: userId,
        );
        unawaited(
          _markConversationReadLocalAndRemote(
            ref,
            token: token,
            userId: userId,
            conversationId: conversationId,
          ),
        );
        return page.messages;
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
      _ensureChatRuntimeCacheCleanerRegistered();
      ref.keepAlive();
      final cached = _chatPeerProfileCache[userId];
      if (cached != null) {
        return cached;
      }

      final session = await ref.watch(appAuthProvider.future);
      final token = session?.token;
      if (token == null || token.trim().isEmpty) {
        throw ApiException(
          AppRuntimeText.instance.t(
            'chat.profile.error.login_required',
            'Bu profili gormek icin once giris yapmalisin.',
          ),
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

final chatGiftsProvider = FutureProvider<List<AppGift>>((ref) async {
  final session = await ref.watch(appAuthProvider.future);
  final token = session?.token;
  final ownerUserId = session?.user?.id;
  if (token == null || token.trim().isEmpty || ownerUserId == null) {
    return const [];
  }

  return AppRepository.instance.gifts(token: token, ownerUserId: ownerUserId);
});

@immutable
class _ChatThemePalette {
  final String labelKey;
  final String fallbackLabel;
  final Color background;
  final Color incomingBubble;
  final Color incomingText;
  final LinearGradient outgoingGradient;

  const _ChatThemePalette({
    required this.labelKey,
    required this.fallbackLabel,
    required this.background,
    required this.incomingBubble,
    required this.incomingText,
    required this.outgoingGradient,
  });

  String get label => AppRuntimeText.instance.t(labelKey, fallbackLabel);
}

const List<_ChatThemePalette> _chatThemes = [
  _ChatThemePalette(
    labelKey: 'chat.theme.default',
    fallbackLabel: 'Varsayilan',
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
    labelKey: 'chat.theme.night',
    fallbackLabel: 'Gece',
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
    labelKey: 'chat.theme.sunset',
    fallbackLabel: 'Gunbatimi',
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
    labelKey: 'chat.theme.forest',
    fallbackLabel: 'Orman',
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
    labelKey: 'chat.theme.cotton',
    fallbackLabel: 'Pamuk',
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
    labelKey: 'chat.theme.sea',
    fallbackLabel: 'Deniz',
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

  final box = await AppHiveBoxes.preferences();
  final storedRaw = box.get(_chatThemePreferenceKey(peerId));
  final stored = storedRaw is num ? storedRaw.toInt() : null;
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
  final box = await AppHiveBoxes.preferences();
  await box.put(_chatThemePreferenceKey(peerId), index);
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

bool _claimConversationReadMark(int conversationId) {
  if (_conversationReadMarkInFlight.contains(conversationId)) {
    return false;
  }

  final lastMarkedAt = _lastConversationReadMarkedAt[conversationId];
  final now = DateTime.now();
  if (lastMarkedAt != null &&
      now.difference(lastMarkedAt) < _conversationReadReceiptCooldown) {
    return false;
  }

  _conversationReadMarkInFlight.add(conversationId);
  _lastConversationReadMarkedAt[conversationId] = now;
  return true;
}

Future<void> _markConversationReadLocalAndRemote(
  dynamic ref, {
  required String token,
  required int userId,
  required int conversationId,
}) async {
  if (!_claimConversationReadMark(conversationId)) {
    return;
  }

  try {
    await ChatLocalStore.instance.applyConversationReadEvent(
      conversationId,
      ownerUserId: userId,
      readerUserId: userId,
      currentUserId: userId,
    );
    ref.read(conversationFeedRefreshProvider.notifier).state++;

    final api = AppAuthApi();
    try {
      await api.markConversationRead(token, conversationId: conversationId);
    } catch (_) {
      // Local read state keeps the UI correct even if the receipt is retried later.
    } finally {
      api.close();
    }
  } finally {
    _conversationReadMarkInFlight.remove(conversationId);
  }
}

int? _oldestServerMessageId(List<AppConversationMessage> messages) {
  for (final message in messages) {
    if (message.id > 0) {
      return message.id;
    }
  }
  return null;
}

List<AppConversationMessage> _mergeConversationMessages(
  Iterable<AppConversationMessage> left,
  Iterable<AppConversationMessage> right,
) {
  final merged = <String, AppConversationMessage>{};

  for (final message in left) {
    merged[_conversationMessageMergeKey(message)] = message;
  }
  for (final message in right) {
    final key = _conversationMessageMergeKey(message);
    final existing = merged[key];
    merged[key] = existing == null
        ? message
        : _preferredConversationMessage(existing, message);
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

String _conversationMessageMergeKey(AppConversationMessage message) {
  final clientMessageId = message.clientMessageId?.trim();
  if (clientMessageId != null && clientMessageId.isNotEmpty) {
    return 'client:$clientMessageId';
  }

  if (message.id > 0) {
    return 'server:${message.id}';
  }
  if (message.id < 0) {
    return 'local:${message.id}';
  }

  final createdAtMs = message.createdAt?.millisecondsSinceEpoch ?? 0;
  return [
    'fallback',
    message.conversationId,
    message.senderId ?? '',
    message.type,
    message.text ?? '',
    message.fileUrl ?? '',
    createdAtMs,
  ].join(':');
}

AppConversationMessage _preferredConversationMessage(
  AppConversationMessage existing,
  AppConversationMessage incoming,
) {
  final existingIsServerMessage = existing.id > 0;
  final incomingIsServerMessage = incoming.id > 0;
  if (incomingIsServerMessage && !existingIsServerMessage) {
    return incoming;
  }
  if (existingIsServerMessage && !incomingIsServerMessage) {
    return existing;
  }

  return incoming;
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
        child: CachedAppImage(
          imageUrl: avatarUrl,
          width: size,
          height: size,
          cacheWidth: (size * 2).round(),
          cacheHeight: (size * 2).round(),
          errorBuilder: (_) {
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
  final Map<String, String>? mediaHttpHeaders;
  final VoidCallback? onReport;
  final bool showTranslateAction;
  final VoidCallback? onTranslate;
  final VoidCallback? onRetry;

  const _MessageBubble({
    super.key,
    required this.message,
    required this.peer,
    required this.theme,
    this.mediaHttpHeaders,
    this.onReport,
    this.showTranslateAction = false,
    this.onTranslate,
    this.onRetry,
  });

  @override
  Widget build(BuildContext context) {
    final isMe = message.side == ChatBubbleSide.me;
    final deliveryLabel = _deliveryStatusLabel(message.deliveryStatus);
    final canRetry = message.deliveryStatus == 'failed' && onRetry != null;

    Widget content;
    switch (message.type) {
      case ChatMessageType.typing:
        content = const _TypingBubble();
      case ChatMessageType.image:
        content = _ImageBubble(
          asset: message.asset!,
          httpHeaders: mediaHttpHeaders,
        );
      case ChatMessageType.audio:
        content = _AudioBubble(
          isMe: isMe,
          duration: message.duration ?? const Duration(),
          source: message.asset,
          httpHeaders: mediaHttpHeaders,
        );
      case ChatMessageType.text:
        content = _TextBubble(
          isMe: isMe,
          text: message.text ?? '',
          translatedText: message.translatedText,
          translationLanguageName: message.translationLanguageName,
          showTranslateAction: showTranslateAction,
          onTranslate: onTranslate,
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
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        message.time,
                        style: const TextStyle(
                          fontFamily: AppFont.family,
                          fontSize: 10,
                          color: Color(0xFFCCCCCC),
                        ),
                      ),
                      if (deliveryLabel != null) ...[
                        const SizedBox(width: 6),
                        canRetry
                            ? PressableScale(
                                onTap: onRetry,
                                scale: 0.96,
                                child: Row(
                                  mainAxisSize: MainAxisSize.min,
                                  children: [
                                    const Icon(
                                      CupertinoIcons.arrow_clockwise,
                                      size: 11,
                                      color: Color(0xFFEF4444),
                                    ),
                                    const SizedBox(width: 3),
                                    Text(
                                      deliveryLabel,
                                      style: const TextStyle(
                                        fontFamily: AppFont.family,
                                        fontSize: 10,
                                        fontWeight: FontWeight.w600,
                                        color: Color(0xFFEF4444),
                                      ),
                                    ),
                                  ],
                                ),
                              )
                            : Text(
                                deliveryLabel,
                                style: const TextStyle(
                                  fontFamily: AppFont.family,
                                  fontSize: 10,
                                  color: Color(0xFF999999),
                                ),
                              ),
                      ],
                    ],
                  ),
                ),
              ],
            ],
          ),
        ),
      ],
    );

    return RepaintBoundary(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 6, 16, 6),
        child: onReport == null
            ? bubble
            : GestureDetector(
                behavior: HitTestBehavior.opaque,
                onLongPress: onReport,
                child: bubble,
              ),
      ),
    );
  }
}

String? _deliveryStatusLabel(String status) {
  return switch (status) {
    'queued' => AppRuntimeText.instance.t('chat.delivery.queued', 'Sirada'),
    'sending' => AppRuntimeText.instance.t(
      'chat.delivery.sending',
      'Gonderiliyor',
    ),
    'failed' => AppRuntimeText.instance.t(
      'chat.delivery.failed',
      'Tekrar dene',
    ),
    _ => null,
  };
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
}) {
  showCupertinoModalPopup<void>(
    context: context,
    builder: (sheetContext) => CupertinoActionSheet(
      title: Text(
        peer.name,
        style: const TextStyle(fontFamily: AppFont.family),
      ),
      message: Text(
        AppRuntimeText.instance.t(
          'chat.message.action_sheet.subtitle',
          'Bu mesajla ilgili islem secin.',
        ),
        style: const TextStyle(fontFamily: AppFont.family),
      ),
      actions: [
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
          child: Text(
            AppRuntimeText.instance.t(
              'chat.message.action.report',
              'Bu Mesaji Sikayet Et',
            ),
            style: const TextStyle(fontFamily: AppFont.family),
          ),
        ),
      ],
      cancelButton: CupertinoActionSheetAction(
        onPressed: () => Navigator.of(sheetContext).pop(),
        child: Text(
          AppRuntimeText.instance.t('commonCancel', 'Vazgec'),
          style: const TextStyle(fontFamily: AppFont.family),
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
  final bool showTranslateAction;
  final VoidCallback? onTranslate;
  final _ChatThemePalette theme;

  const _TextBubble({
    required this.isMe,
    required this.text,
    this.translatedText,
    this.translationLanguageName,
    this.showTranslateAction = false,
    this.onTranslate,
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
          if (!isMe && showTranslateAction && onTranslate != null) ...[
            Align(
              alignment: Alignment.topRight,
              child: GestureDetector(
                onTap: onTranslate,
                behavior: HitTestBehavior.opaque,
                child: Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 8,
                    vertical: 4,
                  ),
                  decoration: BoxDecoration(
                    color: textColor.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(999),
                    border: Border.all(
                      color: textColor.withValues(alpha: 0.18),
                    ),
                  ),
                  child: Text(
                    translation == null ? 'Cevir' : 'Yenile',
                    style: TextStyle(
                      fontFamily: AppFont.family,
                      fontWeight: FontWeight.w700,
                      fontSize: 10.5,
                      color: textColor,
                    ),
                  ),
                ),
              ),
            ),
            const SizedBox(height: 8),
          ],
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
            Container(height: 1, color: textColor.withValues(alpha: 0.14)),
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
  final Map<String, String>? httpHeaders;

  const _ImageBubble({required this.asset, this.httpHeaders});

  @override
  Widget build(BuildContext context) {
    final imageWidget = _chatImageWidget(
      asset,
      fit: BoxFit.cover,
      httpHeaders: httpHeaders,
      errorBuilder: (_) => _imagePlaceholder(),
    );

    return PressableScale(
      onTap: () => Navigator.of(context, rootNavigator: true).push(
        PageRouteBuilder<void>(
          opaque: true,
          fullscreenDialog: true,
          transitionDuration: const Duration(milliseconds: 180),
          reverseTransitionDuration: const Duration(milliseconds: 140),
          pageBuilder: (_, animation, _) => FadeTransition(
            opacity: animation,
            child: _ChatImageViewerScreen(
              asset: asset,
              httpHeaders: httpHeaders,
            ),
          ),
        ),
      ),
      scale: 0.98,
      child: ClipRRect(
        borderRadius: BorderRadius.circular(20),
        child: SizedBox(width: 198, height: 220, child: imageWidget),
      ),
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

class _ChatImageViewerScreen extends StatelessWidget {
  final String asset;
  final Map<String, String>? httpHeaders;

  const _ChatImageViewerScreen({required this.asset, this.httpHeaders});

  @override
  Widget build(BuildContext context) {
    return CupertinoPageScaffold(
      backgroundColor: AppColors.black,
      child: Stack(
        key: const ValueKey('chat-image-viewer'),
        children: [
          Positioned.fill(
            child: SafeArea(
              child: LayoutBuilder(
                builder: (context, constraints) {
                  return InteractiveViewer(
                    minScale: 1,
                    maxScale: 4,
                    child: SizedBox(
                      width: constraints.maxWidth,
                      height: constraints.maxHeight,
                      child: _chatImageWidget(
                        asset,
                        fit: BoxFit.contain,
                        httpHeaders: httpHeaders,
                        errorBuilder: (_) => const _ChatImageViewerFallback(),
                      ),
                    ),
                  );
                },
              ),
            ),
          ),
          SafeArea(
            child: Align(
              alignment: Alignment.topLeft,
              child: Padding(
                padding: const EdgeInsets.fromLTRB(12, 10, 12, 0),
                child: PressableScale(
                  onTap: () =>
                      Navigator.of(context, rootNavigator: true).maybePop(),
                  scale: 0.9,
                  child: Container(
                    width: 40,
                    height: 40,
                    decoration: BoxDecoration(
                      color: const Color(0x8A000000),
                      borderRadius: BorderRadius.circular(20),
                    ),
                    alignment: Alignment.center,
                    child: const Icon(
                      CupertinoIcons.xmark,
                      size: 18,
                      color: AppColors.white,
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
}

class _ChatImageViewerFallback extends StatelessWidget {
  const _ChatImageViewerFallback();

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        const Icon(CupertinoIcons.photo, size: 30, color: AppColors.white),
        const SizedBox(height: 10),
        Text(
          AppRuntimeText.instance.t(
            'chat.image.error.open_failed',
            'Fotograf acilamadi.',
          ),
          style: const TextStyle(
            fontFamily: AppFont.family,
            fontWeight: FontWeight.w700,
            fontSize: 13,
            color: AppColors.white,
          ),
        ),
      ],
    );
  }
}

Widget _chatImageWidget(
  String source, {
  required BoxFit fit,
  Map<String, String>? httpHeaders,
  WidgetBuilder? errorBuilder,
}) {
  final normalized = source.trim();
  final mediaSource = AppMediaSource.resolve(normalized);
  if (mediaSource.isFile) {
    return Image.file(
      File(mediaSource.value),
      fit: fit,
      gaplessPlayback: true,
      errorBuilder: (context, _, _) =>
          errorBuilder?.call(context) ??
          const ColoredBox(color: AppColors.grayField),
    );
  }

  return CachedAppImage(
    imageUrl: normalized,
    fit: fit,
    httpHeaders: httpHeaders,
    errorBuilder: errorBuilder,
  );
}

class _AudioBubble extends StatefulWidget {
  final bool isMe;
  final Duration duration;
  final String? source;
  final Map<String, String>? httpHeaders;

  const _AudioBubble({
    required this.isMe,
    required this.duration,
    required this.source,
    this.httpHeaders,
  });

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
  State<_AudioBubble> createState() => _AudioBubbleState();
}

class _AudioBubbleState extends State<_AudioBubble> {
  late final AudioPlayer _player;
  StreamSubscription<Duration>? _positionSubscription;
  StreamSubscription<Duration?>? _durationSubscription;
  StreamSubscription<PlayerState>? _stateSubscription;
  Duration _position = Duration.zero;
  Duration? _resolvedDuration;
  bool _sourceLoaded = false;
  bool _playing = false;
  bool _loading = false;

  @override
  void initState() {
    super.initState();
    _player = AudioPlayer();
    _positionSubscription = _player.positionStream.listen((position) {
      if (!mounted) {
        return;
      }
      setState(() => _position = position);
    });
    _durationSubscription = _player.durationStream.listen((duration) {
      if (!mounted || duration == null) {
        return;
      }
      setState(() => _resolvedDuration = duration);
    });
    _stateSubscription = _player.playerStateStream.listen((state) {
      if (!mounted) {
        return;
      }
      final completed = state.processingState == ProcessingState.completed;
      setState(() {
        _playing = state.playing && !completed;
        _loading =
            state.processingState == ProcessingState.loading ||
            state.processingState == ProcessingState.buffering;
        if (completed) {
          _position = Duration.zero;
          _sourceLoaded = false;
        }
      });
      if (completed) {
        unawaited(_player.stop());
      }
    });
  }

  @override
  void didUpdateWidget(covariant _AudioBubble oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.source != widget.source) {
      _sourceLoaded = false;
      _position = Duration.zero;
      _resolvedDuration = null;
      unawaited(_player.stop());
    }
  }

  @override
  void dispose() {
    unawaited(_positionSubscription?.cancel());
    unawaited(_durationSubscription?.cancel());
    unawaited(_stateSubscription?.cancel());
    unawaited(_player.dispose());
    super.dispose();
  }

  Future<void> _togglePlayback() async {
    final source = widget.source?.trim();
    if (source == null || source.isEmpty || _loading) {
      return;
    }

    if (_playing) {
      await _player.pause();
      return;
    }

    try {
      if (!_sourceLoaded) {
        await _loadSource(source);
        _sourceLoaded = true;
      }
      await _player.play();
    } catch (_) {
      if (!mounted) {
        return;
      }
      setState(() {
        _sourceLoaded = false;
        _loading = false;
        _playing = false;
      });
    }
  }

  Future<void> _loadSource(String source) {
    final mediaSource = AppMediaSource.resolve(source);
    if (mediaSource.isRemote) {
      return _player.setUrl(source, headers: widget.httpHeaders).then((_) {});
    }

    if (mediaSource.isFile) {
      return _player.setFilePath(mediaSource.value).then((_) {});
    }

    return _player.setUrl(source).then((_) {});
  }

  @override
  Widget build(BuildContext context) {
    final isMe = widget.isMe;
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
    final displayDuration = _resolvedDuration ?? widget.duration;
    final displayPosition = _position > Duration.zero
        ? _position
        : displayDuration;
    final progress = displayDuration.inMilliseconds <= 0
        ? 0.0
        : (_position.inMilliseconds / displayDuration.inMilliseconds).clamp(
            0.0,
            1.0,
          );
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
          PressableScale(
            onTap: _togglePlayback,
            scale: 0.92,
            child: Container(
              width: 34,
              height: 34,
              decoration: BoxDecoration(
                color: playBg,
                borderRadius: BorderRadius.circular(17),
              ),
              alignment: Alignment.center,
              child: _loading
                  ? const CupertinoActivityIndicator(radius: 8)
                  : Icon(
                      _playing
                          ? CupertinoIcons.pause_fill
                          : CupertinoIcons.play_fill,
                      size: 14,
                      color: AppColors.white,
                    ),
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: CustomPaint(
              size: const Size.fromHeight(22),
              painter: _WaveformPainter(
                bars: _AudioBubble._bars,
                color: barColor,
                playedColor: isMe ? AppColors.white : AppColors.onlineGreen,
                progress: progress,
              ),
            ),
          ),
          const SizedBox(width: 8),
          Text(
            _formatDuration(displayPosition),
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

  String _formatDuration(Duration value) {
    final minutes = value.inMinutes.remainder(60).toString().padLeft(1, '0');
    final seconds = value.inSeconds.remainder(60).toString().padLeft(2, '0');
    return '$minutes:$seconds';
  }
}

class _WaveformPainter extends CustomPainter {
  final List<double> bars;
  final Color color;
  final Color? playedColor;
  final double progress;

  _WaveformPainter({
    required this.bars,
    required this.color,
    this.playedColor,
    this.progress = 0,
  });

  @override
  void paint(Canvas canvas, Size size) {
    final basePaint = Paint()
      ..color = color
      ..strokeCap = StrokeCap.round
      ..strokeWidth = 2.5;
    final activePaint = Paint()
      ..color = playedColor ?? color
      ..strokeCap = StrokeCap.round
      ..strokeWidth = 2.5;
    final gap = size.width / bars.length;
    final midY = size.height / 2;
    final activeX = size.width * progress.clamp(0.0, 1.0);
    for (var i = 0; i < bars.length; i++) {
      final h = bars[i] * size.height;
      final x = i * gap + gap / 2;
      canvas.drawLine(
        Offset(x, midY - h / 2),
        Offset(x, midY + h / 2),
        x <= activeX ? activePaint : basePaint,
      );
    }
  }

  @override
  bool shouldRepaint(covariant _WaveformPainter old) =>
      old.color != color ||
      old.playedColor != playedColor ||
      old.progress != progress ||
      old.bars != bars;
}

class _AnimatedWaveformPainter extends CustomPainter {
  final double animValue;
  final Color color;
  static const int _count = 20;

  _AnimatedWaveformPainter({required this.animValue, required this.color});

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = color
      ..strokeCap = StrokeCap.round
      ..strokeWidth = 2.5;
    final gap = size.width / _count;
    final mid = size.height / 2;
    for (var i = 0; i < _count; i++) {
      final phase = i * 0.45;
      final freq = 1.0 + (i % 3) * 0.7;
      final h =
          (0.15 +
              0.80 *
                  ((math.sin(animValue * math.pi * 2 * freq + phase) + 1) /
                      2)) *
          size.height;
      final x = i * gap + gap / 2;
      canvas.drawLine(Offset(x, mid - h / 2), Offset(x, mid + h / 2), paint);
    }
  }

  @override
  bool shouldRepaint(_AnimatedWaveformPainter old) =>
      old.animValue != animValue || old.color != color;
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

class _ChatInputDock extends StatelessWidget {
  final _ChatThemePalette theme;
  final Widget child;

  const _ChatInputDock({required this.theme, required this.child});

  @override
  Widget build(BuildContext context) {
    final safeBottom = MediaQuery.viewPaddingOf(context).bottom;

    return ColoredBox(
      key: const ValueKey('chat-input-dock'),
      color: theme.incomingBubble,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          child,
          SizedBox(height: safeBottom),
        ],
      ),
    );
  }
}

class _ChatInputBar extends StatelessWidget {
  static const double _composerControlHeight = 44;

  final ChatInputVariant variant;
  final _ChatThemePalette theme;
  final TextEditingController? controller;
  final FocusNode? focusNode;
  final VoidCallback? onSend;
  final VoidCallback? onLeadingTap;
  final VoidCallback? onMicTap;
  final VoidCallback? onVoiceCancel;
  final VoidCallback? onVoiceSend;
  final bool canSend;
  final bool isSending;
  final bool isVoiceRecording;
  final bool isVoiceStarting;
  final bool isVoiceSending;
  final Duration voiceElapsed;
  final String? errorText;
  final AnimationController? recordingWaveController;

  const _ChatInputBar({
    this.variant = ChatInputVariant.empty,
    required this.theme,
    this.controller,
    this.focusNode,
    this.onSend,
    this.onLeadingTap,
    this.onMicTap,
    this.onVoiceCancel,
    this.onVoiceSend,
    this.canSend = false,
    this.isSending = false,
    this.isVoiceRecording = false,
    this.isVoiceStarting = false,
    this.isVoiceSending = false,
    this.voiceElapsed = Duration.zero,
    this.errorText,
    this.recordingWaveController,
  });

  @override
  Widget build(BuildContext context) {
    final hasComposer = controller != null;
    final canUseSend = hasComposer && canSend;
    final barColor = theme.incomingBubble;
    final fieldColor = theme.background;
    final fieldTextColor = _readableColorOn(fieldColor);
    final placeholderColor = _mutedReadableColorOn(fieldColor);
    final actionColor = theme.outgoingGradient.colors.first;
    final actionIconColor = _readableColorOn(actionColor);

    return Container(
      key: const ValueKey('chat-input-bar'),
      constraints: const BoxConstraints(minHeight: 62),
      padding: const EdgeInsets.fromLTRB(8, 8, 8, 10),
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
          AnimatedSize(
            duration: const Duration(milliseconds: 160),
            curve: Curves.easeOutCubic,
            alignment: Alignment.bottomCenter,
            child: AnimatedSwitcher(
              duration: const Duration(milliseconds: 160),
              switchInCurve: Curves.easeOutCubic,
              switchOutCurve: Curves.easeInCubic,
              child: isVoiceRecording
                  ? _voiceRecordingBar(
                      sending: isVoiceSending,
                      starting: isVoiceStarting,
                      elapsed: voiceElapsed,
                      onCancel: onVoiceCancel,
                      onSend: onVoiceSend,
                    )
                  : Row(
                      key: const ValueKey('chat-input-row'),
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        _circleButton(
                          key: const ValueKey('chat-input-leading'),
                          icon: CupertinoIcons.camera_fill,
                          bg: barColor,
                          iconColor: _mutedReadableColorOn(barColor),
                          onTap: onLeadingTap,
                        ),
                        const SizedBox(width: 6),
                        Expanded(
                          child: hasComposer
                              ? ConstrainedBox(
                                  constraints: const BoxConstraints(
                                    minHeight: _composerControlHeight,
                                  ),
                                  child: CupertinoTextField(
                                    controller: controller,
                                    focusNode: focusNode,
                                    placeholder: AppRuntimeText.instance.t(
                                      'chatComposerPlaceholder',
                                      'Mesaj yaz...',
                                    ),
                                    minLines: 1,
                                    maxLines: 4,
                                    keyboardType: TextInputType.multiline,
                                    textInputAction: TextInputAction.newline,
                                    textCapitalization:
                                        TextCapitalization.sentences,
                                    padding: const EdgeInsets.symmetric(
                                      horizontal: 16,
                                      vertical: 11,
                                    ),
                                    decoration: BoxDecoration(
                                      color: fieldColor,
                                      borderRadius: BorderRadius.circular(24),
                                    ),
                                    style: TextStyle(
                                      fontFamily: AppFont.family,
                                      fontWeight: FontWeight.w500,
                                      fontSize: 14.5,
                                      height: 1.25,
                                      color: fieldTextColor,
                                    ),
                                    placeholderStyle: TextStyle(
                                      fontFamily: AppFont.family,
                                      fontWeight: FontWeight.w500,
                                      fontSize: 14.5,
                                      height: 1.25,
                                      color: placeholderColor,
                                    ),
                                  ),
                                )
                              : Container(
                                  constraints: const BoxConstraints(
                                    minHeight: _composerControlHeight,
                                  ),
                                  padding: const EdgeInsets.symmetric(
                                    horizontal: 16,
                                  ),
                                  decoration: BoxDecoration(
                                    color: fieldColor,
                                    borderRadius: BorderRadius.circular(21),
                                  ),
                                  child: Row(
                                    children: [
                                      Expanded(
                                        child: Text(
                                          'Mesaj yaz...',
                                          style: TextStyle(
                                            fontFamily: AppFont.family,
                                            fontWeight: FontWeight.w500,
                                            fontSize: 14.5,
                                            height: 1.25,
                                            color: placeholderColor,
                                          ),
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                        ),
                        const SizedBox(width: 6),
                        canUseSend
                            ? _circleButton(
                                key: const ValueKey('chat-input-send'),
                                icon: CupertinoIcons.arrow_up,
                                bg: AppColors.onlineGreen,
                                iconColor: AppColors.white,
                                onTap: !isSending ? onSend : null,
                                child: isSending
                                    ? const CupertinoActivityIndicator(
                                        radius: 9,
                                      )
                                    : null,
                              )
                            : _micButton(
                                key: const ValueKey('chat-input-mic'),
                                bg: actionColor,
                                iconColor: actionIconColor,
                                enabled: hasComposer,
                              ),
                      ],
                    ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _circleButton({
    Key? key,
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
        key: key,
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

  Widget _micButton({
    Key? key,
    required Color bg,
    required Color iconColor,
    required bool enabled,
  }) {
    return GestureDetector(
      key: key,
      behavior: HitTestBehavior.opaque,
      onTap: enabled ? onMicTap : null,
      child: Container(
        width: 44,
        height: 44,
        decoration: BoxDecoration(
          color: bg,
          borderRadius: BorderRadius.circular(22),
        ),
        alignment: Alignment.center,
        child: Icon(CupertinoIcons.mic_fill, size: 19, color: iconColor),
      ),
    );
  }

  Widget _voiceRecordingBar({
    required bool sending,
    required bool starting,
    required Duration elapsed,
    VoidCallback? onCancel,
    VoidCallback? onSend,
  }) {
    const accent = AppColors.coral;
    final minutes = elapsed.inMinutes.remainder(60).toString().padLeft(1, '0');
    final seconds = elapsed.inSeconds.remainder(60).toString().padLeft(2, '0');

    return Row(
      key: const ValueKey('chat-voice-recorder'),
      children: [
        _circleButton(
          key: const ValueKey('chat-voice-cancel'),
          icon: CupertinoIcons.delete,
          bg: AppColors.grayField,
          iconColor: AppColors.black,
          onTap: sending ? null : onCancel,
        ),
        const SizedBox(width: 6),
        Expanded(
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 160),
            height: 44,
            padding: const EdgeInsets.symmetric(horizontal: 12),
            decoration: BoxDecoration(
              color: AppColors.grayField,
              borderRadius: BorderRadius.circular(22),
            ),
            child: Row(
              children: [
                TweenAnimationBuilder<double>(
                  key: ValueKey('voice-pulse-${elapsed.inSeconds}'),
                  tween: Tween<double>(begin: 0.72, end: 1),
                  duration: const Duration(milliseconds: 520),
                  curve: Curves.easeInOut,
                  builder: (context, scale, child) {
                    return Transform.scale(scale: scale, child: child);
                  },
                  child: Icon(CupertinoIcons.mic_fill, size: 18, color: accent),
                ),
                const SizedBox(width: 8),
                Text(
                  '$minutes:$seconds',
                  style: const TextStyle(
                    fontFamily: AppFont.family,
                    fontWeight: FontWeight.w800,
                    fontSize: 13,
                    color: AppColors.black,
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: recordingWaveController != null
                      ? AnimatedBuilder(
                          animation: recordingWaveController!,
                          builder: (_, _) => CustomPaint(
                            size: const Size.fromHeight(18),
                            painter: _AnimatedWaveformPainter(
                              animValue: recordingWaveController!.value,
                              color: accent.withValues(alpha: 0.78),
                            ),
                          ),
                        )
                      : CustomPaint(
                          size: const Size.fromHeight(18),
                          painter: _WaveformPainter(
                            bars: _AudioBubble._bars,
                            color: accent.withValues(alpha: 0.78),
                          ),
                        ),
                ),
                const SizedBox(width: 10),
                const Text(
                  'Kaydediliyor',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    fontFamily: AppFont.family,
                    fontWeight: FontWeight.w700,
                    fontSize: 11,
                    color: AppColors.neutral600,
                  ),
                ),
              ],
            ),
          ),
        ),
        const SizedBox(width: 6),
        _circleButton(
          key: const ValueKey('chat-voice-send'),
          icon: CupertinoIcons.paperplane_fill,
          bg: AppColors.onlineGreen,
          iconColor: AppColors.white,
          onTap: sending || starting ? null : onSend,
          child: sending || starting
              ? const CupertinoActivityIndicator(radius: 9)
              : null,
        ),
      ],
    );
  }
}

Widget buildChatInputBarForTest({
  TextEditingController? controller,
  required bool canSend,
  VoidCallback? onSend,
  VoidCallback? onLeadingTap,
  VoidCallback? onMicTap,
  VoidCallback? onVoiceCancel,
  VoidCallback? onVoiceSend,
  bool isVoiceRecording = false,
  bool isVoiceStarting = false,
  bool isVoiceSending = false,
  Duration voiceElapsed = Duration.zero,
}) {
  return _ChatInputBar(
    variant: ChatInputVariant.full,
    theme: _chatThemes.first,
    controller: controller,
    onSend: onSend,
    onLeadingTap: onLeadingTap,
    onMicTap: onMicTap,
    onVoiceCancel: onVoiceCancel,
    onVoiceSend: onVoiceSend,
    canSend: canSend,
    isVoiceRecording: isVoiceRecording,
    isVoiceStarting: isVoiceStarting,
    isVoiceSending: isVoiceSending,
    voiceElapsed: voiceElapsed,
  );
}

Widget buildPassiveChatInputBarForTest() {
  return _ChatInputBar(
    variant: ChatInputVariant.empty,
    theme: _chatThemes.first,
    canSend: false,
  );
}

Widget buildChatInputDockForTest({required Widget child}) {
  return _ChatInputDock(theme: _chatThemes.first, child: child);
}

Widget buildChatImageBubbleForTest(String asset) {
  return _ImageBubble(asset: asset);
}

Widget buildChatMessageBubbleForTest({
  required ChatMessage message,
  VoidCallback? onRetry,
}) {
  return _MessageBubble(
    message: message,
    peer: const ChatPeer(name: 'Test', handle: '@test', status: 'Cevrim ici'),
    theme: _chatThemes.first,
    onRetry: onRetry,
  );
}

Widget buildBottomAlignedChatMessagesForTest({required List<Widget> children}) {
  return _BottomAlignedMessageList(
    controller: ScrollController(),
    children: children,
  );
}

Key buildConversationMessageKeyForTest(AppConversationMessage message) {
  return _conversationMessageWidgetKey(message);
}

List<AppConversationMessage> mergeConversationMessagesForTest(
  Iterable<AppConversationMessage> left,
  Iterable<AppConversationMessage> right,
) {
  return _mergeConversationMessages(left, right);
}

Widget buildChatEmptyBodyForTest() {
  return const _ChatEmptyBody();
}

Widget buildMediaAttachmentSheetForTest({
  VoidCallback? onCameraTap,
  VoidCallback? onGalleryTap,
}) {
  return MediaAttachmentSheet(
    peerName: 'Test',
    onCameraTap: onCameraTap,
    onGalleryTap: onGalleryTap,
  );
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

class _ChatScreenState extends ConsumerState<ChatScreen>
    with TickerProviderStateMixin {
  late final TextEditingController _messageController;
  late final FocusNode _messageFocusNode;
  int? _currentUserId;
  bool _isSending = false;
  bool? _mutedOverride;
  bool? _blockedOverride;
  int? _themeIndex;
  String? _inputError;
  String? _peerStatusOverride;
  String? _aiStatusOverride;
  Timer? _typingDebounce;
  bool _typingActive = false;
  bool _peerTyping = false;
  Timer? _voiceTimer;
  AudioRecorder? _voiceRecorder;
  String? _voiceRecordingPath;
  int _voiceElapsedSeconds = 0;
  bool _voiceRecording = false;
  bool _voiceStarting = false;
  bool _voiceSending = false;
  bool _voicePressActive = false;
  bool _attachmentSheetOpening = false;
  bool _disposing = false;
  StreamSubscription<FlutterAiLocalStatusEvent>? _localAiStatusSubscription;
  AnimationController? _recordingWaveController;

  @override
  void initState() {
    super.initState();
    _ensureChatRuntimeCacheCleanerRegistered();
    final authState = ref.read(appAuthProvider).asData?.value;
    _currentUserId = authState?.user?.id;
    _messageFocusNode = FocusNode()..addListener(_handleInputFocusChange);
    _messageController = TextEditingController()
      ..addListener(_handleInputChange);
    _localAiStatusSubscription = FlutterAiTurnProcessor.instance.statusEvents
        .listen(_handleLocalAiStatusEvent);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) {
        return;
      }
      unawaited(_loadThemeSelection());
    });
  }

  @override
  void didUpdateWidget(covariant ChatScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.conversation?.id != widget.conversation?.id) {
      if (_typingActive) {
        unawaited(
          _setConversationTyping(
            false,
            force: true,
            conversationOverride: oldWidget.conversation,
          ),
        );
      }
      _typingDebounce?.cancel();
      _typingActive = false;
      _peerTyping = false;
      _attachmentSheetOpening = false;
      _themeIndex = null;
      _peerStatusOverride = null;
      _aiStatusOverride = null;
      unawaited(_loadThemeSelection());
    }
  }

  @override
  void dispose() {
    _disposing = true;
    if (_typingActive) {
      unawaited(_setConversationTyping(false, force: true));
    }
    unawaited(_cancelInlineVoiceRecording());
    unawaited(_localAiStatusSubscription?.cancel());
    _typingDebounce?.cancel();
    _messageFocusNode
      ..removeListener(_handleInputFocusChange)
      ..dispose();
    _messageController
      ..removeListener(_handleInputChange)
      ..dispose();
    super.dispose();
  }

  void _handleLocalAiStatusEvent(FlutterAiLocalStatusEvent event) {
    if (!mounted || event.conversationId != widget.conversation?.id) {
      return;
    }

    setState(() {
      _aiStatusOverride = event.status ?? '';
      _peerStatusOverride = event.status == 'typing'
          ? ((event.statusText == null || event.statusText!.trim().isEmpty)
                ? 'Yaziyor...'
                : event.statusText)
          : null;
    });

    if (event.status == null) {
      ref.read(conversationFeedRefreshProvider.notifier).state++;
    }
  }

  void _handleInputChange() {
    final hasText = _messageController.text.trim().isNotEmpty;

    if (mounted) {
      setState(() {
        if (_inputError != null && hasText) {
          _inputError = null;
        }
      });
    }

    if (!hasText) {
      if (_typingActive) {
        unawaited(_setConversationTyping(false, force: true));
      }
      return;
    }

    if (_messageFocusNode.hasFocus) {
      if (!_typingActive) {
        unawaited(_setConversationTyping(true));
      }
      _scheduleTypingStop();
    }
  }

  void _handleInputFocusChange() {
    if (!_messageFocusNode.hasFocus) {
      if (_typingActive) {
        unawaited(_setConversationTyping(false, force: true));
      }
      return;
    }

    if (_messageController.text.trim().isNotEmpty) {
      if (!_typingActive) {
        unawaited(_setConversationTyping(true));
      }
      _scheduleTypingStop();
    }
  }

  void _scheduleTypingStop() {
    _typingDebounce?.cancel();
    _typingDebounce = Timer(const Duration(seconds: 2), () {
      if (_typingActive) {
        unawaited(_setConversationTyping(false, force: true));
      }
    });
  }

  Future<void> _openAttachmentMenuAfterKeyboardDismiss() async {
    if (widget.conversation == null ||
        _attachmentSheetOpening ||
        _voiceRecording ||
        _voiceStarting) {
      return;
    }

    _attachmentSheetOpening = true;
    _messageFocusNode.unfocus();

    await Future<void>.delayed(const Duration(milliseconds: 250));

    if (!mounted || widget.conversation == null) {
      _attachmentSheetOpening = false;
      return;
    }

    try {
      await showCupertinoModalPopup<void>(
        context: context,
        builder: (_) => MediaAttachmentSheet(
          peerName: _peer.name,
          onCameraTap: () => _pickAndQueueImage(ImageSource.camera),
          onGalleryTap: () => _pickAndQueueImage(ImageSource.gallery),
        ),
      );
    } finally {
      _attachmentSheetOpening = false;
    }
  }

  Future<void> _setConversationTyping(
    bool typing, {
    bool force = false,
    AppConversationPreview? conversationOverride,
  }) async {
    final conversation = conversationOverride ?? widget.conversation;
    final token = ref.read(appAuthProvider).asData?.value?.token;
    final isCurrentConversation = conversationOverride == null;
    final wasTyping = _typingActive;

    if (!typing) {
      _typingDebounce?.cancel();
      if (isCurrentConversation) {
        _typingActive = false;
      }
    }

    if (conversation == null || token == null || token.trim().isEmpty) {
      return;
    }

    if (isCurrentConversation && wasTyping == typing) {
      return;
    }

    if (!typing && !force && !wasTyping) {
      return;
    }

    if (!typing && force && isCurrentConversation && !wasTyping) {
      return;
    }

    if (isCurrentConversation) {
      _typingActive = typing;
    }

    final api = AppAuthApi();
    try {
      await api.setConversationTyping(
        token,
        conversationId: conversation.id,
        typing: typing,
      );
    } catch (_) {
      if (conversationOverride == null && !typing) {
        _typingActive = false;
      }
    } finally {
      api.close();
    }
  }

  ChatPeer get _peer {
    final conversation = widget.conversation;
    if (conversation != null) {
      final basePeer = ChatPeer.fromConversation(conversation);
      if (_peerStatusOverride != null &&
          _peerStatusOverride!.trim().isNotEmpty) {
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

  void _handleRealtimeEvent(ChatRealtimeEvent event) {
    if (!mounted) {
      return;
    }

    if (event.conversationId != widget.conversation?.id) {
      return;
    }

    final currentUserId = _currentUserId;

    if (event.type == ChatRealtimeEventType.conversationTyping) {
      final actorId = _payloadInt(event.payload['user_id']);
      if (actorId == null || actorId == currentUserId) {
        return;
      }

      final typing = _payloadBool(event.payload['typing']);
      final statusText = event.payload['status_text']?.toString();
      setState(() {
        _peerTyping = typing;
        _peerStatusOverride = typing
            ? ((statusText == null || statusText.trim().isEmpty)
                  ? 'Yaziyor...'
                  : statusText)
            : null;
      });
      return;
    }

    if (event.type == ChatRealtimeEventType.aiStatus) {
      final nextStatus = event.payload['status']?.toString();
      final nextStatusText = event.payload['status_text']?.toString();
      setState(() {
        _aiStatusOverride = nextStatus;
        _peerStatusOverride = nextStatus == 'typing'
            ? ((nextStatusText == null || nextStatusText.trim().isEmpty)
                  ? 'Yaziyor...'
                  : nextStatusText)
            : (nextStatusText == null || nextStatusText.trim().isEmpty)
            ? null
            : nextStatusText;
      });
    } else if (event.type == ChatRealtimeEventType.messageSent) {
      final senderId = _payloadInt(event.payload['gonderen_user_id']);
      if (senderId != null && senderId != currentUserId) {
        setState(() {
          _peerTyping = false;
          _peerStatusOverride = null;
          _aiStatusOverride = null;
        });
      }
    }
  }

  Future<void> _sendMessage() async {
    final conversation = widget.conversation;
    final authState = ref.read(appAuthProvider).asData?.value;
    final text = _messageController.text.trim();

    if (conversation == null ||
        authState == null ||
        authState.user?.id == null ||
        text.isEmpty ||
        _isSending) {
      return;
    }
    final currentUser = authState.user!;
    final clientMessageId = _newClientMessageId();

    setState(() {
      _isSending = true;
      _inputError = null;
    });

    final api = AppAuthApi();
    try {
      unawaited(_setConversationTyping(false, force: true));
      final sentMessage = await api.sendMobileConversationMessage(
        authState.token,
        conversationId: conversation.id,
        clientMessageId: clientMessageId,
        messageType: 'metin',
        text: text,
      );
      await ChatLocalStore.instance.upsertConversationMessage(
        sentMessage,
        ownerUserId: currentUser.id,
      );
      _messageController.clear();
      ref.invalidate(conversationMessagesProvider(conversation.id));
      ref.read(conversationFeedRefreshProvider.notifier).state++;
      _playSendSound(currentUser);
      unawaited(
        FlutterAiTurnProcessor.instance.run(
          token: authState.token,
          ownerUserId: currentUser.id,
          forceFetch: true,
          lookaheadSeconds: 120,
        ),
      );
    } catch (error) {
      if (!mounted) {
        return;
      }
      if (error is SocketException ||
          error is HandshakeException ||
          error is TimeoutException) {
        await ChatLocalStore.instance.enqueueTextMessage(
          ownerUserId: currentUser.id,
          conversationId: conversation.id,
          senderId: currentUser.id,
          senderName: currentUser.displayName,
          senderProfileImageUrl: currentUser.profileImageUrl,
          text: text,
          clientMessageId: clientMessageId,
        );
        _messageController.clear();
        setState(() {
          _inputError = null;
        });
        ref.invalidate(conversationMessagesProvider(conversation.id));
        ref.read(conversationFeedRefreshProvider.notifier).state++;
        _playSendSound(currentUser);
        unawaited(
          FlutterAiTurnProcessor.instance.run(
            token: authState.token,
            ownerUserId: currentUser.id,
          ),
        );
        return;
      }
      final message = AppAuthErrorFormatter.messageFrom(error);
      if (error is BlockedByUserApiException ||
          message.toLowerCase().contains('engelledi')) {
        await showCupertinoDialog<void>(
          context: context,
          builder: (dialogContext) => CupertinoAlertDialog(
            title: Text(
              AppRuntimeText.instance.t(
                'chat.message.error.send_failed_title',
                'Mesaj Gonderilemedi',
              ),
              style: const TextStyle(fontFamily: AppFont.family),
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
                child: Text(
                  AppRuntimeText.instance.t('commonOk', 'Tamam'),
                  style: const TextStyle(fontFamily: AppFont.family),
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

  Future<void> _pickAndQueueImage(ImageSource source) async {
    final picked = await ImagePicker().pickImage(
      source: source,
      imageQuality: 88,
      maxWidth: 1600,
    );
    if (picked == null) {
      return;
    }

    await _queueMediaMessage(sourceFilePath: picked.path, messageType: 'foto');
  }

  Future<void> _queueVoiceMessage(VoiceRecordingResult recording) {
    return _queueMediaMessage(
      sourceFilePath: recording.filePath,
      messageType: 'ses',
      fileDuration: recording.duration,
    );
  }

  Future<void> _startInlineVoiceRecording() async {
    if (_voiceRecording ||
        _voiceStarting ||
        _voiceSending ||
        _voicePressActive ||
        _messageController.text.trim().isNotEmpty ||
        widget.conversation == null) {
      return;
    }

    _voicePressActive = true;
    _messageFocusNode.unfocus();
    unawaited(HapticFeedback.mediumImpact());
    setState(() {
      _inputError = null;
      _voiceRecording = true;
      _voiceStarting = true;
      _voiceSending = false;
      _voiceElapsedSeconds = 0;
    });

    final recorder = AudioRecorder();
    try {
      final hasPermission = await recorder.hasPermission();
      if (!hasPermission) {
        await recorder.dispose();
        _voicePressActive = false;
        if (!mounted) {
          return;
        }
        setState(() {
          _voiceRecording = false;
          _voiceStarting = false;
          _voiceSending = false;
          _voiceElapsedSeconds = 0;
          _inputError = 'Mikrofon izni verilmedi.';
        });
        return;
      }

      if (!_voicePressActive) {
        await recorder.dispose();
        return;
      }

      final directory = await getTemporaryDirectory();
      final filePath = path.join(
        directory.path,
        'voice_${DateTime.now().microsecondsSinceEpoch}.m4a',
      );
      await recorder.start(
        const RecordConfig(encoder: AudioEncoder.aacLc),
        path: filePath,
      );
      if (!mounted || !_voicePressActive) {
        await recorder.stop();
        await recorder.dispose();
        if (!_voicePressActive) {
          try {
            final file = File(filePath);
            if (await file.exists()) {
              await file.delete();
            }
          } catch (_) {}
        }
        return;
      }

      _voiceRecorder = recorder;
      _voiceRecordingPath = filePath;
      _voiceTimer?.cancel();
      _voiceTimer = Timer.periodic(const Duration(seconds: 1), (_) {
        if (!mounted || !_voiceRecording) {
          return;
        }
        setState(() => _voiceElapsedSeconds++);
      });
      _recordingWaveController ??= AnimationController(
        vsync: this,
        duration: const Duration(milliseconds: 1200),
      );
      _recordingWaveController!.repeat();
      setState(() {
        _inputError = null;
        _voiceRecording = true;
        _voiceStarting = false;
        _voiceSending = false;
      });
    } catch (_) {
      await recorder.dispose();
      _voicePressActive = false;
      if (!mounted) {
        return;
      }
      setState(() {
        _voiceRecording = false;
        _voiceStarting = false;
        _voiceSending = false;
        _voiceElapsedSeconds = 0;
        _inputError = AppRuntimeText.instance.t(
          'chat.voice.error.start_failed',
          'Ses kaydi baslatilamadi.',
        );
      });
    }
  }

  Future<void> _cancelInlineVoiceRecording() async {
    final wasRecording = _voiceRecording;
    _voicePressActive = false;
    final recorder = _voiceRecorder;
    final filePath = _voiceRecordingPath;
    _voiceTimer?.cancel();
    _voiceTimer = null;
    _voiceRecorder = null;
    _voiceRecordingPath = null;
    _recordingWaveController?.stop();
    _recordingWaveController?.dispose();
    _recordingWaveController = null;

    if (mounted && !_disposing && wasRecording) {
      setState(() {
        _voiceRecording = false;
        _voiceStarting = false;
        _voiceSending = false;
        _voicePressActive = false;
        _voiceElapsedSeconds = 0;
      });
    }

    try {
      await recorder?.stop();
    } catch (_) {}
    try {
      await recorder?.dispose();
    } catch (_) {}
    if (filePath != null && filePath.trim().isNotEmpty) {
      try {
        final file = File(filePath);
        if (await file.exists()) {
          await file.delete();
        }
      } catch (_) {}
    }
  }

  Future<void> _sendInlineVoiceRecording() async {
    if (!_voiceRecording || _voiceStarting || _voiceSending) {
      return;
    }

    if (_voiceRecorder == null) {
      return;
    }

    setState(() => _voiceSending = true);
    _recordingWaveController?.stop();
    _recordingWaveController?.dispose();
    _recordingWaveController = null;
    final recorder = _voiceRecorder;
    _voiceTimer?.cancel();
    _voiceTimer = null;

    String? filePath = _voiceRecordingPath;
    try {
      final stoppedPath = await recorder?.stop();
      if (stoppedPath != null && stoppedPath.trim().isNotEmpty) {
        filePath = stoppedPath;
      }
    } catch (_) {}
    try {
      await recorder?.dispose();
    } catch (_) {}

    _voiceRecorder = null;
    _voiceRecordingPath = null;
    final durationSeconds = _voiceElapsedSeconds < 1 ? 1 : _voiceElapsedSeconds;

    if (filePath == null || filePath.trim().isEmpty) {
      if (!mounted) {
        return;
      }
      setState(() {
        _voiceRecording = false;
        _voiceStarting = false;
        _voiceSending = false;
        _voicePressActive = false;
        _voiceElapsedSeconds = 0;
        _inputError = AppRuntimeText.instance.t(
          'chat.voice.error.missing_recording',
          'Gonderilecek ses kaydi bulunamadi.',
        );
      });
      return;
    }

    await _queueVoiceMessage(
      VoiceRecordingResult(
        filePath: filePath,
        duration: Duration(seconds: durationSeconds),
      ),
    );

    if (!mounted) {
      return;
    }
    setState(() {
      _voiceRecording = false;
      _voiceStarting = false;
      _voiceSending = false;
      _voicePressActive = false;
      _voiceElapsedSeconds = 0;
    });
  }

  Future<void> _queueMediaMessage({
    required String sourceFilePath,
    required String messageType,
    Duration? fileDuration,
  }) async {
    final conversation = widget.conversation;
    final authState = ref.read(appAuthProvider).asData?.value;
    final currentUser = authState?.user;
    if (conversation == null ||
        authState == null ||
        currentUser == null ||
        authState.token.trim().isEmpty) {
      return;
    }

    final clientMessageId = _newClientMessageId();
    final clientUploadId =
        'upload-${DateTime.now().microsecondsSinceEpoch}-$clientMessageId';

    try {
      await ChatLocalStore.instance.enqueueMediaMessage(
        ownerUserId: currentUser.id,
        conversationId: conversation.id,
        senderId: currentUser.id,
        senderName: currentUser.displayName,
        senderProfileImageUrl: currentUser.profileImageUrl,
        sourceFilePath: sourceFilePath,
        messageType: messageType,
        clientMessageId: clientMessageId,
        clientUploadId: clientUploadId,
        fileDuration: fileDuration,
      );
      ref.invalidate(conversationMessagesProvider(conversation.id));
      ref.read(conversationFeedRefreshProvider.notifier).state++;
      _playSendSound(currentUser);
      unawaited(_flushOutboxAndRefresh(conversation.id));
      unawaited(
        FlutterAiTurnProcessor.instance.run(
          token: authState.token,
          ownerUserId: currentUser.id,
        ),
      );
    } catch (error) {
      if (!mounted) {
        return;
      }
      setState(() {
        _inputError = AppAuthErrorFormatter.messageFrom(error);
      });
    }
  }

  void _playSendSound(AppUser user) {
    unawaited(
      AppMessageSoundService.instance.playSend(
        enabled: user.messageSoundsEnabled ?? true,
      ),
    );
  }

  Future<void> _flushOutboxAndRefresh(int conversationId) async {
    final authState = ref.read(appAuthProvider).asData?.value;
    final token = authState?.token;
    final userId = authState?.user?.id;
    if (token == null || token.trim().isEmpty || userId == null) {
      return;
    }

    await AppSyncEngine.instance.flushOutbox(token: token, ownerUserId: userId);
    unawaited(
      FlutterAiTurnProcessor.instance.run(
        token: token,
        ownerUserId: userId,
        forceFetch: true,
        lookaheadSeconds: 120,
      ),
    );
    if (!mounted) {
      return;
    }
    ref.invalidate(conversationMessagesProvider(conversationId));
    ref.read(conversationFeedRefreshProvider.notifier).state++;
  }

  @override
  Widget build(BuildContext context) {
    final hasConversation = widget.conversation != null;
    final authState = ref.watch(appAuthProvider).asData?.value;
    _currentUserId = authState?.user?.id;
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

    ref.listen<ChatRealtimeEventSignal?>(chatRealtimeEventBusProvider, (
      previous,
      next,
    ) {
      final event = next?.event;
      if (event == null) {
        return;
      }
      _handleRealtimeEvent(event);
    });

    ref.listen<AsyncValue<AppAuthState?>>(appAuthProvider, (previous, next) {
      _currentUserId = next.asData?.value?.user?.id;
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
      unawaited(_openAttachmentMenuAfterKeyboardDismiss());
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
                isMuted
                    ? AppRuntimeText.instance.t(
                        'chat.profile.action.unmute',
                        'Sessizden Cikar',
                      )
                    : AppRuntimeText.instance.t(
                        'chat.profile.action.mute',
                        'Sessize Al',
                      ),
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
              child: Text(
                AppRuntimeText.instance.t(
                  'chat.profile.action.report',
                  'Sikayet Et',
                ),
                style: const TextStyle(
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
                isBlocked
                    ? AppRuntimeText.instance.t(
                        'chat.profile.action.unblock',
                        'Engelden Cikar',
                      )
                    : AppRuntimeText.instance.t(
                        'chat.profile.action.block',
                        'Engelle',
                      ),
                style: const TextStyle(fontFamily: AppFont.family),
              ),
            ),
          ],
          cancelButton: CupertinoActionSheetAction(
            onPressed: () => Navigator.of(ctx).pop(),
            child: Text(
              AppRuntimeText.instance.t('commonCancel', 'Vazgec'),
              style: const TextStyle(fontFamily: AppFont.family),
            ),
          ),
        ),
      );
    }

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: _systemOverlayForTheme(theme),
      child: CupertinoPageScaffold(
        backgroundColor: theme.incomingBubble,
        resizeToAvoidBottomInset: true,
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
                          peerTyping: _peerTyping,
                          aiStatus:
                              _aiStatusOverride ??
                              widget.conversation?.aiStatus,
                          theme: theme,
                        ),
                ),
              ),
              _ChatInputDock(
                theme: theme,
                child: _ChatInputBar(
                  variant: widget.mode == ChatScreenMode.empty
                      ? ChatInputVariant.empty
                      : ChatInputVariant.full,
                  theme: theme,
                  controller: widget.conversation == null
                      ? null
                      : _messageController,
                  focusNode: widget.conversation == null
                      ? null
                      : _messageFocusNode,
                  onSend: _sendMessage,
                  onLeadingTap: openAttachmentMenu,
                  onMicTap: _startInlineVoiceRecording,
                  onVoiceCancel: _cancelInlineVoiceRecording,
                  onVoiceSend: _sendInlineVoiceRecording,
                  canSend:
                      _messageController.text.trim().isNotEmpty && !_isSending,
                  isSending: _isSending,
                  isVoiceRecording: _voiceRecording,
                  isVoiceStarting: _voiceStarting,
                  isVoiceSending: _voiceSending,
                  voiceElapsed: Duration(seconds: _voiceElapsedSeconds),
                  errorText: _inputError,
                  recordingWaveController: _recordingWaveController,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

int? _payloadInt(Object? value) {
  return switch (value) {
    final int intValue => intValue,
    final num numValue => numValue.toInt(),
    final String stringValue => int.tryParse(stringValue),
    _ => null,
  };
}

bool _payloadBool(Object? value) {
  return switch (value) {
    final bool boolValue => boolValue,
    final num numValue => numValue != 0,
    final String stringValue =>
      stringValue.trim().toLowerCase() == 'true' || stringValue.trim() == '1',
    _ => false,
  };
}

class _ChatEmptyBody extends StatelessWidget {
  const _ChatEmptyBody();

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final mediaSize = MediaQuery.sizeOf(context);
        final width = constraints.maxWidth.isFinite
            ? constraints.maxWidth
            : mediaSize.width;
        final height = constraints.maxHeight.isFinite
            ? constraints.maxHeight
            : mediaSize.height;
        final keyboardOpen = MediaQuery.viewInsetsOf(context).bottom > 0;
        final compact = keyboardOpen || height < 520;
        final mascotWidth = (width * (compact ? 0.48 : 0.58))
            .clamp(164.0, compact ? 210.0 : 260.0)
            .toDouble();
        final mascotHeight = mascotWidth * 1.16;
        final horizontalPadding = width < 360 ? 20.0 : 28.0;
        final topFlex = compact ? 1 : 2;
        final bottomFlex = compact ? 1 : 3;

        return SizedBox(
          key: const ValueKey('chat-empty-body'),
          width: double.infinity,
          child: Padding(
            padding: EdgeInsets.symmetric(horizontal: horizontalPadding),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Spacer(flex: topFlex),
                Center(
                  child: Image.asset(
                    'assets/images/hello_mascot.png',
                    key: const ValueKey('chat-empty-mascot'),
                    width: mascotWidth,
                    height: mascotHeight,
                    fit: BoxFit.contain,
                  ),
                ),
                SizedBox(height: compact ? 6 : 10),
                Align(
                  alignment: Alignment.center,
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: 360),
                    child: const Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          'Bir Selam Ver!',
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            fontFamily: AppFont.family,
                            fontWeight: FontWeight.w800,
                            fontSize: 17,
                            color: AppColors.black,
                          ),
                        ),
                        SizedBox(height: 4),
                        Text(
                          'Hadi bu firsati kacirma...',
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            fontFamily: AppFont.family,
                            fontSize: 13.5,
                            height: 1.5,
                            color: Color(0xFF666666),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
                Spacer(flex: bottomFlex),
              ],
            ),
          ),
        );
      },
    );
  }
}

class _ChatMessagesBody extends StatelessWidget {
  final ChatPeer peer;
  final AppConversationPreview? conversation;
  final bool peerTyping;
  final String? aiStatus;
  final _ChatThemePalette theme;

  const _ChatMessagesBody({
    required this.peer,
    required this.theme,
    this.peerTyping = false,
    this.aiStatus,
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
      peerTyping: peerTyping,
      aiStatus: aiStatus,
      theme: theme,
    );
  }
}

class _LiveChatMessagesBody extends ConsumerStatefulWidget {
  final AppConversationPreview conversation;
  final ChatPeer peer;
  final bool peerTyping;
  final String? aiStatus;
  final _ChatThemePalette theme;

  const _LiveChatMessagesBody({
    required this.conversation,
    required this.peer,
    required this.peerTyping,
    this.aiStatus,
    required this.theme,
  });

  @override
  ConsumerState<_LiveChatMessagesBody> createState() =>
      _LiveChatMessagesBodyState();
}

Key _conversationMessageWidgetKey(AppConversationMessage message) {
  final clientMessageId = message.clientMessageId?.trim();
  if (clientMessageId != null && clientMessageId.isNotEmpty) {
    return ValueKey('chat-message-client-$clientMessageId');
  }

  return ValueKey('chat-message-server-${message.id}');
}

class _LiveChatMessagesBodyState extends ConsumerState<_LiveChatMessagesBody> {
  late final ScrollController _scrollController;
  List<AppConversationMessage> _messages = const <AppConversationMessage>[];
  final Map<int, AppConversationMessage> _sessionTranslatedMessages =
      <int, AppConversationMessage>{};
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
    _aiStatus = widget.aiStatus;
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
      _sessionTranslatedMessages.clear();
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
      return;
    }

    if (oldWidget.aiStatus != widget.aiStatus) {
      _aiStatus = widget.aiStatus;
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
        _scrollController.position.maxScrollExtent -
                _scrollController.position.pixels >
            96) {
      return;
    }
    unawaited(_loadOlderMessages());
  }

  Future<void> _markConversationRead(String token) async {
    final currentUserId = ref.read(appAuthProvider).asData?.value?.user?.id;
    if (currentUserId == null) {
      return;
    }

    await _markConversationReadLocalAndRemote(
      ref,
      token: token,
      userId: currentUserId,
      conversationId: widget.conversation.id,
    );
  }

  Future<void> _loadInitialMessages() async {
    final authState = ref.read(appAuthProvider).asData?.value;
    final token = authState?.token;
    final userId = authState?.user?.id;
    final conversationId = widget.conversation.id;

    if (token == null || token.trim().isEmpty || userId == null) {
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
      ownerUserId: userId,
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
        _highestFetchedPage = 1;
        _hasMoreOlder = cachedMessages.length >= _conversationMessagePageSize;
      });
      unawaited(_markConversationRead(token));
      return;
    }

    final api = AppAuthApi();
    try {
      final page = await api.fetchMobileConversationMessages(
        token,
        conversationId: conversationId,
        limit: _conversationMessagePageSize,
      );
      if (!mounted || widget.conversation.id != conversationId) {
        return;
      }

      await localStore.upsertConversationMessages(
        page.messages,
        ownerUserId: userId,
      );
      unawaited(_markConversationRead(token));

      setState(() {
        _messages = page.messages;
        _initialLoading = false;
        _errorText = null;
        _highestFetchedPage = 1;
        _hasMoreOlder = page.hasMore;
        _aiStatus = page.aiStatus;
      });
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

  Future<void> _reloadMessagesFromCache() async {
    if (_refreshingLatest) {
      return;
    }

    final authState = ref.read(appAuthProvider).asData?.value;
    final token = authState?.token;
    final userId = authState?.user?.id;
    final conversationId = widget.conversation.id;
    if (token == null || token.trim().isEmpty || userId == null) {
      return;
    }

    _refreshingLatest = true;
    try {
      final cachedMessages = await ChatLocalStore.instance
          .getConversationMessages(
            conversationId,
            ownerUserId: userId,
            limit: _conversationMessagePageSize,
          );
      if (!mounted ||
          widget.conversation.id != conversationId ||
          cachedMessages.isEmpty) {
        return;
      }

      unawaited(_markConversationRead(token));
      final mergedMessages = _mergeConversationMessages(
        _messages,
        cachedMessages,
      );

      setState(() {
        _messages = mergedMessages;
        _errorText = null;
        if (_highestFetchedPage == 0) {
          _highestFetchedPage = 1;
          _hasMoreOlder = cachedMessages.length >= _conversationMessagePageSize;
        }
      });
    } finally {
      _refreshingLatest = false;
    }
  }

  Future<void> _retryPendingMessage(AppConversationMessage message) async {
    final authState = ref.read(appAuthProvider).asData?.value;
    final token = authState?.token;
    final userId = authState?.user?.id;
    final clientMessageId = message.clientMessageId?.trim();
    if (token == null ||
        token.trim().isEmpty ||
        userId == null ||
        clientMessageId == null ||
        clientMessageId.isEmpty) {
      return;
    }

    await ChatLocalStore.instance.queueOutboxRetry(
      ownerUserId: userId,
      clientMessageId: clientMessageId,
    );
    if (!mounted) {
      return;
    }
    ref.invalidate(conversationMessagesProvider(widget.conversation.id));
    unawaited(
      AppSyncEngine.instance
          .flushOutbox(token: token, ownerUserId: userId)
          .whenComplete(() {
            if (!mounted) {
              return;
            }
            ref.invalidate(
              conversationMessagesProvider(widget.conversation.id),
            );
            ref.read(conversationFeedRefreshProvider.notifier).state++;
          }),
    );
  }

  Future<void> _loadOlderMessages() async {
    if (_loadingOlder || !_hasMoreOlder || _highestFetchedPage == 0) {
      return;
    }

    final authState = ref.read(appAuthProvider).asData?.value;
    final token = authState?.token;
    final userId = authState?.user?.id;
    final conversationId = widget.conversation.id;
    if (token == null || token.trim().isEmpty || userId == null) {
      return;
    }

    final beforeId = _oldestServerMessageId(_messages);
    if (beforeId == null) {
      setState(() => _hasMoreOlder = false);
      return;
    }

    setState(() => _loadingOlder = true);

    final api = AppAuthApi();
    try {
      final page = await api.fetchMobileConversationMessages(
        token,
        conversationId: conversationId,
        beforeId: beforeId,
        limit: _conversationMessagePageSize,
      );
      if (!mounted || widget.conversation.id != conversationId) {
        return;
      }

      await ChatLocalStore.instance.upsertConversationMessages(
        page.messages,
        ownerUserId: userId,
      );

      setState(() {
        _messages = _mergeConversationMessages(page.messages, _messages);
        _loadingOlder = false;
        _errorText = null;
        _highestFetchedPage = _highestFetchedPage + 1;
        _hasMoreOlder = page.hasMore;
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
      if (!mounted || widget.conversation.id != message.conversationId) {
        return;
      }

      setState(() {
        _sessionTranslatedMessages[translated.id] = translated;
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
    final authState = ref.watch(appAuthProvider).asData?.value;
    final currentUserId = authState?.user?.id;
    final viewerLanguageCode = authState?.user?.languageCode;
    final token = authState?.token.trim();
    final mediaHttpHeaders = token == null || token.isEmpty
        ? null
        : {'Authorization': 'Bearer $token'};
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
        unawaited(_reloadMessagesFromCache());
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

    final showTypingIndicator = widget.peerTyping || _aiStatus == 'typing';

    if (_messages.isEmpty) {
      if (showTypingIndicator) {
        return _BottomAlignedMessageList(
          controller: _scrollController,
          children: [
            _MessageBubble(
              key: const ValueKey('chat-message-typing'),
              message: const ChatMessage.typing(),
              peer: widget.peer,
              theme: widget.theme,
            ),
          ],
        );
      }

      return const _ChatEmptyBody();
    }

    final displayMessages = _messages
        .map((message) => _sessionTranslatedMessages[message.id] ?? message)
        .toList(growable: false);

    final uiMessages = displayMessages
        .map((message) => _chatMessageFromApi(message, currentUserId))
        .toList(growable: true);

    if (showTypingIndicator) {
      uiMessages.add(const ChatMessage.typing());
    }

    final messageWidgets = <Widget>[
      if (_loadingOlder)
        const Padding(
          key: ValueKey('chat-loading-older'),
          padding: EdgeInsets.only(bottom: 10),
          child: Center(child: CupertinoActivityIndicator(radius: 11)),
        ),
      for (
        var messageIndex = 0;
        messageIndex < uiMessages.length;
        messageIndex++
      )
        if (messageIndex >= displayMessages.length)
          _MessageBubble(
            key: const ValueKey('chat-message-typing'),
            message: uiMessages[messageIndex],
            peer: widget.peer,
            theme: widget.theme,
            mediaHttpHeaders: mediaHttpHeaders,
          )
        else
          _MessageBubble(
            key: _conversationMessageWidgetKey(displayMessages[messageIndex]),
            message: uiMessages[messageIndex],
            peer: widget.peer,
            theme: widget.theme,
            mediaHttpHeaders: mediaHttpHeaders,
            showTranslateAction: shouldShowInlineTranslateAction(
              message: _messages[messageIndex],
              currentUserId: currentUserId,
              viewerLanguageCode: viewerLanguageCode,
              peerLanguageCode: widget.peer.languageCode,
            ),
            onTranslate:
                shouldShowInlineTranslateAction(
                  message: _messages[messageIndex],
                  currentUserId: currentUserId,
                  viewerLanguageCode: viewerLanguageCode,
                  peerLanguageCode: widget.peer.languageCode,
                )
                ? () => _translateMessage(_messages[messageIndex])
                : null,
            onReport:
                !_messages[messageIndex].isFromUser(currentUserId) &&
                    uiMessages[messageIndex].id != null
                ? () => _showMessageActions(
                    context,
                    message: displayMessages[messageIndex],
                    peer: widget.peer,
                  )
                : null,
            onRetry:
                _messages[messageIndex].isFromUser(currentUserId) &&
                    _messages[messageIndex].deliveryStatus == 'failed'
                ? () => _retryPendingMessage(_messages[messageIndex])
                : null,
          ),
    ];

    return _BottomAlignedMessageList(
      controller: _scrollController,
      children: messageWidgets,
    );
  }
}

class _BottomAlignedMessageList extends StatelessWidget {
  final ScrollController controller;
  final List<Widget> children;

  const _BottomAlignedMessageList({
    required this.controller,
    required this.children,
  });

  @override
  Widget build(BuildContext context) {
    return ListView.builder(
      controller: controller,
      reverse: true,
      physics: const RangeMaintainingScrollPhysics(
        parent: ClampingScrollPhysics(),
      ),
      padding: const EdgeInsets.only(top: 16, bottom: 16),
      itemCount: children.length,
      itemBuilder: (context, index) {
        return children[children.length - 1 - index];
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
    deliveryStatus: message.deliveryStatus,
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
                        label: AppRuntimeText.instance.t(
                          'chat.profile.action.send_gift',
                          'Hediye Gonder',
                        ),
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
                        label: isMuted
                            ? AppRuntimeText.instance.t(
                                'chat.profile.action.unmute',
                                'Sessizden Cikar',
                              )
                            : AppRuntimeText.instance.t(
                                'chat.profile.action.mute',
                                'Sessize Al',
                              ),
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
          Text(
            AppRuntimeText.instance.t(
              'chat.profile.gift_senders.title',
              'Hediye Gonderenler',
            ),
            style: const TextStyle(
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
                    AppRuntimeText.instance.t(
                      'chat.profile.gift_senders.count',
                      '{count} hediye gonderdi',
                      args: {'count': entry.totalGiftCount},
                    ),
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
            : CachedAppImage(
                imageUrl: imageUrl,
                cacheWidth: (size * 2).round(),
                cacheHeight: (size * 2).round(),
                errorBuilder: (_) => Center(
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
                  child: Text(
                    AppRuntimeText.instance.t(
                      'chat.media.empty',
                      'Bu sekmede medya yok.',
                    ),
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
              CachedAppImage(
                imageUrl: media.displayUrl,
                cacheWidth: 240,
                cacheHeight: 240,
                errorBuilder: (_) => _videoPlaceholder(),
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
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(
              CupertinoIcons.videocam_fill,
              size: 10,
              color: AppColors.white,
            ),
            const SizedBox(width: 4),
            Text(
              AppRuntimeText.instance.t('profile.media.type.video', 'Video'),
              style: const TextStyle(
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
            label: AppRuntimeText.instance.t('chat.media.tab.all', 'Tumu'),
            selected: selected == _PeerMediaTab.all,
            onTap: () => onChanged(_PeerMediaTab.all),
          ),
          _MediaTabItem(
            label: AppRuntimeText.instance.t(
              'chat.media.tab.photos',
              'Fotograflar',
            ),
            selected: selected == _PeerMediaTab.photos,
            onTap: () => onChanged(_PeerMediaTab.photos),
          ),
          _MediaTabItem(
            label: AppRuntimeText.instance.t(
              'chat.media.tab.videos',
              'Videolar',
            ),
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
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
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
          Text(
            AppRuntimeText.instance.t(
              'chat.theme.section.title',
              'Sohbet Temasi',
            ),
            style: const TextStyle(
              fontFamily: AppFont.family,
              fontWeight: FontWeight.w700,
              fontSize: 16,
              color: AppColors.black,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            AppRuntimeText.instance.t(
              'chat.theme.section.subtitle',
              'Mesajlasma gorunumunu degistir',
            ),
            style: const TextStyle(
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
            label: isBlocked
                ? AppRuntimeText.instance.t(
                    'chat.profile.action.unblock',
                    'Engelden Cikar',
                  )
                : AppRuntimeText.instance.t(
                    'chat.profile.action.block',
                    'Engelle',
                  ),
            onTap: onBlock,
          ),
          Container(
            height: 1,
            margin: const EdgeInsets.symmetric(horizontal: 20),
            color: const Color(0xFFF0F0F0),
          ),
          _DangerListRow(
            icon: CupertinoIcons.exclamationmark_triangle,
            label: AppRuntimeText.instance.t(
              'chat.profile.action.report',
              'Sikayet Et',
            ),
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

  static const List<({String code, String key, String fallback})> _options = [
    (code: '1_saat', key: 'chat.mute.option.one_hour', fallback: '1 saat'),
    (code: '8_saat', key: 'chat.mute.option.eight_hours', fallback: '8 saat'),
    (code: '1_gun', key: 'chat.mute.option.one_day', fallback: '1 gun'),
    (code: 'suresiz', key: 'chat.mute.option.always', fallback: 'Her zaman'),
  ];

  String _t(
    String key,
    String fallback, {
    Map<String, Object?> args = const {},
  }) {
    return AppRuntimeText.instance.t(key, fallback, args: args);
  }

  Future<void> _applyMute() async {
    if (_submitting) {
      return;
    }
    final token = ref.read(appAuthProvider).asData?.value?.token;
    if (token == null || token.trim().isEmpty) {
      setState(() {
        _notice = _t(
          'auth.error.login_required_action',
          'Bu islemi yapmak icin once giris yapmalisin.',
        );
      });
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
          durationCode: _options[_selected].code,
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
            Text(
              _t('chat.mute.unmute.title', 'Sessizden Cikar'),
              style: const TextStyle(
                fontFamily: AppFont.family,
                fontWeight: FontWeight.w800,
                fontSize: 20,
                color: AppColors.black,
              ),
            ),
            const SizedBox(height: 6),
            Text(
              _t(
                'chat.mute.unmute.subtitle',
                '{name} icin bildirimleri yeniden acmak ister misin?',
                args: {'name': widget.peerName},
              ),
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
              label: _submitting
                  ? _t('chat.mute.unmute.processing', 'Kaldiriliyor...')
                  : _t('chat.mute.unmute.button', 'Sessizden cikar'),
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
          Text(
            _t('chat.mute.title', 'Sessize Al'),
            style: const TextStyle(
              fontFamily: AppFont.family,
              fontWeight: FontWeight.w800,
              fontSize: 20,
              color: AppColors.black,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            _t(
              'chat.mute.subtitle',
              '{name} icin bildirimleri ne kadar susturmak istiyorsun?',
              args: {'name': widget.peerName},
            ),
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
                          _t(_options[index].key, _options[index].fallback),
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
            label: _submitting
                ? _t('chat.mute.processing', 'Uygulaniyor...')
                : _t('chat.mute.apply', 'Sessize almayi uygula'),
            onTap: _submitting ? null : _applyMute,
          ),
        ],
      ),
    );
  }
}

class MediaAttachmentSheet extends StatelessWidget {
  final String peerName;
  final VoidCallback? onCameraTap;
  final VoidCallback? onGalleryTap;

  const MediaAttachmentSheet({
    super.key,
    required this.peerName,
    this.onCameraTap,
    this.onGalleryTap,
  });

  @override
  Widget build(BuildContext context) {
    Widget item({
      required IconData icon,
      required String title,
      required String subtitle,
      VoidCallback? onTap,
    }) {
      return Padding(
        padding: const EdgeInsets.only(bottom: 10),
        child: PressableScale(
          onTap: () {
            Navigator.of(context).maybePop();
            onTap?.call();
          },
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
          Text(
            AppRuntimeText.instance.t(
              'chat.attachment.title',
              'Fotograf Gonder',
            ),
            style: const TextStyle(
              fontFamily: AppFont.family,
              fontWeight: FontWeight.w800,
              fontSize: 20,
              color: AppColors.black,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            AppRuntimeText.instance.t(
              'chatAttachmentSubtitle',
              '{name} ile sohbete bir sey ekle.',
              args: {'name': peerName},
            ),
            style: const TextStyle(
              fontFamily: AppFont.family,
              fontSize: 12.5,
              color: AppColors.neutral600,
            ),
          ),
          const SizedBox(height: 16),
          item(
            icon: CupertinoIcons.camera_fill,
            title: AppRuntimeText.instance.t('chatAttachmentCamera', 'Kamera'),
            subtitle: AppRuntimeText.instance.t(
              'chatAttachmentCameraSubtitle',
              'Anlik fotograf cek ve gonder',
            ),
            onTap: onCameraTap,
          ),
          item(
            icon: CupertinoIcons.photo_fill_on_rectangle_fill,
            title: AppRuntimeText.instance.t('chatAttachmentPhoto', 'Fotograf'),
            subtitle: AppRuntimeText.instance.t(
              'chatAttachmentPhotoSubtitle',
              'Galeriden fotograf veya ekran goruntusu sec',
            ),
            onTap: onGalleryTap,
          ),
        ],
      ),
    );
  }
}

@immutable
class VoiceRecordingResult {
  final String filePath;
  final Duration duration;

  const VoiceRecordingResult({required this.filePath, required this.duration});
}

class VoiceRecorderSheet extends StatefulWidget {
  final String peerName;
  final Future<void> Function(VoiceRecordingResult recording)? onSend;

  const VoiceRecorderSheet({super.key, required this.peerName, this.onSend});

  @override
  State<VoiceRecorderSheet> createState() => _VoiceRecorderSheetState();
}

class _VoiceRecorderSheetState extends State<VoiceRecorderSheet> {
  Timer? _timer;
  AudioRecorder? _recorder;
  String? _recordingPath;
  int _elapsed = 0;
  bool _recordingStopped = false;
  bool _sending = false;
  bool _recordingReady = false;
  String? _notice;

  @override
  void initState() {
    super.initState();
    unawaited(_startRecording());
  }

  @override
  void dispose() {
    _timer?.cancel();
    unawaited(_recorder?.dispose());
    super.dispose();
  }

  Future<void> _startRecording() async {
    final recorder = AudioRecorder();
    _recorder = recorder;
    try {
      final hasPermission = await recorder.hasPermission();
      if (!hasPermission) {
        if (mounted) {
          setState(() {
            _recordingStopped = true;
            _notice = 'Mikrofon izni verilmedi.';
          });
        }
        return;
      }

      final directory = await getTemporaryDirectory();
      final filePath = path.join(
        directory.path,
        'voice_${DateTime.now().microsecondsSinceEpoch}.m4a',
      );
      await recorder.start(
        const RecordConfig(encoder: AudioEncoder.aacLc),
        path: filePath,
      );
      if (!mounted) {
        return;
      }
      _recordingPath = filePath;
      _recordingReady = true;
      _timer = Timer.periodic(const Duration(seconds: 1), (_) {
        if (!mounted || _recordingStopped) {
          return;
        }
        setState(() => _elapsed++);
      });
      setState(() {});
    } catch (_) {
      if (mounted) {
        setState(() {
          _recordingStopped = true;
          _notice = AppRuntimeText.instance.t(
            'chat.voice.error.start_failed',
            'Ses kaydi baslatilamadi.',
          );
        });
      }
    }
  }

  Future<void> _stopRecording() async {
    if (_recordingStopped) {
      return;
    }

    _timer?.cancel();
    try {
      final stoppedPath = await _recorder?.stop();
      if (stoppedPath != null && stoppedPath.trim().isNotEmpty) {
        _recordingPath = stoppedPath;
      }
    } catch (_) {}
    if (!mounted) {
      return;
    }
    setState(() => _recordingStopped = true);
  }

  Future<void> _sendRecording() async {
    setState(() => _sending = true);
    if (!_recordingStopped) {
      await _stopRecording();
    }
    final filePath = _recordingPath;
    if (filePath == null || filePath.trim().isEmpty) {
      if (!mounted) {
        return;
      }
      setState(() {
        _sending = false;
        _notice = AppRuntimeText.instance.t(
          'chat.voice.error.missing_recording',
          'Gonderilecek ses kaydi bulunamadi.',
        );
      });
      return;
    }
    await widget.onSend?.call(
      VoiceRecordingResult(
        filePath: filePath,
        duration: Duration(seconds: _elapsed),
      ),
    );
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
            _recordingStopped
                ? AppRuntimeText.instance.t(
                    'chat.voice.title.ready',
                    'Kayit Hazir',
                  )
                : AppRuntimeText.instance.t(
                    'chat.voice.title.recording',
                    'Ses Kaydi',
                  ),
            style: const TextStyle(
              fontFamily: AppFont.family,
              fontWeight: FontWeight.w800,
              fontSize: 20,
              color: AppColors.black,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            _notice ??
                (_recordingStopped
                    ? AppRuntimeText.instance.t(
                        'chat.voice.subtitle.ready',
                        '{name} icin kaydi gonderebilirsin.',
                        args: {'name': widget.peerName},
                      )
                    : AppRuntimeText.instance.t(
                        'chat.voice.subtitle.recording',
                        'Konusmaya basla, kayit otomatik ilerliyor.',
                      )),
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
                          ? AppRuntimeText.instance.t(
                              'chat.voice.recording.stopped',
                              'Kayit durduruldu',
                            )
                          : AppRuntimeText.instance.t(
                              'chat.voice.recording.active',
                              'Kayit devam ediyor',
                            ),
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
          Row(
            children: [
              _voiceActionButton(
                icon: CupertinoIcons.delete,
                label: AppRuntimeText.instance.t('commonCancel', 'Iptal'),
                background: AppColors.grayField,
                color: AppColors.black,
                onTap: () => Navigator.of(context).maybePop(),
              ),
              const Spacer(),
              if (!_recordingStopped)
                _voiceActionButton(
                  icon: CupertinoIcons.stop_fill,
                  label: _recordingReady
                      ? AppRuntimeText.instance.t(
                          'chat.voice.action.stop',
                          'Durdur',
                        )
                      : AppRuntimeText.instance.t(
                          'chat.voice.action.ready',
                          'Hazir',
                        ),
                  background: AppColors.coral,
                  color: AppColors.white,
                  onTap: _recordingReady ? _stopRecording : null,
                )
              else
                _voiceActionButton(
                  icon: CupertinoIcons.paperplane_fill,
                  label: _sending
                      ? AppRuntimeText.instance.t(
                          'chat.voice.action.sending',
                          'Gidiyor',
                        )
                      : AppRuntimeText.instance.t(
                          'chat.voice.action.send',
                          'Gonder',
                        ),
                  background: AppColors.onlineGreen,
                  color: AppColors.white,
                  onTap: _sending ? null : _sendRecording,
                ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _voiceActionButton({
    required IconData icon,
    required String label,
    required Color background,
    required Color color,
    VoidCallback? onTap,
  }) {
    return PressableScale(
      onTap: onTap,
      scale: onTap == null ? 1 : 0.92,
      child: Opacity(
        opacity: onTap == null ? 0.55 : 1,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 50,
              height: 50,
              decoration: BoxDecoration(
                color: background,
                shape: BoxShape.circle,
              ),
              alignment: Alignment.center,
              child: Icon(icon, size: 22, color: color),
            ),
            const SizedBox(height: 6),
            Text(
              label,
              style: const TextStyle(
                fontFamily: AppFont.family,
                fontWeight: FontWeight.w700,
                fontSize: 11,
                color: AppColors.neutral600,
              ),
            ),
          ],
        ),
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
      setState(
        () => _notice = AppRuntimeText.instance.t(
          'chat.gift.error.login_required',
          'Hediye gondermek icin once giris yapmalisin.',
        ),
      );
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
                    Text(
                      AppRuntimeText.instance.t(
                        'chat.gift.title',
                        'Hediye Gonder',
                      ),
                      style: const TextStyle(
                        fontFamily: AppFont.family,
                        fontWeight: FontWeight.w800,
                        fontSize: 20,
                        color: AppColors.black,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      AppRuntimeText.instance.t(
                        'chat.gift.subtitle',
                        '{name} icin bir hediye sec',
                        args: {'name': widget.peerName},
                      ),
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
                return _GiftStateMessage(
                  message: AppRuntimeText.instance.t(
                    'giftListEmpty',
                    'Su anda gonderilebilir hediye bulunmuyor.',
                  ),
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
              sending
                  ? AppRuntimeText.instance.t(
                      'chat.gift.send.processing',
                      'Gonderiliyor...',
                    )
                  : AppRuntimeText.instance.t('chat.gift.send', 'Gonder'),
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
                    AppRuntimeText.instance.t(
                      'chat.gift.cost',
                      '{count} tas',
                      args: {'count': cost},
                    ),
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
  final String? targetDisplayName;

  const ReportSheet({
    super.key,
    this.targetType = ReportTargetType.user,
    this.targetId,
    this.targetDisplayName,
  });

  @override
  ConsumerState<ReportSheet> createState() => _ReportSheetState();
}

class _ReportSheetState extends ConsumerState<ReportSheet> {
  int? _selected;
  bool _submitting = false;
  String? _notice;
  late final TextEditingController _descriptionController;

  static const List<({String key, String fallback})> _options = [
    (key: 'chat.report.option.inappropriate', fallback: 'Uygunsuz icerik'),
    (key: 'chat.report.option.fake_profile', fallback: 'Sahte profil'),
    (key: 'chat.report.option.harassment', fallback: 'Taciz veya zorbalik'),
    (key: 'chat.report.option.other', fallback: 'Diger'),
  ];

  String _t(
    String key,
    String fallback, {
    Map<String, Object?> args = const {},
  }) {
    return AppRuntimeText.instance.t(key, fallback, args: args);
  }

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
            ? _t(
                'chat.report.error.user_target_required',
                'Bu kullaniciyi sikayet etmek icin aktif bir sohbet gerekli.',
              )
            : _t(
                'chat.report.error.message_target_required',
                'Bu mesaji sikayet etmek icin gecerli bir mesaj gerekli.',
              );
      });
      return;
    }

    final token = ref.read(appAuthProvider).asData?.value?.token;
    if (token == null || token.trim().isEmpty) {
      setState(() {
        _notice = _t(
          'auth.error.login_required_action',
          'Bu islemi yapmak icin once giris yapmalisin.',
        );
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
        category: _options[_selected!].fallback,
        description: _descriptionController.text,
      );
      if (!mounted) {
        return;
      }
      await showCupertinoDialog<void>(
        context: context,
        builder: (dialogContext) => CupertinoAlertDialog(
          title: Text(
            AppRuntimeText.instance.t('reportReceivedTitle', 'Sikayet alindi'),
          ),
          content: Text(
            AppRuntimeText.instance.t(
              'reportReceivedMessage',
              'Bildiriminiz inceleme ekibine iletildi.',
            ),
          ),
          actions: [
            CupertinoDialogAction(
              onPressed: () => Navigator.of(dialogContext).pop(),
              child: Text(AppRuntimeText.instance.t('commonOk', 'Tamam')),
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
        ? (widget.targetDisplayName?.trim().isNotEmpty == true
              ? widget.targetDisplayName!
              : _t('chat.report.default_user', 'Bu kullanici'))
        : _t('chat.report.selected_message', 'secilen mesaj');
    final subtitle = _t(
      'chat.report.subtitle',
      '{target} icin bir sebep secin. Aciklama alani istege baglidir.',
      args: {'target': objectLabel},
    );

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
          Text(
            _t('chat.report.title', 'Sikayet Et'),
            style: const TextStyle(
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
                label: _t(_options[i].key, _options[i].fallback),
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
              placeholder: AppRuntimeText.instance.t(
                'reportDescriptionPlaceholder',
                'Isterseniz kisa bir aciklama ekleyin...',
              ),
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
                _submitting
                    ? _t('chat.report.submit.processing', 'Gonderiliyor...')
                    : _t('chat.report.submit', 'Sikayet Et'),
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
              child: Text(
                _t('commonCancel', 'Vazgec'),
                style: const TextStyle(
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
        _notice = AppRuntimeText.instance.t(
          'auth.error.login_required_action',
          'Bu islemi yapmak icin once giris yapmalisin.',
        );
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
    final runtime = AppRuntimeText.instance;
    final subtitle =
        _notice ??
        (canSubmit
            ? (widget.initiallyBlocked
                  ? runtime.t(
                      'chat.block.subtitle.unblock',
                      '{name} engeli kaldirilacak.',
                      args: {'name': widget.targetDisplayName},
                    )
                  : runtime.t(
                      'chat.block.subtitle.block',
                      '{name} size mesaj gonderemeyecek ve profilinizi goremeyecek.',
                      args: {'name': widget.targetDisplayName},
                    ))
            : runtime.t(
                'chat.block.error.conversation_required',
                'Bu kullaniciyi engellemek icin aktif bir sohbet gerekli.',
              ));

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
                ? runtime.t(
                    'chat.block.title.unblock',
                    'Engeli kaldirmak istediginize emin misiniz?',
                  )
                : runtime.t(
                    'chat.block.title.block',
                    'Engellemek istediginize emin misiniz?',
                  ),
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
                          ? runtime.t(
                              'chat.block.processing.unblock',
                              'Kaldiriliyor...',
                            )
                          : runtime.t(
                              'chat.block.processing.block',
                              'Engelleniyor...',
                            ))
                    : (canSubmit
                          ? (widget.initiallyBlocked
                                ? runtime.t(
                                    'chat.profile.action.unblock',
                                    'Engelden Cikar',
                                  )
                                : runtime.t(
                                    'chat.profile.action.block',
                                    'Engelle',
                                  ))
                          : runtime.t('commonOk', 'Tamam')),
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
              child: Text(
                runtime.t('commonCancel', 'Vazgec'),
                style: const TextStyle(
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
