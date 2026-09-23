@props([
    'glyph' => '&#9993;',
    'eyebrow',
    'title',
    'intro',
    'chip' => null,
    'body' => null,
    'alert' => null,
    'primaryUrl' => null,
    'primaryLabel' => null,
    'secondaryUrl' => null,
    'secondaryLabel' => null,
    'closing',
    'team',
])

{{-- The account notices share ONE hero grammar (contact-updated's): each
mail only says its words, so a tweak here reaches every one of them. --}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0">
    <tr>
        <td
            align="center"
            style="
                background-color: #0ea47a;
                background: linear-gradient(135deg, #0ea47a 0%, #0a6b50 100%);
                padding: 18px 40px 18px;
            "
        >
            <table role="presentation" cellpadding="0" cellspacing="0">
                <tr>
                    <td
                        align="center"
                        width="44"
                        height="44"
                        style="
                            width: 44px;
                            height: 44px;
                            border-radius: 50%;
                            background-color: rgba(255, 255, 255, 0.16);
                            border: 1px solid rgba(255, 255, 255, 0.4);
                            font-family: 'Sora', 'Segoe UI', Arial, sans-serif;
                            font-size: 19px;
                            font-weight: 800;
                            line-height: 44px;
                            color: #ffffff;
                        "
                    >
                        {!! $glyph !!}
                    </td>
                </tr>
            </table>

            <p
                style="
                    margin: 8px 0 0;
                    font-family: 'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif;
                    font-size: 12px;
                    font-weight: 700;
                    letter-spacing: 0.16em;
                    text-transform: uppercase;
                    color: #95eacb;
                "
            >
                {{ $eyebrow }}
            </p>

            <h1
                style="
                    margin: 4px 0 0;
                    font-family: 'Sora', 'Segoe UI', Arial, sans-serif;
                    font-size: 26px;
                    line-height: 1.2;
                    font-weight: 800;
                    color: #ffffff;
                "
            >
                {{ $title }}
            </h1>

            <p
                style="
                    margin: 8px 0 0;
                    font-family: 'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif;
                    font-size: 15px;
                    line-height: 1.6;
                    color: #e9fbf4;
                "
            >
                {{ $intro }}
            </p>
        </td>
    </tr>
    @if ($chip)
        <tr>
            <td align="center" style="padding: 18px 40px 0">
                <table role="presentation" cellpadding="0" cellspacing="0">
                    <tr>
                        <td
                            align="center"
                            style="
                                border: 1px solid #d5e5de;
                                border-radius: 999px;
                                background-color: #f2f8f5;
                                padding: 9px 22px;
                                font-family: 'JetBrains Mono', 'Courier New', monospace;
                                font-size: 14px;
                                font-weight: 700;
                                color: #0a6b50;
                            "
                        >
                            {{ $chip }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    @endif
    <tr>
        <td align="center" style="padding: 14px 40px 0">
            @if ($body)
                <p
                    style="
                        margin: 0;
                        font-family: 'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif;
                        font-size: 15px;
                        line-height: 1.6;
                        color: #3d4c47;
                    "
                >
                    {{ $body }}
                </p>
            @endif
            @if ($alert)
                <p
                    style="
                        margin: 10px 0 0;
                        font-family: 'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif;
                        font-size: 13px;
                        line-height: 1.6;
                        color: #3d4c47;
                    "
                >
                    {{ $alert }}
                </p>
            @endif
            @if ($primaryUrl)
                <table role="presentation" cellpadding="0" cellspacing="0" style="margin: 12px auto 0">
                    <tr>
                        <td
                            align="center"
                            style="
                                border-radius: 999px;
                                background-color: #0ea47a;
                                background: linear-gradient(135deg, #0ea47a 0%, #0a6b50 100%);
                            "
                        >
                            <a
                                href="{{ $primaryUrl }}"
                                style="
                                    display: inline-block;
                                    padding: 10px 22px;
                                    font-family: 'Sora', 'Segoe UI', Arial, sans-serif;
                                    font-size: 13px;
                                    font-weight: 800;
                                    color: #ffffff;
                                    text-decoration: none;
                                    border-radius: 999px;
                                "
                            >{{ $primaryLabel }}</a>
                        </td>
                    </tr>
                </table>
            @endif
            @if ($secondaryUrl)
                <table role="presentation" cellpadding="0" cellspacing="0" style="margin: 10px auto 0">
                    <tr>
                        <td align="center" style="border: 2px solid #0ea47a; border-radius: 999px">
                            <a
                                href="{{ $secondaryUrl }}"
                                style="
                                    display: inline-block;
                                    padding: 8px 18px;
                                    font-family: 'Sora', 'Segoe UI', Arial, sans-serif;
                                    font-size: 13px;
                                    font-weight: 800;
                                    color: #0a6b50;
                                    text-decoration: none;
                                    border-radius: 999px;
                                "
                            >{{ $secondaryLabel }}</a>
                        </td>
                    </tr>
                </table>
            @endif
        </td>
    </tr>
    <tr>
        <td align="center" style="padding: 16px 40px 18px">
            <p
                style="
                    margin: 0;
                    font-family: 'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif;
                    font-size: 15px;
                    line-height: 1.6;
                    color: #3d4c47;
                "
            >
                {{ $closing }}
            </p>
            <p
                style="
                    margin: 5px 0 0;
                    font-family: 'Sora', 'Segoe UI', Arial, sans-serif;
                    font-size: 15px;
                    font-weight: 800;
                    color: #0a6b50;
                "
            >
                {{ $team }}
            </p>
        </td>
    </tr>
    <tr>
        <td
            height="6"
            style="
                height: 6px;
                background-color: #0ea47a;
                background: linear-gradient(90deg, #0ea47a 0%, #25c490 100%);
                font-size: 0;
                line-height: 0;
            "
        >
            &nbsp;
        </td>
    </tr>
</table>
