import 'package:flutter/cupertino.dart';

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
  });

  String get contactChannelLabel {
    if (supportWhatsAppUrl != null) {
      return 'WhatsApp';
    }
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
      appName: json['uygulama_adi']?.toString() ?? 'MiniMesaj',
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
    );
  }
}
