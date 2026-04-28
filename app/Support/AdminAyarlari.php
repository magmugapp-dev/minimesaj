<?php

namespace App\Support;

class AdminAyarlari
{
    public static function kategoriler(): array
    {
        return [
            'genel' => [
                'etiket' => 'Genel',
                'aciklama' => 'Uygulama kimliği, sürüm, dil, logo ve destek bilgilerini bu bölümden yönet.',
                'sidebar_grup' => 'Uygulama',
                'tam_genislik' => true,
                'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12a7.5 7.5 0 1115 0 7.5 7.5 0 01-15 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 8.25v4.5l3 1.5"/>',
            ],
            'premium' => [
                'etiket' => 'Premium',
                'aciklama' => 'Premium planların fiyat, hak ve görünür özellik ayarları burada durur.',
                'sidebar_grup' => 'Uygulama',
                'tam_genislik' => false,
                'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 3l1.912 3.873 4.274.621-3.093 3.015.73 4.257L12 12.75l-3.823 2.016.73-4.257L5.814 7.494l4.274-.621L12 3z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 19.5h10.5"/>',
            ],
            'google_auth' => [
                'etiket' => 'Google Giriş',
                'aciklama' => 'Google ile giriş için mobil ve sunucu istemci kimliklerini bu bölümde tut.',
                'sidebar_grup' => 'Uygulama',
                'tam_genislik' => false,
                'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5A7.5 7.5 0 104.5 12h7.5v-3h-4.2"/><path stroke-linecap="round" stroke-linejoin="round" d="M21 12c0-4.97-4.03-9-9-9"/>',
            ],
            'google_play' => [
                'etiket' => 'Google Play',
                'aciklama' => 'Play Store, paket adı, servis hesabı ve mağaza bağlantılarını bu bölümde topla.',
                'sidebar_grup' => 'Uygulama',
                'tam_genislik' => false,
                'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M6.75 4.5l10.5 7.5-10.5 7.5v-15z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 4.5l7.5 7.5-7.5 7.5"/>',
            ],
            'admob' => [
                'etiket' => 'AdMob',
                'aciklama' => 'Google AdMob app kimlikleri ve reklam birimi ayarları.',
                'sidebar_grup' => 'Uygulama',
                'tam_genislik' => false,
                'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M4.5 6.75h15v10.5h-15V6.75z"/><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 10.5h7.5M8.25 13.5h4.5"/><path stroke-linecap="round" stroke-linejoin="round" d="M17.25 4.5v15"/>',
            ],
            'yasal' => [
                'etiket' => 'Yasal Metinler',
                'aciklama' => 'Gizlilik politikasi, KVKK aydinlatma metni ve kullanim kosullari buradan duzenlenir.',
                'sidebar_grup' => 'Uygulama',
                'tam_genislik' => true,
                'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3.75h7.5l3 3v13.5H6.75V3.75z"/><path stroke-linecap="round" stroke-linejoin="round" d="M14.25 3.75v3h3M9 11.25h6M9 14.25h6M9 17.25h3"/>',
            ],
            'apple' => [
                'etiket' => 'Apple',
                'aciklama' => 'Apple giriş ve App Store bağlantılı kimlik bilgilerini bu bölümden yönet.',
                'sidebar_grup' => 'Uygulama',
                'tam_genislik' => false,
                'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M14.25 6.75c-.75-.75-1.5-1.125-2.25-1.125-1.5 0-2.25 1.5-3.375 1.5-.75 0-1.5-.375-2.625-1.125C4.875 7.125 4.5 8.625 4.5 9.75c0 3.75 2.625 8.25 5.25 8.25.75 0 1.125-.375 2.25-.375 1.125 0 1.5.375 2.25.375 2.25 0 4.875-4.125 4.875-7.5 0-1.875-.75-3-1.5-3.75-.75-.75-1.875-1.125-3.375-1.125z"/><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 3.75c0 1.5-1.125 3-2.625 3.375 0-1.875 1.125-3 2.625-3.375z"/>',
            ],
            'bildirimler' => [
                'etiket' => 'Bildirimler',
                'aciklama' => 'Firebase, APNs ve uygulama içi bildirim anahtarları burada tutulur.',
                'sidebar_grup' => 'Uygulama',
                'tam_genislik' => true,
                'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9a6 6 0 00-12 0v.75a8.967 8.967 0 01-2.311 6.022 23.848 23.848 0 005.454 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/>',
            ],
            'ai_saglayicilar' => [
                'etiket' => 'AI Sağlayıcılar',
                'aciklama' => 'AI sağlayıcılarına ait API anahtarları ve varsayılan modeller.',
                'sidebar_grup' => 'Operasyon',
                'tam_genislik' => false,
                'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/>',
            ],
            'puan_sistemi' => [
                'etiket' => 'Puan Sistemi',
                'aciklama' => 'Uygulama içi puan ekonomisinin tüm maliyet ve ödül ayarları.',
                'sidebar_grup' => 'Operasyon',
                'tam_genislik' => false,
                'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-4.5-9.75h9a2.25 2.25 0 010 4.5h-9a2.25 2.25 0 000 4.5h9"/>',
            ],
            'limitler' => [
                'etiket' => 'Limitler',
                'aciklama' => 'Kullanıcı ve içerik limitleri ile günlük kullanım sınırları.',
                'sidebar_grup' => 'Operasyon',
                'tam_genislik' => false,
                'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5M12 3.75v16.5"/>',
            ],
            'moderasyon' => [
                'etiket' => 'Moderasyon',
                'aciklama' => 'İçerik filtreleme, otomatik işlem eşikleri ve güvenli kullanım kuralları.',
                'sidebar_grup' => 'Operasyon',
                'tam_genislik' => false,
                'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>',
            ],
            'eslestirme' => [
                'etiket' => 'Eşleştirme',
                'aciklama' => 'Eşleştirme motoru ve görünürlük parametreleri.',
                'sidebar_grup' => 'Operasyon',
                'tam_genislik' => false,
                'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/>',
            ],
            'eposta' => [
                'etiket' => 'E-posta',
                'aciklama' => 'SMTP ve gönderici bilgileri ile mail teslim ayarları.',
                'sidebar_grup' => 'Altyapı',
                'tam_genislik' => false,
                'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25H4.5a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5H4.5a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-.987 1.864l-7.5 5.25a2.25 2.25 0 01-2.526 0l-7.5-5.25A2.25 2.25 0 012.25 6.993V6.75"/>',
            ],
            'guvenlik' => [
                'etiket' => 'Güvenlik',
                'aciklama' => 'Kimlik doğrulama, kilitleme ve güvenlik kontrol ayarları.',
                'sidebar_grup' => 'Altyapı',
                'tam_genislik' => false,
                'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-1.5 0h12a2.25 2.25 0 012.25 2.25v5.25A2.25 2.25 0 0118 20.25H6A2.25 2.25 0 013.75 18v-5.25A2.25 2.25 0 016 10.5z"/>',
            ],
            'depolama' => [
                'etiket' => 'Depolama',
                'aciklama' => 'Dosya depolama sürücüsü ve medya saklama yapılandırmaları.',
                'sidebar_grup' => 'Altyapı',
                'tam_genislik' => false,
                'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5v10.5H3.75V6.75z"/><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 10.5h9"/><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 13.5h6"/>',
            ],
            'websocket' => [
                'etiket' => 'WebSocket',
                'aciklama' => 'Gerçek zamanlı bağlantı ve Reverb yapılandırmaları.',
                'sidebar_grup' => 'Altyapı',
                'tam_genislik' => false,
                'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9.75h7.5m-7.5 4.5h7.5M4.5 6.75h15a2.25 2.25 0 012.25 2.25v6a2.25 2.25 0 01-2.25 2.25h-15A2.25 2.25 0 012.25 15v-6A2.25 2.25 0 014.5 6.75z"/>',
            ],
            'rate_limiting' => [
                'etiket' => 'Rate Limiting',
                'aciklama' => 'İstemci ve servis bazlı istek sınırları.',
                'sidebar_grup' => 'Altyapı',
                'tam_genislik' => false,
                'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4.5 2.25"/><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
            ],
        ];
    }

