<?php

declare(strict_types=1);

use App\Models\AskFeedback;
use App\Models\AssistantRating;
use App\Models\Business;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** An admin who may open the quality screen, with the menu the layout reads. */
function qualityAdmin(): User
{
    test()->seed(RolesAndPermissionsSeeder::class);
    test()->seed(MenuSeeder::class);

    $admin = User::factory()->create();
    $admin->syncRoles('admin');

    return $admin;
}

test('the business with the most wrong answers is on top', function (): void {
    $bad = Business::factory()->create(['name' => 'Laboratorio Vida']);
    $good = Business::factory()->create(['name' => 'Kiosco La Esquina']);

    AssistantRating::factory()->bad()->count(3)->create(['business_id' => $bad->id]);
    AssistantRating::factory()->count(5)->create(['business_id' => $good->id]);

    $board = AssistantRating::byBusiness();

    expect($board->first()->name)->toBe('Laboratorio Vida')
        ->and((int) $board->first()->bad)->toBe(3)
        ->and((int) $board->first()->good)->toBe(0)
        ->and((int) $board->last()->good)->toBe(5);
});

test('a business nobody ever marked is absent, not shown at zero per cent', function (): void {
    Business::factory()->create(['name' => 'Nadie La Marcó']);

    // Inventing a 0% would accuse an assistant that may be answering fine.
    expect(AssistantRating::byBusiness())->toBeEmpty();
});

test('the share of her own assistant always travels with its total', function (): void {
    AskFeedback::query()->create(['business_id' => Business::factory()->create()->id, 'question' => 'q', 'answer' => 'a', 'rating' => AskFeedback::UP]);
    AskFeedback::query()->create(['business_id' => Business::factory()->create()->id, 'question' => 'q', 'answer' => 'a', 'rating' => AskFeedback::DOWN]);

    expect(AskFeedback::score())->toBe(['good' => 1, 'bad' => 1, 'total' => 2, 'share' => 50.0]);
});

test('with no marks the share is null instead of a zero that reads as a verdict', function (): void {
    expect(AskFeedback::score()['share'])->toBeNull();
});

test('a handful of marks is shown with the warning that it proves nothing', function (): void {
    $business = Business::factory()->create();
    AskFeedback::query()->create(['business_id' => $business->id, 'question' => '¿Cuántos turnos tengo?', 'answer' => 'Ninguno', 'rating' => AskFeedback::DOWN]);

    $this->actingAs(qualityAdmin())->get(route('admin.ai-quality'))
        ->assertOk()
        ->assertSee('¿Cuántos turnos tengo?')
        ->assertSee(trans_choice('ai_quality.score.warning', 1, ['total' => 1]));
});

test('with nothing marked anywhere the screen says exactly that', function (): void {
    $this->actingAs(qualityAdmin())->get(route('admin.ai-quality'))
        ->assertOk()
        ->assertSee(__('ai_quality.customer.empty'))
        ->assertSee(__('ai_quality.owner.empty'));
});

test('a client cannot reach the quality screen', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->actingAs(User::factory()->create())->get(route('admin.ai-quality'))->assertForbidden();
});
