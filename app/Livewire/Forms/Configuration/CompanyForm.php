<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Configuration;

use App\Dto\CompanyDto;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\BaseForm;
use App\Models\Company;
use App\Models\SocialLink;
use App\Rules\AttributeValidator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Locked;

/**
 * El formulario de la Compañía: UN solo registro, para siempre.
 *
 * No hay alta ni baja — o el registro ya existe y se edita, o todavía no se
 * cargó y el formulario arranca en blanco. Por eso NO hereda de
 * `BaseCatalogForm`: no hay tabla que listar, ni id que elegir en un riel, ni
 * `CatalogWiring` con Actions de alta y edición. Hereda del piso de abajo,
 * `BaseForm`, que es lo genérico de cualquier formulario: validar un payload y
 * envolver la acción en `tryAction()`.
 *
 * Tampoco pasa por una Action: en catálogos la Action existe porque es el punto
 * de enchufe que hace genérica a la base de los 9 maestros. Acá hay un solo
 * llamador y la operación es escribir una fila; una Action sería una
 * indirección con una sola entrada y una sola salida.
 */
class CompanyForm extends BaseForm
{
    /**
     * Los pasos de la pantalla.
     *
     * Cada uno valida y guarda SOLO sus columnas. Son las mismas claves que usa
     * el bag de reglas del front (`rules.main` / `rules.commercial`): que se
     * llamen igual de los dos lados es lo que deja leer "qué exige cada paso"
     * sin traducir.
     */
    public const STEP_MAIN = 'main';

    public const STEP_COMMERCIAL = 'commercial';

    /**
     * Campos del paso principal que NO son columnas de `companies`.
     *
     * El país y la provincia se derivan de la región y viven en el DTO como
     * estado de pantalla. Descartar tiene que devolverlos también: si no, la
     * cascada sigue mostrando el domicilio que el usuario acaba de descartar.
     *
     * @var list<string>
     */
    private const MAIN_SCREEN_STATE = ['country_id', 'province_id'];

    /**
     * Columnas de `companies` que escribe el paso comercial.
     *
     * Acá SÍ hace falta la lista aparte: las reglas del paso incluyen las de las
     * redes (`social.*.…`), que no son columnas de esta tabla. En el paso 1 las
     * claves de las reglas ya son exactamente sus columnas.
     *
     * @var list<string>
     */
    private const COMMERCIAL_COLUMNS = ['email', 'phone', 'web'];

    /**
     * El registro cargado. `null` = todavía no se cargaron los datos.
     *
     * Va `#[Locked]` porque nunca lo elige el front: lo asigna `setup()` en el
     * server a partir del único registro de la tabla, o `saveMain()` cuando lo
     * acaba de crear.
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
     * Las redes de la compañía, como filas editables.
     *
     * Cada fila es `['key' => int, 'id' => ?int, 'social_network_id' => ?int,
     * 'url' => ?string]`. `key` identifica a la FILA (no a la red ni al registro):
     * es lo que va en el `wire:key`, para que quitar una del medio mueva el nodo
     * correcto en vez de repintar el que quedó en ese índice. `id` es el registro
     * de `social_links` cuando la fila YA está guardada, y es lo que distingue
     * quitar una fila en blanco de borrar un enlace de verdad.
     *
     * Viven acá y no en el DTO porque no son columnas de `companies`: son filas
     * de `social_links`, y el dueño es polimórfico.
     *
     * @var array<int, array{key: int, id: int|null, social_network_id: int|null, url: string|null}>
     */
    public array $social = [];

    /** Próxima clave de fila. Nunca la elige el front. */
    #[Locked]
    public int $nextSocialKey = 1;

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
        // Las redes SÍ: son las filas del paso 2, y pedirlas después sería la
        // misma consulta hecha tarde.
        $company = Company::with(['region.province', 'socialLinks'])->first();

        if ($company === null) {
            $this->data = new CompanyDto;
            $this->social = [$this->blankSocialRow()];

            return;
        }

