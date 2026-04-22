<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PuanPaketi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PuanPaketiController extends Controller
{
    public function index(): View
    {
        $paketler = PuanPaketi::query()
            ->orderBy('sira')
            ->get();

        return view('admin.finansal.puan-paketleri.index', compact('paketler'));
    }

    public function create(): View
    {
        return view('admin.finansal.puan-paketleri.create', [
            'paket' => new PuanPaketi([
                'para_birimi' => 'TRY',
                'aktif' => true,
                'onerilen_mi' => false,
                'sira' => 10,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $veri = $this->dogrula($request);
        $paket = PuanPaketi::query()->create($veri);

        $this->onerilenPaketiEsitle($paket);

        return redirect()
            ->route('admin.finansal.puan-paketleri.index')
            ->with('basari', 'Puan paketi oluşturuldu.');
    }

    public function edit(PuanPaketi $puanPaketi): View
    {
        return view('admin.finansal.puan-paketleri.edit', [
            'paket' => $puanPaketi,
        ]);
    }

    public function update(Request $request, PuanPaketi $puanPaketi): RedirectResponse
    {
        $puanPaketi->update($this->dogrula($request, $puanPaketi));
        $this->onerilenPaketiEsitle($puanPaketi);

        return redirect()
            ->route('admin.finansal.puan-paketleri.index')
            ->with('basari', 'Puan paketi güncellendi.');
    }

    public function destroy(PuanPaketi $puanPaketi): RedirectResponse
    {
        $puanPaketi->delete();

        return redirect()
            ->route('admin.finansal.puan-paketleri.index')
            ->with('basari', 'Puan paketi silindi.');
    }

    private function dogrula(Request $request, ?PuanPaketi $puanPaketi = null): array
    {
        $id = $puanPaketi?->id;

        return $request->validate([
            'kod' => ['required', 'string', 'max:100', Rule::unique('puan_paketleri', 'kod')->ignore($id)],
            'android_urun_kodu' => ['nullable', 'string', 'max:150', Rule::unique('puan_paketleri', 'android_urun_kodu')->ignore($id)],
            'ios_urun_kodu' => ['nullable', 'string', 'max:150', Rule::unique('puan_paketleri', 'ios_urun_kodu')->ignore($id)],
            'puan' => ['required', 'integer', 'min:1'],
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

    private function onerilenPaketiEsitle(PuanPaketi $paket): void
    {
        if (!$paket->onerilen_mi) {
            return;
        }

        PuanPaketi::query()
            ->where('id', '!=', $paket->id)
            ->update(['onerilen_mi' => false]);
    }
}
