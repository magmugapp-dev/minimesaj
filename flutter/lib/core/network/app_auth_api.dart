import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:math' as math;

import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import 'package:http/io_client.dart';
import 'package:http_parser/http_parser.dart';
import 'package:magmug/core/chat/chat_text_sanitizer.dart';
import 'package:magmug/core/config/app_config.dart';
import 'package:magmug/core/i18n/app_runtime_text.dart';
import 'package:magmug/core/models/app_content_models.dart';
import 'package:magmug/core/models/auth_models.dart';
import 'package:magmug/core/models/communication_models.dart';
import 'package:magmug/core/models/match_models.dart';
import 'package:magmug/core/models/payment_models.dart';
import 'package:magmug/core/models/public_settings_models.dart';
import 'package:magmug/core/models/user_models.dart';
import 'package:magmug/core/network/app_api.dart';

typedef UploadProgressCallback = void Function(double progress);

@immutable
class AppMobileBootstrap {
  final String? syncToken;
  final AppUser user;
  final AppPublicSettings publicSettings;
  final AppMatchCenterSummary matchSummary;
  final List<AppConversationPreview> conversations;
  final List<AppMatchCandidate> discoverProfiles;
  final int unreadNotificationCount;

  const AppMobileBootstrap({
    required this.syncToken,
    required this.user,
    required this.publicSettings,
    required this.matchSummary,
    required this.conversations,
    required this.discoverProfiles,
    required this.unreadNotificationCount,
  });
}

@immutable
class AppMobileSyncResult {
  final String? syncToken;
  final bool hasMore;
  final AppUser? user;
  final AppMatchCenterSummary? matchSummary;
  final List<AppConversationPreview> conversations;
  final List<AppConversationMessage> messages;
  final List<AppNotification> notifications;
  final int unreadNotificationCount;

  const AppMobileSyncResult({
    required this.syncToken,
    this.hasMore = false,
    this.user,
    this.matchSummary,
    this.conversations = const [],
    this.messages = const [],
    this.notifications = const [],
    this.unreadNotificationCount = 0,
  });
}

@immutable
class AppMobileUploadResult {
  final String? clientUploadId;
  final String filePath;
  final String? fileUrl;
  final String? mimeType;
  final int? size;

  const AppMobileUploadResult({
    required this.clientUploadId,
    required this.filePath,
    this.fileUrl,
    this.mimeType,
    this.size,
  });
}

class _ProgressMultipartRequest extends http.MultipartRequest {
  _ProgressMultipartRequest(super.method, super.url, {this.onProgress});

  final void Function(int sentBytes, int totalBytes)? onProgress;

  @override
  http.ByteStream finalize() {
    final int totalBytes = contentLength.toInt();
    int sentBytes = 0;
    final Stream<List<int>> stream = super.finalize().transform(
      StreamTransformer<List<int>, List<int>>.fromHandlers(
        handleData: (chunk, sink) {
          sentBytes = sentBytes + chunk.length;
          onProgress?.call(sentBytes, totalBytes);
          sink.add(chunk);
        },
      ),
    );
    return http.ByteStream(stream);
  }
}

MediaType? _mediaTypeFromFilePath(String filePath) {
  final normalized = filePath.trim().toLowerCase();
  if (normalized.endsWith('.jpg') || normalized.endsWith('.jpeg')) {
    return MediaType('image', 'jpeg');
  }
  if (normalized.endsWith('.png')) {
    return MediaType('image', 'png');
  }
  if (normalized.endsWith('.webp')) {
    return MediaType('image', 'webp');
  }
  if (normalized.endsWith('.heic')) {
    return MediaType('image', 'heic');
  }
  if (normalized.endsWith('.heif')) {
    return MediaType('image', 'heif');
  }
  if (normalized.endsWith('.m4a')) {
    return MediaType('audio', 'mp4');
  }
  if (normalized.endsWith('.aac')) {
    return MediaType('audio', 'aac');
  }
  if (normalized.endsWith('.mp3')) {
    return MediaType('audio', 'mpeg');
  }
  if (normalized.endsWith('.wav')) {
    return MediaType('audio', 'wav');
  }
  if (normalized.endsWith('.mp4')) {
    return MediaType('video', 'mp4');
  }
  if (normalized.endsWith('.mov')) {
    return MediaType('video', 'quicktime');
  }
  if (normalized.endsWith('.avi')) {
    return MediaType('video', 'x-msvideo');
  }
  if (normalized.endsWith('.webm')) {
    return MediaType('video', 'webm');
  }
  if (normalized.endsWith('.m4v')) {
    return MediaType('video', 'x-m4v');
  }
  if (normalized.endsWith('.3gp')) {
    return MediaType('video', '3gpp');
  }
  if (normalized.endsWith('.mkv')) {
    return MediaType('video', 'x-matroska');
  }
  return null;
}

class AppAuthApi {
  final http.Client _client;

  AppAuthApi({http.Client? client})
    : _client = client ?? AppHttpClientFactory.createForApi();

  Future<bool> checkUsernameAvailability(String username) async {
    final normalized = username.trim().replaceFirst(RegExp(r'^@+'), '');
    if (normalized.isEmpty) {
      throw ApiException(
        _text('apiErrorUsernameRequired', 'Kullanici adi bos birakilamaz.'),
      );
    }

    final uri = AppApi.uri(
      AppApi.usernameAvailabilityPath,
    ).replace(queryParameters: {'kullanici_adi': normalized});
    final response = await _client.get(
      uri,
      headers: const {'Accept': 'application/json'},
    );

    final payload = _decodeJsonMap(response);
    if (response.statusCode >= 400) {
      throw _extractApiException(payload);
    }

    return payload['musait'] == true;
  }

  Future<SocialAuthLoginResult> socialLogin({
    required SocialAuthProvider provider,
    required String token,
    String? firstName,
    String? lastName,
    String? avatarUrl,
  }) async {
    final appVersion = await AppClientMetadata.appVersion();
    final body = <String, String>{
      'provider': provider.name,
      'token': token,
      'istemci_tipi': AppEnvironment.clientType,
    };
    _putIfNotBlank(body, 'uygulama_versiyonu', appVersion);
    _putIfNotBlank(body, 'ad', firstName);
    _putIfNotBlank(body, 'soyad', lastName);
    _putIfNotBlank(body, 'avatar_url', avatarUrl);

    final response = await _client.post(
      AppApi.uri(AppApi.socialLoginPath),
      headers: const {'Accept': 'application/json'},
      body: body,
    );

    final payload = _decodeJsonMap(response);

    if (response.statusCode >= 400) {
      throw _extractApiException(payload);
    }

    final durum = (payload['durum'] ?? '').toString();

    if (durum == 'authenticated') {
      return SocialAuthLoginResult.authenticated(
        AuthenticatedSession(
          token: (payload['token'] ?? '').toString(),
          user: _extractUser(payload['kullanici']),
        ),
      );
    }

    return SocialAuthLoginResult.onboardingRequired(
      socialSession: (payload['social_session'] ?? '').toString(),
      prefill: SocialAuthPrefill.fromJson(
        payload['prefill'] is Map<String, dynamic>
            ? payload['prefill'] as Map<String, dynamic>
            : payload['prefill'] is Map
            ? (payload['prefill'] as Map).map(
                (key, value) => MapEntry(key.toString(), value),
              )
            : const {},
      ),
    );
  }

  Future<AuthenticatedSession> completeSocialRegistration(
    OnboardData data,
  ) async {
    if (!data.hasSocialSession) {
      throw ApiException(
        _text(
          'apiErrorSocialSessionMissing',
          'Sosyal oturum bilgisi bulunamadi.',
        ),
      );
    }

    if (!data.step1Valid || data.gender == null) {
      throw ApiException(
        _text(
          'apiErrorOnboardingIncomplete',
          'Onboarding alanlari tamamlanmadi.',
        ),
      );
    }

    final request = http.MultipartRequest(
      'POST',
      AppApi.uri(AppApi.socialRegisterPath),
    );
    final appVersion = await AppClientMetadata.appVersion();
    request.headers['Accept'] = 'application/json';
    final fields = <String, String>{
      'social_session': data.socialSession!.trim(),
      'ad': data.fullNameForApi,
      'kullanici_adi': data.username.trim(),
      'cinsiyet': _genderValue(data.gender),
      'dogum_yili': '${data.birthYear}',
    };
    _putIfNotBlank(fields, 'uygulama_versiyonu', appVersion);
    request.fields.addAll(fields);

    switch (_nullableString(data.photoPath)) {
      case final photoPath?:
        request.files.add(
          await http.MultipartFile.fromPath('dosya', photoPath),
        );
    }

    final streamedResponse = await _client.send(request);
    final response = await http.Response.fromStream(streamedResponse);
    final payload = _decodeJsonMap(response);

    if (response.statusCode >= 400) {
      throw _extractApiException(payload);
    }

    return AuthenticatedSession(
      token: (payload['token'] ?? '').toString(),
      user: _extractUser(payload['kullanici']),
    );
  }

  Future<AppUser> fetchCurrentUser(String token) async {
    final response = await _client.get(
      AppApi.uri(AppApi.currentUserPath),
      headers: {'Accept': 'application/json', 'Authorization': 'Bearer $token'},
    );

    final payload = _decodeJsonMap(response);
    if (response.statusCode == 401 || response.statusCode == 403) {
      throw UnauthorizedApiException(
        _text('apiErrorSessionExpired', 'Oturum suresi doldu.'),
      );
    }
    if (response.statusCode >= 400) {
      throw ApiException(_extractErrorMessage(payload));
    }

    return AppUser.fromJson(_unwrapDataMap(payload));
  }

