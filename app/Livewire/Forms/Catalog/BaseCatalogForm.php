<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Dto\NotificationDto;
use App\Livewire\Forms\BaseForm;

/**
 * Contrato común de los Forms de catálogo.
 *
 * Los nombres son genéricos a propósito: la clase ya dice de qué maestro es
 * (`CurrencyForm::store()`, no `CurrencyForm::storeCurrency()` — "moneda" dos
 * veces). Gracias a eso el bloque de acciones del editor vive una sola vez en
 * `App\Traits\InteractsWithCatalogEditor` en lugar de repetirse en los 9 SFC.
 *
 * El `abstract` es la cerradura: un Form nuevo que se olvide de un método
 * revienta al cargar la clase, no en producción. Mismo criterio que
 * `App\Interfaces\Catalog\DataTable` para la fila de la tabla.
 */
abstract class BaseCatalogForm extends BaseForm
{
    /** Deja el DTO en blanco: es el estado con el que arranca un alta. */
    abstract public function setup(): void;

    abstract public function store(): NotificationDto;

    abstract public function update(): NotificationDto;

    /** Carga el registro en el form. Devuelve false si ya no existe. */
    abstract public function loadData(int $id): bool;
}
