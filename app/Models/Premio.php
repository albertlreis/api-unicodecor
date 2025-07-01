<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

/**
 * @property int $id
 * @property string|null $titulo
 * @property string|null $descricao
 * @property string|null $regras
 * @property string|null $regulamento
 * @property string|null $site
 * @property string|null $banner
 * @property float|null $pontos
 * @property float|null $valor_viagem
 * @property string|null $dt_inicio
 * @property string|null $dt_fim
 * @property string|null $dt_cadastro
 * @property int|null $status
 */
class Premio extends Model
{
    protected $table = 'premios';

    public $timestamps = false;

    protected $fillable = [
        'titulo',
        'descricao',
        'regras',
        'regulamento',
        'site',
        'banner',
        'pontos',
        'valor_viagem',
        'dt_inicio',
        'dt_fim',
        'dt_cadastro',
        'status',
    ];

    protected $casts = [
        'dt_inicio'    => 'datetime',
        'dt_fim'       => 'datetime',
        'dt_cadastro'  => 'datetime',
        'pontos'       => 'float',
        'valor_viagem' => 'float',
        'status'       => 'integer',
    ];

    // -- Relacionamentos

    public function faixas(): HasMany
    {
        return $this->hasMany(PremioFaixa::class, 'id_premio');
    }

    // -- Accessors

    public function getDtCadastroFormatadoAttribute(): ?string
    {
        return $this->dt_cadastro ? Carbon::parse($this->dt_cadastro)->format('d/m/Y') : null;
    }

    public function getDtInicioFormatadoAttribute(): ?string
    {
        return $this->dt_inicio ? Carbon::parse($this->dt_inicio)->format('d/m/Y') : null;
    }

    public function getDtFimFormatadoAttribute(): ?string
    {
        return $this->dt_fim ? Carbon::parse($this->dt_fim)->format('d/m/Y') : null;
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

    public function getValorViagemFormatadoAttribute(): ?string
    {
        return $this->valor_viagem ? number_format($this->valor_viagem, 2, ',', '.') : null;
    }

    public function getPontosFormatadoAttribute(): ?string
    {
        return $this->pontos ? number_format($this->pontos, 2, ',', '.') : null;
    }
}