  Future<AppPublicSettings> fetchPublicSettings() async {
    final response = await _client.get(
      AppApi.uri(AppApi.publicSettingsPath),
      headers: const {'Accept': 'application/json'},
    );

    final payload = _decodeJsonMap(response);
    if (response.statusCode >= 400) {
      throw ApiException(_extractErrorMessage(payload));
    }

    final data = _asMap(payload['veri']) ?? _asMap(payload['data']) ?? payload;
    return AppPublicSettings.fromJson(data);
  }

  Future<AppPublicSettings> fetchMobileConfig() async {
    final response = await _client.get(
      AppApi.uri(AppApi.mobileConfigPath),
      headers: const {'Accept': 'application/json'},
    );

    final payload = _decodeJsonMap(response);
    if (response.statusCode >= 400) {
      throw ApiException(_extractErrorMessage(payload));
    }

    final settings = _asMap(payload['public_settings']) ?? payload;
    return AppPublicSettings.fromJson(settings);
  }

  Future<AppMobileBootstrap> fetchMobileBootstrap(String token) async {
    final response = await _client.get(
      AppApi.uri(AppApi.mobileBootstrapPath),
      headers: {'Accept': 'application/json', 'Authorization': 'Bearer $token'},
    );

    final payload = _decodeJsonMap(response);
    if (response.statusCode == 401 || response.statusCode == 403) {
      throw UnauthorizedApiException(
        _text('apiErrorSessionExpired', 'Oturum suresi doldu.'),
      );
    }
    if (response.statusCode >= 400) {
      throw ApiException(_extractErrorMessage(payload));
    }

    final user = _extractUser(payload['user']);
    if (user == null) {
      throw ApiException(
        _text(
          'apiErrorMobileBootstrapUserUnreadable',
          'Mobil baslangic kullanici yaniti okunamadi.',
        ),
      );
    }
    final currentUserId = user.id;

    return AppMobileBootstrap(
      syncToken: _nullableString(payload['sync_token']?.toString()),
      user: user,
      publicSettings: AppPublicSettings.fromJson(
        _asMap(payload['public_settings']) ?? const <String, dynamic>{},
      ),
      matchSummary: AppMatchCenterSummary.fromJson(
        _asMap(payload['match_summary']) ?? const <String, dynamic>{},
      ),
      conversations: _mapsFromValue(payload['conversations'])
          .map(
            (item) => _conversationFromJson(item, currentUserId: currentUserId),
          )
          .whereType<AppConversationPreview>()
          .toList(),
      discoverProfiles: _mapsFromValue(payload['discover_profiles'])
          .map(AppMatchCandidate.fromJson)
          .where((candidate) => candidate.id > 0)
          .toList(),
      unreadNotificationCount:
          (_asMap(payload['notifications'])?['unread_count'] as num?)
              ?.toInt() ??
          0,
    );
  }

  Future<AppMobileSyncResult> syncMobile(
    String token, {
    String? syncToken,
  }) async {
    final response = await _client.post(
      AppApi.uri(AppApi.mobileSyncPath),
      headers: {
        'Accept': 'application/json',
        'Authorization': 'Bearer $token',
        'Content-Type': 'application/json',
      },
      body: jsonEncode({
        if (syncToken != null && syncToken.trim().isNotEmpty)
          'sync_token': syncToken.trim(),
      }),
    );

    final payload = _decodeJsonMap(response);
    if (response.statusCode == 401 || response.statusCode == 403) {
      throw UnauthorizedApiException(
        _text('apiErrorSessionExpired', 'Oturum suresi doldu.'),
      );
    }
    if (response.statusCode >= 400) {
      throw ApiException(_extractErrorMessage(payload));
    }

    final user = _extractUser(payload['user']);
    final currentUserId = user?.id;
    final notificationPayload = _asMap(payload['notifications']);

    return AppMobileSyncResult(
      syncToken: _nullableString(payload['sync_token']?.toString()),
      hasMore: _boolFromValue(payload['has_more']),
      user: user,
      matchSummary: AppMatchCenterSummary.fromJson(
        _asMap(payload['match_summary']) ?? const <String, dynamic>{},
      ),
      conversations: currentUserId == null
          ? const <AppConversationPreview>[]
          : _mapsFromValue(payload['conversations'])
                .map(
                  (item) =>
                      _conversationFromJson(item, currentUserId: currentUserId),
                )
                .whereType<AppConversationPreview>()
                .toList(),
      messages: _mapsFromValue(payload['messages'])
          .map(_conversationMessageFromJson)
          .whereType<AppConversationMessage>()
          .toList(),
      notifications: _mapsFromValue(
        notificationPayload?['items'],
      ).map(_notificationFromJson).whereType<AppNotification>().toList(),
      unreadNotificationCount:
          (notificationPayload?['unread_count'] as num?)?.toInt() ?? 0,
    );
  }

  Future<List<AppCreditPackage>> fetchCreditPackages(
    String token, {
    String? platform,
  }) async {
    final uri = AppApi.uri(AppApi.paymentPackagesPath).replace(
      queryParameters: {
        if (platform != null && platform.trim().isNotEmpty)
          'platform': platform.trim(),
      },
    );
    final response = await _client.get(
      uri,
      headers: {'Accept': 'application/json', 'Authorization': 'Bearer $token'},
    );

    final payload = _decodeJsonMap(response);
    if (response.statusCode == 401 || response.statusCode == 403) {
      throw UnauthorizedApiException(
        _text('apiErrorSessionExpired', 'Oturum suresi doldu.'),
      );
    }
    if (response.statusCode >= 400) {
      throw ApiException(_extractErrorMessage(payload));
    }

    final packages = _extractDataList(payload)
        .map(AppCreditPackage.fromJson)
        .where((package) => package.isActive && package.credits > 0)
        .toList();
    packages.sort((left, right) => left.order.compareTo(right.order));
    return packages;
  }

  Future<List<AppSubscriptionPackage>> fetchSubscriptionPackages(
    String token, {
    String? platform,
  }) async {
    final uri = AppApi.uri(AppApi.subscriptionPackagesPath).replace(
      queryParameters: {
        if (platform != null && platform.trim().isNotEmpty)
          'platform': platform.trim(),
      },
    );
    final response = await _client.get(
      uri,
      headers: {'Accept': 'application/json', 'Authorization': 'Bearer $token'},
    );

    final payload = _decodeJsonMap(response);
    if (response.statusCode == 401 || response.statusCode == 403) {
      throw UnauthorizedApiException(
        _text('apiErrorSessionExpired', 'Oturum suresi doldu.'),
      );
    }
    if (response.statusCode >= 400) {
      throw ApiException(_extractErrorMessage(payload));
    }

    final packages = _extractDataList(payload)
        .map(AppSubscriptionPackage.fromJson)
        .where((package) => package.isActive && package.months > 0)
        .toList();
    packages.sort((left, right) => left.order.compareTo(right.order));
    return packages;
  }

  Future<List<AppGift>> fetchGifts(String token) async {
    final response = await _client.get(
      AppApi.uri(AppApi.giftListPath),
      headers: {'Accept': 'application/json', 'Authorization': 'Bearer $token'},
    );

    final payload = _decodeJsonMap(response);
    if (response.statusCode == 401 || response.statusCode == 403) {
      throw UnauthorizedApiException(
        _text('apiErrorSessionExpired', 'Oturum suresi doldu.'),
      );
    }
    if (response.statusCode >= 400) {
      throw ApiException(_extractErrorMessage(payload));
    }

    final gifts = _extractDataList(payload)
        .map(AppGift.fromJson)
        .where((gift) => gift.active && gift.id > 0 && gift.cost > 0)
        .toList();
    gifts.sort((a, b) {
      final order = a.order.compareTo(b.order);
      return order == 0 ? a.id.compareTo(b.id) : order;
    });
    return gifts;
  }

  Future<void> verifyPurchase(
    String token, {
    required String platform,
    required String receiptData,
    required String productCode,
    required String productType,
    required double amount,
    required String currency,
  }) async {
    final response = await _client.post(
      AppApi.uri(AppApi.verifyPaymentPath),
      headers: {
        'Accept': 'application/json',
        'Authorization': 'Bearer $token',
        'Content-Type': 'application/json',
      },
      body: jsonEncode({
        'platform': platform,
        'fis_verisi': receiptData,
        'urun_kodu': productCode,
        'urun_tipi': productType,
        'tutar': amount,
        'para_birimi': currency,
      }),
    );

    final payload = _decodeJsonMap(response);
    if (response.statusCode == 401 || response.statusCode == 403) {
      throw UnauthorizedApiException(
        _text('apiErrorSessionExpired', 'Oturum suresi doldu.'),
      );
    }
    if (response.statusCode >= 400) {
      throw ApiException(_extractErrorMessage(payload));
    }
  }

  Future<AppLegalTexts> fetchLegalTexts() async {
    final response = await _client.get(
      AppApi.uri(AppApi.legalTextsPath),
      headers: const {'Accept': 'application/json'},
    );

    final payload = _decodeJsonMap(response);
    if (response.statusCode >= 400) {
      throw ApiException(_extractErrorMessage(payload));
    }

    final data = _asMap(payload['veri']) ?? _asMap(payload['data']) ?? payload;
    return AppLegalTexts.fromJson(data);
  }

