// Sayfa mutasyonlarini izleyen ve cevap akisini yoneten gozlemci servisini kurar.
(() => {
    const kapsam = globalThis.MiniMesaj;

    if (!kapsam?.domYardimcilari || !kapsam?.sohbetServisi || !kapsam?.mesajServisi || !kapsam?.bildirimServisi || !kapsam?.kuyrukServisi) {
        return;
    }

    const { dugumuElemanaDonustur, kucukHarfeCevir, metinListesindenBiriGeciyorMu, gunlukYaz } = kapsam.domYardimcilari;
    const { instagramMesajSayfasindaMi, okunmamisSohbetleriBul, sohbetiKimlikleBul } = kapsam.sohbetServisi;
    const { sohbetiAcVeCevapla, sohbetiAcVeAiCevabiGonder } = kapsam.mesajServisi;
    const { sohbetleriSirayaEkle, siradakiSohbetiAl, kuyruguTemizle, kuyrukUzunlugu } = kapsam.kuyrukServisi;
    const { bilgi, basari } = kapsam.bildirimServisi;
    const { durum, varsayilanDurum, sabitler, seciciler, metinler } = kapsam;

    // Sohbetin son gorulen imzasinin daha once cevaplanip cevaplanmadigini kontrol eder.
    function sohbetBuImzaylaCevaplandiMi(sohbet) {
        if (!sohbet?.sohbetKimligi || !sohbet?.olayImzasi) {
            return false;
        }

        return durum.cevaplananSohbetImzalari.get(sohbet.sohbetKimligi) === sohbet.olayImzasi;
    }

    // Sohbetin son islenen mesaj imzasini cevaplandi olarak kaydeder.
    function sohbetiCevaplandiOlarakIsaretle(sohbet) {
        if (!sohbet?.sohbetKimligi || !sohbet?.olayImzasi) {
            return;
        }

        durum.cevaplananSohbetImzalari.set(sohbet.sohbetKimligi, sohbet.olayImzasi);
    }

    // Servisi bir kez baslatir, depolama ve konum dinleyicilerini baglar.
    async function baslat() {
        if (durum.baslatildi) {
            return;
        }

        durum.baslatildi = true;
        durum.gecerliDurum = await chrome.storage.sync.get(varsayilanDurum);

        // API jetonunu yukle
        if (kapsam.apiServisi) {
            await kapsam.apiServisi.jetonuYukle();
        }

        chrome.storage.onChanged.addListener(depolamaDegisimleriniIsle);
        konumDinleyicileriniKur();
        window.addEventListener("focus", odakDegisti);
        document.addEventListener("visibilitychange", gorunurlukDegisti);
        window.addEventListener("minimesaj:ai-cevabi-hazir", aiCevabiHazirlandi);

        sistemiUygula();
    }

    function aiCevabiHazirlandi() {
        if (!durum.gecerliDurum.sistemEtkin || !instagramMesajSayfasindaMi()) {
            return;
        }

        taramayiPlanla("ai-cevabi-hazir");
    }

    // Storage degisikliklerinden sistem acik-kapali durumunu isler.
    function depolamaDegisimleriniIsle(degisiklikler, alanAdi) {
        // local storage degisiklikleri (jeton, hesap)
        if (alanAdi === 'local') {
            if (degisiklikler.apiJetonu) {
                durum.apiJetonu = degisiklikler.apiJetonu.newValue || null;
                backendBaglantisiniBaglat();
            }
            if (degisiklikler.aktifHesapId) {
                durum.aktifHesapId = degisiklikler.aktifHesapId.newValue || null;
                backendBaglantisiniBaglat();
            }
            if (degisiklikler.aktifHesapKullaniciAdi) {
                durum.aktifHesapKullaniciAdi = degisiklikler.aktifHesapKullaniciAdi.newValue || null;
            }
            return;
        }

        if (alanAdi !== "sync" || !degisiklikler.sistemEtkin) {
            return;
        }

        durum.gecerliDurum.sistemEtkin = Boolean(degisiklikler.sistemEtkin.newValue);

        if (!durum.gecerliDurum.sistemEtkin) {
            durum.cevaplananSohbetImzalari.clear();
            durum.islemdekiSohbetKimlikleri.clear();
            durum.basarisizSohbetZamanlari.clear();
            kuyruguTemizle();
            izlemeSagliginiSifirla();
            bilgi("MiniMesaj durduruldu.", "sistem-kapali");
        }

        sistemiUygula();
    }

    // SPA rota degisimlerini yakalamak icin history ve pencere dinleyicilerini kurar.
    function konumDinleyicileriniKur() {
        if (window.__miniMesajKonumYamasiKuruldu) {
            return;
        }

        window.__miniMesajKonumYamasiKuruldu = true;

        const olayYayinla = () => {
            window.dispatchEvent(new Event("minimesaj:konum-degisti"));
        };

        const pushStateAsli = history.pushState;
        const replaceStateAsli = history.replaceState;

        history.pushState = function (...parametreler) {
            const sonuc = pushStateAsli.apply(this, parametreler);
            olayYayinla();
            return sonuc;
        };

        history.replaceState = function (...parametreler) {
            const sonuc = replaceStateAsli.apply(this, parametreler);
            olayYayinla();
            return sonuc;
        };

        window.addEventListener("popstate", konumDegisti);
        window.addEventListener("minimesaj:konum-degisti", konumDegisti);
    }

    // Sekme yeniden gorunur oldugunda gozlemciyi dogrular ve taramayi tazeler.
    function gorunurlukDegisti() {
        if (document.visibilityState === "hidden") {
            durum.sekmeGizlendiZamani = Date.now();
            return;
        }

        if (durum.gecerliDurum.sistemEtkin && instagramMesajSayfasindaMi()) {
            gozlemciyiDogrulaVeGerekirseYenidenBagla(durum.sekmeGizlendiZamani > 0);
            durum.sekmeGizlendiZamani = 0;
            taramayiPlanla("gorunur");
        }
    }

    // Pencere yeniden odaklandiginda hizli bir tarama talebi olusturur.
    function odakDegisti() {
        if (!durum.gecerliDurum.sistemEtkin || !instagramMesajSayfasindaMi()) {
            return;
        }

        taramayiPlanla("odak");
    }

    // Route degisiminde onceki sohbet durumlarini temizleyip sistemi yeniden uygular.
    function konumDegisti() {
        if (location.pathname === durum.sonYol) {
            return;
        }

        durum.sonYol = location.pathname;
        durum.cevaplananSohbetImzalari.clear();
        durum.islemdekiSohbetKimlikleri.clear();
        durum.basarisizSohbetZamanlari.clear();
        kuyruguTemizle();
        izlemeSagliginiSifirla();
        sistemiUygula();
    }

    // Gecerli sistem durumuna gore gozlemciyi durdurur veya yeniden baslatir.
    function sistemiUygula() {
        dinlemeyiDurdur();

        if (!durum.gecerliDurum.sistemEtkin || !instagramMesajSayfasindaMi()) {
            backendBaglantisiniKes();
            return;
        }

        dinlemeyiBaslat();
        guvenlikTaramasiniBaslat();
        backendBaglantisiniBaglat();
        basari("MiniMesaj dinleme modu aktif.", "sistem-acik");
        taramayiPlanla("baslangic");
    }

    // Backend WebSocket ve senkronizasyon servislerini baslatir.
    function backendBaglantisiniBaglat() {
        if (!durum.gecerliDurum.sistemEtkin || !instagramMesajSayfasindaMi()) {
            return;
        }

        if (kapsam.apiServisi?.girisYapildiMi() && kapsam.apiServisi?.hesapBagliMi()) {
            if (kapsam.websocketServisi && !kapsam.websocketServisi.bagliMi()) {
                kapsam.websocketServisi.baglan();
            }
            kapsam.apiServisi.senkronizasyonuBaslat();
            gunlukYaz('Backend baglantisi baslatildi');
        }
    }

    // Backend baglantisini durdurur.
    function backendBaglantisiniKes() {
        if (kapsam.websocketServisi) {
            kapsam.websocketServisi.kes();
        }
        if (kapsam.apiServisi) {
            kapsam.apiServisi.senkronizasyonuDurdur();
        }
    }

    // Document body uzerinde gerekli mutasyon gozlemcisini baslatir.
    function dinlemeyiBaslat() {
        if (!document.body) {
            return;
        }

        durum.gozlemciBaglantiZamani = Date.now();
        durum.sonGozlemciEtkinlikZamani = durum.gozlemciBaglantiZamani;

        durum.sohbetGozlemcisi = new MutationObserver((mutasyonlar) => {
            if (!durum.gecerliDurum.sistemEtkin) {
                return;
            }

            if (durum.mutasyonlariYoksay) {
                return;
            }

            // Alertify bildirimlerinden gelen mutasyonlari filtrele
            const ilgiliMutasyonlar = mutasyonlar.filter((m) => {
                const hedef = m.target;
                if (hedef?.closest?.('.alertify, .ajs-message, [class*="alertify"]')) {
                    return false;
                }
                return true;
            });

            if (ilgiliMutasyonlar.length === 0) {
                return;
            }

            if (location.pathname !== durum.sonYol) {
                konumDegisti();
                return;
            }

            gozlemciEtkinliginiKaydet();

            if (mutasyonlarIlgiliMi(ilgiliMutasyonlar)) {
                taramayiPlanla("degisim");
            }
        });

        durum.sohbetGozlemcisi.observe(document.body, {
            childList: true,
            subtree: true,
            characterData: true,
            attributes: true,
            attributeFilter: ["aria-label", "class", "style", "title"]
        });
    }

    // Aktif gozlemciyi ve bekleyen zamanlayiciyi temizler.
    function dinlemeyiDurdur() {
        if (durum.sohbetGozlemcisi) {
            durum.sohbetGozlemcisi.disconnect();
            durum.sohbetGozlemcisi = null;
        }

        durum.mutasyonZamanlayicisi = null;
        durum.taramaIstegiPlanlandi = false;
        durum.bekleyenTaramaNedeni = null;

        if (durum.guvenlikTaramaZamanlayicisi !== null) {
            window.clearInterval(durum.guvenlikTaramaZamanlayicisi);
            durum.guvenlikTaramaZamanlayicisi = null;
        }
    }

    // Duzgun calisma takibi icin gozlemci etkinlik zamanini gunceller.
    function gozlemciEtkinliginiKaydet() {
        durum.sonGozlemciEtkinlikZamani = Date.now();
    }

    // Tarama calistiginda son tarama zamanini kaydeder.
    function taramaZamaniniKaydet() {
        durum.sonTaramaZamani = Date.now();
    }

    // Gozlemci sagligini etkileyen zaman alanlarini sifirlar.
    function izlemeSagliginiSifirla() {
        durum.sonTaramaZamani = 0;
        durum.sonGozlemciEtkinlikZamani = 0;
        durum.gozlemciBaglantiZamani = 0;
        durum.sekmeGizlendiZamani = 0;
        durum.taramaIstegiPlanlandi = false;
        durum.bekleyenTaramaNedeni = null;
    }

    // Kuyruktaki sohbet kaydi bayatladiysa sayfadaki guncel eslesmesini bulur.
    function kuyruktakiSohbetiGuncelle(sohbet) {
        if (!sohbet?.sohbetKimligi) {
            return null;
        }

        if (sohbet.baglanti instanceof HTMLElement && sohbet.baglanti.isConnected) {
            return sohbet;
        }

        return sohbetiKimlikleBul(sohbet.sohbetKimligi);
    }

    // Gozlemci devre disi kalmis veya bayatlamissa yeniden kurar.
    function gozlemciyiDogrulaVeGerekirseYenidenBagla(zorla = false) {
        if (!durum.gecerliDurum.sistemEtkin || !instagramMesajSayfasindaMi() || !document.body) {
            return false;
        }

        const simdi = Date.now();
        const sonEtkinlik = durum.sonGozlemciEtkinlikZamani || durum.gozlemciBaglantiZamani || 0;
        const bayatKaldi = !sonEtkinlik || simdi - sonEtkinlik >= sabitler.gozlemciSaglikBeklemeMs;

        if (!zorla && durum.sohbetGozlemcisi && !bayatKaldi) {
            return false;
        }

        dinlemeyiDurdur();
        dinlemeyiBaslat();
        guvenlikTaramasiniBaslat();
        gunlukYaz("Gozlemci yeniden baglandi", { zorla, bayatKaldi });
        return true;
    }

    // Observer sessiz kaldiginda arka planda dusuk frekansli guvenlik taramasi yapar.
    function guvenlikTaramasiniBaslat() {
        if (durum.guvenlikTaramaZamanlayicisi !== null) {
            return;
        }

        durum.guvenlikTaramaZamanlayicisi = window.setInterval(() => {
            if (!durum.gecerliDurum.sistemEtkin || !instagramMesajSayfasindaMi() || durum.mutasyonlariYoksay) {
                return;
            }

            if (document.visibilityState === "visible") {
                return;
            }

            const simdi = Date.now();
            const sonTarama = durum.sonTaramaZamani || 0;

            if (simdi - sonTarama < sabitler.guvenlikTaramaAraligiMs) {
                return;
            }

            taramayiPlanla("guvenlik");
        }, sabitler.guvenlikTaramaAraligiMs);
    }

    // Gelen mutasyon listesinden ilgilenilmesi gereken degisiklik var mi bakar.
    function mutasyonlarIlgiliMi(mutasyonlar) {
        return mutasyonlar.some((mutasyon) => {
            if (mutasyon.type === "attributes" && dugumIlgiliMi(mutasyon.target)) {
                return true;
            }

            if (mutasyon.type === "characterData" && dugumIlgiliMi(mutasyon.target.parentElement)) {
                return true;
            }

            return dugumListesiIlgiliMi(mutasyon.addedNodes)
                || dugumListesiIlgiliMi(mutasyon.removedNodes)
                || dugumIlgiliMi(mutasyon.target);
        });
    }

    // Dugum listesindeki elemanlardan herhangi biri ilgiliyse true dondurur.
    function dugumListesiIlgiliMi(dugumler) {
        return [...dugumler].some((dugum) => dugumIlgiliMi(dugum));
    }

    // Tek bir dugumun sohbet listesiyle ilgili olup olmadigini belirler.
    function dugumIlgiliMi(dugum) {
        const eleman = dugumuElemanaDonustur(dugum);

        if (!eleman) {
            return false;
        }

        // Alertify bildirim elemanlari ve eklenti UI elemanlari gozardi edilir
        if (eleman.closest?.('.alertify, .ajs-message, [class*="alertify"], [id*="alertify"]')) {
            return false;
        }

        if (eleman.matches?.(seciciler.sohbetSatirlari) || eleman.closest?.(seciciler.sohbetSatirlari)) {
            return true;
        }

        if (eleman.matches?.(seciciler.baslik) || eleman.matches?.(seciciler.avatar) || eleman.matches?.(seciciler.zaman)) {
            return true;
        }

        const birlesikMetin = kucukHarfeCevir(`${eleman.getAttribute?.("aria-label") || ""} ${eleman.textContent || ""}`);

        return metinListesindenBiriGeciyorMu(birlesikMetin, [
            ...metinler.okunmamisIpuclari,
            ...metinler.yaziyorIpuclari,
            ...metinler.gidenMesajOnEkleri
        ]);
    }

    // Timer throttling'e takilmamak icin taramayi microtask kuyruğuna planlar.
    function taramayiPlanla(neden = "degisim") {
        if (!durum.gecerliDurum.sistemEtkin || !instagramMesajSayfasindaMi() || durum.mutasyonlariYoksay) {
            return;
        }

        durum.bekleyenTaramaNedeni = neden;

        if (durum.mutasyonIsleniyor || durum.taramaIstegiPlanlandi) {
            return;
        }

        durum.taramaIstegiPlanlandi = true;
        window.setTimeout(() => {
            const planlananNeden = durum.bekleyenTaramaNedeni || neden;
            durum.bekleyenTaramaNedeni = null;
            durum.taramaIstegiPlanlandi = false;
            durum.mutasyonZamanlayicisi = null;
            taramaDongusunuCalistir().catch((hata) => {
                gunlukYaz(`Tarama hatasi (${planlananNeden})`, hata);
            });
        }, sabitler.mutasyonBeklemeSuresiMs);
    }

    // Okunmamis sohbetleri filtreleyip cevap akisini sirasiyla calistirir.
    async function taramaDongusunuCalistir() {
        if (durum.mutasyonIsleniyor || !durum.gecerliDurum.sistemEtkin || !instagramMesajSayfasindaMi()) {
            return;
        }

        durum.mutasyonIsleniyor = true;
        taramaZamaniniKaydet();

        try {
            const simdi = Date.now();
            const okunmamisSohbetler = okunmamisSohbetleriBul()
                .filter((sohbet) => !sohbetBuImzaylaCevaplandiMi(sohbet))
                .filter((sohbet) => !durum.islemdekiSohbetKimlikleri.has(sohbet.sohbetKimligi))
                .filter((sohbet) => {
                    const sonBasarisizlik = durum.basarisizSohbetZamanlari.get(sohbet.sohbetKimligi) || 0;
                    return simdi - sonBasarisizlik >= sabitler.sohbetYenidenDenemeBeklemeMs;
                });

            // Backend bagliysa kisileri ve mesajlari senkronize et
            if (kapsam.apiServisi?.girisYapildiMi() && kapsam.apiServisi?.hesapBagliMi() && okunmamisSohbetler.length > 0) {
                await kisileriBildir(okunmamisSohbetler);
                await mesajlariBildir(okunmamisSohbetler);
            }

            // Bekleyen AI cevaplarini isle (WebSocket veya polling ile gelen)
            await bekleyenAiCevaplariniIsle();

            const yeniSohbetSayisi = sohbetleriSirayaEkle(okunmamisSohbetler);

            if (yeniSohbetSayisi > 0) {
                bilgi(`${kuyrukUzunlugu()} sohbet islem kuyruguna alindi.`, "okunmamis-sohbetler");
            }

            // Sekme arka plandaysa DOM etkilesimi yapilamaz, bekle
            if (document.visibilityState === 'hidden') {
                gunlukYaz('Sekme arka planda, DOM etkilesimleri erteleniyor');
                return;
            }

            let siradakiSohbet = siradakiSohbetiAl();

            while (siradakiSohbet) {
                const guncelSohbet = kuyruktakiSohbetiGuncelle(siradakiSohbet);

                if (!guncelSohbet || !guncelSohbet.okunmamis || sohbetBuImzaylaCevaplandiMi(guncelSohbet)) {
                    siradakiSohbet = siradakiSohbetiAl();
                    continue;
                }

                if (durum.islemdekiSohbetKimlikleri.has(guncelSohbet.sohbetKimligi)) {
                    siradakiSohbet = siradakiSohbetiAl();
                    continue;
                }

                // AI cevabi bekleniyorsa, henuz cevap gelmemis olabilir
                const backendAktif = kapsam.apiServisi?.girisYapildiMi() && kapsam.apiServisi?.hesapBagliMi();
                const bekleyenKayit = durum.aiCevapBekleyenSohbetler.get(guncelSohbet.sohbetKimligi);
                if (backendAktif && bekleyenKayit && bekleyenKayit.olayImzasi !== guncelSohbet.olayImzasi) {
                    kapsam.apiServisi?.bekleyenAiCevaplariniSohbettenTemizle?.(guncelSohbet.sohbetKimligi);
                }
                const aiCevabi = backendAktif ? kapsam.apiServisi.sohbetIcinAiCevabiBul(guncelSohbet.sohbetKimligi) : null;

                let cevaplandi = false;

                if (aiCevabi) {
                    // AI cevabi hazir — gonder
                    durum.aiCevapBekleyenSohbetler.delete(guncelSohbet.sohbetKimligi);
                    cevaplandi = await sohbetiAcVeAiCevabiGonder(guncelSohbet, aiCevabi.mesaj_metni);
                    if (cevaplandi) {
                        kapsam.apiServisi.aiCevabiniKaldir(aiCevabi.mesaj_id);

                        const isaretlemeSonucu = await kapsam.apiServisi.gonderildiIsaretle(aiCevabi.mesaj_id);
                        if (!isaretlemeSonucu?.basarili) {
                            gunlukYaz('AI cevabi gonderildi ancak backend isaretleme basarisiz', {
                                mesaj_id: aiCevabi.mesaj_id,
                                hata: isaretlemeSonucu?.hata || 'Bilinmeyen hata'
                            });
                        }
                    } else {
                        gunlukYaz('AI cevabi gonderilemedi, kuyrukta tutuluyor', {
                            mesaj_id: aiCevabi.mesaj_id,
                            sohbet: guncelSohbet.sohbetKimligi
                        });
                    }
                } else if (!backendAktif) {
                    // Backend bagli degil — sabit cevap ile fallback
                    cevaplandi = await sohbetiAcVeCevapla(guncelSohbet);
                } else {
                    // Backend bagli ama AI cevabi henuz gelmemis — timeout kontrolu
                    const bekleyenKayit = durum.aiCevapBekleyenSohbetler.get(guncelSohbet.sohbetKimligi);
                    if (!bekleyenKayit || bekleyenKayit.olayImzasi !== guncelSohbet.olayImzasi) {
                        kapsam.apiServisi?.bekleyenAiCevaplariniSohbettenTemizle?.(guncelSohbet.sohbetKimligi);
                        durum.aiCevapBekleyenSohbetler.set(guncelSohbet.sohbetKimligi, {
                            baslamaZamani: Date.now(),
                            olayImzasi: guncelSohbet.olayImzasi
                        });
                        gunlukYaz('AI cevabi bekleniyor, zamanlayici baslatildi', guncelSohbet.sohbetKimligi);
                        kapsam.apiServisi?.hizliGidenKuyruguTakibiniPlanla?.();
                    } else if (Date.now() - bekleyenKayit.baslamaZamani >= sabitler.aiCevapBeklemeZamanAsimi) {
                        // Zaman asimi — sabit cevapla fallback
                        gunlukYaz('AI cevabi zaman asimina ugradi, sabit cevap gonderiliyor', guncelSohbet.sohbetKimligi);
                        durum.aiCevapBekleyenSohbetler.delete(guncelSohbet.sohbetKimligi);
                        cevaplandi = await sohbetiAcVeCevapla(guncelSohbet);
                    } else {
                        gunlukYaz('AI cevabi henuz hazir degil, bekleniyor...', guncelSohbet.sohbetKimligi);
                        kapsam.apiServisi?.hizliGidenKuyruguTakibiniPlanla?.();
                    }
                }

                if (cevaplandi) {
                    sohbetiCevaplandiOlarakIsaretle(guncelSohbet);
                }

                siradakiSohbet = siradakiSohbetiAl();
            }
        } finally {
            durum.mutasyonIsleniyor = false;

            if (durum.bekleyenTaramaNedeni) {
                taramayiPlanla(durum.bekleyenTaramaNedeni);
            }
        }
    }

    // Okunmamis sohbet sahiplerini backend'e kisi olarak bildirir.
    async function kisileriBildir(sohbetler) {
        try {
            const kisiler = sohbetler
                .filter((sohbet) => sohbet.gonderenAdi && sohbet.gonderenAdi !== 'Bilinmeyen')
                .map((sohbet) => ({
                    instagram_kisi_id: sohbet.sohbetKimligi,
                    kullanici_adi: null,
                    gorunen_ad: sohbet.gonderenAdi,
                    profil_resmi: null
                }));

            if (kisiler.length > 0) {
                await kapsam.apiServisi.kisileriSenkronize(kisiler);
            }
        } catch (hata) {
            gunlukYaz('Kisi senkronizasyonu hatasi', hata);
        }
    }

    // Okunmamis sohbet on-izleme metinlerini backend'e mesaj olarak bildirir.
    async function mesajlariBildir(sohbetler) {
        try {
            const mesajlar = sohbetler
                .filter((sohbet) => sohbet.onizlemeMetni)
                .map((sohbet) => ({
                    instagram_kisi_id: sohbet.sohbetKimligi,
                    gonderen_tipi: 'karsi_taraf',
                    mesaj_metni: sohbet.onizlemeMetni,
                    mesaj_tipi: 'metin',
                    instagram_mesaj_kodu: [sohbet.sohbetKimligi, kucukHarfeCevir(sohbet.onizlemeMetni)].join('|')
                }));

            if (mesajlar.length > 0) {
                await kapsam.apiServisi.mesajlariGonder(mesajlar);
            }
        } catch (hata) {
            gunlukYaz('Mesaj senkronizasyonu hatasi', hata);
        }
    }

    // Bekleyen AI cevaplarini tarayip sohbet kuyruguna ekler.
    async function bekleyenAiCevaplariniIsle() {
        if (!durum.bekleyenAiCevaplari?.length) {
            return;
        }

        const bekleyenler = [...durum.bekleyenAiCevaplari];

        for (const cevap of bekleyenler) {
            // Dogrudan kisi kodu ile sohbet bul (tam sohbetKimligi eslesmesi)
            let hedefSohbet = null;

            if (cevap.kisi_kodu) {
                hedefSohbet = sohbetiKimlikleBul(cevap.kisi_kodu);
            }

            // Kisi kodu yoksa isim ile dene
            if (!hedefSohbet) {
                const kisiAdi = cevap.kisi?.gorunen_ad || cevap.kisi?.kullanici_adi || '';

                if (kisiAdi) {
                    const tumSohbetler = okunmamisSohbetleriBul();
                    hedefSohbet = tumSohbetler.find((s) => s.sohbetKimligi.includes(kisiAdi)) || null;
                }
            }

            if (hedefSohbet) {
                // Sohbeti kuyruga ekle (cevapla dongusu isleyecek)
                sohbetleriSirayaEkle([hedefSohbet]);
            }
        }
    }

    kapsam.gozlemciServisi = {
        baslat,
        taramayiPlanla,
        taramaDongusunuCalistir
    };
})();
