<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * @property int         $id
 * @property string|null $razao
 * @property string      $nome
 * @property string|null $cnpj            // armazenado APENAS dígitos (14)
 * @property string|null $fone
 * @property string|null $endereco
 * @property string|null $eletronico
 * @property string|null $email
 * @property string|null $apresentacao
 * @property string|null $logomarca
 * @property int         $status
 *
 * @property-read string|null $logomarca_url
 * @property-read string|null $cnpj_mask
 */
class Loja extends Model
{
    protected $table = 'lojas';
    public $timestamps = false;

    protected $fillable = [
        'razao','nome','estadual','municipal','endereco','complemento','bairro','cep',
        'id_cidade','id_estado','fone','logomarca','eletronico','contato','email','celular',
        'contato2','email2','celular2','cnpj','apresentacao','maps','status',
    ];

    protected $appends = ['logomarca_url','cnpj_mask'];

    /** URL pública da logomarca (aceita já-URL do legado). */
    public function getLogomarcaUrlAttribute(): ?string
    {
        if (!$this->logomarca) return null;

        if (preg_match('~^https?://~i', $this->logomarca)) {
            return $this->logomarca;
        }

        $path = 'lojas/'.$this->logomarca;
        return Storage::disk('public')->url($path);
    }

    /** Escopo: apenas ativas (status=1). */
    public function scopeAtivas(Builder $q): Builder
    {
        return $q->where('status', 1)->orderBy('nome');
    }

    /**
     * Mutator de CNPJ: salva apenas dígitos (14) no banco.
     * Aceita nulo.
     */
    public function setCnpjAttribute($value): void
    {
        if ($value === null) {
            $this->attributes['cnpj'] = null;
            return;
        }
        $digits = preg_replace('/\D+/', '', (string) $value);
        $this->attributes['cnpj'] = $digits ?: null;
    }

    /**
     * Accessor de CNPJ mascarado para exibição (00.000.000/0000-00).
     * Não persiste, apenas expõe via $appends/Resource.
     */
    public function getCnpjMaskAttribute(): ?string
    {
        $cnpj = $this->attributes['cnpj'] ?? null;
        if (!$cnpj) return null;

        $digits = preg_replace('/\D+/', '', (string) $cnpj);
        if (strlen($digits) !== 14) return $cnpj;

        return vsprintf('%02s.%03s.%03s/%04s-%02s', str_split($digits));
    }
}
