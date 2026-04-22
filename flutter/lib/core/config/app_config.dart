import 'package:package_info_plus/package_info_plus.dart';

class AppEnvironment {
  AppEnvironment._();

  static const String apiBaseUrl = 'http://192.168.1.104:8000';
  static const String clientType = 'dating';
  static const String androidPlayStoreUrl =
      'https://play.google.com/store/apps/details?id=com.magmug.magmug';
  static const String reverbAppKey = 'minimesaj-key';
  static const int reverbPort = 8080;
}

class SocialAuthConfig {
  SocialAuthConfig._();

  static const String googleServerClientId =
      '609071245287-k6qcdj1kpg8mulm0febnckbsvapn45uk.apps.googleusercontent.com';
}

class AppClientMetadata {
  AppClientMetadata._();

  static Future<String?>? _appVersionFuture;

  static Future<String?> appVersion() {
    return _appVersionFuture ??= _loadAppVersion();
  }

  static Future<String?> _loadAppVersion() async {
    try {
      final info = await PackageInfo.fromPlatform();
      final normalized = info.version.trim();
      return normalized.isEmpty ? null : normalized;
    } catch (_) {
      return null;
    }
  }
}
