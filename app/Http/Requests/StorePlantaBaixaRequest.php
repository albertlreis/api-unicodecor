<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @phpstan-type StorePlantaPayload array{
 *   idEmpreendimentos:int,
 *   titulo:string,
 *   descricao:string|null,
 *   nome:string|null,
 *   arquivo:\Illuminate\Http\UploadedFile
 * }
 */
class StorePlantaBaixaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'idEmpreendimentos' => ['required','integer','exists:empreendimentos,idEmpreendimentos'],
            'titulo'            => ['required','string','max:190'],
            'descricao'         => ['nullable','string','max:2000'],
            'nome'              => ['nullable','string','max:190'],
            'arquivo'           => ['required','file','mimes:pdf','max:20480'],
        ];
    }
}
