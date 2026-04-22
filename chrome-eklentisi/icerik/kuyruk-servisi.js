// Bekleyen sohbetleri tekilleyip sira ile isleyen kuyruk servisini tanimlar.
(() => {
    const kapsam = globalThis.MiniMesaj;

    if (!kapsam?.durum) {
        return;
    }

    const { durum } = kapsam;

    // Tek bir sohbeti kuyruğa ekler veya varsa güncel veriyle yeniler.
    function sohbeteSirayaEkle(sohbet) {
        if (!sohbet?.sohbetKimligi) {
            return false;
        }

        if (durum.kuyruktakiSohbetKimlikleri.has(sohbet.sohbetKimligi)) {
            durum.bekleyenSohbetKuyrugu = durum.bekleyenSohbetKuyrugu.map((siradakiSohbet) => {
                if (siradakiSohbet.sohbetKimligi !== sohbet.sohbetKimligi) {
                    return siradakiSohbet;
                }

                return { ...siradakiSohbet, ...sohbet };
            });

            return false;
        }

        durum.kuyruktakiSohbetKimlikleri.add(sohbet.sohbetKimligi);
        durum.bekleyenSohbetKuyrugu.push(sohbet);
        return true;
    }

    // Birden fazla sohbeti kuyruğa sırayla ekler.
    function sohbetleriSirayaEkle(sohbetler) {
        return (sohbetler || []).reduce((eklenenSayi, sohbet) => {
            return eklenenSayi + Number(sohbeteSirayaEkle(sohbet));
        }, 0);
    }

    // Kuyruktaki ilk sohbeti alır ve kuyruktan çıkarır.
    function siradakiSohbetiAl() {
        const siradakiSohbet = durum.bekleyenSohbetKuyrugu.shift() || null;

        if (siradakiSohbet?.sohbetKimligi) {
            durum.kuyruktakiSohbetKimlikleri.delete(siradakiSohbet.sohbetKimligi);
        }

        return siradakiSohbet;
    }

    // Belirli bir sohbet kaydını kuyruktan siler.
    function kuyruktanSil(sohbetKimligi) {
        if (!sohbetKimligi) {
            return;
        }

        durum.bekleyenSohbetKuyrugu = durum.bekleyenSohbetKuyrugu.filter((sohbet) => sohbet.sohbetKimligi !== sohbetKimligi);
        durum.kuyruktakiSohbetKimlikleri.delete(sohbetKimligi);
    }

    // Kuyrukta bekleyen sohbet var mı kontrol eder.
    function kuyruktaMi(sohbetKimligi) {
        return durum.kuyruktakiSohbetKimlikleri.has(sohbetKimligi);
    }

    // Kuyruktaki toplam bekleyen sohbet sayısını döndürür.
    function kuyrukUzunlugu() {
        return durum.bekleyenSohbetKuyrugu.length;
    }

    // Tüm kuyruk içeriğini temizler.
    function kuyruguTemizle() {
        durum.bekleyenSohbetKuyrugu = [];
        durum.kuyruktakiSohbetKimlikleri.clear();
    }

    kapsam.kuyrukServisi = {
        sohbeteSirayaEkle,
        sohbetleriSirayaEkle,
        siradakiSohbetiAl,
        kuyruktanSil,
        kuyruktaMi,
        kuyrukUzunlugu,
        kuyruguTemizle
    };
})();
