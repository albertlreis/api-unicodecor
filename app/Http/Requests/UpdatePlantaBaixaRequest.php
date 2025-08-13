<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @phpstan-type UpdatePlantaPayload array{
 *   idEmpreendimentos?:int,
 *   titulo?:string,
 *   descricao?:string|null,
 *   nome?:string|null,
 *   arquivo?:\Illuminate\Http\UploadedFile|null,
 *   status?:int
 * }
 */
class UpdatePlantaBaixaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'idEmpreendimentos' => ['sometimes','integer','exists:empreendimentos,idEmpreendimentos'],
            'titulo'            => ['sometimes','string','max:190'],
            'descricao'         => ['sometimes','nullable','string','max:2000'],
            'nome'              => ['sometimes','nullable','string','max:190'],
            'arquivo'           => ['sometimes','file','mimes:pdf','max:20480'],
            'status'            => ['sometimes','integer','in:0,1'],
        ];
    }
}
