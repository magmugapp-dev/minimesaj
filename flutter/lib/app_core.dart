import 'dart:convert';
import 'dart:io';

import 'package:flutter/cupertino.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/gestures.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:http/http.dart' as http;
import 'package:http/io_client.dart';
import 'package:shared_preferences/shared_preferences.dart';

export 'package:flutter/cupertino.dart';
export 'package:flutter/gestures.dart';
export 'package:flutter_riverpod/flutter_riverpod.dart';

class AppColors {
  AppColors._();

  static const Color indigo = Color(0xFF5C6BFF);
  static const Color peach = Color(0xFFFDB384);
  static const Color coral = Color(0xFFFF9794);
  static const Color black = Color(0xFF111111);
  static const Color gray = Color(0xFF999999);
  static const Color grayField = Color(0xFFF5F5F7);
  static const Color grayBorder = Color(0xFFE0E0E0);
  static const Color grayProgress = Color(0xFFEFEFEF);
  static const Color white = Color(0xFFFFFFFF);
  static const Color shadow = Color(0x405C6BFF);
  static const Color neutral100 = Color(0xFFF5F5F5);
  static const Color neutral500 = Color(0xFF737373);
  static const Color neutral600 = Color(0xFF525252);
  static const Color neutral950 = Color(0xFF0A0A0A);
  static const Color zinc900 = Color(0xFF18181B);
  static const Color brandBlue = Color(0xFF1E90FF);
  static const Color onlineGreen = Color(0xFF22C55E);

  static const LinearGradient primary = LinearGradient(
    begin: Alignment(-1.0, -0.3),
    end: Alignment(1.0, 0.3),
    colors: [indigo, peach, coral],
    stops: [0.0, 0.5, 1.0],
  );
}

class AppRadius {
  AppRadius._();

  static const double field = 14;
  static const double card = 16;
  static const double pill = 999;
}

class AppFont {
  AppFont._();

  static const String family = 'PlusJakartaSans';
}

class AppEnvironment {
  AppEnvironment._();

  static const String apiBaseUrl = 'http://192.168.1.104:8000';
  static const String clientType = 'dating';
}

class SocialAuthConfig {
  SocialAuthConfig._();

  static const String googleServerClientId =
      '609071245287-k6qcdj1kpg8mulm0febnckbsvapn45uk.apps.googleusercontent.com';
}

class AppApi {
  AppApi._();

  static const String datingPhotosPath = '/api/dating/fotograflar';
  static const String datingProfilePath = '/api/dating/profil';
  static const String logoutPath = '/api/auth/cikis';
  static const String currentUserPath = '/api/auth/ben';
  static const String socialLoginPath = '/api/auth/sosyal/giris';
  static const String socialRegisterPath = '/api/auth/sosyal/kayit';

  static Uri uri(String path) {
    final normalizedPath = path.startsWith('/') ? path.substring(1) : path;
    return Uri.parse('${AppEnvironment.apiBaseUrl}/').resolve(normalizedPath);
  }
}

enum SocialAuthProvider { google, apple }

enum AuthNoticeTone { info, success, error }

@immutable
class AuthNoticeData {
  final AuthNoticeTone tone;
  final String title;
  final String message;
  final SocialAuthProvider? retryProvider;

  const AuthNoticeData({
    required this.tone,
    required this.title,
    required this.message,
    this.retryProvider,
  });
}

enum Gender { female, male }

@immutable
class OnboardData {
  final String name;
  final String surname;
  final String username;
  final int? birthYear;
  final Gender? gender;
  final String? photoPath;
  final String? socialSession;

  const OnboardData({
    this.name = '',
    this.surname = '',
    this.username = '',
    this.birthYear,
    this.gender,
    this.photoPath,
    this.socialSession,
  });

  OnboardData copyWith({
    String? name,
    String? surname,
    String? username,
    int? birthYear,
    Gender? gender,
    String? photoPath,
    bool clearPhoto = false,
    String? socialSession,
    bool clearSocialSession = false,
  }) {
    return OnboardData(
      name: name ?? this.name,
      surname: surname ?? this.surname,
      username: username ?? this.username,
      birthYear: birthYear ?? this.birthYear,
      gender: gender ?? this.gender,
      photoPath: clearPhoto ? null : (photoPath ?? this.photoPath),
      socialSession: clearSocialSession
          ? null
          : (socialSession ?? this.socialSession),
    );
  }

  bool get step1Valid =>
      name.trim().isNotEmpty &&
      surname.trim().isNotEmpty &&
      username.trim().isNotEmpty &&
      birthYear != null;

  bool get hasSocialSession =>
      socialSession != null && socialSession!.trim().isNotEmpty;

