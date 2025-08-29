<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $idGaleriaImagens
 * @property int $idGalerias
 * @property ?string $arquivo
 * @property ?string $nome
 * @property ?string $descricao
 * @property string $dt_criacao
 * @property int $status
 */
class GaleriaImagem extends Model
{
    protected $table = 'galeria_imagens';
    protected $primaryKey = 'idGaleriaImagens';
    public $timestamps = false;

    protected $fillable = [
        'idGalerias',
        'arquivo',
        'nome',
        'descricao',
        'status',
        'dt_criacao',
    ];

    public function galeria(): BelongsTo
    {
        return $this->belongsTo(Galeria::class, 'idGalerias', 'idGalerias');
    }
}
