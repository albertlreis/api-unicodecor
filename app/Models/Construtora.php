<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Modelo de Construtoras.
 *
 * @property int $idConstrutoras
 * @property string $razao_social
 * @property string|null $cnpj
 * @property string|null $imagem  Caminho relativo no disco 'public' (ex.: "construtoras/uuid.jpg")
 * @property int $status          1=ativo, 0=desabilitado, -1=excluído
 * @property-read string|null $imagem_url URL pública (ex.: "/storage/construtoras/uuid.jpg")
 */
class Construtora extends Model
{
    protected $table = 'construtoras';
    protected $primaryKey = 'idConstrutoras';
    public $timestamps = false;

    protected $fillable = ['razao_social', 'cnpj', 'imagem', 'status'];

    protected $casts = [
        'status' => 'integer',
    ];

    /** Diretório padrão das imagens no disco 'public'. */
    private const IMAGE_DIR = 'construtoras';

    /**
     * Normaliza um caminho de imagem relativo ao disco 'public'.
     * - Preserva URLs absolutas.
     * - Remove prefixos "public/" e "storage/".
     * - Se não houver subpasta, prefixa "construtoras/".
     *
     * @param  string|null $value
     * @return string|null
     */
    private function normalizeImagem(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $raw = trim($value);
        if ($raw === '') {
            return null;
        }

        // URLs completas permanecem como estão.
        if (Str::startsWith($raw, ['http://', 'https://'])) {
            return $raw;
        }

        // Remove barra inicial e prefixos expostos.
        $raw = ltrim($raw, '/');
        $raw = preg_replace('#^(public/|storage/)#', '', $raw);

        // Se veio só o nome do arquivo (sem '/'), prefixa o diretório padrão.
        if (!str_contains($raw, '/')) {
            $raw = self::IMAGE_DIR . '/' . $raw;
        }

        // Evita retornar diretórios sem arquivo.
        $ext = pathinfo($raw, PATHINFO_EXTENSION);
        if ($raw === '' || $ext === '') {
            return null;
        }

        return $raw;
    }

    /**
     * Mutator: salva `imagem` normalizada (ex.: "construtoras/uuid.jpg") ou `null`.
     *
     * @param  string|null $value
     * @return void
     */
    public function setImagemAttribute(?string $value): void
    {
        $this->attributes['imagem'] = $this->normalizeImagem($value);
    }

    /**
     * URL pública da imagem (tratando caminhos locais e URLs legadas).
     *
     * @return string|null
     */
    public function getImagemUrlAttribute(): ?string
    {
        $val = $this->imagem;

        if (!$val) {
            return null;
        }

        // Se já for URL absoluta, retorna como está.
        if (Str::startsWith($val, ['http://', 'https://'])) {
            return $val;
        }

        // Normaliza e garante diretório padrão quando necessário.
        $path = $this->normalizeImagem($val);
        if (!$path) {
            return null;
        }

        // Gera URL pública (requer "php artisan storage:link").
        return Storage::disk('public')->url($path); // "/storage/construtoras/arquivo.jpg"
    }

    /** Escopo: não excluídas (status >= 0). */
    public function scopeNaoExcluidas(Builder $q): Builder
    {
        return $q->where('status', '>=', 0);
    }

    /** Escopo: ativas (status = 1). */
    public function scopeAtivas(Builder $q): Builder
    {
        return $q->where('status', 1);
    }

    /**
     * Empreendimentos vinculados.
     *
     * @return HasMany
     */
    public function empreendimentos(): HasMany
    {
        return $this->hasMany(Empreendimento::class, 'idConstrutoras', 'idConstrutoras');
    }
}
