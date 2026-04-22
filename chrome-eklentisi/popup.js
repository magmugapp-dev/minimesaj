const varsayilanDurum = {
    sistemEtkin: false
};

const apiTemelUrl = 'https://minimesaj.test/api';
const instagramDmKokAdresi = "https://www.instagram.com/direct/";
const instagramDmAdresi = "https://www.instagram.com/direct/inbox/";
const dmSekmeDeseni = "https://www.instagram.com/direct/*";

const elemanlar = {};

document.addEventListener("DOMContentLoaded", baslat);

// ── Baslangic ────────────────────────────────────────────────────────

async function baslat() {
    elemanlariYakala();

    const depoVeri = await chrome.storage.local.get(['apiJetonu', 'aktifHesapId', 'aktifHesapKullaniciAdi']);
    const durum = await chrome.storage.sync.get(varsayilanDurum);

    if (depoVeri.apiJetonu) {
        anaPaneliGoster(depoVeri, durum.sistemEtkin);
    } else {
        girisPaneliGoster();
    }
}

function elemanlariYakala() {
    elemanlar.girisPanel = document.getElementById('girisPanel');
    elemanlar.anaPanel = document.getElementById('anaPanel');
    elemanlar.kullaniciAdiAlani = document.getElementById('kullaniciAdiAlani');
    elemanlar.sifreAlani = document.getElementById('sifreAlani');
    elemanlar.girisHatasi = document.getElementById('girisHatasi');
    elemanlar.girisDugmesi = document.getElementById('girisDugmesi');
    elemanlar.hesapDurumMetni = document.getElementById('hesapDurumMetni');
    elemanlar.hesapIslemDugmesi = document.getElementById('hesapIslemDugmesi');
    elemanlar.hesapBaglamaFormu = document.getElementById('hesapBaglamaFormu');
    elemanlar.igKullaniciAdiAlani = document.getElementById('igKullaniciAdiAlani');
    elemanlar.hesapBaglaHatasi = document.getElementById('hesapBaglaHatasi');
    elemanlar.hesapBaglaDugmesi = document.getElementById('hesapBaglaDugmesi');
    elemanlar.hesapIptalDugmesi = document.getElementById('hesapIptalDugmesi');
    elemanlar.sistemAnahtari = document.getElementById('sistemAnahtari');
    elemanlar.durumMetni = document.getElementById('durumMetni');
    elemanlar.dmDugmesi = document.getElementById('dmDugmesi');
    elemanlar.baglantiBilgisi = document.getElementById('baglantiBilgisi');
    elemanlar.cikisDugmesi = document.getElementById('cikisDugmesi');
}

// ── Giris Paneli ─────────────────────────────────────────────────────

function girisPaneliGoster() {
    elemanlar.girisPanel.classList.remove('gizli');
    elemanlar.anaPanel.classList.add('gizli');
    elemanlar.girisHatasi.classList.add('gizli');

    elemanlar.girisDugmesi.addEventListener('click', girisYap);
    elemanlar.sifreAlani.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') girisYap();
    });
}

async function girisYap() {
    const kullaniciAdi = elemanlar.kullaniciAdiAlani.value.trim();
    const sifre = elemanlar.sifreAlani.value;

    if (!kullaniciAdi || !sifre) {
        hataGoster(elemanlar.girisHatasi, 'Kullanici adi ve sifre gerekli.');
        return;
    }

    elemanlar.girisDugmesi.disabled = true;
    elemanlar.girisDugmesi.textContent = 'Giris yapiliyor...';

    try {
        const yanit = await fetch(`${apiTemelUrl}/auth/giris`, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({
                kullanici_adi: kullaniciAdi,
                password: sifre,
                istemci_tipi: 'extension'
            })
        });

        const veri = await yanit.json().catch(() => null);

        if (!yanit.ok) {
            const mesaj = veri?.errors?.kullanici_adi?.[0] || veri?.message || 'Giris basarisiz.';
            hataGoster(elemanlar.girisHatasi, mesaj);
            return;
        }

        await chrome.storage.local.set({ apiJetonu: veri.token });

        // Hesaplari kontrol et
        const hesaplar = await hesaplariGetir(veri.token);

        if (hesaplar.length > 0) {
            const ilkHesap = hesaplar[0];
            await chrome.storage.local.set({
                aktifHesapId: ilkHesap.id,
                aktifHesapKullaniciAdi: ilkHesap.instagram_kullanici_adi
            });
        }

        const depoVeri = await chrome.storage.local.get(['apiJetonu', 'aktifHesapId', 'aktifHesapKullaniciAdi']);
        const durum = await chrome.storage.sync.get(varsayilanDurum);
        anaPaneliGoster(depoVeri, durum.sistemEtkin);
    } catch (yakalananHata) {
        hataGoster(elemanlar.girisHatasi, 'Sunucuya baglanilamadi.');
    } finally {
        elemanlar.girisDugmesi.disabled = false;
        elemanlar.girisDugmesi.textContent = 'Giris yap';
    }
}

