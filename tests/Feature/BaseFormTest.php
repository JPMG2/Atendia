<?php

declare(strict_types=1);

use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\BaseForm;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Bare component used only to satisfy the Livewire Form constructor
 * (Form::__construct(Component $component, $propertyName)).
 */
class BaseFormHostComponent extends Component
{
    public function render(): string
    {
        return '<div></div>';
    }
}

/**
 * Concrete BaseForm used to exercise the abstract base in isolation. It exposes
 * the protected template methods so the test can drive them directly, and lets
 * each test tweak the payload and rules.
 */
class HarnessForm extends BaseForm
{
    /** @var array<string, mixed> */
    public array $payload = ['name' => 'Ada'];

    protected function transformServiceData(): array
    {
        return $this->payload;
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        // The excludeId flips the rule so a test can prove it is threaded through
        // validateServiceData() into the rules — the whole point of the signature.
        return $excludeId === 99
            ? ['name' => 'required|integer']
            : ['name' => 'required|string'];
    }

    /** @return array<string, mixed> */
    public function runValidate(?int $excludeId = null): array
    {
        return $this->validateServiceData($excludeId);
    }

    /** @param callable():NotificationDto $callback */
    public function runTry(callable $callback, string $errorPrefix): NotificationDto
    {
        return $this->tryAction($callback, $errorPrefix);
    }

    public function resolveNotifier(): NotificationService
    {
        return $this->notificationService();
    }
}

function makeHarnessForm(): HarnessForm
{
    return new HarnessForm(new BaseFormHostComponent, 'form');
}

test('validateServiceData returns the transformed payload when it satisfies the rules', function (): void {
    $form = makeHarnessForm();

    expect($form->runValidate())->toBe(['name' => 'Ada']);
});

test('validateServiceData throws a ValidationException when the payload breaks a rule', function (): void {
    $form = makeHarnessForm();
    $form->payload = ['name' => ''];

    expect(fn () => $form->runValidate())->toThrow(ValidationException::class);
});

test('validateServiceData threads the excludeId into getValidationRules', function (): void {
    $form = makeHarnessForm();
    // 'Ada' passes required|string, but excludeId 99 swaps the rule to required|integer.
    $form->payload = ['name' => 'Ada'];

    expect($form->runValidate())->toBe(['name' => 'Ada']);
    expect(fn () => $form->runValidate(99))->toThrow(ValidationException::class);
});

test('tryAction returns the callback result when it succeeds', function (): void {
    $form = makeHarnessForm();

    $result = $form->runTry(
        fn (): NotificationDto => new NotificationDto('Todo bien', NotificationType::Success),
        'No se pudo: ',
    );

    expect($result)->toEqual(new NotificationDto('Todo bien', NotificationType::Success));
});

test('tryAction converts a thrown exception into an error notification', function (): void {
    $form = makeHarnessForm();

    $result = $form->runTry(function (): NotificationDto {
        throw new RuntimeException('boom');
    }, 'No se pudo guardar');

    expect($result)->toEqual(new NotificationDto('No se pudo guardar', NotificationType::Error));
});

test('tryAction never leaks the exception message into the toast', function (): void {
    // The message used to be appended, so a constraint violation reached the user as
    // "Registro no creado: SQLSTATE[23505] duplicate key value violates unique
    // constraint...", leaking the schema and meaning nothing to them.
    $form = makeHarnessForm();

    $result = $form->runTry(function (): NotificationDto {
        throw new RuntimeException('SQLSTATE[23505] duplicate key value violates unique constraint "currencies_code_unique"');
    }, 'Registro no creado');

    expect($result->message)->toBe('Registro no creado')
        ->and($result->message)->not->toContain('SQLSTATE')
        ->and($result->message)->not->toContain('currencies_code_unique');
});

test('tryAction reports the exception so the detail is not lost', function (): void {
    // Hiding it from the user must not hide it from us.
    Exceptions::fake();

    makeHarnessForm()->runTry(function (): NotificationDto {
        throw new RuntimeException('boom');
    }, 'Registro no creado');

    Exceptions::assertReported(
        fn (RuntimeException $e): bool => $e->getMessage() === 'boom',
    );
});

test('notificationService resolves and memoizes a NotificationService', function (): void {
    $form = makeHarnessForm();

    $first = $form->resolveNotifier();

    expect($first)->toBeInstanceOf(NotificationService::class)
        ->and($form->resolveNotifier())->toBe($first);
});
