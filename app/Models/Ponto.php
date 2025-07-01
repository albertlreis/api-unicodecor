<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

/**
 * @property int $id
 * @property int $id_profissional
 * @property int $id_lojista
 * @property int|null $id_loja
 * @property int|null $id_cliente
 * @property float|null $valor
 * @property string|null $orcamento
 * @property string|null $dt_referencia
 * @property string|null $dt_cadastro
 * @property string|null $dt_edicao
 * @property int|null $status
 */
class Ponto extends Model
{
    protected $table = 'pontos';

    protected $fillable = [
        'id_profissional',
        'id_lojista',
        'id_loja',
        'id_cliente',
        'valor',
        'orcamento',
        'dt_referencia',
        'dt_cadastro',
        'dt_edicao',
        'status',
    ];

    public $timestamps = false;

    // -- Relacionamentos

    public function profissional(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_profissional');
    }

    public function lojista(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_lojista');
    }

    public function loja(): BelongsTo
    {
        return $this->belongsTo(Loja::class, 'id_loja');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_cliente');
    }

    // -- Accessors

    public function getDtCadastroFormatadoAttribute(): ?string
    {
        return $this->dt_cadastro ? Carbon::parse($this->dt_cadastro)->format('d/m/Y') : null;
    }

    public function getDtReferenciaFormatadoAttribute(): ?string
    {
        return $this->dt_referencia ? Carbon::parse($this->dt_referencia)->format('d/m/Y') : null;
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            0 => '<span class="label label-warning">Desabilitado</span>',
            1 => '<span class="label label-primary">Ativo</span>',
            2 => '<span class="label label-danger">Excluído</span>',
            default => '<span class="label label-secondary">Desconhecido</span>',
        };
    }

    public function getValorFormatadoAttribute(): string
    {
        return number_format($this->valor, 2, ',', '.');
    }

    public function getProfissionalNomeEmailAttribute(): string
    {
        if ($this->profissional) {
            return $this->profissional->email
                ? "{$this->profissional->nome}<br />{$this->profissional->email}"
                : $this->profissional->nome;
        }
        return '--';
    }

    public function getLojaNomeAttribute(): ?string
    {
        return $this->loja?->nome;
    }

    public function getClienteNomeAttribute(): ?string
    {
        return $this->cliente?->nome;
    }

    public function getLojistaNomeAttribute(): ?string
    {
        return $this->lojista?->nome;
    }
}
