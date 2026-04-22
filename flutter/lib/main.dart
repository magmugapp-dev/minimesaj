import 'package:flutter/services.dart';

import 'package:magmug/app_core.dart';
import 'package:magmug/features/chat/chat_flow.dart';
import 'package:magmug/features/home/home_flow.dart';
import 'package:magmug/features/match/match_flow.dart';
import 'package:magmug/features/notifications/notifications_flow.dart';
import 'package:magmug/features/onboarding/onboarding_flow.dart';
import 'package:magmug/features/profile/profile_flow.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  SystemChrome.setPreferredOrientations(const [DeviceOrientation.portraitUp]);
  SystemChrome.setSystemUIOverlayStyle(
    const SystemUiOverlayStyle(
      statusBarColor: Color(0x00000000),
      statusBarIconBrightness: Brightness.dark,
      statusBarBrightness: Brightness.light,
    ),
  );
  runApp(const ProviderScope(child: MagmugApp()));
}

class MagmugApp extends StatelessWidget {
  const MagmugApp({super.key});

  @override
  Widget build(BuildContext context) {
    return const CupertinoApp(
      title: 'Magmug',
      debugShowCheckedModeBanner: false,
      theme: CupertinoThemeData(
        brightness: Brightness.light,
        primaryColor: AppColors.indigo,
        scaffoldBackgroundColor: AppColors.white,
        textTheme: CupertinoTextThemeData(
          primaryColor: AppColors.black,
          textStyle: TextStyle(
            fontFamily: AppFont.family,
            fontSize: 16,
            color: AppColors.black,
            fontWeight: FontWeight.w400,
          ),
        ),
      ),
      home: _AppBootstrapScreen(),
    );
  }
}

class _AppBootstrapScreen extends ConsumerWidget {
  const _AppBootstrapScreen();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final authState = ref.watch(appAuthProvider);

    return authState.when(
      loading: () => const CupertinoPageScaffold(
        backgroundColor: AppColors.white,
        child: Center(child: CupertinoActivityIndicator(radius: 16)),
      ),
      error: (_, __) => const OnboardScreen(),
      data: (session) {
        if (session?.token.trim().isNotEmpty == true) {
          return const HomeScreen(mode: HomeMode.list);
        }
        return const OnboardScreen();
      },
    );
  }
}

