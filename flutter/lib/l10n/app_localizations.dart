import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:intl/intl.dart' as intl;

import 'app_localizations_de.dart';
import 'app_localizations_en.dart';
import 'app_localizations_fr.dart';
import 'app_localizations_tr.dart';

// ignore_for_file: type=lint

/// Callers can lookup localized strings with an instance of AppLocalizations
/// returned by `AppLocalizations.of(context)`.
///
/// Applications need to include `AppLocalizations.delegate()` in their app's
/// `localizationDelegates` list, and the locales they support in the app's
/// `supportedLocales` list. For example:
///
/// ```dart
/// import 'l10n/app_localizations.dart';
///
/// return MaterialApp(
///   localizationsDelegates: AppLocalizations.localizationsDelegates,
///   supportedLocales: AppLocalizations.supportedLocales,
///   home: MyApplicationHome(),
/// );
/// ```
///
/// ## Update pubspec.yaml
///
/// Please make sure to update your pubspec.yaml to include the following
/// packages:
///
/// ```yaml
/// dependencies:
///   # Internationalization support.
///   flutter_localizations:
///     sdk: flutter
///   intl: any # Use the pinned version from flutter_localizations
///
///   # Rest of dependencies
/// ```
///
/// ## iOS Applications
///
/// iOS applications define key application metadata, including supported
/// locales, in an Info.plist file that is built into the application bundle.
/// To configure the locales supported by your app, you’ll need to edit this
/// file.
///
/// First, open your project’s ios/Runner.xcworkspace Xcode workspace file.
/// Then, in the Project Navigator, open the Info.plist file under the Runner
/// project’s Runner folder.
///
/// Next, select the Information Property List item, select Add Item from the
/// Editor menu, then select Localizations from the pop-up menu.
///
/// Select and expand the newly-created Localizations item then, for each
/// locale your application supports, add a new item and select the locale
/// you wish to add from the pop-up menu in the Value field. This list should
/// be consistent with the languages listed in the AppLocalizations.supportedLocales
/// property.
abstract class AppLocalizations {
  AppLocalizations(String locale)
    : localeName = intl.Intl.canonicalizedLocale(locale.toString());

  final String localeName;

  static AppLocalizations? of(BuildContext context) {
    return Localizations.of<AppLocalizations>(context, AppLocalizations);
  }

  static const LocalizationsDelegate<AppLocalizations> delegate =
      _AppLocalizationsDelegate();

  /// A list of this localizations delegate along with the default localizations
  /// delegates.
  ///
  /// Returns a list of localizations delegates containing this delegate along with
  /// GlobalMaterialLocalizations.delegate, GlobalCupertinoLocalizations.delegate,
  /// and GlobalWidgetsLocalizations.delegate.
  ///
  /// Additional delegates can be added by appending to this list in
  /// MaterialApp. This list does not have to be used at all if a custom list
  /// of delegates is preferred or required.
  static const List<LocalizationsDelegate<dynamic>> localizationsDelegates =
      <LocalizationsDelegate<dynamic>>[
        delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
      ];

  /// A list of this localizations delegate's supported locales.
  static const List<Locale> supportedLocales = <Locale>[
    Locale('de'),
    Locale('en'),
    Locale('fr'),
    Locale('tr'),
  ];

  /// No description provided for @commonDone.
  ///
  /// In tr, this message translates to:
  /// **'Tamam'**
  String get commonDone;

  /// No description provided for @commonCancel.
  ///
  /// In tr, this message translates to:
  /// **'Vazgec'**
  String get commonCancel;

  /// No description provided for @commonContinue.
  ///
  /// In tr, this message translates to:
  /// **'Devam Et'**
  String get commonContinue;

  /// No description provided for @commonClose.
  ///
  /// In tr, this message translates to:
  /// **'Kapat'**
  String get commonClose;

  /// No description provided for @commonOk.
  ///
  /// In tr, this message translates to:
  /// **'Tamam'**
  String get commonOk;

  /// No description provided for @commonLoading.
  ///
  /// In tr, this message translates to:
  /// **'Yukleniyor'**
  String get commonLoading;

  /// No description provided for @commonRefresh.
  ///
  /// In tr, this message translates to:
  /// **'Yenile'**
  String get commonRefresh;

  /// No description provided for @profileGemBalance.
  ///
  /// In tr, this message translates to:
  /// **'Tas Bakiyesi'**
  String get profileGemBalance;

  /// No description provided for @profileContactUs.
  ///
  /// In tr, this message translates to:
  /// **'Bize Ulasin'**
  String get profileContactUs;

  /// No description provided for @profileNotifications.
  ///
  /// In tr, this message translates to:
  /// **'Bildirimler'**
  String get profileNotifications;

  /// No description provided for @profileLanguage.
  ///
  /// In tr, this message translates to:
  /// **'Dil'**
  String get profileLanguage;

  /// No description provided for @profileBlockedUsers.
  ///
  /// In tr, this message translates to:
  /// **'Engellenen Kullanicilar'**
  String get profileBlockedUsers;

  /// No description provided for @profileHelp.
  ///
  /// In tr, this message translates to:
  /// **'Yardim'**
  String get profileHelp;

