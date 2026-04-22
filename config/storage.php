<?php

/**
 * Storage Configuration
 *
 * Video ve medya dosyaları için depolama ayarları.
 * Tüm upload limitleri ve dosya tiplerini buradan kontrol edebilirsin.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Upload Limits
    |--------------------------------------------------------------------------
    |
    | Medya yükleme limitlerini MB cinsinden ayarla.
    |
    */

    'upload' => [
        // Maksimum dosya boyutu (MB)
        'max_file_size_mb' => env('STORAGE_MAX_FILE_SIZE_MB', 100),

        // Video yükleme limiti (MB)
        'max_video_size_mb' => env('STORAGE_MAX_VIDEO_SIZE_MB', 100),

        // Resim yükleme limiti (MB)
        'max_image_size_mb' => env('STORAGE_MAX_IMAGE_SIZE_MB', 50),

        // İzin verilen video formatlari
        'allowed_video_formats' => [
            'mp4',    // H.264 codec, en uyumlu
            'mov',    // Apple QuickTime
            'avi',    // Windows AVI
            'webm',   // Web Video Format
            'm4v',    // iTunes/Apple video
            '3gp',    // Mobile phone video
            'mkv',    // Matroska format
            'flv',    // Flash video
            'wmv',    // Windows Media Video
        ],

        // İzin verilen resim formatlari
        'allowed_image_formats' => [
            'jpg',
            'jpeg',
            'png',
            'gif',
            'webp',
            'heic',
            'heif',
            'bmp',
            'svg',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Video Compression Settings
    |--------------------------------------------------------------------------
    |
    | Sunucu tarafında video compression ayarları.
    | Bu ayarlar henüz kullanılmıyor (client-side compression mevcut).
    |
    */

    'compression' => [
        // Sunucuda video compression yapılsın mı?
        'enable_server_compression' => env('STORAGE_ENABLE_SERVER_COMPRESSION', false),

        // Encode timeout (saniye)
        'encode_timeout' => env('STORAGE_ENCODE_TIMEOUT', 600),

        // Hedef kalite (1-31, düşük = daha iyi kalite)
        'target_quality' => env('STORAGE_TARGET_QUALITY', 28),

        // İzin verilen codec'ler
        'allowed_codecs' => [
            'h264',   // H.264 - Uyumlu, yaygın
            'h265',   // H.265/HEVC - Daha verimli, eski cihazlarda sorun olabilir
            'vp9',    // Google VP9
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Paths
    |--------------------------------------------------------------------------
    |
    | Medya dosyaları depolama yolları.
    |
    */

    'paths' => [
        // Profil/dating fotoğrafları
        'profile_photos' => 'fotograflar',

        // Profil videoları
        'profile_videos' => 'videolar',

        // Temporary/processing dosyaları
        'temp' => 'temp',

        // Compressed/processed videolar
        'compressed' => 'compressed',
    ],

    /*
    |--------------------------------------------------------------------------
    | Disk Configuration
    |--------------------------------------------------------------------------
    |
    | Medya dosyaları için kullanılacak disk.
    |
    */

    'disk' => env('STORAGE_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Nginx Configuration
    |--------------------------------------------------------------------------
    |
    | Nginx client_max_body_size ayarı.
    | Docker/Nginx'de bu değer tanımlı olmalı, .env'den kontrol edebilirsin.
    |
    */

    'nginx' => [
        'client_max_body_size_mb' => env('STORAGE_NGINX_MAX_BODY_MB', 100),
        'config_path' => env('STORAGE_NGINX_CONFIG_PATH', base_path('docker/nginx.conf')),
        'reload_command' => env('STORAGE_NGINX_RELOAD_COMMAND', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cleanup Settings
    |--------------------------------------------------------------------------
    |
    | Eski/geçici dosyaları temizleme ayarları.
    |
    */

    'cleanup' => [
        // Kaç gün sonra geçici dosyalar silinsin?
        'temp_files_retention_days' => env('STORAGE_TEMP_RETENTION_DAYS', 7),

        // Kaç gün sonra failed uploads silinsin?
        'failed_uploads_retention_days' => env('STORAGE_FAILED_UPLOADS_DAYS', 30),
    ],

];
