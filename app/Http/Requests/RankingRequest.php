<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RankingRequest extends FormRequest
{
    /**
     * Determina se o usuário está autorizado.
     */
    public function authorize(): bool
    {
        $perfil = (int) $this->user()->id_perfil;
        // Admin (1), Lojista (3) e Secretaria (5) podem ver a tela
        return in_array($perfil, [1, 3, 5], true);
    }

    /**
     * Regras de validação.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'id_premio' => ['required'],
            'id_loja'   => ['nullable', 'integer', 'exists:lojas,id'],
        ];
    }

    /**
     * Ajusta a entrada ANTES da validação.
     * - Se for lojista, força o id_loja do próprio usuário.
     */
    protected function prepareForValidation(): void
    {
        $user = $this->user();

        // Força loja do lojista
        if ((int) $user->id_perfil === 3) {
            $lojaId = $user->id_loja ?? $user->loja_id ?? null;
            if ($lojaId) $this->merge(['id_loja' => (int) $lojaId]);
        }

        // Normaliza id_premio:
        // - Se "top100:YYYY" → ok
        // - Se numérico → ok
        // - Caso contrário → nulo p/ disparar erro "required"
        $id = $this->input('id_premio');
        if (is_string($id) && preg_match('/^top100:(\d{4})$/', $id)) {
            return;
        }
        if (!ctype_digit((string) $id)) {
            $this->merge(['id_premio' => null]);
        }
    }

    public function messages(): array
    {
        return [
            'id_premio.required' => 'O parâmetro id_premio é obrigatório.',
            'id_premio.exists' => 'O prêmio informado não existe.',
        ];
    }
}
