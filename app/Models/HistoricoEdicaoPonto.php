<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

/**
 * @property int $id
 * @property int|null $id_pontos
 * @property int|null $id_usuario_alteracao
 * @property float|null $valor_anterior
 * @property float|null $valor_novo
 * @property string|null $dt_referencia_anterior
 * @property string|null $dt_referencia_novo
 * @property string|null $dt_alteracao
 *
 * @property-read Ponto|null $ponto
 * @property-read Usuario|null $usuarioAlteracao
 */
class HistoricoEdicaoPonto extends Model
{
    protected $table = 'historico_edicao_pontos';

    protected $fillable = [
        'id_pontos',
        'id_usuario_alteracao',
        'valor_anterior',
        'valor_novo',
        'dt_referencia_anterior',
        'dt_referencia_novo',
        'dt_alteracao',
    ];

    public $timestamps = false;

    // -- Relacionamentos

    public function ponto(): BelongsTo
    {
        return $this->belongsTo(Ponto::class, 'id_pontos');
    }

    public function usuarioAlteracao(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_alteracao');
    }

    // -- Accessors

    public function getDtAlteracaoFormatadoAttribute(): ?string
    {
        return $this->dt_alteracao ? Carbon::parse($this->dt_alteracao)->format('d/m/Y H:i') : null;
    }

    public function getDtReferenciaAnteriorFormatadoAttribute(): ?string
    {
        return $this->dt_referencia_anterior ? Carbon::parse($this->dt_referencia_anterior)->format('d/m/Y') : null;
    }

    public function getDtReferenciaNovoFormatadoAttribute(): ?string
    {
        return $this->dt_referencia_novo ? Carbon::parse($this->dt_referencia_novo)->format('d/m/Y') : null;
    }

    public function getValorAnteriorFormatadoAttribute(): string
    {
        return number_format($this->valor_anterior ?? 0, 2, ',', '.');
    }

    public function getValorNovoFormatadoAttribute(): string
    {
        return number_format($this->valor_novo ?? 0, 2, ',', '.');
    }
}
