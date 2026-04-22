// Ortak DOM ve metin yardimcilarini izole kapsamda tanimlar.
(() => {
    const kapsam = globalThis.MiniMesaj;

    if (!kapsam) {
        return;
    }

    // Metindeki fazla bosluklari temizler ve kirpar.
    function temizMetin(deger) {
        return (deger || "").replace(/\s+/g, " ").trim();
    }

    // Metni Turkce kurallara gore kucuk harfe cevirir.
    function kucukHarfeCevir(deger) {
        return temizMetin(deger).toLocaleLowerCase("tr-TR");
    }

    // Verilen metin icinde listedeki kaliplardan biri var mi kontrol eder.
    function metinListesindenBiriGeciyorMu(metin, liste) {
        return liste.some((oge) => metin.includes(kucukHarfeCevir(oge)));
    }

    // Onizleme metninin giden mesaj kalibina uyup uymadigini belirler.
    function gidenMesajMi(metin) {
        return metinListesindenBiriGeciyorMu(kucukHarfeCevir(metin), kapsam.metinler.gidenMesajOnEkleri);
    }

    // Bir veya birden fazla seciciden tum eslesen elemanlari tek listede toplar.
    function tumSecicilerdenElemanTopla(seciciler) {
        const seciciListesi = Array.isArray(seciciler) ? seciciler : [seciciler];
        const elemanlar = [];

        seciciListesi.forEach((secici) => {
            document.querySelectorAll(secici).forEach((eleman) => {
                if (!elemanlar.includes(eleman)) {
                    elemanlar.push(eleman);
                }
            });
        });

        return elemanlar;
    }

    // Ayni elemanin tekrar ettigi listeleri essiz hale getirir.
    function essizElemanlariDondur(elemanlar) {
        return elemanlar.filter((eleman, sira) => elemanlar.indexOf(eleman) === sira);
    }

    // Bir elemanin sayfada gorunur durumda olup olmadigini kontrol eder.
    function gorunurMu(eleman) {
        if (!eleman || !(eleman instanceof HTMLElement)) {
            return false;
        }

        const stil = window.getComputedStyle(eleman);
        const istemciDikdortgenleri = eleman.getClientRects();

        return stil.display !== "none" && stil.visibility !== "hidden" && istemciDikdortgenleri.length > 0;
    }

    // Eleman uzerindeki ozelligin temizlenmis metin degerini dondurur.
    function ozellikMetniniAl(eleman, ozellikAdi) {
        return temizMetin(eleman?.getAttribute?.(ozellikAdi) || "");
    }

    // Birden fazla attribute degerini birlestirip kucuk harfli etiket metni uretir.
    function birlesikEtiketMetniniAl(eleman, ozellikler = ["aria-label", "placeholder"]) {
        return kucukHarfeCevir(ozellikler
            .map((ozellikAdi) => ozellikMetniniAl(eleman, ozellikAdi))
            .filter(Boolean)
            .join(" "));
    }

    // Elemanin gorunen veya form degerini ortak yoldan okur.
    function elemanMetniniOku(eleman) {
        if (!eleman) {
            return "";
        }

        const deger = typeof eleman.value === "string" ? eleman.value : "";
        return temizMetin(eleman.innerText || eleman.textContent || deger);
    }

    // Varsayilan olarak input ve change olaylarini ayni anda tetikler.
    function olaylariTetikle(eleman, olayTipleri = ["input", "change"]) {
        olayTipleri.forEach((olayTipi) => {
            eleman.dispatchEvent(new Event(olayTipi, { bubbles: true }));
        });
    }

    // Contenteditable veya secilebilir icerikte aktif secimi tum icerige ayarlar.
    function icerikSeciminiAyarla(eleman, sonaDaralt = false) {
        const secim = window.getSelection();
        const aralik = document.createRange();

        aralik.selectNodeContents(eleman);

        if (sonaDaralt) {
            aralik.collapse(false);
        }

        if (secim) {
            secim.removeAllRanges();
            secim.addRange(aralik);
        }
    }

    // Asenkron akislarda kisa bekleme olusturur.
    function bekle(sureMs) {
        return new Promise((coz) => {
            window.setTimeout(coz, sureMs);
        });
    }

    // MutationObserver dugumlerini guvenli sekilde HTML elemana cevirir.
    function dugumuElemanaDonustur(dugum) {
        if (dugum instanceof HTMLElement || dugum instanceof Element) {
            return dugum;
        }

        if (dugum instanceof Text) {
            return dugum.parentElement;
        }

        return null;
    }

    // Eklenti icin standart bilgi logu basar.
    function gunlukYaz(mesaj, veri) {
        console.info("[MiniMesaj]", mesaj, veri || "");
    }

    kapsam.domYardimcilari = {
        temizMetin,
        kucukHarfeCevir,
        metinListesindenBiriGeciyorMu,
        gidenMesajMi,
        tumSecicilerdenElemanTopla,
        essizElemanlariDondur,
        gorunurMu,
        ozellikMetniniAl,
        birlesikEtiketMetniniAl,
        elemanMetniniOku,
        olaylariTetikle,
        icerikSeciminiAyarla,
        bekle,
        dugumuElemanaDonustur,
        gunlukYaz
    };
})();
