<?php

use App\Classes\Main\Plan;
use App\Interfaces\Catalog\DataTable;
use App\Livewire\Forms\Catalog\BaseCatalogForm;
use App\Livewire\Forms\Catalog\PlanForm;
use App\Models\SubscriptionPlan;
use App\Traits\InteractsWithCatalogEditor;
use Illuminate\Support\Facades\Blade;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Renderless;
use Livewire\Component;

/**
 * Editor for the Plans master (`plans` table): the figures every card and gate
 * of the product reads. Edit-only — see PlanForm for why there is no "new".
 */
new class extends Component
{
    use InteractsWithCatalogEditor;

    public PlanForm $form;

    protected function catalogForm(): BaseCatalogForm
    {
        return $this->form;
    }

    protected function catalogModel(): DataTable
    {
        return new SubscriptionPlan;
    }

    /**
     * The landing's own card for the plan being edited, with what is typed laid
     * over the saved figures. It comes back as the real markup, so the preview
     * can never be a drawing that differs from what a visitor will see.
     *
     * @param  array<string, mixed>  $typed
     */
    #[Renderless]
    public function previewCard(string $code, array $typed): string
    {
        $plan = Plan::preview($code, $typed);

        if ($plan === null) {
            return '';
        }

        $ladder = Plan::ladder();
        $index = (int) collect($ladder)->search(fn (Plan $tier): bool => $tier->code === $code);

        return Blade::render('<x-site.plan-card :plan="$plan" :previous="$previous" :first="$first" :last="$last" />', [
            'plan' => $plan,
            'previous' => $ladder[max(0, $index - 1)],
            'first' => $index === 0,
            'last' => $index === count($ladder) - 1,
        ]);
    }

    /** @return list<array{value: string, label: string}> */
    #[Computed]
    public function statisticsOptions(): array
    {
        return collect(['counts', 'patterns', 'trends'])
            ->map(fn (string $level): array => ['value' => $level, 'label' => __('catalog.plan.statistics.'.$level)])
            ->all();
    }
};
?>

<x-catalog.master
    :rows="$initialRows"
    :search="['code', 'name']"
    :rules="[
        'price' => ['required', 'integer', ['min', 1], ['max', 99999]],
        'conversations_per_month' => ['required', 'integer', ['min', 1], ['max', 1000000]],
        'team_seats' => ['required', 'integer', ['min', 1], ['max', 100]],
        'messages_per_hour' => ['required', 'integer', ['min', 1], ['max', 10000]],
        'audio_minutes_per_month' => ['required', 'integer', ['min', 0], ['max', 100000]],
        'ask_per_month' => ['required', 'integer', ['min', 0], ['max', 100000]],
        'catalog_photos' => ['required', 'integer', ['min', 0], ['max', 1000000]],
        'photos_per_item' => ['required', 'integer', ['min', 0], ['max', 100]],
        'ai_alert_share' => ['required', 'integer', ['min', 1], ['max', 100]],
        'trial_days' => ['integer', ['min', 1], ['max', 90]],
    ]"
