<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @property int $idEmpreendimentos
 * @property int $idConstrutoras
 * @property string|null $nome
 * @property string|null $site
 * @property string|null $imagem
 * @property int $status
 * @property-read string|null $imagem_url
 */
class Empreendimento extends Model
{
    /** @var string */
    protected $table = 'empreendimentos';

    /** @var string */
    protected $primaryKey = 'idEmpreendimentos';

    /** @var bool */
    public $timestamps = false;

    /** @var array<int,string> */
    protected $fillable = [
        'idConstrutoras',
        'nome',
        'site',
        'imagem',
        'status',
    ];

    /** @var array<string,string> */
    protected $casts = [
        'status' => 'int',
    ];

    /** @var array<int,string> */
    protected $appends = ['imagem_url'];

    /**
     * Usa a PK como route key (idEmpreendimentos).
     *
     * @return string
     */
    public function getRouteKeyName(): string
    {
        return $this->getKeyName();
    }

    /**
     * Construtora à qual o empreendimento pertence.
     *
     * @return BelongsTo<Construtora,Empreendimento>
     */
    public function construtora(): BelongsTo
    {
        return $this->belongsTo(Construtora::class, 'idConstrutoras', 'idConstrutoras');
    }

    /**
     * URL pública da imagem.
     * - Se $imagem já começar com http/https, retorna como está (host externo).
     * - Caso contrário, resolve via Storage 'public'.
     *
     * @return string|null
     */
    public function getImagemUrlAttribute(): ?string
    {
        if (!$this->imagem) {
            return null;
        }

        if (Str::startsWith($this->imagem, ['http://', 'https://'])) {
            return $this->imagem;
        }

        return Storage::disk('public')->url($this->imagem);
    }

    /**
     * Plantas baixas associadas ao empreendimento.
     *
     * @return HasMany
     */
    public function plantasBaixas(): HasMany
    {
        return $this->hasMany(PlantaBaixa::class, 'idEmpreendimentos', 'idEmpreendimentos');
    }
}
