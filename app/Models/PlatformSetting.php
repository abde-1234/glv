<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformSetting extends Model
{
    protected $fillable = [
        'platform_name', 'support_email', 'support_phone', 'company_address', 'logo',
        'default_language', 'default_currency', 'date_format', 'per_page',
    ];

    protected $attributes = [
        'platform_name' => 'GLV',
        'default_language' => 'fr',
        'default_currency' => 'MAD',
        'date_format' => 'd/m/Y',
        'per_page' => 10,
    ];

    protected function casts(): array
    {
        return ['per_page' => 'integer'];
    }

    public static function current(): self
    {
        $settings = static::find(1) ?? new static;
        $settings->id = 1;

        return $settings;
    }
}
