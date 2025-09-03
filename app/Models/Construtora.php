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

    /**
     * Normaliza um caminho de imagem para o formato relativo ao disco 'public',
     * removendo prefixos "public/" e "storage/" e barras à esquerda.
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

        // URLs completas permanecem como estão (acessor já trata).
        if (Str::startsWith($raw, ['http://', 'https://'])) {
            return $raw;
        }

        // Remove barra inicial e prefixos de pasta expostos.
        $raw = ltrim($raw, '/');
        $raw = preg_replace('#^(public/|storage/)#', '', $raw);

        // Evita retornar diretórios (sem extensão) como caminho válido.
        $ext = pathinfo($raw, PATHINFO_EXTENSION);
        if ($raw === '' || $ext === '') {
            return null;
        }

        return $raw;
    }

    /**
     * Mutator: sempre salva `imagem` normalizada (ex.: "construtoras/uuid.jpg")
     * ou `null` quando inválido.
     *
     * @param  string|null $value
     * @return void
     */
    public function setImagemAttribute(?string $value): void
    {
        $normalized = $this->normalizeImagem($value);
        $this->attributes['imagem'] = $normalized;
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

        // Se já for URL completa, retorna direto.
        if (Str::startsWith($val, ['http://', 'https://'])) {
            return $val;
        }

        // Normaliza de novo por segurança (caso venha "public/..."/"storage/...").
        $path = $this->normalizeImagem($val);
        if (!$path) {
            return null;
        }

        // Gera URL pública do disco 'public' (requer "php artisan storage:link").
        return Storage::disk('public')->url($path); // => "/storage/construtoras/uuid.jpg"
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
     * Retorna os empreendimentos vinculados à construtora.
     *
     * @return HasMany
     */
    public function empreendimentos(): HasMany
    {
        return $this->hasMany(Empreendimento::class, 'idConstrutoras', 'idConstrutoras');
    }
}
