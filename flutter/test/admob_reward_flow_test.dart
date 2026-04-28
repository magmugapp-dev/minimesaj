import 'dart:convert';
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:hive/hive.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:magmug/app_core.dart';
import 'package:magmug/features/ads/admob_ads.dart';
import 'package:magmug/features/match/match_flow.dart';
import 'package:magmug/features/profile/widgets/profile_purchase_widgets.dart';
import 'package:magmug/l10n/app_localizations.dart';

void main() {
  late Directory hiveDirectory;

  setUpAll(() async {
    hiveDirectory = await Directory.systemTemp.createTemp(
      'magmug_hive_admob_test_',
    );
    Hive.init(hiveDirectory.path);
  });

  setUp(() async {
    await _clearHiveBoxes();
  });

  tearDown(() async {
    await Hive.close();
  });

  tearDownAll(() async {
    if (await hiveDirectory.exists()) {
      await hiveDirectory.delete(recursive: true);
    }
  });

  test('public settings parse admob configuration and resolve test units', () {
    final settings = AppPublicSettings.fromJson({
      'uygulama_adi': 'Magmug',
      'reklamlar': {
        'aktif_mi': true,
        'test_modu': true,
        'odul_puani': 15,
        'gunluk_odul_limiti': 10,
        'android': {
          'app_id': 'ca-app-pub-real~android',
          'rewarded_unit_id': 'ca-app-pub-real/android-rewarded',
          'match_native_unit_id': 'ca-app-pub-real/android-native',
        },
        'ios': {
          'app_id': 'ca-app-pub-real~ios',
          'rewarded_unit_id': 'ca-app-pub-real/ios-rewarded',
          'match_native_unit_id': 'ca-app-pub-real/ios-native',
        },
      },
    });

    expect(settings.ads.enabled, isTrue);
    expect(settings.ads.rewardPoints, 15);
    expect(settings.ads.dailyRewardLimit, 10);
    expect(
      settings.ads.rewardedUnitIdFor('android'),
      AppAdMobSettings.androidTestRewardedUnitId,
    );
    expect(
      settings.ads.matchNativeUnitIdFor('ios'),
      AppAdMobSettings.iosTestNativeUnitId,
    );
  });

  test('reward status and claim api calls use the expected payload', () async {
    final requests = <http.Request>[];
    final api = AppAuthApi(
      client: MockClient((request) async {
        requests.add(request);

        if (request.url.path.endsWith('/api/odeme/reklam-odul/durum')) {
          return http.Response(
            jsonEncode({
              'aktif_mi': true,
              'odul_puani': 15,
              'gunluk_limit': 10,
              'bugun_izlenen': 1,
              'kalan_hak': 9,
            }),
            200,
            headers: {'content-type': 'application/json'},
          );
        }

        return http.Response(
          jsonEncode({
            'mesaj': 'Odul kazandiniz: +15 puan',
            'odul_puani': 15,
            'mevcut_puan': 45,
            'gunluk_limit': 10,
            'bugun_izlenen': 2,
            'kalan_hak': 8,
            'olay_kodu': 'reward-event-1',
            'tekrar_mi': false,
          }),
          201,
          headers: {'content-type': 'application/json'},
        );
      }),
    );

    final status = await api.fetchRewardAdStatus('token-1');
    final claim = await api.claimRewardedAd(
      'token-1',
      platform: 'android',
      adUnitId: 'ca-app-pub-test/rewarded',
      eventCode: 'reward-event-1',
    );
    api.close();

    expect(status.canWatch, isTrue);
    expect(status.remainingRights, 9);
    expect(claim.currentBalance, 45);
    expect(claim.remainingRights, 8);

    final claimBody = jsonDecode(requests.last.body) as Map<String, dynamic>;
    expect(claimBody['reklam_platformu'], 'android');
    expect(claimBody['reklam_birim_kodu'], 'ca-app-pub-test/rewarded');
    expect(claimBody['olay_kodu'], 'reward-event-1');
    expect(claimBody['reklam_tipi'], 'rewarded');
  });

  testWidgets('credit purchase sheet shows rewarded ad action when provided', (
    tester,
  ) async {
    await tester.pumpWidget(
      CupertinoApp(
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        supportedLocales: AppLocalizations.supportedLocales,
        home: ProfileJetonPurchaseSheetView(
          title: 'Jeton satin al',
          subtitle: 'Kredi paketleri',
          packagesAsync: AsyncData([
            AppCreditPackage(
              id: 1,
              code: 'kredi_25',
              storeProductCode: 'magmug.kredi25',
              credits: 25,
              price: 49.99,
              currency: 'TRY',
              badge: null,
              isRecommended: true,
              isActive: true,
              order: 1,
            ),
          ]),
          packages: [
            AppCreditPackage(
              id: 1,
              code: 'kredi_25',
              storeProductCode: 'magmug.kredi25',
              credits: 25,
              price: 49.99,
              currency: 'TRY',
              badge: null,
              isRecommended: true,
              isActive: true,
              order: 1,
            ),
          ],
          selectedIndex: 0,
          onSelectPackage: (_) {},
          loadingErrorMessage: 'Yuklenemedi',
          emptyMessage: 'Bos',
          primaryActionLabel: 'Satin al',
          onPrimaryAction: () {},
          rewardActionLabel: 'Reklam izle +15 Kredi',
          rewardActionSubtitle: 'Kalan hak: 9/10',
          onRewardAction: () {},
          infoText: 'Krediler aninda yuklenir.',
        ),
      ),
    );

    expect(find.text('Reklam izle +15 Kredi'), findsOneWidget);
    expect(find.text('Kalan hak: 9/10'), findsOneWidget);
  });

  testWidgets('matching native ad waits eight seconds after real load', (
    tester,
  ) async {
    _primeNextMatchingNativeAdRequest();
    var loadNotified = false;

    await tester.pumpWidget(
      _matchingApp(
        nativeAdUnitId: 'ca-app-pub-test/native',
        nativeAdCardBuilder:
            (
              context, {
              required String adUnitId,
              required Widget fallback,
              required VoidCallback onLoaded,
              required VoidCallback onFailed,
            }) {
              if (!loadNotified) {
                loadNotified = true;
                WidgetsBinding.instance.addPostFrameCallback((_) => onLoaded());
              }

              return const SizedBox.expand(
                key: ValueKey('loaded-native-ad-card'),
              );
            },
      ),
    );

    await tester.pump();
    await tester.pump(const Duration(seconds: 7));

    expect(find.byType(MatchingScreen), findsOneWidget);
    expect(find.byType(MatchFoundScreen), findsNothing);

    await tester.pump(const Duration(seconds: 1));
    await tester.pump(const Duration(milliseconds: 500));

    expect(find.byType(MatchFoundScreen), findsOneWidget);
  });

  testWidgets('matching native ad failure keeps the short loading flow', (
    tester,
  ) async {
    _primeNextMatchingNativeAdRequest();
    var failureNotified = false;

    await tester.pumpWidget(
      _matchingApp(
        nativeAdUnitId: 'ca-app-pub-test/native',
        nativeAdCardBuilder:
            (
              context, {
              required String adUnitId,
              required Widget fallback,
              required VoidCallback onLoaded,
              required VoidCallback onFailed,
            }) {
              if (!failureNotified) {
                failureNotified = true;
                WidgetsBinding.instance.addPostFrameCallback((_) => onFailed());
              }

              return fallback;
            },
      ),
    );

    await tester.pump();
    await tester.pump(const Duration(milliseconds: 2300));
    await tester.pump(const Duration(milliseconds: 500));

    expect(find.byType(MatchFoundScreen), findsOneWidget);
  });

  testWidgets('premium users do not request the matching native ad', (
    tester,
  ) async {
    AdMobNativeAdFrequencyGate.resetForTesting();
    var requestCount = 0;
    final builder = _countingNativeAdBuilder(() => requestCount++);

    await tester.pumpWidget(
      _matchingApp(
        key: const ValueKey('premium-match'),
        premiumActive: true,
        nativeAdUnitId: 'ca-app-pub-test/native',
        nativeAdCardBuilder: builder,
      ),
    );

    expect(requestCount, 0);
    expect(find.text('Kisa bir ara'), findsNothing);

    await tester.pumpWidget(
      _matchingApp(
        key: const ValueKey('normal-match-1'),
        nativeAdUnitId: 'ca-app-pub-test/native',
        nativeAdCardBuilder: builder,
      ),
    );
    await tester.pumpWidget(
      _matchingApp(
        key: const ValueKey('normal-match-2'),
        nativeAdUnitId: 'ca-app-pub-test/native',
        nativeAdCardBuilder: builder,
      ),
    );

    expect(requestCount, 0);
  });

  testWidgets('matching native ad request repeats every third eligible match', (
    tester,
  ) async {
    AdMobNativeAdFrequencyGate.resetForTesting();
    var requestCount = 0;
    final builder = _countingNativeAdBuilder(() => requestCount++);

    await tester.pumpWidget(
      _matchingApp(
        key: const ValueKey('first-match'),
        nativeAdUnitId: 'ca-app-pub-test/native',
        nativeAdCardBuilder: builder,
      ),
    );

    expect(requestCount, 0);

    await tester.pumpWidget(
      _matchingApp(
        key: const ValueKey('second-match'),
        nativeAdUnitId: 'ca-app-pub-test/native',
        nativeAdCardBuilder: builder,
      ),
    );

    expect(requestCount, 0);

    await tester.pumpWidget(
      _matchingApp(
        key: const ValueKey('third-match'),
        nativeAdUnitId: 'ca-app-pub-test/native',
        nativeAdCardBuilder: builder,
      ),
    );

    expect(requestCount, 1);

    await tester.pumpWidget(
      _matchingApp(
        key: const ValueKey('fourth-match'),
        nativeAdUnitId: 'ca-app-pub-test/native',
        nativeAdCardBuilder: builder,
      ),
    );
    await tester.pumpWidget(
      _matchingApp(
        key: const ValueKey('fifth-match'),
        nativeAdUnitId: 'ca-app-pub-test/native',
        nativeAdCardBuilder: builder,
      ),
    );

    expect(requestCount, 1);

    await tester.pumpWidget(
      _matchingApp(
        key: const ValueKey('sixth-match'),
        nativeAdUnitId: 'ca-app-pub-test/native',
        nativeAdCardBuilder: builder,
      ),
    );

    expect(requestCount, 2);
  });

  testWidgets('matching ad layout keeps app controls outside the ad card', (
    tester,
  ) async {
    await tester.pumpWidget(_matchingApp(withAd: true, nativeAdUnitId: ''));

    expect(find.text('Reklami Kapat'), findsNothing);
    expect(find.text('Kisa bir ara'), findsOneWidget);
    expect(find.text('Durdur'), findsOneWidget);
    expect(find.byKey(const ValueKey('matching-progress-bar')), findsOneWidget);
  });
}