  /// No description provided for @profileRestorePurchases.
  ///
  /// In tr, this message translates to:
  /// **'Satin Alimlari Geri Yukle'**
  String get profileRestorePurchases;

  /// No description provided for @profilePrivacyPolicy.
  ///
  /// In tr, this message translates to:
  /// **'Gizlilik Politikasi'**
  String get profilePrivacyPolicy;

  /// No description provided for @profileKvkk.
  ///
  /// In tr, this message translates to:
  /// **'KVKK Aydinlatma Metni'**
  String get profileKvkk;

  /// No description provided for @profileTerms.
  ///
  /// In tr, this message translates to:
  /// **'Kullanim Kosullari'**
  String get profileTerms;

  /// No description provided for @profileDeleteAccount.
  ///
  /// In tr, this message translates to:
  /// **'Hesabi Sil'**
  String get profileDeleteAccount;

  /// No description provided for @profileSignOut.
  ///
  /// In tr, this message translates to:
  /// **'Cikis Yap'**
  String get profileSignOut;

  /// No description provided for @profilePhotoTitle.
  ///
  /// In tr, this message translates to:
  /// **'Profil Fotografi'**
  String get profilePhotoTitle;

  /// No description provided for @profilePhotoSubtitle.
  ///
  /// In tr, this message translates to:
  /// **'Avatarini ve profil galerini buradan hizlica guncelleyebilirsin.'**
  String get profilePhotoSubtitle;

  /// No description provided for @profileNoActivePhotos.
  ///
  /// In tr, this message translates to:
  /// **'Henuz aktif fotograf yok'**
  String get profileNoActivePhotos;

  /// No description provided for @profileActivePhotosCount.
  ///
  /// In tr, this message translates to:
  /// **'{count} aktif fotograf'**
  String profileActivePhotosCount(int count);

  /// No description provided for @profileVideoCount.
  ///
  /// In tr, this message translates to:
  /// **'{count} video da profil galerinde listeleniyor.'**
  String profileVideoCount(int count);

  /// No description provided for @profilePrimaryHint.
  ///
  /// In tr, this message translates to:
  /// **'Ilk fotograf ana gorunum olarak kullanilir. Galeriden mevcut bir fotografi tekrar ana fotograf yapabilirsin.'**
  String get profilePrimaryHint;

  /// No description provided for @profileTakePhoto.
  ///
  /// In tr, this message translates to:
  /// **'Fotograf cek'**
  String get profileTakePhoto;

  /// No description provided for @profilePickPhoto.
  ///
  /// In tr, this message translates to:
  /// **'Fotograf sec'**
  String get profilePickPhoto;

  /// No description provided for @profilePickVideo.
  ///
  /// In tr, this message translates to:
  /// **'Video sec'**
  String get profilePickVideo;

  /// No description provided for @profileGalleryTitle.
  ///
  /// In tr, this message translates to:
  /// **'Galerin'**
  String get profileGalleryTitle;

  /// No description provided for @deleteAccountStartTitle.
  ///
  /// In tr, this message translates to:
  /// **'Hesabi silme islemini baslat'**
  String get deleteAccountStartTitle;

  /// No description provided for @deleteAccountStartSubtitle.
  ///
  /// In tr, this message translates to:
  /// **'Bu adimdan sonra son bir onay daha isteyecegiz. Devam etmeden once kredi bakiyen ve sohbetlerin dahil tum hesabinin silinecegini bilmelisin.'**
  String get deleteAccountStartSubtitle;

  /// No description provided for @deleteAccountFinalTitle.
  ///
  /// In tr, this message translates to:
  /// **'Hesabinizi kalici olarak silmek istiyor musunuz?'**
  String get deleteAccountFinalTitle;

  /// No description provided for @deleteAccountFinalSubtitle.
  ///
  /// In tr, this message translates to:
  /// **'Bu islem geri alinamaz. Tum verileriniz kalici olarak silinecektir.'**
  String get deleteAccountFinalSubtitle;

  /// No description provided for @deleteAccountDeleting.
  ///
  /// In tr, this message translates to:
  /// **'Hesap siliniyor...'**
  String get deleteAccountDeleting;

  /// No description provided for @languageSheetTitle.
  ///
  /// In tr, this message translates to:
  /// **'Dil Secimi'**
  String get languageSheetTitle;

  /// No description provided for @languageChange.
  ///
  /// In tr, this message translates to:
  /// **'Degistir'**
  String get languageChange;

  /// No description provided for @languageChanging.
  ///
  /// In tr, this message translates to:
  /// **'Kaydediliyor...'**
  String get languageChanging;

  /// No description provided for @languageUpdateFailedTitle.
  ///
  /// In tr, this message translates to:
  /// **'Dil guncellenemedi'**
  String get languageUpdateFailedTitle;

  /// No description provided for @profileMediaAll.
  ///
  /// In tr, this message translates to:
  /// **'Tumu'**
  String get profileMediaAll;

  /// No description provided for @profileMediaPhotos.
  ///
  /// In tr, this message translates to:
  /// **'Fotograflar'**
  String get profileMediaPhotos;

  /// No description provided for @profileMediaVideos.
  ///
  /// In tr, this message translates to:
  /// **'Videolar'**
  String get profileMediaVideos;

