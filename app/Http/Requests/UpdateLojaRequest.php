<?php

namespace App\Http\Requests;

use App\Rules\Cnpj;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLojaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('loja');

        return [
            'razao_social'   => ['sometimes', 'required', 'string', 'max:255'],
            'nome_fantasia'  => ['sometimes', 'required', 'string', 'max:255'],
            'cnpj'           => [
                'sometimes', 'required', 'string', new Cnpj(),
                Rule::unique('lojas', 'cnpj')->ignore($id)
            ],
            'fone'           => ['nullable', 'string', 'max:30'],
            'endereco'       => ['nullable', 'string', 'max:255'],
            'site'           => ['nullable', 'url', 'max:255'],
            'email'          => ['nullable', 'email', 'max:255'],
            'apresentacao'   => ['nullable', 'string'],
            'status'         => ['nullable', 'integer', 'in:0,1'],
            'logomarca'      => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remover_logomarca' => ['nullable', 'boolean'],
        ];
    }
}
