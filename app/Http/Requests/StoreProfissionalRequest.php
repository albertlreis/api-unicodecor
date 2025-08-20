<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validação para criação de Profissional.
 * Regras:
 * - CPF obrigatório, somente dígitos, 11 caracteres, DV válido.
 * - Nome com trim + UPPERCASE (normalizado antes de validar).
 * - Login somente letras minúsculas (a-z), sem espaços, único.
 * - id_perfil forçado para 2 (Profissional).
 */
class StoreProfissionalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normaliza os campos antes da validação:
     * - cpf: somente dígitos
     * - nome: trim + UPPERCASE
     * - login: apenas letras minúsculas a-z
     * - id_perfil: força 2
     */
    protected function prepareForValidation(): void
    {
        $cpf   = preg_replace('/\D+/', '', (string) $this->input('cpf', ''));
        $nome  = mb_strtoupper(trim((string) $this->input('nome', '')));
        $login = $this->input('login');

        $this->merge([
            'cpf'       => $cpf,
            'nome'      => $nome,
            'login'     => $login,
            'id_perfil' => 2, // força perfil Profissional
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'id_perfil'    => ['required', 'integer', 'in:2'],
            'id_loja'      => ['nullable', 'integer'],
            'nome'         => ['required', 'string', 'min:2', 'max:100'],
            'cpf'          => ['required', 'string', 'regex:/^\d{11}$/'], // DV checado em withValidator
            'profissao'    => ['nullable', 'string', 'max:200'],
            'area_atuacao' => ['nullable', 'string', 'max:50'],
            'endereco'     => ['nullable', 'string', 'max:500'],
            'complemento'  => ['nullable', 'string', 'max:200'],
            'bairro'       => ['nullable', 'string', 'max:100'],
            'cep'          => ['nullable', 'string', 'max:20'],
            'id_estado'    => ['nullable', 'integer'],
            'id_cidade'    => ['nullable', 'integer'],
            'site'         => ['nullable', 'string', 'max:100'],
            'email'        => ['nullable', 'email', 'max:100'],
            'fone'         => ['nullable', 'string', 'max:20'],
            'fax'          => ['nullable', 'string', 'max:20'],
            'cel'          => ['nullable', 'string', 'max:20'],
            'dt_nasc'      => ['nullable', 'string', 'max:10'],
            'reg_crea'     => ['nullable', 'string', 'max:30'],
            'reg_abd'      => ['nullable', 'string', 'max:30'],

            // Login: único + apenas letras minúsculas
            'login'        => [
                'required',
                'string',
                'min:3',
                'max:30',
                Rule::unique('usuario', 'login'),
            ],

            // Senha obrigatória na criação
            'senha'        => ['required', 'string', 'min:6', 'max:255'],

            'acesso'       => ['nullable', 'integer'],
            'status'       => ['nullable', 'integer', 'in:0,1,2'],
        ];
    }

    /**
     * Validação adicional do CPF (dígitos verificadores).
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $cpf = (string) $this->input('cpf', '');
            if (!$this->cpfValido($cpf)) {
                $v->errors()->add('cpf', 'CPF inválido.');
            }
        });
    }

    /**
     * Verifica os dígitos verificadores do CPF.
     */
    private function cpfValido(string $raw): bool
    {
        $cpf = preg_replace('/\D+/', '', $raw);
        if (strlen($cpf) !== 11) return false;
        if (preg_match('/^(\d)\1{10}$/', $cpf)) return false;

        $calc = static function (string $base, int $factorStart): int {
            $sum = 0;
            for ($i = 0; $i < strlen($base); $i++) {
                $sum += intval($base[$i]) * ($factorStart - $i);
            }
            $rest = $sum % 11;
            return $rest < 2 ? 0 : 11 - $rest;
        };

        $dv1 = $calc(substr($cpf, 0, 9), 10);
        $dv2 = $calc(substr($cpf, 0, 10), 11);

        return $dv1 === intval($cpf[9]) && $dv2 === intval($cpf[10]);
    }

    /**
     * Mensagens customizadas.
     *
     * @return array<string,string>
     */
    public function messages(): array
    {
        return [
            'id_perfil.in'     => 'Perfil inválido.',
            'cpf.required'     => 'CPF é obrigatório.',
            'cpf.regex'        => 'CPF deve conter exatamente 11 dígitos.',
            'login.unique'     => 'Já existe um usuário com esse login.',
            'senha.required'   => 'Senha é obrigatória.',
        ];
    }
}