  /// No description provided for @profileNoUploadedMedia.
  ///
  /// In tr, this message translates to:
  /// **'Henuz yuklenmis medya yok.'**
  String get profileNoUploadedMedia;

  /// No description provided for @profileNoUploadedPhotos.
  ///
  /// In tr, this message translates to:
  /// **'Henuz yuklenmis fotograf yok.'**
  String get profileNoUploadedPhotos;

  /// No description provided for @profileNoUploadedVideos.
  ///
  /// In tr, this message translates to:
  /// **'Henuz yuklenmis video yok.'**
  String get profileNoUploadedVideos;

  /// No description provided for @profileCameraPhotoAction.
  ///
  /// In tr, this message translates to:
  /// **'Kameradan fotograf cek'**
  String get profileCameraPhotoAction;

  /// No description provided for @profileGalleryPhotoAction.
  ///
  /// In tr, this message translates to:
  /// **'Galeriden fotograf sec'**
  String get profileGalleryPhotoAction;

  /// No description provided for @profileGalleryVideoAction.
  ///
  /// In tr, this message translates to:
  /// **'Galeriden video sec'**
  String get profileGalleryVideoAction;

  /// No description provided for @profileMediaActionTitle.
  ///
  /// In tr, this message translates to:
  /// **'Medya islemleri'**
  String get profileMediaActionTitle;

  /// No description provided for @profileMakePrimary.
  ///
  /// In tr, this message translates to:
  /// **'Profil fotografi yap'**
  String get profileMakePrimary;

  /// No description provided for @profileDeleteMedia.
  ///
  /// In tr, this message translates to:
  /// **'Medyayi sil'**
  String get profileDeleteMedia;

  /// No description provided for @profileScreenTitle.
  ///
  /// In tr, this message translates to:
  /// **'Profilim'**
  String get profileScreenTitle;

  /// No description provided for @profileFallbackDisplayName.
  ///
  /// In tr, this message translates to:
  /// **'Profilin'**
  String get profileFallbackDisplayName;

  /// No description provided for @profileFallbackUsername.
  ///
  /// In tr, this message translates to:
  /// **'kullanici'**
  String get profileFallbackUsername;

  /// No description provided for @profileChangePhoto.
  ///
  /// In tr, this message translates to:
  /// **'Fotografi Degistir'**
  String get profileChangePhoto;

  /// No description provided for @profileEditProfile.
  ///
  /// In tr, this message translates to:
  /// **'Profili Duzenle'**
  String get profileEditProfile;

  /// No description provided for @profilePromoTitle.
  ///
  /// In tr, this message translates to:
  /// **'Sesli arama ve\npremium gorusmeleri ac'**
  String get profilePromoTitle;

  /// No description provided for @profilePromoAction.
  ///
  /// In tr, this message translates to:
  /// **'Yukselt'**
  String get profilePromoAction;

  /// No description provided for @profileMediaManagementTitle.
  ///
  /// In tr, this message translates to:
  /// **'Medya Yonetimi'**
  String get profileMediaManagementTitle;

  /// No description provided for @profileMediaManagementSubtitle.
  ///
  /// In tr, this message translates to:
  /// **'Fotograflarini yenile, yukle ve profil gorunumunu kontrol et.'**
  String get profileMediaManagementSubtitle;

  /// No description provided for @profileManage.
  ///
  /// In tr, this message translates to:
  /// **'Yonet'**
  String get profileManage;

  /// No description provided for @profilePhotoDraftAdded.
  ///
  /// In tr, this message translates to:
  /// **'Fotograf profil taslagina eklendi.'**
  String get profilePhotoDraftAdded;

  /// No description provided for @profilePhotoPickFailed.
  ///
  /// In tr, this message translates to:
  /// **'Fotograf secilirken izin veya cihaz hatasi olustu.'**
  String get profilePhotoPickFailed;

  /// No description provided for @profileVideoAuthRequired.
  ///
  /// In tr, this message translates to:
  /// **'Video yuklemek icin once giris yapmalisin.'**
  String get profileVideoAuthRequired;

  /// No description provided for @profileVideoPickFailed.
  ///
  /// In tr, this message translates to:
  /// **'Video secilirken izin veya cihaz hatasi olustu.'**
  String get profileVideoPickFailed;

  /// No description provided for @profileMediaAuthRequired.
  ///
  /// In tr, this message translates to:
  /// **'Medya yuklemek icin once giris yapmalisin.'**
  String get profileMediaAuthRequired;

  /// No description provided for @profileVideoAdded.
  ///
  /// In tr, this message translates to:
  /// **'Video profiline eklendi.'**
  String get profileVideoAdded;

  /// No description provided for @profilePhotoAdded.
  ///
  /// In tr, this message translates to:
  /// **'Fotograf profiline eklendi.'**
  String get profilePhotoAdded;

  /// No description provided for @profileActionAuthRequired.
  ///
  /// In tr, this message translates to:
  /// **'Bu islem icin once giris yapmalisin.'**
  String get profileActionAuthRequired;

  /// No description provided for @profilePhotoUpdated.
  ///
  /// In tr, this message translates to:
  /// **'Profil fotografi guncellendi.'**
  String get profilePhotoUpdated;

