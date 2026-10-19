<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Admin\PurgeBusiness;
use App\Models\Business;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Deliberately a console command and not a button: erasing cannot be undone,
 * and the admin screen only ever does the reversible removal.
 */
#[Signature('atendia:purge-business {id : The id of a business that was already removed}')]
#[Description('Erases a removed test business, its people and its e-mail for good')]
class PurgeBusinessFromConsole extends Command
{
    public function handle(PurgeBusiness $purge): int
    {
        $business = Business::withTrashed()->find((int) $this->argument('id'));

        if ($business === null) {
            $this->error('No business has that id.');

            return self::FAILURE;
        }

        $typed = $this->ask("This erases \"{$business->name}\" for good. Type its name to confirm");

        if ($typed !== $business->name) {
            $this->error('The name does not match; nothing was erased.');

            return self::FAILURE;
        }

        try {
            $erased = $purge->handle($business);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        foreach ($erased as $table => $count) {
            $this->line("{$table}: {$count}");
        }

        $this->info('Erased. Its e-mail is free to sign up again.');

        return self::SUCCESS;
    }
}
