<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AbonelikPaketi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AbonelikPaketiController extends Controller
{
    public function index(): View
    {
        $paketler = AbonelikPaketi::query()
            ->orderBy('sira')
            ->get();

        return view('admin.finansal.abonelik-paketleri.index', compact('paketler'));
    }

    public function create(): View
    {
        return view('admin.finansal.abonelik-paketleri.create', [
            'paket' => new AbonelikPaketi([
                'para_birimi' => 'USD',
                'aktif' => true,
                'onerilen_mi' => false,
                'sira' => 10,
                'sure_ay' => 1,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $paket = AbonelikPaketi::query()->create($this->dogrula($request));
        $this->onerilenPaketiEsitle($paket);

        return redirect()
            ->route('admin.finansal.abonelik-paketleri.index')
            ->with('basari', 'Abonelik paketi olusturuldu.');
    }

    public function edit(AbonelikPaketi $abonelikPaketi): View
    {
        return view('admin.finansal.abonelik-paketleri.edit', [
            'paket' => $abonelikPaketi,
        ]);
    }

    public function update(Request $request, AbonelikPaketi $abonelikPaketi): RedirectResponse
    {
        $abonelikPaketi->update($this->dogrula($request, $abonelikPaketi));
        $this->onerilenPaketiEsitle($abonelikPaketi);

        return redirect()
            ->route('admin.finansal.abonelik-paketleri.index')
            ->with('basari', 'Abonelik paketi guncellendi.');
    }

    public function destroy(AbonelikPaketi $abonelikPaketi): RedirectResponse
    {
        $abonelikPaketi->delete();

        return redirect()
            ->route('admin.finansal.abonelik-paketleri.index')
            ->with('basari', 'Abonelik paketi silindi.');
    }

    private function dogrula(Request $request, ?AbonelikPaketi $paket = null): array
    {
        $id = $paket?->id;

        return $request->validate([
            'kod' => ['required', 'string', 'max:100', Rule::unique('abonelik_paketleri', 'kod')->ignore($id)],
            'android_urun_kodu' => ['nullable', 'string', 'max:150', Rule::unique('abonelik_paketleri', 'android_urun_kodu')->ignore($id)],
            'ios_urun_kodu' => ['nullable', 'string', 'max:150', Rule::unique('abonelik_paketleri', 'ios_urun_kodu')->ignore($id)],
            'sure_ay' => ['required', 'integer', 'min:1', 'max:36'],
            'fiyat' => ['required', 'numeric', 'min:0'],
            'para_birimi' => ['required', 'string', 'size:3'],
            'rozet' => ['nullable', 'string', 'max:50'],
            'onerilen_mi' => ['nullable', 'boolean'],
            'aktif' => ['nullable', 'boolean'],
            'sira' => ['required', 'integer', 'min:0'],
        ]) + [
            'onerilen_mi' => $request->boolean('onerilen_mi'),
            'aktif' => $request->boolean('aktif', true),
        ];
    }

    private function onerilenPaketiEsitle(AbonelikPaketi $paket): void
    {
        if (!$paket->onerilen_mi) {
            return;
        }

        AbonelikPaketi::query()
            ->where('id', '!=', $paket->id)
            ->update(['onerilen_mi' => false]);
    }
}
