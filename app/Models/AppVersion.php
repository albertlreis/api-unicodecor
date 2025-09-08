<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $platform
 * @property string $latest
 * @property string $min_supported
 * @property string|null $store_url
 * @property string|null $message
 */
class AppVersion extends Model
{
    protected $fillable = [
        'platform',
        'latest',
        'min_supported',
        'store_url',
        'message',
    ];
}
