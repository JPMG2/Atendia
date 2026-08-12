<?php

declare(strict_types=1);

use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Services\NotificationService;
use Illuminate\Database\Eloquent\Model;

/**
 * Lightweight in-memory models: NotificationService only reads getTable(),
 * wasRecentlyCreated and wasChanged(), so no database is needed.
 */
class MasculineFake extends Model
{
    protected $table = 'countries';
}

class FeminineFake extends Model
{
    protected $table = 'provinces';
}

/** Build a model that reports itself as changed, without touching the database. */
function changedModel(string $class): Model
{
    $model = new $class;
    $model->name = 'nuevo';
    $model->syncChanges();

    return $model;
}

// --- created ----------------------------------------------------------------

test('created announces a masculine record as creado', function (): void {
    $model = new MasculineFake;
    $model->wasRecentlyCreated = true;

    expect((new NotificationService)->created($model))
        ->toEqual(new NotificationDto('País creado correctamente', NotificationType::Success));
});

test('created announces a feminine record as creada', function (): void {
    $model = new FeminineFake;
    $model->wasRecentlyCreated = true;

    expect((new NotificationService)->created($model))
        ->toEqual(new NotificationDto('Provincia creada correctamente', NotificationType::Success));
});

test('created reports an error when the model was not recently created', function (): void {
    $model = new MasculineFake;
    $model->wasRecentlyCreated = false;

    expect((new NotificationService)->created($model))
        ->toEqual(new NotificationDto('Registro no creado', NotificationType::Error));
});

// --- updated ----------------------------------------------------------------

test('updated announces a masculine record as actualizado when it changed', function (): void {
    expect((new NotificationService)->updated(changedModel(MasculineFake::class)))
        ->toEqual(new NotificationDto('País actualizado correctamente', NotificationType::Success));
});

test('updated announces a feminine record as actualizada when it changed', function (): void {
    expect((new NotificationService)->updated(changedModel(FeminineFake::class)))
        ->toEqual(new NotificationDto('Provincia actualizada correctamente', NotificationType::Success));
});

test('updated reports info when nothing changed', function (): void {
    expect((new NotificationService)->updated(new MasculineFake))
        ->toEqual(new NotificationDto('No se realizaron cambios en el registro.', NotificationType::Info));
});

// --- deleted ----------------------------------------------------------------

test('deleted announces a masculine record as eliminado', function (): void {
    expect((new NotificationService)->deleted(new MasculineFake))
        ->toEqual(new NotificationDto('País eliminado correctamente', NotificationType::Success));
});

test('deleted announces a feminine record as eliminada', function (): void {
    expect((new NotificationService)->deleted(new FeminineFake))
        ->toEqual(new NotificationDto('Provincia eliminada correctamente', NotificationType::Success));
});

// --- notificationFor (routing + guard) --------------------------------------

test('notificationFor routes create-style actions to the created handler', function (string $action): void {
    $model = new MasculineFake;
    $model->wasRecentlyCreated = true;

    expect((new NotificationService)->notificationFor($model, $action))
        ->toEqual(new NotificationDto('País creado correctamente', NotificationType::Success));
})->with(['create', 'created']);

test('notificationFor routes delete-style actions to the deleted handler', function (string $action): void {
    expect((new NotificationService)->notificationFor(new MasculineFake, $action))
        ->toEqual(new NotificationDto('País eliminado correctamente', NotificationType::Success));
})->with(['delete', 'deleted']);

test('notificationFor throws for a disallowed action', function (): void {
    expect(fn () => (new NotificationService)->notificationFor(new MasculineFake, 'explode'))
        ->toThrow(InvalidArgumentException::class, 'Acción no permitida: explode');
});