async function hesaplariGetir(jeton) {
    try {
        const yanit = await fetch(`${apiTemelUrl}/instagram/hesaplar`, {
            headers: {
                'Accept': 'application/json',
                'Authorization': `Bearer ${jeton}`
            }
        });

        if (!yanit.ok) return [];
        const veri = await yanit.json();
        return veri.data || [];
    } catch (_hata) {
        return [];
    }
}

// ── Ana Panel ────────────────────────────────────────────────────────

function anaPaneliGoster(depoVeri, sistemEtkin) {
    elemanlar.girisPanel.classList.add('gizli');
    elemanlar.anaPanel.classList.remove('gizli');

    hesapDurumunuGuncelle(depoVeri.aktifHesapId, depoVeri.aktifHesapKullaniciAdi);
    sistemDurumunuUygula(Boolean(sistemEtkin));

    elemanlar.sistemAnahtari.addEventListener('change', sistemDurumunuDegistir);
    elemanlar.dmDugmesi.addEventListener('click', dmSayfasinaGit);
    elemanlar.cikisDugmesi.addEventListener('click', cikisYap);
    elemanlar.hesapIslemDugmesi.addEventListener('click', hesapIsleminiBaslat);
    elemanlar.hesapBaglaDugmesi.addEventListener('click', hesapBagla);
    elemanlar.hesapIptalDugmesi.addEventListener('click', hesapBaglamaFormunuGizle);
}

// ── Hesap Yonetimi ───────────────────────────────────────────────────

function hesapDurumunuGuncelle(hesapId, kullaniciAdi) {
    if (hesapId && kullaniciAdi) {
        elemanlar.hesapDurumMetni.textContent = `@${kullaniciAdi}`;
        elemanlar.hesapIslemDugmesi.textContent = 'Kaldir';
        elemanlar.baglantiBilgisi.textContent = 'Hesap bagli';
        elemanlar.baglantiBilgisi.classList.add('bagli');
    } else {
        elemanlar.hesapDurumMetni.textContent = 'Hesap bagli degil';
        elemanlar.hesapIslemDugmesi.textContent = 'Bagla';
        elemanlar.baglantiBilgisi.textContent = 'Hesap bagli degil';
        elemanlar.baglantiBilgisi.classList.remove('bagli');
    }
}

async function hesapIsleminiBaslat() {
    const depoVeri = await chrome.storage.local.get(['apiJetonu', 'aktifHesapId']);

    if (depoVeri.aktifHesapId) {
        await hesapKaldir(depoVeri.apiJetonu, depoVeri.aktifHesapId);
    } else {
        elemanlar.hesapBaglamaFormu.classList.remove('gizli');
        elemanlar.hesapBaglaHatasi.classList.add('gizli');
        elemanlar.igKullaniciAdiAlani.focus();
    }
}

function hesapBaglamaFormunuGizle() {
    elemanlar.hesapBaglamaFormu.classList.add('gizli');
    elemanlar.igKullaniciAdiAlani.value = '';
}

async function hesapBagla() {
    const kullaniciAdi = elemanlar.igKullaniciAdiAlani.value.trim();

    if (!kullaniciAdi) {
        hataGoster(elemanlar.hesapBaglaHatasi, 'Kullanici adi gerekli.');
        return;
    }

    const depoVeri = await chrome.storage.local.get(['apiJetonu']);

    try {
        elemanlar.hesapBaglaDugmesi.disabled = true;

        const yanit = await fetch(`${apiTemelUrl}/instagram/hesaplar`, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${depoVeri.apiJetonu}`
            },
            body: JSON.stringify({ instagram_kullanici_adi: kullaniciAdi })
        });

        const veri = await yanit.json().catch(() => null);

        if (!yanit.ok) {
            hataGoster(elemanlar.hesapBaglaHatasi, veri?.message || 'Hesap baglanamadi.');
            return;
        }

        const hesap = veri.data || veri;

        await chrome.storage.local.set({
            aktifHesapId: hesap.id,
            aktifHesapKullaniciAdi: kullaniciAdi
        });

        hesapDurumunuGuncelle(hesap.id, kullaniciAdi);
        hesapBaglamaFormunuGizle();
    } catch (_hata) {
        hataGoster(elemanlar.hesapBaglaHatasi, 'Sunucuya baglanilamadi.');
    } finally {
        elemanlar.hesapBaglaDugmesi.disabled = false;
    }
}

async function hesapKaldir(jeton, hesapId) {
    try {
        await fetch(`${apiTemelUrl}/instagram/hesaplar/${hesapId}`, {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'Authorization': `Bearer ${jeton}`
            }
        });

        await chrome.storage.local.remove(['aktifHesapId', 'aktifHesapKullaniciAdi']);
        hesapDurumunuGuncelle(null, null);
    } catch (_hata) {
        // Sessizce devam et
    }
}

// ── Cikis ────────────────────────────────────────────────────────────

async function cikisYap() {
    const depoVeri = await chrome.storage.local.get(['apiJetonu']);

    try {
        if (depoVeri.apiJetonu) {
            await fetch(`${apiTemelUrl}/auth/cikis`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${depoVeri.apiJetonu}`
                }
            });
        }
    } catch (_hata) {
        // Sunucuya ulasilamadi, yine de yerel temizlik yap
    }

    await chrome.storage.local.remove(['apiJetonu', 'aktifHesapId', 'aktifHesapKullaniciAdi']);
    girisPaneliGoster();
}