Future<void> _clearHiveBoxes() async {
  const boxes = [
    'app_session',
    'app_preferences',
    'app_content',
    'app_public_settings',
    'ai_prompt',
    'ai_characters',
    'ai_memory',
    'ai_pending_turns',
    'chat_messages',
    'chat_previews',
    'chat_outbox',
    'media_cache_index',
  ];

  for (final box in boxes) {
    try {
      if (Hive.isBoxOpen(box)) {
        await Hive.box<dynamic>(box).clear();
      } else {
        await Hive.deleteBoxFromDisk(box);
      }
    } catch (_) {}
  }
}

void _primeNextMatchingNativeAdRequest() {
  AdMobNativeAdFrequencyGate.resetForTesting();
  expect(AdMobNativeAdFrequencyGate.shouldRequestMatchNativeAd(), isFalse);
  expect(AdMobNativeAdFrequencyGate.shouldRequestMatchNativeAd(), isFalse);
}

MatchingNativeAdCardBuilder _countingNativeAdBuilder(VoidCallback onRequest) {
  return (
    BuildContext context, {
    required String adUnitId,
    required Widget fallback,
    required VoidCallback onLoaded,
    required VoidCallback onFailed,
  }) {
    onRequest();
    return const SizedBox.expand(key: ValueKey('native-ad-requested'));
  };
}

Widget _matchingApp({
  Key? key,
  bool withAd = false,
  bool premiumActive = false,
  required String nativeAdUnitId,
  MatchingNativeAdCardBuilder? nativeAdCardBuilder,
}) {
  return ProviderScope(
    child: CupertinoApp(
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      supportedLocales: AppLocalizations.supportedLocales,
      home: MatchingScreen(
        key: key,
        withAd: withAd,
        candidate: _matchCandidate(),
        premiumActiveOverride: premiumActive,
        nativeAdUnitIdOverride: nativeAdUnitId,
        nativeAdCardBuilder: nativeAdCardBuilder,
      ),
    ),
  );
}

AppMatchCandidate _matchCandidate() {
  return const AppMatchCandidate(
    id: 42,
    firstName: 'Ada',
    surname: 'Yilmaz',
    username: 'ada',
    online: true,
    premiumActive: false,
    photos: [],
  );
}
