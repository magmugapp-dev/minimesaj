// ignore: unused_import
import 'package:intl/intl.dart' as intl;
import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for Turkish (`tr`).
class AppLocalizationsTr extends AppLocalizations {
  AppLocalizationsTr([String locale = 'tr']) : super(locale);

  @override
  String get commonDone => 'Tamam';

  @override
  String get commonCancel => 'Vazgec';

  @override
  String get commonContinue => 'Devam Et';

  @override
  String get commonClose => 'Kapat';

  @override
  String get commonOk => 'Tamam';

  @override
  String get commonLoading => 'Yukleniyor';

  @override
  String get commonRefresh => 'Yenile';

  @override
  String get profileGemBalance => 'Tas Bakiyesi';

  @override
  String get profileContactUs => 'Bize Ulasin';

  @override
  String get profileNotifications => 'Bildirimler';

  @override
  String get profileLanguage => 'Dil';

  @override
  String get profileBlockedUsers => 'Engellenen Kullanicilar';

  @override
  String get profileHelp => 'Yardim';

  @override
  String get profileRestorePurchases => 'Satin Alimlari Geri Yukle';

  @override
  String get profilePrivacyPolicy => 'Gizlilik Politikasi';

  @override
  String get profileKvkk => 'KVKK Aydinlatma Metni';

  @override
  String get profileTerms => 'Kullanim Kosullari';

  @override
  String get profileDeleteAccount => 'Hesabi Sil';

  @override
  String get profileSignOut => 'Cikis Yap';

  @override
  String get profilePhotoTitle => 'Profil Fotografi';

  @override
  String get profilePhotoSubtitle =>
      'Avatarini ve profil galerini buradan hizlica guncelleyebilirsin.';

  @override
  String get profileNoActivePhotos => 'Henuz aktif fotograf yok';

  @override
  String profileActivePhotosCount(int count) {
    return '$count aktif fotograf';
  }

  @override
  String profileVideoCount(int count) {
    return '$count video da profil galerinde listeleniyor.';
  }

  @override
  String get profilePrimaryHint =>
      'Ilk fotograf ana gorunum olarak kullanilir. Galeriden mevcut bir fotografi tekrar ana fotograf yapabilirsin.';

  @override
  String get profileTakePhoto => 'Fotograf cek';

  @override
  String get profilePickPhoto => 'Fotograf sec';

  @override
  String get profilePickVideo => 'Video sec';

  @override
  String get profileGalleryTitle => 'Galerin';

  @override
  String get deleteAccountStartTitle => 'Hesabi silme islemini baslat';

  @override
  String get deleteAccountStartSubtitle =>
      'Bu adimdan sonra son bir onay daha isteyecegiz. Devam etmeden once kredi bakiyen ve sohbetlerin dahil tum hesabinin silinecegini bilmelisin.';

  @override
  String get deleteAccountFinalTitle =>
      'Hesabinizi kalici olarak silmek istiyor musunuz?';

  @override
  String get deleteAccountFinalSubtitle =>
      'Bu islem geri alinamaz. Tum verileriniz kalici olarak silinecektir.';

  @override
  String get deleteAccountDeleting => 'Hesap siliniyor...';

  @override
  String get languageSheetTitle => 'Dil Secimi';

  @override
  String get languageChange => 'Degistir';

  @override
  String get languageChanging => 'Kaydediliyor...';

  @override
  String get languageUpdateFailedTitle => 'Dil guncellenemedi';

  @override
  String get profileMediaAll => 'Tumu';

  @override
  String get profileMediaPhotos => 'Fotograflar';

  @override
  String get profileMediaVideos => 'Videolar';

  @override
  String get profileNoUploadedMedia => 'Henuz yuklenmis medya yok.';

  @override
  String get profileNoUploadedPhotos => 'Henuz yuklenmis fotograf yok.';

  @override
  String get profileNoUploadedVideos => 'Henuz yuklenmis video yok.';

  @override
  String get profileCameraPhotoAction => 'Kameradan fotograf cek';

  @override
  String get profileGalleryPhotoAction => 'Galeriden fotograf sec';

  @override
  String get profileGalleryVideoAction => 'Galeriden video sec';

  @override
  String get profileMediaActionTitle => 'Medya islemleri';

  @override
  String get profileMakePrimary => 'Profil fotografi yap';

  @override
  String get profileDeleteMedia => 'Medyayi sil';

  @override
  String get profileScreenTitle => 'Profilim';

  @override
  String get profileFallbackDisplayName => 'Profilin';

  @override
  String get profileFallbackUsername => 'kullanici';

  @override
  String get profileChangePhoto => 'Fotografi Degistir';

  @override
  String get profileEditProfile => 'Profili Duzenle';

  @override
  String get profilePromoTitle => 'Sesli arama ve\npremium gorusmeleri ac';

