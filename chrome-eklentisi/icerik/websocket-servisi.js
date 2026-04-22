// Reverb/Pusher WebSocket baglantisini ve kanal aboneligi yoneten servisi tanimlar.
(() => {
    const kapsam = globalThis.MiniMesaj;

    if (!kapsam?.domYardimcilari || !kapsam?.bildirimServisi || !kapsam?.apiServisi) {
        return;
    }

    const { gunlukYaz } = kapsam.domYardimcilari;
    const { bilgi, hata: hataGoster, uyari } = kapsam.bildirimServisi;
    const { yayinDogrula, aiCevabiniKuyrugaEkle } = kapsam.apiServisi;
    const { apiYapilandirma, durum } = kapsam;

    let pusherBaglantisi = null;
    let aboneOlunanKanal = null;

    function baglan() {
        kes();

        if (!durum.apiJetonu || !durum.aktifHesapId) {
            gunlukYaz('WebSocket: Jeton veya hesap ID eksik, baglanilmiyor');
            return;
        }

        if (typeof Pusher === 'undefined') {
            gunlukYaz('WebSocket: Pusher kutuphanesi yuklu degil');
            return;
        }

        try {
            pusherBaglantisi = new Pusher(apiYapilandirma.reverbAnahtari, {
                wsHost: apiYapilandirma.reverbSunucu,
                wsPort: apiYapilandirma.reverbPort,
                wssPort: apiYapilandirma.reverbPort,
                forceTLS: apiYapilandirma.reverbSema === 'https',
                enabledTransports: ['ws', 'wss'],
                disableStats: true,
                cluster: '',
                authorizer: (kanal) => ({
                    authorize: async (soketKimligi, geriCagir) => {
                        const sonuc = await yayinDogrula(kanal.name, soketKimligi);

                        if (sonuc) {
                            geriCagir(null, sonuc);
                        } else {
                            geriCagir(new Error('Yayin dogrulama basarisiz'), null);
                        }
                    }
                })
            });

            pusherBaglantisi.connection.bind('connected', () => {
                durum.websocketBagli = true;
                gunlukYaz('WebSocket baglandi');
                bilgi('Sunucu baglantisi kuruldu.', 'ws-baglandi');
                hesapKanalinaAboneOl();
            });

            pusherBaglantisi.connection.bind('disconnected', () => {
                durum.websocketBagli = false;
                gunlukYaz('WebSocket baglantisi kesildi');
            });

            pusherBaglantisi.connection.bind('error', (hata) => {
                durum.websocketBagli = false;
                gunlukYaz('WebSocket hatasi', hata);
            });

            pusherBaglantisi.connection.bind('unavailable', () => {
                durum.websocketBagli = false;
                gunlukYaz('WebSocket sunucu erisimi basarisiz, REST polling aktif');
            });
        } catch (yakalananHata) {
            gunlukYaz('WebSocket baslatilamadi', yakalananHata.message);
        }
    }

    function hesapKanalinaAboneOl() {
        if (!pusherBaglantisi || !durum.aktifHesapId) {
            return;
        }

        const kanalAdi = `private-instagram-hesap.${durum.aktifHesapId}`;
        aboneOlunanKanal = pusherBaglantisi.subscribe(kanalAdi);

        aboneOlunanKanal.bind('pusher:subscription_succeeded', () => {
            gunlukYaz('Kanala abone olundu', kanalAdi);
        });

        aboneOlunanKanal.bind('pusher:subscription_error', (hata) => {
            gunlukYaz('Kanal abonelik hatasi', { kanal: kanalAdi, hata });
            hataGoster('Kanal aboneligi basarisiz.', 'kanal-hata');
        });

        // InstagramAiCevapHazir eventini dinle
        aboneOlunanKanal.bind('instagram.ai_cevap_hazir', (veri) => {
            gunlukYaz('AI cevabi alindi (WebSocket)', veri);

            aiCevabiniKuyrugaEkle({
                id: veri.mesaj_id,
                mesaj_id: veri.mesaj_id,
                instagram_kisi_id: veri.instagram_kisi_id,
                mesaj_metni: veri.mesaj_metni,
                kisi_kodu: veri.kisi_kodu || null,
                kisi: veri.kisi || null
            });

            bilgi('AI cevabi hazir, gonderilecek.', `ai-cevap-${veri.mesaj_id}`);
        });
    }

    function kes() {
        if (aboneOlunanKanal && pusherBaglantisi) {
            try {
                pusherBaglantisi.unsubscribe(aboneOlunanKanal.name);
            } catch (_hata) {
                // Sessizce devam et
            }
            aboneOlunanKanal = null;
        }

        if (pusherBaglantisi) {
            try {
                pusherBaglantisi.disconnect();
            } catch (_hata) {
                // Sessizce devam et
            }
            pusherBaglantisi = null;
        }

        durum.websocketBagli = false;
    }

    function bagliMi() {
        return durum.websocketBagli && pusherBaglantisi !== null;
    }

    kapsam.websocketServisi = {
        baglan,
        kes,
        bagliMi
    };
})();
