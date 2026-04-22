import 'dart:async';
import 'dart:io';

import 'package:magmug/l10n/app_localizations.dart';
import 'package:magmug/app_core.dart';
import 'package:image_picker/image_picker.dart';
import 'package:in_app_purchase/in_app_purchase.dart';
import 'package:magmug/features/blocked_users/domain/entities/blocked_user.dart';
import 'package:magmug/features/blocked_users/presentation/providers/blocked_users_providers.dart';
import 'package:magmug/features/match/match_flow.dart';
import 'package:magmug/features/onboarding/onboarding_flow.dart';
import 'package:magmug/features/payment/store_purchase_service.dart';
import 'package:magmug/features/profile/profile_photo_utils.dart';
import 'package:magmug/features/profile/profile_purchase_utils.dart';
import 'package:magmug/features/profile/widgets/profile_form_widgets.dart';
import 'package:magmug/features/profile/widgets/profile_help_widgets.dart';
import 'package:magmug/features/profile/widgets/profile_overview_widgets.dart';
import 'package:magmug/features/profile/widgets/profile_paywall_widgets.dart';
import 'package:magmug/features/profile/widgets/profile_policy_widgets.dart';
import 'package:magmug/features/profile/widgets/profile_photo_widgets.dart';
import 'package:magmug/features/profile/widgets/profile_purchase_widgets.dart';
import 'package:magmug/features/profile/widgets/profile_sheet_widgets.dart';
import 'package:magmug/features/profile/widgets/profile_settings_widgets.dart';
import 'package:magmug/features/profile/widgets/profile_support_widgets.dart';

// =============================================================================

@immutable
class NotificationPrefs {
  final bool notificationsEnabled;
  final bool vibrationEnabled;

  const NotificationPrefs({
    this.notificationsEnabled = true,
    this.vibrationEnabled = true,
  });

  factory NotificationPrefs.fromUser(AppUser? user) {
    return NotificationPrefs(
      notificationsEnabled: user?.notificationsEnabled ?? true,
      vibrationEnabled: user?.vibrationEnabled ?? true,
    );
  }

  NotificationPrefs copyWith({
    bool? notificationsEnabled,
    bool? vibrationEnabled,
  }) {
    return NotificationPrefs(
      notificationsEnabled: notificationsEnabled ?? this.notificationsEnabled,
      vibrationEnabled: vibrationEnabled ?? this.vibrationEnabled,
    );
  }
}

