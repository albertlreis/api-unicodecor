<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @phpstan-type PontuacaoPayload array{
 *   valor: float|int|string,
 *   dt_referencia: string,
 *   id_profissional: int,
 *   id_cliente: int,
 *   id_loja?: int|null,
 *   orcamento?: string|null
 * }
 */
class PontuacaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $perfil = $this->user()->id_perfil ?? null;
        // Apenas Admin (1) e Lojista (3) podem criar/editar
        return in_array($perfil, [1, 3], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $perfil = $this->user()->id_perfil ?? null;

        $rules = [
            'valor'           => ['required', 'numeric', 'min:0.01'],
            'dt_referencia'   => ['required', 'date'],
            'id_profissional' => ['required', 'exists:usuario,id'],
            'id_cliente'      => ['required', 'exists:usuario,id'], // 🔒 sempre obrigatório
            'orcamento'       => ['nullable', 'string', 'max:255'],
        ];

        if ((int)$perfil === 1) {
            $rules['id_loja'] = ['required', 'exists:lojas,id'];
        } elseif ((int)$perfil === 3) {
            // Lojista: id_loja vem do usuário, ignoramos o payload
            $rules['id_loja'] = ['nullable', 'exists:lojas,id'];
        }

        return $rules;
    }
}
