<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class Construtora
 *
 * @property int $idConstrutoras
 * @property string $razao_social
 * @property string|null $cnpj
 * @property string|null $imagem
 * @property int $status
 *
 * @property-read Empreendimento[] $empreendimentos
 */
class Construtora extends Model
{
    protected $table = 'construtoras';
    protected $primaryKey = 'idConstrutoras';
    public $timestamps = false;

    protected $fillable = [
        'razao_social',
        'cnpj',
        'imagem',
        'status'
    ];

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
