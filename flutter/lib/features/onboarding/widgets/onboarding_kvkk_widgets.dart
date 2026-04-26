import 'package:magmug/app_core.dart';

class OnboardingKvkkView extends ConsumerWidget {
  final VoidCallback onBack;

  const OnboardingKvkkView({super.key, required this.onBack});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final legalTexts = ref.watch(appLegalTextsProvider).asData?.value;
    final kvkk = legalTexts?.kvkk;
    final title = kvkk?.title.trim().isNotEmpty == true
        ? kvkk!.title
        : AppRuntimeText.instance.t('profileKvkk', 'KVKK Aydinlatma Metni');
    final content = kvkk?.content.trim();
    final body = content != null && content.isNotEmpty
        ? content
        : AppRuntimeText.instance.t(
            'legalContentUnavailable',
            'Icerik su anda goruntulenemiyor.',
          );

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
                  CircleBackButton(filled: true, onTap: onBack),
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
                    OnboardingKvkkSection(body: body),
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

class OnboardingKvkkSection extends StatelessWidget {
  final String title;
  final String body;

  const OnboardingKvkkSection({super.key, this.title = '', required this.body});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 18),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (title.trim().isNotEmpty) ...[
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
          ],
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
