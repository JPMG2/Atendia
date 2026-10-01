{{ __('support.mail.title', ['code' => $model->code, 'business' => $model->business?->name]) }}

{{ __('support.kinds.'.$model->kind->value) }}

{{ $model->body }}

{{ __('support.mail.where', ['screen' => $model->screen !== null ? (\App\Models\Menu::titleFor($model->screen) ?? $model->screen) : __('support.no_screen')]) }}
{{ __('support.mail.cta') }}: {{ route('admin.support') }}

{{ __('mail.layout.rights', ['year' => now()->year]) }}
