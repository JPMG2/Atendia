@props(['node'])

{{-- One row of the sidebar as a role would see it, shown only while its
screens are switched on in the `roleEditor` state around it. --}}
@php
    $screens = $node->screenPermissions;
    $list = Illuminate\Support\Js::from($screens);
    $state = $node->isAlwaysOn ? "'on'" : ($screens === [] ? "'off'" : 'screenState('.$list.')');
@endphp

@if ($node->isGroup)
    <div x-show="{{ $state }} !== 'off'">
        <div class="re-pv re-pv-group">
            <x-icon :name="$node->icon ?? 'layout-dashboard'" :size="16" />{{ $node->label }}
        </div>
        <div class="re-pv-kids">
            @foreach ($node->children as $child)
                <x-roles.preview-node :node="$child" />
            @endforeach
        </div>
    </div>
@else
    <div
        class="re-pv"
        x-show="{{ $state }} !== 'off'"
        x-bind:class="{ 're-pv-new': {{ $state }} === 'new', 're-pv-gone': {{ $state }} === 'gone' }"
    >
        <x-icon :name="$node->icon ?? 'file-text'" :size="16" /><span class="re-pv-name">{{ $node->label }}</span>
        <span
            class="re-tag re-tag-new"
            x-show="{{ $state }} === 'new'"
            x-cloak
        >{{ __('admin.roles.editor.tag_new') }}</span>
        <span
            class="re-tag re-tag-warn"
            x-show="{{ $state }} === 'gone'"
            x-cloak
        >{{ __('admin.roles.editor.tag_gone') }}</span>
    </div>
@endif
