/*
 * The carousel and its thumbnails: a thumb hands its whole [data-photo-album]
 * over in one event, so the viewer never asks the server to page. Registers
 * on the Alpine that Livewire brings, NEVER by importing Alpine.
 */

const MAX_SCALE = 4;
const DOUBLE_TAP_SCALE = 2.5;

function clamp(value, min, max) {
    return Math.min(Math.max(value, min), max);
}

function photoViewer() {
    return {
        photos: [],
        index: 0,
        isOpen: false,
        scale: 1,
        panX: 0,
        panY: 0,

        // Gesture bookkeeping: one finger swipes (or pans when zoomed), two pinch.
        swipeX: null,
        drag: null,
        pinch: null,
        lastTap: 0,

        get current() {
            return this.photos[this.index] ?? {};
        },

        get zoomed() {
            return this.scale > 1;
        },

        get transform() {
            return `translate(${this.panX}px, ${this.panY}px) scale(${this.scale})`;
        },

        open(detail) {
            this.photos = detail.photos ?? [];
            this.index = clamp(detail.index ?? 0, 0, this.photos.length - 1);
            this.isOpen = this.photos.length > 0;
            this.resetZoom();
            document.documentElement.style.overflow = 'hidden';
            this.$nextTick(() => {
                this.$refs.close?.focus();
                this.reveal();
            });
        },

        close() {
            this.isOpen = false;
            document.documentElement.style.overflow = '';
        },

        go(to) {
            if (to < 0 || to >= this.photos.length) {
                return;
            }

            this.index = to;
            this.resetZoom();
            this.$nextTick(() => this.reveal());
        },

        reveal() {
            this.$refs.strip
                ?.querySelector('[data-active=true]')
                ?.scrollIntoView({ block: 'nearest', inline: 'center', behavior: 'smooth' });
        },

        resetZoom() {
            this.scale = 1;
            this.panX = 0;
            this.panY = 0;
        },

        // Scales around a screen point, so the spot under the finger or the
        // cursor stays put instead of the photo sliding away from it.
        zoomAt(nextScale, clientX, clientY) {
            const photo = this.$refs.stage?.querySelector('[data-current=true]');
            const next = clamp(nextScale, 1, MAX_SCALE);

            if (!photo || next === 1) {
                this.resetZoom();

                return;
            }

            const box = photo.getBoundingClientRect();
            const originX = clientX - (box.left + box.width / 2);
            const originY = clientY - (box.top + box.height / 2);
            const ratio = next / this.scale;

            this.panX -= originX * (ratio - 1);
            this.panY -= originY * (ratio - 1);
            this.scale = next;
            this.keepInside(photo);
        },

        // The photo never drags fully out of view: at most its own overhang.
        keepInside(photo) {
            const limitX = (photo.offsetWidth * (this.scale - 1)) / 2;
            const limitY = (photo.offsetHeight * (this.scale - 1)) / 2;

            this.panX = clamp(this.panX, -limitX, limitX);
            this.panY = clamp(this.panY, -limitY, limitY);
        },

        toggleZoom(event) {
            if (this.zoomed) {
                this.resetZoom();
            } else {
                this.zoomAt(DOUBLE_TAP_SCALE, event.clientX, event.clientY);
            }
        },

        zoomStep(direction) {
            const stage = this.$refs.stage.getBoundingClientRect();

            this.zoomAt(this.scale + direction, stage.left + stage.width / 2, stage.top + stage.height / 2);
        },

        wheel(event) {
            this.zoomAt(this.scale * (event.deltaY < 0 ? 1.2 : 1 / 1.2), event.clientX, event.clientY);
        },

        pointerDown(event) {
            if (this.zoomed && event.pointerType === 'mouse') {
                this.drag = { x: event.clientX - this.panX, y: event.clientY - this.panY };
            }
        },

        pointerMove(event) {
            if (this.drag === null) {
                return;
            }

            this.panX = event.clientX - this.drag.x;
            this.panY = event.clientY - this.drag.y;
            this.keepInside(this.$refs.stage.querySelector('[data-current=true]'));
        },

        pointerUp() {
            this.drag = null;
        },

        touchStart(event) {
            if (event.touches.length === 2) {
                const [a, b] = event.touches;
                this.pinch = { distance: Math.hypot(a.clientX - b.clientX, a.clientY - b.clientY), scale: this.scale };
                this.swipeX = null;

                return;
            }

            const touch = event.touches[0];

            // Two taps within 300ms read as a double tap, like every photo app.
            if (Date.now() - this.lastTap < 300) {
                this.toggleZoom(touch);
                this.lastTap = 0;

                return;
            }

            this.lastTap = Date.now();
            this.swipeX = this.zoomed ? null : touch.clientX;
            this.drag = this.zoomed ? { x: touch.clientX - this.panX, y: touch.clientY - this.panY } : null;
        },

        touchMove(event) {
            if (this.pinch !== null && event.touches.length === 2) {
                const [a, b] = event.touches;
                const distance = Math.hypot(a.clientX - b.clientX, a.clientY - b.clientY);

                this.zoomAt(this.pinch.scale * (distance / this.pinch.distance), (a.clientX + b.clientX) / 2, (a.clientY + b.clientY) / 2);

                return;
            }

            if (this.drag !== null) {
                this.pointerMove(event.touches[0]);
            }
        },

        touchEnd(event) {
            const touch = event.changedTouches[0];

            if (this.swipeX !== null && Math.abs(touch.clientX - this.swipeX) > 40) {
                this.go(this.index + (touch.clientX < this.swipeX ? 1 : -1));
            }

            this.swipeX = null;
            this.drag = null;
            this.pinch = null;
        },
    };
}

function photoThumb(event = 'photo-viewer') {
    return {
        openAlbum() {
            const album = this.$el.closest('[data-photo-album]') ?? this.$el.parentElement;
            const thumbs = [...album.querySelectorAll('[data-photo]')];

            this.$dispatch(event, {
                index: thumbs.indexOf(this.$el),
                photos: thumbs.map((thumb) => ({
                    src: thumb.dataset.src,
                    download: thumb.dataset.download,
                    caption: thumb.dataset.caption,
                    when: thumb.dataset.when,
                })),
            });
        },
    };
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('photoViewer', photoViewer);
    window.Alpine.data('photoThumb', photoThumb);
});