  String get fullNameForApi {
    final combined = '${name.trim()} ${surname.trim()}'.trim();
    return combined.isEmpty ? name.trim() : combined;
  }
}

class OnboardNotifier extends Notifier<OnboardData> {
  @override
  OnboardData build() => const OnboardData();

  void setName(String value) => state = state.copyWith(name: value);
  void setSurname(String value) => state = state.copyWith(surname: value);
  void setUsername(String value) => state = state.copyWith(username: value);
  void setBirthYear(int value) => state = state.copyWith(birthYear: value);
  void setGender(Gender value) => state = state.copyWith(gender: value);
  void setPhoto(String path) => state = state.copyWith(photoPath: path);
  void clearPhoto() => state = state.copyWith(clearPhoto: true);
  void clearSocialSession() => state = state.copyWith(clearSocialSession: true);

  void prefillDisplayName(String? displayName) {
    final parts = _splitDisplayName(displayName);
    state = state.copyWith(
      name: parts.$1.isEmpty ? state.name : parts.$1,
      surname: parts.$2.isEmpty ? state.surname : parts.$2,
    );
  }

  void startSocialOnboarding({
    required String socialSession,
    String? displayName,
  }) {
    final parts = _splitDisplayName(displayName);
    state = OnboardData(
      name: parts.$1,
      surname: parts.$2,
      socialSession: socialSession,
    );
  }

  void reset() => state = const OnboardData();

  (String, String) _splitDisplayName(String? displayName) {
    final normalized = (displayName ?? '').trim();
    if (normalized.isEmpty) {
      return ('', '');
    }

    final parts = normalized
        .split(RegExp(r'\s+'))
        .where((part) => part.isNotEmpty)
        .toList();

    if (parts.length == 1) {
      return (parts.first, '');
    }

    return (parts.first, parts.sublist(1).join(' '));
  }
}

final onboardProvider = NotifierProvider<OnboardNotifier, OnboardData>(
  OnboardNotifier.new,
);

class ApiException implements Exception {
  final String message;

  const ApiException(this.message);

  @override
  String toString() => message;
}

class UnauthorizedApiException extends ApiException {
  const UnauthorizedApiException(super.message);
}

@immutable
class AppUser {
  final int id;
  final String firstName;
  final String surname;
  final String username;
  final String? email;
  final String? profileImageUrl;
  final String? bio;
  final int? gemBalance;

  const AppUser({
    required this.id,
    required this.firstName,
    required this.surname,
    required this.username,
    this.email,
    this.profileImageUrl,
    this.bio,
    this.gemBalance,
  });

  String get displayName {
    final value = '$firstName $surname'.trim();
    return value.isEmpty ? username : value;
  }

  AppUser copyWith({
    int? id,
    String? firstName,
    String? surname,
    String? username,
    String? email,
    String? profileImageUrl,
    String? bio,
    int? gemBalance,
  }) {
    return AppUser(
      id: id ?? this.id,
      firstName: firstName ?? this.firstName,
      surname: surname ?? this.surname,
      username: username ?? this.username,
      email: email ?? this.email,
      profileImageUrl: profileImageUrl ?? this.profileImageUrl,
      bio: bio ?? this.bio,
      gemBalance: gemBalance ?? this.gemBalance,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'ad': firstName,
      'soyad': surname,
      'kullanici_adi': username,
      'email': email,
      'profil_resmi': profileImageUrl,
      'biyografi': bio,
      'mevcut_puan': gemBalance,
    };
  }

  factory AppUser.fromJson(Map<String, dynamic> json) {
    return AppUser(
      id: (json['id'] as num?)?.toInt() ?? 0,
      firstName: json['ad']?.toString() ?? '',
      surname: json['soyad']?.toString() ?? '',
      username: json['kullanici_adi']?.toString() ?? '',
      email: json['email']?.toString(),
      profileImageUrl: json['profil_resmi']?.toString(),
      bio: json['biyografi']?.toString(),
      gemBalance: (json['mevcut_puan'] as num?)?.toInt(),
    );
  }
}

@immutable
class AppProfilePhoto {
  final int id;
  final String url;
  final int order;
  final bool isPrimary;
  final bool isActive;

  const AppProfilePhoto({
    required this.id,
    required this.url,
    required this.order,
    required this.isPrimary,
    required this.isActive,
  });

  factory AppProfilePhoto.fromJson(Map<String, dynamic> json) {
    return AppProfilePhoto(
      id: (json['id'] as num?)?.toInt() ?? 0,
      url: json['dosya_yolu']?.toString() ?? '',
      order: (json['sira_no'] as num?)?.toInt() ?? 0,
      isPrimary: json['ana_fotograf_mi'] == true,
      isActive: json['aktif_mi'] != false,
    );
  }
}

