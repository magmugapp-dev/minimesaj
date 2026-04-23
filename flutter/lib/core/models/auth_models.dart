import 'package:flutter/cupertino.dart';

enum SocialAuthProvider { google, apple }

enum AuthNoticeTone { info, success, error }

enum AppLanguage { tr, en, de, fr }

extension AppLanguageX on AppLanguage {
  String get code => name;

  Locale get locale => Locale(code);

  String get label {
    return switch (this) {
      AppLanguage.tr => 'Turkce',
      AppLanguage.en => 'English',
      AppLanguage.de => 'Deutsch',
      AppLanguage.fr => 'Francais',
    };
  }

  String get flagCode {
    return switch (this) {
      AppLanguage.tr => 'TR',
      AppLanguage.en => 'EN',
      AppLanguage.de => 'DE',
      AppLanguage.fr => 'FR',
    };
  }
}

AppLanguage appLanguageFromCode(String? rawCode) {
  switch (rawCode?.trim().toLowerCase()) {
    case 'en':
      return AppLanguage.en;
    case 'de':
      return AppLanguage.de;
    case 'fr':
      return AppLanguage.fr;
    case 'tr':
    default:
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