class ProfileScreen extends ConsumerWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context)!;
    final user = ref.watch(appAuthProvider).asData?.value?.user;
    final int gem =
        user?.gemBalance ??
        ref.watch(matchProvider.select((s) => s.gemBalance));
    final blockedUsersAsync = ref.watch(blockedUsersProvider);
    final publicSettings = ref.watch(appPublicSettingsProvider).asData?.value;
    final appLanguage =
        ref.watch(appLanguageProvider).asData?.value ??
        AppPreferencesStorage.fallbackLanguage();
    final blockedUsersCount = blockedUsersAsync.when(
      data: (users) => '${users.length}',
      loading: () => null,
      error: (_, _) => null,
    );

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
                  Expanded(
                    child: Text(
                      l10n.profileScreenTitle,
                      style: const TextStyle(
                        fontFamily: AppFont.family,
                        fontWeight: FontWeight.w800,
                        fontSize: 18,
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
                  ProfileHeaderSection(
                    photoManagerSheetBuilder: (_) =>
                        const ProfilePhotoManagerSheet(),
                    editProfileSheetBuilder: (_) => const EditProfileSheet(),
                  ),
                  const SizedBox(height: 20),
                  PremiumPromoBanner(
                    onTap: () => Navigator.of(
                      context,
                    ).push(cupertinoRoute(const PaywallScreen())),
                  ),
                  const SizedBox(height: 12),
                  ProfileSettingsGroup(
                    children: [
                      ProfileSettingsTile(
                        icon: CupertinoIcons.star_circle_fill,
                        label: l10n.profileGemBalance,
                        trailingText: formatGem(gem),
                        onTap: () => openSheet(const JetonPurchaseSheet()),
                      ),
                    ],
                  ),
                  ProfileSettingsGroup(
                    children: [
                      ProfileSettingsTile(
                        icon: CupertinoIcons.chat_bubble_2_fill,
                        label: l10n.profileContactUs,
                        trailingText: publicSettings?.contactChannelLabel,
                        onTap: () => openSheet(const HelpSheet()),
                      ),
                    ],
                  ),
                  ProfileMediaSection(
                    managerSheetBuilder: (_) =>
                        const ProfilePhotoManagerSheet(),
                  ),
                  const SizedBox(height: 16),
                  ProfileSettingsGroup(
                    children: [
                      ProfileSettingsTile(
                        icon: CupertinoIcons.bell,
                        label: l10n.profileNotifications,
                        showDivider: true,
                        onTap: () => openSheet(const NotificationPrefsSheet()),
                      ),
                      ProfileSettingsTile(
                        icon: CupertinoIcons.globe,
                        label: l10n.profileLanguage,
                        trailingText: appLanguage.label,
                        showDivider: true,
                        onTap: () => openSheet(const LanguageSheet()),
                      ),
                      ProfileSettingsTile(
                        icon: CupertinoIcons.nosign,
                        label: l10n.profileBlockedUsers,
                        badgeCount: blockedUsersCount,
                        onTap: () => openSheet(const BlockedUsersSheet()),
                      ),
                    ],
                  ),
                  ProfileSettingsGroup(
                    children: [
                      ProfileSettingsTile(
                        icon: CupertinoIcons.question_circle,
                        label: l10n.profileHelp,
                        showDivider: true,
                        onTap: () => openSheet(const HelpSheet()),
                      ),
                      ProfileSettingsTile(
                        icon: CupertinoIcons.arrow_clockwise,
                        label: l10n.profileRestorePurchases,
                        onTap: () => openSheet(const RestorePurchasesSheet()),
                      ),
                    ],
                  ),
                  ProfileSettingsGroup(
                    children: [
                      ProfileSettingsTile(
                        icon: CupertinoIcons.shield,
                        label: l10n.profilePrivacyPolicy,
                        showDivider: true,
                        onTap: () => Navigator.of(
                          context,
                        ).push(cupertinoRoute(const PrivacyPolicyScreen())),
                      ),
                      ProfileSettingsTile(
                        icon: CupertinoIcons.doc_text,
                        label: l10n.profileKvkk,
                        showDivider: true,
                        onTap: () => Navigator.of(
                          context,
                        ).push(cupertinoRoute(const KvkkScreen())),
                      ),
                      ProfileSettingsTile(
                        icon: CupertinoIcons.doc_plaintext,
                        label: l10n.profileTerms,
                        onTap: () => Navigator.of(
                          context,
                        ).push(cupertinoRoute(const TermsOfUseScreen())),
                      ),
                    ],
                  ),
                  ProfileSettingsGroup(
                    children: [
                      ProfileSettingsTile(
                        icon: CupertinoIcons.exclamationmark_triangle_fill,
                        label: l10n.profileDeleteAccount,
                        accentColor: const Color(0xFFD97706),
                        iconBackgroundColor: const Color(0xFFFFF7ED),
                        showDivider: true,
                        onTap: () =>
                            openSheet(const DeleteAccountConfirmSheet()),
                      ),
                      ProfileSettingsTile(
                        icon: CupertinoIcons.arrow_right_square,
                        label: l10n.profileSignOut,
                        danger: true,
                        onTap: () => openSheet(const SignOutConfirmSheet()),
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

class ProfilePhotoManagerSheet extends ConsumerStatefulWidget {
  const ProfilePhotoManagerSheet({super.key});

  @override
  ConsumerState<ProfilePhotoManagerSheet> createState() =>
      _ProfilePhotoManagerSheetState();
}

class _ProfilePhotoManagerSheetState
    extends ConsumerState<ProfilePhotoManagerSheet> {
  final AppAuthApi _authApi = AppAuthApi();
  final ImagePicker _picker = ImagePicker();
  bool _loading = true;
  bool _uploading = false;
  String? _notice;
  String? _pendingUploadPath;
  bool _pendingUploadIsVideo = false;
  double _pendingUploadProgress = 0;
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
      if (!mounted) {
        return;
      }
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
      if (!mounted) {
        return;
      }
      setState(() {
        _photos = photos;
        _loading = false;
      });
    } catch (error) {
      if (!mounted) {
        return;
      }
      setState(() {
        _notice = AppAuthErrorFormatter.messageFrom(error);
        _loading = false;
      });
    }
  }

  bool get _atMediaLimit => _photos.length >= kProfileMediaLimit;

  bool get _hasPrimaryPhoto =>
      _photos.any((photo) => photo.isPhoto && photo.isPrimary);

  void _setMediaLimitNotice() {
    setState(() {
      _notice = profileMediaLimitNotice(kProfileMediaLimit);
    });
  }

  Future<void> _refreshMediaState({String? notice}) async {
    final token = ref.read(appAuthProvider).asData?.value?.token;
    if (token == null || token.trim().isEmpty) {
      return;
    }

    final photos = await _authApi.fetchProfilePhotos(token);
    await ref.read(appAuthProvider.notifier).refreshCurrentUser();
    ref.read(onboardProvider.notifier).clearPhoto();
    if (!mounted) {
      return;
    }
    setState(() {
      _photos = photos;
      _notice = notice;
    });
  }

  Future<void> _uploadMedia(String filePath, {required bool isVideo}) async {
    final l10n = AppLocalizations.of(context)!;
    final token = ref.read(appAuthProvider).asData?.value?.token;
    if (token == null || token.trim().isEmpty) {
      setState(() {
        _notice = l10n.profileActionAuthRequired;
      });
      return;
    }

    setState(() {
      _uploading = true;
      _notice = null;
      _pendingUploadPath = filePath;
      _pendingUploadIsVideo = isVideo;
      _pendingUploadProgress = 0;
    });

    String uploadPath = filePath;
    try {
      uploadPath = await prepareProfileMediaUploadPath(
        filePath,
        isVideo: isVideo,
      );
      if (isVideo) {
        final uploadFile = File(uploadPath);
        if (await uploadFile.exists()) {
          final uploadBytes = await uploadFile.length();
          if (uploadBytes > profileMaxSafeUploadBytes) {
            if (mounted) {
              setState(() {
                _notice =
                    'Video boyutu hala yuksek. Daha kisa bir video secip tekrar dene.';
              });
            }
            debugPrint(
              '[ProfileUpload] skip upload because file is still too large: $uploadBytes bytes',
            );
            return;
          }
        }
      }

      final uploaded = await _authApi.uploadProfileMedia(
        token,
        filePath: uploadPath,
        markAsPrimary: !isVideo && !_hasPrimaryPhoto,
        onProgress: (progress) {
          if (!mounted) {
            return;
          }
          setState(() {
            _pendingUploadProgress = progress;
          });
        },
      );

      await _refreshMediaState(
        notice: uploaded.isVideo
            ? l10n.profileVideoAdded
            : l10n.profilePhotoDraftAdded,
      );
    } catch (error) {
      if (!mounted) {
        return;
      }
      setState(() {
        _notice = AppAuthErrorFormatter.messageFrom(error);
      });
      debugPrint(
        '[ProfileUpload] fail isVideo=$isVideo path=$uploadPath error=$error',
      );
    } finally {
      if (uploadPath != filePath) {
        try {
          final tempFile = File(uploadPath);
          if (await tempFile.exists()) {
            await tempFile.delete();
          }
        } catch (_) {}
      }
      if (mounted) {
        setState(() {
          _uploading = false;
          _pendingUploadPath = null;
          _pendingUploadProgress = 0;
          _pendingUploadIsVideo = false;
        });
      }
    }
  }

  Future<void> _pickMediaFrom(
    ImageSource source, {
    required bool isVideo,
  }) async {
    if (_uploading) {
      return;
    }
    if (_atMediaLimit) {
      _setMediaLimitNotice();
      return;
    }

    try {
      final pickedPath = await pickProfileMediaPath(
        _picker,
        source: source,
        isVideo: isVideo,
      );
      if (pickedPath == null) {
        return;
      }
      await _uploadMedia(pickedPath, isVideo: isVideo);
    } catch (error) {
      if (!mounted) {
        return;
      }
      setState(() {
        _notice = AppAuthErrorFormatter.messageFrom(error);
      });
    }
  }

  Future<void> _showMediaActions(AppProfilePhoto photo) async {
    if (_uploading) {
      return;
    }

    final l10n = AppLocalizations.of(context)!;

    final action = await showProfileMediaActionSheet(
      context,
      photo: photo,
      l10n: l10n,
    );

    if (!mounted || action == null) {
      return;
    }

    final token = ref.read(appAuthProvider).asData?.value?.token;
    if (token == null || token.trim().isEmpty) {
      setState(() {
        _notice = l10n.profileActionAuthRequired;
      });
      return;
    }

    setState(() {
      _uploading = true;
      _notice = null;
    });

    try {
      if (action == ProfileMediaAction.makePrimary) {
        await _authApi.updateProfileMedia(
          token,
          mediaId: photo.id,
          markAsPrimary: true,
        );
        await _refreshMediaState(notice: l10n.profilePhotoUpdated);
      }

      if (action == ProfileMediaAction.delete) {
        await _authApi.deleteProfileMedia(token, mediaId: photo.id);
        await _refreshMediaState(
          notice: photo.isVideo
              ? l10n.profileVideoRemoved
              : l10n.profilePhotoRemoved,
        );
      }
    } catch (error) {
      if (!mounted) {
        return;
      }
      setState(() {
        _notice = AppAuthErrorFormatter.messageFrom(error);
      });
    } finally {
      if (mounted) {
        setState(() {
          _uploading = false;
        });
      }
    }
  }

  void _openViewer(AppProfilePhoto selected) {
    final media = sortProfileMedia(_photos);
    openProfileMediaViewer(
      context,
      media: media,
      initialIndex: resolveProfileMediaInitialIndex(media, selected),
    );
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final user = ref.watch(appAuthProvider).asData?.value?.user;
    final primaryPhoto = resolvePrimaryProfilePhoto(_photos);
    final placeholderLabel = resolveProfilePhotoPlaceholderLabel(user, l10n);

    final photoCount = profilePhotoCount(_photos);
    final videoCount = profileVideoCount(_photos);
    final galleryTiles = buildProfilePhotoGalleryTiles(
      photos: _photos,
      maxLimit: kProfileMediaLimit,
      pendingUploadPath: _pendingUploadPath,
      pendingUploadIsVideo: _pendingUploadIsVideo,
      pendingUploadProgress: _pendingUploadProgress,
      primaryBadgeLabel: l10n.profileBadgePrimary,
      videoBadgeLabel: l10n.profileBadgeVideo,
      onPhotoTap: _openViewer,
      onPhotoMoreTap: _showMediaActions,
    );
    final actionDisabled = _uploading || _atMediaLimit;

    return ProfilePhotoManagerSheetView(
      title: l10n.profilePhotoTitle,
      subtitle: l10n.profilePhotoSubtitle,
      preview: ProfilePhotoManagerPreview(
        primaryPhoto: primaryPhoto,
        fallbackImageUrl: user?.profileImageUrl,
        placeholderLabel: placeholderLabel,
      ),
      summaryTitle: photoCount > 0
          ? l10n.profileActivePhotosCount(photoCount)
          : l10n.profileNoActivePhotos,
      summarySubtitle: videoCount > 0
          ? l10n.profileVideoCount(videoCount)
          : l10n.profilePrimaryHint,
      takePhotoLabel: l10n.profileTakePhoto,
      pickPhotoLabel: l10n.profilePickPhoto,
      pickVideoLabel: l10n.profilePickVideo,
      onTakePhoto: actionDisabled
          ? null
          : () => _pickMediaFrom(ImageSource.camera, isVideo: false),
      onPickPhoto: actionDisabled
          ? null
          : () => _pickMediaFrom(ImageSource.gallery, isVideo: false),
      onPickVideo: actionDisabled
          ? null
          : () => _pickMediaFrom(ImageSource.gallery, isVideo: true),
      galleryTitle: l10n.profileGalleryTitle,
      galleryCountLabel: '${_photos.length}/$kProfileMediaLimit',
      isGalleryLoading: _loading,
      galleryTiles: galleryTiles,
      notice: _notice,
      doneLabel: _uploading ? '${l10n.commonLoading}...' : l10n.commonDone,
      onDone: _uploading ? null : () => Navigator.of(context).maybePop(),
    );
  }
}

class PrivacyPolicyScreen extends StatelessWidget {
  const PrivacyPolicyScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;

    return ProfilePolicyScaffold(
      title: l10n.privacyTitle,
      sections: [
        (heading: l10n.privacyHeading1, body: l10n.privacyBody1),
        (heading: l10n.privacyHeading2, body: l10n.privacyBody2),
        (heading: l10n.privacyHeading3, body: l10n.privacyBody3),
        (heading: l10n.privacyHeading4, body: l10n.privacyBody4),
        (heading: l10n.privacyHeading5, body: l10n.privacyBody5),
      ],
    );
  }
}

class TermsOfUseScreen extends StatelessWidget {
  const TermsOfUseScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;

    return ProfilePolicyScaffold(
      title: l10n.termsTitle,
      sections: [
        (heading: l10n.termsHeading1, body: l10n.termsBody1),
        (heading: l10n.termsHeading2, body: l10n.termsBody2),
        (heading: l10n.termsHeading3, body: l10n.termsBody3),
        (heading: l10n.termsHeading4, body: l10n.termsBody4),
        (heading: l10n.termsHeading5, body: l10n.termsBody5),
      ],
    );
  }
}

// ------ Sheets ----------------------------------------------------------------

class RestorePurchasesSheet extends StatefulWidget {
  const RestorePurchasesSheet({super.key});

  @override
  State<RestorePurchasesSheet> createState() => _RestorePurchasesSheetState();
}

class _RestorePurchasesSheetState extends State<RestorePurchasesSheet> {
  final InAppPurchase _inAppPurchase = InAppPurchase.instance;
  StreamSubscription<List<PurchaseDetails>>? _purchaseSubscription;
  Timer? _resultTimer;
  late AppLocalizations _l10n;
  bool _checkingStore = true;
  bool _storeAvailable = false;
  bool _restoring = false;
  int _restoredCount = 0;
  String? _notice;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _l10n = AppLocalizations.of(context)!;
  }

  @override
  void initState() {
    super.initState();
    _purchaseSubscription = _inAppPurchase.purchaseStream.listen(
      _handlePurchaseUpdates,
      onError: (_) {
        if (!mounted) {
          return;
        }
        setState(() {
          _restoring = false;
          _notice = _l10n.restorePurchasesConnectionFailed;
        });
      },
    );
    _checkStoreAvailability();
  }

  @override
  void dispose() {
    _resultTimer?.cancel();
    _purchaseSubscription?.cancel();
    super.dispose();
  }

  Future<void> _checkStoreAvailability() async {
    final l10n = AppLocalizations.of(context)!;
    final isAvailable = await _inAppPurchase.isAvailable();
    if (!mounted) {
      return;
    }
    setState(() {
      _checkingStore = false;
      _storeAvailable = isAvailable;
      if (!isAvailable) {
        _notice = l10n.restorePurchasesUnavailable;
      }
    });
  }

  Future<void> _restorePurchases() async {
    final l10n = AppLocalizations.of(context)!;
    if (_checkingStore || _restoring) {
      return;
    }
    if (!_storeAvailable) {
      setState(() {
        _notice = l10n.restorePurchasesStoreRequired;
      });
      return;
    }

    setState(() {
      _restoring = true;
      _restoredCount = 0;
      _notice = null;
    });

    _resultTimer?.cancel();
    _resultTimer = Timer(const Duration(seconds: 4), () {
      if (!mounted || !_restoring) {
        return;
      }
      setState(() {
        _restoring = false;
        _notice = resolveRestorePurchasesTimeoutNotice(l10n, _restoredCount);
      });
    });

    await _inAppPurchase.restorePurchases();
  }

  Future<void> _handlePurchaseUpdates(List<PurchaseDetails> purchases) async {
    final l10n = AppLocalizations.of(context)!;
    final resolution = resolveRestorePurchasesUpdate(purchases, l10n);

    for (final purchase in resolution.purchasesToComplete) {
      await _inAppPurchase.completePurchase(purchase);
    }

    if (!mounted) {
      return;
    }

    if (resolution.restoredInBatch > 0) {
      _resultTimer?.cancel();
      setState(() {
        _restoring = false;
        _restoredCount += resolution.restoredInBatch;
        _notice = l10n.restorePurchasesRestoredCount(_restoredCount);
      });
      return;
    }

    if (resolution.notice != null) {
      setState(() {
        if (resolution.shouldStopRestoring) {
          _restoring = false;
          _resultTimer?.cancel();
        }
        _notice = resolution.notice;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final success = _restoredCount > 0;

    return ProfileRestorePurchasesSheetView(
      title: l10n.profileRestorePurchases,
      subtitle: l10n.restorePurchasesSubtitle,
      stepOneTitle: l10n.restorePurchasesStep1Title,
      stepOneDescription: l10n.restorePurchasesStep1Description,
      stepTwoTitle: l10n.restorePurchasesStep2Title,
      stepTwoDescription: l10n.restorePurchasesStep2Description,
      stepThreeTitle: l10n.restorePurchasesStep3Title,
      stepThreeDescription: l10n.restorePurchasesStep3Description,
      notice: _notice,
      success: success,
      primaryActionLabel: resolveRestorePurchasesPrimaryActionLabel(
        l10n,
        checkingStore: _checkingStore,
        restoring: _restoring,
      ),
      onPrimaryAction: _checkingStore || _restoring ? null : _restorePurchases,
      closeLabel: l10n.commonClose,
      onClose: () => Navigator.of(context).maybePop(),
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
    final l10n = AppLocalizations.of(context)!;
    return ProfileConfirmSheet(
      title: l10n.signOutConfirmTitle,
      subtitle: l10n.signOutConfirmSubtitle,
      confirmLabel: _submitting ? l10n.signOutProcessing : l10n.profileSignOut,
      destructive: true,
      onConfirm: _signOut,
    );
  }
}

class DeleteAccountConfirmSheet extends ConsumerStatefulWidget {
  const DeleteAccountConfirmSheet({super.key});

  @override
  ConsumerState<DeleteAccountConfirmSheet> createState() =>
      _DeleteAccountConfirmSheetState();
}

class _DeleteAccountConfirmSheetState
    extends ConsumerState<DeleteAccountConfirmSheet> {
  Future<void> _showFinalConfirmation() async {
    final rootNavigator = Navigator.of(context, rootNavigator: true);
    rootNavigator.pop();
    await showCupertinoModalPopup<void>(
      context: rootNavigator.context,
      builder: (_) => const DeleteAccountFinalConfirmSheet(),
    );
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    return ProfileConfirmSheet(
      title: l10n.deleteAccountStartTitle,
      subtitle: l10n.deleteAccountStartSubtitle,
      confirmLabel: l10n.commonContinue,
      confirmColor: const Color(0xFFD97706),
      onConfirm: _showFinalConfirmation,
    );
  }
}

class DeleteAccountFinalConfirmSheet extends ConsumerStatefulWidget {
  const DeleteAccountFinalConfirmSheet({super.key});

  @override
  ConsumerState<DeleteAccountFinalConfirmSheet> createState() =>
      _DeleteAccountFinalConfirmSheetState();
}

class _DeleteAccountFinalConfirmSheetState
    extends ConsumerState<DeleteAccountFinalConfirmSheet> {
  bool _submitting = false;
  String? _notice;

  Future<void> _deleteAccount() async {
    if (_submitting) return;

    setState(() {
      _submitting = true;
      _notice = null;
    });

    try {
      ref.read(onboardProvider.notifier).reset();
      await ref.read(appAuthProvider.notifier).deleteAccount();
      if (!mounted) return;

      Navigator.of(context, rootNavigator: true).pushAndRemoveUntil(
        cupertinoRoute(const OnboardScreen()),
        (route) => false,
      );
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _notice = AppAuthErrorFormatter.messageFrom(error);
      });
    } finally {
      if (mounted) {
        setState(() => _submitting = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    return ProfileConfirmSheet(
      title: l10n.deleteAccountFinalTitle,
      subtitle: _notice ?? l10n.deleteAccountFinalSubtitle,
      confirmLabel: _submitting
          ? l10n.deleteAccountDeleting
          : l10n.profileDeleteAccount,
      destructive: true,
      onConfirm: _deleteAccount,
    );
  }
}

class UnblockConfirmSheet extends ConsumerStatefulWidget {
  final BlockedUser user;

  const UnblockConfirmSheet({super.key, required this.user});

  @override
  ConsumerState<UnblockConfirmSheet> createState() =>
      _UnblockConfirmSheetState();
}

class _UnblockConfirmSheetState extends ConsumerState<UnblockConfirmSheet> {
  bool _submitting = false;
  String? _notice;

  Future<void> _unblock() async {
    if (_submitting) {
      return;
    }

    setState(() {
      _submitting = true;
      _notice = null;
    });

    try {
      await ref
          .read(blockedUsersActionControllerProvider.notifier)
          .unblockUser(widget.user.id);
      if (!mounted) {
        return;
      }
      Navigator.of(context).maybePop();
    } catch (error) {
      if (!mounted) {
        return;
      }
      setState(() {
        _notice = AppAuthErrorFormatter.messageFrom(error);
      });
    } finally {
      if (mounted) {
        setState(() => _submitting = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final displayName = widget.user.displayName.trim();
    final l10n = AppLocalizations.of(context)!;

    return ProfileConfirmSheet(
      title: l10n.unblockConfirmTitle(displayName),
      subtitle: _notice ?? l10n.unblockConfirmSubtitle,
      confirmLabel: _submitting ? l10n.unblockProcessing : l10n.unblockAction,
      onConfirm: _unblock,
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
    final name = data.name.isNotEmpty ? data.name : (user?.firstName ?? '');
    final surname = data.surname.isNotEmpty
        ? data.surname
        : (user?.surname ?? '');
    final username = data.username.isNotEmpty
        ? data.username
        : (user?.username ?? '');
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
    final l10n = AppLocalizations.of(context)!;
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
        if (!context.mounted) return;
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

    return ProfileAdaptiveBottomSheet(
      child: ProfileEditProfileSheetView(
        title: l10n.profileEditProfile,
        firstNameLabel: l10n.editProfileFirstName,
        firstNameController: _name,
        surnameLabel: l10n.editProfileSurname,
        surnameController: _surname,
        usernameLabel: l10n.editProfileUsername,
        usernameController: _username,
        isUsernameReadOnly: user != null,
        usernameHint: l10n.editProfileUsernameUnsupported,
        bioLabel: l10n.editProfileBio,
        bioController: _bio,
        bioPlaceholder: l10n.editProfileBioPlaceholder,
        errorMessage: _submitMessage,
        saveLabel: _submitting ? l10n.commonSaving : l10n.commonSave,
        onSave: _submitting ? null : saveProfile,
      ),
    );
  }
}

class LanguageSheet extends ConsumerStatefulWidget {
  const LanguageSheet({super.key});

  @override
  ConsumerState<LanguageSheet> createState() => _LanguageSheetState();
}

class _LanguageSheetState extends ConsumerState<LanguageSheet> {
  late AppLanguage _selected;
  bool _hasChanges = false;
  bool _isSaving = false;

  @override
  void initState() {
    super.initState();
    _selected =
        ref.read(appLanguageProvider).asData?.value ??
        AppPreferencesStorage.fallbackLanguage();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final persistedLanguage = ref.watch(appLanguageProvider).asData?.value;
    final selectedLanguage = _hasChanges
        ? _selected
        : (persistedLanguage ?? _selected);

    return ProfileAdaptiveBottomSheet(
      child: ProfileLanguageSheetView(
        title: l10n.languageSheetTitle,
        languages: AppLanguage.values,
        selectedLanguage: selectedLanguage,
        onSelect: (language) {
          setState(() {
            _selected = language;
            _hasChanges = true;
          });
        },
        saveLabel: _isSaving ? l10n.languageChanging : l10n.languageChange,
        onSave: _isSaving
            ? null
            : () async {
                setState(() => _isSaving = true);
                try {
                  await ref
                      .read(appLanguageProvider.notifier)
                      .setLanguage(selectedLanguage);
                  if (!context.mounted) {
                    return;
                  }
                  Navigator.of(context).maybePop();
                } catch (error) {
                  if (!mounted) {
                    return;
                  }
                  await showCupertinoDialog<void>(
                    context: context,
                    builder: (dialogContext) => CupertinoAlertDialog(
                      title: Text(
                        l10n.languageUpdateFailedTitle,
                        style: const TextStyle(fontFamily: AppFont.family),
                      ),
                      content: Padding(
                        padding: const EdgeInsets.only(top: 8),
                        child: Text(
                          AppAuthErrorFormatter.messageFrom(error),
                          style: const TextStyle(fontFamily: AppFont.family),
                        ),
                      ),
                      actions: [
                        CupertinoDialogAction(
                          onPressed: () => Navigator.of(dialogContext).pop(),
                          child: Text(
                            l10n.commonOk,
                            style: const TextStyle(fontFamily: AppFont.family),
                          ),
                        ),
                      ],
                    ),
                  );
                } finally {
                  if (mounted) {
                    setState(() => _isSaving = false);
                  }
                }
              },
      ),
    );
  }
}

class NotificationPrefsSheet extends ConsumerStatefulWidget {
  const NotificationPrefsSheet({super.key});

  @override
  ConsumerState<NotificationPrefsSheet> createState() =>
      _NotificationPrefsSheetState();
}

class _NotificationPrefsSheetState
    extends ConsumerState<NotificationPrefsSheet> {
  late NotificationPrefs _prefs;
  bool _isInitialized = false;
  bool _isSaving = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_isInitialized) {
      return;
    }

    final user = ref.read(appAuthProvider).asData?.value?.user;
    _prefs = NotificationPrefs.fromUser(user);
    _isInitialized = true;
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final authState = ref.watch(appAuthProvider);
    final user = authState.asData?.value?.user;
    final serverPrefs = NotificationPrefs.fromUser(user);
    final hasChanges =
        _prefs.notificationsEnabled != serverPrefs.notificationsEnabled ||
        _prefs.vibrationEnabled != serverPrefs.vibrationEnabled;

    return ProfileAdaptiveBottomSheet(
      child: ProfileNotificationPrefsSheetView(
        title: l10n.profileNotifications,
        notificationsTitle: l10n.profileNotifications,
        notificationsDescription: l10n.notificationsDescription,
        notificationsEnabled: _prefs.notificationsEnabled,
        onNotificationsChanged: (_) {
          setState(() {
            _prefs = _prefs.copyWith(
              notificationsEnabled: !_prefs.notificationsEnabled,
            );
          });
        },
        vibrationTitle: l10n.notificationsVibration,
        vibrationDescription: l10n.notificationsVibrationDescription,
        vibrationEnabled: _prefs.vibrationEnabled,
        onVibrationChanged: (_) {
          setState(() {
            _prefs = _prefs.copyWith(
              vibrationEnabled: !_prefs.vibrationEnabled,
            );
          });
        },
        saveLabel: _isSaving ? l10n.commonSaving : l10n.commonSave,
        onSave: _isSaving
            ? null
            : () => _savePreferences(context, hasChanges: hasChanges),
      ),
    );
  }

  Future<void> _savePreferences(
    BuildContext context, {
    required bool hasChanges,
  }) async {
    final l10n = AppLocalizations.of(context)!;
    if (!hasChanges) {
      Navigator.of(context).maybePop();
      return;
    }

    setState(() {
      _isSaving = true;
    });

    try {
      await ref
          .read(appAuthProvider.notifier)
          .updateNotificationPreferences(
            notificationsEnabled: _prefs.notificationsEnabled,
            vibrationEnabled: _prefs.vibrationEnabled,
          );
      if (!context.mounted) {
        return;
      }
      Navigator.of(context).maybePop();
    } catch (error) {
      if (!context.mounted) {
        return;
      }

      showCupertinoDialog<void>(
        context: context,
        builder: (context) => CupertinoAlertDialog(
          title: Text(l10n.saveFailedTitle),
          content: Text(AppAuthErrorFormatter.messageFrom(error)),
          actions: [
            CupertinoDialogAction(
              onPressed: () => Navigator.of(context).pop(),
              child: Text(l10n.commonOk),
            ),
          ],
        ),
      );
    } finally {
      if (mounted) {
        setState(() {
          _isSaving = false;
        });
      }
    }
  }
}

class BlockedUsersSheet extends ConsumerWidget {
  const BlockedUsersSheet({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context)!;
    final blockedUsersAsync = ref.watch(blockedUsersProvider);

    return ProfileAdaptiveBottomSheet(
      scrollable: false,
      maxHeightFactor: 0.78,
      child: ProfileBlockedUsersSheetView(
        title: l10n.profileBlockedUsers,
        emptyMessage: l10n.blockedUsersEmpty,
        retryLabel: l10n.commonRetry,
        blockedUsersAsync: blockedUsersAsync,
        onRetry: () => ref.invalidate(blockedUsersProvider),
        onUnblock: (user) {
          showCupertinoModalPopup<void>(
            context: context,
            builder: (_) => UnblockConfirmSheet(user: user),
          );
        },
        errorMessageBuilder: AppAuthErrorFormatter.messageFrom,
      ),
    );
  }
}

// ------ Paywall ---------------------------------------------------------------

class PaywallScreen extends ConsumerStatefulWidget {
  const PaywallScreen({super.key});

  @override
  ConsumerState<PaywallScreen> createState() => _PaywallScreenState();
}

class _PaywallScreenState extends ConsumerState<PaywallScreen> {
  int _selected = 1;
  final StorePurchaseService _purchaseService = StorePurchaseService();
  bool _isPurchasing = false;

  Future<void> _purchaseSubscription(
    AppSubscriptionPackage selectedPackage,
  ) async {
    final authState = ref.read(appAuthProvider).asData?.value;
    final token = authState?.token;
    final l10n = AppLocalizations.of(context)!;
    final productLabel =
        'Premium ${subscriptionPlanTitle(selectedPackage, l10n)}';

    if (token == null || token.trim().isEmpty) {
      openPremiumPurchaseResultScreen(
        context,
        copy: premiumPurchaseAuthRequiredCopy(l10n),
        productLabel: productLabel,
        amountLabel: selectedPackage.displayPrice,
        l10n: l10n,
      );
      return;
    }

    setState(() => _isPurchasing = true);
    final result = await _purchaseService.purchase(
      token: token,
      productCode: selectedPackage.storeProductCode ?? '',
      kind: StorePurchaseKind.subscription,
      amount: selectedPackage.price,
      currency: selectedPackage.currency,
    );
    if (!mounted) {
      return;
    }

    setState(() => _isPurchasing = false);

    if (result.isSuccess) {
      await ref.read(appAuthProvider.notifier).refreshCurrentUser();
      if (!mounted) {
        return;
      }
      openPremiumPurchaseResultScreen(
        context,
        copy: premiumPurchaseSuccessCopy(l10n),
        productLabel: productLabel,
        amountLabel: selectedPackage.displayPrice,
        l10n: l10n,
      );
      return;
    }

    if (result.status == StorePurchaseStatus.cancelled) {
      return;
    }

    openPremiumPurchaseResultScreen(
      context,
      copy: premiumPurchaseFailureCopy(l10n, message: result.message),
      productLabel: productLabel,
      amountLabel: selectedPackage.displayPrice,
      l10n: l10n,
    );
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final packagesAsync = ref.watch(appSubscriptionPackagesProvider);
    final packages =
        packagesAsync.asData?.value ?? const <AppSubscriptionPackage>[];
    final selectedIndex = resolveRecommendedSelectionIndex(
      packages,
      _selected,
      isRecommended: (package) => package.isRecommended,
    );
    final selectedPackage = packages.isEmpty ? null : packages[selectedIndex];

    return ProfilePaywallScreenView(
      onClose: () => Navigator.of(context).maybePop(),
      heroBadge: l10n.paywallBadgePremium,
      heroTitle: l10n.paywallHeroTitle,
      heroSubtitle: l10n.paywallHeroSubtitle,
      voiceFeatureLabel: l10n.paywallFeatureVoice,
      videoFeatureLabel: l10n.paywallFeatureVideo,
      boostFeatureLabel: l10n.paywallFeatureBoost,
      plansTitle: l10n.paywallPlansTitle,
      isLoading: packagesAsync.isLoading && packages.isEmpty,
      hasError: packagesAsync.hasError && packages.isEmpty,
      isEmpty: packages.isEmpty,
      loadingErrorMessage: 'Premium planlari su anda yuklenemiyor.',
      emptyMessage: 'Su anda aktif premium plani bulunmuyor.',
      packages: packages,
      selectedIndex: selectedIndex,
      onSelectPackage: (index) => setState(() => _selected = index),
      ctaLabel: _isPurchasing
          ? 'Satin alma isleniyor...'
          : selectedPackage == null
          ? 'Plan bulunamadi'
          : l10n.paywallContinueWith(selectedPackage.displayPrice),
      onCtaTap: _isPurchasing || selectedPackage == null
          ? null
          : () => _purchaseSubscription(selectedPackage),
    );
  }
}

// ------ Jeton purchase sheet --------------------------------------------------

class JetonPurchaseSheet extends ConsumerStatefulWidget {
  const JetonPurchaseSheet({super.key});

  @override
  ConsumerState<JetonPurchaseSheet> createState() => _JetonPurchaseSheetState();
}

class _JetonPurchaseSheetState extends ConsumerState<JetonPurchaseSheet> {
  int _selected = 1;
  final StorePurchaseService _purchaseService = StorePurchaseService();
  bool _isPurchasing = false;

  Future<void> _purchaseCredits(AppCreditPackage selectedPackage) async {
    final authState = ref.read(appAuthProvider).asData?.value;
    final token = authState?.token;
    final l10n = AppLocalizations.of(context)!;

    if (token == null || token.trim().isEmpty) {
      showJetonPurchaseResultSheet(
        context,
        copy: jetonPurchaseAuthRequiredCopy(l10n),
        selectedPackage: selectedPackage,
        l10n: l10n,
      );
      return;
    }

    setState(() => _isPurchasing = true);
    final result = await _purchaseService.purchase(
      token: token,
      productCode: selectedPackage.storeProductCode ?? '',
      kind: StorePurchaseKind.creditPack,
      amount: selectedPackage.price,
      currency: selectedPackage.currency,
    );
    if (!mounted) {
      return;
    }

    setState(() => _isPurchasing = false);

    if (result.isSuccess) {
      await ref.read(appAuthProvider.notifier).refreshCurrentUser();
      if (!mounted) {
        return;
      }
      Navigator.of(context, rootNavigator: true).pop();
      showJetonPurchaseResultSheet(
        context,
        copy: jetonPurchaseSuccessCopy(l10n),
        selectedPackage: selectedPackage,
        l10n: l10n,
      );
      return;
    }

    if (result.status == StorePurchaseStatus.cancelled) {
      return;
    }

    showJetonPurchaseResultSheet(
      context,
      copy: jetonPurchaseFailureCopy(l10n, message: result.message),
      selectedPackage: selectedPackage,
      l10n: l10n,
    );
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final packagesAsync = ref.watch(appCreditPackagesProvider);
    final packages = packagesAsync.asData?.value ?? const <AppCreditPackage>[];
    final selectedIndex = resolveRecommendedSelectionIndex(
      packages,
      _selected,
      isRecommended: (package) => package.isRecommended,
    );
    final selectedPackage = packages.isEmpty ? null : packages[selectedIndex];

    return ProfileJetonPurchaseSheetView(
      title: l10n.jetonOfferTitle,
      subtitle: l10n.jetonOfferSubtitle,
      packagesAsync: packagesAsync,
      packages: packages,
      selectedIndex: selectedIndex,
      onSelectPackage: (index) => setState(() => _selected = index),
      loadingErrorMessage: 'Kredi paketleri su anda yuklenemiyor.',
      emptyMessage: 'Su anda satin alinabilir kredi paketi bulunmuyor.',
      primaryActionLabel: _isPurchasing
          ? 'Satin alma isleniyor...'
          : selectedPackage == null
          ? 'Paket bulunamadi'
          : l10n.jetonBuyWith(selectedPackage.displayPrice),
      onPrimaryAction: _isPurchasing || selectedPackage == null
          ? null
          : () => _purchaseCredits(selectedPackage),
      infoText: l10n.jetonInstantCreditInfo,
    );
  }
}