  Future<AppContent> fetchAppContent(String languageCode) async {
    final uri = AppApi.uri(AppApi.appContentPath).replace(
      queryParameters: {
        if (languageCode.trim().isNotEmpty) 'lang': languageCode.trim(),
      },
    );
    final response = await _client.get(
      uri,
      headers: const {'Accept': 'application/json'},
    );

    final payload = _decodeJsonMap(response);
    if (response.statusCode >= 400) {
      throw ApiException(_extractErrorMessage(payload));
    }

    return AppContent.fromJson(payload);
  }

  Future<AppRewardAdStatus> fetchRewardAdStatus(String token) async {
    final response = await _client.get(
      AppApi.uri(AppApi.rewardAdStatusPath),
      headers: {'Accept': 'application/json', 'Authorization': 'Bearer $token'},
    );

    final payload = _decodeJsonMap(response);
    if (response.statusCode == 401 || response.statusCode == 403) {
      throw UnauthorizedApiException(
        _text('apiErrorSessionExpired', 'Oturum suresi doldu.'),
      );
    }
    if (response.statusCode >= 400) {
      throw ApiException(_extractErrorMessage(payload));
    }

    return AppRewardAdStatus.fromJson(payload);
  }

  Future<AppRewardAdClaimResult> claimRewardedAd(
    String token, {
    required String platform,
    required String adUnitId,
    required String eventCode,
    String adType = 'rewarded',
  }) async {
    final response = await _client.post(
      AppApi.uri(AppApi.claimRewardAdPath),
      headers: {
        'Accept': 'application/json',
        'Authorization': 'Bearer $token',
        'Content-Type': 'application/json',
      },
      body: jsonEncode({
        'reklam_platformu': platform,
        'reklam_birim_kodu': adUnitId,
        'olay_kodu': eventCode,
        'reklam_tipi': adType,
      }),
    );

    final payload = _decodeJsonMap(response);
    if (response.statusCode == 401 || response.statusCode == 403) {
      throw UnauthorizedApiException(
        _text('apiErrorSessionExpired', 'Oturum suresi doldu.'),
      );
    }
    if (response.statusCode >= 400) {
      throw ApiException(_extractErrorMessage(payload));
    }

    return AppRewardAdClaimResult.fromJson(payload);
  }

  Future<AppMatchCenterSummary> fetchMatchCenter(String token) async {
    final response = await _client.get(
      AppApi.uri(AppApi.matchCenterPath),
      headers: {'Accept': 'application/json', 'Authorization': 'Bearer $token'},
    );

    final payload = _decodeJsonMap(response);
    if (response.statusCode == 401 || response.statusCode == 403) {
      throw UnauthorizedApiException(
        _text('apiErrorSessionExpired', 'Oturum suresi doldu.'),
      );
    }
    if (response.statusCode >= 400) {
      throw ApiException(_extractErrorMessage(payload));
    }

    return AppMatchCenterSummary.fromJson(payload);
  }

  Future<List<AppMatchCandidate>> fetchDiscoverProfiles(
    String token, {
    int limit = 4,
  }) async {
    final uri = AppApi.uri(AppApi.discoverPath).replace(
      queryParameters: {
        'per_page': '${math.max(limit, 4)}',
        'profil_resimli': '1',
      },
    );
    final response = await _client.get(
      uri,
      headers: {'Accept': 'application/json', 'Authorization': 'Bearer $token'},
    );

    final payload = _decodeJsonMap(response);
    if (response.statusCode == 401 || response.statusCode == 403) {
      throw UnauthorizedApiException(
        _text('apiErrorSessionExpired', 'Oturum suresi doldu.'),
      );
    }
    if (response.statusCode >= 400) {
      throw ApiException(_extractErrorMessage(payload));
    }

    return _extractDataList(payload)
        .map(AppMatchCandidate.fromJson)
        .where((candidate) => candidate.primaryImageUrl != null)
        .take(limit)
        .toList();
  }

  Future<AppMatchFilters> updateMatchPreferences(
    String token, {
    required String genderCode,
    required String ageCode,
    required bool superMatchEnabled,
  }) async {
    final response = await _client.patch(
      AppApi.uri(AppApi.matchPreferencesPath),
      headers: {
        'Accept': 'application/json',
        'Authorization': 'Bearer $token',
        'Content-Type': 'application/json',
      },
      body: jsonEncode({
        'cinsiyet': genderCode,
        'yas': ageCode,
        'super_eslesme_aktif_mi': superMatchEnabled,
      }),
    );

    final payload = _decodeJsonMap(response);
    if (response.statusCode == 401 || response.statusCode == 403) {
      throw UnauthorizedApiException(
        _text('apiErrorSessionExpired', 'Oturum suresi doldu.'),
      );
    }
    if (response.statusCode >= 400) {
      throw ApiException(_extractErrorMessage(payload));
    }

    final filters = _asMap(payload['filtreler']) ?? const <String, dynamic>{};
    return AppMatchFilters.fromJson(filters);
  }

  Future<AppMatchStartResult> startMatch(String token) async {
    final response = await _client.post(
      AppApi.uri(AppApi.startMatchPath),
      headers: {'Accept': 'application/json', 'Authorization': 'Bearer $token'},
    );

    final payload = _decodeJsonMap(response);
    if (response.statusCode == 401 || response.statusCode == 403) {
      throw UnauthorizedApiException(
        _text('apiErrorSessionExpired', 'Oturum suresi doldu.'),
      );
    }
    if (response.statusCode >= 400 && response.statusCode != 402) {
      throw ApiException(_extractErrorMessage(payload));
    }

    return AppMatchStartResult.fromJson(payload);
  }

  Future<AppDirectMatchConversationResult> startMatchConversation(
    String token, {
    required int userId,
  }) async {
    final response = await _client.post(
      AppApi.uri(AppApi.startMatchConversationPath(userId)),
      headers: {'Accept': 'application/json', 'Authorization': 'Bearer $token'},
    );

    final payload = _decodeJsonMap(response);
    if (response.statusCode == 401 || response.statusCode == 403) {
      throw UnauthorizedApiException(
        _text('apiErrorSessionExpired', 'Oturum suresi doldu.'),
      );
    }
    if (response.statusCode >= 400) {
      throw ApiException(_extractErrorMessage(payload));
    }

    return AppDirectMatchConversationResult.fromJson(payload);
  }

  Future<void> skipMatchCandidate(String token, {required int userId}) async {
    final response = await _client.post(
      AppApi.uri(AppApi.skipMatchCandidatePath(userId)),
      headers: {'Accept': 'application/json', 'Authorization': 'Bearer $token'},
    );

    final payload = _decodeJsonMap(response);
    if (response.statusCode == 401 || response.statusCode == 403) {
      throw UnauthorizedApiException(
        _text('apiErrorSessionExpired', 'Oturum suresi doldu.'),
      );
    }
    if (response.statusCode >= 400) {
      throw ApiException(_extractErrorMessage(payload));
    }
  }

  Future<void> submitSupportRequest(
    String token, {
    required String message,
  }) async {
    final response = await _client.post(
      AppApi.uri(AppApi.supportRequestPath),
      headers: {
        'Accept': 'application/json',
        'Authorization': 'Bearer $token',
        'Content-Type': 'application/json',
      },
      body: jsonEncode({'mesaj': message.trim()}),
    );

    final payload = _decodeJsonMap(response);
    if (response.statusCode == 401 || response.statusCode == 403) {
      throw UnauthorizedApiException(
        _text('apiErrorSessionExpired', 'Oturum suresi doldu.'),
      );
    }
    if (response.statusCode >= 400) {
      throw ApiException(_extractErrorMessage(payload));
    }
  }

  Future<AppUser> updateProfile(
    String token, {
    String? firstName,
    String? surname,
    String? bio,
    String? languageCode,
  }) async {
    final body = <String, dynamic>{};
    if (firstName != null) {
      body['ad'] = firstName.trim();
    }
    if (surname != null) {
      body['soyad'] = _nullableString(surname);
    }
    if (bio != null) {
      body['biyografi'] = _nullableString(bio);
    }
    if (languageCode != null && languageCode.trim().isNotEmpty) {
      body['dil'] = languageCode.trim();
    }

    if (body.isEmpty) {
      throw ApiException(
        _text('apiErrorUpdateFieldsMissing', 'Guncellenecek alan bulunamadi.'),
      );
    }

    final response = await _client.patch(
      AppApi.uri(AppApi.datingProfilePath),
      headers: {
        'Accept': 'application/json',
        'Authorization': 'Bearer $token',
        'Content-Type': 'application/json',
      },
      body: jsonEncode(body),
    );

    final payload = _decodeJsonMap(response);
    if (response.statusCode == 401 || response.statusCode == 403) {
      throw UnauthorizedApiException(
        _text('apiErrorSessionExpired', 'Oturum suresi doldu.'),
      );
    }
    if (response.statusCode >= 400) {
      throw ApiException(_extractErrorMessage(payload));
    }

    return AppUser.fromJson(_unwrapDataMap(payload));
  }

  Future<List<AppProfilePhoto>> fetchProfilePhotos(String token) async {
    final response = await _client.get(
      AppApi.uri(AppApi.datingPhotosPath),
      headers: {'Accept': 'application/json', 'Authorization': 'Bearer $token'},
    );

    final payload = _decodeJsonMap(response);
    if (response.statusCode == 401 || response.statusCode == 403) {
      throw UnauthorizedApiException(
        _text('apiErrorSessionExpired', 'Oturum suresi doldu.'),
      );
    }
    if (response.statusCode >= 400) {
      throw ApiException(_extractErrorMessage(payload));
    }

    final photos = _extractDataList(payload)
        .map(AppProfilePhoto.fromJson)
        .where((photo) => photo.url.isNotEmpty && photo.isActive)
        .toList();
    photos.sort((left, right) => left.order.compareTo(right.order));
    return photos;
  }

