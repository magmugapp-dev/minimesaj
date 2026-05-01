<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GeminiApiKey;
use App\Models\GeminiApiWarning;
use Illuminate\Http\Request;

class GeminiController extends Controller
{
    public function index(Request $request)
    {
        $warningsQuery = GeminiApiWarning::query()
            ->with('aiUser:id,ad,kullanici_adi,profil_resmi')
            ->latest('occurred_at');

        if ($code = trim((string) $request->input('error_code'))) {
            $warningsQuery->where('error_code', $code);
        }

        $keys = GeminiApiKey::query()
            ->orderByDesc('active')
            ->orderByDesc('priority')
            ->orderBy('last_used_at')
            ->get();

        $warnings = $warningsQuery->paginate(25)->withQueryString();
        $warningStats = [
            'today' => GeminiApiWarning::query()->whereDate('occurred_at', today())->count(),
            'retryable' => GeminiApiWarning::query()->whereIn('error_code', ['429', '500', '503'])->count(),
            'permanent' => GeminiApiWarning::query()
                ->whereNotIn('error_code', ['429', '500', '503'])
                ->count(),
        ];

        return view('admin.gemini.index', compact('keys', 'warnings', 'warningStats'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:120'],
            'api_key' => ['required', 'string', 'max:4096'],
            'priority' => ['required', 'integer', 'min:-1000', 'max:1000'],
            'active' => ['nullable', 'boolean'],
        ]);

        GeminiApiKey::query()->create([
            'label' => $validated['label'] ?? null,
            'api_key' => $validated['api_key'],
            'priority' => (int) $validated['priority'],
            'active' => $request->boolean('active'),
        ]);

        return back()->with('basari', 'Gemini API anahtari eklendi.');
    }

    public function update(Request $request, GeminiApiKey $key)
    {
        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:120'],
            'api_key' => ['nullable', 'string', 'max:4096'],
            'priority' => ['required', 'integer', 'min:-1000', 'max:1000'],
            'active' => ['nullable', 'boolean'],
            'clear_exhausted' => ['nullable', 'boolean'],
        ]);

        $payload = [
            'label' => $validated['label'] ?? null,
            'priority' => (int) $validated['priority'],
            'active' => $request->boolean('active'),
        ];

        if (filled($validated['api_key'] ?? null)) {
            $payload['api_key'] = $validated['api_key'];
        }
        if ($request->boolean('clear_exhausted')) {
            $payload['exhausted_until'] = null;
        }

        $key->forceFill($payload)->save();

        return back()->with('basari', 'Gemini API anahtari guncellendi.');
    }

    public function destroy(GeminiApiKey $key)
    {
        $key->delete();

        return back()->with('basari', 'Gemini API anahtari silindi.');
    }
}
