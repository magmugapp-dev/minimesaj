import 'package:magmug/app_core.dart';
import 'package:magmug/app_push_bootstrap.dart';
import 'package:magmug/features/home/home_flow.dart';
import 'package:magmug/features/onboarding/onboarding_flow.dart';

class AppBootstrapScreen extends ConsumerWidget {
  const AppBootstrapScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final authState = ref.watch(appAuthProvider);

    return PushBootstrap(
      child: authState.when(
        loading: () => const CupertinoPageScaffold(
          backgroundColor: AppColors.white,
          child: Center(child: CupertinoActivityIndicator(radius: 16)),
        ),
        error: (_, _) => const OnboardScreen(),
        data: (session) {
          if (session?.token.trim().isNotEmpty == true) {
            return const HomeScreen(mode: HomeMode.list);
          }
          return const OnboardScreen();
        },
      ),
    );
  }
}
