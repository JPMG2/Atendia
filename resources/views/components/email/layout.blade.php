@props(['preheader' => null])

{{-- The chrome every AtendIa mail wears: wordmark, card and rights. Styles are
INLINE and colors are hex because mail clients strip <style> blocks and never
load app.css — the one place the brand lives outside the tokens. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>{{ config('app.name') }}</title>
</head>
<body style="margin:0; padding:0; background-color:#EEF4F1; -webkit-text-size-adjust:100%;">
    @if ($preheader)
        {{-- The line clients show next to the subject; never visible in the body. --}}
        <div style="display:none; max-height:0; overflow:hidden; mso-hide:all;">{{ $preheader }}&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;</div>
    @endif

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#EEF4F1;">
        <tr>
            <td align="center" style="padding:26px 16px;">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px; max-width:100%;">
                    <tr>
                        {{-- The wordmark is TEXT, not an image: it shows even in
                        clients that block every image by default. --}}
                        <td align="center" style="padding:0 0 18px;">
                            <span style="font-family:'Sora', 'Segoe UI', Arial, sans-serif; font-size:27px; font-weight:800; letter-spacing:-0.03em; color:#0B5440;">Atend<span style="color:#0EA47A;">ia</span></span>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:separate; background-color:#FFFFFF; border:1px solid #DCE7E2; border-radius:16px; overflow:hidden;">
                                <tr>
                                    <td>{{ $slot }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:18px 24px 0;">
                            <p style="margin:0; font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:12px; line-height:1.6; color:#7A8A84;">{{ __('mail.layout.rights', ['year' => now()->year]) }}</p>

                            @isset($footnote)
                                <p style="margin:6px 0 0; font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:12px; line-height:1.6; color:#7A8A84;">{{ $footnote }}</p>
                            @endisset
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
