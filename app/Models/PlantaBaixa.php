<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class PlantaBaixa
 *
 * @property int $idPlantasBaixas
 * @property int $idEmpreendimentos
 * @property string $titulo
 * @property string $descricao
 * @property string $arquivo
 * @property string $nome
 * @property int|null $status
 *
 * @property-read Empreendimento $empreendimento
 */
class PlantaBaixa extends Model
{
    protected $table = 'plantas_baixas';
    protected $primaryKey = 'idPlantasBaixas';
    public $timestamps = false;

    protected $fillable = [
        'idEmpreendimentos',
        'titulo',
        'descricao',
        'arquivo',
        'nome',
        'status'
    ];

    /**
     * Retorna o empreendimento vinculado à planta baixa.
     *
     * @return BelongsTo
     */
    public function empreendimento(): BelongsTo
    {
        return $this->belongsTo(Empreendimento::class, 'idEmpreendimentos', 'idEmpreendimentos');
    }
}
