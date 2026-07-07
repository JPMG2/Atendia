<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SocialNetworksFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'url', 'icon', 'abbreviation', 'is_active'])]
class SocialNetworks extends Model
{
    /** @use HasFactory<SocialNetworksFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
