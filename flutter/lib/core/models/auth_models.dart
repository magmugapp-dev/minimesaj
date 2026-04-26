import 'package:flutter/cupertino.dart';
import 'package:magmug/core/models/app_content_models.dart';

enum SocialAuthProvider { google, apple }

enum AuthNoticeTone { info, success, error }

@immutable
class AppLanguage {
  final String code;
  final String label;
  final String flagCode;

  const AppLanguage({
    required this.code,
    required this.label,
    required this.flagCode,
  });

  static const tr = AppLanguage(code: 'tr', label: 'Turkce', flagCode: 'TR');
  static const en = AppLanguage(code: 'en', label: 'English', flagCode: 'EN');
  static const de = AppLanguage(code: 'de', label: 'Deutsch', flagCode: 'DE');
  static const fr = AppLanguage(code: 'fr', label: 'Francais', flagCode: 'FR');
  static const values = <AppLanguage>[tr, en, de, fr];

  Locale get locale => Locale(code.split('-').first);

  factory AppLanguage.fromContent(AppContentLanguage language) {
    final label = language.nativeName?.trim().isNotEmpty == true
        ? language.nativeName!.trim()
        : language.name;

    return AppLanguage(
      code: language.code,
      label: label,
      flagCode: language.code.toUpperCase(),
    );
  }

  @override
  bool operator ==(Object other) {
    return other is AppLanguage && other.code == code;
  }

  @override
  int get hashCode => code.hashCode;
}

AppLanguage appLanguageFromCode(String? rawCode) {
  final code = rawCode?.trim().toLowerCase();
  switch (code) {
    case 'en':
      return AppLanguage.en;
    case 'de':
      return AppLanguage.de;
    case 'fr':
      return AppLanguage.fr;
    case 'tr':
      return AppLanguage.tr;
    default:
      if (code != null && code.isNotEmpty) {
        return AppLanguage(
          code: code,
          label: code.toUpperCase(),
          flagCode: code.toUpperCase(),
        );
      }
      return AppLanguage.tr;
  }
}

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
  final String? socialAvatarUrl;

  const OnboardData({
    this.name = '',
    this.surname = '',
    this.username = '',
    this.birthYear,
    this.gender,
    this.photoPath,
    this.socialSession,
    this.socialAvatarUrl,
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
    String? socialAvatarUrl,
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
      socialAvatarUrl: clearSocialSession
          ? null
          : (socialAvatarUrl ?? this.socialAvatarUrl),
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

class ApiException implements Exception {
  final String message;

  const ApiException(this.message);

  @override
  String toString() => message;
}

class UnauthorizedApiException extends ApiException {
  const UnauthorizedApiException(super.message);
}

class BlockedByUserApiException extends ApiException {
  const BlockedByUserApiException(super.message);
}

class AppUpdateRequiredException extends ApiException {
  final String? updateUrl;
  final String? minimumVersion;

  const AppUpdateRequiredException(
    super.message, {
    this.updateUrl,
    this.minimumVersion,
  });
}
