{{-- One toggle for every layout: the wizard lost it by copying chrome instead
of sharing it, hence this component. The dark class and the storage key have
to match the head script that paints the theme before first render. --}}
<button type="button" class="icon-btn icon-btn-secondary" data-testid="theme-toggle"
        x-data="{ dark: document.documentElement.classList.contains('dark') }"
        @click="dark = ! dark; document.documentElement.classList.toggle('dark', dark); localStorage.setItem('atendia-theme', dark ? 'dark' : 'light')"
        :aria-label="dark ? 'Activar tema claro' : 'Activar tema oscuro'">
    <x-icon name="sun" :size="20" x-show="dark" x-cloak />
    <x-icon name="moon" :size="20" x-show="! dark" x-cloak />
</button>
