import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:math' as math;

import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import 'package:http/io_client.dart';
import 'package:http_parser/http_parser.dart';
import 'package:magmug/core/config/app_config.dart';
import 'package:magmug/core/models/auth_models.dart';
import 'package:magmug/core/models/communication_models.dart';
import 'package:magmug/core/models/match_models.dart';
import 'package:magmug/core/models/payment_models.dart';
import 'package:magmug/core/models/public_settings_models.dart';
import 'package:magmug/core/models/user_models.dart';
import 'package:magmug/core/network/app_api.dart';

typedef UploadProgressCallback = void Function(double progress);

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
      throw const ApiException('Kullanici adi bos birakilamaz.');
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
      throw const ApiException('Sosyal oturum bilgisi bulunamadi.');
    }

    if (!data.step1Valid || data.gender == null) {
      throw const ApiException('Onboarding alanlari tamamlanmadi.');
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
      throw const UnauthorizedApiException('Oturum suresi doldu.');
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
      throw const UnauthorizedApiException('Oturum suresi doldu.');
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
      throw const UnauthorizedApiException('Oturum suresi doldu.');
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
      throw const UnauthorizedApiException('Oturum suresi doldu.');
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
      throw const UnauthorizedApiException('Oturum suresi doldu.');
    }
    if (response.statusCode >= 400) {
      throw ApiException(_extractErrorMessage(payload));
    }
  }

  Future<AppMatchCenterSummary> fetchMatchCenter(String token) async {
    final response = await _client.get(
      AppApi.uri(AppApi.matchCenterPath),
      headers: {'Accept': 'application/json', 'Authorization': 'Bearer $token'},
    );

    final payload = _decodeJsonMap(response);
    if (response.statusCode == 401 || response.statusCode == 403) {
      throw const UnauthorizedApiException('Oturum suresi doldu.');
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
      throw const UnauthorizedApiException('Oturum suresi doldu.');
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
      throw const UnauthorizedApiException('Oturum suresi doldu.');
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
      throw const UnauthorizedApiException('Oturum suresi doldu.');
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
      throw const UnauthorizedApiException('Oturum suresi doldu.');
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
      throw const UnauthorizedApiException('Oturum suresi doldu.');
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
      throw const UnauthorizedApiException('Oturum suresi doldu.');
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
      throw const ApiException('Guncellenecek alan bulunamadi.');
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
      throw const UnauthorizedApiException('Oturum suresi doldu.');
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
      throw const UnauthorizedApiException('Oturum suresi doldu.');
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
      throw const UnauthorizedApiException('Oturum suresi doldu.');
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
      throw const ApiException('Guncellenecek medya alani bulunamadi.');
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
      throw const UnauthorizedApiException('Oturum suresi doldu.');
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
      throw const UnauthorizedApiException('Oturum suresi doldu.');
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
      throw const UnauthorizedApiException('Oturum suresi doldu.');
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

  Future<AppConversationMessage> sendConversationMessage(
    String token, {
    required int conversationId,
    required String text,
  }) async {
    final response = await _client.post(
      AppApi.uri(AppApi.datingChatMessagesPath(conversationId)),
      headers: {
        'Accept': 'application/json',
        'Authorization': 'Bearer $token',
        'Content-Type': 'application/json',
      },
      body: jsonEncode({'mesaj_tipi': 'metin', 'mesaj_metni': text.trim()}),
    );

    final payload = _decodeJsonMap(response);
    if (response.statusCode == 401 || response.statusCode == 403) {
      throw const UnauthorizedApiException('Oturum suresi doldu.');
    }
    if (response.statusCode >= 400) {
      throw _extractApiException(payload);
    }

    final message = _conversationMessageFromJson(_unwrapDataMap(payload));
    if (message == null) {
      throw const ApiException('Mesaj gonderildi ama sunucu yaniti okunamadi.');
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
      throw const UnauthorizedApiException('Oturum suresi doldu.');
    }
    if (response.statusCode >= 400) {
      throw _extractApiException(payload);
    }

    final translation = _asMap(payload['ceviri']);
    final translatedText = _nullableString(
      translation?['metin']?.toString() ?? payload['ceviri_metni']?.toString(),
    );
    if (translatedText == null) {
      throw const ApiException('Ceviri alindi ama metin okunamadi.');
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
      throw const UnauthorizedApiException('Oturum suresi doldu.');
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
      throw const UnauthorizedApiException('Oturum suresi doldu.');
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
      throw const UnauthorizedApiException('Oturum suresi doldu.');
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
      throw const UnauthorizedApiException('Oturum suresi doldu.');
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
      throw const UnauthorizedApiException('Oturum suresi doldu.');
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
      throw const UnauthorizedApiException('Oturum suresi doldu.');
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
      throw const UnauthorizedApiException('Oturum suresi doldu.');
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
      throw const UnauthorizedApiException('Oturum suresi doldu.');
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
      throw const UnauthorizedApiException('Oturum suresi doldu.');
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
      throw const UnauthorizedApiException('Oturum suresi doldu.');
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
      throw const UnauthorizedApiException('Oturum suresi doldu.');
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
      throw const UnauthorizedApiException('Oturum suresi doldu.');
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
      throw const UnauthorizedApiException('Oturum suresi doldu.');
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
      throw const UnauthorizedApiException('Oturum suresi doldu.');
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
      throw const UnauthorizedApiException('Oturum suresi doldu.');
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
      }),
    );

    final payload = _decodeJsonMap(response);
    if (response.statusCode == 401 || response.statusCode == 403) {
      throw const UnauthorizedApiException('Oturum suresi doldu.');
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
      throw const UnauthorizedApiException('Oturum suresi doldu.');
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
      throw const UnauthorizedApiException('Oturum suresi doldu.');
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
      throw const UnauthorizedApiException('Oturum zaten sonlanmis.');
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
      throw const UnauthorizedApiException('Oturum zaten sonlanmis.');
    }

    if (response.statusCode >= 400) {
      final payload = _decodeJsonMap(response);
      throw ApiException(_extractErrorMessage(payload));
    }
  }

  void close() {
    _client.close();
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

    return 'Sunucu ile baglanti kurulurken bir hata olustu.';
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
      peerName: peer['ad']?.toString() ?? 'Kullanici',
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

  static AppConversationMessage? _conversationMessageFromJson(
    Map<String, dynamic> json,
  ) {
    final sender = _asMap(json['gonderen']);
    final durationInSeconds = (json['dosya_suresi'] as num?)?.toInt();
    return AppConversationMessage(
      id: (json['id'] as num?)?.toInt() ?? 0,
      conversationId: (json['sohbet_id'] as num?)?.toInt() ?? 0,
      senderId: (sender?['id'] as num?)?.toInt(),
      senderName: sender?['ad']?.toString() ?? 'Kullanici',
      senderProfileImageUrl: sender?['profil_resmi']?.toString(),
      type: json['mesaj_tipi']?.toString() ?? 'metin',
      text: _sanitizeChatText(json['mesaj_metni']?.toString()),
      fileUrl: _nullableString(json['dosya_yolu']?.toString()),
      fileDuration: durationInSeconds == null
          ? null
          : Duration(seconds: durationInSeconds),
      isRead: json['okundu_mu'] == true,
      isAiGenerated: json['ai_tarafindan_uretildi_mi'] == true,
      languageCode: _nullableString(json['dil_kodu']?.toString()),
      languageName: _nullableString(json['dil_adi']?.toString()),
      translatedText: null,
      translationTargetLanguageCode: null,
      translationTargetLanguageName: null,
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
      title: title ?? 'Bildirim',
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
    final text = _sanitizeChatText(message['mesaj_metni']?.toString());
    if (text != null && text.trim().isNotEmpty) {
      return text.trim();
    }

    return switch (type) {
      'foto' => 'Fotograf gonderildi',
      'gorsel' => 'Fotograf gonderildi',
      'ses' => 'Sesli mesaj gonderildi',
      'video' => 'Video gonderildi',
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

  static String? _sanitizeChatText(String? value) {
    final normalized = _nullableString(value);
    if (normalized == null) {
      return null;
    }

    final fenceMatch = RegExp(
      r'^```(?:json)?\s*(.*?)\s*```$',
      caseSensitive: false,
      dotAll: true,
    ).firstMatch(normalized);
    final candidate = _nullableString(fenceMatch?.group(1)) ?? normalized;
    final extracted = _extractEnvelopeText(candidate);

    return _nullableString(extracted ?? candidate);
  }

  static String? _extractEnvelopeText(String text) {
    final normalized = _nullableString(text);
    if (normalized == null) {
      return null;
    }
    if (!normalized.startsWith('{') && !normalized.startsWith('[')) {
      return normalized;
    }

    try {
      final decoded = jsonDecode(normalized);
      final extracted = _extractEnvelopeValue(decoded);
      if (extracted == null) {
        return normalized;
      }

      return _sanitizeChatText(extracted);
    } catch (_) {
      return normalized;
    }
  }

  static String? _extractEnvelopeValue(Object? value) {
    if (value is String) {
      return value;
    }

    if (value is Map) {
      for (final key in const [
        'reply',
        'cevap',
        'text',
        'message',
        'content',
        'mesaj',
      ]) {
        final candidate = _extractEnvelopeValue(value[key]);
        if (candidate != null && candidate.trim().isNotEmpty) {
          return candidate;
        }
      }

      final parts = value['parts'];
      if (parts is List) {
        for (final part in parts) {
          final candidate = _extractEnvelopeValue(part);
          if (candidate != null && candidate.trim().isNotEmpty) {
            return candidate;
          }
        }
      }

      final candidates = value['candidates'];
      if (candidates is List) {
        for (final item in candidates) {
          final candidate = _extractEnvelopeValue(item);
          if (candidate != null && candidate.trim().isNotEmpty) {
            return candidate;
          }
        }
      }
    }

    if (value is List) {
      for (final item in value) {
        final candidate = _extractEnvelopeValue(item);
        if (candidate != null && candidate.trim().isNotEmpty) {
          return candidate;
        }
      }
    }

    return null;
  }
}

class AppHttpClientFactory {
  AppHttpClientFactory._();

  static http.Client createForApi() {
    final httpClient = HttpClient();
    if (kDebugMode) {
      final allowedUri = AppApi.uri('/');
      httpClient.badCertificateCallback = (certificate, host, port) {
        return host == allowedUri.host && port == allowedUri.port;
      };
    }
    return IOClient(httpClient);
  }
}
