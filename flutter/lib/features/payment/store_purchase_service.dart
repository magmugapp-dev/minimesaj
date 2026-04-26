import 'dart:async';

import 'package:in_app_purchase/in_app_purchase.dart';
import 'package:magmug/app_core.dart';

enum StorePurchaseKind { creditPack, subscription }

enum StorePurchaseProgress {
  checkingStore,
  queryingProduct,
  purchasing,
  verifying,
}

enum StorePurchaseStatus { success, cancelled, failure }

String _purchaseText(String key, String fallback) {
  return AppRuntimeText.instance.t(key, fallback);
}

@immutable
class StorePurchaseResult {
  final StorePurchaseStatus status;
  final String message;

  const StorePurchaseResult({required this.status, required this.message});

  bool get isSuccess => status == StorePurchaseStatus.success;
}

class StorePurchaseService {
  StorePurchaseService({InAppPurchase? inAppPurchase})
    : _inAppPurchase = inAppPurchase ?? InAppPurchase.instance;

  final InAppPurchase _inAppPurchase;

  Future<StorePurchaseResult> purchase({
    required String token,
    required String productCode,
    required StorePurchaseKind kind,
    required double amount,
    required String currency,
    void Function(StorePurchaseProgress progress)? onProgress,
  }) async {
    final platform = currentMobileStorePlatform();
    if (platform == null) {
      return StorePurchaseResult(
        status: StorePurchaseStatus.failure,
        message: _purchaseText(
          'payment.store.error.unsupported_device',
          'Bu cihazda App Store veya Play Store satin alma desteklenmiyor.',
        ),
      );
    }

    final normalizedProductCode = productCode.trim();
    if (normalizedProductCode.isEmpty) {
      return StorePurchaseResult(
        status: StorePurchaseStatus.failure,
        message: _purchaseText(
          'payment.store.error.missing_product_code',
          'Panelde secili paket icin magaza urun kodu eksik.',
        ),
      );
    }

    onProgress?.call(StorePurchaseProgress.checkingStore);
    final isAvailable = await _inAppPurchase.isAvailable();
    if (!isAvailable) {
      return StorePurchaseResult(
        status: StorePurchaseStatus.failure,
        message: _purchaseText(
          'payment.store.error.unavailable',
          'Magaza servisine baglanilamadi. Lutfen daha sonra tekrar deneyin.',
        ),
      );
    }

    onProgress?.call(StorePurchaseProgress.queryingProduct);
    final productResponse = await _inAppPurchase.queryProductDetails({
      normalizedProductCode,
    });

    if (productResponse.error != null) {
      return StorePurchaseResult(
        status: StorePurchaseStatus.failure,
        message:
            productResponse.error?.message ??
            _purchaseText(
              'payment.store.error.product_details_failed',
              'Magaza urun bilgisi okunamadi. Panel urun kodlarini kontrol edin.',
            ),
      );
    }

    if (productResponse.notFoundIDs.contains(normalizedProductCode) ||
        productResponse.productDetails.isEmpty) {
      return StorePurchaseResult(
        status: StorePurchaseStatus.failure,
        message: _purchaseText(
          'payment.store.error.product_not_found',
          'Secilen paket App Store veya Play Store tarafinda bulunamadi. Panel urun kodlarini kontrol edin.',
        ),
      );
    }

    final product = productResponse.productDetails.firstWhere(
      (item) => item.id == normalizedProductCode,
      orElse: () => productResponse.productDetails.first,
    );

    final completer = Completer<StorePurchaseResult>();
    final api = AppAuthApi();
    late final StreamSubscription<List<PurchaseDetails>> subscription;

    Future<void> finish(StorePurchaseResult result) async {
      if (!completer.isCompleted) {
        completer.complete(result);
      }
    }

    subscription = _inAppPurchase.purchaseStream.listen(
      (purchases) async {
        for (final purchase in purchases) {
          if (purchase.productID != normalizedProductCode) {
            continue;
          }

          switch (purchase.status) {
            case PurchaseStatus.pending:
              onProgress?.call(StorePurchaseProgress.purchasing);
              break;
            case PurchaseStatus.purchased:
            case PurchaseStatus.restored:
              try {
                onProgress?.call(StorePurchaseProgress.verifying);
                await api.verifyPurchase(
                  token,
                  platform: platform,
                  receiptData: purchase.verificationData.serverVerificationData,
                  productCode: normalizedProductCode,
                  productType: kind == StorePurchaseKind.subscription
                      ? 'abonelik'
                      : 'tek_seferlik',
                  amount: amount,
                  currency: currency,
                );
                if (purchase.pendingCompletePurchase) {
                  await _inAppPurchase.completePurchase(purchase);
                }
                await finish(
                  StorePurchaseResult(
                    status: StorePurchaseStatus.success,
                    message: _purchaseText(
                      'payment.store.success.verified',
                      'Odeme dogrulandi.',
                    ),
                  ),
                );
              } catch (error) {
                if (purchase.pendingCompletePurchase) {
                  await _inAppPurchase.completePurchase(purchase);
                }
                await finish(
                  StorePurchaseResult(
                    status: StorePurchaseStatus.failure,
                    message: AppAuthErrorFormatter.messageFrom(error),
                  ),
                );
              }
              break;
            case PurchaseStatus.error:
              if (purchase.pendingCompletePurchase) {
                await _inAppPurchase.completePurchase(purchase);
              }
              await finish(
                StorePurchaseResult(
                  status: StorePurchaseStatus.failure,
                  message:
                      purchase.error?.message ??
                      _purchaseText(
                        'payment.store.error.purchase_failed',
                        'Magaza satin alma islemi basarisiz oldu.',
                      ),
                ),
              );
              break;
            case PurchaseStatus.canceled:
              await finish(
                StorePurchaseResult(
                  status: StorePurchaseStatus.cancelled,
                  message: _purchaseText(
                    'payment.store.cancelled',
                    'Satin alma islemi iptal edildi.',
                  ),
                ),
              );
              break;
          }
        }
      },
      onError: (Object error) async {
        await finish(
          StorePurchaseResult(
            status: StorePurchaseStatus.failure,
            message: AppAuthErrorFormatter.messageFrom(error),
          ),
        );
      },
    );

    try {
      onProgress?.call(StorePurchaseProgress.purchasing);
      final purchaseParam = PurchaseParam(productDetails: product);
      final started = kind == StorePurchaseKind.creditPack
          ? await _inAppPurchase.buyConsumable(
              purchaseParam: purchaseParam,
              autoConsume: platform == 'android',
            )
          : await _inAppPurchase.buyNonConsumable(purchaseParam: purchaseParam);

      if (!started) {
        return StorePurchaseResult(
          status: StorePurchaseStatus.failure,
          message: _purchaseText(
            'payment.store.error.flow_not_started',
            'Magaza satin alma akisi baslatilamadi.',
          ),
        );
      }

      return await completer.future.timeout(
        const Duration(minutes: 2),
        onTimeout: () => StorePurchaseResult(
          status: StorePurchaseStatus.failure,
          message: _purchaseText(
            'payment.store.error.timeout',
            'Magaza yaniti zaman asimina ugradi. Lutfen tekrar deneyin.',
          ),
        ),
      );
    } finally {
      await subscription.cancel();
      api.close();
    }
  }
}
