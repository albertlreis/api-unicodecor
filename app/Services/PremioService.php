<?php

namespace App\Services;

use App\Models\Premio;
use App\Models\PremioFaixa;
use Illuminate\Contracts\Filesystem\Factory as StorageFactory;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\UploadedFile;

/**
 * Serviço de criação/atualização de Prêmios.
 */
class PremioService
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly StorageFactory $storage,
    ) {}

    /** @return string sha1 do conteúdo */
    private function sha1Of(UploadedFile $file): string
    {
        $ctx = hash_init('sha1');
        $stream = fopen($file->getRealPath(), 'rb');
        while (!feof($stream)) {
            $buf = fread($stream, 1024 * 1024);
            if ($buf !== false) {
                hash_update($ctx, $buf);
            }
        }
        fclose($stream);
        return hash_final($ctx);
    }

    /** @return string extensão coerente (jpg/png/webp) */
    private function resolveExtension(UploadedFile $file): string
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: '');
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            return $ext === 'jpeg' ? 'jpg' : $ext;
        }
        $mime = strtolower((string) $file->getMimeType());
        if (str_contains($mime, 'jpeg')) return 'jpg';
        if (str_contains($mime, 'png'))  return 'png';
        if (str_contains($mime, 'webp')) return 'webp';
        return 'jpg';
    }

    /**
     * Salva no disco 'public/premios' e retorna apenas "hash.ext".
     */
    private function storeBanner(UploadedFile $file): string
    {
        $hash = $this->sha1Of($file);
        $ext  = $this->resolveExtension($file);
        $name = "{$hash}.{$ext}";
        $this->storage->disk('public')->putFileAs('premios', $file, $name);
        return $name;
    }

    /**
     * Cria prêmio (status=1). Banner é opcional; quando presente salva "hash.ext".
     *
     * @param array<string, mixed> $data
     * @param UploadedFile|null    $arquivo
     */
    public function criar(array $data, ?UploadedFile $arquivo): Premio
    {
        return $this->db->transaction(function () use ($data, $arquivo) {
            $premio = new Premio();
            $premio->titulo      = $data['titulo'];
            $premio->regras      = $data['regras']      ?? null;
            $premio->regulamento = $data['regulamento'] ?? null;
            $premio->dt_inicio   = $data['dt_inicio'];
            $premio->dt_fim      = $data['dt_fim'];
            $premio->status      = 1;
            $premio->dt_cadastro = now();

            // banner opcional
            if ($arquivo instanceof UploadedFile && $arquivo->isValid()) {
                $premio->banner = $this->storeBanner($arquivo); // apenas "hash.ext"
            }

            $premio->save();

            $this->syncFaixas($premio, $data['faixas'] ?? []);
            return $premio->load('faixas');
        });
    }

    /**
     * Atualiza prêmio. Se houver 'arquivo', troca a imagem (apaga antiga).
     *
     * @param array<string, mixed> $data
     * @throws \Throwable
     */
    public function atualizar(Premio $premio, array $data, ?UploadedFile $arquivo): Premio
    {
        return $this->db->transaction(function () use ($premio, $data, $arquivo) {
            $premio->titulo      = $data['titulo'];
            $premio->regras      = $data['regras']      ?? null;
            $premio->regulamento = $data['regulamento'] ?? null;
            $premio->dt_inicio   = $data['dt_inicio'];
            $premio->dt_fim      = $data['dt_fim'];

            if ($arquivo instanceof UploadedFile && $arquivo->isValid()) {
                $newName = $this->storeBanner($arquivo);
                if ($premio->banner && $premio->banner !== $newName) {
                    $this->storage->disk('public')->delete('premios/'.ltrim($premio->banner, '/'));
                }
                $premio->banner = $newName;
            }

            $premio->save();

            $this->syncFaixas($premio, $data['faixas'] ?? []);
            return $premio->load('faixas');
        });
    }

    /**
     * Upsert das faixas + exclusão das removidas.
     *
     * @param array<int, array<string, mixed>> $faixas
     */
    private function syncFaixas(Premio $premio, array $faixas): void
    {
        $idsMantidos = [];

        foreach ($faixas as $fx) {
            $payload = [
                'pontos_min'   => (int) ($fx['pontos_min'] ?? 0),
                'pontos_max'   => array_key_exists('pontos_max', $fx)
                    ? ($fx['pontos_max'] !== null ? (int) $fx['pontos_max'] : null)
                    : null,
                'vl_viagem'    => (float) ($fx['vl_viagem'] ?? 0),
                'acompanhante' => (int) ($fx['acompanhante'] ?? 0),
                'descricao'    => $fx['descricao'] ?? null,
            ];

            if (!empty($fx['id'])) {
                $row = PremioFaixa::query()
                    ->where('id', (int) $fx['id'])
                    ->where('id_premio', $premio->id)
                    ->first();

                if ($row) {
                    $row->fill($payload)->save();
                    $idsMantidos[] = $row->id;
                    continue;
                }
            }

            $novo = new PremioFaixa($payload);
            $novo->id_premio = $premio->id;
            $novo->save();
            $idsMantidos[] = $novo->id;
        }

        if (!empty($idsMantidos)) {
            PremioFaixa::query()
                ->where('id_premio', $premio->id)
                ->whereNotIn('id', $idsMantidos)
                ->delete();
        } else {
            PremioFaixa::query()->where('id_premio', $premio->id)->delete();
        }
    }

    /**
     * @param \App\Models\Premio $premio
     * @param bool|int $status
     * @return Premio
     */
    public function alterarStatus(Premio $premio, bool|int $status): Premio
    {
        $premio->status = (int)((bool)$status);
        $premio->save();

        return $premio->refresh();
    }
}
