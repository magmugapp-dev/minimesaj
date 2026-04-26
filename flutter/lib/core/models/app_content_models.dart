import 'package:flutter/cupertino.dart';

String? _nullableContentString(Object? value) {
  final normalized = value?.toString().trim();
  if (normalized == null || normalized.isEmpty) {
    return null;
  }
  return normalized;
}

Map<String, dynamic> _asContentMap(Object? value) {
  if (value is Map<String, dynamic>) {
    return value;
  }
  if (value is Map) {
    return value.map((key, val) => MapEntry(key.toString(), val));
  }
  return const <String, dynamic>{};
}

List<Map<String, dynamic>> _contentMaps(Object? value) {
  if (value is! List) {
    return const <Map<String, dynamic>>[];
  }

  return value
      .whereType<Map>()
      .map((item) {
        return item.map((key, val) => MapEntry(key.toString(), val));
      })
      .toList(growable: false);
}

@immutable
class AppContentLanguage {
  final String code;
  final String name;
  final String? nativeName;
  final bool isActive;
  final bool isDefault;
  final int sortOrder;
  final DateTime? updatedAt;

  const AppContentLanguage({
    required this.code,
    required this.name,
    this.nativeName,
    this.isActive = true,
    this.isDefault = false,
    this.sortOrder = 0,
    this.updatedAt,
  });

  Locale get locale => Locale(code.split('-').first);

  factory AppContentLanguage.fromJson(Map<String, dynamic> json) {
    final rawCode = _nullableContentString(json['code']) ?? 'en';

    return AppContentLanguage(
      code: rawCode.toLowerCase(),
      name: _nullableContentString(json['name']) ?? rawCode.toUpperCase(),
      nativeName: _nullableContentString(
        json['nativeName'] ?? json['native_name'],
      ),
      isActive: json['isActive'] != false && json['is_active'] != false,
      isDefault: json['isDefault'] == true || json['is_default'] == true,
      sortOrder:
          (json['sortOrder'] as num?)?.toInt() ??
          (json['sort_order'] as num?)?.toInt() ??
          0,
      updatedAt: DateTime.tryParse(
        json['updatedAt']?.toString() ?? json['updated_at']?.toString() ?? '',
      ),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'code': code,
      'name': name,
      'nativeName': nativeName,
      'isActive': isActive,
      'isDefault': isDefault,
      'sortOrder': sortOrder,
      'updatedAt': updatedAt?.toIso8601String(),
    };
  }
}

@immutable
class AppContentLegalText {
  final String type;
  final String title;
  final String content;
  final String? languageCode;
  final DateTime? updatedAt;

  const AppContentLegalText({
    required this.type,
    required this.title,
    required this.content,
    this.languageCode,
    this.updatedAt,
  });

  bool get hasContent => content.trim().isNotEmpty;

  factory AppContentLegalText.fromJson(
    Map<String, dynamic> json, {
    required String fallbackType,
    required String fallbackTitle,
  }) {
    return AppContentLegalText(
      type:
          _nullableContentString(json['type'] ?? json['anahtar']) ??
          fallbackType,
      title:
          _nullableContentString(json['title'] ?? json['baslik']) ??
          fallbackTitle,
      content: json['content']?.toString() ?? json['icerik']?.toString() ?? '',
      languageCode: _nullableContentString(
        json['languageCode'] ?? json['language_code'] ?? json['dil_kodu'],
      ),
      updatedAt: DateTime.tryParse(
        json['updatedAt']?.toString() ??
            json['updated_at']?.toString() ??
            json['guncellendi_at']?.toString() ??
            '',
      ),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'type': type,
      'title': title,
      'content': content,
      'languageCode': languageCode,
      'updatedAt': updatedAt?.toIso8601String(),
    };
  }
}

@immutable
class AppContentFaqItem {
  final int id;
  final String question;
  final String answer;
  final String? category;
  final String? screen;
  final int sortOrder;
  final String? languageCode;
  final DateTime? updatedAt;

  const AppContentFaqItem({
    required this.id,
    required this.question,
    required this.answer,
    this.category,
    this.screen,
    this.sortOrder = 0,
    this.languageCode,
    this.updatedAt,
  });

  factory AppContentFaqItem.fromJson(Map<String, dynamic> json) {
    return AppContentFaqItem(
      id: (json['id'] as num?)?.toInt() ?? 0,
      question: json['question']?.toString() ?? json['soru']?.toString() ?? '',
      answer: json['answer']?.toString() ?? json['cevap']?.toString() ?? '',
      category: _nullableContentString(json['category'] ?? json['kategori']),
      screen: _nullableContentString(json['screen'] ?? json['ekran']),
      sortOrder:
          (json['sortOrder'] as num?)?.toInt() ??
          (json['sort_order'] as num?)?.toInt() ??
          0,
      languageCode: _nullableContentString(
        json['languageCode'] ?? json['language_code'] ?? json['dil_kodu'],
      ),
      updatedAt: DateTime.tryParse(
        json['updatedAt']?.toString() ?? json['updated_at']?.toString() ?? '',
      ),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'question': question,
      'answer': answer,
      'category': category,
      'screen': screen,
      'sortOrder': sortOrder,
      'languageCode': languageCode,
      'updatedAt': updatedAt?.toIso8601String(),
    };
  }
}

