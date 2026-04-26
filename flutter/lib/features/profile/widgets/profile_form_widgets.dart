import 'package:magmug/app_core.dart';
import 'package:magmug/features/profile/widgets/profile_settings_widgets.dart';

class ProfileMiniLabeledField extends StatelessWidget {
  final String label;
  final TextEditingController controller;
  final String? placeholder;
  final double height;
  final int? maxLines;
  final bool readOnly;

  const ProfileMiniLabeledField({
    super.key,
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
          constraints: BoxConstraints(minHeight: height),
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
          decoration: BoxDecoration(
            color: AppColors.grayField,
            borderRadius: BorderRadius.circular(12),
          ),
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
            padding: EdgeInsets.zero,
            cursorColor: AppColors.indigo,
          ),
        ),
      ],
    );
  }
}

class ProfileFlagLanguageRow extends StatelessWidget {
  final String flag;
  final String label;
  final bool selected;
  final VoidCallback onTap;

  const ProfileFlagLanguageRow({
    super.key,
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

class ProfileSoftBlackSwitch extends StatelessWidget {
  final bool value;
  final ValueChanged<bool> onChanged;

  const ProfileSoftBlackSwitch({
    super.key,
    required this.value,
    required this.onChanged,
  });

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

class ProfileEditProfileSheetView extends StatelessWidget {
  final String title;
  final String firstNameLabel;
  final TextEditingController firstNameController;
  final String surnameLabel;
  final TextEditingController surnameController;
  final String usernameLabel;
  final TextEditingController usernameController;
  final bool isUsernameReadOnly;
  final String usernameHint;
  final String bioLabel;
  final TextEditingController bioController;
  final String bioPlaceholder;
  final String? errorMessage;
  final String saveLabel;
  final VoidCallback? onSave;

  const ProfileEditProfileSheetView({
    super.key,
    required this.title,
    required this.firstNameLabel,
    required this.firstNameController,
    required this.surnameLabel,
    required this.surnameController,
    required this.usernameLabel,
    required this.usernameController,
    required this.isUsernameReadOnly,
    required this.usernameHint,
    required this.bioLabel,
    required this.bioController,
    required this.bioPlaceholder,
    required this.errorMessage,
    required this.saveLabel,
    required this.onSave,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const ProfileSheetHandle(),
        const SizedBox(height: 18),
        Text(
          title,
          style: const TextStyle(
            fontFamily: AppFont.family,
            fontWeight: FontWeight.w800,
            fontSize: 18,
            color: AppColors.black,
          ),
        ),
        const SizedBox(height: 16),
        ProfileMiniLabeledField(
          label: firstNameLabel,
          controller: firstNameController,
        ),
        const SizedBox(height: 12),
        ProfileMiniLabeledField(
          label: surnameLabel,
          controller: surnameController,
        ),
        const SizedBox(height: 12),
        ProfileMiniLabeledField(
          label: usernameLabel,
          controller: usernameController,
          readOnly: isUsernameReadOnly,
        ),
        if (isUsernameReadOnly) ...[
          const SizedBox(height: 8),
          Text(
            usernameHint,
            style: const TextStyle(
              fontFamily: AppFont.family,
              fontSize: 12,
              height: 1.4,
              color: AppColors.gray,
            ),
          ),
        ],
        const SizedBox(height: 12),
        ProfileMiniLabeledField(
          label: bioLabel,
          controller: bioController,
          placeholder: bioPlaceholder,
          height: 88,
          maxLines: 3,
        ),
        if (errorMessage != null) ...[
          const SizedBox(height: 12),
          Text(
            errorMessage!,
            style: const TextStyle(
              fontFamily: AppFont.family,
              fontSize: 12,
              height: 1.4,
              color: Color(0xFFEF4444),
            ),
          ),
        ],
        const SizedBox(height: 20),
        GradientButton(label: saveLabel, onTap: onSave),
      ],
    );
  }
}

class ProfileLanguageSheetView extends StatelessWidget {
  final String title;
  final List<AppLanguage> languages;
  final AppLanguage selectedLanguage;
  final ValueChanged<AppLanguage> onSelect;
  final String saveLabel;
  final VoidCallback? onSave;

  const ProfileLanguageSheetView({
    super.key,
    required this.title,
    required this.languages,
    required this.selectedLanguage,
    required this.onSelect,
    required this.saveLabel,
    required this.onSave,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const ProfileSheetHandle(),
        const SizedBox(height: 18),
        Text(
          title,
          style: const TextStyle(
            fontFamily: AppFont.family,
            fontWeight: FontWeight.w800,
            fontSize: 18,
            color: AppColors.black,
          ),
        ),
        const SizedBox(height: 16),
        ...languages.map(
          (language) => Padding(
            padding: const EdgeInsets.only(bottom: 8),
            child: ProfileFlagLanguageRow(
              flag: language.flagCode,
              label: language.label,
              selected: selectedLanguage == language,
              onTap: () => onSelect(language),
            ),
          ),
        ),
        const SizedBox(height: 8),
        GradientButton(label: saveLabel, onTap: onSave),
      ],
    );
  }
}

class ProfileNotificationPreferenceRow extends StatelessWidget {
  final String title;
  final String description;
  final bool value;
  final ValueChanged<bool> onChanged;

  const ProfileNotificationPreferenceRow({
    super.key,
    required this.title,
    required this.description,
    required this.value,
    required this.onChanged,
  });

  @override
  Widget build(BuildContext context) {
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
                  description,
                  style: const TextStyle(
                    fontFamily: AppFont.family,
                    fontSize: 11.5,
                    color: Color(0xFF999999),
                  ),
                ),
              ],
            ),
          ),
          ProfileSoftBlackSwitch(value: value, onChanged: onChanged),
        ],
      ),
    );
  }
}

