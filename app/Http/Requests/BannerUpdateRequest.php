<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @phpstan-type BannerUpdatePayload array{
 *   titulo?: string,
 *   link?: string|null,
 *   descricao?: string|null,
 *   status?: bool|int
 * }
 *
 * @property-read BannerUpdatePayload $validated
 */
class BannerUpdateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /**
     * Regras para editar banner:
     */
    public function rules(): array
    {
        return [
            'titulo'    => ['sometimes', 'required', 'string', 'max:255'],
            'link'      => ['sometimes', 'nullable', 'string', 'max:1024'],
            'descricao' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'status'    => ['sometimes', 'boolean'],
            'arquivo'   => ['sometimes', 'nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120'],
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

    public function messages(): array
    {
        return [
            'arquivo.mimes' => 'A imagem deve ser JPG, JPEG, PNG, GIF ou WEBP.',
            'arquivo.max'   => 'A imagem deve ter no máximo 5MB.',
        ];
    }
}
