<?php

declare(strict_types=1);

namespace App\Messaging;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Throwable;

/**
 * A medium a system message goes out through: mail today, WhatsApp tomorrow.
 *
 * The class writes the whole ritual and leaves one hole, `deliver()`, for what
 * a medium does its own way. That abstract has to stay single: a new medium is
 * a SUBCLASS. A second one would force Email to implement a delivery it cannot
 * do, and an empty delivery drops the message in silence.
 */
abstract class Channel
{
    /**
     * What THIS channel demands of the message.
     *
     * `null` means the class only has to exist. A channel overrides it with its
     * own type without the contract growing: it is a constant, not one more
     * abstract method.
     */
    protected const MESSAGE_CONTRACT = null;

    /**
     * @param  Model  $model  The record the message talks about.
     * @param  array<int, string>  $receives  Who it goes to. The channel decides, not the message.
     * @param  class-string  $message  The message class to build (a Mailable, for mail).
     *
     * @throws InvalidArgumentException when `$message` is not usable by this channel.
     */
    public function __construct(
        protected Model $model,
        protected array $receives,
        protected string $message
    ) {
        $this->guardMessage($message);
    }

    /**
     * Sends the message through this channel. It is what the rest of the app
     * calls, and it is the same for every channel — what changes is `deliver()`.
     */
    public function send(): void
    {
        // Captured here, in the request: a queued message is built in the
        // worker, where there is no session, and would go out in the fallback
        // locale instead of the one the person picked.
        $locale = app()->getLocale();

        try {
            $this->deliver($locale);
        } catch (Throwable $e) {
            // A dead channel cannot undo the operation that fired it: the
            // company was saved all the same.
            report($e);
        }
    }

    /**
     * Refuses a `$message` this channel cannot use.
     *
     * It is the one constructor argument PHP cannot vouch for: a string naming
     * a usable class is a promise nobody checks. It throws while CONSTRUCTING,
     * not while sending — a misdeclared message is a programming error, and the
     * catch in `send()` is there for a dead service, not for a typo.
     */
    private function guardMessage(string $message): void
    {
        if (! class_exists($message)) {
            throw new InvalidArgumentException(
                sprintf('[%s] no message class named "%s".', static::class, $message)
            );
        }

        $contract = static::MESSAGE_CONTRACT;

        if ($contract !== null && ! is_a($message, $contract, true)) {
            throw new InvalidArgumentException(
                sprintf('[%s] the message "%s" has to be a %s.', static::class, $message, $contract)
            );
        }
    }

    /** The only thing each channel does its own way. */
    abstract protected function deliver(string $locale): void;
}
