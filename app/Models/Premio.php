<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Modelo de Prêmios/Campanhas.
 *
 * @property int                 $id
 * @property string|null         $titulo
 * @property string|null         $descricao
 * @property string|null         $regras
 * @property string|null         $regulamento
 * @property string|null         $site
 * @property string|null         $banner
 * @property float|null          $valor_viagem
 * @property Carbon|string|null  $dt_inicio
 * @property Carbon|string|null  $dt_fim
 * @property Carbon|string|null  $dt_cadastro
 * @property int|null            $status
 * @property-read string|null    $banner_url
 * @property-read string|null    $regulamento_url
 *
 * @method static findOrFail(mixed $premioId)
 */
class Premio extends Model
{
    /** @var string */
    protected $table = 'premios';

    /** @var bool */
    public $timestamps = false;

    /** Diretórios padrão no disco 'public'. */
    public const BANNER_DIR      = 'premios';

    /** @var array<int, string> */
    protected $fillable = [
        'titulo',
        'descricao',
        'regras',
        'regulamento',
        'site',
        'banner',
        'dt_inicio',
        'dt_fim',
        'dt_cadastro',
        'status',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'dt_inicio'   => 'datetime',
        'dt_fim'      => 'datetime',
        'dt_cadastro' => 'datetime',
        'status'      => 'integer',
    ];

    /** @var array<int, string> */
    protected $appends = ['banner_url', 'regulamento_url'];

    // ------------------------------------------------------------------------------
    // Relações
    // ------------------------------------------------------------------------------

    /** @return HasMany */
    public function faixas(): HasMany
    {
        return $this->hasMany(PremioFaixa::class, 'id_premio')->orderBy('pontos_min');
    }

    // ------------------------------------------------------------------------------
    // Accessors utilitários de data (sem HTML)
    // ------------------------------------------------------------------------------

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

    // ------------------------------------------------------------------------------
    // Escopos
    // ------------------------------------------------------------------------------

    /**
     * Escopo para campanhas ATIVAS na data informada (entre início e fim).
     * Requer: dt_inicio <= data AND dt_fim >= data (dt_fim NÃO nulo).
     *
     * @param  Builder      $query
     * @param  string|null  $data ISO Y-m-d
     * @return Builder
     */
    public function scopeAtivosNoDia(Builder $query, ?string $data = null): Builder
    {
        $hoje = $data ?: Carbon::today()->toDateString();

        return $query
            ->where('status', 1)
            ->whereDate('dt_inicio', '<=', $hoje)
            ->whereNotNull('dt_fim')
            ->whereDate('dt_fim', '>=', $hoje);
    }

    // ------------------------------------------------------------------------------
    // Normalização de arquivos e mutators
    // ------------------------------------------------------------------------------

    /**
     * Normaliza um caminho de arquivo relativo ao disco 'public'.
     *
     * Regras:
     * - Remove URLs absolutas antigas (extrai somente o basename do path).
     * - Remove prefixos "public/" e "storage/" e barras iniciais.
     * - Se não houver subpasta, prefixa o diretório informado ($baseDir).
     * - Retorna null para vazio ou quando não há extensão (evita diretórios).
     *
     * @param  string|null $value    Valor de entrada (pode ser URL absoluta, caminho com prefixos ou só o arquivo)
     * @param  string      $baseDir  Diretório padrão (ex.: self::BANNER_DIR)
     * @return string|null           Caminho normalizado (ex.: "premios/banners/arquivo.jpg")
     */
    protected function normalizeFile(?string $value, string $baseDir): ?string
    {
        if ($value === null) {
            return null;
        }

        $raw = trim($value);
        if ($raw === '') {
            return null;
        }

        // Se vier URL absoluta, extrai apenas o arquivo do path
        if (Str::startsWith($raw, ['http://', 'https://'])) {
            $raw = basename(parse_url($raw, PHP_URL_PATH) ?: $raw);
        }

        // Limpa prefixos e barras iniciais
        $raw = ltrim($raw, '/');
        $raw = preg_replace('#^(public/|storage/)#', '', $raw);

        // Prefixa diretório padrão se não houver subpasta
        if ($raw !== '' && !str_contains($raw, '/')) {
            $raw = trim($baseDir, '/') . '/' . $raw;
        }

        // Evita retornar diretórios sem arquivo (sem extensão)
        $ext = pathinfo($raw, PATHINFO_EXTENSION);
        if ($raw === '' || $ext === '') {
            return null;
        }

        return $raw;
    }

    /**
     * Mutator: persiste somente o basename (ex.: "abc123.jpg").
     * Aceita URL completa, caminho ou basename.
     *
     * @param  string|null $value
     * @return void
     */
    public function setBannerAttribute(?string $value): void
    {
        if ($value === null || trim($value) === '') {
            $this->attributes['banner'] = null;
            return;
        }
        $raw  = trim($value);
        // Extrai apenas o arquivo se vier URL absoluta ou caminho
        $file = basename(parse_url($raw, PHP_URL_PATH) ?: $raw);
        // Recusa diretórios (sem extensão)
        $ext  = pathinfo($file, PATHINFO_EXTENSION);
        $this->attributes['banner'] = $ext ? $file : null;
    }

    /**
     * URL pública do banner (sempre via Storage 'public' e pasta "premios/").
     *
     * @return string|null
     */
    public function getBannerUrlAttribute(): ?string
    {
        if (!$this->banner) {
            return null;
        }
        $path = rtrim(self::BANNER_DIR, '/').'/'.$this->banner; // "premios/hash.ext"
        return Storage::disk('public')->url($path);
    }
}
