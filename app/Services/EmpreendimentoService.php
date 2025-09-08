<?php

namespace App\Services;

use App\Models\Empreendimento;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Orquestra a persistência de Empreendimentos (dados + imagem).
 */
class EmpreendimentoService
{
    /**
     * Cria um empreendimento.
     *
     * @param array<string,mixed> $payload
     * @return Empreendimento
     * @throws Throwable
     */
    public function create(array $payload): Empreendimento
    {
        return DB::transaction(function () use ($payload) {
            $imagePath = $this->storeImageIfNeeded($payload['imagem'] ?? null);

            /** @var Empreendimento $emp */
            $emp = Empreendimento::query()->create([
                'idConstrutoras' => $payload['idConstrutoras'],
                'nome'           => $payload['nome'] ?? null,
                'site'           => $payload['site'] ?? null,
                'imagem'         => $imagePath,
                'status'         => (int)($payload['status'] ?? 1),
            ]);

            return $emp;
        });
    }

    /**
     * Atualiza um empreendimento.
     *
     * @param Empreendimento $emp
     * @param array<string,mixed> $payload
     * @return Empreendimento
     * @throws Throwable
     */
    public function update(Empreendimento $emp, array $payload): Empreendimento
    {
        return DB::transaction(function () use ($emp, $payload) {
            // Substituição da imagem (se enviada)
            if (!empty($payload['imagem']) && $payload['imagem'] instanceof UploadedFile) {
                $newPath = $this->storeImageIfNeeded($payload['imagem']);
                $this->deleteOldImageIfExists($emp->imagem);
                $emp->imagem = $newPath;
            }

            // Campos simples
            foreach (['idConstrutoras', 'nome', 'site', 'status'] as $field) {
                if (array_key_exists($field, $payload)) {
                    $emp->{$field} = $payload[$field];
                }
            }

            $emp->save();
            return $emp;
        });
    }

    /**
     * Exclusão lógica (status = -1).
     *
     * @param Empreendimento $emp
     * @return void
     * @throws Throwable
     */
    public function softDelete(Empreendimento $emp): void
    {
        DB::transaction(function () use ($emp) {
            $emp->status = -1;
            $emp->save();
        });
    }

    /**
     * Salva imagem no disco 'public'.
     *
     * @param UploadedFile|null $file
     * @return string|null caminho relativo salvo no BD
     */
    private function storeImageIfNeeded(?UploadedFile $file): ?string
    {
        if (!$file) {
            return null;
        }

        $ext = $file->getClientOriginalExtension() ?: $file->extension();
        $name = Str::uuid()->toString() . ($ext ? ('.' . strtolower($ext)) : '');

        $dir = 'empreendimentos';

        return $file->storeAs($dir, $name, ['disk' => 'public']);
    }

    /**
     * Remove arquivo antigo se existir.
     *
     * @param string|null $path
     * @return void
     */
    private function deleteOldImageIfExists(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
