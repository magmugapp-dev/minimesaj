import 'package:flutter/cupertino.dart';

String? _nullableString(String? value) {
  final normalized = value?.trim();
  if (normalized == null || normalized.isEmpty) {
    return null;
  }
  return normalized;
}

Map<String, dynamic>? _asMap(Object? value) {
  if (value is! Map) {
    return null;
  }

  return value.map((key, item) => MapEntry(key.toString(), item));
}

@immutable
class AppProfilePhoto {
  final int id;
  final String url;
  final String? previewUrl;
  final int order;
  final bool isPrimary;
  final bool isActive;
  final String mediaType;
  final String? mimeType;
  final int? durationSeconds;

  const AppProfilePhoto({
    required this.id,
    required this.url,
    this.previewUrl,
    required this.order,
    required this.isPrimary,
    required this.isActive,
    required this.mediaType,
    this.mimeType,
    this.durationSeconds,
  });

  bool get isVideo => mediaType == 'video';

  bool get isPhoto => !isVideo;

  String get displayUrl => previewUrl ?? url;

  factory AppProfilePhoto.fromJson(Map<String, dynamic> json) {
    return AppProfilePhoto(
      id: (json['id'] as num?)?.toInt() ?? 0,
      url: json['dosya_yolu']?.toString() ?? '',
      previewUrl: _nullableString(json['onizleme_yolu']?.toString()),
      order: (json['sira_no'] as num?)?.toInt() ?? 0,
      isPrimary: json['ana_fotograf_mi'] == true,
      isActive: json['aktif_mi'] != false,
      mediaType: json['medya_tipi']?.toString() == 'video'
          ? 'video'
          : 'fotograf',
      mimeType: _nullableString(json['mime_tipi']?.toString()),
      durationSeconds: (json['sure_saniye'] as num?)?.toInt(),
    );
  }
}

@immutable
class AppGift {
  final int id;
  final String code;
  final String name;
  final String icon;
  final int cost;
  final bool active;
  final int order;

  const AppGift({
    required this.id,
    required this.code,
    required this.name,
    required this.icon,
    required this.cost,
    required this.active,
    required this.order,
  });

  factory AppGift.fromJson(Map<String, dynamic> json) {
    return AppGift(
      id: (json['id'] as num?)?.toInt() ?? 0,
      code: json['kod']?.toString() ?? '',
      name: json['ad']?.toString() ?? '',
      icon: _nullableString(json['ikon']?.toString()) ?? '\u{1F381}',
      cost: (json['puan_bedeli'] as num?)?.toInt() ?? 0,
      active: json['aktif'] != false,
      order: (json['sira'] as num?)?.toInt() ?? 0,
    );
  }
}

@immutable
class AppGiftSender {
  final int id;
  final String firstName;
  final String surname;
  final String username;
  final String? profileImageUrl;

  const AppGiftSender({
    required this.id,
    required this.firstName,
    required this.surname,
    required this.username,
    this.profileImageUrl,
  });

  String get displayName {
    final value = '$firstName $surname'.trim();
    return value.isEmpty ? username : value;
  }

  factory AppGiftSender.fromJson(Map<String, dynamic> json) {
    return AppGiftSender(
      id: (json['id'] as num?)?.toInt() ?? 0,
      firstName: json['ad']?.toString() ?? '',
      surname: json['soyad']?.toString() ?? '',
      username: json['kullanici_adi']?.toString() ?? '',
      profileImageUrl: _nullableString(json['profil_resmi']?.toString()),
    );
  }
}

@immutable
class AppReceivedGift {
  final int id;
  final int? giftId;
  final String name;
  final String icon;
  final int cost;
  final AppGiftSender? sender;
  final DateTime? createdAt;

  const AppReceivedGift({
    required this.id,
    this.giftId,
    required this.name,
    required this.icon,
    required this.cost,
    this.sender,
    this.createdAt,
  });

