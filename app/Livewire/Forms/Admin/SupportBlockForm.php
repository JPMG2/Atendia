<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Admin;

use App\Actions\Support\BlockSupportTicket;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\BaseForm;
use App\Models\SupportTicket;
use App\Models\User;
use App\Rules\AttributeValidator;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;

/**
 * The hand-over of a report that could not be solved. Every field is
 * required but the notice: a block that does not say what is missing is the
 * same lost report with one more status on it.
 */
class SupportBlockForm extends BaseForm
{
    public string $tried = '';

    public string $missing = '';

    public string $owner = '';

    /** ISO, because that is what the datepicker's hidden field carries. */
    public string $due = '';

    public string $notice = '';

    /** The people a block can be handed to: the team, Development and the business itself. */
    public const string DEVELOPMENT = 'Desarrollo';

    public const string BUSINESS = 'El negocio';

    public function setup(): void
    {
        $this->reset();
        $this->due = now()->addDays(3)->toDateString();
        $this->owner = self::DEVELOPMENT;
    }

    /**
     * Who can follow it: the team by name, plus the two that are not a person.
     *
     * @return array<string, string>
     */
    public static function owners(): array
    {
        return collect(User::supportTeam())
            ->values()
            ->push(self::DEVELOPMENT, self::BUSINESS)
            ->unique()
            ->mapWithKeys(fn (string $name): array => [$name => $name])
            ->all();
    }

    public function save(SupportTicket $ticket, User $author): NotificationDto
    {
        $validated = $this->validateServiceData();

        return $this->tryAction(function () use ($ticket, $author, $validated): NotificationDto {
            app(BlockSupportTicket::class)->handle(
                $ticket,
                $validated['tried'],
                $validated['missing'],
                $validated['owner'],
                CarbonImmutable::parse($validated['due']),
                $validated['notice'] ?? null,
                $author,
            );

            return new NotificationDto(__('support.admin.blocked_saved'), NotificationType::Success);
        }, __('support.admin.blocked_failed'));
    }

    protected function transformServiceData(): array
    {
        return [
            'tried' => trim($this->tried),
            'missing' => trim($this->missing),
            'owner' => trim($this->owner),
            'due' => $this->due,
            'notice' => trim($this->notice) === '' ? null : trim($this->notice),
        ];
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        return [
            'tried' => ['required', 'string', 'min:10', 'max:2000', AttributeValidator::xssFree()],
            'missing' => ['required', 'string', 'min:10', 'max:2000', AttributeValidator::xssFree()],
            'owner' => ['required', Rule::in(array_keys(self::owners()))],
            // Not in the past: a follow-up dated yesterday is already a broken promise.
            'due' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'notice' => ['nullable', 'string', 'max:900', AttributeValidator::xssFree()],
        ];
    }

    /** @return array<string, string> */
    protected function getValidationAttributes(): array
    {
        return [
            'tried' => __('support.admin.block.tried'),
            'missing' => __('support.admin.block.missing'),
            'owner' => __('support.admin.block.owner'),
            'due' => __('support.admin.block.due'),
            'notice' => __('support.admin.block.notice'),
        ];
    }
}
