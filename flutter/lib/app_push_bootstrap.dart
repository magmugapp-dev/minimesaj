import 'dart:async';

import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:magmug/app_core.dart';
import 'package:magmug/core/ai/flutter_ai_turn_processor.dart';
import 'package:magmug/app_push_bindings.dart';
import 'package:magmug/app_push_device_sync.dart';
import 'package:magmug/app_push_message_effect.dart';
import 'package:magmug/app_push_messaging.dart';
import 'package:magmug/app_push_payload_handler.dart';
import 'package:magmug/features/chat/chat_local_store.dart';

class PushBootstrap extends ConsumerStatefulWidget {
  final Widget child;

  const PushBootstrap({super.key, required this.child});

  @override
  ConsumerState<PushBootstrap> createState() => _PushBootstrapState();
}

class _PushBootstrapState extends ConsumerState<PushBootstrap>
    with WidgetsBindingObserver {
  PushMessagingBindings? _pushBindings;
  bool _configured = false;
  bool _isHandlingPendingPush = false;
  bool _permissionRequested = false;
  bool _permissionGranted = false;
  String? _permissionRequestScheduledForToken;
  String? _deviceToken;
  late final StateController<int> _conversationFeedRefreshController;
  late final StateController<int> _notificationsFeedRefreshController;
  late final StateController<Map<String, String>?>
  _pendingPushPayloadController;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _conversationFeedRefreshController = ref.read(
      conversationFeedRefreshProvider.notifier,
    );
    _notificationsFeedRefreshController = ref.read(
      notificationsFeedRefreshProvider.notifier,
    );
    _pendingPushPayloadController = ref.read(
      pendingPushPayloadProvider.notifier,
    );
    unawaited(_configurePushNotifications());
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _pushBindings?.cancel();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (!mounted) {
      return;
    }

    final session = ref.read(appAuthProvider).asData?.value;
    final token = session?.token;
    final ownerUserId = session?.user?.id;
    if (token == null || token.trim().isEmpty || ownerUserId == null) {
      return;
    }

    if (state == AppLifecycleState.paused ||
        state == AppLifecycleState.detached ||
        state == AppLifecycleState.inactive) {
      FlutterAiTurnProcessor.instance.onAppPaused();
      unawaited(_sendHeartbeat(token, online: false));
      return;
    }

    if (state != AppLifecycleState.resumed) {
      return;
    }

    unawaited(_sendHeartbeat(token, online: true));
    FlutterAiTurnProcessor.instance.onAppResumed(
      token: token,
      ownerUserId: ownerUserId,
    );
    unawaited(
      AppCacheSyncCoordinator.instance
          .reconcile(token: token, ownerUserId: ownerUserId)
          .then((didSync) {
            if (!mounted || !didSync) {
              return;
            }
            _conversationFeedRefreshController.state++;
          })
          .catchError((_) {}),
    );
  }

  Future<void> _sendHeartbeat(String token, {required bool online}) async {
    final api = AppAuthApi();
    try {
      await api.sendMobileHeartbeat(token, online: online);
    } catch (_) {
      // Presence heartbeat is best-effort.
    } finally {
      api.close();
    }
  }

  Future<void> _configurePushNotifications() async {
    if (!AppPushSupport.isSupported || _configured) {
      return;
    }

    await _ensureDeviceToken();
    if (!mounted) {
      return;
    }

    final bindings = await bindPushMessaging(
      onForegroundMessage: _handleForegroundMessage,
      onOpenedMessage: _handleOpenedMessage,
      onTokenRefresh: _handleTokenRefreshed,
    );
    if (!mounted) {
      bindings.cancel();
      return;
    }

    _pushBindings = bindings;
    if (bindings.initialMessage != null) {
      _handleOpenedMessage(bindings.initialMessage!);
    }

    _configured = true;
    final session = ref.read(appAuthProvider).asData?.value;
    final appLanguage =
        ref.read(appLanguageProvider).asData?.value ??
        AppPreferencesStorage.fallbackLanguage();
    await _syncNotificationDevice(session, languageCode: appLanguage.code);
  }

  void _handleTokenRefreshed(String nextToken) {
    if (!mounted) {
      return;
    }

    final previousDeviceToken = _deviceToken;
    _deviceToken = nextToken.trim();
    final session = ref.read(appAuthProvider).asData?.value;
    final appLanguage =
        ref.read(appLanguageProvider).asData?.value ??
        AppPreferencesStorage.fallbackLanguage();
    unawaited(
      _syncNotificationDevice(
        session,
        previousDeviceToken: previousDeviceToken,
        languageCode: appLanguage.code,
      ),
    );
  }

  Future<void> _ensureDeviceToken() async {
    _deviceToken = await ensurePushDeviceToken(_deviceToken);
  }

  Future<void> _requestNotificationPermissionIfNeeded(
    AppAuthState? session,
  ) async {
    if (!AppPushSupport.isSupported || _permissionRequested) {
      return;
    }

    final authToken = session?.token;
    if (authToken == null || authToken.trim().isEmpty) {
      return;
    }

    _permissionRequested = true;
    final appLanguage =
        ref.read(appLanguageProvider).asData?.value ??
        AppPreferencesStorage.fallbackLanguage();

    _permissionGranted = await requestPushNotificationPermission();

    await _syncNotificationDevice(session, languageCode: appLanguage.code);
  }

  void _handleForegroundMessage(RemoteMessage message) {
    if (!mounted) {
      return;
    }

    final effect = resolvePushMessageEffect(message);
    if (effect.refreshConversationFeed) {
      unawaited(_applyConversationPushEffect(message));
    }

    if (effect.refreshNotificationsFeed) {
      _notificationsFeedRefreshController.state++;
    }
  }

  void _handleOpenedMessage(RemoteMessage message) {
    if (!mounted) {
      return;
    }

    final effect = resolvePushMessageEffect(message, includePayload: true);
    if (effect.refreshConversationFeed) {
      unawaited(_applyConversationPushEffect(message));
    }
    if (effect.refreshNotificationsFeed) {
      _notificationsFeedRefreshController.state++;
    }
    _pendingPushPayloadController.state = effect.payload;
  }

  Future<void> _applyConversationPushEffect(RemoteMessage message) async {
    final session = ref.read(appAuthProvider).asData?.value;
    final token = session?.token;
    final ownerUserId = session?.user?.id;
    if (token == null || token.trim().isEmpty || ownerUserId == null) {
      return;
    }

    final applied = await _applyConversationPushToLocalCache(
      message,
      currentUserId: ownerUserId,
    );
    final senderId = _payloadInt(
      message.data['gonderen_id'] ?? message.data['gonderen_user_id'],
    );
    if (senderId != null && senderId != ownerUserId) {
      unawaited(
        AppMessageSoundService.instance.playReceive(
          enabled: session?.user?.messageSoundsEnabled ?? true,
        ),
      );
    }
    if (!mounted) {
      return;
    }

    if (applied) {
      _conversationFeedRefreshController.state++;
      return;
    }

    AppCacheSyncCoordinator.instance.scheduleDebounced(
      token: token,
      ownerUserId: ownerUserId,
      force: true,
      onComplete: (didSync) {
        if (!mounted || !didSync) {
          return;
        }
        _conversationFeedRefreshController.state++;
      },
    );
  }

  Future<bool> _applyConversationPushToLocalCache(
    RemoteMessage message, {
    required int currentUserId,
  }) async {
    final data = message.data;
    final type = data['tip']?.toString() ?? data['type']?.toString();
    if (type != 'yeni_mesaj') {
      return false;
    }

    final conversationId = _payloadInt(
      data['sohbet_id'] ?? data['conversation_id'],
    );
    final senderId = _payloadInt(
      data['gonderen_id'] ?? data['gonderen_user_id'],
    );
    if (conversationId == null || senderId == null) {
      return false;
    }

    return ChatLocalStore.instance.applyConversationMessageEvent(
      ownerUserId: currentUserId,
      conversationId: conversationId,
      senderId: senderId,
      currentUserId: currentUserId,
      messageType: data['mesaj_tipi']?.toString(),
      messageText:
          data['on_izleme']?.toString() ??
          data['mesaj']?.toString() ??
          message.notification?.body,
      createdAt: DateTime.tryParse(data['created_at']?.toString() ?? ''),
    );
  }

  Future<void> _syncNotificationDevice(
    AppAuthState? session, {
    String? previousAuthToken,
    String? previousDeviceToken,
    String? languageCode,
  }) async {
    await _ensureDeviceToken();
    final deviceToken = _deviceToken;
    if (!_configured || deviceToken == null || deviceToken.trim().isEmpty) {
      return;
    }

    await syncNotificationDevice(
      session,
      deviceToken: deviceToken,
      permissionGranted: _permissionGranted,
      previousAuthToken: previousAuthToken,
      previousDeviceToken: previousDeviceToken,
      languageCode: languageCode,
    );
  }

  Future<void> _handlePendingPushPayload(Map<String, String>? payload) async {
    if (_isHandlingPendingPush ||
        payload == null ||
        payload.isEmpty ||
        !mounted) {
      return;
    }

    final authState = ref.read(appAuthProvider).asData?.value;
    final authToken = authState?.token;
    final currentUserId = authState?.user?.id;

    if (authToken == null ||
        authToken.trim().isEmpty ||
        currentUserId == null) {
      return;
    }

    _isHandlingPendingPush = true;

    try {
      await handlePendingPushPayload(
        context,
        payload: payload,
        authToken: authToken,
        currentUserId: currentUserId,
      );
    } catch (_) {
      return;
    } finally {
      _pendingPushPayloadController.state = null;
      _isHandlingPendingPush = false;
    }
  }

  void _handleAuthStateChanged(
    AsyncValue<AppAuthState?>? previous,
    AsyncValue<AppAuthState?> next,
  ) {
    final previousSession = previous?.asData?.value;
    final nextSession = next.asData?.value;
    final previousAuthToken = previousSession?.token != nextSession?.token
        ? previousSession?.token
        : null;
    final appLanguage =
        ref.read(appLanguageProvider).asData?.value ??
        AppPreferencesStorage.fallbackLanguage();
    unawaited(
      _syncNotificationDevice(
        nextSession,
        previousAuthToken: previousAuthToken,
        languageCode: appLanguage.code,
      ),
    );

    final becameAuthenticated =
        previousSession?.token != nextSession?.token &&
        nextSession?.token.trim().isNotEmpty == true;
    if (becameAuthenticated) {
      unawaited(_requestNotificationPermissionIfNeeded(nextSession));
      unawaited(
        _handlePendingPushPayload(ref.read(pendingPushPayloadProvider)),
      );
    }
  }

  void _handleLanguageChanged(
    AsyncValue<AppLanguage>? previous,
    AsyncValue<AppLanguage> next,
  ) {
    final previousLanguage = previous?.asData?.value;
    final nextLanguage = next.asData?.value;
    if (previousLanguage == nextLanguage || nextLanguage == null) {
      return;
    }

    unawaited(
      _syncNotificationDevice(
        ref.read(appAuthProvider).asData?.value,
        languageCode: nextLanguage.code,
      ),
    );
  }

  void _requestPermissionForCurrentSession(AppAuthState? currentSession) {
    final token = currentSession?.token.trim();
    if (token == null || token.isEmpty || _permissionRequested) {
      return;
    }

    if (_permissionRequestScheduledForToken == token) {
      return;
    }

    _permissionRequestScheduledForToken = token;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) {
        return;
      }
      _permissionRequestScheduledForToken = null;
      unawaited(_requestNotificationPermissionIfNeeded(currentSession));
    });
  }

  void _handlePendingPushPayloadChanged(
    Map<String, String>? previous,
    Map<String, String>? next,
  ) {
    unawaited(_handlePendingPushPayload(next));
  }

  void _listenAuthState() {
    ref.listen<AsyncValue<AppAuthState?>>(
      appAuthProvider,
      _handleAuthStateChanged,
    );
  }

  void _listenLanguageState() {
    ref.listen<AsyncValue<AppLanguage>>(
      appLanguageProvider,
      _handleLanguageChanged,
    );
  }

  void _listenPendingPushPayload() {
    ref.listen<Map<String, String>?>(
      pendingPushPayloadProvider,
      _handlePendingPushPayloadChanged,
    );
  }

  @override
  Widget build(BuildContext context) {
    _listenAuthState();

    final currentSession = ref.watch(appAuthProvider).asData?.value;
    _requestPermissionForCurrentSession(currentSession);

    _listenLanguageState();
    _listenPendingPushPayload();

    return widget.child;
  }
}

int? _payloadInt(Object? value) {
  return switch (value) {
    final int intValue => intValue,
    final num numValue => numValue.toInt(),
    final String stringValue => int.tryParse(stringValue.trim()),
    _ => null,
  };
}
