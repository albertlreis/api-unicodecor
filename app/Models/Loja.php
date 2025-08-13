<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string|null $razao
 * @property string $nome
 * @property string|null $cnpj
 * @property string|null $fone
 * @property string|null $endereco
 * @property string|null $eletronico
 * @property string|null $email
 * @property string|null $apresentacao
 * @property string|null $logomarca
 * @property int $status
 */
class Loja extends Model
{
    protected $table = 'lojas';
    public $timestamps = false;

    protected $fillable = [
        'razao',
        'nome',
        'estadual',
        'municipal',
        'endereco',
        'complemento',
        'bairro',
        'cep',
        'id_cidade',
        'id_estado',
        'fone',
        'logomarca',
        'eletronico',
        'contato',
        'email',
        'celular',
        'contato2',
        'email2',
        'celular2',
        'cnpj',
        'apresentacao',
        'maps',
        'status',
    ];

    protected $appends = ['logomarca_url'];

    /** URL pública da logomarca (aceita já-URL do legado). */
    public function getLogomarcaUrlAttribute(): ?string
    {
        if (!$this->logomarca) return null;
        if (preg_match('~^https?://~i', $this->logomarca)) return $this->logomarca;
        return Storage::disk('public')->url($this->logomarca);
    }

    /** Escopo: apenas ativas (status=1). */
    public function scopeAtivas(Builder $q): Builder
    {
        return $q->where('status', 1);
    }

    /** Mutator CNPJ tolerante a null; mantém máscara padrão se possível. */
    public function setCnpjAttribute($value): void
    {
        if ($value === null) { $this->attributes['cnpj'] = null; return; }
        $digits = preg_replace('/\D+/', '', (string)$value);
        if (strlen($digits) === 14) {
            $this->attributes['cnpj'] = vsprintf('%02s.%03s.%03s/%04s-%02s', str_split($digits));
        } else {
            $this->attributes['cnpj'] = (string)$value;
        }
    }
}