>
    <x-slot:list>
        <x-catalog.toolbar
            :search-placeholder="__('catalog.plan.search_placeholder')"
            :search-label="__('catalog.plan.search_label')"
            :singular="__('catalog.plan.singular')"
            :plural="__('catalog.plan.plural')"
        />

        <x-catalog.table
            :empty="__('catalog.plan.empty')"
            :columns="[
                ['label' => __('catalog.plan.columns.plan'), 'class' => 'catalog-col-fill'],
                ['label' => __('catalog.plan.columns.price'), 'class' => 'is-num'],
                ['label' => __('catalog.plan.columns.conversations'), 'class' => 'is-num'],
                ['label' => __('catalog.plan.columns.ask'), 'class' => 'is-num'],
                ['label' => __('catalog.plan.columns.businesses'), 'class' => 'is-num'],
                ['label' => __('catalog.plan.columns.tags')],
            ]"
        >
            <td class="catalog-cell-name catalog-cell-fill">
                <span x-text="row.name"></span>
                <span class="catalog-code plan-code" x-text="row.code"></span>
            </td>
            <td class="catalog-cell-num" x-text="'US$ ' + row.price"></td>
            <td class="catalog-cell-num" x-text="row.conversations.toLocaleString('es')"></td>
            <td class="catalog-cell-num" x-text="row.ask"></td>
            <td class="catalog-cell-num" x-text="row.businesses"></td>
            <td>
                <span class="status-tag is-brand" x-show="row.featured">{{ __('catalog.plan.tags.featured') }}</span>
                <span class="status-tag is-info" x-show="row.trial" x-text="{{ \Illuminate\Support\Js::from(__('catalog.plan.tags.trial', ['days' => ':days'])) }}.replace(':days', row.trial)"></span>
            </td>
        </x-catalog.table>
    </x-slot:list>

    <x-slot:form>
        <x-catalog.form-shell
            :edit-title="__('catalog.plan.edit_title')"
            :deletable="false"
            title-key="name"
        >
            {{-- An edit reaches whoever is on the plan the moment it is saved: said before, not after. --}}
            <template x-if="current && current.businesses > 0">
                <x-ui.alert variant="warning" icon="triangle-alert" class="mb-3">
                    <span x-text="(current.businesses === 1 ? {{ \Illuminate\Support\Js::from(__('catalog.plan.impact_one')) }} : {{ \Illuminate\Support\Js::from(__('catalog.plan.impact_many')) }}).replace(':count', current.businesses)"></span>
                </x-ui.alert>
            </template>

            <x-catalog.form-row>
                <x-inputsform.input span="short" :label="__('catalog.plan.fields.price')" required name="price" type="number" min="1" :hint="__('catalog.plan.fields.price_hint')" alpine-error="price" wire:model="form.data.price" />
                <x-inputsform.input span="short" :label="__('catalog.plan.fields.conversations_per_month')" required name="conversations_per_month" type="number" min="1" alpine-error="conversations_per_month" wire:model="form.data.conversations_per_month" />
                <x-inputsform.input span="short" :label="__('catalog.plan.fields.team_seats')" required name="team_seats" type="number" min="1" :hint="__('catalog.plan.fields.team_seats_hint')" alpine-error="team_seats" wire:model="form.data.team_seats" />
                <x-inputsform.input span="short" :label="__('catalog.plan.fields.messages_per_hour')" required name="messages_per_hour" type="number" min="1" :hint="__('catalog.plan.fields.messages_per_hour_hint')" alpine-error="messages_per_hour" wire:model="form.data.messages_per_hour" />
            </x-catalog.form-row>

            <x-catalog.form-row>
                <x-inputsform.input span="short" :label="__('catalog.plan.fields.audio_minutes_per_month')" required name="audio_minutes_per_month" type="number" min="0" :hint="__('catalog.plan.fields.audio_hint')" alpine-error="audio_minutes_per_month" wire:model="form.data.audio_minutes_per_month" />
                <x-inputsform.input span="short" :label="__('catalog.plan.fields.ask_per_month')" required name="ask_per_month" type="number" min="0" :hint="__('catalog.plan.fields.ask_hint')" alpine-error="ask_per_month" wire:model="form.data.ask_per_month" />
                <x-inputsform.input span="short" :label="__('catalog.plan.fields.catalog_photos')" required name="catalog_photos" type="number" min="0" alpine-error="catalog_photos" wire:model="form.data.catalog_photos" />
                <x-inputsform.input span="short" :label="__('catalog.plan.fields.photos_per_item')" required name="photos_per_item" type="number" min="0" alpine-error="photos_per_item" wire:model="form.data.photos_per_item" />
            </x-catalog.form-row>

            <x-catalog.form-row>
                <x-inputsform.combobox
                    span="text"
                    :label="__('catalog.plan.fields.statistics')"
                    required
                    name="statistics"
                    :options="$this->statisticsOptions"
                    :value="$form->data?->statistics"
                    alpine-error="statistics"
                    wire:model="form.data.statistics"
                />
                <x-inputsform.input span="short" :label="__('catalog.plan.fields.ai_alert_share')" required name="ai_alert_share" type="number" min="1" max="100" :hint="__('catalog.plan.fields.ai_alert_share_hint')" alpine-error="ai_alert_share" wire:model="form.data.ai_alert_share" />
                <x-inputsform.input span="short" :label="__('catalog.plan.fields.trial_days')" name="trial_days" type="number" min="1" :hint="__('catalog.plan.fields.trial_days_hint')" alpine-error="trial_days" wire:model="form.data.trial_days" />
            </x-catalog.form-row>

            <x-catalog.form-row>
                <x-inputsform.switch-field span="short" :label="__('catalog.plan.fields.is_featured')" name="is_featured" :on="__('catalog.plan.switch.on')" :off="__('catalog.plan.switch.off')" wire:model="form.data.is_featured" />
                <x-inputsform.switch-field span="short" :label="__('catalog.plan.fields.reads_media')" name="reads_media" :on="__('catalog.plan.switch.on')" :off="__('catalog.plan.switch.off')" wire:model="form.data.reads_media" />
                <x-inputsform.switch-field span="short" :label="__('catalog.plan.fields.departments')" name="departments" :on="__('catalog.plan.switch.on')" :off="__('catalog.plan.switch.off')" wire:model="form.data.departments" />
                <x-inputsform.switch-field span="short" :label="__('catalog.plan.fields.daily_digest')" name="daily_digest" :on="__('catalog.plan.switch.on')" :off="__('catalog.plan.switch.off')" wire:model="form.data.daily_digest" />
            </x-catalog.form-row>

            {{-- The card as the landing draws it, following what is typed: the
            figures are judged on the page that sells them, not on a form. --}}
            <section class="plan-preview" x-data="planPreview" data-testid="plan-preview">
                <div class="plan-preview-head">
                    <div>
                        <h3 class="plan-preview-title">{{ __('catalog.plan.preview.title') }}</h3>
                        <p class="bp-card-sub">{{ __('catalog.plan.preview.note') }}</p>
                    </div>

                    <div class="pricing-period" role="group" aria-label="{{ __('catalog.plan.preview.title') }}">
                        <button type="button" class="pricing-period-btn" x-bind:class="! yearly && 'is-active'" x-on:click="yearly = false">
                            {{ __('landing.pricing.billing_monthly') }}
                        </button>
                        <button type="button" class="pricing-period-btn" x-bind:class="yearly && 'is-active'" x-on:click="yearly = true">
                            {{ __('landing.pricing.billing_yearly') }}
                        </button>
                    </div>
                </div>

                <div class="plan-preview-card" x-html="html"></div>
            </section>
        </x-catalog.form-shell>
    </x-slot:form>
</x-catalog.master>