@immutable
class AppContent {
  final List<AppContentLanguage> languages;
  final String defaultLanguageCode;
  final String selectedLanguageCode;
  final Map<String, String> translations;
  final Map<String, AppContentLegalText> legalTexts;
  final List<AppContentFaqItem> faqs;
  final String version;
  final DateTime? updatedAt;
  final bool fromCache;

  const AppContent({
    required this.languages,
    required this.defaultLanguageCode,
    required this.selectedLanguageCode,
    required this.translations,
    required this.legalTexts,
    required this.faqs,
    required this.version,
    this.updatedAt,
    this.fromCache = false,
  });

  factory AppContent.empty({String languageCode = 'en'}) {
    return AppContent(
      languages: [
        AppContentLanguage(
          code: languageCode,
          name: languageCode.toUpperCase(),
          isDefault: true,
        ),
      ],
      defaultLanguageCode: languageCode,
      selectedLanguageCode: languageCode,
      translations: const <String, String>{},
      legalTexts: const <String, AppContentLegalText>{},
      faqs: const <AppContentFaqItem>[],
      version: '',
    );
  }

  String t(String key, {String? fallback}) {
    final value = translations[key]?.trim();
    if (value != null && value.isNotEmpty) {
      return value;
    }

    final normalizedFallback = fallback?.trim();
    if (normalizedFallback != null && normalizedFallback.isNotEmpty) {
      return normalizedFallback;
    }

    return key;
  }

  AppContent copyWith({bool? fromCache}) {
    return AppContent(
      languages: languages,
      defaultLanguageCode: defaultLanguageCode,
      selectedLanguageCode: selectedLanguageCode,
      translations: translations,
      legalTexts: legalTexts,
      faqs: faqs,
      version: version,
      updatedAt: updatedAt,
      fromCache: fromCache ?? this.fromCache,
    );
  }

  factory AppContent.fromJson(Map<String, dynamic> json) {
    final data = _asContentMap(json['veri'] ?? json['data']);
    final source = data.isEmpty ? json : data;
    final legalRaw = _asContentMap(
      source['legalTexts'] ?? source['legal_texts'] ?? source['metinler'],
    );

    final translationsRaw = _asContentMap(source['translations']);
    final translations = translationsRaw.map(
      (key, value) => MapEntry(key, value?.toString() ?? ''),
    );

    return AppContent(
      languages: _contentMaps(source['languages'] ?? source['diller'])
          .map(AppContentLanguage.fromJson)
          .where((language) => language.code.trim().isNotEmpty)
          .toList(growable: false),
      defaultLanguageCode:
          _nullableContentString(
            source['defaultLanguage'] ?? source['default_language'],
          ) ??
          'en',
      selectedLanguageCode:
          _nullableContentString(
            source['selectedLanguage'] ?? source['selected_language'],
          ) ??
          _nullableContentString(
            source['defaultLanguage'] ?? source['default_language'],
          ) ??
          'en',
      translations: translations,
      legalTexts: {
        'privacy': AppContentLegalText.fromJson(
          _asContentMap(legalRaw['privacy'] ?? legalRaw['gizlilik_politikasi']),
          fallbackType: 'privacy',
          fallbackTitle: '',
        ),
        'kvkk': AppContentLegalText.fromJson(
          _asContentMap(legalRaw['kvkk'] ?? legalRaw['kvkk_aydinlatma_metni']),
          fallbackType: 'kvkk',
          fallbackTitle: '',
        ),
        'terms': AppContentLegalText.fromJson(
          _asContentMap(legalRaw['terms'] ?? legalRaw['kullanim_kosullari']),
          fallbackType: 'terms',
          fallbackTitle: '',
        ),
      },
      faqs: _contentMaps(source['faqs'] ?? source['faq'])
          .map(AppContentFaqItem.fromJson)
          .where((item) => item.question.trim().isNotEmpty)
          .toList(growable: false),
      version: _nullableContentString(source['version']) ?? '',
      updatedAt: DateTime.tryParse(source['updatedAt']?.toString() ?? ''),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'languages': languages.map((language) => language.toJson()).toList(),
      'defaultLanguage': defaultLanguageCode,
      'selectedLanguage': selectedLanguageCode,
      'translations': translations,
      'legalTexts': legalTexts.map(
        (key, value) => MapEntry(key, value.toJson()),
      ),
      'faqs': faqs.map((faq) => faq.toJson()).toList(),
      'version': version,
      'updatedAt': updatedAt?.toIso8601String(),
    };
  }
}
