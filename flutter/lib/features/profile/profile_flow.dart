import 'dart:io';

import 'package:magmug/app_core.dart';
import 'package:image_picker/image_picker.dart';
import 'package:magmug/features/match/match_flow.dart';
import 'package:magmug/features/onboarding/onboarding_flow.dart';

// =============================================================================

enum AppLanguage { tr, en, de, fr }

@immutable
class NotificationPrefs {
  final bool messages;
  final bool matches;
  final bool likes;
  final bool campaigns;

  const NotificationPrefs({
    this.messages = true,
    this.matches = true,
    this.likes = false,
    this.campaigns = false,
  });

  NotificationPrefs copyWith({
    bool? messages,
    bool? matches,
    bool? likes,
    bool? campaigns,
  }) {
    return NotificationPrefs(
      messages: messages ?? this.messages,
      matches: matches ?? this.matches,
      likes: likes ?? this.likes,
      campaigns: campaigns ?? this.campaigns,
    );
  }
}

class NotificationPrefsNotifier extends Notifier<NotificationPrefs> {
  @override
  NotificationPrefs build() => const NotificationPrefs();

  void set(NotificationPrefs next) => state = next;
}

final notifPrefsProvider =
    NotifierProvider<NotificationPrefsNotifier, NotificationPrefs>(
      NotificationPrefsNotifier.new,
    );

@immutable
class BlockedUser {
  final String name;
  final String handle;
  final String? avatarAsset;

  const BlockedUser({
    required this.name,
    required this.handle,
    this.avatarAsset,
  });
}

const List<BlockedUser> _mockBlocked = [
  BlockedUser(
    name: 'Ayse K.',
    handle: '@ayse.k',
    avatarAsset: 'assets/images/gallery_4.png',
  ),
  BlockedUser(
    name: 'Zeynep A.',
    handle: '@zeynep.a',
    avatarAsset: 'assets/images/gallery_5.png',
  ),
];

// ------ Shared primitives -----------------------------------------------------

class _SheetHandle extends StatelessWidget {
  const _SheetHandle();

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Container(
        width: 48,
        height: 4,
        decoration: BoxDecoration(
          color: const Color(0xFFD4D4D4),
          borderRadius: BorderRadius.circular(8),
        ),
      ),
    );
  }
}

class _SettingsTile extends StatelessWidget {
  final IconData icon;
  final String label;
  final String? trailingText;
  final String? badgeCount;
  final bool danger;
  final bool showDivider;
  final VoidCallback onTap;

  const _SettingsTile({
    required this.icon,
    required this.label,
    required this.onTap,
    this.trailingText,
    this.badgeCount,
    this.danger = false,
    this.showDivider = false,
  });

  @override
  Widget build(BuildContext context) {
    final color = danger ? const Color(0xFFEF4444) : AppColors.black;
    final bg = danger ? const Color(0xFFFEF2F2) : AppColors.grayField;

    return Column(
      children: [
        PressableScale(
          onTap: onTap,
          scale: 0.99,
          child: Container(
            height: 62,
            padding: const EdgeInsets.symmetric(horizontal: 18),
            child: Row(
              children: [
                Container(
                  width: 34,
                  height: 34,
                  decoration: BoxDecoration(
                    color: bg,
                    borderRadius: BorderRadius.circular(10),
                  ),
                  alignment: Alignment.center,
                  child: Icon(icon, size: 16, color: color),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    label,
                    style: TextStyle(
                      fontFamily: AppFont.family,
                      fontWeight: FontWeight.w600,
                      fontSize: 14,
                      color: color,
                    ),
                  ),
                ),
                if (trailingText != null)
                  Text(
                    trailingText!,
                    style: const TextStyle(
                      fontFamily: AppFont.family,
                      fontWeight: FontWeight.w600,
                      fontSize: 12,
                      color: Color(0xFF999999),
                    ),
                  ),
                if (badgeCount != null) ...[
                  const SizedBox(width: 8),
                  Text(
                    badgeCount!,
                    style: const TextStyle(
                      fontFamily: AppFont.family,
                      fontWeight: FontWeight.w600,
                      fontSize: 12,
                      color: Color(0xFF999999),
                    ),
                  ),
                ],
                if (!danger) ...[
                  const SizedBox(width: 10),
                  const Icon(
                    CupertinoIcons.chevron_right,
                    size: 14,
                    color: Color(0xFFBBBBBB),
                  ),
                ],
              ],
            ),
          ),
        ),
        if (showDivider)
          Container(
            margin: const EdgeInsets.symmetric(horizontal: 18),
            height: 1,
            color: const Color(0xFFF0F0F0),
          ),
      ],
    );
  }
}

class _SettingsGroup extends StatelessWidget {
  final List<Widget> children;

  const _SettingsGroup({required this.children});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(children: children),
    );
  }
}

// ------ Profile Screen --------------------------------------------------------

