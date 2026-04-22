// Mesaj kutusu bulma, yazma ve gonderme mantigini izole kapsamda toplar.
(() => {
    const kapsam = globalThis.MiniMesaj;

    if (!kapsam?.domYardimcilari || !kapsam?.bildirimServisi) {
        return;
    }

    const {
        tumSecicilerdenElemanTopla,
        gorunurMu,
        temizMetin,
        kucukHarfeCevir,
        birlesikEtiketMetniniAl,
        elemanMetniniOku,
        olaylariTetikle,
        icerikSeciminiAyarla,
        bekle,
        gunlukYaz
    } = kapsam.domYardimcilari;

    const { bilgi, basari, hata } = kapsam.bildirimServisi;
    const { durum, sabitler } = kapsam;

    // Uygun mesaj kutusu adaylarini puanlayip en iyi secenegi dondurur.
    function mesajKutusuBul() {
        const adaylar = tumSecicilerdenElemanTopla(kapsam.seciciler.mesajKutusu)
            .filter(gorunurMu)
            .filter(mesajKutusuAdayiMi)
            .sort(mesajKutusuOnceliginiHesapla);

        return adaylar[0] || null;
    }

    // Verilen aralik icinde rastgele bir tamsayi dondurur.
    function rastgeleAralik(min, maks) {
        return Math.floor(Math.random() * (maks - min + 1)) + min;
    }

    // Karakter tipine gore insan benzeri bekleme suresi hesaplar.
    function harfGecikmeHesapla(karakter) {
        if ('.!?,;:'.includes(karakter)) {
            return rastgeleAralik(sabitler.noktalamaGecikmeMinMs, sabitler.noktalamaGecikmeMaxMs);
        }

        if (karakter === ' ') {
            return rastgeleAralik(sabitler.kelimeArasiGecikmeMinMs, sabitler.kelimeArasiGecikmeMaxMs);
        }

        return rastgeleAralik(sabitler.harfGecikmeMinMs, sabitler.harfGecikmeMaxMs);
    }

    // Tek bir karakter icin keydown/keypress/input/keyup olaylarini tetikler.
    function tusOlaylariniTetikle(mesajKutusu, karakter) {
        const olaySecenekleri = {
            key: karakter,
            code: `Key${karakter.toUpperCase()}`,
            bubbles: true,
            cancelable: true
        };

        mesajKutusu.dispatchEvent(new KeyboardEvent('keydown', olaySecenekleri));
        mesajKutusu.dispatchEvent(new KeyboardEvent('keypress', olaySecenekleri));
        mesajKutusu.dispatchEvent(new InputEvent('input', {
            data: karakter,
            inputType: 'insertText',
            bubbles: true,
            cancelable: true
        }));
        mesajKutusu.dispatchEvent(new KeyboardEvent('keyup', olaySecenekleri));
    }

    // Contenteditable kutuya tek karakter ekler.
    function tekKarakterEkle(mesajKutusu, karakter) {
        icerikSeciminiAyarla(mesajKutusu, true);

        try {
            const eklendi = document.execCommand('insertText', false, karakter);

            if (!eklendi) {
                const secim = window.getSelection();
                const aralik = secim?.getRangeAt(0);

                if (aralik) {
                    aralik.deleteContents();
                    aralik.insertNode(document.createTextNode(karakter));
                    aralik.collapse(false);
                } else {
                    mesajKutusu.textContent += karakter;
                }
            }
        } catch (_hata) {
            mesajKutusu.textContent += karakter;
        }

        tusOlaylariniTetikle(mesajKutusu, karakter);
    }

    // Mesaji textarea veya contenteditable kutuya insan gibi harf harf yazar.
    async function mesajKutusuIcineYaz(mesajKutusu, metin) {
        mesajKutusu.focus();

        if (mesajKutusu instanceof HTMLTextAreaElement || mesajKutusu instanceof HTMLInputElement) {
            const yerelDegerYazicisi = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, "value")?.set;
            const metinAlaniDegerYazicisi = Object.getOwnPropertyDescriptor(window.HTMLTextAreaElement.prototype, "value")?.set;
            const degerYazicisi = mesajKutusu instanceof HTMLTextAreaElement ? metinAlaniDegerYazicisi : yerelDegerYazicisi;

            for (let i = 0; i < metin.length; i += 1) {
                const mevcutMetin = metin.slice(0, i + 1);

                if (degerYazicisi) {
                    degerYazicisi.call(mesajKutusu, mevcutMetin);
                } else {
                    mesajKutusu.value = mevcutMetin;
                }

                tusOlaylariniTetikle(mesajKutusu, metin[i]);
                olaylariTetikle(mesajKutusu);
                await bekle(harfGecikmeHesapla(metin[i]));
            }

            return temizMetin(mesajKutusu.value) === temizMetin(metin);
        }

        await contentEditableKutuyuHazirla(mesajKutusu);
        await bekle(60);

        for (let i = 0; i < metin.length; i += 1) {
            tekKarakterEkle(mesajKutusu, metin[i]);
            await bekle(harfGecikmeHesapla(metin[i]));
        }

        const yazimBasarili = temizMetin(elemanMetniniOku(mesajKutusu)) === temizMetin(metin);

        if (!yazimBasarili) {
            contentEditableMetniniDogrudanYaz(mesajKutusu, metin);
        }

        imleciSonaTasi(mesajKutusu);
        return true;
    }

    // Bir elemanin gercek mesaj kutusu olmaya uygunlugunu kontrol eder.
    function mesajKutusuAdayiMi(mesajKutusu) {
        if (mesajKutusu.closest('header, nav, [role="dialog"], [role="search"]')) {
            return false;
        }

        if (mesajKutusu.querySelector('[contenteditable="true"], textarea, input[type="text"]')) {
            return false;
        }

        const birlesikEtiket = birlesikEtiketMetniniAl(mesajKutusu);

        if (birlesikEtiket.includes("search") || birlesikEtiket.includes("ara")) {
            return false;
        }

        return true;
    }

    // Mesaj kutularini yuksek puanli olan once gelecek sekilde siralar.
    function mesajKutusuOnceliginiHesapla(sol, sag) {
        return mesajKutusuPuani(sag) - mesajKutusuPuani(sol);
    }

    // Mesaj kutusu adayina rol ve etiketlerine gore uygunluk puani verir.
    function mesajKutusuPuani(mesajKutusu) {
        const metin = birlesikEtiketMetniniAl(mesajKutusu);
        let puan = 0;

        if (mesajKutusu.getAttribute("role") === "textbox") {
            puan += 5;
        }

        if (metin.includes("mesaj") || metin.includes("message")) {
            puan += 10;
        }

        if (mesajKutusu.isContentEditable) {
            puan += 3;
        }

        return puan;
    }

    // Contenteditable kutudaki mevcut icerigi secip temizler.
    async function contentEditableKutuyuHazirla(mesajKutusu) {
        icerikSeciminiAyarla(mesajKutusu);
        document.execCommand("delete", false);
        contentEditableMetniniDogrudanYaz(mesajKutusu, "");
    }

    // Contenteditable kutuya tarayici komutu ile metin eklemeyi dener.
    function contentEditableMetniniKomutlaYaz(mesajKutusu, metin) {
        icerikSeciminiAyarla(mesajKutusu, true);

        try {
            return document.execCommand("insertText", false, metin);
        } catch (_hata) {
            return false;
        }
    }

    // Contenteditable kutuya metni dogrudan yazip gerekli olaylari tetikler.
    function contentEditableMetniniDogrudanYaz(mesajKutusu, metin) {
        mesajKutusu.textContent = metin;
        olaylariTetikle(mesajKutusu);
    }

    // Yazilan metni birkac tur kontrol ederek hedef metne dengeler.
    async function mesajMetniniDengele(mesajKutusu, metin) {
        const hedefMetin = temizMetin(metin);

        for (let deneme = 0; deneme < 6; deneme += 1) {
            await bekle(80);
            const sonMetin = elemanMetniniOku(mesajKutusu);

            if (sonMetin === hedefMetin) {
                return true;
            }

            if (!sonMetin) {
                contentEditableMetniniDogrudanYaz(mesajKutusu, metin);
                continue;
            }

            if (mesajMetniTekrarlanmisMi(sonMetin, metin) || sonMetin.includes(hedefMetin)) {
                contentEditableMetniniDogrudanYaz(mesajKutusu, metin);
            }
        }

        contentEditableMetniniDogrudanYaz(mesajKutusu, "");
        return false;
    }

    // Yazim sonrasi imleci kutunun sonuna tasir.
    function imleciSonaTasi(mesajKutusu) {
        icerikSeciminiAyarla(mesajKutusu, true);
    }

    // Okunan metnin ayni cevabin birden fazla tekrarindan olusup olusmadigini tespit eder.
    function mesajMetniTekrarlanmisMi(sonMetin, metin) {
        const temizHedef = temizMetin(metin);

        if (!temizHedef || sonMetin.length <= temizHedef.length) {
            return false;
        }

        if (!sonMetin.startsWith(temizHedef)) {
            return false;
        }

        if (sonMetin.length % temizHedef.length !== 0) {
            return false;
        }

        return sonMetin === temizHedef.repeat(sonMetin.length / temizHedef.length);
    }

    // Eklentinin kendi yazma mutasyonlarini gecici olarak gozardi etmesini saglar.
    // Mesaj uzunluguna gore yoksayma suresini ayarlar (harf harf yazim icin).
    function mutasyonlariGeciciYoksay(mesajUzunlugu = 0) {
        durum.mutasyonlariYoksay = true;

        const tahminiYazimSuresi = mesajUzunlugu > 0
            ? mesajUzunlugu * sabitler.harfGecikmeMaxMs + 2000
            : sabitler.mutasyonYoksayBeklemeMs;

        const sure = Math.max(sabitler.mutasyonYoksayBeklemeMs, tahminiYazimSuresi);

        window.setTimeout(() => {
            durum.mutasyonlariYoksay = false;
        }, sure);
    }

    // Basarisiz sohbet denemesinin zamanini kaydeder ve aktif islem kilidini kaldirir.
    function basarisizDenemeKaydet(sohbetKimligi) {
        durum.basarisizSohbetZamanlari.set(sohbetKimligi, Date.now());
        durum.islemdekiSohbetKimlikleri.delete(sohbetKimligi);
    }

    // Gonder butonunu bulup tiklar, bulunamazsa Enter olaylarini tetikler.
    function gonderDugmesineBas(mesajKutusu) {
        const gonderDugmesi = tumSecicilerdenElemanTopla(kapsam.seciciler.butonlar).find((eleman) => {
            if (!gorunurMu(eleman)) {
                return false;
            }

            const metin = kucukHarfeCevir(elemanMetniniOku(eleman));
            const ariaEtiketi = birlesikEtiketMetniniAl(eleman, ["aria-label"]);

            return ["gonder", "send"].includes(metin) || ["gonder", "send"].some((kalip) => ariaEtiketi.includes(kalip));
        });

        if (gonderDugmesi) {
            gonderDugmesi.click();
            return true;
        }

        ["keydown", "keypress", "keyup"].forEach((olayTipi) => {
            mesajKutusu.dispatchEvent(new KeyboardEvent(olayTipi, {
                key: "Enter",
                code: "Enter",
                bubbles: true,
                cancelable: true
            }));
        });

        return true;
    }

    // Sohbeti acip cevap yazar, gonderir ve durum kayitlarini gunceller.
    async function sohbetiAcVeCevapla(sohbet) {
        if (durum.islemdekiSohbetKimlikleri.has(sohbet.sohbetKimligi)) {
            return false;
        }

        durum.islemdekiSohbetKimlikleri.add(sohbet.sohbetKimligi);

        try {
            mutasyonlariGeciciYoksay(kapsam.sabitler.cevapMetni.length);
            bilgi(`${sohbet.gonderenAdi} icin cevap hazirlaniyor.`, `hazirlik-${sohbet.sohbetKimligi}`);
            sohbet.baglanti.click();
            await bekle(1600);

            const mesajKutusu = mesajKutusuBul();

            if (!mesajKutusu) {
                hata("Mesaj kutusu bulunamadi.", `mesaj-kutusu-${sohbet.sohbetKimligi}`);
                gunlukYaz("Mesaj kutusu bulunamadi", sohbet.sohbetKimligi);
                basarisizDenemeKaydet(sohbet.sohbetKimligi);
                return false;
            }

            const mesajYazildi = await mesajKutusuIcineYaz(mesajKutusu, kapsam.sabitler.cevapMetni);

            if (!mesajYazildi) {
                hata("Mesaj kutusuna yazi eklenemedi.", `mesaj-yaz-${sohbet.sohbetKimligi}`);
                gunlukYaz("Mesaj kutusuna yazi eklenemedi", sohbet.sohbetKimligi);
                basarisizDenemeKaydet(sohbet.sohbetKimligi);
                return false;
            }

            await bekle(250);

            if (!gonderDugmesineBas(mesajKutusu)) {
                hata("Gonder eylemi basarisiz.", `gonder-${sohbet.sohbetKimligi}`);
                gunlukYaz("Gonder eylemi basarisiz", sohbet.sohbetKimligi);
                basarisizDenemeKaydet(sohbet.sohbetKimligi);
                return false;
            }

            await bekle(700);
            durum.basarisizSohbetZamanlari.delete(sohbet.sohbetKimligi);
            durum.islemdekiSohbetKimlikleri.delete(sohbet.sohbetKimligi);
            basari(`${sohbet.gonderenAdi} icin cevap gonderildi.`, `cevap-${sohbet.sohbetKimligi}`);
            return true;
        } catch (yakalananHata) {
            hata("Sohbet cevaplanirken hata olustu.", `hata-${sohbet.sohbetKimligi}`);
            gunlukYaz("Sohbet cevaplanirken hata olustu", yakalananHata);
            basarisizDenemeKaydet(sohbet.sohbetKimligi);
            return false;
        }
    }

    // Backend'den gelen AI cevabini belirtilen sohbete gonderir.
    async function sohbetiAcVeAiCevabiGonder(sohbet, cevapMetni) {
        if (!cevapMetni || durum.islemdekiSohbetKimlikleri.has(sohbet.sohbetKimligi)) {
            return false;
        }

        durum.islemdekiSohbetKimlikleri.add(sohbet.sohbetKimligi);

        try {
            mutasyonlariGeciciYoksay(cevapMetni.length);
            bilgi(`${sohbet.gonderenAdi} icin AI cevabi gonderiliyor.`, `ai-gonder-${sohbet.sohbetKimligi}`);
            sohbet.baglanti.click();
            await bekle(1600);

            const mesajKutusu = mesajKutusuBul();

            if (!mesajKutusu) {
                hata("Mesaj kutusu bulunamadi.", `mesaj-kutusu-${sohbet.sohbetKimligi}`);
                gunlukYaz("AI cevabi icin mesaj kutusu bulunamadi", sohbet.sohbetKimligi);
                basarisizDenemeKaydet(sohbet.sohbetKimligi);
                return false;
            }

            const mesajYazildi = await mesajKutusuIcineYaz(mesajKutusu, cevapMetni);

            if (!mesajYazildi) {
                hata("AI cevabi kutusuna yazilamadi.", `ai-yaz-${sohbet.sohbetKimligi}`);
                gunlukYaz("AI cevabi kutusuna yazilamadi", sohbet.sohbetKimligi);
                basarisizDenemeKaydet(sohbet.sohbetKimligi);
                return false;
            }

            await bekle(250);

            if (!gonderDugmesineBas(mesajKutusu)) {
                hata("AI cevabi gonder eylemi basarisiz.", `ai-gonder-btn-${sohbet.sohbetKimligi}`);
                gunlukYaz("AI cevabi gonder eylemi basarisiz", sohbet.sohbetKimligi);
                basarisizDenemeKaydet(sohbet.sohbetKimligi);
                return false;
            }

            await bekle(700);
            durum.basarisizSohbetZamanlari.delete(sohbet.sohbetKimligi);
            basari(`${sohbet.gonderenAdi} icin AI cevabi gonderildi.`, `ai-cevap-ok-${sohbet.sohbetKimligi}`);
            return true;
        } catch (yakalananHata) {
            hata("AI cevabi gonderilirken hata olustu.", `ai-hata-${sohbet.sohbetKimligi}`);
            gunlukYaz("AI cevabi gonderilirken hata olustu", yakalananHata);
            basarisizDenemeKaydet(sohbet.sohbetKimligi);
            return false;
        } finally {
            durum.islemdekiSohbetKimlikleri.delete(sohbet.sohbetKimligi);
        }
    }

    kapsam.mesajServisi = {
        sohbetiAcVeCevapla,
        sohbetiAcVeAiCevabiGonder,
        mesajKutusuBul,
        mesajKutusuIcineYaz,
        gonderDugmesineBas
    };
})();
