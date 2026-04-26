import 'package:flutter/cupertino.dart';
import 'package:magmug/core/i18n/app_runtime_text.dart';
import 'package:magmug/core/models/app_content_models.dart';

String? _nullableString(String? value) {
  final normalized = value?.trim();
  if (normalized == null || normalized.isEmpty) {
    return null;
  }
  return normalized;
}

@immutable
class AppPublicSettings {
  final String appName;
  final String? appLogoUrl;
  final String? appVersion;
  final String? minimumSupportedVersion;
  final String defaultLanguage;
  final bool registrationEnabled;
  final String? supportEmail;
  final String? supportWhatsApp;
  final String? androidPlayStoreUrl;
  final String? iosAppStoreUrl;
  final AppAdMobSettings ads;

  const AppPublicSettings({
    required this.appName,
    this.appLogoUrl,
    this.appVersion,
    this.minimumSupportedVersion,
    this.defaultLanguage = 'tr',
    this.registrationEnabled = true,
    this.supportEmail,
    this.supportWhatsApp,
    this.androidPlayStoreUrl,
    this.iosAppStoreUrl,
    this.ads = const AppAdMobSettings(),
  });

  String get contactChannelLabel {
    if (supportEmail != null && supportEmail!.trim().isNotEmpty) {
      return 'E-posta';
    }
    return 'Destek';
  }

  String? get supportWhatsAppUrl {
    final rawValue = supportWhatsApp?.trim();
    if (rawValue == null || rawValue.isEmpty) {
      return null;
    }

    final digits = rawValue.replaceAll(RegExp(r'[^0-9]'), '');
    if (digits.isEmpty) {
      return null;
    }

    return 'https://wa.me/$digits';
  }

  factory AppPublicSettings.fromJson(Map<String, dynamic> json) {
    return AppPublicSettings(
      appName:
          json['uygulama_adi']?.toString() ??
          AppRuntimeText.instance.t('app.name.legacy', 'MiniMesaj'),
      appLogoUrl: _nullableString(json['uygulama_logosu']?.toString()),
      appVersion: _nullableString(json['uygulama_versiyonu']?.toString()),
      minimumSupportedVersion: _nullableString(
        json['mobil_minimum_versiyon']?.toString(),
      ),
      defaultLanguage:
          _nullableString(json['varsayilan_dil']?.toString()) ?? 'tr',
      registrationEnabled: json['kayit_aktif_mi'] != false,
      supportEmail: _nullableString(json['destek_eposta']?.toString()),
      supportWhatsApp: _nullableString(json['destek_whatsapp']?.toString()),
      androidPlayStoreUrl: _nullableString(
        json['android_play_store_url']?.toString(),
      ),
      iosAppStoreUrl: _nullableString(json['ios_app_store_url']?.toString()),
      ads: AppAdMobSettings.fromJson(
        (json['reklamlar'] as Map?)?.cast<String, dynamic>(),
      ),
    );
  }
}

enum AppLegalTextKind { privacy, kvkk, terms }

@immutable
class AppLegalText {
  final String key;
  final String title;
  final String content;
  final DateTime? updatedAt;

  const AppLegalText({
    required this.key,
    required this.title,
    required this.content,
    this.updatedAt,
  });

  bool get hasContent => content.trim().isNotEmpty;

  factory AppLegalText.fromJson(
    Map<String, dynamic>? json, {
    required String fallbackKey,
    required String fallbackTitle,
  }) {
    return AppLegalText(
      key: _nullableString(json?['anahtar']?.toString()) ?? fallbackKey,
      title: _nullableString(json?['baslik']?.toString()) ?? fallbackTitle,
      content: json?['icerik']?.toString() ?? '',
      updatedAt: DateTime.tryParse(json?['guncellendi_at']?.toString() ?? ''),
    );
  }
}

@immutable
class AppLegalTexts {
  final String version;
  final AppLegalText privacy;
  final AppLegalText kvkk;
  final AppLegalText terms;

  const AppLegalTexts({
    required this.version,
    required this.privacy,
    required this.kvkk,
    required this.terms,
  });

  AppLegalText byKind(AppLegalTextKind kind) {
    return switch (kind) {
      AppLegalTextKind.privacy => privacy,
      AppLegalTextKind.kvkk => kvkk,
      AppLegalTextKind.terms => terms,
    };
  }

  factory AppLegalTexts.fromJson(Map<String, dynamic> json) {
    final rawTexts = json['metinler'];
    final texts = rawTexts is Map
        ? rawTexts.map((key, value) => MapEntry(key.toString(), value))
        : const <String, dynamic>{};
    Map<String, dynamic>? item(String key) {
      final value = texts[key];
      if (value is Map<String, dynamic>) {
        return value;
      }
      if (value is Map) {
        return value.map((key, value) => MapEntry(key.toString(), value));
      }
      return null;
    }

    return AppLegalTexts(
      version: _nullableString(json['version']?.toString()) ?? '',
      privacy: AppLegalText.fromJson(
        item('gizlilik_politikasi'),
        fallbackKey: 'gizlilik_politikasi',
        fallbackTitle: AppRuntimeText.instance.t(
          'privacyTitle',
          'Gizlilik Politikasi',
        ),
      ),
      kvkk: AppLegalText.fromJson(
        item('kvkk_aydinlatma_metni'),
        fallbackKey: 'kvkk_aydinlatma_metni',
        fallbackTitle: AppRuntimeText.instance.t(
          'profileKvkk',
          'KVKK Aydinlatma Metni',
        ),
      ),
      terms: AppLegalText.fromJson(
        item('kullanim_kosullari'),
        fallbackKey: 'kullanim_kosullari',
        fallbackTitle: AppRuntimeText.instance.t(
          'termsTitle',
          'Kullanim Kosullari',
        ),
      ),
    );
  }

