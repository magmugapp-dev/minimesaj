// ignore: unused_import
import 'package:intl/intl.dart' as intl;
import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for English (`en`).
class AppLocalizationsEn extends AppLocalizations {
  AppLocalizationsEn([String locale = 'en']) : super(locale);

  @override
  String get commonDone => 'Done';

  @override
  String get commonCancel => 'Cancel';

  @override
  String get commonContinue => 'Continue';

  @override
  String get commonClose => 'Close';

  @override
  String get commonOk => 'OK';

  @override
  String get commonLoading => 'Loading';

  @override
  String get commonRefresh => 'Refresh';

  @override
  String get profileGemBalance => 'Stone Balance';

  @override
  String get profileContactUs => 'Contact Us';

  @override
  String get profileNotifications => 'Notifications';

  @override
  String get profileLanguage => 'Language';

  @override
  String get profileBlockedUsers => 'Blocked Users';

  @override
  String get profileHelp => 'Help';

  @override
  String get profileRestorePurchases => 'Restore Purchases';

  @override
  String get profilePrivacyPolicy => 'Privacy Policy';

  @override
  String get profileKvkk => 'KVKK Disclosure';

  @override
  String get profileTerms => 'Terms of Use';

  @override
  String get profileDeleteAccount => 'Delete Account';

  @override
  String get profileSignOut => 'Sign Out';

  @override
  String get profilePhotoTitle => 'Profile Photo';

  @override
  String get profilePhotoSubtitle =>
      'Quickly update your avatar and profile gallery from here.';

  @override
  String get profileNoActivePhotos => 'No active photos yet';

  @override
  String profileActivePhotosCount(int count) {
    return '$count active photos';
  }

  @override
  String profileVideoCount(int count) {
    return '$count videos are also visible in your profile gallery.';
  }

  @override
  String get profilePrimaryHint =>
      'The first photo becomes the primary view. You can promote an existing gallery photo as your main photo at any time.';

  @override
  String get profileTakePhoto => 'Take photo';

  @override
  String get profilePickPhoto => 'Choose photo';

  @override
  String get profilePickVideo => 'Choose video';

  @override
  String get profileGalleryTitle => 'Your gallery';

  @override
  String get deleteAccountStartTitle => 'Start account deletion';

  @override
  String get deleteAccountStartSubtitle =>
      'We will ask for one final confirmation after this step. Before you continue, note that your balance, chats, and full account data will be removed.';

  @override
  String get deleteAccountFinalTitle =>
      'Do you want to permanently delete your account?';

  @override
  String get deleteAccountFinalSubtitle =>
      'This action cannot be undone. All of your data will be permanently removed.';

  @override
  String get deleteAccountDeleting => 'Deleting account...';

  @override
  String get languageSheetTitle => 'Language';

  @override
  String get languageChange => 'Change';

  @override
  String get languageChanging => 'Saving...';

  @override
  String get languageUpdateFailedTitle => 'Language update failed';

  @override
  String get profileMediaAll => 'All';

  @override
  String get profileMediaPhotos => 'Photos';

  @override
  String get profileMediaVideos => 'Videos';

  @override
  String get profileNoUploadedMedia => 'No uploaded media yet.';

  @override
  String get profileNoUploadedPhotos => 'No uploaded photos yet.';

  @override
  String get profileNoUploadedVideos => 'No uploaded videos yet.';

  @override
  String get profileCameraPhotoAction => 'Take a photo';

  @override
  String get profileGalleryPhotoAction => 'Choose from gallery';

  @override
  String get profileGalleryVideoAction => 'Choose a video';

  @override
  String get profileMediaActionTitle => 'Media actions';

  @override
  String get profileMakePrimary => 'Set as profile photo';

  @override
  String get profileDeleteMedia => 'Delete media';

  @override
  String get profileScreenTitle => 'My Profile';

  @override
  String get profileFallbackDisplayName => 'Your profile';

