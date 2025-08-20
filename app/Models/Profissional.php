<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int         $id
 * @property int|null    $id_perfil
 * @property int|null    $id_loja
 * @property string      $nome
 * @property string|null $cpf
 * @property string|null $profissao
 * @property string|null $area_atuacao
 * @property string|null $endereco
 * @property string|null $complemento
 * @property string|null $bairro
 * @property string|null $cep
 * @property int|null    $id_estado
 * @property int|null    $id_cidade
 * @property string|null $site
 * @property string|null $email
 * @property string|null $fone
 * @property string|null $fax
 * @property string|null $cel
 * @property string|null $dt_nasc
 * @property string|null $reg_crea
 * @property string|null $reg_abd
 * @property string      $login
 * @property string      $senha
 * @property int|null    $acesso
 * @property int         $status
 * @property string|null $dtCriacao
 */
class Profissional extends Model
{
    protected $table = 'usuario';
    public $timestamps = false; // tabela usa dtCriacao

    protected $fillable = [
        'id_perfil','id_loja','nome','cpf','profissao','area_atuacao',
        'endereco','complemento','bairro','cep','id_estado','id_cidade',
        'site','email','fone','fax','cel','dt_nasc','reg_crea','reg_abd',
        'login','senha','acesso','status',
    ];

    /** Escopo: ignora excluídos (status=2) por padrão. */
    public function scopeAtivos(Builder $q): Builder
    {
        return $q->where('status', '!=', 2);
    }

    /** Escopo: perfil específico (ex.: 2=Profissional). */
    public function scopePerfil(Builder $q, ?int $idPerfil): Builder
    {
        if ($idPerfil) $q->where('id_perfil', $idPerfil);
        return $q;
    }

    /** Escopo: busca textual por nome/login/email/cpf. */
    public function scopeBusca(Builder $q, ?string $qstr): Builder
    {
        if (!$qstr) return $q;
        $like = '%'.$qstr.'%';
        return $q->where(function (Builder $w) use ($like) {
            $w->where('nome', 'like', $like)
                ->orWhere('login', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('cpf', 'like', $like);
        });
    }
}
