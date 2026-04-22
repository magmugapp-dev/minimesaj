import 'package:magmug/app_core.dart';

class ProfilePolicyScaffold extends StatelessWidget {
  final String title;
  final List<({String heading, String body})> sections;

  const ProfilePolicyScaffold({
    super.key,
    required this.title,
    required this.sections,
  });

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
                    for (var index = 0; index < sections.length; index++)
                      ProfilePolicySection(
                        title: '${index + 1}. ${sections[index].heading}',
                        body: sections[index].body,
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

class ProfilePolicySection extends StatelessWidget {
  final String title;
  final String body;

  const ProfilePolicySection({
    super.key,
    required this.title,
    required this.body,
  });

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
