<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'logo_path',
        'primary_color',
        'currency',
        'timezone',
        'email',
        'phone',
        'address',
        'website_headline',
        'website_about',
    ];

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? asset('uploads/'.$this->logo_path) : null;
    }
}
