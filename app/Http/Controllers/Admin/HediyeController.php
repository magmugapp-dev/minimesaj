<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Hediye;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HediyeController extends Controller
{
    public function index(): View
    {
        $hediyeler = Hediye::query()
            ->orderBy('sira')
            ->orderBy('id')
            ->get();

        return view('admin.hediyeler.index', compact('hediyeler'));
    }

    public function create(): View
    {
        return view('admin.hediyeler.create', [
            'hediye' => new Hediye([
                'ikon' => '🎁',
                'puan_bedeli' => 10,
                'aktif' => true,
                'sira' => 10,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Hediye::query()->create($this->dogrula($request));

        return redirect()
            ->route('admin.hediyeler.index')
            ->with('basari', 'Hediye olusturuldu.');
    }

    public function edit(Hediye $hediye): View
    {
        return view('admin.hediyeler.edit', compact('hediye'));
    }

    public function update(Request $request, Hediye $hediye): RedirectResponse
    {
        $hediye->update($this->dogrula($request, $hediye));

        return redirect()
            ->route('admin.hediyeler.index')
            ->with('basari', 'Hediye guncellendi.');
    }

    public function destroy(Hediye $hediye): RedirectResponse
    {
        $hediye->delete();

        return redirect()
            ->route('admin.hediyeler.index')
            ->with('basari', 'Hediye silindi.');
    }

    private function dogrula(Request $request, ?Hediye $hediye = null): array
    {
        $id = $hediye?->id;

        return $request->validate([
            'kod' => ['required', 'string', 'max:100', Rule::unique('hediyeler', 'kod')->ignore($id)],
            'ad' => ['required', 'string', 'max:100'],
            'ikon' => ['nullable', 'string', 'max:20'],
            'puan_bedeli' => ['required', 'integer', 'min:1', 'max:100000'],
            'aktif' => ['nullable', 'boolean'],
            'sira' => ['required', 'integer', 'min:0'],
        ]) + [
            'aktif' => $request->boolean('aktif', true),
        ];
    }
}

