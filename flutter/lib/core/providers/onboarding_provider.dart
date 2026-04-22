import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:magmug/core/models/auth_models.dart';

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
    String? avatarUrl,
  }) {
    final parts = _splitDisplayName(displayName);
    state = OnboardData(
      name: parts.$1,
      surname: parts.$2,
      socialSession: socialSession,
      socialAvatarUrl: _normalizeOptionalString(avatarUrl),
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

  String? _normalizeOptionalString(String? value) {
    final normalized = value?.trim();
    if (normalized == null || normalized.isEmpty) {
      return null;
    }

    return normalized;
  }
}

final onboardProvider = NotifierProvider<OnboardNotifier, OnboardData>(
  OnboardNotifier.new,
);
