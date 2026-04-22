import 'package:magmug/app_core.dart';

class OnboardingKvkkView extends StatelessWidget {
  final VoidCallback onBack;

  const OnboardingKvkkView({super.key, required this.onBack});

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
                  CircleBackButton(filled: true, onTap: onBack),
                  const SizedBox(width: 12),
                  const Expanded(
                    child: Text(
                      'KVKK Aydinlatma Metni',
                      style: TextStyle(
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
                  children: const [
                    OnboardingKvkkSection(
                      title: 'Veri Sorumlusu',
                      body:
                          'magmug Teknoloji A.S. olarak kisisel verilerinizin korunmasina buyuk onem veriyoruz. 6698 sayili Kisisel Verilerin Korunmasi Kanunu kapsaminda aydinlatma yukumlulugumuzu yerine getirmekteyiz.',
                    ),
                    OnboardingKvkkSection(
                      title: 'Islenen Kisisel Veriler',
                      body:
                          'Kimlik bilgileri (ad, soyad, dogum tarihi), iletisim bilgileri (e-posta, telefon), konum verileri, gorsel veriler (profil fotograflari), uygulama kullanim verileri.',
                    ),
                    OnboardingKvkkSection(
                      title: 'Isleme Amaclari',
                      body:
                          'Hizmet sunumu, kullanici deneyiminin iyilestirilmesi, yasal yukumluluklerin yerine getirilmesi, guvenligin saglanmasi.',
                    ),
                    OnboardingKvkkSection(
                      title: 'Aktarim',
                      body:
                          'Kisisel verileriniz, yasal zorunluluklar disinda yurt ici veya yurt disina aktarilmamaktadir.',
                    ),
                    OnboardingKvkkSection(
                      title: 'Haklariniz',
                      body:
                          'Kisisel verilerinizin islenip islenmedigini ogrenme, duzeltilmesini isteme, silinmesini talep etme haklarina sahipsiniz. Basvurularinizi destek sayfamiz uzerinden iletebilirsiniz.',
                    ),
                    SizedBox(height: 24),
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

  const OnboardingKvkkSection({
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
