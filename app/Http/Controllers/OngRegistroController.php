<?php

namespace App\Http\Controllers;

use App\Models\OngRegistro;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class OngRegistroController extends Controller
{
    public function index(Request $request) {
        $ongs = OngRegistro::orderByDesc('created_at')
            ->paginate(15);

        return view('ongs.index', compact('ongs'));
    }
    
    public function store(Request $request)
    {
        // Normalize checkbox values ("on"/missing) into booleans before validation.
        $this->coerceNotAvailableFlags($request);

        $data = $request->validate($this->validationRules(), $this->validationMessages());
        $data = $this->normalizeFields($data);
        $photos = $this->storePhotoFiles($request);

        $photoUrls = array_values(array_filter(array_merge($data['photo_urls'] ?? [], $photos), fn($value) => is_string($value) && trim($value) !== ''));
        $data['photo_urls'] = array_values(array_unique($photoUrls));
        unset($data['photo_files']);

        $data['monthly_costs'] = $this->populateMonthlyCosts($data);

        $registro = OngRegistro::create([
            ...$data,
            'ip' => $request->ip(),
            'user_agent' => substr((string)$request->userAgent(), 0, 2000),
        ]);

        return response()->json([
            'ok' => true,
            'id' => $registro->id,
            'message' => 'Cadastro recebido com sucesso!',
        ], 201);
    }

    private function publicDiskPathFromUrl(string $url): ?string
    {
        $url = trim($url);
        $app = rtrim(config('app.url'), '/');

        $prefixes = [
            $app . '/storage/',
            '/storage/',
        ];

        foreach ($prefixes as $p) {
            if (str_starts_with($url, $p)) {
                $relative = substr($url, strlen($p)); // ex: "ong-registros/photos/abc.jpg"
                return $relative !== '' ? $relative : null;
            }
        }

        return null; // link externo: não apaga
    }


    public function update(Request $request, OngRegistro $ong)
    {
        $this->coerceNotAvailableFlags($request);
    
        $data = $request->validate($this->validationRules(), $this->validationMessages());
        $data = $this->normalizeFields($data);
    
        $oldUrls = is_array($ong->photo_urls) ? $ong->photo_urls : [];
    
        // novas fotos enviadas
        $newUploadedUrls = $this->storePhotoFiles($request);
    
        // ✅ REGRA DE SUBSTITUIÇÃO:
        // Se veio arquivo novo, SUBSTITUI tudo no BD pelas novas URLs.
        // Se não veio arquivo, mantém o que veio no form (serve pra remover/editar lista).
        if ($request->hasFile('photo_files')) {
            $finalUrls = $newUploadedUrls; // <<< substitui, não faz merge
        } else {
            $keptUrls = $request->input('photo_urls', null);
            $keptUrls = is_array($keptUrls) ? $keptUrls : $oldUrls;
    
            $finalUrls = array_values(array_filter($keptUrls, fn($v) => is_string($v) && trim($v) !== ''));
            $finalUrls = array_values(array_unique($finalUrls));
        }
    
        // (Opcional) apagar fotos antigas do storage quando substituir
        if ($request->hasFile('photo_files')) {
            $removed = array_values(array_diff(
                array_values(array_filter($oldUrls, fn($v) => is_string($v) && trim($v) !== '')),
                $finalUrls
            ));
    
            foreach ($removed as $url) {
                $path = $this->publicDiskPathFromUrl($url);
                if ($path) {
                    Storage::disk('public')->delete($path);
                }
            }
        }
    
        $data['photo_urls'] = $finalUrls ?: null;
        unset($data['photo_files']);
    
        $data['monthly_costs'] = $this->populateMonthlyCosts($data);
    
        $ong->update($data);
    
        return response()->json([
            'ok' => true,
            'message' => 'ONG atualizada com sucesso!',
        ]);
    }

    public function destroy(OngRegistro $ong)
    {
        $ong->delete();

        return response()->json([
            'ok' => true,
            'message' => 'ONG removida com sucesso!',
        ]);
    }

    private function validationRules(): array
    {
        return [
            'name'  => ['required','string','max:255'],
            'email' => ['required','email','max:255'],

            'cnpj'  => ['nullable','string','max:32'],
            'cnpj_not_available' => ['sometimes','boolean'],
            'phone' => ['nullable','string','max:32'],
            'foundation_date' => ['nullable','date'],
            'animal_count' => ['nullable','integer','min:0'],
            'caregiver_count' => ['nullable','integer','min:0'],

            'description' => ['nullable','string'],

            'street' => ['nullable','string','max:255'],
            'number' => ['nullable','string','max:32'],
            'complement' => ['nullable','string','max:255'],
            'district' => ['nullable','string','max:255'],
            'city' => ['nullable','string','max:255'],
            'state' => ['nullable','string','size:2'],
            'zip' => ['nullable','string','max:16'],

            'facebook' => ['nullable','string','max:255'],
            'facebook_not_available' => ['sometimes','boolean'],
            'instagram' => ['nullable','string','max:255'],
            'instagram_not_available' => ['sometimes','boolean'],
            'website' => ['nullable','string','max:255'],
            'website_not_available' => ['sometimes','boolean'],

            'photo_urls' => ['nullable','array'],
            'photo_urls.*' => ['nullable','url','max:2048'],
            'photo_files' => ['nullable','array'],
            'photo_files.*' => ['file','image','max:5120'],

            'portion_value' => ['nullable','numeric','min:0'],
            'medicines_value' => ['nullable','numeric','min:0'],
            'veterinarian_value' => ['nullable','numeric','min:0'],
            'collaborators_value' => ['nullable','numeric','min:0'],
            'other_costs_value' => ['nullable','numeric','min:0'],
            'other_costs_description' => ['nullable','string'],

            'source_tag'  => ['required','string','max:255'],
        ];
    }

    private function coerceNotAvailableFlags(Request $request): void
    {
        $flagFields = [
            'cnpj_not_available',
            'facebook_not_available',
            'instagram_not_available',
            'website_not_available',
        ];

        foreach ($flagFields as $field) {
            if ($request->has($field)) {
                $request->merge([
                    $field => $request->boolean($field),
                ]);
            }
        }
    }

    private function validationMessages(): array
    {
        return [
            'name.required' => 'Informe o nome da ONG.',
            'email.required' => 'Informe o email de contato.',
            'email.email' => 'Informe um email válido.',
            'cnpj.string' => 'O CNPJ deve ser fornecido apenas com caracteres.',
            'foundation_date.date' => 'A data de fundação precisa estar em um formato válido.',
            'animal_count.integer' => 'Informe quantos animais de forma numérica.',
            'caregiver_count.integer' => 'Informe quantos cuidadores de forma numérica.',
            'state.size' => 'Informe a sigla UF com 2 caracteres.',
            'photo_urls.array' => 'As fotos devem ser enviadas como uma lista.',
            'photo_urls.*.url' => 'Cada link de mídia precisa ser uma URL válida.',
            'photo_files.*.file' => 'Envie apenas arquivos válidos para as fotos.',
            'photo_files.*.image' => 'As fotos devem estar em formato de imagem.',
            'photo_files.*.max' => 'Cada foto pode ter até 5 MB.',
            'portion_value.numeric' => 'Informe um número para o custo de ração.',
            'medicines_value.numeric' => 'Informe um número para o custo de medicamentos.',
            'veterinarian_value.numeric' => 'Informe um número para o custo de veterinário.',
            'collaborators_value.numeric' => 'Informe um número para o custo de colaboradores.',
            'other_costs_value.numeric' => 'Informe um número para outros custos.',
            'source_tag.required' => 'Informações de origem são obrigatórias.',
        ];
    }

    private function normalizeFields(array $data): array
    {
        if (!empty($data['state'])) {
            $data['state'] = strtoupper(trim($data['state']));
        }

        if (!empty($data['cnpj'])) {
            $data['cnpj'] = preg_replace('/\D+/', '', $data['cnpj']);
        }

        if (!empty($data['cnpj_not_available'])) {
            $data['cnpj'] = null;
        }
        if (!empty($data['facebook_not_available'])) {
            $data['facebook'] = null;
        }
        if (!empty($data['instagram_not_available'])) {
            $data['instagram'] = null;
        }
        if (!empty($data['website_not_available'])) {
            $data['website'] = null;
        }

        return $data;
    }

    private function storePhotoFiles(Request $request): array
    {
        $uploadedPhotoUrls = [];
        if ($request->hasFile('photo_files')) {
            $files = $request->file('photo_files');
            $files = is_array($files) ? $files : [$files];

            foreach ($files as $file) {
                if ($file && $file->isValid()) {
                    try {
                        Storage::disk('public')->makeDirectory('ong-registros/photos');
                        $path = Storage::disk('public')->putFile('ong-registros/photos', $file, [
                            'visibility' => 'public',
                        ]);
                        if (!is_string($path) || $path === '') {
                            throw new \RuntimeException('Upload did not return a file path.');
                        }
                        if (!Storage::disk('public')->exists($path)) {
                            throw new \RuntimeException("Upload saved path missing: {$path}");
                        }
                        $uploadedPhotoUrls[] = Storage::disk('public')->url($path);
                    } catch (\Throwable $e) {
                        report($e);
                        throw ValidationException::withMessages([
                            'photo_files' => 'Não foi possível salvar as fotos enviadas.',
                        ]);
                    }
                }
            }
        }

        return $uploadedPhotoUrls;
    }

    private function populateMonthlyCosts(array $data): ?array
    {
        $costFields = [
            'portion_value' => 'Ração',
            'medicines_value' => 'Medicamentos',
            'veterinarian_value' => 'Veterinário',
            'collaborators_value' => 'Colaboradores',
            'other_costs_value' => 'Outros custos',
        ];
        $costEntries = [];
        foreach ($costFields as $field => $label) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $raw = $data[$field];
            if ($raw === null || $raw === '') {
                continue;
            }
            $costEntries[] = [
                'label' => $label,
                'amount' => round((float)$raw, 2),
            ];
        }

        if (!empty($data['other_costs_description'])) {
            $details = trim((string)$data['other_costs_description']);
            if ($details !== '') {
                foreach ($costEntries as $index => $entry) {
                    if (($entry['label'] ?? null) === 'Outros custos') {
                        $costEntries[$index]['details'] = $details;
                        break;
                    }
                }
            }
        }

        return $costEntries ?: null;
    }
}
