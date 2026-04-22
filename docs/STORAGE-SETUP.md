# Storage Configuration Guide

Tüm medya yükleme ayarlarını `config/storage.php` ve `.env` dosyalarından kontrol edebilirsin.

## Hızlı Başlangıç

### 1. Upload Limitlerini Ayarla

`.env` dosyasında şu değerleri düzenle:

```env
# Maksimum dosya boyutu (MB)
STORAGE_MAX_FILE_SIZE_MB=100
STORAGE_MAX_VIDEO_SIZE_MB=100
STORAGE_MAX_IMAGE_SIZE_MB=50

# Nginx client_max_body_size (MB)
# Not: Bunu değiştirdikten sonra docker-compose.yml veya nginx.conf'u güncelle
STORAGE_NGINX_MAX_BODY_MB=100
```

**Not:** `STORAGE_NGINX_MAX_BODY_MB`, sunucudaki gerçek limiti belirtir. Nginx tarafında da bu değer ayarlanmalıdır.

### 2. Depolama Disk'ini Seç

```env
# Hangi disk'i kullanacak?
# Seçenekler: local, public, s3
STORAGE_DISK=public
```

- **public**: `storage/app/public` (lokal, symlink üzerinden erişilebilir)
- **local**: `storage/app/private` (lokal, doğrudan erişilemez)
- **s3**: AWS S3 bulut depolama

### 3. Geçici Dosyalar Temizliğini Ayarla

```env
# Kaç gün sonra geçici dosyalar silinsin?
STORAGE_TEMP_RETENTION_DAYS=7

# Kaç gün sonra başarısız upload dosyaları silinsin?
STORAGE_FAILED_UPLOADS_DAYS=30
```

## Depolama Ayarlarında Neler Var?

### İzin Verilen Formatlar

**Video Formatları** (`config/storage.php`):
```php
'allowed_video_formats' => [
    'mp4',    // H.264 codec (en uyumlu)
    'mov',    // Apple QuickTime
    'avi',    // Windows AVI
    'webm',   // Web Video Format
    'm4v',    // iTunes/Apple video
    '3gp',    // Mobile phone video
    'mkv',    // Matroska format
    'flv',    // Flash video
    'wmv',    // Windows Media Video
]
```

**Resim Formatları**:
```php
'allowed_image_formats' => [
    'jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'heif', 'bmp', 'svg'
]
```

### Depolama Yolları

Fotoğraflar ve videolar farklı klasörlere depolanır:

```php
'paths' => [
    'profile_photos' => 'fotograflar',    // /storage/app/public/fotograflar/{user_id}
    'profile_videos' => 'videolar',       // /storage/app/public/videolar/{user_id}
    'temp' => 'temp',                     // Geçici compression dosyaları
    'compressed' => 'compressed',         // İşlenmiş videolar
]
```

## Nginx Konfigürasyonu

`docker/nginx.conf` dosyasında `client_max_body_size` ayarlanır:

```nginx
client_max_body_size 20M;
```

**Bunu değiştirmek için:**

### Docker Compose Kullanıyorsan

1. `docker/nginx.conf` dosyasını düzenle:
   ```nginx
   client_max_body_size 100M;  # İstediğin boyut
   ```

2. Docker container'ı rebuild et:
   ```bash
   docker-compose down
   docker-compose up --build
   ```

### Docker Kullanmıyorsan (Lokal/Herd)

Nginx konfigürasyonunu doğrudan düzenle (Herd kurulumu):
```
/etc/nginx/conf.d/your-domain.conf  # (veya benzeri yol)
```

Sonra Nginx'i reload et:
```bash
sudo systemctl reload nginx
# veya
sudo nginx -s reload
```

## PHP Konfigürasyonu (Opsiyonel)

`php.ini` dosyasındaki bu ayarlar da önemlidir:

```ini
; POST verisi max boyutu
post_max_size = 100M

; File upload max boyutu
upload_max_filesize = 100M

; Script timeout (büyük dosyalar için)
max_execution_time = 300
```

**Not:** Docker'da `php.ini` dosyası PHP container'ında bulunur ve değiştirmek için Dockerfile'da yapılmalıdır.

## S3 Bulut Depolama (Opsiyonel)

S3 kullanmak istiyorsan `.env`'de:

```env
STORAGE_DISK=s3

AWS_ACCESS_KEY_ID=your-key
AWS_SECRET_ACCESS_KEY=your-secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-bucket
AWS_URL=https://your-bucket.s3.amazonaws.com
```

## Veritabanı Kolonu

`user_fotografilar` tablosunda depolanan bilgiler:

```sql
- dosya_yolu    → Depolanan dosya yolu (DB'de tutulur)
- medya_tipi    → 'fotograf' veya 'video'
- mime_tipi     → 'image/jpeg', 'video/mp4' vs.
```

## Hata Ayıklama

### HTTP 413 Payload Too Large

**Semptom:** Video yükleme başarısız, "HTTP 413" hatası

**Çözüm:**
1. Nginx client_max_body_size'ı kontrol et (en az 100M olmalı)
2. PHP post_max_size'ı kontrol et
3. Sunucuya bağlı diğer limitleri kontrol et (reverse proxy vs.)

### Depolamada Yer Kalmadı

**Semptom:** Upload başarısız, disk dolu hatası

**Çözüm:**
1. Geçici dosyaları sil: `storage/app/temp/`
2. Eski videaları yükle: `storage/app/public/videolar/`
3. Disk alanını kontrol et: `df -h`

## Cleanup Jobs

Geçici dosyaları otomatik temizlemek için scheduler'ı ayarla.

**`app/Console/Kernel.php`:**
```php
$schedule->command('storage:cleanup-temp')->daily();
```

Bu komut `STORAGE_TEMP_RETENTION_DAYS` gün eski dosyaları siler.

## Özet Tablo

| Ayar | Dosya | Değer | Örnek |
|------|-------|-------|--------|
| Max dosya boyutu | `.env` | MB | 100 |
| Video limitesi | `.env` | MB | 100 |
| Nginx limiti | `docker/nginx.conf` | MB | 20 |
| Storage disk | `.env` | Seçenek | public |
| Video yolu | `config/storage.php` | String | fotograflar/ |
| Temp retention | `.env` | Gün | 7 |

---

**Sorular mı var?** `docs/` klasöründeki diğer dosyalara bak veya logs'u kontrol et:
- Laravel logs: `storage/logs/laravel.log`
- Nginx logs: `/var/log/nginx/error.log` (Docker'da: `docker logs <container_id>`)