  /// No description provided for @profileVideoRemoved.
  ///
  /// In tr, this message translates to:
  /// **'Video galeriden kaldirildi.'**
  String get profileVideoRemoved;

  /// No description provided for @profilePhotoRemoved.
  ///
  /// In tr, this message translates to:
  /// **'Fotograf galeriden kaldirildi.'**
  String get profilePhotoRemoved;

  /// No description provided for @profilePhotoSourceTitle.
  ///
  /// In tr, this message translates to:
  /// **'Fotograf kaynagi'**
  String get profilePhotoSourceTitle;

  /// No description provided for @profileBadgeNew.
  ///
  /// In tr, this message translates to:
  /// **'Yeni'**
  String get profileBadgeNew;

  /// No description provided for @profileBadgePrimary.
  ///
  /// In tr, this message translates to:
  /// **'Ana'**
  String get profileBadgePrimary;

  /// No description provided for @profileBadgeVideo.
  ///
  /// In tr, this message translates to:
  /// **'Video'**
  String get profileBadgeVideo;

  /// No description provided for @commonSave.
  ///
  /// In tr, this message translates to:
  /// **'Kaydet'**
  String get commonSave;

  /// No description provided for @commonSaving.
  ///
  /// In tr, this message translates to:
  /// **'Kaydediliyor...'**
  String get commonSaving;

  /// No description provided for @commonRetry.
  ///
  /// In tr, this message translates to:
  /// **'Tekrar Dene'**
  String get commonRetry;

  /// No description provided for @restorePurchasesConnectionFailed.
  ///
  /// In tr, this message translates to:
  /// **'Magaza baglantisindan yanit alinamadi.'**
  String get restorePurchasesConnectionFailed;

  /// No description provided for @restorePurchasesUnavailable.
  ///
  /// In tr, this message translates to:
  /// **'Bu cihazda magaza baglantisi kullanilamiyor.'**
  String get restorePurchasesUnavailable;

  /// No description provided for @restorePurchasesStoreRequired.
  ///
  /// In tr, this message translates to:
  /// **'Geri yukleme icin App Store veya Google Play baglantisi gerekli.'**
  String get restorePurchasesStoreRequired;

  /// No description provided for @restorePurchasesRestoredCount.
  ///
  /// In tr, this message translates to:
  /// **'{count} satin alim tekrar eslestirildi.'**
  String restorePurchasesRestoredCount(int count);

  /// No description provided for @restorePurchasesNotFound.
  ///
  /// In tr, this message translates to:
  /// **'Eslesen satin alim bulunamadi. Farkli magaza hesabi kullandiysan destek ekibiyle iletisime gecebilirsin.'**
  String get restorePurchasesNotFound;

  /// No description provided for @restorePurchasesPreparing.
  ///
  /// In tr, this message translates to:
  /// **'Magaza geri yukleme islemini hazirliyor.'**
  String get restorePurchasesPreparing;

  /// No description provided for @restorePurchasesResponseUnreadable.
  ///
  /// In tr, this message translates to:
  /// **'Magaza yaniti okunamadi.'**
  String get restorePurchasesResponseUnreadable;

  /// No description provided for @restorePurchasesCancelled.
  ///
  /// In tr, this message translates to:
  /// **'Geri yukleme magaza tarafinda iptal edildi.'**
  String get restorePurchasesCancelled;

  /// No description provided for @restorePurchasesSubtitle.
  ///
  /// In tr, this message translates to:
  /// **'App Store veya Google Play hesabinla daha once yapilan satin alimlari tekrar eslestirmeyi dener.'**
  String get restorePurchasesSubtitle;

  /// No description provided for @restorePurchasesStep1Title.
  ///
  /// In tr, this message translates to:
  /// **'Ayni magaza hesabini kullan'**
  String get restorePurchasesStep1Title;

  /// No description provided for @restorePurchasesStep1Description.
  ///
  /// In tr, this message translates to:
  /// **'Satin alim yaptigin hesap bu cihazda acik olmali.'**
  String get restorePurchasesStep1Description;

  /// No description provided for @restorePurchasesStep2Title.
  ///
  /// In tr, this message translates to:
  /// **'Magaza kaydini tara'**
  String get restorePurchasesStep2Title;

  /// No description provided for @restorePurchasesStep2Description.
  ///
  /// In tr, this message translates to:
  /// **'Geri yukleme komutu dogrudan mobil magazaya gonderilir.'**
  String get restorePurchasesStep2Description;

  /// No description provided for @restorePurchasesStep3Title.
  ///
  /// In tr, this message translates to:
  /// **'Sorun varsa destek ile ilerle'**
  String get restorePurchasesStep3Title;

  /// No description provided for @restorePurchasesStep3Description.
  ///
  /// In tr, this message translates to:
  /// **'Eslesen urun bulunmazsa bize ulas ekranindan talep acabilirsin.'**
  String get restorePurchasesStep3Description;

  /// No description provided for @restorePurchasesChecking.
  ///
  /// In tr, this message translates to:
  /// **'Magaza Kontrol Ediliyor...'**
  String get restorePurchasesChecking;

  /// No description provided for @restorePurchasesProcessing.
  ///
  /// In tr, this message translates to:
  /// **'Geri Yukleniyor...'**
  String get restorePurchasesProcessing;

