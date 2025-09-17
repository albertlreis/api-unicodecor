<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int         $idGaleriaImagens
 * @property int         $idGalerias
 * @property string|null $arquivo
 * @property string|null $nome
 * @property string|null $descricao
 * @property string      $dt_criacao
 * @property int         $status
 *
 * @property-read Galeria $galeria
 */
class GaleriaImagem extends Model
{
    protected $table = 'galeria_imagens';
    protected $primaryKey = 'idGaleriaImagens';
    public $timestamps = false;

    /** @var array<int, string> */
    protected $fillable = [
        'idGalerias',
        'arquivo',
        'nome',
        'descricao',
        'status',
        'dt_criacao',
    ];

    /**
     * Galeria à qual a imagem pertence.
     *
     * @return BelongsTo
     */
    public function galeria(): BelongsTo
    {
        return $this->belongsTo(Galeria::class, 'idGalerias', 'idGalerias');
    }
}
