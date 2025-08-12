<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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
 * @property Carbon|string|null $dt_inicio
 * @property Carbon|string|null $dt_fim
 * @property Carbon|string|null $dt_cadastro
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

    /** @return HasMany */
    public function faixas(): HasMany
    {
        return $this->hasMany(PremioFaixa::class, 'id_premio')->orderBy('pontos_min');
    }

    // Accessors utilitários (mantidos sem HTML):
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

    /**
     * Escopo para campanhas ativas na data informada (padrão: hoje).
     *
     * @param  Builder  $query
     * @param  string|null  $data ISO Y-m-d
     * @return Builder
     */
    public function scopeAtivosNoDia(Builder $query, ?string $data = null): Builder
    {
        $hoje = $data ?: Carbon::today()->toDateString();

        return $query->where('status', 1)
            ->whereDate('dt_inicio', '<=', $hoje)
            ->where(fn ($q) => $q->whereNull('dt_fim')->orWhereDate('dt_fim', '>=', $hoje));
    }
}
