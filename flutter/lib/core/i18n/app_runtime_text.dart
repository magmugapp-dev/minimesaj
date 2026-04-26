import 'package:flutter/foundation.dart';
import 'package:magmug/core/models/app_content_models.dart';

class AppRuntimeText extends ChangeNotifier {
  AppRuntimeText._();

  static final AppRuntimeText instance = AppRuntimeText._();

  Map<String, String> _translations = const <String, String>{};
  String _languageCode = 'en';
  String _version = '';

  String get languageCode => _languageCode;

  String get version => _version;

  void update(AppContent content) {
    final nextTranslations = Map<String, String>.unmodifiable(
      content.translations,
    );
    if (mapEquals(_translations, nextTranslations) &&
        _languageCode == content.selectedLanguageCode &&
        _version == content.version) {
      return;
    }

    _translations = nextTranslations;
    _languageCode = content.selectedLanguageCode;
    _version = content.version;
    notifyListeners();
  }

  String t(
    String key,
    String fallback, {
    Map<String, Object?> args = const <String, Object?>{},
  }) {
    var value = _translations[key]?.trim();
    if (value == null || value.isEmpty) {
      value = fallback;
    }

    if (args.isEmpty) {
      return value;
    }

    var resolved = value;
    for (final entry in args.entries) {
      final bracedPlaceholder = '{${entry.key}}';
      resolved = resolved
          .replaceAll(bracedPlaceholder, entry.value.toString())
          .replaceAll('\$${entry.key}', entry.value.toString());
    }

    return resolved;
  }
}