@immutable
class AuthenticatedSession {
  final String token;
  final AppUser? user;

  const AuthenticatedSession({required this.token, this.user});
}

@immutable
class AppAuthState {
  final String token;
  final AppUser? user;

  const AppAuthState({required this.token, this.user});

  AuthenticatedSession toSession() {
    return AuthenticatedSession(token: token, user: user);
  }
}

@immutable
class SocialAuthPrefill {
  final SocialAuthProvider provider;
  final String? displayName;
  final String? email;
  final String? avatarUrl;

  const SocialAuthPrefill({
    required this.provider,
    this.displayName,
    this.email,
    this.avatarUrl,
  });

  factory SocialAuthPrefill.fromJson(Map<String, dynamic> json) {
    final providerValue = (json['provider'] ?? '').toString().toLowerCase();
    final provider = providerValue == 'apple'
        ? SocialAuthProvider.apple
        : SocialAuthProvider.google;

    return SocialAuthPrefill(
      provider: provider,
      displayName: json['ad']?.toString(),
      email: json['email']?.toString(),
      avatarUrl: json['avatar_url']?.toString(),
    );
  }
}

enum SocialAuthResultStatus { authenticated, onboardingRequired }

@immutable
class SocialAuthLoginResult {
  final SocialAuthResultStatus status;
  final AuthenticatedSession? session;
  final String? socialSession;
  final SocialAuthPrefill? prefill;

  const SocialAuthLoginResult.authenticated(this.session)
    : status = SocialAuthResultStatus.authenticated,
      socialSession = null,
      prefill = null;

  const SocialAuthLoginResult.onboardingRequired({
    required this.socialSession,
    required this.prefill,
  }) : status = SocialAuthResultStatus.onboardingRequired,
       session = null;
}

class AppSessionStorage {
  AppSessionStorage._();

  static const String _tokenKey = 'auth.token';
  static const String _userKey = 'auth.user';

  static Future<void> saveSession(AuthenticatedSession session) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_tokenKey, session.token);
    if (session.user != null) {
      await prefs.setString(_userKey, jsonEncode(session.user!.toJson()));
    } else {
      await prefs.remove(_userKey);
    }
  }

  static Future<AuthenticatedSession?> readSession() async {
    final prefs = await SharedPreferences.getInstance();
    final token = prefs.getString(_tokenKey);
    if (token == null || token.trim().isEmpty) {
      return null;
    }

    final rawUser = prefs.getString(_userKey);
    AppUser? user;
    if (rawUser != null && rawUser.trim().isNotEmpty) {
      final decoded = jsonDecode(rawUser);
      if (decoded is Map<String, dynamic>) {
        user = AppUser.fromJson(decoded);
      } else if (decoded is Map) {
        user = AppUser.fromJson(
          decoded.map((key, value) => MapEntry(key.toString(), value)),
        );
      }
    }

    return AuthenticatedSession(token: token, user: user);
  }

  static Future<String?> readToken() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString(_tokenKey);
  }

  static Future<void> clear() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_tokenKey);
    await prefs.remove(_userKey);
  }
}

class AppAuthApi {
  final http.Client _client;

  AppAuthApi({http.Client? client})
    : _client = client ?? AppHttpClientFactory.createForApi();

