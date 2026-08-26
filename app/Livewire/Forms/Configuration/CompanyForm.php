<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Configuration;

use App\Dto\CompanyDto;
use App\Models\Company;
use Livewire\Attributes\Locked;
use Livewire\Form;

/**
 * El formulario de la Compañía: UN solo registro, para siempre.
 *
 * No hay alta ni baja — o el registro ya existe y se edita, o todavía no se
 * cargó y el formulario arranca en blanco. Por eso no hereda de
 * `BaseCatalogForm`: no hay tabla que listar ni id que elegir.
 */
class CompanyForm extends Form
{
    /**
     * El registro cargado. `null` = todavía no se cargaron los datos.
     *
     * Va `#[Locked]` porque nunca lo elige el front: lo asigna `setup()` en el
     * server a partir del único registro de la tabla.
     */
    #[Locked]
    public ?int $recordId = null;

    /**
     * El DTO con el estado del formulario.
     *
     * Tiene que quedar inicializado (no null) antes del primer render o un
     * `wire:model="form.data.legal_name"` revienta con "Cannot assign array to
     * property": Livewire no puede recursar dentro de un null.
     */
    public ?CompanyDto $data = null;

    /**
     * Carga el emisor en el formulario, o lo deja en blanco si no existe.
     *
     * `setup()` NO es un hook de Livewire Form: lo llama el `mount()` del
     * componente, igual que en los editores de catálogo.
     */
    public function setup(): void
    {
        // La tabla tiene un solo registro: el primero ES la compañía.
        // El país no se trae: lo único que se lee de él es su id, y ese vive en
        // `provinces.country_id`. Sumar `.country` es una consulta que nadie usa.
        $company = Company::with('region.province')->first();

        if ($company === null) {
            $this->data = new CompanyDto;

            return;
        }

        $this->recordId = $company->id;
        $this->data = CompanyDto::fromArray($this->stateFrom($company));
    }

    /**
     * Cambiar el país invalida todo lo que cuelga de él.
     *
     * Sin esto la provincia vieja sobrevive al cambio: desaparece de la lista
     * —que ya está acotada al país nuevo— pero sigue en el DTO, y lo que se
     * guardaría es una región de otro país.
     *
     * Solo corre cuando el cambio viene del front. Las asignaciones del server
     * (`setup()`, o el reset de acá abajo) no disparan el hook, así que cargar
     * una compañía existente no se borra a sí misma.
     */
    public function updatedDataCountryId(): void
    {
        if ($this->data === null) {
            return;
        }

        $this->data->province_id = null;
        $this->data->region_id = null;
    }

    /**
     * Cambiar la provincia invalida la región, y solo la región: el país sigue
     * siendo válido porque la provincia nueva cuelga de él.
     */
    public function updatedDataProvinceId(): void
    {
        if ($this->data === null) {
            return;
        }

        $this->data->region_id = null;
    }

    /**
     * El registro más el país y la provincia, que no son columnas suyas.
     *
     * La tabla guarda solo la región; el domicilio se muestra de lo general a lo
     * puntual, así que los dos combobox de arriba se derivan subiendo por la
     * cadena región → provincia → país.
     *
     * @return array<string, mixed>
     */
    private function stateFrom(Company $company): array
    {
        // Ya vienen cargadas desde `setup()`: pedirlas con `region()->...` sería
        // volver a consultarlas y tirar el eager load.
        $region = $company->region;

        return [
            ...$company->toArray(),
            'province_id' => $region?->province_id,
            'country_id' => $region?->province?->country_id,
        ];
    }
}
