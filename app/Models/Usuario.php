<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'usuario';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $hidden = ['senha'];

    public function getAuthPassword()
    {
        return $this->senha;
    }
}