  factory AppReceivedGift.fromJson(Map<String, dynamic> json) {
    final sender = _asMap(json['gonderen']);

    return AppReceivedGift(
      id: (json['id'] as num?)?.toInt() ?? 0,
      giftId: (json['hediye_id'] as num?)?.toInt(),
      name: json['hediye_adi']?.toString() ?? 'Hediye',
      icon: _nullableString(json['hediye_ikon']?.toString()) ?? '\u{1F381}',
      cost: (json['puan_bedeli'] as num?)?.toInt() ?? 0,
      sender: sender == null ? null : AppGiftSender.fromJson(sender),
      createdAt: DateTime.tryParse(json['created_at']?.toString() ?? ''),
    );
  }
}

@immutable
class AppMatchFilters {
  final String genderCode;
  final String ageCode;
  final bool superMatchEnabled;

  const AppMatchFilters({
    required this.genderCode,
    required this.ageCode,
    required this.superMatchEnabled,
  });

  factory AppMatchFilters.fromJson(Map<String, dynamic> json) {
    return AppMatchFilters(
      genderCode: _nullableString(json['cinsiyet']?.toString()) ?? 'tum',
      ageCode: _nullableString(json['yas']?.toString()) ?? 'tum',
      superMatchEnabled: json['super_eslesme_aktif_mi'] == true,
    );
  }
}

@immutable
class AppMatchCenterSummary {
  final int gemBalance;
  final int freeMatchesLeft;
  final int startCost;
  final int onlineCount;
  final int waitingLikes;
  final AppMatchFilters filters;

  const AppMatchCenterSummary({
    required this.gemBalance,
    required this.freeMatchesLeft,
    required this.startCost,
    required this.onlineCount,
    required this.waitingLikes,
    required this.filters,
  });

  factory AppMatchCenterSummary.fromJson(Map<String, dynamic> json) {
    final filters = _asMap(json['filtreler']) ?? const <String, dynamic>{};

    return AppMatchCenterSummary(
      gemBalance: (json['mevcut_puan'] as num?)?.toInt() ?? 0,
      freeMatchesLeft: (json['gunluk_ucretsiz_hak'] as num?)?.toInt() ?? 0,
      startCost: (json['eslesme_baslatma_maliyeti'] as num?)?.toInt() ?? 0,
      onlineCount: (json['cevrimici_kisi_sayisi'] as num?)?.toInt() ?? 0,
      waitingLikes: (json['bekleyen_kisi_sayisi'] as num?)?.toInt() ?? 0,
      filters: AppMatchFilters.fromJson(filters),
    );
  }
}

@immutable
class AppMatchCandidate {
  final int id;
  final String firstName;
  final String surname;
  final String username;
  final String? profileImageUrl;
  final String? bio;
  final String? city;
  final int? birthYear;
  final bool online;
  final bool premiumActive;
  final List<AppProfilePhoto> photos;
  final List<AppReceivedGift> receivedGifts;
  final bool muted;
  final bool blocked;
  final DateTime? muteEndsAt;

  const AppMatchCandidate({
    required this.id,
    required this.firstName,
    required this.surname,
    required this.username,
    this.profileImageUrl,
    this.bio,
    this.city,
    this.birthYear,
    required this.online,
    required this.premiumActive,
    required this.photos,
    this.receivedGifts = const [],
    this.muted = false,
    this.blocked = false,
    this.muteEndsAt,
  });

  String get displayName {
    final value = '$firstName $surname'.trim();
    return value.isEmpty ? username : value;
  }

  int? get age {
    final year = birthYear;
    if (year == null || year <= 0) {
      return null;
    }
    return DateTime.now().year - year;
  }

  String? get primaryImageUrl {
    for (final item in photos) {
      if (item.isPhoto && item.displayUrl.trim().isNotEmpty) {
        return item.displayUrl;
      }
    }

    return _nullableString(profileImageUrl);
  }

