import 'dart:async';

import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:magmug/app_core.dart';
import 'package:magmug/app_push_bindings.dart';
import 'package:magmug/app_push_device_sync.dart';
import 'package:magmug/app_push_message_effect.dart';
import 'package:magmug/app_push_messaging.dart';
import 'package:magmug/app_push_payload_handler.dart';

class PushBootstrap extends ConsumerStatefulWidget {
  final Widget child;

  const PushBootstrap({super.key, required this.child});

  @override
  ConsumerState<PushBootstrap> createState() => _PushBootstrapState();
}

class _PushBootstrapState extends ConsumerState<PushBootstrap> {
  PushMessagingBindings? _pushBindings;
  bool _configured = false;
  bool _isHandlingPendingPush = false;
  bool _permissionRequested = false;
  bool _permissionGranted = false;
  String? _deviceToken;
  late final StateController<int> _conversationFeedRefreshController;
  late final StateController<int> _notificationsFeedRefreshController;
  late final StateController<Map<String, String>?>
  _pendingPushPayloadController;

  @override
  void initState() {
    super.initState();
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
    _pushBindings?.cancel();
    super.dispose();
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
      _conversationFeedRefreshController.state++;
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
      _conversationFeedRefreshController.state++;
    }
    if (effect.refreshNotificationsFeed) {
      _notificationsFeedRefreshController.state++;
    }
    _pendingPushPayloadController.state = effect.payload;
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
    if (currentSession?.token.trim().isNotEmpty != true) {
      return;
    }

    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) {
        return;
      }
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
