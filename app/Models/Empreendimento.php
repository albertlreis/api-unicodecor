<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Modelo de Empreendimentos.
 *
 * @property int         $idEmpreendimentos
 * @property int         $idConstrutoras
 * @property string|null $nome
 * @property string|null $site
 * @property string|null $imagem  Caminho relativo no disco 'public' (ex.: "empreendimentos/uuid.jpg")
 * @property int         $status  1=ativo, 0=desabilitado, -1=excluído
 * @property-read string|null $imagem_url URL pública (ex.: "/storage/empreendimentos/uuid.jpg")
 */
class Empreendimento extends Model
{
    /** @var string */
    protected $table = 'empreendimentos';

    /** @var string */
    protected $primaryKey = 'idEmpreendimentos';

    /** @var bool */
    public $timestamps = false;

    /** @var array<int,string> */
    protected $fillable = [
        'idConstrutoras',
        'nome',
        'site',
        'imagem',
        'status',
    ];

    /** @var array<string,string> */
    protected $casts = [
        'status' => 'int',
    ];

    /** @var array<int,string> */
    protected $appends = ['imagem_url'];

    /** Diretório padrão das imagens no disco 'public'. */
    private const IMAGE_DIR = 'empreendimentos';

    /**
     * Usa a PK como route key (idEmpreendimentos).
     *
     * @return string
     */
    public function getRouteKeyName(): string
    {
        return $this->getKeyName();
    }

    /**
     * Construtora à qual o empreendimento pertence.
     *
     * @return BelongsTo<Construtora,Empreendimento>
     */
    public function construtora(): BelongsTo
    {
        return $this->belongsTo(Construtora::class, 'idConstrutoras', 'idConstrutoras');
    }

    /**
     * Plantas baixas associadas ao empreendimento.
     *
     * @return HasMany
     */
    public function plantasBaixas(): HasMany
    {
        return $this->hasMany(PlantaBaixa::class, 'idEmpreendimentos', 'idEmpreendimentos');
    }

    /**
     * Normaliza um caminho de imagem:
     * - Remove URLs absolutas antigas e extrai apenas o arquivo.
     * - Remove prefixos "public/" e "storage/".
     * - Se não houver subpasta, prefixa "empreendimentos/".
     *
     * @param  string|null $value
     * @return string|null
     */
    private function normalizeImagem(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $raw = trim($value);
        if ($raw === '') {
            return null;
        }

        // Caso seja URL absoluta antiga, pega só o basename
        if (Str::startsWith($raw, ['http://', 'https://'])) {
            $raw = basename(parse_url($raw, PHP_URL_PATH) ?? $raw);
        }

        // Remove prefixos e barras iniciais
        $raw = ltrim($raw, '/');
        $raw = preg_replace('#^(public/|storage/)#', '', $raw);

        // Se for apenas o nome do arquivo, adiciona diretório padrão
        if ($raw !== '' && !str_contains($raw, '/')) {
            $raw = self::IMAGE_DIR . '/' . $raw;
        }

        $ext = pathinfo($raw, PATHINFO_EXTENSION);
        if ($raw === '' || $ext === '') {
            return null;
        }

        return $raw;
    }

    public function setImagemAttribute(?string $value): void
    {
        $this->attributes['imagem'] = $this->normalizeImagem($value);
    }

    public function getImagemUrlAttribute(): ?string
    {
        $val = $this->imagem;

        if (!$val) {
            return null;
        }

        $path = $this->normalizeImagem($val);
        if (!$path) {
            return null;
        }

        // Sempre retorna a URL do storage
        return Storage::disk('public')->url($path);
    }
}
