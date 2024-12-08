<?php

namespace App\Models\Setting;

use App\Concerns\Blamable;
use App\Concerns\CompanyOwned;
use App\Enums\Setting\Font;
use App\Enums\Setting\PrimaryColor;
use Database\Factories\Setting\AppearanceFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appearance extends Model
{
    use Blamable;
    use CompanyOwned;
    use HasFactory;

    protected $table = 'appearances';

    protected $fillable = [
        'company_id',
        'primary_color',
        'font',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'primary_color' => PrimaryColor::class,
        'font' => Font::class,
    ];

    protected static function newFactory(): Factory
    {
        return AppearanceFactory::new();
    }
}
