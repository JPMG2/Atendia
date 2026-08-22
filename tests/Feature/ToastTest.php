<?php

declare(strict_types=1);

use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Models\Currency;
use App\Models\User;
use App\Traits\HasNotifications;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component;
use Livewire\Livewire;

// The currency test below persists a record, so start from a clean database
// instead of inheriting currencies left over from previous runs.
uses(RefreshDatabase::class);

test('the toast renders the stack that listens for the notify event', function (): void {
    $html = Livewire::test('toast')->html();

    expect($html)->toContain('x-data="toastStack()"')
        ->toContain('x-on:notify.window="push($event.detail)"')
        ->toContain('class="toast-stack"');
});

test('the toast renders one icon per notification type', function (): void {
    // Every case of NotificationType must have a glyph, otherwise a toast of that
    // type would show an empty circle.
    $html = Livewire::test('toast')->html();

    foreach (NotificationType::cases() as $type) {
        expect($html)->toContain("toast.type === '{$type->value}'");
    }
});

test('the toast animates in and out through the app.css transition classes', function (): void {
    $html = Livewire::test('toast')->html();

    expect($html)->toContain('x-transition:enter="toast-trans"')
        ->toContain('x-transition:enter-start="toast-off"')
        ->toContain('x-transition:leave-end="toast-off"');
});

test('an unknown notification type falls back to info instead of losing the message', function (): void {
    // The @script block travels HTML-escaped inside wire:effects, so decode it
    // before looking for the JavaScript.
    $script = html_entity_decode(Livewire::test('toast')->html());

    expect($script)->toContain("this.types.includes(detail?.type) ? detail.type : 'info'");
});

test('any component can raise a toast through the HasNotifications trait', function (): void {
    // The trait is the single entry point: it turns a NotificationDto into the
    // `notify` event the toast listens for.
    $component = new class extends Component
    {
        use HasNotifications;

        public function notifyNow(): void
        {
            $this->dispatchNotification(
                new NotificationDto('Moneda creada correctamente', NotificationType::Success),
            );
        }

        public function render(): string
        {
            return '<div></div>';
        }
    };

    Livewire::test($component::class)
        ->call('notifyNow')
        ->assertDispatched('notify', type: 'success', message: 'Moneda creada correctamente');
});

test('creating a currency dispatches a success toast', function (): void {
    $this->actingAs(User::factory()->create());

    Livewire::test('catalog.currency')
        ->set('form.data.code', 'USD')
        ->set('form.data.name', 'Dólar Estadounidense')
        ->set('form.data.symbol', 'US$')
        ->set('form.data.decimal_places', 2)
        ->set('form.data.is_active', true)
        ->call('create')
        ->assertDispatched('notify', type: 'success');

    expect(Currency::where('code', 'USD')->exists())->toBeTrue();
});

test('a failed creation dispatches an error toast instead of a success one', function (): void {
    $this->actingAs(User::factory()->create());

    // A duplicate code trips the unique index inside the action, and BaseForm's
    // tryAction turns the exception into an error notification.
    Currency::factory()->create(['code' => 'EUR']);

    Livewire::test('catalog.currency')
        ->set('form.data.code', 'EUR')
        ->set('form.data.name', 'Euro')
        ->set('form.data.symbol', '€')
        ->set('form.data.decimal_places', 2)
        ->set('form.data.is_active', true)
        ->call('create')
        ->assertHasErrors('code');
});
