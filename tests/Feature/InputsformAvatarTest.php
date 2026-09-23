<?php

declare(strict_types=1);

/*
 * The <x-inputsform.avatar> primitive: round face (photo or initials), a
 * picker button, the opt-in remove, and the cropper panel wired to the
 * Livewire model the cropped square uploads to.
 */

it('shows the initials and the picker wired to its upload model', function () {
    $this->blade('<x-inputsform.avatar name="avatar_file" model="form.avatar_file" initials="MG" label="Foto" />')
        ->assertSee('avatar-field-face', false)
        ->assertSee('MG')
        ->assertSee('Foto')
        ->assertSee('inputsformAvatar', false)
        ->assertSee('form.avatar_file', false)
        ->assertSee('type="file"', false)
        ->assertSee('avatar-field-crop', false)
        ->assertSee(__('forms.avatar.pick'))
        ->assertDontSee(__('forms.avatar.remove'));
});

it('offers the remove button only when removable', function () {
    $this->blade('<x-inputsform.avatar name="avatar_file" model="form.avatar_file" preview="/a.webp" removable />')
        ->assertSee('/a.webp', false)
        ->assertSee(__('forms.avatar.remove'))
        ->assertSee('file-reset', false);
});

it('shows the server error under the field', function () {
    $this->blade('<x-inputsform.avatar name="avatar_file" model="form.avatar_file" error="La foto es muy grande." />')
        ->assertSee('La foto es muy grande.');
});
