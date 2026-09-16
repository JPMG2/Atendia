{{-- The clickable half of the Mailcheck pattern. Expects `mailHint` in the
Alpine scope and x-ref="emailInput" on the email field; the detection
itself (`suggestEmail()`) lives in form-guard.js. --}}
<button
    type="button"
    class="field-suggest"
    x-show="mailHint !== ''"
    x-cloak
    x-on:click="
        $refs.emailInput.value = mailHint;
        mailHint = '';
    "
>
    {{ __('forms.email.suggest') }} <span x-text="mailHint"></span>?
</button>
