<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Admin\SendPlatformDigest;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('atendia:platform-digest')]
#[Description('Sends the admin the evening summary of what is still unseen, if there is any')]
class PlatformDigestCommand extends Command
{
    public function handle(SendPlatformDigest $digest): int
    {
        $this->info($digest->handle() ? 'Digest sent.' : 'Nothing to say, nothing sent.');

        return self::SUCCESS;
    }
}