class DevIndexScreen extends StatelessWidget {
  const DevIndexScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return CupertinoPageScaffold(
      backgroundColor: AppColors.neutral100,
      child: SafeArea(
        child: ListView(
          physics: const BouncingScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(20, 24, 20, 32),
          children: [
            const Padding(
              padding: EdgeInsets.only(bottom: 4),
              child: Text(
                'magmug',
                style: TextStyle(
                  fontFamily: AppFont.family,
                  fontWeight: FontWeight.w800,
                  fontSize: 34,
                  color: AppColors.black,
                  letterSpacing: -1.5,
                ),
              ),
            ),
            const Padding(
              padding: EdgeInsets.only(bottom: 24),
              child: Text(
                'Gelistirme panosu - tum ekranlar',
                style: TextStyle(
                  fontFamily: AppFont.family,
                  fontSize: 14,
                  color: AppColors.gray,
                ),
              ),
            ),
            const _DevSectionTitle('Onboard'),
            _DevTile(
              title: 'Onboard (Splash)',
              subtitle: 'Google / Apple butonlari, KVKK linki',
              onTap: () => Navigator.of(
                context,
              ).push(cupertinoRoute(const OnboardScreen())),
            ),
            _DevTile(
              title: 'Login - Step 1',
              subtitle: 'Kisisel bilgiler (isim, soyisim, kullanici adi, yil)',
              onTap: () => Navigator.of(
                context,
              ).push(cupertinoRoute(const LoginStep1Screen())),
            ),
            _DevTile(
              title: 'Login - Step 2',
              subtitle: 'Cinsiyet secimi',
              onTap: () => Navigator.of(
                context,
              ).push(cupertinoRoute(const LoginStep2Screen())),
            ),
            _DevTile(
              title: 'Login - Step 3',
              subtitle: 'Fotograf secimi (kamera/galeri)',
              onTap: () => Navigator.of(
                context,
              ).push(cupertinoRoute(const LoginStep3Screen())),
            ),
            _DevTile(
              title: 'KVKK Aydinlatma Metni',
              subtitle: 'Sozlesme metni',
              onTap: () => Navigator.of(
                context,
              ).push(cupertinoRoute(const KvkkScreen())),
            ),
            const SizedBox(height: 20),
            const _DevSectionTitle('Ana Sayfa'),
            _DevTile(
              title: 'Home - Bos (sohbet yok)',
              subtitle: '3D balon, empty CTA',
              onTap: () => Navigator.of(
                context,
              ).push(cupertinoRoute(const HomeScreen(mode: HomeMode.empty))),
            ),
            _DevTile(
              title: 'Home - Liste',
              subtitle: 'Sohbet listesi + alt sabit banner',
              onTap: () => Navigator.of(
                context,
              ).push(cupertinoRoute(const HomeScreen(mode: HomeMode.list))),
            ),
            _DevTile(
              title: 'Home - Liste + Ust Banner',
              subtitle: 'Reklam placeholder + liste',
              onTap: () => Navigator.of(context).push(
                cupertinoRoute(const HomeScreen(mode: HomeMode.listWithBanner)),
              ),
            ),
            const SizedBox(height: 20),
            const _DevSectionTitle('Eslesme'),
            _DevTile(
              title: 'Match Mode - Free',
              subtitle: 'Ucretsiz haklar aktif, radar + filtre pillleri',
              onTap: () => Navigator.of(context).push(
                cupertinoRoute(
                  const MatchModeScreen(variant: MatchModeVariant.free),
                ),
              ),
            ),
            _DevTile(
              title: 'Match Mode - Paid (8 tas)',
              subtitle: 'Ucretsiz hak bitti, CTA\'da tas chip',
              onTap: () => Navigator.of(context).push(
                cupertinoRoute(
                  const MatchModeScreen(variant: MatchModeVariant.paid),
                ),
              ),
            ),
            _DevTile(
              title: 'Purchase Sheet',
              subtitle: '3 kredi paketi, EN POPULER',
              onTap: () => showCupertinoModalPopup<void>(
                context: context,
                builder: (_) => const PurchaseSheet(),
              ),
            ),
            _DevTile(
              title: 'Gender Filter Sheet',
              subtitle: 'Cinsiyet secimi + Super Eslesme toggle',
              onTap: () => showCupertinoModalPopup<void>(
                context: context,
                builder: (_) => const GenderFilterSheet(),
              ),
            ),
            _DevTile(
              title: 'Matching - Loading',
              subtitle: 'Ambient bg + emoji + Durdur',
              onTap: () => Navigator.of(
                context,
              ).push(cupertinoRoute(const MatchingScreen())),
            ),
            _DevTile(
              title: 'Matching - With Ad',
              subtitle: 'Reklam karti + Premium upsell',
              onTap: () => Navigator.of(
                context,
              ).push(cupertinoRoute(const MatchingScreen(withAd: true))),
            ),
            _DevTile(
              title: 'Match Found - Super (mor)',
              subtitle: 'Super Eslesme ekrani',
              onTap: () => Navigator.of(context).push(
                cupertinoRoute(
                  const MatchFoundScreen(theme: MatchFoundTheme.superMatch),
                ),
              ),
            ),
            _DevTile(
              title: 'Match Found - Normal (turuncu)',
              subtitle: 'Normal eslesme ekrani',
              onTap: () => Navigator.of(context).push(
                cupertinoRoute(
                  const MatchFoundScreen(theme: MatchFoundTheme.normal),
                ),
              ),
            ),
            const SizedBox(height: 20),
            const _DevSectionTitle('Mesaj'),
            _DevTile(
              title: 'Chat - Bos (Hello mascot)',
              subtitle: 'Bir Selam Ver! empty state + input bar',
              onTap: () => Navigator.of(context).push(
                cupertinoRoute(const ChatScreen(mode: ChatScreenMode.empty)),
              ),
            ),
            _DevTile(
              title: 'Chat - Mesajlasma',
              subtitle: 'Text, foto, audio, typing balonlari',
              onTap: () => Navigator.of(context).push(
                cupertinoRoute(const ChatScreen(mode: ChatScreenMode.messages)),
              ),
            ),
            _DevTile(
              title: 'Chat Profile',
              subtitle: 'Profil, media grid, sohbet temasi, engelle/sikayet',
              onTap: () => Navigator.of(
                context,
              ).push(cupertinoRoute(const ChatProfileScreen())),
            ),
            _DevTile(
              title: 'Gift Sheet',
              subtitle: 'Hediye secimi 3x3 grid + kategori chip',
              onTap: () => showCupertinoModalPopup<void>(
                context: context,
                builder: (_) => const GiftSheet(),
              ),
            ),
            _DevTile(
              title: 'Report Sheet',
              subtitle: 'Sikayet sebepleri (radio list)',
              onTap: () => showCupertinoModalPopup<void>(
                context: context,
                builder: (_) => const ReportSheet(),
              ),
            ),
            _DevTile(
              title: 'Block Confirm Sheet',
              subtitle: 'Kullanici engelleme onay modali',
              onTap: () => showCupertinoModalPopup<void>(
                context: context,
                builder: (_) => const BlockConfirmSheet(),
              ),
            ),
            const SizedBox(height: 20),
            const _DevSectionTitle('Bildirimler'),
            _DevTile(
              title: 'Notifications - Bos',
              subtitle: '3D can + empty state',
              onTap: () => Navigator.of(
                context,
              ).push(cupertinoRoute(const NotificationsScreen(empty: true))),
            ),
            _DevTile(
              title: 'Notifications - Dolu',
              subtitle: 'Bugun/Dun/Bu Hafta + promo kartlar + 9 bildirim',
              onTap: () => Navigator.of(
                context,
              ).push(cupertinoRoute(const NotificationsScreen())),
            ),
            const SizedBox(height: 20),
            const _DevSectionTitle('Profil & Ayarlar'),
            _DevTile(
              title: 'Profilim',
              subtitle: 'Ana profil: avatar, premium promo, settings grup',
              onTap: () => Navigator.of(
                context,
              ).push(cupertinoRoute(const ProfileScreen())),
            ),
            _DevTile(
              title: 'Gizlilik Politikasi',
              subtitle: 'Policy scaffold, 5 madde',
              onTap: () => Navigator.of(
                context,
              ).push(cupertinoRoute(const PrivacyPolicyScreen())),
            ),
            _DevTile(
              title: 'Kullanim Kosullari',
              subtitle: 'Policy scaffold, 5 madde',
              onTap: () => Navigator.of(
                context,
              ).push(cupertinoRoute(const TermsOfUseScreen())),
            ),
            _DevTile(
              title: 'Profili Duzenle Sheet',
              subtitle: 'Isim/Soyisim/Kullanici Adi/Biyografi',
              onTap: () => showCupertinoModalPopup<void>(
                context: context,
                builder: (_) => const EditProfileSheet(),
              ),
            ),
            _DevTile(
              title: 'Dil Secimi Sheet',
              subtitle: '4 dil + Degistir',
              onTap: () => showCupertinoModalPopup<void>(
                context: context,
                builder: (_) => const LanguageSheet(),
              ),
            ),
            _DevTile(
              title: 'Bildirim Tercihleri Sheet',
              subtitle: '4 toggle + Kaydet',
              onTap: () => showCupertinoModalPopup<void>(
                context: context,
                builder: (_) => const NotificationPrefsSheet(),
              ),
            ),
            _DevTile(
              title: 'Yardim Sheet',
              subtitle: 'SSS collapsible + Bize Yazin + WhatsApp',
              onTap: () => showCupertinoModalPopup<void>(
                context: context,
                builder: (_) => const HelpSheet(),
              ),
            ),
            _DevTile(
              title: 'Engellenen Kullanicilar Sheet',
              subtitle: '2 kullanici + Engeli Kaldir',
              onTap: () => showCupertinoModalPopup<void>(
                context: context,
                builder: (_) => const BlockedUsersSheet(),
              ),
            ),
            _DevTile(
              title: 'Engeli Kaldir Onay',
              subtitle: 'Confirm sheet',
              onTap: () => showCupertinoModalPopup<void>(
                context: context,
                builder: (_) => const UnblockConfirmSheet(),
              ),
            ),
            _DevTile(
              title: 'Cikis Yap Onay',
              subtitle: 'Red confirm sheet',
              onTap: () => showCupertinoModalPopup<void>(
                context: context,
                builder: (_) => const SignOutConfirmSheet(),
              ),
            ),
            _DevTile(
              title: 'Hesabi Sil Onay',
              subtitle: 'Red destructive confirm sheet',
              onTap: () => showCupertinoModalPopup<void>(
                context: context,
                builder: (_) => const DeleteAccountConfirmSheet(),
              ),
            ),
            const SizedBox(height: 20),
            const _DevSectionTitle('Paywall & Jeton'),
            _DevTile(
              title: 'Paywall - Premium',
              subtitle: 'Koyu bg + 3 plan + Devam Et',
              onTap: () => Navigator.of(
                context,
              ).push(cupertinoRoute(const PaywallScreen())),
            ),
            _DevTile(
              title: 'Jeton Satin Alma Sheet',
              subtitle: 'Karakter + 3 tas paketi + Satin Al',
              onTap: () => showCupertinoModalPopup<void>(
                context: context,
                builder: (_) => const JetonPurchaseSheet(),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _DevSectionTitle extends StatelessWidget {
  final String title;

  const _DevSectionTitle(this.title);

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(left: 4, bottom: 10, top: 4),
      child: Text(
        title.toUpperCase(),
        style: const TextStyle(
          fontFamily: AppFont.family,
          fontWeight: FontWeight.w700,
          fontSize: 11,
          letterSpacing: 1.2,
          color: AppColors.neutral500,
        ),
      ),
    );
  }
}

class _DevTile extends StatelessWidget {
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  const _DevTile({
    required this.title,
    required this.subtitle,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: PressableScale(
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
          decoration: BoxDecoration(
            color: AppColors.white,
            borderRadius: BorderRadius.circular(18),
            boxShadow: const [
              BoxShadow(
                color: Color(0x08000000),
                blurRadius: 10,
                offset: Offset(0, 3),
              ),
            ],
          ),
          child: Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: const TextStyle(
                        fontFamily: AppFont.family,
                        fontWeight: FontWeight.w700,
                        fontSize: 14,
                        color: AppColors.black,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      subtitle,
                      style: const TextStyle(
                        fontFamily: AppFont.family,
                        fontSize: 12,
                        height: 1.35,
                        color: AppColors.gray,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 12),
              const Icon(
                CupertinoIcons.chevron_right,
                size: 18,
                color: AppColors.neutral500,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
