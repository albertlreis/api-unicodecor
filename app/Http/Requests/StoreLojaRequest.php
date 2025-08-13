<?php

namespace App\Http\Requests;

use App\Rules\Cnpj;
use Illuminate\Foundation\Http\FormRequest;

class StoreLojaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'razao_social'   => ['required', 'string', 'max:255'],
            'nome_fantasia'  => ['required', 'string', 'max:255'],
            'cnpj'           => ['required', 'string', new Cnpj(), 'unique:lojas,cnpj'],
            'fone'           => ['nullable', 'string', 'max:30'],
            'endereco'       => ['nullable', 'string', 'max:255'],
            'site'           => ['nullable', 'url', 'max:255'],
            'email'          => ['nullable', 'email', 'max:255'],
            'apresentacao'   => ['nullable', 'string'],
            'status'         => ['nullable', 'integer', 'in:0,1'],
            'logomarca'      => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'], // 2MB
        ];
    }
}
