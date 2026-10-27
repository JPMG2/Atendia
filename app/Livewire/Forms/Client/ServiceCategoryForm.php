<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Client;

use App\Classes\Main\Client;
use App\Dto\DtoCast;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\BaseForm;
use App\Rules\AttributeValidator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * The one-field sheet behind "Agregar categoría". The 30-character cap is
 * WhatsApp's: these shelves become catalog collections, whose names top
 * out there.
 */
class ServiceCategoryForm extends BaseForm
{
    /** The mobile API sends it, so the phone's input stops where the rule does. */
    public const int NAME_MAX = 30;

    public string $name = '';

    /** What the last save created — the service sheet picks it up on return. */
    public ?int $savedId = null;

    public function save(): NotificationDto
    {
        $menu = $this->client()->serviceMenu;

        if ($menu === null) {
            return new NotificationDto(__('notifications.not_found'), NotificationType::Error);
        }

        $validated = $this->validateServiceData();

        return $this->tryAction(function () use ($menu, $validated): NotificationDto {

            $category = $menu->addCategory($validated['name']);
            $this->name = '';
            $this->savedId = $category->id;

            return $this->notificationService()->notificationFor($category, 'created');

        }, __('notifications.not_created'));
    }

    /** Rebuilt per request: a Livewire form cannot hold it in a constructor. */
    private function client(): Client
    {
        return Client::for(Auth::user());
    }

    protected function transformServiceData(): array
    {
        return [
            'name' => DtoCast::squish($this->name) ?? '',
        ];
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        return self::fieldRules(Auth::user()?->business_id);
    }

    /**
     * The shelf sheet's rules, shared with the app's API.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function fieldRules(?int $businessId): array
    {
        return [
            'name' => [
                ...AttributeValidator::stringValid(true, '2'),
                'max:'.self::NAME_MAX,
                Rule::unique('service_categories', 'name')
                    ->where('business_id', $businessId)
                    ->whereNull('deleted_at'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function getValidationAttributes(): array
    {
        return self::fieldNames();
    }

    /**
     * @return array<string, string>
     */
    public static function fieldNames(): array
    {
        return [
            'name' => __('client.services.field_category_name'),
        ];
    }
}
