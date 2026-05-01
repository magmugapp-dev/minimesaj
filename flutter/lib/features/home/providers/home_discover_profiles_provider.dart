import 'package:magmug/app_core.dart';

List<AppMatchCandidate> _cachedDiscoverProfiles = const <AppMatchCandidate>[];
DateTime? _cachedDiscoverProfilesAt;
const Duration _discoverProfilesTtl = Duration(minutes: 5);

final homeDiscoverProfilesProvider = FutureProvider<List<AppMatchCandidate>>((
  ref,
) async {
  final session = await ref.watch(appAuthProvider.future);
  final token = session?.token;
  final ownerUserId = session?.user?.id;
  if (token == null || token.trim().isEmpty) {
    return const [];
  }

  final cachedAt = _cachedDiscoverProfilesAt;
  if (cachedAt != null &&
      DateTime.now().difference(cachedAt) < _discoverProfilesTtl &&
      _cachedDiscoverProfiles.isNotEmpty) {
    return _cachedDiscoverProfiles;
  }

  if (ownerUserId != null) {
    final persisted = await _readDiscoverProfiles(ownerUserId);
    if (persisted.isNotEmpty) {
      _cachedDiscoverProfiles = persisted;
      _cachedDiscoverProfilesAt = DateTime.now();
      return persisted;
    }
  }

  final api = AppAuthApi();
  try {
    final profiles = await api.fetchDiscoverProfiles(token, limit: 4);
    _cachedDiscoverProfiles = profiles;
    _cachedDiscoverProfilesAt = DateTime.now();
    if (ownerUserId != null) {
      await _writeDiscoverProfiles(ownerUserId, profiles);
    }
    return profiles;
  } catch (_) {
    return _cachedDiscoverProfiles;
  } finally {
    api.close();
  }
});

Future<List<AppMatchCandidate>> _readDiscoverProfiles(int ownerUserId) async {
  final box = await AppHiveBoxes.discoverProfiles();
  final row = _asMap(box.get('$ownerUserId:home'));
  final expiresAtMs = (row?['expires_at_ms'] as num?)?.toInt() ?? 0;
  final data = row?['data'];
  if (expiresAtMs <= DateTime.now().millisecondsSinceEpoch || data is! List) {
    return const [];
  }

  try {
    return data
        .map(_asMap)
        .whereType<Map<String, dynamic>>()
        .map(AppMatchCandidate.fromJson)
        .where((candidate) => candidate.id > 0)
        .toList(growable: false);
  } catch (_) {
    await box.delete('$ownerUserId:home');
    return const [];
  }
}

Future<void> _writeDiscoverProfiles(
  int ownerUserId,
  List<AppMatchCandidate> profiles,
) async {
  await (await AppHiveBoxes.discoverProfiles()).put('$ownerUserId:home', {
    'expires_at_ms': DateTime.now()
        .add(_discoverProfilesTtl)
        .millisecondsSinceEpoch,
    'data': profiles.map(_candidateToJson).toList(growable: false),
  });
}

Map<String, dynamic>? _asMap(Object? value) {
  if (value is Map<String, dynamic>) {
    return value;
  }
  if (value is Map) {
    return value.map((key, val) => MapEntry(key.toString(), val));
  }
  return null;
}

Map<String, dynamic> _candidateToJson(AppMatchCandidate value) => {
  'id': value.id,
  'ad': value.firstName,
  'soyad': value.surname,
  'kullanici_adi': value.username,
  'profil_resmi': value.profileImageUrl,
  'biyografi': value.bio,
  'il': value.city,
  'dogum_yili': value.birthYear,
  'cevrim_ici_mi': value.online,
  'premium_aktif_mi': value.premiumActive,
  'fotograflar': value.photos
      .map(
        (photo) => {
          'id': photo.id,
          'dosya_yolu': photo.url,
          'onizleme_yolu': photo.previewUrl,
          'sira_no': photo.order,
          'ana_fotograf_mi': photo.isPrimary,
          'aktif_mi': photo.isActive,
          'medya_tipi': photo.mediaType == 'video' ? 'video' : 'fotograf',
          'mime_tipi': photo.mimeType,
          'sure_saniye': photo.durationSeconds,
        },
      )
      .toList(growable: false),
  'alinan_hediyeler': const <Map<String, dynamic>>[],
  'sessize_alindi_mi': value.muted,
  'engellendi_mi': value.blocked,
  'sessiz_bitis_tarihi': value.muteEndsAt?.toIso8601String(),
};
