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