  @override
  String get profilePromoAction => 'Yukselt';

  @override
  String get profileMediaManagementTitle => 'Medya Yonetimi';

  @override
  String get profileMediaManagementSubtitle =>
      'Fotograflarini yenile, yukle ve profil gorunumunu kontrol et.';

  @override
  String get profileManage => 'Yonet';

  @override
  String get profilePhotoDraftAdded => 'Fotograf profil taslagina eklendi.';

  @override
  String get profilePhotoPickFailed =>
      'Fotograf secilirken izin veya cihaz hatasi olustu.';

  @override
  String get profileVideoAuthRequired =>
      'Video yuklemek icin once giris yapmalisin.';

  @override
  String get profileVideoPickFailed =>
      'Video secilirken izin veya cihaz hatasi olustu.';

  @override
  String get profileMediaAuthRequired =>
      'Medya yuklemek icin once giris yapmalisin.';

  @override
  String get profileVideoAdded => 'Video profiline eklendi.';

  @override
  String get profilePhotoAdded => 'Fotograf profiline eklendi.';

  @override
  String get profileActionAuthRequired =>
      'Bu islem icin once giris yapmalisin.';

  @override
  String get profilePhotoUpdated => 'Profil fotografi guncellendi.';

  @override
  String get profileVideoRemoved => 'Video galeriden kaldirildi.';

  @override
  String get profilePhotoRemoved => 'Fotograf galeriden kaldirildi.';

  @override
  String get profilePhotoSourceTitle => 'Fotograf kaynagi';

  @override
  String get profileBadgeNew => 'Yeni';

  @override
  String get profileBadgePrimary => 'Ana';

  @override
  String get profileBadgeVideo => 'Video';

  @override
  String get commonSave => 'Kaydet';

  @override
  String get commonSaving => 'Kaydediliyor...';

  @override
  String get commonRetry => 'Tekrar Dene';

  @override
  String get restorePurchasesConnectionFailed =>
      'Magaza baglantisindan yanit alinamadi.';

  @override
  String get restorePurchasesUnavailable =>
      'Bu cihazda magaza baglantisi kullanilamiyor.';

  @override
  String get restorePurchasesStoreRequired =>
      'Geri yukleme icin App Store veya Google Play baglantisi gerekli.';

  @override
  String restorePurchasesRestoredCount(int count) {
    return '$count satin alim tekrar eslestirildi.';
  }

  @override
  String get restorePurchasesNotFound =>
      'Eslesen satin alim bulunamadi. Farkli magaza hesabi kullandiysan destek ekibiyle iletisime gecebilirsin.';

  @override
  String get restorePurchasesPreparing =>
      'Magaza geri yukleme islemini hazirliyor.';

  @override
  String get restorePurchasesResponseUnreadable => 'Magaza yaniti okunamadi.';

  @override
  String get restorePurchasesCancelled =>
      'Geri yukleme magaza tarafinda iptal edildi.';

  @override
  String get restorePurchasesSubtitle =>
      'App Store veya Google Play hesabinla daha once yapilan satin alimlari tekrar eslestirmeyi dener.';

  @override
  String get restorePurchasesStep1Title => 'Ayni magaza hesabini kullan';

  @override
  String get restorePurchasesStep1Description =>
      'Satin alim yaptigin hesap bu cihazda acik olmali.';

  @override
  String get restorePurchasesStep2Title => 'Magaza kaydini tara';

  @override
  String get restorePurchasesStep2Description =>
      'Geri yukleme komutu dogrudan mobil magazaya gonderilir.';

  @override
  String get restorePurchasesStep3Title => 'Sorun varsa destek ile ilerle';

  @override
  String get restorePurchasesStep3Description =>
      'Eslesen urun bulunmazsa bize ulas ekranindan talep acabilirsin.';

  @override
  String get restorePurchasesChecking => 'Magaza Kontrol Ediliyor...';

  @override
  String get restorePurchasesProcessing => 'Geri Yukleniyor...';

  @override
  String get restorePurchasesAction => 'Magazadan Geri Yukle';

  @override
  String get signOutConfirmTitle => 'Cikis yapmak istiyor musunuz?';

  @override
  String get signOutConfirmSubtitle =>
      'Tekrar giris yapmak icin Google veya Apple hesabinizi kullanabilirsiniz.';

  @override
  String get signOutProcessing => 'Cikis yapiliyor...';

  @override
  String unblockConfirmTitle(String name) {
    return '$name engelini kaldirmak istiyor musunuz?';
  }

  @override
  String get unblockConfirmSubtitle =>
      'Bu kisi tekrar size mesaj gonderebilecek.';

  @override
  String get unblockProcessing => 'Kaldiriliyor...';

  @override
  String get unblockAction => 'Engeli Kaldir';