class ProfileNotificationPrefsSheetView extends StatelessWidget {
  final String title;
  final String notificationsTitle;
  final String notificationsDescription;
  final bool notificationsEnabled;
  final ValueChanged<bool> onNotificationsChanged;
  final String vibrationTitle;
  final String vibrationDescription;
  final bool vibrationEnabled;
  final ValueChanged<bool> onVibrationChanged;
  final String messageSoundsTitle;
  final String messageSoundsDescription;
  final bool messageSoundsEnabled;
  final ValueChanged<bool> onMessageSoundsChanged;
  final String saveLabel;
  final VoidCallback? onSave;

  const ProfileNotificationPrefsSheetView({
    super.key,
    required this.title,
    required this.notificationsTitle,
    required this.notificationsDescription,
    required this.notificationsEnabled,
    required this.onNotificationsChanged,
    required this.vibrationTitle,
    required this.vibrationDescription,
    required this.vibrationEnabled,
    required this.onVibrationChanged,
    required this.messageSoundsTitle,
    required this.messageSoundsDescription,
    required this.messageSoundsEnabled,
    required this.onMessageSoundsChanged,
    required this.saveLabel,
    required this.onSave,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const ProfileSheetHandle(),
        const SizedBox(height: 18),
        Text(
          title,
          style: const TextStyle(
            fontFamily: AppFont.family,
            fontWeight: FontWeight.w800,
            fontSize: 18,
            color: AppColors.black,
          ),
        ),
        const SizedBox(height: 8),
        ProfileNotificationPreferenceRow(
          title: notificationsTitle,
          description: notificationsDescription,
          value: notificationsEnabled,
          onChanged: onNotificationsChanged,
        ),
        Container(height: 1, color: const Color(0xFFF0F0F0)),
        ProfileNotificationPreferenceRow(
          title: vibrationTitle,
          description: vibrationDescription,
          value: vibrationEnabled,
          onChanged: onVibrationChanged,
        ),
        Container(height: 1, color: const Color(0xFFF0F0F0)),
        ProfileNotificationPreferenceRow(
          title: messageSoundsTitle,
          description: messageSoundsDescription,
          value: messageSoundsEnabled,
          onChanged: onMessageSoundsChanged,
        ),
        const SizedBox(height: 16),
        GradientButton(label: saveLabel, onTap: onSave),
      ],
    );
  }
}
