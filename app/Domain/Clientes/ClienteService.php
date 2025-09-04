<?php

namespace App\Domain\Clientes;

use App\Models\Usuario;
use Illuminate\Support\Collection;

/**
 * Camada de domínio para Clientes armazenados em `usuario` (perfil = 6).
 * Facilita futura migração para tabela dedicada.
 */
class ClienteService
{
    public const PERFIL_CLIENTE = 6;

    /**
     * Lista clientes com busca por nome/documento.
     *
     * @param  string $q
     * @return Collection<int, Usuario>
     */
    public function listar(string $q = ''): Collection
    {
        $query = Usuario::query()
            ->where('id_perfil', self::PERFIL_CLIENTE)
            ->where('status', 1);

        if ($q !== '') {
            $like = "%{$q}%";
            $query->where(function ($sub) use ($like) {
                $sub->where('nome', 'like', $like)
                    ->orWhere('cpf', 'like', $like);
            });
        }

        return $query->orderBy('nome')->get(['id', 'nome', 'cpf']);
    }

    /**
     * Cria um cliente.
     *
     * @param  array{ tipo_pessoa:'F'|'J', nome:string, documento:string } $data
     * @return Usuario
     */
    public function criar(array $data): Usuario
    {
        $doc = preg_replace('/\D+/', '', $data['documento'] ?? '');
        $usuario = new Usuario();
        $usuario->nome      = $data['nome'];
        $usuario->cpf       = $doc; // mantém no mesmo campo (CPF/CNPJ)
        $usuario->id_perfil = self::PERFIL_CLIENTE;
        $usuario->status    = 1;

        // Campos opcionais para compatibilidade com estrutura atual
        // (evita constraints por NOT NULL em colunas antigas).
        $usuario->email     = $usuario->email ?? null;
        $usuario->login     = $usuario->login ?? null;

        $usuario->save();

        return $usuario;
    }
}
