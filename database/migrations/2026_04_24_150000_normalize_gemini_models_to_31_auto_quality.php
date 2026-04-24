<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const TARGET_MODEL = 'gemini-3.1-auto-quality';
    private const LEGACY_MODEL = 'gemini-2.5-flash';

    public function up(): void
    {
        DB::table('ai_ayarlar')
            ->where('saglayici_tipi', 'gemini')
            ->orderBy('id')
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    $updates = [];

                    if ($this->shouldNormalizeGeminiModel($row->model_adi ?? null)) {
                        $updates['model_adi'] = self::TARGET_MODEL;
                    }

                    if (($row->yedek_saglayici_tipi ?? null) === 'gemini'
                        && $this->shouldNormalizeGeminiModel($row->yedek_model_adi ?? null)) {
                        $updates['yedek_model_adi'] = self::TARGET_MODEL;
                    }

                    if ($updates !== []) {
                        DB::table('ai_ayarlar')->where('id', $row->id)->update($updates);
                    }
                }
            });

        DB::table('ai_engine_configs')
            ->where('saglayici_tipi', 'gemini')
            ->orderBy('id')
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    if (!$this->shouldNormalizeGeminiModel($row->model_adi ?? null)) {
                        continue;
                    }

                    DB::table('ai_engine_configs')
                        ->where('id', $row->id)
                        ->update(['model_adi' => self::TARGET_MODEL]);
                }
            });

        DB::table('ai_persona_profiles')
            ->orderBy('id')
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    $metadata = $this->decodeMetadata($row->metadata ?? null);
                    $currentModel = $metadata['model_adi'] ?? null;

                    if ($currentModel !== null && !$this->shouldNormalizeGeminiModel($currentModel)) {
                        continue;
                    }

                    $metadata['model_adi'] = self::TARGET_MODEL;

                    DB::table('ai_persona_profiles')
                        ->where('id', $row->id)
                        ->update([
                            'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        ]);
                }
            });
    }

    public function down(): void
    {
        DB::table('ai_ayarlar')
            ->where('saglayici_tipi', 'gemini')
            ->where('model_adi', self::TARGET_MODEL)
            ->update(['model_adi' => self::LEGACY_MODEL]);

        DB::table('ai_ayarlar')
            ->where('yedek_saglayici_tipi', 'gemini')
            ->where('yedek_model_adi', self::TARGET_MODEL)
            ->update(['yedek_model_adi' => self::LEGACY_MODEL]);

        DB::table('ai_engine_configs')
            ->where('saglayici_tipi', 'gemini')
            ->where('model_adi', self::TARGET_MODEL)
            ->update(['model_adi' => self::LEGACY_MODEL]);

        DB::table('ai_persona_profiles')
            ->orderBy('id')
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    $metadata = $this->decodeMetadata($row->metadata ?? null);

                    if (($metadata['model_adi'] ?? null) !== self::TARGET_MODEL) {
                        continue;
                    }

                    $metadata['model_adi'] = self::LEGACY_MODEL;

                    DB::table('ai_persona_profiles')
                        ->where('id', $row->id)
                        ->update([
                            'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        ]);
                }
            });
    }

    private function shouldNormalizeGeminiModel(?string $value): bool
    {
        $normalized = Str::lower(trim((string) $value));

        if ($normalized === self::TARGET_MODEL) {
            return false;
        }

        if ($normalized === '') {
            return true;
        }

        return Str::startsWith($normalized, 'gemini');
    }

    private function decodeMetadata(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
};
