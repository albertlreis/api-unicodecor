<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

/**
 * @property int $id
 * @property string $nome
 * @property string|null $razao
 * @property string|null $email
 * @property string|null $cnpj
 * @property int $status
 * @property string|null $dt_cadastro
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
        'status'
    ];

    // -- Accessors

    public function getDtCadastroFormatadoAttribute(): ?string
    {
        return $this->dt_cadastro ? Carbon::parse($this->dt_cadastro)->format('d/m/Y') : null;
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
}
