// Laravel backend ile REST API iletisimini yoneten servisi tanimlar.
(() => {
    const kapsam = globalThis.MiniMesaj;

    if (!kapsam?.domYardimcilari || !kapsam?.bildirimServisi) {
        return;
    }

    const { gunlukYaz } = kapsam.domYardimcilari;
    const { apiYapilandirma, durum } = kapsam;

    async function jetonuYukle() {
        const veri = await chrome.storage.local.get(['apiJetonu', 'aktifHesapId', 'aktifHesapKullaniciAdi']);
        durum.apiJetonu = veri.apiJetonu || null;
        durum.aktifHesapId = veri.aktifHesapId || null;
        durum.aktifHesapKullaniciAdi = veri.aktifHesapKullaniciAdi || null;
        return durum.apiJetonu;
    }

    async function jetonuKaydet(jeton) {
        durum.apiJetonu = jeton;
        await chrome.storage.local.set({ apiJetonu: jeton });
    }

    async function hesapBilgisiniKaydet(hesapId, kullaniciAdi) {
        durum.aktifHesapId = hesapId;
        durum.aktifHesapKullaniciAdi = kullaniciAdi;
        await chrome.storage.local.set({
            aktifHesapId: hesapId,
            aktifHesapKullaniciAdi: kullaniciAdi
        });
    }

    async function oturumuTemizle() {
        durum.apiJetonu = null;
        durum.aktifHesapId = null;
        durum.aktifHesapKullaniciAdi = null;
        await chrome.storage.local.remove(['apiJetonu', 'aktifHesapId', 'aktifHesapKullaniciAdi']);
    }

    function girisYapildiMi() {
        return Boolean(durum.apiJetonu);
    }

    function hesapBagliMi() {
        return Boolean(durum.aktifHesapId);
    }

    async function istek(yol, secenekler = {}) {
        const { yontem = 'GET', govde = null, jetonZorunlu = true } = secenekler;

        if (jetonZorunlu && !durum.apiJetonu) {
            gunlukYaz('API jetonu bulunamadi', yol);
            return { basarili: false, hata: 'Oturum acilmamis.' };
        }

        const basliklar = {
            Accept: 'application/json',
            'Content-Type': 'application/json'
        };

        if (durum.apiJetonu) {
            basliklar.Authorization = `Bearer ${durum.apiJetonu}`;
        }

        try {
            const yanit = await fetch(`${apiYapilandirma.temelUrl}${yol}`, {
                method: yontem,
                headers: basliklar,
                body: govde ? JSON.stringify(govde) : null
            });

            const veri = await yanit.json().catch(() => null);

            if (yanit.status === 401) {
                gunlukYaz('Oturum suresi doldu, jeton temizleniyor');
                await oturumuTemizle();
                return { basarili: false, hata: 'Oturum suresi doldu.', durum: 401 };
            }

            if (!yanit.ok) {
                const hataMesaji = veri?.message || veri?.mesaj || `HTTP ${yanit.status}`;
                gunlukYaz('API hatasi', { yol, durum: yanit.status, hata: hataMesaji });
                return { basarili: false, hata: hataMesaji, durum: yanit.status, veri };
            }

            return { basarili: true, veri, durum: yanit.status };
        } catch (yakalananHata) {
            gunlukYaz('API istegi basarisiz', { yol, hata: yakalananHata.message });
            return { basarili: false, hata: yakalananHata.message };
        }
    }

    async function girisYap(kullaniciAdi, sifre) {
        const sonuc = await istek('/auth/giris', {
            yontem: 'POST',
            govde: {
                kullanici_adi: kullaniciAdi,
                password: sifre,
                istemci_tipi: 'extension'
            },
            jetonZorunlu: false
        });

        if (sonuc.basarili && sonuc.veri?.token) {
            await jetonuKaydet(sonuc.veri.token);
            gunlukYaz('Giris basarili', sonuc.veri.kullanici?.kullanici_adi);
        }

        return sonuc;
    }

    async function cikisYap() {
        const sonuc = await istek('/auth/cikis', { yontem: 'POST' });
        await oturumuTemizle();
        return sonuc;
    }

    async function beniBul() {
        return istek('/auth/ben');
    }

    async function hesaplariListele() {
        return istek('/instagram/hesaplar');
    }

    async function hesapBagla(instagramKullaniciAdi) {
        const sonuc = await istek('/instagram/hesaplar', {
            yontem: 'POST',
            govde: { instagram_kullanici_adi: instagramKullaniciAdi }
        });

        if (sonuc.basarili && sonuc.veri?.id) {
            await hesapBilgisiniKaydet(sonuc.veri.id, instagramKullaniciAdi);
        }

        return sonuc;
    }

    async function hesapKaldir(hesapId) {
        const sonuc = await istek(`/instagram/hesaplar/${hesapId}`, { yontem: 'DELETE' });

        if (sonuc.basarili) {
            await hesapBilgisiniKaydet(null, null);
        }

        return sonuc;
    }

    async function kisileriSenkronize(kisiler) {
        if (!durum.aktifHesapId || !kisiler?.length) {
            return { basarili: false, hata: 'Hesap bagli degil veya kisi listesi bos.' };
        }

        return istek(`/instagram/hesaplar/${durum.aktifHesapId}/kisiler/senkronize`, {
            yontem: 'POST',
            govde: { kisiler }
        });
    }

    async function mesajlariGonder(mesajlar) {
        if (!durum.aktifHesapId || !mesajlar?.length) {
            return { basarili: false, hata: 'Hesap bagli degil veya mesaj listesi bos.' };
        }

        return istek(`/instagram/hesaplar/${durum.aktifHesapId}/mesajlar`, {
            yontem: 'POST',
            govde: { mesajlar }
        });
    }

    async function gidenKuyruguGetir() {
        if (!durum.aktifHesapId) {
            return { basarili: false, hata: 'Hesap bagli degil.' };
        }

        return istek(`/instagram/hesaplar/${durum.aktifHesapId}/giden-kuyruk`);
    }

    async function gonderildiIsaretle(mesajId) {
        return istek(`/instagram/mesajlar/${mesajId}/gonderildi`, { yontem: 'PATCH' });
    }

    function hizliGidenKuyruguTakibiGerekliMi() {
        return girisYapildiMi()
            && hesapBagliMi()
            && !durum.websocketBagli
            && durum.aiCevapBekleyenSohbetler.size > 0;
    }

    function hizliGidenKuyruguTakibiniDurdur() {
        if (durum.gidenKuyrukHizliZamanlayicisi !== null) {
            window.clearTimeout(durum.gidenKuyrukHizliZamanlayicisi);
            durum.gidenKuyrukHizliZamanlayicisi = null;
        }
    }

    function hizliGidenKuyruguTakibiniPlanla() {
        if (!hizliGidenKuyruguTakibiGerekliMi() || durum.gidenKuyrukHizliZamanlayicisi !== null) {
            return;
        }

        durum.gidenKuyrukHizliZamanlayicisi = window.setTimeout(() => {
            durum.gidenKuyrukHizliZamanlayicisi = null;
            gidenKuyruguKontrolEt({ kaynak: 'hizli-takip' });
        }, kapsam.sabitler.gidenKuyrukHizliAraligiMs);
    }

    function aiTeslimatiniHemenTetikle(kaynak = 'ai-cevabi-hazir') {
        if (kapsam.gozlemciServisi?.taramayiPlanla) {
            kapsam.gozlemciServisi.taramayiPlanla(kaynak);
            return;
        }

        window.dispatchEvent(new CustomEvent('minimesaj:ai-cevabi-hazir', {
            detail: { kaynak }
        }));
    }

    function senkronizasyonuBaslat() {
        senkronizasyonuDurdur();

        if (!girisYapildiMi() || !hesapBagliMi()) {
            return;
        }

        gidenKuyruguKontrolEt({ kaynak: 'baslangic' });

        durum.gidenKuyrukZamanlayicisi = window.setInterval(() => {
            gidenKuyruguKontrolEt();
        }, kapsam.sabitler.gidenKuyrukAraligiMs);
    }

    function senkronizasyonuDurdur() {
        if (durum.gidenKuyrukZamanlayicisi !== null) {
            window.clearInterval(durum.gidenKuyrukZamanlayicisi);
            durum.gidenKuyrukZamanlayicisi = null;
        }

        hizliGidenKuyruguTakibiniDurdur();

        if (durum.senkronizasyonZamanlayicisi !== null) {
            window.clearInterval(durum.senkronizasyonZamanlayicisi);
            durum.senkronizasyonZamanlayicisi = null;
        }
    }

    async function gidenKuyruguKontrolEt(_secenekler = {}) {
        if (!girisYapildiMi() || !hesapBagliMi()) {
            return;
        }

        const sonuc = await gidenKuyruguGetir();
        console.log('[MiniMesaj][DEBUG] AI giden kuyruk sonucu:', sonuc);

        if (!sonuc.basarili) {
            console.warn('[MiniMesaj][DEBUG] AI giden kuyruk basarisiz:', sonuc);
            hizliGidenKuyruguTakibiniPlanla();
            return;
        }

        const mesajlar = Array.isArray(sonuc.veri) ? sonuc.veri : (sonuc.veri?.data || []);
        console.log('[MiniMesaj][DEBUG] AI giden kuyruk mesajlar:', mesajlar);

        if (mesajlar.length === 0) {
            console.log('[MiniMesaj][DEBUG] AI giden kuyrukta mesaj yok.');
            hizliGidenKuyruguTakibiniPlanla();
            return;
        }

        mesajlar.forEach((mesaj) => {
            aiCevabiniKuyrugaEkle(mesaj);
        });

        hizliGidenKuyruguTakibiniPlanla();
    }

    function aiCevabiniKuyrugaEkle(mesaj) {
        console.log('[MiniMesaj][DEBUG] AI cevabi kuyruga ekleniyor:', mesaj);

        if (!mesaj?.mesaj_metni || !mesaj?.instagram_kisi_id) {
            console.warn('[MiniMesaj][DEBUG] AI cevabi kuyruga eklenemedi, eksik alan:', mesaj);
            return;
        }

        const mesajId = mesaj.mesaj_id || mesaj.id;
        const kisiAnahtari = aiCevapKisiAnahtariOlustur(mesaj);

        if (durum.islenmisAiMesajIdleri.has(mesajId)) {
            return;
        }

        durum.bekleyenAiCevaplari = durum.bekleyenAiCevaplari.filter((bekleyen) => {
            if (bekleyen.mesaj_id === mesajId) {
                return false;
            }

            if (!kisiAnahtari) {
                return true;
            }

            return aiCevapKisiAnahtariOlustur(bekleyen) !== kisiAnahtari;
        });

        durum.bekleyenAiCevaplari.push({
            mesaj_id: mesajId,
            instagram_kisi_id: mesaj.instagram_kisi_id,
            mesaj_metni: mesaj.mesaj_metni,
            kisi_kodu: mesaj.kisi_kodu || mesaj.kisi?.instagram_kisi_id || null,
            kisi: mesaj.kisi || null
        });
        durum.bekleyenAiCevaplari.sort((sol, sag) => (Number(sag.mesaj_id) || 0) - (Number(sol.mesaj_id) || 0));

        gunlukYaz('AI cevabi kuyruga eklendi', {
            mesaj_id: mesajId,
            kisi: mesaj.kisi?.kullanici_adi || mesaj.instagram_kisi_id
        });

        aiTeslimatiniHemenTetikle();
    }

    function sohbetIcinAiCevabiBul(sohbetKimligi) {
        if (!durum.bekleyenAiCevaplari.length) {
            return null;
        }

        const eslesenler = durum.bekleyenAiCevaplari
            .filter((cevap) => aiCevapSohbeteAitMi(cevap, sohbetKimligi))
            .sort((sol, sag) => (Number(sag.mesaj_id) || 0) - (Number(sol.mesaj_id) || 0));

        return eslesenler[0] || null;
    }

    function aiCevabiniKaldir(mesajId) {
        durum.islenmisAiMesajIdleri.add(mesajId);
        durum.bekleyenAiCevaplari = durum.bekleyenAiCevaplari.filter(
            (cevap) => cevap.mesaj_id !== mesajId
        );
    }

    function bekleyenAiCevaplariniSohbettenTemizle(sohbetKimligi) {
        if (!sohbetKimligi) {
            return;
        }

        durum.bekleyenAiCevaplari = durum.bekleyenAiCevaplari.filter(
            (cevap) => !aiCevapSohbeteAitMi(cevap, sohbetKimligi)
        );
        durum.aiCevapBekleyenSohbetler.delete(sohbetKimligi);
    }

    function aiCevapKisiAnahtariOlustur(mesaj) {
        return mesaj?.kisi_kodu || mesaj?.kisi?.instagram_kisi_id || mesaj?.instagram_kisi_id || null;
    }

    function aiCevapSohbeteAitMi(cevap, sohbetKimligi) {
        if (cevap.kisi_kodu && cevap.kisi_kodu === sohbetKimligi) {
            return true;
        }

        const kisiAdi = cevap.kisi?.kullanici_adi || cevap.kisi?.gorunen_ad || '';

        if (!kisiAdi) {
            return false;
        }

        return sohbetKimligi.includes(kisiAdi);
    }

    async function yayinDogrula(kanalAdi, soketKimligi) {
        if (!durum.apiJetonu) {
            return null;
        }

        try {
            const yanit = await fetch(apiYapilandirma.yayinDogrulamaUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    Authorization: `Bearer ${durum.apiJetonu}`
                },
                body: JSON.stringify({
                    socket_id: soketKimligi,
                    channel_name: kanalAdi
                })
            });

            if (!yanit.ok) {
                gunlukYaz('Yayin dogrulama basarisiz', { durum: yanit.status, kanal: kanalAdi });
                return null;
            }

            return yanit.json();
        } catch (yakalananHata) {
            gunlukYaz('Yayin dogrulama hatasi', yakalananHata.message);
            return null;
        }
    }

    kapsam.apiServisi = {
        jetonuYukle,
        jetonuKaydet,
        oturumuTemizle,
        girisYapildiMi,
        hesapBagliMi,
        girisYap,
        cikisYap,
        beniBul,
        hesaplariListele,
        hesapBagla,
        hesapKaldir,
        hesapBilgisiniKaydet,
        kisileriSenkronize,
        mesajlariGonder,
        gidenKuyruguGetir,
        gonderildiIsaretle,
        senkronizasyonuBaslat,
        senkronizasyonuDurdur,
        gidenKuyruguKontrolEt,
        hizliGidenKuyruguTakibiniPlanla,
        aiCevabiniKuyrugaEkle,
        sohbetIcinAiCevabiBul,
        aiCevabiniKaldir,
        bekleyenAiCevaplariniSohbettenTemizle,
        yayinDogrula
    };
})();
