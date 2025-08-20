<?php

namespace App\Services;

use App\Models\Profissional;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Orquestra criação/atualização de profissionais (hash de senha, etc.).
 */
class ProfissionalService
{
    /**
     * Cria um profissional.
     *
     * @param  array<string,mixed> $data
     * @return Profissional
     */
    public function create(array $data): Profissional
    {
        $data['cpf']   = isset($data['cpf']) ? preg_replace('/\D+/', '', $data['cpf']) : null;
        $data['nome']  = isset($data['nome']) ? mb_strtoupper(trim($data['nome'])) : null;

        return DB::transaction(function () use ($data) {
            if (!empty($data['senha'])) {
                $data['senha'] = Hash::make($data['senha']);
            }

            // status padrão 1 (ativo) se nada for enviado
            $data['status'] = $data['status'] ?? 1;

            /** @var Profissional $p */
            $p = Profissional::query()->create($data);

            return $p;
        });
    }

    /**
     * Atualiza um profissional.
     *
     * @param  Profissional        $prof
     * @param  array<string,mixed> $data
     * @return Profissional
     */
    public function update(Profissional $prof, array $data): Profissional
    {
        $data['cpf']   = isset($data['cpf']) ? preg_replace('/\D+/', '', $data['cpf']) : null;
        $data['nome']  = isset($data['nome']) ? mb_strtoupper(trim($data['nome'])) : null;

        return DB::transaction(function () use ($prof, $data) {
            if (array_key_exists('senha', $data)) {
                if (!empty($data['senha'])) {
                    $data['senha'] = Hash::make($data['senha']);
                } else {
                    unset($data['senha']); // não sobreescreve com vazio
                }
            }

            $prof->fill($data);
            $prof->save();

            return $prof;
        });
    }

    /**
     * Exclusão lógica (status=2).
     */
    public function delete(Profissional $prof): void
    {
        DB::transaction(function () use ($prof) {
            $prof->status = 2;
            $prof->save();
        });
    }
}
