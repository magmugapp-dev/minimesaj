import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter/services.dart';
import 'package:magmug/l10n/app_localizations.dart';

import 'package:magmug/app_bootstrap.dart';
import 'package:magmug/app_core.dart';
import 'package:magmug/firebase_options.dart';

@pragma('vm:entry-point')
Future<void> _firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  await Firebase.initializeApp(options: DefaultFirebaseOptions.currentPlatform);
}

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  if (AppPushSupport.isSupported) {
    await Firebase.initializeApp(
      options: DefaultFirebaseOptions.currentPlatform,
    );
    FirebaseMessaging.onBackgroundMessage(_firebaseMessagingBackgroundHandler);
  }
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

class MagmugApp extends ConsumerWidget {
  const MagmugApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final appLanguage =
        ref.watch(appLanguageProvider).asData?.value ??
        AppPreferencesStorage.fallbackLanguage();

    return CupertinoApp(
      title: 'Magmug',
      debugShowCheckedModeBanner: false,
      locale: appLanguage.locale,
      localizationsDelegates: [
        AppLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
      ],
      supportedLocales: AppLocalizations.supportedLocales,
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
      home: const AppBootstrapScreen(),
    );
  }
}
