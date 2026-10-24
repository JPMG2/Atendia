<?php

declare(strict_types=1);

namespace App\Messaging\Channels;

use App\Messaging\Channel;
use App\Messaging\PushMessage;
use App\Models\Business;
use App\Models\LoginDevice;
use App\Services\ExpoPush;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

class Push extends Channel
{
    /** Only a PushMessage goes out this way: anything else is refused on construction. */
    protected const MESSAGE_CONTRACT = PushMessage::class;

    /**
     * `$receives` stays empty, like the panel's: the team is the address.
     * A business the app refuses (403) gets nothing. A person is left alone
     * for a window after a push, so a burst buzzes once; it is given back if
     * Expo fails, because a push that never left must not silence the next.
     */
    protected function deliver(string $locale): void
    {
        $business = $this->business();

        if (! $business->appAccess()->isAllowed()) {
            return;
        }

        $message = new $this->message($this->model, ...$this->messageArguments);
        $window = (int) config('atendia.app.push.window_seconds');
        $claimed = [];

        $outgoing = LoginDevice::pushTargetsFor((int) $business->id)
            ->filter(fn (LoginDevice $device): bool => $message->wants($device->user))
            ->groupBy('user_id')
            ->filter(function ($devices, $userId) use ($window, &$claimed): bool {
                $taken = Cache::add("push:window:{$userId}", true, $window);

                if ($taken) {
                    $claimed[] = $userId;
                }

                return $taken;
            })
            ->flatten()
            ->map(fn (LoginDevice $device): array => array_filter([
                'to' => $device->push_token,
                'title' => $message->title(),
                'body' => $message->body(),
                'sound' => 'default',
                'channelId' => 'default',
                'badge' => $message->badgeFor($device->user),
                'data' => $message->data() ?: null,
            ], fn (mixed $value): bool => $value !== null))
            ->values()
            ->all();

        try {
            foreach (array_chunk($outgoing, ExpoPush::BATCH) as $batch) {
                $dead = app(ExpoPush::class)->send($batch);

                // A token Expo refuses is a phone that uninstalled the app: stop writing to it.
                if ($dead !== []) {
                    LoginDevice::query()->whereIn('push_token', $dead)->update(['push_token' => null]);
                }
            }
        } catch (Throwable $e) {
            foreach ($claimed as $userId) {
                Cache::forget("push:window:{$userId}");
            }

            throw $e;
        }
    }

    private function business(): Business
    {
        $business = $this->model instanceof Business ? $this->model : $this->model->getAttribute('business');

        if (! $business instanceof Business) {
            throw new RuntimeException(sprintf('[%s] %s has no business to notify.', static::class, $this->model::class));
        }

        return $business;
    }
}
