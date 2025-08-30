<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $email
 * @property string $token
 * @property string $expira_em
 * @property bool $utilizado
 * @property string $created_at
 */
class RecuperacaoSenha extends Model
{
    protected $table = 'recuperacao_senha';
    public $timestamps = false;

    protected $fillable = [
        'email', 'token', 'expira_em', 'utilizado',
    ];

    protected $casts = [
        'utilizado' => 'bool',
        'expira_em' => 'datetime',
    ];
}
