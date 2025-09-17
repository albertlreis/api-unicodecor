<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int         $idGalerias
 * @property int|null    $idGaleriaImagens  FK da imagem de capa
 * @property string|null $descricao
 * @property int         $status
 * @property string      $dt_criacao
 *
 * @property-read GaleriaImagem|null $imagemCapa
 */
class Galeria extends Model
{
    protected $table = 'galerias';
    protected $primaryKey = 'idGalerias';
    public $timestamps = false;

    /** @var array<int, string> */
    protected $fillable = [
        'descricao',
        'idGaleriaImagens',
        'status',
        'dt_criacao',
    ];

    /**
     * Imagens ativas da galeria (status=1).
     *
     * @return HasMany
     */
    public function imagens(): HasMany
    {
        return $this->hasMany(GaleriaImagem::class, 'idGalerias', 'idGalerias')
            ->where('status', 1);
    }

    /**
     * Imagem de capa da galeria (FK em galeria.idGaleriaImagens -> galeria_imagens.idGaleriaImagens).
     *
     * @return BelongsTo
     */
    public function imagemCapa(): BelongsTo
    {
        return $this->belongsTo(GaleriaImagem::class, 'idGaleriaImagens', 'idGaleriaImagens');
    }
}
