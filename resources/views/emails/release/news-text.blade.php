{{ __('mail.release_news.eyebrow', ['version' => $release['version']]) }}: {{ $release['title'] }}

{{ __('mail.release_news.intro', ['business' => $model->name]) }}

@foreach ($release['items'] as $item)
- {{ $item }}
@endforeach

{{ __('mail.release_news.cta') }}: {{ route('releases') }}

{{ __('mail.layout.rights', ['year' => now()->year, 'brand' => \App\Models\Company::brand()]) }}
