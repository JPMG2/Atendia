<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Finds the queries a Blade view, a Livewire Form, a form request or a
 * controller builds instead of asking the MODEL. A static call on a model is
 * vocabulary only when the model itself declares the method. One scanner for
 * the guardian and the hook, so the two can never drift.
 */
final class QueryScanner
{
    /** Pre-existing debt, frozen with its reason. Never add an entry: fix the file. */
    public const array ALLOWED = [
        'resources/views/components/⚡ws-demo.blade.php', // dev-only demo, dies before go-live
    ];

    /** @var list<string> */
    private const array PHP_ROOTS = ['app/Livewire/Forms', 'app/Http/Controllers', 'app/Http/Requests'];

    /**
     * Entry points of a query, wherever they are called from. The verbs a
     * Collection shares with the builder (->where, ->pluck) are left out: in
     * a Blade, filtering a list already loaded is presentation.
     *
     * @var list<string>
     */
    private const array ENTRY_POINTS = [
        '/::query\s*\(/',
        '/\bDB::/',
        '/->orderBy\s*\(/',
    ];

    /** In a Blade, on any class: a domain method there (`findValid`) must not trip it. */
    private const string BLADE_VERBS = '(?:where\w*|find|findOrFail|all|first|firstWhere|pluck|orderBy|latest|oldest)\s*\(';

    /** Static query verbs on a class held in a variable, which reflection cannot read. */
    private const string STATIC_VERBS = '(?:where\w*|find\w*|first\w*|all|with|count|exists|pluck|value|select\w*|orderBy\w*|latest|oldest|updateOrCreate|upsert|insert\w*|delete|update|destroy)\s*\(';

    /** Builder verbs after a relation (`->services()->where(`). */
    private const string RELATION_VERBS = '(?:newQuery|with\w*|where\w*|find\w*|first\w*|count|exists|doesntExist|pluck|value|select\w*|orderBy\w*|latest|oldest|limit|take|paginate|cursor\w*|chunk\w*|sum|avg|max|min|updateOrCreate|upsert|insert\w*|increment|decrement|get|delete|update|has|whereHas|distinct)\s*\(';

    /** Statics every model inherits that write, never read. */
    private const array WRITES = ['create', 'forceCreate', 'factory', 'make', 'newModelInstance'];

    public static function judges(string $relative): bool
    {
        $relative = self::relative($relative);

        if (str_starts_with($relative, 'resources/views/')) {
            return str_ends_with($relative, '.blade.php');
        }

        return str_ends_with($relative, '.php')
            && collect(self::PHP_ROOTS)->contains(fn (string $root): bool => str_starts_with($relative, $root.'/'));
    }

