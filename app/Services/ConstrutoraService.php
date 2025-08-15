<?php

namespace App\Services;

use App\Models\Construtora;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Service para persistência de Construtoras (dados + imagem).
 */
class ConstrutoraService
{
    /** Disco público (mapeado para /public/storage). */
    private string $disk = 'public';

    /**
     * Cria uma construtora.
     * @param  array<string, mixed> $payload
     */
    public function create(array $payload): Construtora
    {
        return DB::transaction(function () use ($payload) {
            $imagemPath = $this->storeImageIfNeeded($payload['imagem'] ?? null);

            $construtora = Construtora::query()->create([
                'razao_social' => $payload['razao_social'],
                'cnpj'         => $payload['cnpj'] ?? null,
                'imagem'       => $imagemPath, // ex.: "construtoras/uuid.jpg"
                'status'       => $payload['status'] ?? 1,
            ]);

            return $construtora->fresh();
        });
    }

    /**
     * Atualiza uma construtora.
     * @param  array<string, mixed> $payload
     */
    public function update(Construtora $construtora, array $payload): Construtora
    {
        return DB::transaction(function () use ($construtora, $payload) {
            if (array_key_exists('imagem', $payload)) {
                $new = $this->storeImageIfNeeded($payload['imagem']);
                if ($new && $construtora->imagem) {
                    Storage::disk($this->disk)->delete($construtora->imagem);
                }
                if ($new) {
                    $construtora->imagem = $new;
                } elseif ($payload['imagem'] === null) {
                    // Se quiser permitir apagar a imagem quando vier explicitamente null:
                    Storage::disk($this->disk)->delete($construtora->imagem ?? '');
                    $construtora->imagem = null;
                }
            }

            if (array_key_exists('razao_social', $payload)) {
                $construtora->razao_social = (string) $payload['razao_social'];
            }
            if (array_key_exists('cnpj', $payload)) {
                $construtora->cnpj = $payload['cnpj'] ?: null;
            }
            if (array_key_exists('status', $payload)) {
                $construtora->status = (int) $payload['status'];
            }

            $construtora->save();

            return $construtora->fresh();
        });
    }

    /**
     * Marca como excluída (status = -1) e (opcional) remove o arquivo local.
     */
    public function softDelete(Construtora $construtora, bool $deleteImage = false): void
    {
        DB::transaction(function () use ($construtora, $deleteImage) {
            $construtora->status = -1;
            $construtora->save();

            if ($deleteImage && $construtora->imagem) {
                Storage::disk($this->disk)->delete($construtora->imagem);
            }
        });
    }

    /**
     * Salva a imagem no disco 'public' e retorna o caminho relativo ("construtoras/uuid.jpg").
     *
     * @param  \Illuminate\Http\UploadedFile|null $file
     * @return string|null
     */
    private function storeImageIfNeeded(?UploadedFile $file): ?string
    {
        if (!$file) {
            return null;
        }

        $ext  = $file->getClientOriginalExtension() ?: 'jpg';
        $name = Str::uuid()->toString() . '.' . strtolower($ext);

        // storage/app/public/construtoras/uuid.jpg
        return $file->storeAs('construtoras', $name, $this->disk);
    }
}
