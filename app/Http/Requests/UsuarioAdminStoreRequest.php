<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Rules\CpfValido;

/**
 * @phpdoc Validação criação de usuário administrativo.
 * Obrigatórios: nome, email, cpf, id_perfil, senha.
 * Loja obrigatória quando perfil = 3 (Lojista).
 * Email/CPF únicos na tabela 'usuario'.
 */
class UsuarioAdminStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Normaliza email/cpf antes da validação. */
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
        $perfil = (int) $this->input('id_perfil');

        return [
            'nome'       => ['required', 'string', 'min:3', 'max:100'],
            'email'      => ['required', 'string', 'email', 'max:100', 'unique:usuario,email'],
            'cpf'        => ['required', 'string', 'size:11', new CpfValido, 'unique:usuario,cpf'],
            'id_perfil'  => ['required', 'integer', Rule::in([1,3,5])],
            'senha'      => ['required', 'string', 'min:6', 'max:255'],
            'id_loja'    => [Rule::requiredIf($perfil === 3), 'nullable', 'integer', 'exists:lojas,id'],
        ];
    }

    /** @return array<string,string> */
    public function messages(): array
    {
        return [
            'required'           => 'O campo :attribute é obrigatório.',
            'min'                => 'O campo :attribute deve ter no mínimo :min caracteres.',
            'max'                => 'O campo :attribute deve ter no máximo :max caracteres.',
            'email'              => 'Informe um e-mail válido.',
            'size'               => 'O campo :attribute deve conter exatamente :size dígitos.',
            'integer'            => 'O campo :attribute deve ser um número válido.',
            'exists'             => 'A :attribute selecionada é inválida.',
            'in'                 => 'O campo :attribute é inválido.',
            'usuario'            => 'Usuário inválido.',
            'email.unique'       => 'Este e-mail já está cadastrado.',
            'cpf.unique'         => 'Este CPF já está cadastrado.',
            'id_loja.required'   => 'Selecione a loja para perfis de Lojista.',
        ];
    }

    /** @return array<string,string> */
    public function attributes(): array
    {
        return [
            'nome'      => 'nome',
            'email'     => 'e-mail',
            'cpf'       => 'CPF',
            'id_perfil' => 'perfil',
            'senha'     => 'senha',
            'id_loja'   => 'loja',
        ];
    }
}