class ProfileScreen extends ConsumerWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final gem = ref.watch(matchProvider.select((s) => s.gemBalance));

    void openSheet(Widget sheet) {
      showCupertinoModalPopup<void>(context: context, builder: (_) => sheet);
    }

    return CupertinoPageScaffold(
      backgroundColor: AppColors.neutral100,
      child: SafeArea(
        bottom: false,
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
              child: Row(
                children: [
                  PressableScale(
                    onTap: () => Navigator.of(context).maybePop(),
                    scale: 0.9,
                    child: const Padding(
                      padding: EdgeInsets.all(4),
                      child: Icon(
                        CupertinoIcons.chevron_back,
                        size: 22,
                        color: AppColors.black,
                      ),
                    ),
                  ),
                  const SizedBox(width: 6),
                  const Expanded(
                    child: Text(
                      'Profilim',
                      style: TextStyle(
                        fontFamily: AppFont.family,
                        fontWeight: FontWeight.w800,
                        fontSize: 18,
                        color: AppColors.black,
                      ),
                    ),
                  ),
                  BalanceChip(amount: gem),
                ],
              ),
            ),
            Expanded(
              child: ListView(
                physics: const BouncingScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 32),
                children: [
                  const _ProfileHeader(),
                  const SizedBox(height: 20),
                  _PremiumPromoBanner(
                    onTap: () => Navigator.of(
                      context,
                    ).push(cupertinoRoute(const PaywallScreen())),
                  ),
                  const SizedBox(height: 12),
                  _SettingsGroup(
                    children: [
                      _SettingsTile(
                        icon: CupertinoIcons.star_circle_fill,
                        label: 'Tas Bakiyesi',
                        trailingText: formatGem(gem),
                        onTap: () => openSheet(const JetonPurchaseSheet()),
                      ),
                    ],
                  ),
                  _SettingsGroup(
                    children: [
                      _SettingsTile(
                        icon: CupertinoIcons.chat_bubble_2_fill,
                        label: 'Bize Ulasin',
                        trailingText: 'WhatsApp',
                        onTap: () {},
                      ),
                    ],
                  ),
                  const _ProfileMediaSection(),
                  const SizedBox(height: 16),
                  _SettingsGroup(
                    children: [
                      _SettingsTile(
                        icon: CupertinoIcons.bell,
                        label: 'Bildirimler',
                        showDivider: true,
                        onTap: () => openSheet(const NotificationPrefsSheet()),
                      ),
                      _SettingsTile(
                        icon: CupertinoIcons.globe,
                        label: 'Dil',
                        trailingText: 'Turkce',
                        showDivider: true,
                        onTap: () => openSheet(const LanguageSheet()),
                      ),
                      _SettingsTile(
                        icon: CupertinoIcons.nosign,
                        label: 'Engellenen Kullanicilar',
                        badgeCount: '${_mockBlocked.length}',
                        onTap: () => openSheet(const BlockedUsersSheet()),
                      ),
                    ],
                  ),
                  _SettingsGroup(
                    children: [
                      _SettingsTile(
                        icon: CupertinoIcons.question_circle,
                        label: 'Yardim',
                        showDivider: true,
                        onTap: () => openSheet(const HelpSheet()),
                      ),
                      _SettingsTile(
                        icon: CupertinoIcons.arrow_clockwise,
                        label: 'Satin Alimlari Geri Yukle',
                        onTap: () {},
                      ),
                    ],
                  ),
                  _SettingsGroup(
                    children: [
                      _SettingsTile(
                        icon: CupertinoIcons.shield,
                        label: 'Gizlilik Politikasi',
                        showDivider: true,
                        onTap: () => Navigator.of(
                          context,
                        ).push(cupertinoRoute(const PrivacyPolicyScreen())),
                      ),
                      _SettingsTile(
                        icon: CupertinoIcons.doc_text,
                        label: 'KVKK Aydinlatma Metni',
                        showDivider: true,
                        onTap: () => Navigator.of(
                          context,
                        ).push(cupertinoRoute(const KvkkScreen())),
                      ),
                      _SettingsTile(
                        icon: CupertinoIcons.doc_plaintext,
                        label: 'Kullanim Kosullari',
                        onTap: () => Navigator.of(
                          context,
                        ).push(cupertinoRoute(const TermsOfUseScreen())),
                      ),
                    ],
                  ),
                  _SettingsGroup(
                    children: [
                      _SettingsTile(
                        icon: CupertinoIcons.arrow_right_square,
                        label: 'Cikis Yap',
                        danger: true,
                        showDivider: true,
                        onTap: () => openSheet(const SignOutConfirmSheet()),
                      ),
                      _SettingsTile(
                        icon: CupertinoIcons.trash,
                        label: 'Hesabi Sil',
                        danger: true,
                        onTap: () =>
                            openSheet(const DeleteAccountConfirmSheet()),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  const Center(
                    child: Text(
                      'magmug v1.0.0',
                      style: TextStyle(
                        fontFamily: AppFont.family,
                        fontSize: 11,
                        color: Color(0xFFCCCCCC),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ProfileHeader extends ConsumerWidget {
  const _ProfileHeader();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(appAuthProvider).asData?.value?.user;
    final data = ref.watch(onboardProvider);
    final localDisplayName = '${data.name} ${data.surname}'.trim();
    final displayName = user?.displayName.isNotEmpty == true
        ? user!.displayName
        : (localDisplayName.isNotEmpty ? localDisplayName : 'Mehmet');
    final username = user?.username.isNotEmpty == true
        ? user!.username
        : (data.username.isNotEmpty ? data.username : 'mehmet.k');
    final photo = data.photoPath;
    final remotePhoto = user?.profileImageUrl;

    return Column(
      children: [
        Stack(
          clipBehavior: Clip.none,
          children: [
            Container(
              width: 96,
              height: 96,
              decoration: const BoxDecoration(
                shape: BoxShape.circle,
                boxShadow: [
                  BoxShadow(
                    color: Color(0x14000000),
                    blurRadius: 16,
                    offset: Offset(0, 4),
                  ),
                ],
              ),
              child: ClipOval(
                child: photo != null
                    ? Image.file(File(photo), fit: BoxFit.cover)
                    : remotePhoto != null && remotePhoto.isNotEmpty
                    ? Image.network(remotePhoto, fit: BoxFit.cover)
                    : Image.asset(
                        'assets/images/portrait_self.png',
                        fit: BoxFit.cover,
                      ),
              ),
            ),
            Positioned(
              right: -4,
              bottom: 4,
              child: Container(
                width: 30,
                height: 30,
                decoration: BoxDecoration(
                  color: AppColors.black,
                  shape: BoxShape.circle,
                  border: Border.all(color: AppColors.neutral100, width: 3),
                ),
                alignment: Alignment.center,
                child: const Icon(
                  CupertinoIcons.camera_fill,
                  size: 13,
                  color: AppColors.white,
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 14),
        Text(
          displayName,
          style: const TextStyle(
            fontFamily: AppFont.family,
            fontWeight: FontWeight.w800,
            fontSize: 20,
            color: AppColors.black,
          ),
        ),
        const SizedBox(height: 2),
        Text(
          '@$username',
          style: const TextStyle(
            fontFamily: AppFont.family,
            fontSize: 13,
            color: Color(0xFF999999),
          ),
        ),
        const SizedBox(height: 12),
        PressableScale(
          onTap: () => showCupertinoModalPopup<void>(
            context: context,
            builder: (_) => const EditProfileSheet(),
          ),
          scale: 0.97,
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 10),
            decoration: BoxDecoration(
              color: AppColors.white,
              borderRadius: BorderRadius.circular(50),
              border: Border.all(color: const Color(0xFFE0E0E0)),
            ),
            child: const Text(
              'Profili Duzenle',
              style: TextStyle(
                fontFamily: AppFont.family,
                fontWeight: FontWeight.w700,
                fontSize: 13,
                color: AppColors.black,
              ),
            ),
          ),
        ),
      ],
    );
  }
}

class _PremiumPromoBanner extends StatelessWidget {
  final VoidCallback onTap;

  const _PremiumPromoBanner({required this.onTap});

  @override
  Widget build(BuildContext context) {
    return PressableScale(
      onTap: onTap,
      scale: 0.99,
      child: Container(
        height: 96,
        padding: const EdgeInsets.symmetric(horizontal: 16),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(24),
          gradient: const LinearGradient(
            begin: Alignment.centerLeft,
            end: Alignment.centerRight,
            colors: [Color(0xFF7C6DF5), Color(0xFFB194F9), Color(0xFFFF9EC4)],
          ),
          boxShadow: const [
            BoxShadow(
              color: Color(0x297C6DF5),
              blurRadius: 16,
              offset: Offset(0, 6),
            ),
          ],
        ),
        child: Row(
          children: [
            Image.asset(
              'assets/images/promo_char.png',
              width: 80,
              height: 80,
              fit: BoxFit.contain,
            ),
            const SizedBox(width: 12),
            const Expanded(
              child: Text(
                'Lorem ipsum\ndolor sit amet',
                style: TextStyle(
                  fontFamily: AppFont.family,
                  fontWeight: FontWeight.w800,
                  fontSize: 15,
                  color: AppColors.white,
                  height: 1.25,
                ),
              ),
            ),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
              decoration: BoxDecoration(
                color: AppColors.white,
                borderRadius: BorderRadius.circular(24),
              ),
              child: const Text(
                'Upgrade',
                style: TextStyle(
                  fontFamily: AppFont.family,
                  fontWeight: FontWeight.w700,
                  fontSize: 13,
                  color: Color(0xFF171717),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ProfileMediaSection extends ConsumerStatefulWidget {
  const _ProfileMediaSection();

  @override
  ConsumerState<_ProfileMediaSection> createState() =>
      _ProfileMediaSectionState();
}

class _ProfileMediaSectionState extends ConsumerState<_ProfileMediaSection> {
  final AppAuthApi _authApi = AppAuthApi();
  final ImagePicker _picker = ImagePicker();
  int _tab = 0;
  bool _loading = true;
  bool _uploading = false;
  String? _notice;
  List<AppProfilePhoto> _photos = const [];

  @override
  void initState() {
    super.initState();
    _loadPhotos();
  }

  @override
  void dispose() {
    _authApi.close();
    super.dispose();
  }

  Future<void> _loadPhotos() async {
    final token = ref.read(appAuthProvider).asData?.value?.token;
    if (token == null || token.trim().isEmpty) {
      if (!mounted) return;
      setState(() {
        _photos = const [];
        _loading = false;
      });
      return;
    }

    setState(() {
      _loading = true;
      _notice = null;
    });

    try {
      final photos = await _authApi.fetchProfilePhotos(token);
      if (!mounted) return;
      setState(() => _photos = photos);
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _notice = AppAuthErrorFormatter.messageFrom(error);
      });
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  Future<void> _pickFrom(ImageSource source) async {
    if (_uploading) return;

    try {
      final file = await _picker.pickImage(
        source: source,
        imageQuality: 85,
        maxWidth: 1600,
      );
      if (file != null) {
        await _uploadPhoto(file.path);
      }
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _notice = 'Fotograf secilirken izin veya cihaz hatasi olustu.';
      });
    }
  }

  Future<void> _uploadPhoto(String filePath) async {
    final token = ref.read(appAuthProvider).asData?.value?.token;
    if (token == null || token.trim().isEmpty) {
      setState(() {
        _notice = 'Fotograf yuklemek icin once giris yapmalisin.';
      });
      return;
    }

    final shouldMarkPrimary = _photos.isEmpty;

    setState(() {
      _uploading = true;
      _notice = null;
    });

    try {
      final photo = await _authApi.uploadProfilePhoto(
        token,
        filePath: filePath,
        markAsPrimary: shouldMarkPrimary,
      );
      if (!mounted) return;

      final nextPhotos = [..._photos, photo]
        ..sort((left, right) => left.order.compareTo(right.order));
      setState(() => _photos = nextPhotos);

      if (shouldMarkPrimary) {
        ref.read(onboardProvider.notifier).setPhoto(filePath);
      }
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _notice = AppAuthErrorFormatter.messageFrom(error);
      });
    } finally {
      if (mounted) {
        setState(() => _uploading = false);
      }
    }
  }

  void _openPickerSheet() {
    showCupertinoModalPopup<void>(
      context: context,
      builder: (sheetContext) => CupertinoActionSheet(
        title: const Text(
          'Fotograf kaynagi',
          style: TextStyle(fontFamily: AppFont.family),
        ),
        actions: [
          CupertinoActionSheetAction(
            onPressed: () {
              Navigator.of(sheetContext).pop();
              _pickFrom(ImageSource.camera);
            },
            child: const Text(
              'Kamera',
              style: TextStyle(fontFamily: AppFont.family),
            ),
          ),
          CupertinoActionSheetAction(
            onPressed: () {
              Navigator.of(sheetContext).pop();
              _pickFrom(ImageSource.gallery);
            },
            child: const Text(
              'Galeri',
              style: TextStyle(fontFamily: AppFont.family),
            ),
          ),
        ],
        cancelButton: CupertinoActionSheetAction(
          onPressed: () => Navigator.of(sheetContext).pop(),
          child: const Text(
            'Vazgec',
            style: TextStyle(fontFamily: AppFont.family),
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final isAuthenticated =
        ref.watch(appAuthProvider).asData?.value?.token.trim().isNotEmpty ==
        true;
    final mediaTiles = isAuthenticated ? _remoteTiles() : _mockTiles();

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        children: [
          _ProfileMediaTabBar(
            selected: _tab,
            onChanged: (i) => setState(() => _tab = i),
          ),
          const SizedBox(height: 14),
          AspectRatio(
            aspectRatio: 3 / 2,
            child: _loading && isAuthenticated
                ? const Center(child: CupertinoActivityIndicator(radius: 14))
                : _tab == 2
                ? _emptyMediaState('Video yukleme henuz aktif degil.')
                : GridView.count(
                    shrinkWrap: true,
                    physics: const NeverScrollableScrollPhysics(),
                    crossAxisCount: 3,
                    mainAxisSpacing: 6,
                    crossAxisSpacing: 6,
                    children: mediaTiles,
                  ),
          ),
          if (_notice != null) ...[
            const SizedBox(height: 12),
            Text(
              _notice!,
              style: const TextStyle(
                fontFamily: AppFont.family,
                fontSize: 12,
                height: 1.4,
                color: Color(0xFFEF4444),
              ),
            ),
          ],
          const SizedBox(height: 12),
          PressableScale(
            onTap: isAuthenticated ? _loadPhotos : null,
            scale: 0.99,
            child: Container(
              height: 40,
              decoration: BoxDecoration(
                color: AppColors.grayField,
                borderRadius: BorderRadius.circular(12),
              ),
              alignment: Alignment.center,
              child: Text(
                _uploading ? 'Yukleniyor...' : 'Yenile',
                style: TextStyle(
                  fontFamily: AppFont.family,
                  fontWeight: FontWeight.w700,
                  fontSize: 13,
                  color: Color(0xFF555555),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  List<Widget> _remoteTiles() {
    final visiblePhotos = _photos.take(5).toList();
    final tiles = visiblePhotos.map(_networkMediaTile).toList();
    while (tiles.length < 5) {
      tiles.add(_emptyTile());
    }
    tiles.add(_addTile());
    return tiles;
  }

  List<Widget> _mockTiles() {
    return [
      _mediaTile('assets/images/gallery_1.png'),
      _mediaTile('assets/images/gallery_2.png'),
      _mediaTile('assets/images/gallery_3.png', video: '0:34'),
      _mediaTile('assets/images/gallery_4.png'),
      _mediaTile('assets/images/gallery_5.png'),
      _addTile(),
    ];
  }

  Widget _mediaTile(String asset, {String? video}) {
    return ClipRRect(
      borderRadius: BorderRadius.circular(10),
      child: Stack(
        fit: StackFit.expand,
        children: [
          Image.asset(asset, fit: BoxFit.cover),
          if (video != null)
            Positioned(
              left: 5,
              bottom: 5,
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                decoration: BoxDecoration(
                  color: const Color(0x8C000000),
                  borderRadius: BorderRadius.circular(6),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Icon(
                      CupertinoIcons.play_fill,
                      size: 9,
                      color: AppColors.white,
                    ),
                    const SizedBox(width: 3),
                    Text(
                      video,
                      style: const TextStyle(
                        fontFamily: AppFont.family,
                        fontWeight: FontWeight.w500,
                        fontSize: 10,
                        color: AppColors.white,
                      ),
                    ),
                  ],
                ),
              ),
            ),
        ],
      ),
    );
  }

  Widget _networkMediaTile(AppProfilePhoto photo) {
    return ClipRRect(
      borderRadius: BorderRadius.circular(10),
      child: Stack(
        fit: StackFit.expand,
        children: [
          Image.network(
            photo.url,
            fit: BoxFit.cover,
            errorBuilder: (_, __, ___) => _emptyTile(),
          ),
          if (photo.isPrimary)
            Positioned(
              left: 5,
              top: 5,
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 3),
                decoration: BoxDecoration(
                  color: const Color(0xA6111111),
                  borderRadius: BorderRadius.circular(999),
                ),
                child: const Text(
                  'Ana',
                  style: TextStyle(
                    fontFamily: AppFont.family,
                    fontWeight: FontWeight.w700,
                    fontSize: 10,
                    color: AppColors.white,
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }

  Widget _addTile() {
    return PressableScale(
      onTap: _uploading ? null : _openPickerSheet,
      child: Container(
        decoration: BoxDecoration(
          color: AppColors.grayField,
          borderRadius: BorderRadius.circular(10),
        ),
        alignment: Alignment.center,
        child: _uploading
            ? const CupertinoActivityIndicator(radius: 10)
            : const Icon(
                CupertinoIcons.add,
                size: 22,
                color: Color(0xFFAAAAAA),
              ),
      ),
    );
  }

  Widget _emptyTile() {
    return Container(
      decoration: BoxDecoration(
        color: AppColors.grayField,
        borderRadius: BorderRadius.circular(10),
      ),
    );
  }

  Widget _emptyMediaState(String message) {
    return Container(
      decoration: BoxDecoration(
        color: AppColors.grayField,
        borderRadius: BorderRadius.circular(14),
      ),
      alignment: Alignment.center,
      padding: const EdgeInsets.symmetric(horizontal: 24),
      child: Text(
        message,
        textAlign: TextAlign.center,
        style: const TextStyle(
          fontFamily: AppFont.family,
          fontWeight: FontWeight.w600,
          fontSize: 13,
          color: AppColors.gray,
        ),
      ),
    );
  }
}

class _ProfileMediaTabBar extends StatelessWidget {
  final int selected;
  final ValueChanged<int> onChanged;

  const _ProfileMediaTabBar({required this.selected, required this.onChanged});

  @override
  Widget build(BuildContext context) {
    const labels = ['Tumu', 'Fotograflar', 'Videolar'];
    return Container(
      height: 37,
      padding: const EdgeInsets.all(3),
      decoration: BoxDecoration(
        color: const Color(0xFFF2F2F4),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        children: List.generate(3, (i) {
          final isOn = selected == i;
          return Expanded(
            child: PressableScale(
              onTap: () => onChanged(i),
              scale: 0.98,
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 180),
                decoration: BoxDecoration(
                  color: isOn ? AppColors.black : const Color(0x00000000),
                  borderRadius: BorderRadius.circular(10),
                ),
                alignment: Alignment.center,
                child: Text(
                  labels[i],
                  style: TextStyle(
                    fontFamily: AppFont.family,
                    fontWeight: FontWeight.w600,
                    fontSize: 12,
                    color: isOn ? AppColors.white : const Color(0xFF999999),
                  ),
                ),
              ),
            ),
          );
        }),
      ),
    );
  }
}

// ------ Privacy & Terms (generic scaffold) ------------------------------------

class _PolicyScaffold extends StatelessWidget {
  final String title;
  final List<({String heading, String body})> sections;

  const _PolicyScaffold({required this.title, required this.sections});

  @override
  Widget build(BuildContext context) {
    return CupertinoPageScaffold(
      backgroundColor: AppColors.white,
      child: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const SizedBox(height: 8),
              Row(
                children: [
                  CircleBackButton(
                    filled: true,
                    onTap: () => Navigator.of(context).maybePop(),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      title,
                      style: const TextStyle(
                        fontFamily: AppFont.family,
                        fontWeight: FontWeight.w800,
                        fontSize: 20,
                        color: AppColors.black,
                        letterSpacing: -0.4,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 20),
              Expanded(
                child: ListView(
                  physics: const BouncingScrollPhysics(),
                  children: [
                    for (var i = 0; i < sections.length; i++)
                      _KvkkSection(
                        title: '${i + 1}. ${sections[i].heading}',
                        body: sections[i].body,
                      ),
                    const SizedBox(height: 24),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _KvkkSection extends StatelessWidget {
  final String title;
  final String body;

  const _KvkkSection({required this.title, required this.body});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 18),
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
          const SizedBox(height: 6),
          Text(
            body,
            style: const TextStyle(
              fontFamily: AppFont.family,
              fontSize: 14,
              height: 1.55,
              color: AppColors.black,
            ),
          ),
        ],
      ),
    );
  }
}

class PrivacyPolicyScreen extends StatelessWidget {
  const PrivacyPolicyScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return const _PolicyScaffold(
      title: 'Gizlilik Politikasi',
      sections: [
        (
          heading: 'Veri Toplama',
          body:
              'magmug uygulamasi, kullanicilarinin deneyimini iyilestirmek amaciyla belirli kisisel verileri toplar. Bu veriler arasinda ad, soyad, e-posta adresi, konum bilgisi, profil fotograflari ve uygulama ici etkilesim verileri yer alir.',
        ),
        (
          heading: 'Verilerin Kullanimi',
          body:
              'Toplanan veriler, size daha iyi eslesmeler sunmak, uygulama deneyimini kisisellestirmek ve guvenliginizi saglamak amaciyla kullanilir. Verileriniz ucuncu taraflarla paylasilmaz.',
        ),
        (
          heading: 'Veri Guvenligi',
          body:
              'Tum kisisel verileriniz 256-bit SSL sifreleme ile korunmaktadir. Sunucularimiz guvenli veri merkezlerinde barindirilmakta ve duzenli olarak denetlenmektedir.',
        ),
        (
          heading: 'Cerezler',
          body:
              'Uygulamamiz, kullanici deneyimini iyilestirmek icin cerezler ve benzer teknolojiler kullanmaktadir. Bu cerezler, oturum yonetimi ve tercihlerinizin hatirlanmasi amaciyla kullanilir.',
        ),
        (
          heading: 'Iletisim',
          body:
              'Gizlilik politikamizla ilgili sorulariniz icin destek sayfamiz uzerinden bize ulasabilirsiniz.',
        ),
      ],
    );
  }
}

class TermsOfUseScreen extends StatelessWidget {
  const TermsOfUseScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return const _PolicyScaffold(
      title: 'Kullanim Kosullari',
      sections: [
        (
          heading: 'Hizmet Tanimi',
          body:
              'magmug, kullanicilarin birbirleriyle mesajlasma ve eslesme yoluyla tanismalarini saglayan bir sosyal platformdur.',
        ),
        (
          heading: 'Kullanici Sorumluluklari',
          body:
              'Kullanicilar, dogru ve guncel bilgiler saglamakla yukumludur. Sahte profil olusturmak, taciz, kufur ve uygunsuz icerik paylasmak kesinlikle yasaktir.',
        ),
        (
          heading: 'Yas Siniri',
          body:
              "magmug'u kullanmak icin en az 18 yasinda olmaniz gerekmektedir. 18 yasindan kucuk kullanicilarin hesaplari tespit edildiginde kapatilacaktir.",
        ),
        (
          heading: 'Odeme ve Iadeler',
          body:
              'Uygulama ici satin alimlar Apple App Store veya Google Play Store uzerinden gerceklestirilir. Iade talepleri ilgili magaza politikalarina tabidir.',
        ),
        (
          heading: 'Hesap Sonlandirma',
          body:
              'magmug, kullanim kosullarini ihlal eden hesaplari onceden bildirim yapmaksizin askiya alma veya sonlandirma hakkini sakli tutar.',
        ),
      ],
    );
  }
}

// ------ Sheets ----------------------------------------------------------------

class _ConfirmSheet extends StatelessWidget {
  final String title;
  final String subtitle;
  final String confirmLabel;
  final bool destructive;
  final Future<void> Function()? onConfirm;

  const _ConfirmSheet({
    required this.title,
    required this.subtitle,
    required this.confirmLabel,
    this.destructive = false,
    this.onConfirm,
  });

  @override
  Widget build(BuildContext context) {
    final confirmBg = destructive ? const Color(0xFFEF4444) : null;
    final confirmGradient = destructive ? null : AppColors.primary;

    return Container(
      decoration: const BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      padding: const EdgeInsets.fromLTRB(24, 12, 24, 24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const _SheetHandle(),
          const SizedBox(height: 24),
          Text(
            title,
            textAlign: TextAlign.center,
            style: const TextStyle(
              fontFamily: AppFont.family,
              fontWeight: FontWeight.w800,
              fontSize: 17,
              color: AppColors.black,
              height: 1.3,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            subtitle,
            textAlign: TextAlign.center,
            style: const TextStyle(
              fontFamily: AppFont.family,
              fontSize: 13.5,
              height: 1.5,
              color: Color(0xFF666666),
            ),
          ),
          const SizedBox(height: 20),
          PressableScale(
            onTap: () async {
              if (onConfirm != null) {
                await onConfirm!.call();
                return;
              }
              if (context.mounted) {
                Navigator.of(context).maybePop();
              }
            },
            child: Container(
              height: 52,
              width: double.infinity,
              decoration: BoxDecoration(
                color: confirmBg,
                gradient: confirmGradient,
                borderRadius: BorderRadius.circular(26),
              ),
              alignment: Alignment.center,
              child: Text(
                confirmLabel,
                style: const TextStyle(
                  fontFamily: AppFont.family,
                  fontWeight: FontWeight.w700,
                  fontSize: 15,
                  color: AppColors.white,
                ),
              ),
            ),
          ),
          const SizedBox(height: 8),
          PressableScale(
            onTap: () => Navigator.of(context).maybePop(),
            scale: 0.99,
            child: Container(
              height: 52,
              width: double.infinity,
              decoration: BoxDecoration(
                color: AppColors.grayField,
                borderRadius: BorderRadius.circular(26),
              ),
              alignment: Alignment.center,
              child: const Text(
                'Vazgec',
                style: TextStyle(
                  fontFamily: AppFont.family,
                  fontWeight: FontWeight.w600,
                  fontSize: 15,
                  color: AppColors.black,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class SignOutConfirmSheet extends ConsumerStatefulWidget {
  const SignOutConfirmSheet({super.key});

  @override
  ConsumerState<SignOutConfirmSheet> createState() =>
      _SignOutConfirmSheetState();
}

class _SignOutConfirmSheetState extends ConsumerState<SignOutConfirmSheet> {
  bool _submitting = false;

  Future<void> _signOut() async {
    if (_submitting) return;

    setState(() => _submitting = true);

    try {
      ref.read(onboardProvider.notifier).reset();
      await ref.read(appAuthProvider.notifier).signOut();
      if (!mounted) return;

      Navigator.of(context, rootNavigator: true).pushAndRemoveUntil(
        cupertinoRoute(const OnboardScreen()),
        (route) => false,
      );
    } finally {
      if (mounted) {
        setState(() => _submitting = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return _ConfirmSheet(
      title: 'Cikis yapmak istiyor musunuz?',
      subtitle:
          'Tekrar giris yapmak icin Google veya Apple hesabinizi kullanabilirsiniz.',
      confirmLabel: _submitting ? 'Cikis yapiliyor...' : 'Cikis Yap',
      destructive: true,
      onConfirm: _signOut,
    );
  }
}

class DeleteAccountConfirmSheet extends StatelessWidget {
  const DeleteAccountConfirmSheet({super.key});

  @override
  Widget build(BuildContext context) {
    return const _ConfirmSheet(
      title: 'Hesabinizi silmek istiyor musunuz?',
      subtitle:
          'Bu islem geri alinamaz. Tum verileriniz kalici olarak silinecektir.',
      confirmLabel: 'Hesabi Sil',
      destructive: true,
    );
  }
}

class UnblockConfirmSheet extends StatelessWidget {
  const UnblockConfirmSheet({super.key});

  @override
  Widget build(BuildContext context) {
    return const _ConfirmSheet(
      title: 'engelini kaldirmak istiyor musunuz?',
      subtitle: 'Bu kisi tekrar size mesaj gonderebilecek.',
      confirmLabel: 'Engeli Kaldir',
    );
  }
}

class EditProfileSheet extends ConsumerStatefulWidget {
  const EditProfileSheet({super.key});

  @override
  ConsumerState<EditProfileSheet> createState() => _EditProfileSheetState();
}

class _EditProfileSheetState extends ConsumerState<EditProfileSheet> {
  late final TextEditingController _name;
  late final TextEditingController _surname;
  late final TextEditingController _username;
  final TextEditingController _bio = TextEditingController();
  bool _submitting = false;
  String? _submitMessage;

  @override
  void initState() {
    super.initState();
    final user = ref.read(appAuthProvider).asData?.value?.user;
    final data = ref.read(onboardProvider);
    final name = data.name.isNotEmpty
        ? data.name
        : (user?.firstName ?? 'Mehmet');
    final surname = data.surname.isNotEmpty
        ? data.surname
        : (user?.surname ?? 'Kaya');
    final username = data.username.isNotEmpty
        ? data.username
        : (user?.username ?? 'mehmet.k');
    _name = TextEditingController(text: name);
    _surname = TextEditingController(text: surname);
    _username = TextEditingController(text: '@$username');
    _bio.text = user?.bio ?? '';
  }

  @override
  void dispose() {
    _name.dispose();
    _surname.dispose();
    _username.dispose();
    _bio.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final user = ref.watch(appAuthProvider).asData?.value?.user;

    Future<void> saveProfile() async {
      if (_submitting) return;

      if (user == null) {
        ref.read(onboardProvider.notifier).setName(_name.text);
        ref.read(onboardProvider.notifier).setSurname(_surname.text);
        ref
            .read(onboardProvider.notifier)
            .setUsername(_username.text.replaceAll('@', ''));
        Navigator.of(context).maybePop();
        return;
      }

      setState(() {
        _submitting = true;
        _submitMessage = null;
      });

      try {
        await ref
            .read(appAuthProvider.notifier)
            .updateProfile(
              firstName: _name.text,
              surname: _surname.text,
              bio: _bio.text,
            );
        ref.read(onboardProvider.notifier).setName(_name.text);
        ref.read(onboardProvider.notifier).setSurname(_surname.text);
        if (!mounted) return;
        Navigator.of(context).maybePop();
      } catch (error) {
        if (!mounted) return;
        setState(() {
          _submitMessage = AppAuthErrorFormatter.messageFrom(error);
        });
      } finally {
        if (mounted) {
          setState(() => _submitting = false);
        }
      }
    }

    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
      child: Container(
        decoration: const BoxDecoration(
          color: AppColors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
        ),
        padding: const EdgeInsets.fromLTRB(20, 12, 20, 20),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const _SheetHandle(),
            const SizedBox(height: 18),
            const Text(
              'Profili Duzenle',
              style: TextStyle(
                fontFamily: AppFont.family,
                fontWeight: FontWeight.w800,
                fontSize: 18,
                color: AppColors.black,
              ),
            ),
            const SizedBox(height: 16),
            _MiniLabeledField(label: 'Isim', controller: _name),
            const SizedBox(height: 12),
            _MiniLabeledField(label: 'Soyisim', controller: _surname),
            const SizedBox(height: 12),
            _MiniLabeledField(
              label: 'Kullanici Adi',
              controller: _username,
              readOnly: user != null,
            ),
            if (user != null) ...[
              const SizedBox(height: 8),
              const Text(
                'Kullanici adi degisikligi henuz mobil uygulamadan desteklenmiyor.',
                style: TextStyle(
                  fontFamily: AppFont.family,
                  fontSize: 12,
                  height: 1.4,
                  color: AppColors.gray,
                ),
              ),
            ],
            const SizedBox(height: 12),
            _MiniLabeledField(
              label: 'Biyografi',
              controller: _bio,
              placeholder: 'Kendinden bahset...',
              height: 88,
              maxLines: 3,
            ),
            if (_submitMessage != null) ...[
              const SizedBox(height: 12),
              Text(
                _submitMessage!,
                style: const TextStyle(
                  fontFamily: AppFont.family,
                  fontSize: 12,
                  height: 1.4,
                  color: Color(0xFFEF4444),
                ),
              ),
            ],
            const SizedBox(height: 20),
            GradientButton(
              label: _submitting ? 'Kaydediliyor...' : 'Kaydet',
              onTap: _submitting ? null : saveProfile,
            ),
          ],
        ),
      ),
    );
  }
}

class _MiniLabeledField extends StatelessWidget {
  final String label;
  final TextEditingController controller;
  final String? placeholder;
  final double height;
  final int? maxLines;
  final bool readOnly;

  const _MiniLabeledField({
    required this.label,
    required this.controller,
    this.placeholder,
    this.height = 48,
    this.maxLines = 1,
    this.readOnly = false,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: const TextStyle(
            fontFamily: AppFont.family,
            fontWeight: FontWeight.w500,
            fontSize: 12,
            color: Color(0xFF777777),
          ),
        ),
        const SizedBox(height: 6),
        Container(
          height: height,
          padding: const EdgeInsets.symmetric(horizontal: 14),
          decoration: BoxDecoration(
            color: AppColors.grayField,
            borderRadius: BorderRadius.circular(12),
          ),
          child: Center(
            child: CupertinoTextField(
              controller: controller,
              readOnly: readOnly,
              placeholder: placeholder,
              maxLines: maxLines,
              placeholderStyle: const TextStyle(
                fontFamily: AppFont.family,
                color: Color(0xFF999999),
                fontSize: 14,
              ),
              style: const TextStyle(
                fontFamily: AppFont.family,
                fontWeight: FontWeight.w500,
                fontSize: 14,
                color: AppColors.black,
              ),
              decoration: const BoxDecoration(color: Color(0x00000000)),
              padding: const EdgeInsets.symmetric(vertical: 10),
              cursorColor: AppColors.indigo,
            ),
          ),
        ),
      ],
    );
  }
}

class LanguageSheet extends StatefulWidget {
  const LanguageSheet({super.key});

  @override
  State<LanguageSheet> createState() => _LanguageSheetState();
}

class _LanguageSheetState extends State<LanguageSheet> {
  AppLanguage _selected = AppLanguage.tr;

  static const Map<AppLanguage, ({String flag, String label})> _opts = {
    AppLanguage.tr: (flag: 'TR', label: 'Turkce'),
    AppLanguage.en: (flag: 'EN', label: 'English'),
    AppLanguage.de: (flag: 'DE', label: 'Deutsch'),
    AppLanguage.fr: (flag: 'FR', label: 'Francais'),
  };

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      padding: const EdgeInsets.fromLTRB(20, 12, 20, 20),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const _SheetHandle(),
          const SizedBox(height: 18),
          const Text(
            'Dil Secimi',
            style: TextStyle(
              fontFamily: AppFont.family,
              fontWeight: FontWeight.w800,
              fontSize: 18,
              color: AppColors.black,
            ),
          ),
          const SizedBox(height: 16),
          ...AppLanguage.values.map((lang) {
            final opt = _opts[lang]!;
            return Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: _FlagLanguageRow(
                flag: opt.flag,
                label: opt.label,
                selected: _selected == lang,
                onTap: () => setState(() => _selected = lang),
              ),
            );
          }),
          const SizedBox(height: 8),
          GradientButton(
            label: 'Degistir',
            onTap: () => Navigator.of(context).maybePop(),
          ),
        ],
      ),
    );
  }
}

class _FlagLanguageRow extends StatelessWidget {
  final String flag;
  final String label;
  final bool selected;
  final VoidCallback onTap;

  const _FlagLanguageRow({
    required this.flag,
    required this.label,
    required this.selected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return PressableScale(
      onTap: onTap,
      scale: 0.99,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 160),
        height: 56,
        padding: const EdgeInsets.symmetric(horizontal: 16),
        decoration: BoxDecoration(
          color: AppColors.grayField,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(
            color: selected ? AppColors.black : const Color(0x00000000),
            width: 1.5,
          ),
        ),
        child: Row(
          children: [
            Text(flag, style: const TextStyle(fontSize: 22)),
            const SizedBox(width: 12),
            Expanded(
              child: Text(
                label,
                style: const TextStyle(
                  fontFamily: AppFont.family,
                  fontWeight: FontWeight.w600,
                  fontSize: 14.5,
                  color: AppColors.black,
                ),
              ),
            ),
            AnimatedContainer(
              duration: const Duration(milliseconds: 160),
              width: 22,
              height: 22,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: selected ? AppColors.black : const Color(0x00000000),
                border: selected
                    ? null
                    : Border.all(color: const Color(0xFFD4D4D4), width: 1.5),
              ),
              alignment: Alignment.center,
              child: selected
                  ? const Icon(
                      CupertinoIcons.check_mark,
                      size: 12,
                      color: AppColors.white,
                    )
                  : null,
            ),
          ],
        ),
      ),
    );
  }
}

class NotificationPrefsSheet extends ConsumerWidget {
  const NotificationPrefsSheet({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final prefs = ref.watch(notifPrefsProvider);
    final notifier = ref.read(notifPrefsProvider.notifier);

    Widget row(
      String title,
      String desc,
      bool value,
      NotificationPrefs Function(bool) update,
    ) {
      return Padding(
        padding: const EdgeInsets.symmetric(vertical: 12),
        child: Row(
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
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
                  const SizedBox(height: 2),
                  Text(
                    desc,
                    style: const TextStyle(
                      fontFamily: AppFont.family,
                      fontSize: 11.5,
                      color: Color(0xFF999999),
                    ),
                  ),
                ],
              ),
            ),
            _SoftBlackSwitch(
              value: value,
              onChanged: (_) => notifier.set(update(!value)),
            ),
          ],
        ),
      );
    }

    return Container(
      decoration: const BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      padding: const EdgeInsets.fromLTRB(20, 12, 20, 20),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const _SheetHandle(),
          const SizedBox(height: 18),
          const Text(
            'Bildirimler',
            style: TextStyle(
              fontFamily: AppFont.family,
              fontWeight: FontWeight.w800,
              fontSize: 18,
              color: AppColors.black,
            ),
          ),
          const SizedBox(height: 8),
          row(
            'Mesaj Bildirimleri',
            'Yeni mesaj geldiginde',
            prefs.messages,
            (v) => prefs.copyWith(messages: v),
          ),
          _divider(),
          row(
            'Eslesme Bildirimleri',
            'Yeni eslesmelerde',
            prefs.matches,
            (v) => prefs.copyWith(matches: v),
          ),
          _divider(),
          row(
            'Begeni Bildirimleri',
            'Birisi seni begendiginde',
            prefs.likes,
            (v) => prefs.copyWith(likes: v),
          ),
          _divider(),
          row(
            'Kampanya',
            'Indirim ve firsatlar',
            prefs.campaigns,
            (v) => prefs.copyWith(campaigns: v),
          ),
          const SizedBox(height: 16),
          GradientButton(
            label: 'Kaydet',
            onTap: () => Navigator.of(context).maybePop(),
          ),
        ],
      ),
    );
  }

  Widget _divider() => Container(height: 1, color: const Color(0xFFF0F0F0));
}

class _SoftBlackSwitch extends StatelessWidget {
  final bool value;
  final ValueChanged<bool> onChanged;

  const _SoftBlackSwitch({required this.value, required this.onChanged});

  @override
  Widget build(BuildContext context) {
    return PressableScale(
      onTap: () => onChanged(!value),
      scale: 0.95,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        width: 46,
        height: 26,
        padding: const EdgeInsets.all(3),
        decoration: BoxDecoration(
          color: value ? AppColors.black : const Color(0xFFE5E5E5),
          borderRadius: BorderRadius.circular(24),
        ),
        child: AnimatedAlign(
          duration: const Duration(milliseconds: 220),
          curve: Curves.easeOutCubic,
          alignment: value ? Alignment.centerRight : Alignment.centerLeft,
          child: Container(
            width: 20,
            height: 20,
            decoration: const BoxDecoration(
              color: AppColors.white,
              shape: BoxShape.circle,
              boxShadow: [
                BoxShadow(
                  color: Color(0x1F000000),
                  blurRadius: 4,
                  offset: Offset(0, 2),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class HelpSheet extends StatefulWidget {
  const HelpSheet({super.key});

  @override
  State<HelpSheet> createState() => _HelpSheetState();
}

class _HelpSheetState extends State<HelpSheet> {
  bool _expanded = true;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
      child: Container(
        decoration: const BoxDecoration(
          color: AppColors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
        ),
        padding: const EdgeInsets.fromLTRB(20, 12, 20, 20),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const _SheetHandle(),
            const SizedBox(height: 18),
            const Text(
              'Yardim',
              style: TextStyle(
                fontFamily: AppFont.family,
                fontWeight: FontWeight.w800,
                fontSize: 18,
                color: AppColors.black,
              ),
            ),
            const SizedBox(height: 12),
            PressableScale(
              onTap: () => setState(() => _expanded = !_expanded),
              scale: 0.99,
              child: Container(
                padding: const EdgeInsets.symmetric(vertical: 12),
                child: Row(
                  children: [
                    const Expanded(
                      child: Text(
                        'Sikca Sorulan Sorular',
                        style: TextStyle(
                          fontFamily: AppFont.family,
                          fontWeight: FontWeight.w700,
                          fontSize: 14.5,
                          color: AppColors.black,
                        ),
                      ),
                    ),
                    AnimatedRotation(
                      turns: _expanded ? 0.5 : 0.0,
                      duration: const Duration(milliseconds: 200),
                      child: const Icon(
                        CupertinoIcons.chevron_down,
                        size: 16,
                        color: Color(0xFF666666),
                      ),
                    ),
                  ],
                ),
              ),
            ),
            AnimatedCrossFade(
              duration: const Duration(milliseconds: 220),
              firstChild: const SizedBox.shrink(),
              secondChild: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: const [
                  _FaqItem(
                    question: 'Tas nedir, nasil kullanilir?',
                    answer:
                        'Tas, uygulama ici sanal para birimidir. Ozel emoji gondermek ve ek ozellikler icin kullanilir.',
                  ),
                  _FaqItem(
                    question: 'Eslesme nasil calisir?',
                    answer:
                        'Kesfet bolumunden esles butonuna basarak rastgele birisiyle eslesebilirsiniz. Iki taraf da kabul ederse mesajlasma baslar.',
                  ),
                  _FaqItem(
                    question: 'Premium ne saglar?',
                    answer:
                        'Sinirsiz mesaj, seni kimin begendigini gorme, sesli arama ve haftalik boost gibi ozellikler sunar.',
                  ),
                ],
              ),
              crossFadeState: _expanded
                  ? CrossFadeState.showSecond
                  : CrossFadeState.showFirst,
            ),
            Container(
              height: 1,
              color: const Color(0xFFF0F0F0),
              margin: const EdgeInsets.symmetric(vertical: 12),
            ),
            const Text(
              'Bize Yazin',
              style: TextStyle(
                fontFamily: AppFont.family,
                fontWeight: FontWeight.w700,
                fontSize: 14,
                color: AppColors.black,
              ),
            ),
            const SizedBox(height: 8),
            Container(
              height: 96,
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: AppColors.grayField,
                borderRadius: BorderRadius.circular(12),
              ),
              child: const CupertinoTextField(
                maxLines: 3,
                placeholder: 'Mesajinizi yazin...',
                placeholderStyle: TextStyle(
                  fontFamily: AppFont.family,
                  color: Color(0xFF999999),
                  fontSize: 14,
                ),
                style: TextStyle(
                  fontFamily: AppFont.family,
                  fontSize: 14,
                  color: AppColors.black,
                ),
                decoration: BoxDecoration(color: Color(0x00000000)),
                padding: EdgeInsets.zero,
              ),
            ),
            const SizedBox(height: 12),
            GradientButton(
              label: 'Gonder',
              onTap: () => Navigator.of(context).maybePop(),
            ),
            const SizedBox(height: 12),
            Container(
              height: 1,
              color: const Color(0xFFF0F0F0),
              margin: const EdgeInsets.only(bottom: 12),
            ),
            Row(
              children: [
                Container(
                  width: 24,
                  height: 24,
                  decoration: const BoxDecoration(
                    color: Color(0xFF25D366),
                    shape: BoxShape.circle,
                  ),
                  alignment: Alignment.center,
                  child: const Icon(
                    CupertinoIcons.chat_bubble_fill,
                    size: 13,
                    color: AppColors.white,
                  ),
                ),
                const SizedBox(width: 10),
                const Text(
                  'WhatsApp ile Ulasin',
                  style: TextStyle(
                    fontFamily: AppFont.family,
                    fontWeight: FontWeight.w600,
                    fontSize: 14,
                    color: AppColors.black,
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _FaqItem extends StatelessWidget {
  final String question;
  final String answer;

  const _FaqItem({required this.question, required this.answer});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            question,
            style: const TextStyle(
              fontFamily: AppFont.family,
              fontWeight: FontWeight.w700,
              fontSize: 13.5,
              color: AppColors.black,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            answer,
            style: const TextStyle(
              fontFamily: AppFont.family,
              fontSize: 13,
              height: 1.4,
              color: Color(0xFF555555),
            ),
          ),
        ],
      ),
    );
  }
}

class BlockedUsersSheet extends StatelessWidget {
  const BlockedUsersSheet({super.key});

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      padding: const EdgeInsets.fromLTRB(20, 12, 20, 20),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const _SheetHandle(),
          const SizedBox(height: 18),
          const Text(
            'Engellenen Kullanicilar',
            style: TextStyle(
              fontFamily: AppFont.family,
              fontWeight: FontWeight.w800,
              fontSize: 18,
              color: AppColors.black,
            ),
          ),
          const SizedBox(height: 16),
          ..._mockBlocked.map(
            (u) => Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: _BlockedUserRow(
                user: u,
                onUnblock: () {
                  Navigator.of(context).maybePop();
                  showCupertinoModalPopup<void>(
                    context: context,
                    builder: (_) => const UnblockConfirmSheet(),
                  );
                },
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _BlockedUserRow extends StatelessWidget {
  final BlockedUser user;
  final VoidCallback onUnblock;

  const _BlockedUserRow({required this.user, required this.onUnblock});

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        ClipOval(
          child: user.avatarAsset != null
              ? Image.asset(
                  user.avatarAsset!,
                  width: 44,
                  height: 44,
                  fit: BoxFit.cover,
                )
              : AvatarCircle(name: user.name, size: 44),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                user.name,
                style: const TextStyle(
                  fontFamily: AppFont.family,
                  fontWeight: FontWeight.w700,
                  fontSize: 14,
                  color: AppColors.black,
                ),
              ),
              Text(
                user.handle,
                style: const TextStyle(
                  fontFamily: AppFont.family,
                  fontSize: 12,
                  color: Color(0xFF999999),
                ),
              ),
            ],
          ),
        ),
        PressableScale(
          onTap: onUnblock,
          scale: 0.96,
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
            decoration: BoxDecoration(
              color: const Color(0x1AEF4444),
              borderRadius: BorderRadius.circular(20),
            ),
            child: const Text(
              'Engeli Kaldir',
              style: TextStyle(
                fontFamily: AppFont.family,
                fontWeight: FontWeight.w700,
                fontSize: 12.5,
                color: Color(0xFFEF4444),
              ),
            ),
          ),
        ),
      ],
    );
  }
}

// ------ Paywall ---------------------------------------------------------------

class PaywallScreen extends StatefulWidget {
  const PaywallScreen({super.key});

  @override
  State<PaywallScreen> createState() => _PaywallScreenState();
}

class _PaywallScreenState extends State<PaywallScreen> {
  bool _submitting = false;
  String? _submitMessage;
  int _selected = 1;

  @override
  Widget build(BuildContext context) {
    return CupertinoPageScaffold(
      backgroundColor: const Color(0xFF0A0A12),
      child: Stack(
        children: [
          const Positioned.fill(child: _PaywallAmbient()),
          SafeArea(
            child: Column(
              children: [
                _PaywallHeader(onClose: () => Navigator.of(context).maybePop()),
                const SizedBox(height: 8),
                Expanded(
                  child: SingleChildScrollView(
                    physics: const BouncingScrollPhysics(),
                    child: Column(
                      children: [
                        const SizedBox(height: 32),
                        Container(
                          width: 160,
                          height: 160,
                          decoration: const BoxDecoration(
                            shape: BoxShape.circle,
                            gradient: RadialGradient(
                              colors: [Color(0x40FF3C78), Color(0x00FF3C78)],
                            ),
                          ),
                          alignment: Alignment.center,
                          child: const Icon(
                            CupertinoIcons.videocam_fill,
                            size: 64,
                            color: AppColors.white,
                          ),
                        ),
                        const SizedBox(height: 28),
                        const Text(
                          'Goruntulu ve Sesli Arama.\nHizlica Tanisma Firsati',
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            fontFamily: AppFont.family,
                            fontWeight: FontWeight.w800,
                            fontSize: 21,
                            height: 1.28,
                            color: AppColors.white,
                            letterSpacing: -0.5,
                          ),
                        ),
                        const SizedBox(height: 12),
                        const Text(
                          'Ister goruntulu, ister sesli gorus.\nMesajlasmanin otesine gec.',
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            fontFamily: AppFont.family,
                            fontSize: 13,
                            height: 1.55,
                            color: Color(0x99FFFFFF),
                          ),
                        ),
                        const SizedBox(height: 24),
                        _CarouselDots(selected: 0, count: 9),
                        const SizedBox(height: 32),
                        Padding(
                          padding: const EdgeInsets.symmetric(horizontal: 16),
                          child: Row(
                            crossAxisAlignment: CrossAxisAlignment.end,
                            children: [
                              Expanded(
                                child: _PlanCard(
                                  title: '1 Hafta',
                                  priceMajor: '249',
                                  priceMinor: ',99',
                                  periodLabel: 'haftalik',
                                  oldPrice: '349,99 TL',
                                  saveLabel: '%29 tasarruf',
                                  selected: _selected == 0,
                                  onTap: () => setState(() => _selected = 0),
                                ),
                              ),
                              const SizedBox(width: 8),
                              Expanded(
                                child: _PlanCard(
                                  title: '1 Ay',
                                  priceMajor: '599',
                                  priceMinor: ',99',
                                  periodLabel: 'aylik',
                                  oldPrice: '1.199,96 TL',
                                  badge: 'EN COK SATAN',
                                  saveLabel: '%50 tasarruf',
                                  featured: true,
                                  selected: _selected == 1,
                                  onTap: () => setState(() => _selected = 1),
                                ),
                              ),
                              const SizedBox(width: 8),
                              Expanded(
                                child: _PlanCard(
                                  title: '3 Ay',
                                  priceMajor: '1.199',
                                  priceMinor: ',99',
                                  periodLabel: '3 aylik',
                                  oldPrice: '3.599,88 TL',
                                  badge: 'EN AVANTAJLI',
                                  saveLabel: '%67 tasarruf',
                                  selected: _selected == 2,
                                  onTap: () => setState(() => _selected = 2),
                                ),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 20),
                        const Padding(
                          padding: EdgeInsets.symmetric(horizontal: 24),
                          child: Text(
                            'Planinizi istediginiz zaman iptal edebilirsiniz, taahhut yoktur. Gizli ucret veya ekstra masraf yoktur.',
                            textAlign: TextAlign.center,
                            style: TextStyle(
                              fontFamily: AppFont.family,
                              fontSize: 12,
                              height: 1.55,
                              color: Color(0x66FFFFFF),
                            ),
                          ),
                        ),
                        const SizedBox(height: 20),
                      ],
                    ),
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.fromLTRB(20, 0, 20, 12),
                  child: _PaywallCTA(
                    onTap: () => Navigator.of(context).maybePop(),
                  ),
                ),
                const _PaywallLegal(),
                SizedBox(height: MediaQuery.paddingOf(context).bottom + 8),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _PaywallAmbient extends StatelessWidget {
  const _PaywallAmbient();

  @override
  Widget build(BuildContext context) {
    return IgnorePointer(
      child: Stack(
        children: [
          Positioned(
            left: -120,
            top: -160,
            child: Container(
              width: 480,
              height: 420,
              decoration: const BoxDecoration(
                shape: BoxShape.circle,
                gradient: RadialGradient(
                  colors: [Color(0x66FF3C78), Color(0x005C6BFF)],
                ),
              ),
            ),
          ),
          Positioned(
            right: -120,
            top: -180,
            child: Container(
              width: 460,
              height: 420,
              decoration: const BoxDecoration(
                shape: BoxShape.circle,
                gradient: RadialGradient(
                  colors: [Color(0x405C6BFF), Color(0x00FFFFFF)],
                ),
              ),
            ),
          ),
          Positioned(
            left: -60,
            bottom: -140,
            child: Container(
              width: 380,
              height: 380,
              decoration: const BoxDecoration(
                shape: BoxShape.circle,
                gradient: RadialGradient(
                  colors: [Color(0x33FDB384), Color(0x00FFFFFF)],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _PaywallHeader extends StatelessWidget {
  final VoidCallback onClose;

  const _PaywallHeader({required this.onClose});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 8, 20, 8),
      child: Row(
        children: [
          PressableScale(
            onTap: onClose,
            scale: 0.9,
            child: Container(
              width: 34,
              height: 34,
              decoration: BoxDecoration(
                color: AppColors.white.withValues(alpha: 0.08),
                shape: BoxShape.circle,
              ),
              alignment: Alignment.center,
              child: const Icon(
                CupertinoIcons.xmark,
                size: 16,
                color: AppColors.white,
              ),
            ),
          ),
          const Spacer(),
          const Text(
            'magmug',
            style: TextStyle(
              fontFamily: AppFont.family,
              fontWeight: FontWeight.w800,
              fontSize: 19,
              color: AppColors.white,
              letterSpacing: -0.5,
            ),
          ),
          const SizedBox(width: 10),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(8),
              gradient: const LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: [Color(0xFFFF3C78), Color(0xFFFF6B9D)],
              ),
            ),
            child: const Text(
              'PREMIUM',
              style: TextStyle(
                fontFamily: AppFont.family,
                fontWeight: FontWeight.w800,
                fontSize: 11,
                color: AppColors.white,
                letterSpacing: 0.5,
              ),
            ),
          ),
          const Spacer(),
          const SizedBox(width: 34),
        ],
      ),
    );
  }
}

class _CarouselDots extends StatelessWidget {
  final int selected;
  final int count;

  const _CarouselDots({required this.selected, required this.count});

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: List.generate(count, (i) {
        final active = i == selected;
        return AnimatedContainer(
          duration: const Duration(milliseconds: 220),
          width: active ? 22 : 6,
          height: 6,
          margin: const EdgeInsets.symmetric(horizontal: 2.5),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(3),
            color: active
                ? AppColors.white
                : AppColors.white.withValues(alpha: 0.15),
          ),
        );
      }),
    );
  }
}

class _PlanCard extends StatelessWidget {
  final String title;
  final String priceMajor;
  final String priceMinor;
  final String periodLabel;
  final String? oldPrice;
  final String? badge;
  final String? saveLabel;
  final bool featured;
  final bool selected;
  final VoidCallback onTap;

  const _PlanCard({
    required this.title,
    required this.priceMajor,
    required this.priceMinor,
    required this.periodLabel,
    required this.onTap,
    required this.selected,
    this.oldPrice,
    this.badge,
    this.saveLabel,
    this.featured = false,
  });

  @override
  Widget build(BuildContext context) {
    final bg = featured
        ? const Color(0xFFFF3C78)
        : AppColors.white.withValues(alpha: 0.04);
    final border = featured
        ? const Color(0x80FF3C78)
        : AppColors.white.withValues(alpha: 0.08);

    return PressableScale(
      onTap: onTap,
      scale: 0.97,
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          Container(
            padding: const EdgeInsets.fromLTRB(12, 16, 12, 14),
            decoration: BoxDecoration(
              color: bg,
              borderRadius: BorderRadius.circular(20),
              border: Border.all(
                color: selected && !featured
                    ? AppColors.white.withValues(alpha: 0.35)
                    : border,
                width: featured ? 2 : 1,
              ),
              boxShadow: featured
                  ? const [
                      BoxShadow(
                        color: Color(0x1AFF3C78),
                        blurRadius: 28,
                        offset: Offset(0, 4),
                      ),
                    ]
                  : null,
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    fontFamily: AppFont.family,
                    fontWeight: FontWeight.w800,
                    fontSize: 15,
                    color: AppColors.white,
                  ),
                ),
                if (saveLabel != null) ...[
                  const SizedBox(height: 8),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 8,
                      vertical: 3,
                    ),
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(8),
                      color: featured
                          ? AppColors.white.withValues(alpha: 0.22)
                          : const Color(0x1FFF9794),
                    ),
                    child: Text(
                      saveLabel!,
                      style: TextStyle(
                        fontFamily: AppFont.family,
                        fontWeight: FontWeight.w800,
                        fontSize: 10.5,
                        color: featured
                            ? AppColors.white
                            : const Color(0xFFFF9794),
                      ),
                    ),
                  ),
                ] else
                  const SizedBox(height: 8),
                const SizedBox(height: 14),
                if (oldPrice != null) ...[
                  Text(
                    oldPrice!,
                    style: TextStyle(
                      fontFamily: AppFont.family,
                      fontSize: 12,
                      color: AppColors.white.withValues(alpha: 0.4),
                      decoration: TextDecoration.lineThrough,
                    ),
                  ),
                  const SizedBox(height: 6),
                ],
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'TL',
                      style: TextStyle(
                        fontFamily: AppFont.family,
                        fontWeight: FontWeight.w800,
                        fontSize: 16,
                        color: AppColors.white,
                      ),
                    ),
                    Text(
                      priceMajor,
                      style: const TextStyle(
                        fontFamily: AppFont.family,
                        fontWeight: FontWeight.w800,
                        fontSize: 24,
                        color: AppColors.white,
                        height: 1.0,
                      ),
                    ),
                    Text(
                      priceMinor,
                      style: const TextStyle(
                        fontFamily: AppFont.family,
                        fontWeight: FontWeight.w700,
                        fontSize: 15,
                        color: AppColors.white,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 4),
                Text(
                  periodLabel,
                  style: TextStyle(
                    fontFamily: AppFont.family,
                    fontWeight: FontWeight.w600,
                    fontSize: 11,
                    color: AppColors.white.withValues(alpha: 0.3),
                  ),
                ),
              ],
            ),
          ),
          if (badge != null)
            Positioned(
              top: -10,
              left: 12,
              right: 12,
              child: Container(
                height: 20,
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(10),
                  gradient: featured
                      ? const LinearGradient(
                          begin: Alignment.topLeft,
                          end: Alignment.bottomRight,
                          colors: [Color(0xFFFF3C78), Color(0xFFFF6B9D)],
                        )
                      : const LinearGradient(
                          begin: Alignment.topLeft,
                          end: Alignment.bottomRight,
                          colors: [Color(0xFFFF9794), Color(0xFFFF6B8A)],
                        ),
                ),
                alignment: Alignment.center,
                child: Text(
                  badge!,
                  style: const TextStyle(
                    fontFamily: AppFont.family,
                    fontWeight: FontWeight.w800,
                    fontSize: 9,
                    color: AppColors.white,
                    letterSpacing: 0.3,
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _PaywallCTA extends StatelessWidget {
  final VoidCallback onTap;

  const _PaywallCTA({required this.onTap});

  @override
  Widget build(BuildContext context) {
    return PressableScale(
      onTap: onTap,
      child: Container(
        height: 56,
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(54),
          gradient: const LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [Color(0xFFFF3C78), Color(0xFFFF6B9D)],
          ),
          boxShadow: const [
            BoxShadow(
              color: Color(0x4DFF3C78),
              blurRadius: 28,
              offset: Offset(0, 6),
            ),
          ],
        ),
        alignment: Alignment.center,
        child: const Text(
          'Devam Et',
          style: TextStyle(
            fontFamily: AppFont.family,
            fontWeight: FontWeight.w800,
            fontSize: 16,
            color: AppColors.white,
          ),
        ),
      ),
    );
  }
}

class _PaywallLegal extends StatelessWidget {
  const _PaywallLegal();

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 24),
      child: Text.rich(
        TextSpan(
          style: TextStyle(
            fontFamily: AppFont.family,
            fontSize: 11,
            color: AppColors.white.withValues(alpha: 0.4),
          ),
          children: [
            const TextSpan(text: 'Detayli bilgi icin '),
            TextSpan(
              text: 'Gizlilik Politikasi',
              style: TextStyle(
                color: AppColors.white.withValues(alpha: 0.6),
                decoration: TextDecoration.underline,
              ),
            ),
            const TextSpan(text: ' ve '),
            TextSpan(
              text: 'Kullanim Kosullari',
              style: TextStyle(
                color: AppColors.white.withValues(alpha: 0.6),
                decoration: TextDecoration.underline,
              ),
            ),
          ],
        ),
        textAlign: TextAlign.center,
      ),
    );
  }
}

// ------ Jeton purchase sheet --------------------------------------------------

class JetonPurchaseSheet extends StatefulWidget {
  const JetonPurchaseSheet({super.key});

  @override
  State<JetonPurchaseSheet> createState() => _JetonPurchaseSheetState();
}

class _JetonPurchaseSheetState extends State<JetonPurchaseSheet> {
  int _selected = 1;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(32)),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const SizedBox(height: 12),
          const _SheetHandle(),
          SizedBox(
            height: 180,
            child: Stack(
              alignment: Alignment.center,
              children: [
                Positioned.fill(
                  child: Container(
                    decoration: const BoxDecoration(
                      gradient: RadialGradient(
                        center: Alignment.center,
                        radius: 0.8,
                        colors: [Color(0xFFFFD0DE), Color(0xFFFFFFFF)],
                        stops: [0.0, 1.0],
                      ),
                    ),
                  ),
                ),
                Positioned(
                  top: 14,
                  child: Image.asset(
                    'assets/images/jeton_mascot.png',
                    width: 140,
                    height: 150,
                    fit: BoxFit.contain,
                  ),
                ),
              ],
            ),
          ),
          const Text(
            'Sohbete devam et',
            style: TextStyle(
              fontFamily: AppFont.family,
              fontWeight: FontWeight.w800,
              fontSize: 21,
              color: AppColors.black,
              letterSpacing: -0.5,
            ),
          ),
          const SizedBox(height: 6),
          const Text(
            'Mesaj kredisi al ve konusmalarini surdur',
            style: TextStyle(
              fontFamily: AppFont.family,
              fontSize: 13,
              color: Color(0xFF666666),
            ),
          ),
          const SizedBox(height: 22),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: _JetonPack(
                    amount: '100',
                    oldPrice: '149.99 TL',
                    priceMajor: '89',
                    priceMinor: '.99',
                    discount: '-40%',
                    accent: const Color(0xFFFF9794),
                    selected: _selected == 0,
                    onTap: () => setState(() => _selected = 0),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: _JetonPack(
                    amount: '500',
                    oldPrice: '699.99 TL',
                    priceMajor: '349',
                    priceMinor: '.99',
                    discount: '-50%',
                    accent: const Color(0xFF2B7FFF),
                    featured: true,
                    selected: _selected == 1,
                    onTap: () => setState(() => _selected = 1),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: _JetonPack(
                    amount: '1000',
                    oldPrice: '1499.99 TL',
                    priceMajor: '599',
                    priceMinor: '.99',
                    discount: '-60%',
                    accent: const Color(0xFFFDB384),
                    selected: _selected == 2,
                    onTap: () => setState(() => _selected = 2),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 20),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 20),
            child: GradientButton(
              label: 'Satin Al',
              onTap: () => Navigator.of(context).maybePop(),
            ),
          ),
          const SizedBox(height: 12),
          const Text(
            'Istedigin zaman kullanabilirsin, suresi dolmaz',
            style: TextStyle(
              fontFamily: AppFont.family,
              fontSize: 11,
              color: Color(0xFFCCCCCC),
            ),
          ),
          const SizedBox(height: 12),
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: const [
              Icon(
                CupertinoIcons.shield_lefthalf_fill,
                size: 12,
                color: Color(0xFF999999),
              ),
              SizedBox(width: 4),
              Text(
                'Guvenli Odeme',
                style: TextStyle(
                  fontFamily: AppFont.family,
                  fontSize: 10,
                  color: Color(0xFF999999),
                ),
              ),
              SizedBox(width: 20),
              Icon(
                CupertinoIcons.lock_fill,
                size: 12,
                color: Color(0xFF999999),
              ),
              SizedBox(width: 4),
              Text(
                '256-bit SSL',
                style: TextStyle(
                  fontFamily: AppFont.family,
                  fontSize: 10,
                  color: Color(0xFF999999),
                ),
              ),
            ],
          ),
          SizedBox(height: MediaQuery.paddingOf(context).bottom + 16),
        ],
      ),
    );
  }
}

class _JetonPack extends StatelessWidget {
  final String amount;
  final String oldPrice;
  final String priceMajor;
  final String priceMinor;
  final String discount;
  final Color accent;
  final bool featured;
  final bool selected;
  final VoidCallback onTap;

  const _JetonPack({
    required this.amount,
    required this.oldPrice,
    required this.priceMajor,
    required this.priceMinor,
    required this.discount,
    required this.accent,
    required this.onTap,
    required this.selected,
    this.featured = false,
  });

  @override
  Widget build(BuildContext context) {
    return PressableScale(
      onTap: onTap,
      scale: 0.97,
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          AnimatedContainer(
            duration: const Duration(milliseconds: 180),
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 14),
            decoration: BoxDecoration(
              color: featured ? const Color(0x1A2B7FFF) : AppColors.white,
              borderRadius: BorderRadius.circular(20),
              border: Border.all(
                color: featured
                    ? const Color(0xFF2B7FFF)
                    : const Color(0xFFEFEFEF),
                width: featured ? 2 : 1,
              ),
              boxShadow: featured
                  ? const [
                      BoxShadow(
                        color: Color(0x1A5C6BFF),
                        blurRadius: 24,
                        offset: Offset(0, 4),
                      ),
                    ]
                  : null,
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 10,
                    vertical: 2,
                  ),
                  decoration: BoxDecoration(
                    color: accent.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Text(
                    discount,
                    style: TextStyle(
                      fontFamily: AppFont.family,
                      fontWeight: FontWeight.w800,
                      fontSize: 10,
                      color: accent,
                    ),
                  ),
                ),
                const SizedBox(height: 10),
                Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Image.asset(
                      'assets/images/icon_diamond.png',
                      width: 18,
                      height: 18,
                    ),
                    const SizedBox(width: 4),
                    Text(
                      amount,
                      style: TextStyle(
                        fontFamily: AppFont.family,
                        fontWeight: FontWeight.w800,
                        fontSize: featured ? 32 : 28,
                        color: AppColors.black,
                        height: 1.0,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 4),
                const Text(
                  'tas',
                  style: TextStyle(
                    fontFamily: AppFont.family,
                    fontWeight: FontWeight.w600,
                    fontSize: 11,
                    color: Color(0xFF999999),
                  ),
                ),
                const SizedBox(height: 10),
                Container(
                  height: 1,
                  color: featured
                      ? const Color(0x1F5C6BFF)
                      : const Color(0xFFEFEFEF),
                ),
                const SizedBox(height: 10),
                Text(
                  oldPrice,
                  style: const TextStyle(
                    fontFamily: AppFont.family,
                    fontSize: 11,
                    color: Color(0xFFCCCCCC),
                    decoration: TextDecoration.lineThrough,
                  ),
                ),
                const SizedBox(height: 6),
                Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      priceMajor,
                      style: TextStyle(
                        fontFamily: AppFont.family,
                        fontWeight: FontWeight.w800,
                        fontSize: 17,
                        color: featured
                            ? const Color(0xFF2B7FFF)
                            : AppColors.black,
                      ),
                    ),
                    Padding(
                      padding: const EdgeInsets.only(top: 4),
                      child: Text(
                        priceMinor,
                        style: TextStyle(
                          fontFamily: AppFont.family,
                          fontWeight: FontWeight.w700,
                          fontSize: 12,
                          color: featured
                              ? const Color(0xFF2B7FFF)
                              : AppColors.black,
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
          if (featured)
            Positioned(
              top: -10,
              left: 8,
              right: 8,
              child: Container(
                height: 20,
                decoration: BoxDecoration(
                  color: const Color(0xFF2B7FFF),
                  borderRadius: BorderRadius.circular(10),
                ),
                alignment: Alignment.center,
                child: const Text(
                  'EN POPULER',
                  style: TextStyle(
                    fontFamily: AppFont.family,
                    fontWeight: FontWeight.w800,
                    fontSize: 9,
                    color: AppColors.white,
                    letterSpacing: 0.3,
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}