    /**
     * Every file the rule reaches, keyed by its path from the project root.
     *
     * @return array<string, string>
     */
    public static function files(): array
    {
        $base = dirname(__DIR__, 2);

        return collect(['resources/views', ...self::PHP_ROOTS])
            ->flatMap(fn (string $root): array => iterator_to_array(new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($base.'/'.$root, \FilesystemIterator::SKIP_DOTS),
            ), false))
            ->mapWithKeys(fn (\SplFileInfo $file): array => [
                str_replace('\\', '/', ltrim(substr($file->getPathname(), strlen($base)), '/\\')) => (string) file_get_contents($file->getPathname()),
            ])
            ->filter(fn (string $source, string $relative): bool => self::judges($relative))
            ->all();
    }

    /**
     * The patterns the file breaks; empty when it asks the model.
     *
     * @return list<string>
     */
    public static function offensesIn(string $relative, string $source): array
    {
        $relative = self::relative($relative);

        if (! self::judges($relative) || in_array($relative, self::ALLOWED, true)) {
            return [];
        }

        $blade = str_ends_with($relative, '.blade.php');

        // Pint puts one call per line: a chain is read as if it were on one.
        $code = (string) preg_replace('/\)\s+->/', ')->', $blade ? $source : self::withoutCommentsOrStrings($source));
        $relations = implode('|', self::relationNames());

        $offenses = self::matching([
            ...self::ENTRY_POINTS,
            ...($blade ? ['/::'.self::BLADE_VERBS.'/'] : []),
            'use DB facade' => '/^use\s+Illuminate\\\\Support\\\\Facades\\\\DB\b/m',
            '$class::where/find…(' => '/\$\w+::'.self::STATIC_VERBS.'/',
            '->relation()->where/find…(' => '/->(?:'.$relations.')\(\)->'.self::RELATION_VERBS.'/',
        ], $code);

        foreach (self::modelCalls($code) as $call) {
            $offenses[] = $call;
        }

        return array_values(array_unique($offenses));
    }

    /**
     * @param  array<int|string, string>  $patterns  Keyed by a readable label when the regex is not one.
     * @return list<string>
     */
    private static function matching(array $patterns, string $code): array
    {
        return collect($patterns)
            ->filter(fn (string $pattern): bool => preg_match($pattern, $code) === 1)
            ->map(fn (string $pattern, int|string $label): string => is_string($label) ? $label : $pattern)
            ->values()
            ->all();
    }

    /**
     * Static calls on a model that the model does not declare: `with`,
     * `count`, `firstOrCreate`… all reach the query builder.
     *
     * @return list<string>
     */
    private static function modelCalls(string $code): array
    {
        $models = self::modelsIn($code);

        if (preg_match_all('/(?<![\w$])(\\\\?[\w\\\\]+)::(\w+)\s*\(/', $code, $calls, PREG_SET_ORDER) === 0) {
            return [];
        }

        return collect($calls)
            ->map(fn (array $call): array => [$models[$call[1]] ?? ltrim($call[1], '\\'), $call[1], $call[2]])
            ->filter(fn (array $call): bool => self::isModel($call[0]) && ! in_array($call[2], self::WRITES, true)
                && ! self::declaredByTheApp($call[0], $call[2]))
            ->map(fn (array $call): string => "{$call[1]}::{$call[2]}(")
            ->values()
            ->all();
    }

    /**
     * The methods a model declares as returning an Eloquent relation: only
     * those start a query when chained (`->filter()->take()` is a Collection).
     *
     * @return list<string>
     */
    private static function relationNames(): array
    {
        static $names = null;

        return $names ??= collect(glob(dirname(__DIR__, 2).'/app/Models/*.php') ?: [])
            ->map(fn (string $path): string => 'App\\Models\\'.basename($path, '.php'))
            ->filter(fn (string $class): bool => class_exists($class))
            ->flatMap(fn (string $class): array => (new \ReflectionClass($class))->getMethods(\ReflectionMethod::IS_PUBLIC))
            ->filter(fn (\ReflectionMethod $method): bool => $method->getNumberOfRequiredParameters() === 0
                && str_contains((string) $method->getReturnType(), 'Eloquent\\Relations'))
            ->map(fn (\ReflectionMethod $method): string => $method->getName())
            ->unique()
            ->values()
            ->all();
    }

    private static function isModel(string $class): bool
    {
        return in_array($class, [Role::class, Permission::class], true)
            || (str_starts_with($class, 'App\\Models\\') && is_file(dirname(__DIR__, 2).'/app/Models/'.substr($class, 11).'.php'));
    }

    /** Vocabulary is what the model declares, unless it hands the builder back for the caller to finish. */
    private static function declaredByTheApp(string $class, string $method): bool
    {
        if (! method_exists($class, $method)) {
            return false;
        }

        $reflection = new \ReflectionMethod($class, $method);
        $returns = (string) $reflection->getReturnType();

        return str_starts_with($reflection->getDeclaringClass()->getName(), 'App\\')
            && ! str_contains($returns, 'Builder') && ! str_contains($returns, 'Relation');
    }

    /**
     * The short names this file imports, mapped to their class: a plain `use`,
     * an alias, or a group (`use App\Models\{Customer as Client}`).
     *
     * @return array<string, string>
     */
    private static function modelsIn(string $code): array
    {
        $names = [];

        preg_match_all('/^use\s+([\w\\\\]+)(?:\s+as\s+(\w+))?\s*;/m', $code, $plain, PREG_SET_ORDER);

        foreach ($plain as $import) {
            $names[$import[2] ?? '' ?: substr((string) strrchr('\\'.$import[1], '\\'), 1)] = $import[1];
        }

        preg_match_all('/^use\s+([\w\\\\]+)\\\\\{([^}]+)\}\s*;/m', $code, $groups, PREG_SET_ORDER);

        foreach ($groups as [, $prefix, $members]) {
            foreach (array_map('trim', explode(',', $members)) as $member) {
                [$class, $alias] = array_pad(preg_split('/\s+as\s+/', $member) ?: [], 2, null);
                $names[$alias ?? substr((string) strrchr('\\'.$class, '\\'), 1)] = $prefix.'\\'.$class;
            }
        }

        return $names;
    }

    /**
     * A comment or a message may name the call it avoids ("not by
     * `SocialLink::find()`"): neither is code. A string leaves `''` behind,
     * so `->route('login')` never reads as a zero-argument relation.
     */
    private static function withoutCommentsOrStrings(string $source): string
    {
        return collect(token_get_all($source))
            ->map(fn (array|string $token): string => match (is_array($token) ? $token[0] : null) {
                null => $token,
                T_COMMENT, T_DOC_COMMENT, T_ENCAPSED_AND_WHITESPACE => '',
                T_CONSTANT_ENCAPSED_STRING => "''",
                default => $token[1],
            })
            ->implode('');
    }

    /** The hook may hand over an absolute path from the host or from inside the container. */
    private static function relative(string $path): string
    {
        return (string) preg_replace('#^(?:/var/www/atendia|/var/www/html|'.preg_quote(dirname(__DIR__, 2), '#').')/#', '', $path);
    }
}