  factory AppMatchCandidate.fromJson(Map<String, dynamic> json) {
    final rawPhotos = json['fotograflar'];
    final photos = rawPhotos is List
        ? rawPhotos.whereType<Map>().map((item) {
            return AppProfilePhoto.fromJson(
              item.map((key, value) => MapEntry(key.toString(), value)),
            );
          }).toList()
        : const <AppProfilePhoto>[];
    final rawGifts = json['alinan_hediyeler'];
    final receivedGifts = rawGifts is List
        ? rawGifts.whereType<Map>().map((item) {
            return AppReceivedGift.fromJson(
              item.map((key, value) => MapEntry(key.toString(), value)),
            );
          }).toList()
        : const <AppReceivedGift>[];

    return AppMatchCandidate(
      id: (json['id'] as num?)?.toInt() ?? 0,
      firstName: json['ad']?.toString() ?? '',
      surname: json['soyad']?.toString() ?? '',
      username: json['kullanici_adi']?.toString() ?? '',
      profileImageUrl: _nullableString(json['profil_resmi']?.toString()),
      bio: _nullableString(json['biyografi']?.toString()),
      city: _nullableString(json['il']?.toString()),
      birthYear: (json['dogum_yili'] as num?)?.toInt(),
      online: json['cevrim_ici_mi'] == true,
      premiumActive: json['premium_aktif_mi'] == true,
      photos: photos,
      receivedGifts: receivedGifts,
      muted: json['sessize_alindi_mi'] == true,
      blocked: json['engellendi_mi'] == true,
      muteEndsAt: DateTime.tryParse(
        json['sessiz_bitis_tarihi']?.toString() ?? '',
      ),
    );
  }
}

enum AppMatchStartStatus { candidateFound, insufficientCredits, noCandidate }

@immutable
class AppMatchStartResult {
  final AppMatchStartStatus status;
  final AppMatchCandidate? candidate;
  final int gemBalance;
  final int freeMatchesLeft;
  final int startCost;
  final int? requiredCredits;
  final int? missingCredits;
  final bool usedFreeMatch;
  final String? message;

  const AppMatchStartResult({
    required this.status,
    required this.candidate,
    required this.gemBalance,
    required this.freeMatchesLeft,
    required this.startCost,
    required this.requiredCredits,
    required this.missingCredits,
    required this.usedFreeMatch,
    required this.message,
  });

  factory AppMatchStartResult.fromJson(Map<String, dynamic> json) {
    final statusRaw = json['durum']?.toString();
    final status = switch (statusRaw) {
      'aday_bulundu' => AppMatchStartStatus.candidateFound,
      'yetersiz_puan' => AppMatchStartStatus.insufficientCredits,
      _ => AppMatchStartStatus.noCandidate,
    };

    final candidateMap = _asMap(json['aday']);

    return AppMatchStartResult(
      status: status,
      candidate: candidateMap == null
          ? null
          : AppMatchCandidate.fromJson(candidateMap),
      gemBalance: (json['mevcut_puan'] as num?)?.toInt() ?? 0,
      freeMatchesLeft: (json['gunluk_ucretsiz_hak'] as num?)?.toInt() ?? 0,
      startCost: (json['eslesme_baslatma_maliyeti'] as num?)?.toInt() ?? 0,
      requiredCredits: (json['gerekli_puan'] as num?)?.toInt(),
      missingCredits: (json['eksik_puan'] as num?)?.toInt(),
      usedFreeMatch: json['ucretsiz_hak_kullanildi'] == true,
      message: _nullableString(json['mesaj']?.toString()),
    );
  }
}

@immutable
class AppDirectMatchConversationResult {
  final int? matchId;
  final int? conversationId;
  final String? message;

  const AppDirectMatchConversationResult({
    required this.matchId,
    required this.conversationId,
    required this.message,
  });

  factory AppDirectMatchConversationResult.fromJson(Map<String, dynamic> json) {
    return AppDirectMatchConversationResult(
      matchId: (json['eslesme_id'] as num?)?.toInt(),
      conversationId: (json['sohbet_id'] as num?)?.toInt(),
      message: _nullableString(json['mesaj']?.toString()),
    );
  }
}
