<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $idGalerias
 * @property ?int $idGaleriaImagens
 * @property ?string $descricao
 * @property int $status
 * @property string $dt_criacao
 */
class Galeria extends Model
{
    protected $table = 'galerias';
    protected $primaryKey = 'idGalerias';
    public $timestamps = false;

    protected $fillable = [
        'descricao',
        'idGaleriaImagens',
        'status',
        'dt_criacao',
    ];

    public function imagens(): HasMany
    {
        return $this->hasMany(GaleriaImagem::class, 'idGalerias', 'idGalerias')->where('status', 1);
    }

    public function imagemCapa(): HasOne
    {
        return $this->hasOne(GaleriaImagem::class, 'idGaleriaImagens', 'idGaleriaImagens');
    }
}