  /// No description provided for @restorePurchasesAction.
  ///
  /// In tr, this message translates to:
  /// **'Magazadan Geri Yukle'**
  String get restorePurchasesAction;

  /// No description provided for @signOutConfirmTitle.
  ///
  /// In tr, this message translates to:
  /// **'Cikis yapmak istiyor musunuz?'**
  String get signOutConfirmTitle;

  /// No description provided for @signOutConfirmSubtitle.
  ///
  /// In tr, this message translates to:
  /// **'Tekrar giris yapmak icin Google veya Apple hesabinizi kullanabilirsiniz.'**
  String get signOutConfirmSubtitle;

  /// No description provided for @signOutProcessing.
  ///
  /// In tr, this message translates to:
  /// **'Cikis yapiliyor...'**
  String get signOutProcessing;

  /// No description provided for @unblockConfirmTitle.
  ///
  /// In tr, this message translates to:
  /// **'{name} engelini kaldirmak istiyor musunuz?'**
  String unblockConfirmTitle(String name);

  /// No description provided for @unblockConfirmSubtitle.
  ///
  /// In tr, this message translates to:
  /// **'Bu kisi tekrar size mesaj gonderebilecek.'**
  String get unblockConfirmSubtitle;

  /// No description provided for @unblockProcessing.
  ///
  /// In tr, this message translates to:
  /// **'Kaldiriliyor...'**
  String get unblockProcessing;

  /// No description provided for @unblockAction.
  ///
  /// In tr, this message translates to:
  /// **'Engeli Kaldir'**
  String get unblockAction;

  /// No description provided for @editProfileFirstName.
  ///
  /// In tr, this message translates to:
  /// **'Isim'**
  String get editProfileFirstName;

  /// No description provided for @editProfileSurname.
  ///
  /// In tr, this message translates to:
  /// **'Soyisim'**
  String get editProfileSurname;

  /// No description provided for @editProfileUsername.
  ///
  /// In tr, this message translates to:
  /// **'Kullanici Adi'**
  String get editProfileUsername;

  /// No description provided for @editProfileUsernameUnsupported.
  ///
  /// In tr, this message translates to:
  /// **'Kullanici adi degisikligi henuz mobil uygulamadan desteklenmiyor.'**
  String get editProfileUsernameUnsupported;

  /// No description provided for @editProfileBio.
  ///
  /// In tr, this message translates to:
  /// **'Biyografi'**
  String get editProfileBio;

  /// No description provided for @editProfileBioPlaceholder.
  ///
  /// In tr, this message translates to:
  /// **'Kendinden bahset...'**
  String get editProfileBioPlaceholder;

  /// No description provided for @notificationsDescription.
  ///
  /// In tr, this message translates to:
  /// **'Yeni mesaj ve eslesme bildirimlerini ac veya kapat'**
  String get notificationsDescription;

  /// No description provided for @notificationsVibration.
  ///
  /// In tr, this message translates to:
  /// **'Titresim'**
  String get notificationsVibration;

  /// No description provided for @notificationsVibrationDescription.
  ///
  /// In tr, this message translates to:
  /// **'Bildirim geldiginde titresim kullan'**
  String get notificationsVibrationDescription;

  /// No description provided for @saveFailedTitle.
  ///
  /// In tr, this message translates to:
  /// **'Kaydedilemedi'**
  String get saveFailedTitle;

  /// No description provided for @helpWriteMessageFirst.
  ///
  /// In tr, this message translates to:
  /// **'Lutfen once mesajinizi yazin.'**
  String get helpWriteMessageFirst;

  /// No description provided for @helpAuthRequired.
  ///
  /// In tr, this message translates to:
  /// **'Destek mesaji gondermek icin once giris yapmalisin.'**
  String get helpAuthRequired;

  /// No description provided for @helpMessageReceivedTitle.
  ///
  /// In tr, this message translates to:
  /// **'Mesaj alindi'**
  String get helpMessageReceivedTitle;

  /// No description provided for @helpMessageReceivedSubtitle.
  ///
  /// In tr, this message translates to:
  /// **'Destek ekibi mesajinizi inceleyecek.'**
  String get helpMessageReceivedSubtitle;

  /// No description provided for @helpWhatsAppUnavailable.
  ///
  /// In tr, this message translates to:
  /// **'WhatsApp destek hatti henuz tanimlanmamis.'**
  String get helpWhatsAppUnavailable;

  /// No description provided for @helpWhatsAppLaunchFailed.
  ///
  /// In tr, this message translates to:
  /// **'WhatsApp baglantisi acilamadi.'**
  String get helpWhatsAppLaunchFailed;

  /// No description provided for @helpExternalLaunchFailed.
  ///
  /// In tr, this message translates to:
  /// **'Harici baglanti baslatilamadi.'**
  String get helpExternalLaunchFailed;

  /// No description provided for @helpFaqTitle.
  ///
  /// In tr, this message translates to:
  /// **'Sikca Sorulan Sorular'**
  String get helpFaqTitle;

  /// No description provided for @helpFaqQuestion1.
  ///
  /// In tr, this message translates to:
  /// **'Tas nedir, nasil kullanilir?'**
  String get helpFaqQuestion1;