  Future<AppMatchCandidate> fetchDatingPeerProfile(
    String token, {
    required int userId,
  }) async {
    final response = await _client.get(
      AppApi.uri(AppApi.datingPeerProfilePath(userId)),
      headers: {'Accept': 'application/json', 'Authorization': 'Bearer $token'},
    );

    final payload = _decodeJsonMap(response);
    if (response.statusCode == 401 || response.statusCode == 403) {
      throw UnauthorizedApiException(
        _text('apiErrorSessionExpired', 'Oturum suresi doldu.'),
      );
    }
    if (response.statusCode >= 400) {
      throw ApiException(_extractErrorMessage(payload));
    }

    return AppMatchCandidate.fromJson(_unwrapDataMap(payload));
  }

  Future<AppMatchCandidate> fetchInfluencerProfile(
    String token, {
    required int userId,
  }) async {
    final response = await _client.get(
      AppApi.uri(AppApi.influencerProfilePath(userId)),
      headers: {'Accept': 'application/json', 'Authorization': 'Bearer $token'},
    );

    final payload = _decodeJsonMap(response);
    if (response.statusCode == 401 || response.statusCode == 403) {
      throw UnauthorizedApiException(
        _text('apiErrorSessionExpired', 'Oturum suresi doldu.'),
      );
    }
    if (response.statusCode == 404) {
      throw ApiException(
        _text(
          'home.search.influencer_not_found',
          'Bu profil ID ile aktif influencer bulunamadi.',
        ),
      );
    }
    if (response.statusCode >= 400) {
      throw ApiException(_extractErrorMessage(payload));
    }

    return AppMatchCandidate.fromJson(_unwrapDataMap(payload));
  }

  Future<AppProfilePhoto> updateProfileMedia(
    String token, {
    required int mediaId,
    bool? markAsPrimary,
  }) async {
    final body = <String, dynamic>{};
    if (markAsPrimary != null) {
      body['ana_fotograf_mi'] = markAsPrimary;
    }
    if (body.isEmpty) {
      throw ApiException(
        _text(
          'apiErrorMediaUpdateFieldsMissing',
          'Guncellenecek medya alani bulunamadi.',
        ),
      );
    }

    final response = await _client.patch(
      AppApi.uri(AppApi.datingPhotoPath(mediaId)),
      headers: {
        'Accept': 'application/json',
        'Authorization': 'Bearer $token',
        'Content-Type': 'application/json',
      },
      body: jsonEncode(body),
    );

    final payload = _decodeJsonMap(response);
    if (response.statusCode == 401 || response.statusCode == 403) {
      throw UnauthorizedApiException(
        _text('apiErrorSessionExpired', 'Oturum suresi doldu.'),
      );
    }
    if (response.statusCode >= 400) {
      throw ApiException(_extractErrorMessage(payload));
    }

    return AppProfilePhoto.fromJson(_unwrapDataMap(payload));
  }

  Future<List<AppConversationPreview>> fetchConversations(
    String token, {
    required int currentUserId,
  }) async {
    final response = await _client.get(
      AppApi.uri(AppApi.datingChatsPath),
      headers: {'Accept': 'application/json', 'Authorization': 'Bearer $token'},
    );

    final payload = _decodeJsonMap(response);
    if (response.statusCode == 401 || response.statusCode == 403) {
      throw UnauthorizedApiException(
        _text('apiErrorSessionExpired', 'Oturum suresi doldu.'),
      );
    }
    if (response.statusCode >= 400) {
      throw ApiException(_extractErrorMessage(payload));
    }

    return _extractDataList(payload)
        .map(
          (item) => _conversationFromJson(item, currentUserId: currentUserId),
        )
        .whereType<AppConversationPreview>()
        .toList();
  }

  Future<List<AppConversationMessage>> fetchConversationMessages(
    String token, {
    required int conversationId,
    int page = 1,
  }) async {
    final result = await fetchConversationMessagesPage(
      token,
      conversationId: conversationId,
      page: page,
    );

    return result.messages;
  }

  Future<AppConversationMessagePage> fetchConversationMessagesPage(
    String token, {
    required int conversationId,
    int page = 1,
  }) async {
    final uri = AppApi.uri(
      AppApi.datingChatMessagesPath(conversationId),
    ).replace(queryParameters: <String, String>{'page': page.toString()});

    final response = await _client.get(
      uri,
      headers: {'Accept': 'application/json', 'Authorization': 'Bearer $token'},
    );

    final payload = _decodeJsonMap(response);
    if (response.statusCode == 401 || response.statusCode == 403) {
      throw UnauthorizedApiException(
        _text('apiErrorSessionExpired', 'Oturum suresi doldu.'),
      );
    }
    if (response.statusCode >= 400) {
      throw ApiException(_extractErrorMessage(payload));
    }

    final messages = _extractDataList(payload)
        .map(_conversationMessageFromJson)
        .whereType<AppConversationMessage>()
        .toList()
        .reversed
        .toList();
    final meta = _asMap(payload['meta']);
    final ai = _asMap(payload['ai']);
    final currentPage = (meta?['current_page'] as num?)?.toInt() ?? page;
    final lastPage = (meta?['last_page'] as num?)?.toInt() ?? currentPage;
    final total = (meta?['total'] as num?)?.toInt();

    return AppConversationMessagePage(
      messages: messages,
      currentPage: currentPage,
      nextPage: currentPage < lastPage ? currentPage + 1 : null,
      total: total,
      aiStatus: _nullableString(ai?['status']?.toString()),
      aiStatusText: _nullableString(ai?['status_text']?.toString()),
      aiPlannedAt: DateTime.tryParse(ai?['planned_at']?.toString() ?? ''),
    );
  }

  Future<AppConversationMessagePage> fetchMobileConversationMessages(
    String token, {
    required int conversationId,
    int? afterId,
    int? beforeId,
    int limit = 50,
  }) async {
    final query = <String, String>{
      'limit': limit.clamp(1, 100).toString(),
      if (afterId != null) 'after_id': afterId.toString(),
      if (beforeId != null) 'before_id': beforeId.toString(),
    };
    final uri = AppApi.uri(
      AppApi.mobileConversationMessagesPath(conversationId),
    ).replace(queryParameters: query);

    final response = await _client.get(
      uri,
      headers: {'Accept': 'application/json', 'Authorization': 'Bearer $token'},
    );

    final payload = _decodeJsonMap(response);
    if (response.statusCode == 401 || response.statusCode == 403) {
      throw UnauthorizedApiException(
        _text('apiErrorSessionExpired', 'Oturum suresi doldu.'),
      );
    }
    if (response.statusCode >= 400) {
      throw ApiException(_extractErrorMessage(payload));
    }

    final meta = _asMap(payload['meta']);
    final ai = _asMap(payload['ai']);
    return AppConversationMessagePage(
      messages: _extractDataList(payload)
          .map(_conversationMessageFromJson)
          .whereType<AppConversationMessage>()
          .toList(),
      currentPage: 1,
      nextPage: meta?['has_more_older'] == true ? 2 : null,
      aiStatus: _nullableString(ai?['status']?.toString()),
      aiStatusText: _nullableString(ai?['status_text']?.toString()),
      aiPlannedAt: DateTime.tryParse(ai?['planned_at']?.toString() ?? ''),
    );
  }

  Future<AppConversationMessage> sendConversationMessage(
    String token, {
    required int conversationId,
    required String text,
    String? clientMessageId,
  }) async {
    final response = await _client.post(
      AppApi.uri(AppApi.datingChatMessagesPath(conversationId)),
      headers: {
        'Accept': 'application/json',
        'Authorization': 'Bearer $token',
        'Content-Type': 'application/json',
      },
      body: jsonEncode({
        'mesaj_tipi': 'metin',
        'mesaj_metni': text.trim(),
        if (clientMessageId != null && clientMessageId.trim().isNotEmpty)
          'client_message_id': clientMessageId.trim(),
      }),
    );

    final payload = _decodeJsonMap(response);
    if (response.statusCode == 401 || response.statusCode == 403) {
      throw UnauthorizedApiException(
        _text('apiErrorSessionExpired', 'Oturum suresi doldu.'),
      );
    }
    if (response.statusCode >= 400) {
      throw _extractApiException(payload);
    }

    final message = _conversationMessageFromJson(_unwrapDataMap(payload));
    if (message == null) {
      throw ApiException(
        _text(
          'apiErrorMessageResponseUnreadable',
          'Mesaj gonderildi ama sunucu yaniti okunamadi.',
        ),
      );
    }
    return message;
  }

  Future<AppConversationMessage> sendMobileConversationMessage(
    String token, {
    required int conversationId,
    required String clientMessageId,
    required String messageType,
    String? text,
    String? filePath,
    Duration? fileDuration,
  }) async {
    final body = <String, Object>{
      'client_message_id': clientMessageId,
      'mesaj_tipi': messageType,
    };
    final normalizedText = text?.trim();
    if (normalizedText != null) {
      body['mesaj_metni'] = normalizedText;
    }
    if (filePath != null) {
      body['dosya_yolu'] = filePath;
    }
    if (fileDuration != null) {
      body['dosya_suresi'] = fileDuration.inSeconds;
    }

    final response = await _client.post(
      AppApi.uri(AppApi.mobileConversationMessagesPath(conversationId)),
      headers: {
        'Accept': 'application/json',
        'Authorization': 'Bearer $token',
        'Content-Type': 'application/json',
      },
      body: jsonEncode(body),
    );

    final payload = _decodeJsonMap(response);
    if (response.statusCode == 401 || response.statusCode == 403) {
      throw UnauthorizedApiException(
        _text('apiErrorSessionExpired', 'Oturum suresi doldu.'),
      );
    }
    if (response.statusCode >= 400) {
      throw _extractApiException(payload);
    }

    final message = _conversationMessageFromJson(_unwrapDataMap(payload));
    if (message == null) {
      throw ApiException(
        _text(
          'apiErrorMessageResponseUnreadable',
          'Mesaj gonderildi ama sunucu yaniti okunamadi.',
        ),
      );
    }
    return message;
  }

