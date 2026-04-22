<?php

namespace App\Console\Commands;

use App\Services\AyarServisi;
use Illuminate\Console\Command;

class StorageSyncNginxUploadLimit extends Command
{
    protected $signature = 'storage:sync-nginx-upload-limit
                            {--apply : Nginx config dosyasina yaz}
                            {--reload : Uygulamadan sonra reload komutunu calistir}
                            {--mb= : Panel yerine manuel MB degeri kullan}
                            {--path= : Nginx config dosya yolu}
                            {--reload-command= : Nginx reload komutu}';

    protected $description = 'Depolama panel ayarindaki upload limitini Nginx client_max_body_size ile senkronlar.';

    public function handle(AyarServisi $ayarServisi): int
    {
        $panelLimitMb = (int) $ayarServisi->al(
            'nginx_max_body_mb',
            $ayarServisi->al('max_video_boyut_mb', config('storage.nginx.client_max_body_size_mb', 100))
        );

        $limitMb = (int) ($this->option('mb') ?: $panelLimitMb);
        if ($limitMb < 1) {
            $this->error('Limit MB degeri 1 veya daha buyuk olmalidir.');

            return self::FAILURE;
        }

        $configuredPath = trim((string) config('storage.nginx.config_path'));
        $path = trim((string) $this->option('path'));
        if ($path === '') {
            $path = $configuredPath !== '' ? $configuredPath : base_path('docker/nginx.conf');
        }

        $reloadCommand = trim((string) ($this->option('reload-command') ?: config('storage.nginx.reload_command')));
        $newDirective = "client_max_body_size {$limitMb}M;";

        $this->line('Panel nginx_max_body_mb: ' . $panelLimitMb . ' MB');
        $this->line('Kullanilan limit: ' . $limitMb . ' MB');
        $this->line('Hedef dosya: ' . $path);
        $this->line('Yeni directive: ' . $newDirective);

        if (!$this->option('apply')) {
            $this->info('Dry-run tamamlandi. Uygulamak icin --apply kullan.');

            return self::SUCCESS;
        }

        if (!is_file($path)) {
            $this->error('Nginx config dosyasi bulunamadi: ' . $path);

            return self::FAILURE;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            $this->error('Nginx config dosyasi okunamadi: ' . $path);

            return self::FAILURE;
        }

        $updated = preg_replace(
            '/client_max_body_size\s+\d+[kKmMgG]?\s*;/',
            $newDirective,
            $content,
            1,
            $count
        );

        if ($updated === null || $count < 1) {
            $this->error('client_max_body_size satiri bulunamadi, dosya guncellenmedi.');

            return self::FAILURE;
        }

        if (file_put_contents($path, $updated) === false) {
            $this->error('Nginx config dosyasina yazilamadi: ' . $path);

            return self::FAILURE;
        }

        $this->info('Nginx config guncellendi.');

        if (!$this->option('reload')) {
            $this->warn('Degisikligi etkinlestirmek icin Nginx reload gerekli. --reload ile otomatik calistirabilirsin.');

            return self::SUCCESS;
        }

        if ($reloadCommand === '') {
            $this->warn('Reload komutu tanimli degil. --reload-command ile gec veya STORAGE_NGINX_RELOAD_COMMAND ayarla.');

            return self::SUCCESS;
        }

        $this->line('Reload komutu calistiriliyor: ' . $reloadCommand);

        exec($reloadCommand, $output, $exitCode);

        if ($exitCode !== 0) {
            $this->error('Nginx reload basarisiz oldu (exit code: ' . $exitCode . ').');
            if (!empty($output)) {
                $this->line(implode(PHP_EOL, $output));
            }

            return self::FAILURE;
        }

        $this->info('Nginx reload basarili.');

        return self::SUCCESS;
    }
}
