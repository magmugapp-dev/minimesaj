// Eklentinin ortak ayar, secici, metin ve calisma durumunu tek yerde toplar.
(() => {
    const kapsam = globalThis.MiniMesaj || (globalThis.MiniMesaj = {});

    kapsam.varsayilanDurum = {
        sistemEtkin: false
    };

    // ── API & WebSocket Yapilandirmasi ───────────────────────────────
    kapsam.apiYapilandirma = {
        temelUrl: 'https://minimesaj.test/api',
        yayinDogrulamaUrl: 'https://minimesaj.test/broadcasting/auth',
        reverbAnahtari: 'minimesaj-key',
        reverbSunucu: 'localhost',
        reverbPort: 8080,
        reverbSema: 'http'
    };

    kapsam.sabitler = {
        cevapMetni: "Merhaba, mesajiniz icin tesekkurler. En kisa surede donus yapacagim.",
        mutasyonBeklemeSuresiMs: 350,
        bildirimTekrarBeklemeMs: 2500,
        sohbetYenidenDenemeBeklemeMs: 15000,
        mutasyonYoksayBeklemeMs: 3000,
        guvenlikTaramaAraligiMs: 20000,
        gozlemciSaglikBeklemeMs: 8000,
        apiSenkronAraligiMs: 30000,
        gidenKuyrukAraligiMs: 15000,
        gidenKuyrukHizliAraligiMs: 2500,
        aiCevapBeklemeZamanAsimi: 120000, // 120sn sonra AI cevabi gelmezse fallback
        // Insan benzeri yazma hizi sabitleri
        harfGecikmeMinMs: 30,
        harfGecikmeMaxMs: 120,
        noktalamaGecikmeMinMs: 150,
        noktalamaGecikmeMaxMs: 400,
        kelimeArasiGecikmeMinMs: 80,
        kelimeArasiGecikmeMaxMs: 200
    };

    kapsam.seciciler = {
        sohbetSatirlari: 'div[role="button"][tabindex="0"]',
        baslik: 'span[title]',
        avatar: 'img[alt="user-profile-picture"], img[alt*="profile picture"]',
        zaman: 'abbr[aria-label], time',
        mesajKutusu: [
            'div[contenteditable="true"][role="textbox"]',
            'div[contenteditable="true"][aria-label]',
            'div[contenteditable="true"]',
            'textarea',
            'input[type="text"]'
        ],
        butonlar: 'div[role="button"], button'
    };

    kapsam.metinler = {
        sistemOnizlemeMetinleri: [
            'aktif',
            'active',
            'unread',
            'okunmadi',
            'seen',
            'goruldu',
            'yaziyor',
            'typing',
            'mesaj gonder',
            'message',
            'gonder'
        ],
        gidenMesajOnEkleri: ['sen:', 'you:'],
        yaziyorIpuclari: ['yaziyor', 'typing'],
        okunmamisIpuclari: [
            'unread',
            'okunmadi',
            'new message',
            'new messages',
            'yeni mesaj',
            'yeni mesajlar'
        ],
        bugunIpuclari: [
            'bugun',
            'today',
            'az once',
            'just now',
            'simdi',
            'now',
            'dakika',
            'minute',
            'minutes',
            'saat',
            'hour',
            'hours',
            'sn',
            'sec',
            'dk',
            'min',
            'saniye',
            'second',
            'seconds',
            'sa',
            'hr',
            'hrs'
        ],
        kabulButonuIpuclari: [
            'accept',
            'kabul',
            'kabul et',
            'primary',
            'birincil',
            'one tasi',
            'one al',
            'move to primary'
        ]
    };

    kapsam.durum = kapsam.durum || {
        gecerliDurum: { sistemEtkin: false },
        sohbetGozlemcisi: null,
        mutasyonZamanlayicisi: null,
        guvenlikTaramaZamanlayicisi: null,
        mutasyonIsleniyor: false,
        taramaIstegiPlanlandi: false,
        bekleyenTaramaNedeni: null,
        sonYol: location.pathname,
        sonTaramaZamani: 0,
        sonGozlemciEtkinlikZamani: 0,
        gozlemciBaglantiZamani: 0,
        sekmeGizlendiZamani: 0,
        bekleyenSohbetKuyrugu: [],
        kuyruktakiSohbetKimlikleri: new Set(),
        cevaplananSohbetImzalari: new Map(),
        islemdekiSohbetKimlikleri: new Set(),
        basarisizSohbetZamanlari: new Map(),
        sonBildirimZamanlari: new Map(),
        alertifyHazirlandi: false,
        baslatildi: false,
        mutasyonlariYoksay: false,
        // API & WebSocket durumu
        apiJetonu: null,
        aktifHesapId: null,
        aktifHesapKullaniciAdi: null,
        websocketBagli: false,
        sonSenkronZamani: 0,
        bekleyenAiCevaplari: [],
        islenmisAiMesajIdleri: new Set(), // gonderildi olarak isaretlenen mesaj ID'leri
        aiCevapBekleyenSohbetler: new Map(), // sohbetKimligi -> { baslamaZamani, olayImzasi }
        senkronizasyonZamanlayicisi: null,
        gidenKuyrukZamanlayicisi: null,
        gidenKuyrukHizliZamanlayicisi: null
    };
})();
