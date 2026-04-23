import 'package:magmug/core/config/app_config.dart';

class AppApi {
  AppApi._();

  static const String publicSettingsPath = '/api/uygulama/ayarlar';
  static const String discoverPath = '/api/dating/kesfet';
  static const String matchCenterPath = '/api/dating/eslesme-merkezi';
  static const String matchPreferencesPath = '/api/dating/eslesme-tercihleri';
  static const String startMatchPath = '/api/dating/eslesme-baslat';
  static const String usernameAvailabilityPath =
      '/api/auth/kullanici-adi-musait';
  static const String supportRequestPath = '/api/uygulama/destek-talebi';
  static const String blockedUsersPath = '/api/dating/engeller';
  static const String reportPath = '/api/dating/sikayet';
  static const String notificationDevicesPath =
      '/api/dating/bildirim-cihazlari';
  static const String notificationsPath = '/api/dating/bildirimler';
  static const String unreadNotificationsPath =
      '/api/dating/bildirimler/okunmamis';
  static const String readAllNotificationsPath = '/api/dating/bildirimler/oku';
  static const String datingChatsPath = '/api/dating/sohbetler';
  static const String datingPhotosPath = '/api/dating/fotograflar';
  static const String datingProfilePath = '/api/dating/profil';
  static const String deleteAccountPath = '/api/auth/hesap';
  static const String logoutPath = '/api/auth/cikis';
  static const String currentUserPath = '/api/auth/ben';
  static const String socialLoginPath = '/api/auth/sosyal/giris';
  static const String socialRegisterPath = '/api/auth/sosyal/kayit';
  static const String paymentPackagesPath = '/api/odeme/paketler';
  static const String subscriptionPackagesPath = '/api/odeme/abonelik-paketler';
  static const String verifyPaymentPath = '/api/odeme/dogrula';
  static const String giftListPath = '/api/hediyeler';
  static const String sendGiftPath = '/api/hediye/gonder';

  static String datingChatMessagesPath(int conversationId) =>
      '/api/dating/sohbetler/$conversationId/mesajlar';

  static String datingChatMessageTranslationPath(
    int conversationId,
    int messageId,
  ) => '/api/dating/sohbetler/$conversationId/mesajlar/$messageId/ceviri';

  static String datingChatReadPath(int conversationId) =>
      '/api/dating/sohbetler/$conversationId/oku';

  static String blockUserPath(int userId) => '/api/dating/engelle/$userId';

  static String datingPeerProfilePath(int userId) =>
      '/api/dating/profil/$userId';

  static String muteUserPath(int userId) => '/api/dating/sessize-al/$userId';

  static String startMatchConversationPath(int userId) =>
      '/api/dating/eslesme-sohbet/$userId';

  static String skipMatchCandidatePath(int userId) =>
      '/api/dating/eslesme-gec/$userId';

  static String datingPhotoPath(int mediaId) =>
      '/api/dating/fotograflar/$mediaId';

  static String readNotificationPath(String notificationId) =>
      '/api/dating/bildirimler/$notificationId/oku';

  static Uri uri(String path) {
    final normalizedPath = path.startsWith('/') ? path.substring(1) : path;
    return Uri.parse('${AppEnvironment.apiBaseUrl}/').resolve(normalizedPath);
  }
}