  /// No description provided for @helpFaqAnswer1.
  ///
  /// In tr, this message translates to:
  /// **'Tas, uygulama ici sanal para birimidir. Ozel emoji gondermek ve ek ozellikler icin kullanilir.'**
  String get helpFaqAnswer1;

  /// No description provided for @helpFaqQuestion2.
  ///
  /// In tr, this message translates to:
  /// **'Eslesme nasil calisir?'**
  String get helpFaqQuestion2;

  /// No description provided for @helpFaqAnswer2.
  ///
  /// In tr, this message translates to:
  /// **'Kesfet bolumunden esles butonuna basarak rastgele birisiyle eslesebilirsiniz. Iki taraf da kabul ederse mesajlasma baslar.'**
  String get helpFaqAnswer2;

  /// No description provided for @helpFaqQuestion3.
  ///
  /// In tr, this message translates to:
  /// **'Premium ne saglar?'**
  String get helpFaqQuestion3;

  /// No description provided for @helpFaqAnswer3.
  ///
  /// In tr, this message translates to:
  /// **'Sinirsiz mesaj, seni kimin begendigini gorme, sesli arama ve haftalik boost gibi ozellikler sunar.'**
  String get helpFaqAnswer3;

  /// No description provided for @helpWriteUsTitle.
  ///
  /// In tr, this message translates to:
  /// **'Bize Yazin'**
  String get helpWriteUsTitle;

  /// No description provided for @helpMessagePlaceholder.
  ///
  /// In tr, this message translates to:
  /// **'Mesajinizi yazin...'**
  String get helpMessagePlaceholder;

  /// No description provided for @helpSending.
  ///
  /// In tr, this message translates to:
  /// **'Gonderiliyor...'**
  String get helpSending;

  /// No description provided for @helpSend.
  ///
  /// In tr, this message translates to:
  /// **'Gonder'**
  String get helpSend;

  /// No description provided for @helpWhatsAppContact.
  ///
  /// In tr, this message translates to:
  /// **'WhatsApp ile Ulasin'**
  String get helpWhatsAppContact;

  /// No description provided for @helpWhatsAppComingSoon.
  ///
  /// In tr, this message translates to:
  /// **'WhatsApp destek hatti yakinda eklenecek'**
  String get helpWhatsAppComingSoon;

  /// No description provided for @blockedUsersEmpty.
  ///
  /// In tr, this message translates to:
  /// **'Henuz engelledigin bir kullanici yok.'**
  String get blockedUsersEmpty;

  /// No description provided for @privacyTitle.
  ///
  /// In tr, this message translates to:
  /// **'Gizlilik Politikasi'**
  String get privacyTitle;

  /// No description provided for @privacyHeading1.
  ///
  /// In tr, this message translates to:
  /// **'Veri Toplama'**
  String get privacyHeading1;

  /// No description provided for @privacyBody1.
  ///
  /// In tr, this message translates to:
  /// **'magmug uygulamasi, kullanicilarinin deneyimini iyilestirmek amaciyla belirli kisisel verileri toplar. Bu veriler arasinda ad, soyad, e-posta adresi, konum bilgisi, profil fotograflari ve uygulama ici etkilesim verileri yer alir.'**
  String get privacyBody1;

  /// No description provided for @privacyHeading2.
  ///
  /// In tr, this message translates to:
  /// **'Verilerin Kullanimi'**
  String get privacyHeading2;

  /// No description provided for @privacyBody2.
  ///
  /// In tr, this message translates to:
  /// **'Toplanan veriler, size daha iyi eslesmeler sunmak, uygulama deneyimini kisisellestirmek ve guvenliginizi saglamak amaciyla kullanilir. Verileriniz ucuncu taraflarla paylasilmaz.'**
  String get privacyBody2;

  /// No description provided for @privacyHeading3.
  ///
  /// In tr, this message translates to:
  /// **'Veri Guvenligi'**
  String get privacyHeading3;

  /// No description provided for @privacyBody3.
  ///
  /// In tr, this message translates to:
  /// **'Tum kisisel verileriniz 256-bit SSL sifreleme ile korunmaktadir. Sunucularimiz guvenli veri merkezlerinde barindirilmakta ve duzenli olarak denetlenmektedir.'**
  String get privacyBody3;

  /// No description provided for @privacyHeading4.
  ///
  /// In tr, this message translates to:
  /// **'Cerezler'**
  String get privacyHeading4;

  /// No description provided for @privacyBody4.
  ///
  /// In tr, this message translates to:
  /// **'Uygulamamiz, kullanici deneyimini iyilestirmek icin cerezler ve benzer teknolojiler kullanmaktadir. Bu cerezler, oturum yonetimi ve tercihlerinizin hatirlanmasi amaciyla kullanilir.'**
  String get privacyBody4;

  /// No description provided for @privacyHeading5.
  ///
  /// In tr, this message translates to:
  /// **'Iletisim'**
  String get privacyHeading5;

  /// No description provided for @privacyBody5.
  ///
  /// In tr, this message translates to:
  /// **'Gizlilik politikamizla ilgili sorulariniz icin destek sayfamiz uzerinden bize ulasabilirsiniz.'**
  String get privacyBody5;