// ── Sistem Anahtari ──────────────────────────────────────────────────
async function sistemDurumunuDegistir() {
    const sistemEtkin = elemanlar.sistemAnahtari.checked;

    await chrome.storage.sync.set({ sistemEtkin });
    sistemDurumunuUygula(sistemEtkin);

    if (!sistemEtkin) {
        return;
    }

    await dmSekmesiniHazirla({
        gelenKutusuZorunlu: false,
        yenile: true
    });
}

// Kullanici isterse DM gelen kutusunu odaklayip acik sekmeye tasir.
async function dmSayfasinaGit() {
    await dmSekmesiniHazirla({
        gelenKutusuZorunlu: true,
        yenile: false
    });
}

// Mevcut DM sekmesini bulur, yoksa yeni sekme acar, varsa istenen davranisi uygular.
async function dmSekmesiniHazirla(secenekler) {
    const dmSekmesi = await dmSekmesiniBul();

    if (!dmSekmesi?.id) {
        await dmSekmesiOlustur();
        return;
    }

    await dmSekmesiniEtkinlestir(dmSekmesi, secenekler);
}

// Instagram DM gelen kutusunu yeni ve aktif bir sekmede acar.
async function dmSekmesiOlustur() {
    await chrome.tabs.create({
        url: instagramDmAdresi,
        active: true
    });
}

// Bulunan DM sekmesini gunceller, odaklar ve gerekiyorsa yeniler.
async function dmSekmesiniEtkinlestir(dmSekmesi, secenekler) {
    const guncellemeBilgisi = dmSekmesiGuncellemeBilgisiniOlustur(secenekler);

    await chrome.tabs.update(dmSekmesi.id, guncellemeBilgisi);
    await sekmePenceresiniOdakla(dmSekmesi);

    if (secenekler.yenile && !secenekler.gelenKutusuZorunlu) {
        await chrome.tabs.reload(dmSekmesi.id);
    }
}

// DM sekmesi icin gerekli guncelleme parametrelerini olusturur.
function dmSekmesiGuncellemeBilgisiniOlustur(secenekler) {
    const guncellemeBilgisi = {
        active: true
    };

    if (secenekler.gelenKutusuZorunlu) {
        guncellemeBilgisi.url = instagramDmAdresi;
    }

    return guncellemeBilgisi;
}

// DM sekmesinin bulundugu pencereyi one getirir.
async function sekmePenceresiniOdakla(dmSekmesi) {
    if (!Number.isInteger(dmSekmesi.windowId)) {
        return;
    }

    await chrome.windows.update(dmSekmesi.windowId, { focused: true });
}

// Acik sekmeler arasinda Instagram DM sekmesini arar.
async function dmSekmesiniBul() {
    const sekmeler = await chrome.tabs.query({
        url: dmSekmeDeseni
    });

    return sekmeler.find(instagramDmSekmesiMi) || null;
}

// Verilen sekmenin Instagram DM rotasinda olup olmadigini kontrol eder.
function instagramDmSekmesiMi(sekme) {
    return sekme.url?.startsWith(instagramDmKokAdresi);
}

// Arayuzdeki anahtari ve durum metnini senkron halde gunceller.
function sistemDurumunuUygula(sistemEtkin) {
    elemanlar.sistemAnahtari.checked = sistemEtkin;
    durumMetniniGuncelle(sistemEtkin);
}

// Sistem acik veya kapali bilgisini popup metnine yazar.
function durumMetniniGuncelle(sistemEtkin) {
    elemanlar.durumMetni.textContent = sistemEtkin ? "Sistem acik" : "Sistem kapali";
}

// Hata mesaji elemanini goster ve metnini ayarlar.
function hataGoster(eleman, mesaj) {
    eleman.textContent = mesaj;
    eleman.classList.remove('gizli');
}
