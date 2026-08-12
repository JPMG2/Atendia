<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CatalogFormFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CatalogForm extends Model
{
    /** @use HasFactory<CatalogFormFactory> */
    use HasFactory;

    protected $fillable = [
        'group',
        'title',
        'description',
        'component',
        'icon',
        'order',
        'permission_key',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
