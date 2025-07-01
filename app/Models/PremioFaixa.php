<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $id_premio
 * @property int $pontos_min
 * @property int|null $pontos_max
 * @property bool|null $acompanhante
 * @property string|null $descricao
 * @property float|null $vl_viagem
 */
class PremioFaixa extends Model
{
    protected $table = 'premio_faixas';

    public $timestamps = false;

    protected $fillable = [
        'id_premio',
        'pontos_min',
        'pontos_max',
        'acompanhante',
        'descricao',
        'vl_viagem',
    ];

    protected $casts = [
        'acompanhante' => 'boolean',
        'vl_viagem' => 'float',
    ];

    /**
     * Relacionamento: faixa pertence a um prêmio.
     */
    public function premio(): BelongsTo
    {
        return $this->belongsTo(Premio::class, 'id_premio');
    }

    // Accessors formatados

    public function getValorViagemFormatadoAttribute(): ?string
    {
        return $this->vl_viagem !== null
            ? number_format($this->vl_viagem, 2, ',', '.')
            : null;
    }

    public function getPontosRangeFormatadoAttribute(): string
    {
        $min = number_format($this->pontos_min, 0, '', '.');
        $max = $this->pontos_max ? number_format($this->pontos_max, 0, '', '.') : '∞';
        return "$min a $max";
    }

    public function getAcompanhanteLabelAttribute(): string
    {
        return $this->acompanhante ? 'Sim' : 'Não';
    }
}