  factory AppLegalTexts.fromAppContent(AppContent content) {
    AppContentLegalText textFor(String type, String fallbackTitle) {
      return content.legalTexts[type] ??
          AppContentLegalText(type: type, title: fallbackTitle, content: '');
    }

    final privacy = textFor(
      'privacy',
      AppRuntimeText.instance.t('privacyTitle', 'Gizlilik Politikasi'),
    );
    final kvkk = textFor(
      'kvkk',
      AppRuntimeText.instance.t('profileKvkk', 'KVKK Aydinlatma Metni'),
    );
    final terms = textFor(
      'terms',
      AppRuntimeText.instance.t('termsTitle', 'Kullanim Kosullari'),
    );

    return AppLegalTexts(
      version: content.version,
      privacy: AppLegalText(
        key: 'gizlilik_politikasi',
        title: privacy.title,
        content: privacy.content,
        updatedAt: privacy.updatedAt,
      ),
      kvkk: AppLegalText(
        key: 'kvkk_aydinlatma_metni',
        title: kvkk.title,
        content: kvkk.content,
        updatedAt: kvkk.updatedAt,
      ),
      terms: AppLegalText(
        key: 'kullanim_kosullari',
        title: terms.title,
        content: terms.content,
        updatedAt: terms.updatedAt,
      ),
    );
  }
}

@immutable
class AppAdMobSettings {
  static const String androidTestAppId =
      'ca-app-pub-3940256099942544~3347511713';
  static const String iosTestAppId = 'ca-app-pub-3940256099942544~1458002511';
  static const String androidTestRewardedUnitId =
      'ca-app-pub-3940256099942544/5224354917';
  static const String iosTestRewardedUnitId =
      'ca-app-pub-3940256099942544/1712485313';
  static const String androidTestNativeUnitId =
      'ca-app-pub-3940256099942544/2247696110';
  static const String iosTestNativeUnitId =
      'ca-app-pub-3940256099942544/3986624511';

  final bool enabled;
  final bool testMode;
  final int rewardPoints;
  final int dailyRewardLimit;
  final AppAdMobPlatformSettings android;
  final AppAdMobPlatformSettings ios;

  const AppAdMobSettings({
    this.enabled = false,
    this.testMode = true,
    this.rewardPoints = 0,
    this.dailyRewardLimit = 0,
    this.android = const AppAdMobPlatformSettings(),
    this.ios = const AppAdMobPlatformSettings(),
  });

  factory AppAdMobSettings.fromJson(Map<String, dynamic>? json) {
    if (json == null) {
      return const AppAdMobSettings();
    }

    return AppAdMobSettings(
      enabled: json['aktif_mi'] == true,
      testMode: json['test_modu'] != false,
      rewardPoints: (json['odul_puani'] as num?)?.toInt() ?? 0,
      dailyRewardLimit: (json['gunluk_odul_limiti'] as num?)?.toInt() ?? 0,
      android: AppAdMobPlatformSettings.fromJson(
        (json['android'] as Map?)?.cast<String, dynamic>(),
      ),
      ios: AppAdMobPlatformSettings.fromJson(
        (json['ios'] as Map?)?.cast<String, dynamic>(),
      ),
    );
  }

  String? rewardedUnitIdFor(String? platform) {
    if (!enabled) {
      return null;
    }

    return switch (platform) {
      'android' =>
        testMode ? androidTestRewardedUnitId : android.rewardedUnitId,
      'ios' => testMode ? iosTestRewardedUnitId : ios.rewardedUnitId,
      _ => null,
    };
  }

  String? matchNativeUnitIdFor(String? platform) {
    if (!enabled) {
      return null;
    }

    return switch (platform) {
      'android' =>
        testMode ? androidTestNativeUnitId : android.matchNativeUnitId,
      'ios' => testMode ? iosTestNativeUnitId : ios.matchNativeUnitId,
      _ => null,
    };
  }
}

@immutable
class AppAdMobPlatformSettings {
  final String? appId;
  final String? rewardedUnitId;
  final String? matchNativeUnitId;

  const AppAdMobPlatformSettings({
    this.appId,
    this.rewardedUnitId,
    this.matchNativeUnitId,
  });

  factory AppAdMobPlatformSettings.fromJson(Map<String, dynamic>? json) {
    if (json == null) {
      return const AppAdMobPlatformSettings();
    }

    return AppAdMobPlatformSettings(
      appId: _nullableString(json['app_id']?.toString()),
      rewardedUnitId: _nullableString(json['rewarded_unit_id']?.toString()),
      matchNativeUnitId: _nullableString(
        json['match_native_unit_id']?.toString(),
      ),
    );
  }
}
