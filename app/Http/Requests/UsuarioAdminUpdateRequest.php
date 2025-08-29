<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @phpdoc
 * Validação para atualização de usuário administrativo.
 * - nome, email, cpf, id_perfil obrigatórios
 * - senha opcional (se enviada)
 * - id_loja obrigatório quando id_perfil = 3 (Lojista)
 * - email e cpf únicos desconsiderando o próprio registro
 */
class UsuarioAdminUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');
        $cpf   = $this->input('cpf');

        $this->merge([
            'email' => is_string($email) ? mb_strtolower(trim($email)) : $email,
            'cpf'   => is_string($cpf) ? preg_replace('/\D+/', '', $cpf) : $cpf,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var \App\Models\Usuario|int|string|null $usuario */
        $usuario    = $this->route('usuario'); // model bound ou id
        $usuarioId  = is_object($usuario) ? $usuario->id : (int) $usuario;
        $perfil     = (int) $this->input('id_perfil');

        return [
            'nome'       => ['required', 'string', 'max:100'],
            'email'      => ['required', 'string', 'email', 'max:100',
                Rule::unique('usuario','email')->ignore($usuarioId, 'id')
            ],
            'cpf'        => ['required', 'string', 'max:20',
                Rule::unique('usuario','cpf')->ignore($usuarioId, 'id')
            ],
            'id_perfil'  => ['required', 'integer', Rule::in([1,3,5])],
            'senha'      => ['nullable', 'string', 'min:6', 'max:255'],
            'id_loja'    => [Rule::requiredIf($perfil === 3), 'nullable', 'integer', 'exists:lojas,id'],
        ];
    }

    /** @return array<string,string> */
    public function attributes(): array
    {
        return [
            'id_perfil' => 'perfil',
            'id_loja'   => 'loja',
        ];
    }
}