  @override
  String get profileFallbackUsername => 'user';

  @override
  String get profileChangePhoto => 'Change Photo';

  @override
  String get profileEditProfile => 'Edit Profile';

  @override
  String get profilePromoTitle =>
      'Unlock voice calls and\npremium conversations';

  @override
  String get profilePromoAction => 'Upgrade';

  @override
  String get profileMediaManagementTitle => 'Media Management';

  @override
  String get profileMediaManagementSubtitle =>
      'Refresh uploads and control how your profile media appears.';

  @override
  String get profileManage => 'Manage';

  @override
  String get profilePhotoDraftAdded => 'Photo added to your profile draft.';

  @override
  String get profilePhotoPickFailed =>
      'Photo selection failed because of a permission or device error.';

  @override
  String get profileVideoAuthRequired =>
      'You need to sign in before uploading a video.';

  @override
  String get profileVideoPickFailed =>
      'Video selection failed because of a permission or device error.';

  @override
  String get profileMediaAuthRequired =>
      'You need to sign in before uploading media.';

  @override
  String get profileVideoAdded => 'Video added to your profile.';

  @override
  String get profilePhotoAdded => 'Photo added to your profile.';

  @override
  String get profileActionAuthRequired =>
      'You need to sign in before using this action.';

  @override
  String get profilePhotoUpdated => 'Profile photo updated.';

  @override
  String get profileVideoRemoved => 'Video removed from your gallery.';

  @override
  String get profilePhotoRemoved => 'Photo removed from your gallery.';

  @override
  String get profilePhotoSourceTitle => 'Photo source';

  @override
  String get profileBadgeNew => 'New';

  @override
  String get profileBadgePrimary => 'Main';

  @override
  String get profileBadgeVideo => 'Video';

  @override
  String get commonSave => 'Save';

  @override
  String get commonSaving => 'Saving...';

  @override
  String get commonRetry => 'Retry';

  @override
  String get restorePurchasesConnectionFailed =>
      'The store connection did not respond.';

  @override
  String get restorePurchasesUnavailable =>
      'Store connectivity is not available on this device.';

  @override
  String get restorePurchasesStoreRequired =>
      'Restoring purchases requires an App Store or Google Play connection.';

  @override
  String restorePurchasesRestoredCount(int count) {
    return '$count purchases were restored.';
  }

  @override
  String get restorePurchasesNotFound =>
      'No matching purchases were found. If you used a different store account, contact support.';

  @override
  String get restorePurchasesPreparing =>
      'The store is preparing the restore flow.';

  @override
  String get restorePurchasesResponseUnreadable =>
      'The store response could not be read.';

  @override
  String get restorePurchasesCancelled =>
      'The restore flow was cancelled by the store.';

  @override
  String get restorePurchasesSubtitle =>
      'Attempts to match purchases previously made with your App Store or Google Play account.';

  @override
  String get restorePurchasesStep1Title => 'Use the same store account';

  @override
  String get restorePurchasesStep1Description =>
      'The account used for the purchase should be active on this device.';

  @override
  String get restorePurchasesStep2Title => 'Scan the store record';

  @override
  String get restorePurchasesStep2Description =>
      'The restore command is sent directly to the mobile store.';

  @override
  String get restorePurchasesStep3Title => 'Contact support if needed';

  @override
  String get restorePurchasesStep3Description =>
      'If nothing matches, you can open a support request from the contact screen.';

  @override
  String get restorePurchasesChecking => 'Checking Store...';

  @override
  String get restorePurchasesProcessing => 'Restoring...';

  @override
  String get restorePurchasesAction => 'Restore from Store';

  @override
  String get signOutConfirmTitle => 'Do you want to sign out?';

  @override
  String get signOutConfirmSubtitle =>
      'You can sign back in with your Google or Apple account.';

  @override
  String get signOutProcessing => 'Signing out...';

