// Icerik betigini izole kapsamda baslatir ve gozlemci servisini devreye alir.
(() => {
    const kapsam = globalThis.MiniMesaj;

    if (!kapsam?.gozlemciServisi) {
        console.warn("[MiniMesaj] Gozlemci servisi bulunamadi.");
        return;
    }

    kapsam.gozlemciServisi.baslat().catch((hata) => {
        console.error("[MiniMesaj] Baslatma hatasi", hata);
    });
})();
