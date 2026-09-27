{{ __('moderation.alert.title', ['business' => $model->business?->name]) }}

{{ __('moderation.alert.intro', ['source' => __('moderation.sources.'.$model->source), 'category' => $model->category, 'score' => $model->score]) }}

{{ __('moderation.severity.'.$model->severity->value) }}
{{ __('moderation.alert.cta') }}: {{ route('admin.moderation') }}

{{ __('mail.layout.rights', ['year' => now()->year]) }}
