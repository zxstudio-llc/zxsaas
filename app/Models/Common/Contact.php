<?php

namespace App\Models\Common;

use App\Concerns\Blamable;
use App\Concerns\CompanyOwned;
use App\Enums\Common\ContactType;
use Database\Factories\Common\ContactFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Contact extends Model
{
    use Blamable;
    use CompanyOwned;
    use HasFactory;

    protected $table = 'contacts';

    protected $fillable = [
        'company_id',
        'type',
        'first_name',
        'last_name',
        'email',
        'phones',
        'is_primary',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'type' => ContactType::class,
        'phones' => 'array',
    ];

    public function contactable(): MorphTo
    {
        return $this->morphTo();
    }

    protected static function newFactory(): Factory
    {
        return ContactFactory::new();
    }
}
