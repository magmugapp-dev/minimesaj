import 'dart:io';

import 'package:flutter/services.dart';
import 'package:magmug/core/config/app_config.dart';
import 'package:magmug/core/models/auth_models.dart';

class AppAuthErrorFormatter {
  AppAuthErrorFormatter._();

  static String messageFrom(Object error, {SocialAuthProvider? provider}) {
    if (error is ApiException) {
      final normalized = error.message.toLowerCase();
      if (normalized.contains('http 413') ||
          normalized.contains('post content') ||
          normalized.contains('content-length') ||
          normalized.contains('content leght')) {
        return 'Video boyutu sunucu limitini asiyor. Daha kisa bir video sec ya da sunucuda post_max_size/upload_max_filesize degerlerini artir.';
      }
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
