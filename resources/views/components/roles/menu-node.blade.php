@props(['node'])

{{-- One row of the admin menu in the role editor. It reads and writes the
`roleEditor` Alpine state of the screen around it (resources/js/role-editor.js). --}}
@php
    $screens = $node->screenPermissions;
    $screensJs = Illuminate\Support\Js::from($screens);

    // Actions are reachable while their screen is open; a group of loose
    // permissions has no screen to close.
    $gate = $node->permission === null ? 'true' : 'has('.Illuminate\Support\Js::from($node->permission).')';
@endphp

@if ($node->isGroup)
    <div class="re-group">
        <div class="re-row" x-bind:class="{ 're-off': state({{ $screensJs }}) === 'none' }">
            <span class="re-ico"><x-icon :name="$node->icon ?? 'layout-dashboard'" :size="18" /></span>
            <span class="re-label re-label-group">{{ $node->label }}</span>
            <x-ui.switch
                :aria-label="$node->label"
                x-bind:checked="state({{ $screensJs }}) === 'all'"
                x-effect="$el.indeterminate = state({{ $screensJs }}) === 'some'"
                x-on:change="setMany({{ $screensJs }}, $event.target.checked)"
            />
        </div>

        <div class="re-kids">
            @foreach ($node->children as $child)
                <x-roles.menu-node :node="$child" />
            @endforeach
        </div>
    </div>
@else
    <div class="re-item" x-data="{ expanded: false }">
        <div
            class="re-row"
            @if ($node->permission !== null) x-bind:class="{ 're-off': ! has({{ Illuminate\Support\Js::from($node->permission) }}) }" @endif
        >
            <span class="re-ico"><x-icon :name="$node->icon ?? 'file-text'" :size="16" /></span>
            <span class="re-label">{{ $node->label }}</span>

            @if ($node->isAlwaysOn)
                <span class="re-tag"><x-icon name="lock" :size="12" />{{ __('admin.roles.editor.always_on') }}</span>
            @else
                @if ($node->sharedWith !== null)
                    <span class="re-tag re-tag-warn">{{ __('admin.roles.editor.shares', ['screen' => $node->sharedWith]) }}</span>
                @endif

                @if ($node->actions !== [])
                    <button
                        type="button"
                        class="re-expand"
                        x-on:click="expanded = ! expanded"
                        x-bind:aria-expanded="expanded"
                    >
                        {{ __('admin.roles.editor.actions') }}
                        <span
                            class="font-mono"
                            x-text="{{ Illuminate\Support\Js::from($node->actions) }}.filter((p) => has(p)).length + '/{{ count($node->actions) }}'"
                        ></span>
                        <x-icon name="chevron-down" :size="14" />
                    </button>
                @endif

                @if ($node->permission !== null)
                    <x-ui.switch
                        :aria-label="$node->label"
                        x-bind:checked="has({{ Illuminate\Support\Js::from($node->permission) }})"
                        x-on:change="set({{ Illuminate\Support\Js::from($node->permission) }}, $event.target.checked)"
                    />
                @endif
            @endif
        </div>

        @if ($node->actions !== [])
            <div class="re-acts" x-show="expanded" x-cloak>
                @foreach ($node->actions as $action)
                    <label class="re-act" wire:key="act-{{ $action }}" x-bind:class="{ 're-off': ! {{ $gate }} }">
                        <span>{{ __('admin.roles.permissions.'.$action) }}</span>
                        <x-ui.switch
                            size="sm"
                            :aria-label="__('admin.roles.permissions.'.$action)"
                            x-bind:checked="has({{ Illuminate\Support\Js::from($action) }})"
                            x-bind:disabled="! {{ $gate }}"
                            x-on:change="set({{ Illuminate\Support\Js::from($action) }}, $event.target.checked)"
                        />
                    </label>
                @endforeach
            </div>
        @endif
    </div>
@endif