  /// No description provided for @termsTitle.
  ///
  /// In tr, this message translates to:
  /// **'Kullanim Kosullari'**
  String get termsTitle;

  /// No description provided for @termsHeading1.
  ///
  /// In tr, this message translates to:
  /// **'Hizmet Tanimi'**
  String get termsHeading1;

  /// No description provided for @termsBody1.
  ///
  /// In tr, this message translates to:
  /// **'magmug, kullanicilarin birbirleriyle mesajlasma ve eslesme yoluyla tanismalarini saglayan bir sosyal platformdur.'**
  String get termsBody1;

  /// No description provided for @termsHeading2.
  ///
  /// In tr, this message translates to:
  /// **'Kullanici Sorumluluklari'**
  String get termsHeading2;

  /// No description provided for @termsBody2.
  ///
  /// In tr, this message translates to:
  /// **'Kullanicilar, dogru ve guncel bilgiler saglamakla yukumludur. Sahte profil olusturmak, taciz, kufur ve uygunsuz icerik paylasmak kesinlikle yasaktir.'**
  String get termsBody2;

  /// No description provided for @termsHeading3.
  ///
  /// In tr, this message translates to:
  /// **'Yas Siniri'**
  String get termsHeading3;

  /// No description provided for @termsBody3.
  ///
  /// In tr, this message translates to:
  /// **'magmug\'u kullanmak icin en az 18 yasinda olmaniz gerekmektedir. 18 yasindan kucuk kullanicilarin hesaplari tespit edildiginde kapatilacaktir.'**
  String get termsBody3;

  /// No description provided for @termsHeading4.
  ///
  /// In tr, this message translates to:
  /// **'Odeme ve Iadeler'**
  String get termsHeading4;

  /// No description provided for @termsBody4.
  ///
  /// In tr, this message translates to:
  /// **'Uygulama ici satin alimlar Apple App Store veya Google Play Store uzerinden gerceklestirilir. Iade talepleri ilgili magaza politikalarina tabidir.'**
  String get termsBody4;

  /// No description provided for @termsHeading5.
  ///
  /// In tr, this message translates to:
  /// **'Hesap Sonlandirma'**
  String get termsHeading5;

  /// No description provided for @termsBody5.
  ///
  /// In tr, this message translates to:
  /// **'magmug, kullanim kosullarini ihlal eden hesaplari onceden bildirim yapmaksizin askiya alma veya sonlandirma hakkini sakli tutar.'**
  String get termsBody5;

  /// No description provided for @paywallHeaderTitle.
  ///
  /// In tr, this message translates to:
  /// **'Premium'**
  String get paywallHeaderTitle;

  /// No description provided for @paywallBadgePremium.
  ///
  /// In tr, this message translates to:
  /// **'PREMIUM'**
  String get paywallBadgePremium;

  /// No description provided for @paywallHeroTitle.
  ///
  /// In tr, this message translates to:
  /// **'Mesajlasmanin otesine gec'**
  String get paywallHeroTitle;

  /// No description provided for @paywallHeroSubtitle.
  ///
  /// In tr, this message translates to:
  /// **'Sesli ve goruntulu gorusmeleri ac, daha hizli yakinlas ve premium deneyime gec.'**
  String get paywallHeroSubtitle;

  /// No description provided for @paywallFeatureVoice.
  ///
  /// In tr, this message translates to:
  /// **'Sesli arama'**
  String get paywallFeatureVoice;

  /// No description provided for @paywallFeatureVideo.
  ///
  /// In tr, this message translates to:
  /// **'Goruntulu gorusme'**
  String get paywallFeatureVideo;

  /// No description provided for @paywallFeatureBoost.
  ///
  /// In tr, this message translates to:
  /// **'One cik'**
  String get paywallFeatureBoost;

  /// No description provided for @paywallPlansTitle.
  ///
  /// In tr, this message translates to:
  /// **'Sana uygun premium plan'**
  String get paywallPlansTitle;

  /// No description provided for @paywallPlanWeek.
  ///
  /// In tr, this message translates to:
  /// **'1 Hafta'**
  String get paywallPlanWeek;

  /// No description provided for @paywallPlanMonth.
  ///
  /// In tr, this message translates to:
  /// **'1 Ay'**
  String get paywallPlanMonth;

  /// No description provided for @paywallPlanQuarter.
  ///
  /// In tr, this message translates to:
  /// **'3 Ay'**
  String get paywallPlanQuarter;

  /// No description provided for @paywallPeriodWeek.
  ///
  /// In tr, this message translates to:
  /// **'haftalik'**
  String get paywallPeriodWeek;

  /// No description provided for @paywallPeriodMonth.
  ///
  /// In tr, this message translates to:
  /// **'aylik'**
  String get paywallPeriodMonth;

  /// No description provided for @paywallPeriodQuarter.
  ///
  /// In tr, this message translates to:
  /// **'3 aylik'**
  String get paywallPeriodQuarter;

  /// No description provided for @paywallPopular.
  ///
  /// In tr, this message translates to:
  /// **'POPULER'**
  String get paywallPopular;

  /// No description provided for @paywallAdvantage.
  ///
  /// In tr, this message translates to:
  /// **'AVANTAJ'**
  String get paywallAdvantage;

