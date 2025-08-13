<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @phpstan-type BannerPayload array{
 *   titulo: string,
 *   imagem?: string|null,
 *   link?: string|null,
 *   descricao?: string|null,
 *   status?: bool|int
 * }
 *
 * @property-read BannerPayload $validated
 */
class BannerRequest extends FormRequest
{
    /**
     * Autoriza a requisição.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Regras de validação.
     * Para PATCH/PUT o mesmo conjunto funciona (campos opcionais no PATCH).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isPatch = $this->method() == 'PATCH';

        return [
            'titulo'    => [$isPatch ? 'sometimes' : 'required', 'string', 'max:255'],
            'imagem'    => ['sometimes', 'nullable', 'string', 'max:1024'],
            'link'      => ['sometimes', 'nullable', 'url', 'max:1024'],
            'descricao' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'status'    => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Normaliza payload (ex.: converte status para int 0/1 se necessário).
     *
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): array
    {
        $data = parent::validated($key, $default);

        if (array_key_exists('status', $data)) {
            $data['status'] = (int) ((bool) $data['status']);
        }

        return $data;
    }
}