  Future<AppConversationMessage> translateConversationMessage(
    String token, {
    required AppConversationMessage message,
  }) async {
    final response = await _client.post(
      AppApi.uri(
        AppApi.datingChatMessageTranslationPath(
          message.conversationId,
          message.id,
        ),
      ),
      headers: {'Accept': 'application/json', 'Authorization': 'Bearer $token'},
    );

    final payload = _decodeJsonMap(response);
    if (response.statusCode == 401 || response.statusCode == 403) {
      throw UnauthorizedApiException(
        _text('apiErrorSessionExpired', 'Oturum suresi doldu.'),
      );
    }
    if (response.statusCode >= 400) {
      throw _extractApiException(payload);
    }

    final translation = _asMap(payload['ceviri']);
    final translatedText = _nullableString(
      translation?['metin']?.toString() ?? payload['ceviri_metni']?.toString(),
    );
    if (translatedText == null) {
      throw ApiException(
        _text(
          'apiErrorTranslationTextUnreadable',
          'Ceviri alindi ama metin okunamadi.',
        ),
      );
    }

    return message.copyWith(
      translatedText: translatedText,
      translationTargetLanguageCode: _nullableString(
        translation?['hedef_dil_kodu']?.toString(),
      ),
      translationTargetLanguageName: _nullableString(
        translation?['hedef_dil_adi']?.toString(),
      ),
    );
  }

  Future<void> markConversationRead(
    String token, {
    required int conversationId,
  }) async {
    final response = await _client.patch(
      AppApi.uri(AppApi.datingChatReadPath(conversationId)),
      headers: {'Accept': 'application/json', 'Authorization': 'Bearer $token'},
    );

    if (response.statusCode == 401 || response.statusCode == 403) {
      throw UnauthorizedApiException(
        _text('apiErrorSessionExpired', 'Oturum suresi doldu.'),
      );
    }
    if (response.statusCode >= 400) {
      final payload = _decodeJsonMap(response);
      throw ApiException(_extractErrorMessage(payload));
    }
  }

  Future<void> setConversationTyping(
    String token, {
    required int conversationId,
    required bool typing,
  }) async {
    final response = await _client.patch(
      AppApi.uri(AppApi.datingChatTypingPath(conversationId)),
      headers: {
        'Accept': 'application/json',
        'Authorization': 'Bearer $token',
        'Content-Type': 'application/json',
      },
      body: jsonEncode({'typing': typing}),
    );

    if (response.statusCode == 401 || response.statusCode == 403) {
      throw UnauthorizedApiException(
        _text('apiErrorSessionExpired', 'Oturum suresi doldu.'),
      );
    }
    if (response.statusCode >= 400) {
      final payload = _decodeJsonMap(response);
      throw ApiException(_extractErrorMessage(payload));
    }
  }

  Future<List<AppBlockedUser>> fetchBlockedUsers(String token) async {
    final response = await _client.get(
      AppApi.uri(AppApi.blockedUsersPath),
      headers: {'Accept': 'application/json', 'Authorization': 'Bearer $token'},
    );

    final payload = _decodeJsonMap(response);
    if (response.statusCode == 401 || response.statusCode == 403) {
      throw UnauthorizedApiException(
        _text('apiErrorSessionExpired', 'Oturum suresi doldu.'),
      );
    }
    if (response.statusCode >= 400) {
      throw ApiException(_extractErrorMessage(payload));
    }

    return _extractDataList(payload).map(AppBlockedUser.fromJson).toList();
  }

  Future<void> submitReport(
    String token, {
    required String targetType,
    required int targetId,
    required String category,
    String? description,
  }) async {
    final response = await _client.post(
      AppApi.uri(AppApi.reportPath),
      headers: {
        'Accept': 'application/json',
        'Authorization': 'Bearer $token',
        'Content-Type': 'application/json',
      },
      body: jsonEncode({
        'hedef_tipi': targetType.trim(),
        'hedef_id': targetId,
        'kategori': category.trim(),
        if (description != null && description.trim().isNotEmpty)
          'aciklama': description.trim(),
      }),
    );

    final payload = _decodeJsonMap(response);
    if (response.statusCode == 401 || response.statusCode == 403) {
      throw UnauthorizedApiException(
        _text('apiErrorSessionExpired', 'Oturum suresi doldu.'),
      );
    }
    if (response.statusCode >= 400) {
      throw ApiException(_extractErrorMessage(payload));
    }
  }

  Future<void> submitUserReport(
    String token, {
    required int targetUserId,
    required String category,
    String? description,
  }) {
    return submitReport(
      token,
      targetType: 'user',
      targetId: targetUserId,
      category: category,
      description: description,
    );
  }

  Future<void> blockUser(
    String token, {
    required int userId,
    String? reason,
  }) async {
    final response = await _client.post(
      AppApi.uri(AppApi.blockUserPath(userId)),
      headers: {
        'Accept': 'application/json',
        'Authorization': 'Bearer $token',
        'Content-Type': 'application/json',
      },
      body: jsonEncode({
        if (reason != null && reason.trim().isNotEmpty) 'sebep': reason.trim(),
      }),
    );

    final payload = _decodeJsonMap(response);
    if (response.statusCode == 401 || response.statusCode == 403) {
      throw UnauthorizedApiException(
        _text('apiErrorSessionExpired', 'Oturum suresi doldu.'),
      );
    }
    if (response.statusCode >= 400) {
      throw ApiException(_extractErrorMessage(payload));
    }
  }

  Future<void> unblockUser(String token, {required int userId}) async {
    final response = await _client.delete(
      AppApi.uri(AppApi.blockUserPath(userId)),
      headers: {'Accept': 'application/json', 'Authorization': 'Bearer $token'},
    );

    if (response.statusCode == 401 || response.statusCode == 403) {
      throw UnauthorizedApiException(
        _text('apiErrorSessionExpired', 'Oturum suresi doldu.'),
      );
    }
    if (response.statusCode >= 400) {
      final payload = _decodeJsonMap(response);
      throw ApiException(_extractErrorMessage(payload));
    }
  }

  Future<void> muteUser(
    String token, {
    required int userId,
    required String durationCode,
  }) async {
    final response = await _client.post(
      AppApi.uri(AppApi.muteUserPath(userId)),
      headers: {
        'Accept': 'application/json',
        'Authorization': 'Bearer $token',
        'Content-Type': 'application/json',
      },
      body: jsonEncode({'sure': durationCode}),
    );

    final payload = _decodeJsonMap(response);
    if (response.statusCode == 401 || response.statusCode == 403) {
      throw UnauthorizedApiException(
        _text('apiErrorSessionExpired', 'Oturum suresi doldu.'),
      );
    }
    if (response.statusCode >= 400) {
      throw ApiException(_extractErrorMessage(payload));
    }
  }

  Future<void> unmuteUser(String token, {required int userId}) async {
    final response = await _client.delete(
      AppApi.uri(AppApi.muteUserPath(userId)),
      headers: {'Accept': 'application/json', 'Authorization': 'Bearer $token'},
    );

    if (response.statusCode == 401 || response.statusCode == 403) {
      throw UnauthorizedApiException(
        _text('apiErrorSessionExpired', 'Oturum suresi doldu.'),
      );
    }
    if (response.statusCode >= 400) {
      final payload = _decodeJsonMap(response);
      throw ApiException(_extractErrorMessage(payload));
    }
  }

  Future<int?> sendGift(
    String token, {
    required int receiverUserId,
    required int giftId,
  }) async {
    final response = await _client.post(
      AppApi.uri(AppApi.sendGiftPath),
      headers: {
        'Accept': 'application/json',
        'Authorization': 'Bearer $token',
        'Content-Type': 'application/json',
      },
      body: jsonEncode({'alici_user_id': receiverUserId, 'hediye_id': giftId}),
    );

    final payload = _decodeJsonMap(response);
    if (response.statusCode == 401 || response.statusCode == 403) {
      throw UnauthorizedApiException(
        _text('apiErrorSessionExpired', 'Oturum suresi doldu.'),
      );
    }
    if (response.statusCode >= 400) {
      throw ApiException(_extractErrorMessage(payload));
    }

    return (payload['mevcut_puan'] as num?)?.toInt();
  }

  Future<void> registerNotificationDevice(
    String token, {
    required String deviceToken,
    required String platform,
    required bool notificationPermission,
    String? languageCode,
  }) async {
    final response = await _client.post(
      AppApi.uri(AppApi.notificationDevicesPath),
      headers: {
        'Accept': 'application/json',
        'Authorization': 'Bearer $token',
        'Content-Type': 'application/json',
      },
      body: jsonEncode({
        'token': deviceToken,
        'platform': platform,
        'bildirim_izni': notificationPermission,
        if (languageCode != null && languageCode.trim().isNotEmpty)
          'dil': languageCode.trim(),
      }),
    );

    final payload = _decodeJsonMap(response);
    if (response.statusCode == 401 || response.statusCode == 403) {
      throw UnauthorizedApiException(
        _text('apiErrorSessionExpired', 'Oturum suresi doldu.'),
      );
    }
    if (response.statusCode >= 400) {
      throw ApiException(_extractErrorMessage(payload));
    }
  }