  /// No description provided for @paywallContinueWith.
  ///
  /// In tr, this message translates to:
  /// **'{price} ile Devam Et'**
  String paywallContinueWith(String price);

  /// No description provided for @paywallPendingBadge.
  ///
  /// In tr, this message translates to:
  /// **'ODEME ISLENIYOR'**
  String get paywallPendingBadge;

  /// No description provided for @paywallPendingTitle.
  ///
  /// In tr, this message translates to:
  /// **'Premium onayi bekleniyor'**
  String get paywallPendingTitle;

  /// No description provided for @paywallPendingSubtitle.
  ///
  /// In tr, this message translates to:
  /// **'Magaza satin alma onayini tamamladiginda premium erisimin otomatik olarak acilacak.'**
  String get paywallPendingSubtitle;

  /// No description provided for @paywallPendingStatus.
  ///
  /// In tr, this message translates to:
  /// **'Magaza dogrulamasi bekleniyor'**
  String get paywallPendingStatus;

  /// No description provided for @paywallBackToPlans.
  ///
  /// In tr, this message translates to:
  /// **'Planlara Don'**
  String get paywallBackToPlans;

  /// No description provided for @paywallSaveLabel.
  ///
  /// In tr, this message translates to:
  /// **'{save} tasarruf'**
  String paywallSaveLabel(String save);

  /// No description provided for @paywallLegalPrefix.
  ///
  /// In tr, this message translates to:
  /// **'Detayli bilgi icin '**
  String get paywallLegalPrefix;

  /// No description provided for @paywallLegalAnd.
  ///
  /// In tr, this message translates to:
  /// **' ve '**
  String get paywallLegalAnd;

  /// No description provided for @jetonPaymentSuccessBadge.
  ///
  /// In tr, this message translates to:
  /// **'ODEME BASARILI'**
  String get jetonPaymentSuccessBadge;

  /// No description provided for @jetonPaymentSuccessTitle.
  ///
  /// In tr, this message translates to:
  /// **'Kredi paketin hazir'**
  String get jetonPaymentSuccessTitle;

  /// No description provided for @jetonPaymentSuccessSubtitle.
  ///
  /// In tr, this message translates to:
  /// **'Yeni kredi paketin hesabina eklendi. Sohbetlerine kaldigin yerden devam edebilirsin.'**
  String get jetonPaymentSuccessSubtitle;

  /// No description provided for @jetonCreditsAdded.
  ///
  /// In tr, this message translates to:
  /// **'Krediler hesaba eklendi'**
  String get jetonCreditsAdded;

  /// No description provided for @jetonAwesome.
  ///
  /// In tr, this message translates to:
  /// **'Harika'**
  String get jetonAwesome;

  /// No description provided for @jetonCreditsUnit.
  ///
  /// In tr, this message translates to:
  /// **'Kredi'**
  String get jetonCreditsUnit;

  /// No description provided for @jetonAmountLabel.
  ///
  /// In tr, this message translates to:
  /// **'{amount} Kredi'**
  String jetonAmountLabel(String amount);

  /// No description provided for @jetonOfferTitle.
  ///
  /// In tr, this message translates to:
  /// **'Iste size ozel oneri!'**
  String get jetonOfferTitle;

  /// No description provided for @jetonOfferSubtitle.
  ///
  /// In tr, this message translates to:
  /// **'Sohbetlerine devam etmek icin sana en uygun kredi paketini sec.'**
  String get jetonOfferSubtitle;

  /// No description provided for @jetonBuyWith.
  ///
  /// In tr, this message translates to:
  /// **'{price} ile Satin Al'**
  String jetonBuyWith(String price);

  /// No description provided for @jetonInstantCreditInfo.
  ///
  /// In tr, this message translates to:
  /// **'Odeme tamamlandiginda krediler hesabina aninda eklenir.'**
  String get jetonInstantCreditInfo;

  /// No description provided for @jetonMostPopular.
  ///
  /// In tr, this message translates to:
  /// **'EN POPULER'**
  String get jetonMostPopular;
}

class _AppLocalizationsDelegate
    extends LocalizationsDelegate<AppLocalizations> {
  const _AppLocalizationsDelegate();

  @override
  Future<AppLocalizations> load(Locale locale) {
    return SynchronousFuture<AppLocalizations>(lookupAppLocalizations(locale));
  }

  @override
  bool isSupported(Locale locale) =>
      <String>['de', 'en', 'fr', 'tr'].contains(locale.languageCode);

  @override
  bool shouldReload(_AppLocalizationsDelegate old) => false;
}

AppLocalizations lookupAppLocalizations(Locale locale) {
  // Lookup logic when only language code is specified.
  switch (locale.languageCode) {
    case 'de':
      return AppLocalizationsDe();
    case 'en':
      return AppLocalizationsEn();
    case 'fr':
      return AppLocalizationsFr();
    case 'tr':
      return AppLocalizationsTr();
  }

  throw FlutterError(
    'AppLocalizations.delegate failed to load unsupported locale "$locale". This is likely '
    'an issue with the localizations generation tool. Please file an issue '
    'on GitHub with a reproducible sample app and the gen-l10n configuration '
    'that was used.',
  );
}
