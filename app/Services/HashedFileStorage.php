<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Factory as StorageFactory;
use Illuminate\Http\UploadedFile;

/**
 * Armazena arquivos usando SHA1 do conteúdo como nome (hash.ext).
 * Útil para evitar duplicações e padronizar caminhos.
 */
class HashedFileStorage
{
    public function __construct(
        private readonly StorageFactory $storage
    ) {}

    /** Calcula SHA1 do conteúdo do UploadedFile */
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

    /** Resolve extensão coerente forçando uma extensão específica quando preciso. */
    private function resolveExtension(UploadedFile $file, ?string $forceExt = null): string
    {
        if ($forceExt) return strtolower(ltrim($forceExt, '.'));
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: '');
        return $ext !== '' ? $ext : 'bin';
    }

    /**
     * Armazena em disk/path como "hash.ext" e retorna "hash.ext".
     *
     * @param string $disk   ex.: 'public'
     * @param string $dir    ex.: 'plantas'
     * @param string|null $forceExt ex.: 'dwg'
     */
    public function putWithHash(UploadedFile $file, string $disk, string $dir, ?string $forceExt = null): string
    {
        $hash = $this->sha1Of($file);
        $ext  = $this->resolveExtension($file, $forceExt);
        $name = "{$hash}.{$ext}";
        $this->storage->disk($disk)->putFileAs($dir, $file, $name);
        return $name;
    }
}
