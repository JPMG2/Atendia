@props([
    'set',
    'values' => [],
    'prefix' => 'form.data.attribute_values',
])

{{-- The picked type's own fields, shared by the services and products
sheets: the DEFINITION (curated in the admin catalog) decides the control,
the tenant only fills values. Two per declared row so the sheet stays
compact; the errors wire by the attribute_values.{id} key. --}}
@foreach (collect($set)->chunk(2) as $pair)
    <x-catalog.form-row wire:key="attrs-{{ $pair->keys()->implode('-') }}">
        @foreach ($pair as $attribute)
            @php
                $key = $attribute['id'];
                $name = 'attribute_values.'.$key;
                $model = $prefix.'.'.$key;
                $label = $attribute['label'].($attribute['unit'] !== null ? ' ('.$attribute['unit'].')' : '');
                $value = $values[$key] ?? ($values[(string) $key] ?? null);
            @endphp

            @if ($attribute['data_type'] === 'boolean')
                <x-inputsform.switch-field
                    span="text"
                    :name="$name"
                    :label="$label"
                    :hint="$attribute['hint']"
                    wire:model="{{ $model }}"
                    :checked="(bool) $value"
                />
            @elseif ($attribute['data_type'] === 'list' && $attribute['is_multiple'])
                <div class="field f-text">
                    <span class="field-label">{{ $label }}</span>
                    <div class="flex flex-col gap-1.5 pt-1">
                        @foreach ($attribute['options'] ?? [] as $option)
                            <x-ui.checkbox
                                :name="$name.'[]'"
                                :label="$option"
                                :value="$option"
                                wire:model="{{ $model }}"
                                :checked="in_array($option, (array) $value, true)"
                            />
                        @endforeach
                    </div>
                    @if ($attribute['hint'] !== null || $errors->has($name))
                        <div class="field-meta">
                            @if ($attribute['hint'] !== null)
                                <span class="field-hint">{{ $attribute['hint'] }}</span>
                            @endif
                            @error($name)
                                <span class="field-error-text">{{ $message }}</span>
                            @enderror
                        </div>
                    @endif
                </div>
            @elseif ($attribute['data_type'] === 'list')
                <x-inputsform.combobox
                    span="text"
                    :name="$name"
                    :label="$label"
                    :hint="$attribute['hint']"
                    wire:model="{{ $model }}"
                    :value="$value"
                    :options="array_combine($attribute['options'] ?? [], $attribute['options'] ?? [])"
                />
            @elseif (in_array($attribute['data_type'], ['number', 'money'], true))
                <x-inputsform.input
                    span="text"
                    :name="$name"
                    :label="$label"
                    :hint="$attribute['hint']"
                    wire:model="{{ $model }}"
                    :value="$value"
                    class="font-mono"
                    inputmode="numeric"
                />
            @elseif ($attribute['data_type'] === 'date')
                <x-inputsform.input
                    span="text"
                    :name="$name"
                    :label="$label"
                    :hint="$attribute['hint']"
                    wire:model="{{ $model }}"
                    :value="$value"
                    class="font-mono"
                    placeholder="31/12/2026"
                />
            @elseif ($attribute['data_type'] === 'time')
                <x-inputsform.input
                    span="text"
                    :name="$name"
                    :label="$label"
                    :hint="$attribute['hint']"
                    wire:model="{{ $model }}"
                    :value="$value"
                    class="font-mono"
                    placeholder="09:00"
                />
            @else
                <x-inputsform.input
                    span="text"
                    :name="$name"
                    :label="$label"
                    :hint="$attribute['hint']"
                    wire:model="{{ $model }}"
                    :value="$value"
                />
            @endif
        @endforeach
    </x-catalog.form-row>
@endforeach