  Future<void> unregisterNotificationDevice(
    String token, {
    required String deviceToken,
  }) async {
    final response = await _client.delete(
      AppApi.uri(AppApi.notificationDevicesPath),
      headers: {
        'Accept': 'application/json',
        'Authorization': 'Bearer $token',
        'Content-Type': 'application/json',
      },
      body: jsonEncode({'token': deviceToken}),
    );

    if (response.statusCode == 401 || response.statusCode == 403) {
      throw UnauthorizedApiException(
        _text('apiErrorSessionExpired', 'Oturum suresi doldu.'),
      );
    }
    if (response.statusCode >= 400) {
      final payload = _decodeJsonMap(response);
      throw ApiException(_extractErrorMessage(payload));
    }
  }

  Future<List<AppNotification>> fetchNotifications(String token) async {
    final response = await _client.get(
      AppApi.uri(AppApi.notificationsPath),
      headers: {'Accept': 'application/json', 'Authorization': 'Bearer $token'},
    );

    final payload = _decodeJsonMap(response);
    if (response.statusCode == 401 || response.statusCode == 403) {
      throw UnauthorizedApiException(
        _text('apiErrorSessionExpired', 'Oturum suresi doldu.'),
      );
    }
    if (response.statusCode >= 400) {
      throw ApiException(_extractErrorMessage(payload));
    }

    return _extractDataList(
      payload,
    ).map(_notificationFromJson).whereType<AppNotification>().toList();
  }

  Future<int> fetchUnreadNotificationCount(String token) async {
    final response = await _client.get(
      AppApi.uri(AppApi.unreadNotificationsPath),
      headers: {'Accept': 'application/json', 'Authorization': 'Bearer $token'},
    );

    final payload = _decodeJsonMap(response);
    if (response.statusCode == 401 || response.statusCode == 403) {
      throw UnauthorizedApiException(
        _text('apiErrorSessionExpired', 'Oturum suresi doldu.'),
      );
    }
    if (response.statusCode >= 400) {
      throw ApiException(_extractErrorMessage(payload));
    }

    return (payload['okunmamis_sayisi'] as num?)?.toInt() ?? 0;
  }

  Future<void> markAllNotificationsRead(String token) async {
    final response = await _client.patch(
      AppApi.uri(AppApi.readAllNotificationsPath),
      headers: {'Accept': 'application/json', 'Authorization': 'Bearer $token'},
    );

    if (response.statusCode == 401 || response.statusCode == 403) {
      throw UnauthorizedApiException(
        _text('apiErrorSessionExpired', 'Oturum suresi doldu.'),
      );
    }
    if (response.statusCode >= 400) {
      final payload = _decodeJsonMap(response);
      throw ApiException(_extractErrorMessage(payload));
    }
  }

  Future<void> markNotificationRead(String token, String notificationId) async {
    final response = await _client.patch(
      AppApi.uri(AppApi.readNotificationPath(notificationId)),
      headers: {'Accept': 'application/json', 'Authorization': 'Bearer $token'},
    );

    if (response.statusCode == 401 || response.statusCode == 403) {
      throw UnauthorizedApiException(
        _text('apiErrorSessionExpired', 'Oturum suresi doldu.'),
      );
    }
    if (response.statusCode >= 400) {
      final payload = _decodeJsonMap(response);
      throw ApiException(_extractErrorMessage(payload));
    }
  }

  Future<AppUser> updateNotificationPreferences(
    String token, {
    required bool notificationsEnabled,
    required bool vibrationEnabled,
    required bool messageSoundsEnabled,
  }) async {
    final response = await _client.patch(
      AppApi.uri('/api/dating/bildirim-ayarlari'),
      headers: {
        'Accept': 'application/json',
        'Authorization': 'Bearer $token',
        'Content-Type': 'application/json',
      },
      body: jsonEncode({
        'bildirimler_acik_mi': notificationsEnabled,
        'titresim_acik_mi': vibrationEnabled,
        'ses_acik_mi': messageSoundsEnabled,
      }),
    );

    final payload = _decodeJsonMap(response);
    if (response.statusCode == 401 || response.statusCode == 403) {
      throw UnauthorizedApiException(
        _text('apiErrorSessionExpired', 'Oturum suresi doldu.'),
      );
    }
    if (response.statusCode >= 400) {
      throw ApiException(_extractErrorMessage(payload));
    }

    final user = _extractUser(payload['kullanici']);
    if (user == null) {
      throw const ApiException(
        'Bildirim ayarlari kaydedildi ama kullanici verisi okunamadi.',
      );
    }

    return user;
  }

  Future<AppProfilePhoto> uploadProfileMedia(
    String token, {
    required String filePath,
    bool markAsPrimary = false,
    UploadProgressCallback? onProgress,
  }) async {
    final request = _ProgressMultipartRequest(
      'POST',
      AppApi.uri(AppApi.datingPhotosPath),
      onProgress: onProgress == null
          ? null
          : (sentBytes, totalBytes) {
              final safeTotal = totalBytes <= 0 ? 1 : totalBytes;
              final progress = math.min(sentBytes / safeTotal, 1).toDouble();
              onProgress(progress);
            },
    );
    request.headers.addAll({
      'Accept': 'application/json',
      'Authorization': 'Bearer $token',
    });
    request.fields['ana_fotograf_mi'] = markAsPrimary ? '1' : '0';
    onProgress?.call(0);
    request.files.add(
      await http.MultipartFile.fromPath(
        'dosya',
        filePath,
        contentType: _mediaTypeFromFilePath(filePath),
      ),
    );

    final streamedResponse = await _client.send(request);
    final response = await http.Response.fromStream(streamedResponse);
    final bodyText = utf8.decode(response.bodyBytes, allowMalformed: true);
    Map<String, dynamic> payload = const {};
    if (bodyText.trim().isNotEmpty) {
      try {
        final decoded = jsonDecode(bodyText);
        if (decoded is Map<String, dynamic>) {
          payload = decoded;
        } else if (decoded is Map) {
          payload = decoded.map(
            (key, value) => MapEntry(key.toString(), value),
          );
        }
      } catch (_) {
        payload = const {};
      }
    }

    if (response.statusCode == 401 || response.statusCode == 403) {
      throw UnauthorizedApiException(
        _text('apiErrorSessionExpired', 'Oturum suresi doldu.'),
      );
    }
    if (response.statusCode >= 400) {
      if (payload.isEmpty) {
        final snippet = bodyText.length > 280
            ? '${bodyText.substring(0, 280)}...'
            : bodyText;
        throw ApiException(
          'Medya yukleme basarisiz (HTTP ${response.statusCode}). $snippet',
        );
      }
      throw _extractApiException(payload);
    }

    if (payload.isEmpty) {
      throw const ApiException(
        'Medya yuklendi ancak sunucu yaniti okunamadi. Lutfen tekrar deneyin.',
      );
    }

    onProgress?.call(1);

    return AppProfilePhoto.fromJson(_unwrapDataMap(payload));
  }

  Future<AppMobileUploadResult> uploadMobileMedia(
    String token, {
    required String filePath,
    required String clientUploadId,
    required String messageType,
    UploadProgressCallback? onProgress,
  }) async {
    final request = _ProgressMultipartRequest(
      'POST',
      AppApi.uri(AppApi.mobileUploadsPath),
      onProgress: onProgress == null
          ? null
          : (sent, total) {
              if (total <= 0) {
                onProgress(0);
              } else {
                onProgress((sent / total).clamp(0, 1).toDouble());
              }
            },
    );
    request.headers.addAll({
      'Accept': 'application/json',
      'Authorization': 'Bearer $token',
    });
    request.fields['mesaj_tipi'] = messageType;
    request.fields['client_upload_id'] = clientUploadId;
    request.files.add(
      await http.MultipartFile.fromPath(
        'dosya',
        filePath,
        contentType: _mediaTypeFromFilePath(filePath),
      ),
    );

    final streamedResponse = await _client.send(request);
    final response = await http.Response.fromStream(streamedResponse);
    final payload = _decodeJsonMap(response);
    if (response.statusCode == 401 || response.statusCode == 403) {
      throw UnauthorizedApiException(
        _text('apiErrorSessionExpired', 'Oturum suresi doldu.'),
      );
    }
    if (response.statusCode >= 400) {
      throw ApiException(_extractErrorMessage(payload));
    }

    return AppMobileUploadResult(
      clientUploadId: _nullableString(payload['client_upload_id']?.toString()),
      filePath: payload['dosya_yolu']?.toString() ?? '',
      fileUrl: _nullableString(payload['dosya_url']?.toString()),
      mimeType: _nullableString(payload['mime_tipi']?.toString()),
      size: (payload['boyut'] as num?)?.toInt(),
    );
  }

  Future<AppProfilePhoto> uploadProfilePhoto(
    String token, {
    required String filePath,
    bool markAsPrimary = false,
  }) {
    return uploadProfileMedia(
      token,
      filePath: filePath,
      markAsPrimary: markAsPrimary,
    );
  }

  Future<void> deleteProfileMedia(String token, {required int mediaId}) async {
    final response = await _client.delete(
      AppApi.uri(AppApi.datingPhotoPath(mediaId)),
      headers: {'Accept': 'application/json', 'Authorization': 'Bearer $token'},
    );

    if (response.statusCode == 401 || response.statusCode == 403) {
      throw UnauthorizedApiException(
        _text('apiErrorSessionExpired', 'Oturum suresi doldu.'),
      );
    }
    if (response.statusCode >= 400) {
      final payload = _decodeJsonMap(response);
      throw ApiException(_extractErrorMessage(payload));
    }
  }

