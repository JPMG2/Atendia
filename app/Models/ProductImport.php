<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToBusiness;
use Database\Factories\ProductImportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A spreadsheet a business handed over, with its confirmed column mapping.
 *
 * `business_id` is deliberately NOT fillable, same boundary as Product: a row
 * is created THROUGH its owner, so an id arriving from a request can never
 * queue an import into another tenant.
 */
#[Fillable(['original_name', 'path', 'mapping', 'corrections', 'total_rows', 'status'])]
class ProductImport extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<ProductImportFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mapping' => 'array',
            'corrections' => 'array',
            'total_rows' => 'integer',
        ];
    }
}
