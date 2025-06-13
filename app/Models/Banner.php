<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    protected $table = 'banners';
    protected $primaryKey = 'idBanners';
    public $timestamps = false;

    protected $fillable = [
        'titulo', 'imagem', 'link', 'descricao', 'status'
    ];
}
