<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @phpstan-type BannerStorePayload array{
 *   titulo: string,
 *   link?: string|null,
 *   descricao?: string|null,
 *   status?: bool|int,
 *   arquivo: \Illuminate\Http\UploadedFile
 * }
 *
 * @property-read BannerStorePayload $validated
 */
class BannerStoreRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /**
     * Regras para criar banner:
     * - Título obrigatório;
     * - Upload de imagem obrigatório, exatamente 1280x510;
     * - Link opcional (URL);
     */
    public function rules(): array
    {
        return [
            'titulo'    => ['required', 'string', 'max:255'],
            'arquivo'   => [
                'required', 'file', 'image', 'mimes:jpg,jpeg,png,webp',
                'dimensions:width=1280,height=510',
                'max:5120'
            ],
            'link'      => ['sometimes', 'nullable', 'string', 'max:1024'],
            'descricao' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'status'    => ['sometimes', 'boolean'],
        ];
    }

    public function validated($key = null, $default = null): array
    {
        $data = parent::validated($key, $default);
        if (array_key_exists('status', $data)) {
            $data['status'] = (int) ((bool) $data['status']);
        }
        return $data;
    }
}
