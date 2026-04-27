<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserFotografi;
use App\Services\AyarServisi;
use App\Services\Media\UserProfilePhotoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class AiPhotoController extends Controller
{
    public function __construct(
        private UserProfilePhotoService $photoService,
        private AyarServisi $ayarServisi,
    ) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->string('q'));
        $selectedUserId = (int) $request->integer('user_id');

        $personalar = User::query()
            ->where('hesap_tipi', 'ai')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner
                        ->where('ad', 'like', '%'.$search.'%')
                        ->orWhere('soyad', 'like', '%'.$search.'%')
                        ->orWhere('kullanici_adi', 'like', '%'.$search.'%');
                });
            })
            ->withCount(['fotograflar as fotograf_sayisi' => fn ($query) => $query->where('medya_tipi', 'fotograf')])
            ->with(['fotograflar' => fn ($query) => $query
                ->where('medya_tipi', 'fotograf')
                ->orderByDesc('ana_fotograf_mi')
                ->orderBy('sira_no')
                ->orderBy('id')])
            ->orderBy('ad')
            ->get();

        $aiUsers = User::query()
            ->where('hesap_tipi', 'ai')
            ->orderBy('ad')
            ->get(['id', 'ad', 'soyad', 'kullanici_adi']);

        $selectedUser = $selectedUserId > 0
            ? User::query()
                ->where('hesap_tipi', 'ai')
                ->with(['fotograflar' => fn ($query) => $query->orderBy('sira_no')->orderBy('id')])
                ->find($selectedUserId)
            : null;

        return view('admin.ai-v2.photos', [
            'personalar' => $personalar,
            'aiUsers' => $aiUsers,
            'selectedUser' => $selectedUser,
            'search' => $search,
            'maxPhotos' => $this->photoService->maxPhotos(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(array_merge([
            'hedef_modu' => ['required', Rule::in(['selected', 'filename'])],
            'user_id' => [
                'nullable',
                'required_if:hedef_modu,selected',
                Rule::exists('users', 'id')->where('hesap_tipi', 'ai'),
            ],
        ], $this->photoValidationRules()));

        $mode = $validated['hedef_modu'];
        $selectedUser = null;

        if ($mode === 'selected') {
            $selectedUser = User::query()
                ->where('hesap_tipi', 'ai')
                ->findOrFail((int) $validated['user_id']);
        }

        [$uploadedCount, $errors] = $this->processBulkFiles($request->file('fotograflar', []), $mode, $selectedUser);

        return $this->bulkRedirect($uploadedCount, $errors);
    }

    public function storeForUser(Request $request, User $kullanici): RedirectResponse
    {
        $this->abortUnlessAiUser($kullanici);

        $request->validate(array_merge([
            'ana_fotograf_mi' => ['nullable', 'boolean'],
        ], $this->photoValidationRules()));

        $uploadedCount = 0;
        $errors = [];
        $makeFirstPrimary = $request->boolean('ana_fotograf_mi');

        foreach ($request->file('fotograflar', []) as $index => $file) {
            try {
                $this->photoService->upload($kullanici, $file, $makeFirstPrimary && $index === 0);
                $uploadedCount++;
            } catch (ValidationException $exception) {
                $errors[] = $file->getClientOriginalName().': '.$this->validationMessage($exception);
            } catch (Throwable $exception) {
                report($exception);
                $errors[] = $file->getClientOriginalName().': Fotograf yuklenemedi.';
            }
        }

        return $this->bulkRedirect($uploadedCount, $errors);
    }

    public function update(Request $request, User $kullanici, UserFotografi $fotograf): RedirectResponse
    {
        $this->abortUnlessAiUser($kullanici);

        $validated = $request->validate([
            'ana_fotograf_mi' => ['nullable', 'boolean'],
            'aktif_mi' => ['nullable', 'boolean'],
            'sira_no' => ['nullable', 'integer', 'min:0', 'max:99'],
        ]);

        $data = [
            'ana_fotograf_mi' => $request->boolean('ana_fotograf_mi'),
        ];

        if ($request->has('aktif_mi')) {
            $data['aktif_mi'] = $request->boolean('aktif_mi');
        }

        if (array_key_exists('sira_no', $validated)) {
            $data['sira_no'] = $validated['sira_no'];
        }

        $this->photoService->update($kullanici, $fotograf, $data);

        return back()->with('basari', 'Fotograf guncellendi.');
    }

    public function destroy(User $kullanici, UserFotografi $fotograf): RedirectResponse
    {
        $this->abortUnlessAiUser($kullanici);

        $this->photoService->delete($kullanici, $fotograf);

        return back()->with('basari', 'Fotograf silindi.');
    }

    private function processBulkFiles(array $files, string $mode, ?User $selectedUser): array
    {
        $uploadedCount = 0;
        $errors = [];
        $userCache = [];

        foreach ($files as $file) {
            $targetUser = $selectedUser;

            if ($mode === 'filename') {
                $username = $this->usernameFromFilename($file->getClientOriginalName());

                if ($username === null) {
                    $errors[] = $file->getClientOriginalName().': Dosya adi kullanici_adi__foto.jpg formatinda olmali.';

                    continue;
                }

                $targetUser = $userCache[$username] ??= User::query()
                    ->where('hesap_tipi', 'ai')
                    ->where('kullanici_adi', $username)
                    ->first();

                if (! $targetUser) {
                    $errors[] = $file->getClientOriginalName().": '{$username}' AI kullanicisi bulunamadi.";

                    continue;
                }
            }

            if (! $targetUser) {
                $errors[] = $file->getClientOriginalName().': Hedef AI kullanici bulunamadi.';

                continue;
            }

            try {
                $this->photoService->upload($targetUser, $file);
                $uploadedCount++;
            } catch (ValidationException $exception) {
                $errors[] = $file->getClientOriginalName().': '.$this->validationMessage($exception);
            } catch (Throwable $exception) {
                report($exception);
                $errors[] = $file->getClientOriginalName().': Fotograf yuklenemedi.';
            }
        }

        return [$uploadedCount, $errors];
    }

    private function bulkRedirect(int $uploadedCount, array $errors): RedirectResponse
    {
        $response = back();

        if ($uploadedCount > 0) {
            $response = $response->with('basari', $uploadedCount.' fotograf yuklendi.');
        } else {
            $response = $response->with('hata', 'Fotograf yuklenemedi.');
        }

        if ($errors !== []) {
            $response = $response->with('hatalar', $errors);
        }

        return $response;
    }

    private function usernameFromFilename(string $filename): ?string
    {
        $name = pathinfo($filename, PATHINFO_FILENAME);

        if (! str_contains($name, '__')) {
            return null;
        }

        $username = trim(Str::before($name, '__'));

        return $username !== '' ? $username : null;
    }

    private function photoValidationRules(): array
    {
        $maxPhotoSizeMb = max(1, (int) $this->ayarServisi->al('max_foto_boyut_mb', config('storage.upload.max_image_size_mb', 50)));
        $maxPhotoSizeKb = $maxPhotoSizeMb * 1024;

        return [
            'fotograflar' => ['required', 'array', 'min:1'],
            'fotograflar.*' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:'.$maxPhotoSizeKb],
        ];
    }

    private function validationMessage(ValidationException $exception): string
    {
        return collect($exception->errors())
            ->flatten()
            ->filter()
            ->implode(' ');
    }

    private function abortUnlessAiUser(User $user): void
    {
        abort_unless($user->hesap_tipi === 'ai', 404);
    }
}
