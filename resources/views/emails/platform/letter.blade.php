@php
    // A letter of several paragraphs: escaped first, then its line breaks kept and read from the left, not centred.
    $letter = new \Illuminate\Support\HtmlString('<span style="display:block;text-align:left">'.nl2br(e($text)).'</span>');
@endphp
<x-email.layout :preheader="\Illuminate\Support\Str::limit($text, 90)">
    <x-email.notice
        :eyebrow="__('ask_admin.letter.eyebrow')"
        :title="$heading"
        :intro="__('ask_admin.letter.intro', ['name' => $business->name])"
        :body="$letter"
        :primary-url="route('dashboard')"
        :primary-label="__('ask_admin.letter.cta')"
        :closing="__('ask_admin.letter.closing')"
        :team="__('mail.account.team')"
    />
</x-email.layout>
