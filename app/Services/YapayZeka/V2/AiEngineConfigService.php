<?php

namespace App\Services\YapayZeka\V2;

use App\Models\AiEngineConfig;

class AiEngineConfigService
{
    public function activeConfig(): AiEngineConfig
    {
        $existing = AiEngineConfig::query()
            ->where('aktif_mi', true)
            ->latest('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        return AiEngineConfig::query()->create([
            'ad' => 'Varsayilan Motor',
            'saglayici_tipi' => 'gemini',
            'model_adi' => 'gemini-2.5-flash',
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
            'model_adi' => $config->model_adi ?: 'gemini-2.5-flash',
            'temperature' => (float) $config->temperature,
            'top_p' => (float) $config->top_p,
            'max_output_tokens' => (int) $config->max_output_tokens,
        ];
    }
}