  Future<void> logout(String token) async {
    final response = await _client.post(
      AppApi.uri(AppApi.logoutPath),
      headers: {'Accept': 'application/json', 'Authorization': 'Bearer $token'},
    );

    if (response.statusCode == 401 || response.statusCode == 403) {
      throw UnauthorizedApiException(
        _text('apiErrorSessionAlreadyEnded', 'Oturum zaten sonlanmis.'),
      );
    }

    if (response.statusCode >= 400) {
      final payload = _decodeJsonMap(response);
      throw ApiException(_extractErrorMessage(payload));
    }
  }

  Future<void> deleteAccount(String token) async {
    final response = await _client.delete(
      AppApi.uri(AppApi.deleteAccountPath),
      headers: {'Accept': 'application/json', 'Authorization': 'Bearer $token'},
    );

    if (response.statusCode == 401 || response.statusCode == 403) {
      throw UnauthorizedApiException(
        _text('apiErrorSessionAlreadyEnded', 'Oturum zaten sonlanmis.'),
      );
    }

    if (response.statusCode >= 400) {
      final payload = _decodeJsonMap(response);
      throw ApiException(_extractErrorMessage(payload));
    }
  }

  void close() {
    _client.close();
  }

  static String _text(String key, String fallback) {
    return AppRuntimeText.instance.t(key, fallback);
  }

  static void _putIfNotBlank(
    Map<String, String> target,
    String key,
    String? value,
  ) {
    switch (_nullableString(value)) {
      case final normalized?:
        target[key] = normalized;
    }
  }

  static Map<String, dynamic> _decodeJsonMap(http.Response response) {
    if (response.bodyBytes.isEmpty) {
      return const {};
    }

    final decoded = jsonDecode(utf8.decode(response.bodyBytes));
    if (decoded is Map<String, dynamic>) {
      return decoded;
    }
    if (decoded is Map) {
      return decoded.map((key, value) => MapEntry(key.toString(), value));
    }
    return const {};
  }

  static String _extractErrorMessage(Map<String, dynamic> payload) {
    final errors = payload['errors'];
    if (errors is Map) {
      for (final value in errors.values) {
        if (value is List && value.isNotEmpty) {
          return value.first.toString();
        }
        if (value is String && value.trim().isNotEmpty) {
          return value;
        }
      }
    }

    final message = payload['message']?.toString();
    if (message != null && message.trim().isNotEmpty) {
      return message;
    }

    return _text(
      'apiErrorConnection',
      'Sunucu ile baglanti kurulurken bir hata olustu.',
    );
  }

  static ApiException _extractApiException(Map<String, dynamic> payload) {
    final code =
        payload['kod']?.toString().trim().toLowerCase() ??
        payload['durum']?.toString().trim().toLowerCase();
    final updateUrl =
        _nullableString(payload['guncelleme_url']?.toString()) ??
        _nullableString(payload['update_url']?.toString());
    final minimumVersion =
        _nullableString(payload['minimum_versiyon']?.toString()) ??
        _nullableString(payload['minimum_version']?.toString());

    if (code == 'update_required' || code == 'version_mismatch') {
      return AppUpdateRequiredException(
        _extractErrorMessage(payload),
        updateUrl: updateUrl,
        minimumVersion: minimumVersion,
      );
    }

    if (code == 'engellendi' || code == 'blocked_by_user') {
      return BlockedByUserApiException(_extractErrorMessage(payload));
    }

    return ApiException(_extractErrorMessage(payload));
  }

  static String _genderValue(Gender? gender) {
    return switch (gender) {
      Gender.female => 'kadin',
      Gender.male => 'erkek',
      null => 'belirtmek_istemiyorum',
    };
  }

  static AppUser? _extractUser(Object? raw) {
    if (raw is Map<String, dynamic>) {
      return AppUser.fromJson(raw);
    }
    if (raw is Map) {
      return AppUser.fromJson(
        raw.map((key, value) => MapEntry(key.toString(), value)),
      );
    }
    return null;
  }

  static Map<String, dynamic> _unwrapDataMap(Map<String, dynamic> payload) {
    final data = payload['data'];
    if (data is Map<String, dynamic>) {
      return data;
    }
    if (data is Map) {
      return data.map((key, value) => MapEntry(key.toString(), value));
    }
    return payload;
  }

  static List<Map<String, dynamic>> _extractDataList(
    Map<String, dynamic> payload,
  ) {
    final data = payload['data'];
    if (data is! List) {
      return const [];
    }

    return data.whereType<Map>().map((item) {
      return item.map((key, value) => MapEntry(key.toString(), value));
    }).toList();
  }

  static List<Map<String, dynamic>> _mapsFromValue(Object? value) {
    if (value is! List) {
      return const [];
    }

    return value.whereType<Map>().map((item) {
      return item.map((key, val) => MapEntry(key.toString(), val));
    }).toList();
  }

  static AppConversationPreview? _conversationFromJson(
    Map<String, dynamic> json, {
    required int currentUserId,
  }) {
    final match = _asMap(json['eslesme']);
    final user = _asMap(match?['user']);
    final matchedUser = _asMap(match?['eslesen_user']);
    final leftId = (user?['id'] as num?)?.toInt();
    final peer = leftId == currentUserId ? matchedUser : user;
    final peerId = (peer?['id'] as num?)?.toInt();
    if (peer == null || peerId == null) {
      return null;
    }

    final lastMessage = _asMap(json['son_mesaj']);
    final sentBy = _asMap(lastMessage?['gonderen']);
    final sentByCurrentUser = (sentBy?['id'] as num?)?.toInt() == currentUserId;
    final messageType = lastMessage?['mesaj_tipi']?.toString();
    final messageText = _messagePreviewText(lastMessage);

    return AppConversationPreview(
      id: (json['id'] as num?)?.toInt() ?? 0,
      matchId: (json['eslesme_id'] as num?)?.toInt() ?? 0,
      peerId: peerId,
      peerName: peer['ad']?.toString() ?? _text('commonUser', 'Kullanici'),
      peerUsername: peer['kullanici_adi']?.toString() ?? '',
      peerProfileImageUrl: peer['profil_resmi']?.toString(),
      peerLanguageCode: _nullableString(
        json['peer_language_code']?.toString() ?? peer['dil']?.toString(),
      ),
      peerLanguageName: _nullableString(
        json['peer_language_name']?.toString() ?? peer['dil_adi']?.toString(),
      ),
      online: peer['cevrim_ici_mi'] == true,
      lastMessage: messageText,
      lastMessageType: messageType,
      lastMessageAt: DateTime.tryParse(
        json['son_mesaj_tarihi']?.toString() ??
            lastMessage?['created_at']?.toString() ??
            '',
      ),
      unreadCount: (json['okunmamis_sayisi'] as num?)?.toInt() ?? 0,
      myMessageRead: sentByCurrentUser && (lastMessage?['okundu_mu'] == true),
      aiStatus: _nullableString(json['ai_durumu']?.toString()),
      aiStatusText: _nullableString(json['ai_durum_metni']?.toString()),
      aiPlannedAt: DateTime.tryParse(
        json['ai_planlanan_cevap_at']?.toString() ?? '',
      ),
    );
  }

  static AppConversationMessage? conversationMessageFromJson(
    Map<String, dynamic> json,
  ) {
    return _conversationMessageFromJson(json);
  }

  static AppConversationMessage? _conversationMessageFromJson(
    Map<String, dynamic> json,
  ) {
    final sender = _asMap(json['gonderen']);
    final durationInSeconds = (json['dosya_suresi'] as num?)?.toInt();
    final senderId =
        (sender?['id'] as num?)?.toInt() ??
        (json['gonderen_user_id'] as num?)?.toInt() ??
        int.tryParse(json['gonderen_user_id']?.toString() ?? '');
    final senderName = _nullableString(
      sender?['ad']?.toString() ??
          sender?['kullanici_adi']?.toString() ??
          json['gonderen_adi']?.toString(),
    );
    return AppConversationMessage(
      id: (json['id'] as num?)?.toInt() ?? 0,
      conversationId: (json['sohbet_id'] as num?)?.toInt() ?? 0,
      senderId: senderId,
      senderName: senderName ?? _text('commonUser', 'Kullanici'),
      senderProfileImageUrl: _nullableString(
        sender?['profil_resmi']?.toString() ??
            json['gonderen_profil_resmi']?.toString(),
      ),
      type: json['mesaj_tipi']?.toString() ?? 'metin',
      text: ChatTextSanitizer.sanitize(json['mesaj_metni']?.toString()),
      fileUrl: _nullableString(
        json['dosya_yolu']?.toString() ?? json['dosya_url']?.toString(),
      ),
      fileDuration: durationInSeconds == null
          ? null
          : Duration(seconds: durationInSeconds),
      isRead: _boolFromValue(json['okundu_mu']),
      isAiGenerated: _boolFromValue(json['ai_tarafindan_uretildi_mi']),
      languageCode: _nullableString(json['dil_kodu']?.toString()),
      languageName: _nullableString(json['dil_adi']?.toString()),
      translatedText: null,
      translationTargetLanguageCode: null,
      translationTargetLanguageName: null,
      clientMessageId: _nullableString(json['client_message_id']?.toString()),
      createdAt: DateTime.tryParse(json['created_at']?.toString() ?? ''),
    );
  }

