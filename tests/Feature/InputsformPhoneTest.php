<?php

declare(strict_types=1);

/*
 * Golden-rule behaviours of the <x-inputsform.phone> primitive: a dial select
 * fed by the catalog plus the national number, composed into ONE hidden field
 * ("+58 4247673951") where wire:model lives — the server sees a single column.
 */

const PHONE_COUNTRIES = "[['code' => '54', 'flag' => '🇦🇷'], ['code' => '58', 'flag' => '🇻🇪']]";

it('renders the dial options and keeps the real value on the hidden field', function () {
    $this->blade('<x-inputsform.phone label="WhatsApp" name="whatsapp_number" :countries="'.PHONE_COUNTRIES.'" value="+58 4247673951" wire:model="form.data.whatsapp_number" />')
        ->assertSee('phone-dial', false)
        ->assertSee('+54', false)
        ->assertSee('+58', false)
        ->assertSee('type="hidden"', false)
        ->assertSee('value="+58 4247673951"', false)
        ->assertSee('wire:model="form.data.whatsapp_number"', false)
        ->assertSee('inputsformPhone', false);
});

it('marks required with the asterisk but never the native attribute', function () {
    $this->blade('<x-inputsform.phone label="WhatsApp" name="wa" :countries="'.PHONE_COUNTRIES.'" required />')
        ->assertSee('field-required', false)
        ->assertSee('aria-required', false)
        ->assertDontSee('required>', false);
});

it('wires the Alpine error by key, border and message included', function () {
    $this->blade('<x-inputsform.phone label="WhatsApp" name="wa" :countries="'.PHONE_COUNTRIES.'" alpine-error="whatsapp_number" />')
        ->assertSee('errors.whatsapp_number', false);
});

it('hands the default dial and the catalog dials to the Alpine component', function () {
    $this->blade('<x-inputsform.phone name="wa" :countries="'.PHONE_COUNTRIES.'" default-dial="58" />')
        ->assertSee('defaultDial', false)
        ->assertSee('dials', false)
        ->assertSee('+58', false);
});
