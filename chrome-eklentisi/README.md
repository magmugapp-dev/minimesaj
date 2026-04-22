# MiniMesaj Chrome Eklentisi

Bu klasor, Instagram DM sayfasini dinleyen ve yeni gelen mesajlari gercek zamanli algilayip sabit cevap metni gonderebilen Chrome eklentisini icerir.

## Icerik

- `manifest.json`: Chrome eklentisi tanimi.
- `popup.html`, `popup.css`, `popup.js`: Sadece sistem ac/kapat ve DM sayfasina git kontrolu.
- `icerik/`: AlertifyJS entegrasyonu dahil moduler icerik betigi katmani.
- `icerik/kuyruk-servisi.js`: Bekleyen sohbetleri tekilleyip sirali isleyen kuyruk katmani.
- `icerik-betigi.js`: Sadece icerik modullerini baslatan ince giris dosyasi.
- `package.json`: Uzanti icindeki gelistirme bagimliliklari.

## Kurulum

1. Chrome icinde `chrome://extensions` sayfasini ac.
2. Gelistirici modunu etkinlestir.
3. Bu klasorde bir kez `npm install` calistir.
4. `Paketlenmemis ogeyi yukle` secenegi ile bu klasoru sec.
5. Popup icindeki anahtari ac.
6. Eklenti acik bir DM sekmesi bulursa onu one getirip yeniler; bulamazsa yeni bir DM sekmesi acar.

## Notlar

- Popup artik ayar paneli degildir; yalnizca sistemi acip kapatir ve DM sayfasini acar.
- Icerik betigi interval yerine `MutationObserver` ile DOM degisikliklerini gercek zamanli izler.
- DM sekmesi arka planda kaldiginda uzanti dusuk frekansli guvenlik taramasi yapar; sekme yeniden gorundugunde gozlemci kendini otomatik yeniler.
- Proje bildirimleri Instagram sayfasi icinde AlertifyJS toast bildirimleri olarak gosterilir.
- Icerik mantigi gelistirmeyi kolaylastirmak icin `icerik/` altinda modullere ayrildi.
- Okunmamis sohbetler ayri kuyruk katmaninda tekillenerek sirali sekilde islenir.
- Otomatik cevap metni kod icinde sabit tutulur.
- Ayni sohbet icinde ayni mesaj onizlemesi ikinci kez islenmez; yeni mesaj imzasi degisirse yeniden cevaplanabilir.
- Chrome ve Instagram gizli sekmede veri akislarini yavaslatabilecegi icin uzanti, DM sekmesi acik oldugu surece toparlanma odakli calisir.
- Instagram arayuzu degistikce seciciler guncellenmek zorunda kalabilir.
