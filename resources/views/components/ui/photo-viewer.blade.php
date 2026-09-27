@props(['event' => 'photo-viewer'])

{{-- One carousel per page, opened by any photo-thumb of the page with every
photo of its album. Behaviour (zoom, pinch, swipe) lives in photo-viewer.js. --}}
<div
    x-data="photoViewer"
    x-on:{{ $event }}.window="open($event.detail)"
    x-on:keydown.escape.window="isOpen && close()"
    x-on:keydown.arrow-right.window="isOpen && go(index + 1)"
    x-on:keydown.arrow-left.window="isOpen && go(index - 1)"
    x-on:keydown.window="
        isOpen && ['+', '='].includes($event.key) && zoomStep(1);
        isOpen && $event.key === '-' && zoomStep(-1);
    "
>
    <template x-teleport="body">
        <div
            class="photo-viewer"
            x-show="isOpen"
            x-cloak
            x-transition.opacity.duration.200ms
            role="dialog"
            aria-modal="true"
            aria-label="{{ __('assistant.media.viewer') }}"
            x-on:click.self="close()"
        >
            <header class="photo-viewer-bar">
                <div class="min-w-0">
                    <p
                        class="font-mono text-sm"
                        x-text="@js(__('assistant.media.counter')).replace(':current', index + 1).replace(':total', photos.length)"
                    ></p>
                    <p class="photo-viewer-when font-mono" x-text="current.when"></p>
                </div>
                <div class="flex items-center gap-2">
                    <div class="photo-viewer-zoom">
                        <button
                            type="button"
                            class="photo-viewer-btn"
                            x-bind:disabled="! zoomed"
                            x-on:click="zoomStep(-1)"
                            title="{{ __('assistant.media.zoom_out') }}"
                            aria-label="{{ __('assistant.media.zoom_out') }}"
                        >
                            <x-icon name="zoom-out" :size="20" />
                        </button>
                        <span class="photo-viewer-level font-mono" x-text="Math.round(scale * 100) + '%'"></span>
                        <button
                            type="button"
                            class="photo-viewer-btn"
                            x-bind:disabled="scale >= 4"
                            x-on:click="zoomStep(1)"
                            title="{{ __('assistant.media.zoom_in') }}"
                            aria-label="{{ __('assistant.media.zoom_in') }}"
                        >
                            <x-icon name="zoom-in" :size="20" />
                        </button>
                    </div>
                    <a
                        class="photo-viewer-btn"
                        x-bind:href="current.download"
                        download
                        title="{{ __('assistant.media.download') }}"
                        aria-label="{{ __('assistant.media.download') }}"
                    >
                        <x-icon name="download" :size="20" />
                    </a>
                    <button
                        type="button"
                        class="photo-viewer-btn"
                        x-ref="close"
                        x-on:click="close()"
                        title="{{ __('assistant.media.close') }}"
                        aria-label="{{ __('assistant.media.close') }}"
                    >
                        <x-icon name="x" :size="20" />
                    </button>
                </div>
            </header>

            <div
                class="photo-viewer-stage"
                x-ref="stage"
                x-bind:data-zoomed="zoomed"
                x-on:click.self="close()"
                x-on:wheel.prevent="wheel($event)"
                x-on:pointerdown="pointerDown($event)"
                x-on:pointermove="pointerMove($event)"
                x-on:pointerup.window="pointerUp()"
                x-on:touchstart="touchStart($event)"
                x-on:touchmove.prevent="touchMove($event)"
                x-on:touchend="touchEnd($event)"
            >
                <button
                    type="button"
                    class="photo-viewer-nav prev"
                    x-show="photos.length > 1 && ! zoomed"
                    x-bind:disabled="index === 0"
                    x-on:click="go(index - 1)"
                    aria-label="{{ __('assistant.media.previous') }}"
                >
                    <x-icon name="chevron-left" :size="24" />
                </button>

                <template x-for="(photo, at) in photos" :key="photo.src">
                    <img
                        class="photo-viewer-photo"
                        x-show="at === index"
                        x-bind:data-current="at === index"
                        x-bind:style="at === index ? { transform } : {}"
                        x-transition:enter="photo-viewer-enter"
                        x-transition:enter-start="photo-viewer-enter-start"
                        x-bind:src="photo.src"
                        x-bind:alt="photo.caption || @js(__('assistant.media.image'))"
                        x-on:dblclick="toggleZoom($event)"
                        draggable="false"
                    />
                </template>

                <button
                    type="button"
                    class="photo-viewer-nav next"
                    x-show="photos.length > 1 && ! zoomed"
                    x-bind:disabled="index === photos.length - 1"
                    x-on:click="go(index + 1)"
                    aria-label="{{ __('assistant.media.next') }}"
                >
                    <x-icon name="chevron-right" :size="24" />
                </button>

                <p class="photo-viewer-tip" x-show="! zoomed">{{ __('assistant.media.zoom_tip') }}</p>
            </div>

            <footer class="photo-viewer-foot">
                <p class="photo-viewer-caption" x-show="current.caption" x-text="current.caption"></p>
                <div class="photo-viewer-strip" x-ref="strip" x-show="photos.length > 1">
                    <template x-for="(photo, at) in photos" :key="'thumb-' + photo.src">
                        <button
                            type="button"
                            class="photo-viewer-thumb"
                            x-bind:data-active="at === index"
                            x-on:click="go(at)"
                            x-bind:aria-label="@js(__('assistant.media.counter')).replace(':current', at + 1).replace(':total', photos.length)"
                        >
                            <img x-bind:src="photo.src" alt="" loading="lazy" />
                        </button>
                    </template>
                </div>
            </footer>
        </div>
    </template>
</div>
