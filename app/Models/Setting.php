<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function defaults(): array
    {
        return ['timezone' => 'Africa/Casablanca', 'email_verification' => '0', 'session_minutes' => '120'];
    }

    public static function values(): array
    {
        return array_replace(static::defaults(), static::query()->pluck('value', 'key')->all());
    }
}
