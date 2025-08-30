<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @property-read string $token
 * @property-read string $senha
 * @property-read string $confirmacao_senha
 */
class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:128'],
            'senha' => ['required', 'string', 'min:6', 'max:100'],
            'confirmacao_senha' => ['required', 'same:senha'],
        ];
    }

    public function messages(): array
    {
        return [
            'confirmacao_senha.same' => 'As senhas não conferem.',
        ];
    }
}
