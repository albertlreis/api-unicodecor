<?php

namespace App\Http\Requests;

use App\Rules\Cnpj;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para criação de loja.
 * Garante CNPJ sem máscara na validação.
 */
class LojaStoreRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        if ($this->has('cnpj')) {
            $this->merge([
                'cnpj' => preg_replace('/\D+/', '', (string) $this->input('cnpj')),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'razao_social'      => ['required','string','max:100'],
            'nome_fantasia'     => ['required','string','max:100'],
            'cnpj'              => ['required','string','size:14', new Cnpj(), 'unique:lojas,cnpj'],
            'fone'              => ['required','string','max:25'],
            'endereco'          => ['nullable','string','max:100'],
            'site'              => ['nullable','url','max:100'],
            'email'             => ['required','email','max:50'],
            'apresentacao'      => ['nullable','string'],
            'status'            => ['nullable','integer','in:0,1'],
            'logomarca'         => ['nullable','file','mimes:jpg,jpeg,png,webp','max:2048'],
            'remover_logomarca' => ['nullable','boolean'],
        ];
    }
}
