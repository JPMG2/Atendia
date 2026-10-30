<?php

declare(strict_types=1);

use Tests\Support\QueryScanner;

require_once __DIR__.'/../Support/QueryScanner.php';

/*
|--------------------------------------------------------------------------
| Golden-rule guardian — queries live in the model
|--------------------------------------------------------------------------
| A Blade view, a Livewire Form or a controller never builds a database
| query: it asks the model by domain vocabulary (options(), forEditing(),
| editable()...). Same scanner as the hook check-blade-query-golden-rules.sh.
|
| Recipe: .ai/guidelines/reglas-de-oro-enforcement.md
*/

test('no blade, form or controller builds a query — data comes through model vocabulary', function (): void {
    $offenders = collect(QueryScanner::files())
        ->map(fn (string $source, string $relative): array => QueryScanner::offensesIn($relative, $source))
        ->filter(fn (array $offenses): bool => $offenses !== [])
        ->map(fn (array $offenses, string $relative): string => $relative.' → '.implode(' · ', $offenses))
        ->values();

    expect($offenders->implode("\n"))->toBe('');
});

test('the scanner catches a query in a form and ignores one named in a comment', function (): void {
    $form = <<<'PHP'
        <?php
        use App\Models\Company;
        // Not `Company::query()->first()`: the model knows the row.
        $company = Company::forEditing();
        PHP;

    $uses = "<?php\nuse App\\Models\\Company;\nuse App\\Models\\Customer;\n";

    expect(QueryScanner::offensesIn('app/Livewire/Forms/Demo.php', $form))->toBe([])
        ->and(QueryScanner::offensesIn('app/Livewire/Forms/Demo.php', $uses.'$c = Customer::find(1);'))->not->toBe([])
        ->and(QueryScanner::offensesIn('app/Http/Controllers/Demo.php', $uses.'$c = Company::query()->first();'))->not->toBe([])
        ->and(QueryScanner::offensesIn('app/Http/Controllers/Demo.php', $uses.'$c = SupportCase::find(1);'))->toBe([])
        ->and(QueryScanner::offensesIn('app/Actions/Demo.php', $uses.'$c = Customer::find(1);'))->toBe([]);
});

test('the scanner sees every way a form reaches the builder, and nothing that is not one', function (string $source, bool $isQuery): void {
    $uses = "<?php\nuse App\\Models\\Company;\nuse App\\Models\\Customer;\nuse App\\Models\\Business;\nuse App\\Models\\Testimonial;\nuse Spatie\\Permission\\Models\\Role;\n";

    expect(QueryScanner::offensesIn('/var/www/html/app/Livewire/Forms/Demo.php', $uses.$source) !== [])->toBe($isQuery);
})->with([
    'with on a model' => ["\$c = Company::with('socialLinks')->first();", true],
    'count on a model' => ['$n = Customer::count();', true],
    'firstOrCreate on a model' => ["\$r = Role::findOrCreate('x');", true],
    'an aliased model' => ["use App\\Models\\Customer as Client;\n\$c = Client::find(1);", true],
    'a grouped alias' => ["use App\\Models\\{Customer as Client};\n\$c = Client::find(1);", true],
    'a fully qualified model' => ['$c = \\App\\Models\\Customer::find(1);', true],
    'the DB facade under an alias' => ["use Illuminate\\Support\\Facades\\DB as Database;\nDatabase::table('x');", true],
    'a class in a variable' => ['$r = $model::find(1);', true],
    'a relation chain' => ["\$s = \$business->services()->where('is_bookable', true)->find(1);", true],
    'a relation chain over lines' => ["\$s = \$business->services()\n    ->where('a', 1)\n    ->get();", true],
    'a model method that hands back the builder' => ["\$b = Business::demoIds()->where('x', 1)->get();", true],
    'a method the model declares' => ['$c = Company::forEditing();', false],
    'saving inside tryAction' => ["Testimonial::create(['quote' => 'x']);", false],
    'a message naming a call' => ["\$m = 'use Customer::find(1) via the model';", false],
    'the session' => ["\$l = \$request->session()->get('locale');", false],
    'a collection chain' => ['$t = $rows->filter()->take(3);', false],
    'an enum named like a model' => ["use App\\Enums\\Currency;\n\$c = Currency::from('usd');", false],
]);
