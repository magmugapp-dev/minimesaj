import 'dart:async';

import 'package:google_mobile_ads/google_mobile_ads.dart';
import 'package:magmug/app_core.dart';

class RewardedAdMobResult {
  final bool earnedReward;
  final String? errorMessage;

  const RewardedAdMobResult({required this.earnedReward, this.errorMessage});
}

class AdMobRewardedAdService {
  const AdMobRewardedAdService();

  Future<RewardedAdMobResult> showRewardedAd({required String adUnitId}) async {
    final loadCompleter = Completer<RewardedAd>();

    RewardedAd.load(
      adUnitId: adUnitId,
      request: const AdRequest(),
      rewardedAdLoadCallback: RewardedAdLoadCallback(
        onAdLoaded: loadCompleter.complete,
        onAdFailedToLoad: (error) {
          if (!loadCompleter.isCompleted) {
            loadCompleter.completeError(error);
          }
        },
      ),
    );

    late final RewardedAd ad;
    try {
      ad = await loadCompleter.future.timeout(const Duration(seconds: 15));
    } catch (error) {
      return RewardedAdMobResult(
        earnedReward: false,
        errorMessage: AppRuntimeText.instance.t(
          'rewardAdLoadFailed',
          'Reklam su anda yuklenemedi.',
        ),
      );
    }

    final showCompleter = Completer<RewardedAdMobResult>();
    var earnedReward = false;

    ad.fullScreenContentCallback = FullScreenContentCallback(
      onAdDismissedFullScreenContent: (ad) {
        ad.dispose();
        if (!showCompleter.isCompleted) {
          showCompleter.complete(
            RewardedAdMobResult(earnedReward: earnedReward),
          );
        }
      },
      onAdFailedToShowFullScreenContent: (ad, error) {
        ad.dispose();
        if (!showCompleter.isCompleted) {
          showCompleter.complete(
            RewardedAdMobResult(
              earnedReward: false,
              errorMessage: AppRuntimeText.instance.t(
                'rewardAdShowFailed',
                'Reklam gosterilemedi.',
              ),
            ),
          );
        }
      },
    );

    ad.show(
      onUserEarnedReward: (ad, reward) {
        earnedReward = true;
      },
    );

    return showCompleter.future.timeout(
      const Duration(minutes: 2),
      onTimeout: () {
        ad.dispose();
        return RewardedAdMobResult(earnedReward: earnedReward);
      },
    );
  }
}

class AdMobNativeAdFrequencyGate {
  static const int matchNativeInterval = 3;

  static int _eligibleMatchCount = 0;

  const AdMobNativeAdFrequencyGate._();

  static bool shouldRequestMatchNativeAd() {
    _eligibleMatchCount++;

    if (_eligibleMatchCount >= matchNativeInterval) {
      _eligibleMatchCount = 0;
      return true;
    }

    return false;
  }

  static void resetForTesting() {
    _eligibleMatchCount = 0;
  }
}

class AdMobNativeAdCard extends StatefulWidget {
  final String? adUnitId;
  final Widget fallback;
  final VoidCallback? onLoaded;
  final VoidCallback? onFailed;

  const AdMobNativeAdCard({
    super.key,
    required this.adUnitId,
    required this.fallback,
    this.onLoaded,
    this.onFailed,
  });

  @override
  State<AdMobNativeAdCard> createState() => _AdMobNativeAdCardState();
}

class _AdMobNativeAdCardState extends State<AdMobNativeAdCard> {
  NativeAd? _nativeAd;
  bool _loaded = false;
  bool _failed = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void didUpdateWidget(covariant AdMobNativeAdCard oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.adUnitId != widget.adUnitId) {
      _nativeAd?.dispose();
      _nativeAd = null;
      _loaded = false;
      _failed = false;
      _load();
    }
  }

  @override
  void dispose() {
    _nativeAd?.dispose();
    super.dispose();
  }

  void _load() {
    final adUnitId = widget.adUnitId?.trim();
    if (adUnitId == null || adUnitId.isEmpty) {
      _failed = true;
      scheduleMicrotask(() => widget.onFailed?.call());
      return;
    }

    final ad = NativeAd(
      adUnitId: adUnitId,
      request: const AdRequest(),
      nativeTemplateStyle: NativeTemplateStyle(
        templateType: TemplateType.medium,
      ),
      listener: NativeAdListener(
        onAdLoaded: (ad) {
          if (!mounted) {
            ad.dispose();
            return;
          }
          setState(() {
            _nativeAd = ad as NativeAd;
            _loaded = true;
            _failed = false;
          });
          widget.onLoaded?.call();
        },
        onAdFailedToLoad: (ad, error) {
          ad.dispose();
          if (!mounted) {
            return;
          }
          setState(() {
            _loaded = false;
            _failed = true;
          });
          widget.onFailed?.call();
        },
      ),
    );

    ad.load();
  }

  @override
  Widget build(BuildContext context) {
    final ad = _nativeAd;

    if (!_loaded || _failed || ad == null) {
      return widget.fallback;
    }

    return ClipRRect(
      borderRadius: BorderRadius.circular(24),
      child: AdWidget(ad: ad),
    );
  }
}
