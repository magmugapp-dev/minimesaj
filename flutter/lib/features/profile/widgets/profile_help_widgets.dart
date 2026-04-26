import 'package:magmug/app_core.dart';
import 'package:magmug/l10n/app_localizations.dart';
import 'package:magmug/features/profile/widgets/profile_settings_widgets.dart';
import 'package:magmug/features/profile/widgets/profile_support_widgets.dart';

class HelpSheet extends ConsumerStatefulWidget {
  const HelpSheet({super.key});

  @override
  ConsumerState<HelpSheet> createState() => _HelpSheetState();
}

class _HelpSheetState extends ConsumerState<HelpSheet> {
  bool _expanded = false;
  bool _isLaunching = false;
  String? _notice;
  late final TextEditingController _messageController;

  @override
  void initState() {
    super.initState();
    _messageController = TextEditingController();
  }

  @override
  void dispose() {
    _messageController.dispose();
    super.dispose();
  }

  Future<void> _sendSupportRequest() async {
    final l10n = AppLocalizations.of(context)!;
    if (_isLaunching) {
      return;
    }

    final message = _messageController.text.trim();
    if (message.isEmpty) {
      setState(() {
        _notice = l10n.helpWriteMessageFirst;
      });
      return;
    }

    final token = ref.read(appAuthProvider).asData?.value?.token;
    if (token == null || token.trim().isEmpty) {
      setState(() {
        _notice = l10n.helpAuthRequired;
      });
      return;
    }

    setState(() {
      _isLaunching = true;
      _notice = null;
    });

    final api = AppAuthApi();
    try {
      await api.submitSupportRequest(token, message: message);
      if (!mounted) {
        return;
      }
      await showCupertinoDialog<void>(
        context: context,
        builder: (dialogContext) => CupertinoAlertDialog(
          title: Text(l10n.helpMessageReceivedTitle),
          content: Text(l10n.helpMessageReceivedSubtitle),
          actions: [
            CupertinoDialogAction(
              onPressed: () => Navigator.of(dialogContext).pop(),
              child: Text(l10n.commonOk),
            ),
          ],
        ),
      );
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
      api.close();
      if (mounted) {
        setState(() => _isLaunching = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final appContent = ref.watch(appContentProvider).asData?.value;
    final faqItems = appContent?.faqs ?? const <AppContentFaqItem>[];
    final fallbackFaqItems = <AppContentFaqItem>[
      AppContentFaqItem(
        id: 1,
        question: l10n.helpFaqQuestion1,
        answer: l10n.helpFaqAnswer1,
      ),
      AppContentFaqItem(
        id: 2,
        question: l10n.helpFaqQuestion2,
        answer: l10n.helpFaqAnswer2,
      ),
      AppContentFaqItem(
        id: 3,
        question: l10n.helpFaqQuestion3,
        answer: l10n.helpFaqAnswer3,
      ),
    ];
    final visibleFaqItems = faqItems.isEmpty ? fallbackFaqItems : faqItems;
    final bottomInset = MediaQuery.viewInsetsOf(context).bottom;

    return Align(
      alignment: Alignment.bottomCenter,
      child: Container(
        width: double.infinity,
        decoration: const BoxDecoration(
          color: AppColors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
        ),
        child: SafeArea(
          top: false,
          child: SingleChildScrollView(
            physics: const BouncingScrollPhysics(),
            reverse: true,
            padding: EdgeInsets.fromLTRB(
              20,
              12,
              20,
              20 + MediaQuery.paddingOf(context).bottom + bottomInset,
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const ProfileSheetHandle(),
                const SizedBox(height: 18),
                Text(
                  l10n.profileHelp,
                  style: const TextStyle(
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
                        Expanded(
                          child: Text(
                            l10n.helpFaqTitle,
                            style: const TextStyle(
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
                    children: visibleFaqItems
                        .map(
                          (item) => ProfileFaqItem(
                            question: item.question,
                            answer: item.answer,
                          ),
                        )
                        .toList(growable: false),
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
                Text(
                  l10n.helpWriteUsTitle,
                  style: const TextStyle(
                    fontFamily: AppFont.family,
                    fontWeight: FontWeight.w700,
                    fontSize: 14,
                    color: AppColors.black,
                  ),
                ),
                const SizedBox(height: 8),
                Container(
                  constraints: const BoxConstraints(minHeight: 96),
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: AppColors.grayField,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: CupertinoTextField(
                    controller: _messageController,
                    maxLines: 3,
                    placeholder: l10n.helpMessagePlaceholder,
                    placeholderStyle: const TextStyle(
                      fontFamily: AppFont.family,
                      color: Color(0xFF999999),
                      fontSize: 14,
                    ),
                    style: const TextStyle(
                      fontFamily: AppFont.family,
                      fontSize: 14,
                      color: AppColors.black,
                    ),
                    decoration: const BoxDecoration(color: Color(0x00000000)),
                    padding: EdgeInsets.zero,
                  ),
                ),
                if (_notice != null) ...[
                  const SizedBox(height: 10),
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
                GradientButton(
                  label: _isLaunching ? l10n.helpSending : l10n.helpSend,
                  onTap: _isLaunching ? null : _sendSupportRequest,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
