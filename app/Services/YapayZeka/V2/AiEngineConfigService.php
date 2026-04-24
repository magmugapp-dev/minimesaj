<?php

namespace App\Services\YapayZeka\V2;

use App\Models\AiEngineConfig;
use App\Services\YapayZeka\GeminiModelPolicy;

class AiEngineConfigService
{
    public function activeConfig(): AiEngineConfig
    {
        $existing = AiEngineConfig::query()
            ->where('aktif_mi', true)
            ->latest('id')
            ->first();

        if ($existing) {
            $normalizedModel = $this->normalizeGeminiModel($existing->saglayici_tipi, $existing->model_adi);

            if ($normalizedModel !== $existing->model_adi) {
                $existing->forceFill(['model_adi' => $normalizedModel])->save();
                $existing->refresh();
            }

            return $existing;
        }

        return AiEngineConfig::query()->create([
            'ad' => 'Varsayilan Motor',
            'saglayici_tipi' => 'gemini',
            'model_adi' => GeminiModelPolicy::AUTO_QUALITY,
            'aktif_mi' => true,
            'temperature' => 0.9,
            'top_p' => 0.95,
            'max_output_tokens' => 1024,
            'guardrail_modu' => 'strict',
            'sistem_komutu' => 'Dogal, sakin, insan gibi ve guvenli sohbet et.',
        ]);
    }

    public function modelParameters(AiEngineConfig $config): array
    {
        return [
            'model_adi' => $this->normalizeGeminiModel($config->saglayici_tipi, $config->model_adi),
            'temperature' => (float) $config->temperature,
            'top_p' => (float) $config->top_p,
            'max_output_tokens' => (int) $config->max_output_tokens,
        ];
    }

    private function normalizeGeminiModel(?string $provider, ?string $model): ?string
    {
        if ($provider !== 'gemini') {
            return $model;
        }

        return GeminiModelPolicy::normalizeConfiguredModel($model);
    }
}