  static AppNotification? _notificationFromJson(Map<String, dynamic> json) {
    final payload = _asMap(json['veri']) ?? const <String, dynamic>{};
    final routeParameters = _stringMap(json['rota_parametreleri']);
    final title = _nullableString(
      json['baslik']?.toString() ?? payload['baslik']?.toString(),
    );
    final message = _nullableString(
      json['mesaj']?.toString() ??
          json['govde']?.toString() ??
          payload['mesaj']?.toString() ??
          payload['govde']?.toString(),
    );

    if (title == null && message == null) {
      return null;
    }

    return AppNotification(
      id: json['id']?.toString() ?? '',
      type: _nullableString(
        json['tip']?.toString() ?? payload['tip']?.toString(),
      ),
      title: title ?? _text('notificationFallbackTitle', 'Bildirim'),
      message: message ?? '',
      route: _nullableString(
        json['rota']?.toString() ?? payload['rota']?.toString(),
      ),
      routeParameters: routeParameters,
      payload: payload,
      isRead: json['okundu_mu'] == true,
      createdAt: DateTime.tryParse(json['created_at']?.toString() ?? ''),
    );
  }

  static Map<String, dynamic>? _asMap(Object? value) {
    if (value is Map<String, dynamic>) {
      return value;
    }
    if (value is Map) {
      return value.map((key, val) => MapEntry(key.toString(), val));
    }
    return null;
  }

  static Map<String, String> _stringMap(Object? value) {
    final map = _asMap(value);
    if (map == null) {
      return const {};
    }

    return map.map((key, val) => MapEntry(key, val?.toString() ?? ''));
  }

  static String? _messagePreviewText(Map<String, dynamic>? message) {
    if (message == null) {
      return null;
    }

    final type = message['mesaj_tipi']?.toString();
    final text = ChatTextSanitizer.sanitize(message['mesaj_metni']?.toString());
    if (text != null && text.trim().isNotEmpty) {
      return text.trim();
    }

    return switch (type) {
      'foto' => _text('chatPreviewPhotoSent', 'Fotograf gonderildi'),
      'gorsel' => _text('chatPreviewPhotoSent', 'Fotograf gonderildi'),
      'ses' => _text('chatPreviewVoiceSent', 'Sesli mesaj gonderildi'),
      'video' => _text('chatPreviewVideoSent', 'Video gonderildi'),
      _ => null,
    };
  }

  static String? _nullableString(String? value) {
    final normalized = value?.trim();
    if (normalized == null || normalized.isEmpty) {
      return null;
    }
    return normalized;
  }

  static bool _boolFromValue(Object? value) {
    return switch (value) {
      final bool boolValue => boolValue,
      final num numValue => numValue != 0,
      final String stringValue =>
        stringValue.trim() == '1' || stringValue.trim().toLowerCase() == 'true',
      _ => false,
    };
  }
}

class AppHttpClientFactory {
  AppHttpClientFactory._();

  static http.Client createForApi() {
    final httpClient = HttpClient();
    httpClient.connectionTimeout = const Duration(seconds: 8);
    if (kDebugMode) {
      final allowedUri = AppApi.uri('/');
      httpClient.badCertificateCallback = (certificate, host, port) {
        return host == allowedUri.host && port == allowedUri.port;
      };
    }
    return AppHttpClient(IOClient(httpClient));
  }
}

class AppHttpClient extends http.BaseClient {
  static const Duration _requestTimeout = Duration(seconds: 18);
  static final Map<String, Future<_BufferedResponse>> _inFlightGetRequests =
      <String, Future<_BufferedResponse>>{};
  static final Map<String, String> _etagByKey = <String, String>{};
  static final Map<String, _BufferedResponse> _bodyByKey =
      <String, _BufferedResponse>{};
  static int _requestCounter = 0;

  final http.Client _inner;

  AppHttpClient(this._inner);

  @override
  Future<http.StreamedResponse> send(http.BaseRequest request) async {
    final id = ++_requestCounter;
    final startedAt = DateTime.now();
    final isGet = request.method.toUpperCase() == 'GET';
    final cacheKey = isGet ? _cacheKeyFor(request) : null;

    if (cacheKey != null) {
      final etag = _etagByKey[cacheKey];
      if (etag != null && etag.trim().isNotEmpty) {
        request.headers.putIfAbsent('If-None-Match', () => etag);
      }

      final inFlight = _inFlightGetRequests[cacheKey];
      if (inFlight != null) {
        final buffered = await inFlight;
        _debugLog(id, request, buffered.statusCode, startedAt, deduped: true);
        return buffered.toStreamedResponse(request);
      }

      final future = _sendBufferedWithRetry(request, cacheKey);
      _inFlightGetRequests[cacheKey] = future;
      try {
        final buffered = await future;
        _debugLog(id, request, buffered.statusCode, startedAt);
        return buffered.toStreamedResponse(request);
      } finally {
        if (identical(_inFlightGetRequests[cacheKey], future)) {
          _inFlightGetRequests.remove(cacheKey);
        }
      }
    }

    try {
      final response = await _inner.send(request).timeout(_requestTimeout);
      _debugLog(id, request, response.statusCode, startedAt);
      return response;
    } catch (error) {
      _debugLog(id, request, null, startedAt, error: error);
      rethrow;
    }
  }

  Future<_BufferedResponse> _sendBufferedWithRetry(
    http.BaseRequest request,
    String cacheKey,
  ) async {
    Object? lastError;
    for (var attempt = 0; attempt < 2; attempt++) {
      try {
        return await _sendBuffered(
          _cloneRequest(request),
          cacheKey,
        ).timeout(_requestTimeout);
      } on SocketException catch (error) {
        lastError = error;
      } on HandshakeException catch (error) {
        lastError = error;
      } on TimeoutException catch (error) {
        lastError = error;
      }
    }

    throw lastError ??
        ApiException(
          AppRuntimeText.instance.t(
            'apiErrorServerUnreachable',
            'Sunucuya ulasilamadi.',
          ),
        );
  }

  static http.BaseRequest _cloneRequest(http.BaseRequest request) {
    final clone = http.Request(request.method, request.url)
      ..headers.addAll(request.headers)
      ..followRedirects = request.followRedirects
      ..maxRedirects = request.maxRedirects
      ..persistentConnection = request.persistentConnection;

    if (request is http.Request) {
      clone.bodyBytes = request.bodyBytes;
    }

    return clone;
  }

  Future<_BufferedResponse> _sendBuffered(
    http.BaseRequest request,
    String cacheKey,
  ) async {
    final response = await _inner.send(request);
    final bytes = await response.stream.toBytes();
    final buffered = _BufferedResponse.fromResponse(response, bytes);

    if (buffered.statusCode == 304) {
      final cached = _bodyByKey[cacheKey];
      if (cached != null) {
        return cached.copyWith(statusCode: 200, headers: buffered.headers);
      }
    }

    final etag = buffered.headers['etag'];
    if (buffered.statusCode >= 200 &&
        buffered.statusCode < 300 &&
        etag != null &&
        etag.trim().isNotEmpty) {
      _etagByKey[cacheKey] = etag;
      _bodyByKey[cacheKey] = buffered;
    }

    return buffered;
  }

  static String _cacheKeyFor(http.BaseRequest request) {
    final auth = request.headers['Authorization'] ?? '';
    return '${request.method}:${request.url}:auth=${auth.hashCode}';
  }

  static void _debugLog(
    int id,
    http.BaseRequest request,
    int? statusCode,
    DateTime startedAt, {
    bool deduped = false,
    Object? error,
  }) {
    if (!kDebugMode) {
      return;
    }

    final duration = DateTime.now().difference(startedAt).inMilliseconds;
    final path = request.url.replace(query: '').path;
    final result = statusCode == null ? 'ERR' : statusCode.toString();
    final suffix = deduped ? ' deduped' : '';
    final errorText = error == null ? '' : ' ${error.runtimeType}';
    debugPrint(
      'HTTP#$id ${request.method} $path -> $result ${duration}ms$suffix$errorText',
    );
  }

  @override
  void close() {
    _inner.close();
    super.close();
  }
}

class _BufferedResponse {
  final int statusCode;
  final Map<String, String> headers;
  final List<int> bodyBytes;
  final int? contentLength;
  final bool isRedirect;
  final bool persistentConnection;
  final String? reasonPhrase;

  const _BufferedResponse({
    required this.statusCode,
    required this.headers,
    required this.bodyBytes,
    required this.contentLength,
    required this.isRedirect,
    required this.persistentConnection,
    required this.reasonPhrase,
  });

  factory _BufferedResponse.fromResponse(
    http.StreamedResponse response,
    List<int> bodyBytes,
  ) {
    return _BufferedResponse(
      statusCode: response.statusCode,
      headers: Map<String, String>.from(response.headers),
      bodyBytes: bodyBytes,
      contentLength: response.contentLength,
      isRedirect: response.isRedirect,
      persistentConnection: response.persistentConnection,
      reasonPhrase: response.reasonPhrase,
    );
  }

  _BufferedResponse copyWith({int? statusCode, Map<String, String>? headers}) {
    return _BufferedResponse(
      statusCode: statusCode ?? this.statusCode,
      headers: headers ?? this.headers,
      bodyBytes: bodyBytes,
      contentLength: contentLength,
      isRedirect: isRedirect,
      persistentConnection: persistentConnection,
      reasonPhrase: reasonPhrase,
    );
  }

  http.StreamedResponse toStreamedResponse(http.BaseRequest request) {
    return http.StreamedResponse(
      http.ByteStream.fromBytes(bodyBytes),
      statusCode,
      contentLength: bodyBytes.length,
      request: request,
      headers: headers,
      isRedirect: isRedirect,
      persistentConnection: persistentConnection,
      reasonPhrase: reasonPhrase,
    );
  }
}