  @override
  String get editProfileFirstName => 'Isim';

  @override
  String get editProfileSurname => 'Soyisim';

  @override
  String get editProfileUsername => 'Kullanici Adi';

  @override
  String get editProfileUsernameUnsupported =>
      'Kullanici adi degisikligi henuz mobil uygulamadan desteklenmiyor.';

  @override
  String get editProfileBio => 'Biyografi';

  @override
  String get editProfileBioPlaceholder => 'Kendinden bahset...';

  @override
  String get notificationsDescription =>
      'Yeni mesaj ve eslesme bildirimlerini ac veya kapat';

  @override
  String get notificationsVibration => 'Titresim';

  @override
  String get notificationsVibrationDescription =>
      'Bildirim geldiginde titresim kullan';

  @override
  String get saveFailedTitle => 'Kaydedilemedi';

  @override
  String get helpWriteMessageFirst => 'Lutfen once mesajinizi yazin.';

  @override
  String get helpAuthRequired =>
      'Destek mesaji gondermek icin once giris yapmalisin.';

  @override
  String get helpMessageReceivedTitle => 'Mesaj alindi';

  @override
  String get helpMessageReceivedSubtitle =>
      'Destek ekibi mesajinizi inceleyecek.';

  @override
  String get helpWhatsAppUnavailable =>
      'WhatsApp destek hatti henuz tanimlanmamis.';

  @override
  String get helpWhatsAppLaunchFailed => 'WhatsApp baglantisi acilamadi.';

  @override
  String get helpExternalLaunchFailed => 'Harici baglanti baslatilamadi.';

  @override
  String get helpFaqTitle => 'Sikca Sorulan Sorular';

  @override
  String get helpFaqQuestion1 => 'Tas nedir, nasil kullanilir?';

  @override
  String get helpFaqAnswer1 =>
      'Tas, uygulama ici sanal para birimidir. Ozel emoji gondermek ve ek ozellikler icin kullanilir.';

  @override
  String get helpFaqQuestion2 => 'Eslesme nasil calisir?';

  @override
  String get helpFaqAnswer2 =>
      'Kesfet bolumunden esles butonuna basarak rastgele birisiyle eslesebilirsiniz. Iki taraf da kabul ederse mesajlasma baslar.';

  @override
  String get helpFaqQuestion3 => 'Premium ne saglar?';

  @override
  String get helpFaqAnswer3 =>
      'Sinirsiz mesaj, seni kimin begendigini gorme, sesli arama ve haftalik boost gibi ozellikler sunar.';

  @override
  String get helpWriteUsTitle => 'Bize Yazin';

  @override
  String get helpMessagePlaceholder => 'Mesajinizi yazin...';

  @override
  String get helpSending => 'Gonderiliyor...';

  @override
  String get helpSend => 'Gonder';

  @override
  String get helpWhatsAppContact => 'WhatsApp ile Ulasin';

  @override
  String get helpWhatsAppComingSoon =>
      'WhatsApp destek hatti yakinda eklenecek';

  @override
  String get blockedUsersEmpty => 'Henuz engelledigin bir kullanici yok.';

  @override
  String get privacyTitle => 'Gizlilik Politikasi';

  @override
  String get privacyHeading1 => 'Veri Toplama';

  @override
  String get privacyBody1 =>
      'magmug uygulamasi, kullanicilarinin deneyimini iyilestirmek amaciyla belirli kisisel verileri toplar. Bu veriler arasinda ad, soyad, e-posta adresi, konum bilgisi, profil fotograflari ve uygulama ici etkilesim verileri yer alir.';

  @override
  String get privacyHeading2 => 'Verilerin Kullanimi';

  @override
  String get privacyBody2 =>
      'Toplanan veriler, size daha iyi eslesmeler sunmak, uygulama deneyimini kisisellestirmek ve guvenliginizi saglamak amaciyla kullanilir. Verileriniz ucuncu taraflarla paylasilmaz.';

  @override
  String get privacyHeading3 => 'Veri Guvenligi';

  @override
  String get privacyBody3 =>
      'Tum kisisel verileriniz 256-bit SSL sifreleme ile korunmaktadir. Sunucularimiz guvenli veri merkezlerinde barindirilmakta ve duzenli olarak denetlenmektedir.';

  @override
  String get privacyHeading4 => 'Cerezler';

  @override
  String get privacyBody4 =>
      'Uygulamamiz, kullanici deneyimini iyilestirmek icin cerezler ve benzer teknolojiler kullanmaktadir. Bu cerezler, oturum yonetimi ve tercihlerinizin hatirlanmasi amaciyla kullanilir.';

  @override
  String get privacyHeading5 => 'Iletisim';

  @override
  String get privacyBody5 =>
      'Gizlilik politikamizla ilgili sorulariniz icin destek sayfamiz uzerinden bize ulasabilirsiniz.';

