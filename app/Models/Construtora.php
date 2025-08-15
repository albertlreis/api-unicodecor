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
     * URL pública da imagem (tratando caminhos locais e URLs legadas).
     */
    public function getImagemUrlAttribute(): ?string
    {
        if (!$this->imagem) {
            return null;
        }

        if (Str::startsWith($this->imagem, ['http://', 'https://'])) {
            return $this->imagem;
        }

        // Normaliza: remove "public/" do início, se houver.
        $path = ltrim(preg_replace('#^public/#', '', $this->imagem), '/');

        // Gera URL pública do disco 'public' (requer "php artisan storage:link")
        return Storage::disk('public')->url($path);
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
