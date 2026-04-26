import 'package:flutter/cupertino.dart';
import 'package:magmug/core/models/auth_models.dart';

String? _nullableString(String? value) {
  final normalized = value?.trim();
  if (normalized == null || normalized.isEmpty) {
    return null;
  }
  return normalized;
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
  final int? freeMatchesLeft;
  final bool? notificationsEnabled;
  final bool? vibrationEnabled;
  final bool? messageSoundsEnabled;
  final String? languageCode;
  final String? matchGenderFilterCode;
  final String? matchAgeFilterCode;
  final bool? superMatchEnabled;
  final bool? premiumActive;

  const AppUser({
    required this.id,
    required this.firstName,
    required this.surname,
    required this.username,
    this.email,
    this.profileImageUrl,
    this.bio,
    this.gemBalance,
    this.freeMatchesLeft,
    this.notificationsEnabled,
    this.vibrationEnabled,
    this.messageSoundsEnabled,
    this.languageCode,
    this.matchGenderFilterCode,
    this.matchAgeFilterCode,
    this.superMatchEnabled,
    this.premiumActive,
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
    int? freeMatchesLeft,
    bool? notificationsEnabled,
    bool? vibrationEnabled,
    bool? messageSoundsEnabled,
    String? languageCode,
    String? matchGenderFilterCode,
    String? matchAgeFilterCode,
    bool? superMatchEnabled,
    bool? premiumActive,
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
      freeMatchesLeft: freeMatchesLeft ?? this.freeMatchesLeft,
      notificationsEnabled: notificationsEnabled ?? this.notificationsEnabled,
      vibrationEnabled: vibrationEnabled ?? this.vibrationEnabled,
      messageSoundsEnabled:
          messageSoundsEnabled ?? this.messageSoundsEnabled,
      languageCode: languageCode ?? this.languageCode,
      matchGenderFilterCode:
          matchGenderFilterCode ?? this.matchGenderFilterCode,
      matchAgeFilterCode: matchAgeFilterCode ?? this.matchAgeFilterCode,
      superMatchEnabled: superMatchEnabled ?? this.superMatchEnabled,
      premiumActive: premiumActive ?? this.premiumActive,
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
      'gunluk_ucretsiz_hak': freeMatchesLeft,
      'bildirimler_acik_mi': notificationsEnabled,
      'titresim_acik_mi': vibrationEnabled,
      'ses_acik_mi': messageSoundsEnabled,
      'dil': languageCode,
      'eslesme_cinsiyet_filtresi': matchGenderFilterCode,
      'eslesme_yas_filtresi': matchAgeFilterCode,
      'super_eslesme_aktif_mi': superMatchEnabled,
      'premium_aktif_mi': premiumActive,
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
      freeMatchesLeft: (json['gunluk_ucretsiz_hak'] as num?)?.toInt(),
      notificationsEnabled: json['bildirimler_acik_mi'] as bool?,
      vibrationEnabled: json['titresim_acik_mi'] as bool?,
      messageSoundsEnabled: json['ses_acik_mi'] as bool?,
      languageCode: _nullableString(json['dil']?.toString()),
      matchGenderFilterCode: _nullableString(
        json['eslesme_cinsiyet_filtresi']?.toString(),
      ),
      matchAgeFilterCode: _nullableString(
        json['eslesme_yas_filtresi']?.toString(),
      ),
      superMatchEnabled: json['super_eslesme_aktif_mi'] as bool?,
      premiumActive: json['premium_aktif_mi'] as bool?,
    );
  }
}

@immutable
class AppBlockedUser {
  final int id;
  final String firstName;
  final String surname;
  final String username;
  final String? profileImageUrl;
  final DateTime? blockedAt;

  const AppBlockedUser({
    required this.id,
    required this.firstName,
    required this.surname,
    required this.username,
    this.profileImageUrl,
    this.blockedAt,
  });

  String get displayName {
    final value = '$firstName $surname'.trim();
    return value.isEmpty ? username : value;
  }

  String get handle {
    final value = username.trim();
    if (value.isEmpty) {
      return '';
    }
    return value.startsWith('@') ? value : '@$value';
  }

  factory AppBlockedUser.fromJson(Map<String, dynamic> json) {
    return AppBlockedUser(
      id: (json['id'] as num?)?.toInt() ?? 0,
      firstName: json['ad']?.toString() ?? '',
      surname: json['soyad']?.toString() ?? '',
      username: json['kullanici_adi']?.toString() ?? '',
      profileImageUrl: json['profil_resmi']?.toString(),
      blockedAt: DateTime.tryParse(json['engellendi_at']?.toString() ?? ''),
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
