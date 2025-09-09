<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int         $id
 * @property int|null    $id_loja
 * @property int         $status   1=Ativo, 0=Inativo
 */
class Usuario extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'usuario';

    protected $fillable = [
        'id_perfil','id_loja','nome','cpf','profissao','area_atuacao',
        'endereco','complemento','bairro','cep','id_estado','id_cidade',
        'site','email','fone','fax','cel','dt_nasc','reg_crea','reg_abd',
        'login','senha','acesso','status',
    ];

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $hidden = ['senha'];

    public function getAuthPassword()
    {
        return $this->senha;
    }

    /**
     * Relacionamento: a loja vinculada ao usuário (se houver).
     *
     * @return BelongsTo<Loja, Usuario>
     */
    public function loja(): BelongsTo
    {
        return $this->belongsTo(Loja::class, 'id_loja');
    }

    public function scopeVisiveisParaUsuario($query, Usuario $usuario)
    {
        if ($usuario->id_perfil === 2) {
            return $query->whereIn('id', function ($subquery) use ($usuario) {
                $subquery->select('id_cliente')
                    ->from('pontos')
                    ->where('id_profissional', $usuario->id)
                    ->whereNotNull('id_cliente')
                    ->where('status', 1);
            });
        }

        return $query;
    }
}
