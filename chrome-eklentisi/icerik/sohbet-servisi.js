// Sohbet listesi okuma ve okunmamis tespit mantigini izole kapsamda toplar.
(() => {
    const kapsam = globalThis.MiniMesaj;

    if (!kapsam?.domYardimcilari) {
        return;
    }

    const {
        temizMetin,
        kucukHarfeCevir,
        metinListesindenBiriGeciyorMu,
        gidenMesajMi,
        ozellikMetniniAl,
        elemanMetniniOku,
        essizElemanlariDondur,
        gorunurMu
    } = kapsam.domYardimcilari;

    const { seciciler, metinler } = kapsam;

    // Aktif sayfanin Instagram DM alani olup olmadigini kontrol eder.
    function instagramMesajSayfasindaMi() {
        return location.hostname === "www.instagram.com" && location.pathname.startsWith("/direct");
    }

    // Sohbet satirlarini cikarip sadece okunmamis olanlari dondurur.
    function okunmamisSohbetleriBul() {
        return sohbetSatirlariniBul()
            .map(sohbetBilgisiniCikar)
            .filter((sohbet) => sohbet.okunmamis);
    }

    // Verilen sohbet kimligi icin listede guncel satiri bulup sohbet verisini dondurur.
    function sohbetiKimlikleBul(sohbetKimligi) {
        if (!sohbetKimligi) {
            return null;
        }

        return sohbetSatirlariniBul()
            .map(sohbetBilgisiniCikar)
            .find((sohbet) => sohbet.sohbetKimligi === sohbetKimligi) || null;
    }

    // Muhtemel sohbet satirlarini farkli secicilerden toplayip tek listeye indirir.
    function sohbetSatirlariniBul() {
        const hamAdaylar = [
            ...document.querySelectorAll(seciciler.sohbetSatirlari),
            ...document.querySelectorAll(seciciler.baslik),
            ...document.querySelectorAll(seciciler.avatar),
            ...document.querySelectorAll(seciciler.zaman)
        ];

        const sohbetSatirlari = hamAdaylar
            .map((eleman) => eleman.closest?.(seciciler.sohbetSatirlari) || eleman.closest?.('div[role="button"]') || eleman)
            .filter((eleman) => eleman instanceof HTMLElement)
            .filter(sohbetSatiriAdayiMi);

        return essizElemanlariDondur(sohbetSatirlari);
    }

    // Bir elemanin sohbet satiri adayi olmaya uygunlugunu olcer.
    function sohbetSatiriAdayiMi(sohbetSatiri) {
        if (!gorunurMu(sohbetSatiri)) {
            return false;
        }

        if (sohbetSatiri.closest('form, nav, header, [role="dialog"]')) {
            return false;
        }

        const baslikElemani = sohbetSatiri.querySelector(seciciler.baslik);
        const profilGorseli = sohbetSatiri.querySelector(seciciler.avatar);
        const zamanElemani = sohbetSatiri.querySelector(seciciler.zaman);
        const metin = kucukHarfeCevir(sohbetSatiri.textContent);

        if (metinListesindenBiriGeciyorMu(metin, metinler.kabulButonuIpuclari)) {
            return false;
        }

        return Boolean(baslikElemani && profilGorseli && (zamanElemani || metin.length > 0));
    }

    // Sohbet satirindan kimlik, gonderen ve onizleme gibi bilgileri cikartir.
    function sohbetBilgisiniCikar(sohbetSatiri) {
        const sohbetKimligi = sohbetKimligiOlustur(sohbetSatiri) || `sohbet-${Date.now()}`;
        const metinParcalari = [...sohbetSatiri.querySelectorAll("span, h3, div")]
            .map((eleman) => elemanMetniniOku(eleman))
            .filter(Boolean)
            .filter((metin, sira, dizi) => dizi.indexOf(metin) === sira)
            .slice(0, 12);

        const gonderenAdi = ozellikMetniniAl(sohbetSatiri.querySelector(seciciler.baslik), "title")
            || gonderenAdiniSec(metinParcalari)
            || sohbetKimligi
            || "Bilinmeyen";
        const zamanEtiketi = sohbetZamanEtiketiniBul(sohbetSatiri);
        const onizlemeMetni = sohbetOnizlemeMetniniBul(sohbetSatiri)
            || metinParcalari.find((metin) => metin !== gonderenAdi)
            || "";
        const olayImzasi = sohbetOlayImzasiOlustur({ sohbetKimligi, onizlemeMetni, zamanEtiketi, sohbetSatiri });

        return {
            baglanti: sohbetSatiri,
            sohbetKimligi,
            gonderenAdi,
            zamanEtiketi,
            onizlemeMetni,
            olayImzasi,
            okunmamis: sohbetOkunmamisMi(sohbetSatiri, metinParcalari)
        };
    }

    // Sohbet satirindan zaman etiketini veya son zaman bilgisini cikarir.
    function sohbetZamanEtiketiniBul(sohbetSatiri) {
        const zamanElemani = sohbetSatiri.querySelector(seciciler.zaman);

        if (!zamanElemani) {
            return "";
        }

        return ozellikMetniniAl(zamanElemani, "aria-label") || elemanMetniniOku(zamanElemani);
    }

    // Sohbetin son gorunen durumunu ayirt etmek icin mesaj imzasi uretir.
    function sohbetOlayImzasiOlustur({ sohbetKimligi, onizlemeMetni, zamanEtiketi, sohbetSatiri }) {
        const satirOzeti = elemanMetniniOku(sohbetSatiri)
            .replace(/\s+/g, " ")
            .slice(0, 180);

        return [
            sohbetKimligi,
            kucukHarfeCevir(onizlemeMetni),
            kucukHarfeCevir(zamanEtiketi),
            kucukHarfeCevir(satirOzeti)
        ].join("|");
    }

    // Sohbet satiri icin kararlı ve tekrar kullanilabilir bir kimlik uretir.
    function sohbetKimligiOlustur(sohbetSatiri) {
        const varolanKimlik = sohbetSatiri.dataset.minimesajSohbetKimligi;

        if (varolanKimlik) {
            return varolanKimlik;
        }

        const baslik = ozellikMetniniAl(sohbetSatiri.querySelector(seciciler.baslik), "title");
        const avatarKaynak = ozellikMetniniAl(sohbetSatiri.querySelector(seciciler.avatar), "src")
            .split("?")[0]
            .split("/")
            .filter(Boolean)
            .pop();
        const ariaEtiketi = ozellikMetniniAl(sohbetSatiri, "aria-label");
        const kararlıKimlik = [baslik || "sohbet", avatarKaynak || ariaEtiketi || "avatar-yok"].join("|");

        sohbetSatiri.dataset.minimesajSohbetKimligi = kararlıKimlik;
        return kararlıKimlik;
    }

    // Sohbet onizleme metni olabilecek en anlamli span icerigini bulur.
    function sohbetOnizlemeMetniniBul(sohbetSatiri) {
        const baslik = ozellikMetniniAl(sohbetSatiri.querySelector(seciciler.baslik), "title");
        const metinAdaylari = [...sohbetSatiri.querySelectorAll("span")]
            .map((eleman) => elemanMetniniOku(eleman))
            .filter(Boolean)
            .filter((metin) => metin !== baslik)
            .filter((metin) => !/^\d+[sgad]$/.test(kucukHarfeCevir(metin)))
            .filter((metin) => !metinListesindenBiriGeciyorMu(kucukHarfeCevir(metin), metinler.bugunIpuclari))
            .filter((metin) => !metin.toLowerCase().includes("user-profile-picture"));

        return metinAdaylari.find((metin) => {
            const kucukMetin = kucukHarfeCevir(metin);

            if (metin.length <= 1) {
                return false;
            }

            return !metinListesindenBiriGeciyorMu(kucukMetin, metinler.sistemOnizlemeMetinleri);
        }) || "";
    }

    // Sohbetin okunmamis sayilip sayilmayacagini farkli ipuclarina gore belirler.
    function sohbetOkunmamisMi(sohbetSatiri, metinParcalari) {
        const onizlemeMetni = kucukHarfeCevir(sohbetOnizlemeMetniniBul(sohbetSatiri));
        const ariaParcalari = [...sohbetSatiri.querySelectorAll("[aria-label]")]
            .map((eleman) => ozellikMetniniAl(eleman, "aria-label"))
            .filter(Boolean);

        const birlesikMetin = kucukHarfeCevir([
            sohbetSatiri.getAttribute("aria-label") || "",
            sohbetSatiri.textContent || "",
            ...metinParcalari,
            ...ariaParcalari
        ].join(" "));

        if (metinListesindenBiriGeciyorMu(birlesikMetin, metinler.okunmamisIpuclari)) {
            return true;
        }

        if (metinListesindenBiriGeciyorMu(onizlemeMetni, metinler.yaziyorIpuclari)) {
            return true;
        }

        if (sayisalRozetVarMi(sohbetSatiri) || maviRozetVarMi(sohbetSatiri)) {
            return true;
        }

        return !gidenMesajMi(onizlemeMetni) && kalinYaziIzlenimiVarMi(sohbetSatiri);
    }

    // Metin parcaciklari arasindan gonderen adina en uygun olani secer.
    function gonderenAdiniSec(metinParcalari) {
        return metinParcalari.find((metin) => {
            const kucukMetin = kucukHarfeCevir(metin);

            if (!kucukMetin) {
                return false;
            }

            if (gidenMesajMi(kucukMetin) || metinListesindenBiriGeciyorMu(kucukMetin, metinler.sistemOnizlemeMetinleri)) {
                return false;
            }

            return !metinListesindenBiriGeciyorMu(kucukMetin, metinler.bugunIpuclari);
        }) || "";
    }

    // Sohbet satirinda Instagram'in mavi okunmamis rozetini arar.
    function maviRozetVarMi(sohbetSatiri) {
        return [...sohbetSatiri.querySelectorAll("div, span")]
            .filter(gorunurMu)
            .some((eleman) => {
                const stil = window.getComputedStyle(eleman);
                const arkaPlan = stil.backgroundColor.replace(/\s+/g, "");
                const genislik = Number.parseFloat(stil.width);
                const yukseklik = Number.parseFloat(stil.height);

                return arkaPlan === "rgb(0,149,246)" && genislik <= 14 && yukseklik <= 14;
            });
    }

    // Sayisal badge gorunumunden okunmamis mesaj sayaci var mi kontrol eder.
    function sayisalRozetVarMi(sohbetSatiri) {
        return [...sohbetSatiri.querySelectorAll("span, div")].some((eleman) => {
            const metin = temizMetin(eleman.textContent);

            if (!/^\d+$/.test(metin) || !gorunurMu(eleman)) {
                return false;
            }

            const stil = window.getComputedStyle(eleman);
            return Number.parseInt(stil.fontWeight, 10) >= 600 || stil.backgroundColor.replace(/\s+/g, "") === "rgb(0,149,246)";
        });
    }

    // Kalin yazi kullanimi ile okunmamis gorunumu olup olmadigini olcer.
    function kalinYaziIzlenimiVarMi(sohbetSatiri) {
        return [...sohbetSatiri.querySelectorAll("span, div")].some((eleman) => {
            if (!gorunurMu(eleman)) {
                return false;
            }

            const metin = temizMetin(eleman.textContent);

            if (!metin || metin.length > 80) {
                return false;
            }

            const stil = window.getComputedStyle(eleman);
            const yaziKalini = Number.parseInt(stil.fontWeight, 10);
            return Number.isFinite(yaziKalini) && yaziKalini >= 600;
        });
    }

    kapsam.sohbetServisi = {
        instagramMesajSayfasindaMi,
        okunmamisSohbetleriBul,
        sohbetiKimlikleBul,
        sohbetSatirlariniBul,
        sohbetBilgisiniCikar
    };
})();