    public static function kategori(string $kategori): ?array
    {
        $veri = self::kategoriler()[$kategori] ?? null;

        if ($veri === null) {
            return null;
        }

        return array_merge($veri, self::tema($veri['sidebar_grup']), [
            'slug' => $kategori,
        ]);
    }

    public static function kategorilerSidebarGruplu(): array
    {
        $gruplu = [];

        foreach (self::kategoriler() as $slug => $kategori) {
            $gruplu[$kategori['sidebar_grup']][] = [
                'slug' => $slug,
                'etiket' => $kategori['etiket'],
                'svg' => $kategori['svg'],
            ];
        }

        return $gruplu;
    }

    private static function tema(string $sidebarGrup): array
    {
        return match ($sidebarGrup) {
            'Uygulama' => [
                'kicker' => 'App Control',
                'accent' => '#4f46e5',
                'accent_rgb' => '79, 70, 229',
                'accent_soft' => '#eef2ff',
                'hero_from' => '#020617',
                'hero_via' => '#312e81',
                'hero_to' => '#0f172a',
            ],
            'Operasyon' => [
                'kicker' => 'Growth Ops',
                'accent' => '#0f766e',
                'accent_rgb' => '15, 118, 110',
                'accent_soft' => '#ecfeff',
                'hero_from' => '#022c22',
                'hero_via' => '#134e4a',
                'hero_to' => '#0f172a',
            ],
            'Altyapı' => [
                'kicker' => 'Infra Stack',
                'accent' => '#0369a1',
                'accent_rgb' => '3, 105, 161',
                'accent_soft' => '#eff6ff',
                'hero_from' => '#082f49',
                'hero_via' => '#0f3b66',
                'hero_to' => '#0f172a',
            ],
            'Entegrasyon' => [
                'kicker' => 'Connected Services',
                'accent' => '#b45309',
                'accent_rgb' => '180, 83, 9',
                'accent_soft' => '#fff7ed',
                'hero_from' => '#431407',
                'hero_via' => '#7c2d12',
                'hero_to' => '#111827',
            ],
            default => [
                'kicker' => 'Admin Control',
                'accent' => '#4f46e5',
                'accent_rgb' => '79, 70, 229',
                'accent_soft' => '#eef2ff',
                'hero_from' => '#020617',
                'hero_via' => '#312e81',
                'hero_to' => '#0f172a',
            ],
        };
    }
}
