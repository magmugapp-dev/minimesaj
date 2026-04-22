// Alertify bildirimlerini tek yerden yoneten servisi izole kapsamda kurar.
(() => {
    const kapsam = globalThis.MiniMesaj;

    if (!kapsam?.domYardimcilari) {
        return;
    }

    const { gunlukYaz } = kapsam.domYardimcilari;
    const bildirimIslevleri = {
        bilgi: "message",
        basari: "success",
        uyari: "warning",
        hata: "error"
    };

    // Global Alertify nesnesini varsa dondurur.
    function alertifyNesnesiniAl() {
        return typeof globalThis.alertify !== "undefined" ? globalThis.alertify : null;
    }

    // Alertify ayarlarini ilk kullanimda bir kez uygular.
    function hazirla() {
        const alertifyNesnesi = alertifyNesnesiniAl();

        if (!alertifyNesnesi || kapsam.durum.alertifyHazirlandi) {
            return;
        }

        alertifyNesnesi.set("notifier", "position", "top-right");
        alertifyNesnesi.set("notifier", "delay", 4);
        kapsam.durum.alertifyHazirlandi = true;
    }

    // Aynı anahtar icin bildirim tekrarini belli sure boyunca engeller.
    function tekrarBildirimMi(anahtar) {
        if (!anahtar) {
            return false;
        }

        const simdi = Date.now();
        const oncekiZaman = kapsam.durum.sonBildirimZamanlari.get(anahtar) || 0;

        if (simdi - oncekiZaman < kapsam.sabitler.bildirimTekrarBeklemeMs) {
            return true;
        }

        kapsam.durum.sonBildirimZamanlari.set(anahtar, simdi);
        return false;
    }

    // Bildirim turunu Alertify islevine mapleyip ekrana gosterir.
    function goster(tur, mesaj, anahtar) {
        hazirla();

        if (tekrarBildirimMi(anahtar)) {
            return;
        }

        const alertifyNesnesi = alertifyNesnesiniAl();

        if (!alertifyNesnesi) {
            gunlukYaz("Alertify bulunamadi", { tur, mesaj });
            return;
        }

        const islev = bildirimIslevleri[tur] || bildirimIslevleri.bilgi;

        alertifyNesnesi[islev](mesaj);
        gunlukYaz("Bildirim gosterildi", { tur, mesaj });
    }

    // Belirli bir tur icin tekrar kullanilabilir bildirim fonksiyonu uretir.
    function bildirimGostericisiOlustur(tur) {
        return function bildir(mesaj, anahtar) {
            goster(tur, mesaj, anahtar);
        };
    }

    kapsam.bildirimServisi = {
        hazirla,
        bilgi: bildirimGostericisiOlustur("bilgi"),
        basari: bildirimGostericisiOlustur("basari"),
        uyari: bildirimGostericisiOlustur("uyari"),
        hata: bildirimGostericisiOlustur("hata")
    };
})();
