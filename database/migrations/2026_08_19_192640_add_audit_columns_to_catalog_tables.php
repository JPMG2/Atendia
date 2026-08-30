<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit columns for the catalog masters.
 *
 * Admins edit them by hand, so who touched what matters. The columns let the
 * author show in a grid without joining the log. `softDeletes` because a
 * master is not really deleted: everything referencing the row would dangle.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $tables = [
        'currencies',
        'countries',
        'provinces',
        'regions',
        'tax_conditions',
        'social_networks',
        'current_statuses',
        'business_sectors',
        'business_activities',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                // Deleting the user leaves the master row: only the author is lost.
                $blueprint->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $blueprint->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $blueprint->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();

                if (! Schema::hasColumn($table, 'deleted_at')) {
                    $blueprint->softDeletes();
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                $blueprint->dropConstrainedForeignId('created_by');
                $blueprint->dropConstrainedForeignId('updated_by');
                $blueprint->dropConstrainedForeignId('deleted_by');

                // `regions` already carried the column before this migration.
                if ($table !== 'regions') {
                    $blueprint->dropSoftDeletes();
                }
            });
        }
    }
};