  @override
  String unblockConfirmTitle(String name) {
    return 'Do you want to unblock $name?';
  }

  @override
  String get unblockConfirmSubtitle =>
      'This person will be able to message you again.';

  @override
  String get unblockProcessing => 'Removing...';

  @override
  String get unblockAction => 'Remove Block';

  @override
  String get editProfileFirstName => 'First name';

  @override
  String get editProfileSurname => 'Last name';

  @override
  String get editProfileUsername => 'Username';

  @override
  String get editProfileUsernameUnsupported =>
      'Username changes are not yet supported from the mobile app.';

  @override
  String get editProfileBio => 'Bio';

  @override
  String get editProfileBioPlaceholder => 'Tell us about yourself...';

  @override
  String get notificationsDescription =>
      'Turn message and match notifications on or off';

  @override
  String get notificationsVibration => 'Vibration';

  @override
  String get notificationsVibrationDescription =>
      'Use vibration when a notification arrives';

  @override
  String get saveFailedTitle => 'Could not save';

  @override
  String get helpWriteMessageFirst => 'Please write your message first.';

  @override
  String get helpAuthRequired =>
      'You need to sign in before sending a support message.';

  @override
  String get helpMessageReceivedTitle => 'Message received';

  @override
  String get helpMessageReceivedSubtitle =>
      'The support team will review your message.';

  @override
  String get helpWhatsAppUnavailable =>
      'The WhatsApp support line is not configured yet.';

  @override
  String get helpWhatsAppLaunchFailed => 'Could not open the WhatsApp link.';

  @override
  String get helpExternalLaunchFailed => 'Could not open the external link.';

  @override
  String get helpFaqTitle => 'Frequently Asked Questions';

  @override
  String get helpFaqQuestion1 => 'What is Stone and how is it used?';

  @override
  String get helpFaqAnswer1 =>
      'Stone is the in-app currency. It is used for special emoji and additional features.';

  @override
  String get helpFaqQuestion2 => 'How does matching work?';

  @override
  String get helpFaqAnswer2 =>
      'You can tap the match button in Discover to match with someone random. If both sides accept, chat starts.';

  @override
  String get helpFaqQuestion3 => 'What does Premium include?';

  @override
  String get helpFaqAnswer3 =>
      'It offers unlimited messages, seeing who liked you, voice calls, and weekly boosts.';

  @override
  String get helpWriteUsTitle => 'Write to Us';

  @override
  String get helpMessagePlaceholder => 'Write your message...';

  @override
  String get helpSending => 'Sending...';

  @override
  String get helpSend => 'Send';

  @override
  String get helpWhatsAppContact => 'Contact via WhatsApp';

  @override
  String get helpWhatsAppComingSoon => 'WhatsApp support will be added soon';

  @override
  String get blockedUsersEmpty => 'You have not blocked any users yet.';

  @override
  String get privacyTitle => 'Privacy Policy';

  @override
  String get privacyHeading1 => 'Data Collection';

  @override
  String get privacyBody1 =>
      'magmug collects certain personal data to improve user experience. This includes name, surname, email address, location, profile photos, and in-app interaction data.';

  @override
  String get privacyHeading2 => 'How Data Is Used';

  @override
  String get privacyBody2 =>
      'Collected data is used to provide better matches, personalize the experience, and maintain safety. Your data is not shared with third parties.';

  @override
  String get privacyHeading3 => 'Data Security';

  @override
  String get privacyBody3 =>
      'All personal data is protected with 256-bit SSL encryption. Our servers are hosted in secure data centers and reviewed regularly.';

  @override
  String get privacyHeading4 => 'Cookies';

  @override
  String get privacyBody4 =>
      'The app uses cookies and similar technologies to improve the user experience. These support session management and remembering preferences.';

  @override
  String get privacyHeading5 => 'Contact';

  @override
  String get privacyBody5 =>
      'You can contact us through the support section for any privacy-related questions.';

  @override
  String get termsTitle => 'Terms of Use';

