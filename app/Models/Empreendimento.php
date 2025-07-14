<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class Empreendimento
 *
 * @property int $idEmpreendimentos
 * @property int $idConstrutoras
 * @property string|null $nome
 * @property string|null $site
 * @property string|null $imagem
 * @property int|null $status
 *
 * @property-read Construtora $construtora
 * @property-read PlantaBaixa[] $plantasBaixas
 */
class Empreendimento extends Model
{
    protected $table = 'empreendimentos';
    protected $primaryKey = 'idEmpreendimentos';
    public $timestamps = false;

    protected $fillable = [
        'idConstrutoras',
        'nome',
        'site',
        'imagem',
        'status'
    ];

    /**
     * Retorna a construtora associada ao empreendimento.
     *
     * @return BelongsTo
     */
    public function construtora(): BelongsTo
    {
        return $this->belongsTo(Construtora::class, 'idConstrutoras', 'idConstrutoras');
    }

    /**
     * Retorna as plantas baixas associadas ao empreendimento.
     *
     * @return HasMany
     */
    public function plantasBaixas(): HasMany
    {
        return $this->hasMany(PlantaBaixa::class, 'idEmpreendimentos', 'idEmpreendimentos');
    }
}
