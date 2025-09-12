<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PremioUpdateRequest extends FormRequest
{
    public function authorize(): bool { return $this->user() !== null; }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'titulo'      => ['required', 'string', 'max:255'],
            'regras'      => ['nullable', 'string'],
            'regulamento' => ['nullable', 'string'],
            'dt_inicio'   => ['required', 'date_format:Y-m-d'],
            'dt_fim'      => ['required', 'date_format:Y-m-d', 'after_or_equal:dt_inicio'],

            'faixas'                 => ['array'],
            'faixas.*.id'            => ['sometimes', 'integer', 'min:1'],
            'faixas.*.pontos_min'    => ['required', 'numeric', 'min:0'],
            'faixas.*.pontos_max'    => ['nullable', 'numeric', 'min:0'],
            'faixas.*.vl_viagem'     => ['required', 'numeric', 'min:0'],
            'faixas.*.acompanhante'  => ['required', 'in:0,1'],
            'faixas.*.descricao'     => ['nullable', 'string'],

            'arquivo'   => [
                'nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp',
                'dimensions:width=1650,height=1080',
                'max:5120'
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'dt_fim.after_or_equal' => 'A data de fim deve ser posterior ou igual à data de início.',
            'arquivo.dimensions'    => 'O banner deve ter exatamente 1650x1080 pixels.',
        ];
    }
}