  @override
  String get termsHeading1 => 'Service Description';

  @override
  String get termsBody1 =>
      'magmug is a social platform that helps people meet through messaging and matching.';

  @override
  String get termsHeading2 => 'User Responsibilities';

  @override
  String get termsBody2 =>
      'Users must provide accurate and up-to-date information. Fake profiles, harassment, abusive language, and inappropriate content are strictly forbidden.';

  @override
  String get termsHeading3 => 'Age Limit';

  @override
  String get termsBody3 =>
      'You must be at least 18 years old to use magmug. Accounts belonging to underage users will be closed when detected.';

  @override
  String get termsHeading4 => 'Payments and Refunds';

  @override
  String get termsBody4 =>
      'In-app purchases are processed through Apple App Store or Google Play Store. Refund requests are subject to the relevant store policies.';

  @override
  String get termsHeading5 => 'Account Termination';

  @override
  String get termsBody5 =>
      'magmug reserves the right to suspend or terminate accounts that violate the terms of use without prior notice.';

  @override
  String get paywallHeaderTitle => 'Premium';

  @override
  String get paywallBadgePremium => 'PREMIUM';

  @override
  String get paywallHeroTitle => 'Go beyond messaging';

  @override
  String get paywallHeroSubtitle =>
      'Unlock voice and video calls, connect faster, and switch to the premium experience.';

  @override
  String get paywallFeatureVoice => 'Voice calls';

  @override
  String get paywallFeatureVideo => 'Video calls';

  @override
  String get paywallFeatureBoost => 'Stand out';

  @override
  String get paywallPlansTitle => 'The best premium plan for you';

  @override
  String get paywallPlanWeek => '1 Week';

  @override
  String get paywallPlanMonth => '1 Month';

  @override
  String get paywallPlanQuarter => '3 Months';

  @override
  String get paywallPeriodWeek => 'weekly';

  @override
  String get paywallPeriodMonth => 'monthly';

  @override
  String get paywallPeriodQuarter => '3 months';

  @override
  String get paywallPopular => 'POPULAR';

  @override
  String get paywallAdvantage => 'VALUE';

  @override
  String paywallContinueWith(String price) {
    return 'Continue with $price';
  }

  @override
  String get paywallPendingBadge => 'PAYMENT PROCESSING';

  @override
  String get paywallPendingTitle => 'Waiting for premium approval';

  @override
  String get paywallPendingSubtitle =>
      'Your premium access will unlock automatically when the store finishes purchase approval.';

  @override
  String get paywallPendingStatus => 'Waiting for store verification';

  @override
  String get paywallBackToPlans => 'Back to plans';

  @override
  String paywallSaveLabel(String save) {
    return 'Save $save';
  }

  @override
  String get paywallLegalPrefix => 'For details see ';

  @override
  String get paywallLegalAnd => ' and ';

  @override
  String get jetonPaymentSuccessBadge => 'PAYMENT SUCCESSFUL';

  @override
  String get jetonPaymentSuccessTitle => 'Your credit pack is ready';

  @override
  String get jetonPaymentSuccessSubtitle =>
      'Your new credit pack has been added to your account. You can continue your chats right away.';

  @override
  String get jetonCreditsAdded => 'Credits added to account';

  @override
  String get jetonAwesome => 'Great';

  @override
  String get jetonCreditsUnit => 'Credits';

  @override
  String jetonAmountLabel(String amount) {
    return '$amount Credits';
  }

  @override
  String get jetonOfferTitle => 'A suggestion just for you!';

  @override
  String get jetonOfferSubtitle =>
      'Choose the credit pack that fits your chats best.';

  @override
  String jetonBuyWith(String price) {
    return 'Buy with $price';
  }

  @override
  String get jetonInstantCreditInfo =>
      'Credits are added to your account as soon as payment completes.';

  @override
  String get jetonMostPopular => 'MOST POPULAR';
}