  Future<SocialAuthLoginResult> socialLogin({
    required SocialAuthProvider provider,
    required String token,
    String? firstName,
    String? lastName,
    String? avatarUrl,
  }) async {
    final response = await _client.post(
      AppApi.uri(AppApi.socialLoginPath),
      headers: const {'Accept': 'application/json'},
      body: {
        'provider': provider.name,
        'token': token,
        'istemci_tipi': AppEnvironment.clientType,
        if (firstName != null && firstName.trim().isNotEmpty)
          'ad': firstName.trim(),
        if (lastName != null && lastName.trim().isNotEmpty)
          'soyad': lastName.trim(),
        if (avatarUrl != null && avatarUrl.trim().isNotEmpty)
          'avatar_url': avatarUrl.trim(),
      },
    );

    final payload = _decodeJsonMap(response);

    if (response.statusCode >= 400) {
      throw ApiException(_extractErrorMessage(payload));
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
    request.headers['Accept'] = 'application/json';
    request.fields.addAll({
      'social_session': data.socialSession!.trim(),
      'ad': data.fullNameForApi,
      'kullanici_adi': data.username.trim(),
      'cinsiyet': _genderValue(data.gender),
      'dogum_yili': '${data.birthYear}',
    });

    if (data.photoPath != null && data.photoPath!.trim().isNotEmpty) {
      request.files.add(
        await http.MultipartFile.fromPath('dosya', data.photoPath!.trim()),
      );
    }

    final streamedResponse = await _client.send(request);
    final response = await http.Response.fromStream(streamedResponse);
    final payload = _decodeJsonMap(response);

    if (response.statusCode >= 400) {
      throw ApiException(_extractErrorMessage(payload));
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

  Future<AppUser> updateProfile(
    String token, {
    required String firstName,
    String? surname,
    String? bio,
  }) async {
    final response = await _client.patch(
      AppApi.uri(AppApi.datingProfilePath),
      headers: {
        'Accept': 'application/json',
        'Authorization': 'Bearer $token',
        'Content-Type': 'application/json',
      },
      body: jsonEncode({
        'ad': firstName.trim(),
        'soyad': _nullableString(surname),
        'biyografi': _nullableString(bio),
      }),
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

  Future<AppProfilePhoto> uploadProfilePhoto(
    String token, {
    required String filePath,
    bool markAsPrimary = false,
  }) async {
    final request = http.MultipartRequest(
      'POST',
      AppApi.uri(AppApi.datingPhotosPath),
    );
    request.headers.addAll({
      'Accept': 'application/json',
      'Authorization': 'Bearer $token',
    });
    request.fields['ana_fotograf_mi'] = markAsPrimary ? '1' : '0';
    request.files.add(await http.MultipartFile.fromPath('dosya', filePath));

    final streamedResponse = await _client.send(request);
    final response = await http.Response.fromStream(streamedResponse);
    final payload = _decodeJsonMap(response);

    if (response.statusCode == 401 || response.statusCode == 403) {
      throw const UnauthorizedApiException('Oturum suresi doldu.');
    }
    if (response.statusCode >= 400) {
      throw ApiException(_extractErrorMessage(payload));
    }

    return AppProfilePhoto.fromJson(_unwrapDataMap(payload));
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

  void close() {
    _client.close();
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

  static String? _nullableString(String? value) {
    final normalized = value?.trim();
    if (normalized == null || normalized.isEmpty) {
      return null;
    }
    return normalized;
  }
}

class AppAuthController extends AsyncNotifier<AppAuthState?> {
  late final AppAuthApi _authApi = AppAuthApi();

  @override
  Future<AppAuthState?> build() async {
    final savedSession = await AppSessionStorage.readSession();
    if (savedSession == null) {
      return null;
    }

    try {
      final user = await _authApi.fetchCurrentUser(savedSession.token);
      final next = AppAuthState(token: savedSession.token, user: user);
      await AppSessionStorage.saveSession(next.toSession());
      return next;
    } on UnauthorizedApiException {
      await AppSessionStorage.clear();
      return null;
    } on SocketException {
      return AppAuthState(token: savedSession.token, user: savedSession.user);
    } on HandshakeException {
      return AppAuthState(token: savedSession.token, user: savedSession.user);
    } catch (_) {
      return AppAuthState(token: savedSession.token, user: savedSession.user);
    }
  }

  Future<void> setAuthenticatedSession(AuthenticatedSession session) async {
    final next = AppAuthState(token: session.token, user: session.user);
    state = AsyncData(next);
    await AppSessionStorage.saveSession(session);
  }

  Future<void> updateProfile({
    required String firstName,
    String? surname,
    String? bio,
  }) async {
    final current = state.asData?.value;
    if (current == null || current.token.trim().isEmpty) {
      throw const ApiException('Aktif oturum bulunamadi.');
    }

    final user = await _authApi.updateProfile(
      current.token,
      firstName: firstName,
      surname: surname,
      bio: bio,
    );
    final next = AppAuthState(token: current.token, user: user);
    state = AsyncData(next);
    await AppSessionStorage.saveSession(next.toSession());
  }

  Future<void> refreshCurrentUser() async {
    final current = state.asData?.value;
    if (current == null) return;

    state = const AsyncLoading();
    state = await AsyncValue.guard(() async {
      final user = await _authApi.fetchCurrentUser(current.token);
      final next = AppAuthState(token: current.token, user: user);
      await AppSessionStorage.saveSession(next.toSession());
      return next;
    });
  }

  Future<void> signOut() async {
    final current = state.asData?.value;

    if (current != null && current.token.trim().isNotEmpty) {
      try {
        await _authApi.logout(current.token);
      } on UnauthorizedApiException {
      } on SocketException {
      } on HandshakeException {
      } on ApiException {}
    }

    await AppSessionStorage.clear();
    state = const AsyncData(null);
  }
}

final appAuthProvider = AsyncNotifierProvider<AppAuthController, AppAuthState?>(
  AppAuthController.new,
);

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

class AppAuthErrorFormatter {
  AppAuthErrorFormatter._();

  static String messageFrom(Object error, {SocialAuthProvider? provider}) {
    if (error is ApiException) {
      return error.message;
    }

    if (error is PlatformException) {
      return _platformMessage(error, provider);
    }

    if (error is HandshakeException) {
      return 'HTTPS baglantisi reddedildi. Cihazin ${AppEnvironment.apiBaseUrl} adresindeki sertifikaya guvendigini kontrol et.';
    }

    if (error is SocketException) {
      return 'Sunucuya ulasilamadi. Telefonun ile bilgisayarin ayni agda oldugunu ve ${AppEnvironment.apiBaseUrl} adresinin cihazdan acildigini kontrol et.';
    }

    if (error is HttpException) {
      return error.message;
    }

    return 'Beklenmeyen bir hata olustu. Tekrar deneyebilirsin.';
  }

  static String _platformMessage(
    PlatformException error,
    SocialAuthProvider? provider,
  ) {
    final normalized =
        '${error.code} ${error.message ?? ''} ${error.details ?? ''}'
            .toLowerCase();

    if (normalized.contains('canceled') ||
        normalized.contains('cancelled') ||
        normalized.contains('12501') ||
        normalized.contains('sign_in_canceled')) {
      return '${_providerLabel(provider)} girisi iptal edildi.';
    }

    if (normalized.contains('network_error') || normalized.contains('7:')) {
      return '${_providerLabel(provider)} servisine ulasilamadi. Internet baglantisini ve cihazdaki Google Play Servisleri durumunu kontrol et.';
    }

    if (normalized.contains('developer_error') ||
        normalized.contains('10:') ||
        normalized.contains('sign_in_failed')) {
      return 'Google girisi dogrulanamadi. Flutter uygulamasindaki Google Sign-In ayari, OAuth istemci kimligi veya cihazdaki Google hesap baglantisi problemli olabilir.';
    }

    if (provider == SocialAuthProvider.apple) {
      return error.message ?? 'Apple ile giris baslatilamadi.';
    }

    return error.message ??
        '${_providerLabel(provider)} ile giris baslatilamadi.';
  }

  static String _providerLabel(SocialAuthProvider? provider) {
    return switch (provider) {
      SocialAuthProvider.google => 'Google',
      SocialAuthProvider.apple => 'Apple',
      null => 'Sosyal giris',
    };
  }
}

class PressableScale extends StatefulWidget {
  final Widget child;
  final VoidCallback? onTap;
  final double scale;

  const PressableScale({required this.child, this.onTap, this.scale = 0.97});

  @override
  State<PressableScale> createState() => _PressableScaleState();
}

class _PressableScaleState extends State<PressableScale> {
  bool _pressed = false;

  void _set(bool value) {
    if (!mounted) return;
    setState(() => _pressed = value);
  }

  @override
  Widget build(BuildContext context) {
    final enabled = widget.onTap != null;
    return GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTapDown: enabled ? (_) => _set(true) : null,
      onTapCancel: enabled ? () => _set(false) : null,
      onTapUp: enabled ? (_) => _set(false) : null,
      onTap: widget.onTap,
      child: AnimatedScale(
        scale: _pressed ? widget.scale : 1.0,
        duration: const Duration(milliseconds: 120),
        curve: Curves.easeOut,
        child: AnimatedOpacity(
          duration: const Duration(milliseconds: 120),
          opacity: enabled ? 1.0 : 0.55,
          child: widget.child,
        ),
      ),
    );
  }
}

class GradientButton extends StatelessWidget {
  final String label;
  final VoidCallback? onTap;

  const GradientButton({required this.label, this.onTap});

  @override
  Widget build(BuildContext context) {
    return PressableScale(
      onTap: onTap,
      child: Container(
        height: 55,
        decoration: BoxDecoration(
          gradient: AppColors.primary,
          borderRadius: BorderRadius.circular(AppRadius.pill),
          boxShadow: const [
            BoxShadow(
              color: AppColors.shadow,
              blurRadius: 24,
              offset: Offset(0, 8),
            ),
          ],
        ),
        alignment: Alignment.center,
        child: Text(
          label,
          style: const TextStyle(
            fontFamily: AppFont.family,
            fontWeight: FontWeight.w800,
            fontSize: 16,
            color: AppColors.white,
          ),
        ),
      ),
    );
  }
}

class SecondaryButton extends StatelessWidget {
  final String label;
  final VoidCallback? onTap;

  const SecondaryButton({required this.label, this.onTap});

  @override
  Widget build(BuildContext context) {
    return PressableScale(
      onTap: onTap,
      child: Container(
        height: 55,
        decoration: BoxDecoration(
          color: AppColors.grayField,
          borderRadius: BorderRadius.circular(AppRadius.pill),
        ),
        alignment: Alignment.center,
        child: Text(
          label,
          style: const TextStyle(
            fontFamily: AppFont.family,
            fontWeight: FontWeight.w600,
            fontSize: 16,
            color: AppColors.black,
          ),
        ),
      ),
    );
  }
}

class AuthButton extends StatelessWidget {
  final String label;
  final String iconAsset;
  final Color background;
  final Color labelColor;
  final VoidCallback? onTap;
  final bool elevated;
  final bool enabled;
  final bool loading;

  const AuthButton({
    required this.label,
    required this.iconAsset,
    required this.background,
    required this.labelColor,
    this.onTap,
    this.elevated = false,
    this.enabled = true,
    this.loading = false,
  });

  @override
  Widget build(BuildContext context) {
    final canTap = enabled && !loading && onTap != null;

    return PressableScale(
      onTap: canTap ? onTap : null,
      child: Container(
        height: 52,
        decoration: BoxDecoration(
          color: background,
          borderRadius: BorderRadius.circular(AppRadius.pill),
          border: background == AppColors.white
              ? Border.all(color: const Color(0xFFF0F0F0))
              : null,
          boxShadow: elevated
              ? const [
                  BoxShadow(
                    color: AppColors.shadow,
                    blurRadius: 20,
                    offset: Offset(0, 4),
                  ),
                ]
              : null,
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            if (loading)
              const SizedBox(
                width: 20,
                height: 20,
                child: CupertinoActivityIndicator(radius: 10),
              )
            else
              Image.asset(iconAsset, width: 20, height: 20),
            const SizedBox(width: 12),
            Text(
              label,
              style: TextStyle(
                fontFamily: AppFont.family,
                fontWeight: FontWeight.w600,
                fontSize: 15,
                color: labelColor,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class AuthNoticeCard extends StatelessWidget {
  final AuthNoticeData data;
  final VoidCallback onDismiss;
  final VoidCallback? onRetry;

  const AuthNoticeCard({
    required this.data,
    required this.onDismiss,
    this.onRetry,
  });

  @override
  Widget build(BuildContext context) {
    final palette = switch (data.tone) {
      AuthNoticeTone.info => (
        background: const Color(0xFFF2F7FF),
        border: const Color(0xFFD6E7FF),
        icon: CupertinoIcons.info_circle_fill,
        iconColor: AppColors.brandBlue,
      ),
      AuthNoticeTone.success => (
        background: const Color(0xFFF1FBF5),
        border: const Color(0xFFD1F0DB),
        icon: CupertinoIcons.check_mark_circled_solid,
        iconColor: AppColors.onlineGreen,
      ),
      AuthNoticeTone.error => (
        background: const Color(0xFFFFF4F4),
        border: const Color(0xFFF4D3D3),
        icon: CupertinoIcons.exclamationmark_circle_fill,
        iconColor: AppColors.coral,
      ),
    };

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: palette.background,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: palette.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(palette.icon, size: 20, color: palette.iconColor),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      data.title,
                      style: const TextStyle(
                        fontFamily: AppFont.family,
                        fontWeight: FontWeight.w700,
                        fontSize: 14,
                        color: AppColors.black,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      data.message,
                      style: const TextStyle(
                        fontFamily: AppFont.family,
                        fontSize: 12.5,
                        height: 1.45,
                        color: AppColors.neutral600,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              PressableScale(
                onTap: onDismiss,
                scale: 0.92,
                child: const Icon(
                  CupertinoIcons.xmark,
                  size: 16,
                  color: AppColors.neutral500,
                ),
              ),
            ],
          ),
          if (onRetry != null) ...[
            const SizedBox(height: 12),
            PressableScale(
              onTap: onRetry,
              child: Container(
                height: 38,
                padding: const EdgeInsets.symmetric(horizontal: 14),
                decoration: BoxDecoration(
                  color: AppColors.white,
                  borderRadius: BorderRadius.circular(AppRadius.pill),
                  border: Border.all(color: palette.border),
                ),
                alignment: Alignment.center,
                child: const Text(
                  'Tekrar dene',
                  style: TextStyle(
                    fontFamily: AppFont.family,
                    fontWeight: FontWeight.w700,
                    fontSize: 13,
                    color: AppColors.black,
                  ),
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class StepProgressBar extends StatelessWidget {
  final int currentStep;

  const StepProgressBar({required this.currentStep});

  static const int _totalSteps = 4;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: List.generate(_totalSteps, (index) {
        final active = index < currentStep;
        return Expanded(
          child: Padding(
            padding: EdgeInsets.only(right: index == _totalSteps - 1 ? 0 : 6),
            child: _StepBarSegment(active: active, delay: index * 70),
          ),
        );
      }),
    );
  }
}

class _StepBarSegment extends StatelessWidget {
  final bool active;
  final int delay;

  const _StepBarSegment({required this.active, required this.delay});

  @override
  Widget build(BuildContext context) {
    return TweenAnimationBuilder<double>(
      tween: Tween(begin: 0, end: active ? 1 : 0),
      duration: Duration(milliseconds: 450 + delay),
      curve: Curves.easeOutCubic,
      builder: (context, value, _) {
        return Stack(
          children: [
            Container(
              height: 4,
              decoration: BoxDecoration(
                color: AppColors.grayProgress,
                borderRadius: BorderRadius.circular(2),
              ),
            ),
            FractionallySizedBox(
              widthFactor: value,
              child: Container(
                height: 4,
                decoration: BoxDecoration(
                  color: AppColors.indigo,
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
            ),
          ],
        );
      },
    );
  }
}

class CircleBackButton extends StatelessWidget {
  final VoidCallback? onTap;
  final bool filled;

  const CircleBackButton({this.onTap, this.filled = false});

  @override
  Widget build(BuildContext context) {
    final bg = filled ? AppColors.grayField : AppColors.white;
    return PressableScale(
      onTap: onTap ?? () => Navigator.of(context).maybePop(),
      child: Container(
        width: 40,
        height: 40,
        decoration: BoxDecoration(
          color: bg,
          shape: BoxShape.circle,
          boxShadow: filled
              ? null
              : const [
                  BoxShadow(
                    color: Color(0x0F000000),
                    blurRadius: 8,
                    offset: Offset(0, 2),
                  ),
                ],
        ),
        alignment: Alignment.center,
        child: const Icon(
          CupertinoIcons.chevron_back,
          size: 20,
          color: AppColors.black,
        ),
      ),
    );
  }
}

class LabeledField extends StatefulWidget {
  final String label;
  final String? initialValue;
  final String? placeholder;
  final void Function(String) onChanged;
  final TextCapitalization capitalization;

  const LabeledField({
    required this.label,
    required this.onChanged,
    this.initialValue,
    this.placeholder,
    this.capitalization = TextCapitalization.words,
  });

  @override
  State<LabeledField> createState() => _LabeledFieldState();
}

class _LabeledFieldState extends State<LabeledField> {
  late final TextEditingController _controller;
  final FocusNode _focus = FocusNode();
  bool _focused = false;

  @override
  void initState() {
    super.initState();
    _controller = TextEditingController(text: widget.initialValue ?? '');
    _focus.addListener(() {
      if (!mounted) return;
      setState(() => _focused = _focus.hasFocus);
    });
  }

  @override
  void dispose() {
    _controller.dispose();
    _focus.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          widget.label,
          style: const TextStyle(
            fontFamily: AppFont.family,
            fontWeight: FontWeight.w600,
            fontSize: 12,
            letterSpacing: 1.0,
            color: AppColors.gray,
          ),
        ),
        const SizedBox(height: 8),
        AnimatedContainer(
          duration: const Duration(milliseconds: 200),
          curve: Curves.easeOut,
          height: 54,
          decoration: BoxDecoration(
            color: AppColors.grayField,
            borderRadius: BorderRadius.circular(AppRadius.field),
            border: Border.all(
              color: _focused ? AppColors.indigo : const Color(0x00000000),
              width: 1.6,
            ),
          ),
          child: CupertinoTextField(
            controller: _controller,
            focusNode: _focus,
            onChanged: widget.onChanged,
            textCapitalization: widget.capitalization,
            placeholder: widget.placeholder,
            placeholderStyle: const TextStyle(
              fontFamily: AppFont.family,
              color: AppColors.gray,
              fontSize: 16,
              fontWeight: FontWeight.w500,
            ),
            style: const TextStyle(
              fontFamily: AppFont.family,
              color: AppColors.black,
              fontSize: 16,
              fontWeight: FontWeight.w600,
            ),
            cursorColor: AppColors.indigo,
            decoration: const BoxDecoration(color: Color(0x00000000)),
            padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 16),
          ),
        ),
      ],
    );
  }
}

class GenderOption extends StatelessWidget {
  final String label;
  final String iconAsset;
  final Color iconBackground;
  final bool selected;
  final VoidCallback onTap;

  const GenderOption({
    required this.label,
    required this.iconAsset,
    required this.iconBackground,
    required this.selected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return PressableScale(
      onTap: onTap,
      scale: 0.98,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        curve: Curves.easeOut,
        height: 88,
        decoration: BoxDecoration(
          color: AppColors.grayField,
          borderRadius: BorderRadius.circular(AppRadius.card),
          border: Border.all(
            color: selected ? AppColors.indigo : const Color(0x00000000),
            width: 2,
          ),
        ),
        padding: const EdgeInsets.symmetric(horizontal: 20),
        child: Row(
          children: [
            Container(
              width: 48,
              height: 48,
              decoration: BoxDecoration(
                color: iconBackground,
                borderRadius: BorderRadius.circular(14),
              ),
              alignment: Alignment.center,
              child: Image.asset(iconAsset, width: 24, height: 24),
            ),
            const SizedBox(width: 16),
            Text(
              label,
              style: const TextStyle(
                fontFamily: AppFont.family,
                fontWeight: FontWeight.w700,
                fontSize: 16,
                color: AppColors.black,
              ),
            ),
            const Spacer(),
            AnimatedSwitcher(
              duration: const Duration(milliseconds: 200),
              switchInCurve: Curves.easeOut,
              transitionBuilder: (child, animation) {
                return ScaleTransition(
                  scale: animation,
                  child: FadeTransition(opacity: animation, child: child),
                );
              },
              child: selected
                  ? Container(
                      key: const ValueKey('on'),
                      width: 24,
                      height: 24,
                      decoration: const BoxDecoration(
                        color: AppColors.indigo,
                        shape: BoxShape.circle,
                      ),
                      alignment: Alignment.center,
                      child: const Icon(
                        CupertinoIcons.check_mark,
                        size: 14,
                        color: AppColors.white,
                      ),
                    )
                  : Container(
                      key: const ValueKey('off'),
                      width: 24,
                      height: 24,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        border: Border.all(
                          color: AppColors.grayBorder,
                          width: 2,
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

Route<T> cupertinoRoute<T>(Widget page, {String? name}) {
  return CupertinoPageRoute<T>(
    builder: (_) => page,
    settings: RouteSettings(name: name),
  );
}

String formatGem(int value) {
  final text = value.toString();
  final buffer = StringBuffer();
  for (var index = 0; index < text.length; index++) {
    if (index > 0 && (text.length - index) % 3 == 0) {
      buffer.write('.');
    }
    buffer.write(text[index]);
  }
  return buffer.toString();
}

class BalanceChip extends StatelessWidget {
  final int amount;

  const BalanceChip({super.key, required this.amount});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(20),
        boxShadow: const [
          BoxShadow(
            color: Color(0x14000000),
            blurRadius: 8,
            offset: Offset(0, 2),
          ),
        ],
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Image.asset('assets/images/icon_diamond.png', width: 16, height: 16),
          const SizedBox(width: 8),
          Text(
            formatGem(amount),
            style: const TextStyle(
              fontFamily: AppFont.family,
              fontWeight: FontWeight.w800,
              fontSize: 13,
              color: AppColors.zinc900,
            ),
          ),
        ],
      ),
    );
  }
}

class AvatarCircle extends StatelessWidget {
  final String name;
  final double size;
  final bool online;

  const AvatarCircle({
    super.key,
    required this.name,
    this.size = 52,
    this.online = false,
  });

  @override
  Widget build(BuildContext context) {
    final base = avatarColorForName(name);
    return SizedBox(
      width: size,
      height: size,
      child: Stack(
        children: [
          Container(
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
              initialsOf(name),
              style: TextStyle(
                fontFamily: AppFont.family,
                fontWeight: FontWeight.w700,
                fontSize: size * 0.36,
                color: AppColors.white,
                letterSpacing: 0.2,
              ),
            ),
          ),
          if (online)
            Positioned(
              right: 1,
              bottom: 1,
              child: Container(
                width: 12,
                height: 12,
                decoration: BoxDecoration(
                  color: AppColors.onlineGreen,
                  shape: BoxShape.circle,
                  border: Border.all(color: AppColors.neutral100, width: 2),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

Color avatarColorForName(String name) {
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

String initialsOf(String fullName) {
  final parts = fullName
      .trim()
      .split(RegExp(r'\s+'))
      .where((part) => part.isNotEmpty)
      .toList();
  if (parts.isEmpty) return '?';
  if (parts.length == 1) {
    final value = parts.first;
    return value.substring(0, value.length >= 2 ? 2 : 1).toUpperCase();
  }
  return (parts.first.substring(0, 1) + parts.last.substring(0, 1))
      .toUpperCase();
}