        $this->recordId = $company->id;
        $this->data = CompanyDto::fromArray($this->stateFrom($company));
        $this->social = $this->socialStateFrom($company);
    }

    /**
     * Suma una fila de red vacía debajo de la indicada.
     *
     * Agregar y quitar cuestan un request: las filas son estado del SERVER, que
     * es la única forma de que lo tipeado sobreviva al guardado. Lo que el
     * usuario venía escribiendo viaja con este mismo request, así que no se
     * pierde.
     */
    public function addSocialRow(int $after): void
    {
        array_splice($this->social, $after + 1, 0, [$this->blankSocialRow()]);
    }

    /**
     * Quita una fila y, si esa red ya estaba guardada, la BORRA en el momento.
     *
     * La baja no espera al botón de guardar: por eso la pantalla pide
     * confirmación antes de llamar acá, y por eso `SocialLink` deja rastro en el
     * activity log —no hay papelera de la cual sacarla—.
     *
     * Siempre queda una fila: si se pudieran borrar todas, no habría dónde
     * volver a empezar. Borrar la última deja la fila en blanco.
     *
     * Devuelve el aviso del borrado, o null si solo se quitó una fila que nunca
     * llegó a guardarse (ahí no pasó nada que contar).
     */
    public function removeSocialRow(int $index): ?NotificationDto
    {
        $row = $this->social[$index] ?? null;

        if ($row === null) {
            return null;
        }

        $notification = $row['id'] !== null ? $this->deleteSocialLink($row['id']) : null;

        if (count($this->social) === 1) {
            $this->social = [$this->blankSocialRow()];

            return $notification;
        }

        unset($this->social[$index]);

        $this->social = array_values($this->social);

        return $notification;
    }

    /**
     * Borra un enlace de ESTA compañía.
     *
     * Se busca por la relación y no por `SocialLink::find()`: `$social` es
     * estado público del formulario, así que un id llegado del front tiene que
     * poder ser solo de su propio dueño.
     */
    private function deleteSocialLink(int $id): NotificationDto
    {
        $link = Company::query()->find($this->recordId)?->socialLinks()->find($id);

        if (! $link instanceof SocialLink) {
            return new NotificationDto(__('notifications.not_found'), NotificationType::Error);
        }

        // spatie registra la baja sola (LogsActivity en el modelo).
        $link->delete();

        return $this->notificationService()->notificationFor($link, 'deleted');
    }

    /**
     * Guarda el paso principal: identidad, domicilio, datos fiscales, logo y pie.
     *
     * Es un upsert, no un alta: si el registro ya existe lo actualiza. Lo que se
     * escribe son las columnas del paso 1 y NADA más — `validate()` devuelve
     * solo los atributos que tienen regla, así que el contacto y las redes del
     * paso 2 no se pisan con lo que hubiera en memoria.
     */
    public function saveMain(): NotificationDto
    {
        $validated = $this->validateStep(self::STEP_MAIN);

        $creating = $this->recordId === null;

        return $this->tryAction(function () use ($validated): NotificationDto {

            // La tabla tiene UN registro: si ya existe se actualiza, aunque este
            // formulario haya montado cuando todavía no estaba (otra pestaña
            // abierta, doble click). Sin ese `first()` de respaldo, el segundo
            // guardado crearía una segunda compañía.
            $company = Company::query()->find($this->recordId)
                ?? Company::query()->first()
                ?? new Company;

            $company->fill($validated)->save();

            $this->recordId = $company->id;

            return $this->notificationService()->notificationFor(
                $company,
                $company->wasRecentlyCreated ? 'created' : 'updated',
            );

        }, $creating ? __('notifications.not_created') : __('notifications.not_updated'));
    }

    /**
     * Guarda el paso comercial: el contacto público y las redes.
     *
     * Nunca crea la compañía —para eso está el paso 1, y el 2 ni siquiera se
     * abre hasta que existe—; si el registro no está, avisa en vez de inventar
     * uno a medias.
     */
    public function saveCommercial(): NotificationDto
    {
        if ($this->recordId === null) {
            return new NotificationDto(__('notifications.not_found'), NotificationType::Error);
        }

        $validated = $this->validateStep(self::STEP_COMMERCIAL);

        return $this->tryAction(function () use ($validated): NotificationDto {

            $company = Company::query()->findOrFail($this->recordId);

            $company->fill(Arr::only($validated, self::COMMERCIAL_COLUMNS))->save();

            $linksChanged = $this->syncSocialLinks($company, $validated['social'] ?? []);

            // Si SOLO cambiaron las redes, la fila de `companies` quedó igual y
            // `notificationFor()` diría "no se realizaron cambios" con las redes
            // recién guardadas ahí a la vista.
            if ($linksChanged && ! $company->wasChanged()) {
                return new NotificationDto(
                    __('notifications.updated.female', ['entity' => __('notifications.entities.company')]),
                    NotificationType::Success,
                );
            }

            return $this->notificationService()->notificationFor($company, 'updated');

        }, __('notifications.not_updated'));
    }

    /**
     * Devuelve el paso comercial al último estado guardado, redes incluidas.
     */
    public function discardCommercial(): void
    {
        if ($this->data === null) {
            return;
        }

        $company = Company::with('socialLinks')->find($this->recordId);

        $saved = $company !== null ? CompanyDto::fromArray($company->toArray()) : new CompanyDto;

        foreach (self::COMMERCIAL_COLUMNS as $field) {
            $this->data->{$field} = $saved->{$field};
        }

        $this->social = $company !== null
            ? $this->socialStateFrom($company)
            : [$this->blankSocialRow()];
    }

    /**
     * Devuelve el paso principal al último estado guardado.
     *
     * Sin compañía cargada, ese estado es el formulario en blanco. Toca SOLO los
     * campos del paso: lo que el usuario escribió en el paso 2 y todavía no
     * guardó no tiene por qué perderse porque descartó el 1.
     *
     * Las asignaciones son del server, así que no disparan `updatedData*`: la
     * región restaurada no se borra a sí misma al reponer el país.
     */
    public function discardMain(): void
    {
        if ($this->data === null) {
            return;
        }

        $company = Company::with('region.province')->find($this->recordId);

        $saved = $company !== null
            ? CompanyDto::fromArray($this->stateFrom($company))
            : new CompanyDto;

        // Las claves de las reglas del paso SON sus columnas: una sola lista
        // para lo que se valida, lo que se guarda y lo que se descarta.
        foreach ([...array_keys($this->rulesFor(self::STEP_MAIN)), ...self::MAIN_SCREEN_STATE] as $field) {
            $this->data->{$field} = $saved->{$field};
        }
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
     * Contrato de `BaseForm`: las reglas del formulario ENTERO.
     *
     * El guardado no usa este método: cada paso valida con las suyas
     * ({@see self::validateStep()}). Validar el paso parado contra las reglas
     * del otro pintaría el error en un panel oculto, que para el usuario es el
     * botón que "no hace nada".
     *
     * @return array<string, mixed>
     */
    protected function getValidationRules(?int $excludeId = null): array
    {
        return [
            ...$this->rulesFor(self::STEP_MAIN),
            ...$this->rulesFor(self::STEP_COMMERCIAL),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function getValidationAttributes(): array
    {
        return [
            'legal_name' => config('nicename.legal_name'),
            'tagline' => config('nicename.tagline'),
            'region_id' => config('nicename.region_id'),
            'address' => config('nicename.address'),
            'tax_condition_id' => config('nicename.tax_condition_id'),
            'tax_id' => config('nicename.tax_id'),
            'logo_path_light' => config('nicename.logo_path_light'),
            'logo_path_dark' => config('nicename.logo_path_dark'),
            'text_copyright' => config('nicename.text_copyright'),
            'email' => config('nicename.email'),
            'phone' => config('nicename.phone'),
            'web' => config('nicename.web'),
            // Laravel resuelve `social.0.url` contra la clave con comodín.
            'social.*.social_network_id' => config('nicename.social_network_id'),
            'social.*.url' => config('nicename.url'),
        ];
    }

    protected function transformServiceData(): array
    {
        return [
            ...$this->data?->toPayload() ?? [],
            'social' => $this->filledSocialRows(),
        ];
    }

    /**
     * Las filas de red que el usuario realmente cargó.
     *
     * La pantalla siempre muestra una fila vacía para poder empezar; esa fila no
     * es un error de carga, simplemente no es una red. Una a medias (enlace sin
     * red, o al revés) SÍ pasa: ahí hay que avisar, no descartar en silencio.
     *
     * @return array<int, array{key: int, id: int|null, social_network_id: int|null, url: string|null}>
     */
    private function filledSocialRows(): array
    {
        return array_values(array_filter(
            $this->social,
            fn (array $row): bool => $row['social_network_id'] !== null || trim((string) $row['url']) !== '',
        ));
    }

    /**
     * Deja las redes guardadas exactamente como las dejó la pantalla.
     *
     * Devuelve si algo cambió, que es lo que decide el mensaje del toast.
     *
     * @param  array<int, array{social_network_id: int, url: string}>  $rows
     */
    private function syncSocialLinks(Company $company, array $rows): bool
    {
        $changed = false;
        $kept = [];

        foreach (array_values($rows) as $index => $row) {
            // La red es la clave: es lo que la tabla tiene como unique por dueño,
            // así que reordenar o corregir un enlace edita la fila, no la duplica.
            $link = $company->socialLinks()->updateOrCreate(
                ['social_network_id' => $row['social_network_id']],
                ['url' => $row['url'], 'sort_order' => $index],
            );

            $changed = $changed || $link->wasRecentlyCreated || $link->wasChanged();
            $kept[] = $link->id;
        }

        // Lo que ya no está en pantalla se va: una fila borrada no puede
        // sobrevivir en la base esperando al próximo render.
        $removed = $company->socialLinks()->whereNotIn('id', $kept)->delete();

        return $changed || $removed > 0;
    }

    /**
     * Las redes guardadas, como filas de pantalla y en su orden.
     *
     * @return array<int, array{key: int, id: int|null, social_network_id: int|null, url: string|null}>
     */
    private function socialStateFrom(Company $company): array
    {
        $rows = $company->socialLinks
            ->map(fn (SocialLink $link): array => [
                'key' => $this->nextSocialKey++,
                'id' => $link->id,
                'social_network_id' => $link->social_network_id,
                'url' => $link->url,
            ])
            ->all();

        // Sin ninguna cargada queda la fila en blanco: la pantalla nunca muestra
        // la sección vacía, porque no habría dónde escribir la primera.
        return $rows === [] ? [$this->blankSocialRow()] : $rows;
    }

    /**
     * @return array{key: int, id: null, social_network_id: null, url: null}
     */
    private function blankSocialRow(): array
    {
        return ['key' => $this->nextSocialKey++, 'id' => null, 'social_network_id' => null, 'url' => null];
    }

    /**
     * Las reglas de UN paso.
     *
     * Es también la lista de columnas que ese paso escribe: `validate()`
     * devuelve únicamente los atributos que tienen regla, así que un campo del
     * paso sin regla acá no se guardaría nunca —se perdería en silencio, que es
     * peor que un error.
     *
     * @return array<string, mixed>
     */
    private function rulesFor(string $step): array
    {
        return match ($step) {

            self::STEP_MAIN => [

                // El nombre con el que se emite la factura. Sin `unique`: la
                // tabla tiene un solo registro, no hay con quién chocar.
                'legal_name' => AttributeValidator::stringValid(true, '3'),

                // Opcional (la columna es nullable): sin el `nullable`, una
                // compañía sin tagline rebotaría contra el `min` de stringValid().
                'tagline' => ['nullable', ...AttributeValidator::stringValid(false, '3')],

                // La tabla guarda SOLO la región; el país y la provincia de la
                // pantalla se derivan de ella y no son columnas.
                'region_id' => AttributeValidator::requireAndExists('regions', 'id', 'region_id', true),

                'address' => AttributeValidator::stringValid(true, '3'),

                'tax_condition_id' => AttributeValidator::requireAndExists('tax_conditions', 'id', 'tax_condition_id', true),

                // La columna es varchar(20) y el input pone maxlength=20: sin
                // este tope, el max:255 de stringValid dejaba pasar un número
                // que después revienta en Postgres.
                'tax_id' => [...AttributeValidator::stringValid(true, '3'), 'max:20'],

                // Todavía no se suben —la zona de carga es maqueta—, pero las
                // reglas van igual: son columnas del paso, y lo que no tiene
                // regla no se guarda.
                'logo_path_light' => ['nullable', ...AttributeValidator::stringValid(false, '1')],
                'logo_path_dark' => ['nullable', ...AttributeValidator::stringValid(false, '1')],

                'text_copyright' => ['nullable', ...AttributeValidator::stringValid(false, '3')],
            ],

            self::STEP_COMMERCIAL => [

                // Los tres son opcionales (las columnas son nullable): la empresa
                // puede no publicar un teléfono.
                'email' => ['nullable', 'email:rfc', 'max:255'],

                // La columna es varchar(30); digitValid deja pasar los separadores
                // que la gente escribe de verdad ("+54 9 11 5555-1234").
                'phone' => ['nullable', ...AttributeValidator::digitValid('6', false), 'max:30'],

                // NO se usa webValid(): ese helper suma `active_url`, que resuelve
                // DNS en cada guardado — el sitio de la empresa no puede tener que
                // estar online para poder guardar su ficha.
                'web' => ['nullable', 'url:http,https', 'max:255'],

                'social' => ['array', 'max:20'],

                // `distinct` es la mitad en pantalla del unique (dueño + red) de la
                // tabla: sin él, dos filas con la misma red no serían un error de
                // campo sino un crash de base atrapado por tryAction.
                'social.*.social_network_id' => ['required', 'integer', 'distinct', 'exists:social_networks,id'],

                // El campo se llama "Enlace o usuario" y eso es lo que acepta: pedir
                // una URL completa haría rebotar un "@atendia" que la propia
                // pantalla ofrece escribir.
                'social.*.url' => [...AttributeValidator::stringValid(true, '3'), 'max:255'],
            ],

            default => [],
        };
    }

    /**
     * Valida el payload contra las reglas de UN paso, y devuelve solo esas claves.
     *
     * @return array<string, mixed>
     */
    private function validateStep(string $step): array
    {
        return Validator::make(
            $this->transformServiceData(),
            $this->rulesFor($step),
            [],
            $this->getValidationAttributes(),
        )->validate();
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
