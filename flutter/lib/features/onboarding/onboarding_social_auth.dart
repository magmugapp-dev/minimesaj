import 'package:google_sign_in/google_sign_in.dart';
import 'package:magmug/app_core.dart';
import 'package:sign_in_with_apple/sign_in_with_apple.dart';

typedef OnboardingSocialCredential = ({
  String token,
  String? displayName,
  String? firstName,
  String? lastName,
  String? avatarUrl,
});

String onboardingSocialProviderLabel(SocialAuthProvider provider) {
  return switch (provider) {
    SocialAuthProvider.google => 'Google',
    SocialAuthProvider.apple => 'Apple',
  };
}

Future<OnboardingSocialCredential> getOnboardingSocialCredential({
  required SocialAuthProvider provider,
  required GoogleSignIn googleSignIn,
}) async {
  switch (provider) {
    case SocialAuthProvider.google:
      final account = await googleSignIn.signIn();
      if (account == null) {
        throw const ApiException('Google girisi iptal edildi.');
      }

      final authentication = await account.authentication;
      final idToken = authentication.idToken;
      if (idToken == null || idToken.trim().isEmpty) {
        throw const ApiException('Google kimlik jetonu alinamadi.');
      }

      return (
        token: idToken,
        displayName: account.displayName,
        firstName: null,
        lastName: null,
        avatarUrl: account.photoUrl,
      );
    case SocialAuthProvider.apple:
      if (!await SignInWithApple.isAvailable()) {
        throw const ApiException('Bu cihazda Apple ile giris kullanilamiyor.');
      }

      final credential = await SignInWithApple.getAppleIDCredential(
        scopes: const [
          AppleIDAuthorizationScopes.email,
          AppleIDAuthorizationScopes.fullName,
        ],
      );

      if (credential.authorizationCode.trim().isEmpty) {
        throw const ApiException('Apple yetki kodu alinamadi.');
      }

      final displayName = [credential.givenName, credential.familyName]
          .whereType<String>()
          .map((part) => part.trim())
          .where((part) => part.isNotEmpty)
          .join(' ');

      return (
        token: credential.authorizationCode,
        displayName: displayName.isEmpty ? null : displayName,
        firstName: credential.givenName,
        lastName: credential.familyName,
        avatarUrl: null,
      );
  }
}
