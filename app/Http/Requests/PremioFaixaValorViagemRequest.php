<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @purpose Validação para atualização pontual do campo 'vl_viagem' em uma faixa de prêmio.
 */
class PremioFaixaValorViagemRequest extends FormRequest
{
    /**
     * @return bool
     */
    public function authorize(): bool
    {
        // Autorização simples por id_perfil (perfil 1 = admin).
        /** @var \App\Models\User|null $user */
        $user = $this->user();
        return $user && (int)($user->id_perfil ?? 0) === 1;
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        return [
            // Aceita null para "zerar"; numérico não-negativo
            'vl_viagem' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'vl_viagem.numeric' => 'O valor da viagem deve ser numérico.',
            'vl_viagem.min'     => 'O valor da viagem não pode ser negativo.',
        ];
    }
}
