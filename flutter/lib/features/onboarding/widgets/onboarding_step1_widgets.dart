import 'dart:async';

import 'package:magmug/app_core.dart';

enum OnboardingUsernameAvailabilityStatus {
  initial,
  checking,
  available,
  unavailable,
  error,
}

class OnboardingStep1View extends StatelessWidget {
  final OnboardData data;
  final VoidCallback onBack;
  final VoidCallback onContinue;
  final ValueChanged<String> onNameChanged;
  final ValueChanged<String> onSurnameChanged;
  final ValueChanged<String> onUsernameChanged;
  final ValueChanged<OnboardingUsernameAvailabilityStatus>
  onUsernameStatusChanged;
  final ValueChanged<int> onBirthYearChanged;
  final OnboardingUsernameAvailabilityStatus usernameStatus;

  const OnboardingStep1View({
    super.key,
    required this.data,
    required this.onBack,
    required this.onContinue,
    required this.onNameChanged,
    required this.onSurnameChanged,
    required this.onUsernameChanged,
    required this.onUsernameStatusChanged,
    required this.onBirthYearChanged,
    required this.usernameStatus,
  });

  @override
  Widget build(BuildContext context) {
    return CupertinoPageScaffold(
      backgroundColor: AppColors.white,
      child: SafeArea(
        child: Column(
          children: [
            const SizedBox(height: 8),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 24),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const StepProgressBar(currentStep: 1),
                  const SizedBox(height: 20),
                  Align(
                    alignment: Alignment.centerLeft,
                    child: CircleBackButton(onTap: onBack),
                  ),
                  const SizedBox(height: 20),
                  const Text(
                    'Magmug hesabini\nolusturalim',
                    style: TextStyle(
                      fontFamily: AppFont.family,
                      fontWeight: FontWeight.w800,
                      fontSize: 27.3,
                      height: 32.2 / 27.3,
                      color: AppColors.black,
                      letterSpacing: -1,
                    ),
                  ),
                  const SizedBox(height: 10),
                  const Text(
                    'Gercek adini yaz, boylece insanlar seni taniyabilir',
                    style: TextStyle(
                      fontFamily: AppFont.family,
                      fontSize: 14,
                      color: AppColors.gray,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 24),
            Expanded(
              child: ListView(
                padding: const EdgeInsets.symmetric(horizontal: 24),
                keyboardDismissBehavior:
                    ScrollViewKeyboardDismissBehavior.onDrag,
                children: [
                  LabeledField(
                    label: 'ISIM',
                    initialValue: data.name,
                    placeholder: 'Adini gir...',
                    onChanged: onNameChanged,
                  ),
                  const SizedBox(height: 16),
                  LabeledField(
                    label: 'SOYISIM',
                    initialValue: data.surname,
                    placeholder: 'Soyisim gir...',
                    onChanged: onSurnameChanged,
                  ),
                  const SizedBox(height: 16),
                  OnboardingUsernameAvailabilityField(
                    initialValue: data.username,
                    onChanged: onUsernameChanged,
                    onStatusChanged: onUsernameStatusChanged,
                  ),
                  const SizedBox(height: 16),
                  OnboardingYearField(
                    value: data.birthYear,
                    onChanged: onBirthYearChanged,
                  ),
                  const SizedBox(height: 10),
                  const Text(
                    'Yasin diger kullanicilara gosterilecek ancak dogum tarihin gizli kalacak',
                    style: TextStyle(
                      fontFamily: AppFont.family,
                      fontSize: 12,
                      height: 1.45,
                      color: AppColors.gray,
                    ),
                  ),
                  const SizedBox(height: 40),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(24, 8, 24, 24),
              child: GradientButton(
                label: 'Devam Et',
                onTap:
                    data.step1Valid &&
                        usernameStatus ==
                            OnboardingUsernameAvailabilityStatus.available
                    ? onContinue
                    : null,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class OnboardingUsernameAvailabilityField extends StatefulWidget {
  final String? initialValue;
  final ValueChanged<String> onChanged;
  final ValueChanged<OnboardingUsernameAvailabilityStatus> onStatusChanged;

  const OnboardingUsernameAvailabilityField({
    super.key,
    required this.onChanged,
    required this.onStatusChanged,
    this.initialValue,
  });

  @override
  State<OnboardingUsernameAvailabilityField> createState() =>
      _OnboardingUsernameAvailabilityFieldState();
}

class _OnboardingUsernameAvailabilityFieldState
    extends State<OnboardingUsernameAvailabilityField> {
  static const _minimumUsernameLength = 4;
  static const _debounceDuration = Duration(milliseconds: 450);

  final AppAuthApi _authApi = AppAuthApi();
  final FocusNode _focusNode = FocusNode();
  Timer? _debounce;
  late final TextEditingController _controller;

  bool _focused = false;
  int _requestId = 0;
  OnboardingUsernameAvailabilityStatus _status =
      OnboardingUsernameAvailabilityStatus.initial;
  String? _statusMessage;

  @override
  void initState() {
    super.initState();
    _controller = TextEditingController(text: widget.initialValue ?? '');
    _focusNode.addListener(_handleFocusChange);
    _scheduleAvailabilityCheck(_controller.text, immediate: true);
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _focusNode
      ..removeListener(_handleFocusChange)
      ..dispose();
    _controller.dispose();
    _authApi.close();
    super.dispose();
  }

  void _handleFocusChange() {
    if (!mounted) {
      return;
    }

    setState(() {
      _focused = _focusNode.hasFocus;
    });
  }

  String _normalizeUsername(String value) {
    return value.trim().replaceFirst(RegExp(r'^@+'), '');
  }

  void _setStatus(
    OnboardingUsernameAvailabilityStatus status, {
    String? message,
  }) {
    widget.onStatusChanged(status);
    if (!mounted) {
      return;
    }

    setState(() {
      _status = status;
      _statusMessage = message;
    });
  }

  void _scheduleAvailabilityCheck(String rawValue, {bool immediate = false}) {
    final username = _normalizeUsername(rawValue);
    _debounce?.cancel();

    if (username.isEmpty) {
      _requestId++;
      _setStatus(OnboardingUsernameAvailabilityStatus.initial);
      return;
    }

    if (username.length < _minimumUsernameLength) {
      _requestId++;
      _setStatus(
        OnboardingUsernameAvailabilityStatus.initial,
        message: 'Kullanici adi en az $_minimumUsernameLength karakter olmali.',
      );
      return;
    }

    _setStatus(
      OnboardingUsernameAvailabilityStatus.checking,
      message: 'Kullanici adi kontrol ediliyor...',
    );

    final requestId = ++_requestId;
    if (immediate) {
      unawaited(_checkAvailability(username, requestId));
      return;
    }

    _debounce = Timer(_debounceDuration, () {
      unawaited(_checkAvailability(username, requestId));
    });
  }

  Future<void> _checkAvailability(String username, int requestId) async {
    try {
      final isAvailable = await _authApi.checkUsernameAvailability(username);
      if (!mounted || requestId != _requestId) {
        return;
      }

      _setStatus(
        isAvailable
            ? OnboardingUsernameAvailabilityStatus.available
            : OnboardingUsernameAvailabilityStatus.unavailable,
        message: isAvailable
            ? 'Bu kullanici adi musait.'
            : 'Bu kullanici adi zaten kullaniliyor.',
      );
    } catch (_) {
      if (!mounted || requestId != _requestId) {
        return;
      }

      _setStatus(
        OnboardingUsernameAvailabilityStatus.error,
        message: 'Kullanici adi su an kontrol edilemiyor.',
      );
    }
  }

  Widget? _buildSuffix() {
    switch (_status) {
      case OnboardingUsernameAvailabilityStatus.checking:
        return const SizedBox(
          width: 18,
          height: 18,
          child: CupertinoActivityIndicator(radius: 8),
        );
      case OnboardingUsernameAvailabilityStatus.available:
        return const Icon(
          CupertinoIcons.check_mark_circled_solid,
          color: AppColors.onlineGreen,
          size: 20,
        );
      case OnboardingUsernameAvailabilityStatus.unavailable:
        return const Icon(
          CupertinoIcons.xmark_circle_fill,
          color: AppColors.coral,
          size: 20,
        );
      case OnboardingUsernameAvailabilityStatus.initial:
      case OnboardingUsernameAvailabilityStatus.error:
        return null;
    }
  }

  Color _borderColor() {
    return switch (_status) {
      OnboardingUsernameAvailabilityStatus.available => AppColors.onlineGreen,
      OnboardingUsernameAvailabilityStatus.unavailable => AppColors.coral,
      OnboardingUsernameAvailabilityStatus.error => AppColors.grayBorder,
      _ => _focused ? AppColors.indigo : const Color(0x00000000),
    };
  }

  Color _messageColor() {
    return switch (_status) {
      OnboardingUsernameAvailabilityStatus.available => AppColors.onlineGreen,
      OnboardingUsernameAvailabilityStatus.unavailable => AppColors.coral,
      OnboardingUsernameAvailabilityStatus.error => AppColors.gray,
      OnboardingUsernameAvailabilityStatus.checking => AppColors.gray,
      OnboardingUsernameAvailabilityStatus.initial => AppColors.gray,
    };
  }

  @override
  Widget build(BuildContext context) {
    final suffix = _buildSuffix();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'KULLANICI ADI',
          style: TextStyle(
            fontFamily: AppFont.family,
            fontWeight: FontWeight.w600,
            fontSize: 12,
            letterSpacing: 1.0,
            color: AppColors.gray,
          ),
        ),
        const SizedBox(height: 8),
        AnimatedContainer(
          duration: const Duration(milliseconds: 200),
          curve: Curves.easeOut,
          height: 54,
          decoration: BoxDecoration(
            color: AppColors.grayField,
            borderRadius: BorderRadius.circular(AppRadius.field),
            border: Border.all(color: _borderColor(), width: 1.6),
          ),
          child: CupertinoTextField(
            controller: _controller,
            focusNode: _focusNode,
            onChanged: (value) {
              final username = _normalizeUsername(value);
              widget.onChanged(username);
              _scheduleAvailabilityCheck(value);
            },
            textCapitalization: TextCapitalization.none,
            placeholder: 'Kullanici adi belirle...',
            placeholderStyle: const TextStyle(
              fontFamily: AppFont.family,
              color: AppColors.gray,
              fontSize: 16,
              fontWeight: FontWeight.w500,
            ),
            style: const TextStyle(
              fontFamily: AppFont.family,
              color: AppColors.black,
              fontSize: 16,
              fontWeight: FontWeight.w600,
            ),
            autocorrect: false,
            cursorColor: AppColors.indigo,
            decoration: const BoxDecoration(color: Color(0x00000000)),
            padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 16),
            suffix: suffix == null
                ? null
                : Padding(
                    padding: const EdgeInsets.only(right: 14),
                    child: suffix,
                  ),
            suffixMode: OverlayVisibilityMode.always,
          ),
        ),
        if (_statusMessage != null) ...[
          const SizedBox(height: 8),
          Text(
            _statusMessage!,
            style: TextStyle(
              fontFamily: AppFont.family,
              fontSize: 12,
              color: _messageColor(),
            ),
          ),
        ],
      ],
    );
  }
}

class OnboardingYearField extends StatelessWidget {
  final int? value;
  final ValueChanged<int> onChanged;

  const OnboardingYearField({
    super.key,
    required this.value,
    required this.onChanged,
  });

  Future<void> _openPicker(BuildContext context) async {
    FocusScope.of(context).unfocus();
    final now = DateTime.now().year;
    final years = List<int>.generate(now - 1940 + 1, (i) => now - i);
    int selected = value ?? (now - 25);
    final initialIndex = years.indexOf(selected).clamp(0, years.length - 1);

    await showCupertinoModalPopup<void>(
      context: context,
      builder: (ctx) {
        final sheetHeight = (MediaQuery.sizeOf(ctx).height * 0.48)
            .clamp(280.0, 340.0)
            .toDouble();

        return Container(
          height: sheetHeight,
          decoration: const BoxDecoration(
            color: AppColors.white,
            borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
          ),
          child: SafeArea(
            top: false,
            child: Column(
              children: [
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 10, 16, 4),
                  child: Row(
                    children: [
                      CupertinoButton(
                        padding: EdgeInsets.zero,
                        onPressed: () => Navigator.of(ctx).pop(),
                        child: const Text(
                          'Vazgec',
                          style: TextStyle(
                            fontFamily: AppFont.family,
                            color: AppColors.gray,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ),
                      const Expanded(
                        child: Text(
                          'Dogum Yili',
                          textAlign: TextAlign.center,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            fontFamily: AppFont.family,
                            fontWeight: FontWeight.w700,
                            fontSize: 16,
                            color: AppColors.black,
                          ),
                        ),
                      ),
                      CupertinoButton(
                        padding: EdgeInsets.zero,
                        onPressed: () {
                          onChanged(selected);
                          Navigator.of(ctx).pop();
                        },
                        child: const Text(
                          'Tamam',
                          style: TextStyle(
                            fontFamily: AppFont.family,
                            color: AppColors.indigo,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(
                  height: 1,
                  width: double.infinity,
                  child: ColoredBox(color: AppColors.grayField),
                ),
                Expanded(
                  child: CupertinoPicker(
                    scrollController: FixedExtentScrollController(
                      initialItem: initialIndex,
                    ),
                    itemExtent: 40,
                    squeeze: 1.1,
                    useMagnifier: true,
                    magnification: 1.05,
                    onSelectedItemChanged: (index) {
                      selected = years[index];
                    },
                    children: [
                      for (final year in years)
                        Center(
                          child: Text(
                            '$year',
                            style: const TextStyle(
                              fontFamily: AppFont.family,
                              fontSize: 20,
                              fontWeight: FontWeight.w600,
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
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () => _openPicker(context),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'DOGUM YILI',
            style: TextStyle(
              fontFamily: AppFont.family,
              fontWeight: FontWeight.w600,
              fontSize: 12,
              letterSpacing: 1.0,
              color: AppColors.gray,
            ),
          ),
          const SizedBox(height: 8),
          Container(
            height: 54,
            padding: const EdgeInsets.symmetric(horizontal: 18),
            decoration: BoxDecoration(
              color: AppColors.grayField,
              borderRadius: BorderRadius.circular(AppRadius.field),
            ),
            child: Row(
              children: [
                Expanded(
                  child: Text(
                    value?.toString() ?? 'Yil sec...',
                    style: TextStyle(
                      fontFamily: AppFont.family,
                      color: value == null ? AppColors.gray : AppColors.black,
                      fontSize: 16,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
                const Icon(
                  CupertinoIcons.chevron_down,
                  size: 18,
                  color: AppColors.gray,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
