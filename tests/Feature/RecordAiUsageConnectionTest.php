<?php

declare(strict_types=1);

use App\Listeners\RecordAiUsage;
use App\Models\AiUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Laravel\Ai\Ai;
use Laravel\Ai\Events\TranscriptionGenerated;
use Laravel\Ai\Prompts\TranscriptionPrompt;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\TranscriptionUsage;
use Laravel\Ai\Responses\TranscriptionResponse;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The meter says which key a call went through
|--------------------------------------------------------------------------
| With two OpenAI keys the model name alone cannot tell where a call was
| charged. The row takes the NAME of the provider that answered, which on a
| failover is the second key and not the one the call was meant for.
*/

test('a metered call keeps the name of the key it went through', function (): void {
    config(['ai.providers.openai-app.key' => 'test-key']);

    $event = new TranscriptionGenerated(
        'invocation-1',
        Ai::transcriptionProvider('openai-app'),
        'gpt-4o-transcribe-diarize',
        Mockery::mock(TranscriptionPrompt::class),
        new TranscriptionResponse('hola', new Collection, new TranscriptionUsage(12, 3), new Meta),
    );

    (new RecordAiUsage)->handle($event);

    expect(AiUsage::query()->sole())
        ->connection_key->toBe('openai-app')
        ->model->toBe('gpt-4o-transcribe-diarize');
});
