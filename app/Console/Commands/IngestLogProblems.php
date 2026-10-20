<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Admin\IngestLogProblems as Ingest;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** Feeds the admin logs screen; see {@see Ingest}. */
#[Signature('atendia:ingest-logs')]
#[Description('Folds what the logs gained since the last run into the problems the logs screen shows')]
class IngestLogProblems extends Command
{
    public function handle(Ingest $ingest): int
    {
        $figure = $ingest->handle();

        $this->info(sprintf('Read %d entries into %d problems.', $figure->payload['entries'], $figure->payload['problems']));

        return self::SUCCESS;
    }
}
