<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * A creation or a send that arrives twice is done once. On a slow network the
 * answer can be lost after the row was saved, and the phone retries the very
 * same request. A repeat is only a repeat when it is exactly this person's last
 * write: delete or change anything in between and the same body is a new act.
 * Every write is remembered (`remember`); only the routes marked `guard` replay.
 */
class AnswerRepeatOnce
{
    public const int WINDOW_MINUTES = 10;

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $mode = 'remember'): Response
    {
        $user = $request->user();

        if ($user === null || $request->isMethodSafe()) {
            return $next($request);
        }

        $key = 'repeat-once:'.$user->getAuthIdentifier();
        $print = sha1($request->method().' '.$request->path().' '.json_encode($request->all()));

        if ($mode !== 'guard') {
            return $this->remembered($key, $print, $next($request));
        }

        // One person's writes queue on one lock, held under php-fpm's 60 s: a repeat waits for the first answer.
        return Cache::lock($key.':lock', 60)->block(50, function () use ($key, $print, $next, $request): Response {
            $last = Cache::get($key);

            if (is_array($last) && $last['print'] === $print) {
                return response($last['body'], $last['status'], ['Content-Type' => 'application/json']);
            }

            return $this->remembered($key, $print, $next($request));
        });
    }

    /** Only what was done counts as the last write: a refusal or a crash stays free to try again. */
    private function remembered(string $key, string $print, Response $response): Response
    {
        if ($response->isSuccessful()) {
            Cache::put($key, ['print' => $print, 'status' => $response->getStatusCode(), 'body' => $response->getContent()], now()->addMinutes(self::WINDOW_MINUTES));
        }

        return $response;
    }
}