  @override
  String get termsTitle => 'Kullanim Kosullari';

  @override
  String get termsHeading1 => 'Hizmet Tanimi';

  @override
  String get termsBody1 =>
      'magmug, kullanicilarin birbirleriyle mesajlasma ve eslesme yoluyla tanismalarini saglayan bir sosyal platformdur.';

  @override
  String get termsHeading2 => 'Kullanici Sorumluluklari';

  @override
  String get termsBody2 =>
      'Kullanicilar, dogru ve guncel bilgiler saglamakla yukumludur. Sahte profil olusturmak, taciz, kufur ve uygunsuz icerik paylasmak kesinlikle yasaktir.';

  @override
  String get termsHeading3 => 'Yas Siniri';

  @override
  String get termsBody3 =>
      'magmug\'u kullanmak icin en az 18 yasinda olmaniz gerekmektedir. 18 yasindan kucuk kullanicilarin hesaplari tespit edildiginde kapatilacaktir.';

  @override
  String get termsHeading4 => 'Odeme ve Iadeler';

  @override
  String get termsBody4 =>
      'Uygulama ici satin alimlar Apple App Store veya Google Play Store uzerinden gerceklestirilir. Iade talepleri ilgili magaza politikalarina tabidir.';

  @override
  String get termsHeading5 => 'Hesap Sonlandirma';

  @override
  String get termsBody5 =>
      'magmug, kullanim kosullarini ihlal eden hesaplari onceden bildirim yapmaksizin askiya alma veya sonlandirma hakkini sakli tutar.';

  @override
  String get paywallHeaderTitle => 'Premium';

  @override
  String get paywallBadgePremium => 'PREMIUM';

  @override
  String get paywallHeroTitle => 'Mesajlasmanin otesine gec';

  @override
  String get paywallHeroSubtitle =>
      'Sesli ve goruntulu gorusmeleri ac, daha hizli yakinlas ve premium deneyime gec.';

  @override
  String get paywallFeatureVoice => 'Sesli arama';

  @override
  String get paywallFeatureVideo => 'Goruntulu gorusme';

  @override
  String get paywallFeatureBoost => 'One cik';

  @override
  String get paywallPlansTitle => 'Sana uygun premium plan';

  @override
  String get paywallPlanWeek => '1 Hafta';

  @override
  String get paywallPlanMonth => '1 Ay';

  @override
  String get paywallPlanQuarter => '3 Ay';

  @override
  String get paywallPeriodWeek => 'haftalik';

  @override
  String get paywallPeriodMonth => 'aylik';

  @override
  String get paywallPeriodQuarter => '3 aylik';

  @override
  String get paywallPopular => 'POPULER';

  @override
  String get paywallAdvantage => 'AVANTAJ';

  @override
  String paywallContinueWith(String price) {
    return '$price ile Devam Et';
  }

  @override
  String get paywallPendingBadge => 'ODEME ISLENIYOR';

  @override
  String get paywallPendingTitle => 'Premium onayi bekleniyor';

  @override
  String get paywallPendingSubtitle =>
      'Magaza satin alma onayini tamamladiginda premium erisimin otomatik olarak acilacak.';

  @override
  String get paywallPendingStatus => 'Magaza dogrulamasi bekleniyor';

  @override
  String get paywallBackToPlans => 'Planlara Don';

  @override
  String paywallSaveLabel(String save) {
    return '$save tasarruf';
  }

  @override
  String get paywallLegalPrefix => 'Detayli bilgi icin ';

  @override
  String get paywallLegalAnd => ' ve ';

  @override
  String get jetonPaymentSuccessBadge => 'ODEME BASARILI';

  @override
  String get jetonPaymentSuccessTitle => 'Kredi paketin hazir';

  @override
  String get jetonPaymentSuccessSubtitle =>
      'Yeni kredi paketin hesabina eklendi. Sohbetlerine kaldigin yerden devam edebilirsin.';

  @override
  String get jetonCreditsAdded => 'Krediler hesaba eklendi';

  @override
  String get jetonAwesome => 'Harika';

  @override
  String get jetonCreditsUnit => 'Kredi';

  @override
  String jetonAmountLabel(String amount) {
    return '$amount Kredi';
  }

  @override
  String get jetonOfferTitle => 'Iste size ozel oneri!';

  @override
  String get jetonOfferSubtitle =>
      'Sohbetlerine devam etmek icin sana en uygun kredi paketini sec.';

  @override
  String jetonBuyWith(String price) {
    return '$price ile Satin Al';
  }

  @override
  String get jetonInstantCreditInfo =>
      'Odeme tamamlandiginda krediler hesabina aninda eklenir.';

  @override
  String get jetonMostPopular => 'EN POPULER';
}
